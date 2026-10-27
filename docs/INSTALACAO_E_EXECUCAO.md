# Instalação e execução

> Guia para quem nunca executou o projeto, escrito a partir dos arquivos reais (`README.md`, `iniciar_ia.bat`,
> `schema.sql`, `migrations/`, `.env.example`). Ambiente de referência: **Windows + XAMPP**. Os passos de
> Windows/XAMPP **não foram executados nesta auditoria** (o ambiente de testes foi uma réplica em Linux); os
> comandos citados são os que estão no projeto.

## Início rápido (v4.56)

Num computador com XAMPP e o projeto em `C:\xampp\htdocs\<pasta>`:

1. Dê dois cliques em **`iniciar_jovemconnect.bat`**. Ele confere o PHP, oferece criar o `.env`, liga o Apache e o
   MySQL do XAMPP, oferece criar o banco com o `schema.sql` (só se ele não existir ou estiver vazio) e os dados de
   demonstração, e por fim chama o `iniciar_ia.bat` (IA e navegador). Tudo o que cria, ele pergunta antes.
2. Ao terminar, dê dois cliques em **`parar_jovemconnect.bat`**. Ele fecha a IA desta pasta e pergunta, um de cada
   vez, se deve apagar o banco do XAMPP, desligar o XAMPP e apagar a pasta do projeto do `htdocs` (útil em
   computadores emprestados, como os da escola). Para apagar o banco ou a pasta, é preciso digitar o nome (maiúsculas
   tanto faz); antes de apagar a pasta, ele avisa sobre alterações do Git que ainda não foram enviadas. A exclusão
   acontece numa janela "JovemConnect - limpeza", que mostra o resultado.
3. Se a checagem do PHP não bater (por exemplo, um XAMPP antigo), o `iniciar_jovemconnect.bat` mostra o motivo e
   pergunta se continua mesmo assim.

Os passos abaixo explicam o que esses scripts fazem e como fazer tudo à mão.

## 1. Preparar o ambiente

1. Instale o **XAMPP** (Apache + MariaDB + PHP). Recomendado: PHP **8.1 ou mais novo**; desde a v4.58 o projeto também
   roda no **7.4 e no 8.0** (modo de compatibilidade, para computadores com XAMPP antigo, como os da escola; ver
   [REQUISITOS.md](REQUISITOS.md#versões)).
2. Instale o **Python 3.10+** marcando "Add python.exe to PATH" (só para os recursos de IA; o site funciona sem ele).
3. Opcional: **Composer**, só se for usar e-mail (PHPMailer) ou SMS (Twilio).

## 2. Copiar o projeto e instalar dependências

1. Coloque a pasta em `C:\xampp\htdocs\` — por exemplo `C:\xampp\htdocs\JovemConnect`. O endereço do site
   será `http://localhost/<nome-da-pasta>/`.
2. Opcional (e-mail/SMS), na pasta do projeto: `composer install` — cria `vendor/` (não versionada).
   **Sem isso, verificação de e-mail, verificação de telefone e recuperação de senha não enviam nada.**
3. Dependências Python: na primeira vez em cada computador, o `iniciar_ia.bat` avisa que o `venv` não pode ser
   usado e, se você responder **S**, cria o `venv` deste computador com as versões fixas de
   `IA/requirements-ia.txt` (precisa de internet uma vez). Não copie a pasta `venv` de um computador para outro.
   Não é preciso instalar o `requirements.txt` inteiro — ele lista pacotes que o código não usa (`pandas`,
   `scikit-learn`, `mysql-connector-python`).

## 3. Configurar o banco de dados

Inicie **Apache** e **MySQL** no painel do XAMPP e abra o phpMyAdmin (`http://localhost/phpmyadmin`).

| Script | Para quê | Altera dados? | Quando usar |
|---|---|---|---|
| `schema.sql` | cria as 20 tabelas (15 + 5 da Stage 7); **nenhum registro** (sem contas de exemplo, sem administrador) | **Não** — não tem `DROP`, `DELETE` nem `INSERT`; trava "só banco vazio": em banco com qualquer tabela, para logo no início e nada é criado nem alterado | **somente em banco novo e vazio** |
| `database/diagnostico_somente_leitura.sql` | mostra quais migrações estão aplicadas, pendentes ou parciais, quantos registros receberão colunas novas e confere backups (estrutura, registros por tabela, integridade) | **Não** — só consulta | antes de atualizar, depois de uma falha e para conferir backup/restauração |
| `migrations/2026-10-06_stage3_endereco_empresa.sql` | colunas de endereço em `empresas` | só adiciona colunas | banco já existente, uma vez |
| `migrations/2026-10-08_stage51_locais_trabalho.sql` | tabela `empresa_locais` + colunas em `vagas` (requer a anterior) | só adiciona | banco existente, uma vez |
| `migrations/2026-10-10_stage6_aceite_termos.sql` | colunas do aceite dos termos | só adiciona | banco existente, uma vez |
| `migrations/2026-10-11_stage7_admin_moderacao.sql` | Central administrativa, moderação, denúncias e auditoria (5 tabelas + colunas com padrão "ativa"/"aprovada") | só adiciona | banco existente, uma vez |
| `database/seed_demo.sql` | contas e vagas fictícias de demonstração | **insere dados** (não apaga) | só para apresentação/testes; nunca em produção |

### 4. Instalação nova (banco vazio)

1. phpMyAdmin → **Novo** → nome `jovemconnect` → colação `utf8mb4_unicode_ci` → Criar.
2. Banco `jovemconnect` → **Importar** → `schema.sql` → Executar.
3. **Não** aplique as migrações: o `schema.sql` já as contém.
4. (Opcional — Central administrativa) crie o primeiro administrador pela linha de comando, na pasta do projeto:
   `C:\xampp\php\php.exe scripts\admin.php criar --email=voce@exemplo.com --nome="Seu Nome"`.
   Guarde a senha e cadastre a chave do autenticador mostradas (aparecem uma vez só). Detalhes em
   [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md#instalação).

> O `schema.sql` **não serve para atualizar** e tem uma trava "só banco vazio": num banco que já tem
> **qualquer** tabela (do JovemConnect ou de outro sistema), a importação para logo no início com
> `#1146 - Table '…PARE - use num banco NOVO e VAZIO - nada foi alterado' doesn't exist` e **nada é criado nem
> alterado** — inclusive em ferramentas que continuam depois de erros (ex.: `mysql --force`). Se aparecer esse
> erro, pare e siga a seção 5. Importe sempre o arquivo **inteiro** (trechos soltos não fazem nada). Até a v4.50
> o arquivo começava apagando tabelas e, reimportado sobre um banco com dados, apagava 6 tabelas e parava no
> meio (SEG-11/AUD-002); por isso **nunca use uma cópia antiga** do `schema.sql`. Faça backup (aba
> **Exportar**) antes de qualquer script.
>
> **Reinstalar do zero** apaga tudo e é sempre uma decisão consciente: exporte um backup, exclua o banco
> inteiro (Operações → Excluir o banco de dados) ou use um banco com outro nome, crie-o vazio e importe o
> `schema.sql`. Se a importação parar no meio num banco **novo**, exclua esse banco recém-criado e comece de
> novo (comandos de estrutura não são desfeitos automaticamente, e a trava passa a recusar esse banco).

### 5. Banco que já existe (atualização)

1. **Backup verificado** — passo a passo em [MIGRACOES.md](MIGRACOES.md#backup-verificado-e-restauração):
   exportar com o site parado, copiar `uploads\curriculos`, importar a cópia num banco de teste e comparar as
   Consultas 4, 5 e 6 do diagnóstico nos dois bancos (estrutura, registros e impressão de **cada** tabela,
   integridade das relações). Só siga se tudo for igual.
2. Diagnóstico: aba **SQL** → cole a Consulta 1 de `database/diagnostico_somente_leitura.sql` → Executar.
   Ela não altera nada e mostra, para cada migração, `aplicada`, `PENDENTE` ou `PARCIAL` (a Consulta 2 mostra
   quantos registros receberão colunas novas). Se alguma linha de "Base" aparecer como `FALTANDO`, pare: não
   há migração pronta para esse caso.
3. Para cada migração `PENDENTE`, **na ordem da tabela acima**: aba **SQL** → cole o arquivo → Executar.
   Depois, rode o diagnóstico de novo: tudo deve estar `aplicada`.
4. Rodar de novo uma migração **já aplicada** dá erro "coluna duplicada"/"já existe" sem estragar dados. Mas se
   uma migração **falhar no meio** (`PARCIAL`), rodar o arquivo inteiro de novo **não** completa o que faltou:
   siga [MIGRACOES.md](MIGRACOES.md#se-uma-migração-falhar-no-meio).
5. Depois da migração da Stage 7, crie o primeiro administrador como no passo 4 acima. A migração não cria
   administradores nem dá privilégio a nenhuma conta existente.

Detalhes: [MIGRACOES.md](MIGRACOES.md). Dados de demonstração (opcional): [SEED_DEMO.md](SEED_DEMO.md).

## 6. Variáveis de ambiente

1. Copie `.env.example` para `.env` na raiz do projeto (mesma pasta do `index.php`).
2. Preencha só o que for usar (todas têm padrão ou são opcionais):
   - `DB_*` — com o XAMPP padrão (`root` sem senha, banco `jovemconnect`) nada precisa ser preenchido.
   - `SMTP_*` — e-mail; `TWILIO_*` — SMS; `GEMINI_API_KEY` — IA.
3. O `.env` nunca deve ir para o Git (já está no `.gitignore`).

Lista completa: [CONFIGURACAO.md](CONFIGURACAO.md).

## 7. Configurar o serviço de IA

1. Para usar o Gemini, gere uma chave **própria** no Google AI Studio e coloque em `GEMINI_API_KEY=` no `.env`.
2. Sem chave, o Flask funciona do mesmo jeito: o Match é calculado por regras e o assistente usa respostas prontas.

## 8. Iniciar os serviços

1. XAMPP: **Apache** e **MySQL** em "Running".
2. Dê dois cliques em `iniciar_ia.bat` (ou use o `iniciar_jovemconnect.bat`, que faz o passo 1 e chama este). Ele:
   - usa **só** o ambiente oficial `venv` da pasta do projeto (nunca o Python global nem `venv-<NOME-DO-PC>`);
   - se o `venv` não existir ou tiver vindo de outro computador, para e explica; com **S**, guarda o antigo como
     `venv.antigo-<data>` e cria um novo com as versões fixas de `IA/requirements-ia.txt`;
   - se a IA desta pasta já estiver no ar, reaproveita-a em vez de abrir outra;
   - abre a janela **"JovemConnect - IA"** com `IA\recomendar.py` (fechar a janela desliga a IA) e espera a IA
     confirmar a porta (5000 ou a próxima livre de 5001–5010, gravada em `logs\ia_porta.json`);
   - abre `http://localhost/<pasta>/index.php` no navegador;
   - grava o diagnóstico em `logs\iniciar_ia.log`.
3. Sem o Windows: `venv/bin/python IA/recomendar.py` na pasta do projeto (comando equivalente ao que o `.bat` executa).

## 9. Endereços locais

| Endereço | O quê |
|---|---|
| `http://localhost/JovemConnect/` | site (troque pelo nome da sua pasta) |
| `http://localhost/phpmyadmin` | administração do banco (XAMPP) |
| `http://127.0.0.1:5000/ping` | estado do Flask (não mostra a chave); se a 5000 estiver ocupada, a porta em uso está em `logs/ia_porta.json` |
| `http://localhost/JovemConnect/admin/login.php` | Central administrativa (Stage 7; exige a migração e um administrador criado pela linha de comando) |

## 10. Verificar o funcionamento

| Verificação | Esperado |
|---|---|
| Abrir o site | página inicial com estilo (se aparecer sem CSS, veja "Problemas comuns") |
| `http://127.0.0.1:5000/ping` | JSON com `"status":"ok"`; `"assistente": true` só se houver chave |
| Criar uma conta de jovem de teste | cai na tela "Como deseja verificar sua conta?" |
| Clicar "Match IA" numa vaga | aparece "Match: N%" (com ou sem Flask) |
| Abrir o assistente ✦ e perguntar algo | resposta da IA ou resposta pronta com aviso |
| `http://localhost/JovemConnect/logs/app.log` | **403** (pasta protegida) |
| `http://localhost/JovemConnect/.env` | **deveria dar 403/404** — se mostrar o conteúdo, ver SEG-04 |
| `C:\xampp\php\php.exe scripts\admin.php status` | "Migração da Stage 7: aplicada" e a contagem de administradores |
| `http://localhost/JovemConnect/scripts/admin.php` | **403/404** (a linha de comando não roda pelo navegador) |
| Entrar na Central e clicar "Histórico" → "Conferir integridade agora" | "Histórico íntegro" |
| `logs/app.log` no disco | linhas JSON novas |

## 11. Encerrar

1. Feche a janela **"JovemConnect - IA"** (para o Flask).
2. No XAMPP, **Stop** em Apache e MySQL.

## 12. Problemas comuns

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| "O sistema está temporariamente indisponível" | MySQL parado ou `DB_*` errado | iniciar o MySQL; conferir `.env`; ver `banco.indisponivel` em `logs/app.log` |
| "Ocorreu um erro inesperado" | exceção (ex.: banco antigo sem uma coluna) | ver `erro.inesperado` em `logs/app.log` (`errno`/`sqlstate`); aplicar as migrações pendentes |
| `iniciar_jovemconnect.bat`: "alguma verificacao nao bateu" | PHP antigo, extensão desligada ou pasta sem permissão | ler a linha marcada; responder **S** para continuar mesmo assim (PHP 7.4/8.0 já passa como compatibilidade) ou **N** para parar |
| `parar_jovemconnect.bat`: a pasta não some | algum programa usa arquivos dela (janela da IA, editor, terminal aberto nela) | a janela "JovemConnect - limpeza" lista o que sobrou: feche o programa e responda **S** para tentar de novo; diagnóstico em `%TEMP%\jovemconnect_limpeza.log` |
| Página sem estilo | servidor diferencia maiúsculas (`Assets/` × `assets/`) | ver [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md#publicar-fora-do-xampp) |
| E-mail/SMS não chega | sem `composer install` ou credenciais | `composer install`; preencher `SMTP_*`/`TWILIO_*`; ver o `error_log` do PHP (`C:\xampp\php\logs` ou `C:\xampp\apache\logs\error.log`, conforme o php.ini) |
| `iniciar_ia.bat`: "o ambiente oficial (venv) nao pode ser usado" | `venv` ausente ou criado em outro PC | responder **S** para reconstruir o `venv` deste PC (o anterior vira `venv.antigo-<data>`) |
| `iniciar_ia.bat`: "nenhum Python 3.10+ utilizavel" | Python ausente do PATH | instalar o Python 3.13 marcando "Add python.exe to PATH" |
| `iniciar_ia.bat`: "a instalacao das dependencias falhou" | sem internet/proxy na reconstrução | conectar e rodar de novo (o backup é mantido) |
| `iniciar_ia.bat`: "a IA nao confirmou a porta" | nenhuma porta livre na faixa ou erro na IA | ver a janela "JovemConnect - IA" e `logs/iniciar_ia.log`; liberar uma porta ou ajustar `JC_IA_PORTAS_EXTRAS` |
| `/ping` não abre | Flask fechado, ou rodando em outra porta (5000 ocupada) | a porta em uso está em `logs/ia_porta.json` e na janela da IA; reabrir o `.bat` |
| `"assistente": false` com chave no `.env` | Flask iniciado antes de editar o `.env` | fechar a janela do Flask e rodar o `.bat` de novo |
| Análise contextual sempre "não disponível" | chave inválida, modelo inexistente, limite | ver `gemini.falha` em `logs/ia.log` (`motivo`) |
| "Sua sessão expirou ou a página está desatualizada" | token CSRF de outra sessão | recarregar a página |

Mais procedimentos: [LOGS_E_DIAGNOSTICO.md](LOGS_E_DIAGNOSTICO.md).

## Executar sem integrações externas

O projeto funciona **sem nenhuma** integração opcional:

| Deixe vazio / não instale | Resultado |
|---|---|
| `GEMINI_API_KEY` | Match por regras, assistente com respostas prontas, sem análise contextual |
| Python/Flask | igual ao anterior; o IA Advisor do perfil mostra "IA temporariamente indisponível." |
| `composer install`, `SMTP_*`, `TWILIO_*` | cadastro e login funcionam; verificação é opcional; **recuperação de senha não funciona** |
| internet no navegador | CEP/CNPJ preenchidos à mão; fonte padrão do sistema |
