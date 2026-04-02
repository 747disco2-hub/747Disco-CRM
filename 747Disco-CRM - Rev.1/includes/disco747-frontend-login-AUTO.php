<?php
/**
 * 747 Disco CRM - Frontend Login System AUTO-INSTALL
 * 
 * Questo file si auto-installa nel plugin senza modifiche manuali.
 * Basta copiarlo nella cartella del plugin e il sistema login sarà attivo.
 * 
 * @package Disco747_CRM
 * @version 1.0.0
 * @auto-install true
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// AUTO-INSTALL: Registra se stesso nel plugin
// ============================================================================

add_action('plugins_loaded', 'disco747_autoload_frontend_login', 5);

function disco747_autoload_frontend_login() {
    // Verifica che il plugin principale sia caricato
    if (!class_exists('Disco747_CRM_Plugin')) {
        return;
    }
    
    // Inizializza il sistema di login frontend
    Disco747_Frontend_Login_Auto::get_instance();
}

// ============================================================================
// CLASSE FRONTEND LOGIN - VERSIONE AUTO-INSTALL
// ============================================================================

class Disco747_Frontend_Login_Auto {
    
    private static $instance = null;
    private $page_slug = 'crm-login-747';
    private $page_id = null;
    private $template_created = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Auto-crea template file se non esiste
        $this->auto_create_template();
        
        // Inizializza hooks
        $this->init_hooks();
        
        // Auto-crea pagina login
        add_action('init', array($this, 'auto_create_login_page'), 20);
    }
    
    /**
     * Auto-crea il file template se non esiste
     */
    private function auto_create_template() {
        $template_dir = DISCO747_CRM_PLUGIN_DIR . 'templates/';
        $template_file = $template_dir . 'frontend-login.php';
        
        // Crea directory se non esiste
        if (!file_exists($template_dir)) {
            wp_mkdir_p($template_dir);
        }
        
        // Se il template non esiste, crealo
        if (!file_exists($template_file)) {
            $template_content = $this->get_template_content();
            file_put_contents($template_file, $template_content);
            $this->template_created = true;
            error_log('747 Disco: Template login creato automaticamente');
        }
    }
    
    /**
     * Inizializza hooks WordPress
     */
    private function init_hooks() {
        add_filter('template_include', array($this, 'load_login_template'), 99);
        add_filter('get_pages', array($this, 'hide_login_page'));
        add_filter('login_redirect', array($this, 'custom_login_redirect'), 10, 3);
        add_action('wp_logout', array($this, 'logout_redirect'));
        add_action('admin_init', array($this, 'restrict_admin_access'));
        add_filter('body_class', array($this, 'add_body_class'));
    }
    
    /**
     * Auto-crea pagina di login
     */
    public function auto_create_login_page() {
        $existing_page = get_page_by_path($this->page_slug);
        
        if (!$existing_page) {
            $page_data = array(
                'post_title'     => 'Login CRM 747 Disco',
                'post_name'      => $this->page_slug,
                'post_content'   => '<!-- Pagina di login gestita dal plugin 747 Disco CRM -->',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'post_author'    => 1,
                'comment_status' => 'closed',
                'ping_status'    => 'closed'
            );
            
            $page_id = wp_insert_post($page_data);
            
            if ($page_id && !is_wp_error($page_id)) {
                update_option('disco747_login_page_id', $page_id);
                update_option('page_on_front', $page_id);
                update_option('show_on_front', 'page');
                flush_rewrite_rules();
                
                $this->page_id = $page_id;
                error_log('747 Disco: Pagina login creata automaticamente (ID: ' . $page_id . ')');
            }
        } else {
            $this->page_id = $existing_page->ID;
            update_option('disco747_login_page_id', $existing_page->ID);
            
            // Imposta come homepage se non lo è già
            if (get_option('page_on_front') != $existing_page->ID) {
                update_option('page_on_front', $existing_page->ID);
                update_option('show_on_front', 'page');
                flush_rewrite_rules();
            }
        }
    }
    
    /**
     * Carica template personalizzato
     */
    public function load_login_template($template) {
        if (!is_front_page() && !is_page($this->get_login_page_id())) {
            return $template;
        }
        
        if (is_user_logged_in()) {
            wp_redirect(admin_url('admin.php?page=disco747-crm'));
            exit;
        }
        
        $plugin_template = DISCO747_CRM_PLUGIN_DIR . 'templates/frontend-login.php';
        
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return $template;
    }
    
    /**
     * Nascondi pagina login dai menu
     */
    public function hide_login_page($pages) {
        $login_page_id = $this->get_login_page_id();
        
        if (!$login_page_id) {
            return $pages;
        }
        
        foreach ($pages as $key => $page) {
            if ($page->ID == $login_page_id) {
                unset($pages[$key]);
            }
        }
        
        return $pages;
    }
    
    /**
     * Redirect dopo login
     */
    public function custom_login_redirect($redirect_to, $request, $user) {
        if (is_wp_error($user)) {
            return $redirect_to;
        }
        
        return admin_url('admin.php?page=disco747-crm');
    }
    
    /**
     * Redirect dopo logout
     */
    public function logout_redirect() {
        wp_redirect(home_url());
        exit;
    }
    
    /**
     * Limita accesso wp-admin
     */
    public function restrict_admin_access() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        if (current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['page']) && strpos($_GET['page'], 'disco747') === 0) {
            return;
        }
        
        if (!isset($_GET['page']) || strpos($_GET['page'], 'disco747') !== 0) {
            wp_redirect(home_url());
            exit;
        }
    }
    
    /**
     * Aggiungi body class
     */
    public function add_body_class($classes) {
        if (is_front_page() || is_page($this->get_login_page_id())) {
            $classes[] = 'disco747-login-page';
        }
        return $classes;
    }
    
    /**
     * Ottieni ID pagina login
     */
    private function get_login_page_id() {
        if ($this->page_id) {
            return $this->page_id;
        }
        
        $page_id = get_option('disco747_login_page_id');
        if ($page_id) {
            $this->page_id = $page_id;
            return $page_id;
        }
        
        $page = get_page_by_path($this->page_slug);
        if ($page) {
            $this->page_id = $page->ID;
            update_option('disco747_login_page_id', $page->ID);
            return $page->ID;
        }
        
        return null;
    }
    
    /**
     * Contenuto template login
     */
    private function get_template_content() {
        ob_start();
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - 747 Disco CRM</title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('disco747-frontend-login'); ?>>

<?php
$error_message = '';

if (isset($_POST['disco747_login_submit']) && isset($_POST['disco747_login_nonce'])) {
    if (!wp_verify_nonce($_POST['disco747_login_nonce'], 'disco747_login_action')) {
        $error_message = 'Errore di sicurezza. Ricarica la pagina.';
    } else {
        $credentials = array(
            'user_login'    => sanitize_text_field($_POST['log']),
            'user_password' => $_POST['pwd'],
            'remember'      => isset($_POST['rememberme'])
        );
        
        $user = wp_signon($credentials, is_ssl());
        
        if (is_wp_error($user)) {
            $error_message = 'Username o password non corretti.';
        } else {
            wp_safe_redirect(admin_url('admin.php?page=disco747-crm'));
            exit;
        }
    }
}
?>

<div class="disco747-login-wrapper">
    <div class="disco747-login-container">
        
        <div class="disco747-login-logo">
            <div class="disco747-logo-text">
                <span class="disco747-logo-number">747</span>
                <span class="disco747-logo-name">DISCO</span>
            </div>
        </div>
        
        <h2 class="disco747-login-title">Gestionale Preventivi</h2>
        
        <?php if ($error_message): ?>
            <div class="disco747-message disco747-error">
                ⚠️ <?php echo esc_html($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form name="loginform" id="disco747-loginform" method="post" class="disco747-login-form">
            
            <?php wp_nonce_field('disco747_login_action', 'disco747_login_nonce'); ?>
            
            <div class="disco747-form-group">
                <label for="user_login">👤 Username o Email</label>
                <input type="text" 
                       name="log" 
                       id="user_login" 
                       class="disco747-input" 
                       value="<?php echo isset($_POST['log']) ? esc_attr($_POST['log']) : ''; ?>" 
                       required 
                       autocomplete="username"
                       placeholder="Inserisci il tuo username">
            </div>
            
            <div class="disco747-form-group">
                <label for="user_pass">🔒 Password</label>
                <div class="disco747-password-field">
                    <input type="password" 
                           name="pwd" 
                           id="user_pass" 
                           class="disco747-input" 
                           required 
                           autocomplete="current-password"
                           placeholder="Inserisci la tua password">
                    <button type="button" class="disco747-toggle-password">👁️</button>
                </div>
            </div>
            
            <div class="disco747-form-options">
                <label class="disco747-checkbox">
                    <input name="rememberme" type="checkbox" id="rememberme" value="forever">
                    <span>Ricordami</span>
                </label>
                
                <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="disco747-forgot-link">
                    Password dimenticata?
                </a>
            </div>
            
            <button type="submit" name="disco747_login_submit" class="disco747-submit-btn">
                ➡️ Accedi al CRM
            </button>
            
        </form>
        
        <div class="disco747-login-footer">
            <p>Sistema di gestione preventivi</p>
            <p class="version">v11.4.2</p>
        </div>
        
    </div>
</div>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body.disco747-frontend-login {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #000;
}

.disco747-login-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: linear-gradient(135deg, #000 0%, #1a1a1a 50%, #000 100%);
}

.disco747-login-container {
    background: #fff;
    border-radius: 24px;
    padding: 50px 40px;
    max-width: 440px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(212, 175, 55, 0.25);
    position: relative;
}

.disco747-login-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, #d4af37 0%, #f4d03f 50%, #d4af37 100%);
    border-radius: 24px 24px 0 0;
}

.disco747-login-logo {
    text-align: center;
    margin-bottom: 30px;
}

.disco747-logo-text {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.disco747-logo-number {
    font-size: 56px;
    font-weight: 900;
    color: #000;
    letter-spacing: 3px;
}

.disco747-logo-name {
    font-size: 32px;
    font-weight: 700;
    background: linear-gradient(135deg, #d4af37, #f4d03f);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: 8px;
}

.disco747-login-title {
    text-align: center;
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin-bottom: 30px;
}

.disco747-message {
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
}

.disco747-error {
    background: #fee;
    color: #c33;
    border: 1px solid #fcc;
}

.disco747-form-group {
    margin-bottom: 20px;
}

.disco747-form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.disco747-input {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e5e5e5;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s;
    background: #fafafa;
}

.disco747-input:focus {
    outline: none;
    border-color: #d4af37;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
}

.disco747-password-field {
    position: relative;
}

.disco747-toggle-password {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
}

.disco747-form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    font-size: 14px;
}

.disco747-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.disco747-checkbox input {
    width: 18px;
    height: 18px;
    accent-color: #d4af37;
}

.disco747-forgot-link {
    color: #d4af37;
    text-decoration: none;
    font-weight: 500;
}

.disco747-forgot-link:hover {
    text-decoration: underline;
}

.disco747-submit-btn {
    width: 100%;
    padding: 16px 24px;
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    color: #000;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
}

.disco747-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
}

.disco747-login-footer {
    text-align: center;
    font-size: 13px;
    color: #999;
    padding-top: 20px;
    border-top: 1px solid #f0f0f0;
    margin-top: 20px;
}

.disco747-login-footer p {
    margin: 5px 0;
}

.version {
    font-size: 11px;
    color: #ccc;
}

@media (max-width: 480px) {
    .disco747-login-container {
        padding: 40px 25px;
    }
    
    .disco747-logo-number {
        font-size: 48px;
    }
    
    .disco747-logo-name {
        font-size: 28px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('.disco747-toggle-password');
    const passwordInput = document.getElementById('user_pass');
    
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            this.textContent = type === 'text' ? '🙈' : '👁️';
        });
    }
});
</script>

<?php wp_footer(); ?>
</body>
</html><?php
        return ob_get_clean();
    }
}

// Messaggio di conferma installazione
if (is_admin()) {
    add_action('admin_notices', function() {
        if (get_transient('disco747_login_installed')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>✅ 747 Disco Login:</strong> Sistema di login frontend installato e attivo sulla homepage!</p>';
            echo '</div>';
            delete_transient('disco747_login_installed');
        }
    });
    
    // Imposta transient alla prima esecuzione
    if (!get_option('disco747_login_activated')) {
        set_transient('disco747_login_installed', true, 60);
        update_option('disco747_login_activated', time());
    }
}