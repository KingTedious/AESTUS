# Assistente JovemConnect e recursos de IA

## Como o assistente funciona

1. A pessoa abre o painel (botão ✦) e envia uma mensagem (até 500 caracteres).
2. `includes/assistente.php` envia a mensagem, as últimas falas da conversa e o
   tipo de página para `IA/assistente_ajax.php` (com o token CSRF da página).
3. `IA/assistente_ajax.php`:
   - aplica limites: 8 mensagens por minuto e 60 por hora por sessão, 40 por
     minuto por endereço IP (HTTP 429 com aviso amigável). Mensagens que nem
     chegam ao Gemini (sem chave ou Flask fora do ar) não contam no limite;
   - troca e-mail, CPF, CNPJ, telefone, CEP e números longos por
     `[dado removido]` e avisa a pessoa que isso foi feito;
   - envia ao Flask só a mensagem, as 6 últimas falas e o contexto
     (`pagina` e `perfil`: visitante, jovem ou empresa) — nunca nome, e-mail,
     IDs ou dados da conta;
   - não grava a conversa em banco nem em log (o log guarda só fonte e tempo).
4. O Flask (`/assistente`) chama o Gemini com instruções fixas: dizer que é um
   assistente de IA, responder curto em português, não pedir dados pessoais, não
   inventar vagas/leis e deixar claro que **não executa ações** (não se
   candidata, não altera dados, não envia mensagens).
5. A resposta volta como texto e é mostrada com `textContent`: HTML ou scripts
   vindos da IA aparecem como texto e nunca são executados.

A conversa fica só na memória da página; ao recarregar ou sair, ela some.

## Quando não há IA

Sem `GEMINI_API_KEY`, com o Flask fora do ar, erro do Gemini, limite de uso
(429), resposta vazia ou demora maior que o limite (`GEMINI_TIMEOUT` no Flask,
25 s no navegador), o endpoint responde `{"fonte": "local"}` e o navegador usa as
**respostas prontas** (lista `RESPOSTAS` em `includes/assistente.php`), com o
aviso "Resposta automática: o assistente com IA não está disponível agora".

## Outros recursos de IA

| Recurso | Onde | Sem IA |
|---|---|---|
| Nota de compatibilidade (Match) | `IA/avaliar_ajax.php` → Flask `/avaliar` | Mesmo cálculo em PHP (`includes/match.php`) |
| Análise contextual do Match | `Vagas/avaliar.php` → Flask `/analise_contextual` | A página mostra só a composição da nota |
| Sugestões de currículo (IA Advisor) | Flask `/curriculo` (regras fixas no Flask + sugestão da integração antiga, se ativa) | **Sem o Flask:** "IA temporariamente indisponível." (HTTP 503; não há regras em PHP) |

## Roteiro de testes manuais

Antes: `iniciar_ia.bat` aberto; <http://127.0.0.1:5000/ping> responde.

**Com chave configurada** (`"assistente": true` no `/ping`)
1. Página inicial → ✦ → "Como eu me candidato a uma vaga?" → aparece
   "Digitando…", o botão de enviar fica desativado e chega uma resposta da IA.
2. Envie outra pergunta relacionada ("e depois disso?") → a resposta considera a
   conversa anterior.
3. Envie "meu CPF é 123.456.789-09" → aparece a nota "Removemos dados pessoais da
   sua mensagem antes de enviar".
4. Envie `<b>teste</b>` ou peça para a IA responder com HTML → o texto aparece
   literalmente, sem formatação nem scripts.
5. Envie 9 mensagens em menos de um minuto → a 9ª recebe "Muitas mensagens em
   pouco tempo".
6. Confira `logs/app.log`: eventos `assistente.resposta` com `fonte` e `ms`,
   **sem** o texto das mensagens.

**Sem IA**
7. Feche a janela "JovemConnect - IA" (Flask parado) → pergunte "O que é Jovem
   Aprendiz?" → resposta pronta + aviso de resposta automática, sem erro técnico.
8. Rode o `iniciar_ia.bat` com `GEMINI_API_KEY=` vazio → mesmo comportamento do
   item 7; o Match continua dando nota.

**Acessibilidade**
9. Abra o assistente pelo teclado (Tab até ✦, Enter), digite e envie com Enter;
   Esc fecha e o foco volta ao botão ✦.
10. Em tela de celular (375 px), o painel cabe inteiro, sem rolagem lateral.

Detalhes técnicos completos (algoritmo do Match, cache, limites): [IA_E_MATCH.md](IA_E_MATCH.md).

Na réplica de desenvolvimento, os testes automáticos usaram um **servidor que
imita o Gemini** (sem internet e com chave falsa): respostas válidas, HTML
malicioso, erro de chave inválida (400), 429, 500, demora, resposta quebrada e resposta vazia.
