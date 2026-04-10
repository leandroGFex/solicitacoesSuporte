<?php
// =============================================================
// API - COLUNAS DO KANBAN
// =============================================================
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/migration_helper.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// Garantir integridade do banco (especialmente se o usuário trocou de DB)
$db = getDB();
runAutoMigration($db);

switch ($action) {
    case 'list':
        listColumns();
        break;
    case 'create':
        createColumn();
        break;
    case 'update':
        updateColumn();
        break;
    case 'delete':
        deleteColumn();
        break;
    case 'reorder':
        reorderColumns();
        break;
    default:
        echo json_encode(['error' => 'Ação inválida']);
}

function listColumns()
{
    $category = $_GET['category'] ?? 'cartao';
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM columns_kanban WHERE category = ? ORDER BY position ASC");
    $stmt->execute([$category]);
    $columns = $stmt->fetchAll();

    foreach ($columns as &$col) {
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM cards WHERE column_id = ? AND is_archived = 0");
        $stmtCount->execute([$col['id']]);
        $col['card_count'] = (int) $stmtCount->fetchColumn();
    }
    echo json_encode(['success' => true, 'columns' => $columns]);
}

function createColumn()
{
    if ($_SESSION['user_role'] !== 'admin') {
        echo json_encode(['error' => 'Apenas admins podem criar colunas']);
        return;
    }
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#00897B');
    $icon = trim($_POST['icon'] ?? 'label');
    $category = $_POST['category'] ?? 'cartao';

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
        return;
    }

    $db = getDB();
    $stmtMax = $db->prepare("SELECT COALESCE(MAX(position),0) FROM columns_kanban WHERE category = ?");
    $stmtMax->execute([$category]);
    $maxPos = $stmtMax->fetchColumn();
    
    $stmt = $db->prepare("INSERT INTO columns_kanban (name, color, icon, position, category) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $color, $icon, $maxPos + 1, $category]);
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
}

function updateColumn()
{
    if ($_SESSION['user_role'] !== 'admin') {
        echo json_encode(['error' => 'Apenas admins podem editar colunas']);
        return;
    }
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#00897B');
    $icon = trim($_POST['icon'] ?? 'label');

    $db = getDB();
    $stmt = $db->prepare("UPDATE columns_kanban SET name=?, color=?, icon=? WHERE id=?");
    $stmt->execute([$name, $color, $icon, $id]);
    echo json_encode(['success' => true]);
}

function deleteColumn()
{
    if ($_SESSION['user_role'] !== 'admin') {
        echo json_encode(['error' => 'Apenas admins podem deletar colunas']);
        return;
    }
    $id = (int) ($_POST['id'] ?? 0);
    $db = getDB();

    $count = $db->prepare("SELECT COUNT(*) FROM cards WHERE column_id = ?");
    $count->execute([$id]);
    if ($count->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Mova ou exclua os cards desta coluna antes de deletá-la.']);
        return;
    }
    $db->prepare("DELETE FROM columns_kanban WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
}

function reorderColumns()
{
    if ($_SESSION['user_role'] !== 'admin') {
        echo json_encode(['error' => 'Apenas admins podem reordenar colunas']);
        return;
    }
    $order = json_decode($_POST['order'] ?? '[]', true);
    $db = getDB();
    $stmt = $db->prepare("UPDATE columns_kanban SET position=? WHERE id=?");
    foreach ($order as $pos => $colId) {
        $stmt->execute([$pos + 1, (int) $colId]);
    }
    echo json_encode(['success' => true]);
}
