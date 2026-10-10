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

## Tecnologias

| Parte | Tecnologia |
|---|---|
| Site | PHP 8 (mysqli, sessões), HTML, CSS e JavaScript sem frameworks |
| Banco | MariaDB / MySQL |
| IA | Python 3 + Flask (`IA/recomendar.py`) e Google Gemini (opcional, via API REST) |
| E-mail / SMS | PHPMailer (SMTP) e Twilio, via Composer |
| Ambiente local | XAMPP (Apache + MariaDB) no Windows |

## Como instalar (XAMPP)

1. **Arquivos** — coloque a pasta do projeto em `C:\xampp\htdocs\` (ex.:
   `C:\xampp\htdocs\JovemConnect`) e inicie Apache e MySQL no XAMPP.
2. **Dependências PHP** — na pasta do projeto: `composer install`
   (PHPMailer e Twilio; só necessários para e-mail e SMS).
3. **Configuração** — copie `.env.example` para `.env` e preencha
   (veja [docs/CONFIGURACAO.md](docs/CONFIGURACAO.md)). O `.env` nunca vai para o Git.
4. **Banco de dados**
   - *Instalação nova*: no phpMyAdmin, crie o banco `jovemconnect` e importe
     `schema.sql`. **Atenção:** o `schema.sql` recria as tabelas (apaga os dados
     existentes) — use só em banco novo.
   - *Banco que já existe*: aplique, em ordem, as migrações de `migrations/`
     que ainda não foram aplicadas (veja [docs/MIGRACOES.md](docs/MIGRACOES.md)).
   - *Dados de demonstração (opcional)*: `database/seed_demo.sql`
     (veja [docs/SEED_DEMO.md](docs/SEED_DEMO.md)).
5. **Servidor de IA** — dê dois cliques em `iniciar_ia.bat`. Ele usa o Python do
   computador, cria um ambiente só para aquele PC na primeira vez (instala
   `flask` e `flask-cors`) e abre o site no navegador. Funciona a partir de
   qualquer pasta ou pen drive. Teste: <http://127.0.0.1:5000/ping>.
6. Acesse `http://localhost/JovemConnect/` (o nome da pasta do projeto).

## Estrutura

```
├── index.php, comecar.php, documentos.php   página inicial, escolha jovem/empresa, Termos/Privacidade
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
├── database/   seed_demo.sql (dados fictícios)
├── docs/       documentação técnica
└── schema.sql  estrutura completa do banco (instalação nova)
```

## Documentação

| Documento | Conteúdo |
|---|---|
| [docs/ARQUITETURA.md](docs/ARQUITETURA.md) | Como as partes se conectam, regras de acesso, Match e IA |
| [docs/CONFIGURACAO.md](docs/CONFIGURACAO.md) | Todas as variáveis do `.env` |
| [docs/MIGRACOES.md](docs/MIGRACOES.md) | Como atualizar um banco existente |
| [docs/SEED_DEMO.md](docs/SEED_DEMO.md) | Dados de demonstração e contas de teste |
| [docs/ASSISTENTE_E_IA.md](docs/ASSISTENTE_E_IA.md) | Assistente com IA, alternativas sem IA e roteiro de testes |
| [docs/LOGS.md](docs/LOGS.md) | Formato, eventos, retenção e privacidade dos logs |

Os textos de **Termos de Uso** e **Política de Privacidade** (`includes/documentos.php`)
são **minutas** e precisam de revisão jurídica antes de uso real.

## Segurança (resumo)

Senhas com `password_hash`; consultas com *prepared statements*; token CSRF em
todos os formulários e chamadas AJAX; cookie de sessão HttpOnly/SameSite;
currículos e logs em pastas bloqueadas para o navegador; empresas só veem
candidatos com relação de recrutamento; dados pessoais fora dos logs e mascarados
antes de ir para a IA. Nunca coloque chaves ou senhas no código ou no Git.

## Contato

startupaestus@gmail.com

## Autor

Gabriel Sassati — Desenvolvedor Principal
