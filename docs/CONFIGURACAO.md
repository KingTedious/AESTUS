# Configuração (`.env`)

O arquivo `.env` fica na **raiz do projeto** (mesma pasta do `index.php`). Copie
`.env.example` para `.env` e preencha. O `.env` está no `.gitignore`: **nunca**
o envie para o Git nem o compartilhe. Linhas `CHAVE=valor`, sem espaços em volta
do `=`; linhas começando com `#` são comentários.

O PHP lê o `.env` por `includes/env.php`; o Flask (`IA/recomendar.py`) lê o mesmo
arquivo. Variáveis definidas no sistema operacional têm prioridade.

Exceções no Flask: `FLASK_DEBUG` e a integração antiga `google-generativeai` (que usa
`GEMINI_API_KEY`) só olham as **variáveis de ambiente reais**, não o `.env`
(ver [IA_E_MATCH.md](IA_E_MATCH.md#integração-antiga-google-generativeai)). Lidas pelo código mas fora do
`.env.example`: `DB_CHARSET`, `FLASK_DEBUG` (manter `0`) e `JC_GEMINI_API_BASE` (só para testes com um Gemini
simulado em `127.0.0.1`).

## Banco de dados (PHP)

| Variável | Padrão | Uso |
|---|---|---|
| `DB_HOST` | `localhost` | Servidor do MariaDB/MySQL |
| `DB_USER` | `root` | Usuário |
| `DB_PASS` | vazio | Senha |
| `DB_NAME` | `jovemconnect` | Nome do banco |
| `DB_CHARSET` | `utf8mb4` | Conjunto de caracteres |

## E-mail (verificação e recuperação de senha)

`SMTP_HOST`, `SMTP_PORT` (587), `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`
(`tls`, `ssl` ou `none`), `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`. Sem eles, o envio
de e-mail não funciona (o resto do site sim).

## SMS (verificação por telefone)

`TWILIO_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM_NUMBER`.

## IA (opcional)

| Variável | Padrão | Uso |
|---|---|---|
| `GEMINI_API_KEY` | vazio | Chave da API do Google Gemini. Sem ela: Match tradicional e assistente com respostas prontas |
| `GEMINI_MODEL` | `gemini-3.5-flash-lite` | Modelo usado pela análise contextual e pelo assistente |
| `GEMINI_TIMEOUT` | `12` | Espera máxima por resposta, em segundos (3 a 30) |
| `JC_IA_PORTA` | `5000` | Porta preferida do servidor de IA (1024 a 65535) |
| `JC_IA_PORTAS_EXTRAS` | `5001-5010` | Faixa usada se outro programa estiver na porta preferida (no máximo 50 portas). A IA grava a escolhida em `logs/ia_porta.json`; o PHP lê dali (`includes/ia_endereco.php`) e só aceita portas desta faixa, depois de conferir no `/ping` que é a IA do JovemConnect (senão trata como IA fora do ar) |

Depois de mudar o `.env`, feche a janela "JovemConnect - IA" e rode
`iniciar_ia.bat` de novo. Confira em <http://127.0.0.1:5000/ping>: `"assistente": true`
indica que há chave configurada (a chave em si nunca é mostrada). A mesma chave habilita a
análise complementar de moderação da Central administrativa (`"moderacao": true` no `/ping`).

Use sempre uma chave **nova e só sua**. Se uma chave já foi publicada (Git,
mensagem, print), revogue-a no painel do Google e gere outra.

## Logs

| Variável | Padrão | Uso |
|---|---|---|
| `JC_LOG_DIR` | `logs/` do projeto | Pasta dos logs (PHP e Flask) |
| `JC_LOG_MAX_MB` | `2` | Tamanho máximo de um arquivo de log do PHP antes de abrir outro |
| `JC_LOG_DIAS` | `30` | Por quantos dias os arquivos antigos são guardados |

## Central administrativa (opcional — Stage 7)

| Variável | Padrão | Uso |
|---|---|---|
| `JC_ADMIN_OCIOSO_SEG` | `900` (15 min) | Encerra a sessão administrativa depois desse tempo sem uso (aceito de 120 a 3600; fora disso vale o limite mais próximo) |
| `JC_ADMIN_MAX_SEG` | `28800` (8 h) | Encerra a sessão administrativa depois desse tempo desde a entrada (aceito de 900 a 43200) |

Não existe variável com senha ou conta de administrador: administradores são criados só pela linha de
comando (`php scripts/admin.php criar ...`, ver [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md)).

## Segurança (opcional — v4.60)

| Variável | Padrão | Uso |
|---|---|---|
| `JC_EXIBIR_ERROS` | vazio | Com `1`, os erros do PHP aparecem na página (só para desenvolver no seu PC). Vazio: os erros vão só para o log do PHP e **não** aparecem para o visitante, porque mostrariam caminhos e trechos de SQL (`includes/env.php`) |
| `JC_TENTATIVAS_DIR` | pasta temporária que o PHP usa (`sys_get_temp_dir()`; no Windows, em geral a pasta `TEMP` de quem roda o Apache), subpasta `jovemconnect_tentativas_<hash>` | Pasta dos contadores do limite de tentativas de login e de códigos (`includes/tentativas.php`). Deve ficar **fora** do `htdocs`. Se não puder ser gravada, o site continua funcionando sem limite e registra `tentativas.sem_pasta` no log |
| `JC_AMBIENTE` | vazio (desenvolvimento ou apresentação) | Com `producao`, as contas de demonstração (`@demo.jovemconnect.example`, senha pública) **não entram** e `php scripts/jovemconnect.php banco-seed` se recusa a carregar os dados de demonstração. Use num servidor real; deixe vazio na apresentação com os dados de demonstração |

Para liberar quem foi bloqueado pelo limite de tentativas antes do prazo (por exemplo, na apresentação):
`php scripts/jovemconnect.php limpar-tentativas` (no Windows: `C:\xampp\php\php.exe scripts\jovemconnect.php
limpar-tentativas`). O comando apaga só os contadores; nada no banco muda.

O `.env` em si é bloqueado pelo `.htaccess` da raiz (v4.60). Ainda assim, num servidor de verdade, o ideal é
mantê-lo fora da pasta pública; confira a proteção com `scripts\seguranca\verificar_protecao.bat`.
