jQuery(document).ready(function($) {
    let currentData = [];
    let map = null;
    let markers = [];
    let currentChart = null;

    // Helper: safely get canvas 2D context to avoid "getContext is null" errors
    // Usage: const ctx = safeGetCanvasContext('myChart'); if (ctx) { new Chart(ctx, ...) }
    function safeGetCanvasContext(id) {
        const el = document.getElementById(id);
        if (!el) {
            console.warn('WC KPI: canvas #' + id + ' not found; ensure the element exists before initializing Chart.js and verify the Chart.js CDN URL (for example: https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js).');
            return null;
        }
        if (typeof el.getContext !== 'function') {
            console.warn('WC KPI: element #' + id + ' does not support getContext');
            return null;
        }
        return el.getContext('2d');
    }

    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const tab = $(this).data('tab');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.wc-kpi-tab').hide();
        $('#tab-' + tab).show();
        
        if (tab === 'map' && !map) {
            initMap();
        }
    });

    // Form submission
    $('#wc-kpi-filters').on('submit', function(e) {
        e.preventDefault();
        loadData();
    });

    // Load data via AJAX
    function loadData() {
        const formData = $('#wc-kpi-filters').serializeArray();
        const data = {
            action: 'wc_kpi_get_data',
            nonce: WCKPI.nonce
        };
        
        formData.forEach(item => {
            data[item.name] = item.value;
        });

        console.log('[WC KPI] Sending AJAX request with data:', data);

        $('.spinner').addClass('is-active');
        $('#wc-kpi-submit').prop('disabled', true);

        $.post(WCKPI.ajax_url, data, function(response) {
            $('.spinner').removeClass('is-active');
            $('#wc-kpi-submit').prop('disabled', false);

            console.log('[WC KPI] AJAX response:', response);

            if (response.success) {
                currentData = response.data;
                
                if (currentData.length === 0) {
                    alert('Aucune commande trouvée pour cette période/statut.\n\nVérifiez :\n- La période sélectionnée\n- Le statut des commandes\n- Que des commandes existent dans WooCommerce\n\nActivez le mode Debug pour plus d\'informations dans les logs.');
                    $('#wc-kpi-results').hide();
                } else {
                    displayResults(currentData);
                    $('#wc-kpi-export-json').prop('disabled', false);
                }
            } else {
                alert('Erreur lors du chargement des données: ' + (response.data || 'Erreur inconnue'));
            }
        }).fail(function(xhr, status, error) {
            $('.spinner').removeClass('is-active');
            $('#wc-kpi-submit').prop('disabled', false);
            console.error('[WC KPI] AJAX error:', xhr, status, error);
            alert('Erreur de communication avec le serveur. Vérifiez la console pour plus de détails.');
        });
    }

    // Display results in table
    function displayResults(data) {
        if (!data || data.length === 0) {
            $('#wc-kpi-results').hide();
            alert('Aucun résultat trouvé pour cette période');
            return;
        }

        // Summary
        const totalOrders = data.reduce((sum, row) => sum + row.orders, 0);
        const totalNew = data.reduce((sum, row) => sum + row.new_customers, 0);
        const totalReturning = data.reduce((sum, row) => sum + row.returning_customers, 0);
        const uniquePostcodes = new Set(data.map(r => r.postcode)).size;

        $('#wc-kpi-summary').html(`
            <strong>${data.length}</strong> adresses différentes — 
            <strong>${uniquePostcodes}</strong> codes postaux — 
            <strong>${totalOrders}</strong> commandes — 
            <strong style="color:#28a745;">${totalNew}</strong> nouveaux clients — 
            <strong style="color:#ffc107;">${totalReturning}</strong> clients récurrents
        `);

        // Table rows
        const tbody = $('#wc-kpi-table tbody');
        tbody.empty();

        data.forEach(row => {
            const rowClass = row.new_customers > row.returning_customers ? 'wc-kpi-row-new' : 
                           row.returning_customers > 0 ? 'wc-kpi-row-returning' : '';
            
            const emailsDisplay = row.emails && row.emails.length > 0 ? 
                row.emails.slice(0, 3).join(', ') + (row.emails.length > 3 ? ` (+${row.emails.length - 3})` : '') : 
                '-';

            tbody.append(`
                <tr class="${rowClass}" data-postcode="${row.postcode}">
                    <td><strong>${row.postcode}</strong></td>
                    <td>${row.street || '-'}</td>
                    <td style="text-align:center;"><span class="badge">${row.orders}</span></td>
                    <td style="text-align:center;color:#28a745;"><strong>${row.new_customers}</strong></td>
                    <td style="text-align:center;color:#ffc107;"><strong>${row.returning_customers}</strong></td>
                    <td style="font-size:11px;" title="${row.emails ? row.emails.join(', ') : ''}">${emailsDisplay}</td>
                </tr>
            `);
        });

        $('#wc-kpi-results').show();
        
        // Render chart
        renderChart(data);
    }

    // Render Chart.js graph
    function renderChart(data) {
        const canvas = document.getElementById('wc-kpi-chart');
        if (!canvas) {
            console.warn('[WC KPI] Canvas element not found, skipping chart');
            return;
        }

        const ctx = canvas.getContext('2d');
        
        // Destroy previous chart if exists
        if (currentChart) {
            currentChart.destroy();
        }

        // Prepare data for chart (top 10 postcodes by orders)
        const topData = data.slice(0, 10);
        const labels = topData.map(r => r.postcode);
        const orders = topData.map(r => r.orders);
        const newCustomers = topData.map(r => r.new_customers);
        const returningCustomers = topData.map(r => r.returning_customers);

        currentChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Nouveaux clients',
                        data: newCustomers,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Clients récurrents',
                        data: returningCustomers,
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true,
                        title: { display: true, text: 'Code Postal' }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        title: { display: true, text: 'Nombre de clients' }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Top 10 Codes Postaux - Nouveaux vs Récurrents'
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
    }

    // Export emails as JSON
    $('#wc-kpi-export-json').on('click', function() {
        const allEmails = [];
        currentData.forEach(row => {
            if (row.emails && row.emails.length > 0) {
                row.emails.forEach(email => {
                    if (!allEmails.includes(email)) {
                        allEmails.push(email);
                    }
                });
            }
        });

        if (allEmails.length === 0) {
            alert('Aucune adresse email à exporter');
            return;
        }

        const blob = new Blob([JSON.stringify(allEmails, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'emails-kpi-' + new Date().toISOString().split('T')[0] + '.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // Initialize Leaflet map
    function initMap() {
        if (map) return;

        map = L.map('wc-kpi-map').setView([46.603354, 1.888334], 6); // Center of France
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(map);
    }

    // Geocode all postcodes and plot on map
    $('#wc-kpi-geocode-all').on('click', function() {
        if (currentData.length === 0) {
            alert('Veuillez d\'abord charger des données dans l\'onglet "Tableau de bord"');
            return;
        }

        if (!map) {
            initMap();
        }

        const uniquePostcodes = [...new Set(currentData.map(r => r.postcode))].filter(p => p !== 'N/A');
        
        if (uniquePostcodes.length === 0) {
            alert('Aucun code postal valide à géolocaliser');
            return;
        }

        let processed = 0;

        $('#wc-kpi-geocode-status').text(`Géolocalisation en cours... 0/${uniquePostcodes.length}`);
        $(this).prop('disabled', true);

        // Clear existing markers
        markers.forEach(m => map.removeLayer(m));
        markers = [];

        // Geocode each postcode with delay to respect rate limits
        uniquePostcodes.forEach((postcode, index) => {
            setTimeout(() => {
                $.post(WCKPI.ajax_url, {
                    action: 'wc_kpi_geocode_postcode',
                    nonce: WCKPI.nonce,
                    postcode: postcode
                }, function(response) {
                    processed++;
                    $('#wc-kpi-geocode-status').text(`Géolocalisation... ${processed}/${uniquePostcodes.length}`);

                    if (response.success && response.data) {
                        const lat = parseFloat(response.data.lat);
                        const lon = parseFloat(response.data.lon);
                        
                        // Find orders for this postcode
                        const ordersForPostcode = currentData.filter(r => r.postcode === postcode);
                        const totalOrders = ordersForPostcode.reduce((sum, r) => sum + r.orders, 0);
                        const newCustomers = ordersForPostcode.reduce((sum, r) => sum + r.new_customers, 0);
                        const returningCustomers = ordersForPostcode.reduce((sum, r) => sum + r.returning_customers, 0);

                        const marker = L.marker([lat, lon]).addTo(map);
                        marker.bindPopup(`
                            <strong>${postcode}</strong><br>
                            ${totalOrders} commande(s)<br>
                            ${newCustomers} nouveau(x) client(s)<br>
                            ${returningCustomers} client(s) récurrent(s)
                        `);

                        markers.push(marker);
                    } else {
                        console.error('Geocoding response error for', postcode, response);
                    }
                }).fail(function() {
                    // jQuery passes (jqXHR, textStatus, errorThrown) — access the third argument to avoid unused param warnings
                    const error = arguments[2];
                    console.error('Geocoding error for', postcode, error);
                    processed++;
                    $('#wc-kpi-geocode-status').text(`Géolocalisation... ${processed}/${uniquePostcodes.length} (erreur: ${postcode})`);
                    
                    if (processed === uniquePostcodes.length) {
                        $('#wc-kpi-geocode-all').prop('disabled', false);
                    }
                });
            }, index * 1100); // 1.1s delay between requests (Nominatim rate limit)
        });
    });
});
