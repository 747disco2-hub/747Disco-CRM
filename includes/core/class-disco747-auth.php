<?php
/**
 * Classe per gestione autenticazione del plugin 747 Disco CRM - VERSIONE SEMPLIFICATA
 * 
 * @package    Disco747_CRM
 * @subpackage Core
 * @since      11.4
 * @version    11.5.0
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Core;

use Exception;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_Auth - VERSIONE SEMPLIFICATA SENZA ERRORI
 * 
 * Gestisce l'autenticazione degli utenti per il plugin
 * Utilizza il sistema di autenticazione WordPress invece di sessioni custom
 * 
 * @since 11.4
 */
class Disco747_Auth {

    /**
     * Configurazione plugin
     * 
     * @var Disco747_Config
     */
    private $config;

    /**
     * Database manager
     * 
     * @var Disco747_Database
     */
    private $database;

    /**
     * Utente corrente
     * 
     * @var array
     */
    private $current_user = null;

    /**
     * Flag per indicare se l'utente è loggato
     * 
     * @var bool
     */
    private $is_logged_in = false;

    /**
     * ID sessione corrente
     * 
     * @var string
     */
    private $session_id = null;

    /**
     * Permessi per ruoli
     * 
     * @var array
     */
    private $roles_permissions = array(
        'admin' => array(
            'create_preventivo',
            'edit_preventivo',
            'delete_preventivo',
            'view_dashboard',
            'manage_settings',
            'manage_users',
            'access_admin'
        ),
        'staff' => array(
            'create_preventivo',
            'edit_preventivo',
            'view_dashboard'
        )
    );

    /**
     * Costruttore
     * 
     * @since 11.4
     */
    public function __construct() {
        $this->config = Disco747_Config::get_instance();
        $this->database = new Disco747_Database();
        
        $this->init_auth();
    }

    /**
     * Inizializza il sistema di autenticazione - VERSIONE SEMPLIFICATA
     * 
     * @since 11.4
     */
    private function init_auth() {
        // Hook WordPress (solo per logout e Ajax)
        add_action('init', array($this, 'handle_logout_request'));
        
        // RIMOSSO: add_action('wp_loaded', array($this, 'check_session')); - CAUSAVA L'ERRORE
        
        // Ajax handlers per login (opzionali)
        add_action('wp_ajax_disco747_crm_login', array($this, 'handle_ajax_login'));
        add_action('wp_ajax_nopriv_disco747_crm_login', array($this, 'handle_ajax_login'));
        
        // Ajax handlers per logout
        add_action('wp_ajax_disco747_crm_logout', array($this, 'handle_ajax_logout'));
        add_action('wp_ajax_nopriv_disco747_crm_logout', array($this, 'handle_ajax_logout'));
        
        // Avvia sessione se necessario
        $this->start_session();
        
        $this->log('Sistema autenticazione inizializzato');
    }

    /**
     * Avvia sessione PHP se necessario
     * 
     * @since 11.4
     */
    private function start_session() {
        if (!is_admin() && !headers_sent() && session_status() === PHP_SESSION_NONE) {
            session_start();
            
            // Genera ID sessione se non esiste
            if (!isset($_SESSION['disco747_session_id'])) {
                $_SESSION['disco747_session_id'] = $this->generate_session_id();
            }
            
            $this->session_id = $_SESSION['disco747_session_id'];
        }
    }

    /**
     * Gestisce richiesta di login via Ajax
     * 
     * @since 11.4
     */
    public function handle_ajax_login() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'disco747_crm_login_nonce')) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Sicurezza: nonce non valido'
            )));
        }

        $username = sanitize_user($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'];

        if (empty($username) || empty($password)) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Username e password sono obbligatori'
            )));
        }

        $result = $this->login($username, $password, $remember);
        
        wp_die(json_encode($result));
    }

    /**
     * Gestisce richiesta di logout via Ajax
     * 
     * @since 11.4
     */
    public function handle_ajax_logout() {
        $result = $this->logout();
        wp_die(json_encode($result));
    }

    /**
     * Gestisce logout via URL
     * 
     * @since 11.4
     */
    public function handle_logout_request() {
        if (isset($_GET['disco747_logout']) && $_GET['disco747_logout'] === '1') {
            if (wp_verify_nonce($_GET['nonce'] ?? '', 'disco747_logout_nonce')) {
                $this->logout();
                wp_redirect(remove_query_arg(array('disco747_logout', 'nonce')));
                exit;
            }
        }
    }

    /**
     * Effettua login dell'utente - VERSIONE SEMPLIFICATA
     * 
     * @param string $username Username
     * @param string $password Password
     * @param bool $remember Se ricordare il login
     * @return array Risultato del login
     * @since 11.4
     */
    public function login($username, $password, $remember = false) {
        // Primo: prova con utente WordPress
        $wp_user = wp_authenticate($username, $password);
        
        if (!is_wp_error($wp_user) && $wp_user instanceof \WP_User) {
            // Login WordPress riuscito
            wp_set_current_user($wp_user->ID);
            wp_set_auth_cookie($wp_user->ID, $remember);
            
            $this->current_user = array(
                'id' => $wp_user->ID,
                'username' => $wp_user->user_login,
                'display_name' => $wp_user->display_name,
                'role' => 'admin', // Gli utenti WP con manage_options sono sempre admin
                'email' => $wp_user->user_email
            );
            $this->is_logged_in = true;
            
            return array(
                'success' => true,
                'message' => 'Login effettuato con successo',
                'user' => $this->current_user
            );
        }

        // Secondo: prova con utenti custom (se implementato)
        // Per ora saltiamo questa parte per evitare errori

        return array(
            'success' => false,
            'message' => 'Username o password non corretti'
        );
    }

    /**
     * Effettua logout dell'utente - VERSIONE SEMPLIFICATA
     * 
     * @return array Risultato del logout
     * @since 11.4
     */
    public function logout() {
        // Logout WordPress
        wp_logout();
        
        // Pulisci sessione PHP
        if (isset($_SESSION['disco747_session_id'])) {
            unset($_SESSION['disco747_session_id']);
        }

        // Reset variabili
        $this->current_user = null;
        $this->is_logged_in = false;
        $this->session_id = null;

        $this->log('Logout effettuato');

        return array(
            'success' => true,
            'message' => 'Logout effettuato con successo'
        );
    }

    /**
     * Verifica se l'utente è loggato - USA WORDPRESS
     * 
     * @return bool True se loggato
     * @since 11.4
     */
    public function is_user_logged_in() {
        return is_user_logged_in(); // Usa la funzione WordPress
    }

    /**
     * Ottiene l'utente corrente - USA WORDPRESS
     * 
     * @return array|null Dati utente o null se non loggato
     * @since 11.4
     */
    public function get_current_user() {
        if (!is_user_logged_in()) {
            return null;
        }
        
        $wp_user = wp_get_current_user();
        
        return array(
            'id' => $wp_user->ID,
            'username' => $wp_user->user_login,
            'display_name' => $wp_user->display_name,
            'role' => current_user_can('manage_options') ? 'admin' : 'staff',
            'email' => $wp_user->user_email
        );
    }

    /**
     * Verifica se l'utente ha un permesso specifico
     * 
     * @param string $permission Permesso da verificare
     * @return bool True se ha il permesso
     * @since 11.4
     */
    public function check_permissions($permission) {
        if (!is_user_logged_in()) {
            return false;
        }

        // Gli admin WordPress hanno tutti i permessi
        if (current_user_can('manage_options')) {
            return true;
        }

        // Verifica permessi custom per utenti con manage_options
        $user = $this->get_current_user();
        if (!$user) {
            return false;
        }

        $user_role = $user['role'];
        
        return isset($this->roles_permissions[$user_role]) && 
               in_array($permission, $this->roles_permissions[$user_role]);
    }

    /**
     * Alias per check_permissions - per compatibilità
     * 
     * @param string $permission Permesso da verificare
     * @return bool True se ha il permesso
     * @since 11.5.0
     */
    public function current_user_can($permission) {
        return $this->check_permissions($permission);
    }

    /**
     * Verifica se l'utente può accedere all'admin
     * 
     * @return bool True se può accedere
     * @since 11.4
     */
    public function can_access_admin() {
        // Gli utenti WordPress admin possono sempre accedere
        if (current_user_can('manage_options')) {
            return true;
        }

        // Verifica permessi custom
        return $this->check_permissions('access_admin');
    }

    /**
     * Genera ID sessione univoco
     * 
     * @return string ID sessione
     * @since 11.4
     */
    private function generate_session_id() {
        return 'disco747_' . wp_generate_password(32, false);
    }

    /**
     * Ottiene permessi per ruolo
     * 
     * @param string $role Nome del ruolo
     * @return array Lista permessi
     * @since 11.4
     */
    public function get_role_permissions($role) {
        return isset($this->roles_permissions[$role]) ? 
               $this->roles_permissions[$role] : array();
    }

    /**
     * Verifica se un ruolo esiste
     * 
     * @param string $role Nome del ruolo
     * @return bool True se esiste
     * @since 11.4
     */
    public function role_exists($role) {
        return isset($this->roles_permissions[$role]);
    }

    /**
     * Log helper - usa sistema logging globale
     */
    private function log($message, $level = 'INFO') {
        try {
            // Usa funzione globale di logging del plugin
            if (function_exists('disco747_log')) {
                disco747_log('[Auth] ' . $message, $level);
            } else {
                // Fallback: error_log diretto
                $timestamp = date('Y-m-d H:i:s');
                error_log("[{$timestamp}] [747Disco-CRM-Auth] [{$level}] {$message}");
            }
        } catch (\Exception $e) {
            // Fallback sicuro
            error_log('[747 Disco CRM Auth] ' . $message);
        }
    }
}