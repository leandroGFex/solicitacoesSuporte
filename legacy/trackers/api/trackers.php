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
        listTrackers();
        break;
    case 'create':
        createTracker();
        break;
    case 'update':
        updateTracker();
        break;
    case 'delete':
        deleteTracker();
        break;
    case 'history':
        getHistory();
        break;
    case 'reports':
        getReports();
        break;
        case 'export':
            exportTrackers();
            break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}

function listTrackers()
{
    $db = getDB();

    // Ensure soft-delete + modified_by in history exist
    try {
        $db->exec("ALTER TABLE trackers ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
    } catch (Exception $e) {
    }
    try {
        $db->exec("ALTER TABLE tracker_history ADD COLUMN IF NOT EXISTS modified_by VARCHAR(100) NULL");
    } catch (Exception $e) {
    }

    // Ensure soft-delete column exists (before WHERE clause)
    try {
        $db->exec("ALTER TABLE trackers ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
    } catch (Exception $e) {
    }

    $status = $_GET['status'] ?? '';

    try {
        $sql = "SELECT t.*, s.phone_number AS chip_phone, s.carrier AS chip_carrier
                FROM trackers t
                LEFT JOIN sim_cards s ON t.chip_iccid = s.iccid 
                WHERE t.deleted_at IS NULL";

        $params = [];

        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY t.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $trackers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($trackers as &$item) {
            $stmtC = $db->prepare("SELECT company_name FROM cards WHERE category = 'rastreador' AND extra_data LIKE ? ORDER BY id DESC LIMIT 1");
            $stmtC->execute(['%"' . $item['serial_number'] . '"%']);
            $card = $stmtC->fetch();
            $item['linked_company'] = $card ? $card['company_name'] : null;
        }

        echo json_encode(['success' => true, 'data' => $trackers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}


function createTracker()
{
    $model = trim($_POST['model'] ?? '');
    $serial = trim($_POST['serial_number'] ?? '');
    $iccid = trim($_POST['chip_iccid'] ?? '');
    $status = $_POST['status'] ?? 'Estoque';
    $motivo = trim($_POST['motivo'] ?? '');

    if (empty($serial)) {
        echo json_encode(['success' => false, 'message' => 'Serial é obrigatório']);
        return;
    }

    $db = getDB();

    // Auto-add motivo column + expand status ENUM if missing
    try {
        $db->exec("ALTER TABLE trackers ADD COLUMN IF NOT EXISTS motivo TEXT NULL");
    } catch (Exception $e) {
    }
    try {
        $db->exec("ALTER TABLE trackers MODIFY COLUMN status ENUM('Estoque','Enviado','Recebido','Defeito','Em Manutenção') DEFAULT 'Estoque'");
    } catch (Exception $e) {
    }

    // Check if serial exists
    $stmt = $db->prepare("SELECT id FROM trackers WHERE serial_number = ?");
    $stmt->execute([$serial]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Número de série já cadastrado']);
        return;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO trackers (model, serial_number, chip_iccid, status, motivo) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$model, $serial, $iccid, $status, $motivo ?: null]);
        $trackerId = $db->lastInsertId();

        $histDesc = in_array($status, ['Defeito', 'Em Manutenção']) && $motivo ? $motivo : null;
        $histUser = $_SESSION['user_name'] ?? 'Sistema';
        $stmtHistory = $db->prepare("INSERT INTO tracker_history (tracker_id, action, problem_description, modified_by) VALUES (?, 'Cadastro', ?, ?)");
        $stmtHistory->execute([$trackerId, $histDesc, $histUser]);

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco']);
    }
}

function updateTracker()
{
    $id = (int) ($_POST['id'] ?? 0);
    $model = trim($_POST['model'] ?? '');
    $serial = trim($_POST['serial_number'] ?? '');
    $iccid = trim($_POST['chip_iccid'] ?? '');
    $status = $_POST['status'] ?? 'Estoque';
    $motivo = trim($_POST['motivo'] ?? '');

    if (!$id || empty($serial)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        return;
    }

    $db = getDB();

    // Auto-add motivo column + expand status ENUM if missing
    try {
        $db->exec("ALTER TABLE trackers ADD COLUMN IF NOT EXISTS motivo TEXT NULL");
    } catch (Exception $e) {
    }
    try {
        $db->exec("ALTER TABLE trackers MODIFY COLUMN status ENUM('Estoque','Enviado','Recebido','Defeito','Em Manutenção') DEFAULT 'Estoque'");
    } catch (Exception $e) {
    }

    // Check serial unique
    $stmt = $db->prepare("SELECT id FROM trackers WHERE serial_number = ? AND id != ?");
    $stmt->execute([$serial, $id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Número de série já pertence a outro rastreador']);
        return;
    }

    // Get old status
    $stmt = $db->prepare("SELECT status FROM trackers WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE trackers SET model=?, serial_number=?, chip_iccid=?, status=?, motivo=? WHERE id=?");
        $stmt->execute([$model, $serial, $iccid, $status, $motivo ?: null, $id]);

        // Build history entry
        $action = 'Edicao';
        if ($old && $old['status'] !== $status) {
            if ($status === 'Em Manutenção')
                $action = 'Manutencao';
            elseif ($status === 'Defeito')
                $action = 'Manutencao';
            elseif ($status === 'Recebido')
                $action = 'Recebimento';
            elseif ($status === 'Enviado')
                $action = 'Envio';
        }

        $desc = "Status alterado de {$old['status']} para {$status}";
        if ($motivo && in_array($status, ['Defeito', 'Em Manutenção'])) {
            $desc .= " — Motivo: {$motivo}";
        }
        if ($old && $old['status'] === $status) {
            $desc = 'Dados atualizados';
            if ($motivo && in_array($status, ['Defeito', 'Em Manutenção']))
                $desc .= " — Motivo: {$motivo}";
        }

        $histUser = $_SESSION['user_name'] ?? 'Sistema';
        $stmtHistory = $db->prepare("INSERT INTO tracker_history (tracker_id, action, problem_description, modified_by) VALUES (?, ?, ?, ?)");
        $stmtHistory->execute([$id, $action, $desc, $histUser]);

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar']);
    }
}

function exportTrackers()
{
    $db = getDB();
    $status = $_GET['status'] ?? 'all';

    $sql = "SELECT e.*, 
                   s.iccid as chip_iccid, s.phone_number as chip_phone, s.carrier as chip_carrier
            FROM trackers e
            LEFT JOIN sim_cards s ON e.chip_iccid = s.iccid
            WHERE e.deleted_at IS NULL";
    $params = [];
    if ($status !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rastreadores_relatorio_' . date('Ymd') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['ID', 'Modelo', 'Serial Number', 'ICCID Chip', 'Telefone Chip', 'Operadora Chip', 'Status', 'Motivo'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['model'] ?? '',
            $r['serial_number'],
            $r['chip_iccid'] ?? '',
            $r['chip_phone'] ?? '',
            $r['chip_carrier'] ?? '',
            $r['status'],
            $r['motivo'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}
function deleteTracker()
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }

    $db = getDB();

    // Ensure soft-delete column exists
    try {
        $db->exec("ALTER TABLE trackers ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
    } catch (Exception $e) {
    }

    // Fetch info before deleting (for history)
    $stmt = $db->prepare("SELECT model, serial_number FROM trackers WHERE id = ?");
    $stmt->execute([$id]);
    $eq = $stmt->fetch();
    if (!$eq) {
        echo json_encode(['success' => false, 'message' => 'Rastreador não encontrado']);
        return;
    }

    $db->beginTransaction();
    try {
        // Hard-delete (as requested by user)
        $stmt = $db->prepare("DELETE FROM trackers WHERE id = ?");
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
            FROM tracker_history h 
            LEFT JOIN cards c ON h.card_id = c.id 
            WHERE h.tracker_id = ? 
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

    // Stats por status atual (todos os equipamentos, sem filtro de data)
    $stats = ['Estoque' => 0, 'Enviado' => 0, 'Recebido' => 0, 'Defeito' => 0, 'Em Manutenção' => 0];
    $stmtStats = $db->prepare("SELECT status, COUNT(*) as count FROM trackers WHERE deleted_at IS NULL GROUP BY status");
    $stmtStats->execute();
    while ($row = $stmtStats->fetch()) {
        if (array_key_exists($row['status'], $stats))
            $stats[$row['status']] = (int) $row['count'];
    }
    $stats['total'] = array_sum($stats);

    // Movimentações filtradas por data
    $sqlHistory = "
        SELECT h.action, h.problem_description, h.created_at, e.model, e.serial_number
        FROM tracker_history h
        JOIN trackers e ON h.tracker_id = e.id
        ORDER BY h.created_at DESC
        LIMIT 50
    ";
    $stmtH = $db->prepare($sqlHistory);
    $stmtH->execute();
    $recentEvents = $stmtH->fetchAll();

    // Lista de equipamentos (todos, filtrado por status se passado)
    $sqlEq = "SELECT t.*, s.phone_number AS chip_phone, s.carrier AS chip_carrier
              FROM trackers t
              LEFT JOIN sim_cards s ON t.chip_iccid = s.iccid
              WHERE t.deleted_at IS NULL";
    $params = [];
    if ($status !== 'all') {
        $sqlEq .= " AND t.status = ?";
        $params[] = $status;
    }
    $sqlEq .= " ORDER BY t.id DESC";
    $stmtEq = $db->prepare($sqlEq);
    $stmtEq->execute($params);
    $equipments = $stmtEq->fetchAll(PDO::FETCH_ASSOC);

    foreach ($equipments as &$item) {
        $stmtC = $db->prepare("SELECT company_name FROM cards WHERE category = 'rastreador' AND extra_data LIKE ? ORDER BY id DESC LIMIT 1");
        $stmtC->execute(['%"' . $item['serial_number'] . '"%']);
        $card = $stmtC->fetch();
        $item['linked_company'] = $card ? $card['company_name'] : null;
    }

    echo json_encode(['success' => true, 'stats' => $stats, 'events' => $recentEvents, 'equipments' => $equipments]);
}


