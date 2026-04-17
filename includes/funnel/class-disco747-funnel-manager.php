<?php
/**
 * Funnel Manager - 747 Disco CRM - VERSIONE AGGIORNATA
 * Gestisce la logica del funnel marketing con supporto HTML e anteprima
 * 
 * NOVITA:
 * - Gestione corretta HTML con CSS inline
 * - Anteprima email funzionante
 * - Test invio email
 * - Template email base per contenuti semplici
 * 
 * @package    Disco747_CRM
 * @subpackage Funnel
 * @version    2.0.0
 */

namespace Disco747_CRM\Funnel;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Funnel_Manager {
    
    private $sequences_table;
    private $tracking_table;
    private $preventivi_table;
    
    public function __construct() {
        global $wpdb;
        
        $this->sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';
        $this->tracking_table = $wpdb->prefix . 'disco747_funnel_tracking';
        $this->preventivi_table = $wpdb->prefix . 'disco747_preventivi';
    }
    
    /**
     * Avvia un funnel per un preventivo
     */
    public function start_funnel($preventivo_id, $funnel_type = 'pre_conferma') {
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tracking_table} 
             WHERE preventivo_id = %d AND funnel_type = %s AND status = 'active'",
            $preventivo_id,
            $funnel_type
        ));
        
        if ($existing) {
            error_log("[747Disco-Funnel] Funnel gia attivo per preventivo #{$preventivo_id}");
            return false;
        }
        
        $first_step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sequences_table} 
             WHERE funnel_type = %s AND active = 1 
             ORDER BY step_number ASC LIMIT 1",
            $funnel_type
        ));
        
        if (!$first_step) {
            error_log("[747Disco-Funnel] Nessuna sequenza attiva trovata per {$funnel_type}");
            return false;
        }
        
        $send_time = $first_step->send_time ?? '09:00:00';
        
        if ($funnel_type === 'pre_evento') {
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT data_evento FROM {$this->preventivi_table} WHERE id = %d",
                $preventivo_id
            ));
            
            if ($preventivo && $preventivo->data_evento) {
                $next_send_at = date('Y-m-d', strtotime($preventivo->data_evento . ' ' . $first_step->days_offset . ' days')) . ' ' . $send_time;
            } else {
                $next_send_at = date('Y-m-d', strtotime("+{$first_step->days_offset} days")) . ' ' . $send_time;
            }
        } else {
            $next_send_at = date('Y-m-d', strtotime("+{$first_step->days_offset} days")) . ' ' . $send_time;
        }
        
        $inserted = $wpdb->insert(
            $this->tracking_table,
            array(
                'preventivo_id' => $preventivo_id,
                'funnel_type' => $funnel_type,
                'current_step' => 0,
                'status' => 'active',
                'started_at' => current_time('mysql'),
                'next_send_at' => $next_send_at,
                'emails_log' => json_encode(array()),
                'whatsapp_log' => json_encode(array())
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($inserted) {
            $tracking_id = $wpdb->insert_id;
            error_log("[747Disco-Funnel] Funnel {$funnel_type} avviato per preventivo #{$preventivo_id} (Tracking ID: {$tracking_id})");
            
            if ($first_step->days_offset == 0) {
                $this->send_next_step($tracking_id);
            }
            
            return $tracking_id;
        }
        
        return false;
    }
    
    /**
     * Invia il prossimo step del funnel
     */
    public function send_next_step($tracking_id) {
        global $wpdb;
        
        $tracking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tracking_table} WHERE id = %d",
            $tracking_id
        ));
        
        if (!$tracking || $tracking->status !== 'active') {
            return false;
        }
        
        $preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->preventivi_table} WHERE id = %d",
            $tracking->preventivo_id
        ));
        
        if (!$preventivo) {
            error_log("[747Disco-Funnel] Preventivo #{$tracking->preventivo_id} non trovato");
            return false;
        }
        
        $next_step_number = $tracking->current_step + 1;
        $step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sequences_table} 
             WHERE funnel_type = %s AND step_number = %d AND active = 1",
            $tracking->funnel_type,
            $next_step_number
        ));
        
        if (!$step) {
            $this->complete_funnel($tracking_id);
            return true;
        }
        
        // INVIO EMAIL AL CLIENTE
        $email_sent = false;
        if ($step->email_enabled && !empty($step->email_body)) {
            $email_sent = $this->send_email_to_customer($preventivo, $step);
        }
        
        // INVIO EMAIL NOTIFICA WHATSAPP A TE
        $whatsapp_notif_sent = false;
        if ($step->whatsapp_enabled && !empty($step->whatsapp_text)) {
            $whatsapp_notif_sent = $this->send_whatsapp_notification($preventivo, $step, $tracking_id);
        }
        
        $emails_log = json_decode($tracking->emails_log, true) ?: array();
        $whatsapp_log = json_decode($tracking->whatsapp_log, true) ?: array();
        
        $emails_log[] = array(
            'step' => $next_step_number,
            'sent_at' => current_time('mysql'),
            'success' => $email_sent,
            'subject' => $step->email_subject
        );
        
        if ($step->whatsapp_enabled) {
            $whatsapp_log[] = array(
                'step' => $next_step_number,
                'notification_sent_at' => current_time('mysql'),
                'success' => $whatsapp_notif_sent,
                'status' => 'pending_manual_send'
            );
        }
        
        $next_step_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sequences_table} 
             WHERE funnel_type = %s AND step_number = %d AND active = 1",
            $tracking->funnel_type,
            $next_step_number + 1
        ));
        
        $next_send_at = null;
        if ($next_step_data) {
            $send_time = $next_step_data->send_time ?? '09:00:00';
            
            if ($tracking->funnel_type === 'pre_evento') {
                $next_send_at = date('Y-m-d', strtotime($preventivo->data_evento . ' ' . $next_step_data->days_offset . ' days')) . ' ' . $send_time;
            } else {
                $days_diff = $next_step_data->days_offset - $step->days_offset;
                $next_send_at = date('Y-m-d', strtotime("+{$days_diff} days")) . ' ' . $send_time;
            }
        }
        
        $wpdb->update(
            $this->tracking_table,
            array(
                'current_step' => $next_step_number,
                'last_sent_at' => current_time('mysql'),
                'next_send_at' => $next_send_at,
                'emails_log' => json_encode($emails_log),
                'whatsapp_log' => json_encode($whatsapp_log)
            ),
            array('id' => $tracking_id),
            array('%d', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        error_log("[747Disco-Funnel] Step {$next_step_number} inviato per tracking #{$tracking_id}");
        
        return true;
    }
    
    /**
     * Invia email al cliente - VERSIONE AGGIORNATA
     */
    private function send_email_to_customer($preventivo, $step) {
        $to = $preventivo->email;
        
        if (empty($to)) {
            error_log("[747Disco-Funnel] Email cliente mancante per preventivo #{$preventivo->id}");
            return false;
        }
        
        $subject = $this->replace_variables($step->email_subject, $preventivo);
        $body_content = $this->replace_variables($step->email_body, $preventivo);
        
        // Wrap HTML correttamente
        if (stripos($body_content, '<!doctype') !== false || stripos($body_content, '<html') !== false) {
            // E' gia un documento HTML completo
            $body_html = $body_content;
        } else {
            // Testo semplice o HTML parziale - wrappa con template base
            $body_html = $this->wrap_email_template($body_content);
        }
        
        // Headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: 747 Disco <eventi@747disco.it>',
            'Reply-To: eventi@747disco.it'
        );
        
        $sent = wp_mail($to, $subject, $body_html, $headers);
        
        if ($sent) {
            error_log("[747Disco-Funnel] Email inviata a {$to}");
        } else {
            error_log("[747Disco-Funnel] Errore invio email a {$to}");
        }
        
        return $sent;
    }
    
    /**
     * Wrappa contenuto email con template HTML base - NUOVO
     */
    private function wrap_email_template($content) {
        // Se contiene tag HTML, usalo cosi
        if (strip_tags($content) !== $content) {
            $body = $content;
        } else {
            // Altrimenti converti newline in <br>
            $body = nl2br($content);
        }
        
        return '<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>747 Disco</title>
</head>
<body style="margin:0;padding:0;background:#1a1a1a;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1a1a1a">
        <tr>
            <td align="center" style="padding:20px 12px">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;margin:0 auto;background:#ffffff;border-radius:12px">
                    <tr>
                        <td style="padding:30px">
                            ' . $body . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
    
    /**
     * Genera anteprima email HTML - NUOVO
     */
    public function preview_email($sequence_id, $preventivo_data = null) {
        global $wpdb;
        
        $step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sequences_table} WHERE id = %d",
            $sequence_id
        ));
        
        if (!$step) {
            return '<p>Sequenza non trovata</p>';
        }
        
        // Dati preventivo di esempio se non forniti
        if (!$preventivo_data) {
            $preventivo_data = (object) array(
                'id' => 123,
                'nome_cliente' => 'Mario Rossi',
                'nome_referente' => 'Mario',
                'cognome_referente' => 'Rossi',
                'tipo_evento' => 'Compleanno 18 anni',
                'data_evento' => date('Y-m-d', strtotime('+30 days')),
                'numero_invitati' => 80,
                'tipo_menu' => 'Menu 747',
                'importo_totale' => 2500.00,
                'acconto' => 500.00,
                'email' => 'cliente@example.com',
                'telefono' => '+39 347 1811119'
            );
        }
        
        $subject = $this->replace_variables($step->email_subject, $preventivo_data);
        $body_content = $this->replace_variables($step->email_body, $preventivo_data);
        
        // Wrap HTML correttamente
        if (stripos($body_content, '<!doctype') !== false || stripos($body_content, '<html') !== false) {
            $body_html = $body_content;
        } else {
            $body_html = $this->wrap_email_template($body_content);
        }
        
        return array(
            'subject' => $subject,
            'html' => $body_html
        );
    }
    
    /**
     * Test invio email - NUOVO
     */
    public function test_send_email($sequence_id, $test_email, $preventivo_data = null) {
        global $wpdb;
        
        if (!is_email($test_email)) {
            return array('success' => false, 'message' => 'Email non valida');
        }
        
        $step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sequences_table} WHERE id = %d",
            $sequence_id
        ));
        
        if (!$step) {
            return array('success' => false, 'message' => 'Sequenza non trovata');
        }
        
        // Dati preventivo di esempio se non forniti
        if (!$preventivo_data) {
            $preventivo_data = (object) array(
                'id' => 123,
                'nome_cliente' => 'Mario Rossi',
                'nome_referente' => 'Mario',
                'cognome_referente' => 'Rossi',
                'tipo_evento' => 'Compleanno 18 anni',
                'data_evento' => date('Y-m-d', strtotime('+30 days')),
                'numero_invitati' => 80,
                'tipo_menu' => 'Menu 747',
                'importo_totale' => 2500.00,
                'acconto' => 500.00,
                'email' => $test_email,
                'telefono' => '+39 347 1811119'
            );
        }
        
        $subject = '[TEST] ' . $this->replace_variables($step->email_subject, $preventivo_data);
        $body_content = $this->replace_variables($step->email_body, $preventivo_data);
        
        // Wrap HTML correttamente
        if (stripos($body_content, '<!doctype') !== false || stripos($body_content, '<html') !== false) {
            $body_html = $body_content;
        } else {
            $body_html = $this->wrap_email_template($body_content);
        }
        
        // Aggiungi banner TEST
        $test_banner = '<div style="background:#ff6b6b;color:#fff;padding:15px;text-align:center;font-weight:bold;border-radius:8px;margin-bottom:20px">
            QUESTA E\' UNA EMAIL DI TEST - Dati di esempio
        </div>';
        
        $body_html = str_replace('</body>', $test_banner . '</body>', $body_html);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: 747 Disco <eventi@747disco.it>',
            'Reply-To: eventi@747disco.it'
        );
        
        $sent = wp_mail($test_email, $subject, $body_html, $headers);
        
        if ($sent) {
            return array(
                'success' => true, 
                'message' => "Email di test inviata a {$test_email}"
            );
        } else {
            return array(
                'success' => false, 
                'message' => 'Errore durante l\'invio dell\'email di test'
            );
        }
    }
    
    /**
     * Invia email notifica WhatsApp
     */
    private function send_whatsapp_notification($preventivo, $step, $tracking_id) {
        // Recupera l'email dell'utente che ha creato il preventivo
        $to = 'eventi@747disco.it'; // Fallback default
        
        if (!empty($preventivo->created_by)) {
            $creator = get_userdata($preventivo->created_by);
            if ($creator && !empty($creator->user_email)) {
                $to = $creator->user_email;
                error_log("[747Disco-Funnel] Notifica WhatsApp inviata all'utente creatore: {$to} (ID: {$preventivo->created_by})");
            } else {
                error_log("[747Disco-Funnel] Utente creatore non trovato (ID: {$preventivo->created_by}), uso email default");
            }
        } else {
            error_log("[747Disco-Funnel] Campo created_by mancante, uso email default: {$to}");
        }
        
        $telefono = $preventivo->telefono;
        
        if (empty($telefono)) {
            error_log("[747Disco-Funnel] Telefono mancante per preventivo #{$preventivo->id}");
            return false;
        }
        
        $whatsapp_number = preg_replace('/[^0-9+]/', '', $telefono);
        if (substr($whatsapp_number, 0, 1) !== '+') {
            $whatsapp_number = '+39' . ltrim($whatsapp_number, '0');
        }
        
        $whatsapp_message = $this->replace_variables($step->whatsapp_text, $preventivo);
        
        // ✅ FIX EMOTICON v12.0.4: NON codificare le emoticon!
        // WhatsApp Web richiede emoticon grezze (UTF-8), non percent-encoded
        
        // Assicura che il testo sia UTF-8 corretto
        if (function_exists('mb_convert_encoding')) {
            $whatsapp_message = mb_convert_encoding($whatsapp_message, 'UTF-8', 'UTF-8');
        }
        
        // Codifica SOLO caratteri speciali, NON le emoticon
        $whatsapp_message_encoded = $whatsapp_message;
        
        // Sostituisci manualmente solo i caratteri che devono essere codificati
        $whatsapp_message_encoded = str_replace("\r\n", "\n", $whatsapp_message_encoded); // Normalizza a capo
        $whatsapp_message_encoded = str_replace("\r", "", $whatsapp_message_encoded);     // Rimuovi CR
        $whatsapp_message_encoded = str_replace("\n", "%0A", $whatsapp_message_encoded);  // A capo → %0A
        $whatsapp_message_encoded = str_replace(" ", "%20", $whatsapp_message_encoded);   // Spazio → %20
        $whatsapp_message_encoded = str_replace("!", "%21", $whatsapp_message_encoded);   // ! → %21
        $whatsapp_message_encoded = str_replace(":", "%3A", $whatsapp_message_encoded);   // : → %3A
        $whatsapp_message_encoded = str_replace("/", "%2F", $whatsapp_message_encoded);   // / → %2F
        $whatsapp_message_encoded = str_replace("€", "%E2%82%AC", $whatsapp_message_encoded); // € → %E2%82%AC
        $whatsapp_message_encoded = str_replace(",", "%2C", $whatsapp_message_encoded);   // , → %2C
        
        // EMOTICON 🎉 💰 📅 etc. → RESTANO GREZZE (non codificate!)
        
        error_log("[747Disco-Funnel] WhatsApp message encoding: emoticon RAW (non percent-encoded)");
        
        // Crea URL WhatsApp valido
        $whatsapp_url = "https://wa.me/{$whatsapp_number}?text={$whatsapp_message_encoded}";
        
        $mark_sent_url = admin_url('admin.php?page=disco747-funnel&action=mark_whatsapp_sent&tracking=' . $tracking_id . '&step=' . $step->step_number);
        
        // Prepara info utente per il corpo email
        $creator_name = 'Admin';
        if (!empty($preventivo->created_by)) {
            $creator = get_userdata($preventivo->created_by);
            if ($creator) {
                $creator_name = $creator->display_name ?: $creator->user_login;
            }
        }
        
        $subject = "WhatsApp da inviare - Funnel Step {$step->step_number}";
        
        $body = "
        <div style='max-width: 600px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 10px 10px 0 0;'>
                <h2 style='color: white; margin: 0;'>Notifica Funnel WhatsApp</h2>
                <p style='color: #e0e0e0; margin: 5px 0 0 0; font-size: 14px;'>Preventivo creato da: {$creator_name}</p>
            </div>
            
            <div style='background: white; padding: 25px; border: 2px solid #e9ecef; border-radius: 0 0 10px 10px;'>
                <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                    <strong>Cliente:</strong> {$preventivo->nome_cliente}<br>
                    <strong>Preventivo:</strong> #{$preventivo->id}<br>
                    <strong>Telefono:</strong> {$telefono}<br>
                    <strong>Tipo Evento:</strong> {$preventivo->tipo_evento}<br>
                    <strong>Data Evento:</strong> " . date('d/m/Y', strtotime($preventivo->data_evento)) . "
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$whatsapp_url}' 
                       style='background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); 
                              color: white; 
                              padding: 18px 40px; 
                              text-decoration: none; 
                              border-radius: 30px; 
                              font-weight: bold; 
                              font-size: 18px;
                              display: inline-block;
                              box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);'>
                        INVIA WHATSAPP ORA
                    </a>
                </div>
                
                <div style='background: #e7f3ff; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; margin: 20px 0;'>
                    <strong style='color: #0056b3;'>Preview messaggio:</strong><br><br>
                    <em style='color: #495057;'>" . nl2br(htmlspecialchars($whatsapp_message)) . "</em>
                </div>
                
                <hr style='border: none; border-top: 1px solid #e9ecef; margin: 30px 0;'>
                
                <div style='text-align: center;'>
                    <p style='color: #6c757d; font-size: 14px;'>Dopo aver inviato il WhatsApp, clicca qui per segnarlo:</p>
                    <a href='{$mark_sent_url}' 
                       style='color: #28a745; text-decoration: none; font-weight: bold;'>
                        Segna come Inviato
                    </a>
                </div>
            </div>
            
            <div style='background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 10px 10px;'>
                <p style='margin: 0;'>Funnel Step: {$step->step_number} - {$step->step_name}</p>
            </div>
        </div>
        ";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: 747 Disco Funnel <noreply@gestionale.747disco.it>'
        );
        
        $sent = wp_mail($to, $subject, $body, $headers);
        
        if ($sent) {
            error_log("[747Disco-Funnel] Notifica WhatsApp inviata a {$to}");
        } else {
            error_log("[747Disco-Funnel] Errore invio notifica WhatsApp");
        }
        
        return $sent;
    }
    
    /**
     * Sostituisce variabili nel testo
     */
    private function replace_variables($text, $preventivo) {
        $variables = array(
            '{{nome_referente}}' => $preventivo->nome_referente ?: $preventivo->nome_cliente,
            '{{cognome_referente}}' => $preventivo->cognome_referente ?: '',
            '{{nome}}' => $preventivo->nome_referente ?: $preventivo->nome_cliente,
            '{{cognome}}' => $preventivo->cognome_referente ?: '',
            '{{nome_cliente}}' => $preventivo->nome_cliente,
            '{{tipo_evento}}' => $preventivo->tipo_evento,
            '{{data_evento}}' => date('d/m/Y', strtotime($preventivo->data_evento)),
            '{{numero_invitati}}' => $preventivo->numero_invitati,
            '{{tipo_menu}}' => $preventivo->tipo_menu,
            '{{importo_totale}}' => number_format($preventivo->importo_totale, 2, ',', '.'),
            '{{acconto}}' => number_format($preventivo->acconto, 2, ',', '.'),
            '{{preventivo_id}}' => $preventivo->id,
            '{{telefono_sede}}' => '+39 347 181 1119',
            '{{email_sede}}' => 'eventi@747disco.it'
        );
        
        return str_replace(array_keys($variables), array_values($variables), $text);
    }
    
    /**
     * Completa un funnel
     */
    public function complete_funnel($tracking_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->tracking_table,
            array(
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
                'next_send_at' => null
            ),
            array('id' => $tracking_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        error_log("[747Disco-Funnel] Funnel completato (Tracking ID: {$tracking_id})");
        
        return true;
    }
    
    /**
     * Stoppa un funnel
     */
    public function stop_funnel($preventivo_id, $funnel_type) {
        global $wpdb;
        
        $wpdb->update(
            $this->tracking_table,
            array(
                'status' => 'stopped',
                'completed_at' => current_time('mysql'),
                'next_send_at' => null
            ),
            array(
                'preventivo_id' => $preventivo_id,
                'funnel_type' => $funnel_type
            ),
            array('%s', '%s', '%s'),
            array('%d', '%s')
        );
        
        error_log("[747Disco-Funnel] Funnel stoppato per preventivo #{$preventivo_id}");
        
        return true;
    }
    
    /**
     * Ottieni tracking attivi
     */
    public function get_active_trackings($funnel_type = null) {
        global $wpdb;
        
        $sql = "SELECT t.*, p.nome_cliente, p.email, p.telefono, p.tipo_evento, p.data_evento
                FROM {$this->tracking_table} t
                LEFT JOIN {$this->preventivi_table} p ON t.preventivo_id = p.id
                WHERE t.status = 'active'";
        
        if ($funnel_type) {
            $sql .= $wpdb->prepare(" AND t.funnel_type = %s", $funnel_type);
        }
        
        $sql .= " ORDER BY t.next_send_at ASC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Ottieni tracking da inviare ORA
     */
    public function get_pending_sends() {
        global $wpdb;
        
        // ✅ FIX: Invia email funnel SOLO a preventivi in stato "attivo"
        // Esclude: confermati, annullati, e qualsiasi altro stato
        return $wpdb->get_results("
            SELECT t.*, p.nome_cliente, p.email, p.telefono, p.stato
            FROM {$this->tracking_table} t
            LEFT JOIN {$this->preventivi_table} p ON t.preventivo_id = p.id
            WHERE t.status = 'active' 
              AND t.next_send_at IS NOT NULL
              AND t.next_send_at <= NOW()
              AND p.stato = 'attivo'
            ORDER BY t.next_send_at ASC
        ");
    }
}