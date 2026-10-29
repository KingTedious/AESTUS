# Classificação da informação

> **Classificação deste documento: INTERNO.** Versão v4.60 (29/10/2026). Criado na correção de segurança e
> propriedade intelectual (PI-03, PI-07 e PI-15 do `RELATORIO_AUDITORIA_PI.md`).
> Não contém credenciais, dados pessoais nem os valores do Match.
>
> Este documento é técnico e organizacional. **Não é parecer jurídico** e não afirma que exista segredo
> comercial juridicamente protegido: só indica como cada tipo de informação deve ser tratado para reduzir
> exposição e cópia desnecessárias.

## Por que classificar

O repositório no GitHub é **privado desde a criação**, segundo o proprietário (não reverificado nesta
correção). Isso reduz a exposição, mas não protege:

- o que o Apache entrega a quem alcança o computador (até a v4.59, `.env`, `.git/`, `docs/` e o
  `repomix-output.xml`; desde a v4.60, bloqueados pelo `.htaccess` da raiz **se** ele estiver valendo —
  confira com `scripts\seguranca\verificar_protecao.bat`);
- cópias em pendrive, PCs da escola, serviços de IA e anexos;
- o que for publicado no texto, nos slides ou na apresentação do TCC.

A classificação diz **onde** cada coisa pode estar e **quem** pode vê-la.

## As seis categorias

| Categoria | O que é | Regra |
|---|---|---|
| **PÚBLICO POR NECESSIDADE** | Tudo o que o navegador precisa receber para o site funcionar: HTML gerado, CSS, JavaScript, imagens, logotipos, textos de Termos e Privacidade, respostas prontas do assistente, a nota do Match e a explicação mostrada ao usuário | Qualquer visitante pode ver e copiar. Nunca colocar segredo, regra interna ou dado pessoal de terceiros aqui. A proteção é só o direito autoral sobre a expressão; ofuscar não resolve |
| **INTERNO** | Código-fonte (PHP, Python, scripts), estrutura do banco, migrações, documentação técnica geral | Equipe e orientação. Repositório privado. Não servido pelo Apache. Pode ir ao TCC em trechos ou resumos, com revisão |
| **CONFIDENCIAL** | O que dá vantagem a quem copia ou ajuda a burlar o sistema: algoritmo exato do Match, instruções e esquemas da IA, regras de moderação, mapa completo de funções, riscos de segurança ainda abertos, relatórios de auditoria | Só quem precisa. Nunca em texto público, slide, anexo ou repositório público. Não enviar a terceiros sem necessidade. No TCC, só a versão resumida (abaixo) |
| **DADOS PESSOAIS** | Qualquer dado de pessoa real: contas, CPF, nascimento (há usuários **menores de idade**), telefone, endereço, currículos, IP, conteúdo de logs, cópias do banco | LGPD. O mínimo necessário, só no banco e em `uploads/curriculos/`. Nunca no Git, em documentos, relatórios, prints ou testes. Testes só com dados fictícios |
| **SEGREDOS E CREDENCIAIS** | Senhas, chaves de API, tokens, segredos do autenticador (TOTP), chaves de assinatura | Só no `.env` (ou no banco, no caso do TOTP). Nunca no Git, em documentos, em mensagens ou em ferramentas de IA. Se vazar: trocar a credencial |
| **ARTEFATOS DE DESENVOLVIMENTO** | Itens gerados ou locais: `repomix-output.xml`, `venv/`, `__pycache__/`, logs, relatórios dos scripts de verificação, pacotes de evidência | Regeneráveis ou locais. Fora do Git (`.gitignore`), não servidos pelo Apache, não copiados para entrega sem necessidade |

Quando um item cabe em duas categorias, vale a **mais restritiva** (por exemplo, um log é artefato **e**
pode conter dado pessoal: trate como dado pessoal).

## Classificação dos ativos

"Apache" indica o que o servidor deve fazer desde a v4.60, com o `.htaccess` valendo.

| Ativo | Categoria | Apache | Git | Observação |
|---|---|---|---|---|
| Páginas `.php` (resposta gerada) | Público por necessidade | entrega | rastreado | o **código-fonte** delas é Interno e não sai como texto (`verificar_protecao` testa `x.php.` e `::$DATA`) |
| `assets/css`, `assets/js`, `assets/img` | Público por necessidade | entrega | rastreado | origem e licenças em [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md) |
| Termos de Uso e Política de Privacidade (`includes/documentos.php`) | Público por necessidade | texto entregue pela página | rastreado | minutas; revisão jurídica pendente |
| Respostas prontas do assistente (`includes/assistente.php`) | Público por necessidade | vão ao navegador | rastreado | PI-16: aceito por natureza |
| Nota do Match e explicação por critério (`Vagas/avaliar.php`) | Público por necessidade (para o usuário logado) | entregue pela página | — | ver [Pontuação apresentada](#pontuação-apresentada-aos-usuários-pi-07) |
| Código PHP e Python em geral | Interno | bloqueado (`.py`) / executado (`.php`) | rastreado | — |
| `includes/match.php`, `IA/recomendar.py` (`avaliar`) | **Confidencial** | `includes/` e `.py` bloqueados | rastreado | contêm os pesos e as regras exatas do Match |
| Instruções e esquemas da IA (`CTX_INSTRUCOES`, `ASSIST_INSTRUCOES`, `MOD_INSTRUCOES` em `IA/recomendar.py`) | **Confidencial** | bloqueado | rastreado | a confidencialidade das instruções **não** é barreira de segurança: o servidor valida e limita tudo de qualquer forma |
| Regras de moderação (`includes/moderacao.php`, `includes/validacao.php`) | **Confidencial** | bloqueado | rastreado | conhecer as listas ajuda a burlar a moderação |
| `schema.sql`, `migrations/`, `database/` | Interno | bloqueado | rastreado | `database/seed_demo.sql`: só demonstração (ver abaixo) |
| `scripts/` (ajudantes, verificação, regressão) | Interno | bloqueado | rastreado | — |
| `.env` | **Segredo** | bloqueado | ignorado | nunca ler, copiar nem anexar |
| `geminiAPI.env` (legado, vazio) | Segredo | bloqueado | **retirado do índice na v4.60** (cópia local mantida) | a chave antiga ficou no histórico (PI-06) |
| `.env.example` | Interno | bloqueado | rastreado | só nomes de variáveis e marcadores |
| Banco `jovemconnect` (MariaDB), dumps e backups | **Dados pessoais** | — | nunca | backups fora do `htdocs` |
| `uploads/curriculos/*.pdf` | **Dados pessoais** | bloqueado (`.htaccess` próprio) | ignorado | entregues só por `uploads/ver_curriculo.php`, com permissão; nome aleatório desde a v4.60 |
| `logs/*.log`, `logs/*.json` | Artefato **e** dados pessoais (IP anonimizado, eventos) | bloqueado | ignorado | não anexar logs inteiros em conversas ou relatórios |
| `repomix-output.xml` | Artefato (contém Interno **e** Confidencial) | bloqueado | **retirado do índice na v4.60** (gerado só localmente) | ver [Ferramentas de IA](#ferramentas-de-ia-e-o-repomix) |
| `venv/`, `IA/__pycache__/` | Artefato | bloqueado | ignorado | `pyvenv.cfg` mostra o caminho do Python (nome do usuário do Windows) |
| `.git/` (histórico) | Interno, com trechos **Confidenciais**, **Segredo** antigo e possível **dado pessoal** antigo | bloqueado | — | PI-04 e PI-06: preservar sem reescrever |
| Pacotes de evidência (`gerar_evidencias`) | Interno | fora do `htdocs` por desenho | ignorado (`*.bundle`) | guardar em dois lugares independentes |
| Contas de demonstração (`@demo.jovemconnect.example`) | Interno; credencial **pública por desenho** | — | `seed_demo.sql` rastreado | não entram com `JC_AMBIENTE=producao`; `banco-seed` se recusa nesse modo |
| Segredo TOTP dos administradores (`administradores.mfa_segredo`) | Segredo | — | nunca | SEG-28 |
| Chave de assinatura (SSH/GPG) do autor | Segredo | — | nunca | só a parte **pública** vai ao GitHub ([PROCEDIMENTO_EVIDENCIAS.md](PROCEDIMENTO_EVIDENCIAS.md)) |

## Documentação (`docs/`)

Toda a pasta `docs/` é bloqueada pelo Apache (regra da raiz e `docs/.htaccess`). A coluna "TCC" diz como
usar cada documento no texto ou na apresentação.

| Documento | Categoria | TCC |
|---|---|---|
| [README.md](README.md), [VISAO_GERAL.md](VISAO_GERAL.md), [GLOSSARIO.md](GLOSSARIO.md), [REQUISITOS.md](REQUISITOS.md) | Interno | pode ser adaptado |
| [ARQUITETURA.md](ARQUITETURA.md), [FLUXOS_E_FLUXOGRAMAS.md](FLUXOS_E_FLUXOGRAMAS.md), [INSTALACAO_E_EXECUCAO.md](INSTALACAO_E_EXECUCAO.md), [CONFIGURACAO.md](CONFIGURACAO.md), [MIGRACOES.md](MIGRACOES.md) | Interno | pode ser adaptado (diagramas e passos; sem valores do `.env`) |
| [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md), [MATRIZ_DE_DEPENDENCIAS.md](MATRIZ_DE_DEPENDENCIAS.md) | Interno | diagrama ER e visão geral; o dicionário completo só em anexo restrito |
| [LOGS.md](LOGS.md), [LOGS_E_DIAGNOSTICO.md](LOGS_E_DIAGNOSTICO.md), [TESTES.md](TESTES.md), [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md) | Interno | resumo (estratégia e números de testes) |
| [SEED_DEMO.md](SEED_DEMO.md) | Interno | não publicar a senha de demonstração fora da apresentação |
| [ASSISTENTE_E_IA.md](ASSISTENTE_E_IA.md) | Interno | pode ser adaptado |
| [IA_E_MATCH.md](IA_E_MATCH.md) | **Confidencial** (seção "Match — algoritmo exato" e regras da análise contextual) | **só a versão resumida** abaixo |
| [ARQUIVOS_E_FUNCOES.md](ARQUIVOS_E_FUNCOES.md) | **Confidencial** (mapa completo e resumo fiel das instruções da IA) | não usar; citar só a organização em pastas |
| [ROTAS_E_ENDPOINTS.md](ROTAS_E_ENDPOINTS.md) | **Confidencial** (mapa de todas as entradas do sistema) | resumo por área |
| [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md) | **Confidencial** (permissões, limites, regras de moderação) | resumo do que a Central faz, sem listas e limites |
| [SEGURANCA_E_PRIVACIDADE.md](SEGURANCA_E_PRIVACIDADE.md), [TESTES_RESULTADOS.md](TESTES_RESULTADOS.md), [RELATORIO_CORRECAO_SEGURANCA.md](RELATORIO_CORRECAO_SEGURANCA.md) | **Confidencial** (riscos abertos e roteiros de ataque reproduzidos) | só a lista de mecanismos de proteção, sem riscos abertos |
| [AUTORIA_E_ORIGEM.md](AUTORIA_E_ORIGEM.md) | **Confidencial** (desde 29/10/2026: uso de IA, papéis da equipe, planos de contrato) | não usar; o regulamento não trata de IA e o responsável optou por não declarar no texto (ver o próprio documento) |
| [CLASSIFICACAO.md](CLASSIFICACAO.md), [TITULARIDADE_E_LICENCA.md](TITULARIDADE_E_LICENCA.md), [PROCEDIMENTO_EVIDENCIAS.md](PROCEDIMENTO_EVIDENCIAS.md), [APRESENTACAO.md](APRESENTACAO.md) | Interno | não usar |
| [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md), `THIRD_PARTY_NOTICES.md` (raiz) | Interno | pode ser usado (créditos e licenças) |

Os documentos marcados como Confidenciais começam com um aviso de classificação. **Nada foi apagado nem
movido**: como o repositório é privado, a documentação técnica completa continua no lugar, para a equipe.
Se um dia o repositório for aberto, a parte confidencial deve sair dele antes (decisão do proprietário).

**Relatórios no Project do claude.ai** (`RELATORIO_*.md`, `PENDENCIA_HISTORICO_GIT.md`, `AUDITORIA_ETAPA1.md`):
Confidenciais. O Project também guarda uma **cópia antiga do código (05/10/2026)** com `schema.sql` da época
(anterior à retirada do registro com aparência de dado pessoal), `geminiAPI.env` e um log. Essa cópia não
foi lida nesta correção; recomenda-se que o proprietário a revise e retire do Project o que não for
necessário (PI-03, PI-11). **Decisão do responsável (29/10/2026): manter a cópia**, como registro com data de
05/10/2026 (o responsável indicou que o registro do PI-06 deve ser dele; confirmação pendente). Vale o mesmo para o *zip* do projeto
numa conta pessoal do claude.ai.

## Versões resumidas para o TCC

Textos que descrevem o funcionamento **sem** os detalhes confidenciais. Podem ser adaptados livremente.

**Match.** "A nota de compatibilidade (0 a 100) é calculada por regras fixas, no servidor, comparando o
perfil do jovem com a vaga em cinco aspectos: localização, área de interesse, curso técnico, escolaridade
compatível com o tipo de contrato e o próprio tipo de contrato, com um ajuste final. O jovem vê a nota e a
explicação de cada aspecto. A IA generativa não altera a nota: ela só escreve uma análise em texto a partir
do resultado." — Não incluir: pontos de cada critério, as regras de pontuação parcial, o ajuste, a
normalização de textos e as limitações conhecidas.

**IA generativa.** "As instruções enviadas ao modelo ficam no servidor e nunca vão ao navegador. O texto do
usuário é tratado como dado, os dados pessoais são mascarados antes do envio e a resposta só é usada se
passar por uma validação de formato e tamanho; sem a IA, o sistema continua funcionando com respostas
prontas." — Não incluir: o texto das instruções, os esquemas de resposta e os limites.

**Moderação.** "Os formulários passam por validação estrutural (que recusa código executável) e por regras
de moderação que encaminham conteúdo suspeito para revisão humana na Central administrativa." — Não
incluir: listas de termos, expressões regulares e limites.

**Segurança.** Citar os mecanismos (consultas parametrizadas, proteção CSRF, sessão endurecida, limite de
tentativas, bloqueio de arquivos internos, cabeçalhos de segurança, Central com segundo fator e trilha de
auditoria). Não incluir: limites exatos, riscos ainda abertos e roteiros de teste de ataque.

## Pontuação apresentada aos usuários (PI-07)

**Onde aparece.** A ação `analise` de `IA/avaliar_ajax.php` devolve, para cada critério, os pontos obtidos,
o máximo e o percentual (`includes/match.php`). `Vagas/avaliar.php` mostra "X de Y pontos" e uma barra por
critério ao jovem (em qualquer vaga visível) e à empresa (para candidatos com relação de recrutamento:
candidatura a uma vaga dela ou perfil tornado público pelo próprio jovem).

**Risco de inferência.** Os pesos ficam **visíveis diretamente** (o máximo de cada critério). As regras de
pontuação parcial podem ser reconstruídas por quem criar uma conta de jovem, variar o próprio perfil e
consultar várias vagas: a ação `analise` não tem limite de consultas (só a análise com IA, `contexto`, tem
limite por sessão). Não é preciso acesso ao código.

**Por que nada foi mudado na v4.60.**
1. A explicação por critério é a transparência que o produto oferece ao jovem e à empresa, e é parte da
   demonstração do TCC.
2. A nota ajuda a avaliar o perfil profissional de candidatos. A LGPD (art. 20) dá ao titular o direito
   de pedir revisão de decisões tomadas unicamente com base em tratamento automatizado e de receber
   informações claras sobre os critérios, **observados os segredos comercial e industrial**. O equilíbrio
   entre explicar e proteger é uma decisão do responsável, com orientação jurídica.
3. O TCC **não será publicado** (só apresentado no estande, segundo o responsável, 29/10/2026) e a
   apresentação mostra só os critérios usados no cálculo. Mesmo assim, a tela mostra os pontos; se um dia o
   funcionamento for publicado, o algoritmo dificilmente será
   segredo, com ou sem mudança na tela.

**Alternativas mínimas (não implementadas; escolher se houver interesse comercial):**

| Opção | O que muda | Prós | Contras |
|---|---|---|---|
| A. Limite de consultas da `analise` por sessão/conta | nada na tela | dificulta a coleta em massa; risco baixo de regressão | não esconde os pesos; um limite baixo atrapalha quem compara muitas vagas |
| B. Esconder o máximo ("X de Y pontos" → só a barra e o texto) | um rótulo | pesos deixam de aparecer diretamente | ainda inferíveis pelos percentuais e pela nota total |
| C. Faixas qualitativas por critério (alta/média/baixa) e nota total arredondada | a apresentação | inferência bem mais difícil | menos explicação ao usuário; exige rever textos, testes e a Política de Privacidade |
| D. Manter como está (situação atual) | nada | transparência total; coerente com um TCC | regras reconstruíveis |

Qualquer opção diferente de D exige testes de regressão da página de análise e decisão registrada do
proprietário. **Decisão do responsável (29/10/2026): opção D, mantida até depois da apresentação** (a
pontuação é parte visual da demonstração); rever depois. **Situação: ACEITO FORMALMENTE (temporário).**

## Ferramentas de IA e o repomix

O `repomix-output.xml` reúne o código e a documentação num arquivo só, para entregar a ferramentas de IA.
Ele mistura material Interno e Confidencial, por isso:

- deixou de ser versionado na v4.60 (continua sendo gerado localmente e entregue junto de cada alteração);
- é bloqueado pelo Apache;
- nunca deve conter `.env`, `geminiAPI.env`, logs, `venv/`, currículos nem cópias do banco;
- enviá-lo a um serviço de IA é compartilhar o conteúdo com um terceiro, nos termos desse serviço:
  faça isso só quando necessário.

## Como classificar um arquivo novo

1. O navegador precisa dele para o site funcionar? → Público por necessidade (e sem segredos dentro).
2. Tem senha, chave, token ou segredo? → Segredo: só no `.env`.
3. Tem dado de pessoa real? → Dados pessoais: só no banco ou em `uploads/curriculos/`.
4. Explica pesos, instruções da IA, regras de moderação ou riscos abertos? → Confidencial.
5. É gerado ou local (log, saída de ferramenta, cache, pacote)? → Artefato: `.gitignore`.
6. O resto → Interno.

Depois: confira se o `.htaccess` da raiz cobre o novo tipo de arquivo ou pasta e rode
`scripts\seguranca\verificar_protecao.bat`.
