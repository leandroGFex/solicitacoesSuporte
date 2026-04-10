<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        listChips();
        break;
    case 'create':
        createChip();
        break;
    case 'update':
        updateChip();
        break;
    case 'delete':
        deleteChip();
        break;
    case 'history':
        getHistory();
        break;
    case 'reports':
        getReports();
        break;
    case 'export':
        exportChips();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}

function initChipsTables()
{
    $db = getDB();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `sim_cards` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `phone_number` VARCHAR(50) NOT NULL UNIQUE,
          `iccid` VARCHAR(100) NOT NULL UNIQUE,
          `carrier` VARCHAR(50),
          `status` ENUM('Estoque', 'Em Uso', 'Cancelado', 'Defeito', 'Retirada', 'Reverso') DEFAULT 'Estoque',
          `motivo` TEXT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `deleted_at` DATETIME NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->exec("CREATE TABLE IF NOT EXISTS `sim_history` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `sim_id` INT NOT NULL,
          `action` VARCHAR(50) NOT NULL,
          `problem_description` TEXT,
          `modified_by` VARCHAR(100) NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`sim_id`) REFERENCES `sim_cards`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Migrate 'type' column dynamically
        try {
            $db->exec("ALTER TABLE `sim_cards` ADD COLUMN `type` ENUM('POS', 'Rastreador') DEFAULT 'POS' AFTER `carrier`");
        } catch (Exception $e) {
        }

        // Allow phone_number to be NULL for POS devices
        try {
            $db->exec("ALTER TABLE `sim_cards` MODIFY `phone_number` VARCHAR(50) NULL DEFAULT NULL");
        } catch (Exception $e) {
        }

        // Expanded status ENUM
        try {
            $db->exec("ALTER TABLE `sim_cards` MODIFY `status` ENUM('Estoque', 'Em Uso', 'Cancelado', 'Defeito', 'Retirada', 'Reverso') DEFAULT 'Estoque'");
        } catch (Exception $e) {
        }

    } catch (Exception $e) {
    }
}

function buildChipLinkedInfo($db, $iccid)
{
    if (empty($iccid))
        return ['equip' => null, 'details' => null];

    // Check Trackers
    $stmtT = $db->prepare("SELECT serial_number FROM trackers WHERE chip_iccid = ? LIMIT 1");
    $stmtT->execute([$iccid]);
    $tracker = $stmtT->fetch();
    if ($tracker) {
        $equip = 'Rastreador SN: ' . $tracker['serial_number'];
        $details = '-';
        $stmtC = $db->prepare("SELECT placa, company_name FROM cards WHERE category = 'rastreador' AND extra_data LIKE ? ORDER BY id DESC LIMIT 1");
        $stmtC->execute(['%"' . $tracker['serial_number'] . '"%']);
        $card = $stmtC->fetch();
        if ($card) {
            $parts = [];
            if ($card['placa'])
                $parts[] = 'Placa: ' . $card['placa'];
            if ($card['company_name'])
                $parts[] = $card['company_name'];
            if ($parts)
                $details = implode(' | ', $parts);
        }
        return ['equip' => $equip, 'details' => $details];
    }

    // Check POS
    $stmtP = $db->prepare("SELECT serial_number FROM pos_equipments WHERE chip_iccid = ? LIMIT 1");
    $stmtP->execute([$iccid]);
    $pos = $stmtP->fetch();
    if ($pos) {
        $equip = 'POS SN: ' . $pos['serial_number'];
        $details = '-';
        $stmtC = $db->prepare("SELECT company_name FROM cards WHERE category = 'pos' AND extra_data LIKE ? ORDER BY id DESC LIMIT 1");
        $stmtC->execute(['%"' . $pos['serial_number'] . '"%']);
        $card = $stmtC->fetch();
        if ($card && $card['company_name']) {
            $details = $card['company_name'];
        }
        return ['equip' => $equip, 'details' => $details];
    }

    return ['equip' => null, 'details' => null];
}

function listChips()
{
    initChipsTables();
    $db = getDB();
    $status = $_GET['status'] ?? '';
    $type = $_GET['type'] ?? '';

    $sql = "SELECT * FROM sim_cards WHERE deleted_at IS NULL";
    $params = [];
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    if ($type) {
        $sql .= " AND type = ?";
        $params[] = $type;
    }
    $sql .= " ORDER BY id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $chips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Append linked equipment details
    foreach ($chips as &$chip) {
        $linked = buildChipLinkedInfo($db, $chip['iccid']);
        $chip['linked_equipment'] = $linked['equip'];
        $chip['linked_details'] = $linked['details'];
    }

    echo json_encode(['success' => true, 'data' => $chips]);
}

function createChip()
{
    initChipsTables();
    $db = getDB();

    $phone = trim($_POST['phone_number'] ?? '');
    $iccid = trim($_POST['iccid'] ?? '');
    $type = $_POST['type'] ?? 'POS';
    $carrier = trim($_POST['carrier'] ?? '');
    $status = $_POST['status'] ?? 'Estoque';
    $motivo = trim($_POST['motivo'] ?? '');

    if (empty($iccid)) {
        echo json_encode(['success' => false, 'message' => 'ICCID é obrigatório']);
        return;
    }
    if ($type === 'Rastreador' && empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'O Número da Linha é obrigatório para Rastreadores']);
        return;
    }

    $phoneToSave = $phone !== '' ? $phone : null;

    // Verify unique
    $checkSql = "SELECT id FROM sim_cards WHERE iccid = ?";
    $checkParams = [$iccid];
    if ($phoneToSave) {
        $checkSql .= " OR (phone_number = ? AND phone_number IS NOT NULL)";
        $checkParams[] = $phoneToSave;
    }

    $stmt = $db->prepare($checkSql);
    $stmt->execute($checkParams);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Linha ou ICCID já cadastrado no sistema']);
        return;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO sim_cards (phone_number, iccid, type, carrier, status, motivo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$phoneToSave, $iccid, $type, $carrier, $status, $motivo ?: null]);
        $simId = $db->lastInsertId();

        $histDesc = in_array($status, ['Defeito', 'Cancelado']) && $motivo ? $motivo : null;
        $histUser = $_SESSION['user_name'] ?? 'Sistema';
        $stmtH = $db->prepare("INSERT INTO sim_history (sim_id, action, problem_description, modified_by) VALUES (?, 'Cadastro', ?, ?)");
        $stmtH->execute([$simId, $histDesc, $histUser]);

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco']);
    }
}

function updateChip()
{
    initChipsTables();
    $db = getDB();

    $id = (int) ($_POST['id'] ?? 0);
    $phone = trim($_POST['phone_number'] ?? '');
    $iccid = trim($_POST['iccid'] ?? '');
    $type = $_POST['type'] ?? 'POS';
    $carrier = trim($_POST['carrier'] ?? '');
    $status = $_POST['status'] ?? 'Estoque';
    $motivo = trim($_POST['motivo'] ?? '');

    if (!$id || empty($iccid)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        return;
    }
    if ($type === 'Rastreador' && empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'O Número da Linha é obrigatório para Rastreadores']);
        return;
    }

    $phoneToSave = $phone !== '' ? $phone : null;

    // Verify unique
    $checkSql = "SELECT id FROM sim_cards WHERE (iccid = ?) AND id != ?";
    $checkParams = [$iccid, $id];
    if ($phoneToSave) {
        $checkSql = "SELECT id FROM sim_cards WHERE (iccid = ? OR phone_number = ?) AND id != ?";
        $checkParams = [$iccid, $phoneToSave, $id];
    }

    $stmt = $db->prepare($checkSql);
    $stmt->execute($checkParams);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Linha ou ICCID já cadastrados para outro chip']);
        return;
    }

    $stmt = $db->prepare("SELECT status FROM sim_cards WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE sim_cards SET phone_number=?, iccid=?, type=?, carrier=?, status=?, motivo=? WHERE id=?");
        $stmt->execute([$phoneToSave, $iccid, $type, $carrier, $status, $motivo ?: null, $id]);

        $action = 'Edicao';
        if ($old && $old['status'] !== $status) {
            $action = 'Status';
        }

        $desc = "Status alterado de {$old['status']} para {$status}";
        if ($motivo && in_array($status, ['Defeito', 'Cancelado'])) {
            $desc .= " — Motivo: {$motivo}";
        }
        if ($old && $old['status'] === $status) {
            $desc = 'Dados atualizados';
            if ($motivo && in_array($status, ['Defeito', 'Cancelado'])) {
                $desc .= " — Motivo: {$motivo}";
            }
        }

        $histUser = $_SESSION['user_name'] ?? 'Sistema';
        $stmtH = $db->prepare("INSERT INTO sim_history (sim_id, action, problem_description, modified_by) VALUES (?, ?, ?, ?)");
        $stmtH->execute([$id, $action, $desc, $histUser]);

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar']);
    }
}

function deleteChip()
{
    initChipsTables();
    $db = getDB();
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $db->prepare("SELECT phone_number, iccid FROM sim_cards WHERE id = ?");
    $stmt->execute([$id]);
    $eq = $stmt->fetch();

    if (!$eq) {
        echo json_encode(['success' => false, 'message' => 'Chip não encontrado']);
        return;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE sim_cards SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        $user = $_SESSION['user_name'] ?? 'usuário';
        $desc = "Excluído permanentemente por {$user}. Linha: {$eq['phone_number']} | ICCID: {$eq['iccid']}";
        $stmtH = $db->prepare("INSERT INTO sim_history (sim_id, action, problem_description) VALUES (?, 'Excluido', ?)");
        $stmtH->execute([$id, $desc]);

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir']);
    }
}

function getHistory()
{
    initChipsTables();
    $db = getDB();
    $id = (int) ($_GET['id'] ?? 0);

    $stmt = $db->prepare("SELECT * FROM sim_history WHERE sim_id = ? ORDER BY created_at DESC");
    $stmt->execute([$id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $history]);
}

function getReports()
{
    try {
        initChipsTables();
        $db = getDB();
        $status = $_GET['status'] ?? 'all';
        $type = $_GET['type'] ?? 'all';

        $stats = ['Estoque' => 0, 'Em Uso' => 0, 'Cancelado' => 0, 'Defeito' => 0, 'Retirada' => 0, 'Reverso' => 0];
        
        $sqlStats = "SELECT status, COUNT(*) as count FROM sim_cards WHERE deleted_at IS NULL";
        $paramsStats = [];
        if ($type !== 'all') {
            $sqlStats .= " AND type = ?";
            $paramsStats[] = $type;
        }
        $sqlStats .= " GROUP BY status";

        $stmtStats = $db->prepare($sqlStats);
        $stmtStats->execute($paramsStats);
        while ($row = $stmtStats->fetch()) {
            if (array_key_exists($row['status'], $stats)) {
                $stats[$row['status']] = (int) $row['count'];
            }
        }
        $stats['total'] = array_sum($stats);

        $sqlHistory = "
            SELECT h.action, h.problem_description, h.created_at, e.phone_number, e.iccid, e.carrier
            FROM sim_history h
            JOIN sim_cards e ON h.sim_id = e.id
            ORDER BY h.created_at DESC LIMIT 50
        ";
        $stmtH = $db->prepare($sqlHistory);
        $stmtH->execute();
        $events = $stmtH->fetchAll(PDO::FETCH_ASSOC);

        $sqlEq = "SELECT * FROM sim_cards WHERE deleted_at IS NULL";
        $params = [];
        if ($status !== 'all') {
            $sqlEq .= " AND status = ?";
            $params[] = $status;
        }
        if ($type !== 'all') {
            $sqlEq .= " AND type = ?";
            $params[] = $type;
        }
        $sqlEq .= " ORDER BY id DESC";
        $stmtEq = $db->prepare($sqlEq);
        $stmtEq->execute($params);
        $equipments = $stmtEq->fetchAll(PDO::FETCH_ASSOC);

        // Append linked info
        foreach ($equipments as &$eq) {
            $linked = buildChipLinkedInfo($db, $eq['iccid']);
            $eq['linked_equipment'] = $linked['equip'];
            $eq['linked_details'] = $linked['details'];
        }

        echo json_encode(['success' => true, 'stats' => $stats, 'events' => $events, 'equipments' => $equipments]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro interno API: ' . $e->getMessage()]);
    }
}

function exportChips()
{
    initChipsTables();
    $db = getDB();
    $status = $_GET['status'] ?? 'all';
    $type = $_GET['type'] ?? 'all';

    $sql = "SELECT * FROM sim_cards WHERE deleted_at IS NULL";
    $params = [];
    if ($status !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    if ($type !== 'all') {
        $sql .= " AND type = ?";
        $params[] = $type;
    }
    $sql .= " ORDER BY id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="chips_relatorio_' . date('Ymd') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['ID', 'Linha', 'ICCID', 'Operadora', 'Status', 'Motivo', 'Atrelado A', 'Empresa/Placa', 'Cadastrado em'], ';');

    foreach ($rows as $r) {
        $linked = buildChipLinkedInfo($db, $r['iccid']);
        fputcsv($out, [
            $r['id'],
            $r['phone_number'],
            $r['iccid'],
            $r['carrier'],
            $r['status'],
            $r['motivo'] ?? '',
            $linked['equip'] ?? '',
            $linked['details'] ?? '',
            date('d/m/Y H:i', strtotime($r['created_at']))
        ], ';');
    }
    fclose($out);
    exit;
}
