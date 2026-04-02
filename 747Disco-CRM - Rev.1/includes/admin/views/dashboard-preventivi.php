<?php
/**
 * Dashboard Preventivi - 747 Disco CRM  
 * Versione 11.8.5 - Con link modifica corretto
 */

if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'disco747_preventivi';

$per_page = 20;
$paged = max(1, intval($_GET['paged'] ?? 1));
$offset = ($paged - 1) * $per_page;

$where = array('1=1');
$vals = array();

if (!empty($_GET['search'])) {
    $where[] = "(nome_cliente LIKE %s OR email LIKE %s)";
    $s = '%' . $wpdb->esc_like($_GET['search']) . '%';
    $vals[] = $s; $vals[] = $s;
}

if (!empty($_GET['stato'])) {
    $where[] = "stato = %s";
    $vals[] = sanitize_key($_GET['stato']);
}

if (!empty($_GET['menu'])) {
    $where[] = "tipo_menu LIKE %s";
    $vals[] = '%' . $wpdb->esc_like($_GET['menu']) . '%';
}

$w = implode(' AND ', $where);

if (!empty($vals)) {
    $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$w}", $vals));
    $q = $wpdb->prepare("SELECT * FROM {$table} WHERE {$w} ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge($vals, array($per_page, $offset)));
} else {
    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $q = $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", array($per_page, $offset));
}

$preventivi = $wpdb->get_results($q, ARRAY_A);

$attivi = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE stato = 'attivo'");
$conf = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE acconto > 0");
$ann = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE stato = 'annullato'");

$pages = ceil($total / $per_page);
?>

<div class="wrap" style="max-width:1400px;margin:20px auto">

<!-- Header con pulsante Nuovo Preventivo -->
<div style="background:linear-gradient(135deg,#2c3e50,#34495e);color:#fff;padding:30px;border-radius:15px;margin-bottom:30px;box-shadow:0 10px 30px rgba(0,0,0,.2);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px">
    <div>
        <h1 style="margin:0 0 10px;font-size:2.5rem;display:flex;align-items:center;gap:15px">📊 Dashboard Preventivi</h1>
        <p style="margin:0;opacity:.9;font-size:1.1rem">Gestione completa preventivi 747 Disco</p>
    </div>
    <a href="?page=disco747-crm&action=new_preventivo" class="button button-primary button-hero" style="background:#28a745;border-color:#28a745;font-size:1.1rem;padding:15px 30px;height:auto;line-height:1.4">
        ➕ Nuovo Preventivo
    </a>
</div>

<!-- Statistiche -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px">

<div style="background:linear-gradient(135deg,#6c757d,#5a6268);color:#fff;padding:25px;border-radius:12px;text-align:center;box-shadow:0 5px 15px rgba(0,0,0,.1)">
<h3 style="margin:0;color:#fff;font-size:2.5rem"><?php echo number_format($total); ?></h3>
<p style="margin:10px 0 0;font-weight:600;font-size:1rem">📋 Totali</p>
</div>

<div style="background:linear-gradient(135deg,#28a745,#20c997);color:#fff;padding:25px;border-radius:12px;text-align:center;box-shadow:0 5px 15px rgba(0,0,0,.1)">
<h3 style="margin:0;color:#fff;font-size:2.5rem"><?php echo number_format($attivi); ?></h3>
<p style="margin:10px 0 0;font-weight:600;font-size:1rem">✅ Attivi</p>
</div>

<div style="background:linear-gradient(135deg,#007bff,#0056b3);color:#fff;padding:25px;border-radius:12px;text-align:center;box-shadow:0 5px 15px rgba(0,0,0,.1)">
<h3 style="margin:0;color:#fff;font-size:2.5rem"><?php echo number_format($conf); ?></h3>
<p style="margin:10px 0 0;font-weight:600;font-size:1rem">🎉 Confermati</p>
</div>

<div style="background:linear-gradient(135deg,#dc3545,#c82333);color:#fff;padding:25px;border-radius:12px;text-align:center;box-shadow:0 5px 15px rgba(0,0,0,.1)">
<h3 style="margin:0;color:#fff;font-size:2.5rem"><?php echo number_format($ann); ?></h3>
<p style="margin:10px 0 0;font-weight:600;font-size:1rem">❌ Annullati</p>
</div>

</div>

<!-- Filtri -->
<div style="background:#fff;padding:20px;border-radius:12px;margin-bottom:20px;box-shadow:0 3px 10px rgba(0,0,0,.08)">
<form method="get" style="display:flex;gap:15px;flex-wrap:wrap;align-items:end">
<input type="hidden" name="page" value="disco747-crm">
<input type="hidden" name="action" value="dashboard_preventivi">

<div style="flex:1;min-width:200px">
<label style="display:block;margin-bottom:5px;font-weight:600">🔍 Cerca</label>
<input type="text" name="search" value="<?php echo esc_attr($_GET['search'] ?? ''); ?>" placeholder="Nome o email..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px">
</div>

<div style="min-width:150px">
<label style="display:block;margin-bottom:5px;font-weight:600">📊 Stato</label>
<select name="stato" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px">
<option value="">Tutti</option>
<option value="attivo" <?php selected($_GET['stato'] ?? '', 'attivo'); ?>>Attivo</option>
<option value="confermato" <?php selected($_GET['stato'] ?? '', 'confermato'); ?>>Confermato</option>
<option value="annullato" <?php selected($_GET['stato'] ?? '', 'annullato'); ?>>Annullato</option>
</select>
</div>

<div style="min-width:150px">
<label style="display:block;margin-bottom:5px;font-weight:600">🍽️ Menu</label>
<select name="menu" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px">
<option value="">Tutti</option>
<option value="Menu 7" <?php selected($_GET['menu'] ?? '', 'Menu 7'); ?>>Menu 7</option>
<option value="Menu 7-4" <?php selected($_GET['menu'] ?? '', 'Menu 7-4'); ?>>Menu 7-4</option>
<option value="Menu 7-4-7" <?php selected($_GET['menu'] ?? '', 'Menu 7-4-7'); ?>>Menu 7-4-7</option>
</select>
</div>

<button type="submit" class="button button-primary" style="padding:8px 20px">🔍 Filtra</button>
<a href="?page=disco747-crm&action=dashboard_preventivi" class="button" style="padding:8px 20px">🔄 Reset</a>
</form>
</div>

<!-- Tabella Preventivi -->
<div style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 3px 10px rgba(0,0,0,.08)">

<div style="overflow-x:auto">
<table class="wp-list-table widefat fixed striped" style="width:100%;border-collapse:collapse">
<thead>
<tr style="background:#f8f9fa">
<th style="padding:15px;text-align:left;border-bottom:2px solid #dee2e6;font-weight:700">ID</th>
<th style="padding:15px;text-align:left;border-bottom:2px solid #dee2e6;font-weight:700">Cliente</th>
<th style="padding:15px;text-align:left;border-bottom:2px solid #dee2e6;font-weight:700">Evento</th>
<th style="padding:15px;text-align:left;border-bottom:2px solid #dee2e6;font-weight:700">Data</th>
<th style="padding:15px;text-align:left;border-bottom:2px solid #dee2e6;font-weight:700">Menu</th>
<th style="padding:15px;text-align:center;border-bottom:2px solid #dee2e6;font-weight:700">Invitati</th>
<th style="padding:15px;text-align:center;border-bottom:2px solid #dee2e6;font-weight:700">Stato</th>
<th style="padding:15px;text-align:right;border-bottom:2px solid #dee2e6;font-weight:700">Importo</th>
<th style="padding:15px;text-align:center;border-bottom:2px solid #dee2e6;font-weight:700">Azioni</th>
</tr>
</thead>
<tbody>
<?php if (empty($preventivi)): ?>
<tr><td colspan="9" style="padding:40px;text-align:center;color:#6c757d"><p style="font-size:1.2rem;margin:0">📭 Nessun preventivo trovato</p></td></tr>
<?php else: foreach ($preventivi as $p): ?>
<tr style="transition:background .2s" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
<td style="padding:15px;border-bottom:1px solid #dee2e6"><strong style="color:#007bff">#<?php echo esc_html($p['preventivo_id']); ?></strong></td>
<td style="padding:15px;border-bottom:1px solid #dee2e6"><div><strong><?php echo esc_html($p['nome_cliente']); ?></strong><br><small style="color:#6c757d">📧 <?php echo esc_html($p['email']); ?></small></div></td>
<td style="padding:15px;border-bottom:1px solid #dee2e6"><?php echo esc_html($p['tipo_evento']); ?></td>
<td style="padding:15px;border-bottom:1px solid #dee2e6"><?php echo date('d/m/Y', strtotime($p['data_evento'])); ?></td>
<td style="padding:15px;border-bottom:1px solid #dee2e6"><span style="background:#e9ecef;padding:4px 8px;border-radius:4px;font-size:.9rem"><?php echo esc_html($p['tipo_menu']); ?></span></td>
<td style="padding:15px;border-bottom:1px solid #dee2e6;text-align:center"><strong><?php echo number_format($p['numero_invitati']); ?></strong></td>
<td style="padding:15px;border-bottom:1px solid #dee2e6;text-align:center">
<?php
$sl = '⏳ Attivo'; $sc = '#ffc107';
if ($p['acconto'] > 0) { $sl = '✅ Confermato'; $sc = '#28a745'; }
elseif ($p['stato'] === 'annullato') { $sl = '❌ Annullato'; $sc = '#dc3545'; }
?>
<span style="background:<?php echo $sc; ?>;color:#fff;padding:5px 10px;border-radius:5px;font-size:.85rem;font-weight:600"><?php echo $sl; ?></span>
</td>
<td style="padding:15px;border-bottom:1px solid #dee2e6;text-align:right"><strong style="color:#28a745;font-size:1.1rem">€<?php echo number_format($p['importo_totale'], 2); ?></strong><?php if ($p['acconto'] > 0): ?><br><small style="color:#6c757d">Acconto: €<?php echo number_format($p['acconto'], 2); ?></small><?php endif; ?></td>
<td style="padding:15px;border-bottom:1px solid #dee2e6;text-align:center">
    <a href="?page=disco747-crm&action=new_preventivo&edit_id=<?php echo $p['id']; ?>" class="button button-small" style="margin:2px;background:#007bff;color:#fff;border-color:#007bff">✏️ Modifica</a>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<?php if ($pages > 1): ?>
<div style="padding:20px;border-top:1px solid #dee2e6;text-align:center">
<?php
$base = add_query_arg(array('page'=>'disco747-crm','action'=>'dashboard_preventivi','search'=>$_GET['search']??'','stato'=>$_GET['stato']??'','menu'=>$_GET['menu']??''), admin_url('admin.php'));
for ($i=1; $i<=$pages; $i++):
$cur = ($i === $paged);
$url = add_query_arg('paged', $i, $base);
?>
<a href="<?php echo esc_url($url); ?>" class="button <?php echo $cur ? 'button-primary' : ''; ?>" style="margin:0 3px"><?php echo $i; ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>

</div>

</div>