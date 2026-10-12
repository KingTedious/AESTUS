-- ============================================================
--  JovemConnect — schema.sql  v3.00
--  Gerado em: 2026
--  Servidor: MariaDB 10.4+ / MySQL 8.0+
--  PHP: 8.2  |  Charset: utf8mb4
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;

-- ============================================================
--  DROP (ordem reversa para respeitar FK)
-- ============================================================
DROP TABLE IF EXISTS `avaliacoes_ia`;
DROP TABLE IF EXISTS `beneficios_vaga`;
DROP TABLE IF EXISTS `requisitos_vaga`;
DROP TABLE IF EXISTS `experiencias_jovem`;
DROP TABLE IF EXISTS `formacao_jovem`;
DROP TABLE IF EXISTS `habilidades_jovem`;
DROP TABLE IF EXISTS `candidaturas`;
DROP TABLE IF EXISTS `favoritos`;
DROP TABLE IF EXISTS `vagas`;
DROP TABLE IF EXISTS `jovens`;
DROP TABLE IF EXISTS `empresas`;

-- ============================================================
--  TABELA: empresas
-- ============================================================
CREATE TABLE `empresas` (
  `id`               INT(11)      NOT NULL AUTO_INCREMENT,
  `nome_empresa`     VARCHAR(150) NOT NULL,
  `email`            VARCHAR(100) NOT NULL,
  `senha`            VARCHAR(255) NOT NULL,
  `cnpj`             VARCHAR(18)  NOT NULL,
  `cidade`           VARCHAR(100) DEFAULT NULL,
  `estado`           VARCHAR(50)  DEFAULT NULL,
  `telefone`         VARCHAR(20)  DEFAULT NULL,
  -- v3.00 ↓
  `area_atuacao`     VARCHAR(100) DEFAULT NULL,
  `descricao`        TEXT         DEFAULT NULL   COMMENT 'Missão e visão da empresa',
  `site_oficial`     VARCHAR(255) DEFAULT NULL,
  `linkedin`         VARCHAR(255) DEFAULT NULL,
  `perfil_verificado` TINYINT(1)  NOT NULL DEFAULT 0,
  -- timestamps
  `criado_em`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_empresa`  (`email`),
  UNIQUE KEY `uq_cnpj`          (`cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABELA: jovens
-- ============================================================
CREATE TABLE `jovens` (
  `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
  `nome`                VARCHAR(100) NOT NULL,
  `email`               VARCHAR(100) NOT NULL,
  `senha`               VARCHAR(255) NOT NULL,
  `cpf`                 VARCHAR(14)  NOT NULL,
  `data_nascimento`     DATE         DEFAULT NULL,
  `cidade`              VARCHAR(100) DEFAULT NULL,
  `estado`              VARCHAR(50)  DEFAULT NULL,
  `cep`                 VARCHAR(9)   DEFAULT NULL,
  `telefone`            VARCHAR(20)  DEFAULT NULL,
  -- escolaridade
  `escolaridade_status` ENUM('cursando','completo') NOT NULL DEFAULT 'cursando',
  `escolaridade_tipo`   ENUM('regular','tecnico')   NOT NULL DEFAULT 'regular',
  `escolaridade_curso`  VARCHAR(100) DEFAULT NULL   COMMENT 'Nome do curso técnico, se houver',
  `formacao`            VARCHAR(100) DEFAULT NULL   COMMENT 'Texto legado — usar formacao_jovem para novos registros',
  -- extras v3.00
  `clt`                 TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = já trabalhou em regime CLT',
  `resumo_profissional` TEXT         DEFAULT NULL,
  `area_interesse`      VARCHAR(100) DEFAULT NULL,
  `perfil_publico`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = visível para empresas',
  -- arquivos
  `curriculo`           VARCHAR(255) DEFAULT NULL   COMMENT 'Caminho do PDF gerado',
  -- controle
  `ultimo_login`        DATETIME     DEFAULT NULL,
  `criado_em`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_jovem` (`email`),
  UNIQUE KEY `uq_cpf`         (`cpf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABELA: habilidades_jovem
--  Chips de habilidades exibidos no perfil (ex: "Excel Básico")
-- ============================================================
CREATE TABLE `habilidades_jovem` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `jovem_id`   INT(11)     NOT NULL,
  `habilidade` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hab_jovem` (`jovem_id`),
  CONSTRAINT `fk_hab_jovem`
    FOREIGN KEY (`jovem_id`) REFERENCES `jovens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABELA: formacao_jovem
--  Múltiplas entradas de formação acadêmica por jovem
-- ============================================================
CREATE TABLE `formacao_jovem` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `jovem_id`    INT(11)      NOT NULL,
  `titulo`      VARCHAR(150) NOT NULL  COMMENT 'Ex: Ensino Médio, Técnico em Adm',
  `instituicao` VARCHAR(150) DEFAULT NULL,
  `conclusao`   VARCHAR(20)  DEFAULT NULL  COMMENT 'Ex: Dezembro/2025 ou Em andamento',
  `area`        VARCHAR(100) DEFAULT NULL  COMMENT 'Área do curso técnico',
  PRIMARY KEY (`id`),
  KEY `idx_form_jovem` (`jovem_id`),
  CONSTRAINT `fk_form_jovem`
    FOREIGN KEY (`jovem_id`) REFERENCES `jovens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABELA: experiencias_jovem
--  Experiências informais, voluntárias ou cursos livres
-- ============================================================
CREATE TABLE `experiencias_jovem` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `jovem_id`  INT(11)      NOT NULL,
  `titulo`    VARCHAR(150) NOT NULL,
  `local`     VARCHAR(150) DEFAULT NULL,
  `periodo`   VARCHAR(50)  DEFAULT NULL  COMMENT 'Ex: Jan/2024 – Mar/2024',
  `descricao` TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_exp_jovem` (`jovem_id`),
  CONSTRAINT `fk_exp_jovem`
    FOREIGN KEY (`jovem_id`) REFERENCES `jovens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABELA: vagas
-- ============================================================
CREATE TABLE `vagas` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `empresa_id`    INT(11)      NOT NULL,
  `titulo`        VARCHAR(150) NOT NULL,
  `descricao`     TEXT         DEFAULT NULL,
  `cidade`        VARCHAR(100) DEFAULT NULL,
  `estado`        VARCHAR(50)  DEFAULT NULL,
  `cep`           VARCHAR(9)   DEFAULT NULL   COMMENT 'Para cálculo de distância pela IA',
  `salario`       VARCHAR(50)  DEFAULT NULL   COMMENT 'Mantido como VARCHAR (ex: 1.200,00 ou A combinar)',
  `tipo_contrato` VARCHAR(50)  DEFAULT NULL   COMMENT 'Ex: Jovem Aprendiz, Estágio',
  -- v3.00 ↓
  `carga_horaria` VARCHAR(20)  DEFAULT NULL   COMMENT 'Ex: 20h/semana',
  `area`          VARCHAR(100) DEFAULT NULL   COMMENT 'Ex: Administrativo, TI, Vendas',
  `criado_em`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vaga_empresa` (`empresa_id`),
  KEY `idx_vaga_area`    (`area`),
  KEY `idx_vaga_cidade`  (`cidade`),
  CONSTRAINT `fk_vaga_empresa`
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABELA: requisitos_vaga
--  Chips de requisitos exibidos na página de detalhes da vaga
-- ============================================================
CREATE TABLE `requisitos_vaga` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `vaga_id`   INT(11)      NOT NULL,
  `requisito` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_req_vaga` (`vaga_id`),
  CONSTRAINT `fk_req_vaga`
    FOREIGN KEY (`vaga_id`) REFERENCES `vagas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABELA: beneficios_vaga
--  Chips de benefícios exibidos na página de detalhes da vaga
-- ============================================================
CREATE TABLE `beneficios_vaga` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `vaga_id`   INT(11)      NOT NULL,
  `beneficio` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ben_vaga` (`vaga_id`),
  CONSTRAINT `fk_ben_vaga`
    FOREIGN KEY (`vaga_id`) REFERENCES `vagas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABELA: candidaturas
-- ============================================================
CREATE TABLE `candidaturas` (
  `id`        INT(11)     NOT NULL AUTO_INCREMENT,
  `vaga_id`   INT(11)     NOT NULL,
  `jovem_id`  INT(11)     NOT NULL,
  `status`    VARCHAR(30) NOT NULL DEFAULT 'Recebido'
              COMMENT 'Recebido | Visualizado | Em análise | Aprovado | Reprovado',
  `criado_em` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_candidatura` (`vaga_id`, `jovem_id`)  COMMENT 'Um jovem só pode se candidatar uma vez por vaga',
  KEY `idx_cand_jovem` (`jovem_id`),
  CONSTRAINT `fk_cand_vaga`
    FOREIGN KEY (`vaga_id`)  REFERENCES `vagas`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cand_jovem`
    FOREIGN KEY (`jovem_id`) REFERENCES `jovens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1. Atualizar candidaturas para o fluxo de etapas
ALTER TABLE `candidaturas` 
ADD COLUMN `etapa` ENUM('candidatado', 'em_analise', 'aceito_chat', 'contratado', 'recusado') 
DEFAULT 'candidatado' AFTER `status`;

-- 2. Tabela do Chat de Comunicação
CREATE TABLE IF NOT EXISTS `mensagens_chat` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `candidatura_id` INT NOT NULL,
  `remetente_tipo` ENUM('jovem', 'empresa') NOT NULL,
  `remetente_id` INT NOT NULL,
  `mensagem` TEXT NOT NULL,
  `tipo_mensagem` ENUM('texto', 'card_endereco') DEFAULT 'texto',
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`candidatura_id`) REFERENCES `candidaturas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABELA: favoritos
-- ============================================================
CREATE TABLE `favoritos` (
  `id`        INT(11)   NOT NULL AUTO_INCREMENT,
  `jovem_id`  INT(11)   NOT NULL,
  `vaga_id`   INT(11)   NOT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_favorito` (`jovem_id`, `vaga_id`),
  KEY `idx_fav_vaga` (`vaga_id`),
  CONSTRAINT `fk_fav_jovem`
    FOREIGN KEY (`jovem_id`) REFERENCES `jovens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_vaga`
    FOREIGN KEY (`vaga_id`)  REFERENCES `vagas`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABELA: avaliacoes_ia
--  Cache dos resultados do match calculados pelo Flask.
--  Evita re-chamar a API a cada carregamento de página.
-- ============================================================
CREATE TABLE `avaliacoes_ia` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `jovem_id`   INT(11)     NOT NULL,
  `vaga_id`    INT(11)     NOT NULL,
  `score`      TINYINT     NOT NULL DEFAULT 0  COMMENT '0–100',
  `analise`    TEXT        DEFAULT NULL        COMMENT 'Texto retornado pela IA',
  `criado_em`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY  `uq_avaliacao` (`jovem_id`, `vaga_id`),
  KEY `idx_av_vaga` (`vaga_id`),
  CONSTRAINT `fk_av_jovem`
    FOREIGN KEY (`jovem_id`) REFERENCES `jovens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_av_vaga`
    FOREIGN KEY (`vaga_id`)  REFERENCES `vagas`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Anúncios de Freelance
CREATE TABLE IF NOT EXISTS `freelances` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `empresa_id` INT DEFAULT NULL,
  `jovem_contratante_id` INT DEFAULT NULL,
  `titulo` VARCHAR(150) NOT NULL,
  `descricao` TEXT NOT NULL,
  `categoria` VARCHAR(50) NOT NULL,
  `orcamento_maximo` DECIMAL(10,2) NOT NULL,
  `prazo_dias` INT NOT NULL,
  `status` ENUM('aberto', 'em_andamento', 'concluido', 'cancelado') DEFAULT 'aberto',
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`jovem_contratante_id`) REFERENCES `jovens`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Propostas enviadas pelos Jovens
CREATE TABLE IF NOT EXISTS `propostas_freelance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `freelance_id` INT NOT NULL,
  `jovem_id` INT NOT NULL,
  `valor_proposto` DECIMAL(10,2) NOT NULL,
  `taxa_percentual` DECIMAL(4,2) NOT NULL,
  `taxa_valor` DECIMAL(10,2) NOT NULL,
  `valor_liquido` DECIMAL(10,2) NOT NULL,
  `prazo_dias` INT NOT NULL,
  `mensagem` TEXT NOT NULL,
  `status` ENUM('pendente', 'aceita', 'recusada') DEFAULT 'pendente',
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`freelance_id`) REFERENCES `freelances`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`jovem_id`) REFERENCES `jovens`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  DADOS DE TESTE
-- ============================================================

-- Empresa de teste
-- Senha: 123456  →  gerada com password_hash('123456', PASSWORD_BCRYPT)
INSERT INTO `empresas`
  (`id`, `nome_empresa`, `email`, `senha`, `cnpj`, `cidade`, `estado`, `telefone`, `area_atuacao`, `criado_em`)
VALUES
  (3, 'Tech Solutions Ltda', 'empresa@teste.com',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: "password" — troque em prod
   '12.345.678/0001-90', 'São Paulo', 'SP', NULL, 'Tecnologia e Inovação', '2026-03-18 18:47:22');

-- Jovem de teste (senha em texto puro — APENAS DEV)
-- Para produção use: password_hash('123456', PASSWORD_BCRYPT)
INSERT INTO `jovens`
  (`id`, `nome`, `email`, `senha`, `cpf`, `data_nascimento`, `cidade`, `estado`, `cep`,
   `escolaridade_status`, `escolaridade_tipo`, `escolaridade_curso`, `formacao`, `telefone`, `criado_em`)
VALUES
  (5, 'Jovem Teste', 'jovem@teste.com', '123456',
   '123.456.789-00', '2000-01-01', 'São Paulo', 'SP', '01310-100',
   'cursando', 'regular', NULL, 'Ensino Médio', '11999999999', '2026-03-18 18:48:14'),

  (6, 'Gabriel Sassati', 'gabriel.sassati@gmail.com',
   '$2y$10$Av2SJieFy4ITFNKpjzoN.uAlfi7mboTgbsZKNTawpYFpwzVNgV2OW',
   '146.395.958-32', '2009-05-06', 'São Paulo', 'SP', NULL,
   'cursando', 'regular', NULL, 'Ensino Médio Completo', '11948566681', '2026-04-08 20:41:25');

-- Vaga de exemplo ligada à empresa de teste
INSERT INTO `vagas`
  (`id`, `empresa_id`, `titulo`, `descricao`, `cidade`, `estado`, `salario`, `tipo_contrato`, `carga_horaria`, `area`)
VALUES
  (1, 3, 'Auxiliar Administrativo',
   'Estamos em busca de um Jovem Aprendiz para auxiliar nas rotinas administrativas, organização de documentos e atendimento telefônico. Ótima oportunidade para quem busca o primeiro emprego.',
   'São Paulo', 'SP', '1200.00', 'Jovem Aprendiz', '20h/semana', 'Administrativo');

-- Requisitos da vaga de exemplo
INSERT INTO `requisitos_vaga` (`vaga_id`, `requisito`) VALUES
  (1, 'Ensino Médio em curso ou completo'),
  (1, 'Conhecimento básico em pacote Office'),
  (1, 'Boa comunicação');

-- Benefícios da vaga de exemplo
INSERT INTO `beneficios_vaga` (`vaga_id`, `beneficio`) VALUES
  (1, 'Vale Transporte'),
  (1, 'Seguro de Vida'),
  (1, 'Curso de Capacitação');

-- Habilidades do jovem Gabriel
INSERT INTO `habilidades_jovem` (`jovem_id`, `habilidade`) VALUES
  (6, 'Excel Básico'),
  (6, 'Boa Comunicação'),
  (6, 'Organização');

-- Formação acadêmica do jovem Gabriel
INSERT INTO `formacao_jovem` (`jovem_id`, `titulo`, `instituicao`, `conclusao`, `area`) VALUES
  (6, 'Ensino Médio', 'Escola Estadual Professor José', 'Dezembro/2026', NULL);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Adiciona campos de verificação na tabela de jovens
ALTER TABLE `jovens` 
ADD COLUMN `email_verificado` TINYINT(1) DEFAULT 0,
ADD COLUMN `codigo_verificacao` VARCHAR(6) DEFAULT NULL,
ADD COLUMN `expiracao_codigo` DATETIME DEFAULT NULL;

-- Adiciona campos de verificação na tabela de empresas
ALTER TABLE `empresas` 
ADD COLUMN `email_verificado` TINYINT(1) DEFAULT 0,
ADD COLUMN `codigo_verificacao` VARCHAR(6) DEFAULT NULL,
ADD COLUMN `expiracao_codigo` DATETIME DEFAULT NULL;

-- Colunas de verificação por telefone/SMS (usadas por verificacoes/verificar_telefone.php
-- e verificacoes/escolher_verificacao.php, mas ausentes do schema — Etapa 1 V3.10)
ALTER TABLE `jovens`
ADD COLUMN `telefone_verificado` TINYINT(1) DEFAULT 0,
ADD COLUMN `codigo_telefone` VARCHAR(6) DEFAULT NULL,
ADD COLUMN `expiracao_telefone` DATETIME DEFAULT NULL;

ALTER TABLE `empresas`
ADD COLUMN `telefone_verificado` TINYINT(1) DEFAULT 0,
ADD COLUMN `codigo_telefone` VARCHAR(6) DEFAULT NULL,
ADD COLUMN `expiracao_telefone` DATETIME DEFAULT NULL;

-- Adiciona campos de recuperação para Jovens
ALTER TABLE `jovens` 
ADD COLUMN `codigo_recuperacao` VARCHAR(6) DEFAULT NULL,
ADD COLUMN `expiracao_recuperacao` DATETIME DEFAULT NULL;

-- Adiciona campos de recuperação para Empresas
ALTER TABLE `empresas` 
ADD COLUMN `codigo_recuperacao` VARCHAR(6) DEFAULT NULL,
ADD COLUMN `expiracao_recuperacao` DATETIME DEFAULT NULL;

-- Stage 3: endereço completo da empresa (migração aditiva — mesma de
-- migrations/2026-10-06_stage3_endereco_empresa.sql)
ALTER TABLE `empresas`
  ADD COLUMN `cep`         VARCHAR(9)   DEFAULT NULL COMMENT 'Somente dígitos'  AFTER `telefone`,
  ADD COLUMN `logradouro`  VARCHAR(150) DEFAULT NULL AFTER `cep`,
  ADD COLUMN `numero`      VARCHAR(10)  DEFAULT NULL AFTER `logradouro`,
  ADD COLUMN `complemento` VARCHAR(100) DEFAULT NULL AFTER `numero`,
  ADD COLUMN `bairro`      VARCHAR(100) DEFAULT NULL AFTER `complemento`;

-- Stage 5.1: locais de trabalho das vagas (migração aditiva — mesma de
-- migrations/2026-10-08_stage51_locais_trabalho.sql)
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


-- Stage 6: aceite dos Termos de Uso e da Política de Privacidade (mesma migração de
-- migrations/2026-10-10_stage6_aceite_termos.sql)
ALTER TABLE `jovens`
  ADD COLUMN `termos_versao`     VARCHAR(20) DEFAULT NULL COMMENT 'Versão dos Termos/Privacidade aceita no cadastro',
  ADD COLUMN `termos_aceitos_em` DATETIME    DEFAULT NULL COMMENT 'Quando a caixa de aceite foi marcada e enviada';

ALTER TABLE `empresas`
  ADD COLUMN `termos_versao`     VARCHAR(20) DEFAULT NULL COMMENT 'Versão dos Termos/Privacidade aceita no cadastro',
  ADD COLUMN `termos_aceitos_em` DATETIME    DEFAULT NULL COMMENT 'Quando a caixa de aceite foi marcada e enviada';

-- Stage 7: central administrativa, moderação e auditoria (mesma migração de
-- migrations/2026-10-11_stage7_admin_moderacao.sql). Nenhum administrador é criado aqui:
-- use o provisionamento pela linha de comando (scripts/admin.php).
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
