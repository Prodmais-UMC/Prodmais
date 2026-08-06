<?php
/**
 * PRODMAIS - Alternância de papel para pré-visualização
 * Restrito a contas marcadas como `conta_sistema` no banco (ex: conta de desenvolvedor).
 * Não altera o papel real do usuário — só um override de sessão pra testar outras visões.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['conta_sistema'])) {
    http_response_code(403);
    exit('Acesso negado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novoPapel = $_POST['papel_preview'] ?? 'real';
    if (in_array($novoPapel, ['admin', 'pesquisador', 'visualizador'], true)) {
        $_SESSION['papel_preview'] = $novoPapel;
    } else {
        unset($_SESSION['papel_preview']);
    }
}

$voltar = '/dashboard.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $refHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    if ($refHost !== false && $refHost === ($_SERVER['HTTP_HOST'] ?? null)) {
        $voltar = $_SERVER['HTTP_REFERER'];
    }
}

header('Location: ' . $voltar);
exit;
