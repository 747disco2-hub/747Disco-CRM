<?php
/**
 * Handler AJAX per 2FA - 747 Disco CRM
 * Aggiungi questo codice al file class-disco747-ajax.php o crea un nuovo file
 * 
 * @package Disco747_CRM
 * @version 1.0.0-2FA
 */

// Questi handler vanno aggiunti alla classe esistente o creati come funzioni standalone

/**
 * Handler AJAX: Verifica credenziali e controlla se serve 2FA
 */
add_action('wp_ajax_nopriv_disco747_check_credentials', 'disco747_ajax_check_credentials');
function disco747_ajax_check_credentials() {
    // Verifica nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'disco747_login_action')) {
        wp_send_json_error('Nonce non valido');
    }
    
    $username = sanitize_text_field($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        wp_send_json_error('Username e password richiesti');
    }
    
    // Verifica credenziali WordPress
    $user = wp_authenticate($username, $password);
    
    if (is_wp_error($user)) {
        wp_send_json_error('Username o password non corretti');
    }
    
    // Carica classe 2FA
    require_once WP_PLUGIN_DIR . '/747disco-crm/includes/core/class-disco747-twofactor.php';
    use Disco747_CRM\Core\Disco747_TwoFactor;
    
    // Controlla se ha 2FA attivo
    $requires_2fa = Disco747_TwoFactor::is_enabled_for_user($user->ID);
    
    // Controlla se dispositivo è trusted
    $is_trusted = false;
    if ($requires_2fa) {
        $is_trusted = Disco747_TwoFactor::is_trusted_device($user->ID);
    }
    
    if ($requires_2fa && !$is_trusted) {
        // Serve codice OTP
        wp_send_json_success(array(
            'requires_2fa' => true,
            'message' => 'Inserisci il codice dal tuo Google Authenticator'
        ));
    } else {
        // Login diretto (no 2FA o dispositivo trusted)
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, false);
        
        wp_send_json_success(array(
            'requires_2fa' => false,
            'message' => 'Login effettuato'
        ));
    }
}

/**
 * Handler AJAX: Verifica codice OTP e completa login
 */
add_action('wp_ajax_nopriv_disco747_verify_2fa', 'disco747_ajax_verify_2fa');
function disco747_ajax_verify_2fa() {
    // Verifica nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'disco747_login_action')) {
        wp_send_json_error('Nonce non valido');
    }
    
    $username = sanitize_text_field($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $otp_code = sanitize_text_field($_POST['otp_code'] ?? '');
    $trust_device = !empty($_POST['trust_device']);
    
    if (empty($username) || empty($password) || empty($otp_code)) {
        wp_send_json_error('Dati mancanti');
    }
    
    // Verifica credenziali
    $user = wp_authenticate($username, $password);
    
    if (is_wp_error($user)) {
        wp_send_json_error('Credenziali non valide');
    }
    
    // Carica classe 2FA
    require_once WP_PLUGIN_DIR . '/747disco-crm/includes/core/class-disco747-twofactor.php';
    use Disco747_CRM\Core\Disco747_TwoFactor;
    
    // Ottieni secret utente
    $secret = Disco747_TwoFactor::get_user_secret($user->ID);
    
    if (!$secret) {
        wp_send_json_error('2FA non configurato per questo utente');
    }
    
    // Verifica codice OTP
    if (!Disco747_TwoFactor::verify_code($secret, $otp_code)) {
        wp_send_json_error('Codice OTP non valido o scaduto');
    }
    
    // Codice valido! Effettua login
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, false);
    
    // Se richiesto, ricorda dispositivo
    if ($trust_device) {
        Disco747_TwoFactor::set_trusted_device($user->ID);
    }
    
    wp_send_json_success(array(
        'message' => 'Autenticazione completata',
        'user_id' => $user->ID
    ));
}

/**
 * Handler AJAX: Verifica codice di backup
 */
add_action('wp_ajax_nopriv_disco747_verify_backup_code', 'disco747_ajax_verify_backup_code');
function disco747_ajax_verify_backup_code() {
    // Verifica nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'disco747_login_action')) {
        wp_send_json_error('Nonce non valido');
    }
    
    $username = sanitize_text_field($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $backup_code = sanitize_text_field($_POST['backup_code'] ?? '');
    
    if (empty($username) || empty($password) || empty($backup_code)) {
        wp_send_json_error('Dati mancanti');
    }
    
    // Verifica credenziali
    $user = wp_authenticate($username, $password);
    
    if (is_wp_error($user)) {
        wp_send_json_error('Credenziali non valide');
    }
    
    // Carica classe 2FA
    require_once WP_PLUGIN_DIR . '/747disco-crm/includes/core/class-disco747-twofactor.php';
    use Disco747_CRM\Core\Disco747_TwoFactor;
    
    // Verifica backup code
    if (!Disco747_TwoFactor::verify_backup_code($user->ID, $backup_code)) {
        wp_send_json_error('Codice di backup non valido o già utilizzato');
    }
    
    // Codice valido! Effettua login
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, false);
    
    wp_send_json_success(array(
        'message' => 'Autenticazione completata con codice di backup',
        'user_id' => $user->ID
    ));
}

/**
 * Handler AJAX: Login diretto (senza 2FA)
 */
add_action('wp_ajax_nopriv_disco747_direct_login', 'disco747_ajax_direct_login');
function disco747_ajax_direct_login() {
    // Verifica nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'disco747_login_action')) {
        wp_send_json_error('Nonce non valido');
    }
    
    $username = sanitize_text_field($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        wp_send_json_error('Username e password richiesti');
    }
    
    // Verifica credenziali
    $user = wp_authenticate($username, $password);
    
    if (is_wp_error($user)) {
        wp_send_json_error('Username o password non corretti');
    }
    
    // Effettua login
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $remember);
    
    wp_send_json_success(array(
        'message' => 'Login effettuato',
        'user_id' => $user->ID
    ));
}