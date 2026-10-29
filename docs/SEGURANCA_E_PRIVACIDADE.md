# Segurança e privacidade

> **Classificação: CONFIDENCIAL (uso interno da equipe).** Descreve riscos de segurança, inclusive os ainda abertos, e como foram reproduzidos.
> Não copie para o texto público do TCC, slides ou repositórios públicos: use a versão resumida de
> [CLASSIFICACAO.md](CLASSIFICACAO.md#versões-resumidas-para-o-tcc). Desde a v4.60 a pasta `docs/` não é
> entregue pelo Apache (confira com `scripts\seguranca\verificar_protecao.bat`).

> **Situação atual (v4.60, 29/10/2026): ver [Situação na v4.60](#situação-na-v460)**, logo abaixo. O restante do
> documento registra a auditoria de 10/10/2026 sobre a versão **v4.00**, com atualizações pontuais marcadas.
>
> Auditoria de 10/10/2026 sobre a versão **v4.00**. **Nada foi corrigido nesta auditoria**: as falhas abaixo
> estão registradas como pendências e aguardam autorização para mudança de código
> ([MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md#pendências-que-exigem-mudança-de-código)).
>
> Classificação usada:
> - **Verificado por teste** — exercitado por teste na réplica local, com contas fictícias.
> - **Confirmado no código** — lido no código; sem teste específico.
> - **Parcialmente implementado** — existe, mas com lacuna relevante.
> - **Ausente** — não existe.
> - **Não foi possível confirmar** — depende do servidor real (Apache, php.ini, rede), que não foi acessado.
>
> **Atualização da Stage 7 (11/10/2026):** a Central administrativa, a validação/moderação de conteúdo e a trilha
> de auditoria entraram como mecanismos novos (tabela abaixo, linhas marcadas "Stage 7"; detalhes em
> [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md)). A Stage 7 resolveu SEG-18 e SEG-24 e tratou
> parte de SEG-19; **SEG-01 continua pendente** (aguarda autorização) e os demais riscos seguem como estavam.

## Situação na v4.60

Correção de segurança e de propriedade intelectual de 29/10/2026. Relatório completo, testes e pendências:
[RELATORIO_CORRECAO_SEGURANCA.md](RELATORIO_CORRECAO_SEGURANCA.md). Estados usados:
**CORRIGIDO E VALIDADO** (correção feita e teste relevante passou) · **CORRIGIDO, VALIDAÇÃO PENDENTE** (falta o
teste no ambiente adequado: Apache/MariaDB do XAMPP) · **PENDÊNCIA EXTERNA** (depende de ação, acesso ou
decisão fora do código) · **sem mudança** (fora do escopo desta correção; continua como descrito abaixo).

| ID | Situação | O que existe agora | Teste |
|---|---|---|---|
| SEG-01 | CORRIGIDO E VALIDADO (Lote A, 12/10/2026) | recuperação presa à conta do pedido e de uso único; a v4.60 acrescentou o limite de erros do código | Lote A na réplica (37/37 unidade, 32/32 HTTP); fluxo com limite: `regressao_isolada.bat` (COD-01, LIM-03/04) |
| SEG-02 | CORRIGIDO, VALIDAÇÃO PENDENTE | `includes/tentativas.php`: login com 5 falhas por conta e 30 por IP em 15 min, mais atraso de até 2 s; códigos de 6 dígitos: 5 erros cancelam o código, 50 erros por IP em 15 min. Contadores fora da pasta pública, só com horários | unidade 17/17 (réplica); HTTP no PC: LIM-01 a LIM-04 |
| SEG-03 | CORRIGIDO, VALIDAÇÃO PENDENTE (contas novas) · PENDÊNCIA EXTERNA (contas antigas) | o cadastro grava `perfil_publico = 0` e o `schema.sql` tem `DEFAULT 0`; o jovem liga a visibilidade no perfil. Contas existentes não foram alteradas (SQL opcional em [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md#jovens)). **Decisão de 29/10/2026:** a demonstração usa banco novo (`schema.sql` atual + seed), sem contas antigas ([APRESENTACAO.md](APRESENTACAO.md)) | CAD-01 a CAD-03 no PC |
| SEG-04 | CORRIGIDO, VALIDAÇÃO PENDENTE | `.htaccess` na raiz (pastas internas, arquivos ocultos, segredos, documentos, SQL, Python, scripts, backups; sem listagem de pastas; também os apelidos do Windows — `.git.`, `GIT~1`, `ENV~1`, `::$DATA` —, acrescentados na segunda passagem de auditoria) e em `docs/`, `database/`, `migrations/`, `includes/` | regras simuladas na réplica; no Apache real: `scripts\seguranca\verificar_protecao.bat` e o passo automático do `iniciar_jovemconnect.bat` |
| SEG-05 | CORRIGIDO E VALIDADO | IA Advisor monta a resposta com `createElement`/`textContent` | Chromium na réplica 7/7 (antes da correção, um `<script>` simulado executava; depois, aparece como texto) |
| SEG-06 | CORRIGIDO E VALIDADO (navegador e rede) | CORS retirado; o Flask só atende pedidos com `Host` local (anti *DNS rebinding*), corpo até 512 KB, continua só em `127.0.0.1`; as rotas só aceitam JSON (que outro site não consegue enviar sem CORS) | Flask 12/12 na réplica. **Risco residual:** um programa rodando no próprio computador pode chamar o Flask sem passar pelos limites do PHP — mas esse programa também conseguiria ler o `.env` |
| SEG-07 | CORRIGIDO, VALIDAÇÃO PENDENTE (em parte) | e-mail, SMS e recuperação: 30 s entre envios, 5 por hora por conta, 20 por hora por IP; a recuperação responde igual acima do limite (não revela contas) | unidade (réplica); LIM no PC. Sem teto diário |
| SEG-08 | ACEITO FORMALMENTE (temporário, até depois da apresentação; decisão de 29/10/2026) | exige migração do banco (colunas `VARCHAR(6)`), que só o responsável pode aplicar; mitigado pelo limite de 5 erros e pela validade curta do código | — |
| SEG-09 | CORRIGIDO, VALIDAÇÃO PENDENTE | a sessão guarda uma impressão da senha (hash do hash); trocar a senha encerra as outras sessões na requisição seguinte; token CSRF renovado no login | unidade 7/7 (réplica); SESS-01/02 no PC |
| SEG-10 | CORRIGIDO, VALIDAÇÃO PENDENTE | currículo salvo como `curriculo_{id}_{16 hex aleatórios}.pdf` (o anterior é apagado); pasta continua com `.htaccess` | CV-01 a CV-06 no PC |
| SEG-11 | CORRIGIDO E VALIDADO (Lote B, 12/10/2026) | `schema.sql` só para banco vazio | Lote B na réplica |
| SEG-13 | CORRIGIDO, VALIDAÇÃO PENDENTE | entrar como jovem apaga a identidade de empresa da sessão, e vice-versa | unidade (réplica); IDENT-01 no PC |
| SEG-15 | CORRIGIDO, VALIDAÇÃO PENDENTE (tamanho) | telefone com no máximo 20 caracteres no servidor e no campo; a gravação antes do envio continua | — |
| SEG-20 | em parte | `schema.sql` sem dados desde o Lote B; histórico do Git: PENDÊNCIA EXTERNA (PI-06); `authors` do `composer.json`: decisão do responsável | — |
| SEG-21 | sem mudança (assistente) | os contadores novos de tentativas têm faxina automática (arquivos parados há mais de um dia) | unidade (réplica) |
| SEG-12, 14, 16, 17, 19, 22, 23, 25 a 30 | sem mudança | — | — |
| **SEG-31** (novo, Média) | CORRIGIDO, VALIDAÇÃO PENDENTE | erros do PHP podiam aparecer na página (o XAMPP vem com `display_errors` ligado), mostrando caminhos e SQL. `includes/env.php` desliga a exibição; `JC_EXIBIR_ERROS=1` no `.env` religa no desenvolvimento | réplica: respostas sem caminho nem SQL; PHP-01 no PC |
| **SEG-32** (novo, Baixa) | CORRIGIDO E VALIDADO | o site público não tinha cabeçalhos de segurança. Agora: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, CSP só com `frame-ancestors`, `base-uri` e `form-action`, `Permissions-Policy` | réplica 41/41; HDR-01 a HDR-05 e `verificar_protecao` no PC |
| **SEG-33** (novo, Baixa) | CORRIGIDO E VALIDADO | `session_start()` sem as opções endurecidas em `includes/auth.php`, `includes/header.php` e `logout.php` | `php -l` e regressão |
| **SEG-34** (novo, Média) | CORRIGIDO E VALIDADO (com `JC_AMBIENTE=producao`) | contas de demonstração (senha pública) entravam em qualquer ambiente e o seed podia ser carregado num banco real. Com `JC_AMBIENTE=producao` no `.env`, o login recusa `@demo.jovemconnect.example` e `banco-seed` se recusa | réplica 6/6 e código de saída 3; DEMO-01 a DEMO-04 no PC. Vale só se o `.env` do servidor real tiver `JC_AMBIENTE=producao` |
| **SEG-35** (novo, a definir) | PENDÊNCIA EXTERNA | há um repositório Git na **raiz do `htdocs`** e outras pastas no mesmo `htdocs`; o `.htaccess` do JovemConnect não as alcança. 29/10/2026: as pastas `cline_feats` e `usavel` eram testes e serão apagadas pelo responsável; a origem do `.git` da raiz é desconhecida. Com o Tailscale **Funnel**, tudo isso ficaria na internet ([APRESENTACAO.md](APRESENTACAO.md)) | `verificar_protecao.bat` (grupo `htdocs`) e `diagnostico_somente_leitura.bat` (seção G2) mostram o que está exposto |
| **SEG-36** (novo, Info) | CORRIGIDO E VALIDADO | a inicial do nome (1 caractere) saía sem escape em 6 telas (`Vagas/avaliar.php`, `Vagas/candidatos.php`, `Vagas/curriculoIA.php`, `empresa/perfil.php`, `jovem/perfil.php`, `dashboard/empresa.php`); não explorável com um caractere, corrigido por higiene com `htmlspecialchars` | `php -l`; regressão |

| **TER-01** (novo, conformidade) | ACEITO FORMALMENTE (temporário, até depois da apresentação; decisão de 29/10/2026) | o uso do Gemini não atende aos termos da API (público a partir de 14 anos; plano gratuito). Ver [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md#ter-01--termos-da-api-do-gemini-achado-de-29102026) | — |

**Apresentação (29/10/2026):** o acesso pelos computadores da equipe deve usar o Tailscale **Serve** (só os
aparelhos da sua conta) e não o **Funnel** (internet inteira: exporia o phpMyAdmin, o `.git` da raiz do
`htdocs` e as demais pastas). No estande, todos os acessos contam como o mesmo IP para o limite de tentativas
(`liberar_logins.bat` libera). Roteiro: [APRESENTACAO.md](APRESENTACAO.md).

Itens de propriedade intelectual (PI-01 a PI-17): ver o relatório da v4.60 e [CLASSIFICACAO.md](CLASSIFICACAO.md).

## Atenção imediata

<a id="seg-01"></a>
**SEG-01 — Tomada de conta pela recuperação de senha (gravidade crítica, verificado por teste).**
**Corrigido no Lote A (12/10/2026)** (`includes/recuperacao.php`); o texto a seguir descreve a falha original.
Uma pessoa que tenha uma conta qualquer consegue **trocar a senha de outra conta** sabendo apenas o e-mail
dela. A etapa 1 de `recuperar_senha.php` não apaga a marca `$_SESSION['recuperacao_codigo_ok']` deixada pela
etapa 2, e a etapa 3 só confere essa marca e a validade do código no banco — não o código. Sequência: validar o
próprio código → pedir código para o e-mail da vítima → enviar a nova senha. Na réplica, o teste DOC-001
(script da réplica, contas fictícias; ver [TESTES_RESULTADOS.md](TESTES_RESULTADOS.md)) entrou na conta da vítima com a senha escolhida pelo atacante
(redirecionamento para `../dashboard/jovem.php`). **Recomendação:** no início da etapa 1, apagar todo o estado
`recuperacao_*`; guardar junto da marca o e-mail e a tabela validados e conferi-los na etapa 3; invalidar o
código depois do uso; repetir o teste depois da correção. Até lá, considerar desativar o link "Esqueceu a
senha?" na apresentação pública.

## Mecanismos de proteção

| Mecanismo | Situação | Evidência | Observações |
|---|---|---|---|
| Senhas com `password_hash` / `password_verify` | Verificado por teste | cadastros, `*/processar_login.php`, `recuperar_senha.php`; S3 regressão | bcrypt padrão do PHP |
| Prepared statements em consultas com entrada do usuário | Confirmado no código | todos os módulos | nomes de tabela dinâmicos (`{$tabela}`) vêm só de valores fixos `jovens`/`empresas` |
| Escape de saída HTML (`htmlspecialchars`) | Confirmado no código | páginas | a exceção SEG-05 (`innerHTML` no IA Advisor) foi corrigida na v4.60 |
| Saída de IA via `textContent` | Verificado por teste | `includes/assistente.php`, `Vagas/avaliar.php`; assistente 34/34, S5.1 UI | HTML malicioso do Gemini simulado aparece como texto |
| Token CSRF por sessão (campo, cabeçalho ou JSON) | Verificado por teste | `includes/csrf.php`; S3 regressão | renovado no login desde a v4.60 (SEG-09) |
| Sessão endurecida (HttpOnly, SameSite=Lax, strict mode, Secure em HTTPS) | Confirmado no código | `includes/session.php` | desde a v4.60 todas as entradas usam `jc_session_start()` (SEG-33), que também envia os cabeçalhos de segurança (SEG-32) |
| Regeneração do ID de sessão após login e cadastro | Confirmado no código | `jc_session_regenerate()` | — |
| Expiração da sessão por inatividade | Ausente | sem controle no código | depende de `session.gc_maxlifetime` do php.ini |
| Controle de acesso por dono (vaga, candidatura, local, formação etc.) | Verificado por teste | `WHERE ... empresa_id = ?` / `jovem_id = ?`; S3 regressão, perfil 36/36 | — |
| Regra única de acesso da empresa ao candidato | Verificado por teste | `includes/candidato.php`; perfil 36/36 | desde a v4.60 as contas novas nascem com `perfil_publico = 0` (SEG-03) |
| Mensagem igual para "não existe" e "sem permissão" (404) | Verificado por teste | `jc_candidato_indisponivel`; perfil 36/36 | evita descobrir quais IDs existem |
| Parâmetros de retorno (`voltar`) só por lista fixa | Confirmado no código | Vagas/*, perfil | sem redirecionamento aberto |
| Upload: tamanho ≤ 5 MB, assinatura `%PDF`, nome aleatório desde a v4.60, entrega com `nosniff` e permissão | Verificado por teste | `uploads/curriculos.php`, `uploads/ver_curriculo.php`; S3 parte B | só magic bytes (SEG-26) |
| Bloqueio de acesso direto a `uploads/curriculos/` e `logs/` | Não foi possível confirmar | `.htaccess` nas duas pastas | só vale em Apache com `AllowOverride` (SEG-10); confira com `scripts\seguranca\verificar_protecao.bat` |
| Bloqueio de `.env`, `schema.sql`, `includes/` pela web | Implementado na v4.60; validação no Apache real pendente | `.htaccess` na raiz e em `docs/`, `database/`, `migrations/`, `includes/` | SEG-04 |
| Tratamento global de exceções sem vazar detalhes | Verificado por teste | `includes/config.php`; S3 ("220 respostas sem vazamento") | warnings dependem de `display_errors` do servidor [NC] |
| Logs sem dados pessoais/segredos; IP anonimizado | Verificado por teste | `includes/logger.php`; logs 22/22 | — |
| Limite de tentativas em login e códigos | Implementado na v4.60; validação HTTP pendente no PC | `includes/tentativas.php`; unidade 17/17 | SEG-02 |
| Limite de envio de e-mail/SMS | Implementado em parte na v4.60 (sem teto diário) | `verificacoes/*`, `recuperar_senha.php` | SEG-07 |
| Limite de uso do assistente e da análise contextual | Verificado por teste | `IA/assistente_ajax.php`, `IA/avaliar_ajax.php`; assistente 34/34, contexto 33/33 | contornável chamando o Flask direto da máquina (SEG-06) |
| Mascaramento de dados pessoais antes do Gemini | Verificado por teste | `jc_assist_mascarar`, `jc_match_payload_ia`; assistente 34/34 | — |
| Chave do Gemini nunca devolvida ao navegador | Verificado por teste | `/ping` só booleanos; teste IA-01 | chave vai no cabeçalho `x-goog-api-key` |
| Flask acessível só pela máquina local | Confirmado no código | `app.run(host="127.0.0.1")` | desde a v4.60, sem CORS e só com `Host` local; sem autenticação entre PHP e Flask (SEG-06) |
| Registro do aceite dos Termos (versão e data) | Verificado por teste | `includes/termos.php`; termos 38/38 | contas anteriores à migração ficam sem registro |
| Termos de Uso e Política de Privacidade | Parcialmente implementado | `includes/documentos.php` | textos marcados como **minuta**; revisão jurídica é pendência do projeto |
| Exclusão de conta com dados ligados | Verificado por teste | `*/excluir_conta.php` + `ON DELETE CASCADE` | sem pedir a senha (SEG-16) |
| Códigos guardados com hash | Ausente | colunas `codigo_*` em texto puro | SEG-08 |
| **Stage 7** — Central administrativa separada das contas do site | Verificado por teste | `admin/`, `includes/admin/*`; bloco A 51/51 | sem conta padrão nem cadastro público; administradores só pela linha de comando (`scripts/admin.php`) |
| **Stage 7** — Login administrativo com segundo fator (TOTP) e limite de tentativas | Verificado por teste | `admin/login.php`, `includes/admin/totp.php`, `includes/admin/limite.php`; bloco A | 5 falhas/e-mail ou 20/IP em 15 min; código não reaproveitável; tentativas guardadas só como hash |
| **Stage 7** — Sessão administrativa com expiração (15 min sem uso, 8 h no total), vínculo ao navegador e revogação | Verificado por teste | `includes/admin/sessao.php`; bloco A | cookie `JCADMIN` `HttpOnly`, `SameSite=Strict`, caminho `/admin/` |
| **Stage 7** — Autorização centralizada no servidor (14 permissões) + CSRF + `Origin` + reautenticação | Verificado por teste | `jc_admin_exigir`, `jc_admin_exigir_reauth`; blocos A–C | negações e envios recusados ficam no histórico |
| **Stage 7** — Cabeçalhos de segurança na Central (CSP com *nonce*, `X-Frame-Options: DENY`, `no-store`) | Verificado por teste | `jc_admin_cabecalhos`; bloco A, interface | desde a v4.60 o site público também envia cabeçalhos básicos (SEG-32) |
| **Stage 7** — Validação estrutural e moderação de conteúdo em todos os formulários | Verificado por teste | `includes/validacao.php`, `includes/moderacao.php`; bloco D 74/74 | código executável é recusado na entrada; o resto é sinalizado para revisão humana; o escape na saída continua |
| **Stage 7** — Suspensão de contas e moderação de vagas sem apagar dados | Verificado por teste | `includes/conta_status.php`, `includes/admin/servicos.php`; blocos B e C | sessões da conta suspensa encerradas; conteúdo oculto; tudo reversível |
| **Stage 7** — Trilha de auditoria encadeada por hash, com espelho no log e conferência | Verificado por teste | `includes/admin/auditoria.php`; bloco E 46/46 | detecta alteração e remoção (inclusive no fim); a ação é desfeita se o registro falhar |
| **Stage 7** — IA de moderação só consultiva, com dados mascarados e resposta validada | Verificado por teste | `admin/ia_moderacao.php`, rota Flask `/moderacao`; bloco D | nunca altera vaga, conta ou fila |

## Riscos registrados

Gravidade: **Crítica** (exploração simples com dano grave) · **Alta** · **Média** · **Baixa** · **Info**.

| ID | Gravidade | Risco | Impacto | Evidência | Classificação | Recomendação |
|---|---|---|---|---|---|---|
| SEG-01 | **Crítica** | Tomada de conta pela recuperação de senha (ver acima) | Qualquer conta cujo e-mail seja conhecido | `recuperar_senha.php` etapas 1 (sem `unset` da marca), 3 (só marca + expiração; UPDATE por `recuperacao_email_raw`) | Verificado por teste | Ver acima |
| SEG-02 | Alta | Sem limite de tentativas no login e nos códigos de 6 dígitos (a Central administrativa da Stage 7 tem o seu próprio limite; o login de jovens e empresas continua sem) | Força bruta de senha; 10⁶ códigos em 15 min são automatizáveis | `*/processar_login.php`, `recuperar_senha.php` etapa 2, `verificar_email.php`, `verificar_telefone.php` | Confirmado no código | Contador por conta/sessão/IP, invalidar código após N erros, atraso progressivo |
| SEG-03 | Alta (privacidade) | Perfil do jovem nasce público (`perfil_publico DEFAULT 1`, o cadastro não pergunta); qualquer empresa logada pode abrir perfil e currículo trocando `?id=`; o painel da empresa sugere até 12 jovens da área sem candidatura | Exposição de dados pessoais por enumeração; possível desacordo com o consentimento da Política de Privacidade (LGPD) | `schema.sql` (`jovens.perfil_publico`), `jovem/cadastro.php`, `includes/candidato.php`, `uploads/ver_curriculo.php`, `dashboard/empresa.php` | Confirmado no código | Opt-in no cadastro (decisão de produto), registro de visualizações, limite de acesso |
| SEG-04 | Média | `.env`, `schema.sql`, `composer.json`, `includes/` podem ser servidos pelo Apache (não há `.htaccess` na raiz) | Credenciais de banco/SMTP/Twilio/Gemini expostas, se o servidor não negar *dotfiles* | `find -name .htaccess` → só `logs/` e `uploads/curriculos/` | Não foi possível confirmar | Testar `GET /JovemConnect/.env` no XAMPP; negar `.env`, `*.sql`, `composer.*`, `includes/` ou tirar o `.env` da pasta pública |
| SEG-05 | Média | IA Advisor monta `mensagem`/`sugestoes` com `innerHTML` sem escape | XSS no próprio perfil (texto do Gemini influenciável pelo resumo do jovem) | `jovem/perfil.php` (handler de `#btnAnalisarPerfil`, ~l. 1218–1245) | Confirmado no código | Montar com `textContent`/`createElement` |
| SEG-06 | Média | Flask sem autenticação e com CORS para `localhost`/`127.0.0.1` | Qualquer processo/página local chama `/assistente` e `/analise_contextual` sem os limites do PHP, consumindo a cota da chave | `IA/recomendar.py` (CORS, rotas) | Confirmado no código (impacto [I]) | Segredo compartilhado PHP↔Flask; remover CORS (o PHP usa cURL) |
| SEG-07 | Média | Envio ilimitado de e-mail/SMS (reenvio, GET automático, troca de telefone) | Custo de SMS, spam a terceiros, bloqueio da conta SMTP | `verificar_email.php` (`gerarEEnviarCodigoEmail`), `verificar_telefone.php`, `recuperar_senha.php` | Confirmado no código | Intervalo mínimo e teto diário por conta |
| SEG-08 | Média | Códigos de verificação/recuperação em texto puro no banco | Quem lê o banco (backup, dump) usa um código válido | colunas `codigo_verificacao`, `codigo_telefone`, `codigo_recuperacao` | Confirmado no código | Guardar hash do código e comparar com `hash_equals` |
| SEG-09 | Média | Trocar a senha não derruba sessões abertas; token CSRF não é renovado no login | Sessão roubada continua válida depois da troca | `recuperar_senha.php` etapa 3, `includes/csrf.php` | Confirmado no código | Carimbo de versão da senha conferido a cada requisição; renovar token no login |
| SEG-10 | Média | Proteção de currículos e logs depende de `.htaccess` | Em nginx, `php -S` ou `AllowOverride None`, PDFs com nome previsível (`curriculo_{id}.pdf`) e logs ficam públicos | `uploads/curriculos/.htaccess`, `logs/.htaccess` | Não foi possível confirmar | Guardar fora da pasta pública ou nome aleatório; documentar a exigência (feito em [INSTALACAO_E_EXECUCAO.md](INSTALACAO_E_EXECUCAO.md)) |
| SEG-11 | Média (dados) | Importar o `schema.sql` num banco já existente apaga 6 tabelas filhas e depois falha (tabelas sem `DROP` mantêm chaves estrangeiras) | Perda de habilidades, formações, experiências, requisitos, benefícios e avaliações | Teste na réplica: 4 `DROP` recusados por FK (`mensagens_chat_ibfk_1`, `fk_cand_vaga`, `fk_cand_jovem`, `fk_local_empresa`) | Verificado por teste (réplica MySQL compatível; no phpMyAdmin a execução para no 1º erro [I]). **Corrigido no Lote B (12/10/2026)**: o `schema.sql` não tem mais `DROP`, `DELETE` nem `INSERT` e tem uma trava "só banco vazio": em qualquer banco com tabelas, para no início e nenhum comando de estrutura roda, mesmo em ferramentas que continuam após erros (testado na réplica: 0 tabelas criadas ou alteradas, 0 registros alterados) | Instalação nova só em banco vazio; atualização só pelas migrações ([MIGRACOES.md](MIGRACOES.md)). **Não** ampliar o bloco `DROP` nem desligar `FOREIGN_KEY_CHECKS` (aumentaria a perda por engano); nunca usar uma cópia antiga do `schema.sql` |
| SEG-12 | Baixa | Remoção de vaga é física, com cascata; mensagem de sucesso mesmo quando nada foi removido | Jovens perdem o histórico de candidatura sem aviso | `Vagas/minhas.php` | Confirmado no código | Status "encerrada" em vez de DELETE |
| SEG-13 | Baixa | Sessão pode ter `jovem_id` e `empresa_id` ao mesmo tempo | Estado inconsistente (exige possuir as duas contas) | `*/processar_login.php` não limpam a outra chave | Confirmado no código | Limpar a sessão antes de gravar o novo login |
| SEG-14 | Baixa | Logout por GET sem CSRF | Outro site pode deslogar o usuário | `logout.php` | Confirmado no código | POST + `csrf_require()` |
| SEG-15 | Baixa | `verificar_telefone.php` grava o telefone digitado antes do envio, sem normalizar nem limitar tamanho | Telefone sobrescrito; texto longo pode gerar 500 em modo estrito [I] | `verificar_telefone.php` l. 42 | Confirmado no código | Gravar o número normalizado só após envio bem-sucedido |
| SEG-16 | Baixa | Exclusão de conta sem pedir a senha | Quem tiver uma sessão aberta apaga a conta | `*/excluir_conta.php` | Confirmado no código | Pedir a senha atual |
| SEG-17 | Baixa | "Perfil verificado" é marcado pela própria empresa | Hoje sem efeito visível; vira selo falsificável se for exibido | `empresa/perfil.php` | Confirmado no código | Retirar do formulário da empresa |
| SEG-18 | Baixa | `site_oficial`/`linkedin` gravados sem validar o esquema no perfil | Se um dia virarem `href`, aceitam `javascript:` [I] | `empresa/perfil.php` | Confirmado no código | Validar `https?://` — **Stage 7: corrigido** — o perfil da empresa valida o esquema (`http`/`https`) ao alterar site e LinkedIn; valores antigos não são reescritos |
| SEG-19 | Baixa | `Vagas/criar.php` sem lista branca/tamanho no servidor para contrato, área, salário, carga horária | Valores fora das opções; texto longo vira erro | `Vagas/criar.php` | Confirmado no código | Validar contra as listas e os tamanhos das colunas — **Stage 7: em parte** — salário e carga horária agora têm tamanho e validação; contrato e área continuam sem lista branca no servidor |
| SEG-20 | Baixa (privacidade) | Dados de exemplo do `schema.sql` com aparência de dados pessoais reais; nome e e-mail do autor em `composer.json` | Exposição se o repositório for público | `schema.sql` (INSERT em `jovens`), `composer.json` (`authors`) | Confirmado no código (valores não reproduzidos aqui). **No `schema.sql`, corrigido no Lote B (12/10/2026)**: o arquivo não traz mais nenhuma linha de dados | Continua: no histórico do Git e em bancos criados com o arquivo antigo (decisão do proprietário); `authors` do `composer.json` (decisão do proprietário). Demonstração só com `database/seed_demo.sql` |
| SEG-21 | Baixa | Limite por IP do assistente usa arquivos temporários sem limpeza | Acúmulo de `jc_assist_*.json`; atrás de proxy todos dividem o mesmo IP [I] | `IA/assistente_ajax.php` | Confirmado no código | Limpeza periódica |
| SEG-22 | Baixa | Recuperação procura primeiro em `jovens`: empresa com o mesmo e-mail de um jovem não consegue recuperar | Conta de empresa sem recuperação | `recuperar_senha.php` etapa 1 | Confirmado no código | Perguntar o tipo de conta |
| SEG-23 | Baixa | Falha de envio do e-mail na recuperação é ignorada e não vai para os logs do sistema | Usuário espera um e-mail que não chega; diagnóstico só no `error_log` do PHP | `recuperar_senha.php` (retorno de `enviarCodigoVerificacao` descartado) | Confirmado no código | Registrar `jc_log` do resultado |
| SEG-24 | Baixa | `Vagas/curriculoIA.php` grava `resumo_profissional` sem limite de tamanho (página não linkada) | Texto grande no banco e no Flask | `Vagas/curriculoIA.php` | Confirmado no código | Mesmo limite do perfil, ou remover a página — **Stage 7: corrigido** — o resumo editado passa pela mesma validação do perfil (até 2000 caracteres) |
| SEG-25 | Info | `FLASK_DEBUG=1` ligaria o depurador do Werkzeug | Execução de código por quem acessar a porta | `IA/recomendar.py` (última linha) | Confirmado no código | Manter desligado (padrão) |
| SEG-26 | Info | Validação do PDF só pelos 4 primeiros bytes | Arquivos poliglotas aceitos; mitigado por `application/pdf` + `nosniff` | `uploads/curriculos.php` | Confirmado no código | Aceitável; opcionalmente `finfo` |
| SEG-27 | Info | Google Fonts carregado em todas as páginas (duas vezes nas páginas com `header.php`) | IP do visitante enviado ao Google (declarado na Política de Privacidade) | `style.css` (`@import`), `includes/header.php` | Confirmado no código | Hospedar a fonte localmente |
| SEG-28 | Baixa | (Stage 7) Segredo do autenticador (TOTP) dos administradores guardado em texto (base32) em `administradores.mfa_segredo` | Quem lê o banco (dump, backup) consegue gerar códigos — ainda precisaria da senha | `scripts/admin.php`, `includes/admin/totp.php` | Confirmado no código | Proteger o acesso ao banco e aos backups; evolução: cifrar com chave do `.env` |
| SEG-29 | Info | (Stage 7) A trilha de auditoria **detecta** adulterações, não as impede: quem tiver escrita no banco **e** nos logs pode forjar os dois | Histórico administrativo alterado sem rastro | bloco E (adulterações detectadas na réplica) | Verificado por teste | Usuário do banco do site com só `SELECT, INSERT` em `admin_auditoria`; cópia externa periódica do `seguranca.log` |
| SEG-30 | Info | (Stage 7) Sem HTTPS, o cookie da Central não recebe `Secure` | Sessão administrativa interceptável numa rede não confiável | `jc_admin_https()` | Confirmado no código | Publicar com HTTPS; restringir `/admin/` por IP no Apache |

## Dados pessoais tratados

| Dado | Onde fica | Quem vê | Vai para terceiros? |
|---|---|---|---|
| Nome, e-mail, CPF, data de nascimento, telefone, CEP, cidade/UF do jovem | `jovens` | o próprio jovem; empresa vê nome, cidade/UF, idade e, com candidatura, e-mail | não; CEP é consultado no ViaCEP **pelo navegador** durante o cadastro |
| Formação, habilidades, experiências, resumo | `jovens`, `formacao_jovem`, `habilidades_jovem`, `experiencias_jovem` | jovem; empresa com acesso | parte vai ao Gemini **sem identificação** na análise contextual (cidade, UF, área, formação, curso, habilidades, experiências) |
| Currículo PDF | `uploads/curriculos/curriculo_{id}_{aleatório}.pdf` (antes da v4.60: `curriculo_{id}.pdf`) | jovem; empresa com acesso | não |
| Dados da empresa (CNPJ, endereço, contato) | `empresas`, `empresa_locais` | a própria empresa; jovens veem nome e local da vaga | CNPJ consultado na BrasilAPI pelo navegador |
| Mensagens ao assistente | não são gravadas | — | vão ao Gemini após mascarar e-mail, CPF, CNPJ, telefone, CEP e números longos |
| IP | `seguranca.log` (anonimizado); arquivo temporário do limite do assistente (nome = SHA-256 do IP, sem o IP em claro [C]); contadores do limite de tentativas (v4.60: nome = hash da chave, conteúdo só com horários) | quem administra o servidor | não |
| (Stage 7) E-mail e nome dos administradores; hash da senha; segredo TOTP | `administradores` | só o próprio administrador vê os seus dados na Central; segredo e hash nunca aparecem em tela ou log | não |
| (Stage 7) Tentativas de login na Central | `admin_tentativas` (SHA-256 do e-mail digitado e do IP) | ninguém (só contagens) | não |
| (Stage 7) Histórico administrativo | `admin_auditoria` (justificativa com e-mails e números mascarados, nomes de campos, IP anonimizado) | administradores | não |
| (Stage 7) Denúncias | `denuncias` (motivo e detalhe opcional escrito pelo jovem) | administradores; a empresa **não** sabe quem denunciou | não |
| (Stage 7) Fila de moderação | `moderacao_itens` (códigos de regra e hash do texto — sem cópia do conteúdo) | administradores | não |
| (Stage 7) Textos públicos de vagas e perfis na análise por IA | não são gravados | administradores | vão ao Gemini **só quando um administrador pede**, com e-mails, números longos e endereços de links mascarados |
| (Stage 7) Conteúdo do perfil na Central | lido do registro original | administradores (sem CPF, nascimento, telefone, CEP/endereço completo, senha ou currículo) | não |

## Itens que dependem do servidor real (não confirmados)

1. Se o Apache entrega `/.env` e `/schema.sql` (SEG-04): abrir `http://localhost/JovemConnect/.env` no XAMPP — o
   esperado é **403/404**.
   Desde a v4.60: `scripts\seguranca\verificar_protecao.bat` faz esse teste (e dezenas de outros) e mostra
   APROVADO/REPROVADO; o `iniciar_jovemconnect.bat` faz uma versão curta a cada início.
2. Se `AllowOverride` permite os `.htaccess` de `logs/` e `uploads/curriculos/` (SEG-10): abrir
   `http://localhost/JovemConnect/logs/app.log` — o esperado é **403**.
3. `display_errors` desligado em produção (para que warnings não apareçam na página). Desde a v4.60 o próprio
   projeto desliga a exibição (SEG-31), salvo com `JC_EXIBIR_ERROS=1` no `.env`.
4. `session.gc_maxlifetime` (tempo real de sessão ociosa).
5. Se `GEMINI_API_KEY` está definida como variável de ambiente do Windows (ativaria a integração antiga
   `google-generativeai`): conferir `"gemini": true` em `http://127.0.0.1:5000/ping` (ou na porta de `logs/ia_porta.json`).
