<?php
/**
 * Database management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Mail_Sender_DB {
    
    private static $instance = null;
    private $mailing_db;
    private $source_db;
    private $mailing_wpdb = null;
    private $connection_error = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->mailing_db = WP_MAIL_SENDER_MAILING_DB;
        $this->source_db = WP_MAIL_SENDER_SOURCE_DB;
        
        // Tenter connexion à la DB mailing si credentials fournis
        $this->init_mailing_connection();
    }
    
    /**
     * Initialize mailing database connection
     */
    private function init_mailing_connection() {
        // Récupérer le mot de passe (depuis wp-config.php ou option)
        $password = defined('WP_MAIL_SENDER_MAILING_PASSWORD') && WP_MAIL_SENDER_MAILING_PASSWORD 
            ? WP_MAIL_SENDER_MAILING_PASSWORD 
            : get_option('wp_mail_sender_db_password', '');
        
        if (empty($password)) {
            $this->connection_error = 'Mot de passe DB mailing non configuré';
            error_log('[WP Mail Sender DB ERROR] [' . current_time('mysql') . '] ' . $this->connection_error);
            return;
        }
        
        // Connexion séparée pour la DB mailing (si différente de WP)
        try {
            $this->mailing_wpdb = new wpdb(
                WP_MAIL_SENDER_MAILING_USER,
                $password,
                WP_MAIL_SENDER_MAILING_DB,
                DB_HOST
            );
            
            // Test de connexion
            $test = $this->mailing_wpdb->get_var("SELECT 1");
            if ($test !== '1') {
                throw new Exception('Échec du test de connexion');
            }
            
            error_log('[WP Mail Sender DB INFO] [' . current_time('mysql') . '] Connexion mailing DB établie');
        } catch (Exception $e) {
            $this->connection_error = 'Impossible de se connecter à la DB mailing: ' . $e->getMessage();
            error_log('[WP Mail Sender DB ERROR] [' . current_time('mysql') . '] ' . $this->connection_error);
            $this->mailing_wpdb = null;
        }
    }
    
    /**
     * Check if mailing DB is connected
     */
    public function is_mailing_connected() {
        return $this->mailing_wpdb !== null && !$this->connection_error;
    }
    
    /**
     * Get connection error message
     */
    public function get_connection_error() {
        return $this->connection_error;
    }
    
    /**
     * Get mailing database connection
     */
    public function get_mailing_wpdb() {
        return $this->mailing_wpdb;
    }
    
    /**
     * Create custom tables on activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $mailing_db = WP_MAIL_SENDER_MAILING_DB;
        
        // Vérifier la connexion avant de créer les tables
        $instance = self::get_instance();
        if (!$instance->is_mailing_connected()) {
            error_log('[WP Mail Sender DB ERROR] [' . current_time('mysql') . '] Cannot create tables: ' . $instance->get_connection_error());
            return false;
        }
        
        $db = $instance->mailing_wpdb;
        
        // Templates table (alignée sur le schéma existant mailing_templates)
        $table_templates = "`{$mailing_db}`.`" . WP_MAIL_SENDER_TABLE_PREFIX . "templates`";
        $sql_templates = "CREATE TABLE IF NOT EXISTS {$table_templates} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            subject varchar(255) DEFAULT NULL,
            content longtext NOT NULL,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_default (is_default)
        ) $charset_collate;";
        
        // Mailing lists table
        $table_lists = "`{$mailing_db}`.`" . WP_MAIL_SENDER_TABLE_PREFIX . "lists`";
        $sql_lists = "CREATE TABLE IF NOT EXISTS {$table_lists} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            query_type varchar(50) NOT NULL COMMENT 'customers, orders, custom',
            query_config longtext DEFAULT NULL COMMENT 'JSON config for query',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Campaigns table
        $table_campaigns = "`{$mailing_db}`.`" . WP_MAIL_SENDER_TABLE_PREFIX . "campaigns`";
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS {$table_campaigns} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            template_id bigint(20) NOT NULL,
            list_id bigint(20) NOT NULL,
            status varchar(50) DEFAULT 'draft' COMMENT 'draft, scheduled, sending, sent, failed',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            total_recipients int(11) DEFAULT 0,
            sent_count int(11) DEFAULT 0,
            failed_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Logs table
        $table_logs = "`{$mailing_db}`.`" . WP_MAIL_SENDER_TABLE_PREFIX . "logs`";
        $sql_logs = "CREATE TABLE IF NOT EXISTS {$table_logs} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) DEFAULT NULL,
            recipient_email varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            status varchar(50) DEFAULT 'pending' COMMENT 'pending, sent, failed',
            error_message text DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY recipient_email (recipient_email),
            KEY status (status)
        ) $charset_collate;";
        
        // Segments table (for advanced segmentation)
        $table_segments = "`{$mailing_db}`.`" . WP_MAIL_SENDER_TABLE_PREFIX . "segments`";
        $sql_segments = "CREATE TABLE IF NOT EXISTS {$table_segments} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            filters longtext DEFAULT NULL COMMENT 'JSON config for filters',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_templates);
        dbDelta($sql_lists);
        dbDelta($sql_campaigns);
        dbDelta($sql_logs);
        dbDelta($sql_segments);
        
        error_log('[WP Mail Sender DB INFO] [' . current_time('mysql') . '] Tables created successfully');
        return true;
    }
    
    /**
     * Get WooCommerce customers from source database
     */
    public function get_wc_customers($filters = array()) {
        global $wpdb;
        
        $source_db = $this->source_db;
        // Use configured prefix for the source database
        $prefix = WP_MAIL_SENDER_SOURCE_PREFIX;
        
        $sql = "SELECT DISTINCT 
                u.ID, 
                u.user_email, 
                u.display_name,
                um1.meta_value as first_name,
                um2.meta_value as last_name
            FROM `{$source_db}`.`{$prefix}users` u
            INNER JOIN `{$source_db}`.`{$prefix}usermeta` um ON u.ID = um.user_id
            LEFT JOIN `{$source_db}`.`{$prefix}usermeta` um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
            LEFT JOIN `{$source_db}`.`{$prefix}usermeta` um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
            WHERE um.meta_key = '{$prefix}capabilities' 
            AND um.meta_value LIKE '%customer%'";
        
        if (!empty($filters['date_from'])) {
            $sql .= $wpdb->prepare(" AND u.user_registered >= %s", $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= $wpdb->prepare(" AND u.user_registered <= %s", $filters['date_to']);
        }
        
        error_log('[WP Mail Sender DB DEBUG] get_wc_customers SQL: ' . $sql);
        $results = $wpdb->get_results($sql);
        
        if ($wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] [' . current_time('mysql') . '] get_wc_customers: ' . $wpdb->last_error);
            return array();
        }
        
        error_log('[WP Mail Sender DB DEBUG] get_wc_customers found: ' . count($results) . ' customers');
        return $results;
    }
    
    /**
     * Get WooCommerce orders from source database (HPOS OPTIMIZED)
     */
    public function get_wc_orders($filters = array()) {
        global $wpdb;
        
        $source_db = $this->source_db;
        // Use configured prefix for the source database
        $prefix = WP_MAIL_SENDER_SOURCE_PREFIX;
        
        // Try HPOS tables first (detect using information_schema with proper DB + table name)
        $hpos_table_exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                $source_db,
                $prefix . 'wc_orders'
            )
        ) > 0;
        
        error_log('[WP Mail Sender DB DEBUG] HPOS table exists: ' . ($hpos_table_exists ? 'YES' : 'NO'));
        
        if ($hpos_table_exists) {
            // Use HPOS tables (OPTIMIZED)
            $sql = "SELECT 
                    o.id AS order_id,
                    a.email AS billing_email,
                    a.first_name AS billing_first_name,
                    a.last_name AS billing_last_name,
                    o.date_created_gmt AS order_date,
                    o.total_amount
                FROM `{$source_db}`.`{$prefix}wc_orders` o
                INNER JOIN `{$source_db}`.`{$prefix}wc_order_addresses` a 
                    ON o.id = a.order_id AND a.address_type = 'billing'
                WHERE o.status IN ('wc-completed', 'wc-processing')
                AND a.email IS NOT NULL
                AND a.email != ''";
            
            if (!empty($filters['date_from'])) {
                $sql .= $wpdb->prepare(" AND o.date_created_gmt >= %s", $filters['date_from']);
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= $wpdb->prepare(" AND o.date_created_gmt <= %s", $filters['date_to']);
            }
            
            // Add default date filter if no filters provided (last 2 years)
            if (empty($filters['date_from']) && empty($filters['date_to'])) {
                $sql .= " AND o.date_created_gmt >= DATE_SUB(NOW(), INTERVAL 2 YEAR)";
            }
            
            $sql .= " GROUP BY a.email, o.id
                     ORDER BY o.date_created_gmt DESC";
        } else {
            // Fallback to legacy tables
            error_log('[WP Mail Sender DB INFO] [' . current_time('mysql') . '] HPOS tables not found, using legacy postmeta');
            
            $sql = "SELECT 
                    p.ID AS order_id,
                    email.meta_value AS billing_email,
                    first_name.meta_value AS billing_first_name,
                    last_name.meta_value AS billing_last_name,
                    p.post_date AS order_date
                FROM `{$source_db}`.`{$prefix}posts` p
                INNER JOIN `{$source_db}`.`{$prefix}postmeta` email 
                    ON p.ID = email.post_id AND email.meta_key = '_billing_email'
                LEFT JOIN `{$source_db}`.`{$prefix}postmeta` first_name 
                    ON p.ID = first_name.post_id AND first_name.meta_key = '_billing_first_name'
                LEFT JOIN `{$source_db}`.`{$prefix}postmeta` last_name 
                    ON p.ID = last_name.post_id AND last_name.meta_key = '_billing_last_name'
                WHERE p.post_type = 'shop_order'
                AND email.meta_value IS NOT NULL
                AND email.meta_value != ''";
            
            if (!empty($filters['date_from'])) {
                $sql .= $wpdb->prepare(" AND p.post_date >= %s", $filters['date_from']);
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= $wpdb->prepare(" AND p.post_date <= %s", $filters['date_to']);
            }
            
            if (empty($filters['date_from']) && empty($filters['date_to'])) {
                $sql .= " AND p.post_date >= DATE_SUB(NOW(), INTERVAL 2 YEAR)";
            }
            
            $sql .= " GROUP BY email.meta_value
                     ORDER BY p.post_date DESC";
        }
        
        error_log('[WP Mail Sender DB DEBUG] get_wc_orders SQL: ' . $sql);
        $results = $wpdb->get_results($sql);
        
        if ($wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] [' . current_time('mysql') . '] get_wc_orders: ' . $wpdb->last_error);
            return array();
        }
        
        error_log('[WP Mail Sender DB DEBUG] get_wc_orders found: ' . count($results) . ' orders');
        return $results;
    }
    
    /**
     * Get template by ID (OPTIMIZED: specific columns)
     */
    public function get_template($id) {
        if (!$this->is_mailing_connected()) return null;
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}templates`";
        return $this->mailing_wpdb->get_row($this->mailing_wpdb->prepare(
            "SELECT id, name, subject, content AS body, type, is_default, created_by, created_at, updated_at 
             FROM {$table} WHERE id = %d", 
            $id
        ));
    }
    
    /**
     * Get all templates (OPTIMIZED: specific columns)
     */
    public function get_templates() {
        if (!$this->is_mailing_connected()) return array();
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}templates`";
        return $this->mailing_wpdb->get_results(
            "SELECT id, name, subject, content AS body, type, is_default, created_by, created_at, updated_at 
             FROM {$table} 
             ORDER BY created_at DESC"
        );
    }
    
    /**
     * Save template
     */
    public function save_template($data) {
        if (!$this->is_mailing_connected()) {
            error_log('[WP Mail Sender DB ERROR] save_template: Not connected to mailing DB');
            return false;
        }
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}templates`";
        $type = isset($data['type']) && !empty($data['type']) ? $data['type'] : 'custom';
        $is_default = isset($data['is_default']) ? (int) (bool) $data['is_default'] : 0;
        $created_by = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        
        if (!empty($data['id'])) {
            // Update existing template
            $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
                "UPDATE {$table} SET name = %s, subject = %s, content = %s, type = %s, is_default = %d, updated_at = NOW() WHERE id = %d",
                $data['name'],
                $data['subject'],
                $data['body'],
                $type,
                $is_default,
                $data['id']
            ));
            error_log('[WP Mail Sender DB INFO] save_template UPDATE result: ' . var_export($result, true));
        } else {
            // Insert new template
            $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
                "INSERT INTO {$table} (name, subject, content, type, is_default, created_by, created_at, updated_at) VALUES (%s, %s, %s, %s, %d, %d, NOW(), NOW())",
                $data['name'],
                $data['subject'],
                $data['body'],
                $type,
                $is_default,
                $created_by
            ));
            error_log('[WP Mail Sender DB INFO] save_template INSERT result: ' . var_export($result, true) . ' | insert_id: ' . $this->mailing_wpdb->insert_id);
        }
        
        if ($this->mailing_wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] save_template: ' . $this->mailing_wpdb->last_error);
            return false;
        }
        
        // query() returns number of rows affected, or false on error
        // 0 is valid for UPDATE when nothing changed, but for INSERT we need insert_id
        if ($result === false) {
            error_log('[WP Mail Sender DB ERROR] save_template: query returned false');
            return false;
        }
        
        return true;
    }
    
    /**
     * Delete template
     */
    public function delete_template($id) {
        if (!$this->is_mailing_connected()) return false;
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}templates`";
        $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
            "DELETE FROM {$table} WHERE id = %d",
            $id
        ));
        
        if ($this->mailing_wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] delete_template: ' . $this->mailing_wpdb->last_error);
            return false;
        }
        
        return $result !== false;
    }
    
    /**
     * Get list by ID (OPTIMIZED: specific columns)
     */
    public function get_list($id) {
        error_log('>>> GET_LIST ID: ' . $id);
        
        if (!$this->is_mailing_connected()) {
            error_log('>>> GET_LIST: NOT CONNECTED');
            return null;
        }
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}lists`";
        error_log('>>> GET_LIST TABLE: ' . $table);
        
        $result = $this->mailing_wpdb->get_row($this->mailing_wpdb->prepare(
            "SELECT id, name, description, query_type, query_config, created_at, updated_at 
             FROM {$table} WHERE id = %d", 
            $id
        ));
        
        error_log('>>> GET_LIST RESULT: ' . ($result ? 'FOUND' : 'NOT FOUND'));
        if ($this->mailing_wpdb->last_error) {
            error_log('>>> GET_LIST ERROR: ' . $this->mailing_wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * Get all lists (OPTIMIZED: specific columns)
     */
    public function get_lists() {
        if (!$this->is_mailing_connected()) return array();
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}lists`";
        return $this->mailing_wpdb->get_results(
            "SELECT id, name, description, query_type, query_config, created_at, updated_at 
             FROM {$table} 
             ORDER BY created_at DESC"
        );
    }
    
    /**
     * Save list
     */
    public function save_list($data) {
        if (!$this->is_mailing_connected()) return false;
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}lists`";
        
        if (!empty($data['id'])) {
            // Update existing list
            $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
                "UPDATE {$table} SET name = %s, description = %s, query_type = %s, query_config = %s, updated_at = NOW() WHERE id = %d",
                $data['name'],
                $data['description'],
                $data['query_type'],
                $data['query_config'],
                $data['id']
            ));
        } else {
            // Insert new list
            $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
                "INSERT INTO {$table} (name, description, query_type, query_config, created_at, updated_at) VALUES (%s, %s, %s, %s, NOW(), NOW())",
                $data['name'],
                $data['description'],
                $data['query_type'],
                $data['query_config']
            ));
        }
        
        if ($this->mailing_wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] save_list: ' . $this->mailing_wpdb->last_error);
            return false;
        }
        
        return $result !== false;
    }
    
    /**
     * Delete list
     */
    public function delete_list($id) {
        if (!$this->is_mailing_connected()) return false;
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}lists`";
        $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
            "DELETE FROM {$table} WHERE id = %d",
            $id
        ));
        
        if ($this->mailing_wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] delete_list: ' . $this->mailing_wpdb->last_error);
            return false;
        }
        
        return $result !== false;
    }
    
    /**
     * Get campaign by ID (OPTIMIZED: specific columns)
     */
    public function get_campaign($id) {
        if (!$this->is_mailing_connected()) return null;
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}campaigns`";
        return $this->mailing_wpdb->get_row($this->mailing_wpdb->prepare(
            "SELECT id, name, template_id, list_id, status, scheduled_at, sent_at, 
                    total_recipients, sent_count, failed_count, created_at 
             FROM {$table} WHERE id = %d", 
            $id
        ));
    }
    
    /**
     * Save campaign
     */
    public function save_campaign($data) {
        if (!$this->is_mailing_connected()) return false;
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}campaigns`";
        
        if (!empty($data['id'])) {
            // Update existing campaign - build dynamic SET clause
            $set_parts = array();
            $values = array();
            
            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $set_parts[] = "{$key} = %s";
                    $values[] = $value;
                }
            }
            $values[] = $data['id'];
            
            $sql = "UPDATE {$table} SET " . implode(', ', $set_parts) . " WHERE id = %d";
            $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare($sql, $values));
        } else {
            // Insert new campaign
            $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
                "INSERT INTO {$table} (name, template_id, list_id, status, created_at) VALUES (%s, %d, %d, %s, %s)",
                $data['name'],
                $data['template_id'],
                $data['list_id'],
                $data['status'],
                $data['created_at']
            ));
        }
        
        if ($this->mailing_wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] save_campaign: ' . $this->mailing_wpdb->last_error);
            return false;
        }
        
        return $result !== false;
    }
    
    /**
     * Log email sending
     */
    public function log_email($data) {
        if (!$this->is_mailing_connected()) return false;
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}logs`";
        $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
            "INSERT INTO {$table} (campaign_id, recipient_email, subject, status, error_message, sent_at, created_at) 
             VALUES (%d, %s, %s, %s, %s, %s, %s)",
            $data['campaign_id'],
            $data['recipient_email'],
            $data['subject'],
            $data['status'],
            isset($data['error_message']) ? $data['error_message'] : null,
            isset($data['sent_at']) ? $data['sent_at'] : null,
            $data['created_at']
        ));
        
        if ($this->mailing_wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] log_email: ' . $this->mailing_wpdb->last_error);
            return false;
        }
        
        return $result !== false;
    }
    
    /**
     * Get segment by ID
     */
    public function get_segment($id) {
        if (!$this->is_mailing_connected()) return null;
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}segments`";
        return $this->mailing_wpdb->get_row($this->mailing_wpdb->prepare(
            "SELECT id, name, description, filters, created_at, updated_at 
             FROM {$table} WHERE id = %d", 
            $id
        ));
    }
    
    /**
     * Get all segments
     */
    public function get_segments() {
        if (!$this->is_mailing_connected()) return array();
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}segments`";
        return $this->mailing_wpdb->get_results(
            "SELECT id, name, description, filters, created_at, updated_at 
             FROM {$table} 
             ORDER BY created_at DESC"
        );
    }
    
    /**
     * Save segment
     */
    public function save_segment($data) {
        if (!$this->is_mailing_connected()) return false;
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}segments`";
        
        if (!empty($data['id'])) {
            // Update existing segment
            $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
                "UPDATE {$table} SET name = %s, description = %s, filters = %s, updated_at = NOW() WHERE id = %d",
                $data['name'],
                $data['description'],
                $data['filters'],
                $data['id']
            ));
        } else {
            // Insert new segment
            $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
                "INSERT INTO {$table} (name, description, filters, created_at, updated_at) VALUES (%s, %s, %s, NOW(), NOW())",
                $data['name'],
                $data['description'],
                $data['filters']
            ));
        }
        
        if ($this->mailing_wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] save_segment: ' . $this->mailing_wpdb->last_error);
            return false;
        }
        
        return $result !== false;
    }
    
    /**
     * Delete segment
     */
    public function delete_segment($id) {
        if (!$this->is_mailing_connected()) return false;
        
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $table = "`{$this->mailing_db}`.`{$prefix}segments`";
        $result = $this->mailing_wpdb->query($this->mailing_wpdb->prepare(
            "DELETE FROM {$table} WHERE id = %d",
            $id
        ));
        
        if ($this->mailing_wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] delete_segment: ' . $this->mailing_wpdb->last_error);
            return false;
        }
        
        return $result !== false;
    }
    
    /**
     * Get segment recipients with advanced filters
     */
    public function get_segment_recipients($filters) {
        global $wpdb;
        
        $source_db = $this->source_db;
        $prefix = WP_MAIL_SENDER_SOURCE_PREFIX;
        
        error_log('[WP Mail Sender DB DEBUG] get_segment_recipients called with filters: ' . print_r($filters, true));
        
        // Base query - SIMPLIFIED for better performance and compatibility
        $sql = "SELECT DISTINCT
                p.ID AS order_id,
                MAX(CASE WHEN pm.meta_key = '_billing_email' THEN pm.meta_value END) AS billing_email,
                MAX(CASE WHEN pm.meta_key = '_billing_first_name' THEN pm.meta_value END) AS billing_first_name,
                MAX(CASE WHEN pm.meta_key = '_billing_last_name' THEN pm.meta_value END) AS billing_last_name,
                MAX(CASE WHEN pm.meta_key = '_billing_city' THEN pm.meta_value END) AS billing_city,
                MAX(CASE WHEN pm.meta_key = '_billing_postcode' THEN pm.meta_value END) AS billing_postcode,
                MAX(CASE WHEN pm.meta_key = '_shipping_city' THEN pm.meta_value END) AS shipping_city,
                MAX(CASE WHEN pm.meta_key = '_shipping_postcode' THEN pm.meta_value END) AS shipping_postcode,
                MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) AS order_total,
                p.post_date AS order_date
            FROM `{$source_db}`.`{$prefix}posts` p
            INNER JOIN `{$source_db}`.`{$prefix}postmeta` pm ON p.ID = pm.post_id";
        
        // Product category filter
        if (!empty($filters['product_filter_type'])) {
            if ($filters['product_filter_type'] === 'category' && !empty($filters['categories'])) {
                $categories = array_filter(array_map('trim', explode("\n", $filters['categories'])));
                if (!empty($categories)) {
                    $sql .= " INNER JOIN `{$source_db}`.`{$prefix}woocommerce_order_items` oi 
                              ON p.ID = oi.order_id
                              INNER JOIN `{$source_db}`.`{$prefix}woocommerce_order_itemmeta` oim 
                              ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
                              INNER JOIN `{$source_db}`.`{$prefix}term_relationships` tr 
                              ON oim.meta_value = tr.object_id
                              INNER JOIN `{$source_db}`.`{$prefix}term_taxonomy` tt 
                              ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
                              INNER JOIN `{$source_db}`.`{$prefix}terms` t 
                              ON tt.term_id = t.term_id";
                }
            } elseif ($filters['product_filter_type'] === 'product' && !empty($filters['products'])) {
                $products = array_filter(array_map('trim', explode("\n", $filters['products'])));
                if (!empty($products)) {
                    $sql .= " INNER JOIN `{$source_db}`.`{$prefix}woocommerce_order_items` oi 
                              ON p.ID = oi.order_id
                              INNER JOIN `{$source_db}`.`{$prefix}woocommerce_order_itemmeta` oim 
                              ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'";
                }
            }
        }
        
        $where = array("p.post_type = 'shop_order'");
        
        // Date filters
        if (!empty($filters['date_from'])) {
            $where[] = $wpdb->prepare("p.post_date >= %s", $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $where[] = $wpdb->prepare("p.post_date <= %s", $filters['date_to']);
        }
        
        $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " GROUP BY p.ID";
        $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " GROUP BY p.ID";
        
        // HAVING clauses for aggregated fields (city, postcode, amount, email)
        $having = array("MAX(CASE WHEN pm.meta_key = '_billing_email' THEN pm.meta_value END) IS NOT NULL");
        
        // City filter - use HAVING because it's an aggregated field
        if (!empty($filters['city_type']) && !empty($filters['cities'])) {
            $cities = array_filter(array_map('trim', explode("\n", $filters['cities'])));
            if (!empty($cities)) {
                $city_key = $filters['city_type'] === 'billing' ? '_billing_city' : '_shipping_city';
                $placeholders = implode(',', array_fill(0, count($cities), '%s'));
                $having[] = $wpdb->prepare(
                    "MAX(CASE WHEN pm.meta_key = '{$city_key}' THEN pm.meta_value END) IN ({$placeholders})",
                    $cities
                );
            }
        }
        
        // Postcode filter - use HAVING
        if (!empty($filters['postcode_type']) && !empty($filters['postcodes'])) {
            $postcodes = array_filter(array_map('trim', explode("\n", $filters['postcodes'])));
            if (!empty($postcodes)) {
                $postcode_key = $filters['postcode_type'] === 'billing' ? '_billing_postcode' : '_shipping_postcode';
                $postcode_conditions = array();
                foreach ($postcodes as $pc) {
                    if (strlen($pc) <= 2) {
                        // Prefix search
                        $postcode_conditions[] = $wpdb->prepare(
                            "MAX(CASE WHEN pm.meta_key = '{$postcode_key}' THEN pm.meta_value END) LIKE %s",
                            $pc . '%'
                        );
                    } else {
                        // Exact match
                        $postcode_conditions[] = $wpdb->prepare(
                            "MAX(CASE WHEN pm.meta_key = '{$postcode_key}' THEN pm.meta_value END) = %s",
                            $pc
                        );
                    }
                }
                if (!empty($postcode_conditions)) {
                    $having[] = '(' . implode(' OR ', $postcode_conditions) . ')';
                }
            }
        }
        
        // Amount filters - use HAVING
        if (!empty($filters['amount_min'])) {
            $having[] = $wpdb->prepare(
                "CAST(MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) AS DECIMAL(10,2)) >= %f",
                floatval($filters['amount_min'])
            );
        }
        if (!empty($filters['amount_max'])) {
            $having[] = $wpdb->prepare(
                "CAST(MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) AS DECIMAL(10,2)) <= %f",
                floatval($filters['amount_max'])
            );
        }
        
        if (!empty($having)) {
            $sql .= " HAVING " . implode(' AND ', $having);
        }
        if (!empty($having)) {
            $sql .= " HAVING " . implode(' AND ', $having);
        }
        
        // Product category filter (HAVING clause for aggregated category check)
        if (!empty($filters['product_filter_type']) && $filters['product_filter_type'] === 'category' && !empty($filters['categories'])) {
            $categories = array_filter(array_map('trim', explode("\n", $filters['categories'])));
            if (!empty($categories)) {
                $cat_conditions = array();
                foreach ($categories as $cat) {
                    if (is_numeric($cat)) {
                        $cat_conditions[] = $wpdb->prepare("t.term_id = %d", $cat);
                    } else {
                        $cat_conditions[] = $wpdb->prepare("t.slug = %s", $cat);
                    }
                }
                // Add to WHERE since we have the JOIN
                if (!empty($cat_conditions) && strpos($sql, '`{$prefix}terms` t') !== false) {
                    $sql = str_replace(" GROUP BY p.ID", " AND (" . implode(' OR ', $cat_conditions) . ") GROUP BY p.ID", $sql);
                }
            }
        }
        
        // Product ID filter
        if (!empty($filters['product_filter_type']) && $filters['product_filter_type'] === 'product' && !empty($filters['products'])) {
            $products = array_filter(array_map('intval', array_map('trim', explode("\n", $filters['products']))));
            if (!empty($products)) {
                $placeholders = implode(',', array_fill(0, count($products), '%d'));
                // Add to WHERE since we have the JOIN
                if (!empty($products) && strpos($sql, 'woocommerce_order_itemmeta') !== false) {
                    $sql = str_replace(" GROUP BY p.ID", " AND " . $wpdb->prepare("oim.meta_value IN ({$placeholders})", $products) . " GROUP BY p.ID", $sql);
                }
            }
        }
        
        $sql .= " ORDER BY p.post_date DESC";
        
        error_log('[WP Mail Sender DB DEBUG] get_segment_recipients SQL: ' . $sql);
        $results = $wpdb->get_results($sql);
        
        if ($wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] get_segment_recipients: ' . $wpdb->last_error);
            error_log('[WP Mail Sender DB ERROR] Failed SQL: ' . $sql);
            return array();
        }
        
        error_log('[WP Mail Sender DB DEBUG] get_segment_recipients found: ' . count($results) . ' recipients');
        return $results;
    }
    
    /**
     * Get available cities from orders
     */
    public function get_available_cities($type = 'billing') {
        global $wpdb;
        
        $source_db = $this->source_db;
        $prefix = WP_MAIL_SENDER_SOURCE_PREFIX;
        $meta_key = $type === 'billing' ? '_billing_city' : '_shipping_city';
        
        $sql = "SELECT DISTINCT pm.meta_value as city
                FROM `{$source_db}`.`{$prefix}postmeta` pm
                INNER JOIN `{$source_db}`.`{$prefix}posts` p ON pm.post_id = p.ID
                WHERE pm.meta_key = %s
                AND pm.meta_value IS NOT NULL
                AND pm.meta_value != ''
                AND p.post_type = 'shop_order'
                ORDER BY pm.meta_value";
        
        $results = $wpdb->get_col($wpdb->prepare($sql, $meta_key));
        
        if ($wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] get_available_cities: ' . $wpdb->last_error);
            return array();
        }
        
        return $results;
    }
    
    /**
     * Get product categories
     */
    public function get_product_categories() {
        global $wpdb;
        
        $source_db = $this->source_db;
        $prefix = WP_MAIL_SENDER_SOURCE_PREFIX;
        
        $sql = "SELECT t.term_id as id, t.name, t.slug, tt.count
                FROM `{$source_db}`.`{$prefix}terms` t
                INNER JOIN `{$source_db}`.`{$prefix}term_taxonomy` tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy = 'product_cat'
                AND tt.count > 0
                ORDER BY t.name";
        
        $results = $wpdb->get_results($sql);
        
        if ($wpdb->last_error) {
            error_log('[WP Mail Sender DB ERROR] get_product_categories: ' . $wpdb->last_error);
            return array();
        }
        
        return $results;
    }
}
