(function($){
    $(function(){
        // tabs
        $('.nav-tab').on('click', function(e){
            e.preventDefault();
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.wc-kpi-tab').hide();
            $($(this).attr('href')).show();
        });

        // add chart type selector and compare toggle dynamically
        var controlsHtml = '<label>Type de graphique</label><select name="chart_type" id="wc-kpi-chart-type"><option value="bar">Histogramme</option><option value="line">Courbe</option></select>' +
            '<label style="margin-left:12px;"><input type="checkbox" name="compare_products" id="wc-kpi-compare-products" value="1" /> Comparatif produits</label>' +
            '<label style="margin-left:12px;"><input type="checkbox" name="debug" id="wc-kpi-debug" value="1" /> Debug</label>';
        $('#wc-kpi-filters').append('<div style="margin-top:8px;">' + controlsHtml + '</div>');

        $('#wc-kpi-filters').on('submit', function(e){
            e.preventDefault();
            // build payload: handle multi-select products and 'all products' checkbox
            var payload = { action: 'wc_kpi_get_data', nonce: WCKPI.nonce };
            var formArray = $(this).serializeArray();
            formArray.forEach(function(f){
                // skip the product_select[] here (we handle below)
                if (f.name === 'product_select[]') return;
                payload[f.name] = f.value;
            });

            // products handling
            if ( $('#wc-kpi-all-products').is(':checked') ) {
                payload['product_ids'] = ''; // empty => all products
            } else {
                var prodVals = $('#wc-kpi-product-select').val() || [];
                if (prodVals.length) payload['product_ids'] = prodVals.join(',');
            }

            // debug logs
            console.log('WC KPI payload', payload);

            $.post(WCKPI.ajax_url, payload, function(resp){
                if ( resp && resp.success ) {
                    var rows = resp.data;
                    // keep rows globally for map / 3D
                    window.wcKpiLastRows = rows;
                    var labels = rows.map(function(r){ return r.postcode || 'N/A'; });
                    var values = rows.map(function(r){ return parseInt(r.orders,10) || 0; });

                    var chartType = $('#wc-kpi-chart-type').val() || 'bar';
                    // handle compare_products: if product breakdown present, create multiple datasets
                    var datasets = [];
                    if ( rows.length && rows[0].products ) {
                        // rows[i].products expected as JSON string or object {product_id:count}
                        var productIds = {};
                        rows.forEach(function(r){
                            var p = (typeof r.products === 'string') ? JSON.parse(r.products) : r.products;
                            Object.keys(p).forEach(function(pid){ productIds[pid] = true; });
                        });
                        productIds = Object.keys(productIds);
                        productIds.forEach(function(pid, idx){
                            var dataSet = rows.map(function(r){ var p = (typeof r.products === 'string') ? JSON.parse(r.products) : r.products; return parseInt(p[pid]||0,10); });
                            datasets.push({ label: 'Produit ' + pid, data: dataSet, backgroundColor: 'rgba(' + (50+idx*30) + ',100,200,0.6)' });
                        });
                    } else {
                        datasets.push({ label: 'Commandes', data: values, backgroundColor: 'rgba(54,162,235,0.6)' });
                    }

                    if ( window.wcKpiChart ) { window.wcKpiChart.destroy(); }
                    var ctx = document.getElementById('wc-kpi-chart').getContext('2d');
                    window.wcKpiChart = new Chart(ctx, {
                        type: chartType,
                        data: { labels: labels, datasets: datasets },
                        options: { responsive: true, maintainAspectRatio: false }
                    });

                    // populate map with top N postcodes
                    populateMapWithRows(rows);
                } else {
                    var err = (resp && resp.data && resp.data.error) ? resp.data.error : JSON.stringify(resp);
                    alert('Erreur récupération données: ' + err);
                }
            });
        });

        // Map initialization using Leaflet + OpenStreetMap tiles
        var Lmap, markersLayer;
        function initMap(){
            var container = document.getElementById('wc-kpi-map');
            if (!container) return;
            container.innerHTML = '';
            // ensure container has explicit size before creating map
            container.style.width = '100%';
            container.style.height = '600px';
            Lmap = L.map(container).setView([46.6, 2.4], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(Lmap);
            markersLayer = L.layerGroup().addTo(Lmap);
            // after a short delay, invalidate size so Leaflet recalculates and fills container
            setTimeout(function(){ try{ Lmap.invalidateSize(); }catch(e){ console.warn('invalidateSize failed', e); } }, 150);
        }

        function geocodePostcode(postcode){
            return new Promise(function(resolve, reject){
                var key = 'wc_kpi_geo_' + postcode;
                try { var cached = JSON.parse(localStorage.getItem(key)); if (cached) return resolve(cached); } catch(e){}
                $.post(WCKPI.ajax_url, { action: 'wc_kpi_geocode_postcode', nonce: WCKPI.nonce, postcode: postcode }, function(resp){
                    if (resp && resp.success){
                        localStorage.setItem(key, JSON.stringify(resp.data));
                        resolve(resp.data);
                    } else {
                        reject(resp && resp.data ? resp.data : resp);
                    }
                }).fail(function(err){ reject(err); });
            });
        }

        function populateMapWithRows(rows){
            if (!Lmap) initMap();
            markersLayer.clearLayers();
            var toProcess = rows.slice(0,50); // limit to 50 for geocoding
            var promises = toProcess.map(function(r){ return geocodePostcode(r.postcode).then(function(coords){ return { row: r, coords: coords }; }).catch(function(){ return null; }); });
            Promise.all(promises).then(function(results){
                results.forEach(function(item){
                        if (!item || !item.coords) return;
                        // attach geocode result back to original row so 3D can use it
                        try { item.row._geo = item.coords; } catch(e){}
                        var lat = parseFloat(item.coords.lat), lon = parseFloat(item.coords.lon);
                        var orders = parseInt(item.row.orders,10)||0;
                        var unique_customers = parseInt(item.row.unique_customers,10) || 0;
                        var new_orders = parseInt(item.row.new_orders,10) || 0;
                        var returning_customers = parseInt(item.row.returning_customers,10) || 0;
                        var radius = Math.min(50, 5 + Math.sqrt(orders) * 3);
                        // color: green if majority of orders are from new customers
                        var color = '#0073aa';
                        if (orders > 0 && (new_orders / orders) >= 0.5) color = '#2ecc71';
                        var popup = '<strong>' + (item.row.postcode||'N/A') + '</strong><br/>' + (item.row.street||'') + '<br/>' +
                            'Commandes: ' + orders + '<br/>' +
                            'Commandes provenant de nouveaux clients: ' + new_orders + '<br/>' +
                            'Clients uniques: ' + unique_customers + '<br/>' +
                            'Clients récurrents: ' + returning_customers;
                        var circle = L.circle([lat, lon], { radius: radius * 30, color: color, fillColor: color, fillOpacity: 0.6 }).bindPopup(popup);
                        markersLayer.addLayer(circle);
                    });
            });
        }

        $('a[href="#tab-map"]').on('click', function(){
            // when the map tab becomes visible, either init or refresh sizing
            setTimeout(function(){
                if (!Lmap) {
                    initMap();
                } else {
                    try{ Lmap.invalidateSize(); } catch(e){ console.warn('invalidateSize', e); }
                }
            }, 200);
        });

        // store last rows for 3D rendering
        window.wcKpiLastRows = [];

        // add 3D toggle button
        var btn3d = $('<button class="button" id="wc-kpi-3d-toggle" style="margin-left:12px;">Basculer en 3D</button>');
        $('#wc-kpi-charts').before(btn3d);

        $('#wc-kpi-3d-toggle').on('click', function(){
            if ($(this).data('3d') ) {
                // switch back to 2D (Leaflet)
                $(this).data('3d', false).text('Basculer en 3D');
                $('#wc-kpi-map canvas.threejs-overlay').remove();
                if (Lmap) Lmap.getContainer().style.display = 'block';
                return;
            }
            $(this).data('3d', true).text('Quitter 3D');
            // hide leaflet container
            if (Lmap) Lmap.getContainer().style.display = 'none';
            // create three.js overlay
            var container = document.getElementById('wc-kpi-map');
            var overlay = document.createElement('canvas');
            overlay.className = 'threejs-overlay';
            overlay.style.position = 'absolute';
            overlay.style.left = '0'; overlay.style.top = '0'; overlay.width = container.clientWidth; overlay.height = container.clientHeight;
            container.appendChild(overlay);

            // basic Three.js scene
            var scene = new THREE.Scene();
            var renderer = new THREE.WebGLRenderer({ canvas: overlay, antialias: true, alpha: true });
            renderer.setSize(container.clientWidth, container.clientHeight);
            var camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 10000);
            camera.position.set(0, 500, 500);
            camera.lookAt(new THREE.Vector3(0,0,0));

            var light = new THREE.DirectionalLight(0xffffff, 1);
            light.position.set(0.5, 1, 0.5).normalize();
            scene.add(light);

            // prepare geometry for bars
            var rows = window.wcKpiLastRows || [];
            if (!rows.length) {
                console.warn('Aucune donnée pour le mode 3D');
            }

            // compute lat/lon bbox
            var lats = [], lons = [];
            rows.forEach(function(r){ if (r._geo && r._geo.lat && r._geo.lon){ lats.push(parseFloat(r._geo.lat)); lons.push(parseFloat(r._geo.lon)); } });
            if (lats.length === 0) {
                // try geocoding on the fly (not ideal) - fallback to nothing
                console.warn('No geocoded points available for 3D');
            }

            var latMin = Math.min.apply(null, lats), latMax = Math.max.apply(null, lats);
            var lonMin = Math.min.apply(null, lons), lonMax = Math.max.apply(null, lons);
            var width = lonMax - lonMin || 1;
            var depth = latMax - latMin || 1;

            // add a ground plane (simple)
            var planeGeo = new THREE.PlaneGeometry( width*1000, depth*1000, 1,1 );
            var planeMat = new THREE.MeshBasicMaterial({ color: 0xf3f3f3, opacity: 0.6, transparent: true });
            var plane = new THREE.Mesh(planeGeo, planeMat);
            plane.rotation.x = -Math.PI/2; scene.add(plane);

            // add bars
            rows.forEach(function(r, idx){
                if (!r._geo || !r._geo.lat) return;
                var lat = parseFloat(r._geo.lat), lon = parseFloat(r._geo.lon);
                // map lon/lat to x/z coordinates in scene
                var x = ((lon - lonMin) / width - 0.5) * width * 1000;
                var z = ((latMax - lat) / depth - 0.5) * depth * 1000;
                var height = Math.max(5, Math.sqrt(parseInt(r.orders,10)||1) * 20);
                var geom = new THREE.BoxGeometry( 50, height, 50 );
                var newOrders = parseInt(r.new_orders || 0,10);
                var color = 0x0073aa;
                var unique = parseInt(r.unique_customers||0,10) || 1;
                if ( newOrders / unique >= 0.5 ) color = 0x2ecc71;
                var mat = new THREE.MeshLambertMaterial({ color: color });
                var mesh = new THREE.Mesh(geom, mat);
                mesh.position.set(x, height/2, z);
                scene.add(mesh);
            });

            function animate(){ requestAnimationFrame(animate); renderer.render(scene, camera); }
            animate();
        });
        $('#wc-kpi-charts').css('height','360px');
    });
})(jQuery);
