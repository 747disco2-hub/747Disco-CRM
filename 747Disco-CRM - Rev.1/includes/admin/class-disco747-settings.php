<?php
/**
 * Classe per la gestione dell'interfaccia delle impostazioni
 *
 * Gestisce la creazione dell'interfaccia utente per le impostazioni del plugin,
 * inclusi menu, pagine, sezioni e campi di configurazione.
 *
 * @package    Disco747_CRM
 * @subpackage Admin
 * @since      1.0.0
 * @version    1.0.0
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Admin;

use Disco747_CRM\Core\Disco747_Config;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe Disco747_Settings
 *
 * Gestisce l'interfaccia utente per le impostazioni del plugin WordPress.
 * Si occupa esclusivamente del rendering e della registrazione dei campi,
 * delegando tutta la logica di supporto alla classe helper.
 */
class Disco747_Settings {

    /**
     * Istanza helper per funzioni di supporto
     *
     * @var Disco747_Settings_Helper
     */
    private $helper;

    /**
     * Slug della pagina principale delle impostazioni
     *
     * @var string
     */
    private $page_slug = 'disco747-settings';

    /**
     * Capability richiesta per accedere alle impostazioni
     *
     * @var string
     */
    private $capability = 'manage_options';

    /**
     * Costruttore
     *
     * Inizializza la classe e registra i hook necessari
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->helper = new Disco747_Settings_Helper();
        $this->init_hooks();
    }

    /**
     * Inizializza i hook di WordPress
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Aggiunge le voci di menu nell'amministrazione
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        // Menu principale
        add_menu_page(
            __('747 Disco CRM', 'disco747'),
            __('747 Disco CRM', 'disco747'),
            $this->capability,
            'disco747-crm',
            array($this, 'render_main_page'),
            'dashicons-clipboard',
            30
        );

        // Sottomenu: Impostazioni Generali
        add_submenu_page(
            'disco747-crm',
            __('Impostazioni Generali', 'disco747'),
            __('Impostazioni', 'disco747'),
            $this->capability,
            $this->page_slug,
            array($this, 'render_settings_page')
        );

        // Sottomenu: Storage Cloud
        add_submenu_page(
            'disco747-crm',
            __('Configurazione Storage', 'disco747'),
            __('Storage Cloud', 'disco747'),
            $this->capability,
            'disco747-storage',
            array($this, 'render_storage_page')
        );

        // Sottomenu: Messaggi Automatici
        add_submenu_page(
            'disco747-crm',
            __('Messaggi Automatici', 'disco747'),
            __('Messaggi', 'disco747'),
            $this->capability,
            'disco747-messages',
            array($this, 'render_messages_page')
        );

        // Sottomenu: Sistema
        add_submenu_page(
            'disco747-crm',
            __('Informazioni Sistema', 'disco747'),
            __('Sistema', 'disco747'),
            $this->capability,
            'disco747-system',
            array($this, 'render_system_page')
        );
    }

    /**
     * Registra le impostazioni e i campi
     *
     * @since 1.0.0
     */
    public function register_settings() {
        $this->register_general_settings();
        $this->register_storage_settings();
        $this->register_messaging_settings();
        $this->register_auth_settings();
    }

    /**
     * Registra le impostazioni generali
     *
     * @since 1.0.0
     */
    private function register_general_settings() {
        // Gruppo impostazioni generali
        $group = 'disco747_general_settings';
        
        // Registra le singole opzioni
        register_setting(
            $group,
            'disco747_company_name',
            array(
                'sanitize_callback' => array($this->helper, 'sanitize_text_field'),
                'default' => $this->helper->get_default('company_name')
            )
        );

        register_setting(
            $group,
            'disco747_company_email',
            array(
                'sanitize_callback' => array($this->helper, 'sanitize_email'),
                'default' => $this->helper->get_default('company_email')
            )
        );

        register_setting(
            $group,
            'disco747_company_phone',
            array(
                'sanitize_callback' => array($this->helper, 'sanitize_text_field'),
                'default' => $this->helper->get_default('company_phone')
            )
        );

        register_setting(
            $group,
            'disco747_debug_mode',
            array(
                'sanitize_callback' => array($this->helper, 'sanitize_checkbox'),
                'default' => $this->helper->get_default('debug_mode')
            )
        );

        // Sezione informazioni azienda
        add_settings_section(
            'disco747_company_section',
            __('Informazioni Azienda', 'disco747'),
            array($this, 'render_company_section_info'),
            $this->page_slug
        );

        // Campi della sezione azienda
        add_settings_field(
            'disco747_company_name',
            __('Nome Azienda', 'disco747'),
            array($this, 'render_company_name_field'),
            $this->page_slug,
            'disco747_company_section'
        );

        add_settings_field(
            'disco747_company_email',
            __('Email Aziendale', 'disco747'),
            array($this, 'render_company_email_field'),
            $this->page_slug,
            'disco747_company_section'
        );

        add_settings_field(
            'disco747_company_phone',
            __('Telefono Azienda', 'disco747'),
            array($this, 'render_company_phone_field'),
            $this->page_slug,
            'disco747_company_section'
        );

        // Sezione debug
        add_settings_section(
            'disco747_debug_section',
            __('Debug e Sviluppo', 'disco747'),
            array($this, 'render_debug_section_info'),
            $this->page_slug
        );

        add_settings_field(
            'disco747_debug_mode',
            __('Modalità Debug', 'disco747'),
            array($this, 'render_debug_mode_field'),
            $this->page_slug,
            'disco747_debug_section'
        );
    }

    /**
     * Registra le impostazioni storage
     *
     * @since 1.0.0
     */
    private function register_storage_settings() {
        $group = 'disco747_storage_settings';

        // Storage principale
        register_setting(
            $group,
            'disco747_storage_type',
            array(
                'sanitize_callback' => array($this->helper, 'sanitize_storage_type'),
                'default' => $this->helper->get_default('storage_type')
            )
        );

        // Dropbox
        $dropbox_fields = array('app_key', 'app_secret', 'redirect_uri', 'refresh_token');
        foreach ($dropbox_fields as $field) {
            register_setting(
                $group,
                "disco747_dropbox_{$field}",
                array(
                    'sanitize_callback' => array($this->helper, 'sanitize_credential'),
                    'default' => ''
                )
            );
        }

        // Google Drive
        $gdrive_fields = array('client_id', 'client_secret', 'redirect_uri', 'refresh_token', 'folder_id');
        foreach ($gdrive_fields as $field) {
            register_setting(
                $group,
                "disco747_googledrive_{$field}",
                array(
                    'sanitize_callback' => array($this->helper, 'sanitize_credential'),
                    'default' => ''
                )
            );
        }
    }

    /**
     * Registra le impostazioni messaggistica
     *
     * @since 1.0.0
     */
    private function register_messaging_settings() {
        $group = 'disco747_messaging_settings';

        $messaging_fields = array(
            'email_subject' => 'sanitize_text_field',
            'email_template' => 'sanitize_html_template',
            'whatsapp_template' => 'sanitize_textarea_field',
            'default_send_mode' => 'sanitize_send_mode'
        );

        foreach ($messaging_fields as $field => $sanitizer) {
            register_setting(
                $group,
                "disco747_{$field}",
                array(
                    'sanitize_callback' => array($this->helper, $sanitizer),
                    'default' => $this->helper->get_default($field)
                )
            );
        }
    }

    /**
     * Registra le impostazioni autenticazione
     *
     * @since 1.0.0
     */
    private function register_auth_settings() {
        $group = 'disco747_auth_settings';

        register_setting(
            $group,
            'disco747_session_timeout',
            array(
                'sanitize_callback' => array($this->helper, 'sanitize_positive_integer'),
                'default' => $this->helper->get_default('session_timeout')
            )
        );

        register_setting(
            $group,
            'disco747_max_login_attempts',
            array(
                'sanitize_callback' => array($this->helper, 'sanitize_positive_integer'),
                'default' => $this->helper->get_default('max_login_attempts')
            )
        );
    }

    /**
     * Carica script e stili per l'admin
     *
     * @since 1.0.0
     * @param string $hook_suffix Il suffisso della pagina corrente
     */
    public function enqueue_admin_scripts($hook_suffix) {
        // Carica solo nelle pagine del plugin
        if (strpos($hook_suffix, 'disco747') === false) {
            return;
        }

        wp_enqueue_style(
            'disco747-admin-css',
            plugin_dir_url(__FILE__) . '../../assets/css/admin.css',
            array(),
            Disco747_Config::VERSION
        );

        wp_enqueue_script(
            'disco747-admin-js',
            plugin_dir_url(__FILE__) . '../../assets/js/admin.js',
            array('jquery'),
            Disco747_Config::VERSION,
            true
        );

        // Localizza script per AJAX
        wp_localize_script('disco747-admin-js', 'disco747_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('disco747_admin_nonce'),
            'strings' => array(
                'test_connection' => __('Test Connessione', 'disco747'),
                'testing' => __('Test in corso...', 'disco747'),
                'success' => __('Successo!', 'disco747'),
                'error' => __('Errore:', 'disco747'),
                'confirm_reset' => __('Sei sicuro di voler ripristinare le impostazioni predefinite?', 'disco747')
            )
        ));
    }

    /**
     * Renderizza la pagina principale
     *
     * @since 1.0.0
     */
    public function render_main_page() {
        include_once plugin_dir_path(__FILE__) . 'views/main-page.php';
    }

    /**
     * Renderizza la pagina delle impostazioni generali
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        // Gestisce il salvataggio delle impostazioni
        if (isset($_POST['submit']) && check_admin_referer('disco747_general_settings-options')) {
            $this->handle_settings_save();
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        include_once plugin_dir_path(__FILE__) . 'views/settings-page.php';
    }

    /**
     * Renderizza la pagina storage
     *
     * @since 1.0.0
     */
    public function render_storage_page() {
        // Gestisce azioni storage
        if (isset($_POST['action'])) {
            $this->handle_storage_action();
        }

        $current_storage = get_option('disco747_storage_type', 'dropbox');
        include_once plugin_dir_path(__FILE__) . 'views/storage-page.php';
    }

    /**
     * Renderizza la pagina messaggi
     *
     * @since 1.0.0
     */
    public function render_messages_page() {
        if (isset($_POST['submit']) && check_admin_referer('disco747_messaging_settings-options')) {
            $this->handle_messages_save();
        }

        include_once plugin_dir_path(__FILE__) . 'views/messages-page.php';
    }

    /**
     * Renderizza la pagina sistema
     *
     * @since 1.0.0
     */
    public function render_system_page() {
        $system_info = $this->helper->get_system_info();
        include_once plugin_dir_path(__FILE__) . 'views/system-page.php';
    }

    // ============================================================================
    // CALLBACK PER I CAMPI DELLE IMPOSTAZIONI
    // ============================================================================

    /**
     * Info sezione azienda
     *
     * @since 1.0.0
     */
    public function render_company_section_info() {
        echo '<p>' . __('Configura le informazioni base della tua azienda.', 'disco747') . '</p>';
    }

    /**
     * Campo nome azienda
     *
     * @since 1.0.0
     */
    public function render_company_name_field() {
        $value = get_option('disco747_company_name', $this->helper->get_default('company_name'));
        echo '<input type="text" id="disco747_company_name" name="disco747_company_name" value="' . esc_attr($value) . '" class="regular-text" required />';
        echo '<p class="description">' . __('Nome dell\'azienda che apparirà nei documenti.', 'disco747') . '</p>';
    }

    /**
     * Campo email azienda
     *
     * @since 1.0.0
     */
    public function render_company_email_field() {
        $value = get_option('disco747_company_email', $this->helper->get_default('company_email'));
        echo '<input type="email" id="disco747_company_email" name="disco747_company_email" value="' . esc_attr($value) . '" class="regular-text" required />';
        echo '<p class="description">' . __('Email aziendale per le comunicazioni.', 'disco747') . '</p>';
    }

    /**
     * Campo telefono azienda
     *
     * @since 1.0.0
     */
    public function render_company_phone_field() {
        $value = get_option('disco747_company_phone', $this->helper->get_default('company_phone'));
        echo '<input type="tel" id="disco747_company_phone" name="disco747_company_phone" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Numero di telefono aziendale.', 'disco747') . '</p>';
    }

    /**
     * Info sezione debug
     *
     * @since 1.0.0
     */
    public function render_debug_section_info() {
        echo '<p>' . __('Impostazioni per sviluppatori e risoluzione problemi.', 'disco747') . '</p>';
    }

    /**
     * Campo modalità debug
     *
     * @since 1.0.0
     */
    public function render_debug_mode_field() {
        $value = get_option('disco747_debug_mode', $this->helper->get_default('debug_mode'));
        echo '<label for="disco747_debug_mode">';
        echo '<input type="checkbox" id="disco747_debug_mode" name="disco747_debug_mode" value="1" ' . checked(1, $value, false) . ' />';
        echo ' ' . __('Abilita modalità debug (aumenta i log)', 'disco747');
        echo '</label>';
    }

    // ============================================================================
    // GESTORI AZIONI
    // ============================================================================

    /**
     * Gestisce il salvataggio delle impostazioni generali
     *
     * @since 1.0.0
     */
    private function handle_settings_save() {
        $result = $this->helper->validate_and_save_general_settings($_POST);
        
        if ($result['success']) {
            add_settings_error(
                'disco747_messages',
                'disco747_message',
                __('Impostazioni salvate con successo.', 'disco747'),
                'success'
            );
        } else {
            add_settings_error(
                'disco747_messages',
                'disco747_error',
                $result['message'],
                'error'
            );
        }
    }

    /**
     * Gestisce le azioni storage
     *
     * @since 1.0.0
     */
    private function handle_storage_action() {
        $action = sanitize_key($_POST['action']);
        $result = array('success' => false, 'message' => '');

        switch ($action) {
            case 'save_storage_type':
                $result = $this->helper->save_storage_type($_POST);
                break;

            case 'save_dropbox_credentials':
                $result = $this->helper->save_dropbox_credentials($_POST);
                break;

            case 'save_googledrive_credentials':
                $result = $this->helper->save_googledrive_credentials($_POST);
                break;

            case 'test_connection':
                $result = $this->helper->test_storage_connection();
                break;

            case 'generate_auth_url':
                $result = $this->helper->generate_auth_url();
                break;

            case 'exchange_auth_code':
                $result = $this->helper->exchange_auth_code($_POST);
                break;

            default:
                $result['message'] = __('Azione non riconosciuta.', 'disco747');
        }

        $type = $result['success'] ? 'success' : 'error';
        add_settings_error('disco747_messages', 'disco747_message', $result['message'], $type);
    }

    /**
     * Gestisce il salvataggio messaggi
     *
     * @since 1.0.0
     */
    private function handle_messages_save() {
        $result = $this->helper->validate_and_save_messaging_settings($_POST);
        
        $type = $result['success'] ? 'success' : 'error';
        add_settings_error('disco747_messages', 'disco747_message', $result['message'], $type);
    }

    // ============================================================================
    // UTILITY PUBBLICHE
    // ============================================================================

    /**
     * Ottiene il valore di un'opzione con fallback al default
     *
     * @since 1.0.0
     * @param string $option_name Nome dell'opzione
     * @return mixed Valore dell'opzione
     */
    public function get_option($option_name) {
        return get_option("disco747_{$option_name}", $this->helper->get_default($option_name));
    }

    /**
     * Verifica se il plugin è configurato correttamente
     *
     * @since 1.0.0
     * @return bool True se configurato
     */
    public function is_configured() {
        return $this->helper->is_system_configured();
    }

    /**
     * Ottiene l'URL della pagina di configurazione
     *
     * @since 1.0.0
     * @param string $page Pagina specifica (opzionale)
     * @return string URL della pagina
     */
    public function get_settings_url($page = '') {
        $base_page = $page ?: $this->page_slug;
        return admin_url("admin.php?page={$base_page}");
    }

    /**
     * Renderizza un messaggio di stato
     *
     * @since 1.0.0
     * @param string $message Messaggio da mostrare
     * @param string $type Tipo di messaggio (success, error, warning, info)
     */
    public function render_admin_notice($message, $type = 'info') {
        $class = "notice notice-{$type}";
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    /**
     * Ottiene le informazioni di compatibilità
     *
     * @since 1.0.0
     * @return array Informazioni di compatibilità
     */
    public function get_compatibility_info() {
        return $this->helper->check_system_compatibility();
    }
}