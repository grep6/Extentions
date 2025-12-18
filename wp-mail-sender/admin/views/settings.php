<?php
if (!defined('ABSPATH')) exit;

settings_errors('wp_mail_sender');

// Vérifier l'état des connexions
$db = WP_Mail_Sender_DB::get_instance();
$db_connected = $db->is_mailing_connected();
$db_error = $db->get_connection_error();

$smtp_password_set = !empty(get_option('wp_mail_sender_smtp_password', '')) || 
                     (defined('WP_MAIL_SENDER_SMTP_PASSWORD') && WP_MAIL_SENDER_SMTP_PASSWORD);
$db_password_set = !empty(get_option('wp_mail_sender_db_password', '')) || 
                   (defined('WP_MAIL_SENDER_MAILING_PASSWORD') && WP_MAIL_SENDER_MAILING_PASSWORD);

$environment = defined('WP_MAIL_SENDER_ENVIRONMENT') ? WP_MAIL_SENDER_ENVIRONMENT : 'unknown';
$env_color = $environment === 'staging' ? '#d63638' : '#00a32a';
?>

<div class="wrap">
    <h1>Mail Sender - Configuration</h1>
    
    <!-- Environment Badge -->
    <div style="background: <?php echo $env_color; ?>; color: white; padding: 10px 20px; border-radius: 4px; margin: 20px 0; display: inline-block; font-weight: bold;">
        🌐 Environnement détecté : <?php echo strtoupper($environment); ?>
    </div>
    
    <!-- Status des connexions -->
    <div class="wp-mail-sender-status-cards" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 20px 0;">
        <div class="card" style="padding: 15px; border-left: 4px solid <?php echo $smtp_password_set ? '#46b450' : '#dc3232'; ?>;">
            <h3 style="margin-top: 0;">SMTP Email</h3>
            <?php if ($smtp_password_set): ?>
                <p style="color: #46b450;">✓ Mot de passe configuré</p>
            <?php else: ?>
                <p style="color: #dc3232;">✗ Mot de passe manquant - Les emails ne pourront pas être envoyés</p>
            <?php endif; ?>
        </div>
        
        <div class="card" style="padding: 15px; border-left: 4px solid <?php echo $db_connected ? '#46b450' : '#dc3232'; ?>;">
            <h3 style="margin-top: 0;">Base de données Mailing</h3>
            <?php if ($db_connected): ?>
                <p style="color: #46b450;">✓ Connexion établie</p>
            <?php else: ?>
                <p style="color: #dc3232;">✗ <?php echo esc_html($db_error); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <form method="post" action="">
        <?php wp_nonce_field('wp_mail_sender_settings', 'wp_mail_sender_settings_nonce'); ?>
        
        <h2>🔐 Configuration SMTP (Envoi d'emails)</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Serveur SMTP</label></th>
                <td>
                    <code><?php echo esc_attr(WP_MAIL_SENDER_SMTP_HOST); ?></code>
                    <p class="description">Port: <?php echo esc_attr(WP_MAIL_SENDER_SMTP_PORT); ?> (SSL)</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Compte email</label></th>
                <td>
                    <code><?php echo esc_attr(WP_MAIL_SENDER_SMTP_USER); ?></code>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="from_email">Adresse d'expéditeur (From)</label></th>
                <td>
                    <input type="email" 
                           id="from_email" 
                           name="from_email" 
                           value="<?php echo esc_attr(get_option('wp_mail_sender_from_email', WP_MAIL_SENDER_SMTP_USER)); ?>" 
                           class="regular-text" 
                           placeholder="social@tabac-des-battieres.com" />
                    <p class="description">Adresse utilisée dans l'en-tête <code>From</code>. Par défaut, le compte SMTP.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="reply_to_email">Adresse de réponse (Reply-To)</label></th>
                <td>
                    <input type="email"
                           id="reply_to_email"
                           name="reply_to_email"
                           value="<?php echo esc_attr(get_option('wp_mail_sender_reply_to_email', WP_MAIL_SENDER_SMTP_USER)); ?>"
                           class="regular-text"
                           placeholder="social@tabac-des-battieres.com" />
                    <p class="description">Par défaut: <code><?php echo esc_html(WP_MAIL_SENDER_SMTP_USER); ?></code> (adresse "social").</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="smtp_password">
                        <strong style="color: #dc3232;">* Mot de passe SMTP</strong>
                    </label>
                </th>
                <td>
                    <input type="password" 
                           id="smtp_password" 
                           name="smtp_password" 
                           value="<?php echo esc_attr(get_option('wp_mail_sender_smtp_password', '')); ?>" 
                           class="regular-text" 
                           placeholder="Mot de passe du compte social@tabac-des-battieres.com" />
                    <p class="description">
                        <strong>REQUIS</strong> pour envoyer des emails via <code><?php echo esc_attr(WP_MAIL_SENDER_SMTP_USER); ?></code><br>
                        <?php if (defined('WP_MAIL_SENDER_SMTP_PASSWORD') && WP_MAIL_SENDER_SMTP_PASSWORD): ?>
                            <span style="color: #46b450;">✓ Défini dans wp-config.php (sécurisé)</span>
                        <?php else: ?>
                            <em>Sécurité :</em> Ajouter dans <code>wp-config.php</code>: <code>define('WP_MAIL_SENDER_SMTP_PASSWORD', 'votre_mdp');</code>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="from_name">Nom de l'expéditeur</label></th>
                <td>
                    <input type="text" 
                           id="from_name" 
                           name="from_name" 
                           value="<?php echo esc_attr(get_option('wp_mail_sender_from_name', 'Tabac des Battières')); ?>" 
                           class="regular-text" />
                    <p class="description">Nom affiché dans les emails reçus</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="reply_to_name">Nom du Reply-To</label></th>
                <td>
                    <input type="text"
                           id="reply_to_name"
                           name="reply_to_name"
                           value="<?php echo esc_attr(get_option('wp_mail_sender_reply_to_name', '')); ?>"
                           class="regular-text" />
                    <p class="description">Optionnel. Nom affiché pour l'adresse de réponse.</p>
                </td>
            </tr>
        </table>
        
        <h2>💾 Base de données</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Base source (WooCommerce)</label></th>
                <td>
                    <code><?php echo esc_attr(WP_MAIL_SENDER_SOURCE_DB); ?></code>
                    <p class="description">Lecture des clients/commandes WooCommerce</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Base mailing</label></th>
                <td>
                    <code><?php echo esc_attr(WP_MAIL_SENDER_MAILING_DB); ?></code>
                    <p class="description">Stockage templates, listes et logs d'envoi</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Utilisateur DB</label></th>
                <td><code><?php echo esc_attr(WP_MAIL_SENDER_MAILING_USER); ?></code></td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="db_password">
                        <strong style="color: #dc3232;">* Mot de passe DB Mailing</strong>
                    </label>
                </th>
                <td>
                    <input type="password" 
                           id="db_password" 
                           name="db_password" 
                           value="<?php echo esc_attr(get_option('wp_mail_sender_db_password', '')); ?>" 
                           class="regular-text"
                           placeholder="Mot de passe pour <?php echo esc_attr(WP_MAIL_SENDER_MAILING_USER); ?>" />
                    <p class="description">
                        <strong>REQUIS</strong> pour accéder à <code><?php echo esc_attr(WP_MAIL_SENDER_MAILING_DB); ?></code><br>
                        <?php if (defined('WP_MAIL_SENDER_MAILING_PASSWORD') && WP_MAIL_SENDER_MAILING_PASSWORD): ?>
                            <span style="color: #46b450;">✓ Défini dans wp-config.php (sécurisé)</span>
                        <?php else: ?>
                            <em>Sécurité :</em> Ajouter dans <code>wp-config.php</code>: <code>define('WP_MAIL_SENDER_MAILING_PASSWORD', 'votre_mdp');</code>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="💾 Enregistrer les paramètres" />
            <input type="submit" name="test_connection" class="button button-secondary" value="✉️ Tester l'envoi SMTP" 
                   <?php echo !$smtp_password_set ? 'disabled title="Configurez d\'abord le mot de passe SMTP"' : ''; ?> />
        </p>
    </form>
    
    <hr style="margin: 40px 0;">
    
    <!-- Test d'envoi simple -->
    <h2>🧪 Test d'envoi simple</h2>
    <form method="post" action="" style="background: #f9f9f9; padding: 20px; border-radius: 4px; border: 1px solid #ddd;">
        <?php wp_nonce_field('wp_mail_sender_simple_test', 'wp_mail_sender_simple_test_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="test_email">Email de destination *</label></th>
                <td>
                    <input type="email" 
                           id="test_email" 
                           name="test_email" 
                           value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                           class="regular-text" 
                           required />
                    <p class="description">Email qui recevra le message de test</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="test_subject">Sujet</label></th>
                <td>
                    <input type="text" 
                           id="test_subject" 
                           name="test_subject" 
                           value="Test WP Mail Sender - <?php echo date('d/m/Y H:i'); ?>" 
                           class="large-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="test_message">Message</label></th>
                <td>
                    <textarea id="test_message" 
                              name="test_message" 
                              rows="5" 
                              class="large-text">Ceci est un email de test envoyé depuis WP Mail Sender.

Environnement: <?php echo strtoupper($environment); ?>
Date: <?php echo date('d/m/Y H:i:s'); ?>
Serveur: <?php echo WP_MAIL_SENDER_SMTP_HOST; ?>
Expéditeur: <?php echo WP_MAIL_SENDER_SMTP_USER; ?></textarea>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="send_simple_test" class="button button-primary" value="📤 Envoyer l'email de test" 
                   <?php echo !$smtp_password_set ? 'disabled title="Configurez d\'abord le mot de passe SMTP"' : ''; ?> />
        </p>
        
        <?php if (!$smtp_password_set): ?>
            <p style="color: #dc3232;"><strong>⚠️ Impossible d'envoyer :</strong> Le mot de passe SMTP n'est pas configuré.</p>
        <?php endif; ?>
    </form>
    
    <hr style="margin: 40px 0;">
    
    <h2>� Fonctionnement de l'extension</h2>
    
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #2271b1;">
        <h3 style="margin-top: 0;">🎯 Objectif</h3>
        <p>WP Mail Sender permet d'envoyer des campagnes d'emails personnalisées à vos clients WooCommerce via SMTP, avec gestion de templates et de listes de diffusion.</p>
        
        <h3>🔧 Architecture</h3>
        <ul style="line-height: 1.8;">
            <li><strong>Base source (<?php echo esc_html(WP_MAIL_SENDER_SOURCE_DB); ?>)</strong> : Base WordPress/WooCommerce pour récupérer les clients et commandes</li>
            <li><strong>Base mailing (<?php echo esc_html(WP_MAIL_SENDER_MAILING_DB); ?>)</strong> : Base dédiée qui stocke les templates, listes, campagnes et logs</li>
            <li><strong>Serveur SMTP</strong> : <?php echo esc_html(WP_MAIL_SENDER_SMTP_HOST); ?> pour l'envoi sécurisé des emails</li>
        </ul>
        
        <h3>📋 Workflow</h3>
        <ol style="line-height: 1.8;">
            <li><strong>Templates</strong> : Créez des modèles d'emails avec variables ({{first_name}}, {{email}}, etc.)</li>
            <li><strong>Listes</strong> : Définissez des segments de clients (tous les clients, clients avec commandes, etc.)</li>
            <li><strong>Campagnes</strong> : Associez un template à une liste et lancez l'envoi</li>
            <li><strong>Logs</strong> : Suivez les envois réussis/échoués pour chaque campagne</li>
        </ol>
        
        <h3>🔐 Sécurité</h3>
        <p>Les mots de passe (SMTP et base de données) doivent être définis dans <code>wp-config.php</code> pour une sécurité maximale :</p>
        <pre style="background: #fff; padding: 10px; border-radius: 4px; font-size: 12px;">define('WP_MAIL_SENDER_SMTP_PASSWORD', 'votre_mot_de_passe_smtp');
define('WP_MAIL_SENDER_MAILING_PASSWORD', 'votre_mot_de_passe_db');</pre>
    </div>
</div>

<style>
.wp-mail-sender-status-cards h3 {
    font-size: 16px;
    margin-bottom: 10px;
}
.wp-mail-sender-status-cards p {
    margin: 5px 0;
    font-weight: 500;
}
</style>
