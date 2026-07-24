<?php
/**
 * PRODMAIS - Wrapper de Login
 * Encaminha para a View de Autenticação na arquitetura modular
 */

require_once __DIR__ . '/../src/UmcFunctions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\View\Pages\Auth\LoginPage;

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirecionar se já autenticado
if (!empty($_SESSION['user_id'])) {
    $papel_atual = $_SESSION['papel'] ?? '';
    $dest_already = in_array($papel_atual, ['admin', 'pesquisador']) ? '/admin.php' : '/dashboard.php';
    header('Location: ' . $dest_already);
    exit;
}

require_once __DIR__ . '/../src/Domain/Security/AuthManager.php';
require_once __DIR__ . '/../src/Infrastructure/Database/MysqlConnectionFactory.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'user',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $pdo = criarConexaoMysql();

        $auth    = new AuthManager($pdo);
        $result  = $auth->login($username, $password);

        if ($result['sucesso']) {
            $primeiroNome = explode(' ', trim($_SESSION['nome_completo'] ?? $username))[0];
            $_SESSION['flash_welcome'] = $primeiroNome;

            $destino = in_array($_SESSION['papel'] ?? '', ['admin', 'pesquisador']) ? '/admin.php' : '/dashboard.php';
            header('Location: ' . $destino);
            exit;
        }

        $error = $result['mensagem'];
    } catch (PDOException $e) {
        error_log('Login DB error: ' . $e->getMessage());
        $error = 'Erro de conexão com o banco de dados. Tente novamente.';
    }
}

LoginPage::display(['error' => $error]);
