<?php
// Login Page
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= APP_NAME ?> — Login
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #004D40 0%, #00695C 50%, #004D40 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")
        }

        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.35);
            position: relative;
            z-index: 1;
            animation: fadeUp .4s ease
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 36px
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #00897B, #004D40);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(0, 137, 123, .4)
        }

        .logo-icon span {
            font-size: 32px;
            color: #FFD600
        }

        .logo h1 {
            color: #004D40;
            font-size: 1.7rem;
            font-weight: 700;
            letter-spacing: -0.5px
        }

        .logo p {
            color: #888;
            font-size: .875rem;
            margin-top: 4px
        }

        .form-group {
            margin-bottom: 20px
        }

        label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: #444;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .5px
        }

        .input-wrap {
            position: relative
        }

        .input-wrap span {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #00897B;
            font-size: 20px
        }

        input[type=email],
        input[type=password] {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: .95rem;
            font-family: 'Inter', sans-serif;
            transition: border-color .2s, box-shadow .2s;
            background: #fafafa
        }

        input:focus {
            outline: none;
            border-color: #00897B;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(0, 137, 123, .1)
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #FFD600, #FFC107);
            color: #333;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 8px;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 4px 16px rgba(255, 214, 0, .4);
            letter-spacing: .3px
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255, 214, 0, .5)
        }

        .btn-login:active {
            transform: translateY(0)
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: .875rem;
            display: flex;
            align-items: center;
            gap: 8px;
            display: none
        }

        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            border: 1px solid #EF9A9A
        }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: .78rem;
            color: #bbb
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="logo" style="display: flex; flex-direction: column; align-items: center;">
            <img src="assets/img/logo_colored.png" alt="GRUPO FLEX"
                style="width: 200px; height: auto; margin-bottom: 12px; object-fit: contain;">
            <p>Painel de Ferramentas — Acesso ao sistema</p>
        </div>

        <div class="alert alert-error" id="alertError">
            <span class="material-icons-round" style="font-size:18px">error</span>
            <span id="alertMsg"></span>
        </div>

        <form id="loginForm" onsubmit="doLogin(event)">
            <div class="form-group">
                <label>E-mail</label>
                <div class="input-wrap">
                    <span class="material-icons-round">email</span>
                    <input type="email" id="email" placeholder="seu@email.com" required autofocus>
                </div>
            </div>
            <div class="form-group">
                <label>Senha</label>
                <div class="input-wrap">
                    <span class="material-icons-round">lock</span>
                    <input type="password" id="password" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="btn-login" id="btnLogin">
                Entrar no Sistema →
            </button>
        </form>
        <p class="footer-text">Grupo Flex ©
            <?= date('Y') ?> — v
            <?= APP_VERSION ?>
        </p>
    </div>
    <script>
        async function doLogin(e) {
            e.preventDefault();
            const btn = document.getElementById('btnLogin');
            btn.textContent = 'Entrando…';
            btn.disabled = true;

            const form = new FormData();
            form.append('action', 'login');
            form.append('email', document.getElementById('email').value);
            form.append('password', document.getElementById('password').value);

            const res = await fetch('api/auth.php', { method: 'POST', body: form });
            const data = await res.json();

            if (data.success) {
                window.location = 'index.php?page=dashboard';
            } else {
                const el = document.getElementById('alertError');
                document.getElementById('alertMsg').textContent = data.message || 'Erro ao fazer login';
                el.style.display = 'flex';
                btn.textContent = 'Entrar no Sistema →';
                btn.disabled = false;
            }
        }
    </script>
</body>

</html>