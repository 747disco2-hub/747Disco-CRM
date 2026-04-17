<?php
/**
 * Configurazione Timeout Centralizzata - 747 Disco CRM
 * 
 * Questo file contiene tutte le configurazioni dei timeout per scansioni massive
 * 
 * @package    Disco747_CRM
 * @version    1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// ============================================================================
// TIMEOUT CONFIGURATION
// ============================================================================

/**
 * Timeout PHP per scansioni batch
 * Aumenta questo valore se hai centinaia/migliaia di file da scansionare
 */
define('DISCO747_SCAN_PHP_TIMEOUT', 900); // 15 minuti (default)

/**
 * Timeout JavaScript AJAX
 * Deve essere >= PHP timeout
 */
define('DISCO747_SCAN_JS_TIMEOUT', 900000); // 15 minuti in millisecondi

/**
 * Timeout richieste HTTP a Google Drive API
 */
define('DISCO747_GDRIVE_API_TIMEOUT', 120); // 2 minuti per singola richiesta

/**
 * Rate limiting tra file (microsecondi)
 * Riduci per velocizzare, aumenta se Google Drive restituisce errori rate-limit
 */
define('DISCO747_RATE_LIMIT_USLEEP', 50000); // 50ms (ottimizzato per evitare timeout server)

/**
 * Timeout refresh token OAuth
 */
define('DISCO747_OAUTH_TIMEOUT', 120); // 2 minuti

/**
 * Memory limit per scansioni massive
 */
define('DISCO747_MEMORY_LIMIT', '512M');

// ============================================================================
// FUNZIONI HELPER
// ============================================================================

/**
 * Applica configurazione timeout per scansioni batch
 * 
 * @param int $custom_timeout Timeout custom in secondi (opzionale)
 */
function disco747_set_scan_timeout($custom_timeout = null) {
    $timeout = $custom_timeout ?? DISCO747_SCAN_PHP_TIMEOUT;
    
    @set_time_limit($timeout);
    @ini_set('max_execution_time', $timeout);
    @ini_set('memory_limit', DISCO747_MEMORY_LIMIT);
    
    error_log("[747Disco-Config] Timeout impostato: {$timeout}s, Memory: " . DISCO747_MEMORY_LIMIT);
}

/**
 * Ottieni configurazione timeout per JavaScript
 * 
 * @return array Configurazione timeout
 */
function disco747_get_js_timeout_config() {
    return array(
        'ajax_timeout' => DISCO747_SCAN_JS_TIMEOUT,
        'php_timeout' => DISCO747_SCAN_PHP_TIMEOUT,
        'rate_limit' => DISCO747_RATE_LIMIT_USLEEP / 1000, // Converti in ms
        'display_timeout' => gmdate('i:s', DISCO747_SCAN_PHP_TIMEOUT) // Formato mm:ss
    );
}

/**
 * Mostra info timeout nella pagina admin
 * 
 * @return string HTML info timeout
 */
function disco747_render_timeout_info() {
    $php_timeout = DISCO747_SCAN_PHP_TIMEOUT / 60; // Minuti
    $js_timeout = DISCO747_SCAN_JS_TIMEOUT / 60000; // Minuti
    
    return sprintf(
        '<div class="notice notice-info" style="margin: 20px 0;">
            <p>
                <strong>⏱️ Configurazione Timeout:</strong><br>
                • Timeout PHP: <code>%d minuti</code><br>
                • Timeout AJAX: <code>%d minuti</code><br>
                • Rate Limiting: <code>%dms</code> tra file<br>
                • Memory Limit: <code>%s</code>
            </p>
            <p style="color: #666; font-size: 12px;">
                ℹ️ Per scansioni con centinaia di file, modifica i valori in <code>includes/config-timeouts.php</code>
            </p>
        </div>',
        $php_timeout,
        $js_timeout,
        DISCO747_RATE_LIMIT_USLEEP / 1000,
        DISCO747_MEMORY_LIMIT
    );
}
