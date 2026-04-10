<?php
// =============================================================
// API - CARDS
// =============================================================
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/card_helper.php';
require_once __DIR__ . '/../helpers/automation_helper.php';
require_once __DIR__ . '/../helpers/migration_helper.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// ── AUTO-MIGRAÇÃO: garante colunas extras existam sem precisar rodar SQL manual ──
// ── AUTO-MIGRAÇÃO: garante colunas extras existam ──
// A função runAutoMigration agora vem de helpers/migration_helper.php

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// Executar migração se for necessário (uma vez por request é seguro pois tem try/catch)
$db = getDB();
runAutoMigration($db);

try {
    switch ($action) {
        case 'list':
            listCards();
            break;
        case 'list_archived':
            listArchivedCards();
            break;
        case 'get':
            getCard();
            break;
        case 'create':
            createCard();
            break;
        case 'update':
            updateCard();
            break;
        case 'move':
            moveCard();
            break;
        case 'history':
            cardHistory();
            break;
        case 'delete':
            deleteCard();
            break;
        case 'archive':
            archiveCard();
            break;
        case 'unarchive':
            unarchiveCard();
            break;
        case 'generate_prepost':
            generatePrePost();
            break;
        case 'reorder':
            reorderCards();
            break;
        case 'bulk_archive':
            bulkArchiveCards();
            break;
        default:
            echo json_encode(['error' => 'Ação inválida']);
    }
} catch (Exception $e) {
    http_response_code(200); // retornar 200 para que o JS possa ler o JSON
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}

// =============================================================
// LIST
// =============================================================
function listCards()
{
    $columnId = (int) ($_GET['column_id'] ?? 0);
    $category = $_GET['category'] ?? 'cartao';
    $db = getDB();
    if ($columnId > 0) {
        $stmt = $db->prepare("SELECT c.*, u.name as creator_name, (SELECT COUNT(id) FROM comments WHERE card_id = c.id) as comments_count FROM cards c LEFT JOIN users u ON c.created_by = u.id WHERE c.is_archived = 0 AND c.column_id = ? AND c.category = ? ORDER BY c.created_at DESC, c.position ASC");
        $stmt->execute([$columnId, $category]);
    } else {
        $stmt = $db->prepare("SELECT c.*, u.name as creator_name, (SELECT COUNT(id) FROM comments WHERE card_id = c.id) as comments_count FROM cards c LEFT JOIN users u ON c.created_by = u.id WHERE c.is_archived = 0 AND c.category = ? ORDER BY c.column_id, c.created_at DESC, c.position ASC");
        $stmt->execute([$category]);
    }
    echo json_encode(['success' => true, 'cards' => $stmt->fetchAll()]);
}

// =============================================================
// LIST ARCHIVED
// =============================================================
function listArchivedCards()
{
    $db = getDB();
    $stmt = $db->query("
        SELECT c.id, c.title, c.tracking_code, c.tracking_status, c.client_name, c.company_name, c.placa, c.extra_data, c.updated_at, c.created_at, col.name as column_name 
        FROM cards c 
        LEFT JOIN columns_kanban col ON c.column_id = col.id 
        WHERE c.is_archived = 1 
        ORDER BY c.updated_at DESC
    ");
    echo json_encode(['success' => true, 'cards' => $stmt->fetchAll()]);
}

// =============================================================
// ARCHIVE / UNARCHIVE
// =============================================================
function archiveCard()
{
    $id = (int) ($_POST['id'] ?? 0);
    $db = getDB();
    $stmt = $db->prepare("UPDATE cards SET is_archived = 1, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    
    // Sincronizar status do equipamento ao arquivar
    syncEquipmentsStatus($id, 'archived');
    
    logCardHistory($id, "Arquivou o card manualmente");
    echo json_encode(['success' => true]);
}

function unarchiveCard()
{
    $id = (int) ($_POST['id'] ?? 0);
    $db = getDB();
    $stmt = $db->prepare("UPDATE cards SET is_archived = 0, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    logCardHistory($id, "Restaurou o card do arquivo");
    echo json_encode(['success' => true]);
}

/**
 * Action: Gerar Pré-Postagem Correios manualmente
 */
function generatePrePost()
{
    $id = (int) ($_POST['id'] ?? 0);
    $db = getDB();
    
    // Buscar card
    $stmt = $db->prepare("SELECT * FROM cards WHERE id = ?");
    $stmt->execute([$id]);
    $card = $stmt->fetch();

    if (!$card) {
        echo json_encode(['success' => false, 'message' => 'Card não encontrado']);
        return;
    }

    require_once __DIR__ . '/../helpers/correios.php';
    
    $prepostId = CorreiosAPI::createPrePost($card);
    
    // Se o retorno for um ID (geralmente numérico ou string sem a palavra Erro)
    if ($prepostId && strpos($prepostId, 'Erro') === false) {
        $db->prepare("UPDATE cards SET correios_prepost_id = ? WHERE id = ?")->execute([$prepostId, $id]);
        logCardHistory($id, "Gerou Pré-Postagem Correios (ID: $prepostId)");
        echo json_encode(['success' => true, 'prepost_id' => $prepostId]);
    } else {
        $msg = $prepostId ?: 'Falha ao gerar pré-postagem. Verifique se o endereço e o CNPJ estão corretos.';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}

// =============================================================
// GET SINGLE
// =============================================================
function getCard()
{
    $id = (int) ($_GET['id'] ?? 0);
    $db = getDB();
    $stmt = $db->prepare("SELECT c.*, u.name as creator_name FROM cards c LEFT JOIN users u ON c.created_by = u.id WHERE c.id = ?");
    $stmt->execute([$id]);
    $card = $stmt->fetch();
    if (!$card) {
        echo json_encode(['success' => false, 'message' => 'Card não encontrado']);
        return;
    }
    $cStmt = $db->prepare("SELECT com.*, u.name as user_name FROM comments com LEFT JOIN users u ON com.user_id = u.id WHERE com.card_id = ? ORDER BY com.created_at ASC");
    $cStmt->execute([$id]);
    $card['comments'] = $cStmt->fetchAll();
    // Decode extra_data JSON for JS convenience
    $card['extra_data_decoded'] = $card['extra_data'] ? json_decode($card['extra_data'], true) : null;
    echo json_encode(['success' => true, 'card' => $card]);
}

// =============================================================
// GET HISTORY
// =============================================================
function cardHistory()
{
    $id = (int) ($_GET['id'] ?? 0);
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM card_history WHERE card_id = ? ORDER BY created_at DESC");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'history' => $stmt->fetchAll()]);
}

// =============================================================
// CREATE
// =============================================================
function createCard()
{
    $db = getDB();
    $columnId = (int) ($_POST['column_id'] ?? 0);
    if (!$columnId) {
        $columnId = (int) $db->query("SELECT id FROM columns_kanban ORDER BY position ASC LIMIT 1")->fetchColumn();
    }
    $maxPos = $db->prepare("SELECT COALESCE(MAX(position),0) FROM cards WHERE column_id = ?");
    $maxPos->execute([$columnId]);

    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    $extraData = buildExtraData();

    $remessa = trim($_POST['remessa'] ?? '');
    if (empty($remessa)) {
        $remessa = date('dmy'); // DDMMYY
    }
    $address = $_POST['address_json'] ?? trim($_POST['address'] ?? '');

    $isCompleted = filter_var($_POST['is_completed'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $deadlineMet = null;
    if ($isCompleted) {
        $deadlineMet = 1;
        $stmtEntr = $db->prepare("SELECT id FROM columns_kanban WHERE LOWER(name) LIKE '%enviado%' OR LOWER(name) LIKE '%entregue%' OR LOWER(name) LIKE '%concluído%' OR LOWER(name) LIKE '%concluido%' ORDER BY position DESC LIMIT 1");
        $stmtEntr->execute();
        $entrId = $stmtEntr->fetchColumn();
        if ($entrId) {
            $columnId = $entrId;
        }
    }

    $stmt = $db->prepare("
        INSERT INTO cards
            (column_id, title, description, category, pos_request_type, pos_reason, company_name, remessa, placa, card_number,
             extra_data, client_name, client_email, cnpj, address, tracking_code, reverse_tracking_code,
             deadline, deadline_met, priority, position, created_by, created_from_email, withdrawal_declaration)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $columnId,
        trim($_POST['title'] ?? ''),
        trim($_POST['description'] ?? ''),
        $_POST['category'] ?? 'cartao',
        trim($_POST['pos_request_type'] ?? ''),
        trim($_POST['pos_reason'] ?? ''),
        trim($_POST['company_name'] ?? ''),
        $remessa,
        trim($_POST['placa'] ?? ''),          // compat legado
        trim($_POST['card_number'] ?? ''),     // compat legado
        $extraData,
        trim($_POST['client_name'] ?? ''),
        trim($_POST['client_email'] ?? ''),
        trim($_POST['cnpj'] ?? ''),
        $address,
        trim($_POST['tracking_code'] ?? ''),
        trim($_POST['reverse_tracking_code'] ?? ''),
        $deadline,
        $deadlineMet,
        $_POST['priority'] ?? 'media',
        $maxPos->fetchColumn() + 1,
        $_SESSION['user_id'],
        (int) ($_POST['from_email'] ?? 0),
        handleUpload()
    ]);
    $id = $db->lastInsertId();
    
    // Automação: Pré-postagem Oficial Correios (Apenas se NÃO for retirada)
    $reqType = $_POST['pos_request_type'] ?? '';
    if (empty($_POST['tracking_code']) && $reqType !== 'Retirada Presencial' && $reqType !== 'Retirada') {
        require_once __DIR__ . '/../helpers/correios.php';
        $fullCard = [
            'id' => $id,
            'company_name' => trim($_POST['company_name'] ?? ''),
            'client_name' => trim($_POST['client_name'] ?? ''),
            'address' => $address
        ];
        $prepostId = CorreiosAPI::createPrePost($fullCard);
        if ($prepostId) {
            $db->prepare("UPDATE cards SET correios_prepost_id = ? WHERE id = ?")->execute([$prepostId, $id]);
        }
    }

    $colStmt = $db->prepare("SELECT name FROM columns_kanban WHERE id=?");
    $colStmt->execute([$columnId]);
    $col = $colStmt->fetch();
    if ($col) {
        syncEquipmentsStatus($id, strtolower(trim($col['name'])));
        logCardHistory($id, "Criou o card na coluna " . $col['name']);
    }

    // Processar automações de criação
    AutomationEngine::process($id, 'card_created');

    echo json_encode(['success' => true, 'id' => $id]);
}

// =============================================================
// UPDATE
// =============================================================
function updateCard()
{
    $id = (int) ($_POST['id'] ?? 0);
    $colId = (int) ($_POST['column_id'] ?? 0);
    $db = getDB();
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    $deadlineMet = isset($_POST['deadline_met']) && $_POST['deadline_met'] !== '' ? (int) $_POST['deadline_met'] : null;
    $extraData = buildExtraData();
    $address = $_POST['address_json'] ?? trim($_POST['address'] ?? '');

    // Validar mudança de coluna para aplicar regra de prazos apenas se houver mudança via modal
    $colStmt = $db->prepare("SELECT column_id FROM cards WHERE id=?");
    $colStmt->execute([$id]);
    $oldCol = $colStmt->fetchColumn();

    $isCompleted = filter_var($_POST['is_completed'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($isCompleted) {
        $deadlineMet = 1;
        $stmtCheck = $db->prepare("SELECT name FROM columns_kanban WHERE id=?");
        $stmtCheck->execute([$colId ?: $oldCol]);
        $currName = strtolower((string) $stmtCheck->fetchColumn());
        $isConclusion = (strpos($currName, 'enviado') !== false || strpos($currName, 'entregue') !== false || strpos($currName, 'concluido') !== false || strpos($currName, 'concluído') !== false);

        if (!$isConclusion) {
            $stmtEntr = $db->prepare("SELECT id FROM columns_kanban WHERE LOWER(name) LIKE '%enviado%' OR LOWER(name) LIKE '%entregue%' OR LOWER(name) LIKE '%concluído%' OR LOWER(name) LIKE '%concluido%' ORDER BY position DESC LIMIT 1");
            $stmtEntr->execute();
            $entrId = $stmtEntr->fetchColumn();
            if ($entrId) {
                $colId = $entrId;
            }
        }
    }

    $stmt = $db->prepare("
        UPDATE cards SET
            title=?, description=?, category=?, pos_request_type=?, pos_reason=?, company_name=?, remessa=?, placa=?,
            card_number=?, extra_data=?,
            client_name=?, client_email=?, cnpj=?, address=?,
            tracking_code=?, reverse_tracking_code=?, deadline=?, deadline_met=?, priority=?, column_id=?,
            withdrawal_declaration=COALESCE(?, withdrawal_declaration)
        WHERE id=?
    ");
    $stmt->execute([
        trim($_POST['title'] ?? ''),
        trim($_POST['description'] ?? ''),
        $_POST['category'] ?? 'cartao',
        trim($_POST['pos_request_type'] ?? ''),
        trim($_POST['pos_reason'] ?? ''),
        trim($_POST['company_name'] ?? ''),
        trim($_POST['remessa'] ?? ''),
        trim($_POST['placa'] ?? ''),
        trim($_POST['card_number'] ?? ''),
        $extraData,
        trim($_POST['client_name'] ?? ''),
        trim($_POST['client_email'] ?? ''),
        trim($_POST['cnpj'] ?? ''),
        $address,
        trim($_POST['tracking_code'] ?? ''),
        trim($_POST['reverse_tracking_code'] ?? ''),
        $deadline,
        $deadlineMet,
        $_POST['priority'] ?? 'media',
        $colId ?: $oldCol,
        handleUpload(),
        $id
    ]);

    $stmtCol = $db->prepare("SELECT col.name FROM cards c JOIN columns_kanban col ON c.column_id = col.id WHERE c.id = ?");
    $stmtCol->execute([$id]);
    $col = $stmtCol->fetch();
    if ($col) {
        $colNorm = strtolower(trim($col['name']));
        syncEquipmentsStatus($id, $colNorm);

        // Se mudou de coluna via modal
        if ($colId && $colId != $oldCol) {
            AutomationEngine::process($id, 'move_to_column', ['column_id' => $colId]);
        } else {
            AutomationEngine::process($id, 'field_updated');
        }
    }

    // Log history
    if ($colId && $colId != $oldCol) {
        $oldName = $db->query("SELECT name FROM columns_kanban WHERE id=$oldCol")->fetchColumn();
        logCardHistory($id, "Moveu o card", $oldName ?: '?', $col ? $col['name'] : '?');
    } else {
        logCardHistory($id, "Editou as informações do card");
    }

    echo json_encode(['success' => true]);
}

// =============================================================
// MOVE (entre colunas) — detecta "Enviado" e "Entregue"
// =============================================================
function moveCard()
{
    $id = (int) ($_POST['id'] ?? 0);
    $colId = (int) ($_POST['column_id'] ?? 0);
    $position = (int) ($_POST['position'] ?? 0);
    $db = getDB();

    $oldName = $db->query("SELECT k.name FROM cards c JOIN columns_kanban k ON c.column_id = k.id WHERE c.id=$id")->fetchColumn();

    $db->prepare("UPDATE cards SET column_id=?, position=? WHERE id=?")->execute([$colId, $position, $id]);

    $colStmt = $db->prepare("SELECT name FROM columns_kanban WHERE id=?");
    $colStmt->execute([$colId]);
    $col = $colStmt->fetch();

    if ($col) {
        $colNorm = strtolower(trim($col['name']));
        logCardHistory($id, "Moveu o card", $oldName ?: '?', $col['name']);
        syncEquipmentsStatus($id, $colNorm);
        
        // Processar automações de movimento
        AutomationEngine::process($id, 'move_to_column', ['column_id' => $colId]);
    }
    echo json_encode(['success' => true]);
}

// =============================================================
// BULK ARCHIVE
// =============================================================
function bulkArchiveCards()
{
    $category = $_POST['category'] ?? 'todas';
    $period = $_POST['period'] ?? 'todos'; // Formato YYYY-MM
    $db = getDB();

    $sql = "UPDATE cards SET is_archived = 1, updated_at = NOW() WHERE is_archived = 0";
    $params = [];

    if ($category !== 'todas') {
        $sql .= " AND category = ?";
        $params[] = $category;
    }

    if ($period !== 'todos') {
        $sql .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
        $params[] = $period;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->rowCount();

    // Sincronizar status dos equipamentos arquivados
    if ($count > 0) {
        $selSql = "SELECT id FROM cards WHERE is_archived = 1 AND category IN ('pos', 'rastreador')";
        if ($category !== 'todas') $selSql .= " AND category = " . $db->quote($category);
        if ($period !== 'todos') $selSql .= " AND DATE_FORMAT(created_at, '%Y-%m') = " . $db->quote($period);
        
        $ids = $db->query($selSql)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $cid) {
            syncEquipmentsStatus($cid, 'archived');
        }
    }

    echo json_encode(['success' => true, 'count' => $count, 'message' => "{$count} solicitações arquivadas com sucesso."]);
}

// =============================================================
// DELETE
// =============================================================
function deleteCard()
{
    $id = (int) ($_POST['id'] ?? 0);
    getDB()->prepare("DELETE FROM cards WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
}

// =============================================================
// REORDER (dentro da mesma coluna)
// =============================================================
function reorderCards()
{
    $order = json_decode($_POST['order'] ?? '[]', true);
    $db = getDB();
    $stmt = $db->prepare("UPDATE cards SET position=? WHERE id=?");
    foreach ($order as $pos => $cardId) {
        $stmt->execute([$pos + 1, (int) $cardId]);
    }
    echo json_encode(['success' => true]);
}

// =============================================================
// HELPER: montar extra_data JSON por categoria
//
// Formato esperado do POST:
//   extra_items[]  = JSON de cada linha (placas, tags, seriais…)
//   OU campos individuais conforme categoria
// =============================================================
function buildExtraData()
{
    // O JS envia um único campo "extra_data_json" com o array serializado
    $raw = trim($_POST['extra_data_json'] ?? '');
    if ($raw === '' || $raw === '[]' || $raw === 'null')
        return null;

    // Validar JSON
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE)
        return null;
    if (empty($decoded))
        return null;

    return $raw; // já é string JSON válida
}

// =============================================================
// Helper: Dias Úteis restantes até uma data
// =============================================================
function diasUteisAte($deadline)
{
    $feriados = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '11-20', '12-25'];
    $hoje = new DateTime('today');
    $fim = new DateTime($deadline);
    if ($fim < $hoje)
        return 0;
    $uteis = 0;
    $cur = clone $hoje;
    while ($cur <= $fim) {
        $dow = (int) $cur->format('N'); // 1=seg, 7=dom
        $mmdd = $cur->format('m-d');
        if ($dow < 6 && !in_array($mmdd, $feriados))
            $uteis++;
        $cur->modify('+1 day');
    }
    return $uteis;
}

/**
 * HELPER: Upload de Declaração de Retirada
 */
function handleUpload()
{
    // Se não houver arquivo ou houver erro no upload, retorna null
    if (empty($_FILES['withdrawal_declaration']) || $_FILES['withdrawal_declaration']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES['withdrawal_declaration'];
    
    // Extensões permitidas
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'txt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return null; // Ou lançar erro se preferir
    }

    // Pasta de destino: assets/declarations/
    $dir = __DIR__ . '/../assets/declarations/';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    // Nome único para evitar sobrescrita
    $filename = uniqid('dec_', true) . '.' . $ext;
    
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return $filename;
    }
    
    return null;
}