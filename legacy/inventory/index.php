<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Inventory Page
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: ../index.php?page=login');
    exit;
}

// Shared header vars
$toolTitle = 'Estoque Geral';
$toolIcon = 'inventory_2';
$toolPath = '..';
$toolReportsUrl = '';

require_once __DIR__ . '/../layout/header_tool.php';

// NATIVE FETCH: Fallback so we don't depend on JS for pure read-only tables.
$db = getDB();

// Ensure the new description column exists on legacy databases
try {
    $db->exec("ALTER TABLE `inventory_history` ADD COLUMN `description` TEXT NULL AFTER `modified_by`");
} catch (Exception $e) {
    // Ignore if column already exists
}

// Fetch Low Stock
$stmt = $db->query("SELECT id, name, category, quantity, min_quantity 
                    FROM inventory_items 
                    WHERE deleted_at IS NULL AND quantity <= min_quantity 
                    ORDER BY category ASC, name ASC");
$lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Moves (Entradas e Saídas)
$stmt = $db->query("
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
    ORDER BY m.created_at DESC 
    LIMIT 300
");
$movesReport = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="background:var(--bg); min-height:calc(100vh - 56px);">

    <div class="tool-page-header">
        <div>
            <h2>
                <span class="material-icons-round">inventory_2</span>
                Estoque Geral
            </h2>
            <p style="font-size:.9rem; color:var(--text-muted); margin:4px 0 0 34px;">Controle de materiais diversos,
                suprimentos e insumos.</p>
        </div>
        <div style="display:flex; gap:12px; align-items:center;">
            <span id="lowStockBadge"
                style="display:none; background:#FFE0B2; color:#E65100; font-weight:600; padding:6px 12px; border-radius:20px; font-size:0.85rem; align-items:center; gap:4px;">
                <span class="material-icons-round" style="font-size:16px;">warning</span> <span>0 itens em alerta</span>
            </span>
            <button class="btn btn-outline" onclick="initInventoryImport()">
                <span class="material-icons-round">upload_file</span> Importar de Excel
            </button>
            <button class="btn btn-yellow" onclick="openModal()">
                <span class="material-icons-round">add</span> Cadastrar Item
            </button>
        </div>
    </div>

    <style>
        .tab-btn {
            background: none;
            border: none;
            padding: 8px 16px;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: .95rem;
            transition: all 0.2s;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
        }
    </style>

    <div class="container" style="max-width:1200px; margin:0 auto; padding:0 24px;">

        <div
            style="display:flex; gap:16px; margin-bottom:24px; border-bottom:1px solid var(--border); padding-bottom:8px;">
            <button class="tab-btn active" onclick="switchTab('estoque', this)">
                Itens e Estoque
            </button>
            <button class="tab-btn" onclick="switchTab('reports', this)">
                Relatórios e Compras
            </button>
        </div>

        <!-- TAB ESTOQUE -->
        <div id="tabEstoque">
            <div
                style="background:var(--surface); border-radius:var(--radius-md); border:1px solid var(--border); box-shadow:var(--shadow-sm); overflow:hidden;">
                <div
                    style="padding:14px 20px; border-bottom:1px solid var(--border); display:flex; gap:12px; align-items:center;">
                    <input type="text" id="searchInput" placeholder="Buscar item, categoria..."
                        style="border:1.5px solid var(--border); border-radius:var(--radius-md); padding:8px 14px; font-size:.88rem; font-family:'Inter',sans-serif; outline:none; flex:1;"
                        onkeyup="filterTable()">
                    <select id="filterCategory" onchange="filterTable()"
                        style="border:1.5px solid var(--border); border-radius:var(--radius-md); padding:8px 14px; font-size:.88rem; outline:none;">
                        <option value="">Todas as Categorias</option>
                    </select>
                </div>

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th
                                    style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border);">
                                    Item</th>
                                <th
                                    style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border);">
                                    Descrição</th>
                                <th
                                    style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border);">
                                    Qtd. Atual</th>
                                <th
                                    style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border);">
                                    Alerta Mínimo</th>
                                <th
                                    style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border);">
                                    Ações Rápidas</th>
                                <th
                                    style="padding:10px 14px; text-align:left; font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid var(--border); width: 100px;">
                                    Editar</th>
                            </tr>
                        </thead>
                        <tbody id="invTableBody">
                            <tr>
                                <td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">
                                    Carregando
                                    itens...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <!-- TAB REPORTS / COMPRAS -->
        <div id="tabReports" style="display:none;">

            <!-- Bloco 1: Pedido de Compra -->
            <div
                style="background:var(--surface); border-radius:var(--radius-md); border:1px solid #FFCC80; box-shadow:var(--shadow-sm); overflow:hidden; margin-bottom:24px;">
                <div
                    style="padding:14px 20px; border-bottom:1px solid #FFE0B2; background:#FFF8E1; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3
                            style="margin:0; font-size:1.1rem; color:#E65100; display:flex; align-items:center; gap:6px;">
                            <span class="material-icons-round">shopping_cart</span> Alerta de Reposição & Compras
                        </h3>
                        <p style="margin:4px 0 0 0; font-size:0.85rem; color:#F57C00;">Itens que precisam ser comprados
                            (abaixo do Estoque Mínimo).</p>
                    </div>
                    <button class="btn" id="btnGerarPedido" onclick="printPurchaseFromPHP()"
                        style="background:var(--primary); color:#fff; border:none; display:<?= empty($lowStockItems) ? 'none' : 'inline-flex' ?>; align-items:center; gap:6px;">
                        <span class="material-icons-round" style="font-size:18px;">picture_as_pdf</span> Gerar Pedido
                        PDF
                    </button>
                </div>
                <div style="padding:0;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#fff;">
                                <th style="padding:10px 14px; text-align:left; width:50px;">✔</th>
                                <th style="padding:10px 14px; text-align:left;">Item / Categoria</th>
                                <th style="padding:10px 14px; text-align:left;">Estoque Atual</th>
                                <th style="padding:10px 14px; text-align:left;">Qtd. Solicitar</th>
                                <th style="padding:10px 14px; text-align:left;">Obs. Financeira</th>
                            </tr>
                        </thead>
                        <tbody id="tblComprasPHP">
                            <?php if (empty($lowStockItems)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:20px; color:#666;">Nenhum item abaixo
                                        do estoque mínimo no momento.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lowStockItems as $i):
                                    $reqQty = max(1, $i['min_quantity'] - $i['quantity'] + 5);
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="chk-buy" value="<?= $i['id'] ?>"
                                                data-name="<?= htmlspecialchars($i['name'] ?? '') ?>"
                                                data-cat="<?= htmlspecialchars($i['category'] ?? '') ?>" checked></td>
                                        <td><strong><?= htmlspecialchars($i['name'] ?? '') ?></strong><br><small><?= htmlspecialchars($i['category'] ?? '') ?></small>
                                        </td>
                                        <td style="color:#D84315; font-weight:600;"><?= $i['quantity'] ?></td>
                                        <td><input type="number" class="form-control req-qty" id="req_qty_<?= $i['id'] ?>"
                                                value="<?= $reqQty ?>" style="width:70px; padding:4px;"></td>
                                        <td><input type="text" class="form-control req-obs" id="req_obs_<?= $i['id'] ?>"
                                                placeholder="Ex: Urgente, Compra Mensal..." style="width:140px; padding:4px;">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- Bloco 2: Saídas Históricas -->
            <div
                style="background:var(--surface); border-radius:var(--radius-md); border:1px solid var(--border); box-shadow:var(--shadow-sm); overflow:hidden;">
                <div
                    style="padding:14px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3
                            style="margin:0; font-size:1.1rem; color:var(--text); display:flex; align-items:center; gap:6px;">
                            <span class="material-icons-round">swap_horiz</span> Relatório de Movimentações
                        </h3>
                        <p style="margin:4px 0 0 0; font-size:0.85rem; color:var(--text-muted);">Comprovantes de
                            entrada e retirada de materiais.</p>
                    </div>

                    <div style="display:flex; gap:12px; align-items:center;">
                        <select id="filterMoves" class="form-control"
                            style="width:160px; padding:6px; font-size:0.85rem;" onchange="filterMovesTable()">
                            <option value="all">Todas as Movs.</option>
                            <option value="Entrada">Apenas Entradas</option>
                            <option value="Saída">Apenas Saídas</option>
                        </select>
                        <button id="btnImprimirMovs" class="btn btn-outline"
                            onclick="window.open('print_exits.php?type=all', '_blank')"
                            style="display:flex; align-items:center; gap:6px;">
                            <span class="material-icons-round" style="font-size:18px;">print</span> Imprimir Relatório
                        </button>
                    </div>
                </div>
                <div style="padding:0;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th style="padding:10px 14px; text-align:left;">Data/Hora</th>
                                <th style="padding:10px 14px; text-align:left;">Ação</th>
                                <th style="padding:10px 14px; text-align:left;">Item / Material</th>
                                <th style="padding:10px 14px; text-align:left;">Qtd.</th>
                                <th style="padding:10px 14px; text-align:left;">Usuário / Origem</th>
                                <th style="padding:10px 14px; text-align:left;">Motivo / Descrição</th>
                            </tr>
                        </thead>
                        <tbody id="tblSaidasPHP">
                            <?php if (empty($movesReport)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:20px; color:#666;">Nenhuma
                                        movimentação registrada ainda.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($movesReport as $x):
                                    $isEntrada = $x['action_type'] === 'Entrada';
                                    $color = $isEntrada ? '#2E7D32' : '#C62828';
                                    $sign = $isEntrada ? '+' : '-';
                                    $bg = $isEntrada ? '#E8F5E9' : '#FFEBEE';
                                    $actionIcon = $isEntrada ? 'archive' : 'outbound';
                                    ?>
                                    <tr class="move-row" data-type="<?= $x['action_type'] ?>">
                                        <td><?= date('d/m/Y H:i', strtotime($x['created_at'])) ?></td>
                                        <td><span class="move-badge"
                                                style="background:<?= $bg ?>; color:<?= $color ?>; padding:4px 8px; border-radius:12px; font-size:0.75rem; font-weight:600; display:inline-flex; align-items:center; gap:4px;"><span
                                                    class="material-icons-round"
                                                    style="font-size:14px;"><?= $actionIcon ?></span><?= $x['action_type'] ?></span>
                                        </td>
                                        <td><strong><?= htmlspecialchars($x['item_name'] ?? '') ?></strong><br><small><?= htmlspecialchars($x['category'] ?? '') ?></small>
                                        </td>
                                        <td style="color:<?= $color ?>; font-weight:600;"><?= $sign ?><?= $x['quantity'] ?></td>
                                        <td><?= htmlspecialchars($x['user_name'] ?? '') ?></td>
                                        <td style="font-size:0.8rem; color:#666;">
                                            <?= htmlspecialchars($x['description'] ?: '-') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                function filterMovesTable() {
                    const filter = document.getElementById('filterMoves').value;
                    const rows = document.querySelectorAll('.move-row');

                    rows.forEach(row => {
                        if (filter === 'all' || row.dataset.type === filter) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    // Update Print button URL
                    const btn = document.getElementById('btnImprimirMovs');
                    if (btn) {
                        btn.setAttribute('onclick', `window.open('print_exits.php?type=${filter}', '_blank')`);
                    }
                }

                function printPurchaseFromPHP() {
                    const chks = document.querySelectorAll('.chk-buy:checked');
                    if (chks.length === 0) {
                        alert('Selecione pelo menos um item para comprar.');
                        return;
                    }

                    const items = [];
                    chks.forEach(chk => {
                        const id = chk.value;
                        const name = chk.getAttribute('data-name');
                        const cat = chk.getAttribute('data-cat');
                        const qty = document.getElementById(`req_qty_${id}`).value;
                        const obsEl = document.getElementById(`req_obs_${id}`);
                        const obs = obsEl ? obsEl.value : '';
                        items.push({ id, name, cat, qty, obs });
                    });

                    sessionStorage.setItem('print_purchase_data', JSON.stringify(items));
                    window.open('print_purchase.php', '_blank');
                }
            </script>
        </div>

    </div>

    <!-- =====================================================
     MODAL CRIAR/EDITAR ITEM
===================================================== -->
    <div class="modal-overlay" id="invModal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modalTitle">Cadastrar Item</h2>
                <button class="modal-close" onclick="closeModal()"><span
                        class="material-icons-round">close</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="invId">
                <div class="form-group">
                    <label>Nome do Item *</label>
                    <input type="text" class="form-control" id="invName" placeholder="Ex: Cola Bastão, Rolo Bobina..."
                        required>
                </div>
                <div class="form-group">
                    <label>Categoria</label>
                    <input type="text" class="form-control" id="invCategory"
                        placeholder="Ex: Papelaria, Suprimentos...">
                </div>

                <div style="display:flex; gap:16px;">
                    <div class="form-group" style="flex:1;">
                        <label>Quantidade Atual *</label>
                        <input type="number" class="form-control" id="invQty" value="0" min="0" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Alerta: Estoque Mínimo *</label>
                        <input type="number" class="form-control" id="invMinQty" value="0" min="0" required>
                        <small style="color:var(--text-muted); font-size:11px;">O sistema avisará se o saldo for menor
                            ou
                            igual.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Descrição Opcional</label>
                    <textarea class="form-control" id="invDescription" rows="2"
                        placeholder="Marca, fornecedor, prateleira..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Cancelar</button>
                <button class="btn btn-primary" onclick="saveItem()">Salvar Item</button>
            </div>
        </div>
    </div>

    <!-- =====================================================
     MODAL REGISTRO DE SAÍDA (USO)
===================================================== -->
    <div class="modal-overlay" id="exitModal">
        <div class="modal" style="max-width:400px;">
            <div class="modal-header" style="border-bottom-color:#E3F2FD; background:#F0F8FF;">
                <h2 style="color:#1565C0; display:flex; align-items:center; gap:8px;"><span
                        class="material-icons-round">outbound</span> Retirar do Estoque</h2>
                <button class="modal-close" onclick="closeExitModal()"><span
                        class="material-icons-round">close</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="exitItemId">

                <div
                    style="background:#FFF3E0; padding:12px; border-radius:8px; margin-bottom:16px; border:1px solid #FFE0B2;">
                    <span style="font-size:0.8rem; color:#E65100; font-weight:700; text-transform:uppercase;">Item
                        selecionado:</span><br>
                    <strong id="exitItemName" style="font-size:1.1rem; color:#333;">Nome do Item</strong>
                </div>

                <div class="form-group">
                    <label>Qtd. Retirada * <span id="exitMaxQty" style="float:right; font-size:0.8rem; color:#666;">Max:
                            0</span></label>
                    <input type="number" class="form-control" id="exitQty" value="1" min="1" required
                        style="font-size:1.1rem; font-weight:bold; border-color:#2196F3;">
                </div>

                <div class="form-group">
                    <label>Usuário / Colaborador *</label>
                    <input type="text" class="form-control" id="exitUser" placeholder="Quem está pegando?" required>
                </div>

                <div class="form-group">
                    <label>Motivo ou Destino (Opcional)</label>
                    <input type="text" class="form-control" id="exitDesc" placeholder="Para que será usado?">
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeExitModal()">Cancelar</button>
                <button class="btn" style="background:#1565C0; color:white; border:none;"
                    onclick="registerExit()">Confirmar
                    Retirada</button>
            </div>
        </div>
    </div>

    <!-- =====================================================
     MODAL REGISTRO DE ENTRADA (COMPRA/REPOSIÇÃO)
===================================================== -->
    <div class="modal-overlay" id="entryModal">
        <div class="modal" style="max-width:400px;">
            <div class="modal-header" style="border-bottom-color:#E8F5E9; background:#F1F8E9;">
                <h2 style="color:#2E7D32; display:flex; align-items:center; gap:8px;"><span
                        class="material-icons-round">archive</span> Registrar Entrada</h2>
                <button class="modal-close" onclick="closeEntryModal()"><span
                        class="material-icons-round">close</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="entryItemId">

                <div
                    style="background:#F1F8E9; padding:12px; border-radius:8px; margin-bottom:16px; border:1px solid #C8E6C9;">
                    <span style="font-size:0.8rem; color:#2E7D32; font-weight:700; text-transform:uppercase;">Item
                        selecionado:</span><br>
                    <strong id="entryItemName" style="font-size:1.1rem; color:#333;">Nome do Item</strong>
                </div>

                <div class="form-group">
                    <label>Qtd. de Entrada * </label>
                    <input type="number" class="form-control" id="entryQty" value="1" min="1" required
                        style="font-size:1.1rem; font-weight:bold; border-color:#4CAF50;">
                </div>

                <div class="form-group">
                    <label>Fornecedor / Origem (Opcional)</label>
                    <input type="text" class="form-control" id="entrySupplier" placeholder="De onde veio? / NF">
                </div>

                <div class="form-group">
                    <label>Observações (Opcional)</label>
                    <input type="text" class="form-control" id="entryDesc" placeholder="Detalhes da compra...">
                </div>

            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeEntryModal()">Cancelar</button>
                <button class="btn" style="background:#2E7D32; color:white; border:none;"
                    onclick="registerEntry()">Confirmar
                    Entrada</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script src="<?= $toolPath ?>/assets/js/import_helper.js?v=<?= time() ?>"></script>
    <script>
    function initInventoryImport() {
        openImportModal({
            endpoint: 'api/import.php',
            fields: [
                { key: 'name', label: 'Nome do Item' },
                { key: 'category', label: 'Categoria' },
                { key: 'description', label: 'Descrição' },
                { key: 'quantity', label: 'Qtd. Atual' },
                { key: 'min_quantity', label: 'Alerta Mínimo' }
            ],
            onSuccess: () => {
                if (typeof loadItems === 'function') loadItems();
                else location.reload();
            }
        });
    }
    </script>
    <script src="assets/js/inventory.js?v=<?= time() ?>"></script>
</div>
</body>

</html>