<?php
/**
 * Storage Manager - 747 Disco CRM
 * VERSIONE CORRETTA v11.8.0
 * 
 * FIX APPLICATI:
 * 1. ✅ Metodo upload_file() accetta $folder_path e lo passa all'handler
 * 2. ✅ Metodo extract_date_from_folder() per estrarre data dal path
 * 3. ✅ Supporto completo per upload con cartella specifica
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @version    11.8.0
 */

namespace Disco747_CRM\Storage;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Storage_Manager {
    
    private $googledrive_handler;
    private $dropbox_handler;
    private $storage_type;
    private $debug_mode = true;
    
    public function __construct() {
        $this->storage_type = get_option('disco747_storage_type', 'googledrive');
        $this->init_storage_handlers();
    }
    
    /**
     * Inizializza handler storage
     */
    private function init_storage_handlers() {
        try {
            // Inizializza Google Drive
            if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive')) {
                $this->googledrive_handler = new Disco747_GoogleDrive();
                $this->log('[StorageManager] Google Drive handler inizializzato');
            }
            
            // Inizializza Dropbox
            if (class_exists('Disco747_CRM\\Storage\\Disco747_Dropbox')) {
                $this->dropbox_handler = new Disco747_Dropbox();
                $this->log('[StorageManager] Dropbox handler inizializzato');
            }
            
            $this->log('[StorageManager] Inizializzato con tipo: ' . $this->storage_type);
            
        } catch (\Exception $e) {
            $this->log('[StorageManager] Errore inizializzazione: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * ✅ NUOVO: Ottiene l'handler attivo (GoogleDrive o Dropbox)
     * 
     * @return object|null Handler attivo o null
     */
    public function get_active_handler() {
        if ($this->storage_type === 'googledrive' && $this->googledrive_handler) {
            return $this->googledrive_handler;
        } elseif ($this->storage_type === 'dropbox' && $this->dropbox_handler) {
            return $this->dropbox_handler;
        }
        return null;
    }
    
    /**
     * ✅ NUOVO: Ottiene handler Google Drive
     * 
     * @return object|null Google Drive handler o null
     */
    public function get_googledrive_handler() {
        return $this->googledrive_handler;
    }
    
    /**
     * ✅ NUOVO: Ottiene handler Dropbox
     * 
     * @return object|null Dropbox handler o null
     */
    public function get_dropbox_handler() {
        return $this->dropbox_handler;
    }
    
    /**
     * Ottiene il tipo di storage attivo
     * 
     * @return string 'googledrive' o 'dropbox'
     */
    public function get_storage_type() {
        return $this->storage_type;
    }
    
    /**
     * Imposta il tipo di storage
     * 
     * @param string $type 'googledrive' o 'dropbox'
     */
    public function set_storage_type($type) {
        if (in_array($type, array('googledrive', 'dropbox'))) {
            $this->storage_type = $type;
            update_option('disco747_storage_type', $type);
            $this->log('[StorageManager] Tipo storage cambiato in: ' . $type);
        }
    }
    
    /**
     * ✅ METODO CORRETTO: Upload file con supporto folder_path
     * FIX: Accetta secondo parametro $folder_path e lo usa correttamente
     * 
     * @param string $file_path Percorso file locale
     * @param string $folder_path Percorso cartella su Drive (es: "747-Preventivi/2025/Novembre/")
     * @return string|false URL file caricato o false
     */
    public function upload_file($file_path, $folder_path = '') {
        if (!file_exists($file_path)) {
            $this->log('File non trovato: ' . $file_path, 'ERROR');
            return false;
        }
        
        try {
            $handler = $this->get_active_handler();
            
            if (!$handler) {
                $this->log('Handler storage non disponibile', 'ERROR');
                return false;
            }
            
            $this->log('Upload file: ' . basename($file_path));
            
            // ✅ FIX CRITICO: Passa il folder_path all'handler
            if (!empty($folder_path)) {
                $this->log('📁 Cartella destinazione: ' . $folder_path);
                
                // Se handler ha metodo con folder_path
                if (method_exists($handler, 'upload_to_folder')) {
                    return $handler->upload_to_folder($file_path, $folder_path);
                }
            }
            
            // Fallback: metodo standard
            if (method_exists($handler, 'upload_file')) {
                return $handler->upload_file($file_path, $folder_path);
            }
            
            // Fallback Google Drive specifico
            if (method_exists($handler, 'upload_to_googledrive')) {
                // Estrai data_evento dal folder_path se possibile
                // Es: "747-Preventivi/2025/Novembre/" -> data_evento "2025-11-01"
                $data_evento = $this->extract_date_from_folder($folder_path);
                
                $this->log('📅 Data evento estratta: ' . $data_evento);
                
                return $handler->upload_to_googledrive(
                    $file_path, 
                    basename($file_path),
                    $data_evento
                );
            }
            
            $this->log('Nessun metodo upload disponibile', 'ERROR');
            return false;
            
        } catch (\Exception $e) {
            $this->log('Errore upload: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * ✅ NUOVO: Estrae data evento dal percorso cartella
     * Es: "747-Preventivi/2025/Novembre/" -> "2025-11-01"
     * 
     * @param string $folder_path Percorso cartella
     * @return string Data in formato Y-m-d
     */
    private function extract_date_from_folder($folder_path) {
        // Estrai anno e mese dal path
        if (preg_match('/(\d{4})\/(\w+)/', $folder_path, $matches)) {
            $year = $matches[1];
            $month_name = $matches[2];
            
            // Mappa mesi italiani
            $mesi_map = array(
                'Gennaio' => '01', 'Febbraio' => '02', 'Marzo' => '03',
                'Aprile' => '04', 'Maggio' => '05', 'Giugno' => '06',
                'Luglio' => '07', 'Agosto' => '08', 'Settembre' => '09',
                'Ottobre' => '10', 'Novembre' => '11', 'Dicembre' => '12'
            );
            
            $month_num = $mesi_map[$month_name] ?? '01';
            
            return $year . '-' . $month_num . '-01';
        }
        
        // Fallback: data corrente
        return date('Y-m-d');
    }
    
    /**
     * Verifica se lo storage è connesso
     * 
     * @return bool
     */
    public function is_connected() {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return false;
        }
        
        if (method_exists($handler, 'is_connected')) {
            return $handler->is_connected();
        }
        
        if (method_exists($handler, 'test_connection')) {
            $result = $handler->test_connection();
            return isset($result['success']) && $result['success'];
        }
        
        return false;
    }
    
    /**
     * Test connessione storage
     * 
     * @return array Risultato test
     */
    public function test_connection() {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return array('success' => false, 'message' => 'Storage non inizializzato');
        }
        
        if (method_exists($handler, 'test_connection')) {
            return $handler->test_connection();
        }
        
        return array('success' => false, 'message' => 'Metodo test non disponibile');
    }
    
    /**
     * Download file da storage attivo
     * 
     * @param string $remote_path Path remoto
     * @param string $local_path Path locale di destinazione
     * @return array Risultato download
     */
    public function download_file($remote_path, $local_path) {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return array(
                'success' => false,
                'error' => 'Nessun handler storage disponibile'
            );
        }
        
        if (!method_exists($handler, 'download_file')) {
            return array(
                'success' => false,
                'error' => 'Metodo download_file non disponibile'
            );
        }
        
        try {
            return $handler->download_file($remote_path, $local_path);
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Lista file da storage attivo
     * 
     * @param string $path Path da listare
     * @return array Lista file
     */
    public function list_files($path = '') {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return array();
        }
        
        if (!method_exists($handler, 'list_files')) {
            return array();
        }
        
        try {
            return $handler->list_files($path);
        } catch (\Exception $e) {
            $this->log('[StorageManager] Errore list_files: ' . $e->getMessage(), 'error');
            return array();
        }
    }
    
    /**
     * Elimina file da storage attivo
     * 
     * @param string $remote_path Path remoto del file
     * @return array Risultato eliminazione
     */
    public function delete_file($remote_path) {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return array(
                'success' => false,
                'error' => 'Nessun handler storage disponibile'
            );
        }
        
        if (!method_exists($handler, 'delete_file')) {
            return array(
                'success' => false,
                'error' => 'Metodo delete_file non disponibile'
            );
        }
        
        try {
            return $handler->delete_file($remote_path);
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Crea cartella su storage attivo
     * 
     * @param string $path Path della cartella da creare
     * @return array Risultato creazione
     */
    public function create_folder($path) {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return array(
                'success' => false,
                'error' => 'Nessun handler storage disponibile'
            );
        }
        
        if (!method_exists($handler, 'create_folder')) {
            return array(
                'success' => false,
                'error' => 'Metodo create_folder non disponibile'
            );
        }
        
        try {
            return $handler->create_folder($path);
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Ottiene informazioni su un file
     * 
     * @param string $remote_path Path remoto
     * @return array|false Info file o false
     */
    public function get_file_info($remote_path) {
        $handler = $this->get_active_handler();
        
        if (!$handler) {
            return false;
        }
        
        if (!method_exists($handler, 'get_file_info')) {
            return false;
        }
        
        try {
            return $handler->get_file_info($remote_path);
        } catch (\Exception $e) {
            $this->log('[StorageManager] Errore get_file_info: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Check if storage is configured
     * 
     * @return bool
     */
    public function is_configured() {
        $handler = $this->get_active_handler();
        return $handler !== null;
    }
    
    /**
     * Ottiene configurazione storage
     * 
     * @return array Configurazione
     */
    public function get_config() {
        return array(
            'storage_type' => $this->storage_type,
            'googledrive_available' => $this->googledrive_handler !== null,
            'dropbox_available' => $this->dropbox_handler !== null,
            'active_handler' => $this->get_active_handler() !== null
        );
    }
    
    /**
     * Logging
     * 
     * @param string $message Messaggio
     * @param string $level Livello (INFO, WARNING, ERROR)
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode) {
            error_log("[747Disco-CRM] [StorageManager] [{$level}] {$message}");
        }
    }
}