# Resultados dos testes executados

> **Classificação: CONFIDENCIAL (uso interno da equipe).** Registra roteiros de teste, inclusive de ataques reproduzidos na réplica.
> Não copie para o texto público do TCC, slides ou repositórios públicos: use a versão resumida de
> [CLASSIFICACAO.md](CLASSIFICACAO.md#versões-resumidas-para-o-tcc). Desde a v4.60 a pasta `docs/` não é
> entregue pelo Apache (confira com `scripts\seguranca\verificar_protecao.bat`).

> Gerado a partir dos arquivos de resultado da réplica de desenvolvimento (execuções de 10/10/2026 sobre o código que virou a v4.00; mudanças posteriores foram só de comentários, conferidas por comparação de tokens). "Aprovado"/"Reprovado" vem do próprio teste; nada aqui foi marcado à mão. Observações cortadas em 160 caracteres e com e-mails/caminhos substituídos. Os scripts ficam na réplica e **não fazem parte do repositório** (ver [TESTES.md](TESTES.md#testes-automatizados-da-réplica)).

## Resumo

| Suíte | Descrição | Casos | Aprovados | Log final |
|---|---|---|---|---|
| [S3R](#s3r--stage-3--regressão-funcional-http) | Stage 3 — regressão funcional (HTTP) | 155 | 155 | 155/155 |
| [S3B](#s3b--stage-3--parte-b-upload-links-permissões) | Stage 3 — parte B (upload, links, permissões) | 47 | 47 | 47/47 |
| [S3N](#s3n--stage-3--navegador) | Stage 3 — navegador | 33 | 33 | 33/33 |
| [S4N](#s4n--stage-4--navegação) | Stage 4 — navegação | 68 | 68 | 68/68 |
| [S4I](#s4i--stage-4--interface) | Stage 4 — interface | 44 | 44 | 44/44 |
| [S5M](#s5m--stage-5--modais-termos-privacidade-contato) | Stage 5 — modais (Termos, Privacidade, Contato) | 65 | 65 | 65/65 |
| [S5F](#s5f--stage-5--formação) | Stage 5 — formação | 46 | 46 | 46/46 |
| [S5U](#s5u--stage-5--formação-interface) | Stage 5 — formação (interface) | 21 | 21 | 21/21 |
| [S5V](#s5v--stage-5--identidade-visual) | Stage 5 — identidade visual | 27 | 25 | 25/27 |
| [S51M](#s51m--stage-51--locais-de-trabalho-e-match) | Stage 5.1 — locais de trabalho e Match | 90 | 90 | 90/90 |
| [S51U](#s51u--stage-51--página-de-análise-interface) | Stage 5.1 — página de análise (interface) | 101 | 101 | 101/101 |
| [S51C](#s51c--stage-51--análise-contextual-flask--gemini-simulado) | Stage 5.1 — análise contextual (Flask + Gemini simulado) | 33 | 33 | 33/33 |
| [S51F](#s51f--stage-51--flask-fora-do-ar) | Stage 5.1 — Flask fora do ar | 7 | 7 | 7/7 |
| [S51K](#s51k--stage-51--sem-chave-do-gemini) | Stage 5.1 — sem chave do Gemini | 7 | 7 | 7/7 |
| [S6C](#s6c--stage-6--cadastro-em-etapas) | Stage 6 — cadastro em etapas | 50 | 50 | 50/50 |
| [S6P](#s6p--stage-6--perfil-do-candidato-e-acesso-da-empresa) | Stage 6 — perfil do candidato e acesso da empresa | 36 | 36 | 36/36 |
| [S6A](#s6a--stage-6--animações) | Stage 6 — animações | 19 | 19 | 19/19 |
| [S6T](#s6t--stage-6--termos-e-privacidade) | Stage 6 — termos e privacidade | 38 | 38 | 38/38 |
| [S6S](#s6s--stage-6--dados-de-demonstração-banco-da-réplica) | Stage 6 — dados de demonstração (banco da réplica) | 12 | 12 | 12/12 |
| [S6I](#s6i--stage-6--assistente) | Stage 6 — assistente | 34 | 34 | 34/34 |
| [S6L](#s6l--stage-6--logs) | Stage 6 — logs | 22 | 22 | 22/22 |
| [DOC](#doc--auditoria-de-documentação-10102026) | Auditoria de documentação (10/10/2026) | 7 | 6 | — |

**Total: 959/962 aprovados.** Reprovações: ver S5V (2 casos, iguais na versão anterior v3.48) e DOC-001 (falha de segurança SEG-01, esperada).

Versões posteriores, em seções próprias: [V456](#v456--versões-455-e-456), [V458](#v458--php-74-e-80-e-exclusão-da-pasta), [V459](#v459--linux) e
[V460](#v460--correção-de-segurança-e-propriedade-intelectual) (correção de segurança; 17 verificações na réplica e os
roteiros que faltam rodar no PC). DOC-001 deixou de valer como falha depois do Lote A (SEG-01 corrigido).

## S3R — Stage 3 — regressão funcional (HTTP)

Arquivo de resultado: `tests/s3_rerun/regressao_result.json` · 155/155 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S3R-001 | cadastro jA -&gt; verificacao (opcional) | aprovado | ../verificacoes/escolher_verificacao.php |
| S3R-002 | cadastro jB -&gt; verificacao (opcional) | aprovado | ../verificacoes/escolher_verificacao.php |
| S3R-003 | cadastro eX -&gt; verificacao (opcional) | aprovado | ../verificacoes/escolher_verificacao.php |
| S3R-004 | cadastro eY -&gt; verificacao (opcional) | aprovado | ../verificacoes/escolher_verificacao.php |
| S3R-005 | cadastro grava endereco completo (CEP so digitos) | aprovado | {"cep": "01310100", "logradouro": "Avenida Paulista", "numero": "1578", "complemento": "Andar 5", "bairro": "Bela Vista", "cidade": "São Paulo", "estado": "SP", |
| S3R-006 | CNPJ gravado no formato unico | aprovado | 98.456.739/0598-13 |
| S3R-007 | CEP com 7 digitos e recusado com mensagem | aprovado | HTTP 200 |
| S3R-008 | perfil exibe endereco formatado | aprovado | — |
| S3R-009 | edicao tem CEP primeiro + rua/numero/complemento/bairro + script ViaCEP | aprovado | — |
| S3R-010 | edicao do endereco persiste | aprovado | {"cep": "20040020", "logradouro": "Avenida Rio Branco", "bairro": "Centro", "cidade": "Rio de Janeiro", "estado": "RJ"} |
| S3R-011 | e-mail de outra empresa -&gt; aviso (antes: erro fatal) | aprovado | perfil.php?saved=3 |
| S3R-012 | CNPJ de outra empresa -&gt; aviso (antes: erro fatal) | aprovado | perfil.php?saved=3 |
| S3R-013 | mensagem de e-mail/CNPJ em uso aparece | aprovado | — |
| S3R-014 | modal de exclusao da empresa sem class="hidden" (abre) | aprovado | — |
| S3R-015 | empresa X cria vaga | aprovado | vaga 65 |
| S3R-016 | listar.php?id=X funciona como pagina de detalhes | aprovado | — |
| S3R-017 | detalhe mostra tipo de contrato | aprovado | — |
| S3R-018 | vaga inexistente -&gt; redireciona para a listagem | aprovado | listar.php |
| S3R-019 | detalhe sem login -&gt; login | aprovado | ../jovem/login.php |
| S3R-020 | jovem A se candidata | aprovado | candidatar.php?vaga_id=65&amp;result=ok |
| S3R-021 | candidatura duplicada tratada | aprovado | candidatar.php?vaga_id=65&amp;result=duplicate |
| S3R-022 | candidatura em vaga inexistente: sem erro fatal (antes: Fatal error) | aprovado | 302 ../dashboard/jovem.php |
| S3R-023 | candidatura sem token CSRF -&gt; 403 | aprovado | — |
| S3R-024 | vaga aparece em Minhas Candidaturas com "Ver detalhes" valido | aprovado | — |
| S3R-025 | empresa ve o candidato | aprovado | — |
| S3R-026 | empresa muda status (AJAX) | aprovado | {"ok":true,"status":"Em an\u00e1lise"} |
| S3R-027 | salvar o MESMO status agora responde ok (antes: 403) | aprovado | 200 {"ok":true,"status":"Em an\u00e1lise"} |
| S3R-028 | status invalido -&gt; 400 | aprovado | — |
| S3R-029 | outra empresa NAO altera a candidatura | aprovado | 403 status=Em análise |
| S3R-030 | outra empresa nao ve candidatos da vaga alheia | aprovado | ../empresa/perfil.php |
| S3R-031 | jovem ve o status atualizado | aprovado | — |
| S3R-032 | Flask /ping | aprovado | {"analise_contextual":false,"assistente":false,"gemini":false,"modelo_contextual":"gemini-3.5-flash-lite","status":"ok","versao":"3.00"}  |
| S3R-033 | Flask /avaliar real | aprovado | {"analise": "Compatibilidade razoável. Você atende parte dos requisitos — considere se candidatar e destaque seus pontos |
| S3R-034 | Flask /avaliar com JSON invalido -&gt; 400 | aprovado | — |
| S3R-035 | Match via PHP (jovem) responde score | aprovado | {"score":55,"analise":"Compatibilidade razo\u00e1vel. Voc\u00ea atende parte dos requisitos \u2014 c |
| S3R-036 | score via PHP == score do Flask para os mesmos dados (algoritmo intacto) | aprovado | 55 x 55 |
| S3R-037 | segunda chamada vem do cache | aprovado | — |
| S3R-038 | avaliar_ajax com corpo nao-JSON -&gt; 400 | aprovado | — |
| S3R-039 | Match empresa (vaga propria, perfil publico) | aprovado | {"score":55,"analise":"Compatibilidade razo\u00e1vel. Voc\u00ea atende parte dos |
| S3R-040 | Match empresa em vaga de OUTRA empresa -&gt; 403 | aprovado | — |
| S3R-041 | Match sem token CSRF -&gt; 403 (Stage 2 intacta) | aprovado | — |
| S3R-042 | perfil do jovem agora emite csrf_js() (IA Advisor) | aprovado | — |
| S3R-043 | IA Advisor (acao curriculo) responde sugestoes | aprovado | {"mensagem":"Encontramos 3 ponto(s) para melhorar no seu perfil.","sugestoes":["Seu resumo |
| S3R-044 | Flask desligado -&gt; nota calculada em PHP (Stage 5.1) | aprovado | 200 {"score":55,"analise":"Compatibilidade razo\u00e1vel. Voc\u00ea atende |
| S3R-045 | Flask desligado: resultado em cache continua disponivel | aprovado | — |
| S3R-046 | Flask desligado: IA Advisor -&gt; 503 JSON | aprovado | — |
| S3R-047 | pagina de analise abre com o Flask off (Stage 5.1: nota via PHP; bloco de erro continua no HTML) | aprovado | — |
| S3R-048 | Flask religado -&gt; Match volta a funcionar | aprovado | — |
| S3R-049 | favoritar (AJAX) | aprovado | {"action":"added","favorito":true,"vaga_id":65} |
| S3R-050 | favorito persiste no banco | aprovado | — |
| S3R-051 | pagina de favoritos lista a vaga | aprovado | — |
| S3R-052 | desfavoritar (AJAX) | aprovado | — |
| S3R-053 | favoritos vazio apos remover | aprovado | — |
| S3R-054 | repeticao alterna corretamente | aprovado | [True, False, True, False] |
| S3R-055 | acao de B nao mexe nos favoritos de A | aprovado | — |
| S3R-056 | favoritar com token invalido -&gt; 403 | aprovado | — |
| S3R-057 | favoritar vaga inexistente -&gt; 404 JSON | aprovado | — |
| S3R-058 | favorito persiste apos novo login | aprovado | — |
| S3R-059 | dashboard jovem abre com links para candidaturas e favoritos | aprovado | — |
| S3R-060 | dashboard empresa abre com link para Minhas vagas | aprovado | — |
| S3R-061 | Minhas vagas (empresa) abre e lista a vaga | aprovado | — |
| S3R-062 | Minhas vagas sem o botao "Editar" que abria o formulario de criacao vazio | aprovado | — |
| S3R-063 | logs/ protegido por .htaccess (efeito so verificavel no Apache) | aprovado | — |
| S3R-064 | roteador dashboard/index.php -&gt; jovem | aprovado | — |
| S3R-065 | roteador dashboard/index.php -&gt; empresa | aprovado | — |
| S3R-066 | roteador sem sessao -&gt; landing | aprovado | — |
| S3R-067 | jovem nao entra no dashboard da empresa | aprovado | — |
| S3R-068 | empresa nao entra no dashboard do jovem | aprovado | — |
| S3R-069 | nenhuma resposta contem /dashboard/dashboard/ | aprovado | — |
| S3R-070 | escolha da verificacao oferece "Verificar depois" | aprovado | — |
| S3R-071 | verificacao e opcional: dashboard abre sem verificar | aprovado | — |
| S3R-072 | jovem: codigo gerado e armazenado (6 digitos, expira no futuro) | aprovado | {'c': '530967', 'e': '2026-10-10 17:59:08'} |
| S3R-073 | sem SMTP/PHPMailer a tela avisa a falha de envio (sem erro fatal) | aprovado | — |
| S3R-074 | codigo incorreto recusado | aprovado | — |
| S3R-075 | codigo expirado recusado | aprovado | — |
| S3R-076 | reenvio gera codigo novo com validade renovada | aprovado | — |
| S3R-077 | validacao sem token CSRF -&gt; 403 | aprovado | — |
| S3R-078 | codigo correto -&gt; conclui e vai ao dashboard correto | aprovado | ../dashboard/index.php?verified=1 |
| S3R-079 | pos-verificacao chega em dashboard/jovem.php (sem 404) | aprovado | http://localhost/JovemConnect/dashboard/jovem.php?verificado=email |
| S3R-080 | ja verificado: escolher_verificacao manda ao dashboard | aprovado | — |
| S3R-081 | EMPRESA: verificar_email abre sem erro fatal (antes: Unknown column nome) | aprovado | 200 None |
| S3R-082 | EMPRESA: verificacao de e-mail concluida e persistida | aprovado | ../dashboard/index.php?verified=1 |
| S3R-083 | SMS jovem B: telefone invalido recusado | aprovado | — |
| S3R-084 | SMS jovem B: codigo gerado (envio falha sem Twilio, sem erro fatal) | aprovado | — |
| S3R-085 | SMS jovem B: codigo correto conclui e persiste | aprovado | ../dashboard/index.php?phone_verified=1 |
| S3R-086 | SMS empresa Y: telefone invalido recusado | aprovado | — |
| S3R-087 | SMS empresa Y: codigo gerado (envio falha sem Twilio, sem erro fatal) | aprovado | — |
| S3R-088 | SMS empresa Y: codigo correto conclui e persiste | aprovado | ../dashboard/index.php?phone_verified=1 |
| S3R-089 | "Salvar dados" (update_perfil) persiste tudo | aprovado | {"cidade": "São Paulo", "estado": "SP", "cep": "01310100", "area_interesse": "Administrativo", "s": "completo", "t": "tecnico", "c": "Logística", "formacao": "3 |
| S3R-090 | desmarcar "Possuo curso tecnico" -&gt; volta a "regular" | aprovado | — |
| S3R-091 | registro antigo "tecnico" sem curso continua tecnico ao salvar | aprovado | — |
| S3R-092 | e-mail com mais de 100 caracteres e recusado (nao cortado) | aprovado | — |
| S3R-093 | escolaridade aparece no perfil (antes: em lugar nenhum) | aprovado | — |
| S3R-094 | e-mail de outra conta -&gt; aviso (antes: erro fatal) | aprovado | perfil.php?saved=3 |
| S3R-095 | e-mail invalido -&gt; aviso | aprovado | — |
| S3R-096 | trocar o e-mail desfaz a verificacao do e-mail | aprovado | — |
| S3R-097 | texto longo no perfil: sem erro fatal (truncado) | aprovado | — |
| S3R-098 | formacao com conclusao longa: sem erro fatal (antes: Fatal error) | aprovado | [{"id": 17, "conclusao": "Dezembro de 2025 (pr"}] |
| S3R-099 | jovem A nao apaga formacao de B | aprovado | — |
| S3R-100 | remover formacao propria | aprovado | — |
| S3R-101 | habilidade nao duplica (maiusculas/acentos/espacos) | aprovado | 1 linha(s) |
| S3R-102 | habilidade personalizada aceita + longa truncada em 80 | aprovado | — |
| S3R-103 | habilidades predefinidas disponiveis (datalist) | aprovado | — |
| S3R-104 | habilidades carregam no perfil | aprovado | — |
| S3R-105 | remover habilidade | aprovado | — |
| S3R-106 | experiencia com periodo longo: sem erro fatal | aprovado | — |
| S3R-107 | empresa ve escolaridade do candidato publico | aprovado | — |
| S3R-108 | "Começar" leva a escolha Jovem/Empresa | aprovado | — |
| S3R-109 | comecar.php oferece Jovem, Empresa, Entrar e Voltar | aprovado | — |
| S3R-110 | destinos da escolha abrem | aprovado | — |
| S3R-111 | cadastros tem "Voltar" para a escolha | aprovado | — |
| S3R-112 | logado: comecar.php leva ao painel certo | aprovado | — |
| S3R-113 | login jovem correto -&gt; dashboard | aprovado | ../dashboard/jovem.php |
| S3R-114 | login jovem regenera o ID de sessao | aprovado | — |
| S3R-115 | jovem: Set-Cookie com HttpOnly e SameSite=Lax | aprovado | — |
| S3R-116 | logout jovem | aprovado | — |
| S3R-117 | jovem: dashboard bloqueado apos logout | aprovado | — |
| S3R-118 | jovem: senha incorreta recusada | aprovado | — |
| S3R-119 | jovem: mensagem de erro exibida | aprovado | — |
| S3R-120 | jovem: usuario inexistente recusado | aprovado | — |
| S3R-121 | jovem: hash bcrypt usado como senha NAO autentica | aprovado | — |
| S3R-122 | login empresa correto -&gt; dashboard | aprovado | ../dashboard/empresa.php |
| S3R-123 | login empresa regenera o ID de sessao | aprovado | — |
| S3R-124 | empresa: Set-Cookie com HttpOnly e SameSite=Lax | aprovado | — |
| S3R-125 | logout empresa | aprovado | — |
| S3R-126 | empresa: dashboard bloqueado apos logout | aprovado | — |
| S3R-127 | empresa: senha incorreta recusada | aprovado | — |
| S3R-128 | empresa: mensagem de erro exibida | aprovado | — |
| S3R-129 | empresa: usuario inexistente recusado | aprovado | — |
| S3R-130 | empresa: hash bcrypt usado como senha NAO autentica | aprovado | — |
| S3R-131 | senha legada em texto puro (seed) NAO autentica | aprovado | login.php |
| S3R-132 | sessao invalida/expirada -&gt; login | aprovado | — |
| S3R-133 | processar_login.php da raiz continua neutralizado | aprovado | — |
| S3R-134 | recuperacao: "Voltar para o login" aponta para jovem/login.php | aprovado | — |
| S3R-135 | recuperacao jovens: pular a etapa 2 NAO troca a senha | aprovado | — |
| S3R-136 | recuperacao jovens: tentativa de pular reinicia o fluxo | aprovado | — |
| S3R-137 | recuperacao jovens: fluxo completo troca a senha | aprovado | jovem/login.php |
| S3R-138 | recuperacao jovens: senha antiga deixa de funcionar | aprovado | — |
| S3R-139 | recuperacao: "Voltar para o login" aponta para jovem/login.php | aprovado | — |
| S3R-140 | recuperacao empresas: pular a etapa 2 NAO troca a senha | aprovado | — |
| S3R-141 | recuperacao empresas: tentativa de pular reinicia o fluxo | aprovado | — |
| S3R-142 | recuperacao empresas: fluxo completo troca a senha | aprovado | empresa/login.php |
| S3R-143 | recuperacao empresas: senha antiga deixa de funcionar | aprovado | — |
| S3R-144 | recuperacao sem token CSRF -&gt; 403 | aprovado | — |
| S3R-145 | 12 endpoints recusam POST sem token | aprovado | sem 403: [] |
| S3R-146 | vaga continua existindo (exclusao sem token bloqueada) | aprovado | — |
| S3R-147 | token de OUTRA sessao -&gt; 403 | aprovado | — |
| S3R-148 | 15 URLs com ID invalido/inexistente/ausente ou sem permissao respondem com seguranca | aprovado | [] |
| S3R-149 | Match com vaga inexistente -&gt; 404 JSON | aprovado | — |
| S3R-150 | status de candidatura inexistente -&gt; 403 JSON | aprovado | — |
| S3R-151 | perfil privado: empresa com candidatura ve; sem candidatura recebe "Perfil indisponivel" 404 (Stage 6) | aprovado | X: candidatura=False HTTP 404; Y: candidatura=False HTTP 404 |
| S3R-152 | erro inesperado do banco -&gt; mensagem amigavel 500, sem stack trace/SQL/caminho | aprovado | HTTP 500 vazou=None |
| S3R-153 | ...e o erro fica registrado em logs/app.log (tipo e codigo, sem SQL/mensagem do banco) | aprovado | [{"ts": "2026-10-10T17:44:11+00:00", "nivel": "erro", "componente": "php", "evento": "erro.inesperado", "req": "2073535820d9feaa", "usuario": "jovem:88", "pagin |
| S3R-154 | CPF digitado com outros simbolos e gravado no formato 000.000.000-00 (Stage 6) | aprovado | — |
| S3R-155 | nenhuma das 220 respostas da suite vazou detalhes internos | aprovado | [] |

## S3B — Stage 3 — parte B (upload, links, permissões)

Arquivo de resultado: `tests/s3_rerun/regressao_b_result.json` · 47/47 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S3B-001 | login jovem A (senha redefinida na recuperacao) | aprovado | — |
| S3B-002 | login jovem B | aprovado | — |
| S3B-003 | login empresa X | aprovado | — |
| S3B-004 | perfil mostra estado "nenhum curriculo" e formulario de envio | aprovado | — |
| S3B-005 | sem curriculo nao ha link quebrado para o PDF | aprovado | — |
| S3B-006 | arquivo que nao e PDF recusado com mensagem correta | aprovado | — |
| S3B-007 | mensagem do upload e exibida uma vez (consumida) | aprovado | — |
| S3B-008 | PDF acima de 5 MB recusado | aprovado | — |
| S3B-009 | envio sem token CSRF -&gt; 403 | aprovado | — |
| S3B-010 | PDF valido enviado: feedback de sucesso | aprovado | — |
| S3B-011 | arquivo associado ao proprio usuario (nome fixo, sem nome original) | aprovado | — |
| S3B-012 | perfil mostra estado "enviado" com link autorizado | aprovado | — |
| S3B-013 | substituir o curriculo atualiza o arquivo | aprovado | — |
| S3B-014 | dono baixa o proprio curriculo (application/pdf) | aprovado | — |
| S3B-015 | outro jovem NAO baixa o curriculo de A | aprovado | — |
| S3B-016 | empresa baixa curriculo de perfil publico | aprovado | — |
| S3B-017 | perfil privado: a empresa da vaga em que A se candidatou baixa o curriculo (Stage 6) | aprovado | — |
| S3B-018 | perfil privado: empresa SEM relacao com A NAO baixa o curriculo | aprovado | — |
| S3B-019 | sem login -&gt; pagina de login | aprovado | — |
| S3B-020 | id inexistente/invalido -&gt; 404 | aprovado | — |
| S3B-021 | empresa ve "Ver documento" quando ha curriculo | aprovado | — |
| S3B-022 | empresa ve aviso quando NAO ha curriculo (antes: link 404) | aprovado | — |
| S3B-023 | .htaccess de bloqueio presente na pasta dos curriculos | aprovado | — |
| S3B-024 | cadastro jovem C (descartavel) | aprovado | — |
| S3B-025 | C tem dados em todas as tabelas dependentes + arquivo | aprovado | {"candidaturas": 1, "favoritos": 1, "avaliacoes_ia": 1, "habilidades_jovem": 1, "formacao_jovem": 1, "experiencias_jovem": 1} |
| S3B-026 | perfil tem botao "Excluir Conta" e modal com confirmacao (POST + CSRF) | aprovado | — |
| S3B-027 | GET nao exclui (volta ao perfil) | aprovado | — |
| S3B-028 | POST sem CSRF -&gt; 403, nada excluido | aprovado | — |
| S3B-029 | POST sem a confirmacao do modal -&gt; nao exclui | aprovado | — |
| S3B-030 | exclusao confirmada -&gt; logout e volta a landing | aprovado | ../index.php?conta=excluida |
| S3B-031 | so a PROPRIA conta e excluida (jovem_id=B no POST ignorado) | aprovado | — |
| S3B-032 | CASCADE removeu candidaturas, favoritos, avaliacoes, habilidades, formacao, experiencias | aprovado | {"candidaturas": 0, "favoritos": 0, "avaliacoes_ia": 0, "habilidades_jovem": 0, "formacao_jovem": 0, "experiencias_jovem": 0} |
| S3B-033 | arquivo do curriculo removido | aprovado | — |
| S3B-034 | sessao destruida: dashboard inacessivel | aprovado | — |
| S3B-035 | reusar o cookie antigo (voltar no navegador) nao restaura a sessao | aprovado | — |
| S3B-036 | dados de A continuam intactos | aprovado | — |
| S3B-037 | cadastro empresa Z (descartavel) | aprovado | — |
| S3B-038 | vaga de Z tem candidatura, favorito, avaliacao e requisitos | aprovado | {"candidaturas": 1, "favoritos": 1, "avaliacoes_ia": 1, "requisitos_vaga": 1} |
| S3B-039 | perfil da empresa tem botao + modal de exclusao (POST + CSRF + confirmacao) | aprovado | — |
| S3B-040 | empresa: sem confirmacao nao exclui | aprovado | — |
| S3B-041 | empresa: exclusao confirmada so da propria conta | aprovado | — |
| S3B-042 | empresa: CASCADE removeu vagas e tudo ligado a elas | aprovado | {"candidaturas": 0, "favoritos": 0, "avaliacoes_ia": 0, "requisitos_vaga": 0} |
| S3B-043 | empresa: jovem candidato continua existindo | aprovado | — |
| S3B-044 | empresa: sessao destruida | aprovado | — |
| S3B-045 | links do visitante: 11 paginas visitadas sem 404/500/vazamento | aprovado | quebrados: []; pendentes conhecidos: [] |
| S3B-046 | links do jovem: 202 paginas visitadas sem 404/500/vazamento | aprovado | quebrados: []; pendentes conhecidos: [] |
| S3B-047 | links do empresa: 15 paginas visitadas sem 404/500/vazamento | aprovado | quebrados: []; pendentes conhecidos: [] |

## S3N — Stage 3 — navegador

Arquivo de resultado: `tests/s3_rerun/browser_result.json` · 33/33 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S3N-001 | assistente em index.php (visitante): abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-002 | assistente em dashboard/jovem.php: abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-003 | assistente em dashboard/empresa.php: abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-004 | assistente em candidaturas/minhas.php: abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-005 | assistente em jovem/perfil.php: abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-006 | assistente em empresa/perfil.php: abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-007 | assistente em Vagas/listar.php: abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-008 | assistente em Vagas/listar_detalhe: abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-009 | assistente em Vagas/favoritos.php: abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-010 | assistente em Vagas/candidatos.php: abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-011 | assistente em Vagas/minhas.php: abre, Enviar clicavel, 6 perguntas coerentes, fecha/reabre, sem erros JS | aprovado | — |
| S3N-012 | painel de acessibilidade abre numa pagina que antes tinha o botao sem acao, e o ultimo controle nao fica sob o FAB | aprovado | — |
| S3N-013 | paineis ".hidden" comecam ocultos | aprovado | {"editNome": "none", "editDados": "none", "addFormacaoForm": "none", "addExpForm": "none", "addHabForm": "none", "iaAdvisorResult": "none"} |
| S3N-014 | formPerfil so tem campos do perfil e e valido | aprovado | {"campos": ["action", "csrf_token", "nome", "email", "cidade", "estado", "cep", "area_interesse", "formacao_nivel", "formacao_ano", "em_tecnico", "curso_tecnico |
| S3N-015 | "Editar Dados" alterna para o modo de edicao | aprovado | — |
| S3N-016 | "Salvar Dados Básicos" envia action=update_perfil (unico) com os campos do perfil | aprovado | {"action": ["update_perfil"], "nome": ["Teste Stage3 Jovem A"], "email": ["[e-mail]"], "cidade": ["São Paulo"], "estado": ["SP"], "cep": ["01310100"], "area_int |
| S3N-017 | "Salvar Resumo" (antes bloqueado) agora envia update_perfil | aprovado | — |
| S3N-018 | "Adicionar formacao" envia so add_formacao (independente do perfil) | aprovado | ['action', 'csrf_token', 'form_formacao_nivel', 'form_formacao_ano', 'form_curso_tecnico', 'form_curso_tecnico_outro', 'form_curso_superior', 'form_curso_superi |
| S3N-019 | habilidades: "+" abre e "+" de novo fecha o formulario | aprovado | flex -&gt; none |
| S3N-020 | habilidades: lista predefinida ligada ao campo e envio de add_habilidade | aprovado | 16 opcoes |
| S3N-021 | IA Advisor envia o header X-CSRF-Token e mostra as sugestoes | aprovado | — |
| S3N-022 | modal de exclusao (jovem) abre e cancela | aprovado | — |
| S3N-023 | jovem: "Cancelar" descarta a edicao (Salvar Resumo nao leva a cidade cancelada e nao fica bloqueado) | aprovado | {'cidade': ['São Paulo'], 'email': ['[e-mail]']} |
| S3N-024 | modal de exclusao (empresa) abre com o .hidden definido | aprovado | — |
| S3N-025 | perfil: digitar o CEP preenche rua, bairro, cidade e UF e foca o numero | aprovado | {"cep": "01310-100", "logradouro": "Avenida Paulista", "bairro": "Bela Vista", "cidade": "São Paulo", "estado": "SP", "numero": "1"} |
| S3N-026 | perfil: salvar envia o endereco completo | aprovado | {'cep': ['01310-100'], 'logradouro': ['Avenida Paulista'], 'numero': ['1000'], 'complemento': ['Sala 9'], 'bairro': ['Bela Vista']} |
| S3N-027 | perfil: focar/sair do CEP ja salvo NAO rebusca nem sobrescreve o endereco | aprovado | Avenida Rio Branco |
| S3N-028 | empresa: "Cancelar" descarta a edicao antes de "Salvar Alterações" | aprovado | ['Rio de Janeiro'] |
| S3N-029 | perfil: CEP inexistente avisa para preencher manualmente | aprovado | — |
| S3N-030 | cadastro: busca de CNPJ preenche razao social e, via CEP, o endereco (numero/complemento ficam manuais) | aprovado | {"nomeEmpresa": "EMPRESA SIMULADA LTDA", "cep": "01310-100", "logradouro": "Avenida Paulista", "bairro": "Bela Vista", "cidade": "São Paulo", "estado": "SP", "n |
| S3N-031 | cadastro: sem erros de JavaScript | aprovado | [] |
| S3N-032 | dashboard empresa: Flask fora -&gt; botao mostra "Servidor de IA indisponível." | aprovado | Servidor de IA indisponível. |
| S3N-033 | Comecar -&gt; escolha -&gt; Sou Empresa -&gt; Voltar -&gt; escolha; botao Voltar do navegador funciona | aprovado | http://localhost/JovemConnect/empresa/cadastro.php |

## S4N — Stage 4 — navegação

Arquivo de resultado: `tests/navegacao_s4_result.json` · 68/68 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S4N-001 | Home tem "Começar" (comecar.php) e "Entrar" (jovem/login.php) | aprovado | — |
| S4N-002 | Começar → Sou Jovem / Sou Empresa / Entrar / Voltar à página inicial | aprovado | — |
| S4N-003 | jovem/cadastro.php: "Voltar" leva à escolha Jovem/Empresa (comecar.php) e há link para o login | aprovado | — |
| S4N-004 | empresa/cadastro.php: "Voltar" leva à escolha Jovem/Empresa (comecar.php) e há link para o login | aprovado | — |
| S4N-005 | jovem/login.php: "Voltar para a página inicial", cadastro e "Esqueceu a senha?" | aprovado |  href="../index.php" class="back-link"  Voltar para a página inicial |
| S4N-006 | empresa/login.php: "Voltar para a página inicial", cadastro e "Esqueceu a senha?" | aprovado |  href="../index.php" class="back-link"  Voltar para a página inicial |
| S4N-007 | recuperar_senha.php: "Voltar para o login" | aprovado | — |
| S4N-008 | todos os destinos dessas 7 telas abrem (200) | aprovado | — |
| S4N-009 | Começar → Cadastro do jovem → Voltar = Começar (sem laço) | aprovado | comecar.php |
| S4N-010 | cadastro jovem e empresa (contas da navegação) | aprovado | — |
| S4N-011 | jovem logado: início, começar, logins e cadastros → dashboard/jovem.php | aprovado | — |
| S4N-012 | empresa logado: início, começar, logins e cadastros → dashboard/empresa.php | aprovado | — |
| S4N-013 | painel do jovem: perfil, candidaturas, favoritos, sair e detalhes com voltar=painel | aprovado | [] |
| S4N-014 | painel do jovem → páginas internas abrem (200) | aprovado | [] |
| S4N-015 | detalhes da vaga (voltar=painel) → "←" vai para ../dashboard/jovem.php | aprovado |  href="../dashboard/jovem.php" class="icon-btn"
            title="Voltar ao painel" aria- |
| S4N-016 | detalhes da vaga (voltar=favoritos) → "←" vai para favoritos.php | aprovado |  href="favoritos.php" class="icon-btn"
            title="Voltar aos favoritos" aria-label |
| S4N-017 | detalhes da vaga (voltar=candidaturas) → "←" vai para ../candidaturas/minhas.php | aprovado |  href="../candidaturas/minhas.php" class="icon-btn"
            title="Voltar às minhas ca |
| S4N-018 | detalhes da vaga (voltar=—) → "←" vai para listar.php | aprovado |  href="listar.php" class="icon-btn"
            title="Voltar para a lista de vagas" aria- |
| S4N-019 | detalhes da vaga (voltar=https://evil.example/) → "←" vai para listar.php | aprovado |  href="listar.php" class="icon-btn"
            title="Voltar para a lista de vagas" aria- |
| S4N-020 | vaga já candidatada: "✓ Já candidatado · ver status" leva às candidaturas | aprovado | — |
| S4N-021 | detalhes → "Candidatar-se" leva a origem adiante | aprovado | — |
| S4N-022 | favoritos: título abre os detalhes (voltar=favoritos) e logo/atalhos levam ao painel e candidaturas | aprovado | — |
| S4N-023 | minhas candidaturas: "Ver Detalhes" (voltar=candidaturas), "← Voltar ao painel" e título próprio | aprovado | — |
| S4N-024 | barra do topo (header.php): busca envia para a lista de vagas | aprovado | — |
| S4N-025 | busca do topo encontra a vaga | aprovado | — |
| S4N-026 | confirmação de candidatura: "←" e "Cancelar" voltam aos detalhes com a mesma origem | aprovado | — |
| S4N-027 | origem inválida é ignorada (voltar=xyz) | aprovado | — |
| S4N-028 | candidatura enviada → tela de sucesso mantém a origem | aprovado | candidatar.php?vaga_id=44&amp;result=ok&amp;voltar=favoritos |
| S4N-029 | tela de sucesso: "Minhas Candidaturas" e "Ver outras vagas" | aprovado | — |
| S4N-030 | 2º envio → "já registrada", com a mesma origem | aprovado | candidatar.php?vaga_id=44&amp;result=duplicate&amp;voltar=favoritos |
| S4N-031 | perfil do jovem: "←" volta ao painel (com nome acessível) | aprovado | — |
| S4N-032 | currículo IA: "←" volta ao painel | aprovado | — |
| S4N-033 | análise do Match (jovem): logo leva ao painel do jovem | aprovado | — |
| S4N-034 | painel da empresa: perfil, minhas vagas, criar vaga e candidatos com voltar=painel | aprovado | [] |
| S4N-035 | painel da empresa → páginas internas abrem (200) | aprovado | [] |
| S4N-036 | candidatos (voltar=painel) → "←" vai para ../dashboard/empresa.php | aprovado | — |
| S4N-037 | candidatos (voltar=minhas) → "←" vai para minhas.php | aprovado | — |
| S4N-038 | candidatos (voltar=perfil) → "←" vai para ../empresa/perfil.php | aprovado | — |
| S4N-039 | candidatos (voltar=—) → "←" vai para minhas.php | aprovado | — |
| S4N-040 | candidatos (voltar=//evil.example) → "←" vai para minhas.php | aprovado | — |
| S4N-041 | candidatos → "Ver Perfil" leva a origem (voltar=candidatos&amp;vaga=) | aprovado | — |
| S4N-042 | perfil do candidato → "←" volta aos candidatos da vaga | aprovado | — |
| S4N-043 | sem origem (ou vaga inválida) → "←" volta ao painel da empresa | aprovado | — |
| S4N-044 | "Salvar nos Favoritos" (que levava ao login do jovem) agora é botão desativado "em breve" | aprovado | — |
| S4N-045 | criar vaga: caminho de volta no topo e no fim (painel) | aprovado | — |
| S4N-046 | vaga publicada → "Minhas vagas" com aviso (antes: perfil + "Perfil atualizado") | aprovado | minhas.php?publicada=1 |
| S4N-047 | aviso "Vaga publicada!" aparece | aprovado | — |
| S4N-048 | minhas vagas: "Ver Candidatos" (voltar=minhas) e "← Voltar ao Painel" | aprovado | — |
| S4N-049 | perfil da empresa: "←" volta ao painel e "Gerenciar" leva voltar=perfil | aprovado | — |
| S4N-050 | roteador do painel repassa o aviso de verificado (jovem.php) | aprovado | jovem.php?verificado=email \| jovem.php?verificado=telefone \| jovem.php |
| S4N-051 | roteador do painel repassa o aviso de verificado (empresa.php) | aprovado | empresa.php?verificado=email \| empresa.php?verificado=telefone \| empresa.php |
| S4N-052 | verificar telefone: saídas "outra forma" e "verificar depois" | aprovado | — |
| S4N-053 | POST sem token → 403 com página em português e sem termos técnicos | aprovado | — |
| S4N-054 | ...com "Abrir a página de novo" (mesmo site) e "Ir para a página inicial" (../index.php) | aprovado | — |
| S4N-055 | Referer de outro site NÃO vira link | aprovado | — |
| S4N-056 | página da raiz → link "index.php" (caminho relativo certo) | aprovado | — |
| S4N-057 | AJAX sem token → 403 JSON com mensagem compreensível | aprovado | Sua sessão expirou ou a página está desatualizada. Recarregue a página e tente novamente. |
| S4N-058 | erro inesperado (500): além do "Voltar", link explícito para a página inicial | aprovado | 500 |
| S4N-059 | etapa 1: rótulo ligado ao campo de e-mail | aprovado | — |
| S4N-060 | etapa 2: "Não recebi meu e-mail" e "Voltar para o login" | aprovado | — |
| S4N-061 | etapa 3: agora também tem "Voltar para o login" | aprovado | — |
| S4N-062 | senhas diferentes → aviso anunciado (role="alert") | aprovado | — |
| S4N-063 | senha redefinida → login com aviso de sucesso (role="status") | aprovado | — |
| S4N-064 | login com a nova senha funciona | aprovado | ../dashboard/jovem.php |
| S4N-065 | jovem: sair → página inicial | aprovado | index.php |
| S4N-066 | jovem: depois de sair, 7 páginas internas mandam para o login | aprovado | — |
| S4N-067 | empresa: sair → página inicial | aprovado | index.php |
| S4N-068 | empresa: depois de sair, 6 páginas internas mandam para o login | aprovado | — |

## S4I — Stage 4 — interface

Arquivo de resultado: `tests/interface_s4_result.json` · 44/44 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S4I-001 | ✦ abre (foco no campo, aria-expanded) em 26/26 páginas | aprovado | — |
| S4I-002 | duas perguntas seguidas respondidas em 26/26 páginas | aprovado | — |
| S4I-003 | ✕ fecha e devolve o foco ao ✦ em 26/26 | aprovado | — |
| S4I-004 | Esc fecha o assistente em 26/26 | aprovado | — |
| S4I-005 | ♿ abre em 26/26 | aprovado | — |
| S4I-006 | preferência (tamanho do texto + tons de cinza) aplicada e salva em 26/26 | aprovado | — |
| S4I-007 | preferência da página anterior continua valendo em 23/23 navegações | aprovado | — |
| S4I-008 | nenhum erro de JavaScript nas páginas | aprovado | — |
| S4I-009 | chaves antigas migradas para a chave única (e removidas) e aplicadas | aprovado | {"jc-color-filter": "tritanopia", "jc-font-size": "130", "jc-grayscale": "1", "jc-contrast": "1"} |
| S4I-010 | "Restaurar padrões" limpa tudo | aprovado | {} |
| S4I-011 | ♿ aberto pelo teclado: o foco vai para a 1ª opção do painel (Tamanho do texto) | aprovado | a11yFontSize |
| S4I-012 | página inicial: aviso "Sua conta foi excluída." e endereço limpo | aprovado | — |
| S4I-013 | login: aba "Sou Empresa" troca o rodapé ("ainda não é parceira?" → cadastro da empresa) | aprovado | — |
| S4I-014 | senha errada: aviso com role="alert" e cursor já no e-mail | aprovado | — |
| S4I-015 | login da empresa: texto do rodapé corrigido | aprovado | — |
| S4I-016 | cadastro com senhas diferentes: erro ao lado do campo (sem alert), etapa não avança (Stage 6) | aprovado | ['Informe seu nome completo.', 'Preencha este campo.', 'Informe sua data de nascimento.', 'Informe seu e-mail.', 'As senhas não coincidem.'] |
| S4I-017 | CEP do cadastro do jovem: "CEP não encontrado..." | aprovado | CEP não encontrado — preencha a cidade manualmente. |
| S4I-018 | CEP do cadastro do jovem: "Cidade preenchida pelo CEP...." | aprovado |  Cidade preenchida pelo CEP. |
| S4I-019 | CEP do cadastro do jovem: "Não foi possível buscar o CEP ..." | aprovado | Não foi possível buscar o CEP agora — preencha a cidade manualmente. |
| S4I-020 | busca de CNPJ (404): "CNPJ não encontrado. Confira..." ao lado do campo, sem alert (sem "Failed to fetch") (Stage 6) | aprovado | CNPJ não encontrado. Confira o número ou preencha os dados manualmente. |
| S4I-021 | busca de CNPJ (abort): "Não foi possível consultar o..." ao lado do campo, sem alert (sem "Failed to fetch") (Stage 6) | aprovado | Não foi possível consultar o CNPJ agora. Preencha os dados manualmente. |
| S4I-022 | envio único: botão trava com "Registrando empresa..." e destrava ao voltar à página | aprovado | — |
| S4I-023 | recuperar senha: segue o tema claro e o ícone fica dentro do campo | aprovado | {"fundo": "rgb(245, 246, 250)", "card": "rgb(255, 255, 255)", "dentro": true} |
| S4I-024 | recuperar senha: "Alto contraste" agora tem efeito | aprovado | — |
| S4I-025 | painel: aviso "E-mail verificado com sucesso!" e endereço limpo (F5 não repete) | aprovado | — |
| S4I-026 | modal "Excluir Conta": foco em Cancelar, Esc fecha e o foco volta ao botão | aprovado | — |
| S4I-027 | "+" (Adicionar formação): anuncia aberto/fechado e leva o foco ao 1º campo | aprovado | — |
| S4I-028 | duplo clique em "Confirmar candidatura": 1 candidatura e tela de sucesso | aprovado | http://localhost/JovemConnect/Vagas/candidatar.php?vaga_id=2&amp;result=ok&amp;voltar=painel n=1 |
| S4I-029 | favoritar com erro: aviso visível (servidor / rede) e botão liberado | aprovado | Erro interno ao atualizar favoritos. \| Não foi possível atualizar os favoritos. Tente de novo. |
| S4I-030 | favoritar com resposta inesperada (200 sem JSON): aviso de erro em vez de estado errado | aprovado | — |
| S4I-031 | favoritar (ok): o nome acessível acompanha o estado | aprovado | Salvar nos favoritos → Remover dos favoritos |
| S4I-032 | Match: falha de rede → "Não foi possível avaliar agora. Tente de novo." | aprovado | Não foi possível avaliar agora. Tente de novo. |
| S4I-033 | Match: IA desligada → nota calculada mesmo assim (Stage 5.1), sem código HTTP | aprovado | Match: 55% |
| S4I-034 | painel: card da vaga abre pelo teclado (Enter no título) com voltar=painel | aprovado | http://localhost/JovemConnect/Vagas/listar.php?id=46&amp;voltar=painel |
| S4I-035 | busca do topo: Enter leva à lista de vagas filtrada | aprovado | http://localhost/JovemConnect/Vagas/listar.php?busca=Auxiliar |
| S4I-036 | status do candidato com falha: aviso visível | aprovado | — |
| S4I-037 | modal "Remover vaga": Tab e Shift+Tab ficam dentro do modal (não vão para ✦/♿ atrás dele) | aprovado | [True, True, True, True, True, True] |
| S4I-038 | modal "Remover vaga": foco em Cancelar, Esc fecha e o foco volta ao "Remover" | aprovado | — |
| S4I-039 | criar vaga (Stage 5.1), busca de CEP (erro): aviso em português no campo, sem alert | aprovado | CEP não encontrado — preencha o endereço manualmente. |
| S4I-040 | criar vaga (Stage 5.1), busca de CEP (abort): aviso em português no campo, sem alert | aprovado | Não foi possível buscar o CEP agora — preencha manualmente. |
| S4I-041 | aviso "Vaga publicada!" com role="status" e texto reposto depois do role | aprovado | — |
| S4I-042 | duplo clique em "Publicar": 1 vaga e volta para "Minhas vagas" com aviso | aprovado | n=1 http://localhost/JovemConnect/Vagas/minhas.php?publicada=1 |
| S4I-043 | modal com texto 140% em tela baixa: começa no topo, rola e o botão fica alcançável | aprovado | {"topoCartao": 24, "rola": true, "botaoNaTela": true} |
| S4I-044 | painel da empresa: avaliar sem escolher a vaga explica o que fazer | aprovado | — |

## S5M — Stage 5 — modais (Termos, Privacidade, Contato)

Arquivo de resultado: `tests/modais_s5_result.json` · 65/65 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S5M-001 | 1366px Termos de Uso: abre (role=dialog, aria-modal, título acessível, foco no ×, página atrás travada) | aprovado | {"aberto": true, "role": "dialog", "modal": "true", "titulo": "Termos de Uso", "focoDentro": true, "focoClasse": "jc-modal-fechar", "focoTexto": "Fechar Termos  |
| S5M-002 | 1366px Termos de Uso: Stage 6 — minuta com versão/data ou e-mail oficial (nenhum outro e-mail) | aprovado | Termos de Uso  Minuta — sujeita a revisão jurídica Versão 1.0-minuta · atualizada em 10/10/2026  Resumo O JovemConnect é |
| S5M-003 | 1366px Termos de Uso: Tab/Shift+Tab não saem do modal | aprovado | [True, True, True, True, True, True, True, True, True, True, True, True] |
| S5M-004 | 1366px Termos de Uso: caixa cabe na largura da tela | aprovado | {"l": 363, "r": 1003, "w": 1366, "sw": 1366} |
| S5M-005 | 1366px Termos de Uso: Esc fecha, destrava a página e devolve o foco ao botão do rodapé | aprovado | {'aberto': False, 'role': 'dialog', 'modal': 'true', 'titulo': 'Termos de Uso', 'focoDentro': False, 'focoClasse': 'footer-link', 'focoTexto': 'Termos de Uso',  |
| S5M-006 | 1366px Termos de Uso: × fecha e devolve o foco | aprovado | modalTermos |
| S5M-007 | 1366px Termos de Uso: clique dentro não fecha; clique no fundo fecha | aprovado | — |
| S5M-008 | 1366px Termos de Uso: botão "Fechar" fecha | aprovado | — |
| S5M-009 | 1366px Política de Privacidade: abre (role=dialog, aria-modal, título acessível, foco no ×, página atrás travada) | aprovado | {"aberto": true, "role": "dialog", "modal": "true", "titulo": "Política de Privacidade", "focoDentro": true, "focoClasse": "jc-modal-fechar", "focoTexto": "Fech |
| S5M-010 | 1366px Política de Privacidade: Stage 6 — minuta com versão/data ou e-mail oficial (nenhum outro e-mail) | aprovado | Política de Privacidade  Minuta — sujeita a revisão jurídica Versão 1.0-minuta · atualizada em 10/10/2026  Resumo Coleta |
| S5M-011 | 1366px Política de Privacidade: Tab/Shift+Tab não saem do modal | aprovado | [True, True, True, True, True, True, True, True, True, True, True, True] |
| S5M-012 | 1366px Política de Privacidade: caixa cabe na largura da tela | aprovado | {"l": 363, "r": 1003, "w": 1366, "sw": 1366} |
| S5M-013 | 1366px Política de Privacidade: Esc fecha, destrava a página e devolve o foco ao botão do rodapé | aprovado | {'aberto': False, 'role': 'dialog', 'modal': 'true', 'titulo': 'Política de Privacidade', 'focoDentro': False, 'focoClasse': 'footer-link', 'focoTexto': 'Privac |
| S5M-014 | 1366px Política de Privacidade: × fecha e devolve o foco | aprovado | modalPrivacidade |
| S5M-015 | 1366px Política de Privacidade: clique dentro não fecha; clique no fundo fecha | aprovado | — |
| S5M-016 | 1366px Política de Privacidade: botão "Fechar" fecha | aprovado | — |
| S5M-017 | 1366px Contato: abre (role=dialog, aria-modal, título acessível, foco no ×, página atrás travada) | aprovado | {"aberto": true, "role": "dialog", "modal": "true", "titulo": "Contato", "focoDentro": true, "focoClasse": "jc-modal-fechar", "focoTexto": "Fechar Contato", "tr |
| S5M-018 | 1366px Contato: Stage 6 — minuta com versão/data ou e-mail oficial (nenhum outro e-mail) | aprovado | Contato  Fale com a equipe do JovemConnect pelo e-mail:  [e-mail]  Use o mesmo endereço para dúvidas sobr |
| S5M-019 | 1366px Contato: Tab/Shift+Tab não saem do modal | aprovado | [True, True, True, True, True, True, True, True, True, True, True, True] |
| S5M-020 | 1366px Contato: caixa cabe na largura da tela | aprovado | {"l": 423, "r": 943, "w": 1366, "sw": 1366} |
| S5M-021 | 1366px Contato: Esc fecha, destrava a página e devolve o foco ao botão do rodapé | aprovado | {'aberto': False, 'role': 'dialog', 'modal': 'true', 'titulo': 'Contato', 'focoDentro': False, 'focoClasse': 'footer-link', 'focoTexto': 'Contato', 'trava': Fal |
| S5M-022 | 1366px Contato: × fecha e devolve o foco | aprovado | modalContato |
| S5M-023 | 1366px Contato: clique dentro não fecha; clique no fundo fecha | aprovado | — |
| S5M-024 | 1366px Contato: botão "Fechar" fecha | aprovado | — |
| S5M-025 | 1366px Contato: "Abrir o assistente" fecha o modal e abre o chat | aprovado | {"modal": "none", "chat": true, "foco": "chatInput"} |
| S5M-026 | 1366px página inicial: sem erros de JavaScript | aprovado | [] |
| S5M-027 | 375px Termos de Uso: abre (role=dialog, aria-modal, título acessível, foco no ×, página atrás travada) | aprovado | {"aberto": true, "role": "dialog", "modal": "true", "titulo": "Termos de Uso", "focoDentro": true, "focoClasse": "jc-modal-fechar", "focoTexto": "Fechar Termos  |
| S5M-028 | 375px Termos de Uso: Stage 6 — minuta com versão/data ou e-mail oficial (nenhum outro e-mail) | aprovado | Termos de Uso  Minuta — sujeita a revisão jurídica Versão 1.0-minuta · atualizada em 10/10/2026  Resumo O JovemConnect é |
| S5M-029 | 375px Termos de Uso: Tab/Shift+Tab não saem do modal | aprovado | [True, True, True, True, True, True, True, True, True, True, True, True] |
| S5M-030 | 375px Termos de Uso: caixa cabe na largura da tela | aprovado | {"l": 16, "r": 359, "w": 375, "sw": 375} |
| S5M-031 | 375px Termos de Uso: Esc fecha, destrava a página e devolve o foco ao botão do rodapé | aprovado | {'aberto': False, 'role': 'dialog', 'modal': 'true', 'titulo': 'Termos de Uso', 'focoDentro': False, 'focoClasse': 'footer-link', 'focoTexto': 'Termos de Uso',  |
| S5M-032 | 375px Termos de Uso: × fecha e devolve o foco | aprovado | modalTermos |
| S5M-033 | 375px Termos de Uso: clique dentro não fecha; clique no fundo fecha | aprovado | — |
| S5M-034 | 375px Termos de Uso: botão "Fechar" fecha | aprovado | — |
| S5M-035 | 375px Política de Privacidade: abre (role=dialog, aria-modal, título acessível, foco no ×, página atrás travada) | aprovado | {"aberto": true, "role": "dialog", "modal": "true", "titulo": "Política de Privacidade", "focoDentro": true, "focoClasse": "jc-modal-fechar", "focoTexto": "Fech |
| S5M-036 | 375px Política de Privacidade: Stage 6 — minuta com versão/data ou e-mail oficial (nenhum outro e-mail) | aprovado | Política de Privacidade  Minuta — sujeita a revisão jurídica Versão 1.0-minuta · atualizada em 10/10/2026  Resumo Coleta |
| S5M-037 | 375px Política de Privacidade: Tab/Shift+Tab não saem do modal | aprovado | [True, True, True, True, True, True, True, True, True, True, True, True] |
| S5M-038 | 375px Política de Privacidade: caixa cabe na largura da tela | aprovado | {"l": 16, "r": 359, "w": 375, "sw": 375} |
| S5M-039 | 375px Política de Privacidade: Esc fecha, destrava a página e devolve o foco ao botão do rodapé | aprovado | {'aberto': False, 'role': 'dialog', 'modal': 'true', 'titulo': 'Política de Privacidade', 'focoDentro': False, 'focoClasse': 'footer-link', 'focoTexto': 'Privac |
| S5M-040 | 375px Política de Privacidade: × fecha e devolve o foco | aprovado | modalPrivacidade |
| S5M-041 | 375px Política de Privacidade: clique dentro não fecha; clique no fundo fecha | aprovado | — |
| S5M-042 | 375px Política de Privacidade: botão "Fechar" fecha | aprovado | — |
| S5M-043 | 375px Contato: abre (role=dialog, aria-modal, título acessível, foco no ×, página atrás travada) | aprovado | {"aberto": true, "role": "dialog", "modal": "true", "titulo": "Contato", "focoDentro": true, "focoClasse": "jc-modal-fechar", "focoTexto": "Fechar Contato", "tr |
| S5M-044 | 375px Contato: Stage 6 — minuta com versão/data ou e-mail oficial (nenhum outro e-mail) | aprovado | Contato  Fale com a equipe do JovemConnect pelo e-mail:  [e-mail]  Use o mesmo endereço para dúvidas sobr |
| S5M-045 | 375px Contato: Tab/Shift+Tab não saem do modal | aprovado | [True, True, True, True, True, True, True, True, True, True, True, True] |
| S5M-046 | 375px Contato: caixa cabe na largura da tela | aprovado | {"l": 16, "r": 359, "w": 375, "sw": 375} |
| S5M-047 | 375px Contato: Esc fecha, destrava a página e devolve o foco ao botão do rodapé | aprovado | {'aberto': False, 'role': 'dialog', 'modal': 'true', 'titulo': 'Contato', 'focoDentro': False, 'focoClasse': 'footer-link', 'focoTexto': 'Contato', 'trava': Fal |
| S5M-048 | 375px Contato: × fecha e devolve o foco | aprovado | modalContato |
| S5M-049 | 375px Contato: clique dentro não fecha; clique no fundo fecha | aprovado | — |
| S5M-050 | 375px Contato: botão "Fechar" fecha | aprovado | — |
| S5M-051 | 375px Contato: "Abrir o assistente" fecha o modal e abre o chat | aprovado | {"modal": "none", "chat": true, "foco": "chatInput"} |
| S5M-052 | 375px página inicial: sem erros de JavaScript | aprovado | [] |
| S5M-053 | texto 140% em 667x375: o modal rola, o × continua visível no topo e o último botão fica alcançável | aprovado | {"sh": 413, "ch": 268, "st": 145.71429443359375, "xTopo": 55.984375, "xBase": 106.375, "fBase": 320.5, "vh": 375} |
| S5M-054 | Remover vaga: abre com foco em "Cancelar" (comportamento da Stage 4) | aprovado | {"aberto": true, "role": "dialog", "modal": "true", "titulo": "Remover Vaga", "focoDentro": true, "focoClasse": "btn btn-secondary btn-sm", "focoTexto": "Cancel |
| S5M-055 | Remover vaga: tem botão × com nome acessível | aprovado | — |
| S5M-056 | Remover vaga: × fecha e devolve o foco ao "Remover" | aprovado | Remover |
| S5M-057 | Remover vaga: cabe em 375 px | aprovado | [16, 359, 375] |
| S5M-058 | Remover vaga: Esc continua fechando | aprovado | — |
| S5M-059 | Excluir conta (jovem): abre com foco em "Cancelar"; × fecha e devolve o foco ao "Excluir Conta" | aprovado | {'aberto': True, 'role': 'dialog', 'modal': 'true', 'titulo': 'Excluir Conta', 'focoDentro': True, 'focoClasse': 'btn btn-secondary', 'focoTexto': 'Cancelar', ' |
| S5M-060 | Excluir conta (empresa): abre com foco em "Cancelar"; × fecha e devolve o foco ao "Excluir Conta" | aprovado | {'aberto': True, 'role': 'dialog', 'modal': 'true', 'titulo': 'Excluir Conta', 'focoDentro': True, 'focoClasse': 'btn btn-secondary', 'focoTexto': 'Cancelar', ' |
| S5M-061 | painel de acessibilidade: × fecha e devolve o foco ao botão (aria-expanded=false) | aprovado | {"aberto": false, "exp": "false", "foco": "btnA11y", "rot": "Fechar painel de acessibilidade"} |
| S5M-062 | chat aberto → clique em "Termos": o modal abre (o chat fecha, como na Stage 4) e o Esc fecha o modal e devolve o foco | aprovado | {"modal": "none", "chat": false, "foco": "modalTermos"} |
| S5M-063 | modal por cima do chat aberto: o 1º Esc fecha só o modal (foco volta ao chat); o 2º Esc fecha o chat | aprovado | {"modal": "none", "chat": true, "foco": "chatInput", "chat2": false} |
| S5M-064 | Contato com e-mail oficial configurado: mostra o endereço e o botão "Enviar e-mail" (mailto:) | aprovado | — |
| S5M-065 | Contato com valor inválido na constante: nenhum link de e-mail e nada injetado | aprovado | — |

## S5F — Stage 5 — formação

Arquivo de resultado: `tests/formacao_s5_result.json` · 46/46 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S5F-001 | cadastro: Ensino Médio 2º ano, cursando | aprovado | {"status": 302, "row": {"id": 13, "formacao": "Ensino Médio — 2º ano", "s": "cursando", "t": "regular", "c": null}} |
| S5F-002 | cadastro: Ensino Médio 3º ano concluído + curso técnico (Logística) | aprovado | {"status": 302, "row": {"id": 14, "formacao": "Ensino Médio — 3º ano", "s": "completo", "t": "tecnico", "c": "Logística"}} |
| S5F-003 | cadastro: Técnico — área predefinida (Administração) | aprovado | {"status": 302, "row": {"id": 15, "formacao": "Ensino Técnico — Administração", "s": "cursando", "t": "tecnico", "c": "Administração"}} |
| S5F-004 | cadastro: Técnico — "Outro" com nome | aprovado | {"status": 302, "row": {"id": 16, "formacao": "Ensino Técnico — Eletrotécnica", "s": "cursando", "t": "tecnico", "c": "Eletrotécnica"}} |
| S5F-005 | cadastro: Superior — curso predefinido (Sistemas de Informação), concluído | aprovado | {"status": 302, "row": {"id": 17, "formacao": "Ensino Superior — Sistemas de Informação", "s": "completo", "t": "regular", "c": null}} |
| S5F-006 | cadastro: Superior — Engenharia (predefinida) | aprovado | {"status": 302, "row": {"id": 18, "formacao": "Ensino Superior — Engenharia de Produção", "s": "cursando", "t": "regular", "c": null}} |
| S5F-007 | cadastro: Superior — "Outro" com nome | aprovado | {"status": 302, "row": {"id": 19, "formacao": "Ensino Superior — Biomedicina", "s": "cursando", "t": "regular", "c": null}} |
| S5F-008 | cadastro: Profissionalizante — predefinido (Informática) | aprovado | {"status": 302, "row": {"id": 20, "formacao": "Curso profissionalizante — Informática", "s": "cursando", "t": "regular", "c": null}} |
| S5F-009 | cadastro: Outro — texto livre | aprovado | {"status": 302, "row": {"id": 21, "formacao": "Curso livre de inglês", "s": "cursando", "t": "regular", "c": null}} |
| S5F-010 | cadastro recusa: sem nível (mensagem clara, conta não criada) | aprovado | Selecione o nível de formação. |
| S5F-011 | cadastro recusa: Ensino Médio sem ano (mensagem clara, conta não criada) | aprovado | Selecione o ano do Ensino Médio. |
| S5F-012 | cadastro recusa: Técnico sem área (mensagem clara, conta não criada) | aprovado | Selecione a área do curso técnico. |
| S5F-013 | cadastro recusa: Superior "Outro" vazio (mensagem clara, conta não criada) | aprovado | Informe o curso superior (campo &amp;quot;Qual?&amp;quot;). |
| S5F-014 | cadastro recusa: "Outro" sem texto (mensagem clara, conta não criada) | aprovado | Descreva a sua formação em &amp;quot;Outro&amp;quot;. |
| S5F-015 | cadastro recusa: nível inexistente (mensagem clara, conta não criada) | aprovado | Nível de formação inválido. |
| S5F-016 | cadastro recusa: curso fora da lista (mensagem clara, conta não criada) | aprovado | Selecione o curso superior. |
| S5F-017 | cadastro recusa: EM + técnico sem área (mensagem clara, conta não criada) | aprovado | Selecione a área do curso técnico. |
| S5F-018 | erro no cadastro mantém o que foi escolhido (nível, curso, situação) | aprovado | — |
| S5F-019 | texto de "Outro" é gravado como texto e exibido escapado | aprovado | &lt;script&gt;alert(1)&lt;/script&gt; Curso |
| S5F-020 | POST antigo do cadastro (texto livre + "Possuo curso técnico") segue funcionando | aprovado | {"id": 23, "formacao": "3º ano - conclusão 12/2025", "s": "cursando", "t": "tecnico", "c": "Administração"} |
| S5F-021 | perfil: dados novos voltam selecionados (EM, 3º ano, técnico Logística, concluído) | aprovado | medio 3 Logística |
| S5F-022 | perfil: resumo da escolaridade no formato novo | aprovado | — |
| S5F-023 | perfil: registro ANTIGO ("3º ano - conclusão 12/2025" + técnico) é reconhecido (EM, 3º ano, técnico Administração) e o texto antigo é mostrado | aprovado | — |
| S5F-024 | perfil: resumo do registro antigo igual ao da Stage 3 | aprovado | — |
| S5F-025 | perfil: registro antigo 'Ensino Médio Completo'/regular → nível "medio" | aprovado | medio |
| S5F-026 | perfil: registro antigo 'Curso de oratória'/regular → nível "outro" | aprovado | outro |
| S5F-027 | perfil: registro antigo '(vazio)'/tecnico → nível "tecnico" | aprovado | tecnico |
| S5F-028 | perfil: registro antigo '(vazio)'/regular → nível "não informado" | aprovado | — |
| S5F-029 | dados antigos não foram alterados só por abrir o perfil | aprovado | — |
| S5F-030 | perfil: trocar para Ensino Superior (Direito, concluído) grava o formato novo | aprovado | {"formacao": "Ensino Superior — Direito", "s": "completo", "t": "regular", "c": null} |
| S5F-031 | perfil: Ensino Médio sem ano → não salva e avisa o motivo | aprovado | perfil.php?saved=4 |
| S5F-032 | perfil: aviso de erro mostra "Selecione o ano do Ensino Médio." | aprovado | — |
| S5F-033 | perfil: técnico em TI → tipo "tecnico" e curso "TI" (mesmos valores que o Match já lia) | aprovado | {"formacao": "Ensino Técnico — TI", "t": "tecnico", "c": "TI"} |
| S5F-034 | perfil: "Não informado" é aceito (não trava quem só quer salvar outros dados) | aprovado | {"formacao": "", "t": "regular", "c": null} |
| S5F-035 | perfil: POST antigo (Stage 3) segue funcionando | aprovado | {"formacao": "3º ano Ensino Médio", "s": "completo", "t": "tecnico", "c": "Logística"} |
| S5F-036 | salvar o resumo sem mexer na formação ('Ensino Médio'/regular): salva e mantém o registro antigo intacto | aprovado | {"loc": "perfil.php?saved=1", "formacao": "Ensino Médio", "t": "regular", "c": null, "s": "cursando", "r": "Resumo salvo sem mexer na formação."} |
| S5F-037 | salvar o resumo sem mexer na formação ('3º ano - conclusão 12/2025'/tecnico): salva e mantém o registro antigo intacto | aprovado | {"loc": "perfil.php?saved=1", "formacao": "3º ano - conclusão 12/2025", "t": "tecnico", "c": "Administração", "s": "cursando", "r": "Resumo salvo sem mexer na f |
| S5F-038 | salvar o resumo sem mexer na formação ('vazio'/tecnico): salva e mantém o registro antigo intacto | aprovado | {"loc": "perfil.php?saved=1", "formacao": "", "t": "tecnico", "c": null, "s": "completo", "r": "Resumo salvo sem mexer na formação."} |
| S5F-039 | salvar o resumo sem mexer na formação ('Curso de oratória  avançado'/regular): salva e mantém o registro antigo intacto | aprovado | {"loc": "perfil.php?saved=1", "formacao": "Curso de oratória  avançado", "t": "regular", "c": null, "s": "cursando", "r": "Resumo salvo sem mexer na formação."} |
| S5F-040 | registro antigo + troca do ano (3º → 2º) → grava no formato novo, mantendo o curso técnico | aprovado | {"formacao": "Ensino Médio — 2º ano", "t": "tecnico", "c": "Administração"} |
| S5F-041 | formação adicional: técnico Logística concluído em 2024 | aprovado | [{"titulo": "Ensino Técnico — Logística", "instituicao": "ETEC Teste", "conclusao": "Concluído (2024)", "area": "Logística"}] |
| S5F-042 | formação adicional: Ensino Médio 1º ano cursando (sem ano de conclusão) | aprovado | [{"titulo": "Ensino Médio — 1º ano", "conclusao": "Cursando", "area": ""}] |
| S5F-043 | formação adicional: ano inválido ou nível vazio → não grava e avisa | aprovado | perfil.php?saved=4#formacao perfil.php?saved=4#formacao |
| S5F-044 | formação adicional: POST antigo (texto livre) segue como na Stage 3 (conclusão cortada em 20) | aprovado | [{"titulo": "Técnico em Administração", "conclusao": "Em andamento até dez"}] |
| S5F-045 | lista de formações: formato novo ("ETEC Teste • Concluído (2024)") e antigo ("Conclusão: …") lado a lado | aprovado | — |
| S5F-046 | Match: técnico Administração pelo formulário novo = mesmo score do registro antigo equivalente | aprovado | novo=200/55 antigo=200/55 |

## S5U — Stage 5 — formação (interface)

Arquivo de resultado: `tests/formacao_ui_s5_result.json` · 21/21 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S5U-001 | cadastro: no início só aparecem "Nível de formação" e "Situação" | aprovado | {"vis": [], "sit": "Situação"} |
| S5U-002 | cadastro: Ensino Médio → mostra só ['medio'] | aprovado | ["medio"] |
| S5U-003 | cadastro: EM + "também faço curso técnico" → mostra só ['medio', 'tecnico em-tecnico'] | aprovado | ["medio", "tecnico em-tecnico"] |
| S5U-004 | cadastro: área técnica "Outro" → mostra só ['medio', 'tecnico em-tecnico', 'qual:cadCursoTecnico'] | aprovado | ["medio", "tecnico em-tecnico", "qual:cadCursoTecnico"] |
| S5U-005 | cadastro: área técnica da lista → mostra só ['medio', 'tecnico em-tecnico'] | aprovado | ["medio", "tecnico em-tecnico"] |
| S5U-006 | cadastro: Ensino Superior → mostra só ['superior'] | aprovado | ["superior"] |
| S5U-007 | cadastro: curso superior "Outro" → mostra só ['superior', 'qual:cadCursoSuperior'] | aprovado | ["superior", "qual:cadCursoSuperior"] |
| S5U-008 | cadastro: Curso profissionalizante → mostra só ['profissionalizante'] | aprovado | ["profissionalizante"] |
| S5U-009 | cadastro: Outro (nível) → mostra só ['outro'] | aprovado | ["outro"] |
| S5U-010 | cadastro: Ensino Técnico → mostra só ['tecnico em-tecnico'] | aprovado | ["tecnico em-tecnico"] |
| S5U-011 | cadastro: todo campo de formação que aparece tem rótulo (e "Situação" tem legenda) | aprovado | [] |
| S5U-012 | cadastro: pelo teclado (seta no "Nível") o nível muda e os campos acompanham | aprovado | {"valor": "medio", "vis": ["medio"]} |
| S5U-013 | cadastro completo pela tela (EM 3º ano, concluído, técnico Logística) grava o formato novo | aprovado | {"row": [{"formacao": "Ensino Médio — 3º ano", "s": "completo", "t": "tecnico", "c": "Logística"}], "url": "verificacoes/escolher_verificacao.php"} |
| S5U-014 | cadastro: sem erros de JavaScript | aprovado | [] |
| S5U-015 | 375 px: cadastro com EM + técnico + "Qual?" abertos, sem rolagem lateral | aprovado | {"sw": 375, "w": 375, "l": 39, "r": 336} |
| S5U-016 | perfil: a escolaridade abre com os campos do nível já gravado | aprovado | {"nivel": "medio", "vis": ["medio", "tecnico em-tecnico"]} |
| S5U-017 | perfil: trocar o nível mostra só os campos dele (Superior → lista de cursos; Outro → texto) | aprovado | [["superior"], ["outro"]] |
| S5U-018 | 1366px perfil: "+" → Ensino Técnico mostra a área; adicionar grava "Ensino Técnico — Contabilidade" e "Concluído (2025)" | aprovado | {"vis": ["tecnico em-tecnico"], "item": "Ensino Técnico — Contabilidade ETEC Tela 1366 • Concluído (2025)"} |
| S5U-019 | 1366px perfil: sem rolagem lateral com o formulário aberto e sem erros de JavaScript | aprovado | {"sw": 1366, "w": 1366, "erros": []} |
| S5U-020 | 375px perfil: "+" → Ensino Técnico mostra a área; adicionar grava "Ensino Técnico — Contabilidade" e "Concluído (2025)" | aprovado | {"vis": ["tecnico em-tecnico"], "item": "Ensino Técnico — Contabilidade ETEC Tela 375 • Concluído (2025)"} |
| S5U-021 | 375px perfil: sem rolagem lateral com o formulário aberto e sem erros de JavaScript | aprovado | {"sw": 375, "w": 375, "erros": []} |

## S5V — Stage 5 — identidade visual

Arquivo de resultado: `tests/interface_s5_result.json` · 25/27 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S5V-001 | 1366px: marca na barra = horizontal em 10 páginas × 2 temas (alt, proporção, inteira, dentro da barra) | aprovado | — |
| S5V-002 | 768px: marca na barra = horizontal em 10 páginas × 2 temas (alt, proporção, inteira, dentro da barra) | aprovado | — |
| S5V-003 | 375px: marca na barra = simbolo em 10 páginas × 2 temas (alt, proporção, inteira, dentro da barra) | aprovado | — |
| S5V-004 | 1366px: marca vertical em 7 telas empilhadas × 2 temas | aprovado | — |
| S5V-005 | 375px: marca vertical em 7 telas empilhadas × 2 temas | aprovado | — |
| S5V-006 | login: o título principal (h1) da tela é a marca, com nome "JovemConnect" | aprovado | — |
| S5V-007 | símbolo isolado nas 5 barras compactas (1366 px) e oculto em 375 px (não cabe) | aprovado | — |
| S5V-008 | rodapé da página inicial: marca horizontal (alt, carregada, proporção) | aprovado | [{'src': 'jovemconnect-horizontal.png', 'alt': 'JovemConnect', 'ok': True, 'nat': 3.08125, 'ren': 3.0811298076923075, 'w': 160.21875, 'h': 52, 'l': 602.890625,  |
| S5V-009 | ícone da aba (símbolo oficial) em 25 páginas | aprovado | [] |
| S5V-010 | 1366px dark: fotos oficiais carregadas, decorativas (alt vazio), véu azul + degradê, sem emoji, sem rolagem lateral — atrás do conteúdo, encostada à direita e à | **reprovado** | {"hf": {"l": 491.765625, "r": 1366, "t": 64, "b": 768, "w": 874.234375, "h": 704}, "hero": {"l": 0, "r": 1366, "t": 64, "b": 768, "w": 1366, "h": 704}, "af": {" |
| S5V-011 | 1366px light: fotos oficiais carregadas, decorativas (alt vazio), véu azul + degradê, sem emoji, sem rolagem lateral — atrás do conteúdo, encostada à direita e  | **reprovado** | {"hf": {"l": 491.765625, "r": 1366, "t": 64, "b": 768, "w": 874.234375, "h": 704}, "hero": {"l": 0, "r": 1366, "t": 64, "b": 768, "w": 1366, "h": 704}, "af": {" |
| S5V-012 | 768px dark: fotos oficiais carregadas, decorativas (alt vazio), véu azul + degradê, sem emoji, sem rolagem lateral — faixas de borda a borda que não cobrem o te | aprovado | {"hf": {"l": 0, "r": 768, "t": 476.78125, "b": 722.53125, "w": 768, "h": 245.75}, "hero": {"l": 0, "r": 768, "t": 64, "b": 722.53125, "w": 768, "h": 658.53125}, |
| S5V-013 | 768px light: fotos oficiais carregadas, decorativas (alt vazio), véu azul + degradê, sem emoji, sem rolagem lateral — faixas de borda a borda que não cobrem o t | aprovado | {"hf": {"l": 0, "r": 768, "t": 476.78125, "b": 722.53125, "w": 768, "h": 245.75}, "hero": {"l": 0, "r": 768, "t": 64, "b": 722.53125, "w": 768, "h": 658.53125}, |
| S5V-014 | 375px dark: fotos oficiais carregadas, decorativas (alt vazio), véu azul + degradê, sem emoji, sem rolagem lateral — faixas de borda a borda que não cobrem o te | aprovado | {"hf": {"l": 0, "r": 375, "t": 531.5625, "b": 731.5625, "w": 375, "h": 200}, "hero": {"l": 0, "r": 375, "t": 64, "b": 731.5625, "w": 375, "h": 667.5625}, "af":  |
| S5V-015 | 375px light: fotos oficiais carregadas, decorativas (alt vazio), véu azul + degradê, sem emoji, sem rolagem lateral — faixas de borda a borda que não cobrem o t | aprovado | {"hf": {"l": 0, "r": 375, "t": 531.5625, "b": 731.5625, "w": 375, "h": 200}, "hero": {"l": 0, "r": 375, "t": 64, "b": 731.5625, "w": 375, "h": 667.5625}, "af":  |
| S5V-016 | nenhum emoji/símbolo usado como ícone no texto visível ou em rótulos (25 páginas × 2 temas) | aprovado | — |
| S5V-017 | todo botão/link visível tem nome acessível (ícones sozinhos têm aria-label) | aprovado | — |
| S5V-018 | exceção mantida: botão de tema continua com ☀️ (claro) / 🌙 (escuro) em 24 verificações | aprovado | — |
| S5V-019 | botão do assistente: ícone de traço (sparkles), sem emoji, nome acessível | aprovado | {"txt": "", "icone": "i i-sparkles", "mask": "url(\"data:image/svg+", "rot": "Abrir assistente IA", "cor": "rgb(15, 23, 42)", "w": 21} |
| S5V-020 | botão de acessibilidade: ícone de traço (acessibilidade), sem ♿, nome acessível | aprovado | {"txt": "", "icone": "i i-acessibilidade", "mask": "url(\"data:image/svg+", "rot": "Abrir painel de acessibilidade", "cor": "rgb(139, 139, 158)", "w": 21} |
| S5V-021 | forced-colors (Windows): ícones usam a cor do sistema do texto de botões/links (não somem) | aprovado | [['rgb(0, 0, 0)', 'rgb(0, 0, 0)'], ['rgb(0, 0, 0)', 'rgb(0, 0, 0)'], ['rgb(0, 0, 159)', 'rgb(0, 0, 159)'], ['rgb(0, 0, 159)', 'rgb(0, 0, 159)']] |
| S5V-022 | contraste escuro: texto ≥ 4,5:1 em botão primário, aba ativa, links, badges e filtro ativo | aprovado | {"botão primário": 5.47, "aba ativa": 5.47, "links": 5.08, "badges": 5.72, "filtro ativo": 5.72, "links da página inicial": 5.73} |
| S5V-023 | contraste claro: texto ≥ 4,5:1 em botão primário, aba ativa, links, badges e filtro ativo | aprovado | {"botão primário": 5.47, "aba ativa": 5.47, "links": 6.15, "badges": 5.43, "filtro ativo": 5.43, "links da página inicial": 5.69} |
| S5V-024 | contraste alto contraste escuro: texto ≥ 4,5:1 em botão primário, aba ativa, links, badges e filtro ativo | aprovado | {"botão primário": 8.66, "aba ativa": 8.66, "links": 8.16, "badges": 5.31, "filtro ativo": 5.31, "links da página inicial": 8.66} |
| S5V-025 | contraste alto contraste claro: texto ≥ 4,5:1 em botão primário, aba ativa, links, badges e filtro ativo | aprovado | {"botão primário": 8.06, "aba ativa": 8.06, "links": 8.06, "badges": 6.27, "filtro ativo": 6.27, "links da página inicial": 8.06} |
| S5V-026 | assistente (dark): título no cabeçalho azul ≥ 4,5:1 (antes branco no azul: 3,3:1) | aprovado | [5.47] |
| S5V-027 | assistente (light): título no cabeçalho azul ≥ 4,5:1 (antes branco no azul: 3,3:1) | aprovado | [5.47] |

## S51M — Stage 5.1 — locais de trabalho e Match

Arquivo de resultado: `tests51/match_s51_result.json` · 90/90 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S51M-001 | perfil da empresa: item "Locais de trabalho" com link | aprovado | — |
| S51M-002 | locais.php abre (200) com a sede | aprovado | — |
| S51M-003 | adicionar escritório (UF em minúsculas aceita) | aprovado | locais.php?ok=adicionado |
| S51M-004 | adicionar filial | aprovado | — |
| S51M-005 | adicionar local só com cidade/UF | aprovado | — |
| S51M-006 | locais gravados com UF maiúscula e CEP só dígitos | aprovado | {"id": 9, "empresa_id": 39, "tipo": "escritorio", "nome": "Centro", "cep": "01001000", "logradouro": "Praça da Sé", "numero": "100", "complemento": "", "bairro" |
| S51M-007 | local sem cidade: recusado com mensagem | aprovado | — |
| S51M-008 | local sem cidade: erro mostrado e dados mantidos | aprovado | — |
| S51M-009 | CEP com menos de 8 dígitos: recusado | aprovado | — |
| S51M-010 | UF inválida: recusada | aprovado | — |
| S51M-011 | editar local de OUTRA empresa: recusado | aprovado | — |
| S51M-012 | remover local de OUTRA empresa: recusado | aprovado | — |
| S51M-013 | POST sem CSRF: recusado | aprovado | — |
| S51M-014 | criar vaga: opções de local (principal + 3 locais + outro) | aprovado | — |
| S51M-015 | criar vaga: "Outro endereço" começa escondido e desabilitado | aprovado | — |
| S51M-016 | caso D — vaga no endereço principal | aprovado | — |
| S51M-017 | caso E — vaga em local específico | aprovado | — |
| S51M-018 | caso F — empresa com sede + filial: vaga na filial | aprovado | — |
| S51M-019 | caso H — vaga em local com endereço incompleto | aprovado | — |
| S51M-020 | "Outro endereço": cria o local e a vaga juntos | aprovado | — |
| S51M-021 | "Outro endereço" sem cidade: recusado, nada gravado | aprovado | — |
| S51M-022 | mensagem de erro do local aparece | aprovado | — |
| S51M-023 | local de OUTRA empresa no POST: recusado | aprovado | — |
| S51M-024 | valor de local desconhecido ("local:abc"): usa o padrão (endereço principal) | aprovado | minhas.php?publicada=1 |
| S51M-025 | formulário antigo (sem local_trabalho): comportamento anterior | aprovado | — |
| S51M-026 | empresa só com cidade/UF: vaga no endereço principal | aprovado | — |
| S51M-027 | local efetivo vaga 52: empresa/completo | aprovado | {"origem": "empresa", "rotulo": "Endereço principal da empresa", "texto": "Avenida Paulista, 1000 - Sala 1 - Bela Vista - São Paulo/SP - CEP 01310-100", "nivel" |
| S51M-028 | local efetivo vaga 53: local/completo | aprovado | {"origem": "local", "rotulo": "Escritório — Centro", "texto": "Praça da Sé, 100 - Sé - São Paulo/SP - CEP 01001-000", "nivel": "completo", "faltando": []} |
| S51M-029 | local efetivo vaga 54: local/completo | aprovado | {"origem": "local", "rotulo": "Filial — Campinas", "texto": "Rua Barão de Jaguara, 900 - Centro - Campinas/SP - CEP 13010-111", "nivel": "completo", "faltando": |
| S51M-030 | local efetivo vaga 55: local/parcial | aprovado | {"origem": "local", "rotulo": "Unidade — Guarulhos", "texto": "Guarulhos/SP", "nivel": "parcial", "faltando": ["rua", "número", "bairro", "CEP"]} |
| S51M-031 | local efetivo vaga 56: local/completo | aprovado | {"origem": "local", "rotulo": "Unidade — Osasco", "texto": "Rua Antônio Agú, 50 - Centro - Osasco/SP - CEP 06010-000", "nivel": "completo", "faltando": []} |
| S51M-032 | local efetivo vaga 58: vaga/parcial | aprovado | {"origem": "vaga", "rotulo": "Endereço informado na vaga", "texto": "Barueri/SP - CEP 06454-000", "nivel": "parcial", "faltando": ["rua", "número", "bairro"]} |
| S51M-033 | local efetivo vaga 59: empresa/completo | aprovado | {"origem": "empresa", "rotulo": "Endereço principal da empresa", "texto": "Avenida Paulista, 1000 - Sala 1 - Bela Vista - São Paulo/SP - CEP 01310-100", "nivel" |
| S51M-034 | local efetivo vaga 60: empresa/parcial | aprovado | {"origem": "empresa", "rotulo": "Endereço principal da empresa", "texto": "Santo André/SP", "nivel": "parcial", "faltando": ["rua", "número", "bairro", "CEP"]} |
| S51M-035 | caso H — incompleto: lista o que falta, nada inventado | aprovado | {"origem": "local", "rotulo": "Unidade — Guarulhos", "texto": "Guarulhos/SP", "nivel": "parcial", "faltando": ["rua", "número", "bairro", "CEP"]} |
| S51M-036 | detalhes da vaga mostram o local efetivo | aprovado | — |
| S51M-037 | página de análise mostra o local efetivo | aprovado | — |
| S51M-038 | caso A — compatível: nota alta | aprovado | 80 |
| S51M-039 | caso A: nota = soma dos pontos por critério (pesos 25/25/25/15/10) | aprovado | score=80 soma=80 |
| S51M-040 | caso A — mesma nota que o Flask (algoritmo original) | aprovado | 80 80 |
| S51M-041 | nota da lista (match) = nota da análise detalhada | aprovado | — |
| S51M-042 | análise: limitações declaradas (sem km, sem transporte) | aprovado | — |
| S51M-043 | análise: sem chave/URL/erro interno | aprovado | — |
| S51M-044 | caso B — formação incompatível: escolaridade 0, técnico 0, ajuste −10 | aprovado | {"nome": "Requisito de conclusão", "pontos": -10, "explicacao": "A descrição da vaga pede formação concluída e você está cursando."} |
| S51M-045 | caso B — "o que reduziu" explica escolaridade e o requisito | aprovado | — |
| S51M-046 | caso B: nota = soma dos pontos por critério (pesos 25/25/25/15/10) | aprovado | score=40 soma=40 |
| S51M-047 | caso C — área diferente: 0 de 25 e explicação clara | aprovado | — |
| S51M-048 | caso C — nota menor que a do caso A | aprovado | 55 &lt; 80 |
| S51M-049 | caso C — nenhuma categoria inventada (só os 5 critérios do algoritmo) | aprovado | — |
| S51M-050 | caso G — cidade diferente: 0 pontos, sem afirmar distância/transporte | aprovado | O local de trabalho fica em São Paulo/SP; você informou Santo André/SP. |
| S51M-051 | nome de cidade parecido (12 pts do algoritmo): explicação diz que NÃO indica distância | aprovado | Cidades diferentes (Paulo Afonso/BA e São Paulo/SP) com parte do nome em comum — isso não indica a distância entre elas. |
| S51M-052 | caso F — vaga na filial (Campinas): localização usa a filial, não a sede | aprovado | — |
| S51M-053 | vaga antiga sem cidade: Match usa o endereço da empresa (fallback) | aprovado | — |
| S51M-054 | perfil mudou → nota da lista atualizada (antes ficava a antiga) | aprovado | {"score": 55, "analise": "Compatibilidade razoável. Você atende parte dos requisitos — considere se candidatar e destaque seus pontos fortes.", "motivos": ["A v |
| S51M-055 | análise detalhada acompanha a mesma nota | aprovado | — |
| S51M-056 | análise detalhada também sincroniza a nota guardada | aprovado | — |
| S51M-057 | empresa: análise do candidato público na própria vaga | aprovado | — |
| S51M-058 | empresa: candidato com perfil privado → 404 na análise | aprovado | — |
| S51M-059 | empresa: nota simples (match) de candidato privado SEM candidatura → 404 (Stage 6: regra única de acesso) | aprovado | — |
| S51M-060 | empresa: vaga de OUTRA empresa → 403 | aprovado | — |
| S51M-061 | jovem: jovem_id de outro é ignorado (usa a sessão) | aprovado | — |
| S51M-062 | vaga inexistente → 404 sem detalhes | aprovado | — |
| S51M-063 | sem login → recusado | aprovado | — |
| S51M-064 | sem CSRF → recusado | aprovado | — |
| S51M-065 | contexto sem CSRF → recusado | aprovado | — |
| S51M-066 | contexto: disponível com análise validada | aprovado | {"disponivel":true,"analise":{"resumo":"Seu perfil combina com a \u00e1rea e o tipo de contrato da vaga. A localiza\u00e7\u00e3o \u00e9 a mesma cidade.","pontos |
| S51M-067 | contexto: sem chave/URL interna na resposta | aprovado | — |
| S51M-068 | contexto: IA não traz nota | aprovado | — |
| S51M-069 | contexto: uma chamada ao Gemini | aprovado | 1 |
| S51M-070 | privacidade: nada de nome, e-mail, CPF, telefone, nascimento, resumo, CEP, empresa, salário, senha, sessão, token | aprovado | — |
| S51M-071 | privacidade: sem IDs internos | aprovado | — |
| S51M-072 | payload: só o local efetivo (tipo, bairro, cidade, UF) | aprovado | — |
| S51M-073 | payload: habilidades e nota calculada vão para o contexto | aprovado | — |
| S51M-074 | payload: leitor = jovem | aprovado | — |
| S51M-075 | empresa: contexto escrito para quem recruta (leitor = empresa) | aprovado | — |
| S51M-076 | caso I — limite da API: {"disponivel": false} e nada interno | aprovado | {"disponivel":false} |
| S51M-077 | caso I — resposta inválida: {"disponivel": false} e nada interno | aprovado | {"disponivel":false} |
| S51M-078 | caso I — erro do serviço: {"disponivel": false} e nada interno | aprovado | {"disponivel":false} |
| S51M-079 | caso I — chave inválida: {"disponivel": false} e nada interno | aprovado | {"disponivel":false} |
| S51M-080 | IA com HTML/script: devolvido como texto puro (JSON), sem controle | aprovado | — |
| S51M-081 | Gemini lento: fallback no tempo limite do Flask (~3 s) | aprovado | 3.0s |
| S51M-082 | enquanto a IA responde, outras páginas do usuário não travam | aprovado | 0.01s |
| S51M-083 | limite: 20 análises contextuais por 10 min por sessão; a 21ª volta {"disponivel": false} | aprovado | 20 |
| S51M-084 | editar local: vagas que usam o local acompanham (cidade/CEP) | aprovado | — |
| S51M-085 | Match usa o endereço novo do local | aprovado | — |
| S51M-086 | remover local em uso: bloqueado | aprovado | — |
| S51M-087 | remover local sem vagas: ok | aprovado | — |
| S51M-088 | perfil da empresa salvo | aprovado | perfil.php?saved=1 |
| S51M-089 | editar endereço principal: vagas no endereço principal acompanham | aprovado | {"cidade": "Rio de Janeiro", "estado": "RJ", "cep": "20040-020"} perfil.php?saved=1 |
| S51M-090 | vaga antiga com cidade própria NÃO muda com o endereço da empresa | aprovado | — |

## S51U — Stage 5.1 — página de análise (interface)

Arquivo de resultado: `tests51/ui_s51_result.json` · 101/101 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S51U-001 | 1366px: só o resultado aparece (carregando e erro escondidos) | aprovado | [False, True, False] |
| S51U-002 | 1366px: nota e rótulo iguais aos do servidor | aprovado | {"centro": 360, "score": 360, "botao": 359.9921875, "valor": "55", "rotulo": "Compatibilidade razoável", "barra": "55%", "sw": 1366, "w": 1366} |
| S51U-003 | 1366px: nota e botão centralizados no card | aprovado | — |
| S51U-004 | 1366px: sem rolagem lateral | aprovado | 1366 &gt; 1366 |
| S51U-005 | 1366px: Enter abre o modal (foco dentro, página travada) | aprovado | {"aberto": true, "focoDentro": true, "focoId": "", "focoClasse": "jc-modal-fechar", "trava": true} |
| S51U-006 | 1366px: 5 categorias com nota 0–100, barra e explicação (iguais ao servidor) | aprovado | [{"nome": "Localização", "valor": "0/100", "barra": "0%", "pontos": "0 de 25 pontos", "exp": "O local de trabalho fica em Rio de Janeiro/RJ; você informou São P |
| S51U-007 | 1366px: pontos fortes, o que reduziu, local e limitações preenchidos | aprovado | {"fortes": 3, "reduz": 2, "limites": 3, "local": "Endereço principal da empresa \| Avenida Rio Branco, 1 - Centro - Rio de Janeiro/RJ - CEP 20040-020", "modalSco |
| S51U-008 | 1366px: análise contextual identificada (IA, confiança, "não altera a nota") | aprovado | Gerada por IA (Gemini) · confiança média · não altera a nota |
| S51U-009 | 1366px: comentários da IA nas categorias certas (Localização) e "Habilidades" à parte | aprovado | {"selo": "Gerada por IA (Gemini) · confiança média · não altera a nota", "notaCat": ["Localização"], "extra": ["Habilidades: Suas habilidades em Excel ajudam na |
| S51U-010 | 1366px: modal cabe na largura; rola pelo fundo (padrão dos modais) com o × sempre visível | aprovado | {"l": 323, "r": 1043, "w": 1366, "h": 768, "sw": 1366, "overflow": "auto", "alto": true, "xTop": 40, "xBottom": 76, "rodapeVisivel": true} |
| S51U-011 | 1366px: Tab/Shift+Tab não saem do modal | aprovado | [True, True, True, True, True, True, True, True, True, True, True, True, True, True, True, True] |
| S51U-012 | 1366px: Esc fecha, destrava a página e devolve o foco ao botão | aprovado | {"aberto": false, "focoDentro": false, "focoId": "btnAnaliseDetalhada", "focoClasse": "btn btn-primary", "trava": false} |
| S51U-013 | 1366px: clique dentro não fecha; clique fora fecha | aprovado | — |
| S51U-014 | 1366px: × fecha e devolve o foco | aprovado | — |
| S51U-015 | 1366px: botão "Fechar" fecha | aprovado | — |
| S51U-016 | 1366px: sem erros de JavaScript | aprovado | — |
| S51U-017 | 768px: só o resultado aparece (carregando e erro escondidos) | aprovado | [False, True, False] |
| S51U-018 | 768px: nota e rótulo iguais aos do servidor | aprovado | {"centro": 360, "score": 360, "botao": 359.9921875, "valor": "55", "rotulo": "Compatibilidade razoável", "barra": "55%", "sw": 768, "w": 768} |
| S51U-019 | 768px: nota e botão centralizados no card | aprovado | — |
| S51U-020 | 768px: sem rolagem lateral | aprovado | 768 &gt; 768 |
| S51U-021 | 768px: Enter abre o modal (foco dentro, página travada) | aprovado | {"aberto": true, "focoDentro": true, "focoId": "", "focoClasse": "jc-modal-fechar", "trava": true} |
| S51U-022 | 768px: 5 categorias com nota 0–100, barra e explicação (iguais ao servidor) | aprovado | [{"nome": "Localização", "valor": "0/100", "barra": "0%", "pontos": "0 de 25 pontos", "exp": "O local de trabalho fica em Rio de Janeiro/RJ; você informou São P |
| S51U-023 | 768px: pontos fortes, o que reduziu, local e limitações preenchidos | aprovado | {"fortes": 3, "reduz": 2, "limites": 3, "local": "Endereço principal da empresa \| Avenida Rio Branco, 1 - Centro - Rio de Janeiro/RJ - CEP 20040-020", "modalSco |
| S51U-024 | 768px: análise contextual identificada (IA, confiança, "não altera a nota") | aprovado | Gerada por IA (Gemini) · confiança média · não altera a nota |
| S51U-025 | 768px: comentários da IA nas categorias certas (Localização) e "Habilidades" à parte | aprovado | {"selo": "Gerada por IA (Gemini) · confiança média · não altera a nota", "notaCat": ["Localização"], "extra": ["Habilidades: Suas habilidades em Excel ajudam na |
| S51U-026 | 768px: modal cabe na largura; rola pelo fundo (padrão dos modais) com o × sempre visível | aprovado | {"l": 24, "r": 744, "w": 768, "h": 1024, "sw": 768, "overflow": "auto", "alto": true, "xTop": 40, "xBottom": 76, "rodapeVisivel": true} |
| S51U-027 | 768px: Tab/Shift+Tab não saem do modal | aprovado | [True, True, True, True, True, True, True, True, True, True, True, True, True, True, True, True] |
| S51U-028 | 768px: Esc fecha, destrava a página e devolve o foco ao botão | aprovado | {"aberto": false, "focoDentro": false, "focoId": "btnAnaliseDetalhada", "focoClasse": "btn btn-primary", "trava": false} |
| S51U-029 | 768px: clique dentro não fecha; clique fora fecha | aprovado | — |
| S51U-030 | 768px: × fecha e devolve o foco | aprovado | — |
| S51U-031 | 768px: botão "Fechar" fecha | aprovado | — |
| S51U-032 | 768px: sem erros de JavaScript | aprovado | — |
| S51U-033 | 375px: só o resultado aparece (carregando e erro escondidos) | aprovado | [False, True, False] |
| S51U-034 | 375px: nota e rótulo iguais aos do servidor | aprovado | {"centro": 187.5, "score": 187.5, "botao": 187.4921875, "valor": "55", "rotulo": "Compatibilidade razoável", "barra": "55%", "sw": 375, "w": 375} |
| S51U-035 | 375px: nota e botão centralizados no card | aprovado | — |
| S51U-036 | 375px: sem rolagem lateral | aprovado | 375 &gt; 375 |
| S51U-037 | 375px: Enter abre o modal (foco dentro, página travada) | aprovado | {"aberto": true, "focoDentro": true, "focoId": "", "focoClasse": "jc-modal-fechar", "trava": true} |
| S51U-038 | 375px: 5 categorias com nota 0–100, barra e explicação (iguais ao servidor) | aprovado | [{"nome": "Localização", "valor": "0/100", "barra": "0%", "pontos": "0 de 25 pontos", "exp": "O local de trabalho fica em Rio de Janeiro/RJ; você informou São P |
| S51U-039 | 375px: pontos fortes, o que reduziu, local e limitações preenchidos | aprovado | {"fortes": 3, "reduz": 2, "limites": 3, "local": "Endereço principal da empresa \| Avenida Rio Branco, 1 - Centro - Rio de Janeiro/RJ - CEP 20040-020", "modalSco |
| S51U-040 | 375px: análise contextual identificada (IA, confiança, "não altera a nota") | aprovado | Gerada por IA (Gemini) · confiança média · não altera a nota |
| S51U-041 | 375px: comentários da IA nas categorias certas (Localização) e "Habilidades" à parte | aprovado | {"selo": "Gerada por IA (Gemini) · confiança média · não altera a nota", "notaCat": ["Localização"], "extra": ["Habilidades: Suas habilidades em Excel ajudam na |
| S51U-042 | 375px: modal cabe na largura; rola pelo fundo (padrão dos modais) com o × sempre visível | aprovado | {"l": 16, "r": 359, "w": 375, "h": 667, "sw": 375, "overflow": "auto", "alto": true, "xTop": 40, "xBottom": 76, "rodapeVisivel": true} |
| S51U-043 | 375px: Tab/Shift+Tab não saem do modal | aprovado | [True, True, True, True, True, True, True, True, True, True, True, True, True, True, True, True] |
| S51U-044 | 375px: Esc fecha, destrava a página e devolve o foco ao botão | aprovado | {"aberto": false, "focoDentro": false, "focoId": "btnAnaliseDetalhada", "focoClasse": "btn btn-primary", "trava": false} |
| S51U-045 | 375px: clique dentro não fecha; clique fora fecha | aprovado | — |
| S51U-046 | 375px: × fecha e devolve o foco | aprovado | — |
| S51U-047 | 375px: botão "Fechar" fecha | aprovado | — |
| S51U-048 | 375px: sem erros de JavaScript | aprovado | — |
| S51U-049 | erro no cálculo: só o aviso aparece, com mensagem simples | aprovado | [False, False, True] Erro interno. Tente novamente em instantes. |
| S51U-050 | "Tentar novamente" recalcula e mostra o resultado | aprovado | [False, True, False] |
| S51U-051 | 1366px dark: contraste ≥ 4,5:1 (texto) / 3:1 (grande) no card e no modal (63 textos) | aprovado | [] |
| S51U-052 | 375px dark: contraste ≥ 4,5:1 (texto) / 3:1 (grande) no card e no modal (63 textos) | aprovado | [] |
| S51U-053 | 1366px light: contraste ≥ 4,5:1 (texto) / 3:1 (grande) no card e no modal (63 textos) | aprovado | [] |
| S51U-054 | 375px light: contraste ≥ 4,5:1 (texto) / 3:1 (grande) no card e no modal (63 textos) | aprovado | [] |
| S51U-055 | 1366px dark + alto contraste: contraste ≥ 4,5:1 (texto) / 3:1 (grande) no card e no modal (63 textos) | aprovado | [] |
| S51U-056 | 375px dark + alto contraste: contraste ≥ 4,5:1 (texto) / 3:1 (grande) no card e no modal (63 textos) | aprovado | [] |
| S51U-057 | 1366px light + alto contraste: contraste ≥ 4,5:1 (texto) / 3:1 (grande) no card e no modal (63 textos) | aprovado | [] |
| S51U-058 | 375px light + alto contraste: contraste ≥ 4,5:1 (texto) / 3:1 (grande) no card e no modal (63 textos) | aprovado | [] |
| S51U-059 | HTML/script vindo da IA aparece como texto (nada executa, nenhum elemento criado) | aprovado | {"xss": null, "elementos": 0, "texto": "Gerada por IA (Gemini) · confiança média · não altera a nota&lt;img src=x onerror=\"window.xss=1\"&gt; &lt;script&gt;window.xss=2&lt;/s |
| S51U-060 | IA indisponível: aviso simples, nota e categorias continuam | aprovado | {"msg": "A análise contextual por IA não está disponível agora. A nota e as categorias acima vêm do Match tradicional e continuam valendo.", "cats": 5, "nota":  |
| S51U-061 | empresa: análise escrita sobre "o candidato" | aprovado | Localização 0/100  0 de 25 pontos  O local de trabalho fica em Rio de Janeiro/RJ; o candidato informou São Paulo/SP.  Co |
| S51U-062 | dashboard/jovem.php 1366px: resultado do Match e link legíveis (contraste) | aprovado | [] |
| S51U-063 | dashboard/jovem.php 1366px: link "Ver análise detalhada" no card, dentro do rodapé, sem rolagem lateral | aprovado | {"href": "../Vagas/avaliar.php?vaga_id=1", "txt": "Ver análise detalhada", "dentro": true, "sw": 1366, "w": 1366, "n": 1} |
| S51U-064 | dashboard/jovem.php 1366px: o link não se repete | aprovado | — |
| S51U-065 | dashboard/jovem.php 1366px: o link abre a análise da vaga | aprovado | http://localhost/JovemConnect/Vagas/avaliar.php?vaga_id=1 |
| S51U-066 | dashboard/jovem.php 375px: resultado do Match e link legíveis (contraste) | aprovado | [] |
| S51U-067 | dashboard/jovem.php 375px: link "Ver análise detalhada" no card, dentro do rodapé, sem rolagem lateral | aprovado | {"href": "../Vagas/avaliar.php?vaga_id=1", "txt": "Ver análise detalhada", "dentro": true, "sw": 375, "w": 375, "n": 1} |
| S51U-068 | dashboard/jovem.php 375px: o link não se repete | aprovado | — |
| S51U-069 | dashboard/jovem.php 375px: o link abre a análise da vaga | aprovado | http://localhost/JovemConnect/Vagas/avaliar.php?vaga_id=1 |
| S51U-070 | Vagas/listar.php 1366px: resultado do Match e link legíveis (contraste) | aprovado | [] |
| S51U-071 | Vagas/listar.php 1366px: link "Ver análise detalhada" no card, dentro do rodapé, sem rolagem lateral | aprovado | {"href": "avaliar.php?vaga_id=1", "txt": "Ver análise detalhada", "dentro": true, "sw": 1366, "w": 1366, "n": 1} |
| S51U-072 | Vagas/listar.php 1366px: o link não se repete | aprovado | — |
| S51U-073 | Vagas/listar.php 1366px: o link abre a análise da vaga | aprovado | http://localhost/JovemConnect/Vagas/avaliar.php?vaga_id=1 |
| S51U-074 | Vagas/listar.php 375px: resultado do Match e link legíveis (contraste) | aprovado | [] |
| S51U-075 | Vagas/listar.php 375px: link "Ver análise detalhada" no card, dentro do rodapé, sem rolagem lateral | aprovado | {"href": "avaliar.php?vaga_id=1", "txt": "Ver análise detalhada", "dentro": true, "sw": 375, "w": 375, "n": 1} |
| S51U-076 | Vagas/listar.php 375px: o link não se repete | aprovado | — |
| S51U-077 | Vagas/listar.php 375px: o link abre a análise da vaga | aprovado | http://localhost/JovemConnect/Vagas/avaliar.php?vaga_id=1 |
| S51U-078 | empresa (candidatos, dark): resultado do Match legível e link para a análise | aprovado | [] avaliar.php?vaga_id=12&amp;jovem_id=26 |
| S51U-079 | empresa (candidatos, light): resultado do Match legível e link para a análise | aprovado | [] avaliar.php?vaga_id=12&amp;jovem_id=26 |
| S51U-080 | locais.php 1366px dark: sem rolagem lateral, lista de locais | aprovado | {"sw": 1366, "w": 1366, "itens": 4} |
| S51U-081 | locais.php 1366px dark: contraste dos textos | aprovado | [] |
| S51U-082 | locais.php 1366px light: sem rolagem lateral, lista de locais | aprovado | {"sw": 1366, "w": 1366, "itens": 4} |
| S51U-083 | locais.php 1366px light: contraste dos textos | aprovado | [] |
| S51U-084 | criar.php 1366px: "Outro endereço" começa escondido e desabilitado | aprovado | — |
| S51U-085 | criar.php 1366px: marcar "Outro endereço" mostra e habilita os campos | aprovado | — |
| S51U-086 | criar.php 1366px dark: contraste das opções de local (marcada e não marcada) | aprovado | [] |
| S51U-087 | criar.php 1366px light: contraste das opções de local (marcada e não marcada) | aprovado | [] |
| S51U-088 | criar.php 1366px: sem rolagem lateral | aprovado | {"sw": 1366, "w": 1366} |
| S51U-089 | criar.php 1366px: "Outro endereço" sem cidade não envia; avisa e foca a cidade | aprovado | foco=vagaLocalCidade aviso=Informe a cidade e a UF do local de trabalho. |
| S51U-090 | criar.php 1366px: voltar ao endereço principal esconde e desabilita | aprovado | — |
| S51U-091 | locais.php 375px dark: sem rolagem lateral, lista de locais | aprovado | {"sw": 375, "w": 375, "itens": 4} |
| S51U-092 | locais.php 375px dark: contraste dos textos | aprovado | [] |
| S51U-093 | locais.php 375px light: sem rolagem lateral, lista de locais | aprovado | {"sw": 375, "w": 375, "itens": 4} |
| S51U-094 | locais.php 375px light: contraste dos textos | aprovado | [] |
| S51U-095 | criar.php 375px: "Outro endereço" começa escondido e desabilitado | aprovado | — |
| S51U-096 | criar.php 375px: marcar "Outro endereço" mostra e habilita os campos | aprovado | — |
| S51U-097 | criar.php 375px dark: contraste das opções de local (marcada e não marcada) | aprovado | [] |
| S51U-098 | criar.php 375px light: contraste das opções de local (marcada e não marcada) | aprovado | [] |
| S51U-099 | criar.php 375px: sem rolagem lateral | aprovado | {"sw": 375, "w": 375} |
| S51U-100 | criar.php 375px: "Outro endereço" sem cidade não envia; avisa e foca a cidade | aprovado | foco=vagaLocalCidade aviso=Informe a cidade e a UF do local de trabalho. |
| S51U-101 | criar.php 375px: voltar ao endereço principal esconde e desabilita | aprovado | — |

## S51C — Stage 5.1 — análise contextual (Flask + Gemini simulado)

Arquivo de resultado: `tests51/contexto_flask_result.json` · 33/33 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S51C-001 | ping: diz só se há chave (sem mostrar) | aprovado | — |
| S51C-002 | valido: disponível com análise validada | aprovado | {"analise":{"categorias":[{"explicacao":"Suas habilidades em Excel ajudam nas rotinas administrativas.","nome":"Habilidades"},{"explicacao":"O trabalho \u00e9 n |
| S51C-003 | valido: chave só no cabeçalho x-goog-api-key (não na URL) | aprovado | /v1beta/models/gemini-3.5-flash-lite:generateContent |
| S51C-004 | valido: pede JSON estruturado (responseMimeType + responseSchema) e temperatura baixa | aprovado | — |
| S51C-005 | valido: instruções proíbem nova nota e transporte/distância inventados | aprovado | — |
| S51C-006 | valido: dados enviados = exatamente os recebidos do PHP | aprovado | — |
| S51C-007 | valido: resposta sem chave/URL interna | aprovado | — |
| S51C-008 | cache: mesmos dados → do_cache sem nova chamada | aprovado | — |
| S51C-009 | xss: texto devolvido como texto (sem caracteres de controle) | aprovado | &lt;img src=x onerror="window.xss=1"&gt; &lt;script&gt;window.xss=2&lt;/script&gt; Texto em &lt;b&gt;negrito&lt;/b&gt; |
| S51C-010 | nota: campos de nota descartados | aprovado | {"categorias": [{"explicacao": "Boas habilidades.", "nome": "Habilidades"}], "confianca": "alta", "observacoes": ["Não há dados de transporte público ou tempo d |
| S51C-011 | nota: só categorias permitidas, sem duplicadas/malformadas | aprovado | — |
| S51C-012 | nota: confiança normalizada (ALTA → alta) | aprovado | — |
| S51C-013 | cercas ```json: aceito | aprovado | — |
| S51C-014 | enorme: limites de tamanho/quantidade | aprovado | 600 1 1 |
| S51C-015 | modelo sem responseSchema (400): repete sem esquema e funciona | aprovado | — |
| S51C-016 | json_invalido: disponivel=false (resposta_invalida), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"resposta_invalida"}  |
| S51C-017 | sem_campos: disponivel=false (resposta_invalida), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"resposta_invalida"}  |
| S51C-018 | tipos_errados: disponivel=false (resposta_invalida), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"resposta_invalida"}  |
| S51C-019 | sem_candidatos: disponivel=false (resposta_invalida), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"resposta_invalida"}  |
| S51C-020 | corpo_quebrado: disponivel=false (resposta_invalida), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"resposta_invalida"}  |
| S51C-021 | http400_chave: disponivel=false (chave_invalida), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"chave_invalida"}  |
| S51C-022 | http401: disponivel=false (chave_invalida), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"chave_invalida"}  |
| S51C-023 | http403: disponivel=false (chave_invalida), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"chave_invalida"}  |
| S51C-024 | http404: disponivel=false (modelo_indisponivel), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"modelo_indisponivel"}  |
| S51C-025 | http429: disponivel=false (limite), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"limite"}  |
| S51C-026 | http500: disponivel=false (indisponivel), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"indisponivel"}  |
| S51C-027 | http503: disponivel=false (indisponivel), HTTP 200, sem vazamento | aprovado | {"disponivel":false,"motivo":"indisponivel"}  |
| S51C-028 | chave inválida (400 API_KEY_INVALID): sem repetir a chamada | aprovado | — |
| S51C-029 | lento (8 s, limite 3 s): timeout em ~3 s | aprovado | 3.0s |
| S51C-030 | entrada não-JSON → 400 sem detalhes | aprovado | — |
| S51C-031 | entrada grande demais → 400 | aprovado | — |
| S51C-032 | entrada que não é objeto → 400 | aprovado | — |
| S51C-033 | log do Flask sem a chave | aprovado | — |

## S51F — Stage 5.1 — Flask fora do ar

Arquivo de resultado: `tests51/fallback_flask_fora_result.json` · 7/7 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S51F-001 | Flask fora: match de par novo calcula a nota em PHP (antes: "Servidor de IA indisponível") | aprovado | {"score":30,"analise":"Compatibilidade baixa com seu perfil atual. Explore outras vagas dispon\u00edveis que combinam melhor com voc\u00ea.","motivos":["Ensino  |
| S51F-002 | match rápido | aprovado | 0.02s |
| S51F-003 | nota guardada (cache) igual à devolvida | aprovado | — |
| S51F-004 | análise detalhada funciona (categorias reais) | aprovado | — |
| S51F-005 | contexto: {"disponivel": false}, sem erro interno | aprovado | {"disponivel":false} |
| S51F-006 | contexto responde rápido | aprovado | 0.02s |
| S51F-007 | página de análise abre normalmente | aprovado | — |

## S51K — Stage 5.1 — sem chave do Gemini

Arquivo de resultado: `tests51/fallback_sem_chave_result.json` · 7/7 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S51K-001 | sem chave: match normal pelo Flask | aprovado | {"score":30,"analise":"Compatibilidade baixa com seu perfil atual. Explore outras vagas dispon\u00edveis que combinam melhor com voc\u00ea.","motivos":["Ensino  |
| S51K-002 | match rápido | aprovado | 0.03s |
| S51K-003 | nota guardada (cache) igual à devolvida | aprovado | — |
| S51K-004 | análise detalhada funciona (categorias reais) | aprovado | — |
| S51K-005 | contexto: {"disponivel": false}, sem erro interno | aprovado | {"disponivel":false} |
| S51K-006 | contexto responde rápido | aprovado | 0.03s |
| S51K-007 | página de análise abre normalmente | aprovado | — |

## S6C — Stage 6 — cadastro em etapas

Arquivo de resultado: `tests6/cadastro_s6_result.json` · 50/50 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S6C-001 | jovem 1366px: abre na etapa 1 de 4 (só ela visível, barra 1/4) | aprovado | {"visiveis": ["1"], "contador": "Etapa 1 de 4: Informações básicas", "barra": "1", "erros": [], "foco": ""} |
| S6C-002 | jovem 1366px: "Continuar" com campos vazios não avança e mostra erros perto dos campos | aprovado | {"visiveis": ["1"], "contador": "Etapa 1 de 4: Informações básicas", "barra": "1", "erros": ["Informe seu nome completo.", "Preencha este campo.", "Informe sua  |
| S6C-003 | jovem 1366px: e-mail inválido e senhas diferentes são apontados | aprovado | ["Digite um e-mail válido (ex.: [e-mail]).", "As senhas não coincidem."] |
| S6C-004 | jovem 1366px: erro some ao corrigir o campo | aprovado | — |
| S6C-005 | jovem 1366px: avança para a etapa 2 (Formação) e o foco vai para o título | aprovado | {"visiveis": ["2"], "contador": "Etapa 2 de 4: Formação", "barra": "2", "erros": [], "foco": "etapa2Titulo"} |
| S6C-006 | jovem 1366px: formação obrigatória (nível) é exigida | aprovado | — |
| S6C-007 | jovem 1366px: etapa 3 (perfil profissional) é opcional — dá para avançar vazia | aprovado | — |
| S6C-008 | jovem 1366px: "Voltar" mostra a etapa 2 com os dados mantidos | aprovado | — |
| S6C-009 | jovem 1366px: etapa 4 traz a revisão com os dados digitados | aprovado | Nome Jovem Etapas 1366 E-mail [e-mail] Área de interesse Administrativo Objetivo profissional Quero meu primeiro emprego. Habilidades Excel, Comunicação, excel  |
| S6C-010 | jovem 1366px: caixa de aceite não vem marcada | aprovado | — |
| S6C-011 | jovem 1366px: sem aceite não envia e explica | aprovado | ["Para criar a conta, leia e aceite os Termos de Uso e a Política de Privacidade."] |
| S6C-012 | jovem 1366px: link "Termos de Uso" abre o modal e não marca a caixa | aprovado | — |
| S6C-013 | jovem 1366px: sem rolagem lateral | aprovado | [1366, 1366] |
| S6C-014 | jovem 1366px: duplo clique cria UMA conta com os dados das 4 etapas | aprovado | [{"id": 82, "area_interesse": "Administrativo", "resumo_profissional": "Quero meu primeiro emprego.", "cidade": "São Paulo", "estado": "SP", "termos_versao": "1 |
| S6C-015 | jovem 1366px: aceite registrado (versão + data) | aprovado | — |
| S6C-016 | jovem 1366px: sem erros de JavaScript | aprovado | — |
| S6C-017 | jovem 375px: abre na etapa 1 de 4 (só ela visível, barra 1/4) | aprovado | {"visiveis": ["1"], "contador": "Etapa 1 de 4: Informações básicas", "barra": "1", "erros": [], "foco": ""} |
| S6C-018 | jovem 375px: "Continuar" com campos vazios não avança e mostra erros perto dos campos | aprovado | {"visiveis": ["1"], "contador": "Etapa 1 de 4: Informações básicas", "barra": "1", "erros": ["Informe seu nome completo.", "Preencha este campo.", "Informe sua  |
| S6C-019 | jovem 375px: e-mail inválido e senhas diferentes são apontados | aprovado | ["Digite um e-mail válido (ex.: [e-mail]).", "As senhas não coincidem."] |
| S6C-020 | jovem 375px: erro some ao corrigir o campo | aprovado | — |
| S6C-021 | jovem 375px: avança para a etapa 2 (Formação) e o foco vai para o título | aprovado | {"visiveis": ["2"], "contador": "Etapa 2 de 4: Formação", "barra": "2", "erros": [], "foco": "etapa2Titulo"} |
| S6C-022 | jovem 375px: formação obrigatória (nível) é exigida | aprovado | — |
| S6C-023 | jovem 375px: etapa 3 (perfil profissional) é opcional — dá para avançar vazia | aprovado | — |
| S6C-024 | jovem 375px: "Voltar" mostra a etapa 2 com os dados mantidos | aprovado | — |
| S6C-025 | jovem 375px: etapa 4 traz a revisão com os dados digitados | aprovado | Nome Jovem Etapas 375 E-mail [e-mail] Área de interesse Administrativo Objetivo profissional Quero meu primeiro emprego. Habilidades Excel, Comunicação, excel C |
| S6C-026 | jovem 375px: caixa de aceite não vem marcada | aprovado | — |
| S6C-027 | jovem 375px: sem aceite não envia e explica | aprovado | ["Para criar a conta, leia e aceite os Termos de Uso e a Política de Privacidade."] |
| S6C-028 | jovem 375px: link "Termos de Uso" abre o modal e não marca a caixa | aprovado | — |
| S6C-029 | jovem 375px: sem rolagem lateral | aprovado | [375, 375] |
| S6C-030 | jovem 375px: duplo clique cria UMA conta com os dados das 4 etapas | aprovado | [{"id": 83, "area_interesse": "Administrativo", "resumo_profissional": "Quero meu primeiro emprego.", "cidade": "São Paulo", "estado": "SP", "termos_versao": "1 |
| S6C-031 | jovem 375px: aceite registrado (versão + data) | aprovado | — |
| S6C-032 | jovem 375px: sem erros de JavaScript | aprovado | — |
| S6C-033 | empresa 1366px: 4 etapas, começa na 1 | aprovado | — |
| S6C-034 | empresa 1366px: CNPJ incompleto bloqueia o avanço | aprovado | — |
| S6C-035 | empresa 1366px: etapa 2 é o endereço principal | aprovado | — |
| S6C-036 | empresa 1366px: UF inválida é apontada | aprovado | — |
| S6C-037 | empresa 1366px: revisão mostra os dados | aprovado | CNPJ 13.665.414/0013-66 Empresa Empresa Etapas 1366 E-mail [e-mail] Rua Avenida Paulista Número 900 Bairro Bela Vista Cidade São Paul |
| S6C-038 | empresa 1366px: duplo clique cria UMA conta com endereço e perfil | aprovado | [{"id": 47, "logradouro": "Avenida Paulista", "numero": "900", "area_atuacao": "Varejo", "descricao": "Loja de demonstração.", "site_oficial": "www.exemplo.exam |
| S6C-039 | empresa 1366px: sem erros de JavaScript | aprovado | — |
| S6C-040 | empresa 375px: 4 etapas, começa na 1 | aprovado | — |
| S6C-041 | empresa 375px: CNPJ incompleto bloqueia o avanço | aprovado | — |
| S6C-042 | empresa 375px: etapa 2 é o endereço principal | aprovado | — |
| S6C-043 | empresa 375px: UF inválida é apontada | aprovado | — |
| S6C-044 | empresa 375px: revisão mostra os dados | aprovado | CNPJ 03.755.414/0003-75 Empresa Empresa Etapas 375 E-mail [e-mail] Rua Avenida Paulista Número 900 Bairro Bela Vista Cidade São Paulo  |
| S6C-045 | empresa 375px: duplo clique cria UMA conta com endereço e perfil | aprovado | [{"id": 48, "logradouro": "Avenida Paulista", "numero": "900", "area_atuacao": "Varejo", "descricao": "Loja de demonstração.", "site_oficial": "www.exemplo.exam |
| S6C-046 | empresa 375px: sem erros de JavaScript | aprovado | — |
| S6C-047 | erro do servidor (e-mail já usado): reabre na etapa 1 com a mensagem no campo e o resumo no topo | aprovado | {"visiveis": ["1"], "contador": "Etapa 1 de 4: Informações básicas", "barra": "1", "erros": ["E-mail ou CPF já cadastrado na plataforma. Se a conta é sua, faça  |
| S6C-048 | dados digitados voltam preenchidos (menos a senha) | aprovado | — |
| S6C-049 | sem JavaScript: as 4 etapas aparecem juntas e o cadastro é concluído (cidade/UF separadas no servidor) | aprovado | 4 [{'id': 84, 'cidade': 'Recife', 'estado': 'PE'}] http://localhost/JovemConnect/verificacoes/escolher_verificacao.php |
| S6C-050 | conta criada antes da Stage 6 continua entrando | aprovado | ../dashboard/jovem.php |

## S6P — Stage 6 — perfil do candidato e acesso da empresa

Arquivo de resultado: `tests6/perfil_s6_result.json` · 36/36 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S6P-001 | setup: P e Q candidatos da vaga A | aprovado | — |
| S6P-002 | A→P: 200 com nome, resumo e habilidades | aprovado | — |
| S6P-003 | A→P: contexto da candidatura (vaga, status, inscrição) | aprovado | — |
| S6P-004 | A→P: experiências visíveis e escapadas (sem &lt;script&gt; cru) | aprovado | — |
| S6P-005 | A→P: e-mail disponível (há candidatura) | aprovado | — |
| S6P-006 | A→P: sem CPF, CEP, telefone ou data de nascimento no HTML | aprovado | [] |
| S6P-007 | A→P: links para candidatos da vaga e compatibilidade | aprovado | — |
| S6P-008 | A→P: sem vazamento técnico | aprovado | — |
| S6P-009 | A→Q (privado, candidato): perfil liberado com contexto (única candidatura) | aprovado | — |
| S6P-010 | A→R (privado, sem candidatura): 404 "Perfil indisponível" | aprovado | — |
| S6P-011 | id inexistente: mesma resposta (não revela se existe) | aprovado | — |
| S6P-012 | acesso negado registrado em log (sem dados pessoais) | aprovado | [{"ts": "2026-10-10T17:44:34+00:00", "nivel": "aviso", "componente": "php", "evento": "acesso.negado", "req": "84de50ec1cec7dbc", "usuario": "empresa:53", "pagi |
| S6P-013 | A→S (público, sem candidatura): 200, sem e-mail e com aviso | aprovado | — |
| S6P-014 | B→P (público, sem candidatura à B): 200, sem e-mail e sem título da vaga da A | aprovado | — |
| S6P-015 | B→Q (privado, candidato só da A): 404 | aprovado | — |
| S6P-016 | A com ?vaga= de outra empresa: título da vaga da B não aparece | aprovado | — |
| S6P-017 | currículo: A→Q (candidato) entrega PDF | aprovado | — |
| S6P-018 | currículo: B→Q (sem relação) 404 | aprovado | — |
| S6P-019 | currículo: B→P (público) entrega PDF | aprovado | — |
| S6P-020 | currículo: sem login → redireciona para login | aprovado | — |
| S6P-021 | currículo: outro jovem → 404 | aprovado | — |
| S6P-022 | currículo: acesso direto à pasta continua bloqueado (.htaccess presente) | aprovado | — |
| S6P-023 | avaliar.php: A→Q (privado, candidato) liberado | aprovado | — |
| S6P-024 | avaliar.php: B→Q (sem relação) "Candidato não encontrado" | aprovado | — |
| S6P-025 | ajax análise: A→Q 200 | aprovado | 200 |
| S6P-026 | ajax análise: B→Q 404 | aprovado | 404 |
| S6P-027 | ajax nota: B→R (privado, sem relação) 404 | aprovado | 404 |
| S6P-028 | ajax nota: A→P continua funcionando | aprovado | {"score":80,"analise":"Excelente compatibilidade! Seu perfil est\u00e1 muito alinhado com os requisitos desta vaga. N\u0 |
| S6P-029 | painel da empresa: card do candidato tem "Ver perfil" com a vaga | aprovado | — |
| S6P-030 | lista de candidatos: "Ver Perfil" de Q (privado) agora abre | aprovado | — |
| S6P-031 | outro jovem → redireciona para o próprio perfil | aprovado | — |
| S6P-032 | dono continua vendo o próprio perfil completo (e-mail, CPF no formulário) | aprovado | — |
| S6P-033 | navegador 1366px: sem rolagem horizontal, 1 h1, seções com título, sem erro JS | aprovado | {"sx": 0, "h1": 1, "sec": 4} [] |
| S6P-034 | navegador 1366px: página "Perfil indisponível" com botão de volta | aprovado | — |
| S6P-035 | navegador 375px: sem rolagem horizontal, 1 h1, seções com título, sem erro JS | aprovado | {"sx": 0, "h1": 1, "sec": 4} [] |
| S6P-036 | navegador 375px: página "Perfil indisponível" com botão de volta | aprovado | — |

## S6A — Stage 6 — animações

Arquivo de resultado: `tests6/animacoes_s6_result.json` · 19/19 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S6A-001 | 1366px: 8 elementos com revelar, script ligado | aprovado | — |
| S6A-002 | 1366px: os que estão abaixo da dobra começam escondidos | aprovado | ['0', '0', '0', '0', '0', '0', '0', '0'] |
| S6A-003 | 1366px: ao rolar todos aparecem (opacity 1) | aprovado | ['1', '1', '1', '1', '1', '1', '1', '1'] |
| S6A-004 | 1366px: sem deslocamento de layout (CLS &lt; 0.05) | aprovado | 0.0000 → 0.0000 |
| S6A-005 | 1366px: sem rolagem horizontal durante a animação | aprovado | 0 |
| S6A-006 | 1366px: modal abre com animação curta | aprovado | jc-caixa-entrar |
| S6A-007 | 1366px: sem erros de JavaScript | aprovado | [] |
| S6A-008 | 375px: 8 elementos com revelar, script ligado | aprovado | — |
| S6A-009 | 375px: os que estão abaixo da dobra começam escondidos | aprovado | ['0', '0', '0', '0', '0', '0', '0', '0'] |
| S6A-010 | 375px: ao rolar todos aparecem (opacity 1) | aprovado | ['1', '1', '1', '1', '1', '1', '1', '1'] |
| S6A-011 | 375px: sem deslocamento de layout (CLS &lt; 0.05) | aprovado | 0.0000 → 0.0000 |
| S6A-012 | 375px: sem rolagem horizontal durante a animação | aprovado | 0 |
| S6A-013 | 375px: modal abre com animação curta | aprovado | jc-caixa-entrar |
| S6A-014 | 375px: sem erros de JavaScript | aprovado | [] |
| S6A-015 | reduzir movimento: nada fica escondido e o script não liga | aprovado | — |
| S6A-016 | reduzir movimento: animação do modal praticamente zerada | aprovado | 1e-05s |
| S6A-017 | reduzir movimento: troca de etapa sem animação perceptível | aprovado | 1e-05s |
| S6A-018 | sem JavaScript: todo o conteúdo visível | aprovado | — |
| S6A-019 | cadastro: etapa entra com jc-entrar (keyframes definidos) | aprovado | jc-entrar |

## S6T — Stage 6 — termos e privacidade

Arquivo de resultado: `tests6/termos_s6_result.json` · 38/38 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S6T-001 | documentos.php?doc=termos: 200, título, minuta, versão e data | aprovado | — |
| S6T-002 | documentos.php?doc=termos: resumo + texto completo, sem vazamento | aprovado | — |
| S6T-003 | documentos.php?doc=privacidade: 200, título, minuta, versão e data | aprovado | — |
| S6T-004 | documentos.php?doc=privacidade: resumo + texto completo, sem vazamento | aprovado | — |
| S6T-005 | documentos.php com parâmetro inválido: mostra Termos (sem refletir o valor) | aprovado | — |
| S6T-006 | privacidade menciona: ViaCEP | aprovado | — |
| S6T-007 | privacidade menciona: BrasilAPI | aprovado | — |
| S6T-008 | privacidade menciona: Twilio | aprovado | — |
| S6T-009 | privacidade menciona: Google Gemini | aprovado | — |
| S6T-010 | privacidade menciona: Google Fonts | aprovado | — |
| S6T-011 | privacidade menciona: hash | aprovado | — |
| S6T-012 | privacidade menciona: 30 dias | aprovado | — |
| S6T-013 | privacidade menciona: Excluir a conta | aprovado | — |
| S6T-014 | privacidade não promete criptografia de dados nem exclusão "definitiva" | aprovado | — |
| S6T-015 | 1366px: modal abre pelo teclado com foco no ×, resumo visível e texto completo recolhido | aprovado | {"vis": "flex", "completo_oculto": true, "foco": "jc-modal-fechar", "resumo": 5, "topo": 177.57852172851562, "nav": 64, "zModal": 2000} |
| S6T-016 | 1366px: cabeçalho do modal não fica sob a barra do site | aprovado | {"vis": "flex", "completo_oculto": true, "foco": "jc-modal-fechar", "resumo": 5, "topo": 177.57852172851562, "nav": 64, "zModal": 2000} |
| S6T-017 | 1366px: "Saiba mais" expande o texto completo (aria-expanded=true) | aprovado | {"aberto": true, "exp": "true", "h3": 10, "rot": "Mostrar só o resumo", "sx": 0} |
| S6T-018 | 1366px: Tab continua dentro do modal | aprovado | — |
| S6T-019 | 1366px: Esc fecha e o foco volta ao botão do rodapé | aprovado | — |
| S6T-020 | 1366px: Contato mostra o e-mail oficial com mailto | aprovado | — |
| S6T-021 | 1366px: Contato sem telefone/CNPJ/endereço inventados | aprovado | Contato  Fale com a equipe do JovemConnect pelo e-mail:  [e-mail]  Use o mesmo endereço para dúvidas sobre seus dados pessoais e privacidade. Para dúvidas rápid |
| S6T-022 | 1366px: sem erros de JavaScript | aprovado | [] |
| S6T-023 | 375px: modal abre pelo teclado com foco no ×, resumo visível e texto completo recolhido | aprovado | {"vis": "flex", "completo_oculto": true, "foco": "jc-modal-fechar", "resumo": 5, "topo": 25, "nav": 64, "zModal": 2000} |
| S6T-024 | 375px: cabeçalho do modal não fica sob a barra do site | aprovado | {"vis": "flex", "completo_oculto": true, "foco": "jc-modal-fechar", "resumo": 5, "topo": 25, "nav": 64, "zModal": 2000} |
| S6T-025 | 375px: "Saiba mais" expande o texto completo (aria-expanded=true) | aprovado | {"aberto": true, "exp": "true", "h3": 10, "rot": "Mostrar só o resumo", "sx": 0} |
| S6T-026 | 375px: Tab continua dentro do modal | aprovado | — |
| S6T-027 | 375px: Esc fecha e o foco volta ao botão do rodapé | aprovado | — |
| S6T-028 | 375px: Contato mostra o e-mail oficial com mailto | aprovado | — |
| S6T-029 | 375px: Contato sem telefone/CNPJ/endereço inventados | aprovado | Contato  Fale com a equipe do JovemConnect pelo e-mail:  [e-mail]  Use o mesmo endereço para dúvidas sobre seus dados pessoais e privacidade. Para dúvidas rápid |
| S6T-030 | 375px: sem erros de JavaScript | aprovado | [] |
| S6T-031 | cadastro: caixa de aceite começa desmarcada | aprovado | — |
| S6T-032 | cadastro: um único conjunto de modais na página | aprovado | — |
| S6T-033 | cadastro: link dos Termos abre o modal na mesma página, sem marcar a caixa | aprovado | — |
| S6T-034 | cadastro sem JS: link leva à página da Política de Privacidade | aprovado | — |
| S6T-035 | aceite gravado no cadastro do jovem (versão + data) | aprovado | — |
| S6T-036 | aceite gravado no cadastro da empresa (versão + data) | aprovado | — |
| S6T-037 | sem marcar o aceite: conta não é criada e o erro aparece | aprovado | — |
| S6T-038 | contas antigas continuam com aceite vazio (sem preenchimento retroativo) | aprovado | 22 |

## S6S — Stage 6 — dados de demonstração (banco da réplica)

Arquivo de resultado: `tests6/seed_s6_result.json` · 12/12 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S6S-001 | login demo jovem joao com a senha documentada | aprovado | ../dashboard/jovem.php |
| S6S-002 | jovem demo vê vagas de várias regiões | aprovado | — |
| S6S-003 | jovem demo vê a própria candidatura | aprovado | — |
| S6S-004 | login demo empresa planalto com a senha documentada | aprovado | ../dashboard/empresa.php |
| S6S-005 | empresa demo vê o candidato da vaga (local de trabalho específico) | aprovado | — |
| S6S-006 | vaga em local específico ligada ao local da empresa | aprovado | — |
| S6S-007 | login demo jovem isabela com a senha documentada | aprovado | ../dashboard/jovem.php |
| S6S-008 | login demo empresa pantanal com a senha documentada | aprovado | ../dashboard/empresa.php |
| S6S-009 | perfil privado + candidatura: a empresa da vaga vê o perfil | aprovado | — |
| S6S-010 | perfil privado: outra empresa demo não vê | aprovado | — |
| S6S-011 | 15 empresas em 15 UFs (todas as regiões) | aprovado | 15 |
| S6S-012 | senhas demo são hash bcrypt (nenhuma senha em texto) | aprovado | — |

## S6I — Stage 6 — assistente

Arquivo de resultado: `tests6/assistente_s6_result.json` · 34/34 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S6I-001 | Flask /ping informa assistente configurado (sem expor a chave) | aprovado | — |
| S6I-002 | página traz endpoint, tipo de página e token no widget | aprovado | — |
| S6I-003 | IA responde (fonte ia) com texto sem markdown | aprovado | {"fonte": "ia", "resposta": "Para se candidatar, entre em Vagas, abra a vaga e clique em \"Candidatar-se\".\n• Confira seu perfil antes.", "mascarado": true} |
| S6I-004 | aviso de dados pessoais removidos | aprovado | — |
| S6I-005 | ao Gemini: e-mail, CPF e telefone já mascarados | aprovado | — |
| S6I-006 | ao Gemini: instruções de identidade/limites + histórico + contexto da página | aprovado | — |
| S6I-007 | chave enviada só no cabeçalho x-goog-api-key (não na URL) | aprovado | — |
| S6I-008 | jovem logado: contexto "jovem" sem nome, e-mail ou CPF | aprovado | — |
| S6I-009 | sem token CSRF → 403 | aprovado | — |
| S6I-010 | GET → 405 | aprovado | — |
| S6I-011 | mensagem vazia → 400 | aprovado | — |
| S6I-012 | mensagem longa demais → 400 | aprovado | — |
| S6I-013 | JSON quebrado → 400 | aprovado | — |
| S6I-014 | limite do Gemini (429): fonte local (navegador usa resposta pronta), sem detalhe técnico | aprovado | 200 {"fonte":"local","mascarado":false} 0.0s |
| S6I-015 | erro 500 do Gemini: fonte local (navegador usa resposta pronta), sem detalhe técnico | aprovado | 200 {"fonte":"local","mascarado":false} 0.0s |
| S6I-016 | chave inválida: fonte local (navegador usa resposta pronta), sem detalhe técnico | aprovado | 200 {"fonte":"local","mascarado":false} 0.0s |
| S6I-017 | tempo esgotado: fonte local (navegador usa resposta pronta), sem detalhe técnico | aprovado | 200 {"fonte":"local","mascarado":false} 3.0s |
| S6I-018 | resposta quebrada: fonte local (navegador usa resposta pronta), sem detalhe técnico | aprovado | 200 {"fonte":"local","mascarado":false} 0.0s |
| S6I-019 | resposta vazia: fonte local (navegador usa resposta pronta), sem detalhe técnico | aprovado | 200 {"fonte":"local","mascarado":false} 0.0s |
| S6I-020 | limite por sessão: a 9ª mensagem no mesmo minuto recebe 429 | aprovado | [200, 200, 200, 200, 200, 200, 200, 200, 429] |
| S6I-021 | mensagem do limite é amigável | aprovado | — |
| S6I-022 | log assistente.resposta com fonte/tempo, sem o texto da conversa | aprovado | — |
| S6I-023 | limite registrado em seguranca.log | aprovado | — |
| S6I-024 | 1366px: carregando ("Digitando…", botão desativado) e aviso de IA visível | aprovado | {"dig": true, "dis": true, "aviso": "\n        \n        Assistente virtual com IA: pode cometer erros e não acessa sua conta. Não compartilhe dados pessoais.\n |
| S6I-025 | 1366px: IA lenta → resposta pronta + nota de indisponibilidade | aprovado | Para se candidatar, entre como jovem, abra “Vagas”, escolha a vaga e use “Candidatar-se”. Você acompanha o andamento em “Minhas candidaturas”. 📨 Resposta automá |
| S6I-026 | 1366px: sem erros de JavaScript | aprovado | [] |
| S6I-027 | 375px: resposta da IA aparece como texto | aprovado | Para se candidatar, entre em Vagas, abra a vaga e clique em "Candidatar-se". • Confira seu perfil antes. |
| S6I-028 | 375px: HTML/script vindo da IA é mostrado como texto (não executa) | aprovado | {"xss": 0, "img": 0, "txt": "&lt;img src=x onerror=\"window.xss=1\"&gt; &lt;script&gt;window.xss=2&lt;/script&gt; Texto final", "n": 5} |
| S6I-029 | 375px: histórico da conversa continua no painel | aprovado | 5 |
| S6I-030 | 375px: sem rolagem horizontal com o chat aberto | aprovado | 0 |
| S6I-031 | 375px: sem erros de JavaScript | aprovado | [] |
| S6I-032 | sem GEMINI_API_KEY: fonte local e nenhuma chamada ao Gemini | aprovado | — |
| S6I-033 | sem chave: mensagens que não chegam ao Gemini não contam no limite (12 seguidas, todas 200) | aprovado | [200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200] |
| S6I-034 | Flask fora do ar: fonte local, resposta rápida | aprovado | — |

## S6L — Stage 6 — logs

Arquivo de resultado: `tests6/logs_s6_result.json` · 22/22 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| S6L-001 | todas as linhas novas são JSON válido (uma por evento) | aprovado | 9 linhas |
| S6L-002 | campos padrão: ts, nivel, componente, evento, req | aprovado | — |
| S6L-003 | evento registrado: login.falha | aprovado | — |
| S6L-004 | evento registrado: cadastro.concluido | aprovado | — |
| S6L-005 | evento registrado: vaga.criada | aprovado | — |
| S6L-006 | evento registrado: candidatura.criada | aprovado | — |
| S6L-007 | evento registrado: match.calculado | aprovado | — |
| S6L-008 | evento registrado: csrf.recusado | aprovado | — |
| S6L-009 | evento registrado: flask.requisicao | aprovado | — |
| S6L-010 | injeção: o texto com quebra de linha não criou evento falso | aprovado | — |
| S6L-011 | nenhum dado proibido nos logs (senha, e-mail, CPF, CNPJ, token, chave) | aprovado | [] |
| S6L-012 | IP anonimizado nos eventos de segurança | aprovado | {'127.0.0.0'} |
| S6L-013 | correlação: mesmo identificador de requisição no PHP e no Flask (/avaliar) | aprovado | {'ea4742f1b82d5475'} x {'ea4742f1b82d5475'} |
| S6L-014 | Flask: X-Request-Id inválido é trocado por um novo | aprovado | — |
| S6L-015 | pasta de logs protegida (logs/.htaccess nega acesso) | aprovado | — |
| S6L-016 | logs não ficam no Git (*.log no .gitignore) | aprovado | — |
| S6L-017 | campo proibido vira [removido] e texto longo é cortado | aprovado | {"ts":"2026-10-10T13:49:38+00:00","nivel":"info","componente":"php","evento":"teste.evento","req":"6a03358ff1c8a78f","usuario":"visitante","pagina":"/tmp/jclogm |
| S6L-018 | rotação diária: arquivo de outro dia vira app-AAAAMMDD-HHMMSS.log | aprovado | — |
| S6L-019 | rotação por tamanho (JC_LOG_MAX_MB) | aprovado | — |
| S6L-020 | retenção: arquivo com mais de JC_LOG_DIAS (30) dias é apagado | aprovado | — |
| S6L-021 | pasta de log sem permissão/inexistente: o código segue (sem erro fatal) | aprovado | okok[JovemConnect] não foi possível gravar o log em app.log [JovemConnect] não foi possível gravar o log em app.log [JovemConnect] não foi possível gravar o log |
| S6L-022 | Flask com pasta de log impossível continua respondendo | aprovado | — |

## DOC — Auditoria de documentação (10/10/2026)

Arquivo de resultado: `../doc/testes/t_doc_result.json` · 6/7 aprovados.

| ID | Caso | Status | Observado |
|---|---|---|---|
| DOC-001 | SEG-01 recuperação de senha: atacante NÃO consegue trocar a senha da vítima (esperado seguro) | **reprovado** | redirecionamento do login da vítima com a senha do atacante: ../dashboard/jovem.php |
| DOC-002 | MATCH-01 interesse "TI" × vaga "Administrativo" ganha 13 pts de área (substring "ti") | aprovado | ["A vaga está localizada na sua cidade", "Área da vaga tem relação com seu interesse", "Você está no perfil ideal para Jovem Aprendiz (EM em curso)", "Contrato  |
| DOC-003 | MATCH-02 "Santo André" × "Santos" é tratada como "cidade próxima" (12 pts) | aprovado | ["A vaga está em uma cidade próxima da sua", "Área de interesse (Ti) compatível com a vaga", "Você está no perfil ideal para Jovem Aprendiz (EM em curso)", "Con |
| DOC-004 | MATCH-03 teto: Jovem Aprendiz 100, Estágio 95, CLT 85 | aprovado | 100/95/85 |
| DOC-005 | MATCH-04 "Estagio" sem acento perde os 10 pts de contrato | aprovado | Estágio=70 Estagio=60 |
| DOC-006 | MATCH-05 descrição com "concluído" + jovem cursando aplica −10 | aprovado | sem=75 com=65 |
| DOC-007 | IA-01 /ping não devolve a chave | aprovado | {"analise_contextual":false,"assistente":false,"gemini":false,"modelo_contextual":"gemini-3.5-flash-lite","status":"ok","versao":"3.00"}  |

## V456 — Versões 4.55 e 4.56

Executados em 26/10/2026 na réplica Linux (PHP embutido, banco compatível com MySQL, Flask real, Gemini simulado
e navegador automatizado). Resultados reais; os scripts `.bat` e o código exclusivo do Windows não podem ser
executados nesse ambiente.

| ID | Mudança | Resultado | Observação |
|---|---|---|---|
| V456-001 | Tema claro off-white (`style.css` e `admin.css`) | 119 verificações; 117 na primeira rodada | as 2 falhas eram do próprio teste (um ícone `currentColor` contado como superfície e uma página medida antes de carregar); corrigido o teste, reexecutado sem falhas. Tema escuro e alto contraste idênticos aos de antes |
| V456-002 | Card do Match centralizado (`Vagas/avaliar.php`) | sem teste, a pedido | mudança de uma linha de estilo (`max-width:720px; margin-inline:auto`) |
| V456-003 | Painel do jovem: 6 vagas mais recentes + "Ver todas as vagas" | coberto por V456-004 e pela regressão | — |
| V456-004 | Card de vaga padrão com estrela (`t_cards.py`) | **32/33** | estrela com o estado do banco, favoritar sem navegar, cliques rápidos sem duplicar, erro 500 e queda de conexão sem fingir sucesso, teclado, botão de IA sem navegar, card abre a vaga certa, filtros, `favoritos.php`, celular sem rolagem horizontal, permissões (401, 403, outra conta, empresa, vaga inválida). A única falha foi o registro no console dos erros simulados pelo próprio teste; num teste separado, em uso normal, o console ficou sem erros |
| V456-005 | Verificação do ambiente da IA (`IA/verificar_ambiente.py`) | **12/12 cenários** | `venv` válido com espaço e parênteses no caminho; `venv` de outra pasta, Python global e `venv` sem Flask recusados; criação do `venv`; ping (IA no ar, porta livre, outro programa); espera; data do backup; log sem dados sensíveis |
| V456-006 | Porta da IA com alternativa (`t_portas.py`) | **37/37** | 5000 livre; outro programa na 5000 (IA na 5001 e o site usa a 5001); IA morta e arquivo velho (PHP não fala com outro programa); faixa toda ocupada (código 4); duas IAs ao mesmo tempo (só uma fica); arquivo adulterado ignorado; `JC_IA_PORTA` personalizado; sem IA, "indisponível" |
| V456-007 | Regressão completa com a IA forçada para a 5001 | todas as suítes no padrão | só as 7 falhas que já existiam antes (3 na formação original, 4 no cadastro) |
| V456-008 | Regressão completa com a IA na 5000 | todas as suítes no padrão | as mesmas 7 falhas preexistentes |
| V456-009 | Ajudante dos scripts (`scripts/jovemconnect.php`) | **26/26** | `checar`; `criar-env` nunca sobrescreve; portas; Apache identificado pelo cabeçalho; banco de teste: instalar (20 tabelas), recusar importar por cima, seed sem duplicar, apagar só com o nome certo, recusar banco do sistema; banco real com dados intocado; MySQL inacessível sem mostrar senha; `ia-parar` fecha só a IA desta pasta e não encerra outro programa; 404 pelo navegador |

**Pendentes no Windows e na escola:** executar `iniciar_ia.bat` (primeira vez, reaproveitamento, porta 5000
ocupada, janela fechada, dois cliques seguidos), `iniciar_jovemconnect.bat` e `parar_jovemconnect.bat`, e guardar
`logs\iniciar_ia.log`, `logs\ia_porta.json` e `logs\iniciar_jovemconnect.log`. Também não foram testados aqui
`SO_EXCLUSIVEADDRUSE`, `msvcrt.locking` e os scripts de parada do XAMPP.

## V458 — PHP 7.4 e 8.0 e exclusão da pasta

Executados em 27/10/2026 na réplica Linux (PHP 8.3.6). Não havia PHP 7.4 nem 8.0 disponível para rodar o site
nessas versões, e os `.bat` só rodam no Windows; o que dependia disso foi verificado por análise do código.

| ID | Verificação | Resultado | Observação |
|---|---|---|---|
| V458-001 | `php -l` em todos os arquivos PHP | **91/91** sem erro | — |
| V458-002 | Varredura de recursos exclusivos do PHP 8.0+ (tokenizer do PHP; o detector foi testado antes num arquivo com cada recurso) | nenhum restante | só sobram `str_contains`/`str_starts_with`, cobertas por `includes/compat.php`, e o retorno `never`, que não dá erro no 7.4/8.0. Funções e constantes nativas usadas: todas existem no 7.4 |
| V458-003 | `includes/compat.php` × funções nativas do PHP 8.3 | **36/36** iguais | vazios, `null`, números, acentos, maiúsculas |
| V458-004 | Regressão completa no PHP 8.3 (com `mysqli_report` explícito, sem `match`, com `compat.php`) | todas as suítes no padrão | só as 7 falhas que já existiam (3 na formação original, 4 no cadastro); Central administrativa (onde estavam os `match`): 51, 7, 13, 48, 39, 74 e 46, todas sem falhas |
| V458-005 | Ajudante dos scripts (`t_scripts.py`) | **26/26** | `checar` mostra "PHP 8.3.6 (ok)" |
| V458-006 | `.bat` (`iniciar_jovemconnect`, `parar_jovemconnect`, `iniciar_ia`, `scripts/excluir_pasta`) | revisão estática | ASCII, CRLF, todos os rótulos de `goto`/`call` existem |

**Pendente no Windows / na escola:** rodar o `iniciar_jovemconnect.bat` com o PHP da escola e responder **S** ao
aviso; navegar pelo site (cadastro, vagas, favoritos, Match, Central administrativa); rodar o
`parar_jovemconnect.bat` até a exclusão da pasta e, se algo sobrar, guardar `%TEMP%\jovemconnect_limpeza.log`.

## V459 — Linux

Executados em 28/10/2026 na réplica Linux (PHP 8.3.6, Python 3.13), **sem** os atalhos `Assets → assets` e
`Candidaturas → candidaturas` que a réplica usava antes. Com isso, ela diferencia maiúsculas como um servidor Linux.
Não havia XAMPP para Linux nem systemd na réplica: o modo XAMPP foi testado com um `lampp` falso que registra os
comandos, e o modo systemd só por revisão.

| ID | Verificação | Resultado | Observação |
|---|---|---|---|
| V459-001 | Varredura de todas as referências a arquivos e pastas do projeto contra a grafia do disco | 22 referências corrigidas (`Assets/` → `assets/`, em 21 arquivos) | sobram 9 ocorrências que não são caminhos: comentários e chaves comparadas em minúsculas (`includes/assistente.php`) |
| V459-002 | Páginas de visitante, jovem e empresa num servidor que diferencia maiúsculas (`t_paginas_linux.py`) | **6/6** | 44 páginas visitadas, nenhuma com 404/500; os 13 arquivos de CSS/JS/imagem referenciados respondem 200; `Assets/css/style.css` dá 404 (prova de que o servidor diferencia) |
| V459-003 | Regressão completa no servidor que diferencia maiúsculas | todas as suítes no padrão | só as 7 falhas que já existiam (3 na formação original, 4 no cadastro); Central administrativa 51, 7, 13, 48, 39, 74 e 46 sem falhas |
| V459-004 | Scripts Linux (`t_linux_scripts.py`) | **34/34** | LF e sintaxe; iniciar com tudo no ar (6 etapas, reaproveita a IA); checagem que não bate com N (para) e S (continua); IA desligada → sobe em segundo plano, continua viva depois do script, com a pasta de trabalho fora do projeto e saída em `logs/ia_console.log`; parar com N a tudo (só fecha a IA); venv ausente + N; venv do Windows (avisa, guarda como `venv.antigo-<data>`, cria um venv Linux; sem internet, para com mensagem clara); XAMPP falso (`startapache`, `startmysql`, `stopapache`, `stopmysql`); modo manual; banco de teste apagado só com o nome certo (maiúsculas tanto faz) e o banco do site intacto; pasta excluída só com o nome certo, com aviso do Git e só dentro de `htdocs`/`/var/www`; `quem-usa` e `protecao` no Linux; sem senha ou chave na saída e no log |
| V459-005 | Ajudante dos scripts (`t_scripts.py`) | **26/26** | o comportamento usado pelos `.bat` não mudou |

**Encontrado e corrigido durante os testes:** o `iniciar_ia.sh` deixava um processo `bash` preso esperando a IA,
o que travava quem lia a saída do script. Agora a IA é lançada com `exec`, sem nada preso ao terminal. As perguntas
S/N passaram a aparecer também quando a entrada não é um terminal.

**Pendente num Linux de verdade:** XAMPP para Linux (`/opt/lampp`) e Apache/MariaDB do sistema (systemd, `sudo`,
usuário do Apache gravando em `logs/`, `AllowOverride` para os `.htaccess`) e a abertura do navegador com `xdg-open`.

**Correção (v4.60, PI-12):** os testes acima usaram uma **cópia da pasta do PC**, não um clone do Git. O índice do
Git ainda registrava `Candidaturas/minhas.php` (C maiúsculo); num clone feito no Linux a pasta nascia
`Candidaturas/` e "Minhas candidaturas" dava 404. A grafia foi corrigida no índice na v4.60 e o clone foi testado
(V460-013).

## V460 — correção de segurança e propriedade intelectual

Executados em 29/10/2026 na réplica Linux (PHP 8.3.6, Python 3.13, Chromium via Playwright), **sem banco de dados,
sem Apache e sem Windows**. O que depende deles está na tabela de pendentes, com o roteiro para rodar no PC.

| ID | Verificação | Resultado | Observação |
|---|---|---|---|
| V460-001 | `php -l` em todos os `.php` | **93/93** | — |
| V460-002 | Compatibilidade com PHP 7.4 (verificador heurístico: funções e sintaxe do 8.0+) | **0 ocorrências** | `includes/compat.php` cobre `str_contains`/`str_starts_with`/`str_ends_with` |
| V460-003 | Python (`IA/recomendar.py`, `IA/verificar_ambiente.py`) compila | **2/2** | — |
| V460-004 | Sintaxe dos scripts `sh` (4 `.sh` e o gancho `pre-commit`) | **5/5** | — |
| V460-005 | Regras do `.htaccess` da raiz simuladas (mesmas expressões, PCRE) sobre 197 caminhos reais, 42 variantes de nome e 14 páginas legítimas, em dois modelos (Apache resolve ou não o nome verdadeiro do Windows) | **0 falhas** | com as regras da primeira passagem, 9 variantes passavam (`.git./HEAD`, `.git%20/HEAD`, `GIT~1/HEAD`, `.git::$INDEX_ALLOCATION/HEAD`, `ENV~1`...): corrigido na segunda passagem. Não substitui o teste no Apache |
| V460-006 | Limite de tentativas (`includes/tentativas.php`), unidade | **17/17** | bloqueio no 5º erro, janela deslizante, chave sem e-mail/IP em claro, intervalo e teto de envios, IP só de `REMOTE_ADDR`, faxina, sem pasta = sem bloqueio |
| V460-007 | HTTP no servidor embutido: cabeçalhos em 5 páginas, erros escondidos por padrão e visíveis com `JC_EXIBIR_ERROS=1`, logout, cookie `HttpOnly`/`SameSite=Lax` | **41/41** | — |
| V460-008 | `jc_sessao_entrar()` (identidade única, token CSRF novo, impressão da senha) | **7/7** | — |
| V460-009 | Flask: `Host` local aceito (127.0.0.1, localhost, `[::1]`), externo e disfarçado recusados (403), sem cabeçalho CORS, corpo acima de 512 KB recusado (413) | **12/12** | — |
| V460-010 | IA Advisor no Chromium, código antigo × novo, com resposta simulada contendo HTML e `<script>` | **7/7** | antes: a marcação virava elemento; depois: aparece como texto, com a mesma estrutura e estilo |
| V460-011 | Contas de demonstração com e sem `JC_AMBIENTE=producao`; `banco-seed` em produção | **6/6**; código **3** | — |
| V460-012 | `regressao_isolada.php` sem MySQL | para com código **2** | nada é criado |
| V460-013 | Git sintético com o índice do PC (156 arquivos) + comandos do `git_correcao_seguranca.bat` + gancho `pre-commit` + clone no Linux | **23/23** | `geminiAPI.env` e `repomix-output.xml` saem do índice e ficam na pasta; grafia vira renomeação (R100); gancho recusa `.env`, `*.env` e texto com formato de chave (sem mostrar o valor) e aceita `.env.example`; no clone, `/candidaturas/minhas.php` → 302 e `/Candidaturas/minhas.php` → 404 |
| V460-014 | `php scripts/jovemconnect.php protecao` contra um servidor que **ignora** o `.htaccess` (servidor embutido) | detecta **20** problemas; site 7/7; aviso de `.git` na raiz do `htdocs` | prova que a verificação acusa a exposição (num Apache com o `.htaccess` valendo, o esperado é 0 problemas) |
| V460-015 | Varredura redigida dos arquivos atuais (13 padrões) | **0 credenciais** | ocorrências esperadas: 30 hashes das contas de demonstração (senha pública), 2 marcadores do `.env.example`, 2 CPFs fictícios em documentação, e-mails de contato institucional (2), do autor no `composer.json` (1) e de exemplo `rh@empresa.com.br` (6) |
| V460-016 | Links locais dos documentos (arquivos e âncoras) | **0 quebrados** | — |
| V460-017 | Diagramas Mermaid alterados (login e diagrama ER) renderizam | **2/2** | — |

**Pendentes (exigem o XAMPP, o MySQL ou o Windows do PC):**

| O quê | Como |
|---|---|
| Bloqueio real no Apache (inclusive apelidos e nomes 8.3 reais), cabeçalhos, pastas vizinhas, alcance pela rede | `scripts\seguranca\verificar_protecao.bat` e `verificar_protecao.bat -Rede` |
| Login, limite, códigos, sessões, cadastro privado, currículo com nome aleatório, demonstração, erros do PHP, com banco real de teste | `scripts\seguranca\regressao_isolada.bat` (cerca de 70 verificações: HDR, PUB, PRIV, LOGIN, JOVEM, EMPRESA, IDOR, CAND, CAD, LIM, SESS, IDENT, CV, COD, SAIR, DEMO, PHP) |
| Configuração do XAMPP, rede, firewall, Git e histórico | `scripts\seguranca\diagnostico_somente_leitura.bat` |
| Gravação no Git (índice, grafia, commit, tag, envio) | `scripts\seguranca\git_correcao_seguranca.bat` |
| Sintaxe dos `.ps1` e `.bat` novos | não há PowerShell nem `cmd` na réplica: revisão manual; rodam no PC |

## V461 — Paginação da lista de vagas

Executado em 31/10/2026 na réplica Linux (PHP 8.3.6, Flask, banco compatível com MySQL), sobre a base da v4.59 com
só o `Vagas/listar.php` paginado — a correção de segurança da v4.60 foi validada à parte (seção V460). Motivado pelo
teste de estresse, que mostrou a lista crescendo sem limite (~108 KB com 18 vagas → ~7,5 MB com 2.000). A mudança é
só em `Vagas/listar.php` (modo listagem); o detalhe `?id=` e o resto não mudaram.

| ID | Verificação | Resultado |
|---|---|---|
| V461-001 | Paginação (`t_paginacao.py`): fatiamento 20/20/5 em 45 vagas; sem sobreposição; faixa "X–Y de N"; "Página N de M"; Anterior/Próxima nas pontas certas | **15/15** |
| V461-002 | Limites: `?pagina=9999` cai na última; `?pagina=0`, `-5` e `abc` caem na primeira; sem `?pagina` = página 1 | passou |
| V461-003 | Filtros preservados: `?area=TI` reduz o total e os links de página mantêm `area=TI`; link "Próxima" aponta para `pagina=2` | passou |
| V461-004 | Payload limitado: com 205 vagas a página 1 ainda traz 20 cards e o tamanho fica ~constante vs 45 vagas (não cresce com o total) | passou |
| V461-005 | Lista vazia: 0 cards, sem navegação, estado "Nenhuma vaga encontrada" | passou |
| V461-006 | Cards (`t_cards.py`, comportamento dos cards com a lista paginada) | **32/33** (a única falha é o registro dos erros simulados pelo próprio teste, como nas versões anteriores) |
| V461-007 | Regressão completa (21 suítes) com a lista paginada | 21/21 suítes no padrão; só as 7 falhas pré-existentes da v4.59 (3 em formação original, 4 em cadastro), nenhuma ligada à paginação; 0 tracebacks |

**Medição (réplica):** cada página passou a ~117 KB (20 cards) / ~99 KB (15 cards), **constante** com o total,
contra ~204 KB (55 vagas) e ~7,5 MB (2.000 vagas) da versão sem paginação. A latência por página na réplica
(~0,33 s) é quase toda o custo fixo do Dolt na consulta a `information_schema` (ver o teste de estresse, L-02),
que **não** existe no MariaDB; o que importa é que a latência e o tamanho **não crescem** com o número de vagas.

## V462 — Logs organizados em subpastas

Réplica Linux, 01/11/2026 (PHP 8.3, Python 3.13 com Flask 3.1.3), sem banco nem Apache.

| ID | Teste | Resultado |
|---|---|---|
| V462-001 | `jc_log()` e `jc_log_seguranca()` com `JC_LOG_DIR` temporário: gravam em `site/app.log` e `seguranca/seguranca.log`; nada na raiz; campo `email` continua `[removido]` e IP anonimizado | passou |
| V462-002 | Leitor da Central (`jc_admin_eventos_seguranca`) lê o evento na subpasta nova | passou (1 evento) |
| V462-003 | `organizar-logs` com 15 arquivos de exemplo na raiz: 12 movidos para `site/`, `seguranca/`, `ia/`, `sistema/` e `testes/`; `ia_porta.json`, `ia.lock` e um arquivo desconhecido ficam; `app.log` já existente na subpasta **não** é sobrescrito (o antigo vira `app-anterior-…`) | passou |
| V462-004 | Flask (`IA/recomendar.py` importado com `JC_LOG_DIR` temporário): `log_ia()` grava em `ia/ia.log`; `/ping` 200 | passou |
| V462-005 | `php -l` e checagem de compatibilidade PHP 7.4 nos PHP alterados; `bash -n` nos `.sh`; `py_compile` | passou |
| V462-006 | Links da documentação | sem links novos quebrados |

**No PC (pendente):** rodar o `iniciar_jovemconnect.bat` (move os logs antigos), conferir as subpastas em `logs\`,
entrar no site e ver os eventos em **Central → Segurança**, e rodar o `verificar_protecao.bat` (relatório em
`logs\testes\`).
