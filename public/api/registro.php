<?php
/**
 * PRODMAIS UMC - API de Cadastro de Usuário
 * Aceita POST com JSON. Retorna JSON.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    // Tentar como form-data
    $body = $_POST;
}

$nome_completo = filter_var($body['nome_completo'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
$email         = filter_var($body['email'] ?? '', FILTER_SANITIZE_EMAIL);
$username      = filter_var($body['username'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
$senha         = $body['senha'] ?? '';
$papel         = filter_var($body['papel'] ?? 'visualizador', FILTER_SANITIZE_SPECIAL_CHARS);

// Validações
if (empty($nome_completo) || empty($email) || empty($username) || empty($senha)) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Campos obrigatórios ausentes']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'E-mail inválido']);
    exit;
}

if (!preg_match('/@umc\.br$/i', $email)) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Cadastro permitido apenas com e-mail institucional (@umc.br)']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Username inválido — use apenas letras, números, ponto, traço e underscore']);
    exit;
}

if (strlen($senha) < 8) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Senha deve ter no mínimo 8 caracteres']);
    exit;
}

if (!in_array($papel, ['pesquisador', 'visualizador'])) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Perfil inválido']);
    exit;
}

require_once __DIR__ . '/../../src/Infrastructure/Database/MysqlConnectionFactory.php';

try {
    $db = criarConexaoMysql();

    // Verificar duplicatas
    $stmt = $db->prepare("SELECT id FROM usuarios_admin WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário ou e-mail já cadastrado']);
        exit;
    }

    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $stmt = $db->prepare("
        INSERT INTO usuarios_admin (username, email, password_hash, nome_completo, status, papel)
        VALUES (?, ?, ?, ?, 'pendente', ?)
    ");
    $stmt->execute([$username, $email, $hash, $nome_completo, $papel]);

    http_response_code(201);
    echo json_encode([
        'sucesso'  => true,
        'mensagem' => 'Cadastro realizado com sucesso. Aguarde a aprovação de um administrador.',
    ]);

} catch (PDOException $e) {
    error_log("Erro na API de cadastro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno. Tente novamente.']);
}
