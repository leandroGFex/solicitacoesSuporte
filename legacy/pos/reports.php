<?php
require_once __DIR__ . '/../config/config.php';
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: ../index.php?page=login');
    exit;
}

$toolTitle = 'Estoque POS';
$toolIcon = 'point_of_sale';
$toolPath = '..';
$toolReportsUrl = '';
require_once __DIR__ . '/../layout/header_tool.php';
?>

<div style="background:var(--surface); min-height:calc(100vh - 56px);">

    <!-- Page Header -->
    <div
        style="padding:24px 24px 0; display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
        <div>
            <h1
                style="font-size:1.4rem; font-weight:700; display:flex; align-items:center; gap:10px; color:#212121; margin:0 0 4px;">
                <span class="material-icons-round" style="color:#00897B;">assessment</span> Relatórios — Máquinas POS
            </h1>
            <p style="color:#757575; font-size:.88rem; margin:0;">Acompanhe a distribuição e movimentação das máquinas
                POS</p>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <a href="index.php" class="btn btn-outline">
                <span class="material-icons-round">arrow_back</span> Voltar ao Estoque
            </a>
            <button class="btn btn-teal" onclick="exportReport()">
                <span class="material-icons-round">download</span> Exportar CSV
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="rep-filters" style="margin-top:20px;">
        <!-- Removido Filtro de Datas -->
        <div class="form-group">
            <label>Status</label>
            <select class="form-control" id="filterStatus">
                <option value="all">Todos</option>
                <option value="Estoque">Em Estoque</option>
                <option value="Enviado">Enviados</option>
                <option value="Recebido">Recebidos</option>
                <option value="Defeito">Com Defeito</option>
                <option value="Em Manutenção">Em Manutenção</option>
                <option value="Retirada">Retirada</option>
                <option value="Reverso">Reverso</option>
            </select>
        </div>
        <div class="form-group" style="flex:0; min-width:auto;">
            <label>&nbsp;</label>
            <button class="btn btn-teal" onclick="loadReport()">
                <span class="material-icons-round">search</span> Filtrar
            </button>
        </div>
    </div>

    <!-- Cards de stats -->
    <div class="rep-stats" id="statsGrid">
        <div class="rep-stat rs-total">
            <div class="rs-val" id="sTotal">—</div>
            <div class="rs-lbl">Total</div>
        </div>
        <div class="rep-stat rs-estoque">
            <div class="rs-val" id="sEstoque">—</div>
            <div class="rs-lbl">Em Estoque</div>
        </div>
        <div class="rep-stat rs-enviado">
            <div class="rs-val" id="sEnviado">—</div>
            <div class="rs-lbl">Enviados</div>
        </div>
        <div class="rep-stat rs-recebido">
            <div class="rs-val" id="sRecebido">—</div>
            <div class="rs-lbl">Recebidos</div>
        </div>
        <div class="rep-stat rs-defeito">
            <div class="rs-val" id="sDefeito">—</div>
            <div class="rs-lbl">Com Defeito</div>
        </div>
        <div class="rep-stat rs-manutencao">
            <div class="rs-val" id="sManutencao">—</div>
            <div class="rs-lbl">Em Manutenção</div>
        </div>
        <div class="rep-stat rs-retirada">
            <div class="rs-val" id="sRetirada">—</div>
            <div class="rs-lbl">Retirada</div>
        </div>
        <div class="rep-stat rs-reverso">
            <div class="rs-val" id="sReverso">—</div>
            <div class="rs-lbl">Reverso</div>
        </div>
    </div>

    <!-- Lista de Equipamentos -->
    <div class="rep-table-wrap">
        <div
            style="padding:14px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px;">
            <span class="material-icons-round" style="color:#00897B; font-size:20px;">inventory_2</span>
            <strong style="font-size:.95rem; color:#212121;">Equipamentos Cadastrados</strong>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Modelo</th>
                    <th>Serial Number</th>
                    <th>ICCID (Chip)</th>
                    <th>Motivo / Obs</th>
                    <th>Status</th>
                    <th>Cadastro</th>
                </tr>
            </thead>
            <tbody id="detailTable">
                <tr>
                    <td colspan="7" style="text-align:center; padding:32px; color:var(--text-muted);">Use os filtros
                        acima e clique em Filtrar</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Gráfico (apenas o donut, sem tabela ao lado) -->
    <div class="rep-chart-wrap">
        <h3><span class="material-icons-round">donut_large</span> Distribuição por Status</h3>
        <div style="max-width:360px; height:240px; margin:0 auto;">
            <canvas id="posChart"></canvas>
        </div>
    </div>

    <!-- Últimas Movimentações (separada do gráfico) -->
    <div class="rep-table-wrap" style="margin-top:0;">
        <div
            style="padding:14px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px;">
            <span class="material-icons-round" style="color:#00897B; font-size:20px;">swap_vert</span>
            <strong style="font-size:.95rem; color:#212121;">Últimas Movimentações</strong>
            <span id="movCount" style="font-size:.78rem; color:#888; margin-left:4px;"></span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Equipamento</th>
                    <th>Serial</th>
                    <th>Ação</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody id="movTable">
                <tr>
                    <td colspan="5" style="text-align:center; padding:20px; color:var(--text-muted);">Use os filtros
                        acima e clique em Filtrar</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

<div class="toast-container" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let chartInst = null;
    const badges = { 
        'Estoque': 'estoque', 
        'Enviado': 'enviado', 
        'Recebido': 'recebido', 
        'Defeito': 'defeito', 
        'Em Manutenção': 'manutencao',
        'Retirada': 'retirada',
        'Reverso': 'reverso'
    };

    async function loadReport() {
        const status = document.getElementById('filterStatus').value;

        document.getElementById('movTable').innerHTML = '<tr><td colspan="5"><div class="spinner"></div></td></tr>';
        document.getElementById('detailTable').innerHTML = '<tr><td colspan="7"><div class="spinner"></div></td></tr>';

        const res = await fetch('api/pos.php?action=reports&status=' + encodeURIComponent(status));
        const data = await res.json();
        if (!data.success) { toast('Erro ao carregar relatório', 'error'); return; }

        // Stats
        const s = data.stats;
        document.getElementById('sTotal').textContent = s.total || 0;
        document.getElementById('sEstoque').textContent = s['Estoque'] || 0;
        document.getElementById('sEnviado').textContent = s['Enviado'] || 0;
        document.getElementById('sRecebido').textContent = s['Recebido'] || 0;
        document.getElementById('sDefeito').textContent = s['Defeito'] || 0;
        document.getElementById('sManutencao').textContent = s['Em Manutenção'] || 0;
        document.getElementById('sRetirada').textContent = s['Retirada'] || 0;
        document.getElementById('sReverso').textContent = s['Reverso'] || 0;

        // Gráfico — somente equipamentos reais, não histórico
        const ctx = document.getElementById('posChart').getContext('2d');
        if (chartInst) chartInst.destroy();
        chartInst = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Em Estoque', 'Enviado', 'Recebido', 'Defeito', 'Manutenção', 'Retirada', 'Reverso'],
                datasets: [{
                    data: [
                        s['Estoque'] || 0, 
                        s['Enviado'] || 0, 
                        s['Recebido'] || 0, 
                        s['Defeito'] || 0, 
                        s['Em Manutenção'] || 0,
                        s['Retirada'] || 0,
                        s['Reverso'] || 0
                    ],
                    backgroundColor: ['#00897B', '#1E88E5', '#43A047', '#E53935', '#F57F17', '#7B1FA2', '#5D4037'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 10, padding: 14, font: { size: 12 } } },
                    tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} equipamento(s)` } }
                }
            }
        });

        // Movimentações
        const evs = data.events || [];
        document.getElementById('movCount').textContent = evs.length ? `(${evs.length} registro${evs.length > 1 ? 's' : ''})` : '';
        if (evs.length) {
            document.getElementById('movTable').innerHTML = evs.map(ev => `
            <tr>
                <td style="white-space:nowrap; color:var(--text-muted); font-size:.8rem;">${new Date(ev.created_at).toLocaleString('pt-BR')}</td>
                <td><strong>${esc(ev.model || 'POS')}</strong></td>
                <td style="font-family:monospace; font-size:.8rem;">${esc(ev.serial_number)}</td>
                <td>${esc(ev.action)}</td>
                <td style="color:#888; font-size:.82rem;">${esc(ev.problem_description || '—')}</td>
            </tr>`).join('');
        } else {
            document.getElementById('movTable').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:var(--text-muted);">Nenhuma movimentação no período</td></tr>';
        }

        // Tabela de equipamentos
        const eqs = data.equipments || [];
        if (eqs.length) {
            document.getElementById('detailTable').innerHTML = eqs.map(eq => {
                let chipStr = '<span style="color:#999;font-style:italic;">Nenhum</span>';
                if (eq.chip_iccid) {
                    let compBadge = '';
                    if (eq.linked_company) {
                        compBadge = `<br><span style="display:inline-block; margin-top:4px; padding:2px 6px; background:#e0f2f1; color:#00695c; font-size:0.75rem; border-radius:4px;"><span class="material-icons-round" style="font-size:10px; vertical-align:middle;">business</span> ${esc(eq.linked_company)}</span>`;
                    }
                    if (eq.chip_phone) {
                        chipStr = `<strong style="color:var(--primary-dark); font-size:0.85rem;">${esc(eq.chip_phone)}</strong><br><small style="color:#666;">${esc(eq.chip_carrier)} - ${esc(eq.chip_iccid)}</small>${compBadge}`;
                    } else {
                        chipStr = `<strong style="color:var(--primary-dark); font-size:0.85rem;">ICCID: ${esc(eq.chip_iccid)}</strong>${compBadge}`;
                    }
                }

                return `
                <tr>
                    <td style="color:var(--text-muted);">${eq.id}</td>
                    <td><strong>${esc(eq.model || '—')}</strong></td>
                    <td style="font-family:monospace;">${esc(eq.serial_number)}</td>
                    <td>${chipStr}</td>
                    <td style="color:#888; font-size:.82rem; max-width:200px;">${esc(eq.motivo || '—')}</td>
                    <td><span class="sbadge sbadge-${badges[eq.status] || ''}">${esc(eq.status)}</span></td>
                    <td style="color:var(--text-muted);">${new Date(eq.created_at).toLocaleDateString('pt-BR')}</td>
                </tr>`;
            }).join('');
        } else {
            document.getElementById('detailTable').innerHTML = '<tr><td colspan="7" style="text-align:center; padding:32px; color:var(--text-muted);">Nenhum equipamento no período selecionado</td></tr>';
        }
    }

    function exportReport() {
        const status = document.getElementById('filterStatus').value;
        window.location.href = 'api/pos.php?action=export&status=' + encodeURIComponent(status);
    }

    function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function toast(msg, type = 'info') {
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.innerHTML = `<span class="material-icons-round">${type === 'success' ? 'check_circle' : 'error'}</span> ${msg}`;
        document.getElementById('toastContainer').appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3500);
    }

    loadReport();
</script>
<style>
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .spinner {
        width: 28px;
        height: 28px;
        border: 3px solid rgba(0, 137, 123, .2);
        border-top-color: #00897B;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 20px auto;
    }
</style>
</body>

</html>