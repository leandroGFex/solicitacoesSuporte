<?php
// e:\ARQUIVOS\PROJETOS\SITES\FLEX\SUPORTE FLEX FERRAMENTAS\inventory\print_purchase.php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Acesso negado.");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Pedido de Compra - Estoque Flex</title>
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

        .footer-sig {
            margin-top: 50px;
            display: flex;
            justify-content: space-around;
        }

        .sig-line {
            width: 250px;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 5px;
            font-size: 14px;
        }

        @media print {
            @page {
                margin: 1cm;
                size: A4 portrait;
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
            width: 200px;
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

    <button class="btn-print no-print" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>

    <div class="header">
        <div class="logo">SUPORTE FLEX</div>
        <div class="info">
            Data Emissão:
            <?= date('d/m/Y H:i') ?><br>
            Solicitante:
            <?= htmlspecialchars($_SESSION['user_name']) ?>
        </div>
    </div>

    <h2>Solicitação Interna de Compras (Reposição de Estoque)</h2>

    <table>
        <thead>
            <tr>
                <th style="width: 50px; text-align:center;">ID</th>
                <th>Item / Descrição</th>
                <th>Categoria</th>
                <th style="width: 120px; text-align:center;">Qtd. Solicitada</th>
                <th style="width: 150px;">Observação Financeira</th>
            </tr>
        </thead>
        <tbody id="printBody">
            <!-- Dados preenchidos via JS -->
        </tbody>
    </table>

    <div class="footer-sig">
        <div class="sig-line">Assinatura Solicitante</div>
        <div class="sig-line">Aprovação Diretoria / Financeiro</div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dataStr = sessionStorage.getItem('print_purchase_data');
            if (dataStr) {
                try {
                    const items = JSON.parse(dataStr);
                    const tbody = document.getElementById('printBody');
                    tbody.innerHTML = items.map(i => `
                        <tr>
                            <td style="text-align:center;">${i.id}</td>
                            <td><strong>${i.name}</strong></td>
                            <td>${i.cat || '-'}</td>
                            <td style="text-align:center; font-weight:bold; font-size:16px;">${i.qty}</td>
                            <td>${i.obs || ''}</td>
                        </tr>
                    `).join('');
                } catch (e) { console.error('Error parsing print data'); }
            } else {
                document.getElementById('printBody').innerHTML = '<tr><td colspan="5" style="text-align:center;">Nenhum item selecionado.</td></tr>';
            }
        });
    </script>
</body>

</html>