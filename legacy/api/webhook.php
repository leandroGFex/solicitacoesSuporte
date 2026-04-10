<?php
// =============================================================
// API - WEBHOOK (Integração com Middleware Node.js / Externos)
// =============================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

// Validar Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Use POST.']);
    exit;
}

// Ler Payload
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']);
    exit;
}

// Validar Chave (Header ou Body)
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$providedKey = $data['api_key'] ?? '';
if (strpos($authHeader, 'Bearer ') === 0) $providedKey = substr($authHeader, 7);

if (!defined('WEBHOOK_API_KEY') || $providedKey !== WEBHOOK_API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'API Key inválida.']);
    exit;
}

// =============================================================
// AUTO-MIGRATION (Garantir colunas de integração)
// =============================================================
try {
    $db = getDB();
    $db->exec("ALTER TABLE `cards` 
        ADD COLUMN IF NOT EXISTS `thread_id` VARCHAR(255) AFTER `updated_at`,
        ADD COLUMN IF NOT EXISTS `last_message_id` VARCHAR(255) AFTER `thread_id`,
        ADD COLUMN IF NOT EXISTS `cnpj` VARCHAR(25) AFTER `client_name`,
        ADD COLUMN IF NOT EXISTS `remessa` VARCHAR(100) AFTER `company_name`,
        ADD COLUMN IF NOT EXISTS `client_email` VARCHAR(150) AFTER `cnpj`,
        ADD COLUMN IF NOT EXISTS `address` TEXT AFTER `client_email`
    ");
} catch (Exception $e) { /* Colunas podem já existir */ }

// =============================================================
// PROCESSAR AÇÃO
// =============================================================
$action = $data['action'] ?? 'create_card';

try {
    switch ($action) {
        case 'create_card':
            handleCreateCard($db, $data);
            break;
        case 'add_comment':
            handleAddComment($db, $data);
            break;
        default:
            throw new Exception("Ação desconhecida: $action");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// =============================================================
// FUNÇÕES DE MANIPULAÇÃO
// =============================================================

function handleCreateCard($db, $data) {
    $title = $data['title'] ?? 'Novo Card via Email';
    $columnId = (int)($data['column_id'] ?? 1); // Default: Recebido
    $category = $data['category'] ?? 'cartao';
    $description = $data['description'] ?? '';
    
    // Calcular posição
    $stmtPos = $db->prepare("SELECT COALESCE(MAX(position), 0) FROM cards WHERE column_id = ? AND is_archived = 0");
    $stmtPos->execute([$columnId]);
    $newPos = (int)$stmtPos->fetchColumn() + 1;

    $sql = "INSERT INTO cards (
                column_id, position, title, description, category,
                company_name, remessa, cnpj, client_email, address,
                placa, card_number, extra_data,
                pos_request_type, pos_reason, reverse_tracking_code,
                thread_id, last_message_id, created_from_email, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $columnId, $newPos, $title, $description, $category,
        $data['company_name'] ?? null,
        $data['remessa'] ?? null,
        $data['cnpj'] ?? null,
        $data['client_email'] ?? null,
        $data['address'] ?? null,
        $data['placa'] ?? null,
        $data['card_number'] ?? null,
        isset($data['items']) ? json_encode($data['items']) : '[]',
        $data['pos_request_type'] ?? null,
        $data['pos_reason'] ?? null,
        $data['reverse_tracking_code'] ?? null,
        $data['thread_id'] ?? null,
        $data['message_id'] ?? null
    ]);

    $cardId = $db->lastInsertId();
    
    // Histórico
    $db->prepare("INSERT INTO card_history (card_id, user_name, action) VALUES (?, 'Sistema', 'Card criado via E-mail')")
       ->execute([$cardId]);

    echo json_encode(['success' => true, 'message' => 'Card criado!', 'card_id' => $cardId]);
}

function handleAddComment($db, $data) {
    $cardId = (int)($data['card_id'] ?? 0);
    $threadId = $data['thread_id'] ?? '';
    $content = $data['description'] ?? '';
    $author = $data['from_email'] ?? 'Cliente (Email)';

    // Se não veio card_id, tenta achar pelo thread_id
    if (!$cardId && !empty($threadId)) {
        $stmt = $db->prepare("SELECT id FROM cards WHERE thread_id = ? LIMIT 1");
        $stmt->execute([$threadId]);
        $cardId = $stmt->fetchColumn();
    }

    if (!$cardId) {
        throw new Exception("Card não identificado para este comentário (Thread ID: $threadId)");
    }

    // Inserir comentário
    $stmt = $db->prepare("INSERT INTO comments (card_id, user_id, user_name, content) VALUES (?, 0, ?, ?)");
    $stmt->execute([$cardId, "E-mail: $author", $content]);

    // Atualizar last_message_id no card
    if (!empty($data['message_id'])) {
        $db->prepare("UPDATE cards SET last_message_id = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$data['message_id'], $cardId]);
    }

    echo json_encode(['success' => true, 'message' => 'Comentário adicionado!', 'card_id' => $cardId]);
}

