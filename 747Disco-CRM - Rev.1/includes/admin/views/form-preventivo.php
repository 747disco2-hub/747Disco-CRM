<?php
/**
 * Form Preventivo con 3 Pulsanti Enhanced - 747 Disco CRM
 * Versione 12.1.0 - AGGIUNTO: Pulsanti PDF, Email, WhatsApp
 * 
 * MODIFICHE:
 * - Aggiunti 3 pulsanti post-creazione preventivo
 * - Generazione PDF on-demand
 * - Invio Email con template selezionabile
 * - Invio WhatsApp con template selezionabile
 * - Grafica migliorata ma rispettando lo stile esistente
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// MODALITÀ MODIFICA: Carica dati esistenti
// ============================================================================
$is_edit_mode = false;
$edit_data = null;
$edit_id = 0;

// Priorità 1: Variabile $preventivo passata dalla classe admin
if (isset($preventivo) && !empty($preventivo)) {
    $is_edit_mode = true;
    $edit_data = is_array($preventivo) ? $preventivo : (array) $preventivo;
    $edit_id = intval($edit_data['id'] ?? 0);
}
// Priorità 2: Parametro GET 'id' (dalla view-preventivi)
elseif (!empty($_GET['id'])) {
    $is_edit_mode = true;
    $edit_id = intval($_GET['id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'disco747_preventivi';
    $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $edit_id), ARRAY_A);
}
// Priorità 3: Parametro GET 'edit_id' (compatibilità)
elseif (!empty($_GET['edit_id'])) {
    $is_edit_mode = true;
    $edit_id = intval($_GET['edit_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'disco747_preventivi';
    $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $edit_id), ARRAY_A);
}

// Debug e normalizzazione dati
if ($is_edit_mode) {
    error_log('[747Disco-Form] Edit mode - ID: ' . $edit_id);
    error_log('[747Disco-Form] Edit data loaded: ' . ($edit_data ? 'SI' : 'NO'));
    
    // ✅ Preimposta stato se arriva da cambio rapido dashboard
    if (!empty($_GET['quick_stato']) && $edit_data) {
        $edit_data['stato'] = sanitize_key($_GET['quick_stato']);
        error_log('[747Disco-Form] Stato preimpostato da quick_stato: ' . $edit_data['stato']);
    }
    
    if ($edit_data) {
        error_log('[747Disco-Form] Preventivo ID: ' . ($edit_data['preventivo_id'] ?? 'VUOTO'));
        error_log('[747Disco-Form] Nome cliente: ' . ($edit_data['nome_cliente'] ?? 'VUOTO'));
        error_log('[747Disco-Form] Email: ' . ($edit_data['email'] ?? 'VUOTO'));
        
        // Normalizza campi per compatibilità
        $edit_data['email'] = $edit_data['email'] ?? $edit_data['mail'] ?? '';
        $edit_data['telefono'] = $edit_data['telefono'] ?? $edit_data['cellulare'] ?? '';
        // ✅ FIX: NON sovrascrivere importo_totale con importo_preventivo (che ha già gli extra inclusi!)
        // importo_totale deve contenere SOLO il prezzo base del menu, senza extra
        if (!isset($edit_data['importo_totale']) || $edit_data['importo_totale'] == 0) {
            // Se importo_totale è vuoto, calcola il base sottraendo gli extra da importo_preventivo
            $importo_preventivo = floatval($edit_data['importo_preventivo'] ?? 0);
            $extra_totale = floatval($edit_data['extra1_importo'] ?? 0) + 
                          floatval($edit_data['extra2_importo'] ?? 0) + 
                          floatval($edit_data['extra3_importo'] ?? 0);
            $edit_data['importo_totale'] = $importo_preventivo - $extra_totale;
        }
    } else {
        error_log('[747Disco-Form] ERRORE: Preventivo non trovato con ID: ' . $edit_id);
    }
}

// Helper function per ottenere valore del campo
function get_field_value($field_name, $default = '', $edit_data = null) {
    if ($edit_data && isset($edit_data[$field_name])) {
        return esc_attr($edit_data[$field_name]);
    }
    return esc_attr($default);
}

// Valori predefiniti
$default_values = array(
    'tipo_menu' => 'Menu 7',
    'numero_invitati' => 50,
    'orario_inizio' => '20:30',
    'orario_fine' => '01:30',
    'omaggio1' => 'Crepes alla Nutella',
    'omaggio2' => 'Servizio Fotografico',
    'omaggio3' => '',
    'importo_base' => 0,
    'extra1_importo' => 0,
    'extra2_importo' => 0,
    'extra3_importo' => 0,
    'acconto' => 0
);

$sconti_menu = array(
    'Menu 7' => 400,
    'Menu 7-4' => 500,
    'Menu 7-4-7' => 600
);

$page_title = $is_edit_mode ? 'Modifica Preventivo #' . $edit_id : 'Nuovo Preventivo';
$submit_text = $is_edit_mode ? '💾 Aggiorna Preventivo' : '💾 Salva Preventivo';

?>

<div class="wrap disco747-form-preventivo" style="max-width: 1200px; margin: 30px auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    
    <!-- Header -->
    <div style="background: linear-gradient(135deg, #2b1e1a 0%, #1a1310 100%); padding: 30px; border-radius: 15px 15px 0 0; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="color: #c28a4d; margin: 0; font-size: 2.2rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">
                    🎉 <?php echo esc_html($page_title); ?>
                </h1>
                <p style="color: #a0a0a0; margin: 10px 0 0 0; font-size: 1rem;">
                    Compila tutti i campi per generare il preventivo personalizzato
                </p>
            </div>
            <?php if ($is_edit_mode): ?>
            <div>
                <a href="<?php echo admin_url('admin.php?page=disco747-view-preventivi'); ?>" 
                   style="background: rgba(255,255,255,0.1); color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; transition: all 0.3s ease; border: 2px solid rgba(255,255,255,0.2);"
                   onmouseover="this.style.background='rgba(255,255,255,0.2)'"
                   onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    ← Torna alla Lista
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form principale -->
    <form id="disco747-form-preventivo" method="post" autocomplete="off" style="background: white; border-radius: 0 0 15px 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden;">
        
        <?php wp_nonce_field('disco747_preventivo', 'disco747_preventivo_nonce'); ?>
        <input type="hidden" name="is_edit_mode" value="<?php echo $is_edit_mode ? '1' : '0'; ?>">
        <?php if ($is_edit_mode): ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
        <?php endif; ?>

        <!-- SEZIONE 1: Dati Referente -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                👤 Dati Referente
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        Nome * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="text" name="nome_referente" required
                           value="<?php echo get_field_value('nome_referente', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Mario">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        Cognome * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="text" name="cognome_referente" required
                           value="<?php echo get_field_value('cognome_referente', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Rossi">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        📱 Cellulare * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="tel" name="cellulare" required
                           value="<?php echo get_field_value('telefono', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: 333 1234567">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        📧 Email * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="email" name="mail" required
                           value="<?php echo get_field_value('email', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: mario.rossi@email.com">
                </div>
                
            </div>
        </div>

        <!-- SEZIONE 2: Dettagli Evento -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                🎊 Dettagli Evento
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        📅 Data Evento * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="date" name="data_evento" required
                           value="<?php echo get_field_value('data_evento', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎉 Tipo Evento * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="text" name="tipo_evento" required
                           value="<?php echo get_field_value('tipo_evento', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Festa 18 anni, Compleanno, Matrimonio">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🍽ï¸ Tipo Menu * <span style="color: #dc3545;">●</span>
                    </label>
                    <select name="tipo_menu" id="tipo_menu" required autocomplete="off"
                            style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                        <option value="Menu 7" <?php echo get_field_value('tipo_menu', 'Menu 7', $edit_data) == 'Menu 7' ? 'selected' : ''; ?>>Menu 7</option>
                        <option value="Menu 7-4" <?php echo get_field_value('tipo_menu', '', $edit_data) == 'Menu 7-4' ? 'selected' : ''; ?>>Menu 7-4</option>
                        <option value="Menu 7-4-7" <?php echo get_field_value('tipo_menu', '', $edit_data) == 'Menu 7-4-7' ? 'selected' : ''; ?>>Menu 7-4-7</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        👥 Numero Invitati * <span style="color: #dc3545;">●</span>
                    </label>
                    <input type="number" name="numero_invitati" min="1" required
                           value="<?php echo get_field_value('numero_invitati', '50', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: 50">
                </div>
                
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🕐 Orario Inizio *
                    </label>
                    <input type="time" name="orario_inizio"
                           value="<?php echo get_field_value('orario_inizio', '20:30', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🕐 Orario Fine *
                    </label>
                    <input type="time" name="orario_fine"
                           value="<?php echo get_field_value('orario_fine', '01:30', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                </div>
                
            </div>
        </div>

        <!-- SEZIONE 3: Omaggi Inclusi -->
        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                🎁 Omaggi Inclusi nel Pacchetto
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎁 Omaggio 1
                    </label>
                    <input type="text" name="omaggio1"
                           value="<?php echo get_field_value('omaggio1', $default_values['omaggio1'], $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Crepes alla Nutella">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎁 Omaggio 2
                    </label>
                    <input type="text" name="omaggio2"
                           value="<?php echo get_field_value('omaggio2', $default_values['omaggio2'], $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Servizio Fotografico">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        🎁 Omaggio 3
                    </label>
                    <input type="text" name="omaggio3"
                           value="<?php echo get_field_value('omaggio3', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Omaggio aggiuntivo (opzionale)">
                </div>
                
            </div>
        </div>

        <!-- SEZIONE 4: Extra a Pagamento -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                💰 Extra a Pagamento
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            
            <!-- Extra 1 -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        ➕ Extra 1 - Descrizione
                    </label>
                    <input type="text" name="extra1"
                           value="<?php echo get_field_value('extra1', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Decorazioni Extra">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        💵 Importo Extra 1 (€)
                    </label>
                    <input type="number" name="extra1_importo" id="extra1_importo" min="0" step="0.01"
                           value="<?php echo get_field_value('extra1_importo', '0', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="0.00">
                </div>
            </div>
            
            <!-- Extra 2 -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        ➕ Extra 2 - Descrizione
                    </label>
                    <input type="text" name="extra2"
                           value="<?php echo get_field_value('extra2', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Servizio Video">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        💵 Importo Extra 2 (€)
                    </label>
                    <input type="number" name="extra2_importo" id="extra2_importo" min="0" step="0.01"
                           value="<?php echo get_field_value('extra2_importo', '0', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="0.00">
                </div>
            </div>
            
            <!-- Extra 3 -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        ➕ Extra 3 - Descrizione
                    </label>
                    <input type="text" name="extra3"
                           value="<?php echo get_field_value('extra3', '', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="Es: Torta Personalizzata">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        💵 Importo Extra 3 (€)
                    </label>
                    <input type="number" name="extra3_importo" id="extra3_importo" min="0" step="0.01"
                           value="<?php echo get_field_value('extra3_importo', '0', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="0.00">
                </div>
            </div>
            
        </div>

        <!-- SEZIONE 5: Riepilogo Economico -->
        <div style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                💰 Riepilogo Economico
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        💵 Importo Base Preventivo (€) *
                    </label>
                    <input type="number" name="importo_preventivo" id="importo_preventivo" min="0" step="0.01" required
                           value="<?php echo get_field_value('importo_totale', '0', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="0.00">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        💳 Acconto Versato (€)
                    </label>
                    <input type="number" name="acconto" id="acconto" min="0" step="0.01"
                           value="<?php echo get_field_value('acconto', '0', $edit_data); ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;"
                           placeholder="0.00">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                        📊 Stato Preventivo
                    </label>
                    <select name="stato" id="stato"
                            style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease;">
                        <option value="attivo" <?php echo get_field_value('stato', 'attivo', $edit_data) == 'attivo' ? 'selected' : ''; ?>>
                            ✅ Attivo
                        </option>
                        <option value="confermato" <?php echo get_field_value('stato', '', $edit_data) == 'confermato' ? 'selected' : ''; ?>>
                            💰 Confermato (con acconto)
                        </option>
                        <option value="annullato" <?php echo get_field_value('stato', '', $edit_data) == 'annullato' ? 'selected' : ''; ?>>
                            ❌ Annullato
                        </option>
                    </select>
                    <p style="font-size: 12px; color: #6c757d; margin-top: 5px;">
                        ℹ️ <strong>Annullato</strong> aggiunge "NO" al nome file Excel<br>
                        💡 <strong>Confermato</strong> o acconto > 0 aggiunge "CONF"
                    </p>
                </div>
                
            </div>
            
            <!-- Display Calcoli Automatici -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    
                    <div style="text-align: center;">
                        <div style="font-size: 0.9rem; color: #6c757d; margin-bottom: 5px;">Sconto Menu</div>
                        <div id="sconto_valore" style="font-size: 1.5rem; font-weight: 700; color: #28a745;">€ 0</div>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="font-size: 0.9rem; color: #6c757d; margin-bottom: 5px;">Totale Lordo</div>
                        <div id="totale_lordo_display" style="font-size: 1.5rem; font-weight: 700; color: #c28a4d;">€ 0</div>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="font-size: 0.9rem; color: #6c757d; margin-bottom: 5px;">Saldo Residuo</div>
                        <div id="saldo_display" style="font-size: 1.5rem; font-weight: 700; color: #dc3545;">€ 0</div>
                    </div>
                    
                </div>
            </div>
        </div>

        <!-- SEZIONE 6: Note Aggiuntive -->
        <div style="background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                📝 Note Aggiuntive
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                    📋 Note e Richieste Speciali
                </label>
                <textarea name="note_aggiuntive" rows="4"
                          style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease; resize: vertical;"
                          placeholder="Inserisci note aggiuntive, richieste speciali o dettagli importanti per l'evento..."><?php echo get_field_value('note_aggiuntive', '', $edit_data); ?></textarea>
            </div>
        </div>

        <!-- SEZIONE 7: Note Interne -->
        <div style="background: linear-gradient(135deg, #495057 0%, #343a40 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                🔒 Note Interne
            </h2>
        </div>
        
        <div style="padding: 30px; border-bottom: 1px solid #e9ecef;">
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2b1e1a;">
                    🔒 Note Interne (Visibili solo al team)
                </label>
                <textarea name="note_interne" rows="3"
                          style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease; resize: vertical; background: #f8f9fa;"
                          placeholder="Note per uso interno del team (non visibili al cliente)..."><?php echo get_field_value('note_interne', '', $edit_data); ?></textarea>
            </div>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #ffc107;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #856404;">âš ï¸</span>
                    <small style="color: #856404; font-weight: 500;">
                        Queste note sono riservate al team e non verranno mostrate al cliente nei preventivi o nelle comunicazioni.
                    </small>
                </div>
            </div>
        </div>

        <!-- PULSANTI AZIONE PRINCIPALI -->
        <div style="background: #f8f9fa; padding: 30px; text-align: center; border-bottom: 3px solid #c28a4d;">
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                
                <!-- Pulsante Salva -->
                <button type="submit" name="action" value="save_preventivo"
                        style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; font-size: 16px; cursor: pointer; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); transition: all 0.3s ease;">
                    <?php echo $submit_text; ?> 💾
                </button>
                
                <!-- Pulsante Annulla -->
                
                <!-- Pulsante Torna alla Dashboard -->
                <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm')); ?>"
                   style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 15px 30px; border: none; border-radius: 25px; font-weight: 600; font-size: 16px; text-decoration: none; display: inline-block; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3); transition: all 0.3s ease;">
                    🏠 Torna alla Dashboard
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm')); ?>" 
                   style="background: rgba(108, 117, 125, 0.1); color: #6c757d; padding: 15px 30px; border: 2px solid #6c757d; border-radius: 25px; font-weight: 600; font-size: 16px; text-decoration: none; display: inline-block; transition: all 0.3s ease;">
                    â† Annulla
                </a>
                
            </div>
        </div>
        
    </form>
    
    <?php if ($is_edit_mode && $edit_id > 0): ?>
    <!-- ============================================================================ -->
    <!-- SEZIONE STORICO MODIFICHE -->
    <!-- ============================================================================ -->
    <div style="background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-top: 30px; overflow: hidden;">
        
        <div style="background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.4rem;">
                📋 Storico Modifiche
            </h2>
        </div>
        
        <div style="padding: 30px;">
            <?php
            // Carica storico modifiche
            $disco747_crm = disco747_crm();
            if ($disco747_crm && $disco747_crm->get_database()) {
                $log_entries = $disco747_crm->get_database()->get_preventivo_log($edit_id);
                
                if (!empty($log_entries)):
            ?>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($log_entries as $entry): 
                    $user_info = get_userdata($entry['user_id']);
                    $user_display = $user_info ? $user_info->display_name : $entry['user_name'];
                    
                    $action_icons = array(
                        'create' => '✨',
                        'update' => '✏️',
                        'field_update' => '📝',
                        'delete' => '🗑️'
                    );
                    $icon = $action_icons[$entry['action_type']] ?? '📌';
                    
                    $action_labels = array(
                        'create' => 'Creazione preventivo',
                        'update' => 'Aggiornamento preventivo',
                        'field_update' => 'Modifica campo',
                        'delete' => 'Eliminazione'
                    );
                    $action_label = $action_labels[$entry['action_type']] ?? $entry['action_type'];
                ?>
                <div style="background: #f8f9fa; border-left: 4px solid #6c757d; padding: 15px; margin-bottom: 10px; border-radius: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 15px; flex-wrap: wrap;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #2b1e1a; margin-bottom: 5px;">
                                <?php echo $icon; ?> <?php echo esc_html($action_label); ?>
                            </div>
                            <?php if ($entry['field_changed']): ?>
                            <div style="font-size: 14px; color: #6c757d; margin-top: 8px;">
                                <strong>Campo:</strong> <?php echo esc_html($entry['field_changed']); ?><br>
                                <?php if ($entry['old_value']): ?>
                                <span style="color: #dc3545;">Da:</span> <code><?php echo esc_html(substr($entry['old_value'], 0, 100)); ?></code><br>
                                <?php endif; ?>
                                <span style="color: #28a745;">A:</span> <code><?php echo esc_html(substr($entry['new_value'], 0, 100)); ?></code>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: right; min-width: 200px;">
                            <div style="font-weight: 600; color: #495057; font-size: 14px;">
                                👤 <?php echo esc_html($user_display); ?>
                            </div>
                            <div style="font-size: 12px; color: #6c757d; margin-top: 3px;">
                                🕐 <?php echo date('d/m/Y H:i', strtotime($entry['created_at'])); ?>
                            </div>
                            <?php if ($entry['ip_address']): ?>
                            <div style="font-size: 11px; color: #adb5bd; margin-top: 2px;">
                                🌐 <?php echo esc_html($entry['ip_address']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #6c757d;">
                <div style="font-size: 48px; margin-bottom: 15px;">📋</div>
                <p style="margin: 0; font-size: 16px;">Nessuna modifica registrata per questo preventivo</p>
            </div>
            <?php 
                endif;
            } else {
                echo '<p style="color: #dc3545;">⚠️ Sistema di logging non disponibile</p>';
            }
            ?>
        </div>
        
    </div>
    <?php endif; ?>
    
    <!-- ============================================================================ -->
    <!-- SEZIONE NUOVA: PULSANTI POST-CREAZIONE (PDF, EMAIL, WHATSAPP) -->
    <!-- Visibile SOLO dopo che il preventivo è stato salvato -->
    <!-- ============================================================================ -->
    
    <div id="post-creation-actions" style="display: none; background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); margin-top: 30px; overflow: hidden;">
        
        <!-- Header Sezione -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67339 100%); color: white; padding: 25px; text-align: center;">
            <h2 style="margin: 0; font-size: 1.6rem; font-weight: 700;">
                ✅ Preventivo Creato con Successo!
            </h2>
            <p style="margin: 10px 0 0 0; font-size: 1rem; opacity: 0.9;">
                Ora puoi generare il PDF, inviare l'email o il messaggio WhatsApp al cliente
            </p>
        </div>
        
        <!-- Container Pulsanti -->
        <div style="padding: 40px; background: #f8f9fa;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; max-width: 1000px; margin: 0 auto;">
                
                <!-- PULSANTE 1: Genera PDF -->
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s ease;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">📄</div>
                    <h3 style="margin: 0 0 10px 0; color: #2b1e1a; font-size: 1.2rem;">Genera PDF</h3>
                    <p style="color: #6c757d; font-size: 0.9rem; margin-bottom: 20px;">
                        Crea il file PDF del preventivo e scaricalo immediatamente
                    </p>
                    <button type="button" id="btn-generate-pdf"
                            style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; font-size: 15px; cursor: pointer; box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3); transition: all 0.3s ease; width: 100%;">
                        📄 Genera e Scarica PDF
                    </button>
                </div>
                
                <!-- PULSANTE 2: Invia Email -->
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s ease;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">📧</div>
                    <h3 style="margin: 0 0 10px 0; color: #2b1e1a; font-size: 1.2rem;">Invia Email</h3>
                    <p style="color: #6c757d; font-size: 0.9rem; margin-bottom: 20px;">
                        Invia l'email al cliente con il PDF allegato
                    </p>
                    <button type="button" id="btn-send-email"
                            style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; font-size: 15px; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3); transition: all 0.3s ease; width: 100%;">
                        📧 Invia Email
                    </button>
                </div>
                
                <!-- PULSANTE 3: Invia WhatsApp -->
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s ease;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">💬</div>
                    <h3 style="margin: 0 0 10px 0; color: #2b1e1a; font-size: 1.2rem;">Invia WhatsApp</h3>
                    <p style="color: #6c757d; font-size: 0.9rem; margin-bottom: 20px;">
                        Apri WhatsApp con il messaggio precompilato
                    </p>
                    <button type="button" id="btn-send-whatsapp"
                            style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; font-size: 15px; cursor: pointer; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3); transition: all 0.3s ease; width: 100%;">
                        💬 Invia WhatsApp
                    </button>
                </div>
                
            </div>
        </div>
        
    </div>
    
</div>

<!-- ============================================================================ -->
<!-- MODAL: Selezione Template Email -->
<!-- ============================================================================ -->
<div id="modal-email-template" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 15px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        
        <div style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 25px; border-radius: 15px 15px 0 0;">
            <h3 style="margin: 0; font-size: 1.4rem;">📧 Seleziona Template Email</h3>
        </div>
        
        <div style="padding: 30px;">
            
            <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #2b1e1a;">
                Scegli il template da inviare:
            </label>
            
            <select id="email-template-select" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; margin-bottom: 20px;">
                <option value="1">Template 1 - Standard</option>
                <option value="2">Template 2 - Promozionale</option>
                <option value="3">Template 3 - Formale</option>
            </select>
            
            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="email-attach-pdf" checked style="width: 18px; height: 18px; cursor: pointer;">
                    <span style="font-weight: 600; color: #2b1e1a;">Allega PDF al messaggio</span>
                </label>
            </div>
            
            <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; margin-bottom: 25px;">
                <p style="margin: 0; color: #004085; font-size: 0.9rem;">
                    ℹ️ L'email sarà inviata da <strong>eventi@747disco.it</strong> con copia a <strong>info@747disco.it</strong>
                </p>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: flex-end;">
                <button type="button" id="cancel-email-modal"
                        style="background: #6c757d; color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer;">
                    Annulla
                </button>
                <button type="button" id="confirm-send-email"
                        style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);">
                    📧 Invia Email
                </button>
            </div>
            
        </div>
        
    </div>
</div>

<!-- ============================================================================ -->
<!-- MODAL: Selezione Template WhatsApp -->
<!-- ============================================================================ -->
<div id="modal-whatsapp-template" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 15px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        
        <div style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; padding: 25px; border-radius: 15px 15px 0 0;">
            <h3 style="margin: 0; font-size: 1.4rem;">💬 Seleziona Template WhatsApp</h3>
        </div>
        
        <div style="padding: 30px;">
            
            <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #2b1e1a;">
                Scegli il template da inviare:
            </label>
            
            <select id="whatsapp-template-select" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; margin-bottom: 20px;">
                <option value="1">Template 1 - Cordiale</option>
                <option value="2">Template 2 - Promozionale</option>
                <option value="3">Template 3 - Breve</option>
            </select>
            
            <div style="background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-bottom: 25px;">
                <p style="margin: 0; color: #155724; font-size: 0.9rem;">
                    ℹ️ Verrà aperta l'app WhatsApp con il messaggio già precompilato, pronto per essere inviato al cliente
                </p>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: flex-end;">
                <button type="button" id="cancel-whatsapp-modal"
                        style="background: #6c757d; color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer;">
                    Annulla
                </button>
                <button type="button" id="confirm-send-whatsapp"
                        style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; padding: 12px 25px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);">
                    💬 Apri WhatsApp
                </button>
            </div>
            
        </div>
        
    </div>
</div>

<!-- ============================================================================ -->
<!-- JAVASCRIPT: Calcoli Automatici (ORIGINALE - NON MODIFICATO) -->
<!-- ============================================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Form Preventivo 747 Disco caricato');
    
    // Sconti menu
    const scontiMenu = {
        'Menu 7': 400,
        'Menu 7-4': 500,
        'Menu 7-4-7': 600
    };
    
    // Funzione calcolo importi
    function calcolaImporti() {
        const tipoMenu = document.getElementById('tipo_menu')?.value || 'Menu 7';
        const importoBase = parseFloat(document.getElementById('importo_preventivo')?.value || 0);
        const extra1 = parseFloat(document.getElementById('extra1_importo')?.value || 0);
        const extra2 = parseFloat(document.getElementById('extra2_importo')?.value || 0);
        const extra3 = parseFloat(document.getElementById('extra3_importo')?.value || 0);
        const acconto = parseFloat(document.getElementById('acconto')?.value || 0);
        
        const sconto = scontiMenu[tipoMenu] || 0;
        const totaleExtra = extra1 + extra2 + extra3;
        const totaleLordo = importoBase + totaleExtra;
        const saldo = totaleLordo - acconto;
        
        // Aggiorna display
        if (document.getElementById('sconto_valore')) {
            document.getElementById('sconto_valore').textContent = '€ ' + sconto.toFixed(2);
        }
        if (document.getElementById('totale_lordo_display')) {
            document.getElementById('totale_lordo_display').textContent = '€ ' + totaleLordo.toFixed(2);
        }
        if (document.getElementById('saldo_display')) {
            document.getElementById('saldo_display').textContent = '€ ' + saldo.toFixed(2);
        }
    }
    
    // Event listeners per calcolo automatico
    ['tipo_menu', 'importo_preventivo', 'extra1_importo', 'extra2_importo', 'extra3_importo', 'acconto'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', calcolaImporti);
            element.addEventListener('input', calcolaImporti);
        }
    });
    
    // Calcolo iniziale
    calcolaImporti();
});
</script>

<!-- ============================================================================ -->
<!-- JAVASCRIPT: AJAX Submit Form (ORIGINALE - NON MODIFICATO) -->
<!-- ============================================================================ -->
<script>
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🎯 [747Disco-AJAX] Form AJAX Handler caricato');
    
    // Inizializza preventivoData se siamo in edit mode
    <?php if ($is_edit_mode && $edit_data): ?>
    window.preventivoData = {
        // ID numerico del database (campo 'id')
        id: <?php echo intval($edit_id); ?>,
        db_id: <?php echo intval($edit_id); ?>,
        // ID preventivo stringa (campo 'preventivo_id' - es: #001)
        preventivo_id: '<?php echo esc_js($edit_data['preventivo_id'] ?? intval($edit_id)); ?>',
        
        // Dati cliente
        nome_referente: '<?php echo esc_js($edit_data['nome_referente'] ?? $edit_data['nome_cliente'] ?? ''); ?>',
        cognome_referente: '<?php echo esc_js($edit_data['cognome_referente'] ?? ''); ?>',
        nome_cliente: '<?php echo esc_js(($edit_data['nome_referente'] ?? '') . ' ' . ($edit_data['cognome_referente'] ?? '')); ?>',
        email: '<?php echo esc_js($edit_data['email'] ?? $edit_data['mail'] ?? ''); ?>',
        telefono: '<?php echo esc_js($edit_data['telefono'] ?? $edit_data['cellulare'] ?? ''); ?>',
        
        // Dati evento
        data_evento: '<?php echo esc_js($edit_data['data_evento'] ?? ''); ?>',
        tipo_evento: '<?php echo esc_js($edit_data['tipo_evento'] ?? ''); ?>',
        tipo_menu: '<?php echo esc_js($edit_data['tipo_menu'] ?? ''); ?>',
        numero_invitati: <?php echo intval($edit_data['numero_invitati'] ?? 0); ?>,
        
        // Importi - ✅ FIX: Usa sempre importo_totale (base menu), mai importo_preventivo (che ha extra inclusi)
        importo_totale: <?php echo floatval($edit_data['importo_totale'] ?? 0); ?>,
        acconto: <?php echo floatval($edit_data['acconto'] ?? 0); ?>
    };
    console.log('📝 Edit mode - preventivoData inizializzato:', window.preventivoData);
    console.log('📝 Edit mode - ID numerico:', window.preventivoData.id);
    console.log('📝 Edit mode - Preventivo ID:', window.preventivoData.preventivo_id);
    
    // Mostra pulsanti post-creazione se già esistente
    $('#post-creation-actions').show();
    <?php endif; ?>
    
    const $form = $('#disco747-form-preventivo');
    const $submitButtons = $form.find('button[type="submit"]');
    
    if (!$form.length) {
        console.error('❌ Form preventivo non trovato!');
        return;
    }
    
    // INTERCETTA SUBMIT FORM
    $form.on('submit', function(e) {
        e.preventDefault();
        
        console.log('🚀 [747Disco-AJAX] Submit form intercettato');
        
        // Disabilita pulsanti
        $submitButtons.prop('disabled', true).html('â³ Salvataggio...');
        
        // Raccogli dati form
        const formData = new FormData(this);
        formData.append('action', 'disco747_save_preventivo');
        
        // Fix nonce
        const wpNonce = formData.get('disco747_preventivo_nonce');
        if (wpNonce) {
            formData.append('disco747_nonce', wpNonce);
        }
        
        // AJAX Request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('✅ Risposta server:', response);
                
                if (response.success) {
                    alert('✅ ' + (response.data.message || 'Preventivo creato con successo!'));
                    
                    // ✅ MOSTRA I PULSANTI POST-CREAZIONE
                    $('#post-creation-actions').slideDown(500);
                    
                    // Salva dati per i pulsanti
                    window.preventivoData = response.data;
                    
                    // Scroll verso i pulsanti
                    $('html, body').animate({
                        scrollTop: $('#post-creation-actions').offset().top - 100
                    }, 800);
                    
                } else {
                    const errorMsg = response.data || response.message || 'Errore sconosciuto';
                    alert('❌ Errore: ' + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX:', {xhr, status, error});
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                // Ripristina il testo originale del pulsante
                $submitButtons.prop('disabled', false);
                const isEditMode = $('#is_edit_mode').val();
                const buttonText = isEditMode ? '💾 Aggiorna Preventivo' : '💾 Salva Preventivo';
                $submitButtons.html(buttonText);
            }
        });
    });
});
</script>

<!-- ============================================================================ -->
<!-- JAVASCRIPT: Handler 3 Pulsanti (PDF, EMAIL, WHATSAPP) -->
<!-- ============================================================================ -->
<script>
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🎯 [747Disco-Actions] Handler pulsanti PDF/Email/WhatsApp caricato');
    
    // ========================================================================
    // PULSANTE 1: Genera PDF
    // ========================================================================
    $('#btn-generate-pdf').on('click', function() {
        console.log('📄 Genera PDF cliccato');
        console.log('📄 preventivoData:', window.preventivoData);
        
        // Verifica che esista almeno l'ID numerico
        if (!window.preventivoData || (!window.preventivoData.id && !window.preventivoData.db_id)) {
            alert('❌ Errore: Dati preventivo non disponibili');
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).html('â³ Generazione PDF...');
        
        const prevId = window.preventivoData.id || window.preventivoData.db_id;
        
        console.log('📄 Preventivo ID estratto:', prevId);
        console.log('📄 preventivoData.id:', window.preventivoData.id);
        console.log('📄 preventivoData.db_id:', window.preventivoData.db_id);
        
        if (!prevId || prevId <= 0) {
            alert('❌ Errore: ID preventivo non trovato o non valido');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_generate_pdf',
                nonce: '<?php echo wp_create_nonce("disco747_generate_pdf"); ?>',
                preventivo_id: prevId
            },
            success: function(response) {
                console.log('✅ Risposta generazione PDF:', response);
                
                if (response.success && response.data.pdf_url) {
                    alert('✅ PDF generato con successo!');
                    
                    // Download automatico
                    window.open(response.data.pdf_url, '_blank');
                    
                } else {
                    alert('❌ Errore: ' + (response.data || 'Impossibile generare PDF'));
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX PDF:', error);
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).html('📄 Genera e Scarica PDF');
            }
        });
    });
    
    // ========================================================================
    // PULSANTE 2: Invia Email - Apre Modal
    // ========================================================================
    $('#btn-send-email').on('click', function() {
        console.log('📧 Invia Email cliccato');
        console.log('📧 preventivoData:', window.preventivoData);
        
        // Verifica che esista almeno l'ID numerico
        if (!window.preventivoData || (!window.preventivoData.id && !window.preventivoData.db_id)) {
            alert('❌ Errore: Dati preventivo non disponibili');
            return;
        }
        
        console.log('📧 ID trovato:', window.preventivoData.id || window.preventivoData.db_id);
        
        // Mostra modal selezione template
        $('#modal-email-template').css('display', 'flex').hide().fadeIn(300);
    });
    
    // Chiudi modal email
    $('#cancel-email-modal').on('click', function() {
        $('#modal-email-template').fadeOut(300);
    });
    
    // Conferma invio email
    $('#confirm-send-email').on('click', function() {
        const templateId = $('#email-template-select').val();
        const attachPdf = $('#email-attach-pdf').is(':checked');
        
        console.log('📧 Invio email con template:', templateId, 'PDF allegato:', attachPdf);
        
        // Verifica ID numerico
        const prevId = window.preventivoData.id || window.preventivoData.db_id;
        console.log('📧 ID numerico estratto:', prevId);
        console.log('📧 preventivoData:', window.preventivoData);
        
        if (!prevId) {
            alert('❌ ID preventivo mancante');
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).html('â³ Invio...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_send_email_template',
                nonce: '<?php echo wp_create_nonce("disco747_send_email"); ?>',
                preventivo_id: (window.preventivoData.id || window.preventivoData.db_id),  // ID numerico
                template_id: templateId,
                attach_pdf: attachPdf ? '1' : '0'
            },
            success: function(response) {
                console.log('✅ Risposta invio email:', response);
                
                if (response.success) {
                    alert('✅ Email inviata con successo!');
                    $('#modal-email-template').fadeOut(300);
                } else {
                    alert('❌ Errore: ' + (response.data || 'Impossibile inviare email'));
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX Email:', error);
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).html('📧 Invia Email');
            }
        });
    });
    
    // ========================================================================
    // PULSANTE 3: Invia WhatsApp - Apre Modal
    // ========================================================================
    $('#btn-send-whatsapp').on('click', function() {
        console.log('💬 Invia WhatsApp cliccato');
        console.log('💬 preventivoData:', window.preventivoData);
        
        // Verifica che esista almeno l'ID numerico
        if (!window.preventivoData || (!window.preventivoData.id && !window.preventivoData.db_id)) {
            alert('❌ Errore: Dati preventivo non disponibili');
            return;
        }
        
        console.log('💬 ID trovato:', window.preventivoData.id || window.preventivoData.db_id);
        
        // Mostra modal selezione template
        $('#modal-whatsapp-template').css('display', 'flex').hide().fadeIn(300);
    });
    
    // Chiudi modal whatsapp
    $('#cancel-whatsapp-modal').on('click', function() {
        $('#modal-whatsapp-template').fadeOut(300);
    });
    
    // Conferma invio whatsapp
    $('#confirm-send-whatsapp').on('click', function() {
        const templateId = $('#whatsapp-template-select').val();
        
        // Usa id numerico (priorità a id/db_id che sono sempre presenti)
        const prevId = window.preventivoData.id || window.preventivoData.db_id;
        
        console.log('💬 Invio WhatsApp con template:', templateId);
        console.log('💬 Preventivo ID estratto:', prevId);
        console.log('💬 preventivoData completo:', window.preventivoData);
        
        if (!prevId || prevId <= 0) {
            alert('❌ Errore: ID preventivo non trovato o non valido');
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).html('â³ Preparazione...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_send_whatsapp_template',
                nonce: '<?php echo wp_create_nonce("disco747_send_whatsapp"); ?>',
                preventivo_id: prevId,
                template_id: templateId
            },
            success: function(response) {
                console.log('✅ Risposta WhatsApp:', response);
                
                if (response.success && response.data.whatsapp_url) {
                    // Apri WhatsApp in nuova finestra
                    window.open(response.data.whatsapp_url, '_blank');
                    
                    alert('✅ WhatsApp aperto! Controlla la finestra per inviare il messaggio.');
                    $('#modal-whatsapp-template').fadeOut(300);
                } else {
                    alert('❌ Errore: ' + (response.data || 'Impossibile preparare messaggio WhatsApp'));
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Errore AJAX WhatsApp:', error);
                alert('❌ Errore di connessione: ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).html('💬 Apri WhatsApp');
            }
        });
    });
    
    // Chiudi modal cliccando fuori
    $('#modal-email-template, #modal-whatsapp-template').on('click', function(e) {
        if ($(e.target).is(this)) {
            $(this).fadeOut(300);
        }
    });
    
    // ============================================================================
    // CARICAMENTO DINAMICO TEMPLATE
    // ============================================================================
    
    // Funzione per caricare i template disponibili
    function loadAvailableTemplates() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_get_templates',
                nonce: '<?php echo wp_create_nonce("disco747_admin_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    console.log('✅ Template caricati:', response.data);
                    populateEmailTemplates(response.data.email);
                    populateWhatsAppTemplates(response.data.whatsapp);
                } else {
                    console.error('❌ Errore caricamento template:', response.data);
                }
            },
            error: function() {
                console.error('❌ Errore nella richiesta AJAX template');
            }
        });
    }
    
    // Popola select email
    function populateEmailTemplates(templates) {
        const select = $('#email-template-select');
        if (!select.length) return;
        
        select.html('');
        
        if (templates.length === 0) {
            select.html('<option value="">Nessun template disponibile</option>');
            return;
        }
        
        templates.forEach(function(template) {
            const option = $('<option>')
                .val(template.id)
                .text(template.name);
            select.append(option);
        });
        
        console.log('✅ ' + templates.length + ' template email caricati');
    }
    
    // Popola select WhatsApp
    function populateWhatsAppTemplates(templates) {
        const select = $('#whatsapp-template-select');
        if (!select.length) return;
        
        select.html('');
        
        if (templates.length === 0) {
            select.html('<option value="">Nessun template disponibile</option>');
            return;
        }
        
        templates.forEach(function(template) {
            const option = $('<option>')
                .val(template.id)
                .text(template.name);
            select.append(option);
        });
        
        console.log('✅ ' + templates.length + ' template WhatsApp caricati');
    }
    
    // Carica i template all'avvio
    loadAvailableTemplates();
    
});
</script>

<!-- ============================================================================ -->
<!-- CSS: Stili Aggiuntivi -->
<!-- ============================================================================ -->
<style>
.disco747-form-preventivo input:focus,
.disco747-form-preventivo select:focus,
.disco747-form-preventivo textarea:focus {
    border-color: #c28a4d !important;
    box-shadow: 0 0 0 3px rgba(194, 138, 77, 0.1) !important;
    outline: none !important;
}

.disco747-form-preventivo button:hover {
    transform: translateY(-2px);
    filter: brightness(110%);
}

/* Hover effects per card pulsanti */
#post-creation-actions > div > div > div:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .disco747-form-preventivo [style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>