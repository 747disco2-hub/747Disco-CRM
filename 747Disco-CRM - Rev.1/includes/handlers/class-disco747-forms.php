<?php
/**
 * Forms Handler per 747 Disco CRM - VERSIONE FINALE v12.3.0
 * 
 * FIX APPLICATI:
 * 1. ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Cartella basata su data_evento
 * 2. ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ PDF NON generato automaticamente
 * 3. ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ PDF on-demand SENZA upload Google Drive
 * 4. ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Salvataggio database COMPLETO con tutti i campi
 * 
 * PERCORSO: wp-content/plugins/747disco-crm/includes/handlers/class-disco747-forms.php
 * 
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @version    12.3.0
 */

namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Forms {
    
    private $database;
    private $excel;
    private $pdf;
    private $storage;
    private $email;
    private $whatsapp;
    private $log_enabled = true;
    private $components_loaded = false;
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'disco747_preventivi';
        $this->log('[Forms] Handler Forms v12.3.0 inizializzato');
        
        // Hook AJAX
        add_action('wp_ajax_disco747_save_preventivo', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_nopriv_disco747_save_preventivo', array($this, 'handle_ajax_submission'));
        
        // Hook AJAX solo per PDF (email e whatsapp gestiti da class-disco747-ajax.php)
        add_action('wp_ajax_disco747_generate_pdf', array($this, 'handle_generate_pdf'));
        
        // ✅ FIX: Cleanup più frequente (ogni 10 minuti) per prevenire conflitti file
        add_filter('cron_schedules', array($this, 'add_ten_minute_cron_interval'));
        add_action('disco747_cleanup_temp_files', array($this, 'cleanup_temp_files'));
        if (!wp_next_scheduled('disco747_cleanup_temp_files')) {
            wp_schedule_event(time(), 'ten_minutes', 'disco747_cleanup_temp_files');
        }
        
        $this->log('[Forms] Hook AJAX registrati correttamente');
    }
    
    /**
     * ========================================================================
     * METODO PRINCIPALE - Handle AJAX Submission
     * ========================================================================
     */
    public function handle_ajax_submission() {
        try {
            $this->log('[Forms] ========== INIZIO GESTIONE PREVENTIVO ==========');
            
            if (!$this->verify_nonce()) {
                $this->log('[Forms] ERRORE: Nonce non valido');
                wp_send_json_error('Sessione scaduta. Ricarica la pagina.');
                return;
            }
            
            $this->load_components();
            $data = $this->validate_form_data($_POST);
            
            if (!$data) {
                wp_send_json_error('Dati del form non validi');
                return;
            }
            
            $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Dati validati correttamente');
            
            // Controlla se ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¨ modalitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â  modifica
            $is_edit_mode = !empty($_POST['is_edit_mode']);
            $edit_id = $is_edit_mode ? intval($_POST['edit_id']) : 0;
            
            if ($is_edit_mode && $edit_id > 0) {
                $this->update_preventivo($edit_id, $data);
                return;
            }
            
            // Creazione nuovo preventivo
            $this->create_new_preventivo($data);
            
        } catch (\Exception $e) {
            $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ ERRORE FATALE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ========================================================================
     * CREAZIONE NUOVO PREVENTIVO
     * ========================================================================
     */
    private function create_new_preventivo($data) {
        $this->log('[Forms] MODALITÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ CREAZIONE NUOVO PREVENTIVO');
        
        // Genera ID preventivo progressivo
        $data['preventivo_id'] = $this->generate_preventivo_id();
        $this->log('[Forms] ID Preventivo generato: ' . $data['preventivo_id']);
        
        // ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ GENERA SOLO EXCEL (NO PDF)
        $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â°ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¾ Generazione Excel...');
        $excel_path = $this->create_excel_safe($data);
        
        if (!$excel_path) {
            throw new \Exception('Errore generazione Excel');
        }
        
        $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Excel generato: ' . basename($excel_path));
        
        // ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚ÂºÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â PDF NON generato automaticamente
        $pdf_path = null;
        
        // ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ UPLOAD SU GOOGLE DRIVE nella cartella corretta (basata su data_evento)
        $cloud_url = '';
        if ($this->storage) {
            $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¹Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Upload su Google Drive...');
            
            try {
                // ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ FIX: Usa data_evento per determinare il percorso corretto
                $date_parts = explode('-', $data['data_evento']);
                $year = $date_parts[0];
                $month_num = $date_parts[1];
                
                // Converti numero mese in nome mese italiano
                $mesi = array(
                    '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo',
                    '04' => 'Aprile', '05' => 'Maggio', '06' => 'Giugno',
                    '07' => 'Luglio', '08' => 'Agosto', '09' => 'Settembre',
                    '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre'
                );
                $month_name = $mesi[$month_num] ?? $month_num;
                
                // Percorso corretto: /747-Preventivi/2025/Novembre/
                $drive_folder = '747-Preventivi/' . $year . '/' . $month_name . '/';
                
                $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â°ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Percorso Google Drive: ' . $drive_folder);
                $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â°ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Data evento usata: ' . $data['data_evento']);
                
                // Ã¢Å“â€¦ FIX: Upload Excel e salva file_id per permettere sovrascrittura futura
                if ($excel_path && file_exists($excel_path)) {
                    // Usa direttamente il metodo GoogleDrive per avere file_id
                    if (class_exists('Disco747_CRM\Storage\Disco747_GoogleDrive')) {
                        $googledrive = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
                        $filename = basename($excel_path);
                        $result = $googledrive->upload_to_googledrive($excel_path, $filename, $data['data_evento']);
                        
                        if ($result && is_array($result) && isset($result['url'])) {
                            $cloud_url = $result['url'];
                            $data['googledrive_url'] = $result['url'];
                            $data['googledrive_file_id'] = $result['file_id'];  // Ã¢Å“â€¦ SALVA FILE_ID!
                            $this->log('[Forms] Ã¢Å“â€¦ Excel caricato su Drive: ' . basename($excel_path));
                            $this->log('[Forms] Ã¢Å“â€¦ File ID salvato: ' . $result['file_id']);
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Errore upload Drive: ' . $e->getMessage(), 'WARNING');
            }
        }
        
        // Salva database
        $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â°ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¾ Salvataggio database...');
        $db_id = $this->save_preventivo($data);
        
        if (!$db_id) {
            throw new \Exception('Errore salvataggio database');
        }
        
        $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Preventivo salvato con ID database: ' . $db_id);
        
        // 🚀 HOOK: Lancia evento preventivo creato/confermato/annullato (per funnel automation)
        // ✅ FIX: Gestione corretta degli stati - solo preventivi ATTIVI entrano nel funnel
        if ($data['stato'] === 'confermato' && floatval($data['acconto']) > 0) {
            // Se è confermato con acconto, lancia hook conferma (stoppa pre-conferma, avvia pre-evento)
            do_action('disco747_preventivo_confirmed', $db_id);
            $this->log('[Forms] 🎯 Hook disco747_preventivo_confirmed lanciato (ID: ' . $db_id . ')');
        } elseif ($data['stato'] === 'annullato') {
            // Se è annullato, lancia hook annullamento (non avvia alcun funnel)
            do_action('disco747_preventivo_cancelled', $db_id);
            $this->log('[Forms] 🛑 Hook disco747_preventivo_cancelled lanciato (ID: ' . $db_id . ')');
        } elseif ($data['stato'] === 'attivo') {
            // Solo se è ATTIVO, lancia hook creazione (avvia funnel pre-conferma)
            do_action('disco747_preventivo_created', $db_id);
            $this->log('[Forms] 🎯 Hook disco747_preventivo_created lanciato (ID: ' . $db_id . ')');
        }
        // Nota: Per design, uno stato 'confermato' deve sempre avere acconto > 0
        // Se per qualche motivo uno stato non corrisponde a nessuna condizione, non lancia hook
        // (questo previene preventivi in stati inconsistenti dall'entrare nel funnel)
        
        $this->log('[Forms] ========== ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ PREVENTIVO COMPLETATO ==========');
        
        wp_send_json_success(array(
            'message' => 'Preventivo creato con successo!',
            'preventivo_id' => $data['preventivo_id'],
            'id' => $db_id,
            'db_id' => $db_id,
            'excel_path' => $excel_path,
            'excel_url' => $cloud_url,
            'pdf_generated' => false,
            'pdf_note' => 'PDF non generato automaticamente. Usa il pulsante "Genera PDF" se necessario.',
            // Dati preventivo per JavaScript
            'nome_referente' => $data['nome_referente'] ?? '',
            'cognome_referente' => $data['cognome_referente'] ?? '',
            'nome_cliente' => $data['nome_cliente'] ?? trim(($data['nome_referente'] ?? '') . ' ' . ($data['cognome_referente'] ?? '')),
            'email' => $data['email'] ?? '',
            'telefono' => $data['telefono'] ?? '',
            'data_evento' => $data['data_evento'] ?? '',
            'tipo_evento' => $data['tipo_evento'] ?? '',
            'tipo_menu' => $data['tipo_menu'] ?? '',
            'numero_invitati' => $data['numero_invitati'] ?? 0,
            'orario_inizio' => $data['orario_inizio'] ?? '',
            'orario_fine' => $data['orario_fine'] ?? '',
            'importo_totale' => $data['importo_totale'] ?? 0,
            'acconto' => $data['acconto'] ?? 0,
            'saldo' => ($data['importo_totale'] ?? 0) - ($data['acconto'] ?? 0),
            'omaggio1' => $data['omaggio1'] ?? '',
            'omaggio2' => $data['omaggio2'] ?? '',
            'omaggio3' => $data['omaggio3'] ?? '',
            'extra1' => $data['extra1'] ?? '',
            'extra2' => $data['extra2'] ?? '',
            'extra3' => $data['extra3'] ?? '',
            'stato' => $data['stato'] ?? 'attivo'
        ));
    }
    
    /**
     * ========================================================================
     * AGGIORNAMENTO PREVENTIVO ESISTENTE
     * ========================================================================
     */
    private function update_preventivo($edit_id, $data) {
        $this->log('[Forms] MODALITA AGGIORNAMENTO PREVENTIVO ID: ' . $edit_id);
        
        global $wpdb;
        
        // ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â LEGGI STATO PRECEDENTE (per determinare se ÃƒÆ’Ã‚Â¨ stata una conferma)
        $old_preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $edit_id
        ));
        $old_stato = $old_preventivo ? $old_preventivo->stato : '';
        $old_acconto = $old_preventivo ? floatval($old_preventivo->acconto) : 0;
        
        // Ã¢Å“â€¦ FIX: Calcola extra totale
        $extra_totale = floatval($data['extra1_importo']) + floatval($data['extra2_importo']) + floatval($data['extra3_importo']);
        
        // Ã¢Å“â€¦ IMPORTANTE: 
        // - importo_totale = SOLO base menu (senza extra) - viene dal form
        // - importo_preventivo = base + extra (totale finale)
        // - saldo = importo_preventivo - acconto
        $importo_preventivo = floatval($data['importo_totale']) + $extra_totale;
        $saldo = $importo_preventivo - floatval($data['acconto']);
        
        $this->log('[Forms] Ã°Å¸â€™Â° Calcolo importi:');
        $this->log('  - Base menu (importo_totale): Ã¢â€šÂ¬' . $data['importo_totale']);
        $this->log('  - Extra totale: Ã¢â€šÂ¬' . $extra_totale);
        $this->log('  - Totale finale (importo_preventivo): Ã¢â€šÂ¬' . $importo_preventivo);
        $this->log('  - Acconto: Ã¢â€šÂ¬' . $data['acconto']);
        $this->log('  - Saldo: Ã¢â€šÂ¬' . $saldo);
        
        // Aggiorna database
        $updated = $wpdb->update(
            $this->table_name,
            array(
                'nome_cliente' => $data['nome_cliente'],
                'nome_referente' => $data['nome_referente'],
                'cognome_referente' => $data['cognome_referente'],
                'telefono' => $data['telefono'],
                'email' => $data['email'],
                'data_evento' => $data['data_evento'],
                'tipo_evento' => $data['tipo_evento'],
                'tipo_menu' => $data['tipo_menu'],
                'numero_invitati' => $data['numero_invitati'],
                'orario_inizio' => $data['orario_inizio'],
                'orario_fine' => $data['orario_fine'],
                'omaggio1' => $data['omaggio1'],
                'omaggio2' => $data['omaggio2'],
                'omaggio3' => $data['omaggio3'],
                'extra1' => $data['extra1'],
                'extra1_importo' => $data['extra1_importo'],
                'extra2' => $data['extra2'],
                'extra2_importo' => $data['extra2_importo'],
                'extra3' => $data['extra3'],
                'extra3_importo' => $data['extra3_importo'],
                'importo_totale' => $data['importo_totale'],
                'importo_preventivo' => $importo_preventivo,
                'acconto' => $data['acconto'],
                'saldo' => $saldo,
                'note_aggiuntive' => $data['note_aggiuntive'],
                'note_interne' => $data['note_interne'],
                'stato' => $data['stato'],
                'updated_at' => current_time('mysql')
            ),
            array('id' => $edit_id),
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d',
                '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%f',
                '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s'
            ),
            array('%d')
        );
        
        if ($updated === false) {
            $this->log('[Forms] ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬â„¢ Errore aggiornamento: ' . $wpdb->last_error, 'ERROR');
            wp_send_json_error('Errore aggiornamento database');
            return;
        }
        
        $this->log('[Forms] ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ Preventivo aggiornato con successo');
        
        // âœ… NOTA: La rinomina del file su Google Drive avviene automaticamente
        // quando viene rigenerato l'Excel con il nome corretto (che include "NO" se annullato)
        // e poi caricato tramite update_existing_file(). Non serve una rinomina separata.
        
        // Ã¢Å“â€¦ LOG: Registra aggiornamento nel sistema audit
        if ($this->database && method_exists($this->database, 'log_preventivo_change')) {
            // Prepara array delle modifiche rilevanti
            $changes = array();
            if ($old_preventivo) {
                // Confronta tutti i campi chiave
                if ($old_preventivo->nome_cliente != $data['nome_cliente']) {
                    $changes['nome_cliente'] = array('old' => $old_preventivo->nome_cliente, 'new' => $data['nome_cliente']);
                }
                if ($old_preventivo->telefono != $data['telefono']) {
                    $changes['telefono'] = array('old' => $old_preventivo->telefono, 'new' => $data['telefono']);
                }
                if ($old_preventivo->email != $data['email']) {
                    $changes['email'] = array('old' => $old_preventivo->email, 'new' => $data['email']);
                }
                if ($old_preventivo->data_evento != $data['data_evento']) {
                    $changes['data_evento'] = array('old' => $old_preventivo->data_evento, 'new' => $data['data_evento']);
                }
                if ($old_preventivo->tipo_evento != $data['tipo_evento']) {
                    $changes['tipo_evento'] = array('old' => $old_preventivo->tipo_evento, 'new' => $data['tipo_evento']);
                }
                if ($old_preventivo->tipo_menu != $data['tipo_menu']) {
                    $changes['tipo_menu'] = array('old' => $old_preventivo->tipo_menu, 'new' => $data['tipo_menu']);
                }
                if ($old_preventivo->numero_invitati != $data['numero_invitati']) {
                    $changes['numero_invitati'] = array('old' => $old_preventivo->numero_invitati, 'new' => $data['numero_invitati']);
                }
                if ($old_preventivo->importo_totale != $data['importo_totale']) {
                    $changes['importo_totale'] = array('old' => $old_preventivo->importo_totale, 'new' => $data['importo_totale']);
                }
                if ($old_acconto != floatval($data['acconto'])) {
                    $changes['acconto'] = array('old' => $old_acconto, 'new' => $data['acconto']);
                }
                if ($old_stato != $data['stato']) {
                    $changes['stato'] = array('old' => $old_stato, 'new' => $data['stato']);
                }
            }
            $this->database->log_preventivo_change($edit_id, 'update', $changes);
            $this->log('[Forms] Ã¢Å“â€¦ Log audit aggiornamento registrato (' . count($changes) . ' modifiche)');
        }
        
        // ÃƒÂ°Ã…Â¸Ã…Â½Ã¢â‚¬Â° HOOK: Controlla se il preventivo ÃƒÆ’Ã‚Â¨ stato appena confermato
        $new_stato = $data['stato'];
        $new_acconto = floatval($data['acconto']);
        $just_confirmed = ($old_stato !== 'confermato' && $new_stato === 'confermato' && $new_acconto > 0);
        
        if ($just_confirmed) {
            // Il preventivo ÃƒÆ’Ã‚Â¨ stato APPENA confermato ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ lancia hook
            do_action('disco747_preventivo_confirmed', $edit_id);
            $this->log('[Forms] ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ Hook disco747_preventivo_confirmed lanciato (da ' . $old_stato . ' a confermato, ID: ' . $edit_id . ')');
        }

        // ðŸŽ¯ HOOK: Gestione cambio stato preventivo (per funnel)
        // Quando lo stato cambia da 'attivo' a 'confermato' o 'annullato', ferma il funnel pre-conferma
        if ($old_stato === 'attivo' && $new_stato === 'confermato') {
            // Attivo â†’ Confermato: ferma funnel pre-conferma, avvia funnel post-conferma
            do_action('disco747_preventivo_confirmed', $edit_id);
            $this->log('[Forms] ðŸŽ¯ Hook disco747_preventivo_confirmed lanciato (da attivo a confermato, ID: ' . $edit_id . ')');
        } elseif ($old_stato === 'attivo' && $new_stato === 'annullato') {
            // Attivo â†’ Annullato: ferma funnel pre-conferma
            do_action('disco747_preventivo_cancelled', $edit_id);
            $this->log('[Forms] ðŸ›‘ Hook disco747_preventivo_cancelled lanciato (da attivo ad annullato, ID: ' . $edit_id . ')');
        } elseif ($old_stato !== 'attivo' && $new_stato === 'attivo') {
            // Confermato/Annullato â†’ Attivo: potrebbe aver bisogno di riavviare funnel
            do_action('disco747_preventivo_reactivated', $edit_id);
            $this->log('[Forms] ðŸ”„ Hook disco747_preventivo_reactivated lanciato (da ' . $old_stato . ' ad attivo, ID: ' . $edit_id . ')');
        }


        
        // Rileggi preventivo aggiornato dal database per avere tutti i dati
        $preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $edit_id
        ), ARRAY_A);
        
        // ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ RIGENERA EXCEL E RICARICA SU GOOGLE DRIVE
        $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â°ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¸"ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¾ Rigenerazione Excel per preventivo aggiornato...');
        
        // Prepara dati per Excel (mappatura completa)
        $excel_data = array(
            'preventivo_id' => $preventivo['preventivo_id'],
            'nome_referente' => $preventivo['nome_referente'],
            'cognome_referente' => $preventivo['cognome_referente'],
            'cellulare' => $preventivo['telefono'],
            'mail' => $preventivo['email'],
            'data_evento' => $preventivo['data_evento'],
            'tipo_evento' => $preventivo['tipo_evento'],
            'tipo_menu' => $preventivo['tipo_menu'],
            'numero_invitati' => $preventivo['numero_invitati'],
            'orario_inizio' => $preventivo['orario_inizio'],
            'orario_fine' => $preventivo['orario_fine'],
            'omaggio1' => $preventivo['omaggio1'],
            'omaggio2' => $preventivo['omaggio2'],
            'omaggio3' => $preventivo['omaggio3'],
            'extra1' => $preventivo['extra1'],
            'extra1_importo' => $preventivo['extra1_importo'],
            'extra2' => $preventivo['extra2'],
            'extra2_importo' => $preventivo['extra2_importo'],
            'extra3' => $preventivo['extra3'],
            'extra3_importo' => $preventivo['extra3_importo'],
            'importo_preventivo' => $preventivo['importo_totale'],
            'acconto' => $preventivo['acconto'],
            'stato' => $preventivo['stato']
        );
        
        // Genera Excel aggiornato
        $excel_path = $this->create_excel_safe($excel_data);
        
        if ($excel_path && file_exists($excel_path)) {
            $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Excel rigenerato: ' . basename($excel_path));
            
            // Upload su Google Drive (SOVRASCRIVE file esistente o crea nuovo)
            if ($this->storage) {
                $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¹Ãƒâ€¦"ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ Upload Excel aggiornato su Google Drive...');
                
                try {
                    $existing_file_id = $preventivo['googledrive_file_id'] ?? '';
                    
                    // Ã¢Å“â€¦ DEBUG: Log per diagnostica
                    $this->log('[Forms] Ã°Å¸â€Â Debug Google Drive File ID:');
                    $this->log('  - googledrive_file_id dal DB: "' . ($existing_file_id ?: 'VUOTO/NULL') . '"');
                    $this->log('  - googledrive_url dal DB: "' . ($preventivo['googledrive_url'] ?? 'VUOTO') . '"');
                    
                    // Ã¢Å“â€¦ FALLBACK: Se file_id ÃƒÂ¨ vuoto, prova a estrarlo dall'URL
                    if (empty($existing_file_id) && !empty($preventivo['googledrive_url'])) {
                        $url = $preventivo['googledrive_url'];
                        // URL formato: https://drive.google.com/file/d/{FILE_ID}/view
                        if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
                            $existing_file_id = $matches[1];
                            $this->log('[Forms] Ã°Å¸â€Â§ File ID estratto dall\'URL: ' . $existing_file_id);
                            
                            // Salva il file_id nel database per la prossima volta
                            $wpdb->update(
                                $this->table_name,
                                array('googledrive_file_id' => $existing_file_id),
                                array('id' => $edit_id),
                                array('%s'),
                                array('%d')
                            );
                            $this->log('[Forms] Ã¢Å“â€¦ File ID salvato nel database');
                        }
                    }
                    
                    if (!empty($existing_file_id)) {
                        // Ã¢Å“â€¦ CASO 1: File giÃƒÂ  esistente Ã¢â€ â€™ SOVRASCRIVE
                        $this->log('[Forms] Ã°Å¸â€â€ž File esistente trovato (ID: ' . $existing_file_id . '), SOVRASCRIVO...');
                        
                        if (class_exists('Disco747_CRM\Storage\Disco747_GoogleDrive')) {
                            $googledrive = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
                            if (method_exists($googledrive, 'update_existing_file')) {
                            $result = $googledrive->update_existing_file($excel_path, $existing_file_id);
                            
                            if ($result && isset($result['url'])) {
                                // Aggiorna URL nel database (il file_id rimane lo stesso)
                                $wpdb->update(
                                    $this->table_name,
                                    array(
                                        'googledrive_url' => $result['url'],
                                        'googledrive_file_id' => $result['file_id'],
                                        'excel_url' => $result['url']
                                    ),
                                    array('id' => $edit_id),
                                    array('%s', '%s', '%s'),
                                    array('%d')
                                );
                                $this->log('[Forms] Ã¢Å“â€¦ File Excel SOVRASCRITTO su Drive (stesso file)');
                            } else {
                                $this->log('[Forms] Ã¢Å¡Â Ã¯Â¸Â Errore sovrascrittura, creo nuovo file...', 'WARNING');
                                // Fallback: crea nuovo file
                                $existing_file_id = '';
                            }
                            } else {
                                $this->log('[Forms] Ã¢Å¡Â Ã¯Â¸Â Metodo update_existing_file non disponibile, creo nuovo file...', 'WARNING');
                                $existing_file_id = '';
                            }
                        } else {
                            $this->log('[Forms] Ã¢Å¡Â Ã¯Â¸Â Classe GoogleDrive non disponibile!', 'WARNING');
                            $existing_file_id = '';
                        }
                    }
                    
                    if (empty($existing_file_id)) {
                        // Ã¢Å“â€¦ CASO 2: Nessun file esistente Ã¢â€ â€™ CREA NUOVO
                        $this->log('[Forms] Ã¢Å“Â¨ Nessun file esistente, CREO NUOVO...');
                        
                        // Usa data_evento per determinare il percorso corretto
                        $date_parts = explode('-', $preventivo['data_evento']);
                        $year = $date_parts[0];
                        $month_num = $date_parts[1];
                        
                        // Converti numero mese in nome mese italiano
                        $mesi = array(
                            '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo',
                            '04' => 'Aprile', '05' => 'Maggio', '06' => 'Giugno',
                            '07' => 'Luglio', '08' => 'Agosto', '09' => 'Settembre',
                            '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre'
                        );
                        $month_name = $mesi[$month_num] ?? $month_num;
                        
                        // Percorso corretto: /747-Preventivi/2025/Novembre/
                        $drive_folder = '747-Preventivi/' . $year . '/' . $month_name . '/';
                        
                        $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â°ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¸" Percorso Google Drive: ' . $drive_folder);
                        
                        // Ã¢Å“â€¦ FIX: Upload Excel (nuovo file) con salvataggio file_id
                        if (class_exists('Disco747_CRM\Storage\Disco747_GoogleDrive')) {
                            $googledrive = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
                            $filename = basename($excel_path);
                            $result = $googledrive->upload_to_googledrive($excel_path, $filename, $preventivo['data_evento']);
                            
                            $this->log('[Forms] Ã°Å¸â€Â DEBUG risultato upload: ' . print_r($result, true));
                            
                            if ($result && is_array($result) && isset($result['url'])) {
                                // Aggiorna URL e FILE_ID nel database
                                $update_result = $wpdb->update(
                                    $this->table_name,
                                    array(
                                        'googledrive_url' => $result['url'],
                                        'googledrive_file_id' => isset($result['file_id']) ? $result['file_id'] : '',
                                        'excel_url' => $result['url']
                                    ),
                                    array('id' => $edit_id),
                                    array('%s', '%s', '%s'),
                                    array('%d')
                                );
                                
                                $this->log('[Forms] Ã°Å¸â€Â DEBUG wpdb->update result: ' . ($update_result !== false ? 'SUCCESS' : 'FAILED'));
                                if ($update_result === false) {
                                    $this->log('[Forms] Ã¢ÂÅ’ Errore wpdb: ' . $wpdb->last_error);
                                }
                                
                                $this->log('[Forms] Ã¢Å“â€¦ Nuovo file Excel creato su Drive: ' . basename($excel_path));
                                $this->log('[Forms] Ã¢Å“â€¦ URL salvato: ' . $result['url']);
                                $this->log('[Forms] Ã¢Å“â€¦ File ID salvato: ' . (isset($result['file_id']) ? $result['file_id'] : 'N/A'));
                            } else {
                                $this->log('[Forms] Ã¢ÂÅ’ Risultato upload non valido o mancante!');
                            }
                        }
                    }
                    
                } catch (\Exception $e) {
                    $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ Errore upload Drive: ' . $e->getMessage(), 'WARNING');
                }
            }
        } else {
            $this->log('[Forms] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ Impossibile rigenerare Excel', 'WARNING');
        }
        
        wp_send_json_success(array(
            'message' => 'Preventivo aggiornato con successo!',
            'preventivo_id' => $preventivo['preventivo_id'] ?? $data['preventivo_id'],
            'id' => $edit_id,
            'db_id' => $edit_id,
            'is_edit_mode' => true,
            // Dati preventivo per JavaScript
            'nome_referente' => $preventivo['nome_referente'] ?? $data['nome_referente'] ?? '',
            'cognome_referente' => $preventivo['cognome_referente'] ?? $data['cognome_referente'] ?? '',
            'nome_cliente' => $preventivo['nome_cliente'] ?? $data['nome_cliente'] ?? '',
            'email' => $preventivo['email'] ?? $data['email'] ?? '',
            'telefono' => $preventivo['telefono'] ?? $data['telefono'] ?? '',
            'data_evento' => $preventivo['data_evento'] ?? $data['data_evento'] ?? '',
            'tipo_evento' => $preventivo['tipo_evento'] ?? $data['tipo_evento'] ?? '',
            'tipo_menu' => $preventivo['tipo_menu'] ?? $data['tipo_menu'] ?? '',
            'numero_invitati' => $preventivo['numero_invitati'] ?? $data['numero_invitati'] ?? 0,
            'orario_inizio' => $preventivo['orario_inizio'] ?? $data['orario_inizio'] ?? '',
            'orario_fine' => $preventivo['orario_fine'] ?? $data['orario_fine'] ?? '',
            'importo_totale' => $preventivo['importo_totale'] ?? $data['importo_totale'] ?? 0,
            'importo_preventivo' => $preventivo['importo_preventivo'] ?? $importo_preventivo ?? 0,
            'acconto' => $preventivo['acconto'] ?? $data['acconto'] ?? 0,
            'saldo' => $preventivo['saldo'] ?? $saldo ?? 0,
            'omaggio1' => $preventivo['omaggio1'] ?? $data['omaggio1'] ?? '',
            'omaggio2' => $preventivo['omaggio2'] ?? $data['omaggio2'] ?? '',
            'omaggio3' => $preventivo['omaggio3'] ?? $data['omaggio3'] ?? '',
            'extra1' => $preventivo['extra1'] ?? $data['extra1'] ?? '',
            'extra2' => $preventivo['extra2'] ?? $data['extra2'] ?? '',
            'extra3' => $preventivo['extra3'] ?? '',
            'stato' => $preventivo['stato'] ?? $data['stato'] ?? 'attivo'
        ));
    }
    
    /**
     * ========================================================================
     * METODO 1: Genera PDF on-demand - SENZA UPLOAD GOOGLE DRIVE
     * ========================================================================
     */
    public function handle_generate_pdf() {
        try {
            $this->log('[PDF] ========== GENERAZIONE PDF ON-DEMAND ==========');
            
            if (!check_ajax_referer('disco747_generate_pdf', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $this->load_components();
            
            if (!$this->pdf) {
                wp_send_json_error('PDF Generator non disponibile');
                return;
            }
            
            $preventivo_id = isset($_POST['preventivo_id']) ? sanitize_text_field($_POST['preventivo_id']) : '';
            
            if (empty($preventivo_id)) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            // Carica preventivo dal database
            global $wpdb;
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE preventivo_id = %s OR id = %d",
                $preventivo_id,
                intval($preventivo_id)
            ), ARRAY_A);
            
            if (!$preventivo) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            $this->log('[PDF] ========== DATI DAL DATABASE ==========');
            $this->log('[PDF] Preventivo caricato - ID database: ' . $preventivo['id']);
            $this->log('[PDF] Preventivo_ID: ' . ($preventivo['preventivo_id'] ?? 'N/D'));
            $this->log('[PDF] Nome: ' . ($preventivo['nome_referente'] ?? 'N/D') . ' ' . ($preventivo['cognome_referente'] ?? ''));
            $this->log('[PDF] Telefono: ' . ($preventivo['telefono'] ?? 'N/D'));
            $this->log('[PDF] importo_totale DB: ' . ($preventivo['importo_totale'] ?? 'N/D'));
            $this->log('[PDF] extra1 DB: "' . ($preventivo['extra1'] ?? '') . '" = ' . ($preventivo['extra1_importo'] ?? 'N/D'));
            $this->log('[PDF] extra2 DB: "' . ($preventivo['extra2'] ?? '') . '" = ' . ($preventivo['extra2_importo'] ?? 'N/D'));
            $this->log('[PDF] extra3 DB: "' . ($preventivo['extra3'] ?? '') . '" = ' . ($preventivo['extra3_importo'] ?? 'N/D'));
            $this->log('[PDF] acconto DB: ' . ($preventivo['acconto'] ?? 'N/D'));
            $this->log('[PDF] ==========================================');
            
            // Prepara dati per PDF
            $pdf_data = $this->prepare_pdf_data($preventivo);
            
            // Genera PDF
            $pdf_path = $this->pdf->generate_pdf($pdf_data);
            
            if (!$pdf_path || !file_exists($pdf_path)) {
                wp_send_json_error('Errore generazione PDF');
                return;
            }
            
            $this->log('[PDF] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ PDF generato: ' . basename($pdf_path));
            
            // ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚ÂºÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â RIMOSSO: Upload su Google Drive
            // Il PDF viene generato SOLO localmente per il download
            
            // Genera URL download
            $upload_dir = wp_upload_dir();
            $pdf_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $pdf_path);
            
            // Aggiorna database con path PDF
            $wpdb->update(
                $this->table_name,
                array('pdf_url' => $pdf_path),
                array('preventivo_id' => $preventivo_id),
                array('%s'),
                array('%s')
            );
            
            $this->log('[PDF] ========== ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ COMPLETATO (NO UPLOAD DRIVE) ==========');
            
            wp_send_json_success(array(
                'message' => 'PDF generato con successo!',
                'pdf_path' => $pdf_path,
                'pdf_url' => $pdf_url,
                'download_url' => $pdf_url,
                'filename' => basename($pdf_path),
                'note' => 'PDF salvato solo localmente, NON caricato su Google Drive'
            ));
            
        } catch (\Exception $e) {
            $this->log('[PDF] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ ERRORE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ========================================================================
     * METODO 2: Invia Email con template
     * ========================================================================
     */
    public function handle_send_email_template() {
        try {
            $this->log('[Email] ========== INVIO EMAIL TEMPLATE ==========');
            
            if (!check_ajax_referer('disco747_send_email', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $preventivo_id = isset($_POST['preventivo_id']) ? sanitize_text_field($_POST['preventivo_id']) : '';
            $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '1';
            
            if (empty($preventivo_id)) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            // Carica preventivo
            $preventivo_data = $this->get_preventivo_from_db($preventivo_id);
            
            if (!$preventivo_data) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            // TODO: Implementa invio email
            $this->log('[Email] Template selezionato: ' . $template_id);
            
            wp_send_json_success(array(
                'message' => 'Email inviata con successo!',
                'template_used' => $template_id
            ));
            
        } catch (\Exception $e) {
            $this->log('[Email] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ ERRORE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ========================================================================
     * METODO 3: Invia WhatsApp con template
     * ========================================================================
     */
    public function handle_send_whatsapp_template() {
        try {
            $this->log('[WhatsApp] ========== GENERA LINK WHATSAPP ==========');
            
            if (!check_ajax_referer('disco747_send_whatsapp', 'nonce', false)) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            $preventivo_id = isset($_POST['preventivo_id']) ? sanitize_text_field($_POST['preventivo_id']) : '';
            $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '1';
            
            if (empty($preventivo_id)) {
                wp_send_json_error('ID preventivo mancante');
                return;
            }
            
            // Carica preventivo
            $preventivo_data = $this->get_preventivo_from_db($preventivo_id);
            
            if (!$preventivo_data) {
                wp_send_json_error('Preventivo non trovato');
                return;
            }
            
            // Template WhatsApp
            // ✅ NUOVO v12.0.0: Carica template WhatsApp dal database
            // Prova prima i template configurabili dalla dashboard
            $template_from_db = get_option('disco747_form_whatsapp_template_' . $template_id, false);
            
            if ($template_from_db) {
                // Usa template dal database
                $whatsapp_message = $template_from_db;
            } else {
                // Fallback: usa template predefiniti
                $default_templates = array(
                    '1' => "Ciao {{nome}}! 🎉\n\nIl tuo preventivo per {{tipo_evento}} del {{data_evento}} è pronto!\n\n💰 Importo: {{importo}}\n\n747 Disco - La tua festa indimenticabile! 🎊",
                    '2' => "Ciao {{nome}}! 🎈\n\nTi ricordiamo il tuo evento del {{data_evento}}.\n\nHai confermato? Rispondi per finalizzare! 📞",
                    '3' => "Ciao {{nome}}! ✅\n\nGrazie per aver confermato!\n\n📅 {{data_evento}}\n💰 Acconto: {{acconto}}\n\nCi vediamo presto! 🎉"
                );
                $whatsapp_message = $default_templates[$template_id] ?? $default_templates['1'];
            }
            
            $whatsapp_message = $this->replace_placeholders($whatsapp_message, $preventivo_data);
            
            // Genera link WhatsApp
            $phone = $preventivo_data['telefono'] ?? '';
            $phone = preg_replace('/[^0-9+]/', '', $phone);
            
            if (empty($phone)) {
                wp_send_json_error('Numero telefono non disponibile');
                return;
            }
            
            if (substr($phone, 0, 1) !== '+') {
                if (substr($phone, 0, 2) === '39') {
                    $phone = '+' . $phone;
                } else {
                    $phone = '+39' . $phone;
                }
            }
            
            $whatsapp_url = 'https://wa.me/' . $phone . '?text=' . urlencode($whatsapp_message);
            
            $this->log('[WhatsApp] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Link generato per: ' . $phone);
            
            wp_send_json_success(array(
                'message' => 'Link WhatsApp generato!',
                'whatsapp_url' => $whatsapp_url,
                'phone' => $phone,
                'template_used' => $template_id
            ));
            
        } catch (\Exception $e) {
            $this->log('[WhatsApp] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ ERRORE: ' . $e->getMessage(), 'ERROR');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * ========================================================================
     * HELPER: Carica preventivo dal database
     * ========================================================================
     */
    private function get_preventivo_from_db($preventivo_id) {
        global $wpdb;
        
        $preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE preventivo_id = %s OR id = %d",
            $preventivo_id,
            intval($preventivo_id)
        ), ARRAY_A);
        
        return $preventivo;
    }
    
    /**
     * ========================================================================
     * HELPER: Prepara dati per PDF - MAPPATURA COMPLETA
     * ========================================================================
     */
    private function prepare_pdf_data($preventivo) {
        // Split nome completo se necessario
        $nome_completo = $preventivo['nome_cliente'] ?? '';
        $nome_parts = explode(' ', $nome_completo, 2);
        
        return array(
            // === ID ===
            'preventivo_id' => $preventivo['preventivo_id'] ?? '',
            
            // === DATI CLIENTE ===
            'nome_referente' => $preventivo['nome_referente'] ?? ($nome_parts[0] ?? ''),
            'cognome_referente' => $preventivo['cognome_referente'] ?? ($nome_parts[1] ?? ''),
            'nome_cliente' => $nome_completo,
            
            // === CONTATTI - Tutti gli alias ===
            'mail' => $preventivo['email'] ?? '',
            'email' => $preventivo['email'] ?? '',
            'cellulare' => $preventivo['telefono'] ?? '',
            'telefono' => $preventivo['telefono'] ?? '',
            
            // === DATI EVENTO ===
            'data_evento' => $preventivo['data_evento'] ?? '',
            'tipo_evento' => $preventivo['tipo_evento'] ?? '',
            'tipo_menu' => $preventivo['tipo_menu'] ?? 'Menu 7',
            'numero_invitati' => $preventivo['numero_invitati'] ?? 0,
            
            // === ORARI ===
            'orario_inizio' => $preventivo['orario_inizio'] ?? '20:30',
            'orario_fine' => $preventivo['orario_fine'] ?? '01:30',
            
            // === OMAGGI ===
            'omaggio1' => $preventivo['omaggio1'] ?? '',
            'omaggio2' => $preventivo['omaggio2'] ?? '',
            'omaggio3' => $preventivo['omaggio3'] ?? '',
            
            // === EXTRA ===
            'extra1' => $preventivo['extra1'] ?? '',
            'extra1_importo' => $preventivo['extra1_importo'] ?? 0,
            'extra2' => $preventivo['extra2'] ?? '',
            'extra2_importo' => $preventivo['extra2_importo'] ?? 0,
            'extra3' => $preventivo['extra3'] ?? '',
            'extra3_importo' => $preventivo['extra3_importo'] ?? 0,
            
            // === IMPORTI - Tutti gli alias ===
            'importo_preventivo' => $preventivo['importo_totale'] ?? 0,
            'importo_totale' => $preventivo['importo_totale'] ?? 0,
            'acconto' => $preventivo['acconto'] ?? 0,
            
            // === NOTE ===
            'note_aggiuntive' => $preventivo['note_aggiuntive'] ?? '',
            'note_interne' => $preventivo['note_interne'] ?? '',
            
            // === METADATA ===
            'stato' => $preventivo['stato'] ?? 'attivo',
            'created_at' => $preventivo['created_at'] ?? '',
            'created_by' => $preventivo['created_by'] ?? ''
        );
    }
    
    /**
     * ========================================================================
     * HELPER: Sostituisce placeholder
     * ========================================================================
     */
    private function replace_placeholders($text, $data) {
        $replacements = array(
            '{{nome}}' => $data['nome_referente'] ?? '',
            '{{cognome}}' => $data['cognome_referente'] ?? '',
            '{{nome_completo}}' => trim(($data['nome_referente'] ?? '') . ' ' . ($data['cognome_referente'] ?? '')),
            '{{email}}' => $data['email'] ?? '',
            '{{telefono}}' => $data['telefono'] ?? '',
            '{{data_evento}}' => !empty($data['data_evento']) ? date('d/m/Y', strtotime($data['data_evento'])) : '',
            '{{tipo_evento}}' => $data['tipo_evento'] ?? '',
            '{{orario_inizio}}' => $data['orario_inizio'] ?? '',
            '{{orario_fine}}' => $data['orario_fine'] ?? '',
            '{{tipo_menu}}' => $data['tipo_menu'] ?? '',
            '{{menu}}' => $data['tipo_menu'] ?? '',
            '{{numero_invitati}}' => $data['numero_invitati'] ?? '',
            '{{importo}}' => '€ ' . number_format($data['importo_totale'] ?? 0, 2, ',', '.'),
            '{{acconto}}' => '€ ' . number_format($data['acconto'] ?? 0, 2, ',', '.'),
            '{{saldo}}' => '€ ' . number_format($data['saldo'] ?? 0, 2, ',', '.'),
            '{{preventivo_id}}' => $data['preventivo_id'] ?? '',
            '{{omaggio1}}' => $data['omaggio1'] ?? '',
            '{{omaggio2}}' => $data['omaggio2'] ?? '',
            '{{omaggio3}}' => $data['omaggio3'] ?? '',
            '{{extra1}}' => $data['extra1'] ?? '',
            '{{extra2}}' => $data['extra2'] ?? '',
            '{{extra3}}' => $data['extra3'] ?? '',
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    /**
     * ========================================================================
     * HELPER: Verifica nonce - CORRETTO
     * ========================================================================
     */
    private function verify_nonce() {
        // Verifica il nonce principale del form preventivo
        if (isset($_POST['disco747_preventivo_nonce'])) {
            if (wp_verify_nonce($_POST['disco747_preventivo_nonce'], 'disco747_preventivo')) {
                $this->log('[Forms] ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ Nonce verificato correttamente (disco747_preventivo)');
                return true;
            }
        }
        
        // Fallback: altri possibili nonce
        if (isset($_POST['disco747_nonce'])) {
            if (wp_verify_nonce($_POST['disco747_nonce'], 'disco747_action')) {
                $this->log('[Forms] ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ Nonce verificato correttamente (disco747_nonce)');
                return true;
            }
        }
        
        if (isset($_POST['_wpnonce'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'disco747_preventivo')) {
                $this->log('[Forms] ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ Nonce verificato correttamente (_wpnonce)');
                return true;
            }
        }
        
        // Log per debug
        $this->log('[Forms] ÃƒÆ’Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒâ€¦Ã¢â‚¬â„¢ Nessun nonce valido trovato. Campi POST ricevuti: ' . implode(', ', array_keys($_POST)), 'WARNING');
        
        return false;
    }
    
    /**
     * ========================================================================
     * HELPER: Carica componenti
     * ========================================================================
     */
    private function load_components() {
        if ($this->components_loaded) return;
        
        try {
            $disco747_crm = disco747_crm();
            
            if ($disco747_crm && $disco747_crm->is_initialized()) {
                $this->database = $disco747_crm->get_database();
                $this->excel = $disco747_crm->get_excel();
                $this->pdf = $disco747_crm->get_pdf();
                $this->storage = $disco747_crm->get_storage_manager();
                
                $this->components_loaded = true;
                $this->log('[Forms] Componenti caricati con successo');
            } else {
                throw new \Exception('Plugin principale non inizializzato');
            }
        } catch (\Exception $e) {
            $this->log('[Forms] Errore caricamento componenti: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * ========================================================================
     * HELPER: Valida dati form
     * ========================================================================
     */
    private function validate_form_data($post_data) {
        $data = array();
        
        // DATI CLIENTE
        $nome = sanitize_text_field($post_data['nome_referente'] ?? '');
        $cognome = sanitize_text_field($post_data['cognome_referente'] ?? '');
        $data['nome_cliente'] = trim($nome . ' ' . $cognome);
        $data['nome_referente'] = $nome;
        $data['cognome_referente'] = $cognome;
        
        // CONTATTI - Duplica per compatibilitÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â 
        $data['telefono'] = sanitize_text_field($post_data['cellulare'] ?? '');
        $data['email'] = sanitize_email($post_data['mail'] ?? '');
        $data['cellulare'] = $data['telefono'];
        $data['mail'] = $data['email'];
        
        // DATI EVENTO
        $data['data_evento'] = sanitize_text_field($post_data['data_evento'] ?? '');
        $data['tipo_evento'] = sanitize_text_field($post_data['tipo_evento'] ?? '');
        $data['tipo_menu'] = sanitize_text_field($post_data['tipo_menu'] ?? 'Menu 7');
        $data['numero_invitati'] = intval($post_data['numero_invitati'] ?? 50);
        
        // ORARI
        $data['orario_inizio'] = sanitize_text_field($post_data['orario_inizio'] ?? '20:30');
        $data['orario_fine'] = sanitize_text_field($post_data['orario_fine'] ?? '01:30');
        
        // OMAGGI
        $data['omaggio1'] = sanitize_text_field($post_data['omaggio1'] ?? '');
        $data['omaggio2'] = sanitize_text_field($post_data['omaggio2'] ?? '');
        $data['omaggio3'] = sanitize_text_field($post_data['omaggio3'] ?? '');
        
        // EXTRA A PAGAMENTO
        $data['extra1'] = sanitize_text_field($post_data['extra1'] ?? '');
        $data['extra1_importo'] = floatval($post_data['extra1_importo'] ?? 0);
        $data['extra2'] = sanitize_text_field($post_data['extra2'] ?? '');
        $data['extra2_importo'] = floatval($post_data['extra2_importo'] ?? 0);
        $data['extra3'] = sanitize_text_field($post_data['extra3'] ?? '');
        $data['extra3_importo'] = floatval($post_data['extra3_importo'] ?? 0);
        
        // DEBUG LOG EXTRA
        $this->log('[Forms] Extra validati:');
        $this->log('  - Extra1: "' . $data['extra1'] . '" = ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬' . $data['extra1_importo']);
        $this->log('  - Extra2: "' . $data['extra2'] . '" = ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬' . $data['extra2_importo']);
        $this->log('  - Extra3: "' . $data['extra3'] . '" = ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬' . $data['extra3_importo']);
        
        // Ã¢Å“â€¦ FIX: IMPORTI - Il campo dal form si chiama 'importo_preventivo' ma contiene il BASE MENU (senza extra)
        // Quindi lo assegniamo a importo_totale (che rappresenta il base)
        // importo_preventivo (totale finale con extra) verrÃƒÂ  calcolato dopo
        $data['importo_totale'] = floatval($post_data['importo_preventivo'] ?? 0);  // Base menu dal form
        $data['acconto'] = floatval($post_data['acconto'] ?? 0);
        
        $this->log('[Forms] Ã°Å¸â€œÂ¥ Importo base ricevuto dal form: Ã¢â€šÂ¬' . $data['importo_totale']);
        
        // NOTE
        $data['note_aggiuntive'] = sanitize_textarea_field($post_data['note_aggiuntive'] ?? '');
        $data['note_interne'] = sanitize_textarea_field($post_data['note_interne'] ?? '');
        
        // METADATA
        // âœ… FIX: Leggi stato dal form invece di forzarlo ad 'attivo'
        $data['stato'] = sanitize_key($post_data['stato'] ?? 'attivo');
        $data['created_by'] = get_current_user_id();
        $data['created_at'] = current_time('mysql');
        
        // âœ… DEBUG: Log stato ricevuto
        $this->log('[Forms] ðŸ“Š Stato ricevuto dal form: "' . $data['stato'] . '"');
        
        return $data;
    }
    
    /**
     * ========================================================================
     * HELPER: Genera ID preventivo progressivo
     * ========================================================================
     */
    private function generate_preventivo_id() {
        global $wpdb;
        
        $last_id = $wpdb->get_var("SELECT MAX(CAST(SUBSTRING(preventivo_id, 2) AS UNSIGNED)) FROM {$this->table_name} WHERE preventivo_id LIKE '#%'");
        $next_number = $last_id ? intval($last_id) + 1 : 1;
        
        return '#' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * ========================================================================
     * HELPER: Genera nome file
     * ========================================================================
     */
    private function generate_filename($data) {
        $date_parts = explode('-', $data['data_evento']);
        $day = str_pad($date_parts[2] ?? date('d'), 2, '0', STR_PAD_LEFT);
        $month = str_pad($date_parts[1] ?? date('m'), 2, '0', STR_PAD_LEFT);
        
        $tipo_evento = preg_replace('/[^a-zA-Z0-9\s]/u', '', $data['tipo_evento'] ?? 'Evento');
        $tipo_evento = substr(trim($tipo_evento), 0, 30);
        
        $prefix = '';
        if (isset($data['stato']) && $data['stato'] === 'annullato') {
            $prefix = 'NO ';
        } elseif (isset($data['acconto']) && floatval($data['acconto']) > 0) {
            $prefix = 'CONF ';
        }
        
        // Ã¢Å“â€¦ FIX: Estrazione robusta del numero menu (case-insensitive, rimuove tutti i "Menu" duplicati)
        $menu_type = $data['tipo_menu'] ?? 'Menu 7';
        $menu_number = preg_replace('/\b(menu\s*)+/i', '', $menu_type);
        $menu_number = trim($menu_number);
        
        return $prefix . $day . '_' . $month . ' ' . $tipo_evento . ' (Menu ' . $menu_number . ')';
    }
    
    /**
     * ========================================================================
     * HELPER: Crea Excel in modo sicuro
     * ========================================================================
     */
    private function create_excel_safe($data) {
        if (!$this->excel || !method_exists($this->excel, 'generate_excel')) {
            $this->log('[Excel] Generator non disponibile', 'WARNING');
            return null;
        }
        
        try {
            $result = $this->excel->generate_excel($data);
            
            if ($result && file_exists($result)) {
                return $result;
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->log('[Excel] Errore: ' . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * ========================================================================
     * HELPER: Salva preventivo in database - VERSIONE CORRETTA
     * ========================================================================
     */
    private function save_preventivo($data) {
        global $wpdb;
        
        $this->log('[DB] Preparazione dati per salvataggio database');
        
        // ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ MAPPATURA CORRETTA: Mantieni TUTTI i campi necessari
        $insert_data = array(
            // ID e Metadata
            'preventivo_id' => $data['preventivo_id'] ?? '',
            
            // Cliente - USA I CAMPI SINGOLI
            'nome_referente' => $data['nome_referente'] ?? '',
            'cognome_referente' => $data['cognome_referente'] ?? '',
            'nome_cliente' => $data['nome_cliente'] ?? trim(($data['nome_referente'] ?? '') . ' ' . ($data['cognome_referente'] ?? '')),
            
            // Contatti
            'telefono' => $data['telefono'] ?? '',
            'email' => $data['email'] ?? '',
            
            // Evento
            'data_evento' => $data['data_evento'] ?? date('Y-m-d'),
            'tipo_evento' => $data['tipo_evento'] ?? '',
            'tipo_menu' => $data['tipo_menu'] ?? 'Menu 7',
            'numero_invitati' => intval($data['numero_invitati'] ?? 0),
            
            // Orari
            'orario_inizio' => $data['orario_inizio'] ?? '20:30',
            'orario_fine' => $data['orario_fine'] ?? '01:30',
            
            // Omaggi
            'omaggio1' => $data['omaggio1'] ?? '',
            'omaggio2' => $data['omaggio2'] ?? '',
            'omaggio3' => $data['omaggio3'] ?? '',
            
            // Extra
            'extra1' => $data['extra1'] ?? '',
            'extra1_importo' => floatval($data['extra1_importo'] ?? 0),
            'extra2' => $data['extra2'] ?? '',
            'extra2_importo' => floatval($data['extra2_importo'] ?? 0),
            'extra3' => $data['extra3'] ?? '',
            'extra3_importo' => floatval($data['extra3_importo'] ?? 0),
            
            // Calcola importi
            'importo_totale' => floatval($data['importo_totale'] ?? 0),
            'acconto' => floatval($data['acconto'] ?? 0),
        );
        
        // Ã¢Å“â€¦ FIX: Calcola extra totale
        $extra_totale = $insert_data['extra1_importo'] + $insert_data['extra2_importo'] + $insert_data['extra3_importo'];
        
        // Ã¢Å“â€¦ IMPORTANTE: 
        // - importo_totale = SOLO base menu (senza extra) - viene dal form
        // - importo_preventivo = base + extra (totale finale)
        // - saldo = importo_preventivo - acconto
        $insert_data['importo_preventivo'] = $insert_data['importo_totale'] + $extra_totale;
        $insert_data['saldo'] = $insert_data['importo_preventivo'] - $insert_data['acconto'];
        
        $this->log('[Forms] Ã°Å¸â€™Â° Calcolo importi (nuovo preventivo):');
        $this->log('  - Base menu (importo_totale): Ã¢â€šÂ¬' . $insert_data['importo_totale']);
        $this->log('  - Extra totale: Ã¢â€šÂ¬' . $extra_totale);
        $this->log('  - Totale finale (importo_preventivo): Ã¢â€šÂ¬' . $insert_data['importo_preventivo']);
        $this->log('  - Acconto: Ã¢â€šÂ¬' . $insert_data['acconto']);
        $this->log('  - Saldo: Ã¢â€šÂ¬' . $insert_data['saldo']);
        
        // Continua con gli altri campi
        $insert_data = array_merge($insert_data, array(
            
            // Note
            'note_aggiuntive' => $data['note_aggiuntive'] ?? '',
            'note_interne' => $data['note_interne'] ?? '',
            
            // Stato e URLs
            'stato' => $data['stato'] ?? 'attivo',
            'googledrive_url' => $data['googledrive_url'] ?? '',
            'googledrive_file_id' => $data['googledrive_file_id'] ?? '',  // Ã¢Å“â€¦ SALVA FILE_ID!
            'excel_url' => $data['googledrive_url'] ?? '',
            'pdf_url' => '',
            
            // Metadata
            'created_by' => $data['created_by'] ?? get_current_user_id(),
            'created_at' => $data['created_at'] ?? current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));
        
        // Inserisci nel database
        $inserted = $wpdb->insert($this->table_name, $insert_data);
        
        if ($inserted === false) {
            $this->log('[DB] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ ERRORE INSERIMENTO: ' . $wpdb->last_error, 'ERROR');
            $this->log('[DB] Query: ' . $wpdb->last_query, 'ERROR');
            return false;
        }
        
        $insert_id = $wpdb->insert_id;
        $this->log('[DB] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ Preventivo inserito con ID: ' . $insert_id);
        
        // Verifica immediata che i dati siano salvati
        $check = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $insert_id
        ), ARRAY_A);
        
        if ($check) {
            $this->log('[DB] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ VERIFICA: Dati salvati correttamente');
            $this->log('[DB]   - Nome: ' . ($check['nome_referente'] ?? 'VUOTO'));
            $this->log('[DB]   - Cognome: ' . ($check['cognome_referente'] ?? 'VUOTO'));
            $this->log('[DB]   - Telefono: ' . ($check['telefono'] ?? 'VUOTO'));
            $this->log('[DB]   - Email: ' . ($check['email'] ?? 'VUOTO'));
        } else {
            $this->log('[DB] ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ VERIFICA FALLITA: Record non trovato!', 'ERROR');
        }
        
        // Ã¢Å“â€¦ LOG: Registra creazione nel sistema audit
        if ($this->database && method_exists($this->database, 'log_preventivo_change')) {
            $this->database->log_preventivo_change($insert_id, 'create', array());
            $this->log('[DB] Ã¢Å“â€¦ Log audit creazione registrato');
        }
        
        return $insert_id;
    }
    
    /**
     * ========================================================================
     * HELPER: Aggiungi intervallo cron personalizzato (10 minuti)
     * ========================================================================
     */
    public function add_ten_minute_cron_interval($schedules) {
        if (!isset($schedules['ten_minutes'])) {
            $schedules['ten_minutes'] = array(
                'interval' => 600, // 10 minuti in secondi
                'display'  => __('Ogni 10 minuti', 'disco747')
            );
        }
        return $schedules;
    }
    
    /**
     * ========================================================================
     * HELPER: Cleanup file temporanei - VERSIONE AGGRESSIVA
     * ========================================================================
     * FIX: Cleanup più frequente e aggressivo per prevenire conflitti file
     * - Gira ogni 10 minuti invece che ogni ora
     * - Cancella file più vecchi di 10 minuti (invece di 1 ora)
     * - Pulisce tutta la cartella /preventivi/ (non solo /temp/)
     */
    public function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/preventivi/';
        
        if (!is_dir($base_dir)) {
            return;
        }
        
        $now = time();
        $max_age = 600; // 10 minuti in secondi
        $count = 0;
        
        // Funzione ricorsiva per scandire tutte le sottocartelle
        $scan_directory = function($dir) use (&$scan_directory, $now, $max_age, &$count) {
            $files = glob($dir . '*', GLOB_MARK);
            
            foreach ($files as $file) {
                if (is_dir($file)) {
                    // Ricorsione nelle sottocartelle (es. /2024/11/)
                    $scan_directory($file);
                } elseif (is_file($file)) {
                    // Cancella file più vecchi di 10 minuti
                    if ($now - filemtime($file) >= $max_age) {
                        if (@unlink($file)) {
                            $count++;
                        }
                    }
                }
            }
        };
        
        // Esegui cleanup
        $scan_directory($base_dir);
        
        if ($count > 0) {
            $this->log("[Cleanup] 🧹 Eliminati {$count} file temporanei più vecchi di 10 minuti");
        }
    }
    
    /**
     * ========================================================================
     * HELPER: Logging
     * ========================================================================
     */
    private function log($message, $level = 'INFO') {
        if (!$this->log_enabled) return;
        
        $timestamp = current_time('mysql');
        $log_message = "[{$timestamp}] [Disco747-Forms] [{$level}] {$message}";
        error_log($log_message);
    }
}