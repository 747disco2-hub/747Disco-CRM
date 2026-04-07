<?php
namespace Disco747_CRM\Admin;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Admin {
    
    private $config;
    private $database;
    private $auth;
    private $storage_manager;
    private $pdf_excel_handler;
    private $excel_handler;
    
    private $min_capability = 'manage_options';
    private $asset_version;
    private $admin_notices = array();
    private $hooks_registered = false;
    private $debug_mode = true;

    public function __construct() {
        $this->asset_version = defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.9.0';
        add_action('init', array($this, 'delayed_init'), 10);
    }

    public function delayed_init() {
        try {
            $this->load_dependencies();
            $this->register_admin_hooks();
            $this->log('Admin Manager inizializzato');
        } catch (\Exception $e) {
            $this->log('Errore inizializzazione Admin: ' . $e->getMessage(), 'error');
            $this->add_admin_notice('Errore inizializzazione 747 Disco CRM.', 'error');
        }
    }

    private function load_dependencies() {
        $disco747_crm = disco747_crm();
        if (!$disco747_crm || !$disco747_crm->is_initialized()) {
            throw new \Exception('Plugin principale non ancora inizializzato');
        }
        $this->config = $disco747_crm->get_config();
        $this->database = $disco747_crm->get_database();
        $this->auth = $disco747_crm->get_auth();
        $this->storage_manager = $disco747_crm->get_storage_manager();
        $this->pdf_excel_handler = $disco747_crm->get_pdf();
        $this->excel_handler = $disco747_crm->get_excel();
    }

    private function register_admin_hooks() {
        if ($this->hooks_registered) return;
        try {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('admin_notices', array($this, 'show_admin_notices'));
            add_filter('plugin_action_links_' . plugin_basename(DISCO747_CRM_PLUGIN_FILE), array($this, 'add_plugin_action_links'));
            
            add_action('wp_ajax_disco747_batch_scan_excel', array($this, 'handle_batch_scan'));
            add_action('wp_ajax_disco747_get_preventivo', array($this, 'handle_get_preventivo'));
            add_action('wp_ajax_disco747_delete_preventivo', array($this, 'handle_delete_preventivo'));
            add_action('wp_ajax_disco747_export_preventivi_csv', array($this, 'handle_export_csv'));
            add_action('wp_ajax_disco747_export_preventivi_excel', array($this, 'handle_export_excel'));
            add_action('wp_ajax_disco747_get_funnel_sequence', array($this, 'handle_get_funnel_sequence'));
            
            // ✅ NUOVO v12.1.0: Handler AJAX Test Email
            add_action('wp_ajax_disco747_send_test_email', array($this, 'handle_send_test_email'));

            // ✅ Handler per pagina Report Preventivi (admin-post.php)
            add_action('admin_post_disco747_force_report',      array($this, 'handle_force_report'));
            add_action('admin_post_disco747_test_report_email', array($this, 'handle_test_report_email'));
            add_action('admin_post_disco747_reschedule_report', array($this, 'handle_reschedule_report'));
            
            $this->hooks_registered = true;
            $this->log('Hook WordPress registrati (incluso batch scan e test email)');
        } catch (\Exception $e) {
            $this->log('Errore registrazione hook: ' . $e->getMessage(), 'error');
        }
    }

    public function add_admin_menu() {
        try {
            add_menu_page(
                __('747Disco-CRM', 'disco747'),
                __('747Disco-CRM', 'disco747'),
                $this->min_capability,
                'disco747-crm',
                array($this, 'render_main_dashboard'),
                'dashicons-clipboard',
                30
            );
            add_submenu_page(
                'disco747-crm',
                __('📊 Database Preventivi', 'disco747'),
                __('📊 Database Preventivi', 'disco747'),
                $this->min_capability,
                'disco747-view-preventivi',
                array($this, 'render_view_preventivi_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('📂 Scansione file GDrive', 'disco747'),
                __('📂 Scansione file GDrive', 'disco747'),
                $this->min_capability,
                'disco747-scan-excel',
                array($this, 'render_scan_excel_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('💰 KPI Finanziari', 'disco747'),
                __('💰 KPI Finanziari', 'disco747'),
                $this->min_capability,
                'disco747-financial',
                array($this, 'render_financial_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('💬 Configurazioni Email/Whatsapp', 'disco747'),
                __('💬 Configurazioni Email/Whatsapp', 'disco747'),
                $this->min_capability,
                'disco747-messages',
                array($this, 'render_messages_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('🚀 Configurazioni Funnel', 'disco747'),
                __('🚀 Configurazioni Funnel', 'disco747'),
                $this->min_capability,
                'disco747-funnel',
                array($this, 'render_funnel_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('📋 Report Preventivi', 'disco747'),
                __('📋 Report Preventivi', 'disco747'),
                $this->min_capability,
                'disco747-report-preventivi',
                array($this, 'render_report_preventivi_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('☁️ Impostazioni Cloud', 'disco747'),
                __('☁️ Impostazioni Cloud', 'disco747'),
                $this->min_capability,
                'disco747-settings',
                array($this, 'render_settings_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('🔍 Diagnostica Cella Data', 'disco747'),
                __('🔍 Diagnostica Cella Data', 'disco747'),
                $this->min_capability,
                'disco747-diagnostic',
                array($this, 'render_diagnostic_page')
            );
            add_submenu_page(
                'disco747-crm',
                __('🔬 Debug Excel analizzati', 'disco747'),
                __('🔬 Debug Excel analizzati', 'disco747'),
                $this->min_capability,
                'disco747-debug-structure',
                array($this, 'render_debug_structure_page')
            );
            if (get_option('disco747_debug_mode', false)) {
                add_submenu_page(
                    'disco747-crm',
                    __('🐛 Debug & Test', 'disco747'),
                    __('🐛 Debug & Test', 'disco747'),
                    $this->min_capability,
                    'disco747-debug',
                    array($this, 'render_debug_page')
                );
            }
            $this->log('Menu amministrazione aggiunto');
        } catch (\Exception $e) {
            $this->log('Errore aggiunta menu: ' . $e->getMessage(), 'error');
        }
    }

    public function enqueue_admin_assets($hook_suffix) {
        $this->log('Hook suffix: ' . $hook_suffix);
        
        if (strpos($hook_suffix, 'disco747') === false) return;
        
        try {
            wp_enqueue_style('disco747-admin-style', DISCO747_CRM_PLUGIN_URL . 'assets/css/admin.css', array(), $this->asset_version);
            wp_enqueue_script('disco747-admin-script', DISCO747_CRM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), $this->asset_version, true);
            
            if (strpos($hook_suffix, 'disco747-scan-excel') !== false) {
                $this->log('EXCEL SCAN RILEVATO!');
                
                wp_enqueue_script(
                    'disco747-excel-scan-js',
                    DISCO747_CRM_PLUGIN_URL . 'assets/js/excel-scan.js',
                    array('jquery'),
                    $this->asset_version,
                    true
                );
                
                wp_localize_script('disco747-excel-scan-js', 'disco747ExcelScanData', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('disco747_batch_scan'),
                    'i18n' => array(
                        'error' => __('Errore', 'disco747'),
                        'success' => __('Successo', 'disco747'),
                        'processing' => __('Elaborazione in corso...', 'disco747')
                    )
                ));
                
                $this->log('Assets Excel Scan caricati con nonce corretto');
            }
            
            wp_localize_script('disco747-admin-script', 'disco747Admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('disco747_admin_nonce'),
                'messages' => array(
                    'loading' => __('Caricamento...', 'disco747'),
                    'error' => __('Errore durante l\'operazione', 'disco747'),
                    'success' => __('Operazione completata', 'disco747'),
                    'confirm_delete' => __('Sei sicuro di voler eliminare questo preventivo?', 'disco747'),
                    'processing' => __('Elaborazione in corso...', 'disco747')
                )
            ));
            
            $this->log('Assets amministrazione caricati per: ' . $hook_suffix);
        } catch (\Exception $e) {
            $this->log('Errore caricamento assets: ' . $e->getMessage(), 'error');
        }
    }

    public function render_main_dashboard() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi per accedere a questa pagina.', 'disco747'));
        }
        
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        
        $this->log('Render main dashboard - Action: ' . $action);
        
        switch ($action) {
            case 'new_preventivo':
                $this->log('Rendering form nuovo preventivo');
                $this->render_form_preventivo();
                break;
                
            case 'edit_preventivo':
                $this->log('Rendering form modifica preventivo');
                $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                $this->render_form_preventivo($id);
                break;
                
            default:
                $this->log('Rendering dashboard principale');
                $this->render_main_dashboard_page();
                break;
        }
    }

    private function render_form_preventivo($id = 0) {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi.', 'disco747'));
        }
        
        $preventivo = null;
        if ($id > 0) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $id
            ), ARRAY_A);
        }
        
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/form-preventivo.php';
    }

    public function render_settings_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi.', 'disco747'));
        }
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
    }

    public function render_messages_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi.', 'disco747'));
        }
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/messages-page.php';
    }

    public function render_scan_excel_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi.', 'disco747'));
        }

        $is_googledrive_configured = false;
        $excel_files_list = array();

        try {
            $this->log('=== VERIFICA GOOGLE DRIVE v11.9.0 ===');
            
            $access_token = get_option('disco747_googledrive_access_token', '');
            $refresh_token = get_option('disco747_googledrive_refresh_token', '');
            $storage_type = get_option('disco747_storage_type', '');
            
            $this->log('Storage type: ' . $storage_type);
            $this->log('Access token: ' . (!empty($access_token) ? 'PRESENTE (' . strlen($access_token) . ' chars)' : 'MANCANTE'));
            $this->log('Refresh token: ' . (!empty($refresh_token) ? 'PRESENTE' : 'MANCANTE'));
            
            if (!empty($access_token) && !empty($refresh_token) && $storage_type === 'googledrive') {
                $is_googledrive_configured = true;
                $this->log('✅ CONFIGURATO via token (priorità 1)');
            } else {
                if ($this->storage_manager) {
                    $gd_handler = $this->storage_manager->get_active_handler();
                    
                    if ($gd_handler) {
                        $this->log('Handler trovato: ' . get_class($gd_handler));
                        
                        if (method_exists($gd_handler, 'is_connected')) {
                            $is_googledrive_configured = $gd_handler->is_connected();
                            $this->log('is_connected(): ' . ($is_googledrive_configured ? 'SI' : 'NO'));
                        } elseif (method_exists($gd_handler, 'is_authenticated')) {
                            $is_googledrive_configured = $gd_handler->is_authenticated();
                            $this->log('is_authenticated(): ' . ($is_googledrive_configured ? 'SI' : 'NO'));
                        }
                    } else {
                        $this->log('Handler NULL');
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->log('Errore verifica GoogleDrive: ' . $e->getMessage(), 'error');
        }

        $this->log('=== FINALE is_googledrive_configured: ' . ($is_googledrive_configured ? 'TRUE' : 'FALSE') . ' ===');

        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/excel-scan-page.php';
    }

    public function render_view_preventivi_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }
        
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/view-preventivi-page.php';
    }

    public function render_financial_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }
        
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/financial-analytics-page.php';
    }

    public function render_funnel_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }
        
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/funnel-automation-page.php';
    }

    public function render_report_preventivi_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die(__('Non hai i permessi.', 'disco747'));
        }
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/report-preventivi-page.php';
    }

    public function handle_force_report() {
        check_admin_referer('disco747_report_force');
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        if (class_exists('Disco747_Weekly_Report')) {
            $report = new \Disco747_Weekly_Report();
            $report->send_reports();
        }
        wp_safe_redirect(add_query_arg(array('page' => 'disco747-report-preventivi', 'done' => 'forced'), admin_url('admin.php')));
        exit;
    }

    public function handle_test_report_email() {
        check_admin_referer('disco747_report_test');
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        if (class_exists('Disco747_Weekly_Report')) {
            $report = new \Disco747_Weekly_Report();
            $user   = wp_get_current_user();
            $report->send_test_to_user($user);
        }
        wp_safe_redirect(add_query_arg(array('page' => 'disco747-report-preventivi', 'done' => 'test'), admin_url('admin.php')));
        exit;
    }

    public function handle_reschedule_report() {
        check_admin_referer('disco747_report_reschedule');
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        if (class_exists('Disco747_Weekly_Report')) {
            \Disco747_Weekly_Report::activate();
        }
        wp_safe_redirect(add_query_arg(array('page' => 'disco747-report-preventivi', 'done' => 'rescheduled'), admin_url('admin.php')));
        exit;
    }

    public function render_debug_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/debug-page.php';
    }
    
    public function render_diagnostic_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/diagnostic-excel-dates.php';
    }
    
    public function render_debug_structure_page() {
        if (!current_user_can($this->min_capability)) {
            wp_die('Non hai i permessi per accedere a questa pagina.');
        }
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/debug-excel-structure.php';
    }

    public function handle_batch_scan() {
        try {
            if (!check_ajax_referer('disco747_batch_scan', 'nonce', false)) {
                throw new \Exception('Nonce non valido');
            }

            if (!current_user_can($this->min_capability)) {
                throw new \Exception('Permessi insufficienti');
            }

            $googledrive_handler = $this->storage_manager->get_googledrive();
            if (!$googledrive_handler) {
                throw new \Exception('GoogleDrive non disponibile');
            }

            $gdrive_sync = new \Disco747_CRM\Storage\Disco747_GoogleDrive_Sync($googledrive_handler);

            if (!$gdrive_sync->is_available()) {
                throw new \Exception('GoogleDrive Sync non disponibile');
            }

            $result = $gdrive_sync->scan_excel_files_batch(true, 5);
            wp_send_json_success($result);

        } catch (\Exception $e) {
            $this->log('Errore handle_batch_scan: ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function handle_get_preventivo() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_get_preventivo')) {
                throw new \Exception('Nonce non valido');
            }

            if (!current_user_can($this->min_capability)) {
                throw new \Exception('Permessi insufficienti');
            }

            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            if ($preventivo_id <= 0) {
                throw new \Exception('ID preventivo non valido');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            
            $preventivo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $preventivo_id
            ), ARRAY_A);

            if (!$preventivo) {
                throw new \Exception('Preventivo non trovato');
            }

            wp_send_json_success($preventivo);

        } catch (\Exception $e) {
            $this->log('Errore get_preventivo: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }

    public function handle_delete_preventivo() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_delete_preventivo')) {
                throw new \Exception('Nonce non valido');
            }

            if (!current_user_can($this->min_capability)) {
                throw new \Exception('Permessi insufficienti');
            }

            $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
            if ($preventivo_id <= 0) {
                throw new \Exception('ID preventivo non valido');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';
            
            $result = $wpdb->delete($table_name, array('id' => $preventivo_id), array('%d'));

            if ($result === false) {
                throw new \Exception('Errore durante l\'eliminazione');
            }

            $this->log("Preventivo #{$preventivo_id} eliminato con successo");
            wp_send_json_success(array('message' => 'Preventivo eliminato con successo'));

        } catch (\Exception $e) {
            $this->log('Errore delete_preventivo: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }

    public function handle_get_funnel_sequence() {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'disco747_funnel_nonce')) {
                throw new \Exception('Nonce non valido');
            }

            if (!current_user_can($this->min_capability)) {
                throw new \Exception('Permessi insufficienti');
            }

            $sequence_id = intval($_POST['sequence_id'] ?? 0);
            if ($sequence_id <= 0) {
                throw new \Exception('ID sequenza non valido');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_funnel_sequences';
            
            $sequence = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $sequence_id
            ), ARRAY_A);

            if (!$sequence) {
                throw new \Exception('Sequenza non trovata');
            }

            wp_send_json_success($sequence);

        } catch (\Exception $e) {
            $this->log('Errore get_funnel_sequence: ' . $e->getMessage(), 'error');
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * ✅ NUOVO v12.1.0: Handler AJAX - Invia email di test
     * Gestisce le richieste AJAX per inviare email di prova con placeholder sostituiti
     */
    public function handle_send_test_email() {
        try {
            // Verifica nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'disco747_save_messages')) {
                wp_send_json_error('Nonce non valido');
                return;
            }
            
            // Verifica permessi
            if (!current_user_can($this->min_capability)) {
                wp_send_json_error('Permessi insufficienti');
                return;
            }
            
            // Validazione input
            $template_id = intval($_POST['template_id'] ?? 1);
            $subject = sanitize_text_field($_POST['subject'] ?? '');
            $body = wp_kses_post($_POST['body'] ?? '');
            $to_email = sanitize_email($_POST['to_email'] ?? '');
            
            if (empty($subject)) {
                wp_send_json_error('Oggetto email mancante');
                return;
            }
            
            if (empty($body)) {
                wp_send_json_error('Corpo email mancante');
                return;
            }
            
            if (empty($to_email) || !is_email($to_email)) {
                wp_send_json_error('Indirizzo email non valido');
                return;
            }
            
            // Log
            $this->log("Invio email di test a {$to_email} (Template #{$template_id})");
            
            // Headers HTML
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            );
            
            // Invia email
            $sent = wp_mail($to_email, $subject, $body, $headers);
            
            if ($sent) {
                $this->log("✅ Email di test inviata a {$to_email}");
                wp_send_json_success(array(
                    'message' => "Email di test inviata a {$to_email}"
                ));
            } else {
                $this->log("❌ Errore invio email a {$to_email}");
                wp_send_json_error('Errore durante l\'invio dell\'email. Controlla le impostazioni SMTP.');
            }
            
        } catch (\Exception $e) {
            $this->log('Errore handle_send_test_email: ' . $e->getMessage(), 'error');
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }

    public function handle_export_csv() {
        try {
            if (!wp_verify_nonce($_GET['nonce'], 'disco747_export_csv')) {
                wp_die('Nonce non valido');
            }

            if (!current_user_can($this->min_capability)) {
                wp_die('Permessi insufficienti');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';

            $where = array('1=1');
            $where_values = array();

            if (!empty($_GET['search'])) {
                $where[] = "(nome_cliente LIKE %s OR email LIKE %s)";
                $search = '%' . $wpdb->esc_like($_GET['search']) . '%';
                $where_values[] = $search;
                $where_values[] = $search;
            }

            if (!empty($_GET['stato'])) {
                $where[] = "stato = %s";
                $where_values[] = sanitize_key($_GET['stato']);
            }

            if (!empty($_GET['menu'])) {
                $where[] = "tipo_menu LIKE %s";
                $where_values[] = '%' . $wpdb->esc_like($_GET['menu']) . '%';
            }

            if (!empty($_GET['anno'])) {
                $where[] = "YEAR(data_evento) = %d";
                $where_values[] = intval($_GET['anno']);
            }

            if (!empty($_GET['mese'])) {
                $where[] = "MONTH(data_evento) = %d";
                $where_values[] = intval($_GET['mese']);
            }

            $where_clause = implode(' AND ', $where);

            if (!empty($where_values)) {
                $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC", $where_values);
            } else {
                $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC";
            }

            $preventivi = $wpdb->get_results($query, ARRAY_A);

            $filename = 'preventivi_' . date('Y-m-d_His') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            fputcsv($output, array(
                'ID', 'Preventivo ID', 'Data Evento', 'Nome Cliente', 'Email', 
                'Telefono', 'Tipo Evento', 'Menu', 'Invitati', 'Importo', 
                'Acconto', 'Stato', 'Creato il'
            ));

            foreach ($preventivi as $prev) {
                fputcsv($output, array(
                    $prev['id'],
                    $prev['preventivo_id'],
                    $prev['data_evento'],
                    $prev['nome_cliente'],
                    $prev['email'],
                    $prev['telefono'],
                    $prev['tipo_evento'],
                    $prev['tipo_menu'],
                    $prev['numero_invitati'],
                    $prev['importo_totale'],
                    $prev['acconto'],
                    $prev['stato'],
                    $prev['created_at']
                ));
            }

            fclose($output);
            exit;

        } catch (\Exception $e) {
            wp_die('Errore export: ' . $e->getMessage());
        }
    }

    /**
     * Handle Excel export with filters
     * Generates Excel-compatible file with UTF-8 BOM and tab separator
     */
    public function handle_export_excel() {
        try {
            if (!wp_verify_nonce($_GET['nonce'], 'disco747_export_excel')) {
                wp_die('Nonce non valido');
            }

            if (!current_user_can($this->min_capability)) {
                wp_die('Permessi insufficienti');
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'disco747_preventivi';

            $where = array('1=1');
            $where_values = array();

            // Apply filters (matching CSV export behavior)
            if (!empty($_GET['search'])) {
                $where[] = "(nome_cliente LIKE %s OR email LIKE %s)";
                $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['search'])) . '%';
                $where_values[] = $search;
                $where_values[] = $search;
            }

            if (!empty($_GET['stato'])) {
                $where[] = "stato = %s";
                $where_values[] = sanitize_key($_GET['stato']);
            }

            if (!empty($_GET['menu'])) {
                $where[] = "tipo_menu LIKE %s";
                $where_values[] = '%' . $wpdb->esc_like($_GET['menu']) . '%';
            }

            $anno = !empty($_GET['anno']) ? intval($_GET['anno']) : 0;
            $mese = !empty($_GET['mese']) ? intval($_GET['mese']) : 0;

            if ($anno > 0) {
                $where[] = "YEAR(data_evento) = %d";
                $where_values[] = $anno;
            }

            if ($mese > 0) {
                $where[] = "MONTH(data_evento) = %d";
                $where_values[] = $mese;
            }

            $where_clause = implode(' AND ', $where);

            if (!empty($where_values)) {
                $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY data_evento DESC", $where_values);
            } else {
                $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY data_evento DESC";
            }

            $preventivi = $wpdb->get_results($query, ARRAY_A);

            // Generate dynamic filename based on filters
            $filename = $this->generate_excel_filename($anno, $mese, sanitize_key($_GET['stato'] ?? ''));
            
            // Sanitize filename for header (remove any potentially dangerous characters)
            $safe_filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
            
            // Output CSV file optimized for Excel
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            
            $output = fopen('php://output', 'w');
            
            // UTF-8 BOM for Excel compatibility
            fwrite($output, "\xEF\xBB\xBF");
            
            // Excel separator directive - tells Excel to use semicolon as delimiter
            fwrite($output, "sep=;\n");
            
            // Headers - use semicolon separator for Italian Excel
            $headers = array(
                'Data Evento',
                'Cliente',
                'Telefono',
                'Email',
                'Tipo Evento',
                'Menu',
                'Numero Invitati',
                'Importo Totale',
                'Acconto',
                'Stato'
            );
            fputcsv($output, $headers, ';');

            // Data rows
            foreach ($preventivi as $prev) {
                $data_evento = !empty($prev['data_evento']) ? date('d/m/Y', strtotime($prev['data_evento'])) : '';
                
                $row = array(
                    $data_evento,
                    $prev['nome_cliente'] ?? '',
                    $prev['telefono'] ?? '',
                    $prev['email'] ?? '',
                    $prev['tipo_evento'] ?? '',
                    $prev['tipo_menu'] ?? '',
                    $prev['numero_invitati'] ?? '0',
                    number_format(floatval($prev['importo_totale'] ?? 0), 2, ',', '.'),
                    number_format(floatval($prev['acconto'] ?? 0), 2, ',', '.'),
                    strtoupper($prev['stato'] ?? '')
                );
                
                fputcsv($output, $row, ';');
            }

            fclose($output);
            exit;

        } catch (\Exception $e) {
            wp_die('Errore export Excel: ' . $e->getMessage());
        }
    }

    /**
     * Generate descriptive filename for Excel export based on filters
     */
    private function generate_excel_filename($anno, $mese, $stato) {
        $mesi_nomi = array(
            1 => 'gennaio', 2 => 'febbraio', 3 => 'marzo', 4 => 'aprile',
            5 => 'maggio', 6 => 'giugno', 7 => 'luglio', 8 => 'agosto',
            9 => 'settembre', 10 => 'ottobre', 11 => 'novembre', 12 => 'dicembre'
        );

        $parts = array('preventivi');

        // Add month name if filtered by month
        if ($mese > 0 && isset($mesi_nomi[$mese])) {
            $parts[] = $mesi_nomi[$mese];
        }

        // Add year if filtered
        if ($anno > 0) {
            $parts[] = $anno;
        }

        // Add stato if filtered
        if (!empty($stato)) {
            $parts[] = $stato;
        }

        // If no specific filters, add timestamp
        if (count($parts) === 1) {
            $parts[] = 'export';
            $parts[] = date('Y-m-d_His');
        }

        return implode('_', $parts) . '.csv';
    }

    public function show_admin_notices() {
        foreach ($this->admin_notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
    }

    private function add_admin_notice($message, $type = 'info') {
        $this->admin_notices[] = array(
            'message' => $message,
            'type' => $type
        );
    }

    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=disco747-settings') . '">' . __('Impostazioni', 'disco747') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Render dashboard principale con dati
     */
    private function render_main_dashboard_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'disco747_preventivi';
        
        // Statistiche
        $stats = array(
            'total' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table}")),
            'attivi' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE stato = 'attivo'")),
            'confermati' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE acconto > 0 OR stato = 'confermato'")),
            'annullati' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE stato = 'annullato'")),
            'this_month' => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE MONTH(created_at) = %d AND YEAR(created_at) = %d",
                date('m'),
                date('Y')
            )))
        );
        
        // Eventi imminenti (prossimi 14 giorni)
        $eventi_imminenti = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE data_evento BETWEEN %s AND %s 
             ORDER BY data_evento ASC 
             LIMIT 10",
            date('Y-m-d'),
            date('Y-m-d', strtotime('+14 days'))
        ), ARRAY_A);
        
        // Preventivi recenti (ultimi 10)
        $preventivi_recenti = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 10",
            ARRAY_A
        );
        
        // Dati per grafici
        $chart_data = $this->get_chart_data();
        
        // System status
        $system_status = array(
            'plugin_version' => DISCO747_CRM_VERSION,
            'storage_type' => get_option('disco747_storage_type', 'googledrive'),
            'storage_connected' => false
        );
        
        // Passa variabili alla vista
        require_once DISCO747_CRM_PLUGIN_DIR . 'includes/admin/views/main-page.php';
    }
    
    /**
     * Ottieni KPI finanziari
     */
    private function get_financial_kpi() {
        global $wpdb;
        $table = $wpdb->prefix . 'disco747_preventivi';
        
        // Entrate previste questo mese
        $entrate_mese = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(importo_totale) FROM {$table} 
             WHERE MONTH(data_evento) = %d AND YEAR(data_evento) = %d 
             AND stato != 'annullato'",
            date('m'),
            date('Y')
        )));
        
        // Acconti incassati questo mese
        $acconti_mese = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(acconto) FROM {$table} 
             WHERE MONTH(data_evento) = %d AND YEAR(data_evento) = %d 
             AND acconto > 0",
            date('m'),
            date('Y')
        )));
        
        // Saldo da incassare (confermati con eventi futuri)
        $saldo_da_incassare = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(importo_totale - acconto) FROM {$table} 
             WHERE data_evento >= %s 
             AND acconto > 0 
             AND stato != 'annullato'",
            date('Y-m-d')
        )));
        
        // Valore preventivi attivi (non confermati)
        $valore_attivi = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(importo_totale) FROM {$table} 
             WHERE data_evento >= %s 
             AND (acconto = 0 OR acconto IS NULL)
             AND stato = 'attivo'",
            date('Y-m-d')
        )));
        
        return array(
            'entrate_mese' => $entrate_mese,
            'acconti_mese' => $acconti_mese,
            'saldo_da_incassare' => $saldo_da_incassare,
            'valore_attivi' => $valore_attivi
        );
    }
    
    /**
     * Ottieni dati per grafici
     */
    private function get_chart_data() {
        global $wpdb;
        $table = $wpdb->prefix . 'disco747_preventivi';
        
        // Preventivi per mese (ultimi 6 mesi)
        $preventivi_per_mese = array();
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $count = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE DATE_FORMAT(data_evento, '%%Y-%%m') = %s",
                $month
            )));
            $preventivi_per_mese[] = array(
                'month' => date('M Y', strtotime($month . '-01')),
                'count' => $count
            );
        }
        
        // Conferme vs non confermati (ultimi 30 giorni)
        $confermati_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND (acconto > 0 OR stato = 'confermato')",
            date('Y-m-d', strtotime('-30 days'))
        )));
        
        $non_confermati_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND acconto = 0 AND stato != 'confermato' AND stato != 'annullato'",
            date('Y-m-d', strtotime('-30 days'))
        )));
        
        return array(
            'preventivi_per_mese' => $preventivi_per_mese,
            'confermati' => $confermati_count,
            'non_confermati' => $non_confermati_count
        );
    }
    
    private function log($message, $level = 'INFO') {
        if (!$this->debug_mode) return;
        $prefix = '[747Disco-Admin]';
        $timestamp = current_time('mysql');
        $log_message = "[{$timestamp}] {$prefix} {$message}";
        error_log($log_message);
    }
}