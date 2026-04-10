<?php
// api/dashboard_stats.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    $db = getDB();

    // Identificar colunas dinamicamente
    $cols = $db->query("SELECT id, name FROM columns_kanban ORDER BY position ASC")->fetchAll();
    
    $conclusionColIds = [];
    foreach($cols as $c) {
        $name = strtolower($c['name']);
        if (strpos($name, 'entregue') !== false || strpos($name, 'concluido') !== false || strpos($name, 'concluído') !== false || strpos($name, 'enviado') !== false || strpos($name, 'finalizado') !== false) {
            $conclusionColIds[] = $c['id'];
        }
    }
    if (empty($conclusionColIds) && !empty($cols)) {
        $conclusionColIds[] = end($cols)['id'];
    }

    $conclusionStr = implode(',', $conclusionColIds);

    // TOTAL GERAL (Não arquivado)
    $total = (int) $db->query("SELECT COUNT(*) FROM cards WHERE is_archived = 0")->fetchColumn();

    // Em Andamento (Todas que NÃO foram enviadas/entregues)
    $emAndamento = (int) $db->query("SELECT COUNT(*) FROM cards WHERE is_archived = 0 AND column_id NOT IN ($conclusionStr)")->fetchColumn();

    // Atrasadas (deadline_met = 0 OR (deadline < CURDATE() AND deadline_met IS NULL))
    $atrasadas = (int) $db->query("SELECT COUNT(*) FROM cards WHERE is_archived = 0 AND (deadline_met = 0 OR (deadline < CURDATE() AND deadline_met IS NULL AND column_id NOT IN ($conclusionStr)))")->fetchColumn();

    // Entregues (Apenas as finalizadas)
    $entregues = (int) $db->query("SELECT COUNT(*) FROM cards WHERE is_archived = 0 AND column_id IN ($conclusionStr)")->fetchColumn();

    // 2. Stats de Equipamentos (Relatório Rápido)
    $posStats = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM pos_equipments) as total,
            (SELECT COUNT(*) FROM pos_equipments WHERE status = 'Estoque') as estoque,
            (SELECT COUNT(*) FROM pos_equipments WHERE status = 'Enviado') as enviados,
            (SELECT COUNT(*) FROM pos_equipments WHERE status = 'Recebido') as recebidos,
            (SELECT COUNT(*) FROM pos_equipments WHERE status = 'Defeito') as defeito,
            (SELECT COUNT(*) FROM pos_history WHERE action = 'Manutencao') as manutencao,
            (SELECT COUNT(*) FROM cards WHERE category = 'pos' AND pos_request_type = 'Retirada' AND is_archived = 0) as retirada,
            (SELECT COUNT(*) FROM cards WHERE category = 'pos' AND pos_request_type = 'Reverso' AND is_archived = 0) as reverso
    ")->fetch(PDO::FETCH_ASSOC);

    $trackerStats = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM trackers) as total,
            (SELECT COUNT(*) FROM trackers WHERE status = 'Estoque') as estoque,
            (SELECT COUNT(*) FROM trackers WHERE status = 'Enviado') as enviados,
            (SELECT COUNT(*) FROM trackers WHERE status = 'Recebido') as recebidos,
            (SELECT COUNT(*) FROM trackers WHERE status = 'Defeito') as defeito,
            (SELECT COUNT(*) FROM tracker_history WHERE action = 'Manutencao') as manutencao
    ")->fetch(PDO::FETCH_ASSOC);

    $chipStats = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM sim_cards WHERE deleted_at IS NULL) as total,
            (SELECT COUNT(*) FROM sim_cards WHERE status = 'Estoque' AND deleted_at IS NULL) as estoque,
            (SELECT COUNT(*) FROM sim_cards WHERE status = 'Em Uso' AND deleted_at IS NULL) as em_uso,
            (SELECT COUNT(*) FROM sim_cards WHERE status = 'Cancelado' AND deleted_at IS NULL) as cancelados,
            (SELECT COUNT(*) FROM sim_cards WHERE status = 'Defeito' AND deleted_at IS NULL) as defeito
    ")->fetch(PDO::FETCH_ASSOC);

    // 3. Solicitações por Categoria (para o gráfico)
    $stmtCat = $db->query("SELECT category, COUNT(*) as count FROM cards WHERE is_archived = 0 GROUP BY category");
    $categories = $stmtCat->fetchAll();

    // 4. Atividades Recentes por Equipamento
    $posRecent = $db->query("
        SELECT h.*, p.serial_number as identifier 
        FROM pos_history h 
        JOIN pos_equipments p ON h.pos_id = p.id 
        ORDER BY h.id DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $trackerRecent = $db->query("
        SELECT h.*, t.serial_number as identifier 
        FROM tracker_history h 
        JOIN trackers t ON h.tracker_id = t.id 
        ORDER BY h.id DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $chipRecent = $db->query("
        SELECT h.*, s.phone_number as identifier 
        FROM sim_history h 
        JOIN sim_cards s ON h.sim_id = s.id 
        ORDER BY h.id DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 5. Atividades Recentes Kanban
    try {
        $recent = $db->query("
            SELECT ch.*, c.title as card_title, c.priority
            FROM card_history ch
            JOIN cards c ON ch.card_id = c.id
            ORDER BY ch.id DESC
            LIMIT 5
        ")->fetchAll();
    } catch (Exception $e) {
        $recent = $db->query("
            SELECT com.id, com.card_id, com.user_name as modified_by, com.content as action, com.created_at, c.title as card_title, c.priority
            FROM comments com
            JOIN cards c ON com.card_id = c.id
            ORDER BY com.id DESC
            LIMIT 5
        ")->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => $total,
            'emAndamento' => $emAndamento,
            'atrasadas' => $atrasadas,
            'entregues' => $entregues
        ],
        'equipment_stats' => [
            'pos' => [
                'summary' => $posStats,
                'recent' => $posRecent
            ],
            'trackers' => [
                'summary' => $trackerStats,
                'recent' => $trackerRecent
            ],
            'chips' => [
                'summary' => $chipStats,
                'recent' => $chipRecent
            ]
        ],
        'categories' => $categories,
        'recent' => $recent
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
    ]);
}
