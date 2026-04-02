<?php
/**
 * Dashboard Principale 747 Disco CRM - ENHANCED iOS STYLE
 * 
 * FEATURES:
 * - Filtri mese/anno sopra al calendario
 * - Pulsante grande Nuovo Preventivo
 * - Grafici andamento (Conferme, Annullamenti, Attivi)
 * - Calendario settimanale iOS
 * - Pulsante Modifica negli eventi
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @version    14.0.0
 */

if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$user_name = $current_user->display_name ?? 'Utente';
$version = '14.0.0';

// Carica tabella preventivi
global $wpdb;
$table = $wpdb->prefix . 'disco747_preventivi';

// Parametri filtro calendario
$calendario_mese = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : date('n');
$calendario_anno = isset($_GET['cal_year']) ? intval($_GET['cal_year']) : date('Y');

// Carica eventi del mese selezionato
$primo_giorno = "{$calendario_anno}-" . sprintf('%02d', $calendario_mese) . "-01";
$ultimo_giorno = date('Y-m-t', strtotime($primo_giorno));

$eventi_calendario = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} 
     WHERE data_evento BETWEEN %s AND %s 
     AND stato IN ('attivo', 'confermato')
     ORDER BY data_evento ASC",
    $primo_giorno,
    $ultimo_giorno
), ARRAY_A);

// Raggruppa eventi per data
$eventi_per_data = array();
foreach ($eventi_calendario as $evento) {
    $data = date('Y-m-d', strtotime($evento['data_evento']));
    if (!isset($eventi_per_data[$data])) {
        $eventi_per_data[$data] = array();
    }
    $eventi_per_data[$data][] = $evento;
}

// Statistiche per il mese corrente del calendario
$stats_totali = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table} WHERE data_evento BETWEEN %s AND %s",
    $primo_giorno,
    $ultimo_giorno
));

$stats_confermati = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table} WHERE stato = 'confermato' AND data_evento BETWEEN %s AND %s",
    $primo_giorno,
    $ultimo_giorno
));

$stats_attivi = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table} WHERE stato = 'attivo' AND data_evento BETWEEN %s AND %s",
    $primo_giorno,
    $ultimo_giorno
));

$stats_annullati = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table} WHERE stato = 'annullato' AND data_evento BETWEEN %s AND %s",
    $primo_giorno,
    $ultimo_giorno
));

// Percentuali sul mese corrente
$perc_confermati = $stats_totali > 0 ? round(($stats_confermati / $stats_totali) * 100) : 0;
$perc_attivi = $stats_totali > 0 ? round(($stats_attivi / $stats_totali) * 100) : 0;
$perc_annullati = $stats_totali > 0 ? round(($stats_annullati / $stats_totali) * 100) : 0;

$mesi_nomi = array(
    1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
    5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
    9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
);
?>

<div class="wrap disco747-dashboard-enhanced" style="max-width: 1600px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    
    <!-- ============================================================================ -->
    <!-- HEADER PRINCIPALE -->
    <!-- ============================================================================ -->
    <div style="background: linear-gradient(135deg, #2b1e1a 0%, #c28a4d 50%, #2b1e1a 100%); color: white; padding: 30px 40px; border-radius: 20px; margin-bottom: 30px; box-shadow: 0 10px 40px rgba(43, 30, 26, 0.4); position: relative; overflow: hidden;">
        
        <!-- Decorazioni -->
        <div style="position: absolute; top: -80px; right: -80px; width: 250px; height: 250px; background: rgba(255,215,0,0.15); border-radius: 50%; opacity: 0.6;"></div>
        <div style="position: absolute; bottom: -60px; left: -60px; width: 200px; height: 200px; background: rgba(255,255,255,0.08); border-radius: 50%;"></div>
        
        <div style="position: relative; z-index: 2; text-align: center;">
            <h1 style="margin: 0 0 10px 0; font-size: 2.5rem; font-weight: 800; text-shadow: 3px 3px 6px rgba(0,0,0,0.4); letter-spacing: -1px;">
                🎉 747 Disco CRM
            </h1>
            <p style="margin: 0; font-size: 1.1rem; opacity: 0.95; font-weight: 400; text-shadow: 1px 1px 3px rgba(0,0,0,0.3);">
                Benvenuto, <?php echo esc_html($user_name); ?>! · Dashboard iOS v<?php echo esc_html($version); ?>
            </p>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- PULSANTE NUOVO PREVENTIVO GRANDE -->
    <!-- ============================================================================ -->
    <div style="margin-bottom: 30px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=new_preventivo')); ?>" 
           class="btn-nuovo-preventivo-big"
           style="display: block; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 25px 40px; border-radius: 25px; text-decoration: none; font-weight: 700; font-size: 1.5rem; box-shadow: 0 8px 30px rgba(40, 167, 69, 0.4); transition: all 0.3s ease; text-align: center; border: 3px solid rgba(255,255,255,0.3);">
            <span style="font-size: 2rem; margin-right: 12px;">➕</span> CREA NUOVO PREVENTIVO
        </a>
    </div>

    <!-- ============================================================================ -->
    <!-- GRAFICI ANDAMENTO -->
    <!-- ============================================================================ -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        
        <!-- Grafico Confermati -->
        <div style="background: linear-gradient(135deg, #34c759 0%, #30d158 100%); color: white; padding: 25px; border-radius: 16px; box-shadow: 0 6px 20px rgba(52, 199, 89, 0.3); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: relative; z-index: 2;">
                <div style="font-size: 2.5rem; margin-bottom: 5px;">✅</div>
                <div style="font-size: 2rem; font-weight: 700; margin-bottom: 5px;"><?php echo $stats_confermati; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.95; margin-bottom: 10px;">Confermati</div>
                <div style="background: rgba(255,255,255,0.25); height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="background: white; height: 100%; width: <?php echo $perc_confermati; ?>%; transition: width 1s ease;"></div>
                </div>
                <div style="font-size: 0.75rem; margin-top: 5px; opacity: 0.9;"><?php echo $perc_confermati; ?>% del totale</div>
            </div>
        </div>

        <!-- Grafico Attivi -->
        <div style="background: linear-gradient(135deg, #007aff 0%, #5ac8fa 100%); color: white; padding: 25px; border-radius: 16px; box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: relative; z-index: 2;">
                <div style="font-size: 2.5rem; margin-bottom: 5px;">⏳</div>
                <div style="font-size: 2rem; font-weight: 700; margin-bottom: 5px;"><?php echo $stats_attivi; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.95; margin-bottom: 10px;">Attivi</div>
                <div style="background: rgba(255,255,255,0.25); height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="background: white; height: 100%; width: <?php echo $perc_attivi; ?>%; transition: width 1s ease;"></div>
                </div>
                <div style="font-size: 0.75rem; margin-top: 5px; opacity: 0.9;"><?php echo $perc_attivi; ?>% del totale</div>
            </div>
        </div>

        <!-- Grafico Annullati -->
        <div style="background: linear-gradient(135deg, #ff3b30 0%, #ff453a 100%); color: white; padding: 25px; border-radius: 16px; box-shadow: 0 6px 20px rgba(255, 59, 48, 0.3); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: relative; z-index: 2;">
                <div style="font-size: 2.5rem; margin-bottom: 5px;">❌</div>
                <div style="font-size: 2rem; font-weight: 700; margin-bottom: 5px;"><?php echo $stats_annullati; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.95; margin-bottom: 10px;">Annullati</div>
                <div style="background: rgba(255,255,255,0.25); height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="background: white; height: 100%; width: <?php echo $perc_annullati; ?>%; transition: width 1s ease;"></div>
                </div>
                <div style="font-size: 0.75rem; margin-top: 5px; opacity: 0.9;"><?php echo $perc_annullati; ?>% del totale</div>
            </div>
        </div>

        <!-- Grafico Totali -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #d4a574 100%); color: white; padding: 25px; border-radius: 16px; box-shadow: 0 6px 20px rgba(194, 138, 77, 0.3); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: relative; z-index: 2;">
                <div style="font-size: 2.5rem; margin-bottom: 5px;">📊</div>
                <div style="font-size: 2rem; font-weight: 700; margin-bottom: 5px;"><?php echo $stats_totali; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.95; margin-bottom: 10px;">Totali Mese</div>
                <div style="background: rgba(255,255,255,0.25); height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="background: white; height: 100%; width: 100%; transition: width 1s ease;"></div>
                </div>
                <div style="font-size: 0.75rem; margin-top: 5px; opacity: 0.9;"><?php echo $mesi_nomi[$calendario_mese]; ?> <?php echo $calendario_anno; ?></div>
            </div>
        </div>

    </div>

    <!-- ============================================================================ -->
    <!-- FILTRI CALENDARIO (MESE E ANNO) -->
    <!-- ============================================================================ -->
    <div style="background: #f5f5f7; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; justify-content: center; gap: 15px; flex-wrap: wrap;">
            <label style="font-weight: 600; color: #1d1d1f; font-size: 0.95rem;">
                📅 Filtra Calendario:
            </label>
            
            <select id="filtro-mese" onchange="applicaFiltroCalendario()" style="background: white; border: 2px solid #e5e5e7; color: #1d1d1f; padding: 10px 15px; border-radius: 12px; cursor: pointer; font-size: 0.9rem; font-weight: 600; min-width: 150px; transition: all 0.2s;" onmouseover="this.style.borderColor='#007aff'" onmouseout="this.style.borderColor='#e5e5e7'">
                <?php foreach ($mesi_nomi as $num => $nome): ?>
                    <option value="<?php echo $num; ?>" <?php selected($calendario_mese, $num); ?>>
                        <?php echo $nome; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select id="filtro-anno" onchange="applicaFiltroCalendario()" style="background: white; border: 2px solid #e5e5e7; color: #1d1d1f; padding: 10px 15px; border-radius: 12px; cursor: pointer; font-size: 0.9rem; font-weight: 600; min-width: 120px; transition: all 0.2s;" onmouseover="this.style.borderColor='#007aff'" onmouseout="this.style.borderColor='#e5e5e7'">
                <?php 
                $anno_corrente = date('Y');
                // ✅ FIX: Estendi range per includere sempre 2026 e anni futuri
                for ($anno = $anno_corrente - 1; $anno <= $anno_corrente + 5; $anno++):
                ?>
                    <option value="<?php echo $anno; ?>" <?php selected($calendario_anno, $anno); ?>>
                        <?php echo $anno; ?>
                    </option>
                <?php endfor; ?>
            </select>
            
            <button onclick="vaiOggi()" style="background: #007aff; border: none; color: white; padding: 10px 20px; border-radius: 12px; cursor: pointer; font-size: 0.9rem; font-weight: 700; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0, 122, 255, 0.3);" onmouseover="this.style.background='#0051d5'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#007aff'; this.style.transform='translateY(0)'">
                🔵 Vai a Oggi
            </button>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- CALENDARIO EVENTI STILE IPHONE - LAYOUT SETTIMANALE -->
    <!-- ============================================================================ -->
    <div id="calendario-eventi" class="calendario-ios" style="background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 30px; max-width: 100%;">
        
        <!-- Header Calendario iOS Style -->
        <div style="background: linear-gradient(135deg, #1d1d1f 0%, #000000 100%); padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
            
            <!-- Navigazione Mese -->
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <button onclick="cambioMese(-1)" class="ios-nav-btn" style="background: none; border: none; color: #007aff; padding: 8px 12px; cursor: pointer; font-size: 1.3rem; transition: all 0.2s; border-radius: 8px;" onmouseover="this.style.background='rgba(0, 122, 255, 0.1)'" onmouseout="this.style.background='none'">
                    ‹
                </button>
                
                <div style="text-align: center; flex: 1;">
                    <h2 id="calendario-titolo" style="margin: 0; font-size: 1.4rem; font-weight: 700; color: white; letter-spacing: -0.5px;">
                        <?php echo $mesi_nomi[$calendario_mese] . ' ' . $calendario_anno; ?>
                    </h2>
                    <p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.6); font-size: 0.85rem; font-weight: 500;">
                        <?php echo count($eventi_calendario); ?> eventi
                    </p>
                </div>
                
                <button onclick="cambioMese(1)" class="ios-nav-btn" style="background: none; border: none; color: #007aff; padding: 8px 12px; cursor: pointer; font-size: 1.3rem; transition: all 0.2s; border-radius: 8px;" onmouseover="this.style.background='rgba(0, 122, 255, 0.1)'" onmouseout="this.style.background='none'">
                    ›
                </button>
            </div>
        </div>
        
        <!-- Calendario Settimanale iOS Style -->
        <div style="padding: 0;">
            
            <!-- Intestazioni Giorni Settimana -->
            <div class="ios-header-giorni" style="display: grid; grid-template-columns: repeat(7, 1fr); background: #f9f9f9; border-bottom: 1px solid #e5e5e7; padding: 8px 0;">
                <?php 
                $giorni_settimana = array('L', 'M', 'M', 'G', 'V', 'S', 'D');
                foreach ($giorni_settimana as $giorno): 
                ?>
                    <div style="text-align: center; font-weight: 600; color: #8e8e93; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php echo $giorno; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Settimane del Mese (Layout iOS) -->
            <?php
            $primo_giorno_settimana = date('N', strtotime($primo_giorno)); // 1=Lun, 7=Dom
            $giorni_nel_mese = date('t', strtotime($primo_giorno));
            $oggi = date('Y-m-d');
            
            $giorno_corrente = 1;
            $settimana_num = 0;
            
            // Genera settimane (massimo 6 righe per coprire tutti i casi)
            while ($giorno_corrente <= $giorni_nel_mese || $settimana_num == 0) {
                $settimana_num++;
                echo '<div class="settimana-ios" style="display: grid; grid-template-columns: repeat(7, 1fr); border-bottom: 1px solid #e5e5e7;">';
                
                for ($giorno_settimana = 1; $giorno_settimana <= 7; $giorno_settimana++) {
                    
                    // Prima settimana: aggiungi celle vuote prima del primo giorno
                    if ($settimana_num == 1 && $giorno_settimana < $primo_giorno_settimana) {
                        echo '<div class="giorno-vuoto" style="background: #fafafa; min-height: 60px;"></div>';
                        continue;
                    }
                    
                    // Se abbiamo finito i giorni del mese, celle vuote
                    if ($giorno_corrente > $giorni_nel_mese) {
                        echo '<div class="giorno-vuoto" style="background: #fafafa; min-height: 60px;"></div>';
                        continue;
                    }
                    
                    // Giorno valido
                    $data_corrente = "{$calendario_anno}-" . sprintf('%02d', $calendario_mese) . "-" . sprintf('%02d', $giorno_corrente);
                    $ha_eventi = isset($eventi_per_data[$data_corrente]);
                    $numero_eventi = $ha_eventi ? count($eventi_per_data[$data_corrente]) : 0;
                    $is_oggi = $data_corrente === $oggi;
                    
                    // Conta confermati vs attivi
                    $confermati = 0;
                    $attivi = 0;
                    if ($ha_eventi) {
                        foreach ($eventi_per_data[$data_corrente] as $evt) {
                            if ($evt['stato'] === 'confermato' || floatval($evt['acconto']) > 0) {
                                $confermati++;
                            } else {
                                $attivi++;
                            }
                        }
                    }
                    
                    // Stile iOS
                    $bg_color = 'white';
                    $text_color = '#1d1d1f';
                    $numero_style = '';
                    
                    if ($is_oggi) {
                        $numero_style = 'background: #007aff; color: white; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;';
                    } elseif ($ha_eventi) {
                        $text_color = '#c28a4d';
                    }
                    
                    $cursor = $ha_eventi ? 'pointer' : 'default';
                    $onclick = $ha_eventi ? "mostraEventi('$data_corrente')" : '';
                    
                    echo "<div class='giorno-ios' onclick=\"$onclick\" data-date=\"$data_corrente\" style=\"background: $bg_color; color: $text_color; padding: 10px 4px; text-align: center; cursor: $cursor; transition: all 0.15s; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; position: relative; min-height: 60px;\" onmouseover=\"if(this.onclick) {this.style.background='#f5f5f7';}\" onmouseout=\"this.style.background='$bg_color';\">";
                    
                    // Numero giorno
                    echo "<span style=\"$numero_style font-size: 0.95rem; font-weight: " . ($is_oggi ? '700' : '500') . ";\">$giorno_corrente</span>";
                    
                    // Pallini eventi (massimo 3)
                    if ($ha_eventi) {
                        echo '<div style="display: flex; gap: 3px; align-items: center; justify-content: center; min-height: 6px;">';
                        
                        // Pallini dorati per confermati
                        $totali_da_mostrare = min($confermati + $attivi, 3);
                        for ($i = 0; $i < min($confermati, $totali_da_mostrare); $i++) {
                            echo '<div style="width: 5px; height: 5px; border-radius: 50%; background: #c28a4d;"></div>';
                        }
                        
                        // Pallini grigi per attivi (solo se c'è spazio)
                        $rimanenti = $totali_da_mostrare - $confermati;
                        for ($i = 0; $i < min($attivi, $rimanenti); $i++) {
                            echo '<div style="width: 4px; height: 4px; border-radius: 50%; background: #8e8e93;"></div>';
                        }
                        
                        echo '</div>';
                    }
                    
                    echo '</div>';
                    
                    $giorno_corrente++;
                }
                
                echo '</div>'; // Fine settimana
                
                // Esci se abbiamo finito i giorni
                if ($giorno_corrente > $giorni_nel_mese) {
                    break;
                }
            }
            ?>
            
        </div>
        
        <!-- Area eventi del giorno selezionato -->
        <div id="eventi-giorno" style="display: none; border-top: 2px solid #e5e5ea; padding: 20px; background: #f9f9f9;">
            <h3 id="eventi-giorno-titolo" style="margin: 0 0 15px 0; font-size: 1.1rem; font-weight: 700; color: #1d1d1f;"></h3>
            <div id="eventi-giorno-lista"></div>
        </div>
        
    </div>

    <!-- ============================================================================ -->
    <!-- LINK RAPIDI -->
    <!-- ============================================================================ -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 40px;">
        
        <!-- Card Gestione Preventivi -->
        <a href="<?php echo admin_url('admin.php?page=disco747-view-preventivi'); ?>" 
           style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 16px; text-decoration: none; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3); transition: all 0.3s; display: block;"
           onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 12px 30px rgba(102, 126, 234, 0.4)';"
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 20px rgba(102, 126, 234, 0.3)';">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">📋</div>
            <h3 style="margin: 0 0 8px 0; font-size: 1.3rem; font-weight: 700;">Gestione Preventivi</h3>
            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">Visualizza, modifica, filtra</p>
        </a>

        <!-- Card Impostazioni -->
        <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>" 
           style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; border-radius: 16px; text-decoration: none; box-shadow: 0 8px 20px rgba(240, 147, 251, 0.3); transition: all 0.3s; display: block;"
           onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 12px 30px rgba(240, 147, 251, 0.4)';"
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 20px rgba(240, 147, 251, 0.3)';">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">⚙️</div>
            <h3 style="margin: 0 0 8px 0; font-size: 1.3rem; font-weight: 700;">Impostazioni</h3>
            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">Storage, API, Configurazione</p>
        </a>

        <!-- Card Messaggi -->
        <a href="<?php echo admin_url('admin.php?page=disco747-messages'); ?>" 
           style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 30px; border-radius: 16px; text-decoration: none; box-shadow: 0 8px 20px rgba(79, 172, 254, 0.3); transition: all 0.3s; display: block;"
           onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 12px 30px rgba(79, 172, 254, 0.4)';"
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 20px rgba(79, 172, 254, 0.3)';">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">💬</div>
            <h3 style="margin: 0 0 8px 0; font-size: 1.3rem; font-weight: 700;">Messaggi Automatici</h3>
            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">Email, WhatsApp, Template</p>
        </a>

    </div>

</div>

<!-- ============================================================================ -->
<!-- STILI CSS iOS -->
<!-- ============================================================================ -->
<style>
/* Pulsante Nuovo Preventivo Grande */
.btn-nuovo-preventivo-big:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(40, 167, 69, 0.5);
}

/* Animazioni iOS */
.giorno-ios {
    -webkit-tap-highlight-color: transparent;
}

.giorno-ios:active {
    transform: scale(0.95);
}

/* iOS Touch Optimization */
@media (hover: none) and (pointer: coarse) {
    .giorno-ios {
        min-height: 48px !important;
    }
}

/* Animazione barre grafici */
@keyframes fillBar {
    from { width: 0%; }
}

/* ============================================ */
/* RESPONSIVE - MOBILE FIRST */
/* ============================================ */

/* Mobile Portrait (320px - 480px) */
@media (max-width: 480px) {
    .disco747-dashboard-enhanced {
        margin: 10px auto !important;
        padding: 0 10px;
    }
    
    /* Header compatto */
    .disco747-dashboard-enhanced > div:first-child {
        padding: 20px 20px !important;
        border-radius: 15px !important;
        margin-bottom: 20px !important;
    }
    
    .disco747-dashboard-enhanced h1 {
        font-size: 1.8rem !important;
    }
    
    .disco747-dashboard-enhanced > div:first-child p {
        font-size: 0.9rem !important;
    }
    
    /* Pulsante grande responsive */
    .btn-nuovo-preventivo-big {
        padding: 18px 25px !important;
        font-size: 1.1rem !important;
    }
    
    /* Grafici a colonna singola */
    .disco747-dashboard-enhanced > div:nth-child(3) {
        grid-template-columns: 1fr !important;
    }
    
    /* Filtri compatti */
    .disco747-dashboard-enhanced > div:nth-child(4) > div {
        flex-direction: column !important;
        gap: 10px !important;
    }
    
    #filtro-mese,
    #filtro-anno {
        width: 100% !important;
        min-width: auto !important;
    }
    
    /* Calendario iOS compatto */
    #calendario-eventi {
        border-radius: 15px !important;
        margin-bottom: 20px !important;
    }
    
    #calendario-eventi > div:first-child {
        padding: 15px 10px !important;
    }
    
    #calendario-titolo {
        font-size: 1.1rem !important;
    }
    
    .ios-nav-btn {
        font-size: 1.5rem !important;
        padding: 4px 8px !important;
    }
    
    /* Intestazioni giorni mini */
    .ios-header-giorni > div {
        font-size: 0.65rem !important;
        padding: 6px 0 !important;
    }
    
    /* Giorni iOS touch-friendly */
    .giorno-ios,
    .giorno-vuoto {
        min-height: 48px !important;
        padding: 6px 2px !important;
    }
    
    .giorno-ios span {
        font-size: 0.85rem !important;
    }
    
    /* Pallini eventi mini */
    .giorno-ios > div:last-child > div {
        width: 4px !important;
        height: 4px !important;
    }
    
    /* Link rapidi a colonna singola */
    .disco747-dashboard-enhanced > div:last-child {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
    
    .disco747-dashboard-enhanced > div:last-child a {
        padding: 20px !important;
    }
    
    .disco747-dashboard-enhanced > div:last-child h3 {
        font-size: 1.1rem !important;
    }
}

/* Tablet (481px - 768px) */
@media (min-width: 481px) and (max-width: 768px) {
    .disco747-dashboard-enhanced {
        margin: 15px auto !important;
    }
    
    .btn-nuovo-preventivo-big {
        padding: 20px 30px !important;
        font-size: 1.3rem !important;
    }
    
    #calendario-titolo {
        font-size: 1.3rem !important;
    }
    
    .giorno-ios,
    .giorno-vuoto {
        min-height: 55px !important;
    }
    
    /* Grafici 2 colonne */
    .disco747-dashboard-enhanced > div:nth-child(3) {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    /* Link rapidi 2 colonne */
    .disco747-dashboard-enhanced > div:last-child {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

/* Desktop Large (1200px+) */
@media (min-width: 1200px) {
    .giorno-ios,
    .giorno-vuoto {
        min-height: 70px !important;
    }
    
    .giorno-ios span {
        font-size: 1.05rem !important;
    }
    
    .giorno-ios > div:last-child > div {
        width: 6px !important;
        height: 6px !important;
    }
}

/* Print Styles */
@media print {
    .btn-nuovo-preventivo-big,
    .ios-nav-btn,
    #filtro-mese,
    #filtro-anno,
    button {
        display: none !important;
    }
}
</style>

<!-- ============================================================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================================================ -->
<script>
// Dati eventi dal PHP
const eventiPerData = <?php echo json_encode($eventi_per_data); ?>;
const mesiNomi = <?php echo json_encode($mesi_nomi); ?>;

// Variabili globali
let meseCorrente = <?php echo $calendario_mese; ?>;
let annoCorrente = <?php echo $calendario_anno; ?>;

// Funzione: Applica filtro calendario
function applicaFiltroCalendario() {
    const mese = document.getElementById('filtro-mese').value;
    const anno = document.getElementById('filtro-anno').value;
    
    const url = new URL(window.location.href);
    url.searchParams.set('cal_month', mese);
    url.searchParams.set('cal_year', anno);
    window.location.href = url.toString();
}

// Funzione: Mostra eventi del giorno selezionato CON TUTTI I PULSANTI
function mostraEventi(data) {
    const eventi = eventiPerData[data];
    if (!eventi || eventi.length === 0) return;
    
    // Rimuovi selezione precedente
    document.querySelectorAll('.giorno-ios').forEach(el => {
        el.style.background = el.dataset.date === '<?php echo $oggi; ?>' ? 'white' : 'white';
    });
    
    // Seleziona giorno cliccato
    const giornoEl = document.querySelector(`.giorno-ios[data-date="${data}"]`);
    if (giornoEl) {
        giornoEl.style.background = '#f0f0f0';
    }
    
    // Formatta data
    const dataObj = new Date(data + 'T00:00:00');
    const giornoSettimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'][dataObj.getDay()];
    const giorno = dataObj.getDate();
    const mese = mesiNomi[dataObj.getMonth() + 1];
    
    const titoloFormattato = `${giornoSettimana} ${giorno} ${mese}`;
    
    // Aggiorna UI
    document.getElementById('eventi-giorno-titolo').textContent = titoloFormattato + ' • ' + eventi.length + ' evento' + (eventi.length > 1 ? 'i' : '');
    
    // Crea lista eventi COMPLETA
    let html = '';
    eventi.forEach(evento => {
        const statoClass = evento.stato === 'confermato' || parseFloat(evento.acconto) > 0 ? 'confermato' : (evento.stato === 'annullato' ? 'annullato' : 'attivo');
        
        let statoLabel = '';
        let statoColor = '';
        let statoIcon = '';
        
        if (statoClass === 'confermato') {
            statoLabel = 'Confermato';
            statoColor = '#34c759';
            statoIcon = '✓';
        } else if (statoClass === 'annullato') {
            statoLabel = 'Annullato';
            statoColor = '#ff3b30';
            statoIcon = '✕';
        } else {
            statoLabel = 'Attivo';
            statoColor = '#007aff';
            statoIcon = '○';
        }
        
        // Formatta telefono e email
        const telefono = evento.telefono || '';
        const email = evento.email || '';
        const nomeCliente = evento.nome_cliente || 'Cliente';
        const tipoEvento = evento.tipo_evento || 'Evento';
        const tipoMenu = evento.tipo_menu || 'Menu';
        const numeroInvitati = evento.numero_invitati || '-';
        
        // Link WhatsApp (formato internazionale)
        const telefonoWA = telefono.replace(/\s+/g, '').replace(/^0/, '39'); // Rimuovi spazi e converti in formato internazionale
        const whatsappLink = `https://wa.me/${telefonoWA}`;
        const whatsappMessage = `https://wa.me/${telefonoWA}?text=${encodeURIComponent('Ciao ' + nomeCliente + ', ti contattiamo per il tuo evento del ' + data)}`;
        
        html += `
            <div style="background: white; padding: 20px; margin-bottom: 15px; border-radius: 16px; border-left: 5px solid ${statoColor}; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                
                <!-- Header Card -->
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                    <div style="flex: 1; min-width: 200px;">
                        <div style="font-weight: 700; font-size: 1.2rem; color: #1d1d1f; margin-bottom: 5px;">
                            ${nomeCliente}
                        </div>
                        <div style="font-size: 0.9rem; color: #8e8e93; margin-bottom: 3px;">
                            🎉 ${tipoEvento}
                        </div>
                        <div style="font-size: 0.85rem; color: #8e8e93;">
                            🍽️ ${tipoMenu} • 👥 ${numeroInvitati} invitati
                        </div>
                    </div>
                    
                    <!-- Badge Stato -->
                    <span style="background: ${statoColor}; color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
                        ${statoIcon} ${statoLabel}
                    </span>
                </div>
                
                <!-- Contatti Rapidi -->
                <div style="display: flex; gap: 8px; margin-bottom: 15px; flex-wrap: wrap; padding: 12px; background: #f9f9f9; border-radius: 12px;">
                    
                    ${telefono ? `
                    <!-- WhatsApp -->
                    <a href="${whatsappMessage}" 
                       target="_blank"
                       style="flex: 1; min-width: 45px; background: #25d366; color: white; padding: 10px; border-radius: 12px; text-decoration: none; font-size: 0.85rem; font-weight: 600; text-align: center; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 5px;"
                       onmouseover="this.style.background='#20ba5a'; this.style.transform='translateY(-2px)'"
                       onmouseout="this.style.background='#25d366'; this.style.transform='translateY(0)'">
                        💬 WhatsApp
                    </a>
                    
                    <!-- Telefono -->
                    <a href="tel:${telefono}" 
                       style="flex: 1; min-width: 45px; background: #007aff; color: white; padding: 10px; border-radius: 12px; text-decoration: none; font-size: 0.85rem; font-weight: 600; text-align: center; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 5px;"
                       onmouseover="this.style.background='#0051d5'; this.style.transform='translateY(-2px)'"
                       onmouseout="this.style.background='#007aff'; this.style.transform='translateY(0)'">
                        📞 Chiama
                    </a>
                    ` : ''}
                    
                    ${email ? `
                    <!-- Email -->
                    <a href="mailto:${email}?subject=${encodeURIComponent('Preventivo ' + tipoEvento + ' - 747 Disco')}" 
                       style="flex: 1; min-width: 45px; background: #5856d6; color: white; padding: 10px; border-radius: 12px; text-decoration: none; font-size: 0.85rem; font-weight: 600; text-align: center; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 5px;"
                       onmouseover="this.style.background='#4240b8'; this.style.transform='translateY(-2px)'"
                       onmouseout="this.style.background='#5856d6'; this.style.transform='translateY(0)'">
                        ✉️ Email
                    </a>
                    ` : ''}
                    
                </div>
                
                <!-- Cambio Stato Rapido -->
                <div style="margin-bottom: 15px; padding: 12px; background: #f9f9f9; border-radius: 12px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; color: #1d1d1f;">
                        📊 Cambio Stato Rapido:
                    </label>
                    <select onchange="cambiaStatoRapido(${evento.id}, this.value)" style="width: 100%; padding: 10px; border: 2px solid #e5e5e7; border-radius: 8px; font-size: 0.9rem; font-weight: 600; background: white; cursor: pointer;">
                        <option value="attivo" ${evento.stato === 'attivo' ? 'selected' : ''}>✅ Attivo</option>
                        <option value="confermato" ${evento.stato === 'confermato' ? 'selected' : ''}>🎉 Confermato</option>
                        <option value="annullato" ${evento.stato === 'annullato' ? 'selected' : ''}>❌ Annullato</option>
                    </select>
                </div>
                
                <!-- Azioni Principali -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px;">
                    
                    <!-- Visualizza -->
                    <a href="<?php echo admin_url('admin.php?page=disco747-view-preventivi&id='); ?>${evento.id}" 
                       style="background: #007aff; color: white; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-size: 0.9rem; font-weight: 600; text-align: center; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px;"
                       onmouseover="this.style.background='#0051d5'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0, 122, 255, 0.4)'"
                       onmouseout="this.style.background='#007aff'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        👁️ Visualizza
                    </a>
                    
                    <!-- Modifica -->
                    <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=edit_preventivo&id='); ?>${evento.id}" 
                       style="background: #ff9500; color: white; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-size: 0.9rem; font-weight: 600; text-align: center; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px;"
                       onmouseover="this.style.background='#ff8c00'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(255, 149, 0, 0.4)'"
                       onmouseout="this.style.background='#ff9500'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        ✏️ Modifica
                    </a>
                    
                </div>
                
            </div>
        `;
    });
    
    document.getElementById('eventi-giorno-lista').innerHTML = html;
    document.getElementById('eventi-giorno').style.display = 'block';
    
    // Scroll all'area eventi
    setTimeout(() => {
        document.getElementById('eventi-giorno').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
}

// Funzione: Cambia mese (avanti/indietro)
function cambioMese(direzione) {
    meseCorrente += direzione;
    
    if (meseCorrente > 12) {
        meseCorrente = 1;
        annoCorrente++;
    } else if (meseCorrente < 1) {
        meseCorrente = 12;
        annoCorrente--;
    }
    
    aggiornaCalendario();
}

// Funzione: Vai a oggi
function vaiOggi() {
    const oggi = new Date();
    meseCorrente = oggi.getMonth() + 1;
    annoCorrente = oggi.getFullYear();
    aggiornaCalendario();
}

// Funzione: Cambia stato rapido da box riepilogo
function cambiaStatoRapido(preventivoId, nuovoStato) {
    if (!confirm(`Confermi il cambio stato a "${nuovoStato}"?`)) {
        // Reset select se annullato
        location.reload();
        return;
    }
    
    // Reindirizza al form di modifica con lo stato preimpostato
    const url = '<?php echo admin_url('admin.php?page=disco747-crm&action=edit_preventivo'); ?>&id=' + preventivoId + '&quick_stato=' + nuovoStato;
    window.location.href = url;
}

// Funzione: Aggiorna calendario con AJAX
function aggiornaCalendario() {
    const url = new URL(window.location.href);
    url.searchParams.set('cal_month', meseCorrente);
    url.searchParams.set('cal_year', annoCorrente);
    window.location.href = url.toString();
}

// Inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Dashboard 747 Disco iOS Enhanced caricata');
    
    // Sincronizza filtri con navigazione
    document.getElementById('filtro-mese').value = meseCorrente;
    document.getElementById('filtro-anno').value = annoCorrente;
    
    // Nascondi eventi quando si clicca fuori
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.giorno-ios') && !e.target.closest('#eventi-giorno')) {
            const eventiDiv = document.getElementById('eventi-giorno');
            if (eventiDiv && eventiDiv.style.display === 'block') {
                eventiDiv.style.display = 'none';
                // Reset background giorni
                document.querySelectorAll('.giorno-ios').forEach(el => {
                    el.style.background = 'white';
                });
            }
        }
    });
    
    // Animazione barre grafici
    setTimeout(() => {
        document.querySelectorAll('[style*="transition: width 1s"]').forEach(bar => {
            bar.style.animation = 'fillBar 1.5s ease';
        });
    }, 300);
});

// Touch handling per iOS
if ('ontouchstart' in window) {
    document.querySelectorAll('.giorno-ios').forEach(el => {
        el.addEventListener('touchend', function(e) {
            e.preventDefault();
            if (this.onclick) {
                this.onclick();
            }
        });
    });
}
</script>