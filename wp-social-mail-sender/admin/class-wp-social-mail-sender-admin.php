<?php
/**
 * Admin interface and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Social_Mail_Sender_Admin {
    
    private static $instance = null;
    private $smtp;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->smtp = WP_Social_Mail_Sender_SMTP::get_instance();
        
        // Initialize send functionality
        WP_Social_Mail_Sender_Send::get_instance();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_test_smtp_connection', array($this, 'handle_ajax_test_connection'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Social Mail Sender',
            'Social Mail Sender',
            'manage_options',
            'wp-social-mail-sender',
            array($this, 'render_page'),
            'dashicons-email',
            30
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        if ('toplevel_page_wp-social-mail-sender' !== $hook_suffix) {
            return;
        }
        
        wp_enqueue_style(
            'wp-social-mail-sender-admin',
            WP_SOCIAL_MAIL_SENDER_URL . 'admin/css/admin.css',
            array(),
            WP_SOCIAL_MAIL_SENDER_VERSION
        );
        
        wp_enqueue_script(
            'wp-social-mail-sender-admin',
            WP_SOCIAL_MAIL_SENDER_URL . 'admin/js/admin.js',
            array('jquery'),
            WP_SOCIAL_MAIL_SENDER_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'wp-social-mail-sender-admin',
            'wpSocialMailSenderAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_social_mail_sender_admin')
            )
        );
    }
    
    /**
     * Handle AJAX test connection
     */
    public function handle_ajax_test_connection() {
        check_ajax_referer('wp_social_mail_sender_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : null;
        $result = $this->smtp->test_connection($email);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle settings save
     */
    public function handle_settings_save() {
        if (!isset($_POST['wp_social_mail_sender_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['wp_social_mail_sender_nonce'], 'wp_social_mail_sender_settings')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (isset($_POST['wp_social_mail_sender_smtp_password'])) {
            update_option(
                'wp_social_mail_sender_smtp_password',
                sanitize_text_field($_POST['wp_social_mail_sender_smtp_password'])
            );
            
            // Test connection
            $result = $this->smtp->test_connection();
            
            if ($result['success']) {
                add_settings_error(
                    'wp_social_mail_sender',
                    'success',
                    'Settings saved and SMTP connection successful!',
                    'updated'
                );
            } else {
                add_settings_error(
                    'wp_social_mail_sender',
                    'error',
                    'Settings saved but SMTP connection failed: ' . $result['message'],
                    'error'
                );
            }
        }
    }
    
    /**
     * Render admin page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        
        $settings_errors = get_settings_errors();
        $accounts = $this->smtp->get_all_accounts();
        ?>
        <div class="wrap wp-social-mail-sender-admin">
            <h1>Social Mail Sender - Multi-SMTP Configuration</h1>
            
            <?php if (!empty($settings_errors)): ?>
                <?php settings_errors(); ?>
            <?php endif; ?>
            
            <div class="wp-social-mail-sender-container">
                
                <!-- SMTP Accounts Section -->
                <div class="wp-social-mail-sender-card">
                    <h2>✉️ Configured SMTP Accounts</h2>
                    
                    <?php if (empty($accounts)): ?>
                        <p style="color: #666;">No SMTP accounts configured. Edit wp-social-mail-sender.php to add accounts.</p>
                    <?php else: ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>Email Address</th>
                                    <th>SMTP Host</th>
                                    <th>Port</th>
                                    <th>Secure</th>
                                    <th>Password</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $account): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($account['email']); ?></strong></td>
                                        <td><?php echo esc_html($account['host']); ?></td>
                                        <td><?php echo esc_html($account['port']); ?></td>
                                        <td><?php echo esc_html(strtoupper($account['secure'])); ?></td>
                                        <td>
                                            <?php if ($account['password_set']): ?>
                                                <span style="color: green;">✓ Configured</span>
                                            <?php else: ?>
                                                <span style="color: red;">✗ Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="button button-small test-connection-btn" data-email="<?php echo esc_attr($account['email']); ?>">
                                                Test
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Configuration Guide -->
                <div class="wp-social-mail-sender-card">
                    <h2>⚙️ Configuration Guide</h2>
                    <p>Add or modify SMTP accounts in <code>wp-social-mail-sender.php</code>:</p>
                    <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;"><code>$wp_social_mail_sender_smtp_config = array(
    'social@tabac-des-battieres.com' => array(
        'host' => 'mail.tabac-des-battieres.com',
        'port' => 465,
        'user' => 'social@tabac-des-battieres.com',
        'secure' => 'ssl'
    ),
    'contact@example.com' => array(
        'host' => 'smtp.example.com',
        'port' => 587,
        'user' => 'contact@example.com',
        'secure' => 'tls'
    ),
);</code></pre>
                    
                    <h3>Store Passwords Securely</h3>
                    <p>Add to <code>wp-config.php</code> (before wp-settings.php):</p>
                    <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;"><code>define('WP_SOCIAL_MAIL_SENDER_PASSWORD_SOCIAL_TABAC_DES_BATTIERES_COM', 'password123');
define('WP_SOCIAL_MAIL_SENDER_PASSWORD_CONTACT_EXAMPLE_COM', 'password456');</code></pre>
                    
                    <p><strong>Password naming rule:</strong> <code>WP_SOCIAL_MAIL_SENDER_PASSWORD_</code> + email in UPPERCASE with @ and . replaced by _</p>
                </div>
                
                <!-- Send Email Section -->
                <div class="wp-social-mail-sender-card">
                    <?php WP_Social_Mail_Sender_Send::get_instance()->render_form(); ?>
                </div>
                
                <!-- Information Section -->
                <div class="wp-social-mail-sender-card">
                    <h2>ℹ️ Information</h2>
                    <p><strong>Plugin Version:</strong> <?php echo esc_html(WP_SOCIAL_MAIL_SENDER_VERSION); ?></p>
                    <p><strong>Feature:</strong> Multi-SMTP support - automatically selects correct SMTP based on From email</p>
                    <p><strong>How it works:</strong> When an email is sent, the plugin checks the From address and uses the corresponding SMTP configuration</p>
                </div>
                
            </div>
        </div>
        <?php
    }
}
