<?php
/**
 * Template per la pagina statistiche di 747 Disco CRM
 * Interfaccia dettagliata per analisi e statistiche admin
 * QUESTO FILE VA SOSTITUITO A: includes/admin/views/stats-page.php
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      11.4.2
 */

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Ottieni statistiche (passate dal controller o calcola qui)
$detailed_stats = $detailed_stats ?? array();
$stats_per_month = $detailed_stats['per_mese'] ?? array();
$stats_per_event_type = $detailed_stats['per_tipo_evento'] ?? array();
$stats_per_menu = $detailed_stats['per_menu'] ?? array();
$stats_per_author = $detailed_stats['per_autore'] ?? array();

// Calcola totali
$total_preventivi = 0;
$total_revenue = 0;
if (!empty($stats_per_month)) {
    $total_preventivi = array_sum(array_column($stats_per_month, 'count'));
    $total_revenue = array_sum(array_column($stats_per_month, 'revenue'));
}
$avg_value = $total_preventivi > 0 ? $total_revenue / $total_preventivi : 0;
?>

<div class="wrap">
    <h1>📈 747 Disco CRM - Statistiche Dettagliate</h1>
    
    <!-- Riepilogo Generale -->
    <div style="background: white; padding: 25px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>📊 Riepilogo Generale</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;"><?php echo number_format($total_preventivi); ?></div>
                <div style="font-size: 1rem;">Preventivi Totali</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;">€<?php echo number_format($total_revenue, 2); ?></div>
                <div style="font-size: 1rem;">Fatturato Totale</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #17a2b8 0%, #6610f2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;">€<?php echo number_format($avg_value, 2); ?></div>
                <div style="font-size: 1rem;">Valore Medio</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;"><?php echo date('Y'); ?></div>
                <div style="font-size: 1rem;">Anno Corrente</div>
            </div>
        </div>
    </div>

    <!-- Statistiche per Mese -->
    <div style="background: white; padding: 25px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>📅 Statistiche per Mese</h2>
        
        <?php if (!empty($stats_per_month)): ?>
        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="padding: 12px;">Mese</th>
                        <th style="padding: 12px; text-align: center;">Preventivi</th>
                        <th style="padding: 12px; text-align: center;">Confermati</th>
                        <th style="padding: 12px; text-align: right;">Fatturato</th>
                        <th style="padding: 12px; text-align: right;">Media</th>
                        <th style="padding: 12px; text-align: center;">Tasso Conversione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats_per_month as $month => $data): ?>
                    <tr>
                        <td style="padding: 12px; font-weight: 600;"><?php echo esc_html($month); ?></td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                <?php echo number_format($data['count']); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="background: #e8f5e8; color: #2e7d32; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                <?php echo number_format($data['confirmed'] ?? 0); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: right; font-weight: 600; color: #c28a4d;">
                            €<?php echo number_format($data['revenue'], 2); ?>
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            €<?php echo number_format($data['count'] > 0 ? $data['revenue'] / $data['count'] : 0, 2); ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <?php 
                            $conversion = $data['count'] > 0 ? (($data['confirmed'] ?? 0) / $data['count']) * 100 : 0;
                            $color = $conversion >= 70 ? '#28a745' : ($conversion >= 50 ? '#ffc107' : '#dc3545');
                            ?>
                            <span style="color: <?php echo $color; ?>; font-weight: 600;">
                                <?php echo number_format($conversion, 1); ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #6c757d;">
            <div style="font-size: 48px; margin-bottom: 15px;">📊</div>
            <p>Nessun dato disponibile per le statistiche mensili</p>
            <p style="margin-top: 10px;"><a href="<?php echo esc_url(home_url('/disco747-preventivi')); ?>" class="button button-primary">📝 Crea il primo preventivo</a></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Statistiche per Tipo Evento -->
    <div style="background: white; padding: 25px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>🎉 Statistiche per Tipo Evento</h2>
        
        <?php if (!empty($stats_per_event_type)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
            <?php foreach ($stats_per_event_type as $event_type => $data): ?>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #c28a4d;">
                <h4 style="color: #c28a4d; margin-bottom: 10px;">🎊 <?php echo esc_html(ucfirst($event_type)); ?></h4>
                <div style="display: flex; justify-content: space-between; margin: 8px 0;">
                    <span>Preventivi:</span>
                    <strong><?php echo number_format($data['count']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin: 8px 0;">
                    <span>Fatturato:</span>
                    <strong style="color: #c28a4d;">€<?php echo number_format($data['revenue'], 2); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin: 8px 0;">
                    <span>Media:</span>
                    <strong>€<?php echo number_format($data['count'] > 0 ? $data['revenue'] / $data['count'] : 0, 2); ?></strong>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #6c757d;">
            <div style="font-size: 48px; margin-bottom: 15px;">🎉</div>
            <p>Nessun dato disponibile per i tipi di evento</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Statistiche per Menu -->
    <div style="background: white; padding: 25px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>🍽️ Statistiche per Tipo Menu</h2>
        
        <?php if (!empty($stats_per_menu)): ?>
        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="padding: 12px;">Tipo Menu</th>
                        <th style="padding: 12px; text-align: center;">Preventivi</th>
                        <th style="padding: 12px; text-align: right;">Fatturato</th>
                        <th style="padding: 12px; text-align: right;">Prezzo Medio</th>
                        <th style="padding: 12px; text-align: center;">% del Totale</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats_per_menu as $menu_type => $data): ?>
                    <tr>
                        <td style="padding: 12px;">
                            <span style="font-weight: 600;">🍽️ <?php echo esc_html(ucfirst($menu_type)); ?></span>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                <?php echo number_format($data['count']); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: right; font-weight: 600; color: #c28a4d;">
                            €<?php echo number_format($data['revenue'], 2); ?>
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            €<?php echo number_format($data['count'] > 0 ? $data['revenue'] / $data['count'] : 0, 2); ?>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <?php 
                            $percentage = $total_preventivi > 0 ? ($data['count'] / $total_preventivi) * 100 : 0;
                            ?>
                            <div style="background: #e9ecef; border-radius: 10px; height: 8px; position: relative; margin: 5px 0;">
                                <div style="background: #c28a4d; height: 100%; border-radius: 10px; width: <?php echo $percentage; ?>%;"></div>
                            </div>
                            <span style="font-size: 12px; font-weight: 600;"><?php echo number_format($percentage, 1); ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #6c757d;">
            <div style="font-size: 48px; margin-bottom: 15px;">🍽️</div>
            <p>Nessun dato disponibile per i tipi di menu</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Statistiche per Autore -->
    <div style="background: white; padding: 25px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>👥 Statistiche per Autore</h2>
        
        <?php if (!empty($stats_per_author)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <?php foreach ($stats_per_author as $author => $data): ?>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 18px; font-weight: 600; margin-bottom: 10px;">
                    👤 <?php echo esc_html(ucfirst($author)); ?>
                </div>
                <div style="font-size: 1.8rem; font-weight: bold; margin: 8px 0;">
                    <?php echo number_format($data['count']); ?>
                </div>
                <div style="font-size: 14px; opacity: 0.9;">Preventivi Creati</div>
                <div style="margin-top: 10px; font-size: 14px;">
                    <strong>€<?php echo number_format($data['revenue'], 2); ?></strong>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #6c757d;">
            <div style="font-size: 48px; margin-bottom: 15px;">👥</div>
            <p>Nessun dato disponibile per gli autori</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Azioni e Export -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #dee2e6;">
        <h3>📋 Azioni e Export</h3>
        <div style="display: flex; gap: 15px; margin: 15px 0; flex-wrap: wrap;">
            <button class="button button-primary" onclick="exportStats('excel')">📊 Esporta Excel</button>
            <button class="button button-secondary" onclick="exportStats('csv')">📄 Esporta CSV</button>
            <button class="button button-secondary" onclick="printStats()">🖨️ Stampa Report</button>
            <button class="button button-secondary" onclick="refreshStats()">🔄 Aggiorna Dati</button>
        </div>
    </div>

    <!-- Link Rapidi -->
    <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #c28a4d;">
        <h3 style="color: #c28a4d;">⚡ Navigazione Rapida</h3>
        <div style="display: flex; gap: 15px; margin: 15px 0; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=disco747-crm'); ?>" class="button button-secondary">🏠 Home</a>
            <a href="<?php echo admin_url('admin.php?page=disco747-dashboard'); ?>" class="button button-secondary">📊 Dashboard</a>
            <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>" class="button button-secondary">⚙️ Impostazioni</a>
            <a href="<?php echo esc_url(home_url('/disco747-dashboard')); ?>" class="button button-primary" target="_blank">🔗 Dashboard Frontend</a>
        </div>
    </div>
</div>

<style>
/* Stili aggiuntivi per statistiche */
.wrap h1 {
    color: #2b1e1a;
    border-bottom: 3px solid #c28a4d;
    padding-bottom: 10px;
    margin-bottom: 25px;
}

.wp-list-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.wp-list-table tr:nth-child(even) {
    background: #f8f9fa;
}

.wp-list-table tr:hover {
    background: #e9ecef;
}

h2 {
    border-bottom: 2px solid #c28a4d;
    padding-bottom: 10px;
    color: #2b1e1a;
    margin-bottom: 20px;
}

.button {
    transition: all 0.3s ease;
}

.button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

@media (max-width: 768px) {
    .wp-list-table {
        font-size: 12px;
    }
    
    .wp-list-table th,
    .wp-list-table td {
        padding: 8px !important;
    }
    
    div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    div[style*="display: flex"] {
        flex-direction: column;
        gap: 10px !important;
    }
}
</style>

<script>
function exportStats(format) {
    if (typeof ajaxurl !== 'undefined' && typeof disco747Admin !== 'undefined') {
        const url = ajaxurl + '?action=disco747_export_stats&format=' + format + '&nonce=' + disco747Admin.nonce;
        window.open(url, '_blank');
    } else {
        alert('Funzione di export in sviluppo');
    }
}

function printStats() {
    window.print();
}

function refreshStats() {
    location.reload();
}

// Aggiorna automaticamente ogni 5 minuti
setInterval(function() {
    const lastUpdate = document.createElement('div');
    lastUpdate.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 10px 15px; border-radius: 5px; font-size: 12px; z-index: 9999;';
    lastUpdate.textContent = 'Dati aggiornati: ' + new Date().toLocaleTimeString();
    document.body.appendChild(lastUpdate);
    
    setTimeout(() => {
        if (lastUpdate.parentNode) {
            document.body.removeChild(lastUpdate);
        }
    }, 3000);
}, 300000);
</script>