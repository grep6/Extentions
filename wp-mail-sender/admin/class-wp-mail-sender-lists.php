<?php
/**
 * Mailing lists management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Mail_Sender_Lists {
    
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
        
        add_action('admin_menu', array($this, 'add_admin_menu'), 30);
        add_action('admin_post_wp_mail_sender_save_list', array($this, 'handle_save_list'));
        add_action('admin_post_wp_mail_sender_delete_list', array($this, 'handle_delete_list'));
        add_action('wp_ajax_wp_mail_sender_preview_list', array($this, 'ajax_preview_list'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wp-mail-sender',
            'Listes de diffusion',
            'Listes',
            'manage_options',
            'wp-mail-sender-lists',
            array($this, 'render_lists_page')
        );
    }
    
    /**
     * Render lists page
     */
    public function render_lists_page() {
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $list = $edit_id ? $this->db->get_list($edit_id) : null;
        
        $lists = $this->db->get_lists();
        
        ?>
        <div class="wrap">
            <h1>
                <?php echo $edit_id ? 'Modifier la liste' : 'Listes de diffusion'; ?>
                <?php if (!$edit_id): ?>
                    <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-lists&action=new'); ?>" class="page-title-action">Créer une liste</a>
                <?php endif; ?>
            </h1>
            
            <?php settings_errors('wp_mail_sender_lists'); ?>
            
            <?php if (isset($_GET['action']) && $_GET['action'] === 'new' || $edit_id): ?>
                <!-- List Editor -->
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="list-form">
                    <?php wp_nonce_field('wp_mail_sender_list', 'wp_mail_sender_list_nonce'); ?>
                    <input type="hidden" name="action" value="wp_mail_sender_save_list">
                    <?php if ($edit_id): ?>
                        <input type="hidden" name="list_id" value="<?php echo esc_attr($edit_id); ?>">
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="list_name">Nom de la liste *</label></th>
                            <td>
                                <input type="text" 
                                       id="list_name" 
                                       name="list_name" 
                                       value="<?php echo $list ? esc_attr($list->name) : ''; ?>" 
                                       class="regular-text" 
                                       required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="list_description">Description</label></th>
                            <td>
                                <textarea id="list_description" 
                                          name="list_description" 
                                          rows="3" 
                                          class="large-text"><?php echo $list ? esc_textarea($list->description) : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="query_type">Type de liste *</label></th>
                            <td>
                                <select id="query_type" name="query_type" required>
                                    <option value="">-- Choisir --</option>
                                    <option value="customers" <?php selected($list && $list->query_type, 'customers'); ?>>Tous les clients</option>
                                    <option value="orders" <?php selected($list && $list->query_type, 'orders'); ?>>Clients ayant commandé</option>
                                </select>
                                <p class="description">Source des destinataires</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div id="filters-section" style="<?php echo ($list && in_array($list->query_type, array('customers', 'orders'))) ? '' : 'display:none;'; ?>">
                        <h3>Filtres</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="date_from">Date de début</label></th>
                                <td>
                                    <input type="date" 
                                           id="date_from" 
                                           name="date_from" 
                                           value="<?php echo $list && $list->query_config ? esc_attr(json_decode($list->query_config, true)['date_from'] ?? '') : ''; ?>" 
                                           class="regular-text">
                                    <p class="description">Date d'inscription (clients) ou de commande (orders)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="date_to">Date de fin</label></th>
                                <td>
                                    <input type="date" 
                                           id="date_to" 
                                           name="date_to" 
                                           value="<?php echo $list && $list->query_config ? esc_attr(json_decode($list->query_config, true)['date_to'] ?? '') : ''; ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="💾 Enregistrer la liste">
                        <button type="button" id="preview-list" class="button button-secondary">👁️ Prévisualiser (compter les destinataires)</button>
                        <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-lists'); ?>" class="button">Annuler</a>
                    </p>
                </form>
                
                <div id="list-preview" style="margin-top: 30px; display: none;">
                    <h3>Aperçu de la liste</h3>
                    <div id="preview-content"></div>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    $('#query_type').on('change', function() {
                        if ($(this).val() === 'customers' || $(this).val() === 'orders') {
                            $('#filters-section').show();
                        } else {
                            $('#filters-section').hide();
                        }
                    });
                    
                    $('#preview-list').on('click', function() {
                        var data = {
                            action: 'wp_mail_sender_preview_list',
                            nonce: '<?php echo wp_create_nonce('wp_mail_sender_preview'); ?>',
                            query_type: $('#query_type').val(),
                            date_from: $('#date_from').val(),
                            date_to: $('#date_to').val()
                        };
                        
                        $('#preview-content').html('<p>Chargement...</p>');
                        $('#list-preview').show();
                        
                        $.post(ajaxurl, data, function(response) {
                            if (response.success) {
                                $('#preview-content').html('<p><strong>' + response.data.count + ' destinataires trouvés</strong></p>' + response.data.html);
                            } else {
                                $('#preview-content').html('<p style="color: red;">Erreur: ' + response.data + '</p>');
                            }
                        });
                    });
                });
                </script>
                
            <?php else: ?>
                <!-- Lists Table -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lists)): ?>
                            <tr>
                                <td colspan="5">Aucune liste trouvée. <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-lists&action=new'); ?>">Créer la première liste</a></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lists as $l): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($l->name); ?></strong></td>
                                    <td><?php echo esc_html(ucfirst($l->query_type)); ?></td>
                                    <td><?php echo esc_html($l->description); ?></td>
                                    <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($l->created_at))); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-lists&edit=' . $l->id); ?>" class="button button-small">✏️ Modifier</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wp_mail_sender_delete_list&id=' . $l->id), 'delete_list_' . $l->id); ?>" 
                                           class="button button-small" 
                                           onclick="return confirm('Supprimer cette liste ?')">🗑️ Supprimer</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle list save
     */
    public function handle_save_list() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        check_admin_referer('wp_mail_sender_list', 'wp_mail_sender_list_nonce');
        
        $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
        
        $query_config = json_encode(array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        ));
        
        $data = array(
            'name' => sanitize_text_field($_POST['list_name']),
            'description' => sanitize_textarea_field($_POST['list_description'] ?? ''),
            'query_type' => sanitize_text_field($_POST['query_type']),
            'query_config' => $query_config
        );
        
        if ($list_id) {
            $data['id'] = $list_id;
        }
        
        $result = $this->db->save_list($data);
        
        if ($result) {
            $redirect = add_query_arg(
                array('page' => 'wp-mail-sender-lists', 'saved' => '1'),
                admin_url('admin.php')
            );
            error_log('[WP Mail Sender Lists INFO] [' . current_time('mysql') . '] List saved: ' . $data['name']);
        } else {
            $redirect = add_query_arg(
                array('page' => 'wp-mail-sender-lists', 'error' => '1'),
                admin_url('admin.php')
            );
            error_log('[WP Mail Sender Lists ERROR] [' . current_time('mysql') . '] Failed to save list');
        }
        
        wp_redirect($redirect);
        exit;
    }
    
    /**
     * Handle list delete
     */
    public function handle_delete_list() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $list_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        check_admin_referer('delete_list_' . $list_id);
        
        $result = $this->db->delete_list($list_id);
        
        $redirect = add_query_arg(
            array(
                'page' => 'wp-mail-sender-lists',
                'deleted' => $result ? '1' : '0'
            ),
            admin_url('admin.php')
        );
        
        error_log('[WP Mail Sender Lists INFO] [' . current_time('mysql') . '] List deleted: ID ' . $list_id);
        
        wp_redirect($redirect);
        exit;
    }
    
    /**
     * AJAX preview list
     */
    public function ajax_preview_list() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        check_ajax_referer('wp_mail_sender_preview', 'nonce');
        
        $query_type = sanitize_text_field($_POST['query_type']);
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );
        
        $recipients = array();
        
        if ($query_type === 'customers') {
            $recipients = $this->db->get_wc_customers($filters);
        } elseif ($query_type === 'orders') {
            $recipients = $this->db->get_wc_orders($filters);
        }
        
        $html = '<table class="wp-list-table widefat"><thead><tr><th>Email</th><th>Nom</th></tr></thead><tbody>';
        
        $count = 0;
        foreach ($recipients as $recipient) {
            if ($count >= 10) {
                $html .= '<tr><td colspan="2"><em>... et ' . (count($recipients) - 10) . ' autres</em></td></tr>';
                break;
            }
            $email = $recipient->user_email ?? $recipient->billing_email ?? '';
            $name = ($recipient->first_name ?? $recipient->billing_first_name ?? '') . ' ' . ($recipient->last_name ?? $recipient->billing_last_name ?? '');
            $html .= '<tr><td>' . esc_html($email) . '</td><td>' . esc_html($name) . '</td></tr>';
            $count++;
        }
        
        $html .= '</tbody></table>';
        
        wp_send_json_success(array(
            'count' => count($recipients),
            'html' => $html
        ));
    }
}
