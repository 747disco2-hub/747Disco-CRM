<?php
/**
 * Template Pagina Impostazioni - 747 Disco CRM
 * VERSIONE PULITA - OAuth funzionante con UI ripristinata
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views  
 * @since      11.6.0
 * @version    11.6.0-CLEAN
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ottieni configurazioni esistenti
$company_name = get_option('disco747_company_name', '747 Disco');
$company_email = get_option('disco747_company_email', '');
$company_phone = get_option('disco747_company_phone', '');
$current_storage = get_option('disco747_storage_type', 'googledrive');

// Google Drive config
$gd_credentials = get_option('disco747_gd_credentials', array());
$gd_client_id = $gd_credentials['client_id'] ?? '';
$gd_client_secret = $gd_credentials['client_secret'] ?? '';

// URL redirect automatico basato sul sito
$site_url = get_site_url();
$gd_redirect_uri = $site_url . '/wp-admin/admin.php?page=disco747-settings&action=google_callback';
$gd_refresh_token = $gd_credentials['refresh_token'] ?? '';
$is_gd_configured = !empty($gd_refresh_token);

// Dropbox config (placeholder)
$dropbox_credentials = get_option('disco747_dropbox_credentials', array());
$dropbox_app_key = $dropbox_credentials['app_key'] ?? 'wzrjqtsjjypbha';
$dropbox_app_secret = $dropbox_credentials['app_secret'] ?? 'mf2effofks8iqsn';
$dropbox_redirect_url = $dropbox_credentials['redirect_url'] ?? admin_url('admin.php?page=disco747-settings');
$dropbox_refresh_token = $dropbox_credentials['refresh_token'] ?? '';
$is_dropbox_configured = !empty($dropbox_refresh_token);

// Gestione form submissions
if (isset($_POST['save_general_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'disco747_save_general')) {
    update_option('disco747_company_name', sanitize_text_field($_POST['company_name']));
    update_option('disco747_company_email', sanitize_email($_POST['company_email']));
    update_option('disco747_company_phone', sanitize_text_field($_POST['company_phone']));
    update_option('disco747_storage_type', sanitize_text_field($_POST['storage_type']));
    echo '<div class="notice notice-success is-dismissible"><p>✅ Impostazioni generali salvate!</p></div>';
}
?>

<div class="wrap" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;">
    
    <!-- Header principale -->
    <h1 style="color: #c28a4d; font-size: 2.2rem; margin-bottom: 20px;">
        ⚙️ Impostazioni 747 Disco CRM
    </h1>

    <!-- ======================================================================= -->
    <!-- IMPOSTAZIONI GENERALI -->
    <!-- ======================================================================= -->
    
    <div style="background: white; border-radius: 15px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 8px 25px rgba(43, 30, 26, 0.15);">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #90858a 100%); color: white; padding: 20px; font-weight: 600;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                🏢 Impostazioni Generali
            </h2>
        </div>
        
        <!-- Body -->
        <div style="padding: 25px; background: white;">
            <form method="post">
                <?php wp_nonce_field('disco747_save_general'); ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                    <div>
                        <label style="display: block; color: #2b1e1a; font-weight: 600; margin-bottom: 8px;">
                            Nome Azienda
                        </label>
                        <input type="text" name="company_name" 
                               value="<?php echo esc_attr($company_name); ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;" />
                    </div>
                    
                    <div>
                        <label style="display: block; color: #2b1e1a; font-weight: 600; margin-bottom: 8px;">
                            Email Aziendale
                        </label>
                        <input type="email" name="company_email" 
                               value="<?php echo esc_attr($company_email); ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;" />
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                    <div>
                        <label style="display: block; color: #2b1e1a; font-weight: 600; margin-bottom: 8px;">
                            Telefono
                        </label>
                        <input type="text" name="company_phone" 
                               value="<?php echo esc_attr($company_phone); ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;" />
                    </div>
                    
                    <div>
                        <label style="display: block; color: #2b1e1a; font-weight: 600; margin-bottom: 8px;">
                            Storage Predefinito
                        </label>
                        <select name="storage_type" 
                                style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                            <option value="googledrive" <?php selected($current_storage, 'googledrive'); ?>>📁 Google Drive</option>
                            <option value="dropbox" <?php selected($current_storage, 'dropbox'); ?>>📦 Dropbox</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="save_general_settings" 
                        style="background: linear-gradient(135deg, #c28a4d 0%, #90858a 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(43, 30, 26, 0.3);">
                    💾 Salva Impostazioni Generali
                </button>
            </form>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- CONFIGURAZIONE GOOGLE DRIVE -->
    <!-- ======================================================================= -->
    
    <div style="background: white; border-radius: 15px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 8px 25px rgba(43, 30, 26, 0.15);">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color: white; padding: 20px; font-weight: 600;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                📁 Configurazione Google Drive OAuth 2.0
            </h2>
        </div>
        
        <!-- Body -->
        <div style="padding: 25px; background: white;">
            
            <!-- Status Connessione -->
            <div style="background: <?php echo $is_gd_configured ? '#d4edda' : '#f8d7da'; ?>; padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid <?php echo $is_gd_configured ? '#28a745' : '#dc3545'; ?>;">
                <h4 style="margin: 0 0 10px 0; color: <?php echo $is_gd_configured ? '#155724' : '#721c24'; ?>;">
                    <?php echo $is_gd_configured ? '✅ Google Drive Configurato e Connesso' : '❌ Google Drive Non Configurato'; ?>
                </h4>
                <p style="margin: 0; color: <?php echo $is_gd_configured ? '#155724' : '#721c24'; ?>; font-size: 14px;">
                    <?php echo $is_gd_configured ? 'Connessione OAuth2 attiva. I preventivi vengono salvati automaticamente.' : 'Completa la configurazione OAuth2 per abilitare il salvataggio automatico.'; ?>
                </p>
                
                <?php if ($is_gd_configured): ?>
                    <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 5px;">
                        <strong>🔑 Token attivo:</strong> 
                        <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                            ••••<?php echo substr($gd_refresh_token, -8); ?>
                        </code>
                    </div>
                <?php endif; ?>
            </div>

            <!-- URL Redirect Automatico -->
            <div style="background: #e3f2fd; padding: 15px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid #2196f3;">
                <h4 style="color: #1976d2; margin: 0 0 10px 0;">🔗 URL Redirect (Copia in Google Cloud Console)</h4>
                <div style="background: white; padding: 10px; border-radius: 5px; font-family: 'Courier New', monospace; font-size: 12px; word-break: break-all; border: 2px dashed #2196f3;">
                    <strong><?php echo esc_html($gd_redirect_uri); ?></strong>
                </div>
                <button type="button" onclick="copyToClipboard('<?php echo esc_js($gd_redirect_uri); ?>')" 
                        style="background: #2196f3; color: white; padding: 8px 16px; border: none; border-radius: 5px; font-weight: 600; cursor: pointer; margin-top: 10px;">
                    📋 Copia URL
                </button>
                <p style="color: #1976d2; font-size: 13px; margin: 10px 0 0 0;">
                    <strong>📝 Istruzioni:</strong> Vai su Google Cloud Console → Credentials → OAuth 2.0 Client → "Authorized redirect URIs" → Aggiungi questo URL
                </p>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('disco747_save_gd'); ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                    <div>
                        <label style="display: block; color: #2b1e1a; font-weight: 600; margin-bottom: 8px;">
                            Client ID *
                        </label>
                        <input type="text" name="gd_client_id" 
                               value="<?php echo esc_attr($gd_client_id); ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 12px; font-family: 'Courier New', monospace;" 
                               placeholder="123456789-abcdef.apps.googleusercontent.com"
                               required />
                    </div>
                    
                    <div>
                        <label style="display: block; color: #2b1e1a; font-weight: 600; margin-bottom: 8px;">
                            Client Secret *
                        </label>
                        <input type="password" name="gd_client_secret" 
                               value="<?php echo esc_attr($gd_client_secret); ?>" 
                               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 12px; font-family: 'Courier New', monospace;" 
                               placeholder="GOCSPX-abcdef123456"
                               required />
                    </div>
                </div>
                
                <!-- Pulsanti azione -->
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <button type="submit" name="save_gd_settings" 
                            style="background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);">
                        💾 Salva Configurazione
                    </button>
                    
                    <?php if (!empty($gd_client_id) && !empty($gd_client_secret)): ?>
                        <?php if (!$is_gd_configured): ?>
                            <button type="button" class="btn-authorize-googledrive"
                                    style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);">
                                🔗 Autorizza Accesso Google Drive
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-test-googledrive"
                                    style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);">
                                🔬 Test Connessione
                            </button>
                            
                            <a href="<?php echo admin_url('admin.php?page=disco747-googledrive-files'); ?>" 
                               style="display: inline-block; background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; text-decoration: none; box-shadow: 0 4px 12px rgba(111, 66, 193, 0.3);">
                                📂 Visualizza File Drive
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #dc3545; font-weight: 600; margin: 0; padding: 15px 0;">
                            ⚠️ Inserisci prima Client ID e Client Secret per procedere.
                        </p>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- CONFIGURAZIONE DROPBOX -->
    <!-- ======================================================================= -->
    
    <div style="background: white; border-radius: 15px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 8px 25px rgba(43, 30, 26, 0.15);">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #0061ff 0%, #004acc 100%); color: white; padding: 20px; font-weight: 600;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                📦 Configurazione Dropbox OAuth 2.0
            </h2>
        </div>
        
        <!-- Body -->
        <div style="padding: 25px; background: white;">
            
            <!-- Status Connessione -->
            <div style="background: <?php echo $is_dropbox_configured ? '#d4edda' : '#f8d7da'; ?>; padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid <?php echo $is_dropbox_configured ? '#28a745' : '#dc3545'; ?>;">
                <h4 style="margin: 0 0 10px 0; color: <?php echo $is_dropbox_configured ? '#155724' : '#721c24'; ?>;">
                    <?php echo $is_dropbox_configured ? '✅ Dropbox Configurato' : '❌ Dropbox Non Configurato'; ?>
                </h4>
                <p style="margin: 0; color: <?php echo $is_dropbox_configured ? '#155724' : '#721c24'; ?>; font-size: 14px;">
                    <?php echo $is_dropbox_configured ? 'Connessione attiva e funzionante.' : 'Funzionalità in sviluppo. Utilizza Google Drive.'; ?>
                </p>
            </div>

            <!-- Avviso funzionalità -->
            <div style="background: #fff3cd; padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid #ffc107;">
                <h4 style="color: #856404; margin: 0 0 10px 0;">⚠️ Funzionalità in Sviluppo</h4>
                <p style="margin: 0; color: #856404; font-size: 14px;">
                    L'integrazione Dropbox sarà disponibile nei prossimi aggiornamenti. 
                    Utilizza Google Drive per il salvataggio automatico.
                </p>
            </div>
        </div>
    </div>

    <!-- ======================================================================= -->
    <!-- GUIDA RAPIDA -->
    <!-- ======================================================================= -->
    
    <div style="background: #f8f9fa; border-radius: 15px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white; padding: 15px; font-weight: 600;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                📚 Guida Rapida Google Drive
            </h3>
        </div>
        
        <div style="padding: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                
                <!-- Passi -->
                <div>
                    <h4 style="color: #2b1e1a; margin: 0 0 10px 0;">📋 Passi da Seguire:</h4>
                    <ol style="margin: 0; padding-left: 20px; color: #2b1e1a; font-size: 14px; line-height: 1.6;">
                        <li>Vai su <a href="https://console.cloud.google.com" target="_blank" style="color: #1976d2;">Google Cloud Console</a></li>
                        <li>Crea progetto o seleziona esistente</li>
                        <li>Abilita "Google Drive API"</li>
                        <li>Crea credenziali "OAuth 2.0 Client ID"</li>
                        <li>Copia l'URL redirect dal box blu sopra</li>
                        <li>Incolla in "Authorized redirect URIs"</li>
                        <li>Inserisci Client ID e Secret qui</li>
                        <li>Salva e autorizza l'accesso</li>
                    </ol>
                </div>
                
                <!-- Link -->
                <div>
                    <h4 style="color: #2b1e1a; margin: 0 0 10px 0;">🔗 Link Utili:</h4>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="https://console.cloud.google.com" target="_blank" 
                           style="background: #1976d2; color: white; padding: 10px 15px; text-decoration: none; border-radius: 8px; font-weight: 600; text-align: center;">
                            🌐 Google Cloud Console
                        </a>
                        <a href="https://developers.google.com/drive/api" target="_blank" 
                           style="background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 8px; font-weight: 600; text-align: center;">
                            📖 Documentazione Drive API
                        </a>
                        <?php if ($is_gd_configured): ?>
                            <a href="<?php echo admin_url('admin.php?page=disco747-googledrive-files'); ?>" 
                               style="background: #6f42c1; color: white; padding: 10px 15px; text-decoration: none; border-radius: 8px; font-weight: 600; text-align: center;">
                                📂 File Google Drive
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- JavaScript per copia URL e interazioni -->
<script>
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            alert('✅ URL copiato negli appunti! Incollalo in Google Cloud Console → Credentials → OAuth 2.0 Client → Authorized redirect URIs');
        });
    } else {
        // Fallback per browser vecchi
        var textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('✅ URL copiato! Incollalo in Google Cloud Console');
    }
}

// Effetti hover sui pulsanti
document.addEventListener('DOMContentLoaded', function() {
    var buttons = document.querySelectorAll('button[style*="linear-gradient"], a[style*="linear-gradient"]');
    buttons.forEach(function(button) {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<style>
/* Focus sui form */
input[type="text"]:focus, 
input[type="email"]:focus, 
input[type="url"]:focus, 
input[type="password"]:focus,
select:focus {
    border-color: #c28a4d !important;
    outline: none;
    box-shadow: 0 0 0 3px rgba(194, 138, 77, 0.1);
}

/* Hover sui pulsanti */
button:hover, a:hover {
    transform: translateY(-2px) !important;
}

button:active, a:active {
    transform: translateY(0) !important;
}

/* Notice styling */
.notice {
    margin: 15px 0 !important;
}
</style>