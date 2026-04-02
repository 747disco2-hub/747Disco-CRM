<?php
/**
 * 747 Disco CRM - Frontend Pages - VERSIONE CORRETTA
 * 
 * RISOLVE: Problema timing inizializzazione
 * 
 * @package Disco747_CRM
 * @subpackage Frontend
 * @since 11.5.0
 */

namespace Disco747_CRM\Frontend;

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per la gestione delle pagine frontend - TIMING SAFE
 */
class Disco747_Pages {

    /**
     * Componenti core (lazy loaded)
     */
    private $auth = null;
    private $forms = null;
    private $storage_manager = null;

    /**
     * Configurazione pagine
     */
    private $page_slugs = array(
        'login' => 'disco747-login',
        'form' => 'disco747-preventivi',
        'dashboard' => 'disco747-dashboard',
        'logout' => 'disco747-logout'
    );

    private $registered_pages = array();
    private $page_templates = array();
    private $pages_initialized = false;
    private $components_loaded = false;

    /**
     * Costruttore SAFE con lazy loading
     */
    public function __construct() {
        try {
            // NON caricare componenti nel costruttore!
            $this->init_page_templates();
            $this->init_hooks();
            
            $this->log('Disco747_Pages inizializzato (lazy loading)');
            
        } catch (\Exception $e) {
            $this->log('Errore inizializzazione Disco747_Pages: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Carica componenti solo quando necessario (LAZY LOADING)
     */
    private function load_components_if_needed() {
        if ($this->components_loaded) {
            return true;
        }

        try {
            // Verifica se il plugin principale è disponibile
            if (!function_exists('disco747_crm')) {
                $this->log('Funzione disco747_crm() non ancora disponibile', 'warning');
                return false;
            }

            $disco747_crm = disco747_crm();
            if (!$disco747_crm) {
                $this->log('Plugin principale non disponibile', 'warning');
                return false;
            }

            // Carica componenti solo se disponibili
            if (method_exists($disco747_crm, 'get_auth')) {
                $this->auth = $disco747_crm->get_auth();
            }

            if (method_exists($disco747_crm, 'get_forms')) {
                $this->forms = $disco747_crm->get_forms();
            }

            if (method_exists($disco747_crm, 'get_storage_manager')) {
                $this->storage_manager = $disco747_crm->get_storage_manager();
            }

            $this->components_loaded = true;
            $this->log('Componenti Frontend Pages caricati con successo');
            return true;

        } catch (\Exception $e) {
            $this->log('Errore caricamento componenti: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Inizializza template personalizzati
     */
    private function init_page_templates() {
        $template_path = DISCO747_CRM_PLUGIN_DIR . 'templates/frontend/';
        
        $this->page_templates = array(
            'page-disco747-login.php' => $template_path . 'login-page.php',
            'page-disco747-preventivi.php' => $template_path . 'form-page.php',
            'page-disco747-dashboard.php' => $template_path . 'dashboard-page.php',
            'page-disco747-logout.php' => $template_path . 'logout-page.php'
        );
    }

    /**
     * Inizializza hook WordPress
     */
    private function init_hooks() {
        // Template hooks
        add_filter('template_include', array($this, 'load_custom_templates'), 99);
        
        // Rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Template redirect (usa lazy loading)
        add_action('template_redirect', array($this, 'handle_template_redirect'));
        
        // Shortcodes
        add_shortcode('disco747_login_form', array($this, 'render_login_form_shortcode'));
        add_shortcode('disco747_quote_form', array($this, 'render_quote_form_shortcode'));
        add_shortcode('disco747_dashboard', array($this, 'render_dashboard_shortcode'));
        
        // Assets frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // AJAX handlers (lazy loading)
        add_action('wp_ajax_disco747_frontend_login', array($this, 'handle_frontend_login'));
        add_action('wp_ajax_nopriv_disco747_frontend_login', array($this, 'handle_frontend_login'));
        
        add_action('wp_ajax_disco747_submit_preventivo', array($this, 'handle_submit_preventivo'));
        add_action('wp_ajax_nopriv_disco747_submit_preventivo', array($this, 'handle_submit_preventivo'));
    }

    /**
     * Controlla se è una pagina del plugin
     */
    private function is_disco747_page() {
        if (!is_page()) {
            return false;
        }

        global $post;
        return in_array($post->post_name, $this->page_slugs);
    }

    /**
     * Carica template personalizzati
     */
    public function load_custom_templates($template) {
        if (!$this->is_disco747_page()) {
            return $template;
        }

        global $post;
        $page_slug = $post->post_name;
        $template_key = 'page-' . $page_slug . '.php';
        
        if (isset($this->page_templates[$template_key])) {
            $custom_template = $this->page_templates[$template_key];
            
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * Aggiunge rewrite rules personalizzate
     */
    public function add_rewrite_rules() {
        foreach ($this->page_slugs as $type => $slug) {
            add_rewrite_rule(
                '^' . $slug . '/?$',
                'index.php?pagename=' . $slug,
                'top'
            );
        }
    }

    /**
     * Aggiunge query vars personalizzate
     */
    public function add_query_vars($vars) {
        $vars[] = 'disco747_action';
        $vars[] = 'disco747_id';
        $vars[] = 'disco747_token';
        $vars[] = 'edit';
        return $vars;
    }

    /**
     * Gestisce redirect template (CON LAZY LOADING)
     */
    public function handle_template_redirect() {
        if (!is_page()) {
            return;
        }

        // Carica componenti solo se necessario
        if (!$this->load_components_if_needed()) {
            // Se non riesce a caricare i componenti, non fare redirect
            return;
        }

        global $post;
        $page_slug = $post->post_name;

        // Pagine protette che richiedono login
        $protected_pages = array('disco747-dashboard', 'disco747-preventivi');
        
        if (in_array($page_slug, $protected_pages) && $this->auth && !$this->auth->is_logged_in()) {
            wp_redirect(home_url('/disco747-login/'));
            exit;
        }

        // Logout automatico
        if ($page_slug === 'disco747-logout' && $this->auth) {
            $this->auth->logout();
            wp_redirect(home_url('/disco747-login/?logged_out=1'));
            exit;
        }
    }

    /**
     * Carica assets frontend
     */
    public function enqueue_frontend_assets() {
        if (!$this->is_disco747_page()) {
            return;
        }

        try {
            // CSS frontend
            wp_enqueue_style(
                'disco747-frontend-css',
                DISCO747_CRM_ASSETS_URL . 'css/frontend.css',
                array(),
                DISCO747_CRM_VERSION
            );

            // JS frontend
            wp_enqueue_script(
                'disco747-frontend-js',
                DISCO747_CRM_ASSETS_URL . 'js/frontend.js',
                array('jquery'),
                DISCO747_CRM_VERSION,
                true
            );

            // Localizzazione JS
            wp_localize_script('disco747-frontend-js', 'disco747Frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('disco747_frontend_nonce'),
                'messages' => array(
                    'login_error' => __('Errore di login. Controlla le credenziali.', 'disco747'),
                    'form_error' => __('Errore invio form. Riprova.', 'disco747'),
                    'loading' => __('Caricamento...', 'disco747')
                )
            ));

        } catch (\Exception $e) {
            $this->log('Errore caricamento assets: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * AJAX: Gestisce login frontend (CON LAZY LOADING)
     */
    public function handle_frontend_login() {
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'disco747_frontend_nonce')) {
                wp_send_json_error('Nonce non valido');
            }

            // Carica componenti se necessario
            if (!$this->load_components_if_needed() || !$this->auth) {
                wp_send_json_error('Servizio login non disponibile');
            }

            $username = sanitize_text_field($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = !empty($_POST['remember']);

            if (empty($username) || empty($password)) {
                wp_send_json_error('Username e password richiesti');
            }

            $result = $this->auth->login($username, $password, $remember);

            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => 'Login effettuato',
                    'redirect' => home_url('/disco747-dashboard/')
                ));
            } else {
                wp_send_json_error($result['message']);
            }

        } catch (\Exception $e) {
            $this->log('Errore login frontend: ' . $e->getMessage(), 'error');
            wp_send_json_error('Errore interno del server');
        }
    }

    /**
     * AJAX: Gestisce invio preventivo (CON LAZY LOADING)
     */
    public function handle_submit_preventivo() {
        try {
            // Verifica nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'disco747_frontend_nonce')) {
                wp_send_json_error('Nonce non valido');
            }

            // Carica componenti se necessario
            if (!$this->load_components_if_needed() || !$this->forms) {
                wp_send_json_error('Servizio preventivi non disponibile');
            }

            // Delega l'elaborazione al gestore form
            $result = $this->forms->process_form_submission($_POST);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }

        } catch (\Exception $e) {
            $this->log('Errore invio preventivo: ' . $e->getMessage(), 'error');
            wp_send_json_error('Errore nell\'invio del preventivo');
        }
    }

    /**
     * Shortcode: Form di login
     */
    public function render_login_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect' => home_url('/disco747-dashboard/'),
            'class' => 'disco747-login-form'
        ), $atts);

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <form id="disco747-login-form" method="post">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="remember" value="1">
                        Ricordami
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit">Login</button>
                </div>
                
                <input type="hidden" name="redirect" value="<?php echo esc_url($atts['redirect']); ?>">
                <?php wp_nonce_field('disco747_frontend_nonce', 'nonce'); ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Form preventivo
     */
    public function render_quote_form_shortcode($atts) {
        // Carica componenti se necessario
        if (!$this->load_components_if_needed() || !$this->forms) {
            return '<p>Form preventivi non disponibile.</p>';
        }

        return $this->forms->render_form_shortcode($atts);
    }

    /**
     * Shortcode: Dashboard
     */
    public function render_dashboard_shortcode($atts) {
        // Carica componenti se necessario
        if (!$this->load_components_if_needed() || !$this->auth) {
            return '<p>Dashboard non disponibile.</p>';
        }

        if (!$this->auth->is_logged_in()) {
            return '<p>Devi essere loggato per accedere alla dashboard.</p>';
        }

        ob_start();
        ?>
        <div class="disco747-dashboard">
            <h2>Dashboard 747 Disco CRM</h2>
            <p>Benvenuto nella dashboard!</p>
            <!-- Contenuto dashboard -->
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Funzione di logging
     */
    private function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[" . date('Y-m-d H:i:s') . "] [Disco747_Pages] [{$level}] {$message}");
        }
    }
}