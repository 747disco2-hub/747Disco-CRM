<?php
/**
 * Unconfirmed Quotes Report - 747 Disco CRM
 *
 * Invia periodicamente (frequenza configurabile) una email a ciascun utente
 * che ha generato preventivi, con la lista dei preventivi non ancora confermati
 * (stato = 'attivo'), includendo link per chiamare e scrivere su WhatsApp.
 *
 * Le impostazioni sono gestite dalla pagina admin "Report Preventivi".
 *
 * @package    Disco747_CRM
 * @subpackage Reports
 * @since      12.1.0
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Weekly_Report {

    const CRON_HOOK = 'disco747_weekly_unconfirmed_report';

    public function __construct() {
        add_action(self::CRON_HOOK, array($this, 'send_reports'));
        add_filter('cron_schedules', array($this, 'add_custom_interval'));
    }

    /**
     * Registra l'intervallo cron dinamico basato sull'opzione disco747_report_frequency_days.
     */
    public function add_custom_interval($schedules) {
        $days = intval(get_option('disco747_report_frequency_days', 3));
        $schedules['disco747_report_interval'] = array(
            'interval' => $days * DAY_IN_SECONDS,
            'display'  => sprintf(__('Ogni %d giorni (747 Disco Report)', 'disco747'), $days),
        );
        return $schedules;
    }

    // -------------------------------------------------------------------------
    // Attivazione / Disattivazione cron
    // -------------------------------------------------------------------------

    /**
     * Schedula (o ri-schedula) il cron in base alle impostazioni correnti.
     * Chiamare da activate_plugin() e ogni volta che le impostazioni cambiano.
     */
    public static function activate() {
        $existing = wp_next_scheduled(self::CRON_HOOK);
        if ($existing) {
            wp_unschedule_event($existing, self::CRON_HOOK);
        }
        wp_clear_scheduled_hook(self::CRON_HOOK);

        if (!intval(get_option('disco747_report_enabled', 1))) {
            error_log('[747Disco-Report] ℹ️ Report disabilitato dalle impostazioni. Cron non schedulato.');
            return;
        }

        $next_run = self::next_run_at();
        wp_schedule_event($next_run, 'disco747_report_interval', self::CRON_HOOK);
        error_log('[747Disco-Report] ✅ Cron schedulato. Prossimo invio: ' . date('Y-m-d H:i:s', $next_run));
    }

    /**
     * Rimuove il cron.
     * Chiamare da deactivate_plugin().
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        wp_clear_scheduled_hook(self::CRON_HOOK);
        error_log('[747Disco-Report] ✅ Cron disattivato.');
    }

    // -------------------------------------------------------------------------
    // Invio report
    // -------------------------------------------------------------------------

    /**
     * Eseguito dal cron: recupera i preventivi non confermati e spedisce un
     * report personalizzato a ogni utente creatore.
     */
    public function send_reports() {
        if (!intval(get_option('disco747_report_enabled', 1))) {
            error_log('[747Disco-Report] ℹ️ Report disabilitato dalle impostazioni.');
            return;
        }

        error_log('[747Disco-Report] 🔄 Avvio invio report...');

        global $wpdb;
        $table = $wpdb->prefix . 'disco747_preventivi';

        // Recupera i preventivi attivi (non confermati) creati negli ultimi 10 giorni
        $preventivi = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, preventivo_id, nome_cliente, nome_referente, cognome_referente,
                        telefono, data_evento, tipo_evento, importo_preventivo, acconto, created_by
                 FROM {$table}
                 WHERE stato = 'attivo'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 ORDER BY created_at DESC",
                10
            ),
            ARRAY_A
        );

        if (empty($preventivi)) {
            error_log('[747Disco-Report] ℹ️ Nessun preventivo non confermato. Nessuna email inviata.');
            $this->save_run_log(0, 0);
            return;
        }

        // Raggruppa per utente creatore
        $by_user = array();
        foreach ($preventivi as $p) {
            $user_id = intval($p['created_by']);
            if (!$user_id) {
                continue;
            }
            $by_user[$user_id][] = $p;
        }

        if (empty($by_user)) {
            error_log('[747Disco-Report] ℹ️ Nessun preventivo associato a un utente. Nessuna email inviata.');
            $this->save_run_log(0, count($preventivi));
            return;
        }

        foreach ($by_user as $user_id => $lista) {
            $user = get_user_by('id', $user_id);
            if (!$user || !is_email($user->user_email)) {
                error_log('[747Disco-Report] ⚠️ Utente ID ' . $user_id . ' non trovato o email non valida. Skip.');
                continue;
            }

            $sent = $this->send_report_to_user($user, $lista);

            if ($sent) {
                error_log('[747Disco-Report] ✅ Report inviato a ' . $user->user_email . ' (' . count($lista) . ' preventivi).');
            } else {
                error_log('[747Disco-Report] ❌ Errore invio report a ' . $user->user_email . '.');
            }
        }

        $this->save_run_log(count($by_user), count($preventivi));
    }

    /**
     * Invia il report a un singolo utente usando tutti i preventivi attivi
     * (non filtrati per created_by). Usato per il test dalla pagina admin.
     *
     * @param  WP_User $user Utente destinatario.
     * @return bool
     */
    public function send_test_to_user($user) {
        global $wpdb;
        $table = $wpdb->prefix . 'disco747_preventivi';

        $preventivi = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, preventivo_id, nome_cliente, nome_referente, cognome_referente,
                        telefono, data_evento, tipo_evento, importo_preventivo, acconto, created_by
                 FROM {$table}
                 WHERE stato = 'attivo'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 ORDER BY created_at DESC",
                10
            ),
            ARRAY_A
        );

        if (empty($preventivi)) {
            // Invia comunque il report con lista vuota
            $preventivi = array();
        }

        return $this->send_report_to_user($user, $preventivi);
    }

    /**
     * Restituisce lo stato corrente del cron.
     *
     * @return array
     */
    public static function get_cron_status() {
        $next = wp_next_scheduled(self::CRON_HOOK);
        return array(
            'scheduled'  => (bool) $next,
            'next_run'   => $next ? date_i18n('d/m/Y H:i', $next) : '—',
            'next_ts'    => $next ?: 0,
            'frequency'  => intval(get_option('disco747_report_frequency_days', 3)),
            'send_time'  => get_option('disco747_report_send_time', '09:00'),
            'enabled'    => (bool) intval(get_option('disco747_report_enabled', 1)),
        );
    }

    /**
     * Compone e invia la email di report a un singolo utente.
     *
     * @param  WP_User $user  Utente destinatario.
     * @param  array   $lista Array di preventivi (rows dal DB).
     * @return bool
     */
    private function send_report_to_user($user, $lista) {
        $count            = count($lista);
        $frequency        = intval(get_option('disco747_report_frequency_days', 3));
        $send_time        = get_option('disco747_report_send_time', '09:00');
        $subject_template = get_option('disco747_report_email_subject', '📋 Report preventivi — {count} non confermati');

        $replacements = array(
            '{count}'     => $count,
            '{frequency}' => $frequency,
            '{time}'      => $send_time,
            '{user}'      => $user->display_name ?: $user->user_login,
            '{date}'      => date_i18n('d/m/Y'),
        );

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);

        $html = $this->build_email_html($user, $lista);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );

        return wp_mail($user->user_email, $subject, $html, $headers);
    }

    /**
     * Genera il corpo HTML dell'email.
     *
     * @param  WP_User $user  Utente destinatario.
     * @param  array   $lista Lista preventivi.
     * @return string  HTML completo.
     */
    private function build_email_html($user, $lista) {
        $nome_utente = $user->display_name ?: $user->user_login;
        $count       = count($lista);
        $week_label  = date_i18n('d/m/Y');
        $frequency   = intval(get_option('disco747_report_frequency_days', 3));
        $send_time   = get_option('disco747_report_send_time', '09:00');

        $replacements = array(
            '{count}'     => $count,
            '{frequency}' => $frequency,
            '{time}'      => $send_time,
            '{user}'      => esc_html($nome_utente),
            '{date}'      => $week_label,
        );

        $intro_template  = get_option('disco747_report_email_intro', 'Hai {count} preventivi non ancora confermati. Contatta i clienti per chiudere la prenotazione!');
        $footer_template = get_option('disco747_report_email_footer', 'Questo report viene inviato automaticamente ogni {frequency} giorni alle {time} dal sistema 747 Disco CRM.');

        $intro_text  = str_replace(array_keys($replacements), array_values($replacements), $intro_template);
        $footer_text = str_replace(array_keys($replacements), array_values($replacements), $footer_template);

        $rows_html = '';
        foreach ($lista as $p) {
            $nome_cliente = esc_html(trim($p['nome_referente'] . ' ' . $p['cognome_referente']) ?: $p['nome_cliente']);
            $data_evento  = $p['data_evento'] ? date_i18n('d/m/Y', strtotime($p['data_evento'])) : '—';
            $tipo_evento  = esc_html($p['tipo_evento'] ?: '—');
            $importo      = $p['importo_preventivo'] > 0
                ? '€ ' . number_format((float) $p['importo_preventivo'], 2, ',', '.')
                : '—';
            $prev_id      = esc_html($p['preventivo_id'] ?: '#' . $p['id']);

            // Link telefono e WhatsApp
            $telefono_raw = preg_replace('/[^\d+]/', '', $p['telefono'] ?? '');
            $tel_display  = esc_html($p['telefono'] ?: '—');

            $tel_links = '';
            if ($telefono_raw) {
                $intl_digits = $this->normalize_phone_for_wa($telefono_raw);

                $nome_referente_wa = trim($p['nome_referente'] ?? '');
                $tipo_evento_wa    = trim($p['tipo_evento'] ?? '');

                $wa_message_template = get_option(
                    'disco747_report_whatsapp_message',
                    "Ciao {nome}! 👋\n\nSono {mittente} del 747 Disco 🎉\n\nTi scrivo per fare un check sulla disponibilità della sala per il tuo {tipo_evento} 🎊\n\nDopo il nostro sopralluogo di qualche giorno fa ho tenuto la data in sospeso per te, ma ho diverse richieste che stanno premendo per lo stesso periodo 📅\n\nCi tenevo a darti la precedenza! 🙌\nIl preventivo è in linea con quello che cercavi, o vuoi che sistemiamo qualche dettaglio insieme? 💬\n\nA presto! ✨"
                );

                // Sostituisce i placeholder con i dati reali del preventivo
                $nome_mittente_wa = $nome_utente; // display_name dell'utente WP che riceve la mail
                $wa_message = str_replace(
                    array('{nome}', '{tipo_evento}', '{mittente}'),
                    array($nome_referente_wa, $tipo_evento_wa, $nome_mittente_wa),
                    $wa_message_template
                );

                $tel_href = 'tel:+' . $intl_digits;
                $wa_href  = 'https://wa.me/' . $intl_digits . '?text=' . rawurlencode($wa_message);
                $tel_links = '&nbsp;
                    <a href="' . esc_url($tel_href) . '" style="display:inline-block;padding:4px 10px;background:#c28a4d;color:#2b1e1a;border-radius:4px;text-decoration:none;font-size:12px;font-weight:600;">📞 Chiama</a>
                    &nbsp;
                    <a href="' . $wa_href . '" style="display:inline-block;padding:4px 10px;background:#25D366;color:#fff;border-radius:4px;text-decoration:none;font-size:12px;font-weight:700;">💬 WhatsApp</a>';
            }

            $rows_html .= '
            <tr>
                <td style="padding:10px 12px;border-bottom:1px solid #eee;font-weight:600;color:#555;">' . $prev_id . '</td>
                <td style="padding:10px 12px;border-bottom:1px solid #eee;">' . $nome_cliente . '</td>
                <td style="padding:10px 12px;border-bottom:1px solid #eee;">' . $data_evento . '</td>
                <td style="padding:10px 12px;border-bottom:1px solid #eee;">' . $tipo_evento . '</td>
                <td style="padding:10px 12px;border-bottom:1px solid #eee;">' . $importo . '</td>
                <td style="padding:10px 12px;border-bottom:1px solid #eee;white-space:nowrap;">' . $tel_display . $tel_links . '</td>
            </tr>';
        }

        $html = '<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Preventivi Non Confermati</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#eeeae6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#eeeae6;padding:30px 0;">
        <tr><td align="center">
            <table width="700" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 25px rgba(43,30,26,0.18);">

                <!-- Header -->
                <tr>
                    <td style="background:#2b1e1a;padding:28px 32px;">
                        <h1 style="margin:0;color:#c28a4d;font-size:22px;">🎉 747 Disco CRM</h1>
                        <p style="margin:6px 0 0;color:#eeeae6;font-size:14px;">Report preventivi · ' . esc_html($week_label) . '</p>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:28px 32px;background:#fff;">
                        <p style="margin:0 0 6px;font-size:16px;color:#2b1e1a;">Ciao <strong>' . esc_html($nome_utente) . '</strong>,</p>
                        <p style="margin:0 0 24px;font-size:14px;color:#555;">' . esc_html($intro_text) . '</p>

                        <!-- Tabella preventivi -->
                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="background:#c28a4d;">
                                    <th style="padding:10px 12px;text-align:left;color:#2b1e1a;font-weight:700;border-bottom:2px solid #c28a4d;">ID</th>
                                    <th style="padding:10px 12px;text-align:left;color:#2b1e1a;font-weight:700;border-bottom:2px solid #c28a4d;">Cliente</th>
                                    <th style="padding:10px 12px;text-align:left;color:#2b1e1a;font-weight:700;border-bottom:2px solid #c28a4d;">Data Evento</th>
                                    <th style="padding:10px 12px;text-align:left;color:#2b1e1a;font-weight:700;border-bottom:2px solid #c28a4d;">Tipo</th>
                                    <th style="padding:10px 12px;text-align:left;color:#2b1e1a;font-weight:700;border-bottom:2px solid #c28a4d;">Importo</th>
                                    <th style="padding:10px 12px;text-align:left;color:#2b1e1a;font-weight:700;border-bottom:2px solid #c28a4d;">Contatti</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $rows_html . '
                            </tbody>
                        </table>

                        <p style="margin:24px 0 0;font-size:12px;color:#90858a;">' . esc_html($footer_text) . '</p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background:#2b1e1a;padding:16px 32px;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#c28a4d;">© ' . date('Y') . ' 747 Disco — gestionale.747disco.it</p>
                    </td>
                </tr>

            </table>
        </td></tr>
    </table>
</body>
</html>';

        return $html;
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Calcola il timestamp del prossimo invio in base alle impostazioni.
     *
     * @return int
     */
    private static function next_run_at() {
        $tz        = new DateTimeZone(wp_timezone_string() ?: 'Europe/Rome');
        $now       = new DateTime('now', $tz);
        $days      = intval(get_option('disco747_report_frequency_days', 3));
        $send_time = get_option('disco747_report_send_time', '09:00');
        if (!preg_match('/^\d{2}:\d{2}$/', $send_time)) {
            $send_time = '09:00';
        }
        list($hour, $minute) = explode(':', $send_time);

        $next = clone $now;
        $next->modify('+' . $days . ' days');
        $next->setTime((int) $hour, (int) $minute, 0);

        return $next->getTimestamp();
    }

    /**
     * Normalizza un numero di telefono in formato internazionale (senza +) per wa.me.
     *
     * Gestisce tutti i formati in cui il numero può essere salvato nel DB:
     *   +393331234567  → 393331234567
     *   00393331234567 → 393331234567
     *   3331234567     → 393331234567
     *   333 123 4567   → 393331234567
     *   06123456       → 3906123456
     *
     * @param string $raw Numero grezzo (può contenere spazi, +, ecc.).
     * @return string Cifre internazionali senza + (es. 393331234567).
     */
    private function normalize_phone_for_wa(string $raw): string {
        // 1. Rimuovi tutto tranne cifre e +
        $digits_plus = preg_replace('/[^\d+]/', '', $raw);

        // 2. Se inizia con +39 → rimuovi solo il +
        if (substr($digits_plus, 0, 3) === '+39') {
            return substr($digits_plus, 1);
        }

        // Da qui in poi lavoriamo solo con cifre
        $digits = preg_replace('/[^\d]/', '', $digits_plus);

        // 3. Se inizia con 0039 → rimuovi 00
        if (substr($digits, 0, 4) === '0039') {
            return substr($digits, 2);
        }

        // 4. Se inizia con 39 e ha almeno 11 cifre → prefisso italiano già presente
        if (substr($digits, 0, 2) === '39' && strlen($digits) >= 11) {
            return $digits;
        }

        // 5. Se inizia con 0 (numero locale con zero, es. 06...) → prefissa con 39 mantenendo lo 0
        if (substr($digits, 0, 1) === '0') {
            return '39' . $digits;
        }

        // 6. Altrimenti (es. 3331234567) → aggiungi 39 davanti
        return '39' . $digits;
    }

    /**
     * Salva il log dell'ultima esecuzione (ultime 5 voci).
     *
     * @param int $emails  Numero di email inviate.
     * @param int $count   Numero di preventivi trovati.
     */
    private function save_run_log($emails, $count) {
        $log = get_option('disco747_report_last_run_log', array());
        array_unshift($log, array(
            'date'   => current_time('d/m/Y H:i'),
            'emails' => $emails,
            'count'  => $count,
        ));
        $log = array_slice($log, 0, 5);
        update_option('disco747_report_last_run_log', $log);
    }
}
