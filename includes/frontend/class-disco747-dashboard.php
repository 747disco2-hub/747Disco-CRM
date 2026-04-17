<?php
/**
 * Gestione dashboard frontend del plugin 747 Disco CRM
 *
 * @package Disco747_CRM
 * @subpackage Frontend
 * @since 1.0.0
 */

namespace Disco747_CRM\Frontend;

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per la gestione della dashboard frontend
 *
 * @since 1.0.0
 */
class Disco747_Dashboard {

    /**
     * Istanza Auth
     *
     * @var Disco747_Auth
     * @since 1.0.0
     */
    private $auth;

    /**
     * Storage Manager
     *
     * @var Disco747_Storage_Manager
     * @since 1.0.0
     */
    private $storage_manager;

    /**
     * Filtri attivi
     *
     * @var array
     * @since 1.0.0
     */
    private $active_filters = array();

    /**
     * Ordinamento attivo
     *
     * @var array
     * @since 1.0.0
     */
    private $active_sorting = array(
        'orderby' => 'data_creazione',
        'order' => 'DESC'
    );

    /**
     * Items per pagina
     *
     * @var int
     * @since 1.0.0
     */
    private $items_per_page = 20;

    /**
     * Costruttore
     *
     * @since 1.0.0
     */
    public function __construct() {
        try {
            $this->init_components();
            $this->init_filters_and_sorting();
            
            $this->log('Disco747_Dashboard inizializzato correttamente');
        } catch (Exception $e) {
            $this->log('Errore inizializzazione Disco747_Dashboard: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Inizializza componenti dipendenti
     *
     * @since 1.0.0
     * @throws Exception Se componenti mancanti
     */
    private function init_components() {
        global $disco747_crm;

        if (!$disco747_crm) {
            throw new Exception('Plugin principale non disponibile');
        }

        $this->auth = $disco747_crm->get_auth();
        if (!$this->auth) {
            throw new Exception('Componente Auth non disponibile');
        }

        $this->storage_manager = $disco747_crm->get_storage_manager();
        // Storage manager è opzionale
    }

    /**
     * Inizializza filtri e ordinamento da parametri URL
     *
     * @since 1.0.0
     */
    private function init_filters_and_sorting() {
        // Filtri
        $this->active_filters = array(
            'search' => sanitize_text_field($_GET['search'] ?? ''),
            'stato' => sanitize_text_field($_GET['stato'] ?? ''),
            'tipo_menu' => sanitize_text_field($_GET['tipo_menu'] ?? ''),
            'data_da' => sanitize_text_field($_GET['data_da'] ?? ''),
            'data_a' => sanitize_text_field($_GET['data_a'] ?? ''),
            'created_by' => sanitize_text_field($_GET['created_by'] ?? ''),
            'importo_min' => floatval($_GET['importo_min'] ?? 0),
            'importo_max' => floatval($_GET['importo_max'] ?? 0)
        );

        // Rimuovi filtri vuoti
        $this->active_filters = array_filter($this->active_filters, function($value) {
            return !empty($value);
        });

        // Ordinamento
        $valid_orderby = array('data_creazione', 'data_evento', 'importo_preventivo', 'stato', 'created_by');
        $orderby = sanitize_text_field($_GET['orderby'] ?? 'data_creazione');
        $order = strtoupper(sanitize_text_field($_GET['order'] ?? 'DESC'));

        if (in_array($orderby, $valid_orderby)) {
            $this->active_sorting['orderby'] = $orderby;
        }

        if (in_array($order, array('ASC', 'DESC'))) {
            $this->active_sorting['order'] = $order;
        }

        // Paginazione
        $this->items_per_page = intval($_GET['per_page'] ?? 20);
        if ($this->items_per_page > 100) {
            $this->items_per_page = 100; // Limite massimo
        }
    }

    /**
     * Renderizza la dashboard completa
     *
     * @since 1.0.0
     * @param array $atts Attributi shortcode
     */
    public function render_dashboard($atts = array()) {
        if (!$this->auth->is_user_logged_in()) {
            return $this->render_access_denied();
        }

        $current_user = $this->auth->get_current_user();
        $view_mode = $atts['view'] ?? 'full';

        echo '<div class="disco747-dashboard-container">';
        
        // Header dashboard
        $this->render_dashboard_header($current_user);
        
        // Messaggi di notifica
        $this->render_notifications();
        
        if ($view_mode === 'full') {
            // Statistiche
            if (($atts['show_stats'] ?? 'true') === 'true') {
                $this->render_statistics_section();
            }
            
            // Filtri
            $this->render_filters_section();
        }
        
        // Tabella preventivi
        $this->render_preventivi_table($atts);
        
        // Azioni bulk
        if ($view_mode === 'full') {
            $this->render_bulk_actions();
        }
        
        echo '</div>';
    }

    /**
     * Renderizza header della dashboard
     *
     * @since 1.0.0
     * @param string $current_user Utente corrente
     */
    private function render_dashboard_header($current_user) {
        $storage_type = get_option('disco747_storage_type', 'dropbox');
        $storage_label = ($storage_type === 'googledrive') ? 'Google Drive' : 'Dropbox';
        $company_name = get_option('disco747_company_name', '747 Disco');
        
        echo '<div class="disco747-dashboard-header">';
        echo '<div class="disco747-header-content">';
        
        // Logo e titolo
        echo '<div class="disco747-header-brand">';
        echo '<div class="disco747-logo">';
        echo '<img src="https://gestionale.747disco.it/wp-content/uploads/2025/06/images.png" alt="' . esc_attr($company_name) . '" />';
        echo '</div>';
        echo '<div class="disco747-header-text">';
        echo '<h1>' . esc_html($company_name) . ' CRM</h1>';
        echo '<p>' . __('Dashboard Preventivi', 'disco747') . '</p>';
        echo '</div>';
        echo '</div>';
        
        // Info utente e azioni
        echo '<div class="disco747-header-actions">';
        echo '<div class="disco747-user-info">';
        echo '<span class="disco747-user-welcome">' . sprintf(__('Ciao, %s!', 'disco747'), esc_html($current_user)) . '</span>';
        echo '<span class="disco747-storage-info">' . $storage_label . ' 📁</span>';
        echo '</div>';
        
        echo '<div class="disco747-header-buttons">';
        echo '<a href="' . $this->get_new_preventivo_url() . '" class="disco747-btn disco747-btn-primary">';
        echo '<span class="disco747-btn-icon">➕</span> ' . __('Nuovo Preventivo', 'disco747');
        echo '</a>';
        echo '<a href="' . $this->get_logout_url() . '" class="disco747-btn disco747-btn-secondary" onclick="return confirm(\'' . __('Sei sicuro di voler uscire?', 'disco747') . '\')">';
        echo '<span class="disco747-btn-icon">🚪</span> ' . __('Logout', 'disco747');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderizza sezione notifiche
     *
     * @since 1.0.0
     */
    private function render_notifications() {
        $messages = $this->get_url_messages();
        
        if (empty($messages)) {
            return;
        }

        echo '<div class="disco747-notifications">';
        foreach ($messages as $message) {
            printf(
                '<div class="disco747-notification disco747-%s">%s</div>',
                esc_attr($message['type']),
                esc_html($message['text'])
            );
        }
        echo '</div>';
    }

    /**
     * Renderizza sezione statistiche
     *
     * @since 1.0.0
     */
    private function render_statistics_section() {
        $stats = $this->get_dashboard_statistics();
        
        echo '<div class="disco747-stats-section">';
        echo '<h2>' . __('Panoramica', 'disco747') . '</h2>';
        echo '<div class="disco747-stats-grid">';
        
        // Statistiche principali
        $main_stats = array(
            array(
                'label' => __('Totale Preventivi', 'disco747'),
                'value' => $stats['total'],
                'icon' => '📊',
                'class' => 'total'
            ),
            array(
                'label' => __('Questo Mese', 'disco747'),
                'value' => $stats['this_month'],
                'icon' => '📅',
                'class' => 'month'
            ),
            array(
                'label' => __('Confermati', 'disco747'),
                'value' => $stats['confirmed'],
                'icon' => '✅',
                'class' => 'confirmed'
            ),
            array(
                'label' => __('Valore Totale', 'disco747'),
                'value' => '€' . number_format($stats['total_value'], 0, ',', '.'),
                'icon' => '💰',
                'class' => 'value'
            )
        );

        foreach ($main_stats as $stat) {
            echo '<div class="disco747-stat-card disco747-stat-' . $stat['class'] . '">';
            echo '<div class="disco747-stat-icon">' . $stat['icon'] . '</div>';
            echo '<div class="disco747-stat-content">';
            echo '<div class="disco747-stat-value">' . esc_html($stat['value']) . '</div>';
            echo '<div class="disco747-stat-label">' . esc_html($stat['label']) . '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderizza sezione filtri
     *
     * @since 1.0.0
     */
    private function render_filters_section() {
        echo '<div class="disco747-filters-section">';
        echo '<h3>' . __('Filtri', 'disco747') . '</h3>';
        echo '<form method="get" class="disco747-filters-form">';
        
        echo '<div class="disco747-filters-grid">';
        
        // Campo ricerca
        echo '<div class="disco747-filter-field">';
        echo '<label for="search">' . __('Cerca', 'disco747') . '</label>';
        echo '<input type="text" id="search" name="search" value="' . esc_attr($this->active_filters['search'] ?? '') . '" placeholder="' . __('Nome cliente, email...', 'disco747') . '">';
        echo '</div>';
        
        // Filtro stato
        echo '<div class="disco747-filter-field">';
        echo '<label for="stato">' . __('Stato', 'disco747') . '</label>';
        echo '<select id="stato" name="stato">';
        echo '<option value="">' . __('Tutti gli stati', 'disco747') . '</option>';
        $stati = array('Attivo', 'Confermato', 'Annullato', 'Rifiutato', 'Bozza');
        foreach ($stati as $stato) {
            $selected = ($this->active_filters['stato'] ?? '') === $stato ? ' selected' : '';
            echo '<option value="' . esc_attr($stato) . '"' . $selected . '>' . esc_html($stato) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Filtro tipo menu
        echo '<div class="disco747-filter-field">';
        echo '<label for="tipo_menu">' . __('Tipo Menu', 'disco747') . '</label>';
        echo '<select id="tipo_menu" name="tipo_menu">';
        echo '<option value="">' . __('Tutti i menu', 'disco747') . '</option>';
        $tipi_menu = $this->get_menu_types();
        foreach ($tipi_menu as $tipo) {
            $selected = ($this->active_filters['tipo_menu'] ?? '') === $tipo ? ' selected' : '';
            echo '<option value="' . esc_attr($tipo) . '"' . $selected . '>' . esc_html($tipo) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Data da
        echo '<div class="disco747-filter-field">';
        echo '<label for="data_da">' . __('Data da', 'disco747') . '</label>';
        echo '<input type="date" id="data_da" name="data_da" value="' . esc_attr($this->active_filters['data_da'] ?? '') . '">';
        echo '</div>';
        
        // Data a
        echo '<div class="disco747-filter-field">';
        echo '<label for="data_a">' . __('Data a', 'disco747') . '</label>';
        echo '<input type="date" id="data_a" name="data_a" value="' . esc_attr($this->active_filters['data_a'] ?? '') . '">';
        echo '</div>';
        
        echo '</div>';
        
        // Pulsanti azione
        echo '<div class="disco747-filters-actions">';
        echo '<button type="submit" class="disco747-btn disco747-btn-primary">' . __('Applica Filtri', 'disco747') . '</button>';
        echo '<a href="' . $this->get_base_url() . '" class="disco747-btn disco747-btn-secondary">' . __('Reset', 'disco747') . '</a>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
    }

    /**
     * Renderizza tabella preventivi
     *
     * @since 1.0.0
     * @param array $atts Attributi
     */
    private function render_preventivi_table($atts) {
        $preventivi = $this->get_preventivi_data();
        $pagination = $this->get_pagination_data($preventivi['total']);
        
        echo '<div class="disco747-table-section">';
        echo '<h3>' . __('Preventivi', 'disco747') . '</h3>';
        
        if (empty($preventivi['data'])) {
            $this->render_empty_state();
            echo '</div>';
            return;
        }
        
        // Header tabella con ordinamento
        echo '<div class="disco747-table-wrapper">';
        echo '<table class="disco747-table">';
        echo '<thead>';
        echo '<tr>';
        
        $columns = array(
            'nome_cliente' => __('Cliente', 'disco747'),
            'data_evento' => __('Data Evento', 'disco747'),
            'tipo_menu' => __('Menu', 'disco747'),
            'importo_preventivo' => __('Importo', 'disco747'),
            'stato' => __('Stato', 'disco747'),
            'created_by' => __('Creato da', 'disco747'),
            'data_creazione' => __('Creato il', 'disco747')
        );
        
        foreach ($columns as $column_key => $column_label) {
            echo '<th class="disco747-col-' . $column_key . '">';
            if (in_array($column_key, array('data_creazione', 'data_evento', 'importo_preventivo', 'stato'))) {
                echo '<a href="' . $this->get_sort_url($column_key) . '" class="' . $this->get_sort_class($column_key) . '">';
                echo esc_html($column_label);
                if ($this->active_sorting['orderby'] === $column_key) {
                    echo ' <span class="disco747-sort-indicator">' . ($this->active_sorting['order'] === 'ASC' ? '↑' : '↓') . '</span>';
                }
                echo '</a>';
            } else {
                echo esc_html($column_label);
            }
            echo '</th>';
        }
        
        echo '<th class="disco747-col-actions">' . __('Azioni', 'disco747') . '</th>';
        echo '</tr>';
        echo '</thead>';
        
        // Corpo tabella
        echo '<tbody>';
        foreach ($preventivi['data'] as $preventivo) {
            $this->render_preventivo_row($preventivo);
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        
        // Paginazione
        if ($pagination['total_pages'] > 1) {
            $this->render_pagination($pagination);
        }
        
        echo '</div>';
    }

    /**
     * Renderizza riga preventivo
     *
     * @since 1.0.0
     * @param object $preventivo Dati preventivo
     */
    private function render_preventivo_row($preventivo) {
        echo '<tr class="disco747-preventivo-row" data-id="' . intval($preventivo->id) . '">';
        
        // Cliente
        echo '<td class="disco747-col-nome-cliente">';
        echo '<div class="disco747-client-info">';
        echo '<strong>' . esc_html($preventivo->nome_cliente) . '</strong>';
        if (!empty($preventivo->email_cliente)) {
            echo '<br><small>' . esc_html($preventivo->email_cliente) . '</small>';
        }
        echo '</div>';
        echo '</td>';
        
        // Data evento
        echo '<td class="disco747-col-data-evento">';
        if (!empty($preventivo->data_evento)) {
            echo '<span class="disco747-event-date">' . $this->format_italian_date($preventivo->data_evento) . '</span>';
        } else {
            echo '<span class="disco747-no-date">-</span>';
        }
        echo '</td>';
        
        // Tipo menu
        echo '<td class="disco747-col-tipo-menu">';
        echo '<span class="disco747-menu-type">' . esc_html($preventivo->tipo_menu) . '</span>';
        echo '</td>';
        
        // Importo
        echo '<td class="disco747-col-importo">';
        echo '<span class="disco747-amount">€' . number_format($preventivo->importo_preventivo, 2, ',', '.') . '</span>';
        if (!empty($preventivo->acconto) && $preventivo->acconto > 0) {
            echo '<span class="disco747-deposit">Acc: €' . number_format($preventivo->acconto, 2, ',', '.') . '</span>';
        }
        echo '</td>';
        
        // Stato
        echo '<td class="disco747-col-stato">';
        echo $this->render_status_badge($preventivo->stato, $preventivo->confermato);
        echo '</td>';
        
        // Creato da
        echo '<td class="disco747-col-created-by">';
        echo '<span class="disco747-created-by">' . esc_html($preventivo->created_by ?: 'N/D') . '</span>';
        echo '</td>';
        
        // Data creazione
        echo '<td class="disco747-col-data-creazione">';
        echo '<span class="disco747-created-date">' . human_time_diff(strtotime($preventivo->data_creazione)) . ' fa</span>';
        echo '</td>';
        
        // Azioni
        echo '<td class="disco747-col-actions">';
        $this->render_row_actions($preventivo);
        echo '</td>';
        
        echo '</tr>';
    }

    /**
     * Renderizza badge stato
     *
     * @since 1.0.0
     * @param string $stato Stato preventivo
     * @param string $confermato Flag confermato
     * @return string HTML badge
     */
    private function render_status_badge($stato, $confermato = '') {
        $status_classes = array(
            'Attivo' => 'disco747-status-active',
            'Confermato' => 'disco747-status-confirmed',
            'Annullato' => 'disco747-status-cancelled',
            'Rifiutato' => 'disco747-status-rejected',
            'Bozza' => 'disco747-status-draft'
        );
        
        $status_icons = array(
            'Attivo' => '⏳',
            'Confermato' => '✅',
            'Annullato' => '❌',
            'Rifiutato' => '👎',
            'Bozza' => '📝'
        );
        
        $class = $status_classes[$stato] ?? 'disco747-status-unknown';
        $icon = $status_icons[$stato] ?? '❓';
        
        $output = '<span class="disco747-status-badge ' . $class . '">';
        $output .= '<span class="disco747-status-icon">' . $icon . '</span>';
        $output .= '<span class="disco747-status-text">' . esc_html($stato) . '</span>';
        $output .= '</span>';
        
        return $output;
    }

    /**
     * Renderizza azioni riga
     *
     * @since 1.0.0
     * @param object $preventivo Dati preventivo
     */
    private function render_row_actions($preventivo) {
        $actions = array();
        
        // Visualizza
        $actions['view'] = sprintf(
            '<a href="%s" class="disco747-action disco747-action-view" title="%s">👁️</a>',
            $this->get_view_url($preventivo->id),
            __('Visualizza', 'disco747')
        );
        
        // Modifica
        $actions['edit'] = sprintf(
            '<a href="%s" class="disco747-action disco747-action-edit" title="%s">✏️</a>',
            $this->get_edit_url($preventivo->id),
            __('Modifica', 'disco747')
        );
        
        // Duplica
        $actions['duplicate'] = sprintf(
            '<a href="%s" class="disco747-action disco747-action-duplicate" title="%s">📄</a>',
            $this->get_duplicate_url($preventivo->id),
            __('Duplica', 'disco747')
        );
        
        // PDF
        if (!empty($preventivo->pdf_path)) {
            $actions['pdf'] = sprintf(
                '<a href="%s" class="disco747-action disco747-action-pdf" title="%s" target="_blank">📄</a>',
                esc_url($preventivo->pdf_path),
                __('Scarica PDF', 'disco747')
            );
        }
        
        // Excel
        if (!empty($preventivo->excel_path)) {
            $actions['excel'] = sprintf(
                '<a href="%s" class="disco747-action disco747-action-excel" title="%s" target="_blank">📊</a>',
                esc_url($preventivo->excel_path),
                __('Scarica Excel', 'disco747')
            );
        }
        
        // Elimina
        $actions['delete'] = sprintf(
            '<a href="%s" class="disco747-action disco747-action-delete" title="%s" onclick="return confirm(\'%s\')">🗑️</a>',
            $this->get_delete_url($preventivo->id),
            __('Elimina', 'disco747'),
            __('Sei sicuro di voler eliminare questo preventivo?', 'disco747')
        );
        
        echo '<div class="disco747-actions">';
        echo implode('', $actions);
        echo '</div>';
    }

    /**
     * Renderizza stato vuoto
     *
     * @since 1.0.0
     */
    private function render_empty_state() {
        echo '<div class="disco747-empty-state">';
        echo '<div class="disco747-empty-icon">📝</div>';
        echo '<h3>' . __('Nessun preventivo trovato', 'disco747') . '</h3>';
        echo '<p>' . __('Non hai ancora creato nessun preventivo o i filtri applicati non hanno restituito risultati.', 'disco747') . '</p>';
        echo '<a href="' . $this->get_new_preventivo_url() . '" class="disco747-btn disco747-btn-primary">';
        echo __('Crea il tuo primo preventivo', 'disco747');
        echo '</a>';
        echo '</div>';
    }

    /**
     * Renderizza paginazione
     *
     * @since 1.0.0
     * @param array $pagination Dati paginazione
     */
    private function render_pagination($pagination) {
        echo '<div class="disco747-pagination">';
        
        // Info paginazione
        echo '<div class="disco747-pagination-info">';
        printf(
            __('Mostrando %d-%d di %d risultati', 'disco747'),
            $pagination['from'],
            $pagination['to'],
            $pagination['total']
        );
        echo '</div>';
        
        // Collegamenti paginazione
        echo '<div class="disco747-pagination-links">';
        
        // Prima pagina
        if ($pagination['current_page'] > 1) {
            echo '<a href="' . $this->get_page_url(1) . '" class="disco747-page-link">« ' . __('Prima', 'disco747') . '</a>';
        }
        
        // Pagina precedente
        if ($pagination['current_page'] > 1) {
            echo '<a href="' . $this->get_page_url($pagination['current_page'] - 1) . '" class="disco747-page-link">‹ ' . __('Precedente', 'disco747') . '</a>';
        }
        
        // Numeri pagine
        $start = max(1, $pagination['current_page'] - 2);
        $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            if ($i === $pagination['current_page']) {
                echo '<span class="disco747-page-link disco747-current-page">' . $i . '</span>';
            } else {
                echo '<a href="' . $this->get_page_url($i) . '" class="disco747-page-link">' . $i . '</a>';
            }
        }
        
        // Pagina successiva
        if ($pagination['current_page'] < $pagination['total_pages']) {
            echo '<a href="' . $this->get_page_url($pagination['current_page'] + 1) . '" class="disco747-page-link">' . __('Successiva', 'disco747') . ' ›</a>';
        }
        
        // Ultima pagina
        if ($pagination['current_page'] < $pagination['total_pages']) {
            echo '<a href="' . $this->get_page_url($pagination['total_pages']) . '" class="disco747-page-link">' . __('Ultima', 'disco747') . ' »</a>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderizza azioni bulk
     *
     * @since 1.0.0
     */
    private function render_bulk_actions() {
        echo '<div class="disco747-bulk-actions-section">';
        echo '<h3>' . __('Azioni Bulk', 'disco747') . '</h3>';
        echo '<div class="disco747-bulk-actions-form">';
        
        echo '<select id="disco747-bulk-action">';
        echo '<option value="">' . __('Seleziona azione...', 'disco747') . '</option>';
        echo '<option value="export">' . __('Esporta in CSV', 'disco747') . '</option>';
        echo '<option value="change_status">' . __('Cambia stato', 'disco747') . '</option>';
        echo '<option value="delete">' . __('Elimina selezionati', 'disco747') . '</option>';
        echo '</select>';
        
        echo '<button type="button" id="disco747-apply-bulk" class="disco747-btn disco747-btn-secondary">';
        echo __('Applica', 'disco747');
        echo '</button>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderizza messaggio accesso negato
     *
     * @since 1.0.0
     * @return string HTML messaggio
     */
    private function render_access_denied() {
        return '<div class="disco747-access-denied">' .
               '<h3>' . __('Accesso Negato', 'disco747') . '</h3>' .
               '<p>' . __('Devi effettuare il login per accedere alla dashboard.', 'disco747') . '</p>' .
               '<a href="' . home_url('/disco747-login/') . '" class="disco747-btn disco747-btn-primary">' .
               __('Effettua il Login', 'disco747') . '</a>' .
               '</div>';
    }

    // ============================================================================
    // METODI DATI
    // ============================================================================

    /**
     * Ottiene dati preventivi con filtri e paginazione
     *
     * @since 1.0.0
     * @return array Dati preventivi e totale
     */
    private function get_preventivi_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'disco747_preventivi';
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $offset = ($current_page - 1) * $this->items_per_page;
        
        // Costruisci WHERE clause
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($this->active_filters['search'])) {
            $where_conditions[] = '(nome_cliente LIKE %s OR email_cliente LIKE %s OR telefono_cliente LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($this->active_filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($this->active_filters['stato'])) {
            $where_conditions[] = 'stato = %s';
            $where_values[] = $this->active_filters['stato'];
        }
        
        if (!empty($this->active_filters['tipo_menu'])) {
            $where_conditions[] = 'tipo_menu = %s';
            $where_values[] = $this->active_filters['tipo_menu'];
        }
        
        if (!empty($this->active_filters['data_da'])) {
            $where_conditions[] = 'data_evento >= %s';
            $where_values[] = $this->active_filters['data_da'];
        }
        
        if (!empty($this->active_filters['data_a'])) {
            $where_conditions[] = 'data_evento <= %s';
            $where_values[] = $this->active_filters['data_a'];
        }
        
        if (!empty($this->active_filters['created_by'])) {
            $where_conditions[] = 'created_by = %s';
            $where_values[] = $this->active_filters['created_by'];
        }
        
        if (!empty($this->active_filters['importo_min'])) {
            $where_conditions[] = 'importo_preventivo >= %f';
            $where_values[] = $this->active_filters['importo_min'];
        }
        
        if (!empty($this->active_filters['importo_max'])) {
            $where_conditions[] = 'importo_preventivo <= %f';
            $where_values[] = $this->active_filters['importo_max'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Conta totale risultati
        $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total_items = $wpdb->get_var($count_query);
        
        // Query principale con ordinamento e paginazione
        $order_clause = sprintf(
            'ORDER BY %s %s',
            esc_sql($this->active_sorting['orderby']),
            esc_sql($this->active_sorting['order'])
        );
        
        $main_query = "SELECT * FROM $table_name WHERE $where_clause $order_clause LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($this->items_per_page, $offset));
        
        $results = $wpdb->get_results($wpdb->prepare($main_query, $query_values));
        
        return array(
            'data' => $results ?: array(),
            'total' => intval($total_items)
        );
    }

    /**
     * Ottiene statistiche dashboard
     *
     * @since 1.0.0
     * @return array Statistiche
     */
    private function get_dashboard_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'disco747_preventivi';
        $current_month = date('Y-m');
        
        // Statistiche base
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE DATE_FORMAT(data_creazione, '%%Y-%%m') = %s",
            $current_month
        ));
        $confirmed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE stato = 'Confermato'");
        $total_value = $wpdb->get_var("SELECT SUM(importo_preventivo) FROM $table_name WHERE stato != 'Annullato'");
        
        return array(
            'total' => intval($total),
            'this_month' => intval($this_month),
            'confirmed' => intval($confirmed),
            'total_value' => floatval($total_value)
        );
    }

    /**
     * Ottiene conteggi per stato
     *
     * @since 1.0.0
     * @return array Conteggi stati
     */
    private function get_status_counts() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'disco747_preventivi';
        $results = $wpdb->get_results(
            "SELECT stato, COUNT(*) as count FROM $table_name GROUP BY stato"
        );
        
        $counts = array();
        foreach ($results as $result) {
            $counts[$result->stato] = intval($result->count);
        }
        
        return $counts;
    }

    /**
     * Ottiene tipi menu disponibili
     *
     * @since 1.0.0
     * @return array Tipi menu
     */
    private function get_menu_types() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'disco747_preventivi';
        $results = $wpdb->get_col(
            "SELECT DISTINCT tipo_menu FROM $table_name WHERE tipo_menu IS NOT NULL AND tipo_menu != '' ORDER BY tipo_menu"
        );
        
        return $results ?: array();
    }

    /**
     * Ottiene dati paginazione
     *
     * @since 1.0.0
     * @param int $total_items Totale elementi
     * @return array Dati paginazione
     */
    private function get_pagination_data($total_items) {
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $total_pages = ceil($total_items / $this->items_per_page);
        
        $from = (($current_page - 1) * $this->items_per_page) + 1;
        $to = min($current_page * $this->items_per_page, $total_items);
        
        return array(
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'total' => $total_items,
            'from' => $from,
            'to' => $to,
            'per_page' => $this->items_per_page
        );
    }

    /**
     * Ottiene messaggi da URL
     *
     * @since 1.0.0
     * @return array Messaggi
     */
    private function get_url_messages() {
        $messages = array();
        
        // Messaggi di successo
        if (isset($_GET['message'])) {
            $message_type = sanitize_text_field($_GET['message']);
            $success_messages = array(
                'preventivo_saved' => __('Preventivo salvato con successo.', 'disco747'),
                'preventivo_updated' => __('Preventivo aggiornato con successo.', 'disco747'),
                'deleted' => __('Preventivo eliminato con successo.', 'disco747'),
                'duplicated' => __('Preventivo duplicato con successo.', 'disco747'),
                'exported' => __('Export completato con successo.', 'disco747'),
                'status_changed' => __('Stato modificato con successo.', 'disco747')
            );
            
            if (isset($success_messages[$message_type])) {
                $messages[] = array('type' => 'success', 'text' => $success_messages[$message_type]);
            }
        }
        
        // Messaggi di errore
        if (isset($_GET['error'])) {
            $error_type = sanitize_text_field($_GET['error']);
            $error_messages = array(
                'preventivo_not_found' => __('Preventivo non trovato.', 'disco747'),
                'delete_failed' => __('Errore durante l\'eliminazione del preventivo.', 'disco747'),
                'save_failed' => __('Errore durante il salvataggio del preventivo.', 'disco747'),
                'export_failed' => __('Errore durante l\'export.', 'disco747'),
                'permission_denied' => __('Permessi insufficienti per questa operazione.', 'disco747'),
                'invalid_data' => __('Dati non validi forniti.', 'disco747'),
                'cancel_failed' => __('Errore durante l\'annullamento del preventivo.', 'disco747'),
                'duplicate_failed' => __('Errore durante la duplicazione del preventivo.', 'disco747'),
                'access_denied' => __('Accesso negato a questa operazione.', 'disco747')
            );
            
            if (isset($error_messages[$error_type])) {
                $messages[] = array('type' => 'error', 'text' => $error_messages[$error_type]);
            }
        }
        
        return $messages;
    }

    // ============================================================================
    // METODI URL
    // ============================================================================

    /**
     * Ottiene URL per nuovo preventivo
     *
     * @since 1.0.0
     * @return string URL nuovo preventivo
     */
    private function get_new_preventivo_url() {
        return home_url('/disco747-preventivi/');
    }

    /**
     * Ottiene URL logout
     *
     * @since 1.0.0
     * @return string URL logout
     */
    private function get_logout_url() {
        return home_url('/disco747-logout/');
    }

    /**
     * Ottiene URL visualizzazione preventivo
     *
     * @since 1.0.0
     * @param int $id ID preventivo
     * @return string URL visualizzazione
     */
    private function get_view_url($id) {
        return home_url('/disco747-preventivi/view/' . $id . '/');
    }

    /**
     * Ottiene URL modifica preventivo
     *
     * @since 1.0.0
     * @param int $id ID preventivo
     * @return string URL modifica
     */
    private function get_edit_url($id) {
        return home_url('/disco747-preventivi/edit/' . $id . '/');
    }

    /**
     * Ottiene URL duplicazione preventivo
     *
     * @since 1.0.0
     * @param int $id ID preventivo
     * @return string URL duplicazione
     */
    private function get_duplicate_url($id) {
        return add_query_arg(array(
            'action' => 'duplicate',
            'id' => $id
        ), $this->get_base_url());
    }

    /**
     * Ottiene URL eliminazione preventivo
     *
     * @since 1.0.0
     * @param int $id ID preventivo
     * @return string URL eliminazione
     */
    private function get_delete_url($id) {
        return add_query_arg(array(
            'action' => 'delete',
            'id' => $id,
            'nonce' => wp_create_nonce('disco747_delete_' . $id)
        ), $this->get_base_url());
    }

    /**
     * Formatta data in italiano
     *
     * @since 1.0.0
     * @param string $date Data formato Y-m-d
     * @return string Data formattata
     */
    private function format_italian_date($date) {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return $date;
        }
        
        $giorni = array('Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab');
        $mesi = array(
            'Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu',
            'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'
        );
        
        $giorno_settimana = $giorni[date('w', $timestamp)];
        $giorno = date('j', $timestamp);
        $mese = $mesi[date('n', $timestamp) - 1];
        $anno = date('Y', $timestamp);
        
        return $giorno_settimana . ', ' . $giorno . ' ' . $mese . ' ' . $anno;
    }

    /**
     * Ottiene URL base dashboard
     *
     * @since 1.0.0
     * @return string URL base
     */
    private function get_base_url() {
        return home_url('/disco747-dashboard/');
    }

    /**
     * Ottiene URL per ordinamento colonna
     *
     * @since 1.0.0
     * @param string $column Colonna da ordinare
     * @return string URL ordinamento
     */
    private function get_sort_url($column) {
        $params = $_GET;
        
        if ($this->active_sorting['orderby'] === $column) {
            // Inverte ordine se stessa colonna
            $params['order'] = $this->active_sorting['order'] === 'ASC' ? 'DESC' : 'ASC';
        } else {
            // Nuova colonna, ordine predefinito
            $params['orderby'] = $column;
            $params['order'] = 'DESC';
        }
        
        return $this->get_base_url() . '?' . http_build_query($params);
    }

    /**
     * Ottiene classe CSS per ordinamento colonna
     *
     * @since 1.0.0
     * @param string $column Colonna
     * @return string Classe CSS
     */
    private function get_sort_class($column) {
        if ($this->active_sorting['orderby'] !== $column) {
            return 'disco747-sortable';
        }
        
        $direction = strtolower($this->active_sorting['order']);
        return 'disco747-sortable disco747-sorted disco747-sorted-' . $direction;
    }

    /**
     * Ottiene URL pagina
     *
     * @since 1.0.0
     * @param int $page Numero pagina
     * @return string URL pagina
     */
    private function get_page_url($page) {
        $params = $_GET;
        $params['paged'] = $page;
        
        return $this->get_base_url() . '?' . http_build_query($params);
    }

    // ============================================================================
    // METODI EXPORT E UTILITÀ
    // ============================================================================

    /**
     * Esporta preventivi in CSV
     *
     * @since 1.0.0
     * @param array $preventivi_ids IDs preventivi da esportare
     * @return string Percorso file CSV
     */
    public function export_to_csv($preventivi_ids = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'disco747_preventivi';
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "preventivi_export_{$timestamp}.csv";
        
        // Percorso temporaneo
        $upload_dir = wp_upload_dir();
        $csv_file = $upload_dir['path'] . '/' . $filename;
        
        // Query per i dati
        if ($preventivi_ids && is_array($preventivi_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($preventivi_ids), '%d'));
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id IN ($ids_placeholder) ORDER BY data_creazione DESC",
                $preventivi_ids
            );
        } else {
            $query = "SELECT * FROM $table_name ORDER BY data_creazione DESC";
        }
        
        $preventivi = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($preventivi)) {
            return false;
        }
        
        // Crea file CSV
        $fp = fopen($csv_file, 'w');
        
        // Aggiungi BOM per UTF-8
        fputs($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Header CSV
        $headers = array(
            'ID', 'Cliente', 'Email', 'Telefono', 'Data Evento',
            'Tipo Menu', 'Numero Persone', 'Importo', 'Acconto',
            'Stato', 'Data Creazione', 'Creato da'
        );
        fputcsv($fp, $headers, ';');
        
        // Dati
        foreach ($preventivi as $preventivo) {
            $row = array(
                $preventivo['id'],
                $preventivo['nome_cliente'],
                $preventivo['email_cliente'],
                $preventivo['telefono_cliente'],
                $preventivo['data_evento'],
                $preventivo['tipo_menu'],
                $preventivo['numero_persone'],
                number_format($preventivo['importo_preventivo'], 2, ',', '.'),
                number_format($preventivo['acconto'] ?: 0, 2, ',', '.'),
                $preventivo['stato'],
                $preventivo['data_creazione'],
                $preventivo['created_by'] ?: 'N/D'
            );
            
            fputcsv($fp, $row, ';');
        }
        
        fclose($fp);
        
        $this->log('Export CSV generato: ' . basename($csv_file));
        
        return $csv_file;
    }

    /**
     * Ottiene riepilogo dashboard per widget
     *
     * @since 1.0.0
     * @return array Riepilogo dashboard
     */
    public function get_dashboard_summary() {
        $stats = $this->get_dashboard_statistics();
        $status_counts = $this->get_status_counts();
        
        return array(
            'basic_stats' => $stats,
            'status_counts' => $status_counts,
            'has_data' => $stats['total'] > 0,
            'last_updated' => current_time('mysql')
        );
    }

    /**
     * Verifica se l'utente può accedere alla dashboard
     *
     * @since 1.0.0
     * @return bool True se può accedere
     */
    public function can_access_dashboard() {
        return $this->auth->is_user_logged_in();
    }

    /**
     * Logging per debug
     *
     * @since 1.0.0
     * @param string $message Messaggio da loggare
     * @param string $level Livello di log
     */
    private function log($message, $level = 'info') {
        if (get_option('disco747_debug_mode', false)) {
            $timestamp = current_time('Y-m-d H:i:s');
            $log_entry = sprintf('[%s] [DASHBOARD] [%s] %s', $timestamp, strtoupper($level), $message);
            error_log('DISCO747_CRM: ' . $log_entry);
        }
    }

    // ============================================================================
    // METODI PUBBLICI PER INTEGRAZIONE ESTERNA
    // ============================================================================

    /**
     * Ottiene istanza Auth
     *
     * @since 1.0.0
     * @return Disco747_Auth Istanza Auth
     */
    public function get_auth() {
        return $this->auth;
    }

    /**
     * Ottiene istanza Storage Manager
     *
     * @since 1.0.0
     * @return Disco747_Storage_Manager|null Istanza Storage Manager
     */
    public function get_storage_manager() {
        return $this->storage_manager;
    }

    /**
     * Ottiene filtri attivi
     *
     * @since 1.0.0
     * @return array Filtri attivi
     */
    public function get_active_filters() {
        return $this->active_filters;
    }

    /**
     * Ottiene ordinamento attivo
     *
     * @since 1.0.0
     * @return array Ordinamento attivo
     */
    public function get_active_sorting() {
        return $this->active_sorting;
    }

    /**
     * Imposta numero di elementi per pagina
     *
     * @since 1.0.0
     * @param int $items_per_page Numero elementi per pagina
     */
    public function set_items_per_page($items_per_page) {
        $this->items_per_page = max(1, min(100, intval($items_per_page)));
    }
}