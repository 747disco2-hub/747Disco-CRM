/**
 * CHANGELOG v11.8.1:
 * - ✅ Aggiunto supporto completo per cambio stato preventivi
 * - ✅ Gestione corretta nonce per update_preventivo_status
 * - ✅ Reload automatico pagina dopo cambio stato
 * - ✅ Notifiche migliorate con messaggi dettagliati
 * 
 * JavaScript per l'area admin di 747 Disco CRM
 * Versione corretta con supporto cambio stato
 * 
 * @package    Disco747_CRM
 * @subpackage Assets
 * @since      11.8.1
 */

(function($) {
    'use strict';

    // Oggetto principale per l'admin
    var Disco747Admin = {
        
        // Configurazione
        config: {
            ajaxUrl: typeof disco747Admin !== 'undefined' ? disco747Admin.ajax_url : ajaxurl,
            nonce: typeof disco747Admin !== 'undefined' ? disco747Admin.nonce : '',
            messages: typeof disco747Admin !== 'undefined' ? disco747Admin.messages : {}
        },
        
        // Cache elementi DOM
        cache: {
            $document: $(document),
            $window: $(window),
            $body: $('body')
        },
        
        // Stato applicazione
        state: {
            preventivi: [],
            filteredPreventivi: [],
            currentPage: 1,
            itemsPerPage: 20,
            isLoading: false,
            isSyncing: false
        },
        
        /**
         * Inizializzazione
         */
        init: function() {
            console.log('Inizializzazione 747 Disco Admin...');
            this.bindEvents();
            this.initComponents();
            this.loadInitialData();
            console.log('747 Disco Admin inizializzato correttamente');
        },
        
        /**
         * Bind eventi globali
         */
        bindEvents: function() {
            var self = this;
            
            // Preventivi management
            self.cache.$document.on('click', '.view-preventivo', self.handleViewPreventivo.bind(self));
            self.cache.$document.on('click', '.edit-preventivo', self.handleEditPreventivo.bind(self));
            self.cache.$document.on('click', '.cancel-preventivo', self.handleCancelPreventivo.bind(self));
            self.cache.$document.on('click', '.delete-preventivo', self.handleDeletePreventivo.bind(self));
            
            // Cambio stato dropdown
            self.cache.$document.on('change', '.preventivo-status-select', self.handleStatusChange.bind(self));
            
            // Paginazione
            self.cache.$document.on('click', '.pagination-link', self.handlePagination.bind(self));
            
            // Storage e sync
            self.cache.$document.on('click', '.sync-storage-btn', self.handleSyncStorage.bind(self));
            self.cache.$document.on('click', '.test-storage-btn', self.handleTestStorage.bind(self));
            
            // Form validation
            self.cache.$document.on('submit', '.disco747-form', self.handleFormSubmit.bind(self));
            
            // Filtri
            self.cache.$document.on('change', '.preventivi-filter', self.handleFilterChange.bind(self));
            self.cache.$document.on('submit', '.filter-form', self.handleFilterSubmit.bind(self));
            
            // Notification dismissal
            self.cache.$document.on('click', '.disco747-dismiss', self.handleNotificationDismiss.bind(self));
            
            console.log('Eventi registrati correttamente');
        },
        
        /**
         * Inizializza componenti UI
         */
        initComponents: function() {
            // Select2 se disponibile
            if ($.fn.select2) {
                $('.disco747-select2').select2({
                    width: '100%'
                });
            }
            
            // Datepicker se disponibile
            if ($.fn.datepicker) {
                $('.disco747-datepicker').datepicker({
                    dateFormat: 'dd/mm/yy',
                    changeMonth: true,
                    changeYear: true
                });
            }
            
            // Tooltips
            this.initTooltips();
        },
        
        /**
         * Carica dati iniziali
         */
        loadInitialData: function() {
            // Se siamo nella dashboard preventivi, carica la lista
            if ($('#preventivi-table').length) {
                this.loadPreventivi();
            }
        },
        
        /**
         * Handler per visualizzazione preventivo
         */
        handleViewPreventivo: function(e) {
            e.preventDefault();
            var id = $(e.currentTarget).data('id');
            this.showPreventivoDetails(id);
        },
        
        /**
         * Handler per modifica preventivo
         */
        handleEditPreventivo: function(e) {
            e.preventDefault();
            var id = $(e.currentTarget).data('id');
            window.location.href = this.buildUrl('disco747-crm', {action: 'edit_preventivo', id: id});
        },
        
        /**
         * Handler per annullamento preventivo
         */
        handleCancelPreventivo: function(e) {
            e.preventDefault();
            var self = this;
            var id = $(e.currentTarget).data('id');
            
            if (confirm(self.config.messages.confirm_cancel || 'Sei sicuro di voler annullare questo preventivo?')) {
                self.updatePreventivoStatus(id, 'annullato');
            }
        },
        
        /**
         * Handler per eliminazione preventivo
         */
        handleDeletePreventivo: function(e) {
            e.preventDefault();
            var self = this;
            var id = $(e.currentTarget).data('id');
            
            if (confirm(self.config.messages.confirm_delete || 'Sei sicuro di voler eliminare questo preventivo?')) {
                self.deletePreventivo(id);
            }
        },
        
        /**
         * Handler per cambio stato da dropdown
         */
        handleStatusChange: function(e) {
            e.preventDefault();
            var self = this;
            var $select = $(e.currentTarget);
            var id = $select.data('preventivo-id');
            var newStatus = $select.val();
            var oldStatus = $select.data('old-status');
            
            if (newStatus !== oldStatus) {
                if (confirm('Confermi il cambio di stato del preventivo #' + id + '?')) {
                    self.updatePreventivoStatus(id, newStatus);
                } else {
                    // Ripristina valore precedente
                    $select.val(oldStatus);
                }
            }
        },
        
        /**
         * Handler paginazione
         */
        handlePagination: function(e) {
            e.preventDefault();
            var page = $(e.currentTarget).data('page');
            this.state.currentPage = page;
            this.renderPreventivi();
            this.scrollToTable();
        },
        
        /**
         * Handler sincronizzazione storage
         */
        handleSyncStorage: function(e) {
            e.preventDefault();
            this.syncStorage();
        },
        
        /**
         * Handler test storage
         */
        handleTestStorage: function(e) {
            e.preventDefault();
            this.testStorageConnection();
        },
        
        /**
         * Handler submit form
         */
        handleFormSubmit: function(e) {
            var $form = $(e.currentTarget);
            
            if (!this.validateForm($form)) {
                e.preventDefault();
                this.showNotification('error', 'Compila tutti i campi obbligatori');
                return false;
            }
        },
        
        /**
         * Handler cambio filtri
         */
        handleFilterChange: function(e) {
            this.applyFilters();
        },
        
        /**
         * Handler submit filtri
         */
        handleFilterSubmit: function(e) {
            e.preventDefault();
            this.applyFilters();
        },
        
        /**
         * Handler dismissal notifiche
         */
        handleNotificationDismiss: function(e) {
            e.preventDefault();
            $(e.currentTarget).closest('.disco747-notification').fadeOut(function() {
                $(this).remove();
            });
        },
        
        /**
         * Carica preventivi
         */
        loadPreventivi: function() {
            var self = this;
            
            self.showLoading('.preventivi-loading');
            
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'disco747_get_preventivi',
                    nonce: self.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.state.preventivi = response.data.preventivi || [];
                        self.state.filteredPreventivi = self.state.preventivi;
                        self.renderPreventivi();
                    } else {
                        self.showNotification('error', response.data || 'Errore caricamento preventivi');
                    }
                },
                error: function() {
                    self.showNotification('error', 'Errore di comunicazione con il server');
                },
                complete: function() {
                    self.hideLoading('.preventivi-loading');
                }
            });
        },
        
        /**
         * Renderizza preventivi
         */
        renderPreventivi: function() {
            // Implementazione rendering tabella preventivi
            var start = (this.state.currentPage - 1) * this.state.itemsPerPage;
            var end = start + this.state.itemsPerPage;
            var preventivi = this.state.filteredPreventivi.slice(start, end);
            
            // Renderizza righe tabella
            var html = '';
            preventivi.forEach(function(p) {
                html += this.renderPreventivoRow(p);
            }.bind(this));
            
            $('#preventivi-table tbody').html(html);
            this.renderPagination();
        },
        
        /**
         * Renderizza singola riga preventivo
         */
        renderPreventivoRow: function(preventivo) {
            return '<tr>' +
                '<td>' + preventivo.id + '</td>' +
                '<td>' + preventivo.nome_referente + '</td>' +
                '<td>' + preventivo.data_evento + '</td>' +
                '<td>' + preventivo.tipo_menu + '</td>' +
                '<td>' + this.formatCurrency(preventivo.importo_preventivo) + '</td>' +
                '<td><span class="badge badge-' + preventivo.stato + '">' + preventivo.stato + '</span></td>' +
                '<td>' + this.renderActions(preventivo) + '</td>' +
                '</tr>';
        },
        
        /**
         * Renderizza azioni preventivo
         */
        renderActions: function(preventivo) {
            return '<button class="button view-preventivo" data-id="' + preventivo.id + '">Visualizza</button> ' +
                   '<button class="button edit-preventivo" data-id="' + preventivo.id + '">Modifica</button> ' +
                   '<button class="button delete-preventivo" data-id="' + preventivo.id + '">Elimina</button>';
        },
        
        /**
         * Mostra dettagli preventivo
         */
        showPreventivoDetails: function(id) {
            var self = this;
            
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'disco747_get_preventivo',
                    nonce: self.config.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        self.openPreventivoModal(response.data.preventivo);
                    } else {
                        self.showNotification('error', 'Preventivo non trovato');
                    }
                },
                error: function() {
                    self.showNotification('error', 'Errore caricamento preventivo');
                }
            });
        },
        
        /**
         * Apre modal preventivo
         */
        openPreventivoModal: function(preventivo) {
            // Implementazione modal
            console.log('Apertura modal per preventivo:', preventivo);
        },
        
        /**
         * Aggiorna stato preventivo
         */
        updatePreventivoStatus: function(id, status) {
            var self = this;
            
            console.log('[747Disco] Aggiornamento stato preventivo #' + id + ' a "' + status + '"');
            
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'disco747_update_preventivo_status',
                    nonce: self.config.nonce,
                    id: id,
                    status: status
                },
                success: function(response) {
                    console.log('[747Disco] Risposta server:', response);
                    
                    if (response.success) {
                        var message = response.data.message || 'Stato aggiornato con successo';
                        self.showNotification('success', message);
                        
                        // Ricarica la pagina dopo 1.5 secondi per mostrare i cambiamenti
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        self.showNotification('error', response.data || 'Errore aggiornamento');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[747Disco] Errore AJAX:', {xhr: xhr, status: status, error: error});
                    self.showNotification('error', 'Errore di comunicazione: ' + error);
                }
            });
        },
        
        /**
         * Elimina preventivo
         */
        deletePreventivo: function(id) {
            var self = this;
            
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'disco747_delete_preventivo',
                    nonce: self.config.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Preventivo eliminato');
                        self.loadPreventivi();
                    } else {
                        self.showNotification('error', response.data || 'Errore eliminazione');
                    }
                },
                error: function() {
                    self.showNotification('error', 'Errore di comunicazione');
                }
            });
        },
        
        /**
         * Sincronizza storage
         */
        syncStorage: function() {
            var self = this;
            
            if (self.state.isSyncing) {
                return;
            }
            
            self.state.isSyncing = true;
            self.showLoading('.sync-loading');
            
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'disco747_sync_storage',
                    nonce: self.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Sincronizzazione completata');
                    } else {
                        self.showNotification('error', response.data || 'Errore sincronizzazione');
                    }
                },
                error: function() {
                    self.showNotification('error', 'Errore di comunicazione');
                },
                complete: function() {
                    self.state.isSyncing = false;
                    self.hideLoading('.sync-loading');
                }
            });
        },
        
        /**
         * Test connessione storage
         */
        testStorageConnection: function() {
            var self = this;
            
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'disco747_test_storage',
                    nonce: self.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Connessione storage OK');
                    } else {
                        self.showNotification('error', response.data || 'Connessione fallita');
                    }
                },
                error: function() {
                    self.showNotification('error', 'Errore di comunicazione');
                }
            });
        },
        
        /**
         * Applica filtri
         */
        applyFilters: function() {
            var filters = {
                search: $('.filter-search').val(),
                stato: $('.filter-stato').val(),
                menu: $('.filter-menu').val(),
                data_da: $('.filter-data-da').val(),
                data_a: $('.filter-data-a').val()
            };
            
            this.state.filteredPreventivi = this.filterPreventivi(this.state.preventivi, filters);
            this.state.currentPage = 1;
            this.renderPreventivi();
        },
        
        /**
         * Filtra preventivi
         */
        filterPreventivi: function(preventivi, filters) {
            return preventivi.filter(function(p) {
                var matches = true;
                
                if (filters.search) {
                    var search = filters.search.toLowerCase();
                    matches = matches && (
                        p.nome_referente.toLowerCase().includes(search) ||
                        p.cognome_referente.toLowerCase().includes(search) ||
                        p.mail.toLowerCase().includes(search)
                    );
                }
                
                if (filters.stato) {
                    matches = matches && p.stato === filters.stato;
                }
                
                if (filters.menu) {
                    matches = matches && p.tipo_menu === filters.menu;
                }
                
                // Altri filtri...
                
                return matches;
            });
        },
        
        /**
         * Inizializza tooltips
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                var $el = $(this);
                var text = $el.data('tooltip');
                
                $el.on('mouseenter', function() {
                    var $tooltip = $('<div class="disco747-tooltip">' + text + '</div>');
                    $('body').append($tooltip);
                    
                    var offset = $el.offset();
                    $tooltip.css({
                        top: offset.top - $tooltip.outerHeight() - 10,
                        left: offset.left + ($el.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                    });
                });
                
                $el.on('mouseleave', function() {
                    $('.disco747-tooltip').remove();
                });
            });
        },
        
        /**
         * Formatta valuta
         */
        formatCurrency: function(amount) {
            if (!amount) return '€0,00';
            return '€' + parseFloat(amount).toLocaleString('it-IT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        
        /**
         * Mostra notifica
         */
        showNotification: function(type, message) {
            var iconMap = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            
            var notification = $('<div class="disco747-notification disco747-notification-' + type + '">' +
                '<span class="disco747-notification-icon">' + (iconMap[type] || '') + '</span>' +
                '<span class="disco747-notification-message">' + message + '</span>' +
                '<button class="disco747-dismiss"><span class="screen-reader-text">Dismiss</span></button>' +
            '</div>');
            
            $('.wrap').prepend(notification);
            notification.hide().fadeIn();
            
            // Auto-hide dopo 5 secondi per successo e info
            if (type === 'success' || type === 'info') {
                setTimeout(function() {
                    notification.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },
        
        /**
         * Mostra loading
         */
        showLoading: function(selector) {
            $(selector).show().find('.spinner').addClass('is-active');
        },
        
        /**
         * Nascondi loading
         */
        hideLoading: function(selector) {
            $(selector).hide().find('.spinner').removeClass('is-active');
        },
        
        /**
         * Valida form
         */
        validateForm: function($form) {
            var isValid = true;
            
            $form.find('[required]').each(function() {
                var $field = $(this);
                if (!$field.val().trim()) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });
            
            return isValid;
        },
        
        /**
         * Crea URL amministrazione
         */
        buildUrl: function(page, params) {
            var url = 'admin.php?page=' + page;
            if (params) {
                for (var key in params) {
                    url += '&' + key + '=' + encodeURIComponent(params[key]);
                }
            }
            return url;
        },
        
        /**
         * Renderizza paginazione
         */
        renderPagination: function() {
            var totalPages = Math.ceil(this.state.filteredPreventivi.length / this.state.itemsPerPage);
            var $pagination = $('.disco747-pagination');
            
            if ($pagination.length && totalPages > 1) {
                var paginationHtml = '';
                for (var i = 1; i <= totalPages; i++) {
                    var activeClass = i === this.state.currentPage ? ' current' : '';
                    paginationHtml += '<a href="#" class="pagination-link' + activeClass + '" data-page="' + i + '">' + i + '</a>';
                }
                $pagination.html(paginationHtml);
            }
        },
        
        /**
         * Scroll to table
         */
        scrollToTable: function() {
            $('html, body').animate({
                scrollTop: $("#preventivi-table").offset().top - 100
            }, 300);
        }
    };

    // ============================================================================
    // GESTIONE TEMPLATE MESSAGGI
    // ============================================================================

    var Disco747MessagesTemplates = {
        
        init: function() {
            this.setupTemplateEditor();
            this.setupPlaceholderHelper();
        },
        
        /**
         * Setup editor template con suggerimenti
         */
        setupTemplateEditor: function() {
            // Auto-resize textareas
            $('textarea[id^="email_template_"], textarea[id^="whatsapp_template_"]').each(function() {
                var $textarea = $(this);
                
                $textarea.on('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
                
                // Trigger iniziale
                $textarea.trigger('input');
            });
        },
        
        /**
         * Helper per inserire placeholder
         */
        setupPlaceholderHelper: function() {
            // Aggiungi pulsanti placeholder a ogni textarea
            $('textarea[id^="email_template_"], textarea[id^="whatsapp_template_"]').each(function() {
                var $textarea = $(this);
                var $wrapper = $textarea.parent();
                
                // Crea barra placeholder
                var $placeholderBar = $('<div class="placeholder-helper"></div>').css({
                    'margin-top': '10px',
                    'padding': '10px',
                    'background': '#f1f1f1',
                    'border-radius': '4px',
                    'font-size': '12px'
                });
                
                var placeholders = [
                    '{{nome}}', '{{cognome}}', '{{nome_completo}}',
                    '{{email}}', '{{telefono}}', '{{data_evento}}',
                    '{{tipo_evento}}', '{{menu}}', '{{numero_invitati}}',
                    '{{importo}}', '{{acconto}}', '{{preventivo_id}}'
                ];
                
                placeholders.forEach(function(placeholder) {
                    var $btn = $('<button type="button"></button>')
                        .text(placeholder)
                        .css({
                            'margin': '2px',
                            'padding': '4px 8px',
                            'background': '#fff',
                            'border': '1px solid #ccc',
                            'border-radius': '3px',
                            'cursor': 'pointer',
                            'font-family': 'monospace'
                        })
                        .on('click', function(e) {
                            e.preventDefault();
                            Disco747MessagesTemplates.insertAtCursor($textarea[0], placeholder);
                        });
                    
                    $placeholderBar.append($btn);
                });
                
                $wrapper.append($placeholderBar);
            });
        },
        
        /**
         * Inserisce testo alla posizione del cursore
         */
        insertAtCursor: function(textarea, text) {
            var startPos = textarea.selectionStart;
            var endPos = textarea.selectionEnd;
            var scrollTop = textarea.scrollTop;
            
            textarea.value = textarea.value.substring(0, startPos) + 
                            text + 
                            textarea.value.substring(endPos, textarea.value.length);
            
            textarea.focus();
            textarea.selectionStart = startPos + text.length;
            textarea.selectionEnd = startPos + text.length;
            textarea.scrollTop = scrollTop;
            
            // Trigger change event
            $(textarea).trigger('input');
        }
    };

    // ============================================================================
    // INIZIALIZZAZIONE
    // ============================================================================

    // Inizializzazione quando DOM è pronto
    $(document).ready(function() {
        // Inizializza admin principale
        Disco747Admin.init();
        
        // Inizializza template messaggi se siamo nella pagina corretta
        if ($('body').hasClass('disco747-crm_page_disco747-messages')) {
            Disco747MessagesTemplates.init();
        }
    });

    // Esporta nell'oggetto globale per accesso esterno
    window.Disco747Admin = Disco747Admin;
    window.Disco747MessagesTemplates = Disco747MessagesTemplates;

})(jQuery);