<?php
/**
 * PRODMAIS UMC — Cadastro de Usuário
 * Redesign completo: split-screen, mesma identidade do login
 */

$mensagem = '';
$tipo_mensagem = '';
$cadastrado = false;
$dados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'nome_completo' => filter_input(INPUT_POST, 'nome_completo', FILTER_SANITIZE_SPECIAL_CHARS),
        'email'         => filter_input(INPUT_POST, 'email',         FILTER_SANITIZE_EMAIL),
        'username'      => filter_input(INPUT_POST, 'username',      FILTER_SANITIZE_SPECIAL_CHARS),
        'senha'         => $_POST['senha']      ?? '',
        'senha_conf'    => $_POST['senha_conf'] ?? '',
        'papel'         => filter_input(INPUT_POST, 'papel', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'visualizador',
    ];

    if (empty($dados['nome_completo']) || empty($dados['email']) || empty($dados['username']) || empty($dados['senha'])) {
        $mensagem = 'Preencha todos os campos obrigatórios.';
        $tipo_mensagem = 'error';
    } elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Endereço de e-mail inválido.';
        $tipo_mensagem = 'error';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $dados['username'])) {
        $mensagem = 'Nome de usuário inválido. Use apenas letras, números, ponto, traço e underscore.';
        $tipo_mensagem = 'error';
    } elseif ($dados['senha'] !== $dados['senha_conf']) {
        $mensagem = 'As senhas não conferem.';
        $tipo_mensagem = 'error';
    } elseif (strlen($dados['senha']) < 8) {
        $mensagem = 'A senha deve ter pelo menos 8 caracteres.';
        $tipo_mensagem = 'error';
    } elseif (!in_array($dados['papel'], ['pesquisador', 'visualizador'])) {
        $mensagem = 'Perfil de acesso inválido.';
        $tipo_mensagem = 'error';
    } else {
        require_once __DIR__ . '/../src/Infrastructure/Database/MysqlConnectionFactory.php';

        try {
            $db = criarConexaoMysql();

            $stmt = $db->prepare("SELECT id FROM usuarios_admin WHERE username = ? OR email = ?");
            $stmt->execute([$dados['username'], $dados['email']]);
            if ($stmt->fetch()) {
                $mensagem = 'Usuário ou e-mail já cadastrado no sistema.';
                $tipo_mensagem = 'error';
            } else {
                $hash = password_hash($dados['senha'], PASSWORD_BCRYPT);
                $stmt = $db->prepare(
                    "INSERT INTO usuarios_admin (username, email, password_hash, nome_completo, status, papel)
                     VALUES (?, ?, ?, ?, 'pendente', ?)"
                );
                $stmt->execute([$dados['username'], $dados['email'], $hash, $dados['nome_completo'], $dados['papel']]);
                $cadastrado = true;
                $mensagem = 'Cadastro enviado! Um administrador irá aprovar o seu acesso em breve.';
                $tipo_mensagem = 'success';
            }
        } catch (PDOException $e) {
            error_log("Erro no cadastro: " . $e->getMessage());
            $mensagem = 'Erro ao processar o cadastro. Tente novamente.';
            $tipo_mensagem = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/img/umc-favicon.png">
    <title>Solicitar Acesso — Prodmais UMC</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue-900: #0f1f4b;
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
            --green-600: #16a34a;
            --green-50:  #f0fdf4;
            --green-200: #bbf7d0;
            --red-500:   #ef4444;
            --red-50:    #fef2f2;
            --red-200:   #fecaca;
            --font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --ease: cubic-bezier(0.4, 0, 0.2, 1);
        }

        html {
            overflow-x: clip; /* clip não cria scroll container — preserva position:sticky */
        }

        body {
            min-height: 100%;
            width: 100%;
            overflow-x: clip;
            font-family: var(--font);
            -webkit-font-smoothing: antialiased;
        }

        /* ══════════════════════════════════════════
           LAYOUT SPLIT (espelhado: marca à direita)
        ══════════════════════════════════════════ */
        .auth-shell {
            display: flex;
            min-height: 100vh;
            width: 100%;
            /* SEM overflow:hidden aqui — quebraria position:sticky do brand-panel */
        }

        /* ─── PAINEL ESQUERDO (formulário) ────── */
        .form-panel {
            flex: 1;
            min-width: 0;
            background: #fff;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 3rem 1.5rem 3rem;
            overflow-y: auto;
        }

        .form-inner {
            width: 100%;
            max-width: 440px;
            padding-top: 1rem;
            animation: slideUp .45s var(--ease) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

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

        /* ── grupos de campos ── */
        .field-group { margin-bottom: 1.1rem; }

        .field-label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--gray-700);
            letter-spacing: .01em;
            margin-bottom: .4rem;
        }

        .field-hint {
            display: block;
            font-size: .73rem;
            color: var(--gray-400);
            margin-top: .25rem;
        }

        .input-wrap { position: relative; }

        .input-wrap .field-icon {
            position: absolute;
            left: .9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: .82rem;
            pointer-events: none;
            transition: color .15s;
        }

        .input-wrap input,
        .input-wrap select {
            width: 100%;
            padding: .72rem .9rem .72rem 2.45rem;
            font-size: .9375rem;
            font-family: var(--font);
            color: var(--gray-900);
            background: var(--gray-50);
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
            appearance: none;
        }

        .input-wrap select {
            padding-right: 2.25rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%2394a3b8' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .9rem center;
        }

        .input-wrap input::placeholder { color: var(--gray-400); }

        .input-wrap input:focus,
        .input-wrap select:focus {
            background: #fff;
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3.5px rgba(26,86,219,.1);
        }

        .input-wrap:focus-within .field-icon { color: var(--blue-600); }

        /* toggle senha */
        .toggle-pw {
            position: absolute;
            right: .9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray-400);
            font-size: .82rem;
            padding: .25rem;
            transition: color .15s;
            line-height: 1;
        }
        .toggle-pw:hover { color: var(--gray-700); }

        .input-wrap input.has-toggle { padding-right: 2.75rem; }

        /* grid 2 colunas para senhas */
        .pw-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .875rem;
            margin-bottom: 1.1rem;
        }

        @media (max-width: 480px) { .pw-grid { grid-template-columns: 1fr; } }

        /* ── strength bar ── */
        .pw-strength {
            height: 4px;
            border-radius: 999px;
            background: var(--gray-200);
            margin-top: .5rem;
            overflow: hidden;
        }

        .pw-strength-fill {
            height: 100%;
            border-radius: 999px;
            transition: width .3s var(--ease), background .3s;
            width: 0%;
        }

        /* ── role cards ── */
        .role-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
            margin-bottom: 1.25rem;
        }

        .role-card {
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            padding: 1rem .875rem;
            cursor: pointer;
            transition: border-color .15s, background .15s, box-shadow .15s;
            position: relative;
        }

        .role-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .role-card:hover {
            border-color: var(--blue-500);
            background: rgba(59,130,246,.04);
        }

        .role-card.selected {
            border-color: var(--blue-600);
            background: rgba(26,86,219,.05);
            box-shadow: 0 0 0 3px rgba(26,86,219,.08);
        }

        .role-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            color: var(--gray-500);
            margin-bottom: .625rem;
            transition: background .15s, color .15s;
        }

        .role-card.selected .role-icon {
            background: rgba(26,86,219,.12);
            color: var(--blue-600);
        }

        .role-name {
            font-size: .875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: .2rem;
        }

        .role-desc {
            font-size: .73rem;
            color: var(--gray-500);
            line-height: 1.4;
        }

        /* ── alertas ── */
        .auth-alert {
            display: flex;
            align-items: flex-start;
            gap: .625rem;
            padding: .875rem 1rem;
            border-radius: 10px;
            font-size: .875rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .auth-alert.error {
            background: var(--red-50);
            border: 1px solid var(--red-200);
            color: var(--red-500);
            animation: shake .3s var(--ease);
        }

        .auth-alert.success {
            background: var(--green-50);
            border: 1px solid var(--green-200);
            color: var(--green-600);
        }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        /* ── estado de sucesso ── */
        .success-state {
            text-align: center;
            padding: 2rem 0;
        }

        .success-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--green-50);
            border: 2px solid var(--green-200);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--green-600);
            margin: 0 auto 1.25rem;
            animation: pop .4s var(--ease);
        }

        @keyframes pop {
            0% { transform: scale(.7); opacity: 0; }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .success-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: .5rem;
        }

        .success-sub {
            font-size: .9rem;
            color: var(--gray-500);
            max-width: 320px;
            margin: 0 auto 2rem;
            line-height: 1.6;
        }

        /* ── botão ── */
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
            margin-top: .5rem;
        }

        .btn-primary-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(26,86,219,.4);
            filter: brightness(1.05);
        }

        .btn-primary-auth:active { transform: translateY(0); }

        .btn-outline-auth {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            width: 100%;
            padding: .875rem 1.25rem;
            font-size: .9375rem;
            font-weight: 600;
            font-family: var(--font);
            color: var(--blue-600);
            background: transparent;
            border: 2px solid var(--blue-600);
            border-radius: 10px;
            cursor: pointer;
            min-height: 48px;
            text-decoration: none;
            transition: background .15s, color .15s;
            letter-spacing: .01em;
            margin-top: .75rem;
        }

        .btn-outline-auth:hover {
            background: rgba(26,86,219,.06);
            color: var(--blue-600);
        }

        /* ── divider + alt ── */
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

        /* ─── PAINEL DIREITO (marca) — sticky ─── */
        .brand-panel {
            flex: 0 0 38%;
            min-width: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            align-self: flex-start;
            background:
                radial-gradient(ellipse at 30% 20%, rgba(59,130,246,.18) 0%, transparent 55%),
                radial-gradient(ellipse at 80% 75%, rgba(30,64,175,.2) 0%, transparent 55%),
                linear-gradient(160deg, var(--blue-900) 0%, #0d1b4a 50%, #0a1535 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 2.5rem;
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
            top: -100px; right: -100px;
            width: 340px; height: 340px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59,130,246,.13) 0%, transparent 70%);
            pointer-events: none;
        }

        .brand-content { position: relative; z-index: 1; }

        .brand-logo-row {
            display: flex;
            align-items: center;
            gap: .875rem;
            margin-bottom: 2.5rem;
        }

        .brand-icon {
            width: 46px; height: 46px;
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
            letter-spacing: -.03em;
        }

        .brand-name span { color: var(--blue-400); }

        .brand-headline {
            font-size: clamp(1.4rem, 2.5vw, 1.9rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.25;
            letter-spacing: -.03em;
            margin-bottom: .875rem;
        }

        .brand-sub {
            font-size: .875rem;
            color: rgba(255,255,255,.5);
            line-height: 1.65;
            margin-bottom: 2rem;
        }

        /* steps */
        .step-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .step-num {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: rgba(59,130,246,.2);
            border: 1px solid rgba(59,130,246,.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 800;
            color: var(--blue-400);
            flex-shrink: 0;
            margin-top: .1rem;
        }

        .step-text {
            font-size: .875rem;
            color: rgba(255,255,255,.72);
            line-height: 1.5;
        }

        .step-text strong {
            display: block;
            color: rgba(255,255,255,.9);
            font-weight: 600;
            margin-bottom: .15rem;
        }

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

        .step-item:nth-child(1) {
            animation: revealUp .5s cubic-bezier(0.4,0,0.2,1) both;
            animation-delay: .58s;
        }

        .step-item:nth-child(2) {
            animation: revealUp .5s cubic-bezier(0.4,0,0.2,1) both;
            animation-delay: .72s;
        }

        .step-item:nth-child(3) {
            animation: revealUp .5s cubic-bezier(0.4,0,0.2,1) both;
            animation-delay: .86s;
        }

        /* ══ MOBILE ══ */
        @media (max-width: 767px) {
            .auth-shell { flex-direction: column-reverse; }

            .brand-panel {
                position: static;
                flex: 0 0 auto;
                width: 100%;
                height: auto;
                padding: 1.75rem 1.5rem;
                justify-content: flex-start;
            }

            .brand-headline,
            .brand-sub,
            .step-list { display: none; }

            .brand-logo-row { margin-bottom: 0; }

            .form-panel {
                padding: 2rem 1.25rem 3rem;
                align-items: flex-start;
            }

            .form-inner { padding-top: 0; max-width: 100%; }
            .form-heading { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="auth-shell">

    <!-- ── Painel de formulário (esquerda) ───── -->
    <main class="form-panel" role="main">
        <div class="form-inner">

            <?php if ($cadastrado): ?>
            <!-- Estado de sucesso -->
            <div class="success-state" role="status" aria-live="polite">
                <div class="success-circle" aria-hidden="true">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="success-title">Pedido enviado!</h1>
                <p class="success-sub">
                    Seu cadastro foi recebido e está aguardando aprovação de um administrador.
                    Você receberá um e-mail quando o acesso for liberado.
                </p>
                <a href="/login.php" class="btn-primary-auth">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    Voltar para o login
                </a>
            </div>

            <?php else: ?>
            <!-- Formulário -->
            <p class="form-eyebrow">Novo usuário</p>
            <h1 class="form-heading">Solicitar acesso</h1>
            <p class="form-desc">Preencha os dados abaixo. Um administrador aprovará o seu acesso.</p>

            <?php if (!empty($mensagem) && $tipo_mensagem === 'error'): ?>
            <div class="auth-alert error" role="alert">
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($mensagem); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate id="registerForm">

                <!-- Nome completo -->
                <div class="field-group">
                    <label class="field-label" for="nome_completo">Nome completo <span style="color:var(--red-500)">*</span></label>
                    <div class="input-wrap">
                        <input type="text"
                               id="nome_completo"
                               name="nome_completo"
                               placeholder="Nome completo"
                               autocomplete="name"
                               value="<?php echo htmlspecialchars($dados['nome_completo'] ?? ''); ?>"
                               required>
                        <i class="fas fa-user field-icon" aria-hidden="true"></i>
                    </div>
                </div>

                <!-- E-mail -->
                <div class="field-group">
                    <label class="field-label" for="email">E-mail institucional <span style="color:var(--red-500)">*</span></label>
                    <div class="input-wrap">
                        <input type="email"
                               id="email"
                               name="email"
                               placeholder="nome@exemplo.com"
                               autocomplete="email"
                               value="<?php echo htmlspecialchars($dados['email'] ?? ''); ?>"
                               required>
                        <i class="fas fa-envelope field-icon" aria-hidden="true"></i>
                    </div>
                </div>

                <!-- Usuário (login) -->
                <div class="field-group">
                    <label class="field-label" for="username">Nome de usuário <span style="color:var(--red-500)">*</span></label>
                    <div class="input-wrap">
                        <input type="text"
                               id="username"
                               name="username"
                               placeholder="nome.usuario"
                               autocomplete="username"
                               pattern="[a-zA-Z0-9._-]+"
                               maxlength="100"
                               value="<?php echo htmlspecialchars($dados['username'] ?? ''); ?>"
                               required>
                        <i class="fas fa-at field-icon" aria-hidden="true"></i>
                    </div>
                    <span class="field-hint">Letras, números, ponto, traço e underscore.</span>
                </div>

                <!-- Senha + confirmação -->
                <div class="pw-grid">
                    <div class="field-group" style="margin-bottom:0;">
                        <label class="field-label" for="senha">Senha <span style="color:var(--red-500)">*</span></label>
                        <div class="input-wrap">
                            <input type="password"
                                   id="senha"
                                   name="senha"
                                   class="has-toggle"
                                   placeholder="Mín. 8 caracteres"
                                   autocomplete="new-password"
                                   required
                                   oninput="checkStrength(this.value)">
                            <i class="fas fa-lock field-icon" aria-hidden="true"></i>
                            <button type="button" class="toggle-pw" aria-label="Mostrar senha"
                                    onclick="togglePw('senha', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="pw-strength" role="progressbar" aria-label="Força da senha" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" id="pwBar">
                            <div class="pw-strength-fill" id="pwFill"></div>
                        </div>
                    </div>

                    <div class="field-group" style="margin-bottom:0;">
                        <label class="field-label" for="senha_conf">Confirmar senha <span style="color:var(--red-500)">*</span></label>
                        <div class="input-wrap">
                            <input type="password"
                                   id="senha_conf"
                                   name="senha_conf"
                                   class="has-toggle"
                                   placeholder="Repita a senha"
                                   autocomplete="new-password"
                                   required>
                            <i class="fas fa-lock field-icon" aria-hidden="true"></i>
                            <button type="button" class="toggle-pw" aria-label="Mostrar confirmação"
                                    onclick="togglePw('senha_conf', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Perfil: role cards -->
                <div style="margin-bottom:.4rem;">
                    <label class="field-label" style="display:block;margin-bottom:.75rem;">
                        Perfil de acesso <span style="color:var(--red-500)">*</span>
                    </label>
                    <div class="role-grid" id="roleGrid">

                        <label class="role-card <?php echo (($dados['papel'] ?? 'visualizador') === 'visualizador') ? 'selected' : ''; ?>"
                               for="papel_vis">
                            <input type="radio" id="papel_vis" name="papel" value="visualizador"
                                   <?php echo (($dados['papel'] ?? 'visualizador') === 'visualizador') ? 'checked' : ''; ?>>
                            <div class="role-icon"><i class="fas fa-eye" aria-hidden="true"></i></div>
                            <div class="role-name">Visualizador</div>
                            <div class="role-desc">Apenas consulta e exportação de produções.</div>
                        </label>

                        <label class="role-card <?php echo (($dados['papel'] ?? '') === 'pesquisador') ? 'selected' : ''; ?>"
                               for="papel_pesq">
                            <input type="radio" id="papel_pesq" name="papel" value="pesquisador"
                                   <?php echo (($dados['papel'] ?? '') === 'pesquisador') ? 'checked' : ''; ?>>
                            <div class="role-icon"><i class="fas fa-flask" aria-hidden="true"></i></div>
                            <div class="role-name">Pesquisador</div>
                            <div class="role-desc">Importa currículos e gerencia produções.</div>
                        </label>

                    </div>
                </div>

                <!-- Input oculto para compatibilidade -->
                <input type="hidden" name="papel" id="papelHidden"
                       value="<?php echo htmlspecialchars($dados['papel'] ?? 'visualizador'); ?>">

                <button type="submit" class="btn-primary-auth">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                    Enviar solicitação
                </button>
            </form>

            <div class="auth-divider">ou</div>
            <p class="auth-alt">Já tem acesso? <a href="/login.php">Fazer login</a></p>
            <?php endif; ?>

        </div>
    </main>

    <!-- ── Painel de marca (direita) ─────────── -->
    <aside class="brand-panel" aria-hidden="true">
        <div class="brand-content">
            <div class="brand-logo-row">
                <div class="brand-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <span class="brand-name">Prod<span>mais</span></span>
            </div>

            <h2 class="brand-headline">Como funciona o processo de acesso</h2>
            <p class="brand-sub">O acesso é controlado para garantir a integridade dos dados acadêmicos da UMC.</p>

            <ol class="step-list">
                <li class="step-item">
                    <div class="step-num">1</div>
                    <div class="step-text">
                        <strong>Preencha o formulário</strong>
                        Informe seus dados institucionais e escolha seu perfil de acesso.
                    </div>
                </li>
                <li class="step-item">
                    <div class="step-num">2</div>
                    <div class="step-text">
                        <strong>Aguarde a aprovação</strong>
                        Um administrador revisará seu pedido e ativará o acesso em breve.
                    </div>
                </li>
                <li class="step-item">
                    <div class="step-num">3</div>
                    <div class="step-text">
                        <strong>Acesse o sistema</strong>
                        Com o acesso aprovado, entre com suas credenciais no portal.
                    </div>
                </li>
            </ol>
        </div>
    </aside>

</div>

<script>
// ── Toggle visibilidade senha ──
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
        btn.setAttribute('aria-label', 'Ocultar ' + (id === 'senha' ? 'senha' : 'confirmação'));
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
        btn.setAttribute('aria-label', 'Mostrar ' + (id === 'senha' ? 'senha' : 'confirmação'));
    }
}

// ── Strength bar ──
function checkStrength(value) {
    const fill = document.getElementById('pwFill');
    const bar  = document.getElementById('pwBar');
    let score  = 0;
    if (value.length >= 8)  score++;
    if (/[A-Z]/.test(value)) score++;
    if (/[0-9]/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    const widths = ['0%', '25%', '50%', '75%', '100%'];
    const colors = ['', '#ef4444', '#f59e0b', '#3b82f6', '#16a34a'];
    fill.style.width  = widths[score];
    fill.style.background = colors[score] || 'transparent';
    bar.setAttribute('aria-valuenow', score * 25);
}

// ── Role cards ──
const roleCards  = document.querySelectorAll('.role-card');
const papelHidden = document.getElementById('papelHidden');

roleCards.forEach(card => {
    card.addEventListener('click', () => {
        roleCards.forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        const radio = card.querySelector('input[type="radio"]');
        radio.checked = true;
        if (papelHidden) papelHidden.value = radio.value;
    });
});
</script>
</body>
</html>
