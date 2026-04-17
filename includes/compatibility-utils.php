<?php
/**
 * DEBUG: Controllo Database 747 Disco CRM
 * 
 * INSERISCI QUESTO CODICE temporaneamente nel file functions.php 
 * del tuo tema per verificare cosa sta causando l'errore database
 */

add_action('admin_init', 'debug_747disco_database_check');

function debug_747disco_database_check() {
    // Solo per admin
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Solo una volta per sessione
    if (get_transient('747disco_db_debug_done')) {
        return;
    }
    
    error_log('=== 747 DISCO DATABASE DEBUG START ===');
    
    global $wpdb;
    
    // 1. Verifica se la tabella esiste
    $table_name = $wpdb->prefix . 'preventivi_disco';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    error_log("Tabella $table_name esiste: " . ($table_exists ? 'SI' : 'NO'));
    
    if ($table_exists) {
        // 2. Conta record
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        error_log("Record in tabella: $count");
        
        // 3. Verifica struttura tabella
        $columns = $wpdb->get_results("DESCRIBE $table_name");
        error_log("Colonne tabella: " . print_r($columns, true));
        
        // 4. Test query semplice
        try {
            $test_query = $wpdb->get_results("SELECT * FROM $table_name LIMIT 1");
            error_log("Test query OK: " . count($test_query) . " risultati");
        } catch (Exception $e) {
            error_log("ERRORE test query: " . $e->getMessage());
        }
    }
    
    // 5. Verifica file plugin caricati
    $plugin_files = array(
        'core/class-disco747-database.php',
        'core/class-disco747-config.php',
        'compatibility-utils.php'
    );
    
    foreach ($plugin_files as $file) {
        $full_path = WP_PLUGIN_DIR . '/747disco-crm/includes/' . $file;
        $exists = file_exists($full_path);
        error_log("File $file: " . ($exists ? 'ESISTE' : 'MANCANTE'));
    }
    
    // 6. Memoria e performance
    $memory_usage = memory_get_usage(true) / 1024 / 1024;
    $memory_peak = memory_get_peak_usage(true) / 1024 / 1024;
    error_log("Memoria: {$memory_usage}MB / Peak: {$memory_peak}MB");
    
    error_log('=== 747 DISCO DATABASE DEBUG END ===');
    
    // Segna come fatto per non ripetere
    set_transient('747disco_db_debug_done', true, 300); // 5 minuti
}

// Per cancellare il debug, decommenta questa riga:
// delete_transient('747disco_db_debug_done');