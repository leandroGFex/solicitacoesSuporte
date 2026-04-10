<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$db = getDB();

try {
    switch ($action) {
        case 'list':
            $stmt = $db->query("
                SELECT cp.*, 
                       (SELECT COUNT(*) FROM procedure_contacts WHERE procedure_id = cp.id) as contact_count,
                       (SELECT COUNT(*) FROM procedure_items WHERE procedure_id = cp.id AND enabled = 1) as item_count
                FROM company_procedures cp 
                ORDER BY cp.name ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM company_procedures WHERE id = ?");
            $stmt->execute([$id]);
            $procedure = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$procedure) {
                echo json_encode(['success' => false, 'message' => 'Procedimento não encontrado']);
                exit;
            }

            // Get contacts
            $stmt = $db->prepare("SELECT * FROM procedure_contacts WHERE procedure_id = ?");
            $stmt->execute([$id]);
            $procedure['contacts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get managers
            $stmt = $db->prepare("SELECT * FROM procedure_managers WHERE procedure_id = ?");
            $stmt->execute([$id]);
            $procedure['managers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get items (locks and driver rules)
            $stmt = $db->prepare("SELECT * FROM procedure_items WHERE procedure_id = ?");
            $stmt->execute([$id]);
            $procedure['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $procedure]);
            break;

        case 'save':
            if (!$isAdmin) throw new Exception('Acesso negado');

            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int)($input['id'] ?? 0);
            $name = $input['name'] ?? '';
            $observation = $input['observation'] ?? '';
            $contacts = $input['contacts'] ?? [];
            $managers = $input['managers'] ?? [];
            $items = $input['items'] ?? [];

            if (!$name) throw new Exception('Nome da empresa é obrigatório');

            $db->beginTransaction();

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE company_procedures SET name = ?, observation = ? WHERE id = ?");
                $stmt->execute([$name, $observation, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO company_procedures (name, observation, created_by) VALUES (?, ?, ?)");
                $stmt->execute([$name, $observation, $_SESSION['user_id']]);
                $id = $db->lastInsertId();
            }

            // Update contacts
            $db->prepare("DELETE FROM procedure_contacts WHERE procedure_id = ?")->execute([$id]);
            $stmtContact = $db->prepare("INSERT INTO procedure_contacts (procedure_id, name, phone, email) VALUES (?, ?, ?, ?)");
            foreach ($contacts as $c) {
                if (!empty($c['name'])) {
                    $stmtContact->execute([$id, $c['name'], $c['phone'] ?? '', $c['email'] ?? '']);
                }
            }

            // Update managers
            $db->prepare("DELETE FROM procedure_managers WHERE procedure_id = ?")->execute([$id]);
            $stmtManager = $db->prepare("INSERT INTO procedure_managers (procedure_id, name, phone, observation) VALUES (?, ?, ?, ?)");
            foreach ($managers as $m) {
                if (!empty($m['name'])) {
                    $stmtManager->execute([$id, $m['name'], $m['phone'] ?? '', $m['observation'] ?? '']);
                }
            }

            // Update items
            $db->prepare("DELETE FROM procedure_items WHERE procedure_id = ?")->execute([$id]);
            $stmtItem = $db->prepare("INSERT INTO procedure_items (procedure_id, type, label, enabled, description) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                if (!empty($item['label'])) {
                    $stmtItem->execute([$id, $item['type'], $item['label'], (int)$item['enabled'], $item['description'] ?? '']);
                }
            }

            $db->commit();
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'delete':
            if (!$isAdmin) throw new Exception('Acesso negado');
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int)($input['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM company_procedures WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'search':
            $q = $_GET['q'] ?? '';
            if (empty($q)) {
                $stmt = $db->query("SELECT * FROM company_procedures ORDER BY name ASC");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                exit;
            }

            $words = explode(' ', $q);
            $conditions = [];
            $params = [];
            foreach ($words as $word) {
                if (empty(trim($word))) continue;
                $term = "%$word%";
                $conditions[] = "(cp.name LIKE ? OR pi.description LIKE ? OR pi.label LIKE ?)";
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }
            
            $whereClause = implode(' OR ', $conditions); // "any word" matches
            $stmt = $db->prepare("SELECT DISTINCT cp.*,
                                  (SELECT COUNT(*) FROM procedure_contacts WHERE procedure_id = cp.id) as contact_count,
                                  (SELECT COUNT(*) FROM procedure_items WHERE procedure_id = cp.id AND enabled = 1) as item_count
                                  FROM company_procedures cp 
                                  LEFT JOIN procedure_items pi ON cp.id = pi.procedure_id
                                  WHERE $whereClause
                                  ORDER BY cp.name ASC");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
