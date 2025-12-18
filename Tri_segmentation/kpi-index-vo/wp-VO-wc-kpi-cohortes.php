<?php
/**
 * Plugin Name: WC KPI Cohortes
 * Description: Dashboard admin pour analyser les commandes par code postal / rue et afficher une carte 3D isométrique de la France.
 * Version: 0.0
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
        add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );
    }

        /**
         * Query KPIs (migrated from tdb-kpi-cohortes.php)
         * returns array with totals and percentages
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
         * Dashboard widget 
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
            'KPI V 0.0',
            'KPI V 0.0',
            'manage_options',
            'wp-VO-wc-kpi-cohortes',
            array( $this, 'admin_page' ),
            'dashicons-chart-area',
            56
        );
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_wc-kpi-cohortes' ) {
            return;
        }

        wp_enqueue_style( 'wc-kpi-admin-css', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css' );
    wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true );
    // Leaflet for OpenStreetMap
    wp_enqueue_style( 'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' );
    wp_enqueue_script( 'leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), null, true );
    // three.js kept for future advanced 3D rendering
    wp_enqueue_script( 'threejs', 'https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.min.js', array(), null, true );
    wp_enqueue_script( 'wc-kpi-admin-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js', array( 'jquery', 'chartjs', 'leaflet-js' ), null, true );

        wp_localize_script( 'wc-kpi-admin-js', 'WCKPI', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wc_kpi_nonce' ),
            'nominatim_url' => 'https://nominatim.openstreetmap.org/search',
        ) );
    }

    public function admin_page() {
        // Simple admin UI with tabs, form, chart and map placeholder
        ?>
        <div class="wrap">
            <h1>KPI Cohortes</h1>
            <h2 class="nav-tab-wrapper">
                <a href="#tab-dashboard" class="nav-tab nav-tab-active">Tableau de bord</a>
                <a href="#tab-map" class="nav-tab">Carte 3D</a>
            </h2>

            <div id="tab-dashboard" class="wc-kpi-tab">
                <form id="wc-kpi-filters">
                    <label>Période (de / à)</label>
                    <input type="date" name="date_from" />
                    <input type="date" name="date_to" />

                    <label>Produit(s)</label>
                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" id="wc-kpi-all-products" name="all_products" value="1" /> Tous les produits</label>
                        <select id="wc-kpi-product-select" name="product_select[]" multiple style="min-width:300px; max-width:600px; height:160px;">
                            <?php
                            // try to list products (if WooCommerce active)
                            $products = get_posts( array( 'post_type' => 'product', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
                            if ( $products ) {
                                foreach ( $products as $p ) {
                                    printf( '<option value="%d">%s (ID:%d)</option>', esc_attr( $p->ID ), esc_html( $p->post_title ), esc_attr( $p->ID ) );
                                }
                            } else {
                                echo '<option value="">(Aucun produit trouvé)</option>';
                            }
                            ?>
                        </select>

                    <label>Statut</label>
                    <select name="status">
                        <option value="any">Tout</option>
                        <option value="wc-completed">completed</option>
                        <option value="wc-processing">processing</option>
                        <option value="wc-cancelled">cancelled</option>
                    </select>

                    <label><input type="checkbox" name="new_customers" value="1" /> Nouveaux clients (première commande)</label>
                    <label><input type="checkbox" name="new_postcodes" value="1" /> Nouveaux codes postaux</label>
                    <div style="margin-top:8px;">
                        <label>Afficher seulement emplacements avec au moins&nbsp;
                            <input type="number" name="min_orders" value="1" min="1" style="width:80px;" /> commandes
                        </label>
                        <label style="margin-left:12px;">Limiter résultats à&nbsp;
                            <input type="number" name="result_limit" value="50" min="1" style="width:80px;" /> lignes
                        </label>
                    </div>

                    <label style="display:inline-block;margin-top:8px;"><input type="checkbox" id="wc-kpi-debug" name="debug" value="1" /> Mode Debug (affiche diagnostics)</label>

                    <button class="button button-primary" id="wc-kpi-submit">Afficher</button>
                </form>

                <div id="wc-kpi-charts">
                    <canvas id="wc-kpi-chart" width="800" height="300"></canvas>
                </div>
            </div>

            <div id="tab-map" class="wc-kpi-tab" style="display:none;">
                <div id="wc-kpi-map" style="width:100%;height:600px;background:#f3f3f3;display:flex;align-items:center;justify-content:center;">
                    Chargement de la carte 3D isométrique...
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_get_data() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'permission' );
        }
        if ( ! check_ajax_referer( 'wc_kpi_nonce', '_wpnonce', false ) ) {
            // try raw param too
            if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_kpi_nonce' ) ) {
                wp_send_json_error( 'nonce' );
            }
        }

        global $wpdb;
        $prefix = $wpdb->prefix;
        $addresses_table = $prefix . 'wc_order_addresses';
        $posts_table = $prefix . 'posts';
        $order_items_table = $prefix . 'woocommerce_order_items';
        $order_itemmeta_table = $prefix . 'woocommerce_order_itemmeta';

        $date_from = isset( $_REQUEST['date_from'] ) && $_REQUEST['date_from'] ? $_REQUEST['date_from'] . ' 00:00:00' : null;
        $date_to   = isset( $_REQUEST['date_to'] ) && $_REQUEST['date_to'] ? $_REQUEST['date_to'] . ' 23:59:59' : null;
        $status    = isset( $_REQUEST['status'] ) && $_REQUEST['status'] && $_REQUEST['status'] !== 'any' ? esc_sql( $_REQUEST['status'] ) : null;
        $product_ids_raw = isset( $_REQUEST['product_ids'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['product_ids'] ) ) : '';
        $product_ids = array_filter( array_map( 'intval', explode( ',', $product_ids_raw ) ) );

        // Build order selection
        $where = "post_type = 'shop_order'";
        $params = array();
        if ( $date_from ) {
            $where .= ' AND post_date >= %s';
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where .= ' AND post_date <= %s';
            $params[] = $date_to;
        }
        if ( $status ) {
            $where .= ' AND post_status = %s';
            $params[] = $status;
        }

        $order_ids_sql = "SELECT ID FROM {$posts_table} WHERE {$where}";
        if ( ! empty( $product_ids ) ) {
            // join with order items to filter by product
            // find order IDs containing the product(s)
            $in = implode( ',', array_map( 'absint', $product_ids ) );
            $order_ids_sql = "SELECT DISTINCT oi.order_id FROM {$order_items_table} oi
                JOIN {$order_itemmeta_table} oim ON oim.order_item_id = oi.order_item_id AND oim.meta_key IN ('_product_id','_variation_id') AND oim.meta_value IN ({$in})
                WHERE oi.order_item_type = 'line_item' AND oi.order_id IN ({$order_ids_sql})";
        }

        // Build list of order IDs
        $order_ids_query = "SELECT ID FROM {$posts_table} WHERE {$where}";
        $order_ids = $wpdb->get_col( $wpdb->prepare( $order_ids_query, $params ) );
        if ( empty( $order_ids ) ) {
            // no orders found for selection
            if ( ! empty( $_REQUEST['debug'] ) ) {
                $diag = array(
                    'orders_found' => 0,
                    'order_ids_sample' => array(),
                    'addresses_table' => $addresses_table,
                    'posts_table' => $posts_table,
                    'order_items_table' => $order_items_table,
                    'notes' => 'No orders match the date/status/product filters.'
                );
                wp_send_json_success( array( 'debug' => $diag ) );
            }
            wp_send_json_success( array() );
        }

        $order_ids_in = implode( ',', array_map( 'absint', $order_ids ) );

        // If new_postcodes requested, collect historical postcodes before date_from
        $exclude_postcodes = array();
        if ( ! empty( $_REQUEST['new_postcodes'] ) && $date_from ) {
            $hist_sql = $wpdb->prepare( "SELECT DISTINCT postcode FROM {$addresses_table} addr JOIN {$posts_table} p ON p.ID = addr.order_id WHERE p.post_date < %s", $date_from );
            $exclude_postcodes = $wpdb->get_col( $hist_sql );
        }

        // Fetch address rows for these orders
        $addr_sql = "SELECT addr.order_id, addr.postcode, addr.address_1, addr.email FROM {$addresses_table} addr WHERE addr.order_id IN ({$order_ids_in})";
            $addr_rows = $wpdb->get_results( $addr_sql );
            // If the custom addresses table does not exist, $wpdb->get_results returns null and sets last_error.
            // Treat a missing table as "no custom addresses" and continue to fallback to postmeta rather than aborting the whole AJAX call.
            if ( $addr_rows === null ) {
                // keep the DB error in a variable for debug output but continue with an empty set so the postmeta fallback runs
                $addr_table_error = $wpdb->last_error;
                $addr_rows = array();
            }

            // If no rows found in custom addresses table, fallback to postmeta (standard WooCommerce storage)
            if ( empty( $addr_rows ) ) {
                // try to read billing/shipping fields from postmeta for given orders
                $postmeta_table = $prefix . 'postmeta';
                $meta_keys = array('_billing_postcode','_shipping_postcode','_billing_address_1','_shipping_address_1','_billing_email','_billing_phone');
                $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
                // build SQL to fetch all meta for these orders and keys
                $meta_sql = "SELECT post_id, meta_key, meta_value FROM {$postmeta_table} WHERE post_id IN ({$order_ids_in}) AND meta_key IN ('" . implode("','", $meta_keys) . "')";
                $meta_rows = $wpdb->get_results( $meta_sql );
                if ( $meta_rows ) {
                    // pivot meta into addr-like rows
                    $pivot = array();
                    foreach ( $meta_rows as $m ) {
                        $pid = $m->post_id;
                        if ( ! isset( $pivot[ $pid ] ) ) $pivot[ $pid ] = array( 'order_id' => $pid );
                        $pivot[ $pid ][ $m->meta_key ] = $m->meta_value;
                    }
                    $addr_rows = array();
                    foreach ( $pivot as $pid => $vals ) {
                        $row = new stdClass();
                        $row->order_id = $pid;
                        // prefer shipping postcode/address when available
                        $row->postcode = isset($vals['_shipping_postcode']) && $vals['_shipping_postcode'] !== '' ? $vals['_shipping_postcode'] : (isset($vals['_billing_postcode']) ? $vals['_billing_postcode'] : '');
                        $row->address_1 = isset($vals['_shipping_address_1']) && $vals['_shipping_address_1'] !== '' ? $vals['_shipping_address_1'] : (isset($vals['_billing_address_1']) ? $vals['_billing_address_1'] : '');
                        $row->email = isset($vals['_billing_email']) ? $vals['_billing_email'] : '';
                        $addr_rows[] = $row;
                    }
                }
            }

        // collect emails early to compute first order date per email
        $emails = array();
        foreach ( $addr_rows as $a ) {
            if ( ! empty( $a->email ) ) $emails[] = trim( strtolower( $a->email ) );
        }
        $emails = array_values( array_filter( array_unique( $emails ) ) );

        // compute first order per email (using posts table post_date)
        $email_first = array();
        if ( ! empty( $emails ) ) {
            $placeholders = implode(',', array_fill(0, count($emails), '%s'));
            $first_sql = $wpdb->prepare( "SELECT addr.email, MIN(p.post_date) AS first_date FROM {$addresses_table} addr JOIN {$posts_table} p ON p.ID = addr.order_id WHERE addr.email IN ({$placeholders}) AND p.post_type = 'shop_order' GROUP BY addr.email", ...$emails );
            $first_rows = $wpdb->get_results( $first_sql );
            if ( $first_rows ) {
                foreach ( $first_rows as $fr ) {
                    $email_first[ trim( strtolower( $fr->email ) ) ] = $fr->first_date;
                }
            }
        }

        // note: earlier we normalized $addr_rows to an empty array when the custom addresses table
        // did not exist. Do not abort here; continue and let the postmeta fallback provide data.

        // Fetch order items for these orders to compute per-product counts
        $oi_sql = "SELECT oi.order_id, oim.meta_key, oim.meta_value FROM {$order_items_table} oi JOIN {$order_itemmeta_table} oim ON oim.order_item_id = oi.order_item_id WHERE oi.order_id IN ({$order_ids_in}) AND oi.order_item_type = 'line_item' AND oim.meta_key IN ('_product_id','_variation_id')";
        $oi_rows = $wpdb->get_results( $oi_sql );

        // If debug requested, return diagnostics to help find mismatches (table names, counts, sample ids)
        if ( ! empty( $_REQUEST['debug'] ) ) {
            $diag = array();
            $diag['addresses_table'] = $addresses_table;
            $diag['posts_table'] = $posts_table;
            $diag['order_items_table'] = $order_items_table;
            $diag['order_itemmeta_table'] = $order_itemmeta_table;
            $diag['order_ids_count'] = count($order_ids);
            $diag['order_ids_sample'] = array_slice($order_ids, 0, 10);
            $diag['addr_rows_count'] = is_array($addr_rows) ? count($addr_rows) : 0;
            if ( ! empty( $addr_table_error ) ) $diag['addr_table_error'] = $addr_table_error;
            $diag['oi_rows_count'] = is_array($oi_rows) ? count($oi_rows) : 0;
            $diag['date_from'] = $date_from;
            $diag['date_to'] = $date_to;
            wp_send_json_success( array( 'debug' => $diag ) );
        }

        // aggregate with new/returning customer detection
        $map = array();
        $emails = array();
        foreach ( $addr_rows as $a ) {
            $pc = trim( $a->postcode );
            if ( $pc === '' ) $pc = 'N/A';
            if ( in_array( $pc, $exclude_postcodes ) ) continue;
            $key = $pc . '||' . trim( $a->address_1 );
            if ( ! isset( $map[ $key ] ) ) {
                $map[ $key ] = array( 'postcode' => $pc, 'street' => trim( $a->address_1 ), 'orders' => 0, 'emails' => array(), 'products' => array(), 'new_orders' => 0 );
            }
            $map[ $key ]['orders']++;
            if ( $a->email ) {
                $e = trim( strtolower( $a->email ) );
                $map[ $key ]['emails'][] = $e;
                // if this order belongs to a new customer (first order within period), count it
                if ( isset( $email_first[ $e ] ) ) {
                    $fd = $email_first[ $e ];
                    if ( $fd >= $date_from && $fd < $date_to ) {
                        $map[ $key ]['new_orders']++;
                    }
                }
            }
        }

        // products: attach counts per group
        foreach ( $oi_rows as $oi ) {
            $oid = $oi->order_id;
            $prod_id = intval( $oi->meta_value );
            if ( ! $prod_id ) continue;
            foreach ( $addr_rows as $a ) {
                if ( intval( $a->order_id ) !== intval( $oid ) ) continue;
                $pc = trim( $a->postcode ); if ( $pc === '' ) $pc = 'N/A';
                if ( in_array( $pc, $exclude_postcodes ) ) continue;
                $key = $pc . '||' . trim( $a->address_1 );
                if ( ! isset( $map[ $key ] ) ) continue;
                if ( ! isset( $map[ $key ]['products'][ $prod_id ] ) ) $map[ $key ]['products'][ $prod_id ] = 0;
                $map[ $key ]['products'][ $prod_id ]++;
            }
        }

        // determine first order date per email (to classify new vs returning)
        $email_first = array();
        $emails = array_values( array_filter( array_unique( $emails ) ) );
        if ( ! empty( $emails ) ) {
            $placeholders = implode(',', array_fill(0, count($emails), '%s'));
            $params_e = $emails;
            $first_sql = $wpdb->prepare( "SELECT addr.email, MIN(p.post_date) AS first_date FROM {$addresses_table} addr JOIN {$posts_table} p ON p.ID = addr.order_id WHERE addr.email IN ({$placeholders}) AND p.post_type = 'shop_order' GROUP BY addr.email", ...$params_e );
            $first_rows = $wpdb->get_results( $first_sql );
            if ( $first_rows ) {
                foreach ( $first_rows as $fr ) {
                    $email_first[ trim( strtolower( $fr->email ) ) ] = $fr->first_date;
                }
            }
        }

        // build final rows with new/returning counts
        $final = array();
        foreach ( $map as $key => $info ) {
            $unique_emails = array_values( array_filter( array_unique( $info['emails'] ) ) );
            $new_count = 0;
            foreach ( $unique_emails as $e ) {
                if ( isset( $email_first[ $e ] ) ) {
                    $fd = $email_first[ $e ];
                    if ( $fd >= $date_from && $fd < $date_to ) $new_count++;
                }
            }
            $returning_count = count($unique_emails) - $new_count;
            // attach product JSON
            $info['products'] = json_encode( $info['products'] );
            $info['unique_customers'] = count($unique_emails);
            $info['new_customers'] = $new_count;
            $info['returning_customers'] = $returning_count;
            $final[] = $info;
        }

        // sort by orders desc
        usort( $final, function($a,$b){ return $b['orders'] - $a['orders']; });

        // apply min_orders and result_limit filters from request
        $min_orders = isset($_REQUEST['min_orders']) ? intval($_REQUEST['min_orders']) : 1;
        $result_limit = isset($_REQUEST['result_limit']) ? intval($_REQUEST['result_limit']) : 50;
        $filtered = array_filter( $final, function($r) use ($min_orders){ return intval($r['orders']) >= $min_orders; } );
        if ( $result_limit > 0 ) $filtered = array_slice( array_values($filtered), 0, $result_limit );

        wp_send_json_success( $filtered );
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

        $response = wp_remote_get( $url, array( 'user-agent' => 'wp-_VO-wc-kpi-cohortes - email@example.com' ) );
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
