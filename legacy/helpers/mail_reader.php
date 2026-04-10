<?php
// =============================================================
// HELPER - LEITOR DE E-MAIL IMAP
// Lê e-mails não lidos e retorna dados para criação de cards
// =============================================================

/**
 * Conecta à caixa IMAP e lê e-mails não lidos.
 * Retorna array de e-mails prontos para virar cards.
 */
function lerEmailsImap($host, $port, $user, $pass, $folder = 'INBOX', $ssl = true)
{
    if (!function_exists('imap_open')) {
        return ['error' => 'Extensão IMAP não disponível no servidor.'];
    }

    $flag = $ssl ? '/ssl/novalidate-cert' : '/notls';
    $mailbox = "{{$host}:{$port}/imap{$flag}}{$folder}";

    $conexao = @imap_open($mailbox, $user, $pass, 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);

    if (!$conexao) {
        return ['error' => 'Falha ao conectar ao servidor de e-mail: ' . imap_last_error()];
    }

    // Buscar apenas não lidos
    $emails_ids = imap_search($conexao, 'UNSEEN');

    if (!$emails_ids) {
        imap_close($conexao);
        return ['emails' => [], 'count' => 0];
    }

    $resultado = [];

    foreach ($emails_ids as $id) {
        $header = imap_headerinfo($conexao, $id);
        $struct = imap_fetchstructure($conexao, $id);

        // Extrair dados de cabeçalho
        $assunto = $header->subject ?? '(Sem assunto)';
        $assunto = decodificarCabecalho($assunto);

        $de = '';
        $deNome = '';
        if (!empty($header->from)) {
            $from = $header->from[0];
            $de = $from->mailbox . '@' . $from->host;
            $deNome = decodificarCabecalho($from->personal ?? '');
        }

        // Message-ID para evitar duplicatas
        $messageId = $header->message_id ?? uniqid('email_', true);
        $messageId = trim($messageId, '<>');

        // Extrair corpo do e-mail
        $corpo = extrairCorpo($conexao, $id, $struct);

        // Marcar como lido
        imap_setflag_full($conexao, (string) $id, '\\Seen');

        $resultado[] = [
            'message_id' => $messageId,
            'subject' => $assunto,
            'from' => $de,
            'from_name' => $deNome,
            'body' => limparCorpo($corpo),
            'date' => date('Y-m-d H:i:s', strtotime($header->date ?? 'now')),
        ];
    }

    imap_close($conexao);

    return ['emails' => $resultado, 'count' => count($resultado)];
}

/**
 * Extrai o corpo de texto do e-mail (preferência plain text, fallback HTML)
 */
function extrairCorpo($conexao, $id, $struct)
{
    // E-mail simples (não multipart)
    if (!isset($struct->parts)) {
        $corpo = imap_body($conexao, $id);
        return decodificarParte($corpo, $struct->encoding ?? 0);
    }

    // Multipart: procurar text/plain primeiro
    foreach ($struct->parts as $i => $part) {
        if (strtolower($part->subtype ?? '') === 'plain') {
            $corpo = imap_fetchbody($conexao, $id, (string) ($i + 1));
            return decodificarParte($corpo, $part->encoding ?? 0);
        }
    }

    // Fallback: HTML → texto
    foreach ($struct->parts as $i => $part) {
        if (strtolower($part->subtype ?? '') === 'html') {
            $corpo = imap_fetchbody($conexao, $id, (string) ($i + 1));
            $corpo = decodificarParte($corpo, $part->encoding ?? 0);
            return html_entity_decode(strip_tags($corpo), ENT_QUOTES, 'UTF-8');
        }
    }

    return imap_body($conexao, $id);
}

/**
 * Decodifica o encoding da parte (quoted-printable, base64, etc.)
 */
function decodificarParte($corpo, $encoding)
{
    switch ($encoding) {
        case 4:
            return quoted_printable_decode($corpo);
        case 3:
            return base64_decode($corpo);
        default:
            return $corpo;
    }
}

/**
 * Decodifica cabeçalhos com charset (=?UTF-8?...?=)
 */
function decodificarCabecalho($str)
{
    if (empty($str))
        return '';
    $decoded = imap_mime_header_decode($str);
    $result = '';
    foreach ($decoded as $part) {
        $charset = strtolower($part->charset ?? 'utf-8');
        $text = $part->text ?? '';
        if ($charset !== 'default' && $charset !== 'utf-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $charset);
        }
        $result .= $text;
    }
    return $result;
}

/**
 * Limpa o corpo do e-mail para salvar como descrição do card
 */
function limparCorpo($texto)
{
    // Remove assinaturas comuns
    $texto = preg_replace('/--\s*\r?\n.*/s', '', $texto);
    // Remove linhas de reply (">")
    $texto = preg_replace('/^>.*$/m', '', $texto);
    // Normaliza espaços
    $texto = preg_replace('/\r\n/', "\n", $texto);
    $texto = preg_replace('/\n{3,}/', "\n\n", $texto);
    return trim($texto);
}
