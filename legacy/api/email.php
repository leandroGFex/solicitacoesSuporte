<?php
// =============================================================
// API - LER E-MAILS E CRIAR CARDS
// =============================================================
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/mail_reader.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'read';

switch ($action) {
    case 'read':
        readEmails();
        break;
    case 'test':
        testConnection();
        break;
    default:
        echo json_encode(['error' => 'Ação inválida']);
}

// =============================================================
// Ler e-mails não lidos e criar cards (Unificado)
// =============================================================
function readEmails()
{
    if (!EMAIL_ENABLED) {
        echo json_encode(['success' => false, 'message' => 'E-mail desativado.']);
        return;
    }

    require_once __DIR__ . '/../helpers/automation_helper.php';
    require_once __DIR__ . '/../helpers/mail_reader.php';
    
    // O script internal_mail_reader incrementa $criados
    ob_start();
    include __DIR__ . '/internal_mail_reader.php';
    ob_end_clean();

    echo json_encode([
        'success' => true,
        'criados' => $criados ?? 0,
        'message' => ($criados ?? 0) > 0
            ? "$criados card(s) criado(s) a partir de e-mails!"
            : "Nenhum e-mail novo encontrado."
    ]);
}

// =============================================================
// Testar conexão IMAP (usado nas configurações)
// =============================================================
function testConnection()
{
    if ($_SESSION['user_role'] !== 'admin') {
        echo json_encode(['error' => 'Apenas admins podem testar a conexão']);
        return;
    }

    $host = trim($_POST['host'] ?? (defined('IMAP_HOST') ? IMAP_HOST : ''));
    $port = (int) ($_POST['port'] ?? (defined('IMAP_PORT') ? IMAP_PORT : 0));
    $user = trim($_POST['user'] ?? (defined('IMAP_USER') ? IMAP_USER : ''));
    $pass = trim($_POST['pass'] ?? (defined('IMAP_PASS') ? IMAP_PASS : ''));
    $ssl = (bool) ($_POST['ssl'] ?? (defined('IMAP_SSL') ? IMAP_SSL : true));
    $folderInput = trim($_POST['folder'] ?? '');
    $folder = $folderInput !== '' ? $folderInput : (defined('IMAP_FOLDER') ? IMAP_FOLDER : 'INBOX');

    if (!function_exists('imap_open')) {
        echo json_encode(['success' => false, 'message' => 'Extensão IMAP não disponível.']);
        return;
    }

    $flag = $ssl ? '/ssl/novalidate-cert' : '/notls';
    $mailbox = "{{$host}:{$port}/imap{$flag}}{$folder}";
    $conn = @imap_open($mailbox, $user, $pass, 0, 1);

    if ($conn) {
        $count = imap_num_msg($conn);
        imap_close($conn);
        echo json_encode(['success' => true, 'message' => "Conexão OK! $count mensagem(ns)."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha: ' . imap_last_error()]);
    }
}
