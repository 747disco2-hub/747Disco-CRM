<?php
/**
 * Classe per il parsing e analisi di file Excel dei preventivi
 * Estrae dati strutturati dai file Excel esistenti su Google Drive
 *
 * @package    Disco747_CRM
 * @subpackage Generators
 * @since      11.4.2
 * @version    11.4.2
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Generators;

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_Excel_Parser
 * 
 * Analizza file Excel dei preventivi per estrarre dati strutturati
 * Supporta sia il formato nuovo che quello legacy
 * 
 * @since 11.4.2
 */
class Disco747_Excel_Parser {
    
    /**
     * Mappatura celle Excel per estrazione dati
     */
    private $cell_mapping = array(
        // Dati evento
        'data_evento' => 'B3',
        'tipo_evento' => 'B4', 
        'tipo_menu' => 'B1',      // Fallback per file legacy
        'orario' => 'B5',
        'numero_invitati' => 'B6',
        
        // Dati cliente
        'nome_referente' => 'B8',
        'cognome_referente' => 'B9', 
        'cellulare' => 'B10',
        'email' => 'B11',
        
        // Dati economici
        'importo' => 'B13',
        'acconto' => 'B14',
        'saldo' => 'B15',
        
        // Omaggi
        'omaggio1' => 'B17',
        'omaggio2' => 'B18', 
        'omaggio3' => 'B19',
        
        // Extra a pagamento
        'extra1_nome' => 'B21',
        'extra1_prezzo' => 'C21',
        'extra2_nome' => 'B22',
        'extra2_prezzo' => 'C22', 
        'extra3_nome' => 'B23',
        'extra3_prezzo' => 'C23'
    );
    
    /**
     * Pattern per riconoscere formati nome file
     */
    private $filename_patterns = array(
        'new_format' => '/^(NO\s+|CONF\s+)?(\d{1,2}_\d{1,2})\s+(.+?)\s+\(Menu\s+(\d+|7|74|747)\)\.xlsx?$/i',
        'legacy_format' => '/^(NO\s+|CONF\s+)?(\d{1,2}_\d{1,2})\s+(.+?)\.xlsx?$/i'
    );
    
    /**
     * Debug e logging
     */
    private $debug_mode = true;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->log('[747Disco-Scan] Excel Parser inizializzato');
    }
    
    // ============================================================================
    // METODI PRINCIPALI DI PARSING
    // ============================================================================
    
    /**
     * Analizza un file Excel e restituisce array strutturato con tutti i dati
     *
     * @param string $excel_content Contenuto binario del file Excel
     * @param string $filename Nome del file per context
     * @return array Dati estratti o array con errori
     */
    public function parse_excel_file($excel_content, $filename) {
        $this->log('[747Disco-Scan] Parsing file: ' . $filename);
        
        $result = array(
            'success' => false,
            'data' => array(),
            'errors' => array(),
            'warnings' => array()
        );
        
        try {
            // Carica il file Excel in memoria
            $workbook = $this->load_excel_content($excel_content);
            if (!$workbook) {
                throw new \Exception('Impossibile caricare file Excel');
            }
            
            // Seleziona il primo foglio (Foglio 0)
            $worksheet = $this->get_first_worksheet($workbook);
            if (!$worksheet) {
                throw new \Exception('Nessun foglio di lavoro trovato');
            }
            
            // Estrai dati dal foglio
            $data = $this->extract_data_from_worksheet($worksheet, $filename);
            
            // Determina stato dal filename e/o acconto
            $data['stato'] = $this->determine_status($filename, $data['acconto'] ?? 0);
            
            // Aggiungi metadati
            $data['source'] = 'excel_scan';
            $data['parsed_at'] = current_time('mysql');
            
            $result['success'] = true;
            $result['data'] = $data;
            
            $this->log('[747Disco-Scan] File parsed con successo: ' . count($data) . ' campi estratti');
            
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            $this->log('[747Disco-Scan] Errore parsing: ' . $e->getMessage(), 'error');
        }
        
        return $result;
    }
    
    /**
     * Carica contenuto Excel in memoria usando SimpleXLSX o PHPSpreadsheet
     *
     * @param string $excel_content Contenuto binario
     * @return mixed Workbook object o false
     */
    private function load_excel_content($excel_content) {
        // Strategia 1: Prova con SimpleXLSX se disponibile (più leggero)
        if (class_exists('SimpleXLSX')) {
            try {
                return \SimpleXLSX::parseData($excel_content);
            } catch (\Exception $e) {
                $this->log('[747Disco-Scan] SimpleXLSX fallito: ' . $e->getMessage());
            }
        }
        
        // Strategia 2: Usa PHPSpreadsheet se disponibile
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            try {
                // Salva temporaneamente su filesystem
                $temp_file = $this->create_temp_file($excel_content);
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($temp_file);
                $reader->setReadDataOnly(true);
                $workbook = $reader->load($temp_file);
                unlink($temp_file);
                return $workbook;
            } catch (\Exception $e) {
                $this->log('[747Disco-Scan] PHPSpreadsheet fallito: ' . $e->getMessage());
                if (isset($temp_file) && file_exists($temp_file)) {
                    unlink($temp_file);
                }
            }
        }
        
        // Strategia 3: Parser manuale base per formato Excel semplice
        return $this->basic_excel_parser($excel_content);
    }
    
    /**
     * Ottiene il primo foglio di lavoro
     *
     * @param mixed $workbook Workbook object
     * @return mixed Worksheet object o false
     */
    private function get_first_worksheet($workbook) {
        // SimpleXLSX
        if (is_array($workbook) && isset($workbook['rows'])) {
            return $workbook;
        }
        
        // PHPSpreadsheet
        if (method_exists($workbook, 'getSheet')) {
            try {
                return $workbook->getSheet(0);
            } catch (\Exception $e) {
                $this->log('[747Disco-Scan] Errore accesso foglio: ' . $e->getMessage());
            }
        }
        
        // Parser base
        if (is_array($workbook)) {
            return $workbook;
        }
        
        return false;
    }
    
    /**
     * Estrae tutti i dati dal foglio di lavoro secondo la mappatura
     *
     * @param mixed $worksheet Foglio di lavoro
     * @param string $filename Nome file per context
     * @return array Dati estratti
     */
    private function extract_data_from_worksheet($worksheet, $filename) {
        $data = array();
        $warnings = array();
        
        foreach ($this->cell_mapping as $field => $cell_ref) {
            try {
                $value = $this->get_cell_value($worksheet, $cell_ref);
                $data[$field] = $this->clean_and_format_value($value, $field);
            } catch (\Exception $e) {
                $warnings[] = "Campo {$field} (cella {$cell_ref}): " . $e->getMessage();
                $data[$field] = null;
            }
        }
        
        // Gestione tipo_menu per file legacy senza "(Menu X)" nel nome
        if (empty($data['tipo_menu']) || $data['tipo_menu'] === null) {
            $data['tipo_menu'] = $this->extract_menu_from_filename($filename);
        }
        
        // Se ancora non trovato, usa fallback cella B1
        if (empty($data['tipo_menu'])) {
            $menu_from_cell = $this->get_cell_value($worksheet, 'B1');
            $data['tipo_menu'] = $this->extract_menu_number_from_text($menu_from_cell);
        }
        
        // Validazione e pulizia finale
        $data = $this->validate_and_clean_data($data);
        
        if (!empty($warnings)) {
            $this->log('[747Disco-Scan] Warnings: ' . implode('; ', $warnings));
        }
        
        return $data;
    }
    
    /**
     * Legge il valore di una cella specifica
     *
     * @param mixed $worksheet Foglio di lavoro
     * @param string $cell_ref Riferimento cella (es: "B3")
     * @return mixed Valore della cella
     */
    private function get_cell_value($worksheet, $cell_ref) {
        // Converte riferimento cella in coordinate (B3 -> colonna 2, riga 3)
        $coords = $this->cell_ref_to_coordinates($cell_ref);
        $col = $coords['col'];
        $row = $coords['row'];
        
        // SimpleXLSX (array di righe)
        if (is_array($worksheet) && isset($worksheet['rows'])) {
            return $worksheet['rows'][$row - 1][$col - 1] ?? null;
        }
        
        // PHPSpreadsheet
        if (method_exists($worksheet, 'getCell')) {
            try {
                $cell = $worksheet->getCell($cell_ref);
                return $cell->getCalculatedValue();
            } catch (\Exception $e) {
                return null;
            }
        }
        
        // Parser base (array semplice)
        if (is_array($worksheet) && isset($worksheet[$row - 1][$col - 1])) {
            return $worksheet[$row - 1][$col - 1];
        }
        
        return null;
    }
    
    // ============================================================================
    // METODI DI UTILITÀ E SUPPORTO
    // ============================================================================
    
    /**
     * Converte riferimento cella (B3) in coordinate numeriche
     *
     * @param string $cell_ref Riferimento cella
     * @return array Coordinate ['col' => int, 'row' => int]
     */
    private function cell_ref_to_coordinates($cell_ref) {
        if (preg_match('/^([A-Z]+)(\d+)$/', strtoupper($cell_ref), $matches)) {
            $col_letters = $matches[1];
            $row = intval($matches[2]);
            
            // Converte lettere colonna in numero (A=1, B=2, AA=27, etc)
            $col = 0;
            for ($i = 0; $i < strlen($col_letters); $i++) {
                $col = $col * 26 + (ord($col_letters[$i]) - ord('A') + 1);
            }
            
            return array('col' => $col, 'row' => $row);
        }
        
        throw new \Exception("Riferimento cella non valido: {$cell_ref}");
    }
    
    /**
     * Pulisce e formatta un valore secondo il tipo di campo
     *
     * @param mixed $value Valore grezzo
     * @param string $field Nome campo
     * @return mixed Valore pulito
     */
    private function clean_and_format_value($value, $field) {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Campi numerici
        if (in_array($field, array('numero_invitati', 'importo', 'acconto', 'saldo', 'extra1_prezzo', 'extra2_prezzo', 'extra3_prezzo'))) {
            // Rimuove caratteri non numerici tranne punto e virgola
            $cleaned = preg_replace('/[^\d.,]/', '', (string)$value);
            $cleaned = str_replace(',', '.', $cleaned);
            return floatval($cleaned);
        }
        
        // Campo data
        if ($field === 'data_evento') {
            return $this->parse_date_field($value);
        }
        
        // Campi testo
        $cleaned = trim((string)$value);
        return !empty($cleaned) ? $cleaned : null;
    }
    
    /**
     * Determina lo stato del preventivo dal filename e acconto
     *
     * @param string $filename Nome file
     * @param float $acconto Importo acconto
     * @return string Stato (CONF, NO, o vuoto)
     */
    private function determine_status($filename, $acconto) {
        // Controlla prefisso nel nome file
        if (preg_match('/^CONF\s+/', $filename)) {
            return 'CONF';
        }
        
        if (preg_match('/^NO\s+/', $filename)) {
            return 'NO';
        }
        
        // Logica basata su acconto
        if ($acconto && $acconto > 0) {
            return 'CONF';
        }
        
        return ''; // Stato vuoto per preventivi in sospeso
    }
    
    /**
     * Estrae tipo menu dal nome file (nuovo formato)
     *
     * @param string $filename Nome file
     * @return string Tipo menu o null
     */
    private function extract_menu_from_filename($filename) {
        if (preg_match($this->filename_patterns['new_format'], $filename, $matches)) {
            return $matches[4]; // Gruppo 4 contiene il numero menu
        }
        
        return null;
    }
    
    /**
     * Estrae numero menu da testo generico (fallback cella B1)
     *
     * @param string $text Testo da analizzare
     * @return string Numero menu o null
     */
    private function extract_menu_number_from_text($text) {
        if (empty($text)) {
            return null;
        }
        
        // Cerca pattern "Menu X" o "Menu XXX"
        if (preg_match('/Menu\s*(\d+)/i', $text, $matches)) {
            return $matches[1];
        }
        
        // Cerca solo numeri 7, 74, 747
        if (preg_match('/\b(7|74|747)\b/', $text, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Parsing intelligente campo data
     *
     * @param mixed $value Valore data grezzo
     * @return string Data formattata Y-m-d o null
     */
    private function parse_date_field($value) {
        if (empty($value)) {
            return null;
        }
        
        // Se è già una data PHP
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }
        
        // Se è un timestamp Excel (numero seriale)
        if (is_numeric($value) && $value > 25569) { // 25569 = 1/1/1970 in Excel
            $unix_timestamp = ($value - 25569) * 86400;
            return date('Y-m-d', $unix_timestamp);
        }
        
        // Prova parsing come stringa data
        $date_string = trim((string)$value);
        
        // Pattern comuni italiani: dd/mm/yyyy, dd-mm-yyyy, dd_mm
        $patterns = array(
            '/^(\d{1,2})[\/\-_](\d{1,2})[\/\-_]?(\d{4})?$/' => 'd/m/Y',
            '/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/' => 'Y/m/d'
        );
        
        foreach ($patterns as $pattern => $format) {
            if (preg_match($pattern, $date_string, $matches)) {
                if (count($matches) === 3) { // Solo giorno e mese
                    $day = intval($matches[1]);
                    $month = intval($matches[2]);
                    $year = date('Y'); // Anno corrente
                } else {
                    $day = intval($matches[1]);
                    $month = intval($matches[2]);
                    $year = intval($matches[3]);
                }
                
                if (checkdate($month, $day, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Validazione finale e pulizia dati
     *
     * @param array $data Dati estratti
     * @return array Dati validati
     */
    private function validate_and_clean_data($data) {
        // Calcola saldo se mancante
        if (isset($data['importo']) && isset($data['acconto']) && !isset($data['saldo'])) {
            $data['saldo'] = $data['importo'] - $data['acconto'];
        }
        
        // Normalizza nomi/cognomi
        if (isset($data['nome_referente'])) {
            $data['nome_referente'] = ucwords(strtolower(trim($data['nome_referente'])));
        }
        
        if (isset($data['cognome_referente'])) {
            $data['cognome_referente'] = ucwords(strtolower(trim($data['cognome_referente'])));
        }
        
        // Pulisce email
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $data['email'] = null;
            }
        }
        
        // Normalizza telefono
        if (isset($data['cellulare'])) {
            $phone = preg_replace('/[^\d+]/', '', $data['cellulare']);
            $data['cellulare'] = !empty($phone) ? $phone : null;
        }
        
        return $data;
    }
    
    /**
     * Crea file temporaneo per processing
     *
     * @param string $content Contenuto binario
     * @return string Path file temporaneo
     */
    private function create_temp_file($content) {
        $temp_file = wp_tempnam('disco747_excel_');
        file_put_contents($temp_file, $content);
        return $temp_file;
    }
    
    /**
     * Parser Excel base di fallback (per casi senza librerie)
     *
     * @param string $excel_content Contenuto binario
     * @return array|false Array semplificato o false
     */
    private function basic_excel_parser($excel_content) {
        // Implementazione molto base - estrae solo testo se possibile
        // Questo è un fallback per ambienti senza librerie Excel
        $this->log('[747Disco-Scan] Uso parser Excel base di fallback');
        
        // Cerca pattern XML se è un Excel moderno (XLSX)
        if (strpos($excel_content, 'PK') === 0) {
            // È un file ZIP (XLSX), parsing complesso non implementato nel fallback
            return false;
        }
        
        // Per ora returna false, forzando l'uso di librerie esterne
        return false;
    }
    
    /**
     * Logging con prefisso identificativo
     *
     * @param string $message Messaggio da loggare
     * @param string $level Livello di log
     */
    private function log($message, $level = 'info') {
        if ($this->debug_mode && function_exists('error_log')) {
            $prefix = '[' . date('Y-m-d H:i:s') . '] ';
            error_log($prefix . $message);
        }
    }
}