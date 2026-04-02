<?php
/**
 * 747 Disco CRM - Gestore Dropbox Avanzato
 * 
 * Sistema completo OAuth 2.0, upload, gestione cartelle organizzate
 * Estratto dalla vecchia versione funzionante e integrato nel nuovo sistema
 * 
 * @package    Disco747_CRM
 * @subpackage Storage  
 * @since      11.5.0
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Storage;

use Disco747_CRM\Core\Disco747_Config;
use Exception;

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_Dropbox
 * 
 * Gestore completo Dropbox con OAuth 2.0, upload automatico, 
 * gestione cartelle organizzate per data
 * Estratto dalla vecchia versione funzionante PreventiviParty
 * 
 * @since 11.5.0
 */
class Disco747_Dropbox {

    /**
     * Istanza di configurazione
     * @var Disco747_Config
     */
    private $config;

    /**
     * Modalità debug
     * @var bool
     */
    private $debug_mode;

    /**
     * Base folder per i preventivi
     * @var string
     */
    private $base_folder = '/747-Preventivi';

    /**
     * Mesi in italiano per organizzazione cartelle
     * @var array
     */
    private $mesi_italiani = [
        '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo', '04' => 'Aprile',
        '05' => 'Maggio', '06' => 'Giugno', '07' => 'Luglio', '08' => 'Agosto',
        '09' => 'Settembre', '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre'
    ];

    /**
     * Costruttore
     */
    public function __construct() {
        $this->config = disco747_crm()->get_config();
        $this->debug_mode = defined('DISCO747_DEBUG') ? DISCO747_DEBUG : true;
    }

    // ============================================================================
    // SISTEMA OAUTH 2.0 COMPLETO
    // ============================================================================

    /**
     * Ottiene le credenziali OAuth salvate
     * ESTRATTO dalla vecchia versione funzionante
     */
    public function get_oauth_credentials() {
        return array(
            'app_key' => get_option('disco747_dropbox_app_key', ''),
            'app_secret' => get_option('disco747_dropbox_app_secret', ''),
            'redirect_uri' => get_option('disco747_dropbox_redirect_uri', 'http://localhost'),
            'refresh_token' => get_option('disco747_dropbox_refresh_token', '')
        );
    }

    /**
     * Genera URL per autorizzazione OAuth
     * ESTRATTO dalla vecchia versione
     */
    public function generate_auth_url() {
        $credentials = $this->get_oauth_credentials();
        
        if (empty($credentials['app_key'])) {
            return array('success' => false, 'message' => 'App Key non configurata');
        }
        
        $redirect_uri = !empty($credentials['redirect_uri']) ? 
                       $credentials['redirect_uri'] : 'http://localhost';
        
        $auth_url = 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
            'client_id' => $credentials['app_key'],
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'token_access_type' => 'offline',
            'state' => wp_create_nonce('dropbox_oauth_state')
        ]);
        
        return array(
            'success' => true,
            'auth_url' => $auth_url,
            'redirect_uri' => $redirect_uri
        );
    }

    /**
     * Scambia authorization code con refresh token
     * ESTRATTO dalla vecchia versione funzionante
     */
    public function exchange_code_for_tokens($auth_code) {
        $credentials = $this->get_oauth_credentials();
        
        if (empty($credentials['app_key']) || empty($credentials['app_secret'])) {
            return array('success' => false, 'message' => 'App Key o App Secret mancanti');
        }
        
        $redirect_uri = !empty($credentials['redirect_uri']) ? 
                       $credentials['redirect_uri'] : 'http://localhost';
        $auth_code = trim($auth_code);
        
        $response = wp_remote_post('https://api.dropboxapi.com/oauth2/token', array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => '747Disco-CRM/1.0'
            ),
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => $auth_code,
                'client_id' => trim($credentials['app_key']),
                'client_secret' => trim($credentials['app_secret']),
                'redirect_uri' => $redirect_uri
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Errore connessione: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $this->log("Exchange code HTTP $http_code: $body");
        
        if ($http_code === 200) {
            $data = json_decode($body, true);
            
            if (isset($data['refresh_token'])) {
                // Salva refresh token (permanente)
                update_option('disco747_dropbox_refresh_token', $data['refresh_token']);
                
                // Salva access token temporaneo se presente
                if (isset($data['access_token'])) {
                    $expires_in = $data['expires_in'] ?? 14400;
                    $expires_at = time() + $expires_in - 300; // 5 minuti di margine
                    
                    update_option('disco747_dropbox_access_token', $data['access_token']);
                    update_option('disco747_dropbox_token_expires', $expires_at);
                }
                
                return array(
                    'success' => true,
                    'message' => 'OAuth configurato con successo!',
                    'refresh_token' => substr($data['refresh_token'], 0, 30) . '...'
                );
            } else {
                return array('success' => false, 'message' => 'Refresh token non presente nella risposta');
            }
        }
        
        return array('success' => false, 'message' => "Errore HTTP $http_code: $body");
    }

    /**
     * Ottiene access token valido (refresh automatico se necessario)
     * ESTRATTO dalla vecchia versione funzionante
     */
    public function get_valid_access_token() {
        $current_token = get_option('disco747_dropbox_access_token', '');
        $expires_at = get_option('disco747_dropbox_token_expires', 0);
        $now = time();
        
        // Se il token corrente è valido, restituiscilo
        if (!empty($current_token) && $now < $expires_at) {
            return $current_token;
        }
        
        // Token scaduto o mancante, refresh automatico
        $credentials = $this->get_oauth_credentials();
        if (empty($credentials['refresh_token'])) {
            throw new Exception('Refresh token mancante - configurare OAuth');
        }
        
        return $this->refresh_access_token(
            $credentials['refresh_token'],
            $credentials['app_key'],
            $credentials['app_secret']
        );
    }

    /**
     * Refresh access token usando refresh token
     * ESTRATTO dalla vecchia versione
     */
    private function refresh_access_token($refresh_token, $client_id, $client_secret) {
        $response = wp_remote_post('https://api.dropboxapi.com/oauth2/token', array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => '747Disco-CRM/1.0'
            ),
            'body' => array(
                'grant_type' => 'refresh_token',
                'refresh_token' => trim($refresh_token),
                'client_id' => trim($client_id),
                'client_secret' => trim($client_secret)
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Errore refresh token: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $this->log("Refresh token HTTP $http_code: $body");
        
        if ($http_code === 200) {
            $data = json_decode($body, true);
            if (isset($data['access_token'])) {
                $expires_in = $data['expires_in'] ?? 14400;
                $expires_at = time() + $expires_in - 300; // 5 minuti di margine
                
                // Salva nuovo token
                update_option('disco747_dropbox_access_token', $data['access_token']);
                update_option('disco747_dropbox_token_expires', $expires_at);
                
                return $data['access_token'];
            }
        }
        
        throw new Exception("Errore refresh token HTTP $http_code: $body");
    }

    /**
     * Testa connessione OAuth
     */
    public function test_oauth_connection() {
        try {
            $credentials = $this->get_oauth_credentials();
            
            if (empty($credentials['app_key']) || empty($credentials['app_secret']) || 
                empty($credentials['refresh_token'])) {
                return array('success' => false, 'message' => 'Credenziali OAuth incomplete');
            }
            
            $token = $this->get_valid_access_token();
            
            // Test chiamata API per info utente
            $response = wp_remote_post('https://api.dropboxapi.com/2/users/get_current_account', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => '',
                'timeout' => 15
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => 'Errore connessione: ' . $response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code === 200) {
                $user_info = json_decode($body, true);
                
                if (!$user_info || !isset($user_info['name'])) {
                    return array('success' => false, 'message' => 'Risposta API non valida');
                }
                
                return array(
                    'success' => true, 
                    'message' => 'OAuth funzionante!',
                    'user_name' => $user_info['name']['display_name'] ?? 'N/A',
                    'user_email' => $user_info['email'] ?? 'N/A'
                );
            } else {
                return array('success' => false, 'message' => "Errore API HTTP $status_code: $body");
            }
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Errore OAuth: ' . $e->getMessage());
        }
    }

    // ============================================================================
    // SISTEMA UPLOAD E GESTIONE FILE
    // ============================================================================

    /**
     * Upload file su Dropbox con cartelle organizzate per data
     * ESTRATTO dalla vecchia versione funzionante
     */
    public function upload_to_dropbox($local_file, $remote_filename, $data_evento) {
        if (!file_exists($local_file)) {
            throw new Exception("File non trovato: $local_file");
        }
        
        $token = $this->get_valid_access_token();
        
        // Crea percorso organizzato per data
        $remote_path = $this->generate_organized_path($data_evento, $remote_filename);
        
        // Leggi contenuto file
        $file_content = file_get_contents($local_file);
        if ($file_content === false) {
            throw new Exception("Impossibile leggere file: $local_file");
        }
        
        // Determina metodo upload in base alla dimensione
        $file_size = strlen($file_content);
        if ($file_size > 150 * 1024 * 1024) { // 150MB
            return $this->upload_large_file($local_file, $remote_path, $token);
        }
        
        // Upload normale
        $response = wp_remote_post('https://content.dropboxapi.com/2/files/upload', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode(array(
                    'path' => $remote_path,
                    'mode' => 'overwrite',
                    'autorename' => true
                ))
            ),
            'body' => $file_content,
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Errore upload Dropbox: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore upload Dropbox HTTP $status_code: $body");
        }
        
        // Verifica risposta
        $upload_result = json_decode($body, true);
        if (!$upload_result || !isset($upload_result['path_display'])) {
            throw new Exception("Risposta upload non valida: $body");
        }
        
        $this->log("Upload completato: " . $upload_result['path_display']);
        
        return $upload_result['path_display'];
    }

    /**
     * Upload file grandi con chunking
     * ESTRATTO dalla vecchia versione
     */
    public function upload_large_file($local_file, $remote_path, $token, $chunk_size = 8388608) {
        $file_size = filesize($local_file);
        $file_handle = fopen($local_file, 'rb');
        
        if (!$file_handle) {
            throw new Exception("Impossibile aprire file: $local_file");
        }
        
        try {
            // Inizia sessione upload
            $session_id = $this->start_upload_session($token, $file_handle, $chunk_size);
            
            // Upload chunks rimanenti
            $offset = $chunk_size;
            while ($offset < $file_size) {
                $chunk_data = fread($file_handle, $chunk_size);
                $this->append_upload_session($token, $session_id, $offset, $chunk_data);
                $offset += strlen($chunk_data);
            }
            
            // Finalizza upload
            $result = $this->finish_upload_session($token, $session_id, $offset, $remote_path);
            
            fclose($file_handle);
            
            return $result;
            
        } catch (Exception $e) {
            fclose($file_handle);
            throw $e;
        }
    }

    /**
     * Avvia sessione upload chunked
     */
    private function start_upload_session($token, $file_handle, $chunk_size) {
        $chunk_data = fread($file_handle, $chunk_size);
        
        $response = wp_remote_post('https://content.dropboxapi.com/2/files/upload_session/start', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/octet-stream'
            ),
            'body' => $chunk_data,
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Errore start upload session: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore start upload session HTTP $status_code: $body");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['session_id'])) {
            throw new Exception("Risposta start session non valida: $body");
        }
        
        return $result['session_id'];
    }

    /**
     * Aggiunge chunk a sessione upload
     */
    private function append_upload_session($token, $session_id, $offset, $chunk_data) {
        $response = wp_remote_post('https://content.dropboxapi.com/2/files/upload_session/append_v2', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode(array(
                    'cursor' => array(
                        'session_id' => $session_id,
                        'offset' => $offset
                    )
                ))
            ),
            'body' => $chunk_data,
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Errore append upload session: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            throw new Exception("Errore append upload session HTTP $status_code: $body");
        }
    }

    /**
     * Finalizza sessione upload
     */
    private function finish_upload_session($token, $session_id, $offset, $remote_path) {
        $response = wp_remote_post('https://content.dropboxapi.com/2/files/upload_session/finish', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode(array(
                    'cursor' => array(
                        'session_id' => $session_id,
                        'offset' => $offset
                    ),
                    'commit' => array(
                        'path' => $remote_path,
                        'mode' => 'overwrite',
                        'autorename' => true
                    )
                ))
            ),
            'body' => '',
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Errore finish upload session: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore finish upload session HTTP $status_code: $body");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['path_display'])) {
            throw new Exception("Risposta finish session non valida: $body");
        }
        
        return $result['path_display'];
    }

    // ============================================================================
    // GESTIONE CARTELLE E ORGANIZZAZIONE
    // ============================================================================

    /**
     * Genera percorso organizzato per data
     * ESTRATTO dalla vecchia versione funzionante
     */
    private function generate_organized_path($data_evento, $filename) {
        $date_parts = explode('-', $data_evento);
        $year = $date_parts[0];
        $month_num = $date_parts[1];
        
        $month_name = $this->mesi_italiani[$month_num] ?? 'Sconosciuto';
        
        return $this->base_folder . '/' . $year . '/' . $month_name . '/' . $filename;
    }

    /**
     * Crea cartella su Dropbox
     */
    public function create_dropbox_folder($folder_path) {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_post('https://api.dropboxapi.com/2/files/create_folder_v2', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'path' => $folder_path,
                'autorename' => false
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Errore creazione cartella: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // 200 = successo, 409 = cartella già esistente (ok)
        if ($status_code === 200 || $status_code === 409) {
            return true;
        }
        
        throw new Exception("Errore creazione cartella HTTP $status_code: $body");
    }

    // ============================================================================
    // GESTIONE FILE PREVENTIVI (RINOMINA, ANNULLAMENTO)
    // ============================================================================

    /**
     * Rinomina file preventivo aggiungendo prefisso "NO_" (annullamento)
     * ESTRATTO dalla vecchia versione funzionante
     */
    public function rename_preventivo_files($nome_file_base, $data_evento) {
        try {
            $folder_path = $this->generate_folder_path($data_evento);
            
            // Rinomina Excel
            $old_excel_path = $folder_path . $nome_file_base . '.xlsx';
            $new_excel_path = $folder_path . 'NO_' . $nome_file_base . '.xlsx';
            
            try {
                $this->rename_dropbox_file($old_excel_path, $new_excel_path);
                $this->log("Excel rinominato: $old_excel_path -> $new_excel_path");
            } catch (Exception $e) {
                $this->log("Errore rinomina Excel: " . $e->getMessage());
            }
            
            // Rinomina PDF
            $old_pdf_path = $folder_path . $nome_file_base . '.pdf';
            $new_pdf_path = $folder_path . 'NO_' . $nome_file_base . '.pdf';
            
            try {
                $this->rename_dropbox_file($old_pdf_path, $new_pdf_path);
                $this->log("PDF rinominato: $old_pdf_path -> $new_pdf_path");
            } catch (Exception $e) {
                $this->log("Errore rinomina PDF: " . $e->getMessage());
            }
            
            return array(
                'success' => true,
                'message' => 'File rinominati con successo su Dropbox',
                'excel_path' => $new_excel_path,
                'pdf_path' => $new_pdf_path
            );
            
        } catch (Exception $e) {
            $this->log("Errore generale rinomina file: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Errore rinomina file: ' . $e->getMessage()
            );
        }
    }

    /**
     * Rinomina file su Dropbox (equivalente a "move")
     */
    public function rename_dropbox_file($old_path, $new_path) {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_post('https://api.dropboxapi.com/2/files/move_v2', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'from_path' => $old_path,
                'to_path' => $new_path,
                'allow_shared_folder' => false,
                'autorename' => true,
                'allow_ownership_transfer' => false
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Errore rinomina Dropbox: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            // Se il file non esiste (409), non è un errore critico
            if ($status_code === 409) {
                $error_data = json_decode($body, true);
                if (isset($error_data['error']['.tag']) && 
                    $error_data['error']['.tag'] === 'from_lookup') {
                    $this->log("File non trovato su Dropbox (normale se già rinominato): $old_path");
                    return true;
                }
            }
            throw new Exception("Errore rinomina Dropbox HTTP $status_code: $body");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['metadata'])) {
            throw new Exception("Risposta rinomina non valida: $body");
        }
        
        $this->log("File rinominato con successo: $old_path -> $new_path");
        
        return $result['metadata'];
    }

    /**
     * Genera percorso cartella base per data evento
     */
    private function generate_folder_path($data_evento) {
        $date_parts = explode('-', $data_evento);
        $year = $date_parts[0];
        $month_num = $date_parts[1];
        
        $month_name = $this->mesi_italiani[$month_num] ?? 'Sconosciuto';
        
        return $this->base_folder . '/' . $year . '/' . $month_name . '/';
    }

    // ============================================================================
    // UTILITÀ E MONITORAGGIO
    // ============================================================================

    /**
     * Lista contenuto cartella Dropbox
     */
    public function list_dropbox_folder($folder_path = '') {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_post('https://api.dropboxapi.com/2/files/list_folder', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'path' => $folder_path,
                'recursive' => false,
                'include_media_info' => false,
                'include_deleted' => false,
                'include_has_explicit_shared_members' => false
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Errore lista cartella: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore lista cartella HTTP $status_code: $body");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['entries'])) {
            throw new Exception("Risposta lista cartella non valida: $body");
        }
        
        return $result['entries'];
    }

    /**
     * Verifica se un file esiste su Dropbox
     */
    public function file_exists_on_dropbox($file_path) {
        try {
            $token = $this->get_valid_access_token();
            
            $response = wp_remote_post('https://api.dropboxapi.com/2/files/get_metadata', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'path' => $file_path,
                    'include_media_info' => false,
                    'include_deleted' => false,
                    'include_has_explicit_shared_members' => false
                )),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            return $status_code === 200;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ottiene uso spazio Dropbox
     */
    public function get_dropbox_usage() {
        try {
            $token = $this->get_valid_access_token();
            
            $response = wp_remote_post('https://api.dropboxapi.com/2/users/get_space_usage', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => '',
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('Errore spazio: ' . $response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code !== 200) {
                throw new Exception("Errore spazio HTTP $status_code: $body");
            }
            
            $result = json_decode($body, true);
            if (!$result || !isset($result['used'])) {
                throw new Exception("Risposta spazio non valida: $body");
            }
            
            return $result;
            
        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }

    /**
     * Verifica stato connessione Dropbox
     */
    public function check_dropbox_connection() {
        try {
            $credentials = $this->get_oauth_credentials();
            
            if (empty($credentials['refresh_token'])) {
                return array(
                    'connected' => false,
                    'message' => 'Refresh token non configurato'
                );
            }
            
            $token = $this->get_valid_access_token();
            
            // Test semplice
            $response = wp_remote_post('https://api.dropboxapi.com/2/users/get_current_account', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => '',
                'timeout' => 10
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'connected' => false,
                    'message' => 'Errore connessione: ' . $response->get_error_message()
                );
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code === 200) {
                return array(
                    'connected' => true,
                    'message' => 'Connesso a Dropbox'
                );
            } else {
                return array(
                    'connected' => false,
                    'message' => "Errore HTTP $status_code"
                );
            }
            
        } catch (Exception $e) {
            return array(
                'connected' => false,
                'message' => 'Errore: ' . $e->getMessage()
            );
        }
    }

    /**
     * Formatta bytes in formato leggibile
     */
    public function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Elimina file da Dropbox
     */
    public function delete_dropbox_file($file_path) {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_post('https://api.dropboxapi.com/2/files/delete_v2', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'path' => $file_path
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Errore eliminazione file: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore eliminazione file HTTP $status_code: $body");
        }
        
        return true;
    }

    /**
     * Logging helper
     */
    private function log($message) {
        if ($this->debug_mode) {
            disco747_log('Dropbox Handler: ' . $message, 'INFO');
        }
    }
}