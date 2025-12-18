<?php
/**
 * Templates management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Mail_Sender_Templates {
    
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
        
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_action('admin_post_wp_mail_sender_save_template', array($this, 'handle_save_template'));
        add_action('admin_post_wp_mail_sender_delete_template', array($this, 'handle_delete_template'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wp-mail-sender',
            'Templates',
            'Templates',
            'manage_options',
            'wp-mail-sender-templates',
            array($this, 'render_templates_page')
        );
    }
    
    /**
     * Render templates page
     */
    public function render_templates_page() {
        // Check DB connection first
        if (!$this->db->is_mailing_connected()) {
            ?>
            <div class="wrap">
                <h1>Templates d'emails</h1>
                <div class="notice notice-error">
                    <p><strong>Erreur de connexion à la base de données :</strong> <?php echo esc_html($this->db->get_connection_error()); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=wp-mail-sender-settings'); ?>" class="button">Configurer les mots de passe</a></p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Check if editing
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $template = $edit_id ? $this->db->get_template($edit_id) : null;
        
        // Get all templates
        $templates = $this->db->get_templates();
        
        ?>
        <div class="wrap">
            <h1>
                <?php echo $edit_id ? 'Modifier le template' : 'Templates d\'emails'; ?>
                <?php if (!$edit_id): ?>
                    <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-templates&action=new'); ?>" class="page-title-action">Ajouter un template</a>
                <?php endif; ?>
            </h1>
            
            <?php settings_errors('wp_mail_sender_templates'); ?>
            
            <?php if (isset($_GET['action']) && $_GET['action'] === 'new' || $edit_id): ?>
                <!-- Template Editor -->
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('wp_mail_sender_template', 'wp_mail_sender_template_nonce'); ?>
                    <input type="hidden" name="action" value="wp_mail_sender_save_template">
                    <?php if ($edit_id): ?>
                        <input type="hidden" name="template_id" value="<?php echo esc_attr($edit_id); ?>">
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="template_name">Nom du template *</label></th>
                            <td>
                                <input type="text" 
                                       id="template_name" 
                                       name="template_name" 
                                       value="<?php echo $template ? esc_attr($template->name) : ''; ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description">Nom interne pour identifier le template</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="template_subject">Sujet *</label></th>
                            <td>
                                <input type="text" 
                                       id="template_subject" 
                                       name="template_subject" 
                                       value="<?php echo $template ? esc_attr($template->subject) : ''; ?>" 
                                       class="large-text" 
                                       required>
                                <p class="description">Sujet de l'email (peut contenir des variables)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="template_body">Corps de l'email *</label></th>
                            <td>
                                <?php
                                $content = $template ? $template->body : '';
                                wp_editor($content, 'template_body', array(
                                    'textarea_name' => 'template_body',
                                    'textarea_rows' => 15,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'tinymce' => true
                                ));
                                ?>
                                <p class="description">Contenu HTML de l'email</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="template-variables">
                        <h3>Variables disponibles</h3>
                        <p>Utilisez ces variables dans le sujet ou le corps de l'email :</p>
                        <code>{{first_name}}</code> - Prénom du destinataire<br>
                        <code>{{last_name}}</code> - Nom du destinataire<br>
                        <code>{{email}}</code> - Email du destinataire<br>
                        <code>{{display_name}}</code> - Nom affiché<br>
                        <code>{{site_name}}</code> - Nom du site<br>
                        <code>{{site_url}}</code> - URL du site
                    </div>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="💾 Enregistrer le template">
                        <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-templates'); ?>" class="button">Annuler</a>
                    </p>
                </form>
                
            <?php else: ?>
                <!-- Templates List -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Sujet</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                            <tr>
                                <td colspan="4">Aucun template trouvé. <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-templates&action=new'); ?>">Créer le premier template</a></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($templates as $tpl): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($tpl->name); ?></strong></td>
                                    <td><?php echo esc_html($tpl->subject); ?></td>
                                    <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($tpl->created_at))); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-templates&edit=' . $tpl->id); ?>" class="button button-small">✏️ Modifier</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wp_mail_sender_delete_template&id=' . $tpl->id), 'delete_template_' . $tpl->id); ?>" 
                                           class="button button-small" 
                                           onclick="return confirm('Supprimer ce template ?')">🗑️ Supprimer</a>
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
     * Handle template save
     */
    public function handle_save_template() {
        error_log('[WP Mail Sender Templates] handle_save_template called');
        
        if (!current_user_can('manage_options')) {
            error_log('[WP Mail Sender Templates ERROR] Permission denied');
            wp_die('Permission denied');
        }
        
        check_admin_referer('wp_mail_sender_template', 'wp_mail_sender_template_nonce');
        
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        
        $data = array(
            'name' => sanitize_text_field($_POST['template_name']),
            'subject' => sanitize_text_field($_POST['template_subject']),
            'body' => wp_kses_post($_POST['template_body'])
        );
        
        if ($template_id) {
            $data['id'] = $template_id;
        }
        
        error_log('[WP Mail Sender Templates] Attempting to save template: ' . print_r($data, true));
        error_log('[WP Mail Sender Templates] DB connected: ' . ($this->db->is_mailing_connected() ? 'YES' : 'NO'));
        
        $result = $this->db->save_template($data);
        
        error_log('[WP Mail Sender Templates] save_template result: ' . var_export($result, true));
        
        if ($result) {
            add_settings_error('wp_mail_sender_templates', 'template_saved', '✅ Template enregistré avec succès.', 'success');
            $redirect = add_query_arg(
                array('page' => 'wp-mail-sender-templates', 'saved' => '1'),
                admin_url('admin.php')
            );
            error_log('[WP Mail Sender Templates INFO] [' . current_time('mysql') . '] Template saved: ' . $data['name']);
        } else {
            add_settings_error('wp_mail_sender_templates', 'template_error', '❌ Erreur lors de l\'enregistrement du template.', 'error');
            $redirect = add_query_arg(
                array('page' => 'wp-mail-sender-templates', 'error' => '1'),
                admin_url('admin.php')
            );
            error_log('[WP Mail Sender Templates ERROR] [' . current_time('mysql') . '] Failed to save template');
        }
        
        wp_redirect($redirect);
        exit;
    }
    
    /**
     * Handle template delete
     */
    public function handle_delete_template() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        check_admin_referer('delete_template_' . $template_id);
        
        $result = $this->db->delete_template($template_id);
        
        if ($result) {
            add_settings_error('wp_mail_sender_templates', 'template_deleted', '✅ Template supprimé avec succès.', 'success');
        } else {
            add_settings_error('wp_mail_sender_templates', 'template_delete_error', '❌ Erreur lors de la suppression du template.', 'error');
        }
        
        $redirect = add_query_arg(
            array(
                'page' => 'wp-mail-sender-templates',
                'deleted' => $result ? '1' : '0'
            ),
            admin_url('admin.php')
        );
        
        error_log('[WP Mail Sender Templates INFO] [' . current_time('mysql') . '] Template deleted: ID ' . $template_id);
        
        wp_redirect($redirect);
        exit;
    }
}
