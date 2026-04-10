<?php
/**
 * Middleware Helper
 * Responsável pela comunicação PHP -> Node.js (Middleware)
 */

if (!defined('MIDDLEWARE_URL')) {
    define('MIDDLEWARE_URL', 'https://flex-middleware.onrender.com/api/send-email'); // Alterar após deploy
}

function notifyMiddleware($to, $subject, $body, $threadId = null, $messageId = null) {
    if (empty($to)) return false;

    $payload = [
        'to' => $to,
        'subject' => $subject,
        'body' => $body,
        'threadId' => $threadId,
        'messageId' => $messageId,
        'token' => defined('INTERNAL_TOKEN') ? INTERNAL_TOKEN : 'seu_token_aqui'
    ];

    $ch = curl_init(MIDDLEWARE_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200);
}

/**
 * Notifica o cliente sobre uma atualização no card
 */
function notifyClientUpdate($cardId, $type, $extra = '') {
    $db = getDB();
    $stmt = $db->prepare("SELECT title, client_email, thread_id, last_message_id FROM cards WHERE id = ?");
    $stmt->execute([$cardId]);
    $card = $stmt->fetch();

    if (!$card || empty($card['client_email'])) return false;

    $subject = "RE: " . $card['title'] . " [CHAMADO #$cardId]";
    $body = "";

    switch ($type) {
        case 'comment':
            $body = "Novo comentário em sua solicitação:\n\n\"$extra\"\n\nPara responder, basta responder a este e-mail.";
            break;
        case 'status':
            $body = "Sua solicitação mudou de status para: $extra.\n\nFique atento para novas atualizações.";
            break;
        case 'tracking':
            $body = "Sua solicitação foi enviada! Código de Rastreio: $extra.\n\nVocê pode acompanhar pelo link de rastreio oficial.";
            break;
    }

    return notifyMiddleware(
        $card['client_email'],
        $subject,
        $body,
        $card['thread_id'],
        $card['last_message_id']
    );
}
