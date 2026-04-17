<?php
/**
 * Classe helper per la gestione delle impostazioni
 *
 * Centralizza tutta la logica di supporto per le impostazioni del plugin,
 * inclusi valori di default, validazione, sanitizzazione e utility varie.
 *
 * @package    Disco747_CRM
 * @subpackage Admin
 * @since      1.0.0
 * @version    1.0.0
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Admin;

use Disco747_CRM\Core\Disco747_Config;
use Disco747_CRM\Storage\Disco747_Storage_Manager;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe Disco747_Settings_Helper
 *
 * Fornisce funzionalità di supporto per la gestione delle impostazioni,
 * inclusi valori predefiniti, validazione, sanitizzazione e utility.
 */
class Disco747_Settings_Helper {

    /**
     * Valori di default per tutte le impostazioni
     *
     * @var array
     */
    private static $defaults = array(
        // Impostazioni generali
        'company_name' => '747 Disco',
        'company_email' => 'info@gestionale.747disco.it',
        'company_phone' => '06 123456789',
        'debug_mode' => false,

        // Storage
        'storage_type' => 'dropbox',

        // Messaggistica
        'email_subject' => 'Il tuo preventivo 747 Disco è pronto!',
        'email_template' => '',  // Verrà caricato dinamicamente
        'whatsapp_template' => '',  // Verrà caricato dinamicamente
        'default_send_mode' => 'both',

        // Autenticazione
        'session_timeout' => 3600,  // 1 ora
        'max_login_attempts' => 5,

        // Sistema
        'plugin_version' => Disco747_Config::VERSION,
        'database_version' => '1.0.0',
        'last_update_check' => 0
    );

    /**
     * Modalità di invio supportate
     *
     * @var array
     */
    private static $send_modes = array(
        'email' => 'Solo Email',
        'whatsapp' => 'Solo WhatsApp', 
        'both' => 'Email e WhatsApp',
        'none' => 'Nessun invio automatico'
    );

    /**
     * Tipi di storage supportati
     *
     * @var array
     */
    private static $storage_types = array(
        'dropbox' => 'Dropbox',
        'googledrive' => 'Google Drive'
    );

    /**
     * Costruttore
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Inizializza template predefiniti se non esistono
        if (empty(self::$defaults['email_template'])) {
            self::$defaults['email_template'] = $this->get_default_email_template();
        }
        if (empty(self::$defaults['whatsapp_template'])) {
            self::$defaults['whatsapp_template'] = $this->get_default_whatsapp_template();
        }
    }

    // ============================================================================
    // GESTIONE VALORI DI DEFAULT
    // ============================================================================

    /**
     * Ottiene un valore di default
     *
     * @since 1.0.0
     * @param string $key Chiave del valore richiesto
     * @return mixed Valore di default
     */
    public function get_default($key) {
        return isset(self::$defaults[$key]) ? self::$defaults[$key] : null;
    }

    /**
     * Ottiene tutti i valori di default
     *
     * @since 1.0.0
     * @return array Array dei valori di default
     */
    public function get_all_defaults() {
        return self::$defaults;
    }

    /**
     * Imposta un nuovo valore di default
     *
     * @since 1.0.0
     * @param string $key Chiave del valore
     * @param mixed $value Nuovo valore di default
     */
    public function set_default($key, $value) {
        self::$defaults[$key] = $value;
    }

    /**
     * Ripristina tutti i valori di default
     *
     * @since 1.0.0
     * @return bool True se il ripristino è avvenuto con successo
     */
    public function reset_to_defaults() {
        foreach (self::$defaults as $key => $value) {
            update_option("disco747_{$key}", $value);
        }
        
        return true;
    }

    // ============================================================================
    // FUNZIONI DI SANITIZZAZIONE
    // ============================================================================

    /**
     * Sanitizza un campo di testo
     *
     * @since 1.0.0
     * @param string $input Input da sanitizzare
     * @return string Input sanitizzato
     */
    public function sanitize_text_field($input) {
        return sanitize_text_field(trim($input));
    }

    /**
     * Sanitizza un campo email
     *
     * @since 1.0.0
     * @param string $input Input da sanitizzare
     * @return string Email sanitizzata
     */
    public function sanitize_email($input) {
        $email = sanitize_email(trim($input));
        if (!is_email($email)) {
            add_settings_error(
                'disco747_messages',
                'invalid_email',
                __('Indirizzo email non valido.', 'disco747'),
                'error'
            );
            return get_option('disco747_company_email', self::$defaults['company_email']);
        }
        return $email;
    }

    /**
     * Sanitizza un campo textarea
     *
     * @since 1.0.0
     * @param string $input Input da sanitizzare
     * @return string Textarea sanitizzata
     */
    public function sanitize_textarea_field($input) {
        return sanitize_textarea_field(trim($input));
    }

    /**
     * Sanitizza un template HTML
     *
     * @since 1.0.0
     * @param string $input Input da sanitizzare
     * @return string Template sanitizzato
     */
    public function sanitize_html_template($input) {
        // Permette HTML sicuro per i template email
        $allowed_html = array(
            'div' => array('style' => array(), 'class' => array()),
            'p' => array('style' => array(), 'class' => array()),
            'h1' => array('style' => array(), 'class' => array()),
            'h2' => array('style' => array(), 'class' => array()),
            'h3' => array('style' => array(), 'class' => array()),
            'strong' => array('style' => array()),
            'b' => array(),
            'em' => array(),
            'i' => array(),
            'br' => array(),
            'a' => array('href' => array(), 'style' => array(), 'class' => array()),
            'img' => array('src' => array(), 'alt' => array(), 'style' => array(), 'class' => array()),
            'table' => array('style' => array(), 'class' => array()),
            'tr' => array('style' => array(), 'class' => array()),
            'td' => array('style' => array(), 'class' => array()),
            'th' => array('style' => array(), 'class' => array())
        );

        return wp_kses(trim($input), $allowed_html);
    }

    /**
     * Sanitizza un checkbox
     *
     * @since 1.0.0
     * @param mixed $input Input da sanitizzare
     * @return bool Valore booleano
     */
    public function sanitize_checkbox($input) {
        return !empty($input) && $input !== '0';
    }

    /**
     * Sanitizza un numero intero positivo
     *
     * @since 1.0.0
     * @param mixed $input Input da sanitizzare
     * @return int Numero intero positivo
     */
    public function sanitize_positive_integer($input) {
        $value = intval($input);
        return max(1, $value);
    }

    /**
     * Sanitizza credenziali OAuth
     *
     * @since 1.0.0
     * @param string $input Input da sanitizzare
     * @return string Credenziale sanitizzata
     */
    public function sanitize_credential($input) {
        return sanitize_text_field(trim($input));
    }

    /**
     * Sanitizza il tipo di storage
     *
     * @since 1.0.0
     * @param string $input Input da sanitizzare
     * @return string Tipo di storage valido
     */
    public function sanitize_storage_type($input) {
        $input = sanitize_key($input);
        if (!array_key_exists($input, self::$storage_types)) {
            return 'dropbox'; // Default fallback
        }
        return $input;
    }

    /**
     * Sanitizza la modalità di invio
     *
     * @since 1.0.0
     * @param string $input Input da sanitizzare
     * @return string Modalità di invio valida
     */
    public function sanitize_send_mode($input) {
        $input = sanitize_key($input);
        if (!array_key_exists($input, self::$send_modes)) {
            return 'both'; // Default fallback
        }
        return $input;
    }

    // ============================================================================
    // FUNZIONI DI VALIDAZIONE
    // ============================================================================

    /**
     * Valida e salva le impostazioni generali
     *
     * @since 1.0.0
     * @param array $post_data Dati POST del form
     * @return array Risultato della validazione
     */
    public function validate_and_save_general_settings($post_data) {
        $errors = array();
        $success_count = 0;

        // Valida nome azienda
        if (empty($post_data['disco747_company_name'])) {
            $errors[] = __('Il nome dell\'azienda è obbligatorio.', 'disco747');
        } else {
            update_option('disco747_company_name', $this->sanitize_text_field($post_data['disco747_company_name']));
            $success_count++;
        }

        // Valida email aziendale
        if (empty($post_data['disco747_company_email'])) {
            $errors[] = __('L\'email aziendale è obbligatoria.', 'disco747');
        } else {
            $email = $this->sanitize_email($post_data['disco747_company_email']);
            if (is_email($email)) {
                update_option('disco747_company_email', $email);
                $success_count++;
            } else {
                $errors[] = __('L\'email aziendale non è valida.', 'disco747');
            }
        }

        // Salva telefono (opzionale)
        if (!empty($post_data['disco747_company_phone'])) {
            update_option('disco747_company_phone', $this->sanitize_text_field($post_data['disco747_company_phone']));
            $success_count++;
        }

        // Salva debug mode
        $debug_mode = $this->sanitize_checkbox($post_data['disco747_debug_mode'] ?? false);
        update_option('disco747_debug_mode', $debug_mode);
        $success_count++;

        if (!empty($errors)) {
            return array(
                'success' => false,
                'message' => implode(' ', $errors)
            );
        }

        return array(
            'success' => true,
            'message' => sprintf(__('%d impostazioni salvate con successo.', 'disco747'), $success_count)
        );
    }

    /**
     * Valida e salva le impostazioni di messaggistica
     *
     * @since 1.0.0
     * @param array $post_data Dati POST del form
     * @return array Risultato della validazione
     */
    public function validate_and_save_messaging_settings($post_data) {
        $errors = array();
        $success_count = 0;

        // Valida oggetto email
        if (empty($post_data['disco747_email_subject'])) {
            $errors[] = __('L\'oggetto dell\'email è obbligatorio.', 'disco747');
        } else {
            update_option('disco747_email_subject', $this->sanitize_text_field($post_data['disco747_email_subject']));
            $success_count++;
        }

        // Valida template email
        if (!empty($post_data['disco747_email_template'])) {
            update_option('disco747_email_template', $this->sanitize_html_template($post_data['disco747_email_template']));
            $success_count++;
        }

        // Valida template WhatsApp
        if (!empty($post_data['disco747_whatsapp_template'])) {
            update_option('disco747_whatsapp_template', $this->sanitize_textarea_field($post_data['disco747_whatsapp_template']));
            $success_count++;
        }

        // Valida modalità invio predefinita
        if (!empty($post_data['disco747_default_send_mode'])) {
            update_option('disco747_default_send_mode', $this->sanitize_send_mode($post_data['disco747_default_send_mode']));
            $success_count++;
        }

        if (!empty($errors)) {
            return array(
                'success' => false,
                'message' => implode(' ', $errors)
            );
        }

        return array(
            'success' => true,
            'message' => sprintf(__('%d impostazioni di messaggistica salvate.', 'disco747'), $success_count)
        );
    }

    /**
     * Salva il tipo di storage
     *
     * @since 1.0.0
     * @param array $post_data Dati POST del form
     * @return array Risultato dell'operazione
     */
    public function save_storage_type($post_data) {
        if (empty($post_data['storage_type'])) {
            return array(
                'success' => false,
                'message' => __('Tipo di storage non specificato.', 'disco747')
            );
        }

        $storage_type = $this->sanitize_storage_type($post_data['storage_type']);
        $old_type = get_option('disco747_storage_type', 'dropbox');

        update_option('disco747_storage_type', $storage_type);

        return array(
            'success' => true,
            'message' => sprintf(
                __('Tipo di storage cambiato da %s a %s.', 'disco747'),
                self::$storage_types[$old_type],
                self::$storage_types[$storage_type]
            )
        );
    }

    /**
     * Salva le credenziali Dropbox
     *
     * @since 1.0.0
     * @param array $post_data Dati POST del form
     * @return array Risultato dell'operazione
     */
    public function save_dropbox_credentials($post_data) {
        $required_fields = array('app_key', 'app_secret', 'redirect_uri');
        $errors = array();

        foreach ($required_fields as $field) {
            if (empty($post_data["dropbox_{$field}"])) {
                $errors[] = sprintf(__('Campo %s obbligatorio.', 'disco747'), $field);
            } else {
                update_option("disco747_dropbox_{$field}", $this->sanitize_credential($post_data["dropbox_{$field}"]));
            }
        }

        // Refresh token è opzionale ma se presente lo salviamo
        if (!empty($post_data['dropbox_refresh_token'])) {
            update_option('disco747_dropbox_refresh_token', $this->sanitize_credential($post_data['dropbox_refresh_token']));
        }

        if (!empty($errors)) {
            return array(
                'success' => false,
                'message' => implode(' ', $errors)
            );
        }

        return array(
            'success' => true,
            'message' => __('Credenziali Dropbox salvate con successo.', 'disco747')
        );
    }

    /**
     * Salva le credenziali Google Drive
     *
     * @since 1.0.0
     * @param array $post_data Dati POST del form
     * @return array Risultato dell'operazione
     */
    public function save_googledrive_credentials($post_data) {
        $required_fields = array('client_id', 'client_secret', 'redirect_uri');
        $errors = array();

        foreach ($required_fields as $field) {
            if (empty($post_data["googledrive_{$field}"])) {
                $errors[] = sprintf(__('Campo %s obbligatorio.', 'disco747'), $field);
            } else {
                update_option("disco747_googledrive_{$field}", $this->sanitize_credential($post_data["googledrive_{$field}"]));
            }
        }

        // Campi opzionali
        $optional_fields = array('refresh_token', 'folder_id');
        foreach ($optional_fields as $field) {
            if (!empty($post_data["googledrive_{$field}"])) {
                update_option("disco747_googledrive_{$field}", $this->sanitize_credential($post_data["googledrive_{$field}"]));
            }
        }

        if (!empty($errors)) {
            return array(
                'success' => false,
                'message' => implode(' ', $errors)
            );
        }

        return array(
            'success' => true,
            'message' => __('Credenziali Google Drive salvate con successo.', 'disco747')
        );
    }

    // ============================================================================
    // GESTIONE STORAGE E CONNESSIONI
    // ============================================================================

    /**
     * Testa la connessione storage
     *
     * @since 1.0.0
     * @return array Risultato del test
     */
    public function test_storage_connection() {
        try {
            $storage_manager = new Disco747_Storage_Manager();
            $result = $storage_manager->test_oauth_connection();
            
            if ($result['success']) {
                return array(
                    'success' => true,
                    'message' => sprintf(
                        __('Connessione %s riuscita. Utente: %s', 'disco747'),
                        $storage_manager->get_storage_type(),
                        $result['user_name'] ?? 'N/D'
                    )
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Test connessione fallito: ', 'disco747') . $result['message']
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('Errore durante il test: ', 'disco747') . $e->getMessage()
            );
        }
    }

    /**
     * Genera URL di autorizzazione OAuth
     *
     * @since 1.0.0
     * @return array Risultato della generazione
     */
    public function generate_auth_url() {
        try {
            $storage_manager = new Disco747_Storage_Manager();
            $result = $storage_manager->generate_auth_url();
            
            if ($result['success']) {
                return array(
                    'success' => true,
                    'message' => __('URL di autorizzazione generato.', 'disco747'),
                    'auth_url' => $result['auth_url']
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $result['message']
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('Errore generazione URL: ', 'disco747') . $e->getMessage()
            );
        }
    }

    /**
     * Scambia il codice di autorizzazione con i token
     *
     * @since 1.0.0
     * @param array $post_data Dati POST contenenti il codice
     * @return array Risultato dello scambio
     */
    public function exchange_auth_code($post_data) {
        if (empty($post_data['auth_code'])) {
            return array(
                'success' => false,
                'message' => __('Codice di autorizzazione mancante.', 'disco747')
            );
        }

        try {
            $storage_manager = new Disco747_Storage_Manager();
            $result = $storage_manager->exchange_code_for_tokens(
                $post_data['auth_code'],
                $post_data['state'] ?? null
            );
            
            return $result;
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => __('Errore scambio codice: ', 'disco747') . $e->getMessage()
            );
        }
    }

    // ============================================================================
    // GESTIONE IMPORT/EXPORT CONFIGURAZIONI
    // ============================================================================

    /**
     * Esporta tutte le configurazioni in formato JSON
     *
     * @since 1.0.0
     * @param bool $include_sensitive Include credenziali sensibili
     * @return string JSON delle configurazioni
     */
    public function export_configuration($include_sensitive = false) {
        $config = array(
            'version' => Disco747_Config::VERSION,
            'export_date' => current_time('mysql'),
            'settings' => array()
        );

        // Esporta impostazioni generali
        $general_settings = array(
            'company_name',
            'company_email', 
            'company_phone',
            'debug_mode',
            'storage_type',
            'email_subject',
            'email_template',
            'whatsapp_template',
            'default_send_mode',
            'session_timeout',
            'max_login_attempts'
        );

        foreach ($general_settings as $setting) {
            $config['settings'][$setting] = get_option("disco747_{$setting}", $this->get_default($setting));
        }

        // Include credenziali solo se richiesto
        if ($include_sensitive) {
            $sensitive_settings = array(
                'dropbox_app_key',
                'dropbox_app_secret', 
                'dropbox_redirect_uri',
                'dropbox_refresh_token',
                'googledrive_client_id',
                'googledrive_client_secret',
                'googledrive_redirect_uri',
                'googledrive_refresh_token',
                'googledrive_folder_id'
            );

            foreach ($sensitive_settings as $setting) {
                $value = get_option("disco747_{$setting}", '');
                if (!empty($value)) {
                    $config['sensitive'][$setting] = $value;
                }
            }
        }

        return json_encode($config, JSON_PRETTY_PRINT);
    }

    /**
     * Importa configurazioni da JSON
     *
     * @since 1.0.0
     * @param string $json_data Dati JSON da importare
     * @param bool $overwrite Sovrascrive impostazioni esistenti
     * @return array Risultato dell'importazione
     */
    public function import_configuration($json_data, $overwrite = false) {
        $config = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => __('File JSON non valido.', 'disco747')
            );
        }

        if (!isset($config['settings']) || !is_array($config['settings'])) {
            return array(
                'success' => false,
                'message' => __('Formato configurazione non riconosciuto.', 'disco747')
            );
        }

        $imported = 0;
        $skipped = 0;

        foreach ($config['settings'] as $key => $value) {
            $option_name = "disco747_{$key}";
            
            // Controlla se l'opzione esiste già
            if (!$overwrite && get_option($option_name) !== false) {
                $skipped++;
                continue;
            }

            // Sanitizza il valore in base al tipo
            $sanitized_value = $this->sanitize_imported_value($key, $value);
            update_option($option_name, $sanitized_value);
            $imported++;
        }

        // Importa credenziali sensibili se presenti
        if (isset($config['sensitive']) && is_array($config['sensitive'])) {
            foreach ($config['sensitive'] as $key => $value) {
                $option_name = "disco747_{$key}";
                if ($overwrite || get_option($option_name) === false || get_option($option_name) === '') {
                    update_option($option_name, $this->sanitize_credential($value));
                    $imported++;
                }
            }
        }

        return array(
            'success' => true,
            'message' => sprintf(
                __('Importazione completata. %d impostazioni importate, %d saltate.', 'disco747'),
                $imported,
                $skipped
            )
        );
    }

    /**
     * Sanitizza un valore importato in base al tipo
     *
     * @since 1.0.0
     * @param string $key Chiave dell'impostazione
     * @param mixed $value Valore da sanitizzare
     * @return mixed Valore sanitizzato
     */
    private function sanitize_imported_value($key, $value) {
        switch ($key) {
            case 'company_email':
                return $this->sanitize_email($value);
                
            case 'debug_mode':
                return $this->sanitize_checkbox($value);
                
            case 'session_timeout':
            case 'max_login_attempts':
                return $this->sanitize_positive_integer($value);
                
            case 'storage_type':
                return $this->sanitize_storage_type($value);
                
            case 'default_send_mode':
                return $this->sanitize_send_mode($value);
                
            case 'email_template':
                return $this->sanitize_html_template($value);
                
            case 'whatsapp_template':
                return $this->sanitize_textarea_field($value);
                
            default:
                return $this->sanitize_text_field($value);
        }
    }

    // ============================================================================
    // INFORMAZIONI DI SISTEMA E COMPATIBILITÀ
    // ============================================================================

    /**
     * Verifica se il sistema è configurato correttamente
     *
     * @since 1.0.0
     * @return bool True se configurato
     */
    public function is_system_configured() {
        $required_settings = array(
            'disco747_company_name',
            'disco747_company_email',
            'disco747_storage_type'
        );

        foreach ($required_settings as $setting) {
            if (empty(get_option($setting))) {
                return false;
            }
        }

        // Verifica credenziali storage
        $storage_type = get_option('disco747_storage_type', 'dropbox');
        
        if ($storage_type === 'dropbox') {
            $required_credentials = array(
                'disco747_dropbox_app_key',
                'disco747_dropbox_app_secret'
            );
        } else {
            $required_credentials = array(
                'disco747_googledrive_client_id',
                'disco747_googledrive_client_secret'
            );
        }

        foreach ($required_credentials as $credential) {
            if (empty(get_option($credential))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ottiene informazioni dettagliate del sistema
     *
     * @since 1.0.0
     * @return array Informazioni di sistema
     */
    public function get_system_info() {
        global $wpdb;

        return array(
            'wordpress' => array(
                'version' => get_bloginfo('version'),
                'multisite' => is_multisite(),
                'language' => get_locale(),
                'timezone' => get_option('timezone_string'),
                'date_format' => get_option('date_format'),
                'time_format' => get_option('time_format')
            ),
            'plugin' => array(
                'version' => Disco747_Config::VERSION,
                'database_version' => get_option('disco747_database_version', '1.0.0'),
                'debug_mode' => get_option('disco747_debug_mode', false),
                'storage_type' => get_option('disco747_storage_type', 'dropbox'),
                'configured' => $this->is_system_configured()
            ),
            'server' => array(
                'php_version' => phpversion(),
                'mysql_version' => $wpdb->db_version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/D',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_input_vars' => ini_get('max_input_vars')
            ),
            'extensions' => array(
                'curl' => extension_loaded('curl'),
                'gd' => extension_loaded('gd'),
                'mbstring' => extension_loaded('mbstring'),
                'zip' => extension_loaded('zip'),
                'xml' => extension_loaded('xml'),
                'json' => extension_loaded('json'),
                'openssl' => extension_loaded('openssl')
            ),
            'libraries' => array(
                'phpspreadsheet' => class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet'),
                'mpdf' => class_exists('Mpdf\\Mpdf'),
                'dompdf' => class_exists('Dompdf\\Dompdf')
            )
        );
    }

    /**
     * Verifica la compatibilità del sistema
     *
     * @since 1.0.0
     * @return array Risultati dei controlli di compatibilità
     */
    public function check_system_compatibility() {
        $requirements = array(
            'php_version' => '7.4.0',
            'wordpress_version' => '5.0',
            'mysql_version' => '5.6',
            'memory_limit' => 256 * 1024 * 1024, // 256MB
            'required_extensions' => array('curl', 'mbstring', 'zip', 'xml', 'json')
        );

        $checks = array();
        $system_info = $this->get_system_info();

        // Controllo versione PHP
        $checks['php_version'] = array(
            'required' => $requirements['php_version'],
            'current' => $system_info['server']['php_version'],
            'status' => version_compare($system_info['server']['php_version'], $requirements['php_version'], '>='),
            'message' => version_compare($system_info['server']['php_version'], $requirements['php_version'], '>=') 
                ? __('Versione PHP compatibile', 'disco747')
                : sprintf(__('PHP %s o superiore richiesto', 'disco747'), $requirements['php_version'])
        );

        // Controllo versione WordPress
        $checks['wordpress_version'] = array(
            'required' => $requirements['wordpress_version'],
            'current' => $system_info['wordpress']['version'],
            'status' => version_compare($system_info['wordpress']['version'], $requirements['wordpress_version'], '>='),
            'message' => version_compare($system_info['wordpress']['version'], $requirements['wordpress_version'], '>=')
                ? __('Versione WordPress compatibile', 'disco747')
                : sprintf(__('WordPress %s o superiore richiesto', 'disco747'), $requirements['wordpress_version'])
        );

        // Controllo estensioni PHP
        foreach ($requirements['required_extensions'] as $extension) {
            $checks["extension_{$extension}"] = array(
                'required' => true,
                'current' => $system_info['extensions'][$extension] ?? false,
                'status' => $system_info['extensions'][$extension] ?? false,
                'message' => ($system_info['extensions'][$extension] ?? false)
                    ? sprintf(__('Estensione %s disponibile', 'disco747'), $extension)
                    : sprintf(__('Estensione %s mancante', 'disco747'), $extension)
            );
        }

        // Controllo memoria
        $memory_limit = $this->parse_memory_limit($system_info['server']['memory_limit']);
        $checks['memory_limit'] = array(
            'required' => $this->format_bytes($requirements['memory_limit']),
            'current' => $system_info['server']['memory_limit'],
            'status' => $memory_limit >= $requirements['memory_limit'],
            'message' => $memory_limit >= $requirements['memory_limit']
                ? __('Memoria sufficiente', 'disco747')
                : sprintf(__('Almeno %s di memoria richiesti', 'disco747'), $this->format_bytes($requirements['memory_limit']))
        );

        return $checks;
    }

    /**
     * Converte il valore memory_limit in bytes
     *
     * @since 1.0.0
     * @param string $memory_limit Valore memory_limit
     * @return int Bytes
     */
    private function parse_memory_limit($memory_limit) {
        if ($memory_limit === '-1') {
            return PHP_INT_MAX;
        }

        $value = intval($memory_limit);
        $unit = strtoupper(substr($memory_limit, -1));

        switch ($unit) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return $value;
        }
    }

    /**
     * Formatta bytes in formato leggibile
     *
     * @since 1.0.0
     * @param int $bytes Numero di bytes
     * @param int $precision Precisione decimale
     * @return string Formato leggibile
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // ============================================================================
    // TEMPLATE PREDEFINITI
    // ============================================================================

    /**
     * Ottiene il template email predefinito
     *
     * @since 1.0.0
     * @return string Template HTML email
     */
    private function get_default_email_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;">
            <div style="background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%); padding: 30px; border-radius: 15px; text-align: center; color: white;">
                <h1 style="margin: 0; font-size: 28px;">747 DISCO</h1>
                <p style="margin: 10px 0 0 0; font-size: 16px;">Il tuo preventivo è pronto!</p>
            </div>
            
            <div style="background: white; padding: 30px; border-radius: 15px; margin-top: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                <h2 style="color: #2b1e1a; margin-top: 0;">Ciao {{nome_referente}}!</h2>
                
                <p>Grazie per averci contattato per il tuo evento <strong>{{tipo_evento}}</strong>.</p>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;">
                    <h3 style="color: #c28a4d; margin-top: 0;">📋 Dettagli Evento</h3>
                    <p><strong>📅 Data:</strong> {{data_evento}}</p>
                    <p><strong>🎉 Evento:</strong> {{tipo_evento}}</p>
                    <p><strong>👥 Ospiti:</strong> {{numero_invitati}}</p>
                    <p><strong>🍽️ Menu:</strong> {{tipo_menu}}</p>
                    <p><strong>⏰ Orario:</strong> {{orario}}</p>
                    <p><strong>💰 Importo:</strong> {{importo}}</p>
                    <p><strong>💳 Acconto:</strong> {{acconto}}</p>
                </div>
                
                <p><strong>📎 In allegato trovi il preventivo completo in formato PDF.</strong></p>
                
                <p>Ti contatteremo a breve per finalizzare tutti i dettagli!</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="https://gestionale.747disco.it" style="background: #c28a4d; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: 600;">Visita il nostro sito</a>
                </div>
                
                <p style="text-align: center; color: #666; font-size: 14px;">
                    747 Disco | Via della Musica 1, Roma | Tel: 06 123456789
                </p>
            </div>
        </div>';
    }

    /**
     * Ottiene il template WhatsApp predefinito
     *
     * @since 1.0.0
     * @return string Template WhatsApp
     */
    private function get_default_whatsapp_template() {
        return "🎉 Ciao {{nome_referente}}!\n\nIl preventivo per il tuo {{tipo_evento}} del {{data_evento}} è pronto!\n\n📋 DETTAGLI:\n👥 Ospiti: {{numero_invitati}}\n🍽️ Menu: {{tipo_menu}}\n💰 Importo: {{importo}}\n\nTi abbiamo inviato tutti i dettagli via email con il PDF allegato. Ti contatteremo presto per finalizzare tutto!\n\n✨ 747 DISCO - Dove ogni evento diventa magico!";
    }

    /**
     * Ottiene i segnaposto disponibili per i template
     *
     * @since 1.0.0
     * @return array Lista dei segnaposto con descrizioni
     */
    public function get_template_placeholders() {
        return array(
            '{{nome_referente}}' => __('Nome del referente', 'disco747'),
            '{{cognome_referente}}' => __('Cognome del referente', 'disco747'),
            '{{nome_completo}}' => __('Nome e cognome completo', 'disco747'),
            '{{email}}' => __('Email del referente', 'disco747'),
            '{{telefono}}' => __('Numero di telefono', 'disco747'),
            '{{data_evento}}' => __('Data dell\'evento (formato italiano)', 'disco747'),
            '{{tipo_evento}}' => __('Tipologia di evento', 'disco747'),
            '{{numero_invitati}}' => __('Numero di invitati', 'disco747'),
            '{{tipo_menu}}' => __('Tipo di menu scelto', 'disco747'),
            '{{orario}}' => __('Orario evento (inizio - fine)', 'disco747'),
            '{{importo}}' => __('Importo totale formattato', 'disco747'),
            '{{acconto}}' => __('Importo acconto richiesto', 'disco747'),
            '{{preventivo_id}}' => __('ID univoco del preventivo', 'disco747'),
            '{{data_oggi}}' => __('Data odierna (formato italiano)', 'disco747'),
            '{{omaggi}}' => __('Lista degli omaggi inclusi', 'disco747')
        );
    }

    /**
     * Ottiene le opzioni disponibili per le select
     *
     * @since 1.0.0
     * @return array Array delle opzioni
     */
    public function get_select_options() {
        return array(
            'send_modes' => self::$send_modes,
            'storage_types' => self::$storage_types
        );
    }

    // ============================================================================
    // UTILITY STATICHE
    // ============================================================================

    /**
     * Converte array in opzioni HTML per select
     *
     * @since 1.0.0
     * @param array $options Array delle opzioni
     * @param string $selected Valore selezionato
     * @return string HTML delle opzioni
     */
    public static function array_to_select_options($options, $selected = '') {
        $html = '';
        foreach ($options as $value => $label) {
            $selected_attr = selected($selected, $value, false);
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                $selected_attr,
                esc_html($label)
            );
        }
        return $html;
    }

    /**
     * Genera un nonce per le operazioni di sicurezza
     *
     * @since 1.0.0
     * @param string $action Azione per cui generare il nonce
     * @return string Nonce generato
     */
    public static function generate_nonce($action = 'disco747_settings') {
        return wp_create_nonce($action);
    }

    /**
     * Verifica un nonce
     *
     * @since 1.0.0
     * @param string $nonce Nonce da verificare
     * @param string $action Azione associata al nonce
     * @return bool True se valido
     */
    public static function verify_nonce($nonce, $action = 'disco747_settings') {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Logging sicuro per debug
     *
     * @since 1.0.0
     * @param string $message Messaggio da loggare
     * @param string $level Livello di log (info, warning, error)
     */
    public static function log($message, $level = 'info') {
        if (get_option('disco747_debug_mode', false)) {
            $timestamp = current_time('Y-m-d H:i:s');
            $log_entry = sprintf('[%s] [%s] %s', $timestamp, strtoupper($level), $message);
            error_log('DISCO747_CRM: ' . $log_entry);
        }
    }

    /**
     * Ottiene lo stato di salute del sistema
     *
     * @since 1.0.0
     * @return array Stato di salute con punteggio
     */
    public function get_system_health() {
        $health = array(
            'score' => 0,
            'max_score' => 100,
            'status' => 'unknown',
            'issues' => array(),
            'recommendations' => array()
        );

        $compatibility = $this->check_system_compatibility();
        $configured = $this->is_system_configured();

        // Punteggi per i vari controlli
        $score_weights = array(
            'php_version' => 20,
            'wordpress_version' => 15,
            'memory_limit' => 15,
            'required_extensions' => 25,
            'configuration' => 25
        );

        // Calcola punteggio compatibilità
        foreach ($compatibility as $check_name => $check) {
            if (strpos($check_name, 'extension_') === 0) {
                if ($check['status']) {
                    $health['score'] += $score_weights['required_extensions'] / 5; // 5 estensioni richieste
                } else {
                    $health['issues'][] = $check['message'];
                }
            } else {
                $weight_key = str_replace('_', '_', $check_name);
                if (isset($score_weights[$weight_key]) && $check['status']) {
                    $health['score'] += $score_weights[$weight_key];
                } elseif (isset($score_weights[$weight_key])) {
                    $health['issues'][] = $check['message'];
                }
            }
        }

        // Calcola punteggio configurazione
        if ($configured) {
            $health['score'] += $score_weights['configuration'];
        } else {
            $health['issues'][] = __('Sistema non completamente configurato', 'disco747');
            $health['recommendations'][] = __('Completa la configurazione delle credenziali storage', 'disco747');
        }

        // Determina stato generale
        if ($health['score'] >= 90) {
            $health['status'] = 'excellent';
        } elseif ($health['score'] >= 70) {
            $health['status'] = 'good';
        } elseif ($health['score'] >= 50) {
            $health['status'] = 'fair';
        } else {
            $health['status'] = 'poor';
        }

        // Aggiungi raccomandazioni generali
        if ($health['score'] < 100) {
            if (!$configured) {
                $health['recommendations'][] = __('Configura le credenziali di storage per abilitare tutte le funzionalità', 'disco747');
            }
            
            if (count($health['issues']) > 3) {
                $health['recommendations'][] = __('Contatta il tuo provider hosting per risolvere i problemi di compatibilità', 'disco747');
            }
        }

        return $health;
    }

    /**
     * Ottiene statistiche di utilizzo del plugin
     *
     * @since 1.0.0
     * @return array Statistiche di utilizzo
     */
    public function get_usage_statistics() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'disco747_preventivi';
        
        // Verifica se la tabella esiste
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return array(
                'error' => __('Tabella preventivi non trovata', 'disco747')
            );
        }

        $stats = array();

        // Totale preventivi
        $stats['total_preventivi'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Preventivi per stato
        $stati_query = "SELECT stato, COUNT(*) as count FROM $table_name GROUP BY stato";
        $stati_results = $wpdb->get_results($stati_query);
        $stats['per_stato'] = array();
        foreach ($stati_results as $row) {
            $stats['per_stato'][$row->stato] = $row->count;
        }

        // Preventivi degli ultimi 30 giorni
        $stats['ultimi_30_giorni'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE data_creazione >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));

        // Preventivi per mese (ultimi 12 mesi)
        $monthly_query = "
            SELECT 
                DATE_FORMAT(data_creazione, '%Y-%m') as mese, 
                COUNT(*) as count 
            FROM $table_name 
            WHERE data_creazione >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(data_creazione, '%Y-%m')
            ORDER BY mese ASC
        ";
        $monthly_results = $wpdb->get_results($monthly_query);
        $stats['per_mese'] = array();
        foreach ($monthly_results as $row) {
            $stats['per_mese'][$row->mese] = $row->count;
        }

        // Importo totale preventivi
        $stats['importo_totale'] = $wpdb->get_var("SELECT SUM(importo_preventivo) FROM $table_name");

        // Media importo preventivi
        $stats['importo_medio'] = $wpdb->get_var("SELECT AVG(importo_preventivo) FROM $table_name");

        // Tipo menu più richiesto
        $menu_query = "SELECT tipo_menu, COUNT(*) as count FROM $table_name GROUP BY tipo_menu ORDER BY count DESC LIMIT 1";
        $menu_result = $wpdb->get_row($menu_query);
        $stats['menu_piu_richiesto'] = $menu_result ? $menu_result->tipo_menu : 'N/D';

        return $stats;
    }

    /**
     * Pulisce dati obsoleti e cache
     *
     * @since 1.0.0
     * @return array Risultato della pulizia
     */
    public function cleanup_system() {
        global $wpdb;

        $cleaned = array(
            'transients' => 0,
            'temp_files' => 0,
            'old_logs' => 0
        );

        // Pulisci transient scaduti del plugin
        $transients_deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_disco747_%' 
             OR option_name LIKE '_transient_timeout_disco747_%'"
        );
        $cleaned['transients'] = $transients_deleted;

        // Pulisci file temporanei
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/disco747_temp/';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < strtotime('-1 day')) {
                    unlink($file);
                    $cleaned['temp_files']++;
                }
            }
        }

        // Pulisci log vecchi (se debug attivo)
        if (get_option('disco747_debug_mode')) {
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($log_file) && filesize($log_file) > 10 * 1024 * 1024) { // 10MB
                // Mantieni solo le ultime 1000 righe
                $lines = file($log_file);
                if (count($lines) > 1000) {
                    $new_content = implode('', array_slice($lines, -1000));
                    file_put_contents($log_file, $new_content);
                    $cleaned['old_logs'] = count($lines) - 1000;
                }
            }
        }

        self::log('Pulizia sistema completata: ' . json_encode($cleaned));

        return $cleaned;
    }

    /**
     * Verifica la presenza di aggiornamenti
     *
     * @since 1.0.0
     * @return array Informazioni sugli aggiornamenti
     */
    public function check_for_updates() {
        $current_version = Disco747_Config::VERSION;
        $last_check = get_option('disco747_last_update_check', 0);
        
        // Controlla massimo una volta al giorno
        if ((time() - $last_check) < DAY_IN_SECONDS) {
            return array(
                'checked' => false,
                'message' => __('Controllo aggiornamenti già effettuato oggi', 'disco747')
            );
        }

        // Simula controllo aggiornamenti (implementazione futura)
        update_option('disco747_last_update_check', time());
        
        return array(
            'checked' => true,
            'current_version' => $current_version,
            'latest_version' => $current_version,
            'update_available' => false,
            'message' => __('Nessun aggiornamento disponibile', 'disco747')
        );
    }
}