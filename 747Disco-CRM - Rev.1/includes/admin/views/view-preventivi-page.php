<?php
/**
 * Pagina View Preventivi - 747 Disco CRM
 * Versione 11.9.3 - RICERCA CORRETTA
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @version    11.9.3-SEARCH-FIXED
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
    'order_by' => sanitize_key($_GET['order_by'] ?? 'data_evento'),
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

// ========================================================================
// APPLICAZIONE FILTRI - RICERCA CORRETTA
// ========================================================================

// Filtro RICERCA - Cerca in PIÙ CAMPI
// nome_cliente contiene NOME + COGNOME insieme
// nome_referente e cognome_referente sono SEPARATI
if (!empty($filters['search'])) {
    $where[] = "(
        nome_cliente LIKE %s 
        OR CONCAT(nome_referente, ' ', cognome_referente) LIKE %s
        OR email LIKE %s 
        OR telefono LIKE %s 
        OR tipo_evento LIKE %s
        OR preventivo_id LIKE %s
    )";
    $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
    
    // Aggiungi il termine di ricerca per ogni campo
    $where_values[] = $search_term;  // nome_cliente (contiene già nome cognome)
    $where_values[] = $search_term;  // CONCAT(nome_referente, ' ', cognome_referente)
    $where_values[] = $search_term;  // email
    $where_values[] = $search_term;  // telefono
    $where_values[] = $search_term;  // tipo_evento
    $where_values[] = $search_term;  // preventivo_id
}

// Filtro STATO
if (!empty($filters['stato'])) {
    $where[] = "stato = %s";
    $where_values[] = $filters['stato'];
}

// Filtro MENU
if (!empty($filters['menu'])) {
    $where[] = "tipo_menu LIKE %s";
    $where_values[] = '%' . $wpdb->esc_like($filters['menu']) . '%';
}

// Filtro ANNO
if ($filters['anno'] > 0) {
    $where[] = "YEAR(data_evento) = %d";
    $where_values[] = $filters['anno'];
}

// Filtro MESE
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
$total_preventivi = intval($wpdb->get_var($count_query));
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
    'totale' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name}")),
    'attivi' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE stato = 'attivo'")),
    'confermati' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE acconto > 0 OR stato = 'confermato'")),
    'annullati' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE stato = 'annullato'"))
);

?>

<div class="wrap disco747-wrap">
    
    <!-- Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
        <h1 style="margin: 0;">📊 Database Preventivi</h1>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo admin_url('admin.php?page=disco747-scan-excel'); ?>" class="button">
                📄 Excel Scan
            </a>
            <button type="button" id="export-csv-btn" class="button button-primary">
                📥 Export CSV
            </button>
            <button type="button" id="export-excel-btn" class="button button-primary" style="background: #217346; border-color: #1e6b3e;">
                📊 Export Excel
            </button>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-content">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="stat-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="stat-label">📊 Totale</div>
                    <div class="stat-value"><?php echo number_format($stats['totale']); ?></div>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <div class="stat-label">🔵 Attivi</div>
                    <div class="stat-value"><?php echo number_format($stats['attivi']); ?></div>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="stat-label">✅ Confermati</div>
                    <div class="stat-value"><?php echo number_format($stats['confermati']); ?></div>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <div class="stat-label">❌ Annullati</div>
                    <div class="stat-value"><?php echo number_format($stats['annullati']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtri -->
    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header">
            🔍 Filtri di Ricerca
            <?php if (array_filter($filters, function($v) { return !empty($v) && $v !== 'DESC' && $v !== 'data_evento'; })): ?>
                <a href="<?php echo admin_url('admin.php?page=disco747-view-preventivi'); ?>" 
                   style="float: right; font-size: 13px; color: #2271b1; text-decoration: none;">
                    ✖ Cancella Filtri
                </a>
            <?php endif; ?>
        </div>
        <div class="disco747-card-content">
            <form method="get" action="" id="filters-form">
                <input type="hidden" name="page" value="disco747-view-preventivi">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <!-- Ricerca -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">🔍 Ricerca Avanzata</label>
                        <input type="text" name="search" 
                               value="<?php echo esc_attr($filters['search']); ?>" 
                               placeholder="Nome, referente, email, tel..."
                               style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            💡 Cerca in: cliente, referente, email, telefono, evento
                        </small>
                    </div>

                    <!-- Stato -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">📌 Stato</label>
                        <select name="stato" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                            <option value="">Tutti gli stati</option>
                            <option value="attivo" <?php selected($filters['stato'], 'attivo'); ?>>Attivo</option>
                            <option value="confermato" <?php selected($filters['stato'], 'confermato'); ?>>Confermato</option>
                            <option value="annullato" <?php selected($filters['stato'], 'annullato'); ?>>Annullato</option>
                            <option value="bozza" <?php selected($filters['stato'], 'bozza'); ?>>Bozza</option>
                        </select>
                    </div>

                    <!-- Menu -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">🍽️ Menu</label>
                        <select name="menu" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                            <option value="">Tutti i menu</option>
                            <option value="MENU 7" <?php selected($filters['menu'], 'MENU 7'); ?>>MENU 7</option>
                            <option value="MENU 7-4" <?php selected($filters['menu'], 'MENU 7-4'); ?>>MENU 7-4</option>
                            <option value="MENU 7-4-7" <?php selected($filters['menu'], 'MENU 7-4-7'); ?>>MENU 7-4-7</option>
                        </select>
                    </div>

                    <!-- Anno -->
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">📅 Anno</label>
                        <select name="anno" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                            <option value="">Tutti gli anni</option>
                            <?php
                            $current_year = date('Y');
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
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">📆 Mese</label>
                        <select name="mese" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
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
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">🔢 Ordina per</label>
                        <select name="order_by" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;">
                            <option value="data_evento" <?php selected($filters['order_by'], 'data_evento'); ?>>Data Evento</option>
                            <option value="created_at" <?php selected($filters['order_by'], 'created_at'); ?>>Data Creazione</option>
                            <option value="nome_cliente" <?php selected($filters['order_by'], 'nome_cliente'); ?>>Cliente</option>
                            <option value="importo_totale" <?php selected($filters['order_by'], 'importo_totale'); ?>>Importo</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <button type="submit" class="button button-primary" style="background: #0073aa; border-color: #005a87;">
                        🔍 Applica Filtri
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=disco747-view-preventivi'); ?>" 
                       class="button">Ripristina Filtri</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabella Preventivi -->
    <div class="disco747-card">
        <div class="disco747-card-header">
            📋 Preventivi (<?php echo number_format($total_preventivi); ?> risultati)
            <?php if (!empty($filters['search'])): ?>
                <span style="color: #666; font-size: 13px; font-weight: normal;">
                    🔎 Ricerca: "<strong><?php echo esc_html($filters['search']); ?></strong>"
                </span>
            <?php endif; ?>
        </div>
        <div class="disco747-card-content" style="padding: 0;">
            
            <?php if (empty($preventivi)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 15px;">🔭</div>
                    <h3 style="margin: 0 0 10px 0;">Nessun preventivo trovato</h3>
                    <p style="margin: 0;">
                        <?php if (!empty($filters['search'])): ?>
                            Nessun risultato per "<strong><?php echo esc_html($filters['search']); ?></strong>"<br>
                            <small style="color: #999; margin-top: 10px; display: block;">
                                Prova a cercare:<br>
                                • Nome del cliente<br>
                                • Nome del referente<br>
                                • Email o telefono<br>
                                • Tipo di evento<br>
                                • ID preventivo
                            </small>
                        <?php else: ?>
                            Prova a modificare i filtri
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                
                <!-- TABELLA DESKTOP -->
                <div style="overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead>
                            <tr>
                                <th style="width: 100px;">Data Evento</th>
                                <th>Cliente</th>
                                <th>Referente</th>
                                <th style="width: 60px; text-align: center;">📞</th>
                                <th>Tipo Evento</th>
                                <th style="width: 100px;">Menu</th>
                                <th style="width: 70px;">Invitati</th>
                                <th style="width: 100px;">Importo</th>
                                <th style="width: 90px;">Acconto</th>
                                <th style="width: 80px;">Stato</th>
                                <th style="width: 180px;">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preventivi as $prev): ?>
                                <tr data-preventivo-id="<?php echo $prev->id; ?>">
                                    <td style="font-weight: 500;">
                                        <?php echo $prev->data_evento ? date('d/m/Y', strtotime($prev->data_evento)) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <strong style="color: #0073aa;">
                                            <?php echo esc_html($prev->nome_cliente ?: 'N/A'); ?>
                                        </strong>
                                        <?php if ($prev->email): ?>
                                            <br><small style="color: #666;">✉️ <?php echo esc_html($prev->email); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $referente = trim(($prev->nome_referente ?? '') . ' ' . ($prev->cognome_referente ?? ''));
                                        echo esc_html($referente ?: '—');
                                        ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($prev->telefono): 
                                            $whatsapp_number = preg_replace('/[^0-9+]/', '', $prev->telefono);
                                            if (substr($whatsapp_number, 0, 1) !== '+') {
                                                $whatsapp_number = '+39' . $whatsapp_number;
                                            }
                                        ?>
                                            <a href="https://wa.me/<?php echo esc_attr($whatsapp_number); ?>" 
                                               target="_blank"
                                               title="<?php echo esc_attr($prev->telefono); ?>"
                                               style="display: inline-flex; align-items: center; justify-content: center; background: #25D366; color: white; width: 36px; height: 36px; border-radius: 50%; text-decoration: none; font-size: 18px;">
                                                📱
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #ccc;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($prev->tipo_evento ?: 'N/A'); ?></td>
                                    <td><?php echo esc_html($prev->tipo_menu ?: 'N/A'); ?></td>
                                    <td style="text-align: center;"><?php echo intval($prev->numero_invitati); ?></td>
                                    <td style="text-align: right; font-weight: 600;">
                                        € <?php echo number_format(floatval($prev->importo_totale), 2, ',', '.'); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if (floatval($prev->acconto) > 0): ?>
                                            <span style="color: #2ea044; font-weight: 600;">€ <?php echo number_format(floatval($prev->acconto), 2, ',', '.'); ?></span>
                                        <?php else: ?>
                                            <span style="color: #999;">€ 0,00</span>
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
                                        <span style="background: <?php echo $badge_color; ?>; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block;">
                                            <?php echo esc_html(strtoupper($prev->stato ?: 'N/A')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=edit_preventivo&id=' . $prev->id); ?>" 
                                               class="button button-small"
                                               title="Modifica preventivo">
                                                ✏️ Modifica
                                            </a>
                                            <?php if ($prev->googledrive_file_id): ?>
                                                <a href="https://drive.google.com/file/d/<?php echo esc_attr($prev->googledrive_file_id); ?>/view" 
                                                   target="_blank" 
                                                   class="button button-small"
                                                   title="Apri su Google Drive">
                                                    🔍 Drive
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" 
                                                    class="button button-small btn-delete-preventivo" 
                                                    data-id="<?php echo $prev->id; ?>"
                                                    title="Elimina preventivo"
                                                    style="color: #d63638;">
                                                ❌
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginazione -->
                <?php if ($total_pages > 1): ?>
                    <div style="padding: 20px; border-top: 1px solid #e9ecef; background: #f8f9fa;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="color: #666;">
                                Pagina <strong><?php echo $page; ?></strong> di <strong><?php echo $total_pages; ?></strong> 
                                (<strong><?php echo number_format($total_preventivi); ?></strong> preventivi)
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo add_query_arg('paged', $page - 1); ?>" class="button">
                                        ← Precedente
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo add_query_arg('paged', $page + 1); ?>" class="button button-primary">
                                        Successiva →
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
    transition: all 0.2s;
}
.btn-delete-preventivo:hover {
    background: #a00;
    transform: scale(1.05);
}

@media (max-width: 768px) {
    .disco747-wrap {
        padding: 10px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('✅ View Preventivi JS caricato');

    // EXPORT CSV
    $('#export-csv-btn').on('click', function() {
        console.log('📥 Export CSV richiesto');
        
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

    // EXPORT EXCEL
    $('#export-excel-btn').on('click', function() {
        console.log('📊 Export Excel richiesto');
        
        var url = '<?php echo admin_url('admin-ajax.php'); ?>';
        var params = {
            action: 'disco747_export_preventivi_excel',
            nonce: '<?php echo wp_create_nonce('disco747_export_excel'); ?>',
            <?php if (!empty($filters['search'])): ?>search: '<?php echo esc_js($filters['search']); ?>',<?php endif; ?>
            <?php if (!empty($filters['stato'])): ?>stato: '<?php echo esc_js($filters['stato']); ?>',<?php endif; ?>
            <?php if (!empty($filters['menu'])): ?>menu: '<?php echo esc_js($filters['menu']); ?>',<?php endif; ?>
            <?php if ($filters['anno'] > 0): ?>anno: <?php echo $filters['anno']; ?>,<?php endif; ?>
            <?php if ($filters['mese'] > 0): ?>mese: <?php echo $filters['mese']; ?>,<?php endif; ?>
        };
        
        var queryString = $.param(params);
        window.location.href = url + '?' + queryString;
    });

    // ELIMINA PREVENTIVO
    $(document).on('click', '.btn-delete-preventivo', function() {
        var preventivoId = $(this).data('id');
        var $row = $(this).closest('tr');
        
        if (!confirm('⚠️ Sei sicuro di voler eliminare questo preventivo?\n\nQuesta azione è irreversibile!')) {
            return;
        }
        
        console.log('🗑️ Eliminazione preventivo ID:', preventivoId);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
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
                console.log('✅ Risposta eliminazione:', response);
                
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                    });
                    alert('✅ Preventivo eliminato con successo');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    alert('❌ Errore: ' + (response.data || 'Impossibile eliminare il preventivo'));
                    $row.css('opacity', '1');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX:', error);
                alert('❌ Errore di connessione al server');
                $row.css('opacity', '1');
            }
        });
    });

    console.log('✅ Tutti gli handler JS registrati');
});
</script>