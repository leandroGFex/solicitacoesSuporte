// app.js para POS

let allPos = [];
let editingId = null;

function statusClass(s) {
    const map = { 
        'Estoque': 'estoque', 
        'Enviado': 'enviado', 
        'Recebido': 'recebido', 
        'Defeito': 'defeito', 
        'Em Manutenção': 'manutencao',
        'Retirada': 'retirada',
        'Reverso': 'reverso'
    };
    return map[s] || s.toLowerCase().replace(/[^a-z0-9]/g, '');
}

document.addEventListener('DOMContentLoaded', loadPos);

async function api(url, method = 'GET', body = null) {
    const opts = { method };
    if (body) opts.body = body;
    try {
        const res = await fetch(url, opts);
        return await res.json();
    } catch (e) {
        return { success: false, message: 'Erro de conexão API' };
    }
}

function toast(msg, type = 'info') {
    const container = document.getElementById('toastContainer');
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<span class="material-icons-round">${type === 'success' ? 'check_circle' : 'error'}</span> ${msg}`;
    container.appendChild(el);
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(100%)';
        setTimeout(() => el.remove(), 300);
    }, 3000);
}

function openModal(id = null) {
    editingId = id;
    const isEditing = id !== null;
    document.getElementById('modalTitle').textContent = isEditing ? 'Editar POS' : 'Cadastrar POS';
    document.getElementById('btnDeletePos').style.display = isEditing ? 'inline-flex' : 'none';

    if (isEditing) {
        const pos = allPos.find(p => p.id == id);
        if (pos) {
            document.getElementById('posId').value = pos.id;
            document.getElementById('posModel').value = pos.model;
            document.getElementById('posSerial').value = pos.serial_number;
            document.getElementById('posChip').value = pos.chip_iccid || '';
            document.getElementById('posStatus').value = pos.status;
            document.getElementById('posMotivo').value = pos.motivo || '';
            toggleMotivoPOS(pos.status);
        }
    } else {
        document.getElementById('posId').value = '';
        document.getElementById('posModel').value = '';
        document.getElementById('posSerial').value = '';
        document.getElementById('posChip').value = '';
        document.getElementById('posStatus').value = 'Estoque';
        document.getElementById('posMotivo').value = '';
        toggleMotivoPOS('Estoque');
    }

    document.getElementById('posModal').classList.add('open');
}

function toggleMotivoPOS(status) {
    const show = status === 'Defeito' || status === 'Em Manutenção' || status === 'Retirada' || status === 'Reverso';
    document.getElementById('posMotivGrp').style.display = show ? 'block' : 'none';
    
    // Ajustar placeholder se for retirada/reverso
    const motivoInput = document.getElementById('posMotivo');
    if (status === 'Retirada' || status === 'Reverso') {
        motivoInput.placeholder = "Descreva o motivo da retirada ou reverso...";
    } else {
        motivoInput.placeholder = "Descreva o defeito ou motivo da manutenção...";
    }
}

function closeModal() {
    document.getElementById('posModal').classList.remove('open');
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.remove('open');
}

async function loadPos() {
    const res = await api('api/pos.php?action=list');
    if (res.success) {
        allPos = res.data;
        
        // Popular filtros de modelos
        const models = [...new Set(allPos.map(p => p.model))].sort();
        const modelSelects = ['modelFilter', 'repModel'];
        modelSelects.forEach(id => {
            const select = document.getElementById(id);
            if (select) {
                const currentVal = select.value;
                select.innerHTML = '<option value="all">Todos os modelos</option>' + 
                    models.map(m => `<option value="${esc(m)}">${esc(m)}</option>`).join('');
                if (models.includes(currentVal)) select.value = currentVal;
            }
        });

        filterTable(); // Carrega com filtros iniciais (todos)
    }
}

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const model = document.getElementById('modelFilter').value;
    const status = document.getElementById('statusFilter').value;

    const filtered = allPos.filter(p => {
        const matchesSearch = p.model.toLowerCase().includes(q) ||
            p.serial_number.toLowerCase().includes(q) ||
            (p.chip_iccid && p.chip_iccid.toLowerCase().includes(q));
        
        const matchesModel = model === 'all' || p.model === model;
        const matchesStatus = status === 'all' || p.status === status;

        return matchesSearch && matchesModel && matchesStatus;
    });

    renderTable(filtered);

    // Atualizar contador
    const countEl = document.getElementById('filteredCountText');
    if (countEl) {
        if (filtered.length === allPos.length) {
            countEl.textContent = `Total: ${allPos.length} máquinas`;
        } else {
            countEl.textContent = `Exibindo ${filtered.length} de ${allPos.length} máquinas`;
        }
    }
}

function renderTable(data) {
    const tbody = document.getElementById('posTableBody');
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">Nenhuma máquina POS encontrada.</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(p => {
        let chipStr = '<span style="color:#999;font-style:italic;">Nenhum</span>';
        if (p.chip_iccid) {
            let compBadge = '';
            if (p.linked_company) {
                compBadge = `<br><span style="display:inline-block; margin-top:4px; padding:2px 6px; background:#e0f2f1; color:#00695c; font-size:0.75rem; border-radius:4px;"><span class="material-icons-round" style="font-size:10px; vertical-align:middle;">business</span> ${esc(p.linked_company)}</span>`;
            }
            if (p.chip_phone) {
                chipStr = `<strong style="color:var(--primary-dark); font-size:0.85rem;">${esc(p.chip_phone)}</strong><br><small style="color:#666;">${esc(p.chip_carrier)} - ${esc(p.chip_iccid)}</small>${compBadge}`;
            } else {
                chipStr = `<strong style="color:var(--primary-dark); font-size:0.85rem;">ICCID: ${esc(p.chip_iccid)}</strong>${compBadge}`;
            }
        }

        return `
        <tr>
            <td><strong>${p.model}</strong></td>
            <td>${p.serial_number}</td>
            <td>${chipStr}</td>
            <td><span class="status-badge status-${statusClass(p.status)}">${p.status}</span></td>
            <td>${new Date(p.created_at).toLocaleDateString('pt-BR')}</td>
            <td>
                <button class="btn btn-icon" onclick="openModal(${p.id})" title="Editar"><span class="material-icons-round">edit</span></button>
                <button class="btn btn-icon" onclick="openHistory(${p.id})" title="Histórico"><span class="material-icons-round">history</span></button>
            </td>
        </tr>`;
    }).join('');
}

async function savePos() {
    const model = document.getElementById('posModel').value.trim();
    const serial = document.getElementById('posSerial').value.trim();
    const status = document.getElementById('posStatus').value;
    const motivo = document.getElementById('posMotivo').value.trim();

    if (!model || !serial) {
        toast('Modelo e Serial são obrigatórios!', 'error');
        return;
    }
    const needsReason = ['Defeito', 'Em Manutenção', 'Retirada', 'Reverso'].includes(status);
    if (needsReason && !motivo) {
        toast('Descreva o motivo/problema para este status!', 'error');
        document.getElementById('posMotivo').focus();
        return;
    }

    const form = new FormData();
    form.append('action', editingId ? 'update' : 'create');
    if (editingId) form.append('id', editingId);
    form.append('model', model);
    form.append('serial_number', serial);
    form.append('chip_iccid', document.getElementById('posChip').value.trim());
    form.append('status', status);
    form.append('motivo', motivo);

    const res = await api('api/pos.php', 'POST', form);
    if (res.success) {
        toast(editingId ? 'Equipamento atualizado!' : 'Equipamento cadastrado!', 'success');
        closeModal();
        window.location.reload();
    } else {
        toast(res.message || 'Erro ao salvar', 'error');
    }
}

async function deletePos() {
    if (!confirm('Deseja excluir esta máquina? Isso apagará seu histórico!')) return;

    const form = new FormData();
    form.append('action', 'delete');
    form.append('id', editingId);

    const res = await api('api/pos.php', 'POST', form);
    if (res.success) {
        toast('Máquina excluída!', 'success');
        closeModal();
        window.location.reload();
    } else {
        toast(res.message || 'Erro ao excluir', 'error');
    }
}

async function openHistory(id) {
    const res = await api(`api/pos.php?action=history&id=${id}`);
    if (!res.success) return;

    const content = document.getElementById('historyContent');
    if (res.data.length === 0) {
        content.innerHTML = '<p>Nenhum histórico encontrado para esta máquina.</p>';
    } else {
        content.innerHTML = '<ul style="list-style:none; padding:0;">' + res.data.map(h => `
            <li style="margin-bottom: 12px; padding: 12px; border-left: 3px solid #00897B; background: #fafafa; border-radius: 0 8px 8px 0;">
                <div style="font-size: 0.85rem; color: #666; margin-bottom: 4px;">${new Date(h.created_at).toLocaleString('pt-BR')}</div>
                <div style="font-weight: 600; color: #004D40;">Ação: ${h.action}</div>
                ${h.kanban_card_id ? `<div style="font-size: 0.9rem; margin-top: 4px;"><span class="material-icons-round" style="font-size:14px; vertical-align:middle;">view_kanban</span> <a href="../index.php?page=board" style="color:#00897B; text-decoration:none;">Card #${h.kanban_card_id}</a> - ${h.card_title}</div>` : ''}
                ${h.problem_description ? `<div style="font-size: 0.9rem; margin-top: 4px; color: #555;">${h.problem_description}</div>` : ''}
            </li>
        `).join('') + '</ul>';
    }

    document.getElementById('historyModal').classList.add('open');
}

// Para JS ler role
const SESSION_ROLE = document.body.dataset.role || 'user';

// =============================================================
// REPORTS & DASHBOARD TAB
// =============================================================
let posChartInstance = null;

function switchTab(tab) {
    document.getElementById('tabEstoque').style.display = tab === 'estoque' ? 'block' : 'none';
    document.getElementById('tabReports').style.display = tab === 'reports' ? 'block' : 'none';

    document.getElementById('btnTabEstoque').style.background = tab === 'estoque' ? '#fff' : 'transparent';
    document.getElementById('btnTabEstoque').style.color = tab === 'estoque' ? 'var(--primary-dark)' : '#666';
    document.getElementById('btnTabEstoque').style.boxShadow = tab === 'estoque' ? '0 2px 4px rgba(0,0,0,0.1)' : 'none';

    document.getElementById('btnTabReports').style.background = tab === 'reports' ? '#fff' : 'transparent';
    document.getElementById('btnTabReports').style.color = tab === 'reports' ? 'var(--primary-dark)' : '#666';
    document.getElementById('btnTabReports').style.boxShadow = tab === 'reports' ? '0 2px 4px rgba(0,0,0,0.1)' : 'none';

    if (tab === 'reports') {
        loadReports();
    }
}

async function loadReports() {
    const tbody = document.getElementById('reportsHistoryTable');
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;"><div class="spinner"></div></td></tr>';

    const res = await api('api/pos.php?action=reports');
    if (!res.success) {
        toast('Erro ao carregar relatórios', 'error');
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:red;">Falha ao carregar</td></tr>';
        return;
    }

    renderPosChart(res.stats);

    if (res.events && res.events.length > 0) {
        tbody.innerHTML = res.events.map(ev => `
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 0.9rem;">${new Date(ev.created_at).toLocaleString('pt-BR')}</td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 0.9rem;">Sistema/Usuário</td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 0.9rem;"><strong>${ev.model || 'POS'}</strong><br><small style="color:#666">${ev.serial_number}</small></td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 0.9rem;">${ev.action} ${ev.problem_description ? `<br><small style="color:#888">${ev.problem_description}</small>` : ''}</td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#888;">Nenhuma movimentação recente</td></tr>';
    }
}

function renderPosChart(stats) {
    const ctx = document.getElementById('posStatusChart').getContext('2d');
    if (posChartInstance) posChartInstance.destroy();

    posChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Estoque', 'Enviado', 'Recebido', 'Defeito', 'Manutenção'],
            datasets: [{
                data: [stats.Estoque || 0, stats.Enviado || 0, stats.Recebido || 0, stats.Defeito || 0, stats['Em Manutenção'] || 0],
                backgroundColor: ['#00897B', '#1E88E5', '#43A047', '#E53935', '#F57F17'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 8 } }
            }
        }
    });
}

function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
