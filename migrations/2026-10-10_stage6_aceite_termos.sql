-- ============================================================
--  Stage 6 (10/10/2026) — Registro do aceite dos Termos de Uso e da
--  Política de Privacidade no cadastro.
--
--  Migração ADITIVA: só ADICIONA 2 colunas opcionais (NULL) em `jovens`
--  e em `empresas`. Não altera nem apaga dados. Contas antigas ficam com
--  NULL (o aceite delas não foi registrado pelo sistema).
--
--  Aplicar UMA vez no banco existente (phpMyAdmin → banco jovemconnect →
--  aba SQL → colar e executar). Instalação nova: o schema.sql já inclui.
--
--  Reversão (apaga os registros de aceite):
--    ALTER TABLE `jovens`   DROP COLUMN `termos_aceitos_em`, DROP COLUMN `termos_versao`;
--    ALTER TABLE `empresas` DROP COLUMN `termos_aceitos_em`, DROP COLUMN `termos_versao`;
-- ============================================================

ALTER TABLE `jovens`
  ADD COLUMN `termos_versao`     VARCHAR(20) DEFAULT NULL COMMENT 'Versão dos Termos/Privacidade aceita no cadastro',
  ADD COLUMN `termos_aceitos_em` DATETIME    DEFAULT NULL COMMENT 'Quando a caixa de aceite foi marcada e enviada';

ALTER TABLE `empresas`
  ADD COLUMN `termos_versao`     VARCHAR(20) DEFAULT NULL COMMENT 'Versão dos Termos/Privacidade aceita no cadastro',
  ADD COLUMN `termos_aceitos_em` DATETIME    DEFAULT NULL COMMENT 'Quando a caixa de aceite foi marcada e enviada';
