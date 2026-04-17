<?php
/**
 * Pagina Setup 2FA - 747 Disco CRM
 * 
 * Questa pagina va aggiunta al menu admin del plugin
 * Gli utenti possono abilitare/disabilitare Google Authenticator
 * 
 * @package Disco747_CRM
 * @version 1.0.0-2FA
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Solo utenti loggati
if (!is_user_logged_in()) {
    wp_die('Accesso negato');
}

$current_user = wp_get_current_user();

// Carica classe 2FA
require_once WP_PLUGIN_DIR . '/747disco-crm/includes/core/class-disco747-twofactor.php';
use Disco747_CRM\Core\Disco747_TwoFactor;

// Gestione form
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verifica nonce
    if (!wp_verify_nonce($_POST['disco747_2fa_nonce'] ?? '', 'disco747_2fa_action')) {
        $message = 'Sicurezza: nonce non valido';
        $message_type = 'error';
    } else {
        
        $action = $_POST['action'] ?? '';
        
        // ABILITA 2FA
        if ($action === 'enable_2fa') {
            $otp_code = sanitize_text_field($_POST['otp_code'] ?? '');
            $temp_secret = sanitize_text_field($_POST['temp_secret'] ?? '');
            
            if (empty($otp_code)) {
                $message = 'Inserisci il codice da Google Authenticator';
                $message_type = 'error';
            } elseif (Disco747_TwoFactor::verify_code($temp_secret, $otp_code)) {
                // Codice valido! Abilita 2FA
                Disco747_TwoFactor::enable_for_user($current_user->ID, $temp_secret);
                $message = 'Google Authenticator abilitato con successo! Salva i codici di backup.';
                $message_type = 'success';
            } else {
                $message = 'Codice non valido. Riprova.';
                $message_type = 'error';
            }
        }
        
        // DISABILITA 2FA
        elseif ($action === 'disable_2fa') {
            $password = $_POST['confirm_password'] ?? '';
            
            if (empty($password)) {
                $message = 'Inserisci la password per confermare';
                $message_type = 'error';
            } elseif (wp_check_password($password, $current_user->user_pass, $current_user->ID)) {
                Disco747_TwoFactor::disable_for_user($current_user->ID);
                $message = 'Google Authenticator disabilitato';
                $message_type = 'success';
            } else {
                $message = 'Password non corretta';
                $message_type = 'error';
            }
        }
        
        // RIGENERA BACKUP CODES
        elseif ($action === 'regenerate_backup') {
            $new_codes = Disco747_TwoFactor::generate_backup_codes();
            update_user_meta($current_user->ID, Disco747_TwoFactor::BACKUP_CODES_META_KEY, $new_codes);
            $message = 'Nuovi codici di backup generati';
            $message_type = 'success';
        }
        
        // RIMUOVI DISPOSITIVI TRUSTED
        elseif ($action === 'remove_devices') {
            Disco747_TwoFactor::remove_all_trusted_devices($current_user->ID);
            $message = 'Tutti i dispositivi trusted rimossi';
            $message_type = 'success';
        }
    }
}

// Stato 2FA
$is_enabled = Disco747_TwoFactor::is_enabled_for_user($current_user->ID);
$secret = Disco747_TwoFactor::get_user_secret($current_user->ID);
$backup_codes = Disco747_TwoFactor::get_backup_codes($current_user->ID);

// Se sta abilitando, genera secret temporaneo
$temp_secret = '';
if (!$is_enabled && empty($_POST['temp_secret'])) {
    $temp_secret = Disco747_TwoFactor::generate_secret();
}

?>

<style>
    .disco747-2fa-wrap {
        max-width: 900px;
        margin: 20px 0;
    }
    
    .disco747-2fa-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .disco747-2fa-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 2px solid #c28a4d;
    }
    
    .disco747-2fa-icon {
        font-size: 3rem;
    }
    
    .disco747-2fa-title h1 {
        margin: 0;
        font-size: 1.8rem;
        color: #2b1e1a;
    }
    
    .disco747-2fa-title p {
        margin: 5px 0 0 0;
        color: #666;
    }
    
    .disco747-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .disco747-status-badge.active {
        background: #d4edda;
        color: #155724;
    }
    
    .disco747-status-badge.inactive {
        background: #f8d7da;
        color: #721c24;
    }
    
    .disco747-qr-section {
        text-align: center;
        padding: 30px;
        background: #f8f9fa;
        border-radius: 10px;
        margin: 20px 0;
    }
    
    .disco747-qr-section img {
        border: 3px solid #c28a4d;
        border-radius: 10px;
        padding: 10px;
        background: white;
    }
    
    .disco747-secret-box {
        background: #fff3cd;
        border: 2px dashed #ffc107;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        margin: 20px 0;
    }
    
    .disco747-secret-box code {
        font-size: 1.3rem;
        letter-spacing: 0.2rem;
        color: #856404;
        font-weight: 600;
    }
    
    .disco747-backup-codes {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin: 20px 0;
    }
    
    .disco747-backup-code {
        background: #f8f9fa;
        padding: 12px;
        text-align: center;
        border-radius: 8px;
        border: 2px solid #e0e0e0;
        font-family: 'Courier New', monospace;
        font-size: 0.95rem;
        font-weight: 600;
    }
    
    .disco747-form-group {
        margin-bottom: 20px;
    }
    
    .disco747-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2b1e1a;
    }
    
    .disco747-form-group input[type="text"],
    .disco747-form-group input[type="password"] {
        width: 100%;
        max-width: 300px;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 1rem;
    }
    
    .disco747-btn {
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1rem;
    }
    
    .disco747-btn-primary {
        background: linear-gradient(135deg, #c28a4d 0%, #d4a574 100%);
        color: white;
    }
    
    .disco747-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(194, 138, 77, 0.3);
    }
    
    .disco747-btn-danger {
        background: #dc3545;
        color: white;
    }
    
    .disco747-btn-danger:hover {
        background: #c82333;
    }
    
    .disco747-btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .disco747-alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .disco747-alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .disco747-alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .disco747-alert-info {
        background: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }
    
    .disco747-steps {
        counter-reset: step-counter;
        list-style: none;
        padding: 0;
    }
    
    .disco747-steps li {
        counter-increment: step-counter;
        margin-bottom: 20px;
        padding-left: 50px;
        position: relative;
    }
    
    .disco747-steps li::before {
        content: counter(step-counter);
        position: absolute;
        left: 0;
        top: 0;
        width: 35px;
        height: 35px;
        background: #c28a4d;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
    }
</style>

<div class="wrap disco747-2fa-wrap">
    
    <div class="disco747-2fa-card">
        <div class="disco747-2fa-header">
            <div class="disco747-2fa-icon">🔐</div>
            <div class="disco747-2fa-title">
                <h1>Autenticazione a Due Fattori</h1>
                <p>Google Authenticator per 747 Disco CRM</p>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="disco747-alert disco747-alert-<?php echo $message_type; ?>">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 20px;">
            <strong>Stato:</strong>
            <span class="disco747-status-badge <?php echo $is_enabled ? 'active' : 'inactive'; ?>">
                <?php echo $is_enabled ? '✓ Attivo' : '✗ Non attivo'; ?>
            </span>
        </div>
        
        <?php if (!$is_enabled): ?>
            <!-- SETUP 2FA -->
            <div class="disco747-2fa-card" style="background: #f8f9fa;">
                <h2 style="margin-top: 0;">📱 Configura Google Authenticator</h2>
                
                <ol class="disco747-steps">
                    <li>
                        <strong>Scarica Google Authenticator</strong><br>
                        Disponibile su <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">Android</a> e 
                        <a href="https://apps.apple.com/it/app/google-authenticator/id388497605" target="_blank">iOS</a>
                    </li>
                    <li>
                        <strong>Scansiona questo QR Code</strong><br>
                        <div class="disco747-qr-section">
                            <img src="<?php echo Disco747_TwoFactor::get_qr_code_url($temp_secret, $current_user->user_login); ?>" 
                                 alt="QR Code" />
                        </div>
                        <div class="disco747-secret-box">
                            <small>Oppure inserisci manualmente:</small><br>
                            <code><?php echo esc_html($temp_secret); ?></code>
                        </div>
                    </li>
                    <li>
                        <strong>Inserisci il codice a 6 cifre</strong><br>
                        <form method="post">
                            <div class="disco747-form-group">
                                <input 
                                    type="text" 
                                    name="otp_code" 
                                    placeholder="000000" 
                                    maxlength="6" 
                                    pattern="[0-9]{6}"
                                    required
                                    style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem;"
                                />
                            </div>
                            <input type="hidden" name="action" value="enable_2fa">
                            <input type="hidden" name="temp_secret" value="<?php echo esc_attr($temp_secret); ?>">
                            <?php wp_nonce_field('disco747_2fa_action', 'disco747_2fa_nonce'); ?>
                            <button type="submit" class="disco747-btn disco747-btn-primary">
                                ✓ Attiva 2FA
                            </button>
                        </form>
                    </li>
                </ol>
            </div>
            
        <?php else: ?>
            <!-- 2FA ATTIVO -->
            <div class="disco747-2fa-card" style="background: #d4edda;">
                <h2 style="margin-top: 0; color: #155724;">✅ Google Authenticator Attivo</h2>
                <p style="color: #155724;">Il tuo account è protetto con autenticazione a due fattori.</p>
            </div>
            
            <!-- Codici di Backup -->
            <div class="disco747-2fa-card">
                <h2 style="margin-top: 0;">🔑 Codici di Backup</h2>
                <p>Usa questi codici se non hai accesso a Google Authenticator. <strong>Ogni codice può essere usato una sola volta.</strong></p>
                
                <?php if (!empty($backup_codes)): ?>
                    <div class="disco747-backup-codes">
                        <?php foreach ($backup_codes as $code): ?>
                            <div class="disco747-backup-code"><?php echo esc_html($code); ?></div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="disco747-alert disco747-alert-info">
                        <strong>⚠️ Importante:</strong> Salva questi codici in un posto sicuro. Ti serviranno se perdi il telefono.
                    </div>
                    
                    <form method="post" style="margin-top: 20px;">
                        <input type="hidden" name="action" value="regenerate_backup">
                        <?php wp_nonce_field('disco747_2fa_action', 'disco747_2fa_nonce'); ?>
                        <button type="submit" class="disco747-btn disco747-btn-secondary">
                            🔄 Rigenera Codici
                        </button>
                    </form>
                <?php else: ?>
                    <p>Nessun codice di backup disponibile.</p>
                <?php endif; ?>
            </div>
            
            <!-- Dispositivi Trusted -->
            <div class="disco747-2fa-card">
                <h2 style="margin-top: 0;">📱 Dispositivi Fidati</h2>
                <p>Se hai selezionato "Ricorda questo dispositivo" durante il login, puoi rimuovere tutti i dispositivi trusted qui.</p>
                
                <form method="post" onsubmit="return confirm('Sei sicuro? Dovrai inserire il codice OTP su tutti i dispositivi.');">
                    <input type="hidden" name="action" value="remove_devices">
                    <?php wp_nonce_field('disco747_2fa_action', 'disco747_2fa_nonce'); ?>
                    <button type="submit" class="disco747-btn disco747-btn-secondary">
                        🗑️ Rimuovi Tutti i Dispositivi
                    </button>
                </form>
            </div>
            
            <!-- Disabilita 2FA -->
            <div class="disco747-2fa-card" style="border: 2px solid #dc3545;">
                <h2 style="margin-top: 0; color: #dc3545;">🔓 Disabilita 2FA</h2>
                <p>Inserisci la tua password per disabilitare Google Authenticator.</p>
                
                <form method="post" onsubmit="return confirm('Sei sicuro di voler disabilitare la protezione 2FA?');">
                    <div class="disco747-form-group">
                        <label>Password Conferma:</label>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            required 
                            placeholder="Inserisci la tua password"
                        />
                    </div>
                    <input type="hidden" name="action" value="disable_2fa">
                    <?php wp_nonce_field('disco747_2fa_action', 'disco747_2fa_nonce'); ?>
                    <button type="submit" class="disco747-btn disco747-btn-danger">
                        ✗ Disabilita 2FA
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
    </div>
    
</div>