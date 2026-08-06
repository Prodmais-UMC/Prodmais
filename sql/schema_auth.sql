-- =====================================================
-- PRODMAIS UMC - Sistema de Autenticacao e Seguranca
-- Adicionar ao schema.sql existente
-- =====================================================

-- =====================================================
-- TABELA: usuarios_admin
-- Armazena usuarios administradores do sistema
-- =====================================================
DROP TABLE IF EXISTS `usuarios_admin`;
CREATE TABLE `usuarios_admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'BCrypt hash',
    `nome_completo` VARCHAR(255),
    `ultimo_login` TIMESTAMP NULL,
    `tentativas_login` INT DEFAULT 0,
    `bloqueado_ate` TIMESTAMP NULL,
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `status` ENUM('pendente','ativo','inativo') NOT NULL DEFAULT 'ativo' COMMENT 'pendente = aguarda aprovação admin',
    `papel` ENUM('admin','pesquisador','visualizador') NOT NULL DEFAULT 'visualizador',
    `conta_sistema` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Conta protegida (ex: desenvolvedor) — não editável/removível por outros admins via UI',
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: tokens_recuperacao_senha
-- Tokens para recuperacao de senha
-- =====================================================
DROP TABLE IF EXISTS `tokens_recuperacao_senha`;
CREATE TABLE `tokens_recuperacao_senha` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `expira_em` TIMESTAMP NOT NULL,
    `usado` BOOLEAN DEFAULT FALSE,
    `usado_em` TIMESTAMP NULL,
    `ip_solicitacao` VARCHAR(45),
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_admin`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`),
    INDEX `idx_expira` (`expira_em`),
    INDEX `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABELA: log_login
-- Registro de tentativas de login (auditoria)
-- =====================================================
DROP TABLE IF EXISTS `log_login`;
CREATE TABLE `log_login` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NULL,
    `username` VARCHAR(100),
    `sucesso` BOOLEAN NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(500),
    `motivo_falha` VARCHAR(255) NULL,
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_admin`(`id`) ON DELETE SET NULL,
    INDEX `idx_usuario` (`usuario_id`),
    INDEX `idx_sucesso` (`sucesso`),
    INDEX `idx_data` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Nenhum usuario e criado pelo schema.
-- Crie o primeiro admin com: php bin/criar_admin.php
-- (le ADMIN_USERNAME / ADMIN_EMAIL / ADMIN_PASSWORD / ADMIN_NOME de variaveis de ambiente)
-- =====================================================

-- =====================================================
-- PROCEDIMENTO: Limpar tokens expirados (executar periodicamente)
-- =====================================================
DELIMITER //
CREATE PROCEDURE limpar_tokens_expirados()
BEGIN
    DELETE FROM tokens_recuperacao_senha 
    WHERE expira_em < NOW() 
    OR usado = TRUE;
END //
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;
