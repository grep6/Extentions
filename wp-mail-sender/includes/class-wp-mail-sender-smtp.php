<?php
/**
 * SMTP Configuration class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Mail_Sender_SMTP {
    
    private static $instance = null;
    private $from_email = null;
    private $from_name = null;
    
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
        add_action('wp_mail_failed', array($this, 'on_mail_failed'));
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
        
        // Log configuration details
        error_log('[WP Mail Sender SMTP INFO] [' . current_time('mysql') . '] Preparing SMTP config');
        error_log('[WP Mail Sender SMTP INFO] Host: ' . WP_MAIL_SENDER_SMTP_HOST . ' Port: ' . WP_MAIL_SENDER_SMTP_PORT);
        error_log('[WP Mail Sender SMTP INFO] User: ' . WP_MAIL_SENDER_SMTP_USER . ' Secure: ' . WP_MAIL_SENDER_SMTP_SECURE);
        
        $phpmailer->isSMTP();
        $phpmailer->Host = WP_MAIL_SENDER_SMTP_HOST;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = WP_MAIL_SENDER_SMTP_PORT;
        $phpmailer->Username = WP_MAIL_SENDER_SMTP_USER;
        $phpmailer->Password = $smtp_password;
        $phpmailer->CharSet = 'UTF-8';
        
        // Encryption & TLS negotiation
        $phpmailer->SMTPSecure = WP_MAIL_SENDER_SMTP_SECURE; // 'ssl' or 'tls'
        // Disable AutoTLS for implicit SSL (port 465) to avoid STARTTLS negotiation issues
        $phpmailer->SMTPAutoTLS = (strtolower(WP_MAIL_SENDER_SMTP_SECURE) === 'tls');
        
        // Enable debug output when requested
        if (defined('WP_MAIL_SENDER_DEBUG') && WP_MAIL_SENDER_DEBUG) {
            $phpmailer->SMTPDebug = 2; // client and server messages
            $phpmailer->Debugoutput = function($str, $level) {
                error_log('[WP Mail Sender SMTP DEBUG] ' . $str);
            };
        }
        
        // Force From and Reply-To to the configured address
        $from_email = $this->get_from_email();
        $from_name = $this->get_from_name();
        if (!empty($from_email)) {
            $phpmailer->setFrom($from_email, $from_name, false);
            $phpmailer->clearReplyTos();
            $reply_email = $this->get_reply_to_email();
            $reply_name  = $this->get_reply_to_name();
            if (!empty($reply_email)) {
                $phpmailer->addReplyTo($reply_email, $reply_name ?: $from_name);
            } else {
                $phpmailer->addReplyTo($from_email, $from_name);
            }
        }
        
        error_log('[WP Mail Sender SMTP INFO] [' . current_time('mysql') . '] SMTP configured for ' . WP_MAIL_SENDER_SMTP_USER);
    }
    
    /**
     * Set from email address
     */
    public function mail_from($email) {
        return $this->get_from_email();
    }
    
    /**
     * Set from name
     */
    public function mail_from_name($name) {
        return $this->get_from_name();
    }

    private function get_from_email() {
        if ($this->from_email !== null) {
            return $this->from_email;
        }
        $configured = get_option('wp_mail_sender_from_email', WP_MAIL_SENDER_SMTP_USER);
        $this->from_email = is_email($configured) ? $configured : WP_MAIL_SENDER_SMTP_USER;
        return $this->from_email;
    }

    private function get_from_name() {
        if ($this->from_name !== null) {
            return $this->from_name;
        }
        $this->from_name = get_option('wp_mail_sender_from_name', 'Tabac des Battières');
        return $this->from_name;
    }

    /**
     * Public accessors for sender identity
     */
    public function get_sender_email() {
        return $this->get_from_email();
    }

    public function get_sender_name() {
        return $this->get_from_name();
    }

    /**
     * Reply-To accessors (options are optional)
     */
    public function get_reply_to_email() {
        $email = get_option('wp_mail_sender_reply_to_email', '');
        return is_email($email) ? $email : '';
    }

    public function get_reply_to_name() {
        return get_option('wp_mail_sender_reply_to_name', '');
    }
    
    /**
     * Log detailed failure info when wp_mail fails
     */
    public function on_mail_failed($wp_error) {
        $msg = $wp_error instanceof WP_Error ? $wp_error->get_error_message() : 'Unknown error';
        error_log('[WP Mail Sender SMTP ERROR] [' . current_time('mysql') . '] wp_mail_failed: ' . $msg);
        if ($wp_error instanceof WP_Error) {
            $data = $wp_error->get_error_data();
            if (!empty($data)) {
                error_log('[WP Mail Sender SMTP ERROR] Error data: ' . print_r($data, true));
            }
        }
    }
    
    /**
     * Test SMTP connection
     */
    public static function test_connection() {
        $to = get_option('admin_email');
        $subject = 'WP Mail Sender - Test de connexion SMTP';
        $message = 'Ceci est un email de test pour vérifier la configuration SMTP.';
        
        // Force From header to ensure correct account
        $headers = array('From: ' . WP_MAIL_SENDER_SMTP_USER);
        $result = wp_mail($to, $subject, $message, $headers);
        
        if ($result) {
            error_log('[WP Mail Sender SMTP] [' . current_time('mysql') . '] Test email sent successfully');
            return true;
        } else {
            error_log('[WP Mail Sender SMTP ERROR] [' . current_time('mysql') . '] Failed to send test email');
            return false;
        }
    }
}
