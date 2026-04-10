<?php
// e:\ARQUIVOS\PROJETOS\SITES\FLEX\SUPORTE FLEX FERRAMENTAS\inventory\api\inventory.php
ob_start();
require_once '../../config/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide direct text output
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0)
        return false;
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "PHP Error: $errstr in $errfile:$errline"]);
    exit;
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Fatal Error: {$error['message']} in {$error['file']}:{$error['line']}"]);
        exit;
    }
});

// Inicializa ou garante que as tabelas de estoque existam
function initInventoryTables()
{
    $db = getDB();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `inventory_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `category` VARCHAR(100) NULL,
            `description` TEXT NULL,
            `quantity` INT DEFAULT 0,
            `min_quantity` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->exec("CREATE TABLE IF NOT EXISTS `inventory_exits` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `item_id` INT NOT NULL,
            `quantity_used` INT NOT NULL,
            `user_name` VARCHAR(100) NOT NULL,
            `description` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->exec("CREATE TABLE IF NOT EXISTS `inventory_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `item_id` INT NOT NULL,
            `action` VARCHAR(50) NOT NULL,
            `quantity_change` INT NOT NULL DEFAULT 0,
            `modified_by` VARCHAR(100) NULL,
            `description` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Adiciona a coluna description se ela não existir (para bancos legados)
        try {
            $db->exec("ALTER TABLE `inventory_history` ADD COLUMN `description` TEXT NULL AFTER `modified_by`");
        } catch (Exception $e) {
        }

    } catch (Exception $e) {
    }
}

function logInventoryHistory($db, $itemId, $action, $qtyChange, $modifiedBy, $description = null)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $user = $modifiedBy ?: ($_SESSION['user_name'] ?? 'Sistema');

    $stmt = $db->prepare("INSERT INTO inventory_history (item_id, action, quantity_change, modified_by, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$itemId, $action, $qtyChange, $user, $description]);
}

function listItems()
{
    initInventoryTables();
    $db = getDB();
    $category = $_GET['category'] ?? '';

    $sql = "SELECT id, name, category, description, quantity, min_quantity, created_at, 
                   IF(quantity <= min_quantity, 1, 0) AS is_low_stock 
            FROM inventory_items 
            WHERE deleted_at IS NULL";

    $params = [];
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }

    $sql .= " ORDER BY is_low_stock DESC, name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar categorias distintas para filtro
    $stmtCats = $db->query("SELECT DISTINCT category FROM inventory_items WHERE deleted_at IS NULL AND category != '' ORDER BY category ASC");
    $categories = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

    // Contagem de alertas totais
    $stmtAlerts = $db->query("SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL AND quantity <= min_quantity");
    $totalAlerts = $stmtAlerts->fetchColumn();

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $items, 'categories' => $categories, 'total_alerts' => $totalAlerts], JSON_UNESCAPED_UNICODE);
}

function createOrUpdateItem()
{
    initInventoryTables();
    $db = getDB();

    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $min_quantity = (int) ($_POST['min_quantity'] ?? 0);

    if (empty($name)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'O nome do item é obrigatório.']);
        return;
    }

    try {
        if ($id) {
            // Edição
            $stmt = $db->prepare("SELECT quantity FROM inventory_items WHERE id = ?");
            $stmt->execute([$id]);
            $oldObj = $stmt->fetch();
            $oldQty = $oldObj ? (int) $oldObj['quantity'] : 0;

            $stmt = $db->prepare("UPDATE inventory_items SET name=?, category=?, description=?, quantity=?, min_quantity=? WHERE id=?");
            $stmt->execute([$name, $category, $description, $quantity, $min_quantity, $id]);

            $diff = $quantity - $oldQty;
            if ($diff !== 0) {
                logInventoryHistory($db, $id, 'Estoque Ajustado Manualmente', $diff, null);
            } else {
                logInventoryHistory($db, $id, 'Item Editado', 0, null);
            }

            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Item atualizado com sucesso.']);
        } else {
            // Criação
            $stmt = $db->prepare("INSERT INTO inventory_items (name, category, description, quantity, min_quantity) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $category, $description, $quantity, $min_quantity]);
            $newId = $db->lastInsertId();

            logInventoryHistory($db, $newId, 'Item Cadastrado', $quantity, null);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Item criado com sucesso.']);
        }
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function registerExit()
{
    initInventoryTables();
    $db = getDB();

    $itemId = $_POST['item_id'] ?? null;
    $qtyUsed = (int) ($_POST['quantity_used'] ?? 0);
    $userName = trim($_POST['user_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$itemId || empty($userName)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Preencha item e usuário solicitante.']);
        return;
    }

    if ($qtyUsed <= 0) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Quantidade inválida.']);
        return;
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT quantity, name FROM inventory_items WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Item não encontrado.']);
            return;
        }

        if ((int) $item['quantity'] < $qtyUsed) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Saldo insuficiente. O item '{$item['name']}' possui apenas {$item['quantity']} unidade(s) em estoque."]);
            return;
        }

        // Deduzir do saldo
        $stmtUpdate = $db->prepare("UPDATE inventory_items SET quantity = quantity - ? WHERE id = ?");
        $stmtUpdate->execute([$qtyUsed, $itemId]);

        // Registrar Saída
        $stmtExit = $db->prepare("INSERT INTO inventory_exits (item_id, quantity_used, user_name, description) VALUES (?, ?, ?, ?)");
        $stmtExit->execute([$itemId, $qtyUsed, $userName, $description]);

        // Registrar Log
        logInventoryHistory($db, $itemId, 'Saída de Estoque', -$qtyUsed, $userName, $description);

        $db->commit();
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Saída registrada com sucesso!']);

    } catch (Exception $e) {
        $db->rollBack();
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function registerEntry()
{
    initInventoryTables();
    $db = getDB();

    $itemId = $_POST['item_id'] ?? null;
    $qtyAdded = (int) ($_POST['quantity_added'] ?? 0);
    $supplier = trim($_POST['supplier'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$itemId || $qtyAdded <= 0) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Dados de entrada inválidos.']);
        return;
    }

    try {
        $db->beginTransaction();

        // Registrar entrada (adicionar ao estoque)
        $stmtUpdate = $db->prepare("UPDATE inventory_items SET quantity = quantity + ? WHERE id = ?");
        $stmtUpdate->execute([$qtyAdded, $itemId]);

        // Registrar Log
        $logDesc = $description ? "Origem: $supplier | $description" : ($supplier ? "Origem: $supplier" : null);
        logInventoryHistory($db, $itemId, 'Entrada de Estoque', $qtyAdded, $supplier ?: 'Sistema', $logDesc);

        $db->commit();
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Entrada registrada com sucesso!']);

    } catch (Exception $e) {
        $db->rollBack();
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteItem()
{
    initInventoryTables();
    $db = getDB();
    $id = $_POST['id'] ?? null;

    if (!$id) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID não informado']);
        return;
    }
    try {
        $stmt = $db->prepare("UPDATE inventory_items SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        logInventoryHistory($db, $id, 'Item Excluído', 0, null);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getLowStockItems()
{
    initInventoryTables();
    $db = getDB();

    $stmt = $db->query("SELECT id, name, category, quantity, min_quantity 
                        FROM inventory_items 
                        WHERE deleted_at IS NULL AND quantity <= min_quantity 
                        ORDER BY category ASC, name ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $items], JSON_UNESCAPED_UNICODE);
}

function getExitsReport()
{
    initInventoryTables();
    $db = getDB();

    $stmt = $db->query("SELECT e.id, e.quantity_used, e.user_name, e.description, e.created_at, 
                               i.name AS item_name, i.category 
                        FROM inventory_exits e
                        JOIN inventory_items i ON e.item_id = i.id
                        ORDER BY e.created_at DESC 
                        LIMIT 300"); // Filtro inicial ou limitação para relatórios
    $exits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $exits], JSON_UNESCAPED_UNICODE);
}


$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listItems();
        break;
    case 'create_update':
        createOrUpdateItem();
        break;
    case 'register_exit':
        registerExit();
        break;
    case 'register_entry':
        registerEntry();
        break;
    case 'delete':
        deleteItem();
        break;
    case 'low_stock':
        getLowStockItems();
        break;
    case 'exits_report':
        getExitsReport();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
}
