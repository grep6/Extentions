<?php
/**
 * Core email sending functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Mail_Sender_Core {
    
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
    }
    
    /**
     * Send email to single recipient
     */
    public function send_single_email($to, $subject, $body, $campaign_id = null) {
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Ensure sender identity is explicitly applied even if SMTP isn’t active
        if (class_exists('WP_Mail_Sender_SMTP')) {
            $smtp = WP_Mail_Sender_SMTP::get_instance();
            if (method_exists($smtp, 'get_sender_email') && method_exists($smtp, 'get_sender_name')) {
                $from_email = $smtp->get_sender_email();
                $from_name = $smtp->get_sender_name();
                if (!empty($from_email)) {
                    $headers[] = 'From: ' . (empty($from_name) ? $from_email : ($from_name . ' <' . $from_email . '>'));
                    // Prefer configured Reply-To if available
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
        
        $log_data = array(
            'campaign_id' => $campaign_id,
            'recipient_email' => $to,
            'subject' => $subject,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        try {
            $result = wp_mail($to, $subject, $body, $headers);
            
            if ($result) {
                $log_data['status'] = 'sent';
                $log_data['sent_at'] = current_time('mysql');
                error_log('[WP Mail Sender CORE] [' . current_time('mysql') . '] Email sent to: ' . $to);
            } else {
                $log_data['status'] = 'failed';
                $log_data['error_message'] = 'wp_mail returned false';
                error_log('[WP Mail Sender CORE ERROR] [' . current_time('mysql') . '] Failed to send email to: ' . $to);
            }
        } catch (Exception $e) {
            $log_data['status'] = 'failed';
            $log_data['error_message'] = $e->getMessage();
            error_log('[WP Mail Sender CORE ERROR] [' . current_time('mysql') . '] Exception: ' . $e->getMessage());
            $result = false;
        }
        
        $this->db->log_email($log_data);
        
        return $result;
    }
    
    /**
     * Send campaign emails
     */
    public function send_campaign($campaign_id, $batch_size = 50) {
        error_log('>>> SEND CAMPAIGN START - ID: ' . $campaign_id);
        
        // Use dedicated mailing DB connection
        $wpdb = $this->db->get_mailing_wpdb();
        
        // Get campaign details
        $mailing_db = WP_MAIL_SENDER_MAILING_DB;
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$mailing_db}`.`{$prefix}campaigns` WHERE id = %d",
            $campaign_id
        ));
        
        error_log('>>> Campaign found: ' . ($campaign ? 'YES' : 'NO'));
        if (!$campaign) {
            error_log('[WP Mail Sender CORE ERROR] Campaign not found: ' . $campaign_id);
            return false;
        }
        
        // Get template
        $template = $this->db->get_template($campaign->template_id);
        error_log('>>> Template found: ' . ($template ? 'YES' : 'NO'));
        if (!$template) {
            error_log('[WP Mail Sender CORE ERROR] Template not found: ' . $campaign->template_id);
            return false;
        }
        
        // Get list
        error_log('>>> Getting list ID: ' . $campaign->list_id);
        $list = $this->db->get_list($campaign->list_id);
        error_log('>>> List found: ' . ($list ? 'YES' : 'NO'));
        if (!$list) {
            error_log('[WP Mail Sender CORE ERROR] List not found: ' . $campaign->list_id);
            return false;
        }
        
        error_log('>>> List query_type: ' . $list->query_type);
        
        // Get recipients based on list configuration
        $recipients = $this->get_list_recipients($list);
        error_log('>>> Recipients count: ' . count($recipients));
        
        if (empty($recipients)) {
            error_log('[WP Mail Sender CORE ERROR] No recipients found for list: ' . $list->id);
            return false;
        }
        
        // Update campaign status
        $wpdb->update(
            "`{$mailing_db}`.`{$prefix}campaigns`",
            array('status' => 'sending', 'total_recipients' => count($recipients)),
            array('id' => $campaign_id)
        );
        
        $sent_count = 0;
        $failed_count = 0;
        
        // Send emails in batches
        foreach ($recipients as $recipient) {
            $email = $recipient->user_email ?? $recipient->billing_email ?? '';
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed_count++;
                continue;
            }
            
            // Replace variables in template
            $body = $this->replace_variables($template->body, $recipient);
            $subject = $this->replace_variables($template->subject, $recipient);
            
            if ($this->send_single_email($email, $subject, $body, $campaign_id)) {
                $sent_count++;
            } else {
                $failed_count++;
            }
            
            // Sleep to avoid overwhelming SMTP server
            usleep(100000); // 0.1 second
        }
        
        // Update campaign final status
        $wpdb->update(
            "`{$mailing_db}`.`{$prefix}campaigns`",
            array(
                'status' => 'sent',
                'sent_at' => current_time('mysql'),
                'sent_count' => $sent_count,
                'failed_count' => $failed_count
            ),
            array('id' => $campaign_id)
        );
        
        error_log('[WP Mail Sender CORE] [' . current_time('mysql') . '] Campaign completed. Sent: ' . $sent_count . ', Failed: ' . $failed_count);
        
        return true;
    }
    
    /**
     * Get recipients from list configuration
     */
    private function get_list_recipients($list) {
        error_log('>>> GET_LIST_RECIPIENTS - query_type: ' . $list->query_type);
        
        $config = json_decode($list->query_config, true);
        error_log('>>> Config: ' . print_r($config, true));
        
        switch ($list->query_type) {
            case 'customers':
                error_log('>>> Fetching customers...');
                $result = $this->db->get_wc_customers($config);
                error_log('>>> Customers found: ' . count($result));
                return $result;
                
            case 'orders':
                error_log('>>> Fetching orders...');
                $result = $this->db->get_wc_orders($config);
                error_log('>>> Orders found: ' . count($result));
                return $result;
                
            case 'custom':
                // Custom query implementation
                error_log('>>> Custom type not implemented');
                return array();
                
            default:
                error_log('>>> Unknown query_type: ' . $list->query_type);
                return array();
        }
    }
    
    /**
     * Replace variables in template
     */
    private function replace_variables($content, $recipient) {
        $variables = array(
            '{{first_name}}' => $recipient->first_name ?? $recipient->billing_first_name ?? '',
            '{{last_name}}' => $recipient->last_name ?? $recipient->billing_last_name ?? '',
            '{{email}}' => $recipient->user_email ?? $recipient->billing_email ?? '',
            '{{display_name}}' => $recipient->display_name ?? '',
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => get_site_url(),
        );
        
        return str_replace(array_keys($variables), array_values($variables), $content);
    }
}
