<?php
/**
 * API - IMPORTAÇÃO DE CHIPS (SIM CARDS)
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

    $stmtInsert = $db->prepare("INSERT INTO sim_cards (phone_number, iccid, type, carrier, status) VALUES (?, ?, ?, ?, ?)");
    $stmtLog = $db->prepare("INSERT INTO sim_history (sim_id, action, problem_description, modified_by, created_at) VALUES (?, 'Cadastro', 'Importado via Excel', ?, NOW())");

    $count = 0;
    foreach ($data as $row) {
        $iccid = trim($row['iccid'] ?? '');
        if (empty($iccid)) continue;

        $phone = trim($row['phone_number'] ?? '');
        $type = trim($row['type'] ?? 'POS');
        $carrier = trim($row['carrier'] ?? '');
        $status = trim($row['status'] ?? 'Estoque');

        // Check if ICCID already exists
        $stmtCheck = $db->prepare("SELECT id FROM sim_cards WHERE iccid = ?");
        $stmtCheck->execute([$iccid]);
        if ($stmtCheck->fetch()) continue; // Skip duplicates by ICCID

        // Inserir chip
        $stmtInsert->execute([$phone ?: null, $iccid, $type, $carrier, $status]);
        $simId = $db->lastInsertId();

        // Logar histórico
        $stmtLog->execute([$simId, $userName]);
        $count++;
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => "$count chips importados com sucesso!"]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao importar: ' . $e->getMessage()]);
}
