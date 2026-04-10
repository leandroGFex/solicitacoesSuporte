// =============================================================
// AUTOMATIONS (REGRAS) UI LOGIC
// =============================================================
function openAutomationsModal() {
    loadAutomationRules();
    showRulesList();
    openModal('automationsModal');
}

function closeAutomationsModal() {
    closeModal('automationsModal');
}

function showRulesList() {
    document.getElementById('automationsViewList').style.display = 'block';
    document.getElementById('automationRuleForm').style.display = 'none';
}

function showRuleForm() {
    document.getElementById('automationsViewList').style.display = 'none';
    document.getElementById('automationRuleForm').style.display = 'block';
}

async function loadAutomationRules() {
    const container = document.getElementById('rulesListContainer');
    container.innerHTML = '<div style="text-align:center;padding:20px"><span class="spinner"></span></div>';
    
    const res = await api(`api/automations.php?action=list&category=${state.currentCategory}`);
    if (!res.success) {
        container.innerHTML = '<div class="alert alert-error">Erro ao carregar regras.</div>';
        return;
    }

    if (res.rules.length === 0) {
        container.innerHTML = '<div style="text-align:center;color:#999;padding:40px;border:2px dashed #eee;border-radius:10px">Nenhuma regra configurada para esta categoria.</div>';
        return;
    }

    container.innerHTML = res.rules.map(rule => `
        <div class="rule-item" style="background:#f8f9fa; border-left:4px solid var(--primary); padding:15px; margin-bottom:12px; border-radius:8px; display:flex; justify-content:space-between; align-items:center">
            <div>
                <strong style="display:block; font-size:1rem; color:var(--primary-dark)">${esc(rule.name)}</strong>
                <div style="font-size:0.85rem; color:#666; margin-top:4px">
                    <span class="material-icons-round" style="font-size:14px; vertical-align:middle">flash_on</span> ${translateTrigger(rule.trigger_event)}
                    <span style="margin:0 8px; color:#ccc">|</span>
                    <span class="material-icons-round" style="font-size:14px; vertical-align:middle">play_arrow</span> ${rule.actions.length} ações
                </div>
            </div>
            <div style="display:flex; gap:10px; align-items:center">
                <div class="form-check form-switch" style="padding:0; margin:0">
                    <input class="form-check-input" type="checkbox" ${rule.is_active == 1 ? 'checked' : ''} onchange="toggleRule(${rule.id}, this.checked)">
                </div>
                <button class="btn btn-ghost btn-sm" onclick='editRule(${JSON.stringify(rule).replace(/'/g, "&#39;")})' title="Editar"><span class="material-icons-round">edit</span></button>
                <button class="btn btn-ghost btn-sm text-danger" onclick="deleteRuleUI(${rule.id})" title="Excluir"><span class="material-icons-round">delete</span></button>
            </div>
        </div>
    `).join('');
}

function translateTrigger(event) {
    const map = {
        'move_to_column': 'Ao mover para coluna',
        'card_created': 'Ao criar card',
        'field_updated': 'Ao atualizar campos'
    };
    return map[event] || event;
}

function openNewRuleForm() {
    state.editingRuleId = null;
    document.getElementById('ruleName').value = '';
    document.getElementById('ruleTriggerEvent').value = 'move_to_column';
    document.getElementById('ruleActionsContainer').innerHTML = '';
    onTriggerEventChange();
    addRuleAction(); // Start with one blank action
    showRuleForm();
}

function onTriggerEventChange() {
    const event = document.getElementById('ruleTriggerEvent').value;
    const group = document.getElementById('triggerConfigGroup');
    
    if (event === 'move_to_column') {
        let options = state.columns.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
        group.innerHTML = `<label>Coluna Destino</label><select class="form-control" id="triggerColId">${options}</select>`;
    } else if (event === 'field_updated') {
        group.innerHTML = `
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px">
                <div><label>Campo</label><select class="form-control" id="triggerFieldName">
                    <option value="title">Título</option>
                    <option value="description">Observação (Descrição)</option>
                    <option value="tracking_status">Status de Rastreio (Correios)</option>
                    <option value="tracking_code">Código de Rastreio</option>
                    <option value="category">Categoria</option>
                </select></div>
                <div><label>Contém Texto</label><input type="text" class="form-control" id="triggerFieldText" placeholder="Palavra-chave (ex: postado)"></div>
            </div>
        `;
    } else {
        group.innerHTML = '';
    }
}

function addRuleAction(type = 'move_to_column', config = {}) {
    const container = document.getElementById('ruleActionsContainer');
    const idx = container.children.length;
    const div = document.createElement('div');
    div.className = 'rule-action-row';
    div.style = "background:#f1f3f5; padding:12px; border-radius:6px; margin-bottom:10px; position:relative";
    
    div.innerHTML = `
        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:15px">
            <div class="form-group" style="margin:0">
                <label>Tipo de Ação</label>
                <select class="form-control action-type" onchange="onActionTypeChange(this)">
                    <option value="move_to_column" ${type === 'move_to_column' ? 'selected' : ''}>Mover para coluna</option>
                    <option value="set_deadline_days" ${type === 'set_deadline_days' ? 'selected' : ''}>Definir prazo (dias úteis)</option>
                    <option value="set_priority" ${type === 'set_priority' ? 'selected' : ''}>Mudar prioridade</option>
                    <option value="add_comment" ${type === 'add_comment' ? 'selected' : ''}>Adicionar comentário</option>
                    <option value="archive" ${type === 'archive' ? 'selected' : ''}>Arquivar card</option>
                </select>
            </div>
            <div class="action-config-group" style="margin:0">
                <!-- Config específica aqui -->
            </div>
        </div>
        <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()" style="position:absolute; top:5px; right:5px">✕</button>
    `;

    container.appendChild(div);
    const typeSelect = div.querySelector('.action-type');
    renderActionConfig(typeSelect, config);
}

function onActionTypeChange(select) {
    renderActionConfig(select, {});
}

function renderActionConfig(select, config) {
    const type = select.value;
    const group = select.closest('.rule-action-row').querySelector('.action-config-group');
    let html = '';

    if (type === 'move_to_column') {
        let opts = state.columns.map(c => `<option value="${c.id}" ${config.column_id == c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('');
        html = `<label>Coluna</label><select class="form-control act-config" data-key="column_id">${opts}</select>`;
    } else if (type === 'set_deadline_days') {
        html = `<label>Dias Úteis (a contar de hoje)</label><input type="number" class="form-control act-config" data-key="days" value="${config.days || 0}" min="0">`;
    } else if (type === 'set_priority') {
        html = `<label>Nível</label><select class="form-control act-config" data-key="priority"><option value="baixa" ${config.priority === 'baixa' ? 'selected' : ''}>Baixa</option><option value="media" ${config.priority === 'media' ? 'selected' : ''}>Média</option><option value="alta" ${config.priority === 'alta' ? 'selected' : ''}>Alta</option></select>`;
    } else if (type === 'add_comment') {
        html = `<label>Texto do comentário</label><input type="text" class="form-control act-config" data-key="text" value="${esc(config.text || '')}" placeholder="Digite a mensagem...">`;
    } else {
        html = '<p style="font-size:0.8rem; color:#666; margin-top:25px">Sem configurações adicionais.</p>';
    }

    group.innerHTML = html;
}

async function saveAutomationRule() {
    const name = document.getElementById('ruleName').value.trim();
    if (!name) return toast('Dê um nome para a regra', 'error');

    const triggerEvent = document.getElementById('ruleTriggerEvent').value;
    let triggerConfig = {};
    if (triggerEvent === 'move_to_column') triggerConfig.column_id = document.getElementById('triggerColId').value;
    else if (triggerEvent === 'field_updated') {
        triggerConfig.field = document.getElementById('triggerFieldName').value;
        triggerConfig.contains = document.getElementById('triggerFieldText').value;
    }

    const actionRows = document.querySelectorAll('.rule-action-row');
    let actions = [];
    actionRows.forEach(row => {
        const type = row.querySelector('.action-type').value;
        let config = {};
        row.querySelectorAll('.act-config').forEach(inp => {
            config[inp.dataset.key] = inp.value;
        });
        actions.push({ type, config });
    });

    if (actions.length === 0) return toast('Adicione pelo menos uma ação', 'error');

    const form = new FormData();
    form.append('action', 'save');
    if (state.editingRuleId) form.append('id', state.editingRuleId);
    form.append('name', name);
    form.append('category', state.currentCategory);
    form.append('trigger_event', triggerEvent);
    form.append('trigger_config', JSON.stringify(triggerConfig));
    form.append('actions', JSON.stringify(actions));

    const res = await api('api/automations.php', 'POST', form);
    if (res.success) {
        toast('Regra salva!', 'success');
        showRulesList();
        loadAutomationRules();
    } else {
        toast(res.message || 'Erro ao salvar', 'error');
    }
}

async function toggleRule(id, active) {
    const res = await api(`api/automations.php?action=toggle&id=${id}&active=${active ? 1 : 0}`);
    if (res.success) toast(active ? 'Regra ativada' : 'Regra desativada', 'success');
}

async function deleteRuleUI(id) {
    if (!confirm('Deseja excluir esta regra de automação?')) return;
    const res = await api(`api/automations.php?action=delete&id=${id}`);
    if (res.success) {
        toast('Regra excluída', 'success');
        loadAutomationRules();
    }
}

function editRule(rule) {
    state.editingRuleId = rule.id;
    document.getElementById('ruleName').value = rule.name;
    document.getElementById('ruleTriggerEvent').value = rule.trigger_event;
    
    onTriggerEventChange();
    const triggerConf = JSON.parse(rule.trigger_config);
    if (rule.trigger_event === 'move_to_column') document.getElementById('triggerColId').value = triggerConf.column_id;
    else if (rule.trigger_event === 'field_updated') {
        document.getElementById('triggerFieldName').value = triggerConf.field;
        document.getElementById('triggerFieldText').value = triggerConf.contains;
    }

    document.getElementById('ruleActionsContainer').innerHTML = '';
    rule.actions.forEach(act => {
        addRuleAction(act.action_type, JSON.parse(act.action_config));
    });

    showRuleForm();
}

// =============================================================
// WEB CRON TRIGGER
// =============================================================
async function runWebCron() {
    try {
        await api('api/web_cron.php');
        console.log('[WebCron] Processado com sucesso.');
    } catch (e) {
        console.error('[WebCron] Erro:', e);
    }
}
