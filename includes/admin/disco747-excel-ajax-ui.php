<?php
/**
 * AJAX Handlers aggiuntivi per Excel Scan UI
 * Statistiche e tabella preventivi da wp_disco747_preventivi
 * 
 * @package    Disco747_CRM
 * @subpackage Admin
 * @version    1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Registra handler AJAX per UI Excel Scan
 */
add_action('wp_ajax_disco747_get_excel_stats', 'disco747_ajax_get_excel_stats');
add_action('wp_ajax_disco747_get_excel_table', 'disco747_ajax_get_excel_table');

/**
 * Handler AJAX: Ottieni statistiche preventivi Excel
 */
function disco747_ajax_get_excel_stats() {
    // Verifica nonce
    if (!check_ajax_referer('disco747_excel_scan', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce non valido'));
        return;
    }
    
    // Verifica permessi
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permessi insufficienti'));
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'disco747_preventivi';
    
    try {
        // Conta totale file con googledrive_file_id (= file Excel)
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} 
             WHERE googledrive_file_id IS NOT NULL 
             AND googledrive_file_id != ''"
        );
        
        // Conta file analizzati con successo (hanno dati completi)
        $success = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} 
             WHERE googledrive_file_id IS NOT NULL 
             AND googledrive_file_id != ''
             AND nome_cliente IS NOT NULL 
             AND nome_cliente != ''"
        );
        
        // Conta confermati (con acconto > 0)
        $confirmed = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} 
             WHERE googledrive_file_id IS NOT NULL 
             AND googledrive_file_id != ''
             AND acconto > 0"
        );
        
        // Conta errori (file senza dati completi)
        $errors = $total - $success;
        
        wp_send_json_success(array(
            'total' => intval($total),
            'success' => intval($success),
            'confirmed' => intval($confirmed),
            'errors' => intval($errors)
        ));
        
    } catch (Exception $e) {
        error_log('[747Disco-AJAX-Stats] Errore: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Errore caricamento statistiche'));
    }
}

/**
 * Handler AJAX: Ottieni tabella preventivi Excel paginata
 */
function disco747_ajax_get_excel_table() {
    // Verifica nonce
    if (!check_ajax_referer('disco747_excel_scan', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce non valido'));
        return;
    }
    
    // Verifica permessi
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permessi insufficienti'));
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'disco747_preventivi';
    
    try {
        // Parametri
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $menu = isset($_POST['menu']) ? sanitize_text_field($_POST['menu']) : '';
        
        // Costruisci WHERE clause
        $where = "googledrive_file_id IS NOT NULL AND googledrive_file_id != ''";
        $where_params = array();
        
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (nome_cliente LIKE %s OR email LIKE %s OR telefono LIKE %s OR tipo_evento LIKE %s)";
            $where_params[] = $search_like;
            $where_params[] = $search_like;
            $where_params[] = $search_like;
            $where_params[] = $search_like;
        }
        
        if (!empty($menu)) {
            $where .= " AND tipo_menu LIKE %s";
            $where_params[] = '%' . $wpdb->esc_like($menu) . '%';
        }
        
        // Conta totale
        $count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        if (!empty($where_params)) {
            $count_query = $wpdb->prepare($count_query, $where_params);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Query principale
        $query = "SELECT * FROM {$table} WHERE {$where} ORDER BY data_evento DESC, id DESC LIMIT %d OFFSET %d";
        $query_params = array_merge($where_params, array($per_page, $offset));
        
        $rows = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
        
        // Paginazione
        $total_pages = ceil($total_items / $per_page);
        
        wp_send_json_success(array(
            'rows' => $rows,
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $per_page,
                'total_items' => intval($total_items),
                'total_pages' => intval($total_pages)
            )
        ));
        
    } catch (Exception $e) {
        error_log('[747Disco-AJAX-Table] Errore: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Errore caricamento tabella'));
    }
}