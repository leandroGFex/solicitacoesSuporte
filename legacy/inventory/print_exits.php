<?php
// e:\ARQUIVOS\PROJETOS\SITES\FLEX\SUPORTE FLEX FERRAMENTAS\inventory\print_exits.php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Acesso negado.");
}
require_once '../config/config.php';
$db = getDB();

// Ensure the new description column exists on legacy databases
try {
    $db->exec("ALTER TABLE `inventory_history` ADD COLUMN `description` TEXT NULL AFTER `modified_by`");
} catch (Exception $e) {
    // Ignore if column already exists
}

$type = $_GET['type'] ?? 'all';
$title = "Movimentações Diárias (Entradas e Saídas)";

$sql = "
    SELECT m.id, m.quantity, m.user_name, m.description, m.created_at, m.action_type,
           i.name AS item_name, i.category 
    FROM (
        SELECT id, quantity_used AS quantity, user_name, description, created_at, 'Saída' AS action_type, item_id
        FROM inventory_exits
        
        UNION ALL
        
        SELECT id, quantity_change AS quantity, modified_by AS user_name, description, created_at, 'Entrada' AS action_type, item_id
        FROM inventory_history
        WHERE action = 'Entrada de Estoque'
    ) m
    JOIN inventory_items i ON m.item_id = i.id
";

if ($type === 'Entrada') {
    $sql .= " WHERE m.action_type = 'Entrada'";
    $title = "Entradas de Estoque";
} elseif ($type === 'Saída') {
    $sql .= " WHERE m.action_type = 'Saída'";
    $title = "Saídas de Estoque";
}

$sql .= " ORDER BY m.created_at DESC LIMIT 300";

$stmt = $db->query($sql);
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Movimentações - Estoque Flex</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #00A859;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #00A859;
        }

        .info {
            text-align: right;
            font-size: 14px;
            color: #666;
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            text-transform: uppercase;
            font-size: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #f4f4f4;
            color: #333;
        }

        @media print {
            @page {
                margin: 1cm;
                size: A4 landscape;
            }

            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }
        }

        .btn-print {
            display: block;
            width: 220px;
            margin: 0 auto 30px;
            padding: 12px;
            background: #00A859;
            color: white;
            border: none;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
        }
    </style>
</head>

<body>

    <button class="btn-print no-print" onclick="window.print()">🖨️ Imprimir Controle (PDF)</button>

    <div class="header">
        <div class="logo">SUPORTE FLEX</div>
        <div class="info">
            Relatório gerado em:
            <?= date('d/m/Y H:i') ?><br>
            Usuário:
            <?= htmlspecialchars($_SESSION['user_name']) ?>
        </div>
    </div>

    <h2>Controle Interno: <?= htmlspecialchars($title) ?></h2>

    <table>
        <thead>
            <tr>
                <th style="width: 140px;">Data e Hora</th>
                <th style="width: 80px; text-align:center;">Ação</th>
                <th>Item / Material</th>
                <th>Categoria</th>
                <th style="width: 80px; text-align:center;">Qtd.</th>
                <th>Origem / Solicitante</th>
                <th>Motivo / Descrição</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($exits) === 0): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">Nenhuma saída registrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($exits as $ex):
                    $isEntrada = $ex['action_type'] === 'Entrada';
                    $color = $isEntrada ? '#2E7D32' : '#C62828';
                    $sign = $isEntrada ? '+' : '-';
                    ?>
                    <tr>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($ex['created_at'])) ?>
                        </td>
                        <td style="text-align:center; font-weight:bold; color:<?= $color ?>;">
                            <?= $ex['action_type'] ?>
                        </td>
                        <td><strong>
                                <?= htmlspecialchars($ex['item_name']) ?>
                            </strong></td>
                        <td>
                            <?= htmlspecialchars($ex['category'] ?? '-') ?>
                        </td>
                        <td style="text-align:center; font-weight:bold; color:<?= $color ?>;"><?= $sign ?>
                            <?= $ex['quantity'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($ex['user_name']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($ex['description'] ?: '-') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>

</html>