# Migrações do banco

Cada arquivo de `migrations/` é uma alteração **aditiva** (só cria colunas ou
tabelas; não apaga nem altera dados). O nome começa com a data, que é a ordem de
aplicação.

| Arquivo | O que faz |
|---|---|
| `2026-10-06_stage3_endereco_empresa.sql` | Endereço completo da empresa (`cep`, `logradouro`, `numero`, `complemento`, `bairro`) |
| `2026-10-08_stage51_locais_trabalho.sql` | Tabela `empresa_locais` e local de trabalho da vaga (`vagas.local_trabalho`, `vagas.local_id`) |
| `2026-10-10_stage6_aceite_termos.sql` | Versão e data do aceite dos Termos/Privacidade (`termos_versao`, `termos_aceitos_em` em `jovens` e `empresas`) |

## Como aplicar (banco que já existe)

1. Faça um backup: phpMyAdmin → banco `jovemconnect` → **Exportar**.
2. Veja quais já foram aplicadas: na aba **Estrutura** da tabela, procure as
   colunas que a migração cria (ex.: `termos_aceitos_em` em `jovens`). Se já
   existem, pule aquela migração — rodar de novo dá erro de "coluna duplicada"
   (sem estragar nada).
3. Para cada migração pendente, em ordem: aba **SQL** → cole o conteúdo do
   arquivo → **Executar**.

Cada arquivo traz, em comentário, como desfazer a alteração (isso apaga os dados
daquelas colunas — só use se tiver certeza).

## Instalação nova

O `schema.sql` já inclui todas as migrações; não é preciso aplicá-las. Lembre que
o `schema.sql` **recria as tabelas** — nunca o importe num banco com dados.

## O site sem a migração

O código verifica se as colunas existem: sem a migração de locais, as vagas usam
cidade/UF digitadas; sem a do aceite, o cadastro continua exigindo a caixa de
aceite, mas não grava versão/data.
