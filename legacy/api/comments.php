<?php
// =============================================================
// API - COMENTÁRIOS DO KANBAN
// =============================================================
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        listComments();
        break;
    case 'create':
        createComment();
        break;
    case 'delete':
        deleteComment();
        break;
    case 'update':
        updateComment();
        break;
    default:
        echo json_encode(['error' => 'Ação inválida']);
}

function listComments()
{
    $cardId = (int) ($_GET['card_id'] ?? 0);
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM comments WHERE card_id = ? ORDER BY created_at ASC");
    $stmt->execute([$cardId]);
    echo json_encode(['success' => true, 'comments' => $stmt->fetchAll()]);
}

function createComment()
{
    $cardId = (int) ($_POST['card_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (!$cardId || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        return;
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO comments (card_id, user_id, user_name, content) VALUES (?,?,?,?)");
    $stmt->execute([$cardId, $_SESSION['user_id'], $_SESSION['user_name'], $content]);
    echo json_encode([
        'success' => true,
        'id' => $db->lastInsertId(),
        'user_name' => $_SESSION['user_name'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

function deleteComment()
{
    $id = (int) ($_POST['id'] ?? 0);
    $db = getDB();
    // Apenas admin ou próprio autor pode deletar
    $stmt = $db->prepare("SELECT user_id FROM comments WHERE id=?");
    $stmt->execute([$id]);
    $comment = $stmt->fetch();
    if (!$comment) {
        echo json_encode(['success' => false, 'message' => 'Comentário não encontrado']);
        return;
    }
    if ($_SESSION['user_role'] !== 'admin' && $comment['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        return;
    }
    $db->prepare("DELETE FROM comments WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
}

function updateComment()
{
    $id = (int) ($_POST['id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (!$id || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        return;
    }

    $db = getDB();
    // Apenas admin ou próprio autor pode editar
    $stmt = $db->prepare("SELECT user_id FROM comments WHERE id=?");
    $stmt->execute([$id]);
    $comment = $stmt->fetch();

    if (!$comment) {
        echo json_encode(['success' => false, 'message' => 'Comentário não encontrado']);
        return;
    }

    if ($_SESSION['user_role'] !== 'admin' && $comment['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão para editar este comentário']);
        return;
    }

    $stmt = $db->prepare("UPDATE comments SET content=? WHERE id=?");
    $stmt->execute([$content, $id]);

    echo json_encode(['success' => true, 'message' => 'Comentário atualizado']);
}