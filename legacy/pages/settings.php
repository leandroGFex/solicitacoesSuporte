<?php
// Settings Page — Gerenciamento de Usuários e Config. do Sistema
require_once __DIR__ . '/../config/config.php';

if ($_SESSION['user_role'] !== 'admin') {
  header('Location: index.php?page=board');
  exit;
}

include __DIR__ . '/../layout/header.php';
?>

<div style="background:var(--bg);min-height:calc(100vh - 56px);padding:24px">
  <div style="max-width:900px;margin:0 auto">

    <!-- Header -->
    <div
      style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
      <div>
        <h1 style="font-size:1.4rem;font-weight:700;color:#212121;display:flex;align-items:center;gap:10px">
          <span class="material-icons-round" style="color:#00897B">settings</span> Configurações
        </h1>
        <p style="color:#757575;font-size:.88rem">Gerenciamento de usuários e configurações do sistema</p>
      </div>
      <a href="index.php?page=dashboard" class="btn btn-primary-main"><span
          class="material-icons-round">arrow_back</span>
        Voltar ao Dashboard</a>
    </div>

    <!-- Tabs de config -->
    <div style="display:flex;gap:2px;border-bottom:2px solid #e0e0e0;margin-bottom:24px">
      <button class="tab-btn active" data-stab="users" onclick="switchSettingsTab('users')">
        <span class="material-icons-round">group</span> Usuários
      </button>
      <button class="tab-btn" data-stab="system" onclick="switchSettingsTab('system')">
        <span class="material-icons-round">tune</span> Sistema
      </button>
    </div>

    <!-- TAB: USUÁRIOS -->
    <div id="stab-users">
      <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
        <button class="btn btn-primary-main" onclick="openNewUserModal()">
          <span class="material-icons-round">person_add</span> Novo Usuário
        </button>
      </div>
      <div style="background:#fff;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:#f5f6fa">
              <th
                style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#757575;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e0e0e0">
                Nome</th>
              <th
                style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#757575;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e0e0e0">
                E-mail</th>
              <th
                style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#757575;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e0e0e0">
                Perfil</th>
              <th
                style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#757575;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e0e0e0">
                Status</th>
              <th style="padding:12px 16px;border-bottom:1px solid #e0e0e0"></th>
            </tr>
          </thead>
          <tbody id="usersTable">
            <tr>
              <td colspan="5" style="text-align:center;padding:32px;color:#999">Carregando...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB: SISTEMA -->
    <div id="stab-system" style="display:none">

      <!-- IMAP Card -->
      <div
        style="background:#fff;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:28px;margin-bottom:20px">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:8px">
          <span class="material-icons-round" style="color:#00897B">email</span> Integração de E-mail (IMAP)
        </h3>
        <p style="color:#757575;font-size:.82rem;margin-bottom:16px">
          O sistema lê a caixa de entrada e cria cards automaticamente.<br>
          <strong>Fluxo:</strong> envie um e-mail colocando o e-mail do sistema em <strong>CC</strong> → card criado
          automaticamente.
        </p>
        <div
          style="background:#E8F5E9;border:1px solid #A5D6A7;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:.82rem;color:#1B5E20;display:flex;gap:8px">
          <span class="material-icons-round" style="font-size:18px;flex-shrink:0">lightbulb</span>
          <span><strong>Recomendado:</strong> crie um Gmail (ex: <code>solicitacoes.flexgrupo@gmail.com</code>),
            ative verificação em 2 etapas, gere uma <strong>Senha de App</strong> e use abaixo.<br>
            Host: <code>imap.gmail.com</code> | Porta: <code>993</code> | SSL: Sim</span>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Host IMAP</label>
            <input class="form-control" id="imapHost" value="<?= htmlspecialchars(IMAP_HOST ?: 'imap.gmail.com') ?>"
              placeholder="imap.gmail.com">
          </div>
          <div class="form-group">
            <label>Porta</label>
            <input class="form-control" id="imapPort" type="number" value="<?= IMAP_PORT ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Usuário (E-mail do sistema)</label>
            <input class="form-control" id="imapUser" value="<?= htmlspecialchars(IMAP_USER) ?>"
              placeholder="sistema@gmail.com">
          </div>
          <div class="form-group">
            <label>Senha / Senha de App</label>
            <input class="form-control" type="password" id="imapPass" placeholder="••••••••••••">
          </div>
        </div>
        <div class="form-row" style="margin-bottom:16px;">
          <div class="form-group" style="max-width:300px;">
            <label>Pasta IMAP (Opcional - Ex: INBOX ou SuporteFlex)</label>
            <input class="form-control" id="imapFolder"
              value="<?= htmlspecialchars(defined('IMAP_FOLDER') ? IMAP_FOLDER : 'INBOX') ?>" placeholder="INBOX">
          </div>
        </div>
        <hr style="border:0;border-top:1px solid #e0e0e0;margin:20px 0;">
        <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:12px;color:#212121;">Filtros de Assunto (E-mail para Card)</h4>
        <p style="color:#757575;font-size:.82rem;margin-bottom:16px">
          Defina as palavras-chave que o e-mail deve conter no <strong>Assunto</strong> para gerar o card na categoria correta. Separe por vírgulas.
        </p>
        <div class="form-row" style="margin-bottom:16px;">
            <div class="form-group">
                <label>Assuntos para CARTÃO</label>
                <input class="form-control" id="imapKwCartao" value="<?= htmlspecialchars(defined('IMAP_KW_CARTAO') ? IMAP_KW_CARTAO : 'Cartão, Cartao') ?>" placeholder="Cartão, Novo Cartão">
            </div>
            <div class="form-group">
                <label>Assuntos para TAG</label>
                <input class="form-control" id="imapKwTag" value="<?= htmlspecialchars(defined('IMAP_KW_TAG') ? IMAP_KW_TAG : 'Tag') ?>" placeholder="Tag, Nova Tag">
            </div>
        </div>
        <div class="form-row" style="margin-bottom:16px;">
            <div class="form-group">
                <label>Assuntos para POS (Máquina)</label>
                <input class="form-control" id="imapKwPos" value="<?= htmlspecialchars(defined('IMAP_KW_POS') ? IMAP_KW_POS : 'POS, Máquina, Maquina') ?>" placeholder="POS, Máquina">
            </div>
            <div class="form-group">
                <label>Assuntos para RASTREADOR</label>
                <input class="form-control" id="imapKwRastreio" value="<?= htmlspecialchars(defined('IMAP_KW_RASTREIO') ? IMAP_KW_RASTREIO : 'Rastreador, Rastreio') ?>" placeholder="Rastreador, Rastreio">
            </div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:16px">
          <input type="checkbox" id="imapSsl" <?= IMAP_SSL ? 'checked' : '' ?>> Usar SSL
        </label>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn btn-outline" onclick="testImapConnection()">
            <span class="material-icons-round">wifi</span> Testar Conexão
          </button>
          <button class="btn btn-primary-main" onclick="saveImapConfig()">
            <span class="material-icons-round">save</span> Salvar
          </button>
        </div>
        <div id="imapTestResult" style="margin-top:12px"></div>
        <p style="font-size:.78rem;color:#9E9E9E;margin-top:12px">⚠️ Após salvar, envie o <code>config/config.php</code>
          atualizado ao servidor via FTP.</p>
      </div>


    </div>
  </div>
</div>

<!-- MODAL: NOVO/EDITAR USUÁRIO -->
<div class="modal-overlay" id="userModal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h2 id="userModalTitle">Novo Usuário</h2>
      <button class="modal-close" onclick="closeModal('userModal')"><span
          class="material-icons-round">close</span></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Nome</label>
        <input type="text" class="form-control" id="userName" placeholder="Nome completo">
      </div>
      <div class="form-group">
        <label>E-mail</label>
        <input type="email" class="form-control" id="userEmail" placeholder="email@exemplo.com">
      </div>
      <div class="form-group">
        <label>Perfil</label>
        <select class="form-control" id="userRole">
          <option value="user">Usuário</option>
          <option value="admin">Administrador</option>
        </select>
      </div>
      <div id="passwordFields">
        <div class="form-group">
          <label>Senha</label>
          <input type="password" class="form-control" id="userPass" placeholder="Mínimo 6 caracteres">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-danger btn-sm" id="btnResetPass" onclick="resetUserPassword()"
        style="margin-right:auto;display:none">
        <span class="material-icons-round">lock_reset</span> Redefinir Senha
      </button>
      <button class="btn btn-outline" onclick="closeModal('userModal')">Cancelar</button>
      <button class="btn btn-primary-main" onclick="saveUser()"><span class="material-icons-round">save</span>
        Salvar</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
  let editingUserId = null;

  function switchSettingsTab(tab) {
    document.querySelectorAll('[data-stab]').forEach(b => b.classList.toggle('active', b.dataset.stab === tab));
    document.getElementById('stab-users').style.display = tab === 'users' ? 'block' : 'none';
    document.getElementById('stab-system').style.display = tab === 'system' ? 'block' : 'none';
  }

  async function loadUsers() {
    const res = await fetch('api/users.php?action=list');
    const data = await res.json();
    const tbody = document.getElementById('usersTable');
    if (!data.users?.length) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#999">Nenhum usuário</td></tr>';
      return;
    }
    tbody.innerHTML = data.users.map(u => `
        <tr>
          <td style="padding:12px 16px;font-weight:600">${esc(u.name)}</td>
          <td style="padding:12px 16px;color:#757575">${esc(u.email)}</td>
          <td style="padding:12px 16px">
            <span style="background:${u.role === 'admin' ? '#E8EAF6' : '#E0F2F1'};color:${u.role === 'admin' ? '#3949AB' : '#00695C'};padding:3px 10px;border-radius:12px;font-size:.75rem;font-weight:700;text-transform:uppercase">
              ${u.role === 'admin' ? 'Admin' : 'Usuário'}
            </span>
          </td>
          <td style="padding:12px 16px">
            <span style="background:${u.active ? '#E8F5E9' : '#FFEBEE'};color:${u.active ? '#2E7D32' : '#C62828'};padding:3px 10px;border-radius:12px;font-size:.75rem;font-weight:700">
              ${u.active ? '● Ativo' : '● Inativo'}
            </span>
          </td>
          <td style="padding:12px 16px;text-align:right">
            <button class="btn btn-outline btn-sm" onclick="openEditUserModal(${JSON.stringify(u).replace(/\"/g, '&quot;')})">
              <span class="material-icons-round">edit</span>
            </button>
            <button class="btn btn-sm" style="background:#f5f6fa;border:1px solid #e0e0e0;margin-left:6px" onclick="toggleUserStatus(${u.id}, ${u.active})">
              <span class="material-icons-round">${u.active ? 'block' : 'check_circle'}</span>
            </button>
          </td>
        </tr>
    `).join('');
  }

  function openNewUserModal() {
    editingUserId = null;
    document.getElementById('userModalTitle').textContent = 'Novo Usuário';
    document.getElementById('btnResetPass').style.display = 'none';
    document.getElementById('userName').value = '';
    document.getElementById('userEmail').value = '';
    document.getElementById('userRole').value = 'user';
    document.getElementById('userPass').value = '';
    document.getElementById('passwordFields').style.display = 'block';
    openModal('userModal');
  }

  function openEditUserModal(user) {
    editingUserId = user.id;
    document.getElementById('userModalTitle').textContent = 'Editar Usuário';
    document.getElementById('btnResetPass').style.display = 'flex';
    document.getElementById('userName').value = user.name;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userRole').value = user.role;
    document.getElementById('passwordFields').style.display = 'none';
    openModal('userModal');
  }

  async function saveUser() {
    const form = new FormData();
    form.append('action', editingUserId ? 'update' : 'create');
    if (editingUserId) form.append('id', editingUserId);
    form.append('name', document.getElementById('userName').value);
    form.append('email', document.getElementById('userEmail').value);
    form.append('role', document.getElementById('userRole').value);
    if (!editingUserId) form.append('password', document.getElementById('userPass').value);

    const res = await fetch('api/users.php', { method: 'POST', body: form });
    const data = await res.json();
    if (data.success) {
      closeModal('userModal'); loadUsers();
      toast(editingUserId ? 'Usuário atualizado!' : 'Usuário criado!', 'success');
    } else toast(data.message || 'Erro', 'error');
  }

  async function toggleUserStatus(id, currentActive) {
    const form = new FormData();
    form.append('action', 'toggle_active');
    form.append('id', id);
    await fetch('api/users.php', { method: 'POST', body: form });
    toast(currentActive ? 'Usuário desativado' : 'Usuário ativado', 'success');
    loadUsers();
  }

  async function resetUserPassword() {
    const newPass = prompt('Nova senha para este usuário (mínimo 6 caracteres):');
    if (!newPass || newPass.length < 6) { toast('Senha inválida', 'error'); return; }
    const form = new FormData();
    form.append('action', 'change_password');
    form.append('id', editingUserId);
    form.append('password', newPass);
    const res = await fetch('api/users.php', { method: 'POST', body: form });
    const data = await res.json();
    if (data.success) toast('Senha redefinida com sucesso!', 'success');
    else toast(data.message || 'Erro', 'error');
  }

  function openModal(id) { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

  function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = msg;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 3000);
  }

  async function testImapConnection() {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px"></span> Testando...';
    const form = new FormData();
    form.append('action', 'test');
    form.append('host', document.getElementById('imapHost').value);
    form.append('port', document.getElementById('imapPort').value);
    form.append('user', document.getElementById('imapUser').value);
    form.append('pass', document.getElementById('imapPass').value);
    form.append('folder', document.getElementById('imapFolder').value);
    form.append('ssl', document.getElementById('imapSsl').checked ? '1' : '0');
    const res = await fetch('api/email.php', { method: 'POST', body: form });
    const data = await res.json();
    btn.disabled = false;
    btn.innerHTML = '<span class="material-icons-round">wifi</span> Testar Conexão';
    const r = document.getElementById('imapTestResult');
    r.innerHTML = `<div style="padding:10px 14px;border-radius:8px;font-size:.83rem;background:${data.success ? '#E8F5E9' : '#FFEBEE'};color:${data.success ? '#1B5E20' : '#B71C1C'}">${data.message}</div>`;
  }

  function saveImapConfig() {
    const host = document.getElementById('imapHost').value.trim();
    const port = document.getElementById('imapPort').value.trim();
    const user = document.getElementById('imapUser').value.trim();
    const pass = document.getElementById('imapPass').value;
    const folder = document.getElementById('imapFolder').value.trim() || 'INBOX';
    const ssl = document.getElementById('imapSsl').checked ? 'true' : 'false';
    const kwCartao = document.getElementById('imapKwCartao').value.trim() || 'Cartão, Cartao';
    const kwTag = document.getElementById('imapKwTag').value.trim() || 'Tag';
    const kwPos = document.getElementById('imapKwPos').value.trim() || 'POS, Máquina, Maquina';
    const kwRastreio = document.getElementById('imapKwRastreio').value.trim() || 'Rastreador, Rastreio';

    if (!host || !user) { toast('Preencha host e usuário', 'error'); return; }
    // Gera o trecho config para copiar
    const snippet = `define('IMAP_HOST',   '${host}');\ndefine('IMAP_PORT',   ${port});\ndefine('IMAP_USER',   '${user}');\ndefine('IMAP_PASS',   '${pass}');\ndefine('IMAP_FOLDER', '${folder}');\ndefine('IMAP_SSL',    ${ssl});\ndefine('EMAIL_ENABLED', true);\n\n// Filtros de Assunto\ndefine('IMAP_KW_CARTAO', '${kwCartao}');\ndefine('IMAP_KW_TAG', '${kwTag}');\ndefine('IMAP_KW_POS', '${kwPos}');\ndefine('IMAP_KW_RASTREIO', '${kwRastreio}');`;
    prompt('Copie o trecho abaixo e cole em config/config.php:', snippet);
    toast('Copie o trecho e atualize config/config.php via FTP', 'info');
  }

  loadUsers();
</script>
<?php include __DIR__ . '/../layout/footer.php'; ?>