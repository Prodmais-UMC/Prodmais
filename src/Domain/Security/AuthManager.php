<?php
/**
 * PRODMAIS UMC - Classe de Autenticacao e Seguranca
 * Gerencia login, recuperacao de senha e sessoes seguras
 */

class AuthManager {
    private $db;
    private $max_tentativas = 5;
    private $tempo_bloqueio = 900; // 15 minutos em segundos
    private $token_validade = 3600; // 1 hora em segundos
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
        $this->iniciarSessaoSegura();
    }
    
    /**
     * Iniciar sessao com configuracoes de seguranca
     */
    private function iniciarSessaoSegura() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuracoes de seguranca da sessao
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerar ID da sessao periodicamente (a cada 30 minutos)
            if (!isset($_SESSION['criado_em'])) {
                $_SESSION['criado_em'] = time();
            } elseif (time() - $_SESSION['criado_em'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['criado_em'] = time();
            }
            
            // Timeout de inatividade (2 horas)
            if (isset($_SESSION['ultima_atividade']) && (time() - $_SESSION['ultima_atividade'] > 7200)) {
                $this->logout();
            }
            $_SESSION['ultima_atividade'] = time();
        }
    }
    
    /**
     * Autenticar usuario
     */
    public function login($username, $password) {
        try {
            // Verificar se usuario esta bloqueado
            $stmt = $this->db->prepare("
                SELECT id, username, email, password_hash, tentativas_login, bloqueado_ate, nome_completo, status, papel, conta_sistema
                FROM usuarios_admin
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $this->registrarTentativa(null, $username, false, 'Usuario nao encontrado');
                return ['sucesso' => false, 'mensagem' => 'Usuario ou senha invalidos'];
            }

            // Verificar status da conta
            if ($usuario['status'] === 'pendente') {
                return ['sucesso' => false, 'mensagem' => 'Sua conta aguarda aprovação de um administrador'];
            }
            if ($usuario['status'] === 'inativo') {
                return ['sucesso' => false, 'mensagem' => 'Conta desativada. Entre em contato com o administrador'];
            }

            // Verificar bloqueio
            if ($usuario['bloqueado_ate'] && strtotime($usuario['bloqueado_ate']) > time()) {
                $tempo_restante = ceil((strtotime($usuario['bloqueado_ate']) - time()) / 60);
                $this->registrarTentativa($usuario['id'], $username, false, 'Conta bloqueada');
                return ['sucesso' => false, 'mensagem' => "Conta temporariamente bloqueada. Tente novamente em {$tempo_restante} minutos"];
            }
            
            // Verificar senha
            if (!password_verify($password, $usuario['password_hash'])) {
                $tentativas = $usuario['tentativas_login'] + 1;
                
                // Bloquear apos max tentativas
                if ($tentativas >= $this->max_tentativas) {
                    $bloqueado_ate = date('Y-m-d H:i:s', time() + $this->tempo_bloqueio);
                    $stmt = $this->db->prepare("
                        UPDATE usuarios_admin 
                        SET tentativas_login = ?, bloqueado_ate = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$tentativas, $bloqueado_ate, $usuario['id']]);
                    
                    $this->registrarTentativa($usuario['id'], $username, false, 'Conta bloqueada por excesso de tentativas');
                    return ['sucesso' => false, 'mensagem' => 'Conta bloqueada por excesso de tentativas. Tente novamente em 15 minutos'];
                }
                
                // Incrementar tentativas
                $stmt = $this->db->prepare("UPDATE usuarios_admin SET tentativas_login = ? WHERE id = ?");
                $stmt->execute([$tentativas, $usuario['id']]);
                
                $tentativas_restantes = $this->max_tentativas - $tentativas;
                $this->registrarTentativa($usuario['id'], $username, false, 'Senha incorreta');
                return ['sucesso' => false, 'mensagem' => "Senha incorreta. Restam {$tentativas_restantes} tentativas"];
            }
            
            // Login bem-sucedido
            $stmt = $this->db->prepare("
                UPDATE usuarios_admin 
                SET tentativas_login = 0, bloqueado_ate = NULL, ultimo_login = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$usuario['id']]);
            
            // Configurar sessao
            session_regenerate_id(true);
            $_SESSION['user_id']       = $usuario['id'];
            $_SESSION['username']      = $usuario['username'];
            $_SESSION['user']          = $usuario['username']; // compatibilidade com admin.php
            $_SESSION['nome_completo'] = $usuario['nome_completo'];
            $_SESSION['papel']         = $usuario['papel'];
            $_SESSION['conta_sistema'] = (bool) $usuario['conta_sistema'];
            $_SESSION['criado_em']     = time();
            $_SESSION['ultima_atividade'] = time();
            
            $this->registrarTentativa($usuario['id'], $username, true, null);
            
            return ['sucesso' => true, 'mensagem' => 'Login realizado com sucesso'];
            
        } catch (Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            return ['sucesso' => false, 'mensagem' => 'Erro ao processar login'];
        }
    }
    
    /**
     * Registrar tentativa de login
     */
    private function registrarTentativa($usuario_id, $username, $sucesso, $motivo_falha) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO log_login (usuario_id, username, sucesso, ip_address, user_agent, motivo_falha) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $usuario_id,
                $username,
                $sucesso ? 1 : 0,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $motivo_falha
            ]);
        } catch (Exception $e) {
            error_log("Erro ao registrar tentativa: " . $e->getMessage());
        }
    }
    
    /**
     * Gerar token de recuperacao de senha
     */
    public function solicitarRecuperacaoSenha($email) {
        try {
            // Buscar usuario
            $stmt = $this->db->prepare("SELECT id, username, email, nome_completo FROM usuarios_admin WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                // Por seguranca, retornar sucesso mesmo se email nao existir
                return ['sucesso' => true, 'mensagem' => 'Se o email estiver cadastrado, voce recebera as instrucoes'];
            }
            
            // Gerar token seguro
            $token = bin2hex(random_bytes(32));
            $expira_em = date('Y-m-d H:i:s', time() + $this->token_validade);
            
            // Salvar token
            $stmt = $this->db->prepare("
                INSERT INTO tokens_recuperacao_senha (usuario_id, token, expira_em, ip_solicitacao) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $usuario['id'],
                $token,
                $expira_em,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            
            // Enviar email
            $link = "https://" . $_SERVER['HTTP_HOST'] . "/redefinir-senha.php?token=" . $token;
            $enviado = $this->enviarEmailRecuperacao($usuario['email'], $usuario['nome_completo'], $link);
            
            if ($enviado) {
                return ['sucesso' => true, 'mensagem' => 'Email enviado com instrucoes para recuperacao de senha'];
            } else {
                return ['sucesso' => false, 'mensagem' => 'Erro ao enviar email. Tente novamente'];
            }
            
        } catch (Exception $e) {
            error_log("Erro na recuperacao: " . $e->getMessage());
            return ['sucesso' => false, 'mensagem' => 'Erro ao processar solicitacao'];
        }
    }
    
    /**
     * Enviar email de recuperacao
     */
    private function enviarEmailRecuperacao($email, $nome, $link) {
        $assunto = "Recuperacao de Senha - Prodmais UMC";
        
        $mensagem = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 15px 30px; background: #1e40af; color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Recuperacao de Senha</h1>
                    <p>Sistema Prodmais UMC</p>
                </div>
                <div class='content'>
                    <p>Ola, <strong>{$nome}</strong></p>
                    <p>Recebemos uma solicitacao para redefinir sua senha no sistema Prodmais UMC.</p>
                    <p>Clique no botao abaixo para criar uma nova senha:</p>
                    <p style='text-align: center;'>
                        <a href='{$link}' class='button'>Redefinir Senha</a>
                    </p>
                    <p>Ou copie e cole este link no navegador:</p>
                    <p style='word-break: break-all; background: white; padding: 10px; border-radius: 5px;'>{$link}</p>
                    
                    <div class='warning'>
                        <strong>⚠ Importante:</strong>
                        <ul>
                            <li>Este link expira em 1 hora</li>
                            <li>Se voce nao solicitou esta alteracao, ignore este email</li>
                            <li>Nunca compartilhe este link com outras pessoas</li>
                        </ul>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Universidade de Mogi das Cruzes</p>
                    <p>Este e um email automatico, nao responda</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Prodmais UMC <noreply@umc.br>\r\n";
        
        return mail($email, $assunto, $mensagem, $headers);
    }
    
    /**
     * Validar token de recuperacao
     */
    public function validarToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.id, t.usuario_id, u.username, u.email 
                FROM tokens_recuperacao_senha t
                JOIN usuarios_admin u ON t.usuario_id = u.id
                WHERE t.token = ? AND t.usado = FALSE AND t.expira_em > NOW()
            ");
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao validar token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Redefinir senha
     */
    public function redefinirSenha($token, $nova_senha) {
        try {
            // Validar token
            $dados_token = $this->validarToken($token);
            if (!$dados_token) {
                return ['sucesso' => false, 'mensagem' => 'Token invalido ou expirado'];
            }
            
            // Validar senha forte
            if (strlen($nova_senha) < 8) {
                return ['sucesso' => false, 'mensagem' => 'Senha deve ter no minimo 8 caracteres'];
            }
            
            // Hash da senha
            $password_hash = password_hash($nova_senha, PASSWORD_BCRYPT);
            
            // Atualizar senha
            $stmt = $this->db->prepare("UPDATE usuarios_admin SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $dados_token['usuario_id']]);
            
            // Marcar token como usado
            $stmt = $this->db->prepare("UPDATE tokens_recuperacao_senha SET usado = TRUE, usado_em = NOW() WHERE token = ?");
            $stmt->execute([$token]);
            
            return ['sucesso' => true, 'mensagem' => 'Senha redefinida com sucesso'];
            
        } catch (Exception $e) {
            error_log("Erro ao redefinir senha: " . $e->getMessage());
            return ['sucesso' => false, 'mensagem' => 'Erro ao redefinir senha'];
        }
    }
    
    /**
     * Trocar senha (usuario logado)
     */
    public function trocarSenha($usuario_id, $senha_atual, $nova_senha) {
        try {
            // Buscar usuario
            $stmt = $this->db->prepare("SELECT password_hash FROM usuarios_admin WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                return ['sucesso' => false, 'mensagem' => 'Usuario nao encontrado'];
            }
            
            // Verificar senha atual
            if (!password_verify($senha_atual, $usuario['password_hash'])) {
                return ['sucesso' => false, 'mensagem' => 'Senha atual incorreta'];
            }
            
            // Validar nova senha
            if (strlen($nova_senha) < 8) {
                return ['sucesso' => false, 'mensagem' => 'Nova senha deve ter no minimo 8 caracteres'];
            }
            
            if ($senha_atual === $nova_senha) {
                return ['sucesso' => false, 'mensagem' => 'Nova senha deve ser diferente da atual'];
            }
            
            // Atualizar senha
            $password_hash = password_hash($nova_senha, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE usuarios_admin SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $usuario_id]);
            
            return ['sucesso' => true, 'mensagem' => 'Senha alterada com sucesso'];
            
        } catch (Exception $e) {
            error_log("Erro ao trocar senha: " . $e->getMessage());
            return ['sucesso' => false, 'mensagem' => 'Erro ao trocar senha'];
        }
    }
    
    /**
     * Logout
     */
    public function logout() {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }
    
    /**
     * Verificar se usuario esta autenticado
     */
    public function estaAutenticado() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
    
    /**
     * Obter dados do usuario logado
     */
    public function getUsuarioLogado() {
        if (!$this->estaAutenticado()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'nome_completo' => $_SESSION['nome_completo'] ?? ''
        ];
    }
}
