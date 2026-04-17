$body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 200) {
            $error_desc = $body['error_description'] ?? ($body['error'] ?? 'Errore sconosciuto');
            $error_msg = "Errore Google: {$error_desc}";
            $this->log($error_msg, 'ERROR');
            return array('success' => false, 'message' => $error_msg);
        }

        if (isset($body['refresh_token'])) {
            // Salva refresh token nelle credenziali
            $credentials['refresh_token'] = $body['refresh_token'];
            update_option('disco747_gd_credentials', $credentials);
            
            // Salva access token
            $this->save_access_token($body);
            
            $this->log('Refresh token Google Drive salvato con successo');
            
            return array(
                'success' => true, 
                'message' => 'Google Drive configurato con successo!',
                'refresh_token' => substr($body['refresh_token'], 0, 30) . '...'
            );
        } else {
            $error_msg = 'Refresh token non ricevuto. Assicurati che il "prompt" sia impostato su "consent" e riprova l\'autorizzazione';
            $this->log($error_msg, 'ERROR');
            return array('success' => false, 'message' => $error_msg);
        }
    }

    /**
     * Ottiene access token valido, rinfrescandolo se necessario
     * IDENTICO al vecchio plugin
     */
    public function get_valid_access_token() {
        // Check cache prima
        if ($this->access_token_cache) {
            $expires = get_option('disco747_googledrive_token_expires', 0);
            if (time() < $expires - 300) { // 5 minuti di margine
                return $this->access_token_cache;
            }
        }
        
        $token = get_option('disco747_googledrive_access_token');
        $expires = get_option('disco747_googledrive_token_expires', 0);

        if ($token && time() < $expires - 300) { // 5 minuti di margine
            $this->access_token_cache = $token;
            return $token;
        }

        $this->log('Access token scaduto o mancante. Eseguo il refresh');
        $credentials = $this->get_oauth_credentials();
        if (empty($credentials['refresh_token'])) {
            throw new Exception('Refresh token per Google Drive mancante. Riconfigurare l\'autorizzazione');
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $credentials['refresh_token'],
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret']
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Errore di connessione durante il refresh del token: ' . $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 200) {
            $error_desc = $body['error_description'] ?? ($body['error'] ?? 'Errore sconosciuto');
            if ($body['error'] === 'invalid_grant') {
                // Refresh token scaduto, cancellalo
                $credentials['refresh_token'] = '';
                update_option('disco747_gd_credentials', $credentials);
                throw new Exception("Refresh token non valido o scaduto ({$error_desc}). È necessaria una nuova autorizzazione");
            }
            throw new Exception("Errore durante il refresh del token: {$error_desc}");
        }

        $this->save_access_token($body);
        $this->access_token_cache = $body['access_token'];
        return $body['access_token'];
    }

    /**
     * Salva access token e la sua scadenza
     */
    private function save_access_token($token_data) {
        if (isset($token_data['access_token'])) {
            $expires_in = $token_data['expires_in'] ?? 3600; // 1 ora di default
            update_option('disco747_googledrive_access_token', $token_data['access_token']);
            update_option('disco747_googledrive_token_expires', time() + $expires_in);
            $this->log('Nuovo access token Google Drive salvato');
        }
    }

    /**
     * Test connessione all'API di Google Drive
     * IDENTICO al vecchio plugin
     */
    public function test_oauth_connection() {
        try {
            $token = $this->get_valid_access_token();
            
            $response = wp_remote_get('https://www.googleapis.com/drive/v3/about?fields=user', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'timeout' => 15
            ]);
            
            if (is_wp_error($response)) {
                $error_msg = 'Errore di connessione: ' . $response->get_error_message();
                $this->log($error_msg, 'ERROR');
                return array('success' => false, 'message' => $error_msg);
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code === 200) {
                $this->log('Test OAuth Google Drive riuscito');
                return array(
                    'success' => true,
                    'message' => 'Connesso a Google Drive con successo!',
                    'user_name' => $body['user']['displayName'] ?? 'N/D',
                    'user_email' => $body['user']['emailAddress'] ?? 'N/D'
                );
            } else {
                $error_details = $body['error']['message'] ?? 'Errore API sconosciuto';
                $error_msg = "Errore API Google Drive: {$error_details}";
                $this->log($error_msg, 'ERROR');
                return array('success' => false, 'message' => $error_msg);
            }
        } catch (Exception $e) {
            $error_msg = 'Errore durante il test: ' . $e->getMessage();
            $this->log($error_msg, 'ERROR');
            return array('success' => false, 'message' => $error_msg);
        }
    }

    // ============================================================================
    // GESTIONE FILE E UPLOAD - COMPLETA dal vecchio plugin
    // ============================================================================

    /**
     * Upload file su Google Drive con gestione automatica file grandi
     * LOGICA IDENTICA al vecchio plugin
     */
    public function upload_to_googledrive($local_file_path, $remote_filename, $data_evento) {
        if (!file_exists($local_file_path)) {
            throw new Exception("File locale non trovato: {$local_file_path}");
        }

        $file_size = filesize($local_file_path);
        $this->log("Upload Google Drive: {$remote_filename} ({$file_size} bytes)");
        
        if ($file_size > 5 * 1024 * 1024) { // 5MB
            $this->log("File grande ({$file_size} bytes), uso resumable upload");
            return $this->upload_large_file($local_file_path, $remote_filename, $data_evento);
        } else {
            $this->log("File standard ({$file_size} bytes), uso multipart upload");
            return $this->upload_small_file($local_file_path, $remote_filename, $data_evento);
        }
    }

    /**
     * Upload file "piccolo" (< 5MB) con multipart upload
     * IDENTICO al vecchio plugin
     */
    private function upload_small_file($local_file_path, $remote_filename, $data_evento) {
        $token = $this->get_valid_access_token();
        $folder_id = $this->get_or_create_folder_structure($data_evento);
        $file_content = file_get_contents($local_file_path);
        
        $boundary = '-------' . microtime(true);
        $metadata = json_encode(['name' => $remote_filename, 'parents' => [$folder_id]]);
        
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= "{$metadata}\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: " . mime_content_type($local_file_path) . "\r\n\r\n";
        $body .= "{$file_content}\r\n";
        $body .= "--{$boundary}--";

        $response = wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => "multipart/related; boundary=\"{$boundary}\"",
            ],
            'body' => $body,
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Errore upload: ' . $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 200) {
            $error_message = $response_body['error']['message'] ?? 'Errore sconosciuto';
            throw new Exception("Errore API durante l'upload: {$error_message}");
        }

        $this->log("File caricato con successo su Google Drive, ID: " . $response_body['id']);
        return $response_body['id']; // Ritorna l'ID del file su Google Drive
    }

    /**
     * Upload file "grande" (> 5MB) con resumable upload
     * PLACEHOLDER per implementazione futura
     */
    private function upload_large_file($local_file_path, $remote_filename, $data_evento) {
        $this->log("Upload file grandi non ancora implementato completamente");
        throw new Exception("L'upload di file di dimensioni superiori a 5MB non è ancora pienamente supportato in questa versione");
    }

    // ============================================================================
    // GESTIONE CARTELLE - COMPLETA dal vecchio plugin
    // ============================================================================

    /**
     * Ottiene o crea la struttura delle cartelle (Anno/Mese) 
     * IDENTICO al vecchio plugin con cache transient
     */
    private function get_or_create_folder_structure($data_evento) {
        $cache_key = 'disco747_gdrive_folder_' . date('Y_m', strtotime($data_evento));
        $cached_folder_id = get_transient($cache_key);
        if ($cached_folder_id) {
            $this->log("Uso ID cartella dalla cache: {$cached_folder_id}");
            return $cached_folder_id;
        }

        $credentials = $this->get_oauth_credentials();
        $main_folder_id = $credentials['folder_id'] ?? '';
        
        if (empty($main_folder_id)) {
            $main_folder_id = $this->create_folder('PreventiviParty', 'root');
            $credentials['folder_id'] = $main_folder_id;
            update_option('disco747_gd_credentials', $credentials);
            $this->log("Cartella principale 'PreventiviParty' creata con ID: {$main_folder_id}");
        }

        $year = date('Y', strtotime($data_evento));
        $year_folder_id = $this->find_or_create_folder($year, $main_folder_id);
        
        $months_it = [
            '01' => 'Gennaio', '02' => 'Febbraio', '03' => 'Marzo', '04' => 'Aprile',
            '05' => 'Maggio', '06' => 'Giugno', '07' => 'Luglio', '08' => 'Agosto',
            '09' => 'Settembre', '10' => 'Ottobre', '11' => 'Novembre', '12' => 'Dicembre'
        ];
        $month_name = $months_it[date('m', strtotime($data_evento))] ?? 'MeseSconosciuto';
        $month_folder_id = $this->find_or_create_folder($month_name, $year_folder_id);
        
        set_transient($cache_key, $month_folder_id, DAY_IN_SECONDS);
        return $month_folder_id;
    }

    /**
     * Cerca cartella per nome, se non la trova la crea
     */
    private function find_or_create_folder($folder_name, $parent_id) {
        $token = $this->get_valid_access_token();
        
        $query = "name='{$folder_name}' and '{$parent_id}' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false";
        $response = wp_remote_get('https://www.googleapis.com/drive/v3/files?' . http_build_query(['q' => $query, 'fields' => 'files(id)']), [
            'headers' => ['Authorization' => 'Bearer ' . $token], 
            'timeout' => 30
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($data['files'])) {
                $this->log("Cartella '{$folder_name}' trovata con ID: " . $data['files'][0]['id']);
                return $data['files'][0]['id'];
            }
        }
        
        $this->log("Cartella '{$folder_name}' non trovata, la creo all'interno di {$parent_id}");
        return $this->create_folder($folder_name, $parent_id);
    }
    
    /**
     * Crea cartella su Google Drive
     */
    private function create_folder($folder_name, $parent_id) {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_post('https://www.googleapis.com/drive/v3/files', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token, 
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'name' => $folder_name, 
                'mimeType' => 'application/vnd.google-apps.folder', 
                'parents' => [$parent_id]
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new Exception("Errore durante la creazione della cartella: " . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 200) {
            $error_message = $body['error']['message'] ?? 'Errore sconosciuto';
            throw new Exception("Errore API durante la creazione della cartella: {$error_message}");
        }
        
        $this->log("Cartella '{$folder_name}' creata con ID: " . $body['id']);
        return $body['id'];
    }

    // ============================================================================
    // GESTIONE FILE E OPERAZIONI AVANZATE
    // ============================================================================

    /**
     * Lista file in una cartella Google Drive
     */
    public function list_googledrive_folder($folder_id = null) {
        $token = $this->get_valid_access_token();
        
        if (!$folder_id) {
            $credentials = $this->get_oauth_credentials();
            $folder_id = $credentials['folder_id'] ?? 'root';
        }
        
        $query = "'{$folder_id}' in parents and trashed=false";
        $response = wp_remote_get('https://www.googleapis.com/drive/v3/files?' . http_build_query([
            'q' => $query,
            'fields' => 'files(id,name,size,modifiedTime,mimeType,parents)',
            'pageSize' => 100
        ]), [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore lista cartella Google Drive: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            throw new Exception("Errore lista cartella Google Drive HTTP {$http_code}: {$body}");
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['files'])) {
            throw new Exception("Risposta lista cartella non valida: {$body}");
        }
        
        return $result['files'];
    }

    /**
     * Verifica se file esiste su Google Drive
     */
    public function file_exists_on_googledrive($file_name, $folder_id = null) {
        try {
            $files = $this->list_googledrive_folder($folder_id);
            
            foreach ($files as $file) {
                if ($file['name'] === $file_name) {
                    return $file['id'];
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log("Errore verifica esistenza file: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Elimina file da Google Drive
     */
    public function delete_googledrive_file($file_id) {
        $token = $this->get_valid_access_token();
        
        $response = wp_remote_request('https://www.googleapis.com/drive/v3/files/' . $file_id, [
            'method' => 'DELETE',
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Errore eliminazione file Google Drive: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code !== 204) {
            $body = wp_remote_retrieve_body($response);
            throw new Exception("Errore eliminazione file Google Drive HTTP {$http_code}: {$body}");
        }
        
        $this->log("File eliminato da Google Drive: {$file_id}");
        return true;
    }

    /**
     * Ottiene link condivisione per file
     */
    public function create_shareable_link($file_id) {
        $token = $this->get_valid_access_token();
        
        // Rendi il file pubblico
        $permission_response = wp_remote_post("https://www.googleapis.com/drive/v3/files/{$file_id}/permissions", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'role' => 'reader',
                'type' => 'anyone'
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($permission_response)) {
            throw new Exception('Errore impostazione permessi: ' . $permission_response->get_error_message());
        }
        
        // Ottieni link di visualizzazione
        $file_response = wp_remote_get("https://www.googleapis.com/drive/v3/files/{$file_id}?fields=webViewLink", [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 30
        ]);
        
        if (is_wp_error($file_response)) {
            throw new Exception('Errore ottenimento link: ' . $file_response->get_error_message());
        }
        
        $file_data = json_decode(wp_remote_retrieve_body($file_response), true);
        
        if (!isset($file_data['webViewLink'])) {
            throw new Exception('Link non disponibile');
        }
        
        return $file_data['webViewLink'];
    }

    // ============================================================================
    // SINCRONIZZAZIONE E GESTIONE PREVENTIVI
    // ============================================================================

    /**
     * Ottiene file preventivo (placeholder per compatibilità)
     */
    public function get_preventivo_files($nome_file_base, $data_evento) {
        try {
            $folder_id = $this->get_or_create_folder_structure($data_evento);
            $files = $this->list_googledrive_folder($folder_id);
            
            $preventivo_files = array();
            
            foreach ($files as $file) {
                if (strpos($file['name'], $nome_file_base) !== false) {
                    $preventivo_files[] = array(
                        'name' => $file['name'],
                        'id' => $file['id'],
                        'size' => $file['size'] ?? 0,
                        'modified' => $file['modifiedTime'] ?? '',
                        'is_cancelled' => strpos($file['name'], 'NO_') === 0,
                        'type' => pathinfo($file['name'], PATHINFO_EXTENSION)
                    );
                }
            }
            
            $this->log("Trovati " . count($preventivo_files) . " file Google Drive per {$nome_file_base}");
            return $preventivo_files;
            
        } catch (Exception $e) {
            $this->log("Errore ricerca file preventivo Google Drive: " . $e->getMessage(), 'ERROR');
            return array();
        }
    }

    /**
     * Sincronizza preventivo con Google Drive (placeholder)
     */
    public function sync_preventivo_with_googledrive($preventivo) {
        try {
            if (empty($preventivo->nome_file)) {
                return 'unknown';
            }
            
            $files = $this->get_preventivo_files($preventivo->nome_file, $preventivo->data_evento);
            
            if (empty($files)) {
                return 'missing';
            }
            
            // Verifica stato file
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
                return 'renamed';
            } elseif ($has_normal_files) {
                return 'ok';
            } else {
                return 'unknown';
            }
            
        } catch (Exception $e) {
            $this->log("Errore sync preventivo Google Drive {$preventivo->id}: " . $e->getMessage(), 'ERROR');
            return 'unknown';
        }
    }

    // ============================================================================
    // UTILITÀ E INFORMAZIONI
    // ============================================================================

    /**
     * Ottiene informazioni account Google Drive
     */
    public function get_account_info() {
        try {
            $token = $this->get_valid_access_token();
            
            $response = wp_remote_get('https://www.googleapis.com/drive/v3/about?fields=user,storageQuota', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'timeout' => 15
            ]);
            
            if (is_wp_error($response)) {
                throw new Exception('Errore info account: ' . $response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code === 200) {
                return $body;
            } else {
                throw new Exception("Errore API Google Drive HTTP {$http_code}");
            }
            
        } catch (Exception $e) {
            $this->log("Errore info account Google Drive: " . $e->getMessage(), 'ERROR');
            return array('error' => $e->getMessage());
        }
    }

    /**
     * Ottiene quota storage Google Drive
     */
    public function get_googledrive_usage() {
        try {
            $account_info = $this->get_account_info();
            
            if (isset($account_info['error'])) {
                return $account_info;
            }
            
            $quota = $account_info['storageQuota'] ?? array();
            
            return array(
                'used' => intval($quota['usage'] ?? 0),
                'limit' => intval($quota['limit'] ?? 0),
                'used_in_drive' => intval($quota['usageInDrive'] ?? 0),
                'used_in_drive_trash' => intval($quota['usageInDriveTrash'] ?? 0)
            );
            
        } catch (Exception $e) {
            $this->log("Errore quota Google Drive: " . $e->getMessage(), 'ERROR');
            return array('error' => $e->getMessage());
        }
    }

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
     * Verifica stato connessione veloce
     */
    public function check_googledrive_connection() {
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
            $response = wp_remote_get('https://www.googleapis.com/drive/v3/about?fields=user', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'timeout' => 10
            ]);
            
            if (is_wp_error($response)) {
                return array(
                    'connected' => false,
                    'message' => 'Errore connessione: ' . $response->get_error_message()
                );
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            
            if ($http_code === 200) {
                return array(
                    'connected' => true,
                    'message' => 'Connesso a Google Drive'
                );
            } else {
                return array(
                    'connected' => false,
                    'message' => "Errore HTTP {$http_code}"
                );
            }
            
        } catch (Exception $e) {
            return array(
                'connected' => false,
                'message' => 'Errore: ' . $e->getMessage()
            );
        }
    }

    // ============================================================================
    // BACKUP E RESET
    // ============================================================================

    /**
     * Reset configurazione Google Drive
     */
    public function reset_configuration() {
        // Elimina tutte le opzioni Google Drive
        delete_option('disco747_gd_credentials');
        delete_option('disco747_googledrive_access_token');
        delete_option('disco747_googledrive_token_expires');
        delete_option('disco747_googledrive_oauth_state');
        
        // Pulisci cache
        $this->credentials_cache = null;
        $this->access_token_cache = null;
        
        $this->log('Configurazione Google Drive resettata completamente');
        
        return array(
            'success' => true,
            'message' => 'Configurazione Google Drive resettata. Riconfigura OAuth.'
        );
    }

    /**
     * Backup configurazione OAuth
     */
    public function backup_oauth_config() {
        $credentials = $this->get_oauth_credentials();
        
        $backup_data = array(
            'timestamp' => time(),
            'version' => '11.4.2',
            'credentials' => $credentials,
            'access_token_expires' => get_option('disco747_googledrive_token_expires', 0)
        );
        
        $backup_dir = wp_upload_dir()['basedir'] . '/disco747-backups/';
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $backup_file = $backup_dir . 'googledrive_oauth_backup_' . date('Y-m-d_H-i-s') . '.json';
        $result = file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            throw new Exception('Impossibile creare file backup');
        }
        
        $this->log("Backup OAuth Google Drive creato: {$backup_file}");
        return $backup_file;
    }

    /**
     * Diagnostica completa sistema Google Drive
     */
    public function run_diagnostics() {
        $diagnostics = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '11.4.2',
            'oauth_configured' => false,
            'connection_test' => false,
            'credentials_valid' => false,
            'folder_structure' => false,
            'errors' => array(),
            'warnings' => array(),
            'info' => array()
        );

        try {
            // Test 1: Configurazione OAuth
            $diagnostics['oauth_configured'] = $this->is_oauth_configured();
            if (!$diagnostics['oauth_configured']) {
                $diagnostics['errors'][] = 'OAuth Google Drive non configurato';
            } else {
                $diagnostics['info'][] = 'Credenziali OAuth Google Drive presenti';
            }

            // Test 2: Validità credenziali
            if ($diagnostics['oauth_configured']) {
                try {
                    $token = $this->get_valid_access_token();
                    $diagnostics['credentials_valid'] = !empty($token);
                    if ($diagnostics['credentials_valid']) {
                        $diagnostics['info'][] = 'Access token Google Drive ottenuto';
                    }
                } catch (Exception $e) {
                    $diagnostics['errors'][] = 'Token Google Drive non valido: ' . $e->getMessage();
                }
            }

            // Test 3: Connessione API
            if ($diagnostics['credentials_valid']) {
                $connection_test = $this->test_oauth_connection();
                $diagnostics['connection_test'] = $connection_test['success'];
                if ($connection_test['success']) {
                    $diagnostics['info'][] = 'Connessione API Google Drive funzionante';
                    if (isset($connection_test['user_name'])) {
                        $diagnostics['info'][] = 'Utente: ' . $connection_test['user_name'];
                    }
                } else {
                    $diagnostics['errors'][] = 'Test connessione Google Drive fallito: ' . $connection_test['message'];
                }
            }

            // Test 4: Struttura cartelle
            if ($diagnostics['connection_test']) {
                try {
                    $credentials = $this->get_oauth_credentials();
                    if (!empty($credentials['folder_id'])) {
                        $diagnostics['folder_structure'] = true;
                        $diagnostics['info'][] = 'Cartella principale configurata: ' . $credentials['folder_id'];
                    } else {
                        $diagnostics['warnings'][] = 'Cartella principale verrà creata al primo upload';
                    }
                } catch (Exception $e) {
                    $diagnostics['warnings'][] = 'Errore verifica cartelle: ' . $e->getMessage();
                }
            }

            // Test 5: Quota storage
            if ($diagnostics['connection_test']) {
                try {
                    $usage = $this->get_googledrive_usage();
                    if (!isset($usage['error'])) {
                        $used_formatted = $this->format_bytes($usage['used'] ?? 0);
                        $limit_formatted = $this->format_bytes($usage['limit'] ?? 0);
                        $diagnostics['info'][] = "Spazio Google Drive: {$used_formatted} / {$limit_formatted}";
                    }
                } catch (Exception $e) {
                    $diagnostics['warnings'][] = 'Impossibile ottenere quota Google Drive: ' . $e->getMessage();
                }
            }

            // Stato finale
            $diagnostics['overall_status'] = $diagnostics['oauth_configured'] && 
                                           $diagnostics['connection_test'] && 
                                           $diagnostics['credentials_valid'] ? 'OK' : 'ERROR';

        } catch (Exception $e) {
            $diagnostics['errors'][] = 'Errore diagnostica Google Drive: ' . $e->getMessage();
            $diagnostics['overall_status'] = 'ERROR';
        }

        $this->log("Diagnostica Google Drive completata: " . $diagnostics['overall_status']);
        return $diagnostics;
    }

    /**
     * Pulisce token scaduti e cache
     */
    public function cleanup_expired_tokens() {
        $expires_at = get_option('disco747_googledrive_token_expires', 0);
        
        if (time() > $expires_at + 3600) { // 1 ora dopo scadenza
            delete_option('disco747_googledrive_access_token');
            delete_option('disco747_googledrive_token_expires');
            $this->access_token_cache = null;
            $this->log('Token Google Drive scaduti puliti dal database');
        }
        
        // Pulisci cache cartelle vecchie
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_disco747_gdrive_folder_%' AND option_value < " . (time() - DAY_IN_SECONDS));
    }

    /**
     * Destructor per cleanup
     */
    public function __destruct() {
        if ($this->debug_mode) {
            $this->log('Google Drive Handler terminato');
        }
    }
}

// ============================================================================
// COMPATIBILITÀ CON VECCHIO PLUGIN - WRAPPER FUNCTIONS
// ============================================================================

/**
 * Funzioni wrapper per compatibilità con il vecchio plugin PreventiviPartyGoogleDrive
 * Permettono al nuovo plugin di usare la stessa interfaccia del vecchio
 */
if (!class_exists('PreventiviPartyGoogleDrive')) {
    class PreventiviPartyGoogleDrive extends Disco747_CRM\Storage\Disco747_GoogleDrive {
        
        public function __construct() {
            parent::__construct();
        }
        
        // Metodi wrapper specifici per mantenere compatibilità
        public function upload_to_googledrive($local_file_path, $remote_filename, $data_evento) {
            return parent::upload_to_googledrive($local_file_path, $remote_filename, $data_evento);
        }
        
        // Tutti gli altri metodi sono già compatibili grazie all'ereditarietà
    }
}

?><?php
/**
 * 747 Disco CRM - Google Drive Storage Handler v11.4.2
 * COMPLETO: Integra TUTTA la logica OAuth del vecchio PreventiviPartyGoogleDrive
 * RIPRISTINA: Refresh Token, OAuth completo, upload, gestione cartelle
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
 * Classe Disco747_GoogleDrive
 * 
 * Gestisce tutte le operazioni Google Drive con OAuth 2.0 completo
 * REPLICA ESATTA della funzionalità del vecchio PreventiviPartyGoogleDrive
 * 
 * @since 11.4.2
 */
class Disco747_GoogleDrive {

    /**
     * Configurazione
     */
    private $debug_mode = true;
    private $upload_dir;
    
    /**
     * Cache per performance
     */
    private $access_token_cache = null;
    private $credentials_cache = null;

    /**
     * Costruttore
     */
    public function __construct() {
        $this->upload_dir = wp_upload_dir()['basedir'] . '/preventivi/';
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
        $this->log('Google Drive Handler v11.4.2 inizializzato');
    }

    /**
     * Logging interno
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode && function_exists('error_log')) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[{$timestamp}] [Disco747_GoogleDrive] [{$level}] {$message}");
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
        $new_credentials = get_option('disco747_gd_credentials', array());
        if (!empty($new_credentials['client_id'])) {
            $this->credentials_cache = $new_credentials;
            return $new_credentials;
        }
        
        // FALLBACK: Vecchia struttura per migrazione
        $old_credentials = array(
            'client_id' => get_option('preventivi_googledrive_client_id', ''),
            'client_secret' => get_option('preventivi_googledrive_client_secret', ''),
            'redirect_uri' => get_option('preventivi_googledrive_redirect_uri', ''),
            'refresh_token' => get_option('preventivi_googledrive_refresh_token', ''),
            'folder_id' => get_option('preventivi_googledrive_folder_id', '')
        );
        
        // Se trovate credenziali vecchie, migrale automaticamente
        if (!empty($old_credentials['client_id'])) {
            update_option('disco747_gd_credentials', $old_credentials);
            $this->credentials_cache = $old_credentials;
            $this->log('Credenziali Google Drive migrate dal vecchio plugin');
            return $old_credentials;
        }
        
        // Credenziali vuote di default
        $default_credentials = array(
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => admin_url('admin.php?page=disco747-settings'),
            'refresh_token' => '',
            'folder_id' => ''
        );
        
        $this->credentials_cache = $default_credentials;
        return $default_credentials;
    }

    /**
     * Verifica se OAuth è configurato completamente
     */
    public function is_oauth_configured() {
        $credentials = $this->get_oauth_credentials();
        return !empty($credentials['client_id']) && 
               !empty($credentials['client_secret']) && 
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
        
        if (empty($credentials['client_id']) || empty($credentials['redirect_uri'])) {
            return array('success' => false, 'message' => 'Client ID o Redirect URI non configurati');
        }

        $state = wp_create_nonce('googledrive_oauth_' . time());
        update_option('disco747_googledrive_oauth_state', $state);

        $params = [
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $credentials['redirect_uri'],
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'response_type' => 'code',
            'access_type' => 'offline', // IMPORTANTE: Per ottenere refresh token
            'prompt' => 'consent', // IMPORTANTE: Forza re-consent per refresh token
            'state' => $state
        ];

        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        
        $this->log('URL autorizzazione Google Drive generato');
        
        return array(
            'success' => true, 
            'auth_url' => $auth_url,
            'state' => $state
        );
    }

    /**
     * Scambia authorization code con refresh token
     * LOGICA COMPLETA dal vecchio plugin
     */
    public function exchange_code_for_tokens($auth_code, $state) {
        $stored_state = get_option('disco747_googledrive_oauth_state');
        if (empty($state) || $state !== $stored_state) {
            return array('success' => false, 'message' => 'Errore di sicurezza: stato OAuth non valido');
        }
        delete_option('disco747_googledrive_oauth_state');

        $credentials = $this->get_oauth_credentials();
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            return array('success' => false, 'message' => 'Credenziali OAuth incomplete');
        }

        $this->log('Scambio code per tokens Google Drive...');

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => trim($auth_code),
                'client_id' => trim($credentials['client_id']),
                'client_secret' => trim($credentials['client_secret']),
                'redirect_uri' => $credentials['redirect_uri']
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            $error_msg = 'Errore di connessione: ' . $response->get_error_message();
            $this->log($error_msg, 'ERROR');
            return array('success' => false, 'message' => $error_msg);
        }

        $body = json_decode(wp_remote