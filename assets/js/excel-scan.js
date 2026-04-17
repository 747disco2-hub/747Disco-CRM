jQuery(document).ready(function($) {
    'use strict';

    console.log('[Excel-Scan] Script caricato');
    console.log('[Excel-Scan] jQuery version:', $.fn.jquery);
    console.log('[Excel-Scan] ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'NON DEFINITO');
    console.log('[Excel-Scan] disco747ExcelScanData:', typeof disco747ExcelScanData !== 'undefined' ? disco747ExcelScanData : 'NON DEFINITO');

    const ExcelScan = {
        config: {
            ajaxurl: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
            nonce: typeof disco747ExcelScanData !== 'undefined' ? disco747ExcelScanData.nonce : '',
            batchSize: 4 // ✅ Processa 4 file alla volta (batch più sicuro)
        },
        
        // ✅ Flag per prevenire scansioni multiple simultanee
        isScanning: false,
        
        // ✅ Accumulatori per batch progressivi
        totalFilesFound: 0,
        totalProcessed: 0,
        totalSaved: 0,
        totalErrors: 0,
        allMessages: [],
        
        init: function() {
            console.log('[Excel-Scan] Inizializzazione...');
            console.log('[Excel-Scan] Config:', this.config);
            
            const startBtn = $('#start-scan-btn');
            const resetBtn = $('#reset-scan-btn');
            
            console.log('[Excel-Scan] Pulsante #start-scan-btn:', startBtn.length > 0 ? 'TROVATO' : 'NON TROVATO');
            console.log('[Excel-Scan] Pulsante #reset-scan-btn:', resetBtn.length > 0 ? 'TROVATO' : 'NON TROVATO');
            
            if (startBtn.length === 0) {
                console.error('[Excel-Scan] ERRORE: Pulsante #start-scan-btn non trovato nel DOM');
                return;
            }
            
            this.bindEvents();
            console.log('[Excel-Scan] Inizializzazione completata');
        },
        
        bindEvents: function() {
            const self = this;
            
            $('#start-scan-btn').on('click', function(e) {
                console.log('[Excel-Scan] Click su start-scan-btn rilevato');
                self.handleScan(e);
            });
            
            $('#reset-scan-btn').on('click', function(e) {
                console.log('[Excel-Scan] Click su reset-scan-btn rilevato');
                self.handleResetScan(e);
            });
            
            $('#unlock-scan-btn').on('click', function(e) {
                console.log('[Excel-Scan] Click su unlock-scan-btn rilevato');
                self.handleUnlockScan(e);
            });
            
            console.log('[Excel-Scan] Eventi collegati correttamente');
        },
        
        handleScan: function(e) {
            e.preventDefault();
            e.stopImmediatePropagation(); // ✅ Blocca altri handler sullo stesso elemento
            
            console.log('[Excel-Scan] === AVVIO SCANSIONE PROGRESSIVA ===');
            
            // ✅ PREVIENI SCANSIONI MULTIPLE SIMULTANEE (CHECK IMMEDIATO)
            if (this.isScanning) {
                console.warn('[Excel-Scan] ⚠️ Scansione già in corso, richiesta ignorata');
                alert('⚠️ Scansione già in corso! Attendere il completamento.');
                return;
            }
            
            // ✅ IMPOSTA FLAG IMMEDIATAMENTE (prima di tutto)
            this.isScanning = true;
            console.log('[Excel-Scan] 🔒 Flag isScanning impostato a TRUE');
            
            // ✅ DISABILITA PULSANTI IMMEDIATAMENTE
            const btn = $('#start-scan-btn');
            const resetBtn = $('#reset-scan-btn');
            btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Scansione...');
            resetBtn.prop('disabled', true);
            
            const year = $('#scan-year').val();
            const month = $('#scan-month').val();

            console.log('[Excel-Scan] Parametri:', {year: year, month: month, batchSize: this.config.batchSize});

            // Reset accumulatori
            this.totalFilesFound = 0;
            this.totalProcessed = 0;
            this.totalSaved = 0;
            this.totalErrors = 0;
            this.allMessages = [];

            $('#progress-section').show();
            $('#progress-bar-fill').css('width', '0%');
            $('#progress-percent').text('0%');
            $('#progress-status').text('🔍 Ricerca file su Google Drive...');
            $('#results-section').hide();
            $('#new-files-box').hide();
            $('#debug-log').text('🚀 Avvio scansione progressiva (4 file/batch)...\n');

            // ✅ Avvia batch progressivo da offset 0
            this.processBatch(year, month, 0, true, btn, resetBtn);
        },
        
        // ✅ NUOVO: Processa un batch di file
        processBatch: function(year, month, offset, isFirstBatch, btn, resetBtn) {
            const self = this;
            
            const ajaxData = {
                action: 'batch_scan_excel',
                nonce: this.config.nonce,
                year: year,
                month: month,
                offset: offset,
                limit: this.config.batchSize,
                is_first_batch: isFirstBatch
            };

            console.log('[Excel-Scan] 📦 Batch offset ' + offset + ', limit ' + this.config.batchSize);

            $.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: ajaxData,
                timeout: 150000, // ✅ 150 secondi (2.5 min) per batch con margine di sicurezza
                success: function(response) {
                    console.log('[Excel-Scan] ✅ Batch completato:', response);
                    
                    if (response.success && response.data) {
                        const d = response.data;
                        
                        // Aggiorna accumulatori
                        if (isFirstBatch) {
                            self.totalFilesFound = d.total_files || 0;
                        }
                        self.totalProcessed += d.processed || 0;
                        self.totalSaved += d.new_records || 0;
                        self.totalErrors += d.errors || 0;
                        self.allMessages = self.allMessages.concat(d.messages || []);
                        
                        // Aggiorna progress bar
                        const progress = self.totalFilesFound > 0 ? Math.round((offset + d.batch_size) / self.totalFilesFound * 100) : 0;
                        $('#progress-bar-fill').css('width', progress + '%');
                        $('#progress-percent').text(progress + '%');
                        $('#progress-status').text('📦 Batch ' + Math.ceil((offset + d.batch_size) / self.config.batchSize) + ' completato - ' + (offset + d.batch_size) + '/' + self.totalFilesFound + ' file');
                        
                        $('#debug-log').append('✅ Batch ' + offset + '-' + (offset + d.batch_size) + ': ' + d.processed + ' processati, ' + d.errors + ' errori\n');
                        
                        // Se ci sono altri file, processa il prossimo batch
                        if (d.has_more) {
                            console.log('[Excel-Scan] 🔄 Altri file da processare, avvio prossimo batch...');
                            setTimeout(function() {
                                self.processBatch(year, month, d.next_offset, false, btn, resetBtn);
                            }, 500); // 500ms pausa tra batch
                        } else {
                            // Scansione completata!
                            console.log('[Excel-Scan] 🎉 Scansione completa!');
                            self.showFinalResults(btn, resetBtn);
                        }
                    } else {
                        console.error('[Excel-Scan] Risposta non valida:', response);
                        self.handleScanError(null, 'error', response.data?.message || 'Risposta non valida');
                        self.unlockUI(btn, resetBtn);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Excel-Scan] ❌ Errore batch:', {xhr: xhr, status: status, error: error});
                    self.handleScanError(xhr, status, error);
                    self.unlockUI(btn, resetBtn);
                }
            });
        },
        
        // ✅ NUOVO: Mostra risultati finali
        showFinalResults: function(btn, resetBtn) {
            console.log('[Excel-Scan] === MOSTRA RISULTATI FINALI ===');
            
            $('#progress-bar-fill').css('width', '100%');
            $('#progress-percent').text('100%');
            $('#progress-status').text('✅ Scansione completata!');
            
            $('#stat-total').text(this.totalFilesFound);
            $('#stat-processed').text(this.totalProcessed);
            $('#stat-new').text(this.totalSaved);
            $('#stat-updated').text(0);
            $('#stat-errors').text(this.totalErrors);
            
            $('#summary-total').text(this.totalFilesFound);
            $('#summary-new').text(this.totalSaved);
            $('#summary-updated').text(0);
            $('#summary-errors').text(this.totalErrors);
            
            $('#results-section').show();
            
            const messagesList = $('#messages-list');
            messagesList.empty();
            
            if (this.allMessages.length > 0) {
                this.allMessages.forEach(function(msg) {
                    messagesList.append('<li>' + msg + '</li>');
                });
            } else {
                messagesList.append('<li>Nessun messaggio disponibile</li>');
            }
            
            $('#debug-log').append('\n🎉 COMPLETATO!\n');
            $('#debug-log').append('📊 Totale: ' + this.totalFilesFound + ' file\n');
            $('#debug-log').append('✅ Processati: ' + this.totalProcessed + '\n');
            $('#debug-log').append('💾 Salvati: ' + this.totalSaved + '\n');
            $('#debug-log').append('❌ Errori: ' + this.totalErrors + '\n');
            
            this.unlockUI(btn, resetBtn);
        },
        
        // ✅ NUOVO: Sblocca UI
        unlockUI: function(btn, resetBtn) {
            this.isScanning = false;
            console.log('[Excel-Scan] 🔓 Flag isScanning impostato a FALSE');
            btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Analizza Ora');
            resetBtn.prop('disabled', false);
        },
        
        handleResetScan: function(e) {
            e.preventDefault();
            e.stopImmediatePropagation(); // ✅ Blocca altri handler
            
            console.log('[Excel-Scan] === AVVIO RESET & SCAN ===');
            
            // ✅ PREVIENI SCANSIONI MULTIPLE SIMULTANEE (CHECK IMMEDIATO)
            if (this.isScanning) {
                console.warn('[Excel-Scan] ⚠️ Scansione già in corso, richiesta ignorata');
                alert('⚠️ Scansione già in corso! Attendere il completamento.');
                return;
            }
            
            if (!confirm('⚠️ ATTENZIONE!\n\nQuesto cancellerà TUTTI i record dalla tabella e rifarà la scansione completa.\n\nSei sicuro di voler procedere?')) {
                console.log('[Excel-Scan] Reset annullato dall\'utente');
                return;
            }
            
            // ✅ IMPOSTA FLAG IMMEDIATAMENTE dopo il confirm
            this.isScanning = true;
            console.log('[Excel-Scan] 🔒 Flag isScanning impostato a TRUE (reset)');
            
            // ✅ DISABILITA PULSANTI IMMEDIATAMENTE
            const btn = $('#reset-scan-btn');
            const scanBtn = $('#start-scan-btn');
            btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Elaborazione...');
            scanBtn.prop('disabled', true);
            
            const year = $('#scan-year').val();
            const month = $('#scan-month').val();

            console.log('[Excel-Scan] Parametri reset:', {year: year, month: month});

            $('#progress-section').show();
            $('#progress-bar-fill').css('width', '0%');
            $('#progress-percent').text('0%');
            $('#progress-status').text('🗑️ Svuotamento database...');
            $('#results-section').hide();
            $('#new-files-box').hide();
            $('#debug-log').text('🗑️ Svuotamento database in corso...\n');

            const ajaxData = {
                action: 'reset_and_scan_excel',
                nonce: this.config.nonce,
                year: year,
                month: month
            };

            console.log('[Excel-Scan] Invio richiesta AJAX reset a:', this.config.ajaxurl);
            console.log('[Excel-Scan] Dati AJAX reset:', ajaxData);

            $.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: ajaxData,
                timeout: 900000, // ✅ 15 minuti timeout (per scansioni con centinaia di file)
                success: function(response) {
                    console.log('[Excel-Scan] Risposta AJAX reset ricevuta:', response);
                    ExcelScan.handleScanSuccess(response);
                },
                error: function(xhr, status, error) {
                    console.error('[Excel-Scan] Errore AJAX reset:', {xhr: xhr, status: status, error: error});
                    ExcelScan.handleScanError(xhr, status, error);
                },
                complete: function() {
                    console.log('[Excel-Scan] Richiesta AJAX reset completata');
                    // ✅ Rilascia il lock
                    ExcelScan.isScanning = false;
                    console.log('[Excel-Scan] 🔓 Flag isScanning impostato a FALSE (reset)');
                    
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Svuota e Rianalizza');
                    scanBtn.prop('disabled', false);
                }
            });
        },
        
        handleScanSuccess: function(response) {
            console.log('[Excel-Scan] === GESTIONE RISPOSTA SUCCESSO ===');
            console.log('[Excel-Scan] Response completa:', response);
            
            if (response.success) {
                console.log('[Excel-Scan] Risposta positiva, elaborazione dati...');
                const d = response.data;
                
                // ✅ RETROCOMPATIBILITÀ: Controlla se ci sono altri file da processare
                if (d.has_more && d.next_offset !== undefined) {
                    console.log('[Excel-Scan] ⚠️ SAFETY LIMIT attivo - Altri file da processare!');
                    console.log('[Excel-Scan] ⏱️ Attendi 2 secondi e ricarica la pagina per continuare...');
                    
                    alert('⚠️ ATTENZIONE!\n\n' +
                          'Il server ha un limite di timeout.\n\n' +
                          '✅ Processati: ' + d.processed + '/' + d.total_files + ' file\n' +
                          '🔄 Rimanenti: ' + (d.total_files - d.next_offset) + ' file\n\n' +
                          'COSA FARE:\n' +
                          '1. Clicca OK\n' +
                          '2. Ricarica la pagina (F5)\n' +
                          '3. Clicca di nuovo "Analizza Ora"\n\n' +
                          'Il sistema continuerà automaticamente!');
                    
                    $('#progress-status').text('⚠️ Completato parziale - Ricaricare pagina');
                    $('#debug-log').text('⚠️ SAFETY LIMIT RAGGIUNTO\n' +
                                        '='.repeat(50) + '\n\n' +
                                        '📊 Batch ' + d.current_offset + '-' + d.next_offset + ' completato\n' +
                                        '   Processati:      ' + d.processed + '/' + d.total_files + '\n' +
                                        '   Salvati:         ' + (d.new_records || 0) + '\n' +
                                        '   Errori:          ' + (d.errors || 0) + '\n\n' +
                                        '🔄 RICARICA LA PAGINA (F5) e clicca "Analizza Ora" per continuare!\n\n' +
                                        '='.repeat(50));
                    return; // Non mostrare risultati finali
                }
                
                // ✅ Scansione completata al 100%
                $('#progress-bar-fill').css('width', '100%');
                $('#progress-percent').text('100%');
                $('#progress-status').text('✅ Completato!');
                
                $('#stat-total').text(d.total_files || 0);
                $('#stat-processed').text(d.processed || 0);
                $('#stat-new').text(d.new_records || 0);
                $('#stat-updated').text(d.updated_records || 0);
                $('#stat-errors').text(d.errors || 0);
                
                $('#summary-total').text(d.total_files || 0);
                $('#summary-new').text(d.new_records || 0);
                $('#summary-updated').text(d.updated_records || 0);
                $('#summary-errors').text(d.errors || 0);
                
                if (d.errors > 0) {
                    $('#error-card').show();
                }
                
                $('#results-section').fadeIn();
                
                const log = '✅ SCANSIONE COMPLETATA\n' + '='.repeat(50) + '\n\n' +
                    '📊 RISULTATI:\n' +
                    '   File trovati:    ' + (d.total_files || 0) + '\n' +
                    '   Processati:      ' + (d.processed || 0) + '\n' +
                    '   Nuovi:           ' + (d.new_records || 0) + '\n' +
                    '   Aggiornati:      ' + (d.updated_records || 0) + '\n' +
                    '   Errori:          ' + (d.errors || 0) + '\n\n' +
                    '='.repeat(50) + '\n' +
                    '⏱️  Completato: ' + new Date().toLocaleString('it-IT');
                
                $('#debug-log').text(log);
                
                if (d.new_files_list && d.new_files_list.length > 0) {
                    console.log('[Excel-Scan] Mostrando ' + d.new_files_list.length + ' file processati');
                    this.showNewFiles(d.new_files_list);
                } else {
                    console.log('[Excel-Scan] Nessun file da mostrare');
                    $('#new-files-box').fadeIn();
                    $('#new-files-table-body').html('<tr><td colspan="5" style="text-align: center; padding: 30px; color: #999;">Nessun file processato</td></tr>');
                }
            } else {
                console.error('[Excel-Scan] Risposta negativa dal server');
                $('#progress-status').text('❌ Errore');
                const errorMsg = (response.data && response.data.message) ? response.data.message : 'Errore sconosciuto';
                $('#debug-log').text('❌ ERRORE:\n' + errorMsg);
                alert('❌ Errore: ' + errorMsg);
            }
        },
        
        handleScanError: function(xhr, status, error) {
            console.error('[Excel-Scan] === ERRORE AJAX ===');
            console.error('[Excel-Scan] Status:', status);
            console.error('[Excel-Scan] Error:', error);
            
            // ✅ Gestione xhr null (quando success: false)
            if (!xhr || xhr === null) {
                $('#progress-status').text('❌ Errore server');
                $('#debug-log').text('❌ ERRORE SERVER:\n' + error);
                alert('❌ ' + error);
                return;
            }
            
            console.error('[Excel-Scan] Response:', xhr.responseText);
            
            // ✅ Gestione errore 503 Service Unavailable
            if (xhr.status === 503) {
                $('#progress-status').text('❌ Server sovraccarico (503)');
                $('#debug-log').text('❌ ERRORE 503: SERVER SOVRACCARICO\n\n' +
                    'Il server ha troppe richieste simultanee.\n\n' +
                    '✅ SOLUZIONE:\n' +
                    '1. Aspetta 2-3 minuti\n' +
                    '2. Ricarica la pagina (F5)\n' +
                    '3. Riprova la scansione\n\n' +
                    '⚠️ NON cliccare più volte il pulsante!');
                alert('❌ Server sovraccarico (503)\n\n' +
                      'Aspetta 2-3 minuti e ricarica la pagina.\n\n' +
                      '⚠️ NON cliccare più volte il pulsante di scansione!');
                return;
            }
            
            $('#progress-status').text('❌ Errore connessione');
            $('#debug-log').text('❌ ERRORE AJAX:\n' +
                'Status: ' + status + '\n' +
                'Error: ' + error + '\n' +
                'HTTP Code: ' + xhr.status + '\n' +
                'Response: ' + (xhr.responseText || 'Nessuna risposta'));
            alert('❌ Errore di connessione: ' + error);
        },
        
        showNewFiles: function(files) {
            console.log('[Excel-Scan] Popolamento tabella con ' + files.length + ' file');
            const tbody = $('#new-files-table-body');
            tbody.empty();
            
            if (!files || files.length === 0) {
                tbody.html('<tr><td colspan="5" style="text-align: center; padding: 30px; color: #999;">Nessun file da mostrare</td></tr>');
                return;
            }
            
            files.forEach(function(f) {
                let badge = 'badge-info';
                let icon = '📅';
                let stato = f.stato || 'Attivo';
                
                if (stato.toLowerCase() === 'confermato') {
                    badge = 'badge-success';
                    icon = '✅';
                } else if (stato.toLowerCase() === 'annullato') {
                    badge = 'badge-danger';
                    icon = '❌';
                }
                
                const row = '<tr>' +
                    '<td style="font-weight: bold; color: #667eea;">' + (f.data_evento || '-') + '</td>' +
                    '<td>' + (f.tipo_evento || '-') + '</td>' +
                    '<td><span class="badge ' + badge + '">' + (f.tipo_menu || '-') + '</span></td>' +
                    '<td>' + icon + ' ' + stato + '</td>' +
                    '<td class="mobile-hide" style="font-size: 12px; color: #666;">' + (f.filename || '-') + '</td>' +
                    '</tr>';
                
                tbody.append(row);
            });
            
            $('#new-files-box').fadeIn();
            
            setTimeout(function() {
                const box = $('#new-files-box')[0];
                if (box) {
                    box.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                }
            }, 300);
            
            console.log('[Excel-Scan] Tabella popolata con successo');
        },
        
        handleUnlockScan: function(e) {
            e.preventDefault();
            const self = this;
            
            if (!confirm('⚠️ ATTENZIONE!\n\nStai per forzare lo sblocco della scansione.\n\nUsa questa funzione SOLO se:\n- Vedi "Scansione già in corso"\n- Ma NON c\'è alcuna scansione attiva\n\nContinuare?')) {
                return;
            }
            
            console.log('[Excel-Scan] 🔓 Richiesta sblocco lock...');
            
            $.ajax({
                url: self.config.ajaxurl,
                type: 'POST',
                data: {
                    action: 'disco747_unlock_scan',
                    nonce: self.config.nonce
                },
                timeout: 10000, // 10 secondi
                success: function(response) {
                    console.log('[Excel-Scan] ✅ Risposta sblocco:', response);
                    
                    if (response.success) {
                        alert('✅ Lock rilasciato con successo!\n\nPuoi ora avviare una nuova scansione.');
                        $('#progress-status').text('🔓 Lock rilasciato');
                        $('#debug-log').text('✅ Lock rilasciato con successo!\n\nPuoi ora avviare una nuova scansione.');
                    } else {
                        alert('❌ Errore: ' + (response.data?.message || 'Impossibile sbloccare'));
                        $('#debug-log').text('❌ ERRORE SBLOCCO:\n' + (response.data?.message || 'Sconosciuto'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Excel-Scan] ❌ Errore sblocco:', {xhr: xhr, status: status, error: error});
                    alert('❌ Errore durante lo sblocco: ' + error);
                    $('#debug-log').text('❌ ERRORE SBLOCCO AJAX:\n' + error);
                }
            });
        }
    };

    console.log('[Excel-Scan] Avvio inizializzazione...');
    ExcelScan.init();
});