# Rotas e endpoints

> Gerado na auditoria de documentação de 10/10/2026 sobre a versão **v4.00** (tag `v4.00`). Legenda: **[C]** confirmado no código · **[I]** inferido (com a evidência indicada) · **[NC]**/**[N]** não confirmado.

Todas as entradas HTTP do sistema: páginas PHP (GET/POST), endpoints AJAX (JSON) e rotas do Flask. Não existe roteador central: **cada arquivo `.php` é uma rota** (o caminho da URL é o caminho do arquivo a partir da pasta do projeto, por exemplo `http://localhost/JovemConnect/Vagas/listar.php`).

## Convenções comuns

- **CSRF**: todo POST que altera estado chama `csrf_require()` (`includes/csrf.php`). Formulários enviam o campo `csrf_token`; chamadas `fetch` recebem o cabeçalho `X-CSRF-Token` (injetado por `csrf_js()`). Falha → HTTP 403 (HTML ou JSON) e evento `csrf.recusado` em `logs/seguranca.log`.
- **Autenticação**: sessão PHP com `jovem_id` ou `empresa_id` (`includes/session.php`). Sem sessão, as páginas redirecionam (302) para o login; os endpoints JSON respondem 401.
- **Erro inesperado**: exceção não tratada em qualquer página que inclua `includes/config.php` → HTTP 500 genérico (HTML ou `{"error": ...}`), detalhes só em `logs/app.log` (`erro.inesperado`).
- **Banco indisponível**: `includes/config.php` responde 503 em texto.
- **Flask**: escuta só em `127.0.0.1`, na porta 5000 por padrão (ou a próxima livre de 5001–5010); o navegador nunca o chama diretamente (só o PHP, via cURL, no endereço de `jc_ia_url()`).

## Resumo por área

| Área | Páginas / endpoints |
|---|---|
| Público | `index.php`, `comecar.php`, `documentos.php` |
| Conta | `jovem/login.php`, `empresa/login.php`, `jovem/processar_login.php`, `empresa/processar_login.php`, `processar_login.php` (legado), `logout.php`, `recuperar_senha.php`, `verificacoes/*.php`, `jovem/excluir_conta.php`, `empresa/excluir_conta.php`, `dashboard/index.php` |
| Cadastro e perfil | `jovem/cadastro.php`, `empresa/cadastro.php`, `jovem/perfil.php`, `empresa/perfil.php`, `empresa/locais.php`, `uploads/curriculos.php`, `uploads/ver_curriculo.php` |
| Vagas | `Vagas/listar.php`, `Vagas/criar.php`, `Vagas/minhas.php`, `Vagas/candidatar.php`, `Vagas/candidatos.php`, `Vagas/favoritar.php`, `Vagas/favoritos.php`, `candidaturas/minhas.php`, `dashboard/jovem.php`, `dashboard/empresa.php` |
| IA | `IA/avaliar_ajax.php`, `IA/assistente_ajax.php`, `Vagas/avaliar.php`, `Vagas/curriculoIA.php` (não linkada) |
| Flask | `POST /avaliar`, `POST /curriculo`, `POST /analise_contextual`, `POST /assistente`, `POST /moderacao` (Stage 7), `GET /ping` |
| Central administrativa (Stage 7) | `admin/login.php`, `admin/logout.php`, `admin/index.php`, `admin/contas.php`, `admin/conta.php`, `admin/vagas.php`, `admin/vaga.php`, `admin/moderacao.php`, `admin/auditoria.php`, `admin/seguranca.php`, `admin/configuracoes.php`, `admin/ia_moderacao.php`; `Vagas/denunciar.php`; linha de comando `scripts/admin.php` |
| Não são rotas (incluídos) | `includes/*.php`, `functions.php` (não usado) — acessá-los direto pelo navegador executa o arquivo sem saída útil [I] |

## Módulo A — Autenticação, sessão, segurança base e contas

| Caminho | Método | Autenticação | Permissão/dono | Parâmetros | Validações | Banco | Serviços externos | Resposta | Códigos/erros | Efeitos colaterais |
|---|---|---|---|---|---|---|---|---|---|---|
| `/jovem/login.php` | GET | visitante (logado é redirecionado) | — | — | — | — | — | HTML (aba jovem) | 302 → `../dashboard/jovem.php` / `../dashboard/empresa.php` | consome `erro_login`/`sucesso_login`; cria `csrf_token` |
| `/empresa/login.php` | GET | visitante | — | — | — | — | — | HTML (aba empresa) | 302 → painéis | idem |
| `/jovem/processar_login.php` | POST | visitante | — | `email`, `senha`, `csrf_token` (`tipo_usuario` ignorado) | email válido, senha não vazia, CSRF | SELECT `jovens`; UPDATE `jovens.ultimo_login` | — | 302 | GET → `login.php`; CSRF → 403; erro → `login.php` + `erro_login` | sessão `jovem_id`/`jovem_nome`, regenera ID, log |
| `/empresa/processar_login.php` | POST | visitante | — | idem | idem | SELECT `empresas` | — | 302 | idem | sessão `empresa_id`/`empresa_nome`, regenera ID, log |
| `/processar_login.php` | GET/POST | qualquer | — | ignorados | — | — | — | 302 → `dashboard/jovem.php`, `dashboard/empresa.php` ou `jovem/login.php` | — | nenhum (legado neutralizado) |
| `/logout.php` | GET | qualquer | — | — | nenhuma (sem CSRF) | — | — | 302 → `index.php` | — | log `logout`; destrói sessão e cookie |
| `/recuperar_senha.php` | GET | qualquer | — | `reenviar` (opcional) | — | — | — | HTML da etapa atual; `?reenviar` → 302 para si | — | `?reenviar` limpa estado de recuperação |
| `/recuperar_senha.php` (etapa 1) | POST | qualquer | — | `acao_solicitar_codigo`, `email`, `csrf_token` | CSRF, email válido | SELECT `jovens`/`empresas`; UPDATE `codigo_recuperacao`, `expiracao_recuperacao` | SMTP (PHPMailer) | HTML etapa 2 | 403 CSRF; erro de e-mail inválido | grava código; envia e-mail; sessão `recuperacao_*` |
| `/recuperar_senha.php` (etapa 2) | POST | qualquer | via sessão | `acao_validar_codigo_recuperacao`, `codigo`, `csrf_token` | CSRF, `hash_equals`, expiração | SELECT código/expiração | — | HTML etapa 3 ou erro | 403 CSRF | `recuperacao_codigo_ok=true` |
| `/recuperar_senha.php` (etapa 3) | POST | qualquer | via sessão | `acao_definir_nova_senha`, `nova_senha`, `confirmar_senha`, `csrf_token` | CSRF, flag da sessão, ≥ 8, iguais, expiração | SELECT expiração; UPDATE `senha` + limpa código | — | 302 → `jovem/login.php` ou `empresa/login.php` | 403 CSRF; 302 para si se sessão perdida | troca senha; `sucesso_login` |
| `/verificacoes/escolher_verificacao.php` | GET | jovem ou empresa | própria conta (ID da sessão) | — | — | SELECT `email, telefone, *_verificado` | — | HTML | 302 → `../index.php` (sem sessão) / `../dashboard/index.php` (já verificado) | — |
| `/verificacoes/verificar_email.php` | GET | jovem ou empresa | própria conta | — | — | SELECT; UPDATE `codigo_verificacao`, `expiracao_codigo` (se não houver código válido) | SMTP | HTML | 302 → `../index.php` / `../dashboard/index.php` | envia e-mail automaticamente |
| `/verificacoes/verificar_email.php` | POST | jovem ou empresa | própria conta | `acao_verificar`+`codigo` **ou** `acao_reenviar`; `csrf_token` | CSRF, `hash_equals`, expiração | SELECT; UPDATE `email_verificado=1` / novo código | SMTP (reenvio) | 302 → `../dashboard/index.php?verified=1` ou HTML com mensagem | 403 CSRF | marca verificado / reenvia |
| `/verificacoes/verificar_telefone.php` | GET | jovem ou empresa | própria conta | — | — | SELECT | — | HTML | 302 → `../index.php` / `../dashboard/index.php` | — |
| `/verificacoes/verificar_telefone.php` | POST | jovem ou empresa | própria conta | `acao_enviar_sms`+`telefone` **ou** `acao_validar_codigo`+`codigo`; `csrf_token` | CSRF, `normalizarTelefoneE164`, `hash_equals`, expiração | UPDATE `telefone, codigo_telefone, expiracao_telefone` / `telefone_verificado=1` | Twilio | 302 → `../dashboard/index.php?phone_verified=1` ou HTML | 403 CSRF | altera telefone da conta; envia SMS |
| `/jovem/excluir_conta.php` | POST | jovem | só a própria (`jovem_id` da sessão) | `confirmar=1`, `csrf_token` | CSRF, confirmação | SELECT `curriculo`; DELETE `jovens` (+ cascata) | — | 302 → `../index.php?conta=excluida` | sem sessão → `login.php`; GET/sem confirmar → `perfil.php`; 403 CSRF | apaga PDF; destrói sessão; log |
| `/empresa/excluir_conta.php` | POST | empresa | só a própria | `confirmar=1`, `csrf_token` | CSRF, confirmação | DELETE `empresas` (+ cascata) | — | 302 → `../index.php?conta=excluida` | idem | destrói sessão; log |
| `/dashboard/index.php` | GET | qualquer | — | `verified`, `phone_verified` | — | — | — | 302 → `jovem.php`, `empresa.php` (com `?verificado=email|telefone`) ou `../index.php` | — | — |

Não há rotas Flask neste módulo.

## Módulo B — Cadastro, perfis, locais de trabalho e currículo

| Caminho | Método | Autenticação | Permissão/dono | Parâmetros | Validações | Banco | Serviços externos | Resposta | Códigos/erros | Efeitos colaterais |
|---|---|---|---|---|---|---|---|---|---|---|
| `jovem/cadastro.php` | GET | visitante (jovem/empresa logados são redirecionados) | — | — | — | — | — | HTML do formulário (etapa 1) | 302 para os painéis | — |
| `jovem/cadastro.php` | POST | visitante | — | campos listados em [ARQUIVOS_E_FUNCOES.md](ARQUIVOS_E_FUNCOES.md) | CSRF; campos; formação; ≤ 15 habilidades; aceite; duplicidade e-mail/CPF | SELECT jovens; transação INSERT jovens + habilidades_jovem + UPDATE termos | ViaCEP (só no navegador) | 302 `../verificacoes/escolher_verificacao.php` ou HTML com erros | 403 CSRF; 1062 → mensagem; outros → `geral` + log | sessão `jovem_id/jovem_nome`, regenera o id; logs `cadastro.*` |
| `empresa/cadastro.php` | GET | visitante | — | — | — | `SHOW COLUMNS empresas` | — | HTML | 302 | — |
| `empresa/cadastro.php` | POST | visitante | — | campos listados em [ARQUIVOS_E_FUNCOES.md](ARQUIVOS_E_FUNCOES.md) | CSRF; campos; CEP; UF; site; aceite; duplicidade e-mail/CNPJ | SELECT + INSERT empresas + UPDATE termos (sem transação) | BrasilAPI, ViaCEP (navegador) | 302 verificação ou HTML com erros | 403; 1062; `geral` | sessão `empresa_id/empresa_nome`; logs |
| `jovem/perfil.php` | GET | jovem ou empresa | dono; empresa só com `jc_empresa_acesso_candidato` | `id`, `saved`, `vaga`, `voltar` | intval; `voltar` só `candidatos` | SELECT jovens (colunas por visão), formacao_jovem, habilidades_jovem, experiencias_jovem, candidaturas+vagas | — | HTML (visão do dono ou da empresa) | 302 login / painel / perfil próprio; 404 "Perfil indisponível"; 404 texto | log `acesso.negado` |
| `jovem/perfil.php` | POST `action=update_perfil` | jovem | dono | ver tabela | CSRF; nome/e-mail; e-mail único; formação | SELECT/UPDATE jovens | — | 302 `?saved=1/2/3/4` | 403; exceção → 500 | zera `email_verificado` se o e-mail mudar; sessão `jovem_nome` |
| `jovem/perfil.php` | POST `add_formacao` / `del_formacao` | jovem | dono (`AND jovem_id=?`) | `form_*` / `form_id` | estrutura; ano 4 dígitos | INSERT/DELETE formacao_jovem | — | 302 `#formacao` / `?saved=4#formacao` | 403 | — |
| `jovem/perfil.php` | POST `add_habilidade` / `del_habilidade` | jovem | dono | `habilidade` / `hab_id` | 80 caracteres; sem repetir | SELECT+INSERT IGNORE / DELETE habilidades_jovem | — | 302 `#habilidades` | 403 | — |
| `jovem/perfil.php` | POST `add_experiencia` / `del_experiencia` | jovem | dono | `exp_*` / `exp_id` | título obrigatório | INSERT/DELETE experiencias_jovem | — | 302 `#experiencias` | 403 | — |
| `../IA/avaliar_ajax.php` (chamado pelo perfil) | POST JSON | jovem | só a sessão do jovem (ignora o `jovem_id` do corpo) | `{action:'curriculo'}` | CSRF via cabeçalho (`csrf_js`) | SELECT resumo, habilidades, formações | Flask `/curriculo` no endereço de `jc_ia_url()` (+ Gemini) | JSON `{mensagem, sugestoes[]}` | 403; 503 "Servidor de IA indisponível." | — (endpoint de outro módulo) |
| `empresa/perfil.php` | GET | empresa | própria | `saved` | — | SELECT empresas, vagas; `jc_locais_da_empresa` | — | HTML | 302 login | — |
| `empresa/perfil.php` | POST | empresa | própria | campos listados em [ARQUIVOS_E_FUNCOES.md](ARQUIVOS_E_FUNCOES.md) | CSRF; nome/e-mail/CNPJ/CEP; e-mail/CNPJ únicos | UPDATE empresas (2x); UPDATE vagas (sincronização) | ViaCEP (navegador) | 302 `?saved=1/2/3` | 403; exceção → 500 | zera `email_verificado`; sessão `empresa_nome`; altera cidade/UF/CEP das vagas `local_trabalho='empresa'` |
| `empresa/locais.php` | GET | empresa | própria | `editar`, `ok` | — | SELECT empresas, empresa_locais (+ COUNT vagas) | — | HTML | 302 login | — |
| `empresa/locais.php` | POST `acao=salvar` | empresa | `$do_proprio` | `local_id`, `tipo`, `nome`, endereço | CSRF; `jc_local_erro` | INSERT/UPDATE empresa_locais; UPDATE vagas | ViaCEP (navegador) | 302 `?ok=adicionado/editado/erro` ou `#formLocal` | 403 | logs `local.*`; sessão `locais_erro/form` |
| `empresa/locais.php` | POST `acao=remover` | empresa | `$do_proprio` | `local_id` | não pode estar em uso | SELECT COUNT vagas; DELETE empresa_locais | — | 302 `?ok=removido/em_uso/erro` | 403 | logs |
| `uploads/curriculos.php` | POST multipart | jovem | o próprio (id da sessão) | `curriculo` | CSRF; erro de upload; ≤ 5 MB; `%PDF` | UPDATE jovens.curriculo | — | 302 `../jovem/perfil.php?saved=1/2` | 403; GET → 302 | grava/sobrescreve `uploads/curriculos/curriculo_{id}.pdf`; mensagens na sessão |
| `uploads/ver_curriculo.php` | GET | jovem ou empresa | dono, ou `jc_empresa_pode_ver_candidato` | `id` | int > 0 | SELECT jovens.curriculo; candidaturas | — | `application/pdf` inline | 302 login; 404 texto | log `acesso.negado` |
| `uploads/curriculos/*` | GET | — | bloqueado | — | `.htaccess` | — | — | 403 (Apache) | — | — |

Não há rotas Flask neste módulo; o Flask só é usado indiretamente pelo IA Advisor.

## Módulo C — Vagas, candidaturas, favoritos e painéis

| Caminho | Método | Autenticação | Permissão/dono | Parâmetros | Validações | Banco | Serviços externos | Resposta | Códigos/erros | Efeitos colaterais |
|---|---|---|---|---|---|---|---|---|---|---|
| `Vagas/listar.php` | GET | jovem | qualquer vaga | `busca`, `cidade`, `estado`, `area` | trim; UF upper; nenhum limite | SELECT vagas+empresas+favoritos; DISTINCT area | — | HTML listagem | 302 → `../jovem/login.php` sem sessão | — |
| `Vagas/listar.php?id=N` | GET | jovem | qualquer vaga | `id`, `voltar` (painel/favoritos/candidaturas) | intval; lista fixa | SELECT vagas, empresas, requisitos_vaga, beneficios_vaga, avaliacoes_ia, candidaturas, empresa_locais | link Google Maps | HTML detalhes | 302 → `listar.php` se vaga não existe | — |
| `Vagas/criar.php` | GET | empresa | — | `error` | lista fixa de mensagens | SHOW TABLES/COLUMNS; empresas; empresa_locais | ViaCEP (navegador, só modo antigo) | HTML form | 302 → `../empresa/login.php` | — |
| `Vagas/criar.php` | POST | empresa | local precisa ser da empresa | `titulo`, `descricao`, `tipo_contrato`*, `cidade`, `estado`, `cep`, `salario`, `carga_horaria`, `area`, `requisitos`, `beneficios`, `local_trabalho`, `local_*`, `csrf_token` | CSRF; 3 obrigatórios; local (cidade+UF, CEP 8) | INSERT vagas, empresa_locais (opcional, transação), requisitos_vaga, beneficios_vaga | — | 302 | `?error=missing/local/db`; 403 CSRF; sucesso → `minhas.php?publicada=1` | log `vaga.criada`; possível novo local da empresa |
| `Vagas/minhas.php` | GET | empresa | só vagas próprias | `deleted`, `publicada` | isset | SELECT vagas + COUNT candidaturas | — | HTML | 302 login | — |
| `Vagas/minhas.php` | POST | empresa | `WHERE empresa_id=?` | `action=delete`, `vaga_id`, `csrf_token` | CSRF; intval | DELETE vagas (+cascata) | — | 302 `minhas.php?deleted=1` | 403 CSRF | apaga candidaturas, favoritos, requisitos, benefícios, avaliações (e mensagens_chat); log `vaga.removida`/`vaga.remocao_recusada` |
| `Vagas/candidatar.php` | GET | jovem | — | `vaga`/`vaga_id`, `result`, `voltar` | intval; lista fixa | SELECT vagas+empresas, candidaturas | — | HTML (confirmação/ok/duplicate/error) | 302 → `../dashboard/jovem.php` se vaga inválida | — |
| `Vagas/candidatar.php` | POST | jovem | — | `vaga_id`, `voltar`, `csrf_token` | CSRF; vaga existe; duplicidade (SELECT + UNIQUE 1062) | SELECT vagas, candidaturas; INSERT candidaturas (`status='Recebido'`) | — | 302 `candidatar.php?vaga_id=X&result=ok|duplicate|error[&voltar]` | 403 CSRF; 302 painel | log `candidatura.criada`/`candidatura.erro` |
| `Vagas/candidatos.php` | GET | empresa | vaga da empresa | `vaga`/`id`, `voltar` (painel/minhas/perfil), `saved` | intval; ownership | SELECT vagas, candidaturas+jovens | — | HTML | 302 → `../empresa/perfil.php` se vaga inválida/alheia | — |
| `Vagas/candidatos.php?vaga=N` | POST (XHR) | empresa | vaga da empresa + UPDATE com `v.empresa_id` | `candidatura_id`, `status`, header CSRF | 5 status válidos | UPDATE candidaturas.status; SELECT ownership | — | JSON `{ok,status}` / `{error}` | 400, 403 (CSRF ou dono) | log `candidatura.status`; log segurança `acesso.negado` |
| `Vagas/candidatos.php?vaga=N` | POST (form) | empresa | idem | `candidatura_id`, `status`, `csrf_token` | 5 status válidos (inválido é ignorado em silêncio) | UPDATE candidaturas.status | — | 302 `candidatos.php?vaga=N&saved=1` | 403 CSRF | log `candidatura.status`/`_sem_mudanca` |
| `Vagas/favoritar.php` | POST (XHR) | jovem | `jovem_id` da sessão | `vaga_id`, header CSRF | intval; vaga existe | SELECT vagas, favoritos; INSERT/DELETE favoritos | — | JSON `{action,favorito,vaga_id}` / `{error}` | 401, 403, 405, 400, 404, 500 | toggle do favorito |
| `Vagas/favoritar.php` | POST (form) | jovem | idem | `vaga_id`, `csrf_token` | idem | idem | — | 302 `listar.php?id=X&favorito=added|removed` | 302 login / painel / `painel?erro=favorito` | idem |
| `Vagas/favoritar.php` | GET | — | — | — | — | — | — | 302 `../dashboard/jovem.php` (ou 405 JSON se XHR) | — | — |
| `Vagas/favoritos.php` | GET | jovem | só os próprios | `favorito` | `=== 'removed'` | SELECT favoritos+vagas+empresas | — | HTML | 302 login | — |
| `candidaturas/minhas.php` | GET | jovem | só as próprias | — | — | SELECT candidaturas+vagas+empresas | — | HTML (header/footer) | 302 login | — |
| `dashboard/jovem.php` | GET | jovem | — | `area`, `verificado` | trim | DISTINCT area; SELECT vagas+empresas | — | HTML | 302 login | — |
| `dashboard/empresa.php` | GET | empresa | candidaturas às vagas próprias; fallback: jovens públicos da área | `verificado` | — | SELECT empresas, vagas, jovens+candidaturas (ou jovens por área LIMIT 12) | — | HTML | 302 login | — |
| `IA/avaliar_ajax.php` (chamado por este módulo) | POST JSON | jovem ou empresa | jovem: sessão; empresa: dono da vaga + `jc_empresa_pode_ver_candidato` | `action` (padrão `match`), `vaga_id`, `jovem_id` | CSRF (header) | avaliacoes_ia (SELECT/INSERT … ON DUPLICATE) e dados de match | Flask `/avaliar` (timeout 6 s), fallback PHP | JSON `{score, analise, motivos?, do_cache, fonte?}` | 400, 401, 403, 404, 405, 500 | cache de nota; logs `match.*` (documentado no módulo da IA) |

## Módulo D — IA, Match e Assistente

| Caminho | Método | Autenticação | Permissão/dono | Parâmetros | Validações | Banco | Serviços externos | Resposta | Códigos/erros | Efeitos colaterais |
|---|---|---|---|---|---|---|---|---|---|---|
| `IA/avaliar_ajax.php` (`action:"match"` ou ausente) | POST JSON | sessão jovem ou empresa | jovem: sempre o próprio; empresa: vaga dela + `jc_empresa_pode_ver_candidato` | `vaga_id`, `jovem_id` (empresa) | CSRF; JSON; IDs > 0; existência | SELECT jovens, vagas, local, avaliacoes_ia; INSERT/UPDATE avaliacoes_ia | Flask `/avaliar` (6 s) só para par novo | `{score, analise, motivos?, do_cache, fonte?/atualizado?}` | 401, 405, 403 (CSRF/dono), 400, 404 | grava cache; logs `match.calculado`, `match.divergencia`, `flask.falha` |
| `IA/avaliar_ajax.php` (`"analise"`) | POST JSON | idem | idem | idem | idem | idem + INSERT/UPDATE se cache ausente/divergente | nenhum | `{score, rotulo, texto, categorias, ajuste, fortes, reduziram, local, limites}` | idem | grava cache |
| `IA/avaliar_ajax.php` (`"contexto"`) | POST JSON | idem | idem | idem | idem + limite 20/600 s por sessão | SELECT jovens, vagas, local, avaliacoes_ia, habilidades_jovem, experiencias_jovem, formacao_jovem, requisitos_vaga | Flask `/analise_contextual` (20 s) → Gemini | `{disponivel:true, analise}` ou `{disponivel:false}` | idem (200 mesmo indisponível) | atualiza `$_SESSION['jc_ctx_chamadas']`; log `match.contexto` |
| `IA/avaliar_ajax.php` (`"curriculo"`) | POST JSON | sessão | só jovem (empresa → 403) | — (`jovem_id` enviado é ignorado) | CSRF | SELECT jovens.resumo_profissional, habilidades_jovem, formacao_jovem | Flask `/curriculo` (6 s) | `{mensagem, sugestoes}` | 403, 503 "Servidor de IA indisponível." | nenhum |
| `IA/avaliar_ajax.php` (outra action) | POST | sessão | — | `action` | — | — | — | `{"error":"Ação '…' desconhecida."}` | 400 | — |
| `IA/assistente_ajax.php` | POST JSON | **nenhuma** (visitante permitido) | — | `mensagem`, `historico[]`, `pagina` | CSRF; corpo ≤ 20 000 bytes; mensagem string não vazia, ≤ 550 chars originais (corta em 500); histórico ≤ 6 × 600; `pagina` `^[a-z]{2,20}$`; limites 8/min e 60/h por sessão, 40/min por IP | nenhum | Flask `/assistente` (20 s) → Gemini | `{fonte:"ia", resposta, mascarado}` / `{fonte:"local", mascarado}` | 400, 403, 405, 429 | `$_SESSION['jc_assist']`; arquivo de contador por IP; logs `assistente.resposta`, `assistente.limite`; "reembolso" do limite em `sem_chave`/`flask_indisponivel` |
| `Vagas/avaliar.php` | GET | sessão jovem ou empresa (senão → `../index.php`) | jovem: próprio; empresa: dona da vaga + relação com candidato | `vaga_id`, `jovem_id` (empresa) | `FILTER_VALIDATE_INT` | SELECT vagas, jovens, local | (via AJAX) | HTML | mensagens de erro na página | log `acesso.negado` |
| `Vagas/curriculoIA.php` | GET | sessão jovem (senão → `../jovem/login.php`) | próprio | — | — | SELECT jovens, habilidades_jovem, formacao_jovem | — | HTML com formulário | — | — |
| `Vagas/curriculoIA.php` | POST form | sessão jovem | próprio | `csrf_token`, `resumo` | `csrf_require()`; `trim` | + UPDATE `jovens.resumo_profissional` (se mudou e resposta válida) | Flask `/curriculo` (10 s) | HTML com resultado ou erro | 403 HTML (CSRF) | altera o resumo do perfil |
| Flask `POST /avaliar` | POST JSON | nenhuma (só 127.0.0.1) | — | 9 campos | objeto JSON | — | Gemini SDK antigo (opcional) | `{score, analise, motivos, status}` | 400 | log `flask.requisicao` |
| Flask `POST /curriculo` | POST JSON | nenhuma | — | `resumo`, `habilidades`, `formacoes` | objeto JSON | — | Gemini SDK antigo (opcional) | `{mensagem, sugestoes, status}` | 400 | log |
| Flask `POST /analise_contextual` | POST JSON | nenhuma | — | payload de `jc_match_payload_ia` | objeto; ≤ 24 000 chars | — | Gemini REST (`CTX_TIMEOUT`) | `{disponivel, analise?, do_cache?, motivo?}` | 400 `entrada_invalida` | cache em memória 6 h; logs `gemini.falha` |
| Flask `POST /assistente` | POST JSON | nenhuma | — | `mensagem`, `historico`, `contexto` | objeto; ≤ 12 000; mensagem não vazia | — | Gemini REST | `{disponivel, resposta?, motivo?}` | 400 `entrada_invalida` | logs |
| Flask `GET /ping` | GET | nenhuma | — | — | — | — | — | status JSON | — | não loga |

## Módulo F — Central administrativa, moderação e denúncias (Stage 7)

Convenções da Central (`admin/`): sessão própria `JCADMIN`; todas as páginas começam com
`jc_admin_exigir('permissão')` (sem sessão → 303 para `login.php`; sem a migração → 503 "Central
indisponível"; sem permissão → 403 e registro `acesso.negado`); POST exige o token `_csrf` (ou o cabeçalho
`X-CSRF-Token`) e `Origin` do próprio site (falha → 403 e registro `csrf.recusado`); toda ação que altera
algo exige "Confirmo" + justificativa (10–500) e entra no histórico (`admin_auditoria`) na mesma transação;
respostas com `Cache-Control: no-store`, CSP com *nonce* e `X-Frame-Options: DENY`. Redirecionamentos após POST
usam 303. Detalhes em [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md).

| Caminho | Método | Autenticação | Permissão/dono | Parâmetros | Validações | Banco | Serviços externos | Resposta | Códigos/erros | Efeitos colaterais |
|---|---|---|---|---|---|---|---|---|---|---|
| `admin/login.php` | GET | — | — | `motivo` (lista fixa) | enum | — | — | HTML (formulário ou "ainda não foi instalada") | — | — |
| `admin/login.php` | POST | — | conta ativa, papel conhecido | `email`, `senha`, `codigo`, `_csrf` | CSRF; limite 5/e-mail e 20/IP em 15 min; senha (bcrypt); TOTP com anti-reuso | SELECT/UPDATE administradores; INSERT admin_tentativas (hashes); INSERT admin_auditoria | — | 303 `index.php` / HTML com erro genérico | atraso progressivo 0,5–4 s | sessão nova (ID trocado); registro `login` (sucesso/falha/negado); sem registro → entrada cancelada |
| `admin/logout.php` | GET / POST | sessão Central | — | `_csrf` (POST) | CSRF | INSERT admin_auditoria | — | GET: botão "Sair"; POST: 303 `login.php?motivo=logout` | — | sessão destruída, registro `logout` |
| `admin/index.php` | GET | sessão Central | `painel.ver` | — | — | COUNT/SUM agregados (uma consulta por tabela); últimas ações | — | HTML painel | 303/403/503 | — |
| `admin/contas.php` | GET | sessão Central | `contas.ver` | `q`, `tipo`, `status` (ativa/suspensa), `de`, `ate`, `p` | tamanho; datas válidas; `%`/`_` escapados | SELECT jovens UNION ALL empresas (20 por página) | — | HTML (e-mail parcialmente oculto) | — | — |
| `admin/conta.php` | GET | sessão Central | `contas.ver` | `tipo` (jovem/empresa), `id` | enum, inteiro | SELECT conta (sem CPF, nascimento, telefone, CEP, senha), habilidades/experiências, vagas recentes, histórico | — | HTML ficha | 404 conta inexistente | — |
| `admin/conta.php` | POST `acao=editar` | sessão Central | `contas.editar` + reautenticação (5 min) | `tipo`, `id`, `campos[...]`, `justificativa`, `confirmo` | mapa de campos + camadas 1/2 (bloqueio) | UPDATE só o que mudou; INSERT admin_auditoria (nomes dos campos) | — | 303 ficha / HTML com erros | 403 CSRF | registro `conta.editada` |
| `admin/conta.php` | POST `acao=suspender`/`reativar` | sessão Central | `contas.suspender` + reautenticação | `tipo`, `id`, `motivo` (lista fixa), `justificativa`, `confirmo`, `reauth_senha`, `reauth_codigo` | motivo; estado atual (FOR UPDATE) | UPDATE `conta_status`, `conta_status_em`; INSERT admin_auditoria | — | 303 ficha com aviso | erro "já está suspensa/ativa" | sessões da conta encerradas no próximo acesso; conteúdo oculto/reexibido |
| `admin/vagas.php` | GET | sessão Central | `vagas.ver` | `q`, `estado`, `empresa`, `area`, `empresa_suspensa`, `de`, `ate`, `p` | enum, inteiros, datas | SELECT vagas+empresas | — | HTML | — | — |
| `admin/vaga.php` | GET | sessão Central | `vagas.ver` | `id` | inteiro | SELECT vaga, requisitos, benefícios, local, itens da fila, histórico | — | HTML ficha | 404 | — |
| `admin/vaga.php` | POST `acao=moderar` | sessão Central | `vagas.moderar` | `id`, `decisao` (aprovar/revisar/rejeitar/suspender), `motivo`, `justificativa`, `resolver_itens`, `confirmo` | transição permitida; motivo da lista certa | UPDATE `moderacao_status/motivo/em`; UPDATE moderacao_itens (opcional); INSERT admin_auditoria | — | 303 ficha | "não se aplica" | registro `vaga.moderada` (`ação.motivo`) |
| `admin/vaga.php` | POST `acao=editar` | sessão Central | `vagas.editar` + reautenticação | `id`, `campos[vaga.titulo/descricao/salario/carga_horaria]`, `justificativa`, `confirmo` | camadas 1/2 | UPDATE só o que mudou (local de trabalho intocado) | — | 303 ficha | — | registro `vaga.editada` |
| `admin/moderacao.php` | GET | sessão Central | `moderacao.ver` | `estado`, `risco`, `tipo`, `origem`, `p`, `item` | enum, inteiros | SELECT moderacao_itens (+ conteúdo atual do campo no registro original, para `item`) | — | HTML fila / detalhe | 404 item | — |
| `admin/moderacao.php` | POST | sessão Central | `moderacao.decidir` | `item`, `decisao`, `justificativa`, `acao_vaga`, `motivo_vaga`, `confirmo` | item pendente; decisão e ação válidas | UPDATE moderacao_itens; UPDATE vagas (se houver ação); INSERT admin_auditoria | — | 303 | item já decidido | registros `moderacao.decidida` (+ `vaga.moderada`) |
| `admin/ia_moderacao.php` | POST (fetch ou formulário) | sessão Central | `ia.consultar` | `tipo` (vaga, jovem, empresa), `id`, `_csrf` | limite 10 por 5 min por sessão | SELECT textos públicos; INSERT admin_auditoria | Flask `POST /moderacao` (20 s) → Gemini | JSON `{ok, risco, categorias, resumo, do_cache, regras_resumo}` ou HTML | `{ok:false, erro}` (sem chave, Flask fora, limite, demora, resposta inválida) | nenhum sobre vaga/fila/conta; registro `ia.consulta` |
| `admin/auditoria.php` | GET | sessão Central | `auditoria.ver` | `admin`, `acao`, `tipo`, `recurso`, `resultado`, `de`, `ate`, `p` | enum, inteiros, datas | SELECT admin_auditoria | — | HTML | — | — |
| `admin/auditoria.php` | POST | sessão Central | `auditoria.verificar` | `_csrf` | — | SELECT admin_auditoria (em blocos); leitura de `logs/seguranca*.log` | — | HTML com o resultado | — | registro `auditoria.verificada` |
| `admin/seguranca.php` | GET | sessão Central | `seguranca.ver` | `evento` (prefixo) | enum | COUNT admin_tentativas; SELECT admin_auditoria (falhas/negações); leitura de `logs/seguranca.log` | — | HTML | — | — |
| `admin/configuracoes.php` | GET | sessão Central | `configuracoes.ver` | — | — | SELECT administradores (própria conta); detecção da migração | — | HTML (ambiente, papel e permissões, minha conta) | — | — |
| `admin/configuracoes.php` | POST `acao=senha`/`sessoes` | sessão Central | `configuracoes.ver` + reautenticação | `nova_senha`, `nova_senha2`, `confirmo` | política de senha (12+, 5 diferentes, sem o e-mail, fora das comuns) | UPDATE administradores (`senha_hash`/`sessao_versao`); INSERT admin_auditoria | — | 303 | — | outras sessões encerradas |
| `Vagas/denunciar.php` | POST | jovem | vaga visível | `vaga_id`, `motivo` (lista fixa), `detalhe` (≤ 500), `voltar`, `csrf_token` | CSRF; motivo; validação do detalhe; 1 por jovem+vaga; 10/dia | INSERT denuncias; INSERT/UPDATE moderacao_itens | — | 302 `listar.php?id=N&denuncia=ok|ja|limite|invalida|erro` | sem a migração → 302 `listar.php` | nada é ocultado automaticamente |
| Flask `POST /moderacao` | POST JSON | nenhuma (só 127.0.0.1) | — | `tipo` (vaga/perfil_jovem/perfil_empresa), `campos[{nome, texto}]` (≤ 12, ≤ 16 000 bytes) | objeto; tipo; campos não vazios | — | Gemini REST (esquema JSON fechado) | `{disponivel, analise{risco, categorias, resumo, confianca}, do_cache}` ou `{disponivel:false, motivo}` | 400 `entrada_invalida` | cache em memória 1 h; log `moderacao.analise` |
| `scripts/admin.php` | linha de comando | — (pelo navegador: 404; `scripts/.htaccess` nega) | quem tem acesso ao terminal | `status`, `criar`, `listar`, `desativar`, `reativar`, `redefinir-senha`, `redefinir-mfa`, `verificar-auditoria` + opções | e-mail, nome, confirmação "CRIAR ADMINISTRADOR" para adicionais | administradores; admin_auditoria | — | texto | código de saída 1 em erro | registros `cli.*` |

### Rotas existentes que mudaram na Stage 7

- **Visibilidade** (`jc_vagas_visiveis_sql`): `Vagas/listar.php` (lista, detalhe e áreas), `dashboard/jovem.php`,
  `Vagas/candidatar.php`, `Vagas/favoritar.php` (adicionar), `Vagas/avaliar.php` e `IA/avaliar_ajax.php` (jovem)
  ignoram vagas fora do ar e de empresas suspensas; `Vagas/favoritos.php` e `candidaturas/minhas.php` mostram
  "Vaga indisponível" (sem os dados) — remover o favorito continua possível.
- **Jovens suspensos** somem para as empresas em `dashboard/empresa.php`, `Vagas/candidatos.php`,
  `jovem/perfil.php?id=` e `uploads/ver_curriculo.php` (404).
- **Login** (`jovem/processar_login.php`, `empresa/processar_login.php`): conta suspensa é recusada depois da senha
  correta (`login.bloqueado`). `includes/config.php` encerra a sessão aberta de conta suspensa (página → login com
  aviso; AJAX → 403 JSON).
- **Formulários** (cadastro e perfil de jovem e empresa, formação, habilidades, experiências, locais de trabalho,
  `Vagas/criar.php`, `Vagas/curriculoIA.php`): camadas 1 e 2 de validação/moderação; erro mostrado no campo
  (perfil: `?saved=4`/`?saved=5`; vaga: `criar.php?error=validacao` com o rascunho preservado).
- **`Vagas/criar.php`**: vaga de risco alto → `minhas.php?revisao=1` (fica "Em revisão"); demais → `publicada=1`.
- **`Vagas/minhas.php`**, `dashboard/empresa.php`, `Vagas/candidatos.php`: selo e motivo de moderação das vagas da
  empresa.

## Módulo E — Interface pública, componentes compartilhados, CSS e configuração

| Caminho | Método | Autenticação | Permissão/dono | Parâmetros | Validações | Banco | Serviços externos | Resposta | Códigos/erros | Efeitos colaterais |
|---|---|---|---|---|---|---|---|---|---|---|
| `/index.php` | GET | Visitante (logado é redirecionado) | — | `?conta=excluida` (só JS) | `isset` das chaves de sessão | — | Google Fonts (CSS `@import`); links externos gov.br/planalto | HTML da home | 302 → `dashboard/jovem.php` ou `dashboard/empresa.php` | Inicia sessão endurecida (`jc_session_start`); grava `jc-theme` no `localStorage` ao alternar tema |
| `/comecar.php` | GET | Visitante | — | — | `isset` sessão | — | Google Fonts | HTML de escolha | 302 → painel se logado | Inicia sessão |
| `/documentos.php` | GET | Nenhuma (não usa sessão) | — | `doc` = `termos` \| `privacidade` | `is_string` + lista branca (`isset($docs[...])`); inválido → `termos` | — | Google Fonts | HTML do documento | 200 sempre; header `X-Content-Type-Options: nosniff` | — |
| Modais `#modalTermos`, `#modalPrivacidade`, `#modalContato` (em `index.php`, `jovem/cadastro.php`, `empresa/cadastro.php`) | — (cliente) | — | — | gatilho `data-jc-modal-abrir` | — | — | `mailto:` | Abre overlay | — | `html.jc-sem-rolagem` enquanto aberto |
| Busca global da navbar interna (`includes/header.php`) | GET → `../Vagas/listar.php` | Jovem (só aparece para jovem) | — | `busca` | Nenhuma no formulário | (em `Vagas/listar.php`) | — | — | — | — |
| Link "Sair" (`includes/header.php`) | GET → `../logout.php` | Logado | — | — | — | — | — | — | — | Encerra sessão (em `logout.php`) |
