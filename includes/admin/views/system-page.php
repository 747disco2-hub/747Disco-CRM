<?php
/**
 * Template per la pagina informazioni sistema 747 Disco CRM
 *
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      1.0.0
 */

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Ottieni informazioni sistema
$system_health = $this->settings->helper->get_system_health();
$compatibility = $this->settings->helper->check_system_compatibility();
?>

<div class="wrap disco747-system-page">
    <!-- Header -->
    <div class="disco747-page-header">
        <h1>
            <span class="dashicons dashicons-info"></span>
            <?php _e('Informazioni Sistema 747 Disco CRM', 'disco747'); ?>
        </h1>
        <p class="disco747-page-description">
            <?php _e('Diagnostics, compatibilità e stato di salute del sistema', 'disco747'); ?>
        </p>
    </div>

    <!-- Stato Salute Sistema -->
    <div class="disco747-health-overview">
        <div class="disco747-health-card disco747-health-<?php echo esc_attr($system_health['status']); ?>">
            <div class="disco747-health-icon">
                <?php
                $icons = [
                    'excellent' => '🟢',
                    'good' => '🟡', 
                    'fair' => '🟠',
                    'poor' => '🔴'
                ];
                echo $icons[$system_health['status']] ?? '❓';
                ?>
            </div>
            <div class="disco747-health-info">
                <h2><?php _e('Salute Sistema:', 'disco747'); ?> 
                    <span class="disco747-health-score"><?php echo intval($system_health['score']); ?>/100</span>
                </h2>
                <p class="disco747-health-status">
                    <?php 
                    $status_texts = [
                        'excellent' => __('Sistema in condizioni eccellenti', 'disco747'),
                        'good' => __('Sistema in buone condizioni', 'disco747'),
                        'fair' => __('Sistema in condizioni discrete', 'disco747'),
                        'poor' => __('Sistema necessita attenzione', 'disco747')
                    ];
                    echo esc_html($status_texts[$system_health['status']] ?? __('Stato sconosciuto', 'disco747'));
                    ?>
                </p>
            </div>
            <div class="disco747-health-actions">
                <button type="button" class="button disco747-btn-primary" id="disco747-system-check">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Aggiorna Check', 'disco747'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="disco747-system-grid">
        
        <!-- Card Test Compatibilità -->
        <div class="disco747-system-card">
            <div class="disco747-card-header">
                <h2><span class="dashicons dashicons-yes-alt"></span> <?php _e('Test Compatibilità', 'disco747'); ?></h2>
            </div>
            <div class="disco747-card-body">
                <div class="disco747-compatibility-list">
                    <?php foreach ($compatibility as $check_name => $check): ?>
                    <div class="disco747-compatibility-item">
                        <div class="disco747-check-status">
                            <?php if ($check['status']): ?>
                                <span class="disco747-status-icon success">✅</span>
                            <?php else: ?>
                                <span class="disco747-status-icon error">❌</span>
                            <?php endif; ?>
                        </div>
                        <div class="disco747-check-info">
                            <strong><?php echo esc_html($check['message']); ?></strong>
                            <?php if (isset($check['current'])): ?>
                                <span class="disco747-check-value"><?php echo esc_html($check['current']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Card Informazioni Server -->
        <div class="disco747-system-card">
            <div class="disco747-card-header">
                <h2><span class="dashicons dashicons-admin-generic"></span> <?php _e('Informazioni Server', 'disco747'); ?></h2>
            </div>
            <div class="disco747-card-body">
                <table class="disco747-info-table">
                    <tr>
                        <td><strong><?php _e('Versione PHP', 'disco747'); ?></strong></td>
                        <td><?php echo esc_html($system_info['server']['php_version']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Versione MySQL', 'disco747'); ?></strong></td>
                        <td><?php echo esc_html($system_info['server']['mysql_version']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Memoria PHP', 'disco747'); ?></strong></td>
                        <td><?php echo esc_html($system_info['server']['memory_limit']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Max Execution Time', 'disco747'); ?></strong></td>
                        <td><?php echo esc_html($system_info['server']['max_execution_time']); ?>s</td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Upload Max Size', 'disco747'); ?></strong></td>
                        <td><?php echo esc_html($system_info['server']['upload_max_filesize']); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Card Informazioni Plugin -->
        <div class="disco747-system-card">
            <div class="disco747-card-header">
                <h2><span class="dashicons dashicons-admin-plugins"></span> <?php _e('Informazioni Plugin', 'disco747'); ?></h2>
            </div>
            <div class="disco747-card-body">
                <table class="disco747-info-table">
                    <tr>
                        <td><strong><?php _e('Versione Plugin', 'disco747'); ?></strong></td>
                        <td><?php echo esc_html(Disco747_Config::VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Versione Database', 'disco747'); ?></strong></td>
                        <td><?php echo esc_html($system_info['plugin']['database_version']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Debug Mode', 'disco747'); ?></strong></td>
                        <td>
                            <?php if ($system_info['plugin']['debug_mode']): ?>
                                <span class="disco747-status-enabled">✅ <?php _e('Attivo', 'disco747'); ?></span>
                            <?php else: ?>
                                <span class="disco747-status-disabled">❌ <?php _e('Disattivo', 'disco747'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Storage Type', 'disco747'); ?></strong></td>
                        <td><?php echo esc_html(ucfirst($system_info['plugin']['storage_type'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Configurazione', 'disco747'); ?></strong></td>
                        <td>
                            <?php if ($system_info['plugin']['configured']): ?>
                                <span class="disco747-status-ok">✅ <?php _e('Completa', 'disco747'); ?></span>
                            <?php else: ?>
                                <span class="disco747-status-warning">⚠️ <?php _e('Incompleta', 'disco747'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Card Problemi Rilevati -->
        <?php if (!empty($system_health['issues'])): ?>
        <div class="disco747-system-card disco747-issues-card">
            <div class="disco747-card-header">
                <h2><span class="dashicons dashicons-warning"></span> <?php _e('Problemi Rilevati', 'disco747'); ?></h2>
            </div>
            <div class="disco747-card-body">
                <div class="disco747-issues-list">
                    <?php foreach ($system_health['issues'] as $issue): ?>
                    <div class="disco747-issue-item">
                        <span class="disco747-issue-icon">⚠️</span>
                        <span class="disco747-issue-text"><?php echo esc_html($issue); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card Raccomandazioni -->
        <?php if (!empty($system_health['recommendations'])): ?>
        <div class="disco747-system-card">
            <div class="disco747-card-header">
                <h2><span class="dashicons dashicons-lightbulb"></span> <?php _e('Raccomandazioni', 'disco747'); ?></h2>
            </div>
            <div class="disco747-card-body">
                <div class="disco747-recommendations-list">
                    <?php foreach ($system_health['recommendations'] as $recommendation): ?>
                    <div class="disco747-recommendation-item">
                        <span class="disco747-recommendation-icon">💡</span>
                        <span class="disco747-recommendation-text"><?php echo esc_html($recommendation); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<style>
.disco747-system-page {
    background: #f9f9f9;
    margin: 0 -20px;
    padding: 0;
}

.disco747-page-header {
    background: linear-gradient(135deg, #2b1e1a 0%, #c28a4d 100%);
    color: white;
    padding: 30px 20px;
    margin-bottom: 20px;
}

.disco747-health-overview {
    padding: 0 20px;
    margin-bottom: 30px;
}

.disco747-health-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 25px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-left: 6px solid #28a745;
}

.disco747-health-card.disco747-health-poor {
    border-left-color: #dc3545;
}

.disco747-health-card.disco747-health-fair {
    border-left-color: #ffc107;
}

.disco747-health-icon {
    font-size: 48px;
}

.disco747-health-score {
    font-size: 32px;
    font-weight: bold;
    color: #c28a4d;
}

.disco747-system-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    padding: 0 20px;
}

.disco747-system-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.disco747-card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.disco747-card-header h2 {
    margin: 0;
    font-size: 18px;
    color: #2b1e1a;
    display: flex;
    align-items: center;
    gap: 8px;
}

.disco747-card-body {
    padding: 20px;
}

.disco747-compatibility-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.disco747-compatibility-item:last-child {
    border-bottom: none;
}

.disco747-info-table {
    width: 100%;
}

.disco747-info-table td {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.disco747-info-table td:first-child {
    width: 50%;
}

.disco747-issues-card {
    border-left: 4px solid #dc3545;
}

.disco747-issue-item, .disco747-recommendation-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.disco747-status-enabled { color: #28a745; }
.disco747-status-disabled { color: #dc3545; }
.disco747-status-ok { color: #28a745; }
.disco747-status-warning { color: #ffc107; }

.disco747-btn-primary {
    background: #c28a4d !important;
    border-color: #c28a4d !important;
    color: white !important;
}

@media (max-width: 768px) {
    .disco747-system-grid {
        grid-template-columns: 1fr;
    }
    
    .disco747-health-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#disco747-system-check').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.html('<span class="dashicons dashicons-update disco747-spin"></span> Verificando...');
        button.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'disco747_system_check',
            nonce: disco747Admin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Errore durante la verifica del sistema');
            }
        }).always(function() {
            button.html(originalText);
            button.prop('disabled', false);
        });
    });
});

.disco747-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</script>