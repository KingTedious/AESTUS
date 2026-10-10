# Arquitetura do JovemConnect

## Visão geral

```
Navegador ──HTTP──▶ Apache + PHP (páginas e endpoints AJAX) ──mysqli──▶ MariaDB
                         │
                         └─HTTP 127.0.0.1:5000 (X-Request-Id)──▶ Flask (IA/recomendar.py)
                                                                   └─HTTPS──▶ Google Gemini (opcional)
```

- O **PHP** monta as páginas, controla login/sessão e aplica todas as regras de
  acesso. O navegador nunca fala direto com o Flask nem com o Gemini.
- O **Flask** só escuta em `127.0.0.1` e recebe do PHP dados já reduzidos ao
  mínimo. Fora do ar, o site continua funcionando (veja "Quando a IA falha").

## Pastas e responsabilidades

| Pasta / arquivo | Papel |
|---|---|
| `includes/config.php` | Conexão com o banco (variáveis `DB_*` do `.env`), tratamento de erros sem expor detalhes |
| `includes/env.php` | Lê o `.env` da raiz |
| `includes/session.php`, `includes/csrf.php` | Sessão endurecida e token CSRF (formulários: campo `csrf_token`; AJAX: cabeçalho `X-CSRF-Token`) |
| `includes/logger.php` | Logs JSON (veja [LOGS.md](LOGS.md)) |
| `includes/etapas.php` | Formulário em etapas (cadastros) |
| `includes/termos.php`, `includes/documentos.php`, `includes/institucional.php` | Versão e registro do aceite; texto dos Termos/Privacidade; modais do rodapé |
| `includes/candidato.php` | Regra única de acesso da empresa aos dados de um candidato |
| `includes/match.php` | Match tradicional em PHP (cópia fiel do algoritmo do Flask) e cliente HTTP do Flask |
| `includes/locais.php` | Endereço principal e locais de trabalho; "local efetivo" de cada vaga |
| `includes/formacao.php` | Formação estruturada (nível → curso/ano → situação) |
| `includes/assistente.php` | Assistente, painel de acessibilidade e botões flutuantes |
| `IA/recomendar.py` | Rotas `/avaliar`, `/curriculo`, `/analise_contextual`, `/assistente`, `/ping` |

## Cadastro em etapas

`jovem/cadastro.php` e `empresa/cadastro.php` têm **um único formulário** dividido
em 4 seções. Com JavaScript, `includes/etapas.php` mostra uma etapa por vez
("Etapa X de 4", barra de progresso, Voltar/Continuar), valida cada etapa antes de
avançar, mostra os erros ao lado dos campos e monta o resumo para revisão. Sem
JavaScript, todas as etapas aparecem juntas e o formulário funciona igual.

O servidor **sempre** valida tudo de novo; com erro, a página reabre na etapa do
primeiro campo com problema e mantém o que foi digitado (menos as senhas). O
e-mail/CPF/CNPJ repetido é barrado antes do INSERT e também pela chave única do
banco (dois envios ao mesmo tempo). O aceite dos Termos é uma caixa que nunca vem
marcada; versão e data ficam em `termos_versao` / `termos_aceitos_em`.

## Quem vê o quê (empresa × candidato)

Regra em `includes/candidato.php`, usada no perfil (`jovem/perfil.php?id=`), no
currículo (`uploads/ver_curriculo.php`) e na análise do Match
(`Vagas/avaliar.php`, `IA/avaliar_ajax.php`). A empresa vê o candidato quando:

1. o jovem se candidatou a uma vaga **desta** empresa; ou
2. o jovem deixou ligado "Permitir que empresas encontrem seu currículo nas buscas".

Sem relação: resposta "Perfil indisponível" (HTTP 404, igual para "não existe"),
registrada como `acesso.negado` em `logs/seguranca.log`.

A empresa nunca recebe CPF, CEP, endereço, telefone ou data de nascimento (só a
idade). O **e-mail** só aparece no caso 1. A consulta SQL da visão da empresa já
seleciona apenas essas colunas.

## Match

- Nota de 0 a 100 por regras fixas (localização, área, curso técnico,
  escolaridade, tipo de contrato, habilidades, experiência). Pesos e regras estão
  em `IA/recomendar.py` (`/avaliar`) e, idênticos, em `includes/match.php`.
- Com o Flask fora do ar, o PHP calcula a mesma nota.
- O "local efetivo" da vaga é o local de trabalho escolhido na vaga ou, se não
  houver, o endereço principal da empresa; vagas antigas usam a cidade/UF
  digitadas nelas.
- A **análise contextual** (Gemini) só explica a nota; nunca a altera.

## Assistente

`includes/assistente.php` → `IA/assistente_ajax.php` → Flask `/assistente` → Gemini.
Detalhes, limites e testes em [ASSISTENTE_E_IA.md](ASSISTENTE_E_IA.md).

## Quando a IA falha

| Situação | O que o usuário vê |
|---|---|
| Sem `GEMINI_API_KEY` | Match tradicional; assistente com respostas prontas |
| Flask fora do ar | Nota calculada no PHP; assistente com respostas prontas |
| Gemini com erro, limite ou lento | Match sem análise contextual; assistente com respostas prontas e aviso |

## Banco de dados

Estrutura completa em `schema.sql` (instalação nova). Mudanças em bancos já em
uso entram por migrações **aditivas** em `migrations/` (veja [MIGRACOES.md](MIGRACOES.md)).
Tabelas principais: `jovens`, `empresas`, `empresa_locais`, `vagas`
(+ `requisitos_vaga`, `beneficios_vaga`), `candidaturas`, `favoritos`,
`habilidades_jovem`, `formacao_jovem`, `experiencias_jovem`, `avaliacoes_ia`.
Excluir uma conta remove os registros ligados a ela (chaves estrangeiras com
`ON DELETE CASCADE`) e o arquivo do currículo.
