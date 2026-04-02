<?php
/**
 * DEBUG PREVENTIVI 747 DISCO CRM
 * 
 * ISTRUZIONI:
 * 1. Salva questo file come 'debug-preventivi.php' nella cartella del plugin
 * 2. Aggiungi al browser: /wp-admin/admin.php?page=disco747-debug-test
 * 3. Esegui i test per identificare esattamente dove si blocca il salvataggio
 */

// Sicurezza WordPress
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// Aggiungi pagina di debug temporanea
add_action('admin_menu', function() {
    add_submenu_page(
        'disco747-crm',
        'Debug Preventivi',
        'Debug Test',
        'manage_options',
        'disco747-debug-test',
        'disco747_debug_test_page'
    );
});

function disco747_debug_test_page() {
    ?>
    <div class="wrap">
        <h1>🔧 Debug Preventivi 747 Disco CRM</h1>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #00a0d2;">
            <h3>🎯 Test di Diagnostica</h3>
            <p>Questi test identificheranno esattamente dove si blocca il salvataggio preventivi.</p>
        </div>

        <?php
        if (isset($_GET['test'])) {
            $test_type = sanitize_text_field($_GET['test']);
            disco747_run_debug_test($test_type);
        } else {
            disco747_show_debug_menu();
        }
        ?>
    </div>
    <?php
}

function disco747_show_debug_menu() {
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
        
        <!-- Test 1: Verifica Database -->
        <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
            <h3>🗄️ Test 1: Database</h3>
            <p>Verifica esistenza tabella e struttura campi</p>
            <a href="?page=disco747-debug-test&test=database" class="button button-primary">Esegui Test Database</a>
        </div>

        <!-- Test 2: Verifica Handler AJAX -->
        <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
            <h3>⚡ Test 2: Handler AJAX</h3>
            <p>Identifica conflitti tra handler AJAX</p>
            <a href="?page=disco747-debug-test&test=ajax" class="button button-primary">Esegui Test AJAX</a>
        </div>

        <!-- Test 3: Verifica Componenti -->
        <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
            <h3>🔧 Test 3: Componenti</h3>
            <p>Verifica caricamento Database, Excel, PDF</p>
            <a href="?page=disco747-debug-test&test=components" class="button button-primary">Esegui Test Componenti</a>
        </div>

        <!-- Test 4: Test Salvataggio -->
        <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
            <h3>💾 Test 4: Salvataggio</h3>
            <p>Simula salvataggio preventivo completo</p>
            <a href="?page=disco747-debug-test&test=save" class="button button-primary">Esegui Test Salvataggio</a>
        </div>

        <!-- Test 5: Test Form Data -->
        <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
            <h3>📝 Test 5: Dati Form</h3>
            <p>Verifica mapping campi form → database</p>
            <a href="?page=disco747-debug-test&test=formdata" class="button button-primary">Esegui Test Form Data</a>
        </div>

        <!-- Test 6: Storage Test -->
        <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
            <h3>☁️ Test 6: Storage</h3>
            <p>Verifica connessione Google Drive</p>
            <a href="?page=disco747-debug-test&test=storage" class="button button-primary">Esegui Test Storage</a>
        </div>
    </div>
    <?php
}

function disco747_run_debug_test($test_type) {
    echo "<div style='background: #f1f1f1; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h3>🔍 Risultati Test: " . strtoupper($test_type) . "</h3>";
    
    switch ($test_type) {
        case 'database':
            disco747_test_database();
            break;
        case 'ajax':
            disco747_test_ajax_handlers();
            break;
        case 'components':
            disco747_test_components();
            break;
        case 'save':
            disco747_test_save_process();
            break;
        case 'formdata':
            disco747_test_form_mapping();
            break;
        case 'storage':
            disco747_test_storage();
            break;
        default:
            echo "<p style='color: red;'>❌ Test non riconosciuto</p>";
    }
    
    echo "</div>";
    echo "<p><a href='?page=disco747-debug-test' class='button'>← Torna ai Test</a></p>";
}

function disco747_test_database() {
    global $wpdb;
    
    echo "<h4>🗄️ Test Database</h4>";
    
    // Test 1: Verifica esistenza tabella
    $table_name = $wpdb->prefix . 'disco747_preventivi';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    
    if ($table_exists) {
        echo "<p style='color: green;'>✅ Tabella $table_name ESISTE</p>";
        
        // Test 2: Conta record
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo "<p>📊 Record presenti: <strong>$count</strong></p>";
        
        // Test 3: Verifica colonne
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        echo "<h5>📋 Colonne Tabella:</h5>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li><strong>{$column->Field}</strong> ({$column->Type}) - Default: {$column->Default}</li>";
        }
        echo "</ul>";
        
        // Test 4: Verifica colonne chiave
        $required_columns = ['preventivo_id', 'nome_referente', 'mail', 'importo_totale', 'tipo_menu'];
        $missing_columns = [];
        
        $existing_columns = array_column($columns, 'Field');
        foreach ($required_columns as $req_col) {
            if (!in_array($req_col, $existing_columns)) {
                $missing_columns[] = $req_col;
            }
        }
        
        if (empty($missing_columns)) {
            echo "<p style='color: green;'>✅ Tutte le colonne richieste sono presenti</p>";
        } else {
            echo "<p style='color: red;'>❌ Colonne mancanti: " . implode(', ', $missing_columns) . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Tabella $table_name NON ESISTE</p>";
    }
    
    // Test 5: Verifica permessi database
    try {
        $test_insert = $wpdb->query("INSERT INTO $table_name (preventivo_id, nome_referente, mail, created_at) VALUES ('TEST', 'Test User', 'test@test.com', NOW())");
        if ($test_insert) {
            echo "<p style='color: green;'>✅ Permessi INSERT funzionanti</p>";
            // Rimuovi record di test
            $wpdb->query("DELETE FROM $table_name WHERE preventivo_id = 'TEST'");
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Errore permessi INSERT: " . $e->getMessage() . "</p>";
    }
}

function disco747_test_ajax_handlers() {
    echo "<h4>⚡ Test Handler AJAX</h4>";
    
    // Verifica se ci sono hook multipli registrati
    global $wp_filter;
    
    $ajax_action = 'wp_ajax_disco747_save_preventivo';
    
    if (isset($wp_filter[$ajax_action])) {
        $handlers = $wp_filter[$ajax_action]->callbacks;
        $handler_count = 0;
        
        echo "<h5>🔍 Handler registrati per 'disco747_save_preventivo':</h5>";
        echo "<ul>";
        
        foreach ($handlers as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $handler_count++;
                $callback_info = disco747_get_callback_info($callback['function']);
                echo "<li><strong>Priorità $priority:</strong> $callback_info</li>";
            }
        }
        echo "</ul>";
        
        if ($handler_count > 1) {
            echo "<p style='color: red;'>❌ CONFLITTO: Trovati $handler_count handler per la stessa azione!</p>";
            echo "<p><strong>SOLUZIONE:</strong> Rimuovi uno dei due handler duplicati.</p>";
        } else {
            echo "<p style='color: green;'>✅ Un solo handler registrato (corretto)</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Nessun handler AJAX registrato per 'disco747_save_preventivo'</p>";
    }
    
    // Verifica nonce
    $nonce = wp_create_nonce('disco747_admin_nonce');
    echo "<p>🔒 Nonce generato: <code>$nonce</code></p>";
}

function disco747_get_callback_info($callback) {
    if (is_array($callback)) {
        if (is_object($callback[0])) {
            return get_class($callback[0]) . '::' . $callback[1];
        } else {
            return $callback[0] . '::' . $callback[1];
        }
    } elseif (is_string($callback)) {
        return $callback;
    } else {
        return 'Callback sconosciuto';
    }
}

function disco747_test_components() {
    echo "<h4>🔧 Test Componenti</h4>";
    
    // Test caricamento istanza principale
    try {
        if (function_exists('disco747_crm')) {
            $disco747_crm = disco747_crm();
            if ($disco747_crm && $disco747_crm->is_initialized()) {
                echo "<p style='color: green;'>✅ Plugin principale inizializzato</p>";
                
                // Test componenti individuali
                $components = [
                    'database' => 'get_database',
                    'config' => 'get_config', 
                    'storage_manager' => 'get_storage_manager',
                    'pdf' => 'get_pdf',
                    'excel' => 'get_excel'
                ];
                
                foreach ($components as $name => $method) {
                    try {
                        $component = method_exists($disco747_crm, $method) ? $disco747_crm->$method() : null;
                        if ($component) {
                            echo "<p style='color: green;'>✅ Componente '$name' caricato: " . get_class($component) . "</p>";
                        } else {
                            echo "<p style='color: orange;'>⚠️ Componente '$name' non disponibile</p>";
                        }
                    } catch (Exception $e) {
                        echo "<p style='color: red;'>❌ Errore caricamento '$name': " . $e->getMessage() . "</p>";
                    }
                }
                
            } else {
                echo "<p style='color: red;'>❌ Plugin principale non inizializzato</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Funzione disco747_crm() non disponibile</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Errore test componenti: " . $e->getMessage() . "</p>";
    }
}

function disco747_test_save_process() {
    echo "<h4>💾 Test Processo Salvataggio</h4>";
    
    // Simula dati form
    $test_data = [
        'preventivo_id' => 'TEST-' . time(),
        'nome_referente' => 'Mario',
        'cognome_referente' => 'Rossi',
        'mail' => 'mario.rossi@test.com',
        'cellulare' => '3331234567',
        'data_evento' => date('Y-m-d', strtotime('+30 days')),
        'tipo_evento' => 'Compleanno Test',
        'tipo_menu' => 'Menu 747',
        'numero_invitati' => 50,
        'importo_totale' => 1500.00,
        'acconto' => 300.00,
        'stato' => 'attivo',
        'confermato' => 0
    ];
    
    echo "<h5>📝 Dati di Test:</h5>";
    echo "<pre style='background: #f9f9f9; padding: 10px; border-radius: 5px;'>";
    print_r($test_data);
    echo "</pre>";
    
    // Test 1: Verifica classe Database
    try {
        if (class_exists('Disco747_CRM\Core\Disco747_Database')) {
            $database = new \Disco747_CRM\Core\Disco747_Database();
            echo "<p style='color: green;'>✅ Classe Database caricata</p>";
            
            // Test 2: Inserimento
            $result = $database->insert_preventivo($test_data);
            if ($result) {
                echo "<p style='color: green;'>✅ Preventivo test inserito con ID: $result</p>";
                
                // Test 3: Lettura
                $saved_data = $database->get_preventivo($result);
                if ($saved_data) {
                    echo "<p style='color: green;'>✅ Preventivo letto dal database</p>";
                    
                    // Test 4: Eliminazione test
                    $database->delete_preventivo($result);
                    echo "<p style='color: blue;'>🗑️ Preventivo test eliminato</p>";
                } else {
                    echo "<p style='color: red;'>❌ Impossibile leggere preventivo salvato</p>";
                }
                
            } else {
                echo "<p style='color: red;'>❌ Errore inserimento preventivo test</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ Classe Database non trovata</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Errore test salvataggio: " . $e->getMessage() . "</p>";
    }
}

function disco747_test_form_mapping() {
    echo "<h4>📝 Test Mapping Form → Database</h4>";
    
    // Campi aspettati dal form
    $form_fields = [
        'nome_referente', 'cognome_referente', 'cellulare', 'mail',
        'data_evento', 'tipo_evento', 'tipo_menu', 'numero_invitati',
        'orario_evento', 'importo_totale', 'acconto',
        'omaggio1', 'omaggio2', 'omaggio3',
        'extra1_descrizione', 'extra1_importo',
        'extra2_descrizione', 'extra2_importo', 
        'extra3_descrizione', 'extra3_importo',
        'note_aggiuntive'
    ];
    
    // Verifica campi database
    global $wpdb;
    $table_name = $wpdb->prefix . 'disco747_preventivi';
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $db_fields = array_column($columns, 'Field');
    
    echo "<h5>✅ Campi Form → Database Mapping:</h5>";
    echo "<table style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f1f1f1;'><th style='border: 1px solid #ddd; padding: 8px;'>Campo Form</th><th style='border: 1px solid #ddd; padding: 8px;'>Presente in DB</th></tr>";
    
    foreach ($form_fields as $field) {
        $exists = in_array($field, $db_fields);
        $status = $exists ? "<span style='color: green;'>✅ SI</span>" : "<span style='color: red;'>❌ NO</span>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>$field</td><td style='border: 1px solid #ddd; padding: 8px;'>$status</td></tr>";
    }
    echo "</table>";
}

function disco747_test_storage() {
    echo "<h4>☁️ Test Storage (Google Drive)</h4>";
    
    // Verifica credenziali Google Drive
    $gd_credentials = get_option('disco747_gd_credentials', array());
    
    if (!empty($gd_credentials['client_id'])) {
        echo "<p style='color: green;'>✅ Client ID presente</p>";
    } else {
        echo "<p style='color: red;'>❌ Client ID mancante</p>";
    }
    
    if (!empty($gd_credentials['client_secret'])) {
        echo "<p style='color: green;'>✅ Client Secret presente</p>";
    } else {
        echo "<p style='color: red;'>❌ Client Secret mancante</p>";
    }
    
    if (!empty($gd_credentials['refresh_token'])) {
        echo "<p style='color: green;'>✅ Refresh Token presente</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Refresh Token mancante (autorizzazione richiesta)</p>";
    }
    
    // Test Storage Manager
    try {
        if (function_exists('disco747_crm')) {
            $disco747_crm = disco747_crm();
            $storage_manager = $disco747_crm->get_storage_manager();
            
            if ($storage_manager) {
                echo "<p style='color: green;'>✅ Storage Manager disponibile</p>";
                
                // Test connessione (se possibile)
                if (method_exists($storage_manager, 'test_connection')) {
                    $test_result = $storage_manager->test_connection();
                    if ($test_result) {
                        echo "<p style='color: green;'>✅ Connessione Google Drive OK</p>";
                    } else {
                        echo "<p style='color: red;'>❌ Connessione Google Drive fallita</p>";
                    }
                }
            } else {
                echo "<p style='color: red;'>❌ Storage Manager non disponibile</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Errore test storage: " . $e->getMessage() . "</p>";
    }
}