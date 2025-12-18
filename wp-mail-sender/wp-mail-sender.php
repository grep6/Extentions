<?php
/**
 * Plugin Name: WP Mail Sender
 * Plugin URI: https://tabac-des-battieres.com
 * Description: Extension d'envoi d'emails via SMTP avec gestion de templates et listes de diffusion
 * Version: 1.1.1
 * Author: Antonin pour Tabac des Battières
 * Author URI: https://tabac-des-battieres.com
 * License: GPL-2.0+
 * Text Domain: wp-mail-sender
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_MAIL_SENDER_VERSION', '1.1.1');
define('WP_MAIL_SENDER_PATH', plugin_dir_path(__FILE__));
define('WP_MAIL_SENDER_URL', plugin_dir_url(__FILE__));
define('WP_MAIL_SENDER_BASENAME', plugin_basename(__FILE__));

/**
 * Auto-detect environment (staging vs production)
 */
function wp_mail_sender_detect_environment() {
    $site_url = get_site_url();
    $is_staging = (
        strpos($site_url, 'staging.') !== false ||
        strpos($site_url, 'dev.') !== false ||
        strpos($site_url, 'test.') !== false ||
        strpos(ABSPATH, 'staging') !== false
    );
    
    return $is_staging ? 'staging' : 'production';
}

$wp_mail_sender_env = wp_mail_sender_detect_environment();

// SMTP Configuration (identique pour staging et production)
define('WP_MAIL_SENDER_SMTP_HOST', 'mail.tabac-des-battieres.com');
define('WP_MAIL_SENDER_SMTP_PORT', 465);
define('WP_MAIL_SENDER_SMTP_USER', 'social@tabac-des-battieres.com');
define('WP_MAIL_SENDER_SMTP_SECURE', 'ssl');

// Database configuration - Auto-détection avec fallback
if ($wp_mail_sender_env === 'staging') {
    // Staging: peut utiliser la même DB ou une DB de test
    define('WP_MAIL_SENDER_SOURCE_DB', 'pora1119_wp535'); // ou DB staging dédiée
    define('WP_MAIL_SENDER_MAILING_DB', 'pora1119_mailing');
    define('WP_MAIL_SENDER_MAILING_USER', 'pora1119_mailing_user');
} else {
    // Production: même config mais peut être surchargée via wp-config.php
    define('WP_MAIL_SENDER_SOURCE_DB', defined('WP_MAIL_SENDER_PROD_SOURCE_DB') ? WP_MAIL_SENDER_PROD_SOURCE_DB : 'pora1119_wp535');
    define('WP_MAIL_SENDER_MAILING_DB', defined('WP_MAIL_SENDER_PROD_MAILING_DB') ? WP_MAIL_SENDER_PROD_MAILING_DB : 'pora1119_mailing');
    define('WP_MAIL_SENDER_MAILING_USER', defined('WP_MAIL_SENDER_PROD_MAILING_USER') ? WP_MAIL_SENDER_PROD_MAILING_USER : 'pora1119_mailing_user');
}

// Password fallback: wp-config.php > option WP > constante vide

// Table prefix for source database (WooCommerce)
define('WP_MAIL_SENDER_SOURCE_PREFIX', 'wp5i_');

// Table prefix for mailing database
define('WP_MAIL_SENDER_TABLE_PREFIX', 'mailing_');

// Log environment detection
define('WP_MAIL_SENDER_ENVIRONMENT', $wp_mail_sender_env);

/**
 * Main plugin class
 */
class WP_Mail_Sender {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once WP_MAIL_SENDER_PATH . 'includes/class-wp-mail-sender-db.php';
        require_once WP_MAIL_SENDER_PATH . 'includes/class-wp-mail-sender-smtp.php';
        require_once WP_MAIL_SENDER_PATH . 'includes/class-wp-mail-sender-core.php';
        
        // Admin classes
        if (is_admin()) {
            require_once WP_MAIL_SENDER_PATH . 'admin/class-wp-mail-sender-admin.php';
            require_once WP_MAIL_SENDER_PATH . 'admin/class-wp-mail-sender-templates.php';
            require_once WP_MAIL_SENDER_PATH . 'admin/class-wp-mail-sender-lists.php';
            require_once WP_MAIL_SENDER_PATH . 'admin/class-wp-mail-sender-segments.php';
            require_once WP_MAIL_SENDER_PATH . 'admin/class-wp-mail-sender-send.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'init_admin'), 1); // Priority 1 to load early
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize database
        WP_Mail_Sender_DB::get_instance();
        
        // Initialize SMTP configuration
        WP_Mail_Sender_SMTP::get_instance();
        
        // Initialize core functionality
        WP_Mail_Sender_Core::get_instance();
        
        error_log('[WP Mail Sender INFO] [' . current_time('mysql') . '] Plugin initialized');
    }
    
    /**
     * Initialize admin interface
     */
    public function init_admin() {
        if (is_admin()) {
            WP_Mail_Sender_Admin::get_instance();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        WP_Mail_Sender_DB::create_tables();
        error_log('[WP Mail Sender INFO] [' . current_time('mysql') . '] Plugin activated on ' . WP_MAIL_SENDER_ENVIRONMENT . ' environment');
        error_log('[WP Mail Sender INFO] Source DB: ' . WP_MAIL_SENDER_SOURCE_DB . ', Mailing DB: ' . WP_MAIL_SENDER_MAILING_DB);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        error_log('[WP Mail Sender INFO] [' . current_time('mysql') . '] Plugin deactivated');
    }
}

// Initialize plugin
function wp_mail_sender_init() {
    return WP_Mail_Sender::get_instance();
}

add_action('plugins_loaded', 'wp_mail_sender_init');
