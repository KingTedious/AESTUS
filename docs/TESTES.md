# Testes

> Auditoria de 10/10/2026, versão **v4.00** (resultados da Stage 7, de 11/10/2026, na seção
> [Stage 7](#stage-7-11102026)). Status possíveis: **aprovado**, **reprovado**, **bloqueado**,
> **não executado**. Só recebe "aprovado"/"reprovado" o caso que foi executado e tem evidência em
> [TESTES_RESULTADOS.md](TESTES_RESULTADOS.md) (962 verificações automáticas: 959 aprovadas, 3 reprovadas).

## Situação dos testes no projeto

- **O repositório não contém testes automatizados de unidade** (nenhum PHPUnit, pytest ou CI) [C]. Desde a v4.60
  há três roteiros de verificação versionados em `scripts/seguranca/`, para rodar no próprio XAMPP (seção
  [v4.60](#v460--verificações-no-próprio-xampp)).
- Os testes abaixo rodaram numa **réplica de desenvolvimento** (Linux, PHP 8.3 com servidor embutido, Flask
  3.1, banco compatível com MySQL, Chromium via Playwright, **Gemini simulado** com chave falsa). Os scripts
  ficam na réplica e não foram copiados para o projeto.
- Contas de teste foram criadas pelos próprios testes, com dados fictícios, só no banco da réplica. Nenhum
  banco real, chave real ou serviço externo foi usado.
- O único roteiro manual versionado é o de [ASSISTENTE_E_IA.md](ASSISTENTE_E_IA.md#roteiro-de-testes-manuais).

## Testes automatizados da réplica

| Suíte | O que cobre | Resultado |
|---|---|---|
| S3R / S3B / S3N | regressão funcional HTTP e no navegador: cadastro, login, perfis, vagas, candidaturas, favoritos, upload, CSRF, permissões, vazamento de erros | 155/155 · 47/47 · 33/33 |
| S4N / S4I | navegação entre páginas e componentes de interface | 68/68 · 44/44 |
| S5M / S5F / S5U / S5V | modais, formação estruturada, identidade visual | 65/65 · 46/46 · 21/21 · 25/27 |
| S51M / S51U / S51C / S51F / S51K | locais de trabalho, Match, página de análise, análise contextual, Flask fora, sem chave | 90/90 · 101/101 · 33/33 · 7/7 · 7/7 |
| S6C / S6P / S6A / S6T / S6S / S6I / S6L | cadastro em etapas, acesso ao candidato, animações, termos, seed, assistente, logs | 50/50 · 36/36 · 19/19 · 38/38 · 12/12 · 34/34 · 22/22 |
| DOC | auditoria de documentação: recuperação de senha, regras do Match, `/ping` | 6/7 (DOC-001 reprovado = falha SEG-01) |
| Paridade Python × PHP | 1.205 entradas comparadas | 0 divergências |
| Links | 81 URLs (visitante, jovem, empresa) | 0 quebrados |
| Responsivo | 78 medições (375–1366 px, zoom 140%) | 0 problemas |
| Acessibilidade | contraste, rótulos, foco, `aria-hidden`, idioma, movimento reduzido | 0 problemas novos |
| Reimportação do `schema.sql` (banco de rascunho) | comportamento do script sobre banco existente | 22 comandos falharam (SEG-11) |

As duas reprovações de S5V (S5V-010/011) medem a posição da foto da página inicial e ficaram desatualizadas
depois do ajuste de tamanho da imagem pedido na Stage 6; o mesmo resultado (25/27) ocorre na v3.48.

### Stage 7 (11/10/2026)

Bateria completa na réplica com o código da Stage 7, em duas fases: **sem** a migração da Stage 7 (banco igual ao
da v4.00) e **com** ela (aplicada como no phpMyAdmin: 8 comandos, 0 falhas). Duração: 36 min. Nenhum seed foi
executado. Detalhes de cada bloco em [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md#testes).

| Suíte | O que cobre | Sem a migração | Com a migração |
|---|---|---|---|
| S7-F0 | site sem a migração = v4.00; Central e linha de comando avisam e não criam nada | 25/25 | — |
| S7-A | provisionamento pela linha de comando, login com autenticador, limite de tentativas, sessão, CSRF, reautenticação, permissões | — | 51/51 |
| S7-UI | Central no navegador (teclado, foco, 375 px, sem erro de JavaScript) | — | 13/13 |
| S7-B | contas: busca, privacidade da ficha, correção, suspensão/reativação e efeitos | — | 48/48 |
| S7-C | vagas: estados de moderação, local preservado, fila, denúncias, IA na ficha | — | 39/39 |
| S7-D | validação e moderação: 40 exemplos nas funções, formulários reais, IA simulada (fora, sem chave, válida, manipulada, inválida, limite, cache, perfil) | — | 74/74 |
| S7-E | auditoria: registros completos, espelho no log, sem segredos, adulterações detectadas, atomicidade, sem tela de exclusão | — | 46/46 |
| S7-S | seed analisado sem executar: colunas existem; 346 textos sem bloqueio/sinalização | — | 5/5 |
| S3R / S3B / S3N | regressão funcional | 155/155 · 47/47 · 33/33 | 155/155 · 47/47 · 33/33 |
| S4N / S4I | navegação e interface | 68/68 · 44/44 | 68/68 · 44/44 |
| S5M / S5F / S5U / S5V | modais, formação, formação no navegador, identidade | 65/65 · 47/47 · 21/21 · 25/27 | 65/65 · 47/47 · 21/21 · 25/27 |
| S51M / S51U / S51C / S51F / S51K | Match e locais, interface, contexto, Flask fora, sem chave | 90/90 · 101/101 · 33/33 · 7/7 · 7/7 | 90/90 · 101/101 · 33/33 · 7/7 · 7/7 |
| S6C / S6P / S6A / S6T / S6I / S6L | cadastro, perfil do candidato, animações, termos, assistente, logs | 50/50 · 36/36 · 19/19 · 38/38 · 34/34 · 22/22 | 50/50 · 36/36 · 19/19 · 38/38 · 34/34 · 22/22 |
| Paridade Python × PHP | 2 × 1.205 entradas | 0 divergências | 0 divergências |
| Acessibilidade (52 páginas) | contraste, rótulos, foco, idioma | 3 achados, os mesmos da v4.00 | 3 achados, os mesmos da v4.00 |
| Links · responsivo · auditoria visual | URLs, 78 medidas + zoom 140%, 27 páginas | 0 · 0 · 0 | 0 · 0 · 0 |

Os 25/27 de S5V são as mesmas 2 medições desatualizadas descritas acima (conferido de novo: a v4.00 dá 25/27).
Testes antigos ajustados por mudança **intencional** de comportamento (as cópias originais ficaram intactas):
S5F (texto com `<script>` agora é recusado na entrada; o escape na saída continua testado com um dado antigo),
S6C (a porta de comparação passou a servir a v4.00, que já exige o aceite) e S5M/S6T/S6C (versão da minuta
1.1, de 11/10/2026). A suíte de logs passou a ler o logger da Stage 7.

## Casos por área

Formato: **ID · Objetivo · Pré-condições · Dados fictícios · Passos · Esperado · Observado · Status**.
O "Observado" cita o caso automático correspondente quando houve execução.

### Cadastro

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-CAD-01 | Cadastro de jovem válido | visitante | nome "Ana Teste", e-mail `ana@exemplo.test`, CPF de teste, senha `SenhaTeste#2026` | preencher 4 etapas, marcar aceite, enviar | 302 para `verificacoes/escolher_verificacao.php`; linha em `jovens` com `termos_versao` | S6C (50/50) | aprovado |
| T-CAD-02 | Etapas com JS: não avança com erro | idem | e-mail inválido | clicar Continuar na etapa 1 | permanece na etapa 1 com erro ao lado do campo | S6C | aprovado |
| T-CAD-03 | Sem JS, o formulário funciona | JS desligado | dados válidos | enviar | conta criada | S6C | aprovado |
| T-CAD-04 | Duplicidade de e-mail/CPF | conta existente | mesmo e-mail | enviar | mensagem de duplicidade; nada inserido | S6C, S3R | aprovado |
| T-CAD-05 | Aceite obrigatório | visitante | caixa desmarcada | enviar | erro "aceite"; nenhuma conta | S6T | aprovado |
| T-CAD-06 | Cadastro de empresa com CNPJ/CEP | visitante | CNPJ de teste, CEP 8 dígitos | preencher e enviar | conta da empresa; endereço gravado | S6C | aprovado |
| T-CAD-07 | Corrida de duplicidade (dois envios simultâneos) | visitante | mesmo e-mail em 2 requisições | enviar em paralelo | uma conta; a outra recebe mensagem (1062) | — | não executado |

### Login e sessão

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-LOG-01 | Login correto do jovem | conta de teste | e-mail e senha corretos | enviar | 302 `dashboard/jovem.php` | S3R; S6S-001 | aprovado |
| T-LOG-02 | Senha errada | idem | senha errada | enviar | mensagem genérica | S3R-118, S3R-127, S4I-014 | aprovado |
| T-LOG-03 | POST sem token CSRF | idem | sem `csrf_token` | enviar | 403 | S3R | aprovado |
| T-LOG-04 | Logout | logado | — | clicar Sair; abrir página interna | redireciona para o login | S4N | aprovado |
| T-LOG-05 | ID de sessão muda após o login | conta de teste | — | comparar cookie antes/depois | ID diferente | S3R-114 | aprovado |
| T-LOG-06 | Recuperação de senha completa (código lido do banco) | conta de teste | — | etapas 1–3; pular etapa 2; sem CSRF | senha trocada; pular a etapa 2 não troca; 403 sem token | S3R-135..144, S4N-063/064 | aprovado |
| T-LOG-06b | Recebimento do e-mail de código | `composer install` + SMTP | — | etapa 1 | e-mail recebido | — | bloqueado (sem PHPMailer/SMTP na réplica) |
| T-LOG-07 | **Recuperação não troca senha de outra conta** | duas contas fictícias | atacante e vítima | validar o próprio código, pedir código da vítima, definir senha | etapa 3 recusada | **entrou na conta da vítima** | **reprovado** (DOC-001, SEG-01) |
| T-LOG-08 | Limite de tentativas de login | conta | 20 senhas erradas | repetir | bloqueio/atraso | — | não executado (recurso ausente) |

### Perfis (jovem)

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-PER-01 | Atualizar formação estruturada | jovem logado | "Ensino Médio — 2º ano, cursando" | salvar | colunas `formacao`, `escolaridade_*` atualizadas | S5F | aprovado |
| T-PER-02 | Adicionar/remover habilidade e experiência | jovem | "Excel", "Atendimento" | adicionar e remover | linhas criadas/removidas só do próprio jovem | S3R | aprovado |
| T-PER-03 | Enviar PDF válido | jovem | PDF de 1 página | enviar | `curriculo_{id}.pdf` e `jovens.curriculo` | S3B | aprovado |
| T-PER-04 | Recusar não-PDF e > 5 MB | jovem | `.txt` renomeado; PDF de 6 MB | enviar | mensagem de erro | S3B | aprovado |
| T-PER-05 | Trocar e-mail zera verificação | jovem verificado | novo e-mail | salvar | `email_verificado = 0` | — | não executado (confirmado no código) |
| T-PER-06 | IA Advisor sem Flask | Flask parado | — | clicar "Analisar" | "IA temporariamente indisponível." | — | não executado |

### Empresas

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-EMP-01 | Editar endereço sincroniza vagas | empresa com vaga "no endereço da empresa" | nova cidade | salvar perfil | cidade da vaga muda | S51M | aprovado |
| T-EMP-02 | Locais: criar, editar, remover | empresa | filial fictícia | operações | conforme | S51M | aprovado |
| T-EMP-03 | Local em uso não é removido | local usado por vaga | — | remover | `?ok=em_uso` | S51M | aprovado |
| T-EMP-04 | Site sem `https://` impede salvar o perfil | empresa cadastrada com "www.exemplo.test" | — | salvar perfil no navegador | (comportamento atual) navegador bloqueia o envio | — | não executado (confirmado no código) |

### Vagas

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-VAG-01 | Publicar vaga com local | empresa | "Auxiliar Administrativo", Jovem Aprendiz | enviar | `?publicada=1`; vaga na listagem | S3R, S51M | aprovado |
| T-VAG-02 | Campos obrigatórios | empresa | sem título | enviar | `?error=missing` | S3R | aprovado |
| T-VAG-03 | Remover vaga própria | vaga da empresa | — | Remover | vaga e candidaturas apagadas | S3R | aprovado |
| T-VAG-04 | Não remover vaga de outra empresa | duas empresas | `vaga_id` alheio | POST delete | nada apagado; `vaga.remocao_recusada` | S3R | aprovado |
| T-VAG-05 | Busca por cidade/área | jovem; vagas variadas | "São Paulo", "TI" | filtrar | só vagas compatíveis | S3R, S4N | aprovado |
| T-VAG-06 | Editar/encerrar vaga | — | — | — | — | — | não executado (recurso ausente) |

### Candidaturas

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-CAN-01 | Candidatar-se | jovem; vaga | — | confirmar | `?result=ok`; status "Recebido" | S3R | aprovado |
| T-CAN-02 | Segunda candidatura recusada | já candidatado | — | confirmar de novo | `?result=duplicate` | S3R | aprovado |
| T-CAN-03 | Empresa muda status | candidatura à vaga dela | "Em análise" | Salvar | `{ok:true}`; jovem vê o novo status | S3R | aprovado |
| T-CAN-04 | Status inválido | empresa | "Contratado" | POST | 400 | S3R-028 | aprovado |
| T-CAN-05 | Empresa não altera candidatura de outra | duas empresas | id alheio | POST | recusado | S3R-029, S3R-030 | aprovado |
| T-CAN-06 | Favoritar/desfavoritar | jovem | — | clicar a estrela 2× | adiciona e remove | S3R | aprovado |

### Acesso da empresa a candidatos

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-ACE-01 | Candidato da própria vaga: perfil com e-mail | candidatura | — | abrir `jovem/perfil.php?id=` | perfil + e-mail; sem CPF/CEP/telefone | S6P | aprovado |
| T-ACE-02 | Perfil público sem candidatura | `perfil_publico = 1` | — | abrir | perfil sem e-mail | S6P | aprovado |
| T-ACE-03 | Perfil privado sem relação | `perfil_publico = 0` | — | abrir perfil/currículo/Match | 404 "Perfil indisponível" + `acesso.negado` | S6P | aprovado |
| T-ACE-04 | Enumeração de perfis públicos | vários jovens com padrão público | ids 1..N | abrir em sequência | (comportamento atual) todos acessíveis | — | não executado (SEG-03, confirmado no código) |

### Match

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-MAT-01 | Paridade Python × PHP | Flask ativo | 1.205 combinações | comparar notas | 0 divergências | 0 divergências | aprovado |
| T-MAT-02 | Flask fora do ar | Flask parado | par novo | Match | nota calculada em PHP | S51F | aprovado |
| T-MAT-03 | Cache: nota muda ao editar o perfil | cache existente | mudar cidade | Match | `atualizado:true` | S51M | aprovado |
| T-MAT-04 | Substring de área ("TI" × "Administrativo") | Flask ativo | ver dados | POST `/avaliar` | (comportamento atual) +13 | +13 ("relação") | aprovado (DOC-002 documenta a limitação) |
| T-MAT-05 | Cidades com nome parecido | idem | "Santo André" × "Santos" | idem | (atual) +12 "cidade próxima" | +12 | aprovado (DOC-003) |
| T-MAT-06 | Tetos por contrato | idem | Aprendiz/Estágio/CLT com tudo compatível | idem | 100 / 95 / 85 | 100/95/85 | aprovado (DOC-004) |
| T-MAT-07 | Acentos | idem | "Estagio" | idem | (atual) perde 10 pontos | 70 → 60 | aprovado (DOC-005) |
| T-MAT-08 | Ajuste "concluído" | idem | descrição com "concluído", jovem cursando | idem | −10 | 75 → 65 | aprovado (DOC-006) |
| T-MAT-09 | Análise contextual não altera a nota | Gemini simulado | — | abrir detalhe | nota igual; selo "não altera a nota" | S51C, S51U | aprovado |
| T-MAT-10 | Indisponível × nota baixa | sem chave | — | abrir detalhe | nota exibida + aviso de IA indisponível | S51K, S51U | aprovado |

### Assistente

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-ASS-01 | Resposta da IA | Gemini simulado | "Como me candidato?" | enviar | resposta `fonte:"ia"` | S6I | aprovado |
| T-ASS-02 | Mascaramento | idem | "meu CPF é 123.456.789-09" (fictício) | enviar | aviso "Removemos dados pessoais"; CPF não chega ao Flask | S6I | aprovado |
| T-ASS-03 | HTML não executa | idem | resposta com `<script>` | enviar | aparece como texto | S6I | aprovado |
| T-ASS-04 | Limite por minuto | idem | 9 mensagens | enviar | 9ª com 429 | S6I | aprovado |
| T-ASS-05 | Sem IA | Flask parado | — | enviar | resposta pronta + aviso; cota devolvida | S6I | aprovado |
| T-ASS-06 | Gemini real com chave válida | chave real | — | enviar | resposta real | — | não executado (proibido usar chave real nesta tarefa) |

### Termos e privacidade

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-TER-01 | Página própria dos documentos | — | `?doc=termos`, `?doc=privacidade`, `?doc=xyz` | abrir | 200; inválido vira Termos | S6T | aprovado |
| T-TER-02 | Modais acessíveis | — | — | abrir pelo teclado, Esc | `role=dialog`, foco preso e devolvido | S5M | aprovado |
| T-TER-03 | Registro do aceite | migração aplicada | — | cadastrar | `termos_versao` e `termos_aceitos_em` | S6T | aprovado |
| T-TER-04 | Sem a migração | banco sem as colunas | — | cadastrar | conta criada sem registro | smoke sem migração | aprovado |

### Segurança e autorização

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-SEG-01 | Respostas não vazam detalhes internos | — | 220 respostas | varrer por "Fatal error", "SQLSTATE", caminhos | nenhum vazamento | S3R | aprovado |
| T-SEG-02 | Páginas internas exigem login | sem sessão | — | abrir | redireciona | S4N | aprovado |
| T-SEG-03 | `/ping` não mostra a chave | Flask | — | GET | só booleanos | DOC-007 | aprovado |
| T-SEG-04 | `.env` não é servido | Apache real | — | GET `/.env` | 403/404 | — | não executado (exige o XAMPP do usuário); desde a v4.60 coberto por `verificar_protecao.bat` |
| T-SEG-05 | `logs/` e `uploads/curriculos/` bloqueados | Apache real | — | GET direto | 403 | — | não executado (a réplica não usa Apache); desde a v4.60 coberto por `verificar_protecao.bat` (arquivo de teste criado e apagado) |
| T-SEG-06 | XSS no IA Advisor | integração antiga ativa | resumo com instrução maliciosa | analisar | texto escapado | — | não executado (exige Gemini real; risco confirmado no código, SEG-05) |

### Banco de dados

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-BD-01 | `schema.sql` em banco vazio | banco de rascunho | — | importar | 49 comandos, 0 falhas | 49/0 | aprovado |
| T-BD-02 | Migrações em banco antigo | banco sem migrações | — | aplicar em ordem | colunas criadas; site funciona | smoke com migração | aprovado |
| T-BD-03 | Reimportar `schema.sql` sobre banco existente | banco de rascunho já criado | — | importar de novo | (comportamento atual) falha parcial | 22 falhas; 6 tabelas apagadas | aprovado como **constatação** (registrado em SEG-11) |
| T-BD-04 | Exclusão de conta e cascata | conta com candidatura, favorito, avaliação | — | excluir pelo modal | só a própria conta; dependentes removidos; GET/sem CSRF/sem confirmação não excluem | S3B-026..031, S3B-038..041 | aprovado |
| T-BD-05 | Seed duas vezes | banco da réplica | `database/seed_demo.sql` | aplicar 2× | sem duplicar | S6S | aprovado |

### Logs

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-LOGS-01 | Uma linha JSON por evento | — | — | gerar eventos | JSON válido | S6L | aprovado |
| T-LOGS-02 | Sem dados pessoais | — | e-mail, CPF em campos | registrar | `[removido]`/`[e-mail]` | S6L | aprovado |
| T-LOGS-03 | Correlação PHP × Flask | Flask | — | Match de par novo | mesmo `req` em `app.log` e `ia.log` | S6L-013, S6L-014 | aprovado |
| T-LOGS-04 | Rotação e retenção | arquivos de log de teste | — | simular outro dia, tamanho e idade | rotaciona e apaga os antigos | S6L-018..020 | aprovado |

### Responsividade e acessibilidade

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-RES-01 | Sem rolagem lateral | — | 375, 768, 1366 px; zoom 140% | medir 78 páginas×tamanhos | 0 casos | 0 | aprovado |
| T-A11Y-01 | Contraste, rótulos, foco | — | temas claro/escuro | varrer páginas | sem problemas novos | 0 novos | aprovado |
| T-A11Y-02 | Movimento reduzido | `prefers-reduced-motion` | — | abrir páginas | sem animação; conteúdo visível | S6A, a11y | aprovado |
| T-A11Y-03 | Fonte para dislexia | — | — | ativar | fonte OpenDyslexic | — | não executado (a fonte não é carregada; confirmado no código) |

### Compatibilidade com dados antigos

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-COMP-01 | Vagas antigas sem `local_trabalho` | vaga com `local_trabalho = NULL` | — | listar/Match | usa cidade/UF da vaga | S51M | aprovado |
| T-COMP-02 | Formação no formato antigo | registro antigo | técnico Administração | Match | mesma nota do formato novo | S5F (55 = 55) | aprovado |
| T-COMP-03 | Site sem migrações | banco antigo | — | navegar | funciona | smoke sem migração | aprovado |

### Integração PHP × Python

| ID | Objetivo | Pré-condições | Dados | Passos | Esperado | Observado | Status |
|---|---|---|---|---|---|---|---|
| T-INT-01 | `X-Request-Id` repassado | Flask | — | Match | mesmo `req`; ID inválido é trocado | S6L-013, S6L-014 | aprovado |
| T-INT-02 | Flask fora do ar / Gemini lento | Flask parado; mock lento | — | Match, contexto, assistente | cálculo local; timeout tratado | S51F-001, S51C-029, S51M-081, S6I-034 | aprovado |
| T-INT-03 | Respostas inválidas do Gemini | mock: HTML, 400, 429, 500, vazio, quebrado | — | contexto/assistente | indisponível sem erro técnico | S51C, S6I | aprovado |
| T-INT-04 | `iniciar_ia.bat` no Windows | Windows com Python | — | duplo clique | Flask e navegador abertos | — | não executado (sem Windows nesta auditoria) |

## v4.60 — verificações no próprio XAMPP

Roteiros versionados (não alteram o banco do site nem a pasta; todos gravam um relatório em `logs\`, que fica fora
do Git). Resultados da réplica em [TESTES_RESULTADOS.md](TESTES_RESULTADOS.md#v460--correção-de-segurança-e-propriedade-intelectual).

| Roteiro | Precisa de | O que confere | Saída |
|---|---|---|---|
| `scripts\seguranca\verificar_protecao.bat` (`-Rede` opcional) | Apache ligado | arquivos internos existentes bloqueados (403/404), variantes e apelidos do Windows (inclusive os nomes curtos 8.3 reais), código-fonte, listagem, `logs/` e `uploads/curriculos/` fechadas, site funcionando, cabeçalhos, pastas vizinhas do `htdocs` | 0 = aprovado, 1 = reprovação, 2 = Apache fora |
| `scripts\seguranca\regressao_isolada.bat` (`--manter` opcional) | MySQL do XAMPP ligado | num **banco de teste novo** e numa cópia temporária: cabeçalhos (HDR), páginas (PUB/PRIV), login e limite (LOGIN, LIM), acesso por dono (IDOR, PRIV, CAND), cadastro privado (CAD), sessões (SESS, IDENT), currículo (CV), código cancelado (COD), contas de demonstração (DEMO), logout (SAIR), erros do PHP (PHP) | 0 = tudo aprovado, 1 = falha, 2 = ambiente indisponível |
| `scripts\seguranca\diagnostico_somente_leitura.bat` | — | versões, `Listen`, `AllowOverride`, PHP, MariaDB, rede, firewall, Git e varredura de segredos com saída redigida | relatório |
| `php scripts\jovemconnect.php protecao` | Apache ligado | versão curta da verificação (roda sozinha no `iniciar_jovemconnect.bat`) | 0/1 |

## Testes de regressão recomendados

Rodar depois de **qualquer** mudança (prioridade nesta ordem):

1. Login/logout dos dois tipos, cadastro (com e sem JS) e CSRF (T-LOG-01..04, T-CAD-01..05).
2. Permissões de dono e acesso ao candidato (T-VAG-04, T-CAN-05, T-ACE-01..03).
3. Match: paridade Python × PHP e Flask fora (T-MAT-01, T-MAT-02).
4. Upload e entrega do currículo (T-PER-03, T-PER-04).
5. Varredura de vazamento de erros (T-SEG-01) e links (81 URLs).
6. Responsivo e acessibilidade nas páginas alteradas.
7. ~~Depois de corrigir SEG-01: repetir T-LOG-07~~ (SEG-01 corrigido no Lote A).
10. (v4.60) Depois de mudar o `.htaccess`, o Apache ou a pasta de lugar: `verificar_protecao.bat`. Depois de mudar
    login, códigos, sessão, cadastro ou currículo: `regressao_isolada.bat`.
8. (Stage 7) Central administrativa: entrada com autenticador, limite de tentativas, permissões e CSRF (bloco A);
   suspensão/reativação e efeitos (bloco B); visibilidade das vagas moderadas (bloco C); exemplos de validação e
   moderação (bloco D); conferência do histórico (`php scripts/admin.php verificar-auditoria`, bloco E).
9. (Stage 7) Sempre que mexer em consultas de vagas ou candidatos: conferir que vagas fora do ar, de empresas
   suspensas e jovens suspensos continuam ocultos (`jc_vagas_visiveis_sql`, `jc_jovens_ativos_sql`).

## Recomendações

- Versionar uma bateria mínima (por exemplo `tests/` com scripts Python + `requests`) para os itens 1–5.
- Executar T-SEG-04 e T-SEG-05 no XAMPP real antes de qualquer apresentação pública (`verificar_protecao.bat`).
