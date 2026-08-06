-- =====================================================
-- PRODMAIS UMC - Base de classificação Qualis CAPES
-- Tabela de referência ISSN -> estrato Qualis, carregada
-- manualmente via upload de CSV no painel admin
-- (Conformidade LGPD -> Base Qualis)
-- =====================================================
CREATE TABLE IF NOT EXISTS `qualis_capes` (
    `issn` VARCHAR(20) NOT NULL,
    `area_avaliacao` VARCHAR(150) NOT NULL DEFAULT '',
    `estrato` VARCHAR(5) NOT NULL,
    `titulo_periodico` VARCHAR(255) NULL,
    `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`issn`, `area_avaliacao`),
    INDEX `idx_issn` (`issn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
