-- ============================================================
--  Stage 3 (06/10/2026) — Endereço completo da empresa
--
--  Migração ADITIVA: só ADICIONA 5 colunas opcionais (NULL) na tabela
--  `empresas`. Não altera, não apaga e não reescreve nenhum dado.
--  `cidade` e `estado` já existiam e continuam sendo usadas.
--
--  Aplicar UMA vez no banco já existente (phpMyAdmin → banco
--  jovemconnect → aba SQL → colar e executar; ou pelo cliente mysql).
--  Em instalação nova não precisa: o schema.sql já inclui este bloco.
--
--  Reversão (somente se necessário — apaga os endereços digitados):
--    ALTER TABLE `empresas` DROP COLUMN `cep`, DROP COLUMN `logradouro`,
--      DROP COLUMN `numero`, DROP COLUMN `complemento`, DROP COLUMN `bairro`;
-- ============================================================

ALTER TABLE `empresas`
  ADD COLUMN `cep`         VARCHAR(9)   DEFAULT NULL COMMENT 'Somente dígitos'  AFTER `telefone`,
  ADD COLUMN `logradouro`  VARCHAR(150) DEFAULT NULL AFTER `cep`,
  ADD COLUMN `numero`      VARCHAR(10)  DEFAULT NULL AFTER `logradouro`,
  ADD COLUMN `complemento` VARCHAR(100) DEFAULT NULL AFTER `numero`,
  ADD COLUMN `bairro`      VARCHAR(100) DEFAULT NULL AFTER `complemento`;
