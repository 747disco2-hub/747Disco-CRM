<?php
/**
 * Excel Generator Class - 747 Disco CRM
 * STEP 1: Mapping completo e calcoli economici corretti
 * 
 * @package 747Disco-CRM
 * @version 12.0.0-STEP1
 * @author 747 Disco Team
 */

namespace Disco747_CRM\Generators;

defined('ABSPATH') || exit;

// Autoload PhpSpreadsheet se necessario
if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    $vendor_autoload = plugin_dir_path(dirname(dirname(__FILE__))) . 'vendor/autoload.php';
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
    }
}

class Disco747_Excel {

    private $templates_path;
    private $output_path;
    private $debug_mode = true;
    private $autoloader_loaded = false;
    private $template_dirs = array();
    
    private $template_files = array(
        'Menu 7' => 'Menu 7.xlsx',
        'Menu 74' => 'Menu 7 - 4.xlsx',
        'Menu 7-4' => 'Menu 7 - 4.xlsx',
        'Menu 747' => 'Menu 7 - 4 - 7.xlsx',
        'Menu 7-4-7' => 'Menu 7 - 4 - 7.xlsx'
    );

    // SCONTI ALL-INCLUSIVE PER MENU (in €)
    private $sconti_menu = array(
        'Menu 7' => 400,
        'Menu 7-4' => 500,
        'Menu 7-4-7' => 600
    );

    public function __construct() {
        $this->setup_paths();
        $this->log('[747Disco-Excel] Generator v12.0.0-STEP1 inizializzato');
    }

    private function setup_paths() {
        $plugin_path = plugin_dir_path(dirname(dirname(__FILE__)));
        $this->template_dirs = array(
            $plugin_path . 'templates/',
            $plugin_path . 'assets/templates/',
            $plugin_path . 'includes/templates/',
            WP_CONTENT_DIR . '/uploads/disco747/templates/'
        );
        
        $upload_dir = wp_upload_dir();
        $this->output_path = $upload_dir['basedir'] . '/preventivi/';
        
        if (!file_exists($this->output_path)) {
            wp_mkdir_p($this->output_path);
        }
    }

    /**
     * METODO PRINCIPALE: Genera Excel
     */
    public function generate_excel($data) {
        try {
            $filename = $this->generate_filename($data);
            $file_path = $this->output_path . $filename;
            
            $this->log('[747Disco-Excel] 📄 Generando Excel: ' . $filename);
            
            $template_path = $this->find_excel_template($data['tipo_menu'] ?? 'Menu 7');
            
            $success = false;
            if ($template_path && file_exists($template_path)) {
                $this->log('[747Disco-Excel] 📋 Usando template: ' . basename($template_path));
                $success = $this->compile_excel_from_template($template_path, $file_path, $data);
            } else {
                $this->log('[747Disco-Excel] ⚠️ Template non trovato - uso Excel semplice');
                $success = $this->create_simple_excel($file_path, $data);
            }
            
            if ($success && file_exists($file_path) && filesize($file_path) > 0) {
                $file_size = $this->format_file_size(filesize($file_path));
                $this->log('[747Disco-Excel] ✅ Excel generato: ' . $filename . ' (' . $file_size . ')');
                return $file_path;
            }
            
            $this->log('[747Disco-Excel] ❌ Errore: file non generato', 'ERROR');
            return false;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-Excel] ❌ Errore generazione: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * COMPILAZIONE TEMPLATE CON MAPPING CORRETTO
     */
    private function compile_excel_from_template($template_path, $output_path, $data) {
        try {
            if (!$this->check_phpspreadsheet()) {
                throw new \Exception('PhpSpreadsheet non disponibile');
            }
            
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($template_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $this->log('[747Disco-Excel] 📝 Compilazione mapping celle...');
            
            // STEP 1: Scrivi tipo_menu in B1
            $tipo_menu_display = $data['tipo_menu'] ?? 'Menu 7';
            $worksheet->setCellValue('B1', $tipo_menu_display);
            $this->log('[747Disco-Excel] B1 → tipo_menu: ' . $tipo_menu_display);
            
            // STEP 2: Dati evento (C6-C9)
            $data_evento_formatted = $this->format_data_evento($data['data_evento'] ?? '');
            $worksheet->setCellValue('C6', $data_evento_formatted);
            $this->log('[747Disco-Excel] C6 → data_evento: ' . $data_evento_formatted);
            
            $worksheet->setCellValue('C7', $data['tipo_evento'] ?? '');
            $this->log('[747Disco-Excel] C7 → tipo_evento: ' . ($data['tipo_evento'] ?? ''));
            
            $orario = $this->format_orario($data);
            $worksheet->setCellValue('C8', $orario);
            $this->log('[747Disco-Excel] C8 → orario: ' . $orario);
            
            $worksheet->setCellValue('C9', $data['numero_invitati'] ?? '');
            $this->log('[747Disco-Excel] C9 → numero_invitati: ' . ($data['numero_invitati'] ?? ''));
            
            // STEP 3: Dati referente (C11-C15)
            $worksheet->setCellValue('C11', $data['nome_referente'] ?? '');
            $this->log('[747Disco-Excel] C11 → nome_referente: ' . ($data['nome_referente'] ?? ''));
            
            $worksheet->setCellValue('C12', $data['cognome_referente'] ?? '');
            $this->log('[747Disco-Excel] C12 → cognome_referente: ' . ($data['cognome_referente'] ?? ''));
            
            $worksheet->setCellValue('C14', $data['cellulare'] ?? '');
            $this->log('[747Disco-Excel] C14 → cellulare: ' . ($data['cellulare'] ?? ''));
            
            // IMPORTANTE: il form usa 'mail', non 'email'
            $worksheet->setCellValue('C15', $data['mail'] ?? '');
            $this->log('[747Disco-Excel] C15 → email: ' . ($data['mail'] ?? ''));
            
            // STEP 4: Omaggi (C17-C19)
            $worksheet->setCellValue('C17', $data['omaggio1'] ?? '');
            $worksheet->setCellValue('C18', $data['omaggio2'] ?? '');
            $worksheet->setCellValue('C19', $data['omaggio3'] ?? '');
            $this->log('[747Disco-Excel] C17-C19 → omaggi compilati');
            
            // STEP 5: CALCOLI ECONOMICI (le celle più critiche!)
            $this->compile_economic_data($worksheet, $data);
            
            // STEP 6: Extra (C33-C35 e F33-F35)
            $this->compile_extra_data($worksheet, $data);
            
            // STEP 7: Salva
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($output_path);
            
            $this->log('[747Disco-Excel] ✅ Template compilato e salvato');
            return true;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-Excel] ❌ Errore compilazione template: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * CALCOLI ECONOMICI CORRETTI (vincolo principale!)
     */
    private function compile_economic_data($worksheet, $data) {
        // Recupera valori base
        $importo_base = floatval($data['importo_preventivo'] ?? $data['importo'] ?? 0);
        $extra1_importo = floatval($data['extra1_importo'] ?? 0);
        $extra2_importo = floatval($data['extra2_importo'] ?? 0);
        $extra3_importo = floatval($data['extra3_importo'] ?? 0);
        $acconto = floatval($data['acconto'] ?? 0);
        
        // Determina sconto all-inclusive
        $tipo_menu = $data['tipo_menu'] ?? 'Menu 7';
        $sconto_all_inclusive = $this->sconti_menu[$tipo_menu] ?? $this->sconti_menu['Menu 7'];
        
        // CALCOLI SECONDO LE REGOLE SPECIFICATE:
        // totale = importo + somma(extra1_importo, extra2_importo, extra3_importo)
        $totale = $importo_base + $extra1_importo + $extra2_importo + $extra3_importo;
        
        // totale_parziale = totale − sconto_all_inclusive
        $totale_parziale = $totale - $sconto_all_inclusive;
        
        // saldo = totale − acconto
        $saldo = $totale - $acconto;
        
        // SCRIVI NELLE CELLE CORRETTE:
        // F27 → importo (importo totale base dal form)
        $worksheet->setCellValue('F27', $importo_base);
        $this->log('[747Disco-Excel] F27 → importo base: ' . number_format($importo_base, 2, ',', '.'));
        
        // F28 → acconto
        $worksheet->setCellValue('F28', $acconto);
        $this->log('[747Disco-Excel] F28 → acconto: ' . number_format($acconto, 2, ',', '.'));
        
        // F30 → saldo = totale − acconto
        $worksheet->setCellValue('F30', $saldo);
        $this->log('[747Disco-Excel] F30 → saldo: ' . number_format($saldo, 2, ',', '.'));
        
        // LOG COMPLETO DEI CALCOLI
        $this->log('[747Disco-Excel] 💰 CALCOLI ECONOMICI:');
        $this->log('[747Disco-Excel]    • Importo base: €' . number_format($importo_base, 2, ',', '.'));
        $this->log('[747Disco-Excel]    • Extra1: €' . number_format($extra1_importo, 2, ',', '.'));
        $this->log('[747Disco-Excel]    • Extra2: €' . number_format($extra2_importo, 2, ',', '.'));
        $this->log('[747Disco-Excel]    • Extra3: €' . number_format($extra3_importo, 2, ',', '.'));
        $this->log('[747Disco-Excel]    • Sconto all-inclusive (' . $tipo_menu . '): €' . number_format($sconto_all_inclusive, 2, ',', '.'));
        $this->log('[747Disco-Excel]    • TOTALE: €' . number_format($totale, 2, ',', '.'));
        $this->log('[747Disco-Excel]    • Totale parziale: €' . number_format($totale_parziale, 2, ',', '.'));
        $this->log('[747Disco-Excel]    • Acconto: €' . number_format($acconto, 2, ',', '.'));
        $this->log('[747Disco-Excel]    • SALDO: €' . number_format($saldo, 2, ',', '.'));
    }

    /**
     * COMPILA EXTRA (descrizioni e importi)
     */
    private function compile_extra_data($worksheet, $data) {
        // Extra 1
        if (!empty($data['extra1'])) {
            $worksheet->setCellValue('C33', $data['extra1']);
            $worksheet->setCellValue('F33', floatval($data['extra1_importo'] ?? 0));
            $this->log('[747Disco-Excel] C33/F33 → ' . $data['extra1'] . ': €' . ($data['extra1_importo'] ?? 0));
        }
        
        // Extra 2
        if (!empty($data['extra2'])) {
            $worksheet->setCellValue('C34', $data['extra2']);
            $worksheet->setCellValue('F34', floatval($data['extra2_importo'] ?? 0));
            $this->log('[747Disco-Excel] C34/F34 → ' . $data['extra2'] . ': €' . ($data['extra2_importo'] ?? 0));
        }
        
        // Extra 3
        if (!empty($data['extra3'])) {
            $worksheet->setCellValue('C35', $data['extra3']);
            $worksheet->setCellValue('F35', floatval($data['extra3_importo'] ?? 0));
            $this->log('[747Disco-Excel] C35/F35 → ' . $data['extra3'] . ': €' . ($data['extra3_importo'] ?? 0));
        }
    }

    /**
     * FORMATTA DATA EVENTO (gg/mm/aaaa)
     */
    private function format_data_evento($data_evento) {
        if (empty($data_evento)) {
            return date('d/m/Y');
        }
        
        // Se è già in formato Y-m-d (2024-12-25)
        $date_obj = \DateTime::createFromFormat('Y-m-d', $data_evento);
        if ($date_obj) {
            return $date_obj->format('d/m/Y');
        }
        
        // Altrimenti ritorna così com'è
        return $data_evento;
    }

    /**
     * FORMATTA ORARIO (inizio - fine oppure solo inizio)
     */
    private function format_orario($data) {
        $orario_inizio = $data['orario_inizio'] ?? $data['orario_evento'] ?? '19:00';
        $orario_fine = $data['orario_fine'] ?? '';
        
        if (!empty($orario_fine)) {
            return $orario_inizio . ' - ' . $orario_fine;
        }
        
        return $orario_inizio;
    }

    /**
     * GENERA NOME FILE (conforme alle regole)
     */
    private function generate_filename($data) {
        $date_parts = explode('-', $data['data_evento'] ?? date('Y-m-d'));
        $day = str_pad($date_parts[2] ?? date('d'), 2, '0', STR_PAD_LEFT);
        $month = str_pad($date_parts[1] ?? date('m'), 2, '0', STR_PAD_LEFT);
        
        $tipo_evento = $this->sanitize_filename($data['tipo_evento'] ?? 'Evento');
        
        $prefix = '';
        $acconto = floatval($data['acconto'] ?? 0);
        
        if (isset($data['stato']) && $data['stato'] === 'annullato') {
            $prefix = 'NO ';
        } elseif ($acconto > 0) {
            $prefix = 'CONF ';
        }
        
        // ✅ FIX: Estrazione robusta del numero menu (case-insensitive, rimuove tutti i "Menu" duplicati)
        $menu_type = $data['tipo_menu'] ?? 'Menu 7';
        $menu_number = preg_replace('/\b(menu\s*)+/i', '', $menu_type);
        $menu_number = trim($menu_number);
        
        $filename = $prefix . $day . '_' . $month . ' ' . $tipo_evento . ' (Menu ' . $menu_number . ').xlsx';
        
        $this->log('[747Disco-Excel] 🏷️ Nome file Excel generato: ' . $filename);
        
        return $filename;
    }

    /**
     * SANIFICA NOME FILE
     */
    private function sanitize_filename($string) {
        $string = preg_replace('/[^a-zA-Z0-9\s\-àáâãäåçèéêëìíîïñòóôõöøùúûüýÿ]/u', '', $string);
        return trim($string);
    }

    /**
     * TROVA TEMPLATE EXCEL
     */
    private function find_excel_template($menu_type) {
        $template_file = $this->template_files[$menu_type] ?? $this->template_files['Menu 7'];
        
        foreach ($this->template_dirs as $dir) {
            $template_path = $dir . $template_file;
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        
        return false;
    }

    /**
     * CREA EXCEL SEMPLICE (fallback se template manca)
     */
    private function create_simple_excel($file_path, $data) {
        try {
            if (!$this->check_phpspreadsheet()) {
                return false;
            }
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Header
            $worksheet->setCellValue('A1', '747 DISCO - PREVENTIVO');
            $worksheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            
            // Usa stesso mapping del template
            $worksheet->setCellValue('B1', $data['tipo_menu'] ?? 'Menu 7');
            $worksheet->setCellValue('C6', $this->format_data_evento($data['data_evento'] ?? ''));
            $worksheet->setCellValue('C7', $data['tipo_evento'] ?? '');
            $worksheet->setCellValue('C8', $this->format_orario($data));
            $worksheet->setCellValue('C9', $data['numero_invitati'] ?? '');
            $worksheet->setCellValue('C11', $data['nome_referente'] ?? '');
            $worksheet->setCellValue('C12', $data['cognome_referente'] ?? '');
            $worksheet->setCellValue('C14', $data['cellulare'] ?? '');
            $worksheet->setCellValue('C15', $data['mail'] ?? '');
            $worksheet->setCellValue('C17', $data['omaggio1'] ?? '');
            $worksheet->setCellValue('C18', $data['omaggio2'] ?? '');
            $worksheet->setCellValue('C19', $data['omaggio3'] ?? '');
            
            // Calcoli economici
            $this->compile_economic_data($worksheet, $data);
            $this->compile_extra_data($worksheet, $data);
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($file_path);
            
            return true;
            
        } catch (\Exception $e) {
            $this->log('[747Disco-Excel] ❌ Errore Excel semplice: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * VERIFICA PHPSPREADSHEET
     */
    private function check_phpspreadsheet() {
        if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return true;
        }
        
        $autoloader_paths = array(
            __DIR__ . '/../../vendor/autoload.php',
            ABSPATH . 'vendor/autoload.php',
            ABSPATH . 'wp-content/plugins/disco747-crm/vendor/autoload.php',
            dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php'
        );
        
        foreach ($autoloader_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                    $this->log('[747Disco-Excel] ✅ PhpSpreadsheet caricato da: ' . $path);
                    return true;
                }
            }
        }
        
        $this->log('[747Disco-Excel] ❌ PhpSpreadsheet non disponibile', 'ERROR');
        return false;
    }

    /**
     * FORMATTA DIMENSIONE FILE
     */
    private function format_file_size($bytes) {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * LOGGING
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode && function_exists('error_log')) {
            error_log($message);
        }
    }
}