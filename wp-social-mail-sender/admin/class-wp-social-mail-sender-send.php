<?php
/**
 * Send email interface and handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Social_Mail_Sender_Send {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'handle_email_send'));
        add_action('wp_ajax_send_test_email', array($this, 'ajax_send_email'));
    }
    
    /**
     * Handle email send via form submission
     */
    public function handle_email_send() {
        if (!isset($_POST['wp_social_mail_sender_send_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['wp_social_mail_sender_send_nonce'], 'wp_social_mail_sender_send')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (!isset($_POST['email_recipient']) || !isset($_POST['email_subject']) || !isset($_POST['email_body'])) {
            return;
        }
        
        $recipient = sanitize_email($_POST['email_recipient']);
        $subject = sanitize_text_field($_POST['email_subject']);
        $body = wp_kses_post($_POST['email_body']);
        
        if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            add_settings_error(
                'wp_social_mail_sender_send',
                'invalid_email',
                'Invalid recipient email address',
                'error'
            );
            return;
        }
        
        if (empty($subject)) {
            add_settings_error(
                'wp_social_mail_sender_send',
                'no_subject',
                'Subject is required',
                'error'
            );
            return;
        }
        
        if (empty($body)) {
            add_settings_error(
                'wp_social_mail_sender_send',
                'no_body',
                'Message body is required',
                'error'
            );
            return;
        }
        
        // Send email
        $result = wp_mail($recipient, $subject, $body);
        
        if ($result) {
            add_settings_error(
                'wp_social_mail_sender_send',
                'success',
                'Email sent successfully to: ' . $recipient,
                'updated'
            );
            
            error_log('[WP Social Mail Sender] Email sent via form to: ' . $recipient);
        } else {
            add_settings_error(
                'wp_social_mail_sender_send',
                'send_failed',
                'Failed to send email. Check debug.log for details.',
                'error'
            );
            
            error_log('[WP Social Mail Sender] Failed to send email to: ' . $recipient);
        }
    }
    
    /**
     * AJAX handler for sending email
     */
    public function ajax_send_email() {
        check_ajax_referer('wp_social_mail_sender_send', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        if (!isset($_POST['recipient']) || !isset($_POST['subject']) || !isset($_POST['body'])) {
            wp_send_json_error(array('message' => 'Missing required fields'));
        }
        
        $recipient = sanitize_email($_POST['recipient']);
        $subject = sanitize_text_field($_POST['subject']);
        $body = wp_kses_post($_POST['body']);
        
        if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
        }
        
        if (empty($subject)) {
            wp_send_json_error(array('message' => 'Subject is required'));
        }
        
        if (empty($body)) {
            wp_send_json_error(array('message' => 'Message body is required'));
        }
        
        // Send email
        $result = wp_mail($recipient, $subject, $body);
        
        if ($result) {
            error_log('[WP Social Mail Sender] Email sent via AJAX to: ' . $recipient);
            wp_send_json_success(array(
                'message' => 'Email sent successfully to: ' . $recipient
            ));
        } else {
            error_log('[WP Social Mail Sender] Failed to send email via AJAX to: ' . $recipient);
            wp_send_json_error(array(
                'message' => 'Failed to send email. Check debug.log for details.'
            ));
        }
    }
    
    /**
     * Render send email form
     */
    public function render_form() {
        ?>
        <div class="wp-social-mail-sender-card">
            <h2>Send Email</h2>
            
            <form method="post" id="wp-social-mail-sender-form">
                <?php wp_nonce_field('wp_social_mail_sender_send', 'wp_social_mail_sender_send_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="email_recipient">Recipient Email *</label>
                        </th>
                        <td>
                            <input 
                                type="email" 
                                id="email_recipient" 
                                name="email_recipient" 
                                class="regular-text"
                                placeholder="recipient@example.com"
                                required
                            >
                            <p class="description">Email address of the recipient</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="email_subject">Subject *</label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                id="email_subject" 
                                name="email_subject" 
                                class="regular-text"
                                placeholder="Email subject"
                                required
                            >
                            <p class="description">Subject line of the email</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="email_body">Message Body *</label>
                        </th>
                        <td>
                            <?php
                            wp_editor(
                                '',
                                'email_body',
                                array(
                                    'media_buttons' => false,
                                    'textarea_rows' => 10,
                                    'teeny' => true
                                )
                            );
                            ?>
                            <p class="description">Email body (HTML allowed)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Send Email', 'primary', 'submit', true); ?>
            </form>
        </div>
        <?php
    }
}
