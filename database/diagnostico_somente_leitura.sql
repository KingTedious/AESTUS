-- ============================================================
--  JovemConnect — DIAGNÓSTICO DO BANCO (SOMENTE LEITURA)
--
--  Não altera nada: só consulta a estrutura (information_schema) e conta
--  registros. Pode ser executado no banco de trabalho a qualquer momento,
--  quantas vezes quiser. Não mostra nenhum dado pessoal, só quantidades e
--  números de conferência.
--
--  Para que serve
--    - Antes de uma atualização: saber quais migrações de migrations/ já
--      foram aplicadas, quais faltam e quantos registros receberão as
--      colunas novas (Consultas 1, 2 e 3).
--    - Depois de uma falha no meio de uma migração: ver exatamente o que
--      ficou PARCIAL (docs/MIGRACOES.md, seção "Se uma migração falhar").
--    - Conferir um BACKUP e uma RESTAURAÇÃO (Consultas 4, 5 e 6): rode-as no
--      banco original e na cópia restaurada e compare linha por linha
--      (docs/MIGRACOES.md, seção "Backup verificado e restauração").
--
--  Como usar (phpMyAdmin)
--    Banco jovemconnect → aba SQL → cole UMA consulta por vez → Executar.
--    Consultas 1 a 4: da linha SELECT até o ponto e vírgula.
--    Consultas 5 e 6: o bloco inteiro, do primeiro SET até o EXECUTE (elas
--    montam sozinhas a consulta a partir das tabelas que existem no banco; o
--    comando montado é só um SELECT).
--
--  Como ler a Consulta 1
--    esperado = quantas peças (tabelas, colunas, índices, chaves) a etapa cria;
--    encontrado = quantas existem no banco agora.
--      ok / aplicada  → nada a fazer.
--      PENDENTE       → aplique o arquivo daquela migração (uma vez).
--      PARCIAL        → NÃO rode o arquivo inteiro de novo: ver MIGRACOES.md.
--      FALTANDO       → o banco é anterior às migrações e não existe arquivo
--                       pronto para isso: pare e peça uma migração específica.
-- ============================================================

-- Consulta 1 — situação de cada etapa da estrutura
SELECT item, esperado, encontrado,
       CASE
         WHEN encontrado = esperado THEN IF(grupo = 'base', 'ok', 'aplicada')
         WHEN grupo = 'base'        THEN 'FALTANDO - pare: não há migração pronta para isto'
         WHEN encontrado = 0        THEN 'PENDENTE - aplicar o arquivo desta migração'
         ELSE 'PARCIAL - não rode o arquivo inteiro de novo (ver MIGRACOES.md)'
       END AS situacao
FROM (
  SELECT 'base' AS grupo, '1. Base: tabelas principais' AS item, 9 AS esperado,
         (SELECT COUNT(*) FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME IN ('empresas','jovens','habilidades_jovem','formacao_jovem','experiencias_jovem',
                                'vagas','requisitos_vaga','beneficios_vaga','candidaturas')) AS encontrado
  UNION ALL
  SELECT 'base', '2. Base: chat, favoritos, avaliações e freelances', 5,
         (SELECT COUNT(*) FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME IN ('mensagens_chat','favoritos','avaliacoes_ia','freelances','propostas_freelance'))
  UNION ALL
  SELECT 'base', '3. Base: verificação e recuperação (jovens e empresas)', 16,
         (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('jovens','empresas')
             AND COLUMN_NAME IN ('email_verificado','codigo_verificacao','expiracao_codigo',
                                 'telefone_verificado','codigo_telefone','expiracao_telefone',
                                 'codigo_recuperacao','expiracao_recuperacao'))
  UNION ALL
  SELECT 'base', '4. Base: candidaturas.etapa', 1,
         (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'candidaturas' AND COLUMN_NAME = 'etapa')
  UNION ALL
  SELECT 'migracao', '5. Stage 3   (2026-10-06_stage3_endereco_empresa.sql)', 5,
         (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresas'
             AND COLUMN_NAME IN ('cep','logradouro','numero','complemento','bairro'))
  UNION ALL
  SELECT 'migracao', '6. Stage 5.1 (2026-10-08_stage51_locais_trabalho.sql)', 4,
         (SELECT COUNT(*) FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_locais')
       + (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vagas'
             AND COLUMN_NAME IN ('local_trabalho','local_id'))
       + (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
           WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'vagas'
             AND CONSTRAINT_NAME = 'fk_vaga_local' AND CONSTRAINT_TYPE = 'FOREIGN KEY')
  UNION ALL
  SELECT 'migracao', '7. Stage 6   (2026-10-10_stage6_aceite_termos.sql)', 4,
         (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('jovens','empresas')
             AND COLUMN_NAME IN ('termos_versao','termos_aceitos_em'))
  UNION ALL
  SELECT 'migracao', '8. Stage 7   (2026-10-11_stage7_admin_moderacao.sql)', 13,
         (SELECT COUNT(*) FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME IN ('administradores','admin_tentativas','admin_auditoria','moderacao_itens','denuncias'))
       + (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('jovens','empresas')
             AND COLUMN_NAME IN ('conta_status','conta_status_em'))
       + (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vagas'
             AND COLUMN_NAME IN ('moderacao_status','moderacao_motivo','moderacao_em'))
       + (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vagas' AND INDEX_NAME = 'idx_vaga_moderacao')
) AS etapas
ORDER BY item;

-- Consulta 2 — registros que recebem colunas novas numa atualização.
-- As quatro migrações só ACRESCENTAM colunas (com valor padrão) a estas três
-- tabelas e criam tabelas novas vazias; nenhum valor existente é alterado.
SELECT 'jovens' AS tabela, COUNT(*) AS registros FROM `jovens`
UNION ALL SELECT 'empresas', COUNT(*) FROM `empresas`
UNION ALL SELECT 'vagas',    COUNT(*) FROM `vagas`;

-- Consulta 3 — contas de exemplo que o schema.sql ANTIGO (até a v4.50) gravava
-- (e-mails @teste.com; uma delas com senha conhecida). Mostra só quantidades.
SELECT 'jovens com e-mail @teste.com' AS item, COUNT(*) AS quantidade FROM `jovens` WHERE `email` LIKE '%@teste.com'
UNION ALL
SELECT 'empresas com e-mail @teste.com', COUNT(*) FROM `empresas` WHERE `email` LIKE '%@teste.com';

-- ============================================================
--  CONFERÊNCIA DE BACKUP E RESTAURAÇÃO (Consultas 4, 5 e 6)
--  Rode as três no banco ORIGINAL e na cópia RESTAURADA, do mesmo jeito
--  (mesma ferramenta), e compare. O backup só vale se:
--    Consulta 4 → os quatro números forem iguais;
--    Consulta 5 → a lista de tabelas, os "registros" e a "impressao" forem
--                 iguais em TODAS as linhas;
--    Consulta 6 → todas as linhas derem 0, nos dois bancos.
--  "impressao" é um número de conferência calculado a partir do conteúdo de
--  cada linha: muda se qualquer valor mudar, mesmo com a mesma quantidade de
--  registros. Num banco vazio as Consultas 5 e 6 não têm o que listar.
-- ============================================================

-- Consulta 4 — estrutura: quantas tabelas, colunas, índices e chaves estrangeiras
SELECT (SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()) AS tabelas,
       (SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()) AS colunas,
       (SELECT COUNT(*) FROM (SELECT DISTINCT TABLE_NAME, INDEX_NAME
                                FROM information_schema.STATISTICS
                               WHERE TABLE_SCHEMA = DATABASE()) AS i) AS indices,
       (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()) AS chaves_estrangeiras;

-- Consulta 5 — registros e impressão digital de CADA tabela (bloco inteiro)
SET SESSION group_concat_max_len = 1000000;
SET @jc_q = (
  SELECT GROUP_CONCAT(
           CONCAT('SELECT ''', d.tabela, ''' AS tabela, COUNT(*) AS registros, ',
                  'CAST(COALESCE(SUM(CRC32(CONCAT_WS(''|'', ', d.colunas, '))), 0) AS DECIMAL(30,0)) AS impressao ',
                  'FROM `', d.tabela, '`')
           ORDER BY d.tabela SEPARATOR ' UNION ALL ')
    FROM (SELECT c.TABLE_NAME AS tabela,
                 GROUP_CONCAT(CONCAT('IFNULL(`', c.COLUMN_NAME, '`, ''<nulo>'')')
                              ORDER BY c.ORDINAL_POSITION SEPARATOR ', ') AS colunas
            FROM information_schema.COLUMNS c
            JOIN information_schema.TABLES t
              ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
             AND t.TABLE_TYPE = 'BASE TABLE'
           WHERE c.TABLE_SCHEMA = DATABASE()
           GROUP BY c.TABLE_NAME) AS d);
PREPARE jc_consulta FROM @jc_q;
EXECUTE jc_consulta;

-- Consulta 6 — integridade: registros órfãos em CADA chave estrangeira (bloco inteiro)
-- Cada linha = uma relação (tabela.coluna -> tabela.coluna). Tudo precisa dar 0.
SET SESSION group_concat_max_len = 1000000;
SET @jc_q = (
  SELECT GROUP_CONCAT(
           CONCAT('SELECT ''', k.TABLE_NAME, '.', k.COLUMN_NAME, ' -> ',
                  k.REFERENCED_TABLE_NAME, '.', k.REFERENCED_COLUMN_NAME,
                  ''' AS relacao, COUNT(*) AS registros_orfaos FROM `', k.TABLE_NAME, '` f ',
                  'LEFT JOIN `', k.REFERENCED_TABLE_NAME, '` p ON p.`', k.REFERENCED_COLUMN_NAME,
                  '` = f.`', k.COLUMN_NAME, '` WHERE f.`', k.COLUMN_NAME, '` IS NOT NULL AND p.`',
                  k.REFERENCED_COLUMN_NAME, '` IS NULL')
           ORDER BY k.TABLE_NAME, k.COLUMN_NAME SEPARATOR ' UNION ALL ')
    FROM information_schema.KEY_COLUMN_USAGE k
   WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL);
PREPARE jc_consulta FROM @jc_q;
EXECUTE jc_consulta;
