/**
 * Form Handler per Preventivo 747 Disco
 * Gestisce submit e validazione lato client
 * 
 * PERCORSO: /assets/js/preventivo-form.js
 * @version 11.6.2
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🎉 [747Disco] Form handler caricato');
    
    // Selettori
    const $form = $('#preventivo-form, form[action*="disco747"]');
    const $submitBtn = $('#submit-preventivo, input[type="submit"]').first();
    
    if (!$form.length) {
        console.log('⚠️ [747Disco] Form preventivo non trovato');
        return;
    }
    
    console.log('✅ [747Disco] Form preventivo trovato:', $form.attr('id'));
    
    /**
     * Handler submit form
     */
    $form.on('submit', function(e) {
        e.preventDefault();
        
        console.log('🚀 [747Disco] Submit form preventivo');
        
        // Disabilita pulsante
        $submitBtn.prop('disabled', true).val('Salvataggio in corso...');
        
        // Raccogli dati
        const formData = new FormData(this);
        formData.append('action', 'disco747_save_preventivo');
        
        // Aggiungi nonce se disponibile
        if (typeof disco747Ajax !== 'undefined' && disco747Ajax.nonce) {
            formData.append('nonce', disco747Ajax.nonce);
        }
        
        // Log dati inviati (solo chiavi)
        console.log('📤 [747Disco] Campi form:');
        for (let [key, value] of formData.entries()) {
            if (key !== 'nonce') {
                console.log(`  ${key}: ${value}`);
            }
        }
        
        // AJAX request
        $.ajax({
            url: disco747Ajax.ajaxurl || ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('✅ [747Disco] Risposta server:', response);
                
                if (response.success) {
                    // Mostra successo
                    showNotice('✅ ' + response.data.message, 'success');
                    
                    // Log dettagli
                    if (response.data.files) {
                        console.log('📁 File generati:', response.data.files);
                    }
                    if (response.data.cloud_urls) {
                        console.log('☁️ URL cloud:', response.data.cloud_urls);
                    }
                    
                    // Redirect dopo 2 secondi
                    if (response.data.redirect) {
                        setTimeout(function() {
                            console.log('🔄 Redirect a:', response.data.redirect);
                            window.location.href = response.data.redirect;
                        }, 2000);
                    } else {
                        // Reset form
                        setTimeout(function() {
                            $form[0].reset();
                            $submitBtn.prop('disabled', false).val('Salva Preventivo');
                        }, 2000);
                    }
                } else {
                    // Errore dal server
                    const errorMsg = response.data || 'Errore sconosciuto';
                    showNotice('❌ ' + errorMsg, 'error');
                    $submitBtn.prop('disabled', false).val('Salva Preventivo');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ [747Disco] Errore AJAX:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                
                showNotice('❌ Errore di comunicazione: ' + error, 'error');
                $submitBtn.prop('disabled', false).val('Salva Preventivo');
            }
        });
    });
    
    /**
     * Validazione real-time
     */
    
    // Data evento - minimo oggi
    $('input[name="data_evento"]').attr('min', new Date().toISOString().split('T')[0]);
    
    // Email validation
    $('input[name="email"]').on('blur', function() {
        const email = $(this).val();
        if (email && !isValidEmail(email)) {
            $(this).addClass('error');
            showNotice('⚠️ Email non valida', 'warning');
        } else {
            $(this).removeClass('error');
        }
    });
    
    // Telefono validation
    $('input[name="telefono"]').on('blur', function() {
        const tel = $(this).val();
        if (tel && !isValidPhone(tel)) {
            $(this).addClass('error');
            showNotice('⚠️ Telefono non valido', 'warning');
        } else {
            $(this).removeClass('error');
        }
    });
    
    // Calcolo automatico saldo
    $('input[name="importo_totale"], input[name="acconto"]').on('input', function() {
        const totale = parseFloat($('input[name="importo_totale"]').val()) || 0;
        const acconto = parseFloat($('input[name="acconto"]').val()) || 0;
        const saldo = totale - acconto;
        
        // Aggiorna display saldo se esiste
        const $saldoDisplay = $('#saldo-display, .saldo-display');
        if ($saldoDisplay.length) {
            $saldoDisplay.text('€' + saldo.toFixed(2));
        }
        
        // Aggiorna stato
        const $statoDisplay = $('#stato-display, .stato-display');
        if ($statoDisplay.length) {
            if (acconto > 0) {
                $statoDisplay.text('Confermato').css('color', '#28a745');
            } else {
                $statoDisplay.text('Non confermato').css('color', '#ffc107');
            }
        }
    });
    
    /**
     * Utilities
     */
    
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    function isValidPhone(phone) {
        return /^[\d\s\+\-\(\)]{8,20}$/.test(phone);
    }
    
    function showNotice(message, type) {
        // Rimuovi notice precedenti
        $('.disco747-notice').remove();
        
        const typeClass = type === 'success' ? 'notice-success' : 
                         type === 'error' ? 'notice-error' : 
                         'notice-warning';
        
        const $notice = $('<div class="notice ' + typeClass + ' is-dismissible disco747-notice"><p>' + message + '</p></div>');
        
        // Inserisci dopo il titolo o all'inizio del form
        if ($('.wrap h1').length) {
            $('.wrap h1').after($notice);
        } else {
            $form.prepend($notice);
        }
        
        // Auto-dismiss dopo 5 secondi
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    console.log('✅ [747Disco] Form handler inizializzato completamente');
});