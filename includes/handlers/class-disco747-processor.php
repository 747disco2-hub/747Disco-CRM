<?php
/**
 * Processor Class - 747 Disco CRM  
 * VERSIONE CORRETTA: Flusso completo e logging dettagliato
 * 
 * @package    Disco747_CRM
 * @subpackage Handlers
 * @since      11.6.2-FINAL
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Handlers;

use Exception;

// Sicurezza
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Processore principale per preventivi
 */
class Disco747_Processor {

    private $database;
    private $excel_generator;
    private $pdf_generator;
    private $storage_manager;
    private $messaging;
    private $debug_mode = true;
    private $upload_dir;

    /**
     * Costruttore
     */
    public function __construct($database = null, $excel_generator = null, $pdf_generator = null, $storage_manager = null, $messaging = null) {
        $this->database = $database;
        $this->excel_generator = $excel_generator;
        $this->pdf_generator = $pdf_generator;
        $this->storage_manager = $storage_manager;
        $this->messaging = $messaging;
        
        // Setup directory upload
        $upload_info = wp_upload_dir();
        $this->upload_dir = $upload_info['basedir'] . '/preventivi/';
        
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
        
        $this->log('ÃƒÂ°Ã…Â¸Ã…Â¡Ã¢â€šÂ¬ [747Disco-Processor] v11.6.2 inizializzato');
    }

    /**
     * METODO PRINCIPALE: Processa preventivo completo
     */
    public function process_preventivo($post_data) {
        $this->log('[747Disco-Create] ========== INIZIO PROCESSAMENTO ==========');
        
        $excel_path = null;
        $pdf_path = null;
        
        try {
            // STEP 1: Valida dati
            $this->log('[747Disco-Create] STEP 1: Validazione dati');
            $data = $this->validate_and_sanitize_data($post_data);
            $this->log('[747Disco-Create] Ã¢Å“â€¦ Dati validati');
            
            // STEP 2: Genera nomi file
            $this->log('[747Disco-Create] STEP 2: Generazione nomi file');
            $filename_base = $this->generate_filename($data);
            
            // Crea sottocartella per anno/mese
            $date_parts = explode('-', $data['data_evento']);
            $year = $date_parts[0];
            $month = $date_parts[1];
            $year_month_dir = $this->upload_dir . $year . '/' . $month . '/';
            
            if (!file_exists($year_month_dir)) {
                wp_mkdir_p($year_month_dir);
            }
            
            $excel_path = $year_month_dir . $filename_base . '.xlsx';
            $pdf_path = $year_month_dir . $filename_base . '.pdf';
            
            $this->log('[747Disco-Create] Ã¢Å“â€¦ Percorsi: ' . $filename_base);
            
            // STEP 3: Genera Excel
            $this->log('[747Disco-Create] STEP 3: Generazione Excel');
            if (!$this->generate_excel_file($excel_path, $data)) {
                throw new Exception('Errore generazione Excel');
            }
            $this->log('[747Disco-Create] Ã¢Å“â€¦ Excel generato: ' . basename($excel_path));
            
            // STEP 4: Genera PDF
            $this->log('[747Disco-Create] STEP 4: Generazione PDF');
            if (!$this->generate_pdf_file($pdf_path, $data)) {
                throw new Exception('Errore generazione PDF');
            }
            $this->log('[747Disco-Create] Ã¢Å“â€¦ PDF generato: ' . basename($pdf_path));
            
            // STEP 5: Upload su Google Drive
            $this->log('[747Disco-Create] STEP 5: Upload su Google Drive');
            $uploaded_urls = $this->upload_to_storage($excel_path, $pdf_path, $data['data_evento']);
            $this->log('[747Disco-Create] Ã¢Å“â€¦ Upload completato');
            
            // STEP 6: Salva database
            $this->log('[747Disco-Create] STEP 6: Salvataggio database');
            $preventivo_id = $this->save_to_database($data, $uploaded_urls);
            $this->log('[747Disco-Create] Ã¢Å“â€¦ Salvato con ID: ' . $preventivo_id);
            
            // 🚀 HOOK: Lancia evento preventivo creato/confermato/annullato (per funnel automation)
            // ✅ FIX: Gestione corretta degli stati - solo preventivi ATTIVI entrano nel funnel
            if ($data['stato'] === 'confermato' && $data['acconto'] > 0) {
                // Se è confermato con acconto, lancia hook conferma (stoppa pre-conferma, avvia pre-evento)
                do_action('disco747_preventivo_confirmed', $preventivo_id);
                $this->log('[747Disco-Create] 🎯 Hook disco747_preventivo_confirmed lanciato (ID: ' . $preventivo_id . ')');
            } elseif ($data['stato'] === 'annullato') {
                // Se è annullato, lancia hook annullamento (non avvia alcun funnel)
                do_action('disco747_preventivo_cancelled', $preventivo_id);
                $this->log('[747Disco-Create] 🛑 Hook disco747_preventivo_cancelled lanciato (ID: ' . $preventivo_id . ')');
            } elseif ($data['stato'] === 'attivo') {
                // Solo se è ATTIVO, lancia hook creazione (avvia funnel pre-conferma)
                do_action('disco747_preventivo_created', $preventivo_id);
                $this->log('[747Disco-Create] 🎯 Hook disco747_preventivo_created lanciato (ID: ' . $preventivo_id . ')');
            }
            // Nota: Per design, uno stato 'confermato' deve sempre avere acconto > 0 (vedi calculate_stato())
            // Se per qualche motivo uno stato non corrisponde a nessuna condizione, non lancia hook
            // (questo previene preventivi in stati inconsistenti dall'entrare nel funnel)
            
            // STEP 7: Upsert dashboard
            $this->log('[747Disco-Create] STEP 7: Upsert dashboard');
            $this->upsert_dashboard($preventivo_id, $data, $uploaded_urls);
            $this->log('[747Disco-Create] Ã¢Å“â€¦ Dashboard aggiornata');
            
            // STEP 8: Messaggi automatici
            if (($data['send_mode'] ?? 'none') !== 'none') {
                $this->log('[747Disco-Create] STEP 8: Invio messaggi');
                $this->handle_automatic_communications($preventivo_id, $data);
                $this->log('[747Disco-Create] Ã¢Å“â€¦ Messaggi inviati');
            }
            
            $this->log('[747Disco-Create] ========== Ã¢Å“â€¦Ã¢Å“â€¦Ã¢Å“â€¦ SUCCESSO! ID: ' . $preventivo_id . ' ==========');
            
            // Prepara risposta completa con tutti i dati necessari
            $response_data = array(
                'success' => true,
                'preventivo_id' => $preventivo_id,
                'id' => $preventivo_id,
                'filename' => $filename_base,
                'urls' => $uploaded_urls,
                'local_files' => array(
                    'excel' => $excel_path,
                    'pdf' => $pdf_path
                ),
                'message' => 'Preventivo ' . (isset($data['edit_id']) ? 'aggiornato' : 'creato') . ' con successo!',
                // Dati preventivo per JavaScript
                'nome_referente' => $data['nome_referente'] ?? '',
                'cognome_referente' => $data['cognome_referente'] ?? '',
                'nome_cliente' => trim(($data['nome_referente'] ?? '') . ' ' . ($data['cognome_referente'] ?? '')),
                'email' => $data['email'] ?? '',
                'telefono' => $data['telefono'] ?? '',
                'data_evento' => $data['data_evento'] ?? '',
                'tipo_evento' => $data['tipo_evento'] ?? '',
                'tipo_menu' => $data['tipo_menu'] ?? '',
                'numero_invitati' => $data['numero_invitati'] ?? 0,
                'importo_totale' => $data['importo_totale'] ?? 0,
                'acconto' => $data['acconto'] ?? 0
            );
            
            return $response_data;
            
        } catch (Exception $e) {
            $this->log('[747Disco-Create] Ã¢ÂÅ’ ERRORE: ' . $e->getMessage(), 'ERROR');
            
            return array(
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Valida e sanitizza dati
     */
    private function validate_and_sanitize_data($post_data) {
        return array(
            // Evento
            'data_evento' => sanitize_text_field($post_data['data_evento'] ?? date('Y-m-d')),
            'tipo_evento' => sanitize_text_field($post_data['tipo_evento'] ?? ''),
            'tipo_menu' => sanitize_text_field($post_data['tipo_menu'] ?? 'Menu 7'),
            'numero_invitati' => intval($post_data['numero_invitati'] ?? 50),
            'orario_evento' => sanitize_text_field($post_data['orario_evento'] ?? '19:00-23:00'),
            
            // Cliente
            'nome_cliente' => sanitize_text_field($post_data['nome_cliente'] ?? ''),
            'telefono' => sanitize_text_field($post_data['telefono'] ?? ''),
            'email' => sanitize_email($post_data['email'] ?? ''),
            
            // Economici
            'importo_totale' => floatval($post_data['importo_totale'] ?? 0),
            'acconto' => floatval($post_data['acconto'] ?? 0),
            
            // Extra
            'omaggio1' => sanitize_text_field($post_data['omaggio1'] ?? ''),
            'omaggio2' => sanitize_text_field($post_data['omaggio2'] ?? ''),
            'omaggio3' => sanitize_text_field($post_data['omaggio3'] ?? ''),
            'extra1' => sanitize_text_field($post_data['extra1'] ?? ''),
            'extra2' => sanitize_text_field($post_data['extra2'] ?? ''),
            'extra3' => sanitize_text_field($post_data['extra3'] ?? ''),
            
            // Stato
            'stato' => $this->calculate_stato($post_data),
            'send_mode' => sanitize_key($post_data['send_mode'] ?? 'none'),
            
            // Metadata
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
    }

    /**
     * Calcola stato preventivo
     */
    private function calculate_stato($post_data) {
        if (isset($post_data['annullato']) && $post_data['annullato']) {
            return 'annullato';
        }
        
        $acconto = floatval($post_data['acconto'] ?? 0);
        return $acconto > 0 ? 'confermato' : 'attivo';
    }

    /**
     * Genera nome file secondo regole
     */
    private function generate_filename($data) {
        // Estrai data
        $date_parts = explode('-', $data['data_evento']);
        $day = str_pad($date_parts[2] ?? date('d'), 2, '0', STR_PAD_LEFT);
        $month = str_pad($date_parts[1] ?? date('m'), 2, '0', STR_PAD_LEFT);
        
        // Sanitizza tipo evento
        $tipo_evento = preg_replace('/[^a-zA-Z0-9\s]/u', '', $data['tipo_evento'] ?? 'Evento');
        $tipo_evento = substr(trim($tipo_evento), 0, 30);
        
        // Determina prefisso
        $prefix = '';
        if ($data['stato'] === 'annullato') {
            $prefix = 'NO ';
        } elseif ($data['stato'] === 'confermato') {
            $prefix = 'CONF ';
        }
        
        // ✅ FIX: Estrazione robusta del numero menu (case-insensitive, rimuove tutti i "Menu" duplicati)
        $menu_type = $data['tipo_menu'] ?? 'Menu 7';
        $menu_number = preg_replace('/\b(menu\s*)+/i', '', $menu_type);
        $menu_number = trim($menu_number);
        
        return $prefix . $day . '_' . $month . ' ' . $tipo_evento . ' (Menu ' . $menu_number . ')';
    }

    /**
     * Genera file Excel
     */
    private function generate_excel_file($excel_path, $data) {
        if (!$this->excel_generator) {
            $this->log('[747Disco-Create] Ã¢Å¡Â Ã¯Â¸Â Excel generator non disponibile', 'WARNING');
            return false;
        }
        
        try {
            $result = $this->excel_generator->generate_excel($data);
            
            if ($result && file_exists($result)) {
                // Sposta in posizione corretta se necessario
                if ($result !== $excel_path) {
                    copy($result, $excel_path);
                }
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log('[747Disco-Create] Errore Excel: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Genera file PDF
     */
    private function generate_pdf_file($pdf_path, $data) {
        if (!$this->pdf_generator) {
            $this->log('[747Disco-Create] Ã¢Å¡Â Ã¯Â¸Â PDF generator non disponibile', 'WARNING');
            return false;
        }
        
        try {
            $result = $this->pdf_generator->generate_pdf($data);
            
            if ($result && file_exists($result)) {
                // Sposta in posizione corretta se necessario
                if ($result !== $pdf_path) {
                    copy($result, $pdf_path);
                }
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log('[747Disco-Create] Errore PDF: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Upload su Google Drive
     */
    private function upload_to_storage($excel_path, $pdf_path, $data_evento) {
        $uploaded_urls = array();
        
        if (!$this->storage_manager) {
            $this->log('[747Disco-Create] Ã¢Å¡Â Ã¯Â¸Â Storage manager non disponibile', 'WARNING');
            return $uploaded_urls;
        }
        
        try {
            // Calcola percorso: /747-Preventivi/AAAA/MMMM/
            $date_parts = explode('-', $data_evento);
            $year = $date_parts[0];
            $month = $date_parts[1];
            $folder_path = '747-Preventivi/' . $year . '/' . $month . '/';
            
            // Upload Excel
            if ($excel_path && file_exists($excel_path)) {
                $excel_url = $this->storage_manager->upload_file($excel_path, $folder_path);
                if ($excel_url) {
                    $uploaded_urls['excel_url'] = $excel_url;
                    $this->log('[747Disco-Create] Ã¢Å“â€¦ Excel su Drive: ' . basename($excel_path));
                }
            }
            
            // Upload PDF
            if ($pdf_path && file_exists($pdf_path)) {
                $pdf_url = $this->storage_manager->upload_file($pdf_path, $folder_path);
                if ($pdf_url) {
                    $uploaded_urls['pdf_url'] = $pdf_url;
                    $this->log('[747Disco-Create] Ã¢Å“â€¦ PDF su Drive: ' . basename($pdf_path));
                }
            }
            
            // URL compatibilitÃƒÂ 
            if (!empty($uploaded_urls)) {
                $uploaded_urls['googledrive_url'] = $uploaded_urls['pdf_url'] ?? $uploaded_urls['excel_url'] ?? '';
            }
            
            return $uploaded_urls;
            
        } catch (Exception $e) {
            $this->log('[747Disco-Create] Errore upload: ' . $e->getMessage(), 'ERROR');
            return $uploaded_urls;
        }
    }

    /**
     * Salva nel database
     */
    private function save_to_database($data, $uploaded_urls) {
        if (!$this->database) {
            throw new Exception('Database non disponibile');
        }
        
        $save_data = array_merge($data, $uploaded_urls);
        
        $preventivo_id = $this->database->insert_preventivo($save_data);
        
        if (!$preventivo_id) {
            throw new Exception('Errore inserimento database');
        }
        
        return $preventivo_id;
    }

    /**
     * Upsert dashboard
     */
    private function upsert_dashboard($preventivo_id, $data, $urls) {
        if (!$this->database) {
            return;
        }
        
        $dashboard_data = array_merge($data, $urls);
        $dashboard_data['id'] = $preventivo_id;
        
        if (method_exists($this->database, 'upsert_preventivo')) {
            $this->database->upsert_preventivo($dashboard_data);
        } else {
            $this->database->update_preventivo($preventivo_id, $dashboard_data);
        }
    }

    /**
     * Gestisce comunicazioni
     */
    private function handle_automatic_communications($preventivo_id, $data) {
        if (!$this->messaging) {
            return;
        }
        
        try {
            $this->messaging->send_new_preventivo_notification($preventivo_id, $data);
        } catch (Exception $e) {
            $this->log('Errore messaggi: ' . $e->getMessage(), 'WARNING');
        }
    }

    /**
     * Logging
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode) {
            if (function_exists('disco747_log')) {
                disco747_log($message, $level);
            } else {
                error_log($message);
            }
        }
    }
}