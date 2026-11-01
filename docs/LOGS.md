# Logs

Uma linha **JSON** por evento, na pasta `logs/` (ou `JC_LOG_DIR`). A pasta é
bloqueada para o navegador (`logs/.htaccess`) e os arquivos `*.log` ficam fora do Git.

**Desde a v4.62, uma subpasta por assunto** (resumo também em `logs/LEIAME.txt`):

| Subpasta | Arquivos | Quem grava | Conteúdo |
|---|---|---|---|
| `logs/site/` | `app.log`, `app-AAAAMMDD-HHMMSS.log` | PHP (`jc_log`) | ações do site: erros, Match, IA, Flask, vagas, candidaturas, locais, assistente |
| `logs/seguranca/` | `seguranca.log`, `seguranca-AAAAMMDD-HHMMSS.log` | PHP (`jc_log_seguranca`) | logins, cadastros, verificações, recuperação de senha, conta, CSRF recusado, acesso negado, denúncias, limite do assistente, espelho da auditoria da Central (com IP anonimizado) |
| `logs/ia/` | `ia.log`, `ia.log.AAAA-MM-DD`, `iniciar_ia.log` (Linux: `ia_console.log`) | Flask e `iniciar_ia` | cada requisição (rota, status, tempo), falhas do Gemini (motivo) e o diagnóstico de início da IA |
| `logs/sistema/` | `iniciar_jovemconnect.log` | `iniciar_jovemconnect` | diagnóstico de início do projeto |
| `logs/testes/` | `verificacao_protecao-*.log`, `regressao_isolada-*.log`, `diagnostico_seguranca-*.log` | roteiros de `scripts/seguranca/` | resultados das verificações de segurança |
| raiz de `logs/` | `ia_porta.json`, `ia.lock` | Flask | **estado** da IA (porta e instância única), não são logs |

Arquivos antigos que ainda estejam na raiz são movidos para a subpasta certa por
`php scripts/jovemconnect.php organizar-logs` (o `iniciar_jovemconnect` faz isso a cada início; nunca apaga
nem sobrescreve; arquivo em uso fica para a próxima vez).

Exemplo:

```json
{"ts":"2026-10-10T09:30:00-03:00","nivel":"aviso","componente":"php","evento":"acesso.negado","req":"a1b2c3d4e5f60718","usuario":"empresa:3","pagina":"/JovemConnect/jovem/perfil.php","ip":"192.168.0.0","dados":{"recurso":"perfil_candidato","jovem":12}}
```

- `nivel`: `debug`, `info`, `aviso` ou `erro`.
- `req`: identificador da requisição. O PHP envia o mesmo valor ao Flask no
  cabeçalho `X-Request-Id`, então a mesma ação aparece com o mesmo `req` em
  `app.log` e `ia.log`.
- `usuario`: tipo e número interno da conta (`jovem:12`), nunca nome ou e-mail.

## Principais eventos

`login.sucesso`, `login.falha`, `logout`, `cadastro.concluido`, `cadastro.falha`
(só os nomes dos campos com erro), `cadastro.erro_banco`, `conta.excluida`,
`senha.recuperacao_solicitada`, `senha.codigo_recusado`, `senha.redefinida`,
`verificacao.email.*` e `verificacao.telefone.*` (enviado, sucesso, falha),
`csrf.recusado`, `acesso.negado`, `vaga.criada`, `vaga.removida`,
`vaga.remocao_recusada`, `candidatura.criada`, `candidatura.status`,
`candidatura.erro`, `local.adicionado`, `local.editado`, `local.removido`,
`local.remocao_bloqueada`, `match.calculado`, `match.contexto`,
`match.divergencia`, `flask.falha`, `assistente.resposta`, `assistente.limite`,
`banco.indisponivel`, `erro.inesperado`; no Flask, `flask.requisicao` e
`gemini.falha`. Chamadas antigas a `registrar_log()` continuam funcionando e
viram o evento `legado`.

Stage 7 (Central administrativa e moderação — ver
[ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md#logs)):

- `seguranca.log`: `admin.<ação>` — espelho de **cada** registro do histórico
  administrativo (ex.: `admin.login`, `admin.conta.suspensa`, `admin.vaga.moderada`,
  `admin.cli.admin_criado`), gravado só depois do COMMIT, com `dados.registro` (id em
  `admin_auditoria`) e `dados.elo` (16 primeiros caracteres do hash daquele registro);
  `admin.login_csrf`; `login.bloqueado` (conta suspensa tentou entrar);
  `conta.sessao_encerrada`; `denuncia.criada`, `denuncia.limite`, `denuncia.recusada`.
- `app.log`: `admin.auditoria_erro`, `admin.erro_inesperado`, `cli.admin_erro`,
  `moderacao.sinalizado`, `moderacao.erro_fila`, `vaga.recusada`, `denuncia.erro`.
- `ia.log`: `moderacao.analise` e `gemini.falha` com `rota=moderacao`.

v4.60 (correção de segurança):

- `seguranca.log`: `login.bloqueado` também com `motivo` = `muitas_tentativas` (limite de falhas por conta ou
  por IP) ou `conta_demonstracao` (`JC_AMBIENTE=producao`); `senha.recuperacao_limitada` (pedidos de código
  acima do intervalo ou do teto por hora; a tela é a mesma de sempre); `senha.codigo_bloqueado` (limite de erros
  por IP) e `senha.codigo_cancelado` (5 erros: o código deixa de valer); `verificacao.email.bloqueada`,
  `verificacao.email.limitada`, `verificacao.email.codigo_cancelado` e os equivalentes
  `verificacao.telefone.*`; `conta.sessao_encerrada` também com `motivo` = `senha_alterada` (a senha foi trocada
  em outra sessão) ou `conta_inexistente`.
- `app.log`: `tentativas.sem_pasta` (a pasta dos contadores não pode ser gravada; o site segue **sem** limite
  até isso ser resolvido — ver `JC_TENTATIVAS_DIR` em [CONFIGURACAO.md](CONFIGURACAO.md)).
- Nenhum desses eventos grava e-mail, IP em claro, senha ou código: os contadores do limite ficam fora de
  `logs/`, em arquivos com nome de hash e só com horários.

O `elo` é o único valor que não passa pela máscara de números (é gerado pelo sistema,
16 caracteres hexadecimais); a conferência do histórico compara esses elos com a tabela
para revelar registros apagados ou trocados.

## O que nunca é gravado

Senhas, tokens/CSRF, cookies, chaves de API, códigos de verificação, CPF, CNPJ,
telefone, e-mail, CEP e endereço, data de nascimento, currículo, textos digitados
(mensagens do assistente, prompts e respostas da IA) e, na Central administrativa,
senha, segredo e códigos do autenticador, o texto avaliado pela moderação e a
justificativa do administrador (que fica só no histórico, mascarada). Campos com esses nomes viram
`[removido]`; e-mails e sequências longas de números dentro de textos são trocados
por `[e-mail]`/`[número]`. Textos são cortados e não conseguem quebrar a linha
(o JSON escapa quebras de linha), então ninguém consegue "inventar" um evento.
Erros de banco guardam só o código do erro (a mensagem pode conter SQL e dados).

## Rotação e retenção

- PHP: um arquivo por dia e um novo se passar de `JC_LOG_MAX_MB` (2 MB); o
  anterior vira `app-AAAAMMDD-HHMMSS.log`. Arquivos com mais de `JC_LOG_DIAS`
  (30) dias (ou além dos 60 mais recentes) são apagados **no momento de uma rotação**
  (não a cada gravação).
- Flask: `ia.log` troca à meia-noite e guarda os últimos `JC_LOG_DIAS` arquivos.

Como investigar problemas com esses logs: [LOGS_E_DIAGNOSTICO.md](LOGS_E_DIAGNOSTICO.md).

## Falhas

Se a pasta não puder ser gravada (sem permissão, disco cheio), o site e o Flask
continuam funcionando; o PHP avisa só no `error_log` do servidor.
