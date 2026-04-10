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
        listPos();
        break;
    case 'create':
        createPos();
        break;
    case 'update':
        updatePos();
        break;
    case 'delete':
        deletePos();
        break;
    case 'history':
        getHistory();
        break;
    case 'reports':
        getReports();
        break;
    case 'export':
        exportPos();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}

function listPos()
{
    $db = getDB();

    // Ensure soft-delete column + modified_by in history exist
    try {
        $db->exec("ALTER TABLE pos_equipments ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
    } catch (Exception $e) {
    }
    try {
        $db->exec("ALTER TABLE pos_history ADD COLUMN IF NOT EXISTS modified_by VARCHAR(100) NULL");
    } catch (Exception $e) {
    }

    // Ensure soft-delete column exists (before WHERE clause)
    try {
        $db->exec("ALTER TABLE pos_equipments ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
    } catch (Exception $e) {
    }
    $status = $_GET['status'] ?? '';

    try {
        $sql = "SELECT p.*, s.phone_number AS chip_phone, s.carrier AS chip_carrier
                FROM pos_equipments p
                LEFT JOIN sim_cards s ON p.chip_iccid = s.iccid 
                WHERE p.deleted_at IS NULL";
        $params = [];

        if ($status) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY p.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $pos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pos as &$item) {
            $stmtC = $db->prepare("SELECT company_name FROM cards WHERE category = 'pos' AND extra_data LIKE ? ORDER BY id DESC LIMIT 1");
            $stmtC->execute(['%"' . $item['serial_number'] . '"%']);
            $card = $stmtC->fetch();
            $item['linked_company'] = $card ? $card['company_name'] : null;
        }

        echo json_encode(['success' => true, 'data' => $pos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function createPos()
{
    $model = trim($_POST['model'] ?? '');
    $serial = trim($_POST['serial_number'] ?? '');
    $iccid = trim($_POST['chip_iccid'] ?? '');
    $status = $_POST['status'] ?? 'Estoque';
    $motivo = trim($_POST['motivo'] ?? '');

    if (empty($model) || empty($serial)) {
        echo json_encode(['success' => false, 'message' => 'Modelo e Serial são obrigatórios']);
        return;
    }

    $db = getDB();

    // Auto-add motivo column + expand status ENUM if missing
    try {
        $db->exec("ALTER TABLE pos_equipments ADD COLUMN IF NOT EXISTS motivo TEXT NULL");
    } catch (Exception $e) {
    }
    try {
        $db->exec("ALTER TABLE pos_equipments MODIFY COLUMN status ENUM('Estoque','Enviado','Recebido','Defeito','Em Manutenção','Retirada','Reverso') DEFAULT 'Estoque'");
    } catch (Exception $e) {
    }

    // Check if serial exists
    $stmt = $db->prepare("SELECT id FROM pos_equipments WHERE serial_number = ?");
    $stmt->execute([$serial]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Número de série já cadastrado']);
        return;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO pos_equipments (model, serial_number, chip_iccid, status, motivo) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$model, $serial, $iccid, $status, $motivo ?: null]);
        $posId = $db->lastInsertId();

        $histDesc = in_array($status, ['Defeito', 'Em Manutenção', 'Retirada', 'Reverso']) && $motivo ? $motivo : null;
        $histUser = $_SESSION['user_name'] ?? 'Sistema';
        $stmtHistory = $db->prepare("INSERT INTO pos_history (pos_id, action, problem_description, modified_by) VALUES (?, 'Cadastro', ?, ?)");
        $stmtHistory->execute([$posId, $histDesc, $histUser]);

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco']);
    }
}

function updatePos()
{
    $id = (int) ($_POST['id'] ?? 0);
    $model = trim($_POST['model'] ?? '');
    $serial = trim($_POST['serial_number'] ?? '');
    $iccid = trim($_POST['chip_iccid'] ?? '');
    $status = $_POST['status'] ?? 'Estoque';
    $motivo = trim($_POST['motivo'] ?? '');

    if (!$id || empty($model) || empty($serial)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        return;
    }

    $db = getDB();

    // Auto-add motivo column + expand status ENUM if missing
    try {
        $db->exec("ALTER TABLE pos_equipments ADD COLUMN IF NOT EXISTS motivo TEXT NULL");
    } catch (Exception $e) {
    }
    try {
        $db->exec("ALTER TABLE pos_equipments MODIFY COLUMN status ENUM('Estoque','Enviado','Recebido','Defeito','Em Manutenção','Retirada','Reverso') DEFAULT 'Estoque'");
    } catch (Exception $e) {
    }

    // Check serial unique
    $stmt = $db->prepare("SELECT id FROM pos_equipments WHERE serial_number = ? AND id != ?");
    $stmt->execute([$serial, $id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Número de série já pertence a outra máquina']);
        return;
    }

    // Get old status
    $stmt = $db->prepare("SELECT status FROM pos_equipments WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE pos_equipments SET model=?, serial_number=?, chip_iccid=?, status=?, motivo=? WHERE id=?");
        $stmt->execute([$model, $serial, $iccid, $status, $motivo ?: null, $id]);

        // Build history entry
        $action = 'Edicao';
        if ($old && $old['status'] !== $status) {
            if ($status === 'Em Manutenção' || $status === 'Defeito')
                $action = 'Manutencao';
            elseif ($status === 'Recebido')
                $action = 'Recebimento';
            elseif ($status === 'Enviado')
                $action = 'Envio';
            elseif ($status === 'Retirada' || $status === 'Reverso')
                $action = 'Edicao'; // Ou criar nova ação se necessário
        }

        $desc = "Status alterado de {$old['status']} para {$status}";
        if ($motivo && in_array($status, ['Defeito', 'Em Manutenção', 'Retirada', 'Reverso'])) {
            $desc .= " — Motivo: {$motivo}";
        }
        if ($old && $old['status'] === $status) {
            $desc = 'Dados atualizados';
            if ($motivo && in_array($status, ['Defeito', 'Em Manutenção', 'Retirada', 'Reverso']))
                $desc .= " — Motivo: {$motivo}";
        }

        $histUser = $_SESSION['user_name'] ?? 'Sistema';
        $stmtHistory = $db->prepare("INSERT INTO pos_history (pos_id, action, problem_description, modified_by) VALUES (?, ?, ?, ?)");
        $stmtHistory->execute([$id, $action, $desc, $histUser]);

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar']);
    }
}

function deletePos()
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }

    $db = getDB();

    // Ensure soft-delete column exists
    try {
        $db->exec("ALTER TABLE pos_equipments ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
    } catch (Exception $e) {
    }

    // Fetch info before deleting (for history)
    $stmt = $db->prepare("SELECT model, serial_number FROM pos_equipments WHERE id = ?");
    $stmt->execute([$id]);
    $eq = $stmt->fetch();
    if (!$eq) {
        echo json_encode(['success' => false, 'message' => 'Equipamento não encontrado']);
        return;
    }

    $db->beginTransaction();
    try {
        // Hard-delete (as requested by user)
        $stmt = $db->prepare("DELETE FROM pos_equipments WHERE id = ?");
        $stmt->execute([$id]);

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir']);
    }
}


function getHistory()
{
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'data' => []]);
        return;
    }

    $db = getDB();
    $sql = "SELECT h.*, c.title as card_title, c.id as kanban_card_id 
            FROM pos_history h 
            LEFT JOIN cards c ON h.card_id = c.id 
            WHERE h.pos_id = ? 
            ORDER BY h.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $history = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $history]);
}

function getReports()
{
    $db = getDB();
    $status = $_GET['status'] ?? 'all';
    $model = $_GET['model'] ?? 'all';

    // Stats por status (total do estoque, sem filtro de data)
    $stats = ['Estoque' => 0, 'Enviado' => 0, 'Recebido' => 0, 'Defeito' => 0, 'Em Manutenção' => 0, 'Retirada' => 0, 'Reverso' => 0];
    
    $statsSql = "SELECT status, COUNT(*) as count FROM pos_equipments WHERE deleted_at IS NULL";
    $statsParams = [];
    if ($model !== 'all') {
        $statsSql .= " AND model = ?";
        $statsParams[] = $model;
    }
    $statsSql .= " GROUP BY status";
    
    $stmtStats = $db->prepare($statsSql);
    $stmtStats->execute($statsParams);
    while ($row = $stmtStats->fetch()) { // Added missing loop
        if (array_key_exists($row['status'], $stats)) {
            $stats[$row['status']] = (int) $row['count'];
        }
    }
    $stats['total'] = array_sum($stats);

    // Últimas movimentações
    $sqlHistory = "
        SELECT h.action, h.problem_description, h.created_at, e.model, e.serial_number
        FROM pos_history h
        JOIN pos_equipments e ON h.pos_id = e.id
        ORDER BY h.created_at DESC LIMIT 50
    ";
    $stmtH = $db->prepare($sqlHistory);
    $stmtH->execute();
    $recentEvents = $stmtH->fetchAll();

    // Lista de equipamentos (todos, filtrado apenas por status se passado)
    $sqlEq = "SELECT p.*, s.phone_number AS chip_phone, s.carrier AS chip_carrier
              FROM pos_equipments p
              LEFT JOIN sim_cards s ON p.chip_iccid = s.iccid
              WHERE p.deleted_at IS NULL";
    $params = [];
    if ($status !== 'all') {
        $sqlEq .= " AND p.status = ?";
        $params[] = $status;
    }
    if ($model !== 'all') {
        $sqlEq .= " AND p.model = ?";
        $params[] = $model;
    }
    $sqlEq .= " ORDER BY p.id DESC";
    $stmtEq = $db->prepare($sqlEq);
    $stmtEq->execute($params);
    $equipments = $stmtEq->fetchAll(PDO::FETCH_ASSOC);

    foreach ($equipments as &$item) {
        $stmtC = $db->prepare("SELECT company_name FROM cards WHERE category = 'pos' AND extra_data LIKE ? ORDER BY id DESC LIMIT 1");
        $stmtC->execute(['%"' . $item['serial_number'] . '"%']);
        $card = $stmtC->fetch();
        $item['linked_company'] = $card ? $card['company_name'] : null;
    }

    echo json_encode(['success' => true, 'stats' => $stats, 'events' => $recentEvents, 'equipments' => $equipments]);
}


function exportPos()
{
    $db = getDB();
    $status = $_GET['status'] ?? 'all';
    $model = $_GET['model'] ?? 'all';

    $sql = "SELECT e.*, 
                   s.iccid as chip_iccid_sim, s.phone_number as chip_phone, s.carrier as chip_carrier
            FROM pos_equipments e
            LEFT JOIN sim_cards s ON e.chip_iccid = s.iccid
            WHERE e.deleted_at IS NULL";
    $params = [];
    if ($status !== 'all') {
        $sql .= " AND e.status = ?";
        $params[] = $status;
    }
    if ($model !== 'all') {
        $sql .= " AND e.model = ?";
        $params[] = $model;
    }
    $sql .= " ORDER BY e.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Send CSV headers
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="pos_relatorio_' . date('Ymd') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['ID', 'Modelo', 'Serial Number', 'ICCID Chip', 'Status', 'Motivo', 'Cadastrado em'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['model'],
            $r['serial_number'],
            $r['chip_iccid'] ?? '',
            $r['status'],
            $r['motivo'] ?? '',
            date('d/m/Y H:i', strtotime($r['created_at']))
        ], ';');
    }
    fclose($out);
    exit;
}
