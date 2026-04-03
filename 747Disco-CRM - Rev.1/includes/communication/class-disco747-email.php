<?php
/**
 * Email Handler Class - 747 Disco CRM
 * VERSIONE v12.0.0 - FIX HTML EMAIL PRESERVATION
 * 
 * @package    Disco747_CRM
 * @subpackage Communication
 * @since      12.0.0
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Communication;

use Disco747_CRM\Core\Disco747_Config;
use Exception;

defined('ABSPATH') || exit;

class Disco747_Email {

    private $config;
    private $smtp_config;
    private $debug_mode;
    private $delivery_log = array();

    public function __construct() {
        $this->config = Disco747_Config::get_instance();
        $this->debug_mode = $this->config->get('debug_mode', false);
        
        $this->smtp_config = array(
            'enabled' => $this->config->get('smtp_enabled', false),
            'host' => $this->config->get('smtp_host', ''),
            'port' => $this->config->get('smtp_port', 587),
            'username' => $this->config->get('smtp_username', ''),
            'password' => $this->config->get('smtp_password', ''),
            'encryption' => $this->config->get('smtp_encryption', 'tls'),
            'from_name' => $this->config->get('email_from_name', '747 Disco'),
            'from_email' => $this->config->get('email_from_address', 'info@gestionale.747disco.it')
        );
        
        $this->setup_hooks();
        $this->log('✅ Email Handler v12.0.0 inizializzato');
    }

    private function setup_hooks() {
        if ($this->smtp_config['enabled']) {
            add_action('phpmailer_init', array($this, 'configure_smtp'));
        }
        add_action('wp_mail_failed', array($this, 'on_mail_failure'));
    }

    public function send_preventivo_email($preventivo_data, $pdf_path = null, $options = array()) {
        $this->log('Invio email preventivo per: ' . ($preventivo_data['nome_referente'] ?? 'N/A'));

        try {
            $template_id = isset($options['template_id']) ? intval($options['template_id']) : 1;
            $this->log('Template ID: ' . $template_id);
            
            $email_data = $this->prepare_email_data($preventivo_data, $options, $template_id);
            $email_content = $this->generate_email_content($email_data, $template_id);
            
            $attachments = array();
            if ($pdf_path && file_exists($pdf_path)) {
                $attachments[] = $pdf_path;
                $this->log('PDF allegato: ' . basename($pdf_path));
            }
            
            $this->log('Invio wp_mail con ' . count($attachments) . ' allegati');
            $sent = wp_mail(
                $email_data['recipient_email'],
                $email_data['subject'],
                $email_content,
                $this->get_email_headers(),
                $attachments
            );
            
            $this->log_delivery($email_data, $sent, $attachments);
            
            if ($sent) {
                $this->log('✅ Email inviata a: ' . $email_data['recipient_email']);
            } else {
                throw new Exception('Errore invio email');
            }
            
            return $sent;
            
        } catch (Exception $e) {
            $this->log('❌ Errore: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    private function prepare_email_data($preventivo_data, $options = array(), $template_id = 1) {
        $recipient_email = $preventivo_data['mail'] ?? '';
        if (!is_email($recipient_email)) {
            throw new Exception('Email non valida: ' . $recipient_email);
        }
        
        $extra1_importo = floatval($preventivo_data['extra1_importo'] ?? 0);
        $extra2_importo = floatval($preventivo_data['extra2_importo'] ?? 0);
        $extra3_importo = floatval($preventivo_data['extra3_importo'] ?? 0);

        $extra_totale = 0;
        if (!empty($preventivo_data['extra1']) && $extra1_importo > 0) {
            $extra_totale += $extra1_importo;
        }
        if (!empty($preventivo_data['extra2']) && $extra2_importo > 0) {
            $extra_totale += $extra2_importo;
        }
        if (!empty($preventivo_data['extra3']) && $extra3_importo > 0) {
            $extra_totale += $extra3_importo;
        }

        $importo_preventivo = floatval($preventivo_data['importo_preventivo'] ?? $preventivo_data['importo_totale'] ?? 0);
        $acconto = floatval($preventivo_data['acconto'] ?? 0);

        $sconti_menu = array(
            'Menu 7' => 400,
            'Menu 7-4' => 500,
            'Menu 74' => 500,
            'Menu 7-4-7' => 600,
            'Menu 747' => 600
        );
        $tipo_menu = $preventivo_data['tipo_menu'] ?? 'Menu 7';
        $sconto_menu = $sconti_menu[$tipo_menu] ?? 400;

        $totale = $importo_preventivo + $extra_totale;
        $saldo = $totale - $acconto;
        
        $template_vars = array(
            'nome' => $preventivo_data['nome_referente'] ?? '',
            'cognome' => $preventivo_data['cognome_referente'] ?? '',
            'nome_completo' => trim(($preventivo_data['nome_referente'] ?? '') . ' ' . ($preventivo_data['cognome_referente'] ?? '')),
            'email' => $preventivo_data['mail'] ?? '',
            'telefono' => $preventivo_data['telefono'] ?? $preventivo_data['cellulare'] ?? '',
            'data_evento' => $this->format_date($preventivo_data['data_evento'] ?? ''),
            'tipo_evento' => $preventivo_data['tipo_evento'] ?? '',
            'numero_invitati' => $preventivo_data['numero_invitati'] ?? 0,
            'orario_inizio' => $this->format_time($preventivo_data['orario_inizio'] ?? '20:30'),
            'orario_fine' => $this->format_time($preventivo_data['orario_fine'] ?? '01:30'),
            'tipo_menu' => $preventivo_data['tipo_menu'] ?? '',
            'menu' => $preventivo_data['tipo_menu'] ?? '',
            'importo_preventivo' => $this->format_currency($importo_preventivo),
            'importo' => $this->format_currency($totale),
            'sconto_allinclusive_formatted' => $this->format_currency($sconto_menu),
            'totale' => $this->format_currency($totale),
            'acconto' => $this->format_currency($acconto),
            'saldo' => $this->format_currency($saldo),
            'preventivo_id' => $preventivo_data['preventivo_id'] ?? '',
            'extra1' => $preventivo_data['extra1'] ?? '',
            'extra2' => $preventivo_data['extra2'] ?? '',
            'extra3' => $preventivo_data['extra3'] ?? '',
            'omaggio1' => $preventivo_data['omaggio1'] ?? '',
            'omaggio2' => $preventivo_data['omaggio2'] ?? '',
            'omaggio3' => $preventivo_data['omaggio3'] ?? '',
            'extra1_importo_formatted' => $this->format_currency($extra1_importo),
            'extra2_importo_formatted' => $this->format_currency($extra2_importo),
            'extra3_importo_formatted' => $this->format_currency($extra3_importo),
        );
        
        $subject = get_option('disco747_email_subject_' . $template_id, 'Il tuo preventivo 747 Disco è pronto!');
        
        return array(
            'recipient_email' => $recipient_email,
            'subject' => $subject,
            'template_vars' => $template_vars,
            'template_id' => $template_id,
            'options' => $options
        );
    }

    private function generate_email_content($email_data, $template_id = 1) {
        $custom_template = get_option('disco747_email_template_' . $template_id, '');
        
        if (!empty($custom_template)) {
            $html = $this->replace_placeholders($custom_template, $email_data['template_vars']);
            $this->log('✅ Template personalizzato compilato');
            return $html;
        }
        
        return $this->get_default_template($email_data['template_vars']);
    }

    private function replace_placeholders($template, $vars) {
        foreach ($vars as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, esc_html($value), $template);
        }
        
        return $template;
    }

    private function get_default_template($vars) {
        $nome = esc_html($vars['nome'] ?? '');
        $data_evento = esc_html($vars['data_evento']);
        $tipo_evento = esc_html($vars['tipo_evento']);
        $numero_invitati = esc_html($vars['numero_invitati']);
        $tipo_menu = esc_html($vars['tipo_menu']);
        $orario_inizio = esc_html($vars['orario_inizio']);
        $orario_fine = esc_html($vars['orario_fine']);
        $importo = esc_html($vars['totale']);
        
        $omaggi_html = '';
        if (!empty($vars['omaggio1'])) {
            $omaggi_html .= '<li>🎁 ' . esc_html($vars['omaggio1']) . '</li>';
        }
        if (!empty($vars['omaggio2'])) {
            $omaggi_html .= '<li>🎁 ' . esc_html($vars['omaggio2']) . '</li>';
        }
        if (!empty($vars['omaggio3'])) {
            $omaggi_html .= '<li>🎁 ' . esc_html($vars['omaggio3']) . '</li>';
        }
        
        $extra_html = '';
        if (!empty($vars['extra1'])) {
            $extra_html .= '<li>💰 ' . esc_html($vars['extra1']) . ' - ' . esc_html($vars['extra1_importo_formatted']) . '</li>';
        }
        if (!empty($vars['extra2'])) {
            $extra_html .= '<li>💰 ' . esc_html($vars['extra2']) . ' - ' . esc_html($vars['extra2_importo_formatted']) . '</li>';
        }
        if (!empty($vars['extra3'])) {
            $extra_html .= '<li>💰 ' . esc_html($vars['extra3']) . ' - ' . esc_html($vars['extra3_importo_formatted']) . '</li>';
        }
        
        return '<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>747 Disco - Preventivo</title>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,sans-serif">
    <div style="max-width:600px;margin:0 auto;background:#1a1a1a">
        <div style="background:linear-gradient(135deg,#c28a4d 0%,#f4d03f 100%);padding:40px 20px;text-align:center">
            <h1 style="margin:0;font-size:36px;color:#000;text-transform:uppercase">747 DISCO</h1>
            <p style="margin:10px 0 0;font-size:14px;color:rgba(0,0,0,0.7)">La tua festa, la nostra passione</p>
        </div>
        
        <div style="padding:30px 25px;background:#ffffff;color:#333">
            <p style="font-size:16px;line-height:1.6;margin:0 0 20px">
                Gentile <strong>' . $nome . '</strong>,
            </p>
            
            <p style="font-size:16px;line-height:1.6;margin-bottom:25px">
                Siamo lieti di inviarle il preventivo per il suo <strong>' . $tipo_evento . '</strong> del <strong>' . $data_evento . '</strong>.
            </p>
            
            <div style="background:#f8f9fa;padding:20px;border-radius:12px;margin:25px 0;border-left:4px solid #c28a4d">
                <h2 style="margin:0 0 15px;color:#c28a4d;font-size:20px">📋 Dettagli Evento</h2>
                <table style="width:100%">
                    <tr>
                        <td style="padding:8px 0;color:#666;width:40%">🗓️ Data</td>
                        <td style="padding:8px 0;color:#333;font-weight:600">' . $data_evento . '</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#666">⏰ Orario</td>
                        <td style="padding:8px 0;color:#333;font-weight:600">' . $orario_inizio . ' - ' . $orario_fine . '</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#666">👥 Invitati</td>
                        <td style="padding:8px 0;color:#333;font-weight:600">' . $numero_invitati . ' persone</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;color:#666">🍽️ Menu</td>
                        <td style="padding:8px 0;color:#333;font-weight:600">' . $tipo_menu . '</td>
                    </tr>
                </table>
            </div>';
        
        if (!empty($omaggi_html)) {
            $html .= '<div style="background:#e8f5e9;padding:20px;border-radius:12px;margin:25px 0;border-left:4px solid #4caf50">
                <h3 style="margin:0 0 10px;color:#2e7d32;font-size:18px">🎁 Omaggi Inclusi</h3>
                <ul style="list-style:none;padding:0;margin:0">' . $omaggi_html . '</ul>
            </div>';
        }
        
        if (!empty($extra_html)) {
            $html .= '<div style="background:#fff3e0;padding:20px;border-radius:12px;margin:25px 0;border-left:4px solid #ff9800">
                <h3 style="margin:0 0 10px;color:#e65100;font-size:18px">💰 Extra Selezionati</h3>
                <ul style="list-style:none;padding:0;margin:0">' . $extra_html . '</ul>
            </div>';
        }
        
        $html .= '
            <div style="background:linear-gradient(135deg,#2b1e1a 0%,#3c3c3c 100%);padding:25px;border-radius:12px;margin:25px 0;text-align:center">
                <p style="margin:0;color:rgba(255,255,255,0.8);font-size:14px">Investimento Totale</p>
                <p style="margin:10px 0;color:#c28a4d;font-size:42px;font-weight:bold">' . $importo . '</p>
            </div>
            
            <p style="color:#333;line-height:1.6;margin-top:25px">
                Il preventivo completo è allegato in PDF. Siamo a disposizione per qualsiasi chiarimento.
            </p>
        </div>
        
        <div style="text-align:center;color:#666;font-size:12px;padding:20px">
            <p style="margin:5px 0"><strong style="color:#c28a4d">747 DISCO</strong></p>
            <p style="margin:15px 0 5px">📧 info@gestionale.747disco.it | 📞 +39 333 123 4567</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    private function get_email_headers() {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->smtp_config['from_name'] . ' <' . $this->smtp_config['from_email'] . '>'
        );
        
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->user_email) {
            $headers[] = 'Bcc: ' . $current_user->user_email;
            $this->log('✅ BCC: ' . $current_user->user_email);
        }
        
        return $headers;
    }

    public function configure_smtp($phpmailer) {
        if (!$this->smtp_config['enabled']) {
            return;
        }
        
        $phpmailer->isSMTP();
        $phpmailer->Host = $this->smtp_config['host'];
        $phpmailer->Port = $this->smtp_config['port'];
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $this->smtp_config['username'];
        $phpmailer->Password = $this->smtp_config['password'];
        $phpmailer->SMTPSecure = $this->smtp_config['encryption'];
    }

    public function on_mail_failure($wp_error) {
        $this->log("Errore: " . $wp_error->get_error_message(), 'ERROR');
    }

    private function format_date($date_string) {
        if (empty($date_string)) return '';
        
        $timestamp = strtotime($date_string);
        if (!$timestamp) return $date_string;
        
        $mesi = array(1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
            5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
            9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
        );
        
        $giorno = date('d', $timestamp);
        $mese = $mesi[intval(date('m', $timestamp))];
        $anno = date('Y', $timestamp);
        
        return "$giorno $mese $anno";
    }

    private function format_time($time_string) {
        if (empty($time_string)) return '';
        return substr($time_string, 0, 5);
    }

    private function format_currency($amount) {
        return '€' . number_format(floatval($amount), 2, ',', '.');
    }

    private function log_delivery($email_data, $success, $attachments = array()) {
        $this->delivery_log[] = array(
            'timestamp' => current_time('mysql'),
            'recipient' => $email_data['recipient_email'],
            'success' => $success,
            'attachments' => count($attachments)
        );
    }

    private function log($message, $level = 'INFO') {
        error_log("[747Disco-Email] [{$level}] {$message}");
    }
}