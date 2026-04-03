<?php
/**
 * Pagina Messaggi Automatici - 747 Disco CRM
 * VERSIONE 12.1.0 - Gestione Template Email + WhatsApp + Test Email
 * 
 * NOVITÀ v12.1.0:
 * - Pulsante "Testa Email" con input per email destinatario
 * - Invio email di test tramite AJAX
 * - Sostituzione automatica placeholder con dati di esempio
 * 
 * @package Disco747_CRM
 * @version 12.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Salva impostazioni se form inviato
if (isset($_POST['save_message_templates']) && wp_verify_nonce($_POST['disco747_messages_nonce'], 'disco747_save_messages')) {
    
    // Salva Email Templates
    for ($i = 1; $i <= get_option('disco747_max_templates', 5); $i++) {
        update_option('disco747_email_name_' . $i, sanitize_text_field($_POST['email_name_' . $i] ?? 'Template Email ' . $i));
        update_option('disco747_email_template_' . $i, wp_kses_post($_POST['email_template_' . $i] ?? ''));
        update_option('disco747_email_subject_' . $i, sanitize_text_field($_POST['email_subject_' . $i] ?? ''));
        update_option('disco747_email_enabled_' . $i, isset($_POST['email_enabled_' . $i]) ? 1 : 0);
    }
    
    // ✅ NUOVO: Salva Template WhatsApp Form (i 3 template del form preventivo)
    for ($i = 1; $i <= 3; $i++) {
        // Controlla se il template deve essere cancellato
        if (isset($_POST['delete_form_whatsapp_' . $i])) {
            delete_option('disco747_form_whatsapp_template_' . $i);
            delete_option('disco747_form_whatsapp_name_' . $i);
        } else {
            // Salva normalmente
            $template_content = $_POST['form_whatsapp_template_' . $i] ?? '';
            update_option('disco747_form_whatsapp_template_' . $i, $template_content);
            update_option('disco747_form_whatsapp_name_' . $i, sanitize_text_field($_POST['form_whatsapp_name_' . $i] ?? 'Template Form ' . $i));
        }
    }
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Template salvati con successo!</strong></p></div>';
}

// Numero massimo di template (modificabile)
$max_templates = get_option('disco747_max_templates', 5);

// Carica impostazioni esistenti
$email_templates = array();
$form_whatsapp_templates = array();

for ($i = 1; $i <= $max_templates; $i++) {
    $email_templates[$i] = array(
        'name' => get_option('disco747_email_name_' . $i, 'Template Email ' . $i),
        'subject' => get_option('disco747_email_subject_' . $i, ''),
        'body' => get_option('disco747_email_template_' . $i, ''),
        'enabled' => get_option('disco747_email_enabled_' . $i, 1)
    );
}

// ✅ NUOVO: Carica i 3 template WhatsApp del form con valori di default
$default_form_templates = array(
    1 => "Ciao {{nome}}! 🎉\n\nIl tuo preventivo per {{tipo_evento}} del {{data_evento}} è pronto!\n\n💰 Importo: {{importo}}\n\n747 Disco - La tua festa indimenticabile! 🎊",
    2 => "Ciao {{nome}}! 🎈\n\nTi ricordiamo il tuo evento del {{data_evento}}.\n\nHai confermato? Rispondi per finalizzare! 📞",
    3 => "Ciao {{nome}}! ✅\n\nGrazie per aver confermato!\n\n📅 {{data_evento}}\n💰 Acconto: {{acconto}}\n\nCi vediamo presto! 🎉"
);

for ($i = 1; $i <= 3; $i++) {
    $form_whatsapp_templates[$i] = array(
        'name' => get_option('disco747_form_whatsapp_name_' . $i, 'Template Form WhatsApp #' . $i),
        'body' => get_option('disco747_form_whatsapp_template_' . $i, $default_form_templates[$i])
    );
}
?>

<div class="wrap disco747-wrap">
    <h1 class="disco747-page-title">
        <span class="disco747-icon">💬</span>
        Messaggi Automatici
    </h1>

    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header">
            <h3>ℹ️ Informazioni sui Template</h3>
        </div>
        <div class="disco747-card-content">
            <p>Configura i template per email e WhatsApp che potrai inviare dopo la creazione del preventivo.</p>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 15px;">
                <strong style="font-size: 16px;">📖 Campi dinamici disponibili (Placeholder)</strong>
                <p style="margin: 10px 0; color: #666; font-size: 14px;">Clicca su un placeholder per copiarlo negli appunti</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 20px;">
                    
                    <!-- Cliente -->
                    <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff;">
                        <strong style="display: block; margin-bottom: 10px; color: #007bff;">👤 Cliente</strong>
                        <code class="copyable-placeholder">{{nome}}</code>
                        <code class="copyable-placeholder">{{cognome}}</code>
                        <code class="copyable-placeholder">{{nome_completo}}</code>
                        <code class="copyable-placeholder">{{email}}</code>
                        <code class="copyable-placeholder">{{telefono}}</code>
                    </div>
                    
                    <!-- Evento -->
                    <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
                        <strong style="display: block; margin-bottom: 10px; color: #28a745;">🎉 Evento</strong>
                        <code class="copyable-placeholder">{{data_evento}}</code>
                        <code class="copyable-placeholder">{{tipo_evento}}</code>
                        <code class="copyable-placeholder">{{numero_invitati}}</code>
                        <code class="copyable-placeholder">{{orario_inizio}}</code>
                        <code class="copyable-placeholder">{{orario_fine}}</code>
                    </div>
                    
                    <!-- Menu -->
                    <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <strong style="display: block; margin-bottom: 10px; color: #f39c12;">🍽️ Menu</strong>
                        <code class="copyable-placeholder">{{tipo_menu}}</code>
                        <code class="copyable-placeholder">{{importo}}</code>
                        <code class="copyable-placeholder">{{acconto}}</code>
                        <code class="copyable-placeholder">{{saldo}}</code>
                    </div>
                    
                    <!-- Extra -->
                    <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #6f42c1;">
                        <strong style="display: block; margin-bottom: 10px; color: #6f42c1;">✨ Altro</strong>
                        <code class="copyable-placeholder">{{preventivo_id}}</code>
                        <code class="copyable-placeholder">{{omaggio1}}</code>
                        <code class="copyable-placeholder">{{extra1}}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('disco747_save_messages', 'disco747_messages_nonce'); ?>

        <!-- ========================================
             TEMPLATE WHATSAPP FORM PREVENTIVO
        ======================================== -->
        <div class="disco747-card" style="margin-bottom: 30px; border: 3px solid #d4af37;">
            <div class="disco747-card-header" style="background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%); color: #000; display: flex; justify-content: space-between; align-items: center; padding: 20px;">
                <div>
                    <h2 style="margin: 0; color: #000; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">📱</span>
                        <span>Template WhatsApp Form Preventivo</span>
                    </h2>
                    <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">
                        Questi sono i 3 template usati quando clicchi il pulsante WhatsApp dalla dashboard preventivi
                    </p>
                </div>
            </div>
            <div class="disco747-card-content">
                
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <strong style="color: #856404;">⚠️ IMPORTANTE:</strong>
                    <ul style="margin: 10px 0 0 20px; color: #856404;">
                        <li>Questi template vengono usati <strong>direttamente dal form preventivo</strong></li>
                        <li>Puoi usare <strong>emoji</strong> liberamente (verranno preservate)</li>
                        <li>Usa i <strong>placeholder</strong> per inserire dati dinamici</li>
                        <li>Template 1: Preventivo nuovo | Template 2: Promemoria | Template 3: Conferma</li>
                    </ul>
                </div>
                
                <?php foreach ($form_whatsapp_templates as $i => $template): ?>
                <div id="form-whatsapp-<?php echo $i; ?>" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #d4af37; position: relative;">
                    
                    <!-- Pulsante Cancella in alto a destra -->
                    <div style="position: absolute; top: 15px; right: 15px;">
                        <button type="button" 
                                class="disco747-button" 
                                onclick="deleteFormWhatsAppTemplate(<?php echo $i; ?>)"
                                style="background: #dc3545; color: white; padding: 8px 15px; font-size: 13px; border: none; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <span>🗑️</span>
                            <span>Cancella Template</span>
                        </button>
                    </div>
                    
                    <div style="margin-bottom: 15px; padding-right: 180px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            📝 Nome Template #<?php echo $i; ?>
                        </label>
                        <input type="text" 
                               name="form_whatsapp_name_<?php echo $i; ?>" 
                               id="form_whatsapp_name_<?php echo $i; ?>"
                               value="<?php echo esc_attr($template['name']); ?>" 
                               placeholder="Es: Template Preventivo Nuovo"
                               style="font-size: 16px; font-weight: 600; color: #2b1e1a; border: 1px solid #ddd; padding: 10px 15px; border-radius: 4px; width: 100%; box-sizing: border-box;">
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="form_whatsapp_template_<?php echo $i; ?>" style="display: block; font-weight: 600; margin-bottom: 8px;">
                            💬 Messaggio WhatsApp
                        </label>
                        <textarea id="form_whatsapp_template_<?php echo $i; ?>" 
                                  name="form_whatsapp_template_<?php echo $i; ?>" 
                                  rows="10"
                                  placeholder="Scrivi il messaggio WhatsApp con emoji 🎉..."
                                  style="width: 100%; padding: 12px; border: 2px solid #d4af37; border-radius: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; line-height: 1.6; box-sizing: border-box;"><?php echo esc_textarea($template['body']); ?></textarea>
                    </div>
                    
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <button type="button" class="disco747-button disco747-button-secondary" onclick="previewFormWhatsApp(<?php echo $i; ?>)">
                            👁️ Anteprima
                        </button>
                        <button type="button" class="disco747-button" onclick="testFormWhatsApp(<?php echo $i; ?>)" style="background: #25d366; color: white; border: none;">
                            📱 Testa WhatsApp
                        </button>
                        <button type="button" class="disco747-button" onclick="resetFormWhatsAppTemplate(<?php echo $i; ?>)" style="background: #6c757d; color: white; border: none;">
                            🔄 Ripristina Default
                        </button>
                    </div>
                    
                    <!-- Campo nascosto per segnalare cancellazione -->
                    <input type="hidden" name="delete_form_whatsapp_<?php echo $i; ?>" id="delete_flag_<?php echo $i; ?>" value="">
                </div>
                <?php endforeach; ?>
                
            </div>
        </div>

        <!-- ========================================
             TEMPLATE EMAIL
        ======================================== -->
        <div class="disco747-card" style="margin-bottom: 30px;">
            <div class="disco747-card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; justify-content: space-between; align-items: center; padding: 20px;">
                <h2 style="margin: 0; color: white;">📧 Template Email</h2>
            </div>
            <div class="disco747-card-content">
                
                <?php for ($i = 1; $i <= $max_templates; $i++): ?>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #007bff;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <input type="text" 
                               name="email_name_<?php echo $i; ?>" 
                               value="<?php echo esc_attr($email_templates[$i]['name']); ?>" 
                               placeholder="Nome Template"
                               style="font-size: 18px; font-weight: 700; color: #2b1e1a; border: 1px solid #ddd; padding: 8px 12px; border-radius: 4px; width: 50%;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="email_enabled_<?php echo $i; ?>" value="1" <?php checked($email_templates[$i]['enabled'], 1); ?>>
                            <span style="font-weight: 600;">Attivo</span>
                        </label>
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="email_subject_<?php echo $i; ?>">Oggetto Email</label>
                        <input type="text" 
                               id="email_subject_<?php echo $i; ?>" 
                               name="email_subject_<?php echo $i; ?>" 
                               value="<?php echo esc_attr($email_templates[$i]['subject']); ?>" 
                               placeholder="Es: Preventivo {{preventivo_id}} - {{nome_completo}}"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div class="disco747-form-group" style="margin-top: 15px;">
                        <label for="email_template_<?php echo $i; ?>">Corpo Email (HTML)</label>
                        <textarea id="email_template_<?php echo $i; ?>" 
                                  name="email_template_<?php echo $i; ?>" 
                                  rows="12"
                                  placeholder="Scrivi il testo dell'email in HTML..."
                                  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;"><?php echo esc_textarea($email_templates[$i]['body']); ?></textarea>
                    </div>
                    
                    <!-- ✅ NUOVO: Pulsanti Anteprima e Testa Email con campo input email -->
                    <div style="margin-top: 15px; display: flex; gap: 10px; align-items: flex-end;">
                        <div style="flex: 1;">
                            <button type="button" class="disco747-button disco747-button-secondary" onclick="previewEmail(<?php echo $i; ?>)">
                                👁️ Anteprima
                            </button>
                        </div>
                        <div style="flex: 1; display: flex; gap: 10px; align-items: center;">
                            <input type="email" 
                                   id="test_email_input_<?php echo $i; ?>" 
                                   placeholder="email@example.com" 
                                   value="<?php echo esc_attr(get_option('admin_email')); ?>"
                                   style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                            <button type="button" class="disco747-button" onclick="testEmail(<?php echo $i; ?>)" style="background: #667eea; color: white; border: none; white-space: nowrap;">
                                📧 Testa Email
                            </button>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
                
            </div>
        </div>

        <!-- Pulsante Salva -->
        <div class="disco747-form-actions">
            <button type="submit" name="save_message_templates" class="disco747-button disco747-button-primary" style="font-size: 18px; padding: 15px 30px;">
                💾 Salva Tutti i Template
            </button>
        </div>
    </form>

</div>

<script>
// Funzione per copiare placeholder
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.copyable-placeholder').forEach(function(element) {
        element.style.cursor = 'pointer';
        element.style.display = 'inline-block';
        element.style.margin = '5px';
        element.style.padding = '4px 8px';
        element.style.background = '#e9ecef';
        element.style.borderRadius = '4px';
        element.style.fontSize = '13px';
        element.style.transition = 'all 0.2s';
        
        element.addEventListener('click', function() {
            const text = this.textContent;
            navigator.clipboard.writeText(text).then(function() {
                const original = element.style.background;
                element.style.background = '#28a745';
                element.style.color = 'white';
                setTimeout(function() {
                    element.style.background = original;
                    element.style.color = '';
                }, 300);
            });
        });
        
        element.addEventListener('mouseenter', function() {
            this.style.background = '#007bff';
            this.style.color = 'white';
        });
        
        element.addEventListener('mouseleave', function() {
            this.style.background = '#e9ecef';
            this.style.color = '';
        });
    });
});

// Anteprima Email
function previewEmail(templateId) {
    const subject = document.getElementById('email_subject_' + templateId).value;
    const body = document.getElementById('email_template_' + templateId).value;
    
    const previewWindow = window.open('', 'Preview', 'width=800,height=600');
    previewWindow.document.write(`
        <html>
        <head>
            <title>Anteprima Email</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
                .preview-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .subject { font-size: 18px; font-weight: bold; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #d4af37; }
            </style>
        </head>
        <body>
            <div class="preview-container">
                <div class="subject">Oggetto: ${subject}</div>
                ${body}
            </div>
        </body>
        </html>
    `);
}

// ✅ NUOVO: Testa Email - invia email di test
function testEmail(templateId) {
    const subject = document.getElementById('email_subject_' + templateId).value;
    const body = document.getElementById('email_template_' + templateId).value;
    const to_email = document.getElementById('test_email_input_' + templateId).value;
    
    // Validazione
    if (!subject.trim()) {
        alert('⚠️ L\'oggetto email è vuoto!\n\nInserisci un oggetto prima di testare.');
        return;
    }
    
    if (!body.trim()) {
        alert('⚠️ Il corpo email è vuoto!\n\nInserisci il contenuto dell\'email prima di testare.');
        return;
    }
    
    if (!to_email.trim() || !to_email.includes('@')) {
        alert('⚠️ Indirizzo email non valido!\n\nInserisci un email valida nel campo di testo.');
        return;
    }
    
    // Sostituisci placeholder con dati di esempio
    let testSubject = subject
        .replace(/{{nome}}/g, 'Mario')
        .replace(/{{cognome}}/g, 'Rossi')
        .replace(/{{nome_completo}}/g, 'Mario Rossi')
        .replace(/{{email}}/g, 'mario.rossi@example.com')
        .replace(/{{telefono}}/g, '+39 123 456 7890')
        .replace(/{{tipo_evento}}/g, 'Compleanno 18 anni')
        .replace(/{{data_evento}}/g, '15/12/2024')
        .replace(/{{orario_inizio}}/g, '20:30')
        .replace(/{{orario_fine}}/g, '01:30')
        .replace(/{{tipo_menu}}/g, 'Menu 747')
        .replace(/{{importo}}/g, '€ 1.500,00')
        .replace(/{{acconto}}/g, '€ 500,00')
        .replace(/{{saldo}}/g, '€ 1.000,00')
        .replace(/{{numero_invitati}}/g, '50')
        .replace(/{{preventivo_id}}/g, '123')
        .replace(/{{omaggio1}}/g, 'Cocktail di benvenuto')
        .replace(/{{extra1}}/g, 'DJ incluso');
    
    let testBody = body
        .replace(/{{nome}}/g, 'Mario')
        .replace(/{{cognome}}/g, 'Rossi')
        .replace(/{{nome_completo}}/g, 'Mario Rossi')
        .replace(/{{email}}/g, 'mario.rossi@example.com')
        .replace(/{{telefono}}/g, '+39 123 456 7890')
        .replace(/{{tipo_evento}}/g, 'Compleanno 18 anni')
        .replace(/{{data_evento}}/g, '15/12/2024')
        .replace(/{{orario_inizio}}/g, '20:30')
        .replace(/{{orario_fine}}/g, '01:30')
        .replace(/{{tipo_menu}}/g, 'Menu 747')
        .replace(/{{importo}}/g, '€ 1.500,00')
        .replace(/{{acconto}}/g, '€ 500,00')
        .replace(/{{saldo}}/g, '€ 1.000,00')
        .replace(/{{numero_invitati}}/g, '50')
        .replace(/{{preventivo_id}}/g, '123')
        .replace(/{{omaggio1}}/g, 'Cocktail di benvenuto')
        .replace(/{{extra1}}/g, 'DJ incluso');
    
    // Richiesta AJAX per inviare email di test
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'disco747_send_test_email',
            nonce: jQuery('input[name="disco747_messages_nonce"]').val(),
            template_id: templateId,
            subject: testSubject,
            body: testBody,
            to_email: to_email
        },
        beforeSend: function() {
            jQuery('#test_email_input_' + templateId).prop('disabled', true);
        },
        success: function(response) {
            if (response.success) {
                alert('✅ Email di test inviata con successo a:\n\n' + to_email + '\n\nSe non la vedi in Posta, controlla lo spam.');
            } else {
                alert('❌ Errore invio email:\n\n' + (response.data || 'Errore sconosciuto'));
            }
        },
        error: function(xhr, status, error) {
            alert('❌ Errore di comunicazione:\n\n' + error);
        },
        complete: function() {
            jQuery('#test_email_input_' + templateId).prop('disabled', false);
        }
    });
}

// ✅ Anteprima WhatsApp Form
function previewFormWhatsApp(templateId) {
    const name = document.getElementById('form_whatsapp_template_' + templateId).value;
    const body = document.getElementById('form_whatsapp_template_' + templateId).value;
    
    const previewWindow = window.open('', 'Preview WhatsApp', 'width=400,height=600');
    previewWindow.document.write(`
        <html>
        <head>
            <title>Anteprima WhatsApp</title>
            <style>
                body { 
                    margin: 0; 
                    padding: 0; 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    background: #0b141a;
                }
                .whatsapp-container {
                    max-width: 400px;
                    margin: 0 auto;
                    background: #0b141a;
                    height: 100vh;
                    display: flex;
                    flex-direction: column;
                }
                .whatsapp-header {
                    background: #202c33;
                    color: #e9edef;
                    padding: 15px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .whatsapp-chat {
                    flex: 1;
                    background: #0b141a url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect fill="%230b141a" width="100" height="100"/></svg>');
                    padding: 20px;
                    overflow-y: auto;
                }
                .message-bubble {
                    background: #005c4b;
                    color: #e9edef;
                    padding: 8px 12px;
                    border-radius: 8px;
                    margin-bottom: 10px;
                    max-width: 80%;
                    margin-left: auto;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                    font-size: 14px;
                    line-height: 1.5;
                    box-shadow: 0 1px 2px rgba(0,0,0,0.3);
                }
                .time {
                    font-size: 11px;
                    color: #8696a0;
                    text-align: right;
                    margin-top: 5px;
                }
            </style>
        </head>
        <body>
            <div class="whatsapp-container">
                <div class="whatsapp-header">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #d4af37;"></div>
                    <div>
                        <div style="font-weight: 600;">Cliente Esempio</div>
                        <div style="font-size: 12px; opacity: 0.7;">online</div>
                    </div>
                </div>
                <div class="whatsapp-chat">
                    <div class="message-bubble">
                        ${body.replace(/\n/g, '<br>')}
                        <div class="time">${new Date().toLocaleTimeString('it-IT', {hour: '2-digit', minute: '2-digit'})}</div>
                    </div>
                </div>
            </div>
        </body>
        </html>
    `);
}

// ✅ Testa WhatsApp Form (apre WhatsApp con messaggio)
function testFormWhatsApp(templateId) {
    const body = document.getElementById('form_whatsapp_template_' + templateId).value;
    
    let testMessage = body
        .replace(/{{nome}}/g, 'Mario')
        .replace(/{{cognome}}/g, 'Rossi')
        .replace(/{{nome_completo}}/g, 'Mario Rossi')
        .replace(/{{tipo_evento}}/g, 'Compleanno 18 anni')
        .replace(/{{data_evento}}/g, '15/12/2024')
        .replace(/{{orario_inizio}}/g, '20:30')
        .replace(/{{orario_fine}}/g, '01:30')
        .replace(/{{tipo_menu}}/g, 'Menu 747')
        .replace(/{{importo}}/g, '€ 1.500,00')
        .replace(/{{acconto}}/g, '€ 500,00')
        .replace(/{{numero_invitati}}/g, '50')
        .replace(/{{preventivo_id}}/g, '123')
        .replace(/{{omaggio1}}/g, 'Cocktail di benvenuto')
        .replace(/{{extra1}}/g, 'DJ incluso');
    
    const whatsappUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(testMessage);
    window.open(whatsappUrl, '_blank');
}

// ✅ Cancella template WhatsApp Form
function deleteFormWhatsAppTemplate(templateId) {
    if (!confirm('⚠️ SEI SICURO?\n\nVuoi cancellare definitivamente questo template?\n\nDopo il salvataggio, il template tornerà al valore predefinito.')) {
        return;
    }
    
    document.getElementById('delete_flag_' + templateId).value = '1';
    document.getElementById('form_whatsapp_name_' + templateId).value = '';
    document.getElementById('form_whatsapp_template_' + templateId).value = '';
    
    const templateBox = document.getElementById('form-whatsapp-' + templateId);
    templateBox.style.opacity = '0.5';
    templateBox.style.pointerEvents = 'none';
    
    const deleteMessage = document.createElement('div');
    deleteMessage.style.cssText = 'background: #dc3545; color: white; padding: 15px; border-radius: 8px; margin-bottom: 15px; text-align: center; font-weight: 600;';
    deleteMessage.innerHTML = '🗑️ Template segnato per cancellazione - Salva per confermare';
    templateBox.insertBefore(deleteMessage, templateBox.firstChild);
    
    alert('✅ Template segnato per cancellazione!\n\nClicca "💾 Salva Tutti i Template" in fondo alla pagina per confermare.');
}

// ✅ Ripristina template predefinito
function resetFormWhatsAppTemplate(templateId) {
    const defaultTemplates = {
        1: "Ciao {{nome}}! 🎉\n\nIl tuo preventivo per {{tipo_evento}} del {{data_evento}} è pronto!\n\n💰 Importo: {{importo}}\n\n747 Disco - La tua festa indimenticabile! 🎊",
        2: "Ciao {{nome}}! 🎈\n\nTi ricordiamo il tuo evento del {{data_evento}}.\n\nHai confermato? Rispondi per finalizzare! 📞",
        3: "Ciao {{nome}}! ✅\n\nGrazie per aver confermato!\n\n📅 {{data_evento}}\n💰 Acconto: {{acconto}}\n\nCi vediamo presto! 🎉"
    };
    
    if (!confirm('🔄 Ripristinare il template predefinito?\n\nIl testo attuale verrà sostituito con quello originale.')) {
        return;
    }
    
    document.getElementById('form_whatsapp_template_' + templateId).value = defaultTemplates[templateId];
    document.getElementById('form_whatsapp_name_' + templateId).value = 'Template Form WhatsApp #' + templateId;
    document.getElementById('delete_flag_' + templateId).value = '';
    
    const templateBox = document.getElementById('form-whatsapp-' + templateId);
    templateBox.style.opacity = '1';
    templateBox.style.pointerEvents = 'auto';
    
    alert('✅ Template ripristinato!\n\nRicorda di salvare le modifiche cliccando "💾 Salva Tutti i Template".');
}
</script>

<style>
.disco747-wrap {
    background: #f5f5f5;
    padding: 20px;
}

.disco747-page-title {
    font-size: 32px;
    font-weight: 700;
    color: #2b1e1a;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.disco747-icon {
    font-size: 40px;
}

.disco747-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.disco747-card-header {
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    color: #000;
    padding: 20px;
    font-weight: 600;
}

.disco747-card-content {
    padding: 30px;
}

.disco747-form-group {
    margin-bottom: 20px;
}

.disco747-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #2b1e1a;
}

.disco747-button {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.disco747-button-primary {
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    color: #000;
}

.disco747-button-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.4);
}

.disco747-button-secondary {
    background: #6c757d;
    color: white;
}

.disco747-button-secondary:hover {
    background: #5a6268;
}

.disco747-form-actions {
    text-align: center;
    margin-top: 30px;
}
</style>