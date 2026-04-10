<?php
// =============================================================
// API - AUTOMATIONS (CONTRÔLE DE REGRAS)
// =============================================================
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/migration_helper.php';

// Garantir integridade do banco
$db = getDB();
runAutoMigration($db);

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        listRules();
        break;
    case 'save':
        saveRule();
        break;
    case 'delete':
        deleteRule();
        break;
    case 'toggle':
        toggleRule();
        break;
    default:
        echo json_encode(['error' => 'Ação inválida']);
}

function listRules() {
    $category = $_GET['category'] ?? 'cartao';
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM automation_rules WHERE category = ? ORDER BY created_at DESC");
    $stmt->execute([$category]);
    $rules = $stmt->fetchAll();

    foreach ($rules as &$rule) {
        $stmtActions = $db->prepare("SELECT * FROM automation_actions WHERE rule_id = ? ORDER BY position ASC");
        $stmtActions->execute([$rule['id']]);
        $rule['actions'] = $stmtActions->fetchAll();
    }

    echo json_encode(['success' => true, 'rules' => $rules]);
}

function saveRule() {
    if ($_SESSION['user_role'] !== 'admin') {
        echo json_encode(['error' => 'Apenas admins podem gerenciar regras']);
        return;
    }

    $db = getDB();
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? 'cartao';
    $triggerEvent = $_POST['trigger_event'] ?? '';
    $triggerConfig = $_POST['trigger_config'] ?? '{}';
    $actions = json_decode($_POST['actions'] ?? '[]', true);

    if (empty($name) || empty($triggerEvent)) {
        echo json_encode(['success' => false, 'message' => 'Nome e Gatilho são obrigatórios']);
        return;
    }

    $db->beginTransaction();
    try {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE automation_rules SET name=?, category=?, trigger_event=?, trigger_config=? WHERE id=?");
            $stmt->execute([$name, $category, $triggerEvent, $triggerConfig, $id]);
            $db->prepare("DELETE FROM automation_actions WHERE rule_id=?")->execute([$id]);
        } else {
            $stmt = $db->prepare("INSERT INTO automation_rules (name, category, trigger_event, trigger_config) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $category, $triggerEvent, $triggerConfig]);
            $id = $db->lastInsertId();
        }

        $stmtAction = $db->prepare("INSERT INTO automation_actions (rule_id, action_type, action_config, position) VALUES (?, ?, ?, ?)");
        foreach ($actions as $i => $act) {
            $stmtAction->execute([$id, $act['type'], json_encode($act['config']), $i]);
        }

        $db->commit();
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteRule() {
    if ($_SESSION['user_role'] !== 'admin') {
        echo json_encode(['error' => 'Não autorizado']);
        return;
    }
    $id = (int) ($_POST['id'] ?? 0);
    getDB()->prepare("DELETE FROM automation_rules WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
}

function toggleRule() {
    if ($_SESSION['user_role'] !== 'admin') {
        echo json_encode(['error' => 'Não autorizado']);
        return;
    }
    $id = (int) ($_POST['id'] ?? 0);
    $active = (int) ($_POST['active'] ?? 1);
    getDB()->prepare("UPDATE automation_rules SET is_active=? WHERE id=?")->execute([$active, $id]);
    echo json_encode(['success' => true]);
}
