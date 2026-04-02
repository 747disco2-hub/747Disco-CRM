<?php
/**
 * Script Debug Hook AJAX - 747 Disco CRM
 * Da salvare in: /includes/admin/debug-hooks.php
 * 
 * Questo script verifica i conflitti di hook AJAX direttamente dal plugin
 * senza toccare file WordPress esterni
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

/**
 * Classe Debug Hook per verificare conflitti AJAX
 */
class Disco747_Debug_Hooks {
    
    public function __construct() {
        // Aggiungi debug solo per amministratori nelle pagine del plugin
        add_action('admin_footer', array($this, 'show_ajax_debug'));
    }
    
    /**
     * Mostra debug hook AJAX nelle pagine admin del plugin
     */
    public function show_ajax_debug() {
        // Mostra solo nelle pagine del plugin
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'disco747') === false) {
            return;
        }
        
        // Solo per amministratori
        if (!current_user_can('manage_options')) {
            return;
        }
        
        global $wp_filter;
        
        $ajax_actions_to_check = array(
            'wp_ajax_disco747_save_preventivo',
            'wp_ajax_disco747_delete_preventivo',
            'wp_ajax_disco747_get_preventivo'
        );
        
        echo '<div id="disco747-debug-hooks" style="
            position: fixed; 
            bottom: 20px; 
            right: 20px; 
            background: #fff; 
            border: 2px solid #0073aa; 
            padding: 15px; 
            z-index: 9999; 
            max-width: 400px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        ">';
        
        echo '<h4 style="margin: 0 0 10px 0; color: #0073aa;">🔍 Debug Hook AJAX</h4>';
        
        $total_conflicts = 0;
        
        foreach ($ajax_actions_to_check as $ajax_action) {
            echo '<div style="margin-bottom: 10px;">';
            echo '<strong>' . str_replace('wp_ajax_', '', $ajax_action) . ':</strong><br>';
            
            if (isset($wp_filter[$ajax_action])) {
                $handlers = $wp_filter[$ajax_action]->callbacks;
                $handler_count = 0;
                
                foreach ($handlers as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        $handler_count++;
                        $callback_info = $this->get_callback_info($callback['function']);
                        
                        $color = $handler_count > 1 ? 'red' : 'green';
                        echo "<span style='color: $color; font-size: 11px;'>$callback_info</span><br>";
                    }
                }
                
                if ($handler_count > 1) {
                    $total_conflicts++;
                    echo "<span style='color: red; font-weight: bold; font-size: 10px;'>❌ CONFLITTO! ($handler_count handler)</span>";
                } else {
                    echo "<span style='color: green; font-weight: bold; font-size: 10px;'>✅ OK (1 handler)</span>";
                }
                
            } else {
                echo "<span style='color: orange; font-size: 10px;'>⚠️ Nessun handler</span>";
            }
            
            echo '</div>';
        }
        
        // Stato generale
        echo '<hr style="margin: 10px 0;">';
        if ($total_conflicts > 0) {
            echo "<div style='color: red; font-weight: bold;'>❌ TROVATI $total_conflicts CONFLITTI!</div>";
            echo "<div style='font-size: 10px; color: #666;'>Sostituisci i file corretti del plugin</div>";
        } else {
            echo "<div style='color: green; font-weight: bold;'>✅ TUTTO OK! Nessun conflitto</div>";
            echo "<div style='font-size: 10px; color: #666;'>Puoi testare il salvataggio preventivi</div>";
        }
        
        // Pulsante per chiudere
        echo '<div style="text-align: right; margin-top: 10px;">';
        echo '<button onclick="document.getElementById(\'disco747-debug-hooks\').style.display=\'none\'" style="
            background: #0073aa; 
            color: white; 
            border: none; 
            padding: 5px 10px; 
            border-radius: 3px; 
            cursor: pointer;
            font-size: 11px;
        ">Nascondi</button>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Ottieni informazioni sul callback
     */
    private function get_callback_info($callback) {
        if (is_array($callback)) {
            if (is_object($callback[0])) {
                $class = get_class($callback[0]);
                // Abbrevia nomi classe lunghi
                if (strpos($class, 'Disco747_CRM') !== false) {
                    $class = str_replace('Disco747_CRM\\', '', $class);
                    $class = str_replace('\\', '\\', $class);
                }
                return $class . '::' . $callback[1];
            } else {
                return $callback[0] . '::' . $callback[1];
            }
        } elseif (is_string($callback)) {
            return $callback;
        } else {
            return 'Callback sconosciuto';
        }
    }
}

// Inizializza debug solo se siamo in admin
if (is_admin()) {
    new Disco747_Debug_Hooks();
}