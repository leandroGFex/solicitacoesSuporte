// app.js para Trackers

let allTrackers = [];
let editingId = null;

// Lê a role do usuário logado (definido em data-role no <body> pelo header_tool.php)
const SESSION_ROLE = document.body.dataset.role || 'user';


function statusClass(s) {
    const map = { 'Estoque': 'estoque', 'Enviado': 'enviado', 'Recebido': 'recebido', 'Defeito': 'defeito', 'Em Manutenção': 'manutencao' };
    return map[s] || s.toLowerCase().replace(/[^a-z0-9]/g, '');
}

document.addEventListener('DOMContentLoaded', loadTrackers);

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
    document.getElementById('modalTitle').textContent = isEditing ? 'Editar Rastreador' : 'Cadastrar Rastreador';
    document.getElementById('btnDeleteTracker').style.display = isEditing ? 'inline-flex' : 'none';

    if (isEditing) {
        const tracker = allTrackers.find(p => p.id == id);
        if (tracker) {
            document.getElementById('trackerId').value = tracker.id;
            document.getElementById('trackerModel').value = tracker.model || '';
            document.getElementById('trackerSerial').value = tracker.serial_number;
            document.getElementById('trackerChip').value = tracker.chip_iccid || '';
            document.getElementById('trackerStatus').value = tracker.status;
            document.getElementById('trackerMotivo').value = tracker.motivo || '';
            toggleMotivoTracker(tracker.status);
        }
    } else {
        document.getElementById('trackerId').value = '';
        document.getElementById('trackerModel').value = '';
        document.getElementById('trackerSerial').value = '';
        document.getElementById('trackerChip').value = '';
        document.getElementById('trackerStatus').value = 'Estoque';
        document.getElementById('trackerMotivo').value = '';
        toggleMotivoTracker('Estoque');
    }

    document.getElementById('trackerModal').classList.add('open');
}

function toggleMotivoTracker(status) {
    const show = status === 'Defeito' || status === 'Em Manutenção';
    document.getElementById('trackerMotivGrp').style.display = show ? 'block' : 'none';
}

function closeModal() {
    document.getElementById('trackerModal').classList.remove('open');
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.remove('open');
}

async function loadTrackers() {
    const res = await api('api/trackers.php?action=list');
    if (res.success) {
        allTrackers = res.data;
        renderTable(allTrackers);
    }
}

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const filtered = allTrackers.filter(p =>
        (p.model && p.model.toLowerCase().includes(q)) ||
        p.serial_number.toLowerCase().includes(q) ||
        (p.chip_iccid && p.chip_iccid.toLowerCase().includes(q))
    );
    renderTable(filtered);
}

function renderTable(data) {
    const tbody = document.getElementById('trackerTableBody');
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#888;">Nenhum rastreador encontrado.</td></tr>';
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
            <td><strong>${p.model || '-'}</strong></td>
            <td>${p.serial_number}</td>
            <td>${chipStr}</td>
            <td><span class="status-badge status-${statusClass(p.status)}">${p.status}</span></td>
            <td>${new Date(p.created_at).toLocaleDateString('pt-BR')}</td>
            <td>
                <div class="action-btns">
                    <button class="btn btn-icon" onclick="openHistory(${p.id})" title="Ver Histórico">
                        <span class="material-icons-round">history</span>
                    </button>
                    <button class="btn btn-icon" onclick="openModal(${p.id})" title="Editar">
                        <span class="material-icons-round">edit</span>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

async function saveTracker() {
    const model = document.getElementById('trackerModel').value.trim();
    const serial = document.getElementById('trackerSerial').value.trim();
    const status = document.getElementById('trackerStatus').value;
    const motivo = document.getElementById('trackerMotivo').value.trim();

    if (!serial) {
        toast('Serial é obrigatório!', 'error');
        return;
    }
    if ((status === 'Defeito' || status === 'Em Manutenção') && !motivo) {
        toast('Descreva o motivo/problema para este status!', 'error');
        document.getElementById('trackerMotivo').focus();
        return;
    }

    const form = new FormData();
    form.append('action', editingId ? 'update' : 'create');
    if (editingId) form.append('id', editingId);
    form.append('model', model);
    form.append('serial_number', serial);
    form.append('chip_iccid', document.getElementById('trackerChip').value.trim());
    form.append('status', status);
    form.append('motivo', motivo);

    const res = await api('api/trackers.php', 'POST', form);
    if (res.success) {
        toast(editingId ? 'Rastreador atualizado!' : 'Rastreador cadastrado!', 'success');
        closeModal();
        window.location.reload();
    } else {
        toast(res.message || 'Erro ao salvar', 'error');
    }
}

async function deleteTracker() {
    if (!confirm('Deseja excluir este rastreador? Isso apagará seu histórico!')) return;

    const form = new FormData();
    form.append('action', 'delete');
    form.append('id', editingId);

    const res = await api('api/trackers.php', 'POST', form);
    if (res.success) {
        toast('Rastreador excluído!', 'success');
        closeModal();
        window.location.reload();
    } else {
        toast(res.message || 'Erro ao excluir', 'error');
    }
}

async function openHistory(id) {
    const res = await api(`api/trackers.php?action=history&id=${id}`);
    if (!res.success) return;

    const content = document.getElementById('historyContent');
    if (res.data.length === 0) {
        content.innerHTML = '<p>Nenhum histórico encontrado para este rastreador.</p>';
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

function renderHistoryParams(h) {
    if (!h) return '';
    return h.action + (h.problem_description ? ` - ${h.problem_description}` : '');
}

// =============================================================
// REPORTS & DASHBOARD TAB
// =============================================================
let trackerChartInstance = null;

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

    const res = await api('api/trackers.php?action=reports');
    if (!res.success) {
        toast('Erro ao carregar relatórios', 'error');
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:red;">Falha ao carregar</td></tr>';
        return;
    }

    renderTrackerChart(res.stats);

    if (res.events && res.events.length > 0) {
        tbody.innerHTML = res.events.map(ev => `
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 0.9rem;">${new Date(ev.created_at).toLocaleString('pt-BR')}</td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 0.9rem;">Sistema/Usuário</td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 0.9rem;"><strong>${ev.model || 'Rastreador'}</strong><br><small style="color:#666">${ev.serial_number}</small></td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border); font-size: 0.9rem;">${ev.action} ${ev.problem_description ? `<br><small style="color:#888">${ev.problem_description}</small>` : ''}</td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#888;">Nenhuma movimentação recente</td></tr>';
    }
}

function renderTrackerChart(stats) {
    const ctx = document.getElementById('trackerStatusChart').getContext('2d');
    if (trackerChartInstance) trackerChartInstance.destroy();

    trackerChartInstance = new Chart(ctx, {
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
