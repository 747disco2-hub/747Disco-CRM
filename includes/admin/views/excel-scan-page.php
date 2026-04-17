<?php
/**
 * Pagina Excel Scan - Interfaccia Ottimizzata Mobile-First
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      11.8.9-RESET-AND-SCAN
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_drive_configured = isset($is_googledrive_configured) && $is_googledrive_configured;
?>

<div class="wrap disco747-excel-scan">
    <div class="disco747-page-header">
        <h1>
            <span class="dashicons dashicons-media-spreadsheet"></span>
            Scansione File Excel
        </h1>
        <p class="page-subtitle">Importa automaticamente i preventivi da Google Drive</p>
    </div>
    
    <?php 
    // ✅ Mostra info timeout
    if (function_exists('disco747_render_timeout_info')) {
        echo disco747_render_timeout_info();
    }
    ?>

    <?php if (!$is_drive_configured): ?>
        <div class="disco747-notice disco747-notice-warning">
            <div class="notice-icon">⚠️</div>
            <div class="notice-content">
                <strong>Configurazione Google Drive Richiesta</strong>
                <p>Per utilizzare la scansione automatica, configura prima l'integrazione con Google Drive.</p>
                <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>" class="button button-primary">
                    Vai alle Impostazioni
                </a>
            </div>
        </div>
    <?php else: ?>

        <!-- Box Scansione -->
        <div class="disco747-box">
            <div class="box-header">
                <h2>🚀 Avvia Scansione</h2>
            </div>

            <div class="box-body">
                <div class="scan-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>📅 Anno</label>
                            <select id="scan-year">
                                <option value="2025" selected>2025</option>
                                <option value="2024">2024</option>
                                <option value="2026">2026</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>📆 Mese (opzionale)</label>
                            <select id="scan-month">
                                <option value="">Tutti i mesi</option>
                                <option value="GENNAIO">Gennaio</option>
                                <option value="FEBBRAIO">Febbraio</option>
                                <option value="MARZO">Marzo</option>
                                <option value="APRILE">Aprile</option>
                                <option value="MAGGIO">Maggio</option>
                                <option value="GIUGNO">Giugno</option>
                                <option value="LUGLIO">Luglio</option>
                                <option value="AGOSTO">Agosto</option>
                                <option value="SETTEMBRE">Settembre</option>
                                <option value="OTTOBRE">Ottobre</option>
                                <option value="NOVEMBRE">Novembre</option>
                                <option value="DICEMBRE">Dicembre</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button id="start-scan-btn" class="btn-scan" style="flex: 1;">
                            <span class="dashicons dashicons-update"></span>
                            Analizza Ora
                        </button>
                        
                        <button id="reset-scan-btn" class="btn-reset" style="flex: 0 0 auto;">
                            <span class="dashicons dashicons-trash"></span>
                            Svuota e Rianalizza
                        </button>
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <button id="unlock-scan-btn" class="button button-secondary" style="width: 100%; padding: 8px; border-color: #ff6b6b; color: #ff6b6b;">
                            <span class="dashicons dashicons-unlock" style="color: #ff6b6b;"></span>
                            🔓 Sblocca Scansione (Emergenza)
                        </button>
                        <p style="font-size: 12px; color: #666; margin-top: 5px; text-align: center;">
                            ⚠️ Usa solo se vedi "Scansione già in corso" senza scansione attiva
                        </p>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div id="progress-section" style="display: none;">
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="progress-bar-fill">
                            <span id="progress-percent">0%</span>
                        </div>
                    </div>
                    <p class="progress-status" id="progress-status">Inizializzazione...</p>
                </div>

                <!-- Risultati -->
                <div id="results-section" style="display: none;">
                    <div class="results-cards">
                        <div class="stat-card card-blue">
                            <div class="stat-icon">📁</div>
                            <div class="stat-number" id="stat-total">0</div>
                            <div class="stat-label">Trovati</div>
                        </div>

                        <div class="stat-card card-green">
                            <div class="stat-icon">✅</div>
                            <div class="stat-number" id="stat-processed">0</div>
                            <div class="stat-label">Processati</div>
                        </div>

                        <div class="stat-card card-yellow">
                            <div class="stat-icon">🆕</div>
                            <div class="stat-number" id="stat-new">0</div>
                            <div class="stat-label">Nuovi</div>
                        </div>

                        <div class="stat-card card-purple">
                            <div class="stat-icon">🔄</div>
                            <div class="stat-number" id="stat-updated">0</div>
                            <div class="stat-label">Aggiornati</div>
                        </div>

                        <div class="stat-card card-red" id="error-card" style="display: none;">
                            <div class="stat-icon">❌</div>
                            <div class="stat-number" id="stat-errors">0</div>
                            <div class="stat-label">Errori</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Panel -->
        <div class="debug-panel">
            <div class="debug-header" onclick="disco747ToggleDebug()">
                <h3>
                    <span id="debug-icon" class="dashicons dashicons-arrow-down-alt2"></span>
                    Debug & Informazioni Sistema
                </h3>
            </div>
            <div id="debug-body" class="debug-body" style="display: none;">
                <div class="debug-box">
                    <h4>📋 Log Scansione</h4>
                    <pre id="debug-log">Nessun log disponibile. Avvia una scansione.</pre>
                </div>

                <div class="debug-box">
                    <h4>ℹ️ Info Sistema</h4>
                    <table class="info-table">
                        <tr>
                            <td><strong>Google Drive:</strong></td>
                            <td><span class="badge badge-success">✅ Configurato</span></td>
                        </tr>
                        <tr>
                            <td><strong>Versione:</strong></td>
                            <td>11.8.9-RESET-AND-SCAN</td>
                        </tr>
                        <tr>
                            <td><strong>Database:</strong></td>
                            <td>wp_disco747_preventivi</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
/* CSS completo per l'interfaccia */
.disco747-excel-scan { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
.disco747-page-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
.disco747-page-header h1 { margin: 0; font-size: 28px; display: flex; align-items: center; justify-content: center; gap: 10px; }
.disco747-page-header .dashicons { font-size: 32px; width: 32px; height: 32px; }
.page-subtitle { margin: 10px 0 0; opacity: 0.95; font-size: 15px; }
.disco747-box { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px; }
.box-header { padding: 20px 25px; background: #f8f9fa; border-bottom: 2px solid #667eea; border-radius: 10px 10px 0 0; }
.box-header h2 { margin: 0; font-size: 20px; color: #2c3e50; }
.box-body { padding: 25px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #2c3e50; }
.form-group select { width: 100%; padding: 10px 12px; border: 2px solid #e0e6ed; border-radius: 6px; font-size: 14px; background: white; }
.form-group select:focus { outline: none; border-color: #667eea; }
.btn-scan { padding: 15px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); }
.btn-scan:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4); }
.btn-scan:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
.btn-reset { padding: 15px 24px; background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3); white-space: nowrap; }
.btn-reset:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(231, 76, 60, 0.4); background: linear-gradient(135deg, #c0392b 0%, #a93226 100%); }
.btn-reset:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
#progress-section { margin-top: 25px; }
.progress-bar-container { width: 100%; height: 35px; background: #f0f2f5; border-radius: 18px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1); }
.progress-bar-fill { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); width: 0%; transition: width 0.4s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px; }
.progress-status { margin-top: 10px; text-align: center; color: #546e7a; font-size: 14px; }
#results-section { margin-top: 30px; }
.results-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
.stat-card { background: white; border: 2px solid; border-radius: 10px; padding: 20px; text-align: center; transition: transform 0.2s; }
.stat-card:hover { transform: translateY(-3px); }
.card-blue { border-color: #3498db; background: #e8f4f8; }
.card-green { border-color: #27ae60; background: #e8f8f5; }
.card-yellow { border-color: #f39c12; background: #fef5e7; }
.card-purple { border-color: #667eea; background: #f0f3ff; }
.card-red { border-color: #e74c3c; background: #fef0ef; }
.stat-icon { font-size: 36px; margin-bottom: 8px; }
.stat-number { font-size: 32px; font-weight: bold; color: #2c3e50; margin-bottom: 5px; }
.stat-label { font-size: 13px; color: #7f8c8d; font-weight: 600; text-transform: uppercase; }
.debug-panel { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px; overflow: hidden; }
.debug-header { padding: 15px 20px; background: #e9ecef; cursor: pointer; user-select: none; }
.debug-header:hover { background: #dee2e6; }
.debug-header h3 { margin: 0; font-size: 16px; color: #2c3e50; display: flex; align-items: center; gap: 8px; }
.debug-body { padding: 20px; }
.debug-box { background: white; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
.debug-box:last-child { margin-bottom: 0; }
.debug-box h4 { margin: 0 0 10px; font-size: 14px; color: #2c3e50; }
.debug-box pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 6px; max-height: 300px; overflow-y: auto; font-size: 12px; margin: 0; white-space: pre-wrap; }
.info-table { width: 100%; }
.info-table td { padding: 8px 0; font-size: 14px; }
.info-table td:first-child { width: 150px; }
.badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.spin { animation: spin 1s linear infinite; }
@media (max-width: 768px) {
    .disco747-page-header h1 { font-size: 22px; }
    .form-row { grid-template-columns: 1fr; gap: 15px; }
    .results-cards { grid-template-columns: repeat(2, 1fr); }
    .stat-icon { font-size: 28px; }
    .stat-number { font-size: 24px; }
    .btn-reset { flex: 1 !important; }
}
@media (max-width: 480px) {
    .results-cards { grid-template-columns: 1fr; }
}
</style>

<script>
// ✅ Funzione toggle debug (SOLO utility, no AJAX)
function disco747ToggleDebug() {
    const body = document.getElementById('debug-body');
    const icon = document.getElementById('debug-icon');
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon.classList.remove('dashicons-arrow-down-alt2');
        icon.classList.add('dashicons-arrow-up-alt2');
    } else {
        body.style.display = 'none';
        icon.classList.remove('dashicons-arrow-up-alt2');
        icon.classList.add('dashicons-arrow-down-alt2');
    }
}

// ✅ NOTA: Tutti gli handler AJAX sono gestiti da /assets/js/excel-scan.js
// ❌ NON duplicare codice jQuery qui - causa conflitti e doppie esecuzioni
</script>