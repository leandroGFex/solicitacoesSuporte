<?php
// =============================================================
// HELPER - CARD & EQUIPMENT SYNC
// =============================================================

require_once __DIR__ . '/../config/config.php';

/**
 * Registrar Histórico do Card
 */
function logCardHistory($cardId, $action, $oldCol = null, $newCol = null)
{
    $db = getDB();
    $userId = $_SESSION['user_id'] ?? null;
    $userName = $_SESSION['user_name'] ?? 'Sistema';
    $stmt = $db->prepare("INSERT INTO card_history (card_id, user_id, user_name, action, old_col_name, new_col_name) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$cardId, $userId, $userName, $action, $oldCol, $newCol]);
}

/**
 * Sincronizar status do estoque (POS/Rastreador)
 */
function syncEquipmentsStatus($cardId, $colName)
{
    $db = getDB();
    $stmt = $db->prepare("SELECT category, extra_data, pos_request_type, pos_reason FROM cards WHERE id=?");
    $stmt->execute([$cardId]);
    $card = $stmt->fetch();

    if (!$card || empty($card['extra_data']) || !in_array($card['category'], ['pos', 'rastreador'])) {
        return;
    }

    $items = json_decode($card['extra_data'], true);
    if (!is_array($items) || empty($items))
        return;

    // Determinar novo status baseado na coluna e tipo de solicitação
    $reqType = trim($card['pos_request_type'] ?? '');
    $colNorm = strtolower(trim($colName));
    
    if ($reqType === 'Retirada' || $reqType === 'Reverso') {
        if ($colNorm === 'archived' || strpos($colNorm, 'recebido') !== false || strpos($colNorm, 'estoque') !== false) {
            $newStatus = 'Estoque';
        } else {
            $newStatus = $reqType;
        }
    } else {
        $newStatus = 'Estoque';
        if (strpos($colNorm, 'enviado') !== false || strpos($colNorm, 'aguardando') !== false) {
            $newStatus = 'Enviado';
        } elseif (strpos($colNorm, 'entregue') !== false || strpos($colNorm, 'recebido') !== false || strpos($colNorm, 'concluido') !== false || strpos($colNorm, 'concluído') !== false) {
            $newStatus = 'Recebido';
        } elseif (strpos($colNorm, 'retorno') !== false || strpos($colNorm, 'defeito') !== false) {
            $newStatus = 'Defeito';
        }
    }

    $table = $card['category'] === 'pos' ? 'pos_equipments' : 'trackers';
    $histTable = $card['category'] === 'pos' ? 'pos_history' : 'tracker_history';
    $idCol = $card['category'] === 'pos' ? 'pos_id' : 'tracker_id';

    foreach ($items as $item) {
        if (empty($item['serial'])) continue;

        $s = $db->prepare("SELECT id, status FROM {$table} WHERE serial_number = ?");
        $s->execute([$item['serial']]);
        $eq = $s->fetch();

        if ($eq && $eq['status'] !== $newStatus) {
            $db->prepare("UPDATE {$table} SET status=? WHERE id=?")->execute([$newStatus, $eq['id']]);
            
            $action = 'Edicao';
            if ($newStatus === 'Defeito') $action = 'Manutencao';
            elseif ($newStatus === 'Recebido') $action = 'Recebimento';
            elseif ($newStatus === 'Enviado') $action = 'Envio';
            elseif ($newStatus === 'Retirada') $action = 'Retirada';
            elseif ($newStatus === 'Reverso') $action = 'Reverso';
            elseif ($newStatus === 'Estoque') $action = 'Retorno ao Estoque';

            $db->prepare("INSERT INTO {$histTable} ({$idCol}, card_id, action, problem_description) VALUES (?, ?, ?, ?)")
                ->execute([$eq['id'], $cardId, $action, "Movido para a coluna: " . $colName]);
        }
    }
}
