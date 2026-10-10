-- ============================================================
--  Stage 5.1 (08/10/2026) — Locais de trabalho das vagas
--
--  Migração ADITIVA: cria a tabela `empresa_locais` e ADICIONA duas
--  colunas opcionais (NULL) em `vagas`. Não altera, não apaga e não
--  reescreve nenhum dado existente.
--
--  - O endereço principal (sede) continua nas colunas de `empresas`
--    (Stage 3) — não é copiado para a tabela nova.
--  - `empresa_locais` guarda os OUTROS locais da empresa (escritório,
--    filial, unidade ou outro), com o mesmo formato de endereço.
--  - `vagas.local_trabalho`: 'empresa' = endereço principal da empresa;
--    'local' = o local em `vagas.local_id`; NULL = vaga antiga (continua
--    usando a cidade/UF/CEP dela; sem eles, o endereço da empresa).
--
--  Requer a migração da Stage 3 (2026-10-06_stage3_endereco_empresa.sql).
--  Aplicar UMA vez no banco já existente (phpMyAdmin → banco
--  jovemconnect → aba SQL → colar e executar).
--  Em instalação nova não precisa: o schema.sql já inclui este bloco.
--
--  Reversão (somente se necessário — apaga os locais cadastrados):
--    ALTER TABLE `vagas` DROP FOREIGN KEY `fk_vaga_local`,
--      DROP COLUMN `local_id`, DROP COLUMN `local_trabalho`;
--    DROP TABLE `empresa_locais`;
-- ============================================================

CREATE TABLE IF NOT EXISTS `empresa_locais` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `empresa_id`    INT(11)      NOT NULL,
  `tipo`          ENUM('escritorio','filial','unidade','outro') NOT NULL DEFAULT 'escritorio',
  `nome`          VARCHAR(100) DEFAULT NULL COMMENT 'Ex: Unidade Augusta',
  `cep`           VARCHAR(9)   DEFAULT NULL COMMENT 'Somente dígitos',
  `logradouro`    VARCHAR(150) DEFAULT NULL,
  `numero`        VARCHAR(10)  DEFAULT NULL,
  `complemento`   VARCHAR(100) DEFAULT NULL,
  `bairro`        VARCHAR(100) DEFAULT NULL,
  `cidade`        VARCHAR(100) DEFAULT NULL,
  `estado`        VARCHAR(50)  DEFAULT NULL,
  `criado_em`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_local_empresa` (`empresa_id`),
  CONSTRAINT `fk_local_empresa`
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `vagas`
  ADD COLUMN `local_trabalho` ENUM('empresa','local') DEFAULT NULL
      COMMENT 'empresa = endereço principal; local = vagas.local_id; NULL = vaga antiga' AFTER `cep`,
  ADD COLUMN `local_id`       INT(11) DEFAULT NULL AFTER `local_trabalho`,
  ADD KEY `idx_vaga_local` (`local_id`),
  ADD CONSTRAINT `fk_vaga_local`
    FOREIGN KEY (`local_id`) REFERENCES `empresa_locais` (`id`) ON DELETE SET NULL;
