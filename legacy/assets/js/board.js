// =============================================================
// GRUPO FLEX KANBAN — board.js
// =============================================================

// Captura erros JS silenciosos e mostra alert visível
window.onerror = function (msg, src, line, col, err) {
    alert('Erro JS [' + line + ']: ' + msg + '\n' + (err ? err.stack : ''));
    return false;
};

// Prazo em dias úteis por categoria
const CAT_PRAZOS = { cartao: 4, tag: 5, pos: 10, rastreador: 10 };

let state = {
    columns: [],
    cards: {},        // { columnId: [cards] }
    currentCard: null,
    currentColumnId: null,
    editingColumnId: null,
    dragCardId: null,
    dragFromColId: null,
    dragColId: null,  // coluna sendo arrastada
    currentCategory: localStorage.getItem('boardCategory') || 'cartao',
};

// =============================================================
// INIT
// =============================================================
document.addEventListener('DOMContentLoaded', () => {
    // Sincronizar seletor no header
    const filter = document.getElementById('boardCategoryFilter');
    if (filter) filter.value = state.currentCategory;

    loadBoard();
    setInterval(loadBoard, 30000); // Auto-refresh a cada 30 segundos
    
    // Disparar Web Cron silenciosamente
    runWebCron();
    setInterval(runWebCron, 600000); // Tenta rodar a cada 10 minutos (servidor controla)

    setupTabs();
    document.getElementById('cardDeadline').addEventListener('change', function () {
        showDeadlineAlert(this.value);
    });
    document.getElementById('cardCategory').addEventListener('change', onCategoryChange);
});

async function loadBoard() {
    // Parar o polling silencioso se o usuário estiver movendo ou editando
    const modal = document.getElementById('cardModal');
    if (state.dragCardId || state.dragColId || (modal && modal.style.display === 'flex')) {
        return;
    }

    const cat = state.currentCategory;
    const [colRes, cardRes] = await Promise.all([
        api(`api/columns.php?action=list&category=${cat}`),
        api(`api/cards.php?action=list&category=${cat}`)
    ]);

    state.columns = colRes.columns || [];
    const allCards = cardRes.cards || [];

    state.cards = {};
    state.columns.forEach(c => state.cards[c.id] = []);
    allCards.forEach(card => {
        if (state.cards[card.column_id]) {
            state.cards[card.column_id].push(card);
        }
    });

    renderBoard();
    populateColumnSelect();
}

function changeBoardCategory(cat) {
    state.currentCategory = cat;
    localStorage.setItem('boardCategory', cat);
    // Mostrar loading
    const wrap = document.getElementById('boardWrap');
    if (wrap) {
        wrap.querySelectorAll('.column').forEach(el => el.style.opacity = '0.4');
    }
    loadBoard();
}

// =============================================================
// RENDER BOARD
// =============================================================
function renderBoard() {
    const wrap = document.getElementById('boardWrap');
    const addBtn = document.getElementById('addColBtn');
    wrap.querySelectorAll('.column').forEach(el => el.remove());
    state.columns.forEach(col => {
        const colEl = createColumnElement(col);
        wrap.insertBefore(colEl, addBtn);
    });
    state.columns.forEach(col => {
        const badge = wrap.querySelector(`[data-col="${col.id}"] .col-badge`);
        if (badge) badge.textContent = (state.cards[col.id] || []).length;
    });
}

function createColumnElement(col) {
    const cards = state.cards[col.id] || [];
    const div = document.createElement('div');
    div.className = 'column';
    div.dataset.col = col.id;

    const isAdmin = SESSION.userRole === 'admin';

    if (isAdmin) {
        div.draggable = true;
        div.addEventListener('dragstart', (e) => onColDragStart(e, col.id));
        div.addEventListener('dragend', () => onColDragEnd());
        div.addEventListener('dragover', (e) => { e.preventDefault(); e.stopPropagation(); });
        div.addEventListener('dragenter', (e) => {
            if (state.dragColId && state.dragColId !== col.id) {
                e.preventDefault(); e.stopPropagation();
                div.classList.add('col-drag-over');
            }
        });
        div.addEventListener('dragleave', (e) => {
            if (!div.contains(e.relatedTarget)) div.classList.remove('col-drag-over');
        });
        div.addEventListener('drop', (e) => onColDrop(e, col.id));
    }

    div.innerHTML = `
        <div class="column-header" style="--col-color:${col.color}">
          <div class="col-header-left">
            ${isAdmin ? `<span class="material-icons-round col-icon" style="font-size:14px;opacity:.6;cursor:grab" title="Arraste para reordenar">drag_indicator</span>` : ''}
            <span class="material-icons-round col-icon">${col.icon || 'label'}</span>
            <span class="col-title">${esc(col.name)}</span>
          </div>
          <div style="display:flex;align-items:center;gap:6px">
            <span class="col-badge">${cards.length}</span>
            ${isAdmin ? `
            <div class="col-actions">
              <button class="col-btn" onclick="openEditColumnModal(${col.id})" title="Editar coluna">
                <span class="material-icons-round">edit</span>
              </button>
            </div>` : ''}
          </div>
        </div>
        <div class="column-body" id="col-body-${col.id}"
             ondragover="onDragOver(event, ${col.id})"
             ondrop="onDrop(event, ${col.id})"
             ondragenter="onDragEnter(event, ${col.id})"
             ondragleave="onDragLeave(event, ${col.id})">
        </div>
        <div class="column-footer">
          <button class="btn-add-card" onclick="openNewCardModal(${col.id})">
            <span class="material-icons-round">add</span> Adicionar card
          </button>
        </div>
    `;

    const body = div.querySelector(`#col-body-${col.id}`);
    cards.sort((a, b) => a.position - b.position).forEach(card => {
        body.appendChild(createCardElement(card));
    });
    return div;
}

function createCardElement(card) {
    const div = document.createElement('div');
    div.className = `card priority-${card.priority || 'media'}`;
    div.dataset.cardId = card.id;
    div.draggable = true;

    div.ondragstart = (e) => onDragStart(e, card.id, card.column_id);
    div.ondragend = (e) => onDragEnd(e);
    div.onclick = () => openCardModal(card.id);

    const today = new Date().toISOString().split('T')[0];
    let deadlineHtml = '';
    if (card.deadline) {
        const isOverdue = card.deadline < today && card.deadline_met === null;
        const du = calcularDiasUteis(card.deadline);
        let label, cls;
        if (card.deadline_met == 1) { cls = 'tag-ok'; label = '✓ Cumprido'; }
        else if (isOverdue) { cls = 'overdue'; label = '⚠ Vencido'; div.classList.add('deadline-overdue'); }
        else if (du <= 2) { cls = 'tag-warn'; label = `${du}du`; }
        else { cls = ''; label = `${du}du`; }
        deadlineHtml = `<span class="card-deadline ${cls}"><span class="material-icons-round">${isOverdue ? 'warning' : 'event'}</span>${label}</span>`;
    }

    const catMap = { cartao: 'cartão', tag: 'tag', pos: 'pos', rastreador: 'rastreador' };
    const catBadge = `<span class="cat-badge cat-${card.category || 'cartao'}">${catMap[card.category] || card.category}</span>`;

    // Extra data preview (first item)
    let extraHtml = '';
    if (card.extra_data) {
        try {
            const items = JSON.parse(card.extra_data);
            if (items && items.length) {
                const first = items[0];
                if (first.placa) extraHtml += `<span class="card-tag"><span class="material-icons-round">directions_car</span>${esc(first.placa)}</span>`;
                if (first.serial) extraHtml += `<span class="card-tag"><span class="material-icons-round">qr_code</span>${esc(first.serial)}</span>`;
                if (items.length > 1) extraHtml += `<span class="card-tag">+${items.length - 1}</span>`;
            }
        } catch (e) { }
    }

    const remessaHtml = card.remessa ? `<span class="card-tag"><span class="material-icons-round">inventory_2</span>${esc(card.remessa)}</span>` : '';
    const companyHtml = card.company_name ? `<span class="card-tag"><span class="material-icons-round">business</span>${esc(card.company_name)}</span>` : '';
    const isPresencial = (card.pos_request_type === 'Retirada Presencial');
    
    let trackingHtml = '';
    if (!isPresencial) {
        if (card.tracking_code) {
            trackingHtml += `<span class="card-tag" style="background:#E3F2FD; color:#1565C0; border: 1px solid #BBDEFB;"><span class="material-icons-round">local_shipping</span>${esc(card.tracking_code)}</span>`;
        }
        if (card.tracking_status) {
            trackingHtml += `<span class="card-tag tag-ok" style="margin-left: 4px;"><span class="material-icons-round">radar</span>Rastreado</span>`;
        }
    }

    const withdrawalHtml = (card.category !== 'pos' && isPresencial) 
        ? `<span class="card-tag" style="background:#fff3cd; color:#856404; border:1px solid #ffeeba; font-size:0.7rem; font-weight:bold;">RETIRADA PRESENCIAL</span>` 
        : '';

    div.innerHTML = `
        <div style="display:flex;align-items:center;gap:5px;margin-bottom:4px">${catBadge} ${withdrawalHtml}</div>
        <div class="card-title">${esc(card.title)}</div>
        <div class="card-meta">${extraHtml}${remessaHtml}${companyHtml}${trackingHtml}</div>
        <div class="card-footer">
          ${deadlineHtml}
          <span class="card-comment-count" title="${card.comments_count || 0} comentário(s)"><span class="material-icons-round">comment</span>${card.comments_count > 0 ? card.comments_count : '—'}</span>
        </div>
    `;
    return div;
}

// =============================================================
// DRAG & DROP (cards)
// =============================================================
function onDragStart(e, cardId, colId) {
    e.stopPropagation();
    state.dragCardId = cardId;
    state.dragFromColId = colId;
    e.dataTransfer.effectAllowed = 'move';
    document.querySelector(`[data-card-id="${cardId}"]`)?.classList.add('dragging');
}
function onDragEnd(e) {
    if (e && e.stopPropagation) e.stopPropagation();
    document.querySelectorAll('.card.dragging').forEach(c => c.classList.remove('dragging'));
    document.querySelectorAll('.column.drag-over').forEach(c => c.classList.remove('drag-over'));
}
function onDragOver(e, colId) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; }
function onDragEnter(e, colId) { document.querySelector(`[data-col="${colId}"]`)?.classList.add('drag-over'); }
function onDragLeave(e, colId) {
    const col = document.querySelector(`[data-col="${colId}"]`);
    if (col && !col.contains(e.relatedTarget)) col.classList.remove('drag-over');
}
async function onDrop(e, targetColId) {
    e.preventDefault();
    if (state.dragColId) return; // ignorar se for drag de coluna

    const cardId = state.dragCardId;
    const fromCol = state.dragFromColId;
    if (!cardId || targetColId === fromCol) return;

    const card = (state.cards[fromCol] || []).find(c => c.id == cardId);
    if (!card) return;
    state.cards[fromCol] = state.cards[fromCol].filter(c => c.id != cardId);
    if (!state.cards[targetColId]) state.cards[targetColId] = [];
    card.column_id = targetColId;
    state.cards[targetColId].push(card);
    renderBoard();

    const form = new FormData();
    form.append('action', 'move');
    form.append('id', cardId);
    form.append('column_id', targetColId);
    form.append('position', state.cards[targetColId].length);
    await api('api/cards.php', 'POST', form);

    // Libera a trava de drag para que o loadBoard() não aborte
    state.dragCardId = null;

    await loadBoard(); // Força o refresh da tela para mostrar atualizações de status
    toast('Card movido!', 'success');
}

// =============================================================
// DRAG DE COLUNAS (admin)
// =============================================================
function onColDragStart(e, colId) {
    state.dragColId = colId;
    state.dragCardId = null;
    e.dataTransfer.effectAllowed = 'move';
    document.querySelector(`[data-col="${colId}"]`)?.classList.add('col-dragging');
}
function onColDragEnd() {
    state.dragColId = null;
    document.querySelectorAll('.column.col-dragging').forEach(el => el.classList.remove('col-dragging'));
    document.querySelectorAll('.column.col-drag-over').forEach(el => el.classList.remove('col-drag-over'));
}
async function onColDrop(e, targetColId) {
    e.preventDefault(); e.stopPropagation();
    const fromColId = state.dragColId;
    if (!fromColId || fromColId === targetColId) { onColDragEnd(); return; }

    const fromIdx = state.columns.findIndex(c => c.id == fromColId);
    const toIdx = state.columns.findIndex(c => c.id == targetColId);
    if (fromIdx === -1 || toIdx === -1) { onColDragEnd(); return; }

    const [moved] = state.columns.splice(fromIdx, 1);
    state.columns.splice(toIdx, 0, moved);
    renderBoard();
    onColDragEnd();

    const form = new FormData();
    form.append('action', 'reorder');
    form.append('order', JSON.stringify(state.columns.map(c => c.id)));
    await api('api/columns.php', 'POST', form);
    toast('Ordem das colunas salva!', 'success');
}

// =============================================================
// MODAL DE CARD
// =============================================================
async function openCardModal(cardId) {
    const res = await api(`api/cards.php?action=get&id=${cardId}`);
    if (!res.success) return;
    const card = res.card;
    state.currentCard = card;

    document.getElementById('cardModalTitle').textContent = 'Editar Solicitação';
    document.getElementById('btnDeleteCard').style.display = 'flex';
    document.getElementById('btnArchiveCard').style.display = 'flex';
    document.getElementById('cardTitle').value = card.title || '';
    document.getElementById('cardDescription').value = card.description || '';
    document.getElementById('cardCategory').value = card.category || 'cartao';
    document.getElementById('cardRemessa').value = card.remessa || '';
    document.getElementById('cardCompany').value = card.company_name || '';
    document.getElementById('cardEmail').value = card.client_email || '';
    document.getElementById('cardCnpj').value = card.cnpj || '';

    // POS handling
    document.getElementById('posRequestType').value = card.pos_request_type || '';
    document.getElementById('posReason').value = card.pos_reason || '';
    document.getElementById('reverseTrackingCode').value = card.reverse_tracking_code || '';

    // Declaration handling
    const decGroup = document.getElementById('declarationGroup');
    const decPreview = document.getElementById('declarationPreview');
    const decStatus = document.getElementById('declarationStatus');
    const decLink = document.getElementById('declarationLink');
    if (decGroup) {
        if (card.pos_request_type === 'Retirada Presencial') {
            decGroup.style.display = 'block';
            if (card.withdrawal_declaration) {
                decPreview.style.display = 'block';
                decLink.dataset.filename = card.withdrawal_declaration;
                decLink.onclick = () => handleDeclarationClick(decLink);
                decStatus.textContent = '(Já possuí arquivo)';
            } else {
                decPreview.style.display = 'none';
                decStatus.textContent = '(Arquivo não enviado)';
            }
        } else {
            decGroup.style.display = 'none';
        }
    }
    const decFile = document.getElementById('cardDeclaration');
    if (decFile) decFile.value = '';

    // Address handling
    try {
        if (card.address && card.address.startsWith('{')) {
            const addr = JSON.parse(card.address);
            document.getElementById('cardCep').value = addr.cep || '';
            document.getElementById('cardAddress').value = addr.logradouro || '';
            document.getElementById('cardAddressNumber').value = addr.numero || '';
            document.getElementById('cardNeighborhood').value = addr.bairro || '';
            document.getElementById('cardComplement').value = addr.complemento || '';
            document.getElementById('cardCityState').value = addr.cidade_uf || '';
        } else {
            document.getElementById('cardCep').value = '';
            document.getElementById('cardAddress').value = card.address || '';
            document.getElementById('cardAddressNumber').value = '';
            document.getElementById('cardNeighborhood').value = '';
            document.getElementById('cardComplement').value = '';
            document.getElementById('cardCityState').value = '';
        }
    } catch (e) {
        document.getElementById('cardAddress').value = card.address || '';
    }

    document.getElementById('cardDeadline').value = card.deadline || '';
    document.getElementById('cardPriority').value = card.priority || 'media';
    document.getElementById('cardTracking').value = card.tracking_code || '';
    document.getElementById('cardIsCompleted').checked = (card.deadline_met == 1);
    document.getElementById('cardColumn').value = card.column_id;

    // Trigger UI updates
    onCategoryChange();

    // Limpar o rastreio anterior para não aparecer histórico de outro card
    const tr = document.getElementById('trackingResult');
    if (tr) tr.innerHTML = '';

    // Rastreio atual
    const tDiv = document.getElementById('currentTrackingStatus');
    let trackingHtml = card.tracking_status
        ? `<div class="deadline-alert deadline-ok"><span class="material-icons-round">radar</span>${esc(card.tracking_status)}</div>`
        : '';

    tDiv.innerHTML = trackingHtml;
    
    // Ações Integradas Correios
    const corrDiv = document.getElementById('correiosActions');
    const isPresencial = (card.pos_request_type === 'Retirada Presencial');
    
    if (corrDiv) {
        if (card.correios_prepost_id || isPresencial || card.category === 'pos' || card.category === 'rastreador') {
            corrDiv.style.display = 'none';
        } else {
            corrDiv.style.display = 'block';
            corrDiv.innerHTML = `
                <div class="section-label" style="font-size:0.75rem; color:var(--text-muted); margin-bottom:8px">INTEGRAÇÃO CORREIOS</div>
                <button type="button" class="btn btn-teal" id="btnGenPrePost" onclick="generateCorreiosPrePost(${card.id})" style="width:100%; font-size:0.85rem">
                    <span class="material-icons-round">cloud_sync</span> Gerar Pré-Postagem (SEDEX)
                </button>
            `;
        }
    }

    showDeadlineAlert(card.deadline);
    renderCategoryFields(card.category || 'cartao', card.extra_data_decoded);
    renderComments(card.comments || []);
    switchTab('dados');
    openModal('cardModal');

    if (document.querySelector('.tab-btn[data-tab="historico"]')?.classList.contains('active')) {
        loadCardHistory();
    }
}

async function loadCardHistory() {
    if (!state.currentCard) return;
    const histList = document.getElementById('historyList');
    histList.innerHTML = '<div style="text-align:center;padding:20px"><span class="spinner" style="width:20px;height:20px;border-width:2px;border-color:var(--primary) transparent var(--primary) transparent"></span></div>';

    const res = await api(`api/cards.php?action=history&id=${state.currentCard.id}`);

    if (!res.success) {
        histList.innerHTML = '<div style="text-align:center;color:#999;padding:20px">Erro ao carregar histórico</div>';
        return;
    }

    if (!res.history || res.history.length === 0) {
        histList.innerHTML = '<div style="text-align:center;color:#999;padding:20px">Nenhum histórico encontrado</div>';
        return;
    }

    histList.innerHTML = res.history.map(h => `
        <div class="comment-item" style="padding: 12px; border-bottom: 1px solid var(--border); background: #fafafa; margin-bottom: 8px; border-radius: 8px;">
            <div style="font-size: 0.8rem; color: #666; display: flex; justify-content: space-between; margin-bottom: 6px;">
                <strong style="display:flex;align-items:center;gap:4px;color:var(--primary-dark)">
                    <span class="material-icons-round" style="font-size: 16px;">account_circle</span> 
                    ${esc(h.user_name || 'Sistema')}
                </strong>
                <span>${new Date(h.created_at).toLocaleString('pt-BR')}</span>
            </div>
            <div style="font-size: 0.95rem; color: #333; margin-top: 4px; display: flex; align-items: center; gap: 6px;">
                ${h.action.includes('Criou') ? '<span class="material-icons-round" style="font-size:16px;color:#2E7D32">add_circle</span>' : ''}
                ${h.action.includes('Moveu') ? '<span class="material-icons-round" style="font-size:16px;color:#1565C0">arrow_forward</span>' : ''}
                ${h.action.includes('Editou') ? '<span class="material-icons-round" style="font-size:16px;color:#F57F17">edit</span>' : ''}
                ${esc(h.action)}
            </div>
            ${h.old_col_name && h.new_col_name ? `
                <div style="margin-top: 8px; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; color: #555;">
                    <span style="background: #eee; padding: 2px 8px; border-radius: 12px;">${esc(h.old_col_name)}</span>
                    <span class="material-icons-round" style="font-size: 14px;">arrow_right_alt</span>
                    <span style="background: #E0F2F1; color: #00695C; padding: 2px 8px; border-radius: 12px;">${esc(h.new_col_name)}</span>
                </div>
            ` : ''}
        </div>
    `).join('');
}

function openNewCardModal(colId) {
    try {
        state.currentCard = null;
        document.getElementById('cardModalTitle').textContent = 'Nova Solicitação';
        document.getElementById('btnDeleteCard').style.display = 'none';
        ['cardTitle', 'cardDescription', 'cardRemessa', 'cardCompany', 'cardEmail',
            'cardCnpj', 'cardCep', 'cardAddress', 'cardAddressNumber', 'cardNeighborhood', 'cardComplement', 'cardCityState', 'cardDeadline', 'cardTracking', 'posRequestType', 'posReason', 'reverseTrackingCode', 'cardDeclaration'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        const dg = document.getElementById('declarationGroup'); if (dg) dg.style.display = 'none';
        const dp = document.getElementById('declarationPreview'); if (dp) dp.style.display = 'none';
        const ds = document.getElementById('declarationStatus'); if (ds) ds.textContent = '';
        document.getElementById('cardCategory').value = state.currentCategory;
        onCategoryChange(); // Para filtrar modos de solicitação
        document.getElementById('cardPriority').value = 'media';
        document.getElementById('cardIsCompleted').checked = false;
        const tr = document.getElementById('trackingResult'); if (tr) tr.innerHTML = '';
        const cts = document.getElementById('currentTrackingStatus'); if (cts) cts.innerHTML = '';
        const da = document.getElementById('deadlineAlert'); if (da) da.innerHTML = '';
        const cl = document.getElementById('commentList'); if (cl) cl.innerHTML = '';
        const colSel = document.getElementById('cardColumn');
        if (colId && colSel) colSel.value = colId;

        setAutoDeadline(state.currentCategory);
        renderCategoryFields(state.currentCategory, null);
        switchTab('dados');
        openModal('cardModal');
    } catch (e) {
        alert('Erro ao abrir modal: ' + e.message + '\n' + e.stack);
    }
}

function closeCardModal() { closeModal('cardModal'); state.currentCard = null; }

async function saveCard() {
    try {
        const title = document.getElementById('cardTitle').value.trim();
        if (!title) { toast('Título é obrigatório', 'error'); return; }

        const form = new FormData();
        form.append('action', state.currentCard ? 'update' : 'create');
        if (state.currentCard) form.append('id', state.currentCard.id);
        form.append('title', title);
        form.append('description', document.getElementById('cardDescription').value);
        form.append('category', document.getElementById('cardCategory').value);
        form.append('pos_request_type', document.getElementById('posRequestType').value);
        form.append('pos_reason', document.getElementById('posReason').value);
        form.append('remessa', document.getElementById('cardRemessa').value);
        form.append('placa', '');
        form.append('card_number', '');
        form.append('extra_data_json', collectExtraData());
        form.append('company_name', document.getElementById('cardCompany').value);
        form.append('client_email', document.getElementById('cardEmail').value);
        const cnpjVal = document.getElementById('cardCnpj').value;
        form.append('cnpj', cnpjVal);
        const reqType = document.getElementById('posRequestType').value;
        const isPresencial = (reqType === 'Retirada Presencial' || reqType === 'Retirada');

        const addrObj = {
            cep: document.getElementById('cardCep')?.value || '',
            logradouro: document.getElementById('cardAddress')?.value || '',
            numero: document.getElementById('cardAddressNumber')?.value || '',
            bairro: document.getElementById('cardNeighborhood')?.value || '',
            complemento: document.getElementById('cardComplement')?.value || '',
            cidade_uf: document.getElementById('cardCityState')?.value || ''
        };

        // Validação campos obrigatórios para Correios (visto que quase tudo sai via SEDEX)
        // Se for retirada/presencial, não obriga endereço/CNPJ
        if (!title || (!isPresencial && (!cnpjVal || !addrObj.cep || !addrObj.logradouro || !addrObj.numero || !addrObj.bairro || !addrObj.cidade_uf))) {
            toast('Por favor, preencha todos os campos obrigatórios (*)', 'warning');
            return;
        }
        form.append('address_json', JSON.stringify(addrObj));
        form.append('deadline', document.getElementById('cardDeadline').value);
        form.append('priority', document.getElementById('cardPriority').value);
        form.append('tracking_code', document.getElementById('cardTracking').value);
        form.append('reverse_tracking_code', document.getElementById('reverseTrackingCode').value);
        form.append('column_id', document.getElementById('cardColumn').value);
        form.append('is_completed', document.getElementById('cardIsCompleted').checked ? 'true' : 'false');

        const decFile = document.getElementById('cardDeclaration');
        if (decFile && decFile.files.length > 0) {
            form.append('withdrawal_declaration', decFile.files[0]);
        }

        form.append('category', state.currentCategory);
        const res = await api('api/cards.php', 'POST', form);
        if (res.success) {
            toast(state.currentCard ? 'Card atualizado!' : 'Card criado!', 'success');
            closeCardModal();
            loadBoard();
        } else {
            const errMsg = res.message || 'Erro ao salvar card';
            toast(errMsg, 'error');
            console.error('[saveCard] API error:', res);
        }
    } catch (e) {
        alert('Erro em saveCard: ' + e.message + '\n' + e.stack);
    }
}

async function deleteCard() {
    if (!state.currentCard) return;
    if (!confirm('Excluir este card? Esta ação não pode ser desfeita.')) return;
    const form = new FormData();
    form.append('action', 'delete');
    form.append('id', state.currentCard.id);
    const res = await api('api/cards.php', 'POST', form);
    if (res.success) { toast('Card excluído', 'success'); closeCardModal(); loadBoard(); }
}

async function archiveCurrentCard() {
    if (!state.currentCard) return;
    if (!confirm('Deseja arquivar esta solicitação manualmente? Ela sairá do painel principal.')) return;

    const form = new FormData();
    form.append('action', 'archive');
    form.append('id', state.currentCard.id);

    const res = await api('api/cards.php', 'POST', form);
    if (res.success) {
        toast('Solicitação arquivada com sucesso!', 'success');
        closeCardModal();
        loadBoard();
    } else {
        toast(res.message || 'Erro ao arquivar', 'error');
    }
}

async function unarchiveCard(id) {
    if (!confirm('Deseja restaurar este cartão para o painel principal?')) return;

    const form = new FormData();
    form.append('action', 'unarchive');
    form.append('id', id);

    const res = await api('api/cards.php', 'POST', form);
    if (res.success) {
        toast('Cartão restaurado ao painel!', 'success');
        loadBoard();
        openArchiveModal();
    } else {
        toast(res.message || 'Erro ao restaurar', 'error');
    }
}

// =============================================================
// CAMPOS DINÂMICOS POR CATEGORIA
// =============================================================
function onCategoryChange() {
    const cat = document.getElementById('cardCategory').value;

    const posReqGroup = document.getElementById('posRequestTypeGroup');
    const reqTypeSelect = document.getElementById('posRequestType');
    if (posReqGroup && reqTypeSelect) {
        posReqGroup.style.display = 'block';
        const currentVal = reqTypeSelect.value;
        let html = '<option value="">Envio Padrão</option>';
        if (cat === 'pos') {
            html += `
                <option value="Retirada">Retirada</option>
                <option value="Reverso">Reverso</option>
            `;
        } else {
            html += `<option value="Retirada Presencial">Retirada Presencial</option>`;
        }
        reqTypeSelect.innerHTML = html;
        const exists = Array.from(reqTypeSelect.options).some(opt => opt.value === currentVal);
        reqTypeSelect.value = exists ? currentVal : '';
    }

    onPosRequestTypeChange();
    setAutoDeadline(cat);
    renderCategoryFields(cat, null);
}

function onPosRequestTypeChange() {
    const cat = document.getElementById('cardCategory').value;
    const reqType = document.getElementById('posRequestType').value;
    const reasonGroup = document.getElementById('posReasonGroup');
    const revGroup = document.getElementById('reverseTrackingGroup');
    const revLabel = document.getElementById('reverseTrackingLabel');
    const reasonSpan = document.getElementById('posReasonLabelSpan');
    const decGroup = document.getElementById('declarationGroup');
    const addrGroup = document.getElementById('addressFieldsGroup');

    if (reqType === 'Retirada' || reqType === 'Reverso' || reqType === 'Retirada Presencial') {
        if (cat === 'pos' && (reqType === 'Retirada' || reqType === 'Reverso')) {
            if (reasonGroup) {
                reasonGroup.style.display = 'block';
                if (reasonSpan) reasonSpan.textContent = reqType;
            }
        } else {
            if (reasonGroup) { reasonGroup.style.display = 'none'; document.getElementById('posReason').value = ''; }
        }

        if (reqType === 'Reverso' || reqType === 'Retirada') {
            if (revGroup) revGroup.style.display = 'block';
            if (revLabel) revLabel.style.display = 'block';
        } else {
            if (revGroup) { revGroup.style.display = 'none'; document.getElementById('reverseTrackingCode').value = ''; }
            if (revLabel) revLabel.style.display = 'none';
        }

        const tabRastreio = document.querySelector('.tab-btn[data-tab="rastreio"]');
        const corrDiv = document.getElementById('correiosActions');

        if (reqType === 'Retirada Presencial') {
            if (decGroup) decGroup.style.display = 'block';
            if (tabRastreio) tabRastreio.style.display = 'none';
            if (corrDiv) corrDiv.style.display = 'none';
            // Se estiver na aba de rastreio, move para dados
            if (document.getElementById('tab-rastreio').classList.contains('active')) {
                switchTab('dados');
            }
        } else {
            if (decGroup) decGroup.style.display = 'none';
            if (tabRastreio) tabRastreio.style.display = 'flex';
            if (corrDiv) corrDiv.style.display = 'block';
        }

        if (addrGroup) {
            addrGroup.style.display = (reqType === 'Retirada' || reqType === 'Retirada Presencial') ? 'none' : 'block';
        }
    } else {
        if (reasonGroup) { reasonGroup.style.display = 'none'; document.getElementById('posReason').value = ''; }
        if (revGroup) { revGroup.style.display = 'none'; document.getElementById('reverseTrackingCode').value = ''; }
        if (revLabel) revLabel.style.display = 'none';
        if (decGroup) {
            decGroup.style.display = 'none';
            const fileInp = document.getElementById('cardDeclaration');
            if (fileInp) fileInp.value = '';
        }
        if (addrGroup) addrGroup.style.display = 'block';

        // Restaurar aba de rastreio e ações correios se voltar para padrão
        const tabRastreio = document.querySelector('.tab-btn[data-tab="rastreio"]');
        const corrDiv = document.getElementById('correiosActions');
        if (tabRastreio) tabRastreio.style.display = 'flex';
        
        // Só mostra corrDiv se NÃO tiver prepost_id (lógica do openCardModal)
        if (corrDiv) {
            const hasPrePost = state.currentCard && state.currentCard.correios_prepost_id;
            corrDiv.style.display = hasPrePost ? 'none' : 'block';
        }
    }
}

function setAutoDeadline(cat) {
    const n = CAT_PRAZOS[cat] || 5;
    const feriados = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '11-20', '12-25'];
    let cur = new Date(); cur.setHours(0, 0, 0, 0);
    let uteis = 0;
    while (uteis < n) {
        cur.setDate(cur.getDate() + 1);
        const dow = cur.getDay();
        const mmdd = String(cur.getMonth() + 1).padStart(2, '0') + '-' + String(cur.getDate()).padStart(2, '0');
        if (dow !== 0 && dow !== 6 && !feriados.includes(mmdd)) uteis++;
    }
    const val = cur.toISOString().split('T')[0];
    document.getElementById('cardDeadline').value = val;
    showDeadlineAlert(val);
    const hint = document.getElementById('deadlineHint');
    if (hint) hint.textContent = `Prazo automático: ${n} dias úteis`;
}

function renderCategoryFields(cat, existingData) {
    const block = document.getElementById('extraFieldsBlock');
    const configs = {
        cartao: { label: '💳 Placas e Números de Cartão', cols: 'cols-2', fields: ['placa', 'numero_cartao'], labels: ['Placa', 'Nº Cartão'], placeholders: ['Ex: ABC-1234', 'Ex: 4532***'], addLabel: '+ Adicionar placa/cartão' },
        tag: { label: '🏷️ Placas e Números de Tag', cols: 'cols-2', fields: ['placa', 'numero_tag'], labels: ['Placa', 'Nº da Tag'], placeholders: ['Ex: ABC-1234', 'Ex: TAG-001'], addLabel: '+ Adicionar placa/tag' },
        rastreador: { label: '📡 Placas, Serial e ICCID do Chip', cols: 'cols-3', fields: ['placa', 'serial', 'iccid'], labels: ['Placa', 'Serial', 'ICCID'], placeholders: ['Ex: ABC-1234', 'SN-001', '8955...'], addLabel: '+ Adicionar rastreador' },
        pos: { label: '📟 Seriais das máquinas POS', cols: 'cols-3', fields: ['modelo', 'serial', 'iccid'], labels: ['Modelo', 'Serial POS', 'ICCID (Chip)'], placeholders: ['Ex: S920', 'Ex: POS-SN-001', 'Ex: 8955...'], addLabel: '+ Adicionar outro POS' }
    };
    const cfg = configs[cat] || configs.cartao;
    const rows = (existingData && existingData.length) ? existingData : [{}];
    block.innerHTML = `
        <div class="section-label" style="margin-top:4px"><span class="material-icons-round">list</span> ${cfg.label}</div>
        <div id="extraRowsContainer">${rows.map((row, i) => buildExtraRow(cfg, row, i)).join('')}</div>
        <button type="button" class="add-row-btn" onclick="addExtraRow()"><span class="material-icons-round">add</span> ${cfg.addLabel}</button>
        <span class="deadline-auto-hint" id="deadlineHint"><span class="material-icons-round" style="font-size:13px">schedule</span> Prazo automático: ${CAT_PRAZOS[cat]} dias úteis</span>
    `;
    block.dataset.cat = cat;
}

function buildExtraRow(cfg, data, idx) {
    const canRemove = idx > 0;
    const fieldInputs = cfg.fields.map((f, fi) => `
        <div class="form-group" style="margin-bottom:0">
            <label style="font-size:.72rem">${cfg.labels[fi]}</label>
            <input type="text" class="form-control extra-field" data-field="${f}" value="${esc(data[f] || '')}" placeholder="${cfg.placeholders[fi]}">
        </div>`).join('');
    return `<div class="extra-field-row ${cfg.cols}" data-row="${idx}">${fieldInputs}${canRemove ? `<button type="button" class="remove-row-btn" onclick="removeExtraRow(this)" title="Remover">✕</button>` : ''}</div>`;
}

function addExtraRow() {
    const container = document.getElementById('extraRowsContainer');
    const block = document.getElementById('extraFieldsBlock');
    const cat = block.dataset.cat || 'cartao';
    const configs = getCatConfigs();
    const cfg = configs[cat] || configs.cartao;
    const idx = container.querySelectorAll('.extra-field-row').length;
    container.insertAdjacentHTML('beforeend', buildExtraRow(cfg, {}, idx));
}

function removeExtraRow(btn) {
    btn.closest('.extra-field-row').remove();
    document.querySelectorAll('#extraRowsContainer .extra-field-row').forEach((row, i) => {
        const rb = row.querySelector('.remove-row-btn');
        if (i === 0 && rb) rb.remove();
    });
}

function collectExtraData() {
    const rows = document.querySelectorAll('#extraRowsContainer .extra-field-row');
    const result = [];
    rows.forEach(row => {
        const obj = {};
        row.querySelectorAll('.extra-field').forEach(input => { obj[input.dataset.field] = input.value.trim(); });
        if (Object.values(obj).some(v => v !== '')) result.push(obj);
    });
    return result.length ? JSON.stringify(result) : '[]';
}

function getCatConfigs() {
    return {
        cartao: { fields: ['placa', 'numero_cartao'], labels: ['Placa', 'Nº Cartão'], placeholders: ['Ex: ABC-1234', 'Ex: 4532***'], cols: 'cols-2', addLabel: '+ Adicionar placa/cartão' },
        tag: { fields: ['placa', 'numero_tag'], labels: ['Placa', 'Nº da Tag'], placeholders: ['Ex: ABC-1234', 'Ex: TAG-001'], cols: 'cols-2', addLabel: '+ Adicionar placa/tag' },
        rastreador: { fields: ['placa', 'serial', 'iccid'], labels: ['Placa', 'Serial', 'ICCID'], placeholders: ['Ex: ABC-1234', 'SN-001', '8955...'], cols: 'cols-3', addLabel: '+ Adicionar rastreador' },
        pos: { fields: ['modelo', 'serial', 'iccid'], labels: ['Modelo', 'Serial POS', 'ICCID (Chip)'], placeholders: ['Ex: S920', 'Ex: POS-SN-001', 'Ex: 8955...'], cols: 'cols-3', addLabel: '+ Adicionar outro POS' },
    };
}

// =============================================================
// RASTREIO
// =============================================================
async function checkTracking() {
    const code = document.getElementById('cardTracking').value.trim().toUpperCase();
    const cardId = state.currentCard?.id || 0;
    const btn = document.getElementById('btnTrack');
    const result = document.getElementById('trackingResult');
    if (!code) { toast('Informe o código de rastreio', 'warning'); return; }
    btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px"></span>';
    btn.disabled = true;
    result.innerHTML = '';
    const form = new FormData();
    form.append('action', 'track'); form.append('code', code); form.append('card_id', cardId);
    const res = await api('api/tracking.php', 'POST', form);
    btn.innerHTML = '<span class="material-icons-round">search</span> Rastrear';
    btn.disabled = false;
    if (!res.success) {
        result.innerHTML = `<div class="deadline-alert deadline-overdue"><span class="material-icons-round">error</span>${res.message || 'Erro ao consultar rastreio'}</div>`;
        return;
    }
    const obj = res.data?.objetos?.[0] || {};
    const eventos = obj.eventos || [];
    if (!eventos.length) {
        result.innerHTML = `<div class="deadline-alert deadline-warn"><span class="material-icons-round">info</span>Nenhum evento encontrado.</div>`;
        return;
    }
    const formatDt = (d) => {
        if (!d) return '';
        const parts = d.split('T');
        if (parts.length === 2) {
            return parts[0].split('-').reverse().join('/') + ' às ' + parts[1];
        }
        return d;
    };

    let forecastHtml = '';
    if (obj.dtPrevista) {
        const datePart = obj.dtPrevista.split('T')[0];
        const fDate = datePart.split('-').reverse().join('/');
        forecastHtml = `
            <div class="deadline-alert deadline-ok" style="margin-bottom:15px; background:#e3f2fd; color:#1565c0; border:1px solid #bbdefb; display:flex; align-items:center; gap:8px;">
                <span class="material-icons-round">event_available</span>
                <span><strong>Previsão de Entrega:</strong> ${fDate}</span>
            </div>`;
    } else {
        forecastHtml = `
            <div class="deadline-alert" style="margin-bottom:15px; background:#f5f5f5; color:#757575; border:1px solid #e0e0e0; display:flex; align-items:center; gap:8px;">
                <span class="material-icons-round">event_busy</span>
                <span>Data de entrega indisponível</span>
            </div>`;
    }

    let html = forecastHtml + eventos.map((ev, i) => `
        <div class="tracking-event">
          <div class="tracking-dot ${i === 0 ? 'latest' : ''}"></div>
          <div class="tracking-info">
            <div class="tracking-status">${esc(ev.descricao || '')}</div>
            ${ev.detalhe ? `<div class="tracking-detail">${esc(ev.detalhe)}</div>` : ''}
            <div class="tracking-detail">${esc(ev.unidade?.nome || ev.local || '')}</div>
          </div>
          <div class="tracking-date">${esc(formatDt(ev.dtHrCriado || ev.data))}</div>
        </div>`).join('');
    html += `<div style="margin-top:15px;text-align:center"><a href="https://rastreamento.correios.com.br/app/index.php" target="_blank" class="btn btn-secondary">Ver Histórico Completo</a></div>`;
    result.innerHTML = html;
    if (cardId && eventos[0]) {
        const status = eventos[0].descricao + (eventos[0].detalhe ? ' - ' + eventos[0].detalhe : '');
        document.getElementById('currentTrackingStatus').innerHTML = `<div class="deadline-alert deadline-ok"><span class="material-icons-round">radar</span>${esc(status)}</div>`;
    }
    toast('Rastreio atualizado!', 'success');
}

/**
 * Abre o PDF da Etiqueta ou Declaração em uma nova aba
 */
function handleCorreiosPdf(cardId, type) {
    const url = `api/correios_files.php?card_id=${cardId}&type=${type}`;
    window.open(url, '_blank');
}

/**
 * Gera a Pré-Postagem manualmente
 */
async function generateCorreiosPrePost(cardId) {
    const btn = document.getElementById('btnGenPrePost');
    if (!btn) return;
    
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px"></span> Gerando...';
    
    try {
        const form = new FormData();
        form.append('action', 'generate_prepost');
        form.append('id', cardId);
        
        const res = await api('api/cards.php', 'POST', form);
        if (res.success) {
            toast('Pré-postagem gerada!', 'success');
            // Recarregar os dados do card no modal
            openCardModal(cardId);
            loadBoard();
        } else {
            toast(res.message || 'Erro ao gerar pré-postagem', 'error');
        }
    } catch (e) {
        console.error('[generateCorreiosPrePost] Error:', e);
        toast('Erro de conexão', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

async function updateAllTracking() {
    const btn = document.getElementById('btnUpdateTracking');
    btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px"></span> Atualizando...';
    btn.disabled = true;
    const res = await api('api/cards.php?action=list');
    const cardsWithTracking = (res.cards || []).filter(c => c.tracking_code);
    let updated = 0;
    for (const card of cardsWithTracking) {
        const f = new FormData(); f.append('action', 'track'); f.append('code', card.tracking_code); f.append('card_id', card.id);
        await api('api/tracking.php', 'POST', f);
        updated++;
    }
    btn.innerHTML = '<span class="material-icons-round">radar</span> Atualizar Rastreios';
    btn.disabled = false;
    toast(`${updated} rastreio(s) atualizado(s)!`, 'success');
    loadBoard();
}

// =============================================================
// COMENTÁRIOS
// =============================================================
function renderComments(comments) {
    const list = document.getElementById('commentList');
    if (!comments.length) {
        list.innerHTML = '<div class="empty-state"><span class="material-icons-round">forum</span>Nenhum comentário ainda</div>';
        return;
    }
    list.innerHTML = comments.map(c => {
        const isOwner = (String(c.user_id) === String(SESSION.userId)) || (SESSION.userRole === 'admin');
        return `
        <div class="comment" id="comment-${c.id}">
          <div class="comment-avatar">${c.user_name?.[0]?.toUpperCase() || '?'}</div>
          <div class="comment-body">
            <div class="comment-header">
                <div>
                    <span class="comment-author">${esc(c.user_name)}</span>
                    <span class="comment-time">${formatDateTime(c.created_at)}</span>
                </div>
                ${isOwner ? `
                <div class="comment-actions">
                    <button class="btn-icon-sm" onclick="editComment(${c.id})" title="Editar comentário">
                        <span class="material-icons-round" style="font-size:16px">edit</span>
                    </button>
                </div>` : ''}
            </div>
            <div class="comment-text" id="comment-text-${c.id}">${esc(c.content)}</div>
          </div>
        </div>`;
    }).join('');
}

function editComment(id) {
    const textEl = document.getElementById(`comment-text-${id}`);
    if (!textEl) return;
    const original = textEl.innerText;
    textEl.dataset.original = original;
    textEl.innerHTML = `
        <textarea class="form-control" id="edit-textarea-${id}" rows="2" style="margin-top:5px">${esc(original)}</textarea>
        <div style="display:flex; gap:5px; justify-content:flex-end; margin-top:5px">
            <button class="btn btn-sm btn-outline" onclick="cancelEditComment(${id})">Cancelar</button>
            <button class="btn btn-sm btn-teal" onclick="updateComment(${id})">Salvar</button>
        </div>
    `;
}

function cancelEditComment(id) {
    const textEl = document.getElementById(`comment-text-${id}`);
    if (textEl && textEl.dataset.original !== undefined) {
        textEl.textContent = textEl.dataset.original;
    }
}

async function updateComment(id) {
    const text = document.getElementById(`edit-textarea-${id}`).value.trim();
    if (!text) { toast('O comentário não pode estar vazio', 'warning'); return; }
    const f = new FormData();
    f.append('action', 'update');
    f.append('id', id);
    f.append('content', text);
    const res = await api('api/comments.php', 'POST', f);
    if (res.success) {
        toast('Comentário atualizado', 'success');
        if (state.currentCard) {
            const cardRes = await api(`api/cards.php?action=get&id=${state.currentCard.id}`);
            renderComments(cardRes.card?.comments || []);
        }
    } else {
        toast(res.message || 'Erro ao atualizar', 'error');
    }
}

async function saveComment() {
    const text = document.getElementById('newCommentText').value.trim();
    if (!text || !state.currentCard) { toast('Digite um comentário', 'warning'); return; }
    const f = new FormData(); f.append('action', 'create'); f.append('card_id', state.currentCard.id); f.append('content', text);
    const res = await api('api/comments.php', 'POST', f);
    if (res.success) {
        document.getElementById('newCommentText').value = '';
        const cardRes = await api(`api/cards.php?action=get&id=${state.currentCard.id}`);
        renderComments(cardRes.card?.comments || []);
    }
}

// =============================================================
// MODAL DE COLUNA
// =============================================================
function openNewColumnModal() {
    state.editingColumnId = null;
    document.getElementById('columnModalTitle').textContent = 'Nova Coluna';
    document.getElementById('colName').value = '';
    document.getElementById('btnDeleteCol').style.display = 'none';
    selectColor('#00897B');
    openModal('columnModal');
}

function openEditColumnModal(colId) {
    const col = state.columns.find(c => c.id == colId);
    if (!col) return;
    state.editingColumnId = colId;
    document.getElementById('columnModalTitle').textContent = 'Editar Coluna';
    document.getElementById('colName').value = col.name;
    document.getElementById('btnDeleteCol').style.display = 'flex';
    selectColor(col.color);
    openModal('columnModal');
}

function selectColor(color, el = null) {
    document.getElementById('colColor').value = color;
    document.querySelectorAll('.color-swatch').forEach(s => s.style.borderColor = 'transparent');
    
    if (el) {
        el.style.borderColor = 'var(--text-color)';
    } else {
        // Tenta encontrar o swatch correspondente pela cor (case-insensitive)
        const swatches = document.querySelectorAll('.color-swatch');
        let found = false;
        swatches.forEach(s => {
            if (s.dataset.color.toLowerCase() === color.toLowerCase()) {
                s.style.borderColor = 'var(--text-color)';
                found = true;
            }
        });
        // Se não for um dos swatches pré-definidos, atualiza o input de cor customizada
        if (!found) {
            document.getElementById('colColorCustom').value = color;
        }
    }
}

function closeColumnModal() { closeModal('columnModal'); }

async function saveColumn() {
    const name = document.getElementById('colName').value.trim();
    const color = document.getElementById('colColor').value;
    if (!name) { toast('Nome é obrigatório', 'error'); return; }
    const f = new FormData(); f.append('action', state.editingColumnId ? 'update' : 'create');
    if (state.editingColumnId) f.append('id', state.editingColumnId);
    f.append('name', name); f.append('color', color);
    f.append('category', state.currentCategory);
    const res = await api('api/columns.php', 'POST', f);
    if (res.success) { toast('Coluna salva!', 'success'); closeColumnModal(); loadBoard(); }
}

async function deleteColumn() {
    if (!state.editingColumnId) return;
    if (!confirm('Excluir esta coluna?')) return;
    const f = new FormData(); f.append('action', 'delete'); f.append('id', state.editingColumnId);
    const res = await api('api/columns.php', 'POST', f);
    if (res.success) { toast('Coluna excluída', 'success'); closeColumnModal(); loadBoard(); }
}

async function readEmails() {
    const btn = document.querySelector('.btn-nav.btn-secondary');
    btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px"></span> Lendo...';
    btn.disabled = true;
    const f = new FormData(); f.append('action', 'read');
    const res = await api('api/email.php', 'POST', f);
    btn.innerHTML = '<span class="material-icons-round">email</span> Ler E-mails'; btn.disabled = false;
    if (res.success) { toast(res.message, res.criados > 0 ? 'success' : 'info'); if (res.criados > 0) loadBoard(); }
}

// =============================================================
// SENHA
// =============================================================
function openChangePassword() {
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    openModal('passwordModal');
    closeUserMenu();
}
async function savePassword() {
    const p1 = document.getElementById('newPassword').value;
    const p2 = document.getElementById('confirmPassword').value;
    if (p1.length < 6) { toast('Senha curta', 'error'); return; }
    if (p1 !== p2) { toast('Senhas diferentes', 'error'); return; }
    const f = new FormData(); f.append('action', 'change_password'); f.append('id', SESSION.userId); f.append('password', p1);
    const res = await api('api/users.php', 'POST', f);
    if (res.success) { toast('Senha salva!', 'success'); closeModal('passwordModal'); }
}

// =============================================================
// HELPERS
// =============================================================
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

let archiveTableInstance = null;
async function openArchiveModal() {
    openModal('archiveModal');
    const tbody = document.querySelector('#archiveTable tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">Carregando...</td></tr>';
    const res = await api('api/cards.php?action=list_archived');
    const records = res.cards || [];
    if (archiveTableInstance !== null) { archiveTableInstance.destroy(); archiveTableInstance = null; }
    if (tbody) {
        tbody.innerHTML = records.map(c => `
            <tr>
                <td>${esc(c.title)}</td>
                <td>${esc(c.company_name || c.client_name || '-')}</td>
                <td>-</td>
                <td>${esc(c.tracking_code || '-')}</td>
                <td>${esc(c.tracking_status || '-')}</td>
                <td>${formatDate(c.updated_at?.split(' ')[0])}</td>
                <td style="text-align:center"><button class="btn btn-primary-main btn-sm" onclick="unarchiveCard(${c.id})"><span class="material-icons-round" style="font-size:16px">unarchive</span></button></td>
            </tr>`).join('');
    }
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        archiveTableInstance = $('#archiveTable').DataTable({ language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' }, pageLength: 25, order: [[5, 'desc']] });
    }
}
function closeArchiveModal() { closeModal('archiveModal'); }

function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tabName));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.toggle('active', t.id === `tab-${tabName}`));
}
function setupTabs() { document.querySelectorAll('.tab-btn').forEach(btn => btn.addEventListener('click', () => switchTab(btn.dataset.tab))); }
function toggleUserMenu() { document.getElementById('userDropdown').classList.toggle('open'); }
function closeUserMenu() { document.getElementById('userDropdown').classList.remove('open'); }
document.addEventListener('click', (e) => { if (!e.target.closest('.user-menu')) closeUserMenu(); });

function populateColumnSelect() {
    const sel = document.getElementById('cardColumn');
    if (sel) sel.innerHTML = state.columns.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
}
function confirmLogout() { return confirm('Deseja sair?'); }

function showDeadlineAlert(deadline) {
    const el = document.getElementById('deadlineAlert');
    if (!deadline || !el) return;
    const du = calcularDiasUteis(deadline);
    if (du <= 2) el.innerHTML = `<div class="deadline-alert deadline-warn">Prazo em ${du} dia(s) útil(eis)</div>`;
    else el.innerHTML = `<div class="deadline-alert deadline-ok">Prazo em ${du} dias úteis</div>`;
}

function calcularDiasUteis(deadline) {
    if (!deadline) return 0;
    const feriados = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '11-20', '12-25'];
    const hoje = new Date(); hoje.setHours(0,0,0,0);
    const fim = new Date(deadline); fim.setHours(0,0,0,0);
    let uteis = 0, cur = new Date(hoje);
    while (cur <= fim) {
        const dow = cur.getDay();
        const mmdd = String(cur.getMonth() + 1).padStart(2, '0') + '-' + String(cur.getDate()).padStart(2, '0');
        if (dow !== 0 && dow !== 6 && !feriados.includes(mmdd)) uteis++;
        cur.setDate(cur.getDate() + 1);
    }
    return uteis;
}

async function api(url, method = 'GET', body = null) {
    try {
        const opts = { method }; if (body) opts.body = body;
        const res = await fetch(url, opts); return await res.json();
    } catch (e) { return {}; }
}

function toast(msg, type = 'info') {
    const icons = { success: 'check_circle', error: 'error', warning: 'warning', info: 'info' };
    const el = document.createElement('div'); el.className = `toast ${type}`;
    el.innerHTML = `<span class="material-icons-round">${icons[type] || 'info'}</span>${msg}`;
    const container = document.getElementById('toastContainer');
    if (container) { container.appendChild(el); setTimeout(() => el.remove(), 3000); }
}

function esc(str) { return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
function formatDate(d) { if (!d) return ''; const [y, m, day] = d.split('-'); return `${day}/${m}/${y}`; }
function formatDateTime(d) { if (!d) return ''; const dt = new Date(d); return dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }); }

function maskCep(input) {
    let v = input.value.replace(/\D/g, '').slice(0, 8);
    if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
    input.value = v;
}

async function buscarCep(cep) {
    const clean = String(cep).replace(/\D/g, '');
    if (clean.length !== 8) return;
    const response = await fetch(`https://viacep.com.br/ws/${clean}/json/`);
    const data = await response.json();
    if (!data.erro) {
        document.getElementById('cardAddress').value = data.logradouro || '';
        document.getElementById('cardNeighborhood').value = data.bairro || '';
        document.getElementById('cardCityState').value = `${data.localidade || ''} - ${data.uf || ''}`;
    }
}

// =============================================================
// OVERLAY DE DECLARAÇÃO
// =============================================================
function handleDeclarationClick(el) {
    const filename = el.dataset.filename;
    if (filename) openDeclarationOverlay(filename);
}

function openDeclarationOverlay(filename) {
    const url = 'assets/declarations/' + filename;
    const overlay = document.getElementById('declarationOverlay');
    const body = document.getElementById('overlayBody');
    const dl = document.getElementById('overlayDownload');
    const ext = document.getElementById('overlayExternal');
    if (!overlay || !body) return;
    dl.href = url; ext.href = url;
    const fileExt = filename.split('.').pop().toLowerCase();
    const isImg = ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(fileExt);
    if (isImg) body.innerHTML = `<img src="${url}" alt="Declaração" style="max-width:100%; max-height:80vh; border-radius:8px;">`;
    else if (fileExt === 'pdf') body.innerHTML = `<iframe src="${url}" style="width:100%; height:80vh; border:none; border-radius:8px;"></iframe>`;
    else body.innerHTML = `<div style="color:#fff; text-align:center; padding:20px;"><span class="material-icons-round" style="font-size:5rem; display:block; margin-bottom:20px; color:#ccc;">insert_drive_file</span><p>Este arquivo não suporta visualização direta.</p><br><a href="${url}" download class="btn btn-primary-main">Baixar Arquivo</a></div>`;
    overlay.classList.add('active');
}

function closeDeclarationOverlay() {
    const overlay = document.getElementById('declarationOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        const body = document.getElementById('overlayBody');
        if (body) body.innerHTML = '';
    }
}

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDeclarationOverlay(); });
// =============================================================
// ARQUIVAMENTO EM MASSA
// =============================================================
function openBulkArchiveModal() {
    document.getElementById('bulkCategory').value = 'todas';
    document.getElementById('bulkPeriod').value = '';
    openModal('bulkArchiveModal');
}

function closeBulkArchiveModal() {
    closeModal('bulkArchiveModal');
}

async function confirmBulkArchive() {
    const category = document.getElementById('bulkCategory').value;
    const period = document.getElementById('bulkPeriod').value;

    const catLabel = category === 'todas' ? 'todas as categorias' : category.toUpperCase();
    const periodLabel = period ? `do período ${period}` : 'de todo o histórico';

    if (!confirm(`Tem certeza que deseja arquivar TODAS as solicitações de ${catLabel} ${periodLabel}?\n\nEsta ação moverá os cards para a lista de arquivados.`)) {
        return;
    }

    const btn = document.getElementById('btnConfirmBulk');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px"></span> Processando...';

    try {
        const form = new FormData();
        form.append('action', 'bulk_archive');
        form.append('category', category);
        form.append('period', period || 'todos');

        const res = await api('api/cards.php', 'POST', form);
        if (res.success) {
            toast(res.message || 'Arquivamento concluído!', 'success');
            closeBulkArchiveModal();
            loadBoard();
        } else {
            toast(res.message || 'Erro ao processar arquivamento', 'error');
        }
    } catch (e) {
        console.error('[confirmBulkArchive] Error:', e);
        toast('Erro de conexão com o servidor', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}
