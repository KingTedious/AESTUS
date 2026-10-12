-- ============================================================
--  Stage 7 (11/10/2026) — Central administrativa, moderação e auditoria
--
--  Migração ADITIVA: cria 5 tabelas novas e ADICIONA colunas cujos
--  valores padrão mantêm o comportamento atual do site:
--    - jovens.conta_status e empresas.conta_status = 'ativa'
--    - vagas.moderacao_status = 'aprovada' (todas as vagas existentes
--      continuam visíveis exatamente como antes)
--  Não altera, não apaga e não reescreve nenhum dado existente.
--
--  Nenhuma conta de jovem ou de empresa recebe privilégio administrativo:
--  os administradores ficam numa tabela própria, que nasce VAZIA. A única
--  forma de criar um administrador é o provisionamento pela linha de
--  comando (scripts/admin.php — ver docs/ADMINISTRACAO_E_MODERACAO.md).
--  `administradores.papel` não tem valor padrão e `ativo` nasce 0.
--
--  Requer as migrações anteriores (Stage 3, 5.1 e 6).
--  Aplicar UMA vez no banco já existente (phpMyAdmin → banco
--  jovemconnect → aba SQL → colar e executar). Faça um backup antes
--  (aba Exportar). Em instalação nova não precisa: o schema.sql já
--  inclui este bloco.
--
--  Como saber se já foi aplicada: a tabela `administradores` aparece na
--  lista de tabelas do banco (ou: SHOW TABLES LIKE 'administradores';).
--  Rodar de novo dá erro "tabela já existe"/"coluna duplicada" sem
--  estragar dados.
--
--  Sem esta migração o site continua funcionando como na v4.00; a
--  Central administrativa (admin/) mostra que a migração está pendente.
--
--  Reversão (somente se necessário — APAGA administradores, histórico de
--  auditoria, fila de moderação e denúncias; faça backup antes):
--    DROP TABLE `denuncias`;
--    DROP TABLE `moderacao_itens`;
--    DROP TABLE `admin_auditoria`;
--    DROP TABLE `admin_tentativas`;
--    DROP TABLE `administradores`;
--    ALTER TABLE `vagas`    DROP KEY `idx_vaga_moderacao`, DROP COLUMN `moderacao_status`,
--                           DROP COLUMN `moderacao_motivo`, DROP COLUMN `moderacao_em`;
--    ALTER TABLE `jovens`   DROP COLUMN `conta_status`, DROP COLUMN `conta_status_em`;
--    ALTER TABLE `empresas` DROP COLUMN `conta_status`, DROP COLUMN `conta_status_em`;
-- ============================================================

-- Administradores (separados das contas de jovens e empresas)
CREATE TABLE IF NOT EXISTS `administradores` (
  `id`                INT(11)      NOT NULL AUTO_INCREMENT,
  `email`             VARCHAR(100) NOT NULL,
  `nome`              VARCHAR(100) NOT NULL,
  `senha_hash`        VARCHAR(255) NOT NULL COMMENT 'password_hash(); a senha nunca é gravada',
  `papel`             VARCHAR(30)  NOT NULL COMMENT 'Sem valor padrão: definido só pelo provisionamento',
  `ativo`             TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '0 até o provisionamento ativar',
  `mfa_segredo`       VARCHAR(64)  DEFAULT NULL COMMENT 'Segredo do autenticador (TOTP, base32); NULL = sem MFA',
  `mfa_ultimo_passo`  BIGINT       DEFAULT NULL COMMENT 'Último intervalo TOTP aceito (impede reusar o código)',
  `sessao_versao`     INT(11)      NOT NULL DEFAULT 1 COMMENT 'Muda ao trocar a senha ou desativar: derruba as sessões abertas',
  `ultimo_login_em`   DATETIME     DEFAULT NULL,
  `senha_alterada_em` DATETIME     DEFAULT NULL,
  `criado_em`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativas de login administrativo (limite de tentativas; só hashes, nunca e-mail ou IP)
CREATE TABLE IF NOT EXISTS `admin_tentativas` (
  `id`         BIGINT     NOT NULL AUTO_INCREMENT,
  `chave_hash` CHAR(64)   NOT NULL COMMENT 'SHA-256 do e-mail digitado',
  `ip_hash`    CHAR(64)   NOT NULL COMMENT 'SHA-256 do endereço IP',
  `sucesso`    TINYINT(1) NOT NULL DEFAULT 0,
  `criado_em`  DATETIME   NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tent_chave` (`chave_hash`, `criado_em`),
  KEY `idx_tent_ip`    (`ip_hash`, `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico de ações administrativas (somente inclusão; cada linha guarda o
-- hash da anterior, o que torna alterações e remoções detectáveis)
CREATE TABLE IF NOT EXISTS `admin_auditoria` (
  `id`            BIGINT       NOT NULL AUTO_INCREMENT,
  `criado_em`     DATETIME     NOT NULL,
  `admin_id`      INT(11)      DEFAULT NULL COMMENT 'NULL = linha de comando ou tentativa sem login',
  `acao`          VARCHAR(60)  NOT NULL,
  `recurso_tipo`  VARCHAR(30)  DEFAULT NULL,
  `recurso_id`    INT(11)      DEFAULT NULL,
  `resultado`     VARCHAR(20)  NOT NULL COMMENT 'sucesso | falha | negado',
  `motivo_codigo` VARCHAR(60)  DEFAULT NULL,
  `justificativa` VARCHAR(500) DEFAULT NULL,
  `campos`        VARCHAR(300) DEFAULT NULL COMMENT 'Nomes dos campos alterados (sem o conteúdo)',
  `req_id`        VARCHAR(32)  DEFAULT NULL COMMENT 'Correlação com logs/app.log e logs/seguranca.log',
  `ip_anon`       VARCHAR(45)  DEFAULT NULL,
  `hash_anterior` CHAR(64)     NOT NULL,
  `hash`          CHAR(64)     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aud_recurso` (`recurso_tipo`, `recurso_id`),
  KEY `idx_aud_admin`   (`admin_id`, `criado_em`),
  KEY `idx_aud_acao`    (`acao`, `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila de moderação (o texto avaliado NÃO é copiado: fica só o hash)
CREATE TABLE IF NOT EXISTS `moderacao_itens` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `recurso_tipo`  VARCHAR(20)  NOT NULL COMMENT 'vaga | jovem | empresa',
  `recurso_id`    INT(11)      NOT NULL,
  `campo`         VARCHAR(40)  NOT NULL COMMENT 'Campo do mapa de validação (ex.: vaga.descricao) ou "denuncia"',
  `autor_tipo`    VARCHAR(10)  DEFAULT NULL,
  `autor_id`      INT(11)      DEFAULT NULL,
  `origem`        VARCHAR(20)  NOT NULL COMMENT 'regra | denuncia',
  `regras`        VARCHAR(255) DEFAULT NULL COMMENT 'Códigos das regras que sinalizaram',
  `risco`         ENUM('baixo','medio','alto') NOT NULL,
  `estado`        ENUM('pendente','resolvido') NOT NULL DEFAULT 'pendente',
  `decisao`       VARCHAR(20)  DEFAULT NULL,
  `justificativa` VARCHAR(500) DEFAULT NULL,
  `decidido_por`  INT(11)      DEFAULT NULL,
  `decidido_em`   DATETIME     DEFAULT NULL,
  `conteudo_hash` CHAR(64)     DEFAULT NULL COMMENT 'SHA-256 do texto avaliado',
  `ocorrencias`   INT(11)      NOT NULL DEFAULT 1,
  `criado_em`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mod_fila`    (`estado`, `risco`, `criado_em`),
  KEY `idx_mod_recurso` (`recurso_tipo`, `recurso_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Denúncias de vagas feitas por jovens (uma por jovem e vaga).
-- vaga_id SEM chave estrangeira de propósito: se a empresa excluir a vaga,
-- a denúncia continua para a moderação (evidência). jovem_id COM chave
-- estrangeira: se o jovem excluir a própria conta, as denúncias dele saem junto.
CREATE TABLE IF NOT EXISTS `denuncias` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `vaga_id`   INT(11)      NOT NULL COMMENT 'Sem FK: a denúncia sobrevive à exclusão da vaga',
  `jovem_id`  INT(11)      NOT NULL,
  `motivo`    VARCHAR(30)  NOT NULL,
  `detalhe`   VARCHAR(500) DEFAULT NULL,
  `item_id`   INT(11)      DEFAULT NULL COMMENT 'Item da fila de moderação',
  `criado_em` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_denuncia`  (`vaga_id`, `jovem_id`),
  KEY `idx_den_jovem` (`jovem_id`, `criado_em`),
  CONSTRAINT `fk_den_jovem` FOREIGN KEY (`jovem_id`) REFERENCES `jovens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estado das contas (suspensão pela administração; padrão = como hoje)
ALTER TABLE `jovens`
  ADD COLUMN `conta_status`    ENUM('ativa','suspensa') NOT NULL DEFAULT 'ativa' COMMENT 'suspensa = sem acesso, decidido pela administração',
  ADD COLUMN `conta_status_em` DATETIME DEFAULT NULL COMMENT 'Última mudança do estado da conta';

ALTER TABLE `empresas`
  ADD COLUMN `conta_status`    ENUM('ativa','suspensa') NOT NULL DEFAULT 'ativa' COMMENT 'suspensa = sem acesso, decidido pela administração',
  ADD COLUMN `conta_status_em` DATETIME DEFAULT NULL COMMENT 'Última mudança do estado da conta';

-- Estado de moderação das vagas (separado da publicação; padrão = visível como hoje)
ALTER TABLE `vagas`
  ADD COLUMN `moderacao_status` ENUM('aprovada','pendente','sinalizada','rejeitada','suspensa') NOT NULL DEFAULT 'aprovada'
      COMMENT 'aprovada/sinalizada = visível; pendente/rejeitada/suspensa = oculta para os jovens',
  ADD COLUMN `moderacao_motivo` VARCHAR(60) DEFAULT NULL COMMENT 'Código do motivo mostrado à empresa',
  ADD COLUMN `moderacao_em`     DATETIME    DEFAULT NULL COMMENT 'Última decisão de moderação',
  ADD KEY `idx_vaga_moderacao` (`moderacao_status`);
