<?php
/**
 * AJAX Handlers per 747 Disco CRM
 * Gestisce preventivi, funnel email e cambio stato
 * 
 * @package    Disco747_CRM
 * @subpackage Admin
 * @version    2.2.0
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
        die();
    }
    
    $preventivo_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    
    if (!$preventivo_id || !$new_status) {
        wp_send_json_error('Parametri mancanti');
        die();
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
        die();
    }
    
    $old_status = $preventivo['stato'];
    $pdf_url = $preventivo['pdf_url'];
    $excel_url = $preventivo['excel_url'];
    $googledrive_file_id = $preventivo['googledrive_file_id'];
    
    // 🔒 VALIDAZIONE: Non permettere stato 'confermato' senza acconto
    if ($new_status === 'confermato' && floatval($preventivo['acconto']) <= 0) {
        wp_send_json_error('Non puoi confermare un preventivo senza acconto. Inserisci prima l\'importo dell\'acconto.');
        die();
    }
    
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
        die();
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
    die();
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
        die();
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'disco747_preventivi';
    
    $preventivi = $wpdb->get_results(
        "SELECT * FROM {$table_name} ORDER BY data_evento DESC, id DESC",
        ARRAY_A
    );
    
    wp_send_json_success(array('preventivi' => $preventivi));
    die();
}

/**
 * AJAX: Ottieni singolo preventivo
 */
add_action('wp_ajax_disco747_get_preventivo', 'disco747_ajax_get_preventivo');
function disco747_ajax_get_preventivo() {
    check_ajax_referer('disco747_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
        die();
    }
    
    $preventivo_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$preventivo_id) {
        wp_send_json_error('ID preventivo mancante');
        die();
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'disco747_preventivi';
    
    $preventivo = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $preventivo_id
    ), ARRAY_A);
    
    if (!$preventivo) {
        wp_send_json_error('Preventivo non trovato');
        die();
    }
    
    wp_send_json_success(array('preventivo' => $preventivo));
    die();
}

/**
 * AJAX: Elimina preventivo
 */
add_action('wp_ajax_disco747_delete_preventivo', 'disco747_ajax_delete_preventivo');
function disco747_ajax_delete_preventivo() {
    check_ajax_referer('disco747_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
        die();
    }
    
    $preventivo_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$preventivo_id) {
        wp_send_json_error('ID preventivo mancante');
        die();
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
        die();
    } else {
        wp_send_json_error('Errore eliminazione preventivo');
        die();
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
        die();
    }
    
    $sequence_id = intval($_POST['sequence_id'] ?? 0);
    
    if (!$sequence_id) {
        wp_send_json_error('ID sequenza mancante');
        die();
    }
    
    $funnel_manager = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
    $preview = $funnel_manager->preview_email($sequence_id);
    
    wp_send_json_success($preview);
    die();
}

/**
 * AJAX: Test invio email funnel
 */
add_action('wp_ajax_disco747_test_funnel_email', 'disco747_ajax_test_funnel_email');
function disco747_ajax_test_funnel_email() {
    check_ajax_referer('disco747_funnel_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
        die();
    }
    
    $sequence_id = intval($_POST['sequence_id'] ?? 0);
    $test_email = sanitize_email($_POST['test_email'] ?? '');
    
    if (!$sequence_id) {
        wp_send_json_error('ID sequenza mancante');
        die();
    }
    
    if (!$test_email || !is_email($test_email)) {
        wp_send_json_error('Email non valida');
        die();
    }
    
    $funnel_manager = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
    $result = $funnel_manager->test_send_email($sequence_id, $test_email);
    
    if ($result['success']) {
        wp_send_json_success($result['message']);
        die();
    } else {
        wp_send_json_error($result['message']);
        die();
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
        die();
    }
    
    global $wpdb;
    $sequence_id = intval($_POST['sequence_id'] ?? 0);
    
    if (!$sequence_id) {
        wp_send_json_error('ID sequenza mancante');
        die();
    }
    
    $sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';
    $sequence = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$sequences_table} WHERE id = %d",
        $sequence_id
    ));
    
    if (!$sequence) {
        wp_send_json_error('Sequenza non trovata');
        die();
    }
    
    wp_send_json_success($sequence);
    die();
}

// ============================================================================
// HANDLER ANTEPRIMA EMAIL - NUOVO v2.2.0
// ============================================================================

/**
 * AJAX: Invia email di test/anteprima
 * 
 * @since 2.2.0
 */
add_action('wp_ajax_disco747_send_preview_email', 'disco747_ajax_send_preview_email');
add_action('wp_ajax_nopriv_disco747_send_preview_email', 'disco747_ajax_send_preview_email');

function disco747_ajax_send_preview_email() {
    // Log inizio
    error_log('[747Disco] === INIZIO ANTEPRIMA EMAIL ===');
    
    // Prova diverse chiavi di nonce (per compatibilità)
    $nonce_keys = array(
        'disco747_admin_nonce',
        '_wpnonce',
        'disco747_funnel_nonce',
        'nonce'
    );
    
    $nonce_valid = false;
    $nonce_used = '';
    
    if (isset($_POST['nonce'])) {
        foreach ($nonce_keys as $key) {
            if (wp_verify_nonce($_POST['nonce'], $key)) {
                $nonce_valid = true;
                $nonce_used = $key;
                error_log('[747Disco] ✅ Nonce valido con chiave: ' . $key);
                break;
            }
        }
    }
    
    if (!$nonce_valid) {
        error_log('[747Disco] ❌ Nonce non valido. Nonce ricevuto: ' . ($_POST['nonce'] ?? 'NESSUNO'));
        wp_send_json_error(array(
            'message' => 'Errore di sicurezza: nonce non valido',
            'code' => 'invalid_nonce',
            'nonce_received' => $_POST['nonce'] ?? 'NESSUNO'
        ));
        die();
    }
    
    // Verifica permessi (admin only)
    if (!current_user_can('manage_options')) {
        error_log('[747Disco] ❌ Permessi insufficienti per: ' . wp_get_current_user()->user_email);
        wp_send_json_error(array(
            'message' => 'Permessi insufficienti',
            'code' => 'insufficient_permissions'
        ));
        die();
    }
    
    // Estrai parametri
    $test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
    $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : 'Anteprima Email 747 Disco';
    $template_type = isset($_POST['template_type']) ? sanitize_text_field($_POST['template_type']) : 'preview';
    
    error_log('[747Disco] Parametri ricevuti: email=' . $test_email . ', subject=' . $subject . ', template=' . $template_type);
    
    // Validazione email
    if (!$test_email || !is_email($test_email)) {
        $admin_email = get_option('admin_email');
        $test_email = $admin_email;
        error_log('[747Disco] ⚠️ Email non fornita, uso admin email: ' . $admin_email);
    }
    
    // Prepara dati di test
    $test_data = array(
        'nome' => 'Test User',
        'cognome' => '747 Disco',
        'email' => $test_email,
        'tipo_evento' => 'Evento di Prova',
        'data_evento' => date('d/m/Y', strtotime('+30 days')),
        'tipo_menu' => 'Menu Standard',
        'numero_invitati' => 50,
        'importo_totale' => 1500.00,
        'acconto' => 300.00,
        'saldo' => 1200.00,
        'preventivo_id' => 'TEST-' . time()
    );
    
    // Prepara email HTML
    $email_body = disco747_generate_preview_email_html($test_data, $template_type);
    
    if (!$email_body) {
        error_log('[747Disco] ❌ Impossibile generare corpo email');
        wp_send_json_error(array(
            'message' => 'Errore generazione corpo email',
            'code' => 'email_generation_failed'
        ));
        die();
    }
    
    // Configura header per HTML
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    // Aggiungi header From se configurato
    $from_name = get_option('disco747_email_from_name', 'Centro 747 Disco');
    $from_email = get_option('disco747_email_from_address', get_option('admin_email'));
    $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
    
    error_log('[747Disco] ✅ Header email configurati. From: ' . $from_name . ' <' . $from_email . '>');
    
    // Invia email di test
    try {
        error_log('[747Disco] 📧 Invio email a: ' . $test_email);
        
        $mail_result = wp_mail(
            $test_email,
            $subject,
            $email_body,
            $headers
        );
        
        if ($mail_result) {
            error_log('[747Disco] ✅ Email inviata con successo a: ' . $test_email);
            
            wp_send_json_success(array(
                'message' => '✅ Email di anteprima inviata a: ' . $test_email,
                'email_sent_to' => $test_email,
                'code' => 'email_sent_success'
            ));
            die();
        } else {
            error_log('[747Disco] ❌ wp_mail() ha ritornato false');
            
            wp_send_json_error(array(
                'message' => '❌ Errore invio email. Verifica configurazione SMTP del server.',
                'code' => 'mail_send_failed'
            ));
            die();
        }
        
    } catch (Exception $e) {
        error_log('[747Disco] ❌ Exception durante invio email: ' . $e->getMessage());
        
        wp_send_json_error(array(
            'message' => '❌ Errore: ' . $e->getMessage(),
            'code' => 'email_exception'
        ));
        die();
    }
}

/**
 * Genera HTML per email di anteprima
 * 
 * @param array $data Dati per il template
 * @param string $template_type Tipo di template
 * @return string HTML email
 * 
 * @since 2.2.0
 */
function disco747_generate_preview_email_html($data, $template_type = 'preview') {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Anteprima Email - 747 Disco</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f5f5f5;
                line-height: 1.6;
                color: #333;
            }
            
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            .header {
                background: linear-gradient(135deg, #2b1e1a 0%, #3c3c3c 100%);
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 28px;
                color: #c28a4d;
                margin-bottom: 5px;
                text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            }
            
            .header p {
                font-size: 14px;
                color: #eeeae6;
                opacity: 0.9;
            }
            
            .content {
                padding: 30px 20px;
            }
            
            .greeting {
                margin-bottom: 20px;
                font-size: 16px;
            }
            
            .section {
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 1px solid #eee;
            }
            
            .section:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            
            .section-title {
                font-size: 18px;
                font-weight: bold;
                color: #2b1e1a;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .data-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                font-size: 14px;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .data-row:last-child {
                border-bottom: none;
            }
            
            .data-label {
                font-weight: 600;
                color: #666;
                flex: 1;
            }
            
            .data-value {
                color: #2b1e1a;
                text-align: right;
                flex: 1;
            }
            
            .highlight-box {
                background: linear-gradient(135deg, #fff9f5 0%, #fffbf7 100%);
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #c28a4d;
                margin: 15px 0;
            }
            
            .amount-total {
                font-size: 24px;
                font-weight: bold;
                color: #c28a4d;
                text-align: center;
                margin: 10px 0;
            }
            
            .button-container {
                text-align: center;
                margin: 25px 0;
            }
            
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%);
                color: white;
                padding: 12px 30px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                transition: all 0.3s ease;
                box-shadow: 0 2px 4px rgba(194, 138, 77, 0.3);
            }
            
            .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(194, 138, 77, 0.4);
            }
            
            .footer {
                background: #f9f9f9;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #999;
                border-top: 1px solid #eee;
            }
            
            .test-badge {
                display: inline-block;
                background: #ffc107;
                color: #000;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                margin-top: 10px;
            }
            
            @media (max-width: 600px) {
                .container {
                    border-radius: 0;
                }
                
                .header {
                    padding: 20px 15px;
                }
                
                .header h1 {
                    font-size: 22px;
                }
                
                .content {
                    padding: 20px 15px;
                }
                
                .data-row {
                    flex-direction: column;
                }
                
                .data-value {
                    text-align: left;
                    margin-top: 5px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- HEADER -->
            <div class="header">
                <h1>🎭 747 DISCO</h1>
                <p>Anteprima Preventivo</p>
            </div>
            
            <!-- CONTENUTO -->
            <div class="content">
                <!-- SALUTO -->
                <div class="greeting">
                    <p>Caro/a <strong><?php echo esc_html($data['nome'] . ' ' . $data['cognome']); ?></strong>,</p>
                    <p style="margin-top: 10px; color: #666;">Ecco l'anteprima del tuo preventivo personalizzato per il tuo evento speciale.</p>
                </div>
                
                <!-- SEZIONE EVENTO -->
                <div class="section">
                    <div class="section-title">
                        <span>📋</span> Dettagli Evento
                    </div>
                    <div class="data-row">
                        <span class="data-label">Tipo Evento:</span>
                        <span class="data-value"><?php echo esc_html($data['tipo_evento']); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Data Evento:</span>
                        <span class="data-value"><?php echo esc_html($data['data_evento']); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Numero Invitati:</span>
                        <span class="data-value"><?php echo esc_html($data['numero_invitati']); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">Menu Scelto:</span>
                        <span class="data-value"><?php echo esc_html($data['tipo_menu']); ?></span>
                    </div>
                </div>
                
                <!-- SEZIONE ECONOMICA -->
                <div class="section">
                    <div class="section-title">
                        <span>💰</span> Dettagli Economici
                    </div>
                    <div class="highlight-box">
                        <div class="amount-total">
                            €<?php echo number_format($data['importo_totale'], 2, ',', '.'); ?>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Acconto Richiesto:</span>
                            <span class="data-value">€<?php echo number_format($data['acconto'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Saldo Rimanente:</span>
                            <span class="data-value">€<?php echo number_format($data['saldo'], 2, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- SEZIONE AZIONE -->
                <div class="section">
                    <div class="section-title">
                        <span>✅</span> Conferma Preventivo
                    </div>
                    <p style="margin-bottom: 10px; color: #666;">
                        Per confermare il preventivo e procedere con la prenotazione, accedi al tuo account:
                    </p>
                    <div class="button-container">
                        <a href="<?php echo home_url('/disco747-dashboard/'); ?>" class="button">
                            👁️ Visualizza e Conferma
                        </a>
                    </div>
                    <p style="font-size: 12px; color: #999; text-align: center;">
                        Preventivo ID: <strong><?php echo esc_html($data['preventivo_id']); ?></strong>
                    </p>
                </div>
            </div>
            
            <!-- FOOTER -->
            <div class="footer">
                <p>🎭 Centro 747 Disco - Email generata il <?php echo date('d/m/Y H:i'); ?></p>
                <p style="margin-top: 10px; color: #bbb;">Questa è un'email di test. I dati sono indicativi.</p>
                <span class="test-badge">ANTEPRIMA TEST</span>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}