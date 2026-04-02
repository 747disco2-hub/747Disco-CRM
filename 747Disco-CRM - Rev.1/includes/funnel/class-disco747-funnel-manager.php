<?php
/**
 * Funnel Manager - 747 Disco CRM - VERSIONE AGGIORNATA
 * Gestisce la logica del funnel marketing con supporto HTML e anteprima
 * 
 * FIX PRINCIPALI:
 * - Protezione contro preventivi duplicati nel funnel
 * - ON DUPLICATE KEY UPDATE per evitare errori
 * - Doppio controllo: database + logica applicativa
 * 
 * @package    Disco747_CRM
 * @subpackage Funnel
 * @version    2.2.0
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
     * ✅ VERSIONE CORRETTA: Usa UNIQUE KEY nel database per evitare duplicati
     */
    public function start_funnel($preventivo_id, $funnel_type = 'pre_conferma') {
        global $wpdb;
        
        // ✅ DOPPIO CONTROLLO 1: Verifica che non esista già un tracking attivo
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->tracking_table} 
             WHERE preventivo_id = %d AND funnel_type = %s AND status = 'active'",
            $preventivo_id,
            $funnel_type
        ));
        
        if ($existing) {
            error_log("[747Disco-Funnel] ⚠️ Funnel ATTIVO già esistente per preventivo #{$preventivo_id} - Skip");
            return false;
        }
        
        $first_step = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sequences_table} 
             WHERE funnel_type = %s AND active = 1 
             ORDER BY step_number ASC LIMIT 1",
            $funnel_type
        ));
        
        if (!$first_step) {
            error_log("[747Disco-Funnel] ⚠️ Nessuna sequenza attiva trovata per {$funnel_type}");
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
        
        // ✅ PROTEZIONE 2: ON DUPLICATE KEY UPDATE per evitare errori di inserimento
        // La UNIQUE KEY (preventivo_id, funnel_type) garantisce un solo tracking per preventivo+funnel
        $sql = $wpdb->prepare(
            "INSERT INTO {$this->tracking_table} 
            (preventivo_id, funnel_type, current_step, status, started_at, next_send_at, emails_log, whatsapp_log)
            VALUES (%d, %s, %d, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE 
                status = IF(status = 'active', status, 'active'),
                started_at = %s,
                next_send_at = %s",
            $preventivo_id,
            $funnel_type,
            0,
            'active',
            current_time('mysql'),
            $next_send_at,
            json_encode(array()),
            json_encode(array()),
            current_time('mysql'),
            $next_send_at
        );
        
        $result = $wpdb->query($sql);
        
        if ($result !== false) {
            // Recupera il tracking (sia nuovo che esistente)
            $tracking = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$this->tracking_table} 
                 WHERE preventivo_id = %d AND funnel_type = %s",
                $preventivo_id,
                $funnel_type
            ));
            
            if ($tracking) {
                $tracking_id = $tracking->id;
                error_log("[747Disco-Funnel] ✅ Funnel {$funnel_type} pronto per preventivo #{$preventivo_id} (Tracking ID: {$tracking_id})");
                
                // Se primo step ha offset 0, invia subito
                if ($first_step->days_offset == 0) {
                    $this->send_next_step($tracking_id);
                }
                
                return $tracking_id;
            }
        }
        
        error_log("[747Disco-Funnel] ❌ Errore inserimento/update tracking per preventivo #{$preventivo_id}");
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
            error_log("[747Disco-Funnel] ⚠️ Tracking #{$tracking_id} non attivo");
            return false;
        }
        
        $preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->preventivi_table} WHERE id = %d",
            $tracking->preventivo_id
        ));
        
        if (!$preventivo) {
            error_log("[747Disco-Funnel] ⚠️ Preventivo #{$tracking->preventivo_id} non trovato");
            return false;
        }
        
        // ✅ Verifica stato corretto per tipo di funnel
        if ($tracking->funnel_type === 'pre_conferma' && $preventivo->stato !== 'attivo') {
            error_log("[747Disco-Funnel] ⚠️ SKIP: Preventivo #{$preventivo->id} non attivo - Funnel stoppato");
            $this->stop_funnel($tracking->preventivo_id, 'pre_conferma');
            return false;
        }
        
        if ($tracking->funnel_type === 'pre_evento' && $preventivo->stato !== 'confermato') {
            error_log("[747Disco-Funnel] ⚠️ SKIP: Preventivo #{$preventivo->id} non confermato - Funnel stoppato");
            $this->stop_funnel($tracking->preventivo_id, 'pre_evento');
            return false;
        }
        
        error_log("[747Disco-Funnel] ✅ Verifica stato: Preventivo #{$preventivo->id} - Stato: '{$preventivo->stato}'");
        
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
        
        // INVIO NOTIFICA WHATSAPP
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
        
        // Se non c'è un prossimo step, completa il funnel
        if (!$next_step_data) {
            $wpdb->update(
                $this->tracking_table,
                array(
                    'current_step' => $next_step_number,
                    'last_sent_at' => current_time('mysql'),
                    'emails_log' => json_encode($emails_log),
                    'whatsapp_log' => json_encode($whatsapp_log),
                    'status' => 'completed',
                    'completed_at' => current_time('mysql'),
                    'next_send_at' => null
                ),
                array('id' => $tracking_id),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            error_log("[747Disco-Funnel] ✅ Step {$next_step_number} inviato (ultimo) - Funnel completato");
            return true;
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
        
        error_log("[747Disco-Funnel] ✅ Step {$next_step_number} inviato per tracking #{$tracking_id}");
        
        return true;
    }
    
    /**
     * Invia email al cliente
     */
    private function send_email_to_customer($preventivo, $step) {
        $to = $preventivo->email;
        
        if (empty($to)) {
            error_log("[747Disco-Funnel] ⚠️ Email cliente mancante per preventivo #{$preventivo->id}");
            return false;
        }
        
        $subject = $this->replace_variables($step->email_subject, $preventivo);
        $body_content = $this->replace_variables($step->email_body, $preventivo);
        
        if (stripos($body_content, '<!doctype') !== false || stripos($body_content, '<html') !== false) {
            $body_html = $body_content;
        } else {
            $body_html = $this->wrap_email_template($body_content);
        }
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: 747 Disco <eventi@747disco.it>',
            'Reply-To: eventi@747disco.it'
        );
        
        $sent = wp_mail($to, $subject, $body_html, $headers);
        
        if ($sent) {
            error_log("[747Disco-Funnel] ✅ Email inviata a {$to}");
        } else {
            error_log("[747Disco-Funnel] ❌ Errore invio email a {$to}");
        }
        
        return $sent;
    }
    
    /**
     * Wrappa contenuto email con template HTML
     */
    private function wrap_email_template($content) {
        if (strip_tags($content) !== $content) {
            $body = $content;
        } else {
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
     * Invia notifica WhatsApp
     */
    private function send_whatsapp_notification($preventivo, $step, $tracking_id) {
        $to = 'eventi@747disco.it';
        
        if (!empty($preventivo->created_by)) {
            $creator = get_userdata($preventivo->created_by);
            if ($creator && !empty($creator->user_email)) {
                $to = $creator->user_email;
            }
        }
        
        $telefono = $preventivo->telefono;
        
        if (empty($telefono)) {
            error_log("[747Disco-Funnel] ⚠️ Telefono mancante per preventivo #{$preventivo->id}");
            return false;
        }
        
        $whatsapp_message = $this->replace_variables($step->whatsapp_text, $preventivo);
        
        if (function_exists('mb_convert_encoding')) {
            $whatsapp_message = mb_convert_encoding($whatsapp_message, 'UTF-8', 'auto');
        }
        
        $whatsapp_number_clean = preg_replace('/[^0-9]/', '', $telefono);
        if (substr($whatsapp_number_clean, 0, 2) !== '39') {
            $whatsapp_number_clean = '39' . ltrim($whatsapp_number_clean, '0');
        }
        
        $whatsapp_url = 'https://api.whatsapp.com/send?phone=' . $whatsapp_number_clean . '&text=' . rawurlencode($whatsapp_message);
        
        $subject = "WhatsApp da inviare - Funnel Step {$step->step_number}";
        
        $body = "<div style='max-width: 600px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 10px 10px 0 0;'>
                <h2 style='color: white; margin: 0;'>Notifica Funnel WhatsApp</h2>
            </div>
            <div style='background: white; padding: 25px; border: 2px solid #e9ecef; border-radius: 0 0 10px 10px;'>
                <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                    <strong>Cliente:</strong> {$preventivo->nome_cliente}<br>
                    <strong>Preventivo:</strong> #{$preventivo->id}<br>
                    <strong>Telefono:</strong> {$telefono}
                </div>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$whatsapp_url}' style='background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; padding: 18px 40px; text-decoration: none; border-radius: 30px; font-weight: bold; display: inline-block;'>
                        INVIA WHATSAPP ORA
                    </a>
                </div>
                <div style='background: #e7f3ff; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff;'>
                    <strong style='color: #0056b3;'>Preview messaggio:</strong><br><br>
                    <em style='color: #495057;'>" . nl2br(htmlspecialchars($whatsapp_message)) . "</em>
                </div>
            </div>
        </div>";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: 747 Disco Funnel <noreply@gestionale.747disco.it>'
        );
        
        $sent = wp_mail($to, $subject, $body, $headers);
        
        if ($sent) {
            error_log("[747Disco-Funnel] ✅ Notifica WhatsApp inviata");
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
        
        error_log("[747Disco-Funnel] ✅ Funnel completato (Tracking ID: {$tracking_id})");
        
        return true;
    }
    
    /**
     * Stoppa un funnel
     */
    public function stop_funnel($preventivo_id, $funnel_type) {
        global $wpdb;
        
        $updated = $wpdb->update(
            $this->tracking_table,
            array(
                'status' => 'stopped',
                'completed_at' => current_time('mysql'),
                'next_send_at' => null
            ),
            array(
                'preventivo_id' => $preventivo_id,
                'funnel_type' => $funnel_type,
                'status' => 'active'
            ),
            array('%s', '%s', '%s'),
            array('%d', '%s', '%s')
        );
        
        if ($updated) {
            error_log("[747Disco-Funnel] ✅ Funnel stoppato per preventivo #{$preventivo_id}");
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottieni tracking attivi
     */
    public function get_active_trackings($funnel_type = null) {
        global $wpdb;
        
        $sql = "SELECT t.*, p.nome_cliente, p.email, p.telefono, p.tipo_evento, p.data_evento, p.stato
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
        
        return $wpdb->get_results("
            SELECT t.*, p.nome_cliente, p.email, p.telefono, p.stato
            FROM {$this->tracking_table} t
            LEFT JOIN {$this->preventivi_table} p ON t.preventivo_id = p.id
            WHERE t.status = 'active' 
              AND t.next_send_at IS NOT NULL
              AND t.next_send_at <= NOW()
              AND (
                (t.funnel_type = 'pre_conferma' AND p.stato = 'attivo')
                OR (t.funnel_type = 'pre_evento' AND p.stato = 'confermato')
              )
            ORDER BY t.next_send_at ASC
        ");
    }
    
    /**
     * Debug: Report tracking
     */
    public function debug_tracking_report() {
        global $wpdb;
        
        error_log("[747Disco-Funnel] 🔍 === DEBUG REPORT TRACKING ATTIVI ===");
        
        $report = $wpdb->get_results("
            SELECT 
                t.funnel_type,
                p.stato as preventivo_stato,
                COUNT(*) as count
            FROM {$this->tracking_table} t
            LEFT JOIN {$this->preventivi_table} p ON t.preventivo_id = p.id
            WHERE t.status = 'active'
            GROUP BY t.funnel_type, p.stato
            ORDER BY t.funnel_type, p.stato
        ");
        
        foreach ($report as $row) {
            $stato = $row->preventivo_stato ?? 'NULL';
            error_log("[747Disco-Funnel]   Funnel: {$row->funnel_type}, Stato: {$stato}, Count: {$row->count}");
        }
        
        error_log("[747Disco-Funnel] === FINE DEBUG REPORT ===");
    }
}