<?php
/**
 * Pagina configurazione Google Drive OAuth
 * 
 * @package Disco747_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ottieni credenziali salvate
$client_id = get_option('disco747_google_client_id', '');
$client_secret = get_option('disco747_google_client_secret', '');
$google_token = get_option('disco747_google_token', '');
$is_connected = !empty($google_token);

// Redirect URI fisso
$redirect_uri = admin_url('admin.php?page=disco747-settings&action=google_callback');

?>

<div class="wrap">
    <h1>⚙️ Configurazione Google Drive</h1>
    
    <div style="background: white; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        
        <?php if (!$is_connected): ?>
        
        <!-- STEP 1: Credenziali OAuth -->
        <div style="margin-bottom: 30px;">
            <h2>📋 Step 1: Credenziali Google OAuth</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('disco747_google_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="client_id">Client ID</label></th>
                        <td>
                            <input type="text" id="client_id" name="client_id" 
                                   value="<?php echo esc_attr($client_id); ?>" 
                                   class="regular-text" style="width: 100%;"
                                   placeholder="xxxxx.apps.googleusercontent.com">
                            <p class="description">Il Client ID dalla Google Console</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="client_secret">Client Secret</label></th>
                        <td>
                            <input type="password" id="client_secret" name="client_secret" 
                                   value="<?php echo esc_attr($client_secret); ?>" 
                                   class="regular-text" style="width: 100%;"
                                   placeholder="GOCSPX-xxxxx">
                            <p class="description">Il Client Secret dalla Google Console</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Redirect URI</th>
                        <td>
                            <code style="background: #f5f5f5; padding: 10px; display: block;">
                                <?php echo esc_html($redirect_uri); ?>
                            </code>
                            <p class="description">
                                ⚠️ Aggiungi ESATTAMENTE questo URI nelle "Authorized redirect URIs" nella Google Console
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="save_credentials" class="button button-primary">
                        💾 Salva Credenziali
                    </button>
                </p>
            </form>
        </div>
        
        <!-- STEP 2: Autorizzazione -->
        <?php if ($client_id && $client_secret): ?>
        <div style="margin-bottom: 30px; padding: 20px; background: #f0f8ff; border-radius: 8px;">
            <h2>🔐 Step 2: Autorizza Accesso</h2>
            
            <p>Clicca il pulsante qui sotto per autorizzare l'accesso a Google Drive:</p>
            
            <p>
                <a href="<?php echo esc_url($this->get_google_auth_url()); ?>" 
                   class="button button-primary button-hero">
                    🚀 Connetti Google Drive
                </a>
            </p>
        </div>
        <?php else: ?>
        <div style="padding: 20px; background: #fff3cd; border-radius: 8px;">
            <p>⚠️ Prima inserisci le credenziali OAuth sopra</p>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        
        <!-- Stato connesso -->
        <div style="padding: 20px; background: #d4edda; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="color: #155724;">✅ Google Drive Connesso</h2>
            
            <?php
            // Decodifica token per info
            $token_data = json_decode($google_token, true);
            if ($token_data):
            ?>
            <p>
                <strong>Token Info:</strong><br>
                - Access Token: <?php echo substr($token_data['access_token'], 0, 20); ?>...<br>
                - Refresh Token: <?php echo !empty($token_data['refresh_token']) ? '✅ Presente' : '❌ Mancante'; ?><br>
                - Scadenza: <?php 
                    if (isset($token_data['expires_in'])) {
                        $expires = $token_data['created'] + $token_data['expires_in'];
                        echo date('d/m/Y H:i:s', $expires);
                        echo ($expires > time()) ? ' (Valido)' : ' (Scaduto)';
                    }
                ?>
            </p>
            <?php endif; ?>
            
            <p>
                <form method="post" action="" style="display: inline;">
                    <?php wp_nonce_field('disco747_disconnect_google'); ?>
                    <button type="submit" name="disconnect_google" class="button button-secondary"
                            onclick="return confirm('Sei sicuro di voler disconnettere Google Drive?')">
                        🔌 Disconnetti
                    </button>
                </form>
                
                <button type="button" class="button button-secondary" onclick="testGoogleDrive()">
                    🧪 Test Connessione
                </button>
            </p>
        </div>
        
        <?php endif; ?>
        
        <!-- Istruzioni -->
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3>📚 Come ottenere le credenziali Google:</h3>
            
            <ol>
                <li>Vai su <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
                <li>Crea un nuovo progetto o seleziona uno esistente</li>
                <li>Vai su "APIs & Services" → "Credentials"</li>
                <li>Clicca "CREATE CREDENTIALS" → "OAuth client ID"</li>
                <li>Scegli "Web application"</li>
                <li>Aggiungi il Redirect URI mostrato sopra</li>
                <li>Copia Client ID e Client Secret qui</li>
                <li>Abilita "Google Drive API" nel progetto</li>
            </ol>
        </div>
        
    </div>
</div>

<script>
function testGoogleDrive() {
    jQuery.post(ajaxurl, {
        action: 'disco747_test_google_drive',
        nonce: '<?php echo wp_create_nonce('disco747_admin_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            alert('✅ Connessione OK!\n\nFile trovati: ' + response.data.file_count);
        } else {
            alert('❌ Errore: ' + response.data);
        }
    });
}
</script>

<?php
// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Salva credenziali
    if (isset($_POST['save_credentials'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'disco747_google_settings')) {
            update_option('disco747_google_client_id', sanitize_text_field($_POST['client_id']));
            update_option('disco747_google_client_secret', sanitize_text_field($_POST['client_secret']));
            
            echo '<div class="notice notice-success"><p>✅ Credenziali salvate!</p></div>';
            
            // Reload per aggiornare UI
            echo '<script>setTimeout(function(){ location.reload(); }, 1000);</script>';
        }
    }
    
    // Disconnetti
    if (isset($_POST['disconnect_google'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'disco747_disconnect_google')) {
            delete_option('disco747_google_token');
            
            echo '<div class="notice notice-success"><p>✅ Google Drive disconnesso!</p></div>';
            echo '<script>setTimeout(function(){ location.reload(); }, 1000);</script>';
        }
    }
}
?>

<?php
// Helper function per generare URL autorizzazione
if (!function_exists('get_google_auth_url')) {
    function get_google_auth_url() {
        $client_id = get_option('disco747_google_client_id');
        $redirect_uri = admin_url('admin.php?page=disco747-settings&action=google_callback');
        
        $params = array(
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent'
        );
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
}
?>