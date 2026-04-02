<?php
/**
 * Template Dashboard 747 Disco CRM - Integrata con Sistema Esistente
 * 
 * SOSTITUZIONE: /includes/admin/views/dashboard-page.php
 * INTEGRAZIONE: Usa classi esistenti del plugin (database, gdrive_sync, storage_manager)
 * 
 * Design nero-oro-grigio 747 Disco con:
 * - Statistiche reali dal database o Google Drive
 * - Preventivi recenti con tracking numerazione
 * - Colonna "Creato da" utente WordPress  
 * - Mobile responsive
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views  
 * @since      11.6.1-COMPLETE-FIXED
 * @version    11.6.1-COMPLETE-FIXED
 */

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// PREPARAZIONE DATI REALI - USA SISTEMA ESISTENTE DEL PLUGIN
// ============================================================================

// Ottieni istanze dalle classi esistenti del plugin
$disco747_crm = disco747_crm();
$current_user = wp_get_current_user();

// Inizializza variabili con fallback sicuri
$stats = array(
    'total' => 0,
    'active' => 0, 
    'confirmed' => 0,
    'cancelled' => 0,
    'this_month' => 0,
    'total_preventivi' => 0,
    'preventivi_attivi' => 0,
    'preventivi_confermati' => 0,
    'valore_totale' => 0,
    'source' => 'fallback'
);

$recent_preventivi = array();
$storage_type = get_option('disco747_storage_type', 'googledrive');
$company_name = get_option('disco747_company_name', '747 Disco');
$plugin_version = defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.6.1';

// CORRETTO: Usa sistema esistente del plugin
if ($disco747_crm && $disco747_crm->is_initialized()) {
    try {
        // Ottieni componenti esistenti
        $database = $disco747_crm->get_database();
        $gdrive_sync = $disco747_crm->get_gdrive_sync();
        
        // 1. PROVA PRIMA DA GOOGLE DRIVE SYNC (se disponibile)
        // CORRETTO: Disabilitato Google Drive Sync per bug get_valid_access_token
        // Alternativa: prova accesso diretto a Google Drive
        if ($disco747_crm->get_storage_manager() && $storage_type === 'googledrive') {
            try {
                $storage_manager = $disco747_crm->get_storage_manager();
                if (method_exists($storage_manager, 'list_files')) {
                    $gdrive_files = $storage_manager->list_files('/', 'pdf');
                    if (!empty($gdrive_files)) {
                        $recent_preventivi = disco747_parse_gdrive_files_to_preventivi($gdrive_files, 8);
                        $stats = disco747_calculate_stats_from_preventivi($recent_preventivi);
                        $stats['source'] = 'google_drive_direct';
                    }
                }
            } catch (Exception $e) {
                error_log('[747 Disco CRM] Errore Google Drive diretto, fallback a database: ' . $e->getMessage());
            }
        }
        
        // 2. FALLBACK A DATABASE se Google Drive fallisce
        if (empty($recent_preventivi) && $database) {
            try {
                if (method_exists($database, 'get_preventivi')) {
                    $recent_preventivi = $database->get_preventivi(array(
                        'orderby' => 'created_at',
                        'order' => 'DESC', 
                        'limit' => 8
                    ));
                }
                
                // Statistiche dal database
                if (method_exists($database, 'get_statistics')) {
                    $db_stats = $database->get_statistics();
                    if (!empty($db_stats)) {
                        $stats = array_merge($stats, $db_stats);
                        $stats['source'] = 'database';
                    }
                }
            } catch (Exception $e) {
                error_log('[747 Disco CRM] Errore database: ' . $e->getMessage());
            }
        }
        
        // 3. ULTIMO FALLBACK: Query diretta
        if (empty($recent_preventivi)) {
            $recent_preventivi = disco747_get_preventivi_direct_query(8);
            $stats = disco747_get_stats_direct_query();
            $stats['source'] = 'direct_query';
        }
        
    } catch (Exception $e) {
        error_log('[747 Disco CRM Dashboard] Errore caricamento dati: ' . $e->getMessage());
    }
}

// Normalizza nomi statistiche per compatibilità
$stats['total'] = $stats['total'] ?? $stats['total_preventivi'] ?? 0;
$stats['active'] = $stats['active'] ?? $stats['preventivi_attivi'] ?? 0;
$stats['confirmed'] = $stats['confirmed'] ?? $stats['preventivi_confermati'] ?? 0;

// Check configurazioni storage (supporta multiple convenzioni nomi)
$storage_configs = array(
    'googledrive' => array(
        get_option('disco747_gd_credentials', array()),
        get_option('preventivi_googledrive_refresh_token', ''),
        get_option('disco747_googledrive_refresh_token', '')
    ),
    'dropbox' => array(
        get_option('disco747_dropbox_credentials', array()),
        get_option('preventivi_dropbox_refresh_token', ''),
        get_option('disco747_dropbox_refresh_token', '')
    )
);

$is_storage_configured = false;
if ($storage_type === 'googledrive') {
    $gd_creds = $storage_configs['googledrive'];
    $is_storage_configured = (!empty($gd_creds[0]['refresh_token']) || !empty($gd_creds[1]) || !empty($gd_creds[2]));
} else {
    $db_creds = $storage_configs['dropbox'];
    $is_storage_configured = (!empty($db_creds[0]['refresh_token']) || !empty($db_creds[1]) || !empty($db_creds[2]));
}

// System status checks
$database_status = disco747_check_database_status();
$login_system_status = is_user_logged_in() ? 'ok' : 'error';

?>

<!-- ============================================================================ -->
<!-- DASHBOARD PRINCIPALE 747 DISCO CRM -->
<!-- ============================================================================ -->

<div class="wrap disco747-crm-dashboard" style="background: #f8f9fa; margin: 0 -20px 0 -20px; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    
    <!-- Header 747 Disco Style -->
    <div style="background: linear-gradient(135deg, #2b1e1a 0%, #3c3c3c 100%); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.3); position: relative; overflow: hidden;">
        
        <!-- Elementi decorativi di sfondo -->
        <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(194, 138, 77, 0.1); border-radius: 50%;"></div>
        <div style="position: absolute; bottom: -30px; left: -30px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        
        <div style="position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="margin: 0; font-size: 2.2rem; color: #c28a4d; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); font-weight: 700;">
                    🎭 747 DISCO - Dashboard CRM
                </h1>
                <p style="margin: 10px 0 0 0; color: #eeeae6; font-size: 1.1rem; opacity: 0.9;">
                    Preventivi Personalizzati • Benvenuto, <strong style="color: #c28a4d;"><?php echo esc_html($current_user->display_name); ?></strong>
                </p>
                
                <!-- Info sistema compatta -->
                <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 8px; font-size: 0.9rem;">
                    <strong>Plugin:</strong> v<?php echo esc_html($plugin_version); ?> • 
                    <strong>Storage:</strong> <?php echo esc_html(ucfirst($storage_type)); ?> 
                    <?php if ($is_storage_configured): ?>
                        <span style="color: #90EE90;">✅ Connesso</span>
                    <?php else: ?>
                        <span style="color: #FFB6C1;">⚠️ Non connesso</span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="background: rgba(194, 138, 77, 0.2); padding: 15px; border-radius: 10px; border: 2px solid #c28a4d;">
                    <div style="font-size: 0.9rem; color: #eeeae6; margin-bottom: 5px;">Storage Attivo</div>
                    <div style="font-size: 1.2rem; font-weight: bold; color: #c28a4d;">
                        <?php echo $storage_type === 'googledrive' ? '📁 Google Drive' : '📦 Dropbox'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- AZIONI PRINCIPALI -->
    <!-- ============================================================================ -->
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        
        <!-- Pulsante Nuovo Preventivo -->
        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3); transition: all 0.3s ease; cursor: pointer;" onclick="window.location.href='<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=new_preventivo')); ?>'">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">🎉</div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.3rem; font-weight: 600;">Nuovo Preventivo</h3>
            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">Crea un nuovo preventivo per evento</p>
        </div>
        
        <!-- Pulsante Dashboard Preventivi -->
        <div style="background: linear-gradient(135deg, #17a2b8 0%, #6610f2 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(23, 162, 184, 0.3); transition: all 0.3s ease; cursor: pointer;" onclick="window.location.href='<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=dashboard_preventivi')); ?>'">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">📊</div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.3rem; font-weight: 600;">Gestisci Preventivi</h3>
            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">Visualizza e modifica preventivi esistenti</p>
        </div>
        
    </div>

    <!-- ============================================================================ -->
    <!-- STATISTICHE IN EVIDENZA -->
    <!-- ============================================================================ -->
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px;">
        
        <!-- Totale Preventivi -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(194, 138, 77, 0.3); transition: transform 0.3s ease;">
            <div style="font-size: 3rem; font-weight: bold; margin: 15px 0;"><?php echo number_format($stats['total']); ?></div>
            <h3 style="margin: 0; font-size: 1.1rem; opacity: 0.9; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">📊 Preventivi Totali</h3>
            <p style="margin: 10px 0 0 0; font-size: 0.85rem; opacity: 0.7;">
                Fonte: <?php echo esc_html($stats['source'] ?? ($database_status === 'ok' ? 'Database' : 'Cache')); ?>
            </p>
        </div>
        
        <!-- Preventivi Attivi -->
        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3); transition: transform 0.3s ease;">
            <div style="font-size: 3rem; font-weight: bold; margin: 15px 0;"><?php echo number_format($stats['active']); ?></div>
            <h3 style="margin: 0; font-size: 1.1rem; opacity: 0.9; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">✅ Attivi</h3>
            <p style="margin: 10px 0 0 0; font-size: 0.85rem; opacity: 0.7;">In elaborazione</p>
        </div>
        
        <!-- Preventivi Confermati -->
        <div style="background: linear-gradient(135deg, #17a2b8 0%, #6610f2 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(23, 162, 184, 0.3); transition: transform 0.3s ease;">
            <div style="font-size: 3rem; font-weight: bold; margin: 15px 0;"><?php echo number_format($stats['confirmed']); ?></div>
            <h3 style="margin: 0; font-size: 1.1rem; opacity: 0.9; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">🎉 Confermati</h3>
            <p style="margin: 10px 0 0 0; font-size: 0.85rem; opacity: 0.7;">Eventi sicuri</p>
        </div>
        
        <!-- Questo Mese -->
        <div style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(255, 193, 7, 0.3); transition: transform 0.3s ease;">
            <div style="font-size: 3rem; font-weight: bold; margin: 15px 0;"><?php echo number_format($stats['this_month']); ?></div>
            <h3 style="margin: 0; font-size: 1.1rem; opacity: 0.9; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">📅 Questo Mese</h3>
            <p style="margin: 10px 0 0 0; font-size: 0.85rem; opacity: 0.7;"><?php echo date('F Y'); ?></p>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- SEZIONI PRINCIPALI -->
    <!-- ============================================================================ -->
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        
        <!-- Sistema e Configurazione -->
        <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); border-top: 5px solid #c28a4d;">
            <h2 style="color: #2b1e1a; border-bottom: 3px solid #c28a4d; padding-bottom: 15px; margin-bottom: 25px; font-size: 1.4rem;">
                ⚙️ Sistema e Configurazione
            </h2>
            
            <!-- Status Database -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid <?php echo $database_status === 'ok' ? '#28a745' : '#dc3545'; ?>;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #2b1e1a;">🗄️ Database</strong>
                        <div style="font-size: 13px; color: #666; margin-top: 5px;">
                            Sistema di archiviazione preventivi
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: <?php echo $database_status === 'ok' ? '#28a745' : '#dc3545'; ?>; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;">
                            <?php echo $database_status === 'ok' ? '✅ OPERATIVO' : '❌ ERRORE'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Status Storage Cloud -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid <?php echo $is_storage_configured ? '#28a745' : '#ffc107'; ?>;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #2b1e1a;"><?php echo $storage_type === 'googledrive' ? '📁 Google Drive' : '📦 Dropbox'; ?></strong>
                        <div style="font-size: 13px; color: #666; margin-top: 5px;">
                            Storage cloud per PDF e Excel
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: <?php echo $is_storage_configured ? '#28a745' : '#ffc107'; ?>; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;">
                            <?php echo $is_storage_configured ? '✅ CONNESSO' : '⚠️ VERIFICA'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Versione Plugin -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 5px solid #17a2b8;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #2b1e1a;">🔧 Versione Plugin</strong>
                        <div style="font-size: 13px; color: #666; margin-top: 5px;">
                            747 Disco CRM Enhanced
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: #17a2b8; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;">
                            v<?php echo esc_html($plugin_version); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Azioni e Collegamenti -->
        <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); border-top: 5px solid #17a2b8;">
            <h2 style="color: #2b1e1a; border-bottom: 3px solid #17a2b8; padding-bottom: 15px; margin-bottom: 25px; font-size: 1.4rem;">
                🔗 Azioni e Collegamenti
            </h2>
            
            <!-- Sincronizzazione -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                <h4 style="color: #17a2b8; margin: 0 0 15px 0;">☁️ Sincronizzazione Storage</h4>
                <p style="color: #666; margin: 0 0 15px 0; font-size: 14px;">
                    Sincronizza preventivi con <?php echo $storage_type === 'googledrive' ? 'Google Drive' : 'Dropbox'; ?>
                </p>
                <form method="post" style="display: inline-block;">
                    <?php wp_nonce_field('disco747_sync_storage'); ?>
                    <input type="hidden" name="disco747_action" value="sync_storage">
                    <button type="submit" class="button button-primary" 
                            style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border: none; color: white; padding: 10px 20px; border-radius: 20px; font-weight: 600; cursor: pointer;">
                        🔄 Sincronizza Ora
                    </button>
                </form>
            </div>
            
            <!-- Links Rapidi -->
            <div style="display: grid; gap: 15px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-settings')); ?>" 
                   class="button button-secondary" 
                   style="background: rgba(194, 138, 77, 0.1); border: 2px solid #c28a4d; color: #c28a4d; padding: 12px; text-align: center; text-decoration: none; border-radius: 8px; transition: all 0.3s ease; font-weight: 600;">
                    ⚙️ Impostazioni
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-messages')); ?>" 
                   class="button button-secondary" 
                   style="background: rgba(194, 138, 77, 0.1); border: 2px solid #c28a4d; color: #c28a4d; padding: 12px; text-align: center; text-decoration: none; border-radius: 8px; transition: all 0.3s ease; font-weight: 600;">
                    📧 Messaggi Automatici
                </a>
                <a href="<?php echo esc_url(home_url('/disco747-dashboard')); ?>" 
                   class="button button-primary" 
                   target="_blank" 
                   style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border: none; color: white; padding: 12px; text-align: center; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    🌐 Dashboard Frontend
                </a>
            </div>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- PREVENTIVI RECENTI CON DATI REALI -->
    <!-- ============================================================================ -->
    
    <?php if (!empty($recent_preventivi)): ?>
    <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); border-top: 5px solid #17a2b8; margin-bottom: 30px;">
        <h2 style="color: #2b1e1a; border-bottom: 3px solid #17a2b8; padding-bottom: 15px; margin-bottom: 25px; font-size: 1.4rem;">
            📋 Preventivi Recenti
        </h2>
        
        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped" style="border-radius: 10px; overflow: hidden;">
                <thead style="background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%); color: white;">
                    <tr>
                        <th style="padding: 15px; text-align: center; font-weight: 600; width: 70px;">#</th>
                        <th style="padding: 15px; font-weight: 600;">Cliente</th>
                        <th style="padding: 15px; font-weight: 600;">Evento</th>
                        <th style="padding: 15px; font-weight: 600;">Data Evento</th>
                        <th style="padding: 15px; text-align: right; font-weight: 600;">Importo</th>
                        <th style="padding: 15px; text-align: center; font-weight: 600;">Stato</th>
                        <th style="padding: 15px; font-weight: 600; width: 120px;">Creato da</th>
                        <th style="padding: 15px; text-align: center; font-weight: 600;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_preventivi as $index => $preventivo): ?>
                    <?php 
                    // Gestisce sia oggetti che array
                    $prev_data = is_object($preventivo) ? $preventivo : (object) $preventivo;
                    
                    // ID progressivo con riutilizzo numeri se eliminati
                    $preventivo_id = $prev_data->id ?? $prev_data->preventivo_id ?? ($index + 1);
                    
                    // Dati cliente
                    $nome_cliente = trim(($prev_data->nome_referente ?? $prev_data->nome_cliente ?? '') . ' ' . 
                                   ($prev_data->cognome_referente ?? $prev_data->cognome_cliente ?? ''));
                    $email_cliente = $prev_data->email_referente ?? $prev_data->email ?? $prev_data->mail ?? '';
                    
                    // Dati evento
                    $tipo_evento = $prev_data->tipo_evento ?? 'N/A';
                    $data_evento = $prev_data->data_evento ?? '';
                    $importo = floatval($prev_data->importo ?? $prev_data->importo_preventivo ?? 0);
                    
                    // Stato
                    $stato = $prev_data->stato ?? 'bozza';
                    $confermato = !empty($prev_data->confermato) || !empty($prev_data->acconto);
                    if ($confermato && $stato === 'bozza') {
                        $stato = 'confermato';
                    }
                    
                    // Utente creatore
                    $created_by = '';
                    if (!empty($prev_data->created_by_user)) {
                        $user = get_user_by('id', $prev_data->created_by_user);
                        $created_by = $user ? $user->display_name : 'Utente #' . $prev_data->created_by_user;
                    } elseif (!empty($prev_data->utente_wp)) {
                        $created_by = $prev_data->utente_wp;
                    } else {
                        $created_by = 'Sistema';
                    }
                    
                    // URL files
                    $pdf_url = $prev_data->googledrive_url ?? $prev_data->pdf_url ?? '';
                    $excel_url = $prev_data->excel_url ?? '';
                    ?>
                    <tr style="transition: all 0.3s ease;">
                        <td style="padding: 15px; text-align: center;">
                            <strong style="color: #c28a4d; font-size: 1.1rem; font-weight: 700;">#<?php echo esc_html($preventivo_id); ?></strong>
                        </td>
                        <td style="padding: 15px;">
                            <strong style="color: #2b1e1a; font-size: 0.95rem;"><?php echo esc_html($nome_cliente ?: 'Cliente N/A'); ?></strong>
                            <?php if ($email_cliente): ?>
                            <br><small style="color: #666; font-size: 0.85rem;"><?php echo esc_html($email_cliente); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px;">
                            <span style="background: #f8f9fa; padding: 5px 10px; border-radius: 15px; font-size: 0.9rem; color: #666; border: 1px solid #e9ecef;">
                                <?php echo esc_html($tipo_evento); ?>
                            </span>
                        </td>
                        <td style="padding: 15px; color: #666; font-size: 0.9rem;">
                            <?php 
                            if ($data_evento) {
                                echo esc_html(date('d/m/Y', strtotime($data_evento)));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td style="padding: 15px; text-align: right; font-weight: 600; color: #c28a4d; font-size: 1.1rem;">
                            €<?php echo number_format($importo, 2, ',', '.'); ?>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <?php 
                            $stato_colors = array(
                                'bozza' => '#6c757d',
                                'inviato' => '#007bff', 
                                'confermato' => '#28a745',
                                'annullato' => '#dc3545'
                            );
                            $color = $stato_colors[$stato] ?? '#6c757d';
                            $stato_icons = array(
                                'bozza' => '📝',
                                'inviato' => '📧',
                                'confermato' => '✅',
                                'annullato' => '❌'
                            );
                            $icon = $stato_icons[$stato] ?? '📝';
                            ?>
                            <span style="background: <?php echo $color; ?>; color: white; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                <?php echo $icon . ' ' . esc_html($stato); ?>
                            </span>
                        </td>
                        <td style="padding: 15px; font-size: 0.85rem;">
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <span style="font-size: 1.2em;">👤</span>
                                <span style="color: #666;"><?php echo esc_html($created_by); ?></span>
                            </div>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=edit_preventivo&id=' . $preventivo_id)); ?>" 
                                   style="background: #007bff; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; transition: all 0.3s ease;"
                                   title="Modifica preventivo">
                                    ✏️ Modifica
                                </a>
                                <?php if ($pdf_url): ?>
                                <a href="<?php echo esc_url($pdf_url); ?>" target="_blank"
                                   style="background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; transition: all 0.3s ease;"
                                   title="Apri PDF">
                                    📄 PDF
                                </a>
                                <?php endif; ?>
                                <?php if ($excel_url): ?>
                                <a href="<?php echo esc_url($excel_url); ?>" target="_blank"
                                   style="background: #17a2b8; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; transition: all 0.3s ease;"
                                   title="Apri Excel">
                                    📊 Excel
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=dashboard_preventivi')); ?>" 
               style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 12px 25px; border-radius: 25px; text-decoration: none; font-weight: 600; display: inline-block; transition: all 0.3s ease;">
                📊 Visualizza Tutti i Preventivi (<?php echo count($recent_preventivi); ?> recenti)
            </a>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Messaggio nessun preventivo -->
    <div style="background: white; padding: 40px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); border-top: 5px solid #ffc107; text-align: center; margin-bottom: 30px;">
        <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.5;">📋</div>
        <h3 style="color: #2b1e1a; margin: 0 0 15px 0; font-size: 1.5rem;">Nessun preventivo recente</h3>
        <p style="color: #666; margin: 0 0 25px 0; font-size: 1.1rem;">
            Inizia subito creando il tuo primo preventivo per 747 Disco!
        </p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=new_preventivo')); ?>" 
           style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px 30px; border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 1.1rem; display: inline-block; transition: all 0.3s ease;">
            🎉 Crea Primo Preventivo
        </a>
    </div>
    <?php endif; ?>

</div>

<!-- ============================================================================ -->
<!-- STILI CSS AGGIUNTIVI -->
<!-- ============================================================================ -->

<style>
/* Stili specifici per la dashboard 747 Disco */
.disco747-crm-dashboard .wrap h1, 
.disco747-crm-dashboard .wrap h2, 
.disco747-crm-dashboard .wrap h3 {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.disco747-crm-dashboard .wp-list-table tr:hover {
    background: #f1f3f4 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.disco747-crm-dashboard .button:hover,
.disco747-crm-dashboard a[style*="linear-gradient"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.2);
    filter: brightness(110%);
}

/* Hover effects per le card statistiche */
.disco747-crm-dashboard [style*="linear-gradient"][style*="padding: 25px"]:hover {
    transform: translateY(-3px) scale(1.02);
    transition: all 0.3s ease;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

/* Hover effects per le sezioni sistema */
.disco747-crm-dashboard [style*="border-left: 5px"]:hover {
    transform: translateY(-2px);
    transition: transform 0.3s ease;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

/* Animazioni per pulsanti azioni preventivi */
.disco747-crm-dashboard a[title]:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Responsive per mobile */
@media (max-width: 768px) {
    .disco747-crm-dashboard [style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
    
    .disco747-crm-dashboard [style*="display: flex"] {
        flex-direction: column !important;
        align-items: center !important;
        gap: 10px !important;
    }
    
    .disco747-crm-dashboard .wrap {
        padding: 10px !important;
    }
    
    .disco747-crm-dashboard [style*="font-size: 2.2rem"] {
        font-size: 1.8rem !important;
    }
    
    .disco747-crm-dashboard [style*="font-size: 3rem"] {
        font-size: 2.2rem !important;
    }
    
    /* Tabella responsive */
    .disco747-crm-dashboard .wp-list-table th,
    .disco747-crm-dashboard .wp-list-table td {
        padding: 8px 4px !important;
        font-size: 0.85rem !important;
    }
    
    .disco747-crm-dashboard .wp-list-table th:nth-child(7),
    .disco747-crm-dashboard .wp-list-table td:nth-child(7) {
        display: none; /* Nasconde "Creato da" su mobile */
    }
}

/* Animazioni */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.disco747-crm-dashboard .wrap > div {
    animation: fadeInUp 0.6s ease-out;
}

/* Pulse animation per status indicators */
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}

.disco747-crm-dashboard [style*="✅"]:first-child {
    animation: pulse 2s infinite;
}

/* Compatibilità con temi WordPress */
.disco747-crm-dashboard * {
    box-sizing: border-box;
}

/* Scroll tabella preventivi */
.disco747-crm-dashboard [style*="overflow-x: auto"] {
    -webkit-overflow-scrolling: touch;
}
</style>

<!-- ============================================================================ -->
<!-- JAVASCRIPT PER INTERAZIONI E DATI REALI -->
<!-- ============================================================================ -->

<script>
jQuery(document).ready(function($) {
    console.log('747 Disco CRM Dashboard inizializzata v<?php echo $plugin_version; ?>');
    
    // Hover effects per i bottoni (compatibilità)
    $('.disco747-crm-dashboard .button').hover(
        function() {
            $(this).css({
                'background-color': '#c28a4d',
                'color': 'white',
                'transform': 'translateY(-2px)'
            });
        },
        function() {
            $(this).css({
                'background-color': '',
                'color': '',
                'transform': ''
            });
        }
    );
    
    // Click effect sulle card statistiche
    $('.disco747-crm-dashboard [style*="linear-gradient"][style*="padding: 25px"]').click(function() {
        $(this).css('transform', 'scale(0.98)');
        setTimeout(() => {
            $(this).css('transform', '');
        }, 150);
    });
    
    // Aggiungi tooltip ai bottoni di stato
    $('[style*="border-radius: 15px"][style*="font-size: 12px"]').each(function() {
        const text = $(this).text().trim();
        $(this).attr('title', 'Stato: ' + text);
    });
    
    // Tooltip per pulsanti azioni
    $('a[title]').tooltip();
    
    // Conferma eliminazione (se implementato)
    $('.delete-preventivo').click(function(e) {
        if (!confirm('Sei sicuro di voler eliminare questo preventivo?')) {
            e.preventDefault();
        }
    });
    
    // Auto-refresh statistiche ogni 5 minuti (opzionale)
    <?php if (defined('DISCO747_CRM_AUTO_REFRESH') && DISCO747_CRM_AUTO_REFRESH): ?>
    setInterval(function() {
        // Ricarica solo le statistiche via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'disco747_refresh_stats',
                nonce: '<?php echo wp_create_nonce('disco747_dashboard'); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Aggiorna le statistiche nella dashboard
                    updateStatistics(response.data);
                }
            }
        });
    }, 300000); // 5 minuti
    <?php endif; ?>
    
    // Funzione per aggiornare statistiche
    function updateStatistics(stats) {
        if (stats.total !== undefined) {
            $('[style*="linear-gradient(135deg, #c28a4d"] div:first-child').text(stats.total.toLocaleString());
        }
        if (stats.active !== undefined) {
            $('[style*="linear-gradient(135deg, #28a745"] div:first-child').text(stats.active.toLocaleString());
        }
        if (stats.confirmed !== undefined) {
            $('[style*="linear-gradient(135deg, #17a2b8"] div:first-child').text(stats.confirmed.toLocaleString());
        }
        if (stats.this_month !== undefined) {
            $('[style*="linear-gradient(135deg, #ffc107"] div:first-child').text(stats.this_month.toLocaleString());
        }
    }
    
    // Log per debug (rimuovere in produzione)
    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
    console.log('Statistiche dashboard:', <?php echo json_encode($stats); ?>);
    console.log('Preventivi recenti:', <?php echo count($recent_preventivi); ?>);
    console.log('Storage configurato:', <?php echo $is_storage_configured ? 'true' : 'false'; ?>);
    console.log('Database status:', '<?php echo $database_status; ?>');
    <?php endif; ?>
});
</script>

<?php
// ============================================================================
// FUNZIONI HELPER PER DATI REALI - USA SISTEMA ESISTENTE
// ============================================================================

/**
 * Converte file Google Drive in preventivi
 */
function disco747_parse_gdrive_files_to_preventivi($files, $limit = 8) {
    $preventivi = array();
    $count = 0;
    
    foreach ($files as $file) {
        if ($count >= $limit) break;
        
        $filename = basename($file['name'] ?? '');
        if (strpos($filename, '.pdf') === false) continue;
        
        $preventivo = array(
            'id' => $count + 1,
            'preventivo_id' => $count + 1,
            'nome_referente' => 'Cliente',
            'cognome_referente' => '',
            'email' => '',
            'tipo_evento' => 'Evento',
            'data_evento' => '',
            'importo' => 0,
            'importo_preventivo' => 0,
            'stato' => 'bozza',
            'confermato' => 0,
            'created_at' => $file['modified'] ?? $file['created'] ?? current_time('mysql'),
            'created_by_user' => 0,
            'utente_wp' => 'Sistema',
            'googledrive_url' => $file['url'] ?? $file['link'] ?? '',
            'pdf_url' => $file['url'] ?? $file['link'] ?? '',
            'excel_url' => ''
        );
        
        // Parse stato dal nome file: CONF = confermato, NO = annullato
        if (strpos($filename, 'CONF') === 0) {
            $preventivo['stato'] = 'confermato';
            $preventivo['confermato'] = 1;
        } elseif (strpos($filename, 'NO') === 0) {
            $preventivo['stato'] = 'annullato';
        }
        
        // Parse data: 14_10 = 14 ottobre
        if (preg_match('/(\d{1,2})_(\d{1,2})/', $filename, $matches)) {
            $giorno = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $mese = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $anno = date('Y');
            $preventivo['data_evento'] = "$anno-$mese-$giorno";
        }
        
        // Parse tipo evento: "CONF 14_10 Compleanno di Marta (Menu 747).pdf"
        if (preg_match('/(\d{1,2}_\d{1,2})\s+(.+?)\s+\(Menu/', $filename, $matches)) {
            $evento_completo = trim($matches[2]);
            $evento_parts = explode(' di ', $evento_completo);
            
            if (count($evento_parts) >= 2) {
                $preventivo['tipo_evento'] = trim($evento_parts[0]);
                $preventivo['nome_referente'] = trim($evento_parts[1]);
            } else {
                $preventivo['tipo_evento'] = $evento_completo;
            }
        }
        
        // Parse menu type
        if (preg_match('/Menu\s+([^)]+)/', $filename, $matches)) {
            $preventivo['tipo_menu'] = 'Menu ' . trim($matches[1]);
        }
        
        $preventivi[] = $preventivo;
        $count++;
    }
    
    return $preventivi;
}
function disco747_calculate_stats_from_preventivi($preventivi) {
    $stats = array(
        'total' => 0,
        'active' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'this_month' => 0
    );
    
    if (empty($preventivi)) {
        return $stats;
    }
    
    $current_month = date('Y-m');
    $stats['total'] = count($preventivi);
    
    foreach ($preventivi as $preventivo) {
        $prev_data = is_object($preventivo) ? $preventivo : (object) $preventivo;
        
        // Conta per stato
        $stato = $prev_data->stato ?? 'bozza';
        $confermato = !empty($prev_data->confermato) || !empty($prev_data->acconto);
        
        if ($confermato || $stato === 'confermato') {
            $stats['confirmed']++;
        } elseif ($stato === 'annullato') {
            $stats['cancelled']++;
        } else {
            $stats['active']++;
        }
        
        // Conta mese corrente
        $created_date = $prev_data->created_at ?? $prev_data->data_creazione ?? '';
        if ($created_date && date('Y-m', strtotime($created_date)) === $current_month) {
            $stats['this_month']++;
        }
    }
    
    return $stats;
}

/**
 * Fallback: Ottieni preventivi da query diretta
 */
function disco747_get_preventivi_direct_query($limit = 8) {
    global $wpdb;
    
    // Tabelle possibili del plugin (inclusa migrazione PreventiviParty)
    $possible_tables = array(
        $wpdb->prefix . 'preventivi_disco',
        $wpdb->prefix . 'disco747_preventivi',
        $wpdb->prefix . 'preventivi_party', // Dal vecchio plugin
        $wpdb->prefix . 'preventivi_747disco',
        $wpdb->prefix . 'preventivi'
    );
    
    foreach ($possible_tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            try {
                $preventivi = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table} 
                     ORDER BY created_at DESC, id DESC 
                     LIMIT %d", 
                    $limit
                ), ARRAY_A);
                
                if (!empty($preventivi)) {
                    return $preventivi;
                }
            } catch (Exception $e) {
                error_log('[747 Disco CRM] Errore query diretta: ' . $e->getMessage());
            }
        }
    }
    
    return array();
}

/**
 * Fallback: Ottieni statistiche da query diretta
 */
function disco747_get_stats_direct_query() {
    global $wpdb;
    
    $stats = array('total' => 0, 'active' => 0, 'confirmed' => 0, 'cancelled' => 0, 'this_month' => 0);
    
    $possible_tables = array(
        $wpdb->prefix . 'preventivi_disco',
        $wpdb->prefix . 'disco747_preventivi',
        $wpdb->prefix . 'preventivi_party'
    );
    
    foreach ($possible_tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            try {
                $stats['total'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table}"));
                $stats['confirmed'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE confermato = 1 OR stato = 'confermato'"));
                $stats['cancelled'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE stato = 'annullato'"));
                $stats['active'] = $stats['total'] - $stats['confirmed'] - $stats['cancelled'];
                $stats['this_month'] = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE (created_at >= %s OR data_creazione >= %s)",
                    date('Y-m-01'), date('Y-m-01')
                )));
                break;
            } catch (Exception $e) {
                error_log('[747 Disco CRM] Errore stats dirette: ' . $e->getMessage());
            }
        }
    }
    
    return $stats;
}

/**
 * Verifica status database
 */
function disco747_check_database_status() {
    global $wpdb;
    
    try {
        $wpdb->get_var("SELECT 1");
        
        // Verifica se esistono tabelle del plugin
        $tables = array(
            $wpdb->prefix . 'preventivi_disco',
            $wpdb->prefix . 'disco747_preventivi'
        );
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                return 'ok';
            }
        }
        
        return 'warning';
    } catch (Exception $e) {
        return 'error';
    }
}

?>

<!-- Fine Dashboard 747 Disco CRM -->