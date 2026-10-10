# Logs

Uma linha **JSON** por evento, na pasta `logs/` (ou `JC_LOG_DIR`). A pasta é
bloqueada para o navegador (`logs/.htaccess`) e os arquivos `*.log` ficam fora do Git.

| Arquivo | Quem grava | Conteúdo |
|---|---|---|
| `app.log` | PHP | funcionamento: erros, Match, IA, Flask, vagas, candidaturas, assistente |
| `seguranca.log` | PHP | auditoria: login, cadastro, conta, CSRF recusado, acesso negado, limite do assistente (com IP anonimizado) |
| `ia.log` | Flask | cada requisição (rota, status, tempo) e falhas do Gemini (motivo) |

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

## O que nunca é gravado

Senhas, tokens/CSRF, cookies, chaves de API, códigos de verificação, CPF, CNPJ,
telefone, e-mail, CEP e endereço, data de nascimento, currículo, textos digitados
(mensagens do assistente, prompts e respostas da IA). Campos com esses nomes viram
`[removido]`; e-mails e sequências longas de números dentro de textos são trocados
por `[e-mail]`/`[número]`. Textos são cortados e não conseguem quebrar a linha
(o JSON escapa quebras de linha), então ninguém consegue "inventar" um evento.
Erros de banco guardam só o código do erro (a mensagem pode conter SQL e dados).

## Rotação e retenção

- PHP: um arquivo por dia e um novo se passar de `JC_LOG_MAX_MB` (2 MB); o
  anterior vira `app-AAAAMMDD-HHMMSS.log`. Arquivos com mais de `JC_LOG_DIAS`
  (30) dias são apagados na próxima gravação.
- Flask: `ia.log` troca à meia-noite e guarda os últimos `JC_LOG_DIAS` arquivos.

## Falhas

Se a pasta não puder ser gravada (sem permissão, disco cheio), o site e o Flask
continuam funcionando; o PHP avisa só no `error_log` do servidor.
