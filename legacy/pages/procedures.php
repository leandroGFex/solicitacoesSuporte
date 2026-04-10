<?php
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: index.php?page=login');
    exit;
}

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
include __DIR__ . '/../layout/header.php';
?>

<!-- Material Icons & Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

<style>
    :root {
        --proc-primary: #2E7D32;
        --proc-primary-light: #E8F5E9;
        --proc-primary-dark: #1B5E20;
        --proc-accent: #43A047;
        --proc-danger: #C62828;
        --proc-text: #333;
        --proc-text-muted: #666;
        --proc-border: #e0e0e0;
        --proc-bg: #f8f9fa;
        --proc-white: #ffffff;
        --proc-radius: 12px;
        --proc-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .proc-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 24px 20px 80px;
    }

    /* Topbar - Matching Manuals */
    .proc-topbar {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 28px;
        flex-wrap: wrap;
    }

    .proc-topbar-left {
        flex: 1;
    }

    .proc-topbar-left h2 {
        font-size: 1.9rem;
        font-weight: 700;
        color: var(--proc-primary-dark);
        margin: 0 0 4px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .proc-topbar-left p {
        color: var(--proc-text-muted);
        margin: 0;
        font-size: .95rem;
    }

    /* Search Bar - Exactly like Manuals */
    .proc-search-wrap {
        position: relative;
        margin-bottom: 28px;
    }

    .proc-search-wrap .material-icons-round {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--proc-text-muted);
        pointer-events: none;
    }

    #procSearch {
        width: 100%;
        padding: 12px 16px 12px 48px;
        border: 2px solid var(--proc-border);
        border-radius: 10px;
        font-size: 1rem;
        background: var(--proc-white);
        color: var(--proc-text);
        transition: border-color .2s;
        box-sizing: border-box;
    }

    #procSearch:focus {
        outline: none;
        border-color: var(--proc-accent);
    }

    .proc-search-hint {
        font-size: .82rem;
        color: var(--proc-text-muted);
        margin-top: 6px;
    }

    /* Grid & Cards - Matching Manuals */
    .proc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .proc-card {
        background: var(--proc-white);
        border: 1px solid var(--proc-border);
        border-radius: var(--proc-radius);
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: transform .2s, box-shadow .2s;
        cursor: pointer;
        position: relative;
    }

    .proc-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .proc-card-top {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .proc-card-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: var(--proc-primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .proc-card-icon .material-icons-round {
        color: var(--proc-primary);
        font-size: 22px;
    }

    .proc-card h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--proc-text);
        line-height: 1.3;
        flex: 1;
    }

    .proc-card-meta {
        font-size: .8rem;
        color: var(--proc-text-muted);
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .proc-card-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .proc-card-meta .material-icons-round {
        font-size: 14px;
    }

    .proc-card-actions {
        position: absolute;
        top: 16px;
        right: 16px;
        display: flex;
        gap: 4px;
    }

    .proc-card-info {
        font-size: 0.9rem;
        color: var(--proc-text-muted);
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .proc-card-info span {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .proc-card-actions {
        position: absolute;
        top: 16px;
        right: 16px;
        display: flex;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .proc-card:hover .proc-card-actions {
        opacity: 1;
    }

    /* Modals */
    .proc-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        display: none;
        align-items: flex-start;
        justify-content: center;
        padding: 40px 20px;
        overflow-y: auto;
    }

    .proc-modal-overlay.open {
        display: flex;
    }

    .proc-modal {
        background: var(--proc-white);
        border-radius: 16px;
        width: 100%;
        max-width: 900px;
        padding: 40px;
        position: relative;
    }

    .proc-modal h2 {
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--proc-primary-dark);
        margin-top: 0;
    }

    /* Forms */
    .proc-form-section {
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--proc-border);
    }

    .proc-form-section h4 {
        color: var(--proc-primary);
        font-size: 1.1rem;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .proc-input-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 12px;
    }

    .proc-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .proc-field label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--proc-text);
    }

    .proc-field input,
    .proc-field textarea {
        padding: 10px 14px;
        border: 1px solid var(--proc-border);
        border-radius: 8px;
        font-size: 0.95rem;
    }

    .proc-field input:focus,
    .proc-field textarea:focus {
        outline: none;
        border-color: var(--proc-accent);
    }

    .proc-dynamic-item {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 12px;
        background: #fcfcfc;
        padding: 16px;
        border-radius: 10px;
        border: 1px solid #eee;
    }

    .proc-btn-add {
        background: var(--proc-primary-light);
        color: var(--proc-primary-dark);
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
    }

    .proc-btn-add:hover {
        background: #d0e8d0;
    }

    /* Checklists */
    .proc-checklist {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .proc-check-item {
        background: #fff;
        border: 1px solid #eee;
        padding: 16px;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .proc-check-label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        cursor: pointer;
    }

    .proc-check-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--proc-primary);
    }

    .proc-check-desc {
        display: none;
        margin-top: 4px;
    }

    .proc-check-desc textarea {
        width: 100%;
        min-height: 60px;
        font-size: 0.85rem;
        padding: 8px;
        box-sizing: border-box;
    }

    .proc-check-item.active .proc-check-desc {
        display: block;
    }

    /* Buttons */
    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        border: none;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--proc-primary);
        color: #fff;
    }

    .btn-primary:hover {
        background: var(--proc-primary-dark);
    }

    .btn-outline {
        background: transparent;
        color: var(--proc-primary);
        border: 2px solid var(--proc-primary);
    }

    .btn-outline:hover {
        background: var(--proc-primary-light);
    }

    .btn-danger {
        background: #fde8e8;
        color: var(--proc-danger);
    }

    .btn-danger:hover {
        background: #fbd5d5;
    }

    .btn-icon {
        padding: 6px;
        border-radius: 6px;
    }

    .proc-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 20px;
    }

    /* Viewer Styles */
    .proc-viewer-content {
        line-height: 1.6;
    }

    .proc-view-section {
        margin-bottom: 24px;
    }

    .proc-view-section h5 {
        color: var(--proc-text-muted);
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
        margin-bottom: 12px;
        border-bottom: 1px solid #eee;
        padding-bottom: 6px;
    }

    .proc-view-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 16px;
    }

    .proc-view-item {
        background: #f9f9f9;
        padding: 12px;
        border-radius: 8px;
    }

    .proc-view-item strong {
        display: block;
        color: var(--proc-primary-dark);
        margin-bottom: 4px;
    }

    .proc-view-item p {
        margin: 4px 0 0;
        font-size: 0.9rem;
        display: flex;
        align-items: flex-start;
        gap: 6px;
        word-break: break-all;
        line-height: 1.4;
    }

    .proc-view-item p .material-icons-round {
        margin-top: 2px;
        flex-shrink: 0;
    }

    .proc-rule-active {
        display: flex;
        gap: 12px;
        margin-bottom: 12px;
        padding: 12px;
        background: #eaffea;
        border-radius: 8px;
        border-left: 4px solid var(--proc-primary);
    }

    .proc-rule-active strong {
        color: var(--proc-primary-dark);
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        .proc-modal {
            padding: 24px;
        }

        .proc-header {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

<div class="proc-page">
    <a href="index.php?page=dashboard" class="btn btn-outline btn-sm"
        style="margin-bottom:20px;display:inline-flex;text-decoration:none !important;">
        <span class="material-icons-round" style="font-size:16px">arrow_back</span>
        Dashboard
    </a>

    <div class="proc-topbar">
        <div class="proc-topbar-left">
            <h2><span class="material-icons-round" style="font-size:2rem">business</span> Procedimentos Empresas</h2>
            <p>Configurações e contatos específicos por parceiro.</p>
        </div>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary" onclick="openProcModal()">
                <span class="material-icons-round">add</span> Cadastrar Nova Empresa
            </button>
        <?php endif; ?>
    </div>

    <div class="proc-search-wrap">
        <span class="material-icons-round">search</span>
        <input type="text" id="procSearch" placeholder="Buscar por qualquer palavra em qualquer empresa…">
    </div>
    <div id="searchHint" class="proc-search-hint"></div>

    <div class="proc-grid" id="procGrid">
        <!-- Cards will be rendered here -->
    </div>
</div>

<!-- VIEWER MODAL -->
<div class="proc-modal-overlay" id="viewerOverlay">
    <div class="proc-modal">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px;">
            <h2 id="viewName">Nome da Empresa</h2>
            <button class="btn-icon" onclick="closeModals()"><span class="material-icons-round">close</span></button>
        </div>

        <div class="proc-viewer-content" id="viewerContent">
            <!-- Content populated via JS -->
        </div>

        <div class="proc-modal-footer">
            <button class="btn btn-outline" onclick="closeModals()">Fechar</button>
        </div>
    </div>
</div>

<!-- ADMIN MODAL -->
<div class="proc-modal-overlay" id="adminModal">
    <div class="proc-modal">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px;">
            <h2 id="modalTitle">Novo Procedimento</h2>
            <button class="btn-icon" onclick="closeModals()"><span class="material-icons-round">close</span></button>
        </div>

        <form id="procForm">
            <input type="hidden" id="editId" value="">

            <!-- Basic Info -->
            <div class="proc-form-section">
                <div class="proc-field">
                    <label>Nome da Empresa *</label>
                    <input type="text" id="compName" required placeholder="Ex: Transportadora Exemplo Ltda">
                </div>
            </div>

            <!-- Contacts -->
            <div class="proc-form-section">
                <h4><span class="material-icons-round">contacts</span> Contatos da Empresa</h4>
                <div id="contactList"></div>
                <button type="button" class="proc-btn-add" onclick="addContactField()">
                    <span class="material-icons-round">add</span> Adicionar Contato
                </button>
            </div>

            <!-- Managers -->
            <div class="proc-form-section">
                <h4><span class="material-icons-round">assignment_ind</span> Gestores Responsáveis</h4>
                <div id="managerList"></div>
                <button type="button" class="proc-btn-add" onclick="addManagerField()">
                    <span class="material-icons-round">add</span> Adicionar Gestor
                </button>
            </div>

            <!-- Rules (Regras) -->
            <div class="proc-form-section">
                <h4><span class="material-icons-round">lock</span> Regras da Empresa</h4>
                <div class="proc-checklist" id="lockList">
                    <!-- Standard locks -->
                    <div class="proc-check-item" data-type="trava" data-label="Km Livre">
                        <label class="proc-check-label">
                            <input type="checkbox" onchange="toggleCheck(this)"> Km Livre
                        </label>
                        <div class="proc-check-desc">
                            <textarea placeholder="Descrição da regra de Km Livre..."></textarea>
                        </div>
                    </div>
                    <div class="proc-check-item" data-type="trava" data-label="Liberar Saldo">
                        <label class="proc-check-label">
                            <input type="checkbox" onchange="toggleCheck(this)"> Liberar Saldo
                        </label>
                        <div class="proc-check-desc">
                            <textarea placeholder="Procedimento para liberar saldo..."></textarea>
                        </div>
                    </div>
                </div>
                <button type="button" class="proc-btn-add" onclick="addCustomItem('trava', 'lockList')">
                    <span class="material-icons-round">add</span> Outra Regra
                </button>
            </div>

            <!-- Driver Rules -->
            <div class="proc-form-section">
                <h4><span class="material-icons-round">person</span> Regras para Motoristas</h4>
                <div class="proc-checklist" id="driverList">
                    <div class="proc-check-item" data-type="motorista" data-label="Correção de KM">
                        <label class="proc-check-label">
                            <input type="checkbox" onchange="toggleCheck(this)"> Correção de KM
                        </label>
                        <div class="proc-check-desc">
                            <textarea placeholder="Como proceder na correção..."></textarea>
                        </div>
                    </div>
                    <div class="proc-check-item" data-type="motorista" data-label="Consultar Saldo">
                        <label class="proc-check-label">
                            <input type="checkbox" onchange="toggleCheck(this)"> Consultar Saldo
                        </label>
                        <div class="proc-check-desc">
                            <textarea placeholder="Como o motorista consulta saldo..."></textarea>
                        </div>
                    </div>
                    <div class="proc-check-item" data-type="motorista" data-label="Relatórios">
                        <label class="proc-check-label">
                            <input type="checkbox" onchange="toggleCheck(this)"> Relatórios
                        </label>
                        <div class="proc-check-desc">
                            <textarea placeholder="Acesso a relatórios..."></textarea>
                        </div>
                    </div>
                    <div class="proc-check-item" data-type="motorista" data-label="Postos Credenciados">
                        <label class="proc-check-label">
                            <input type="checkbox" onchange="toggleCheck(this)"> Postos Credenciados
                        </label>
                        <div class="proc-check-desc">
                            <textarea placeholder="Consulta de postos..."></textarea>
                        </div>
                    </div>
                    <div class="proc-check-item" data-type="motorista" data-label="Liberar Média de KM">
                        <label class="proc-check-label">
                            <input type="checkbox" onchange="toggleCheck(this)"> Liberar Média de KM
                        </label>
                        <div class="proc-check-desc">
                            <textarea placeholder="Regra de média de KM..."></textarea>
                        </div>
                    </div>
                </div>
                <button type="button" class="proc-btn-add" onclick="addCustomItem('motorista', 'driverList')">
                    <span class="material-icons-round">add</span> Outra Regra
                </button>
            </div>

            <!-- Observations -->
            <div class="proc-form-section">
                <div class="proc-field">
                    <label>Observações Gerais</label>
                    <textarea id="compObs" rows="4" placeholder="Outras informações importantes..."></textarea>
                </div>
            </div>

            <div class="proc-modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModals()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="saveBtn">Salvar Procedimento</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRM -->
<div class="proc-modal-overlay" id="confirmDelete">
    <div class="proc-modal" style="max-width:400px; text-align:center;">
        <span class="material-icons-round"
            style="font-size:48px; color:var(--proc-danger); margin-bottom:12px;">warning</span>
        <h3>Excluir Procedimento?</h3>
        <p>Esta ação não pode ser desfeita.</p>
        <input type="hidden" id="deleteId">
        <div class="proc-modal-footer" style="justify-content:center;">
            <button class="btn btn-outline" onclick="closeModals()">Cancelar</button>
            <button class="btn btn-danger" onclick="confirmDelete()">Excluir</button>
        </div>
    </div>
</div>

<script>
    let allProcedures = [];
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

    async function loadProcedures() {
        const res = await fetch('api/procedures.php?action=list').then(r => r.json());
        if (res.success) {
            allProcedures = res.data;
            renderGrid(allProcedures);
        }
    }

    function renderGrid(data) {
        const grid = document.getElementById('procGrid');
        if (!data || data.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:60px; color:#999;"><span class="material-icons-round" style="font-size:48px; display:block; margin-bottom:16px; opacity:0.3">business_off</span>Nenhum procedimento encontrado.</div>';
            return;
        }

        grid.innerHTML = data.map(p => `
            <div class="proc-card" onclick="viewProcedure(${p.id})">
                <div class="proc-card-top">
                    <div class="proc-card-icon">
                        <span class="material-icons-round">business</span>
                    </div>
                    <div>
                        <h3>${esc(p.name)}</h3>
                    </div>
                </div>
                <div class="proc-card-meta">
                    <span><span class="material-icons-round">contacts</span>${p.contact_count || 0} contatos</span>
                    <span><span class="material-icons-round">schedule</span>${fmtDate(p.created_at)}</span>
                </div>
                ${isAdmin ? `
                <div class="proc-card-actions" onclick="event.stopPropagation()">
                    <button class="btn-icon" onclick="editProcedure(${p.id})" title="Editar"><span class="material-icons-round" style="font-size:18px; color:var(--proc-primary)">edit</span></button>
                    <button class="btn-icon danger" onclick="askDelete(${p.id})" title="Excluir"><span class="material-icons-round" style="font-size:18px">delete</span></button>
                </div>
                ` : ''}
            </div>
        `).join('');
    }

    // Search
    const searchHint = document.getElementById('searchHint');
    document.getElementById('procSearch').addEventListener('input', debounce(async (e) => {
        const q = e.target.value.trim();
        if (!q) {
            searchHint.textContent = '';
            renderGrid(allProcedures);
            return;
        }
        searchHint.textContent = 'Buscando...';
        const res = await fetch(`api/procedures.php?action=search&q=${encodeURIComponent(q)}`).then(r => r.json());
        if (res.success) {
            searchHint.textContent = `${res.data.length} resultado(s) para "${q}"`;
            renderGrid(res.data);
        }
    }, 400));

    // Header User Menu (Mandatory)
    function toggleUserMenu() {
        document.getElementById('userDropdown').classList.toggle('open');
    }
    function closeUserMenu() {
        var dd = document.getElementById('userDropdown');
        if (dd) dd.classList.remove('open');
    }
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.user-menu')) closeUserMenu();
    });
    function confirmLogout() { return confirm('Deseja realmente sair?'); }
    function openChangePassword() { window.location.href = 'index.php?page=board'; }

    // Modal Helpers
    function openProcModal() {
        document.getElementById('modalTitle').textContent = 'Novo Procedimento';
        document.getElementById('editId').value = '';
        document.getElementById('procForm').reset();
        document.getElementById('contactList').innerHTML = '';
        document.getElementById('managerList').innerHTML = '';

        // Reset checklists and descriptions
        document.querySelectorAll('.proc-check-item').forEach(el => {
            el.classList.remove('active');
            const chk = el.querySelector('input[type="checkbox"]');
            if (chk) chk.checked = false;
        });

        // Remove custom items (those without standard labels)
        const standardLabels = ['Km Livre', 'Liberar Saldo', 'Correção de KM', 'Consultar Saldo', 'Relatórios', 'Postos Credenciados', 'Liberar Média de KM'];
        document.querySelectorAll('.proc-check-item').forEach(el => {
            if (!standardLabels.includes(el.dataset.label)) el.remove();
        });

        addContactField();
        addManagerField();
        document.getElementById('adminModal').classList.add('open');
    }

    function closeModals() {
        document.querySelectorAll('.proc-modal-overlay').forEach(el => el.classList.remove('open'));
        document.body.style.overflow = '';
    }

    function toggleCheck(chk) {
        const item = chk.closest('.proc-check-item');
        if (chk.checked) item.classList.add('active');
        else item.classList.remove('active');
    }

    // Dynamic Fields
    function addContactField(data = {}) {
        const div = document.createElement('div');
        div.className = 'proc-dynamic-item';
        div.innerHTML = `
            <div class="proc-input-grid" style="flex:1">
                <div class="proc-field"><label>Nome</label><input type="text" class="c-name" value="${data.name || ''}" placeholder="Opcional"></div>
                <div class="proc-field"><label>Telefone</label><input type="text" class="c-phone" value="${data.phone || ''}"></div>
                <div class="proc-field"><label>E-mail</label><input type="text" class="c-email" value="${data.email || ''}"></div>
            </div>
            <button type="button" class="btn-icon danger" onclick="this.closest('.proc-dynamic-item').remove()"><span class="material-icons-round">delete</span></button>
        `;
        document.getElementById('contactList').appendChild(div);
    }

    function addManagerField(data = {}) {
        const div = document.createElement('div');
        div.className = 'proc-dynamic-item';
        div.innerHTML = `
            <div class="proc-input-grid" style="flex:1">
                <div class="proc-field"><label>Nome</label><input type="text" class="m-name" value="${data.name || ''}"></div>
                <div class="proc-field"><label>Telefone</label><input type="text" class="m-phone" value="${data.phone || ''}"></div>
                <div class="proc-field"><label>Observação</label><input type="text" class="m-obs" value="${data.observation || ''}" placeholder="Ex: Cargo, Setor..."></div>
            </div>
            <button type="button" class="btn-icon danger" onclick="this.closest('.proc-dynamic-item').remove()"><span class="material-icons-round">delete</span></button>
        `;
        document.getElementById('managerList').appendChild(div);
    }

    function addCustomItem(type, targetId, data = {}) {
        const div = document.createElement('div');
        div.className = `proc-check-item ${data.enabled == 1 ? 'active' : ''}`;
        div.dataset.type = type;
        div.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px; flex:1">
                    <input type="checkbox" onchange="toggleCheck(this)" ${data.enabled == 1 ? 'checked' : ''} style="width:18px;height:18px;accent-color:var(--proc-primary)">
                    <input type="text" class="custom-label" placeholder="Nome da regra..." value="${data.label || ''}" 
                           style="border:none; border-bottom:1px solid #ddd; background:transparent; font-weight:600; font-family:inherit; font-size:1rem; width:100%; padding:4px 0;">
                </div>
                <button type="button" class="btn-icon" style="color:#aaa" onclick="this.closest('.proc-check-item').remove()"><i class="material-icons-round" style="font-size:16px">close</i></button>
            </div>
            <div class="proc-check-desc" style="${data.enabled == 1 ? 'display:block' : ''}">
                <textarea placeholder="Descrição...">${data.description || ''}</textarea>
            </div>
        `;
        document.getElementById(targetId).appendChild(div);
    }

    // CRUD
    document.getElementById('procForm').onsubmit = async (e) => {
        e.preventDefault();
        const btn = document.getElementById('saveBtn');
        const id = document.getElementById('editId').value;

        const payload = {
            id: id || 0,
            name: document.getElementById('compName').value,
            observation: document.getElementById('compObs').value,
            contacts: [...document.querySelectorAll('#contactList .proc-dynamic-item')].map(el => ({
                name: el.querySelector('.c-name').value,
                phone: el.querySelector('.c-phone').value,
                email: el.querySelector('.c-email').value
            })),
            managers: [...document.querySelectorAll('#managerList .proc-dynamic-item')].map(el => ({
                name: el.querySelector('.m-name').value,
                phone: el.querySelector('.m-phone').value,
                observation: el.querySelector('.m-obs').value
            })),
            items: [...document.querySelectorAll('.proc-check-item')].map(el => {
                const isCustom = el.querySelector('.custom-label');
                return {
                    type: el.dataset.type,
                    label: isCustom ? isCustom.value : el.dataset.label,
                    enabled: el.querySelector('input[type="checkbox"]').checked ? 1 : 0,
                    description: el.querySelector('textarea').value
                };
            })
        };

        btn.innerHTML = 'Salvando...';
        btn.disabled = true;

        const res = await fetch('api/procedures.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(r => r.json());

        if (res.success) {
            closeModals();
            loadProcedures();
        } else {
            alert('Erro: ' + res.message);
        }
        btn.innerHTML = 'Salvar Procedimento';
        btn.disabled = false;
    };

    async function editProcedure(id) {
        const res = await fetch(`api/procedures.php?action=get&id=${id}`).then(r => r.json());
        if (!res.success) return;
        const p = res.data;

        openProcModal();
        document.getElementById('modalTitle').textContent = 'Editar Procedimento';
        document.getElementById('editId').value = p.id;
        document.getElementById('compName').value = p.name;
        document.getElementById('compObs').value = p.observation || '';

        // Contacts & Managers
        document.getElementById('contactList').innerHTML = '';
        p.contacts.forEach(c => addContactField(c));
        document.getElementById('managerList').innerHTML = '';
        p.managers.forEach(m => addManagerField(m));

        // Items
        p.items.forEach(item => {
            const standard = document.querySelector(`.proc-check-item[data-label="${item.label}"][data-type="${item.type}"]`);
            if (standard) {
                const chk = standard.querySelector('input[type="checkbox"]');
                chk.checked = item.enabled == 1;
                standard.querySelector('textarea').value = item.description || '';
                if (chk.checked) standard.classList.add('active');
            } else {
                addCustomItem(item.type, item.type === 'trava' ? 'lockList' : 'driverList', item);
            }
        });
    }

    async function viewProcedure(id) {
        const res = await fetch(`api/procedures.php?action=get&id=${id}`).then(r => r.json());
        if (!res.success) return;
        const p = res.data;

        document.getElementById('viewName').textContent = p.name;
        const content = document.getElementById('viewerContent');

        let html = `
            <div class="proc-view-section">
                <h5>Contatos da Empresa</h5>
                <div class="proc-view-grid">
                    ${p.contacts.map(c => `
                        <div class="proc-view-item">
                            <strong>${esc(c.name)}</strong>
                            ${c.phone ? `<p><span class="material-icons-round" style="font-size:16px;">phone</span> <span>${esc(c.phone)}</span></p>` : ''}
                            ${c.email ? `<p><span class="material-icons-round" style="font-size:16px;">email</span> <span>${esc(c.email)}</span></p>` : ''}
                        </div>
                    `).join('') || '<p>Nenhum contato cadastrado.</p>'}
                </div>
            </div>

            <div class="proc-view-section">
                <h5>Gestores Responsáveis</h5>
                <div class="proc-view-grid">
                    ${p.managers.map(m => `
                        <div class="proc-view-item">
                            <strong>${esc(m.name)}</strong>
                            ${m.phone ? `<p><span class="material-icons-round" style="font-size:16px;">phone</span> <span>${esc(m.phone)}</span></p>` : ''}
                            ${m.observation ? `<p><span class="material-icons-round" style="font-size:16px;">info</span> <span>${esc(m.observation)}</span></p>` : ''}
                        </div>
                    `).join('') || '<p>Nenhum gestor cadastrado.</p>'}
                </div>
            </div>

            <div class="proc-view-section">
                <h5>Regras da Empresa</h5>
                ${p.items.filter(i => i.enabled == 1 && i.type === 'trava').map(i => `
                    <div class="proc-rule-active" style="border-left-color: #2E7D32;">
                        <span class="material-icons-round" style="color:var(--proc-primary)">lock</span>
                        <div>
                            <strong>${esc(i.label)}</strong>
                            ${i.description ? `<p style="margin:4px 0 0; white-space:pre-wrap;">${esc(i.description)}</p>` : ''}
                        </div>
                    </div>
                `).join('') || '<p>Nenhuma regra ativa.</p>'}
            </div>

            <div class="proc-view-section">
                <h5>Regras para Motoristas</h5>
                ${p.items.filter(i => i.enabled == 1 && i.type === 'motorista').map(i => `
                    <div class="proc-rule-active" style="border-left-color: #0288D1;">
                        <span class="material-icons-round" style="color:#0288D1">check_circle</span>
                        <div>
                            <strong>${esc(i.label)}</strong>
                            ${i.description ? `<p style="margin:4px 0 0; white-space:pre-wrap;">${esc(i.description)}</p>` : ''}
                        </div>
                    </div>
                `).join('') || '<p>Nenhuma regra ativa.</p>'}
            </div>

            ${p.observation ? `
            <div class="proc-view-section">
                <h5>Observações Gerais</h5>
                <div style="background:#fff7e6; padding:16px; border-radius:8px; border-left:4px solid #ffa940; white-space:pre-wrap;">${esc(p.observation)}</div>
            </div>
            ` : ''}
        `;

        content.innerHTML = html;
        document.getElementById('viewerOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function askDelete(id) {
        document.getElementById('deleteId').value = id;
        document.getElementById('confirmDelete').classList.add('open');
    }

    async function confirmDelete() {
        const id = document.getElementById('deleteId').value;
        await fetch('api/procedures.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        }).then(r => r.json());
        closeModals();
        loadProcedures();
    }

    // Utils
    function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function fmtDate(s) { return s ? new Date(s).toLocaleDateString('pt-BR') : ''; }
    function debounce(fn, ms) {
        let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), ms); };
    }

    // Init
    loadProcedures();
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>