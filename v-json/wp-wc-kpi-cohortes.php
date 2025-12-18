<?php
/**
 * Plugin Name: WC KPI Cohortes
 * Description: Dashboard admin pour analyser les commandes par code postal / rue et afficher une carte 3D isométrique de la France.
 * Version: 0.3
 * Author: Antonin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_KPI_Cohortes {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_wc_kpi_get_data', array( $this, 'ajax_get_data' ) );
        add_action( 'wp_ajax_wc_kpi_geocode_postcode', array( $this, 'ajax_geocode_postcode' ) );
        add_shortcode( 'tdb_kpis', array( $this, 'shortcode_tdb_kpis' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );
    }

        /**
         * Query KPIs (Statuts des commandes)
         */
        private function query_kpis( $start, $end, $statuses = array('wc-completed','wc-processing','wc-on-hold') ) {
                global $wpdb;
                $order_stats = $wpdb->prefix . 'wc_order_stats';

                $start_dt = date('Y-m-d 00:00:00', strtotime($start));
                $end_dt   = date('Y-m-d 00:00:00', strtotime($end));

                $ph = implode(',', array_fill(0, count($statuses), '%s'));

                $sql = "
            SELECT
                COUNT(DISTINCT s.customer_id) AS total_customers,
                SUM(CASE WHEN f.first_order >= %s AND f.first_order < %s THEN 1 ELSE 0 END) AS new_customers
            FROM {$order_stats} AS s
            JOIN (
                SELECT customer_id, MIN(date_created) AS first_order
                FROM {$order_stats}
                WHERE customer_id > 0
                    AND status IN ($ph)
                GROUP BY customer_id
            ) AS f
                ON f.customer_id = s.customer_id
            WHERE s.customer_id > 0
                AND s.status IN ($ph)
                AND s.date_created >= %s AND s.date_created < %s
        ";

                // params order: dates (2) + statuses (n) + statuses (n) + dates (2)
                $params = array_merge(array($start_dt, $end_dt), $statuses, $statuses, array($start_dt, $end_dt));

                $row = $wpdb->get_row( $wpdb->prepare( $sql, $params ), ARRAY_A );

                $total = intval( $row['total_customers'] ?? 0 );
                $new   = intval( $row['new_customers'] ?? 0 );
                $ret   = max(0, $total - $new);

                $pct_new = $total ? round(100 * $new / $total, 2) : 0;
                $pct_ret = $total ? round(100 * $ret / $total, 2) : 0;

                return array_merge( compact('start_dt','end_dt'), array(
                        'statuses'      => $statuses,
                        'total'         => $total,
                        'new'           => $new,
                        'returning'     => $ret,
                        'pct_new'       => number_format($pct_new, 2),
                        'pct_returning' => number_format($pct_ret, 2),
                ) );
        }

        /**
         * Shortcode [tdb_kpis start="YYYY-MM-DD" end="YYYY-MM-DD" statuses="..."]
         */
        public function shortcode_tdb_kpis( $atts ) {
                $atts = shortcode_atts(array(
                        'start'    => date('Y-m-01'),
                        'end'      => date('Y-m-01', strtotime('+1 month')),
                        'statuses' => 'wc-completed,wc-processing,wc-on-hold'
                ), $atts, 'tdb_kpis');

                $statuses = array_filter(array_map('trim', explode(',', $atts['statuses'])));
                $k = $this->query_kpis( $atts['start'], $atts['end'], $statuses );

                ob_start();
                ?>
                <div class="tdb-kpis">
                    <p><strong>Période</strong> : <?php echo esc_html( $k['start_dt'] ); ?> → <?php echo esc_html( $k['end_dt'] ); ?></p>
                    <p><strong>Statuts</strong> : <?php echo esc_html( implode(', ', $k['statuses'] ) ); ?></p>
                    <p>Total clients acheteurs : <strong><?php echo intval( $k['total'] ); ?></strong></p>
                    <p>Nouveaux : <strong><?php echo intval( $k['new'] ); ?></strong> (<?php echo esc_html( $k['pct_new'] ); ?>%) — 
                         Récurrents : <strong><?php echo intval( $k['returning'] ); ?></strong> (<?php echo esc_html( $k['pct_returning'] ); ?>%)</p>
                </div>
                <?php
                return ob_get_clean();
        }

        /**
         * Dashboard widget (migrated)
         */
        public function dashboard_widget() {
                add_meta_box('tdb_kpi_widget', 'KPI Cohortes (Mois en cours)', function() {
                        $start = date('Y-m-01');
                        $end   = date('Y-m-01', strtotime('+1 month'));
                        $k     = $this->query_kpis($start, $end);
                        echo '<p>Période : <strong>'.esc_html($k['start_dt']).'</strong> → <strong>'.esc_html($k['end_dt']).'</strong></p>';
                        echo '<p>Total : <strong>'.intval($k['total']).'</strong> — Nouveaux : <strong>'.intval($k['new']).'</strong> ('.esc_html($k['pct_new']).'%) — Récurrents : <strong>'.intval($k['returning']).'</strong> ('.esc_html($k['pct_returning']).'%)</p>';
                }, 'dashboard', 'side', 'high');
        }

    public function add_admin_menu() {
        add_menu_page(
            'KPI Cohortes',
            'KPI Cohortes',
            'manage_options',
            'wc-kpi-cohortes',
            array( $this, 'admin_page' ),
            'dashicons-chart-area',
            56
        );
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_wc-kpi-cohortes' ) {
            return;
        }

        // Version dynamique basée sur la modification du fichier pour forcer le rechargement
        $css_version = filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/admin.css' );
        $js_version = filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/admin.js' );

        wp_enqueue_style( 'wc-kpi-admin-css', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', array(), $css_version );
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true );
        // Leaflet for OpenStreetMap
        wp_enqueue_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' );
        wp_enqueue_script( 'leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), null, true );
        // three.js kept for future advanced 3D rendering
        wp_enqueue_script( 'threejs', 'https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.min.js', array(), null, true );
        wp_enqueue_script( 'wc-kpi-admin-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js', array( 'jquery', 'chartjs', 'leaflet-js' ), $js_version, true );

        wp_localize_script( 'wc-kpi-admin-js', 'WCKPI', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wc_kpi_nonce' ),
            'nominatim_url' => 'https://nominatim.openstreetmap.org/search',
        ) );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>KPI Cohortes</h1>
            <h2 class="nav-tab-wrapper">
                <a href="#tab-dashboard" class="nav-tab nav-tab-active" data-tab="dashboard">Tableau de bord</a>
                <a href="#tab-map" class="nav-tab" data-tab="map">Carte France</a>
            </h2>

            <div id="tab-dashboard" class="wc-kpi-tab">
                <form id="wc-kpi-filters">
                    <h3>Filtres de commandes</h3>
                    
                    <div style="margin-bottom:16px;">
                        <label><strong>Période (de / à)</strong></label><br/>
                        <input type="date" name="date_from" value="<?php echo date('Y-m-01'); ?>" />
                        <input type="date" name="date_to" value="<?php echo date('Y-m-d'); ?>" />
                    </div>

                    <div style="margin-bottom:16px;">
                        <label><strong>Statut des commandes</strong></label><br/>
                        <select name="status">
                            <option value="any">Tous les statuts</option>
                            <option value="wc-completed" selected>Complétées (completed)</option>
                            <option value="wc-processing">En cours (processing)</option>
                            <option value="wc-on-hold">En attente (on-hold)</option>
                            <option value="wc-cancelled">Annulées (cancelled)</option>
                        </select>
                    </div>

                    <div style="margin-top:16px;">
                        <button type="submit" class="button button-primary" id="wc-kpi-submit">📊 Afficher les résultats</button>
                        <button type="button" class="button" id="wc-kpi-export-json" style="margin-left:8px;" disabled>📥 Exporter emails (JSON)</button>
                        <label style="display:inline-block;margin-left:16px;"><input type="checkbox" id="wc-kpi-debug" name="debug" value="1" /> Mode Debug</label>
                        <span class="spinner" style="float:none;margin-left:8px;"></span>
                    </div>
                </form>

                <div id="wc-kpi-results" style="margin-top:24px;display:none;">
                    <h3>Résultats par code postal</h3>
                    <div id="wc-kpi-summary" style="background:#f9f9f9;padding:12px;margin-bottom:16px;border-left:4px solid #2271b1;"></div>
                    
                    <table class="wp-list-table widefat fixed striped" id="wc-kpi-table">
                        <thead>
                            <tr>
                                <th style="width:120px;">Code Postal</th>
                                <th>Adresse</th>
                                <th style="width:100px;text-align:center;">Commandes</th>
                                <th style="width:120px;text-align:center;">Nouveaux</th>
                                <th style="width:120px;text-align:center;">Récurrents</th>
                                <th style="width:200px;">Emails</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    
                    <div style="margin-top:24px;">
                        <h3>Graphique</h3>
                        <canvas id="wc-kpi-chart" width="800" height="300"></canvas>
                    </div>
                </div>
            </div>

            <div id="tab-map" class="wc-kpi-tab" style="display:none;">
                <div style="margin-bottom:12px;">
                    <button type="button" class="button" id="wc-kpi-geocode-all">🗺️ Géolocaliser tous les codes postaux</button>
                    <span id="wc-kpi-geocode-status" style="margin-left:12px;"></span>
                </div>
                <div id="wc-kpi-map" style="width:100%;height:600px;background:#e0e0e0;"></div>
            </div>
        </div>
        <style>
            .wc-kpi-row-new { background-color: #d4edda !important; }
            .wc-kpi-row-returning { background-color: #fff3cd !important; }
        </style>
        <?php
    }

    public function ajax_get_data() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'permission' );
        }
        if ( ! check_ajax_referer( 'wc_kpi_nonce', '_wpnonce', false ) ) {
            if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_kpi_nonce' ) ) {
                wp_send_json_error( 'nonce' );
            }
        }

        global $wpdb;
        $prefix = $wpdb->prefix;
        $addresses_table = $prefix . 'wc_order_addresses';
        $posts_table = $prefix . 'posts';

        $date_from = isset( $_REQUEST['date_from'] ) && $_REQUEST['date_from'] ? $_REQUEST['date_from'] . ' 00:00:00' : null;
        $date_to   = isset( $_REQUEST['date_to'] ) && $_REQUEST['date_to'] ? $_REQUEST['date_to'] . ' 23:59:59' : null;
        $status    = isset( $_REQUEST['status'] ) && $_REQUEST['status'] && $_REQUEST['status'] !== 'any' ? sanitize_text_field( $_REQUEST['status'] ) : null;
        $debug     = isset( $_REQUEST['debug'] ) && $_REQUEST['debug'] == '1';

        // Build WHERE clause
        $where = "p.post_type = 'shop_order'";
        $params = array();
        if ( $date_from ) {
            $where .= ' AND p.post_date >= %s';
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where .= ' AND p.post_date <= %s';
            $params[] = $date_to;
        }
        if ( $status ) {
            $where .= ' AND p.post_status = %s';
            $params[] = $status;
        }

        // Check if custom addresses table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$addresses_table}'" ) === $addresses_table;

        // Debug logging
        if ( $debug ) {
            error_log( "[WC KPI DEBUG] [" . date('Y-m-d H:i:s') . "] Table exists: " . ($table_exists ? 'YES' : 'NO') );
            error_log( "[WC KPI DEBUG] Date range: {$date_from} to {$date_to}, Status: " . ($status ?: 'any') );
        }

        if ( $table_exists ) {
            // Use custom addresses table
            $sql = "SELECT addr.order_id, addr.postcode, addr.address_1, addr.email, p.post_date 
                    FROM {$addresses_table} addr 
                    JOIN {$posts_table} p ON p.ID = addr.order_id 
                    WHERE {$where}";
            if ( ! empty( $params ) ) {
                $addr_rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
            } else {
                $addr_rows = $wpdb->get_results( $sql );
            }
            
            if ( $debug ) {
                error_log( "[WC KPI DEBUG] Custom table query returned " . count($addr_rows) . " rows" );
                if ( $wpdb->last_error ) {
                    error_log( "[WC KPI DEBUG] SQL Error: " . $wpdb->last_error );
                }
            }
        } else {
            // Fallback to postmeta
            if ( $debug ) {
                error_log( "[WC KPI DEBUG] Using postmeta fallback" );
            }
            
            $postmeta_table = $prefix . 'postmeta';
            $order_ids_sql = "SELECT ID FROM {$posts_table} p WHERE {$where}";
            if ( ! empty( $params ) ) {
                $order_ids = $wpdb->get_col( $wpdb->prepare( $order_ids_sql, $params ) );
            } else {
                $order_ids = $wpdb->get_col( $order_ids_sql );
            }

            if ( $debug ) {
                error_log( "[WC KPI DEBUG] Found " . count($order_ids) . " order IDs matching filters" );
                if ( ! empty( $order_ids ) ) {
                    error_log( "[WC KPI DEBUG] Sample order IDs: " . implode(', ', array_slice($order_ids, 0, 5)) );
                }
            }

            if ( empty( $order_ids ) ) {
                if ( $debug ) {
                    error_log( "[WC KPI DEBUG] No orders found, returning empty array" );
                }
                wp_send_json_success( array() );
            }

            $order_ids_in = implode( ',', array_map( 'absint', $order_ids ) );
            $meta_sql = "SELECT post_id, meta_key, meta_value FROM {$postmeta_table} 
                         WHERE post_id IN ({$order_ids_in}) 
                         AND meta_key IN ('_billing_postcode','_shipping_postcode','_billing_address_1','_shipping_address_1','_billing_email')";
            $meta_rows = $wpdb->get_results( $meta_sql );

            if ( $debug ) {
                error_log( "[WC KPI DEBUG] Fetched " . count($meta_rows) . " postmeta rows" );
            }

            // Pivot postmeta into address rows
            $pivot = array();
            foreach ( $meta_rows as $m ) {
                $pid = $m->post_id;
                if ( ! isset( $pivot[ $pid ] ) ) $pivot[ $pid ] = array();
                $pivot[ $pid ][ $m->meta_key ] = $m->meta_value;
            }

            $addr_rows = array();
            foreach ( $pivot as $pid => $vals ) {
                $order_date = $wpdb->get_var( $wpdb->prepare( "SELECT post_date FROM {$posts_table} WHERE ID = %d", $pid ) );
                $row = new stdClass();
                $row->order_id = $pid;
                $row->postcode = ! empty( $vals['_shipping_postcode'] ) ? $vals['_shipping_postcode'] : ( $vals['_billing_postcode'] ?? '' );
                $row->address_1 = ! empty( $vals['_shipping_address_1'] ) ? $vals['_shipping_address_1'] : ( $vals['_billing_address_1'] ?? '' );
                $row->email = $vals['_billing_email'] ?? '';
                $row->post_date = $order_date;
                $addr_rows[] = $row;
            }
            
            if ( $debug ) {
                error_log( "[WC KPI DEBUG] Pivoted into " . count($addr_rows) . " address rows" );
            }
        }

        if ( empty( $addr_rows ) ) {
            if ( $debug ) {
                error_log( "[WC KPI DEBUG] No address rows found after processing" );
            }
            wp_send_json_success( array() );
        }

        // Collect all unique emails to determine first order date
        $all_emails = array();
        foreach ( $addr_rows as $a ) {
            if ( ! empty( $a->email ) ) {
                $all_emails[] = trim( strtolower( $a->email ) );
            }
        }
        $all_emails = array_values( array_unique( $all_emails ) );

        // Get first order date for each email (works with both table and postmeta)
        $email_first = array();
        if ( ! empty( $all_emails ) ) {
            if ( $table_exists ) {
                $placeholders = implode( ',', array_fill( 0, count( $all_emails ), '%s' ) );
                $first_sql = "SELECT addr.email, MIN(p.post_date) AS first_date 
                              FROM {$addresses_table} addr 
                              JOIN {$posts_table} p ON p.ID = addr.order_id 
                              WHERE addr.email IN ({$placeholders}) AND p.post_type = 'shop_order' 
                              GROUP BY addr.email";
                $first_rows = $wpdb->get_results( $wpdb->prepare( $first_sql, ...$all_emails ) );
                foreach ( $first_rows as $fr ) {
                    $email_first[ trim( strtolower( $fr->email ) ) ] = $fr->first_date;
                }
            } else {
                // Fallback: find first order per email via postmeta
                $postmeta_table = $prefix . 'postmeta';
                $placeholders = implode( ',', array_fill( 0, count( $all_emails ), '%s' ) );
                $first_sql = "SELECT pm.meta_value AS email, MIN(p.post_date) AS first_date 
                              FROM {$postmeta_table} pm 
                              JOIN {$posts_table} p ON p.ID = pm.post_id 
                              WHERE pm.meta_key = '_billing_email' 
                              AND pm.meta_value IN ({$placeholders}) 
                              AND p.post_type = 'shop_order' 
                              GROUP BY pm.meta_value";
                $first_rows = $wpdb->get_results( $wpdb->prepare( $first_sql, ...$all_emails ) );
                foreach ( $first_rows as $fr ) {
                    $email_first[ trim( strtolower( $fr->email ) ) ] = $fr->first_date;
                }
            }
        }

        // Aggregate by postcode + address
        $map = array();
        foreach ( $addr_rows as $a ) {
            $pc = trim( $a->postcode );
            if ( $pc === '' ) $pc = 'N/A';
            $key = $pc . '||' . trim( $a->address_1 );
            
            if ( ! isset( $map[ $key ] ) ) {
                $map[ $key ] = array(
                    'postcode' => $pc,
                    'street' => trim( $a->address_1 ),
                    'orders' => 0,
                    'emails' => array(),
                    'new_customers' => 0,
                    'returning_customers' => 0,
                );
            }
            
            $map[ $key ]['orders']++;
            
            if ( ! empty( $a->email ) ) {
                $e = trim( strtolower( $a->email ) );
                if ( ! in_array( $e, $map[ $key ]['emails'] ) ) {
                    $map[ $key ]['emails'][] = $e;
                    
                    // Check if new customer (first order in period)
                    if ( isset( $email_first[ $e ] ) ) {
                        $fd = $email_first[ $e ];
                        if ( $date_from && $fd >= $date_from && ( ! $date_to || $fd <= $date_to ) ) {
                            $map[ $key ]['new_customers']++;
                        } else {
                            $map[ $key ]['returning_customers']++;
                        }
                    } else {
                        // No prior order found, consider new
                        $map[ $key ]['new_customers']++;
                    }
                }
            }
        }

        // Convert to array and sort by orders desc
        $final = array_values( $map );
        usort( $final, function( $a, $b ) {
            return $b['orders'] - $a['orders'];
        } );

        if ( $debug ) {
            error_log( "[WC KPI DEBUG] Returning " . count($final) . " aggregated results" );
        }

        wp_send_json_success( $final );
    }

    /**
     * Geocode a postcode using Nominatim and cache the result in options
     */
    public function ajax_geocode_postcode() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'permission' );
        }
        if ( ! check_ajax_referer( 'wc_kpi_nonce', '_wpnonce', false ) ) {
            if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_kpi_nonce' ) ) {
                wp_send_json_error( 'nonce' );
            }
        }

        $postcode = isset( $_REQUEST['postcode'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['postcode'] ) ) : '';
        if ( ! $postcode ) {
            wp_send_json_error( 'missing_postcode' );
        }

        $option_key = 'wc_kpi_geo_' . md5( $postcode );
        $cached = get_option( $option_key );
        if ( $cached ) {
            wp_send_json_success( $cached );
        }

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(array(
            'postalcode' => $postcode,
            'country' => 'France',
            'format' => 'json',
            'limit' => 1,
        ));

        $response = wp_remote_get( $url, array( 'user-agent' => 'WP WC KPI Cohortes - email@example.com' ) );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( empty( $data ) || ! is_array( $data ) ) {
            wp_send_json_error( 'no_result' );
        }
        $res = array(
            'lat' => $data[0]['lat'],
            'lon' => $data[0]['lon'],
            'display_name' => $data[0]['display_name'],
        );
        // cache for 30 days
        update_option( $option_key, $res );
        wp_send_json_success( $res );
    }
}

new WC_KPI_Cohortes();
