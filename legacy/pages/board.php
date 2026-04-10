<?php
// Board Page - Kanban Principal
require_once __DIR__ . '/../config/config.php';
include __DIR__ . '/../layout/header.php';
?>

<div class="board-wrap" id="boardWrap">
    <!-- Colunas carregadas via JS -->
    <div class="add-column-btn" onclick="openNewColumnModal()" id="addColBtn" <?php if ($_SESSION['user_role'] !== 'admin')
        echo 'style="display:none"'; ?>>
        <span class="material-icons-round">add_circle_outline</span>
        Nova Coluna
    </div>
    <div class="add-column-btn" onclick="openArchiveModal()" id="viewArchiveBtn"
        style="background: var(--bg-card); color: var(--text-muted); border-color: var(--border);">
        <span class="material-icons-round">inventory_2</span>
        Ver Arquivados
    </div>
    <div class="add-column-btn" onclick="openBulkArchiveModal()" id="bulkArchiveBtn"
        style="background: #fff3e0; color: #e65100; border-color: #ffb74d;">
        <span class="material-icons-round">archive</span>
        Arquivar em Massa
    </div>

    <!-- Toast container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- ==================== MODAL: CARD ========================= -->
    <div class="modal-overlay" id="cardModal">
        <div class="modal" style="max-width:720px">
            <div class="modal-header">
                <h2 id="cardModalTitle">Nova Solicitação</h2>
                <button class="modal-close" onclick="closeCardModal()"><span
                        class="material-icons-round">close</span></button>
            </div>
            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="tab-btn active" data-tab="dados"><span class="material-icons-round">info</span>
                        Dados</button>
                    <button class="tab-btn" data-tab="rastreio"><span class="material-icons-round">radar</span>
                        Rastreio</button>
                    <button class="tab-btn" data-tab="comentarios"><span class="material-icons-round">comment</span>
                        Comentários</button>
                    <button class="tab-btn" data-tab="historico" onclick="loadCardHistory()"><span
                            class="material-icons-round">history</span>
                        Histórico</button>
                </div>

                <!-- TAB: DADOS -->
                <div class="tab-content active" id="tab-dados">
                    <div class="form-group">
                        <label>Título *</label>
                        <input type="text" class="form-control" id="cardTitle" placeholder="Ex: Entrega Empresa XPTO"
                            required>
                    </div>
                    <div class="form-group">
                        <label>Observação</label>
                        <textarea class="form-control" id="cardDescription"
                            placeholder="Observações da solicitação..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="display:none;">
                            <label>Categoria *</label>
                            <select class="form-control" id="cardCategory" onchange="onCategoryChange()">
                                <option value="cartao">💳 Cartão</option>
                                <option value="tag">🏷️ Tag</option>
                                <option value="pos">📟 POS</option>
                                <option value="rastreador">📡 Rastreador</option>
                            </select>
                        </div>
                        <div class="form-group" id="posRequestTypeGroup">
                            <label>Modo de Solicitação *</label>
                            <select class="form-control" id="posRequestType" onchange="onPosRequestTypeChange()">
                                <option value="">Envio Padrão</option>
                                <option value="Retirada">Retirada</option>
                                <option value="Reverso">Reverso</option>
                                <option value="Retirada Presencial">Retirada Presencial</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nº Remessa</label>
                            <input type="text" class="form-control" id="cardRemessa" placeholder="Auto" readonly
                                style="background:var(--bg-card); cursor:not-allowed;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Nome da Empresa / Cliente</label>
                        <input type="text" class="form-control" id="cardCompany">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>E-mail do Cliente</label>
                            <input type="email" class="form-control" id="cardEmail">
                        </div>
                        <div class="form-group">
                            <label>CNPJ *</label>
                            <input type="text" class="form-control" id="cardCnpj" placeholder="00.000.000/0000-00" required>
                        </div>
                    </div>
                    <div id="addressFieldsGroup">
                        <div class="form-row">
                            <div class="form-group" style="flex: 0 0 120px;">
                                <label>CEP *</label>
                                <input type="text" class="form-control" id="cardCep" placeholder="00000-000" maxlength="9"
                                    oninput="maskCep(this)" onblur="buscarCep(this.value)" required>

                            </div>
                            <div class="form-group">
                                <label>Endereço / Logradouro *</label>
                                <input type="text" class="form-control" id="cardAddress" required>
                            </div>
                            <div class="form-group" style="flex: 0 0 100px;">
                                <label>Número *</label>
                                <input type="text" class="form-control" id="cardAddressNumber" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Bairro *</label>
                                <input type="text" class="form-control" id="cardNeighborhood" required>
                            </div>
                            <div class="form-group">
                                <label>Complemento</label>
                                <input type="text" class="form-control" id="cardComplement" placeholder="Apto, Sala, etc">
                            </div>
                            <div class="form-group">
                                <label>Cidade/UF *</label>
                                <input type="text" class="form-control" id="cardCityState" placeholder="Cidade - UF" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="posReasonGroup" style="display:none;">
                        <label>Motivo da <span id="posReasonLabelSpan">Retirada/Reverso</span> *</label>
                        <textarea class="form-control" id="posReason" placeholder="Observações do motivo..."
                            rows="2"></textarea>
                    </div>

                    <div class="form-group" id="declarationGroup" style="display:none;">
                        <label>Declaração de Retirada <span id="declarationStatus" style="font-weight:normal; font-size:0.8rem; color:var(--primary)"></span></label>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <input type="file" class="form-control" id="cardDeclaration" accept=".pdf,image/*,.doc,.docx,.txt">
                            <div id="declarationPreview" style="display:none;">
                                <a href="javascript:void(0)" id="declarationLink" class="btn btn-outline btn-sm" onclick="handleDeclarationClick(this)">
                                    <span class="material-icons-round">visibility</span> Ver atual
                                </a>
                            </div>
                        </div>
                        <p style="font-size:0.75rem; color:#666; margin-top:4px;">Formatos aceitos: PDF, Imagens, Documentos.</p>
                    </div>

                    <!-- ===== BLOCO DINÂMICO POR CATEGORIA ===== -->
                    <div id="extraFieldsBlock">
                        <!-- Renderizado pelo JS baseado na categoria -->
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Prazo de Entrega</label>
                            <input type="date" class="form-control" id="cardDeadline">
                            <div id="deadlineAlert" class="mt-1"></div>
                        </div>
                        <div class="form-group">
                            <label>Prioridade</label>
                            <select class="form-control" id="cardPriority">
                                <option value="baixa">🟢 Baixa</option>
                                <option value="media" selected>🟡 Média</option>
                                <option value="alta">🔴 Alta</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Coluna</label>
                            <select class="form-control" id="cardColumn"></select>
                        </div>
                        <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 8px;">
                            <label
                                style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; border: 1px solid #4CAF50; padding: 8px 12px; border-radius: 8px; background: rgba(76, 175, 80, 0.05);">
                                <input type="checkbox" id="cardIsCompleted"
                                    style="width: 18px; height: 18px; accent-color: #4CAF50;">
                        </div>
                    </div>
                    <!-- Ações Integradas Correios -->
                    <div id="correiosActions" style="margin-top:20px; border-top:1px solid #eee; padding-top:15px; display:none;"></div>
                </div>

                <!-- TAB: RASTREIO -->
                <div class="tab-content" id="tab-rastreio">
                    <div class="section-label"><span class="material-icons-round">local_shipping</span> Código de
                        Rastreio
                        (Correios)</div>
                    <div class="tracking-input-wrap">
                        <input type="text" class="form-control" id="cardTracking"
                            placeholder="Rastreio Principal (Ex: AA123456789BR)" style="text-transform:uppercase">
                        <button class="btn btn-teal" onclick="checkTracking()" id="btnTrack">
                            <span class="material-icons-round">search</span> Rastrear
                        </button>
                    </div>
                    <div id="trackingResult" class="tracking-events"></div>
                    <div id="currentTrackingStatus" class="mt-2"></div>

                    <div class="section-label" id="reverseTrackingLabel" style="margin-top:24px; display:none;"><span
                            class="material-icons-round">assignment_return</span> Código Reverso
                        (Registro Opcional)</div>
                    <div class="tracking-input-wrap" id="reverseTrackingGroup" style="display:none;">
                        <input type="text" class="form-control" id="reverseTrackingCode"
                            placeholder="Código Reverso (Ex: 123456789)" style="text-transform:uppercase">
                    </div>
                </div>

                <!-- TAB: COMENTÁRIOS -->
                <div class="tab-content" id="tab-comentarios">
                    <div class="section-label"><span class="material-icons-round">forum</span> Comentários</div>
                    <div class="comment-list" id="commentList"></div>
                    <div class="comment-new">
                        <textarea class="form-control" id="newCommentText" placeholder="Adicionar comentário..."
                            rows="2"></textarea>
                        <button class="btn btn-teal btn-sm" onclick="saveComment()"
                            style="align-self:flex-end;white-space:nowrap">
                            <span class="material-icons-round">send</span>
                        </button>
                    </div>
                </div>

                <!-- TAB: HISTÓRICO -->
                <div class="tab-content" id="tab-historico">
                    <div class="section-label"><span class="material-icons-round">history</span> Histórico do Card</div>
                    <div class="comment-list" id="historyList" style="max-height: 250px; overflow-y: auto;">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger btn-sm" id="btnDeleteCard" onclick="deleteCard()"
                    style="margin-right:auto;display:none">
                    <span class="material-icons-round">delete</span> Excluir
                </button>
                <button class="btn btn-warning btn-sm" id="btnArchiveCard" onclick="archiveCurrentCard()"
                    style="margin-right:auto;display:none;background:#ff9800;color:white;border-color:#ff9800;">
                    <span class="material-icons-round" style="font-size:16px;">archive</span> Arquivar
                </button>
                <button class="btn btn-outline" onclick="closeCardModal()">Cancelar</button>
                <button class="btn btn-primary-main" onclick="saveCard()">
                    <span class="material-icons-round">save</span> Salvar
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: COLUNA ======================= -->
    <div class="modal-overlay" id="columnModal">
        <div class="modal" style="max-width:420px">
            <div class="modal-header">
                <h2 id="columnModalTitle">Nova Coluna</h2>
                <button class="modal-close" onclick="closeColumnModal()"><span
                        class="material-icons-round">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nome da Coluna *</label>
                    <input type="text" class="form-control" id="colName" placeholder="Ex: Em Revisão">
                </div>
                <div class="form-group">
                    <label>Cor</label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap" id="colorPicker">
                        <?php
                        $colors = ['#00897B', '#004D40', '#1565C0', '#6A1B9A', '#E64A19', '#2E7D32', '#F57F17', '#37474F', '#AD1457'];
                        foreach ($colors as $c):
                            ?>
                            <div class="color-swatch" data-color="<?= $c ?>"
                                style="width:30px;height:30px;background:<?= $c ?>;border-radius:50%;cursor:pointer;transition:transform .15s;border:3px solid transparent"
                                onclick="selectColor('<?= $c ?>', this)"></div>
                        <?php endforeach; ?>
                        <input type="color" id="colColorCustom" onchange="selectColor(this.value, null)"
                            title="Cor personalizada"
                            style="width:30px;height:30px;border:none;border-radius:50%;cursor:pointer;padding:0">
                    </div>
                    <input type="hidden" id="colColor" value="#00897B">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger btn-sm" id="btnDeleteCol" onclick="deleteColumn()"
                    style="margin-right:auto;display:none">
                    <span class="material-icons-round">delete</span> Excluir
                </button>
                <button class="btn btn-outline" onclick="closeColumnModal()">Cancelar</button>
                <button class="btn btn-primary-main" onclick="saveColumn()">
                    <span class="material-icons-round">save</span> Salvar
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: SENHA ======================== -->
    <div class="modal-overlay" id="passwordModal">
        <div class="modal" style="max-width:380px">
            <div class="modal-header">
                <h2>Alterar Senha</h2>
                <button class="modal-close" onclick="closeModal('passwordModal')"><span
                        class="material-icons-round">close</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nova Senha</label>
                    <input type="password" class="form-control" id="newPassword" placeholder="Mínimo 6 caracteres">
                </div>
                <div class="form-group">
                    <label>Confirmar Senha</label>
                    <input type="password" class="form-control" id="confirmPassword">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('passwordModal')">Cancelar</button>
                <button class="btn btn-teal" onclick="savePassword()"><span class="material-icons-round">lock</span>
                    Alterar</button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: ARQUIVADOS ======================== -->
    <div class="modal-overlay" id="archiveModal">
        <div class="modal"
            style="max-width:1100px; width: 95%; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header">
                <h2><span class="material-icons-round"
                        style="vertical-align: middle; margin-right: 8px;">inventory_2</span> Solicitações Arquivadas
                    (Concluídas)</h2>
                <button class="modal-close" onclick="closeArchiveModal()"><span
                        class="material-icons-round">close</span></button>
            </div>
            <div class="modal-body" style="flex: 1; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table-dados" id="archiveTable" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Empresa / Cliente</th>
                                <th>Placa / ID</th>
                                <th>Rastreio</th>
                                <th>Status Correios</th>
                                <th>Data Conclusão</th>
                                <th style="width: 80px; text-align: center;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populado via JS DataTables -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeArchiveModal()">Fechar</button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: ARQUIVAR EM MASSA ======================== -->
    <div class="modal-overlay" id="bulkArchiveModal">
        <div class="modal" style="max-width:480px">
            <div class="modal-header">
                <h2><span class="material-icons-round" style="vertical-align: middle; margin-right: 8px;">archive</span> Arquivar em Massa</h2>
                <button class="modal-close" onclick="closeBulkArchiveModal()"><span class="material-icons-round">close</span></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #666; font-size: 0.9rem;">
                    Selecione os filtros abaixo para arquivar solicitações em massa. Os cards movidos para o arquivo não aparecerão mais no painel principal.
                </p>
                
                <div class="form-group">
                    <label>Categoria</label>
                    <select class="form-control" id="bulkCategory">
                        <option value="todas">📁 Todas as categorias</option>
                        <option value="cartao">💳 Cartão</option>
                        <option value="tag">🏷️ Tag</option>
                        <option value="pos">📟 POS</option>
                        <option value="rastreador">📡 Rastreador</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Período de Criação (Mês/Ano)</label>
                    <input type="month" class="form-control" id="bulkPeriod">
                    <p style="font-size: 0.75rem; color: #888; margin-top: 4px;">Deixe vazio para arquivar todos de acordo com a categoria.</p>
                </div>

                <div style="background: #fff8e1; padding: 12px; border-radius: 8px; border: 1px solid #ffe082; margin-top: 20px;">
                    <div style="display:flex; gap:10px; color: #856404; font-size: 0.85rem;">
                        <span class="material-icons-round" style="font-size: 18px;">warning</span>
                        <span>Essa ação irá arquivar apenas os cards que <strong>não</strong> estão arquivados no momento.</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeBulkArchiveModal()">Cancelar</button>
                <button class="btn btn-warning" onclick="confirmBulkArchive()" id="btnConfirmBulk">
                    <span class="material-icons-round">archive</span> Arquivar Selecionados
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: AUTOMAÇÕES ======================== -->
    <div class="modal-overlay" id="automationsModal">
        <div class="modal" style="max-width:800px; width: 95%;">
            <div class="modal-header">
                <h2><span class="material-icons-round" style="vertical-align: middle; margin-right: 8px;">bolt</span> Automações (Regras)</h2>
                <button class="modal-close" onclick="closeAutomationsModal()"><span class="material-icons-round">close</span></button>
            </div>
            <div class="modal-body">
                <div id="automationsViewList">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px">
                        <p style="color:#666; font-size:0.9rem">Configure gatilhos e ações automáticas para esta categoria.</p>
                        <button class="btn btn-primary-main btn-sm" onclick="openNewRuleForm()">
                            <span class="material-icons-round">add</span> Nova Regra
                        </button>
                    </div>
                    <div id="rulesListContainer">
                        <!-- Carregado via JS -->
                    </div>
                </div>

                <div id="automationRuleForm" style="display:none">
                    <div class="form-group">
                        <label>Nome da Regra *</label>
                        <input type="text" class="form-control" id="ruleName" placeholder="Ex: Definir prazo automático">
                    </div>
                    
                    <div class="section-label"><span class="material-icons-round">flash_on</span> Gatilho (Trigger)</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Evento</label>
                            <select class="form-control" id="ruleTriggerEvent" onchange="onTriggerEventChange()">
                                <option value="move_to_column">Mover para coluna</option>
                                <option value="card_created">Card criado</option>
                                <option value="field_updated">Campo atualizado</option>
                            </select>
                        </div>
                        <div class="form-group" id="triggerConfigGroup">
                            <!-- Injetado dinamicamente -->
                        </div>
                    </div>

                    <div class="section-label"><span class="material-icons-round">play_arrow</span> Ações (Actions)</div>
                    <div id="ruleActionsContainer">
                        <!-- Injetado dinamicamente -->
                    </div>
                    <button class="add-row-btn" onclick="addRuleAction()" style="margin-top:10px">
                        <span class="material-icons-round">add</span> Adicionar Ação
                    </button>
                    
                    <div style="margin-top:30px; display:flex; gap:10px; justify-content:flex-end">
                        <button class="btn btn-outline" onclick="showRulesList()">Cancelar</button>
                        <button class="btn btn-primary-main" onclick="saveAutomationRule()">Salvar Regra</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Dynamic extra-fields block */
        .extra-field-row {
            display: grid;
            gap: 10px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 10px 12px;
            margin-bottom: 8px;
            position: relative;
            align-items: end;
        }

        .extra-field-row.cols-2 {
            grid-template-columns: 1fr 1fr;
        }

        .extra-field-row.cols-3 {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .extra-field-row.cols-1 {
            grid-template-columns: 1fr;
        }

        .remove-row-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #ffebee;
            border: none;
            border-radius: 6px;
            color: #c62828;
            width: 26px;
            height: 26px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: background .15s;
        }

        /* Overlay para Declaração */
        .declaration-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .declaration-overlay.active { opacity: 1; pointer-events: all; }
        .overlay-content {
            background: #fff; width: 90%; height: 90%;
            border-radius: 12px; display: flex; flex-direction: column; overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .overlay-header {
            padding: 15px 20px; border-bottom: 1px solid #eee;
            display: flex; justify-content: space-between; align-items: center; background: #f8f9fa;
        }
        .overlay-header h3 { margin: 0; font-size: 1.1rem; color: #333; }
        .overlay-actions { display: flex; gap: 10px; }
        .overlay-body { flex: 1; background: #555; display: flex; align-items: center; justify-content: center; overflow: auto; }
        .overlay-body img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .overlay-body iframe { width: 100%; height: 100%; border: none; }
        .btn-icon {
            background: none; border: none; cursor: pointer; color: #666;
            display: flex; align-items: center; padding: 5px; border-radius: 50%; transition: background 0.2s;
        }
        .btn-icon:hover { background: #eee; color: var(--primary); }

        .remove-row-btn:hover {
            background: #c62828;
            color: #fff;
        }

        .add-row-btn {
            width: 100%;
            padding: 8px;
            background: transparent;
            border: 2px dashed var(--border);
            border-radius: var(--radius-sm);
            color: var(--primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: .82rem;
            font-family: 'Inter', sans-serif;
            transition: var(--transition);
            margin-top: 4px;
        }

        .add-row-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary);
        }

        .deadline-auto-hint {
            font-size: .72rem;
            color: var(--primary-dark);
            background: var(--primary-light);
            border-radius: 4px;
            padding: 3px 8px;
            margin-top: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* DataTables Custom Theme */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 6px 10px;
            outline: none;
            margin-left: 8px;
            font-family: inherit;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary);
        }

        .dataTables_wrapper .dataTables_length select {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 4px;
            outline: none;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: var(--radius-sm) !important;
            border: 1px solid transparent !important;
            box-shadow: none !important;
            padding: 4px 10px !important;
            margin-left: 2px !important;
            color: var(--text-color) !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--primary) !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--bg-body) !important;
            border-color: var(--border) !important;
        }

        table.dataTable.no-footer {
            border-bottom: 1px solid var(--border) !important;
        }

        .table-dados th {
            font-weight: 600;
            color: var(--text-color);
            background: #f8fafc;
            border-bottom: 2px solid var(--border);
            padding: 12px 16px;
        }

        .table-dados td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 0.9rem;
        }
    </style>

    <script>
        // Passar dados da sessão para JS
        const SESSION = {
            userId: <?= $_SESSION['user_id'] ?>,
            userName: <?= json_encode($_SESSION['user_name']) ?>,
            userRole: <?= json_encode($_SESSION['user_role']) ?>
        };
    </script>

    <!-- Modal Arquivados -->
    <?php include 'modals/archived_cards.php'; ?>

    <!-- Overlay de Visualização de Declaração -->
    <div id="declarationOverlay" class="declaration-overlay">
        <div class="overlay-content">
            <div class="overlay-header">
                <h3 id="overlayTitle">Visualização da Declaração</h3>
                <div class="overlay-actions">
                    <a href="#" id="overlayDownload" download class="btn-icon" title="Baixar">
                        <span class="material-icons-round">download</span>
                    </a>
                    <a href="#" id="overlayExternal" target="_blank" class="btn-icon" title="Abrir em nova aba">
                        <span class="material-icons-round">open_in_new</span>
                    </a>
                    <button onclick="closeDeclarationOverlay()" class="btn-icon" title="Fechar">
                        <span class="material-icons-round">close</span>
                    </button>
                </div>
            </div>
            <div id="overlayBody" class="overlay-body">
                <!-- Conteúdo injetado via JS -->
            </div>
        </div>
    </div>
    <!-- Adicionando jQuery e DataTables para a Tabela de Arquivados -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <script src="assets/js/automations.js?v=<?= time() ?>"></script>
    <script src="assets/js/board.js?v=<?= time() ?>"></script>
    <?php include __DIR__ . '/../layout/footer.php'; ?>