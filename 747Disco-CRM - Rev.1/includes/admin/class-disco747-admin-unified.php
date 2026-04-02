<?php

namespace Disco747_CRM\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Manager Unificato - Replica esatta dell'interfaccia preventivi-party
 * Basato su preventivi-party-general.php con integrazione nel nuovo sistema 747 Disco CRM
 * 
 * @since 11.4
 */
class Disco747_Admin_Unified {
    
    private $config;
    private $database;
    private $storage_manager;
    private $legacy_storage_manager;
    private $dropbox_handler;
    private $pdf_excel_handler;
    private $googledrive_handler;
    
    private $min_capability = 'manage_options';
    private $hooks_registered = false;
    private $asset_version;
    private $debug_mode = true;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->asset_version = DISCO747_CRM_VERSION;
        $this->load_components();
        $this->log('Admin Unificato inizializzato');
    }
    
    /**
     * Carica componenti dal plugin principale
     */
    private function load_components() {
        $disco747 = disco747_crm();
        
        if ($disco747 && $disco747->is_initialized()) {
            $this->config = $disco747->get_config();
            $this->database = $disco747->get_database();
            
            // Componenti legacy per compatibilità con preventivi-party
            $this->legacy_storage_manager = $disco747->get_legacy_storage_manager();
            $this->dropbox_handler = $disco747->get_dropbox_handler();
            $this->pdf_excel_handler = $disco747->get_pdf_excel_handler();
            $this->googledrive_handler = $disco747->get_googledrive_handler();
            
            // Storage manager nuovo (se disponibile)
            $this->storage_manager = $disco747->get_storage_manager();
        }
    }
    
    /**
     * Inizializza hook WordPress
     */
    public function init_hooks() {
        if ($this->hooks_registered) {
            return;
        }
        
        // Menu principale (identico a preventivi-party)
        add_action('admin_menu', array($this, 'add_unified_admin_menu'));
        
        // Assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_unified_assets'));
        
        // AJAX handlers (compatibilità completa con il vecchio plugin)
        add_action('wp_ajax_disco747_process_quote', array($this, 'handle_process_quote'));
        add_action('wp_ajax_disco747_oauth_action', array($this, 'handle_oauth_action'));
        add_action('wp_ajax_disco747_storage_action', array($this, 'handle_storage_action'));
        add_action('wp_ajax_disco747_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_disco747_generate_auth_url', array($this, 'handle_generate_auth_url'));
        add_action('wp_ajax_disco747_exchange_code', array($this, 'handle_exchange_code'));
        
        // Processing form submissions (come nel vecchio plugin)
        add_action('admin_init', array($this, 'process_admin_forms'));
        
        $this->hooks_registered = true;
    }
    
    /**
     * Aggiunge menu admin unificato (IDENTICO a preventivi-party)
     */
    public function add_unified_admin_menu() {
        if (!current_user_can($this->min_capability)) {
            return;
        }
        
        // Menu principale
        add_menu_page(
            '747 Disco CRM',
            '747 Disco CRM',
            $this->min_capability,
            'disco747-crm',
            array($this, 'render_main_unified_page'),
            $this->get_menu_icon(),
            30
        );
        
        // Sottomenu (identici al vecchio plugin)
        add_submenu_page(
            'disco747-crm',
            'Dashboard',
            'Dashboard', 
            $this->min_capability,
            'disco747-crm',
            array($this, 'render_main_unified_page')
        );
        
        add_submenu_page(
            'disco747-crm',
            'Preventivi',
            'Preventivi',
            $this->min_capability,
            'disco747-preventivi',
            array($this, 'render_preventivi_page')
        );
        
        add_submenu_page(
            'disco747-crm',
            'Storage Cloud',
            'Storage Cloud',
            $this->min_capability,
            'disco747-storage',
            array($this, 'render_storage_page')
        );
        
        add_submenu_page(
            'disco747-crm',
            'Messaggi',
            'Messaggi',
            $this->min_capability,
            'disco747-messages',
            array($this, 'render_messages_page')
        );
        
        add_submenu_page(
            'disco747-crm',
            'Impostazioni',
            'Impostazioni',
            $this->min_capability,
            'disco747-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'disco747-crm',
            'Debug',
            'Debug',
            $this->min_capability,
            'disco747-debug',
            array($this, 'render_debug_page')
        );
    }
    
    /**
     * Ottiene icona menu
     */
    private function get_menu_icon() {
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2"/>
                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2"/>
                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2"/>
            </svg>'
        );
    }
    
    /**
     * Carica assets unificati
     */
    public function enqueue_unified_assets($hook) {
        // Carica solo nelle pagine del plugin
        if (strpos($hook, 'disco747') === false) {
            return;
        }
        
        // CSS unificato (stili identici al vecchio plugin)
        wp_enqueue_style(
            'disco747-unified-css',
            DISCO747_CRM_ASSETS_URL . 'css/unified-admin.css',
            array(),
            $this->asset_version
        );
        
        // JavaScript unificato
        wp_enqueue_script(
            'disco747-unified-js',
            DISCO747_CRM_ASSETS_URL . 'js/unified-admin.js',
            array('jquery'),
            $this->asset_version,
            true
        );
        
        // Localizzazione (identica al vecchio plugin)
        wp_localize_script('disco747-unified-js', 'disco747Admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('disco747_admin_nonce'),
            'messages' => array(
                'confirm_delete' => __('Sei sicuro di voler eliminare questo preventivo?', 'disco747'),
                'processing' => __('Elaborazione in corso...', 'disco747'),
                'error' => __('Si è verificato un errore. Riprova.', 'disco747'),
                'success' => __('Operazione completata con successo.', 'disco747'),
                'oauth_success' => __('OAuth configurato correttamente!', 'disco747'),
                'storage_switched' => __('Storage cambiato con successo!', 'disco747')
            )
        ));
    }
    
    /**
     * Render pagina principale unificata (IDENTICA a preventivi-party-general.php)
     */
    public function render_main_unified_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
        }
        
        // Gestisce login esterno se richiesto
        if (isset($_GET['external_login'])) {
            $this->handle_external_login_form();
            return;
        }
        
        // Dashboard principale con tutte le sezioni
        $stats = $this->get_dashboard_statistics();
        $system_status = $this->get_system_status_summary();
        $recent_preventivi = $this->get_recent_preventivi(5);
        
        include DISCO747_CRM_TEMPLATES_PATH . 'admin/main-unified-page.php';
    }
    
    /**
     * Render pagina preventivi
     */
    public function render_preventivi_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
        }
        
        $preventivi = $this->get_all_preventivi();
        include DISCO747_CRM_TEMPLATES_PATH . 'admin/preventivi-unified-page.php';
    }
    
    /**
     * Render pagina storage (IDENTICA al vecchio plugin)
     */
    public function render_storage_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
        }
        
        $storage_status = $this->get_storage_status();
        $dropbox_config = $this->get_dropbox_config();
        $gdrive_config = $this->get_googledrive_config();
        
        include DISCO747_CRM_TEMPLATES_PATH . 'admin/storage-unified-page.php';
    }
    
    /**
     * Render pagina messaggi
     */
    public function render_messages_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
        }
        
        $email_settings = $this->get_email_settings();
        $whatsapp_settings = $this->get_whatsapp_settings();
        
        include DISCO747_CRM_TEMPLATES_PATH . 'admin/messages-unified-page.php';
    }
    
    /**
     * Render pagina impostazioni
     */
    public function render_settings_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
        }
        
        include DISCO747_CRM_TEMPLATES_PATH . 'admin/settings-unified-page.php';
    }
    
    /**
     * Render pagina debug
     */
    public function render_debug_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
        }
        
        $debug_info = $this->get_debug_info();
        include DISCO747_CRM_TEMPLATES_PATH . 'admin/debug-unified-page.php';
    }
    
    // ============================================================================
    // DATA METHODS (Identici al funzionamento del vecchio plugin)
    // ============================================================================
    
    /**
     * Ottiene statistiche dashboard
     */
    private function get_dashboard_statistics() {
        $stats = array(
            'total_quotes' => 0,
            'pending_quotes' => 0,
            'confirmed_quotes' => 0,
            'this_month_revenue' => 0,
            'last_quote_date' => null
        );
        
        if ($this->database) {
            try {
                global $wpdb;
                $table = $wpdb->prefix . 'preventivi_disco';
                
                // Assicura che la tabella esista
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
                if (!$table_exists) {
                    // Crea tabella se non esiste
                    $this->database->create_tables();
                }
                
                // Statistiche base
                $stats['total_quotes'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table}") ?: 0);
                $stats['pending_quotes'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE stato = 'pending'") ?: 0);
                $stats['confirmed_quotes'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE confermato = 1") ?: 0);
                
                // Fatturato mensile
                $stats['this_month_revenue'] = floatval($wpdb->get_var("
                    SELECT SUM(importo_preventivo) 
                    FROM {$table} 
                    WHERE MONTH(created_at) = MONTH(CURDATE()) 
                    AND YEAR(created_at) = YEAR(CURDATE()) 
                    AND confermato = 1
                ") ?: 0);
                
                // Ultima data
                $stats['last_quote_date'] = $wpdb->get_var("
                    SELECT created_at 
                    FROM {$table} 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                
            } catch (Exception $e) {
                $this->log('Errore statistiche: ' . $e->getMessage(), 'ERROR');
            }
        }
        
        return $stats;
    }
    
    /**
     * Ottiene status sistema
     */
    private function get_system_status_summary() {
        return array(
            'database' => $this->database ? 'ok' : 'error',
            'storage' => $this->get_storage_configured_status(),
            'legacy_components' => $this->check_legacy_components(),
            'php_version' => phpversion(),
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => DISCO747_CRM_VERSION
        );
    }
    
    /**
     * Verifica componenti legacy
     */
    private function check_legacy_components() {
        $components = array(
            'storage_manager' => $this->legacy_storage_manager ? 'ok' : 'missing',
            'dropbox_handler' => $this->dropbox_handler ? 'ok' : 'missing',
            'pdf_excel_handler' => $this->pdf_excel_handler ? 'ok' : 'missing',
            'googledrive_handler' => $this->googledrive_handler ? 'ok' : 'missing'
        );
        
        $all_ok = !in_array('missing', $components);
        return $all_ok ? 'ok' : 'warning';
    }
    
    /**
     * Verifica status storage configurato
     */
    private function get_storage_configured_status() {
        $storage_type = get_option('preventivi_storage_type', 'dropbox');
        
        if ($storage_type === 'dropbox') {
            $refresh_token = get_option('preventivi_dropbox_refresh_token', '');
            return !empty($refresh_token) ? 'ok' : 'warning';
        } elseif ($storage_type === 'googledrive') {
            $refresh_token = get_option('preventivi_googledrive_refresh_token', '');
            return !empty($refresh_token) ? 'ok' : 'warning';
        }
        
        return 'warning';
    }
    
    /**
     * Ottiene preventivi recenti
     */
    private function get_recent_preventivi($limit = 5) {
        if (!$this->database) {
            return array();
        }
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'preventivi_disco';
            
            return $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$table} 
                ORDER BY created_at DESC 
                LIMIT %d
            ", $limit), ARRAY_A) ?: array();
            
        } catch (Exception $e) {
            $this->log('Errore preventivi recenti: ' . $e->getMessage(), 'ERROR');
            return array();
        }
    }
    
    /**
     * Ottiene tutti i preventivi
     */
    private function get_all_preventivi() {
        if (!$this->database) {
            return array();
        }
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'preventivi_disco';
            
            return $wpdb->get_results("
                SELECT * FROM {$table} 
                ORDER BY created_at DESC
            ", ARRAY_A) ?: array();
            
        } catch (Exception $e) {
            $this->log('Errore tutti preventivi: ' . $e->getMessage(), 'ERROR');
            return array();
        }
    }
    
    /**
     * Ottiene status storage
     */
    private function get_storage_status() {
        $status = array(
            'current_type' => get_option('preventivi_storage_type', 'dropbox'),
            'dropbox_configured' => false,
            'googledrive_configured' => false,
            'last_test' => get_option('disco747_last_storage_test', null)
        );
        
        // Test Dropbox
        $dropbox_refresh = get_option('preventivi_dropbox_refresh_token', '');
        $status['dropbox_configured'] = !empty($dropbox_refresh);
        
        // Test Google Drive
        $gdrive_refresh = get_option('preventivi_googledrive_refresh_token', '');
        $status['googledrive_configured'] = !empty($gdrive_refresh);
        
        return $status;
    }
    
    /**
     * Ottiene configurazione Dropbox
     */
    private function get_dropbox_config() {
        return array(
            'app_key' => get_option('preventivi_dropbox_app_key', ''),
            'app_secret' => get_option('preventivi_dropbox_app_secret', ''),
            'redirect_uri' => get_option('preventivi_dropbox_redirect_uri', ''),
            'refresh_token' => get_option('preventivi_dropbox_refresh_token', '')
        );
    }
    
    /**
     * Ottiene configurazione Google Drive
     */
    private function get_googledrive_config() {
        return array(
            'client_id' => get_option('preventivi_googledrive_client_id', ''),
            'client_secret' => get_option('preventivi_googledrive_client_secret', ''),
            'redirect_uri' => get_option('preventivi_googledrive_redirect_uri', ''),
            'refresh_token' => get_option('preventivi_googledrive_refresh_token', ''),
            'folder_id' => get_option('preventivi_googledrive_folder_id', '')
        );
    }
    
    /**
     * Ottiene impostazioni email
     */
    private function get_email_settings() {
        return array(
            'subject' => get_option('preventivi_email_subject', 'Il tuo preventivo 747 Disco è pronto!'),
            'template' => get_option('preventivi_email_template', $this->get_default_email_template()),
            'send_mode' => get_option('preventivi_default_send_mode', 'both')
        );
    }
    
    /**
     * Ottiene impostazioni WhatsApp
     */
    private function get_whatsapp_settings() {
        return array(
            'template' => get_option('preventivi_whatsapp_template', $this->get_default_whatsapp_template()),
            'enabled' => get_option('preventivi_whatsapp_enabled', '1')
        );
    }
    
    /**
     * Template email predefinito (IDENTICO al vecchio plugin)
     */
    public function get_default_email_template() {
        return "Ciao {{nome_referente}},\n\nIl tuo preventivo è pronto!\n\n**📅 Data:** {{data_evento}}\n**🎉 Evento:** {{tipo_evento}}\n**👥 Ospiti:** {{numero_invitati}}\n**🍽️ Menu:** {{tipo_menu}}\n**💰 Importo:** {{importo}}\n**💳 Acconto:** {{acconto}}\n\n📎 In allegato trovi il preventivo completo in formato PDF.\n\nTi contatteremo a breve per finalizzare tutti i dettagli!\n\n747 Disco | Via della Musica 1, Roma | Tel: 06 123456789";
    }
    
    /**
     * Template WhatsApp predefinito (IDENTICO al vecchio plugin)
     */
    public function get_default_whatsapp_template() {
        return "🎉 Ciao {{nome_referente}}!\n\nIl tuo preventivo per {{tipo_evento}} del {{data_evento}} è pronto!\n\n💰 Importo: {{importo}}\n💳 Acconto: {{acconto}}\n\nTi invieremo tutti i dettagli via email.\n\n747 Disco - La tua festa indimenticabile! 🎊";
    }
    
    /**
     * Ottiene informazioni debug
     */
    private function get_debug_info() {
        return array(
            'plugin_version' => DISCO747_CRM_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'mysql_version' => $this->get_mysql_version(),
            'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'storage_type' => get_option('preventivi_storage_type', 'dropbox'),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'legacy_components' => array(
                'storage_manager' => $this->legacy_storage_manager ? 'Available' : 'Missing',
                'dropbox_handler' => $this->dropbox_handler ? 'Available' : 'Missing',
                'pdf_excel_handler' => $this->pdf_excel_handler ? 'Available' : 'Missing',
                'googledrive_handler' => $this->googledrive_handler ? 'Available' : 'Missing'
            ),
            'new_components' => array(
                'database' => $this->database ? 'Available' : 'Missing',
                'config' => $this->config ? 'Available' : 'Missing',
                'storage_manager' => $this->storage_manager ? 'Available' : 'Missing'
            )
        );
    }
    
    /**
     * Ottiene versione MySQL
     */
    private function get_mysql_version() {
        global $wpdb;
        try {
            return $wpdb->get_var("SELECT VERSION()");
        } catch (Exception $e) {
            return 'Unknown';
        }
    }
    
    // ============================================================================
    // FORM PROCESSING (Identico al vecchio plugin)
    // ============================================================================
    
    /**
     * Processa form admin
     */
    public function process_admin_forms() {
        if (!current_user_can($this->min_capability)) {
            return;
        }
        
        // Verifica nonce
        if (!isset($_POST['disco747_nonce']) || !wp_verify_nonce($_POST['disco747_nonce'], 'disco747_admin_action')) {
            return;
        }
        
        // Gestisce azioni specifiche
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_storage_settings':
                    $this->save_storage_settings();
                    break;
                case 'save_message_settings':
                    $this->save_message_settings();
                    break;
                case 'test_oauth_connection':
                    $this->test_oauth_connection();
                    break;
                case 'switch_storage_type':
                    $this->switch_storage_type();
                    break;
                case 'force_database_update':
                    $this->force_database_update();
                    break;
                case 'save_oauth_settings':
                    $this->save_oauth_settings();
                    break;
                case 'exchange_oauth_code':
                    $this->exchange_oauth_code();
                    break;
            }
        }
    }
    
    /**
     * Salva impostazioni storage
     */
    private function save_storage_settings() {
        $storage_type = sanitize_text_field($_POST['storage_type'] ?? 'dropbox');
        update_option('preventivi_storage_type', $storage_type);
        
        if ($storage_type === 'dropbox') {
            update_option('preventivi_dropbox_app_key', sanitize_text_field($_POST['dropbox_app_key'] ?? ''));
            update_option('preventivi_dropbox_app_secret', sanitize_text_field($_POST['dropbox_app_secret'] ?? ''));
            update_option('preventivi_dropbox_redirect_uri', esc_url_raw($_POST['dropbox_redirect_uri'] ?? ''));
        } elseif ($storage_type === 'googledrive') {
            update_option('preventivi_googledrive_client_id', sanitize_text_field($_POST['gdrive_client_id'] ?? ''));
            update_option('preventivi_googledrive_client_secret', sanitize_text_field($_POST['gdrive_client_secret'] ?? ''));
            update_option('preventivi_googledrive_redirect_uri', esc_url_raw($_POST['gdrive_redirect_uri'] ?? ''));
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>✅ Impostazioni storage salvate con successo!</p></div>';
        });
    }
    
    /**
     * Salva impostazioni messaggi
     */
    private function save_message_settings() {
        update_option('preventivi_email_subject', sanitize_text_field($_POST['email_subject'] ?? ''));
        update_option('preventivi_email_template', wp_kses_post($_POST['email_template'] ?? ''));
        update_option('preventivi_whatsapp_template', sanitize_textarea_field($_POST['whatsapp_template'] ?? ''));
        update_option('preventivi_default_send_mode', sanitize_text_field($_POST['send_mode'] ?? 'both'));
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>✅ Impostazioni messaggi salvate con successo!</p></div>';
        });
    }
    
    /**
     * Test connessione OAuth
     */
    private function test_oauth_connection() {
        $result = $this->perform_oauth_test();
        
        if ($result['success']) {
            update_option('disco747_last_storage_test', current_time('mysql'));
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-success"><p>✅ ' . esc_html($result['message']) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>❌ ' . esc_html($result['message']) . '</p></div>';
            });
        }
    }
    
    /**
     * Esegue test OAuth utilizzando i componenti legacy
     */
    private function perform_oauth_test() {
        // Usa storage manager legacy se disponibile
        if ($this->legacy_storage_manager) {
            return $this->legacy_storage_manager->test_oauth_connection();
        }
        
        // Fallback ai singoli handler
        $storage_type = get_option('preventivi_storage_type', 'dropbox');
        
        if ($storage_type === 'dropbox' && $this->dropbox_handler) {
            return $this->dropbox_handler->test_oauth_connection();
        } elseif ($storage_type === 'googledrive' && $this->googledrive_handler) {
            return $this->googledrive_handler->test_oauth_connection();
        }
        
        return array('success' => false, 'message' => 'Nessuno storage handler disponibile');
    }
    
    /**
     * Cambia tipo storage
     */
    private function switch_storage_type() {
        $new_type = sanitize_text_field($_POST['new_storage_type'] ?? '');
        
        if (in_array($new_type, array('dropbox', 'googledrive'))) {
            update_option('preventivi_storage_type', $new_type);
            
            add_action('admin_notices', function() use ($new_type) {
                echo '<div class="notice notice-success"><p>✅ Storage cambiato a: ' . ucfirst($new_type) . '</p></div>';
            });
        }
    }
    
    /**
     * Forza aggiornamento database
     */
    private function force_database_update() {
        if ($this->database && method_exists($this->database, 'create_tables')) {
            $this->database->create_tables();
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>✅ Database aggiornato forzatamente!</p></div>';
            });
        }
    }
    
    /**
     * Salva impostazioni OAuth
     */
    private function save_oauth_settings() {
        $storage_type = get_option('preventivi_storage_type', 'dropbox');
        
        if ($storage_type === 'dropbox') {
            update_option('preventivi_dropbox_app_key', sanitize_text_field($_POST['dropbox_app_key'] ?? ''));
            update_option('preventivi_dropbox_app_secret', sanitize_text_field($_POST['dropbox_app_secret'] ?? ''));
            update_option('preventivi_dropbox_redirect_uri', esc_url_raw($_POST['dropbox_redirect_uri'] ?? ''));
        } elseif ($storage_type === 'googledrive') {
            update_option('preventivi_googledrive_client_id', sanitize_text_field($_POST['googledrive_client_id'] ?? ''));
            update_option('preventivi_googledrive_client_secret', sanitize_text_field($_POST['googledrive_client_secret'] ?? ''));
            update_option('preventivi_googledrive_redirect_uri', esc_url_raw($_POST['googledrive_redirect_uri'] ?? ''));
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>✅ Impostazioni OAuth salvate!</p></div>';
        });
    }
    
    /**
     * Scambia codice OAuth
     */
    private function exchange_oauth_code() {
        $auth_code = sanitize_text_field($_POST['oauth_auth_code'] ?? '');
        $state = sanitize_text_field($_POST['oauth_state'] ?? '');
        $storage_type = get_option('preventivi_storage_type', 'dropbox');
        
        $result = $this->perform_oauth_exchange($auth_code, $state, $storage_type);
        
        if ($result['success']) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-success"><p>✅ ' . esc_html($result['message']) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>❌ ' . esc_html($result['message']) . '</p></div>';
            });
        }
    }
    
    /**
     * Esegue scambio codice OAuth
     */
    private function perform_oauth_exchange($auth_code, $state, $storage_type) {
        // Usa storage manager legacy se disponibile
        if ($this->legacy_storage_manager) {
            return $this->legacy_storage_manager->exchange_code_for_tokens($auth_code, $state);
        }
        
        // Fallback ai singoli handler
        if ($storage_type === 'dropbox' && $this->dropbox_handler) {
            return $this->dropbox_handler->exchange_code_for_tokens($auth_code);
        } elseif ($storage_type === 'googledrive' && $this->googledrive_handler) {
            return $this->googledrive_handler->exchange_code_for_tokens($auth_code, $state);
        }
        
        return array('success' => false, 'message' => 'Nessuno storage handler disponibile');
    }
    
    // ============================================================================
    // AJAX HANDLERS (Compatibilità completa con il vecchio plugin)
    // ============================================================================
    
    /**
     * Gestisce elaborazione preventivo
     */
    public function handle_process_quote() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // Processa preventivo (logica identica al vecchio plugin)
        $quote_data = array(
            'nome_referente' => sanitize_text_field($_POST['nome_referente'] ?? ''),
            'cognome_referente' => sanitize_text_field($_POST['cognome_referente'] ?? ''),
            'mail' => sanitize_email($_POST['mail'] ?? ''),
            'cellulare' => sanitize_text_field($_POST['cellulare'] ?? ''),
            'tipo_evento' => sanitize_text_field($_POST['tipo_evento'] ?? ''),
            'data_evento' => sanitize_text_field($_POST['data_evento'] ?? ''),
            'numero_invitati' => intval($_POST['numero_invitati'] ?? 0),
            'tipo_menu' => sanitize_text_field($_POST['tipo_menu'] ?? ''),
            'importo_preventivo' => floatval($_POST['importo_preventivo'] ?? 0)
        );
        
        // Salva nel database
        if ($this->database) {
            $result = $this->database->insert_preventivo($quote_data);
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => 'Preventivo salvato con successo',
                    'quote_id' => $result
                ));
            } else {
                wp_send_json_error('Errore salvataggio preventivo');
            }
        } else {
            wp_send_json_error('Database non disponibile');
        }
    }
    
    /**
     * Gestisce azioni OAuth
     */
    public function handle_oauth_action() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        
        switch ($action_type) {
            case 'generate_auth_url':
                $this->ajax_generate_auth_url();
                break;
            case 'exchange_code':
                $this->ajax_exchange_code();
                break;
            case 'test_connection':
                $this->ajax_test_connection();
                break;
            default:
                wp_send_json_error('Azione non riconosciuta');
        }
    }
    
    /**
     * AJAX: Genera URL autorizzazione
     */
    private function ajax_generate_auth_url() {
        // Usa storage manager legacy se disponibile
        if ($this->legacy_storage_manager) {
            $result = $this->legacy_storage_manager->generate_auth_url();
            wp_send_json($result);
            return;
        }
        
        // Fallback ai singoli handler
        $storage_type = get_option('preventivi_storage_type', 'dropbox');
        
        if ($storage_type === 'dropbox' && $this->dropbox_handler) {
            $result = $this->dropbox_handler->generate_auth_url();
            wp_send_json($result);
        } elseif ($storage_type === 'googledrive' && $this->googledrive_handler) {
            $result = $this->googledrive_handler->generate_auth_url();
            wp_send_json($result);
        } else {
            wp_send_json_error('Storage handler non disponibile');
        }
    }
    
    /**
     * AJAX: Scambia codice autorizzazione
     */
    private function ajax_exchange_code() {
        $auth_code = sanitize_text_field($_POST['auth_code'] ?? '');
        $state = sanitize_text_field($_POST['state'] ?? '');
        $storage_type = get_option('preventivi_storage_type', 'dropbox');
        
        $result = $this->perform_oauth_exchange($auth_code, $state, $storage_type);
        wp_send_json($result);
    }
    
    /**
     * AJAX: Test connessione
     */
    private function ajax_test_connection() {
        $result = $this->perform_oauth_test();
        wp_send_json($result);
    }
    
    /**
     * Gestisce azioni storage
     */
    public function handle_storage_action() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        
        switch ($action_type) {
            case 'switch_type':
                $new_type = sanitize_text_field($_POST['storage_type'] ?? '');
                update_option('preventivi_storage_type', $new_type);
                wp_send_json_success('Storage cambiato a: ' . $new_type);
                break;
            case 'get_usage':
                $this->ajax_get_storage_usage();
                break;
            default:
                wp_send_json_error('Azione storage non riconosciuta');
        }
    }
    
    /**
     * AJAX: Ottiene uso storage
     */
    private function ajax_get_storage_usage() {
        $storage_type = get_option('preventivi_storage_type', 'dropbox');
        
        if ($storage_type === 'dropbox' && $this->dropbox_handler && method_exists($this->dropbox_handler, 'get_dropbox_usage')) {
            $usage = $this->dropbox_handler->get_dropbox_usage();
            wp_send_json_success($usage);
        } else {
            wp_send_json_success(array(
                'usage' => 'N/A',
                'type' => $storage_type
            ));
        }
    }
    
    /**
     * Gestisce test connessione
     */
    public function handle_test_connection() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $result = $this->perform_oauth_test();
        wp_send_json($result);
    }
    
    /**
     * Gestisce generazione URL autorizzazione
     */
    public function handle_generate_auth_url() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $this->ajax_generate_auth_url();
    }
    
    /**
     * Gestisce scambio codice
     */
    public function handle_exchange_code() {
        check_ajax_referer('disco747_admin_nonce', 'nonce');
        
        if (!current_user_can($this->min_capability)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $this->ajax_exchange_code();
    }
    
    /**
     * Gestisce login esterno
     */
    private function handle_external_login_form() {
        // Sistema di login personalizzato (identico al vecchio plugin)
        if (isset($_POST['login_username']) && isset($_POST['login_password'])) {
            $username = sanitize_text_field($_POST['login_username']);
            $password = $_POST['login_password'];
            
            // Verifica credenziali hardcoded
            $users = array(
                'andrea' => 'disco_747@!',
                'federico' => 'disco@747_!',
                'staff' => 'staff123',
                'manager' => 'manager747'
            );
            
            if (isset($users[$username]) && $users[$username] === $password) {
                // Login riuscito
                $_SESSION['disco747_external_user'] = $username;
                wp_redirect(admin_url('admin.php?page=disco747-crm&external_dashboard=1'));
                exit;
            } else {
                $login_error = 'Credenziali non valide';
            }
        }
        
        // Mostra form login
        include DISCO747_CRM_TEMPLATES_PATH . 'external/login-form.php';
    }
    
    /**
     * Logging interno
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode && function_exists('error_log')) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[{$timestamp}] [747Disco-CRM-AdminUnified] [{$level}] {$message}");
        }
    }
}

// Alias per compatibilità
if (!class_exists('Disco747_Admin_Unified')) {
    class_alias('Disco747_CRM\\Admin\\Disco747_Admin_Unified', 'Disco747_Admin_Unified');
}
