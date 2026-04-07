<?php
/**
 * Weekly Unconfirmed Quotes Report - 747 Disco CRM
 *
 * Invia ogni 3 giorni alle 09:00 una email a ciascun utente che ha
 * generato preventivi, con la lista dei preventivi non ancora confermati
 * (stato = 'attivo'), includendo link per chiamare e scrivere su WhatsApp.
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
        add_filter('cron_schedules', array($this, 'add_three_days_interval'));
    }

    /**
     * Registra un intervallo cron personalizzato di 3 giorni.
     *
     * @param  array $schedules Intervalli esistenti.
     * @return array
     */
    public function add_three_days_interval($schedules) {
        if (!isset($schedules['every_three_days'])) {
            $schedules['every_three_days'] = array(
                'interval' => 3 * DAY_IN_SECONDS, // 259200 secondi
                'display'  => __('Ogni 3 giorni', 'disco747'),
            );
        }
        return $schedules;
    }

    // -------------------------------------------------------------------------
    // Attivazione / Disattivazione cron
    // -------------------------------------------------------------------------

    /**
     * Schedula il cron ogni 3 giorni alle 09:00 (ora del sito).
     * Chiamare da activate_plugin().
     */
    public static function activate() {
        // Rimuovi eventuale cron precedente (potrebbe usare l'intervallo 'weekly')
        $existing = wp_next_scheduled(self::CRON_HOOK);
        if ($existing) {
            wp_unschedule_event($existing, self::CRON_HOOK);
        }
        wp_clear_scheduled_hook(self::CRON_HOOK);

        // Schedula con il nuovo intervallo ogni 3 giorni
        $next_run = self::next_run_9am();
        wp_schedule_event($next_run, 'every_three_days', self::CRON_HOOK);
        error_log('[747Disco-WeeklyReport] ✅ Cron ogni 3 giorni attivato. Prossimo invio: ' . date('Y-m-d H:i:s', $next_run));
    }

    /**
     * Rimuove il cron settimanale.
     * Chiamare da deactivate_plugin().
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        wp_clear_scheduled_hook(self::CRON_HOOK);
        error_log('[747Disco-WeeklyReport] ✅ Cron ogni 3 giorni disattivato.');
    }

    // -------------------------------------------------------------------------
    // Invio report
    // -------------------------------------------------------------------------

    /**
     * Eseguito dal cron: recupera i preventivi non confermati e spedisce un
     * report personalizzato a ogni utente creatore.
     */
    public function send_reports() {
        error_log('[747Disco-WeeklyReport] 🔄 Avvio invio report ogni 3 giorni...');

        global $wpdb;
        $table = $wpdb->prefix . 'disco747_preventivi';

        // Recupera tutti i preventivi attivi (non confermati, non annullati)
        $preventivi = $wpdb->get_results(
            "SELECT id, preventivo_id, nome_cliente, nome_referente, cognome_referente,
                    telefono, data_evento, tipo_evento, importo_preventivo, acconto, created_by
             FROM {$table}
             WHERE stato = 'attivo'
             ORDER BY data_evento ASC",
            ARRAY_A
        );

        if (empty($preventivi)) {
            error_log('[747Disco-WeeklyReport] ℹ️ Nessun preventivo non confermato. Nessuna email inviata.');
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
            error_log('[747Disco-WeeklyReport] ℹ️ Nessun preventivo associato a un utente. Nessuna email inviata.');
            return;
        }

        foreach ($by_user as $user_id => $lista) {
            $user = get_user_by('id', $user_id);
            if (!$user || !is_email($user->user_email)) {
                error_log('[747Disco-WeeklyReport] ⚠️ Utente ID ' . $user_id . ' non trovato o email non valida. Skip.');
                continue;
            }

            $sent = $this->send_report_to_user($user, $lista);

            if ($sent) {
                error_log('[747Disco-WeeklyReport] ✅ Report inviato a ' . $user->user_email . ' (' . count($lista) . ' preventivi).');
            } else {
                error_log('[747Disco-WeeklyReport] ❌ Errore invio report a ' . $user->user_email . '.');
            }
        }
    }

    /**
     * Compone e invia la email di report a un singolo utente.
     *
     * @param  WP_User $user  Utente destinatario.
     * @param  array   $lista Array di preventivi (rows dal DB).
     * @return bool
     */
    private function send_report_to_user($user, $lista) {
        $count   = count($lista);
        $subject = '📋 Report preventivi — ' . $count . ' ' . ($count === 1 ? 'preventivo' : 'preventivi') . ' non confermati';

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
            // Normalizza il numero: rimuovi tutto tranne cifre e +
            $telefono_raw = preg_replace('/[^\d+]/', '', $p['telefono'] ?? '');
            $tel_display  = esc_html($p['telefono'] ?: '—');

            $tel_links = '';
            if ($telefono_raw) {
                // Determina il numero internazionale (E.164 senza '+')
                if (preg_match('/^\+(\d+)$/', $telefono_raw, $m)) {
                    // Ha già un prefisso internazionale (es. +393356789000)
                    $intl_digits = $m[1];
                } elseif (preg_match('/^0039(\d+)$/', $telefono_raw, $m)) {
                    // Prefisso italiano in formato 0039
                    $intl_digits = '39' . $m[1];
                } elseif (preg_match('/^0(\d+)$/', $telefono_raw, $m)) {
                    // Numero locale con 0 iniziale (es. 0335...) → aggiungi 39
                    $intl_digits = '39' . $m[1];
                } else {
                    // Numero senza prefisso (es. 3356789000) → aggiungi 39
                    $intl_digits = '39' . $telefono_raw;
                }

                $tel_href = 'tel:+' . $intl_digits;
                $wa_href  = 'https://wa.me/' . $intl_digits;
                $tel_links = '&nbsp;
                    <a href="' . esc_url($tel_href) . '" style="display:inline-block;padding:4px 10px;background:#1976D2;color:#fff;border-radius:4px;text-decoration:none;font-size:12px;">📞 Chiama</a>
                    &nbsp;
                    <a href="' . esc_url($wa_href) . '" style="display:inline-block;padding:4px 10px;background:#25D366;color:#fff;border-radius:4px;text-decoration:none;font-size:12px;">💬 WhatsApp</a>';
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
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
        <tr><td align="center">
            <table width="700" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">

                <!-- Header -->
                <tr>
                    <td style="background:#1a237e;padding:28px 32px;">
                        <h1 style="margin:0;color:#fff;font-size:22px;">🎉 747 Disco CRM</h1>
                        <p style="margin:6px 0 0;color:#c5cae9;font-size:14px;">Report ogni 3 giorni · ' . esc_html($week_label) . '</p>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:28px 32px;">
                        <p style="margin:0 0 6px;font-size:16px;color:#333;">Ciao <strong>' . esc_html($nome_utente) . '</strong>,</p>
                        <p style="margin:0 0 24px;font-size:14px;color:#555;">
                            Hai <strong>' . $count . '</strong> ' . ($count === 1 ? 'preventivo' : 'preventivi') . ' non ancora confermati.
                            Contatta i clienti per chiudere la prenotazione!
                        </p>

                        <!-- Tabella preventivi -->
                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="background:#e8eaf6;">
                                    <th style="padding:10px 12px;text-align:left;color:#3949ab;font-weight:700;border-bottom:2px solid #c5cae9;">ID</th>
                                    <th style="padding:10px 12px;text-align:left;color:#3949ab;font-weight:700;border-bottom:2px solid #c5cae9;">Cliente</th>
                                    <th style="padding:10px 12px;text-align:left;color:#3949ab;font-weight:700;border-bottom:2px solid #c5cae9;">Data Evento</th>
                                    <th style="padding:10px 12px;text-align:left;color:#3949ab;font-weight:700;border-bottom:2px solid #c5cae9;">Tipo</th>
                                    <th style="padding:10px 12px;text-align:left;color:#3949ab;font-weight:700;border-bottom:2px solid #c5cae9;">Importo</th>
                                    <th style="padding:10px 12px;text-align:left;color:#3949ab;font-weight:700;border-bottom:2px solid #c5cae9;">Contatti</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $rows_html . '
                            </tbody>
                        </table>

                        <p style="margin:24px 0 0;font-size:12px;color:#999;">
                            Questo report viene inviato automaticamente ogni 3 giorni alle 09:00 dal sistema 747 Disco CRM.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background:#f5f5f5;padding:16px 32px;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#aaa;">© ' . date('Y') . ' 747 Disco — gestionale.747disco.it</p>
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
     * Restituisce il timestamp Unix di tra 3 giorni alle 09:00 (ora del sito).
     *
     * @return int
     */
    private static function next_run_9am() {
        $tz  = new DateTimeZone(wp_timezone_string() ?: 'Europe/Rome');
        $now = new DateTime('now', $tz);
        $next = clone $now;
        $next->modify('+3 days');
        $next->setTime(9, 0, 0);
        return $next->getTimestamp();
    }
}
