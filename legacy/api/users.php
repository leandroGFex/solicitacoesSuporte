<?php
// =============================================================
// API - GERENCIAMENTO DE USUÁRIOS (Admin only)
// =============================================================
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// Apenas admin pode gerenciar usuários
if ($_SESSION['user_role'] !== 'admin' && !in_array($action, ['profile', 'change_password'])) {
    echo json_encode(['error' => 'Apenas administradores podem gerenciar usuários']);
    exit;
}

switch ($action) {
    case 'list':
        listUsers();
        break;
    case 'create':
        createUser();
        break;
    case 'update':
        updateUser();
        break;
    case 'toggle_active':
        toggleActive();
        break;
    case 'change_password':
        changePassword();
        break;
    case 'delete':
        deleteUser();
        break;
    default:
        echo json_encode(['error' => 'Ação inválida']);
}

function listUsers()
{
    $db = getDB();
    $users = $db->query("SELECT id, name, email, role, active, created_at FROM users ORDER BY name ASC")->fetchAll();
    echo json_encode(['success' => true, 'users' => $users]);
}

function createUser()
{
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'user';

    if (empty($name) || empty($email) || empty($pass)) {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
        return;
    }

    $db = getDB();
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'E-mail já cadastrado']);
        return;
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
    $stmt->execute([$name, $email, $hash, $role]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
}

function updateUser()
{
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';

    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
    $stmt->execute([$name, $email, $role, $id]);
    echo json_encode(['success' => true]);
}

function toggleActive()
{
    $id = (int) ($_POST['id'] ?? 0);
    $db = getDB();
    $db->prepare("UPDATE users SET active = NOT active WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
}

function changePassword()
{
    $id = (int) ($_POST['id'] ?? $_SESSION['user_id']);
    $newPass = trim($_POST['password'] ?? '');

    // Apenas admin pode trocar senha de outros; usuário pode trocar a própria
    if ($_SESSION['user_role'] !== 'admin' && $id != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        return;
    }

    if (strlen($newPass) < 6) {
        echo json_encode(['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres']);
        return;
    }

    $db = getDB();
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
    echo json_encode(['success' => true]);
}

function deleteUser()
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === (int) $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Você não pode deletar a sua própria conta']);
        return;
    }
    $db = getDB();
    $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
}
