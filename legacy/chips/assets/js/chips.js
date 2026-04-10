let chipsData = [];

document.addEventListener('DOMContentLoaded', () => {
    loadChips();

    const chipTypeEl = document.getElementById('chipType');
    if (chipTypeEl) {
        chipTypeEl.addEventListener('change', (e) => {
            const lbl = document.getElementById('lblChipPhone');
            if (!lbl) return;
            if (e.target.value === 'Rastreador') {
                lbl.innerHTML = 'Número da Linha *';
            } else {
                lbl.innerHTML = 'Número da Linha <span style="font-weight:normal;color:#999;font-size:0.8rem">(Opcional para POS)</span>';
            }
        });
    }
});

function toggleUserMenu() {
    const d = document.getElementById('userDropdown');
    if (d) d.classList.toggle('open');
}

function closeUserMenu() {
    const d = document.getElementById('userDropdown');
    if (d) d.classList.remove('open');
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.user-menu')) closeUserMenu();
});

function confirmLogout() {
    return confirm('Deseja realmente sair?');
}

async function loadChips() {
    const tbody = document.getElementById('chipsTableBody');
    if (!tbody) return;

    try {
        const res = await fetch('api/chips.php?action=list');
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#e53935;">Erro: ${esc(data.message)}</td></tr>`;
            return;
        }

        chipsData = data.data;
        renderChipsTable(chipsData);
    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#e53935;">Erro ao carregar chips</td></tr>`;
    }
}

function renderChipsTable(list) {
    const tbody = document.getElementById('chipsTableBody');
    if (!tbody) return;

    if (list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:32px; color:#999;">Nenhum chip encontrado</td></tr>`;
        return;
    }

    const badges = {
        'Estoque': 'sbadge-estoque',
        'Em Uso': 'sbadge-enviado',
        'Cancelado': 'sbadge-recebido',
        'Defeito': 'sbadge-defeito',
        'Retirada': 'sbadge-retirada',
        'Reverso': 'sbadge-reverso'
    };

    let html = '';
    list.forEach(eq => {
        let vinculationStr = '<span style="color:#999; font-style:italic;">Nenhum</span>';
        if (eq.linked_equipment) {
            vinculationStr = `<strong style="color:var(--primary-dark); font-size:0.85rem;">${esc(eq.linked_equipment)}</strong>`;
            if (eq.linked_details) vinculationStr += `<br><small style="color:#666;">${esc(eq.linked_details)}</small>`;
        }

        const typeBadgeStr = eq.type === 'Rastreador'
            ? `<span style="background:#F3E5F5; color:#7B1FA2; padding:3px 8px; border-radius:12px; font-size:.75rem; font-weight:600;"><span class="material-icons-round" style="font-size:12px; vertical-align:-2px;">my_location</span> Rastreador</span>`
            : `<span style="background:#E3F2FD; color:#1976D2; padding:3px 8px; border-radius:12px; font-size:.75rem; font-weight:600;"><span class="material-icons-round" style="font-size:12px; vertical-align:-2px;">point_of_sale</span> POS</span>`;

        html += `
        <tr style="border-bottom:1px solid var(--border); transition:background 0.2s;">
            <td style="padding:14px; font-weight:600; color:var(--text);">${esc(eq.phone_number)}</td>
            <td style="padding:14px; font-family:monospace; color:var(--text-muted);">${esc(eq.iccid)}</td>
            <td style="padding:14px;">${typeBadgeStr}</td>
            <td style="padding:14px; color:var(--text-muted);">${esc(eq.carrier || '—')}</td>
            <td style="padding:14px;">${vinculationStr}</td>
            <td style="padding:14px;">
                <span class="sbadge ${badges[eq.status] || ''}">${esc(eq.status)}</span>
            </td>
            <td style="padding:14px; color:var(--text-muted); font-size:.85rem;">${new Date(eq.created_at).toLocaleDateString('pt-BR')}</td>
            <td style="padding:14px;">
                <button class="btn-icon" title="Editar" onclick="editChip(${eq.id})">
                    <span class="material-icons-round" style="color:#1E88E5; font-size:18px;">edit</span>
                </button>
                <button class="btn-icon" title="Histórico" onclick="openHistory(${eq.id}, '${esc(eq.phone_number)}')">
                    <span class="material-icons-round" style="color:var(--primary); font-size:18px;">history</span>
                </button>
            </td>
        </tr>
        `;
    });
    tbody.innerHTML = html;
}

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const t = document.getElementById('filterType').value;
    const s = document.getElementById('filterStatus').value;

    const filtered = chipsData.filter(eq => {
        let matchText = true;
        let matchType = true;
        let matchStatus = true;

        if (q) {
            matchText = (eq.phone_number || '').toLowerCase().includes(q) ||
                (eq.iccid || '').toLowerCase().includes(q) ||
                (eq.carrier || '').toLowerCase().includes(q);
        }

        if (t) {
            matchType = (eq.type === t);
        }

        if (s) {
            matchStatus = (eq.status === s);
        }

        return matchText && matchType && matchStatus;
    });
    renderChipsTable(filtered);
}

function openModal() {
    document.getElementById('chipId').value = '';
    document.getElementById('chipType').value = 'POS';
    document.getElementById('chipPhone').value = '';
    document.getElementById('chipIccid').value = '';
    document.getElementById('chipCarrier').value = '';
    document.getElementById('chipStatus').value = 'Estoque';
    document.getElementById('chipMotivo').value = '';

    document.getElementById('modalTitle').textContent = 'Cadastrar Chip';
    toggleMotivo('Estoque');

    // Dispara a checagem visual da label de tipo
    document.getElementById('chipType').dispatchEvent(new Event('change'));

    const btnDel = document.getElementById('btnDeleteChip');
    if (btnDel) btnDel.style.display = 'none';

    document.getElementById('chipModal').classList.add('open');
}

function editChip(id) {
    const eq = chipsData.find(e => e.id == id);
    if (!eq) return;

    document.getElementById('chipId').value = eq.id;
    document.getElementById('chipType').value = eq.type || 'POS';
    document.getElementById('chipPhone').value = eq.phone_number;
    document.getElementById('chipIccid').value = eq.iccid;
    document.getElementById('chipCarrier').value = eq.carrier || '';
    document.getElementById('chipStatus').value = eq.status;
    document.getElementById('chipMotivo').value = eq.motivo || '';

    document.getElementById('modalTitle').textContent = 'Editar Chip';
    toggleMotivo(eq.status);

    // Dispara a checagem visual da label de tipo
    document.getElementById('chipType').dispatchEvent(new Event('change'));

    const btnDel = document.getElementById('btnDeleteChip');
    // Só admin ou conforme regra? Deixamos visível pra quem pode editar.
    if (btnDel) btnDel.style.display = 'inline-block';

    document.getElementById('chipModal').classList.add('open');
}

function toggleMotivo(status) {
    const grp = document.getElementById('chipMotivGrp');
    if (!grp) return;
    if (status === 'Defeito' || status === 'Cancelado') {
        grp.style.display = 'block';
    } else {
        grp.style.display = 'none';
        document.getElementById('chipMotivo').value = '';
    }
}

function closeModal() {
    document.getElementById('chipModal').classList.remove('open');
}

async function saveChip() {
    const id = document.getElementById('chipId').value;
    const type = document.getElementById('chipType').value;
    const phone = document.getElementById('chipPhone').value.trim();
    const iccid = document.getElementById('chipIccid').value.trim();
    const carrier = document.getElementById('chipCarrier').value.trim();
    const status = document.getElementById('chipStatus').value;
    const motivo = document.getElementById('chipMotivo').value.trim();

    if (type === 'Rastreador' && !phone) {
        toast('O Número da Linha é obrigatório para Rastreadores', 'error');
        return;
    }
    if (!iccid) {
        toast('O ICCID é obrigatório', 'error');
        return;
    }
    if ((status === 'Defeito' || status === 'Cancelado') && !motivo) {
        toast('O motivo é obrigatório para este status', 'error');
        return;
    }

    const form = new FormData();
    form.append('action', id ? 'update' : 'create');
    if (id) form.append('id', id);
    form.append('type', type);
    form.append('phone_number', phone);
    form.append('iccid', iccid);
    form.append('carrier', carrier);
    form.append('status', status);
    form.append('motivo', motivo);

    try {
        const res = await fetch('api/chips.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.success) {
            toast('Chip salvo com sucesso!', 'success');
            closeModal();
            loadChips();
            // Atualizar os contadores recarregando a página pra ficar igual ao original POS
            setTimeout(() => window.location.reload(), 1000);
        } else {
            toast(data.message || 'Erro ao salvar', 'error');
        }
    } catch (err) {
        console.error(err);
        toast('Erro de comunicação', 'error');
    }
}

async function deleteChip() {
    const id = document.getElementById('chipId').value;
    if (!id) return;

    if (!confirm('ATENÇÃO: Deseja realmente excluir este chip permanentemente do sistema?')) return;

    const form = new FormData();
    form.append('action', 'delete');
    form.append('id', id);

    try {
        const res = await fetch('api/chips.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            toast('Chip excluído.', 'success');
            closeModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            toast(data.message || 'Erro', 'error');
        }
    } catch (err) {
        toast('Erro na exclusão', 'error');
    }
}

async function openHistory(id, phone) {
    document.getElementById('historyModal').classList.add('open');
    const content = document.getElementById('historyContent');
    content.innerHTML = '<div style="text-align:center; padding:40px;"><div class="spinner"></div></div>';

    try {
        const res = await fetch('api/chips.php?action=history&id=' + id);
        const data = await res.json();

        if (!data.success) {
            content.innerHTML = `<p style="color:#c62828;">Erro: ${esc(data.message)}</p>`;
            return;
        }

        if (data.data.length === 0) {
            content.innerHTML = '<p style="color:#666; text-align:center; padding:20px;">Nenhum histórico encontrado para este chip.</p>';
            return;
        }

        content.innerHTML = '<ul style="list-style:none; padding:0;">' + data.data.map(h => {
            const dateStr = new Date(h.created_at).toLocaleString('pt-BR');
            const user = h.modified_by ? h.modified_by : 'Sistema';

            // Determinar a cor e icone baseados na ação para dar um visual legal
            let borderCol = '#00897B';
            let iconColor = '#00897B';
            let iconName = 'info';

            if (h.action === 'Cadastro') { borderCol = '#43A047'; iconColor = '#43A047'; iconName = 'add_circle'; }
            if (h.action === 'Excluido') { borderCol = '#E53935'; iconColor = '#E53935'; iconName = 'delete'; }
            if (h.action === 'Edicao') { borderCol = '#1E88E5'; iconColor = '#1E88E5'; iconName = 'edit'; }

            return `
            <li style="margin-bottom: 12px; padding: 12px; border-left: 3px solid ${borderCol}; background: #fafafa; border-radius: 0 8px 8px 0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                <div style="font-size: 0.85rem; color: #666; margin-bottom: 4px; display:flex; align-items:center; gap:6px;">
                    <span class="material-icons-round" style="font-size:14px; color:${iconColor};">${iconName}</span>
                    <strong>${esc(user)}</strong> &mdash; ${dateStr}
                </div>
                <div style="font-weight: 600; color: var(--text);">Ação: ${esc(h.action)}</div>
                ${h.problem_description ? `<div style="font-size: 0.9rem; margin-top: 6px; color: #555; background:#fff; padding:6px 10px; border-radius:4px; border:1px solid #eee;">${esc(h.problem_description)}</div>` : ''}
            </li>
            `;
        }).join('') + '</ul>';

    } catch (err) {
        content.innerHTML = `<p style="color:#c62828;">Erro ao carregar histórico.</p>`;
    }
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.remove('open');
}

function toast(msg, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const t = document.createElement('div');
    t.className = `toast ${type}`;

    let icon = 'info';
    if (type === 'success') icon = 'check_circle';
    if (type === 'error') icon = 'error';
    if (type === 'warning') icon = 'warning';

    t.innerHTML = `<span class="material-icons-round">${icon}</span> ${esc(msg)}`;
    container.appendChild(t);

    setTimeout(() => {
        t.style.opacity = '0';
        t.style.transform = 'translateY(20px)';
        setTimeout(() => t.remove(), 300);
    }, 3000);
}

function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
