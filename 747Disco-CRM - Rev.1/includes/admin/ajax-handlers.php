<?php
/**
 * AJAX Handlers per 747 Disco CRM
 * Gestisce preventivi, funnel email e cambio stato
 * 
 * @package    Disco747_CRM
 * @subpackage Admin
 * @version    2.0.0
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// ============================================================================
// HANDLER PREVENTIVI
// ============================================================================

/**
 * AJAX: Aggiorna stato preventivo
 */
add_action('wp_ajax_disco747_update_preventivo_status', 'disco747_ajax_update_preventivo_status');
function disco747_ajax_update_preventivo_status() {
    check_ajax_referer('disco747_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
    
    $preventivo_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    
    if (!$preventivo_id || !$new_status) {
        wp_send_json_error('Parametri mancanti');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'disco747_preventivi';
    
    // Carica preventivo corrente
    $preventivo = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $preventivo_id
    ), ARRAY_A);
    
    if (!$preventivo) {
        wp_send_json_error('Preventivo non trovato');
    }
    
    $old_status = $preventivo['stato'];
    $pdf_url = $preventivo['pdf_url'];
    $excel_url = $preventivo['excel_url'];
    $googledrive_file_id = $preventivo['googledrive_file_id'];
    
    // Aggiorna stato nel database
    $updated = $wpdb->update(
        $table_name,
        array('stato' => $new_status),
        array('id' => $preventivo_id),
        array('%s'),
        array('%d')
    );
    
    if ($updated === false) {
        wp_send_json_error('Errore aggiornamento database');
    }
    
    // Gestione rinominazione file
    $files_renamed = array();
    
    // Rinomina PDF se esiste
    if (!empty($pdf_url) && file_exists($pdf_url)) {
        $new_pdf_path = disco747_rename_file_by_status($pdf_url, $old_status, $new_status);
        if ($new_pdf_path && $new_pdf_path !== $pdf_url) {
            $wpdb->update(
                $table_name,
                array('pdf_url' => $new_pdf_path),
                array('id' => $preventivo_id),
                array('%s'),
                array('%d')
            );
            $files_renamed[] = 'PDF: ' . basename($new_pdf_path);
        }
    }
    
    // Rinomina Excel se esiste
    if (!empty($excel_url) && file_exists($excel_url)) {
        $new_excel_path = disco747_rename_file_by_status($excel_url, $old_status, $new_status);
        if ($new_excel_path && $new_excel_path !== $excel_url) {
            $wpdb->update(
                $table_name,
                array('excel_url' => $new_excel_path),
                array('id' => $preventivo_id),
                array('%s'),
                array('%d')
            );
            $files_renamed[] = 'Excel: ' . basename($new_excel_path);
        }
    }
    
    // ✅ AGGIUNTO: Rinomina file su Google Drive se esiste
    if (!empty($googledrive_file_id) && class_exists('Disco747_CRM\Storage\Disco747_GoogleDrive')) {
        $googledrive = new Disco747_CRM\Storage\Disco747_GoogleDrive();
        
        // Valida e genera il nome del file in base allo stato
        $data_evento_str = $preventivo['data_evento'] ?? '';
        $timestamp = strtotime($data_evento_str);
        
        if ($timestamp !== false) {
            $data_evento = date('d_m', $timestamp);
            $tipo_evento = $preventivo['tipo_evento'] ?? 'Evento';
            $tipo_menu = $preventivo['tipo_menu'] ?? 'Menu 7';
            $menu_type = preg_replace('/\b(menu\s*)+/i', '', $tipo_menu);
            $menu_type = trim($menu_type);
            
            // Costruisci il nome base del file
            $base_filename = "{$data_evento} {$tipo_evento} (Menu {$menu_type})";
            
            // Determina il prefisso in base allo stato
            $new_filename = $base_filename;
            if (strtolower($new_status) === 'annullato') {
                $new_filename = "NO {$base_filename}.xlsx";
            } elseif (strtolower($new_status) === 'confermato') {
                $new_filename = "CONF {$base_filename}.xlsx";
            } else {
                $new_filename = "{$base_filename}.xlsx";
            }
            
            error_log('[747Disco] Tentativo rinomina Google Drive: ' . $new_filename);
            
            // Rinomina su Google Drive
            $rename_result = $googledrive->rename_file($googledrive_file_id, $new_filename);
            
            if ($rename_result && isset($rename_result['url'])) {
                error_log('[747Disco] ✅ File rinominato su Google Drive: ' . $new_filename);
                // Aggiorna URL nel database
                $wpdb->update(
                    $table_name,
                    array('googledrive_url' => $rename_result['url']),
                    array('id' => $preventivo_id),
                    array('%s'),
                    array('%d')
                );
                $files_renamed[] = 'Google Drive: ' . $new_filename;
            } else {
                error_log('[747Disco] ⚠️ Errore rinomina file su Google Drive');
            }
        } else {
            error_log('[747Disco] ⚠️ Data evento non valida, impossibile rinominare file su Google Drive');
        }
    }
    
    $message = 'Stato aggiornato da "' . $old_status . '" a "' . $new_status . '"';
    if (!empty($files_renamed)) {
        $message .= '. File rinominati: ' . implode(', ', $files_renamed);
    }
    
    wp_send_json_success(array(
        'message' => $message,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'files_renamed' => $files_renamed
    ));
}

/**
 * Funzione helper per rinominare file in base allo stato
 */
function disco747_rename_file_by_status($file_path, $old_status, $new_status) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    $dir = dirname($file_path);
    $filename = basename($file_path);
    
    // Rimuovi prefissi esistenti
    $filename = preg_replace('/^(NO_|CONF_)/', '', $filename);
    
    // Aggiungi nuovo prefisso in base allo stato
    $new_filename = $filename;
    
    if (strtolower($new_status) === 'annullato') {
        $new_filename = 'NO_' . $filename;
    } elseif (strtolower($new_status) === 'confermato') {
        $new_filename = 'CONF_' . $filename;
    }
    
    // Se il nome non è cambiato, ritorna il path originale
    if ($new_filename === $filename && $old_status !== $new_status) {
        // Verifica se aveva un prefisso da rimuovere
        if (preg_match('/^(NO_|CONF_)/', basename($file_path))) {
            // Rimuovi il prefisso
            $new_path = $dir . '/' . $filename;
            if (rename($file_path, $new_path)) {
                return $new_path;
            }
        }
        return $file_path;
    }
    
    $new_path = $dir . '/' . $new_filename;
    
    // Rinomina il file
    if (rename($file_path, $new_path)) {
        error_log('[747Disco] File rinominato: ' . $filename . ' -> ' . $new_filename);
        return $new_path;
    }
    
    error_log('[747Disco] ERRORE rinominazione file: ' . $file_path);
    return $file_path;
}

/**
 * AJAX: Ottieni preventivi
 */
add_action('wp_ajax_disco747_get_preventivi', 'disco747_ajax_get_preventivi');
function disco747_ajax_get_preventivi() {
    check_ajax_referer('disco747_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'disco747_preventivi';
    
    $preventivi = $wpdb->get_results(
        "SELECT * FROM {$table_name} ORDER BY data_evento DESC, id DESC",
        ARRAY_A
    );
    
    wp_send_json_success(array('preventivi' => $preventivi));
}

/**
 * AJAX: Ottieni singolo preventivo
 */
add_action('wp_ajax_disco747_get_preventivo', 'disco747_ajax_get_preventivo');
function disco747_ajax_get_preventivo() {
    check_ajax_referer('disco747_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
    
    $preventivo_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$preventivo_id) {
        wp_send_json_error('ID preventivo mancante');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'disco747_preventivi';
    
    $preventivo = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $preventivo_id
    ), ARRAY_A);
    
    if (!$preventivo) {
        wp_send_json_error('Preventivo non trovato');
    }
    
    wp_send_json_success(array('preventivo' => $preventivo));
}

/**
 * AJAX: Elimina preventivo
 */
add_action('wp_ajax_disco747_delete_preventivo', 'disco747_ajax_delete_preventivo');
function disco747_ajax_delete_preventivo() {
    check_ajax_referer('disco747_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
    
    $preventivo_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$preventivo_id) {
        wp_send_json_error('ID preventivo mancante');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'disco747_preventivi';
    
    $deleted = $wpdb->delete(
        $table_name,
        array('id' => $preventivo_id),
        array('%d')
    );
    
    if ($deleted) {
        wp_send_json_success('Preventivo eliminato con successo');
    } else {
        wp_send_json_error('Errore eliminazione preventivo');
    }
}

// ============================================================================
// HANDLER FUNNEL EMAIL
// ============================================================================

/**
 * AJAX: Anteprima email funnel
 */
add_action('wp_ajax_disco747_preview_funnel_email', 'disco747_ajax_preview_funnel_email');
function disco747_ajax_preview_funnel_email() {
    check_ajax_referer('disco747_funnel_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
    
    $sequence_id = intval($_POST['sequence_id'] ?? 0);
    
    if (!$sequence_id) {
        wp_send_json_error('ID sequenza mancante');
    }
    
    $funnel_manager = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
    $preview = $funnel_manager->preview_email($sequence_id);
    
    wp_send_json_success($preview);
}

/**
 * AJAX: Test invio email funnel
 */
add_action('wp_ajax_disco747_test_funnel_email', 'disco747_ajax_test_funnel_email');
function disco747_ajax_test_funnel_email() {
    check_ajax_referer('disco747_funnel_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
    
    $sequence_id = intval($_POST['sequence_id'] ?? 0);
    $test_email = sanitize_email($_POST['test_email'] ?? '');
    
    if (!$sequence_id) {
        wp_send_json_error('ID sequenza mancante');
    }
    
    if (!$test_email || !is_email($test_email)) {
        wp_send_json_error('Email non valida');
    }
    
    $funnel_manager = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
    $result = $funnel_manager->test_send_email($sequence_id, $test_email);
    
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
}

/**
 * AJAX: Carica dati sequenza per editing
 */
add_action('wp_ajax_disco747_get_funnel_sequence', 'disco747_ajax_get_funnel_sequence');
function disco747_ajax_get_funnel_sequence() {
    check_ajax_referer('disco747_funnel_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
    
    global $wpdb;
    $sequence_id = intval($_POST['sequence_id'] ?? 0);
    
    if (!$sequence_id) {
        wp_send_json_error('ID sequenza mancante');
    }
    
    $sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';
    $sequence = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$sequences_table} WHERE id = %d",
        $sequence_id
    ));
    
    if (!$sequence) {
        wp_send_json_error('Sequenza non trovata');
    }
    
    wp_send_json_success($sequence);
}