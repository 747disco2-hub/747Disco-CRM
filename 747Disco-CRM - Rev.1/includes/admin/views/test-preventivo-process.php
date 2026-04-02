<?php
/**
 * Test Diagnostico Processo Preventivo
 * Verifica ogni step del processo per identificare il problema
 */

if (!defined('ABSPATH')) {
    exit;
}

// Funzione helper per output colorato
function debug_output($message, $type = 'info') {
    $colors = [
        'success' => '#28a745',
        'error' => '#dc3545',
        'warning' => '#ffc107',
        'info' => '#17a2b8'
    ];
    $color = $colors[$type] ?? '#333';
    echo "<div style='padding: 10px; margin: 5px 0; background: {$color}; color: white; border-radius: 4px;'>{$message}</div>";
}

// Inizia test
echo '<div class="wrap">';
echo '<h1>🔍 Test Diagnostico Processo Preventivo</h1>';
echo '<div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px;">';

// TEST 1: Plugin principale
echo '<h2>1. TEST PLUGIN PRINCIPALE</h2>';
if (function_exists('disco747_crm')) {
    $plugin = disco747_crm();
    if ($plugin) {
        debug_output('✅ Plugin principale disponibile', 'success');
        
        // Verifica inizializzazione
        if (method_exists($plugin, 'is_initialized') && $plugin->is_initialized()) {
            debug_output('✅ Plugin inizializzato correttamente', 'success');
        } else {
            debug_output('⚠️ Plugin non completamente inizializzato', 'warning');
        }
    } else {
        debug_output('❌ Plugin principale non istanziato', 'error');
    }
} else {
    debug_output('❌ Funzione disco747_crm() non trovata', 'error');
}

// TEST 2: Componenti
echo '<h2>2. TEST COMPONENTI</h2>';
if (isset($plugin)) {
    // Database
    if (method_exists($plugin, 'get_database')) {
        $database = $plugin->get_database();
        if ($database) {
            debug_output('✅ Database component: OK', 'success');
            
            // Test tabella
            global $wpdb;
            $table = $wpdb->prefix . 'disco747_preventivi';
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if ($exists) {
                debug_output("✅ Tabella {$table} esiste", 'success');
            } else {
                debug_output("❌ Tabella {$table} NON esiste", 'error');
            }
        } else {
            debug_output('❌ Database component: NULL', 'error');
        }
    } else {
        debug_output('❌ Metodo get_database() non esiste', 'error');
    }
    
    // Excel Generator
    if (method_exists($plugin, 'get_excel')) {
        $excel = $plugin->get_excel();
        if ($excel) {
            debug_output('✅ Excel generator: OK', 'success');
            
            // Verifica metodi
            if (method_exists($excel, 'generate_excel')) {
                debug_output('✅ Metodo generate_excel() esiste', 'success');
            } else {
                debug_output('❌ Metodo generate_excel() NON esiste', 'error');
            }
            
            // Test PhpSpreadsheet
            if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
                debug_output('✅ PhpSpreadsheet disponibile', 'success');
            } else {
                debug_output('⚠️ PhpSpreadsheet non caricato', 'warning');
                
                // Cerca vendor
                $vendor_paths = [
                    WP_PLUGIN_DIR . '/747disco-crm/vendor/autoload.php',
                    plugin_dir_path(dirname(__FILE__)) . '../../vendor/autoload.php',
                ];
                foreach ($vendor_paths as $path) {
                    if (file_exists($path)) {
                        debug_output("📁 Vendor trovato in: {$path}", 'info');
                        require_once $path;
                        break;
                    }
                }
            }
        } else {
            debug_output('❌ Excel generator: NULL', 'error');
        }
    } else {
        debug_output('❌ Metodo get_excel() non esiste', 'error');
    }
    
    // PDF Generator
    if (method_exists($plugin, 'get_pdf')) {
        $pdf = $plugin->get_pdf();
        if ($pdf) {
            debug_output('✅ PDF generator: OK', 'success');
            
            if (method_exists($pdf, 'generate_pdf')) {
                debug_output('✅ Metodo generate_pdf() esiste', 'success');
            } else {
                debug_output('❌ Metodo generate_pdf() NON esiste', 'error');
            }
        } else {
            debug_output('❌ PDF generator: NULL', 'error');
        }
    } else {
        debug_output('❌ Metodo get_pdf() non esiste', 'error');
    }
    
    // Storage Manager
    if (method_exists($plugin, 'get_storage_manager')) {
        $storage = $plugin->get_storage_manager();
        if ($storage) {
            debug_output('✅ Storage Manager: OK', 'success');
            
            // Test connessione
            if (method_exists($storage, 'test_connection')) {
                $connected = $storage->test_connection();
                if ($connected) {
                    debug_output('✅ Storage connesso (Google Drive/Dropbox)', 'success');
                } else {
                    debug_output('⚠️ Storage non connesso', 'warning');
                }
            }
            
            // Verifica metodo upload
            if (method_exists($storage, 'upload_file')) {
                debug_output('✅ Metodo upload_file() esiste', 'success');
            } else {
                debug_output('❌ Metodo upload_file() NON esiste', 'error');
            }
        } else {
            debug_output('❌ Storage Manager: NULL', 'error');
        }
    } else {
        debug_output('❌ Metodo get_storage_manager() non esiste', 'error');
    }
    
    // Forms Handler
    if (method_exists($plugin, 'get_forms')) {
        $forms = $plugin->get_forms();
        if ($forms) {
            debug_output('✅ Forms Handler: OK', 'success');
        } else {
            debug_output('❌ Forms Handler: NULL', 'error');
        }
    } else {
        debug_output('❌ Metodo get_forms() non esiste', 'error');
    }
}

// TEST 3: Directory e permessi
echo '<h2>3. TEST DIRECTORY E PERMESSI</h2>';

$upload_dir = wp_upload_dir();
$preventivi_dir = $upload_dir['basedir'] . '/preventivi/';

if (is_dir($preventivi_dir)) {
    debug_output("✅ Directory preventivi esiste: {$preventivi_dir}", 'success');
    
    if (is_writable($preventivi_dir)) {
        debug_output('✅ Directory scrivibile', 'success');
    } else {
        debug_output('❌ Directory NON scrivibile', 'error');
    }
} else {
    debug_output("⚠️ Directory preventivi non esiste: {$preventivi_dir}", 'warning');
    
    // Prova a crearla
    if (wp_mkdir_p($preventivi_dir)) {
        debug_output('✅ Directory creata con successo', 'success');
    } else {
        debug_output('❌ Impossibile creare directory', 'error');
    }
}

// TEST 4: Google Drive Credentials
echo '<h2>4. TEST GOOGLE DRIVE</h2>';

// Verifica token OAuth
$google_token = get_option('disco747_google_token');
if ($google_token) {
    debug_output('✅ Token Google salvato', 'success');
    
    // Decodifica token
    $token_data = json_decode($google_token, true);
    if ($token_data) {
        if (isset($token_data['access_token'])) {
            debug_output('✅ Access token presente', 'success');
        }
        if (isset($token_data['refresh_token'])) {
            debug_output('✅ Refresh token presente', 'success');
        }
        if (isset($token_data['expires_in'])) {
            $expires = $token_data['created'] + $token_data['expires_in'];
            if ($expires > time()) {
                debug_output('✅ Token valido', 'success');
            } else {
                debug_output('⚠️ Token scaduto, necessario refresh', 'warning');
            }
        }
    }
} else {
    debug_output('❌ Nessun token Google salvato', 'error');
}

// TEST 5: Test pratico generazione file
echo '<h2>5. TEST GENERAZIONE FILE</h2>';

if (isset($excel) && $excel) {
    // Dati di test
    $test_data = [
        'nome_referente' => 'Test',
        'cognome_referente' => 'Cliente',
        'data_evento' => date('Y-m-d'),
        'tipo_evento' => 'Test Evento',
        'tipo_menu' => 'Menu 747',
        'numero_invitati' => 50,
        'cellulare' => '333 1234567',
        'mail' => 'test@example.com',
        'importo_preventivo' => 1000,
        'acconto' => 300,
        'omaggio1' => 'Test omaggio',
        'extra1_descrizione' => 'Extra test',
        'extra1_importo' => 100
    ];
    
    debug_output('📝 Tentativo generazione Excel con dati test...', 'info');
    
    try {
        if (method_exists($excel, 'generate_excel')) {
            $result = $excel->generate_excel($test_data);
            if ($result) {
                if (is_string($result) && file_exists($result)) {
                    debug_output("✅ Excel generato: {$result}", 'success');
                    debug_output("📏 Dimensione file: " . filesize($result) . " bytes", 'info');
                    
                    // Cleanup
                    @unlink($result);
                    debug_output("🧹 File test eliminato", 'info');
                } else {
                    debug_output("❌ Excel non generato correttamente. Result: " . print_r($result, true), 'error');
                }
            } else {
                debug_output('❌ generate_excel() ha restituito false/null', 'error');
            }
        }
    } catch (Exception $e) {
        debug_output('❌ Errore: ' . $e->getMessage(), 'error');
    }
}

// TEST 6: AJAX Handler
echo '<h2>6. TEST AJAX HANDLER</h2>';

// Verifica se l'action è registrato
global $wp_filter;
if (isset($wp_filter['wp_ajax_disco747_save_preventivo'])) {
    debug_output('✅ AJAX handler registrato per disco747_save_preventivo', 'success');
    
    $callbacks = $wp_filter['wp_ajax_disco747_save_preventivo']->callbacks;
    foreach ($callbacks as $priority => $hooks) {
        foreach ($hooks as $hook) {
            if (is_array($hook['function'])) {
                $class = is_object($hook['function'][0]) ? get_class($hook['function'][0]) : $hook['function'][0];
                $method = $hook['function'][1];
                debug_output("📌 Hook: {$class}::{$method} (priorità: {$priority})", 'info');
            }
        }
    }
} else {
    debug_output('❌ AJAX handler NON registrato', 'error');
}

// TEST 7: Error Log recenti
echo '<h2>7. ULTIMI ERRORI DAL LOG</h2>';

$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file)) {
    $lines = file($log_file);
    $recent_lines = array_slice($lines, -20); // Ultime 20 righe
    
    $disco_errors = array_filter($recent_lines, function($line) {
        return strpos($line, '747Disco') !== false || strpos($line, 'disco747') !== false;
    });
    
    if (!empty($disco_errors)) {
        echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">';
        foreach ($disco_errors as $error) {
            echo htmlspecialchars($error);
        }
        echo '</pre>';
    } else {
        debug_output('Nessun errore recente del plugin trovato', 'info');
    }
} else {
    debug_output('File debug.log non trovato', 'info');
}

echo '</div></div>';

// Aggiungi pulsante test live
?>
<div style="margin-top: 20px; padding: 20px; background: white; border-radius: 8px;">
    <h3>Test Live Creazione Preventivo</h3>
    <button id="test-live-process" class="button button-primary button-hero">
        🚀 Esegui Test Live
    </button>
    <div id="test-results" style="margin-top: 20px;"></div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#test-live-process').on('click', function() {
        var $button = $(this);
        var $results = $('#test-results');
        
        $button.prop('disabled', true).text('Testing...');
        $results.html('<div style="padding: 10px; background: #17a2b8; color: white; border-radius: 4px;">🔄 Test in corso...</div>');
        
        // Prepara dati test
        var testData = new FormData();
        testData.append('action', 'disco747_save_preventivo');
        testData.append('nonce', '<?php echo wp_create_nonce('disco747_form_nonce'); ?>');
        testData.append('nome_referente', 'Test');
        testData.append('cognome_referente', 'Debug');
        testData.append('data_evento', '<?php echo date('Y-m-d'); ?>');
        testData.append('tipo_evento', 'Test Diagnostico');
        testData.append('tipo_menu', 'Menu 747');
        testData.append('numero_invitati', '50');
        testData.append('cellulare', '333 1234567');
        testData.append('mail', 'test@debug.com');
        testData.append('importo_preventivo', '1000');
        testData.append('acconto', '300');
        testData.append('omaggio1', 'Test omaggio');
        testData.append('extra1_descrizione', 'Extra test');
        testData.append('extra1_importo', '100');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: testData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Response:', response);
                
                if (response.success) {
                    var html = '<div style="padding: 10px; background: #28a745; color: white; border-radius: 4px;">✅ Test completato con successo!</div>';
                    html += '<div style="margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 4px;">';
                    html += '<strong>Risultati:</strong><br>';
                    html += 'ID Preventivo: ' + response.data.preventivo_id + '<br>';
                    
                    if (response.data.urls) {
                        html += '<strong>URLs generati:</strong><br>';
                        if (response.data.urls.excel_url) {
                            html += '📊 Excel: ' + response.data.urls.excel_url + '<br>';
                        }
                        if (response.data.urls.pdf_url) {
                            html += '📄 PDF: ' + response.data.urls.pdf_url + '<br>';
                        }
                    } else {
                        html += '⚠️ Nessun URL generato<br>';
                    }
                    html += '</div>';
                    
                    $results.html(html);
                } else {
                    $results.html('<div style="padding: 10px; background: #dc3545; color: white; border-radius: 4px;">❌ Errore: ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr.responseText);
                $results.html('<div style="padding: 10px; background: #dc3545; color: white; border-radius: 4px;">❌ Errore AJAX: ' + error + '<br><pre>' + xhr.responseText + '</pre></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('🚀 Esegui Test Live');
            }
        });
    });
});
</script>