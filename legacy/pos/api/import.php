<?php
/**
 * API - IMPORTAÇÃO DE ESTOQUE POS
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

    $stmtInsert = $db->prepare("INSERT INTO pos_equipments (model, serial_number, chip_iccid, status) VALUES (?, ?, ?, ?)");
    $stmtLog = $db->prepare("INSERT INTO pos_history (pos_id, action, problem_description, created_at) VALUES (?, 'Cadastro', 'Importado via Excel', NOW())");

    $count = 0;
    foreach ($data as $row) {
        $model = trim($row['model'] ?? '');
        $serial = trim($row['serial_number'] ?? '');
        
        if (empty($model) || empty($serial)) continue;

        $chip = trim($row['chip_iccid'] ?? '');
        $status = trim($row['status'] ?? 'Estoque');

        // Check if serial already exists
        $stmtCheck = $db->prepare("SELECT id FROM pos_equipments WHERE serial_number = ?");
        $stmtCheck->execute([$serial]);
        if ($stmtCheck->fetch()) continue; // Skip duplicates by serial

        // Inserir equipamento
        $stmtInsert->execute([$model, $serial, $chip, $status]);
        $posId = $db->lastInsertId();

        // Logar histórico
        $stmtLog->execute([$posId]);
        $count++;
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => "$count máquinas POS importadas com sucesso!"]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao importar: ' . $e->getMessage()]);
}
