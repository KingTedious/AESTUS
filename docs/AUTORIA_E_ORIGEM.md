# Autoria e origem

> **Classificação: CONFIDENCIAL** (desde 29/10/2026: contém o uso de ferramentas de IA, papéis da equipe e
> planos de contrato). Versão v4.60 (29/10/2026). Criado para o PI-04 e o PI-05 do `RELATORIO_AUDITORIA_PI.md`.
>
> Registro **factual** do que se sabe sobre quem fez o JovemConnect, quando e com quais ferramentas. Não
> inventa autoria, datas nem contratos, e não apaga menções à assistência de IA. **Não é parecer jurídico** e
> não estabelece titularidade: isso depende das decisões listadas em
> [TITULARIDADE_E_LICENCA.md](TITULARIDADE_E_LICENCA.md).

## Como ler este registro

Cada informação traz a fonte:

| Marca | Significa | Força como prova |
|---|---|---|
| **[Declarado]** | informado pelo responsável pelo projeto | depende de confirmação por documentos |
| **[Git]** | metadados do Git (autor, data, mensagem) | **declarados** por quem faz o commit; podem ser escritos livremente |
| **[Relatório]** | relatórios das sessões de desenvolvimento e auditoria guardados no Project do claude.ai | registro de terceiro (serviço on-line), com data |
| **[Arquivo]** | metadados dos próprios arquivos (ex.: manifesto C2PA das imagens) | indica por onde o arquivo passou, não quem o criou |
| **[A confirmar]** | lacuna conhecida | — |

Assinatura criptográfica (SSH/GPG) e evidência externa (carimbo de tempo, registro, backup com hash
guardado por outra pessoa) **ainda não existem** para as versões até a v4.59. O roteiro para criá-las daqui
para frente está em [PROCEDIMENTO_EVIDENCIAS.md](PROCEDIMENTO_EVIDENCIAS.md); o responsável decidiu, em
29/10/2026, configurar a assinatura **antes** de gravar a v4.60.

## Projeto e pessoas

| Item | Informação | Fonte |
|---|---|---|
| Produto | JovemConnect, plataforma de empregabilidade para jovens (público a partir de 14 anos) | [Declarado] |
| Organização | Aestus, startup **sem CNPJ** (sem personalidade jurídica), descrita como organização-mãe e "dona" do JovemConnect; os donos são os integrantes, sujeitos ao contrato em preparação | [Declarado] (29/10/2026) |
| Contexto acadêmico | Trabalho de conclusão de curso do Ensino Médio Técnico, Colégio Cruzeiro do Sul (SP), 2026. O regulamento da escola diz que o trabalho é dos integrantes; não trata de publicação, uso comercial nem uso de IA | [Declarado] |
| Orientação | professores Felipe Dias, Vanderlei Winchiski e Antônio Marcos (atuais); outros professores acompanharam o projeto em períodos anteriores do ano | [Declarado] |
| Equipe | 9 integrantes | [Declarado] |
| Desenvolvedor principal (full-stack) | Gabriel Sassati (nome já presente no `README.md` e no `composer.json`) | [Declarado] |
| Desenvolvedor secundário | um integrante da equipe | [Declarado] |
| Design da marca e da identidade visual (logotipos do JovemConnect e da Aestus) | Renato Braga de Mello — nome registrado por autorização do responsável, como autor dos logotipos | [Declarado] |
| Design (principalmente do site da Aestus) | um integrante | [Declarado] |
| Design (assistência na identidade visual e slides da apresentação) | um integrante | [Declarado] |
| Documentação (artigo científico e documentos entregues para avaliação) | dois integrantes | [Declarado] |
| Opiniões sobre o design | um integrante | [Declarado] |
| Custeio da maior parte das despesas | um integrante | [Declarado] |

**Nomes:** por decisão do responsável (29/10/2026), a lista nominal fica **pendente** até o contrato
particular entre os integrantes. Este documento só traz os nomes já públicos no projeto e o do autor dos
logotipos (autorizado).

**Vínculos de trabalho:** há integrantes que trabalham, em atividades sem relação com o projeto nem com a
escola; ninguém usou computador, horário ou material do trabalho no projeto [Declarado].

**Identidades no Git e no GitHub [Git] [Declarado]:** os commits locais têm uma única identidade declarada
(e-mail pessoal, não reproduzido); há uma segunda identidade, da conta do GitHub, usada em commits feitos
pela interface web. Os remotos `origin` e `pessoal` são das contas do desenvolvedor principal e do
desenvolvedor secundário, as duas com verificação em duas etapas (2FA). O repositório é privado desde a
criação; ninguém mais tem acesso (dois convites a outros integrantes expiraram sem uso).

## Linha do tempo conhecida

| Data | Evento | Fonte |
|---|---|---|
| antes de 06/08/2026 | o projeto era versionado em **pastas locais** no PC do desenvolvedor principal (pasta Documentos), com as datas originais de criação e modificação; não existem em outro lugar | [Declarado]; inventário com hashes: `scripts\seguranca\inventariar_versoes_antigas.bat` |
| 06/08/2026 | repositório criado às 14:58; 22 commits (v0.01 a v3.05) entre 15:03 e 16:01, com 1 a 3 minutos entre eles (importação das versões locais) | [Git] |
| 21/08/2026 | *checkpoint* da ferramenta de IA Cline (`refs/cline/checkpoints/…`) | [Git] |
| 17/09/2026 | `commit --amend` (v3.10 → v3.23) e `reset` do `main` | [Git] |
| 05/10/2026 | primeira auditoria documentada com o Claude no Project (v3.23); cópia do código enviada ao Project | [Relatório] |
| 06/10 a 11/10/2026 | Stages 3 a 7 (endereço da empresa, locais de trabalho, termos, Central administrativa e moderação); v4.00 em 10/10 | [Relatório]; datas das migrações em `migrations/` |
| 12/10/2026 | auditoria integral; Lotes A (recuperação de senha) e B (instalação do banco) gravados; v4.50 | [Relatório] |
| 13/10 a 18/10/2026 | Lotes C a F preparados numa réplica e **não aplicados** ao projeto | [Relatório] |
| 22/10/2026 | v4.51 publicada (v4.50 + Lotes A e B); versão final do TCC (quatro alterações aprovadas); envio e remoção do "Site Aestus" pela web do GitHub | [Declarado], [Relatório], [Git] |
| 24/10/2026 | `filter-branch` reescreveu 4 commits, retirando as anotações `Co-Authored-By`/`Claude-Session` (originais preservados localmente) | [Git] |
| 26/10/2026 | *force push* do `main`; tag `v4.55` recriada; v4.55 e v4.56 | [Git], [Relatório] |
| 27/10/2026 | v4.58 (compatibilidade com PHP 7.4 e 8.0) | [Relatório] |
| 28/10/2026 | v4.59 (Linux) e auditoria de propriedade intelectual | [Relatório] |
| 29/10/2026 | v4.60: correção de segurança e de propriedade intelectual; respostas do responsável registradas | [Relatório] (`RELATORIO_CORRECAO_SEGURANCA.md`) |

As datas do Git mostram **quando os commits foram feitos**, não quando o código foi escrito. As datas das
pastas locais são do sistema de arquivos e podem ser alteradas; o inventário com hashes, enviado a um terceiro
(por exemplo, por e-mail), fixa ao menos que aquele conteúdo existia na data do envio.

## Ferramentas de IA

| Ferramenta | Uso | Período | Evidência | Limitação |
|---|---|---|---|---|
| **ChatGPT** (OpenAI) | início e estrutura inicial do projeto (versões 1.xx); depois, assistente virtual ao longo de todo o desenvolvimento | desde o início | [Declarado] | sem registro das conversas no projeto |
| **Google Gemini** (desenvolvimento) | construção e evolução do código a partir da versão 2.00; também pela extensão Cline, abandonada por consumir créditos sem concluir uma auditoria | da v2.00 em diante; Cline ao menos em 21/08/2026 | [Declarado]; `refs/cline/checkpoints/…` | partes exatas desconhecidas |
| **Claude** (Anthropic) | correção, melhoria e criação de código a partir da versão 3.00; da 3.50 em diante, também os roteiros (scripts) de ações do projeto; no Project "JovemConnect": auditorias, Stages 3 a 7, Lotes A e B, versões 4.5x, documentação de `docs/`, testes numa réplica e a v4.60. Sempre **a pedido e sob decisão do responsável** | da v3.00 em diante (antes de 05/10/2026 numa conta pessoal; documentado no Project desde 05/10/2026) | [Declarado]; relatórios no Project; anotações de coautoria em 4 commits, preservadas nos backups locais (`refs/original`, `backup-v4.55-com-assinatura`); manifesto C2PA em imagens | a parte exata de cada linha não foi medida |
| **Google Gemini** (em execução) | gera textos para os usuários em tempo de uso (análise contextual, assistente, moderação consultiva); não escreve código | em execução | `IA/recomendar.py` | termos da API: ver TER-01 em [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md) |

**Declaração no TCC:** o regulamento da escola não trata de IA; o responsável optou (29/10/2026) por não
incluir declaração no texto do TCC. Este registro interno permanece: ele é parte da prova de autoria e será
necessário num eventual registro do programa. Recomendação mantida: consultar o orientador sobre como tratar
o assunto e responder com a verdade se a banca perguntar.

## Decisões do responsável (registradas)

Decisões técnicas e de produto tomadas pelo responsável, informadas nas sessões de desenvolvimento:

- perfis de jovens **privados por padrão**; o perfil e o currículo só ficam públicos por escolha do próprio
  jovem (aplicado às contas novas na v4.60);
- o repositório do GitHub é **privado desde a criação** (declaração; verificação simples: abrir o endereço
  numa janela anônima deve dar 404);
- a chave antiga do Gemini, que ficou no histórico, foi **revogada** (declaração);
- gravar os Lotes A e B (12/10/2026) e não aplicar os Lotes C a F;
- escopo da versão final do TCC congelado em quatro alterações (validação em tempo real nos cadastros,
  logotipos para os temas claro e escuro, "série" no lugar de "ano" no Ensino Médio, IA Advisor
  aproveitando a formação do cadastro);
- prioridade: versão funcional, demonstrável e compatível com os computadores da escola (PHP mais antigo);
- autorizar, em 28/10/2026, a correção integral de segurança e de propriedade intelectual que gerou a v4.60;
- em 29/10/2026: manter o histórico do Git como está (PI-06); manter a pontuação do Match como está até
  depois da apresentação (PI-07); deixar PI-14, PI-16, SEG-08 e a adequação aos termos do Gemini (TER-01)
  para depois da apresentação; adotar "todos os direitos reservados" ([`LICENSE`](../LICENSE)); pretender
  registrar as marcas e o programa no INPI; manter as fotos atuais por enquanto; manter a cópia antiga do
  código no Project; guardar as evidências no PC do desenvolvedor principal; demonstração com banco novo
  (`schema.sql` atual + `database/seed_demo.sql`) e limpeza dos dados depois da apresentação
  ([APRESENTACAO.md](APRESENTACAO.md)).

## Contribuições de terceiros

Ícones Lucide e Feather, a fonte Inter, bibliotecas Python e PHP, serviços externos e duas fotos de
terceiros sem licença: ver [ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md) e
[`THIRD_PARTY_NOTICES.md`](../THIRD_PARTY_NOTICES.md).

## Limitações de comprovação

- Autor e data dos commits são **declarados** e o histórico já foi reescrito e movido (17/09, 24/10, 26/10).
- Nenhum commit nem tag do autor tem assinatura criptográfica até a v4.59.
- O desenvolvimento anterior a 06/08/2026 depende das pastas locais (uma única cópia, no PC do
  desenvolvedor principal) e de outros registros.
- As anotações de coautoria de IA foram retiradas do histórico principal e só existem nos backups **deste
  computador**.
- O manifesto C2PA das imagens diz que o arquivo passou pelo Claude, não quem criou o desenho.
- Ainda não existem contratos, cessões nem acordos entre integrantes (um contrato está em preparação).
- Os relatórios das sessões estão num serviço de terceiros (claude.ai).

## Evidências que devem ser preservadas

`refs/original/`, `refs/cline/`, os branches `backup-*`, o *reflog*, as tags existentes, as **pastas locais
das versões antigas** (sem abrir, editar ou mover os arquivos, para não mudar as datas), a cópia de
05/10/2026 no Project e os relatórios do Project. Não use `git gc --prune`, `git reflog expire`, reescrita de
histórico nem exclusão de branches e tags sem uma cópia completa com hash guardada fora deste computador
([PROCEDIMENTO_EVIDENCIAS.md](PROCEDIMENTO_EVIDENCIAS.md)).

## A completar pelo responsável

- [ ] Lista nominal da equipe e titularidade: depois do contrato ([TITULARIDADE_E_LICENCA.md](TITULARIDADE_E_LICENCA.md)).
- [ ] Inventário das pastas das versões antigas (`inventariar_versoes_antigas.bat`) e envio do `SHA256SUMS.txt` a um terceiro.
- [ ] Arquivos editáveis dos logotipos (Canva, Photoshop) e fontes usadas ([ORIGEM_DOS_ATIVOS.md](ORIGEM_DOS_ATIVOS.md)).
- [ ] O que é o repositório Git na raiz do `htdocs` (SEG-35).
