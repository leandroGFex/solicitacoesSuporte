<?php
// =============================================================
// API - DOWNLOAD DE ETIQUETAS E DECLARAÇÕES (CORREIOS)
// =============================================================
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/correios.php';

if (empty($_SESSION['logged_in'])) {
    http_response_code(403);
    die("Não autorizado");
}

$cardId = (int) ($_GET['card_id'] ?? 0);
$type = $_GET['type'] ?? 'etiqueta'; // etiqueta ou declaracao

if (!$cardId) {
    die("Card ID não informado");
}

$db = getDB();
$stmt = $db->prepare("SELECT correios_prepost_id, company_name FROM cards WHERE id = ?");
$stmt->execute([$cardId]);
$card = $stmt->fetch();

if (!$card || empty($card['correios_prepost_id'])) {
    die("Card não possui pré-postagem gerada ou não encontrado.");
}

$pdf = null;
$filename = "";

if ($type === 'etiqueta') {
    $pdf = CorreiosAPI::getPdfEtiqueta($card['correios_prepost_id']);
    $filename = "Etiqueta_" . ($card['company_name'] ?: $cardId) . ".pdf";
} else {
    $pdf = CorreiosAPI::getPdfDeclaracao($card['correios_prepost_id']);
    $filename = "Declaracao_" . ($card['company_name'] ?: $cardId) . ".pdf";
}

if ($pdf) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    echo $pdf;
} else {
    http_response_code(404);
    die("Não foi possível gerar o PDF. Verifique se a pré-postagem ainda é válida.");
}
