<?php
/**
 * 747 Disco CRM - Dropbox Storage Handler v11.4.2
 * COMPLETO: Integra TUTTA la logica OAuth del vecchio PreventiviParty
 * RIPRISTINA: Refresh Token, OAuth completo, upload, gestione file
 * 
 * @package    Disco747_CRM
 * @subpackage Storage
 * @since      11.4.2
 * @version    11.4.2
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Storage;

use Exception;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_Dropbox
 * 
 * Gestisce tutte le operazioni Dropbox con OAuth 2.0 completo
 * REPLICA ESATTA della funzionalità del vecchio PreventiviPartyDropbox
 * 
 * @since 11.4.2
 */
class Disco747_Dropbox {

    /**
     * Configurazione
     */
    private $debug_mode = true;
    private $api_base_url = 'https://api.dropboxapi.com';
    private $content_base_url = 'https://content.dropboxapi.com';
    
    /**
     * Cache per performance
     */
    private $access_token_cache = null;
    private $credentials_cache = null;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->log('Dropbox Handler v11.4.2 inizializzato');
    }

    /**
     * Logging interno
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode && function_exists('error_log')) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[{$timestamp}] [Disco747_Dropbox] [{$level}] {$message}");
        }
    }

    // ============================================================================
    // GESTIONE CREDENZIALI - INTEGRATA DAL VECCHIO PLUGIN
    // ============================================================================

    /**
     * Ottiene credenziali OAuth complete dal database
     * COMPATIBILE con vecchio plugin usando nuova struttura
     */
    public function get_oauth_credentials() {
        if ($this->credentials_cache !== null) {
            return $this->credentials_cache;
        }
        
        // NUOVA struttura (priorità)
        $new_credentials = get_option('disco747_dropbox_credentials', array());
        if (!empty($new_credentials['app_key'])) {
            $this->credentials_cache = $new_credentials;
            return $new_credentials;
        }
        
        // FALLBACK: Vecchia struttura per migrazione
        $old_credentials = array(
            'app_key' => get_option('preventivi_dropbox_app_key', ''),
            'app_secret' => get_option('preventivi_dropbox_app_secret', ''),
            'redirect_url' => get_option('preventivi_dropbox_redirect_uri', 'http://localhost'),
            'refresh_token' => get_option('preventivi_dropbox_refresh_token', '')
        );
        
        // Se trovate credenziali vecchie, migrale automaticamente
        if (!empty($old_credentials['app_key'])) {
            update_option('disco747_dropbox_credentials', $old_credentials);
            $this->credentials_cache = $old_credentials;
            $this->log('Credenziali migrate dal vecchio plugin');
            return $old_credentials;
        }
        
        // Credenziali vuote di default
        $default_credentials = array(
            'app_key' => '',
            'app_secret' => '',
            'redirect_url' => admin_url('admin.php?page=disco747-settings'),
            'refresh_token' => ''
        );
        
        $this->credentials_cache = $default_credentials;
        return $default_credentials;
    }

    /**
     * Verifica se OAuth è configurato completamente
     */
    public function is_oauth_configured() {
        $credentials = $this->get_oauth_credentials();
        return !empty($credentials['app_key']) && 
               !empty($credentials['app_secret']) && 
               !empty($credentials['refresh_token']);
    }

    // ============================================================================
    // OAUTH 2.0 FLOW COMPLETO - DAL VECCHIO PLUGIN
    // ============================================================================

    /**
     * Genera URL per autorizzazione OAuth
     * IDENTICO al vecchio plugin
     */
    public function generate_auth_url() {
        $credentials = $this->get_oauth_credentials();
        
        if (empty($credentials['app_key'])) {
            return array('success' => false, 'message' => 'App Key non configurata');
        }
        
        $redirect_uri = !empty($credentials['redirect_url']) ? 
                       $credentials['redirect_url'] : 
                       admin_url('admin.php?page=disco747-settings');
        
        $state = wp_create_nonce('dropbox_oauth_state_' . time());
        update_option('disco747_dropbox_oauth_state', $state);
        
        $auth_url = 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
            'client_id' => $credentials['app_key'],
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'token_access_type' => 'offline', // IMPORTANTE: Per ottenere refresh token
            'state' => $state
        ]);
        
        $this->log('URL autorizzazione generato per App Key: ' . substr($credentials['app_key'], 0, 10) . '...');
        
        return array(
            'success' => true,
            'auth_url' => $auth_url,
            'redirect_uri' => $redirect_uri,
            'state' => $state
        );
    }

    /**
     * Scambia authorization code con refresh token
     * LOGICA COMPLETA dal vecchio plugin
     */
    public function exchange_code_for_tokens($auth_code, $state = null) {
        $credentials = $this->get_oauth_credentials();
        
        if (empty($credentials['app_key']) || empty($credentials['app_secret'])) {
            return array('success' => false, 'message' => 'App Key o App Secret mancanti');
        }
        
        // Verifica stato se fornito
        if ($state) {
            $stored_state = get_option('disco747_dropbox_oauth_state');
            if ($state !== $stored_state) {
                return array('success' => false, 'message' => 'Stato OAuth non valido (sicurezza)');
            }
            delete_option('disco747_dropbox_oauth_state');
        }
        
        $redirect_uri = !empty($credentials['redirect_url']) ? 
                       $credentials['redirect_url'] : 
                       admin_url('admin.php?page=disco747-settings');
        
        $auth_code = trim($auth_code);
        
        $this->log('Scambio code per tokens - App Key: ' . substr($credentials['app_key'], 0, 10) . '...');
        
        $response = wp_remote_post($this->api_base_url . '/oauth2/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $auth_code,
                'client_id' => trim($credentials['app_key']),
                'client_secret' => trim($credentials['app_secret']),
                'redirect_uri' => $redirect_uri
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            $error_msg = 'Errore connessione: ' . $response->get_error_message();
            $this->log($error_msg, 'ERROR');
            return array('success' => false, 'message' => $error_msg);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $this->log("Exchange code HTTP {$http_code}: " . substr($body, 0, 200) . '...');
        
        if ($http_code === 200) {
            $data = json_decode($body, true);
            
            if (isset($data['refresh_token'])) {
                // Salva refresh token nelle credenziali
                $credentials['refresh_token'] = $data['refresh_token'];
                update_option('disco747_dropbox_credentials', $credentials);
                
                // Salva access token se presente
                if (isset($data['access_token'])) {
                    $expires_in = $data['expires_in'] ?? 14400; // 4 ore default
                    $expires_at = time() + $expires_in - 300; // 5 minuti di margine
                    
                    update_option('disco747_dropbox_access_token', $data['access_token']);
                    update_option('disco747_dropbox_token_expires', $expires_at);
                    $this->access_token_cache = $data['access_token'];
                }
                
                $this->log('Refresh token salvato con successo');
                
                return array(
                    'success' => true,
                    'message' => 'Dropbox configurato con successo! Refresh token ottenuto.',
                    'refresh_token' => substr($data['refresh_token'], 0, 30) . '...',
                    'expires_in' => $data['expires_in'] ?? null
                );
            } else {
                $error_msg = 'Refresh token non presente nella risposta Dropbox';
                $this->log($error_msg, 'ERROR');
                return array('success' => false, 'message' => $error_msg);
            }
        }
        
        $error_msg = "Errore HTTP {$http_code}: {$body}";
        $this->log($error_msg, 'ERROR');
        return array('success' => false, 'message' => $error_msg);
    }

    /**
     * Ottiene access token valido (refresh automatico se scaduto)
     * LOGICA IDENTICA al vecchio plugin
     */
    public function get_valid_access_token() {
        // Check cache prima
        if ($this->access_token_cache) {
            $expires_at = get_option('disco747_dropbox_token_expires', 0);
            if (time() < $expires_at) {
                return $this->access_token_cache;
            }
        }
        
        // Verifica token corrente
        $current_token = get_option('disco747_dropbox_access_token', '');
        $expires_at = get_option('disco747_dropbox_token_expires', 0);
        $now = time();
        
        // Se token valido, restituiscilo
        if (!empty($current_token) && $now < $expires_at) {
            $this->access_token_cache = $current_token;
            return $current_token;
        }
        
        // Token scaduto/mancante, esegui refresh
        $this->log('Access token scaduto, eseguo refresh...');
        
        $credentials = $this->get_oauth_credentials();
        if (empty($credentials['refresh_token'])) {
            throw new Exception('Refresh token mancante - riconfigura OAuth Dropbox');
        }
        
        return $this->refresh_access_token($credentials['refresh_token'], $credentials['app_key'], $credentials['app_secret']);
    }

    /**
     * Refresh access token usando refresh token
     * IMPLEMENTAZIONE COMPLETA dal vecchio plugin
     */
    public function refresh_access_token($refresh_token, $client_id, $client_secret) {
        $this->log('Refresh access token...');
        
        $response = wp_remote_post($this->api_base_url . '/oauth2/token', [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => trim($refresh_token),
                'client_id' => trim($client_id),
                'client_secret' => trim($client_secret)
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            $error_msg = 'Errore cURL refresh: ' . $response->get_error_message();
            $this->log($error_msg, 'ERROR');
            throw new Exception($error_msg);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $this->log("Refresh token HTTP {$http_code}: " . substr($body, 0, 200) . '...');
        
        if ($http_code === 200) {
            $data = json_decode($body, true);
            if (isset($data['access_token'])) {
                $expires_in = $data['expires_in'] ?? 14400; // 4 ore default
                $expires_at = time() + $expires_in - 300; // 5 minuti di margine
                
                // Salva nuovo token
                update_option('disco747_dropbox_access_token', $data['access_token']);
                update_option('disco747_dropbox_token_expires', $expires_at);
                $this->access_token_cache = $data['access_token'];
                
                $this->log('Access token refreshed con successo');
                return $data['access_token'];
            }
        }
        
        $error_msg = "Errore refresh token HTTP {$http_code}: {$body}";
        $this->log($error_msg, 'ERROR');
        throw new Exception($error_msg);
    }

    /**
     * Test connessione OAuth completo
     * IDENTICO al vecchio plugin
     */
    public function test_oauth_connection() {
        try {
            $credentials = $this->get_oauth_credentials();
            
            if (empty($credentials['app_key']) || empty($credentials['app_secret']) || empty($credentials['refresh_token'])) {
                return array('success' => false, 'message' => 'Credenziali OAuth incomplete');
            }
            
            $this->log('Test connessione OAuth...');
            $token = $this->get_valid_access_token();
            
            // Test chiamata API - ottieni info utente
            $response = wp_remote_post($this->api_base_url . '/2/users/get_current_account', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'User-Agent' => '747-Disco-CRM/11.4.2'
                ],
                'body' => '',
                'timeout' => 15
            ]);
            
            if (is_wp_error($response)) {
                $error_msg = 'Errore connessione: ' . $response->get_error_message();
                $this->log($error_msg, 'ERROR');
                return array('success' => false, 'message' => $error_msg);
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code === 200) {
                $user_info = json_decode($body, true);
                
                if (!$user_info || !isset($user_info['name'])) {
                    return array('success' => false, 'message' => 'Risposta API Dropbox non valida');
                }
                
                $this->log('Test OAuth riuscito per utente: ' . ($user_info['name']['display_name'] ?? 'N/A'));
                
                return array(
                    'success' => true, 
                    'message' => 'Dropbox OAuth configurato correttamente!',
                    'user_name' => $user_info['name']['display_name'] ?? 'N/A',
                    'user_email' => $user_info['email'] ?? 'N/A',
                    'account_id' => $user_info['account_id'] ?? 'N/A'
                );
            } else {
                $error_msg = "Errore API Dropbox HTTP {$status_code}: {$body}";
                $this->log($error_msg, 'ERROR');
                return array('success' => false, 'message' => $error_msg);
            }
            
        } catch (Exception $e) {
            $error_msg = 'Errore test OAuth: ' . $e->getMessage();
            $this->log($error_msg, 'ERROR');
            return array('success' => false, 'message' => $error_msg);
        }
    }

    // ============================================================================
    // GESTIONE FILE E UPLOAD - COMPLETA dal vecchio plugin
    // ============================================================================

    /**
     * Upload file su Dropbox con struttura cartelle per data
     * LOGICA IDENTICA al vecchio plugin
     */
    public function upload_to_dropbox($local_file, $remote_filename, $data_evento) {
        if (!file_exists($local_file)) {
            throw new Exception("File locale non trovato: {$local_file}");
        }
        
        $file_size = filesize($local_file);
        $this->log("Upload file: {$remote_filename} ({$file_size} bytes)");
        
        $token = $this->get_valid_access_token();
        
        // Crea percorso organizzato per data (identico al vecchio plugin)
        $remote_path = $this->generate_folder_path($data_evento) . $remote_filename;
        
        // Leggi contenuto file
        $file_content = file_get_contents($local_file);
        if ($file_content === false) {
            throw new Exception("Impossibile leggere file: {$local_file}");
        }
        
        // Upload usando API v2
        $response = wp_remote_post($this->content_base_url . '/2/files/upload', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode([
                    'path' => $remote_path,
                    'mode' => 'overwrite',
                    'autorename' => true
                ]),
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'body' => $file_content,
            'timeout' => 120
        ]);
        
        if (is_wp_error($response)) {
            $error_msg = 'Errore upload Dropbox: ' . $response->get_error_message();
            $this->log($error_msg, 'ERROR');
            throw new Exception($error_msg);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            $error_msg = "Errore upload Dropbox HTTP {$status_code}: {$body}";
            $this->log($error_msg, 'ERROR');
            throw new Exception($error_msg);
        }
        
        // Verifica risposta
        $upload_result = json_decode($body, true);
        if (!$upload_result || !isset($upload_result['path_display'])) {
            throw new Exception("Risposta upload non valida: {$body}");
        }
        
        $this->log("Upload riuscito: " . $upload_result['path_display']);
        return $upload_result['path_display'];
    }

    /**
     * Upload file grandi con chunking (dal vecchio plugin)
     */
    public function upload_large_file_to_dropbox($local_file, $remote_filename, $data_evento, $chunk_size = 8388608) {
        if (!file_exists($local_file)) {
            throw new Exception("File non trovato: {$local_file}");
        }
        
        $file_size = filesize($local_file);
        $this->log("Upload file grande: {$remote_filename} ({$file_size} bytes, chunk: {$chunk_size})");
        
        if ($file_size <= $chunk_size) {
            // File piccolo, upload normale
            return $this->upload_to_dropbox($local_file, $remote_filename, $data_evento);
        }
        
        $token = $this->get_valid_access_token();
        $remote_path = $this->generate_folder_path($data_evento) . $remote_filename;
        
        $file_handle = fopen($local_file, 'rb');
        if (!$file_handle) {
            throw new Exception("Impossibile aprire file: {$local_file}");
        }
        
        try {
            // Inizio sessione upload
            $session_id = $this->start_upload_session($token, $file_handle, $chunk_size);
            
            // Upload chunks
            $offset = $chunk_size;
            while ($offset < $file_size) {
                $chunk_data = fread($file_handle, $chunk_size);
                $this->append_upload_session($token, $session_id, $offset, $chunk_data);
                $offset += strlen($chunk_data);
                
                $this->log("Chunk uploaded: {$offset}/{$file_size} bytes");
            }
            
            // Finalizza upload
            $result = $this->finish_upload_session($token, $session_id, $offset, $remote_path);
            
            fclose($file_handle);
            $this->log("Upload grande completato: {$result}");
            
            return $result;
            
        } catch (Exception $e) {
            fclose($file_handle);
            throw $e;
        }
    }

    /**
     * Inizia sessione upload chunked
     */
    private function start_upload_session($token, $file_handle, $chunk_size) {
        $chunk_data = fread($file_handle, $chunk_size);
        
        $response = wp_remote_post($this->content_base_url . '/2/files/upload_session/start', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/octet-stream',
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'body' => $chunk_data,
            'timeout' => 120
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore start upload session: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore start upload session HTTP {$status_code}: {$body}");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['session_id'])) {
            throw new Exception("Risposta start session non valida: {$body}");
        }
        
        return $result['session_id'];
    }

    /**
     * Aggiunge chunk a sessione upload
     */
    private function append_upload_session($token, $session_id, $offset, $chunk_data) {
        $response = wp_remote_post($this->content_base_url . '/2/files/upload_session/append_v2', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode([
                    'cursor' => [
                        'session_id' => $session_id,
                        'offset' => $offset
                    ]
                ]),
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'body' => $chunk_data,
            'timeout' => 120
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore append upload session: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            throw new Exception("Errore append upload session HTTP {$status_code}: {$body}");
        }
    }

    /**
     * Finalizza sessione upload
     */
    private function finish_upload_session($token, $session_id, $offset, $remote_path) {
        $response = wp_remote_post($this->content_base_url . '/2/files/upload_session/finish', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode([
                    'cursor' => [
                        'session_id' => $session_id,
                        'offset' => $offset
                    ],
                    'commit' => [
                        'path' => $remote_path,
                        'mode' => 'overwrite',
                        'autorename' => true
                    ]
                ]),
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'body' => '',
            'timeout' => 120
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore finish upload session: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore finish upload session HTTP {$status_code}: {$body}");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['path_display'])) {
            throw new Exception("Risposta finish session non valida: {$body}");
        }
        
        return $result['path_display'];
    }

    // ============================================================================
    // GESTIONE CARTELLE E PATH - dal vecchio plugin
    // ============================================================================

    /**
     * Genera percorso cartella basato sulla data evento
     * IDENTICO al vecchio plugin con mesi italiani
     */
    private function generate_folder_path($data_evento) {
        $date_parts = explode('-', $data_evento);
        $year = $date_parts[0] ?? date('Y');
        $month_num = $date_parts[1] ?? date('m');
        
        $months_it = [
            '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo', '04' => 'Aprile',
            '05' => 'Maggio', '06' => 'Giugno', '07' => 'Luglio', '08' => 'Agosto',
            '09' => 'Settembre', '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre'
        ];
        
        $month_name = $months_it[$month_num] ?? 'Sconosciuto';
        
        return '/PreventiviParty/' . $year . '/' . $month_name . '/';
    }

    /**
     * Crea cartella su Dropbox se non esiste
     */
    public function create_dropbox_folder($folder_path) {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_post($this->api_base_url . '/2/files/create_folder_v2', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'body' => json_encode([
                'path' => $folder_path,
                'autorename' => false
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore creazione cartella: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // 200 = successo, 409 = cartella già esistente (ok)
        if ($status_code === 200 || $status_code === 409) {
            $this->log("Cartella creata/esistente: {$folder_path}");
            return true;
        }
        
        throw new Exception("Errore creazione cartella HTTP {$status_code}: {$body}");
    }

    // ============================================================================
    // GESTIONE FILE - OPERAZIONI AVANZATE dal vecchio plugin
    // ============================================================================

    /**
     * Rinomina file su Dropbox (move operation)
     */
    public function rename_dropbox_file($old_path, $new_path) {
        $token = $this->get_valid_access_token();
        
        $this->log("Rinomina file: {$old_path} -> {$new_path}");
        
        $response = wp_remote_post($this->api_base_url . '/2/files/move_v2', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'body' => json_encode([
                'from_path' => $old_path,
                'to_path' => $new_path,
                'allow_shared_folder' => false,
                'autorename' => true,
                'allow_ownership_transfer' => false
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore rinomina Dropbox: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            // Se file non esiste (409), non è errore critico
            if ($status_code === 409) {
                $error_data = json_decode($body, true);
                if (isset($error_data['error']['.tag']) && $error_data['error']['.tag'] === 'from_lookup') {
                    $this->log("File non trovato su Dropbox (già rinominato?): {$old_path}");
                    return true; // Non è errore, file potrebbe essere già rinominato
                }
            }
            throw new Exception("Errore rinomina Dropbox HTTP {$status_code}: {$body}");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['metadata'])) {
            throw new Exception("Risposta rinomina non valida: {$body}");
        }
        
        $this->log("File rinominato con successo: {$old_path} -> {$new_path}");
        return $result['metadata'];
    }

    /**
     * Rinomina file preventivo con prefisso NO_ (annullamento)
     * FUNZIONE COMPLETA dal vecchio plugin
     */
    public function rename_preventivo_files($nome_file_base, $data_evento) {
        try {
            $folder_path = $this->generate_folder_path($data_evento);
            
            $results = array('success' => true, 'operations' => array());
            
            // Rinomina Excel
            $old_excel_path = $folder_path . $nome_file_base . '.xlsx';
            $new_excel_path = $folder_path . 'NO_' . $nome_file_base . '.xlsx';
            
            try {
                $this->rename_dropbox_file($old_excel_path, $new_excel_path);
                $results['operations'][] = "Excel: {$old_excel_path} -> {$new_excel_path}";
                $results['excel_renamed'] = true;
            } catch (Exception $e) {
                $this->log("Errore rinomina Excel: " . $e->getMessage());
                $results['operations'][] = "Excel: ERRORE - " . $e->getMessage();
                $results['excel_renamed'] = false;
            }
            
            // Rinomina PDF
            $old_pdf_path = $folder_path . $nome_file_base . '.pdf';
            $new_pdf_path = $folder_path . 'NO_' . $nome_file_base . '.pdf';
            
            try {
                $this->rename_dropbox_file($old_pdf_path, $new_pdf_path);
                $results['operations'][] = "PDF: {$old_pdf_path} -> {$new_pdf_path}";
                $results['pdf_renamed'] = true;
            } catch (Exception $e) {
                $this->log("Errore rinomina PDF: " . $e->getMessage());
                $results['operations'][] = "PDF: ERRORE - " . $e->getMessage();
                $results['pdf_renamed'] = false;
            }
            
            $results['message'] = 'Operazione rinomina file completata';
            $results['excel_path'] = $new_excel_path;
            $results['pdf_path'] = $new_pdf_path;
            
            return $results;
            
        } catch (Exception $e) {
            $this->log("Errore generale rinomina file: " . $e->getMessage(), 'ERROR');
            return array(
                'success' => false,
                'message' => 'Errore rinomina file: ' . $e->getMessage(),
                'operations' => array()
            );
        }
    }

    /**
     * Lista contenuto cartella Dropbox
     */
    public function list_dropbox_folder($folder_path = '') {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_post($this->api_base_url . '/2/files/list_folder', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'body' => json_encode([
                'path' => $folder_path,
                'recursive' => false,
                'include_media_info' => false,
                'include_deleted' => false,
                'include_has_explicit_shared_members' => false
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore lista cartella: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore lista cartella HTTP {$status_code}: {$body}");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['entries'])) {
            throw new Exception("Risposta lista cartella non valida: {$body}");
        }
        
        return $result['entries'];
    }

    /**
     * Ottiene lista file preventivo (Excel e PDF)
     */
    public function get_preventivo_files($nome_file_base, $data_evento) {
        try {
            $folder_path = $this->generate_folder_path($data_evento);
            $files = $this->list_dropbox_folder($folder_path);
            
            $preventivo_files = array();
            
            foreach ($files as $file) {
                if ($file['.tag'] === 'file') {
                    $filename = $file['name'];
                    
                    // Cerca file che contengono il nome base (con o senza NO_)
                    if (strpos($filename, $nome_file_base) !== false) {
                        $preventivo_files[] = array(
                            'name' => $filename,
                            'path' => $file['path_display'],
                            'size' => $file['size'],
                            'modified' => $file['client_modified'],
                            'is_cancelled' => strpos($filename, 'NO_') === 0,
                            'type' => pathinfo($filename, PATHINFO_EXTENSION)
                        );
                    }
                }
            }
            
            $this->log("Trovati " . count($preventivo_files) . " file per {$nome_file_base}");
            return $preventivo_files;
            
        } catch (Exception $e) {
            $this->log("Errore ricerca file preventivo: " . $e->getMessage(), 'ERROR');
            return array();
        }
    }

    /**
     * Sincronizza stato preventivo con Dropbox
     */
    public function sync_preventivo_with_dropbox($preventivo) {
        try {
            if (empty($preventivo->nome_file)) {
                return 'unknown';
            }
            
            $files = $this->get_preventivo_files($preventivo->nome_file, $preventivo->data_evento);
            
            if (empty($files)) {
                return 'missing';
            }
            
            // Verifica se i file sono stati rinominati (hanno prefisso NO_)
            $has_renamed_files = false;
            $has_normal_files = false;
            
            foreach ($files as $file) {
                if ($file['is_cancelled']) {
                    $has_renamed_files = true;
                } else {
                    $has_normal_files = true;
                }
            }
            
            if ($has_renamed_files && !$has_normal_files) {
                return 'renamed'; // Tutti i file sono stati rinominati (annullati)
            } elseif ($has_normal_files) {
                return 'ok'; // File normali presenti
            } else {
                return 'unknown';
            }
            
        } catch (Exception $e) {
            $this->log("Errore sync preventivo {$preventivo->id}: " . $e->getMessage(), 'ERROR');
            return 'unknown';
        }
    }

    // ============================================================================
    // UTILITÀ E INFORMAZIONI ACCOUNT
    // ============================================================================

    /**
     * Ottiene spazio utilizzato su Dropbox
     */
    public function get_dropbox_usage() {
        try {
            $token = $this->get_valid_access_token();
            
            $response = wp_remote_post($this->api_base_url . '/2/users/get_space_usage', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'User-Agent' => '747-Disco-CRM/11.4.2'
                ],
                'body' => '',
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                throw new Exception('Errore spazio: ' . $response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code !== 200) {
                throw new Exception("Errore spazio HTTP {$status_code}: {$body}");
            }
            
            $result = json_decode($body, true);
            if (!$result || !isset($result['used'])) {
                throw new Exception("Risposta spazio non valida: {$body}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("Errore ottenimento spazio: " . $e->getMessage(), 'ERROR');
            return array('error' => $e->getMessage());
        }
    }

    /**
     * Verifica se file esiste su Dropbox
     */
    public function file_exists_on_dropbox($file_path) {
        try {
            $token = $this->get_valid_access_token();
            
            $response = wp_remote_post($this->api_base_url . '/2/files/get_metadata', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'User-Agent' => '747-Disco-CRM/11.4.2'
                ],
                'body' => json_encode([
                    'path' => $file_path,
                    'include_media_info' => false,
                    'include_deleted' => false,
                    'include_has_explicit_shared_members' => false
                ]),
                'timeout' => 30
            ]);
            
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
     * Crea link condivisione temporaneo
     */
    public function create_temporary_link($file_path) {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_post($this->api_base_url . '/2/files/get_temporary_link', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'body' => json_encode([
                'path' => $file_path
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore link temporaneo: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore link temporaneo HTTP {$status_code}: {$body}");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['link'])) {
            throw new Exception("Risposta link temporaneo non valida: {$body}");
        }
        
        return $result['link'];
    }

    /**
     * Elimina file da Dropbox
     */
    public function delete_dropbox_file($file_path) {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_post($this->api_base_url . '/2/files/delete_v2', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'body' => json_encode([
                'path' => $file_path
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore eliminazione file: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore eliminazione file HTTP {$status_code}: {$body}");
        }
        
        $this->log("File eliminato: {$file_path}");
        return true;
    }

    /**
     * Copia file invece di spostarlo (backup)
     */
    public function copy_dropbox_file($source_path, $dest_path) {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_post($this->api_base_url . '/2/files/copy_v2', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'User-Agent' => '747-Disco-CRM/11.4.2'
            ],
            'body' => json_encode([
                'from_path' => $source_path,
                'to_path' => $dest_path,
                'allow_shared_folder' => false,
                'autorename' => true,
                'allow_ownership_transfer' => false
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore copia Dropbox: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new Exception("Errore copia Dropbox HTTP {$status_code}: {$body}");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['metadata'])) {
            throw new Exception("Risposta copia non valida: {$body}");
        }
        
        $this->log("File copiato: {$source_path} -> {$dest_path}");
        return $result['metadata'];
    }

    // ============================================================================
    // BACKUP E RIPRISTINO
    // ============================================================================

    /**
     * Backup configurazione OAuth
     */
    public function backup_oauth_config() {
        $credentials = $this->get_oauth_credentials();
        
        $backup_data = array(
            'timestamp' => time(),
            'version' => '11.4.2',
            'credentials' => $credentials,
            'access_token_expires' => get_option('disco747_dropbox_token_expires', 0)
        );
        
        $backup_dir = wp_upload_dir()['basedir'] . '/disco747-backups/';
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $backup_file = $backup_dir . 'dropbox_oauth_backup_' . date('Y-m-d_H-i-s') . '.json';
        $result = file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            throw new Exception('Impossibile creare file backup');
        }
        
        $this->log("Backup OAuth creato: {$backup_file}");
        return $backup_file;
    }

    /**
     * Ripristina configurazione OAuth
     */
    public function restore_oauth_config($backup_file) {
        if (!file_exists($backup_file)) {
            throw new Exception('File backup non trovato');
        }
        
        $backup_content = file_get_contents($backup_file);
        if ($backup_content === false) {
            throw new Exception('Impossibile leggere file backup');
        }
        
        $backup_data = json_decode($backup_content, true);
        if (!$backup_data || !isset($backup_data['credentials'])) {
            throw new Exception('File backup non valido o corrotto');
        }
        
        $credentials = $backup_data['credentials'];
        
        // Ripristina credenziali
        update_option('disco747_dropbox_credentials', $credentials);
        
        // Ripristina scadenza token se presente
        if (isset($backup_data['access_token_expires'])) {
            update_option('disco747_dropbox_token_expires', $backup_data['access_token_expires']);
        }
        
        // Pulisci cache
        $this->credentials_cache = null;
        $this->access_token_cache = null;
        
        $this->log("Configurazione OAuth ripristinata da: {$backup_file}");
        return true;
    }

    // ============================================================================
    // UTILITÀ E HELPER
    // ============================================================================

    /**
     * Formatta bytes in formato leggibile
     */
    public function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Pulisce token scaduti dal database
     */
    public function cleanup_expired_tokens() {
        $expires_at = get_option('disco747_dropbox_token_expires', 0);
        
        if (time() > $expires_at + 3600) { // 1 ora dopo scadenza
            delete_option('disco747_dropbox_access_token');
            delete_option('disco747_dropbox_token_expires');
            $this->access_token_cache = null;
            $this->log('Token scaduti puliti dal database');
        }
    }

    /**
     * Verifica stato connessione veloce
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
            
            // Test veloce
            $response = wp_remote_post($this->api_base_url . '/2/users/get_current_account', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'User-Agent' => '747-Disco-CRM/11.4.2'
                ],
                'body' => '',
                'timeout' => 10
            ]);
            
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
                    'message' => "Errore HTTP {$status_code}"
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
     * Ottiene informazioni dettagliate account
     */
    public function get_account_info() {
        try {
            $token = $this->get_valid_access_token();
            
            $response = wp_remote_post($this->api_base_url . '/2/users/get_current_account', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'User-Agent' => '747-Disco-CRM/11.4.2'
                ],
                'body' => '',
                'timeout' => 15
            ]);
            
            if (is_wp_error($response)) {
                throw new Exception('Errore info account: ' . $response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code !== 200) {
                throw new Exception("Errore info account HTTP {$status_code}: {$body}");
            }
            
            $account_info = json_decode($body, true);
            if (!$account_info) {
                throw new Exception("Risposta info account non valida: {$body}");
            }
            
            return $account_info;
            
        } catch (Exception $e) {
            $this->log("Errore info account: " . $e->getMessage(), 'ERROR');
            return array('error' => $e->getMessage());
        }
    }

    /**
     * Reset completo configurazione (per debug)
     */
    public function reset_configuration() {
        // Elimina tutte le opzioni Dropbox
        delete_option('disco747_dropbox_credentials');
        delete_option('disco747_dropbox_access_token');
        delete_option('disco747_dropbox_token_expires');
        delete_option('disco747_dropbox_oauth_state');
        
        // Pulisci cache
        $this->credentials_cache = null;
        $this->access_token_cache = null;
        
        $this->log('Configurazione Dropbox resettata completamente');
        
        return array(
            'success' => true,
            'message' => 'Configurazione Dropbox resettata. Riconfigura OAuth.'
        );
    }

    /**
     * Diagnostica completa sistema
     */
    public function run_diagnostics() {
        $diagnostics = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '11.4.2',
            'oauth_configured' => false,
            'connection_test' => false,
            'credentials_valid' => false,
            'errors' => array(),
            'warnings' => array(),
            'info' => array()
        );

        try {
            // Test 1: Configurazione OAuth
            $diagnostics['oauth_configured'] = $this->is_oauth_configured();
            if (!$diagnostics['oauth_configured']) {
                $diagnostics['errors'][] = 'OAuth non configurato - mancano credenziali';
            } else {
                $diagnostics['info'][] = 'Credenziali OAuth presenti';
            }

            // Test 2: Validità credenziali
            if ($diagnostics['oauth_configured']) {
                try {
                    $token = $this->get_valid_access_token();
                    $diagnostics['credentials_valid'] = !empty($token);
                    if ($diagnostics['credentials_valid']) {
                        $diagnostics['info'][] = 'Access token ottenuto con successo';
                    }
                } catch (Exception $e) {
                    $diagnostics['errors'][] = 'Token non valido: ' . $e->getMessage();
                }
            }

            // Test 3: Connessione API
            if ($diagnostics['credentials_valid']) {
                $connection_test = $this->test_oauth_connection();
                $diagnostics['connection_test'] = $connection_test['success'];
                if ($connection_test['success']) {
                    $diagnostics['info'][] = 'Connessione API Dropbox funzionante';
                    if (isset($connection_test['user_name'])) {
                        $diagnostics['info'][] = 'Utente: ' . $connection_test['user_name'];
                    }
                } else {
                    $diagnostics['errors'][] = 'Test connessione fallito: ' . $connection_test['message'];
                }
            }

            // Test 4: Spazio storage
            if ($diagnostics['connection_test']) {
                try {
                    $usage = $this->get_dropbox_usage();
                    if (!isset($usage['error'])) {
                        $used_formatted = $this->format_bytes($usage['used'] ?? 0);
                        $total_formatted = $this->format_bytes($usage['allocation']['allocated'] ?? 0);
                        $diagnostics['info'][] = "Spazio utilizzato: {$used_formatted} / {$total_formatted}";
                    }
                } catch (Exception $e) {
                    $diagnostics['warnings'][] = 'Impossibile ottenere info spazio: ' . $e->getMessage();
                }
            }

            // Stato finale
            $diagnostics['overall_status'] = $diagnostics['oauth_configured'] && 
                                           $diagnostics['connection_test'] && 
                                           $diagnostics['credentials_valid'] ? 'OK' : 'ERROR';

        } catch (Exception $e) {
            $diagnostics['errors'][] = 'Errore diagnostica: ' . $e->getMessage();
            $diagnostics['overall_status'] = 'ERROR';
        }

        $this->log("Diagnostica completata: " . $diagnostics['overall_status']);
        return $diagnostics;
    }

    /**
     * Destructor per cleanup
     */
    public function __destruct() {
        if ($this->debug_mode) {
            $this->log('Dropbox Handler terminato');
        }
    }
}

// ============================================================================
// COMPATIBILITÀ CON VECCHIO PLUGIN - WRAPPER FUNCTIONS
// ============================================================================

/**
 * Funzioni wrapper per compatibilità con il vecchio plugin PreventiviPartyDropbox
 * Permettono al nuovo plugin di usare la stessa interfaccia del vecchio
 */
if (!class_exists('PreventiviPartyDropbox')) {
    class PreventiviPartyDropbox extends Disco747_CRM\Storage\Disco747_Dropbox {
        
        public function __construct() {
            parent::__construct();
        }
        
        // Metodi wrapper per mantenere compatibilità
        public function get_dropbox_access_token_from_refresh($refresh_token, $client_id, $client_secret) {
            return $this->refresh_access_token($refresh_token, $client_id, $client_secret);
        }
        
        // Tutti gli altri metodi sono già compatibili grazie all'ereditarietà
    }
}

?>