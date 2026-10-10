# Configuração (`.env`)

O arquivo `.env` fica na **raiz do projeto** (mesma pasta do `index.php`). Copie
`.env.example` para `.env` e preencha. O `.env` está no `.gitignore`: **nunca**
o envie para o Git nem o compartilhe. Linhas `CHAVE=valor`, sem espaços em volta
do `=`; linhas começando com `#` são comentários.

O PHP lê o `.env` por `includes/env.php`; o Flask (`IA/recomendar.py`) lê o mesmo
arquivo. Variáveis definidas no sistema operacional têm prioridade.

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

Depois de mudar o `.env`, feche a janela "JovemConnect - IA" e rode
`iniciar_ia.bat` de novo. Confira em <http://127.0.0.1:5000/ping>: `"assistente": true`
indica que há chave configurada (a chave em si nunca é mostrada).

Use sempre uma chave **nova e só sua**. Se uma chave já foi publicada (Git,
mensagem, print), revogue-a no painel do Google e gere outra.

## Logs

| Variável | Padrão | Uso |
|---|---|---|
| `JC_LOG_DIR` | `logs/` do projeto | Pasta dos logs (PHP e Flask) |
| `JC_LOG_MAX_MB` | `2` | Tamanho máximo de um arquivo de log do PHP antes de abrir outro |
| `JC_LOG_DIAS` | `30` | Por quantos dias os arquivos antigos são guardados |
