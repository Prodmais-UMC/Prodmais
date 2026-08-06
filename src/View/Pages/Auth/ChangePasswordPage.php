<?php
session_start();

/**
 * PRODMAIS UMC — Alterar Senha (usuário logado)
 */

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../../../../config/config_umc.php';
require_once __DIR__ . '/../../../../src/Domain/Security/AuthManager.php';
require_once __DIR__ . '/../../../../src/Infrastructure/Database/MysqlConnectionFactory.php';

try {
    $db = criarConexaoMysql();
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$auth        = new AuthManager($db);
$mensagem    = '';
$tipo        = '';
$concluido   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha_atual     = $_POST['senha_atual']     ?? '';
    $nova_senha      = $_POST['nova_senha']      ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if (strlen($nova_senha) < 8) {
        $mensagem = 'A nova senha deve ter pelo menos 8 caracteres.';
        $tipo = 'error';
    } elseif ($nova_senha !== $confirmar_senha) {
        $mensagem = 'As senhas não coincidem.';
        $tipo = 'error';
    } else {
        $resultado = $auth->trocarSenha($_SESSION['user_id'], $senha_atual, $nova_senha);
        $mensagem  = $resultado['mensagem'];
        $tipo      = $resultado['sucesso'] ? 'success' : 'error';
        $concluido = $resultado['sucesso'];
    }
}

$usuario = $auth->getUsuarioLogado();
$nome_exibido = $usuario['nome_completo'] ?? $usuario['username'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title>Alterar Senha — Prodmais UMC</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue-900: #0f1f4b;
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
            --green-400: #34d399;
            --green-500: #10b981;
            --red-50:   #fef2f2;
            --red-200:  #fecaca;
            --font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --ease: cubic-bezier(0.4, 0, 0.2, 1);
        }

        html { overflow-x: clip; }
        body { font-family: var(--font); -webkit-font-smoothing: antialiased; overflow-x: clip; }

        .auth-shell { display: flex; min-height: 100vh; }

        /* ── BRAND PANEL ── */
        .brand-panel {
            flex: 0 0 42%;
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

        .brand-panel::after {
            content: '';
            position: absolute;
            bottom: -80px; right: -80px;
            width: 320px; height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59,130,246,.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .brand-top { position: relative; z-index: 1; }

        .brand-logo-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 3rem;
        }

        .brand-logo-row img {
            height: 40px;
            filter: brightness(0) invert(1);
            opacity: 0.9;
        }

        .brand-logo-text { font-size: 1.25rem; font-weight: 800; color: white; letter-spacing: -0.3px; }
        .brand-logo-text span { color: var(--blue-400); }

        .brand-icon-wrap {
            width: 64px; height: 64px;
            border-radius: 18px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.75rem;
        }

        .brand-icon-wrap i { font-size: 1.75rem; color: var(--blue-400); }

        .brand-headline {
            font-size: clamp(1.6rem, 2.5vw, 2.2rem);
            font-weight: 800;
            color: white;
            line-height: 1.2;
            letter-spacing: -0.5px;
            margin-bottom: 0.75rem;
        }

        .brand-sub {
            font-size: 1rem;
            color: rgba(255,255,255,.55);
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        /* User chip no brand panel */
        .brand-user-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 999px;
            padding: 0.5rem 1rem 0.5rem 0.625rem;
            margin-bottom: 2rem;
        }

        .brand-user-chip .avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue-500), var(--blue-400));
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-user-chip .avatar i { font-size: 0.75rem; color: white; }
        .brand-user-chip span { font-size: 0.825rem; font-weight: 500; color: rgba(255,255,255,.8); }

        .brand-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .brand-features li { display: flex; align-items: flex-start; gap: 0.75rem; }

        .brand-features li .fi {
            width: 22px; height: 22px;
            border-radius: 50%;
            background: rgba(52,211,153,.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .brand-features li .fi i { font-size: 0.6rem; color: var(--green-400); }
        .brand-features li span { font-size: 0.925rem; color: rgba(255,255,255,.75); line-height: 1.5; }

        .brand-bottom { position: relative; z-index: 1; }

        .brand-quote {
            font-size: 0.8rem;
            color: rgba(255,255,255,.35);
            border-top: 1px solid rgba(255,255,255,.08);
            padding-top: 1.5rem;
            font-style: italic;
        }

        /* ── FORM PANEL ── */
        .form-panel {
            flex: 1;
            background: white;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 2.5rem;
        }

        .form-inner { max-width: 440px; width: 100%; margin: 0 auto; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-500);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 2.5rem;
            transition: color 200ms var(--ease);
        }

        .back-link:hover { color: var(--blue-600); }

        .form-heading {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--gray-900);
            letter-spacing: -0.5px;
            margin-bottom: 0.5rem;
        }

        .form-subheading {
            font-size: 0.925rem;
            color: var(--gray-500);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        /* Field */
        .field { margin-bottom: 1.25rem; }

        .field label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray-700);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .field-wrap { position: relative; }

        .field input {
            width: 100%;
            padding: 0.875rem 3rem 0.875rem 1rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            font-family: var(--font);
            font-size: 0.95rem;
            color: var(--gray-900);
            background: var(--gray-50);
            transition: border-color 200ms var(--ease), box-shadow 200ms var(--ease), background 200ms var(--ease);
            outline: none;
        }

        .field input:focus {
            border-color: var(--blue-600);
            background: white;
            box-shadow: 0 0 0 3px rgba(26,86,219,.08);
        }

        .field input::placeholder { color: var(--gray-400); }

        .toggle-eye {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray-400);
            padding: 4px;
            line-height: 1;
            transition: color 150ms var(--ease);
        }

        .toggle-eye:hover { color: var(--blue-600); }

        .strength-wrap {
            margin-top: 0.5rem;
            height: 4px;
            border-radius: 2px;
            background: var(--gray-100);
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            border-radius: 2px;
            width: 0;
            transition: width 300ms var(--ease), background 300ms var(--ease);
        }

        .strength-label {
            font-size: 0.75rem;
            margin-top: 0.375rem;
            color: var(--gray-400);
            font-weight: 500;
            min-height: 1rem;
        }

        .match-indicator {
            font-size: 0.75rem;
            margin-top: 0.375rem;
            font-weight: 500;
            min-height: 1rem;
        }

        /* Divider */
        .section-divider {
            height: 1px;
            background: var(--gray-100);
            margin: 1.5rem 0;
        }

        /* Feedback */
        .msg {
            border-radius: 10px;
            padding: 1rem 1.25rem;
            font-size: 0.9rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .msg-error   { background: var(--red-50);  border: 1px solid var(--red-200);   color: #991b1b; }
        .msg-success { background: #f0fdf4;         border: 1px solid #bbf7d0;          color: #166534; }
        .msg i { margin-top: 1px; flex-shrink: 0; }

        /* Submit */
        .btn-submit {
            width: 100%;
            padding: 0.9rem 1.5rem;
            background: linear-gradient(135deg, var(--blue-600), var(--blue-700, #1e429f));
            color: white;
            border: none;
            border-radius: 10px;
            font-family: var(--font);
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 220ms var(--ease);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 14px rgba(26,86,219,.3);
            margin-top: 1.5rem;
        }

        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(26,86,219,.4); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        /* Success state */
        .success-state { text-align: center; padding: 2rem 0; }

        .success-icon {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--green-500), #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 24px rgba(16,185,129,.3);
            animation: scaleIn 400ms var(--ease);
        }

        .success-icon i { font-size: 2rem; color: white; }

        .success-state h3 {
            font-size: 1.375rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }

        .success-state p {
            color: var(--gray-500);
            font-size: 0.925rem;
            line-height: 1.6;
            max-width: 340px;
            margin: 0 auto 2rem;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2rem;
            background: linear-gradient(135deg, var(--blue-600), var(--blue-700, #1e429f));
            color: white;
            border: none;
            border-radius: 10px;
            font-family: var(--font);
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 220ms var(--ease);
            box-shadow: 0 4px 14px rgba(26,86,219,.3);
        }

        .btn-action:hover { color: white; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(26,86,219,.4); }

        @keyframes scaleIn {
            from { transform: scale(0.6); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }

        @media (max-width: 767px) {
            .auth-shell { flex-direction: column; }
            .brand-panel { position: static; height: auto; padding: 2rem 1.5rem; flex: none; }
            .brand-headline { font-size: 1.4rem; }
            .brand-sub, .brand-features, .brand-bottom { display: none; }
            .form-panel { padding: 2rem 1.25rem; }
        }
    </style>
</head>
<body>

<div class="auth-shell">

    <!-- ── BRAND PANEL ── -->
    <aside class="brand-panel">
        <div class="brand-top">
            <div class="brand-logo-row">
                <img src="/img/umc-logo.png" alt="UMC" onerror="this.style.display='none'">
                <span class="brand-logo-text">Prod<span>mais</span></span>
            </div>

            <div class="brand-user-chip">
                <div class="avatar"><i class="fas fa-user"></i></div>
                <span><?= htmlspecialchars($nome_exibido) ?></span>
            </div>

            <div class="brand-icon-wrap">
                <i class="fas fa-key"></i>
            </div>

            <h1 class="brand-headline">Mantenha sua<br>conta segura</h1>
            <p class="brand-sub">
                Trocar a senha regularmente é uma boa prática.
                Escolha algo único que só você saiba.
            </p>

            <ul class="brand-features">
                <li>
                    <div class="fi"><i class="fas fa-check"></i></div>
                    <span>Deve ser diferente da senha atual</span>
                </li>
                <li>
                    <div class="fi"><i class="fas fa-check"></i></div>
                    <span>Armazenada com hash bcrypt seguro</span>
                </li>
                <li>
                    <div class="fi"><i class="fas fa-check"></i></div>
                    <span>Todas as suas sessões continuam ativas</span>
                </li>
            </ul>
        </div>

        <div class="brand-bottom">
            <p class="brand-quote">
                Universidade de Mogi das Cruzes &mdash; Sistema de Gestão Científica
            </p>
        </div>
    </aside>

    <!-- ── FORM PANEL ── -->
    <main class="form-panel">
        <div class="form-inner">

            <a href="/admin.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Voltar ao painel
            </a>

            <?php if ($concluido): ?>

            <div class="success-state">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3>Senha alterada!</h3>
                <p>Sua senha foi atualizada com sucesso. Use-a no próximo login.</p>
                <a href="/admin.php" class="btn-action">
                    <i class="fas fa-th-large"></i>
                    Ir ao painel
                </a>
            </div>

            <?php else: ?>

            <h2 class="form-heading">Alterar senha</h2>
            <p class="form-subheading">
                Confirme a senha atual e escolha uma nova para continuar.
            </p>

            <?php if ($mensagem): ?>
            <div class="msg msg-<?= $tipo === 'success' ? 'success' : 'error' ?>" role="alert">
                <i class="fas fa-<?= $tipo === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <span><?= htmlspecialchars($mensagem) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" id="changeForm" novalidate>
                <div class="field">
                    <label for="senha_atual">Senha atual</label>
                    <div class="field-wrap">
                        <input type="password" id="senha_atual" name="senha_atual"
                               placeholder="Sua senha atual"
                               required autocomplete="current-password">
                        <button type="button" class="toggle-eye" aria-label="Mostrar senha atual"
                                onclick="togglePwd('senha_atual', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div class="field">
                    <label for="nova_senha">Nova senha</label>
                    <div class="field-wrap">
                        <input type="password" id="nova_senha" name="nova_senha"
                               placeholder="Mínimo 8 caracteres"
                               required minlength="8" autocomplete="new-password">
                        <button type="button" class="toggle-eye" aria-label="Mostrar nova senha"
                                onclick="togglePwd('nova_senha', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="strength-wrap">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>

                <div class="field">
                    <label for="confirmar_senha">Confirmar nova senha</label>
                    <div class="field-wrap">
                        <input type="password" id="confirmar_senha" name="confirmar_senha"
                               placeholder="Repita a nova senha"
                               required minlength="8" autocomplete="new-password">
                        <button type="button" class="toggle-eye" aria-label="Mostrar confirmação"
                                onclick="togglePwd('confirmar_senha', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="match-indicator" id="matchIndicator"></div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-lock"></i>
                    Salvar nova senha
                </button>
            </form>

            <?php endif; ?>

        </div>
    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    icon.classList.toggle('fa-eye',       !isHidden);
    icon.classList.toggle('fa-eye-slash',  isHidden);
}

const novaSenha      = document.getElementById('nova_senha');
const confirmarSenha = document.getElementById('confirmar_senha');
const strengthBar    = document.getElementById('strengthBar');
const strengthLabel  = document.getElementById('strengthLabel');
const matchIndicator = document.getElementById('matchIndicator');

const levels = [
    { color: '',        label: '' },
    { color: '#ef4444', label: 'Muito fraca' },
    { color: '#f59e0b', label: 'Fraca' },
    { color: '#3b82f6', label: 'Boa' },
    { color: '#10b981', label: 'Forte' },
];

function calcStrength(v) {
    let s = 0;
    if (v.length >= 8) s++;
    if (v.match(/[a-z]/) && v.match(/[A-Z]/)) s++;
    if (v.match(/[0-9]/)) s++;
    if (v.match(/[^a-zA-Z0-9]/)) s++;
    return s;
}

novaSenha?.addEventListener('input', () => {
    const v = novaSenha.value;
    const s = v ? calcStrength(v) : 0;
    const lv = levels[s] || levels[0];
    strengthBar.style.width = s ? (s * 25) + '%' : '0';
    strengthBar.style.background = lv.color;
    strengthLabel.textContent = lv.label;
    strengthLabel.style.color = lv.color || '#94a3b8';
    checkMatch();
});

confirmarSenha?.addEventListener('input', checkMatch);

function checkMatch() {
    if (!confirmarSenha.value) { matchIndicator.innerHTML = ''; return; }
    const match = novaSenha.value === confirmarSenha.value;
    matchIndicator.innerHTML = match
        ? '<i class="fas fa-circle-check" aria-hidden="true"></i> Senhas coincidem'
        : '<i class="fas fa-circle-xmark" aria-hidden="true"></i> Senhas não coincidem';
    matchIndicator.style.color = match ? '#10b981' : '#ef4444';
}

document.getElementById('changeForm')?.addEventListener('submit', function(e) {
    if (novaSenha.value !== confirmarSenha.value) {
        e.preventDefault();
        matchIndicator.innerHTML = '<i class="fas fa-circle-xmark" aria-hidden="true"></i> Senhas não coincidem';
        matchIndicator.style.color = '#ef4444';
        confirmarSenha.focus();
    }
});
</script>
</body>
</html>
