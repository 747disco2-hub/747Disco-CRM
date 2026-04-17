<?php
/**
 * Classe per gestione richieste AJAX - 747 Disco CRM
 * VERSIONE 11.8.9 con Batch Scan endpoint
 *
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @version    11.8.9-BATCH-SCAN
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Ajax {

    /**
     * Componenti core
     */
    private $config;
    private $database;
    private $auth;
    private $storage_manager;
    private $pdf_generator;
    private $excel_generator;
    private $googledrive_sync;
    
    /**
     * Flag debug
     */
    private $debug_mode = true;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->load_dependencies();
        $this->register_ajax_hooks();
        $this->log('AJAX Handler inizializzato con endpoint batch scan');
    }

    /**
     * Carica dipendenze
     */
    private function load_dependencies() {
        $disco747_crm = disco747_crm();
        
        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        $this->pdf_generator = $disco747_crm->get_pdf();
        $this->excel_generator = $disco747_crm->get_excel();
        
        // Carica GoogleDrive Sync se disponibile
        if ($disco747_crm && method_exists($disco747_crm, 'get_googledrive_sync')) {
            $this->googledrive_sync = $disco747_crm->get_googledrive_sync();
        }
        
        // Crea tabella log email se non esiste
        $this->create_email_log_table();
    }
    
    /**
     * Crea tabella per log invio email
     */
    private function create_email_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'disco747_email_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            preventivo_id bigint(20) NOT NULL,
            email_to varchar(255) NOT NULL,
            subject varchar(500) DEFAULT NULL,
            template_id varchar(50) DEFAULT NULL,
            sent_at datetime NOT NULL,
            status varchar(20) NOT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY preventivo_id (preventivo_id),
            KEY sent_at (sent_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->log('Tabella email_log verificata/creata');
    }

    /**
     * Registra hook AJAX
     */
    private function register_ajax_hooks() {
        
        // Storage e OAuth
        add_action('wp_ajax_disco747_test_storage', array($this, 'handle_storage_test'));
        add_action('wp_ajax_disco747_update_setting', array($this, 'handle_update_setting'));
        
        // Batch scan Excel da Google Drive
        add_action('wp_ajax_batch_scan_excel', array($this, 'handle_batch_scan'));
        add_action('wp_ajax_reset_and_scan_excel', array($this, 'handle_reset_and_scan'));
        
        // Template messaggi
        add_action('wp_ajax_disco747_get_templates', array($this, 'handle_get_templates'));
        add_action('wp_ajax_disco747_compile_template', array($this, 'handle_compile_template'));
        add_action('wp_ajax_disco747_send_email_template', array($this, 'handle_send_email_template'));
        add_action('wp_ajax_disco747_send_whatsapp_template', array($this, 'handle_send_whatsapp_template'));
        
        $this->log('Hook AJAX registrati (incluso batch scan + templates + send email + whatsapp)');
    }

    /**
     * ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ Handler per batch scan di file Excel su Google Drive
     */
    public function handle_batch_scan() {
        error_log('[747Disco-Scan] handle_batch_scan chiamato');
        
        try {
            // Verifica nonce
            if (!check_ajax_referer('disco747_batch_scan', 'nonce', false)) {
                error_log('[747Disco-Scan] Nonce non valido');
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            error_log('[747Disco-Scan] Nonce OK');
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                error_log('[747Disco-Scan] Permessi insufficienti');
                wp_send_json_error('Permessi insufficienti');
                return;
            }
            
            error_log('[747Disco-Scan] Permessi OK');
            
            // Verifica che GoogleDrive Sync sia disponibile
            if (!$this->googledrive_sync) {
                error_log('[747Disco-Scan] GoogleDrive Sync non disponibile');
                wp_send_json_error('Servizio GoogleDrive Sync non disponibile');
                return;
            }
            
            error_log('[747Disco-Scan] GoogleDrive Sync disponibile, avvio scansione...');
            
            // Ottieni parametri
            $year = sanitize_text_field($_POST['year'] ?? date('Y'));
            $month = sanitize_text_field($_POST['month'] ?? '');
            
            error_log("[747Disco-Scan] Parametri: anno={$year}, mese={$month}");
            
            // Esegui batch scan
            $result = $this->googledrive_sync->scan_excel_files_batch($year, $month);
            
            error_log('[747Disco-Scan] Risultato batch scan: ' . json_encode($result));
            
            wp_send_json_success($result);
            
        } catch (\Exception $e) {
            error_log('[747Disco-Scan] Errore batch scan: ' . $e->getMessage());
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    /**
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬â€Ã¢â‚¬ËœÃƒÂ¯Ã‚Â¸Ã‚Â Handler per reset e scan completo
     */
    public function handle_reset_and_scan() {
        error_log('[747Disco-Scan] handle_reset_and_scan chiamato');
        
        try {
            // Verifica nonce
            if (!check_ajax_referer('disco747_batch_scan', 'nonce', false)) {
                error_log('[747Disco-Scan] Nonce non valido');
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                error_log('[747Disco-Scan] Permessi insufficienti');
                wp_send_json_error('Permessi insufficienti');
                return;
            }
            
            error_log('[747Disco-Scan] Svuotamento database...');
            
            // Svuota tabella preventivi
            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            $deleted = $wpdb->query("DELETE FROM {$table_name}");
            
            error_log("[747Disco-Scan] Eliminati {$deleted} record dal database");
            
            // Esegui batch scan normale
            $this->handle_batch_scan();
            
        } catch (\Exception $e) {
            error_log('[747Disco-Scan] Errore reset and scan: ' . $e->getMessage());
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    /**
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â§ Handler per invio email con template
     */
    public function handle_send_email_template() {
        try {
            $this->log('[Email] Richiesta invio email template');
            
            // Verifica nonce
            if (!check_ajax_referer('disco747_send_email', 'nonce', false)) {
                throw new \Exception('Nonce non valido');
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                throw new \Exception('Permessi insufficienti');
            }
            
            // Ottieni parametri
            $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
            $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '1';
            $attach_pdf = isset($_POST['attach_pdf']) && $_POST['attach_pdf'] === '1';
            
            if ($preventivo_id <= 0) {
                throw new \Exception('ID preventivo non valido');
            }
            
            $this->log("[Email] Preventivo ID: {$preventivo_id}, Template: {$template_id}, Allega PDF: " . ($attach_pdf ? 'SI' : 'NO'));
            
            // Carica preventivo dal database
            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $preventivo_id
            ), ARRAY_A);
            
            if (!$preventivo) {
                throw new \Exception('Preventivo non trovato nel database');
            }
            
            $this->log('[Email] Preventivo caricato: ' . $preventivo['nome_cliente']);
            
            // Verifica che ci sia un email valida
            if (empty($preventivo['email'])) {
                throw new \Exception('Email destinatario mancante');
            }
            
            // Carica Email Manager
            $disco747_crm = disco747_crm();
            $email_manager = $disco747_crm->get_email();
            
            if (!$email_manager) {
                throw new \Exception('Email Manager non disponibile');
            }
            
            // âœ… LISTA UFFICIALE: Passa esattamente i dati per i placeholder richiesti
            $email_data = array(
                // === CLIENTE ===
                'nome_referente' => $preventivo['nome_referente'] ?? '',
                'cognome_referente' => $preventivo['cognome_referente'] ?? '',
                'mail' => $preventivo['email'],
                'email' => $preventivo['email'],
                'telefono' => $preventivo['telefono'] ?? '',
                
                // === EVENTO ===
                'data_evento' => $preventivo['data_evento'] ?? '',
                'tipo_evento' => $preventivo['tipo_evento'] ?? '',
                'numero_invitati' => $preventivo['numero_invitati'] ?? 0,
                'orario_inizio' => $preventivo['orario_inizio'] ?? '20:30',
                'orario_fine' => $preventivo['orario_fine'] ?? '01:30',
                
                // === MENU ===
                'tipo_menu' => $preventivo['tipo_menu'] ?? '',
                
                // === IMPORTI ===
                'importo_preventivo' => $preventivo['importo_totale'] ?? 0,
                'importo_totale' => $preventivo['importo_totale'] ?? 0,
                'acconto' => $preventivo['acconto'] ?? 0,
                
                // === EXTRA ===
                'extra1' => $preventivo['extra1'] ?? '',
                'extra1_importo' => $preventivo['extra1_importo'] ?? 0,
                'extra2' => $preventivo['extra2'] ?? '',
                'extra2_importo' => $preventivo['extra2_importo'] ?? 0,
                'extra3' => $preventivo['extra3'] ?? '',
                'extra3_importo' => $preventivo['extra3_importo'] ?? 0,
                
                // === OMAGGI ===
                'omaggio1' => $preventivo['omaggio1'] ?? '',
                'omaggio2' => $preventivo['omaggio2'] ?? '',
                'omaggio3' => $preventivo['omaggio3'] ?? '',
                
                // === ALTRO ===
                'preventivo_id' => $preventivo['preventivo_id'] ?? '',
                'stato' => $preventivo['stato'] ?? 'attivo'
            );
            
            // Path PDF se richiesto
            $pdf_path = null;
            if ($attach_pdf) {
                $this->log('[Email] PDF richiesto - verifico esistenza...');
                
                // Controlla se esiste giÃƒÆ’Ã‚Â  un PDF nel database
                if (!empty($preventivo['pdf_url'])) {
                    $pdf_path = $preventivo['pdf_url'];
                    $this->log('[Email] PDF path dal DB: ' . $pdf_path);
                }
                
                // Verifica se il file esiste fisicamente
                if (!$pdf_path || !file_exists($pdf_path)) {
                    $this->log('[Email] PDF non trovato su disco, genero nuovo PDF...');
                    
                    // Prepara dati completi per PDF
                    $pdf_data = array(
                        'nome_referente' => $preventivo['nome_referente'] ?? '',
                        'cognome_referente' => $preventivo['cognome_referente'] ?? '',
                        'nome_cliente' => $preventivo['nome_cliente'] ?? '',
                        'email' => $preventivo['email'] ?? '',
                        'mail' => $preventivo['email'] ?? '',
                        'telefono' => $preventivo['telefono'] ?? '',
                        'cellulare' => $preventivo['telefono'] ?? '',
                        'data_evento' => $preventivo['data_evento'] ?? '',
                        'tipo_evento' => $preventivo['tipo_evento'] ?? '',
                        'tipo_menu' => $preventivo['tipo_menu'] ?? 'Menu 7',
                        'numero_invitati' => $preventivo['numero_invitati'] ?? 0,
                        'orario_inizio' => $preventivo['orario_inizio'] ?? '20:30',
                        'orario_fine' => $preventivo['orario_fine'] ?? '01:30',
                        'omaggio1' => $preventivo['omaggio1'] ?? '',
                        'omaggio2' => $preventivo['omaggio2'] ?? '',
                        'omaggio3' => $preventivo['omaggio3'] ?? '',
                        'extra1' => $preventivo['extra1'] ?? '',
                        'extra1_importo' => $preventivo['extra1_importo'] ?? 0,
                        'extra2' => $preventivo['extra2'] ?? '',
                        'extra2_importo' => $preventivo['extra2_importo'] ?? 0,
                        'extra3' => $preventivo['extra3'] ?? '',
                        'extra3_importo' => $preventivo['extra3_importo'] ?? 0,
                        'importo_totale' => $preventivo['importo_totale'] ?? 0,
                        'importo_preventivo' => $preventivo['importo_preventivo'] ?? $preventivo['importo_totale'] ?? 0,
                        'acconto' => $preventivo['acconto'] ?? 0,
                        'note_aggiuntive' => $preventivo['note_aggiuntive'] ?? '',
                        'note_interne' => $preventivo['note_interne'] ?? '',
                        'preventivo_id' => $preventivo['preventivo_id'] ?? '',
                        'stato' => $preventivo['stato'] ?? 'attivo'
                    );
                    
                    $pdf_generator = $disco747_crm->get_pdf();
                    if ($pdf_generator) {
                        $pdf_path = $pdf_generator->generate_pdf($pdf_data);
                        
                        if ($pdf_path && file_exists($pdf_path)) {
                            $this->log('[Email] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ PDF generato con successo: ' . basename($pdf_path));
                            
                            // Aggiorna database con path PDF
                            $wpdb->update(
                                $table_name,
                                array('pdf_url' => $pdf_path),
                                array('id' => $preventivo_id),
                                array('%s'),
                                array('%d')
                            );
                        } else {
                            $this->log('[Email] ÃƒÂ¢Ã‚ÂÃ…â€™ ERRORE: PDF non generato o file non trovato', 'ERROR');
                            $pdf_path = null;
                        }
                    } else {
                        $this->log('[Email] ÃƒÂ¢Ã‚ÂÃ…â€™ PDF Generator non disponibile', 'ERROR');
                    }
                } else {
                    $this->log('[Email] ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ PDF esistente trovato: ' . basename($pdf_path));
                }
            }
            
            // Invia email
            $this->log('[Email] Invio email a: ' . $email_data['mail']);
            if ($pdf_path) {
                $this->log('[Email] PDF da allegare: ' . $pdf_path);
                $this->log('[Email] PDF esiste su disco: ' . (file_exists($pdf_path) ? 'SI' : 'NO'));
                $this->log('[Email] Dimensione PDF: ' . (file_exists($pdf_path) ? filesize($pdf_path) . ' bytes' : 'N/A'));
            } else {
                $this->log('[Email] Nessun PDF da allegare');
            }
            
            // Passa template_id nelle options
            $options = array('template_id' => $template_id);
            $result = $email_manager->send_preventivo_email($email_data, $pdf_path, $options);
            
            if ($result) {
                // Log invio email nel database
                $this->log_email_sent($preventivo_id, $email_data['mail'], $template_id, true);
                
                wp_send_json_success(array(
                    'message' => 'Email inviata con successo!',
                    'email_to' => $email_data['mail'],
                    'pdf_attached' => $attach_pdf && $pdf_path ? true : false
                ));
            } else {
                throw new \Exception('Errore durante invio email');
            }
            
        } catch (\Exception $e) {
            $this->log('[Email] ERRORE: ' . $e->getMessage(), 'ERROR');
            
            // Log errore
            if (isset($preventivo_id) && $preventivo_id > 0) {
                $this->log_email_sent($preventivo_id, $_POST['email'] ?? '', $template_id ?? '1', false, $e->getMessage());
            }
            
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Â¬ Handler per invio WhatsApp con template
     */
    public function handle_send_whatsapp_template() {
        try {
            $this->log('[WhatsApp] Richiesta link WhatsApp');
            
            // Verifica nonce
            if (!check_ajax_referer('disco747_send_whatsapp', 'nonce', false)) {
                throw new \Exception('Nonce non valido');
            }
            
            // Verifica permessi
            if (!current_user_can('manage_options')) {
                throw new \Exception('Permessi insufficienti');
            }
            
            // Ottieni parametri
            $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
            $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '1';
            
            if ($preventivo_id <= 0) {
                throw new \Exception('ID preventivo non valido');
            }
            
            $this->log("[WhatsApp] Preventivo ID: {$preventivo_id}, Template: {$template_id}");
            
            // Carica preventivo dal database
            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $preventivo_id
            ), ARRAY_A);
            
            if (!$preventivo) {
                throw new \Exception('Preventivo non trovato');
            }
            
            // Verifica telefono
            $telefono = $preventivo['telefono'] ?? '';
            if (empty($telefono)) {
                throw new \Exception('Numero telefono non disponibile');
            }
            
            // âœ… Template WhatsApp con EMOJI CORRETTE (UTF-8)
            // ✅ NUOVO v12.0.0: Carica template WhatsApp dal database
            // Prova prima i template configurabili dalla dashboard
            $template_from_db = get_option('disco747_form_whatsapp_template_' . $template_id, false);
            
            if ($template_from_db) {
                // Usa template dal database
                $whatsapp_message = $template_from_db;
                $this->log("[WhatsApp] 📋 Template caricato dal database (ID: {$template_id})");
            } else {
                // Fallback: usa template predefiniti
                $default_templates = array(
                    '1' => "Ciao {{nome}}! 🎉\n\nIl tuo preventivo per {{tipo_evento}} del {{data_evento}} è pronto!\n\n💰 Importo: {{importo}}\n\n747 Disco - La tua festa indimenticabile! 🎊",
                    '2' => "Ciao {{nome}}! 🎈\n\nTi ricordiamo il tuo evento del {{data_evento}}.\n\nHai confermato? Rispondi per finalizzare! 📞",
                    '3' => "Ciao {{nome}}! ✅\n\nGrazie per aver confermato!\n\n📅 {{data_evento}}\n💰 Acconto: {{acconto}}\n\nCi vediamo presto! 🎉"
                );
                $whatsapp_message = $default_templates[$template_id] ?? $default_templates['1'];
                $this->log("[WhatsApp] 🔄 Template predefinito usato come fallback (ID: {$template_id})");
            }
            
            
            // Sostituisci placeholder
            $replacements = array(
                '{{nome}}' => $preventivo['nome_referente'] ?? '',
                '{{cognome}}' => $preventivo['cognome_referente'] ?? '',
                '{{nome_completo}}' => trim(($preventivo['nome_referente'] ?? '') . ' ' . ($preventivo['cognome_referente'] ?? '')),
                '{{email}}' => $preventivo['email'] ?? '',
                '{{telefono}}' => $telefono,
                '{{data_evento}}' => !empty($preventivo['data_evento']) ? date('d/m/Y', strtotime($preventivo['data_evento'])) : '',
                '{{tipo_evento}}' => $preventivo['tipo_evento'] ?? '',
                '{{orario_inizio}}' => $preventivo['orario_inizio'] ?? '',
                '{{orario_fine}}' => $preventivo['orario_fine'] ?? '',
                '{{tipo_menu}}' => $preventivo['tipo_menu'] ?? '',
                '{{menu}}' => $preventivo['tipo_menu'] ?? '',
                '{{numero_invitati}}' => $preventivo['numero_invitati'] ?? '',
                '{{importo}}' => '€ ' . number_format($preventivo['importo_totale'] ?? 0, 2, ',', '.'),
                '{{acconto}}' => '€ ' . number_format($preventivo['acconto'] ?? 0, 2, ',', '.'),
                '{{saldo}}' => '€ ' . number_format($preventivo['saldo'] ?? 0, 2, ',', '.'),
                '{{preventivo_id}}' => $preventivo['preventivo_id'] ?? '',
                '{{omaggio1}}' => $preventivo['omaggio1'] ?? '',
                '{{omaggio2}}' => $preventivo['omaggio2'] ?? '',
                '{{omaggio3}}' => $preventivo['omaggio3'] ?? '',
                '{{extra1}}' => $preventivo['extra1'] ?? '',
                '{{extra2}}' => $preventivo['extra2'] ?? '',
                '{{extra3}}' => $preventivo['extra3'] ?? '',
            );
            
            $whatsapp_message = str_replace(array_keys($replacements), array_values($replacements), $whatsapp_message);
            
            // âœ… FIX EMOJI: Formatta numero telefono (SENZA +)
            $phone = preg_replace('/[^0-9]/', '', $telefono); // Rimuove tutto tranne numeri
            
            // Aggiungi prefisso Italia se mancante
            if (substr($phone, 0, 2) !== '39') {
                $phone = '39' . $phone;
            }
            
            // âœ… FIX EMOJI: Genera link WhatsApp corretto
            // IMPORTANTE: Usa api.whatsapp.com/send + rawurlencode per emoji
            $whatsapp_url = 'https://api.whatsapp.com/send?phone=' . $phone . '&text=' . rawurlencode($whatsapp_message);
            
            $this->log('[WhatsApp] âœ… Link generato per: ' . $phone);
            $this->log('[WhatsApp] âœ… URL con emoji correttamente codificato');
            
            wp_send_json_success(array(
                'message' => 'Link WhatsApp generato!',
                'whatsapp_url' => $whatsapp_url,
                'phone' => $phone,
                'template_used' => $template_id
            ));
            
        } catch (\Exception $e) {
            $this->log('[WhatsApp] ERRORE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Â¹ Handler per ottenere template disponibili
     */
    public function handle_get_templates() {
        try {
            if (!check_ajax_referer('disco747_admin_nonce', 'nonce', false)) {
                throw new \Exception('Nonce non valido');
            }
            
            // Leggi numero massimo di template configurati
            $max_templates = get_option('disco747_max_templates', 5);
            
            // Ã°Å¸â€œÂ§ Carica template EMAIL dalle impostazioni WordPress
            $email_templates = array();
            for ($i = 1; $i <= $max_templates; $i++) {
                $name = get_option('disco747_email_name_' . $i, 'Template Email ' . $i);
                $enabled = get_option('disco747_email_enabled_' . $i, 1);
                
                // Include solo i template abilitati
                if ($enabled) {
                    $email_templates[] = array(
                        'id' => (string)$i,
                        'name' => $name
                    );
                }
            }
            
            // Ã°Å¸â€™Â¬ Carica template WHATSAPP dalle impostazioni WordPress
            $whatsapp_templates = array();
            for ($i = 1; $i <= $max_templates; $i++) {
                $name = get_option('disco747_whatsapp_name_' . $i, 'Template WhatsApp ' . $i);
                $enabled = get_option('disco747_whatsapp_enabled_' . $i, 1);
                
                // Include solo i template abilitati
                if ($enabled) {
                    $whatsapp_templates[] = array(
                        'id' => (string)$i,
                        'name' => $name
                    );
                }
            }
            
            $this->log('[Templates] Caricati ' . count($email_templates) . ' template email e ' . count($whatsapp_templates) . ' template WhatsApp');
            
            wp_send_json_success(array(
                'email' => $email_templates,
                'whatsapp' => $whatsapp_templates
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â Handler per compilare template
     */
    public function handle_compile_template() {
        try {
            if (!check_ajax_referer('disco747_admin_nonce', 'nonce', false)) {
                throw new \Exception('Nonce non valido');
            }
            
            $template_id = $_POST['template_id'] ?? '';
            $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
            
            // TODO: Implementare compilazione template dinamica
            
            wp_send_json_success(array(
                'compiled_template' => 'Template compilato...'
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  Salva log invio email nel database
     */
    private function log_email_sent($preventivo_id, $email_to, $template_id, $success, $error_message = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'disco747_email_log';
        
        $wpdb->insert($table_name, array(
            'preventivo_id' => $preventivo_id,
            'email_to' => $email_to,
            'subject' => 'Preventivo 747 Disco',
            'template_id' => $template_id,
            'sent_at' => current_time('mysql'),
            'status' => $success ? 'sent' : 'failed',
            'error_message' => $error_message
        ));
    }
    
    /**
     * Log interno
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode && function_exists('error_log')) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[{$timestamp}] [747Disco-AJAX] [{$level}] {$message}");
        }
    }
}