<?php
// Reports Page
require_once __DIR__ . '/../config/config.php';
include __DIR__ . '/../layout/header.php';
?>

<div style="background:var(--surface);min-height:calc(100vh - 56px)">
    <div class="page-header"
        style="padding:24px 24px 0;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
            <h1 class="page-header"
                style="font-size:1.4rem;font-weight:700;display:flex;align-items:center;gap:10px;color:#212121">
                <span class="material-icons-round" style="color:#00897B">assessment</span> Relatórios de Prazos
            </h1>
            <p style="color:#757575;font-size:.88rem;margin-top:4px">Acompanhe o cumprimento de prazos das solicitações
            </p>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <a href="index.php?page=board" class="btn btn-outline">
                <span class="material-icons-round">arrow_back</span> Voltar ao Board
            </a>
            <button class="btn btn-teal" id="btnExport" onclick="exportReport()">
                <span class="material-icons-round">download</span> Exportar CSV
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-bar">
        <div class="form-group">
            <label>Data inicial</label>
            <input type="date" class="form-control" id="filterFrom" value="<?= date('Y-m-01') ?>">
        </div>
        <div class="form-group">
            <label>Data final</label>
            <input type="date" class="form-control" id="filterTo" value="<?= date('Y-m-t') ?>">
        </div>
        <div class="form-group">
            <label>Status do Prazo</label>
            <select class="form-control" id="filterStatus">
                <option value="all">Todos</option>
                <option value="met">✅ Cumpridos</option>
                <option value="missed">❌ Atrasados</option>
                <option value="open">🔵 Em aberto (no prazo)</option>
                <option value="overdue">⚠️ Vencidos (sem entrega)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Categoria</label>
            <select class="form-control" id="filterCategory">
                <option value="all">Todas</option>
                <option value="cartao">💳 Cartão</option>
                <option value="tag">🏷️ Tag</option>
                <option value="pos">📟 POS</option>
                <option value="rastreador">📡 Rastreador</option>
            </select>
        </div>
        <div class="form-group" style="flex:0">
            <label>&nbsp;</label>
            <button class="btn btn-primary-main" onclick="loadReport()">
                <span class="material-icons-round">search</span> Filtrar
            </button>
        </div>
    </div>

    <!-- Stats globais -->
    <div class="stats-grid" id="statsGrid" style="padding:0 24px 16px">
        <div class="stat-card total">
            <div class="stat-value" id="statTotal">—</div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-card met">
            <div class="stat-value" id="statMet">—</div>
            <div class="stat-label">✅ Cumpridos</div>
        </div>
        <div class="stat-card missed">
            <div class="stat-value" id="statMissed">—</div>
            <div class="stat-label">❌ Atrasados</div>
        </div>
        <div class="stat-card open">
            <div class="stat-value" id="statOpen">—</div>
            <div class="stat-label">🔵 Em aberto</div>
        </div>
        <div class="stat-card overdue">
            <div class="stat-value" id="statOverdue">—</div>
            <div class="stat-label">⚠️ Vencidos</div>
        </div>
    </div>

    <!-- Stats por categoria -->
    <div class="cat-stats-grid" id="catStatsGrid" style="display:none"></div>

    <!-- Tabela -->
    <div class="table-wrap" style="margin-top: 24px;">
        <table id="reportDataTable" style="width:100%" class="table-dados">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Data Criação</th>
                    <th>Título</th>
                    <th>Categoria</th>
                    <th>Empresa / Cliente</th>
                    <th>Rastreio</th>
                    <th>Prazo</th>
                    <th>Data Conclusão</th>
                    <th>Status</th>
                    <th>Coluna</th>
                </tr>
            </thead>
            <tbody id="reportTable">
                <tr>
                    <td colspan="10" class="text-center text-muted" style="padding:32px">Use os filtros acima e clique
                        em
                        Filtrar</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<!-- Carregamento de Bibliotecas: jQuery, DataTables e Chart.js -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* DataTables Custom Theme p/ Relatórios */
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 6px 10px;
        outline: none;
        margin-left: 8px;
        font-family: inherit;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: var(--primary);
    }

    .dataTables_wrapper .dataTables_length select {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 4px;
        outline: none;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: var(--radius-sm) !important;
        border: 1px solid transparent !important;
        box-shadow: none !important;
        padding: 4px 10px !important;
        margin-left: 2px !important;
        color: var(--text-color) !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: var(--primary) !important;
        color: #fff !important;
        border-color: var(--primary) !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--bg-body) !important;
        border-color: var(--border) !important;
    }

    table.dataTable.no-footer {
        border-bottom: 1px solid var(--border) !important;
    }

    .table-dados th {
        font-weight: 600;
        color: var(--text-color);
        background: #f8fafc;
        border-bottom: 2px solid var(--border);
        padding: 12px 16px;
    }

    .table-dados td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        font-size: 0.9rem;
    }
</style>

<script>
    const SESSIONr = { userId: <?= $_SESSION['user_id'] ?> };
    let reportTableInstance = null;
    let reportChartInstance = null;

    async function loadReport() {
        // Indicador Loading
        const tbody = document.getElementById('reportTable');
        if (reportTableInstance === null && tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center" style="padding:32px">Carregando...</td></tr>';

        const form = new FormData();
        form.append('action', 'generate');
        form.append('date_from', document.getElementById('filterFrom').value);
        form.append('date_to', document.getElementById('filterTo').value);
        form.append('status', document.getElementById('filterStatus').value);
        form.append('category', document.getElementById('filterCategory').value);

        const res = await fetch('api/reports.php', { method: 'POST', body: form });
        const data = await res.json();
        if (!data.success) return;

        // Stats globais
        document.getElementById('statTotal').textContent = data.stats.total;
        document.getElementById('statMet').textContent = data.stats.met;
        document.getElementById('statMissed').textContent = data.stats.missed;
        document.getElementById('statOpen').textContent = data.stats.open;
        document.getElementById('statOverdue').textContent = data.stats.overdue;

        // Stats por categoria
        const catColors = { cartao: '#3949AB', tag: '#00838F', pos: '#E65100', rastreador: '#7B1FA2' };
        const catLabels = { cartao: 'Cartão', tag: 'Tag', pos: 'POS', rastreador: 'Rastreador' };
        const cg = document.getElementById('catStatsGrid');
        if (data.stats.by_category?.length) {
            cg.style.display = 'grid';
            cg.innerHTML = data.stats.by_category.map(c => `
                <div class="cat-stat-card ${c.category}">
                    <div class="cat-stat-value">${c.total}</div>
                    <div class="cat-stat-label">${catLabels[c.category] || c.category} <span style="color:#aaa;font-weight:400;font-size:.7rem">(${c.met || 0} cumpridos)</span></div>
                </div>
            `).join('');

            if (data.stats.cartoes_enviados > 0) {
                cg.innerHTML += `
                    <div class="cat-stat-card cartao" style="border-left-color:#2E7D32">
                        <div class="cat-stat-value" style="color:#2E7D32">${data.stats.cartoes_enviados}</div>
                        <div class="cat-stat-label">Cartões enviados c/ prazo cumprido</div>
                    </div>`;
            }
        } else {
            cg.style.display = 'none';
        }

        // Tabela DataTables Update
        if (reportTableInstance !== null) {
            reportTableInstance.destroy();
            reportTableInstance = null;
        }

        if (!data.cards.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted" style="padding:32px">Nenhum resultado encontrado</td></tr>';
            return;
        }

        const rows = data.cards.map(c => {
            let badgeClass = 'badge-open', badgeLabel = 'Em aberto';
            if (c.deadline_met == 1) { badgeClass = 'badge-met'; badgeLabel = 'Cumprido'; }
            else if (c.deadline_met == 0) { badgeClass = 'badge-missed'; badgeLabel = 'Atrasado'; }
            else if (c.deadline && c.deadline < new Date().toISOString().split('T')[0]) { badgeClass = 'badge-overdue'; badgeLabel = 'Vencido'; }

            const catBadge = `<span class="cat-badge cat-${c.category}">${catLabels[c.category] || c.category}</span>`;

            // Client/Empresa fallback
            let finalClient = c.company_name || c.client_name || '-';
            if (c.company_name && c.client_name) finalClient = `${c.company_name} / ${c.client_name}`;

            // Placa/Extra data fallback
            let finalPlaca = c.placa || '';
            if (!finalPlaca && c.extra_data) {
                try {
                    const extra = JSON.parse(c.extra_data);
                    if (Array.isArray(extra)) {
                        const placas = extra.filter(item => item.placa).map(item => item.placa);
                        if (placas.length > 0) finalPlaca = placas.join(', ');
                    }
                } catch (e) { }
            }

            // Delivery or Transit text
            let deliveryText = '';
            if (c.delivery_date && c.tracking_latest_status) {
                const isDelivered = c.tracking_latest_status.toLowerCase().includes('entregue');
                const color = isDelivered ? '#2E7D32' : '#757575'; // Green if delivered, gray if transit
                deliveryText = `<br><span style="font-size:0.7rem; color:${color}; font-weight:500;">${c.tracking_latest_status}: ${c.delivery_date}</span>`;
            }

            return `<tr>
            <td style="color:#999">${c.id}</td>
            <td data-order="${c.created_at || ''}">${c.created_at ? formatDate(c.created_at.split(' ')[0]) : '—'}</td>
            <td style="font-weight:600;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${esc(c.title)}">${esc(c.title)}</td>
            <td>${catBadge}</td>
            <td>${esc(finalClient)}</td>
            <td style="font-size:.78rem;max-width:140px;overflow:hidden;text-overflow:ellipsis">
                ${c.tracking_code ? `<span style="font-family:monospace">${esc(c.tracking_code)}</span>${deliveryText}` : '—'}
            </td>
            <td data-order="${c.deadline || ''}">${c.deadline ? formatDate(c.deadline) : '—'}</td>
            <td data-order="${c.completion_date || ''}">${c.completion_date ? formatDate(c.completion_date.split(' ')[0]) : '—'}</td>
            <td><span class="badge ${badgeClass}">${badgeLabel}</span></td>
            <td><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${esc(c.column_color)};margin-right:6px"></span>${esc(c.column_name || '—')}</td>
            </tr>`;
        });

        tbody.innerHTML = rows.join('');

        // Inicializa o DataTables
        if (typeof $ !== 'undefined' && $.fn.DataTable) {
            reportTableInstance = $('#reportDataTable').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
                pageLength: 50,
                order: [[7, 'asc']] // Ordena pelo Prazo por padrão
            });
        }
    }



    function exportReport() {
        const from = document.getElementById('filterFrom').value;
        const to = document.getElementById('filterTo').value;
        const status = document.getElementById('filterStatus').value;
        const cat = document.getElementById('filterCategory').value;
        // The backend export API directly extracts filters parameters.
        const exportUrl = `api/reports.php?action=export&date_from=${from}&date_to=${to}&status=${status}&category=${cat}`;
        window.open(exportUrl, '_blank');
    }

    function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function formatDate(d) { if (!d) return ''; const [y, m, day] = d.split('-'); return `${day}/${m}/${y}`; }

    function toast(msg, type = 'info') {
        const el = document.createElement('div');
        el.className = `toast ${type}`;
        el.textContent = msg;
        document.getElementById('toastContainer').appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }

    // Carregar ao abrir
    loadReport();
</script>
<?php include __DIR__ . '/../layout/footer.php'; ?>