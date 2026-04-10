<?php
// =============================================================
// API - RASTREAMENTO CORREIOS
// =============================================================
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/correios.php';
require_once __DIR__ . '/../helpers/card_helper.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'track';

switch ($action) {
    case 'track':
        trackPackage();
        break;
    case 'history':
        trackingHistory();
        break;
    default:
        echo json_encode(['error' => 'Ação inválida']);
}

function trackPackage()
{
    $code = strtoupper(trim($_POST['code'] ?? $_GET['code'] ?? ''));
    $cardId = (int) ($_POST['card_id'] ?? $_GET['card_id'] ?? 0);

    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Código de rastreio não informado']);
        return;
    }

    $result = CorreiosAPI::track($code);

    if (!$result || isset($result['error'])) {
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Não foi possível consultar o rastreio.']);
        return;
    }

    // Salvar histórico e atualizar card
    if ($cardId > 0) {
        $db = getDB();
        $statusJson = json_encode($result);

        // Salvar no histórico
        $stmt = $db->prepare("INSERT INTO tracking_history (card_id, tracking_code, status_json) VALUES (?,?,?)");
        $stmt->execute([$cardId, $code, $statusJson]);

        // Pegar status mais recente
        $latestStatus = '';
        if (!empty($result['objetos'][0]['eventos'][0])) {
            $ev = $result['objetos'][0]['eventos'][0];
            $latestStatus = $ev['descricao'] ?? '';
            if (!empty($ev['detalhe']))
                $latestStatus .= ' - ' . $ev['detalhe'];
        }

        // Atualizar card e possivelmente a coluna (lógica igual à automação)
        $fullStatus = mb_strtolower($latestStatus);

        // Obter as colunas base de conclusao/retorno se o card precisar ser movido
        $stmtCol = $db->prepare('SELECT column_id FROM cards WHERE id=?');
        $stmtCol->execute([$cardId]);
        $currentCol = $stmtCol->fetchColumn();

        $moveToId = null;
        $moveToName = null;
        $actionMsg = 'Status de rastreio atualizado pela consulta manual';

        if (strpos($fullStatus, 'entregue') !== false || strpos($fullStatus, 'recebido') !== false || strpos($fullStatus, 'retirado') !== false) {
            // Tenta pegar a coluna Entregue primeiro, se falhar tenta Recebido
            $stmtCols = $db->query("SELECT id, name FROM columns_kanban WHERE LOWER(name) LIKE '%entregue%' OR LOWER(name) LIKE '%concluído%' ORDER BY id ASC LIMIT 1");
            $colTarget = $stmtCols->fetch();
            if (!$colTarget) {
                $stmtCols = $db->query("SELECT id, name FROM columns_kanban WHERE LOWER(name) LIKE '%recebido%' LIMIT 1");
                $colTarget = $stmtCols->fetch();
            }
            if ($colTarget) {
                $moveToId = $colTarget['id'];
                $moveToName = $colTarget['name'];
                $actionMsg = "Automação (Manual): Entregue";
            }
        } elseif (strpos($fullStatus, 'retornando') !== false || strpos($fullStatus, 'devolvido') !== false || strpos($fullStatus, 'recusou') !== false || strpos($fullStatus, 'incorreto') !== false || strpos($fullStatus, 'não atendido') !== false || strpos($fullStatus, 'não procurado') !== false) {
            $stmtCols = $db->query("SELECT id, name FROM columns_kanban WHERE LOWER(name) LIKE '%retorno%' OR LOWER(name) LIKE '%devolução%' LIMIT 1");
            $colTarget = $stmtCols->fetch();
            if ($colTarget) {
                $moveToId = $colTarget['id'];
                $moveToName = $colTarget['name'];
                $actionMsg = "Automação (Manual): Retorno/Devolução";
            }
        } elseif (strpos($fullStatus, 'postado') !== false || strpos($fullStatus, 'encaminhado') !== false || strpos($fullStatus, 'em trânsito') !== false || strpos($fullStatus, 'saiu para entrega') !== false) {
            // Se já foi postado ou encaminhado, move para "Enviado"
            $stmtCols = $db->query("SELECT id, name FROM columns_kanban WHERE LOWER(name) LIKE '%enviado%' LIMIT 1");
            $colTarget = $stmtCols->fetch();
            if ($colTarget) {
                $moveToId = $colTarget['id'];
                $moveToName = $colTarget['name'];
                $actionMsg = "Automação (Manual): Enviado / Em Trânsito";
            }
        }

        if ($moveToId && $moveToId != $currentCol) {
            $oldColName = $db->query("SELECT name FROM columns_kanban WHERE id=$currentCol")->fetchColumn() ?: '?';

            // Move o card
            $deadlineMet = (strpos($fullStatus, 'entregue') !== false || strpos($fullStatus, 'recebido') !== false || strpos($fullStatus, 'retirado') !== false) ? 1 : null;
            $db->prepare("UPDATE cards SET tracking_status=?, tracking_updated_at=NOW(), column_id=?, deadline_met=? WHERE id=?")
                ->execute([$latestStatus, $moveToId, $deadlineMet, $cardId]);

            // Histórico Kanban Visual
            $stmtCardHist = $db->prepare("INSERT INTO card_history (card_id, user_name, action, old_col_name, new_col_name) VALUES (?, ?, ?, ?, ?)");
            $stmtCardHist->execute([$cardId, $_SESSION['user_name'] ?? 'Sistema', "Moveu o card ($actionMsg)", $oldColName, $moveToName]);
        } else {
            // Apenas atualiza status
            $db->prepare("UPDATE cards SET tracking_status=?, tracking_updated_at=NOW() WHERE id=?")
                ->execute([$latestStatus, $cardId]);
        }
    }

    echo json_encode(['success' => true, 'data' => $result]);
}

function trackingHistory()
{
    $cardId = (int) ($_GET['card_id'] ?? 0);
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tracking_history WHERE card_id=? ORDER BY checked_at DESC LIMIT 20");
    $stmt->execute([$cardId]);
    echo json_encode(['success' => true, 'history' => $stmt->fetchAll()]);
}
