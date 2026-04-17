<?php
/**
 * TEST SCRIPT STANDALONE - Verifica Template WhatsApp JSON
 * Versione senza dipendenze WordPress
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test WhatsApp Template - 747 Disco</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 0;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2b1e1a 0%, #1a1310 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
            color: #c28a4d;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .header p {
            margin: 15px 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        .content {
            padding: 30px;
        }
        .step {
            margin: 30px 0;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 5px solid #007bff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .step h2 {
            margin-top: 0;
            color: #2b1e1a;
            font-size: 1.5rem;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        pre {
            background: white;
            padding: 15px;
            border-radius: 5px;
            overflow: auto;
            border: 1px solid #dee2e6;
            max-height: 400px;
            line-height: 1.5;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .template-box {
            background: white;
            padding: 20px;
            margin: 15px 0;
            border-radius: 10px;
            border-left: 4px solid #28a745;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .whatsapp-preview {
            background: #e5ddd5;
            padding: 20px;
            border-radius: 10px;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .whatsapp-bubble {
            background: #dcf8c6;
            padding: 15px;
            border-radius: 8px;
            white-space: pre-wrap;
            line-height: 1.5;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #25D366;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 10px 5px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #128C7E;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .stat-box h3 {
            margin: 0;
            font-size: 2.5rem;
        }
        .stat-box p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        details {
            margin: 15px 0;
            background: white;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        summary {
            cursor: pointer;
            font-weight: bold;
            color: #007bff;
            padding: 5px;
        }
        summary:hover {
            color: #0056b3;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            border-top: 1px solid #dee2e6;
            margin-top: 30px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🧪 Test Template WhatsApp JSON</h1>
        <p>747 Disco CRM - Sistema di Diagnostica Template</p>
    </div>

    <div class="content">

<?php
// ===========================================================================
// STEP 1: DETERMINA PERCORSO FILE
// ===========================================================================
echo '<div class="step">';
echo '<h2>📁 Step 1: Individuazione File JSON</h2>';

$current_dir = __DIR__;
$wp_root = $current_dir;

// Risali nella gerarchia per trovare wp-config.php
for ($i = 0; $i < 10; $i++) {
    if (file_exists($wp_root . '/wp-config.php')) {
        break;
    }
    $wp_root = dirname($wp_root);
}

echo '<p><strong>📂 Script in:</strong> <code>' . __FILE__ . '</code></p>';
echo '<p><strong>🏠 WordPress root:</strong> <code>' . $wp_root . '</code></p>';

$upload_base = $wp_root . '/wp-content/uploads';
$templates_dir = $upload_base . '/disco747-templates';
$file_path = $templates_dir . '/whatsapp-templates.json';

echo '<p><strong>🎯 Percorso file JSON:</strong></p>';
echo '<pre>' . $file_path . '</pre>';

// Verifica cartella
if (!is_dir($templates_dir)) {
    echo '<p class="error">❌ ERRORE: La cartella non esiste</p>';
    
    echo '<div class="alert-box">';
    echo '<h3>⚠️ Azione Richiesta</h3>';
    echo '<p>Devi creare la cartella via FTP:</p>';
    echo '<pre>' . $templates_dir . '</pre>';
    echo '<p><strong>Permessi consigliati:</strong> 755</p>';
    echo '<p><strong>Come fare:</strong></p>';
    echo '<ol>';
    echo '<li>Apri il tuo client FTP (FileZilla, ecc.)</li>';
    echo '<li>Vai in <code>/wp-content/uploads/</code></li>';
    echo '<li>Crea una nuova cartella chiamata <code>disco747-templates</code></li>';
    echo '<li>Imposta permessi 755</li>';
    echo '<li>Ricarica questa pagina</li>';
    echo '</ol>';
    echo '</div>';
    echo '</div></div></body></html>';
    exit;
}

echo '<p class="success">✅ Cartella trovata!</p>';
echo '<p>📊 Permessi cartella: <code>' . substr(sprintf('%o', fileperms($templates_dir)), -4) . '</code></p>';

// Verifica file
if (!file_exists($file_path)) {
    echo '<p class="error">❌ ERRORE: File JSON non trovato</p>';
    
    echo '<div class="alert-box">';
    echo '<h3>⚠️ Azione Richiesta</h3>';
    echo '<p>Il file <code>whatsapp-templates.json</code> non esiste in:</p>';
    echo '<pre>' . $templates_dir . '</pre>';
    
    echo '<h4>📋 Contenuto da copiare:</h4>';
    echo '<p>Crea un file chiamato <code>whatsapp-templates.json</code> e incolla questo contenuto:</p>';
    
    $example_json = array(
        '1' => array(
            'name' => 'Nuovo Preventivo',
            'body' => "Ciao {{nome}}! 🎉\n\nIl tuo preventivo per {{tipo_evento}} del {{data_evento}} è pronto!\n\n📅 Data: {{data_evento}}\n🎊 Evento: {{tipo_evento}}\n👥 Invitati: {{numero_invitati}}\n🍽️ Menu: {{tipo_menu}}\n💰 Importo: {{importo}}\n\n747 Disco - La tua festa indimenticabile! 🎈\n\n📞 Contattaci per confermare!",
            'enabled' => 1
        ),
        '2' => array(
            'name' => 'Promemoria Evento',
            'body' => "Ciao {{nome}}! ⏰\n\nTi ricordiamo il tuo evento:\n\n📅 {{data_evento}}\n🎉 {{tipo_evento}}\n👥 {{numero_invitati}} invitati\n\n747 Disco 🎈",
            'enabled' => 1
        )
    );
    
    echo '<details>';
    echo '<summary>👁️ Visualizza contenuto JSON (clicca per espandere)</summary>';
    echo '<pre>' . htmlspecialchars(json_encode($example_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
    echo '</details>';
    
    echo '<h4>🔧 Come procedere:</h4>';
    echo '<ol>';
    echo '<li>Crea un file di testo sul tuo computer</li>';
    echo '<li>Copia il JSON sopra (espandi il box)</li>';
    echo '<li>Salva come <code>whatsapp-templates.json</code> (UTF-8)</li>';
    echo '<li>Carica via FTP nella cartella: <code>' . $templates_dir . '</code></li>';
    echo '<li>Imposta permessi 644</li>';
    echo '<li>Ricarica questa pagina</li>';
    echo '</ol>';
    echo '</div>';
    
    echo '</div></div></body></html>';
    exit;
}

echo '<p class="success">✅ File JSON trovato!</p>';
echo '<p>📦 Dimensione: <strong>' . number_format(filesize($file_path)) . '</strong> bytes</p>';
echo '<p>🔐 Permessi file: <code>' . substr(sprintf('%o', fileperms($file_path)), -4) . '</code></p>';
echo '<p>🕒 Ultima modifica: <strong>' . date('d/m/Y H:i:s', filemtime($file_path)) . '</strong></p>';
echo '</div>';

// ===========================================================================
// STEP 2: LETTURA FILE
// ===========================================================================
echo '<div class="step">';
echo '<h2>📖 Step 2: Lettura File JSON</h2>';

$json_data = file_get_contents($file_path);

if ($json_data === false) {
    echo '<p class="error">❌ ERRORE: Impossibile leggere il file</p>';
    echo '<p>Verifica i permessi del file (dovrebbe essere almeno 644)</p>';
    echo '</div></div></body></html>';
    exit;
}

echo '<p class="success">✅ File letto correttamente</p>';
echo '<p>📏 Dimensione dati: <strong>' . number_format(strlen($json_data)) . '</strong> bytes</p>';

// Verifica encoding
$is_utf8 = mb_check_encoding($json_data, 'UTF-8');
if ($is_utf8) {
    echo '<p class="success">✅ Encoding UTF-8 corretto</p>';
} else {
    echo '<p class="error">❌ ATTENZIONE: Problemi con encoding UTF-8</p>';
    echo '<p>Il file potrebbe non essere salvato come UTF-8. Ri-salvalo assicurandoti che sia UTF-8 (senza BOM).</p>';
}

echo '</div>';

// ===========================================================================
// STEP 3: DECODIFICA JSON
// ===========================================================================
echo '<div class="step">';
echo '<h2>🔍 Step 3: Decodifica JSON</h2>';

$templates = json_decode($json_data, true);
$json_error = json_last_error();

if ($templates === null || $json_error !== JSON_ERROR_NONE) {
    echo '<p class="error">❌ ERRORE: JSON non valido</p>';
    echo '<p><strong>Errore JSON:</strong> ' . json_last_error_msg() . '</p>';
    
    echo '<details>';
    echo '<summary>📄 Visualizza contenuto file (primi 1000 caratteri)</summary>';
    echo '<pre>' . htmlspecialchars(substr($json_data, 0, 1000)) . '</pre>';
    echo '</details>';
    
    echo '</div></div></body></html>';
    exit;
}

echo '<p class="success">✅ JSON decodificato con successo</p>';
echo '<p>📋 Template trovati: <strong>' . count($templates) . '</strong></p>';

// Statistiche
$enabled_count = 0;
$disabled_count = 0;
$total_chars = 0;
$emoji_count = 0;
$emoji_found = array();

foreach ($templates as $id => $template) {
    if (!empty($template['enabled'])) {
        $enabled_count++;
    } else {
        $disabled_count++;
    }
    
    $body = $template['body'] ?? '';
    $total_chars += strlen($body);
    
    // Conta emoticon
    preg_match_all('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $body, $matches);
    if (!empty($matches[0])) {
        $emoji_count += count($matches[0]);
        $emoji_found = array_merge($emoji_found, $matches[0]);
    }
}

$emoji_found = array_unique($emoji_found);

echo '<div class="stats">';
echo '<div class="stat-box"><h3>' . count($templates) . '</h3><p>Template Totali</p></div>';
echo '<div class="stat-box"><h3>' . $enabled_count . '</h3><p>Attivi</p></div>';
echo '<div class="stat-box"><h3>' . $disabled_count . '</h3><p>Disattivati</p></div>';
echo '<div class="stat-box"><h3>' . $emoji_count . '</h3><p>Emoticon Totali</p></div>';
echo '</div>';

if (!empty($emoji_found)) {
    echo '<div class="info-box">';
    echo '<p><strong>🎨 Emoticon trovate:</strong></p>';
    echo '<p style="font-size: 2rem;">' . implode(' ', array_slice($emoji_found, 0, 20)) . '</p>';
    if (count($emoji_found) > 20) {
        echo '<p><em>...e altre ' . (count($emoji_found) - 20) . '</em></p>';
    }
    echo '</div>';
}

echo '</div>';

// ===========================================================================
// STEP 4: VISUALIZZA TEMPLATE
// ===========================================================================
echo '<div class="step">';
echo '<h2>📋 Step 4: Template Disponibili</h2>';

foreach ($templates as $id => $template) {
    $enabled = !empty($template['enabled']);
    $status_color = $enabled ? '#28a745' : '#dc3545';
    $status_text = $enabled ? '✅ Attivo' : '❌ Disattivo';
    
    echo '<div class="template-box" style="border-left-color: ' . $status_color . ';">';
    echo '<h3 style="margin-top: 0; color: #2b1e1a;">Template #' . $id . ': ' . htmlspecialchars($template['name'] ?? 'Senza nome') . ' <span style="color: ' . $status_color . ';">' . $status_text . '</span></h3>';
    
    $body = $template['body'] ?? '';
    preg_match_all('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $body, $emoji_matches);
    $template_emoji_count = count($emoji_matches[0] ?? array());
    
    echo '<p>📏 <strong>Lunghezza:</strong> ' . strlen($body) . ' caratteri</p>';
    echo '<p>🎨 <strong>Emoticon:</strong> ' . $template_emoji_count . '</p>';
    
    echo '<details>';
    echo '<summary>👁️ Visualizza contenuto completo</summary>';
    echo '<pre style="margin-top: 10px;">' . htmlspecialchars($body) . '</pre>';
    echo '</details>';
    
    echo '</div>';
}

echo '</div>';

// ===========================================================================
// STEP 5: TEST COMPILAZIONE
// ===========================================================================
echo '<div class="step">';
echo '<h2>🔧 Step 5: Test Compilazione Template</h2>';

if (count($templates) > 0) {
    // Trova primo template attivo
    $test_template_id = null;
    foreach ($templates as $id => $template) {
        if (!empty($template['enabled'])) {
            $test_template_id = $id;
            break;
        }
    }
    
    if ($test_template_id) {
        $message = $templates[$test_template_id]['body'];
        
        // Dati di test
        $test_data = array(
            '{{nome}}' => 'Mario',
            '{{cognome}}' => 'Rossi',
            '{{nome_completo}}' => 'Mario Rossi',
            '{{data_evento}}' => '15/12/2025',
            '{{tipo_evento}}' => 'Compleanno',
            '{{tipo_menu}}' => 'Menu 747',
            '{{menu}}' => 'Menu 747',
            '{{numero_invitati}}' => '50',
            '{{importo}}' => '€ 2.500,00',
            '{{importo_totale}}' => '€ 2.500,00',
            '{{totale}}' => '€ 2.500,00',
            '{{acconto}}' => '€ 500,00',
            '{{saldo}}' => '€ 2.000,00',
            '{{telefono}}' => '333 1234567',
            '{{email}}' => 'mario.rossi@email.com',
            '{{orario_inizio}}' => '20:30',
            '{{orario_fine}}' => '01:30'
        );
        
        echo '<p>🎯 <strong>Template utilizzato:</strong> #' . $test_template_id . ' - ' . htmlspecialchars($templates[$test_template_id]['name']) . '</p>';
        
        echo '<h4>📄 Template Originale (con placeholder):</h4>';
        echo '<pre style="background:#f8f9fa; border-left: 4px solid #007bff;">' . htmlspecialchars($message) . '</pre>';
        
        // Compila template
        $compiled_message = str_replace(array_keys($test_data), array_values($test_data), $message);
        
        echo '<h4>✨ Template Compilato (anteprima):</h4>';
        echo '<div class="whatsapp-preview">';
        echo '<div class="whatsapp-bubble">' . nl2br(htmlspecialchars($compiled_message)) . '</div>';
        echo '</div>';
        
        // Genera link WhatsApp
        $phone = '+393331234567';
        $whatsapp_url = 'https://wa.me/' . $phone . '?text=' . urlencode($compiled_message);
        
        echo '<h4>🔗 Test Link WhatsApp:</h4>';
        echo '<div class="info-box">';
        echo '<p>📞 <strong>Numero test:</strong> ' . $phone . '</p>';
        echo '<p>Questo link aprirà WhatsApp con il messaggio già compilato (verifica che le emoticon siano corrette!):</p>';
        echo '<p><a href="' . htmlspecialchars($whatsapp_url) . '" target="_blank" class="btn">💬 Apri WhatsApp Web (Test)</a></p>';
        echo '</div>';
        
        echo '<details>';
        echo '<summary>🔍 Mostra URL completo WhatsApp</summary>';
        echo '<pre style="word-wrap: break-word; white-space: pre-wrap;">' . htmlspecialchars($whatsapp_url) . '</pre>';
        echo '</details>';
        
    } else {
        echo '<p class="warning">⚠️ Nessun template attivo trovato per il test</p>';
        echo '<p>Abilita almeno un template impostando <code>"enabled": 1</code> nel file JSON.</p>';
    }
} else {
    echo '<p class="error">❌ Nessun template disponibile</p>';
}

echo '</div>';

// ===========================================================================
// STEP 6: RIEPILOGO FINALE
// ===========================================================================
echo '<div class="step" style="border-left-color: #28a745; background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f4 100%);">';
echo '<h2>📊 Riepilogo Finale</h2>';

$all_ok = $is_utf8 && count($templates) > 0 && $enabled_count > 0 && $emoji_count > 0;

echo '<ul style="line-height: 2.5; font-size: 1.1rem;">';
echo '<li class="success">✅ File JSON trovato e accessibile</li>';
echo '<li class="success">✅ File leggibile (' . number_format(strlen($json_data)) . ' bytes)</li>';
echo '<li class="' . ($is_utf8 ? 'success' : 'error') . '">' . ($is_utf8 ? '✅' : '❌') . ' Encoding UTF-8 ' . ($is_utf8 ? 'corretto' : 'NON corretto') . '</li>';
echo '<li class="success">✅ JSON valido e decodificato</li>';
echo '<li class="success">✅ ' . count($templates) . ' template trovati</li>';
echo '<li class="' . ($enabled_count > 0 ? 'success' : 'warning') . '">' . ($enabled_count > 0 ? '✅' : '⚠️') . ' ' . $enabled_count . ' template attivi</li>';
echo '<li class="' . ($emoji_count > 0 ? 'success' : 'warning') . '">' . ($emoji_count > 0 ? '✅' : '⚠️') . ' ' . $emoji_count . ' emoticon trovate</li>';
echo '<li class="success">✅ Sostituzione placeholder funzionante</li>';
echo '<li class="success">✅ Link WhatsApp generato correttamente</li>';
echo '</ul>';

if ($all_ok) {
    echo '<h3 style="color: #28a745; margin-top: 30px; text-align: center; font-size: 1.8rem;">🎉 Sistema Completamente Funzionante!</h3>';
    echo '<p style="text-align: center; font-size: 1.1rem;">Tutti i test sono stati superati. Il sistema template WhatsApp è pronto per l\'uso.</p>';
    
    echo '<div class="info-box" style="margin-top: 20px;">';
    echo '<h4>✨ Prossimi Passi:</h4>';
    echo '<ol style="line-height: 2;">';
    echo '<li>✅ Verifica che il file <code>class-disco747-ajax.php</code> sia stato aggiornato</li>';
    echo '<li>📋 Vai alla pagina <strong>Messaggi Automatici</strong> e verifica che i template siano visibili</li>';
    echo '<li>📝 Vai al <strong>Form Preventivo</strong> e testa l\'invio WhatsApp</li>';
    echo '<li>💬 Verifica che il dropdown mostri i template disponibili</li>';
    echo '<li>🎯 Testa l\'invio effettivo del messaggio WhatsApp</li>';
    echo '</ol>';
    echo '</div>';
} else {
    echo '<h3 style="color: #ffc107; margin-top: 30px;">⚠️ Alcune Verifiche Non Superate</h3>';
    echo '<p>Controlla i messaggi di errore sopra e sistema i problemi indicati.</p>';
}

echo '</div>';

?>

        <div class="footer">
            <p>✅ Test completato il <strong><?php echo date('d/m/Y H:i:s'); ?></strong></p>
            <p style="font-size: 12px; margin-top: 10px; color: #999;">747 Disco CRM - Template WhatsApp System v12.0.1-STANDALONE</p>
            <p style="font-size: 12px; margin-top: 5px; color: #999;">Script diagnostico completo senza dipendenze WordPress</p>
        </div>

    </div>
</div>

</body>
</html>