<?php
/**
 * 747 Disco CRM - Sistema Template Avanzato
 * 
 * Gestisce i template HTML e Excel per la generazione di preventivi.
 * Supporta template dinamici, compilazione variabili e caching.
 * 
 * @package    Disco747_CRM
 * @subpackage Generators
 * @since      1.0.0
 * @version    1.0.0
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Generators;

use Disco747_CRM\Core\Disco747_Config;
use Exception;

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Disco747_Templates
 * 
 * Sistema di template intelligente per 747 Disco CRM
 * Gestisce HTML, Excel e template personalizzati
 */
class Disco747_Templates {

    /**
     * Istanza singleton
     * @var Disco747_Templates
     */
    private static $instance = null;

    /**
     * Istanza di configurazione
     * @var Disco747_Config
     */
    private $config;

    /**
     * Modalità debug
     * @var bool
     */
    private $debug_mode;

    /**
     * Directory templates
     * @var string
     */
    private $templates_dir;

    /**
     * Directory cache
     * @var string
     */
    private $cache_dir;

    /**
     * Template compilati in cache
     * @var array
     */
    private $compiled_cache = [];

    /**
     * Variabili globali template
     * @var array
     */
    private $global_vars = [];

    /**
     * Template predefiniti per tipi menu
     * @var array
     */
    private $menu_templates = [
        'Menu 7' => 'menu-7-template.html',
        'Menu 7-4' => 'menu-7-4-template.html',
        'Menu 7-4-7' => 'menu-7-4-7-template.html'
    ];

    /**
     * Template email predefiniti
     * @var array
     */
    private $email_templates = [
        'preventivo_nuovo' => 'email-nuovo-preventivo.html',
        'preventivo_confermato' => 'email-preventivo-confermato.html',
        'promemoria_evento' => 'email-promemoria-evento.html',
        'followup_evento' => 'email-followup-evento.html'
    ];

    /**
     * Pattern per sostituzione variabili
     * @var array
     */
    private $variable_patterns = [
        'simple' => '/\{\{([^}]+)\}\}/',
        'conditional' => '/\{\{if\s+([^}]+)\}\}(.*?)\{\{\/if\}\}/s',
        'loop' => '/\{\{each\s+([^}]+)\}\}(.*?)\{\{\/each\}\}/s',
        'include' => '/\{\{include\s+([^}]+)\}\}/',
        'function' => '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\(([^)]*)\)\}\}/'
    ];

    /**
     * Costruttore privato per singleton
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->config = Disco747_Config::get_instance();
        $this->debug_mode = $this->config->get('debug_mode', false);
        
        // Imposta directory
        $upload = wp_upload_dir();
        $this->templates_dir = $upload['basedir'] . '/747disco-crm/templates/';
        $this->cache_dir = $upload['basedir'] . '/747disco-crm/cache/';
        
        // Crea directory se non esistono
        $this->ensure_directories();
        
        // Inizializza variabili globali
        $this->init_global_vars();
        
        // Crea template predefiniti se non esistono
        $this->create_default_templates();
        
        $this->log('Sistema template inizializzato');
    }

    /**
     * Ottieni istanza singleton
     * 
     * @return Disco747_Templates
     * @since 1.0.0
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Assicura che le directory esistano
     *
     * @since 1.0.0
     */
    private function ensure_directories() {
        $directories = [$this->templates_dir, $this->cache_dir];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Crea file .htaccess per sicurezza
                $htaccess = $dir . '.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, "deny from all\n");
                }
            }
        }
    }

    /**
     * Inizializza variabili globali
     *
     * @since 1.0.0
     */
    private function init_global_vars() {
        $this->global_vars = [
            'company_name' => $this->config->get('company_name', '747 Disco'),
            'company_email' => $this->config->get('company_email', 'info@gestionale.747disco.it'),
            'company_phone' => $this->config->get('company_phone', '06 123456789'),
            'company_website' => $this->config->get('company_website', 'https://gestionale.747disco.it'),
            'current_date' => date('d/m/Y'),
            'current_year' => date('Y'),
            'plugin_version' => defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '1.0.0'
        ];
    }

    /**
     * Renderizza un template con i dati forniti
     *
     * @param string $template_name Nome del template
     * @param array $data Dati per il template
     * @param array $options Opzioni di rendering
     * @return string Template renderizzato
     * @since 1.0.0
     */
    public function render($template_name, $data = [], $options = []) {
        try {
            $this->log("Rendering template: {$template_name}");
            
            // Carica il template
            $template_content = $this->load_template($template_name);
            
            if (!$template_content) {
                throw new Exception("Template non trovato: {$template_name}");
            }
            
            // Unisci dati con variabili globali
            $merged_data = array_merge($this->global_vars, $data);
            
            // Compila il template
            $compiled = $this->compile_template($template_content, $merged_data, $options);
            
            $this->log("Template renderizzato con successo: {$template_name}");
            return $compiled;
            
        } catch (Exception $e) {
            $this->log("Errore rendering template {$template_name}: " . $e->getMessage(), 'ERROR');
            return $this->get_error_template($e->getMessage());
        }
    }

    /**
     * Carica un template dal filesystem
     *
     * @param string $template_name Nome del template
     * @return string|false Contenuto del template
     * @since 1.0.0
     */
    private function load_template($template_name) {
        // Costruisci percorso template
        $template_file = $this->templates_dir . $template_name;
        
        // Aggiungi estensione se mancante
        if (!pathinfo($template_name, PATHINFO_EXTENSION)) {
            $template_file .= '.html';
        }
        
        // Verifica sicurezza percorso
        if (!$this->is_safe_path($template_file)) {
            $this->log("Percorso template non sicuro: {$template_file}", 'ERROR');
            return false;
        }
        
        // Carica da cache se disponibile
        $cache_key = md5($template_file);
        if (isset($this->compiled_cache[$cache_key])) {
            return $this->compiled_cache[$cache_key];
        }
        
        // Carica dal file
        if (file_exists($template_file)) {
            $content = file_get_contents($template_file);
            $this->compiled_cache[$cache_key] = $content;
            return $content;
        }
        
        $this->log("Template non trovato: {$template_file}", 'WARNING');
        return false;
    }

    /**
     * Compila un template sostituendo le variabili
     *
     * @param string $template_content Contenuto del template
     * @param array $data Dati per la sostituzione
     * @param array $options Opzioni di compilazione
     * @return string Template compilato
     * @since 1.0.0
     */
    private function compile_template($template_content, $data, $options = []) {
        $compiled = $template_content;
        
        // 1. Gestisci include
        $compiled = $this->process_includes($compiled);
        
        // 2. Gestisci condizionali
        $compiled = $this->process_conditionals($compiled, $data);
        
        // 3. Gestisci loop
        $compiled = $this->process_loops($compiled, $data);
        
        // 4. Gestisci funzioni
        $compiled = $this->process_functions($compiled, $data);
        
        // 5. Sostituisci variabili semplici
        $compiled = $this->process_simple_variables($compiled, $data);
        
        // 6. Post-processing
        if (!empty($options['minify'])) {
            $compiled = $this->minify_html($compiled);
        }
        
        return $compiled;
    }

    /**
     * Processa gli include nei template
     *
     * @param string $content Contenuto template
     * @return string Contenuto con include processati
     * @since 1.0.0
     */
    private function process_includes($content) {
        return preg_replace_callback(
            $this->variable_patterns['include'],
            function($matches) {
                $include_name = trim($matches[1]);
                $include_content = $this->load_template($include_name);
                return $include_content ?: "<!-- Include non trovato: {$include_name} -->";
            },
            $content
        );
    }

    /**
     * Processa i condizionali nei template
     *
     * @param string $content Contenuto template
     * @param array $data Dati
     * @return string Contenuto con condizionali processati
     * @since 1.0.0
     */
    private function process_conditionals($content, $data) {
        return preg_replace_callback(
            $this->variable_patterns['conditional'],
            function($matches) use ($data) {
                $condition = trim($matches[1]);
                $inner_content = $matches[2];
                
                if ($this->evaluate_condition($condition, $data)) {
                    return $inner_content;
                }
                return '';
            },
            $content
        );
    }

    /**
     * Processa i loop nei template
     *
     * @param string $content Contenuto template
     * @param array $data Dati
     * @return string Contenuto con loop processati
     * @since 1.0.0
     */
    private function process_loops($content, $data) {
        return preg_replace_callback(
            $this->variable_patterns['loop'],
            function($matches) use ($data) {
                $loop_var = trim($matches[1]);
                $loop_template = $matches[2];
                
                if (!isset($data[$loop_var]) || !is_array($data[$loop_var])) {
                    return '';
                }
                
                $output = '';
                foreach ($data[$loop_var] as $index => $item) {
                    $loop_data = array_merge($data, [
                        'item' => $item,
                        'index' => $index,
                        'first' => $index === 0,
                        'last' => $index === count($data[$loop_var]) - 1
                    ]);
                    
                    $output .= $this->process_simple_variables($loop_template, $loop_data);
                }
                
                return $output;
            },
            $content
        );
    }

    /**
     * Processa le funzioni nei template
     *
     * @param string $content Contenuto template
     * @param array $data Dati
     * @return string Contenuto con funzioni processate
     * @since 1.0.0
     */
    private function process_functions($content, $data) {
        return preg_replace_callback(
            $this->variable_patterns['function'],
            function($matches) use ($data) {
                $function_name = $matches[1];
                $params = !empty($matches[2]) ? explode(',', $matches[2]) : [];
                
                return $this->call_template_function($function_name, $params, $data);
            },
            $content
        );
    }

    /**
     * Processa le variabili semplici nei template
     *
     * @param string $content Contenuto template
     * @param array $data Dati
     * @return string Contenuto con variabili sostituite
     * @since 1.0.0
     */
    private function process_simple_variables($content, $data) {
        return preg_replace_callback(
            $this->variable_patterns['simple'],
            function($matches) use ($data) {
                $var_name = trim($matches[1]);
                return $this->get_variable_value($var_name, $data);
            },
            $content
        );
    }

    /**
     * Ottieni il valore di una variabile dai dati
     *
     * @param string $var_name Nome variabile
     * @param array $data Dati
     * @return string Valore della variabile
     * @since 1.0.0
     */
    private function get_variable_value($var_name, $data) {
        // Supporta notazione dot (es: client.nome)
        $parts = explode('.', $var_name);
        $value = $data;
        
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return "{{$var_name}}"; // Mantieni placeholder se non trovato
            }
        }
        
        return $this->format_value($value);
    }

    /**
     * Formatta un valore per l'output
     *
     * @param mixed $value Valore da formattare
     * @return string Valore formattato
     * @since 1.0.0
     */
    private function format_value($value) {
        if (is_null($value)) {
            return '';
        }
        
        if (is_bool($value)) {
            return $value ? 'Sì' : 'No';
        }
        
        if (is_numeric($value)) {
            return number_format($value, 2, ',', '.');
        }
        
        return esc_html((string)$value);
    }

    /**
     * Valuta una condizione
     *
     * @param string $condition Condizione da valutare
     * @param array $data Dati
     * @return bool Risultato valutazione
     * @since 1.0.0
     */
    private function evaluate_condition($condition, $data) {
        // Implementazione semplice per condizioni base
        if (preg_match('/^(\w+)$/', $condition, $matches)) {
            $var_name = $matches[1];
            return !empty($data[$var_name]);
        }
        
        if (preg_match('/^(\w+)\s*==\s*["\']([^"\']*)["\']$/', $condition, $matches)) {
            $var_name = $matches[1];
            $expected_value = $matches[2];
            return isset($data[$var_name]) && $data[$var_name] == $expected_value;
        }
        
        return false;
    }

    /**
     * Chiama una funzione template
     *
     * @param string $function_name Nome funzione
     * @param array $params Parametri
     * @param array $data Dati
     * @return string Risultato funzione
     * @since 1.0.0
     */
    private function call_template_function($function_name, $params, $data) {
        switch ($function_name) {
            case 'date':
                $format = $params[0] ?? 'd/m/Y';
                return date(trim($format, '"\''));
                
            case 'format_currency':
                $value = $params[0] ?? 0;
                $currency = $params[1] ?? '€';
                return number_format((float)$value, 2, ',', '.') . ' ' . trim($currency, '"\'');
                
            case 'upper':
                $text = $params[0] ?? '';
                return strtoupper($this->get_variable_value(trim($text, '"\''), $data));
                
            case 'lower':
                $text = $params[0] ?? '';
                return strtolower($this->get_variable_value(trim($text, '"\''), $data));
                
            default:
                return "{{$function_name}()}";
        }
    }

    /**
     * Verifica se un percorso è sicuro
     *
     * @param string $path Percorso da verificare
     * @return bool True se sicuro
     * @since 1.0.0
     */
    private function is_safe_path($path) {
        $real_path = realpath($path);
        $safe_dir = realpath($this->templates_dir);
        
        return $real_path && $safe_dir && strpos($real_path, $safe_dir) === 0;
    }

    /**
     * Minifica HTML
     *
     * @param string $html HTML da minificare
     * @return string HTML minificato
     * @since 1.0.0
     */
    private function minify_html($html) {
        // Rimuovi commenti HTML
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);
        
        // Rimuovi spazi extra
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Rimuovi spazi attorno ai tag
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }

    /**
     * Ottieni template di errore
     *
     * @param string $error_message Messaggio errore
     * @return string Template errore
     * @since 1.0.0
     */
    private function get_error_template($error_message) {
        return "<!-- Errore Template: " . esc_html($error_message) . " -->";
    }

    /**
     * Crea template predefiniti
     *
     * @since 1.0.0
     */
    public function create_default_templates() {
        $templates = [
            'preventivo-base.html' => $this->get_base_quote_template(),
            'email-preventivo.html' => $this->get_email_template(),
            'ricevuta-acconto.html' => $this->get_receipt_template()
        ];
        
        foreach ($templates as $filename => $content) {
            $file_path = $this->templates_dir . $filename;
            
            if (!file_exists($file_path)) {
                file_put_contents($file_path, $content);
                $this->log("Template creato: {$filename}");
            }
        }
    }

    /**
     * Template base per preventivo
     *
     * @return string Template HTML
     * @since 1.0.0
     */
    private function get_base_quote_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Preventivo {{company_name}}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #DAA520; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 28px; font-weight: bold; color: #DAA520; }
        .company-info { font-size: 14px; margin-top: 10px; }
        .quote-details { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .client-info { margin: 20px 0; }
        .event-details { margin: 20px 0; }
        .menu-section { margin: 20px 0; }
        .totals { background: #fff; border: 2px solid #DAA520; padding: 20px; margin: 20px 0; }
        .total-line { display: flex; justify-content: space-between; margin: 5px 0; }
        .final-total { font-weight: bold; font-size: 18px; color: #DAA520; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; }
        h2 { color: #DAA520; border-bottom: 1px solid #DAA520; padding-bottom: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">{{company_name}}</div>
        <div class="company-info">
            Email: {{company_email}} | Tel: {{company_phone}}<br>
            www.gestionale.747disco.it
        </div>
    </div>
    
    <div class="quote-details">
        <h1>PREVENTIVO N. {{numero_preventivo}}</h1>
        <p><strong>Data:</strong> {{current_date}}</p>
    </div>
    
    <div class="client-info">
        <h2>DATI CLIENTE</h2>
        <p><strong>Nome:</strong> {{nome_cliente}}</p>
        <p><strong>Email:</strong> {{mail}}</p>
        <p><strong>Telefono:</strong> {{telefono}}</p>
    </div>
    
    <div class="event-details">
        <h2>DETTAGLI EVENTO</h2>
        <p><strong>Tipo Evento:</strong> {{tipo_evento}}</p>
        <p><strong>Data Evento:</strong> {{data_evento}}</p>
        <p><strong>Numero Ospiti:</strong> {{numero_ospiti}}</p>
        <p><strong>Location:</strong> {{location}}</p>
    </div>
    
    <div class="menu-section">
        <h2>MENU SELEZIONATO</h2>
        <p><strong>Tipo Menu:</strong> {{tipo_menu}}</p>
        {{if menu_details}}
        <div class="menu-details">{{menu_details}}</div>
        {{/if}}
    </div>
    
    <div class="totals">
        <div class="total-line">
            <span>Subtotale:</span>
            <span>{{format_currency(subtotale)}} €</span>
        </div>
        {{if sconto_applicato}}
        <div class="total-line">
            <span>Sconto:</span>
            <span>- {{format_currency(sconto_applicato)}} €</span>
        </div>
        {{/if}}
        <div class="total-line final-total">
            <span>TOTALE:</span>
            <span>{{format_currency(totale)}} €</span>
        </div>
        {{if acconto}}
        <div class="total-line">
            <span>Acconto versato:</span>
            <span>{{format_currency(acconto)}} €</span>
        </div>
        <div class="total-line">
            <span>Saldo da versare:</span>
            <span>{{format_currency(saldo)}} €</span>
        </div>
        {{/if}}
    </div>
    
    <div class="footer">
        <p>Preventivo valido fino al {{data_scadenza}}</p>
        <p>Grazie per averci scelto!</p>
        <p><em>{{company_name}} - La tua festa, la nostra passione</em></p>
    </div>
</body>
</html>';
    }

    /**
     * Template email
     *
     * @return string Template HTML email
     * @since 1.0.0
     */
    private function get_email_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Il tuo preventivo è pronto!</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; background: #DAA520; color: white; padding: 20px; border-radius: 5px;">
            <h1>{{company_name}}</h1>
            <p>Il tuo preventivo è pronto!</p>
        </div>
        
        <div style="padding: 20px; background: #f8f9fa; margin: 20px 0; border-radius: 5px;">
            <p>Ciao <strong>{{nome_cliente}}</strong>,</p>
            
            <p>Abbiamo preparato il preventivo per il tuo <strong>{{tipo_evento}}</strong> 
            del <strong>{{data_evento}}</strong>.</p>
            
            <p><strong>Dettagli:</strong></p>
            <ul>
                <li>Numero ospiti: {{numero_ospiti}}</li>
                <li>Menu: {{tipo_menu}}</li>
                <li>Totale: {{format_currency(totale)}} €</li>
            </ul>
            
            <p>Trovi il preventivo completo in allegato.</p>
            
            <p style="text-align: center; margin: 30px 0;">
                <a href="mailto:{{company_email}}" style="background: #DAA520; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                    Contattaci per confermare
                </a>
            </p>
        </div>
        
        <div style="text-align: center; color: #666; font-size: 12px; margin-top: 30px;">
            <p>{{company_name}} - {{company_email}} - {{company_phone}}</p>
            <p>www.gestionale.747disco.it</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Template ricevuta acconto
     *
     * @return string Template HTML ricevuta
     * @since 1.0.0
     */
    private function get_receipt_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ricevuta Acconto</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .receipt-info { background: #f0f0f0; padding: 20px; border-radius: 5px; }
        .amount { font-size: 24px; font-weight: bold; color: #DAA520; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{company_name}}</h1>
        <h2>RICEVUTA ACCONTO</h2>
    </div>
    
    <div class="receipt-info">
        <p><strong>Cliente:</strong> {{nome_cliente}}</p>
        <p><strong>Evento:</strong> {{tipo_evento}} del {{data_evento}}</p>
        <p><strong>Data Pagamento:</strong> {{current_date}}</p>
        
        <div class="amount">
            ACCONTO VERSATO: {{format_currency(acconto)}} €
        </div>
        
        <p><strong>Saldo rimanente:</strong> {{format_currency(saldo)}} €</p>
        <p><strong>Da versare entro:</strong> {{data_saldo}}</p>
    </div>
    
    <div style="margin-top: 40px; text-align: center;">
        <p>Grazie per la fiducia!</p>
        <p><em>{{company_name}}</em></p>
    </div>
</body>
</html>';
    }

    /**
     * Log per debug
     *
     * @param string $message Messaggio
     * @param string $level Livello (INFO, WARNING, ERROR)
     * @since 1.0.0
     */
    private function log($message, $level = 'INFO') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $timestamp = date('[Y-m-d H:i:s]');
            $log_message = "[747Disco-Templates] [{$level}] {$message}";
            error_log("{$timestamp} {$log_message}");
        }
    }

    /**
     * Previeni clonazione
     */
    private function __clone() {}

    /**
     * Previeni unserialize
     */
    public function __wakeup() {}
}