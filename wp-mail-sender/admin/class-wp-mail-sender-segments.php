<?php
/**
 * Advanced segments management class
 * Permet de créer des segments personnalisés par ville, catégorie de produits, périodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Mail_Sender_Segments {
    
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
        
        add_action('admin_menu', array($this, 'add_admin_menu'), 35);
        add_action('admin_post_wp_mail_sender_save_segment', array($this, 'handle_save_segment'));
        add_action('admin_post_wp_mail_sender_delete_segment', array($this, 'handle_delete_segment'));
        add_action('wp_ajax_wp_mail_sender_preview_segment', array($this, 'ajax_preview_segment'));
        add_action('wp_ajax_wp_mail_sender_get_cities', array($this, 'ajax_get_cities'));
        add_action('wp_ajax_wp_mail_sender_get_categories', array($this, 'ajax_get_categories'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wp-mail-sender',
            'Segments personnalisés',
            'Segments',
            'manage_options',
            'wp-mail-sender-segments',
            array($this, 'render_segments_page')
        );
    }
    
    /**
     * Render segments page
     */
    public function render_segments_page() {
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $segment = $edit_id ? $this->db->get_segment($edit_id) : null;
        
        $segments = $this->db->get_segments();
        
        ?>
        <div class="wrap">
            <h1>
                <?php echo $edit_id ? 'Modifier le segment' : 'Segments personnalisés'; ?>
                <?php if (!$edit_id): ?>
                    <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-segments&action=new'); ?>" class="page-title-action">Créer un segment</a>
                <?php endif; ?>
            </h1>
            
            <?php settings_errors('wp_mail_sender_segments'); ?>
            
            <?php if (isset($_GET['action']) && $_GET['action'] === 'new' || $edit_id): ?>
                <!-- Segment Editor -->
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="segment-form">
                    <?php wp_nonce_field('wp_mail_sender_segment', 'wp_mail_sender_segment_nonce'); ?>
                    <input type="hidden" name="action" value="wp_mail_sender_save_segment">
                    <?php if ($edit_id): ?>
                        <input type="hidden" name="segment_id" value="<?php echo esc_attr($edit_id); ?>">
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="segment_name">Nom du segment *</label></th>
                            <td>
                                <input type="text" 
                                       id="segment_name" 
                                       name="segment_name" 
                                       value="<?php echo $segment ? esc_attr($segment->name) : ''; ?>" 
                                       class="regular-text" 
                                       required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="segment_description">Description</label></th>
                            <td>
                                <textarea id="segment_description" 
                                          name="segment_description" 
                                          rows="3" 
                                          class="large-text"><?php echo $segment ? esc_textarea($segment->description) : ''; ?></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>🎯 Filtres de segmentation</h3>
                    <p class="description">Tous les filtres sont cumulatifs (ET logique). Laissez vide pour ne pas filtrer.</p>
                    
                    <table class="form-table">
                        <!-- Filtre par période -->
                        <tr>
                            <th scope="row" colspan="2" style="background: #f0f0f1; padding: 10px;">
                                <strong>📅 Période de commande</strong>
                            </th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="date_from">Date de début</label></th>
                            <td>
                                <input type="date" 
                                       id="date_from" 
                                       name="date_from" 
                                       value="<?php echo $segment && $segment->filters ? esc_attr(json_decode($segment->filters, true)['date_from'] ?? '') : ''; ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="date_to">Date de fin</label></th>
                            <td>
                                <input type="date" 
                                       id="date_to" 
                                       name="date_to" 
                                       value="<?php echo $segment && $segment->filters ? esc_attr(json_decode($segment->filters, true)['date_to'] ?? '') : ''; ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="new_customers">Nouveaux clients (première commande dans la période)</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="new_customers" name="new_customers" value="1" <?php echo ($segment && $segment->filters && !empty(json_decode($segment->filters, true)['new_customers'])) ? 'checked' : ''; ?>>
                                    Activer
                                </label>
                                <p class="description">Basé sur la première commande (legacy postmeta). Nécessite une période.</p>
                            </td>
                        </tr>
                        
                        <!-- Filtre par ville -->
                        <tr>
                            <th scope="row" colspan="2" style="background: #f0f0f1; padding: 10px;">
                                <strong>🏙️ Localisation (Ville)</strong>
                            </th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="city_type">Type d'adresse</label></th>
                            <td>
                                <select id="city_type" name="city_type">
                                    <option value="">-- Ne pas filtrer --</option>
                                    <option value="billing" <?php selected($segment && $segment->filters && (json_decode($segment->filters, true)['city_type'] ?? '') === 'billing'); ?>>Adresse de facturation</option>
                                    <option value="shipping" <?php selected($segment && $segment->filters && (json_decode($segment->filters, true)['city_type'] ?? '') === 'shipping'); ?>>Adresse de livraison</option>
                                </select>
                            </td>
                        </tr>
                        <tr id="city-filter-row" style="<?php echo ($segment && $segment->filters && !empty(json_decode($segment->filters, true)['city_type'])) ? '' : 'display:none;'; ?>">
                            <th scope="row"><label for="cities">Villes (une par ligne)</label></th>
                            <td>
                                <textarea id="cities" 
                                          name="cities" 
                                          rows="5" 
                                          class="large-text" 
                                          placeholder="Paris&#10;Lyon&#10;Marseille"><?php echo $segment && $segment->filters ? esc_textarea(json_decode($segment->filters, true)['cities'] ?? '') : ''; ?></textarea>
                                <p class="description">
                                    Entrez une ville par ligne. Exemples: Paris, Lyon, Marseille<br>
                                    <button type="button" id="load-cities" class="button button-small">📥 Charger les villes disponibles</button>
                                </p>
                                <div id="cities-list" style="display:none; margin-top: 10px; max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;"></div>
                            </td>
                        </tr>
                        
                        <!-- Filtre par code postal -->
                        <tr>
                            <th scope="row"><label for="postcode_type">Code postal</label></th>
                            <td>
                                <select id="postcode_type" name="postcode_type">
                                    <option value="">-- Ne pas filtrer --</option>
                                    <option value="billing" <?php selected($segment && $segment->filters && (json_decode($segment->filters, true)['postcode_type'] ?? '') === 'billing'); ?>>Facturation</option>
                                    <option value="shipping" <?php selected($segment && $segment->filters && (json_decode($segment->filters, true)['postcode_type'] ?? '') === 'shipping'); ?>>Livraison</option>
                                </select>
                            </td>
                        </tr>
                        <tr id="postcode-filter-row" style="<?php echo ($segment && $segment->filters && !empty(json_decode($segment->filters, true)['postcode_type'])) ? '' : 'display:none;'; ?>">
                            <th scope="row"><label for="postcodes">Codes postaux</label></th>
                            <td>
                                <textarea id="postcodes" 
                                          name="postcodes" 
                                          rows="3" 
                                          class="large-text" 
                                          placeholder="75000&#10;69000&#10;13000"><?php echo $segment && $segment->filters ? esc_textarea(json_decode($segment->filters, true)['postcodes'] ?? '') : ''; ?></textarea>
                                <p class="description">Un code postal par ligne, ou préfixes (ex: 75 pour Paris)</p>
                            </td>
                        </tr>
                        
                        <!-- Filtre par catégorie de produits -->
                        <tr>
                            <th scope="row" colspan="2" style="background: #f0f0f1; padding: 10px;">
                                <strong>🛍️ Produits / Catégories</strong>
                            </th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="product_filter_type">Filtre produits</label></th>
                            <td>
                                <select id="product_filter_type" name="product_filter_type">
                                    <option value="">-- Ne pas filtrer --</option>
                                    <option value="category" <?php selected($segment && $segment->filters && (json_decode($segment->filters, true)['product_filter_type'] ?? '') === 'category'); ?>>Par catégorie</option>
                                    <option value="product" <?php selected($segment && $segment->filters && (json_decode($segment->filters, true)['product_filter_type'] ?? '') === 'product'); ?>>Par produit spécifique</option>
                                    <option value="any" <?php selected($segment && $segment->filters && (json_decode($segment->filters, true)['product_filter_type'] ?? '') === 'any'); ?>>Clients ayant acheté au moins un produit</option>
                                </select>
                            </td>
                        </tr>
                        <tr id="category-filter-row" style="<?php echo ($segment && $segment->filters && (json_decode($segment->filters, true)['product_filter_type'] ?? '') === 'category') ? '' : 'display:none;'; ?>">
                            <th scope="row"><label for="categories">Catégories</label></th>
                            <td>
                                <textarea id="categories" 
                                          name="categories" 
                                          rows="4" 
                                          class="large-text"
                                          placeholder="ID ou slug de catégorie (un par ligne)"><?php echo $segment && $segment->filters ? esc_textarea(json_decode($segment->filters, true)['categories'] ?? '') : ''; ?></textarea>
                                <p class="description">
                                    ID ou slug de catégorie WooCommerce (un par ligne)<br>
                                    <button type="button" id="load-categories" class="button button-small">📥 Charger les catégories disponibles</button>
                                </p>
                                <div id="categories-list" style="display:none; margin-top: 10px; max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;"></div>
                            </td>
                        </tr>
                        <tr id="product-filter-row" style="<?php echo ($segment && $segment->filters && (json_decode($segment->filters, true)['product_filter_type'] ?? '') === 'product') ? '' : 'display:none;'; ?>">
                            <th scope="row"><label for="products">IDs de produits</label></th>
                            <td>
                                <textarea id="products" 
                                          name="products" 
                                          rows="3" 
                                          class="large-text"
                                          placeholder="123&#10;456&#10;789"><?php echo $segment && $segment->filters ? esc_textarea(json_decode($segment->filters, true)['products'] ?? '') : ''; ?></textarea>
                                <p class="description">ID de produit WooCommerce (un par ligne)</p>
                            </td>
                        </tr>
                        
                        <!-- Filtre par montant de commande -->
                        <tr>
                            <th scope="row" colspan="2" style="background: #f0f0f1; padding: 10px;">
                                <strong>💰 Montant des commandes</strong>
                            </th>
                        </tr>
                        <tr>
                            <th scope="row"><label for="amount_min">Montant minimum (€)</label></th>
                            <td>
                                <input type="number" 
                                       id="amount_min" 
                                       name="amount_min" 
                                       step="0.01"
                                       value="<?php echo $segment && $segment->filters ? esc_attr(json_decode($segment->filters, true)['amount_min'] ?? '') : ''; ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="amount_max">Montant maximum (€)</label></th>
                            <td>
                                <input type="number" 
                                       id="amount_max" 
                                       name="amount_max" 
                                       step="0.01"
                                       value="<?php echo $segment && $segment->filters ? esc_attr(json_decode($segment->filters, true)['amount_max'] ?? '') : ''; ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="💾 Enregistrer le segment">
                        <button type="button" id="preview-segment" class="button button-secondary">👁️ Prévisualiser le segment</button>
                        <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-segments'); ?>" class="button">Annuler</a>
                    </p>
                </form>
                
                <div id="segment-preview" style="margin-top: 30px; display: none;">
                    <h3>Aperçu du segment</h3>
                    <div id="preview-content"></div>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Show/hide city filter
                    $('#city_type').on('change', function() {
                        if ($(this).val()) {
                            $('#city-filter-row').show();
                        } else {
                            $('#city-filter-row').hide();
                        }
                    });
                    
                    // Show/hide postcode filter
                    $('#postcode_type').on('change', function() {
                        if ($(this).val()) {
                            $('#postcode-filter-row').show();
                        } else {
                            $('#postcode-filter-row').hide();
                        }
                    });
                    
                    // Show/hide product filters
                    $('#product_filter_type').on('change', function() {
                        $('#category-filter-row, #product-filter-row').hide();
                        if ($(this).val() === 'category') {
                            $('#category-filter-row').show();
                        } else if ($(this).val() === 'product') {
                            $('#product-filter-row').show();
                        }
                    });
                    
                    // Load cities
                    $('#load-cities').on('click', function() {
                        var type = $('#city_type').val();
                        if (!type) {
                            alert('Sélectionnez d\'abord le type d\'adresse');
                            return;
                        }
                        
                        $('#cities-list').html('<p>Chargement...</p>').show();
                        
                        $.post(ajaxurl, {
                            action: 'wp_mail_sender_get_cities',
                            nonce: '<?php echo wp_create_nonce('wp_mail_sender_cities'); ?>',
                            type: type
                        }, function(response) {
                            if (response.success) {
                                var html = '<strong>Villes disponibles (cliquez pour ajouter) :</strong><br><br>';
                                response.data.forEach(function(city) {
                                    html += '<a href="#" class="button button-small" style="margin: 2px;" data-city="' + city + '">' + city + '</a> ';
                                });
                                $('#cities-list').html(html);
                                
                                // Add city on click
                                $('#cities-list a').on('click', function(e) {
                                    e.preventDefault();
                                    var city = $(this).data('city');
                                    var current = $('#cities').val();
                                    if (current && !current.endsWith('\n')) {
                                        current += '\n';
                                    }
                                    $('#cities').val(current + city);
                                });
                            } else {
                                $('#cities-list').html('<p style="color: red;">Erreur: ' + response.data + '</p>');
                            }
                        });
                    });
                    
                    // Load categories
                    $('#load-categories').on('click', function() {
                        $('#categories-list').html('<p>Chargement...</p>').show();
                        
                        $.post(ajaxurl, {
                            action: 'wp_mail_sender_get_categories',
                            nonce: '<?php echo wp_create_nonce('wp_mail_sender_categories'); ?>'
                        }, function(response) {
                            if (response.success) {
                                var html = '<strong>Catégories disponibles (cliquez pour ajouter) :</strong><br><br>';
                                response.data.forEach(function(cat) {
                                    html += '<a href="#" class="button button-small" style="margin: 2px;" data-cat="' + cat.slug + '" title="ID: ' + cat.id + '">' + cat.name + ' (' + cat.count + ')</a> ';
                                });
                                $('#categories-list').html(html);
                                
                                // Add category on click
                                $('#categories-list a').on('click', function(e) {
                                    e.preventDefault();
                                    var cat = $(this).data('cat');
                                    var current = $('#categories').val();
                                    if (current && !current.endsWith('\n')) {
                                        current += '\n';
                                    }
                                    $('#categories').val(current + cat);
                                });
                            } else {
                                $('#categories-list').html('<p style="color: red;">Erreur: ' + response.data + '</p>');
                            }
                        });
                    });
                    
                    // Preview segment
                    $('#preview-segment').on('click', function() {
                        var data = {
                            action: 'wp_mail_sender_preview_segment',
                            nonce: '<?php echo wp_create_nonce('wp_mail_sender_preview_segment'); ?>',
                            date_from: $('#date_from').val(),
                            date_to: $('#date_to').val(),
                            new_customers: $('#new_customers').is(':checked') ? 1 : 0,
                            city_type: $('#city_type').val(),
                            cities: $('#cities').val(),
                            postcode_type: $('#postcode_type').val(),
                            postcodes: $('#postcodes').val(),
                            product_filter_type: $('#product_filter_type').val(),
                            categories: $('#categories').val(),
                            products: $('#products').val(),
                            amount_min: $('#amount_min').val(),
                            amount_max: $('#amount_max').val()
                        };
                        
                        $('#preview-content').html('<p>Chargement...</p>');
                        $('#segment-preview').show();
                        
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
                <!-- Segments List -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Filtres actifs</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($segments)): ?>
                            <tr>
                                <td colspan="5">Aucun segment trouvé. <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-segments&action=new'); ?>">Créer le premier segment</a></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($segments as $seg): ?>
                                <?php 
                                $filters = json_decode($seg->filters, true) ?: array();
                                $active_filters = array();
                                if (!empty($filters['date_from']) || !empty($filters['date_to'])) $active_filters[] = 'Période';
                                if (!empty($filters['new_customers'])) $active_filters[] = 'Nouveaux clients';
                                if (!empty($filters['city_type'])) $active_filters[] = 'Ville';
                                if (!empty($filters['postcode_type'])) $active_filters[] = 'Code postal';
                                if (!empty($filters['product_filter_type'])) $active_filters[] = 'Produits';
                                if (!empty($filters['amount_min']) || !empty($filters['amount_max'])) $active_filters[] = 'Montant';
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($seg->name); ?></strong></td>
                                    <td><?php echo esc_html($seg->description); ?></td>
                                    <td><?php echo esc_html(implode(', ', $active_filters) ?: 'Aucun'); ?></td>
                                    <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($seg->created_at))); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=wp-mail-sender-segments&edit=' . $seg->id); ?>" class="button button-small">✏️ Modifier</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wp_mail_sender_delete_segment&id=' . $seg->id), 'delete_segment_' . $seg->id); ?>" 
                                           class="button button-small" 
                                           onclick="return confirm('Supprimer ce segment ?')">🗑️ Supprimer</a>
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
     * Handle segment save
     */
    public function handle_save_segment() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        check_admin_referer('wp_mail_sender_segment', 'wp_mail_sender_segment_nonce');
        
        $segment_id = isset($_POST['segment_id']) ? intval($_POST['segment_id']) : 0;
        
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'new_customers' => !empty($_POST['new_customers']) ? 1 : 0,
            'city_type' => sanitize_text_field($_POST['city_type'] ?? ''),
            'cities' => sanitize_textarea_field($_POST['cities'] ?? ''),
            'postcode_type' => sanitize_text_field($_POST['postcode_type'] ?? ''),
            'postcodes' => sanitize_textarea_field($_POST['postcodes'] ?? ''),
            'product_filter_type' => sanitize_text_field($_POST['product_filter_type'] ?? ''),
            'categories' => sanitize_textarea_field($_POST['categories'] ?? ''),
            'products' => sanitize_textarea_field($_POST['products'] ?? ''),
            'amount_min' => sanitize_text_field($_POST['amount_min'] ?? ''),
            'amount_max' => sanitize_text_field($_POST['amount_max'] ?? '')
        );
        
        $data = array(
            'name' => sanitize_text_field($_POST['segment_name']),
            'description' => sanitize_textarea_field($_POST['segment_description'] ?? ''),
            'filters' => json_encode($filters)
        );
        
        if ($segment_id) {
            $data['id'] = $segment_id;
        }
        
        $result = $this->db->save_segment($data);
        
        if ($result) {
            add_settings_error('wp_mail_sender_segments', 'segment_saved', '✅ Segment enregistré avec succès.', 'success');
            $redirect = add_query_arg(
                array('page' => 'wp-mail-sender-segments', 'saved' => '1'),
                admin_url('admin.php')
            );
            error_log('[WP Mail Sender Segments INFO] [' . current_time('mysql') . '] Segment saved: ' . $data['name']);
        } else {
            add_settings_error('wp_mail_sender_segments', 'segment_error', '❌ Erreur lors de l\'enregistrement du segment.', 'error');
            $redirect = add_query_arg(
                array('page' => 'wp-mail-sender-segments', 'error' => '1'),
                admin_url('admin.php')
            );
            error_log('[WP Mail Sender Segments ERROR] [' . current_time('mysql') . '] Failed to save segment');
        }
        
        wp_redirect($redirect);
        exit;
    }
    
    /**
     * Handle segment delete
     */
    public function handle_delete_segment() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $segment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        check_admin_referer('delete_segment_' . $segment_id);
        
        $result = $this->db->delete_segment($segment_id);
        
        if ($result) {
            add_settings_error('wp_mail_sender_segments', 'segment_deleted', '✅ Segment supprimé avec succès.', 'success');
        } else {
            add_settings_error('wp_mail_sender_segments', 'segment_delete_error', '❌ Erreur lors de la suppression du segment.', 'error');
        }
        
        $redirect = add_query_arg(
            array(
                'page' => 'wp-mail-sender-segments',
                'deleted' => $result ? '1' : '0'
            ),
            admin_url('admin.php')
        );
        
        error_log('[WP Mail Sender Segments INFO] [' . current_time('mysql') . '] Segment deleted: ID ' . $segment_id);
        
        wp_redirect($redirect);
        exit;
    }
    
    /**
     * AJAX preview segment
     */
    public function ajax_preview_segment() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        check_ajax_referer('wp_mail_sender_preview_segment', 'nonce');
        
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'new_customers' => !empty($_POST['new_customers']) ? 1 : 0,
            'city_type' => sanitize_text_field($_POST['city_type'] ?? ''),
            'cities' => sanitize_textarea_field($_POST['cities'] ?? ''),
            'postcode_type' => sanitize_text_field($_POST['postcode_type'] ?? ''),
            'postcodes' => sanitize_textarea_field($_POST['postcodes'] ?? ''),
            'product_filter_type' => sanitize_text_field($_POST['product_filter_type'] ?? ''),
            'categories' => sanitize_textarea_field($_POST['categories'] ?? ''),
            'products' => sanitize_textarea_field($_POST['products'] ?? ''),
            'amount_min' => sanitize_text_field($_POST['amount_min'] ?? ''),
            'amount_max' => sanitize_text_field($_POST['amount_max'] ?? '')
        );
        
        error_log('[WP Mail Sender Segments] Preview called with filters: ' . print_r($filters, true));
        
        // Check if all filters are empty - if so, show a simple test query
        $has_filters = false;
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $has_filters = true;
                break;
            }
        }
        
        if (!$has_filters) {
            // No filters - return a simple test to verify database access
            global $wpdb;
            $source_db = WP_MAIL_SENDER_SOURCE_DB;
            $prefix = WP_MAIL_SENDER_SOURCE_PREFIX;
            
            $test_sql = "SELECT COUNT(*) as total 
                        FROM `{$source_db}`.`{$prefix}posts` 
                        WHERE post_type = 'shop_order' 
                        LIMIT 1";
            
            $test_count = $wpdb->get_var($test_sql);
            error_log('[WP Mail Sender Segments] Test query found: ' . $test_count . ' orders');
            
            if ($wpdb->last_error) {
                error_log('[WP Mail Sender Segments ERROR] Test query failed: ' . $wpdb->last_error);
                wp_send_json_error('Erreur de connexion à la base de données: ' . $wpdb->last_error);
                return;
            }
            
            wp_send_json_error('Veuillez sélectionner au moins un filtre. (' . $test_count . ' commandes disponibles dans la base)');
            return;
        }
        
        if (!empty($filters['new_customers']) && !empty($filters['date_from']) && !empty($filters['date_to'])) {
            $recipients = $this->db->get_new_customers_legacy($filters['date_from'], $filters['date_to']);
        } else {
            $recipients = $this->db->get_segment_recipients($filters);
        }
        
        $html = '<table class="wp-list-table widefat"><thead><tr><th>Email</th><th>Nom</th><th>Ville</th></tr></thead><tbody>';
        
        $count = 0;
        foreach ($recipients as $recipient) {
            if ($count >= 15) {
                $html .= '<tr><td colspan="3"><em>... et ' . (count($recipients) - 15) . ' autres</em></td></tr>';
                break;
            }
            $email = $recipient->billing_email ?? $recipient->user_email ?? ($recipient->email ?? '');
            $name = trim(($recipient->billing_first_name ?? $recipient->first_name ?? ($recipient->name ?? '')) . ' ' . ($recipient->billing_last_name ?? $recipient->last_name ?? ''));
            $city = $recipient->billing_city ?? $recipient->shipping_city ?? ($recipient->city ?? '');
            $html .= '<tr><td>' . esc_html($email) . '</td><td>' . esc_html($name) . '</td><td>' . esc_html($city) . '</td></tr>';
            $count++;
        }
        
        $html .= '</tbody></table>';
        
        wp_send_json_success(array(
            'count' => count($recipients),
            'html' => $html
        ));
    }
    
    /**
     * AJAX get cities
     */
    public function ajax_get_cities() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        check_ajax_referer('wp_mail_sender_cities', 'nonce');
        
        $type = sanitize_text_field($_POST['type'] ?? 'billing');
        $cities = $this->db->get_available_cities($type);
        
        wp_send_json_success($cities);
    }
    
    /**
     * AJAX get categories
     */
    public function ajax_get_categories() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        check_ajax_referer('wp_mail_sender_categories', 'nonce');
        
        $categories = $this->db->get_product_categories();
        
        wp_send_json_success($categories);
    }
}
