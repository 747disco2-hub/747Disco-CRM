<?php
/**
 * Script v2 per aggiungere debug a excel-scan-page.php
 * Versione più flessibile che cerca la chiusura </script> finale
 */

$file_path = __DIR__ . '/includes/admin/views/excel-scan-page.php';

if (!file_exists($file_path)) {
    die("❌ File excel-scan-page.php non trovato in: " . $file_path);
}

// Leggi il file
$content = file_get_contents($file_path);

echo "<h3>📋 Analisi file excel-scan-page.php</h3>";
echo "Dimensione file: " . strlen($content) . " bytes<br>";
echo "Numero righe: " . substr_count($content, "\n") . "<br><br>";

// Mostra le ultime 100 righe per debug
$lines = explode("\n", $content);
$total_lines = count($lines);
echo "<h4>🔍 Ultime 50 righe del file:</h4>";
echo "<pre style='background:#f5f5f5; padding:10px; border:1px solid #ccc; max-height:300px; overflow:auto;'>";
for ($i = max(0, $total_lines - 50); $i < $total_lines; $i++) {
    echo "Riga " . ($i + 1) . ": " . htmlspecialchars($lines[$i]) . "\n";
}
echo "</pre>";

// Cerca pattern alternativi
echo "<h4>🔎 Pattern cercati nel file:</h4>";

$patterns = [
    "console.log('Excel Scanner inizializzazione completata');",
    "});",
    "</script>",
    "Excel Scanner",
    "jQuery(document).ready"
];

foreach ($patterns as $pattern) {
    $count = substr_count($content, $pattern);
    echo "Pattern '<strong>" . htmlspecialchars($pattern) . "</strong>': trovato <strong>{$count}</strong> volte<br>";
}

echo "<br><h4>📍 Posizione ultima chiusura &lt;/script&gt;:</h4>";
$last_script_pos = strrpos($content, '</script>');
if ($last_script_pos !== false) {
    echo "Trovata alla posizione: <strong>{$last_script_pos}</strong><br>";
    
    // Mostra contesto (100 caratteri prima e dopo)
    $context_start = max(0, $last_script_pos - 200);
    $context_end = min(strlen($content), $last_script_pos + 200);
    $context = substr($content, $context_start, $context_end - $context_start);
    
    echo "<h4>📄 Contesto ultima chiusura &lt;/script&gt;:</h4>";
    echo "<pre style='background:#fff3cd; padding:10px; border:1px solid #ffc107;'>";
    echo htmlspecialchars($context);
    echo "</pre>";
    
    // Prova ad aggiungere il debug DOPO l'ultima chiusura </script>
    echo "<br><h3>✅ Provo ad aggiungere il debug...</h3>";
    
    $debug_code = "\n\n" .
"<!-- ============================================================ -->\n" .
"<!-- TEST DEBUG - DA RIMUOVERE DOPO VERIFICA -->\n" .
"<!-- ============================================================ -->\n" .
"<script>\n" .
"console.log('🧪 [DEBUG-TEST] Script test caricato');\n\n" .
"jQuery(document).ready(function(\$) {\n" .
"    console.log('🧪 [DEBUG-TEST] INIZIO TEST');\n" .
"    alert('🧪 TEST 1/7: jQuery caricato!');\n" .
"    \n" .
"    var btn = \$('#start-batch-analysis');\n" .
"    console.log('🧪 [DEBUG-TEST] Bottoni trovati:', btn.length);\n" .
"    \n" .
"    if (btn.length > 0) {\n" .
"        alert('🧪 TEST 2/7: Bottone TROVATO! ✅');\n" .
"    } else {\n" .
"        alert('🧪 TEST 2/7: Bottone NON TROVATO! ❌');\n" .
"        return;\n" .
"    }\n" .
"    \n" .
"    var existingEvents = \$._data(btn[0], 'events');\n" .
"    if (existingEvents && existingEvents.click) {\n" .
"        alert('🧪 TEST 3/7: Ci sono ' + existingEvents.click.length + ' handler');\n" .
"    } else {\n" .
"        alert('🧪 TEST 3/7: Nessun handler esistente');\n" .
"    }\n" .
"    \n" .
"    btn.off('click');\n" .
"    alert('🧪 TEST 4/7: Handler rimossi');\n" .
"    \n" .
"    btn.on('click', function(e) {\n" .
"        console.log('🧪 [DEBUG-TEST] CLICK INTERCETTATO!');\n" .
"        alert('🧪 TEST 5/7: CLICK FUNZIONA! ✅');\n" .
"        \n" .
"        if (typeof ajaxurl === 'undefined') {\n" .
"            alert('🧪 TEST 6/7: ajaxurl NON DEFINITO! ❌');\n" .
"            e.preventDefault();\n" .
"            return false;\n" .
"        }\n" .
"        alert('🧪 TEST 6/7: ajaxurl OK');\n" .
"        \n" .
"        \$.ajax({\n" .
"            url: ajaxurl,\n" .
"            type: 'POST',\n" .
"            data: {\n" .
"                action: 'disco747_batch_scan_excel',\n" .
"                nonce: '" . wp_create_nonce('disco747_batch_scan') . "',\n" .
"                file_id: 'TEST',\n" .
"                file_name: 'TEST.xlsx',\n" .
"                current_index: 0,\n" .
"                total_files: 1\n" .
"            },\n" .
"            success: function(response) {\n" .
"                console.log('🧪 AJAX SUCCESS:', response);\n" .
"                alert('🧪 TEST 7/7: AJAX OK! ✅\\n' + JSON.stringify(response).substring(0, 100));\n" .
"            },\n" .
"            error: function(xhr, status, error) {\n" .
"                console.log('🧪 AJAX ERROR:', status, error);\n" .
"                alert('🧪 TEST 7/7: AJAX ERRORE! ❌\\nStatus: ' + status + '\\nError: ' + error);\n" .
"            }\n" .
"        });\n" .
"        \n" .
"        e.preventDefault();\n" .
"        return false;\n" .
"    });\n" .
"    \n" .
"    alert('🧪 SETUP OK! Clicca sul bottone batch');\n" .
"});\n" .
"</script>\n" .
"<!-- ============================================================ -->\n";
    
    // Inserisci DOPO l'ultima chiusura </script>
    $insert_position = $last_script_pos + strlen('</script>');
    $new_content = substr($content, 0, $insert_position) . $debug_code . substr($content, $insert_position);
    
    // Backup
    $backup_path = $file_path . '.backup-' . date('YmdHis');
    copy($file_path, $backup_path);
    echo "✅ Backup creato: " . basename($backup_path) . "<br>";
    
    // Scrivi
    file_put_contents($file_path, $new_content);
    echo "✅ Debug aggiunto dopo ultima chiusura &lt;/script&gt;<br>";
    echo "✅ File aggiornato con successo!<br><br>";
    
    echo "<h3>🎯 Prossimi passi:</h3>";
    echo "1. Ricarica la pagina Excel Scan<br>";
    echo "2. Vedrai 7 alert di test<br>";
    echo "3. Dimmi quali test passano e quali falliscono<br>";
    echo "4. Dopo il test, elimina questo file add-debug-v2.php<br>";
    
} else {
    echo "❌ Nessuna chiusura &lt;/script&gt; trovata nel file!";
}
?>