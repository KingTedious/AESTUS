# Arquivos e funções

> Gerado na auditoria de documentação de 10/10/2026 sobre a versão **v4.00** (tag `v4.00`) e **atualizado para a v4.56** (26/10/2026) nos arquivos que mudaram nas versões 4.55 e 4.56: painel do jovem, lista de vagas, card de vaga, inicialização e porta da IA, scripts de iniciar/parar e configuração; na **v4.58** (27/10/2026), compatibilidade com PHP 7.4/8.0 e a exclusão da pasta pelo `parar_jovemconnect.bat`; na **v4.59** (28/10/2026), funcionamento no Linux (`.sh`, caminhos com maiúsculas corretas, ajudante para Linux). Nos demais arquivos, os números de linha são os da v4.00 e podem ter mudado depois. Legenda: **[C]** confirmado no código · **[I]** inferido (com a evidência indicada) · **[NC]**/**[N]** não confirmado.

Catálogo de **todos os arquivos de código e configuração** do repositório (88 arquivos versionados na v4.00; os arquivos `.env`, `geminiAPI.env` e `logs/*.log` existem só na máquina e não foram abertos). Documentos `.md` e arquivos SQL estão descritos em [README.md](README.md), [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md) e [MIGRACOES.md](MIGRACOES.md).

Para cada arquivo: papel, quem o usa, entradas, saídas, banco, funções (assinatura, parâmetros, retorno, efeitos colaterais, erros) e observações. O índice de funções no fim lista as 115 funções PHP e as 20 funções Python com a linha da definição e quem as chama.

## Sumário

- [Módulo A — Autenticação, sessão, segurança base e contas](#módulo-a--autenticação-sessão-segurança-base-e-contas)
- [Módulo B — Cadastro, perfis, locais de trabalho e currículo](#módulo-b--cadastro-perfis-locais-de-trabalho-e-currículo)
- [Módulo C — Vagas, candidaturas, favoritos e painéis](#módulo-c--vagas-candidaturas-favoritos-e-painéis)
- [Módulo D — IA, Match e Assistente](#módulo-d--ia-match-e-assistente)
- [Módulo E — Interface pública, componentes compartilhados, CSS e configuração](#módulo-e--interface-pública-componentes-compartilhados-css-e-configuração)
- [Índice de funções PHP](#índice-de-funções-php)
- [Índice de funções Python (Flask)](#índice-de-funções-python-flask)

## Módulo A — Autenticação, sessão, segurança base e contas

Chaves de sessão do módulo (confirmadas):

| Chave `$_SESSION` | Quem grava | Quem lê / apaga |
|---|---|---|
| `jovem_id` (int), `jovem_nome` | `jovem/processar_login.php`, `jovem/cadastro.php` | todo o projeto; apagada em `logout.php`, `jovem/excluir_conta.php` |
| `empresa_id` (int), `empresa_nome` | `empresa/processar_login.php`, `empresa/cadastro.php` | todo o projeto; apagada em `logout.php`, `empresa/excluir_conta.php` |
| `csrf_token` (64 hex) | `includes/csrf.php` → `csrf_token()` | `csrf_verify()` |
| `erro_login` | `jovem/processar_login.php`, `empresa/processar_login.php`, `includes/auth.php` | exibida e apagada (`unset`) em `jovem/login.php` e `empresa/login.php` |
| `sucesso_login` | `recuperar_senha.php` (etapa 3) | exibida e apagada nas duas telas de login |
| `recuperacao_etapa` (1/2/3), `recuperacao_email_mascarado`, `recuperacao_email_raw`, `recuperacao_tabela` (`jovens`/`empresas`), `recuperacao_codigo_ok` (bool) | `recuperar_senha.php` | `recuperar_senha.php` |

### `includes/session.php`
- **Tipo:** módulo auxiliar (include), PHP.
- **Responsabilidade:** substitui `session_start()` por uma inicialização endurecida (cookie HttpOnly, SameSite=Lax, `use_strict_mode`, `Secure` só sob HTTPS) e oferece a regeneração do ID de sessão após autenticar.
- **Quando executa / quem usa:** incluído por `includes/csrf.php` (que chama `jc_session_start()` automaticamente, portanto por toda página que inclui csrf.php), e diretamente por `index.php`, `comecar.php`, `processar_login.php`, `dashboard/index.php`, `candidaturas/minhas.php`, `uploads/ver_curriculo.php`, `IA/assistente_ajax.php` (linha 161). `jc_session_regenerate()` é chamada por `jovem/processar_login.php:59`, `empresa/processar_login.php:54`, `jovem/cadastro.php:203`, `empresa/cadastro.php:146`.
- **Depende de:** nada.
- **Entradas:** `$_SERVER['HTTPS']`, `$_SERVER['SERVER_PORT']` (para decidir `secure`).
- **Saídas:** cookie de sessão (`PHPSESSID`), lifetime 0 (cookie de navegador), path `/`.
- **Banco:** nenhum.
- **Permissões e validações:** n/a.
- **Erros:** `@ini_set` silencia falhas; se a sessão já estiver ativa, `jc_session_start()` retorna sem fazer nada.
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz / efeitos | Quem chama |
|---|---|---|---|---|---|
| `jc_session_start` | 17 | — | `void` | Se `session_status() !== PHP_SESSION_NONE`, retorna. Fora do CLI: `session.use_strict_mode=1`, `session.cookie_httponly=1`, `session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>$secure])` (PHP ≥ 7.3; senão truque `'/; samesite=Lax'` no path). Depois `session_start()`. | csrf.php e os arquivos listados acima |
| `jc_session_regenerate` | 50 | — | `void` | `session_regenerate_id(true)` (apaga a sessão antiga) se houver sessão ativa. | processar_login (jovem/empresa), cadastro (jovem/empresa) |

- **Riscos / limitações:** [C] não há expiração por inatividade nem limite absoluto de duração da sessão no código (depende de `session.gc_maxlifetime` do php.ini). [C] Arquivos que ainda chamam `session_start()` direto (`logout.php:5`, `includes/auth.php:20`, `includes/header.php:18`) não aplicam esses parâmetros se forem os primeiros a iniciar a sessão (no caso de auth.php e header.php, na prática a sessão já foi iniciada por csrf.php nas páginas do módulo — [I]).

### `includes/csrf.php`
- **Tipo:** módulo auxiliar (include), PHP + JavaScript gerado.
- **Responsabilidade:** proteção CSRF por sessão (sincronizador de token). Gera um token por sessão, emite o campo hidden, valida o token em POSTs (campo, cabeçalho `X-CSRF-Token` ou JSON) e injeta um wrapper de `fetch()` que adiciona o cabeçalho automaticamente em requisições não-GET de mesma origem.
- **Quando executa / quem usa:** incluído no topo de quase todas as páginas com estado (`jovem/login.php`, `empresa/login.php`, `jovem/processar_login.php`, `empresa/processar_login.php`, `recuperar_senha.php`, `verificacoes/*.php` exceto validacoes_nomes, `jovem/excluir_conta.php`, `empresa/excluir_conta.php`, `IA/assistente_ajax.php`, etc.). Ao ser incluído, chama `jc_session_start()` (linha 24). `csrf_js()` é emitido por `Vagas/avaliar.php`, `Vagas/candidatos.php`, `Vagas/favoritos.php`, `Vagas/listar.php`, `dashboard/empresa.php`, `dashboard/jovem.php`, `jovem/perfil.php`. `csrf_require(true)` (modo JSON) é usado em `Vagas/candidatos.php:62`, `IA/avaliar_ajax.php:34`, `IA/assistente_ajax.php:50`.
- **Depende de:** `includes/session.php`; opcionalmente `jc_log_seguranca()` (logger.php), só se já carregado.
- **Entradas:** `$_POST['csrf_token']`, `$_SERVER['HTTP_X_CSRF_TOKEN']`, corpo `php://input` (JSON com chave `csrf_token`), `$_SERVER['HTTP_REFERER']`, `$_SERVER['HTTP_HOST']`, `$_SERVER['SCRIPT_FILENAME']`.
- **Saídas:** campo `<input type="hidden" name="csrf_token">`; `<script>` do wrapper; em recusa: HTTP 403 com HTML amigável (link “Abrir a página de novo” só se o Referer for do mesmo host, e link para `index.php` relativo) ou JSON `{"error": "Sua sessão expirou ou a página está desatualizada. Recarregue a página e tente novamente."}`.
- **Banco:** nenhum.
- **Permissões e validações:** comparação `hash_equals`; exige que o token da sessão exista e que o enviado seja string.
- **Erros:** token ausente/errado → log `csrf.recusado` (nível `aviso`, dados `{ajax: bool}`) em `seguranca.log` se o logger estiver carregado → 403 → `exit`.
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz / efeitos | Quem chama |
|---|---|---|---|---|---|
| `csrf_token` | 28 | — | `string` | Cria `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))` se vazio/não string; devolve. Token fixo por sessão (não rotaciona por requisição). | `csrf_field`, `csrf_js` |
| `csrf_field` | 36 | — | `string` HTML | `<input type="hidden" name="csrf_token" value="…">` escapado. | todos os formulários do módulo |
| `csrf_verify` | 48 | — | `bool` | Procura em ordem: `$_POST['csrf_token']` → `HTTP_X_CSRF_TOKEN` → JSON de `php://input`. `hash_equals` contra a sessão. | `csrf_require` |
| `csrf_require` | 68 | `bool $ajax=false` | `void` (ou `exit`) | Se inválido: log, 403, HTML ou JSON, `exit`. | processar_login, recuperar_senha, verificacoes, excluir_conta, AJAX (ver acima) |
| `_csrf_url_inicio` | 106 | — | `string` | Calcula `../`×nível + `index.php` a partir de `SCRIPT_FILENAME`; senão `/`. | `csrf_require` |
| `_csrf_url_voltar` | 118 | — | `?string` | Devolve o Referer apenas se http(s) e mesmo `HTTP_HOST` (usado só como link, nunca redirect). | `csrf_require` |
| `csrf_js` | 133 | — | `string` `<script>` | Substitui `window.fetch`: para URL de mesma origem e método ≠ GET/HEAD, faz `o.headers = Object.assign({}, o.headers, {'X-CSRF-Token': t})`. | 7 páginas listadas acima |

- **Riscos / limitações:** [C] o wrapper só cobre `fetch`; `XMLHttpRequest`/forms não são tocados (forms usam `csrf_field`). [C] `Object.assign({}, o.headers)` perde os cabeçalhos se o chamador passar um objeto `Headers` (não enumerável), e `new URL(u)` com um objeto `Request` cai no `catch` e segue sem token; grep não encontrou `new Headers`/`new Request` no projeto, então hoje não ocorre. [C] O token é o mesmo durante toda a sessão e não é regenerado no login (`jc_session_regenerate` mantém os dados, logo mantém o token).

### `includes/auth.php`
- **Tipo:** módulo auxiliar (include), PHP.
- **Responsabilidade:** define `verificar_autenticacao($tipo)` que redireciona visitantes para o login e usuários do tipo errado para o próprio painel; carrega o logger e define um `registrar_log` de fallback.
- **Quando executa / quem usa:** incluído apenas por `verificacoes/escolher_verificacao.php:4` e `verificacoes/verificar_telefone.php:4`. **`verificar_autenticacao` não é chamada em nenhum arquivo** (grep `verificar_autenticacao` só encontra a definição e um comentário em `jovem/perfil.php:14`). Os dois includers fazem a checagem de sessão inline.
- **Depende de:** `includes/logger.php` (se existir).
- **Entradas:** `$_SESSION['empresa_id']`, `$_SESSION['jovem_id']`, `$_SERVER['REQUEST_URI']`.
- **Saídas:** redirecionamentos `../index.php` (tipo inválido), `../{tipo}/login.php` (sem sessão, grava `erro_login = 'Faça login para acessar esta página.'`), `../dashboard/jovem.php` ou `../dashboard/empresa.php` (tipo errado).
- **Banco:** nenhum.
- **Erros:** registra via `registrar_log` (legado) com níveis `ERROR`/`WARNING`; a mensagem inclui `REQUEST_URI` e o ID interno.
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz / efeitos | Quem chama |
|---|---|---|---|---|---|
| `registrar_log` (fallback) | 32 | `string $mensagem, string $nivel='INFO'` | `void` | `error_log("[JovemConnect][nivel] msg")`. Só é definido se logger.php não existir/definir antes. | ninguém na prática (logger.php sempre existe) |
| `verificar_autenticacao` | 43 | `string $tipo` (`'empresa'`/`'jovem'`) | `void` (ou `exit`) | Descrito acima. Redirecionamentos relativos `../` (presume página 1 nível abaixo da raiz). | **ninguém** |

- **Riscos / limitações:** [C] código legado/inativo. [C] Inicia sessão com `session_start()` cru se ainda não houver (linha 20). [C] Ao ser incluído em `verificar_telefone.php`/`escolher_verificacao.php` não tem efeito além de garantir `registrar_log` (já definido por config.php → logger.php).

### `includes/config.php`
- **Tipo:** configuração (include), PHP.
- **Responsabilidade:** carrega logger e `.env`, instala o tratador global de exceções e abre a conexão `mysqli` única (`$conn`) com charset utf8mb4.
- **Quando executa / quem usa:** incluído por ~30 arquivos (todas as páginas com banco: `jovem/*`, `empresa/*`, `Vagas/*`, `dashboard/jovem.php`, `dashboard/empresa.php`, `candidaturas/minhas.php`, `uploads/*`, `IA/avaliar_ajax.php`, `recuperar_senha.php`, `verificacoes/*`). Observação: o comentário de `empresa/login.php` diz “Sem include de config.php”, e o grep o lista apenas por causa desse comentário — [C] a página não inclui config.php.
- **Depende de:** `includes/logger.php`, `includes/env.php`, MariaDB/MySQL.
- **Modo de erro do mysqli (v4.58) [C]:** chama `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` antes de conectar. É o padrão do PHP 8.1+, e o código conta com ele (duplicidades, transações, tratador global); no PHP 7.4/8.0 o padrão era só devolver `false`, então agora o modo é o mesmo em qualquer versão.
- **Entradas:** variáveis de ambiente `DB_HOST` (padrão `localhost`), `DB_USER` (padrão `root`), `DB_PASS` (padrão vazio), `DB_NAME` (padrão `jovemconnect`), `DB_CHARSET` (padrão `utf8mb4`), definidas como constantes com `define()` (somente se `DB_HOST` ainda não definido).
- **Saídas:** variável global `$conn`; em falha de conexão: HTTP 503 + texto puro “O sistema está temporariamente indisponível…” (`die`).
- **Banco:** só conecta.
- **Erros:**
  - Conexão: `mysqli_sql_exception` (PHP ≥ 8.1) → `registrar_log('Falha na conexao… (codigo N)', 'ERROR')` **e** `jc_log('banco.indisponivel','erro',['errno'=>…])` → 503 → `die`. Caminho antigo (`connect_error`) loga a mensagem completa do MySQL via `registrar_log`.
  - **Tratador global** `jc_excecao_nao_tratada` (registrado com `set_exception_handler`): chama `jc_log_excecao($e)` (tipo, arquivo:linha, errno/sqlstate — sem SQL); decide JSON se `X-Requested-With: XMLHttpRequest` ou se algum cabeçalho já enviado contém `application/json`; responde 500 (se cabeçalhos ainda não enviados) com JSON `{"error":"Erro interno. Tente novamente em instantes."}` ou HTML “Ocorreu um erro inesperado… Voltar · Ir para a página inicial” (index.php relativo ao script).
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz / efeitos | Quem chama |
|---|---|---|---|---|---|
| `registrar_log` (fallback) | 15 | `string, string='INFO'` | `void` | `error_log` — só se logger.php não existir. | ninguém na prática |
| `jc_excecao_nao_tratada` | 28 | `Throwable $e` | `void` | Ver acima. | PHP (exceção não capturada) |

- **Riscos / limitações:** [C] só trata exceções; não há `set_error_handler`, então warnings/notices seguem a configuração `display_errors` do servidor. [C] A falha de conexão responde texto puro mesmo para endpoints AJAX. [C] Comentário (linha 25) cita `logs/sistema.log`, mas o logger grava `app.log`/`seguranca.log`. [I] No ramo `connect_error` a mensagem crua do MySQL (pode conter o usuário do banco) vai ao log; em PHP 8.1+ esse ramo não é alcançado porque o construtor lança exceção.

### `includes/env.php`
- **Tipo:** módulo auxiliar (include), PHP.
- **Responsabilidade:** carregador mínimo do arquivo `.env` da raiz para `putenv()`/`$_ENV`, sem sobrescrever variáveis já existentes no ambiente. Desde a v4.58 inclui `includes/compat.php` na primeira linha (usa `str_contains`).
- **Quando executa / quem usa:** `includes/config.php:70`, `includes/mailer.php:17`, `includes/sms.php:16`, `IA/assistente_ajax.php`. Chama `carregarEnv()` ao ser incluído. **`includes/logger.php` não o inclui** (só o menciona em comentário).
- **Depende de:** arquivo `.env` (não existe na cópia; há `.env.example` com as chaves `DB_*`, `SMTP_*`, `TWILIO_*`, `GEMINI_*`, `JC_LOG_*`).
- **Entradas:** linhas `CHAVE=valor`; ignora vazias, `#` e linhas sem `=`; remove aspas simples/duplas envolventes.
- **Saídas:** variáveis de ambiente do processo.
- **Erros:** arquivo ausente/ilegível → retorna silenciosamente (todas as configs caem nos padrões).
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `carregarEnv` | 14 | — | `void` | Idempotente (`static $carregado`). `putenv` + `$_ENV` apenas se `getenv($chave) === false`. | o próprio env.php ao ser incluído |

- **Riscos / limitações:** [C] não trata `export`, comentários no fim da linha nem escapes. [I] **Não há `.htaccess` na raiz** (únicos: `logs/.htaccess` e `uploads/curriculos/.htaccess`); se a raiz do projeto estiver dentro do DocumentRoot do Apache, `/.env` pode ser servido como texto, a depender da configuração do servidor (o padrão do Apache bloqueia só `.ht*`).


### `includes/compat.php` (novo na v4.58)
- **Tipo:** módulo auxiliar (include), PHP.
- **Responsabilidade:** deixar o projeto rodar num XAMPP com **PHP 7.4 ou 8.0** (por exemplo, os computadores da escola). Define `str_contains`, `str_starts_with` e `str_ends_with` (funções do PHP 8.0) **só se não existirem** (`function_exists`); no PHP 8.0+ nada é definido e as nativas continuam valendo. Os parâmetros não têm tipo de propósito: as nativas aceitam qualquer escalar (inclusive `null`), e uma função do usuário com `string $x` daria `TypeError` com `null` no 7.4.
- **Quem usa [C]:** `require_once` na primeira linha de todo arquivo que chama essas funções: `includes/env.php`, `logger.php`, `match.php`, `validacao.php`, `sms.php`, `assistente.php`, `moderacao.php`, `includes/admin/servicos.php`, `autorizacao.php`, `layout.php` e `verificacoes/validacoes_nomes.php`.
- **Testes [T]:** as três funções foram comparadas com as nativas do PHP 8.3 em 36 casos (vazios, `null`, números, acentos, maiúsculas): nenhuma diferença.
- **O que mais mudou para o PHP 7.4 (v4.58) [C]:** as 4 expressões `match` (`admin/conta.php`, `admin/vaga.php`, `includes/admin/servicos.php`, `includes/admin/layout.php`) viraram tabela/`switch` com o mesmo resultado; em `functions.php`, `chamarFlask` perdeu o tipo de retorno `array|false` e o `catch (Exception)` ganhou variável. Uma varredura pelo tokenizer do PHP não encontrou mais nenhum recurso exclusivo do PHP 8.0+ no código (`match`, tipos de união, `?->`, argumentos nomeados, `throw` como expressão, vírgula final em parâmetros, atributos, `enum`, `readonly`); todas as funções e constantes nativas usadas existem no 7.4.
- **Limite:** não havia PHP 7.4 nem 8.0 para executar o site; a compatibilidade foi verificada por análise do código e pela regressão completa no PHP 8.3. Diferenças de comportamento entre versões (comparação de texto com número no 7.4, por exemplo) não foram exercitadas.

### `includes/logger.php`
- **Tipo:** módulo auxiliar (include), PHP.
- **Responsabilidade:** registro estruturado de eventos (uma linha JSON por evento) em `app.log` (funcionamento) e `seguranca.log` (auditoria, com IP anonimizado), com redação de campos sensíveis, rotação diária/por tamanho e retenção. Mantém `registrar_log` (API antiga de texto livre).
- **Quando executa / quem usa:** incluído por `includes/config.php:10`, `includes/auth.php:25`, `logout.php:14`, `IA/assistente_ajax.php`. Por config.php, está disponível em todas as páginas com banco. `jc_log_seguranca` é usado neste módulo em: login (sucesso/falha), recuperação de senha, verificação e-mail/telefone, exclusão de conta, logout, CSRF recusado.
- **Depende de:** sistema de arquivos; variáveis `JC_LOG_DIR` (padrão `<raiz>/logs`), `JC_LOG_MAX_MB` (padrão 2), `JC_LOG_DIAS` (padrão 30) lidas via `getenv` no momento da gravação.
- **Entradas:** `$_SERVER['HTTP_X_REQUEST_ID']` (aceito se casar `^[a-f0-9]{16,32}$`), `$_SERVER['REMOTE_ADDR']`, `$_SERVER['SCRIPT_NAME']`, `$_SESSION['jovem_id'|'empresa_id']`.
- **Saídas:** arquivos `<JC_LOG_DIR>/app.log`, `<JC_LOG_DIR>/seguranca.log`, rotacionados para `app-AAAAMMDD-HHMMSS.log` / `seguranca-AAAAMMDD-HHMMSS.log`. Formato de linha: `{"ts": ISO-8601, "nivel": debug|info|aviso|erro, "componente":"php", "evento": "area.acao", "req": id, "usuario": "jovem:N"|"empresa:N"|"visitante", "pagina": SCRIPT_NAME, ["ip"], ["dados"]}`.
- **Banco:** nenhum.
- **Proteções implementadas (confirmadas):**
  - **Campos proibidos** (`jc_log_campo_proibido`): qualquer chave de `$dados` cujo nome em minúsculas *contenha* uma destas substrings vira `"[removido]"`: `senha, password, pass, token, csrf, cookie, sess, cpf, cnpj, telefone, phone, email, e-mail, cep, endereco, logradouro, numero, chave, key, secret, segredo, codigo, curriculo, mensagem, resposta, prompt, nascimento, authorization`.
  - **Limpeza de valores** (`jc_log_limpar`): remove caracteres de controle (impede injeção de linhas), troca padrões de e-mail por `[e-mail]` e sequências de ≥ 10 caracteres de dígitos/`.-/ ` por `[número]`, corta em 300 caracteres; arrays: no máximo 30 itens e 4 níveis (depois `[...]`).
  - **IP anonimizado** (`jc_log_ip`), só em `seguranca.log`: IPv4 → último octeto `0`; IPv6 → 3 primeiros grupos + `::`; inválido → `-`. Usa apenas `REMOTE_ADDR` (não lê `X-Forwarded-For`).
  - **Exceções** (`jc_log_excecao`): `mysqli_sql_exception` → só `errno` e `sqlstate`; demais → mensagem com trechos entre aspas trocados por `?`.
  - **Rotação/retenção** (`jc_log_rotacionar`, chamado antes de cada gravação): rotaciona se o arquivo atual ≥ `JC_LOG_MAX_MB` MB **ou** se a data do primeiro evento (lida dos 20 primeiros bytes) ≠ hoje. Só no momento da rotação percorre `base-*.log` (ordem decrescente de nome) e apaga os de índice ≥ 60 **ou** com `filemtime` mais antigo que `JC_LOG_DIAS` dias.
  - **Falha de gravação**: nunca interrompe o site; vai ao `error_log` do PHP.
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz / efeitos | Quem chama |
|---|---|---|---|---|---|
| `jc_req_id` | 38 | — | `string` | ID da requisição (estático): reaproveita `X-Request-ID` válido ou gera 16 hex. | `jc_log`; (fora do módulo: chamadas ao Flask — [NC] neste módulo) |
| `jc_log_config` | 50 | `string $nome, string $padrao` | `string` | `getenv` com padrão para vazio/ausente. | `jc_log_dir`, `jc_log_rotacionar` |
| `jc_log_dir` | 56 | — | `string` | Pasta de logs; cria com `mkdir 0750` recursivo se faltar. | `jc_log` |
| `jc_log_campo_proibido` | 65 | `string $campo` | `bool` | Lista acima (substring, `strtolower`). | `jc_log_limpar` |
| `jc_log_limpar` | 79 | `mixed $valor, int $nivel=0` | `mixed` | Limpeza recursiva acima. | `jc_log` |
| `jc_log_usuario` | 101 | — | `string` | `jovem:<id>` / `empresa:<id>` / `visitante` (prioriza jovem). | `jc_log` |
| `jc_log_ip` | 115 | — | `string` | IP anonimizado. | `jc_log` (só seguranca) |
| `jc_log_rotacionar` | 127 | `string $arquivo` | `void` | Rotação + retenção. | `jc_log` |
| `jc_log` | 158 | `string $evento, string $nivel='info', array $dados=[], string $arquivo='app'` | `void` | Monta e grava a linha (`FILE_APPEND|LOCK_EX`); nome do evento filtrado para `[a-z0-9_.-]`; nível inválido → `info`. Captura `Throwable`. | todo o projeto |
| `jc_log_seguranca` | 187 | `string $evento, string $nivel='info', array $dados=[]` | `void` | `jc_log(..., 'seguranca')`. | login, logout, recuperação, verificação, exclusão, CSRF |
| `jc_log_excecao` | 195 | `Throwable $e, string $evento='erro.inesperado'` | `void` | Ver acima; nível `erro`, em `app.log`. | `jc_excecao_nao_tratada` (config.php) |
| `registrar_log` | 211 | `mixed $mensagem, mixed $nivel='INFO'` | `void` | Legado: mapeia `ERROR→erro`, `WARNING/WARN→aviso`, `DEBUG→debug`, demais `info`; grava evento `legado` com `dados.texto` (passa pela limpeza, mas a chave `texto` não é proibida). | `config.php` (falha de conexão), `auth.php` (inativo) e outros arquivos fora do módulo |

- **Riscos / limitações:**
  - [C] O comentário de `jc_log_campo_proibido` diz “comparação sem maiúsculas/acentos”, mas só há `strtolower` — uma chave `endereço` (com cedilha) não casa com `endereco`.
  - [C] A regra de “`[número]`” também apaga datas/horas em textos livres (ex.: `2026-10-10` tem 10 caracteres).
  - [C] Retenção só roda quando há rotação (o cabeçalho diz “na próxima gravação”), e o arquivo rotacionado recebe o carimbo de data/hora da rotação, não do conteúdo.
  - [C] O logger não inclui env.php: em `logout.php` (que inclui logger.php sem config.php/env.php), `JC_LOG_DIR`/`JC_LOG_MAX_MB`/`JC_LOG_DIAS` definidos apenas no `.env` são ignorados — o evento `logout` vai para `<raiz>/logs` mesmo que o `.env` aponte outra pasta.
  - [C] `X-Request-ID` vem do cliente (validado como hex) — um cliente pode escolher o ID de correlação.
  - [C] Para IPv6 curto (ex. `::1`), o resultado é `::1::` (cosmético).

### `includes/mailer.php`
- **Tipo:** módulo auxiliar (include), PHP.
- **Responsabilidade:** envia o e-mail com o código de 6 dígitos via SMTP usando PHPMailer.
- **Quando executa / quem usa:** `recuperar_senha.php:4` e `verificacoes/verificar_email.php:4`.
- **Depende de:** `includes/env.php`; `vendor/autoload.php` do Composer (`phpmailer/phpmailer ^6.9` em `composer.json`); servidor SMTP.
- **Comportamento com/sem `vendor/` (confirmado):** o autoload é **opcional** — `if (is_file(.../vendor/autoload.php)) require_once` (linhas 19–23). **A pasta `vendor/` não existe na cópia.** Sem ela, a inclusão não falha; `enviarCodigoVerificacao()` detecta `!class_exists(PHPMailer)`, grava no `error_log` `"[mailer.php] PHPMailer não encontrado em vendor/. Rode \"composer install\"."` e retorna `false`.
- **Comportamento sem SMTP:** se `SMTP_HOST`, `SMTP_USER` ou `SMTP_PASS` estiver vazio → `error_log("Configuração de SMTP ausente ou incompleta…")` e `false`.
- **Entradas (env):** `SMTP_HOST`, `SMTP_PORT` (padrão 587), `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE` (`tls` padrão → STARTTLS; `ssl` → SMTPS; `none` → sem TLS e `SMTPAutoTLS=false`), `SMTP_FROM_EMAIL` (padrão = SMTP_USER), `SMTP_FROM_NAME` (padrão `JovemConnect`).
- **Saídas:** e-mail HTML + AltBody, assunto “Seu código de verificação — JovemConnect”, texto “Esse código expira em 15 minutos”; timeout 10 s.
- **Erros:** `PHPMailer\Exception` → `error_log('[mailer.php] Falha ao enviar e-mail: ' . ErrorInfo)` → `false`.
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `enviarCodigoVerificacao` | 27 | `string $email, string $nome, string $codigo` | `bool` | Ver acima. Nome e código escapados com `htmlspecialchars` no corpo. | `recuperar_senha.php` (etapa 1, retorno ignorado), `verificar_email.php` → `gerarEEnviarCodigoEmail` |

- **Riscos / limitações:** [C] o mesmo texto (“concluir a verificação da sua conta”) é usado para recuperação de senha. [C] Falhas vão para o `error_log` do PHP, não para `app.log`.

### `includes/sms.php`
- **Tipo:** módulo auxiliar (include), PHP.
- **Responsabilidade:** normaliza telefone brasileiro para E.164 e envia o código por SMS via SDK do Twilio.
- **Quando executa / quem usa:** só `verificacoes/verificar_telefone.php:5`.
- **Depende de:** `includes/env.php`; `vendor/autoload.php` (`twilio/sdk ^8.3`); API Twilio.
- **Comportamento com/sem `vendor/` (confirmado):** mesmo padrão do mailer — autoload opcional (linhas 18–22). Sem `vendor/` (situação da cópia), `enviarCodigoSMS()` grava `"[sms.php] SDK do Twilio não encontrado em vendor/. Rode \"composer install\"."` no `error_log` e retorna `false`. `normalizarTelefoneE164()` funciona sem o SDK.
- **Comportamento sem Twilio:** `TWILIO_SID`, `TWILIO_AUTH_TOKEN` ou `TWILIO_FROM_NUMBER` vazios → `error_log` + `false`.
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `normalizarTelefoneE164` | 31 | `string $telefone` | `?string` | Remove não-dígitos; 12–13 dígitos começando com `55` → `+<dígitos>`; 10–11 dígitos → `+55<dígitos>`; senão `null`. Não valida DDD nem o 9º dígito. | `enviarCodigoSMS`, `verificar_telefone.php` (validação) |
| `enviarCodigoSMS` | 54 | `string $telefone, string $codigo` | `bool` | Checa SDK, credenciais, normaliza; `Client->messages->create($para, ['from'=>…,'body'=>"Seu código de verificação JovemConnect: {codigo} (expira em 15 minutos)."])`. `TwilioException` → `error_log` + `false`. | `verificar_telefone.php` |

- **Riscos / limitações:** [C] só captura `TwilioException`; outra exceção iria ao tratador global (500). [C] A normalização remove letras/símbolos, então entradas com lixo textual passam na validação de `verificar_telefone.php` e são gravadas como digitadas.

### `jovem/login.php`
- **Tipo:** página GET (formulário), PHP + HTML + JS.
- **Responsabilidade:** tela de login com abas “Sou Jovem” (ativa por padrão) e “Sou Empresa”; a aba muda o `action` do formulário entre `processar_login.php` (jovem) e `../empresa/processar_login.php`.
- **Quando executa / quem usa:** links/redirecionamentos de muitos arquivos (`jovem/processar_login.php`, `processar_login.php` raiz, `recuperar_senha.php`, `includes/header.php`, `index.php`, `Vagas/*`, etc.).
- **Depende de:** `includes/csrf.php`, `includes/assistente.php` (FABs, acessibilidade e o tratador de `data-envio-unico`).
- **Entradas:** `$_SESSION['jovem_id']`, `['empresa_id']`, `['erro_login']`, `['sucesso_login']`; `localStorage['jc-theme']`.
- **Saídas:** HTML; se já logado → `Location: ../dashboard/jovem.php` ou `../dashboard/empresa.php`.
- **Banco:** nenhum.
- **Interface:** link “Voltar para a página inicial”; alerta `#erroLogin` (role=alert) e alerta de sucesso (role=status), ambos consumidos (`unset`) ao exibir; abas `#tabJovem`/`#tabEmpresa` (`onclick="switchProfile(...)"`); formulário `#loginForm` `POST` `action="processar_login.php"`, `novalidate`, `data-envio-unico="Entrando..."`, campos `tipo_usuario` (hidden, `jovem`), `csrf_token`, `email` (type=email), `senha`; link `#linkRecuperar` → `../recuperar_senha.php`; rodapé `#footerText`/`#footerLink` (cadastro). JS: aplica tema salvo; foca `#email` se houver erro.
- **Funções JS:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `switchProfile` | 159 | `type` (`'jovem'`/`'empresa'`) | — | Alterna classes/aria das abas, `tipo_usuario`, placeholder, `form.action`, textos/links do rodapé. | `onclick` das abas |

- **Riscos / observações:** [C] o campo `tipo_usuario` é enviado mas **nenhum processar_login o lê** — o tipo é decidido pelo arquivo de destino. [C] CSS: `assets/` minúsculo desde a v4.59 (antes `Assets/`, que no Linux deixava a página sem CSS).

### `empresa/login.php`
- **Tipo:** página GET (formulário), PHP + HTML + JS.
- **Responsabilidade:** igual a `jovem/login.php`, com a aba “Sou Empresa” ativa; o formulário aponta para `processar_login.php` (empresa) e a aba jovem troca para `../jovem/processar_login.php`.
- **Quando executa / quem usa:** `empresa/processar_login.php`, `empresa/cadastro.php`, `empresa/perfil.php`, `empresa/excluir_conta.php`, `Vagas/*` (redirecionamentos de empresa), `recuperar_senha.php` (após redefinir senha de empresa).
- **Depende de / Entradas / Saídas / Interface:** idênticos a `jovem/login.php` (redireciona primeiro `empresa_id` → `../dashboard/empresa.php`, depois `jovem_id`). Diferenças: `tipo_usuario=empresa`, placeholder `rh@empresa.com.br`, rodapé “Registre sua Empresa”, e `DOMContentLoaded → switchProfile('empresa')`.
- **Funções JS:** `switchProfile(type)` (linha ~169), mesma lógica com os caminhos invertidos.
- **Riscos / observações:** [C] CSS: `assets/` minúsculo desde a v4.59 (antes `Assets/`, que no Linux deixava a página sem CSS). [C] Comentário do cabeçalho diz “Página de login unificada (empresa + jovem)… Sem include de config.php” — correto.

### `processar_login.php` (raiz)
- **Tipo:** ponto de entrada (GET/POST) legado, neutralizado.
- **Responsabilidade:** não autentica nada; só redireciona. O comentário explica que antes aceitava a própria hash ou senha em texto puro.
- **Quando executa / quem usa:** nenhum formulário aponta para ele (grep: só os próprios login.php mencionam `processar_login.php` relativo à sua pasta).
- **Depende de:** `includes/session.php`.
- **Saídas:** `Location: dashboard/jovem.php` (se `jovem_id`), `dashboard/empresa.php` (se `empresa_id`), senão `jovem/login.php`.
- **Banco / CSRF:** nenhum.
- **Riscos:** nenhum ativo; é código legado mantido como redirecionamento.

### `jovem/processar_login.php`
- **Tipo:** endpoint POST de formulário, PHP.
- **Responsabilidade:** autentica jovem por e-mail + senha (`password_verify`), grava a sessão, regenera o ID, atualiza `ultimo_login` e redireciona para o painel.
- **Quando executa / quem usa:** `#loginForm` de `jovem/login.php` (aba jovem) e de `empresa/login.php` (após `switchProfile('jovem')`).
- **Depende de:** `includes/csrf.php`, `includes/config.php` (→ logger, env).
- **Entradas:** `POST email` (`trim`, `FILTER_VALIDATE_EMAIL`), `POST senha` (não vazia), `POST csrf_token`. (`tipo_usuario` ignorado.)
- **Saídas:** GET → `Location: login.php`; campos inválidos → `erro_login="Preencha todos os campos corretamente."` + `login.php`; credenciais erradas → `erro_login="E-mail ou senha incorretos. Tente novamente."` + `login.php`; sucesso → `../dashboard/jovem.php`.
- **Banco:** `SELECT id, nome, senha FROM jovens WHERE email = ? LIMIT 1` (prepared); `UPDATE jovens SET ultimo_login = NOW() WHERE id = ?` (prepared).
- **Permissões e validações:** visitante; CSRF exigido (`csrf_require()` depois do include de config — ordem: método → config → CSRF).
- **Erros / logs:** `login.falha` (`aviso`, `{tipo:'jovem', motivo:'campos_invalidos'|'credenciais'}`), `login.sucesso` (`info`, `{tipo:'jovem'}`) em `seguranca.log`. E-mail inexistente e senha errada geram o mesmo evento e a mesma mensagem.
- **Riscos:** [C] sem limite de tentativas / bloqueio / atraso (grep por `tentativa|rate.limit|bloqueio|lockout` sem resultados). [C] Não remove `empresa_id` já presente na sessão: um POST direto com sessão de empresa ativa deixa as duas chaves (as telas de login redirecionam antes, mas o endpoint não checa). [C] Não exige `email_verificado`/`telefone_verificado` (verificação é opcional). [C] `password_needs_rehash` não é usado.

### `empresa/processar_login.php`
- **Tipo:** endpoint POST de formulário, PHP.
- **Responsabilidade:** espelho do anterior para empresas.
- **Quando executa / quem usa:** `#loginForm` de `empresa/login.php` e de `jovem/login.php` (aba empresa).
- **Entradas / validações:** idênticas (email, senha, csrf).
- **Saídas:** `login.php` (GET, inválido, credenciais erradas) ou `../dashboard/empresa.php`.
- **Banco:** `SELECT id, nome_empresa, senha FROM empresas WHERE email = ? LIMIT 1` (prepared). **Não atualiza nenhuma coluna de último login** (a tabela `empresas` não tem `ultimo_login` no schema).
- **Sessão:** `empresa_id = intval(id)`, `empresa_nome = nome_empresa`, `jc_session_regenerate()`.
- **Logs:** `login.falha`/`login.sucesso` com `tipo:'empresa'`.
- **Riscos:** os mesmos do login jovem. [C] Comentário das linhas 41–45 diz que “o usuário de teste (id=3) tem senha '123456' em texto puro”; no `schema.sql` o registro de empresa id=3 tem hash bcrypt (e o próprio schema diverge entre “123456” e “password” no comentário). Quem tem senha em texto puro no seed é o jovem de teste (id=5) — esse não consegue logar via `password_verify`.

### `logout.php`
- **Tipo:** ponto de entrada GET, PHP.
- **Responsabilidade:** registra o logout, esvazia e destrói a sessão, expira o cookie e volta à página inicial.
- **Quando executa / quem usa:** links `<a href="../logout.php">` em `includes/header.php:128`, `dashboard/jovem.php:118`, `dashboard/empresa.php:167`, `Vagas/candidatos.php`, `Vagas/listar.php`, `Vagas/minhas.php`, `Vagas/avaliar.php`, `Vagas/favoritos.php`.
- **Depende de:** `includes/logger.php` (opcional, `file_exists`). **Não** inclui session.php, csrf.php, config.php nem env.php.
- **Entradas:** sessão.
- **Saídas:** `Location: index.php`.
- **Fluxo de destruição:** `session_start()` cru → captura `$nome_usuario`, `$id_usuario`, `$tipo_usuario` → `jc_log_seguranca('logout','info',['tipo'=>…])` → `session_unset()` → `setcookie(session_name(), '', time()-42000, path, domain, secure, httponly)` → `session_destroy()`.
- **Banco:** nenhum.
- **Permissões:** nenhuma; sem CSRF (GET).
- **Riscos:** [C] logout por GET sem token (um link/imagem de outro site pode deslogar o usuário — impacto baixo). [C] `$nome_usuario` e `$id_usuario` são calculados e não usados (resto do log antigo). [C] Logger sem env.php → configs de log do `.env` ignoradas (ver logger). [C] `setcookie` sem `samesite` (cookie está sendo apagado, impacto nulo).

### `recuperar_senha.php`
- **Tipo:** página GET/POST de 3 etapas (máquina de estados na sessão), PHP + HTML.
- **Responsabilidade:** recuperação de senha por código de 6 dígitos enviado por e-mail: (1) informar e-mail, (2) validar código, (3) definir nova senha. Funciona para jovens e empresas.
- **Quando executa / quem usa:** link “Esqueceu a senha?” (`#linkRecuperar`) de `jovem/login.php` e `empresa/login.php`.
- **Depende de:** `includes/csrf.php`, `includes/config.php`, `includes/mailer.php` (SMTP + PHPMailer), `includes/assistente.php`.
- **Entradas:**
  - Etapa 1: `POST acao_solicitar_codigo`, `POST email` (`trim`, `FILTER_VALIDATE_EMAIL`), `csrf_token`.
  - Etapa 2: `POST acao_validar_codigo_recuperacao`, `POST codigo` (`trim`; HTML: `maxlength=6`, `pattern=[0-9]{6}`), `csrf_token`; sessão `recuperacao_tabela`, `recuperacao_email_raw`.
  - Etapa 3: `POST acao_definir_nova_senha`, `POST nova_senha` (≥ 8 bytes via `strlen`), `POST confirmar_senha` (igual), `csrf_token`; sessão `recuperacao_tabela`, `recuperacao_email_raw`, `recuperacao_codigo_ok`.
  - `GET reenviar` → limpa as 5 chaves de recuperação e redireciona para `recuperar_senha.php`.
  - Etapa exibida = `$_SESSION['recuperacao_etapa'] ?? 1`.
- **Onde ficam os códigos:** colunas `codigo_recuperacao VARCHAR(6)` e `expiracao_recuperacao DATETIME` de `jovens` **e** `empresas` (ALTER no fim do `schema.sql`), em texto puro. Código = `sprintf('%06d', random_int(0, 999999))`; expiração = agora + 15 min (horário do PHP, comparado como string `Y-m-d H:i:s`).
- **Banco:**
  - Etapa 1: `SELECT id, nome FROM jovens WHERE email=?`; se não achar, `SELECT id, nome_empresa AS nome FROM empresas WHERE email=?` (prepared). Se achou: `UPDATE {tabela} SET codigo_recuperacao=?, expiracao_recuperacao=? WHERE email=?` (prepared; nome da tabela vem de lista fixa).
  - Etapa 2: `SELECT codigo_recuperacao, expiracao_recuperacao FROM {tabela} WHERE email=?`.
  - Etapa 3: `SELECT expiracao_recuperacao FROM {tabela} WHERE email=?`; `UPDATE {tabela} SET senha=?, codigo_recuperacao=NULL, expiracao_recuperacao=NULL WHERE email=?` (hash `password_hash(PASSWORD_DEFAULT)`).
- **Saídas:**
  - Etapa 1 OK (conta existindo ou não): etapa 2, “Mandamos um código para `ga***l@dominio`”. E-mail inválido: “Por favor, digite um e-mail válido.”
  - Etapa 2 OK → etapa 3; falha → “Código incorreto ou expirado.” (mesma mensagem para conta inexistente/código errado/expirado).
  - Etapa 3: senha curta → “A nova senha precisa ter pelo menos 8 caracteres.”; diferentes → “As senhas não coincidem.”; sessão sem dados → limpa e `Location: recuperar_senha.php`; expiração vencida → limpa, “O código expirou. Solicite a recuperação novamente.”, etapa 1; sucesso → `sucesso_login="Senha redefinida com sucesso! Faça login com sua nova senha."` e `Location: jovem/login.php` ou `empresa/login.php`.
- **Logs:** `senha.recuperacao_solicitada` (`{conta_encontrada: bool}`), `senha.codigo_recusado` (aviso), `senha.redefinida` (`{tipo}`) em `seguranca.log`.
- **Interface:** card único; alerta `.alert-error`; Etapa 1: form `POST recuperar_senha.php` `data-envio-unico="Enviando..."`, `#recEmail`; Etapa 2: form `data-envio-unico="Confirmando..."`, `#recCodigo`, botão-link “Não recebi meu e-mail” → `?reenviar=1` (volta à etapa 1, não reenvia sozinho); Etapa 3: `#recNovaSenha`, `#recConfirmarSenha` (`minlength=8`); “Voltar para o login” sempre → `jovem/login.php`. CSS inline com variáveis do `style.css`; tema via `localStorage`.
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `mascararEmail` | 11 | `string $email` | `string` | Usuário ≤ 2 chars → 1º char + `***`; senão 2 primeiros + `***` + último; mantém o domínio. | etapa 1 |

- **Riscos:** ver os achados em [SEGURANCA_E_PRIVACIDADE.md](SEGURANCA_E_PRIVACIDADE.md) e [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md) (**falha crítica de tomada de conta via `recuperacao_codigo_ok` não resetado**, sem limite de tentativas, código em texto puro, retorno do e-mail ignorado, e-mail duplicado entre tabelas).

### `verificacoes/escolher_verificacao.php`
- **Tipo:** página GET, PHP + HTML.
- **Responsabilidade:** após o cadastro, oferece verificar a conta por e-mail ou SMS (ou “verificar depois”). Se a conta já tem e-mail **ou** telefone verificado, vai direto ao painel.
- **Quando executa / quem usa:** redirecionamento de `jovem/cadastro.php:206` e `empresa/cadastro.php:149` logo após o login automático; links “Escolher outra forma de verificação” em `verificar_email.php` e `verificar_telefone.php`.
- **Depende de:** `csrf.php`, `config.php`, `auth.php` (sem uso efetivo), `header.php`, `footer.php` (→ `assistente.php`).
- **Entradas:** `$_SESSION['jovem_id'] ?? $_SESSION['empresa_id']` (jovem tem prioridade).
- **Saídas:** sem sessão → `../index.php`; já verificado → `../dashboard/index.php`; senão HTML com dois cards (`verificar_email.php` mostrando o e-mail da conta escapado; `verificar_telefone.php`) e link `../dashboard/index.php`.
- **Banco:** `SELECT email, telefone, email_verificado, telefone_verificado FROM {jovens|empresas} WHERE id=?` (prepared).
- **Riscos:** [C] se o ID da sessão não existir mais no banco, `$user` é `null` e a página gera avisos e mostra e-mail vazio (sem tratamento).

### `verificacoes/verificar_email.php`
- **Tipo:** página GET/POST, PHP + HTML.
- **Responsabilidade:** verificação de e-mail com código de 6 dígitos: no primeiro GET envia automaticamente um código (se não houver um válido), aceita a validação e o reenvio por POST.
- **Quando executa / quem usa:** card de `escolher_verificacao.php`.
- **Depende de:** `csrf.php`, `config.php`, `mailer.php` (PHPMailer + SMTP), `header.php`, `footer.php`.
- **Entradas:** sessão (`jovem_id` ou `empresa_id`); `POST acao_verificar` + `POST codigo` (`trim`); `POST acao_reenviar`; `csrf_token` em ambos os POSTs.
- **Colunas usadas:** `email`, `nome` (jovens) / `nome_empresa AS nome` (empresas), `codigo_verificacao VARCHAR(6)`, `expiracao_codigo DATETIME`, `email_verificado TINYINT(1)`.
- **Banco:**
  - `gerarEEnviarCodigoEmail`: `UPDATE {tabela} SET codigo_verificacao=?, expiracao_codigo=? WHERE id=?`.
  - Verificar: `SELECT email, codigo_verificacao, expiracao_codigo, email_verificado … WHERE id=?`; sucesso → `UPDATE {tabela} SET email_verificado=1, codigo_verificacao=NULL, expiracao_codigo=NULL WHERE id=?`.
  - Reenviar / GET: `SELECT {colNome}, email[, codigo_verificacao, expiracao_codigo, email_verificado] … WHERE id=?`.
- **Saídas:** sem sessão → `../index.php`; já verificado → `../dashboard/index.php`; sucesso → `../dashboard/index.php?verified=1`; falha → “Código incorreto ou expirado. Verifique a caixa de entrada ou solicite o reenvio.”; envio OK → “Enviamos um código para …!” / “Novo código enviado para …!”; envio falhou → “Não foi possível enviar o e-mail agora…”.
- **Sem SMTP / sem vendor/:** `enviarCodigoVerificacao` retorna `false` → o código **é gravado no banco mesmo assim** (UPDATE antes do envio), log `verificacao.email.enviado` com nível `aviso` e `{ok:false}`, a tela mostra o erro de envio. Como na próxima visita (GET) existe um código válido por 15 min, não há novo envio automático nesse intervalo (só pelo botão de reenvio).
- **Logs:** `verificacao.email.enviado`, `verificacao.email.sucesso`, `verificacao.email.falha`.
- **Interface:** card com alertas (erro/sucesso); form de validação `POST verificar_email.php` (`acao_verificar`, campo `codigo` `maxlength=6`, `inputmode=numeric`, `data-envio-unico="Validando..."`); form de reenvio (`acao_reenviar`, `data-envio-unico="Enviando..."`); links para escolher outra forma e para o painel.
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `gerarEEnviarCodigoEmail` | 26 | `mysqli $conn, string $tabela, int $idUsuario, string $email, string $nome` | `bool` | Gera código (`random_int`), expiração +15 min, UPDATE, envia via `enviarCodigoVerificacao`, loga. | POST reenvio, GET inicial |

- **Riscos:** [C] mensagens de sucesso já recebem `htmlspecialchars($user['email'])` e são escapadas de novo na exibição (dupla codificação: e-mail com `&` aparece como `&amp;`). [C] Sem limite de tentativas de código nem de reenvios (cada GET sem código válido, ou cada POST de reenvio, dispara um e-mail).

### `verificacoes/verificar_telefone.php`
- **Tipo:** página GET/POST, PHP + HTML.
- **Responsabilidade:** verificação de telefone por SMS: o usuário informa/edita o celular, recebe o código e o valida.
- **Quando executa / quem usa:** card de `escolher_verificacao.php`.
- **Depende de:** `csrf.php`, `config.php`, `auth.php` (sem uso efetivo), `sms.php` (Twilio), `header.php`, `footer.php`.
- **Entradas:** sessão; `POST acao_enviar_sms` + `POST telefone` (`trim`, `normalizarTelefoneE164 !== null`); `POST acao_validar_codigo` + `POST codigo`; `csrf_token`.
- **Colunas usadas:** `telefone VARCHAR(20)`, `telefone_verificado TINYINT(1)`, `codigo_telefone VARCHAR(6)`, `expiracao_telefone DATETIME`.
- **Banco:**
  - Sempre: `SELECT telefone, telefone_verificado, codigo_telefone, expiracao_telefone FROM {tabela} WHERE id=?`.
  - Enviar: `UPDATE {tabela} SET telefone=?, codigo_telefone=?, expiracao_telefone=? WHERE id=?` (grava o telefone **como digitado**, antes de enviar).
  - Validar OK: `UPDATE {tabela} SET telefone_verificado=1, codigo_telefone=NULL, expiracao_telefone=NULL WHERE id=?`.
- **Saídas:** sem sessão → `../index.php`; já verificado → `../dashboard/index.php`; sucesso → `../dashboard/index.php?phone_verified=1`; mensagens “Digite um número de celular válido, com DDD…”, “Código SMS enviado com sucesso para …!”, “Falha ao enviar SMS. Verifique o número e tente novamente.”, “Código incorreto ou expirado. Tente novamente.”
- **Sem Twilio / sem vendor/:** `enviarCodigoSMS` → `false`; **o telefone e o código já foram gravados**; log `verificacao.telefone.enviado` `aviso` `{ok:false}`; tela mostra “Falha ao enviar SMS…”. Se `$user['telefone']` não estiver vazio, o formulário de validação aparece.
- **Logs:** `verificacao.telefone.enviado`, `verificacao.telefone.sucesso`, `verificacao.telefone.falha`.
- **Interface:** form 1 `POST` (`acao_enviar_sms`, `#telCelular` type=tel pré-preenchido, botão “Enviar Código SMS”/“Reenviar SMS”, `data-envio-unico="Enviando SMS..."`); form 2 (só se há telefone) `acao_validar_codigo`, `#telCodigo` `maxlength=6`; links de saída.
- **Funções:** nenhuma definida.
- **Riscos:** [C] troca o telefone da conta sem validar unicidade e mesmo se o SMS falhar; [I] entrada com mais de 20 caracteres (letras passam pela normalização) pode exceder `VARCHAR(20)` e, com modo estrito do MySQL, virar exceção → página 500 genérica. [C] Sem limite de envios (custo Twilio) nem de tentativas. [C] Dupla codificação na mensagem de sucesso (como no e-mail).

### `verificacoes/validacoes_nomes.php`
- **Tipo:** módulo auxiliar (biblioteca de funções), PHP.
- **Responsabilidade:** filtro de termos impróprios com normalização de leetspeak/acentos e validação de nome de jovem (≥ 2 palavras, só letras, cada parte ≥ 2 letras, sem 4+ repetições) e de empresa (letras, números, `' . & / -`, ≥ 2 caracteres).
- **Quando executa / quem usa:** **ninguém.** `grep` por `validacoes_nomes`, `validarNomeJovem`, `validarNomeEmpresa`, `contemPalavraImpropria`, `normalizarTexto` não encontra nenhum include nem chamada fora do próprio arquivo. O comentário da linha 2 diz `// includes/validacoes.php` (arquivo inexistente).
- **Banco / sessão / CSRF:** nenhum.
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `normalizarTexto` | 7 | `string $texto` | `string` | `mb_strtolower`, troca `0→o 1→i 3→e 4→a 5→s 7→t @→a`, remove acentos comuns. | `contemPalavraImpropria` |
| `contemPalavraImpropria` | 33 | `string $nome` | `bool` | `str_contains` contra lista fixa (inclui `admin`, `suporte`, `jovemconnect`, palavrões). | `validarNomeJovem`, `validarNomeEmpresa` |
| `validarNomeJovem` | 60 | `string $nome` | `array{valido:bool, erro?:string, nome_formatado?:string}` | Regras acima; devolve nome em Title Case. | ninguém |
| `validarNomeEmpresa` | 98 | `string $nome` | `array{valido, erro?, nome_formatado?}` | Regras acima. | ninguém |

- **Riscos / limitações:** [C] código morto. [C] Se fosse usado, a busca por substring geraria falsos positivos (ex.: `pau` dentro de “Paulo”/“Paula”, `puta` em “Computação”, `pinto` em sobrenome “Pinto”).

### `jovem/excluir_conta.php`
- **Tipo:** endpoint POST de formulário, PHP.
- **Responsabilidade:** exclui a conta do próprio jovem logado, apaga o PDF do currículo e encerra a sessão.
- **Quando executa / quem usa:** modal de `jovem/perfil.php:1126` (`<form method="POST" action="excluir_conta.php">` com `confirmar=1` e `csrf_field()`).
- **Depende de:** `csrf.php`, `config.php`.
- **Entradas:** sessão `jovem_id` (única fonte do ID); `POST confirmar === '1'`; `csrf_token`.
- **Saídas:** sem sessão → `login.php`; não-POST ou sem `confirmar` → `perfil.php`; sucesso → `../index.php?conta=excluida` (aviso tratado em `index.php:214`).
- **Banco:** `SELECT curriculo FROM jovens WHERE id=?`; `DELETE FROM jovens WHERE id=?` (prepared). Pelo schema, `ON DELETE CASCADE` remove `habilidades_jovem`, `formacao_jovem`, `experiencias_jovem`, `candidaturas` (→ `mensagens_chat`), `favoritos`, `avaliacoes_ia`, `freelances` (por `jovem_contratante_id`) e `propostas_freelance`.
- **Arquivos:** apaga `uploads/curriculos/curriculo_{id}.pdf` e `uploads/curriculos/basename(curriculo)` se existirem (`@unlink`).
- **Sessão:** `$_SESSION = []`, cookie expirado, `session_destroy()`.
- **Logs:** `conta.excluida` `{tipo:'jovem'}` (o campo `usuario` ainda mostra `jovem:<id>` porque o log ocorre antes de limpar a sessão).
- **Riscos:** [C] não pede a senha novamente (só sessão + CSRF + confirmação). [C] Não verifica se o `DELETE` afetou linhas.

### `empresa/excluir_conta.php`
- **Tipo:** endpoint POST de formulário, PHP.
- **Responsabilidade:** exclui a empresa logada (cascata em vagas e dependentes) e encerra a sessão.
- **Quando executa / quem usa:** modal de `empresa/perfil.php:452`.
- **Entradas / saídas / sessão / logs:** iguais ao do jovem (com `empresa_id`, `tipo:'empresa'`).
- **Banco:** `DELETE FROM empresas WHERE id=?` (prepared). Pelo schema, cascata em `vagas` (→ `requisitos_vaga`, `beneficios_vaga`, `candidaturas` → `mensagens_chat`, `favoritos`, `avaliacoes_ia`), `freelances` (`empresa_id`), `empresa_locais`.
- **Riscos:** [C] não apaga arquivos (não há upload de empresa no schema — [I] por grep de `logo|foto` sem resultado no schema). [C] Sem reautenticação.

### `dashboard/index.php`
- **Tipo:** ponto de entrada GET (roteador), PHP.
- **Responsabilidade:** encaminha cada usuário para o seu painel e repassa o aviso de verificação.
- **Quando executa / quem usa:** destino de `verificacoes/escolher_verificacao.php`, `verificar_email.php`, `verificar_telefone.php` (sucesso, “já verificado” e “verificar depois”).
- **Depende de:** `includes/session.php`.
- **Entradas:** `GET verified`, `GET phone_verified` (só presença); sessão.
- **Saídas:** `jovem.php[?verificado=email|telefone]` (prioridade para jovem), `empresa.php[?verificado=…]`, ou `../index.php`. O aviso é exibido por JS em `dashboard/jovem.php:392` e `dashboard/empresa.php:524` (`jcAviso`).
- **Banco / CSRF:** nenhum.

## Módulo B — Cadastro, perfis, locais de trabalho e currículo

Observações gerais válidas para todo o módulo **[C]**:
- O `includes/config.php` liga `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` (desde a v4.58; antes valia o padrão do PHP ≥ 8.1, que é o mesmo): o mysqli **lança `mysqli_sql_exception`** quando algo dá errado, em qualquer versão do PHP. O `config.php` registra `set_exception_handler('jc_excecao_nao_tratada')`: a exceção vai para o log (`jc_log_excecao`) e a pessoa vê uma página 500 genérica ("Ocorreu um erro inesperado…"), ou JSON `{"error": ...}` quando a requisição é AJAX. Por isso, todo teste `if (!$stmt->execute())` / `$ok = $stmt->execute()` que aparece adiante é, na prática, um caminho que nunca roda (ver os achados em [SEGURANCA_E_PRIVACIDADE.md](SEGURANCA_E_PRIVACIDADE.md) e [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md)).
- `includes/csrf.php` inicia a sessão endurecida (`session.php`) e oferece `csrf_field()`, `csrf_require()` (falha: HTTP 403, página "Não foi possível concluir", log `csrf.recusado`) e `csrf_js()`.
- O envio único (`data-envio-unico="Texto..."`) é implementado em `includes/assistente.php` (listener `submit` no `document`, linhas ~665-693). Ele trava o botão depois do primeiro envio válido e destrava no `pageshow` quando a página volta do cache do navegador.
- `jcIcone(nome)` (ícone em SVG/span) é definida em `includes/assistente.php` (~linha 611).
- Todas as páginas do módulo carregam `../assets/css/style.css` (`assets/` minúsculo desde a v4.59 (antes `Assets/`, que no Linux deixava a página sem CSS)).

### `jovem/cadastro.php`
- **Tipo:** ponto de entrada, página GET (formulário) + POST (o formulário envia para a própria página). PHP + HTML + JS inline.
- **Responsabilidade:** cria a conta do jovem num formulário único dividido em 4 etapas. Valida os dados no servidor, impede duplicidade de e-mail/CPF e grava, numa transação, o jovem + as habilidades + o aceite dos termos. Em seguida faz o login automático e manda para a escolha de verificação da conta.
- **Quando executa / quem usa:** links em `index.php:92` e `comecar.php:54` ("Sou Jovem"); `jovem/login.php:138,182` ("Cadastre-se agora"); `empresa/login.php:193` (alternância do rodapé).
- **Depende de:** `includes/csrf.php` (sessão + CSRF), `includes/config.php` (`$conn`, logger), `includes/formacao.php`, `includes/termos.php`, `includes/etapas.php`, `includes/institucional.php` (modais dos Termos/Privacidade via `data-jc-modal-abrir`), `includes/assistente.php` (envio único, `jcIcone`). Serviço externo: **ViaCEP**, chamado no navegador (`https://viacep.com.br/ws/{cep}/json/`).
- **Entradas (POST):**

| Campo | Etapa | Tratamento no servidor |
|---|---|---|
| `csrf_token` (via `csrf_field()`) | — | `csrf_require()` |
| `nome` | 1 | trim, `mb_substr(...,0,100)`; obrigatório |
| `cpf` | 1 | só dígitos; precisa ter 11; é regravado como `000.000.000-00` (sem checar o dígito verificador) |
| `data_nascimento` | 1 | obrigatório; regex `^\d{4}-\d{2}-\d{2}$`, `strtotime` válido e não pode ser futura |
| `email` | 1 | trim; obrigatório; `FILTER_VALIDATE_EMAIL`; no máximo 100 caracteres (não é cortado, é recusado) |
| `senha`, `confirmar_senha` | 1 | obrigatória, ≥ 8 bytes (`strlen`), as duas iguais |
| `formacao_nivel`, `formacao_ano`, `em_tecnico`, `curso_tecnico[_outro]`, `curso_superior[_outro]`, `curso_prof[_outro]`, `formacao_outro`, `escolaridade_status` | 2 | `jc_formacao_ler_post($_POST,$erros,'','escolaridade_status',true,true)` (formação obrigatória) |
| `clt` (checkbox) | 2 | `isset` → 1/0 |
| `escolaridade_tipo`, `curso_predefinido`, `curso_outro`, `formacao` | — | **formato antigo**: só é usado quando o POST não traz `formacao_nivel` (nenhum formulário atual envia esses campos) |
| `area_interesse` | 3 | trim, 100 caracteres (o `<select>` oferece 10 opções fixas, mas o servidor não confere se o valor está na lista) |
| `resumo_profissional` | 3 | trim, 1000 caracteres |
| `habilidades` | 3 | 1500 caracteres; separadas por `[,;\n]+`; cada uma cortada em 80; repetições removidas sem diferenciar maiúsculas; erro se passar de 15 |
| `cep` | 4 | só dígitos; se vier preenchido, precisa ter 8 |
| `cidade`, `estado` (hidden) | 4 | 100 / 2 caracteres (estado em maiúsculas; sem validação de formato) |
| `cidade_estado` | 4 | modo sem JS: se `cidade` chegou vazia, divide "Cidade - UF" pelo último `-` (a UF precisa casar com `^[A-Za-z]{2}$`) |
| `aceite_termos` | 4 | tem de ser exatamente `'1'` |

- **Saídas:** HTML. Com sucesso: `Location: ../verificacoes/escolher_verificacao.php`. Já logado como jovem: `../dashboard/jovem.php`; como empresa: `../dashboard/empresa.php`. Com erro: a mesma página com o resumo `#errosCadastro` (`role="alert"`) e mensagens junto de cada campo.
- **Banco:**
  - `SELECT id FROM jovens WHERE email = ? OR cpf = ? OR cpf = ? LIMIT 1` (prepared; CPF com máscara e CPF só com dígitos).
  - `INSERT INTO jovens (nome, email, senha, cpf, data_nascimento, cidade, estado, cep, formacao, escolaridade_status, escolaridade_tipo, escolaridade_curso, clt, area_interesse, resumo_profissional)` (prepared, tipos `ssssssssssssiss`). `perfil_publico` não é enviado, então fica com o DEFAULT **1** do schema.
  - `INSERT INTO habilidades_jovem (jovem_id, habilidade)` para cada habilidade (prepared).
  - `UPDATE jovens SET termos_versao=?, termos_aceitos_em=NOW()`, via `jc_termos_registrar` (só se as colunas existirem).
  - Tudo entre `begin_transaction()` e `commit()`; em `mysqli_sql_exception`, `rollback()`.
- **Permissões e validações:** só visitante (as duas sessões são redirecionadas). CSRF obrigatório. Senha guardada com `password_hash(PASSWORD_DEFAULT)`. Depois de criar a conta, `jc_session_regenerate()`.
- **Erros:**
  - Validação → `$erros['campo']`; os erros da formação entram com chave numérica.
  - Duplicidade (SELECT ou exceção **1062**) → `$erros['email']` = "E-mail ou CPF já cadastrado na plataforma…".
  - Outra exceção do banco → `jc_log_excecao($e,'cadastro.erro_banco')` + `$erros['geral']` = "Erro interno ao cadastrar…".
  - Toda falha registra `jc_log_seguranca('cadastro.falha','aviso',['tipo'=>'jovem','campos'=>[nomes]])`, só com os nomes dos campos. Sucesso: `cadastro.concluido` com o número de habilidades.
- **Interface:**
  - `form#formCadastroJovem` (POST para a própria página, `novalidate`, `data-envio-unico="Cadastrando..."`, `data-etapas`, `data-etapa-inicial`).
  - Etapa 1 "Informações básicas": nome, CPF (`data-digitos="11"`), data (`max=hoje`), e-mail, senha e confirmação (`data-igual-a="senha"`).
  - Etapa 2 "Formação": `jc_formacao_campos(id='cad')` + checkbox CLT.
  - Etapa 3 "Perfil profissional" (opcional): área (select), objetivo (textarea), habilidades (texto).
  - Etapa 4 "Localização e revisão": CEP (`data-digitos="8"`), "Cidade / UF", campos hidden `#hidCidade`/`#hidEstado`, caixa de revisão `[data-etapas-revisao]`, aceite (`required`, links que abrem os modais `modalTermos`/`modalPrivacidade`), botão "Criar minha conta" em `[data-etapas-fim]`.
  - JS da página: tema (`localStorage jc-theme`); foco em `#errosCadastro`; máscaras de CPF e CEP; no `blur` do CEP, consulta o ViaCEP (preenche `#cidadeEstado`, `#hidCidade`, `#hidEstado`; mensagens "CEP não encontrado…" e "Não foi possível buscar…" em `#cepStatusJovem`); idade calculada em `#idadeDisplay`; `separarCidadeUf()` no `change` e no `submit`.
- **Funções:**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `$err` (closure) | 225 | `string $campo` | string HTML | `jc_campo_erro($erros,$campo)` | template |
| `$aria` (closure) | 226 | `string $campo` | string | `jc_campo_aria` | template |
| `$val` (closure) | 227 | `string $campo` | string escapada | valor de `$campos` para repreencher o formulário | template |
| `separarCidadeUf()` (JS) | 543 | — | void | divide `#cidadeEstado` pelo último `-` e preenche os hidden `cidade`/`estado` (UF em maiúsculas, 2 caracteres) | `change` de `#cidadeEstado`, `submit` do formulário |
| handlers anônimos (JS) | 492-540 | eventos | — | máscaras, ViaCEP, idade | DOM |

- **Riscos / limitações:**
  - O CPF não tem o dígito verificador conferido; só a quantidade de dígitos. **[C]**
  - `area_interesse` não é conferida contra a lista. **[C]**
  - `estado` não tem formato validado (no cadastro da empresa tem). **[C]**
  - O campo `formacao` do formato antigo entra sem limite de tamanho (coluna de 100), mas nenhum formulário atual usa esse caminho. **[C]**
  - A etapa reaberta é a do **primeiro erro na ordem em que foi gravado**, não a de menor número. Os erros da formação são gravados antes dos da etapa 1, então, com erro nas duas, a página reabre na etapa 2. **[C]** (ordem das linhas 87-147 + `jc_etapa_do_erro`)

### `empresa/cadastro.php`
- **Tipo:** ponto de entrada GET/POST (envia para a própria página). PHP + HTML + JS.
- **Responsabilidade:** cria a conta da empresa em 4 etapas: identificação/acesso, endereço principal (sede), perfil opcional e revisão + aceite. Valida, impede duplicidade de e-mail/CNPJ, grava o endereço completo quando a migração de endereço existe e registra o aceite. Termina com login automático e redirecionamento para a verificação.
- **Quando executa / quem usa:** `index.php:93`, `comecar.php:55`, `empresa/login.php:148,209`, `jovem/login.php:197`.
- **Depende de:** `csrf.php`, `config.php`, `includes/endereco_empresa.php`, `includes/termos.php`, `includes/etapas.php`, `institucional.php`, `assistente.php`. Externos (no navegador): **BrasilAPI** `https://brasilapi.com.br/api/cnpj/v1/{cnpj}` e **ViaCEP**.
- **Entradas (POST):**

| Campo | Etapa | Tratamento |
|---|---|---|
| `cnpj` | 1 | precisa ter 14 dígitos; gravado como `00.000.000/0000-00` (`cnpj_formatado`); sem dígito verificador |
| `nome_empresa` | 1 | trim, 150 caracteres; obrigatório |
| `email` | 1 | obrigatório, `FILTER_VALIDATE_EMAIL`, ≤ 100 |
| `senha`, `confirmar_senha` | 1 | ≥ 8, as duas iguais |
| `cep`, `logradouro`, `numero`, `complemento`, `bairro` | 2 | `endereco_empresa_do_post()` (8/150/10/100/100); CEP, se informado, precisa ter 8 dígitos. Os campos só aparecem quando `empresa_tem_endereco()` |
| `cidade`, `estado` | 2 | 100 / 2 caracteres; `estado`, se informado, precisa casar com `^[A-Z]{2}$` |
| `area_atuacao` | 3 | 100 |
| `descricao` | 3 | 2000 |
| `site_oficial` | 3 | 255; validado com `FILTER_VALIDATE_URL` depois de acrescentar `https://` quando falta o esquema. **Grava o texto original** (sem o esquema) |
| `aceite_termos` | 4 | `'1'` obrigatório |

- **Saídas:** sucesso → `Location: ../verificacoes/escolher_verificacao.php`. Já logado → painel do respectivo tipo. Erro → a página de novo, aberta na etapa do primeiro erro.
- **Banco:**
  - `SELECT id FROM empresas WHERE email = ? OR cnpj = ? LIMIT 1` (prepared; CNPJ só no formato com máscara).
  - `INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, site_oficial, cep, logradouro, numero, complemento, bairro)` com a migração de endereço; sem ela, as 9 primeiras colunas.
  - `jc_termos_registrar($conn,'empresas',$id)`.
  - **Sem transação.**
- **Permissões:** só visitante; CSRF; `password_hash`; `jc_session_regenerate()`.
- **Erros:** iguais aos do jovem (1062 → `$erros['email']` "E-mail ou CNPJ já cadastrado…"; outros → log `cadastro.erro_banco` + `$erros['geral']`; logs `cadastro.falha` e `cadastro.concluido` com `tipo=empresa`). Diferença: não há rollback. Se o INSERT der certo e o UPDATE do aceite falhar, a conta fica criada, mas a pessoa vê "Erro interno" e não fica logada **[I]**, porque `jc_termos_registrar` roda depois do INSERT dentro do mesmo `try` (linhas 138-141).
- **Interface:**
  - `form#formEmpresa` (`novalidate`, `data-envio-unico="Registrando empresa..."`, `data-etapas`).
  - Etapa 1 "Identificação": CNPJ + botão `#btnBuscarCnpj`, nome, e-mail, senhas.
  - Etapa 2 "Endereço principal": CEP/rua/número/complemento/bairro (só com a migração) + cidade/UF (`data-uf`).
  - Etapa 3 "Perfil da empresa": área, sobre, site.
  - Etapa 4 "Revisão": resumo + aceite + "Registrar empresa".
  - JS: máscara do CNPJ. Clique em `#btnBuscarCnpj`: exige 14 dígitos, mostra o spinner, desabilita o botão, chama a BrasilAPI e então troca o nome pela `razao_social` (**sempre sobrescreve**). Cidade, UF e CEP só são preenchidos se estiverem vazios; com o CEP preenchido, chama `buscarCep()`. Mensagens em `#cnpjStatus` (HTTP diferente de 2xx → "CNPJ não encontrado…"; falha de rede → "Não foi possível consultar…").
  - `buscarCep()`: só consulta se o CEP tiver 8 dígitos e for diferente de `ultimoCepBuscado`. Preenche logradouro/bairro/cidade/estado (**sobrescreve**) e foca em `#numero`. Dispara no `input` (com 9 caracteres) e no `blur`.
- **Funções:**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `$err`, `$aria`, `$val` | 166-168 | `string` | string | iguais às do jovem | template |
| `buscarCep()` (JS, async) | 480 | — | Promise | ViaCEP → endereço | `input`/`blur` do `#cep`, busca do CNPJ |
| handler de `#btnBuscarCnpj` (JS) | 438 | clique | — | BrasilAPI | botão |

- **Riscos:**
  - A duplicidade de CNPJ só é conferida no formato com máscara. Contas antigas gravadas só com dígitos não seriam encontradas, e o UNIQUE `uq_cnpj` também não pegaria **[I]** (comparar com o tratamento duplo de CPF no jovem).
  - O nome digitado é trocado pela razão social sem perguntar. **[C]**

### `includes/etapas.php`
- **Tipo:** módulo auxiliar (include) com funções PHP e um componente JS.
- **Responsabilidade:** formulário em etapas sem perder dados. Um único `<form>`; cada `<section class="etapa" data-etapa data-titulo>` vira uma etapa. Fornece as mensagens de erro do servidor junto de cada campo, a etapa de reabertura e o script de navegação/validação no navegador.
- **Quem usa:** `jovem/cadastro.php` e `empresa/cadastro.php` (são os únicos).
- **Depende de:** nada no PHP; no JS, de `window.jcIcone` (opcional; se faltar, usa string vazia).
- **Funcionamento sem JavaScript [C]:** o script não roda, as `<section>` ficam todas visíveis (o atributo `hidden` só é aplicado pelo JS) e o botão de envio fica na etapa 4. Os campos `required` não bloqueiam o envio porque os formulários têm `novalidate`, então toda a validação fica no servidor. A formação mostra todos os sub-blocos graças a `<noscript><style>` (ver `formacao.php`). Cidade/UF do jovem chegam por `cidade_estado`.
- **Data-atributos interpretados pelo JS:**

| Atributo | Onde | Efeito |
|---|---|---|
| `form[data-etapas]` | form | ativa o componente (precisa de ≥ 2 etapas) |
| `data-etapa-inicial="N"` | form | etapa aberta ao carregar (de 1 até o total) |
| `section.etapa[data-etapa][data-titulo]` | seção | etapa; título no contador e na lista |
| `data-etapas-revisao` | div | recebe `<dl class="etapas-resumo">` ao entrar na última etapa |
| `data-revisao="Rótulo"` | campo | entra no resumo (select → texto da opção; checkbox → Sim/Não; vazio → "Não informado") |
| `data-etapas-fim` | div | a navegação é inserida antes dela |
| `required` | campo | vazio → `data-erro-vazio`, ou "Marque esta opção…" (checkbox) / "Preencha este campo." |
| `data-erro-vazio` | campo | mensagem para o campo obrigatório vazio |
| `type="email"` | campo | regex `^[^\s@]+@[^\s@]+\.[^\s@]+$` |
| `minlength` | campo | "Use pelo menos N caracteres." |
| `data-digitos="N"` + `data-erro-digitos` | campo | conta só os dígitos |
| `data-igual-a="id"` + `data-erro-igual` | campo | precisa ser igual ao outro campo |
| `data-uf` | campo | `^[A-Za-z]{2}$` |

- **Saídas:** HTML (`<p class="campo-erro" id="erro-{campo}" data-erro-servidor>`), atributos ARIA e `<script>` (impresso uma única vez por página).
- **Banco:** nenhum.
- **Funções:**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `jc_campo_erro` | 34 | `array $erros, string $campo` | string HTML | parágrafo com a mensagem escapada, ou `''` se não há erro / o valor não é string | os dois cadastros (`$err`) |
| `jc_campo_aria` | 44 | `array $erros, string $campo` | string | ` aria-invalid="true" aria-describedby="erro-{campo}"` | os dois cadastros (`$aria`) |
| `jc_etapa_do_erro` | 51 | `array $erros, array $mapa` | int | percorre as chaves na ordem de inserção; devolve `$mapa[campo]`, ou `$mapa['*']` para chave numérica; 1 por padrão | os dois cadastros |
| `jc_etapas_script` | 64 | — | string | `<script>` do componente (com `static $impresso`) | os dois cadastros |
| `visivel(el, secao)` (JS) | 76 | elemento, seção | bool | ignora campos desabilitados ou hidden e os que estão dentro de outro `[hidden]` que não seja a própria seção (por isso campos de sub-blocos escondidos da formação não são validados) | `campos`, `montarRevisao` |
| `mensagemDoCampo` (JS) | 82 | el | string/null | `data-erro-vazio` | `validar` |
| `valorDe` (JS) | 86 | el | string | valor (checkbox `'1'`/`''`; radio pelo grupo) | `validar` |
| `validar` (JS) | 95 | el | string | regras da tabela acima | vários |
| `lugarDaMensagem` (JS) | 112 | el | Element | onde inserir a mensagem | `mostrarErro` |
| `mostrarErro` (JS) | 115 | el, msg | void | cria/remove `#erro-js-{id}` e ajusta `aria-invalid`/`aria-describedby` | `validarEtapa`, `input` |
| `campos` (JS) | 137 | seção | Element[] | campos com `name` visíveis | `validarEtapa` |
| `iniciar` (JS) | 141 | form | void | monta o topo (contador "Etapa X de N", barra `role=progressbar`, lista `ol.etapas-lista`) e os botões Voltar/Continuar; registra `input` (apaga o erro quando corrigido, inclusive o do servidor), `keydown` (Enter avança em vez de enviar) e `submit` em captura (valida todas as etapas e abre a primeira inválida, com `stopImmediatePropagation`, que impede o envio único) | auto-init |
| `validarEtapa(i, focar)` (JS) | 195 | int, bool | bool | valida os campos da etapa | Continuar, Enter, submit |
| `montarRevisao` (JS) | 205 | — | void | resumo `dl` | `ir` (última etapa) |
| `ir(i, focar)` (JS) | 225 | int, bool | void | mostra a etapa, ajusta classes, `aria-current`, contador e barra; foca o título e rola até o topo (respeita `prefers-reduced-motion`) | navegação |

- **Riscos / limitações:**
  - A validação no navegador só conhece as regras acima. Ano do Ensino Médio e cursos (sem `required`) só são validados no servidor. **[C]**
  - O `required` do nível de formação é verificado. **[C]**

### `includes/termos.php`
- **Tipo:** include de configuração + funções.
- **Responsabilidade:** define a versão e a data dos Termos/Política (`JC_TERMOS_VERSAO='1.0-minuta'`, `JC_TERMOS_DATA='10/10/2026'`) e registra o aceite na conta recém-criada.
- **Quem usa:** `jovem/cadastro.php`, `empresa/cadastro.php` e `includes/documentos.php` (mostra "Versão … · atualizada em …", linha ~105).
- **Banco:** `SHOW COLUMNS FROM \`{tabela}\` LIKE 'termos_aceitos_em'` (o nome da tabela vem de lista fixa) e `UPDATE \`{tabela}\` SET termos_versao = ?, termos_aceitos_em = NOW() WHERE id = ?` (prepared).
- **Sem a migração `migrations/2026-10-10_stage6_aceite_termos.sql` [C]:** `jc_termos_colunas` retorna false, e `jc_termos_registrar` sai sem gravar nada e sem erro. O cadastro continua exigindo a caixa (a validação está nas páginas), só não fica registro de data/versão. Contas antigas ficam com NULL.
- **Funções:**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `jc_termos_colunas` | 24 | `mysqli $conn, string $tabela` | bool | só aceita `jovens`/`empresas`; cache estático por tabela | `jc_termos_registrar` |
| `jc_termos_registrar` | 37 | `mysqli, string $tabela, int $id` | void | UPDATE versão + NOW(); nada se `$id<=0` ou sem as colunas; exceção do mysqli sobe para quem chamou | os dois cadastros |

- **Observação:** o "aceite" registrado é o envio do formulário com `aceite_termos=1`. Nem o IP nem o texto aceito são guardados, só a versão. **[C]**

### `includes/formacao.php`
- **Tipo:** include (constantes, funções PHP, HTML/JS do componente).
- **Responsabilidade:** formação estruturada NÍVEL → CURSO/ANO → SITUAÇÃO, sem migração. Converte do formulário para as colunas existentes, reconhece os dados antigos e gera os campos.
- **Quem usa:** `jovem/cadastro.php` (`ler_post`, `campos`) e `jovem/perfil.php` (`ler_post`, `de_jovem`, `inalterado`, `resumo`, `detalhe`, `campos`).
- **Constantes:** `JC_FORMACAO_SEP=' — '` (travessão com espaços); `JC_FORMACAO_NIVEIS` (medio, tecnico, superior, profissionalizante, outro); `JC_FORMACAO_ANOS` (1-3); `JC_CURSOS_TECNICOS` (7: Administração, TI, Logística, Enfermagem, Mecânica, Contabilidade, Marketing; chave = valor gravado, rótulo de exibição distinto só para TI); `JC_CURSOS_SUPERIORES` (17); `JC_CURSOS_PROFISSIONALIZANTES` (7); `JC_FORMACAO_OUTRO_MAX=70`; `JC_FORMACAO_LIVRE_MAX=90`.
- **Formato novo × antigo [C]:**

| Coluna | Formato novo (gravado por `ler_post`) | Formato antigo |
|---|---|---|
| `jovens.formacao` (VARCHAR 100) | rótulo padronizado: `Ensino Médio — 3º ano`, `Ensino Técnico — {curso}`, `Ensino Superior — {curso}`, `Curso profissionalizante — {curso}`, ou o texto livre de "Outro" | texto livre qualquer (ex.: "Ensino Médio Completo") |
| `jovens.escolaridade_status` | `cursando`/`completo` (radio "Situação") | igual |
| `jovens.escolaridade_tipo` | `tecnico` se existe `curso_tecnico` (nível técnico, ou médio + "também faço curso técnico"); senão `regular` | checkbox / `escolaridade_tipo` |
| `jovens.escolaridade_curso` | nome do curso técnico, ou NULL | `curso_predefinido`/`curso_outro` (cadastro antigo), `escolaridade_curso` (perfil antigo) |
| `formacao_jovem.titulo` | rótulo padronizado (150) | texto livre `form_titulo` |
| `formacao_jovem.conclusao` (20) | `Cursando` / `Concluído`, com ` (AAAA)` opcional | texto livre (ex.: "Dezembro/2025") |
| `formacao_jovem.area` | curso (nos níveis técnico/superior/profissionalizante) | `form_area` livre |

  Nada é convertido no banco. Quando o perfil é editado, o texto antigo que não é reconhecido aparece em "Outro" com o aviso "Informado antes: …".
- **Funções:**

| Nome | Linha | Parâmetros | Retorno | O que faz / efeitos | Quem chama |
|---|---|---|---|---|---|
| `jc_formacao_cursos` | 80 | `string $nivel` | `?array [campo, lista, nome]` | lista de cursos do nível (`curso_tecnico`/`curso_superior`/`curso_prof`); null para medio/outro | `ler_post`, `de_jovem` |
| `jc_formacao_texto` | 91 | `mixed $valor, int $max` | string | troca caracteres de controle por espaço, normaliza espaços, trim e `mb_substr` | interna |
| `jc_formacao_rotulo` | 99 | `string $nivel, ?int $ano, string $curso, string $outro` | string | monta o rótulo padronizado | `ler_post` |
| `jc_formacao_ler_post` | 119 | `array $post, array &$erros, string $p='', string $campoSit='escolaridade_status', bool $obrigatorio=true, bool $comEmTecnico=true` | `?array` | null se não há `{$p}formacao_nivel`. Senão devolve `nivel, ano, curso, outro, em_tecnico, curso_tecnico, situacao, rotulo, tipo, valores`. Erros acrescentados a `$erros[]` (sem chave): "Selecione o nível de formação." (só se obrigatório), "Nível de formação inválido.", "Selecione o ano do Ensino Médio.", "Informe {nome} (campo "Qual?").", "Selecione {nome}.", "Descreva a sua formação em "Outro"." `rotulo` só é preenchido quando a formação está completa. Situação inválida vira `cursando` | cadastro jovem, perfil (`update_perfil`, `add_formacao`) |
| `jc_formacao_valores_vazios` | 204 | — | array | valores padrão para repopular (inclui `legado`) | várias |
| `jc_formacao_por_curso` | 212 | `array &$v, string $campo, array $lista, string $curso` | void | põe o curso no select; se não estiver na lista → `Outro` + `_outro` | `de_jovem` |
| `jc_formacao_de_jovem` | 226 | `array $j` (linha de `jovens`) | array (valores) | reconhece `^Ensino Médio — ([123])º ano$` e os prefixos técnico/superior/profissionalizante. Texto antigo: `legado`=texto; heurística "médio / Nº ano / série" → medio (ano pelo número; "complet/conclu" → 3º); `escolaridade_tipo=tecnico` → nível técnico; o resto → `outro` com o texto (90) | perfil (formulário e `inalterado`), `resumo` |
| `jc_formacao_inalterado` | 290 | `array $enviado, array $mostrado` | bool | compara o POST com o que o formulário mostrou; ignora listas que não pertencem ao nível mostrado. Se nada mudou, o perfil não regrava a formação (preserva o texto antigo) | perfil `update_perfil` |
| `jc_formacao_resumo` | 318 | `array $j` | string | formato novo: "{formacao} · Cursando/Concluído[ · Curso técnico: X]"; antigo: "Cursando • Curso técnico: X • {formacao}" | perfil (as duas visões) |
| `jc_formacao_detalhe` | 341 | `array $f` (formacao_jovem) | string HTML **já escapada** | "instituição • Cursando (2026) • Área: X" (a área só aparece se não estiver contida no título; `conclusao` antiga recebe o prefixo "Conclusão:") | perfil (as duas visões) |
| `jc_formacao_campos` | 370 | `array $o` (`id`, `nome`, `form`, `situacao`, `em_tecnico`, `obrigatorio`, `vazio`, `valores`) | string HTML | select de nível (`data-formacao-nivel`, `required` se obrigatório), bloco do médio (ano + checkbox `em_tecnico`), 3 blocos de curso (`data-formacao-se`, select `data-formacao-curso`, "Qual?" `data-formacao-outro-de`), bloco "Outro", fieldset da situação, aviso de legado; na 1ª chamada também `<noscript><style>` e `<script>` | cadastro (`cad`), perfil (`perf`, `add`) |
| `atualizar(bloco)` (JS) | 476 | Element | void | mostra/esconde os sub-blocos conforme o nível e "em técnico", e o "Qual?" conforme "Outro" | `change` delegado, init |
| `iniciar()` (JS) | 494 | — | void | aplica `atualizar` em todos os `[data-formacao]` | DOMContentLoaded |

- **Riscos:**
  - Rótulos com o curso em "Outro" (até 70) cabem em `jovens.formacao` (100): "Curso profissionalizante — " tem 27 caracteres. O texto livre de "Outro" (90) também cabe. **[C]**
  - `jovens.formacao` é descrita no schema como "Texto legado — usar formacao_jovem" (`schema.sql:76`), mas o código atual continua usando a coluna como formação principal (divergência de comentário). **[C]**

### `includes/endereco_empresa.php`
- **Tipo:** include de funções.
- **Responsabilidade:** endereço completo da empresa, que depende da migração aditiva `migrations/2026-10-06_stage3_endereco_empresa.sql`. Também formata o CNPJ.
- **Quem usa:** `empresa/cadastro.php`, `empresa/perfil.php`, `includes/locais.php` (que por sua vez é usado em `Vagas/criar.php`, `empresa/locais.php` e em `endereco_empresa_formatado` de `Vagas/criar.php`).
- **Banco:** `SHOW COLUMNS FROM empresas LIKE 'logradouro'`.
- **Funções:**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `empresa_tem_endereco` | 17 | `mysqli` | bool | a migração foi aplicada? (cache estático) | cadastro, perfil, `locais.php` |
| `endereco_empresa_do_post` | 27 | — (lê `$_POST`) | array `cep, logradouro, numero, complemento, bairro` | CEP só com dígitos (8); textos com trim e cortados (150/10/100/100) | cadastro, perfil |
| `endereco_empresa_formatado` | 38 | `array $e` | string | "Rua, nº - compl. - bairro - Cidade/UF - CEP 00000-000" (partes vazias omitidas). **Não escapa**; quem chama usa `htmlspecialchars` | perfil, `empresa/locais.php`, `locais.php`, `Vagas/criar.php` |
| `cnpj_formatado` | 53 | `string $cnpj` | `?string` | `00.000.000/0000-00`, ou null sem 14 dígitos | cadastro, perfil |

### `includes/locais_campos.php`
- **Tipo:** include de **template** (HTML + JS), não declara funções PHP.
- **Responsabilidade:** campos de endereço de um local de trabalho (CEP, rua, número, complemento, bairro, cidade*, UF*), com busca no ViaCEP.
- **Quem usa:** `empresa/locais.php:278` (`$campos_prefixo='local'`) e `Vagas/criar.php`.
- **Entradas (variáveis do escopo):** `$form` (valores), `$campos_prefixo` (prefixo dos ids, padrão `local`), `$campos_nome` (prefixo dos names, padrão `''`). Cria as variáveis `$_p`, `$_n`, `$_v`, `$_cepFmt` no escopo de quem incluiu.
- **Saídas:** `div[data-locais-campos]` com os inputs (`data-local-cep`, `data-local-campo="logradouro|numero|bairro|cidade|estado"`, `data-local-cep-status`). O `<script>` sai uma única vez, controlado por `$GLOBALS['_jc_locais_campos_js']`.
- **Funções:**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `buscar(campo)` (JS async) | 69 | input CEP | Promise | ViaCEP; só consulta com 8 dígitos e CEP diferente do último (WeakMap). Sobrescreve rua/bairro/cidade/UF dentro do bloco | `input` (com 9 caracteres) e `focusout` delegados |

- **Observação:** cidade/UF têm `required`, mas o formulário de `empresa/locais.php` usa `novalidate`, então a validação real é `jc_local_erro` no servidor. **[C]**

### `includes/locais.php`
- **Tipo:** include de funções (domínio "local de trabalho").
- **Responsabilidade:** modelo de locais. A sede é o endereço da tabela `empresas`. Os demais locais ficam em `empresa_locais`. Cada vaga indica `vagas.local_trabalho` (`'empresa'` | `'local'` | NULL legado) e `vagas.local_id`. Este arquivo resolve o **local efetivo** e sincroniza as cópias de cidade/UF/CEP nas vagas.
- **Quem usa:** `Vagas/criar.php`, `Vagas/listar.php`, `Vagas/avaliar.php`, `empresa/locais.php`, `empresa/perfil.php`, `IA/avaliar_ajax.php`, `includes/match.php` (`require_once` na linha 22) e `includes/assistente.php`.
- **Depende de:** `endereco_empresa.php` (via `require_once`) e da migração `migrations/2026-10-08_stage51_locais_trabalho.sql` (tabela `empresa_locais`; `vagas.local_trabalho ENUM('empresa','local')`, `vagas.local_id` com FK `ON DELETE SET NULL`).
- **"Local efetivo" [C] (`jc_local_resolver`):**
  1. `local_trabalho='local'`, com `local_id` e o local encontrado (`l_tipo` não nulo) → endereço do local (`l_*`), origem `local`, rótulo "Escritório — Nome".
  2. `local_trabalho='empresa'` → endereço principal (`e_*`), rótulo "Endereço principal da empresa".
  3. Caso contrário (vaga antiga, local removido ou banco sem a migração) → cidade/UF/CEP da própria vaga, **sem** rua, número, complemento e bairro (origem `vaga`, "Endereço informado na vaga"). Se a vaga não tiver cidade nem CEP → endereço da empresa.
  4. Nível: `completo` (rua + número + cidade + UF), `parcial` (cidade, CEP ou rua) ou `indisponivel` (nesse caso origem `indisponivel` e rótulo "Local de trabalho não informado"). Também devolve `faltando` (rua, número, bairro, cidade, UF, CEP) e `texto` (formatado).
- **Sem a migração [C]:** `jc_locais_disponiveis` = false (exige a tabela `empresa_locais` **e** a coluna `vagas.local_trabalho` **e** `empresa_tem_endereco`). Nesse caso: `jc_locais_da_empresa` → `[]`; as funções de sincronização não fazem nada; `jc_local_efetivo` não pede as colunas `l_*`/`local_trabalho`, e o resolver cai no caso 3. `jc_endereco_principal` e `jc_local_efetivo` selecionam as colunas `e_cep…` só se `empresa_tem_endereco`.
- **Banco:** `empresa_locais` (SELECT/INSERT), `vagas` (SELECT/UPDATE), `empresas` (SELECT). Tudo com prepared statements; os fragmentos de colunas interpolados são fixos no código.
- **Funções:**

| Nome | Linha | Parâmetros | Retorno | O que faz / tabelas | Quem chama |
|---|---|---|---|---|---|
| `jc_local_tipos` | 36 | — | array | escritorio/filial/unidade/outro → rótulos | `empresa/locais.php`, `Vagas/criar.php`, internas |
| `jc_locais_disponiveis` | 46 | `mysqli` | bool | `SHOW TABLES LIKE 'empresa_locais'` + `SHOW COLUMNS FROM vagas LIKE 'local_trabalho'` + endereço (cache estático) | perfil, locais, criar, internas |
| `jc_post_texto` | 59 | `array $post, string $chave, int $max` | string | string com trim e cortada; arrays viram `''` | `jc_local_do_post` |
| `jc_local_do_post` | 65 | `array $post, string $p=''` | array (9 chaves) | tipo inválido → `escritorio`; CEP só com dígitos (8); UF em maiúsculas (2) | `empresa/locais.php`, `Vagas/criar.php` |
| `jc_local_erro` | 81 | `array $l` | `?string` | CEP preenchido com tamanho diferente de 8 → "CEP inválido…"; cidade vazia ou UF fora de `^[A-Z]{2}$` → "Informe pelo menos a cidade e a UF…" | idem |
| `jc_local_rotulo` | 92 | `string $tipo, ?string $nome` | string | "Tipo — Nome", ou só o tipo | locais, criar, resolver |
| `jc_cep_formatado` | 99 | `?string` | `?string` | `00000-000`, ou null | sincronizações, criar |
| `jc_locais_da_empresa` | 105 | `mysqli, int $empresa_id` | array | `SELECT l.* … , (SELECT COUNT(*) FROM vagas WHERE local_id=l.id AND local_trabalho='local') AS em_uso FROM empresa_locais WHERE empresa_id=? ORDER BY criado_em, id` | perfil, locais, criar |
| `jc_endereco_principal` | 125 | `mysqli, int` | array (7 partes) | `SELECT cidade, estado[, cep, logradouro, numero, complemento, bairro] FROM empresas WHERE id=?` | locais, criar, `sincronizar_empresa` |
| `jc_local_partes` | 136 | `array $r, string $pre` | array | extrai as 7 partes com prefixo (CEP só com dígitos, UF em maiúsculas) | internas, criar |
| `jc_local_resolver` | 155 | `array $r` | array | regra do local efetivo (acima) | `jc_local_efetivo` |
| `jc_local_efetivo` | 210 | `mysqli, int $vaga_id` | array (`[]` se a vaga não existe) | `SELECT v.cidade, v.estado, v.cep, e.cidade AS e_cidade… FROM vagas v JOIN empresas e … LEFT JOIN empresa_locais l ON l.id=v.local_id AND l.empresa_id=v.empresa_id WHERE v.id=?` | `Vagas/listar.php`, `Vagas/avaliar.php`, `includes/match.php` |
| `jc_vagas_sincronizar_empresa` | 232 | `mysqli, int` | void | `UPDATE vagas SET cidade=?, estado=?, cep=? WHERE empresa_id=? AND local_trabalho='empresa'` (vazio → NULL) | `empresa/perfil.php` |
| `jc_vagas_sincronizar_local` | 249 | `mysqli, int $empresa_id, int $local_id, array $l` | void | `UPDATE vagas … WHERE empresa_id=? AND local_id=? AND local_trabalho='local'` | `empresa/locais.php` |
| `jc_local_inserir` | 266 | `mysqli, int, array $l` | int (id, ou 0) | `INSERT INTO empresa_locais (...)` (CEP/nome vazios → NULL). Com exceções ligadas, uma falha lança exceção em vez de devolver 0 | `empresa/locais.php`, `Vagas/criar.php` |

### `includes/candidato.php`
- **Tipo:** include de funções. É a regra única de acesso de uma empresa a um candidato.
- **Responsabilidade:** decide se a empresa pode ver o perfil, o currículo PDF e a análise de um jovem, e devolve as candidaturas do jovem às vagas da empresa. Também emite a página única de "indisponível".
- **Quem usa:** `jovem/perfil.php`, `uploads/ver_curriculo.php`, `Vagas/avaliar.php`, `IA/avaliar_ajax.php`.
- **Regra [C]:** `permitido = (jovens.perfil_publico = 1) OU (existe candidatura do jovem a uma vaga com vagas.empresa_id = empresa)`. O e-mail só é liberado no segundo caso (a decisão é tomada em `jovem/perfil.php`). Jovem inexistente → `permitido=false`.
- **Banco:** `SELECT perfil_publico FROM jovens WHERE id=?` e `SELECT c.id, c.vaga_id, c.status, c.criado_em, v.titulo FROM candidaturas c JOIN vagas v ON v.id=c.vaga_id WHERE c.jovem_id=? AND v.empresa_id=? ORDER BY c.criado_em DESC, c.id DESC` (prepared).
- **Funções:**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `jc_empresa_acesso_candidato` | 31 | `mysqli, int $empresa_id, int $jovem_id` | `{permitido, publico, candidaturas[]}` | regra acima; ids ≤ 0 → negado | `jovem/perfil.php` |
| `jc_empresa_pode_ver_candidato` | 65 | idem | bool | atalho | `ver_curriculo.php`, `Vagas/avaliar.php`, `IA/avaliar_ajax.php` |
| `jc_candidato_indisponivel` | 76 | `string $voltar='../dashboard/empresa.php'` | `never` | HTTP 404 + página "Perfil indisponível" (a mesma para "não existe" e "sem permissão") + `exit` | `jovem/perfil.php` |

- **Riscos:**
  - `perfil_publico` tem DEFAULT 1 (`schema.sql:81`), e o cadastro não pergunta nada sobre isso. Ou seja, todo jovem novo já está acessível a **qualquer** empresa logada, inclusive o PDF do currículo (ver os achados em [SEGURANCA_E_PRIVACIDADE.md](SEGURANCA_E_PRIVACIDADE.md) e [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md)). **[C]**
  - A regra não verifica se a empresa confirmou o e-mail/telefone. **[NC]**: não foi verificado se outra camada exige isso.
  - O tipo de retorno `never` só é verificado a partir do PHP 8.1. No 7.4/8.0 ele é lido como um nome de classe e não causa erro, porque a função sempre termina com `exit` (nunca retorna) **[I, semântica do PHP]**.

### `jovem/perfil.php`
- **Tipo:** ponto de entrada GET/POST com duas visões. PHP + HTML + JS.
- **Responsabilidade:** (1) **Dono**: edição do próprio perfil (dados básicos, escolaridade, resumo, visibilidade), formações adicionais, experiências, habilidades, envio do currículo, IA Advisor e exclusão da conta. (2) **Empresa**: visão somente leitura do candidato, com contexto de candidatura, link para a compatibilidade e contato por e-mail quando permitido.
- **Quando executa / quem usa:** `Vagas/candidatos.php:329` (`?id=…&voltar=candidatos&vaga=…`), `dashboard/empresa.php:277` (`?id=…[&vaga=…]`), links de "Meu Perfil" (`header.php:121`, `dashboard/jovem.php:117`, `Vagas/listar.php:422`, `Vagas/favoritos.php:65`, `Vagas/curriculoIA.php:132,252`, `Vagas/avaliar.php:107`), o redirecionamento de `uploads/curriculos.php` e o próprio formulário (POST).
- **Depende de:** `csrf.php`, `config.php`, `formacao.php`, `candidato.php`, `assistente.php`. AJAX: `../IA/avaliar_ajax.php` (que chama o Flask `/curriculo` no endereço de `jc_ia_url()` e, opcionalmente, o Gemini). POST de upload: `../uploads/curriculos.php`. Exclusão: `excluir_conta.php`.
- **Acesso (linhas 15-52) [C]:**
  1. Sem sessão de jovem nem de empresa → `Location: login.php` (login do jovem).
  2. `jovem_id` = `?id` (intval); senão a sessão do jovem; empresa sem `?id` → `../dashboard/empresa.php`.
  3. `eh_dono` = sessão de jovem com id igual. `eh_empresa` = sessão de empresa e não dono.
  4. Jovem tentando ver outro jovem (sem sessão de empresa) → `Location: perfil.php`.
  5. Empresa: `jc_empresa_acesso_candidato`. Negado → log `acesso.negado` (`recurso=perfil_candidato`) + `jc_candidato_indisponivel()` (404).
  6. Linha não encontrada depois disso → 404 com o texto "Perfil não encontrado." (na prática, só no caso do dono cuja conta foi apagada).
- **Ações POST (só o dono; `csrf_require()`; campo `action`, padrão `update_perfil`):**

| `action` | Campos | Validação | Banco | Redireciona |
|---|---|---|---|---|
| `update_perfil` | `nome`(100), `email`, `cidade`(100), `estado`(2, maiúsculas), `cep`(só dígitos, 8), `resumo_profissional` (sem corte), `area_interesse`(100), `perfil_publico` (checkbox), campos de formação (`formacao_nivel`…, `escolaridade_status`) ou o formato antigo (`escolaridade_status`, `escolaridade_tipo` checkbox, `escolaridade_curso`, `formacao`) | nome vazio, e-mail inválido ou > 100 → `?saved=2`; e-mail de outra conta → `?saved=3`; formação alterada e com erro → `$_SESSION['erro_formacao']` + `?saved=4`. CEP/UF sem validação de formato | `SELECT id FROM jovens WHERE email=? AND id<>?`; `SELECT email, email_verificado, escolaridade_*, formacao …`; `UPDATE jovens SET nome, email, email_verificado, cidade, estado, cep, resumo_profissional, area_interesse, perfil_publico, escolaridade_status, escolaridade_tipo, escolaridade_curso, formacao WHERE id=?`. Trocar o e-mail (comparação sem diferenciar maiúsculas) zera `email_verificado`. Formação inalterada (`jc_formacao_inalterado`) → mantém os valores atuais. Atualiza `$_SESSION['jovem_nome']` | `perfil.php?saved=1` |
| `add_formacao` (formato novo, com `form_formacao_nivel`) | `form_formacao_nivel`, `form_formacao_ano`, `form_curso_*`, `form_formacao_outro`, `form_situacao`, `form_instituicao`(150), `form_conclusao` | `jc_formacao_ler_post(...,'form_','form_situacao',true,false)` (sem "em técnico"); ano `^(19|20)\d{2}$` | `INSERT INTO formacao_jovem (jovem_id, titulo, instituicao, conclusao, area)`: título = rótulo (150); conclusão = "Concluído"/"Cursando" + " (AAAA)"; área = curso (técnico/superior/profissionalizante) | erro → `?saved=4#formacao`; ok → `perfil.php#formacao` |
| `add_formacao` (formato antigo) | `form_titulo`(150), `form_instituicao`(150), `form_conclusao`(20), `form_area`(100) | só insere se tiver título | mesmo INSERT | `#formacao` |
| `del_formacao` | `form_id` | intval | `DELETE FROM formacao_jovem WHERE id=? AND jovem_id=?` (com dono) | `#formacao` |
| `add_habilidade` | `habilidade` | espaços normalizados, 80; não insere repetida (SELECT com a collation `utf8mb4_unicode_ci`, sem diferenciar maiúsculas); **sem limite de quantidade** | `SELECT id … WHERE jovem_id=? AND habilidade=?` + `INSERT IGNORE INTO habilidades_jovem` | `#habilidades` |
| `del_habilidade` | `hab_id` | intval | `DELETE … WHERE id=? AND jovem_id=?` | `#habilidades` |
| `add_experiencia` | `exp_titulo`(150, obrigatório), `exp_local`(150), `exp_periodo`(50), `exp_descricao` (sem corte) | título não vazio | `INSERT INTO experiencias_jovem (jovem_id, titulo, local, periodo, descricao)` | `#experiencias` |
| `del_experiencia` | `exp_id` | intval | `DELETE … WHERE id=? AND jovem_id=?` | `#experiencias` |
| outro valor | — | — | nada (cai na leitura e renderiza a página) | — |

  POST de quem não é dono (empresa) é ignorado: o bloco exige `$eh_dono` e a página renderiza normalmente. **[C]**
- **Colunas selecionadas em cada visão [C] (linhas 311-318):**
  - **Dono:** `nome, email, cpf, data_nascimento, cidade, estado, cep, escolaridade_status, escolaridade_tipo, escolaridade_curso, formacao, resumo_profissional, area_interesse, perfil_publico, curriculo` + `idade` (`TIMESTAMPDIFF`). `cpf`/`data_nascimento` são selecionados mas não aparecem no HTML.
  - **Empresa:** `nome, cidade, estado, escolaridade_status, escolaridade_tipo, escolaridade_curso, formacao, resumo_profissional, area_interesse, curriculo` + `idade`, e **`email` só se `$acesso['candidaturas']` não estiver vazio**. Nunca CPF, CEP, telefone ou data de nascimento.
  - As duas visões também leem `formacao_jovem (id, titulo, instituicao, conclusao, area)`, `habilidades_jovem (id, habilidade)` e `experiencias_jovem (id, titulo, local, periodo, descricao)`, todas `WHERE jovem_id=?`.
- **Entradas GET:** `id`, `saved` (1-4), `vaga` (foco da candidatura, só na visão da empresa) e `voltar` (só o valor fixo `candidatos` + `vaga` > 0 leva a `../Vagas/candidatos.php?vaga=N`; caso contrário, ao painel).
- **Sessão:** `erro_formacao`, `sucesso_upload` e `erro_upload` são lidas e apagadas em seguida (PRG). Os toasts têm prioridade: sucesso do upload > erro do upload > `saved`.
- **Interface do dono:**
  - `form#formPerfil` vazio, com `action=update_perfil`. Os campos espalhados pela página apontam para ele com `form="formPerfil"` (evita formulários aninhados).
  - Sidebar: avatar com a inicial, view/edit (`#viewNome/#editNome/#viewDados/#editDados`, alternados por `#btnEditar`; "Cancelar" restaura os `defaultValue`), `jc_formacao_campos(id='perf', form='formPerfil', obrigatorio=false, vazio='Não informado')`, IA Advisor (`#btnAnalisarPerfil`), "Excluir Conta" (abre `#modalExcluir`, POST para `excluir_conta.php` com `confirmar=1` + CSRF).
  - Seção "Currículo Automático": textarea do resumo, "Baixar PDF"/"Ver PDF" (`../uploads/ver_curriculo.php?id=N`, só se o arquivo existir em disco) e formulário de upload (`multipart`, `MAX_FILE_SIZE=5242880`, `accept=application/pdf`, `data-envio-unico`).
  - Seção "Formação" (`#formacao`): formulário de adição oculto, alternado por `toggleAddForm`; resumo da escolaridade; lista com botões de remover.
  - Seções "Experiência" (`#experiencias`) e "Habilidades" (`#habilidades`, com `datalist` de 16 sugestões); "Configurações de Visibilidade" (toggle `perfil_publico`).
  - AJAX do IA Advisor: `POST ../IA/avaliar_ajax.php`, JSON `{action:'curriculo', jovem_id}`, cabeçalho CSRF injetado por `csrf_js()`. A resposta `{mensagem, sugestoes[]}` é inserida com **`innerHTML` sem escapar**. Se `!res.ok` ou houver exceção: "IA temporariamente indisponível.".
- **Interface da empresa:**
  - Sidebar: área, avatar, nome, idade + cidade/UF, resumo (`nl2br`), escolaridade (`jc_formacao_resumo`), habilidades.
  - Seção "Candidatura": a candidatura em foco (`?vaga=` de uma candidatura dela; ou a única, se houver só uma), com status, data e links "Ver candidatos da vaga" (`../Vagas/candidatos.php?vaga=`) e "Ver compatibilidade" (`../Vagas/avaliar.php?vaga_id=&jovem_id=`); demais candidaturas; aviso de "perfil visível" quando não há candidatura.
  - Formação, Experiências (com descrição), Ações: PDF, `mailto:` (só com e-mail liberado), "Salvar candidato (em breve)" desabilitado. "IA Dica": texto fixo que depende de `escolaridade_tipo`.
- **Funções JS:**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `toggleAddForm(id, btn)` | 1188 | string, Element | void | mostra/esconde o formulário, ajusta `aria-expanded`, foca o 1º campo | `onclick` dos botões "+" |
| `abrirModalExcluir()` | 1208 | — | void | `display:flex` + foco em Cancelar | `#btnExcluir` |
| `fecharModalExcluir()` | 1209 | — | void | esconde + foco em Excluir | Cancelar, ×, Esc, clique fora |
| handler do `#btnEditar`, do toast e do IA Advisor | 1148-1254 | — | — | descritos acima | DOM |

- **Riscos:** ver os achados em [SEGURANCA_E_PRIVACIDADE.md](SEGURANCA_E_PRIVACIDADE.md) e [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md). Resumo: XSS por `innerHTML` no IA Advisor; `perfil_publico=1` por padrão; corrida no e-mail que termina em 500; `resumo`/`descricao` sem corte.

### `empresa/perfil.php`
- **Tipo:** ponto de entrada GET/POST. PHP + HTML + JS.
- **Responsabilidade:** a empresa vê e edita os próprios dados (nome, e-mail, CNPJ, endereço completo, área, descrição, site, LinkedIn, "perfil verificado"). Ao salvar, sincroniza cidade/UF/CEP das vagas que usam o endereço principal. Lista as vagas e dá acesso aos locais de trabalho e à exclusão da conta.
- **Quem usa:** `dashboard/empresa.php:166`, `header.php:124`, `Vagas/minhas.php:92`, `Vagas/candidatos.php` (voltar e redirecionamentos 24/30/49), `Vagas/avaliar.php:107`, `empresa/locais.php:170,209`, `empresa/excluir_conta.php:22,29`.
- **Depende de:** `csrf.php`, `config.php`, `endereco_empresa.php`, `locais.php`, `assistente.php`. Externo: ViaCEP (navegador).
- **Entradas POST:** `nome_empresa`(150), `email` (≤ 100, validado), `cnpj` (14 dígitos → formatado), `cidade`(100), `estado`(2, maiúsculas; **sem validação de formato**), `area_atuacao`(100), `descricao` (sem corte, TEXT), `site_oficial`(255, **sem validação no servidor**), `linkedin`(255), `perfil_verificado` (checkbox), `cep/logradouro/numero/complemento/bairro` (`endereco_empresa_do_post`).
- **Validações / saídas:**
  - Nome vazio, e-mail inválido, CNPJ diferente de 14 dígitos ou CEP preenchido com tamanho diferente de 8 → `perfil.php?saved=2`.
  - E-mail/CNPJ de outra empresa → `?saved=3`.
  - Sucesso → `?saved=1`.
  - Sem sessão → `login.php`. Linha não encontrada → texto "Erro ao carregar dados da empresa.".
- **Banco:**
  - `SELECT id FROM empresas WHERE (email=? OR cnpj=?) AND id<>?`.
  - `SELECT email, email_verificado`.
  - `UPDATE empresas SET nome_empresa, email, email_verificado, cnpj, cidade, estado, area_atuacao, descricao, site_oficial, linkedin, perfil_verificado WHERE id=?`.
  - Com a migração: `UPDATE empresas SET cep, logradouro, numero, complemento, bairro WHERE id=?`.
  - `jc_vagas_sincronizar_empresa`.
  - Leitura: `SELECT nome_empresa, email, cnpj, cidade, estado, telefone, area_atuacao, descricao, site_oficial, linkedin, perfil_verificado[, cep…]` e `SELECT id, titulo, area, cidade, estado FROM vagas WHERE empresa_id=? ORDER BY criado_em DESC`.
  - Tudo prepared, sem transação.
- **Sincronização das vagas com o endereço [C]:** depois de salvar, `jc_vagas_sincronizar_empresa` copia cidade/UF/CEP (formatado) da sede para as vagas com `local_trabalho='empresa'` (só se `jc_locais_disponiveis`). Endereço vazio → as cópias nas vagas viram NULL. As vagas com `local_trabalho` NULL (antigas) ou `'local'` não são alteradas.
- **Interface:**
  - Um único `form#formPerfil` (POST, **sem `novalidate`**) envolve sidebar e conteúdo.
  - View/edit dos dados básicos (`#btnEditar`; "Cancelar" restaura os valores e o `ultimoCepBuscado`); campos de endereço só com a migração.
  - "Locais de trabalho: Sede + N outros · Gerenciar locais" (só com `jc_locais_disponiveis`).
  - "Dados Institucionais": área, descrição, site (`type="url"`), LinkedIn.
  - "Vagas Ativas": **todas** as vagas (não existe coluna de status em `vagas`), com link `../Vagas/candidatos.php?id=N&voltar=perfil`.
  - Toggle "Perfil Verificado".
  - Modal de exclusão (POST `excluir_conta.php`).
  - JS: tema, toast, `buscarCep()` (igual ao do cadastro), modal.
- **Funções JS:** `buscarCep()` (l. 527: ViaCEP, sobrescreve rua/bairro/cidade/UF, foca no número), `abrirModalExcluir()`/`fecharModalExcluir()` (l. 567-568).
- **Riscos:** o selo "Perfil verificado" é declarado pela própria empresa; `type="url"` impede salvar quando o site foi gravado sem esquema; não trata 1062; estado sem validação (ver [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md)).

### `empresa/locais.php`
- **Tipo:** ponto de entrada GET/POST. PHP + HTML.
- **Responsabilidade:** CRUD dos "outros locais" (escritório, filial, unidade, outro) da empresa. Mostra a sede (que é editada no perfil). Impede remover um local usado por vagas e sincroniza as vagas ao editar.
- **Quem usa:** `empresa/perfil.php:245` e `Vagas/criar.php:375`.
- **Depende de:** `csrf.php`, `config.php`, `locais.php` (e `endereco_empresa.php`), `locais_campos.php`, `assistente.php`.
- **Entradas:**
  - POST `acao` = `salvar` | `remover`; `local_id`; `tipo`, `nome`(100) + os campos de endereço (`jc_local_do_post`).
  - GET: `editar` (id), `ok` (`adicionado`, `editado`, `removido`, `em_uso`, `erro`).
  - Sessão: `locais_erro`, `locais_form` (repopular depois de erro).
- **Permissões:**
  - Sessão de empresa (senão `login.php`); CSRF.
  - Dono: o closure `$do_proprio` confere `SELECT id FROM empresa_locais WHERE id=? AND empresa_id=?`. UPDATE e DELETE também filtram por `empresa_id`.
  - `?editar` só encontra os locais da lista da própria empresa.
  - POST sem `jc_locais_disponiveis` → ignorado (renderiza a mensagem "ainda não estão disponíveis").
- **Fluxos POST:**
  - `salvar` com `jc_local_erro` → sessão + `Location: locais.php[?editar=ID]#formLocal`.
  - `salvar` com id > 0 que não é da empresa → `?ok=erro`.
  - Edição → `UPDATE empresa_locais SET tipo, nome, cep, logradouro, numero, complemento, bairro, cidade, estado WHERE id=? AND empresa_id=?` + `jc_vagas_sincronizar_local` + log `local.editado` → `?ok=editado`.
  - Inclusão → `jc_local_inserir`, log `local.adicionado` ou `local.erro` → `?ok=adicionado|erro`.
  - `remover`: verifica o dono e depois `SELECT COUNT(*) FROM vagas WHERE local_id=? AND empresa_id=?`. Se > 0 → log `local.remocao_bloqueada` → `?ok=em_uso`; senão `DELETE FROM empresa_locais WHERE id=? AND empresa_id=?` + log `local.removido` → `?ok=removido`.
  - `acao` desconhecida → `locais.php`.
- **Interface:** alerta (`role=alert` ou `status`); sede (texto formatado + link para o perfil); lista `ul.locais-lista` (rótulo, endereço, "Usado por N vagas", Editar, Remover desabilitado quando em uso, com `data-envio-unico`); `form#formLocal` (`novalidate`, select de tipo, nome, `locais_campos.php`, "Salvar local", "Cancelar edição").
- **Funções:** `$do_proprio` (closure, l. 32: `int $id` → bool, SELECT de posse); `$h` (escape).
- **Observação:** quando há uso, o botão Remover é desabilitado no HTML e a regra também é aplicada no servidor. **[C]**

### `uploads/curriculos.php`
- **Tipo:** endpoint POST (multipart), sem HTML próprio; responde só com redirecionamentos (PRG).
- **Responsabilidade:** recebe o PDF do currículo do jovem logado, valida, grava em disco com nome fixo e atualiza `jovens.curriculo`.
- **Quem usa:** formulário em `jovem/perfil.php:648`.
- **Entradas:** `$_FILES['curriculo']`, `csrf_token`, `MAX_FILE_SIZE` (cliente, 5242880), sessão `jovem_id`.
- **Validações [C], em ordem:**
  1. Sem sessão de jovem → `../jovem/login.php`.
  2. Método diferente de POST → `../jovem/perfil.php`.
  3. `csrf_require()` (403).
  4. `error !== UPLOAD_ERR_OK` → mensagem mapeada (INI_SIZE, FORM_SIZE, PARTIAL, NO_FILE, NO_TMP_DIR, CANT_WRITE, EXTENSION) ou "Erro desconhecido (código N)".
  5. `size > 5*1024*1024` → "O arquivo excede o limite de 5 MB.".
  6. Os 4 primeiros bytes precisam ser `%PDF` (magic bytes; não confia em `$_FILES['type']` nem na extensão; não usa `finfo`).
- **Gravação:** nome fixo `curriculo_{jovem_id}.pdf` (ignora o nome original e sobrescreve o anterior), em `{raiz}/uploads/curriculos/` (cria com `mkdir 0755` se não existir), via `move_uploaded_file`.
- **Banco:** `UPDATE jovens SET curriculo=? WHERE id=?` (prepared).
- **Saídas:** erro → `$_SESSION['erro_upload']` + `Location: ../jovem/perfil.php?saved=2`. Sucesso → `$_SESSION['sucesso_upload']='Currículo atualizado com sucesso!'` + `?saved=1`.
- **Erros:** falha no `move_uploaded_file` → "Falha ao salvar o arquivo…". O ramo "o banco falhou → `unlink`" (l. 98-104) não é atingível com exceções ativas: a exceção vai para o handler global (500) **e o arquivo fica em disco** **[I]** (ver as observações gerais). Não há log de sucesso nem de recusa.
- **Funções:** nenhuma.

### `uploads/ver_curriculo.php`
- **Tipo:** endpoint GET que entrega um binário (PDF).
- **Responsabilidade:** entrega protegida do PDF do currículo, para o dono ou para uma empresa autorizada pela regra de `includes/candidato.php`.
- **Quem usa:** links `../uploads/ver_curriculo.php?id=N` em `jovem/perfil.php` (as duas visões) **[C]**. Outras páginas, como `Vagas/candidatos.php`, não apareceram no grep por `ver_curriculo`.
- **Depende de:** `includes/session.php` (`jc_session_start()`; não carrega o CSRF porque é GET), `config.php` (logger), `candidato.php`.
- **Entradas:** `GET id` (`filter_input FILTER_VALIDATE_INT`); sessão `jovem_id`/`empresa_id`.
- **Fluxo [C]:**
  1. Sem sessão → `Location: ../jovem/login.php`.
  2. id ≤ 0 → 404.
  3. Jovem que não é o dono e sem sessão de empresa → 404.
  4. `SELECT curriculo FROM jovens WHERE id=?`; vazio ou inexistente → 404.
  5. Não é dono e `!jc_empresa_pode_ver_candidato` → log `acesso.negado` (`recurso=curriculo`) + 404.
  6. `basename()` do valor gravado; o arquivo precisa existir.
  7. Headers `Content-Type: application/pdf`, `Content-Length`, `Content-Disposition: inline; filename="curriculo.pdf"`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`; `readfile`.
- **Erro:** texto "Currículo não encontrado." com HTTP 404 nos casos 2 a 6 (resposta única que não revela o motivo).
- **Funções:** `curriculo_nao_encontrado(): never` (l. 27).

### `uploads/curriculos/.htaccess`
- **Tipo:** configuração do Apache.
- **Responsabilidade:** bloqueia o acesso HTTP direto à pasta dos PDFs (`Require all denied` no Apache 2.4; `Order allow,deny / Deny from all` no 2.2).
- **Limitação [I]:** só tem efeito no Apache com `AllowOverride` que permita `AuthConfig`/`Limit`. Em Nginx, no servidor embutido do PHP ou com `AllowOverride None`, os arquivos `curriculo_{id}.pdf` (nome previsível) ficam acessíveis diretamente.

## Módulo C — Vagas, candidaturas, favoritos e painéis

Dependências comuns a quase todos os arquivos (não repetidas em cada seção):
- `includes/csrf.php` → inclui `includes/session.php` e chama `jc_session_start()`; fornece `csrf_field()`, `csrf_require(bool $ajax)`, `csrf_js()`. `csrf_js()` substitui `window.fetch` para acrescentar o header `X-CSRF-Token` em todo POST de mesma origem. `csrf_verify()` aceita `$_POST['csrf_token']`, header `X-CSRF-Token` ou `csrf_token` em corpo JSON (comparação `hash_equals`). Falha → `jc_log_seguranca('csrf.recusado')`, HTTP 403 (HTML amigável ou JSON `{"error": "Sua sessão expirou..."}`).
- `includes/config.php` → conexão `$conn` (mysqli), carrega `includes/logger.php` (`jc_log`, `jc_log_seguranca`, `jc_log_excecao`) e registra `set_exception_handler('jc_excecao_nao_tratada')`: exceção não tratada → HTTP 500, JSON `{"error":"Erro interno. Tente novamente em instantes."}` quando a requisição é XHR ou já tem header JSON; senão HTML curto com links "Voltar"/"Ir para a página inicial"; detalhes vão para o log.
- `includes/assistente.php` → FABs, chat do assistente, painel de acessibilidade; define `window.jcAviso(msg, tipo)`, `window.jcIcone(nome)` e o comportamento `<form data-envio-unico="...">` (trava o botão após o 1º envio).
- CSS: todas as páginas (inclusive `candidaturas/minhas.php`, via `includes/header.php`) referenciam `../assets/css/style.css` (`assets/` minúsculo desde a v4.59 (antes `Assets/`, que no Linux deixava a página sem CSS)).

### `Vagas/listar.php`

- **Tipo:** ponto de entrada, página GET com dois modos (listagem e detalhes). PHP + HTML + JS inline.
- **Responsabilidade:** (1) sem `?id` lista todas as vagas com filtros por texto, cidade, UF e área, marcando as favoritas do jovem; (2) com `?id=N` mostra os detalhes da vaga (dados, requisitos, benefícios, local efetivo, nota de match em cache, estado "já candidatado"). Na listagem, cada vaga usa o card padrão `jc_card_vaga()` (`includes/card_vaga.php`, desde a v4.56), o mesmo do painel do jovem: estrela de favoritar (AJAX) e botão "Avaliar Match com IA" (AJAX).
- **Quando executa / quem usa:** links em `dashboard/jovem.php` (cards → `listar.php?id=X&voltar=painel`; botão "Ver todas as vagas" → `listar.php`; chips de área → `listar.php?area=X`; busca do topo → `listar.php?busca=`), `Vagas/favoritos.php` (`?id=X&voltar=favoritos`), `candidaturas/minhas.php` (`?id=X&voltar=candidaturas`), `Vagas/candidatar.php` (seta/Cancelar → `listar.php?id=X[&voltar=..]`), `Vagas/favoritar.php` (redirect sem JS → `listar.php?id=X&favorito=added|removed`), `Vagas/avaliar.php` (linha ~110, voltar para `listar.php?id=`), `includes/header.php` (form de busca GET `../Vagas/listar.php`, campo `busca`), `includes/assistente.php` (mapa de contexto `vagas/listar.php`).
- **Depende de:** `includes/csrf.php`, `includes/config.php`, `includes/locais.php` (`jc_local_efetivo`), `includes/card_vaga.php` (`jc_card_vaga`, `jc_card_vaga_script`), `includes/assistente.php`. Externo: link para Google Maps (`https://www.google.com/maps/search/...`, só navegação). AJAX para `favoritar.php` e `../IA/avaliar_ajax.php`.
- **Entradas:**
  - SESSION `jovem_id` (obrigatória; `intval`).
  - Modo detalhes: GET `id` (`intval`, > 0 ativa o modo); GET `voltar` ∈ {`painel`, `favoritos`, `candidaturas`} (lista fixa `$destinos_voltar`; qualquer outro valor → `listar.php`).
  - Modo listagem: GET `busca` (`trim`), `cidade` (`trim`), `estado` (`strtoupper(trim)`), `area` (`trim`). Sem limite de tamanho, sem lista de valores permitidos.
- **Saídas:** HTML. Redirecionamentos: sem sessão → `../jovem/login.php`; `?id` de vaga inexistente → `listar.php`. Sem códigos HTTP explícitos.
- **Banco (todas com prepared statement, exceto a de áreas que é query fixa sem parâmetros):**
  - Detalhes: `SELECT v.id, titulo, descricao, cidade, estado, salario, carga_horaria, area, tipo_contrato, cep, e.nome_empresa, e.cidade FROM vagas v JOIN empresas e ... WHERE v.id=? LIMIT 1`; `SELECT requisito FROM requisitos_vaga WHERE vaga_id=? ORDER BY id`; `SELECT beneficio FROM beneficios_vaga WHERE vaga_id=? ORDER BY id`; `SELECT score, analise FROM avaliacoes_ia WHERE jovem_id=? AND vaga_id=?`; `SELECT id FROM candidaturas WHERE jovem_id=? AND vaga_id=?`; mais as consultas internas de `jc_local_efetivo` (vagas, empresas, `empresa_locais` se a migração existir).
  - Listagem: SQL dinâmico (ver "Como o SQL é montado" abaixo) em `vagas JOIN empresas LEFT JOIN favoritos`; `SELECT DISTINCT area FROM vagas WHERE area IS NOT NULL AND area != '' ORDER BY area` (`$conn->query`, sem entrada do usuário).
- **Como o SQL da listagem é montado [C]:**
  - `$types = "i"`, `$params = [$jovem_id]` (o 1º parâmetro é do `LEFT JOIN favoritos f ON f.vaga_id = v.id AND f.jovem_id = ?`).
  - `busca` não vazia → `(v.titulo LIKE ? OR v.descricao LIKE ?)` com `%busca%` (2× `s`).
  - `cidade` → `v.cidade LIKE ?` com `%cidade%`.
  - `estado` → `v.estado = ?` (igualdade exata).
  - `area` → `v.area = ?` (igualdade exata).
  - Cláusulas unidas com `AND` após `WHERE 1=1`; `ORDER BY v.criado_em DESC`.
  - Coluna calculada `IF(f.id IS NOT NULL, 1, 0) AS eh_favorito`.
  - **Não há paginação nem `LIMIT`**: todas as vagas que casam são carregadas e renderizadas. `%` e `_` digitados pelo usuário não são escapados (funcionam como curingas do LIKE) — sem risco de injeção (prepared), só semântica.
- **Permissões e validações:** só jovem logado. Qualquer jovem vê qualquer vaga (não há conceito de vaga encerrada/inativa). Página emite `csrf_js()` (necessário para o POST AJAX de favoritos e da IA).
- **Erros:** vaga inexistente → redirect silencioso para a listagem. Lista vazia → card "Nenhuma vaga encontrada" com link `listar.php`. Erros de banco → exceção → handler global (HTTP 500).
- **Interface:**
  - *Detalhes:* barra superior com seta (destino `$voltar_url`, rótulo `$voltar_rotulo`) e botão "Candidatar-se" (`candidatar.php?vaga=X[&voltar=..]`) ou badge "Candidatura Enviada". Coluna principal: badge de área, título, empresa, meta (SALÁRIO "R$ ..." — exibido como texto livre, CARGA HORÁRIA, LOCAL = `vagas.cidade, estado`, CONTRATO), descrição (`nl2br(htmlspecialchars)`), Requisitos (grid, se houver), Benefícios (chips, se houver). Aside: cartão de localização com `nome_empresa`, `local_vaga['rotulo']` e `local_vaga['texto']`; botão "Ver no Google Maps" (só se `nivel` ≠ `indisponivel`; consulta = endereço completo, ou nome da empresa + texto quando o nível não é `completo`); "Candidatar-se Agora" ou "Já candidatado · ver status" (→ `../candidaturas/minhas.php`); card "IA Dica": com cache mostra "Match calculado: N%" + 120 caracteres da análise + link "Ver análise completa" (`avaliar.php?vaga_id=X`); sem cache, link "Avaliar Compatibilidade" (mesmo destino). Não há botão de favoritar no modo detalhes. O parâmetro `favorito=` vindo de `favoritar.php` não é lido (nenhum aviso).
  - *Listagem:* navbar com form `#formBusca` (GET `listar.php`, campo `busca`, preserva só `area` em hidden); atalhos para candidaturas, favoritos, perfil, sair; botão de tema `#btnTheme`. Sidebar: chips de área (link "Todos" → `listar.php[?busca=]`; cada área → `listar.php?area=X[&busca=]`) — os chips **descartam** `cidade`/`estado`; form "Localização" (GET, campos `cidade` `#filtroCidade`, `estado` `#filtroEstado` maxlength=2, preserva `busca` e `area` em hidden) e "Limpar filtro" (mantém busca/área). Contador "Mostrando N resultado(s) para ... em ...". Grid `#gridVagas` com um `jc_card_vaga($vaga, '')` por vaga: o card inteiro abre `listar.php?id=X` (**sem `voltar`**), o título é um link de verdade, badge de área, estrela `#fav-X` (`toggleFavorito`, não abre o detalhe), empresa, salário, carga, cidade/UF e botão "Avaliar Match com IA" (`avaliarMatch`, não abre o detalhe). O botão "Ver Detalhes" deixou de existir na v4.56 (o card inteiro é clicável).
- **Funções:**

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos / tabelas / erros | Quem chama |
|---|---|---|---|---|---|
| `applyTheme` (JS) | 614 | `t` (string `'light'`/`'dark'`) | — | Aplica `data-theme` e troca o ícone do botão; preferência em `localStorage['jc-theme']` | carga da página, clique em `#btnTheme` |
| `toggleFavorito` (JS) | — | `vagaId` (int), `btn` (HTMLButtonElement) | Promise | Desde a v4.56 vem de `jc_card_vaga_script('favoritar.php')` (`includes/card_vaga.php`), com o mesmo código de antes: POST `FormData{vaga_id}` para `favoritar.php` com header `X-Requested-With: XMLHttpRequest` (+ `X-CSRF-Token` via `csrf_js`); exige `res.ok` e `data.favorito` booleano; atualiza ícone, cor, `title`, `aria-label` e `aria-pressed`; erro → `jcAviso`; ignora cliques repetidos durante a requisição | estrela do card |
| `avaliarMatch` (JS) | 627 | `vagaId` (int), `btn` | Promise | POST JSON `{action:'match', vaga_id, jovem_id}` para `../IA/avaliar_ajax.php` (o servidor ignora o `jovem_id` enviado e usa a sessão — ver `IA/avaliar_ajax.php` linha ~63). Sucesso → "Match: N%" e adiciona link `.match-link` "Ver análise detalhada" (`avaliar.php?vaga_id=X`). Erro → mostra `corpo.error` ou mensagem genérica por 3,5 s e reabilita | `onclick` do botão "Match IA" |

- **Riscos / limitações / observações:**
  - Sem paginação/limite (performance cresce com o nº de vagas) [C].
  - Filtros não se combinam totalmente: chips de área e "Todos" perdem cidade/UF; a busca do topo perde cidade/UF [C].
  - O card e o título na listagem não passam `voltar`, então a seta dos detalhes volta à listagem **sem os filtros** usados [C].
  - Detalhes mostram LOCAL a partir de `vagas.cidade/estado` (cópia) e o aside a partir de `jc_local_efetivo` — normalmente iguais, pois `includes/locais.php` sincroniza a cópia [I: `jc_vagas_sincronizar_empresa`/`_local`].
  - Os atributos `data-titulo`/`data-empresa` deixaram de existir com o card padrão (v4.56); nenhuma página tem mais busca só no navegador [C].

---

### `Vagas/criar.php`

- **Tipo:** ponto de entrada, página GET (formulário) + POST (processamento com PRG). PHP + HTML + JS.
- **Responsabilidade:** permite à empresa publicar uma vaga com campos básicos, local de trabalho, remuneração, descrição, requisitos e benefícios. Grava `vagas`, `requisitos_vaga`, `beneficios_vaga` e, opcionalmente, um novo `empresa_locais`. Não existe modo de edição.
- **Quando executa / quem usa:** links em `dashboard/empresa.php` ("Criar Vaga", "Criar Nova Vaga", "Criar Primeira Vaga"), `Vagas/minhas.php` ("Criar Nova Vaga", "Publicar Primeira Vaga"), `Vagas/candidatos.php` ("Nova Vaga"), `empresa/perfil.php` (linhas ~379 e ~402). O form faz POST para a própria página (sem `action`).
- **Depende de:** `includes/csrf.php`, `includes/config.php`, `includes/locais.php` (`jc_locais_disponiveis`, `jc_local_do_post`, `jc_local_erro`, `jc_locais_da_empresa`, `jc_local_partes`, `jc_endereco_principal`, `jc_cep_formatado`, `jc_local_inserir`, `jc_local_rotulo`, `jc_local_tipos`), `includes/endereco_empresa.php` (via locais.php: `endereco_empresa_formatado`, `empresa_tem_endereco`), `includes/locais_campos.php` (campos de endereço com prefixo `vagaLocal`/nome `local_`), `includes/assistente.php`. Externo: **ViaCEP** (`https://viacep.com.br/ws/{cep}/json/`) só no formulário antigo (sem migração).
- **Entradas (POST):** `csrf_token`; `titulo`, `descricao`, `tipo_contrato` (obrigatórios, só `trim` + `empty`); `cidade`, `estado` (`strtoupper`), `cep` (só dígitos; formatado `00000-000` se tiver 8), `salario`, `carga_horaria`, `area` (só `trim`); `requisitos`, `beneficios` (texto separado por vírgula); `local_trabalho` (`empresa` | `local:<id>` | `outro`); com `outro`: `local_tipo`, `local_nome`, `local_cep`, `local_logradouro`, `local_numero`, `local_complemento`, `local_bairro`, `local_cidade`, `local_estado` (lidos por `jc_local_do_post`, que corta no tamanho da coluna e valida o tipo contra `jc_local_tipos()`). GET `error` ∈ {`missing`, `db`, `local`}. SESSION `empresa_id`.
  - **Não há validação no servidor** de `tipo_contrato` e `area` contra as opções do `<select>`, nem limite de tamanho para `titulo` (VARCHAR 150), `salario` (50), `carga_horaria` (20), `estado` (50) no modo antigo [C].
- **Saídas / redirecionamentos:** sem sessão → `../empresa/login.php`; obrigatório ausente → `criar.php?error=missing`; local inválido → `criar.php?error=local`; falha no INSERT da vaga → `criar.php?error=db`; sucesso → `minhas.php?publicada=1` (PRG). CSRF inválido → 403 HTML.
- **Escolha do local de trabalho [C]:**
  - `$tem_locais = jc_locais_disponiveis($conn)` → true só se existe a tabela `empresa_locais`, a coluna `vagas.local_trabalho` e as colunas de endereço em `empresas` (`empresa_tem_endereco`).
  - **Sem a migração** (`$tem_locais = false`): o formulário mostra o card antigo (CEP com botão ViaCEP, cidade, UF) e o INSERT usa só `empresa_id, titulo, descricao, cidade, estado, cep, salario, carga_horaria, area, tipo_contrato`. Cidade/UF são opcionais nesse modo.
  - **Com a migração**: rádios `local_trabalho`: "Endereço principal da empresa" (desabilitado se a empresa não tem cidade), um rádio por local cadastrado (`local:<id>`), "Outro endereço" (fieldset `#localOutro`, `hidden disabled` até ser marcado). Padrão: `empresa` se há cidade; senão primeiro local; senão `outro`.
    - Compatibilidade: se a migração existe mas o POST **não** traz `local_trabalho` e traz cidade/UF (página antiga aberta antes da atualização), usa o comportamento antigo (`$tem_locais_form = false`).
    - `empresa` → `jc_endereco_principal`; sem cidade → `error=local`. `local_trabalho='empresa'`, `local_id=NULL`.
    - `local:<id>` → procura o id em `jc_locais_da_empresa($conn, $empresa_id)` (garante que é da própria empresa); não achou → `error=local`. `local_trabalho='local'`, `local_id=id`.
    - `outro` → `jc_local_do_post($_POST,'local_')`; `jc_local_erro` exige cidade e UF (`^[A-Z]{2}$`) e CEP vazio ou 8 dígitos → senão `error=local`. Dentro de transação: `jc_local_inserir` (INSERT `empresa_locais`) e depois INSERT da vaga com o novo `local_id`.
    - Em todos os casos a vaga recebe uma cópia de `cidade`, `estado`, `cep` do local escolhido (o que o usuário digitou nos campos antigos é sobrescrito).
- **Banco:** `INSERT INTO vagas (...)` (2 variantes, prepared; `bind_param "issssssissss"` ou `"isssssssss"`); `INSERT INTO empresa_locais` (via `jc_local_inserir`, prepared); `INSERT INTO requisitos_vaga (vaga_id, requisito)` para cada item `trim` não vazio de até 150 bytes (`strlen`); `INSERT INTO beneficios_vaga (vaga_id, beneficio)` até 100 bytes. Leituras: `SHOW TABLES/COLUMNS` (em `jc_locais_disponiveis`), `empresas` (endereço principal), `empresa_locais` (+ subconsulta contando vagas que usam o local).
- **Transação:** só no modo com locais (`begin_transaction` → INSERT local opcional → INSERT vaga → `commit`; erro → `rollback`). Requisitos e benefícios são gravados **depois** e fora da transação; o resultado de cada `execute()` não é verificado; itens acima do limite são descartados em silêncio [C].
- **Permissões e validações:** só empresa logada; `csrf_require()` no POST; `empresa_id` vem da sessão; local existente validado como da própria empresa. Navegador: `required` nos três campos obrigatórios + checagem JS no submit (alert); "Outro endereço" exige `vagaLocalCidade` e `vagaLocalEstado` (jcAviso); `data-envio-unico` evita duplo envio.
- **Erros:** mensagens por `?error=` (`$erros_msg`): `missing` "Preencha os campos obrigatórios...", `db` "Erro interno ao salvar a vaga...", `local` "Informe pelo menos a cidade e a UF...". Exceção no INSERT → `error_log("[JovemConnect] Erro ao criar vaga: ...")` + rollback + `error=db`. Sucesso → `jc_log('vaga.criada','info',['vaga'=>id,'local'=>local_trabalho|'vaga'])`. Os dados digitados **não** são preservados após um erro (o redirect volta com o formulário vazio) [C].
- **Interface:** cards: cabeçalho; Informações Básicas (`#titulo`, `#tipo_contrato` com opções `Jovem Aprendiz`, `Estágio`, `CLT`, `PJ`; `#area` com `Administrativo`, `TI`, `Vendas`, `Logística`, `Marketing`, `Recursos Humanos`, `Financeiro`, `Saúde`, `Educação`, `Outro`); Local de trabalho (novo) ou Localização (antigo: `#cep`, `#btnBuscarCep`, `#cidade`, `#estado`); Remuneração (`#salario` texto livre, `#carga_horaria` com `20h/semana`, `25h/semana`, `30h/semana`, `40h/semana`, `A combinar`); Descrição (`#descricao`); Requisitos/Benefícios (`#requisitos`, `#beneficios` + prévia em chips `#reqChips`, `#benChips`); botão "Publicar Vaga Agora"; link "Gerenciar locais de trabalho" → `../empresa/locais.php`. Form `#formVaga` (POST, `novalidate`). Esta página não emite `csrf_js()` (não faz POST via fetch).
- **Funções:**

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos / tabelas / erros | Quem chama |
|---|---|---|---|---|---|
| `renderChips` (JS) | 610 | `inputId` (string), `chipsId` (string) | — | Liga um listener `input` que divide o texto por vírgula e mostra chips (escapa `<` e `>`) | chamada 2× na carga (requisitos, benefícios) |
| handler `#cep` input (JS anônimo) | 549 | evento | — | Máscara `00000-000` | campo CEP (modo antigo) |
| handler `#btnBuscarCep` click (JS anônimo) | 556 | evento | Promise | GET ViaCEP; preenche `#cidade`/`#estado`; `alert` para CEP incompleto, inexistente (`data.erro`) ou falha de rede | botão de busca (modo antigo) |
| handler rádios `local_trabalho` (JS anônimo) | 588 | evento | — | Mostra/habilita `#localOutro` só com "Outro endereço" | change dos rádios |
| handlers submit `#formVaga` (JS anônimos) | 596, 632 | evento | — | Exigem cidade/UF do "outro endereço" e os 3 obrigatórios; `preventDefault` + aviso | submit |

- **Riscos / limitações / observações:** ver Achados (sem edição, sem whitelist, requisitos fora da transação, dados perdidos no erro).

---

### `Vagas/minhas.php`

- **Tipo:** ponto de entrada, página GET + POST (remoção com PRG). PHP + HTML + JS.
- **Responsabilidade:** lista as vagas da empresa logada com contagem de candidatos e permite **remover** (DELETE real) uma vaga. Mostra avisos de "publicada" e "removida".
- **Quando executa / quem usa:** redirect de `Vagas/criar.php` (`minhas.php?publicada=1`); links em `dashboard/empresa.php` (navbar), `Vagas/candidatos.php` (navbar e destino padrão de "voltar"), `includes/header.php` linha ~123 (empresa), próprio form `#formDelete`.
- **Depende de:** `includes/csrf.php`, `includes/config.php`, `includes/assistente.php`.
- **Entradas:** SESSION `empresa_id`; POST `action=delete`, `vaga_id` (`intval`), `csrf_token`; GET `deleted`, `publicada` (só `isset`).
- **Saídas:** HTML; POST → sempre `minhas.php?deleted=1` (mesmo que nada tenha sido removido); sem sessão → `../empresa/login.php`.
- **Banco:**
  - `DELETE FROM vagas WHERE id = ? AND empresa_id = ?` (prepared). Pelas FKs do `schema.sql`, apaga em cascata: `requisitos_vaga`, `beneficios_vaga`, `candidaturas` (e, através delas, `mensagens_chat`), `favoritos`, `avaliacoes_ia`. `empresa_locais` **não** é afetada (a FK `fk_vaga_local` é da vaga para o local, `ON DELETE SET NULL` no sentido inverso).
  - `SELECT v.id, titulo, descricao, cidade, estado, salario, carga_horaria, area, tipo_contrato, criado_em, COUNT(c.id) AS total_candidatos FROM vagas v LEFT JOIN candidaturas c ... WHERE v.empresa_id = ? GROUP BY v.id ORDER BY v.criado_em DESC` (prepared; sem paginação).
- **Permissões e validações:** só empresa; ownership no próprio `WHERE empresa_id = ?`; `csrf_require()`. Log: `jc_log('vaga.removida','info')` se `affected_rows > 0`, senão `jc_log('vaga.remocao_recusada','aviso')`.
- **Erros:** vaga de outra empresa/inexistente → nada é apagado, log "remocao_recusada", mas o usuário vê "Vaga removida com sucesso." [C]. Erro de banco → handler global 500.
- **Edição/encerramento:** **não existem** [C]. Comentário nas linhas 205–207 registra que o botão "Editar" foi removido porque `criar.php` ignora `?editar=`; não há `UPDATE vagas` de conteúdo em nenhum arquivo (grep: os únicos `UPDATE vagas` estão em `includes/locais.php` e só sincronizam cidade/UF/CEP). A tabela `vagas` não tem coluna de status/ativa/encerrada.
- **Interface:** navbar; toast `#toast` (deleted/publicada); cabeçalho com contagem e "Criar Nova Vaga"; um card por vaga (título, área, tipo, cidade/UF, salário, carga, "Publicada em dd/mm/aaaa", contador de candidatos, 180 caracteres da descrição); ações: "Ver Candidatos (N)" → `candidatos.php?vaga=X&voltar=minhas`, "Remover" → `confirmarDelete(id, título)`. Modal `#modalDelete` (`role=dialog`) com form `#formDelete` (POST `minhas.php`, hidden `action=delete`, `vaga_id` `#deleteVagaId`, `data-envio-unico`), botões Cancelar/"Remover Permanentemente", fecha por ×, clique fora e Esc. Estado vazio "Nenhuma vaga publicada ainda".
- **Funções:**

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos / tabelas / erros | Quem chama |
|---|---|---|---|---|---|
| `applyTheme` (JS) | 290 | `t` (string) | — | Tema | carga, `#btnTheme` |
| `confirmarDelete` (JS) | 320 | `vagaId` (int), `titulo` (string) | — | Guarda o elemento de origem, preenche `#deleteVagaId` e a mensagem, abre o modal e foca "Cancelar" | `onclick` de "Remover" |
| `fecharModal` (JS) | 329 | — | — | Esconde o modal e devolve o foco | ×, Cancelar, clique fora, Esc |
| toast (JS anônimo) | 301 | — | — | Mostra o toast com `role=status` 4 s | carga |

- **Riscos / limitações / observações:** remoção irreversível e sem soft delete; apaga histórico de candidaturas dos jovens; aviso de sucesso mesmo quando nada foi removido; `onclick="confirmarDelete(..., '<?= htmlspecialchars(addslashes(titulo)) ?>')"` — `addslashes` não escapa quebra de linha; um título com `\n` (só possível via POST manipulado) quebraria o JS do botão [I].

---

### `Vagas/candidatar.php`

- **Tipo:** ponto de entrada, GET (confirmação/resultado) + POST (candidatura) com PRG. PHP + HTML.
- **Responsabilidade:** mostra o resumo da vaga e o botão "Confirmar Candidatura"; no POST valida a vaga, evita duplicidade e grava `candidaturas` com status `Recebido`; depois redireciona para a mesma página com `result=ok|duplicate|error`.
- **Quando executa / quem usa:** links em `Vagas/listar.php` (detalhes: `candidatar.php?vaga=X[&voltar=..]`), `Vagas/favoritos.php` (`candidatar.php?vaga=X&voltar=favoritos`); o próprio form; `includes/assistente.php` (contexto `vagas/candidatar.php`).
- **Depende de:** `includes/csrf.php`, `includes/config.php`, `includes/assistente.php`.
- **Entradas:** SESSION `jovem_id`; GET `vaga` ou `vaga_id` (`intval`), `result` (texto livre; só `ok`, `duplicate`, `error` têm tela própria), `voltar`; POST `vaga_id` (`intval`), `voltar`, `csrf_token`. `voltar` (POST tem prioridade sobre GET) ∈ {`painel`,`favoritos`,`candidaturas`}, senão vazio.
- **Saídas / redirecionamentos:** sem sessão → `../jovem/login.php`; POST com `vaga_id <= 0` ou vaga inexistente → `../dashboard/jovem.php`; duplicada → `candidatar.php?vaga_id=X&result=duplicate[&voltar=..]`; sucesso → `...&result=ok`; falha → `...&result=error`; GET sem vaga válida ou vaga inexistente → `../dashboard/jovem.php`.
- **Banco (prepared):** `SELECT id FROM vagas WHERE id=?`; `SELECT id FROM candidaturas WHERE vaga_id=? AND jovem_id=?` (no POST e no GET); `INSERT INTO candidaturas (vaga_id, jovem_id, status) VALUES (?, ?, 'Recebido')`; GET: `SELECT v.id, titulo, descricao, cidade, estado, salario, carga_horaria, area, tipo_contrato, e.nome_empresa FROM vagas v JOIN empresas e ...`.
  - A coluna `etapa` não é informada; pelo `schema.sql` ela recebe o DEFAULT `'candidatado'` (se o ALTER tiver sido aplicado) [C schema / I banco real].
- **Duplicidade:** checagem prévia por SELECT + `UNIQUE KEY uq_candidatura (vaga_id, jovem_id)`; `mysqli_sql_exception` com código 1062 → `result=duplicate`. Outras exceções → `jc_log_excecao($e,'candidatura.erro')` + `jc_log('candidatura.erro','erro')` + `result=error`. Sucesso → `jc_log('candidatura.criada','info',['vaga'=>id])`.
- **Permissões e validações:** só jovem; `jovem_id` da sessão; CSRF no POST; o jovem não precisa ter perfil completo, currículo ou verificação para se candidatar (nenhuma checagem no arquivo) [C].
- **Interface:** barra com seta → `listar.php?id=X[&voltar=]`; estados: `ok` (card verde, "Status atual: Recebido", links "Ver outras vagas" → painel e "Minhas Candidaturas"), `duplicate` (amarelo), `error` (vermelho, "Tentar novamente" → `candidatar.php?vaga=X`), padrão (resumo da vaga + form POST `candidatar.php` com hidden `vaga_id`, `voltar`, `csrf_token`, `data-envio-unico`, botões Cancelar/Confirmar). GET de quem já é candidato sem `result` → força a tela `duplicate`.
- **Funções:** nenhuma função nomeada (só o script de tema inline).
- **Riscos / observações:** `?result=ok` digitado manualmente mostra "Candidatura Enviada!" sem ter enviado nada (só visual) [C]; o redirect de sucesso não passa pelos detalhes da vaga.

---

### `Vagas/candidatos.php`

- **Tipo:** ponto de entrada, GET (lista) + POST AJAX (JSON) + POST convencional (fallback). PHP + HTML + JS.
- **Responsabilidade:** mostra os candidatos de **uma vaga da empresa logada** e permite trocar o status de cada candidatura; oferece "Ver Perfil" e "Match com IA" por candidato.
- **Quando executa / quem usa:** `Vagas/minhas.php` (`candidatos.php?vaga=X&voltar=minhas`), `dashboard/empresa.php` (`../Vagas/candidatos.php?id=X&voltar=painel`), `empresa/perfil.php` linha ~393 (`?id=X&voltar=perfil`), `jovem/perfil.php` linhas ~421 e ~973 (`candidatos.php?vaga=X`), `Vagas/avaliar.php` linha ~109; fetch do próprio arquivo.
- **Depende de:** `includes/csrf.php`, `includes/config.php`, `includes/assistente.php`; AJAX para `../IA/avaliar_ajax.php`.
- **Entradas:** SESSION `empresa_id`; GET `vaga` ou `id` (`intval`), `voltar` ∈ {`painel`,`minhas`,`perfil`} (padrão `minhas.php`), `saved`; POST `candidatura_id` (`intval`), `status` (`trim`, precisa estar em `$statuses_validos`), `csrf_token` ou header `X-CSRF-Token`; header `X-Requested-With: XMLHttpRequest` decide o modo AJAX.
- **Valores de status permitidos [C]:** `Recebido`, `Visualizado`, `Em análise`, `Aprovado`, `Reprovado` (comparação estrita). A coluna é `VARCHAR(30)` sem CHECK no banco.
- **Saídas:**
  - AJAX: `Content-Type: application/json`. 400 `{"error":"Dados inválidos."}`; 403 `{"error":"Sem permissão ou candidatura não encontrada."}`; 200 `{"ok":true,"status":"..."}`; CSRF inválido → 403 JSON.
  - Fallback: redirect `candidatos.php?vaga=X&saved=1` (perde `voltar`), mesmo se o status for inválido ou nada mudou.
  - GET: `vaga <= 0` ou vaga que não é da empresa → `../empresa/perfil.php`; sem sessão → `../empresa/login.php`.
- **Banco (prepared):** ownership `SELECT id, titulo, area, cidade, estado, salario, carga_horaria FROM vagas WHERE id=? AND empresa_id=?`; troca de status `UPDATE candidaturas c JOIN vagas v ON c.vaga_id=v.id SET c.status=? WHERE c.id=? AND v.empresa_id=?`; se 0 linhas afetadas (mesmo status), reconfirma ownership com `SELECT c.id ... WHERE c.id=? AND v.empresa_id=?`; lista `SELECT j.id, nome, email, cidade, estado, area_interesse, escolaridade_tipo, escolaridade_curso, resumo_profissional, TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) AS idade, c.id, c.status, c.criado_em FROM candidaturas c JOIN jovens j ... WHERE c.vaga_id=? ORDER BY c.criado_em DESC` (sem paginação). **Não lê nem grava `candidaturas.etapa`.**
- **Permissões e validações:** só empresa; a vaga precisa ser da empresa (checada **antes** do POST, então o POST também exige `?vaga=` da própria empresa); o UPDATE repete o filtro `v.empresa_id = ?` (impede alterar candidatura de outra vaga/empresa mesmo com `candidatura_id` forjado). Lista todos os candidatos da vaga independentemente de `perfil_publico`.
- **Erros / logs:** AJAX ok → `jc_log('candidatura.status')`; sem permissão → `jc_log_seguranca('acesso.negado','aviso',['recurso'=>'candidatura',...])` + 403. Fallback → `jc_log('candidatura.status' | 'candidatura.status_sem_mudanca')`.
- **Interface:** navbar; toast "Status atualizado com sucesso!" (`saved`); cabeçalho com link de volta, área, título, nº de candidatos, cidade/UF, salário, botão "Nova Vaga"; grid `#gridCandidatos` com `.candidate-card#card-<cand_id>`: avatar (inicial), nome, idade • cidade/UF, badge `#badge-<cand_id>` (classe por `statusClass`), "Técnico em &lt;curso&gt;" ou área de interesse, resumo (120 caracteres) ou frase padrão, "Inscrito em dd/mm/aaaa", `<select id="sel-<cand_id>">` com os 5 status + botão "Salvar" (`atualizarStatus`), "Ver Perfil" → `../jovem/perfil.php?id=<jovem>&voltar=candidatos&vaga=<vaga>`, "Match com IA" (`avaliarMatch(jovem, vaga, btn)`). `<noscript>`: form POST `candidatos.php?vaga=X` com hidden `status="Em análise"` fixo e botão "Salvar (sem JS)". Estado vazio "Nenhum candidato ainda". Emite `csrf_js()`.
- **Funções:**

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos / tabelas / erros | Quem chama |
|---|---|---|---|---|---|
| `statusClass` (PHP) | 539 | `string $status` | `string` | Mapa status → classe do badge (`badge-success`, `badge-danger`, `badge-warning`, `badge-info`, `''`) | render dos cards (linha ~270) |
| `applyTheme` (JS) | 390 | `t` | — | Tema | carga, `#btnTheme` |
| toast (JS anônimo) | 402 | — | — | Exibe toast | carga |
| `badgeClass` (JS) | 415 | `status` (string) | string | Espelho JS de `statusClass` | `atualizarStatus` |
| `atualizarStatus` (JS) | 426 | `candId` (int), `btn` | Promise | POST `FormData{candidatura_id, status}` para `candidatos.php?vaga=X` com `X-Requested-With`; sucesso → atualiza badge, botão "Salvo" 2 s; erro → "Não salvo" + `jcAviso(data.error ou genérica)` | botão "Salvar" |
| `avaliarMatch` (JS) | 483 | `jovemId`, `vagaId`, `btn` | Promise | POST JSON `{action:'match', jovem_id, vaga_id, empresa_id}` para `../IA/avaliar_ajax.php` (o servidor ignora `empresa_id` do corpo, usa a sessão e checa dono da vaga + `jc_empresa_pode_ver_candidato`); mostra "Match: N%", `title` = análise e link `avaliar.php?vaga_id=X&jovem_id=Y` | botão "Match com IA" |

- **Riscos / observações:** fallback sem JS só consegue gravar "Em análise" [C]; `j.email` é lido mas não exibido (dado desnecessário) [C]; status "Visualizado" nunca é aplicado automaticamente (nenhum arquivo grava esse valor fora deste select — grep) [C].

---

### `Vagas/favoritar.php`

- **Tipo:** endpoint POST (AJAX JSON ou form convencional). PHP.
- **Responsabilidade:** alterna (toggle) uma vaga nos favoritos do jovem: remove se já existe, insere se não existe.
- **Quando executa / quem usa:** fetch em `Vagas/listar.php` (`toggleFavorito`) e `Vagas/favoritos.php` (`desfavoritar`); form `<noscript>` em `Vagas/favoritos.php`.
- **Depende de:** `includes/csrf.php`, `includes/config.php`.
- **Entradas:** SESSION `jovem_id`; método POST; POST `vaga_id` (`intval`), `csrf_token` (form) ou header `X-CSRF-Token` (AJAX); header `X-Requested-With: XMLHttpRequest` define `$is_ajax`.
- **Saídas:**
  - AJAX: 401 `{"error":"Não autenticado."}`; 405 `{"error":"Método não permitido."}`; 403 CSRF JSON; 400 `{"error":"vaga_id inválido."}`; 404 `{"error":"Vaga não encontrada."}`; 500 `{"error":"Erro interno ao atualizar favoritos."}`; 200 `{"action":"added"|"removed","favorito":bool,"vaga_id":int}`.
  - Sem AJAX: sem sessão → `../jovem/login.php`; não-POST, `vaga_id` inválido ou vaga inexistente → `../dashboard/jovem.php`; falha → `../dashboard/jovem.php?erro=favorito`; sucesso → `listar.php?id=X&favorito=added|removed`.
- **Banco (prepared):** `SELECT id FROM vagas WHERE id=?`; `SELECT id FROM favoritos WHERE jovem_id=? AND vaga_id=?`; `DELETE FROM favoritos WHERE jovem_id=? AND vaga_id=?` ou `INSERT INTO favoritos (jovem_id, vaga_id) VALUES (?,?)`. UNIQUE `uq_favorito (jovem_id, vaga_id)`.
- **Permissões:** só jovem; `jovem_id` da sessão; CSRF. Ordem: auth → método → config → CSRF → validação.
- **Erros:** `error_log("[JovemConnect] Erro ao atualizar favoritos: ...")`. Em PHP 8.1+ o mysqli lança exceção, então uma falha real (ex.: dois cliques simultâneos violando a UNIQUE) cai no handler global (500 JSON "Erro interno...") antes do `if (!$ok)` [I: comentário de `includes/config.php` linhas 21–26 e de `candidatar.php` linha 83].
- **Funções:**

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos / tabelas / erros | Quem chama |
|---|---|---|---|---|---|
| `jsonError` | 17 | `int $code`, `string $msg` | `never` | Define código HTTP, header JSON, imprime `{"error":msg}` e encerra | ramos de erro AJAX |
| `jsonOk` | 24 | `array $data` | `never` | Header JSON, imprime `$data`, encerra | sucesso AJAX |

- **Riscos / observações:** toggle (não idempotente): um reenvio desfaz a ação; `?favorito=` do redirect não é lido em `listar.php` e `?erro=favorito` não é lido em `dashboard/jovem.php` (o usuário sem JS não recebe aviso) [C]. Tipo de retorno `never` exige PHP ≥ 8.1.

---

### `Vagas/favoritos.php`

- **Tipo:** página GET. PHP + HTML + JS.
- **Responsabilidade:** lista as vagas favoritas do jovem e permite candidatar-se, avaliar com IA e remover dos favoritos (AJAX com fallback).
- **Quando executa / quem usa:** links em `dashboard/jovem.php`, `Vagas/listar.php`, `includes/header.php` (linha ~120), navbar desta página; `includes/assistente.php`.
- **Depende de:** `includes/csrf.php`, `includes/config.php`, `includes/assistente.php`; fetch para `favoritar.php`.
- **Entradas:** SESSION `jovem_id`; GET `favorito` (só `=== 'removed'` mostra toast).
- **Saídas:** HTML; sem sessão → `../jovem/login.php`.
- **Banco (prepared):** `SELECT v.id, titulo, descricao, cidade, estado, salario, carga_horaria, area, tipo_contrato, e.nome_empresa, f.id AS fav_id, f.criado_em AS favoritado_em FROM favoritos f JOIN vagas v JOIN empresas e WHERE f.jovem_id=? ORDER BY f.criado_em DESC` (sem paginação).
- **Interface:** navbar; toast "Vaga removida dos favoritos." (só se `?favorito=removed` — nenhum fluxo atual redireciona para cá com esse parâmetro); link "Voltar ao painel"; "Ver todas as vagas" → `../dashboard/jovem.php`; grid `#gridFavoritos` com `.job-card#card-fav-X`: título → `listar.php?id=X&voltar=favoritos`, área, empresa, salário, carga, local, 130 caracteres da descrição, "Salvo em dd/mm/aaaa", "Candidatar-me" → `candidatar.php?vaga=X&voltar=favoritos`, "Avaliar IA" → `avaliar.php?vaga_id=X`, "Remover dos Favoritos" (`desfavoritar`), `<noscript>` form POST `favoritar.php` (hidden `vaga_id`, `csrf_token`). Estado vazio "Nenhuma vaga salva ainda". Emite `csrf_js()`.
- **Funções:**

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos / tabelas / erros | Quem chama |
|---|---|---|---|---|---|
| `applyTheme` (JS) | 239 | `t` | — | Tema | carga, `#btnTheme` |
| toast (JS anônimo) | 251 | — | — | Exibe toast | carga |
| `desfavoritar` (JS) | 264 | `vagaId` (int), `btn` | Promise | POST `FormData{vaga_id}` para `favoritar.php` com `X-Requested-With`; `action==='removed'` → anima e remove o card; se o grid ficar vazio, injeta o estado vazio; `action==='added'` (toggle inverso) → só restaura o botão (o card continua, agora favorito de novo); erro → `jcAviso` genérico | botão "Remover dos Favoritos" |

- **Riscos / observações:** fallback sem JS redireciona para os detalhes da vaga (`listar.php?id=X&favorito=removed`), não volta para a lista de favoritos [C].

---

### `candidaturas/minhas.php`

- **Tipo:** página GET. PHP + HTML (usa `includes/header.php` e `includes/footer.php`).
- **Responsabilidade:** lista as candidaturas do jovem logado com o status de cada uma e link para os detalhes da vaga.
- **Quando executa / quem usa:** links em `dashboard/jovem.php`, `Vagas/listar.php` (navbar e "Já candidatado · ver status"), `Vagas/favoritos.php`, `Vagas/candidatar.php` (telas ok/duplicate), `includes/header.php` (linha ~119).
- **Depende de:** `includes/session.php` (`jc_session_start()` — **não** inclui `csrf.php`), `includes/config.php`, `includes/header.php`, `includes/footer.php` (que inclui `includes/assistente.php`).
- **Entradas:** SESSION `jovem_id`. Nenhum parâmetro GET/POST.
- **Saídas:** HTML; sem sessão → `../jovem/login.php` (checado antes de qualquer saída).
- **Banco (prepared):** `SELECT v.id, titulo, descricao, salario, tipo_contrato, cidade, estado, e.nome_empresa, c.id, c.status, c.criado_em FROM candidaturas c JOIN vagas v JOIN empresas e WHERE c.jovem_id=? ORDER BY c.criado_em DESC` (sem paginação). Não lê `etapa`.
- **Permissões:** só jovem; somente as próprias candidaturas.
- **Interface:** "Voltar ao painel"; título; grid de `.job-card`: título, badge de status (`statusBadge`), empresa, salário, tipo de contrato, cidade/UF, 130 caracteres da descrição, "Candidatado em dd/mm/aaaa", "Ver Detalhes" → `../Vagas/listar.php?id=X&voltar=candidaturas`. Estado vazio "Nenhuma candidatura ainda" → painel. Não há ação de cancelar/retirar candidatura (nenhum DELETE de candidaturas no projeto — confirmado: o único DELETE envolvendo candidaturas é a cascata da remoção da vaga/jovem).
- **Funções:**

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos / tabelas / erros | Quem chama |
|---|---|---|---|---|---|
| `statusBadge` | 48 | `string $status` | `string` (HTML) | Normaliza (`mb_strtolower(trim)`) e devolve `<span class="badge ...">rótulo</span>`; status desconhecido → rótulo escapado com `htmlspecialchars` | loop de render (linha ~82) |

- **Riscos / observações:** comentário da linha 23 diz "sem carga_horaria" mas a tabela `vagas` tem `carga_horaria` (v3.00) — ver Achados.

---

### `dashboard/jovem.php`

- **Tipo:** página GET (painel do jovem). PHP + HTML + JS.
- **Responsabilidade (v4.56):** painel inicial do jovem. Mostra só as **6 vagas mais recentes** (`$limite_painel = 6`) com o card padrão (`includes/card_vaga.php`) e um botão **"Ver todas as vagas (N)"** para `Vagas/listar.php`, onde ficam a busca, os filtros e a lista completa. Também mostra as notícias fixas.
- **Quando executa / quem usa:** redirects pós-login (`jovem/processar_login.php`, `processar_login.php`, `index.php`, `comecar.php`, `dashboard/index.php` com `?verificado=`), `includes/auth.php`, `jovem/login.php`/`cadastro.php` e `empresa/login.php`/`cadastro.php` (jovem já logado); links de volta em praticamente todas as páginas do jovem; destino de erro de `favoritar.php`/`candidatar.php`.
- **Depende de:** `includes/csrf.php`, `includes/config.php`, `includes/card_vaga.php`, `includes/assistente.php` (com `$assistente_boas_vindas`), `jc_vagas_visiveis_sql()` (vagas visíveis; texto vazio sem a migração da Stage 7); AJAX para `../IA/avaliar_ajax.php` (Match) e `../Vagas/favoritar.php` (estrela).
- **Entradas:** SESSION `jovem_id`, `jovem_nome` (lido em `$jovem_nome`, **não usado**); GET `verificado` (`email`|`telefone`, lido só no JS). O painel não lê mais `?area=`: os filtros ficam na lista completa.
- **Saídas:** HTML; sem sessão → `../jovem/login.php`.
- **Banco [C]:** `SELECT DISTINCT v.area FROM vagas v WHERE … ORDER BY v.area` (`query` fixa, só vagas visíveis); `SELECT COUNT(*) … FROM vagas v JOIN empresas e` (total de vagas visíveis, o mesmo número da lista completa); vagas: `SELECT v.id, titulo, descricao, cidade, estado, salario, carga_horaria, area, tipo_contrato, e.nome_empresa, IF(f.id IS NOT NULL,1,0) AS eh_favorito FROM vagas v JOIN empresas e LEFT JOIN favoritos f ON f.vaga_id = v.id AND f.jovem_id = ? … ORDER BY v.criado_em DESC, v.id DESC LIMIT 6` (prepared; o `jovem_id` vem da sessão).
- **O que mostra [C]:** título **"Vagas Recentes"** e o texto "As 6 vagas mais recentes, de N disponíveis" (ou "N vaga(s) disponível(is)" quando há 6 ou menos); notícias são um array PHP estático (`$noticias`, 3 itens fixos); "Mais notícias em breve" sem link. Não há recomendação por perfil.
- **Interface:** navbar com busca `#searchVagas` — um formulário GET para `../Vagas/listar.php` (campo `busca`), que procura em todas as vagas —, atalhos (candidaturas, favoritos, perfil, sair) e tema. Sidebar: chips "Explorar por Área" (cada um → `../Vagas/listar.php?area=X`) e notícias. Main: grid `#gridVagas` com `jc_card_vaga($vaga, '../Vagas/', 'painel')` (card clicável → `../Vagas/listar.php?id=X&voltar=painel`, estrela de favoritar e botão "Avaliar Match com IA"), botão "Ver todas as vagas"; sem vagas → card de estado vazio. `<title>`: "Meu Painel | JovemConnect".
- **Funções:**

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos / tabelas / erros | Quem chama |
|---|---|---|---|---|---|
| `applyTheme` (JS) | 264 | `t` | — | Tema | carga, `#btnTheme` |
| `avaliarMatch` (JS) | 278 | `vagaId` (int), `btn` | Promise | POST JSON `{vaga_id, jovem_id}` (**sem `action`**; o servidor assume `'match'` por padrão) para `../IA/avaliar_ajax.php`; mostra "Match: N%", `title` = análise, link `../Vagas/avaliar.php?vaga_id=X`; erro → mensagem por 3,5 s | botão do card |
| `toggleFavorito` (JS) | — | `vagaId` (int), `btn` | Promise | Impressa por `jc_card_vaga_script('../Vagas/favoritar.php')` (ver `includes/card_vaga.php`) | estrela do card |
| IIFE `verificado` (JS) | 329 | — | — | Mostra "E-mail/Telefone verificado com sucesso!" e limpa a query | carga |

- **Riscos / observações:** o painel mostra no máximo 6 vagas por decisão de produto; a busca, os filtros e as favoritas completas ficam em `Vagas/listar.php`. A ordem usa `v.id` como desempate, então vagas criadas no mesmo segundo aparecem sempre na mesma ordem [C].

---

### `includes/card_vaga.php` (novo na v4.56)

- **Tipo:** módulo auxiliar (include) com duas funções PHP que imprimem HTML e JS.
- **Responsabilidade:** card de vaga único do jovem. O modelo é o card do painel, com a estrela de favoritar que antes existia só em `Vagas/listar.php`. Assim, o painel e a lista completa não divergem mais.
- **Quem usa [C, grep]:** `dashboard/jovem.php` (`jc_card_vaga($vaga, '../Vagas/', 'painel')` e `jc_card_vaga_script('../Vagas/favoritar.php')`) e `Vagas/listar.php` (`jc_card_vaga($vaga, '')` e `jc_card_vaga_script('favoritar.php')`).
- **Depende de:** nada no PHP. No JS, de `jcIcone()` e `window.jcAviso` (de `includes/assistente.php`), de `csrf_js()` da página (cabeçalho `X-CSRF-Token`) e da função `avaliarMatch()` de cada página, que continua a mesma.
- **Campos esperados em `$vaga`:** `id`, `titulo`, `nome_empresa`, `area`, `salario`, `carga_horaria`, `cidade`, `estado`, `eh_favorito` (0/1).
- **Banco:** nenhum. Favoritar reaproveita `Vagas/favoritar.php` sem mudanças (sessão do jovem, CSRF, vaga visível e `UNIQUE` no banco).

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `jc_card_vaga` | 28 | `array $vaga, string $pasta_vagas, string $voltar = ''` | void (imprime) | Card `.job-card`: o card inteiro abre `{pasta}listar.php?id=X[&voltar=...]`; o título é um link de verdade (teclado); badge de área; estrela `#fav-X` (`aria-pressed`, `title`/`aria-label` "Salvar nos favoritos"/"Remover dos favoritos", `stopPropagation`); empresa, salário, carga, cidade/UF; botão "Avaliar Match com IA" (`stopPropagation`). Tudo escapado com `htmlspecialchars`/`json_encode` | painel e lista |
| `jc_card_vaga_script` | 129 | `string $url_favoritar` | void (imprime `<script>`) | Define `toggleFavorito(vagaId, btn)`: ignora cliques repetidos, POST `FormData{vaga_id}` com `X-Requested-With: XMLHttpRequest`, só confirma se `res.ok` e `data.favorito` for booleano; erro 500 ou queda de conexão → aviso, sem fingir sucesso | painel e lista |

---

### `dashboard/empresa.php`

- **Tipo:** página GET (painel da empresa). PHP + HTML + JS.
- **Responsabilidade:** painel inicial da empresa: seção "Candidatos Compatíveis" (candidatos às vagas da empresa ou, sem candidatos, sugestões por área) com Match IA e "Ver perfil"; seção "Suas Vagas Ativas" com link para gerenciar candidatos.
- **Quando executa / quem usa:** redirects pós-login (`empresa/processar_login.php` linha ~58, `processar_login.php`, `index.php`, `comecar.php`, `dashboard/index.php`), `includes/auth.php`, `jovem/perfil.php` (empresa sem `?id`), `includes/candidato.php` (`jc_candidato_indisponivel`), links de volta das páginas da empresa.
- **Depende de:** `includes/csrf.php`, `includes/config.php` (incluído **antes** da checagem de sessão), `includes/assistente.php`; AJAX para `../IA/avaliar_ajax.php`.
- **Entradas:** SESSION `empresa_id`, `empresa_nome` (fallback do nome); GET `verificado` (JS).
- **Saídas:** HTML; sem sessão → `../empresa/login.php`.
- **Banco (prepared):**
  1. `SELECT nome_empresa, area_atuacao FROM empresas WHERE id=?`.
  2. `SELECT id, titulo, cidade, estado, area, salario, carga_horaria FROM vagas WHERE empresa_id=? ORDER BY criado_em DESC`.
  3. Modo normal: `SELECT j.id, nome, cidade, estado, area_interesse, resumo_profissional, escolaridade_curso, escolaridade_tipo, idade, c.vaga_id AS vaga_id_card, v.titulo AS vaga_titulo_card FROM jovens j JOIN candidaturas c JOIN vagas v WHERE v.empresa_id=? AND j.perfil_publico=1 ORDER BY c.criado_em DESC` — uma linha (card) por candidatura; sem limite.
  4. **Modo fallback** (só se a consulta 3 voltar 0 linhas **e** `area_atuacao` não for vazia): `SELECT ... FROM jovens j WHERE j.area_interesse LIKE ? AND j.perfil_publico=1 ORDER BY j.criado_em DESC LIMIT 12` com `%area_atuacao%`. Sem `area_atuacao` → nenhum card.
- **Lógica de vaga por card [C]:** normal → `vaga_id` da candidatura; fallback com 1 vaga → essa vaga (`$vaga_fallback_unica`); fallback com 2+ vagas → `<select class="select-vaga-avaliar">` por card ("Avaliar para qual vaga?"); fallback com 0 vagas → botão desabilitado ("Publique uma vaga para poder avaliar candidatos.").
- **Botões de Match:** `avaliarMatch(jovemId, vagaId, btn)` ou `avaliarMatchComSeletor(jovemId, btn)` → POST JSON `{action:'match', jovem_id, vaga_id, empresa_id}` para `../IA/avaliar_ajax.php`, que usa `$_SESSION['empresa_id']`, exige que a vaga seja da empresa (403 "Esta vaga não pertence à sua empresa.") e que `jc_empresa_pode_ver_candidato` (candidatura a vaga da empresa **ou** `perfil_publico = 1`) seja verdadeiro (404 "Candidato não encontrado."); grava/atualiza `avaliacoes_ia`.
- **Links "Ver perfil":** `../jovem/perfil.php?id=<jovem>` + `&vaga=<vaga>` só no modo normal (sem `voltar`, então a seta do perfil volta ao painel; o `vaga` é usado em `jovem/perfil.php` linhas ~368–381 para destacar a candidatura). No fallback, só `?id=`; o acesso é permitido por `perfil_publico = 1` (`includes/candidato.php`).
- **Interface:** navbar com `#searchCandidatos` (filtro client-side por `data-nome`), atalhos "Minhas vagas", perfil, sair; título "Candidatos Compatíveis" + frase; grid `#gridCandidatos` (`.candidate-card`: avatar, nome, idade/local, badge de área, curso, bio 130 caracteres, "Candidatura para: &lt;vaga&gt;" ou seletor, "Ver perfil", botão Match). Seção "Suas Vagas Ativas": cards com título, área, salário, carga, local, "Gerenciar Candidatos" → `../Vagas/candidatos.php?id=X&voltar=painel`; botões de criar vaga. Aviso `?verificado=`. Emite `csrf_js()`.
- **Funções:**

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos / tabelas / erros | Quem chama |
|---|---|---|---|---|---|
| `applyTheme` (JS) | 427 | `t` | — | Tema | carga, `#btnTheme` |
| handler `#searchCandidatos` (JS anônimo) | 440 | evento | — | Filtra cards pelo nome | digitação |
| `avaliarMatch` (JS) | 449 | `jovemId`, `vagaId`, `btn` | Promise | Ver "Botões de Match"; sucesso → "Match: N%", `title`=análise, link `../Vagas/avaliar.php?vaga_id=X&jovem_id=Y`; `!res.ok` ou sem `score` → mostra `data.error` 3,5 s; exceção → mensagem genérica | botão normal / `avaliarMatchComSeletor` |
| `avaliarMatchComSeletor` (JS) | 506 | `jovemId`, `btn` | — | Lê o `<select>` do card; sem escolha → foco + classe `input-error` + `jcAviso`; senão chama `avaliarMatch` | botão no modo fallback com 2+ vagas |
| IIFE `verificado` (JS) | 523 | — | — | Aviso de verificação | carga |

- **Riscos / observações:** ver Achados (texto do cabeçalho, `perfil_publico` filtrando candidatos reais, exposição no fallback).

## Módulo D — IA, Match e Assistente

### `IA/recomendar.py`

- **Tipo:** servidor HTTP Flask (Python), processo separado do Apache/PHP, escuta só em `127.0.0.1`. **Porta (v4.55):** tenta `JC_IA_PORTA` (padrão 5000) e, se outro programa estiver nela, a primeira livre de `JC_IA_PORTAS_EXTRAS` (padrão 5001–5010, no máximo 50 portas). A porta escolhida é gravada em `logs/ia_porta.json`, que o PHP lê (`includes/ia_endereco.php`) [C, bloco principal, l. 1038–1090].
- **Responsabilidade:** (1) calcular a nota de compatibilidade jovem↔vaga por regras (`/avaliar`); (2) gerar sugestões de melhoria de currículo por regras (+ opcional SDK antigo) (`/curriculo`); (3) chamar a API REST do Gemini para a análise contextual do Match (`/analise_contextual`) e para o Assistente (`/assistente`), validando/limpando a resposta; (4) health check (`/ping`); (5) log estruturado em `logs/ia.log`.
- **Quando executa / quem usa:** iniciado por `iniciar_ia.bat` (janela "JovemConnect - IA", sempre com `venv\Scripts\python.exe`) ou manualmente (`venv\Scripts\python.exe IA\recomendar.py`). Chamado por PHP via cURL, sempre no endereço devolvido por `jc_ia_url()`:
  - `/avaliar` ← `includes/match.php` → `jc_flask_post('/avaliar', …, 6)` usado em `IA/avaliar_ajax.php` action `match` (par novo).
  - `/curriculo` ← `IA/avaliar_ajax.php` action `curriculo` (cURL próprio, timeout 6 s) e `Vagas/curriculoIA.php` (cURL próprio, timeout 10 s).
  - `/analise_contextual` ← `IA/avaliar_ajax.php` action `contexto` via `jc_flask_post(…, 20)`.
  - `/assistente` ← `IA/assistente_ajax.php` via `jc_flask_post(…, 20)`.
  - `/ping` ← `functions.php` → `flaskOnline()` (função sem chamadores — ver os achados em [SEGURANCA_E_PRIVACIDADE.md](SEGURANCA_E_PRIVACIDADE.md) e [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md)); documentação manual em `docs/ASSISTENTE_E_IA.md`.
- **Depende de:** `flask`, `flask_cors` (importados no topo; obrigatórios). Biblioteca padrão: `os, re, hashlib, json, socket, threading, time, urllib.request/error, logging(.handlers), uuid, datetime`. Opcional: `google.generativeai` (importado só se `GEMINI_API_KEY` estiver em `os.environ`). Serviço externo: `https://generativelanguage.googleapis.com/v1beta` (Gemini REST). Lê o arquivo `.env` da raiz do projeto (não lido nesta documentação).
- **Requisito de versão [C/I]:** anotação `-> str | None` (l. 70) é avaliada na definição da função → exige Python ≥ 3.10 (em 3.9 dá `TypeError` ao importar) [I pela semântica do Python].

#### Configuração

| Item | Origem | Valor/regra | Linha |
|---|---|---|---|
| `GEMINI_KEY` (integração **antiga**) | **somente** `os.environ["GEMINI_API_KEY"]` | Se não vazio: `import google.generativeai`, `genai.configure`, `GenerativeModel("gemini-1.5-flash")`. `ImportError` → mensagem no console e `gemini_model=None` | 34–46 |
| `_ENV_PROJETO` | arquivo `<raiz>/.env` lido por `_ler_env_projeto()` (sem alterar `os.environ`) | linhas `CHAVE=valor`, ignora vazias/`#`, remove aspas simples/duplas externas; erro de leitura → `{}` | 315–336 |
| `_config(nome, padrao)` | `os.environ` → `.env` → padrão | — | 339–340 |
| `CTX_CHAVE` (integração **nova**) | `_config("GEMINI_API_KEY").strip()` | vazio → rotas novas respondem `sem_chave` | 421 |
| `CTX_MODELO` | `_config("GEMINI_MODEL", "gemini-3.5-flash-lite")` | precisa casar `^[a-z0-9][a-z0-9.\-]{1,60}$`, senão volta ao padrão | 422–424 |
| `CTX_TIMEOUT` | `_config("GEMINI_TIMEOUT", "12")` | `float`, limitado a [3, 30] s; inválido → 12 | 425–428 |
| `CTX_API` | fixo `https://generativelanguage.googleapis.com/v1beta` | substituído por `JC_GEMINI_API_BASE` **só** se começar com `http://127.0.0.1:` ou `http://localhost:` (servidor simulado de testes); lido só de `os.environ` | 429–432 |
| `JC_LOG_DIR` | `_config` | pasta de logs; padrão `<raiz>/logs` | 362 |
| `JC_LOG_DIAS` | `_config`, padrão 30 | `int` limitado a [1, 365]; inválido → 30 | 364–367 |
| `FLASK_DEBUG` | só `os.environ` | `"1"` liga o modo debug do Flask (com `app.run(use_reloader=False)`, para não abrir um segundo processo que brigaria pela trava); padrão desligado | 1078 |
| `JC_IA_PORTA` | `_config`, padrão 5000 | porta preferida; inteiro de 1024 a 65535, inválido → padrão | 953 |
| `JC_IA_PORTAS_EXTRAS` | `_config`, padrão `5001-5010` | faixa extra `início-fim`, no máximo 50 portas; inválida → padrão | 953 |
| CORS | `CORS(app, origins=["http://localhost", "http://127.0.0.1", "http://localhost:80"])` | vale para chamadas de navegador; o PHP (cURL) não depende de CORS | 31 |

#### Logs (`logs/ia.log`)
- Logger `jovemconnect.ia`, `TimedRotatingFileHandler(when="midnight", backupCount=_dias, encoding="utf-8", delay=True)`, formato = só a mensagem (uma linha JSON). Sem permissão de escrita → `NullHandler` (servidor segue) [C, l. 358–373].
- `log_ia(evento, nivel, **dados)`: linha `{"ts","nivel"(debug|info|aviso|erro),"componente":"flask","evento"(sanitizado [a-z0-9_.-]),"req","dados"}`; `dados`: até 15 chaves, nome sanitizado (máx. 30), valores não numéricos cortados em 120 caracteres e sem caracteres de controle. Exceções no log são engolidas [C, l. 376–393].
- `@before_request _inicio_requisicao`: aceita `X-Request-Id` se casar `^[0-9a-f]{8,32}$`, senão gera `uuid4().hex[:16]`; guarda `g.t0` [C].
- `@after_request _fim_requisicao`: para toda rota exceto `/ping`, grava `flask.requisicao` (nível `aviso` se status ≥ 400) com `rota` (40 chars), `metodo`, `status`, `ms`; devolve o cabeçalho `X-Request-Id` [C].
- Eventos extras: `gemini.falha` (rota `analise_contextual` com `motivo`; rota `assistente` com `motivo` e às vezes `http`). Mensagens `print(...)` vão para o console da janela do Flask (não para o arquivo) [C].
- Nunca grava textos digitados, prompts, respostas ou a chave (afirmado no comentário l. 346–349; **confirmado** que nenhuma chamada a `log_ia` passa esses campos) [C].

#### Rotas e funções

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos / erros | Quem chama |
|---|---|---|---|---|---|
| `_str` | 51 | `value: object` | `str` | `(value or "").lower().strip()`. **Não** remove acentos. Valor não-string truthy (ex.: número, lista) → `AttributeError` → HTTP 500 [I] | `avaliar()` |
| `analise_estatica` | 56 | `score: int` | `str` | Faixas: ≥80 "Excelente compatibilidade! …"; ≥60 "Boa compatibilidade! …"; ≥40 "Compatibilidade razoável. …"; <40 "Compatibilidade baixa com seu perfil atual. …" | `avaliar()` |
| `analise_gemini` | 70 | `score:int, jovem:dict, vaga:dict` | `str \| None` | SDK antigo (`gemini_model.generate_content(prompt)`); prompt com score, cidades, formação (`tipo` em `curso`), área, contrato; pede "exatamente 2 frases … sem markdown". Qualquer exceção → `print` e `None` | `avaliar()` |
| `avaliar` → **POST `/avaliar`** | 97 | JSON (ver Match abaixo) | JSON `{score, analise, motivos, status:"ok"}`; 400 `{"error":"JSON inválido ou Content-Type incorreto.","score":0}` se corpo não for objeto | Algoritmo determinístico (detalhado na seção "Match") + texto `analise_gemini(...) or analise_estatica(score)` | PHP `jc_flask_post('/avaliar')` |
| `analisar_curriculo` → **POST `/curriculo`** | 206 | JSON `resumo:str`, `habilidades:list`, `formacoes:list` | JSON `{mensagem, sugestoes:[str], status:"ok"}`; 400 `{"error":"JSON inválido.","sugestoes":[]}` | Regras: resumo `<80` caracteres; frases negativas ("não tenho experiência", "sem experiência", "nunca trabalhei", "não sei fazer"); menciona "computador/informática/tecnologia" sem nenhuma de "excel, word, python, canva, photoshop, java, php, figma"; sem habilidades / `<3` habilidades; sem formações. Se `gemini_model` e `resumo`: 1 sugestão Gemini inserida no índice 0 (falha silenciosa). `mensagem` = "Encontramos N ponto(s) para melhorar no seu perfil." ou "Seu perfil está com uma boa base! …". Obs.: `resumo` não-string → `AttributeError` em `.strip()` → 500 [I] | `IA/avaliar_ajax.php` (`curriculo`), `Vagas/curriculoIA.php` |
| `_ler_env_projeto` | 315 | — | `dict` | Lê `.env` (ver Configuração) | módulo (`_ENV_PROJETO`) |
| `_config` | 339 | `nome:str, padrao:str` | `str` | env → .env → padrão | configuração |
| `log_ia` | 376 | `evento:str, nivel:str, **dados` | `None` | Ver Logs | `_fim_requisicao`, `analise_contextual`, `assistente` |
| `_tem_contexto` | 396 | — | `bool` | `has_request_context()` | `log_ia` |
| `_inicio_requisicao` | 404 | — | — | `before_request` (req id, t0) | Flask |
| `_fim_requisicao` | 411 | `resposta` | resposta | `after_request` (log + cabeçalho) | Flask |
| `_ctx_texto` | 488 | `valor, maximo:int` | `str` | Não-string → `""`; remove controles, colapsa espaços, remove `**`, `__`, `##`; corta | `_ctx_lista`, `validar_contexto` |
| `_ctx_lista` | 498 | `valor, quantos, maximo` | `list` | Lista de textos únicos, até `quantos` itens | `validar_contexto` |
| `validar_contexto` | 511 | `obj` | `dict \| None` | Exige dict com `resumo` não vazio (≤600). `confianca` minúscula com "é"→"e", fora de {alta, media, baixa} → `"baixa"`. `pontos_fortes` ≤4×200, `pontos_fracos` ≤4×200, `observacoes` ≤3×240, `categorias` só nomes de `CTX_CATEGORIAS`, explicação ≤280, sem repetir nome. Qualquer campo de nota é descartado (não é copiado) | `analise_contextual` |
| `_ctx_chamar_gemini` | 538 | `dados:dict, com_esquema:bool=True` | `(obj\|None, motivo\|None)` | POST `{CTX_API}/models/{CTX_MODELO}:generateContent`, cabeçalho `x-goog-api-key`. `generationConfig`: `temperature 0.2`, `maxOutputTokens 1024`, `responseMimeType application/json`, `responseSchema=CTX_ESQUEMA` (se `com_esquema`). Lê até 400 000 bytes. Erros: HTTP 400 com esquema e sem menção a chave → **repete uma vez sem esquema**; chave ruim (`API_KEY`/`API key` no corpo do erro) ou 401/403 → `chave_invalida`; 404 → `modelo_indisponivel`; 429 → `limite`; outros → `indisponivel`; `URLError/timeout/OSError` → `timeout` (se "timed out" na mensagem) ou `indisponivel`; JSON fora do formato → `resposta_invalida`. Remove cercas ```` ```json ```` antes do `json.loads` | `analise_contextual` |
| `analise_contextual` → **POST `/analise_contextual`** | 585 | JSON (payload de `jc_match_payload_ia`) | 200 `{"disponivel":true,"analise":{…},"do_cache":bool}` ou 200 `{"disponivel":false,"motivo":…}`; 400 `{"disponivel":false,"motivo":"entrada_invalida"}` se não for dict ou `len(json.dumps(dados)) > 24000` | Sem chave → `sem_chave`. Cache em memória (ver abaixo). Falha → `log_ia("gemini.falha")`. Resposta sem campos obrigatórios → `resposta_invalida` (só `print`) | PHP action `contexto` |
| `_assist_texto` | 664 | `valor, maximo` | `str` | Não-string → `""`; remove controles; `strip()`; corta | `assistente` |
| `_assist_limpar_resposta` | 671 | `texto:str` | `str` | Remove controles, blocos ```` ``` ````, `**`, `__`, `##+`, crase; marcadores `*`/`-` de início de linha viram "• "; colapsa 3+ quebras; >1200 chars → corta na última palavra + "…" | `assistente` |
| `assistente` → **POST `/assistente`** | 683 | JSON `mensagem:str`, `historico:[{papel,texto}]`, `contexto:{pagina,perfil}` | 200 `{"disponivel":true,"resposta":str}` ou 200 `{"disponivel":false,"motivo":…}`; 400 `entrada_invalida` (não-dict, `>12000` chars serializados, ou mensagem vazia) | `mensagem` ≤600; `pagina` ∈ `ASSIST_PAGINAS` senão `"outra"`; `perfil` ∈ {visitante, jovem, empresa} senão `"visitante"`; últimas 6 falas do histórico (cada ≤800), papel `assistente`→`model`, demais→`user`; última parte `"[Contexto: página '<pagina>', pessoa: <perfil>]\n<mensagem>"`. `systemInstruction = ASSIST_INSTRUCOES`, `temperature 0.4`, `maxOutputTokens 512`, timeout `CTX_TIMEOUT`, lê até 200 000 bytes. Motivos de falha iguais aos de `_ctx_chamar_gemini` (sem a repetição sem esquema) + `resposta_vazia` | PHP `IA/assistente_ajax.php` |
| `ping` → **GET `/ping`** | 914 | — | `{"status":"ok","gemini":bool(SDK antigo carregado),"analise_contextual":bool(CTX_CHAVE),"assistente":bool(CTX_CHAVE),"moderacao":bool(CTX_CHAVE),"modelo_contextual":CTX_MODELO,"versao":"3.00","instancia":INSTANCIA}` (`instancia`: identificador aleatório desta execução, desde a v4.55) | Nunca devolve a chave | `includes/ia_endereco.php` (confirma a IA antes de cada uso), `IA/verificar_ambiente.py` (`ping`/`esperar`), `scripts/jovemconnect.php` (`ia-parar`), `functions.php` → `flaskOnline()` (sem uso) |

**Porta, instância única e arquivo de estado (v4.55) [C, l. 930–1090]:** antes de abrir a porta, `_travar_instancia()` trava `logs/ia.lock` pelo sistema operacional (`msvcrt` no Windows, `fcntl` no Linux): uma segunda IA da mesma pasta sai com o código **3**. `_abrir_servidor()` tenta as portas de `portas_candidatas()` com `_ServidorIA` (o `ThreadedWSGIServer` do Werkzeug, o mesmo do `app.run`, com `SO_EXCLUSIVEADDRUSE` no Windows); a porta fica reservada no próprio bind. Sem porta livre → código **4**. `_gravar_estado()` grava `logs/ia_porta.json` (`porta`, `instancia`, `pid`, `iniciado_em`, `versao`) de forma atômica; se não conseguir gravar fora da porta preferida → código **5**. Numa saída normal (Ctrl+C, SIGTERM, Ctrl+Break) `_apagar_estado()` apaga o arquivo, só se ainda for desta execução. Nenhum processo de outro programa é encerrado.

**Cache da análise contextual [C, l. 482–485, 598–618]:** dicionário em memória `_ctx_cache` protegido por `threading.Lock`; chave = SHA-256 do JSON de entrada com `sort_keys=True`; validade `6*3600` s; máximo 256 entradas — ao atingir, remove as 64 (`CTX_CACHE_MAX // 4`) com expiração mais próxima. Perdido ao reiniciar o Flask. Só guarda análises **válidas**.

**`CTX_INSTRUCOES` (instrução de sistema da análise contextual) [C, l. 437–457]** — resumo fiel das 10 regras: usar só os dados recebidos; não dar outra nota/porcentagem nem contradizer a nota; não afirmar nada sobre distância em km, transporte público, rotas, estações, trânsito ou tempo de deslocamento; considerar só o local de trabalho informado; comparar habilidades/experiências/formações com descrição e requisitos de forma qualitativa; dados insuficientes → dizer isso e confiança "baixa"; português do Brasil, frases curtas, sem markdown; em `categorias` só os nomes de `CTX_CATEGORIAS`; escrever para o `leitor` ("jovem" → "você"; "empresa" → sobre o candidato); responder só com o JSON.

**`CTX_ESQUEMA` [C, l. 459–480]:** `OBJECT` com `resumo` (STRING), `pontos_fortes`/`pontos_fracos`/`observacoes` (ARRAY de STRING), `confianca` (STRING enum alta|media|baixa), `categorias` (ARRAY de OBJECT `{nome: enum CTX_CATEGORIAS, explicacao: STRING}`, ambos obrigatórios); obrigatórios no topo: `resumo, pontos_fortes, pontos_fracos, confianca`.
`CTX_CATEGORIAS` = `Localização, Área de interesse, Curso técnico, Escolaridade, Tipo de contrato, Habilidades, Experiência`.

**`ASSIST_INSTRUCOES` [C, l. 633–657]:** identifica-se como assistente virtual de IA (não pessoa); descreve as funcionalidades da plataforma; regras: ≤120 palavras, texto simples, sem markdown/tabelas/links inventados; não acessa conta nem executa ações; não pede dados pessoais e orienta a não compartilhá-los; não inventa vagas, empresas, salários, prazos, leis ou funcionalidades; para Jovem Aprendiz indica gov.br e Lei 10.097/2000; fora de carreira/estudos/plataforma → recusa; ignora pedidos para mudar/revelar regras.
`ASSIST_PAGINAS` = `inicio, cadastro, login, painel, vagas, vaga, candidaturas, favoritos, perfil, candidatos, avaliacao, locais, verificacao, outra`.

- **Banco:** nenhum (o Flask não acessa o MySQL) [C].
- **Permissões e validações:** nenhuma autenticação; proteção é apenas o bind em `127.0.0.1` [C]. Validação de tamanho apenas em `/analise_contextual` (24 000) e `/assistente` (12 000) [C].
- **Riscos / observações:** ver os achados em [SEGURANCA_E_PRIVACIDADE.md](SEGURANCA_E_PRIVACIDADE.md) e [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md) (SDK antigo, sem autenticação, `_str` com não-string, CORS, debug).

---

### `includes/match.php`

- **Tipo:** módulo auxiliar (include) PHP; define funções dentro de `if (!function_exists('jc_match_calcular'))`.
- **Responsabilidade:** cópia em PHP do algoritmo `/avaliar` (fallback e cálculo para a análise detalhada), textos estáticos por faixa, composição da nota por categoria, montagem e validação da análise contextual, cliente HTTP para o Flask (`jc_flask_post`) e carga dos dados do Match no banco.
- **Quando executa / quem usa:** `require_once` em `IA/avaliar_ajax.php` (l. 32) e `IA/assistente_ajax.php` (l. 32, só por `jc_flask_post`) [C, grep].
- **Depende de:** `includes/locais.php` (`jc_local_efetivo`) via `require_once` (l. 22); `includes/ia_endereco.php` (`jc_ia_url`, l. 25, desde a v4.55); opcionalmente `jc_req_id()` e `jc_log()` de `includes/logger.php` (checa `function_exists`); extensão `curl` e `mbstring`; PCRE com `\p{Cased}` (PCRE2 ≥ 10.40, com alternativa).
- **Entradas/Saídas:** apenas por parâmetros de função.
- **Banco (todas com prepared statement):**
  - `jc_match_carregar`: `SELECT cidade, estado, area_interesse, escolaridade_tipo, escolaridade_curso, escolaridade_status, formacao FROM jovens WHERE id=?`; `SELECT titulo, area, tipo_contrato, carga_horaria, descricao FROM vagas WHERE id=?`; + `jc_local_efetivo` (SELECT em `vagas` JOIN `empresas` LEFT JOIN `empresa_locais`, ver módulo de locais).
  - `jc_match_payload_ia`: `SELECT habilidade FROM habilidades_jovem WHERE jovem_id=? ORDER BY id`; `SELECT titulo, periodo FROM experiencias_jovem WHERE jovem_id=? ORDER BY id`; `SELECT titulo, conclusao FROM formacao_jovem WHERE jovem_id=? ORDER BY id`; `SELECT requisito FROM requisitos_vaga WHERE vaga_id=? ORDER BY id`.
- **Permissões:** nenhuma — quem chama deve autorizar (feito em `avaliar_ajax.php`).
- **Erros:** `jc_flask_post` devolve `null` em qualquer falha (sem cURL, JSON não codificável, erro de transporte, HTTP ≠ 200, corpo não-JSON) e registra `flask.falha` em `logs/app.log` com `rota`, `motivo` (`timeout` | `conexao` | `http_<código>`), `ms`.

**Funções**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `jc_py_ws` | 32 | — | `string` | Classe de caracteres de espaço equivalente a `str.isspace()` | `jc_py_str`, `jc_py_split` |
| `jc_py_str` | 37 | `$valor` | `string` | Equivale a `_str()`: `null/false/''/array` → `''`; `mb_strtolower` UTF-8; trim de espaços Unicode. **Sem remoção de acentos** | `jc_match_calcular` |
| `jc_py_split` | 46 | `string` | `array` | `str.split()` do Python | `jc_match_calcular` |
| `jc_py_title` | 51 | `string` | `string` | `str.title()` do Python (para os textos dos motivos) | `jc_match_calcular` |
| `jc_match_pesos` | 70 | — | `array` | `localizacao 25, area 25, tecnico 25, escolaridade 15, contrato 10` | `jc_match_categorias` |
| `jc_match_calcular` | 80 | `array $d` (9 campos) | `['score','soma','pontos'(6 chaves),'situacao','motivos','eh_aprendiz']` | Algoritmo do Match (ver seção Match) | `avaliar_ajax.php` |
| `jc_match_texto_estatico` | 210 | `int` | `string` | Mesmos 4 textos de `analise_estatica()` | `avaliar_ajax.php` |
| `jc_match_rotulo` | 224 | `int` | `string` | ≥80 "Excelente compatibilidade"; ≥60 "Boa compatibilidade"; ≥40 "Compatibilidade razoável"; senão "Compatibilidade baixa" | `avaliar_ajax.php` (`analise`) |
| `jc_match_categorias` | 238 | `array $calc, array $ctx` | lista de `{id,nome,icone,pontos,maximo,percentual,explicacao}` | 5 categorias com pontos reais; `percentual = round(pontos*100/maximo)`; explicações escolhidas pela `situacao`, em 2ª pessoa (`quem=jovem`) ou 3ª (`empresa`). Ícones: `map-pin, briefcase, graduation-cap, file-text, clipboard-list` | `avaliar_ajax.php` (`analise`, `contexto`) |
| `jc_match_ajuste` | 312 | `array $calc, string $quem` | `?array {nome:"Requisito de conclusão", pontos:-10, explicacao}` | Só existe se `pontos['ajuste'] != 0`; se `soma < 0`, acrescenta "Como a soma ficou abaixo de zero, a nota final é 0 (o mínimo)." | idem |
| `jc_match_destaques` | 330 | `array $cats, ?array $ajuste` | `['fortes'=>[], 'reduziram'=>[]]` | Categoria com `pontos >= maximo` → forte; senão "Nome (p de máx): explicação"; ajuste entra em "reduziram" | `analise` |
| `jc_match_categorias_ia` | 349 | — | `array` | Mesma lista de `CTX_CATEGORIAS` | `jc_match_validar_ia` |
| `jc_match_texto_ia` | 355 | `$v, int $max` | `string` | Mesmo saneamento de `_ctx_texto` | `jc_match_validar_ia` |
| `jc_match_validar_ia` | 370 | `$a` | `?array` | Revalida no PHP a análise do Flask com os mesmos limites de `validar_contexto` (defesa em profundidade) | `contexto` |
| `jc_flask_post` | 421 | `string $rota, array $payload, int $timeout` | `?array` | `POST` em `jc_ia_url($rota)` (`includes/ia_endereco.php`: `http://127.0.0.1:<porta confirmada>{rota}`; sem IA confirmada → `null`, o cURL falha na hora e a função devolve `null`), JSON (`JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE`), cabeçalhos `Content-Type: application/json` e `X-Request-Id: jc_req_id()`, `CURLOPT_TIMEOUT=$timeout`, `CURLOPT_CONNECTTIMEOUT=3`. Só aceita HTTP 200 | `avaliar_ajax.php`, `assistente_ajax.php` |
| `jc_match_carregar` | 463 | `mysqli, int $jovem_id, int $vaga_id` | `?array {jovem, vaga, local, entrada}` | Carrega dados e monta `entrada` (os 9 campos do `/avaliar`); `cidade_vaga` = cidade do **local efetivo**; `null` se jovem ou vaga não existir | `avaliar_ajax.php` |
| `jc_match_payload_ia` | 512 | `mysqli, int, int, array $dados, array $calc, array $cats, ?array $ajuste, string $leitor` | `array` | Payload mínimo para o Gemini (ver Fluxo 3) | `contexto` |

- **Riscos:** o comentário diz "A igualdade com o Python é conferida por um teste de paridade" — nenhum arquivo de teste existe na cópia (grep por "paridade" só acha este comentário) [C]. Diferenças sutis possíveis entre `str.lower()` do Python e `mb_strtolower` em caracteres raros [I]. A divergência é registrada em produção (`match.divergencia`).

---

### `includes/ia_endereco.php` (novo na v4.55)

- **Tipo:** módulo auxiliar (include) PHP; funções dentro de `if (!function_exists('jc_ia_url'))`.
- **Responsabilidade:** único lugar em que o PHP decide para onde vão as chamadas à IA. O host é sempre `127.0.0.1`; a porta é a que a IA gravou em `logs/ia_porta.json`, desde que esteja na faixa do `.env` e o `/ping` dela devolva a **mesma `instancia`** do arquivo. Se não, tenta a porta preferida, desde que nela responda uma IA do JovemConnect. Sem IA confirmada → `null` (os consumidores mostram "IA indisponível", como antes). Dados do site nunca vão para um programa desconhecido.
- **Quem usa [C, grep]:** `includes/match.php` (`jc_flask_post`), `IA/avaliar_ajax.php` (`/curriculo`), `Vagas/curriculoIA.php` (`/curriculo`), `admin/ia_moderacao.php` (`/moderacao`), `admin/configuracoes.php` (mostra o endereço atual ou "fora do ar no momento"), `functions.php` (`chamarFlask`, `flaskOnline`).
- **Depende de:** `includes/env.php` (`JC_IA_PORTA`, `JC_IA_PORTAS_EXTRAS`); extensão cURL.
- **Entradas:** só o `.env` e o arquivo `logs/ia_porta.json`. Nada vem da requisição HTTP (`$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE`, `$_REQUEST`).
- **Custo:** um `/ping` local por requisição que usa a IA (cerca de 1 ms medido na réplica).

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `jc_ia_portas` | 26 | — | `array [preferida, início, fim]` | Lê e valida `JC_IA_PORTA` (1024–65535, padrão 5000) e `JC_IA_PORTAS_EXTRAS` (padrão 5001-5010, no máximo 50 portas); inválido → padrão | `jc_ia_base` |
| `jc_ia_ping` | 44 | `int $porta` | `?array` | GET `http://127.0.0.1:<porta>/ping` (conexão 1 s, total 2 s, sem proxy); devolve o JSON só se for HTTP 200 com `status: ok` e `versao` | `jc_ia_base` |
| `jc_ia_base` | 66 | — | `?string` | `http://127.0.0.1:<porta>` confirmada (arquivo + instância, ou a porta preferida), ou `null` | `jc_ia_url`, `admin/configuracoes.php` |
| `jc_ia_url` | 99 | `string $rota` | `?string` | Base + rota, ou `null` (`curl_init(null)` falha com erro 3, tratado como IA fora do ar) | consumidores acima |

---

### `IA/avaliar_ajax.php`

- **Tipo:** endpoint AJAX JSON (POST), PHP.
- **Responsabilidade:** porta única do navegador para o Match: nota (`match`), composição detalhada (`analise`), análise contextual Gemini (`contexto`) e sugestões de currículo (`curriculo`). Faz autenticação, autorização (dono da vaga / relação de recrutamento), cache em `avaliacoes_ia` e fallback para o cálculo PHP.
- **Quem usa [C, grep]:** `fetch('../IA/avaliar_ajax.php')` em `dashboard/jovem.php` (l. 345, sem `action` → `match`), `Vagas/listar.php` (l. 681, `match`), `dashboard/empresa.php` (l. 454, `match`), `Vagas/candidatos.php` (l. 488, `match`), `Vagas/avaliar.php` (l. 338, `analise` e `contexto`), `jovem/perfil.php` (l. 1225, `curriculo`). Todas essas páginas emitem `csrf_js()` (que injeta `X-CSRF-Token` em todo `fetch` POST de mesma origem) [C].
- **Depende de:** `includes/csrf.php` (sessão + CSRF), `includes/config.php` (`$conn`, logger, env), `includes/match.php`, `includes/candidato.php` (`jc_empresa_pode_ver_candidato`). Flask (`/avaliar`, `/analise_contextual`, `/curriculo`).
- **Entradas:** corpo JSON (`php://input`): `action` (padrão `'match'`), `vaga_id` (`intval`), `jovem_id` (`intval`; **só usado quando a sessão é de empresa**), `csrf_token` opcional (alternativa ao cabeçalho). Campos extras enviados pelo front e **ignorados**: `empresa_id` (dashboard/empresa e candidatos), `jovem_id` na sessão de jovem. Sessão: `jovem_id` ou `empresa_id`; `jc_ctx_chamadas` (limite).
- **Ordem das validações [C]:** (1) sem sessão → 401 `{"error":"Não autenticado."}`; (2) método ≠ POST → 405 `{"error":"Método não permitido."}`; (3) `csrf_require(true)` → 403 `{"error":"Sua sessão expirou ou a página está desatualizada. Recarregue a página e tente novamente."}` + `csrf.recusado` em `seguranca.log`; (4) corpo não-array → 400 `{"error":"JSON inválido."}`.
- **Saídas por action:**
  - `match`: `{score, analise, do_cache:true}` (cache válido) | `{score, analise, motivos, do_cache:false, atualizado:true}` (cache desatualizado) | `{score, analise, motivos, do_cache:false, fonte:"flask"|"local"}` (par novo).
  - `analise`: `{score, rotulo, texto, categorias[], ajuste|null, fortes[], reduziram[], local{origem,rotulo,texto,nivel,faltando[]}, limites[3]}`.
  - `contexto`: `{disponivel:true, analise:{resumo,pontos_fortes,pontos_fracos,observacoes,confianca,categorias}}` ou `{disponivel:false}` (sempre HTTP 200, **sem motivo** para o navegador).
  - `curriculo`: `{mensagem, sugestoes}`; 403 `{"error":"Acesso não autorizado."}` se não for jovem; 503 `{"error":"Servidor de IA indisponível."}` se o cURL falhar.
  - Ação desconhecida: 400 `{"error":"Ação '<action>' desconhecida."}`.
- **Erros comuns às actions de Match:** empresa e vaga não é dela → 403 `{"error":"Esta vaga não pertence à sua empresa."}`; empresa sem relação com o jovem (`jovem_id>0`) → 404 `{"error":"Candidato não encontrado."}` + `acesso.negado` (`recurso: match_<action>`); `jovem_id<=0` ou `vaga_id<=0` → 400 `{"error":"IDs inválidos."}`; jovem/vaga inexistente → 404 `{"error":"Jovem ou vaga não encontrados."}`.
- **Banco (prepared statements):** `SELECT id FROM vagas WHERE id=? AND empresa_id=?` (ownership); `SELECT score, analise FROM avaliacoes_ia WHERE jovem_id=? AND vaga_id=?`; `INSERT INTO avaliacoes_ia (jovem_id, vaga_id, score, analise) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE score=?, analise=?` (chave única `uq_avaliacao(jovem_id, vaga_id)` em `schema.sql`); `curriculo`: `SELECT resumo_profissional FROM jovens WHERE id=?`, `SELECT habilidade FROM habilidades_jovem WHERE jovem_id=?`, `SELECT titulo FROM formacao_jovem WHERE jovem_id=?`; + leituras de `jc_match_carregar`/`jc_match_payload_ia`/`jc_empresa_pode_ver_candidato`.
- **Logs:** `match.divergencia` (aviso; `flask`, `php`, `vaga`), `match.calculado` (info; `fonte`, `vaga`, `ms`), `match.contexto` (`disponivel`, `motivo` ∈ ok | motivo do Flask | `resposta_invalida` | `flask_indisponivel` | `limite_sessao`, `cache`, `ms`), `flask.falha` (via `jc_flask_post`), `acesso.negado` (segurança).
- **Limites de sessão:** `contexto` — 20 chamadas por janela de 600 s (`$_SESSION['jc_ctx_chamadas']`, lista de timestamps); a contagem é feita **antes** de chamar o Flask e inclui respostas que vieram do cache do Flask [C]. `session_write_close()` antes de chamar o Flask em `match` (par novo) e `contexto` [C].
- **Interface:** não tem.

**Funções:** só a closure `$guardar(int $score, string $analise)` (l. 127) — `INSERT … ON DUPLICATE KEY UPDATE` em `avaliacoes_ia`. Chamada nas actions `match` e `analise`.

---

### `IA/assistente_ajax.php`

- **Tipo:** endpoint AJAX JSON (POST), PHP. Funciona também para **visitante** (sem login) [C — não há checagem de sessão de usuário].
- **Responsabilidade:** recebe mensagem do chat, saneia, mascara dados pessoais, aplica limites por sessão/IP, chama Flask `/assistente` e informa ao navegador se a resposta é da IA (`fonte:"ia"`) ou se ele deve usar a resposta pronta (`fonte:"local"`).
- **Quem usa:** `includes/assistente.php` (atributo `data-endpoint` = `<raiz>IA/assistente_ajax.php`; `fetch` em `enviarMensagem()`), presente em todas as páginas que incluem o componente [C].
- **Depende de:** `includes/csrf.php`, `includes/env.php`, `includes/logger.php`, `includes/match.php` (`jc_flask_post`), `includes/session.php` (`jc_session_start`, via csrf.php). Flask `/assistente`. Sistema de arquivos temporário (`sys_get_temp_dir()`).
- **Cabeçalhos de resposta:** `Content-Type: application/json; charset=utf-8`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store`.
- **Entradas:** corpo JSON lido com limite de **20 000 bytes** (`file_get_contents(..., 0, 20000)`): `mensagem` (string obrigatória), `historico` (lista de `{papel, texto}`), `pagina` (string `^[a-z]{2,20}$`, senão `"outra"`); cabeçalho `X-CSRF-Token` (o componente o envia explicitamente a partir de `data-token`). Sessão: `jovem_id`/`empresa_id` (só para derivar `perfil`), `jc_assist` (timestamps).
- **Constantes [C, l. 34–39]:** `JC_ASSIST_MSG_MAX=500`, `JC_ASSIST_HIST_ITENS=6`, `JC_ASSIST_HIST_MAX=600`, `JC_ASSIST_LIMITE_MINUTO=8` (sessão), `JC_ASSIST_LIMITE_HORA=60` (sessão), `JC_ASSIST_LIMITE_IP_MIN=40` (por IP, por minuto).
- **Saídas:** 200 `{"fonte":"ia","resposta":<≤1500 chars>,"mascarado":bool}`; 200 `{"fonte":"local","mascarado":bool}`; 429 `{"erro":"Muitas mensagens em pouco tempo. Aguarde um minuto e tente de novo."}`; 400 `{"erro":"Mensagem inválida."}` / `{"erro":"Digite uma mensagem."}` / `{"erro":"Mensagem muito longa (máximo 500 caracteres)."}`; 403 (CSRF, JSON com `error`); 405 `{"erro":"Método não permitido."}`.
- **Banco:** nenhum [C]. O conteúdo da conversa não é gravado em banco nem em log [C — `jc_log` recebe só `fonte`, `motivo`, `ms`, `mascarado`; e o logger proíbe campos `mensagem`/`resposta`].
- **Arquivos:** contador por IP em `<tmp>/jc_assist_<sha256('jc|'+REMOTE_ADDR)>.json` (lista JSON de timestamps do último minuto), com `flock(LOCK_EX)` [C].
- **Logs:** `assistente.limite` (segurança; `escopo` ip|minuto|hora), `assistente.resposta` (app; `fonte`, `motivo`, `ms`, `mascarado`).

**Funções**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `jc_assist_sair` | 41 | `int $codigo, array $corpo` | `never` | Define código HTTP, imprime JSON e encerra | o próprio arquivo |
| `jc_assist_texto` | 58 | `string $t, int $max` | `string` | Remove controles (mantém `\n`/`\r`/tab), colapsa espaços/tabs, trim, corta em `$max` (mb) | mensagem e histórico |
| `jc_assist_mascarar` | 65 | `string $t, bool &$houve` | `string` | Substitui por `[dado removido]`, nesta ordem: e-mail; CNPJ `\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b`; CPF `\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b`; telefone `(?:\+?55\s?)?\(?\b\d{2}\)?\s?9?\d{4}[\s\-]?\d{4}\b`; CEP `\b\d{5}-\d{3}\b`; qualquer número com 8+ dígitos. `$houve=true` se trocou algo | mensagem e cada fala do histórico |

- **Riscos/observações:** a checagem "mensagem muito longa" (>550 caracteres originais) vem **depois** do corte em 500; mensagens de 501–550 caracteres são cortadas silenciosamente [C]. O texto mascarado pode passar de 500 caracteres (o marcador tem 15) — o Flask corta em 600 [C]. Arquivos de contador por IP nunca são apagados (um por IP) [I]. `REMOTE_ADDR` atrás de proxy reverso seria o IP do proxy [I].

---

### `includes/assistente.php`

- **Tipo:** módulo de interface incluído (HTML + CSS por classe + JavaScript), PHP. Protegido contra inclusão dupla por `JC_ASSISTENTE_INCLUIDO`.
- **Responsabilidade:** renderiza o widget do Assistente JovemConnect, o painel de acessibilidade e os botões flutuantes (✦/♿); expõe utilidades globais de interface: `window.jcAviso`, `window.jcModal`, `window.jcIcone`, envio único de formulário (`data-envio-unico`) e modais padronizados (`data-jc-modal-abrir/fechar`), além da trava de Tab em diálogos.
- **Quem usa [C, grep]:** `include` em `index.php`, `comecar.php`, `recuperar_senha.php`, `includes/footer.php`, `dashboard/jovem.php`, `dashboard/empresa.php`, `Vagas/{listar (2×, em ramos diferentes), criar, minhas, favoritos, candidatar, candidatos, avaliar, curriculoIA}.php`, `jovem/{login, cadastro, perfil}.php`, `empresa/{login, cadastro, perfil, locais}.php`.
- **Depende de:** `includes/csrf.php` (`csrf_token()`); classes de `assets/css/style.css` (`chat-*`, `a11y-*`, `fab-*`, `toast*`, `jc-modal`, `html[data-jc-zoom]`, `html[data-high-contrast]`, `jc-sem-rolagem`); endpoint `IA/assistente_ajax.php`.
- **Entradas (PHP):** `$_SERVER['SCRIPT_FILENAME']`/`SCRIPT_NAME` (para `$_assist_raiz` = `''` ou `'../'` e `$_assist_pagina`); variável opcional `$assistente_boas_vindas` (string) definida pela página antes do include.
- **Mapeamento de página (`data-pagina`) [C, l. 47–53]** — primeira chave contida em `<pasta>/<arquivo>` (minúsculo): `/index.php`→inicio, `/cadastro.php`→cadastro, `/login.php`→login, `dashboard/`→painel, `vagas/listar.php|criar.php|minhas.php`→vagas, `vagas/favoritos.php`→favoritos, `candidaturas/`→candidaturas, `vagas/candidatar.php`→vaga, `/perfil.php`→perfil, `vagas/candidatos.php`→candidatos, `vagas/avaliar.php`→avaliacao, `empresa/locais.php`→locais, `verificacoes/`→verificacao; senão `outra`.
- **Saídas:** HTML: `#chatWidget` (`role=dialog`, `data-endpoint`, `data-pagina`, `data-token` = token CSRF), `#chatAviso` ("Assistente virtual com IA: pode cometer erros e não acessa sua conta. Não compartilhe dados pessoais."), `#chatBody` (`aria-live=polite`, saudação via `htmlspecialchars`), `#chatInput` (`maxlength=500`), `#chatSend`; `#a11yPanel` com `#a11yIdioma` (desabilitado, "em breve"), `#a11yFontSize` (range 80–140, passo 5), `#a11yDyslexia`, `#a11yColorFilter` (protanopia/deuteranopia/tritanopia), `#a11yContrast`, `#a11yGrayscale`, `#a11yReset`, `#a11yFechar`; `.fab-espaco`; `#fabStackGlobal` com `#btnChat` e `#btnA11y`.
- **Banco:** nenhum. **Armazenamento local:** `localStorage` chaves `jc-font-size`, `jc-dyslexia`, `jc-color-filter`, `jc-contrast`, `jc-grayscale`; migra uma vez as antigas `jc-fontsize`, `jc-daltonismo`, `jc-contraste`, `jc-cinza` (objeto `LEGADO`) [C].
- **Permissões:** nenhuma (componente público).

**Funções JavaScript (todas dentro de uma IIFE `'use strict'`)**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `ls.get/set/del` | 226 | chave, valor | valor/— | `localStorage` com `try/catch` (modo privado não quebra) | preferências |
| `toggleChat` | 259 | `force?` | — | Abre/fecha o chat, `aria-hidden`/`aria-expanded`; ao abrir fecha o painel a11y e foca o campo | `#btnChat`, `#chatClose`, clique fora, Esc, `data-jc-abrir-assistente` |
| `appendMsg` | 275 | `texto, isUser=false` | `HTMLDivElement` | Cria balão com `textContent` (nunca HTML) | `enviarMensagem` |
| `RESPOSTAS` / `PADRAO` | 290 / 302 | — | — | Respostas prontas (ver Fluxo 4) | `responder` |
| `responder` | 304 | `texto` | `string` | Minúsculas, remove acentos (NFD + faixa U+0300–U+036F), divide em palavras `[a-z0-9]+`; primeira entrada de `RESPOSTAS` (na ordem) em que alguma palavra é igual à chave (chave ≤2 letras, ex. `ia`) ou começa com a chave (≥3 letras); senão `PADRAO` | `enviarMensagem` |
| `mostrarResposta` | 320 | `el, texto, nota` | — | Troca "Digitando…" pelo texto (`textContent`) e acrescenta `<small class="chat-msg-nota">` | `enviarMensagem` |
| `enviarMensagem` (async) | 333 | — | — | Ver Fluxo 4; trava (`enviando`), timeout 25 s via `AbortController`, histórico só em memória | `#chatSend` click; Enter em `#chatInput` (sem Shift e fora de composição IME) |
| `toggleA11y` | 389 | `force?` | — | Abre/fecha o painel; foca o primeiro controle habilitado | `#btnA11y`, `#a11yFechar`, clique fora, Esc |
| `applyFontSize` | 436 | `v` | — | Limita 80–140; ≠100 → `html[data-jc-zoom]` + `--jc-zoom`; 100 → remove | range, preferências, reset |
| `applyDyslexia` | 461 | `on` | — | Define `--font: "OpenDyslexic","Comic Sans MS",cursive` no `<html>` | idem |
| `aplicarFiltros` / `applyColorFilter` / `applyGrayscale` | 492/499/505 | `v`/`on` | — | Combina filtro de daltonismo (`filterMap`) e `grayscale(1)` num único `html.style.filter` | idem |
| `applyContrast` | 529 | `on` | — | `html[data-high-contrast]` | idem |
| handler `#a11yReset` | 543 | — | — | Apaga chaves novas e antigas e reaplica padrões | clique |
| `window.jcAviso` | 558 | `mensagem, tipo` ('sucesso' ou outro = erro) | — | Toast `#jcAviso` (`role=alert` para erro, `status` para sucesso); texto inserido 60 ms depois (para leitor de tela anunciar); some após 7 s (erro) ou 4 s (sucesso); mensagem como texto puro | páginas com AJAX (outros módulos) |
| `abrirModal` / `fecharModal` (`window.jcModal`) | 591/600 | `modal, origem` / `modal` | — | Pilha `modaisAbertos`; `display:flex`; classe `jc-sem-rolagem` no `<html>`; foca `[data-jc-foco-inicial]` ou `.jc-modal-fechar`; ao fechar devolve o foco à origem | delegação de clique; `Vagas/avaliar.php` |
| `window.jcIcone` | 611 | `nome` | `HTMLSpanElement` | `<span class="i i-<nome>" aria-hidden="true">` | dashboards, listar, candidatos, avaliar |
| delegação de clique (modais) | 618 | — | — | `[data-jc-modal-abrir]` abre; `[data-jc-modal-fechar]` fecha (e abre o chat se tiver `data-jc-abrir-assistente`); clique no fundo fecha | — |
| keydown Escape (modais) | 634 | — | — | Fecha o modal do topo; o handler do chat/painel ignora Esc se houver modal aberto | — |
| keydown Tab (trava de foco) | 645 | — | — | Em qualquer `[role=dialog][aria-modal=true]` visível, circula o foco entre o primeiro e o último focável | — |
| submit `data-envio-unico` | 666 | — | — | Se não `defaultPrevented`: marca `data-jc-enviando=1`, troca o texto do botão (`e.submitter` ou primeiro submit) pelo valor do atributo, desabilita no próximo tick; segundo envio é bloqueado | formulários de outros módulos e `Vagas/curriculoIA.php` |
| `pageshow` (bfcache) | 681 | — | — | Se `persisted`, destrava formulários e restaura o HTML original dos botões | — |

- **Riscos/observações:** com a chave `'/index.php'` verificada primeiro, `dashboard/index.php` seria classificado como `inicio`, não `painel` [I — ordem do array]; `Vagas/curriculoIA.php` cai em `outra` [C]. O histórico do chat só recebe pares quando a resposta veio da IA [C].

---

### `Vagas/avaliar.php`

- **Tipo:** página GET (PHP + HTML + JS). Não processa POST.
- **Responsabilidade:** página "Análise de Compatibilidade": valida acesso, mostra cabeçalho da vaga (e do candidato, se empresa), carrega a nota e a composição via AJAX (`action:'analise'`) e, ao abrir o modal "Ver análise detalhada", pede a análise contextual (`action:'contexto'`).
- **Quem linka [C, grep]:** `Vagas/listar.php` (l. 279, 287 e link criado por JS l. 699), `Vagas/favoritos.php` (l. 175), `dashboard/jovem.php` (link JS l. 369), `dashboard/empresa.php` (link JS l. 486, com `jovem_id`), `Vagas/candidatos.php` (link JS l. 517, com `jovem_id`), `jovem/perfil.php` (l. 974, visão da empresa sobre o perfil do candidato, com `jovem_id`). O comentário do cabeçalho diz que empresa/candidatos "poderão linkar no futuro" — já linkam (ver [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md)).
- **Depende de:** `includes/csrf.php`, `includes/config.php`, `includes/locais.php` (`jc_local_efetivo`), `includes/candidato.php`, `includes/assistente.php` (para `jcModal`, `jcIcone` e chat); `IA/avaliar_ajax.php`.
- **Entradas:** GET `vaga_id` (`FILTER_VALIDATE_INT`; inválido → 0); GET `jovem_id` (`FILTER_VALIDATE_INT`; **só para empresa**). Sessão `jovem_id` ou `empresa_id`.
- **Saídas:** HTML. Sem sessão → `Location: ../index.php`. Estados de erro na própria página (card "Não foi possível abrir esta análise"): "Vaga inválida.", "Vaga não encontrada." (inexistente ou de outra empresa — mesma mensagem), "Nenhum candidato foi especificado para esta avaliação.", "Candidato não encontrado." (inexistente ou sem relação; neste caso grava `acesso.negado` com `recurso: analise_match`).
- **Banco (prepared):** jovem: `SELECT id, titulo, cidade, estado, area, tipo_contrato, descricao FROM vagas WHERE id=?`; empresa: mesmo SELECT `AND empresa_id=?` e `SELECT id, nome, cidade, estado, area_interesse FROM jovens WHERE id=?` (após `jc_empresa_pode_ver_candidato`); `jc_local_efetivo`.
- **Permissões:** jovem usa sempre `$_SESSION['jovem_id']` (GET `jovem_id` ignorado); empresa precisa ser dona da vaga e ter relação com o candidato (candidatura em vaga dela ou perfil público — regra de `includes/candidato.php`). Jovem pode abrir a análise de **qualquer** vaga existente (não há filtro de status; a tabela `vagas` em `schema.sql` não tem coluna de status) [C].
- **Interface:** navbar (logo → painel, tema `#btnTheme` com `localStorage['jc-theme']`, perfil, sair); card da vaga (título, área, local efetivo se `nivel ≠ indisponivel`, contrato; dados do candidato para empresa); `section#cardResultado` com estados `#estadoCarregando` ("Calculando compatibilidade..."), `#estadoSucesso` (`#scoreValor`/100, `#scoreBarra`, `#scoreRotulo`, `#analiseTexto`, botão `#btnAnaliseDetalhada`, nota "Nota calculada pelo Match do JovemConnect, com critérios objetivos.") e `#estadoErro` (`#erroTexto`, botão "Tentar novamente" → `carregarAvaliacao()`); modal `#modalAnalise` (`role=dialog aria-modal=true`) com resumo, `#listaCategorias`, `#blocoAjuste`, `#listaFortes`, `#listaReduziram`, local (`#localRotulo`, `#localTexto`, `#localAviso`), `#contextoConteudo` (análise contextual), `#listaLimites`; link "Voltar" (`../Vagas/candidatos.php?vaga=<id>` para empresa, `../Vagas/listar.php?id=<id>` para jovem).
- **Observação:** a folha de estilo é `../assets/css/style.css` (`assets/` minúsculo desde a v4.59 (antes `Assets/`, que no Linux deixava a página sem CSS)); o mesmo em `Vagas/curriculoIA.php`.

**Funções JavaScript**

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `applyTheme` | 303 | `t` | — | Tema claro/escuro | carga, `#btnTheme` |
| `elemento` | 322 | `tag, classe, texto` | elemento | Cria nó com `textContent` | todas abaixo |
| `preencherLista` | 328 | `id, itens, vazio` | — | Preenche `<ul>` ou mostra texto de vazio | `montarModal` |
| `postarMatch` (async) | 337 | `action` | `{ok, data}` | `POST ../IA/avaliar_ajax.php` corpo `{action, vaga_id, jovem_id}` (`MATCH_IDS`); CSRF via `csrf_js()` | `carregarAvaliacao`, `carregarContexto` |
| `carregarAvaliacao` (async) | 347 | — | — | `action:'analise'`; erro HTTP ou `score` não numérico → `data.error` ou "Não foi possível calcular a compatibilidade agora."; exceção de rede → "Não foi possível se conectar ao servidor de análise." | `DOMContentLoaded`, botão "Tentar novamente" |
| `montarModal` | 375 | `d` | — | Preenche categorias (`percentual/100`, "p de máx pontos", explicação), ajuste, fortes ("Nenhum critério com pontuação máxima."), reduziram ("Nenhum critério perdeu pontos."), limites, local (aviso: completo → vazio; parcial → "Endereço incompleto: … não informado(s). Nada foi completado automaticamente."; senão "Sem endereço para a vaga: a localização não pôde ser avaliada."); zera o contexto | `carregarAvaliacao` |
| `carregarContexto` (async) | 425 | — | — | Uma vez por abertura; "Gerando a análise contextual..."; `action:'contexto'`; indisponível → "A análise contextual por IA não está disponível agora. A nota e as categorias acima vêm do Match tradicional e continuam valendo." e libera nova tentativa ao reabrir; sucesso → selo "Gerada por IA (Gemini) · confiança &lt;alta/média/baixa&gt; · não altera a nota", resumo, listas "Pontos fortes", "Pontos de atenção", "Observações"; cada `categorias[i]` cujo nome coincide com uma das 5 categorias do Match vira "Contexto (IA): …" dentro dela; as demais (Habilidades, Experiência) vão para "Outros aspectos" | clique em `#btnAnaliseDetalhada` |

---

### `Vagas/curriculoIA.php`

- **Tipo:** página GET/POST (PHP + HTML), "Mentor de Currículo IA".
- **Responsabilidade:** mostra o resumo profissional do jovem num `<textarea>` editável, envia resumo + habilidades + formações ao Flask `/curriculo` e exibe a mensagem e as sugestões; se o resumo enviado for diferente do salvo, **atualiza** `jovens.resumo_profissional`.
- **Quem usa:** nenhuma página do projeto linka para ela (grep por `curriculoIA` só encontra o próprio arquivo) — acessível só por URL direta [C]. A funcionalidade equivalente do perfil usa `avaliar_ajax.php` action `curriculo`.
- **Depende de:** `includes/csrf.php`, `includes/config.php`, `includes/assistente.php` (envio único `data-envio-unico="Analisando..."`); Flask `/curriculo` (cURL, `CURLOPT_TIMEOUT=10`, `CURLOPT_CONNECTTIMEOUT=3`, sem `X-Request-Id`).
- **Entradas:** sessão `jovem_id` (sem ela → `Location: ../jovem/login.php`); POST `csrf_token` (`csrf_require()` → página 403 em HTML), POST `resumo` (`trim`; **sem limite de tamanho**; se o campo não vier, usa o resumo do banco).
- **Saídas:** HTML; erro "O servidor de IA está indisponível no momento. Tente novamente em alguns minutos." (falha cURL ou HTTP ≠ 200) ou "Resposta inválida do servidor de IA." (JSON não-array); sucesso: `mensagem` e cada sugestão com `htmlspecialchars` + botões "Atualizar Perfil" (`../jovem/perfil.php`) e "Ver Vagas Disponíveis" (`../dashboard/jovem.php`).
- **Banco (prepared):** `SELECT nome, resumo_profissional, area_interesse, escolaridade_tipo, escolaridade_curso FROM jovens WHERE id=?`; `SELECT habilidade FROM habilidades_jovem WHERE jovem_id=? ORDER BY id ASC`; `SELECT titulo, instituicao, conclusao FROM formacao_jovem WHERE jovem_id=? ORDER BY id ASC`; `UPDATE jovens SET resumo_profissional=? WHERE id=?` (só após resposta válida do Flask, resumo não vazio e diferente do salvo).
- **Interface:** barra superior (voltar ao painel, "Ver Meu Perfil"); card com nome, área e contagens; `form#formIA` (POST, mesma página) com `textarea#resumo` e `button#btnAnalisar` ("Analisar com IA"); lista das habilidades que serão incluídas; bloco de erro ou resultado. JS próprio: só aplica tema salvo.
- **Riscos:** o resumo é gravado sem limite de tamanho/saneamento além do `trim` (coluna e limites definidos em outro módulo) [C]; o texto pode ir ao Gemini via SDK antigo se ativo [C].

---

### `functions.php`

- **Tipo:** biblioteca PHP de utilitários (raiz do projeto).
- **Responsabilidade (declarada):** datas, CPF/CNPJ/CEP, texto, `h()`, chamada ao Flask e `/ping`.
- **Quem usa:** **ninguém** — nenhum `require/include` de `functions.php` em todo o projeto; nenhuma das funções abaixo é chamada fora do próprio arquivo (grep por cada nome; o único resultado externo é um comentário em `includes/env.php` l. 7). Os `$h(...)` encontrados em `empresa/locais.php` e `includes/formacao.php` são closures locais, não a função `h()` [C].
- **Depende de:** extensão cURL; `includes/ia_endereco.php` (endereço da IA, desde a v4.55).
- **Banco:** nenhum.

| Nome | Linha | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `calcularIdade` | 27 | `?string $dataNascimento` | `?int` | Idade em anos; vazio, futuro ou inválido → `null` | `calcularIdadeDinamica` (interno) |
| `calcularIdadeDinamica` | 50 | `?string` | `int` | Alias; `null` → 0 | ninguém |
| `apenasNumeros` | 62 | `string` | `string` | Remove não dígitos | funções internas |
| `validarCpf` | 69 | `string` | `bool` | 11 dígitos, rejeita repetidos, dígitos verificadores | ninguém |
| `formatarCpf` | 101 | `string` | `string` | `000.000.000-00` (ou dígitos se ≠ 11) | ninguém |
| `validarCnpj` | 118 | `string` | `bool` | 14 dígitos, rejeita repetidos, DV com pesos cíclicos 2–9 | ninguém |
| `formatarCnpj` | 153 | `string` | `string` | `00.000.000/0000-00` | ninguém |
| `formatarCep` | 172 | `string` | `string` | `00000-000` | ninguém |
| `truncar` | 191 | `string, int=150, string='…'` | `string` | Corte UTF-8 com sufixo | ninguém |
| `h` | 203 | `string` | `string` | `htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8')` | ninguém |
| `chamarFlask` | 229 | `string $rota, array $dados=[], int $timeout=6` | `array\|false` (sem declarar o tipo desde a v4.58, por causa do PHP 7.4) | POST JSON ao Flask em `jc_ia_url($rota)`; falha → `error_log` e `false` | ninguém (substituída na prática por `jc_flask_post`) |
| `flaskOnline` | 265 | — | `bool` | GET `/ping` (em `jc_ia_url`) (timeout 2 s, conexão 1 s); `true` se HTTP 200 | ninguém |

- **Observação:** como o arquivo não é incluído, definir aqui uma função com o mesmo nome de outra do projeto não causa conflito hoje [I]. Validações de CPF/CNPJ efetivamente usadas ficam em outros módulos (não neste arquivo) [I — grep sem chamadores].

---

### `iniciar_ia.bat`

- **Tipo:** script de inicialização Windows (batch, ASCII, terminações CRLF). Reescrito na v4.55.
- **Responsabilidade:** iniciar `IA\recomendar.py` **somente** com o ambiente oficial do projeto, `<pasta do .bat>\venv`, e abrir a plataforma no navegador. Nunca roda a IA com o Python global, nunca adota outro ambiente (`venv-NOMEDOPC`, `.venv`) e nunca apaga nada.
- **Por que mudou:** a versão antiga criava `venv-%COMPUTERNAME%` em cada computador porque o `venv` do projeto aponta para o Python do computador em que foi criado, que não existe nos PCs da escola. Era a origem da pasta "venv SPXX-XXX".
- **Passo a passo [C]:**
  1. Caminhos absolutos a partir da pasta do `.bat` (`RAIZ`, `VENV`, `PY=venv\Scripts\python.exe`, `IA\recomendar.py`, `IA\verificar_ambiente.py`, `IA\requirements-ia.txt`). Limpa `PYTHONHOME`, `PYTHONPATH` e `VIRTUAL_ENV` e liga `PYTHONNOUSERSITE=1`, para o Python não herdar configurações do computador.
  2. **[1/5] Diagnóstico:** computador, pastas e ambiente oficial, na tela e em `logs\iniciar_ia.log` (sem senhas ou dados pessoais). Ambientes não oficiais encontrados (`venv-*`, `.venv`) só são avisados e ignorados.
  3. **[2/5] Arquivos do projeto:** os três arquivos acima precisam existir.
  4. **[3/5] Ambiente oficial:** `verificar_ambiente.py verificar` confere se o `venv` pertence a esta pasta, se o Python é 3.10+ e se as versões fixas de `IA\requirements-ia.txt` estão instaladas.
  5. Se o `venv` estiver ausente ou inválido (por exemplo, copiado de outro PC), o `.bat` **para e explica o motivo**. Só com a confirmação **S** ele reconstrói o `venv` neste computador: renomeia o anterior para `venv.antigo-<data>`, cria um novo com o Python do computador (`py -3` ou `python`, validado por `verificar_ambiente.py base`) e instala `IA\requirements-ia.txt` (precisa de internet uma vez). Com **N**, nada é alterado.
  6. **[4/5] A IA já está rodando?** `verificar_ambiente.py ping`: se a IA desta pasta (ou uma IA do JovemConnect na porta preferida) já responde, ela é reaproveitada e nenhuma outra é aberta.
  7. **[5/5]** Inicia a IA na janela **"JovemConnect - IA"** (`cmd /k`; fechar a janela desliga a IA). Desde a v4.58 a pasta de trabalho dessa janela é o `htdocs` (fora do projeto): uma janela "dentro" da pasta impedia o `parar_jovemconnect.bat` de excluí-la. A IA usa caminhos absolutos, então isso não muda nada para ela e espera até 20 s a IA confirmar a porta (`verificar_ambiente.py esperar 20`: arquivo `logs\ia_porta.json` + `instancia` no `/ping`). Sem resposta → para com diagnóstico.
  8. Abre `http://localhost/<pasta>/index.php` no navegador.
- **Porta:** a 5000 ocupada deixou de ser erro: a própria IA vai para a próxima livre da faixa. O `.bat` não encerra nenhum processo.
- **Não faz:** não define `GEMINI_API_KEY` (a chave vem do `.env`, lido pelo próprio Python); não instala `google-generativeai`; não inicia Apache/MySQL (isso é do `iniciar_jovemconnect.bat`).
- **Testes:** a lógica de `verificar_ambiente.py` foi testada na réplica Linux (12 cenários); o `.bat` em si só pode ser executado no Windows.

---

### `IA/verificar_ambiente.py` (novo na v4.55)

- **Tipo:** utilitário Python de linha de comando usado pelo `iniciar_ia.bat`. Só biblioteca padrão. O código de saída é a resposta (0 = ok).
- **Modos [C, `main`, l. 221]:**

| Modo | O que faz | Saída |
|---|---|---|
| `verificar <venv> <requirements>` | Confere se o Python que roda é o do `venv` informado e pertence a esta pasta, se é 3.10+ e se as versões fixas instaladas batem com o arquivo | 0 ok; outros códigos explicam o motivo (o `.bat` mostra a mensagem) |
| `base` | Confere se o Python usado pode criar o `venv` (não é um `venv`, versão 3.10+) | 0 ok |
| `onde` | Mostra qual Python está rodando (diagnóstico) | — |
| `carimbo` | Data e hora para o nome `venv.antigo-<data>` | texto |
| `ping` | Lê a faixa do `.env` e `logs/ia_porta.json` e consulta o `/ping` | 0 a IA do JovemConnect já responde (reaproveitar); 1 porta preferida livre (iniciar); 2 outro programa na porta preferida (a IA vai para a faixa extra) |
| `esperar <s>` | Espera até `<s>` segundos a IA confirmar a porta (arquivo + mesma `instancia` no `/ping`) | 0 confirmada; 1 sem resposta |

- **Funções:** `log` (40, grava em `logs/iniciar_ia.log` sem dados sensíveis), `norm` (52), `fixadas` (56, lê as versões fixas), `verificar` (76), `base` (110), `_config` (127, lê `JC_IA_*` do ambiente ou do `.env`), `portas` (145, mesmas regras de `ia_endereco.php`), `consultar` (156), `estado` (176), `ping` (193), `esperar` (210), `main` (221).

---

### `requirements.txt`

- **Tipo:** lista de dependências Python (pip) com comentários de ambiente.
- **Conteúdo [C]:** `flask`, `flask-cors`, `mysql-connector-python`, `pandas`, `scikit-learn` (sem versões fixadas). Desde a v4.55, um comentário no topo avisa que o servidor de IA precisa só de `flask` e `flask-cors`, com as versões fixas de `IA/requirements-ia.txt` (o que o `iniciar_ia.bat` instala). Comentários: Apache 2.4+, MySQL 8.0+/MariaDB, PHP 8.0+, extensões mysqli/openssl/session/json, XAMPP 3.3.0+.
- **Observações:** `mysql-connector-python`, `pandas` e `scikit-learn` não são importados em nenhum lugar (grep) [C]. `google-generativeai` (usado opcionalmente) não consta [C]. O comentário "flask-cors — Essencial para o PHP conseguir chamar o Flask" é impreciso: CORS só afeta navegadores; o PHP chama por cURL [I]. "PHP v8.0" é insuficiente: o código usa tipo de retorno `never` (`IA/assistente_ajax.php` → `jc_assist_sair`, `includes/candidato.php`), que exige PHP 8.1 [C/I]. Não menciona a extensão `curl` nem `mbstring`, usadas por este módulo [C]. Não indica a versão mínima do Python (≥ 3.10, ver acima) [I].


### `IA/requirements-ia.txt` (novo na v4.55)

- **Tipo:** lista de dependências do servidor de IA com **versões fixas**, as mesmas do `venv` em que o projeto foi desenvolvido e testado (Python 3.13; mínimo 3.10, exigido pelo `click 8.3`).
- **Conteúdo [C]:** `flask==3.1.3`, `flask-cors==6.0.2`, `werkzeug==3.1.8`, `jinja2==3.1.6`, `itsdangerous==2.2.0`, `click==8.3.3`, `blinker==1.9.0`, `markupsafe==3.0.3`, `colorama==0.4.6` (só no Windows).
- **Quem usa:** `iniciar_ia.bat` (instala só quando você confirma a reconstrução do `venv`; nunca atualiza nada sozinho) e `IA/verificar_ambiente.py verificar` (confere as versões instaladas). Instalação manual: `venv\Scripts\python.exe -m pip install -r IA\requirements-ia.txt`.


### `iniciar_jovemconnect.bat` (novo na v4.56)

- **Tipo:** script Windows (batch, ASCII, CRLF) na raiz do projeto. Diagnóstico na tela e em `logs\iniciar_jovemconnect.log`.
- **Responsabilidade:** subir tudo num computador com XAMPP, na ordem certa, perguntando antes de criar qualquer coisa:
  1. Procura o XAMPP duas pastas acima (`..\..`, o esperado em `C:\xampp\htdocs\<pasta>`) e usa o `php.exe` dele com `scripts/jovemconnect.php`.
  2. `checar`: versão do PHP, extensões, pastas graváveis e se a pasta está dentro do `htdocs`. **Desde a v4.58, nada disso bloqueia sozinho:** se alguma verificação não bater, o `.bat` mostra o motivo e pergunta "Continuar mesmo assim? [S/N]" (N para com o diagnóstico). PHP 7.4 e 8.0 já passam como "modo de compatibilidade"; abaixo do 7.4 a checagem avisa que é antigo demais. Sem `.env`, oferece criá-lo a partir do `.env.example` (`criar-env`, que nunca sobrescreve).
  3. **Apache (porta 80):** se a porta já responde, confere se é um Apache; se não estiver ligado, roda o `apache_start.bat` do XAMPP (com a pasta do XAMPP como pasta de trabalho) e espera até 25 s.
  4. **MySQL (porta 3306):** roda o `mysql_start.bat` se precisar e espera até 40 s.
  5. **Banco:** `banco-status`. Se o banco não existe ou está vazio, pergunta (S/N) se deve importar o `schema.sql` (`banco-instalar`) e depois se deve carregar os dados de demonstração (`banco-seed`). Um banco que já tem tabelas nunca recebe importação por cima.
  6. Chama o `iniciar_ia.bat` (IA e navegador).
- **Não faz:** não encerra processos de outros programas, não altera um banco que já existe e não mexe no Git.

### `parar_jovemconnect.bat` (novo na v4.56)

- **Tipo:** script Windows (batch, ASCII, CRLF) na raiz do projeto, para encerrar o uso num computador emprestado (por exemplo, o da escola).
- **Passo a passo, sempre perguntando:**
  1. Fecha **só a IA desta pasta** (`ia-parar`: confere `pid` e `instancia` de `logs/ia_porta.json` no `/ping` antes de encerrar) e, desde a v4.58, também a janela "JovemConnect - IA" dela.
  2. Pergunta se deve **apagar o banco do XAMPP**. Com S, é preciso digitar o nome do banco (`DB_NAME`; maiúsculas e minúsculas tanto faz desde a v4.58); liga o MySQL se precisar e roda `banco-apagar`, que recusa nomes diferentes do `DB_NAME` e bancos do sistema.
  3. Pergunta se deve **desligar o XAMPP** (`apache_stop.bat` e `mysql_stop.bat`).
  4. Pergunta se deve **apagar a pasta do projeto do `htdocs`**. Só aceita se a pasta estiver dentro de `htdocs` e tiver os arquivos do JovemConnect. Antes, avisa sobre alterações do Git que não foram salvas (commits pendentes ou que não estão no GitHub) e exige digitar o nome da pasta (maiúsculas e minúsculas tanto faz desde a v4.58). A exclusão é feita por uma cópia de `scripts\excluir_pasta.bat` em `%TEMP%`, numa janela "JovemConnect - limpeza", depois que o `.bat` sai (um `.bat` não consegue apagar a pasta em que está).
- **Exclusão da pasta (v4.58):** na v4.56/4.57 ela falhava na escola. As causas encontradas no código e corrigidas: (a) o nome digitado era comparado diferenciando maiúsculas (`jovemconnect` ≠ `JovemConnect`); (b) a janela "JovemConnect - IA" ficava aberta com a pasta do projeto como pasta de trabalho, o que impede o Windows de apagá-la; (c) arquivos somente leitura (objetos do `.git`) não eram tratados e não havia nova tentativa. Não foi possível confirmar qual delas aconteceu na escola, porque o script antigo não guardava diagnóstico.

### `scripts/jovemconnect.php` (novo na v4.56)

- **Tipo:** ajudante de linha de comando dos dois `.bat` acima e, desde a v4.59, dos `.sh` do Linux (o mesmo arquivo; `$WINDOWS` escolhe os comandos de cada sistema, e as mensagens do `checar` dão a dica certa para cada um). Só roda pelo terminal: pelo navegador responde **404** (`PHP_SAPI !== 'cli'`), e a pasta `scripts/` também é bloqueada pelo `.htaccess`. Lê o banco pelo `.env` (mesmas regras de `includes/config.php`) e **nunca mostra a senha**. As mensagens não têm acentos de propósito, porque o console do Windows nem sempre mostra UTF-8.
- **Comandos [C, l. 123–296]:**

| Comando | O que faz | Código de saída |
|---|---|---|
| `checar` | Versão do PHP (8.1+ ok; 7.4–8.0 ok em modo de compatibilidade; abaixo de 7.4 falha), extensões (mysqli, curl, openssl, mbstring, json), `.env`, pastas graváveis, se está no `htdocs` | 0 ok; 20 sem `.env`; 1 algo não bateu (o `.bat` pergunta se continua mesmo assim) |
| `criar-env` | Copia `.env.example` para `.env`, **nunca** sobrescreve | 0 |
| `porta <n>` / `esperar-porta <n> <s>` | `127.0.0.1:<n>` aceita conexão? / espera até `<s>` segundos | 0 aberta; 1 fechada |
| `quem-usa <n>` | Mostra o programa que escuta na porta (Windows: `netstat` + `tasklist`; Linux, desde a v4.59: `ss`, ou `lsof`) | — |
| `apache [porta]` | Quem responde em `http://127.0.0.1/` é um Apache (cabeçalho `Server`)? | 0 sim; 1 não |
| `site <pasta>` | `http://localhost/<pasta>/index.php` é o JovemConnect? | 0 sim; 1 não |
| `protecao <pasta>` *(v4.59)* | Cria um arquivo de teste em `logs/` e em `uploads/curriculos/`, pede cada um pelo Apache e apaga: confere se os `.htaccess` estão valendo (no Linux dependem de `AllowOverride`) | 0 protegidas; 1 alguma aberta |
| `banco-nome` | Imprime o `DB_NAME` | 0 |
| `banco-status` | Situação do banco | 0 instalado; 10 não existe; 11 existe vazio; 2 MySQL inacessível |
| `banco-instalar` | `CREATE DATABASE … utf8mb4_unicode_ci` (se não existe) e importa o `schema.sql` **só se estiver vazio** | 0 ok; 12 recusado (já tem tabelas) |
| `banco-seed` | Importa `database/seed_demo.sql` (não duplica nada) | 0 |
| `banco-apagar <nome>` | `DROP DATABASE`, só se `<nome>` for igual ao `DB_NAME` e não for um banco do sistema (`mysql`, `information_schema`, `performance_schema`, `sys`, `phpmyadmin`, `test`) | 0 ok (ou já não existia); 3 recusado |
| `ia-parar` | Fecha só a IA desta pasta: confere a `instancia` de `logs/ia_porta.json` no `/ping` e, no Windows, se o `pid` é de um Python (`tasklist`) antes do `taskkill`; arquivo velho apontando para outro programa → não encerra nada. Desde a v4.58, se o processo pai do Python for a janela `cmd.exe` "JovemConnect - IA" (`wmic`, ou PowerShell sem ele; título conferido no `tasklist /V`), fecha a janela junto (`taskkill /T`). No Linux (v4.59), antes de encerrar confere em `/proc/<pid>/cmdline` se o processo é mesmo o `recomendar.py` e usa `posix_kill`, ou `kill` sem a extensão `posix` | 0 |

- **Funções internas:** `diz` (45), `conectar` (47), `nome_valido` (57), `tabelas` (61), `importar` (73, `multi_query` do arquivo SQL), `porta_aberta` (99), `pid_pai` (v4.58, pai de um processo no Windows), `tarefa` (v4.58, linha do `tasklist /V` de um pid), `http_get`.
- **Testes:** 26/26 na réplica Linux na v4.56 e de novo na v4.58 (ver [TESTES_RESULTADOS.md](TESTES_RESULTADOS.md#v458--php-74-e-80-e-exclusão-da-pasta)). Este arquivo também não usa nada exclusivo do PHP 8.0+, porque roda com o `php.exe` do XAMPP de cada computador.

### `scripts/excluir_pasta.bat` (novo na v4.58)

- **Tipo:** script Windows (batch, ASCII, CRLF). **Não é para rodar direto:** o `parar_jovemconnect.bat` copia este arquivo para `%TEMP%` e o chama com a pasta do projeto, depois da confirmação.
- **Passo a passo [C]:** repete as proteções (pasta dentro de `htdocs`, com `index.php`, `iniciar_ia.bat` e `scripts\jovemconnect.php`); espera 3 s; até 3 tentativas com `attrib -r -s -h /s /d` (somente leitura e ocultos) e `rmdir /s /q`, com 3 s entre elas. Se algo sobrar, lista o que ficou, sugere o que fechar (janela da IA, editor, terminal) e pergunta "Tentar de novo? [S/N]". No fim, a cópia temporária se apaga.
- **Diagnóstico:** `%TEMP%\jovemconnect_limpeza.log` (fora da pasta, que é apagada).


### Scripts para Linux (novos na v4.59)

Mesmo papel e mesmas regras dos `.bat`, para um computador Linux. Rode com `bash <arquivo>` na pasta do projeto (não precisam de permissão de execução). Fim de linha **LF** (garantido pelo `.gitattributes`, que tem `*.sh text eol=lf`).

| Arquivo | Equivale a | O que faz [C] |
|---|---|---|
| `iniciar_jovemconnect.sh` | `iniciar_jovemconnect.bat` | 6 etapas: (1) `checar` do PHP, com "Continuar mesmo assim?" se algo não bater, e criação do `.env`; (2) oferece liberar a escrita do Apache em `logs/` e `uploads/curriculos/` (`chmod -R a+rwX`; o Apache roda com outro usuário); (3) Apache na porta 80 — se já responde, aceita um Apache ou um servidor que já mostra o JovemConnect; senão liga; (4) MySQL na 3306; (5) banco: instalar `schema.sql` e dados de demonstração só com S, e explica como criar um usuário quando o `root` do MariaDB do sistema não entra sem `sudo`; (6) confere o site e a proteção das pastas (`protecao`); depois chama o `iniciar_ia.sh`. Diagnóstico em `logs/iniciar_jovemconnect.log` |
| `iniciar_ia.sh` | `iniciar_ia.bat` | Só o `venv` oficial (`venv/bin/python`), validado por `IA/verificar_ambiente.py`; venv ausente, de outro PC ou **criado no Windows** (tem `venv/Scripts`) → para, explica e só reconstrói com S, guardando o antigo como `venv.antigo-<data>`. Reaproveita a IA que já está no ar; senão inicia a IA **em segundo plano** (`setsid nohup`, saída em `logs/ia_console.log`, pasta de trabalho fora do projeto) e espera até 20 s a confirmação da porta. Abre o navegador com `xdg-open` se houver interface gráfica (`JC_NAO_ABRIR=1` desliga). Diagnóstico em `logs/iniciar_ia.log` |
| `parar_jovemconnect.sh` | `parar_jovemconnect.bat` + `scripts/excluir_pasta.bat` | Fecha só a IA desta pasta; pergunta se apaga o banco (digitando o nome), se desliga Apache e MySQL (avisa que, nos serviços do sistema, outros sites também param) e se exclui a pasta: só dentro de `htdocs` ou `/var/www`, com os arquivos do JovemConnect, aviso do Git e nome digitado (maiúsculas tanto faz). Todo o código fica em funções, então o script pode apagar a própria pasta; até 3 tentativas e, se sobrar algo (arquivos criados pelo Apache), oferece repetir com `sudo` |
| `scripts/linux_comum.sh` | — | Funções comuns (`log`, `perguntar`, `ajuda`, `servico`), carregadas com `source`. Reconhece o ambiente: **XAMPP para Linux** em `/opt/lampp` (`sudo /opt/lampp/lampp startapache|startmysql|stopapache|stopmysql`, PHP em `/opt/lampp/bin/php`), **serviços do sistema** com systemd (`apache2`/`httpd`; `mariadb`/`mysql`/`mysqld`) ou **manual** (só confere). Variáveis opcionais: `JC_PHP`, `JC_LAMPP` |

- **O que não muda no Windows:** os `.bat`, o `iniciar_ia.bat` e o `excluir_pasta.bat` continuam iguais. O ajudante `scripts/jovemconnect.php` só ganhou ramos para Linux e o comando `protecao`, que os `.bat` não usam.
- **Testes:** ver [TESTES_RESULTADOS.md](TESTES_RESULTADOS.md#v459--linux).

> O algoritmo exato do Match (pesos, cache e fallback) está em [IA_E_MATCH.md](IA_E_MATCH.md).

## Módulo E — Interface pública, componentes compartilhados, CSS e configuração

### `index.php`
- **Tipo:** página pública GET (ponto de entrada), PHP + HTML + JS inline. HTML próprio (não usa `includes/header.php`/`footer.php`).
- **Responsabilidade:** página inicial do visitante: navbar pública, seção hero, seção "Sobre" (`#sobre`) com revelação ao rolar, rodapé com botões que abrem os modais de Termos/Privacidade/Contato e o assistente/painel de acessibilidade. Quem já está logado é redirecionado ao painel. Não abre conexão com o banco [C] (não inclui `config.php`).
- **Quando executa / quem usa:** destino de `logout.php` (`Location: index.php`), `jovem/excluir_conta.php` e `empresa/excluir_conta.php` (`../index.php?conta=excluida`), `includes/auth.php`, `dashboard/index.php`, `Vagas/avaliar.php` e das três páginas de `verificacoes/` quando não há sessão; links "página inicial" de `comecar.php`, `documentos.php` e da navbar pública de `includes/header.php` (`../index.php`, `../index.php#sobre`).
- **Depende de:** `includes/session.php` → `jc_session_start()`; `includes/institucional.php` (modais); `includes/assistente.php` (chat, painel ♿, FABs, `window.jcAviso`, controle de modais). Externos: Google Fonts (via `@import` do `style.css`), links para gov.br e planalto.gov.br (`target="_blank" rel="noopener"`).
- **Entradas:** `$_SESSION['jovem_id']`, `$_SESSION['empresa_id']` (apenas `isset`); `?conta=excluida` lido só no navegador (`URLSearchParams`).
- **Saídas:** HTML; redirecionamentos `302 Location: dashboard/jovem.php` (sessão jovem) ou `dashboard/empresa.php` (sessão empresa) — o jovem tem prioridade se ambas existirem [C ordem dos `if`].
- **Banco:** nenhum.
- **Permissões e validações:** página pública; sem CSRF (não há formulário).
- **Erros:** não há tratamento específico; se `localStorage` lançar exceção (armazenamento bloqueado) a linha `applyTheme(localStorage.getItem('jc-theme') || 'dark')` (≈l.188) interrompe o `<script>` final — ver Achados.
- **Interface:**
  - `<html data-theme="dark">`, `<body class="landing-page">`. CSS: `href="assets/css/style.css"` (minúsculo desde a v4.59). Ícones: `assets/img/marca/jovemconnect-icone-32.png` e `-180.png`.
  - Script no `<head>`: adiciona `revelar-ativo` em `<html>` se houver `IntersectionObserver` e **não** houver `prefers-reduced-motion: reduce`.
  - `nav.navbar#mainNav`: logo (`<picture>` com `jovemconnect-simbolo.png` até 480px e `jovemconnect-horizontal.png` acima), link `#sobre`, link "Entrar" → `jovem/login.php` (estilo inline), `button#btnTheme.btn-theme` (🌙/☀️), "Começar" → `comecar.php`.
  - `section.hero-section`: fundo decorativo `.home-fundo.hero-fundo` com `assets/img/home/pessoa-computador.jpg` (`alt=""`, `aria-hidden`), texto `.hero-text.fade-up`, CTAs "Sou Jovem" → `jovem/cadastro.php`, "Sou Empresa" → `empresa/cadastro.php`.
  - `.scroll-indicator` (chevron decorativo).
  - `section.about-section#sobre`: fundo `.about-fundo` com `assets/img/home/edificio-empresa.jpg` (`loading="lazy"`), título, parágrafo, lista `.about-features`, `.about-links` (3 links externos com `sr-only` "(abre em nova aba)"); itens com `data-revelar` e `style="--ordem:N"` (atraso escalonado).
  - `footer.site-footer`: logo horizontal, tagline, `nav.footer-links` com 3 `<button class="footer-link" data-jc-modal-abrir="modalTermos|modalPrivacidade|modalContato" aria-haspopup="dialog">`.
  - Inclui `includes/institucional.php` e, depois, `includes/assistente.php` com `$assistente_boas_vindas` definido.
  - Aviso pós-exclusão: se `?conta=excluida`, chama `window.jcAviso('Sua conta foi excluída.', 'sucesso')` e limpa a URL com `history.replaceState`.
- **Funções (JS):**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `applyTheme` | 184 | `t` (string `'dark'`/`'light'`) | — | Grava `data-theme` em `<html>` e troca o emoji do `#btnTheme` | carga da página e clique em `#btnTheme` |
| IIFE "revelar" | 197 | — | — | Se `<html>.revelar-ativo`, observa `[data-revelar]` com `IntersectionObserver` (`threshold: 0.01`), adiciona `.revelado` e para de observar | execução imediata |
| listener `scroll` | 207 | — | — | Alterna `.scrolled` em `#mainNav` quando `scrollY > 20` (passivo). **Não há regra `.scrolled` no `style.css`** [C grep] | evento `scroll` |
| listener `click` de `#btnTheme` | 189 | — | — | Alterna tema e grava `localStorage['jc-theme']` | clique |

- **Riscos / limitações:** ver os achados em [SEGURANCA_E_PRIVACIDADE.md](SEGURANCA_E_PRIVACIDADE.md) e [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md) (`localStorage` sem `try`, `.scrolled` sem CSS; o caminho `Assets/` foi corrigido na v4.59).

### `comecar.php`
- **Tipo:** página pública GET, PHP + HTML. HTML próprio.
- **Responsabilidade:** destino do botão "Começar": o visitante escolhe "Sou Jovem" (`jovem/cadastro.php`) ou "Sou Empresa" (`empresa/cadastro.php`). Mesma regra de redirecionamento do `index.php` para quem já está logado.
- **Quando executa / quem usa:** links em `index.php` (navbar), `includes/header.php` (navbar pública, `../comecar.php`), `jovem/cadastro.php` e `empresa/cadastro.php` [C grep `comecar.php`].
- **Depende de:** `includes/session.php` (`jc_session_start`), `includes/assistente.php`. CSS `assets/css/style.css` (minúsculo).
- **Entradas:** `$_SESSION['jovem_id']`, `$_SESSION['empresa_id']`.
- **Saídas:** HTML; `Location: dashboard/jovem.php` ou `dashboard/empresa.php`.
- **Banco:** nenhum. **Permissões:** público.
- **Interface:** `.form-page > .form-container`, logo vertical `jovemconnect-vertical.png` (link para `index.php`), `.form-card` com dois botões `.btn-lg`, link "Entrar" → `jovem/login.php`, "Voltar para a página inicial" (`.back-link`). Script do `<head>` aplica `jc-theme` antes da pintura (sem `try/catch`). Não tem botão de tema próprio.
- **Funções:** apenas IIFE anônima de tema no `<head>` (≈l.29).
- **Riscos:** nenhum específico além do `localStorage` sem `try` (só falha o script do `<head>`, sem efeito colateral grave) [I].

### `documentos.php`
- **Tipo:** página pública GET, PHP + HTML. HTML próprio. Não inicia sessão.
- **Responsabilidade:** exibe Termos de Uso ou Política de Privacidade em página própria (`?doc=termos|privacidade`), com o mesmo texto dos modais. Funciona sem JavaScript; é o `href` dos links do cadastro e o botão "Abrir em página própria" dos modais.
- **Quando executa / quem usa:** `jovem/cadastro.php` (l.460–461) e `empresa/cadastro.php` (l.395–396) com `href="../documentos.php?doc=..."` + `data-jc-modal-abrir` (com JS, `includes/assistente.php` faz `preventDefault` e abre o modal); `includes/institucional.php` (rodapé de cada modal, `target="_blank"`); auto-link "Veja também".
- **Depende de:** `includes/documentos.php` (→ `includes/termos.php`). Não inclui `assistente.php` (sem painel ♿ e sem aplicar preferências de acessibilidade salvas) [C].
- **Entradas:** `$_GET['doc']` — aceito só se `is_string` e for chave de `jc_documentos()`; caso contrário usa `'termos'` (lista branca) [C].
- **Saídas:** HTML; cabeçalho `X-Content-Type-Options: nosniff`. Sempre 200.
- **Banco:** nenhum.
- **Interface:** `main.doc-pagina` com link de volta (`.doc-voltar`), `<h1>` com ícone, selo de versão (`jc_documento_versao()`), `section.card.doc-resumo-caixa` (resumo), `section.doc-completo` (seções com `<h2>`), rodapé "Veja também" + e-mail de contato (só se `JC_CONTATO_EMAIL` for e-mail válido). Tema aplicado no `<head>` com `try/catch`. CSS `assets/css/style.css` (minúsculo desde a v4.59). Sem apple-touch-icon. Regras `@media print` em `style.css` §29 escondem o link de volta.
- **Funções:** nenhuma própria.
- **Riscos:** `$doc['icone']` é impresso sem `htmlspecialchars` (l.29) — valor estático do código, sem risco atual [C].

### `includes/header.php`
- **Tipo:** módulo auxiliar (include) que **abre o documento HTML inteiro** (`<!DOCTYPE>`, `<head>`, `<body>`) e imprime a navbar.
- **Responsabilidade:** escolhe a navbar conforme a sessão: interna (`nav.navbar-inner`) para jovem/empresa ou pública (`nav.navbar`) para visitante; define `<title>` a partir de `$page_title`; aplica o tema salvo antes do primeiro render.
- **Quando executa / quem usa (grep `includes/header.php`):** `candidaturas/minhas.php` (l.19), `verificacoes/escolher_verificacao.php` (l.69), `verificacoes/verificar_email.php` (l.130), `verificacoes/verificar_telefone.php` (l.95). As três páginas de `verificacoes/` **já imprimem seu próprio `<!DOCTYPE html><html><head>…<body>`** antes do include (ver Achados).
- **Depende de:** sessão PHP (chama `session_start()` comum se ainda não houver sessão — **não** usa `jc_session_start()`; todos os usuários atuais iniciam a sessão antes via `session.php`/`csrf.php` [C]). Google Fonts (`<link>` Inter 300–800 + `preconnect`), `../assets/css/style.css` (minúsculo desde a v4.59), ícones de marca.
- **Entradas:** `$_SESSION['jovem_id']`, `$_SESSION['empresa_id']`; variável `$page_title` (opcional, escapada com `htmlspecialchars`).
- **Saídas:** HTML. Variáveis locais `$_hdr_*` são removidas com `unset` no fim (`$page_title` não é removida).
- **Banco:** nenhum.
- **Interface:**
  - Logado: logo → `../dashboard/jovem.php` ou `../dashboard/empresa.php`; **só para jovem**, `form[method=GET][action="../Vagas/listar.php"][role=search]` com `input#searchGlobal[name=busca][type=search]`; `button#btnTheme.icon-btn.btn-theme`; atalhos — jovem: `../candidaturas/minhas.php`, `../Vagas/favoritos.php`, `../jovem/perfil.php`; empresa: `../Vagas/minhas.php`, `../empresa/perfil.php`; "Sair" → `../logout.php` (link GET).
  - Visitante: logo → `../index.php`, links "Sobre" (`../index.php#sobre`) e "Entrar" (`../jovem/login.php`), `#btnTheme`, "Começar" (`../comecar.php`).
  - Todos os caminhos são relativos com `../` — o include só funciona em páginas de **subpasta** [C].
- **Funções:** IIFE de tema no `<head>` (≈l.58), sem `try/catch`.
- **Riscos:** Logout por GET (link simples) [C]; documento duplicado nas páginas de verificação (o caminho `Assets/` foi corrigido na v4.59).

### `includes/footer.php`
- **Tipo:** módulo auxiliar (include) que fecha o documento (`</body></html>`).
- **Responsabilidade:** inclui `includes/assistente.php` (chat, painel ♿, FABs) e o JS de tema (aplicar + alternar em todos os `.btn-theme, #btnTheme`).
- **Quando executa / quem usa:** as mesmas 4 páginas do `header.php` (`candidaturas/minhas.php` l.160; `verificacoes/*` com `include_once`). As páginas de `verificacoes/` imprimem `</body></html>` de novo depois do include [C].
- **Depende de:** `includes/assistente.php`.
- **Entradas:** `localStorage['jc-theme']`.
- **Saídas:** HTML/JS.
- **Funções (JS):**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| `applyTheme` | 40 | `t` (string) | — | Seta `data-theme` e troca emoji de todos os botões `.btn-theme, #btnTheme` | carga e clique |
| listener `click` (por botão) | 49 | — | — | Alterna dark/light e grava `jc-theme` | clique |

- **Riscos / observações:** o comentário do topo diz que o arquivo "contém chat widget, painel de acessibilidade, FABs, JS de chat/acessibilidade/persistência" — hoje só tem o include e o tema (divergência). `localStorage` sem `try`.

### `includes/institucional.php`
- **Tipo:** módulo auxiliar (include) que imprime 3 modais + 1 script.
- **Responsabilidade:** renderiza `#modalTermos`, `#modalPrivacidade` (duas camadas: resumo + "Saiba mais") e `#modalContato`. Guarda de inclusão única via constante `JC_INSTITUCIONAL_INCLUIDO`.
- **Quando executa / quem usa:** `index.php` (l.160), `jovem/cadastro.php` (l.479), `empresa/cadastro.php` (l.414) [C grep].
- **Depende de:** `includes/documentos.php` (conteúdo e `JC_CONTATO_EMAIL`), `includes/termos.php` (versão/data, indireto). Comportamento de abrir/fechar/foco vem de `includes/assistente.php` (`abrirModal`, `fecharModal`, Esc, clique no fundo, prisão do Tab, `data-jc-abrir-assistente`).
- **Entradas:** `$_SERVER['SCRIPT_FILENAME']` para calcular `$_jc_raiz` (`''` se a página está na raiz, senão `'../'`).
- **Saídas:** HTML: para cada documento `div.jc-modal#modal{Termos|Privacidade}[role=dialog][aria-modal=true][aria-labelledby=…Titulo]` > `.jc-modal-caixa.doc-modal` > `.jc-modal-topo` (h2 + `button.jc-modal-fechar[data-jc-modal-fechar]`), `.jc-modal-corpo` (versão, `h3.doc-resumo-titulo`, `ul.doc-resumo`, `button.doc-saiba-mais[aria-expanded][aria-controls][data-jc-expandir]`, `div.doc-completo[hidden]` com seções em `<h3>`), `.jc-modal-rodape` ("Abrir em página própria" → `{raiz}documentos.php?doc=…` em nova aba; "Fechar"). `#modalContato`: e-mail (`mailto:`) se configurado, senão texto alternativo; botões "Enviar e-mail", "Abrir o assistente" (`data-jc-modal-fechar data-jc-abrir-assistente`), "Fechar".
- **Banco:** nenhum.
- **Funções (JS):**

| Nome | Linha aprox. | Parâmetros | Retorno | O que faz | Quem chama |
|---|---|---|---|---|---|
| listener `click` em `document` ("Saiba mais") | 90 | evento | — | Para `[data-jc-expandir]`: alterna `hidden` do alvo, `aria-expanded`, classe `.aberto` (gira o chevron), troca o rótulo ("Mostrar só o resumo" ↔ "Saiba mais: ler o texto completo") e rola até o primeiro `h3/h2` ao abrir | clique |

- **Observações:** o "Saiba mais" é um `<button>` dentro do modal; se o JS de `assistente.php` não carregar, os modais ficam com `display:none` (CSS) e os botões do rodapé do `index.php` não fazem nada (não são links) [I]. No cadastro os gatilhos são `<a href="../documentos.php?...">`, então sem JS abrem a página própria [C].

### `includes/documentos.php`
- **Tipo:** módulo auxiliar (biblioteca de conteúdo estático), PHP.
- **Responsabilidade:** define o texto (minutas) dos Termos de Uso e da Política de Privacidade e funções que geram o HTML de versão, resumo e seções. Define `JC_CONTATO_EMAIL` (fixo no código, sem leitura do `.env`).
- **Quando executa / quem usa:** `includes/institucional.php` (`require_once`) e `documentos.php` (`require_once`).
- **Depende de:** `includes/termos.php` (`JC_TERMOS_VERSAO = '1.0-minuta'`, `JC_TERMOS_DATA = '10/10/2026'`).
- **Entradas/Saídas:** sem entrada do usuário; retorna strings HTML.
- **Banco:** nenhum.
- **Funções:**

| Nome | Linha aprox. | Parâmetros (tipos) | Retorno | O que faz / efeitos | Quem chama |
|---|---|---|---|---|---|
| `jc_documentos` | 30 | — | `array` `['termos'=>…, 'privacidade'=>…]`, cada um `{titulo, icone, resumo: string[], secoes: [[titulo, html], …]}` | Monta o conteúdo; injeta link `mailto:` (escapado) se `JC_CONTATO_EMAIL` for válido. Termos: 5 itens de resumo, 8 seções. Privacidade: 5 itens de resumo, 10 seções | `institucional.php`, `documentos.php` |
| `jc_documento_versao` | 102 | — | `string` HTML (`p.doc-versao` com `.pendente-selo` "Minuta…" e "Versão X · atualizada em Y") | Escapa versão/data | ambos |
| `jc_documento_secoes` | 109 | `array $doc`, `int $nivel = 3` | `string` HTML | Gera `<hN>` (N limitado a 2–4) com título escapado + corpo HTML **não escapado** (estático) | institucional (nível 3), documentos.php (nível 2) |
| `jc_documento_resumo` | 120 | `array $doc` | `string` `<ul class="doc-resumo">` | Itens escapados | ambos |

- **Observações:** as funções ficam dentro de `if (!function_exists('jc_documentos'))` — se outra parte definir `jc_documentos`, as quatro não são definidas [C]. O texto da Privacidade cita Google Fonts, ViaCEP, BrasilAPI, SMTP, Twilio, Gemini e Google Maps e a retenção de logs ~30 dias (coerente com `JC_LOG_DIAS` padrão 30 em `includes/logger.php`).

### `assets/css/style.css`
- **Tipo:** folha de estilo única do design system ("v3.00" no cabeçalho), **3.153 linhas** (não ~3.300), 29 seções numeradas.
- **Quem usa (grep `style.css`):** todas as páginas com HTML: `index.php`, `comecar.php`, `documentos.php`, `recuperar_senha.php`, `dashboard/jovem.php`, `dashboard/empresa.php`, `jovem/{login,cadastro,perfil}.php`, `empresa/{login,cadastro,perfil,locais}.php`, `Vagas/{listar (2×: l.105 e l.382), criar, candidatar, candidatos, minhas, avaliar, favoritos, curriculoIA}.php`, `verificacoes/{escolher_verificacao,verificar_email,verificar_telefone}.php`, `includes/header.php`, `includes/candidato.php` (l.83, HTML de página de erro/indisponível).
  - ~~Caminho com `Assets/` (A maiúsculo)~~: corrigido na v4.59 nos 21 arquivos (index, documentos, header.php, candidato.php, dashboards, jovem/*, empresa/*, Vagas/*). Uma varredura de todas as referências a arquivos e pastas do projeto não encontrou mais nenhuma grafia diferente da do disco.
  - Caminho com **`assets/` (minúsculo)**: comecar.php, recuperar_senha.php, verificacoes/*.
- **Dependência externa:** `@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap')` (l.6) — **toda página que carrega o CSS carrega Google Fonts**.
- **Detalhamento das seções:** ver tabela abaixo.

#### Seções do `style.css`

| § | Linhas | Título | Componentes / classes principais | Responsivo / temas / a11y |
|---|---|---|---|---|
| — | 1–6 | Cabeçalho | `@import` Google Fonts (Inter 300–900) | — |
| 1 | 9–89 | Variáveis do design system | `:root`: `--accent` `#4B8EF1`, `--accent-hover`, `--accent-light`, `--accent-subtle`, `--brand`, `--accent-fill`, `--accent-fill-hover`, `--on-accent` `#0f172a`, superfícies `--bg-body/surface/card/input/hover`, texto `--text-primary/secondary/muted`, `--border`, `--border-focus`, sombras `--shadow-xs..lg`, raios `--radius-xs..full`, `--font` (`'Inter', system-ui, sans-serif`), `--dur` .2s, `--ease` | Tema escuro é o padrão; `[data-theme="light"]` (l.65) redefine superfícies, texto, borda, `--accent` `#105CCB` e sombras |
| 2 | 92–134 | Reset & base | `*` box-sizing/margin 0; `html{scroll-behavior:smooth}`; `body` (fonte 15px, transição de cor); `a`, `img`, `ul/ol` sem marcador, `button`; scrollbar WebKit; autofill | — |
| 3 | 136–152 | Barra de progresso | `.page-progress` (fixa, 3px, z-index 2000), `.page-progress-fill` (degradê) | Usada em `Vagas/curriculoIA`, `candidatar`, `criar`, `listar`, `jovem/perfil` |
| 4 | 154–231 | Navbar pública | `.navbar` (fixa 64px, z-index 900, `backdrop-filter`), `.nav-logo` (+ legados `.logo-box`, `.logo-jovem`, `.logo-connect`), `.nav-links`, `.nav-actions`, `.btn-theme` | — |
| 5 | 233–275 | Navbar interna | `.navbar-inner` (fixa 60px), `.navbar-inner-center` (máx. 460px), `.navbar-inner-right`, `.icon-btn` | — |
| 6 | 277–379 | Botões | `.btn` (outline none; foco por sombra), `.btn-primary` (fundo `--accent-fill`, texto `--on-accent`), `.btn-secondary`, `.btn-accent-outline`, `.btn-ghost`, `.btn-ia`, `.btn-danger`, `.btn-sm`, `.btn-lg`, `.btn-full` | `.btn-danger` é redefinido na §27 |
| 7 | 381–559 | Inputs & formulários | `.form-group`, `.form-label`, `.form-control` (+`:focus`, `:disabled`), `.input-group`/`.input-icon`, `.input-row`/`.btn-icon`, `select.form-control` (seta SVG inline), `.form-row` (2 col.), `.form-row-3`, `.form-check*`, `.form-checks-group`, `input[type=range]`, `.toggle`/`.toggle-track` (switch), `textarea.form-control`, `.form-error` | `.form-row`/`.form-row-3` viram 1 coluna em ≤768px (§22) |
| 8 | 561–584 | Barra de pesquisa | `.search-bar` (+`:focus-within`), `.search-icon` | Ajustes ≤768px na §26 |
| 9 | 586–708 | Cards | `.card` (hover sobe 2px), `.job-card*` (card de vaga), `.candidate-card*` (card de candidato; `.candidate-bio` com `line-clamp: 3`) | `.card:hover` sem transform com movimento reduzido (§29) |
| 10 | 710–769 | Badge / chip | `.badge`, `.badge-area`, `.filter-chip` (+`.active`), `.skill-chip`, `.benefit-chip` | Cor de `.filter-chip` ajustada na §27 |
| 11 | 771–786 | Avatar | `.avatar`, `.avatar-sm/md/lg/xl` | Cor ajustada na §27 |
| 12 | 788–952 | Página inicial (landing) | `.landing-page`, `.hero-section` (grid 2 col. — **sobrescrito por flex na §27**), `.hero-title` (`clamp(36px,4.5vw,58px)`, peso 900), `.hl`, `.hero-desc`, `.hero-cta`, `.scroll-indicator`, `.about-section`, `.about-text`, `.about-features` (marcador de bolinha via `::before`), `.about-links`/`.highlight`, `.site-footer`, `.footer-logo`, `.footer-tagline`, `.footer-links`. Legados sem uso: `.hero-eyebrow`, `.hero-image-wrap`, `.about-img-wrap`, `.footer-link-breve` | Ver §22 e §27 |
| 13 | 954–1052 | Login | `.auth-page`, `.auth-card` (máx. 480px), `.auth-logo`, `.auth-tabs`/`.auth-tab.active` (abas Jovem/Empresa), `.auth-footer-link`, `.forgot-link` | `.auth-card` padding reduzido ≤768px |
| 14 | 1054–1127 | Formulários de cadastro | `.form-page`, `.form-page-logo` (`.logo-inner` legado), `.form-container` (máx. 680px), `.form-card`, `.form-section-header`, `.form-submit-card`, `.form-page-title`, `.form-page-subtitle` | `.form-card` padding reduzido ≤768px |
| 15 | 1129–1255 | Dashboard — layout | `.dashboard-wrap` (flex, padding-top 60px), `.dashboard-sidebar` (300px, `position: sticky; top: 60px`), `.dashboard-main`, `.page-heading`, `.sidebar-card`, `.sidebar-section-title`, `.filter-wrap`, `.news-list`/`.news-item`/`.news-meta`/`.news-tag`/`.news-date`/`.news-title`/`.news-excerpt`/`.news-see-all`, `.items-grid` (`auto-fill, minmax(320px,1fr)`) | Sidebar 260px ≤1024px; **sidebar some (`display:none`) ≤768px**; `.items-grid` 1 col. ≤768px |
| 16 | 1257–1407 | Detalhes da vaga | `.detail-bar*`, `.detail-page` (grid `1fr 300px`), `.detail-main`, `.detail-aside`, `.detail-vaga-*`, `.detail-meta-*`, `.detail-section*`, `.req-grid`/`.req-item`, `.map-card`/`.map-visual`/`.map-info`, `.ia-dica-*` | `1fr 260px` ≤1024px; 1 col. ≤768px; quebra de título/meta na §26 |
| 17 | 1409–1632 | Perfis | `.profile-bar*`, `.profile-page` (grid `300px 1fr`), `.profile-side` (sticky), `.profile-side-*`, `.profile-info-*`, `.profile-main`, `.profile-section*`, `.edu-*`, `.empty-state`, `.skills-wrap`, `.visibility-toggle-item`/`.vt-text`, `.ia-advisor-*`, `.actions-*`, `.pdf-*`, `.job-active-*` | `260px 1fr` ≤1024px; 1 col. e `.profile-side{position:static}` ≤768px |
| 18 | 1634–1698 | FABs flutuantes | `.fab-stack` (fixo, bottom/right 24px, z-index 800), `.fab`, `.fab-ai`, `.fab-a11y`; legado `.user-indicator` (sem uso) | bottom/right 16px ≤480px |
| 19 | 1700–1802 | Chat widget | `.chat-widget` (fixo, bottom 146px, 360px, z-index 700, `display:none` → `.is-open{display:flex}`), `.chat-header*`, `.chat-close`, `.chat-body`, `.chat-msg`, `.chat-footer`, `.chat-input`, `.chat-send`. `@keyframes popIn` (opacidade + translateY(10px) + scale(.97)) | Largura `calc(100vw - 40px)` ≤768px |
| 20 | 1804–1873 | Painel de acessibilidade | `.a11y-panel` (fixo, 330px, `.is-open{display:block}`, `popIn`), `.a11y-header`, `.a11y-reset`, `.a11y-body`, `.a11y-item`, `.a11y-label`, `.a11y-row`, `.a11y-divider` | Largura `calc(100vw - 40px)` ≤768px |
| 21 | 1875–1914 | Utilitários | `.text-accent/muted/secondary/danger`, `.font-bold/semibold`, `.d-flex/grid/none/block`, `.align-center`, `.justify-between`, `.gap-4..24`, `.mt-8..32`, `.mb-8..24`, `.w-full`, `.hidden` (`display:none !important`), `.text-center` | — |
| 22 | 1916–1982 | Responsividade | Blocos `@media (max-width:1024px)`, `(max-width:768px)`, `(max-width:480px)` (detalhes nas linhas de cada seção) — inclui `.hero-title{font-size:32px}`, `.hero-cta` em coluna e `.btn` 100% em ≤480px | Breakpoints principais: **1024 / 768 / 480 px** |
| 23 | 1984–2014 | Animações | `@keyframes fadeSlideUp` (`.fade-up`, .35s), `@keyframes spin` (`.spin`, `.loading-spinner`) | `@media (prefers-reduced-motion: reduce)`: **todas** as animações e transições com duração `.01ms !important` |
| 24 | 2016–2034 | Badge — status | `.badge-success`, `.badge-danger`, `.badge-warning`, `.badge-info` | Cores do tema claro na §26 |
| 25 | 2036–2068 | Toast | `.toast` (fixo, bottom 90px, centralizado, z-index 1500, `white-space: nowrap`), `.toast-success`, `.toast-error`, `.toast-visible` | Quebra linha ≤768px (§26). Usado por `window.jcAviso` (`assistente.php`) |
| 26 | 2070–2205 | Acessibilidade, navegação e celular | Foco visível (`outline 2px var(--accent)`) para `a/button/select/[tabindex]` e `.toggle`; **zoom**: `html[data-jc-zoom] body > :not(.chat-widget):not(.a11y-panel):not(.fab-stack):not(.fab-espaco):not(.toast):not(script){zoom:var(--jc-zoom,1)}`; **alto contraste**: `html[data-high-contrast]` (fundo preto, texto branco, `--accent` `#6aa8ff`, `--on-accent` preto) e `html[data-theme="light"][data-high-contrast]` (fundo branco, texto preto, `--accent` `#0a47b8`, `--on-accent` branco); sublinhado de links e foco `3px #ffbf47` no alto contraste; tokens de status do tema claro `--jc-sucesso-texto`, `--jc-erro-texto`, `--jc-aviso-texto`, `--jc-info-texto`; modais `[role=dialog][aria-modal=true]` rolam; `.fab-espaco` (0 → 112px após `.dashboard-wrap` e em ≤768px); quebra em `.detail-*` | `@media (max-width:768px)`: navbar interna compacta, `.fab-espaco` 112px, toast com quebra. `@media (max-width:480px)`: some `.nav-links` e textos do logo na navbar pública, `.icon-btn` 32px |
| 27 | 2207–2741 | Identidade visual, ícones e acabamento | **Ícones**: `.i` (máscara SVG via `--i`, cor = `currentColor`; aviso de licença Lucide ISC + Feather MIT) e **54 classes `.i-*`** (`i-arrow-left`, `i-arrow-right`, `i-chevron-down`, `i-external-link`, `i-log-out`, `i-menu`, `i-x`, `i-plus`, `i-pencil`, `i-trash`, `i-search`, `i-send`, `i-download`, `i-rotate-ccw`, `i-filter`, `i-loader`, `i-user`, `i-users`, `i-building`, `i-briefcase`, `i-graduation-cap`, `i-clipboard-list`, `i-file-text`, `i-list-checks`, `i-star`, `i-star-cheia`, `i-map-pin`, `i-banknote`, `i-clock`, `i-calendar`, `i-gift`, `i-newspaper`, `i-mail`, `i-smartphone`, `i-message`, `i-lock`, `i-key`, `i-shield`, `i-globe`, `i-settings`, `i-check`, `i-check-circle`, `i-x-circle`, `i-alert-triangle`, `i-info`, `i-sparkles`, `i-bot`, `i-lightbulb`, `i-acessibilidade`, `i-type`, `i-tamanho-texto`, `i-palette`, `i-contrast`, `i-eye`); `.icone-destaque` (+`.sucesso/.aviso/.erro/.neutro`); `.sr-only`; `.back-link`, `.link-seta`; **marca** `.marca`, `.marca-nav` (42px; 48px na `.navbar`), `.marca-rodape` (52px), `.marca-compacta` (32px), `.marca-vertical` (128px; 112px no `.form-page-logo`); estados de botão (`:disabled`, `[aria-disabled]`, `.btn[data-jc-texto-original]::before` spinner), novo `.btn-danger`; **alertas** `.alerta`, `.alerta-erro/sucesso/aviso/info`; **modais** `.jc-modal` (fixo, z-index 2000, `display:none`, overlay rolável), `.jc-modal-caixa` (máx. 640px), `.jc-modal-topo` (**sticky**), `.jc-modal-corpo`, `.jc-modal-rodape`, `.jc-modal-fechar`, `.pendente-selo`, `.bloco-pendente`, `html.jc-sem-rolagem{overflow:hidden}`; `.footer-link`; ícones do assistente/painel (`.a11y-header-acoes`, `.a11y-fechar`); **composição da home** (`.hero-section`/`.about-section` em flex com `isolation`, `.home-fundo` absoluto atrás do texto com opacidade .5, véu azul `::before`, degradês `::after`; `.hero-fundo` 64% à direita, `.about-fundo` 56% à esquerda); `.formacao-*`; contraste: `--jc-favorito` (`#facc15`, claro `#b45309`), `.avatar/.news-tag/.ia-advisor-title` em `--accent-light`, ajuste de cinza no escuro para `.filter-chip/.ia-advisor-card/.contagem-candidatos`, `.form-error/.text-danger` `#f87171` no escuro | `@media (forced-colors: active)`: `.i` usa `CanvasText/LinkText/ButtonText`. `@media (max-width:480px)`: marca menor e `.marca-compacta` some. `@media (min-width:1025px) and (max-width:1200px)`: fundos 54%/44%. `@media (max-width:1024px)`: fotos da home viram faixas (`position:relative`, altura `clamp(200px,32vw,320px)`). `@media (max-width:768px)`: paddings da home 24px. Tema claro: opacidade .42 da foto; alto contraste: .28 |
| 28 | 2743–2932 | Ajustes finais, local de trabalho e Match | Locais: `.local-endereco`, `.locais-lista`, `.local-item*`, `.local-opcoes`, `.local-opcao` (usa `:has(input:checked)` / `:has(input:disabled)`), `.local-opcao-texto`, `.local-origem`, `.local-outro`. Match: `.btn-ia.match-resultado`, `.match-card` (`[hidden]{display:none!important}`), `.match-estado`, `.match-eyebrow`, `.match-score` (56px), `.match-score-max`, `.match-barra` (> span com transição de largura), `.match-rotulo`, `.match-texto`, `.match-acoes`, `.match-nota`, `.match-link`, `.match-modal` (720px), `.match-resumo`, `.match-categorias`, `.match-cat-*`, `.match-ajuste`, `.match-colunas` (`auto-fit, minmax(240px,1fr)`), `.match-lista*`, `.match-bloco-ia`, `.match-selo-ia`, `.match-carregando` | Alto contraste: borda da barra = texto. `forced-colors`: barra `Canvas/CanvasText/Highlight`. ≤480px: card e placar menores |
| 29 | 2934–3153 | Cadastro em etapas, perfil do candidato, animações, termos e privacidade, assistente | Etapas: `.etapa-titulo`, `.etapa-descricao`, `.etapa-subtitulo`, `.etapas-topo`, `.etapas-contador`, `.etapas-barra`, `.etapas-lista` (contador CSS, `li.atual`, `li.feita` com ✓), `.etapas-ativo .etapa.etapa-atual` (animação), `.etapas-nav`, `.campo-erro`, `.form-control[aria-invalid="true"]`, `.erros-lista`, `.etapas-revisao`, `.etapas-resumo` (dl em grid), `.etapa-aceite`, `.link-botao`. Animações: `@keyframes jc-entrar`, `jc-fundo-entrar` (overlay do modal .18s), `jc-caixa-entrar` (caixa do modal .22s), `.btn:active` "afunda". **Revelar ao rolar**: `.revelar-ativo [data-revelar]{opacity:0; translateY(18px)}` → `.revelado` com transição .55s e atraso `calc(var(--ordem,0)*80ms)`. Perfil do candidato: `.perfil-candidato*`, `.candidatura-contexto*`, `.candidatura-lista`, `.candidate-card-link`, `.perfil-indisponivel`. Documentos: `.doc-versao`, `.doc-resumo-titulo`, `.doc-resumo`, `.doc-saiba-mais` (+`.aberto` gira o ícone 180°), `.doc-completo` (`scroll-margin-top:72px`), `.doc-modal`, `.doc-contato-email`, `.doc-pagina` (760px), `.doc-voltar`, `.doc-rodape`. Assistente: `.chat-aviso`, `.chat-msg` (`pre-line`), `.chat-msg-nota`, `.chat-msg-digitando` (`@keyframes jc-pulsar`), `.chat-send[disabled]` | ≤480px: lista de etapas só com números, resumo 1 col., botões de navegação flexíveis. `@media (prefers-reduced-motion: reduce)`: revelar sem animação (`opacity:1 !important`), sem transform no `.btn:active` e `.card:hover`, `scroll-behavior:auto`. `@media print`: esconde `.doc-voltar` e remove largura máxima de `.doc-pagina` |

**Resumo transversal do CSS**
- **Breakpoints exatos:** `max-width: 1024px` (§22, §27), `max-width: 768px` (§22, §26, §27), `max-width: 480px` (§22, §26, §27, §28, §29), `min-width: 1025px and max-width: 1200px` (§27). Outros: `prefers-reduced-motion: reduce` (§23, §29), `forced-colors: active` (§27 ×1, §28 ×1), `print` (§29).
- **Temas:** atributo `data-theme` no `<html>`; escuro = `:root` (padrão e valor inicial `data-theme="dark"` no HTML); claro = `[data-theme="light"]` (§1, §26, §27, §29).
- **Alto contraste:** atributo booleano `html[data-high-contrast]` (ligado por `includes/assistente.php` → `applyContrast`), com variante clara `html[data-theme="light"][data-high-contrast]` (§26, §27 `.btn-danger` e `.home-fundo`, §28 `.match-barra`).
- **Zoom de acessibilidade:** `html[data-jc-zoom]` + variável `--jc-zoom` (80–140% ÷ 100), aplicado aos filhos diretos do `<body>` exceto chat, painel, FABs, `.fab-espaco`, toast e `<script>` (§26).
- **Keyframes:** `popIn` (§19), `fadeSlideUp`, `spin` (§23), `jc-entrar`, `jc-fundo-entrar`, `jc-caixa-entrar`, `jc-pulsar` (§29).
- **Camadas (z-index):** `.page-progress` 2000, `.jc-modal` 2000, `.toast` 1500, `.navbar`/`.navbar-inner` 900, `.fab-stack`/`.user-indicator` 800, `.chat-widget`/`.a11y-panel` 700.
- **Regras sobrescritas mais adiante (cascata):** `.hero-section`/`.about-section` (grid §12 → flex §27), `.btn-danger` (§6 → §27), `.filter-chip` cor (§10 → §27), `.hero-title .hl` (duplicada l.820 e l.2729), `.chat-msg` (§19 + §29), `.footer-logo` margem (§12 → §27).

### `.env.example`
- **Tipo:** configuração (modelo), não executado. Comentário aponta `docs/CONFIGURACAO.md`.
- **Carregamento:** PHP — `includes/env.php` → `carregarEnv()` lê `{raiz}/.env` linha a linha, ignora `#`/linhas sem `=`, remove aspas envolventes e chama `putenv()` + `$_ENV[...]` **só se `getenv($chave) === false`** (variável real do sistema tem prioridade). É incluído por `includes/config.php` (l.70), `includes/mailer.php` (l.17), `includes/sms.php` (l.16) e `IA/assistente_ajax.php` (l.30). Python — `IA/recomendar.py` → `_ler_env_projeto()` + `_config(nome, padrao)` = `os.environ.get(nome) or _ENV_PROJETO.get(nome) or padrao` (valor vazio cai no padrão).

| Variável | Lida em (arquivo → símbolo) | Padrão no código | Obrigatória? |
|---|---|---|---|
| `DB_HOST` | `includes/config.php` → `define('DB_HOST', getenv('DB_HOST') ?: 'localhost')` | `localhost` | Não (padrão local) |
| `DB_USER` | `includes/config.php` → `DB_USER` | `root` | Não (padrão local) |
| `DB_PASS` | `includes/config.php` → `DB_PASS` (`getenv !== false ? valor : ''` — aceita vazio) | `''` | Não (padrão vazio) |
| `DB_NAME` | `includes/config.php` → `DB_NAME` | `jovemconnect` | Não |
| `DB_CHARSET` *(não está no `.env.example`; está em `docs/CONFIGURACAO.md`)* | `includes/config.php` → `DB_CHARSET` | `utf8mb4` | Não |
| `SMTP_HOST` | `includes/mailer.php` → `enviarCodigoVerificacao()` `$host` | `''` | **Sim** para enviar e-mail (vazio → `error_log` e `return false`) |
| `SMTP_PORT` | `mailer.php` → `$port` (`(int)`) | `587` | Não |
| `SMTP_USER` | `mailer.php` → `$usuario` | `''` | **Sim** para e-mail |
| `SMTP_PASS` | `mailer.php` → `$senha` | `''` | **Sim** para e-mail |
| `SMTP_SECURE` | `mailer.php` → `$seguranca` (`ssl` → SMTPS; `none` → sem TLS e `SMTPAutoTLS=false`; outro → STARTTLS) | `tls` | Não |
| `SMTP_FROM_EMAIL` | `mailer.php` → `$deEmail` | valor de `SMTP_USER` | Não |
| `SMTP_FROM_NAME` | `mailer.php` → `$deNome` | `JovemConnect` | Não |
| `TWILIO_SID` | `includes/sms.php` → `enviarCodigoSMS()` `$sid` | `''` | **Sim** para SMS (vazio → `error_log` e `false`) |
| `TWILIO_AUTH_TOKEN` | `sms.php` → `$token` | `''` | **Sim** para SMS |
| `TWILIO_FROM_NUMBER` | `sms.php` → `$numeroDe` | `''` | **Sim** para SMS |
| `GEMINI_API_KEY` | `IA/recomendar.py` → (a) l.34 `GEMINI_KEY = os.environ.get(...)` (**só ambiente real, não lê `.env`**; ativa `google.generativeai`, modelo fixo `gemini-1.5-flash`, usado em `/avaliar` e `/curriculo`); (b) l.421 `CTX_CHAVE = _config(...)` (lê `.env`; usado em `/analise_contextual` e `/assistente`) | `''` | Não (opcional; sem chave usa análise estática / rotas respondem sem IA) |
| `GEMINI_MODEL` | `recomendar.py` → `CTX_MODELO` (validado por regex `[a-z0-9][a-z0-9.\-]{1,60}`, senão volta ao padrão) | `gemini-3.5-flash-lite` | Não |
| `GEMINI_TIMEOUT` | `recomendar.py` → `CTX_TIMEOUT` (float limitado a 3–30 s; inválido → 12) | `12` | Não |
| `JC_LOG_DIR` | `includes/logger.php` → `jc_log_dir()` via `jc_log_config()`; `recomendar.py` → `_pasta_logs` | `{raiz}/logs` | Não |
| `JC_LOG_MAX_MB` | `logger.php` → `jc_log_rotacionar()` (`max(1, intval)`) | `2` | Não |
| `JC_LOG_DIAS` | `logger.php` → `jc_log_rotacionar()`; `recomendar.py` → `_dias` (1–365, `backupCount`) | `30` | Não |
| `JC_IA_PORTA` *(v4.55)* | `includes/ia_endereco.php` → `jc_ia_portas()`; `recomendar.py` → `portas_candidatas()`; `IA/verificar_ambiente.py` → `portas()` | `5000` | Não |
| `JC_IA_PORTAS_EXTRAS` *(v4.55)* | os mesmos três (faixa `início-fim`, no máximo 50 portas) | `5001-5010` | Não |
| `FLASK_DEBUG` *(fora do `.env.example`)* | `recomendar.py`, bloco principal, `os.environ.get("FLASK_DEBUG","0") == "1"` (só ambiente real) | `0` | Não |
| `JC_GEMINI_API_BASE` *(fora do `.env.example`, só testes)* | `recomendar.py` l.430 (aceito só se começar com `http://127.0.0.1:` ou `http://localhost:`) | — | Não |

### `.gitignore`
- **Tipo:** configuração do Git.
- **Conteúdo:** ignora `venv/`, `.venv/`, `venv-*/`, `venv.antigo-*/` (backups do `iniciar_ia.bat`, v4.55), `logs/ia_porta.json`, `logs/ia_porta.json.tmp` e `logs/ia.lock` (estado da IA, v4.55), `__pycache__/`, `*.pyc`; `vendor/`; `.env` e `*.env` (o `.env.example` **não** é ignorado, pois termina em `.example`) [C]; `*.log`, `error_log`; `*.sql` com exceções `!schema.sql`, `!migrations/*.sql`, `!database/seed_demo.sql`; `uploads/curriculos/*` com exceção `!uploads/curriculos/.htaccess`.
- **Observações:** comentário explica que `#` no fim da linha viraria parte do padrão (correto). `logs/.htaccess` continua versionado (só `*.log` é ignorado).

### `composer.json`
- **Tipo:** configuração do Composer (projeto `type: project`).
- **Declara:** `php >= 8.0`, `phpmailer/phpmailer ^6.9`, `twilio/sdk ^8.3`; bloco `authors` com nome e e-mail pessoal do autor (não reproduzido aqui).
- **Uso no código:** `includes/mailer.php` (l.19–21) e `includes/sms.php` (l.18–20) fazem `require_once vendor/autoload.php` **somente se o arquivo existir**; sem ele, `class_exists(PHPMailer)` / `class_exists(Twilio\Rest\Client)` falham e as funções retornam `false` com `error_log("... Rode composer install")`.
- **Verificação:** **a pasta `vendor/` NÃO existe na cópia** e não há `composer.lock` [C `ls`]. Coerente com `.gitignore` (`vendor/`) e com o `README.md` l.31 ("`composer install`"). Sem `composer install`, e-mail e SMS de verificação/recuperação não funcionam [C pelos `class_exists`].

### `logs/.htaccess`
- **Tipo:** configuração Apache. Nega todo acesso HTTP à pasta `logs/` (`Require all denied` no Apache 2.4; `Order allow,deny` + `Deny from all` no 2.2). Comentário: logs têm nomes, IDs, IPs e detalhes técnicos.
- **Limitação:** só vale em Apache com `AllowOverride` que permita `AuthConfig`/`Limit`; em nginx ou `php -S` não tem efeito [I]. Se `JC_LOG_DIR` apontar para fora de `logs/`, a proteção depende do novo local.

### `uploads/curriculos/.htaccess`
- **Tipo:** configuração Apache, mesmas diretivas. Os PDFs só devem sair por `uploads/ver_curriculo.php` (que confere sessão/permissão — fora deste módulo). Mesma limitação de servidor. A pasta na cópia contém apenas o `.htaccess` [C].

### Imagens em `assets/img`

| Arquivo | Dimensão real | Onde é usada |
|---|---|---|
| `marca/jovemconnect-horizontal.png` (493×160) | 493×160 | `<img>` da navbar (acima de 480px): `index.php` (l.51), `includes/header.php` (l.79, l.146), `dashboard/jovem.php`, `dashboard/empresa.php`, `Vagas/avaliar.php`, `candidatos.php`, `favoritos.php`, `listar.php` (l.394), `minhas.php`; rodapé do `index.php` (l.145, `.marca-rodape`) |
| `marca/jovemconnect-simbolo.png` | 192×192 | `<source media="(max-width: 480px)">` das navbars: `index.php`, `header.php`, dashboards, `Vagas/{avaliar,candidatar,candidatos,curriculoIA,favoritos,listar(2×),minhas}.php`, `empresa/perfil.php`, `jovem/perfil.php` |
| `marca/jovemconnect-vertical.png` | 546×420 | logo das telas de formulário/login: `comecar.php`, `jovem/{login,cadastro}.php`, `empresa/{login,cadastro,locais}.php`, `Vagas/criar.php`, `recuperar_senha.php` |
| `marca/jovemconnect-icone-32.png` | 32×32 | favicon (`rel="icon"`) de praticamente todas as páginas, inclusive `documentos.php` e `includes/candidato.php` |
| `marca/jovemconnect-icone-180.png` | 180×180 | `apple-touch-icon` nas mesmas páginas (exceto `documentos.php` e `includes/candidato.php`) |
| `home/pessoa-computador.jpg` | 5331×3648, ~1,27 MB | fundo do hero de `index.php` (l.79). Atributos no HTML: `width="1398" height="2097"` (retrato) — não batem com o arquivo (paisagem) |
| `home/edificio-empresa.jpg` | 683×1024, ~150 KB | fundo da seção "Sobre" do `index.php` (l.108), `loading="lazy"` |

## Índice de funções PHP

| Função | Arquivo:linha | Assinatura | Chamada em |
|---|---|---|---|
| `jc_assist_sair` | `IA/assistente_ajax.php`:41 | `jc_assist_sair(int $codigo, array $corpo): never` | (próprio arquivo) |
| `jc_assist_texto` | `IA/assistente_ajax.php`:58 | `jc_assist_texto(string $t, int $max): string` | (próprio arquivo) |
| `jc_assist_mascarar` | `IA/assistente_ajax.php`:65 | `jc_assist_mascarar(string $t, bool &$houve): string` | (próprio arquivo) |
| `statusClass` | `Vagas/candidatos.php`:539 | `statusClass(string $status): string` | (próprio arquivo) |
| `jsonError` | `Vagas/favoritar.php`:17 | `jsonError(int $code, string $msg): never` | (próprio arquivo) |
| `jsonOk` | `Vagas/favoritar.php`:24 | `jsonOk(array $data): never` | (próprio arquivo) |
| `statusBadge` | `candidaturas/minhas.php`:48 | `statusBadge(string $status): string` | (próprio arquivo) |
| `calcularIdade` | `functions.php`:27 | `calcularIdade(?string $dataNascimento): ?int` | (próprio arquivo) |
| `calcularIdadeDinamica` | `functions.php`:50 | `calcularIdadeDinamica(?string $dataNascimento): int` | (próprio arquivo) |
| `apenasNumeros` | `functions.php`:62 | `apenasNumeros(string $valor): string` | (próprio arquivo) |
| `validarCpf` | `functions.php`:69 | `validarCpf(string $cpf): bool` | (próprio arquivo) |
| `formatarCpf` | `functions.php`:101 | `formatarCpf(string $cpf): string` | (próprio arquivo) |
| `validarCnpj` | `functions.php`:118 | `validarCnpj(string $cnpj): bool` | (próprio arquivo) |
| `formatarCnpj` | `functions.php`:153 | `formatarCnpj(string $cnpj): string` | (próprio arquivo) |
| `formatarCep` | `functions.php`:172 | `formatarCep(string $cep): string` | (próprio arquivo) |
| `truncar` | `functions.php`:191 | `truncar(string $texto, int $limite = 150, string $sufixo = '…'): string` | (próprio arquivo) |
| `h` | `functions.php`:203 | `h(string $texto): string` | (próprio arquivo) |
| `chamarFlask` | `functions.php`:229 | `chamarFlask(string $rota, array $dados = [], int $timeout = 6)` (retorna `array\|false`) | (próprio arquivo) |
| `flaskOnline` | `functions.php`:265 | `flaskOnline(): bool` | (próprio arquivo) |
| `registrar_log` | `includes/auth.php`:32 | `registrar_log(string $mensagem, string $nivel = 'INFO'): void` | (próprio arquivo), `includes/config.php`, `includes/logger.php` |
| `verificar_autenticacao` | `includes/auth.php`:43 | `verificar_autenticacao(string $tipo): void` | (próprio arquivo) |
| `jc_empresa_acesso_candidato` | `includes/candidato.php`:31 | `jc_empresa_acesso_candidato(mysqli $conn, int $empresa_id, int $jovem_id): array` | (próprio arquivo), `jovem/perfil.php` |
| `jc_empresa_pode_ver_candidato` | `includes/candidato.php`:65 | `jc_empresa_pode_ver_candidato(mysqli $conn, int $empresa_id, int $jovem_id): bool` | `IA/avaliar_ajax.php`, `Vagas/avaliar.php`, `uploads/ver_curriculo.php` |
| `jc_candidato_indisponivel` | `includes/candidato.php`:76 | `jc_candidato_indisponivel(string $voltar = '../dashboard/empresa.php'): never` | `jovem/perfil.php` |
| `registrar_log` | `includes/config.php`:15 | `registrar_log(string $mensagem, string $nivel = 'INFO'): void` | `includes/auth.php`, (próprio arquivo), `includes/logger.php` |
| `jc_excecao_nao_tratada` | `includes/config.php`:28 | `jc_excecao_nao_tratada(Throwable $e): void` | (registrada com `set_exception_handler` no próprio arquivo; chamada pelo PHP) |
| `csrf_token` | `includes/csrf.php`:28 | `csrf_token(): string` | `includes/assistente.php`, (próprio arquivo) |
| `csrf_field` | `includes/csrf.php`:36 | `csrf_field(): string` | `Vagas/candidatar.php`, `Vagas/candidatos.php`, `Vagas/criar.php`, `Vagas/curriculoIA.php`, `Vagas/favoritos.php`, `Vagas/minhas.php`, `empresa/cadastro.php`, `empresa/locais.php`, `empresa/login.php`, `empresa/perfil.php`, (próprio arquivo), `jovem/cadastro.php`, `jovem/login.php`, `jovem/perfil.php`, `recuperar_senha.php`, `verificacoes/verificar_email.php`, `verificacoes/verificar_telefone.php` |
| `csrf_verify` | `includes/csrf.php`:48 | `csrf_verify(): bool` | (próprio arquivo) |
| `csrf_require` | `includes/csrf.php`:68 | `csrf_require(bool $ajax = false): void` | `IA/assistente_ajax.php`, `IA/avaliar_ajax.php`, `Vagas/candidatar.php`, `Vagas/candidatos.php`, `Vagas/criar.php`, `Vagas/curriculoIA.php`, `Vagas/favoritar.php`, `Vagas/minhas.php`, `empresa/cadastro.php`, `empresa/excluir_conta.php`, `empresa/locais.php`, `empresa/perfil.php`, `empresa/processar_login.php`, (próprio arquivo), `jovem/cadastro.php`, `jovem/excluir_conta.php`, `jovem/perfil.php`, `jovem/processar_login.php`, `recuperar_senha.php`, `uploads/curriculos.php`, `verificacoes/verificar_email.php`, `verificacoes/verificar_telefone.php` |
| `_csrf_url_inicio` | `includes/csrf.php`:106 | `_csrf_url_inicio(): string` | (próprio arquivo) |
| `_csrf_url_voltar` | `includes/csrf.php`:118 | `_csrf_url_voltar(): ?string` | (próprio arquivo) |
| `csrf_js` | `includes/csrf.php`:133 | `csrf_js(): string` | `Vagas/avaliar.php`, `Vagas/candidatos.php`, `Vagas/favoritos.php`, `Vagas/listar.php`, `dashboard/empresa.php`, `dashboard/jovem.php`, (próprio arquivo), `jovem/perfil.php` |
| `jc_documentos` | `includes/documentos.php`:30 | `jc_documentos(): array` | `documentos.php`, `includes/institucional.php` |
| `jc_documento_versao` | `includes/documentos.php`:102 | `jc_documento_versao(): string` | `documentos.php`, `includes/institucional.php` |
| `jc_documento_secoes` | `includes/documentos.php`:109 | `jc_documento_secoes(array $doc, int $nivel = 3): string` | `documentos.php`, `includes/institucional.php` |
| `jc_documento_resumo` | `includes/documentos.php`:120 | `jc_documento_resumo(array $doc): string` | `documentos.php`, `includes/institucional.php` |
| `empresa_tem_endereco` | `includes/endereco_empresa.php`:17 | `empresa_tem_endereco(mysqli $conn): bool` | `empresa/cadastro.php`, `empresa/perfil.php`, `includes/locais.php` |
| `endereco_empresa_do_post` | `includes/endereco_empresa.php`:27 | `endereco_empresa_do_post(): array` | `empresa/cadastro.php`, `empresa/perfil.php` |
| `endereco_empresa_formatado` | `includes/endereco_empresa.php`:38 | `endereco_empresa_formatado(array $e): string` | `Vagas/criar.php`, `empresa/locais.php`, `empresa/perfil.php`, `includes/locais.php` |
| `cnpj_formatado` | `includes/endereco_empresa.php`:53 | `cnpj_formatado(string $cnpj): ?string` | `empresa/cadastro.php`, `empresa/perfil.php` |
| `carregarEnv` | `includes/env.php`:14 | `carregarEnv(): void` | (próprio arquivo) |
| `jc_campo_erro` | `includes/etapas.php`:34 | `jc_campo_erro(array $erros, string $campo): string` | `empresa/cadastro.php`, `jovem/cadastro.php` |
| `jc_campo_aria` | `includes/etapas.php`:44 | `jc_campo_aria(array $erros, string $campo): string` | `empresa/cadastro.php`, `jovem/cadastro.php` |
| `jc_etapa_do_erro` | `includes/etapas.php`:51 | `jc_etapa_do_erro(array $erros, array $mapa): int` | `empresa/cadastro.php`, `jovem/cadastro.php` |
| `jc_etapas_script` | `includes/etapas.php`:64 | `jc_etapas_script(): string` | `empresa/cadastro.php`, (próprio arquivo), `jovem/cadastro.php` |
| `jc_formacao_cursos` | `includes/formacao.php`:80 | `jc_formacao_cursos(string $nivel): ?array` | (próprio arquivo) |
| `jc_formacao_texto` | `includes/formacao.php`:91 | `jc_formacao_texto($valor, int $max): string` | (próprio arquivo) |
| `jc_formacao_rotulo` | `includes/formacao.php`:99 | `jc_formacao_rotulo(string $nivel, ?int $ano, string $curso, string $outro): string` | (próprio arquivo) |
| `jc_formacao_ler_post` | `includes/formacao.php`:119 | `jc_formacao_ler_post(array $post, array &$erros, string $p = '', string $campoSit = 'escolaridade_status', bool $obrigatorio = true, bool $comEmTecnico = true): ?array` | (próprio arquivo), `jovem/cadastro.php`, `jovem/perfil.php` |
| `jc_formacao_valores_vazios` | `includes/formacao.php`:204 | `jc_formacao_valores_vazios(): array` | (próprio arquivo) |
| `jc_formacao_por_curso` | `includes/formacao.php`:212 | `jc_formacao_por_curso(array &$v, string $campo, array $lista, string $curso): void` | (próprio arquivo) |
| `jc_formacao_de_jovem` | `includes/formacao.php`:226 | `jc_formacao_de_jovem(array $j): array` | (próprio arquivo), `jovem/perfil.php` |
| `jc_formacao_inalterado` | `includes/formacao.php`:290 | `jc_formacao_inalterado(array $enviado, array $mostrado): bool` | `jovem/perfil.php` |
| `jc_formacao_resumo` | `includes/formacao.php`:318 | `jc_formacao_resumo(array $j): string` | `jovem/perfil.php` |
| `jc_formacao_detalhe` | `includes/formacao.php`:341 | `jc_formacao_detalhe(array $f): string` | `jovem/perfil.php` |
| `jc_formacao_campos` | `includes/formacao.php`:370 | `jc_formacao_campos(array $o): string` | `jovem/cadastro.php`, `jovem/perfil.php` |
| `jc_local_tipos` | `includes/locais.php`:36 | `jc_local_tipos(): array` | `Vagas/criar.php`, `empresa/locais.php`, (próprio arquivo) |
| `jc_locais_disponiveis` | `includes/locais.php`:46 | `jc_locais_disponiveis(mysqli $conn): bool` | `Vagas/criar.php`, `empresa/locais.php`, `empresa/perfil.php`, (próprio arquivo) |
| `jc_post_texto` | `includes/locais.php`:59 | `jc_post_texto(array $post, string $chave, int $max): string` | (próprio arquivo) |
| `jc_local_do_post` | `includes/locais.php`:65 | `jc_local_do_post(array $post, string $p = ''): array` | `Vagas/criar.php`, `empresa/locais.php` |
| `jc_local_erro` | `includes/locais.php`:81 | `jc_local_erro(array $l): ?string` | `Vagas/criar.php`, `empresa/locais.php` |
| `jc_local_rotulo` | `includes/locais.php`:92 | `jc_local_rotulo(string $tipo, ?string $nome): string` | `Vagas/criar.php`, `empresa/locais.php`, (próprio arquivo) |
| `jc_cep_formatado` | `includes/locais.php`:99 | `jc_cep_formatado(?string $cep): ?string` | `Vagas/criar.php`, (próprio arquivo) |
| `jc_locais_da_empresa` | `includes/locais.php`:105 | `jc_locais_da_empresa(mysqli $conn, int $empresa_id): array` | `Vagas/criar.php`, `empresa/locais.php`, `empresa/perfil.php` |
| `jc_endereco_principal` | `includes/locais.php`:125 | `jc_endereco_principal(mysqli $conn, int $empresa_id): array` | `Vagas/criar.php`, `empresa/locais.php`, (próprio arquivo) |
| `jc_local_partes` | `includes/locais.php`:136 | `jc_local_partes(array $r, string $pre): array` | `Vagas/criar.php`, (próprio arquivo) |
| `jc_local_resolver` | `includes/locais.php`:155 | `jc_local_resolver(array $r): array` | (próprio arquivo) |
| `jc_local_efetivo` | `includes/locais.php`:210 | `jc_local_efetivo(mysqli $conn, int $vaga_id): array` | `Vagas/avaliar.php`, `Vagas/listar.php`, `includes/match.php` |
| `jc_vagas_sincronizar_empresa` | `includes/locais.php`:232 | `jc_vagas_sincronizar_empresa(mysqli $conn, int $empresa_id): void` | `empresa/perfil.php` |
| `jc_vagas_sincronizar_local` | `includes/locais.php`:249 | `jc_vagas_sincronizar_local(mysqli $conn, int $empresa_id, int $local_id, array $l): void` | `empresa/locais.php` |
| `jc_local_inserir` | `includes/locais.php`:266 | `jc_local_inserir(mysqli $conn, int $empresa_id, array $l): int` | `Vagas/criar.php`, `empresa/locais.php` |
| `jc_req_id` | `includes/logger.php`:38 | `jc_req_id(): string` | (próprio arquivo), `includes/match.php` |
| `jc_log_config` | `includes/logger.php`:50 | `jc_log_config(string $nome, string $padrao): string` | (próprio arquivo) |
| `jc_log_dir` | `includes/logger.php`:56 | `jc_log_dir(): string` | (próprio arquivo) |
| `jc_log_campo_proibido` | `includes/logger.php`:65 | `jc_log_campo_proibido(string $campo): bool` | (próprio arquivo) |
| `jc_log_limpar` | `includes/logger.php`:79 | `jc_log_limpar($valor, int $nivel = 0)` | (próprio arquivo) |
| `jc_log_usuario` | `includes/logger.php`:101 | `jc_log_usuario(): string` | (próprio arquivo) |
| `jc_log_ip` | `includes/logger.php`:115 | `jc_log_ip(): string` | (próprio arquivo) |
| `jc_log_rotacionar` | `includes/logger.php`:127 | `jc_log_rotacionar(string $arquivo): void` | (próprio arquivo) |
| `jc_log` | `includes/logger.php`:158 | `jc_log(string $evento, string $nivel = 'info', array $dados = [], string $arquivo = 'app'): void` | `IA/assistente_ajax.php`, `IA/avaliar_ajax.php`, `Vagas/candidatar.php`, `Vagas/candidatos.php`, `Vagas/criar.php`, `Vagas/minhas.php`, `empresa/locais.php`, `includes/config.php`, (próprio arquivo), `includes/match.php` |
| `jc_log_seguranca` | `includes/logger.php`:187 | `jc_log_seguranca(string $evento, string $nivel = 'info', array $dados = []): void` | `IA/assistente_ajax.php`, `IA/avaliar_ajax.php`, `Vagas/avaliar.php`, `Vagas/candidatos.php`, `empresa/cadastro.php`, `empresa/excluir_conta.php`, `empresa/processar_login.php`, `includes/csrf.php`, (próprio arquivo), `jovem/cadastro.php`, `jovem/excluir_conta.php`, `jovem/perfil.php`, `jovem/processar_login.php`, `logout.php`, `recuperar_senha.php`, `uploads/ver_curriculo.php`, `verificacoes/verificar_email.php`, `verificacoes/verificar_telefone.php` |
| `jc_log_excecao` | `includes/logger.php`:195 | `jc_log_excecao(Throwable $e, string $evento = 'erro.inesperado'): void` | `Vagas/candidatar.php`, `empresa/cadastro.php`, `includes/config.php`, (próprio arquivo), `jovem/cadastro.php` |
| `registrar_log` | `includes/logger.php`:211 | `registrar_log($mensagem, $nivel = 'INFO'): void` | `includes/auth.php`, `includes/config.php`, (próprio arquivo) |
| `enviarCodigoVerificacao` | `includes/mailer.php`:27 | `enviarCodigoVerificacao(string $email, string $nome, string $codigo): bool` | (próprio arquivo), `recuperar_senha.php`, `verificacoes/verificar_email.php` |
| `jc_py_ws` | `includes/match.php`:32 | `jc_py_ws(): string` | (próprio arquivo) |
| `jc_py_str` | `includes/match.php`:37 | `jc_py_str($valor): string` | (próprio arquivo) |
| `jc_py_split` | `includes/match.php`:46 | `jc_py_split(string $s): array` | (próprio arquivo) |
| `jc_py_title` | `includes/match.php`:51 | `jc_py_title(string $s): string` | (próprio arquivo) |
| `jc_match_pesos` | `includes/match.php`:70 | `jc_match_pesos(): array` | (próprio arquivo) |
| `jc_match_calcular` | `includes/match.php`:80 | `jc_match_calcular(array $d): array` | `IA/avaliar_ajax.php` |
| `jc_match_texto_estatico` | `includes/match.php`:210 | `jc_match_texto_estatico(int $score): string` | `IA/avaliar_ajax.php` |
| `jc_match_rotulo` | `includes/match.php`:224 | `jc_match_rotulo(int $score): string` | `IA/avaliar_ajax.php` |
| `jc_match_categorias` | `includes/match.php`:238 | `jc_match_categorias(array $calc, array $ctx): array` | `IA/avaliar_ajax.php` |
| `jc_match_ajuste` | `includes/match.php`:312 | `jc_match_ajuste(array $calc, string $quem = 'jovem'): ?array` | `IA/avaliar_ajax.php` |
| `jc_match_destaques` | `includes/match.php`:330 | `jc_match_destaques(array $cats, ?array $ajuste): array` | `IA/avaliar_ajax.php` |
| `jc_match_categorias_ia` | `includes/match.php`:349 | `jc_match_categorias_ia(): array` | (próprio arquivo) |
| `jc_match_texto_ia` | `includes/match.php`:355 | `jc_match_texto_ia($v, int $max): string` | (próprio arquivo) |
| `jc_match_validar_ia` | `includes/match.php`:370 | `jc_match_validar_ia($a): ?array` | `IA/avaliar_ajax.php` |
| `jc_flask_post` | `includes/match.php`:421 | `jc_flask_post(string $rota, array $payload, int $timeout): ?array` | `IA/assistente_ajax.php`, `IA/avaliar_ajax.php` |
| `jc_match_carregar` | `includes/match.php`:463 | `jc_match_carregar(mysqli $conn, int $jovem_id, int $vaga_id): ?array` | `IA/avaliar_ajax.php` |
| `jc_match_payload_ia` | `includes/match.php`:512 | `jc_match_payload_ia(mysqli $conn, int $jovem_id, int $vaga_id, array $dados, array $calc, array $cats, ?array $ajuste, string $leitor = 'jovem'): array` | `IA/avaliar_ajax.php` |
| `jc_session_start` | `includes/session.php`:17 | `jc_session_start(): void` | `IA/assistente_ajax.php`, `candidaturas/minhas.php`, `comecar.php`, `dashboard/index.php`, `includes/csrf.php`, (próprio arquivo), `index.php`, `processar_login.php`, `uploads/ver_curriculo.php` |
| `jc_session_regenerate` | `includes/session.php`:50 | `jc_session_regenerate(): void` | `empresa/cadastro.php`, `empresa/processar_login.php`, `jovem/cadastro.php`, `jovem/processar_login.php` |
| `normalizarTelefoneE164` | `includes/sms.php`:31 | `normalizarTelefoneE164(string $telefone): ?string` | (próprio arquivo), `verificacoes/verificar_telefone.php` |
| `enviarCodigoSMS` | `includes/sms.php`:54 | `enviarCodigoSMS(string $telefone, string $codigo): bool` | (próprio arquivo), `verificacoes/verificar_telefone.php` |
| `jc_termos_colunas` | `includes/termos.php`:24 | `jc_termos_colunas(mysqli $conn, string $tabela): bool` | (próprio arquivo) |
| `jc_termos_registrar` | `includes/termos.php`:37 | `jc_termos_registrar(mysqli $conn, string $tabela, int $id): void` | `empresa/cadastro.php`, `jovem/cadastro.php` |
| `mascararEmail` | `recuperar_senha.php`:11 | `mascararEmail(string $email): string` | (próprio arquivo) |
| `curriculo_nao_encontrado` | `uploads/ver_curriculo.php`:27 | `curriculo_nao_encontrado(): never` | (próprio arquivo) |
| `normalizarTexto` | `verificacoes/validacoes_nomes.php`:7 | `normalizarTexto(string $texto): string` | (próprio arquivo) |
| `contemPalavraImpropria` | `verificacoes/validacoes_nomes.php`:33 | `contemPalavraImpropria(string $nome): bool` | (próprio arquivo) |
| `validarNomeJovem` | `verificacoes/validacoes_nomes.php`:60 | `validarNomeJovem(string $nome): array` | — (sem chamador encontrado) |
| `validarNomeEmpresa` | `verificacoes/validacoes_nomes.php`:98 | `validarNomeEmpresa(string $nome): array` | — (sem chamador encontrado) |
| `gerarEEnviarCodigoEmail` | `verificacoes/verificar_email.php`:26 | `gerarEEnviarCodigoEmail(mysqli $conn, string $tabela, int $idUsuario, string $email, string $nome): bool` | (próprio arquivo) |

| `jc_card_vaga` | `includes/card_vaga.php`:28 | `jc_card_vaga(array $vaga, string $pasta_vagas, string $voltar = ''): void` | `dashboard/jovem.php`, `Vagas/listar.php` (v4.56) |
| `jc_card_vaga_script` | `includes/card_vaga.php`:129 | `jc_card_vaga_script(string $url_favoritar): void` | `dashboard/jovem.php`, `Vagas/listar.php` (v4.56) |
| `jc_ia_portas` | `includes/ia_endereco.php`:26 | `jc_ia_portas(): array` | (próprio arquivo) (v4.55) |
| `jc_ia_ping` | `includes/ia_endereco.php`:44 | `jc_ia_ping(int $porta): ?array` | (próprio arquivo) (v4.55) |
| `jc_ia_base` | `includes/ia_endereco.php`:66 | `jc_ia_base(): ?string` | `admin/configuracoes.php`, (próprio arquivo) (v4.55) |
| `jc_ia_url` | `includes/ia_endereco.php`:99 | `jc_ia_url(string $rota): ?string` | `includes/match.php`, `IA/avaliar_ajax.php`, `Vagas/curriculoIA.php`, `admin/ia_moderacao.php`, `functions.php` (v4.55) |

Total na v4.00: **115 funções PHP** nomeadas (closures/funções anônimas não entram). As 6 linhas acima foram acrescentadas nas versões 4.55 e 4.56; os arquivos da Central administrativa (Stage 7) estão descritos em [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md#arquitetura-dos-arquivos) e as funções do ajudante `scripts/jovemconnect.php` na entrada dele. "Chamada em" foi levantado por busca textual `nome(` em todos os `.php`; chamadas dinâmicas (string) não seriam detectadas — nenhuma foi encontrada.

## Índice de funções Python (Flask)

Arquivo: `IA/recomendar.py`.

| Função | Linha | Assinatura | Rota / gancho |
|---|---|---|---|
| `_str` | 51 | `_str(value)` | — |
| `analise_estatica` | 56 | `analise_estatica(score)` | — |
| `analise_gemini` | 70 | `analise_gemini(score, jovem, vaga)` | — |
| `avaliar` | 98 | `avaliar()` | `/avaliar POST` |
| `analisar_curriculo` | 207 | `analisar_curriculo()` | `/curriculo POST` |
| `_ler_env_projeto` | 315 | `_ler_env_projeto()` | — |
| `_config` | 339 | `_config(nome, padrao)` | — |
| `log_ia` | 376 | `log_ia(evento, nivel)` | — |
| `_tem_contexto` | 396 | `_tem_contexto()` | — |
| `_inicio_requisicao` | 405 | `_inicio_requisicao()` | `@app.before_request` |
| `_fim_requisicao` | 412 | `_fim_requisicao(resposta)` | `@app.after_request` |
| `_ctx_texto` | 488 | `_ctx_texto(valor, maximo)` | — |
| `_ctx_lista` | 498 | `_ctx_lista(valor, quantos, maximo)` | — |
| `validar_contexto` | 511 | `validar_contexto(obj)` | — |
| `_ctx_chamar_gemini` | 538 | `_ctx_chamar_gemini(dados, com_esquema)` | — |
| `analise_contextual` | 586 | `analise_contextual()` | `/analise_contextual POST` |
| `_assist_texto` | 664 | `_assist_texto(valor, maximo)` | — |
| `_assist_limpar_resposta` | 671 | `_assist_limpar_resposta(texto)` | — |
| `assistente` | 684 | `assistente()` | `/assistente POST` |
| `validar_moderacao` | 806 | `validar_moderacao(obj)` | — (Stage 7) |
| `_mod_chamar_gemini` | 829 | `_mod_chamar_gemini(dados, com_esquema)` | — (Stage 7) |
| `moderacao` | 872 | `moderacao()` | `/moderacao POST` (Stage 7) |
| `ping` | 914 | `ping()` | `/ping GET` |
| `portas_candidatas` | 953 | `portas_candidatas()` | — (v4.55) |
| `_ServidorIA.server_bind` | 974 | `server_bind(self)` | — (v4.55, bind exclusivo) |
| `_travar_instancia` | 984 | `_travar_instancia()` | — (v4.55) |
| `_gravar_estado` | 1002 | `_gravar_estado(porta)` | — (v4.55) |
| `_apagar_estado` | 1017 | `_apagar_estado()` | `atexit` (v4.55) |
| `_abrir_servidor` | 1027 | `_abrir_servidor(candidatas)` | — (v4.55) |
