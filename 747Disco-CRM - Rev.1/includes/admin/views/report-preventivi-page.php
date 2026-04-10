<?php
/**
 * Pagina Admin — Report Preventivi Non Confermati
 * Permette di configurare il report automatico senza toccare il codice.
 *
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      12.2.0
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

$notice_success = '';
$notice_error   = '';

// ─── Salvataggio impostazioni generali ──────────────────────────────────────
if (isset($_POST['disco747_save_settings'])) {
    check_admin_referer('disco747_report_settings');

    $frequency = intval($_POST['report_frequency_days'] ?? 3);
    if (!in_array($frequency, array(1, 2, 3, 5, 7), true)) {
        $frequency = 3;
    }
    $send_time = sanitize_text_field($_POST['report_send_time'] ?? '09:00');
    if (!preg_match('/^\d{2}:\d{2}$/', $send_time)) {
        $send_time = '09:00';
    }
    $enabled = isset($_POST['report_enabled']) ? 1 : 0;

    update_option('disco747_report_frequency_days', $frequency);
    update_option('disco747_report_send_time', $send_time);
    update_option('disco747_report_enabled', $enabled);

    // Ri-schedula il cron con le nuove impostazioni
    if (class_exists('Disco747_Weekly_Report')) {
        Disco747_Weekly_Report::activate();
    }

    $notice_success = '✅ Impostazioni generali salvate e cron aggiornato.';
}

// ─── Salvataggio testo email ─────────────────────────────────────────────────
if (isset($_POST['disco747_save_email_text'])) {
    check_admin_referer('disco747_report_settings');

    update_option('disco747_report_email_subject', sanitize_text_field($_POST['report_email_subject'] ?? ''));
    update_option('disco747_report_email_intro',   sanitize_textarea_field($_POST['report_email_intro'] ?? ''));
    update_option('disco747_report_email_footer',  sanitize_textarea_field($_POST['report_email_footer'] ?? ''));

    $notice_success = '✅ Testo email salvato correttamente.';
}

// ─── Salvataggio messaggio WhatsApp ──────────────────────────────────────────
if (isset($_POST['disco747_save_whatsapp_message'])) {
    check_admin_referer('disco747_report_settings');

    update_option('disco747_report_whatsapp_message', sanitize_textarea_field($_POST['report_whatsapp_message'] ?? ''));

    $notice_success = '✅ Messaggio WhatsApp salvato.';
}

// ─── Leggi valori correnti ───────────────────────────────────────────────────
$frequency  = intval(get_option('disco747_report_frequency_days', 3));
$send_time  = get_option('disco747_report_send_time', '09:00');
$enabled    = intval(get_option('disco747_report_enabled', 1));
$subject    = get_option('disco747_report_email_subject', '📋 Report preventivi — {count} non confermati');
$intro      = get_option('disco747_report_email_intro', 'Hai {count} preventivi non ancora confermati. Contatta i clienti per chiudere la prenotazione!');
$footer     = get_option('disco747_report_email_footer', 'Questo report viene inviato automaticamente ogni {frequency} giorni alle {time} dal sistema 747 Disco CRM.');
$whatsapp_message = get_option('disco747_report_whatsapp_message', "Ciao {nome}, spero tutto bene! Sono Andrea del 747.\nTi scrivo per fare un check sulla disponibilità della sala per il tuo {tipo_evento}. Dopo il sopralluogo di tre giorni fa, ho tenuto la data in sospeso, ma ho diverse richieste che premono per lo stesso periodo.\nCi tenevo a darti la precedenza: il preventivo è in linea con quello che cercavi o vuoi che sistemiamo qualche dettaglio insieme?");

// ─── Stato cron ──────────────────────────────────────────────────────────────
$cron_status = class_exists('Disco747_Weekly_Report')
    ? Disco747_Weekly_Report::get_cron_status()
    : array('scheduled' => false, 'next_run' => '—', 'frequency' => $frequency, 'send_time' => $send_time, 'enabled' => (bool) $enabled);

// ─── Log ultime esecuzioni ───────────────────────────────────────────────────
$run_log = get_option('disco747_report_last_run_log', array());

// ─── Messaggi "done" da redirect POST/handler ────────────────────────────────
if (!$notice_success) {
    $done = sanitize_key($_GET['done'] ?? '');
    if ($done === 'forced') {
        $notice_success = '✅ Invio forzato eseguito. Controlla i log per i dettagli.';
    } elseif ($done === 'test') {
        $notice_success = '✅ Email di test inviata al tuo indirizzo.';
    } elseif ($done === 'rescheduled') {
        $notice_success = '✅ Cron ri-schedulato con le impostazioni correnti.';
    }
}

$card_style = 'background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.12);padding:24px;margin-bottom:24px;';
?>

<div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">

    <h1 style="color:#c28a4d;font-size:2rem;margin-bottom:20px;">📋 Report Preventivi Non Confermati</h1>

    <?php if ($notice_success): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice_success); ?></p></div>
    <?php endif; ?>
    <?php if ($notice_error): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($notice_error); ?></p></div>
    <?php endif; ?>

    <!-- ================================================================== -->
    <!-- SEZIONE 1: Impostazioni generali                                    -->
    <!-- ================================================================== -->
    <div style="<?php echo esc_attr($card_style); ?>">
        <h2 style="margin-top:0;font-size:1.2rem;">⚙️ Impostazioni generali</h2>
        <form method="post">
            <?php wp_nonce_field('disco747_report_settings'); ?>

            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row" style="width:220px;">
                        <label for="report_frequency_days">Frequenza invio</label>
                    </th>
                    <td>
                        <select name="report_frequency_days" id="report_frequency_days">
                            <?php foreach (array(1, 2, 3, 5, 7) as $d): ?>
                                <option value="<?php echo esc_attr($d); ?>" <?php selected($frequency, $d); ?>>
                                    <?php echo esc_html($d === 1 ? '1 giorno' : $d . ' giorni'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Ogni quanti giorni viene inviato il report.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="report_send_time">Orario invio</label>
                    </th>
                    <td>
                        <input type="time" name="report_send_time" id="report_send_time"
                               value="<?php echo esc_attr($send_time); ?>" class="regular-text" style="width:120px;">
                        <p class="description">Ora del giorno in cui viene inviato il report (formato HH:MM).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Report attivo</th>
                    <td>
                        <label>
                            <input type="checkbox" name="report_enabled" value="1" <?php checked($enabled, 1); ?>>
                            Abilita l'invio automatico del report
                        </label>
                        <p class="description">Se deselezionato, il cron viene rimosso e nessuna email verrà inviata automaticamente.</p>
                    </td>
                </tr>
            </table>

            <p style="margin-top:16px;">
                <button type="submit" name="disco747_save_settings" class="button button-primary">
                    💾 Salva impostazioni
                </button>
            </p>
        </form>
    </div>

    <!-- ================================================================== -->
    <!-- SEZIONE 2: Testo email                                              -->
    <!-- ================================================================== -->
    <div style="<?php echo esc_attr($card_style); ?>">
        <h2 style="margin-top:0;font-size:1.2rem;">📧 Testo email</h2>

        <p style="color:#666;font-size:13px;margin-top:0;">
            Placeholder disponibili:
            <code>{count}</code> (n° preventivi),
            <code>{frequency}</code> (giorni),
            <code>{time}</code> (orario),
            <code>{user}</code> (nome utente),
            <code>{date}</code> (data odierna).
        </p>

        <form method="post">
            <?php wp_nonce_field('disco747_report_settings'); ?>

            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row" style="width:220px;">
                        <label for="report_email_subject">Oggetto email</label>
                    </th>
                    <td>
                        <input type="text" name="report_email_subject" id="report_email_subject"
                               value="<?php echo esc_attr($subject); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="report_email_intro">Testo introduttivo</label>
                    </th>
                    <td>
                        <textarea name="report_email_intro" id="report_email_intro"
                                  class="large-text" rows="3"><?php echo esc_textarea($intro); ?></textarea>
                        <p class="description">Testo mostrato sopra la tabella dei preventivi nell'email.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="report_email_footer">Testo footer email</label>
                    </th>
                    <td>
                        <textarea name="report_email_footer" id="report_email_footer"
                                  class="large-text" rows="2"><?php echo esc_textarea($footer); ?></textarea>
                        <p class="description">Testo piccolo mostrato sotto la tabella.</p>
                    </td>
                </tr>
            </table>

            <p style="margin-top:16px;">
                <button type="submit" name="disco747_save_email_text" class="button button-primary">
                    💾 Salva testo
                </button>
            </p>
        </form>
    </div>

    <!-- ================================================================== -->
    <!-- SEZIONE 3: Messaggio WhatsApp di follow-up                         -->
    <!-- ================================================================== -->
    <div style="<?php echo esc_attr($card_style); ?>">
        <h2 style="margin-top:0;font-size:1.2rem;">💬 Messaggio WhatsApp di follow-up</h2>

        <p style="color:#666;font-size:13px;margin-top:0;">
            Placeholder disponibili:
            <code>{nome}</code> → Nome del cliente (es. Mario),
            <code>{tipo_evento}</code> → Tipo di evento (es. Compleanno).
        </p>

        <form method="post">
            <?php wp_nonce_field('disco747_report_settings'); ?>

            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row" style="width:220px;">
                        <label for="report_whatsapp_message">Testo del messaggio</label>
                    </th>
                    <td>
                        <textarea name="report_whatsapp_message" id="report_whatsapp_message"
                                  class="large-text" rows="8"><?php echo esc_textarea($whatsapp_message); ?></textarea>
                        <p class="description">
                            Usa <code>{nome}</code> per il nome del cliente e <code>{tipo_evento}</code> per il tipo di evento.
                            I ritorni a capo vengono rispettati nel link WhatsApp.
                        </p>
                    </td>
                </tr>
            </table>

            <p style="margin-top:16px;">
                <button type="submit" name="disco747_save_whatsapp_message" class="button button-primary">
                    💾 Salva messaggio WhatsApp
                </button>
            </p>
        </form>
    </div>

    <!-- ================================================================== -->
    <!-- SEZIONE 4: Test e stato cron                                        -->
    <!-- ================================================================== -->
    <div style="<?php echo esc_attr($card_style); ?>">
        <h2 style="margin-top:0;font-size:1.2rem;">🧪 Test e stato cron</h2>

        <!-- Pannello stato -->
        <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:16px 20px;margin-bottom:20px;">
            <h3 style="margin-top:0;font-size:1rem;">📡 Stato cron</h3>
            <table style="border-collapse:collapse;font-size:13px;">
                <tr>
                    <td style="padding:4px 12px 4px 0;font-weight:600;color:#555;">Report abilitato:</td>
                    <td>
                        <?php if ($cron_status['enabled']): ?>
                            <span style="color:#2e7d32;font-weight:600;">✅ Sì</span>
                        <?php else: ?>
                            <span style="color:#c62828;font-weight:600;">❌ No</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px 12px 4px 0;font-weight:600;color:#555;">Cron schedulato:</td>
                    <td>
                        <?php if ($cron_status['scheduled']): ?>
                            <span style="color:#2e7d32;font-weight:600;">✅ Sì</span>
                        <?php else: ?>
                            <span style="color:#c62828;font-weight:600;">❌ No</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px 12px 4px 0;font-weight:600;color:#555;">Prossimo invio:</td>
                    <td><?php echo esc_html($cron_status['next_run']); ?></td>
                </tr>
                <tr>
                    <td style="padding:4px 12px 4px 0;font-weight:600;color:#555;">Frequenza attuale:</td>
                    <td>Ogni <?php echo esc_html($cron_status['frequency']); ?> giorni alle <?php echo esc_html($cron_status['send_time']); ?></td>
                </tr>
            </table>
        </div>

        <!-- Pulsanti azione -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="disco747_force_report">
                <?php wp_nonce_field('disco747_report_force'); ?>
                <button type="submit" class="button button-secondary"
                        onclick="return confirm(<?php echo esc_js('Sicuro di voler forzare l\'invio adesso?'); ?>);">
                    ▶️ Forza invio subito
                </button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="disco747_reschedule_report">
                <?php wp_nonce_field('disco747_report_reschedule'); ?>
                <button type="submit" class="button button-secondary">
                    🔄 Reschedula cron
                </button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="disco747_test_report_email">
                <?php wp_nonce_field('disco747_report_test'); ?>
                <button type="submit" class="button button-secondary">
                    📬 Invia email di test a me
                </button>
            </form>

        </div>

        <!-- Log ultime esecuzioni -->
        <?php if (!empty($run_log)): ?>
            <h3 style="font-size:1rem;margin-bottom:10px;">🗓️ Ultime esecuzioni</h3>
            <table class="widefat striped" style="font-size:13px;max-width:600px;">
                <thead>
                    <tr>
                        <th>Data/Ora</th>
                        <th>Email inviate</th>
                        <th>Preventivi trovati</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($run_log as $entry): ?>
                        <tr>
                            <td><?php echo esc_html($entry['date'] ?? '—'); ?></td>
                            <td><?php echo esc_html($entry['emails'] ?? 0); ?></td>
                            <td><?php echo esc_html($entry['count'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#888;font-size:13px;"><em>Nessuna esecuzione registrata.</em></p>
        <?php endif; ?>
    </div>

</div><!-- .wrap -->
