<?php
/**
 * Classe per sincronizzazione preventivi da Google Drive
 * VERSIONE 11.8.1-FIXED: Con metodo is_available() e preventivo_id autogenerato
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @since      11.8.1
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_GoogleDrive_Sync {

    private $googledrive;
    private $database;
    private $preventivi_cache = null;
    private $cache_duration = 300;
    private $debug_mode = true;
    private $sync_available = false;
    private $last_error = '';

    /**
     * Costruttore
     */
    public function __construct($googledrive_instance = null) {
        $session_id = 'INIT_' . date('His') . '_' . wp_rand(100, 999);
        
        try {
            $this->log("=== DEBUG SESSION {$session_id} CONSTRUCTOR START ===");
            
            if ($googledrive_instance) {
                $this->googledrive = $googledrive_instance;
                $this->sync_available = true;
                $this->log("DEBUG: GoogleDrive instance fornita esternamente");
            } else {
                $this->log("DEBUG: Cerco di caricare classe GoogleDrive autonomamente...");
                if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                    $this->googledrive = new \Disco747_CRM\Storage\Disco747_GoogleDrive();
                    $this->sync_available = true;
                    $this->log("DEBUG: Classe GoogleDrive trovata e istanziata");
                } else {
                    $this->log("DEBUG: Classe GoogleDrive NON trovata", 'WARNING');
                    $this->sync_available = false;
                }
            }
            
            // Carica database handler
            if (function_exists('disco747_crm')) {
                $this->database = disco747_crm()->get_database();
                $this->log("DEBUG: Database handler caricato");
            }
            
            $this->log("DEBUG: GoogleDrive Sync Handler inizializzato (disponibile: " . ($this->sync_available ? 'SI' : 'NO') . ")");
            $this->log("=== DEBUG SESSION {$session_id} CONSTRUCTOR END ===");
            
        } catch (\Exception $e) {
            $this->log("DEBUG: Errore inizializzazione GoogleDrive Sync: " . $e->getMessage(), 'ERROR');
            $this->sync_available = false;
        }
    }

    /**
     * ✅ METODO PUBBLICO: Verifica se Google Drive Sync è disponibile
     * 
     * @return bool
     */
    public function is_available() {
        return $this->sync_available;
    }

    /**
     * METODO BATCH SCAN - Processa file Excel da Google Drive
     */
    public function scan_excel_files_batch($year = null, $month = null) {
        $this->log('[747Disco-Scan] ========== INIZIO BATCH SCAN ==========');
        
        $messages = array();
        $new_count = 0;
        $updated_count = 0;
        $error_count = 0;
        $total_files = 0;
        
        try {
            // Step 1: Verifica token
            $token = $this->googledrive->get_valid_access_token();
            if (!$token) {
                throw new \Exception('Token Google Drive non valido');
            }
            $this->log('[747Disco-Scan] Token ottenuto con successo');
            $messages[] = '✅ Token Google Drive ottenuto';
            
            // Step 2: Trova cartella principale
            $main_folder_id = $this->find_main_folder_safe($token);
            if (!$main_folder_id) {
                throw new \Exception('Cartella /747-Preventivi/ non trovata');
            }
            $this->log("[747Disco-Scan] Cartella 747-Preventivi trovata: {$main_folder_id}");
            $messages[] = '✅ Cartella /747-Preventivi/ trovata';
            
            // Step 3: Trova file Excel con filtri anno/mese
            $all_files = $this->scan_excel_files_with_filters($main_folder_id, $token, $year, $month);
            $total_files = count($all_files);
            $this->log("[747Disco-Scan] Trovati {$total_files} file Excel totali");
            $messages[] = "📊 Trovati {$total_files} file Excel totali";
            
            if ($total_files === 0) {
                $messages[] = '⚠️ Nessun file Excel trovato con i filtri specificati';
                return array(
                    'success' => true,
                    'total_files' => 0,
                    'processed' => 0,
                    'new_records' => 0,
                    'updated_records' => 0,
                    'errors' => 0,
                    'messages' => $messages
                );
            }
            
            // Step 4: Processa file in batch di 2
            $batch_size = 2;
            $batches = array_chunk($all_files, $batch_size);
            $total_batches = count($batches);
            
            $this->log("[747Disco-Scan] Processamento in {$total_batches} batch da {$batch_size} file");
            $messages[] = "🔄 Processamento in {$total_batches} batch da {$batch_size} file";
            
            foreach ($batches as $batch_index => $batch_files) {
                $batch_num = $batch_index + 1;
                $this->log("[747Disco-Scan] === BATCH {$batch_num}/{$total_batches} ===");
                $messages[] = "📦 Batch {$batch_num}/{$total_batches}";
                
                foreach ($batch_files as $file_index => $file) {
                    $file_number = ($batch_index * $batch_size) + $file_index + 1;
                    $file_name = $file['filename'] ?? 'Unknown';
                    
                    $this->log("[747Disco-Scan] Processamento file {$file_number}/{$total_files}: {$file_name}");
                    
                    try {
                        $result = $this->process_single_excel_file($file, $token);
                        
                        if ($result['success']) {
                            if ($result['action'] === 'inserted') {
                                $new_count++;
                                $messages[] = "✅ Nuovo: {$file_name}";
                            } else {
                                $updated_count++;
                                $messages[] = "🔄 Aggiornato: {$file_name}";
                            }
                        } else {
                            $error_count++;
                            $messages[] = "❌ Errore: {$file_name} - " . ($result['error'] ?? 'Errore sconosciuto');
                        }
                        
                    } catch (\Exception $e) {
                        $error_count++;
                        $messages[] = "❌ Errore: {$file_name} - " . $e->getMessage();
                        $this->log("[747Disco-Scan] Errore processamento: " . $e->getMessage(), 'ERROR');
                    }
                }
                
                // Pausa tra batch per evitare timeout
                if ($batch_index < $total_batches - 1) {
                    sleep(1);
                }
            }
            
            $messages[] = '✅ Batch scan completato';
            $messages[] = "📊 Risultati: {$new_count} nuovi, {$updated_count} aggiornati, {$error_count} errori";
            
        } catch (\Exception $e) {
            $messages[] = '❌ Errore batch scan: ' . $e->getMessage();
            $this->log('[747Disco-Scan] Errore: ' . $e->getMessage(), 'ERROR');
        }
        
        $this->log('[747Disco-Scan] ========== FINE BATCH SCAN ==========');
        
        return array(
            'success' => $error_count === 0,
            'total_files' => $total_files,
            'processed' => $new_count + $updated_count,
            'new_records' => $new_count,
            'updated_records' => $updated_count,
            'errors' => $error_count,
            'messages' => $messages
        );
    }

    /**
     * Processa singolo file Excel
     */
    private function process_single_excel_file($file, $token) {
        $this->log('[PROCESS] Inizio processamento: ' . $file['filename']);
        
        $temp_file_path = null;
        
        try {
            // Download file temporaneo
            $file_id = $file['googledrive_id'];
            $temp_result = $this->download_file_to_temp($file_id, $token);
            
            if (!$temp_result['success']) {
                throw new \Exception('Errore download: ' . $temp_result['error']);
            }
            
            $temp_file_path = $temp_result['path'];
            $this->log('[PROCESS] File scaricato: ' . $temp_file_path);
            
            // Estrai dati dal file Excel
            $extracted_data = $this->extract_data_from_excel($temp_file_path);
            
            if (!$extracted_data) {
                throw new \Exception('Impossibile estrarre dati dal file Excel');
            }
            
            $this->log('[PROCESS] Dati estratti: cliente=' . ($extracted_data['nome_cliente'] ?? 'N/A'));
            
            // Aggiungi metadati Google Drive
            $extracted_data['googledrive_file_id'] = $file_id;
            $extracted_data['googledrive_url'] = 'https://drive.google.com/file/d/' . $file_id . '/view';
            $extracted_data['excel_url'] = '';
            $extracted_data['pdf_url'] = '';
            
            // Salva nel database
            $save_result = $this->save_to_database($extracted_data);
            
            // Cleanup file temporaneo
            if (file_exists($temp_file_path)) {
                unlink($temp_file_path);
                $this->log('[PROCESS] ✅ File temp cancellato: ' . $temp_file_path);
            }
            
            return $save_result;
            
        } catch (\Exception $e) {
            // Cleanup in caso di errore
            if ($temp_file_path && file_exists($temp_file_path)) {
                unlink($temp_file_path);
            }
            
            $this->log('[PROCESS] Errore: ' . $e->getMessage(), 'ERROR');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * ✅ METODO CORRETTO: Salva o aggiorna preventivo nel database con preventivo_id autogenerato
     */
    private function save_to_database($data) {
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            
            // Controlla se esiste già tramite googledrive_file_id
            $existing = null;
            if (!empty($data['googledrive_file_id'])) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE googledrive_file_id = %s",
                    $data['googledrive_file_id']
                ));
            }
            
            if ($existing) {
                // UPDATE preventivo esistente
                $this->log('[747Disco-Scan] Preventivo esistente trovato (ID: ' . $existing->id . '), UPDATE');
                
                $update_data = array(
                    'data_evento' => $data['data_evento'] ?? $existing->data_evento,
                    'tipo_evento' => $data['tipo_evento'] ?? $existing->tipo_evento,
                    'tipo_menu' => $data['tipo_menu'] ?? $existing->tipo_menu,
                    'numero_invitati' => intval($data['numero_invitati'] ?? $existing->numero_invitati),
                    'orario_evento' => $data['orario_evento'] ?? $existing->orario_evento,
                    'nome_cliente' => $data['nome_cliente'] ?? $existing->nome_cliente,
                    'nome_referente' => $data['nome_referente'] ?? $existing->nome_referente,
                    'cognome_referente' => $data['cognome_referente'] ?? $existing->cognome_referente,
                    'telefono' => $data['telefono'] ?? $existing->telefono,
                    'email' => $data['email'] ?? $existing->email,
                    'importo_totale' => floatval($data['importo_totale'] ?? $existing->importo_totale),
                    'acconto' => floatval($data['acconto'] ?? $existing->acconto),
                    'saldo' => floatval($data['saldo'] ?? $existing->saldo),
                    'omaggio1' => $data['omaggio1'] ?? $existing->omaggio1,
                    'omaggio2' => $data['omaggio2'] ?? $existing->omaggio2,
                    'omaggio3' => $data['omaggio3'] ?? $existing->omaggio3,
                    'extra1' => $data['extra1'] ?? $existing->extra1,
                    'extra1_importo' => floatval($data['extra1_importo'] ?? $existing->extra1_importo),
                    'extra2' => $data['extra2'] ?? $existing->extra2,
                    'extra2_importo' => floatval($data['extra2_importo'] ?? $existing->extra2_importo),
                    'extra3' => $data['extra3'] ?? $existing->extra3,
                    'extra3_importo' => floatval($data['extra3_importo'] ?? $existing->extra3_importo),
                    'stato' => $data['stato'] ?? $existing->stato,
                    'googledrive_url' => $data['googledrive_url'] ?? $existing->googledrive_url,
                    'googledrive_file_id' => $data['googledrive_file_id'] ?? $existing->googledrive_file_id,
                    'updated_at' => current_time('mysql')
                );
                
                $result = $wpdb->update(
                    $table_name,
                    $update_data,
                    array('id' => $existing->id),
                    array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
                
                if ($result === false) {
                    $this->log('[747Disco-Scan] Errore update: ' . $wpdb->last_error, 'ERROR');
                    return array('success' => false, 'error' => $wpdb->last_error);
                }
                
                // ✅ LOG: Registra aggiornamento da sync Google Drive
                $database = disco747_crm()->get_database();
                if ($database && method_exists($database, 'log_preventivo_change')) {
                    $database->log_preventivo_change($existing->id, 'update', array(
                        'sync_source' => array('old' => '', 'new' => 'Google Drive Sync')
                    ));
                }
                
                return array(
                    'success' => true,
                    'id' => $existing->id,
                    'action' => 'updated'
                );
                
            } else {
                // ✅ INSERT nuovo preventivo con preventivo_id AUTOGENERATO
                $this->log('[DB] Preventivo nuovo, INSERT');
                
                // ✅ GENERA preventivo_id PROGRESSIVO
                $max_id = $wpdb->get_var("
                    SELECT MAX(CAST(preventivo_id AS UNSIGNED)) 
                    FROM {$table_name}
                    WHERE preventivo_id != '' AND preventivo_id IS NOT NULL
                ");
                
                $next_id = intval($max_id) + 1;
                $preventivo_id = str_pad($next_id, 3, '0', STR_PAD_LEFT); // 001, 002, 003...
                
                $this->log("[DB] ✅ Generato preventivo_id: {$preventivo_id}");
                
                $insert_data = array(
                    'preventivo_id' => $preventivo_id, // ✅ CAMPO OBBLIGATORIO
                    'data_evento' => $data['data_evento'] ?? date('Y-m-d'),
                    'tipo_evento' => $data['tipo_evento'] ?? '',
                    'tipo_menu' => $data['tipo_menu'] ?? '',
                    'numero_invitati' => intval($data['numero_invitati'] ?? 0),
                    'orario_evento' => $data['orario_evento'] ?? '',
                    'nome_cliente' => $data['nome_cliente'] ?? '',
                    'nome_referente' => $data['nome_referente'] ?? '',
                    'cognome_referente' => $data['cognome_referente'] ?? '',
                    'telefono' => $data['telefono'] ?? '',
                    'email' => $data['email'] ?? '',
                    'importo_totale' => floatval($data['importo_totale'] ?? 0),
                    'acconto' => floatval($data['acconto'] ?? 0),
                    'saldo' => floatval($data['saldo'] ?? 0),
                    'omaggio1' => $data['omaggio1'] ?? '',
                    'omaggio2' => $data['omaggio2'] ?? '',
                    'omaggio3' => $data['omaggio3'] ?? '',
                    'extra1' => $data['extra1'] ?? '',
                    'extra1_importo' => floatval($data['extra1_importo'] ?? 0),
                    'extra2' => $data['extra2'] ?? '',
                    'extra2_importo' => floatval($data['extra2_importo'] ?? 0),
                    'extra3' => $data['extra3'] ?? '',
                    'extra3_importo' => floatval($data['extra3_importo'] ?? 0),
                    'stato' => $data['stato'] ?? 'attivo',
                    'excel_url' => '',
                    'pdf_url' => '',
                    'googledrive_url' => $data['googledrive_url'] ?? '',
                    'googledrive_file_id' => $data['googledrive_file_id'] ?? '',
                    'created_at' => current_time('mysql'),
                    'created_by' => get_current_user_id(),
                    'updated_at' => current_time('mysql')
                );
                
                $result = $wpdb->insert(
                    $table_name,
                    $insert_data,
                    array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
                );
                
                if ($result === false) {
                    $this->log('[DB] Errore insert: ' . $wpdb->last_error, 'ERROR');
                    return array('success' => false, 'error' => $wpdb->last_error);
                }
                
                $insert_id = $wpdb->insert_id;
                $this->log("[DB] ✅ Preventivo inserito con ID: {$insert_id}");
                
                // ✅ LOG: Registra creazione da sync Google Drive
                $database = disco747_crm()->get_database();
                if ($database && method_exists($database, 'log_preventivo_change')) {
                    $database->log_preventivo_change($insert_id, 'create', array(
                        'sync_source' => array('old' => '', 'new' => 'Google Drive Sync')
                    ));
                }
                
                return array(
                    'success' => true,
                    'id' => $insert_id,
                    'action' => 'inserted'
                );
            }
            
        } catch (\Exception $e) {
            $this->log('[DB] Exception: ' . $e->getMessage(), 'ERROR');
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Download file temporaneo
     */
    private function download_file_to_temp($file_id, $token) {
        try {
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/disco747-temp';
            
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $temp_filename = 'temp_' . time() . '_' . sanitize_file_name(basename($file_id)) . '.xlsx';
            $temp_path = $temp_dir . '/' . $temp_filename;
            
            $download_url = 'https://www.googleapis.com/drive/v3/files/' . $file_id . '?alt=media';
            
            $response = wp_remote_get($download_url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'error' => $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            
            if (empty($body)) {
                return array('success' => false, 'error' => 'File vuoto');
            }
            
            file_put_contents($temp_path, $body);
            
            $this->log('[DOWNLOAD] File salvato: ' . $temp_path . ' (' . strlen($body) . ' bytes)');
            
            return array('success' => true, 'path' => $temp_path);
            
        } catch (\Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Estrai dati da file Excel
     */
    private function extract_data_from_excel($file_path) {
        try {
            $this->log('[EXCEL] Apertura file: ' . $file_path);
            
            require_once DISCO747_CRM_PLUGIN_DIR . 'vendor/autoload.php';
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $sheet = $spreadsheet->getActiveSheet();
            
            // Leggi celle specifiche secondo le specifiche richieste
            $data = array(
                // Dati evento (specifiche richieste)
                'data_evento' => $this->normalize_date($sheet->getCell('C6')->getValue()),
                'tipo_evento' => trim($sheet->getCell('C7')->getValue() ?? ''),
                'tipo_menu' => trim($sheet->getCell('B1')->getValue() ?? ''),
                'orario_evento' => trim($sheet->getCell('C8')->getValue() ?? ''),
                'numero_invitati' => intval($sheet->getCell('C9')->getValue() ?? 0),
                
                // Dati cliente (specifiche richieste)
                'nome_referente' => trim($sheet->getCell('C11')->getValue() ?? ''),
                'cognome_referente' => trim($sheet->getCell('C12')->getValue() ?? ''),
                'telefono' => trim($sheet->getCell('C14')->getValue() ?? ''),
                'email' => trim($sheet->getCell('C15')->getValue() ?? ''),
                
                // Dati economici (specifiche richieste)
                'importo_totale' => floatval($sheet->getCell('F27')->getValue() ?? 0),
                'acconto' => floatval($sheet->getCell('F28')->getValue() ?? 0),
                'saldo' => floatval($sheet->getCell('F30')->getValue() ?? 0),
                
                // Omaggi (specifiche richieste)
                'omaggio1' => trim($sheet->getCell('C17')->getValue() ?? ''),
                'omaggio2' => trim($sheet->getCell('C18')->getValue() ?? ''),
                'omaggio3' => trim($sheet->getCell('C19')->getValue() ?? ''),
                
                // Extra a pagamento (specifiche richieste)
                'extra1' => trim($sheet->getCell('B33')->getValue() ?? ''),
                'extra1_importo' => floatval($sheet->getCell('F33')->getValue() ?? 0),
                'extra2' => trim($sheet->getCell('B34')->getValue() ?? ''),
                'extra2_importo' => floatval($sheet->getCell('F34')->getValue() ?? 0),
                'extra3' => trim($sheet->getCell('B35')->getValue() ?? ''),
                'extra3_importo' => floatval($sheet->getCell('F35')->getValue() ?? 0),
                
                // Metadati
                'nome_cliente' => trim($sheet->getCell('C11')->getValue() ?? '') . ' ' . trim($sheet->getCell('C12')->getValue() ?? ''),
                'stato' => 'attivo'
            );
            
            $this->log('[EXCEL] Dati estratti: ' . $data['nome_cliente'] . ' - ' . $data['data_evento']);
            
            return $data;
            
        } catch (\Exception $e) {
            $this->log('[EXCEL] Errore: ' . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Normalizza data Excel
     */
    private function normalize_date($value) {
        if (empty($value)) {
            return date('Y-m-d');
        }
        
        // Se è già formato Y-m-d
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        
        // Se è formato d/m/Y
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            return $matches[3] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }
        
        // Se è numero seriale Excel
        if (is_numeric($value) && $value > 25569) {
            $unix_date = ($value - 25569) * 86400;
            return date('Y-m-d', $unix_date);
        }
        
        return date('Y-m-d');
    }

    /**
     * Trova cartella principale
     */
    private function find_main_folder_safe($token) {
        try {
            $query = "mimeType='application/vnd.google-apps.folder' and name='747-Preventivi' and trashed=false";
            
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id,name)',
                'pageSize' => 10
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['files']) && !empty($data['files'])) {
                    return $data['files'][0]['id'];
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->log("Errore ricerca cartella: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Scansione file Excel con filtri anno/mese
     */
    private function scan_excel_files_with_filters($main_folder_id, $token, $year = null, $month = null) {
        $all_files = array();
        
        try {
            // Se anno specificato, cerca cartella anno
            if ($year) {
                $year_folder_id = $this->find_year_folder($main_folder_id, $token, $year);
                if (!$year_folder_id) {
                    $this->log("[747Disco-Scan] Cartella anno {$year} non trovata");
                    return array();
                }
                
                // Se mese specificato, cerca cartella mese
                if ($month) {
                    $month_folder_id = $this->find_month_folder($year_folder_id, $token, $month);
                    if (!$month_folder_id) {
                        $this->log("[747Disco-Scan] Cartella mese {$month} non trovata");
                        return array();
                    }
                    $all_files = $this->scan_excel_files_in_folder($month_folder_id, $token);
                } else {
                    // Scansiona tutti i mesi dell'anno
                    $all_files = $this->scan_all_excel_files_recursive($year_folder_id, $token);
                }
            } else {
                // Scansiona tutto
                $all_files = $this->scan_all_excel_files_recursive($main_folder_id, $token);
            }
            
            $this->log("[747Disco-Scan] Filtri applicati - Anno: " . ($year ?: 'tutti') . ", Mese: " . ($month ?: 'tutti'));
            
        } catch (\Exception $e) {
            $this->log("[747Disco-Scan] Errore scansione con filtri: " . $e->getMessage(), 'ERROR');
        }
        
        return $all_files;
    }

    /**
     * Trova cartella anno
     */
    private function find_year_folder($parent_id, $token, $year) {
        $query = "'{$parent_id}' in parents and mimeType='application/vnd.google-apps.folder' and name='{$year}' and trashed=false";
        
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q' => $query,
            'fields' => 'files(id,name)',
            'pageSize' => 1
        ));
        
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['files'][0]['id'])) {
                return $data['files'][0]['id'];
            }
        }
        
        return null;
    }

    /**
     * Trova cartella mese
     */
    private function find_month_folder($parent_id, $token, $month) {
        $query = "'{$parent_id}' in parents and mimeType='application/vnd.google-apps.folder' and name='{$month}' and trashed=false";
        
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q' => $query,
            'fields' => 'files(id,name)',
            'pageSize' => 1
        ));
        
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['files'][0]['id'])) {
                return $data['files'][0]['id'];
            }
        }
        
        return null;
    }

    /**
     * Scansiona file Excel in una cartella specifica
     */
    private function scan_excel_files_in_folder($folder_id, $token) {
        $files = array();
        
        try {
            $query = "'{$folder_id}' in parents and (mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or mimeType='application/vnd.ms-excel') and trashed=false";
            
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id,name,size,modifiedTime)',
                'pageSize' => 100
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['files'])) {
                    foreach ($data['files'] as $file) {
                        $files[] = array(
                            'googledrive_id' => $file['id'],
                            'filename' => $file['name'],
                            'file_size' => $file['size'] ?? 0,
                            'modified_time' => $file['modifiedTime'] ?? ''
                        );
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->log("Errore scansione cartella: " . $e->getMessage(), 'ERROR');
        }
        
        return $files;
    }

    /**
     * Scansione ricorsiva file Excel
     */
    private function scan_all_excel_files_recursive($folder_id, $token) {
        $all_files = array();
        
        try {
            // Cerca file Excel nella cartella corrente
            $query = "'{$folder_id}' in parents and (mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or mimeType='application/vnd.ms-excel') and trashed=false";
            
            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query,
                'fields' => 'files(id,name,size,modifiedTime)',
                'pageSize' => 100
            ));
            
            $response = wp_remote_get($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['files'])) {
                    foreach ($data['files'] as $file) {
                        $all_files[] = array(
                            'googledrive_id' => $file['id'],
                            'filename' => $file['name'],
                            'file_size' => $file['size'] ?? 0,
                            'modified_time' => $file['modifiedTime'] ?? ''
                        );
                    }
                }
            }
            
            // Cerca nelle sottocartelle
            $query_folders = "'{$folder_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
            
            $url_folders = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
                'q' => $query_folders,
                'fields' => 'files(id,name)',
                'pageSize' => 50
            ));
            
            $response_folders = wp_remote_get($url_folders, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 30
            ));
            
            if (!is_wp_error($response_folders)) {
                $body_folders = wp_remote_retrieve_body($response_folders);
                $data_folders = json_decode($body_folders, true);
                
                if (isset($data_folders['files'])) {
                    foreach ($data_folders['files'] as $subfolder) {
                        $subfiles = $this->scan_all_excel_files_recursive($subfolder['id'], $token);
                        $all_files = array_merge($all_files, $subfiles);
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->log("Errore scansione: " . $e->getMessage(), 'ERROR');
        }
        
        return $all_files;
    }

    /**
     * Logging
     */
    private function log($message, $level = 'INFO') {
        if (!$this->debug_mode) return;
        
        $prefix = '[747Disco-GDriveSync]';
        error_log("{$prefix} {$message}");
    }
}