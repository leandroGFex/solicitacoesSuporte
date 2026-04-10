/**
 * Shared Import Helper Logic
 * Handles Excel reading, mapping UI, and bulk API submission.
 */

let importData = [];
let importColumns = [];
let importConfig = {
    endpoint: '',
    fields: [],
    onSuccess: () => {}
};

function openImportModal(config) {
    importConfig = config;
    const modalHtml = `
    <div class="modal-overlay" id="importModal" style="display:flex; z-index:4000;">
        <div class="modal" style="max-width:800px; width:95%;">
            <div class="modal-header">
                <h2><span class="material-icons-round">upload_file</span> Importar de Excel</h2>
                <button class="modal-close" onclick="closeImportModal()"><span class="material-icons-round">close</span></button>
            </div>
            <div class="modal-body" id="importModalBody">
                <div id="importStep1">
                    <div style="border:2px dashed var(--border); border-radius:12px; padding:40px; text-align:center; cursor:pointer;" onclick="document.getElementById('importFile').click()">
                        <span class="material-icons-round" style="font-size:48px; color:var(--primary); opacity:0.5;">cloud_upload</span>
                        <p style="margin:10px 0; font-weight:600;">Clique para selecionar ou arraste o arquivo .xlsx / .csv</p>
                        <input type="file" id="importFile" hidden accept=".xlsx, .xls, .csv" onchange="handleFileSelect(event)">
                    </div>
                </div>
                <div id="importStep2" style="display:none;">
                    <p style="margin-bottom:16px; font-size:.9rem; color:var(--text-muted);">Vincule as colunas da sua planilha aos campos do sistema:</p>
                    <div style="max-height:400px; overflow-y:auto; border:1px solid var(--border); border-radius:8px;">
                        <table style="width:100%; border-collapse:collapse;" class="rep-table">
                            <thead>
                                <tr style="background:#f8f9fa; position:sticky; top:0;">
                                    <th style="padding:10px; text-align:left;">Coluna Planilha</th>
                                    <th style="padding:10px; text-align:left;">Campo Sistema</th>
                                    <th style="padding:10px; text-align:left;">Exemplo Dado</th>
                                </tr>
                            </thead>
                            <tbody id="mappingTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="importModalFooter">
                <button class="btn btn-outline" onclick="closeImportModal()">Cancelar</button>
                <button class="btn btn-primary" id="btnRunImport" style="display:none;" onclick="runImport()">Confirmar Importação (<span id="importCount">0</span>)</button>
            </div>
        </div>
    </div>`;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function closeImportModal() {
    const modal = document.getElementById('importModal');
    if (modal) modal.remove();
}

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];
        const json = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

        if (json.length < 2) {
            alert('A planilha parece estar vazia ou sem cabeçalho.');
            return;
        }

        importColumns = json[0].map(c => String(c || '').trim());
        importData = json.slice(1).map(row => {
            let obj = {};
            importColumns.forEach((col, idx) => {
                obj[col] = row[idx];
            });
            return obj;
        });

        renderMappingTable();
    };
    reader.readAsArrayBuffer(file);
}

function renderMappingTable() {
    const tbody = document.getElementById('mappingTableBody');
    tbody.innerHTML = '';
    
    importColumns.forEach((col, idx) => {
        if (!col) return;
        
        const preview = importData[0][col] || '-';
        const options = importConfig.fields.map(f => {
            const selected = suggestMapping(col, f.label) ? 'selected' : '';
            return `<option value="${f.key}" ${selected}>${f.label}</option>`;
        }).join('');

        tbody.innerHTML += `
        <tr>
            <td style="padding:10px; border-bottom:1px solid #eee;"><strong>${col}</strong></td>
            <td style="padding:10px; border-bottom:1px solid #eee;">
                <select class="form-control mapping-select" data-column="${col}" style="padding:4px; font-size:.85rem;">
                    <option value="">-- Ignorar --</option>
                    ${options}
                </select>
            </td>
            <td style="padding:10px; border-bottom:1px solid #eee; font-size:.8rem; color:#666;">${preview}</td>
        </tr>`;
    });

    document.getElementById('importStep1').style.display = 'none';
    document.getElementById('importStep2').style.display = 'block';
    document.getElementById('btnRunImport').style.display = 'inline-flex';
    document.getElementById('importCount').textContent = importData.length;
}

function suggestMapping(colName, fieldLabel) {
    const c = colName.toLowerCase();
    const f = fieldLabel.toLowerCase();
    if (c === f) return true;
    if (f.includes(c) || c.includes(f)) return true;
    
    // Custom common synonyms
    const synonyms = {
        'nome': ['item', 'produto', 'equipamento', 'modelo'],
        'modelo': ['nome', 'item'],
        'serial': ['número de série', 's/n', 'sn', 'serial_number'],
        'iccid': ['chip', 'sim', 'sim_card'],
        'telefone': ['número', 'phone', 'celular'],
        'qtd': ['quantidade', 'atual', 'estoque'],
    };
    
    for (let key in synonyms) {
        if (f.includes(key)) {
            return synonyms[key].some(s => c.includes(s));
        }
    }
    
    return false;
}

async function runImport() {
    const mapping = {};
    document.querySelectorAll('.mapping-select').forEach(sel => {
        if (sel.value) mapping[sel.dataset.column] = sel.value;
    });

    if (Object.keys(mapping).length === 0) {
        alert('Selecione pelo menos um campo para mapear.');
        return;
    }

    const payload = importData.map(row => {
        let mapped = {};
        for (let col in mapping) {
            mapped[mapping[col]] = row[col];
        }
        return mapped;
    });

    const btn = document.getElementById('btnRunImport');
    btn.disabled = true;
    btn.textContent = 'Processando...';

    try {
        const res = await fetch(importConfig.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ data: payload })
        });
        const result = await res.json();
        
        if (result.success) {
            alert(result.message || 'Importação concluída com sucesso!');
            closeImportModal();
            importConfig.onSuccess();
        } else {
            alert('Erro: ' + (result.message || 'Ocorreu um problema na importação.'));
            btn.disabled = false;
            btn.innerHTML = `Confirmar Importação (<span id="importCount">${importData.length}</span>)`;
        }
    } catch (e) {
        alert('Erro de conexão: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = `Confirmar Importação (<span id="importCount">${importData.length}</span>)`;
    }
}
