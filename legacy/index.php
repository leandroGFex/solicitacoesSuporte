<?php
// =============================================================
// ROTEADOR PRINCIPAL
// =============================================================
session_start();
require_once __DIR__ . '/config/config.php';

$page = $_GET['page'] ?? 'dashboard';

// Redirecionar para login se não estiver logado
if (empty($_SESSION['logged_in']) && $page !== 'login') {
    header('Location: index.php?page=login');
    exit;
}

$allowedPages = ['dashboard', 'login', 'board', 'reports', 'settings', 'manual', 'procedures'];
if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

switch ($page) {
    case 'login':
        include __DIR__ . '/pages/login.php';
        break;
    case 'dashboard':
        include __DIR__ . '/pages/dashboard.php';
        break;
    case 'board':
        include __DIR__ . '/pages/board.php';
        break;
    case 'reports':
        include __DIR__ . '/pages/reports.php';
        break;
    case 'settings':
        include __DIR__ . '/pages/settings.php';
        break;
    case 'manual':
        include __DIR__ . '/pages/manual.php';
        break;
    case 'procedures':
        include __DIR__ . '/pages/procedures.php';
        break;
}
