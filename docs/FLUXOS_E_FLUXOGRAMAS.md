# Fluxos e fluxogramas

> Auditoria de 10/10/2026, versão **v4.00**. Os diagramas seguem os caminhos reais do código (arquivos,
> funções, tabelas e parâmetros com os nomes encontrados). **[C]** confirmado no código · **[T]** verificado
> por teste na réplica · **[I]** inferido. Fluxos que **não existem** estão registrados como ausentes, sem
> diagrama fictício. Cada fluxo responde a 10 perguntas: (1) evento, (2) quem recebe, (3) validações,
> (4) permissões, (5) funções/serviços, (6) banco, (7) respostas, (8) erros, (9) falha de dependência,
> (10) estado final.

## Sumário

- [F-00 — Fluxograma mestre](#f-00--fluxograma-mestre)
- [F-01 — Entrada no site e navegação pública](#f-01--entrada-no-site-e-navegação-pública)
- [F-02 — Cadastro de jovem](#f-02--cadastro-de-jovem)
- [F-03 — Cadastro de empresa](#f-03--cadastro-de-empresa)
- [F-04 — Login, logout, verificação e recuperação de acesso](#f-04--login-logout-verificação-e-recuperação-de-acesso)
- [F-05 — Autenticação e controle de permissões](#f-05--autenticação-e-controle-de-permissões)
- [F-06 — Edição de perfil](#f-06--edição-de-perfil)
- [F-07 — Criação, publicação e remoção de vagas](#f-07--criação-publicação-e-remoção-de-vagas)
- [F-08 — Visualização e pesquisa de vagas](#f-08--visualização-e-pesquisa-de-vagas)
- [F-09 — Candidatura a uma vaga](#f-09--candidatura-a-uma-vaga)
- [F-10 — Visualização e gerenciamento de candidaturas](#f-10--visualização-e-gerenciamento-de-candidaturas)
- [F-11 — Acesso de empresas aos perfis de candidatos](#f-11--acesso-de-empresas-aos-perfis-de-candidatos)
- [F-12 — Match Inteligente](#f-12--match-inteligente)
- [F-13 — Fluxo interno da análise de compatibilidade](#f-13--fluxo-interno-da-análise-de-compatibilidade)
- [F-14 — Integração com o serviço de IA](#f-14--integração-com-o-serviço-de-ia)
- [F-15 — Assistente](#f-15--assistente)
- [F-16 — Aceite dos termos e da política de privacidade](#f-16--aceite-dos-termos-e-da-política-de-privacidade)
- [F-17 — Exclusão de contas](#f-17--exclusão-de-contas)
- [F-18 — Falhas e indisponibilidade de serviços externos](#f-18--falhas-e-indisponibilidade-de-serviços-externos)

---

## F-00 — Fluxograma mestre

Jornada de ponta a ponta, concisa. Os processos complexos têm diagrama próprio (indicado entre parênteses).

```mermaid
flowchart TD
    V(["Visitante"]) --> H["index.php · comecar.php (F-01)"]
    H -.-> AS["assistente ✦ (F-15)"]
    H -.-> DOC["Termos e Privacidade (F-16)"]
    H --> T{"tipo de conta"}
    T -- "Sou Jovem" --> CJ["jovem/cadastro.php (F-02)"]
    T -- "Sou Empresa" --> CE["empresa/cadastro.php (F-03)"]
    T -- "Entrar" --> LG["*/login.php → */processar_login.php (F-04)"]
    CJ --> OK{"dados válidos e<br/>aceite marcado?"}
    CE --> OK
    OK -- "não" --> E1["página com os erros<br/>(etapa do 1º erro)"]
    OK -- "sim" --> D1[("INSERT jovens / empresas")]
    D1 --> VER["verificação de e-mail/telefone<br/>(opcional)"]
    LG --> AU{"credenciais<br/>corretas?"}
    AU -- "não" --> E2["erro_login genérico"]
    AU -- "sim" --> PA["dashboard/index.php"]
    VER --> PA
    PA -- "jovem_id" --> DJ["dashboard/jovem.php"]
    PA -- "empresa_id" --> DE["dashboard/empresa.php"]
    PA -.-> SAIR["logout.php → index.php"]
    DJ --> PF["perfil e currículo (F-06)"]
    DJ --> BV["buscar vagas (F-08)"]
    BV --> CA["candidatar (F-09)"]
    DE --> NV["publicar / remover vaga (F-07)"]
    DE --> GC["candidatos e status (F-10)"]
    GC --> VP["perfil do candidato (F-11)"]
    BV --> MA{"Match (F-12/F-13):<br/>Flask disponível?"}
    GC --> MA
    MA -- "sim" --> FL["Flask /avaliar"]
    MA -- "não" --> LO["cálculo em PHP"]
    PF --> D2[("jovens + formações, habilidades,<br/>experiências · PDF em uploads/")]
    CA --> D3[("candidaturas")]
    NV --> D4[("vagas · requisitos · benefícios")]
    FL --> D5[("avaliacoes_ia")]
    LO --> D5
    PA -.-> FX["falhas: 403 CSRF · 404 sem permissão ·<br/>500 erro inesperado · 503 banco (F-18)"]
```

---

## F-01 — Entrada no site e navegação pública

```mermaid
flowchart TD
    A["GET index.php"] --> S["jc_session_start()"]
    S --> J{"jovem_id na sessão?"}
    J -- "sim" --> DJ["302 dashboard/jovem.php"]
    J -- "não" --> E{"empresa_id na sessão?"}
    E -- "sim" --> DE["302 dashboard/empresa.php"]
    E -- "não" --> H["HTML da home<br/>+ modais (institucional.php)<br/>+ assistente.php"]
    H --> T{"clique"}
    T -- "Começar" --> CO["comecar.php"]
    T -- "Entrar" --> LO["jovem/login.php"]
    T -- "Termos / Privacidade / Contato" --> MO["modal (F-16)"]
    T -- "✦" --> AS["assistente (F-15)"]
    T -- "tema / ♿" --> PR["localStorage: jc-theme, jc-font-size…"]
    CO --> CJ["jovem/cadastro.php"]
    CO --> CE["empresa/cadastro.php"]
    CO --> LO
```

| # | Resposta |
|---|---|
| 1 | Visitante abre `index.php`, `comecar.php` ou `documentos.php`. |
| 2 | `index.php` e `comecar.php` (sessão via `jc_session_start`); `documentos.php` não usa sessão. |
| 3 | `documentos.php?doc=` só aceita `termos`/`privacidade` (inválido → `termos`). |
| 4 | Usuário logado é redirecionado ao painel do seu tipo. |
| 5 | `includes/institucional.php` (modais), `includes/assistente.php` (chat, acessibilidade), `includes/documentos.php`. |
| 6 | Nenhuma. |
| 7 | HTML; 302 para painéis; aviso JS em `?conta=excluida`. |
| 8 | Nenhum erro de servidor esperado. |
| 9 | Sem JavaScript os modais da home não abrem; com `localStorage` bloqueado o script final do `index.php` para antes do "revelar ao rolar" e partes da página podem ficar invisíveis [I]; sem Google Fonts, fonte do sistema. |
| 10 | Nada gravado no servidor; preferências de tema/acessibilidade no navegador. |

---

## F-02 — Cadastro de jovem

**No navegador**

```mermaid
flowchart TD
    A["GET jovem/cadastro.php"] --> L{"já logado?"}
    L -- "sim" --> P["302 painel"]
    L -- "não" --> F["formulário único em 4 etapas<br/>(jc_etapas_script)"]
    F --> JS{"JavaScript ativo?"}
    JS -- "sim" --> V["validarEtapa() a cada Continuar<br/>ViaCEP no blur do CEP<br/>montarRevisao()"]
    JS -- "não" --> T["todas as etapas visíveis"]
    V -- "enviar" --> POST["POST jovem/cadastro.php"]
    T -- "enviar" --> POST
```

**No servidor** — o envio do formulário chega por POST em `jovem/cadastro.php`:

```mermaid
flowchart TD
    POST["POST jovem/cadastro.php"] --> C
    C{"csrf_require()"} -- "inválido" --> E403["403 + csrf.recusado"]
    C -- "ok" --> VR["valida campos, formação (jc_formacao_ler_post),<br/>≤ 15 habilidades e aceite marcado"]
    VR --> VAL{"tudo<br/>válido?"}
    VAL -- "não" --> ERR["página com os erros<br/>(abre na etapa do 1º erro)<br/>log cadastro.falha"]
    VAL -- "sim" --> DUP{"e-mail ou CPF já<br/>existe? (SELECT)"}
    DUP -- "sim" --> ERR
    DUP -- "não" --> TX["transação: INSERT jovens<br/>INSERT habilidades_jovem (n)<br/>jc_termos_registrar('jovens')"]
    TX -- "exceção 1062" --> RB1["rollback + mensagem<br/>de duplicidade"]
    TX -- "outra exceção" --> RB2["rollback + 'Erro interno'<br/>+ log"]
    TX -- "ok" --> S["sessão jovem_id/jovem_nome<br/>jc_session_regenerate()<br/>log cadastro.concluido"]
    S --> VER["302 verificacoes/<br/>escolher_verificacao.php"]
```

| # | Resposta |
|---|---|
| 1 | "Sou Jovem" em `comecar.php` ou link direto. |
| 2 | `jovem/cadastro.php` (GET e POST). |
| 3 | Navegador: obrigatórios, CPF 11 dígitos, e-mail, senha ≥ 8 e confirmação. Servidor: tudo de novo + formação estruturada, até 15 habilidades, resumo ≤ 1.000, cidade "Cidade - UF", aceite. |
| 4 | Só visitante (logado → painel). |
| 5 | `csrf_require`, `jc_formacao_ler_post`, `jc_termos_registrar`, `jc_session_regenerate`, `jc_log`; ViaCEP no navegador. |
| 6 | SELECT `jovens` (duplicidade, CPF com e sem máscara); transação com INSERT `jovens` + `habilidades_jovem` + UPDATE do aceite. |
| 7 | 302 para a verificação; ou a página com erros (senhas não são repreenchidas). |
| 8 | 403 CSRF; duplicidade (1062); erro interno com rollback. |
| 9 | ViaCEP fora → cidade digitada à mão; sem a migração de termos → conta criada sem registro do aceite. |
| 10 | Conta com `perfil_publico = 0` (padrão desde a v4.60; antes, `1`), `email_verificado = 0`, sessão aberta. [T: suíte cadastro 50/50, sobre a versão anterior] |

---

## F-03 — Cadastro de empresa

```mermaid
flowchart TD
    A["GET empresa/cadastro.php"] --> EN["empresa_tem_endereco()<br/>decide campos de endereço"]
    EN --> F["formulário em 4 etapas"]
    F --> CN{"Buscar CNPJ"}
    CN -- "BrasilAPI ok" --> PRE["preenche razão social, cidade, UF, CEP"]
    CN -- "falha" --> MAN["mensagem; preenchimento manual"]
    PRE --> POST["POST empresa/cadastro.php"]
    MAN --> POST
    POST --> C{"csrf_require()"}
    C -- "inválido" --> E403["403"]
    C -- "ok" --> VAL{"CNPJ 14 dígitos, CEP, UF [A-Z]{2},<br/>site, senha, aceite?"}
    VAL -- "não" --> ERR["página com erros"]
    VAL -- "sim" --> DUP{"e-mail ou CNPJ (com máscara)<br/>já existe?"}
    DUP -- "sim" --> ERR
    DUP -- "não" --> INS["INSERT empresas<br/>jc_termos_registrar('empresas')<br/>(sem transação)"]
    INS --> S["sessão empresa_id/empresa_nome<br/>jc_session_regenerate()"]
    S --> VER["302 verificacoes/escolher_verificacao.php"]
```

| # | Resposta |
|---|---|
| 1 | "Sou Empresa". |
| 2 | `empresa/cadastro.php`. |
| 3 | CNPJ, CEP (8 dígitos), UF, site (com `https://` só para validar), senha, aceite; duplicidade de e-mail/CNPJ. |
| 4 | Só visitante. |
| 5 | `empresa_tem_endereco`, `jc_termos_registrar`, `jc_session_regenerate`; BrasilAPI e ViaCEP no navegador. |
| 6 | `SHOW COLUMNS empresas`; SELECT; INSERT `empresas` (14 colunas com a migração de endereço, 9 sem); UPDATE do aceite. |
| 7 | 302 para a verificação ou página com erros. |
| 8 | 403; 1062; erro interno. Sem transação: falha no aceite após o INSERT deixa a conta criada [I]. |
| 9 | BrasilAPI/ViaCEP fora → manual. |
| 10 | Conta da empresa e sessão aberta. [T: suíte cadastro] |

---

## F-04 — Login, logout, verificação e recuperação de acesso

### Login e logout

```mermaid
flowchart TD
    A["jovem/login.php ou empresa/login.php"] --> L{"já logado?"}
    L -- "sim" --> P["302 painel"]
    L -- "não" --> F["formulário; aba muda form.action<br/>(switchProfile)"]
    F --> POST["POST jovem/processar_login.php<br/>ou empresa/processar_login.php"]
    POST --> M{"método POST?"}
    M -- "não" --> BK["302 login.php"]
    M -- "sim" --> C{"csrf_require()"}
    C -- "inválido" --> E403["403"]
    C -- "ok" --> V{"e-mail válido e senha preenchida?"}
    V -- "não" --> FAIL["login.falha (campos_invalidos)<br/>erro_login → 302 login.php"]
    V -- "sim" --> LIM{"v4.60: limite de tentativas<br/>(5 por conta, 30 por IP em 15 min)"}
    LIM -- "atingido" --> BLQ["login.bloqueado (muitas_tentativas)<br/>Muitas tentativas… aguarde → 302 login.php"]
    LIM -- "ok" --> Q["SELECT … WHERE email = ?<br/>password_verify"]
    Q -- "falhou" --> FAIL2["login.falha (credenciais)<br/>v4.60: registra a tentativa e espera até 2 s<br/>mensagem genérica"]
    Q -- "ok" --> DEMO{"v4.60: JC_AMBIENTE=producao<br/>e conta de demonstração?"}
    DEMO -- "sim" --> BLQ2["login.bloqueado (conta_demonstracao)"]
    DEMO -- "não" --> OK["jc_sessao_entrar(): apaga a outra identidade,<br/>grava {tipo}_id/{tipo}_nome e jc_senha_ref,<br/>novo ID de sessão e token CSRF; zera o contador<br/>jovem: UPDATE ultimo_login<br/>login.sucesso"]
    OK --> D["302 ../dashboard/{tipo}.php"]
    D -.-> SAIR["GET logout.php → log logout<br/>session_unset + cookie expirado + destroy<br/>302 index.php"]
```

### Verificação de e-mail e telefone (opcional)

```mermaid
flowchart TD
    A["escolher_verificacao.php"] --> S{"sessão?"}
    S -- "não" --> I["302 ../index.php"]
    S -- "sim" --> J{"já verificado?"}
    J -- "sim" --> D["302 ../dashboard/index.php"]
    J -- "não" --> O{"opção"}
    O -- "depois" --> D
    O -- "e-mail" --> E["verificar_email.php<br/>gerarEEnviarCodigoEmail → UPDATE codigo_verificacao<br/>enviarCodigoVerificacao (PHPMailer)"]
    O -- "telefone" --> T["verificar_telefone.php<br/>UPDATE telefone, codigo_telefone<br/>enviarCodigoSMS (Twilio)"]
    E --> ENV{"vendor/ e SMTP configurados?"}
    ENV -- "não" --> NE["'Não foi possível enviar o e-mail agora'<br/>(código gravado, não recebido)"]
    ENV -- "sim" --> COD["POST código → hash_equals + validade 15 min"]
    T --> COD
    COD -- "ok" --> MARCA["UPDATE *_verificado = 1<br/>302 painel ?verificado="]
    COD -- "errado" --> MSG["mensagem + log"]
```

### Recuperação de senha (com a falha SEG-01)

> A falha SEG-01 foi **corrigida no Lote A** (12/10/2026): a autorização fica presa à conta do pedido e o código é
> de uso único. Na v4.60 entraram o intervalo e o teto de pedidos de código (resposta igual, sem revelar contas)
> e o cancelamento do código depois de 5 erros. O diagrama abaixo registra a versão v4.00, com a falha.

```mermaid
flowchart LR
    subgraph E1["Etapa 1 — pedir o código"]
        direction TB
        A1["POST acao_solicitar_codigo + email"] --> C1{"CSRF e e-mail válidos?"}
        C1 -- "não" --> X1["403 / mensagem"]
        C1 -- "sim" --> B1{"e-mail existe em jovens?<br/>senão em empresas?"}
        B1 -- "sim" --> G1["código de 6 dígitos, +15 min<br/>UPDATE codigo_recuperacao<br/>enviarCodigoVerificacao<br/>(retorno ignorado)"]
        B1 -- "não" --> N1["nada gravado"]
        G1 --> S1["sessão: etapa = 2, email_raw, tabela<br/>⚠ recuperacao_codigo_ok NÃO é apagado"]
        N1 --> S1
    end
    subgraph E2["Etapa 2 — validar o código"]
        direction TB
        A2["POST acao_validar_codigo_recuperacao"] --> C2{"hash_equals e<br/>não expirado?"}
        C2 -- "não" --> X2["'Código incorreto ou expirado'"]
        C2 -- "sim" --> OK2["recuperacao_codigo_ok = true<br/>etapa = 3"]
    end
    subgraph E3["Etapa 3 — nova senha"]
        direction TB
        A3["POST acao_definir_nova_senha"] --> C3{"tabela, email_raw e codigo_ok<br/>na sessão? senha ≥ 8 e igual<br/>à confirmação?"}
        C3 -- "não" --> X3["volta à etapa 1 / mensagem"]
        C3 -- "sim" --> EXP{"expiracao_recuperacao do email_raw<br/>ainda válida?<br/>(o código NÃO é reconferido)"}
        EXP -- "não" --> X3
        EXP -- "sim" --> UPD["UPDATE senha = password_hash<br/>limpa o código · senha.redefinida<br/>302 login + sucesso_login"]
    end
    E1 --> E2 --> E3
```

| # | Login/logout | Verificação | Recuperação |
|---|---|---|---|
| 1 | envio do formulário de login; clique em "Sair" | após cadastro ou pelo link do painel | link "Esqueceu a senha?" |
| 2 | `*/processar_login.php`; `logout.php` | `verificacoes/*.php` | `recuperar_senha.php` (3 etapas no mesmo arquivo) |
| 3 | CSRF, e-mail válido, senha preenchida, `password_verify` | CSRF, `hash_equals`, validade, `normalizarTelefoneE164` | CSRF, e-mail, `hash_equals`, validade, senha ≥ 8 |
| 4 | visitante; logout para qualquer um (GET, sem CSRF) | conta da sessão | qualquer pessoa (via sessão) |
| 5 | `jc_session_regenerate`, `jc_log_seguranca` | `gerarEEnviarCodigoEmail`, `enviarCodigoVerificacao`, `enviarCodigoSMS` | `enviarCodigoVerificacao`, `mascararEmail` |
| 6 | SELECT `jovens`/`empresas`; UPDATE `jovens.ultimo_login` | UPDATE `codigo_*`, `expiracao_*`, `*_verificado`, `telefone` | UPDATE `codigo_recuperacao`, `expiracao_recuperacao`, `senha` |
| 7 | 302 painel / login com `erro_login` | HTML com mensagens; 302 painel `?verificado=` | HTML da etapa; 302 login com `sucesso_login` |
| 8 | 403, credenciais inválidas, 500 | código errado/expirado, 403 | código errado/expirado, 403 |
| 9 | banco fora → 503 | **sem `vendor/` nada é enviado** | sem `vendor/`, tela diz "Mandamos um código" mas nada foi enviado |
| 10 | sessão do tipo autenticado / sessão destruída | `*_verificado = 1` (não libera nada a mais) | senha trocada; sessões abertas continuam válidas |

[T: login/logout em S3 regressão e S4 navegação; recuperação: SEG-01 em `t_doc.py` comprovou a falha.]

---

## F-05 — Autenticação e controle de permissões

```mermaid
flowchart TD
    R["Requisição a uma página interna"] --> S{"sessão tem a chave do tipo<br/>que a página exige?"}
    S -- "não (página)" --> LG["302 login do tipo"]
    S -- "não (JSON)" --> U401["401 JSON"]
    S -- "sim" --> P{"POST que altera estado?"}
    P -- "sim" --> C{"csrf_require()"}
    C -- "inválido" --> F403["403 + csrf.recusado"]
    C -- "ok" --> O
    P -- "não" --> O{"registro pertence ao usuário?<br/>(WHERE … = id da sessão)"}
    O -- "não" --> N["302 / 403 / 404 conforme a página<br/>(log acesso.negado quando é dado de candidato)"]
    O -- "sim" --> OK["executa"]
```

| # | Resposta |
|---|---|
| 1 | Qualquer acesso a páginas de `jovem/`, `empresa/`, `Vagas/`, `dashboard/`, `uploads/`, `IA/`. |
| 2 | A própria página (não há middleware; `verificar_autenticacao()` não é usada). |
| 3 | Presença de `jovem_id`/`empresa_id`; parâmetros inteiros (`intval`/`FILTER_VALIDATE_INT`). |
| 4 | Tipo de usuário; dono (`empresa_id`/`jovem_id` da sessão no `WHERE`); `jc_empresa_acesso_candidato` para dados de jovens. |
| 5 | `jc_session_start`, `csrf_require`, `jc_log_seguranca`. |
| 6 | Consultas com o ID da sessão. |
| 7 | 302 para login/painel, 401/403/404, ou a resposta normal. |
| 8 | Ver acima. |
| 9 | Banco fora → 503. |
| 10 | Acesso concedido apenas ao que é do usuário. [T: S3 regressão, perfil 36/36] |

Limitações: a verificação de e-mail não bloqueia nada; uma sessão pode ter os dois tipos (SEG-13); não há expiração por inatividade.

---

## F-06 — Edição de perfil

### Jovem — `jovem/perfil.php` e `uploads/curriculos.php`

```mermaid
flowchart TD
    J0["POST jovem/perfil.php (campo action)"] --> JC{"csrf_require()"}
    JC -- "inválido" --> J403["403"]
    JC -- "ok" --> JA{"qual action?"}
    JA -- "update_perfil" --> JV{"nome e e-mail válidos?<br/>e-mail livre?"}
    JV -- "não" --> J2["302 ?saved=2 / ?saved=3"]
    JV -- "sim" --> JF{"formação mudou<br/>e é inválida?"}
    JF -- "sim" --> J4["302 ?saved=4"]
    JF -- "não" --> JU["UPDATE jovens<br/>(zera email_verificado se o e-mail mudou)<br/>302 ?saved=1"]
    JA -- "add/del_formacao · add/del_habilidade ·<br/>add/del_experiencia" --> JS["INSERT/DELETE com AND jovem_id = sessão<br/>302 #âncora"]
    UP["POST uploads/curriculos.php"] --> UV{"CSRF ok, sem erro de upload,<br/>≤ 5 MB e começa com %PDF?"}
    UV -- "não" --> U2["302 ?saved=2 + mensagem"]
    UV -- "sim" --> UM["move_uploaded_file → curriculo_{id}.pdf<br/>UPDATE jovens.curriculo · 302 ?saved=1"]
```

### Empresa — `empresa/perfil.php` e `empresa/locais.php`

```mermaid
flowchart TD
    E0["POST empresa/perfil.php"] --> EV{"CSRF, campos e<br/>e-mail/CNPJ ok?"}
    EV -- "não" --> E2["302 ?saved=2 / 3"]
    EV -- "sim" --> EU["UPDATE empresas (2×)<br/>jc_vagas_sincronizar_empresa → UPDATE vagas<br/>302 ?saved=1"]
    EU ~~~ L0
    L0["POST empresa/locais.php + csrf_require()"] --> LA{"acao"}
    LA -- "salvar" --> LV{"jc_local_erro<br/>sem erro?"}
    LV -- "não" --> LE["volta ao formulário com o erro"]
    LV -- "sim" --> LI{"local_id > 0?"}
    LI -- "não (novo)" --> LN["jc_local_inserir → INSERT empresa_locais<br/>?ok=adicionado"]
    LI -- "sim" --> LP{"local é da empresa?<br/>($do_proprio)"}
    LP -- "não" --> LX["?ok=erro"]
    LP -- "sim" --> LS["UPDATE empresa_locais<br/>jc_vagas_sincronizar_local · ?ok=editado"]
    LA -- "remover" --> LQ{"local é da empresa?"}
    LQ -- "não" --> LX
    LQ -- "sim" --> LR{"alguma vaga usa o local?"}
    LR -- "sim" --> LU["?ok=em_uso"]
    LR -- "não" --> LD["DELETE empresa_locais · ?ok=removido"]
```

| # | Resposta |
|---|---|
| 1 | Botões "Salvar…", "+", "Remover", envio de PDF, formulário de locais. |
| 2 | `jovem/perfil.php`, `uploads/curriculos.php`, `empresa/perfil.php`, `empresa/locais.php`. |
| 3 | Nome/e-mail, unicidade de e-mail/CNPJ, formação (`jc_formacao_inalterado`/`jc_formacao_ler_post`), ano `(19\|20)\d{2}`, habilidade ≤ 80, PDF ≤ 5 MB com `%PDF`, `jc_local_erro`. |
| 4 | Somente o dono (ID da sessão); locais com `$do_proprio`. |
| 5 | `jc_formacao_*`, `jc_vagas_sincronizar_empresa`, `jc_vagas_sincronizar_local`, `jc_local_inserir`, `jc_locais_disponiveis`. |
| 6 | UPDATE `jovens`/`empresas`; INSERT/DELETE `formacao_jovem`, `habilidades_jovem`, `experiencias_jovem`; INSERT/UPDATE/DELETE `empresa_locais`; UPDATE `vagas`. |
| 7 | 302 com `?saved=1..4`, `?ok=…`, toasts. |
| 8 | 403 CSRF; duplicidade por corrida vira 500 (sem `catch` do 1062); `type="url"` no site pode impedir o envio do formulário da empresa. |
| 9 | Sem a migração de locais, `empresa/locais.php` mostra "ainda não estão disponíveis"; PDF maior que `post_max_size` → 403 do CSRF [I]. |
| 10 | Perfil atualizado; vagas "no endereço da empresa" acompanham o endereço. [T: formação 46/46, perfil 36/36, S5.1 90/90, S3 parte B] |

---

## F-07 — Criação, publicação e remoção de vagas

```mermaid
flowchart TD
    subgraph PUB["Publicar — Vagas/criar.php"]
        direction TB
        A["GET Vagas/criar.php"] --> E{"empresa<br/>logada?"}
        E -- "não" --> L["302 ../empresa/login.php"]
        E -- "sim" --> M{"jc_locais_<br/>disponiveis()?"}
        M -- "sim" --> FN["formulário com local:<br/>empresa · local:id · outro"]
        M -- "não" --> FA["formulário antigo<br/>(cidade/UF/CEP + ViaCEP)"]
        FN -- "POST + csrf_require()" --> O{"título, descrição<br/>e contrato?"}
        FA -- "POST + csrf_require()" --> O
        O -- "não" --> X1["302 ?error=missing"]
        O -- "sim" --> LC{"local válido e<br/>da empresa?"}
        LC -- "não" --> X2["302 ?error=local"]
        LC -- "sim" --> TX["transação: INSERT empresa_locais<br/>(se 'outro') · INSERT vagas · commit"]
        TX -- "erro" --> X3["rollback · 302 ?error=db"]
        TX -- "ok" --> RQ["INSERT requisitos_vaga / beneficios_vaga<br/>(fora da transação)"]
        RQ --> OK["log vaga.criada<br/>302 minhas.php?publicada=1"]
    end
    subgraph REM["Remover — Vagas/minhas.php"]
        direction TB
        RM["Remover → modal de confirmação"] --> D["POST action=delete + CSRF"]
        D --> DW["DELETE vagas WHERE id = ?<br/>AND empresa_id = ?<br/>(cascata do banco)"]
        DW --> DD["302 ?deleted=1<br/>(mesmo se 0 linhas)"]
    end
```

| # | Resposta |
|---|---|
| 1 | "Criar Vaga" / "Remover". |
| 2 | `Vagas/criar.php`; `Vagas/minhas.php`. |
| 3 | 3 obrigatórios; local (`jc_local_erro`); **sem** lista branca de contrato/área nem limite de tamanho no servidor (SEG-19). |
| 4 | Empresa; local e vaga precisam ser da empresa. |
| 5 | `jc_locais_disponiveis`, `jc_local_erro`, `jc_local_inserir`, `jc_log`. |
| 6 | INSERT `vagas`, `empresa_locais`, `requisitos_vaga`, `beneficios_vaga`; DELETE `vagas` com cascata. |
| 7 | 302 + toasts "Vaga publicada!" / "Vaga removida com sucesso.". |
| 8 | `?error=missing/local/db`; 403. |
| 9 | ViaCEP fora (modo antigo) → preenchimento manual. |
| 10 | Vaga visível para todos os jovens; remoção apaga o histórico. [T: S3 regressão, S5.1] |

**Ausentes:** edição de vaga, pausa, encerramento e expiração — não há código nem coluna de status (não há
diagrama para esses fluxos).

---

## F-08 — Visualização e pesquisa de vagas

```mermaid
flowchart TD
    A["dashboard/jovem.php ou busca do header"] --> J{"jovem logado?"}
    J -- "não" --> L["302 ../jovem/login.php"]
    J -- "sim" --> I{"Vagas/listar.php tem ?id?"}
    I -- "não" --> Q["SQL dinâmico com busca, cidade, estado, area<br/>LEFT JOIN favoritos · sem LIMIT"]
    Q --> H["cards + estrela + Match IA"]
    I -- "sim" --> X{"vaga existe?"}
    X -- "não" --> BL["302 listar.php"]
    X -- "sim" --> D["vaga+empresa, requisitos, benefícios,<br/>avaliacoes_ia, candidatura existente,<br/>jc_local_efetivo"]
    D --> BT{"já se candidatou?"}
    BT -- "sim" --> JA["'Já candidatado · ver status'"]
    BT -- "não" --> CA["'Candidatar-se' → F-09"]
```

| # | Resposta |
|---|---|
| 1 | Painel, busca, clique num card. |
| 2 | `Vagas/listar.php`, `dashboard/jovem.php`. |
| 3 | `trim`, UF maiúscula, `intval(id)`, `voltar` em lista fixa. |
| 4 | Somente jovem (empresas não usam esta página). |
| 5 | `jc_local_efetivo`; `fetch` para `favoritar.php` e `avaliar_ajax.php`. |
| 6 | SELECT `vagas`, `empresas`, `favoritos`, `requisitos_vaga`, `beneficios_vaga`, `avaliacoes_ia`, `candidaturas`, `empresa_locais`. |
| 7 | HTML. |
| 8 | Vaga inexistente → listagem; erro de banco → 500. |
| 9 | Sem a migração de locais, usa cidade/UF da vaga/empresa. |
| 10 | Nada alterado. Limitações: filtros de chips descartam cidade/UF; sem paginação. [T: S3, S4 navegação] |

---

## F-09 — Candidatura a uma vaga

```mermaid
flowchart TD
    A["GET Vagas/candidatar.php?vaga=X"] --> J{"jovem logado?"}
    J -- "não" --> L["302 login"]
    J -- "sim" --> V{"vaga existe?"}
    V -- "não" --> P["302 ../dashboard/jovem.php"]
    V -- "sim" --> D{"já candidatado?"}
    D -- "sim" --> DU["tela 'duplicate'"]
    D -- "não" --> CF["tela de confirmação"]
    CF --> POST["POST vaga_id + csrf_token"]
    POST --> C{"csrf_require() e vaga existe?"}
    C -- "não" --> P
    C -- "sim" --> S{"SELECT candidaturas do par"}
    S -- "existe" --> R1["302 ?result=duplicate"]
    S -- "não" --> I["INSERT candidaturas (status 'Recebido')"]
    I -- "1062" --> R1
    I -- "outra exceção" --> R2["log candidatura.erro · ?result=error"]
    I -- "ok" --> R3["log candidatura.criada · ?result=ok"]
```

| # | Resposta |
|---|---|
| 1 | "Candidatar-se" nos detalhes ou nos favoritos. |
| 2 | `Vagas/candidatar.php`. |
| 3 | CSRF, `vaga_id` > 0, vaga existe, duplicidade (SELECT + `uq_candidatura`). |
| 4 | Jovem (o ID vem da sessão). |
| 5 | `csrf_require`, `jc_log`. |
| 6 | SELECT `vagas`, `empresas`, `candidaturas`; INSERT `candidaturas`. |
| 7 | 302 para `?result=ok|duplicate|error` (PRG). |
| 8 | 403; duplicidade; erro. `?result=ok` pode ser forjado na URL (só visual). |
| 9 | Banco fora → 503. |
| 10 | Candidatura "Recebido", visível ao jovem e à empresa; **nenhuma notificação**. [T: S3 regressão] |

---

## F-10 — Visualização e gerenciamento de candidaturas

```mermaid
flowchart TD
    subgraph J["Jovem"]
        J1["candidaturas/minhas.php"] --> J2["SELECT candidaturas + vagas + empresas<br/>do jovem_id da sessão"]
        J2 --> J3["lista com badge de status"]
    end
    subgraph E["Empresa"]
        E1["Vagas/candidatos.php?vaga=X"] --> E2{"vaga é da empresa?"}
        E2 -- "não" --> E3["302 ../empresa/perfil.php"]
        E2 -- "sim" --> E4["lista candidaturas + jovens"]
        E4 --> E5["Salvar status → fetch POST (X-CSRF-Token)"]
        E5 --> E6{"CSRF ok e status em $statuses_validos?"}
        E6 -- "não" --> E7["400 / 403 JSON"]
        E6 -- "sim" --> E8["UPDATE candidaturas JOIN vagas<br/>WHERE c.id AND v.empresa_id"]
        E8 --> E9{"linhas afetadas?"}
        E9 -- "0 e não é dono" --> E10["403 + acesso.negado"]
        E9 -- "sim / mesmo status" --> E11["{ok:true,status} · badge atualizado"]
    end
    E11 -. "próximo acesso do jovem" .-> J3
```

| # | Resposta |
|---|---|
| 1 | Menu "Minhas candidaturas"; "Ver/Gerenciar candidatos". |
| 2 | `candidaturas/minhas.php`; `Vagas/candidatos.php` (GET e POST XHR/form). |
| 3 | `candidatura_id` > 0; status em 5 valores; sem JS o formulário `<noscript>` envia sempre "Em análise". |
| 4 | Jovem vê só as próprias; empresa só das próprias vagas. |
| 5 | `csrf_require(true)`, `jc_log`, `jc_log_seguranca`. |
| 6 | SELECT; UPDATE `candidaturas.status`. |
| 7 | HTML; JSON `{ok,status}`; 302 `?saved=1` sem JS. |
| 8 | 400, 403. |
| 9 | — |
| 10 | Status alterado; jovem vê ao abrir a página (sem notificação; "Visualizado" não é automático; jovem não pode retirar). [T: S3 regressão] |

O painel da empresa (`dashboard/empresa.php`) mostra só candidatos com `perfil_publico = 1` e, se não houver
nenhum, até 12 jovens públicos da área da empresa (modo de sugestão) [C].

---

## F-11 — Acesso de empresas aos perfis de candidatos

```mermaid
flowchart TD
    A["jovem/perfil.php?id=J · uploads/ver_curriculo.php?id=J<br/>Vagas/avaliar.php?jovem_id=J · avaliar_ajax.php"] --> S{"empresa logada?"}
    S -- "não (e não é o dono)" --> L["302 login"]
    S -- "sim" --> R["jc_empresa_acesso_candidato(empresa, J)"]
    R --> P{"candidatura de J a vaga da empresa<br/>OU perfil_publico = 1?"}
    P -- "não ou J não existe" --> N["404 'Perfil indisponível'<br/>acesso.negado (seguranca.log)"]
    P -- "sim" --> C{"tem candidatura?"}
    C -- "sim" --> F["visão da empresa + e-mail"]
    C -- "não" --> G["visão da empresa sem e-mail"]
    F --> PDF["currículo via ver_curriculo.php<br/>(no-store, nosniff)"]
    G --> PDF
```

| # | Resposta |
|---|---|
| 1 | Clique num candidato ou numa sugestão do painel. |
| 2 | `jovem/perfil.php`, `uploads/ver_curriculo.php`, `Vagas/avaliar.php`, `IA/avaliar_ajax.php`. |
| 3 | `id` inteiro > 0. |
| 4 | Regra de `includes/candidato.php` (dono da vaga + relação, ou perfil público). |
| 5 | `jc_empresa_acesso_candidato`, `jc_empresa_pode_ver_candidato`, `jc_candidato_indisponivel`. |
| 6 | SELECT `jovens` (só colunas da visão da empresa), `candidaturas`, `vagas`, `formacao_jovem`, `habilidades_jovem`, `experiencias_jovem`. |
| 7 | HTML do perfil; PDF inline; 404 igual para inexistente e negado. |
| 8 | 404. |
| 9 | PDF ausente no disco → 404 "Currículo não encontrado.". |
| 10 | Nunca expõe CPF, CEP, endereço, telefone ou data de nascimento (só idade). Ver SEG-03 sobre o padrão público. [T: perfil 36/36] |

---

## F-12 — Match Inteligente

```mermaid
flowchart TD
    A["clique em 'Match IA'<br/>(dashboard/jovem, Vagas/listar, dashboard/empresa, Vagas/candidatos)"] --> F["fetch POST IA/avaliar_ajax.php<br/>{action:'match', vaga_id, jovem_id}"]
    F --> S{"sessão?"}
    S -- "não" --> U401["401"]
    S -- "sim" --> C{"POST, CSRF e JSON válidos?"}
    C -- "não" --> E4["405 / 403 / 400"]
    C -- "sim" --> T{"tipo"}
    T -- "jovem" --> JJ["jovem_id = sessão"]
    T -- "empresa" --> EE{"vaga é da empresa e<br/>jc_empresa_pode_ver_candidato?"}
    EE -- "não" --> X["403 / 404 + acesso.negado"]
    EE -- "sim" --> JJ
    JJ --> LD{"jc_match_carregar: jovem e vaga existem?"}
    LD -- "não" --> N404["404"]
    LD -- "sim" --> CALC["F-13: cálculo, cache e Flask"]
    CALC --> R["{score, analise, do_cache, fonte}<br/>botão 'Match: N%' + link para Vagas/avaliar.php"]
```

| # | Resposta |
|---|---|
| 1 | Botão "Match IA"/"Avaliar Match com IA". |
| 2 | `IA/avaliar_ajax.php` (action `match`, padrão). |
| 3 | Método, CSRF (cabeçalho), JSON, IDs > 0. |
| 4 | Jovem: só ele mesmo; empresa: vaga própria + relação com o candidato. |
| 5 | `jc_match_carregar`, `jc_match_calcular`, `jc_flask_post`, `jc_match_texto_estatico`. |
| 6 | SELECT `jovens`, `vagas`, local; SELECT/INSERT … ON DUPLICATE `avaliacoes_ia`. |
| 7 | JSON; o botão mostra a nota; erro aparece no botão por 3,5 s. |
| 8 | 400, 401, 403, 404, 405. |
| 9 | Flask fora → nota calculada em PHP (`fonte:"local"`), sem diferença para o usuário. |
| 10 | Cache do par criado/atualizado. [T: S5.1 match 90/90, fallback 7/7 ×2] |

---

## F-13 — Fluxo interno da análise de compatibilidade

```mermaid
flowchart TD
    A["entrada: 9 campos (jc_match_carregar) · minúsculas + trim · acentos não são removidos<br/>cidade e área vazias valem 0 · comparações por igualdade ou substring"]
    A --> L1
    A --> R1
    A --> T1
    A --> E1
    A --> C1
    subgraph LOC["1. Localização (máx. 25)"]
        L1{"mesma<br/>cidade?"} -- "sim" --> L25["+25"]
        L1 -- "não" --> L2{"palavra (4+ letras)<br/>do jovem contida<br/>na cidade da vaga?"}
        L2 -- "sim" --> L12["+12"]
        L2 -- "não" --> L0["0"]
    end
    subgraph ARE["2. Área (máx. 25)"]
        R1{"mesma<br/>área?"} -- "sim" --> R25["+25"]
        R1 -- "não" --> R2{"uma contém<br/>a outra?"}
        R2 -- "sim" --> R13["+13"]
        R2 -- "não" --> R0["0"]
    end
    subgraph TEC["3. Curso técnico (máx. 25)"]
        T1{"técnico<br/>com curso?"} -- "não" --> T0["0"]
        T1 -- "sim" --> T2{"palavras do curso<br/>(4+ letras) na<br/>descrição ou área"}
        T2 -- "≥ 2 ou o curso inteiro" --> T25["+25"]
        T2 -- "1" --> T12["+12"]
        T2 -- "0" --> T5["+5"]
    end
    subgraph ESC["4. Escolaridade × contrato (máx. 15)"]
        E1{"Jovem<br/>Aprendiz?"} -- "sim" --> E2{"situação"}
        E2 -- "cursando" --> E15["+15"]
        E2 -- "completo" --> E8["+8"]
        E2 -- "vazia" --> E0a["0"]
        E1 -- "não" --> E3{"situação"}
        E3 -- "completo" --> E10["+10"]
        E3 -- "cursando / vazia" --> E0b["0"]
    end
    subgraph CON["5. Tipo de contrato (máx. 10)"]
        C1{"Aprendiz ou<br/>'estágio'?"} -- "sim" --> C10["+10"]
        C1 -- "não" --> C0["0"]
    end
    LOC --> S["soma dos 5 critérios"]
    ARE --> S
    TEC --> S
    ESC --> S
    CON --> S
    S --> AJ{"descrição contém 'concluído'<br/>e jovem cursando?"}
    AJ -- "sim" --> M10["−10"]
    AJ -- "não" --> FIM["limita a 0–100 → nota<br/>texto por faixa (≥ 80, ≥ 60, ≥ 40, < 40)"]
    M10 --> FIM
```

Na página `Vagas/avaliar.php` o navegador chama `action:"analise"` (sempre em PHP:
`jc_match_categorias`, `jc_match_ajuste`, `jc_match_destaques`) e, ao abrir o detalhe, `action:"contexto"`
(F-14). Tabela completa dos critérios em [IA_E_MATCH.md](IA_E_MATCH.md#match--algoritmo-exato).

| # | Resposta |
|---|---|
| 1 | Chamada interna de F-12 ou abertura de `Vagas/avaliar.php`. |
| 2 | `IA/recomendar.py::avaliar` ou `includes/match.php::jc_match_calcular` (idênticos). |
| 3 | Campos tratados como texto; vazio = 0 no critério. |
| 4 | Herdadas de F-12. |
| 5 | Funções acima. |
| 6 | Nenhuma no cálculo; o cache é tratado em `avaliar_ajax.php`. |
| 7 | `score`, `motivos`, `analise`; na página: categorias, ajuste, pontos fortes, limitações. |
| 8 | Entrada não-texto no Flask → 500 da rota → PHP usa o cálculo local [I]. |
| 9 | Flask fora → mesmo cálculo em PHP. |
| 10 | Nota determinística. [T: paridade 0 divergências em 1.205 casos; MATCH-01..05] |

---

## F-14 — Integração com o serviço de IA

```mermaid
sequenceDiagram
    autonumber
    participant N as Navegador
    participant P as IA/avaliar_ajax.php
    participant F as Flask (5000 ou 5001–5010)
    participant G as Gemini
    N->>P: POST action=contexto<br/>(+ X-CSRF-Token)
    Note over P: sessão, CSRF, dono da vaga,<br/>jc_empresa_pode_ver_candidato,<br/>limite de 20 chamadas / 600 s
    alt limite atingido
        P-->>N: {disponivel:false}<br/>(log limite_sessao)
    end
    Note over P: jc_match_payload_ia: sem nome,<br/>e-mail, CPF, IDs nem salário
    P->>F: jc_flask_post<br/>/analise_contextual (20 s)
    alt Flask fora do ar
        F--xP: sem resposta<br/>(flask_indisponivel)
    else sem GEMINI_API_KEY
        F-->>P: {disponivel:false,<br/>motivo:"sem_chave"}
    else resposta no cache (6 h)
        F-->>P: {disponivel:true, analise,<br/>do_cache:true}
    else chama o Gemini
        F->>G: generateContent<br/>(x-goog-api-key, esquema JSON)
        alt erro, 429, tempo ou JSON inválido
            G-->>F: falha
            F-->>P: {disponivel:false,<br/>motivo}
        else ok
            G-->>F: JSON
            Note over F: validar_contexto
            F-->>P: {disponivel:true, analise}
        end
    end
    Note over P: jc_match_validar_ia · log match.contexto
    P-->>N: {disponivel:true, analise}<br/>ou {disponivel:false}
    Note over N: exibe com textContent e o selo<br/>"Gerada por IA… não altera a nota"
```

| # | Resposta |
|---|---|
| 1 | Abrir "Ver análise detalhada" (contexto); Match de par novo (`/avaliar`); IA Advisor (`/curriculo`); assistente (`/assistente`). |
| 2 | `IA/avaliar_ajax.php`, `IA/assistente_ajax.php` → rotas do Flask. |
| 3 | PHP: sessão, CSRF, tamanho; Flask: JSON objeto, limites de tamanho; resposta do Gemini validada no Flask e no PHP. |
| 4 | As do PHP; o Flask não autentica (SEG-06). |
| 5 | `jc_flask_post`, `jc_match_payload_ia`, `jc_match_validar_ia`; `_ctx_chamar_gemini`, `validar_contexto`. |
| 6 | Só leitura (`jovens`, `vagas`, `habilidades_jovem`, `experiencias_jovem`, `formacao_jovem`, `requisitos_vaga`, `avaliacoes_ia`). |
| 7 | JSON. |
| 8 | Motivos: `entrada_invalida`, `sem_chave`, `chave_invalida`, `modelo_indisponivel`, `limite`, `timeout`, `indisponivel`, `resposta_invalida`, `flask_indisponivel`, `limite_sessao`. |
| 9 | Sempre degrada para "não disponível" sem afetar a nota. |
| 10 | Nada gravado no banco. [T: contexto 33/33 com Gemini simulado] |

---

## F-15 — Assistente

```mermaid
flowchart TD
    A["✦ → enviarMensagem()<br/>(≤ 500 caracteres, timeout 25 s)"] --> P["POST IA/assistente_ajax.php<br/>{mensagem, historico (6), pagina}"]
    P --> V{"POST, CSRF, corpo ≤ 20 000 B,<br/>mensagem válida?"}
    V -- "não" --> E["405 / 403 / 400"]
    V -- "sim" --> MK["jc_assist_mascarar<br/>(e-mail, CPF, CNPJ, telefone, CEP, números longos)"]
    MK --> LIM{"8/min e 60/h por sessão,<br/>40/min por IP?"}
    LIM -- "excedeu" --> R429["429 + assistente.limite"]
    LIM -- "ok" --> FL["jc_flask_post('/assistente', 20 s)"]
    FL --> OK{"resposta válida da IA?"}
    OK -- "sim" --> IA["{fonte:'ia', resposta}"]
    OK -- "sem_chave / flask_indisponivel" --> RB["devolve a cota (reembolso)<br/>{fonte:'local'}"]
    OK -- "outro erro do Gemini" --> LO["{fonte:'local'} (conta no limite)"]
    IA --> UI["mostra com textContent"]
    RB --> RP["resposta pronta (RESPOSTAS) + aviso"]
    LO --> RP
    E --> RP
```

| # | Resposta |
|---|---|
| 1 | Enviar mensagem no painel ✦ (visitantes também). |
| 2 | `IA/assistente_ajax.php` → Flask `assistente()`. |
| 3 | Ver diagrama; histórico ≤ 6 × 600; `pagina` `^[a-z]{2,20}$`. |
| 4 | Nenhuma autenticação; perfil (visitante/jovem/empresa) só como contexto. |
| 5 | `jc_assist_texto`, `jc_assist_mascarar`, `jc_flask_post`; `_assist_limpar_resposta`. |
| 6 | Nenhuma. |
| 7 | JSON; texto na tela; aviso "Resposta automática…" e "Removemos dados pessoais…" quando for o caso. |
| 8 | 400, 403, 405, 429. |
| 9 | Respostas prontas. |
| 10 | Conversa só na memória da página; log só com metadados. A IA **não executa ações**. [T: assistente 34/34] |

---

## F-16 — Aceite dos termos e da política de privacidade

```mermaid
flowchart TD
    A["clique em Termos / Privacidade<br/>(rodapé da home ou link do cadastro)"] --> J{"JavaScript?"}
    J -- "sim" --> M["modal: resumo + versão (jc_documento_resumo, jc_documento_versao)"]
    J -- "não" --> PG["documentos.php?doc=… em nova aba"]
    M --> SM{"Saiba mais?"}
    SM -- "sim" --> TC["texto completo no mesmo modal"]
    M --> PG
    C["Etapa 4 do cadastro:<br/>caixa aceite_termos (no próprio cadastro)"] --> V{"marcada?"}
    V -- "não" --> ER["erro 'aceite' — conta não é criada"]
    V -- "sim" --> R["jc_termos_registrar(tabela, id)"]
    R --> COL{"coluna termos_aceitos_em existe?"}
    COL -- "sim" --> UP["UPDATE termos_versao, termos_aceitos_em = NOW()"]
    COL -- "não" --> SK["sem registro (site continua)"]
```

| # | Resposta |
|---|---|
| 1 | Abrir um documento; enviar o cadastro. |
| 2 | `includes/institucional.php`, `documentos.php`, `includes/termos.php`. |
| 3 | `doc` em lista branca; `$_POST['aceite_termos'] === '1'`. |
| 4 | Nenhuma. |
| 5 | `jc_documento_*` (`includes/documentos.php`), `jc_termos_colunas`, `jc_termos_registrar`. |
| 6 | `SHOW COLUMNS`; UPDATE `jovens`/`empresas`. |
| 7 | Modal, página própria ou erro no cadastro. |
| 8 | Aceite ausente. |
| 9 | Sem a migração: conta criada sem registro do aceite. |
| 10 | Versão e data do aceite gravadas. Os textos são **minutas**. Abrir o documento não registra nada. [T: termos 38/38, modais 65/65] |

---

## F-17 — Exclusão de contas

```mermaid
flowchart TD
    A["perfil → modal → POST excluir_conta.php<br/>confirmar=1 + csrf_token"] --> S{"sessão do tipo?"}
    S -- "não" --> L["302 login.php"]
    S -- "sim" --> M{"POST com confirmar=1?"}
    M -- "não" --> P["302 perfil.php"]
    M -- "sim" --> C{"csrf_require()"}
    C -- "inválido" --> F["403"]
    C -- "ok" --> J{"jovem?"}
    J -- "sim" --> PDF["apaga uploads/curriculos/curriculo_{id}.pdf"]
    J -- "não" --> D
    PDF --> D["DELETE jovens|empresas WHERE id = sessão<br/>(cascata do banco)"]
    D --> LG["log conta.excluida · sessão e cookie destruídos"]
    LG --> I["302 ../index.php?conta=excluida"]
```

| # | Resposta |
|---|---|
| 1 | "Excluir conta" no perfil. |
| 2 | `jovem/excluir_conta.php`, `empresa/excluir_conta.php`. |
| 3 | CSRF e `confirmar=1`; **não pede a senha** (SEG-16). |
| 4 | Somente a própria conta (ID da sessão). |
| 5 | `csrf_require`, `jc_log_seguranca`. |
| 6 | SELECT `jovens.curriculo`; DELETE `jovens`/`empresas` + cascata (ver [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md#o-que-a-exclusão-apaga-cascata)). |
| 7 | 302 para a home com aviso. |
| 8 | 403; erro de banco → 500 (o PDF já pode ter sido apagado). |
| 9 | — |
| 10 | Conta e dados ligados removidos; não há retenção nem período de arrependimento. [T: S3 regressão] |

---

## F-18 — Falhas e indisponibilidade de serviços externos

```mermaid
flowchart TD
    A["Requisição"] --> DB{"banco disponível?"}
    DB -- "não" --> D503["503 texto (banco.indisponivel)"]
    DB -- "sim" --> X{"exceção não tratada?"}
    X -- "sim" --> E500["500 genérico (erro.inesperado)"]
    X -- "não" --> S{"usa serviço externo?"}
    S -- "Flask /avaliar" --> F1{"responde em 6 s?"}
    F1 -- "não" --> FL["cálculo PHP (flask.falha)"]
    S -- "Flask /curriculo" --> F2{"responde?"}
    F2 -- "não" --> C503["503 'Servidor de IA indisponível.'"]
    S -- "Gemini (contexto/assistente)" --> G{"chave + Flask + Gemini ok?"}
    G -- "não" --> GL["{disponivel:false} / respostas prontas"]
    S -- "SMTP / Twilio" --> M{"vendor/ + credenciais + envio ok?"}
    M -- "não" --> MF["false → mensagem de falha<br/>(recuperação: mensagem de sucesso mesmo assim)"]
    S -- "ViaCEP / BrasilAPI (navegador)" --> V{"respondem?"}
    V -- "não" --> VM["preenchimento manual"]
```

| Dependência | Se falhar | Onde ver |
|---|---|---|
| Banco | 503 em todas as páginas com dados | `app.log` → `banco.indisponivel` |
| Flask | Match local; currículo 503; assistente com respostas prontas | `app.log` → `flask.falha` |
| Gemini | sem análise contextual; respostas prontas | `ia.log` → `gemini.falha`; `app.log` → `match.contexto`, `assistente.resposta` |
| SMTP/Twilio/`vendor/` | códigos não chegam | `error_log` do PHP (não vai para `logs/`) |
| ViaCEP/BrasilAPI | preenchimento manual | console do navegador |
| Google Fonts | fonte do sistema | — |

Diagnóstico passo a passo: [LOGS_E_DIAGNOSTICO.md](LOGS_E_DIAGNOSTICO.md). [T: fallback 7/7 ×2, assistente 34/34, contexto 33/33]
