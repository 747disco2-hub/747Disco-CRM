<?php
/**
 * Pagina Analisi Finanziaria - 747 Disco CRM
 * Dashboard completa per analisi economiche e trend
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @version    12.0.0
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

global $wpdb;
$table_name = $wpdb->prefix . 'disco747_preventivi';

// ============================================================================
// FILTRI TEMPORALI
// ============================================================================
$filter_type = sanitize_key($_GET['filter_type'] ?? 'month');
$filter_year = intval($_GET['filter_year'] ?? date('Y'));
$filter_month = intval($_GET['filter_month'] ?? date('m'));
$filter_quarter = intval($_GET['filter_quarter'] ?? ceil(date('m') / 3));
$date_from = sanitize_text_field($_GET['date_from'] ?? '');
$date_to = sanitize_text_field($_GET['date_to'] ?? '');

// Calcola date in base al filtro
switch ($filter_type) {
    case 'today':
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
        $period_label = 'Oggi - ' . date('d/m/Y');
        break;
        
    case 'week':
        $date_from = date('Y-m-d', strtotime('monday this week'));
        $date_to = date('Y-m-d', strtotime('sunday this week'));
        $period_label = 'Questa Settimana';
        break;
        
    case 'month':
        $date_from = date('Y-m-01', strtotime("{$filter_year}-{$filter_month}-01"));
        $date_to = date('Y-m-t', strtotime("{$filter_year}-{$filter_month}-01"));
        $period_label = date('F Y', strtotime($date_from));
        break;
        
    case 'quarter':
        $quarter_start_month = ($filter_quarter - 1) * 3 + 1;
        $date_from = date('Y-m-01', strtotime("{$filter_year}-{$quarter_start_month}-01"));
        $date_to = date('Y-m-t', strtotime("{$filter_year}-" . ($quarter_start_month + 2) . "-01"));
        $period_label = "Q{$filter_quarter} {$filter_year}";
        break;
        
    case 'year':
        $date_from = "{$filter_year}-01-01";
        $date_to = "{$filter_year}-12-31";
        $period_label = "Anno {$filter_year}";
        break;
        
    case 'custom':
        if (empty($date_from)) $date_from = date('Y-m-01');
        if (empty($date_to)) $date_to = date('Y-m-d');
        $period_label = date('d/m/Y', strtotime($date_from)) . ' - ' . date('d/m/Y', strtotime($date_to));
        break;
        
    default:
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-d');
        $period_label = 'Questo Mese';
}

// ============================================================================
// QUERY DATI FINANZIARI
// ============================================================================

// KPI Principali - DEFINIZIONI CORRETTE
$query_base = "
    SELECT 
        COUNT(*) as totale_preventivi,
        COUNT(CASE WHEN stato = 'confermato' OR acconto > 0 THEN 1 END) as preventivi_confermati,
        COUNT(CASE WHEN stato = 'attivo' AND (acconto = 0 OR acconto IS NULL) THEN 1 END) as preventivi_attivi,
        COUNT(CASE WHEN stato = 'annullato' THEN 1 END) as preventivi_annullati,
        -- ✅ FATTURATO TOTALE = SOLO CONFERMATI (preventivi chiusi con acconto)
        SUM(CASE WHEN stato = 'confermato' OR acconto > 0 THEN importo_totale ELSE 0 END) as fatturato_totale,
        -- FATTURATO CONFERMATO (stesso del totale per chiarezza)
        SUM(CASE WHEN stato = 'confermato' OR acconto > 0 THEN importo_totale ELSE 0 END) as fatturato_confermato,
        -- ✅ POTENZIALE = ATTIVI + CONFERMATI (tutto tranne annullati)
        SUM(CASE WHEN stato != 'annullato' THEN importo_totale ELSE 0 END) as fatturato_potenziale,
        -- ATTIVI PURI (senza acconto, potenziale da convertire)
        SUM(CASE WHEN stato = 'attivo' AND (acconto = 0 OR acconto IS NULL) THEN importo_totale ELSE 0 END) as fatturato_attivo,
        SUM(acconto) as acconti_incassati,
        SUM(CASE WHEN stato = 'confermato' OR acconto > 0 THEN (importo_totale - acconto) ELSE 0 END) as saldi_da_incassare,
        SUM(extra1_importo + extra2_importo + extra3_importo) as fatturato_extra,
        AVG(CASE WHEN stato = 'confermato' OR acconto > 0 THEN importo_totale END) as ticket_medio,
        AVG(CASE WHEN stato = 'confermato' OR acconto > 0 THEN numero_invitati END) as invitati_medio
    FROM {$table_name}
    WHERE data_evento BETWEEN %s AND %s
";

$kpi = $wpdb->get_row($wpdb->prepare($query_base, $date_from, $date_to));

// Calcola periodo precedente per comparazione
$days_diff = (strtotime($date_to) - strtotime($date_from)) / 86400;
$prev_date_to = date('Y-m-d', strtotime($date_from . ' -1 day'));
$prev_date_from = date('Y-m-d', strtotime($prev_date_to . " -{$days_diff} days"));

$kpi_prev = $wpdb->get_row($wpdb->prepare($query_base, $prev_date_from, $prev_date_to));

// Calcola variazioni percentuali
function calc_variation($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

// Breakdown per Menu
$breakdown_menu = $wpdb->get_results($wpdb->prepare("
    SELECT 
        tipo_menu,
        COUNT(*) as count,
        SUM(CASE WHEN stato != 'annullato' THEN importo_totale ELSE 0 END) as fatturato,
        AVG(CASE WHEN stato != 'annullato' THEN importo_totale END) as ticket_medio
    FROM {$table_name}
    WHERE data_evento BETWEEN %s AND %s
    GROUP BY tipo_menu
    ORDER BY fatturato DESC
", $date_from, $date_to));

// Breakdown per Tipo Evento
$breakdown_evento = $wpdb->get_results($wpdb->prepare("
    SELECT 
        tipo_evento,
        COUNT(*) as count,
        SUM(CASE WHEN stato != 'annullato' THEN importo_totale ELSE 0 END) as fatturato
    FROM {$table_name}
    WHERE data_evento BETWEEN %s AND %s
    GROUP BY tipo_evento
    ORDER BY fatturato DESC
    LIMIT 10
", $date_from, $date_to));

// Trend giornaliero (ultimi 30 giorni del periodo)
$trend_daily = $wpdb->get_results($wpdb->prepare("
    SELECT 
        DATE(data_evento) as data,
        COUNT(*) as preventivi,
        SUM(CASE WHEN stato != 'annullato' THEN importo_totale ELSE 0 END) as fatturato
    FROM {$table_name}
    WHERE data_evento BETWEEN %s AND %s
    GROUP BY DATE(data_evento)
    ORDER BY data ASC
", $date_from, $date_to));

// Trend mensile (ultimi 12 mesi)
$trend_monthly = $wpdb->get_results("
    SELECT 
        DATE_FORMAT(data_evento, '%Y-%m') as mese,
        COUNT(*) as preventivi,
        SUM(CASE WHEN stato = 'confermato' OR acconto > 0 THEN importo_totale ELSE 0 END) as fatturato,
        SUM(acconto) as acconti,
        COUNT(CASE WHEN stato = 'confermato' OR acconto > 0 THEN 1 END) as confermati,
        COUNT(*) as totali
    FROM {$table_name}
    WHERE data_evento >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(data_evento, '%Y-%m')
    ORDER BY mese ASC
");

// Distribuzione per stato (periodo corrente)
$distribuzione_stato = $wpdb->get_row($wpdb->prepare("
    SELECT 
        COUNT(CASE WHEN stato = 'confermato' OR acconto > 0 THEN 1 END) as confermati,
        COUNT(CASE WHEN stato = 'attivo' AND (acconto = 0 OR acconto IS NULL) THEN 1 END) as attivi,
        COUNT(CASE WHEN stato = 'annullato' THEN 1 END) as annullati,
        SUM(CASE WHEN stato = 'confermato' OR acconto > 0 THEN importo_totale ELSE 0 END) as fatturato_confermato,
        SUM(CASE WHEN stato = 'attivo' AND (acconto = 0 OR acconto IS NULL) THEN importo_totale ELSE 0 END) as fatturato_attivo,
        SUM(CASE WHEN stato = 'annullato' THEN importo_totale ELSE 0 END) as fatturato_annullato
    FROM {$table_name}
    WHERE data_evento BETWEEN %s AND %s
", $date_from, $date_to));

// Top 10 Eventi per fatturato
$top_eventi = $wpdb->get_results($wpdb->prepare("
    SELECT 
        id,
        nome_cliente,
        tipo_evento,
        data_evento,
        importo_totale,
        acconto,
        stato
    FROM {$table_name}
    WHERE data_evento BETWEEN %s AND %s
        AND stato != 'annullato'
    ORDER BY importo_totale DESC
    LIMIT 10
", $date_from, $date_to));

?>

<div class="wrap disco747-financial">
    
    <!-- HEADER -->
    <div style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); padding: 30px; border-radius: 15px; margin-bottom: 30px; color: white; box-shadow: 0 4px 20px rgba(30, 58, 138, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="margin: 0; font-size: 2.5rem; font-weight: 700; display: flex; align-items: center; gap: 15px;">
                    💰 Analisi Finanziaria
                </h1>
                <p style="margin: 10px 0 0 0; font-size: 1.1rem; opacity: 0.9;">
                    Dashboard completa per monitoraggio economico e trend
                </p>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 10px; border: 2px solid rgba(255,255,255,0.2);">
                <div style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 5px;">Periodo Selezionato</div>
                <div style="font-size: 1.3rem; font-weight: 700;"><?php echo esc_html($period_label); ?></div>
            </div>
        </div>
    </div>

    <!-- FILTRI TEMPORALI -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header" style="background: #f8f9fa;">
            📅 Filtri Temporali
        </div>
        <div class="disco747-card-content">
            <form method="get" action="" id="financial-filters">
                <input type="hidden" name="page" value="disco747-financial">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    
                    <!-- Tipo Filtro -->
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Tipo Periodo</label>
                        <select name="filter_type" id="filter-type" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;" onchange="toggleCustomDates()">
                            <option value="today" <?php selected($filter_type, 'today'); ?>>Oggi</option>
                            <option value="week" <?php selected($filter_type, 'week'); ?>>Questa Settimana</option>
                            <option value="month" <?php selected($filter_type, 'month'); ?>>Mese</option>
                            <option value="quarter" <?php selected($filter_type, 'quarter'); ?>>Trimestre</option>
                            <option value="year" <?php selected($filter_type, 'year'); ?>>Anno</option>
                            <option value="custom" <?php selected($filter_type, 'custom'); ?>>Personalizzato</option>
                        </select>
                    </div>

                    <!-- Anno (per mese/trimestre/anno) -->
                    <div id="year-filter" style="<?php echo in_array($filter_type, ['month', 'quarter', 'year']) ? '' : 'display:none;'; ?>">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Anno</label>
                        <select name="filter_year" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                            <?php for ($y = date('Y'); $y >= (date('Y') - 5); $y--): ?>
                                <option value="<?php echo $y; ?>" <?php selected($filter_year, $y); ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Mese (per filtro mese) -->
                    <div id="month-filter" style="<?php echo $filter_type === 'month' ? '' : 'display:none;'; ?>">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Mese</label>
                        <select name="filter_month" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                            <?php
                            $mesi = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
                            foreach ($mesi as $i => $mese):
                            ?>
                                <option value="<?php echo $i + 1; ?>" <?php selected($filter_month, $i + 1); ?>><?php echo $mese; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Trimestre (per filtro trimestre) -->
                    <div id="quarter-filter" style="<?php echo $filter_type === 'quarter' ? '' : 'display:none;'; ?>">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Trimestre</label>
                        <select name="filter_quarter" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                            <option value="1" <?php selected($filter_quarter, 1); ?>>Q1 (Gen-Mar)</option>
                            <option value="2" <?php selected($filter_quarter, 2); ?>>Q2 (Apr-Giu)</option>
                            <option value="3" <?php selected($filter_quarter, 3); ?>>Q3 (Lug-Set)</option>
                            <option value="4" <?php selected($filter_quarter, 4); ?>>Q4 (Ott-Dic)</option>
                        </select>
                    </div>

                    <!-- Date Personalizzate -->
                    <div id="custom-dates" style="<?php echo $filter_type === 'custom' ? 'display: contents;' : 'display:none;'; ?>">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Data Inizio</label>
                            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Data Fine</label>
                            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                        </div>
                    </div>

                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="button button-primary" style="padding: 10px 30px;">
                        🔍 Applica Filtri
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=disco747-financial'); ?>" class="button">
                        Ripristina
                    </a>
                    <button type="button" id="export-financial-csv" class="button" style="margin-left: auto;">
                        📥 Export CSV
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI CARDS PRINCIPALI -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        
        <!-- Fatturato Totale (CONFERMATI) -->
        <?php
        $var_fatturato = calc_variation($kpi->fatturato_totale, $kpi_prev->fatturato_totale);
        $var_class = $var_fatturato >= 0 ? 'positive' : 'negative';
        ?>
        <div class="kpi-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <div class="kpi-icon">💰</div>
            <div class="kpi-label">Fatturato Totale</div>
            <div class="kpi-value">€<?php echo number_format($kpi->fatturato_totale, 2, ',', '.'); ?></div>
            <div class="kpi-subtitle">Da preventivi confermati</div>
            <div class="kpi-trend <?php echo $var_class; ?>">
                <?php echo $var_fatturato >= 0 ? '↑' : '↓'; ?> <?php echo abs($var_fatturato); ?>% vs periodo precedente
            </div>
        </div>

        <!-- Fatturato Potenziale (ATTIVI + CONFERMATI) -->
        <?php $var_potenziale = calc_variation($kpi->fatturato_potenziale, $kpi_prev->fatturato_potenziale); ?>
        <div class="kpi-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
            <div class="kpi-icon">🎯</div>
            <div class="kpi-label">Potenziale Totale</div>
            <div class="kpi-value">€<?php echo number_format($kpi->fatturato_potenziale, 2, ',', '.'); ?></div>
            <div class="kpi-subtitle">Attivi + Confermati (no annullati)</div>
            <div class="kpi-trend <?php echo $var_potenziale >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $var_potenziale >= 0 ? '↑' : '↓'; ?> <?php echo abs($var_potenziale); ?>%
            </div>
        </div>

        <!-- Fatturato da Convertire (SOLO ATTIVI) -->
        <div class="kpi-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <div class="kpi-icon">⏳</div>
            <div class="kpi-label">Da Convertire</div>
            <div class="kpi-value">€<?php echo number_format($kpi->fatturato_attivo, 2, ',', '.'); ?></div>
            <div class="kpi-subtitle"><?php echo $kpi->preventivi_attivi; ?> preventivi attivi</div>
        </div>

        <!-- Acconti Incassati -->
        <div class="kpi-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
            <div class="kpi-icon">💳</div>
            <div class="kpi-label">Acconti Incassati</div>
            <div class="kpi-value">€<?php echo number_format($kpi->acconti_incassati, 2, ',', '.'); ?></div>
            <div class="kpi-subtitle">
                <?php 
                $perc_acconti = $kpi->fatturato_totale > 0 ? ($kpi->acconti_incassati / $kpi->fatturato_totale * 100) : 0;
                echo number_format($perc_acconti, 1); 
                ?>% del totale
            </div>
        </div>

        <!-- Saldi da Incassare -->
        <div class="kpi-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
            <div class="kpi-icon">💵</div>
            <div class="kpi-label">Saldi da Incassare</div>
            <div class="kpi-value">€<?php echo number_format($kpi->saldi_da_incassare, 2, ',', '.'); ?></div>
            <div class="kpi-subtitle">Da eventi confermati</div>
        </div>

        <!-- Ticket Medio -->
        <div class="kpi-card" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
            <div class="kpi-icon">🎟️</div>
            <div class="kpi-label">Ticket Medio</div>
            <div class="kpi-value">€<?php echo number_format($kpi->ticket_medio, 2, ',', '.'); ?></div>
            <div class="kpi-subtitle"><?php echo number_format($kpi->invitati_medio, 0); ?> invitati medi</div>
        </div>

        <!-- Preventivi Totali -->
        <?php $var_preventivi = calc_variation($kpi->totale_preventivi, $kpi_prev->totale_preventivi); ?>
        <div class="kpi-card" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
            <div class="kpi-icon">📊</div>
            <div class="kpi-label">Preventivi Totali</div>
            <div class="kpi-value"><?php echo number_format($kpi->totale_preventivi); ?></div>
            <div class="kpi-trend <?php echo $var_preventivi >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $var_preventivi >= 0 ? '↑' : '↓'; ?> <?php echo abs($var_preventivi); ?>%
            </div>
        </div>

        <!-- Tasso Conversione -->
        <?php 
        $tasso_conv = $kpi->totale_preventivi > 0 ? ($kpi->preventivi_confermati / $kpi->totale_preventivi * 100) : 0;
        ?>
        <div class="kpi-card" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);">
            <div class="kpi-icon">🎯</div>
            <div class="kpi-label">Tasso Conversione</div>
            <div class="kpi-value"><?php echo number_format($tasso_conv, 1); ?>%</div>
            <div class="kpi-subtitle"><?php echo $kpi->preventivi_confermati; ?> / <?php echo $kpi->totale_preventivi; ?> convertiti</div>
        </div>

    </div>

    <!-- GRAFICI PRINCIPALI -->
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
        
        <!-- Grafico Distribuzione Stati -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                📊 Distribuzione Preventivi per Stato
            </div>
            <div class="disco747-card-content">
                <canvas id="chart-stato-distribution" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Grafico Fatturato per Stato -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                💰 Fatturato per Stato
            </div>
            <div class="disco747-card-content">
                <canvas id="chart-fatturato-stato" style="max-height: 300px;"></canvas>
            </div>
        </div>

    </div>

    <!-- GRAFICI TREND -->
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
        
        <!-- Grafico Trend Mensile -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                📈 Trend Fatturato Ultimi 12 Mesi
            </div>
            <div class="disco747-card-content">
                <canvas id="chart-monthly-revenue" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Grafico Tasso Conversione -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                🎯 Evoluzione Tasso Conversione
            </div>
            <div class="disco747-card-content">
                <canvas id="chart-conversion-trend" style="max-height: 300px;"></canvas>
            </div>
        </div>

    </div>

    <!-- GRAFICI DETTAGLIO -->
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">

        <!-- Grafico Breakdown Menu -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                🍽️ Fatturato per Tipo Menu
            </div>
            <div class="disco747-card-content">
                <canvas id="chart-menu-breakdown" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Grafico Acconti vs Saldi -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                💳 Acconti vs Saldi da Incassare
            </div>
            <div class="disco747-card-content">
                <canvas id="chart-acconti-saldi" style="max-height: 300px;"></canvas>
            </div>
        </div>

    </div>

    <!-- TABELLE DETTAGLIATE -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        
        <!-- Breakdown Menu -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                📋 Dettaglio per Menu
            </div>
            <div class="disco747-card-content" style="padding: 0;">
                <table class="wp-list-table widefat" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th style="text-align: center;">N° Eventi</th>
                            <th style="text-align: right;">Fatturato</th>
                            <th style="text-align: right;">Ticket Medio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($breakdown_menu as $item): ?>
                        <tr>
                            <td><strong><?php echo esc_html($item->tipo_menu); ?></strong></td>
                            <td style="text-align: center;"><?php echo $item->count; ?></td>
                            <td style="text-align: right;">€<?php echo number_format($item->fatturato, 2, ',', '.'); ?></td>
                            <td style="text-align: right;">€<?php echo number_format($item->ticket_medio, 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Breakdown Tipo Evento -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                🎉 Top 10 Tipi di Evento
            </div>
            <div class="disco747-card-content" style="padding: 0;">
                <table class="wp-list-table widefat" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Tipo Evento</th>
                            <th style="text-align: center;">N° Eventi</th>
                            <th style="text-align: right;">Fatturato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($breakdown_evento as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->tipo_evento); ?></td>
                            <td style="text-align: center;"><?php echo $item->count; ?></td>
                            <td style="text-align: right;">€<?php echo number_format($item->fatturato, 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- TOP 10 EVENTI -->
    <div class="disco747-card">
        <div class="disco747-card-header">
            🏆 Top 10 Eventi per Fatturato
        </div>
        <div class="disco747-card-content" style="padding: 0;">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Pos.</th>
                        <th>Cliente</th>
                        <th>Tipo Evento</th>
                        <th>Data Evento</th>
                        <th style="text-align: right;">Importo</th>
                        <th style="text-align: right;">Acconto</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_eventi as $i => $evento): ?>
                    <tr>
                        <td><strong>#<?php echo $i + 1; ?></strong></td>
                        <td><?php echo esc_html($evento->nome_cliente); ?></td>
                        <td><?php echo esc_html($evento->tipo_evento); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($evento->data_evento)); ?></td>
                        <td style="text-align: right;"><strong>€<?php echo number_format($evento->importo_totale, 2, ',', '.'); ?></strong></td>
                        <td style="text-align: right;">€<?php echo number_format($evento->acconto, 2, ',', '.'); ?></td>
                        <td>
                            <?php
                            $badge_colors = ['confermato' => '#10b981', 'attivo' => '#3b82f6', 'annullato' => '#ef4444'];
                            $color = $badge_colors[strtolower($evento->stato)] ?? '#6b7280';
                            ?>
                            <span style="background: <?php echo $color; ?>; color: white; padding: 4px 10px; border-radius: 5px; font-size: 11px; font-weight: 600;">
                                <?php echo strtoupper($evento->stato); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- CSS STYLES -->
<style>
.disco747-financial {
    padding: 20px;
    max-width: 1600px;
    margin: 0 auto;
}

.disco747-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 20px;
}

.disco747-card-header {
    padding: 20px;
    border-bottom: 2px solid #f0f0f0;
    font-size: 18px;
    font-weight: 700;
    background: white;
}

.disco747-card-content {
    padding: 25px;
}

.kpi-card {
    padding: 25px;
    border-radius: 12px;
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    position: relative;
    overflow: hidden;
}

.kpi-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
    opacity: 0.9;
}

.kpi-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 10px;
    font-weight: 500;
}

.kpi-value {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 8px;
    line-height: 1;
}

.kpi-trend {
    font-size: 0.85rem;
    opacity: 0.95;
    font-weight: 600;
}

.kpi-subtitle {
    font-size: 0.85rem;
    opacity: 0.9;
    margin-top: 5px;
}

.kpi-trend.positive {
    color: #d1fae5;
}

.kpi-trend.negative {
    color: #fecaca;
}

/* Responsive */
@media (max-width: 1200px) {
    .disco747-financial > div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}

@media (max-width: 768px) {
    .disco747-financial {
        padding: 10px;
    }
    
    .kpi-value {
        font-size: 1.5rem;
    }
    
    .kpi-icon {
        font-size: 2rem;
    }
}
</style>

<!-- JAVASCRIPT -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
jQuery(document).ready(function($) {
    
    // Toggle filtri personalizzati
    window.toggleCustomDates = function() {
        const filterType = $('#filter-type').val();
        
        $('#year-filter').hide();
        $('#month-filter').hide();
        $('#quarter-filter').hide();
        $('#custom-dates').hide();
        
        if (['month', 'quarter', 'year'].includes(filterType)) {
            $('#year-filter').show();
        }
        
        if (filterType === 'month') {
            $('#month-filter').show();
        }
        
        if (filterType === 'quarter') {
            $('#quarter-filter').show();
        }
        
        if (filterType === 'custom') {
            $('#custom-dates').css('display', 'contents');
        }
    };
    
    // Grafico Trend Mensile
    const monthlyData = <?php echo json_encode($trend_monthly); ?>;
    const monthlyLabels = monthlyData.map(item => {
        const [year, month] = item.mese.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('it-IT', { month: 'short', year: '2-digit' });
    });
    
    new Chart(document.getElementById('chart-monthly-revenue'), {
        type: 'line',
        data: {
            labels: monthlyLabels,
            datasets: [{
                label: 'Fatturato',
                data: monthlyData.map(item => item.fatturato),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Acconti',
                data: monthlyData.map(item => item.acconti),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '€' + value.toLocaleString('it-IT');
                        }
                    }
                }
            }
        }
    });
    
    // =========================================================================
    // GRAFICO 1: Distribuzione Preventivi per Stato
    // =========================================================================
    const distribStato = <?php echo json_encode($distribuzione_stato); ?>;
    
    new Chart(document.getElementById('chart-stato-distribution'), {
        type: 'pie',
        data: {
            labels: ['✅ Confermati', '⏳ Attivi', '❌ Annullati'],
            datasets: [{
                data: [distribStato.confermati, distribStato.attivi, distribStato.annullati],
                backgroundColor: ['#10b981', '#3b82f6', '#ef4444'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 14, weight: 'bold' },
                        padding: 15
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = distribStato.confermati + distribStato.attivi + distribStato.annullati;
                            const perc = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': ' + context.parsed + ' (' + perc + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // =========================================================================
    // GRAFICO 2: Fatturato per Stato
    // =========================================================================
    new Chart(document.getElementById('chart-fatturato-stato'), {
        type: 'bar',
        data: {
            labels: ['Confermato', 'Attivo', 'Annullato'],
            datasets: [{
                label: 'Fatturato (€)',
                data: [
                    distribStato.fatturato_confermato,
                    distribStato.fatturato_attivo,
                    distribStato.fatturato_annullato
                ],
                backgroundColor: ['#10b981', '#3b82f6', '#ef4444'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '€' + value.toLocaleString('it-IT');
                        }
                    }
                }
            }
        }
    });
    
    // =========================================================================
    // GRAFICO 3: Evoluzione Tasso Conversione (ultimi 12 mesi)
    // =========================================================================
    new Chart(document.getElementById('chart-conversion-trend'), {
        type: 'line',
        data: {
            labels: monthlyLabels,
            datasets: [{
                label: 'Tasso Conversione (%)',
                data: monthlyData.map(item => {
                    return item.totali > 0 ? ((item.confermati / item.totali) * 100).toFixed(1) : 0;
                }),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Conversione: ' + context.parsed.y + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
    
    // =========================================================================
    // GRAFICO 4: Breakdown Menu
    // =========================================================================
    const menuData = <?php echo json_encode($breakdown_menu); ?>;
    
    new Chart(document.getElementById('chart-menu-breakdown'), {
        type: 'doughnut',
        data: {
            labels: menuData.map(item => item.tipo_menu),
            datasets: [{
                data: menuData.map(item => item.fatturato),
                backgroundColor: [
                    '#3b82f6',
                    '#10b981',
                    '#f59e0b',
                    '#ef4444',
                    '#8b5cf6',
                    '#ec4899',
                    '#06b6d4',
                    '#14b8a6'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: { size: 12 },
                        padding: 10
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': €' + context.parsed.toLocaleString('it-IT');
                        }
                    }
                }
            }
        }
    });
    
    // =========================================================================
    // GRAFICO 5: Acconti vs Saldi da Incassare
    // =========================================================================
    const accontiIncassati = <?php echo floatval($kpi->acconti_incassati); ?>;
    const saldiDaIncassare = <?php echo floatval($kpi->saldi_da_incassare); ?>;
    
    new Chart(document.getElementById('chart-acconti-saldi'), {
        type: 'doughnut',
        data: {
            labels: ['💳 Acconti Incassati', '💵 Saldi da Incassare'],
            datasets: [{
                data: [accontiIncassati, saldiDaIncassare],
                backgroundColor: ['#8b5cf6', '#ef4444'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 14, weight: 'bold' },
                        padding: 15
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = accontiIncassati + saldiDaIncassare;
                            const perc = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': €' + context.parsed.toLocaleString('it-IT') + ' (' + perc + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // Export CSV
    $('#export-financial-csv').on('click', function() {
        const params = new URLSearchParams(window.location.search);
        params.set('action', 'disco747_export_financial_csv');
        params.set('nonce', '<?php echo wp_create_nonce('disco747_export_financial'); ?>');
        
        window.location.href = ajaxurl + '?' + params.toString();
    });
    
});
</script>
