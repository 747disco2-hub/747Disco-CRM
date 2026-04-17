<?php
/**
 * Template per la pagina configurazione storage 747 Disco CRM
 *
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      1.0.0
 */

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Ottieni configurazione storage corrente
$current_storage = get_option('disco747_storage_type', 'dropbox');
$storage_status = $this->get_storage_status();

// Configurazioni Dropbox
$dropbox_config = array(
    'app_key' => get_option('disco747_dropbox_app_key', ''),
    'app_secret' => get_option('disco747_dropbox_app_secret', ''),
    'redirect_uri' => get_option('disco747_dropbox_redirect_uri', ''),
    'refresh_token' => get_option('disco747_dropbox_refresh_token', '')
);

// Configurazioni Google Drive
$gdrive_config = array(
    'client_id' => get_option('disco747_googledrive_client_id', ''),
    'client_secret' => get_option('disco747_googledrive_client_secret', ''),
    'redirect_uri' => get_option('disco747_googledrive_redirect_uri', ''),
    'refresh_token' => get_option('disco747_googledrive_refresh_token', ''),
    'folder_id' => get_option('disco747_googledrive_folder_id', '')
);
?>

<div class="wrap disco747-storage-page">
    <!-- Header -->
    <div class="disco747-page-header">
        <h1>
            <span class="dashicons dashicons-cloud"></span>
            <?php _e('Configurazione Storage Cloud', 'disco747'); ?>
        </h1>
        <p class="disco747-page-description">
            <?php _e('Configura Dropbox o Google Drive per il salvataggio automatico dei preventivi', 'disco747'); ?>
        </p>
    </div>

    <!-- Stato Storage Attuale -->
    <div class="disco747-storage-status">
        <div class="disco747-status-card <?php echo $storage_status['connected'] ? 'connected' : 'disconnected'; ?>">
            <div class="disco747-status-icon">
                <?php if ($storage_status['connected']): ?>
                    <span class="dashicons dashicons-yes-alt"></span>
                <?php else: ?>
                    <span class="dashicons dashicons-warning"></span>
                <?php endif; ?>
            </div>
            <div class="disco747-status-info">
                <h3>
                    <?php if ($storage_status['connected']): ?>
                        <?php printf(__('%s Connesso', 'disco747'), $storage_status['type']); ?>
                    <?php else: ?>
                        <?php printf(__('%s Non Connesso', 'disco747'), $storage_status['type']); ?>
                    <?php endif; ?>
                </h3>
                <p>
                    <?php if ($storage_status['connected']): ?>
                        <?php _e('I file vengono salvati automaticamente nel cloud.', 'disco747'); ?>
                    <?php else: ?>
                        <?php _e('Configura le credenziali per abilitare il salvataggio automatico.', 'disco747'); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="disco747-status-actions">
                <button type="button" class="button" id="disco747-test-storage">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e('Test Connessione', 'disco747'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Selezione Tipo Storage -->
    <div class="disco747-storage-selector">
        <h2><?php _e('Seleziona Servizio Storage', 'disco747'); ?></h2>
        
        <form method="post" action="" id="disco747-storage-type-form">
            <?php wp_nonce_field('disco747_storage_type_action', 'disco747_storage_type_nonce'); ?>
            <input type="hidden" name="action" value="save_storage_type" />
            
            <div class="disco747-storage-options">
                
                <!-- Opzione Dropbox -->
                <div class="disco747-storage-option <?php echo $current_storage === 'dropbox' ? 'active' : ''; ?>">
                    <label for="storage_dropbox">
                        <input type="radio" 
                               id="storage_dropbox" 
                               name="storage_type" 
                               value="dropbox" 
                               <?php checked($current_storage, 'dropbox'); ?> />
                        
                        <div class="disco747-option-content">
                            <div class="disco747-option-icon">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__, 2)) . 'assets/images/dropbox-logo.png'); ?>" 
                                     alt="Dropbox" 
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" />
                                <span class="dashicons dashicons-cloud" style="display: none; font-size: 48px; color: #0061ff;"></span>
                            </div>
                            <div class="disco747-option-info">
                                <h3>Dropbox</h3>
                                <p><?php _e('Salvataggio sicuro e sincronizzazione automatica', 'disco747'); ?></p>
                                <div class="disco747-option-features">
                                    <span class="disco747-feature">✓ <?php _e('15GB gratuiti', 'disco747'); ?></span>
                                    <span class="disco747-feature">✓ <?php _e('Sincronizzazione rapida', 'disco747'); ?></span>
                                    <span class="disco747-feature">✓ <?php _e('API stabile', 'disco747'); ?></span>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Opzione Google Drive -->
                <div class="disco747-storage-option <?php echo $current_storage === 'googledrive' ? 'active' : ''; ?>">
                    <label for="storage_googledrive">
                        <input type="radio" 
                               id="storage_googledrive" 
                               name="storage_type" 
                               value="googledrive" 
                               <?php checked($current_storage, 'googledrive'); ?> />
                        
                        <div class="disco747-option-content">
                            <div class="disco747-option-icon">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__, 2)) . 'assets/images/googledrive-logo.png'); ?>" 
                                     alt="Google Drive" 
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" />
                                <span class="dashicons dashicons-cloud" style="display: none; font-size: 48px; color: #4285f4;"></span>
                            </div>
                            <div class="disco747-option-info">
                                <h3>Google Drive</h3>
                                <p><?php _e('Integrazione con ecosystem Google', 'disco747'); ?></p>
                                <div class="disco747-option-features">
                                    <span class="disco747-feature">✓ <?php _e('15GB gratuiti', 'disco747'); ?></span>
                                    <span class="disco747-feature">✓ <?php _e('Condivisione facile', 'disco747'); ?></span>
                                    <span class="disco747-feature">✓ <?php _e('Editing online', 'disco747'); ?></span>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>

            </div>
            
            <p class="submit">
                <input type="submit" class="button-primary disco747-btn-primary" 
                       value="<?php esc_attr_e('Salva Selezione', 'disco747'); ?>" />
            </p>
        </form>
    </div>

    <!-- Configurazione Dropbox -->
    <div class="disco747-config-section <?php echo $current_storage === 'dropbox' ? 'active' : 'hidden'; ?>" 
         id="disco747-dropbox-config">
        
        <div class="disco747-config-card">
            <div class="disco747-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php _e('Configurazione Dropbox', 'disco747'); ?>
                </h2>
            </div>
            
            <div class="disco747-card-body">
                <!-- Istruzioni -->
                <div class="disco747-instructions">
                    <h3><?php _e('Come configurare Dropbox:', 'disco747'); ?></h3>
                    <ol>
                        <li><?php _e('Vai su', 'disco747'); ?> <a href="https://www.dropbox.com/developers/apps" target="_blank">Dropbox App Console</a></li>
                        <li><?php _e('Crea una nuova app con accesso "Full Dropbox"', 'disco747'); ?></li>
                        <li><?php _e('Copia App Key e App Secret qui sotto', 'disco747'); ?></li>
                        <li><?php _e('Aggiungi questo Redirect URI:', 'disco747'); ?> <code><?php echo esc_html(admin_url('admin.php?page=disco747-storage&oauth_callback=dropbox')); ?></code></li>
                    </ol>
                </div>

                <form method="post" action="" id="disco747-dropbox-form">
                    <?php wp_nonce_field('disco747_dropbox_action', 'disco747_dropbox_nonce'); ?>
                    <input type="hidden" name="action" value="save_dropbox_credentials" />
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="dropbox_app_key"><?php _e('App Key', 'disco747'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="dropbox_app_key" 
                                       name="dropbox_app_key" 
                                       value="<?php echo esc_attr($dropbox_config['app_key']); ?>" 
                                       class="regular-text" 
                                       required />
                                <p class="description">
                                    <?php _e('App Key dalla console sviluppatori Dropbox', 'disco747'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="dropbox_app_secret"><?php _e('App Secret', 'disco747'); ?> *</label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="dropbox_app_secret" 
                                       name="dropbox_app_secret" 
                                       value="<?php echo esc_attr($dropbox_config['app_secret']); ?>" 
                                       class="regular-text" 
                                       required />
                                <button type="button" class="button disco747-toggle-password" data-target="dropbox_app_secret">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                                <p class="description">
                                    <?php _e('App Secret dalla console sviluppatori Dropbox', 'disco747'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="dropbox_redirect_uri"><?php _e('Redirect URI', 'disco747'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="dropbox_redirect_uri" 
                                       name="dropbox_redirect_uri" 
                                       value="<?php echo esc_attr($dropbox_config['redirect_uri'] ?: admin_url('admin.php?page=disco747-storage&oauth_callback=dropbox')); ?>" 
                                       class="regular-text" 
                                       readonly />
                                <p class="description">
                                    <?php _e('URI da configurare nell\'app Dropbox (automatico)', 'disco747'); ?>
                                </p>
                            </td>
                        </tr>
                        <?php if (!empty($dropbox_config['refresh_token'])): ?>
                        <tr>
                            <th scope="row"><?php _e('Stato Autorizzazione', 'disco747'); ?></th>
                            <td>
                                <span class="disco747-status-ok">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Autorizzato e connesso', 'disco747'); ?>
                                </span>
                                <p class="description">
                                    <?php _e('L\'applicazione è autorizzata ad accedere al tuo Dropbox', 'disco747'); ?>
                                </p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button-primary disco747-btn-primary" 
                               value="<?php esc_attr_e('Salva Credenziali Dropbox', 'disco747'); ?>" />
                        
                        <?php if (!empty($dropbox_config['app_key']) && !empty($dropbox_config['app_secret'])): ?>
                        <button type="button" class="button disco747-btn-secondary" id="disco747-dropbox-auth">
                            <span class="dashicons dashicons-admin-network"></span>
                            <?php _e('Autorizza Dropbox', 'disco747'); ?>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (!empty($dropbox_config['refresh_token'])): ?>
                        <button type="button" class="button" id="disco747-dropbox-revoke">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php _e('Revoca Autorizzazione', 'disco747'); ?>
                        </button>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
    </div>

    <!-- Configurazione Google Drive -->
    <div class="disco747-config-section <?php echo $current_storage === 'googledrive' ? 'active' : 'hidden'; ?>" 
         id="disco747-googledrive-config">
        
        <div class="disco747-config-card">
            <div class="disco747-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php _e('Configurazione Google Drive', 'disco747'); ?>
                </h2>
            </div>
            
            <div class="disco747-card-body">
                <!-- Istruzioni -->
                <div class="disco747-instructions">
                    <h3><?php _e('Come configurare Google Drive:', 'disco747'); ?></h3>
                    <ol>
                        <li><?php _e('Vai su', 'disco747'); ?> <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                        <li><?php _e('Crea un nuovo progetto o seleziona uno esistente', 'disco747'); ?></li>
                        <li><?php _e('Abilita l\'API Google Drive', 'disco747'); ?></li>
                        <li><?php _e('Crea credenziali OAuth 2.0 per applicazione web', 'disco747'); ?></li>
                        <li><?php _e('Aggiungi questo Redirect URI:', 'disco747'); ?> <code><?php echo esc_html(admin_url('admin.php?page=disco747-storage&oauth_callback=googledrive')); ?></code></li>
                    </ol>
                </div>

                <form method="post" action="" id="disco747-googledrive-form">
                    <?php wp_nonce_field('disco747_googledrive_action', 'disco747_googledrive_nonce'); ?>
                    <input type="hidden" name="action" value="save_googledrive_credentials" />
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="googledrive_client_id"><?php _e('Client ID', 'disco747'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="googledrive_client_id" 
                                       name="googledrive_client_id" 
                                       value="<?php echo esc_attr($gdrive_config['client_id']); ?>" 
                                       class="regular-text" 
                                       required />
                                <p class="description">
                                    <?php _e('Client ID OAuth 2.0 da Google Cloud Console', 'disco747'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="googledrive_client_secret"><?php _e('Client Secret', 'disco747'); ?> *</label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="googledrive_client_secret" 
                                       name="googledrive_client_secret" 
                                       value="<?php echo esc_attr($gdrive_config['client_secret']); ?>" 
                                       class="regular-text" 
                                       required />
                                <button type="button" class="button disco747-toggle-password" data-target="googledrive_client_secret">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                                <p class="description">
                                    <?php _e('Client Secret OAuth 2.0 da Google Cloud Console', 'disco747'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="googledrive_redirect_uri"><?php _e('Redirect URI', 'disco747'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="googledrive_redirect_uri" 
                                       name="googledrive_redirect_uri" 
                                       value="<?php echo esc_attr($gdrive_config['redirect_uri'] ?: admin_url('admin.php?page=disco747-storage&oauth_callback=googledrive')); ?>" 
                                       class="regular-text" 
                                       readonly />
                                <p class="description">
                                    <?php _e('URI da configurare in Google Cloud Console (automatico)', 'disco747'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="googledrive_folder_id"><?php _e('ID Cartella (Opzionale)', 'disco747'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="googledrive_folder_id" 
                                       name="googledrive_folder_id" 
                                       value="<?php echo esc_attr($gdrive_config['folder_id']); ?>" 
                                       class="regular-text" 
                                       placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms" />
                                <p class="description">
                                    <?php _e('ID della cartella specifica dove salvare i file (lascia vuoto per cartella root)', 'disco747'); ?>
                                </p>
                            </td>
                        </tr>
                        <?php if (!empty($gdrive_config['refresh_token'])): ?>
                        <tr>
                            <th scope="row"><?php _e('Stato Autorizzazione', 'disco747'); ?></th>
                            <td>
                                <span class="disco747-status-ok">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Autorizzato e connesso', 'disco747'); ?>
                                </span>
                                <p class="description">
                                    <?php _e('L\'applicazione è autorizzata ad accedere al tuo Google Drive', 'disco747'); ?>
                                </p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button-primary disco747-btn-primary" 
                               value="<?php esc_attr_e('Salva Credenziali Google Drive', 'disco747'); ?>" />
                        
                        <?php if (!empty($gdrive_config['client_id']) && !empty($gdrive_config['client_secret'])): ?>
                        <button type="button" class="button disco747-btn-secondary" id="disco747-googledrive-auth">
                            <span class="dashicons dashicons-admin-network"></span>
                            <?php _e('Autorizza Google Drive', 'disco747'); ?>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (!empty($gdrive_config['refresh_token'])): ?>
                        <button type="button" class="button" id="disco747-googledrive-revoke">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php _e('Revoca Autorizzazione', 'disco747'); ?>
                        </button>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
    </div>

    <!-- Test Results -->
    <div id="disco747-test-results" class="disco747-test-results" style="display: none;">
        <div class="disco747-test-card">
            <div class="disco747-test-header">
                <h3><span class="dashicons dashicons-admin-tools"></span> <?php _e('Risultati Test', 'disco747'); ?></h3>
                <button type="button" class="button-link" id="disco747-close-test">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="disco747-test-body">
                <div id="disco747-test-content">
                    <!-- Contenuto dinamico -->
                </div>
            </div>
        </div>
    </div>

</div>

<style>
/* Stili specifici per la pagina storage */
.disco747-storage-page {
    background: #f9f9f9;
    margin: 0 -20px;
    padding: 0;
}

.disco747-page-header {
    background: linear-gradient(135deg, #2b1e1a 0%, #c28a4d 100%);
    color: white;
    padding: 30px 20px;
    margin-bottom: 20px;
}

.disco747-page-header h1 {
    margin: 0;
    font-size: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.disco747-page-description {
    margin: 10px 0 0 0;
    opacity: 0.9;
    font-size: 16px;
}

.disco747-storage-status {
    padding: 0 20px;
    margin-bottom: 30px;
}

.disco747-status-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #dc3545;
}

.disco747-status-card.connected {
    border-left-color: #28a745;
}

.disco747-status-icon {
    font-size: 48px;
    color: #dc3545;
}

.disco747-status-card.connected .disco747-status-icon {
    color: #28a745;
}

.disco747-status-info {
    flex: 1;
}

.disco747-status-info h3 {
    margin: 0 0 5px 0;
    font-size: 20px;
}

.disco747-storage-selector {
    padding: 0 20px;
    margin-bottom: 30px;
}

.disco747-storage-selector h2 {
    margin-bottom: 20px;
    color: #2b1e1a;
}

.disco747-storage-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.disco747-storage-option {
    border: 2px solid #ddd;
    border-radius: 8px;
    background: white;
    transition: all 0.3s ease;
    cursor: pointer;
}

.disco747-storage-option:hover {
    border-color: #c28a4d;
    box-shadow: 0 4px 12px rgba(194, 138, 77, 0.2);
}

.disco747-storage-option.active {
    border-color: #c28a4d;
    background: #fff8f0;
    box-shadow: 0 4px 12px rgba(194, 138, 77, 0.3);
}

.disco747-storage-option label {
    display: block;
    cursor: pointer;
    margin: 0;
}

.disco747-storage-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.disco747-option-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 30px 20px;
    text-align: center;
}

.disco747-option-icon {
    margin-bottom: 15px;
}

.disco747-option-icon img {
    height: 48px;
    width: auto;
}

.disco747-option-info h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
    color: #2b1e1a;
}

.disco747-option-info p {
    margin: 0 0 15px 0;
    color: #666;
}

.disco747-option-features {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.disco747-feature {
    font-size: 14px;
    color: #28a745;
}

.disco747-config-section {
    padding: 0 20px;
    margin-bottom: 30px;
}

.disco747-config-section.hidden {
    display: none;
}

.disco747-config-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.disco747-card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.disco747-card-header h2 {
    margin: 0;
    font-size: 18px;
    color: #2b1e1a;
    display: flex;
    align-items: center;
    gap: 8px;
}

.disco747-card-body {
    padding: 20px;
}

.disco747-instructions {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 30px;
}

.disco747-instructions h3 {
    margin-top: 0;
    color: #0073aa;
}

.disco747-instructions ol {
    margin-bottom: 0;
}

.disco747-instructions code {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    word-break: break-all;
}

.disco747-toggle-password {
    margin-left: 5px;
}

.disco747-status-ok {
    color: #28a745;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
}

.disco747-btn-primary {
    background: #c28a4d !important;
    border-color: #c28a4d !important;
    text-shadow: none !important;
    box-shadow: 0 2px 8px rgba(194, 138, 77, 0.3) !important;
}

.disco747-btn-primary:hover {
    background: #b8794a !important;
    border-color: #b8794a !important;
}

.disco747-btn-secondary {
    background: #b8b1b3 !important;
    border-color: #b8b1b3 !important;
    color: white !important;
}

.disco747-btn-secondary:hover {
    background: #a5a0a2 !important;
    border-color: #a5a0a2 !important;
}

.disco747-test-results {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.disco747-test-card {
    background: white;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.disco747-test-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.disco747-test-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.disco747-test-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.disco747-test-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.disco747-test-item:last-child {
    border-bottom: none;
}

.disco747-test-status.success {
    color: #28a745;
}

.disco747-test-status.error {
    color: #dc3545;
}

.disco747-test-status.warning {
    color: #ffc107;
}

/* Responsive */
@media (max-width: 768px) {
    .disco747-storage-options {
        grid-template-columns: 1fr;
    }
    
    .disco747-option-content {
        flex-direction: row;
        text-align: left;
        padding: 20px;
    }
    
    .disco747-option-icon {
        margin-right: 15px;
        margin-bottom: 0;
    }
    
    .disco747-option-features {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .disco747-status-card {
        flex-direction: column;
        text-align: center;
    }
    
    .disco747-instructions code {
        font-size: 12px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Cambia tipo storage
    $('input[name="storage_type"]').on('change', function() {
        var selectedType = $(this).val();
        
        // Mostra/nascondi sezioni di configurazione
        $('.disco747-config-section').removeClass('active').addClass('hidden');
        $('#disco747-' + selectedType + '-config').removeClass('hidden').addClass('active');
        
        // Aggiorna classi opzioni
        $('.disco747-storage-option').removeClass('active');
        $(this).closest('.disco747-storage-option').addClass('active');
    });
    
    // Test connessione storage
    $('#disco747-test-storage').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.html('<span class="dashicons dashicons-update disco747-spin"></span> Testando...');
        button.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'disco747_test_storage',
            nonce: disco747Admin.nonce
        }, function(response) {
            if (response.success) {
                showTestResults(response.data);
            } else {
                showTestResults({
                    test_result: {
                        success: false,
                        message: response.data.message || 'Errore test connessione'
                    }
                });
            }
        }).fail(function() {
            showTestResults({
                test_result: {
                    success: false,
                    message: 'Errore di comunicazione con il server'
                }
            });
        }).always(function() {
            button.html(originalText);
            button.prop('disabled', false);
        });
    });
    
    // Autorizzazione Dropbox
    $('#disco747-dropbox-auth').on('click', function() {
        authorizeStorage('dropbox');
    });
    
    // Autorizzazione Google Drive
    $('#disco747-googledrive-auth').on('click', function() {
        authorizeStorage('googledrive');
    });
    
    // Revoca autorizzazioni
    $('#disco747-dropbox-revoke, #disco747-googledrive-revoke').on('click', function() {
        var storageType = $(this).attr('id').includes('dropbox') ? 'dropbox' : 'googledrive';
        
        if (confirm('Sei sicuro di voler revocare l\'autorizzazione? Dovrai riautorizzare l\'accesso.')) {
            revokeAuthorization(storageType);
        }
    });
    
    // Toggle password visibility
    $('.disco747-toggle-password').on('click', function() {
        var targetId = $(this).data('target');
        var target = $('#' + targetId);
        var icon = $(this).find('.dashicons');
        
        if (target.attr('type') === 'password') {
            target.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            target.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });
    
    // Chiudi risultati test
    $('#disco747-close-test').on('click', function() {
        $('#disco747-test-results').hide();
    });
    
    // Chiudi su click overlay
    $('#disco747-test-results').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Funzione per mostrare risultati test
    function showTestResults(data) {
        var html = '';
        
        if (data.test_result) {
            html += '<div class="disco747-test-item">';
            html += '<span>Connessione Storage</span>';
            html += '<span class="disco747-test-status ' + (data.test_result.success ? 'success' : 'error') + '">';
            html += data.test_result.success ? '✅ Connesso' : '❌ ' + data.test_result.message;
            html += '</span>';
            html += '</div>';
        }
        
        if (data.diagnostics) {
            $.each(data.diagnostics, function(key, result) {
                html += '<div class="disco747-test-item">';
                html += '<span>' + key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</span>';
                html += '<span class="disco747-test-status ' + (result.success ? 'success' : 'error') + '">';
                html += result.success ? '✅ OK' : '❌ ' + (result.message || 'Errore');
                html += '</span>';
                html += '</div>';
            });
        }
        
        $('#disco747-test-content').html(html);
        $('#disco747-test-results').show();
    }
    
    // Funzione per autorizzazione storage
    function authorizeStorage(storageType) {
        var button = $('#disco747-' + storageType + '-auth');
        var originalText = button.html();
        
        button.html('<span class="dashicons dashicons-update disco747-spin"></span> Generando URL...');
        button.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'disco747_generate_auth_url',
            storage_type: storageType,
            nonce: disco747Admin.nonce
        }, function(response) {
            if (response.success && response.data.auth_url) {
                // Apri finestra di autorizzazione
                var authWindow = window.open(
                    response.data.auth_url,
                    'storage_auth',
                    'width=600,height=700,scrollbars=yes,resizable=yes'
                );
                
                // Polling per controllo chiusura finestra
                var pollTimer = setInterval(function() {
                    if (authWindow.closed) {
                        clearInterval(pollTimer);
                        
                        // Verifica se autorizzazione completata
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                }, 1000);
                
            } else {
                alert('Errore generazione URL autorizzazione: ' + (response.data.message || 'Errore sconosciuto'));
            }
        }).fail(function() {
            alert('Errore di comunicazione con il server');
        }).always(function() {
            button.html(originalText);
            button.prop('disabled', false);
        });
    }
    
    // Funzione per revocare autorizzazione
    function revokeAuthorization(storageType) {
        $.post(ajaxurl, {
            action: 'disco747_revoke_auth',
            storage_type: storageType,
            nonce: disco747Admin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Errore revoca autorizzazione: ' + (response.data.message || 'Errore sconosciuto'));
            }
        });
    }
    
    // Gestione callback OAuth dalla URL
    var urlParams = new URLSearchParams(window.location.search);
    var authCode = urlParams.get('code');
    var storageType = urlParams.get('oauth_callback');
    
    if (authCode && storageType) {
        // Scambia il codice con i token
        $.post(ajaxurl, {
            action: 'disco747_exchange_auth_code',
            auth_code: authCode,
            storage_type: storageType,
            nonce: disco747Admin.nonce
        }, function(response) {
            if (response.success) {
                // Rimuovi parametri dalla URL e ricarica
                var newUrl = window.location.pathname + '?page=disco747-storage';
                window.history.replaceState({}, document.title, newUrl);
                location.reload();
            } else {
                alert('Errore autorizzazione: ' + (response.data.message || 'Errore sconosciuto'));
            }
        });
    }
    
    // Validazione form
    $('form').on('submit', function(e) {
        var form = $(this);
        var hasError = false;
        
        // Controlla campi required
        form.find('input[required]').each(function() {
            if ($(this).val().trim() === '') {
                $(this).css('border-color', '#dc3545');
                hasError = true;
            } else {
                $(this).css('border-color', '');
            }
        });
        
        if (hasError) {
            e.preventDefault();
            $('<div class="notice notice-error is-dismissible"><p>Compila tutti i campi obbligatori.</p></div>')
                .insertAfter('.disco747-page-header')
                .delay(5000)
                .fadeOut();
        }
    });
    
});

/* Animazione spin */
.disco747-spin {
    animation: disco747-spin 1s linear infinite;
}

@keyframes disco747-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</script>