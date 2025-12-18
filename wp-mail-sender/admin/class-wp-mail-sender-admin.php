<?php
/**
 * Admin interface class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Mail_Sender_Admin {
    
    private static $instance = null;
    private $db;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = WP_Mail_Sender_DB::get_instance();
        
        // Initialize sub-classes FIRST (before hooks)
        WP_Mail_Sender_Templates::get_instance();
        WP_Mail_Sender_Lists::get_instance();
        WP_Mail_Sender_Segments::get_instance();
        WP_Mail_Sender_Send::get_instance();
        
        // Then add hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_settings_save'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            'Mail Sender',
            'Mail Sender',
            'manage_options',
            'wp-mail-sender',
            array($this, 'render_dashboard_page'),
            'dashicons-email',
            30
        );
        
        // Sous-menu : Tableau de bord (remplace le menu principal)
        add_submenu_page(
            'wp-mail-sender',
            'Tableau de bord',
            'Tableau de bord',
            'manage_options',
            'wp-mail-sender',
            array($this, 'render_dashboard_page')
        );
        
        // Sous-menu : Configuration (AJOUTÉ ICI pour éviter le doublon)
        add_submenu_page(
            'wp-mail-sender',
            'Configuration',
            'Configuration',
            'manage_options',
            'wp-mail-sender-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Charger uniquement sur les pages du plugin
        if (strpos($hook, 'wp-mail-sender') === false) {
            return;
        }
        
        wp_enqueue_style(
            'wp-mail-sender-admin',
            WP_MAIL_SENDER_URL . 'admin/css/admin.css',
            array(),
            WP_MAIL_SENDER_VERSION
        );
        
        wp_enqueue_script(
            'wp-mail-sender-admin',
            WP_MAIL_SENDER_URL . 'admin/js/admin.js',
            array('jquery'),
            WP_MAIL_SENDER_VERSION,
            true
        );
        
        wp_localize_script('wp-mail-sender-admin', 'wpMailSender', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_mail_sender_nonce')
        ));
    }
    
    /**
     * Handle settings save
     */
    public function handle_settings_save() {
        if (!isset($_POST['wp_mail_sender_settings_nonce']) && !isset($_POST['wp_mail_sender_simple_test_nonce'])) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle simple test email
        if (isset($_POST['send_simple_test']) && isset($_POST['wp_mail_sender_simple_test_nonce'])) {
            if (!wp_verify_nonce($_POST['wp_mail_sender_simple_test_nonce'], 'wp_mail_sender_simple_test')) {
                return;
            }
            
            $to = sanitize_email($_POST['test_email']);
            $subject = sanitize_text_field($_POST['test_subject']);
            $message = sanitize_textarea_field($_POST['test_message']);
            
            if (empty($to) || !is_email($to)) {
                add_settings_error('wp_mail_sender', 'test_invalid_email', 'Adresse email invalide.', 'error');
                return;
            }
            
            $headers = array('Content-Type: text/html; charset=UTF-8');

            // Apply explicit sender for consistent identity in tests
            if (class_exists('WP_Mail_Sender_SMTP')) {
                $smtp = WP_Mail_Sender_SMTP::get_instance();
                if (method_exists($smtp, 'get_sender_email') && method_exists($smtp, 'get_sender_name')) {
                    $from_email = $smtp->get_sender_email();
                    $from_name = $smtp->get_sender_name();
                    if (!empty($from_email)) {
                        $headers[] = 'From: ' . (empty($from_name) ? $from_email : ($from_name . ' <' . $from_email . '>'));
                        $reply_email = method_exists($smtp, 'get_reply_to_email') ? $smtp->get_reply_to_email() : '';
                        $reply_name  = method_exists($smtp, 'get_reply_to_name') ? $smtp->get_reply_to_name() : '';
                        if (!empty($reply_email)) {
                            $headers[] = 'Reply-To: ' . (empty($reply_name) ? $reply_email : ($reply_name . ' <' . $reply_email . '>'));
                        } else {
                            $headers[] = 'Reply-To: ' . (empty($from_name) ? $from_email : ($from_name . ' <' . $from_email . '>'));
                        }
                    }
                }
            }
            $html_message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6;">';
            $html_message .= '<h2 style="color: #0073aa;">Test WP Mail Sender</h2>';
            $html_message .= '<div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #00a32a; margin: 20px 0;">';
            $html_message .= nl2br(esc_html($message));
            $html_message .= '</div>';
            $html_message .= '<hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">';
            $html_message .= '<p style="color: #666; font-size: 12px;">Envoyé via WP Mail Sender Plugin</p>';
            $html_message .= '</body></html>';
            
            $result = wp_mail($to, $subject, $html_message, $headers);
            
            if ($result) {
                add_settings_error('wp_mail_sender', 'test_success', '✅ Email de test envoyé avec succès à ' . $to . ' !', 'updated');
                error_log('[WP Mail Sender TEST] Email sent successfully to: ' . $to);
            } else {
                add_settings_error('wp_mail_sender', 'test_error', '❌ Échec de l\'envoi de l\'email de test. Vérifiez les logs.', 'error');
                error_log('[WP Mail Sender TEST ERROR] Failed to send test email to: ' . $to);
            }
            return;
        }
        
        // Handle settings save
        if (!wp_verify_nonce($_POST['wp_mail_sender_settings_nonce'], 'wp_mail_sender_settings')) {
            return;
        }
        
        // Save SMTP password
        if (isset($_POST['smtp_password'])) {
            update_option('wp_mail_sender_smtp_password', sanitize_text_field($_POST['smtp_password']));
        }
        
        // Save DB password
        if (isset($_POST['db_password'])) {
            update_option('wp_mail_sender_db_password', sanitize_text_field($_POST['db_password']));
        }
        
        // Save from name
        if (isset($_POST['from_name'])) {
            update_option('wp_mail_sender_from_name', sanitize_text_field($_POST['from_name']));
        }
        
        // Save from email
        if (isset($_POST['from_email'])) {
            $email = sanitize_email($_POST['from_email']);
            if (!empty($email) && is_email($email)) {
                update_option('wp_mail_sender_from_email', $email);
            }
        }

        // Save reply-to email (optional)
        if (isset($_POST['reply_to_email'])) {
            $reply = sanitize_email($_POST['reply_to_email']);
            if (empty($reply)) {
                update_option('wp_mail_sender_reply_to_email', '');
            } elseif (is_email($reply)) {
                update_option('wp_mail_sender_reply_to_email', $reply);
            }
        }

        // Save reply-to name (optional)
        if (isset($_POST['reply_to_name'])) {
            update_option('wp_mail_sender_reply_to_name', sanitize_text_field($_POST['reply_to_name']));
        }
        
        // Test connection if requested
        if (isset($_POST['test_connection'])) {
            if (WP_Mail_Sender_SMTP::test_connection()) {
                add_settings_error('wp_mail_sender', 'test_success', 'Email de test envoyé avec succès !', 'updated');
            } else {
                add_settings_error('wp_mail_sender', 'test_error', 'Échec de l\'envoi de l\'email de test. Vérifiez les logs.', 'error');
            }
        } else {
            add_settings_error('wp_mail_sender', 'settings_updated', 'Paramètres enregistrés.', 'updated');
        }
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        // Check DB connection first
        if (!$this->db->is_mailing_connected()) {
            ?>
            <div class="wrap">
                <h1>Mail Sender - Tableau de bord</h1>
                <div class="notice notice-error">
                    <p><strong>Erreur de connexion à la base de données :</strong> <?php echo esc_html($this->db->get_connection_error()); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=wp-mail-sender-settings'); ?>" class="button">Configurer les mots de passe</a></p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Use dedicated mailing DB connection
        $wpdb = $this->db->get_mailing_wpdb();
        $mailing_db = WP_MAIL_SENDER_MAILING_DB;
        
        // Get statistics - Optimized with specific columns
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $total_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM `{$mailing_db}`.`{$prefix}campaigns`");
        $total_sent = $wpdb->get_var("SELECT COALESCE(SUM(sent_count), 0) FROM `{$mailing_db}`.`{$prefix}campaigns`");
        $total_templates = $wpdb->get_var("SELECT COUNT(*) FROM `{$mailing_db}`.`{$prefix}templates`");
        $total_lists = $wpdb->get_var("SELECT COUNT(*) FROM `{$mailing_db}`.`{$prefix}lists`");
        
        // Get recent campaigns - OPTIMIZED: INNER JOIN instead of LEFT JOIN, specific columns
        $recent_campaigns = $wpdb->get_results("
            SELECT 
                c.id,
                c.name,
                c.status,
                c.scheduled_at,
                c.sent_at,
                c.total_recipients,
                c.sent_count,
                c.failed_count,
                c.created_at,
                t.name AS template_name,
                l.name AS list_name
            FROM `{$mailing_db}`.`{$prefix}campaigns` c
            INNER JOIN `{$mailing_db}`.`{$prefix}templates` t ON c.template_id = t.id
            INNER JOIN `{$mailing_db}`.`{$prefix}lists` l ON c.list_id = l.id
            ORDER BY c.created_at DESC
            LIMIT 10
        ");
        
        include WP_MAIL_SENDER_PATH . 'admin/views/dashboard.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        include WP_MAIL_SENDER_PATH . 'admin/views/settings.php';
    }
}
