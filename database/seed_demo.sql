-- ============================================================
--  database/seed_demo.sql — DADOS FICTÍCIOS DE DEMONSTRAÇÃO
--
--  Cria 15 empresas (todas as regiões do Brasil), vagas com requisitos
--  e benefícios, alguns locais de trabalho, 15 jovens com perfis variados
--  e algumas candidaturas. Serve para apresentar o sistema e testar.
--
--  NÃO USE EM PRODUÇÃO. Todas as contas usam a MESMA senha de demonstração,
--  documentada em docs/SEED_DEMO.md. CPFs e CNPJs têm dígitos verificadores
--  inválidos de propósito; e-mails usam o domínio reservado .example.
--
--  Pré-requisito: banco criado pelo schema.sql atual OU banco antigo com as
--  migrações de migrations/ aplicadas (endereço da empresa e locais de trabalho).
--
--  Seguro para rodar mais de uma vez: cada INSERT só acontece se o registro
--  ainda não existir (pelo e-mail da conta / título da vaga). Não apaga nem
--  altera nada que já está no banco. Não usa IDs fixos.
--
--  Como aplicar: phpMyAdmin → banco jovemconnect → aba SQL → colar e executar
--  (ou: mysql -u root jovemconnect < database/seed_demo.sql).
-- ============================================================

SET NAMES utf8mb4;

-- ── Empresas ─────────────────────────────────────────────────
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Rio Negro Logística (demo)', 'rionegro@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.001/0001-00', 'Manaus', 'AM', 'Logística', 'Operador logístico fictício que atende o comércio da Zona Franca.', '69005000', 'Avenida Eduardo Ribeiro', '520', 'Centro', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'rionegro@demo.jovemconnect.example' OR cnpj = '90.000.001/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Açaí do Pará Alimentos (demo)', 'acaipara@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.002/0001-00', 'Belém', 'PA', 'Vendas', 'Indústria fictícia de polpas de frutas regionais.', '66010000', 'Avenida Presidente Vargas', '310', 'Campina', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'acaipara@demo.jovemconnect.example' OR cnpj = '90.000.002/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Cerrado Tech Soluções (demo)', 'cerradotech@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.003/0001-00', 'Palmas', 'TO', 'TI', 'Pequena empresa fictícia de suporte técnico e sistemas.', '77001002', 'Quadra 104 Norte, Avenida LO 2', '15', 'Plano Diretor Norte', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'cerradotech@demo.jovemconnect.example' OR cnpj = '90.000.003/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Recife Mar Serviços Administrativos (demo)', 'recifemar@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.004/0001-00', 'Recife', 'PE', 'Administrativo', 'Escritório fictício de serviços administrativos para pequenas empresas.', '50030230', 'Rua do Bom Jesus', '125', 'Recife', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'recifemar@demo.jovemconnect.example' OR cnpj = '90.000.004/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Baía Digital Marketing (demo)', 'baiadigital@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.005/0001-00', 'Salvador', 'BA', 'Marketing', 'Agência fictícia de comunicação digital.', '40020000', 'Avenida Sete de Setembro', '1200', 'Centro', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'baiadigital@demo.jovemconnect.example' OR cnpj = '90.000.005/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Fortal Sol Varejo (demo)', 'fortalsol@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.006/0001-00', 'Fortaleza', 'CE', 'Vendas', 'Rede fictícia de lojas de utilidades domésticas.', '60060000', 'Rua Barão do Rio Branco', '800', 'Centro', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'fortalsol@demo.jovemconnect.example' OR cnpj = '90.000.006/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Goiás Agro Cooperativa (demo)', 'goiasagro@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.007/0001-00', 'Goiânia', 'GO', 'Administrativo', 'Cooperativa agrícola fictícia.', '74003010', 'Avenida Goiás', '400', 'Setor Central', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'goiasagro@demo.jovemconnect.example' OR cnpj = '90.000.007/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Planalto Saúde Clínicas (demo)', 'planalto@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.008/0001-00', 'Brasília', 'DF', 'Saúde', 'Rede fictícia de clínicas populares.', '70040010', 'SBS Quadra 2, Bloco E', 's/n', 'Asa Sul', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'planalto@demo.jovemconnect.example' OR cnpj = '90.000.008/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Pantanal Transportes (demo)', 'pantanal@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.009/0001-00', 'Campo Grande', 'MS', 'Logística', 'Transportadora fictícia de cargas regionais.', '79002000', 'Rua 14 de Julho', '1500', 'Centro', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'pantanal@demo.jovemconnect.example' OR cnpj = '90.000.009/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Paulista Educação (demo)', 'paulistaeduca@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.010/0001-00', 'São Paulo', 'SP', 'Educação', 'Escola de cursos livres fictícia.', '01310100', 'Avenida Paulista', '1000', 'Bela Vista', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'paulistaeduca@demo.jovemconnect.example' OR cnpj = '90.000.010/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Minas Dados Contabilidade (demo)', 'minasdados@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.011/0001-00', 'Belo Horizonte', 'MG', 'Financeiro', 'Escritório de contabilidade fictício.', '30130010', 'Avenida Afonso Pena', '700', 'Centro', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'minasdados@demo.jovemconnect.example' OR cnpj = '90.000.011/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Carioca Mobilidade (demo)', 'cariocamov@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.012/0001-00', 'Rio de Janeiro', 'RJ', 'TI', 'Startup fictícia de aplicativos de transporte.', '20040002', 'Avenida Rio Branco', '150', 'Centro', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'cariocamov@demo.jovemconnect.example' OR cnpj = '90.000.012/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Araucária Móveis (demo)', 'araucaria@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.013/0001-00', 'Curitiba', 'PR', 'Vendas', 'Fabricante fictício de móveis planejados.', '80010000', 'Rua XV de Novembro', '300', 'Centro', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'araucaria@demo.jovemconnect.example' OR cnpj = '90.000.013/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Guaíba Recursos Humanos (demo)', 'guaiba@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.014/0001-00', 'Porto Alegre', 'RS', 'Recursos Humanos', 'Consultoria fictícia de recrutamento.', '90010000', 'Rua dos Andradas', '1000', 'Centro Histórico', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'guaiba@demo.jovemconnect.example' OR cnpj = '90.000.014/0001-00');
INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, cep, logradouro, numero, bairro, email_verificado)
  SELECT 'Ilha da Magia Turismo (demo)', 'ilhamagia@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '90.000.015/0001-00', 'Florianópolis', 'SC', 'Marketing', 'Agência fictícia de turismo receptivo.', '88010000', 'Rua Felipe Schmidt', '200', 'Centro', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE email = 'ilhamagia@demo.jovemconnect.example' OR cnpj = '90.000.015/0001-00');

-- ── Locais de trabalho (além do endereço principal) ─────────
INSERT INTO empresa_locais (empresa_id, tipo, nome, cep, logradouro, numero, bairro, cidade, estado)
  SELECT e.id, 'filial', 'Loja Aldeota', '60150160', 'Avenida Santos Dumont', '2100', 'Aldeota', 'Fortaleza', 'CE' FROM empresas e
  WHERE e.email = 'fortalsol@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM empresa_locais l WHERE l.empresa_id = e.id AND l.nome = 'Loja Aldeota');
INSERT INTO empresa_locais (empresa_id, tipo, nome, cep, logradouro, numero, bairro, cidade, estado)
  SELECT e.id, 'unidade', 'Unidade Taguatinga', '72010010', 'QNA 1', '10', 'Taguatinga Norte', 'Brasília', 'DF' FROM empresas e
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM empresa_locais l WHERE l.empresa_id = e.id AND l.nome = 'Unidade Taguatinga');

-- ── Vagas (cidade/UF/CEP = local de trabalho, como o sistema grava) ──
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Jovem Aprendiz em Logística', 'Apoio no recebimento, conferência e organização de mercadorias no armazém.', e.cidade, e.estado, e.cep, 'empresa', '1.100,00', 'Jovem Aprendiz', '20h/semana', 'Logística'
  FROM empresas e
  WHERE e.email = 'rionegro@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Jovem Aprendiz em Logística');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'rionegro@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Logística'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Organização' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'rionegro@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Logística'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Organização');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Disponibilidade no período da tarde' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'rionegro@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Logística'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Disponibilidade no período da tarde');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'rionegro@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Logística'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-refeição' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'rionegro@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Logística'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-refeição');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Aprendiz de Atendimento ao Cliente', 'Atendimento por telefone e mensagens, registro de pedidos e apoio ao time comercial.', e.cidade, e.estado, e.cep, 'empresa', '1.050,00', 'Jovem Aprendiz', '20h/semana', 'Vendas'
  FROM empresas e
  WHERE e.email = 'acaipara@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Aprendiz de Atendimento ao Cliente');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Boa comunicação' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'acaipara@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Atendimento ao Cliente'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Boa comunicação');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'acaipara@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Atendimento ao Cliente'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'acaipara@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Atendimento ao Cliente'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Lanche no local' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'acaipara@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Atendimento ao Cliente'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Lanche no local');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Estágio em Suporte de TI', 'Atendimento de chamados, instalação de programas e manutenção básica de computadores.', e.cidade, e.estado, e.cep, 'empresa', '1.300,00', 'Estágio', '30h/semana', 'TI'
  FROM empresas e
  WHERE e.email = 'cerradotech@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Estágio em Suporte de TI');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Cursando Técnico ou Superior em TI' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'cerradotech@demo.jovemconnect.example' AND v.titulo = 'Estágio em Suporte de TI'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Cursando Técnico ou Superior em TI');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Noções de redes' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'cerradotech@demo.jovemconnect.example' AND v.titulo = 'Estágio em Suporte de TI'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Noções de redes');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Pacote Office' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'cerradotech@demo.jovemconnect.example' AND v.titulo = 'Estágio em Suporte de TI'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Pacote Office');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Bolsa-auxílio' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'cerradotech@demo.jovemconnect.example' AND v.titulo = 'Estágio em Suporte de TI'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Bolsa-auxílio');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'cerradotech@demo.jovemconnect.example' AND v.titulo = 'Estágio em Suporte de TI'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Jovem Aprendiz Administrativo', 'Arquivo de documentos, digitação de planilhas e apoio à recepção.', e.cidade, e.estado, e.cep, 'empresa', '1.100,00', 'Jovem Aprendiz', '20h/semana', 'Administrativo'
  FROM empresas e
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Jovem Aprendiz Administrativo');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz Administrativo'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Excel básico' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz Administrativo'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Excel básico');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz Administrativo'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-refeição' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz Administrativo'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-refeição');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Curso de capacitação' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz Administrativo'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Curso de capacitação');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Aprendiz de Recursos Humanos', 'Apoio na organização de documentos de admissão e na agenda de entrevistas.', e.cidade, e.estado, e.cep, 'empresa', '1.100,00', 'Jovem Aprendiz', '20h/semana', 'Recursos Humanos'
  FROM empresas e
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Aprendiz de Recursos Humanos');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Recursos Humanos'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Discrição com informações' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Recursos Humanos'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Discrição com informações');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Recursos Humanos'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Estágio em Marketing Digital', 'Criação de posts, acompanhamento de métricas e apoio em campanhas.', e.cidade, e.estado, e.cep, 'empresa', '1.200,00', 'Estágio', '30h/semana', 'Marketing'
  FROM empresas e
  WHERE e.email = 'baiadigital@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Estágio em Marketing Digital');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Cursando Marketing, Publicidade ou áreas afins' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'baiadigital@demo.jovemconnect.example' AND v.titulo = 'Estágio em Marketing Digital'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Cursando Marketing, Publicidade ou áreas afins');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Criatividade' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'baiadigital@demo.jovemconnect.example' AND v.titulo = 'Estágio em Marketing Digital'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Criatividade');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Noções de redes sociais' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'baiadigital@demo.jovemconnect.example' AND v.titulo = 'Estágio em Marketing Digital'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Noções de redes sociais');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Bolsa-auxílio' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'baiadigital@demo.jovemconnect.example' AND v.titulo = 'Estágio em Marketing Digital'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Bolsa-auxílio');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Home office parcial' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'baiadigital@demo.jovemconnect.example' AND v.titulo = 'Estágio em Marketing Digital'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Home office parcial');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Jovem Aprendiz em Vendas', 'Atendimento no salão de vendas, reposição e organização de vitrines.', e.cidade, e.estado, e.cep, 'empresa', '1.150,00', 'Jovem Aprendiz', '25h/semana', 'Vendas'
  FROM empresas e
  WHERE e.email = 'fortalsol@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Jovem Aprendiz em Vendas');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'fortalsol@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Vendas'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Simpatia no atendimento' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'fortalsol@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Vendas'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Simpatia no atendimento');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'fortalsol@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Vendas'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Desconto em compras' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'fortalsol@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Vendas'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Desconto em compras');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Aprendiz Financeiro', 'Lançamento de notas, conferência de pagamentos e apoio ao contas a pagar.', e.cidade, e.estado, e.cep, 'empresa', '1.100,00', 'Jovem Aprendiz', '20h/semana', 'Financeiro'
  FROM empresas e
  WHERE e.email = 'goiasagro@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Aprendiz Financeiro');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'goiasagro@demo.jovemconnect.example' AND v.titulo = 'Aprendiz Financeiro'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Matemática básica' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'goiasagro@demo.jovemconnect.example' AND v.titulo = 'Aprendiz Financeiro'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Matemática básica');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Excel básico' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'goiasagro@demo.jovemconnect.example' AND v.titulo = 'Aprendiz Financeiro'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Excel básico');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'goiasagro@demo.jovemconnect.example' AND v.titulo = 'Aprendiz Financeiro'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Plano odontológico' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'goiasagro@demo.jovemconnect.example' AND v.titulo = 'Aprendiz Financeiro'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Plano odontológico');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Jovem Aprendiz em Recepção de Clínica', 'Recepção de pacientes, agendamento e organização de prontuários.', e.cidade, e.estado, e.cep, 'empresa', '1.150,00', 'Jovem Aprendiz', '20h/semana', 'Saúde'
  FROM empresas e
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Jovem Aprendiz em Recepção de Clínica');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Recepção de Clínica'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Boa comunicação' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Recepção de Clínica'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Boa comunicação');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Recepção de Clínica'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-refeição' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Recepção de Clínica'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-refeição');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, local_id, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Estágio Técnico em Enfermagem', 'Apoio à equipe de enfermagem em triagem e organização de materiais.', 'Brasília', 'DF', '72010010', 'local', l.id, '1.250,00', 'Estágio', '30h/semana', 'Saúde'
  FROM empresas e JOIN empresa_locais l ON l.empresa_id = e.id AND l.nome = 'Unidade Taguatinga'
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Estágio Técnico em Enfermagem');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Cursando Técnico em Enfermagem' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND v.titulo = 'Estágio Técnico em Enfermagem'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Cursando Técnico em Enfermagem');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Bolsa-auxílio' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND v.titulo = 'Estágio Técnico em Enfermagem'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Bolsa-auxílio');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND v.titulo = 'Estágio Técnico em Enfermagem'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Aprendiz de Logística e Expedição', 'Separação de pedidos, emissão de etiquetas e controle de entregas.', e.cidade, e.estado, e.cep, 'empresa', '1.050,00', 'Jovem Aprendiz', '20h/semana', 'Logística'
  FROM empresas e
  WHERE e.email = 'pantanal@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Aprendiz de Logística e Expedição');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'pantanal@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Logística e Expedição'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Atenção a detalhes' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'pantanal@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Logística e Expedição'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Atenção a detalhes');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'pantanal@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Logística e Expedição'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Jovem Aprendiz em Secretaria Escolar', 'Atendimento a alunos e responsáveis, matrículas e organização de turmas.', e.cidade, e.estado, e.cep, 'empresa', '1.200,00', 'Jovem Aprendiz', '20h/semana', 'Educação'
  FROM empresas e
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Jovem Aprendiz em Secretaria Escolar');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Secretaria Escolar'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Organização' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Secretaria Escolar'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Organização');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Secretaria Escolar'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Bolsa em curso de idiomas' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Secretaria Escolar'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Bolsa em curso de idiomas');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Estágio em Desenvolvimento Web', 'Manutenção do site da escola e de páginas de cursos (HTML, CSS e PHP).', e.cidade, e.estado, e.cep, 'empresa', '1.500,00', 'Estágio', '30h/semana', 'TI'
  FROM empresas e
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Estágio em Desenvolvimento Web');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Cursando Análise e Desenvolvimento de Sistemas ou similar' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND v.titulo = 'Estágio em Desenvolvimento Web'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Cursando Análise e Desenvolvimento de Sistemas ou similar');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'HTML e CSS' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND v.titulo = 'Estágio em Desenvolvimento Web'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'HTML e CSS');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Noções de PHP' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND v.titulo = 'Estágio em Desenvolvimento Web'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Noções de PHP');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Bolsa-auxílio' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND v.titulo = 'Estágio em Desenvolvimento Web'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Bolsa-auxílio');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-refeição' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND v.titulo = 'Estágio em Desenvolvimento Web'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-refeição');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Aprendiz em Contabilidade', 'Organização de documentos fiscais e apoio na conferência de lançamentos.', e.cidade, e.estado, e.cep, 'empresa', '1.100,00', 'Jovem Aprendiz', '20h/semana', 'Financeiro'
  FROM empresas e
  WHERE e.email = 'minasdados@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Aprendiz em Contabilidade');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'minasdados@demo.jovemconnect.example' AND v.titulo = 'Aprendiz em Contabilidade'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Interesse por números' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'minasdados@demo.jovemconnect.example' AND v.titulo = 'Aprendiz em Contabilidade'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Interesse por números');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'minasdados@demo.jovemconnect.example' AND v.titulo = 'Aprendiz em Contabilidade'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-refeição' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'minasdados@demo.jovemconnect.example' AND v.titulo = 'Aprendiz em Contabilidade'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-refeição');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Estágio em Análise de Dados', 'Montagem de planilhas e relatórios sobre o uso do aplicativo.', e.cidade, e.estado, e.cep, 'empresa', '1.600,00', 'Estágio', '30h/semana', 'TI'
  FROM empresas e
  WHERE e.email = 'cariocamov@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Estágio em Análise de Dados');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Cursando Sistemas de Informação, Estatística ou similar' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'cariocamov@demo.jovemconnect.example' AND v.titulo = 'Estágio em Análise de Dados'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Cursando Sistemas de Informação, Estatística ou similar');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Excel intermediário' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'cariocamov@demo.jovemconnect.example' AND v.titulo = 'Estágio em Análise de Dados'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Excel intermediário');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Bolsa-auxílio' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'cariocamov@demo.jovemconnect.example' AND v.titulo = 'Estágio em Análise de Dados'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Bolsa-auxílio');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Home office parcial' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'cariocamov@demo.jovemconnect.example' AND v.titulo = 'Estágio em Análise de Dados'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Home office parcial');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Jovem Aprendiz em Vendas Internas', 'Atendimento a clientes no showroom, orçamentos simples e cadastro de pedidos.', e.cidade, e.estado, e.cep, 'empresa', '1.150,00', 'Jovem Aprendiz', '20h/semana', 'Vendas'
  FROM empresas e
  WHERE e.email = 'araucaria@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Jovem Aprendiz em Vendas Internas');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'araucaria@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Vendas Internas'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Boa comunicação' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'araucaria@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Vendas Internas'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Boa comunicação');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'araucaria@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Vendas Internas'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-alimentação' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'araucaria@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Vendas Internas'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-alimentação');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Estágio em Recursos Humanos', 'Triagem de currículos, agendamento de entrevistas e apoio em integrações.', e.cidade, e.estado, e.cep, 'empresa', '1.300,00', 'Estágio', '30h/semana', 'Recursos Humanos'
  FROM empresas e
  WHERE e.email = 'guaiba@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Estágio em Recursos Humanos');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Cursando Psicologia ou Gestão de RH' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'guaiba@demo.jovemconnect.example' AND v.titulo = 'Estágio em Recursos Humanos'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Cursando Psicologia ou Gestão de RH');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Pacote Office' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'guaiba@demo.jovemconnect.example' AND v.titulo = 'Estágio em Recursos Humanos'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Pacote Office');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Bolsa-auxílio' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'guaiba@demo.jovemconnect.example' AND v.titulo = 'Estágio em Recursos Humanos'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Bolsa-auxílio');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'guaiba@demo.jovemconnect.example' AND v.titulo = 'Estágio em Recursos Humanos'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, salario, tipo_contrato, carga_horaria, area)
  SELECT e.id, 'Jovem Aprendiz em Atendimento e Marketing', 'Atendimento a turistas, divulgação de passeios nas redes sociais e organização de reservas.', e.cidade, e.estado, e.cep, 'empresa', '1.150,00', 'Jovem Aprendiz', '20h/semana', 'Marketing'
  FROM empresas e
  WHERE e.email = 'ilhamagia@demo.jovemconnect.example' AND NOT EXISTS (SELECT 1 FROM vagas v WHERE v.empresa_id = e.id AND v.titulo = 'Jovem Aprendiz em Atendimento e Marketing');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Ensino Médio em curso' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'ilhamagia@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Atendimento e Marketing'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Ensino Médio em curso');
INSERT INTO requisitos_vaga (vaga_id, requisito)
  SELECT v.id, 'Gostar de atender pessoas' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'ilhamagia@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Atendimento e Marketing'
    AND NOT EXISTS (SELECT 1 FROM requisitos_vaga x WHERE x.vaga_id = v.id AND x.requisito = 'Gostar de atender pessoas');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Vale-transporte' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'ilhamagia@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Atendimento e Marketing'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Vale-transporte');
INSERT INTO beneficios_vaga (vaga_id, beneficio)
  SELECT v.id, 'Passeios da agência' FROM vagas v JOIN empresas e ON e.id = v.empresa_id
  WHERE e.email = 'ilhamagia@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Atendimento e Marketing'
    AND NOT EXISTS (SELECT 1 FROM beneficios_vaga x WHERE x.vaga_id = v.id AND x.beneficio = 'Passeios da agência');

-- ── Jovens ───────────────────────────────────────────────────
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Ana Beatriz Souza', 'ana@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.001-00', '2008-04-12', 'Manaus', 'AM', '69005010', 'Ensino Médio — 2º ano', 'cursando', 'regular', NULL, 'Logística', 'Estudante do 2º ano, organizada e pontual. Quero meu primeiro emprego na área de logística.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'ana@demo.jovemconnect.example' OR cpf = '900.000.001-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Organização' FROM jovens j WHERE j.email = 'ana@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Organização');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Excel básico' FROM jovens j WHERE j.email = 'ana@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Excel básico');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Trabalho em equipe' FROM jovens j WHERE j.email = 'ana@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Trabalho em equipe');
INSERT INTO experiencias_jovem (jovem_id, titulo, local, periodo, descricao)
  SELECT j.id, 'Voluntária na feira de ciências', 'Escola Estadual (fictícia)', '2025', 'Organização dos estandes e recepção dos visitantes.' FROM jovens j WHERE j.email = 'ana@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM experiencias_jovem x WHERE x.jovem_id = j.id AND x.titulo = 'Voluntária na feira de ciências');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Bruno Henrique Lima', 'bruno@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.002-00', '2007-09-30', 'Belém', 'PA', '66015000', 'Ensino Médio — 3º ano', 'cursando', 'regular', NULL, 'Vendas', 'Comunicativo, gosto de conversar com pessoas e resolver problemas.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'bruno@demo.jovemconnect.example' OR cpf = '900.000.002-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Comunicação' FROM jovens j WHERE j.email = 'bruno@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Comunicação');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Atendimento ao cliente' FROM jovens j WHERE j.email = 'bruno@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Atendimento ao cliente');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Carla Mendes Rocha', 'carla@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.003-00', '2006-02-18', 'Palmas', 'TO', '77001010', 'Ensino Técnico — TI', 'cursando', 'tecnico', 'TI', 'TI', 'Estudante de técnico em TI, curto montar computadores e ajudar colegas com tecnologia.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'carla@demo.jovemconnect.example' OR cpf = '900.000.003-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Montagem de computadores' FROM jovens j WHERE j.email = 'carla@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Montagem de computadores');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Windows' FROM jovens j WHERE j.email = 'carla@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Windows');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Redes básicas' FROM jovens j WHERE j.email = 'carla@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Redes básicas');
INSERT INTO formacao_jovem (jovem_id, titulo, instituicao, conclusao, area)
  SELECT j.id, 'Técnico em Informática', 'Instituto Técnico (fictício)', 'Em andamento', 'TI' FROM jovens j WHERE j.email = 'carla@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM formacao_jovem x WHERE x.jovem_id = j.id AND x.titulo = 'Técnico em Informática');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Diego Ferreira Alves', 'diego@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.004-00', '2008-11-05', 'Recife', 'PE', '50030000', 'Ensino Médio — 1º ano', 'cursando', 'regular', NULL, 'Administrativo', 'Primeiro emprego. Aprendo rápido e gosto de organizar documentos.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'diego@demo.jovemconnect.example' OR cpf = '900.000.004-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Digitação' FROM jovens j WHERE j.email = 'diego@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Digitação');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Organização' FROM jovens j WHERE j.email = 'diego@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Organização');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Elisa Cristina Nunes', 'elisa@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.005-00', '2007-06-22', 'Salvador', 'BA', '40020010', 'Ensino Médio — 3º ano', 'cursando', 'tecnico', 'Marketing', 'Marketing', 'Faço técnico em Marketing junto com o Ensino Médio e cuido das redes sociais do grêmio.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'elisa@demo.jovemconnect.example' OR cpf = '900.000.005-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Canva' FROM jovens j WHERE j.email = 'elisa@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Canva');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Redes sociais' FROM jovens j WHERE j.email = 'elisa@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Redes sociais');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Criatividade' FROM jovens j WHERE j.email = 'elisa@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Criatividade');
INSERT INTO formacao_jovem (jovem_id, titulo, instituicao, conclusao, area)
  SELECT j.id, 'Curso livre de Fotografia', 'Centro Cultural (fictício)', '2024', NULL FROM jovens j WHERE j.email = 'elisa@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM formacao_jovem x WHERE x.jovem_id = j.id AND x.titulo = 'Curso livre de Fotografia');
INSERT INTO experiencias_jovem (jovem_id, titulo, local, periodo, descricao)
  SELECT j.id, 'Social media do grêmio estudantil', 'Escola (fictícia)', '2024 – atual', 'Criação de posts e cobertura de eventos.' FROM jovens j WHERE j.email = 'elisa@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM experiencias_jovem x WHERE x.jovem_id = j.id AND x.titulo = 'Social media do grêmio estudantil');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Felipe Augusto Barros', 'felipe@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.006-00', '2008-01-14', 'Fortaleza', 'CE', '60060010', 'Ensino Médio — 2º ano', 'cursando', 'regular', NULL, 'Vendas', 'Gosto de atender o público. Ajudo na loja da família nos fins de semana.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'felipe@demo.jovemconnect.example' OR cpf = '900.000.006-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Atendimento ao cliente' FROM jovens j WHERE j.email = 'felipe@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Atendimento ao cliente');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Caixa' FROM jovens j WHERE j.email = 'felipe@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Caixa');
INSERT INTO experiencias_jovem (jovem_id, titulo, local, periodo, descricao)
  SELECT j.id, 'Ajudante na loja da família', 'Comércio familiar', '2023 – atual', 'Atendimento e reposição de produtos.' FROM jovens j WHERE j.email = 'felipe@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM experiencias_jovem x WHERE x.jovem_id = j.id AND x.titulo = 'Ajudante na loja da família');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Gabriela Torres Pinto', 'gabriela@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.007-00', '2006-08-03', 'Goiânia', 'GO', '74003020', 'Ensino Médio — 3º ano', 'completo', 'regular', NULL, 'Financeiro', 'Concluí o Ensino Médio e quero começar na área financeira.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'gabriela@demo.jovemconnect.example' OR cpf = '900.000.007-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Excel básico' FROM jovens j WHERE j.email = 'gabriela@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Excel básico');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Matemática' FROM jovens j WHERE j.email = 'gabriela@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Matemática');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Organização' FROM jovens j WHERE j.email = 'gabriela@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Organização');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Heitor Gomes Cardoso', 'heitor@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.008-00', '2005-12-09', 'Brasília', 'DF', '72010020', 'Ensino Técnico — Enfermagem', 'cursando', 'tecnico', 'Enfermagem', 'Saúde', 'Estudante de técnico em Enfermagem, cuidadoso e responsável.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'heitor@demo.jovemconnect.example' OR cpf = '900.000.008-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Primeiros socorros' FROM jovens j WHERE j.email = 'heitor@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Primeiros socorros');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Empatia' FROM jovens j WHERE j.email = 'heitor@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Empatia');
INSERT INTO formacao_jovem (jovem_id, titulo, instituicao, conclusao, area)
  SELECT j.id, 'Técnico em Enfermagem', 'Escola Técnica (fictícia)', 'Em andamento', 'Enfermagem' FROM jovens j WHERE j.email = 'heitor@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM formacao_jovem x WHERE x.jovem_id = j.id AND x.titulo = 'Técnico em Enfermagem');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Isabela Martins Freitas', 'isabela@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.009-00', '2008-03-27', 'Campo Grande', 'MS', '79002010', 'Ensino Médio — 2º ano', 'cursando', 'tecnico', 'Logística', 'Logística', 'Curso técnico em Logística junto com o Ensino Médio.', 0, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'isabela@demo.jovemconnect.example' OR cpf = '900.000.009-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Controle de estoque' FROM jovens j WHERE j.email = 'isabela@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Controle de estoque');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Excel básico' FROM jovens j WHERE j.email = 'isabela@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Excel básico');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'João Pedro Ribeiro', 'joao@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.010-00', '2005-05-16', 'São Paulo', 'SP', '01311000', 'Ensino Superior — Análise e Desenvolvimento de Sistemas', 'cursando', 'regular', NULL, 'TI', 'Estudante de ADS, faço sites simples e estou aprendendo PHP.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'joao@demo.jovemconnect.example' OR cpf = '900.000.010-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'HTML' FROM jovens j WHERE j.email = 'joao@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'HTML');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'CSS' FROM jovens j WHERE j.email = 'joao@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'CSS');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'PHP básico' FROM jovens j WHERE j.email = 'joao@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'PHP básico');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Git' FROM jovens j WHERE j.email = 'joao@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Git');
INSERT INTO formacao_jovem (jovem_id, titulo, instituicao, conclusao, area)
  SELECT j.id, 'Curso livre de Lógica de Programação', 'Plataforma on-line (fictícia)', '2024', 'TI' FROM jovens j WHERE j.email = 'joao@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM formacao_jovem x WHERE x.jovem_id = j.id AND x.titulo = 'Curso livre de Lógica de Programação');
INSERT INTO experiencias_jovem (jovem_id, titulo, local, periodo, descricao)
  SELECT j.id, 'Site da associação do bairro', 'Trabalho voluntário', '2025', 'Criação de um site com HTML e CSS.' FROM jovens j WHERE j.email = 'joao@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM experiencias_jovem x WHERE x.jovem_id = j.id AND x.titulo = 'Site da associação do bairro');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Karina Oliveira Duarte', 'karina@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.011-00', '2007-10-11', 'Belo Horizonte', 'MG', '30130020', 'Ensino Médio — 3º ano', 'cursando', 'tecnico', 'Contabilidade', 'Financeiro', 'Faço técnico em Contabilidade e gosto de trabalhar com números.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'karina@demo.jovemconnect.example' OR cpf = '900.000.011-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Excel' FROM jovens j WHERE j.email = 'karina@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Excel');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Matemática financeira' FROM jovens j WHERE j.email = 'karina@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Matemática financeira');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Lucas Vinícius Moreira', 'lucas@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.012-00', '2004-07-01', 'Rio de Janeiro', 'RJ', '20040010', 'Ensino Superior — Sistemas de Informação', 'cursando', 'regular', NULL, 'TI', 'Estudante de Sistemas de Informação com interesse em dados.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'lucas@demo.jovemconnect.example' OR cpf = '900.000.012-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Excel intermediário' FROM jovens j WHERE j.email = 'lucas@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Excel intermediário');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'SQL básico' FROM jovens j WHERE j.email = 'lucas@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'SQL básico');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Python básico' FROM jovens j WHERE j.email = 'lucas@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Python básico');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Marina Lopes Teixeira', 'marina@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.013-00', '2008-09-19', 'Curitiba', 'PR', '80010010', 'Ensino Médio — 1º ano', 'cursando', 'regular', NULL, 'Vendas', 'Tímida no começo, mas muito dedicada. Quero aprender sobre vendas.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'marina@demo.jovemconnect.example' OR cpf = '900.000.013-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Dedicação' FROM jovens j WHERE j.email = 'marina@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Dedicação');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Pontualidade' FROM jovens j WHERE j.email = 'marina@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Pontualidade');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Nicolas Prado Azevedo', 'nicolas@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.014-00', '2005-03-08', 'Porto Alegre', 'RS', '90010010', 'Ensino Superior — Psicologia', 'cursando', 'regular', NULL, 'Recursos Humanos', 'Estudante de Psicologia buscando estágio em Recursos Humanos.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'nicolas@demo.jovemconnect.example' OR cpf = '900.000.014-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Escuta ativa' FROM jovens j WHERE j.email = 'nicolas@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Escuta ativa');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Pacote Office' FROM jovens j WHERE j.email = 'nicolas@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Pacote Office');
INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, area_interesse, resumo_profissional, perfil_publico, email_verificado)
  SELECT 'Olívia Santana Reis', 'olivia@demo.jovemconnect.example', '$2y$10$QPao6Yo6nherb68d2sejDubfLqHxm/HIzNlDamnNLS5ypsEMEkH6O', '900.000.015-00', '2007-12-25', 'Florianópolis', 'SC', '88010010', 'Ensino Médio — 3º ano', 'cursando', 'regular', NULL, 'Marketing', 'Falo um pouco de espanhol e adoro mostrar a cidade para quem visita.', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM jovens WHERE email = 'olivia@demo.jovemconnect.example' OR cpf = '900.000.015-00');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Espanhol básico' FROM jovens j WHERE j.email = 'olivia@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Espanhol básico');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Comunicação' FROM jovens j WHERE j.email = 'olivia@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Comunicação');
INSERT INTO habilidades_jovem (jovem_id, habilidade)
  SELECT j.id, 'Redes sociais' FROM jovens j WHERE j.email = 'olivia@demo.jovemconnect.example'
    AND NOT EXISTS (SELECT 1 FROM habilidades_jovem x WHERE x.jovem_id = j.id AND x.habilidade = 'Redes sociais');

-- ── Candidaturas ─────────────────────────────────────────────
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'ana@demo.jovemconnect.example'
  WHERE e.email = 'rionegro@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Logística'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'bruno@demo.jovemconnect.example'
  WHERE e.email = 'acaipara@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Atendimento ao Cliente'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'carla@demo.jovemconnect.example'
  WHERE e.email = 'cerradotech@demo.jovemconnect.example' AND v.titulo = 'Estágio em Suporte de TI'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'diego@demo.jovemconnect.example'
  WHERE e.email = 'recifemar@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz Administrativo'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'elisa@demo.jovemconnect.example'
  WHERE e.email = 'baiadigital@demo.jovemconnect.example' AND v.titulo = 'Estágio em Marketing Digital'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'felipe@demo.jovemconnect.example'
  WHERE e.email = 'fortalsol@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Vendas'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'gabriela@demo.jovemconnect.example'
  WHERE e.email = 'goiasagro@demo.jovemconnect.example' AND v.titulo = 'Aprendiz Financeiro'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'heitor@demo.jovemconnect.example'
  WHERE e.email = 'planalto@demo.jovemconnect.example' AND v.titulo = 'Estágio Técnico em Enfermagem'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'isabela@demo.jovemconnect.example'
  WHERE e.email = 'pantanal@demo.jovemconnect.example' AND v.titulo = 'Aprendiz de Logística e Expedição'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'joao@demo.jovemconnect.example'
  WHERE e.email = 'paulistaeduca@demo.jovemconnect.example' AND v.titulo = 'Estágio em Desenvolvimento Web'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'karina@demo.jovemconnect.example'
  WHERE e.email = 'minasdados@demo.jovemconnect.example' AND v.titulo = 'Aprendiz em Contabilidade'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'lucas@demo.jovemconnect.example'
  WHERE e.email = 'cariocamov@demo.jovemconnect.example' AND v.titulo = 'Estágio em Análise de Dados'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'nicolas@demo.jovemconnect.example'
  WHERE e.email = 'guaiba@demo.jovemconnect.example' AND v.titulo = 'Estágio em Recursos Humanos'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'olivia@demo.jovemconnect.example'
  WHERE e.email = 'ilhamagia@demo.jovemconnect.example' AND v.titulo = 'Jovem Aprendiz em Atendimento e Marketing'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);
INSERT INTO candidaturas (vaga_id, jovem_id)
  SELECT v.id, j.id FROM vagas v JOIN empresas e ON e.id = v.empresa_id JOIN jovens j ON j.email = 'olivia@demo.jovemconnect.example'
  WHERE e.email = 'baiadigital@demo.jovemconnect.example' AND v.titulo = 'Estágio em Marketing Digital'
    AND NOT EXISTS (SELECT 1 FROM candidaturas c WHERE c.vaga_id = v.id AND c.jovem_id = j.id);

-- Fim do seed de demonstração.
