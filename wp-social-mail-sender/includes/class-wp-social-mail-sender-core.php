<?php
/**
 * Core email sending functionality with custom From address
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Social_Mail_Sender_Core {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize SMTP configuration
        WP_Social_Mail_Sender_SMTP::get_instance();
        
        // Hook to override the From email header
        add_filter('wp_mail_from', array($this, 'set_from_email'), 10, 1);
        add_filter('wp_mail_from_name', array($this, 'set_from_name'), 10, 1);
        add_filter('wp_mail_content_type', array($this, 'set_content_type'), 10, 1);
    }
    
    /**
     * Set the From email address for all outgoing emails
     * 
     * @param string $from_email Default from email
     * @return string Modified from email
     */
    public function set_from_email($from_email) {
        error_log('[WP Social Mail Sender] Setting From email to: ' . WP_SOCIAL_MAIL_SENDER_SMTP_USER);
        return WP_SOCIAL_MAIL_SENDER_SMTP_USER;
    }
    
    /**
     * Set the From name for all outgoing emails
     * 
     * @param string $from_name Default from name
     * @return string Modified from name
     */
    public function set_from_name($from_name) {
        return get_bloginfo('name');
    }
    
    /**
     * Set content type to HTML for all emails
     * 
     * @param string $content_type Default content type
     * @return string HTML content type
     */
    public function set_content_type($content_type) {
        return 'text/html';
    }
}
