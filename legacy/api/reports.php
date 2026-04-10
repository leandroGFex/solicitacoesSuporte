<?php
// =============================================================
// API - RELATÓRIOS DE PRAZOS
// =============================================================
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'generate';

switch ($action) {
    case 'generate':
        generateReport();
        break;
    case 'export':
        exportCsv();
        break;
    default:
        echo json_encode(['error' => 'Ação inválida']);
}

// =============================================================
// GERAR RELATÓRIO
// =============================================================
function generateReport()
{
    $db = getDB();
    $dateFrom = !empty($_POST['date_from']) ? $_POST['date_from'] : date('Y-m-01');
    $dateTo = !empty($_POST['date_to']) ? $_POST['date_to'] : date('Y-m-t');

    // Normaliza caso venha no formato DD/MM/YYYY
    $dateFrom = preg_replace('/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$2-$1', trim($dateFrom));
    $dateTo = preg_replace('/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$2-$1', trim($dateTo));
    $status = $_POST['status'] ?? 'all';
    $catFilter = $_POST['category'] ?? 'all';

    // Query base com join na coluna para pegar cor e nome
    $where = ['c.deadline IS NOT NULL', 'DATE(c.created_at) BETWEEN ? AND ?'];
    $params = [$dateFrom, $dateTo];

    if ($status === 'met') {
        $where[] = 'c.deadline_met = 1';
    } elseif ($status === 'missed') {
        $where[] = 'c.deadline_met = 0';
    } elseif ($status === 'open') {
        $where[] = 'c.deadline_met IS NULL AND c.deadline >= CURDATE()';
    } elseif ($status === 'overdue') {
        $where[] = 'c.deadline_met IS NULL AND c.deadline < CURDATE()';
    }

    if ($catFilter !== 'all') {
        $where[] = 'c.category = ?';
        $params[] = $catFilter;
    }

    $sql = "SELECT c.*, col.name as column_name, col.color as column_color, c.created_at,
                   (SELECT MIN(created_at) FROM card_history ch WHERE ch.card_id = c.id AND (LOWER(ch.new_col_name) LIKE '%entregue%' OR LOWER(ch.new_col_name) LIKE '%concluído%' OR LOWER(ch.new_col_name) LIKE '%concluido%' OR LOWER(ch.new_col_name) LIKE '%enviado%' OR LOWER(ch.action) LIKE '%entregue%' OR LOWER(ch.action) LIKE '%enviado%')) as completion_date,
                   (SELECT status_json FROM tracking_history th WHERE th.card_id = c.id ORDER BY th.id DESC LIMIT 1) as latest_tracking_json
            FROM cards c
            LEFT JOIN columns_kanban col ON c.column_id = col.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.deadline ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cards = $stmt->fetchAll();

    // Processar campos adicionais nos cartões
    foreach ($cards as &$c) {
        $c['delivery_date'] = '';
        $c['tracking_latest_status'] = '';
        if (!empty($c['latest_tracking_json'])) {
            $tj = json_decode($c['latest_tracking_json'], true);
            if (!empty($tj['objetos'][0]['eventos'][0])) {
                $ev = $tj['objetos'][0]['eventos'][0];
                $c['tracking_latest_status'] = $ev['descricao'] ?? '';
                $c['delivery_date'] = $ev['dtHrCriado'] ?? '';
            }
        }
        unset($c['latest_tracking_json']); // não precisa enviar todo json
    }

    // ---- Stats globais ----
    $totalStmt = $db->prepare("SELECT COUNT(*) FROM cards WHERE deadline IS NOT NULL AND DATE(created_at) BETWEEN ? AND ?");
    $totalStmt->execute([$dateFrom, $dateTo]);
    $total = (int) $totalStmt->fetchColumn();

    $metStmt = $db->prepare("SELECT COUNT(*) FROM cards WHERE deadline_met=1 AND DATE(created_at) BETWEEN ? AND ?");
    $metStmt->execute([$dateFrom, $dateTo]);
    $met = (int) $metStmt->fetchColumn();

    $missedStmt = $db->prepare("SELECT COUNT(*) FROM cards WHERE deadline_met=0 AND DATE(created_at) BETWEEN ? AND ?");
    $missedStmt->execute([$dateFrom, $dateTo]);
    $missed = (int) $missedStmt->fetchColumn();

    $openStmt = $db->prepare("SELECT COUNT(*) FROM cards WHERE deadline_met IS NULL AND deadline >= CURDATE() AND DATE(created_at) BETWEEN ? AND ?");
    $openStmt->execute([$dateFrom, $dateTo]);
    $open = (int) $openStmt->fetchColumn();

    $overdueStmt = $db->prepare("SELECT COUNT(*) FROM cards WHERE deadline_met IS NULL AND deadline < CURDATE() AND DATE(created_at) BETWEEN ? AND ?");
    $overdueStmt->execute([$dateFrom, $dateTo]);
    $overdue = (int) $overdueStmt->fetchColumn();

    // ---- Stats por categoria ----
    $catStmt = $db->prepare("
        SELECT category,
               COUNT(*) as total,
               SUM(deadline_met = 1) as met,
               SUM(deadline_met = 0) as missed
        FROM cards
        WHERE deadline IS NOT NULL AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY category
    ");
    $catStmt->execute([$dateFrom, $dateTo]);
    $byCategory = $catStmt->fetchAll();

    // ---- Cartões enviados (categoria='cartao' com deadline_met=1) ----
    $cardsStmt = $db->prepare("
        SELECT COUNT(*) as enviados, COUNT(card_number) as com_numero
        FROM cards
        WHERE category = 'cartao' AND deadline_met = 1 AND DATE(created_at) BETWEEN ? AND ?
    ");
    $cardsStmt->execute([$dateFrom, $dateTo]);
    $cartoesSent = $cardsStmt->fetch();

    $catLabels = ['cartao' => 'Cartão', 'tag' => 'Tag', 'pos' => 'POS', 'rastreador' => 'Rastreador'];
    foreach ($byCategory as &$cat) {
        $cat['label'] = $catLabels[$cat['category']] ?? ucfirst($cat['category']);
    }

    echo json_encode([
        'success' => true,
        'cards' => $cards,
        'stats' => [
            'total' => $total,
            'met' => $met,
            'missed' => $missed,
            'open' => $open,
            'overdue' => $overdue,
            'by_category' => $byCategory,
            'cartoes_enviados' => (int) ($cartoesSent['enviados'] ?? 0),
        ]
    ]);
}

// =============================================================
// EXPORTAR CSV
// =============================================================
function exportCsv()
{
    $db = getDB();
    $dateFrom = !empty($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
    $dateTo = !empty($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

    $dateFrom = preg_replace('/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$2-$1', trim($dateFrom));
    $dateTo = preg_replace('/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$2-$1', trim($dateTo));
    $status = $_GET['status'] ?? 'all';
    $catFilter = $_GET['category'] ?? 'all';

    $where = ['c.deadline IS NOT NULL', 'DATE(c.created_at) BETWEEN ? AND ?'];
    $params = [$dateFrom, $dateTo];

    if ($status === 'met') {
        $where[] = 'c.deadline_met = 1';
    } elseif ($status === 'missed') {
        $where[] = 'c.deadline_met = 0';
    } elseif ($status === 'open') {
        $where[] = 'c.deadline_met IS NULL AND c.deadline >= CURDATE()';
    } elseif ($status === 'overdue') {
        $where[] = 'c.deadline_met IS NULL AND c.deadline < CURDATE()';
    }

    if ($catFilter !== 'all') {
        $where[] = 'c.category = ?';
        $params[] = $catFilter;
    }

    $sql = "
        SELECT c.id, c.title, c.category, c.company_name, c.client_name, c.remessa, c.placa, c.extra_data,
               c.pos_request_type, c.pos_reason, c.reverse_tracking_code,
               c.tracking_code, c.deadline, c.deadline_met, c.created_at, col.name as coluna,
               (SELECT MIN(created_at) FROM card_history ch WHERE ch.card_id = c.id AND (LOWER(ch.new_col_name) LIKE '%entregue%' OR LOWER(ch.new_col_name) LIKE '%concluído%' OR LOWER(ch.action) LIKE '%entregue%' OR LOWER(ch.new_col_name) LIKE '%enviado%' OR LOWER(ch.action) LIKE '%enviado%')) as completion_date,
               (SELECT status_json FROM tracking_history th WHERE th.card_id = c.id ORDER BY th.id DESC LIMIT 1) as latest_tracking_json
        FROM cards c
        LEFT JOIN columns_kanban col ON c.column_id = col.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.deadline ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_prazos_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8

    $out = fopen('php://output', 'w');
    $catLabels = ['cartao' => 'Cartão', 'tag' => 'Tag', 'pos' => 'POS', 'rastreador' => 'Rastreador'];

    // Adiciona o título com o período
    $periodoStr = date('d/m/Y', strtotime($dateFrom)) . ' a ' . date('d/m/Y', strtotime($dateTo));
    fputcsv($out, ['RELATÓRIO DE ENVIOS', 'Período: ' . $periodoStr], ';');
    fputcsv($out, [], ';');

    fputcsv($out, ['#', 'Data Criação', 'Título', 'Categoria', 'Serviço POS', 'Motivo', 'Empresa / Cliente', 'Rastreio (Normal/Reverso)', 'Último Evento Rastreio', 'Prazo', 'Data Conclusão', 'Status', 'Coluna'], ';');
    foreach ($rows as $r) {
        $met = $r['deadline_met'] !== null ? ($r['deadline_met'] ? 'Cumprido' : 'Atrasado') : 'Em aberto';
        if ($r['deadline'] && $r['deadline_met'] === null && $r['deadline'] < date('Y-m-d')) {
            $met = 'Vencido';
        }

        $finalClient = $r['company_name'] ?: $r['client_name'] ?: '-';
        if ($r['company_name'] && $r['client_name'])
            $finalClient = $r['company_name'] . ' / ' . $r['client_name'];

        $finalPlaca = $r['placa'] ?: '-';
        // Extrai placa do extra_data se possível
        if (empty($r['placa']) && !empty($r['extra_data'])) {
            $extra = json_decode($r['extra_data'], true);
            if (is_array($extra)) {
                $placas = array_filter(array_column($extra, 'placa'));
                if ($placas)
                    $finalPlaca = implode(', ', $placas);
            }
        }

        $createdAt = '';
        if ($r['created_at']) {
            $createdAt = date('d/m/Y H:i', strtotime($r['created_at']));
        }

        $completionDate = '';
        if ($r['completion_date']) {
            $completionDate = date('d/m/Y H:i', strtotime($r['completion_date']));
        }

        $deliveryDate = '';
        $trackingLatestStatus = '';
        if (!empty($r['latest_tracking_json'])) {
            $tj = json_decode($r['latest_tracking_json'], true);
            if (!empty($tj['objetos'][0]['eventos'][0])) {
                $ev = $tj['objetos'][0]['eventos'][0];
                $trackingLatestStatus = $ev['descricao'] ?? '';
                $deliveryDate = $ev['dtHrCriado'] ?? '';
            }
        }

        $trackingInfoRaw = $trackingLatestStatus ? "$trackingLatestStatus em $deliveryDate" : '';

        $finalTracking = $r['tracking_code'] ?: '';
        if ($r['reverse_tracking_code']) {
            $finalTracking .= $finalTracking ? " / Rev: " . $r['reverse_tracking_code'] : "Rev: " . $r['reverse_tracking_code'];
        }

        fputcsv($out, [
            $r['id'],
            $createdAt,
            $r['title'],
            $catLabels[$r['category']] ?? $r['category'],
            $r['pos_request_type'] ?: '-',
            $r['pos_reason'] ?: '-',
            $finalClient,
            $finalTracking,
            $trackingInfoRaw,
            $r['deadline'] ? date('d/m/Y', strtotime($r['deadline'])) : '',
            $completionDate,
            $met,
            $r['coluna'],
        ], ';');
    }
    fclose($out);
    exit;
}
