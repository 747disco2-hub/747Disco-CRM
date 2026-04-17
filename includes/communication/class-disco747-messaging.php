<?php
/**
 * 747 Disco CRM - Messaging Handler
 * 
 * Gestisce l'invio coordinato di messaggi email e WhatsApp per i preventivi.
 * 
 * @package    747Disco_CRM
 * @subpackage Communication
 * @since      1.0.0
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe Disco747_Messaging
 * 
 * NOTA: Questa classe NON usa namespace per compatibilità
 */
class Disco747_Messaging {

    /**
     * Istanza di configurazione
     * @var object
     */
    private $config;

    /**
     * Modalità debug
     * @var bool
     */
    private $debug_mode;

    /**
     * Sistema template
     * @var object
     */
    private $templates;

    /**
     * Handler email
     * @var Disco747_Email
     */
    private $email_handler;

    /**
     * Handler WhatsApp
     * @var Disco747_WhatsApp
     */
    private $whatsapp_handler;

    /**
     * Eventi trigger supportati
     */
    const TRIGGER_EVENTS = array(
        'new_preventivo',
        'preventivo_confirmed',
        'preventivo_cancelled',
        'reminder_sent',
        'followup_sent'
    );

    /**
     * Costruttore
     */
    public function __construct() {
        // Usa la classe con namespace completo o alias se disponibile
        if (class_exists('Disco747_Config')) {
            $this->config = Disco747_Config::get_instance();
        } elseif (class_exists('Disco747_CRM\\Core\\Disco747_Config')) {
            $this->config = Disco747_CRM\Core\Disco747_Config::get_instance();
        } else {
            throw new Exception('Classe Config non trovata');
        }
        
        $this->debug_mode = $this->config->get('debug_mode', false);

        // Carica template system
        if (class_exists('Disco747_Templates')) {
            $this->templates = Disco747_Templates::get_instance();
        } elseif (class_exists('Disco747_CRM\\Generators\\Disco747_Templates')) {
            $this->templates = Disco747_CRM\Generators\Disco747_Templates::get_instance();
        }

        // Inizializza handlers
        $this->email_handler = new Disco747_Email();
        $this->whatsapp_handler = new Disco747_WhatsApp();

        $this->setup_hooks();

        $this->log('Messaging Manager inizializzato');
    }

    /**
     * Setup hooks WordPress
     */
    private function setup_hooks() {
        // Hook per invii programmati
        add_action('disco747_send_reminder', array($this, 'send_scheduled_reminder'), 10, 1);
        add_action('disco747_send_followup', array($this, 'send_scheduled_followup'), 10, 1);
    }

    /**
     * Invia comunicazione completa per un preventivo
     * 
     * @param array  $preventivo_data Dati preventivo
     * @param string $send_mode       Modalità invio ('email', 'whatsapp', 'both', 'none')
     * @param array  $options         Opzioni aggiuntive
     * @return array Risultati invio
     */
    public function send_preventivo_communication($preventivo_data, $send_mode = 'both', $options = array()) {
        $this->log("Invio comunicazione preventivo modalità: $send_mode");

        $results = array(
            'email' => null,
            'whatsapp' => null,
            'success' => false,
            'errors' => array(),
        );

        try {
            $pdf_path = null;

            // Genera PDF se necessario per email
            if (in_array($send_mode, array('email', 'both'))) {
                $pdf_path = $this->generate_pdf_for_communication($preventivo_data);
            }

            // Invio Email
            if (in_array($send_mode, array('email', 'both'))) {
                try {
                    $email_sent = $this->email_handler->send_preventivo_email(
                        $preventivo_data,
                        $pdf_path,
                        $options['email'] ?? array()
                    );
                    $results['email'] = array(
                        'success' => $email_sent, 
                        'pdf_attached' => !empty($pdf_path)
                    );
                } catch (Exception $e) {
                    $results['errors'][] = 'Email: ' . $e->getMessage();
                    $results['email'] = array(
                        'success' => false, 
                        'error' => $e->getMessage()
                    );
                }
            }

            // Generazione link WhatsApp
            if (in_array($send_mode, array('whatsapp', 'both'))) {
                try {
                    $whatsapp_url = $this->whatsapp_handler->generate_preventivo_link(
                        $preventivo_data,
                        'default',
                        $options['whatsapp'] ?? array()
                    );
                    $results['whatsapp'] = array(
                        'success' => true, 
                        'url' => $whatsapp_url
                    );
                    $this->save_whatsapp_url_for_frontend($whatsapp_url);
                } catch (Exception $e) {
                    $results['errors'][] = 'WhatsApp: ' . $e->getMessage();
                    $results['whatsapp'] = array(
                        'success' => false, 
                        'error' => $e->getMessage()
                    );
                }
            }

            // Pulisce PDF temporaneo
            if ($pdf_path && file_exists($pdf_path)) {
                unlink($pdf_path);
            }

            // Valuta successo complessivo
            $results['success'] = $this->evaluate_communication_success($results, $send_mode);

            // Notifica team se richiesto
            if ($results['success'] && ($options['notify_team'] ?? true)) {
                $this->email_handler->send_team_notification($preventivo_data, 'preventivo_created');
            }

            $this->log("Comunicazione completata - Successo: " . ($results['success'] ? 'SI' : 'NO'));

            return $results;

        } catch (Exception $e) {
            $this->log("Errore comunicazione: " . $e->getMessage());
            $results['errors'][] = 'Generale: ' . $e->getMessage();
            return $results;
        }
    }

    /**
     * Invia email con allegato
     * 
     * @param array $form_data Dati form
     * @param string $pdf_path Path PDF
     * @param bool $is_update Se è un aggiornamento
     * @return array Risultato
     */
    public function send_email_with_attachment($form_data, $pdf_path, $is_update = false) {
        return $this->email_handler->send_email_with_attachment($form_data, $pdf_path, $is_update);
    }

    /**
     * Prepara messaggio WhatsApp
     * 
     * @param array $form_data Dati form
     * @param bool $is_update Se è un aggiornamento
     * @return array Risultato con URL
     */
    public function prepare_whatsapp_message($form_data, $is_update = false) {
        return $this->whatsapp_handler->prepare_whatsapp_message($form_data, $is_update);
    }

    /**
     * Valuta se la comunicazione è stata complessivamente un successo
     * 
     * @param array $results Risultati
     * @param string $send_mode Modalità invio
     * @return bool Success
     */
    private function evaluate_communication_success($results, $send_mode) {
        if ($send_mode === 'both') {
            return (!empty($results['email']['success']) || !empty($results['whatsapp']['success']));
        } elseif ($send_mode === 'email') {
            return !empty($results['email']['success']);
        } elseif ($send_mode === 'whatsapp') {
            return !empty($results['whatsapp']['success']);
        }
        return false;
    }

    /**
     * Genera PDF per comunicazione
     * 
     * @param array $preventivo_data Dati preventivo
     * @return string|null Path del PDF
     */
    private function generate_pdf_for_communication($preventivo_data) {
        if (!empty($preventivo_data)) {
            // Verifica se la classe PDF esiste
            $pdf_class = null;
            if (class_exists('Disco747_PDF')) {
                $pdf_class = 'Disco747_PDF';
            } elseif (class_exists('Disco747_CRM\\Generators\\Disco747_PDF')) {
                $pdf_class = 'Disco747_CRM\\Generators\\Disco747_PDF';
            }
            
            if ($pdf_class) {
                $pdf_generator = new $pdf_class();
                $temp_path = $this->config->get_upload_path('temp') . 'temp_' . time() . '.pdf';
                
                if ($pdf_generator->generate_pdf($preventivo_data, $temp_path)) {
                    return $temp_path;
                }
            }
        }
        return null;
    }

    /**
     * Salva URL WhatsApp per utilizzo nel frontend
     * 
     * @param string $whatsapp_url URL WhatsApp
     */
    private function save_whatsapp_url_for_frontend($whatsapp_url) {
        update_option('disco747_last_whatsapp_url', $whatsapp_url);
        wp_schedule_single_event(time() + 300, 'disco747_clear_whatsapp_url');
    }

    /**
     * Invia promemoria programmato
     * 
     * @param int $preventivo_id ID preventivo
     */
    public function send_scheduled_reminder($preventivo_id) {
        $database = disco747_get_component('database');
        if (!$database) {
            return;
        }
        
        $preventivo = $database->get_preventivo($preventivo_id);
        if (!$preventivo) {
            return;
        }
        
        // Converti oggetto in array
        $preventivo_data = (array) $preventivo;
        
        // Invia promemoria
        $this->send_preventivo_communication(
            $preventivo_data, 
            'email', 
            array('template_type' => 'reminder')
        );
        
        $this->log("Promemoria inviato per preventivo ID: {$preventivo_id}");
    }

    /**
     * Invia follow-up programmato
     * 
     * @param int $preventivo_id ID preventivo
     */
    public function send_scheduled_followup($preventivo_id) {
        $database = disco747_get_component('database');
        if (!$database) {
            return;
        }
        
        $preventivo = $database->get_preventivo($preventivo_id);
        if (!$preventivo) {
            return;
        }
        
        // Converti oggetto in array
        $preventivo_data = (array) $preventivo;
        
        // Invia follow-up
        $this->send_preventivo_communication(
            $preventivo_data, 
            'both', 
            array('template_type' => 'followup')
        );
        
        $this->log("Follow-up inviato per preventivo ID: {$preventivo_id}");
    }

    /**
     * Programma promemoria per un preventivo
     * 
     * @param int $preventivo_id ID preventivo
     * @param int $days_before Giorni prima dell'evento
     * @return bool Success
     */
    public function schedule_reminder($preventivo_id, $days_before = 3) {
        $database = disco747_get_component('database');
        if (!$database) {
            return false;
        }
        
        $preventivo = $database->get_preventivo($preventivo_id);
        if (!$preventivo) {
            return false;
        }
        
        $event_date = strtotime($preventivo->data_evento);
        $reminder_date = $event_date - ($days_before * DAY_IN_SECONDS);
        
        if ($reminder_date > time()) {
            wp_schedule_single_event($reminder_date, 'disco747_send_reminder', array($preventivo_id));
            $this->log("Promemoria programmato per preventivo {$preventivo_id} il " . date('Y-m-d H:i:s', $reminder_date));
            return true;
        }
        
        return false;
    }

    /**
     * Ottieni statistiche messaggistica
     * 
     * @return array Statistiche
     */
    public function get_messaging_stats() {
        $email_log = get_option('disco747_email_delivery_log', array());
        $whatsapp_log = get_option('disco747_whatsapp_delivery_log', array());
        
        $stats = array(
            'email_sent' => count($email_log),
            'email_success' => count(array_filter($email_log, function($log) {
                return $log['success'] ?? false;
            })),
            'whatsapp_generated' => count($whatsapp_log),
            'total_communications' => count($email_log) + count($whatsapp_log)
        );
        
        $stats['success_rate'] = $stats['total_communications'] > 0 
            ? round(($stats['email_success'] / $stats['total_communications']) * 100, 2)
            : 0;
            
        return $stats;
    }

    /**
     * Log interno
     * 
     * @param string $message Messaggio
     * @param string $level Livello
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode || $level === 'ERROR') {
            error_log("[747Disco-Messaging] [{$level}] {$message}");
        }
    }
}