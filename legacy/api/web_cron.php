<?php
// =============================================================
// WEB CRON - PROCESSADOR CENTRAL
// Chamado via JS para contornar limitação de cron real
// =============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/automation_helper.php';
require_once __DIR__ . '/../helpers/correios.php';

// Controle de frequência (ex: rodar a cada 30 minutos no máximo)
$cronFile = __DIR__ . '/../config/.last_web_cron';
$now = time();
$lastRun = file_exists($cronFile) ? (int)file_get_contents($cronFile) : 0;

if (($now - $lastRun) < 1800) { // 30 minutos
    echo json_encode(['success' => true, 'message' => 'Pulado (rodou recentemente)']);
    exit;
}
file_put_contents($cronFile, $now);

$db = getDB();

// 1. Atualizar Rastreios (Substituindo cron_tracking antigo)
$stmtCards = $db->query("SELECT id, tracking_code, column_id FROM cards WHERE is_archived = 0 AND tracking_code IS NOT NULL AND tracking_code != '' AND (tracking_updated_at IS NULL OR tracking_updated_at < DATE_SUB(NOW(), INTERVAL 8 HOUR)) LIMIT 20");
$cards = $stmtCards->fetchAll();

foreach ($cards as $card) {
    try {
        $result = CorreiosAPI::track($card['tracking_code']);
        if ($result && !empty($result['objetos'][0]['eventos'])) {
            $ev = $result['objetos'][0]['eventos'][0];
            $newStatus = $ev['descricao'] . ($ev['detalhe'] ? ' - ' . $ev['detalhe'] : '');
            
            $db->prepare("UPDATE cards SET tracking_status = ?, tracking_updated_at = NOW() WHERE id = ?")
               ->execute([$newStatus, $card['id']]);
            
            // Disparar evento de campo atualizado (pode acionar regras)
            AutomationEngine::process($card['id'], 'field_updated', ['field' => 'tracking_status', 'value' => $newStatus]);
        }
    } catch (Exception $e) { /* pular erro individual */ }
}

// 2. Ler E-mails (Substituindo cron_mail antigo)
if (defined('EMAIL_ENABLED') && EMAIL_ENABLED) {
    try {
        // Rate limit para e-mail (5 minutos)
        $mailLock = __DIR__ . '/../config/.last_mail_sync';
        $lastMail = file_exists($mailLock) ? (int)file_get_contents($mailLock) : 0;
        
        if (($now - $lastMail) > 300) {
            file_put_contents($mailLock, $now);
            require_once __DIR__ . '/../helpers/mail_reader.php';
            // Chama a lógica de leitura (que pode ser movida para uma função ou incluída)
            include __DIR__ . '/internal_mail_reader.php'; 
        }
    } catch (Exception $e) { /* fail silent */ }
}

// 3. Processar Automações de Tempo (Arquivamento e Datas)
// Arquivar entregues há mais de 30 dias (limpeza automática)
$db->query("UPDATE cards SET is_archived = 1 WHERE is_archived = 0 AND deadline_met = 1 AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");

echo json_encode(['success' => true]);
