<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>Mail Sender - Tableau de bord</h1>
    
    <div class="wp-mail-sender-stats">
        <div class="stat-box">
            <h3><?php echo esc_html($total_campaigns ?? 0); ?></h3>
            <p>Campagnes</p>
        </div>
        <div class="stat-box">
            <h3><?php echo esc_html($total_sent ?? 0); ?></h3>
            <p>Emails envoyés</p>
        </div>
        <div class="stat-box">
            <h3><?php echo esc_html($total_templates ?? 0); ?></h3>
            <p>Templates</p>
        </div>
        <div class="stat-box">
            <h3><?php echo esc_html($total_lists ?? 0); ?></h3>
            <p>Listes</p>
        </div>
    </div>
    
    <h2>Campagnes récentes</h2>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Template</th>
                <th>Liste</th>
                <th>Statut</th>
                <th>Envoyés</th>
                <th>Échoués</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recent_campaigns)): ?>
                <tr>
                    <td colspan="7">Aucune campagne trouvée.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recent_campaigns as $campaign): ?>
                    <tr>
                        <td><?php echo esc_html($campaign->name); ?></td>
                        <td><?php echo esc_html($campaign->template_name); ?></td>
                        <td><?php echo esc_html($campaign->list_name); ?></td>
                        <td>
                            <span class="status-<?php echo esc_attr($campaign->status); ?>">
                                <?php echo esc_html(ucfirst($campaign->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($campaign->sent_count); ?></td>
                        <td><?php echo esc_html($campaign->failed_count); ?></td>
                        <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($campaign->created_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
