<?php
/**
 * Send campaign class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Mail_Sender_Send {
    
    private static $instance = null;
    private $db;
    private $core;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = WP_Mail_Sender_DB::get_instance();
        $this->core = WP_Mail_Sender_Core::get_instance();
        
        add_action('admin_menu', array($this, 'add_admin_menu'), 40);
        add_action('admin_post_wp_mail_sender_create_campaign', array($this, 'handle_create_campaign'));
        add_action('admin_post_wp_mail_sender_send_campaign', array($this, 'handle_send_campaign'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wp-mail-sender',
            'Envoyer',
            'Envoyer',
            'manage_options',
            'wp-mail-sender-send',
            array($this, 'render_send_page')
        );
    }
    
    /**
     * Render send page
     */
    public function render_send_page() {
        $templates = $this->db->get_templates();
        $lists = $this->db->get_lists();
        $segments = $this->db->get_segments();
        
        ?>
        <div class="wrap">
            <h1>📧 Envoyer une campagne</h1>
            
            <?php settings_errors('wp_mail_sender_send'); ?>
            
            <?php if (empty($templates)): ?>
                <div class="notice notice-warning">
                    <p>Vous devez d'abord <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-templates'); ?>">créer un template</a> avant d'envoyer une campagne.</p>
                </div>
            <?php elseif (empty($lists) && empty($segments)): ?>
                <div class="notice notice-warning">
                    <p>Vous devez d'abord créer une <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-lists'); ?>">liste</a> ou un <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-segments'); ?>">segment personnalisé</a> avant d'envoyer une campagne.</p>
                </div>
            <?php else: ?>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="campaign-form">
                    <?php wp_nonce_field('wp_mail_sender_campaign', 'wp_mail_sender_campaign_nonce'); ?>
                    <input type="hidden" name="action" value="wp_mail_sender_create_campaign">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="campaign_name">Nom de la campagne *</label></th>
                            <td>
                                <input type="text" 
                                       id="campaign_name" 
                                       name="campaign_name" 
                                       value="" 
                                       class="regular-text" 
                                       required>
                                <p class="description">Nom interne pour identifier cette campagne</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="template_id">Template *</label></th>
                            <td>
                                <select id="template_id" name="template_id" required>
                                    <option value="">-- Choisir un template --</option>
                                    <?php foreach ($templates as $tpl): ?>
                                        <option value="<?php echo esc_attr($tpl->id); ?>">
                                            <?php echo esc_html($tpl->name); ?> - <?php echo esc_html($tpl->subject); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-templates'); ?>" target="_blank">Gérer les templates</a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="list_type">Type de liste *</label></th>
                            <td>
                                <select id="list_type" name="list_type" required>
                                    <option value="">-- Choisir --</option>
                                    <option value="list">Liste simple</option>
                                    <option value="segment">Segment personnalisé</option>
                                </select>
                            </td>
                        </tr>
                        <tr id="list-select-row" style="display:none;">
                            <th scope="row"><label for="list_id">Liste de destinataires *</label></th>
                            <td>
                                <select id="list_id" name="list_id">
                                    <option value="">-- Choisir une liste --</option>
                                    <?php foreach ($lists as $list): ?>
                                        <option value="<?php echo esc_attr($list->id); ?>">
                                            <?php echo esc_html($list->name); ?> (<?php echo esc_html($list->query_type); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-lists'); ?>" target="_blank">Gérer les listes</a>
                                </p>
                            </td>
                        </tr>
                        <tr id="segment-select-row" style="display:none;">
                            <th scope="row"><label for="segment_id">Segment personnalisé *</label></th>
                            <td>
                                <select id="segment_id" name="segment_id">
                                    <option value="">-- Choisir un segment --</option>
                                    <?php foreach ($segments as $seg): ?>
                                        <option value="<?php echo esc_attr($seg->id); ?>">
                                            <?php echo esc_html($seg->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-segments'); ?>" target="_blank">Gérer les segments</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <script>
                    jQuery(document).ready(function($) {
                        $('#list_type').on('change', function() {
                            $('#list-select-row, #segment-select-row').hide();
                            $('#list_id, #segment_id').removeAttr('required');
                            
                            if ($(this).val() === 'list') {
                                $('#list-select-row').show();
                                $('#list_id').attr('required', 'required');
                            } else if ($(this).val() === 'segment') {
                                $('#segment-select-row').show();
                                $('#segment_id').attr('required', 'required');
                            }
                        });
                    });
                    </script>
                    
                    <div class="notice notice-info inline">
                        <p>
                            <strong>⚠️ Important :</strong> L'envoi démarre immédiatement après la création de la campagne. 
                            Vérifiez bien vos paramètres avant de cliquer sur "Créer et envoyer".
                        </p>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" 
                               class="button button-primary button-large" 
                               value="🚀 Créer et envoyer la campagne"
                               onclick="return confirm('Êtes-vous sûr de vouloir envoyer cette campagne maintenant ?');">
                    </p>
                </form>
                
            <?php endif; ?>
            
            <hr style="margin: 40px 0;">
            
            <h2>📊 Historique des campagnes</h2>
            <?php $this->render_campaigns_history(); ?>
        </div>
        <?php
    }
    
    /**
     * Render campaigns history
     */
    private function render_campaigns_history() {
        $mailing_db = WP_MAIL_SENDER_MAILING_DB;
        
        if (!$this->db->is_mailing_connected()) {
            echo '<p>Erreur de connexion à la base de données.</p>';
            return;
        }
        
        $wpdb = $this->db->get_mailing_wpdb();
        $prefix = WP_MAIL_SENDER_TABLE_PREFIX;
        $campaigns = $wpdb->get_results("
            SELECT c.*, t.name as template_name, l.name as list_name
            FROM `{$mailing_db}`.`{$prefix}campaigns` c
            LEFT JOIN `{$mailing_db}`.`{$prefix}templates` t ON c.template_id = t.id
            LEFT JOIN `{$mailing_db}`.`{$prefix}lists` l ON c.list_id = l.id
            ORDER BY c.created_at DESC
            LIMIT 20
        ");
        
        if (empty($campaigns)) {
            echo '<p>Aucune campagne envoyée pour le moment.</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Template</th>
                    <th>Liste</th>
                    <th>Statut</th>
                    <th>Total</th>
                    <th>Envoyés</th>
                    <th>Échoués</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                    <tr>
                        <td><strong><?php echo esc_html($campaign->name); ?></strong></td>
                        <td><?php echo esc_html($campaign->template_name); ?></td>
                        <td><?php echo esc_html($campaign->list_name); ?></td>
                        <td>
                            <?php
                            $status_colors = array(
                                'draft' => '#996800',
                                'sending' => '#d63638',
                                'sent' => '#00a32a',
                                'failed' => '#d63638'
                            );
                            $color = $status_colors[$campaign->status] ?? '#646970';
                            ?>
                            <span style="color: <?php echo $color; ?>; font-weight: 600;">
                                <?php echo esc_html(ucfirst($campaign->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($campaign->total_recipients); ?></td>
                        <td><?php echo esc_html($campaign->sent_count); ?></td>
                        <td><?php echo esc_html($campaign->failed_count); ?></td>
                        <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($campaign->created_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Handle campaign creation and sending
     */
    public function handle_create_campaign() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        check_admin_referer('wp_mail_sender_campaign', 'wp_mail_sender_campaign_nonce');
        
        $campaign_data = array(
            'name' => sanitize_text_field($_POST['campaign_name']),
            'template_id' => intval($_POST['template_id']),
            'list_id' => intval($_POST['list_id']),
            'status' => 'draft',
            'created_at' => current_time('mysql')
        );
        
        // Create campaign
        $result = $this->db->save_campaign($campaign_data);
        
        if (!$result) {
            $redirect = add_query_arg(
                array('page' => 'wp-mail-sender-send', 'error' => '1'),
                admin_url('admin.php')
            );
            error_log('[WP Mail Sender Send ERROR] [' . current_time('mysql') . '] Failed to create campaign');
            wp_redirect($redirect);
            exit;
        }
        
        // Get campaign ID
        $wpdb = $this->db->get_mailing_wpdb();
        $campaign_id = $wpdb->insert_id;
        
        error_log('[WP Mail Sender Send INFO] [' . current_time('mysql') . '] Campaign created: ID ' . $campaign_id);
        
        // Send campaign immediately
        $send_result = $this->core->send_campaign($campaign_id);
        
        if ($send_result) {
            $redirect = add_query_arg(
                array('page' => 'wp-mail-sender-send', 'sent' => '1'),
                admin_url('admin.php')
            );
        } else {
            $redirect = add_query_arg(
                array('page' => 'wp-mail-sender-send', 'send_error' => '1'),
                admin_url('admin.php')
            );
        }
        
        wp_redirect($redirect);
        exit;
    }
}
