<?php
/**
 * Template Dashboard Sincronizzazione Preventivi - 747 Disco CRM
 * 
 * NUOVO FILE: templates/admin/dashboard-sync-preventivi.php
 * 
 * Template per la dashboard unificata del "Registro Preventivi" che sincronizza
 * i preventivi creati dal form con i file Excel presenti su Google Drive.
 * 
 * Questo template viene incluso da class-disco747-admin.php nel metodo 
 * render_dashboard_sync_preventivi() e utilizza i dati preparati dalla classe admin.
 *
 * @package    Disco747_CRM
 * @subpackage Admin/Templates
 * @since      11.6.1-ENHANCED-SYNC
 * @version    11.6.1-ENHANCED-SYNC
 */

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Variabili preparate dalla classe admin
// $registry_stats, $preventivi sono passate dal metodo render_dashboard_sync_preventivi()

// Preparazione dati se non definite (safety)
if (!isset($registry_stats)) {
    $registry_stats = array(
        'total' => 0,
        'allineati' => 0, 
        'solo_drive' => 0,
        'da_aggiornare' => 0,
        'errori' => 0
    );
}

if (!isset($preventivi)) {
    $preventivi = array();
}

// Filtri dalla query string
$filters = array(
    'search' => sanitize_text_field($_GET['search'] ?? ''),
    'stato_sync' => sanitize_key($_GET['stato_sync'] ?? ''),
    'anno' => intval($_GET['anno'] ?? 0),
    'mese' => intval($_GET['mese'] ?? 0),
    'menu' => sanitize_key($_GET['menu'] ?? '')
);

// Configurazione sync automatica
$auto_sync_enabled = get_option('disco747_auto_sync_enabled', true);
$last_sync_timestamp = get_option('disco747_last_sync_drive', 0);
$next_sync_timestamp = wp_next_scheduled('disco747_daily_sync_cron');

// Formatta timestamps
$ultima_sync = $last_sync_timestamp ? wp_date('d/m/Y H:i', $last_sync_timestamp) : 'Mai eseguita';
$prossima_sync = $next_sync_timestamp ? wp_date('d/m/Y H:i', $next_sync_timestamp) : 'Non programmata';
?>

<div class="wrap disco747-sync-dashboard" style="margin-right: 20px;">
    <!-- Header con design 747 Disco -->
    <div style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 30px; margin: -10px -20px 30px -20px; border-radius: 8px; position: relative; overflow: hidden;">
        <!-- Pattern decorativo -->
        <div style="position: absolute; top: 0; right: 0; width: 200px; height: 100%; background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"50\" cy=\"50\" r=\"1\" fill=\"%23c28a4d\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>'); opacity: 0.3;"></div>
        
        <h1 style="color: #c28a4d; font-size: 32px; margin: 0; display: flex; align-items: center; gap: 15px; position: relative; z-index: 1;">
            <span class="dashicons dashicons-database-export" style="font-size: 36px;"></span>
            Registro Preventivi Unificato
        </h1>
        <p style="color: #cccccc; margin: 10px 0 0 0; font-size: 16px; position: relative; z-index: 1;">
            Dashboard di sincronizzazione tra preventivi del form e file Excel su Google Drive
        </p>
    </div>

    <!-- Notice Area per feedback -->
    <div id="sync-notices"></div>

    <!-- Statistiche con design cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border: 2px solid #c28a4d; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 4px 15px rgba(194, 138, 77, 0.1); transition: transform 0.3s ease;">
            <div style="color: #c28a4d; font-size: 36px; font-weight: bold; margin: 0 0 8px 0;"><?php echo $registry_stats['total']; ?></div>
            <p style="margin: 0; color: #666; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Totale Preventivi</p>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #ffffff 0%, #f0fff4 100%); border: 2px solid #28a745; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.1); transition: transform 0.3s ease;">
            <div style="color: #28a745; font-size: 36px; font-weight: bold; margin: 0 0 8px 0;"><?php echo $registry_stats['allineati']; ?></div>
            <p style="margin: 0; color: #666; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Allineati</p>
            <small style="color: #28a745; font-size: 12px;">✓ Sincronizzati</small>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 100%); border: 2px solid #007cba; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 4px 15px rgba(0, 124, 186, 0.1); transition: transform 0.3s ease;">
            <div style="color: #007cba; font-size: 36px; font-weight: bold; margin: 0 0 8px 0;"><?php echo $registry_stats['solo_drive']; ?></div>
            <p style="margin: 0; color: #666; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Solo Drive</p>
            <small style="color: #007cba; font-size: 12px;">📁 Solo su Google Drive</small>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #ffffff 0%, #fffbf0 100%); border: 2px solid #ffc107; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 4px 15px rgba(255, 193, 7, 0.1); transition: transform 0.3s ease;">
            <div style="color: #e68900; font-size: 36px; font-weight: bold; margin: 0 0 8px 0;"><?php echo $registry_stats['da_aggiornare']; ?></div>
            <p style="margin: 0; color: #666; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Da Aggiornare</p>
            <small style="color: #e68900; font-size: 12px;">⚡ Modifiche pending</small>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, #ffffff 0%, #fff5f5 100%); border: 2px solid #dc3545; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.1); transition: transform 0.3s ease;">
            <div style="color: #dc3545; font-size: 36px; font-weight: bold; margin: 0 0 8px 0;"><?php echo $registry_stats['errori']; ?></div>
            <p style="margin: 0; color: #666; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Errori</p>
            <small style="color: #dc3545; font-size: 12px;">❌ Da correggere</small>
        </div>
    </div>

    <!-- Controlli Sincronizzazione -->
    <div style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border: 1px solid #dee2e6; border-radius: 12px; padding: 30px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 20px;">
            <div>
                <h3 style="margin: 0 0 15px 0; color: #1a1a1a; font-size: 20px; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-cloud" style="color: #c28a4d;"></span>
                    Sincronizzazione Google Drive
                </h3>
                <div style="display: grid; grid-template-columns: auto auto; gap: 15px 30px; font-size: 14px;">
                    <strong>Ultima sync:</strong> <span style="color: #666;"><?php echo esc_html($ultima_sync); ?></span>
                    <strong>Prossima sync:</strong> <span style="color: #666;"><?php echo esc_html($prossima_sync); ?></span>
                </div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 15px; align-items: end;">
                <!-- Toggle Auto Sync -->
                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; background: #fff; padding: 10px 15px; border-radius: 8px; border: 1px solid #ddd;">
                    <input type="checkbox" id="auto-sync-toggle" <?php checked($auto_sync_enabled); ?> 
                           style="transform: scale(1.3); margin: 0;">
                    <span style="font-weight: 600; color: #333;">Sync automatica 23:30</span>
                </label>
                
                <!-- Pulsante Sincronizza Ora -->
                <button id="sync-now-btn" class="button button-primary" style="background: linear-gradient(135deg, #c28a4d 0%, #b8941f 100%); border: none; font-weight: 600; padding: 12px 25px; border-radius: 8px; font-size: 15px; box-shadow: 0 3px 10px rgba(194, 138, 77, 0.3); transition: transform 0.2s ease;">
                    <span class="dashicons dashicons-update" style="margin-right: 8px; font-size: 16px;"></span>
                    Sincronizza Ora
                </button>
            </div>
        </div>
        
        <!-- Progress Bar (nascosta di default) -->
        <div id="sync-progress" style="display: none; margin-top: 25px;">
            <div style="background: #e9ecef; border-radius: 6px; height: 12px; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                <div id="sync-progress-bar" style="background: linear-gradient(90deg, #c28a4d, #b8941f); height: 100%; width: 0%; transition: width 0.3s ease; border-radius: 6px;"></div>
            </div>
            <p id="sync-status" style="margin: 12px 0 0 0; font-style: italic; color: #666; text-align: center;">Preparazione sincronizzazione...</p>
        </div>
    </div>

    <!-- Filtri con design moderno -->
    <form method="get" action="" style="background: #ffffff; border: 1px solid #e1e5e9; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.04);">
        <input type="hidden" name="page" value="disco747-dashboard-sync-preventivi">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: end;">
            <!-- Ricerca -->
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">🔍 Ricerca</label>
                <input type="text" name="search" value="<?php echo esc_attr($filters['search']); ?>" 
                       placeholder="Nome, email, evento..." 
                       style="width: 100%; padding: 12px 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px; transition: border-color 0.2s ease;">
            </div>
            
            <!-- Stato Sync -->
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">📊 Stato Sincronizzazione</label>
                <select name="stato_sync" style="width: 100%; padding: 12px 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px; background: #fff;">
                    <option value="">Tutti gli stati</option>
                    <option value="allineato" <?php selected($filters['stato_sync'], 'allineato'); ?>>✅ Allineato</option>
                    <option value="solo_drive" <?php selected($filters['stato_sync'], 'solo_drive'); ?>>📁 Solo Drive</option>
                    <option value="da_aggiornare" <?php selected($filters['stato_sync'], 'da_aggiornare'); ?>>⚡ Da Aggiornare</option>
                    <option value="errore" <?php selected($filters['stato_sync'], 'errore'); ?>>❌ Errore</option>
                </select>
            </div>
            
            <!-- Anno -->
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">📅 Anno</label>
                <select name="anno" style="width: 100%; padding: 12px 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px; background: #fff;">
                    <option value="">Tutti gli anni</option>
                    <?php for($year = date('Y') + 1; $year >= 2020; $year--): ?>
                        <option value="<?php echo $year; ?>" <?php selected($filters['anno'], $year); ?>><?php echo $year; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <!-- Mese -->
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">🗓️ Mese</label>
                <select name="mese" style="width: 100%; padding: 12px 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px; background: #fff;">
                    <option value="">Tutti i mesi</option>
                    <?php 
                    $mesi = array(1=>'Gennaio', 2=>'Febbraio', 3=>'Marzo', 4=>'Aprile', 5=>'Maggio', 6=>'Giugno',
                                  7=>'Luglio', 8=>'Agosto', 9=>'Settembre', 10=>'Ottobre', 11=>'Novembre', 12=>'Dicembre');
                    foreach($mesi as $num => $nome): ?>
                        <option value="<?php echo $num; ?>" <?php selected($filters['mese'], $num); ?>><?php echo $nome; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Menu -->
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px;">🍽️ Menu</label>
                <select name="menu" style="width: 100%; padding: 12px 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 14px; background: #fff;">
                    <option value="">Tutti i menu</option>
                    <option value="Menu 7" <?php selected($filters['menu'], 'Menu 7'); ?>>Menu 7</option>
                    <option value="Menu 74" <?php selected($filters['menu'], 'Menu 74'); ?>>Menu 74</option>
                    <option value="Menu 747" <?php selected($filters['menu'], 'Menu 747'); ?>>Menu 747</option>
                </select>
            </div>
            
            <!-- Pulsanti -->
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="button button-secondary" style="padding: 12px 20px; border-radius: 8px; font-weight: 600;">
                    <span class="dashicons dashicons-filter" style="margin-right: 5px;"></span>
                    Filtra
                </button>
                <a href="<?php echo admin_url('admin.php?page=disco747-dashboard-sync-preventivi'); ?>" 
                   class="button" style="padding: 12px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center;">
                    <span class="dashicons dashicons-dismiss" style="margin-right: 5px;"></span>
                    Reset
                </a>
            </div>
        </div>
    </form>

    <!-- Tabella Preventivi con design moderno -->
    <div style="background: #ffffff; border: 1px solid #e1e5e9; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.04);">
        <div class="table-responsive">
            <table class="wp-list-table widefat fixed striped" style="border: none; margin: 0;">
                <thead>
                    <tr style="background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);">
                        <th style="color: #c28a4d; padding: 20px 15px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">ID</th>
                        <th style="color: #c28a4d; padding: 20px 15px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Cliente</th>
                        <th style="color: #c28a4d; padding: 20px 15px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Evento</th>
                        <th style="color: #c28a4d; padding: 20px 15px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Data</th>
                        <th style="color: #c28a4d; padding: 20px 15px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Menu</th>
                        <th style="color: #c28a4d; padding: 20px 15px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Stato Sync</th>
                        <th style="color: #c28a4d; padding: 20px 15px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($preventivi)): ?>
                        <?php foreach ($preventivi as $preventivo): ?>
                            <?php 
                            // Determina colori e icone per stato sync
                            $sync_status = $preventivo->sync_status ?? 'sconosciuto';
                            $status_config = array(
                                'allineato' => array('color' => '#28a745', 'bg' => '#d4edda', 'icon' => '✅', 'text' => 'Allineato'),
                                'solo_drive' => array('color' => '#007cba', 'bg' => '#cce7f0', 'icon' => '📁', 'text' => 'Solo Drive'),
                                'da_aggiornare' => array('color' => '#e68900', 'bg' => '#fff3cd', 'icon' => '⚡', 'text' => 'Da Aggiornare'),
                                'errore' => array('color' => '#dc3545', 'bg' => '#f8d7da', 'icon' => '❌', 'text' => 'Errore')
                            );
                            $config = $status_config[$sync_status] ?? array('color' => '#6c757d', 'bg' => '#f8f9fa', 'icon' => '❓', 'text' => 'Sconosciuto');
                            ?>
                            <tr class="preventivo-row" style="transition: all 0.2s ease;">
                                <td style="padding: 18px 15px;">
                                    <strong style="color: #c28a4d; font-size: 16px;">
                                        <?php echo esc_html($preventivo->preventivo_id ?? '#' . $preventivo->id); ?>
                                    </strong>
                                </td>
                                <td style="padding: 18px 15px;">
                                    <div style="line-height: 1.4;">
                                        <strong style="color: #333; font-size: 15px;">
                                            <?php echo esc_html($preventivo->nome_referente); ?>
                                            <?php if (!empty($preventivo->cognome_referente)): ?>
                                                <?php echo ' ' . esc_html($preventivo->cognome_referente); ?>
                                            <?php endif; ?>
                                        </strong>
                                        <?php if (!empty($preventivo->mail)): ?>
                                            <br><small style="color: #666; font-size: 13px;">
                                                📧 <?php echo esc_html($preventivo->mail); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($preventivo->cellulare)): ?>
                                            <br><small style="color: #666; font-size: 13px;">
                                                📱 <?php echo esc_html($preventivo->cellulare); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 18px 15px;">
                                    <div style="line-height: 1.4;">
                                        <strong style="color: #333; font-size: 14px;"><?php echo esc_html($preventivo->tipo_evento); ?></strong>
                                        <?php if (!empty($preventivo->numero_invitati)): ?>
                                            <br><small style="color: #666; font-size: 12px;">
                                                👥 <?php echo intval($preventivo->numero_invitati); ?> persone
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 18px 15px;">
                                    <strong style="color: #333;">
                                        <?php echo wp_date('d/m/Y', strtotime($preventivo->data_evento)); ?>
                                    </strong>
                                    <br><small style="color: #666; font-size: 12px;">
                                        <?php 
                                        $giorni_diff = ceil((strtotime($preventivo->data_evento) - time()) / (60*60*24));
                                        if ($giorni_diff > 0) {
                                            echo "📅 tra {$giorni_diff} giorni";
                                        } elseif ($giorni_diff === 0) {
                                            echo "🎉 oggi";
                                        } else {
                                            echo "✅ " . abs($giorni_diff) . " giorni fa";
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td style="padding: 18px 15px;">
                                    <span style="background: linear-gradient(135deg, #c28a4d, #b8941f); color: white; padding: 6px 12px; border-radius: 15px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <?php echo esc_html($preventivo->tipo_menu ?? 'N/D'); ?>
                                    </span>
                                </td>
                                <td style="padding: 18px 15px;">
                                    <div style="display: flex; flex-direction: column; gap: 5px;">
                                        <span style="background: <?php echo $config['bg']; ?>; color: <?php echo $config['color']; ?>; padding: 6px 12px; border-radius: 15px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; gap: 5px; max-width: fit-content;">
                                            <?php echo $config['icon']; ?>
                                            <?php echo $config['text']; ?>
                                        </span>
                                        
                                        <?php if (!empty($preventivo->sync_error)): ?>
                                            <small style="color: #dc3545; font-size: 11px; font-style: italic; line-height: 1.2; max-width: 150px;">
                                                <?php echo esc_html(substr($preventivo->sync_error, 0, 60)) . (strlen($preventivo->sync_error) > 60 ? '...' : ''); ?>
                                            </small>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($preventivo->sync_updated_at)): ?>
                                            <small style="color: #666; font-size: 10px;">
                                                🕒 <?php echo wp_date('d/m H:i', strtotime($preventivo->sync_updated_at)); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 18px 15px;">
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <?php if (!empty($preventivo->drive_file_id)): ?>
                                            <a href="https://drive.google.com/file/d/<?php echo esc_attr($preventivo->drive_file_id); ?>/view" 
                                               target="_blank" class="button button-small" 
                                               title="Apri su Google Drive"
                                               style="background: #4285f4; color: white; border: none; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 12px;">
                                                <span class="dashicons dashicons-external" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($preventivo->preventivo_id) || !empty($preventivo->id)): ?>
                                            <button class="sync-single-btn button button-small" 
                                                    data-id="<?php echo esc_attr($preventivo->preventivo_id ?? $preventivo->id); ?>" 
                                                    title="Risincronizza questo preventivo"
                                                    style="background: #c28a4d; color: white; border: none; padding: 6px 10px; border-radius: 6px; font-size: 12px;">
                                                <span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="padding: 60px; text-align: center; color: #666;">
                                <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;">📋</div>
                                <h3 style="margin: 0 0 15px 0; color: #999;">Nessun preventivo trovato</h3>
                                <p style="margin: 0 0 25px 0; color: #666;">
                                    <?php if (!empty(array_filter($filters))): ?>
                                        Nessun preventivo corrisponde ai filtri selezionati.
                                        <br><a href="<?php echo admin_url('admin.php?page=disco747-dashboard-sync-preventivi'); ?>" style="color: #c28a4d;">Rimuovi filtri</a>
                                    <?php else: ?>
                                        Non ci sono ancora preventivi sincronizzati.
                                    <?php endif; ?>
                                </p>
                                <button id="first-sync-btn" class="button button-primary" style="background: #c28a4d; border-color: #c28a4d; font-size: 16px; padding: 12px 25px; border-radius: 8px;">
                                    <span class="dashicons dashicons-download" style="margin-right: 8px;"></span>
                                    Esegui prima sincronizzazione
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Log Sincronizzazione (collapsible) -->
    <div style="margin-top: 30px;">
        <button id="toggle-sync-log" class="button" style="margin-bottom: 15px; padding: 10px 20px; border-radius: 8px;">
            <span class="dashicons dashicons-editor-code" style="margin-right: 8px;"></span>
            <span id="log-toggle-text">Mostra Log Sincronizzazione</span>
        </button>
        
        <div id="sync-log-container" style="display: none; background: #1a1a1a; color: #00ff41; padding: 25px; border-radius: 12px; font-family: 'Courier New', 'Monaco', monospace; max-height: 400px; overflow-y: auto; border: 2px solid #333; position: relative;">
            <div style="position: absolute; top: 10px; right: 15px; color: #666; font-size: 12px;">
                <span class="dashicons dashicons-admin-tools"></span> Terminal 747 Disco CRM
            </div>
            <div id="sync-log" style="margin-top: 20px; line-height: 1.6;">
                <div style="color: #666; font-style: italic;">[In attesa di sincronizzazione...]</div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Avanzato per Interazioni -->
<script>
jQuery(document).ready(function($) {
    
    // ========================================================================
    // VARIABILI GLOBALI
    // ========================================================================
    
    let syncInProgress = false;
    const nonce = '<?php echo wp_create_nonce("disco747_admin_nonce"); ?>';
    
    // ========================================================================
    // FUNZIONI HELPER
    // ========================================================================
    
    function showNotice(type, message, autoHide = true) {
        const noticeClasses = {
            'success': 'notice-success',
            'error': 'notice-error', 
            'warning': 'notice-warning',
            'info': 'notice-info'
        };
        
        const notice = $(`
            <div class="notice ${noticeClasses[type]} is-dismissible" style="margin: 20px 0; padding: 15px; border-radius: 8px;">
                <p style="margin: 0; font-weight: 500;"><strong>${message}</strong></p>
            </div>
        `);
        
        $('#sync-notices').prepend(notice);
        
        // Auto-hide dopo 5 secondi per messaggi di successo
        if (autoHide && type === 'success') {
            setTimeout(() => notice.fadeOut(() => notice.remove()), 5000);
        }
        
        // Scroll in alto per mostrare la notice
        $('html, body').animate({ scrollTop: 0 }, 500);
    }
    
    function updateSyncProgress(percentage, message) {
        $('#sync-progress-bar').css('width', percentage + '%');
        $('#sync-status').text(message);
    }
    
    function addLogEntry(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const colors = {
            'info': '#00ff41',
            'success': '#28a745', 
            'error': '#ff6b6b',
            'warning': '#ffc107'
        };
        
        const logEntry = $(`
            <div style="color: ${colors[type]}; margin-bottom: 5px;">
                [${timestamp}] ${message}
            </div>
        `);
        
        $('#sync-log').append(logEntry);
        
        // Auto-scroll to bottom
        const logContainer = $('#sync-log-container');
        if (logContainer.is(':visible')) {
            logContainer.scrollTop(logContainer[0].scrollHeight);
        }
    }
    
    // ========================================================================
    // SINCRONIZZAZIONE MANUALE "Sincronizza Ora"
    // ========================================================================
    
    $('#sync-now-btn, #first-sync-btn').on('click', function() {
        if (syncInProgress) {
            showNotice('warning', 'Sincronizzazione già in corso...');
            return;
        }
        
        const $btn = $(this);
        const originalHtml = $btn.html();
        const isFirstSync = $btn.attr('id') === 'first-sync-btn';
        
        syncInProgress = true;
        
        // UI Changes
        $btn.prop('disabled', true)
            .html('<span class="dashicons dashicons-update disco-spin" style="margin-right: 8px;"></span>Sincronizzando...');
        
        $('#sync-progress').fadeIn();
        $('#sync-log-container').fadeIn();
        $('#sync-log').html(''); // Clear previous logs
        
        addLogEntry('🚀 Avvio sincronizzazione Google Drive...', 'info');
        updateSyncProgress(10, 'Inizializzazione...');
        
        // AJAX Request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_sync_drive_now',
                nonce: nonce,
                force_full_sync: isFirstSync
            },
            timeout: 300000, // 5 minuti timeout
            
            success: function(response) {
                updateSyncProgress(100, 'Sincronizzazione completata!');
                
                if (response.success) {
                    const data = response.data;
                    
                    addLogEntry('✅ Sincronizzazione completata con successo!', 'success');
                    addLogEntry(`📊 Statistiche: Creati: ${data.created}, Aggiornati: ${data.updated}, Errori: ${data.errors}`, 'info');
                    
                    if (data.files_processed) {
                        addLogEntry(`📁 File processati: ${data.files_processed}`, 'info');
                    }
                    
                    if (data.execution_time) {
                        addLogEntry(`⏱️ Tempo esecuzione: ${data.execution_time} secondi`, 'info');
                    }
                    
                    // Mostra errori se presenti
                    if (data.error_messages && data.error_messages.length > 0) {
                        addLogEntry('⚠️ Errori riscontrati:', 'warning');
                        data.error_messages.forEach(error => {
                            addLogEntry(`  • ${error}`, 'error');
                        });
                    }
                    
                    // Messaggio di successo
                    let successMsg = `Sincronizzazione completata! Creati: ${data.created}, Aggiornati: ${data.updated}`;
                    if (data.errors > 0) {
                        successMsg += `, Errori: ${data.errors}`;
                    }
                    
                    showNotice('success', successMsg);
                    
                    // Ricarica pagina dopo 3 secondi per mostrare nuovi dati
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                    
                } else {
                    // Errore dalla risposta
                    const errorMsg = response.data || 'Errore sconosciuto durante la sincronizzazione';
                    addLogEntry(`❌ Errore: ${errorMsg}`, 'error');
                    showNotice('error', `Errore sincronizzazione: ${errorMsg}`, false);
                }
            },
            
            error: function(xhr, status, error) {
                updateSyncProgress(0, 'Errore di comunicazione');
                addLogEntry(`❌ Errore AJAX: ${error}`, 'error');
                addLogEntry(`Status: ${status}, Response: ${xhr.responseText}`, 'error');
                showNotice('error', 'Errore di comunicazione con il server. Riprova.', false);
            },
            
            complete: function() {
                // Ripristina pulsante
                syncInProgress = false;
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
    
    // ========================================================================
    // TOGGLE AUTO SYNC
    // ========================================================================
    
    $('#auto-sync-toggle').on('change', function() {
        const $toggle = $(this);
        const enabled = $toggle.is(':checked');
        const originalState = !enabled; // Stato prima del change
        
        // Disabilita temporaneamente
        $toggle.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_toggle_auto_sync',
                nonce: nonce,
                enabled: enabled ? 1 : 0
            },
            
            success: function(response) {
                if (response.success) {
                    const message = enabled ? 
                        '✅ Sincronizzazione automatica attivata per le 23:30' : 
                        '⏹️ Sincronizzazione automatica disattivata';
                    
                    showNotice('success', message);
                    addLogEntry(message, 'success');
                    
                    // Aggiorna info prossima sync se fornita
                    if (response.data.next_run) {
                        // TODO: Aggiornare display prossima sync
                    }
                } else {
                    // Errore - ripristina stato precedente
                    $toggle.prop('checked', originalState);
                    const errorMsg = response.data || 'Errore aggiornamento impostazioni';
                    showNotice('error', `Errore: ${errorMsg}`, false);
                }
            },
            
            error: function() {
                // Errore comunicazione - ripristina stato
                $toggle.prop('checked', originalState);
                showNotice('error', 'Errore di comunicazione. Riprova.', false);
            },
            
            complete: function() {
                $toggle.prop('disabled', false);
            }
        });
    });
    
    // ========================================================================
    // SINCRONIZZAZIONE SINGOLO PREVENTIVO
    // ========================================================================
    
    $('.sync-single-btn').on('click', function() {
        const $btn = $(this);
        const preventivoId = $btn.data('id');
        const originalHtml = $btn.html();
        
        if (!preventivoId) {
            showNotice('error', 'ID preventivo non valido');
            return;
        }
        
        $btn.prop('disabled', true)
            .html('<span class="dashicons dashicons-update disco-spin" style="font-size: 14px;"></span>');
        
        addLogEntry(`🔄 Sincronizzazione preventivo ${preventivoId}...`, 'info');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_sync_single_preventivo',
                nonce: nonce,
                preventivo_id: preventivoId
            },
            
            success: function(response) {
                if (response.success) {
                    addLogEntry(`✅ Preventivo ${preventivoId} sincronizzato`, 'success');
                    showNotice('success', `Preventivo ${preventivoId} sincronizzato con successo!`);
                    
                    // Ricarica solo la riga della tabella (o tutta la pagina)
                    setTimeout(() => location.reload(), 1500);
                } else {
                    const errorMsg = response.data || 'Errore sincronizzazione';
                    addLogEntry(`❌ Errore sync ${preventivoId}: ${errorMsg}`, 'error');
                    showNotice('error', `Errore sincronizzazione: ${errorMsg}`, false);
                }
            },
            
            error: function() {
                addLogEntry(`❌ Errore comunicazione sync ${preventivoId}`, 'error');
                showNotice('error', 'Errore di comunicazione', false);
            },
            
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
    
    // ========================================================================
    // TOGGLE LOG SINCRONIZZAZIONE
    // ========================================================================
    
    $('#toggle-sync-log').on('click', function() {
        const $container = $('#sync-log-container');
        const $toggleText = $('#log-toggle-text');
        
        if ($container.is(':visible')) {
            $container.slideUp();
            $toggleText.text('Mostra Log Sincronizzazione');
        } else {
            $container.slideDown();
            $toggleText.text('Nascondi Log Sincronizzazione');
        }
    });
    
    // ========================================================================
    // EFFETTI VISIVI E UX ENHANCEMENTS
    // ========================================================================
    
    // Hover effects per stat cards
    $('.stat-card').on('mouseenter', function() {
        $(this).css('transform', 'translateY(-3px) scale(1.02)');
    }).on('mouseleave', function() {
        $(this).css('transform', 'translateY(0) scale(1)');
    });
    
    // Row hover effects
    $('.preventivo-row').on('mouseenter', function() {
        $(this).css({
            'background-color': '#f8f9fa',
            'transform': 'translateX(3px)'
        });
    }).on('mouseleave', function() {
        $(this).css({
            'background-color': '',
            'transform': 'translateX(0)'
        });
    });
    
    // Focus styles per inputs
    $('input, select').on('focus', function() {
        $(this).css('border-color', '#c28a4d');
    }).on('blur', function() {
        $(this).css('border-color', '#e1e5e9');
    });
    
    // Auto-submit filters on change (opzionale)
    $('.disco747-sync-dashboard select[name="stato_sync"], .disco747-sync-dashboard select[name="anno"], .disco747-sync-dashboard select[name="mese"], .disco747-sync-dashboard select[name="menu"]').on('change', function() {
        // Uncomment per auto-submit
        // $(this).closest('form').submit();
    });
});
</script>

<!-- CSS Avanzato -->
<style>
/* Animazioni personalizzate */
@keyframes disco-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.disco-spin {
    animation: disco-spin 1s linear infinite;
}

/* Responsive enhancements */
@media (max-width: 1200px) {
    .disco747-sync-dashboard [style*="grid-template-columns"] {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
    }
}

@media (max-width: 768px) {
    .disco747-sync-dashboard [style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    .disco747-sync-dashboard .table-responsive {
        overflow-x: auto;
    }
    
    .disco747-sync-dashboard table {
        min-width: 800px;
        font-size: 13px;
    }
    
    .disco747-sync-dashboard table th,
    .disco747-sync-dashboard table td {
        padding: 12px 8px !important;
    }
    
    /* Stack controls on mobile */
    .disco747-sync-dashboard [style*="justify-content: space-between"] {
        flex-direction: column !important;
        gap: 20px !important;
        align-items: stretch !important;
    }
}

/* Scrollbar personalizzata per log */
#sync-log-container::-webkit-scrollbar {
    width: 8px;
}

#sync-log-container::-webkit-scrollbar-track {
    background: #2a2a2a;
    border-radius: 4px;
}

#sync-log-container::-webkit-scrollbar-thumb {
    background: #c28a4d;
    border-radius: 4px;
}

#sync-log-container::-webkit-scrollbar-thumb:hover {
    background: #b8941f;
}

/* Pulse effect per pulsanti importanti */
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(194, 138, 77, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(194, 138, 77, 0); }
    100% { box-shadow: 0 0 0 0 rgba(194, 138, 77, 0); }
}

#sync-now-btn:not(:disabled):hover {
    animation: pulse 2s infinite;
}

/* Tooltip styles */
[title] {
    position: relative;
}

/* Enhanced focus styles */
button:focus, input:focus, select:focus {
    outline: 2px solid #c28a4d !important;
    outline-offset: 2px !important;
}

/* Loading states */
.loading {
    opacity: 0.6;
    pointer-events: none;
}
</style>