<?php
/**
 * Pagina Automazione Funnel - 747 Disco CRM
 * Interfaccia per gestire sequenze email/WhatsApp automatiche
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @version    1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

use Disco747_CRM\Funnel\Disco747_Funnel_Manager;
use Disco747_CRM\Funnel\Disco747_Funnel_Scheduler;
use Disco747_CRM\Funnel\Disco747_Funnel_Database;

global $wpdb;
$sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';
$tracking_table = $wpdb->prefix . 'disco747_funnel_tracking';

// ✅ AUTO-FIX: Crea tabelle se non esistono
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$sequences_table}'");
if ($table_exists !== $sequences_table) {
    $funnel_db = new Disco747_Funnel_Database();
    $funnel_db->create_tables();
    error_log('[747Disco-Funnel] ✅ Tabelle funnel create automaticamente');
    $auto_created = true;
}

// Inizializza manager
$funnel_manager = new Disco747_Funnel_Manager();
$scheduler = new Disco747_Funnel_Scheduler();

// ✅ AUTO-FIX: Attiva scheduler se non attivo
if (!wp_next_scheduled('disco747_funnel_check_sends')) {
    $scheduler->activate();
    error_log('[747Disco-Funnel] ✅ Scheduler attivato automaticamente');
}

// TAB attivo
$active_tab = sanitize_key($_GET['tab'] ?? 'pre_conferma');

// Azioni
if (isset($_POST['save_sequence'])) {
    // Salva/Aggiorna sequenza
    $sequence_id = intval($_POST['sequence_id'] ?? 0);
    
    $data = array(
        'funnel_type' => sanitize_key($_POST['funnel_type']),
        'step_number' => intval($_POST['step_number']),
        'step_name' => sanitize_text_field($_POST['step_name']),
        'days_offset' => intval($_POST['days_offset']),
        'send_time' => sanitize_text_field($_POST['send_time']) . ':00',
        'email_enabled' => isset($_POST['email_enabled']) ? 1 : 0,
        'email_subject' => sanitize_text_field($_POST['email_subject']),
        'email_body' => wp_kses_post($_POST['email_body']),
        'whatsapp_enabled' => isset($_POST['whatsapp_enabled']) ? 1 : 0,
        'whatsapp_text' => sanitize_textarea_field($_POST['whatsapp_text']),
        'active' => isset($_POST['active']) ? 1 : 0
    );
    
    if ($sequence_id > 0) {
        $wpdb->update($sequences_table, $data, array('id' => $sequence_id));
        $message = '✅ Sequenza aggiornata con successo!';
    } else {
        $wpdb->insert($sequences_table, $data);
        $message = '✅ Sequenza creata con successo!';
    }
}

if (isset($_GET['delete_sequence'])) {
    $seq_id = intval($_GET['delete_sequence']);
    $wpdb->delete($sequences_table, array('id' => $seq_id));
    $message = '✅ Sequenza eliminata!';
}

if (isset($_GET['action']) && $_GET['action'] === 'test_cron') {
    $scheduler->process_pending_sends();
    $message = '✅ Test cron eseguito! Controlla i log.';
}

// Carica sequenze
$sequences_pre_conferma = $wpdb->get_results("
    SELECT * FROM {$sequences_table} 
    WHERE funnel_type = 'pre_conferma' 
    ORDER BY step_number ASC
");

$sequences_pre_evento = $wpdb->get_results("
    SELECT * FROM {$sequences_table} 
    WHERE funnel_type = 'pre_evento' 
    ORDER BY step_number ASC
");

// Statistiche
$stats = array(
    'active_trackings' => $wpdb->get_var("SELECT COUNT(*) FROM {$tracking_table} WHERE status = 'active'"),
    'completed_today' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$tracking_table} WHERE status = 'completed' AND DATE(completed_at) = %s",
        date('Y-m-d')
    )),
    'emails_sent_today' => 0 // Calcolare dal log
);

$active_trackings = $funnel_manager->get_active_trackings();
$cron_status = $scheduler->get_cron_status();

?>

<div class="wrap disco747-funnel">
    
    <!-- HEADER -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 15px; margin-bottom: 30px; color: white; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);">
        <h1 style="margin: 0; font-size: 2.5rem; font-weight: 700;">
            🚀 Automazione Funnel Marketing
        </h1>
        <p style="margin: 10px 0 0 0; font-size: 1.1rem; opacity: 0.9;">
            Sistema automatico per convertire preventivi e aumentare le vendite
        </p>
    </div>

    <?php if (isset($auto_created) && $auto_created): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✅ Tabelle Funnel create con successo!</strong> Il sistema è ora operativo. Ricarica la pagina per vedere le sequenze di default.</p>
            <p><a href="<?php echo admin_url('admin.php?page=disco747-funnel'); ?>" class="button button-primary">🔄 Ricarica Pagina</a></p>
        </div>
    <?php endif; ?>

    <?php if (isset($message)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>

    <!-- STATISTICHE RAPIDE -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        
        <div class="kpi-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="kpi-icon">📬</div>
            <div class="kpi-label">Funnel Attivi</div>
            <div class="kpi-value"><?php echo $stats['active_trackings']; ?></div>
        </div>
        
        <div class="kpi-box" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Completati Oggi</div>
            <div class="kpi-value"><?php echo $stats['completed_today']; ?></div>
        </div>
        
        <div class="kpi-box" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <div class="kpi-icon">⏰</div>
            <div class="kpi-label">Prossimo Check</div>
            <div class="kpi-value" style="font-size: 1.2rem;"><?php echo $cron_status['sends_check']['next_run_relative']; ?></div>
        </div>
        
    </div>

    <!-- TABS -->
    <div class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <a href="?page=disco747-funnel&tab=pre_conferma" 
           class="nav-tab <?php echo $active_tab === 'pre_conferma' ? 'nav-tab-active' : ''; ?>">
            🎯 Funnel Pre-Conferma
        </a>
        <a href="?page=disco747-funnel&tab=pre_evento" 
           class="nav-tab <?php echo $active_tab === 'pre_evento' ? 'nav-tab-active' : ''; ?>">
            🎁 Funnel Pre-Evento
        </a>
        <a href="?page=disco747-funnel&tab=tracking" 
           class="nav-tab <?php echo $active_tab === 'tracking' ? 'nav-tab-active' : ''; ?>">
            📊 Tracking Attivi
        </a>
        <a href="?page=disco747-funnel&tab=settings" 
           class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            ⚙️ Impostazioni
        </a>
    </div>

    <!-- CONTENUTO TAB -->
    <?php if ($active_tab === 'pre_conferma' || $active_tab === 'pre_evento'): ?>
        
        <?php
        $sequences = $active_tab === 'pre_conferma' ? $sequences_pre_conferma : $sequences_pre_evento;
        $funnel_label = $active_tab === 'pre_conferma' ? 'Pre-Conferma' : 'Pre-Evento';
        ?>

        <!-- Descrizione -->
        <div class="disco747-card" style="margin-bottom: 20px; background: #e7f3ff; border-left: 4px solid #007bff;">
            <div class="disco747-card-content">
                <h3 style="margin: 0 0 10px 0; color: #0056b3;">
                    <?php if ($active_tab === 'pre_conferma'): ?>
                        🎯 Funnel Pre-Conferma
                    <?php else: ?>
                        🎁 Funnel Pre-Evento (Upselling)
                    <?php endif; ?>
                </h3>
                <p style="margin: 0; color: #495057;">
                    <?php if ($active_tab === 'pre_conferma'): ?>
                        Questo funnel si attiva <strong>automaticamente</strong> quando crei un nuovo preventivo NON confermato.
                        L'obiettivo è convertire il preventivo in conferma con invio progressivo di email e WhatsApp.
                    <?php else: ?>
                        Questo funnel si attiva per eventi <strong>confermati</strong> a pochi giorni dall'evento.
                        L'obiettivo è vendere pacchetti extra e upgrade last-minute.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Lista Sequenze -->
        <div class="disco747-card">
            <div class="disco747-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <span>📋 Sequenza Step</span>
                <button type="button" class="button button-primary" onclick="showAddStepModal('<?php echo $active_tab; ?>')">
                    ➕ Aggiungi Step
                </button>
            </div>
            <div class="disco747-card-content" style="padding: 0;">
                
                <?php if (empty($sequences)): ?>
                    <div style="padding: 40px; text-align: center; color: #6c757d;">
                        <p>Nessuna sequenza configurata. Clicca "Aggiungi Step" per iniziare!</p>
                    </div>
                <?php else: ?>
                    
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Step</th>
                                <th style="width: 100px;">Timing</th>
                                <th style="width: 80px;">⏰ Orario</th>
                                <th>Nome</th>
                                <th style="width: 80px; text-align: center;">📧 Email</th>
                                <th style="width: 80px; text-align: center;">💬 WhatsApp</th>
                                <th style="width: 80px; text-align: center;">Stato</th>
                                <th style="width: 150px;">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sequences as $seq): ?>
                            <tr>
                                <td><strong>#<?php echo $seq->step_number; ?></strong></td>
                                <td>
                                    <span style="background: #e9ecef; padding: 5px 10px; border-radius: 5px; font-weight: 600;">
                                        <?php echo $seq->days_offset >= 0 ? '+' : ''; ?><?php echo $seq->days_offset; ?> giorni
                                    </span>
                                </td>
                                <td>
                                    <span style="background: #fff3cd; padding: 5px 10px; border-radius: 5px; font-weight: 600; color: #856404;">
                                        <?php echo $seq->send_time ? substr($seq->send_time, 0, 5) : '09:00'; ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($seq->step_name); ?></td>
                                <td style="text-align: center;">
                                    <?php if ($seq->email_enabled): ?>
                                        <span style="color: #28a745; font-size: 20px;">✓</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-size: 20px;">✗</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($seq->whatsapp_enabled): ?>
                                        <span style="color: #28a745; font-size: 20px;">✓</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-size: 20px;">✗</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($seq->active): ?>
                                        <span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 5px; font-size: 11px; font-weight: 600;">ATTIVO</span>
                                    <?php else: ?>
                                        <span style="background: #6c757d; color: white; padding: 4px 8px; border-radius: 5px; font-size: 11px; font-weight: 600;">DISATTIVO</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <!-- NUOVO: Pulsante Anteprima Email -->
                                    <button type="button" class="button button-small" onclick="previewEmail(<?php echo $seq->id; ?>)" 
                                            style="background:#667eea;color:#fff;border-color:#667eea;margin-right:5px;" 
                                            title="Anteprima Email">
                                        👁️ Anteprima
                                    </button>
                                    
                                    <button type="button" class="button button-small" onclick="editSequence(<?php echo $seq->id; ?>)">
                                        ✏️ Modifica
                                    </button>
                                    <a href="?page=disco747-funnel&tab=<?php echo $active_tab; ?>&delete_sequence=<?php echo $seq->id; ?>" 
                                       class="button button-small"
                                       onclick="return confirm('Sicuro di eliminare questa sequenza?')"
                                       style="color: #dc3545;">
                                        🗑️
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                <?php endif; ?>
                
            </div>
        </div>

    <?php elseif ($active_tab === 'tracking'): ?>
        
        <!-- Tracking Attivi -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                📊 Funnel Attivi in Corso
            </div>
            <div class="disco747-card-content" style="padding: 0;">
                
                <?php if (empty($active_trackings)): ?>
                    <div style="padding: 40px; text-align: center; color: #6c757d;">
                        <p>Nessun funnel attivo al momento.</p>
                    </div>
                <?php else: ?>
                    
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>Preventivo</th>
                                <th>Cliente</th>
                                <th>Tipo Funnel</th>
                                <th>Step Corrente</th>
                                <th>Prossimo Invio</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_trackings as $track): ?>
                            <tr>
                                <td><strong>#<?php echo $track->preventivo_id; ?></strong></td>
                                <td><?php echo esc_html($track->nome_cliente); ?></td>
                                <td>
                                    <?php if ($track->funnel_type === 'pre_conferma'): ?>
                                        <span style="background: #667eea; color: white; padding: 4px 10px; border-radius: 5px; font-size: 11px;">PRE-CONFERMA</span>
                                    <?php else: ?>
                                        <span style="background: #f59e0b; color: white; padding: 4px 10px; border-radius: 5px; font-size: 11px;">PRE-EVENTO</span>
                                    <?php endif; ?>
                                </td>
                                <td>Step <?php echo $track->current_step; ?></td>
                                <td>
                                    <?php if ($track->next_send_at): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($track->next_send_at)); ?>
                                    <?php else: ?>
                                        <em>Completato</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=disco747-funnel&action=pause&tracking_id=<?php echo $track->id; ?>" class="button button-small">
                                        ⏸️ Pausa
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                <?php endif; ?>
                
            </div>
        </div>

    <?php elseif ($active_tab === 'settings'): ?>
        
        <!-- Impostazioni -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                ⚙️ Impostazioni Sistema
            </div>
            <div class="disco747-card-content">
                
                <h3>📮 Email Notifiche WhatsApp</h3>
                <p>Le notifiche per inviare WhatsApp vengono inviate a: <strong>info@gestionale.747disco.it</strong></p>
                
                <hr>
                
                <h3>⏰ Stato WP Cron</h3>
                <table class="form-table">
                    <tr>
                        <th>Check Invii Orario:</th>
                        <td>
                            <strong style="color: <?php echo $cron_status['sends_check']['active'] ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo $cron_status['sends_check']['active'] ? '✅ ATTIVO' : '❌ NON ATTIVO'; ?>
                            </strong>
                            <br>
                            Prossimo check: <?php echo $cron_status['sends_check']['next_run']; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Check Pre-Evento Giornaliero:</th>
                        <td>
                            <strong style="color: <?php echo $cron_status['pre_evento_check']['active'] ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo $cron_status['pre_evento_check']['active'] ? '✅ ATTIVO' : '❌ NON ATTIVO'; ?>
                            </strong>
                            <br>
                            Prossimo check: <?php echo $cron_status['pre_evento_check']['next_run']; ?>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <a href="?page=disco747-funnel&tab=settings&action=test_cron" class="button button-primary">
                        🧪 Test Cron Manuale
                    </a>
                </p>
                
                <hr>
                
                <h3>📝 Variabili Disponibili nei Template</h3>
                <p>Puoi usare queste variabili nei testi di email e WhatsApp:</p>
                <ul style="column-count: 2; column-gap: 30px;">
                    <li><code>{{nome_referente}}</code> - Nome del referente</li>
                    <li><code>{{cognome_referente}}</code> - Cognome del referente</li>
                    <li><code>{{nome_cliente}}</code> - Nome completo cliente</li>
                    <li><code>{{tipo_evento}}</code> - Tipo di evento</li>
                    <li><code>{{data_evento}}</code> - Data evento (formato: 25/12/2025)</li>
                    <li><code>{{numero_invitati}}</code> - Numero invitati</li>
                    <li><code>{{tipo_menu}}</code> - Tipo menu scelto</li>
                    <li><code>{{importo_totale}}</code> - Importo totale (es: 3.500,00)</li>
                    <li><code>{{acconto}}</code> - Importo acconto</li>
                    <li><code>{{telefono_sede}}</code> - Telefono 747 Disco</li>
                    <li><code>{{email_sede}}</code> - Email info@gestionale.747disco.it</li>
                </ul>
                
            </div>
        </div>

    <?php endif; ?>

</div>

<!-- Modal Aggiungi/Modifica Step -->
<div id="sequence-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; overflow-y: auto;">
    <div style="max-width: 900px; margin: 50px auto; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 12px 12px 0 0; color: white;">
            <h2 id="modal-title" style="margin: 0;">➕ Aggiungi Step</h2>
        </div>
        
        <form method="post" style="padding: 30px;">
            <input type="hidden" name="save_sequence" value="1">
            <input type="hidden" name="sequence_id" id="edit-sequence-id" value="">
            <input type="hidden" name="funnel_type" id="edit-funnel-type" value="">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Numero Step *</label>
                    <input type="number" name="step_number" id="edit-step-number" required min="1" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Giorni Offset *</label>
                    <input type="number" name="days_offset" id="edit-days-offset" required style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                    <small>Esempio: 0 = subito, +2 = dopo 2 giorni, -10 = 10 giorni prima evento</small>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">⏰ Orario Invio *</label>
                    <input type="time" name="send_time" id="edit-send-time" value="09:00" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                    <small>A che ora del giorno inviare l'email (es: 09:00, 14:30)</small>
                </div>
                
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Nome Step *</label>
                <input type="text" name="step_name" id="edit-step-name" required placeholder="Es: Follow-up iniziale" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
            </div>
            
            <!-- Email -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="email_enabled" id="edit-email-enabled" value="1" checked style="width: 20px; height: 20px;">
                        <span style="font-weight: 700; font-size: 1.1rem;">📧 Email Attiva</span>
                    </label>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Oggetto Email</label>
                    <input type="text" name="email_subject" id="edit-email-subject" placeholder="Il tuo preventivo è pronto!" style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                </div>
                
                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Corpo Email</label>
                    <textarea name="email_body" id="edit-email-body" rows="8" placeholder="Ciao {{nome_referente}}, ..." style="width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 8px; font-family: monospace;"></textarea>
                </div>
            </div>
            
            <!-- WhatsApp -->
            <div style="background: #d1fae5; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="whatsapp_enabled" id="edit-whatsapp-enabled" value="1" style="width: 20px; height: 20px;">
                        <span style="font-weight: 700; font-size: 1.1rem;">💬 WhatsApp Attivo</span>
                    </label>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Testo WhatsApp</label>
                    <textarea name="whatsapp_text" id="edit-whatsapp-text" rows="5" placeholder="Ciao {{nome_referente}}! ..." style="width: 100%; padding: 10px; border: 2px solid #10b981; border-radius: 8px; font-family: monospace;"></textarea>
                    <small style="color: #065f46;">📱 Ti verrà inviata un'email con link per aprire WhatsApp e inviare manualmente</small>
                </div>
            </div>
            
            <!-- Attivo -->
            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="active" id="edit-active" value="1" checked style="width: 20px; height: 20px;">
                    <span style="font-weight: 600;">✅ Step Attivo (disattiva per metterlo in pausa)</span>
                </label>
            </div>
            
            <div style="text-align: right; border-top: 2px solid #e9ecef; padding-top: 20px;">
                <button type="button" onclick="closeModal()" class="button" style="margin-right: 10px;">Annulla</button>
                <button type="submit" class="button button-primary">💾 Salva Step</button>
            </div>
            
        </form>
        
    </div>
</div>

<!-- CSS -->
<style>
.disco747-funnel {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.disco747-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.disco747-card-header {
    padding: 20px;
    border-bottom: 2px solid #f0f0f0;
    font-size: 18px;
    font-weight: 700;
    background: white;
}

.disco747-card-content {
    padding: 25px;
}

.kpi-box {
    padding: 25px;
    border-radius: 12px;
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    text-align: center;
}

.kpi-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.kpi-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 10px;
}

.kpi-value {
    font-size: 2rem;
    font-weight: 800;
}
</style>

<!-- JavaScript -->
<script>
function showAddStepModal(funnelType) {
    document.getElementById('modal-title').textContent = '➕ Aggiungi Step';
    document.getElementById('edit-sequence-id').value = '';
    document.getElementById('edit-funnel-type').value = funnelType;
    document.getElementById('edit-step-number').value = '';
    document.getElementById('edit-days-offset').value = '';
    document.getElementById('edit-send-time').value = '09:00';
    document.getElementById('edit-step-name').value = '';
    document.getElementById('edit-email-enabled').checked = true;
    document.getElementById('edit-email-subject').value = '';
    document.getElementById('edit-email-body').value = '';
    document.getElementById('edit-whatsapp-enabled').checked = false;
    document.getElementById('edit-whatsapp-text').value = '';
    document.getElementById('edit-active').checked = true;
    
    document.getElementById('sequence-modal').style.display = 'block';
}

function editSequence(sequenceId) {
    console.log('✏️ Caricamento sequenza ID:', sequenceId);
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'disco747_get_funnel_sequence',
            nonce: '<?php echo wp_create_nonce('disco747_funnel_nonce'); ?>',
            sequence_id: sequenceId
        },
        success: function(response) {
            if (response.success && response.data) {
                const seq = response.data;
                
                // Precompila form
                document.getElementById('modal-title').textContent = '✏️ Modifica Step #' + seq.step_number;
                document.getElementById('edit-sequence-id').value = seq.id;
                document.getElementById('edit-funnel-type').value = seq.funnel_type;
                document.getElementById('edit-step-number').value = seq.step_number;
                document.getElementById('edit-days-offset').value = seq.days_offset;
                document.getElementById('edit-send-time').value = seq.send_time ? seq.send_time.substring(0, 5) : '09:00';
                document.getElementById('edit-step-name').value = seq.step_name || '';
                document.getElementById('edit-email-enabled').checked = seq.email_enabled == 1;
                document.getElementById('edit-email-subject').value = seq.email_subject || '';
                document.getElementById('edit-email-body').value = seq.email_body || '';
                document.getElementById('edit-whatsapp-enabled').checked = seq.whatsapp_enabled == 1;
                document.getElementById('edit-whatsapp-text').value = seq.whatsapp_text || '';
                document.getElementById('edit-active').checked = seq.active == 1;
                
                // Mostra modal
                document.getElementById('sequence-modal').style.display = 'block';
            } else {
                alert('❌ Errore: ' + (response.data || 'Impossibile caricare la sequenza'));
            }
        },
        error: function() {
            alert('❌ Errore di connessione al server');
        }
    });
}

function closeModal() {
    document.getElementById('sequence-modal').style.display = 'none';
}

// Chiudi modal cliccando fuori
document.getElementById('sequence-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<!-- ===================================================================
     MODALE ANTEPRIMA EMAIL + TEST - AGGIUNTO PER NUOVE FUNZIONALITÀ
     =================================================================== -->

<!-- MODALE ANTEPRIMA EMAIL -->
<div id="email-preview-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:100000;overflow-y:auto;">
    <div style="max-width:900px;margin:40px auto;position:relative;">
        <!-- Header Modal -->
        <div style="background:#fff;padding:20px;border-radius:12px 12px 0 0;border-bottom:2px solid #e9ecef;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <h2 style="margin:0;font-size:24px;color:#333;">
                    📧 Anteprima Email
                </h2>
                <button onclick="closeEmailPreview()" style="background:none;border:none;font-size:28px;cursor:pointer;color:#666;line-height:1;">&times;</button>
            </div>
            
            <!-- Oggetto Email -->
            <div style="margin-top:15px;padding:12px;background:#f8f9fa;border-radius:8px;border-left:4px solid #007bff;">
                <strong style="color:#0056b3;">Oggetto:</strong>
                <span id="preview-subject" style="color:#333;margin-left:8px;"></span>
            </div>
            
            <!-- Azioni -->
            <div style="margin-top:15px;display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="showTestEmailForm()" class="button button-primary" style="background:#28a745;border-color:#28a745;">
                    📤 Invia Email di Test
                </button>
                <input type="email" id="test-email-input" placeholder="tua-email@example.com" style="display:none;padding:8px 12px;border:2px solid #28a745;border-radius:6px;flex:1;min-width:250px;">
                <button id="send-test-btn" onclick="sendTestEmail()" class="button button-primary" style="display:none;background:#28a745;border-color:#28a745;">
                    ✅ Conferma Invio
                </button>
                <button onclick="hideTestEmailForm()" class="button" style="display:none;" id="cancel-test-btn">
                    ❌ Annulla
                </button>
            </div>
            
            <!-- Messaggio test -->
            <div id="test-email-message" style="display:none;margin-top:10px;padding:12px;border-radius:8px;"></div>
        </div>
        
        <!-- Contenuto Email (iframe) -->
        <div style="background:#fff;padding:0;border-radius:0 0 12px 12px;">
            <iframe id="email-preview-iframe" style="width:100%;min-height:600px;border:none;border-radius:0 0 12px 12px;"></iframe>
        </div>
    </div>
</div>

<!-- JAVASCRIPT PER ANTEPRIMA E TEST EMAIL -->
<script>
// Variabile globale per tenere traccia della sequenza corrente
let currentPreviewSequenceId = null;

// Mostra anteprima email
function previewEmail(sequenceId) {
    currentPreviewSequenceId = sequenceId;
    
    // Mostra modal
    document.getElementById('email-preview-modal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Mostra loading
    document.getElementById('preview-subject').textContent = 'Caricamento...';
    const iframe = document.getElementById('email-preview-iframe');
    iframe.srcdoc = '<div style="text-align:center;padding:50px;font-family:sans-serif;color:#666;">⏳ Caricamento anteprima...</div>';
    
    // Richiesta AJAX
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'disco747_preview_funnel_email',
            nonce: '<?php echo wp_create_nonce('disco747_funnel_nonce'); ?>',
            sequence_id: sequenceId
        },
        success: function(response) {
            if (response.success && response.data) {
                document.getElementById('preview-subject').textContent = response.data.subject;
                iframe.srcdoc = response.data.html;
            } else {
                iframe.srcdoc = '<div style="text-align:center;padding:50px;font-family:sans-serif;color:#d32f2f;">❌ Errore durante il caricamento dell\'anteprima</div>';
            }
        },
        error: function() {
            iframe.srcdoc = '<div style="text-align:center;padding:50px;font-family:sans-serif;color:#d32f2f;">❌ Errore di connessione</div>';
        }
    });
}

// Chiudi modale anteprima
function closeEmailPreview() {
    document.getElementById('email-preview-modal').style.display = 'none';
    document.body.style.overflow = 'auto';
    hideTestEmailForm();
    currentPreviewSequenceId = null;
}

// Mostra form test email
function showTestEmailForm() {
    document.getElementById('test-email-input').style.display = 'block';
    document.getElementById('send-test-btn').style.display = 'inline-block';
    document.getElementById('cancel-test-btn').style.display = 'inline-block';
    document.querySelector('[onclick="showTestEmailForm()"]').style.display = 'none';
    document.getElementById('test-email-message').style.display = 'none';
    
    // Suggerisci email utente corrente se disponibile
    <?php
    $current_user = wp_get_current_user();
    if ($current_user && $current_user->user_email) {
        echo "document.getElementById('test-email-input').value = '{$current_user->user_email}';";
    }
    ?>
}

// Nascondi form test email
function hideTestEmailForm() {
    document.getElementById('test-email-input').style.display = 'none';
    document.getElementById('send-test-btn').style.display = 'none';
    document.getElementById('cancel-test-btn').style.display = 'none';
    document.querySelector('[onclick="showTestEmailForm()"]').style.display = 'inline-block';
    document.getElementById('test-email-input').value = '';
    document.getElementById('test-email-message').style.display = 'none';
}

// Invia email di test
function sendTestEmail() {
    const testEmail = document.getElementById('test-email-input').value;
    const messageDiv = document.getElementById('test-email-message');
    
    if (!testEmail || !testEmail.includes('@')) {
        messageDiv.style.display = 'block';
        messageDiv.style.background = '#ffebee';
        messageDiv.style.color = '#c62828';
        messageDiv.style.borderLeft = '4px solid #c62828';
        messageDiv.textContent = '❌ Inserisci un indirizzo email valido';
        return;
    }
    
    // Mostra loading
    messageDiv.style.display = 'block';
    messageDiv.style.background = '#e3f2fd';
    messageDiv.style.color = '#1565c0';
    messageDiv.style.borderLeft = '4px solid #1565c0';
    messageDiv.textContent = '⏳ Invio in corso...';
    
    // Disabilita pulsante
    const sendBtn = document.getElementById('send-test-btn');
    sendBtn.disabled = true;
    sendBtn.textContent = '⏳ Invio...';
    
    // Richiesta AJAX
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'disco747_test_funnel_email',
            nonce: '<?php echo wp_create_nonce('disco747_funnel_nonce'); ?>',
            sequence_id: currentPreviewSequenceId,
            test_email: testEmail
        },
        success: function(response) {
            sendBtn.disabled = false;
            sendBtn.textContent = '✅ Conferma Invio';
            
            if (response.success) {
                messageDiv.style.background = '#e8f5e9';
                messageDiv.style.color = '#2e7d32';
                messageDiv.style.borderLeft = '4px solid #2e7d32';
                messageDiv.innerHTML = '✅ ' + response.data + '<br><small>Controlla la tua casella di posta (anche spam)</small>';
                
                // Nascondi form dopo 3 secondi
                setTimeout(function() {
                    hideTestEmailForm();
                }, 3000);
            } else {
                messageDiv.style.background = '#ffebee';
                messageDiv.style.color = '#c62828';
                messageDiv.style.borderLeft = '4px solid #c62828';
                messageDiv.textContent = '❌ ' + response.data;
            }
        },
        error: function() {
            sendBtn.disabled = false;
            sendBtn.textContent = '✅ Conferma Invio';
            messageDiv.style.background = '#ffebee';
            messageDiv.style.color = '#c62828';
            messageDiv.style.borderLeft = '4px solid #c62828';
            messageDiv.textContent = '❌ Errore di connessione. Riprova.';
        }
    });
}

// Chiudi modale cliccando fuori
document.getElementById('email-preview-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEmailPreview();
    }
});

// Chiudi con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('email-preview-modal').style.display === 'block') {
        closeEmailPreview();
    }
});
</script>
