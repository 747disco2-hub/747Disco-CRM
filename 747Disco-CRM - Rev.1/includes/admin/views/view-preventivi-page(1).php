<?php
/**
 * Pagina View Preventivi - 747 Disco CRM
 * Versione 11.8.3 - Con Filtri e Modal Modifica
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @version    11.8.3-FILTERS-EDIT
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

global $wpdb;
$table_name = $wpdb->prefix . 'disco747_preventivi';

// Parametri filtri
$filters = array(
    'search' => sanitize_text_field($_GET['search'] ?? ''),
    'stato' => sanitize_key($_GET['stato'] ?? ''),
    'menu' => sanitize_text_field($_GET['menu'] ?? ''),
    'anno' => intval($_GET['anno'] ?? 0),
    'mese' => intval($_GET['mese'] ?? 0),
    'order_by' => sanitize_key($_GET['order_by'] ?? 'created_at'),
    'order' => strtoupper(sanitize_text_field($_GET['order'] ?? 'DESC'))
);

// Valida che order sia solo ASC o DESC
if (!in_array($filters['order'], array('ASC', 'DESC'))) {
    $filters['order'] = 'DESC';
}

// Paginazione
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Query base
$where = array('1=1');
$where_values = array();

// Applica filtri
if (!empty($filters['search'])) {
    $where[] = "(nome_cliente LIKE %s OR email LIKE %s OR telefono LIKE %s OR tipo_evento LIKE %s)";
    $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
    $where_values[] = $search_term;
    $where_values[] = $search_term;
    $where_values[] = $search_term;
    $where_values[] = $search_term;
}

if (!empty($filters['stato'])) {
    $where[] = "stato = %s";
    $where_values[] = $filters['stato'];
}

if (!empty($filters['menu'])) {
    $where[] = "tipo_menu LIKE %s";
    $where_values[] = '%' . $wpdb->esc_like($filters['menu']) . '%';
}

if ($filters['anno'] > 0) {
    $where[] = "YEAR(data_evento) = %d";
    $where_values[] = $filters['anno'];
}

if ($filters['mese'] > 0) {
    $where[] = "MONTH(data_evento) = %d";
    $where_values[] = $filters['mese'];
}

$where_clause = implode(' AND ', $where);

// Conta totali
if (!empty($where_values)) {
    $count_query = $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}", $where_values);
} else {
    $count_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
}
$total_preventivi = $wpdb->get_var($count_query);
$total_pages = ceil($total_preventivi / $per_page);

// Query preventivi
$order_clause = sprintf('ORDER BY %s %s', 
    sanitize_key($filters['order_by']), 
    $filters['order'] === 'ASC' ? 'ASC' : 'DESC'
);

if (!empty($where_values)) {
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE {$where_clause} {$order_clause} LIMIT %d OFFSET %d",
        array_merge($where_values, array($per_page, $offset))
    );
} else {
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE {$where_clause} {$order_clause} LIMIT %d OFFSET %d",
        $per_page,
        $offset
    );
}

$preventivi = $wpdb->get_results($query);

// Statistiche
$stats = array(
    'totale' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
    'attivi' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE stato = 'attivo'"),
    'confermati' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE stato = 'confermato'"),
    'annullati' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE stato = 'annullato'")
);

?>

<div class="wrap disco747-wrap">
    
    <!-- Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
        <h1 style="margin: 0;">ðŸ“Š Database Preventivi</h1>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo admin_url('admin.php?page=disco747-scan-excel'); ?>" class="button">
                ðŸ”„ Excel Scan
            </a>
            <button type="button" id="export-csv-btn" class="button button-primary">
                ðŸ“¥ Export CSV
            </button>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-content">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="stat-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="stat-label">ðŸ“Š Totale</div>
                    <div class="stat-value"><?php echo number_format($stats['totale']); ?></div>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <div class="stat-label">ðŸ”µ Attivi</div>
                    <div class="stat-value"><?php echo number_format($stats['attivi']); ?></div>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="stat-label">âœ… Confermati</div>
                    <div class="stat-value"><?php echo number_format($stats['confermati']); ?></div>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <div class="stat-label">âŒ Annullati</div>
                    <div class="stat-value"><?php echo number_format($stats['annullati']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtri -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header">
            ðŸ” Filtri di Ricerca
            <?php if (array_filter($filters)): ?>
                <a href="<?php echo admin_url('admin.php?page=disco747-view-preventivi'); ?>" 
                   style="float: right; font-size: 13px; color: #2271b1; text-decoration: none;">
                    âœ– Cancella Filtri
                </a>
            <?php endif; ?>
        </div>
        <div class="disco747-card-content">
            <form method="get" action="" id="filters-form">
                <input type="hidden" name="page" value="disco747-view-preventivi">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <!-- Ricerca -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">ðŸ” Cerca</label>
                        <input type="text" name="search" 
                               value="<?php echo esc_attr($filters['search']); ?>" 
                               placeholder="Nome, email, telefono..."
                               style="width: 100%; padding: 8px;">
                    </div>

                    <!-- Stato -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">ðŸ“Œ Stato</label>
                        <select name="stato" style="width: 100%; padding: 8px;">
                            <option value="">Tutti gli stati</option>
                            <option value="attivo" <?php selected($filters['stato'], 'attivo'); ?>>Attivo</option>
                            <option value="confermato" <?php selected($filters['stato'], 'confermato'); ?>>Confermato</option>
                            <option value="annullato" <?php selected($filters['stato'], 'annullato'); ?>>Annullato</option>
                            <option value="bozza" <?php selected($filters['stato'], 'bozza'); ?>>Bozza</option>
                        </select>
                    </div>

                    <!-- Menu -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">ðŸ½ï¸ Menu</label>
                        <select name="menu" style="width: 100%; padding: 8px;">
                            <option value="">Tutti i menu</option>
                            <option value="Menu 7" <?php selected($filters['menu'], 'Menu 7'); ?>>Menu 7</option>
                            <option value="Menu 7-4" <?php selected($filters['menu'], 'Menu 7-4'); ?>>Menu 7-4</option>
                            <option value="Menu 7-4-7" <?php selected($filters['menu'], 'Menu 7-4-7'); ?>>Menu 7-4-7</option>
                        </select>
                    </div>

                    <!-- Anno -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">ðŸ“… Anno</label>
                        <select name="anno" style="width: 100%; padding: 8px;">
                            <option value="">Tutti gli anni</option>
                            <?php
                            $current_year = date('Y');
                            // Mostra: anno corrente + 1 anno futuro + 3 anni passati
                            for ($y = ($current_year + 1); $y >= ($current_year - 3); $y--):
                            ?>
                                <option value="<?php echo $y; ?>" <?php selected($filters['anno'], $y); ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Mese -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">ðŸ“† Mese</label>
                        <select name="mese" style="width: 100%; padding: 8px;">
                            <option value="">Tutti i mesi</option>
                            <?php
                            $mesi = array(
                                1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
                                5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
                                9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
                            );
                            foreach ($mesi as $num => $nome):
                            ?>
                                <option value="<?php echo $num; ?>" <?php selected($filters['mese'], $num); ?>>
                                    <?php echo $nome; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ordina per -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">ðŸ”¢ Ordina per</label>
                        <select name="order_by" style="width: 100%; padding: 8px;">
                            <option value="created_at" <?php selected($filters['order_by'], 'created_at'); ?>>Data Creazione</option>
                            <option value="data_evento" <?php selected($filters['order_by'], 'data_evento'); ?>>Data Evento</option>
                            <option value="nome_cliente" <?php selected($filters['order_by'], 'nome_cliente'); ?>>Cliente</option>
                            <option value="importo_totale" <?php selected($filters['order_by'], 'importo_totale'); ?>>Importo</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <button type="submit" class="button button-primary">ðŸ” Applica Filtri</button>
                    <a href="<?php echo admin_url('admin.php?page=disco747-view-preventivi'); ?>" 
                       class="button">Ripristina</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella Preventivi -->
    <div class="disco747-card">
        <div class="disco747-card-header">
            ðŸ“‹ Preventivi (<?php echo number_format($total_preventivi); ?> risultati)
        </div>
        <div class="disco747-card-content" style="padding: 0;">
            
            <?php if (empty($preventivi)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 15px;">ðŸ“­</div>
                    <h3 style="margin: 0 0 10px 0;">Nessun preventivo trovato</h3>
                    <p style="margin: 0;">Prova a modificare i filtri o esegui un Batch Scan</p>
                </div>
            <?php else: ?>
                
                <!-- TABELLA DESKTOP (nascosta su mobile) -->
                <div class="preventivi-table-desktop" style="overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead>
                            <tr>
                                <?php
                                // Funzione helper per generare link ordinamento
                                function get_sort_link($column, $label, $current_order_by, $current_order, $width = '', $align = '') {
                                    $is_active = ($current_order_by === $column);
                                    
                                    // Determina il nuovo ordine
                                    if ($is_active) {
                                        // Se Ã¨ giÃ  attiva, inverti l'ordine
                                        $new_order = ($current_order === 'ASC') ? 'DESC' : 'ASC';
                                    } else {
                                        // Se non Ã¨ attiva, inizia con DESC
                                        $new_order = 'DESC';
                                    }
                                    
                                    // Mantieni tutti i parametri esistenti
                                    $current_params = $_GET;
                                    $current_params['order_by'] = $column;
                                    $current_params['order'] = $new_order;
                                    $current_params['paged'] = 1; // Reset pagination
                                    
                                    $url = add_query_arg($current_params);
                                    
                                    $arrow = '';
                                    if ($is_active) {
                                        $arrow = ($current_order === 'ASC') ? ' â†‘' : ' â†“';
                                    }
                                    
                                    $style = '';
                                    if ($width) $style .= "width: {$width}; ";
                                    if ($align) $style .= "text-align: {$align}; ";
                                    if ($is_active) $style .= "background: #f0f0f1; font-weight: 700;";
                                    
                                    return sprintf(
                                        '<th style="%s"><a href="%s" style="text-decoration: none; color: inherit; display: block; padding: 8px 12px;">%s%s</a></th>',
                                        $style,
                                        esc_url($url),
                                        esc_html($label),
                                        $arrow
                                    );
                                }
                                
                                echo get_sort_link('data_evento', 'Data Evento', $filters['order_by'], $filters['order'], '100px');
                                echo get_sort_link('nome_cliente', 'Cliente', $filters['order_by'], $filters['order']);
                                ?>
                                <th style="width: 60px; text-align: center;">WhatsApp</th>
                                <?php
                                echo get_sort_link('tipo_evento', 'Tipo Evento', $filters['order_by'], $filters['order']);
                                echo get_sort_link('tipo_menu', 'Menu', $filters['order_by'], $filters['order'], '100px');
                                echo get_sort_link('numero_invitati', 'Invitati', $filters['order_by'], $filters['order'], '70px');
                                echo get_sort_link('importo_totale', 'Importo', $filters['order_by'], $filters['order'], '100px');
                                echo get_sort_link('acconto', 'Acconto', $filters['order_by'], $filters['order'], '90px');
                                echo get_sort_link('stato', 'Stato', $filters['order_by'], $filters['order'], '80px');
                                ?>
                                <th style="width: 180px;">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preventivi as $prev): ?>
                                <tr data-preventivo-id="<?php echo $prev->id; ?>">
                                    <td><?php echo $prev->data_evento ? date('d/m/Y', strtotime($prev->data_evento)) : 'N/A'; ?></td>
                                    <td>
                                        <strong><?php echo esc_html($prev->nome_cliente ?: 'N/A'); ?></strong>
                                        <?php if ($prev->telefono): ?>
                                            <br><small style="color: #666;">ðŸ“ž <?php echo esc_html($prev->telefono); ?></small>
                                        <?php endif; ?>
                                        <?php if ($prev->email): ?>
                                            <br><small style="color: #666;">âœ‰ï¸ <?php echo esc_html($prev->email); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($prev->telefono): 
                                            // Formatta numero per WhatsApp (rimuovi spazi, trattini, parentesi)
                                            $whatsapp_number = preg_replace('/[^0-9+]/', '', $prev->telefono);
                                            // Se non inizia con +, aggiungi prefisso Italia
                                            if (substr($whatsapp_number, 0, 1) !== '+') {
                                                $whatsapp_number = '+39' . $whatsapp_number;
                                            }
                                        ?>
                                            <a href="https://wa.me/<?php echo esc_attr($whatsapp_number); ?>" 
                                               target="_blank"
                                               title="Apri chat WhatsApp con <?php echo esc_attr($prev->nome_cliente); ?>"
                                               style="display: inline-flex; align-items: center; justify-content: center; background: #25D366; color: white; width: 36px; height: 36px; border-radius: 50%; text-decoration: none; font-size: 18px; transition: all 0.3s;"
                                               onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 4px 12px rgba(37, 211, 102, 0.4)';"
                                               onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';">
                                                ðŸ“±
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #ccc; font-size: 12px;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($prev->tipo_evento ?: 'N/A'); ?></td>
                                    <td><?php echo esc_html($prev->tipo_menu ?: 'N/A'); ?></td>
                                    <td style="text-align: center;"><?php echo intval($prev->numero_invitati); ?></td>
                                    <td style="text-align: right;">
                                        <strong>â‚¬ <?php echo number_format(floatval($prev->importo_totale), 2, ',', '.'); ?></strong>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if (floatval($prev->acconto) > 0): ?>
                                            <span style="color: #2ea044;">â‚¬ <?php echo number_format(floatval($prev->acconto), 2, ',', '.'); ?></span>
                                        <?php else: ?>
                                            <span style="color: #999;">â‚¬ 0,00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $stato = strtolower($prev->stato);
                                        $badge_colors = array(
                                            'confermato' => '#2ea044',
                                            'attivo' => '#0969da',
                                            'annullato' => '#cf222e',
                                            'bozza' => '#999'
                                        );
                                        $badge_color = $badge_colors[$stato] ?? '#999';
                                        ?>
                                        <span style="background: <?php echo $badge_color; ?>; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                            <?php echo esc_html(strtoupper($prev->stato ?: 'N/A')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=edit_preventivo&id=' . $prev->id); ?>" 
                                               class="button button-small" 
                                               title="Modifica preventivo">
                                                âœï¸ Modifica
                                            </a>
                                            <?php if ($prev->googledrive_file_id): ?>
                                                <a href="https://drive.google.com/file/d/<?php echo esc_attr($prev->googledrive_file_id); ?>/view" 
                                                   target="_blank" 
                                                   class="button button-small" 
                                                   title="Apri su Google Drive"
                                                   style="font-size: 11px;">
                                                    ðŸ“ Drive
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" 
                                                    class="button button-small btn-delete-preventivo" 
                                                    data-id="<?php echo $prev->id; ?>"
                                                    title="Elimina preventivo"
                                                    style="color: #d63638;">
                                                âŒ
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- CARDS MOBILE (visibili solo su mobile) -->
                <div class="preventivi-cards-mobile">
                    <?php foreach ($preventivi as $prev): 
                        $stato = strtolower($prev->stato);
                        $badge_colors = array(
                            'confermato' => '#2ea044',
                            'attivo' => '#0969da',
                            'annullato' => '#cf222e',
                            'bozza' => '#999'
                        );
                        $badge_color = $badge_colors[$stato] ?? '#999';
                        
                        // WhatsApp number
                        $whatsapp_number = '';
                        if ($prev->telefono) {
                            $whatsapp_number = preg_replace('/[^0-9+]/', '', $prev->telefono);
                            if (substr($whatsapp_number, 0, 1) !== '+') {
                                $whatsapp_number = '+39' . $whatsapp_number;
                            }
                        }
                    ?>
                    <div class="preventivo-card-mobile" data-preventivo-id="<?php echo $prev->id; ?>" style="background: white; border: 2px solid #e9ecef; border-radius: 12px; padding: 18px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        
                        <!-- Header Card -->
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                            <div style="flex: 1;">
                                <h3 style="margin: 0 0 8px 0; font-size: 1.2rem; color: #2b1e1a; font-weight: 700;">
                                    <?php echo esc_html($prev->nome_cliente ?: 'N/A'); ?>
                                </h3>
                                <div style="color: #666; font-size: 0.9rem;">
                                    <?php echo esc_html($prev->tipo_evento ?: 'N/A'); ?>
                                </div>
                            </div>
                            <div>
                                <span style="background: <?php echo $badge_color; ?>; color: #fff; padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; white-space: nowrap;">
                                    <?php echo esc_html(strtoupper($prev->stato ?: 'N/A')); ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Info Grid -->
                        <div style="display: grid; gap: 12px; margin-bottom: 15px;">
                            
                            <!-- Data Evento -->
                            <div style="display: flex; align-items: center; gap: 12px; background: #f8f9fa; padding: 12px; border-radius: 8px;">
                                <div style="font-size: 1.8rem;">ðŸ“…</div>
                                <div style="flex: 1;">
                                    <div style="font-size: 0.75rem; color: #6c757d; margin-bottom: 3px;">Data Evento</div>
                                    <div style="font-weight: 700; color: #2b1e1a; font-size: 1.1rem;">
                                        <?php echo $prev->data_evento ? date('d/m/Y', strtotime($prev->data_evento)) : 'N/A'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Menu e Invitati -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 0.75rem; color: #6c757d; margin-bottom: 5px;">Menu</div>
                                    <div style="font-weight: 700; color: #495057;">
                                        <?php echo esc_html($prev->tipo_menu ?: 'N/A'); ?>
                                    </div>
                                </div>
                                <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 0.75rem; color: #6c757d; margin-bottom: 5px;">Invitati</div>
                                    <div style="font-weight: 700; color: #495057;">
                                        <?php echo intval($prev->numero_invitati); ?> pax
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Importi -->
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; border-radius: 10px; color: white;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <div>
                                        <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 5px;">Importo Totale</div>
                                        <div style="font-size: 1.5rem; font-weight: 800;">
                                            â‚¬<?php echo number_format(floatval($prev->importo_totale), 2, ',', '.'); ?>
                                        </div>
                                    </div>
                                    <?php if (floatval($prev->acconto) > 0): ?>
                                    <div style="text-align: right;">
                                        <div style="font-size: 0.8rem; opacity: 0.9; margin-bottom: 5px;">Acconto</div>
                                        <div style="font-size: 1.2rem; font-weight: 700;">
                                            â‚¬<?php echo number_format(floatval($prev->acconto), 2, ',', '.'); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (floatval($prev->acconto) > 0): ?>
                                <div style="padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.3); font-size: 0.85rem; opacity: 0.95;">
                                    Saldo: â‚¬<?php echo number_format(floatval($prev->importo_totale) - floatval($prev->acconto), 2, ',', '.'); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Contatti -->
                            <div style="background: #fff8e6; padding: 12px; border-radius: 8px; border-left: 4px solid #ffc107;">
                                <?php if ($prev->telefono): ?>
                                <div style="font-size: 0.9rem; color: #495057; margin-bottom: 6px;">
                                    <strong>ðŸ“ž</strong> <?php echo esc_html($prev->telefono); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($prev->email): ?>
                                <div style="font-size: 0.9rem; color: #495057;">
                                    <strong>âœ‰ï¸</strong> <?php echo esc_html($prev->email); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Azioni -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                            <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=edit_preventivo&id=' . $prev->id); ?>" 
                               class="button button-small" 
                               style="width: 100%; padding: 10px 8px; font-size: 0.85rem; background: #0073aa; color: white; border: none; border-radius: 8px; font-weight: 600; text-decoration: none; display: flex; align-items: center; justify-content: center;">
                                âœï¸ Modifica
                            </a>
                            
                            <?php if ($whatsapp_number): ?>
                            <a href="https://wa.me/<?php echo esc_attr($whatsapp_number); ?>" 
                               target="_blank"
                               style="width: 100%; padding: 10px 8px; font-size: 0.85rem; background: #25D366; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; text-align: center; display: flex; align-items: center; justify-content: center;">
                                ðŸ“± WhatsApp
                            </a>
                            <?php else: ?>
                            <div style="width: 100%; padding: 10px 8px; font-size: 0.85rem; background: #e9ecef; color: #999; border-radius: 8px; text-align: center;">
                                N/A
                            </div>
                            <?php endif; ?>
                            
                            <button type="button" 
                                    class="button button-small btn-delete-preventivo" 
                                    data-id="<?php echo $prev->id; ?>"
                                    style="width: 100%; padding: 10px 8px; font-size: 0.85rem; background: #dc3232; color: white; border: none; border-radius: 8px; font-weight: 600;">
                                âŒ
                            </button>
                        </div>
                        
                        <?php if ($prev->googledrive_file_id): ?>
                        <div style="margin-top: 10px;">
                            <a href="https://drive.google.com/file/d/<?php echo esc_attr($prev->googledrive_file_id); ?>/view" 
                               target="_blank"
                               style="display: block; width: 100%; padding: 10px; background: #f8f9fa; color: #495057; text-decoration: none; border-radius: 8px; text-align: center; font-weight: 600; border: 2px solid #e9ecef;">
                                ðŸ“ Apri su Google Drive
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginazione -->
                <?php if ($total_pages > 1): ?>
                    <div style="padding: 20px; border-top: 1px solid #e9ecef; background: #f8f9fa;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                Pagina <?php echo $page; ?> di <?php echo $total_pages; ?> 
                                (<?php echo number_format($total_preventivi); ?> preventivi totali)
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo add_query_arg('paged', $page - 1); ?>" class="button">
                                        â† Precedente
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo add_query_arg('paged', $page + 1); ?>" class="button button-primary">
                                        Successiva â†’
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.disco747-wrap {
    padding: 20px;
}
.disco747-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
.disco747-card-header {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    font-size: 16px;
    font-weight: 600;
}
.disco747-card-content {
    padding: 20px;
}
.stat-box {
    text-align: center;
    padding: 20px;
    border-radius: 8px;
}
.stat-label {
    font-size: 13px;
    margin-bottom: 8px;
    opacity: 0.9;
}
.stat-value {
    font-size: 32px;
    font-weight: bold;
}
.btn-delete-preventivo {
    background: #dc3232;
    color: white;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
    font-size: 11px;
    border-radius: 3px;
}
.btn-delete-preventivo:hover {
    background: #a00;
}

/* Intestazioni ordinabili */
.wp-list-table thead th a {
    cursor: pointer;
    user-select: none;
    transition: all 0.2s ease;
}
.wp-list-table thead th a:hover {
    background: #e8e8e9 !important;
    color: #2271b1 !important;
}

/* ============================================================================ */
/* RESPONSIVE DESIGN */
/* ============================================================================ */

/* Default: mostra tabella, nascondi cards */
.preventivi-table-desktop {
    display: block;
}
.preventivi-cards-mobile {
    display: none;
}

/* TABLET (< 992px) */
@media (max-width: 992px) {
    .disco747-wrap {
        padding: 15px;
    }
    
    .disco747-card-content {
        padding: 15px;
    }
    
    /* Statistiche a 2 colonne */
    .disco747-card-content > div[style*="grid-template-columns"] {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    /* Filtri piÃ¹ compatti */
    #filters-form > div[style*="grid-template-columns"] {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

/* MOBILE (< 768px) */
@media (max-width: 768px) {
    .disco747-wrap {
        padding: 10px;
    }
    
    /* Header piÃ¹ compatto */
    .disco747-wrap > div:first-child {
        flex-direction: column;
        align-items: stretch !important;
        gap: 15px;
    }
    
    .disco747-wrap > div:first-child h1 {
        font-size: 1.5rem !important;
    }
    
    .disco747-wrap > div:first-child > div {
        width: 100%;
        justify-content: stretch;
    }
    
    .disco747-wrap > div:first-child > div .button {
        flex: 1;
    }
    
    /* Statistiche a 1 colonna */
    .disco747-card-content > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    .stat-box {
        padding: 15px !important;
    }
    
    .stat-value {
        font-size: 24px !important;
    }
    
    /* Filtri a 1 colonna */
    #filters-form > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    #filters-form input,
    #filters-form select {
        font-size: 16px !important; /* Previene zoom su iOS */
    }
    
    #filters-form > div:last-child {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    #filters-form > div:last-child .button {
        width: 100%;
    }
    
    /* NASCONDI TABELLA, MOSTRA CARDS */
    .preventivi-table-desktop {
        display: none !important;
    }
    
    .preventivi-cards-mobile {
        display: block !important;
        padding: 15px;
    }
    
    /* Paginazione piÃ¹ compatta */
    .disco747-card-content > div[style*="padding: 20px"] {
        padding: 15px !important;
    }
    
    .disco747-card-content > div[style*="padding: 20px"] > div:first-child {
        flex-direction: column;
        align-items: stretch !important;
        gap: 15px;
        text-align: center;
    }
    
    .disco747-card-content > div[style*="padding: 20px"] > div:first-child > div:last-child {
        width: 100%;
    }
    
    .disco747-card-content > div[style*="padding: 20px"] > div:first-child > div:last-child a {
        flex: 1;
    }
}

/* MOBILE SMALL (< 480px) */
@media (max-width: 480px) {
    .disco747-card-header {
        padding: 15px !important;
        font-size: 14px !important;
    }
    
    .preventivo-card-mobile h3 {
        font-size: 1.1rem !important;
    }
    
    .preventivo-card-mobile {
        padding: 15px !important;
    }
}

/* Animazione cards */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.preventivo-card-mobile {
    animation: slideInUp 0.3s ease-out;
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('âœ… View Preventivi JS caricato');

    // ========================================================================
    // EXPORT CSV
    // ========================================================================
    $('#export-csv-btn').on('click', function() {
        console.log('ðŸ“¥ Export CSV richiesto');
        
        // Costruisci URL con parametri filtri
        var url = '<?php echo admin_url('admin-ajax.php'); ?>';
        var params = {
            action: 'disco747_export_preventivi_csv',
            nonce: '<?php echo wp_create_nonce('disco747_export_csv'); ?>',
            <?php if (!empty($filters['search'])): ?>search: '<?php echo esc_js($filters['search']); ?>',<?php endif; ?>
            <?php if (!empty($filters['stato'])): ?>stato: '<?php echo esc_js($filters['stato']); ?>',<?php endif; ?>
            <?php if (!empty($filters['menu'])): ?>menu: '<?php echo esc_js($filters['menu']); ?>',<?php endif; ?>
            <?php if ($filters['anno'] > 0): ?>anno: <?php echo $filters['anno']; ?>,<?php endif; ?>
            <?php if ($filters['mese'] > 0): ?>mese: <?php echo $filters['mese']; ?>,<?php endif; ?>
        };
        
        var queryString = $.param(params);
        window.location.href = url + '?' + queryString;
    });

    // ========================================================================
    // ELIMINA PREVENTIVO
    // ========================================================================
    $(document).on('click', '.btn-delete-preventivo', function() {
        var preventivoId = $(this).data('id');
        var $row = $(this).closest('tr');
        var cliente = $row.find('td:nth-child(2) strong').text();
        
        if (!confirm('âš ï¸ Sei sicuro di voler eliminare il preventivo di ' + cliente + '?\n\nQuesta azione Ã¨ irreversibile!')) {
            return;
        }
        
        console.log('ðŸ—‘ï¸ Eliminazione preventivo ID:', preventivoId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_delete_preventivo',
                nonce: '<?php echo wp_create_nonce('disco747_delete_preventivo'); ?>',
                preventivo_id: preventivoId
            },
            beforeSend: function() {
                $row.css('opacity', '0.5');
            },
            success: function(response) {
                console.log('âœ… Risposta eliminazione:', response);
                
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                    });
                    alert('âœ… Preventivo eliminato con successo');
                } else {
                    alert('âŒ Errore: ' + (response.data || 'Impossibile eliminare il preventivo'));
                    $row.css('opacity', '1');
                }
            },
            error: function(xhr, status, error) {
                console.error('âŒ Errore AJAX:', error);
                alert('âŒ Errore di connessione al server');
                $row.css('opacity', '1');
            }
        });
    });

    console.log('âœ… Tutti gli handler JS registrati');
});
</script>