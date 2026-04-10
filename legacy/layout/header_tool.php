<?php
// =============================================================
// HEADER TOOL — Shared header for POS and Trackers pages
// Expects: $toolTitle (string), $toolIcon (material icon name), $toolPath (relative prefix for assets)
// =============================================================
$toolTitle = $toolTitle ?? 'Ferramenta';
$toolIcon = $toolIcon ?? 'build';
$toolPath = $toolPath ?? '..';
$toolReportsUrl = $toolReportsUrl ?? '';

// Global Low Stock Warning Check
try {
    $dbLocal = getDB();
    $stmtLow = $dbLocal->query("SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL AND quantity <= min_quantity");
    $globalLowStockCount = $stmtLow->fetchColumn();
} catch (Exception $e) {
    $globalLowStockCount = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= APP_NAME ?> —
        <?= htmlspecialchars($toolTitle) ?>
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?= $toolPath ?>/assets/css/style.css">
    <link rel="icon" type="image/png" href="<?= $toolPath ?>/assets/img/favicon.png">
    <!-- SheetJS for Excel Import -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <style>
        /* === Tool-specific overrides === */
        body {
            background: var(--bg);
        }

        /* Stats cards for inventory pages */
        .inv-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            padding: 0 24px 20px;
        }

        .inv-stat-card {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            border-left: 4px solid var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .inv-stat-card .stat-label {
            font-size: .78rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 4px;
        }

        .inv-stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .inv-stat-card.defeito {
            border-left-color: var(--danger);
        }

        .inv-stat-card.defeito .stat-value {
            color: var(--danger);
        }

        .inv-stat-card.manutencao {
            border-left-color: var(--warning);
        }

        .inv-stat-card.manutencao .stat-value {
            color: var(--warning);
        }

        /* Reports section (Kanban-style) */
        .rep-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
            padding: 16px 24px;
            background: var(--surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            margin: 0 24px 20px;
            box-shadow: var(--shadow-sm);
        }

        .rep-filters .form-group {
            margin-bottom: 0;
            flex: 1;
            min-width: 160px;
        }

        .rep-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 14px;
            padding: 0 24px 20px;
        }

        .rep-stat {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            text-align: center;
            border-top: 3px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .rep-stat .rs-val {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text);
        }

        .rep-stat .rs-lbl {
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-top: 2px;
        }

        .rep-stat.rs-total {
            border-top-color: var(--primary-dark);
        }

        .rep-stat.rs-total .rs-val {
            color: var(--primary-dark);
        }

        .rep-stat.rs-estoque {
            border-top-color: var(--primary);
        }

        .rep-stat.rs-enviado {
            border-top-color: #1E88E5;
        }

        .rep-stat.rs-recebido {
            border-top-color: var(--success);
        }

        .rep-stat.rs-defeito {
            border-top-color: var(--danger);
        }

        .rep-stat.rs-defeito .rs-val {
            color: var(--danger);
        }

        .rep-stat.rs-manutencao {
            border-top-color: var(--warning);
        }

        .rep-stat.rs-manutencao .rs-val {
            color: var(--warning);
        }

        .rep-stat.rs-retirada {
            border-top-color: #7B1FA2;
        }

        .rep-stat.rs-retirada .rs-val {
            color: #7B1FA2;
        }

        .rep-stat.rs-reverso {
            border-top-color: #5D4037;
        }

        .rep-stat.rs-reverso .rs-val {
            color: #5D4037;
        }

        /* Chart area */
        .rep-chart-wrap {
            background: var(--surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            padding: 20px 24px;
            margin: 0 24px 20px;
            box-shadow: var(--shadow-sm);
        }

        .rep-chart-wrap h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rep-chart-inner {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .rep-chart-canvas {
            flex: 0 0 260px;
            max-height: 260px;
        }

        .rep-timeline {
            flex: 1;
            min-width: 260px;
        }

        /* Table wrapper */
        .rep-table-wrap {
            background: var(--surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            margin: 0 24px 24px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .rep-table-wrap table {
            width: 100%;
            border-collapse: collapse;
        }

        .rep-table-wrap thead th {
            background: #f8f9fa;
            padding: 10px 14px;
            text-align: left;
            font-size: .78rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .4px;
            border-bottom: 1px solid var(--border);
        }

        .rep-table-wrap tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid #f0f0f0;
            font-size: .88rem;
            color: var(--text);
            vertical-align: middle;
        }

        .rep-table-wrap tbody tr:hover td {
            background: #fafafa;
        }

        .rep-table-wrap tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status badges (used in inventory table and reports) */
        .sbadge,
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
        }

        .sbadge-estoque,
        .status-estoque {
            background: #E0F2F1;
            color: #004D40;
        }

        .sbadge-enviado,
        .status-enviado {
            background: #E3F2FD;
            color: #1565C0;
        }

        .sbadge-recebido,
        .status-recebido {
            background: #E8F5E9;
            color: #2E7D32;
        }

        .sbadge-defeito,
        .status-defeito {
            background: #FFEBEE;
            color: #C62828;
        }

        .sbadge-manutencao,
        .status-manutencao {
            background: #FFF3E0;
            color: #E65100;
        }

        .sbadge-retirada,
        .status-retirada {
            background: #F3E5F5;
            color: #7B1FA2;
        }

        .sbadge-reverso,
        .status-reverso {
            background: #EFEBE9;
            color: #5D4037;
        }

        /* Action buttons in table */
        .action-btns {
            display: flex;
            gap: 4px;
        }

        .btn-icon {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-muted);
            border-radius: 6px;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .btn-icon:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
            border-color: var(--primary);
        }

        .btn-icon .material-icons-round {
            font-size: 16px;
        }

        /* Tab switcher */
        .tool-tab-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, .15);
            padding: 4px;
            border-radius: var(--radius-md);
        }

        .tool-tab {
            padding: 6px 18px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: .82rem;
            cursor: pointer;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }

        .tool-tab.active {
            background: #fff;
            color: var(--primary-dark);
            box-shadow: 0 2px 6px rgba(0, 0, 0, .12);
        }

        .tool-tab.inactive {
            background: transparent;
            color: rgba(255, 255, 255, .75);
        }

        .tool-tab.inactive:hover {
            background: rgba(255, 255, 255, .15);
            color: #fff;
        }

        /* Page sub-header */
        .tool-page-header {
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .tool-page-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .tool-page-header .material-icons-round {
            color: var(--primary);
        }

        /* Spinner */
        .spinner {
            width: 28px;
            height: 28px;
            border: 3px solid rgba(0, 137, 123, .2);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 32px auto;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: .85rem;
            cursor: pointer;
            border: none;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-teal {
            background: var(--primary);
            color: #fff;
        }

        .btn-teal:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            background: #fff;
            border: 1.5px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover {
            background: #f5f5f5;
        }

        .btn-yellow {
            background: var(--accent);
            color: #333;
        }

        .btn-yellow:hover {
            background: var(--accent-dark);
        }

        .btn .material-icons-round {
            font-size: 18px;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            padding: 12px 18px;
            background: #333;
            color: #fff;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .2);
            animation: toastIn .3s ease;
        }

        .toast-success {
            background: #2E7D32;
        }

        .toast-error {
            background: #C62828;
        }

        @keyframes toastIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Change password modal */
        .tool-cp-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }

        .tool-cp-modal.open {
            display: flex;
        }

        .tool-cp-box {
            background: #fff;
            border-radius: var(--radius-lg);
            padding: 28px;
            min-width: 320px;
            max-width: 400px;
            width: 90%;
            box-shadow: var(--shadow-lg);
        }

        .tool-cp-box h3 {
            margin: 0 0 20px;
            color: var(--primary-dark);
            font-size: 1.05rem;
        }
    </style>
</head>

<body data-role="<?= htmlspecialchars($_SESSION['user_role'] ?? 'user') ?>">

    <nav class="navbar">
        <div class="navbar-brand">
            <a href="<?= $toolPath ?>/index.php"
                style="text-decoration:none; display:flex; align-items:center; gap:12px;">
                <img src="<?= $toolPath ?>/assets/img/logo_white.png" alt="GRUPO FLEX"
                    style="height:36px; width:auto; object-fit:contain;">
                <span style="color:rgba(255,255,255,.5); font-size:1.1rem; margin: 0 4px;">|</span>
                <span style="color:#A7FFEB; font-size:.92rem; font-weight:500;">
                    <?= htmlspecialchars($toolTitle) ?>
                </span>
            </a>
        </div>
        <div class="navbar-actions">
            <!-- Global Tools Shortcuts -->
            <div
                style="display:flex; gap:4px; align-items:center; <?= $toolReportsUrl ? 'margin-right:8px; border-right:1px solid rgba(255,255,255,0.2); padding-right:12px;' : '' ?>">
                <a href="<?= $toolPath ?>/pos/index.php" class="btn-nav btn-ghost" title="Máquinas POS"><span
                        class="material-icons-round" style="font-size:20px;">point_of_sale</span></a>
                <a href="<?= $toolPath ?>/trackers/index.php" class="btn-nav btn-ghost" title="Rastreadores"><span
                        class="material-icons-round" style="font-size:20px;">location_on</span></a>
                <a href="<?= $toolPath ?>/chips/index.php" class="btn-nav btn-ghost" title="Chips"><span
                        class="material-icons-round" style="font-size:20px;">sim_card</span></a>
                <a href="<?= $toolPath ?>/inventory/index.php" class="btn-nav btn-ghost" title="Estoque Geral"><span
                        class="material-icons-round" style="font-size:20px;">inventory_2</span></a>
            </div>

            <?php if (!empty($globalLowStockCount) && $globalLowStockCount > 0): ?>
                <a href="<?= $toolPath ?>/inventory/index.php" class="btn-nav btn-ghost"
                    style="background:rgba(216, 67, 21, 0.4); border:1px solid rgba(255,255,255,0.2);"
                    title="Aviso: <?= $globalLowStockCount ?> itens com estoque baixo!">
                    <span class="material-icons-round" style="color:#FFF;">warning</span>
                    <span
                        style="font-size:0.8rem; font-weight:700; color:#FFF; margin-left:4px;"><?= $globalLowStockCount ?></span>
                </a>
            <?php endif; ?>

            <?php if ($toolReportsUrl): ?>
                <a href="<?= htmlspecialchars($toolReportsUrl) ?>" class="btn-nav btn-ghost" title="Relatórios">
                    <span class="material-icons-round">assessment</span>
                </a>
            <?php endif; ?>
            <div class="navbar-user" <?= !$toolReportsUrl ? 'style="border-left:none; padding-left:0; margin-left:8px;"' : '' ?>>
                <div class="user-menu" onclick="toggleUserMenu()">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-info">
                            <strong>
                                <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?>
                            </strong>
                            <small>
                                <?= ($_SESSION['user_role'] ?? '') === 'admin' ? 'Administrador' : 'Usuário' ?>
                            </small>
                        </div>
                        <hr>
                        <a href="#" onclick="openChangePw(); return false;">
                            <span class="material-icons-round">lock</span> Alterar Senha
                        </a>
                        <a href="<?= $toolPath ?>/api/auth.php?action=logout" onclick="return confirm('Deseja sair?')">
                            <span class="material-icons-round">logout</span> Sair
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Modal Alterar Senha -->
    <div class="tool-cp-modal" id="changePwModal">
        <div class="tool-cp-box">
            <h3>Alterar Senha</h3>
            <div class="form-group">
                <label>Senha Atual</label>
                <input type="password" id="cpCurrent" class="form-control">
            </div>
            <div class="form-group">
                <label>Nova Senha</label>
                <input type="password" id="cpNew" class="form-control">
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label>Confirmar Nova Senha</label>
                <input type="password" id="cpConfirm" class="form-control">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button onclick="closeChangePw()" class="btn btn-outline">Cancelar</button>
                <button onclick="saveNewPassword()" class="btn btn-teal">Salvar</button>
            </div>
        </div>
    </div>

    <script>
        function toggleUserMenu() {
            const dd = document.getElementById('userDropdown');
            dd.classList.toggle('open');
        }
        document.addEventListener('click', function (e) {
            const menu = document.querySelector('.user-menu');
            if (menu && !menu.contains(e.target)) {
                document.getElementById('userDropdown').classList.remove('open');
            }
        });
        function openChangePw() {
            document.getElementById('userDropdown').classList.remove('open');
            document.getElementById('changePwModal').classList.add('open');
        }
        function closeChangePw() { document.getElementById('changePwModal').classList.remove('open'); }
        async function saveNewPassword() {
            const cur = document.getElementById('cpCurrent').value;
            const nw = document.getElementById('cpNew').value;
            const cf = document.getElementById('cpConfirm').value;
            if (!cur || !nw || !cf) { toolToast('Preencha todos os campos', 'error'); return; }
            if (nw !== cf) { toolToast('Senhas não coincidem', 'error'); return; }
            const form = new FormData();
            form.append('action', 'change_password');
            form.append('current_password', cur);
            form.append('new_password', nw);
            const res = await fetch('<?= $toolPath ?>/api/auth.php', { method: 'POST', body: form });
            const data = await res.json();
            if (data.success) { toolToast('Senha alterada!', 'success'); closeChangePw(); }
            else { toolToast(data.message || 'Erro ao alterar senha', 'error'); }
        }
        function toolToast(msg, type = 'info') {
            const el = document.createElement('div');
            el.className = `toast toast-${type}`;
            el.innerHTML = `<span class="material-icons-round">${type === 'success' ? 'check_circle' : 'error'}</span> ${msg}`;
            document.getElementById('toastContainer').appendChild(el);
            setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3000);
        }
    </script>