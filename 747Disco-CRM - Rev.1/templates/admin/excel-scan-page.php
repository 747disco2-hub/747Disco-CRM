<?php
/**
 * Template Scansione Excel Auto - 747 Disco CRM
 * 
 * Percorso: /templates/admin/excel-scan-page.php
 * 
 * Pagina admin autonoma per scansionare e analizzare file Excel da Google Drive
 * Design nero-oro-grigio coerente con l'identità 747 Disco
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Templates
 * @since      11.5.9-EXCEL-SCAN
 * @version    1.0.0
 */

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Verifica capacità utente
if (!current_user_can('manage_options')) {
    wp_die(__('Non hai i permessi per accedere a questa pagina.'));
}

// Genera nonce per sicurezza AJAX
$nonce = wp_create_nonce('disco747_excel_scan');

// Verifica status Google Drive
$disco747_crm = disco747_crm();
$gdrive_available = false;
$gdrive_status_msg = 'Non disponibile';

if ($disco747_crm && $disco747_crm->is_initialized()) {
    $gdrive_sync = $disco747_crm->get_gdrive_sync();
    if ($gdrive_sync && $gdrive_sync->is_sync_available()) {
        $gdrive_available = true;
        $gdrive_status_msg = 'Connesso e configurato';
    } else {
        $gdrive_status_msg = 'Non configurato nelle Impostazioni';
    }
}
?>

<div class="wrap disco747-excel-scan-wrap">
    <h1 class="disco747-title">
        <span class="disco747-logo">🎵</span>
        Scansione Excel Auto - 747 Disco CRM
    </h1>
    
    <p class="disco747-subtitle">
        Analizza automaticamente file Excel da Google Drive con debug dettagliato passo-passo
    </p>

    <!-- STATUS BAR -->
    <div id="disco747-status-bar" class="disco747-status-bar">
        <div class="status-item">
            <span class="status-label">Google Drive:</span>
            <span id="gdrive-status" class="status-value <?php echo $gdrive_available ? 'connected' : 'error'; ?>">
                <?php echo esc_html($gdrive_status_msg); ?>
            </span>
        </div>
        <div class="status-item">
            <span class="status-label">File Excel trovati:</span>
            <span id="files-count" class="status-value checking">Caricamento...</span>
        </div>
        <div class="status-item">
            <span class="status-label">Versione Plugin:</span>
            <span class="status-value"><?php echo defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.5.9'; ?></span>
        </div>
    </div>

    <?php if (!$gdrive_available): ?>
    <!-- AVVISO CONFIGURAZIONE -->
    <div class="notice notice-warning">
        <p><strong>⚠️ Attenzione:</strong> Google Drive non è configurato correttamente.</p>
        <p>Per utilizzare la Scansione Excel Auto, configura prima Google Drive nelle 
           <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>" class="button button-secondary">Impostazioni</a>
        </p>
    </div>
    <?php endif; ?>

    <!-- SEZIONE RICERCA E LISTA FILE -->
    <div class="disco747-card">
        <div class="card-header">
            <h2>📁 Seleziona File Excel da Google Drive</h2>
            <button type="button" id="refresh-all-btn" class="disco747-btn disco747-btn-small">🔄 Aggiorna Tutto</button>
        </div>
        <div class="card-body">
            <!-- Filtro ricerca -->
            <div class="search-controls">
                <input type="text" id="excel-search" class="disco747-input" placeholder="Cerca file Excel per nome o percorso..." <?php echo !$gdrive_available ? 'disabled' : ''; ?>>
                <button type="button" id="search-files-btn" class="disco747-btn disco747-btn-primary" <?php echo !$gdrive_available ? 'disabled' : ''; ?>>
                    🔍 Cerca File
                </button>
                <button type="button" id="refresh-files-btn" class="disco747-btn disco747-btn-secondary" <?php echo !$gdrive_available ? 'disabled' : ''; ?>>
                    🔄 Aggiorna Lista
                </button>
            </div>

            <!-- Lista file Excel -->
            <div id="excel-files-container">
                <?php if ($gdrive_available): ?>
                <div class="loading-message">
                    <div class="disco747-spinner"></div>
                    <p>Caricamento file Excel da Google Drive...</p>
                </div>
                <?php else: ?>
                <div class="loading-message">
                    <p style="color: #666;">📂 Lista file non disponibile - Configura prima Google Drive</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Paginazione -->
            <div id="files-pagination" class="pagination-container" style="display: none;">
                <button type="button" id="prev-page-btn" class="disco747-btn disco747-btn-small" disabled>« Precedente</button>
                <span id="page-info" class="page-info">Pagina 1 di 1</span>
                <button type="button" id="next-page-btn" class="disco747-btn disco747-btn-small" disabled>Successiva »</button>
            </div>
        </div>
    </div>

    <!-- SEZIONE INPUT MANUALE -->
    <div class="disco747-card">
        <div class="card-header">
            <h2>⌨️ Input Manuale File ID (Test Avanzato)</h2>
        </div>
        <div class="card-body">
            <div class="manual-input-controls">
                <input type="text" id="manual-file-id" class="disco747-input" placeholder="Inserisci Google Drive File ID direttamente..." <?php echo !$gdrive_available ? 'disabled' : ''; ?>>
                <button type="button" id="analyze-manual-btn" class="disco747-btn disco747-btn-accent" <?php echo !$gdrive_available ? 'disabled' : ''; ?>>
                    🔍 Analizza File ID
                </button>
            </div>
            <div class="input-help">
                <p class="input-hint">
                    💡 <strong>Come ottenere il File ID:</strong>
                </p>
                <ol class="help-steps">
                    <li>Vai su Google Drive e apri il file Excel</li>
                    <li>Copia l'URL dalla barra degli indirizzi</li>
                    <li>Il File ID è la stringa lunga dopo <code>/d/</code> nell'URL</li>
                    <li>Esempio: <code>https://drive.google.com/file/d/<strong>1ABC123def456GHI789jkl</strong>/view</code></li>
                </ol>
            </div>
        </div>
    </div>

    <!-- SEZIONE RISULTATI ANALISI -->
    <div id="analysis-results" class="disco747-card" style="display: none;">
        <div class="card-header">
            <h2>📊 Risultati Analisi Excel</h2>
            <div class="header-actions">
                <button type="button" id="export-results-btn" class="disco747-btn disco747-btn-small">💾 Esporta JSON</button>
                <button type="button" id="clear-results-btn" class="disco747-btn disco747-btn-small">✖ Pulisci</button>
            </div>
        </div>
        <div class="card-body">
            <!-- Dashboard dati estratti -->
            <div id="extracted-data-dashboard" class="data-dashboard">
                <!-- Qui verranno inseriti i dati via JavaScript -->
            </div>
            
            <!-- Informazioni aggiuntive -->
            <div id="analysis-summary" class="analysis-summary" style="display: none;">
                <h3>📋 Riassunto Analisi</h3>
                <div class="summary-content">
                    <!-- Contenuto dinamico -->
                </div>
            </div>
        </div>
    </div>

    <!-- SEZIONE LOG DEBUG -->
    <div id="debug-log-section" class="disco747-card" style="display: none;">
        <div class="card-header">
            <h2>🔧 Log Debug Dettagliato</h2>
            <div class="header-actions">
                <button type="button" id="toggle-log-btn" class="disco747-btn disco747-btn-small">👁️ Mostra/Nascondi</button>
                <button type="button" id="copy-log-btn" class="disco747-btn disco747-btn-small">📋 Copia Log</button>
                <button type="button" id="download-log-btn" class="disco747-btn disco747-btn-small">💾 Scarica</button>
            </div>
        </div>
        <div class="card-body">
            <pre id="debug-log-content" class="debug-log"></pre>
            <div id="log-stats" class="log-stats" style="display: none;">
                <span class="stat-item">Righe: <span id="log-lines-count">0</span></span>
                <span class="stat-item">Errori: <span id="log-errors-count">0</span></span>
                <span class="stat-item">Warnings: <span id="log-warnings-count">0</span></span>
            </div>
        </div>
    </div>

    <!-- SEZIONE HELP & INFO -->
    <div class="disco747-card">
        <div class="card-header">
            <h2>❓ Informazioni e Template Supportati</h2>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-column">
                    <h3>🆕 Template NUOVO</h3>
                    <div class="template-info">
                        <p><strong>Riconoscimento:</strong> Cella B1 contiene "Menu"</p>
                        <ul class="field-list">
                            <li><code>B1</code> → Menu (es: Menu 747)</li>
                            <li><code>C6</code> → Data Evento</li>
                            <li><code>C7</code> → Tipo Evento</li>
                            <li><code>C8</code> → Numero Invitati</li>
                            <li><code>C27</code> → Importo Totale</li>
                            <li><code>F28</code> → Acconto</li>
                        </ul>
                    </div>
                </div>
                
                <div class="info-column">
                    <h3>📰 Template VECCHIO</h3>
                    <div class="template-info">
                        <p><strong>Riconoscimento:</strong> Cella B1 NON contiene "Menu"</p>
                        <ul class="field-list">
                            <li><code>C4</code> → Data Evento</li>
                            <li><code>C5</code> → Tipo Evento</li>
                            <li><code>A18</code> → Menu (parsing speciale)</li>
                            <li><code>C25</code> → Importo Totale</li>
                            <li><code>F23</code> → Acconto</li>
                        </ul>
                        <p><strong>Fallback Totale:</strong> Cerca in G15/H15 se C25 vuota</p>
                    </div>
                </div>
            </div>
            
            <div class="help-notes">
                <h4>🔧 Note Tecniche</h4>
                <ul>
                    <li><strong>Conversioni Date:</strong> Supporta seriali Excel e formati stringa comuni</li>
                    <li><strong>Parsing Menu:</strong> Converte pattern come "7-4" in "Menu 74"</li>
                    <li><strong>Pulizia Automatica:</strong> I file temporanei vengono sempre cancellati</li>
                    <li><strong>Timeout Download:</strong> 60 secondi per file di grandi dimensioni</li>
                    <li><strong>Sicurezza:</strong> Tutti gli input vengono sanitizzati e validati</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ALERT MESSAGES -->
    <div id="disco747-alerts"></div>
</div>

<!-- STYLES -->
<link rel="stylesheet" href="<?php echo DISCO747_CRM_ASSETS_URL; ?>css/excel-scan.css?v=<?php echo defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '1.0.0'; ?>">

<!-- JavaScript -->
<script>
// Passa dati PHP a JavaScript
window.disco747ExcelScanData = {
    nonce: '<?php echo $nonce; ?>',
    ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
    gdriveAvailable: <?php echo json_encode($gdrive_available); ?>,
    pluginVersion: '<?php echo defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.5.9'; ?>',
    strings: {
        analyzing: '<?php echo esc_js(__('Analisi in corso...', 'disco747')); ?>',
        error: '<?php echo esc_js(__('Errore', 'disco747')); ?>',
        success: '<?php echo esc_js(__('Successo', 'disco747')); ?>',
        loading: '<?php echo esc_js(__('Caricamento...', 'disco747')); ?>',
        noFileId: '<?php echo esc_js(__('Inserisci un File ID valido', 'disco747')); ?>',
        analyzing: '<?php echo esc_js(__('Analisi in corso', 'disco747')); ?>',
        connectionError: '<?php echo esc_js(__('Errore di connessione', 'disco747')); ?>',
        configureGdrive: '<?php echo esc_js(__('Configura prima Google Drive nelle Impostazioni', 'disco747')); ?>'
    }
};
</script>

<?php
// Carica il JavaScript se è disponibile Google Drive
if ($gdrive_available && file_exists(DISCO747_CRM_PLUGIN_DIR . 'assets/js/excel-scan.js')):
?>
<script src="<?php echo DISCO747_CRM_ASSETS_URL; ?>js/excel-scan.js?v=<?php echo defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '1.0.0'; ?>"></script>
<?php else: ?>
<script>
// JavaScript minimal per casi senza Google Drive
jQuery(document).ready(function($) {
    if (!window.disco747ExcelScanData.gdriveAvailable) {
        $('#disco747-status-bar .status-value').removeClass('checking').addClass('error');
        $('#files-count').text('N/D - Google Drive non configurato');
        
        // Disabilita controlli
        $('#excel-search, #manual-file-id, button[id*="btn"]').prop('disabled', true);
        
        // Mostra messaggio
        $('#disco747-alerts').html(
            '<div class="alert alert-error">❌ ' + 
            window.disco747ExcelScanData.strings.configureGdrive + 
            '</div>'
        );
    }
});
</script>
<?php endif; ?>

<style>
/* CSS Embedded per funzionamento autonomo */
:root {
    --disco747-gold: #FFD700;
    --disco747-dark-gold: #DAA520;
    --disco747-black: #1a1a1a;
    --disco747-gray: #333333;
    --disco747-light-gray: #666666;
    --disco747-white: #ffffff;
    --disco747-bg: #f8f9fa;
    --disco747-border: #ddd;
    --disco747-accent: #ff6b35;
    --disco747-success: #28a745;
    --disco747-error: #dc3545;
    --disco747-warning: #ffc107;
}

.disco747-excel-scan-wrap {
    background: var(--disco747-bg);
    padding: 20px;
    margin: 0;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.disco747-title {
    color: var(--disco747-black);
    font-size: 2.2em;
    margin: 0 0 10px 0;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 15px;
}

.disco747-logo {
    background: linear-gradient(135deg, var(--disco747-gold), var(--disco747-dark-gold));
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5em;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.disco747-subtitle {
    color: var(--disco747-light-gray);
    font-size: 1.1em;
    margin: 0 0 30px 0;
}

.disco747-status-bar {
    background: var(--disco747-black);
    color: var(--disco747-gold);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 25px;
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-label {
    font-weight: 600;
}

.status-value {
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.9em;
    font-weight: 500;
    transition: all 0.3s;
}

.status-value.checking {
    background: var(--disco747-light-gray);
    color: var(--disco747-white);
    animation: blink 1.5s infinite;
}

.status-value.connected {
    background: var(--disco747-success);
    color: var(--disco747-white);
}

.status-value.error {
    background: var(--disco747-error);
    color: var(--disco747-white);
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.5; }
}

.disco747-card {
    background: var(--disco747-white);
    border: 1px solid var(--disco747-border);
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s;
}

.disco747-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.card-header {
    background: linear-gradient(135deg, var(--disco747-black), var(--disco747-gray));
    color: var(--disco747-gold);
    padding: 15px 20px;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h2 {
    margin: 0;
    font-size: 1.3em;
    font-weight: 600;
    flex-grow: 1;
}

.header-actions {
    display: flex;
    gap: 8px;
}

.card-body {
    padding: 20px;
}

.disco747-input {
    width: 100%;
    max-width: 400px;
    padding: 10px 15px;
    border: 2px solid var(--disco747-border);
    border-radius: 6px;
    font-size: 1em;
    transition: all 0.3s;
}

.disco747-input:focus {
    outline: none;
    border-color: var(--disco747-gold);
    box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
}

.disco747-input:disabled {
    background: #f5f5f5;
    color: #999;
    cursor: not-allowed;
}

.disco747-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
    position: relative;
    overflow: hidden;
}

.disco747-btn:before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255,255,255,0.3);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.disco747-btn:active:before {
    width: 300px;
    height: 300px;
}

.disco747-btn-primary {
    background: linear-gradient(135deg, var(--disco747-gold), var(--disco747-dark-gold));
    color: var(--disco747-black);
}

.disco747-btn-primary:hover {
    background: var(--disco747-dark-gold);
    transform: translateY(-2px);
}

.disco747-btn-secondary {
    background: var(--disco747-gray);
    color: var(--disco747-white);
}

.disco747-btn-secondary:hover {
    background: var(--disco747-black);
}

.disco747-btn-accent {
    background: linear-gradient(135deg, var(--disco747-accent), #e55a2b);
    color: var(--disco747-white);
}

.disco747-btn-accent:hover {
    background: #e55a2b;
    transform: translateY(-2px);
}

.disco747-btn-small {
    padding: 6px 12px;
    font-size: 0.9em;
}

.disco747-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

.search-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: center;
}

.manual-input-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.input-hint, .input-help {
    color: var(--disco747-light-gray);
    font-size: 0.9em;
    margin: 10px 0;
}

.help-steps {
    margin: 10px 0 0 20px;
    font-size: 0.9em;
}

.help-steps li {
    margin-bottom: 5px;
}

.help-steps code {
    background: #f0f0f0;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: Monaco, Consolas, monospace;
}

.excel-files-list {
    border: 1px solid var(--disco747-border);
    border-radius: 6px;
    max-height: 500px;
    overflow-y: auto;
}

.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid var(--disco747-border);
    transition: background-color 0.2s;
}

.file-item:hover {
    background-color: #f8f9fa;
}

.file-item:last-child {
    border-bottom: none;
}

.file-info {
    flex-grow: 1;
}

.file-name {
    font-weight: 600;
    color: var(--disco747-black);
    margin-bottom: 4px;
}

.file-details {
    font-size: 0.9em;
    color: var(--disco747-light-gray);
}

.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-top: 20px;
    padding: 15px;
}

.page-info {
    color: var(--disco747-light-gray);
    font-weight: 500;
}

.data-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.data-item {
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border: 1px solid var(--disco747-border);
    border-radius: 6px;
    padding: 15px;
    text-align: center;
    transition: transform 0.2s;
}

.data-item:hover {
    transform: translateY(-2px);
}

.data-label {
    color: var(--disco747-light-gray);
    font-size: 0.9em;
    margin-bottom: 8px;
    font-weight: 600;
}

.data-value {
    color: var(--disco747-black);
    font-size: 1.2em;
    font-weight: 700;
}

.template-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 600;
    margin-left: 10px;
}

.template-nuovo {
    background: var(--disco747-success);
    color: white;
}

.template-vecchio {
    background: var(--disco747-warning);
    color: #212529;
}

.debug-log {
    background: #1e1e1e;
    color: #00ff00;
    padding: 15px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
    max-height: 500px;
    overflow-y: auto;
    white-space: pre-wrap;
    margin: 0;
    line-height: 1.4;
}

.log-stats {
    display: flex;
    gap: 15px;
    margin-top: 10px;
    font-size: 0.9em;
    color: var(--disco747-light-gray);
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.disco747-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--disco747-border);
    border-top: 4px solid var(--disco747-gold);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-message {
    text-align: center;
    padding: 40px;
    color: var(--disco747-light-gray);
}

.alert {
    padding: 15px;
    margin: 15px 0;
    border-radius: 6px;
    font-weight: 500;
    position: relative;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.info-column {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid var(--disco747-border);
}

.info-column h3 {
    margin: 0 0 10px 0;
    color: var(--disco747-black);
}

.template-info p {
    margin: 0 0 10px 0;
    font-weight: 600;
}

.field-list {
    margin: 10px 0;
    padding-left: 20px;
}

.field-list li {
    margin-bottom: 5px;
    font-family: Monaco, Consolas, monospace;
    font-size: 0.9em;
}

.field-list code {
    background: var(--disco747-gold);
    color: var(--disco747-black);
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: bold;
}

.help-notes {
    background: #e9ecef;
    padding: 15px;
    border-radius: 6px;
    margin-top: 20px;
}

.help-notes h4 {
    margin: 0 0 10px 0;
    color: var(--disco747-black);
}

.help-notes ul {
    margin: 0;
    padding-left: 20px;
}

.help-notes li {
    margin-bottom: 8px;
}

/* Responsive */
@media (max-width: 768px) {
    .disco747-excel-scan-wrap {
        padding: 15px;
    }
    
    .disco747-title {
        font-size: 1.8em;
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .search-controls, 
    .manual-input-controls,
    .header-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .disco747-input {
        max-width: 100%;
        margin-bottom: 10px;
    }
    
    .disco747-status-bar {
        flex-direction: column;
        gap: 10px;
    }
    
    .data-dashboard {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .pagination-container {
        flex-wrap: wrap;
        gap: 10px;
    }
}

@media (max-width: 480px) {
    .file-item {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .card-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .header-actions {
        justify-content: center;
    }
}

/* Print styles */
@media print {
    .disco747-excel-scan-wrap {
        background: white;
        padding: 0;
    }
    
    .disco747-btn,
    .search-controls,
    .manual-input-controls,
    .header-actions {
        display: none;
    }
    
    .disco747-card {
        box-shadow: none;
        border: 1px solid #ccc;
        break-inside: avoid;
    }
    
    .card-header {
        background: #f0f0f0 !important;
        color: black !important;
    }
}

/* Focus e accessibilità */
.disco747-btn:focus,
.disco747-input:focus {
    outline: 2px solid var(--disco747-gold);
    outline-offset: 2px;
}

/* Dark mode support (se l'utente ha preferenze scure) */
@media (prefers-color-scheme: dark) {
    :root {
        --disco747-bg: #1a1a1a;
        --disco747-white: #2d2d2d;
        --disco747-border: #444;
        --disco747-light-gray: #ccc;
    }
}
</style>