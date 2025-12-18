<?php
/**
 * SMTP configuration and email hook handler - Multi-SMTP support
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Social_Mail_Sender_SMTP {
    
    private static $instance = null;
    private $smtp_config = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Load SMTP configuration
        $this->smtp_config = WP_SOCIAL_MAIL_SENDER_SMTP_CONFIG;
        
        if (empty($this->smtp_config)) {
            error_log('[WP Social Mail Sender SMTP ERROR] No SMTP configuration found');
        }
        
        // Load passwords from wp-config or database
        $this->load_passwords();
        
        // Hook into phpmailer_init to configure SMTP settings based on From email
        add_action('phpmailer_init', array($this, 'configure_phpmailer'), 10, 1);
    }
    
    /**
     * Load passwords from wp-config constants or database
     */
    private function load_passwords() {
        foreach ($this->smtp_config as $email => $config) {
            // Password priority: wp-config constant > database option
            $password_constant = 'WP_SOCIAL_MAIL_SENDER_PASSWORD_' . strtoupper(str_replace(array('@', '.'), '_', $email));
            
            if (defined($password_constant)) {
                $this->smtp_config[$email]['password'] = constant($password_constant);
            } else {
                $this->smtp_config[$email]['password'] = get_option('wp_social_mail_sender_password_' . $email, '');
            }
            
            if (empty($this->smtp_config[$email]['password'])) {
                error_log('[WP Social Mail Sender SMTP ERROR] Password not configured for: ' . $email);
            }
        }
    }
    
    /**
     * Get SMTP config for a specific email address
     * 
     * @param string $email The email address
     * @return array|false Configuration array or false if not found
     */
    private function get_config_for_email($email) {
        if (isset($this->smtp_config[$email])) {
            return $this->smtp_config[$email];
        }
        return false;
    }
    
    /**
     * Configure PHPMailer instance with SMTP settings based on From email
     * This hook is called by WordPress when wp_mail() is triggered
     * 
     * @param PHPMailer $phpmailer The PHPMailer instance
     */
    public function configure_phpmailer($phpmailer) {
        try {
            $from_email = $phpmailer->From;
            error_log('[WP Social Mail Sender SMTP] Processing email from: ' . $from_email);
            
            // Get configuration for this email address
            $config = $this->get_config_for_email($from_email);
            
            if (!$config) {
                error_log('[WP Social Mail Sender SMTP WARNING] No SMTP config found for: ' . $from_email . ', using default');
                return; // Use WordPress default
            }
            
            if (empty($config['password'])) {
                error_log('[WP Social Mail Sender SMTP ERROR] Password not configured for: ' . $from_email);
                return;
            }
            
            // Log configuration being applied
            error_log('[WP Social Mail Sender SMTP] Configuring SMTP for: ' . $from_email);
            error_log('[WP Social Mail Sender SMTP] Host: ' . $config['host'] . ':' . $config['port']);
            error_log('[WP Social Mail Sender SMTP] User: ' . $config['user']);
            error_log('[WP Social Mail Sender SMTP] Secure: ' . $config['secure']);
            
            // Configure SMTP
            $phpmailer->isSMTP();
            $phpmailer->Host = $config['host'];
            $phpmailer->Port = $config['port'];
            $phpmailer->Username = $config['user'];
            $phpmailer->Password = $config['password'];
            $phpmailer->SMTPAuth = true;
            $phpmailer->CharSet = 'UTF-8';
            $phpmailer->SMTPAutoTLS = false;
            $phpmailer->Debugoutput = 'error_log';
            $phpmailer->SMTPDebug = 2;
            
            // Set encryption
            if ('ssl' === $config['secure']) {
                $phpmailer->SMTPSecure = 'ssl';
            } elseif ('tls' === $config['secure']) {
                $phpmailer->SMTPSecure = 'tls';
            }
            
            error_log('[WP Social Mail Sender SMTP] PHPMailer configured successfully for: ' . $from_email);
            
        } catch (Exception $e) {
            error_log('[WP Social Mail Sender SMTP ERROR] Configuration failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test SMTP connection for a specific email
     * 
     * @param string $email Email address to test
     * @return array Test result
     */
    public function test_connection($email = null) {
        error_log('[WP Social Mail Sender SMTP] Starting connection test...');
        
        // If no email specified, use first configured
        if (!$email && !empty($this->smtp_config)) {
            $email = array_key_first($this->smtp_config);
        }
        
        if (!$email) {
            error_log('[WP Social Mail Sender SMTP ERROR] No email address provided');
            return array('success' => false, 'message' => 'No email address configured');
        }
        
        $config = $this->get_config_for_email($email);
        if (!$config) {
            error_log('[WP Social Mail Sender SMTP ERROR] Configuration not found for: ' . $email);
            return array('success' => false, 'message' => 'SMTP configuration not found for: ' . $email);
        }
        
        if (empty($config['password'])) {
            error_log('[WP Social Mail Sender SMTP ERROR] Password not configured for: ' . $email);
            return array('success' => false, 'message' => 'SMTP password not configured for: ' . $email);
        }
        
        try {
            $admin_email = get_option('admin_email');
            $subject = '[Test] WP Social Mail Sender SMTP Test - ' . $email;
            $body = '<p>This is a test email from: <strong>' . $email . '</strong></p>';
            
            error_log('[WP Social Mail Sender SMTP] Test details:');
            error_log('[WP Social Mail Sender SMTP] - Admin email: ' . $admin_email);
            error_log('[WP Social Mail Sender SMTP] - From: ' . $email);
            error_log('[WP Social Mail Sender SMTP] Attempting to send test email...');
            
            // Send test email with specified From address
            $headers = array('From: ' . $email);
            $result = wp_mail($admin_email, $subject, $body, $headers);
            
            if ($result) {
                error_log('[WP Social Mail Sender SMTP] ✓ Connection test SUCCESSFUL - test email sent');
                return array(
                    'success' => true, 
                    'message' => 'SMTP connection successful! Test email sent to: ' . $admin_email . ' from: ' . $email
                );
            } else {
                error_log('[WP Social Mail Sender SMTP] ✗ Connection test FAILED - wp_mail returned false');
                error_log('[WP Social Mail Sender SMTP] Check WordPress debug.log for more details');
                return array(
                    'success' => false, 
                    'message' => 'SMTP connection failed. Check debug.log for details.'
                );
            }
            
        } catch (Exception $e) {
            error_log('[WP Social Mail Sender SMTP ERROR] Exception during test: ' . $e->getMessage());
            error_log('[WP Social Mail Sender SMTP ERROR] Stack trace: ' . $e->getTraceAsString());
            return array('success' => false, 'message' => 'Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all configured SMTP accounts
     */
    public function get_all_accounts() {
        $accounts = array();
        foreach ($this->smtp_config as $email => $config) {
            $accounts[$email] = array(
                'email' => $email,
                'host' => $config['host'],
                'port' => $config['port'],
                'user' => $config['user'],
                'secure' => $config['secure'],
                'password_set' => !empty($config['password'])
            );
        }
        return $accounts;
    }
}
