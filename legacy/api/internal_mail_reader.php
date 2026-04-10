<?php
// =============================================================
// INTERNAL MAIL READER LOGIC
// Bloque separado para ser incluído pelo Web Cron ou acionamento manual
// =============================================================

if (!defined('EMAIL_ENABLED') || !EMAIL_ENABLED) return;

$imapFolder = defined('IMAP_FOLDER') ? IMAP_FOLDER : 'INBOX';
$result = lerEmailsImap(IMAP_HOST, IMAP_PORT, IMAP_USER, IMAP_PASS, $imapFolder, IMAP_SSL);

if (isset($result['error'])) return;

$db = getDB();
$criados = 0;

// Pegar primeira coluna padrão para novos cards (seletor de categoria não se aplica aqui diretamente no loop, mas usaremos a primeira da categoria detectada)
$getCol = function($cat) use ($db) {
    $stmt = $db->prepare("SELECT id FROM columns_kanban WHERE category = ? ORDER BY position ASC LIMIT 1");
    $stmt->execute([$cat]);
    return $stmt->fetchColumn() ?: 1;
};

foreach ($result['emails'] as $email) {
    $check = $db->prepare("SELECT id FROM email_log WHERE message_id = ?");
    $check->execute([$email['message_id']]);
    if ($check->fetch()) continue;

    $assunto = $email['subject'];
    $corpo = $email['body'];

    $getKeywords = function($constName, $default) {
        $str = defined($constName) ? constant($constName) : $default;
        return array_map('trim', explode(',', strtolower($str)));
    };
    $hasKeyword = function($textLow, $keywordsArr) {
        foreach ($keywordsArr as $kw) {
            if ($kw !== '' && strpos($textLow, $kw) !== false) return true;
        }
        return false;
    };

    $assuntoLow = strtolower($assunto);
    $category = null;

    if ($hasKeyword($assuntoLow, $getKeywords('IMAP_KW_CARTAO', 'cartão, cartao'))) $category = 'cartao';
    elseif ($hasKeyword($assuntoLow, $getKeywords('IMAP_KW_TAG', 'tag'))) $category = 'tag';
    elseif ($hasKeyword($assuntoLow, $getKeywords('IMAP_KW_POS', 'pos, máquina, maquina'))) $category = 'pos';
    elseif ($hasKeyword($assuntoLow, $getKeywords('IMAP_KW_RASTREIO', 'rastreador, rastreio'))) $category = 'rastreador';

    if (!$category) {
        $db->prepare("INSERT INTO email_log (message_id, subject, sender, card_id, created_at) VALUES (?,?,?, NULL, NOW())")
            ->execute([$email['message_id'], $assunto, $email['from']]);
        continue;
    }

    $pegarCampo = function ($regex, $text) {
        if (preg_match($regex, $text, $matches)) return trim($matches[1]);
        return '';
    };

    $placa = $pegarCampo('/-\s*PLACA:\s*(.*?)\s*(?:-|$)/si', $corpo) ?: $pegarCampo('/-\s*PLACA(?:\(S\))?:\s*(.*?)\s*(?:-|$)/si', $corpo);
    $cliente = $pegarCampo('/-\s*COLABORADOR:\s*(.*?)\s*(?:-|$)/s', $corpo);
    $empresa = $pegarCampo('/-\s*CLIENTE:\s*(.*?)\s*(?:-|$)/s', $corpo) ?: $pegarCampo('/-\s*EMPRESA:\s*(.*?)\s*(?:-|$)/s', $corpo);

    $primeiraColuna = $getCol($category);
    $maxPos = $db->prepare("SELECT COALESCE(MAX(position),0) FROM cards WHERE column_id = ?");
    $maxPos->execute([$primeiraColuna]);
    $pos = $maxPos->fetchColumn() + 1;

    $stmtCard = $db->prepare("
        INSERT INTO cards
            (column_id, title, description, category, client_email, client_name, company_name, placa, position, created_by, created_from_email, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, NOW(), NOW())
    ");
    $stmtCard->execute([
        $primeiraColuna,
        mb_substr($assunto, 0, 255),
        $corpo,
        $category,
        $email['from'],
        mb_substr($cliente ?: $email['from_name'], 0, 100),
        mb_substr($empresa, 0, 100),
        mb_substr($placa, 0, 50),
        $pos
    ]);

    $cardId = $db->lastInsertId();
    $db->prepare("INSERT INTO email_log (message_id, subject, sender, card_id, created_at) VALUES (?,?,?,?, NOW())")
        ->execute([$email['message_id'], $assunto, $email['from'], $cardId]);

    $db->prepare("INSERT INTO card_history (card_id, user_name, action, created_at) VALUES (?, ?, ?, NOW())")
        ->execute([$cardId, '🤖 Automatização', 'Card gerado via E-mail (CC Google Forms)']);
    
    // Processar automações de criação para e-mail
    AutomationEngine::process($cardId, 'card_created');

    $criados++;
}
