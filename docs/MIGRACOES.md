# Migrações do banco

Cada arquivo de `migrations/` é uma alteração **aditiva** (só cria colunas ou
tabelas; não apaga nem altera dados). O nome começa com a data, que é a ordem de
aplicação.

| Arquivo | O que faz |
|---|---|
| `2026-10-06_stage3_endereco_empresa.sql` | Endereço completo da empresa (`cep`, `logradouro`, `numero`, `complemento`, `bairro`) |
| `2026-10-08_stage51_locais_trabalho.sql` | Tabela `empresa_locais` e local de trabalho da vaga (`vagas.local_trabalho`, `vagas.local_id`) |
| `2026-10-10_stage6_aceite_termos.sql` | Versão e data do aceite dos Termos/Privacidade (`termos_versao`, `termos_aceitos_em` em `jovens` e `empresas`) |
| `2026-10-11_stage7_admin_moderacao.sql` | Central administrativa e moderação: tabelas `administradores` (nasce **vazia**), `admin_tentativas`, `admin_auditoria`, `moderacao_itens`, `denuncias`; colunas `conta_status`/`conta_status_em` em `jovens` e `empresas` (padrão `ativa`) e `moderacao_status`/`moderacao_motivo`/`moderacao_em` em `vagas` (padrão `aprovada`) — ver [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md) |

## Instalação nova × atualização

| Situação | O que usar | O que **não** usar |
|---|---|---|
| Banco novo e vazio | `schema.sql` (já inclui as 4 migrações) | as migrações (não são necessárias) |
| Banco que já tem o sistema | as migrações **pendentes**, em ordem | o `schema.sql` (a trava "só banco vazio" recusa qualquer banco com tabelas: não altera nada, mas também não atualiza) |

## Como aplicar (banco que já existe)

1. **Backup verificado** — seção [Backup verificado e restauração](#backup-verificado-e-restauração) abaixo.
   Só siga se a cópia de teste conferir em tudo (Consultas 4, 5 e 6 iguais e sem órfãos).
2. **Diagnóstico (só leitura).** Aba **SQL** → cole a Consulta 1 de `database/diagnostico_somente_leitura.sql`
   → **Executar**. Para cada migração aparece `aplicada`, `PENDENTE` ou `PARCIAL`; a Consulta 2 mostra quantos
   registros de `jovens`, `empresas` e `vagas` receberão colunas novas (com valor padrão; nenhum valor existente
   muda). Se uma linha de "Base" aparecer `FALTANDO`, **pare**: o banco é anterior a estas migrações e não há
   arquivo pronto para isso. (Também dá para conferir à mão na aba **Estrutura**, como antes.)
3. Para cada migração `PENDENTE`, **em ordem de data**: aba **SQL** → cole o conteúdo do arquivo → **Executar**.
   Se aparecer qualquer erro, **pare** (não siga para a próxima) e veja a seção abaixo.
4. Rode o diagnóstico de novo: todas as linhas devem estar `aplicada`.

Rodar de novo uma migração **já aplicada** dá erro "coluna duplicada"/"tabela já existe" e não altera nada
(testado na réplica, modo phpMyAdmin e modo `--force`).

Cada arquivo traz, em comentário, como desfazer a alteração (isso apaga os dados
daquelas colunas — só use se tiver certeza). A reversão da Stage 7 apaga também
administradores, histórico de auditoria, fila de moderação e denúncias.

Depois da migração da Stage 7, crie o primeiro administrador pela linha de comando:
`C:\xampp\php\php.exe scripts\admin.php criar --email=... --nome="..."` (passo a passo em
[ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md#instalação)). A migração não cria
nenhum administrador nem dá privilégio a contas existentes, e nenhum *seed* é necessário.

## Se uma migração falhar no meio

Comandos de estrutura (`CREATE`/`ALTER`) não são desfeitos por transação no MySQL/MariaDB: se a execução parar
no meio (erro, queda do XAMPP, tempo esgotado), os comandos anteriores **ficam aplicados**. O diagnóstico
mostra a migração como `PARCIAL`.

- **Não rode o arquivo inteiro de novo**: ele para no primeiro comando que já foi aplicado e os que faltam
  continuam sem rodar (confirmado na réplica com a Stage 7).
- **Opção A — voltar ao backup** (a mais segura): anote a mensagem de erro; restaure o backup verificado do
  passo 1 como em [Restaurar sem apagar nada](#restaurar-sem-apagar-nada); resolva a causa do erro; aplique de
  novo.
- **Opção B — completar o que falta**: com o diagnóstico e a aba **Estrutura**, identifique os comandos do
  arquivo que ainda não rodaram e execute **só esses**, um por vez, na ordem do arquivo. Use quando a causa
  foi externa (queda, tempo esgotado) e não um erro do próprio comando.

Em qualquer opção, nenhum registro existente é apagado ou alterado pelas migrações; o que fica pela metade é a
estrutura.

## Backup verificado e restauração

Contar só alguns registros não prova que um backup está completo: na réplica, um arquivo **cortado no meio**
foi importado **sem nenhum erro**, e um backup com **um único valor alterado** tinha exatamente as mesmas
quantidades. Por isso a conferência compara a estrutura, a quantidade e uma "impressão" do conteúdo de
**cada** tabela, e os registros órfãos de **cada** chave estrangeira, com as Consultas 4, 5 e 6 de
`database/diagnostico_somente_leitura.sql` (todas só leem).

### Fazer o backup

1. **Pare o uso do site** (ninguém cadastrando, candidatando ou moderando): um backup feito com o site em uso
   pode sair inconsistente (registros ligados a outros que não entraram no arquivo).
2. phpMyAdmin → banco `jovemconnect` → **Exportar** → método **Personalizado** → formato SQL → saída em
   arquivo, com estrutura **e** dados e com o valor de `AUTO_INCREMENT` (para que ids de contas apagadas não
   voltem a ser usados). Pelo terminal, a alternativa é
   `C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --default-character-set=utf8mb4 jovemconnect > C:\backup\jovemconnect_AAAA-MM-DD.sql`.
3. Copie também a pasta `uploads\curriculos` (os PDFs ficam fora do banco).
4. Guarde o backup **fora** da pasta do projeto: ele tem dados pessoais e não pode ir para o Git nem ficar na
   pasta servida pelo Apache.

### Conferir o backup (antes de qualquer migração)

1. Crie um banco **vazio** de teste, por exemplo `jovemconnect_teste_backup` (`utf8mb4_unicode_ci`), e
   **importe** o arquivo nele. A importação precisa terminar **sem nenhum erro**.
2. Rode as Consultas 4, 5 e 6 no banco **original** e no de **teste**, do mesmo jeito, e compare:

| Consulta | O que mostra | O backup só vale se… |
|---|---|---|
| 4 — estrutura | quantidade de tabelas, colunas, índices e chaves estrangeiras | os quatro números forem iguais |
| 5 — registros e impressão | para **cada** tabela: quantidade de registros e um número de conferência do conteúdo | todas as linhas forem iguais (mesmas tabelas, mesmos registros, mesma impressão) |
| 6 — integridade | para **cada** chave estrangeira: registros que apontam para um registro que não existe | todas as linhas derem **0** nos dois bancos |

3. **Guarde os resultados** das três consultas (anote ou use "Exportar" no resultado): são a referência para
   conferir uma restauração futura.
4. Se qualquer coisa for diferente, o backup **não vale**: exporte de novo (com o site parado) e repita. Não
   siga para a migração.
5. **Conferência funcional da cópia** (recomendada): no `.env`, defina temporariamente
   `DB_NAME=jovemconnect_teste_backup`, faça a [lista de conferência](#lista-de-conferência-funcional) e
   **volte** `DB_NAME` ao valor de antes. Faça isso **depois** das consultas, porque entrar no site grava
   registros novos (último login, histórico).

### Restaurar sem apagar nada

Para voltar ao backup depois de uma migração que deu errado (ou de qualquer outro problema no banco):

1. Pare o uso do site.
2. Crie um banco **novo e vazio** (ex.: `jovemconnect_restaurado`, `utf8mb4_unicode_ci`) e importe nele o
   **backup verificado**. O banco com problema fica como está, para análise: nada é apagado.
3. Rode as Consultas 4, 5 e 6 no banco restaurado e compare com os resultados **guardados** na conferência do
   backup: tudo igual e a Consulta 6 toda em **0**.
4. Se a pasta `uploads\curriculos` também foi afetada, restaure a cópia dela.
5. No `.env`, defina `DB_NAME=jovemconnect_restaurado` (o site passa a usar o banco restaurado) e faça a lista
   de conferência abaixo.
6. Só depois de tudo conferido decida, com calma, o que fazer com o banco antigo (ele pode ficar como
   evidência).

### Lista de conferência funcional

- o site abre sem mensagem de erro;
- um jovem conhecido entra; "Minhas candidaturas" mostra as mesmas vagas de antes; o currículo PDF dele abre;
- uma empresa conhecida entra; "Minhas vagas" mostra as mesmas vagas; a página de candidatos de uma vaga abre;
- o administrador entra (senha + código do autenticador); Contas abre; em Histórico, **Conferir integridade**
  mostra "Histórico íntegro" (se o backup for mais antigo que o log, os registros mais novos citados no log
  aparecem como ausentes — esperado, ver [LOGS_E_DIAGNOSTICO.md](LOGS_E_DIAGNOSTICO.md));
- a Consulta 1 do diagnóstico mostra a mesma situação de antes (`ok`/`aplicada`).

Testado na réplica (12/10/2026): dois formatos de backup restaurados e conferidos sem nenhuma diferença; quatro
backups defeituosos (cortado no meio, sem os registros de uma tabela, com um valor alterado, e inconsistente
com registros órfãos) — todos acusados pelas consultas; o site funcionou sobre o banco restaurado. O
"Exportar" do phpMyAdmin e o `mysqldump` do XAMPP **não** foram executados nesse teste.

## Instalação nova

O `schema.sql` já inclui todas as migrações; não é preciso aplicá-las. Ele serve
**só** para banco novo e vazio: não tem `DROP`, `DELETE` nem `INSERT`, e uma trava no
início confere se o banco está vazio — em qualquer banco com tabelas, para ali e nenhum
comando de estrutura é executado (nem em ferramentas que continuam depois de erros).
Até a v4.50 o arquivo apagava tabelas ao ser reimportado (SEG-11/AUD-002; detalhes em
[BANCO_DE_DADOS.md](BANCO_DE_DADOS.md#cuidados-com-o-schemasql)) — nunca use uma cópia antiga.

## O site sem a migração

O código verifica se as colunas existem: sem a migração de locais, as vagas usam
cidade/UF digitadas; sem a do aceite, o cadastro continua exigindo a caixa de
aceite, mas não grava versão/data; sem a da Stage 7, o site funciona como na v4.00
(sem suspensão de contas, estados de moderação, fila e denúncias — a validação dos
formulários e o bloqueio de código executável continuam valendo) e a Central
administrativa e a linha de comando avisam que a migração está pendente.
