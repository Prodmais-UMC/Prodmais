<?php
namespace App\View\Pages\Auth;

/**
 * LoginPage — Redesign completo
 * Split-screen: painel de marca (esquerda) + formulário (direita)
 */
class LoginPage {
    public static function display($props = []) {
        $error = $props['error'] ?? '';
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title>Entrar — Prodmais UMC</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue-900: #0f1f4b;
            --blue-800: #162449;
            --blue-700: #1a3a6b;
            --blue-600: #1a56db;
            --blue-500: #3b82f6;
            --blue-400: #60a5fa;
            --gray-50:  #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
            --red-500:  #ef4444;
            --red-50:   #fef2f2;
            --red-200:  #fecaca;
            --font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --ease: cubic-bezier(0.4, 0, 0.2, 1);
        }

        html {
            overflow-x: clip;
        }

        body {
            height: 100%;
            width: 100%;
            overflow-x: clip;
            font-family: var(--font);
            -webkit-font-smoothing: antialiased;
        }

        /* ══════════════════════════════════════════
           LAYOUT SPLIT
        ══════════════════════════════════════════ */
        .auth-shell {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* ─── PAINEL ESQUERDO (marca) — sticky ─── */
        .brand-panel {
            flex: 0 0 42%;
            min-width: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            align-self: flex-start;
            background:
                radial-gradient(ellipse at 20% 20%, rgba(59,130,246,.18) 0%, transparent 55%),
                radial-gradient(ellipse at 80% 80%, rgba(30,64,175,.20) 0%, transparent 55%),
                linear-gradient(160deg, var(--blue-900) 0%, #0d1b4a 50%, #0a1535 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem 3rem 2.5rem;
            overflow: hidden;
        }

        /* grade decorativa */
        .brand-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
        }

        /* orbe decorativo */
        .brand-panel::after {
            content: '';
            position: absolute;
            bottom: -80px; right: -80px;
            width: 320px; height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59,130,246,.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .brand-top {
            position: relative;
            z-index: 1;
        }

        .brand-logo-row {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            margin-bottom: 3.5rem;
        }

        .brand-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: rgba(59,130,246,.25);
            border: 1px solid rgba(255,255,255,.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--blue-400);
            font-size: 1.2rem;
            backdrop-filter: blur(6px);
        }

        .brand-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.03em;
        }

        .brand-name span { color: var(--blue-400); }

        .brand-headline {
            font-size: clamp(1.6rem, 3vw, 2.1rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
        }

        .brand-sub {
            font-size: 0.9rem;
            color: rgba(255,255,255,.55);
            line-height: 1.6;
            max-width: 320px;
            margin-bottom: 2.5rem;
        }

        /* feature pills */
        .feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }

        .feature-dot {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(59,130,246,.18);
            border: 1px solid rgba(59,130,246,.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--blue-400);
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .feature-text {
            font-size: 0.875rem;
            color: rgba(255,255,255,.75);
            font-weight: 500;
        }

        .brand-bottom {
            position: relative;
            z-index: 1;
        }

        .brand-tagline {
            font-size: 0.75rem;
            color: rgba(255,255,255,.3);
            letter-spacing: 0.04em;
        }

        /* ─── PAINEL DIREITO (formulário) ─────── */
        .form-panel {
            flex: 1;
            min-width: 0;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
        }

        .form-inner {
            width: 100%;
            max-width: 400px;
            animation: slideUp .45s var(--ease) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* cabeçalho do form */
        .form-eyebrow {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--blue-600);
            margin-bottom: .75rem;
        }

        .form-heading {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--gray-900);
            letter-spacing: -.03em;
            line-height: 1.2;
            margin-bottom: .5rem;
        }

        .form-desc {
            font-size: .9rem;
            color: var(--gray-500);
            margin-bottom: 2rem;
        }

        /* ── campos ── */
        .field-group { margin-bottom: 1.25rem; }

        .field-label {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: .45rem;
        }

        .field-label label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--gray-700);
            letter-spacing: .01em;
        }

        .field-label a {
            font-size: .78rem;
            color: var(--blue-600);
            text-decoration: none;
            font-weight: 500;
        }

        .field-label a:hover { text-decoration: underline; }

        /* input com ícone */
        .input-wrap {
            position: relative;
        }

        .input-wrap .field-icon {
            position: absolute;
            left: .9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: .85rem;
            pointer-events: none;
            transition: color .15s;
        }

        .input-wrap input {
            width: 100%;
            padding: .75rem .9rem .75rem 2.5rem;
            font-size: .9375rem;
            font-family: var(--font);
            color: var(--gray-900);
            background: var(--gray-50);
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }

        .input-wrap input::placeholder { color: var(--gray-400); }

        .input-wrap input:focus {
            background: #fff;
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3.5px rgba(26,86,219,.1);
        }

        .input-wrap input:focus + .field-icon,
        .input-wrap:focus-within .field-icon {
            color: var(--blue-600);
        }

        /* botão olho (senha) */
        .toggle-pw {
            position: absolute;
            right: .9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray-400);
            font-size: .85rem;
            padding: .25rem;
            transition: color .15s;
            line-height: 1;
        }

        .toggle-pw:hover { color: var(--gray-700); }

        .input-wrap input[type="password"].has-toggle,
        .input-wrap input[type="text"].has-toggle {
            padding-right: 2.75rem;
        }

        /* ── alerta de erro ── */
        .auth-error {
            display: flex;
            align-items: center;
            gap: .625rem;
            padding: .75rem 1rem;
            background: var(--red-50);
            border: 1px solid var(--red-200);
            border-radius: 10px;
            color: var(--red-500);
            font-size: .875rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            animation: shake .3s var(--ease);
        }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        /* ── botão principal ── */
        .btn-primary-auth {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            width: 100%;
            padding: .875rem 1.25rem;
            font-size: .9375rem;
            font-weight: 700;
            font-family: var(--font);
            color: #fff;
            background: linear-gradient(135deg, #1a56db 0%, #1e429f 100%);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            min-height: 48px;
            transition: transform .15s var(--ease), box-shadow .15s var(--ease), filter .15s;
            box-shadow: 0 4px 14px rgba(26,86,219,.3);
            letter-spacing: .01em;
            margin-top: .25rem;
        }

        .btn-primary-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(26,86,219,.4);
            filter: brightness(1.05);
        }

        .btn-primary-auth:disabled {
            cursor: wait;
            opacity: .85;
            transform: none;
        }

        .btn-primary-auth:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(26,86,219,.25);
        }

        /* ── divider ── */
        .auth-divider {
            display: flex;
            align-items: center;
            gap: .875rem;
            margin: 1.5rem 0;
            color: var(--gray-400);
            font-size: .8rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--gray-200);
        }

        /* ── link para cadastro ── */
        .auth-alt {
            text-align: center;
            font-size: .875rem;
            color: var(--gray-500);
        }

        .auth-alt a {
            color: var(--blue-600);
            font-weight: 600;
            text-decoration: none;
        }

        .auth-alt a:hover { text-decoration: underline; }

        /* ══ ANIMAÇÕES DE REVELAÇÃO — painel da marca ══ */
        @keyframes revealDown {
            from { opacity: 0; transform: translateY(-14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes revealRight {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes revealUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes revealFade {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .brand-logo-row {
            animation: revealDown .5s cubic-bezier(0.4,0,0.2,1) both;
            animation-delay: .1s;
        }
        .brand-headline {
            animation: revealRight .55s cubic-bezier(0.4,0,0.2,1) both;
            animation-delay: .28s;
        }
        .brand-sub {
            animation: revealFade .5s ease both;
            animation-delay: .45s;
        }
        .feature-list li:nth-child(1) {
            animation: revealUp .5s cubic-bezier(0.4,0,0.2,1) both;
            animation-delay: .55s;
        }
        .feature-list li:nth-child(2) {
            animation: revealUp .5s cubic-bezier(0.4,0,0.2,1) both;
            animation-delay: .68s;
        }
        .feature-list li:nth-child(3) {
            animation: revealUp .5s cubic-bezier(0.4,0,0.2,1) both;
            animation-delay: .81s;
        }
        .feature-list li:nth-child(4) {
            animation: revealUp .5s cubic-bezier(0.4,0,0.2,1) both;
            animation-delay: .94s;
        }

        /* ══════════════════════════════════════════
           MOBILE (< 768px): coluna única
        ══════════════════════════════════════════ */
        @media (max-width: 767px) {
            .auth-shell { flex-direction: column; }

            .brand-panel {
                position: static;
                flex: 0 0 auto;
                width: 100%;
                height: auto;
                padding: 1.75rem 1.5rem 1.5rem;
            }

            .brand-logo-row { margin-bottom: 0; }

            .brand-headline,
            .brand-sub,
            .feature-list,
            .brand-bottom { display: none; }

            .form-panel {
                flex: 1 1 auto;
                width: 100%;
                align-items: flex-start;
                padding: 2rem 1.25rem 3rem;
            }

            .form-inner { max-width: 100%; }

            .form-heading { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="auth-shell">

    <!-- ── Painel de marca ────────────────────── -->
    <aside class="brand-panel" aria-hidden="true">
        <div class="brand-top">
            <div class="brand-logo-row">
                <div class="brand-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <span class="brand-name">Prod<span>mais</span></span>
            </div>

            <h2 class="brand-headline">Gestão de produção científica da UMC</h2>
            <p class="brand-sub">Indexe, busque e exporte a produção acadêmica dos programas de pós-graduação da Universidade de Mogi das Cruzes.</p>

            <ul class="feature-list">
                <li class="feature-item">
                    <div class="feature-dot"><i class="fas fa-id-card"></i></div>
                    <span class="feature-text">Importação automática de currículos Lattes</span>
                </li>
                <li class="feature-item">
                    <div class="feature-dot"><i class="fas fa-star"></i></div>
                    <span class="feature-text">Classificação Qualis CAPES integrada</span>
                </li>
                <li class="feature-item">
                    <div class="feature-dot"><i class="fas fa-link"></i></div>
                    <span class="feature-text">Integração com ORCID e OpenAlex</span>
                </li>
                <li class="feature-item">
                    <div class="feature-dot"><i class="fas fa-file-export"></i></div>
                    <span class="feature-text">Exportação em BibTeX, RIS, CSV e XML</span>
                </li>
            </ul>
        </div>

        <div class="brand-bottom">
            <p class="brand-tagline">UNIVERSIDADE DE MOGI DAS CRUZES &mdash; CAPES &mdash; CNPq</p>
        </div>
    </aside>

    <!-- ── Painel de formulário ───────────────── -->
    <main class="form-panel" role="main">
        <div class="form-inner">

            <p class="form-eyebrow">Portal Acadêmico</p>
            <h1 class="form-heading">Bem-vindo de volta</h1>
            <p class="form-desc">Entre com suas credenciais para acessar o sistema Prodmais.</p>

            <?php if ($error): ?>
            <div class="auth-error" role="alert">
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate id="loginForm">

                <!-- E-mail / usuário -->
                <div class="field-group">
                    <div class="field-label">
                        <label for="login-user">E-mail ou usuário</label>
                    </div>
                    <div class="input-wrap">
                        <input type="text"
                               id="login-user"
                               name="user"
                               placeholder="nome@exemplo.com"
                               autocomplete="username"
                               required>
                        <i class="fas fa-at field-icon" aria-hidden="true"></i>
                    </div>
                </div>

                <!-- Senha -->
                <div class="field-group">
                    <div class="field-label">
                        <label for="login-password">Senha</label>
                        <a href="/esqueci-senha.php">Esqueceu a senha?</a>
                    </div>
                    <div class="input-wrap">
                        <input type="password"
                               id="login-password"
                               name="password"
                               class="has-toggle"
                               placeholder="••••••••"
                               autocomplete="current-password"
                               required>
                        <i class="fas fa-lock field-icon" aria-hidden="true"></i>
                        <button type="button"
                                class="toggle-pw"
                                aria-label="Mostrar ou ocultar senha"
                                onclick="togglePw('login-password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary-auth" id="loginSubmitBtn">
                    <i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>
                    Acessar o sistema
                </button>
            </form>
            <script>
                document.getElementById('loginForm').addEventListener('submit', function () {
                    var btn = document.getElementById('loginSubmitBtn');
                    if (btn.disabled) {
                        return;
                    }
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Validando...';
                });
            </script>

            <div class="auth-divider">ou</div>

            <p class="auth-alt">
                Não tem acesso?&ensp;<a href="/registro.php">Solicitar cadastro</a>
            </p>
        </div>
    </main>

</div>

<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
        btn.setAttribute('aria-label', 'Ocultar senha');
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
        btn.setAttribute('aria-label', 'Mostrar senha');
    }
}
</script>
</body>
</html>
        <?php
    }
}
