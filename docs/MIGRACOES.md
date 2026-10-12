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

## Como aplicar (banco que já existe)

1. Faça um backup: phpMyAdmin → banco `jovemconnect` → **Exportar**.
2. Veja quais já foram aplicadas: na aba **Estrutura** da tabela, procure as
   colunas que a migração cria (ex.: `termos_aceitos_em` em `jovens`; para a
   Stage 7, a tabela `administradores` na lista de tabelas). Se já existem, pule
   aquela migração — rodar de novo dá erro de "coluna duplicada"/"tabela já
   existe" (sem estragar nada).
3. Para cada migração pendente, em ordem: aba **SQL** → cole o conteúdo do
   arquivo → **Executar**.

Cada arquivo traz, em comentário, como desfazer a alteração (isso apaga os dados
daquelas colunas — só use se tiver certeza). A reversão da Stage 7 apaga também
administradores, histórico de auditoria, fila de moderação e denúncias.

Depois da migração da Stage 7, crie o primeiro administrador pela linha de comando:
`C:\xampp\php\php.exe scripts\admin.php criar --email=... --nome="..."` (passo a passo em
[ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md#instalação)). A migração não cria
nenhum administrador nem dá privilégio a contas existentes, e nenhum *seed* é necessário.

## Instalação nova

O `schema.sql` já inclui todas as migrações; não é preciso aplicá-las. Lembre que
o `schema.sql` **recria as tabelas** — nunca o importe num banco com dados. Num teste
da auditoria de 10/10/2026, a reimportação sobre um banco existente apagou 6 tabelas e
depois parou com erro de chave estrangeira (detalhes em
[BANCO_DE_DADOS.md](BANCO_DE_DADOS.md#cuidados-com-o-schemasql)).

## O site sem a migração

O código verifica se as colunas existem: sem a migração de locais, as vagas usam
cidade/UF digitadas; sem a do aceite, o cadastro continua exigindo a caixa de
aceite, mas não grava versão/data; sem a da Stage 7, o site funciona como na v4.00
(sem suspensão de contas, estados de moderação, fila e denúncias — a validação dos
formulários e o bloqueio de código executável continuam valendo) e a Central
administrativa e a linha de comando avisam que a migração está pendente.
