<?php
/**
 * Debug Excel Structure - Mostra tutte le celle del file Excel
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
    <h1>🔬 Debug Struttura Excel</h1>
    <p>Questo strumento scarica un file Excel specifico e mostra il contenuto di TUTTE le celle per capire la struttura reale.</p>
    
    <div class="card" style="max-width: 800px; margin: 20px 0; padding: 20px;">
        <h2>Seleziona File da Analizzare</h2>
        
        <div style="margin: 20px 0;">
            <label for="file-selector" style="font-weight: bold;">Scegli File Excel:</label>
            <select id="file-selector" class="regular-text" style="margin-left: 10px;">
                <option value="">-- Caricamento lista file... --</option>
            </select>
            <button id="analyze-structure" class="button button-primary" style="margin-left: 10px;" disabled>
                Analizza Struttura
            </button>
        </div>
        
        <div id="loading-indicator" style="display: none; margin: 20px 0;">
            <p><span class="spinner is-active" style="float: none;"></span> Analisi in corso...</p>
        </div>
    </div>
    
    <div id="structure-output" style="display: none; margin-top: 20px;">
        <h2>📋 Struttura File Excel</h2>
        
        <div id="file-info" style="background: #f0f0f1; padding: 15px; margin-bottom: 20px; border-left: 4px solid #2271b1;">
            <!-- Info file popolato via JS -->
        </div>
        
        <div id="sheet-tabs" style="margin-bottom: 20px;">
            <!-- Tabs fogli Excel -->
        </div>
        
        <div style="overflow-x: auto;">
            <table id="excel-grid" class="wp-list-table widefat fixed striped" style="margin-top: 20px; font-size: 12px;">
                <thead>
                    <tr>
                        <th width="50px">Riga/Col</th>
                        <!-- Colonne A-Z generate via JS -->
                    </tr>
                </thead>
                <tbody id="excel-grid-body">
                    <!-- Celle popolate via JavaScript -->
                </tbody>
            </table>
        </div>
        
        <div id="cell-details" style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccc;">
            <h3>🔍 Dettagli Cella Selezionata</h3>
            <p id="selected-cell-info">Clicca su una cella nella tabella sopra per vedere i dettagli</p>
        </div>
    </div>
</div>

<style>
#excel-grid td, #excel-grid th {
    padding: 8px;
    border: 1px solid #ddd;
    min-width: 120px;
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

#excel-grid th {
    background: #2271b1;
    color: white;
    font-weight: bold;
    position: sticky;
    top: 0;
    z-index: 10;
}

#excel-grid tbody th {
    background: #f0f0f1;
    font-weight: bold;
}

#excel-grid td:hover {
    background: #fffbcc;
    cursor: pointer;
}

.cell-highlight {
    background: #c3e6cb !important;
    font-weight: bold;
}

.cell-empty {
    background: #f8f9fa;
    color: #999;
}

.cell-date {
    background: #d1ecf1;
}

.cell-number {
    background: #fff3cd;
}
</style>

<script>
jQuery(document).ready(function($) {
    let filesList = [];
    let currentFileData = null;
    
    // Carica lista file
    loadFilesList();
    
    function loadFilesList() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_get_excel_files_list',
                nonce: '<?php echo wp_create_nonce('disco747_debug'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    filesList = response.data.files;
                    populateFileSelector();
                } else {
                    alert('Errore caricamento lista file: ' + response.data.message);
                }
            }
        });
    }
    
    function populateFileSelector() {
        let selector = $('#file-selector');
        selector.empty();
        selector.append('<option value="">-- Seleziona un file --</option>');
        
        $.each(filesList, function(index, file) {
            selector.append('<option value="' + file.id + '">' + file.name + '</option>');
        });
        
        selector.prop('disabled', false);
    }
    
    $('#file-selector').on('change', function() {
        let fileId = $(this).val();
        if (fileId) {
            $('#analyze-structure').prop('disabled', false);
        } else {
            $('#analyze-structure').prop('disabled', true);
        }
    });
    
    $('#analyze-structure').on('click', function() {
        let fileId = $('#file-selector').val();
        let fileName = $('#file-selector option:selected').text();
        
        if (!fileId) {
            alert('Seleziona un file prima');
            return;
        }
        
        analyzeFileStructure(fileId, fileName);
    });
    
    function analyzeFileStructure(fileId, fileName) {
        $('#loading-indicator').show();
        $('#structure-output').hide();
        $('#analyze-structure').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_analyze_excel_structure',
                nonce: '<?php echo wp_create_nonce('disco747_debug'); ?>',
                file_id: fileId
            },
            success: function(response) {
                $('#loading-indicator').hide();
                $('#analyze-structure').prop('disabled', false);
                
                if (response.success) {
                    currentFileData = response.data;
                    displayStructure(fileName);
                } else {
                    alert('Errore analisi: ' + response.data.message);
                }
            },
            error: function() {
                $('#loading-indicator').hide();
                $('#analyze-structure').prop('disabled', false);
                alert('Errore di connessione');
            }
        });
    }
    
    function displayStructure(fileName) {
        // Info file
        let info = '<strong>File:</strong> ' + fileName + '<br>';
        info += '<strong>Fogli trovati:</strong> ' + currentFileData.sheets.length + '<br>';
        info += '<strong>Foglio attivo:</strong> ' + currentFileData.active_sheet;
        $('#file-info').html(info);
        
        // Crea griglia Excel (prime 30 righe, colonne A-J)
        createExcelGrid(currentFileData.cells);
        
        $('#structure-output').show();
    }
    
    function createExcelGrid(cells) {
        let tbody = $('#excel-grid-body');
        let thead = $('#excel-grid thead tr');
        
        tbody.empty();
        thead.find('th:not(:first)').remove();
        
        // Intestazioni colonne (A-J)
        let cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $.each(cols, function(i, col) {
            thead.append('<th>' + col + '</th>');
        });
        
        // Righe (1-30)
        for (let row = 1; row <= 30; row++) {
            let tr = $('<tr>');
            tr.append('<th>' + row + '</th>');
            
            $.each(cols, function(i, col) {
                let cellRef = col + row;
                let cellData = cells[cellRef] || null;
                let td = $('<td>');
                
                td.attr('data-cell', cellRef);
                
                if (cellData) {
                    let displayValue = cellData.display || '';
                    if (displayValue.length > 30) {
                        displayValue = displayValue.substring(0, 30) + '...';
                    }
                    td.text(displayValue);
                    
                    // Evidenzia celle importanti
                    if (cellRef === 'C6') {
                        td.addClass('cell-highlight');
                        td.attr('title', 'CELLA DATA_EVENTO');
                    }
                    
                    // Colora in base al tipo
                    if (cellData.type === 'date') {
                        td.addClass('cell-date');
                    } else if (cellData.type === 'number') {
                        td.addClass('cell-number');
                    }
                } else {
                    td.addClass('cell-empty').text('(vuota)');
                }
                
                tr.append(td);
            });
            
            tbody.append(tr);
        }
        
        // Click handler per dettagli cella
        $('#excel-grid td[data-cell]').on('click', function() {
            let cellRef = $(this).data('cell');
            let cellData = cells[cellRef];
            
            $('#excel-grid td').removeClass('cell-highlight');
            $(this).addClass('cell-highlight');
            
            showCellDetails(cellRef, cellData);
        });
    }
    
    function showCellDetails(cellRef, cellData) {
        if (!cellData) {
            $('#selected-cell-info').html(
                '<strong>Cella:</strong> ' + cellRef + '<br>' +
                '<strong>Valore:</strong> <em>(vuota)</em>'
            );
            return;
        }
        
        let html = '<strong>Cella:</strong> ' + cellRef + '<br>';
        html += '<strong>Valore Raw:</strong> <code>' + (cellData.raw || 'null') + '</code><br>';
        html += '<strong>Valore Formattato:</strong> ' + (cellData.display || '(vuoto)') + '<br>';
        html += '<strong>Tipo:</strong> ' + (cellData.type || 'unknown') + '<br>';
        
        if (cellData.formula) {
            html += '<strong>Formula:</strong> <code>' + cellData.formula + '</code><br>';
        }
        
        if (cellData.type === 'date' && cellData.parsed_date) {
            html += '<strong>Data Parsata:</strong> ' + cellData.parsed_date + '<br>';
        }
        
        $('#selected-cell-info').html(html);
    }
});
</script>
<?php
