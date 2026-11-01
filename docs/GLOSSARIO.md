# Glossário

> Definições com o significado **efetivo no código** da v4.00 (auditoria de 10/10/2026).

| Termo | Significado no JovemConnect | Onde aparece |
|---|---|---|
| **Usuário** | Quem usa o site. Pode ser visitante (sem sessão), jovem ou empresa. Não existe tabela "usuários": jovens e empresas ficam em tabelas separadas. | `jovens`, `empresas`, `$_SESSION` |
| **Visitante** | Pessoa sem login. Vê a página inicial, os documentos e o assistente. | `index.php`, `IA/assistente_ajax.php` |
| **Jovem** | Conta de candidato (pessoa física, CPF). Identificada por `$_SESSION['jovem_id']`. | `jovens`, `jovem/` |
| **Empresa** | Conta de contratante (CNPJ). Identificada por `$_SESSION['empresa_id']`. | `empresas`, `empresa/` |
| **Perfil profissional** | Dados do jovem visíveis em `jovem/perfil.php`: formação, área de interesse, habilidades, experiências, resumo e currículo. Tem duas visões: a do dono e a da empresa (sem CPF, CEP, endereço, telefone, nascimento). | `jovem/perfil.php`, `includes/candidato.php` |
| **Perfil público** | Opção "Permitir que empresas encontrem seu currículo nas buscas" (`perfil_publico`). **Desligada por padrão desde a v4.60** (antes, ligada); só o próprio jovem a liga. Libera o perfil a **qualquer** empresa logada. | `jovens.perfil_publico` |
| **Formação principal** | Nível + curso/ano + situação, guardados em `jovens` (`formacao`, `escolaridade_tipo`, `escolaridade_curso`, `escolaridade_status`). | `includes/formacao.php` |
| **Formação adicional** | Outras formações em `formacao_jovem`. Não entram na nota do Match. | `jovem/perfil.php` |
| **Currículo** | PDF enviado pelo jovem (`uploads/curriculos/curriculo_{id}.pdf`), entregue só por `uploads/ver_curriculo.php`. Não há gerador de currículo. | `uploads/` |
| **Vaga** | Oportunidade publicada por uma empresa (`vagas`), com contrato (Jovem Aprendiz, Estágio, CLT, PJ), área, local, requisitos e benefícios. Não tem status: existe ou foi removida. | `Vagas/criar.php` |
| **Local de trabalho** | Endereço onde a vaga acontece: o endereço principal da empresa ou um local em `empresa_locais` (escritório, filial, unidade, outro). | `includes/locais.php` |
| **Local efetivo** | O local usado na listagem, na análise e no Match: local escolhido → endereço da empresa → cidade/UF da própria vaga (vagas antigas). | `jc_local_efetivo()` |
| **Candidatura** | Registro de um jovem numa vaga (`candidaturas`), única por par, com status. | `Vagas/candidatar.php` |
| **Status da candidatura** | Um de: Recebido, Visualizado, Em análise, Aprovado, Reprovado. Alterado só pela empresa. | `Vagas/candidatos.php` |
| **Favorito** | Vaga marcada com estrela pelo jovem (`favoritos`). | `Vagas/favoritar.php` |
| **Match** / **compatibilidade** | Nota de 0 a 100 entre um jovem e uma vaga, calculada por **regras fixas** (localização, área, curso técnico, escolaridade × contrato, tipo de contrato, ajuste). Apesar do nome "Match IA", não é calculada por IA. | `IA/recomendar.py`, `includes/match.php` |
| **Match tradicional / cálculo local** | O mesmo algoritmo executado em PHP quando o Flask não responde. | `jc_match_calcular()` |
| **Cache do Match** | Nota guardada por par em `avaliacoes_ia`; recalculada quando a nota muda. | `IA/avaliar_ajax.php` |
| **Análise contextual** | Texto opcional gerado pelo Gemini que explica a nota; nunca a altera. | `Vagas/avaliar.php`, `/analise_contextual` |
| **IA Advisor** | Botão do perfil do jovem que pede sugestões de currículo ao Flask (`/curriculo`). | `jovem/perfil.php` |
| **Mentor de Currículo** | Página `Vagas/curriculoIA.php`, sem link no site. | — |
| **Assistente** | Chat ✦ que responde dúvidas sobre o site com o Gemini ou respostas prontas; não executa ações. | `includes/assistente.php` |
| **Respostas prontas** | Lista fixa `RESPOSTAS` em JavaScript usada quando a IA não responde. | `includes/assistente.php` |
| **Serviço de IA** | Aplicação Flask `IA/recomendar.py` em `127.0.0.1` (porta 5000 por padrão ou a próxima livre de 5001–5010, gravada em `logs/ia_porta.json`), chamada só pelo PHP. Inclui o Match e a ponte para o Gemini. | `iniciar_ia.bat` |
| **Gemini** | API de IA generativa do Google, opcional (`GEMINI_API_KEY`). | `IA/recomendar.py` |
| **Integração antiga** | Uso do pacote `google-generativeai` com `gemini-1.5-flash`; só ativa com variável de ambiente real e o pacote instalado. | `IA/recomendar.py` |
| **Endpoint** | Arquivo PHP ou rota do Flask que responde JSON para chamadas `fetch` (não uma página). | `IA/avaliar_ajax.php`, `Vagas/favoritar.php` |
| **Rota** | Qualquer URL do sistema; no PHP, o caminho do arquivo. | [ROTAS_E_ENDPOINTS.md](ROTAS_E_ENDPOINTS.md) |
| **Sessão** | Sessão PHP (cookie `PHPSESSID`) com o tipo e o ID da conta; endurecida por `jc_session_start()`. | `includes/session.php` |
| **Token CSRF** | Valor aleatório por sessão exigido em todo POST que altera dados. | `includes/csrf.php` |
| **Envio único** | Atributo `data-envio-unico` que trava o botão após o primeiro envio. | `includes/assistente.php` |
| **Cadastro em etapas** | Formulário único dividido em 4 seções exibidas uma por vez com JavaScript. | `includes/etapas.php` |
| **Aceite dos termos** | Caixa obrigatória no cadastro; registra versão e data em `termos_versao`/`termos_aceitos_em`. | `includes/termos.php` |
| **Minuta** | Rótulo dos textos dos Termos e da Política: versão provisória, sem revisão jurídica. | `includes/documentos.php` |
| **Verificação** | Confirmação opcional de e-mail ou telefone por código de 6 dígitos; não libera nem bloqueia funções. | `verificacoes/` |
| **Migração** | Arquivo SQL aditivo em `migrations/`, aplicado uma vez em bancos existentes, na ordem da data. | [MIGRACOES.md](MIGRACOES.md) |
| **Schema** | `schema.sql`: estrutura completa para instalação nova; recria tabelas. | [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md) |
| **Seed de demonstração** | `database/seed_demo.sql`: dados fictícios para apresentação. | [SEED_DEMO.md](SEED_DEMO.md) |
| **Stage** | Etapa de evolução do projeto (Stage 3, 4, 5, 5.1, 6), citada em comentários e migrações. | código |
| **Réplica** | Cópia do projeto em ambiente de desenvolvimento isolado onde os testes desta auditoria rodaram. | [TESTES.md](TESTES.md) |
| **Evento de log** | Linha JSON `area.acao` em `logs/site/app.log`, `logs/seguranca/seguranca.log` ou `logs/ia/ia.log`. | [LOGS.md](LOGS.md) |
| **`req`** | Identificador da requisição, repassado do PHP ao Flask em `X-Request-Id` para correlacionar logs. | `jc_req_id()` |
