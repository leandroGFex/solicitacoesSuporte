<?php
// Global Dashboard Page
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: index.php?page=login');
    exit;
}

include __DIR__ . '/../layout/header.php';
$isAdmin = ($_SESSION['user_role'] === 'admin');
?>
<style>
    /* DASHBOARD CONTAINER */
    .dashboard-container {
        max-width: 1400px;
        margin: 40px auto;
        padding: 0 24px;
    }

    .dashboard-header {
        margin-bottom: 32px;
    }

    .dashboard-header h2 {
        font-size: 2rem;
        color: #004D40;
        margin-bottom: 8px;
    }

    .dashboard-header p {
        color: #666;
        font-size: 1.1rem;
    }

    /* TOOLS GRID (PARA USUARIOS COMUNS) */
    .tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 24px;
    }

    /* SIDE-BY-SIDE SPLIT (PARA ADMINS) */
    .dashboard-main-split {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: 32px;
        align-items: start;
    }

    @media (max-width: 1250px) {
        .dashboard-main-split { grid-template-columns: 1fr; }
    }

    .tools-sidebar {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    /* TOOL CARD STYLES */
    .tool-card {
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s, box-shadow 0.2s;
        text-decoration: none;
        color: inherit;
        border: 1px solid #e0e0e0;
        display: flex;
        flex-direction: column;
    }

    .isAdmin .tool-card {
        padding: 16px 20px;
    }

    .tool-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        border-color: #00897B;
    }

    .tool-icon {
        width: 50px;
        height: 50px;
        background: #e0f2f1;
        color: #00897B;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }

    .isAdmin .tool-icon {
        width: 42px;
        height: 42px;
        margin-bottom: 12px;
    }

    .tool-icon .material-icons-round { font-size: 28px; }
    .isAdmin .tool-icon .material-icons-round { font-size: 24px; }

    .tool-card h3 {
        font-size: 1.3rem;
        color: #004D40;
        margin-bottom: 8px;
        font-weight: 700;
    }

    .isAdmin .tool-card h3 { font-size: 1.1rem; margin-bottom: 4px; }

    .tool-card p {
        color: #666;
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 16px;
    }

    .isAdmin .tool-card p {
        font-size: 0.85rem;
        margin-bottom: 12px;
        line-height: 1.4;
    }

    .tool-footer {
        display: flex;
        align-items: center;
        color: #00897B;
        font-weight: 600;
        font-size: 0.95rem;
        gap: 4px;
        margin-top: auto;
    }

    .isAdmin .tool-footer { font-size: 0.85rem; }

    /* ADMIN DASHBOARD SECTION */
    .admin-dashboard-section {
        min-width: 0; /* Permite que o container encolha e forçe o wrap interno */
    }

    .report-selector-header {
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .metric-card {
        background: #fff;
        padding: 16px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #eee;
    }

    .metric-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }

    .metric-value { font-size: 1.4rem; font-weight: 800; color: #212121; }
    .metric-label { font-size: 0.72rem; color: #757575; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

    .dashboard-row {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 24px;
    }

    @media (max-width: 1000px) { .dashboard-row { grid-template-columns: 1fr; } }

    .widget-card {
        background: #fff;
        padding: 24px;
        border-radius: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid #eee;
        min-width: 0;
    }

    .widget-card h3 {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 20px;
        color: #004D40;
        display: flex; align-items: center; gap: 8px;
    }

    .chart-container { height: 280px; position: relative; }

    .recent-activities { display: flex; flex-direction: column; gap: 14px; }
    .activity-item { display: flex; gap: 12px; padding-bottom: 14px; border-bottom: 1px solid #f5f5f5; }
    .activity-item:last-child { border-bottom: none; }
    .activity-content { flex: 1; overflow: hidden; min-width: 0; }
    .activity-title { font-size: 0.9rem; font-weight: 700; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #333; }
    .activity-desc { font-size: 0.82rem; color: #555; }
    .activity-meta { display: flex; justify-content: space-between; margin-top: 6px; font-size: 0.72rem; color: #999; font-weight: 500; }
</style>

<div class="dashboard-container <?= $isAdmin ? 'isAdmin' : '' ?>">
    <div class="dashboard-header">
        <h2>Bem-vindo, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?>!</h2>
        <p>Acesse suas ferramentas e acompanhe o desempenho em tempo real.</p>
    </div>

    <?php if ($isAdmin): ?>
        <!-- ADMIN SPLIT VIEW -->
        <div class="dashboard-main-split">
            <div class="tools-sidebar">
                <h4 style="font-size: 0.8rem; color: #999; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; padding-left: 4px;">Atalhos</h4>
                <?php include 'dashboard_tools_content.php'; ?>
            </div>

            <div class="admin-dashboard-section">
                <div class="report-selector-header">
                    <div>
                        <h3 style="font-size: 1.4rem; color: #004D40; margin-bottom: 2px;">Indicadores</h3>
                        <p id="dashboard-subtitle" style="font-size: 0.9rem; color: #666;">Visão geral das solicitações</p>
                    </div>
                    <select id="reportTypeSelect" onchange="changeReport(this.value)" style="padding: 10px 16px; border-radius: 12px; border: 1px solid #ddd; font-family: inherit; font-size: 0.9rem; background: #fff; cursor: pointer; outline: none; box-shadow: 0 2px 5px rgba(0,0,0,0.05); min-width: 200px;">
                        <option value="solicitacoes">📊 Solicitações</option>
                        <option value="pos">📟 Equipamentos POS</option>
                        <option value="trackers">📡 Rastreadores</option>
                        <option value="chips">📱 Chips (SIM Cards)</option>
                    </select>
                </div>

                <div class="metrics-grid" id="metrics-grid">
                    <div style="grid-column: 1/-1; text-align: center; color: #999; padding: 20px;">Carregando métricas...</div>
                </div>

                <div class="dashboard-row">
                    <div class="widget-card">
                        <h3><span class="material-icons-round" style="color:#00897B">pie_chart</span>Gráfico de Distribuição</h3>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                    <div class="widget-card">
                        <h3><span class="material-icons-round" style="color:#00897B">history</span>Histórico Recente</h3>
                        <div id="recent-activities-list">
                            <div style="text-align: center; color: #999; padding: 20px;">Carregando histórico...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- USER GRID VIEW -->
        <div class="tools-grid">
            <?php include 'dashboard_tools_content.php'; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let dashboardData = null;
    let dashboardChart = null;

    document.addEventListener('DOMContentLoaded', loadDashboardStats);

    async function loadDashboardStats() {
        if (!document.getElementById('metrics-grid')) return;
        try {
            const res = await fetch('api/dashboard_stats.php');
            const data = await res.json();
            if (data.success) {
                dashboardData = data;
                renderQuickReport('solicitacoes');
                updateDashboardChart('solicitacoes');
                renderRecentActivities(data.recent, 'solicitacoes');
            }
        } catch (e) { console.error(e); }
    }

    function changeReport(v) {
        if (!dashboardData) return;
        renderQuickReport(v);
        updateDashboardChart(v);
        
        let recentData = [];
        if (v === 'solicitacoes') {
            recentData = dashboardData.recent;
        } else {
            recentData = dashboardData.equipment_stats[v].recent;
        }
        renderRecentActivities(recentData, v);

        const subtitle = document.getElementById('dashboard-subtitle');
        if (v === 'solicitacoes') subtitle.textContent = 'Visão geral das solicitações';
        else if (v === 'pos') subtitle.textContent = 'Estoque de POS e histórico de serviços';
        else if (v === 'trackers') subtitle.textContent = 'Estoque e manutenção de Rastreadores';
        else subtitle.textContent = 'Ciclo de vida e status dos Chips';
    }

    function renderQuickReport(type) {
        const grid = document.getElementById('metrics-grid');
        if (!grid) return;
        grid.innerHTML = '';
        let items = [];
        if (type === 'solicitacoes') {
            const s = dashboardData.stats;
            items = [
                { label: 'Total', value: s.total, icon: 'assignment', color: '#00897B', bg: '#e0f2f1' },
                { label: 'Em Andamento', value: s.emAndamento, icon: 'schedule', color: '#0288D1', bg: '#e1f5fe' },
                { label: 'Atrasadas', value: s.atrasadas, icon: 'priority_high', color: '#c62828', bg: '#ffebee' },
                { label: 'Entregues', value: s.entregues, icon: 'check_circle', color: '#2e7d32', bg: '#e8f5e9' }
            ];
        } else if (type === 'chips') {
            const s = dashboardData.equipment_stats.chips.summary;
            items = [
                { label: 'Total', value: s.total, icon: 'sim_card', color: '#0288D1', bg: '#e1f5fe' },
                { label: 'Estoque', value: s.estoque, icon: 'inventory_2', color: '#00897B', bg: '#e0f2f1' },
                { label: 'Em Uso', value: s.em_uso, icon: 'devices', color: '#7B1FA2', bg: '#F3E5F5' },
                { label: 'Cancelados', value: s.cancelados, icon: 'block', color: '#263238', bg: '#eceff1' },
                { label: 'Defeito', value: s.defeito, icon: 'running_with_errors', color: '#c62828', bg: '#ffebee' }
            ];
        } else {
            const s = type === 'pos' ? dashboardData.equipment_stats.pos.summary : dashboardData.equipment_stats.trackers.summary;
            items = [
                { label: 'Total', value: s.total, icon: type === 'pos' ? 'point_of_sale' : 'location_on', color: '#263238', bg: '#eceff1' },
                { label: 'Estoque', value: s.estoque, icon: 'inventory_2', color: '#00897B', bg: '#e0f2f1' },
                { label: 'Enviados', value: s.enviados, icon: 'local_shipping', color: '#0288D1', bg: '#e1f5fe' },
                { label: 'Recebidos', value: s.recebidos, icon: 'task_alt', color: '#2E7D32', bg: '#E8F5E9' },
                { label: 'Defeito', value: s.defeito, icon: 'running_with_errors', color: '#c62828', bg: '#ffebee' },
                { label: 'Manutenção', value: s.manutencao, icon: 'engineering', color: '#EF6C00', bg: '#FFF3E0' }
            ];
            if (type === 'pos') {
                items.push({ label: 'Retirada', value: s.retirada, icon: 'file_download', color: '#6A1B9A', bg: '#F3E5F5' });
                items.push({ label: 'Reverso', value: s.reverso, icon: 'settings_backup_restore', color: '#3E2723', bg: '#EFEBE9' });
            }
        }
        items.forEach(i => {
            const div = document.createElement('div');
            div.className = 'metric-card';
            div.innerHTML = `<div class="metric-icon" style="background:${i.bg};color:${i.color}"><span class="material-icons-round">${i.icon}</span></div><div class="metric-info"><span class="metric-label">${i.label}</span><span class="metric-value">${i.value}</span></div>`;
            grid.appendChild(div);
        });
    }

    function updateDashboardChart(type) {
        const canv = document.getElementById('categoryChart');
        if (!canv) return;
        const ctx = canv.getContext('2d');
        if (dashboardChart) dashboardChart.destroy();

        let labels = [];
        let counts = [];
        let bgColors = [];
        
        if (type === 'solicitacoes') {
            const s = dashboardData.stats;
            // Mostra os status das caixinhas superiores
            labels = ['Em Andamento', 'Atrasadas', 'Entregues'];
            // Para não duplicar fatias (já que atrasadas é subconjunto de emAndamento):
            counts = [s.emAndamento - s.atrasadas, s.atrasadas, s.entregues];
            bgColors = ['#0288D1', '#dc2626', '#2e7d32'];
        } else if (type === 'chips') {
            const s = dashboardData.equipment_stats.chips.summary;
            labels = ['Estoque', 'Em Uso', 'Cancelados', 'Defeito'];
            counts = [s.estoque, s.em_uso, s.cancelados, s.defeito];
            bgColors = ['#00897B', '#0288D1', '#9E9E9E', '#c62828'];
        } else {
            const s = type === 'pos' ? dashboardData.equipment_stats.pos.summary : dashboardData.equipment_stats.trackers.summary;
            labels = ['Estoque', 'Enviados', 'Recebidos', 'Defeito', 'Manutenção'];
            counts = [s.estoque, s.enviados, s.recebidos, s.defeito, s.manutencao];
            bgColors = ['#00897B', '#0288D1', '#2e7d32', '#c62828', '#EF6C00'];
            
            if (type === 'pos') {
                labels.push('Retirada', 'Reverso');
                counts.push(s.retirada, s.reverso);
                bgColors.push('#6A1B9A', '#3E2723');
            }
        }

        dashboardChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: counts, backgroundColor: bgColors, borderWidth: 0, hoverOffset: 15 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '70%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true, pointStyle: 'circle' } },
                    tooltip: { 
                        backgroundColor: 'rgba(0,0,0,0.8)', 
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                if (context.raw !== undefined) label += context.raw;
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    function renderRecentActivities(recent, type = 'solicitacoes') {
        const list = document.getElementById('recent-activities-list');
        if (!list) return;
        if (!recent || recent.length === 0) { list.innerHTML = '<div style="padding:20px;text-align:center;color:#999">Sem histórico de movimentações</div>'; return; }
        
        list.innerHTML = recent.map(a => {
            const dateStr = new Date(a.created_at).toLocaleDateString('pt-BR');
            const title = type === 'solicitacoes' ? a.card_title : (a.identifier || 'Equipamento');
            const desc = a.action === 'Alteração de Coluna' ? `Movido para ${a.new_col_name}` : a.action;
            const userName = type === 'solicitacoes' ? (a.user_name || 'Sistema') : (a.modified_by || 'Sistema');
            const dotColor = '#00897B';

            return `
                <div class="activity-item">
                    <div style="width:8px;height:8px;border-radius:50%;background:${dotColor};margin-top:6px;flex-shrink:0;"></div>
                    <div class="activity-content">
                        <div class="activity-title">${title}</div>
                        <div class="activity-desc">${desc}</div>
                        <div class="activity-meta"><span>${dateStr}</span><span>${userName}</span></div>
                    </div>
                </div>`;
        }).join('');
    }

    function toggleUserMenu() { const dd = document.getElementById('userDropdown'); if(dd) dd.classList.toggle('open'); }
    document.addEventListener('click', (e) => { if (!e.target.closest('.user-menu')) { const dd = document.getElementById('userDropdown'); if(dd) dd.classList.remove('open'); } });
</script>
</body>
</html>