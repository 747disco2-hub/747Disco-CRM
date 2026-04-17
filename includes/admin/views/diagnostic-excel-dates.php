<?php
/**
 * Script Diagnostico: Analizza quali file Excel hanno data_evento NULL nella cella C6
 * 
 * @package    Disco747_CRM
 * @version    1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

?>
<div class="wrap">
    <h1>🔍 Diagnostica Date Excel</h1>
    <p>Analisi dei file Excel su Google Drive per verificare quali hanno la cella C6 (data_evento) vuota.</p>
    
    <div id="diagnostic-results" style="margin-top: 20px;">
        <button id="start-diagnostic" class="button button-primary">Avvia Diagnostica</button>
    </div>
    
    <div id="diagnostic-output" style="margin-top: 20px; display: none;">
        <h2>Risultati Diagnostica</h2>
        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="35%">Nome File</th>
                    <th width="15%">Cartella Anno</th>
                    <th width="15%">Cartella Mese</th>
                    <th width="15%">Cella C6</th>
                    <th width="15%">Stato</th>
                </tr>
            </thead>
            <tbody id="diagnostic-table-body">
                <!-- Popolato via JavaScript -->
            </tbody>
        </table>
        
        <div id="diagnostic-summary" style="margin-top: 20px; padding: 20px; background: #f0f0f1; border-left: 4px solid #2271b1;">
            <h3>Riepilogo</h3>
            <p id="summary-text">Caricamento...</p>
        </div>
    </div>
</div>

<style>
.status-ok { color: #00a32a; font-weight: bold; }
.status-error { color: #d63638; font-weight: bold; }
.status-warning { color: #dba617; font-weight: bold; }
.cell-empty { background: #ffebee; }
.cell-ok { background: #e8f5e9; }
</style>

<script>
jQuery(document).ready(function($) {
    $('#start-diagnostic').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('Analisi in corso...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_diagnostic_excel_dates',
                nonce: '<?php echo wp_create_nonce('disco747_diagnostic'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                    $('#diagnostic-output').show();
                } else {
                    alert('Errore: ' + response.data.message);
                    button.prop('disabled', false).text('Avvia Diagnostica');
                }
            },
            error: function() {
                alert('Errore di connessione');
                button.prop('disabled', false).text('Avvia Diagnostica');
            }
        });
    });
    
    function displayResults(data) {
        var tbody = $('#diagnostic-table-body');
        tbody.empty();
        
        var filesWithoutDate = 0;
        var filesWithDate = 0;
        var filesCorrupt = 0;
        
        $.each(data.files, function(index, file) {
            var statusClass = 'status-ok';
            var statusText = '✅ OK';
            var cellClass = 'cell-ok';
            
            if (file.status === 'error') {
                statusClass = 'status-error';
                statusText = '❌ ERRORE';
                cellClass = 'cell-empty';
                filesCorrupt++;
            } else if (!file.date_value || file.date_value === 'NULL' || file.date_value === '') {
                statusClass = 'status-warning';
                statusText = '⚠️ DATA VUOTA';
                cellClass = 'cell-empty';
                filesWithoutDate++;
            } else {
                filesWithDate++;
            }
            
            var row = '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td><strong>' + file.name + '</strong></td>' +
                '<td>' + (file.year_folder || '-') + '</td>' +
                '<td>' + (file.month_folder || '-') + '</td>' +
                '<td class="' + cellClass + '">' + (file.date_value || '<em>vuota</em>') + '</td>' +
                '<td class="' + statusClass + '">' + statusText + '</td>' +
                '</tr>';
            
            tbody.append(row);
        });
        
        // Riepilogo
        var summaryHtml = '<strong>Totale file analizzati:</strong> ' + data.files.length + '<br>' +
            '✅ File con data valida: ' + filesWithDate + '<br>' +
            '⚠️ File con data vuota (serve fallback): ' + filesWithoutDate + '<br>' +
            '❌ File corrotti/illeggibili: ' + filesCorrupt;
        
        if (filesWithoutDate > 0) {
            summaryHtml += '<br><br><strong style="color: #d63638;">⚠️ ' + filesWithoutDate + ' file necessitano del fallback anno dalla cartella!</strong>';
        }
        
        $('#summary-text').html(summaryHtml);
    }
});
</script>
<?php
