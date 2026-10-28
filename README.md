# JovemConnect

Plataforma de empregabilidade que conecta jovens em busca do primeiro emprego
(Jovem Aprendiz, estágio e vagas iniciais) a empresas. Projeto acadêmico (TCC).

- **Jovens**: cadastro em etapas, perfil com formação, habilidades, experiências
  e currículo em PDF, lista de vagas com filtros, favoritos, candidaturas e nota
  de compatibilidade (Match) com cada vaga.
- **Empresas**: cadastro em etapas, endereço principal e outros locais de
  trabalho, publicação de vagas, lista de candidatos de cada vaga (com status) e
  perfil do candidato.
- **IA (opcional)**: o Match é calculado por regras fixas; quando há uma chave do
  Google Gemini configurada, a plataforma também gera a análise explicativa do
  Match e o Assistente JovemConnect responde com IA generativa. Sem chave, tudo
  continua funcionando (Match tradicional e respostas prontas do assistente).
- **Central administrativa** (`admin/`): busca e correção de contas e vagas,
  suspensão reversível, moderação de vagas, fila de sinalizações e denúncias,
  histórico auditável e análise de conteúdo por IA só como orientação. Os
  administradores são criados **somente pela linha de comando**
  (`scripts/admin.php`), com senha e autenticador (veja
  [docs/ADMINISTRACAO_E_MODERACAO.md](docs/ADMINISTRACAO_E_MODERACAO.md)).

## Tecnologias

| Parte | Tecnologia |
|---|---|
| Site | PHP 8.1+ (também roda no 7.4 e no 8.0, em modo de compatibilidade), mysqli, sessões, HTML, CSS e JavaScript sem frameworks |
| Banco | MariaDB / MySQL |
| IA | Python 3 + Flask (`IA/recomendar.py`) e Google Gemini (opcional, via API REST) |
| E-mail / SMS | PHPMailer (SMTP) e Twilio, via Composer |
| Ambiente local | XAMPP (Apache + MariaDB) no Windows; no Linux, XAMPP para Linux ou Apache + MariaDB do sistema |

## Como instalar (XAMPP)

**Jeito rápido (computador novo, escola, apresentação):** com a pasta em
`C:\xampp\htdocs\`, dê dois cliques em **`iniciar_jovemconnect.bat`**. Ele confere o PHP (se algo
não bater, como um XAMPP antigo, mostra o motivo e pergunta se continua mesmo assim),
liga o Apache e o MySQL do XAMPP (se ainda não estiverem ligados), pergunta se pode
criar o banco e instalar o `schema.sql` (só em banco vazio; oferece os dados de
demonstração), inicia a IA e abre o site. Para desligar, use
**`parar_jovemconnect.bat`**: ele fecha a IA e **pergunta** se deve apagar o banco do
projeto, desligar o XAMPP e excluir a pasta deste computador (cada exclusão pede
`S` e que você digite o nome; maiúsculas tanto faz). Não use a limpeza no seu computador principal.
No **Linux**, use `bash iniciar_jovemconnect.sh` e `bash parar_jovemconnect.sh` na pasta do projeto (com a
pasta em `/opt/lampp/htdocs/` ou `/var/www/html/`); veja `docs/INSTALACAO_E_EXECUCAO.md`.
Os passos abaixo são o jeito manual.

1. **Arquivos** — coloque a pasta do projeto em `C:\xampp\htdocs\` (ex.:
   `C:\xampp\htdocs\JovemConnect`) e inicie Apache e MySQL no XAMPP.
2. **Dependências PHP** — na pasta do projeto: `composer install`
   (PHPMailer e Twilio; só necessários para e-mail e SMS).
3. **Configuração** — copie `.env.example` para `.env` e preencha
   (veja [docs/CONFIGURACAO.md](docs/CONFIGURACAO.md)). O `.env` nunca vai para o Git.
4. **Banco de dados**
   - *Instalação nova*: no phpMyAdmin, crie o banco `jovemconnect` **vazio** e
     importe `schema.sql`. Ele só cria as tabelas: não apaga nada e não grava
     nenhum registro (nem contas de exemplo). Tem uma trava "só banco vazio": em
     qualquer banco que já tenha alguma tabela, a importação para logo no início
     (`… PARE - use num banco NOVO e VAZIO - nada foi alterado`) e nada é criado
     nem alterado — também em ferramentas que continuam depois de erros.
   - *Banco que já existe*: **não** use o `schema.sql`. Faça um backup verificado,
     rode `database/diagnostico_somente_leitura.sql` (só consulta) para ver o que
     falta e aplique, em ordem, só as migrações de `migrations/` pendentes
     (veja [docs/MIGRACOES.md](docs/MIGRACOES.md)).
   - *Dados de demonstração (opcional)*: `database/seed_demo.sql`
     (veja [docs/SEED_DEMO.md](docs/SEED_DEMO.md)).
5. **Servidor de IA** — dê dois cliques em `iniciar_ia.bat`. Ele usa **só** o
   ambiente oficial `venv` da pasta do projeto (nunca o Python global nem outro
   ambiente), confere se ele funciona neste computador e abre o site no navegador.
   Se o `venv` estiver ausente ou tiver vindo de outro PC, o `.bat` para, explica
   e, se você responder **S**, reconstrói o `venv` deste computador com as versões
   fixas de `IA/requirements-ia.txt` (precisa de internet nessa vez), guardando o
   anterior como `venv.antigo-<data>`. **Não copie a pasta `venv` entre
   computadores.** Diagnóstico em `logs/iniciar_ia.log`.
   A IA usa a porta 5000; se outro programa estiver nela, escolhe a primeira livre
   entre 5001 e 5010 (configurável no `.env`: `JC_IA_PORTA`, `JC_IA_PORTAS_EXTRAS`)
   e grava em `logs/ia_porta.json` — o site lê dali, sem configurar nada.
   Teste: <http://127.0.0.1:5000/ping> (ou a porta mostrada pelo `.bat`).
6. Acesse `http://localhost/JovemConnect/` (o nome da pasta do projeto).
7. **Central administrativa (opcional)** — com a migração da Stage 7 aplicada (ou
   instalação nova), crie o primeiro administrador no terminal, na pasta do projeto:
   `C:\xampp\php\php.exe scripts\admin.php criar --email=voce@exemplo.com --nome="Seu Nome"`
   e entre em `http://localhost/JovemConnect/admin/login.php`.

## Estrutura

```
├── index.php, comecar.php, documentos.php   página inicial, escolha jovem/empresa, Termos/Privacidade
├── admin/      Central administrativa (contas, vagas, moderação, histórico, segurança)
├── scripts/    admin.php: criação de administradores pela linha de comando (bloqueado para o navegador)
├── jovem/      cadastro, login, perfil (próprio e visto pela empresa), excluir conta
├── empresa/    cadastro, login, perfil, locais de trabalho, excluir conta
├── dashboard/  painéis do jovem e da empresa
├── Vagas/      listar, detalhes, favoritos, candidatar, criar, minhas vagas, candidatos, análise do Match
├── candidaturas/  candidaturas do jovem
├── verificacoes/  verificação de e-mail e telefone
├── IA/         recomendar.py (Flask), avaliar_ajax.php (Match), assistente_ajax.php (assistente)
├── includes/   configuração, sessão/CSRF, logs, regras compartilhadas, componentes de tela
├── uploads/    currículos (pasta protegida) e entrega controlada do PDF
├── logs/       registros JSON (pasta protegida, fora do Git)
├── migrations/ alterações aditivas do banco, em ordem de data
├── database/   seed_demo.sql (dados fictícios) e diagnostico_somente_leitura.sql
├── docs/       documentação técnica
└── schema.sql  estrutura completa do banco (só instalação nova; sem dados)
```

## Documentação

| Documento | Conteúdo |
|---|---|
| [docs/README.md](docs/README.md) | **Índice da documentação técnica completa** (arquitetura, fluxos, banco, arquivos e funções, rotas, segurança, testes, manutenção) |
| [docs/ARQUITETURA.md](docs/ARQUITETURA.md) | Como as partes se conectam, regras de acesso, Match e IA |
| [docs/CONFIGURACAO.md](docs/CONFIGURACAO.md) | Todas as variáveis do `.env` |
| [docs/MIGRACOES.md](docs/MIGRACOES.md) | Como atualizar um banco existente |
| [docs/SEED_DEMO.md](docs/SEED_DEMO.md) | Dados de demonstração e contas de teste |
| [docs/ASSISTENTE_E_IA.md](docs/ASSISTENTE_E_IA.md) | Assistente com IA, alternativas sem IA e roteiro de testes |
| [docs/LOGS.md](docs/LOGS.md) | Formato, eventos, retenção e privacidade dos logs |
| [docs/ADMINISTRACAO_E_MODERACAO.md](docs/ADMINISTRACAO_E_MODERACAO.md) | Central administrativa, permissões, moderação, denúncias e auditoria |

Os textos de **Termos de Uso** e **Política de Privacidade** (`includes/documentos.php`)
são **minutas** e precisam de revisão jurídica antes de uso real.

## Segurança (resumo)

Senhas com `password_hash`; consultas com *prepared statements*; token CSRF em
todos os formulários e chamadas AJAX; cookie de sessão HttpOnly/SameSite;
currículos e logs em pastas bloqueadas para o navegador (via `.htaccess`, no Apache); empresas só veem
candidatos que se candidataram às suas vagas ou que deixaram o perfil visível (ligado por padrão); dados pessoais fora dos logs e mascarados
antes de ir para a IA. Textos enviados pelos formulários passam por validação e
moderação no servidor (código executável é recusado; indícios de golpe,
discriminação ou ofensa vão para revisão humana). A Central administrativa tem
sessão própria, segundo fator, limite de tentativas, permissões conferidas no
servidor e histórico encadeado por hash. Nunca coloque chaves ou senhas no código ou no Git.
Riscos conhecidos e pendências: [docs/SEGURANCA_E_PRIVACIDADE.md](docs/SEGURANCA_E_PRIVACIDADE.md).

## Contato

startupaestus@gmail.com

## Autor

Gabriel Sassati — Desenvolvedor Principal
