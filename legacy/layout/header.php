<?php
// =============================================================
// HEADER - LAYOUT PRINCIPAL
// =============================================================
$global_page = $_GET['page'] ?? 'dashboard';

$page_title = 'Dashboard';
if ($global_page === 'board') {
    $page_title = 'Solicitações';
} elseif ($global_page === 'reports') {
    $page_title = 'Relatórios';
} elseif ($global_page === 'settings') {
    $page_title = 'Configurações';
} elseif ($global_page === 'manual') {
    $page_title = 'Manual';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= APP_NAME ?> — <?= $page_title ?>
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
</head>

<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <a href="index.php" style="text-decoration:none; display:flex; flex-direction:column;">
                <img src="assets/img/logo_white.png" alt="GRUPO FLEX"
                    style="height: 40px; width: auto; object-fit: contain;">
            </a>
        </div>
        <div class="navbar-actions">
            <?php if ($global_page === 'board'): ?>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <button onclick="openAutomationsModal()" class="btn-nav btn-secondary" title="Configurar Automações">
                    <span class="material-icons-round">bolt</span> Regras
                </button>
                <?php endif; ?>
                <div class="category-selector-nav">
                    <select id="boardCategoryFilter" class="form-control-nav" onchange="changeBoardCategory(this.value)">
                        <option value="cartao">💳 Cartão</option>
                        <option value="tag">🏷️ Tag</option>
                        <option value="pos">📟 POS</option>
                        <option value="rastreador">📡 Rastreador</option>
                    </select>
                </div>
                <button onclick="readEmails()" class="btn-nav btn-secondary" title="Ler e-mails (requer config.)">
                    <span class="material-icons-round">email</span> Ler E-mails
                </button>
                <button onclick="updateAllTracking()" class="btn-nav btn-secondary" id="btnUpdateTracking">
                    <span class="material-icons-round">radar</span> Atualizar Rastreios
                </button>
                <button onclick="openNewCardModal()" class="btn-nav btn-primary">
                    <span class="material-icons-round">add</span> Nova Solicitação
                </button>
            <?php endif; ?>
            <div class="navbar-user">
                <?php if (!in_array($global_page, ['dashboard', 'manual', 'procedures'])): ?>
                    <a href="index.php?page=reports" class="btn-nav btn-ghost" title="Relatórios">
                        <span class="material-icons-round">assessment</span>
                    </a>
                <?php endif; ?>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="index.php?page=settings" class="btn-nav btn-ghost" title="Configurações">
                        <span class="material-icons-round">settings</span>
                    </a>
                <?php endif; ?>
                <div class="user-menu" onclick="toggleUserMenu()">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                    </div>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-info">
                            <strong>
                                <?= htmlspecialchars($_SESSION['user_name']) ?>
                            </strong>
                            <small>
                                <?= $_SESSION['user_role'] === 'admin' ? 'Administrador' : 'Usuário' ?>
                            </small>
                        </div>
                        <hr>
                        <a href="#" onclick="openChangePassword()"><span class="material-icons-round">lock</span>
                            Alterar Senha</a>
                        <a href="api/auth.php?action=logout" onclick="return confirmLogout()"><span
                                class="material-icons-round">logout</span> Sair</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <script>
        // Start background automation
        setTimeout(() => {
            fetch('api/cron_tracking.php').catch(() => { });
            fetch('api/cron_mail.php').catch(() => { });
        }, 15000);
    </script>