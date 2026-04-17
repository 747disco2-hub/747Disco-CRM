<?php
/**
 * PDF Generator Class - 747 Disco CRM
 * VERSIONE CORRETTA v12.4.0
 * 
 * FIX APPLICATI:
 * 1. ✅ Mappatura completa campi con TUTTI gli alias
 * 2. ✅ Cellulare/Telefono unificati
 * 3. ✅ Email/Mail unificati
 * 4. ✅ Totale parziale con sconto 30%
 * 5. ✅ Acconto sempre formattato
 * 6. ✅ FIX: Priorità importo_totale su importo_preventivo
 * 7. ✅ FIX: Nome file univoco con ID preventivo
 * 8. ✅ FIX: Sovrascrittura file esistenti
 * 
 * @package    Disco747_CRM
 * @subpackage Generators
 * @version    12.4.0
 */

namespace Disco747_CRM\Generators;

defined('ABSPATH') || exit;

if (!defined('PDF_PAGE_ORIENTATION')) {
    define('PDF_PAGE_ORIENTATION', 'P');
    define('PDF_UNIT', 'mm');
    define('PDF_PAGE_FORMAT', 'A4');
}

if (!class_exists('\Dompdf\Dompdf')) {
    $vendor_autoload = plugin_dir_path(dirname(dirname(__FILE__))) . 'vendor/autoload.php';
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
    }
}

class Disco747_PDF {

    private $templates_path;
    private $output_path;
    private $debug_mode = true;

    public function __construct() {
        $this->templates_path = plugin_dir_path(dirname(dirname(__FILE__))) .  'templates/';
        $this->output_path = wp_upload_dir()['basedir'] . '/preventivi/';
        
        if (!file_exists($this->output_path)) {
            wp_mkdir_p($this->output_path);
        }
        
        $this->log('PDF Generator v12.4.0 inizializzato');
    }

    /**
     * ========================================================================
     * METODO PRINCIPALE: Genera PDF
     * ========================================================================
     */
    public function generate_pdf($data) {
        try {
            $this->log('🚀 Avvio generazione PDF per ' . ($data['tipo_evento'] ?? 'evento'));
            
            $pdf_filename = $this->generate_filename($data);
            $pdf_path = $this->output_path . $pdf_filename;
            
            // ✅ FIX v12.4.0: Elimina file esistente con stesso nome prima di generare
            if (file_exists($pdf_path)) {
                $this->log('⚠️ File esistente trovato, lo elimino: ' . $pdf_filename);
                unlink($pdf_path);
            }
            
            $template_file = $this->get_template_file($data['tipo_menu'] ?? 'Menu 7');
            $template_path = $this->templates_path .  $template_file;
            
            if (!file_exists($template_path)) {
                $this->log('⚠️ Template HTML non trovato: ' . $template_path);
                $this->log('⚠️ Uso generazione PDF semplice');
                return $this->generate_simple_pdf($pdf_path, $data);
            }
            
            // ✅ CORREZIONE: Prepara TUTTI i dati con mappatura completa
            $prepared_data = $this->prepare_pdf_data($data);
            
            // Compila template
            $html_content = $this->compile_template($template_path, $prepared_data);
            
            // Genera PDF con Dompdf
            $result = $this->create_pdf_with_dompdf($html_content, $pdf_path);
            
            if ($result && file_exists($pdf_path)) {
                $this->log('✅ PDF generato: ' . $pdf_filename);
                return $pdf_path;
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->log('❌ Errore PDF: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * ========================================================================
     * METODO CORRETTO: Prepara TUTTI i dati per il template PDF
     * ========================================================================
     */
    private function prepare_pdf_data($data) {
        // ===== DEBUG: Log dati in ingresso =====
        $this->log('========== DEBUG PREPARE_PDF_DATA ==========');
        $this->log('Dati ricevuti:');
        $this->log('  - importo_preventivo: ' . ($data['importo_preventivo'] ?? 'N/D'));
        $this->log('  - importo_totale: ' . ($data['importo_totale'] ?? 'N/D'));
        $this->log('  - tipo_menu: ' . ($data['tipo_menu'] ?? 'N/D'));
        $this->log('  - extra1: "' . ($data['extra1'] ?? '') . '"');
        $this->log('  - extra1_importo: ' . ($data['extra1_importo'] ?? 'N/D'));
        $this->log('  - extra2: "' . ($data['extra2'] ?? '') . '"');
        $this->log('  - extra2_importo: ' .  ($data['extra2_importo'] ?? 'N/D'));
        $this->log('  - extra3: "' . ($data['extra3'] ?? '') .  '"');
        $this->log('  - extra3_importo: ' . ($data['extra3_importo'] ?? 'N/D'));
        $this->log('  - acconto: ' . ($data['acconto'] ?? 'N/D'));
        $this->log('==========================================');
        
        // ✅ FIX v12.4.0: Priorità a importo_totale (valore corretto già scontato)
        // PRIMA era: $data['importo_preventivo'] ?? $data['importo_totale']
        // ORA è: $data['importo_totale'] ?? $data['importo_preventivo']
        $importo_preventivo = floatval($data['importo_totale'] ?? $data['importo_preventivo'] ?? 0);
        $acconto = floatval($data['acconto'] ?? 0);
        
        // SCONTO MENU in base al tipo di menu
        $sconti_menu = array(
            'Menu 7' => 400,
            'Menu 7-4' => 500,
            'Menu 74' => 500,
            'Menu 7-4-7' => 600,
            'Menu 747' => 600
        );
        $tipo_menu = $data['tipo_menu'] ?? 'Menu 7';
        $sconto_menu = $sconti_menu[$tipo_menu] ??  400;
        
        // Calcola extra se presenti (solo se hanno importo > 0)
        $extra_totale = 0;
        $extra1_importo = floatval($data['extra1_importo'] ?? 0);
        $extra2_importo = floatval($data['extra2_importo'] ?? 0);
        $extra3_importo = floatval($data['extra3_importo'] ?? 0);
        
        if (! empty($data['extra1']) && $extra1_importo > 0) {
            $extra_totale += $extra1_importo;
        }
        if (!empty($data['extra2']) && $extra2_importo > 0) {
            $extra_totale += $extra2_importo;
        }
        if (! empty($data['extra3']) && $extra3_importo > 0) {
            $extra_totale += $extra3_importo;
        }
        
        // CALCOLI FINALI (formula corretta)
        $totale_parziale = $importo_preventivo + $sconto_menu + $extra_totale; // Totale teorico con sconto
        $totale = $importo_preventivo + $extra_totale; // Totale effettivo da pagare
        $saldo = $totale - $acconto;
        
        $this->log('CALCOLI:');
        $this->log('  - Importo Base: ' . $importo_preventivo);
        $this->log('  - Sconto Menu (' . $tipo_menu . '): ' . $sconto_menu);
        $this->log('  - Extra Totale: ' . $extra_totale);
        $this->log('  - Totale Parziale: ' . $totale_parziale);
        $this->log('  - Totale: ' . $totale);
        $this->log('  - Saldo: ' .  $saldo);
        
        // Prepara display omaggi
        $omaggi = array();
        if (! empty($data['omaggio1'])) $omaggi[] = $data['omaggio1'];
        if (! empty($data['omaggio2'])) $omaggi[] = $data['omaggio2'];
        if (!empty($data['omaggio3'])) $omaggi[] = $data['omaggio3'];
        $omaggi_display = ! empty($omaggi) ? implode(', ', $omaggi) : 'Nessuno';
        
        // Prepara display extra (solo se hanno descrizione E importo > 0)
        $extra = array();
        if (!empty($data['extra1']) && $extra1_importo > 0) {
            $extra[] = $data['extra1'] . ' - €' . number_format($extra1_importo, 2, ',', '.');
        }
        if (!empty($data['extra2']) && $extra2_importo > 0) {
            $extra[] = $data['extra2'] . ' - €' .  number_format($extra2_importo, 2, ',', '.');
        }
        if (! empty($data['extra3']) && $extra3_importo > 0) {
            $extra[] = $data['extra3'] .  ' - €' . number_format($extra3_importo, 2, ',', '.');
        }
        $extra_display = !empty($extra) ? implode(', ', $extra) : 'Nessuno';
        
        // ✅ MAPPATURA COMPLETA con TUTTI i possibili alias
        $prepared = array(
            // === DATI CLIENTE ===
            'nome_referente' => $data['nome_referente'] ?? '',
            'cognome_referente' => $data['cognome_referente'] ?? '',
            'nome_cliente' => $data['nome_cliente'] ?? trim(($data['nome_referente'] ??  '') . ' ' . ($data['cognome_referente'] ?? '')),
            
            // === CONTATTI - TUTTI GLI ALIAS ===
            // Telefono/Cellulare - tutti gli alias possibili
            'telefono' => $data['telefono'] ??  $data['cellulare'] ?? '',
            'cellulare' => $data['cellulare'] ??  $data['telefono'] ?? '',
            
            // Email/Mail - tutti gli alias possibili
            'email' => $data['email'] ?? $data['mail'] ?? '',
            'mail' => $data['mail'] ?? $data['email'] ?? '',
            
            // === DATI EVENTO ===
            'data_evento' => $this->format_date($data['data_evento'] ?? ''),
            'data_evento_raw' => $data['data_evento'] ?? '',
            'tipo_evento' => $data['tipo_evento'] ?? '',
            'orario' => $this->format_orario($data),
            'orario_inizio' => $data['orario_inizio'] ??  '20:30',
            'orario_fine' => $data['orario_fine'] ?? '01:30',
            'numero_invitati' => $data['numero_invitati'] ?? '',
            'tipo_menu' => $data['tipo_menu'] ?? 'Menu 7',
            
            // === IMPORTI FORMATTATI - CON SCONTO MENU ===
            'importo_preventivo' => $importo_preventivo,
            'importo_preventivo_formatted' => '€' . number_format($importo_preventivo, 2, ',', '.'),
            
            // Sconto Menu
            'sconto_menu' => $sconto_menu,
            'sconto_allinclusive' => '€' . number_format($sconto_menu, 2, ',', '.'),
            'sconto_allinclusive_formatted' => '€' . number_format($sconto_menu, 2, ',', '.'),
            
            // Totale Parziale (importo base + sconto menu + extra)
            'totale_parziale' => '€' . number_format($totale_parziale, 2, ',', '.'),
            'totale_parziale_raw' => $totale_parziale,
            
            // Totale Effettivo (importo base + extra)
            'totale' => '€' . number_format($totale, 2, ',', '.'),
            'totale_raw' => $totale,
            'totale_lordo' => '€' . number_format($totale, 2, ',', '. '),
            'totale_finale' => '€' . number_format($totale, 2, ',', '.'),
            
            // Acconto e Saldo
            'acconto' => '€' . number_format($acconto, 2, ',', '.'),
            'acconto_raw' => $acconto,
            'acconto_formatted' => '€' . number_format($acconto, 2, ',', '.'),
            
            'saldo' => '€' .  number_format($saldo, 2, ',', '.'),
            'saldo_raw' => $saldo,
            
            // === OMAGGI E EXTRA ===
            'omaggi_display' => $omaggi_display,
            'extra_display' => $extra_display,
            
            'omaggio1' => $data['omaggio1'] ?? '',
            'omaggio2' => $data['omaggio2'] ?? '',
            'omaggio3' => $data['omaggio3'] ?? '',
            
            'extra1' => $data['extra1'] ?? '',
            'extra1_importo' => $extra1_importo,
            'extra1_formatted' => '€' . number_format($extra1_importo, 2, ',', '.'),
            
            'extra2' => $data['extra2'] ?? '',
            'extra2_importo' => $extra2_importo,
            'extra2_formatted' => '€' . number_format($extra2_importo, 2, ',', '.'),
            
            'extra3' => $data['extra3'] ?? '',
            'extra3_importo' => $extra3_importo,
            'extra3_formatted' => '€' . number_format($extra3_importo, 2, ',', '.'),
            
            // === NOTE ===
            'note_aggiuntive' => $data['note_aggiuntive'] ??  '',
            'note_interne' => $data['note_interne'] ?? '',
            
            // === METADATA ===
            'preventivo_id' => $data['preventivo_id'] ?? '',
            'stato' => $data['stato'] ??  'attivo'
        );
        
        $this->log('✅ Dati preparati per template PDF');
        $this->log('  → Telefono: ' . $prepared['telefono']);
        $this->log('  → Email: ' . $prepared['email']);
        $this->log('  → Totale parziale: ' . $prepared['totale_parziale']);
        $this->log('  → Acconto: ' . $prepared['acconto']);
        
        return $prepared;
    }

    /**
     * ========================================================================
     * HELPER: Formatta data
     * ========================================================================
     */
    private function format_date($date_string) {
        if (empty($date_string)) {
            return '';
        }
        
        try {
            $date = new \DateTime($date_string);
            return $date->format('d/m/Y');
        } catch (\Exception $e) {
            return $date_string;
        }
    }

    /**
     * ========================================================================
     * HELPER: Formatta orario
     * ========================================================================
     */
    private function format_orario($data) {
        $orario_inizio = $data['orario_inizio'] ?? '20:30';
        $orario_fine = $data['orario_fine'] ?? '01:30';
        
        return $orario_inizio . ' - ' . $orario_fine;
    }

    /**
     * ========================================================================
     * HELPER: Genera nome file PDF - ✅ FIX v12.4.0: Aggiunto ID univoco
     * ========================================================================
     */
    private function generate_filename($data) {
        $data_parts = explode('-', $data['data_evento'] ?? date('Y-m-d'));
        
        if (count($data_parts) !== 3) {
            $this->log('⚠️ Formato data evento non valido: ' . $data['data_evento'], 'ERROR');
            throw new \Exception('Formato data evento non valido: ' . $data['data_evento']);
        }
        
        $day = str_pad($data_parts[2], 2, '0', STR_PAD_LEFT);
        $month = str_pad($data_parts[1], 2, '0', STR_PAD_LEFT);
        
        $this->log('📅 PDF - Data evento estratta: ' . $day . '_' . $month .  ' da ' . $data['data_evento']);
        
        $tipo_evento = $this->sanitize_filename($data['tipo_evento'] ??  'Evento');
        $tipo_evento = substr($tipo_evento, 0, 50);
        
        $prefix = '';
        if (isset($data['stato']) && $data['stato'] === 'annullato') {
            $prefix = 'NO ';
        } elseif (isset($data['acconto']) && floatval($data['acconto']) > 0) {
            $prefix = 'CONF ';
        }
        
        // ✅ FIX: Estrazione robusta del numero menu (case-insensitive, rimuove tutti i "Menu" duplicati)
        $menu_type = $data['tipo_menu'] ?? 'Menu 7';
        $menu_number = preg_replace('/\b(menu\s*)+/i', '', $menu_type);
        $menu_number = trim($menu_number);
        
        // ✅ FIX v12.4.0: Aggiunge ID preventivo per rendere il nome file UNIVOCO
        $preventivo_id = $data['preventivo_id'] ?? '';
        $id_suffix = '';
        if (! empty($preventivo_id)) {
            // Estrai solo numeri dall'ID se presente (es. "PRV-2025-001" -> "2025001")
            $id_numeric = preg_replace('/[^0-9]/', '', $preventivo_id);
            if (! empty($id_numeric)) {
                $id_suffix = ' [ID-' . $id_numeric . ']';
            }
        } else {
            // Se non c'è ID preventivo, usa timestamp per unicità
            $id_suffix = ' [' . time() . ']';
        }
        
        $filename = $prefix . $day . '_' . $month . ' ' . $tipo_evento . ' (Menu ' . $menu_number . ')' . $id_suffix .  '.pdf';
        
        $this->log('📄 Nome file PDF generato: ' . $filename);
        
        return $filename;
    }

    /**
     * ========================================================================
     * HELPER: Sanitizza nome file
     * ========================================================================
     */
    private function sanitize_filename($string) {
        $string = preg_replace('/[^a-zA-Z0-9\s\-àáâãäåçèéêëìíîïñòóôõöøùúûüýÿ]/u', '', $string);
        return trim($string);
    }

    /**
     * ========================================================================
     * HELPER: Ottieni file template
     * ========================================================================
     */
    private function get_template_file($menu_type) {
        $mapping = array(
            'Menu 7' => 'menu-7-template.html',
            'Menu 74' => 'menu-7-4-template.html',
            'Menu 7-4' => 'menu-7-4-template.html',
            'Menu 747' => 'menu-7-4-7-template.html',
            'Menu 7-4-7' => 'menu-7-4-7-template.html'
        );
        
        return $mapping[$menu_type] ?? 'menu-7-template.html';
    }

    /**
     * ========================================================================
     * HELPER: Compila template HTML
     * ========================================================================
     */
    private function compile_template($template_path, $data) {
        $html = file_get_contents($template_path);
        
        // Sostituisci tutti i placeholder
        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $html = str_replace($placeholder, $value, $html);
        }
        
        // ✅ RIMUOVI LE RIGHE DEGLI EXTRA VUOTI
        // Pattern: <div ... >• {{extra1}} - {{extra1_formatted}}</div> con valori vuoti
        $html = $this->remove_empty_extra_lines($html, $data);
        
        // ✅ RIMUOVI LE RIGHE DEGLI OMAGGI VUOTI
        $html = $this->remove_empty_omaggio_lines($html, $data);
        
        return $html;
    }
    
    /**
     * ========================================================================
     * HELPER: Rimuove le righe degli extra vuoti dal HTML compilato
     * ========================================================================
     */
    private function remove_empty_extra_lines($html, $data) {
        // Extra 1
        if (empty($data['extra1']) || floatval($data['extra1_importo'] ?? 0) <= 0) {
            // Rimuovi l'intera riga che contiene l'extra1
            $html = preg_replace('/<div[^>]*>\\s*•\\s*' . preg_quote($data['extra1'] ?? '', '/') . '\\s*-\\s*' . preg_quote($data['extra1_formatted'] ?? '', '/') . '\\s*<\\/div>/i', '', $html);
            // Fallback: rimuovi righe che contengono "€0,00" o "€ 0,00"
            $html = preg_replace('/<div[^>]*>\\s*•\\s*-\\s*€\\s*0[,.]00\\s*<\\/div>/i', '', $html);
        }
        
        // Extra 2
        if (empty($data['extra2']) || floatval($data['extra2_importo'] ?? 0) <= 0) {
            $html = preg_replace('/<div[^>]*>\\s*•\\s*' . preg_quote($data['extra2'] ?? '', '/') . '\\s*-\\s*' .  preg_quote($data['extra2_formatted'] ?? '', '/') . '\\s*<\\/div>/i', '', $html);
            $html = preg_replace('/<div[^>]*>\\s*•\\s*-\\s*€\\s*0[,.]00\\s*<\\/div>/i', '', $html);
        }
        
        // Extra 3
        if (empty($data['extra3']) || floatval($data['extra3_importo'] ?? 0) <= 0) {
            $html = preg_replace('/<div[^>]*>\\s*•\\s*' . preg_quote($data['extra3'] ?? '', '/') .  '\\s*-\\s*' . preg_quote($data['extra3_formatted'] ?? '', '/') . '\\s*<\\/div>/i', '', $html);
            $html = preg_replace('/<div[^>]*>\\s*•\\s*-\\s*€\\s*0[,.]00\\s*<\\/div>/i', '', $html);
        }
        
        return $html;
    }
    
    /**
     * ========================================================================
     * HELPER: Rimuove le righe degli omaggi vuoti dal HTML compilato
     * ========================================================================
     */
    private function remove_empty_omaggio_lines($html, $data) {
        // Omaggio 1
        if (empty($data['omaggio1'])) {
            $html = preg_replace('/<div[^>]*>\\s*•\\s*<\\/div>/i', '', $html);
        }
        
        // Omaggio 2
        if (empty($data['omaggio2'])) {
            $html = preg_replace('/<div[^>]*>\\s*•\\s*<\\/div>/i', '', $html);
        }
        
        // Omaggio 3
        if (empty($data['omaggio3'])) {
            $html = preg_replace('/<div[^>]*>\\s*•\\s*<\\/div>/i', '', $html);
        }
        
        return $html;
    }

    /**
     * ========================================================================
     * HELPER: Crea PDF con Dompdf
     * ========================================================================
     */
    private function create_pdf_with_dompdf($html_content, $output_path) {
        try {
            if (! class_exists('\Dompdf\Dompdf')) {
                throw new \Exception('Dompdf non disponibile');
            }
            
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html_content);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            file_put_contents($output_path, $dompdf->output());
            
            return true;
            
        } catch (\Exception $e) {
            $this->log('❌ Errore Dompdf: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * ========================================================================
     * FALLBACK: Genera PDF semplice senza template
     * ========================================================================
     */
    private function generate_simple_pdf($output_path, $data) {
        try {
            $html = $this->generate_simple_html($data);
            return $this->create_pdf_with_dompdf($html, $output_path) ? $output_path : false;
        } catch (\Exception $e) {
            $this->log('❌ Errore PDF semplice: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * ========================================================================
     * HELPER: Genera HTML semplice
     * ========================================================================
     */
    private function generate_simple_html($data) {
        $nome_cliente = $data['nome_cliente'] ?? 'Cliente';
        $tipo_evento = $data['tipo_evento'] ?? 'Evento';
        $data_evento = $this->format_date($data['data_evento'] ?? '');
        $tipo_menu = $data['tipo_menu'] ?? 'Menu 7';
        $numero_invitati = $data['numero_invitati'] ?? 0;
        $telefono = $data['telefono'] ?? $data['cellulare'] ?? '';
        $email = $data['email'] ??  $data['mail'] ?? '';
        // ✅ FIX v12.4.0: Priorità a importo_totale
        $importo = $data['importo_totale'] ?? $data['importo_preventivo'] ?? 0;
        $acconto = $data['acconto'] ?? 0;
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h1 { color: #DAA520; text-align: center; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .label { font-weight: bold; color: #333; }
    </style>
</head>
<body>
    <h1>747 DISCO - PREVENTIVO</h1>
    
    <div class="section">
        <h2>Dati Cliente</h2>
        <p><span class="label">Nome:</span> ' . htmlspecialchars($nome_cliente) . '</p>
        <p><span class="label">Telefono:</span> ' . htmlspecialchars($telefono) .  '</p>
        <p><span class="label">Email:</span> ' . htmlspecialchars($email) . '</p>
    </div>
    
    <div class="section">
        <h2>Dettagli Evento</h2>
        <p><span class="label">Tipo Evento:</span> ' .  htmlspecialchars($tipo_evento) . '</p>
        <p><span class="label">Data:</span> ' . htmlspecialchars($data_evento) . '</p>
        <p><span class="label">Menu:</span> ' . htmlspecialchars($tipo_menu) .  '</p>
        <p><span class="label">Invitati:</span> ' . htmlspecialchars($numero_invitati) . '</p>
    </div>
    
    <div class="section">
        <h2>Importi</h2>
        <p><span class="label">Totale:</span> €' . number_format($importo, 2, ',', '.') . '</p>
        <p><span class="label">Acconto:</span> €' . number_format($acconto, 2, ',', '.') . '</p>
        <p><span class="label">Saldo:</span> €' . number_format($importo - $acconto, 2, ',', '. ') . '</p>
    </div>
</body>
</html>';
    }

    /**
     * ========================================================================
     * HELPER: Logging
     * ========================================================================
     */
    private function log($message, $level = 'INFO') {
        if (! $this->debug_mode) return;
        
        $timestamp = current_time('mysql');
        $log_message = "[{$timestamp}] [Disco747-PDF] [{$level}] {$message}";
        error_log($log_message);
    }
}