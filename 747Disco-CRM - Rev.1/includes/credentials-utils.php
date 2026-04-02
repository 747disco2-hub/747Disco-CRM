<?php
/**
 * Utilities per gestione credenziali e verifica stato plugin
 * NUOVO FILE per completare le correzioni Google Drive
 * 
 * @package    Disco747_CRM
 * @subpackage Utils
 * @since      11.6.2-FIXED
 */

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Verifica se le credenziali Google Drive sono configurate
 * 
 * @return bool
 */
function disco747_has_google_credentials() {
    $client_id = get_option('disco747_googledrive_client_id');
    $client_secret = get_option('disco747_googledrive_client_secret');
    $refresh_token = get_option('disco747_googledrive_refresh_token');
    
    return !empty($client_id) && !empty($client_secret) && !empty($refresh_token);
}

/**
 * Ottieni stato dettagliato delle credenziali
 * 
 * @return array
 */
function disco747_get_credentials_status() {
    return [
        'has_client_id' => !empty(get_option('disco747_googledrive_client_id')),
        'has_client_secret' => !empty(get_option('disco747_googledrive_client_secret')),
        'has_refresh_token' => !empty(get_option('disco747_googledrive_refresh_token')),
        'folder_id' => get_option('disco747_googledrive_folder_id', ''),
        'is_configured' => disco747_has_google_credentials(),
        'storage_type' => get_option('disco747_storage_type', 'googledrive')
    ];
}

/**
 * Verifica se il plugin è correttamente configurato
 * 
 * @return array Stato configurazione
 */
function disco747_check_plugin_configuration() {
    $plugin = disco747_crm();
    $status = [
        'plugin_loaded' => false,
        'plugin_initialized' => false,
        'database_ok' => false,
        'storage_configured' => false,
        'google_drive_ok' => false,
        'errors' => [],
        'warnings' => []
    ];
    
    // Verifica plugin caricato
    if ($plugin) {
        $status['plugin_loaded'] = true;
        
        // Verifica inizializzazione
        if ($plugin->is_initialized()) {
            $status['plugin_initialized'] = true;
            
            // Verifica database
            $database = $plugin->get_database();
            if ($database && method_exists($database, 'test_connection')) {
                $status['database_ok'] = $database->test_connection();
            } else {
                $status['database_ok'] = true; // Assumi OK se arriviamo qui
            }
            
            // Verifica storage
            $storage_type = get_option('disco747_storage_type', 'googledrive');
            if ($storage_type === 'googledrive') {
                if (disco747_has_google_credentials()) {
                    $status['storage_configured'] = true;
                    
                    // Test Google Drive
                    try {
                        $storage_manager = $plugin->get_storage_manager();
                        if ($storage_manager) {
                            $googledrive = $storage_manager->get_googledrive();
                            if ($googledrive && method_exists($googledrive, 'test_connection')) {
                                $test_result = $googledrive->test_connection();
                                $status['google_drive_ok'] = $test_result['success'] ?? false;
                                if (!$status['google_drive_ok']) {
                                    $status['errors'][] = $test_result['message'] ?? 'Test Google Drive fallito';
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $status['errors'][] = 'Errore test Google Drive: ' . $e->getMessage();
                    }
                } else {
                    $status['warnings'][] = 'Credenziali Google Drive non configurate';
                }
            }
        } else {
            $status['errors'][] = 'Plugin non inizializzato correttamente';
        }
    } else {
        $status['errors'][] = 'Plugin non caricato';
    }
    
    return $status;
}

/**
 * Ottieni preventivi con fallback intelligente
 * 
 * @param array $options Opzioni
 * @return array
 */
function disco747_get_preventivi_smart($options = []) {
    $preventivi = [];
    $source = 'none';
    $error = '';
    
    $plugin = disco747_crm();
    if (!$plugin || !$plugin->is_initialized()) {
        return [
            'data' => [],
            'source' => 'error',
            'error' => 'Plugin non disponibile'
        ];
    }
    
    // Prova prima Google Drive se configurato
    if (disco747_has_google_credentials()) {
        try {
            $gdrive_sync = $plugin->get_gdrive_sync();
            if ($gdrive_sync && method_exists($gdrive_sync, 'is_available') && $gdrive_sync->is_available()) {
                $preventivi = $gdrive_sync->get_all_preventivi($options['use_cache'] ?? true);
                if (!empty($preventivi)) {
                    $source = 'google_drive';
                } else {
                    $error = 'Nessun preventivo trovato su Google Drive';
                }
            } else {
                $error = 'Sincronizzazione Google Drive non disponibile';
            }
        } catch (Exception $e) {
            $error = 'Errore Google Drive: ' . $e->getMessage();
        }
    }
    
    // Fallback al database se necessario
    if (empty($preventivi)) {
        try {
            $database = $plugin->get_database();
            if ($database) {
                $preventivi = $database->get_all_preventivi($options);
                if (!empty($preventivi)) {
                    $source = 'database';
                    if ($error) {
                        $error .= ' - Dati dal database locale';
                    }
                } else {
                    if (!$error) {
                        $error = 'Nessun preventivo nel database';
                    }
                }
            } else {
                $error = 'Database non disponibile';
            }
        } catch (Exception $e) {
            $error .= ' - Errore database: ' . $e->getMessage();
        }
    }
    
    return [
        'data' => $preventivi,
        'source' => $source,
        'error' => $error
    ];
}

/**
 * Ottieni statistiche con fallback intelligente
 * 
 * @return array
 */
function disco747_get_statistics_smart() {
    $result = disco747_get_preventivi_smart(['limit' => 0]); // Tutti i preventivi per statistiche
    $preventivi = $result['data'];
    
    $stats = [
        'total' => count($preventivi),
        'confirmed' => 0,
        'active' => 0,
        'cancelled' => 0,
        'this_month' => 0,
        'total_value' => 0,
        'source' => $result['source'],
        'error' => $result['error']
    ];
    
    if (empty($preventivi)) {
        return $stats;
    }
    
    $current_month = date('Y-m');
    
    foreach ($preventivi as $preventivo) {
        // Gestisci sia oggetti che array
        $stato = is_object($preventivo) ? $preventivo->stato : ($preventivo['stato'] ?? '');
        $importo = is_object($preventivo) ? $preventivo->importo_preventivo : ($preventivo['importo_preventivo'] ?? 0);
        $data_evento = is_object($preventivo) ? $preventivo->data_evento : ($preventivo['data_evento'] ?? '');
        
        // Conteggi stati
        switch (strtolower(trim($stato))) {
            case 'confermato':
                $stats['confirmed']++;
                break;
            case 'annullato':
                $stats['cancelled']++;
                break;
            default:
                $stats['active']++;
                break;
        }
        
        // Valore totale
        $stats['total_value'] += floatval($importo);
        
        // Questo mese
        if (substr($data_evento, 0, 7) === $current_month) {
            $stats['this_month']++;
        }
    }
    
    return $stats;
}

/**
 * Genera messaggio di stato per l'admin
 * 
 * @return string
 */
function disco747_get_admin_status_message() {
    $config = disco747_check_plugin_configuration();
    
    if (!$config['plugin_loaded'] || !$config['plugin_initialized']) {
        return '<div class="notice notice-error"><p><strong>747 Disco CRM:</strong> Plugin non funzionante. Contattare supporto.</p></div>';
    }
    
    if (!$config['storage_configured']) {
        $settings_url = admin_url('admin.php?page=disco747-settings');
        return '<div class="notice notice-warning"><p><strong>Configurazione richiesta:</strong> <a href="' . $settings_url . '">Configura le credenziali Google Drive</a> per sincronizzare i preventivi.</p></div>';
    }
    
    if (!$config['google_drive_ok'] && !empty($config['errors'])) {
        $settings_url = admin_url('admin.php?page=disco747-settings');
        return '<div class="notice notice-error"><p><strong>Errore Google Drive:</strong> ' . implode(', ', $config['errors']) . ' <a href="' . $settings_url . '">Ricontrolla impostazioni</a></p></div>';
    }
    
    if (!empty($config['warnings'])) {
        return '<div class="notice notice-info"><p><strong>Info:</strong> ' . implode(', ', $config['warnings']) . '</p></div>';
    }
    
    return ''; // Tutto OK, nessun messaggio
}

/**
 * Hook per mostrare notice admin
 */
function disco747_show_admin_notices() {
    // Solo nelle pagine del plugin
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'disco747') === false) {
        return;
    }
    
    echo disco747_get_admin_status_message();
}
add_action('admin_notices', 'disco747_show_admin_notices');

/**
 * Aggiungi script per debug nel footer admin
 */
function disco747_admin_debug_info() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'disco747') === false) {
        return;
    }
    
    if (defined('DISCO747_CRM_DEBUG') && DISCO747_CRM_DEBUG) {
        $config = disco747_check_plugin_configuration();
        echo '<script>console.log("747 Disco CRM Debug:", ' . json_encode($config) . ');</script>';
    }
}
add_action('admin_footer', 'disco747_admin_debug_info');

/**
 * Helper per logging unificato
 */
function disco747_log($message, $level = 'INFO') {
    if (defined('DISCO747_CRM_DEBUG') && DISCO747_CRM_DEBUG) {
        $timestamp = date('Y-m-d H:i:s');
        error_log("[{$timestamp}] [747Disco-CRM] [{$level}] {$message}");
    }
}

/**
 * Reset credenziali in caso di problemi
 */
function disco747_reset_google_credentials() {
    delete_option('disco747_googledrive_client_id');
    delete_option('disco747_googledrive_client_secret');
    delete_option('disco747_googledrive_refresh_token');
    delete_option('disco747_googledrive_folder_id');
    delete_option('disco747_gd_credentials');
    
    // Pulisci anche cache
    delete_transient('disco747_gdrive_preventivi_v2');
    
    disco747_log('Credenziali Google Drive resettate', 'WARNING');
    
    return true;
}

/**
 * Verifica e ripara configurazione se possibile
 */
function disco747_repair_configuration() {
    $repaired = [];
    $errors = [];
    
    try {
        // Verifica opzioni base
        if (empty(get_option('disco747_storage_type'))) {
            update_option('disco747_storage_type', 'googledrive');
            $repaired[] = 'Storage type impostato a Google Drive';
        }
        
        // Verifica tabelle database
        $plugin = disco747_crm();
        if ($plugin && $plugin->is_initialized()) {
            $database = $plugin->get_database();
            if ($database && method_exists($database, 'maybe_create_tables')) {
                $database->maybe_create_tables();
                $repaired[] = 'Tabelle database verificate';
            }
        }
        
        return [
            'success' => true,
            'repaired' => $repaired,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        return [
            'success' => false,
            'repaired' => $repaired,
            'errors' => $errors
        ];
    }
}