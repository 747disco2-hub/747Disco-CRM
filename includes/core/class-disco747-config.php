<?php
/**
 * Classe per gestione configurazione del plugin 747 Disco CRM
 * 
 * @package    Disco747_CRM
 * @subpackage Core
 * @since      11.4
 * @version    11.4
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Core;

use Exception;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_Config
 * 
 * Gestisce tutte le configurazioni del plugin utilizzando il pattern Singleton
 * per garantire una singola istanza globale della configurazione.
 * 
 * @since 11.4
 */
class Disco747_Config {

    /**
     * Versione del plugin
     * 
     * @var string
     */
    const VERSION = '11.4';

    /**
     * Istanza singleton
     * 
     * @var Disco747_Config
     */
    private static $instance = null;

    /**
     * Prefisso per le opzioni nel database
     * 
     * @var string
     */
    private $option_prefix = 'disco747_crm_';

    /**
     * Cache delle configurazioni caricate
     * 
     * @var array
     */
    private $config_cache = array();

    /**
     * Flag per indicare se la configurazione è stata caricata
     * 
     * @var bool
     */
    private $config_loaded = false;

    /**
     * Configurazioni di default
     * 
     * @var array
     */
    private $default_config = array(
        // === PLUGIN INFO ===
        'plugin_version' => self::VERSION,
        'plugin_name' => '747 Disco CRM',
        'plugin_slug' => 'disco747-crm',
        
        // === COMPANY INFO ===
        'company_name' => '747 Disco',
        'company_email' => 'info@gestionale.747disco.it',
        'company_phone' => '06 123456789',
        'company_website' => 'https://gestionale.747disco.it',
        'company_address' => 'Via Example 123, Roma',
        'company_vat' => 'IT12345678901',

        // === SYSTEM SETTINGS ===
        'debug_mode' => false,
        'maintenance_mode' => false,
        'enable_logs' => true,
        'timezone' => 'Europe/Rome',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',

        // === STORAGE SETTINGS ===
        'storage_type' => 'dropbox', // dropbox, googledrive
        'enable_backup' => true,
        'backup_retention_days' => 30,
        'max_file_size' => 10485760, // 10MB
        'allowed_file_types' => array('pdf', 'xlsx', 'csv'),

        // === EMAIL SETTINGS ===
        'email_from_name' => '747 Disco',
        'email_from_address' => 'no-reply@gestionale.747disco.it',
        'email_subject' => 'Il tuo preventivo è pronto - {{nome_referente}}',
        'email_template' => 'Ciao {{nome_referente}}, il tuo preventivo per il {{data_evento}} è pronto!',
        'smtp_enabled' => false,
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',

        // === WHATSAPP SETTINGS ===
        'whatsapp_enabled' => true,
        'whatsapp_token' => '',
        'whatsapp_phone' => '',
        'whatsapp_template' => 'Ciao {{nome_referente}}, il tuo preventivo per il {{data_evento}} è pronto!',

        // === SECURITY SETTINGS ===
        'session_timeout' => 3600,
        'max_login_attempts' => 5,
        'lockout_duration' => 900,
        'require_ssl' => false,
        'ip_whitelist' => array(),
        'enable_2fa' => false,

        // === PERFORMANCE SETTINGS ===
        'cache_enabled' => true,
        'cache_duration' => 3600,
        'optimize_images' => true,
        'minify_output' => false,
        'enable_cdn' => false,

        // === LOGGING SETTINGS ===
        'log_level' => 'INFO',
        'log_retention_days' => 30,
        'log_file_max_size' => '10MB',
        'error_reporting' => true,

        // === API SETTINGS ===
        'api_enabled' => false,
        'api_rate_limit' => 100,
        'api_key_expiry' => 86400,

        // === PATHS & URLS ===
        'upload_path' => '',
        'template_path' => '',
        'log_path' => '',
        'cache_path' => '',
    );

    /**
     * Costruttore privato per il pattern Singleton
     *
     * @since 11.4
     */
    private function __construct() {
        $this->init_config();
    }

    /**
     * Ottiene l'istanza singleton
     *
     * @return Disco747_Config Istanza singleton
     * @since 11.4
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Previene la clonazione dell'istanza
     *
     * @since 11.4
     */
    private function __clone() {}

    /**
     * Previene la deserializzazione dell'istanza
     *
     * @since 11.4
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Inizializza la configurazione
     *
     * @since 11.4
     */
    private function init_config() {
        $this->setup_paths();
        $this->load_config();
        $this->migrate_old_settings();
    }

    /**
     * Configura i percorsi predefiniti
     *
     * @since 11.4
     */
    private function setup_paths() {
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/747disco-crm/';

        $this->default_config['upload_path'] = $base_path;
        $this->default_config['template_path'] = $base_path . 'templates/';
        $this->default_config['log_path'] = $base_path . 'logs/';
        $this->default_config['cache_path'] = $base_path . 'cache/';
    }

    /**
     * Carica le configurazioni dal database
     *
     * @since 11.4
     */
    public function load_config() {
        if ($this->config_loaded) {
            return;
        }

        // Carica configurazione principale
        $saved_config = get_option($this->option_prefix . 'config', array());
        
        // Merge con i default
        $this->config_cache = array_merge($this->default_config, $saved_config);
        
        // Carica configurazioni storage separate (per compatibilità)
        $this->load_storage_config();
        
        $this->config_loaded = true;
        $this->log('Configurazione caricata con successo');
    }

    /**
     * Carica configurazioni storage dal sistema precedente
     *
     * @since 11.4
     */
    private function load_storage_config() {
        // Dropbox
        $dropbox_config = array(
            'dropbox_app_key' => get_option('preventivi_dropbox_app_key', ''),
            'dropbox_app_secret' => get_option('preventivi_dropbox_app_secret', ''),
            'dropbox_redirect_uri' => get_option('preventivi_dropbox_redirect_uri', ''),
            'dropbox_refresh_token' => get_option('preventivi_dropbox_refresh_token', ''),
        );

        // Google Drive
        $googledrive_config = array(
            'googledrive_client_id' => get_option('preventivi_googledrive_client_id', ''),
            'googledrive_client_secret' => get_option('preventivi_googledrive_client_secret', ''),
            'googledrive_redirect_uri' => get_option('preventivi_googledrive_redirect_uri', ''),
            'googledrive_refresh_token' => get_option('preventivi_googledrive_refresh_token', ''),
            'googledrive_folder_id' => get_option('preventivi_googledrive_folder_id', ''),
        );

        // WhatsApp
        $whatsapp_config = array(
            'whatsapp_token' => get_option('preventivi_whatsapp_token', ''),
            'whatsapp_phone' => get_option('preventivi_whatsapp_phone', ''),
        );

        // Merge nelle configurazioni
        $this->config_cache = array_merge($this->config_cache, $dropbox_config, $googledrive_config, $whatsapp_config);
    }

    /**
     * Migra impostazioni dal sistema precedente
     *
     * @since 11.4
     */
    private function migrate_old_settings() {
        $migration_done = get_option($this->option_prefix . 'migration_done', false);
        
        if ($migration_done) {
            return;
        }

        // Mappatura vecchie opzioni -> nuove opzioni
        $migration_map = array(
            'preventivi_party_company_name' => 'company_name',
            'preventivi_party_company_email' => 'company_email',
            'preventivi_party_debug_mode' => 'debug_mode',
            'preventivi_party_storage_type' => 'storage_type',
        );

        foreach ($migration_map as $old_key => $new_key) {
            $old_value = get_option($old_key);
            if ($old_value !== false && !isset($this->config_cache[$new_key])) {
                $this->config_cache[$new_key] = $old_value;
            }
        }

        // Salva configurazione migrata
        $this->save_config();
        
        // Marca migrazione come completata
        update_option($this->option_prefix . 'migration_done', true);
        
        $this->log('Migrazione impostazioni completata');
    }

    /**
     * Ottiene un valore di configurazione
     *
     * @param string $key Chiave della configurazione
     * @param mixed $default Valore di default se non trovato
     * @return mixed Valore della configurazione
     * @since 11.4
     */
    public function get($key, $default = null) {
        if (!$this->config_loaded) {
            $this->load_config();
        }

        if (isset($this->config_cache[$key])) {
            return $this->config_cache[$key];
        }

        return $default !== null ? $default : (isset($this->default_config[$key]) ? $this->default_config[$key] : null);
    }

    /**
     * Imposta un valore di configurazione
     *
     * @param string $key Chiave della configurazione
     * @param mixed $value Valore da impostare
     * @return bool True se salvato con successo
     * @since 11.4
     */
    public function set($key, $value) {
        $this->config_cache[$key] = $value;
        return $this->save_config();
    }

    /**
     * Imposta multipli valori di configurazione
     *
     * @param array $values Array chiave => valore
     * @return bool True se salvato con successo
     * @since 11.4
     */
    public function set_multiple($values) {
        foreach ($values as $key => $value) {
            $this->config_cache[$key] = $value;
        }
        return $this->save_config();
    }

    /**
     * Salva la configurazione nel database
     *
     * @return bool True se salvato con successo
     * @since 11.4
     */
    private function save_config() {
        return update_option($this->option_prefix . 'config', $this->config_cache);
    }

    /**
     * Verifica se lo storage è configurato
     *
     * @return bool True se configurato
     * @since 11.4
     */
    public function is_storage_configured() {
        $storage_type = $this->get('storage_type');
        
        if (empty($storage_type)) {
            return false;
        }

        if ($storage_type === 'dropbox') {
            return !empty($this->get('dropbox_app_key')) && 
                   !empty($this->get('dropbox_app_secret')) &&
                   !empty($this->get('dropbox_refresh_token'));
        }

        if ($storage_type === 'googledrive') {
            return !empty($this->get('googledrive_client_id')) && 
                   !empty($this->get('googledrive_client_secret')) &&
                   !empty($this->get('googledrive_refresh_token'));
        }

        return false;
    }

    /**
     * Ottiene configurazione storage
     *
     * @param string $storage_type Tipo di storage (dropbox/googledrive)
     * @return array Configurazione storage
     * @since 11.4
     */
    public function get_storage_config($storage_type = null) {
        if (!$storage_type) {
            $storage_type = $this->get('storage_type');
        }

        $config = array();

        if ($storage_type === 'dropbox') {
            $config = array(
                'app_key' => $this->get('dropbox_app_key'),
                'app_secret' => $this->get('dropbox_app_secret'),
                'redirect_uri' => $this->get('dropbox_redirect_uri'),
                'refresh_token' => $this->get('dropbox_refresh_token'),
            );
        } elseif ($storage_type === 'googledrive') {
            $config = array(
                'client_id' => $this->get('googledrive_client_id'),
                'client_secret' => $this->get('googledrive_client_secret'),
                'redirect_uri' => $this->get('googledrive_redirect_uri'),
                'refresh_token' => $this->get('googledrive_refresh_token'),
                'folder_id' => $this->get('googledrive_folder_id'),
            );
        }

        return $config;
    }

    /**
     * Ottiene percorso di upload
     *
     * @param string $type Tipo di percorso (base, template, log, cache)
     * @return string Percorso completo
     * @since 11.4
     */
    public function get_upload_path($type = 'base') {
        $base_path = $this->get('upload_path');
        
        switch ($type) {
            case 'template':
                return $base_path . 'templates/';
            case 'log':
                return $base_path . 'logs/';
            case 'cache':
                return $base_path . 'cache/';
            case 'temp':
                return $base_path . 'temp/';
            case 'backup':
                return $base_path . 'backups/';
            case 'preventivi':
                return $base_path . 'preventivi/';
            default:
                return $base_path;
        }
    }

    /**
     * Ottiene URL di upload
     *
     * @param string $type Tipo di URL
     * @return string URL completo
     * @since 11.4
     */
    public function get_upload_url($type = 'base') {
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'] . '/747disco-crm/';
        
        switch ($type) {
            case 'template':
                return $base_url . 'templates/';
            case 'cache':
                return $base_url . 'cache/';
            case 'upload':
            default:
                return $base_url;
        }
    }

    /**
     * Verifica se il debug è abilitato
     *
     * @return bool True se debug abilitato
     * @since 11.4
     */
    public function is_debug_enabled() {
        return $this->get('debug_mode', false);
    }

    /**
     * Ottiene informazioni di sistema
     *
     * @return array Info di sistema
     * @since 11.4
     */
    public function get_system_info() {
        return array(
            'plugin_version' => $this->get('plugin_version'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'mysql_version' => $this->get_mysql_version(),
            'storage_type' => $this->get('storage_type'),
            'storage_configured' => $this->is_storage_configured(),
            'debug_mode' => $this->is_debug_enabled(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'config_loaded' => $this->config_loaded,
            'timestamp' => current_time('mysql'),
        );
    }

    /**
     * Ottiene versione MySQL
     *
     * @return string Versione MySQL
     * @since 11.4
     */
    private function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var("SELECT VERSION()");
    }

    /**
     * Esporta configurazioni per backup
     *
     * @return array Configurazioni esportabili
     * @since 11.4
     */
    public function export_config() {
        if (!$this->config_loaded) {
            $this->load_config();
        }

        // Rimuovi dati sensibili
        $export = $this->config_cache;
        $sensitive_keys = array(
            'dropbox_app_secret',
            'dropbox_refresh_token',
            'googledrive_client_secret',
            'googledrive_refresh_token',
            'whatsapp_token',
            'smtp_password'
        );

        foreach ($sensitive_keys as $key) {
            if (isset($export[$key])) {
                $export[$key] = '***REDACTED***';
            }
        }

        return $export;
    }

    /**
     * Log centralizzato
     *
     * @param string $message Messaggio da loggare
     * @param string $level Livello di log (INFO, WARNING, ERROR)
     * @since 11.4
     */
    public function log($message, $level = 'INFO') {
        if (!$this->get('enable_logs', true)) {
            return;
        }

        $log_file = $this->get_upload_path('log') . 'disco747-crm-' . date('Y-m-d') . '.log';
        $timestamp = current_time('mysql');
        $log_entry = "[{$timestamp}] [{$level}] {$message}\n";

        // Crea directory log se non esiste
        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Scrivi nel file di log
        error_log($log_entry, 3, $log_file);

        // Se debug mode attivo, logga anche in error_log di PHP
        if ($this->is_debug_enabled()) {
            error_log("[747Disco-CRM] [{$level}] {$message}");
        }
    }

    /**
     * Reset configurazione ai valori di default
     *
     * @param bool $confirm Conferma reset
     * @return bool True se reset eseguito
     * @since 11.4
     */
    public function reset_to_defaults($confirm = false) {
        if (!$confirm) {
            return false;
        }

        $this->config_cache = $this->default_config;
        $this->setup_paths();
        
        return $this->save_config();
    }
}