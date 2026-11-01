# Relatório de correção de segurança e propriedade intelectual — JovemConnect v4.60

> **Classificação: CONFIDENCIAL (uso interno da equipe).** Descreve riscos, inclusive os ainda abertos.
> **Data:** 29/10/2026 · **Base:** v4.59 (commit `acb1c53`), conferida arquivo a arquivo com a pasta do PC
> (`C:\xampp\htdocs\JovemConnect`); nenhum arquivo de código mudou no PC entre a cópia (28/10) e a entrega.
> **Referência:** `RELATORIO_AUDITORIA_PI.md` (PI-01 a PI-17) e `docs/SEGURANCA_E_PRIVACIDADE.md` (SEG-xx).
> **Nome do arquivo:** a missão pedia `RELATORIO_CORRECO_SEGURANCA.md`; usei `RELATORIO_CORRECAO_SEGURANCA.md`
> (grafia corrigida).

**Estados usados (só estes):** **CORRIGIDO E VALIDADO** (correção feita e teste relevante passou) ·
**CORRIGIDO, VALIDAÇÃO PENDENTE** (falta o teste no ambiente adequado: Apache, MariaDB, Windows ou o Git do PC) ·
**NÃO REPRODUZIDO** · **INCONCLUSIVO** (evidência insuficiente) · **PENDÊNCIA EXTERNA** (depende de
configuração, acesso, decisão ou ação fora do código) · **ACEITO FORMALMENTE** (só com decisão registrada do
responsável — itens nesse estado a partir das respostas de 29/10/2026, seção 9).

**O que não foi feito:** nenhum commit, tag, push, criação ou exclusão de branch, reescrita de histórico,
`reset`, `clean` ou `gc`; nenhum acesso ao banco do PC, ao `.env`, aos logs reais, ao GitHub ou ao Gemini;
nenhuma chave gerada; nenhuma imagem trocada; nenhum dado apagado. A licença ("todos os direitos reservados")
foi aplicada por decisão do responsável de 29/10/2026. Os pesos, critérios
e o algoritmo do Match, os prompts e o modelo da IA, o `mysqli`, os contratos dos endpoints e a compatibilidade
com PHP 7.4 foram preservados. Este relatório não declara o projeto "seguro" nem "juridicamente protegido":
registra o que foi corrigido, como foi testado e o que ainda depende de você.

---

## 1. Resumo executivo

1. **Exposição pelo Apache (PI-01, o achado crítico):** `.htaccess` novo na raiz e em `docs/`, `database/`,
   `migrations/` e `includes/`, sem listagem de pastas. Bloqueia `.env`, `.git`, documentação, `.sql`, `.py`,
   scripts, logs, backups e também os "apelidos" que o Windows aceita para o mesmo arquivo (`.git.`, `GIT~1`,
   `ENV~1`, `::$DATA`) — esses apelidos foram encontrados na **segunda passagem** de auditoria e corrigidos.
   Validado por simulação das regras (0 falhas em 197 caminhos, 42 variantes e 14 páginas). **Falta o teste no seu
   Apache** (`verificar_protecao.bat`), que agora também roda, em versão curta, a cada `iniciar_jovemconnect.bat`.
2. **Git (PI-08, PI-09, PI-12, PI-17):** o roteiro `scripts\seguranca\git_correcao_seguranca.bat` tira
   `geminiAPI.env` e `repomix-output.xml` do índice (os arquivos ficam na pasta), corrige a grafia
   `Candidaturas/` → `candidaturas/`, prepara a lista fixa de arquivos e oferece o gancho `pre-commit`, o commit, a
   tag e o envio, **sempre com confirmação e sem `--force`**. Testado num repositório sintético com o índice do PC e
   num clone Linux: **23/23**. Nada foi executado no seu Git.
3. **Aplicação:** limite de tentativas no login e nos códigos de 6 dígitos (SEG-02), intervalo e teto de envios
   (SEG-07), troca de senha encerra as outras sessões e token CSRF novo no login (SEG-09), uma identidade por sessão
   (SEG-13), **perfil do jovem privado por padrão** nas contas novas (SEG-03), currículo com nome aleatório
   (SEG-10), cabeçalhos de segurança (SEG-32), erros do PHP fora da tela (SEG-31), Flask sem CORS e só com `Host`
   local (SEG-06), IA Advisor sem `innerHTML` (SEG-05) e contas de demonstração bloqueadas com
   `JC_AMBIENTE=producao` (SEG-34). Nenhuma migração de banco.
4. **Propriedade intelectual e documentação:** classificação da informação e versões resumidas para o TCC
   (`CLASSIFICACAO.md`), avisos "CONFIDENCIAL" em 6 documentos, registro factual de autoria e uso de IA
   (`AUTORIA_E_ORIGEM.md`), origem e licenças dos ativos (`ORIGEM_DOS_ATIVOS.md`, `THIRD_PARTY_NOTICES.md`),
   perguntas de titularidade (`TITULARIDADE_E_LICENCA.md`) e roteiro de assinatura, hashes e backups
   (`PROCEDIMENTO_EVIDENCIAS.md` + `gerar_evidencias.bat`).
5. **Testes:** 17 verificações na réplica Linux, todas aprovadas (seção 5). **Ficam para o seu PC** o Apache real,
   o MySQL real (`regressao_isolada.bat`, cerca de 70 verificações num banco de teste novo), o diagnóstico de rede/firewall
   e o Git (seção 6).
6. **Dependem de você:** rodar os três roteiros e o do Git; decidir sobre a rede da escola (`Listen`), o
   repositório Git e as pastas extras na raiz do `htdocs` (SEG-35), o histórico com dados de aparência pessoal
   (PI-06), as contas antigas com perfil público, a visibilidade dos pesos do Match (PI-07) e as questões de
   titularidade e licença (seções 9 e 13).
7. **Respostas do responsável (29/10/2026, tarde):** registradas em `AUTORIA_E_ORIGEM.md`,
   `ORIGEM_DOS_ATIVOS.md`, `TITULARIDADE_E_LICENCA.md`, `CLASSIFICACAO.md` e `PROCEDIMENTO_EVIDENCIAS.md`;
   novos `LICENSE` (todos os direitos reservados), `docs/APRESENTACAO.md`, `liberar_logins.bat`,
   `configurar_assinatura.bat` e `inventariar_versoes_antigas.bat`. Achado novo **TER-01**: o uso do Gemini
   não atende aos termos da API (idade mínima de 18 anos; plano gratuito). Na interface, só entraram o aviso
   "© 2026 JovemConnect · Aestus. Todos os direitos reservados." (página inicial, documentos, início do cadastro,
   logins e Central) e o logotipo da Aestus com "Criado por" no rodapé da página inicial.

## 2. Estado inicial e estado final

**Linha de base (Fase 0, v4.59):** `php -l` 91/91; compatibilidade 7.4 sem ocorrências; Python compila;
varredura sem credenciais reais nos arquivos atuais (as mesmas ocorrências esperadas da seção 5); só 3 pastas
com `.htaccess` (`logs/`, `uploads/curriculos/`, `scripts/`) e nenhum na raiz; `geminiAPI.env` e
`repomix-output.xml` rastreados; índice com `Candidaturas/minhas.php`; nenhum commit ou tag assinado.

| Área | Antes (v4.59) | Depois (v4.60) |
|---|---|---|
| Apache | sem `.htaccess` na raiz: `.env`, `.git`, `docs/`, `.sql`, `.py`, repomix e `venv` servidos como arquivo | `.htaccess` na raiz + 4 pastas novas protegidas; apelidos do Windows barrados; verificador completo e verificação curta no início |
| Rede | verificada em 01/11/2026: Apache e MariaDB abertos à rede, com o firewall liberando em redes Públicas (seção 9, item 10) | `diagnostico_somente_leitura.bat` mostra `Listen`, `AllowOverride`, MariaDB, portas, firewall e pastas vizinhas; procedimento para `Listen 127.0.0.1` |
| Git | `geminiAPI.env` e repomix rastreados; `Candidaturas/` no índice; sem gancho | roteiro do Git pronto (não executado); `.gitignore` atualizado; gancho `pre-commit` opcional |
| Autenticação | sem limite de tentativas; sessões sobreviviam à troca de senha; token CSRF fixo; duas identidades possíveis | limite por conta e IP; sessões encerradas; token renovado; identidade única |
| Privacidade | perfil do jovem nascia público; currículo com nome previsível | perfil privado nas contas novas; nome aleatório |
| Saída e cabeçalhos | erros do PHP podiam aparecer; sem cabeçalhos no site público; `innerHTML` no IA Advisor | erros fora da tela; cabeçalhos básicos; `textContent` |
| Flask | CORS para `localhost`; qualquer `Host` | sem CORS; só `Host` local; corpo até 512 KB |
| Demonstração | contas de senha pública aceitas em qualquer ambiente | recusadas com `JC_AMBIENTE=producao`; `banco-seed` recusa |
| Documentação | sem classificação; algoritmo e mapa do código sem aviso; sem registro de autoria, origem de ativos e licenças | classificação, avisos, registros e procedimentos (seção 3) |
| Evidências | nenhum manifesto, *bundle* ou assinatura | `gerar_evidencias.bat` e roteiro de assinatura (execução pendente) |

## 3. Arquivos alterados, criados e retirados do índice

**Criados (30, dos quais 7 depois das respostas de 29/10/2026):**
- Proteção: `.htaccess` (raiz), `docs/.htaccess`, `database/.htaccess`, `migrations/.htaccess`, `includes/.htaccess`.
- Código: `includes/tentativas.php`.
- Scripts (`scripts/seguranca/`): `verificar_protecao.ps1`/`.bat`, `diagnostico_somente_leitura.ps1`/`.bat`,
  `regressao_isolada.php`/`.bat`, `gerar_evidencias.ps1`/`.bat`, `git_correcao_seguranca.bat`, `pre-commit`,
  `configurar_assinatura.ps1`/`.bat`, `inventariar_versoes_antigas.ps1`/`.bat`; na raiz, `liberar_logins.bat`.
- Licença: `LICENSE` (todos os direitos reservados).
- Documentos: `THIRD_PARTY_NOTICES.md` (raiz), `docs/CLASSIFICACAO.md`, `docs/AUTORIA_E_ORIGEM.md`,
  `docs/ORIGEM_DOS_ATIVOS.md`, `docs/TITULARIDADE_E_LICENCA.md`, `docs/PROCEDIMENTO_EVIDENCIAS.md`,
  `docs/RELATORIO_CORRECAO_SEGURANCA.md` (este), `docs/APRESENTACAO.md`.

**Alterados — código e configuração (31):** `.env.example`, `.gitattributes`, `.gitignore`, `requirements.txt`,
`schema.sql`, `IA/recomendar.py`, `includes/auth.php`, `includes/conta_status.php`, `includes/env.php`,
`includes/header.php`, `includes/session.php`, `jovem/cadastro.php`, `jovem/perfil.php`,
`jovem/processar_login.php`, `empresa/cadastro.php`, `empresa/perfil.php`, `empresa/processar_login.php`,
`logout.php`, `recuperar_senha.php`, `uploads/curriculos.php`, `verificacoes/verificar_email.php`,
`verificacoes/verificar_telefone.php`, `Vagas/avaliar.php`, `Vagas/candidatos.php`, `Vagas/curriculoIA.php`,
`dashboard/empresa.php`, `assets/css/admin.css` (só comentário de licença), `scripts/jovemconnect.php`,
`iniciar_jovemconnect.bat`, `iniciar_jovemconnect.sh`, `README.md`, `composer.json` (campo `license`),
`THIRD_PARTY_NOTICES.md` (texto das imagens e da licença), e o aviso de direitos autorais: `index.php`, `documentos.php`, `comecar.php`,
`jovem/login.php`, `empresa/login.php`, `includes/documentos.php` (`jc_copyright()`), `includes/admin/layout.php`,
`assets/css/style.css`, `assets/css/admin.css` e o novo `assets/img/marca/aestus-horizontal.png`.

**Alterados — documentação em `docs/` (22):** `README.md`, `ADMINISTRACAO_E_MODERACAO.md`, `ARQUITETURA.md`,
`ARQUIVOS_E_FUNCOES.md`, `BANCO_DE_DADOS.md`, `CONFIGURACAO.md`, `FLUXOS_E_FLUXOGRAMAS.md`, `GLOSSARIO.md`,
`IA_E_MATCH.md`, `INSTALACAO_E_EXECUCAO.md`, `LOGS.md`, `LOGS_E_DIAGNOSTICO.md`, `MANUTENCAO_E_EVOLUCAO.md`,
`MATRIZ_DE_DEPENDENCIAS.md`, `MIGRACOES.md`, `REQUISITOS.md`, `ROTAS_E_ENDPOINTS.md`, `SEED_DEMO.md`,
`SEGURANCA_E_PRIVACIDADE.md`, `TESTES.md`, `TESTES_RESULTADOS.md`, `VISAO_GERAL.md`.

**Retirados do índice do Git (pelo roteiro do Git, quando você o rodar; as cópias locais continuam):**
`geminiAPI.env`, `repomix-output.xml`. **Renomeado no índice:** `Candidaturas/minhas.php` →
`candidaturas/minhas.php` (o arquivo da pasta não muda).

**Regenerado só localmente (fora do Git):** `repomix-output.xml`, no mesmo formato de antes, com a v4.60 (sem
`.env`, logs, `venv`, currículos nem cópias do banco). **Não entregues:** logs, relatórios dos testes da réplica
e arquivos temporários.

## 4. Achados do relatório original e situação atual

### Propriedade intelectual (PI)

| ID | Gravidade original | Situação | O que foi feito | Evidência | Risco residual |
|---|---|---|---|---|---|
| PI-01 Apache serve arquivos internos | Crítica | **CORRIGIDO E VALIDADO** | `.htaccess` na raiz (pastas internas, ocultos, segredos, documentos, SQL, Python, scripts, backups, apelidos do Windows, `-Indexes`) e em 4 pastas; verificação no início; verificador completo; 01/11/2026, Apache real do PC: `verificar_protecao.bat` com 109 aprovados (internos, variantes do Windows, código-fonte, listagem, site e cabeçalhos); a única reprovação é o `.git` da raiz do `htdocs`, fora do projeto (SEG-35) | V460-005 (simulação, 0 falhas; as regras da 1ª passagem deixavam 9 variantes); V460-014 (a verificação acusa a exposição) | depende de `AllowOverride All`; o Apache continua acessível pela rede se escutar em todas as interfaces; pastas fora do projeto (SEG-35) |
| PI-02 Repositório público | Alta | **PENDÊNCIA EXTERNA** | não tratado como vulnerabilidade confirmada (o responsável informa que o repositório é privado desde a criação); visibilidade não alterada; 29/10: contas do desenvolvedor principal e do secundário, 2FA nas duas, nenhum outro colaborador (dois convites expiraram) [Declarado]; vira NÃO REPRODUZIDO se o endereço dos dois remotos der 404 numa janela anônima | GitHub não acessado | **dúvida registrada:** a auditoria de 12/10 anotou como "confirmadamente público" o remoto `pessoal` (outra conta), que pode ser um repositório diferente do `origin`; confirmar os dois |
| PI-03 Documentação revela a lógica | Alta | **CORRIGIDO, VALIDAÇÃO PENDENTE** | `CLASSIFICACAO.md` (6 categorias, documentos internos × confidenciais, versões resumidas para o TCC); avisos CONFIDENCIAL em 6 documentos; `docs/` bloqueada no Apache; repomix fora do Git; nada apagado nem movido | links 0 quebrados (V460-016); bloqueio de `docs/` na simulação | quem tem acesso ao repositório lê tudo; o texto público do TCC deve usar as versões resumidas; cópia antiga do código no Project |
| PI-04 Autoria e cronologia frágeis | Alta | **PENDÊNCIA EXTERNA** | `PROCEDIMENTO_EVIDENCIAS.md`; `gerar_evidencias.bat` (zip, manifesto SHA-256, *bundle* verificado, hashes, fora do `htdocs`); roteiro do Git assina commit e tag se a chave existir; histórico preservado; 29/10: assinatura aceita antes da v4.60 (`configurar_assinatura.bat`); versões anteriores ao Git em pastas locais, com inventário só de leitura (`inventariar_versoes_antigas.bat`); evidências guardadas só no PC, por decisão | roteiro conferido no repositório sintético (V460-013) | a chave e a execução são suas; o passado não pode ser "consertado" |
| PI-05 Contribuições e titularidade | Alta | **PENDÊNCIA EXTERNA** | `AUTORIA_E_ORIGEM.md` (linha do tempo, Claude e Cline, decisões do responsável, limitações) e `TITULARIDADE_E_LICENCA.md`; 29/10: papéis por função, ferramentas de IA (ChatGPT, Gemini/Cline, Claude) e regulamento registrados; nomes e titularidade dependem do contrato em preparação | — | equipe, regras da escola, Aestus, termos das ferramentas de IA |
| PI-06 Dados de aparência pessoal, contas e chave no histórico | Alta | **PENDÊNCIA EXTERNA** | histórico preservado (sem reescrita); o diagnóstico procura no histórico, com saída redigida, chaves, CPFs válidos e `.env` não vazios; contas de demonstração recusadas em produção (SEG-34); 29/10: decisão de manter o histórico; vira ACEITO FORMALMENTE quando o responsável confirmar que o registro é dele | varredura do histórico pendente no PC | decisão com orientação em LGPD; a revogação da chave antiga é informação do responsável |
| PI-07 Pesos do Match inferíveis pela interface | Média | **ACEITO FORMALMENTE** | análise do risco e 4 alternativas mínimas em `CLASSIFICACAO.md`; nada mudou na tela nem no cálculo; 29/10: opção D, temporária, até depois da apresentação | código: `IA/avaliar_ajax.php` (`analise` sem limite), `Vagas/avaliar.php` | pesos visíveis; LGPD, art. 20 (transparência × segredo) |
| PI-08 `geminiAPI.env` rastreado | Média | **CORRIGIDO E VALIDADO** | `.gitignore` + `git rm --cached` no roteiro (arquivo mantido na pasta); 01/11/2026: diagnóstico no PC — não rastreado e ignorado | V460-013 | — |
| PI-09 Repomix versionado e servido | Média | **CORRIGIDO E VALIDADO** | `.gitignore` (`repomix-output.*`) + `git rm --cached` no roteiro + bloqueio no Apache; continua sendo gerado localmente; 01/11/2026: não rastreado, ignorado e 403 no Apache real | V460-013, V460-005 | as versões antigas continuam no histórico |
| PI-10 Ativos sem origem; licenças | Média | **PENDÊNCIA EXTERNA** | `ORIGEM_DOS_ATIVOS.md` (metadados, hashes, licenças conferidas), `THIRD_PARTY_NOTICES.md`, aviso de licença completo em `admin.css`; correção: os 8 logotipos têm manifesto C2PA (o relatório PI dizia que só 2); 29/10: fotos copiadas do Google Imagens, sem licença, mantidas por enquanto; logotipos criados pela equipe (autor registrado) | metadados lidos dos arquivos | origem das 2 fotos e autoria dos logotipos |
| PI-11 Cópias fora do controle | Média | **PENDÊNCIA EXTERNA** | inventário ampliado: repositório Git na **raiz do `htdocs`** e pastas extras (`cline_feats`, `usavel`) detectados; verificador e diagnóstico passam a apontá-los; 29/10: `cline_feats` e `usavel` eram testes (o responsável apaga); `.git` da raiz do `htdocs` de origem desconhecida; cópia no Project e *zip* na conta pessoal mantidos como registro datado | listagem (só nomes) da raiz do `htdocs` | o conteúdo dessas pastas não foi lido; cópia antiga do código no Project |
| PI-12 `Candidaturas/` × `candidaturas/` | Alta (funcional) | **CORRIGIDO E VALIDADO** | renomeação só no índice, no roteiro do Git (o Git registra como renomeação R100); `TESTES_RESULTADOS.md` (V459) corrigido; 01/11/2026: o índice do PC tem só `candidaturas/minhas.php` (teste num clone Linux continua recomendado) | clone Linux: `/candidaturas/minhas.php` → 302, `/Candidaturas/` → 404 (V460-013) | o roteiro confere o índice ao final; repetir num clone real (seção 11) |
| PI-13 Listagem de pastas | Baixa | **CORRIGIDO E VALIDADO** | `Options -Indexes`; 01/11/2026: grupo "listagem" aprovado no Apache real | verificador (grupo "listagem") | — |
| PI-14 Extração das instruções da IA | Baixa | **ACEITO FORMALMENTE** | não testável sem o Gemini real (e sem requisições a terceiros); os controles do servidor não dependem do segredo do prompt; Flask sem CORS e só local; 29/10: temporário, até depois da apresentação | — | um usuário pode tentar extrair as instruções conversando |
| PI-15 Sem controles de confidencialidade | Baixa | **PENDÊNCIA EXTERNA** | classificação criada (`CLASSIFICACAO.md`); 29/10: contrato particular entre os integrantes em preparação; `AUTORIA_E_ORIGEM.md` passou a Confidencial | — | acordos com colaboradores e política de acesso |
| PI-16 Respostas prontas no navegador | Baixa | **ACEITO FORMALMENTE** | classificadas como "público por necessidade" (`CLASSIFICACAO.md`); aceitar formalmente é decisão sua; 29/10: temporário, até depois da apresentação | — | — |
| PI-17 Sem verificação de segredo no commit | Baixa | **CORRIGIDO, VALIDAÇÃO PENDENTE** | `scripts/seguranca/pre-commit` (só nomes na saída) e instalação opcional no roteiro do Git; `.gitattributes` mantém LF | V460-013: recusa `.env`, `*.env` e formato de chave; aceita `.env.example` e arquivo comum | é local e pode ser pulado com `--no-verify` |
| MA-01 a MA-05 Marcas e identidade visual | — | **PENDÊNCIA EXTERNA** | separadas em `TITULARIDADE_E_LICENCA.md`; 29/10: busca no INPI sem marcas iguais; intenção de registrar marcas e programa [Declarado] | — | busca e registro no INPI |

### Segurança da aplicação (Fase 4 e segunda passagem)

| ID | Situação | Resumo |
|---|---|---|
| SEG-01 recuperação de senha | **CORRIGIDO E VALIDADO** (Lote A, 12/10) | v4.60 acrescentou o limite de erros do código |
| SEG-02 limite de tentativas | **CORRIGIDO E VALIDADO** | 5 falhas por conta e 30 por IP em 15 min; 5 erros cancelam o código; unidade 17/17; regressão no PC (MariaDB 10.4 e PHP 8.2 reais), 01/11/2026: LIM-01 a LIM-04 e COD-01 |
| SEG-03 perfil público por padrão | **CORRIGIDO E VALIDADO** | SQL opcional em `BANCO_DE_DADOS.md`; 29/10: a demonstração usa banco novo (`schema.sql` + seed), sem contas antigas; regressão no PC (MariaDB 10.4 e PHP 8.2 reais), 01/11/2026: CAD-01 a CAD-03 |
| SEG-04 arquivos servidos | **CORRIGIDO E VALIDADO** | ver PI-01; 01/11/2026: verificador no Apache real (ver PI-01) |
| SEG-05 IA Advisor `innerHTML` | **CORRIGIDO E VALIDADO** | Chromium 7/7 (antes × depois) |
| SEG-06 Flask sem autenticação, com CORS | **CORRIGIDO E VALIDADO** (vetores do navegador e da rede) | Flask 12/12; risco residual: processo local |
| SEG-07 envio ilimitado | **CORRIGIDO, VALIDAÇÃO PENDENTE** | 30 s, 5/h por conta, 20/h por IP; sem teto diário |
| SEG-08 códigos em texto puro | **ACEITO FORMALMENTE** | exige migração do banco; mitigado pelo limite de erros; 29/10: temporário, até depois da apresentação |
| SEG-09 sessões após troca de senha; token no login | **CORRIGIDO E VALIDADO** | unidade 7/7; regressão no PC (MariaDB 10.4 e PHP 8.2 reais), 01/11/2026: LOGIN-02, LOGIN-03, SESS-01 e SESS-02 |
| SEG-10 currículo previsível | **CORRIGIDO E VALIDADO** | nome aleatório; arquivo antigo apagado; regressão no PC (MariaDB 10.4 e PHP 8.2 reais), 01/11/2026: CV-01 a CV-06 |
| SEG-13 duas identidades | **CORRIGIDO E VALIDADO** | —; regressão no PC (MariaDB 10.4 e PHP 8.2 reais), 01/11/2026: IDENT-01 |
| SEG-15 telefone sem limite | **CORRIGIDO, VALIDAÇÃO PENDENTE** (tamanho) | — |
| SEG-31 erros do PHP na tela (novo) | **CORRIGIDO E VALIDADO** | HTTP 41/41 na réplica; PHP-01 no PC; regressão no PC (MariaDB 10.4 e PHP 8.2 reais), 01/11/2026: PHP-01 (nenhuma página mostrou aviso ou erro do PHP) |
| SEG-32 cabeçalhos de segurança (novo) | **CORRIGIDO E VALIDADO** | HTTP 41/41 |
| SEG-33 `session_start()` cru (novo) | **CORRIGIDO E VALIDADO** | `php -l` e regressão |
| SEG-34 contas de demonstração em produção (novo) | **CORRIGIDO E VALIDADO** (com `JC_AMBIENTE=producao`) | 6/6 e código 3 |
| SEG-35 Git e pastas extras na raiz do `htdocs` (novo) | **PENDÊNCIA EXTERNA** | fora do alcance do `.htaccess` do projeto; 29/10: pastas de teste serão apagadas pelo responsável; `.git` da raiz de origem desconhecida; 01/11/2026: o verificador confirmou `../.git/HEAD` acessível (HTTP 200) pela raiz do `htdocs`; 01/11/2026: o `.git` da raiz do `htdocs` é um repositório **vazio** (0 arquivos, nenhum commit) — não expõe código; basta apagá-lo ou movê-lo |
| SEG-36 inicial do nome sem escape (novo, 2ª passagem) | **CORRIGIDO E VALIDADO** | não explorável (1 caractere); `htmlspecialchars` em 6 telas |
| TER-01 uso do Gemini fora dos termos da API (novo, 29/10) | **ACEITO FORMALMENTE** (temporário, até depois da apresentação) | público a partir de 14 anos e plano gratuito; os termos exigem 18 anos e proíbem público menor de idade; mitigação no estande em `APRESENTACAO.md`; caminhos em `ORIGEM_DOS_ATIVOS.md` |

Os demais itens de `SEGURANCA_E_PRIVACIDADE.md` (SEG-12, 14, 16, 17, 19, 21 a 30) não mudaram nesta correção.
Os Lotes C, D e E da remediação de outubro (preparados numa réplica, nunca gravados) **não estavam aplicados** no
PC; as correções acima foram feitas de forma independente e menor (sem migração).

## 5. Evidências dos testes realizados

Réplica Linux, 29/10/2026 (PHP 8.3.6, Python 3.13, Chromium via Playwright), sem banco, sem Apache e sem Windows.
Detalhes em `docs/TESTES_RESULTADOS.md`, seção V460.

| ID | Verificação | Resultado |
|---|---|---|
| V460-001 | `php -l` | 93/93 |
| V460-002 | compatibilidade PHP 7.4 | 0 ocorrências |
| V460-003 | Python compila | 2/2 |
| V460-004 | sintaxe `sh` | 5/5 |
| V460-005 | regras do `.htaccess` simuladas (197 caminhos, 42 variantes, 14 páginas, 2 modelos) | 0 falhas (regras da 1ª passagem: 9 variantes passavam) |
| V460-006 | limite de tentativas (unidade) | 17/17 |
| V460-007 | HTTP: cabeçalhos, erros, logout, cookie | 41/41 |
| V460-008 | `jc_sessao_entrar()` | 7/7 |
| V460-009 | Flask: `Host`, CORS, 413 | 12/12 |
| V460-010 | IA Advisor no Chromium (antes × depois) | 7/7 |
| V460-011 | contas de demonstração; `banco-seed` em produção | 6/6; código 3 |
| V460-012 | `regressao_isolada.php` sem MySQL | para com código 2 |
| V460-013 | Git sintético (índice do PC) + roteiro do Git + gancho + clone Linux | 23/23 |
| V460-014 | `protecao` num servidor que ignora `.htaccess` | acusa 20 problemas; site 7/7; aviso do `.git` na raiz do `htdocs` |
| V460-015 | varredura redigida (13 padrões) | 0 credenciais |
| V460-016 | links dos documentos | 0 quebrados |
| V460-017 | diagramas Mermaid alterados | 2/2 |

**Varredura (V460-015) — classificação das ocorrências, sem valores:** falso positivo: 2 CPFs fictícios de exemplo
em documentação e 6 e-mails de exemplo (`rh@empresa.com.br`); público por desenho: 30 hashes das contas de
demonstração (senha pública, recusadas em produção) e 2 menções ao e-mail institucional de contato; marcadores:
2 linhas do `.env.example`; dado pessoal atual: o e-mail do autor no `composer.json` (decisão sua, SEG-20);
segredo atual: **nenhum**; segredo antigo: a chave do Gemini no histórico (segundo a auditoria de 12/10), revogada
segundo o responsável (não verificado de forma independente); histórico: varredura pendente no PC.

Os scripts de teste da réplica ficaram fora do projeto. Os roteiros que valem no seu PC estão versionados em
`scripts/seguranca/`.

## 6. Testes que não puderam ser executados e motivo

| Teste | Motivo | Como executar |
|---|---|---|
| Bloqueio no Apache real (inclusive apelidos e nomes 8.3 reais), cabeçalhos no Apache, pastas vizinhas, alcance pela rede | não há Apache nem Windows na réplica | `scripts\seguranca\verificar_protecao.bat` e `-Rede` (seção 10) |
| Login, limite, códigos, sessões, cadastro privado, currículo, demonstração e erros do PHP com banco | não há MySQL na réplica; o banco real não pode ser usado | `scripts\seguranca\regressao_isolada.bat` (banco de teste novo, apagado no fim) |
| `Listen`, `AllowOverride`, `bind-address`, firewall, perfil de rede | configuração do seu XAMPP e do Windows | `scripts\seguranca\diagnostico_somente_leitura.bat` |
| Varredura do histórico do Git | os objetos do histórico ficam no seu PC (e não foram copiados, por privacidade) | o mesmo diagnóstico (seção H2, saída redigida) |
| Roteiro do Git no Windows (`core.ignorecase`, CRLF) | o roteiro é `.bat`; não executo comandos no seu Git | `scripts\seguranca\git_correcao_seguranca.bat` (confere o índice no fim) |
| Sintaxe dos `.ps1` e `.bat` novos | não há PowerShell nem `cmd` na réplica | revisão manual feita; rodar no PC |
| Gemini real (PI-14) e envio real de e-mail/SMS | sem chave; proibido fazer requisições de teste a terceiros | — |
| Clone de um repositório real no Linux | depende do commit no seu Git | seção 11 |

## 7. Regressões encontradas e correções realizadas

- **Segunda passagem — apelidos do Windows (corrigido):** com as regras da primeira versão, `.git./HEAD`,
  `.git%20/HEAD`, `GIT~1/HEAD`, `.git::$INDEX_ALLOCATION/HEAD` e, se o Apache não resolver o nome verdadeiro,
  `ENV~1` passariam. As regras agora barram ponto/espaço no fim de pasta, `~número`, `:` e conferem o caminho já
  resolvido do `.git`. O verificador testa os nomes curtos 8.3 **reais** do disco.
- **Segunda passagem — inicial do nome sem escape (SEG-36, corrigido):** 6 telas.
- **Segunda passagem — fora do projeto (SEG-35):** repositório Git na raiz do `htdocs`; o `protecao` passou a
  avisar e o verificador e o diagnóstico passaram a mostrar.
- **Diagnóstico:** a busca por `CHAVE=valor` atravessava linhas (falso positivo) e tinha aspas no padrão (o
  PowerShell 5.1 as alteraria ao chamar o `git grep`); corrigido.
- **Contadores do limite:** faxina automática de arquivos parados há mais de um dia (evita acúmulo).
- **Documentação divergente corrigida:** SEG-01 aparecia como pendente em vários documentos (corrigido no Lote A);
  "perfil ligado por padrão"; comentário "flask-cors essencial"; V459 dizia que o Linux funcionava sem distinguir
  cópia de clone; descrição da pasta temporária dos contadores.
- **Nenhuma regressão funcional** apareceu nas verificações da réplica. Observação anterior, fora do escopo e não
  alterada: no Windows em português, `php scripts/jovemconnect.php quem-usa` procura a palavra "LISTENING" no
  `netstat`, que aparece como "ESCUTANDO"; o comando só deixa de identificar o programa que usa a porta.

**Execução no PC (01/11/2026):**
- `verificar_protecao.bat`: 109 aprovados, 1 reprovado (o `.git` da raiz do `htdocs`, fora do projeto: SEG-35).
- `regressao_isolada.bat` (segunda execução, 10:40): **70 de 70 aprovados** no MariaDB 10.4.32 e PHP 8.2.12 reais —
  cabeçalhos, páginas públicas e privadas, login, IDOR, privacidade do perfil, candidatura, cadastro, limite de
  tentativas, sessões, currículo, código de verificação, logout, contas de demonstração e ausência de erros do PHP.
- `diagnostico_somente_leitura.bat` (corrigido, 10:41): Apache com `AllowOverride All`; **`Listen 80` em todas as
  interfaces e MariaDB sem `bind-address`, com regras do Firewall do Windows liberando `httpd.exe` e `mysqld.exe`
  em redes Públicas** (risco em rede compartilhada; ver seção 9); assinatura ainda não configurada; histórico
  como esperado (chave antiga revogada em `geminiAPI.env` e o registro do PI-06 no `schema.sql` até a v4.50).
- `regressao_isolada.bat` (primeira execução, 10:22): parou na instalação do `schema.sql` no banco de teste com o erro 2006 (conexão com o
  MySQL perdida). O roteiro só mostrava o número do erro e dizia ter apagado o banco de teste mesmo sem conexão.
  **Corrigido:** agora mostra a mensagem do MySQL e o comando em que parou, diz se o MySQL caiu ou só fechou a
  conexão, e tenta apagar o banco de teste com uma conexão nova (avisa se não conseguir). Causa ainda em análise.
- `diagnostico_somente_leitura.bat`: o relatório saiu só com uma linha. **Causa:** o PowerShell não diferencia
  maiúsculas e minúsculas; a lista do relatório se chamava `$L` e um laço `foreach ($l ...)` no nível do
  script a sobrescrevia. A linha que restou era um marcador de exemplo de CPF (`000.000.000-00`, inválido) do
  `jovem/cadastro.php`, não um dado real. **Corrigido:** a lista foi renomeada (`$script:Relatorio`).

## 8. Riscos residuais e limitações do ambiente

- O `.htaccess` só vale com `AllowOverride All` (padrão do XAMPP). Com `None`, é ignorado em silêncio — por isso o
  verificador e a checagem no início existem. Se uma diretiva não for permitida, o Apache responde 500 (a checagem
  no início mostra "SITE AFETADO"; reversão de emergência na seção 10).
- Enquanto o Apache escutar em todas as interfaces, o site (as páginas públicas) é acessível por outros
  dispositivos da rede; o `Listen 127.0.0.1` é decisão sua.
- O comportamento do Apache do Windows com nomes 8.3 e ponto no fim foi tratado nos dois modelos possíveis, mas só
  o verificador no seu PC confirma.
- Bloqueio de conta por terceiros: 5 senhas erradas para um e-mail bloqueiam aquele login por 15 minutos (troca
  aceita entre força bruta e incômodo). `limpar-tentativas` libera.
- Processos rodando no próprio computador podem chamar o Flask sem os limites do PHP (também leriam o `.env`).
- Códigos de verificação continuam em texto no banco (SEG-08), com limite de erros.
- Contas antigas com perfil público continuam públicas até você decidir (SQL opcional).
- O histórico do Git, as cópias no GitHub, no remoto `pessoal`, no Project e em outras máquinas não mudam com esta
  correção.
- Se o site da Aestus exibir o JovemConnect dentro de um *iframe* em **outro domínio**, os cabeçalhos
  `X-Frame-Options: SAMEORIGIN` e `frame-ancestors 'self'` impedirão (no mesmo `localhost` funciona); ajuste em
  `jc_cabecalhos_seguranca()` se for o caso.
- A réplica não reproduz o Windows, o Apache, o MariaDB nem o Gemini reais.

## 9. Ações manuais pendentes do proprietário

Em ordem sugerida (atualizada com as respostas de 29/10/2026):

1. **Assinatura:** `scripts\seguranca\configurar_assinatura.bat` e cadastrar a chave pública no GitHub como
   *Signing Key* (seção 12). Fazer **antes** do item 5.
2. **Pastas de teste (SEG-35):** apagar `cline_feats` e `usavel` da raiz do `htdocs`; descobrir o que é o `.git`
   da raiz do `htdocs` (se for uma cópia ou um repositório sem uso, mover para fora do `htdocs`).
3. **Ligar o XAMPP e rodar `scripts\seguranca\verificar_protecao.bat`** (esperado: só APROVADO) e
   **`scripts\seguranca\regressao_isolada.bat`** com o MySQL ligado.
4. **Rodar `scripts\seguranca\diagnostico_somente_leitura.bat`** e conferir `AllowOverride`, `Listen`,
   `bind-address`, firewall, seção G2 e seção H2. Se quiser minha análise, me envie os relatórios de `logs\`.
5. **Gravar a v4.60:** `scripts\seguranca\git_correcao_seguranca.bat`. Conferir no fim: "OK: o índice agora
   tem candidaturas/minhas.php" e a assinatura (`git tag -v v4.60`).
6. **Evidências:** `gerar_evidencias.bat -Versao v4.60` e `inventariar_versoes_antigas.bat` (pasta das versões
   anteriores ao Git); mandar os dois `SHA256SUMS.txt` para o seu e-mail.
7. **GitHub (PI-02):** abrir o endereço dos dois remotos numa janela anônima do navegador: deve dar 404.
8. **PI-06:** `git log --all --oneline -S "Sassati" -- schema.sql` — se aparecer algum commit, o seu sobrenome
   esteve no `schema.sql` antigo (o comando não mostra os dados). Se a senha daquela época ainda for usada em
   algum lugar, trocá-la lá.
9. **Bancos antigos:** nos PCs onde o JovemConnect rodou com o banco antigo (inclusive os da escola), apagar o
   banco (pode ter dados reais) e, onde for apresentar, recriar com `schema.sql` + seed.
10. **Rede (achado do diagnóstico de 01/11/2026):** o Apache escuta em todas as interfaces (`Listen 80`), o
    MariaDB não tem `bind-address` e o Firewall do Windows tem regras que liberam `httpd.exe` e `mysqld.exe` em
    redes **Públicas**. Em Wi-Fi compartilhado (escola, evento), outros aparelhos alcançam o site e a porta 3306
    do banco. Correção (no PC, fora do projeto): `Listen 127.0.0.1:80` no `httpd.conf`, `bind-address=127.0.0.1`
    na seção `[mysqld]` do `my.ini` e desativar as duas regras de entrada no Firewall (Painel de Controle →
    Firewall do Windows Defender → Configurações avançadas → Regras de Entrada). O Tailscale Serve continua
    funcionando (ele chega por 127.0.0.1).
11. **Apresentação:** seguir `docs/APRESENTACAO.md` (Tailscale **Serve**, não Funnel; `Listen 127.0.0.1:80` no
    PC de casa; contas de demonstração; limpeza no fim).
12. **Depois da apresentação:** TER-01 (Gemini), PI-07, PI-14, PI-16 e SEG-08 (aceitos só até lá); trocar as
    fotos antes de qualquer uso público; contrato entre os integrantes com advogado (seção 13).

**Respostas e decisões do responsável (29/10/2026):**

| Assunto | Decisão / fato [Declarado] |
|---|---|
| Apresentação | provavelmente pelo Tailscale a partir do PC de casa; acesso só pelos computadores da equipe (dois notebooks e um PC da escola); dados limpos depois; o site da Aestus abre o JovemConnect por link (os cabeçalhos da v4.60 não atrapalham) |
| Banco da demonstração | `schema.sql` atual + `seed_demo.sql`; visitantes orientados a não usar dados pessoais além do nome |
| Assinatura | sim, antes de gravar a v4.60 |
| Pastas no `htdocs` | `cline_feats` e `usavel` eram testes; o responsável apaga; o `.git` da raiz: origem desconhecida |
| Fotos | copiadas do Google Imagens; mantidas por enquanto |
| Logotipos | criação da equipe (autor registrado em `ORIGEM_DOS_ATIVOS.md`); arquivos editáveis a reunir |
| Equipe e nomes | papéis por função registrados; lista nominal pendente até o contrato |
| Antes de 06/08 | versões em pastas locais, na pasta Documentos do PC do responsável, só ali |
| Ferramentas de IA | ChatGPT (1.xx e assistente), Gemini (2.00+, inclusive Cline), Claude (3.00+) |
| GitHub | duas contas (desenvolvedor principal e secundário), 2FA, privado, sem outros colaboradores |
| Cópia no Project e *zip* na conta pessoal | manter, como registro com data |
| PI-06 | manter o histórico (o registro deve ser do próprio responsável) |
| PI-07 | manter como está até depois da apresentação |
| PI-14, PI-16, SEG-08 | depois da apresentação |
| Gemini (TER-01) | público a partir de 14 anos; plano gratuito; conta titular de pessoa maior de idade; adequar depois da apresentação |
| Aestus | startup sem CNPJ; donos: os integrantes, sujeitos ao contrato |
| Escola | o trabalho é dos integrantes; regulamento sem regra de publicação, uso comercial ou IA; não declarar IA no texto |
| Vínculos | nenhum uso de recursos de emprego |
| Licença | todos os direitos reservados (`LICENSE`) |
| INPI | busca sem marcas iguais; intenção de registrar marcas e programa |
| TCC | não será publicado; apresentado no estande, mostrando os critérios do Match |
| Evidências | guardadas no PC do responsável, por enquanto |

## 10. Instruções de validação no XAMPP real

1. Abrir o XAMPP Control Panel e ligar **Apache** e **MySQL** (ou rodar `iniciar_jovemconnect.bat`: no passo do
   Apache ele já faz a verificação curta e avisa se algo estiver exposto).
2. Na pasta do projeto, dar dois cliques em `scripts\seguranca\verificar_protecao.bat`.
   - Esperado: todas as linhas **APROVADO**; grupos `interno`, `pasta`, `variante`, `nome8.3`, `fonte`,
     `listagem`, `canario`, `site`, `cabecalho` e `htdocs`. Código de saída 0. Relatório em
     `logs\verificacao_protecao-<data>.log`.
   - `ACESSIVEL`: o `.htaccess` não está valendo → `C:\xampp\apache\conf\httpd.conf`, bloco
     `<Directory "C:/xampp/htdocs">`, `AllowOverride All`; reiniciar o Apache; rodar de novo.
   - `ERRO NO SERVIDOR`/`BLOQUEADO` em `site`: a proteção afetou o site; ver `C:\xampp\apache\logs\error.log`.
   - `ACESSIVEL (fora do projeto)`: SEG-35 (seção 9, item 5).
3. Para ver o alcance pela rede: `scripts\seguranca\verificar_protecao.bat -Rede`.
4. Com o MySQL ligado: `scripts\seguranca\regressao_isolada.bat` → "RESULTADO" no fim e relatório em
   `logs\regressao_isolada-<data>.log`. Nada é gravado no banco `jovemconnect`.
5. Roteiro manual rápido (navegador): entrar como jovem e como empresa; errar a senha 5 vezes numa conta de teste
   (aparece "Muitas tentativas…"; `C:\xampp\php\php.exe scripts\jovemconnect.php limpar-tentativas` libera);
   criar um jovem novo e conferir que o perfil nasce não visível; enviar um currículo e abri-lo pelo perfil;
   abrir `http://localhost/JovemConnect/.env` e `.../docs/README.md` (403).
6. **Reversão de emergência** (só se todas as páginas derem erro 500 depois da atualização): no Prompt de Comando,
   na pasta do projeto, `ren .htaccess htaccess.desativado`, reiniciar o Apache e me enviar o trecho do
   `error.log`. Os `.htaccess` das pastas internas continuam protegendo; para reativar,
   `ren htaccess.desativado .htaccess`.

## 11. Instruções de teste em clone Linux

Depois de gravar a v4.60 no Git (seção 9, item 4), num Linux com Apache + PHP + MariaDB (ou XAMPP para Linux):

```bash
cd /var/www/html                     # ou /opt/lampp/htdocs
git clone <URL-do-repositorio> JovemConnect    # ou: git clone jovemconnect_<data>.bundle JovemConnect
cd JovemConnect
git ls-files | grep -i '^candidaturas/'                   # esperado: candidaturas/minhas.php (minúsculo)
git ls-files | grep -E '^(geminiAPI\.env|repomix-output\.xml)$' || echo "ok: fora do Git"
ls -la .htaccess docs/.htaccess includes/.htaccess        # devem existir
bash iniciar_jovemconnect.sh                              # sobe tudo e roda a checagem de proteção
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/JovemConnect/candidaturas/minhas.php   # 302
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/JovemConnect/Candidaturas/minhas.php   # 404
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/JovemConnect/docs/README.md            # 403
php scripts/jovemconnect.php protecao                     # "RESULTADO: protecao valendo neste Apache."
```

Sem Apache, só a parte de maiúsculas pode ser testada: `php -S 127.0.0.1:8080 -t ..` na pasta do clone e os dois
primeiros `curl` (o servidor embutido **não** aplica `.htaccess`).

## 12. Procedimento futuro de assinatura e backup

Resumo de `docs/PROCEDIMENTO_EVIDENCIAS.md`:

1. **Uma vez** (`scripts\seguranca\configurar_assinatura.bat` faz tudo com confirmação e um teste): criar sua
   chave SSH de assinatura (`ssh-keygen -t ed25519 ...`), configurar `gpg.format ssh`,
   `user.signingkey`, `commit.gpgsign` e `tag.gpgsign`, o arquivo `allowed_signers` e cadastrar a chave pública no
   GitHub como *Signing Key*. A chave privada nunca sai do seu computador.
2. **A cada versão:** commit assinado → tag assinada (`git tag -s`, nunca recriar) → `git tag -v` → envio sem
   `--force` (o roteiro do Git faz isso com confirmação).
3. **Pacote de evidências:** `scripts\seguranca\gerar_evidencias.bat -Versao v4.60` → zip, manifesto SHA-256,
   *bundle* verificado e `SHA256SUMS.txt`, fora do `htdocs`.
4. **Data externa e cópias:** enviar só o `SHA256SUMS.txt` por e-mail (para você e o orientador) ou a um serviço de
   carimbo de tempo; guardar a pasta, **cifrada**, em dois lugares independentes (o *bundle* contém o histórico
   inteiro).
5. **Conferir:** `certutil -hashfile <arquivo> SHA256`, `git bundle verify`, `git clone <bundle>`,
   `git rev-parse "v4.60^{commit}"`, `git tag -v v4.60`.
6. **Preservar** `refs/original/`, `refs/cline/`, `backup-*`, tags e *reflog*; nada de `gc --prune`.

## 13. Questões jurídicas e de titularidade que exigem decisão humana

(Detalhes em `docs/TITULARIDADE_E_LICENCA.md`; nada aqui é parecer jurídico.)

- Quem é titular do código (integrantes, Aestus, escola) e o que diz o regulamento de TCC.
- Partes feitas em estágio, emprego, bolsa ou contrato (Lei 9.609/1998, art. 4º).
- Tratamento e declaração dos trechos produzidos com IA (Claude, Cline) e termos de uso dessas ferramentas.
- Licença do projeto (nenhuma escolhida), acordo entre integrantes e uso dos nomes.
- Marcas "JovemConnect" e "Aestus" e os logotipos (busca e eventual registro no INPI); registro do programa
  (opcional).
- Origem e licença das duas fotos; autoria dos logotipos.
- O que manter confidencial, dado o que o TCC vai publicar; transparência do Match × segredo (LGPD, art. 20).
- Histórico do Git com registro de aparência pessoal, possivelmente de menor (LGPD, art. 14): manter privado e
  documentado ou reescrever com backup prévio.

Situação depois das respostas de 29/10/2026: licença escolhida e aplicada; titularidade, nomes e acordos
dependem de um contrato particular em preparação. Pontos para levar ao advogado (autoria não se transfere,
cessão escrita de cada autor, assistência dos pais para menores de 18 anos, programa de computador não é
patenteável, a Aestus sem CNPJ não pode ser titular) em `TITULARIDADE_E_LICENCA.md`. Nova questão: adequação
do uso do Gemini aos termos da API (TER-01).

## 14. Lista final de verificação reproduzível

- [ ] `php -l` em todos os `.php` sem erro (`for /r %f in (*.php) do @C:\xampp\php\php.exe -l "%f" | findstr /v "No syntax"`).
- [ ] `scripts\seguranca\verificar_protecao.bat` → só APROVADO (código 0), em casa e na escola.
- [ ] `scripts\seguranca\verificar_protecao.bat -Rede` → na escola, "Apache NAO escuta" nos IPs da rede, ou
      bloqueios APROVADOS se ele escutar.
- [ ] `iniciar_jovemconnect.bat` → sem aviso de proteção no passo do Apache.
- [ ] `scripts\seguranca\regressao_isolada.bat` → todas as verificações aprovadas.
- [ ] `scripts\seguranca\diagnostico_somente_leitura.bat` → `AllowOverride All`; `Listen` conforme a decisão;
      nenhum segredo atual na seção H1; histórico (H2) registrado para a decisão do PI-06.
- [ ] `scripts\seguranca\git_correcao_seguranca.bat` → `geminiAPI.env` e `repomix-output.xml` "nao rastreado" e
      "continua na pasta"; "OK: o índice agora tem candidaturas/minhas.php"; commit e tag criados.
- [ ] `git ls-files | findstr /i "geminiAPI.env repomix-output.xml"` → nada.
- [ ] `git log -1 --show-signature` e `git tag -v v4.60` → assinatura válida (se configurada).
- [ ] `scripts\seguranca\gerar_evidencias.bat -Versao v4.60` → pasta fora do `htdocs`; `SHA256SUMS.txt` enviado
      por e-mail; duas cópias cifradas guardadas.
- [ ] Clone Linux (seção 11) → `candidaturas/minhas.php` 302, `Candidaturas/` 404, `docs/` 403.
- [ ] Decisões da seção 9 registradas (data e escolha) em `docs/AUTORIA_E_ORIGEM.md` ou num registro seu.
- [ ] `scripts\seguranca\configurar_assinatura.bat` → "Teste aprovado" e chave pública cadastrada no GitHub.
- [ ] `scripts\seguranca\inventariar_versoes_antigas.bat` → inventário fora da pasta; `SHA256SUMS.txt` enviado
      por e-mail.
- [ ] `liberar_logins.bat` testado na véspera da apresentação ([APRESENTACAO.md](APRESENTACAO.md)).
