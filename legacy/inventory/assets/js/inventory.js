// e:\ARQUIVOS\PROJETOS\SITES\FLEX\SUPORTE FLEX FERRAMENTAS\inventory\assets\js\inventory.js

let inventoryData = [];
let categories = [];
let filteredData = [];

document.addEventListener('DOMContentLoaded', () => {
    loadInventory();
});

function esc(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ---------------------------------------------------------
// CARREGAMENTO E RENDERIZAÇÃO 
// ---------------------------------------------------------

async function loadInventory() {
    try {
        const cat = document.getElementById('filterCategory')?.value || '';
        const res = await fetch(`api/inventory.php?action=list&category=${encodeURIComponent(cat)}`);
        const json = await res.json();

        if (!json.success) {
            toolToast(json.message, 'error');
            return;
        }

        inventoryData = json.data;
        filteredData = [...inventoryData];

        if (json.categories) {
            updateCategoryFilter(json.categories);
        }

        if (json.total_alerts !== undefined) {
            updateAlertBadge(json.total_alerts);
        }

        renderTable();
    } catch (e) {
        console.error(e);
        toolToast('Erro ao carregar itens.', 'error');
    }
}

function updateCategoryFilter(cats) {
    let html = '<option value="">Todas as Categorias</option>';
    cats.forEach(c => {
        html += `<option value="${esc(c)}">${esc(c)}</option>`;
    });
    const sel = document.getElementById('filterCategory');
    if (sel && sel.innerHTML !== html) {
        const val = sel.value;
        sel.innerHTML = html;
        sel.value = val;
    }
}

function updateAlertBadge(total) {
    const badge = document.getElementById('lowStockBadge');
    if (!badge) return;
    if (total > 0) {
        badge.style.display = 'inline-flex';
        badge.innerText = `${total} itens em alerta`;
    } else {
        badge.style.display = 'none';
    }
}

function filterTable() {
    const term = document.getElementById('searchInput').value.toLowerCase();
    const catFilt = document.getElementById('filterCategory').value;

    filteredData = inventoryData.filter(i => {
        let matchTerm = true;
        let matchCat = true;

        if (term) {
            matchTerm = (i.name || '').toLowerCase().includes(term) ||
                (i.category || '').toLowerCase().includes(term) ||
                (i.description || '').toLowerCase().includes(term);
        }

        if (catFilt) {
            matchCat = (i.category === catFilt);
        }

        return matchTerm && matchCat;
    });
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('invTableBody');
    if (!tbody) return;

    if (filteredData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">Nenhum item encontrado</td></tr>';
        return;
    }

    tbody.innerHTML = filteredData.map(i => {
        const qt = parseInt(i.quantity);
        const min = parseInt(i.min_quantity);
        const isLow = qt <= min;

        const rowStyle = isLow ? 'background:#FFF3E0;' : '';
        const statusBadge = isLow
            ? `<span style="background:#FFE0B2; color:#E65100; padding:2px 6px; border-radius:4px; font-size:.75rem; font-weight:600;"><span class="material-icons-round" style="font-size:12px; vertical-align:middle;">warning</span> Baixo (${min})</span>`
            : `<span style="color:var(--text-muted); font-size:.8rem;">Mín: ${min}</span>`;

        return `
        <tr style="${rowStyle}">
            <td><strong>${esc(i.name)}</strong><br><small style="color:var(--text-muted);">${esc(i.category || 'Sem Categoria')}</small></td>
            <td style="color:#666; font-size:0.85rem; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${esc(i.description)}">${esc(i.description || '—')}</td>
            <td style="font-size:1.1rem; font-weight:700; color:${isLow ? '#D84315' : '#2E7D32'};">${qt}</td>
            <td>${statusBadge}</td>
            <td>
                <div style="display:flex; gap:6px;">
                    <button class="btn btn-outline" style="font-size:0.75rem; padding:4px 8px; border-color:#2E7D32; color:#2E7D32;" onclick="openEntryModal(${i.id}, '${esc(i.name)}')">
                        <span class="material-icons-round" style="font-size:14px;">archive</span> Entrada
                    </button>
                    <button class="btn btn-outline" style="font-size:0.75rem; padding:4px 8px;" onclick="openExitModal(${i.id}, '${esc(i.name)}', ${qt})">
                        <span class="material-icons-round" style="font-size:14px;">outbound</span> Saída
                    </button>
                </div>
            </td>
            <td>
                <button class="btn btn-icon" onclick="openModal(${i.id})" title="Editar"><span class="material-icons-round">edit</span></button>
                <button class="btn btn-icon btn-danger" onclick="deleteItem(${i.id})" title="Excluir"><span class="material-icons-round">delete</span></button>
            </td>
        </tr>`;
    }).join('');
}


// ---------------------------------------------------------
// MODAIS DE CADASTRO/EDIÇÃO
// ---------------------------------------------------------

function openModal(id = null) {
    document.getElementById('invId').value = id || '';
    if (id) {
        const item = inventoryData.find(i => i.id == id);
        if (item) {
            document.getElementById('modalTitle').innerText = 'Editar Item';
            document.getElementById('invName').value = item.name;
            document.getElementById('invCategory').value = item.category;
            document.getElementById('invDescription').value = item.description;
            document.getElementById('invQty').value = item.quantity;
            document.getElementById('invMinQty').value = item.min_quantity;
        }
    } else {
        document.getElementById('modalTitle').innerText = 'Novo Item';
        document.getElementById('invName').value = '';
        document.getElementById('invCategory').value = '';
        document.getElementById('invDescription').value = '';
        document.getElementById('invQty').value = '0';
        document.getElementById('invMinQty').value = '0';
    }
    document.getElementById('invModal').classList.add('open');
}

function closeModal() {
    document.getElementById('invModal').classList.remove('open');
}

async function saveItem() {
    const id = document.getElementById('invId').value;
    const name = document.getElementById('invName').value.trim();
    if (!name) { toolToast('O Nome do item é obrigatório', 'error'); return; }

    const fd = new FormData();
    fd.append('action', 'create_update');
    fd.append('id', id);
    fd.append('name', name);
    fd.append('category', document.getElementById('invCategory').value.trim());
    fd.append('description', document.getElementById('invDescription').value.trim());
    fd.append('quantity', document.getElementById('invQty').value);
    fd.append('min_quantity', document.getElementById('invMinQty').value);

    try {
        const res = await fetch('api/inventory.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            toolToast(json.message);
            closeModal();
            setTimeout(() => window.location.reload(), 600);
        } else {
            toolToast(json.message, 'error');
        }
    } catch (e) {
        console.error(e);
        toolToast('Erro ao salvar item', 'error');
    }
}

async function deleteItem(id) {
    if (!confirm("Tem certeza que deseja excluir este item do estoque? Esta ação não pode ser desfeita.")) return;
    try {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        const res = await fetch('api/inventory.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            toolToast('Item excluído com sucesso.');
            setTimeout(() => window.location.reload(), 600);
        } else {
            toolToast(json.message, 'error');
        }
    } catch (e) {
        toolToast('Erro ao excluir', 'error');
    }
}

// ---------------------------------------------------------
// MODAL DE SAÍDA (USO)
// ---------------------------------------------------------

function openExitModal(item_id, name, maxQty) {
    document.getElementById('exitItemId').value = item_id;
    document.getElementById('exitItemName').innerText = name;
    document.getElementById('exitMaxQty').innerText = `Max: ${maxQty}`;

    // reset form
    document.getElementById('exitQty').value = 1;
    document.getElementById('exitQty').max = maxQty;
    document.getElementById('exitUser').value = '';
    document.getElementById('exitDesc').value = '';

    document.getElementById('exitModal').classList.add('open');
}

function closeExitModal() {
    document.getElementById('exitModal').classList.remove('open');
}

async function registerExit() {
    const itemId = document.getElementById('exitItemId').value;
    const qty = parseInt(document.getElementById('exitQty').value);
    const user = document.getElementById('exitUser').value.trim();
    const desc = document.getElementById('exitDesc').value.trim();

    if (qty <= 0) { toolToast('Quantidade deve ser maior que zero.', 'error'); return; }
    if (!user) { toolToast('Informe quem está retirando o item.', 'error'); return; }

    const fd = new FormData();
    fd.append('action', 'register_exit');
    fd.append('item_id', itemId);
    fd.append('quantity_used', qty);
    fd.append('user_name', user);
    fd.append('description', desc);

    try {
        const res = await fetch('api/inventory.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            toolToast('Saída registrada!');
            closeExitModal();
            setTimeout(() => window.location.reload(), 600);
        } else {
            toolToast(json.message, 'error');
        }
    } catch (e) {
        toolToast('Erro de conexão ao registrar', 'error');
    }
}

// ---------------------------------------------------------
// MODAL DE ENTRADA (COMPRA/REPOSIÇÃO)
// ---------------------------------------------------------

function openEntryModal(item_id, name) {
    document.getElementById('entryItemId').value = item_id;
    document.getElementById('entryItemName').innerText = name;

    // reset form
    document.getElementById('entryQty').value = 1;
    document.getElementById('entrySupplier').value = '';
    document.getElementById('entryDesc').value = '';

    document.getElementById('entryModal').classList.add('open');
}

function closeEntryModal() {
    document.getElementById('entryModal').classList.remove('open');
}

async function registerEntry() {
    const itemId = document.getElementById('entryItemId').value;
    const qty = parseInt(document.getElementById('entryQty').value);
    const supplier = document.getElementById('entrySupplier').value.trim();
    const desc = document.getElementById('entryDesc').value.trim();

    if (qty <= 0) { toolToast('Quantidade deve ser maior que zero.', 'error'); return; }

    const fd = new FormData();
    fd.append('action', 'register_entry');
    fd.append('item_id', itemId);
    fd.append('quantity_added', qty);
    fd.append('supplier', supplier);
    fd.append('description', desc);

    try {
        const res = await fetch('api/inventory.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            toolToast(json.message);
            closeEntryModal();
            setTimeout(() => window.location.reload(), 600);
        } else {
            toolToast(json.message, 'error');
        }
    } catch (e) {
        toolToast('Erro ao registrar saída.', 'error');
    }
}

// ---------------------------------------------------------
// COMPORTAMENTO DAS ABAS
// ---------------------------------------------------------
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('[id^="tab"]').forEach(t => t.style.display = 'none');

    if (btn) {
        btn.classList.add('active');
    }

    if (tab === 'reports') {
        document.getElementById('tabReports').style.display = 'block';
    } else {
        document.getElementById('tabEstoque').style.display = 'block';
        loadInventory();
    }
}

// ---------------------------------------------------------
// RELATÓRIOS E COMPRAS (PRINT)
// ---------------------------------------------------------

function printPurchaseOrder() {
    const chks = document.querySelectorAll('.chk-compra:checked');
    if (chks.length === 0) {
        toolToast('Selecione pelo menos um item para comprar.', 'error');
        return;
    }

    // Monta dados via query string ou localStorage para a página de impressão
    const items = [];
    chks.forEach(chk => {
        const id = chk.value;
        const name = chk.getAttribute('data-name');
        const cat = chk.getAttribute('data-cat');
        const qty = document.getElementById(`req_qty_${id}`).value;
        const obsEl = document.getElementById(`req_obs_${id}`);
        const obs = obsEl ? obsEl.value : '';
        items.push({ id, name, cat, qty, obs });
    });

    sessionStorage.setItem('print_purchase_data', JSON.stringify(items));
    window.open('print_purchase.php', '_blank');
}

function printExitsReport() {
    window.open('print_exits.php', '_blank');
}
