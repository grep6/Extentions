<?php
/**
 * SMTP Configuration class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Mail_Sender_SMTP {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('phpmailer_init', array($this, 'configure_smtp'));
        add_filter('wp_mail_from', array($this, 'mail_from'));
        add_filter('wp_mail_from_name', array($this, 'mail_from_name'));
    }
    
    /**
     * Configure PHPMailer with SMTP settings
     */
    public function configure_smtp($phpmailer) {
        // Récupérer le mot de passe (priorité: wp-config.php > option WP > constante vide)
        $smtp_password = defined('WP_MAIL_SENDER_SMTP_PASSWORD') && WP_MAIL_SENDER_SMTP_PASSWORD 
            ? WP_MAIL_SENDER_SMTP_PASSWORD 
            : get_option('wp_mail_sender_smtp_password', '');
        
        if (empty($smtp_password)) {
            error_log('[WP Mail Sender SMTP ERROR] [' . current_time('mysql') . '] SMTP password not configured');
            return; // Ne pas tenter d'envoyer sans mot de passe
        }
        
        $phpmailer->isSMTP();
        $phpmailer->Host = WP_MAIL_SENDER_SMTP_HOST;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = WP_MAIL_SENDER_SMTP_PORT;
        $phpmailer->Username = WP_MAIL_SENDER_SMTP_USER;
        $phpmailer->Password = $smtp_password;
        $phpmailer->SMTPSecure = WP_MAIL_SENDER_SMTP_SECURE;
        $phpmailer->SMTPAutoTLS = true;
        
        // Debug mode (disabled by default)
        if (defined('WP_MAIL_SENDER_DEBUG') && WP_MAIL_SENDER_DEBUG) {
            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function($str, $level) {
                error_log('[WP Mail Sender SMTP DEBUG] ' . $str);
            };
        }
        
        error_log('[WP Mail Sender SMTP INFO] [' . current_time('mysql') . '] SMTP configured for ' . WP_MAIL_SENDER_SMTP_USER);
    }
    
    /**
     * Set from email address
     */
    public function mail_from($email) {
        return WP_MAIL_SENDER_SMTP_USER;
    }
    
    /**
     * Set from name
     */
    public function mail_from_name($name) {
        return get_option('wp_mail_sender_from_name', 'Tabac des Battières');
    }
    
    /**
     * Test SMTP connection
     */
    public static function test_connection() {
        $to = get_option('admin_email');
        $subject = 'WP Mail Sender - Test de connexion SMTP';
        $message = 'Ceci est un email de test pour vérifier la configuration SMTP.';
        
        $result = wp_mail($to, $subject, $message);
        
        if ($result) {
            error_log('[WP Mail Sender SMTP] [' . current_time('mysql') . '] Test email sent successfully');
            return true;
        } else {
            error_log('[WP Mail Sender SMTP ERROR] [' . current_time('mysql') . '] Failed to send test email');
            return false;
        }
    }
}
