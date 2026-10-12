# Manutenção e evolução

> Auditoria de 10/10/2026, versão **v4.00**. Separação usada: **Prática existente** (o projeto já faz, com
> evidência) × **Recomendação** (sugestão desta auditoria, ainda não adotada).

## Convenções encontradas no código (práticas existentes)

| Convenção | Exemplo |
|---|---|
| Uma página por arquivo; pastas por área (`jovem/`, `empresa/`, `Vagas/`, `dashboard/`, `IA/`) | `Vagas/candidatos.php` |
| Bibliotecas em `includes/`, carregadas com `require_once __DIR__ . '/…'` ou `dirname(__DIR__) . '/includes/…'` | `includes/config.php` |
| Funções novas com prefixo `jc_` e nomes em português | `jc_local_efetivo`, `jc_match_calcular` |
| Funções protegidas por `if (!function_exists(...))` em bibliotecas reaproveitadas | `includes/match.php` |
| Cabeçalho de comentário no topo de cada arquivo (papel, quem usa) | quase todos os `.php` |
| POST → redirect → GET com códigos `?saved=`, `?ok=`, `?error=`, `?result=` | perfis, vagas |
| `csrf_field()` em formulários, `csrf_js()` nas páginas com `fetch` | `jovem/perfil.php` |
| Prepared statements com `bind_param` | todo o projeto |
| Colunas opcionais detectadas em tempo de execução | `empresa_tem_endereco()`, `jc_locais_disponiveis()` |
| Migrações aditivas com data no nome e reversão em comentário | `migrations/` |
| Logs por `jc_log`/`jc_log_seguranca` com nomes `area.acao` | `includes/logger.php` |
| Textos de interface e comentários em português | — |
| Evolução por "Stages" com relatórios | relatórios `RELATORIO_STAGE*.md` guardados fora do repositório; comentários "Stage N" no código |

## Dependências entre módulos e áreas de maior risco de regressão

Mapa completo: [MATRIZ_DE_DEPENDENCIAS.md](MATRIZ_DE_DEPENDENCIAS.md). Pontos que exigem mais cuidado:

| Área | Por que é sensível | O que testar depois de mexer |
|---|---|---|
| `includes/match.php` + `IA/recomendar.py` | algoritmo duplicado; o cache só muda quando a nota muda | paridade Python × PHP (T-MAT-01), Flask fora (T-MAT-02) |
| `includes/candidato.php` | uma regra controla perfil, currículo e Match | T-ACE-01..03 |
| `includes/csrf.php`, `includes/session.php` | todos os POST e AJAX dependem deles | T-LOG-03, S3R completo |
| `includes/assistente.php` | inclui envio único, modais, acessibilidade, `jcIcone`, `jcAviso` — usado por quase todas as páginas | navegação (S4N), modais (S5M), assistente (S6I) |
| `includes/locais.php` | cidade efetiva da vaga (Match, listagem, análise) e sincronização de endereços | S51M |
| `includes/formacao.php` | formato salvo em `jovens` e lido pelo Match | S5F, S5U |
| `includes/etapas.php` | cadastro dos dois tipos | S6C |
| `assets/css/style.css` | 3.153 linhas; regras sobrescritas mais adiante (ex.: `.hero-section` §12 → §27) | responsivo, a11y, identidade |
| `schema.sql` | recria tabelas | nunca rodar em banco com dados |
| `recuperar_senha.php` | falha crítica SEG-01 | T-LOG-06, T-LOG-07 |

## Como adicionar uma funcionalidade (recomendação baseada nas práticas existentes)

1. Página nova na pasta da área; no topo: `require_once` de `includes/csrf.php` (sessão) e `includes/config.php` (banco).
2. Conferir a sessão do tipo certo e redirecionar como as páginas vizinhas.
3. Todo POST: `csrf_require()`; formulários com `csrf_field()`; páginas com `fetch`, `echo csrf_js()`.
4. Filtrar sempre pelo ID **da sessão**, nunca do formulário; usar prepared statements.
5. Escapar saída com `htmlspecialchars`; inserir texto vindo de IA ou do usuário com `textContent`.
6. Regras compartilhadas em `includes/` com prefixo `jc_`.
7. Registrar eventos relevantes com `jc_log('area.acao', nivel, [metadados])` — sem dados pessoais.
8. Usar `assets/css/style.css` (com "a" minúsculo em código novo) e as variáveis de tema existentes.
9. Atualizar a documentação ([ARQUIVOS_E_FUNCOES.md](ARQUIVOS_E_FUNCOES.md), [ROTAS_E_ENDPOINTS.md](ROTAS_E_ENDPOINTS.md)).
10. (Stage 7) Campo de texto novo editável pelo usuário: registrar no mapa `jc_campos()` (`includes/validacao.php`),
    com tipo, limites e política de moderação, e validar com `jc_mod_formulario()`/`jc_mod_lista()` antes de gravar
    o **valor normalizado**; sinais vão para a fila com `jc_mod_registrar()` depois do COMMIT.
11. (Stage 7) Ação administrativa nova: página em `admin/` começando com `jc_admin_exigir('permissão')`
    (permissão nova em `jc_admin_permissoes()`), "Confirmo" + justificativa, `jc_admin_transacao()` com
    `jc_admin_auditar()` dentro, nome legível em `jc_admin_acoes()`. Nunca dar privilégio por migração.

## Como modificar o banco

Prática existente (Stages 3, 5.1 e 6):

1. Criar `migrations/AAAA-MM-DD_descricao.sql` **aditivo** (só `ADD COLUMN`/`CREATE TABLE IF NOT EXISTS`),
   com colunas `NULL` ou com `DEFAULT`, e a reversão em comentário.
2. Replicar o mesmo bloco no `schema.sql` (instalação nova).
3. No PHP, detectar a coluna/tabela antes de usar (padrão `SHOW COLUMNS … LIKE`), para o site funcionar antes e
   depois da migração.
4. Documentar em [MIGRACOES.md](MIGRACOES.md) e [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md).

A Stage 7 seguiu a mesma prática e concentrou a detecção numa função (`jc_stage7_disponivel()`,
`includes/esquema.php`: uma consulta ao `information_schema`, guardada por requisição).

Recomendações: criar uma tabela de controle de migrações; incluir no bloco `DROP` do `schema.sql` as tabelas
que hoje ficam de fora (SEG-11); nunca reaproveitar `schema.sql` para atualizar banco.

## Preservar compatibilidade com dados antigos

- Dados antigos ficam `NULL` nas colunas novas e o código trata `NULL` (ex.: `vagas.local_trabalho = NULL` usa
  cidade/UF da própria vaga; contas sem `termos_aceitos_em` continuam entrando) [C][T].
- Formatos antigos de POST continuam aceitos em `jovem/cadastro.php` e `jovem/perfil.php` (formação antiga) [C].
- Mudou o Match? Notas em cache só são recalculadas quando a nova nota for diferente — o texto antigo pode ficar.

## Como validar mudanças (recomendação)

1. `php -l` em todo arquivo alterado; `python -m py_compile IA/recomendar.py`.
2. Regressão mínima de [TESTES.md](TESTES.md#testes-de-regressão-recomendados).
3. Ver `logs/app.log` e `logs/seguranca.log` durante os testes: `erro.inesperado` indica regressão.
4. Para mudanças só de comentário: comparar tokens do PHP sem comentários (técnica usada na Stage 6).

## Investigar efeitos colaterais

- Quem chama a função: índice em [ARQUIVOS_E_FUNCOES.md](ARQUIVOS_E_FUNCOES.md#índice-de-funções-php).
- Quais tabelas mudam: [MATRIZ_DE_DEPENDENCIAS.md](MATRIZ_DE_DEPENDENCIAS.md#arquivo--função--tabela).
- Cascatas do banco: [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md#o-que-a-exclusão-apaga-cascata).

## Publicar fora do XAMPP

Itens que mudam fora do Windows/Apache:

1. Trocar `../Assets/css/style.css` por `../assets/css/style.css` (≈ 21 arquivos) — em Linux, essas páginas
   ficam sem CSS [C caminho / I efeito].
2. Garantir `AllowOverride` (Apache) ou regras equivalentes (nginx) para `logs/` e `uploads/curriculos/`;
   negar `.env`, `*.sql`, `composer.*`, `includes/` (SEG-04, SEG-10).
3. Substituir `iniciar_ia.bat` por um serviço (ex.: systemd) que rode `python IA/recomendar.py`.
4. HTTPS (o cookie de sessão já recebe `Secure` automaticamente em HTTPS).
5. `display_errors=Off` e `FLASK_DEBUG` desligado.
6. (Stage 7) Central administrativa: HTTPS (o cookie `JCADMIN` só recebe `Secure` em HTTPS), restringir `/admin/`
   por IP se possível, negar `scripts/` (já há `.htaccess`) e usar no site um usuário do banco com só
   `SELECT, INSERT` em `admin_auditoria` (SEG-29).

## Funcionalidades que dependem de serviços externos

| Funcionalidade | Serviço | Sem ele |
|---|---|---|
| Verificação de e-mail, recuperação de senha | SMTP + PHPMailer | não funcionam |
| Verificação de telefone | Twilio | não funciona |
| Análise contextual, assistente com IA | Gemini | indisponível com aviso / respostas prontas |
| IA Advisor | Flask | indisponível |
| Preenchimento de CEP/CNPJ | ViaCEP, BrasilAPI | manual |

## Pendências que exigem mudança de código

**Nenhuma foi corrigida na auditoria de 10/10/2026; aguardam autorização.** Detalhes e evidências em
[SEGURANCA_E_PRIVACIDADE.md](SEGURANCA_E_PRIVACIDADE.md). Na Stage 7 (11/10/2026), dentro do escopo autorizado,
foram resolvidos SEG-18 (esquema de site/LinkedIn) e SEG-24 (limite do resumo em `Vagas/curriculoIA.php`) e
tratado em parte SEG-19 (salário e carga horária); **SEG-01 continua pendente**.

| Prioridade | Pendência | Ref. |
|---|---|---|
| 1 — urgente | Corrigir a tomada de conta na recuperação de senha | SEG-01 |
| 2 | Limite de tentativas em login e códigos; limite de envio de e-mail/SMS | SEG-02, SEG-07 |
| 2 | Decidir visibilidade padrão do perfil (opt-in) e o modo de sugestões do painel da empresa | SEG-03 |
| 2 | Bloquear `.env`/`schema.sql`/`includes/` na web (testar primeiro no XAMPP) | SEG-04 |
| 3 | IA Advisor com `textContent` | SEG-05 |
| 3 | Segredo compartilhado PHP↔Flask; remover CORS | SEG-06 |
| 3 | Hash dos códigos; invalidar sessões ao trocar a senha | SEG-08, SEG-09 |
| 3 | Bloco `DROP` do `schema.sql` | SEG-11 |
| 4 | Caminho `Assets/` → `assets/` | — |
| 4 | `type="url"` do site impede salvar o perfil da empresa | — |
| 4 | Fallback de sugestões de currículo sem Flask (ou mensagem clara) | — |
| 4 | Regras de substring do Match (decisão de produto; mudar os dois lados) | MATCH-01/02 |
| 4 | Normalizar acentos no Match ("Estagio", "concluido") | MATCH-04 |
| 4 | Edição/encerramento de vaga (em vez de DELETE); retirada de candidatura; notificações | SEG-12 |
| 5 | `localStorage` sem `try/catch` em `index.php`, `includes/footer.php`, `includes/header.php`, `comecar.php` | — |
| 5 | Documento HTML duplicado nas páginas de `verificacoes/` | — |
| 5 | Mensagem "Mandamos um código" mesmo sem envio | SEG-23 |
| 5 | `requirements.txt`: remover pacotes sem uso, mínimo de PHP para 8.1 em `composer.json` | — |
| 5 | Carregar a fonte OpenDyslexic ou renomear a opção | — |
| 2 | (Stage 7) Login de jovens e empresas sem limite de tentativas — a Central tem o seu; o site público continua sem | SEG-02 |
| 3 | (Stage 7) "Perfil verificado" marcado pela própria empresa (continua no formulário) | SEG-17 |
| 3 | (Stage 7) Contrato e área da vaga sem lista branca no servidor | SEG-19 |
| 4 | (Stage 7) Segredo TOTP dos administradores em texto no banco (cifrar com chave do `.env`) | SEG-28 |
| 4 | (Stage 7) Papéis administrativos mais restritos (hoje só `administrador`) | [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md#papéis-e-permissões) |
| 4 | (Stage 7) Usuário do banco com só `SELECT, INSERT` em `admin_auditoria` e cópia externa do `seguranca.log` | SEG-29 |
| 5 | (Stage 7) Atualizar [ARQUIVOS_E_FUNCOES.md](ARQUIVOS_E_FUNCOES.md) e a [matriz](MATRIZ_DE_DEPENDENCIAS.md) com os arquivos da Stage 7 (hoje listados em [ADMINISTRACAO_E_MODERACAO.md](ADMINISTRACAO_E_MODERACAO.md#arquitetura-dos-arquivos)) | — |

## Dívidas técnicas e código sem uso

| Item | Evidência |
|---|---|
| `functions.php` (12 funções, nenhum `include`) | busca por nome do arquivo e das funções |
| `includes/auth.php` → `verificar_autenticacao()` (nunca chamada) | índice de funções |
| `verificacoes/validacoes_nomes.php` (4 funções sem chamador) | índice de funções; a Stage 7 acrescentou um aviso no cabeçalho apontando para `includes/validacao.php` |
| `processar_login.php` na raiz (só redireciona) | `ARQUIVOS_E_FUNCOES.md` |
| `Vagas/curriculoIA.php` (nenhum link) | busca por `curriculoIA` |
| Integração `google-generativeai` / `gemini-1.5-flash` | `IA/recomendar.py` |
| `candidaturas.etapa`, tabelas `mensagens_chat`, `freelances`, `propostas_freelance` | sem uso |
| Classes CSS sem uso (`.hero-eyebrow`, `.logo-box`, `.user-indicator`…) | busca em todos os `.php` |
| Ramos `if (!$stmt->execute())` inalcançáveis (mysqli lança exceção) | `includes/config.php` sem `mysqli_report` |
| Validações diferentes entre cadastro e perfil (limites de habilidades, UF, tamanhos) | módulo B |

**Recomendação:** não apagar esses itens sem decisão (podem ser material do TCC); marcá-los como legado.

## Comentários e documentos desatualizados encontrados

| Onde | Divergência | Situação |
|---|---|---|
| `docs/ARQUITETURA.md` (antigo) | dizia que habilidades e experiência entram na nota | **corrigido** nesta auditoria |
| `docs/ASSISTENTE_E_IA.md` | "Sem IA: sugestões fixas por regras" para o currículo | **corrigido** (sem Flask → indisponível) |
| `docs/LOGS.md` | "arquivos antigos apagados na próxima gravação" | **corrigido** (só na rotação) |
| `includes/config.php` (l. 25), `logs/.htaccess` | citam `logs/sistema.log` | registrado (comentário de código) |
| `includes/match.php` (cabeçalho) | cita um teste de paridade que não existe no repositório | registrado |
| `IA/avaliar_ajax.php` | comentário `// 'match' ou 'curriculo'` (há também `analise` e `contexto`) | registrado |
| `includes/termos.php` | manda editar o texto em `includes/institucional.php` (está em `includes/documentos.php`) | registrado |
| `empresa/processar_login.php` | diz que a empresa de teste tem senha em texto puro (é o jovem) | registrado |
| `requirements.txt` | PHP 8.0; "flask-cors essencial para o PHP" | registrado |
| `includes/logger.php` | "comparação sem acentos"; ".env" lido pelo logger | registrado |
| `index.php` | "Gerador de currículos profissional" | registrado |

## Recomendações para próximos desenvolvedores

1. Comece por [README.md](README.md) → [ARQUITETURA.md](ARQUITETURA.md) → [FLUXOS_E_FLUXOGRAMAS.md](FLUXOS_E_FLUXOGRAMAS.md).
2. Antes de mexer num arquivo, procure-o em [ARQUIVOS_E_FUNCOES.md](ARQUIVOS_E_FUNCOES.md) e na matriz.
3. Trate SEG-01 antes de qualquer demonstração pública.
4. Versione os testes de regressão.
5. Nunca use uma chave de API já exposta; gere uma nova e mantenha-a só no `.env`.
