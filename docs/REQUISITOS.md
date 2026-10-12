# Requisitos e dependências

> Auditoria de 10/10/2026, versão **v4.00**. Cada versão está marcada como **identificada no código** (o
> código exige), **declarada** (aparece em arquivo do projeto, sem verificação), **testada** (a réplica de
> desenvolvimento rodou com ela) ou **recomendada** (sugestão desta auditoria). Nenhum requisito de hardware
> foi medido, por isso nenhum é indicado. Os requisitos funcionais e as regras de negócio estão em
> [VISAO_GERAL.md](VISAO_GERAL.md#funcionalidades-existentes-parciais-e-ausentes).

## Ambiente de referência

| Item | Situação |
|---|---|
| Sistema operacional | **Windows** com XAMPP (declarado: `README.md`, `requirements.txt`; identificado: `iniciar_ia.bat`, caminhos `Assets/` com maiúscula que só funcionam em sistema de arquivos sem distinção de maiúsculas) |
| Linux | Não suportado sem ajustes (caminho `Assets/`, `.htaccess`, `iniciar_ia.bat`) — ver [MANUTENCAO_E_EVOLUCAO.md](MANUTENCAO_E_EVOLUCAO.md#publicar-fora-do-xampp) |
| Réplica usada nos testes desta auditoria | Linux, PHP 8.3.6 (servidor embutido), Python 3.13, Flask 3.1.3, banco compatível com MySQL — **não é idêntica ao XAMPP** |

## Versões

| Ferramenta | Mínimo identificado no código | Declarado no projeto | Testado | Observação |
|---|---|---|---|---|
| PHP | **8.1** (tipo de retorno `never` em `IA/assistente_ajax.php`, `Vagas/favoritar.php`, `uploads/ver_curriculo.php`, `includes/candidato.php`) | `>=8.0` em `composer.json` e `requirements.txt`; "8.2" no cabeçalho do `schema.sql` | 8.3.6 | O mínimo declarado está desatualizado; com PHP 8.0 essas páginas dão erro de sintaxe [I] |
| MariaDB / MySQL | InnoDB, `utf8mb4`, `ON DUPLICATE KEY UPDATE`, `CHECK` não usado | "MariaDB 10.4+ / MySQL 8.0+" (`schema.sql`) | servidor compatível com MySQL | — |
| Apache | `.htaccess` com `Require all denied` (2.4) e `Deny from all` (2.2) | "Apache 2.4+" (`requirements.txt`) | não (a réplica usou o servidor embutido do PHP) | precisa de `AllowOverride` para os `.htaccess` |
| XAMPP | — | "v3.3.0+" (`requirements.txt`, versão do painel) | não | — |
| Python | **3.10** (anotação `str \| None` avaliada na definição de `analise_gemini`) | "Python 3" (`iniciar_ia.bat`) | 3.13 | — |
| Flask, flask-cors | qualquer versão com `Flask`, `request`, `jsonify`, `CORS` | sem versão fixa (`requirements.txt`) | Flask 3.1.3 | recomendado fixar versões |
| Composer | — | `composer install` (`README.md`) | não | só para e-mail e SMS |
| PHPMailer | classe `PHPMailer\PHPMailer\PHPMailer` | `^6.9` | não | — |
| Twilio SDK | classe `Twilio\Rest\Client` | `^8.3` | não | — |

## Extensões do PHP

| Extensão | Uso | Obrigatória? |
|---|---|---|
| `mysqli` | conexão e consultas (`includes/config.php`) | sim |
| `session` | login e CSRF | sim |
| `json` | endpoints AJAX, logs | sim (embutida no PHP 8) |
| `mbstring` | `mb_strtolower`, `mb_substr` etc. em 20 arquivos (Match, assistente, cadastros, formação) | sim [C] |
| `curl` | chamadas ao Flask (`includes/match.php`, `IA/avaliar_ajax.php`, `Vagas/curriculoIA.php`) | não; sem ela o Match usa o cálculo local [I] |
| `openssl` | SMTP com TLS (PHPMailer) e HTTPS do Twilio | só com e-mail/SMS |
| `fileinfo`, `gd`, `intl` | não usadas | não |

`random_bytes`/`random_int`, `password_hash` e `hash_equals` fazem parte do núcleo do PHP.

## Pacotes Python

| Pacote | Usado pelo código? | Instalado pelo `iniciar_ia.bat`? |
|---|---|---|
| `flask` | sim | sim |
| `flask-cors` | sim (importado); o PHP usa cURL, então CORS não é necessário para o funcionamento [C] | sim |
| `mysql-connector-python`, `pandas`, `scikit-learn` | **não** (listados em `requirements.txt` sem uso) | não |
| `google-generativeai` | opcional (integração antiga; ver [IA_E_MATCH.md](IA_E_MATCH.md#integração-antiga-google-generativeai)) | não; também não está no `requirements.txt` |

As chamadas ao Gemini das rotas novas usam só a biblioteca padrão (`urllib`) [C].

## Bibliotecas de front-end

Nenhuma biblioteca JavaScript ou framework CSS. Recursos externos carregados pelo navegador: Google Fonts
(Inter) e as APIs ViaCEP e BrasilAPI. A fonte OpenDyslexic citada no CSS **não** é carregada.

## Portas e serviços em execução

| Serviço | Endereço | Obrigatório? |
|---|---|---|
| Apache | `http://localhost/` (porta 80) | sim |
| MariaDB/MySQL | `localhost:3306` (padrão do mysqli) | sim |
| Flask (`IA/recomendar.py`) | `http://127.0.0.1:5000` | não |
| Gemini | `generativelanguage.googleapis.com` (HTTPS) [C: base padrão em `recomendar.py`] | não |

## Variáveis de ambiente

Lista completa, padrões e quem lê cada uma: [CONFIGURACAO.md](CONFIGURACAO.md) e o catálogo de `.env.example`
em [ARQUIVOS_E_FUNCOES.md](ARQUIVOS_E_FUNCOES.md). Resumo do que é obrigatório:

| Grupo | Obrigatório? | Sem configurar |
|---|---|---|
| `DB_*` | não (há padrões para XAMPP: `localhost`, `root`, senha vazia, banco `jovemconnect`) | usa os padrões |
| `SMTP_*` | só para e-mail | verificação de e-mail e recuperação de senha não enviam |
| `TWILIO_*` | só para SMS | verificação de telefone não envia |
| `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_TIMEOUT` | não | Match tradicional e respostas prontas |
| `JC_LOG_DIR`, `JC_LOG_MAX_MB`, `JC_LOG_DIAS` | não | `logs/`, 2 MB, 30 dias |

Variáveis lidas pelo código que **não estão no `.env.example`**: `DB_CHARSET`, `FLASK_DEBUG` (só ambiente real)
e `JC_GEMINI_API_BASE` (só testes).

## Integrações que exigem credenciais externas

| Integração | Credencial | Onde configurar |
|---|---|---|
| E-mail | conta SMTP (`SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`) | `.env` |
| SMS | conta Twilio (`TWILIO_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM_NUMBER`) | `.env` |
| IA | chave do Google AI Studio (`GEMINI_API_KEY`) | `.env` ou variável de ambiente |

## Requisitos de qualidade observados

Os itens abaixo descrevem o comportamento encontrado (não metas declaradas pelo projeto).

| ID | Requisito | Situação | Evidência |
|---|---|---|---|
| RNF-01 | Consultas com entrada do usuário usam prepared statements. | Confirmado no código | revisão dos módulos A–E; nomes de tabela dinâmicos vêm só de valores fixos (`jovens`/`empresas`) |
| RNF-02 | Proteção CSRF em todo POST que altera estado. | Verificado por teste | `includes/csrf.php`; S3 regressão |
| RNF-03 | Sessão com cookie HttpOnly, SameSite=Lax, modo estrito e regeneração do ID no login/cadastro. | Confirmado no código | `includes/session.php` |
| RNF-04 | Senhas com `password_hash` (bcrypt). | Confirmado no código | cadastros, `recuperar_senha.php` |
| RNF-05 | Erros não expõem detalhes técnicos. | Verificado por teste | `jc_excecao_nao_tratada`; S3 regressão (220 respostas sem vazamento) |
| RNF-06 | Logs sem dados pessoais nem segredos; IP anonimizado; rotação e retenção. | Verificado por teste | `includes/logger.php`; suíte logs (22/22) |
| RNF-07 | O site funciona sem o Flask e sem a chave do Gemini. | Verificado por teste | fallback 7/7 ×2; assistente 34/34 |
| RNF-08 | Dados enviados ao Gemini sem identificadores pessoais. | Confirmado no código | `jc_match_payload_ia`, `jc_assist_mascarar` |
| RNF-09 | Páginas utilizáveis de 375 px a 1366 px, sem rolagem lateral, inclusive com zoom de 140%. | Verificado por teste | responsivo: 78 medições, 0 problemas |
| RNF-10 | Acessibilidade: rótulos, foco visível, contraste, modais acessíveis, movimento reduzido. | Verificado por teste | a11y sem problemas novos; S5 modais 65/65; animações 19/19 |
| RNF-11 | Funciona antes e depois das migrações. | Verificado por teste | smoke sem/com migração |
| RNF-12 | Limite de tentativas em login e códigos. | **Ausente** | SEG-02 |
| RNF-13 | Paginação das listagens. | **Ausente** | `Vagas/listar.php`, painéis, `Vagas/candidatos.php` |
| RNF-14 | Portabilidade para Linux. | Parcial | caminho `Assets/`; `.htaccess` só em Apache |
