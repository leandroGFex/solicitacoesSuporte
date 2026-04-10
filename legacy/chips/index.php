<?php
// SIM Cards Inventory Page
require_once __DIR__ . '/../config/config.php';
session_start();

if (empty($_SESSION['logged_in'])) {
    header('Location: ../index.php?page=login');
    exit;
}

$db = getDB();

// Contagens para os cards informativos
$totalEstoque = $db->query("SELECT COUNT(*) FROM sim_cards WHERE status = 'Estoque' AND deleted_at IS NULL")->fetchColumn();
$totalEmUso = $db->query("SELECT COUNT(*) FROM sim_cards WHERE status = 'Em Uso' AND deleted_at IS NULL")->fetchColumn();
$totalCancelado = $db->query("SELECT COUNT(*) FROM sim_cards WHERE status = 'Cancelado' AND deleted_at IS NULL")->fetchColumn();
$totalDefeito = $db->query("SELECT COUNT(*) FROM sim_cards WHERE status = 'Defeito' AND deleted_at IS NULL")->fetchColumn();
$totalRetirada = $db->query("SELECT COUNT(*) FROM sim_cards WHERE status = 'Retirada' AND deleted_at IS NULL")->fetchColumn();
$totalReverso = $db->query("SELECT COUNT(*) FROM sim_cards WHERE status = 'Reverso' AND deleted_at IS NULL")->fetchColumn();
$totalGeral = $totalEstoque + $totalEmUso + $totalCancelado + $totalDefeito + $totalRetirada + $totalReverso;

// Shared header vars
$toolTitle = 'Estoque Chips';
$toolIcon = 'sim_card';
$toolPath = '..';
$toolReportsUrl = 'reports.php';
require_once __DIR__ . '/../layout/header_tool.php';
?>

<div style="background:var(--bg); min-height:calc(100vh - 56px);">

    <!-- Page sub-header -->
    <div class="tool-page-header">
        <h2>
            <span class="material-icons-round">sim_card</span>
            Controle de Chips (SIM)
        </h2>
        <div style="display:flex; gap:12px; align-items:center;">
            <button class="btn btn-outline" onclick="initChipsImport()">
                <span class="material-icons-round">upload_file</span> Importar de Excel
            </button>
            <button class="btn btn-yellow" onclick="openModal()">
                <span class="material-icons-round">add</span> Novo Chip
            </button>
        </div>
    </div>

    <!-- =====================================================
         STATS GRID — always visible
    ===================================================== -->
    <div class="inv-stats-grid">
        <div class="inv-stat-card" style="border-left-color:var(--primary-dark)">
            <div class="stat-label">Total</div>
            <div class="stat-value" style="color:var(--primary-dark)">
                <?= $totalGeral ?>
            </div>
        </div>
        <div class="inv-stat-card">
            <div class="stat-label">Em Estoque</div>
            <div class="stat-value">
                <?= $totalEstoque ?>
            </div>
        </div>
        <div class="inv-stat-card" style="border-left-color:#1E88E5">
            <div class="stat-label">Em Uso</div>
            <div class="stat-value" style="color:#1E88E5">
                <?= $totalEmUso ?>
            </div>
        </div>
        <div class="inv-stat-card" style="border-left-color:#E53935">
            <div class="stat-label">Cancelados</div>
            <div class="stat-value" style="color:#E53935">
                <?= $totalCancelado ?>
            </div>
        </div>
        <div class="inv-stat-card defeito">
            <div class="stat-label">Com Defeito</div>
            <div class="stat-value">
                <?= $totalDefeito ?>
            </div>
        </div>
        <div class="inv-stat-card" style="border-left-color:#7B1FA2">
            <div class="stat-label">Retirada</div>
            <div class="stat-value" style="color:#7B1FA2">
                <?= $totalRetirada ?>
            </div>
        </div>
        <div class="inv-stat-card" style="border-left-color:#5D4037">
            <div class="stat-label">Reverso</div>
            <div class="stat-value" style="color:#5D4037">
                <?= $totalReverso ?>
            </div>
        </div>
    </div>

    <!-- =====================================================
         TAB: ESTOQUE
    ===================================================== -->
    <div id="tabEstoque">
        <div
            style="background:var(--surface); border-radius:var(--radius-md); border:1px solid var(--border); margin:0 24px 24px; box-shadow:var(--shadow-sm); overflow:hidden;">
            <div
                style="padding:14px 20px; border-bottom:1px solid var(--border); display:flex; gap:12px; justify-content:space-between; align-items:center;">
                <input type="text" id="searchInput" placeholder="Buscar por linha, ICCID ou operadora..."
                    style="border:1.5px solid var(--border); border-radius:var(--radius-md); padding:8px 14px; font-size:.88rem; font-family:'Inter',sans-serif; outline:none; flex:1;"
                    onkeyup="filterTable()" onfocus="this.style.borderColor='var(--primary)'"
                    onblur="this.style.borderColor='var(--border)'">

                <select id="filterType" onchange="filterTable()" class="form-control" style="width:200px;">
                    <option value="">Todas as Categorias</option>
                    <option value="POS">POS</option>
                    <option value="Rastreador">Rastreadores</option>
                </select>

                <select id="filterStatus" onchange="filterTable()" class="form-control" style="width:200px;">
                    <option value="">Todos os Status</option>
                    <option value="Estoque">Em Estoque</option>
                    <option value="Em Uso">Em Uso</option>
                    <option value="Cancelado">Cancelado</option>
                    <option value="Defeito">Com Defeito</option>
                    <option value="Retirada">Retirada</option>
                    <option value="Reverso">Reverso</option>
                </select>
            </div>
            <div style="overflow-x:auto;">
                <table id="chipsTable" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#fcfcfc;">
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
                            <th
                                style="padding:12px 14px; text-transform:uppercase; font-size:.75rem; color:#888; font-weight:700; letter-spacing:.5px; border-bottom:1px solid #eee;">
                                Ações</th>
                        </tr>
                    </thead>
                    <tbody id="chipsTableBody">
                        <tr>
                            <td colspan="7" style="text-align:center; padding:32px; color:var(--text-muted);">
                                Carregando...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /container -->

<!-- =====================================================
     MODAIS
===================================================== -->

<!-- Modal Criar/Editar Chip -->
<div class="modal-overlay" id="chipModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modalTitle">Cadastrar Chip</h2>
            <button class="modal-close" onclick="closeModal()"><span class="material-icons-round">close</span></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="chipId">
            <div class="form-group">
                <label>Categoria *</label>
                <select class="form-control" id="chipType" required>
                    <option value="POS">POS</option>
                    <option value="Rastreador">Rastreador</option>
                </select>
            </div>
            <div class="form-group">
                <label id="lblChipPhone">Número da Linha *</label>
                <input type="text" class="form-control" id="chipPhone" placeholder="Ex: 11999999999">
            </div>
            <div class="form-group">
                <label>ICCID *</label>
                <input type="text" class="form-control" id="chipIccid" placeholder="Ex: 895512..." required>
            </div>
            <div class="form-group">
                <label>Operadora</label>
                <input type="text" class="form-control" id="chipCarrier" placeholder="Ex: Vivo, Claro, TIM, Algar...">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select class="form-control" id="chipStatus" onchange="toggleMotivo(this.value)">
                    <option value="Estoque">Estoque</option>
                    <option value="Em Uso">Em Uso</option>
                    <option value="Cancelado">Cancelado</option>
                    <option value="Defeito">Com Defeito</option>
                    <option value="Retirada">Retirada</option>
                    <option value="Reverso">Reverso</option>
                </select>
            </div>
            <div class="form-group" id="chipMotivGrp" style="display:none;">
                <label style="color:#E65100; display:flex; align-items:center; gap:4px;">
                    <span class="material-icons-round" style="font-size:16px;">warning</span>
                    Motivo / Observação *
                </label>
                <textarea class="form-control" id="chipMotivo" rows="3" placeholder="Por que cancelou ou deu defeito?"
                    style="border-color:#FFB74D; resize:vertical;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" style="margin-right:auto; background:#FFEBEE; color:#C62828; border:none;"
                id="btnDeleteChip" onclick="deleteChip()" style="display:none;">&#128465; Excluir Conta</button>
            <button class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-teal" onclick="saveChip()">Salvar</button>
        </div>
    </div>
</div>

<!-- Modal Histórico -->
<div class="modal-overlay" id="historyModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h2>Histórico do Chip</h2>
            <button class="modal-close" onclick="closeHistoryModal()"><span
                    class="material-icons-round">close</span></button>
        </div>
        <div class="modal-body" id="historyContent">
            <!-- JS vai jogar aqui -->
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="<?= $toolPath ?>/assets/js/import_helper.js?v=<?= time() ?>"></script>
<script>
function initChipsImport() {
    openImportModal({
        endpoint: 'api/import.php',
        fields: [
            { key: 'phone_number', label: 'Linha (Número)' },
            { key: 'iccid', label: 'ICCID' },
            { key: 'type', label: 'Tipo (POS/Rastreador)' },
            { key: 'carrier', label: 'Operadora' },
            { key: 'status', label: 'Status' }
        ],
        onSuccess: () => {
            if (typeof loadChips === 'function') loadChips();
            else location.reload();
        }
    });
}
</script>
<script src="assets/js/chips.js?v=<?= time() ?>"></script>
<script>
    function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
</script>

</body>

</html>