<?php
/**
 * Plugin Name: WP Social Mail Sender
 * Plugin URI: https://tabac-des-battieres.com
 * Description: Extension d'envoi d'emails depuis une adresse email spécifique via SMTP
 * Version: 1.0.0
 * Author: Antonin pour Tabac des Battières
 * Author URI: https://tabac-des-battieres.com
 * License: GPL-2.0+
 * Text Domain: wp-social-mail-sender
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_SOCIAL_MAIL_SENDER_VERSION', '1.0.0');
define('WP_SOCIAL_MAIL_SENDER_PATH', plugin_dir_path(__FILE__));
define('WP_SOCIAL_MAIL_SENDER_URL', plugin_dir_url(__FILE__));
define('WP_SOCIAL_MAIL_SENDER_BASENAME', plugin_basename(__FILE__));

// SMTP Configuration - Multi-SMTP support
// Format: 'email@domain.com' => array(host, port, user, password, secure)
// You can add multiple SMTP configurations here
$wp_social_mail_sender_smtp_config = array(
    'social@tabac-des-battieres.com' => array(
        'host' => 'mail.tabac-des-battieres.com',
        'port' => 465,
        'user' => 'social@tabac-des-battieres.com',
        'secure' => 'ssl'
    ),
    // Add more SMTP configurations below:
    // 'contact@example.com' => array(
    //     'host' => 'smtp.example.com',
    //     'port' => 587,
    //     'user' => 'contact@example.com',
    //     'secure' => 'tls'
    // ),
);

// Allow overriding via wp-config.php
if (!defined('WP_SOCIAL_MAIL_SENDER_SMTP_CONFIG')) {
    define('WP_SOCIAL_MAIL_SENDER_SMTP_CONFIG', $wp_social_mail_sender_smtp_config);
}


/**
 * Main plugin class
 */
class WP_Social_Mail_Sender {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once WP_SOCIAL_MAIL_SENDER_PATH . 'includes/class-wp-social-mail-sender-core.php';
        require_once WP_SOCIAL_MAIL_SENDER_PATH . 'includes/class-wp-social-mail-sender-smtp.php';
        require_once WP_SOCIAL_MAIL_SENDER_PATH . 'admin/class-wp-social-mail-sender-send.php';
        require_once WP_SOCIAL_MAIL_SENDER_PATH . 'admin/class-wp-social-mail-sender-admin.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize admin
        if (is_admin()) {
            WP_Social_Mail_Sender_Admin::get_instance();
        }
        
        // Initialize core functionality
        WP_Social_Mail_Sender_Core::get_instance();
    }
}

/**
 * Initialize plugin
 */
function wp_social_mail_sender_init() {
    return WP_Social_Mail_Sender::get_instance();
}

// Start plugin
wp_social_mail_sender_init();

// Activation hook
register_activation_hook(__FILE__, 'wp_social_mail_sender_activate');
function wp_social_mail_sender_activate() {
    // Add custom settings option
    add_option('wp_social_mail_sender_smtp_password', '');
    error_log('[WP Social Mail Sender] Plugin activated');
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wp_social_mail_sender_deactivate');
function wp_social_mail_sender_deactivate() {
    error_log('[WP Social Mail Sender] Plugin deactivated');
}
