<?php
// Trackers Inventory Page
require_once __DIR__ . '/../config/config.php';
session_start();

if (empty($_SESSION['logged_in'])) {
    header('Location: ../index.php?page=login');
    exit;
}

$db = getDB();

// Contagens para os cards informativos
$totalEstoque = $db->query("SELECT COUNT(*) FROM trackers WHERE status = 'Estoque' AND deleted_at IS NULL")->fetchColumn();
$totalEnviado = $db->query("SELECT COUNT(*) FROM trackers WHERE status = 'Enviado' AND deleted_at IS NULL")->fetchColumn();
$totalRecebido = $db->query("SELECT COUNT(*) FROM trackers WHERE status = 'Recebido' AND deleted_at IS NULL")->fetchColumn();
$totalDefeito = $db->query("SELECT COUNT(*) FROM trackers WHERE status = 'Defeito' AND deleted_at IS NULL")->fetchColumn();
$totalManutencao = $db->query("SELECT COUNT(*) FROM trackers WHERE status = 'Em Manutenção' AND deleted_at IS NULL")->fetchColumn();
$totalGeral = $totalEstoque + $totalEnviado + $totalRecebido + $totalDefeito + $totalManutencao;

// Shared header vars
$toolTitle = 'Estoque Rastreadores';
$toolIcon = 'location_on';
$toolPath = '..';
$toolReportsUrl = 'reports.php';
require_once __DIR__ . '/../layout/header_tool.php';
?>

<div style="background:var(--bg); min-height:calc(100vh - 56px);">

    <!-- Page sub-header -->
    <div class="tool-page-header">
        <h2>
            <span class="material-icons-round">location_on</span>
            Rastreadores
        </h2>
        <div style="display:flex; gap:12px; align-items:center;">
            <button class="btn btn-outline" onclick="initTrackerImport()">
                <span class="material-icons-round">upload_file</span> Importar de Excel
            </button>
            <button class="btn btn-yellow" onclick="openModal()">
                <span class="material-icons-round">add</span> Novo Rastreador
            </button>
        </div>
    </div>

    <!-- =====================================================
         STATS GRID — always visible
    ===================================================== -->
    <div class="inv-stats-grid">
        <div class="inv-stat-card" style="border-left-color:var(--primary-dark)">
            <div class="stat-label">Total</div>
            <div class="stat-value" style="color:var(--primary-dark)"><?= $totalGeral ?></div>
        </div>
        <div class="inv-stat-card">
            <div class="stat-label">Em Estoque</div>
            <div class="stat-value"><?= $totalEstoque ?></div>
        </div>
        <div class="inv-stat-card" style="border-left-color:#1E88E5">
            <div class="stat-label">Enviados</div>
            <div class="stat-value" style="color:#1E88E5"><?= $totalEnviado ?></div>
        </div>
        <div class="inv-stat-card" style="border-left-color:var(--success)">
            <div class="stat-label">Recebidos</div>
            <div class="stat-value" style="color:var(--success)"><?= $totalRecebido ?></div>
        </div>
        <div class="inv-stat-card defeito">
            <div class="stat-label">Com Defeito</div>
            <div class="stat-value"><?= $totalDefeito ?></div>
        </div>
        <div class="inv-stat-card manutencao">
            <div class="stat-label">Em Manutenção</div>
            <div class="stat-value"><?= $totalManutencao ?></div>
        </div>
    </div>

    <!-- =====================================================
         TAB: ESTOQUE
    ===================================================== -->
    <div id="tabEstoque">
        <div
            style="background:var(--surface); border-radius:var(--radius-md); border:1px solid var(--border); margin:0 24px 24px; box-shadow:var(--shadow-sm); overflow:hidden;">
            <div
                style="padding:14px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                <input type="text" id="searchInput" placeholder="Buscar por modelo, serial ou chip..."
                    style="border:1.5px solid var(--border); border-radius:var(--radius-md); padding:8px 14px; font-size:.88rem; font-family:'Inter',sans-serif; outline:none; width:280px;"
                    onkeyup="filterTable()" onfocus="this.style.borderColor='var(--primary)'"
                    onblur="this.style.borderColor='var(--border)'">
            </div>
            <div style="overflow-x:auto;">
                <table id="trackerTable" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th
                                style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border);">
                                Modelo</th>
                            <th
                                style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border);">
                                Serial Number</th>
                            <th
                                style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border);">
                                ICCID (Chip)</th>
                            <th
                                style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border);">
                                Status</th>
                            <th
                                style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border);">
                                Cadastro</th>
                            <th
                                style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border);">
                                Ações</th>
                        </tr>
                    </thead>
                    <tbody id="trackerTableBody">
                        <tr>
                            <td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">
                                Carregando...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =====================================================
         TAB: RELATÓRIOS
    ===================================================== -->
    <div id="tabReports" style="display:none;">

        <div
            style="padding:0 24px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <h3 style="font-size:1rem; font-weight:700; color:var(--text); margin:0 0 4px;">Relatório de Estoque
                    Rastreadores</h3>
                <p style="font-size:.82rem; color:var(--text-muted); margin:0;">Acompanhe a distribuição e movimentação
                    dos rastreadores</p>
            </div>
            <button class="btn btn-teal" onclick="exportTrackerReport()">
                <span class="material-icons-round">download</span> Exportar CSV
            </button>
        </div>

        <!-- Filtros -->
        <div class="rep-filters">
            <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" class="form-control" id="repFrom" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group">
                <label>Data Final</label>
                <input type="date" class="form-control" id="repTo" value="<?= date('Y-m-t') ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select class="form-control" id="repStatus">
                    <option value="all">Todos</option>
                    <option value="Estoque">Em Estoque</option>
                    <option value="Enviado">Enviados</option>
                    <option value="Recebido">Recebidos</option>
                    <option value="Defeito">Com Defeito</option>
                    <option value="Em Manutenção">Em Manutenção</option>
                </select>
            </div>
            <div class="form-group" style="flex:0; min-width:auto;">
                <label>&nbsp;</label>
                <button class="btn btn-teal" onclick="loadTrackerReport()">
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
            <div class="rep-stat rs-enviado">
                <div class="rs-val" id="rsEnviado">—</div>
                <div class="rs-lbl">Enviados</div>
            </div>
            <div class="rep-stat rs-recebido">
                <div class="rs-val" id="rsRecebido">—</div>
                <div class="rs-lbl">Recebidos</div>
            </div>
            <div class="rep-stat rs-defeito">
                <div class="rs-val" id="rsDefeito">—</div>
                <div class="rs-lbl">Com Defeito</div>
            </div>
            <div class="rep-stat rs-manutencao">
                <div class="rs-val" id="rsManutencao">—</div>
                <div class="rs-lbl">Em Manutenção</div>
            </div>
        </div>

        <!-- Gráfico + Últimas movimentações -->
        <div class="rep-chart-wrap">
            <h3><span class="material-icons-round">bar_chart</span> Distribuição por Status</h3>
            <div class="rep-chart-inner">
                <div class="rep-chart-canvas">
                    <canvas id="trackerStatusChart" style="max-height:220px;"></canvas>
                </div>
                <div class="rep-timeline" style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:.85rem;">
                        <thead>
                            <tr>
                                <th
                                    style="padding:8px 12px; border-bottom:1px solid var(--border); text-align:left; font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">
                                    Data</th>
                                <th
                                    style="padding:8px 12px; border-bottom:1px solid var(--border); text-align:left; font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">
                                    Equipamento</th>
                                <th
                                    style="padding:8px 12px; border-bottom:1px solid var(--border); text-align:left; font-size:.75rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">
                                    Ação</th>
                            </tr>
                        </thead>
                        <tbody id="repMovTable">
                            <tr>
                                <td colspan="3" style="text-align:center; padding:24px; color:var(--text-muted);">Use os
                                    filtros acima e clique em Filtrar</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tabela detalhada -->
        <div class="rep-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Modelo</th>
                        <th>Serial Number</th>
                        <th>ICCID (Chip)</th>
                        <th>Status</th>
                        <th>Cadastro</th>
                    </tr>
                </thead>
                <tbody id="repDetailTable">
                    <tr>
                        <td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">Use os filtros
                            acima e clique em Filtrar</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div><!-- /tabReports -->
</div><!-- /container -->

<!-- =====================================================
     MODAIS
===================================================== -->

<!-- Modal Criar/Editar Tracker -->
<div class="modal-overlay" id="trackerModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalTitle">Cadastrar Rastreador</h2>
            <button class="modal-close" onclick="closeModal()"><span class="material-icons-round">close</span></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="trackerId">
            <div class="form-group">
                <label>Modelo do Rastreador (Opcional)</label>
                <input type="text" class="form-control" id="trackerModel" placeholder="Ex: Coban, TK303...">
            </div>
            <div class="form-group">
                <label>Serial Number (SN) *</label>
                <input type="text" class="form-control" id="trackerSerial" placeholder="Número de série único" required>
            </div>
            <div class="form-group">
                <label>ICCID do Chip (Opcional)</label>
                <input type="text" class="form-control" id="trackerChip" placeholder="8955...">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select class="form-control" id="trackerStatus" onchange="toggleMotivoTracker(this.value)">
                    <option value="Estoque">Estoque</option>
                    <option value="Enviado">Enviado</option>
                    <option value="Recebido">Recebido</option>
                    <option value="Defeito">Com Defeito</option>
                    <option value="Em Manutenção">Em Manutenção</option>
                </select>
            </div>
            <div class="form-group" id="trackerMotivGrp" style="display:none;">
                <label style="color:#E65100; display:flex; align-items:center; gap:4px;">
                    <span class="material-icons-round" style="font-size:16px;">warning</span>
                    Motivo / Descrição do Problema *
                </label>
                <textarea class="form-control" id="trackerMotivo" rows="3"
                    placeholder="Descreva o defeito ou motivo da manutenção..."
                    style="border-color:#FFB74D; resize:vertical;"></textarea>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-outline" style="margin-right:auto; background:#FFEBEE; color:#C62828; border:none;"
                id="btnDeleteTracker" onclick="deleteTracker()" style="display:none;">&#128465; Excluir
                Cadastro</button>
            <button class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-teal" onclick="saveTracker()">Salvar</button>
        </div>
    </div>
</div>

<!-- Modal Histórico -->
<div class="modal-overlay" id="historyModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h2>Histórico do Equipamento</h2>
            <button class="modal-close" onclick="closeHistoryModal()"><span
                    class="material-icons-round">close</span></button>
        </div>
        <div class="modal-body" id="historyContent">
            <!-- carregado por js -->
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="<?= $toolPath ?>/assets/js/import_helper.js?v=<?= time() ?>"></script>
<script>
function initTrackerImport() {
    openImportModal({
        endpoint: 'api/import.php',
        fields: [
            { key: 'model', label: 'Modelo' },
            { key: 'serial_number', label: 'Número de Série' },
            { key: 'chip_iccid', label: 'ICCID do Chip' },
            { key: 'status', label: 'Status' }
        ],
        onSuccess: () => {
            if (typeof loadTrackerTable === 'function') loadTrackerTable();
            else location.reload();
        }
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/trackers.js?v=<?= time() ?>"></script>
<script>
    // ============================================================
    // REPORTS TAB
    // ============================================================
    let trackerChartInstance = null;

    function switchTab(tab) {
        document.getElementById('tabEstoque').style.display = tab === 'estoque' ? 'block' : 'none';
        document.getElementById('tabReports').style.display = tab === 'reports' ? 'block' : 'none';
        document.getElementById('btnTabEstoque').className = 'tool-tab ' + (tab === 'estoque' ? 'active' : 'inactive');
        document.getElementById('btnTabReports').className = 'tool-tab ' + (tab === 'reports' ? 'active' : 'inactive');
        if (tab === 'reports') loadTrackerReport();
    }

    async function loadTrackerReport() {
        const from = document.getElementById('repFrom').value;
        const to = document.getElementById('repTo').value;
        const status = document.getElementById('repStatus').value;

        document.getElementById('repMovTable').innerHTML = '<tr><td colspan="3"><div class="spinner"></div></td></tr>';
        document.getElementById('repDetailTable').innerHTML = '<tr><td colspan="6"><div class="spinner"></div></td></tr>';

        const res = await fetch('api/trackers.php?action=reports&date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to) + '&status=' + encodeURIComponent(status));
        const data = await res.json();
        if (!data.success) { toast('Erro ao carregar relatório', 'error'); return; }

        // Stats
        document.getElementById('rsTotal').textContent = data.stats.total || 0;
        document.getElementById('rsEstoque').textContent = data.stats['Estoque'] || 0;
        document.getElementById('rsEnviado').textContent = data.stats['Enviado'] || 0;
        document.getElementById('rsRecebido').textContent = data.stats['Recebido'] || 0;
        document.getElementById('rsDefeito').textContent = data.stats['Defeito'] || 0;
        document.getElementById('rsManutencao').textContent = data.stats['Em Manutenção'] || 0;

        // Chart
        renderTrackerChart(data.stats);

        // Movimentações recentes
        if (data.events && data.events.length > 0) {
            document.getElementById('repMovTable').innerHTML = data.events.map(ev => `
            <tr>
                <td style="padding:8px 12px; border-bottom:1px solid #f0f0f0; font-size:.78rem; color:var(--text-muted);">${new Date(ev.created_at).toLocaleString('pt-BR')}</td>
                <td style="padding:8px 12px; border-bottom:1px solid #f0f0f0;"><strong>${esc(ev.model || 'Rastreador')}</strong><br><small style="color:#888">${esc(ev.serial_number)}</small></td>
                <td style="padding:8px 12px; border-bottom:1px solid #f0f0f0;">${esc(ev.action)}</td>
            </tr>
        `).join('');
        } else {
            document.getElementById('repMovTable').innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:var(--text-muted);">Nenhuma movimentação no período</td></tr>';
        }

        // Tabela detalhada
        if (data.equipments && data.equipments.length > 0) {
            const badges = { 'Estoque': 'sbadge-estoque', 'Enviado': 'sbadge-enviado', 'Recebido': 'sbadge-recebido', 'Defeito': 'sbadge-defeito', 'Em Manutenção': 'sbadge-manutencao' };
            document.getElementById('repDetailTable').innerHTML = data.equipments.map(eq => `
            <tr>
                <td style="color:var(--text-muted)">${eq.id}</td>
                <td><strong>${esc(eq.model || '—')}</strong></td>
                <td style="font-family:monospace; font-size:.85rem;">${esc(eq.serial_number)}</td>
                <td style="font-size:.82rem; color:var(--text-muted);">${esc(eq.chip_iccid || '—')}</td>
                <td><span class="sbadge ${badges[eq.status] || ''}">${esc(eq.status)}</span></td>
                <td style="font-size:.82rem; color:var(--text-muted);">${new Date(eq.created_at).toLocaleDateString('pt-BR')}</td>
            </tr>
        `).join('');
        } else {
            document.getElementById('repDetailTable').innerHTML = '<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">Nenhum equipamento encontrado no período</td></tr>';
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
                    data: [stats['Estoque'] || 0, stats['Enviado'] || 0, stats['Recebido'] || 0, stats['Defeito'] || 0, stats['Em Manutenção'] || 0],
                    backgroundColor: ['#00897B', '#1E88E5', '#43A047', '#E53935', '#F57F17'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: { legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 8 } } }
            }
        });
    }

    function exportTrackerReport() {
        const from = document.getElementById('repFrom').value;
        const to = document.getElementById('repTo').value;
        window.open('api/trackers.php?action=export&date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to), '_blank');
    }

    function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
</script>

</body>

</html>