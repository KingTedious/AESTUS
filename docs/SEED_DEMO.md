# Dados de demonstração (`database/seed_demo.sql`)

Cria dados **fictícios** para apresentar e testar a plataforma:

- 15 empresas, uma em cada UF abaixo, cobrindo as 5 regiões: AM, PA, TO, PE, BA,
  CE, GO, DF, MS, SP, MG, RJ, PR, RS, SC;
- 19 vagas (Jovem Aprendiz e Estágio) com requisitos e benefícios; 2 locais de
  trabalho extras (uma filial e uma unidade) e uma vaga num desses locais;
- 15 jovens com formações, áreas, habilidades e experiências variadas (um com
  perfil não visível para empresas);
- 15 candidaturas.

## Contas

- E-mail: `<nome>@demo.jovemconnect.example` — por exemplo `joao@demo.jovemconnect.example`
  (jovem) e `planalto@demo.jovemconnect.example` (empresa). A lista completa está
  no próprio arquivo (procure por `@demo.jovemconnect.example`).
- Senha de **todas** as contas de demonstração: `Demo@2026`.
- As senhas estão gravadas como *hash* (`password_hash` do PHP), como nas contas
  reais. Os e-mails já vêm verificados para entrar direto.
- CPFs e CNPJs têm dígitos verificadores inválidos de propósito e o domínio
  `.example` é reservado: nada corresponde a pessoas ou empresas reais.

## Como aplicar

Pré-requisito: banco criado pelo `schema.sql` atual **ou** banco antigo com as
migrações aplicadas ([MIGRACOES.md](MIGRACOES.md)).

phpMyAdmin → banco `jovemconnect` → aba **SQL** → cole o conteúdo de
`database/seed_demo.sql` → **Executar** (ou `mysql -u root jovemconnect < database/seed_demo.sql`).

- Pode rodar mais de uma vez: cada registro só é inserido se ainda não existir
  (conta pelo e-mail; vaga pelo título dentro da empresa; e assim por diante).
- Não apaga nem altera nada que já está no banco e não usa IDs fixos.
- Testado num banco vazio (criado pelo `schema.sql`) e num banco já com dados,
  executando duas vezes em cada.

**Não use em produção** (a senha é pública). Para remover, exclua as contas de
demonstração pelo próprio site (Perfil → Excluir conta) ou, no phpMyAdmin, apague
as linhas de `jovens` e `empresas` com e-mail `@demo.jovemconnect.example` — o
banco remove em cascata o que for ligado a elas.

Para regenerar o arquivo (mudar textos ou quantidades), edite os dados no script
gerador usado na Stage 6 e gere de novo; o SQL resultante é o que fica no projeto.
