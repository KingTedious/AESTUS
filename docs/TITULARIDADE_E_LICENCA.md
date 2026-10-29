# Titularidade, licença e marca — pendências

> **Classificação: INTERNO.** Versão v4.60 (29/10/2026). PI-05, PI-10 e PI-15 do `RELATORIO_AUDITORIA_PI.md`.
>
> **Não é parecer jurídico.** Este documento organiza fatos e perguntas para o responsável decidir com a
> orientação da escola e de um profissional de propriedade intelectual e de proteção de dados.
> Em 29/10/2026 o responsável escolheu **"todos os direitos reservados"** ([`LICENSE`](../LICENSE)). Nenhuma
> autoria foi alterada nos arquivos e nada aqui afirma que o projeto está juridicamente protegido.

## Situação atual (fatos)

| Item | Situação |
|---|---|
| Arquivo de licença (`LICENSE`) | **todos os direitos reservados** (desde 29/10/2026), em nome dos autores (equipe Aestus); componentes de terceiros mantêm as próprias licenças |
| Campo `license` do `composer.json` | `proprietary` (desde 29/10/2026); o arquivo declara um autor (nome e e-mail pessoal) |
| `README.md` | seção "Autor" com o desenvolvedor principal; contato institucional da Aestus |
| Repositório no GitHub | privado desde a criação; contas do desenvolvedor principal e do secundário, ambas com 2FA; nenhum outro colaborador (dois convites expiraram) [Declarado] |
| Contratos, cessões, acordos entre integrantes, acordos de confidencialidade | nenhum ainda; um contrato particular entre os integrantes está em preparação [Declarado] |
| Registro de programa de computador (INPI) | nenhum; há intenção de registrar [Declarado] |
| Registro de marca ("JovemConnect", "Aestus") | nenhum; busca feita pela equipe no INPI sem encontrar marcas iguais; há intenção de registrar [Declarado] |
| Aestus | startup sem CNPJ (sem personalidade jurídica); donos: os integrantes, sujeitos ao contrato [Declarado] |
| Regulamento do TCC | o trabalho é dos integrantes; não trata de publicação, uso comercial nem IA [Declarado] |

"Todos os direitos reservados" deixa explícito o que já valia sem licença: ninguém recebe autorização para
usar, copiar ou distribuir. Não impede, na prática, que quem tenha acesso copie.

## Quatro proteções diferentes

Não se confundem; cada uma tem regras e titulares próprios.

| Proteção | Sobre o quê | Referência (para a conversa com o profissional) | Observação |
|---|---|---|---|
| **Direito autoral sobre o programa** | código-fonte PHP, Python, SQL, CSS, JS e scripts | Lei 9.609/1998 (programa de computador) e, no que couber, Lei 9.610/1998 | a proteção **independe de registro** (Lei 9.609, art. 2º, §3º); o registro no INPI é **opcional** (art. 3º) e ajuda a fixar data e autoria declarada (o INPI trabalha com um resumo *hash* do código, que o titular guarda) |
| **Direito autoral sobre outras obras** | textos (documentação, termos, textos da interface), fotos, desenho dos logotipos | Lei 9.610/1998 | fotos de origem desconhecida não ficam autorizadas só por estarem no projeto ([ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md)) |
| **Marca** | nomes "JovemConnect" e "Aestus" e os logotipos como sinal distintivo | Lei 9.279/1996 (registro no INPI) | depende de busca de anterioridade e de pedido; é diferente do direito autoral sobre o desenho do logo |
| **Informação confidencial** | o que se quiser manter em segredo (ex.: pesos do Match, instruções da IA de versões futuras) | Lei 9.279/1996, art. 195, XI (concorrência desleal), que exclui o que é de conhecimento público | repositório privado, sozinho, não cria segredo comercial; o que for publicado no TCC deixa de ser confidencial ([CLASSIFICACAO.md](CLASSIFICACAO.md)) |

Proteção de dados pessoais (LGPD, Lei 13.709/2018) é outro assunto, tratado no PI-06 (histórico do Git) e no
PI-07 (transparência do Match, art. 20).

## Perguntas que exigem decisão humana

| # | Pergunta | Quem decide / com quem | Fatos de apoio |
|---|---|---|---|
| T1 | Quem é titular do código: os integrantes em coautoria, só alguns, a Aestus, a escola? | integrantes, com a orientação da escola e de profissional | [AUTORIA_E_ORIGEM.md](AUTORIA_E_ORIGEM.md) |
| T2 | O que o regulamento de TCC da escola diz sobre titularidade, publicação e uso comercial do trabalho? | responsável e orientador | — |
| T3 | Alguma parte foi feita durante estágio, emprego, bolsa ou contrato? A Lei 9.609/1998, art. 4º, atribui ao empregador ou contratante, salvo estipulação em contrário, os programas feitos nesses vínculos (o §3º estende a regra a bolsistas, estagiários e assemelhados) | cada integrante | — |
| T4 | O que é a Aestus juridicamente (pessoa jurídica? de quem?) e qual a relação dela com o JovemConnect (titular, licenciada, apenas marca)? | responsável | o responsável descreve a Aestus como organização-mãe do JovemConnect |
| T5 | Como tratar os trechos produzidos com ferramentas de IA? A Lei 9.610/1998, art. 11, define autor como pessoa física; a proteção de conteúdo gerado por IA não tem consenso no Brasil; os termos de uso de cada ferramenta tratam da titularidade das saídas | responsável, com profissional | ferramentas e períodos em [AUTORIA_E_ORIGEM.md](AUTORIA_E_ORIGEM.md); termos das ferramentas não verificados |
| T6 | Como declarar no TCC o uso de IA e de componentes de terceiros? | responsável e orientador | [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md) |
| T7 | Qual licença adotar (por exemplo: todos os direitos reservados; licença para avaliação acadêmica; uma licença aberta) e para quais partes? | titulares, **depois** de T1 a T5 | — |
| T8 | Haverá acordo escrito entre os integrantes (coautoria, cessão ou licença para a Aestus, uso dos nomes)? | integrantes | nenhum acordo encontrado |
| T9 | Registrar o programa no INPI? Registrar as marcas? | titulares, com profissional | busca de anterioridade da marca não feita |
| T10 | Origem e licença das duas fotos; autoria dos logotipos; trocar ou manter? | responsável | [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md) |
| T11 | O que manter confidencial, sabendo o que o TCC vai publicar? Transparência do Match × segredo (LGPD, art. 20) | responsável, com profissional | [CLASSIFICACAO.md](CLASSIFICACAO.md) (PI-07) |
| T12 | Histórico do Git com registro de aparência pessoal (possível menor) e chave antiga revogada: manter privado e documentado, ou reescrever (com backup antes)? | responsável, com orientação em LGPD | PI-06; [PROCEDIMENTO_EVIDENCIAS.md](PROCEDIMENTO_EVIDENCIAS.md) |

## Respostas do responsável (29/10/2026)

| # | Resposta [Declarado] | Observação (não é parecer) |
|---|---|---|
| T1 | os integrantes; o contrato vai definir quem mantém direitos e participação | ver "Pontos para o contrato" |
| T2 | o regulamento diz que o trabalho é dos integrantes; não trata de publicação, uso comercial nem IA | — |
| T3 | integrantes que trabalham o fazem em atividades sem relação com o projeto; ninguém usou computador, horário ou material do trabalho | pela Lei 9.609/1998, art. 4º, §2º, nesse caso os direitos são do próprio autor |
| T4 | startup sem CNPJ; os donos são os integrantes; a Aestus é a "dona" do JovemConnect (como a Meta do WhatsApp) | sem personalidade jurídica, a Aestus ainda não pode ser titular: no papel, os direitos são dos autores |
| T5 | ChatGPT, Gemini (inclusive pelo Cline) e Claude, nas fases descritas em [AUTORIA_E_ORIGEM.md](AUTORIA_E_ORIGEM.md) | conferir os termos de cada ferramenta quanto às saídas antes do registro |
| T6 | não declarar no texto do TCC (o regulamento não trata de IA) | recomendação: consultar o orientador; responder com a verdade se perguntado |
| T7 | **todos os direitos reservados** | aplicado: [`LICENSE`](../LICENSE), `composer.json`, `README.md` |
| T8 | contrato particular em preparação: só manteria direitos e participação quem pagar os custos iniciais de registro e continuar pagando depois dos três primeiros anos | ver "Pontos para o contrato" |
| T9 | registrar as marcas e o programa, se possível; busca no INPI sem marcas iguais | — |
| T10 | fotos copiadas do Google Imagens, sem licença, mantidas por enquanto; logotipos da equipe (autor: Renato Braga de Mello) | [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md) |
| T11 | o TCC não será publicado (só apresentado no estande); a apresentação mostra os critérios do Match; a tela mantém a pontuação (PI-07) | [CLASSIFICACAO.md](CLASSIFICACAO.md) |
| T12 | manter o histórico do Git como está | PI-06 |

## Pontos para o contrato (levar a um advogado)

Não é parecer jurídico; são pontos para a conversa com o profissional:

- **Autoria não se transfere.** Quem criou continua sendo autor (Lei 9.610/1998, art. 27; no programa de
  computador, Lei 9.609/1998, art. 2º, §1º, preserva o direito de reivindicar a autoria). O que se transfere
  são os direitos de exploração, por **cessão escrita** de cada autor (Lei 9.610/1998, arts. 49 e 50).
- **Quem não assinar continua com os direitos sobre o que criou.** Por exemplo: sem a cessão do autor dos
  logotipos, os outros (ou a Aestus) não poderiam usá-los. O contrato precisa prever o que acontece com as
  partes de quem sair.
- **Menores de 18 anos** só firmam contrato válido com os pais ou responsáveis (Código Civil, arts. 3º e 4º:
  assistidos de 16 a 17 anos, representados abaixo de 16).
- **Programa de computador não é patenteável** no Brasil (Lei 9.279/1996, art. 10, V). Os instrumentos são o
  **registro do programa** (opcional, fixa data e autoria declarada) e o **registro de marca**, ambos no INPI.
- **A Aestus sem CNPJ** não pode ser titular. Para ela ser dona do JovemConnect, precisa ser constituída como
  empresa e receber os direitos por cessão dos autores; abrir empresa com sócios menores de idade tem regras
  próprias (contador e advogado).
- **Partes feitas com IA:** a proteção de conteúdo gerado por IA não tem consenso; o registro e o contrato
  devem considerar o histórico de [AUTORIA_E_ORIGEM.md](AUTORIA_E_ORIGEM.md).
- **Busca de marca:** a busca do INPI deve incluir nomes parecidos nas classes de serviço pretendidas, não só o
  nome exato.

## Ordem sugerida

1. Completar os fatos pendentes de [AUTORIA_E_ORIGEM.md](AUTORIA_E_ORIGEM.md) e de
   [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md).
2. Consultar o regulamento da escola e o orientador (T2, T6).
3. Com um profissional: titularidade, acordo entre integrantes, licença, marca e registro (T1, T3 a T5, T7 a T9).
4. Depois do contrato: atualizar o titular no `LICENSE` e no `README.md` (hoje: "os autores do JovemConnect
   (equipe Aestus)").

## O que esta correção fez e não fez

- **Fez:** aplicou "todos os direitos reservados" (decisão de 29/10/2026); registrou os fatos (este documento, [AUTORIA_E_ORIGEM.md](AUTORIA_E_ORIGEM.md),
  [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md), `THIRD_PARTY_NOTICES.md`), completou os avisos de licença dos
  ícones e preparou o roteiro de evidências ([PROCEDIMENTO_EVIDENCIAS.md](PROCEDIMENTO_EVIDENCIAS.md)).
- **Não fez:** alterar autoria, registrar nada, trocar imagens ou logotipos,
  reescrever o histórico.
