<?php
/**
 * API - IMPORTAÇÃO DE ESTOQUE GERAL
 */
require_once '../../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$data = $input['data'] ?? [];

if (empty($data)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum dado recebido']);
    exit;
}

$db = getDB();
$userName = $_SESSION['user_name'] ?? 'Sistema';

try {
    $db->beginTransaction();

    $stmtInsert = $db->prepare("INSERT INTO inventory_items (name, category, description, quantity, min_quantity) VALUES (?, ?, ?, ?, ?)");
    $stmtLog = $db->prepare("INSERT INTO inventory_history (item_id, action, quantity_change, modified_by, description) VALUES (?, ?, ?, ?, ?)");

    $count = 0;
    foreach ($data as $row) {
        $name = trim($row['name'] ?? '');
        if (empty($name)) continue;

        $category = trim($row['category'] ?? '');
        $description = trim($row['description'] ?? '');
        $quantity = (int)($row['quantity'] ?? 0);
        $min_quantity = (int)($row['min_quantity'] ?? 0);

        // Inserir item
        $stmtInsert->execute([$name, $category, $description, $quantity, $min_quantity]);
        $itemId = $db->lastInsertId();

        // Logar histórico
        $stmtLog->execute([$itemId, 'Importado via Excel', $quantity, $userName, 'Criação inicial via importação']);
        $count++;
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => "$count itens importados com sucesso!"]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao importar: ' . $e->getMessage()]);
}
