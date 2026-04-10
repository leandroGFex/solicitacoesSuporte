<?php
// api/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'login') {
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if (!$user['active']) {
            echo json_encode(['success' => false, 'message' => 'Usuário inativo']);
            exit;
        }

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Credenciais inválidas']);
    }
} elseif ($action === 'logout') {
    session_destroy();
    header('Location: ../index.php?page=login');
    exit;
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}
