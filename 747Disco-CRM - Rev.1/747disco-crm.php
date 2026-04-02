<?php
/**
 * Plugin Name: 747 Disco CRM - PreventiviParty Enhanced
 * Plugin URI: https://gestionale.747disco.it
 * Description: Sistema CRM completo per la gestione dei preventivi della location 747 Disco.  Replica del vecchio PreventiviParty con funzionalità avanzate.
 * Version: 12.0.0
 * Author: 747 Disco Team
 * Author URI: https://gestionale.747disco.it
 * Text Domain: disco747
 * Domain Path: /languages/
 * Requires at least: 5.8
 * Tested up to: 6.4.2
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package    Disco747_CRM
 * @version    12.0.0
 * @author     747 Disco Team
 */

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// ========================================================================
// COSTANTI DEL PLUGIN - CONFIGURATION
// ========================================================================

// Versione plugin
define('DISCO747_CRM_VERSION', '12.0.0');

// Percorsi del plugin
define('DISCO747_CRM_PLUGIN_FILE', __FILE__);
define('DISCO747_CRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DISCO747_CRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DISCO747_CRM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Prefissi database
define('DISCO747_CRM_DB_PREFIX', 'disco747_');

// Debug mode (può essere disabilitato in produzione)
define('DISCO747_CRM_DEBUG', true);

// ========================================================================
// FILTRI GLOBALI PER EMAIL HTML - v12.0.0
// ========================================================================

/**
 * ✅ FILTRO GLOBALE: Garantisce che le email with "Content-Type: text/html"
 * siano inviate correttamente da wp_mail()
 * 
 * Questo filtro intercetta il corpo dell'email e lo restituisce invariato,
 * ma PHPMailer lo riconosce come HTML grazie agli header.
 * 
 * @since 12.0.0
 */
add_filter('wp_mail', function($args) {
    // Se è definito 'Content-Type: text/html' negli header, non fare nulla
    // wp_mail() lo gestirà automaticamente
    return $args;
});

// ========================================================================
// CLASSE PRINCIPALE DEL PLUGIN - VERSIONE COMPLETA E CORRETTA
// ========================================================================

/**
 * Classe principale del plugin 747 Disco CRM
 * VERSIONE CORRETTA: Risolve tutti i problemi di inizializzazione e logging
 *
 * @since 12.0.0
 */
final class Disco747_CRM_Plugin {
    
    /**
     * Istanza singleton
     */
    private static $instance = null;
    
    /**
     * Componenti del plugin
     */
    private $config = null;
    private $database = null;
    private $auth = null;
    private $admin = null;
    private $frontend = null;
    private $storage_manager = null;
    private $email_manager = null;
    private $pdf_generator = null;
    private $excel_generator = null;
    private $gdrive_sync = null;
    private $forms_handler = null;
    
    /**
     * Stato inizializzazione
     */
    private $initialized = false;
    private $debug_mode = DISCO747_CRM_DEBUG;
    
    /**
     * Costruttore privato per singleton
     */
    private function __construct() {
        // Registra hook di attivazione/disattivazione
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
        
        // Inizializzazione principale
        add_action('plugins_loaded', array($this, 'init_plugin'), 10);
        
        // Hook per cleanup
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_files'));
    }

    /**
     * Ottieni istanza singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inizializzazione plugin - SAFE VERSION
     */
    public function init_plugin() {
        try {
            // Log inizializzazione
            $this->public_log('🚀 Inizializzazione 747 Disco CRM v' . DISCO747_CRM_VERSION);
            
            // Carica autoloader
            $this->load_autoloader();
            
            // Inizializza componenti core
            $this->init_core_components();
            
            // Inizializza componenti aggiuntivi
            $this->init_additional_components();
            
            // Registra hook finali
            $this->register_final_hooks();
            
            $this->initialized = true;
            $this->public_log('✅ Plugin inizializzato correttamente');
            
        } catch (Exception $e) {
            $this->public_log('❌ Errore inizializzazione: ' . $e->getMessage(), 'ERROR');
            add_action('admin_notices', array($this, 'show_init_error_notice'));
        }
    }

    /**
     * Carica autoloader SAFE - FIXED con storage dependencies + AJAX handlers + Excel Scan
     */
    private function load_autoloader() {
        // Carica le classi principali manualmente per sicurezza
        $core_files = array(
            'includes/core/class-disco747-config.php',
            'includes/core/class-disco747-database.php',
            'includes/core/class-disco747-auth.php',
            'includes/admin/class-disco747-admin.php',
            // AGGIUNTO: File storage necessari PRIMA del Storage Manager
            'includes/storage/class-disco747-googledrive.php',
            'includes/storage/class-disco747-dropbox.php',
            'includes/storage/class-disco747-storage-manager.php',
            // ✅ AGGIUNTO: Excel Scan Handler REALE
            'includes/handlers/class-disco747-excel-scan-handler.php',
            // ✅ AGGIUNTO: AJAX Handlers per Excel Scan
            'includes/admin/ajax-handlers.php',
            // ✅ AGGIUNTO: Funnel Marketing System
            // ✅ AGGIUNTO: Processor - CRITICO per lanciare gli hook funnel
            'includes/handlers/class-disco747-processor.php',
            'includes/funnel/class-disco747-funnel-database.php',
            'includes/funnel/class-disco747-funnel-manager.php',
            'includes/funnel/class-disco747-funnel-scheduler.php'
        );
        
        $loaded_files = 0;
        $missing_files = array();
        $optional_missing = array();
        
        foreach ($core_files as $file) {
            $file_path = DISCO747_CRM_PLUGIN_DIR . $file;
            
            if (file_exists($file_path)) {
                require_once $file_path;
                $loaded_files++;
                $this->public_log("✅ Caricato: {$file}");
            } else {
                // Alcuni file storage potrebbero non esistere ancora
                if (strpos($file, 'storage/') !== false && strpos($file, 'storage-manager') === false) {
                    $optional_missing[] = $file;
                    $this->public_log("⚠️ File storage opzionale mancante: {$file}", 'WARNING');
                } elseif (strpos($file, 'ajax-handlers.php') !== false) {
                    // AJAX handlers è opzionale
                    $optional_missing[] = $file;
                    $this->public_log("⚠️ AJAX handlers opzionale mancante: {$file}", 'WARNING');
                } elseif (strpos($file, 'excel-scan-handler.php') !== false) {
                    // Excel scan handler è opzionale
                    $optional_missing[] = $file;
                    $this->public_log("⚠️ Excel scan handler opzionale mancante: {$file}", 'WARNING');
                } elseif (strpos($file, 'funnel/') !== false) {
                    // Funnel system è opzionale
                    $optional_missing[] = $file;
                    $this->public_log("⚠️ File funnel opzionale mancante: {$file}", 'WARNING');
                } else {
                    $missing_files[] = $file;
                    $this->public_log("❌ File core critico mancante: {$file}", 'ERROR');
                }
            }
        }
        
        // Solo i file critici sono obbligatori
        if (count($missing_files) > 0) {
            throw new Exception("File core critici mancanti: " . implode(', ', $missing_files));
        }
        
        $message = "✅ Autoloader caricato ({$loaded_files} file core";
        if (count($optional_missing) > 0) {
            $message .= ", " . count($optional_missing) . " file opzionali mancanti";
        }
        $message .= ")";
        
        $this->public_log($message);
    }

    /**
     * Inizializza componenti core - FIXED: Verifica presenza get_instance()
     */
    private function init_core_components() {
        // Config Manager (Singleton confermato)
        if (class_exists('Disco747_CRM\\Core\\Disco747_Config')) {
            $this->config = Disco747_CRM\Core\Disco747_Config::get_instance();
        }
        
        // Database Manager (NON singleton - usa costruttore normale)
        if (class_exists('Disco747_CRM\\Core\\Disco747_Database')) {
            if (method_exists('Disco747_CRM\\Core\\Disco747_Database', 'get_instance')) {
                $this->database = Disco747_CRM\Core\Disco747_Database::get_instance();
            } else {
                $this->database = new Disco747_CRM\Core\Disco747_Database();
            }
        }
        
        // Auth Manager (verifica se è singleton)
        if (class_exists('Disco747_CRM\\Core\\Disco747_Auth')) {
            if (method_exists('Disco747_CRM\\Core\\Disco747_Auth', 'get_instance')) {
                $this->auth = Disco747_CRM\Core\Disco747_Auth::get_instance();
            } else {
                $this->auth = new Disco747_CRM\Core\Disco747_Auth();
            }
        }
        
        // Storage Manager (verifica se è singleton)
        if (class_exists('Disco747_CRM\\Storage\\Disco747_Storage_Manager')) {
            if (method_exists('Disco747_CRM\\Storage\\Disco747_Storage_Manager', 'get_instance')) {
                $this->storage_manager = Disco747_CRM\Storage\Disco747_Storage_Manager::get_instance();
            } else {
                $this->storage_manager = new Disco747_CRM\Storage\Disco747_Storage_Manager();
            }
        }
        
        $this->public_log('✅ Componenti core inizializzati');
    }

    /**
     * Inizializza componenti aggiuntivi SAFE - FIXED con gestione errori robusta
     */
    private function init_additional_components() {
        // Admin Manager
        try {
            if (class_exists('Disco747_CRM\\Admin\\Disco747_Admin')) {
                if (method_exists('Disco747_CRM\\Admin\\Disco747_Admin', 'get_instance')) {
                    $this->admin = Disco747_CRM\Admin\Disco747_Admin::get_instance();
                } else {
                    $this->admin = new Disco747_CRM\Admin\Disco747_Admin();
                }
                $this->public_log('✅ Admin Manager caricato');
            }
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento Admin Manager: ' . $e->getMessage(), 'ERROR');
        }
        
        // PDF Generator
        try {
            add_action('init', function() {
                $pdf_path = DISCO747_CRM_PLUGIN_DIR . 'includes/generators/class-disco747-pdf.php';
                if (file_exists($pdf_path)) {
                    require_once $pdf_path;
                    $this->public_log('✅ Caricato: includes/generators/class-disco747-pdf.php');
                    
                    if (class_exists('Disco747_CRM\\Generators\\Disco747_PDF')) {
                        $this->pdf_generator = new Disco747_CRM\Generators\Disco747_PDF();
                        $this->public_log('✅ PDF Generator inizializzato');
                    }
                }
            });
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento PDF Generator: ' . $e->getMessage(), 'ERROR');
        }
        
        // Excel Generator
        try {
            add_action('init', function() {
                $excel_path = DISCO747_CRM_PLUGIN_DIR . 'includes/generators/class-disco747-excel.php';
                if (file_exists($excel_path)) {
                    require_once $excel_path;
                    $this->public_log('✅ Caricato: includes/generators/class-disco747-excel.php');
                    
                    if (class_exists('Disco747_CRM\\Generators\\Disco747_Excel')) {
                        $this->excel_generator = new Disco747_CRM\Generators\Disco747_Excel();
                        $this->public_log('✅ Excel Generator inizializzato');
                    }
                }
            });
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento Excel Generator: ' . $e->getMessage(), 'ERROR');
        }
        
        // Email Manager
        try {
            add_action('init', function() {
                $email_path = DISCO747_CRM_PLUGIN_DIR . 'includes/communication/class-disco747-email.php';
                if (file_exists($email_path)) {
                    require_once $email_path;
                    $this->public_log('✅ Caricato: includes/communication/class-disco747-email.php');
                    
                    if (class_exists('Disco747_CRM\\Communication\\Disco747_Email')) {
                        // Prova singleton prima
                        if (method_exists('Disco747_CRM\\Communication\\Disco747_Email', 'get_instance')) {
                            $this->email_manager = Disco747_CRM\Communication\Disco747_Email::get_instance();
                        } else {
                            $this->email_manager = new Disco747_CRM\Communication\Disco747_Email();
                        }
                    }
                }
            });
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento Email Manager: ' .  $e->getMessage(), 'ERROR');
        }
        
        // Messaging Manager
        try {
            add_action('init', function() {
                $messaging_path = DISCO747_CRM_PLUGIN_DIR . 'includes/communication/class-disco747-messaging.php';
                if (file_exists($messaging_path)) {
                    require_once $messaging_path;
                    $this->public_log('✅ Caricato: includes/communication/class-disco747-messaging.php');
                }
            });
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento Messaging Manager: ' . $e->getMessage(), 'ERROR');
        }
        
        // Google Drive Sync
        try {
            add_action('init', function() {
                $gdrive_sync_path = DISCO747_CRM_PLUGIN_DIR . 'includes/storage/class-disco747-googledrive-sync.php';
                if (file_exists($gdrive_sync_path)) {
                    require_once $gdrive_sync_path;
                    $this->public_log('✅ Caricato: includes/storage/class-disco747-googledrive-sync.php');
                    
                    if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive_Sync')) {
                        $this->gdrive_sync = new Disco747_CRM\Storage\Disco747_GoogleDrive_Sync();
                        $this->public_log('✅ Google Drive Sync inizializzato');
                    }
                }
            });
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento Google Drive Sync: ' . $e->getMessage(), 'ERROR');
        }
        
        // AJAX Handlers
        try {
            add_action('init', function() {
                $ajax_path = DISCO747_CRM_PLUGIN_DIR . 'includes/handlers/class-disco747-ajax.php';
                if (file_exists($ajax_path)) {
                    require_once $ajax_path;
                    $this->public_log('✅ Caricato: includes/handlers/class-disco747-ajax.php');
                    
                    if (class_exists('Disco747_CRM\\Handlers\\Disco747_AJAX')) {
                        // Inizializza AJAX handlers
                        new Disco747_CRM\Handlers\Disco747_AJAX();
                        $this->public_log('✅ AJAX Handlers inizializzato');
                    }
                }
            });
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento AJAX Handlers: ' . $e->getMessage(), 'ERROR');
        }
        
        // 🚀 FUNNEL SCHEDULER - CRITICO per far funzionare gli hook
        try {
            add_action('init', function() {
                if (class_exists('Disco747_CRM\\Funnel\\Disco747_Funnel_Scheduler')) {
                    new Disco747_CRM\Funnel\Disco747_Funnel_Scheduler();
                    $this->public_log('🎯 Funnel Scheduler inizializzato e in ascolto degli hook');
                } else {
                    $this->public_log('⚠️ Classe Disco747_Funnel_Scheduler non trovata', 'WARNING');
                }
            }, 5); // Priority 5 - prima di altri componenti ma dopo il core
        } catch (Exception $e) {
            $this->public_log('❌ Errore inizializzazione Funnel Scheduler: ' . $e->getMessage(), 'ERROR');
        }
        
        // 🎯 PROCESSOR - CRITICO per lanciare gli hook do_action
        try {
            add_action('init', function() {
                if (class_exists('Disco747_CRM\\Handlers\\Disco747_Processor')) {
                    new Disco747_CRM\Handlers\Disco747_Processor();
                    $this->public_log('🎯 Processor inizializzato - Pronto a lanciare hook funnel');
                } else {
                    $this->public_log('⚠️ Classe Disco747_Processor non trovata', 'WARNING');
                }
            }, 10); // Priority 10 - DOPO il Funnel Scheduler (priority 5)
        } catch (Exception $e) {
            $this->public_log('❌ Errore inizializzazione Processor: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Registra hook finali
     */
    private function register_final_hooks() {
        // Hook per l'inizializzazione completata di WordPress
        add_action('wp_loaded', array($this, 'wp_init_complete'));
        
        // Forms Handler - Carica dopo che tutto è pronto
        try {
            add_action('wp_loaded', function() {
                $forms_path = DISCO747_CRM_PLUGIN_DIR .  'includes/handlers/class-disco747-forms.php';
                if (file_exists($forms_path)) {
                    require_once $forms_path;
                    $this->public_log('✅ Caricato: includes/handlers/class-disco747-forms.php');
                }
                
                if (class_exists('Disco747_CRM\\Handlers\\Disco747_Forms')) {
                    $this->forms_handler = new Disco747_CRM\Handlers\Disco747_Forms();
                    $this->public_log('✅ Forms Handler caricato');
                }
            });
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento Forms Handler: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Callback WordPress init completato
     */
    public function wp_init_complete() {
        $this->public_log('✅ Init WordPress completato');
        
        // Auto-migrazione dal vecchio plugin se presente
        $this->auto_migrate_if_needed();
    }

    /**
     * Auto-migrazione dal vecchio plugin se necessario
     */
    private function auto_migrate_if_needed() {
        // Controlla se esiste già la tabella del vecchio plugin
        global $wpdb;
        $old_table = $wpdb->prefix . 'preventivi_party';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$old_table}'") && $this->database) {
            $this->public_log('🔄 Rilevata tabella vecchio plugin, avvio migrazione');
            
            try {
                // Esegui migrazione automatica se il database manager lo supporta
                if (method_exists($this->database, 'migrate_from_old_plugin')) {
                    $this->database->migrate_from_old_plugin();
                    $this->public_log('✅ Migrazione completata');
                }
            } catch (Exception $e) {
                $this->public_log('❌ Errore migrazione: ' . $e->getMessage(), 'ERROR');
            }
        }
    }

    // ============================================================================
    // METODI GETTER PER COMPONENTI
    // ============================================================================

    /**
     * Verifica se il plugin è inizializzato
     */
    public function is_initialized() {
        return $this->initialized;
    }

    /**
     * Ottieni componente Config
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Ottieni componente Database
     */
    public function get_database() {
        return $this->database;
    }

    /**
     * Ottieni componente Auth
     */
    public function get_auth() {
        return $this->auth;
    }

    /**
     * Ottieni componente Admin
     */
    public function get_admin() {
        return $this->admin;
    }

    /**
     * Ottieni componente Storage Manager
     */
    public function get_storage_manager() {
        return $this->storage_manager;
    }

    /**
     * Ottieni componente Email Manager
     */
    public function get_email() {
        return $this->email_manager;
    }

    /**
     * Ottieni componente PDF Generator
     */
    public function get_pdf() {
        return $this->pdf_generator;
    }

    /**
     * Ottieni componente Excel Generator
     */
    public function get_excel() {
        return $this->excel_generator;
    }

    /**
     * Ottieni componente Google Drive Sync
     */
    public function get_gdrive_sync() {
        return $this->gdrive_sync;
    }

    /**
     * Ottieni componente Forms Handler
     */
    public function get_forms_handler() {
        return $this->forms_handler;
    }

    /**
     * ALIAS: get_forms() per compatibilità
     */
    public function get_forms() {
        return $this->forms_handler;
    }

    // ============================================================================
    // HOOK ATTIVAZIONE/DISATTIVAZIONE
    // ============================================================================

    /**
     * Hook attivazione plugin
     */
    public function activate_plugin() {
        try {
            $this->public_log('🚀 Attivazione plugin 747 Disco CRM v' . DISCO747_CRM_VERSION);
            
            // Crea tabelle database se necessario
            if ($this->database && method_exists($this->database, 'create_tables')) {
                $this->database->create_tables();
            }
            
            // Crea tabelle funnel
            if (class_exists('Disco747_CRM\\Funnel\\Disco747_Funnel_Database')) {
                $funnel_db = new Disco747_CRM\Funnel\Disco747_Funnel_Database();
                $funnel_db->create_tables();
                $this->public_log('✅ Tabelle funnel create');
            }
            
            // Attiva scheduler funnel
            if (class_exists('Disco747_CRM\\Funnel\\Disco747_Funnel_Scheduler')) {
                $scheduler = new Disco747_CRM\Funnel\Disco747_Funnel_Scheduler();
                $scheduler->activate();
                $this->public_log('✅ Scheduler funnel attivato');
            }
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            $this->public_log('✅ Plugin attivato con successo');
            
        } catch (Exception $e) {
            $this->public_log('❌ Errore attivazione: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Hook disattivazione plugin
     */
    public function deactivate_plugin() {
        try {
            $this->public_log('🛑 Disattivazione plugin 747 Disco CRM');
            
            // Disattiva scheduler funnel
            if (class_exists('Disco747_CRM\\Funnel\\Disco747_Funnel_Scheduler')) {
                $scheduler = new Disco747_CRM\Funnel\Disco747_Funnel_Scheduler();
                $scheduler->deactivate();
                $this->public_log('✅ Scheduler funnel disattivato');
            }
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Pulizia scheduled events
            wp_clear_scheduled_hook('disco747_cleanup_temp_files');
            
            $this->public_log('✅ Plugin disattivato');
            
        } catch (Exception $e) {
            $this->public_log('❌ Errore disattivazione: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * ✅ MODIFICATO: Cleanup file temporanei E PDF preventivi vecchi
     */
    public function cleanup_old_files() {
        try {
            $upload_dir = wp_upload_dir();
            $total_cleaned = 0;
            
            // 1️⃣ CLEANUP FILE TEMPORANEI (esistente - mantieni)
            $temp_dir = $upload_dir['basedir'] . '/preventivi/temp/';
            
            if (is_dir($temp_dir)) {
                $files = glob($temp_dir . '*');
                $count_temp = 0;
                
                foreach ($files as $file) {
                    if (is_file($file) && (time() - filemtime($file)) > 86400) { // 24 ore
                        if (unlink($file)) {
                            $count_temp++;
                        }
                    }
                }
                
                if ($count_temp > 0) {
                    $this->public_log("🗑️ Cleanup TEMP: {$count_temp} file temporanei eliminati");
                    $total_cleaned += $count_temp;
                }
            }
            
            // 2️⃣ NUOVO: CLEANUP PDF PREVENTIVI INVIATI VIA EMAIL
            $preventivi_dir = $upload_dir['basedir'] . '/preventivi/';
            
            if (is_dir($preventivi_dir)) {
                $pdf_files = glob($preventivi_dir . '*.pdf');
                $count_pdf = 0;
                $retention_days = 7; // Configurabile: elimina PDF dopo 7 giorni
                $retention_seconds = $retention_days * 86400;
                
                foreach ($pdf_files as $pdf_file) {
                    if (is_file($pdf_file)) {
                        $file_age = time() - filemtime($pdf_file);
                        
                        // Cancella solo PDF più vecchi di $retention_days giorni
                        if ($file_age > $retention_seconds) {
                            if (unlink($pdf_file)) {
                                $count_pdf++;
                                $this->public_log("🗑️ PDF eliminato: " . basename($pdf_file) . " (età: " . round($file_age / 86400, 1) . " giorni)");
                            }
                        }
                    }
                }
                
                if ($count_pdf > 0) {
                    $this->public_log("✅ Cleanup PDF: {$count_pdf} preventivi eliminati (>= {$retention_days} giorni)");
                    $total_cleaned += $count_pdf;
                }
            }
            
            // Log finale
            if ($total_cleaned > 0) {
                $this->public_log("✅ Cleanup completato: {$total_cleaned} file totali eliminati");
            } else {
                $this->public_log("ℹ️ Cleanup eseguito: nessun file da eliminare");
            }
            
        } catch (Exception $e) {
            $this->public_log('❌ Errore cleanup: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Mostra notice errore inizializzazione
     */
    public function show_init_error_notice() {
        echo '<div class="notice notice-error"><p><strong>747 Disco CRM:</strong> Errore inizializzazione plugin.  Controlla i log per dettagli.</p></div>';
    }

    /**
     * Log pubblico visibile (sempre attivo)
     */
    public function public_log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] [747Disco-CRM] [{$level}] {$message}";
        error_log($log_message);
    }
}

// ============================================================================
// INIZIALIZZAZIONE PLUGIN
// ============================================================================

/**
 * Funzione helper per ottenere l'istanza del plugin
 * 
 * @return Disco747_CRM_Plugin
 */
function disco747_crm() {
    return Disco747_CRM_Plugin::get_instance();
}

// Avvia il plugin
disco747_crm();