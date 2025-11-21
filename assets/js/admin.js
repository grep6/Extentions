jQuery(document).ready(function($) {
    let currentData = [];
    let map = null;
    let markers = [];

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
            _wpnonce: WCKPI.nonce
        };
        
        formData.forEach(item => {
            data[item.name] = item.value;
        });

        $('.spinner').addClass('is-active');
        $('#wc-kpi-submit').prop('disabled', true);

        $.post(WCKPI.ajax_url, data, function(response) {
            $('.spinner').removeClass('is-active');
            $('#wc-kpi-submit').prop('disabled', false);

            if (response.success) {
                currentData = response.data;
                displayResults(currentData);
                $('#wc-kpi-export-json').prop('disabled', currentData.length === 0);
            } else {
                alert('Erreur lors du chargement des données: ' + (response.data || 'Erreur inconnue'));
            }
        }).fail(function() {
            $('.spinner').removeClass('is-active');
            $('#wc-kpi-submit').prop('disabled', false);
            alert('Erreur de communication avec le serveur');
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
            
            const emailsDisplay = row.emails.length > 0 ? 
                row.emails.slice(0, 3).join(', ') + (row.emails.length > 3 ? '...' : '') : 
                '-';

            tbody.append(`
                <tr class="${rowClass}" data-postcode="${row.postcode}">
                    <td><strong>${row.postcode}</strong></td>
                    <td>${row.street || '-'}</td>
                    <td style="text-align:center;"><span class="badge">${row.orders}</span></td>
                    <td style="text-align:center;color:#28a745;"><strong>${row.new_customers}</strong></td>
                    <td style="text-align:center;color:#ffc107;"><strong>${row.returning_customers}</strong></td>
                    <td style="font-size:11px;">${emailsDisplay}</td>
                </tr>
            `);
        });

        $('#wc-kpi-results').show();
    }

    // Export emails as JSON
    $('#wc-kpi-export-json').on('click', function() {
        const allEmails = [];
        currentData.forEach(row => {
            row.emails.forEach(email => {
                if (!allEmails.includes(email)) {
                    allEmails.push(email);
                }
            });
        });

        const blob = new Blob([JSON.stringify(allEmails, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'emails-kpi-' + new Date().toISOString().split('T')[0] + '.json';
        a.click();
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

        if (currentData.length > 0) {
            plotMarkersOnMap();
        }
    }

    // Geocode all postcodes and plot on map
    $('#wc-kpi-geocode-all').on('click', function() {
        if (currentData.length === 0) {
            alert('Veuillez d\'abord charger des données');
            return;
        }

        const uniquePostcodes = [...new Set(currentData.map(r => r.postcode))].filter(p => p !== 'N/A');
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
                    _wpnonce: WCKPI.nonce,
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

                        const marker = L.marker([lat, lon]).addTo(map);
                        marker.bindPopup(`
                            <strong>${postcode}</strong><br>
                            ${totalOrders} commande(s)<br>
                            <span style="color:#28a745;">${newCustomers} nouveau(x)</span>
                        `);
                        markers.push(marker);
                    }

                    if (processed === uniquePostcodes.length) {
                        $('#wc-kpi-geocode-status').text(`✓ ${processed} codes postaux géolocalisés`);
                        $('#wc-kpi-geocode-all').prop('disabled', false);
                        
                        if (markers.length > 0) {
                            const group = new L.featureGroup(markers);
                            map.fitBounds(group.getBounds().pad(0.1));
                        }
                    }
                });
            }, index * 1100); // 1.1s delay between requests (Nominatim rate limit)
        });
    });

    function plotMarkersOnMap() {
        // Auto-plot if geocoding data already cached
        // This would require storing geo data with each postcode
    }
});
