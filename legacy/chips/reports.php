<?php
require_once __DIR__ . '/../config/config.php';
session_start();

if (empty($_SESSION['logged_in'])) {
    header('Location: ../index.php?page=login');
    exit;
}

$toolTitle = 'Estoque Chips';
$toolIcon = 'sim_card';
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
                <span class="material-icons-round" style="color:#00897B;">assessment</span> Relatórios — Chips SIM
            </h1>
            <p style="color:#757575; font-size:.88rem; margin:0;">Acompanhe a distribuição e movimentação dos Chips SIM
            </p>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <a href="index.php" class="btn btn-outline">
                <span class="material-icons-round">arrow_back</span> Voltar ao Estoque
            </a>
            <button class="btn btn-teal" onclick="exportChipsReport()">
                <span class="material-icons-round">download</span> Exportar CSV
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="rep-filters" style="margin-top:20px;">
        <!-- Removido Filtro de Datas -->
        <div class="form-group">
            <label>Status</label>
            <select class="form-control" id="repStatus">
                <option value="all">Todos</option>
                <option value="Estoque">Em Estoque</option>
                <option value="Em Uso">Em Uso</option>
                <option value="Cancelado">Cancelados</option>
                <option value="Defeito">Com Defeito</option>
                <option value="Retirada">Retirada</option>
                <option value="Reverso">Reverso</option>
            </select>
        </div>
        <div class="form-group">
            <label>Tipo</label>
            <select class="form-control" id="repType">
                <option value="all">Todos</option>
                <option value="POS">POS</option>
                <option value="Rastreador">Rastreador</option>
            </select>
        </div>
        <div class="form-group" style="flex:0; min-width:auto;">
            <label>&nbsp;</label>
            <button class="btn btn-teal" onclick="loadChipsReport()">
                <span class="material-icons-round">search</span> Filtrar
            </button>
        </div>
    </div>

    <!-- Stats do relatório -->
    <div class="rep-stats" id="repStatsRow">
        <div class="rep-stat rs-total">
            <div class="rs-val" id="rsTotal">—</div>
            <div class="rs-lbl">Total</div>
        </div>
        <div class="rep-stat rs-estoque">
            <div class="rs-val" id="rsEstoque">—</div>
            <div class="rs-lbl">Em Estoque</div>
        </div>
        <div class="rep-stat rs-enviado" style="border-top-color:#1E88E5;">
            <div class="rs-val" id="rsEmUso" style="color:#1E88E5;">—</div>
            <div class="rs-lbl">Em Uso</div>
        </div>
        <div class="rep-stat rs-recebido" style="border-top-color:#E53935; background:rgba(229, 57, 53, 0.05);">
            <div class="rs-val" id="rsCancelado" style="color:#E53935;">—</div>
            <div class="rs-lbl">Cancelados</div>
        </div>
        <div class="rep-stat rs-defeito">
            <div class="rs-val" id="rsDefeito">—</div>
            <div class="rs-lbl">Com Defeito</div>
        </div>
        <div class="rep-stat rs-retirada">
            <div class="rs-val" id="rsRetirada">—</div>
            <div class="rs-lbl">Retirada</div>
        </div>
        <div class="rep-stat rs-reverso">
            <div class="rs-val" id="rsReverso">—</div>
            <div class="rs-lbl">Reverso</div>
        </div>
    </div>

    <!-- Lista de Chips -->
    <div
        style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-top:24px; padding-bottom:8px;">
        <div style="padding:16px 20px; border-bottom:1px solid #eee; display:flex; align-items:center; gap:8px;">
            <span class="material-icons-round" style="color:#00897B; font-size:20px;">inventory_2</span>
            <strong style="font-size:.95rem; color:#212121;">Chips Cadastrados</strong>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:.9rem;">
                <thead>
                    <tr style="background:#fcfcfc;">
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            #</th>
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            Linha</th>
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            ICCID</th>
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            Tipo</th>
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            Operadora</th>
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            Vínculo</th>
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            Status</th>
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            Cadastro</th>
                    </tr>
                </thead>
                <tbody id="repDetailTable">
                    <tr>
                        <td colspan="8" style="text-align:center; padding:32px; color:var(--text-muted);">Use os filtros
                            acima e clique em Filtrar</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Gráfico -->
    <div class="rep-chart-wrap">
        <h3><span class="material-icons-round">donut_large</span> Distribuição por Status</h3>
        <div style="max-width:360px; height:240px; margin:0 auto;">
            <canvas id="chipsStatusChart"></canvas>
        </div>
    </div>

    <!-- Últimas movimentações -->
    <div
        style="background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-top:0; padding-bottom:8px;">
        <div style="padding:16px 20px; border-bottom:1px solid #eee; display:flex; align-items:center; gap:8px;">
            <span class="material-icons-round" style="color:#00897B; font-size:20px;">swap_vert</span>
            <strong style="font-size:.95rem; color:#212121;">Últimas Movimentações</strong>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:.9rem;">
                <thead>
                    <tr style="background:#fcfcfc;">
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            Data</th>
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            Chip</th>
                        <th
                            style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                            Ação</th>
                    </tr>
                </thead>
                <tbody id="repMovTable">
                    <tr>
                        <td colspan="3" style="text-align:center; padding:24px; color:var(--text-muted);">Use os filtros
                            acima e clique em Filtrar</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /container -->

<div class="toast-container" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let chipsChartInstance = null;

    async function loadChipsReport() {
        const status = document.getElementById('repStatus').value;
        const type = document.getElementById('repType').value;

        document.getElementById('repMovTable').innerHTML = '<tr><td colspan="3"><div class="spinner"></div></td></tr>';
        document.getElementById('repDetailTable').innerHTML = '<tr><td colspan="8"><div class="spinner"></div></td></tr>';

        try {
            const res = await fetch('api/chips.php?action=reports&status=' + status + '&type=' + type);
            const data = await res.json();

            if (!data || !data.success) {
                console.error("API returned error:", data);
                toast(data && data.message ? data.message : 'Erro ao carregar', 'error');
                document.getElementById('repMovTable').innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:#c62828;">Falha ao carregar</td></tr>';
                document.getElementById('repDetailTable').innerHTML = '<tr><td colspan="8" style="text-align:center; padding:32px; color:#c62828;">Falha ao carregar</td></tr>';
                return;
            }

            document.getElementById('rsTotal').textContent = data.stats.total || 0;
            document.getElementById('rsEstoque').textContent = data.stats['Estoque'] || 0;
            document.getElementById('rsEmUso').textContent = data.stats['Em Uso'] || 0;
            document.getElementById('rsCancelado').textContent = data.stats['Cancelado'] || 0;
            document.getElementById('rsDefeito').textContent = data.stats['Defeito'] || 0;
            document.getElementById('rsRetirada').textContent = data.stats['Retirada'] || 0;
            document.getElementById('rsReverso').textContent = data.stats['Reverso'] || 0;

            renderChipsChart(data.stats);

            if (data.events && data.events.length > 0) {
                document.getElementById('repMovTable').innerHTML = data.events.map(ev => `
                <tr>
                    <td style="padding:8px 12px; border-bottom:1px solid #f0f0f0; font-size:.78rem; color:var(--text-muted);">${new Date(ev.created_at).toLocaleString('pt-BR')}</td>
                    <td style="padding:8px 12px; border-bottom:1px solid #f0f0f0;"><strong>${esc(ev.phone_number)}</strong><br><small style="color:#888">${esc(ev.iccid)}</small></td>
                    <td style="padding:8px 12px; border-bottom:1px solid #f0f0f0;">${esc(ev.action)}</td>
                </tr>
                `).join('');
            } else {
                document.getElementById('repMovTable').innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:var(--text-muted);">Nenhuma movimentação</td></tr>';
            }

            if (data.equipments && data.equipments.length > 0) {
                const badges = { 
                    'Estoque': 'sbadge-estoque', 
                    'Em Uso': 'sbadge-enviado', 
                    'Cancelado': 'sbadge-recebido', 
                    'Defeito': 'sbadge-defeito',
                    'Retirada': 'sbadge-retirada',
                    'Reverso': 'sbadge-reverso'
                };
                document.getElementById('repDetailTable').innerHTML = data.equipments.map(eq => {
                    let vinculationStr = '<span style="color:#999; font-style:italic;">Nenhum</span>';
                    if (eq.linked_equipment) {
                        vinculationStr = `<strong style="color:var(--primary-dark); font-size:0.85rem;">${esc(eq.linked_equipment)}</strong>`;
                        if (eq.linked_details) vinculationStr += `<br><small style="color:#666;">${esc(eq.linked_details)}</small>`;
                    }

                    return `<tr style="border-bottom:1px solid #eee; transition:background 0.2s;">
                        <td style="padding:14px; color:var(--text-muted)">${eq.id}</td>
                        <td style="padding:14px;"><strong>${esc(eq.phone_number)}</strong></td>
                        <td style="padding:14px; font-family:monospace; font-size:.85rem; color:var(--text-muted);">${esc(eq.iccid)}</td>
                        <td style="padding:14px; font-size:.85rem; color:var(--text-muted);">${esc(eq.type || 'POS')}</td>
                        <td style="padding:14px; font-size:.85rem; color:var(--text-muted);">${esc(eq.carrier || '—')}</td>
                        <td style="padding:14px;">${vinculationStr}</td>
                        <td style="padding:14px;"><span class="sbadge ${badges[eq.status] || ''}">${esc(eq.status)}</span></td>
                        <td style="padding:14px; font-size:.85rem; color:var(--text-muted);">${new Date(eq.created_at).toLocaleDateString('pt-BR')}</td>
                    </tr>`;
                }).join('');
            } else {
                document.getElementById('repDetailTable').innerHTML = '<tr><td colspan="8" style="text-align:center; padding:32px; color:#888;">Nenhum chip encontrado</td></tr>';
            }
        } catch (err) {
            console.error("Erro fatal UI:", err);
            toast('Falha de conexão ou erro JS', 'error');
            document.getElementById('repMovTable').innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:#c62828;">Erro ao carregar (Pressione F12 para ver log)</td></tr>';
            document.getElementById('repDetailTable').innerHTML = '<tr><td colspan="8" style="text-align:center; padding:32px; color:#c62828;">Erro ao carregar (Pressione F12 para ver log)</td></tr>';
        }
    }

    function renderChipsChart(stats) {
        const ctx = document.getElementById('chipsStatusChart').getContext('2d');
        if (chipsChartInstance) chipsChartInstance.destroy();
        chipsChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Estoque', 'Em Uso', 'Cancelado', 'Defeito', 'Retirada', 'Reverso'],
                datasets: [{
                    data: [
                        stats['Estoque'] || 0, 
                        stats['Em Uso'] || 0, 
                        stats['Cancelado'] || 0, 
                        stats['Defeito'] || 0,
                        stats['Retirada'] || 0,
                        stats['Reverso'] || 0
                    ],
                    backgroundColor: ['#00897B', '#1E88E5', '#E53935', '#F57F17', '#7B1FA2', '#5D4037'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '68%',
                plugins: {
                    legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 10, padding: 14, font: { size: 12 } } },
                    tooltip: {
                        callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} chip(s)` } }
                }
            }
        });
    }

    function exportChipsReport() {
        const status = document.getElementById('repStatus').value;
        const type = document.getElementById('repType').value;
        window.open('api/chips.php?action=export&status='+status+'&type='+type, '_blank');
    }

    function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    function toast(msg, type = 'info') {
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.innerHTML = `<span class="material-icons-round">${type === 'success' ? 'check_circle' : 'error'}</span> ${msg}`;
        document.getElementById('toastContainer').appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3500);
    }
    
    // Auto load on init
    loadChipsReport();
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