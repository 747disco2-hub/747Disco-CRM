<?php
/**
 * Template per la pagina strumenti 747 Disco CRM
 *
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      1.0.0
 */

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Ottieni strumenti disponibili
$tools = $this->get_available_tools();
?>

<div class="wrap disco747-tools-page">
    <!-- Header -->
    <div class="disco747-page-header">
        <h1>
            <span class="dashicons dashicons-admin-tools"></span>
            <?php _e('Strumenti e Utilità 747 Disco CRM', 'disco747'); ?>
        </h1>
        <p class="disco747-page-description">
            <?php _e('Strumenti di manutenzione, backup e gestione del sistema', 'disco747'); ?>
        </p>
    </div>

    <div class="disco747-tools-grid">
        
        <?php foreach ($tools as $tool_key => $tool): ?>
        <div class="disco747-tool-card disco747-tool-<?php echo esc_attr($tool['type']); ?>">
            <div class="disco747-tool-header">
                <div class="disco747-tool-icon">
                    <span class="dashicons dashicons-<?php echo esc_attr($tool['icon']); ?>"></span>
                </div>
                <div class="disco747-tool-info">
                    <h3><?php echo esc_html($tool['name']); ?></h3>
                    <p><?php echo esc_html($tool['description']); ?></p>
                </div>
            </div>
            
            <div class="disco747-tool-body">
                <form method="post" action="" class="disco747-tool-form" data-tool="<?php echo esc_attr($tool_key); ?>">
                    <?php wp_nonce_field('disco747_tools_nonce'); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr($tool['action']); ?>" />
                    
                    <?php if ($tool_key === 'export_settings'): ?>
                        <div class="disco747-tool-options">
                            <label>
                                <input type="checkbox" name="include_sensitive" value="1" />
                                <?php _e('Includi credenziali sensibili', 'disco747'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Include anche API keys e tokens nel backup (non consigliato per condivisione).', 'disco747'); ?>
                            </p>
                        </div>
                    
                    <?php elseif ($tool_key === 'import_settings'): ?>
                        <div class="disco747-tool-options">
                            <input type="file" name="config_file" accept=".json" id="disco747-config-file" />
                            <label>
                                <input type="checkbox" name="overwrite" value="1" />
                                <?php _e('Sovrascrivi impostazioni esistenti', 'disco747'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Carica un file di configurazione JSON precedentemente esportato.', 'disco747'); ?>
                            </p>
                        </div>
                    
                    <?php elseif ($tool_key === 'system_cleanup'): ?>
                        <div class="disco747-tool-options">
                            <div class="disco747-cleanup-options">
                                <label>
                                    <input type="checkbox" name="cleanup_temp" value="1" checked />
                                    <?php _e('File temporanei', 'disco747'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="cleanup_logs" value="1" />
                                    <?php _e('Log vecchi (>30 giorni)', 'disco747'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="cleanup_cache" value="1" checked />
                                    <?php _e('Cache e transient', 'disco747'); ?>
                                </label>
                            </div>
                        </div>
                    
                    <?php elseif (isset($tool['confirm']) && $tool['confirm']): ?>
                        <div class="disco747-tool-warning">
                            <p><strong>⚠️ <?php _e('Attenzione:', 'disco747'); ?></strong> 
                               <?php _e('Questa operazione non può essere annullata.', 'disco747'); ?></p>
                            <label>
                                <input type="checkbox" name="confirm_<?php echo esc_attr($tool['action']); ?>" value="yes" required />
                                <?php _e('Confermo di voler procedere', 'disco747'); ?>
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <div class="disco747-tool-actions">
                        <button type="submit" class="button disco747-btn-<?php echo esc_attr($tool['type']); ?>">
                            <span class="dashicons dashicons-<?php echo esc_attr($tool['icon']); ?>"></span>
                            <?php echo esc_html($tool['name']); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Tool Personalizzato: Backup Database -->
        <div class="disco747-tool-card disco747-tool-backup">
            <div class="disco747-tool-header">
                <div class="disco747-tool-icon">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <div class="disco747-tool-info">
                    <h3><?php _e('Backup Database', 'disco747'); ?></h3>
                    <p><?php _e('Crea un backup completo di tutti i dati dei preventivi', 'disco747'); ?></p>
                </div>
            </div>
            <div class="disco747-tool-body">
                <div class="disco747-backup-info">
                    <p><strong><?php _e('Ultimo backup:', 'disco747'); ?></strong> 
                       <span id="disco747-last-backup"><?php echo esc_html(get_option('disco747_last_backup', __('Mai', 'disco747'))); ?></span></p>
                    <p><strong><?php _e('Dimensione database:', 'disco747'); ?></strong> 
                       <span id="disco747-db-size">-</span></p>
                </div>
                <button type="button" class="button disco747-btn-backup" id="disco747-create-backup">
                    <span class="dashicons dashicons-database"></span>
                    <?php _e('Crea Backup', 'disco747'); ?>
                </button>
            </div>
        </div>

        <!-- Tool Personalizzato: Diagnostics -->
        <div class="disco747-tool-card disco747-tool-test">
            <div class="disco747-tool-header">
                <div class="disco747-tool-icon">
                    <span class="dashicons dashicons-admin-tools"></span>
                </div>
                <div class="disco747-tool-info">
                    <h3><?php _e('Diagnostics Completo', 'disco747'); ?></h3>
                    <p><?php _e('Esegue un test completo di tutti i componenti del sistema', 'disco747'); ?></p>
                </div>
            </div>
            <div class="disco747-tool-body">
                <div class="disco747-diagnostics-status">
                    <div id="disco747-diagnostics-results" style="display: none;">
                        <!-- Risultati caricati dinamicamente -->
                    </div>
                </div>
                <button type="button" class="button disco747-btn-test" id="disco747-run-diagnostics">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e('Esegui Diagnostics', 'disco747'); ?>
                </button>
            </div>
        </div>

        <!-- Tool Personalizzato: Log Viewer -->
        <?php if (get_option('disco747_debug_mode', false)): ?>
        <div class="disco747-tool-card disco747-tool-debug">
            <div class="disco747-tool-header">
                <div class="disco747-tool-icon">
                    <span class="dashicons dashicons-media-text"></span>
                </div>
                <div class="disco747-tool-info">
                    <h3><?php _e('Visualizzatore Log', 'disco747'); ?></h3>
                    <p><?php _e('Visualizza e analizza i log di debug del sistema', 'disco747'); ?></p>
                </div>
            </div>
            <div class="disco747-tool-body">
                <div class="disco747-log-controls">
                    <select id="disco747-log-level">
                        <option value=""><?php _e('Tutti i livelli', 'disco747'); ?></option>
                        <option value="ERROR"><?php _e('Solo Errori', 'disco747'); ?></option>
                        <option value="WARNING"><?php _e('Warning ed Errori', 'disco747'); ?></option>
                        <option value="INFO"><?php _e('Info e superiori', 'disco747'); ?></option>
                    </select>
                    <input type="number" id="disco747-log-lines" value="50" min="10" max="500" placeholder="Righe" />
                </div>
                <button type="button" class="button disco747-btn-debug" id="disco747-load-logs">
                    <span class="dashicons dashicons-media-text"></span>
                    <?php _e('Carica Log', 'disco747'); ?>
                </button>
                <div id="disco747-log-content" class="disco747-log-viewer" style="display: none;">
                    <!-- Log caricati dinamicamente -->
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Modal Risultati -->
    <div id="disco747-results-modal" class="disco747-modal" style="display: none;">
        <div class="disco747-modal-content">
            <div class="disco747-modal-header">
                <h3 id="disco747-modal-title"><?php _e('Risultati Operazione', 'disco747'); ?></h3>
                <button type="button" class="button-link" id="disco747-close-results">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="disco747-modal-body">
                <div id="disco747-modal-results">
                    <!-- Contenuto caricato dinamicamente -->
                </div>
            </div>
            <div class="disco747-modal-footer">
                <button type="button" class="button" id="disco747-download-result" style="display: none;">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Scarica', 'disco747'); ?>
                </button>
            </div>
        </div>
    </div>

</div>

<style>
.disco747-tools-page {
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

.disco747-page-header h1 {
    margin: 0;
    font-size: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.disco747-page-description {
    margin: 10px 0 0 0;
    opacity: 0.9;
    font-size: 16px;
}

.disco747-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    padding: 20px;
}

.disco747-tool-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    border-left: 4px solid #c28a4d;
}

.disco747-tool-export { border-left-color: #28a745; }
.disco747-tool-import { border-left-color: #007bff; }
.disco747-tool-test { border-left-color: #17a2b8; }
.disco747-tool-maintenance { border-left-color: #ffc107; }
.disco747-tool-reset { border-left-color: #dc3545; }
.disco747-tool-backup { border-left-color: #6f42c1; }
.disco747-tool-debug { border-left-color: #fd7e14; }

.disco747-tool-header {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.disco747-tool-icon {
    font-size: 32px;
    color: #c28a4d;
}

.disco747-tool-info h3 {
    margin: 0 0 5px 0;
    color: #2b1e1a;
}

.disco747-tool-info p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.disco747-tool-body {
    padding: 20px;
}

.disco747-tool-options {
    margin-bottom: 15px;
}

.disco747-tool-options label {
    display: block;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.disco747-cleanup-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.disco747-tool-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
}

.disco747-tool-warning label {
    margin-top: 10px;
}

.disco747-backup-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.disco747-backup-info p {
    margin: 5px 0;
}

.disco747-diagnostics-status {
    min-height: 50px;
    margin-bottom: 15px;
}

.disco747-log-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.disco747-log-controls select,
.disco747-log-controls input {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.disco747-log-viewer {
    background: #2b1e1a;
    color: #c28a4d;
    padding: 15px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    max-height: 300px;
    overflow-y: auto;
    margin-top: 15px;
}

.disco747-log-viewer .log-error { color: #ff6b6b; }
.disco747-log-viewer .log-warning { color: #feca57; }
.disco747-log-viewer .log-info { color: #48dbfb; }
.disco747-log-viewer .log-debug { color: #ff9ff3; }

.disco747-btn-export { background: #28a745 !important; border-color: #28a745 !important; color: white !important; }
.disco747-btn-import { background: #007bff !important; border-color: #007bff !important; color: white !important; }
.disco747-btn-test { background: #17a2b8 !important; border-color: #17a2b8 !important; color: white !important; }
.disco747-btn-maintenance { background: #ffc107 !important; border-color: #ffc107 !important; color: #212529 !important; }
.disco747-btn-reset { background: #dc3545 !important; border-color: #dc3545 !important; color: white !important; }
.disco747-btn-backup { background: #6f42c1 !important; border-color: #6f42c1 !important; color: white !important; }
.disco747-btn-debug { background: #fd7e14 !important; border-color: #fd7e14 !important; color: white !important; }

.disco747-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.disco747-modal-content {
    background: white;
    border-radius: 8px;
    max-width: 800px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.disco747-modal-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.disco747-modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.disco747-modal-footer {
    background: #f8f9fa;
    padding: 15px 20px;
    border-top: 1px solid #e9ecef;
    text-align: right;
}

.disco747-result-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.disco747-result-success { color: #28a745; }
.disco747-result-error { color: #dc3545; }
.disco747-result-warning { color: #ffc107; }

@media (max-width: 768px) {
    .disco747-tools-grid {
        grid-template-columns: 1fr;
        padding: 10px;
    }
    
    .disco747-tool-header {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .disco747-log-controls {
        flex-direction: column;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Gestione form strumenti
    $('.disco747-tool-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var tool = form.data('tool');
        var formData = new FormData(form[0]);
        var button = form.find('button[type="submit"]');
        var originalText = button.html();
        
        button.html('<span class="dashicons dashicons-update disco747-spin"></span> Elaborando...');
        button.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showResults(tool, response.data);
                } else {
                    alert('Errore: ' + (response.data.message || 'Operazione fallita'));
                }
            },
            error: function() {
                alert('Errore di comunicazione con il server');
            },
            complete: function() {
                button.html(originalText);
                button.prop('disabled', false);
            }
        });
    });
    
    // Backup database
    $('#disco747-create-backup').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.html('<span class="dashicons dashicons-update disco747-spin"></span> Creando backup...');
        button.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'disco747_create_backup',
            nonce: disco747Admin.nonce
        }, function(response) {
            if (response.success) {
                $('#disco747-last-backup').text(response.data.timestamp);
                showResults('backup', response.data);
            } else {
                alert('Errore durante il backup: ' + response.data.message);
            }
        }).always(function() {
            button.html(originalText);
            button.prop('disabled', false);
        });
    });
    
    // Diagnostics
    $('#disco747-run-diagnostics').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.html('<span class="dashicons dashicons-update disco747-spin"></span> Eseguendo test...');
        button.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'disco747_run_diagnostics',
            nonce: disco747Admin.nonce
        }, function(response) {
            if (response.success) {
                displayDiagnosticsResults(response.data);
            } else {
                alert('Errore durante i diagnostics');
            }
        }).always(function() {
            button.html(originalText);
            button.prop('disabled', false);
        });
    });
    
    // Log viewer
    $('#disco747-load-logs').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        var level = $('#disco747-log-level').val();
        var lines = $('#disco747-log-lines').val();
        
        button.html('<span class="dashicons dashicons-update disco747-spin"></span> Caricando...');
        button.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'disco747_get_logs',
            level: level,
            lines: lines,
            nonce: disco747Admin.nonce
        }, function(response) {
            if (response.success) {
                displayLogs(response.data.logs);
            } else {
                alert('Errore caricamento log');
            }
        }).always(function() {
            button.html(originalText);
            button.prop('disabled', false);
        });
    });
    
    // Chiudi modal
    $('#disco747-close-results').on('click', function() {
        $('#disco747-results-modal').hide();
    });
    
    // Download risultati
    $('#disco747-download-result').on('click', function() {
        var downloadUrl = $(this).data('url');
        if (downloadUrl) {
            window.location.href = downloadUrl;
        }
    });
    
    // Carica dimensione database all'avvio
    loadDatabaseSize();
    
    function showResults(tool, data) {
        var title = 'Risultati ' + tool.charAt(0).toUpperCase() + tool.slice(1);
        var html = '';
        
        if (data.results) {
            data.results.forEach(function(result) {
                var statusClass = result.success ? 'success' : 'error';
                html += '<div class="disco747-result-item">';
                html += '<span>' + result.message + '</span>';
                html += '<span class="disco747-result-' + statusClass + '">';
                html += result.success ? '✅' : '❌';
                html += '</span>';
                html += '</div>';
            });
        } else {
            html = '<p>' + (data.message || 'Operazione completata') + '</p>';
        }
        
        $('#disco747-modal-title').text(title);
        $('#disco747-modal-results').html(html);
        
        if (data.download_url) {
            $('#disco747-download-result').data('url', data.download_url).show();
        } else {
            $('#disco747-download-result').hide();
        }
        
        $('#disco747-results-modal').show();
    }
    
    function displayDiagnosticsResults(data) {
        var html = '<div class="disco747-diagnostics-summary">';
        html += '<h4>Risultati Diagnostics</h4>';
        
        data.tests.forEach(function(test) {
            var statusClass = test.passed ? 'success' : 'error';
            html += '<div class="disco747-result-item">';
            html += '<span>' + test.name + '</span>';
            html += '<span class="disco747-result-' + statusClass + '">';
            html += test.passed ? '✅ OK' : '❌ ' + test.error;
            html += '</span>';
            html += '</div>';
        });
        
        html += '</div>';
        $('#disco747-diagnostics-results').html(html).show();
    }
    
    function displayLogs(logs) {
        var html = '';
        
        logs.forEach(function(log) {
            var levelClass = 'log-' + log.level.toLowerCase();
            html += '<div class="' + levelClass + '">';
            html += '[' + log.timestamp + '] [' + log.level + '] ' + log.message;
            html += '</div>';
        });
        
        $('#disco747-log-content').html(html).show();
    }
    
    function loadDatabaseSize() {
        $.post(ajaxurl, {
            action: 'disco747_get_db_size',
            nonce: disco747Admin.nonce
        }, function(response) {
            if (response.success) {
                $('#disco747-db-size').text(response.data.size);
            }
        });
    }
    
});

.disco747-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</script>