/**
 * JavaScript Frontend 747 Disco CRM
 * Funzionalità avanzate per interfaccia utente
 * Mobile-first, ottimizzato per touch e dispositivi mobili
 */

(function($) {
    'use strict';
    
    // Namespace globale per 747 Disco CRM
    window.Disco747 = window.Disco747 || {};
    
    // Configurazione globale
    const config = {
        debug: true,
        ajax_timeout: 30000,
        retry_attempts: 3,
        animation_duration: 300,
        touch_threshold: 100
    };
    
    // Cache per elementi DOM utilizzati frequentemente
    const cache = {
        $window: $(window),
        $document: $(document),
        $body: $('body')
    };
    
    // ============================================================================
    // UTILITY FUNCTIONS
    // ============================================================================
    
    /**
     * Log sicuro per debug
     */
    function log(message, type = 'info') {
        if (config.debug && console && console.log) {
            const timestamp = new Date().toLocaleTimeString();
            console.log(`[${timestamp}] [Disco747-${type.toUpperCase()}] ${message}`);
        }
    }
    
    /**
     * Escape HTML per sicurezza
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Formatta importo in euro
     */
    function formatCurrency(amount) {
        if (!amount && amount !== 0) return '€0,00';
        return '€' + parseFloat(amount).toLocaleString('it-IT', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    /**
     * Formatta data in formato italiano
     */
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT');
    }
    
    /**
     * Debounce per limitare chiamate frequenti
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Throttle per limitare eventi scroll/resize
     */
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
    
    // ============================================================================
    // NOTIFICATION SYSTEM
    // ============================================================================
    
    const NotificationManager = {
        container: null,
        
        init() {
            this.container = $('#disco747-notifications');
            if (!this.container.length) {
                this.container = $('<div id="disco747-notifications" class="disco747-notifications-container"></div>');
                cache.$body.prepend(this.container);
            }
        },
        
        show(message, type = 'success', duration = 5000) {
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            
            const notification = $(`
                <div class="disco747-notification ${type} disco747-fade-in">
                    <span class="disco747-notification-icon">${icons[type] || '📢'}</span>
                    <span class="disco747-notification-message">${escapeHtml(message)}</span>
                    <button class="disco747-notification-close" aria-label="Chiudi notifica">×</button>
                </div>
            `);
            
            this.container.prepend(notification);
            
            // Auto-remove dopo duration
            if (duration > 0) {
                setTimeout(() => {
                    this.remove(notification);
                }, duration);
            }
            
            // Click per chiudere
            notification.find('.disco747-notification-close').on('click', () => {
                this.remove(notification);
            });
            
            log(`Notifica mostrata: ${type} - ${message}`);
            return notification;
        },
        
        remove(notification) {
            notification.fadeOut(config.animation_duration, function() {
                $(this).remove();
            });
        },
        
        clear() {
            this.container.empty();
        }
    };
    
    // ============================================================================
    // AJAX MANAGER CON RETRY E CACHING
    // ============================================================================
    
    const AjaxManager = {
        cache: new Map(),
        activeRequests: new Map(),
        
        async request(action, data = {}, options = {}) {
            const defaults = {
                method: 'POST',
                timeout: config.ajax_timeout,
                cache: false,
                retries: config.retry_attempts,
                showLoader: true,
                showErrors: true
            };
            
            const settings = { ...defaults, ...options };
            const cacheKey = `${action}_${JSON.stringify(data)}`;
            
            // Controlla cache se abilitata
            if (settings.cache && this.cache.has(cacheKey)) {
                log(`Risposta da cache per: ${action}`);
                return this.cache.get(cacheKey);
            }
            
            // Evita richieste duplicate
            if (this.activeRequests.has(cacheKey)) {
                log(`Richiesta già in corso per: ${action}`);
                return this.activeRequests.get(cacheKey);
            }
            
            const requestData = {
                action: action,
                nonce: disco747Frontend.nonce,
                ...data
            };
            
            const requestPromise = this._makeRequest(requestData, settings, 0);
            this.activeRequests.set(cacheKey, requestPromise);
            
            try {
                const result = await requestPromise;
                
                // Salva in cache se richiesto
                if (settings.cache) {
                    this.cache.set(cacheKey, result);
                }
                
                return result;
            } finally {
                this.activeRequests.delete(cacheKey);
            }
        },
        
        async _makeRequest(data, settings, attempt) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: disco747Frontend.ajax_url,
                    method: settings.method,
                    data: data,
                    timeout: settings.timeout,
                    dataType: 'json'
                })
                .done((response) => {
                    if (response.success) {
                        log(`AJAX success per ${data.action} (tentativo ${attempt + 1})`);
                        resolve(response.data);
                    } else {
                        const error = new Error(response.data?.message || 'Errore server sconosciuto');
                        if (settings.showErrors) {
                            NotificationManager.show(error.message, 'error');
                        }
                        reject(error);
                    }
                })
                .fail(async (xhr, status, error) => {
                    log(`AJAX error per ${data.action}: ${status} - ${error}`, 'error');
                    
                    // Retry automatico per errori di rete
                    if (attempt < settings.retries && this._shouldRetry(xhr.status)) {
                        log(`Retry ${attempt + 1}/${settings.retries} per ${data.action}`);
                        await this._delay(1000 * (attempt + 1));
                        try {
                            const result = await this._makeRequest(data, settings, attempt + 1);
                            resolve(result);
                        } catch (retryError) {
                            reject(retryError);
                        }
                    } else {
                        const errorMessage = this._getErrorMessage(xhr, status, error);
                        if (settings.showErrors) {
                            NotificationManager.show(errorMessage, 'error');
                        }
                        reject(new Error(errorMessage));
                    }
                });
            });
        },
        
        _shouldRetry(status) {
            return [0, 429, 500, 502, 503, 504].includes(status);
        },
        
        _getErrorMessage(xhr, status, error) {
            if (status === 'timeout') {
                return 'Timeout della richiesta. Riprova più tardi.';
            } else if (status === 'abort') {
                return 'Richiesta annullata.';
            } else if (xhr.status === 0) {
                return 'Errore di connessione. Controlla la tua connessione internet.';
            } else {
                return `Errore ${xhr.status}: ${error}`;
            }
        },
        
        _delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        
        clearCache() {
            this.cache.clear();
            log('Cache AJAX pulita');
        }
    };
    
    // ============================================================================
    // FORM MANAGER CON VALIDAZIONE
    // ============================================================================
    
    const FormManager = {
        forms: new Map(),
        
        init() {
            this.registerForm('disco747-login-form', this.handleLogin);
            this.registerForm('disco747-preventivi-form', this.handlePreventivo);
            this.setupGlobalValidation();
        },
        
        registerForm(formId, handler) {
            const $form = $(`#${formId}`);
            if ($form.length) {
                this.forms.set(formId, { $form, handler });
                $form.on('submit', (e) => this.handleSubmit(e, formId));
                log(`Form registrato: ${formId}`);
            }
        },
        
        async handleSubmit(e, formId) {
            e.preventDefault();
            
            const formData = this.forms.get(formId);
            if (!formData) {
                log(`Form non trovato: ${formId}`, 'error');
                return;
            }
            
            const { $form, handler } = formData;
            
            // Validazione del form
            if (!this.validateForm($form)) {
                return;
            }
            
            // Disabilita form durante invio
            this.setFormLoading($form, true);
            
            try {
                await handler.call(this, $form);
            } catch (error) {
                log(`Errore invio form ${formId}: ${error.message}`, 'error');
            } finally {
                this.setFormLoading($form, false);
            }
        },
        
        validateForm($form) {
            let isValid = true;
            const $requiredFields = $form.find('[required]');
            
            $requiredFields.each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (!value) {
                    FormManager.showFieldError($field, 'Questo campo è obbligatorio');
                    isValid = false;
                } else {
                    FormManager.clearFieldError($field);
                }
            });
            
            // Validazione email
            const $emailFields = $form.find('input[type="email"]');
            $emailFields.each(function() {
                const $field = $(this);
                const email = $field.val().trim();
                
                if (email && !FormManager.isValidEmail(email)) {
                    FormManager.showFieldError($field, 'Inserisci un indirizzo email valido');
                    isValid = false;
                }
            });
            
            return isValid;
        },
        
        showFieldError($field, message) {
            this.clearFieldError($field);
            
            $field.addClass('disco747-form-error');
            const $error = $(`<div class="disco747-field-error">${escapeHtml(message)}</div>`);
            $field.closest('.disco747-form-group, .form-group').append($error);
        },
        
        clearFieldError($field) {
            $field.removeClass('disco747-form-error');
            $field.closest('.disco747-form-group, .form-group').find('.disco747-field-error').remove();
        },
        
        setFormLoading($form, loading) {
            const $submitBtn = $form.find('[type="submit"]');
            
            if (loading) {
                $submitBtn.prop('disabled', true);
                $submitBtn.data('original-text', $submitBtn.text());
                $submitBtn.text('🔄 Elaborazione...');
                $form.addClass('disco747-form-loading');
            } else {
                $submitBtn.prop('disabled', false);
                $submitBtn.text($submitBtn.data('original-text') || 'Invia');
                $form.removeClass('disco747-form-loading');
            }
        },
        
        isValidEmail(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },
        
        setupGlobalValidation() {
            // Rimuovi errori quando l'utente inizia a digitare
            cache.$document.on('input', '.disco747-form-error', function() {
                FormManager.clearFieldError($(this));
            });
        },
        
        // Handler specifici per i form
        async handleLogin($form) {
            const formData = this.serializeForm($form);
            
            try {
                const result = await AjaxManager.request('disco747_frontend_login', formData);
                
                NotificationManager.show(result.message, 'success');
                
                // Redirect dopo breve pausa
                setTimeout(() => {
                    window.location.href = result.redirect_to || '/disco747-dashboard/';
                }, 1000);
                
            } catch (error) {
                log(`Errore login: ${error.message}`, 'error');
            }
        },
        
        async handlePreventivo($form) {
            const formData = this.serializeForm($form);
            const isEditMode = formData.edit_mode === '1';
            const action = isEditMode ? 'disco747_update_preventivo' : 'disco747_submit_preventivo';
            
            try {
                const result = await AjaxManager.request(action, formData);
                
                NotificationManager.show(result.message, 'success');
                
                // Gestione WhatsApp se presente
                if (result.whatsapp_url) {
                    setTimeout(() => {
                        window.open(result.whatsapp_url, '_blank');
                    }, 2000);
                }
                
                // Reset form se non in modalità modifica
                if (!isEditMode) {
                    this.resetForm($form);
                }
                
            } catch (error) {
                log(`Errore preventivo: ${error.message}`, 'error');
            }
        },
        
        serializeForm($form) {
            const data = {};
            
            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                const type = $field.attr('type');
                
                if (!name) return;
                
                if (type === 'radio' || type === 'checkbox') {
                    if ($field.is(':checked')) {
                        data[name] = $field.val();
                    }
                } else {
                    data[name] = $field.val();
                }
            });
            
            return data;
        },
        
        resetForm($form) {
            $form[0].reset();
            this.clearAllFieldErrors($form);
            
            // Ripristina valori predefiniti se esistono
            $form.find('[data-default]').each(function() {
                $(this).val($(this).data('default'));
            });
        },
        
        clearAllFieldErrors($form) {
            $form.find('.disco747-form-error').removeClass('disco747-form-error');
            $form.find('.disco747-field-error').remove();
        }
    };
    
    // ============================================================================
    // DASHBOARD MANAGER
    // ============================================================================
    
    const DashboardManager = {
        preventivi: [],
        filteredPreventivi: [],
        currentFilters: {},
        
        init() {
            this.setupEventListeners();
            this.loadPreventivi();
        },
        
        setupEventListeners() {
            // Filtri
            $('#apply-filters').on('click', () => this.applyFilters());
            $('#reset-filters').on('click', () => this.resetFilters());
            
            // Ricerca in tempo reale con debounce
            $('#filter-search').on('input', debounce(() => this.applyFilters(), 300));
            
            // Sync
            $('#sync-btn').on('click', () => this.syncStorage());
            
            // Azioni preventivi
            cache.$document.on('click', '.disco747-action-cancel', (e) => this.cancelPreventivo(e));
            cache.$document.on('click', '.disco747-action-delete', (e) => this.deletePreventivo(e));
        },
        
        async loadPreventivi() {
            try {
                this.showLoadingState();
                
                const data = await AjaxManager.request('disco747_get_all_preventivi', {}, { cache: true });
                this.preventivi = data.preventivi || [];
                this.filteredPreventivi = [...this.preventivi];
                
                this.renderPreventivi();
                this.updateStats();
                
            } catch (error) {
                this.showErrorState('Errore caricamento preventivi');
                log(`Errore caricamento preventivi: ${error.message}`, 'error');
            }
        },
        
        applyFilters() {
            const filters = {
                search: $('#filter-search').val().toLowerCase(),
                stato: $('#filter-stato').val(),
                menu: $('#filter-menu').val(),
                createdBy: $('#filter-created-by').val(),
                dataDa: $('#filter-data-da').val(),
                dataA: $('#filter-data-a').val()
            };
            
            this.currentFilters = filters;
            
            this.filteredPreventivi = this.preventivi.filter(preventivo => {
                // Filtro ricerca
                if (filters.search) {
                    const searchFields = [
                        preventivo.nome_referente,
                        preventivo.cognome_referente,
                        preventivo.mail,
                        preventivo.tipo_evento
                    ].join(' ').toLowerCase();
                    
                    if (!searchFields.includes(filters.search)) {
                        return false;
                    }
                }
                
                // Altri filtri
                if (filters.stato && preventivo.stato !== filters.stato) return false;
                if (filters.menu && preventivo.tipo_menu !== filters.menu) return false;
                if (filters.createdBy && preventivo.created_by !== filters.createdBy) return false;
                if (filters.dataDa && preventivo.data_evento < filters.dataDa) return false;
                if (filters.dataA && preventivo.data_evento > filters.dataA) return false;
                
                return true;
            });
            
            this.renderPreventivi();
            this.updateStats();
            
            log(`Filtri applicati: ${this.filteredPreventivi.length}/${this.preventivi.length} preventivi`);
        },
        
        resetFilters() {
            $('#filter-search, #filter-stato, #filter-menu, #filter-created-by, #filter-data-da, #filter-data-a').val('');
            this.currentFilters = {};
            this.filteredPreventivi = [...this.preventivi];
            this.renderPreventivi();
            this.updateStats();
        },
        
        renderPreventivi() {
            const $tbody = $('#preventivi-table-body');
            
            if (this.filteredPreventivi.length === 0) {
                $tbody.html(this.getEmptyStateHtml());
                return;
            }
            
            let html = '';
            this.filteredPreventivi.forEach((preventivo, index) => {
                html += this.getRowHtml(preventivo, index + 1);
            });
            
            $tbody.html(html);
        },
        
        getRowHtml(preventivo, counter) {
            const statusClass = 'disco747-status-' + preventivo.stato.toLowerCase();
            const createdBy = preventivo.created_by || 'N/A';
            
            return `
                <tr data-preventivo-id="${preventivo.id}">
                    <td><span class="disco747-counter-badge">#${counter}</span></td>
                    <td>
                        <div class="disco747-client-info">
                            <strong>${escapeHtml(preventivo.nome_referente)} ${escapeHtml(preventivo.cognome_referente)}</strong>
                        </div>
                        <div class="disco747-client-contact">${escapeHtml(preventivo.mail)}</div>
                    </td>
                    <td>
                        <div class="disco747-event-title"><strong>${escapeHtml(preventivo.tipo_evento)}</strong></div>
                        <div class="disco747-event-menu">${escapeHtml(preventivo.tipo_menu)}</div>
                    </td>
                    <td>${formatDate(preventivo.data_evento)}</td>
                    <td><span class="disco747-status-badge ${statusClass}">${escapeHtml(preventivo.stato)}</span></td>
                    <td><strong class="disco747-amount-main">${formatCurrency(preventivo.importo_preventivo)}</strong></td>
                    <td><span class="disco747-created-by">${escapeHtml(createdBy)}</span></td>
                    <td>
                        <div class="disco747-action-buttons">
                            <a href="/disco747-preventivi/?edit=${preventivo.id}" class="disco747-btn disco747-btn-sm disco747-btn-primary">
                                ✏️ Modifica
                            </a>
                            <button class="disco747-btn disco747-btn-sm disco747-btn-danger disco747-action-cancel" data-id="${preventivo.id}">
                                ❌ Annulla
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        },
        
        getEmptyStateHtml() {
            return `
                <tr>
                    <td colspan="8" class="disco747-empty-state">
                        <div class="disco747-empty-icon">📋</div>
                        <div class="disco747-empty-title">Nessun preventivo trovato</div>
                        <div class="disco747-empty-text">Non ci sono preventivi che corrispondono ai filtri selezionati</div>
                        <a href="/disco747-preventivi/" class="disco747-btn disco747-btn-primary">
                            ➕ Crea nuovo preventivo
                        </a>
                    </td>
                </tr>
            `;
        },
        
        updateStats() {
            const total = this.filteredPreventivi.length;
            const pending = this.filteredPreventivi.filter(p => p.stato === 'Attivo').length;
            const confirmed = this.filteredPreventivi.filter(p => p.stato === 'Confermato').length;
            
            // Calcola ricavi del mese corrente
            const currentMonth = new Date().getMonth() + 1;
            const currentYear = new Date().getFullYear();
            const monthlyRevenue = this.filteredPreventivi
                .filter(p => {
                    const eventDate = new Date(p.data_evento);
                    return eventDate.getMonth() + 1 === currentMonth && 
                           eventDate.getFullYear() === currentYear &&
                           p.stato === 'Confermato';
                })
                .reduce((sum, p) => sum + parseFloat(p.importo_preventivo || 0), 0);
            
            $('#stat-total').text(total);
            $('#stat-pending').text(pending);
            $('#stat-confirmed').text(confirmed);
            $('#stat-revenue').text(formatCurrency(monthlyRevenue));
        },
        
        showLoadingState() {
            $('#preventivi-table-body').html(`
                <tr>
                    <td colspan="8" class="disco747-loading">
                        <div class="disco747-spinner"></div>
                        <div>Caricamento preventivi...</div>
                    </td>
                </tr>
            `);
        },
        
        showErrorState(message) {
            $('#preventivi-table-body').html(`
                <tr>
                    <td colspan="8" class="disco747-empty-state">
                        <div class="disco747-empty-icon">⚠️</div>
                        <div class="disco747-empty-title">Errore</div>
                        <div class="disco747-empty-text">${escapeHtml(message)}</div>
                        <button class="disco747-btn disco747-btn-primary" onclick="DashboardManager.loadPreventivi()">
                            🔄 Riprova
                        </button>
                    </td>
                </tr>
            `);
        },
        
        async cancelPreventivo(e) {
            e.preventDefault();
            
            const preventivoId = $(e.target).data('id');
            
            if (!confirm('Sei sicuro di voler annullare questo preventivo?')) {
                return;
            }
            
            try {
                await AjaxManager.request('disco747_annulla_preventivo', {
                    preventivo_id: preventivoId,
                    azione: 'cancella'
                });
                
                NotificationManager.show('Preventivo annullato con successo', 'success');
                this.loadPreventivi(); // Ricarica dati
                
            } catch (error) {
                log(`Errore annullamento preventivo: ${error.message}`, 'error');
            }
        },
        
        async syncStorage() {
            const $btn = $('#sync-btn');
            const originalHtml = $btn.html();
            
            $btn.html('<span>🔄</span> Sincronizzando...').prop('disabled', true);
            
            try {
                const result = await AjaxManager.request('disco747_sync_dropbox');
                NotificationManager.show(result.message, 'success');
                this.loadPreventivi(); // Ricarica dati
                
            } catch (error) {
                log(`Errore sincronizzazione: ${error.message}`, 'error');
            } finally {
                $btn.html(originalHtml).prop('disabled', false);
            }
        }
    };
    
    // ============================================================================
    // MOBILE OPTIMIZATIONS
    // ============================================================================
    
    const MobileManager = {
        isMobile: false,
        isTouch: false,
        
        init() {
            this.detectDevice();
            this.setupTouchHandlers();
            this.setupViewportHandlers();
        },
        
        detectDevice() {
            this.isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            this.isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            
            if (this.isMobile) {
                cache.$body.addClass('disco747-mobile');
            }
            
            if (this.isTouch) {
                cache.$body.addClass('disco747-touch');
            }
            
            log(`Device detection: mobile=${this.isMobile}, touch=${this.isTouch}`);
        },
        
        setupTouchHandlers() {
            if (!this.isTouch) return;
            
            // Miglioramento tap per bottoni
            cache.$document.on('touchstart', '.disco747-btn', function() {
                $(this).addClass('disco747-btn-active');
            });
            
            cache.$document.on('touchend touchcancel', '.disco747-btn', function() {
                const $btn = $(this);
                setTimeout(() => {
                    $btn.removeClass('disco747-btn-active');
                }, 150);
            });
            
            // Swipe per eliminare righe tabella (mobile)
            this.setupSwipeToAction();
        },
        
        setupSwipeToAction() {
            let startX = 0;
            let currentX = 0;
            let isDragging = false;
            
            cache.$document.on('touchstart', '.disco747-table tr', function(e) {
                startX = e.originalEvent.touches[0].clientX;
                isDragging = false;
            });
            
            cache.$document.on('touchmove', '.disco747-table tr', function(e) {
                if (!startX) return;
                
                currentX = e.originalEvent.touches[0].clientX;
                const diffX = startX - currentX;
                
                if (Math.abs(diffX) > 10) {
                    isDragging = true;
                    
                    if (diffX > config.touch_threshold) {
                        $(this).addClass('disco747-swipe-left');
                    } else if (diffX < -config.touch_threshold) {
                        $(this).addClass('disco747-swipe-right');
                    }
                }
            });
            
            cache.$document.on('touchend', '.disco747-table tr', function() {
                const $row = $(this);
                
                setTimeout(() => {
                    $row.removeClass('disco747-swipe-left disco747-swipe-right');
                }, 300);
                
                startX = 0;
                currentX = 0;
                isDragging = false;
            });
        },
        
        setupViewportHandlers() {
            // Gestione orientamento device
            cache.$window.on('orientationchange resize', throttle(() => {
                this.handleViewportChange();
            }, 250));
        },
        
        handleViewportChange() {
            // Forza ricalcolo layout dopo cambio orientamento
            setTimeout(() => {
                cache.$window.trigger('resize');
            }, 100);
        }
    };
    
    // ============================================================================
    // PERFORMANCE MANAGER
    // ============================================================================
    
    const PerformanceManager = {
        init() {
            this.setupLazyLoading();
            this.setupImageOptimization();
            this.monitorPerformance();
        },
        
        setupLazyLoading() {
            // Lazy loading per immagini e contenuti pesanti
            if ('IntersectionObserver' in window) {
                const lazyObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const $target = $(entry.target);
                            
                            if ($target.hasClass('disco747-lazy-load')) {
                                this.loadLazyContent($target);
                                lazyObserver.unobserve(entry.target);
                            }
                        }
                    });
                });
                
                $('.disco747-lazy-load').each(function() {
                    lazyObserver.observe(this);
                });
            }
        },
        
        loadLazyContent($element) {
            const src = $element.data('src');
            if (src) {
                $element.attr('src', src).removeClass('disco747-lazy-load');
            }
        },
        
        setupImageOptimization() {
            // Ottimizzazione automatica immagini per mobile
            if (this.isMobile) {
                $('img').each(function() {
                    const $img = $(this);
                    const src = $img.attr('src');
                    
                    if (src && !src.includes('?')) {
                        $img.attr('src', src + '?w=800&q=80');
                    }
                });
            }
        },
        
        monitorPerformance() {
            // Monitoring basilare delle performance
            if ('performance' in window) {
                cache.$window.on('load', () => {
                    setTimeout(() => {
                        const perfData = performance.getEntriesByType('navigation')[0];
                        if (perfData) {
                            const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
                            log(`Page load time: ${loadTime}ms`);
                        }
                    }, 1000);
                });
            }
        }
    };
    
    // ============================================================================
    // INIZIALIZZAZIONE GLOBALE
    // ============================================================================
    
    // Inizializza tutto quando il DOM è pronto
    $(document).ready(function() {
        log('Inizializzazione 747 Disco CRM Frontend');
        
        // Inizializza tutti i manager
        NotificationManager.init();
        FormManager.init();
        MobileManager.init();
        PerformanceManager.init();
        
        // Inizializza dashboard se presente
        if ($('#preventivi-table-body').length) {
            DashboardManager.init();
        }
        
        // Esponi API globali
        window.Disco747 = {
            notification: NotificationManager,
            ajax: AjaxManager,
            form: FormManager,
            dashboard: DashboardManager,
            mobile: MobileManager,
            performance: PerformanceManager,
            utils: {
                log,
                escapeHtml,
                formatCurrency,
                formatDate,
                debounce,
                throttle
            }
        };
        
        log('Inizializzazione completata');
    });
    
})(jQuery);