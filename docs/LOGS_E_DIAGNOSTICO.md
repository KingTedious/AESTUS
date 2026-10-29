# Logs e diagnóstico

> Auditoria de 10/10/2026, versão **v4.00**. Formato e eventos: [LOGS.md](LOGS.md). Este documento diz
> **onde olhar** e **como investigar**. Todos os exemplos são fictícios. Os arquivos de log reais da máquina
> do projeto **não foram abertos** nesta auditoria.

## Quem gera logs e onde

| Origem | Arquivo | Como grava | Conteúdo |
|---|---|---|---|
| PHP — funcionamento | `logs/app.log` (ou `JC_LOG_DIR`) | `jc_log()` em `includes/logger.php` | erros, Match, IA, Flask, vagas, candidaturas, locais, assistente |
| PHP — segurança | `logs/seguranca.log` | `jc_log_seguranca()` | login, cadastro, verificação, recuperação, exclusão, CSRF, acesso negado, limite do assistente; **IP anonimizado** |
| Flask | `logs/ia.log` | `log_ia()` em `IA/recomendar.py` | `flask.requisicao` (rota, status, tempo) e `gemini.falha` (motivo) |
| PHP — erros do interpretador e envio de e-mail/SMS | `error_log` do PHP/Apache (fora do projeto; caminho definido no `php.ini` do XAMPP) | `error_log()` em `includes/mailer.php`, `includes/sms.php` | "PHPMailer não encontrado…", falhas SMTP/Twilio, warnings |
| Flask — console | janela "JovemConnect - IA" | `print` na inicialização | estado da chave, erros de importação |
| Navegador | console do navegador | — | falhas de `fetch`, ViaCEP/BrasilAPI |

Eventos registrados no código (busca por `jc_log(` e `jc_log_seguranca(`), além de nomes montados em tempo de
execução listados em [LOGS.md](LOGS.md#principais-eventos): `login.sucesso`, `login.falha`, `logout`,
`cadastro.concluido`, `cadastro.falha`, `conta.excluida`, `senha.recuperacao_solicitada`, `senha.codigo_recusado`,
`senha.redefinida`, `verificacao.email.{enviado,sucesso,falha}`, `verificacao.telefone.{enviado,sucesso,falha}`,
`csrf.recusado`, `acesso.negado`, `assistente.limite`, `assistente.resposta`, `vaga.criada`, `candidatura.criada`,
`candidatura.status`, `candidatura.erro`, `local.editado`, `local.removido`, `local.remocao_bloqueada`,
`match.calculado`, `match.contexto`, `match.divergencia`, `flask.falha`, `banco.indisponivel`, `legado`,
`erro.inesperado`; Flask: `flask.requisicao`, `gemini.falha`.

## O que **nunca** deve aparecer nos logs

Senhas e hashes de senha, tokens (CSRF, sessão, cookies), chaves de API, códigos de verificação, CPF, CNPJ,
telefone, e-mail, CEP, endereço residencial, data de nascimento, currículos e qualquer conteúdo das conversas
com o assistente ou da IA (mensagens, prompts, respostas).

Como o código garante [C][T: suíte logs 22/22]:

- chaves de `dados` cujo nome contém `senha`, `token`, `csrf`, `cookie`, `cpf`, `cnpj`, `telefone`, `email`,
  `cep`, `endereco`, `chave`, `key`, `codigo`, `curriculo`, `mensagem`, `resposta`, `prompt`, `nascimento` etc.
  viram `"[removido]"` (`jc_log_campo_proibido`);
- e-mails e sequências de 10+ dígitos dentro de textos viram `[e-mail]`/`[número]` (`jc_log_limpar`);
- exceções de banco guardam só `errno`/`sqlstate` (`jc_log_excecao`);
- controle de caracteres removido, texto cortado em 300 caracteres (não é possível "forjar" linhas).

Limitações: a comparação de nomes de campo não remove acentos (`endereço` com cedilha não seria detectado);
o `error_log` do PHP **não** passa por essas proteções — `registrar_log` legado e mensagens de PHPMailer/Twilio
podem conter detalhes técnicos.

## Correlacionar PHP, Python e serviços externos

Cada requisição PHP tem um `req` (16–32 hexadecimais, `jc_req_id()`). Nas chamadas ao Flask, o PHP envia o
mesmo valor no cabeçalho `X-Request-Id` e o Flask o grava em `ia.log`. Procure o mesmo `req` nos três
arquivos:

```text
app.log      {"ts":"2026-10-10T14:02:11-03:00","nivel":"info","componente":"php","evento":"match.contexto","req":"9f2c4b7a1e0d3c55","usuario":"jovem:12","pagina":"/JovemConnect/IA/avaliar_ajax.php","dados":{"disponivel":false,"motivo":"chave_invalida","cache":false,"ms":812}}
ia.log       {"ts":"2026-10-10T14:02:11-03:00","nivel":"aviso","componente":"flask","evento":"gemini.falha","req":"9f2c4b7a1e0d3c55","dados":{"rota":"analise_contextual","motivo":"chave_invalida"}}
ia.log       {"ts":"2026-10-10T14:02:11-03:00","nivel":"info","componente":"flask","evento":"flask.requisicao","req":"9f2c4b7a1e0d3c55","dados":{"rota":"/analise_contextual","status":200,"ms":798}}
```

(Valores fictícios; a ordem exata dos campos pode variar.)

## Rotação, retenção e proteção

| Item | PHP (`includes/logger.php`) | Flask (`recomendar.py`) |
|---|---|---|
| Rotação | antes de cada gravação: se o arquivo passou de `JC_LOG_MAX_MB` (2 MB) **ou** o primeiro evento é de outro dia → renomeia para `app-AAAAMMDD-HHMMSS.log` | `TimedRotatingFileHandler` à meia-noite |
| Retenção | **somente quando há rotação**: apaga arquivos com mais de `JC_LOG_DIAS` (30) dias ou além dos 60 mais recentes | guarda `JC_LOG_DIAS` arquivos |
| Proteção | `logs/.htaccess` (só Apache com `AllowOverride`); `*.log` no `.gitignore` | mesma pasta |
| Falha ao gravar | o site continua; aviso só no `error_log` | o Flask continua |

Observação: `logout.php` inclui o logger sem `env.php`; se `JC_LOG_DIR` estiver só no `.env`, o evento
`logout` vai para a pasta `logs/` padrão [C].

## Procedimentos de diagnóstico

### Configuração (`.env`)
1. O `.env` está na raiz (mesma pasta do `index.php`) e se chama exatamente `.env`?
2. Variáveis do Windows têm prioridade sobre o `.env` (`includes/env.php`).
3. Depois de mudar o `.env`, o PHP lê na próxima requisição; o **Flask só ao reiniciar**.
4. `http://127.0.0.1:5000/ping` (ou a porta gravada em `logs/ia_porta.json`, se a 5000 estava ocupada):
   `"assistente"`/`"analise_contextual"` mostram se o Flask achou a chave. A inicialização da IA fica registrada em
   `logs/iniciar_ia.log` e a dos scripts do XAMPP em `logs/iniciar_jovemconnect.log`.

### Banco de dados
1. Mensagem "temporariamente indisponível" → `banco.indisponivel` em `app.log` com `errno`
   (ex.: 2002 = servidor parado, 1045 = usuário/senha, 1049 = banco inexistente).
2. "Ocorreu um erro inesperado" → `erro.inesperado` com `errno`/`sqlstate`: `1054` (coluna desconhecida)
   costuma indicar **migração não aplicada**; `1062` é duplicidade; `1406` texto longo demais (modo estrito).
3. Confirme as colunas no phpMyAdmin (aba Estrutura) e compare com [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md).

### Autenticação
1. Procure `login.falha` em `seguranca.log`: `motivo` `campos_invalidos` ou `credenciais`.
2. Conta de exemplo que não entra (só em bancos criados com o `schema.sql` antigo, até a v4.50): um registro de exemplo tem senha em texto puro e nunca faz login [C]. O `schema.sql` atual não cria contas.
3. "Sua sessão expirou…" → `csrf.recusado`: página aberta antes de um login/logout; recarregar.
4. Recuperação "enviou" mas nada chegou → `senha.recuperacao_solicitada` existe, mas a falha de envio está só no
   `error_log` do PHP ("PHPMailer não encontrado" = falta `composer install`).

5. (v4.60) "Muitas tentativas seguidas" no login → `login.bloqueado` com `motivo=muitas_tentativas` em
   `seguranca.log`; espere o prazo ou rode `php scripts/jovemconnect.php limpar-tentativas`. Código que "deixou
   de valer" → `senha.codigo_cancelado` ou `verificacao.*.codigo_cancelado` (5 erros): peça outro código.
6. (v4.60) Usuário "deslogado sozinho" → `conta.sessao_encerrada` com `motivo=senha_alterada` (a senha foi
   trocada em outro lugar) ou `conta_suspensa`.
7. (v4.60) O limite não está valendo → procure `tentativas.sem_pasta` em `app.log` (pasta dos contadores sem
   permissão de gravação).

### Autorização e acesso
1. Empresa recebe "Perfil indisponível" → `acesso.negado` em `seguranca.log` (`recurso`, `jovem`); confira se há
   candidatura do jovem a uma vaga **dessa** empresa ou se o perfil é público (`jovens.perfil_publico`).
2. Mudança de status recusada (403) → `acesso.negado` com a candidatura: a vaga não é da empresa da sessão.

### Match
1. Nota inesperada: recalcule à mão com a tabela de [IA_E_MATCH.md](IA_E_MATCH.md#critérios-e-pontos) — lembre das regras de substring.
2. `match.divergencia` em `app.log` → Python e PHP calcularam diferente: alguém mudou só um dos lados.
3. `flask.falha` → o Flask estava fora ou demorou mais de 6 s; a nota veio do PHP (`fonte:"local"`).
4. Nota antiga depois de editar o perfil: o cache só é trocado quando a **nota** muda.

### Assistente
1. Sempre "Resposta automática" → `assistente.resposta` com `fonte:"local"` e `motivo`
   (`sem_chave`, `flask_indisponivel`, `chave_invalida`, `limite`, `timeout`…); detalhe do Gemini em `ia.log`.
2. "Muitas mensagens em pouco tempo" → `assistente.limite` (8/min, 60/h por sessão; 40/min por IP).

### E-mail e SMS
1. Sem `vendor/` → `error_log`: "Rode composer install".
2. Credenciais vazias → "Configuração de SMTP ausente ou incompleta" / equivalente do Twilio.
3. Erro do provedor → mensagem do PHPMailer/Twilio no `error_log`.

### Central administrativa (Stage 7)
1. "Ainda não foi instalada" / "Central indisponível" → a migração da Stage 7 não foi aplicada:
   `php scripts/admin.php status` diz o que falta ([MIGRACOES.md](MIGRACOES.md)).
2. Não consegue entrar → Central → Segurança (ou `admin.login` com `resultado` `falha`/`negado` em
   `seguranca.log`; o `motivo` diz `senha`, `mfa`, `inativo`, `email` ou `limite_*`). Código do autenticador sempre
   recusado: confira o relógio do computador (o TOTP depende da hora certa, tolerância de 30 s). Bloqueio por
   tentativas: aguarde 15 minutos. Senha ou autenticador perdidos: `redefinir-senha` / `redefinir-mfa` na linha de
   comando.
3. "Sua sessão foi encerrada" → `admin.sessao.encerrada` com o motivo (`inatividade`, `tempo_total`, `navegador`,
   `versao` — senha trocada ou sessões encerradas —, `inativo`).
4. "Operação não concluída… informe o código X" → procure `"req":"X"` em `app.log`: `admin.erro_inesperado` (e,
   se for o histórico, `admin.auditoria_erro`) mostram o tipo e o local do erro. Nada foi alterado.
5. "Envio recusado" → `admin.csrf.recusado`: página aberta há muito tempo ou em outra aba depois de sair; abra de
   novo.
6. Conferência do histórico com problema → `php scripts/admin.php verificar-auditoria` aponta o primeiro registro;
   compare com o backup e com a linha `admin.*` de mesmo `registro` em `seguranca.log` (ver
   [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md#histórico-trilha-de-auditoria)). Depois de
   restaurar o banco de um backup, os registros mais novos citados no log aparecem como "ausentes" — esperado.
7. Vaga "sumiu" para os jovens → Central → Vagas: estado `pendente`/`rejeitada`/`suspensa` ou empresa suspensa;
   `moderacao.sinalizado` em `app.log` mostra a regra; o histórico da vaga mostra quem decidiu.
8. Jovem ou empresa diz que não consegue entrar → `login.bloqueado` em `seguranca.log` = conta suspensa (Central →
   Contas → histórico da conta).
9. Análise por IA indisponível → a mensagem diz o motivo (sem chave, Flask parado, limite, demora); detalhe em
   `ia.log` (`gemini.falha`, `rota=moderacao`).
