<?php
/* ============================================================
   Vagas/avaliar.php
   ------------------------------------------------------------
   Página de análise de compatibilidade IA (jovem ↔ vaga).

   Linkada por:
     - Vagas/listar.php    (?vaga_id=X)               → jovem
     - Vagas/favoritos.php (?vaga_id=X)                → jovem
     - dashboard/empresa.php / Vagas/candidatos.php    → poderão
       linkar aqui no futuro com (?vaga_id=X&jovem_id=Y) → empresa

   Esta página só monta o contexto e a autorização; quem calcula
   e retorna o score continua sendo, sem nenhuma duplicação:
       IA/avaliar_ajax.php → IA/recomendar.py (Flask)

   Autorização (nunca confia em ID vindo do cliente sozinho):
     - jovem   → jovem_id sempre de $_SESSION['jovem_id'].
     - empresa → vaga precisa pertencer a empresa_id da sessão;
                 jovem_id (candidato) vem do GET, mas só é aceito
                 com relação de recrutamento (includes/candidato.php):
                 candidatura a uma vaga da empresa ou perfil público.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/locais.php';   // Local efetivo da vaga
require_once dirname(__DIR__) . '/includes/candidato.php';
require_once dirname(__DIR__) . '/includes/conta_status.php';   // Stage 7: visibilidade

if (!isset($_SESSION['jovem_id']) && !isset($_SESSION['empresa_id'])) {
    header("Location: ../index.php");
    exit;
}

$tipo              = isset($_SESSION['jovem_id']) ? 'jovem' : 'empresa';
$erroPagina        = null;
$vaga              = null;
$candidato         = null;
$jovem_id_avaliado = 0;

$vaga_id = filter_input(INPUT_GET, 'vaga_id', FILTER_VALIDATE_INT);
$vaga_id = ($vaga_id === false || $vaga_id === null) ? 0 : $vaga_id;

if ($vaga_id <= 0) {
    $erroPagina = "Vaga inválida.";

} elseif ($tipo === 'jovem') {
    $jovem_id_avaliado = intval($_SESSION['jovem_id']);

    // Stage 7: o jovem só avalia vaga visível (não fora do ar pela moderação
    // nem de empresa suspensa)
    $stmt_v = $conn->prepare(
        "SELECT id, titulo, cidade, estado, area, tipo_contrato, descricao
         FROM vagas v WHERE v.id = ?" . jc_vagas_visiveis_sql($conn, 'v') . " LIMIT 1"
    );
    $stmt_v->bind_param("i", $vaga_id);
    $stmt_v->execute();
    $vaga = $stmt_v->get_result()->fetch_assoc();
    $stmt_v->close();

    if (!$vaga) {
        $erroPagina = "Vaga não encontrada.";
    }

} else { // empresa
    $empresa_id = intval($_SESSION['empresa_id']);

    $jovem_id_get      = filter_input(INPUT_GET, 'jovem_id', FILTER_VALIDATE_INT);
    $jovem_id_avaliado = ($jovem_id_get === false || $jovem_id_get === null) ? 0 : $jovem_id_get;

    $stmt_v = $conn->prepare(
        "SELECT id, titulo, cidade, estado, area, tipo_contrato, descricao
         FROM vagas WHERE id = ? AND empresa_id = ? LIMIT 1"
    );
    $stmt_v->bind_param("ii", $vaga_id, $empresa_id);
    $stmt_v->execute();
    $vaga = $stmt_v->get_result()->fetch_assoc();
    $stmt_v->close();

    if (!$vaga) {
        // Mesma mensagem para "vaga não existe" e "vaga é de outra empresa" —
        // evita confirmar a existência de uma vaga de um concorrente.
        $erroPagina = "Vaga não encontrada.";
    } elseif ($jovem_id_avaliado <= 0) {
        $erroPagina = "Nenhum candidato foi especificado para esta avaliação.";
    } else {
        $candidato = null;
        if (jc_empresa_pode_ver_candidato($conn, $empresa_id, $jovem_id_avaliado)) {
            $stmt_j = $conn->prepare(
                "SELECT id, nome, cidade, estado, area_interesse
                 FROM jovens WHERE id = ? LIMIT 1"
            );
            $stmt_j->bind_param("i", $jovem_id_avaliado);
            $stmt_j->execute();
            $candidato = $stmt_j->get_result()->fetch_assoc();
            $stmt_j->close();
        } else {
            jc_log_seguranca('acesso.negado', 'aviso', ['recurso' => 'analise_match', 'jovem' => $jovem_id_avaliado]);
        }

        if (!$candidato) {
            $erroPagina = "Candidato não encontrado.";
        }
    }
}

// Local de trabalho efetivo (local da vaga → endereço principal da empresa)
$local_vaga = (!$erroPagina && $vaga) ? jc_local_efetivo($conn, $vaga_id) : [];

$perfilLink = ($tipo === 'empresa') ? '../empresa/perfil.php' : '../jovem/perfil.php';
$voltarLink = ($tipo === 'empresa')
    ? '../Vagas/candidatos.php?vaga=' . $vaga_id
    : '../Vagas/listar.php?id=' . $vaga_id;
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Compatibilidade | JovemConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>
<?= csrf_js() ?>

<nav class="navbar-inner">
    <a href="<?= $tipo === 'empresa' ? '../dashboard/empresa.php' : '../dashboard/jovem.php' ?>" class="nav-logo"
       aria-label="JovemConnect — meu painel">
        <picture class="marca marca-nav marca-tema-escuro">
            <source media="(max-width: 480px)" srcset="../assets/img/marca/jovemconnect-simbolo.png" width="910" height="910">
            <img src="../assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="848" height="291">
        </picture>
        <picture class="marca marca-nav marca-tema-claro">
            <source media="(max-width: 480px)" srcset="../assets/img/marca/jovemconnect-simbolo-claro.png" width="910" height="910">
            <img src="../assets/img/marca/jovemconnect-horizontal-claro.png" alt="JovemConnect" width="848" height="291" loading="lazy">
        </picture>
    </a>
    <div class="navbar-inner-right">
        <button class="icon-btn btn-theme" id="btnTheme" title="Alternar tema" aria-label="Alternar tema">🌙</button>
        <a href="<?= htmlspecialchars($perfilLink) ?>" class="icon-btn" title="Meu Perfil" aria-label="Meu Perfil"><span class="i i-user" aria-hidden="true"></span></a>
        <a href="../logout.php" class="icon-btn text-danger" title="Sair" aria-label="Sair"><span class="i i-log-out" aria-hidden="true"></span></a>
    </div>
</nav>

<div class="dashboard-wrap">
<main class="dashboard-main" style="max-width:720px;margin-inline:auto;">

    <?php if ($erroPagina): ?>

        <!-- ── ESTADO: NÃO AUTORIZADO / DADOS INVÁLIDOS ─────────────────────── -->
        <div class="card fade-up" style="text-align:center;padding:56px 32px;max-width:460px;margin:40px auto;">
            <div class="icone-destaque neutro" aria-hidden="true"><span class="i i-lock" aria-hidden="true"></span></div>
            <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">Não foi possível abrir esta análise</h3>
            <p class="text-secondary" style="font-size:14px;line-height:1.6;margin-bottom:28px;">
                <?= htmlspecialchars($erroPagina) ?>
            </p>
            <a href="<?= $tipo === 'empresa' ? '../dashboard/empresa.php' : '../dashboard/jovem.php' ?>"
               class="btn btn-primary btn-full">Voltar ao painel</a>
        </div>

    <?php else: ?>

        <!-- ── CABEÇALHO: VAGA (+ CANDIDATO, SE FOR VISÃO DE EMPRESA) ───────── -->
        <div class="page-heading fade-up">
            <h1>Análise de Compatibilidade</h1>
            <p class="text-secondary">
                <?php if ($tipo === 'empresa'): ?>
                    Comparando <strong><?= htmlspecialchars($candidato['nome']) ?></strong>
                    com a vaga <strong><?= htmlspecialchars($vaga['titulo']) ?></strong>.
                <?php else: ?>
                    Sua compatibilidade com a vaga <strong><?= htmlspecialchars($vaga['titulo']) ?></strong>.
                <?php endif; ?>
            </p>
        </div>

        <div class="card fade-up" style="margin-bottom:20px;">
            <div class="job-card-top">
                <h3 class="job-card-title"><?= htmlspecialchars($vaga['titulo']) ?></h3>
                <?php if (!empty($vaga['area'])): ?>
                <span class="badge badge-area"><?= htmlspecialchars($vaga['area']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (($local_vaga['nivel'] ?? 'indisponivel') !== 'indisponivel'): ?>
            <!-- Local de trabalho efetivo (o mesmo usado no Match) -->
            <div class="job-card-location">
                <span class="i i-map-pin" aria-hidden="true"></span>
                <span><?= htmlspecialchars($local_vaga['texto']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($vaga['tipo_contrato'])): ?>
            <div class="job-card-meta-item" style="margin-top:8px;">
                <span class="i i-file-text" aria-hidden="true"></span><span><?= htmlspecialchars($vaga['tipo_contrato']) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($tipo === 'empresa'): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                <div class="candidate-card-info">
                    <div class="avatar avatar-md"><?= htmlspecialchars(strtoupper(mb_substr($candidato['nome'], 0, 1))) ?></div>
                    <div>
                        <div class="candidate-name"><?= htmlspecialchars($candidato['nome']) ?></div>
                        <div class="candidate-sub">
                            <?= htmlspecialchars($candidato['cidade'] ?? '') ?><?= !empty($candidato['estado']) ? ', ' . htmlspecialchars($candidato['estado']) : '' ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── RESULTADO (preenchido via JS) ───────────────────────────────────
             Nota centralizada (Match tradicional) + "Ver análise
             detalhada" (modal com a composição da nota, o local de trabalho
             usado e a análise contextual opcional do Gemini). -->
        <section class="card fade-up match-card" id="cardResultado" aria-labelledby="matchTitulo" aria-live="polite">
            <div id="estadoCarregando" class="match-estado">
                <span class="loading-spinner"></span>
                <p class="text-secondary">Calculando compatibilidade...</p>
            </div>

            <div id="estadoSucesso" hidden>
                <p class="match-eyebrow" id="matchTitulo"><span class="i i-sparkles" aria-hidden="true"></span> Compatibilidade</p>
                <p class="match-score"><span id="scoreValor">--</span><span class="match-score-max">/100</span></p>
                <div class="match-barra" aria-hidden="true"><span id="scoreBarra"></span></div>
                <p class="match-rotulo" id="scoreRotulo"></p>
                <p class="match-texto" id="analiseTexto"></p>
                <div class="match-acoes">
                    <button type="button" class="btn btn-primary" id="btnAnaliseDetalhada" aria-haspopup="dialog">
                        <span class="i i-list-checks" aria-hidden="true"></span> Ver análise detalhada
                    </button>
                </div>
                <p class="match-nota">Nota calculada pelo Match do JovemConnect, com critérios objetivos.</p>
            </div>

            <div id="estadoErro" hidden>
                <div class="icone-destaque aviso" aria-hidden="true"><span class="i i-alert-triangle" aria-hidden="true"></span></div>
                <p class="text-secondary" id="erroTexto">Não foi possível calcular a compatibilidade agora.</p>
                <button class="btn btn-ghost btn-sm" style="margin-top:16px;" onclick="carregarAvaliacao()">Tentar novamente</button>
            </div>
        </section>

        <!-- Análise detalhada (mesmo padrão dos modais) -->
        <div class="jc-modal" id="modalAnalise" role="dialog" aria-modal="true" aria-labelledby="modalAnaliseTitulo">
            <div class="jc-modal-caixa match-modal">
                <div class="jc-modal-topo">
                    <h2 id="modalAnaliseTitulo"><span class="i i-sparkles" aria-hidden="true"></span> Análise detalhada</h2>
                    <button type="button" class="jc-modal-fechar" data-jc-modal-fechar aria-label="Fechar análise detalhada"><span class="i i-x" aria-hidden="true"></span></button>
                </div>
                <div class="jc-modal-corpo">
                    <div class="match-resumo">
                        <p class="match-score"><span id="modalScore">--</span><span class="match-score-max">/100</span></p>
                        <p class="match-rotulo" id="modalRotulo"></p>
                    </div>

                    <h3 id="secCategorias"><span class="i i-list-checks" aria-hidden="true"></span> Como a nota foi calculada</h3>
                    <ul class="match-categorias" id="listaCategorias" aria-labelledby="secCategorias"></ul>
                    <div id="blocoAjuste"></div>

                    <div class="match-colunas">
                        <section aria-labelledby="secFortes">
                            <h3 id="secFortes"><span class="i i-check-circle" aria-hidden="true"></span> Pontos fortes</h3>
                            <ul class="match-lista" id="listaFortes"></ul>
                        </section>
                        <section aria-labelledby="secReduziram">
                            <h3 id="secReduziram"><span class="i i-alert-triangle" aria-hidden="true"></span> O que reduziu a nota</h3>
                            <ul class="match-lista" id="listaReduziram"></ul>
                        </section>
                    </div>

                    <section aria-labelledby="secLocal">
                        <h3 id="secLocal"><span class="i i-map-pin" aria-hidden="true"></span> Local de trabalho considerado</h3>
                        <p><strong id="localRotulo"></strong></p>
                        <p id="localTexto"></p>
                        <p class="text-muted" id="localAviso" style="font-size:12px;"></p>
                    </section>

                    <section aria-labelledby="secContexto" id="blocoContexto">
                        <h3 id="secContexto"><span class="i i-sparkles" aria-hidden="true"></span> Análise contextual</h3>
                        <div class="match-bloco-ia" id="contextoConteudo" aria-live="polite"></div>
                    </section>

                    <section aria-labelledby="secLimites">
                        <h3 id="secLimites"><span class="i i-info" aria-hidden="true"></span> Limitações</h3>
                        <ul class="match-lista" id="listaLimites"></ul>
                    </section>
                </div>
                <div class="jc-modal-rodape">
                    <button type="button" class="btn btn-secondary btn-sm" data-jc-modal-fechar>Fechar</button>
                </div>
            </div>
        </div>

        <a href="<?= htmlspecialchars($voltarLink) ?>" class="back-link" style="display:flex;justify-content:center;margin-top:20px;">
            <span class="i i-arrow-left" aria-hidden="true"></span> Voltar
        </a>

    <?php endif; ?>

</main>
</div>

<!-- Assistente + acessibilidade (esta página não tinha os botões ✦/♿) -->
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>

<script>
const html     = document.documentElement;
const btnTheme = document.getElementById('btnTheme');
function applyTheme(t) {
    html.setAttribute('data-theme', t);
    if (btnTheme) btnTheme.textContent = t === 'light' ? '☀️' : '🌙';
}
applyTheme(localStorage.getItem('jc-theme') || 'dark');
btnTheme?.addEventListener('click', () => {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    localStorage.setItem('jc-theme', next);
});

<?php if (!$erroPagina): ?>
/* ── resultado do Match + análise detalhada ─────────────────────
   Todo texto vindo do servidor (inclusive da IA) entra com textContent —
   nunca innerHTML. */
const MATCH_IDS = { vaga_id: <?= (int) $vaga_id ?>, jovem_id: <?= (int) $jovem_id_avaliado ?> };
let analiseAtual   = null;
let contextoCarregado = false;

function elemento(tag, classe, texto) {
    const el = document.createElement(tag);
    if (classe) el.className = classe;
    if (texto !== undefined && texto !== null) el.textContent = String(texto);
    return el;
}
function preencherLista(id, itens, vazio) {
    const ul = document.getElementById(id);
    ul.replaceChildren();
    if (!Array.isArray(itens) || !itens.length) {
        ul.replaceChildren(elemento('li', 'match-lista-vazia', vazio));
        return;
    }
    itens.forEach(t => ul.appendChild(elemento('li', '', t)));
}
async function postarMatch(action) {
    const res = await fetch('../IA/avaliar_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ action, ...MATCH_IDS }),
    });
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, data };
}

async function carregarAvaliacao() {
    const carregando = document.getElementById('estadoCarregando');
    const sucesso    = document.getElementById('estadoSucesso');
    const erro       = document.getElementById('estadoErro');
    carregando.hidden = false; sucesso.hidden = true; erro.hidden = true;

    try {
        const { ok, data } = await postarMatch('analise');
        if (!ok || typeof data.score !== 'number') {
            document.getElementById('erroTexto').textContent =
                data.error || 'Não foi possível calcular a compatibilidade agora.';
            carregando.hidden = true; erro.hidden = false;
            return;
        }
        analiseAtual = data;
        const score = Math.max(0, Math.min(100, data.score));
        document.getElementById('scoreValor').textContent   = score;
        document.getElementById('scoreBarra').style.width  = score + '%';
        document.getElementById('scoreRotulo').textContent  = data.rotulo || '';
        document.getElementById('analiseTexto').textContent = data.texto || '';
        montarModal(data);
        carregando.hidden = true; sucesso.hidden = false;
    } catch (e) {
        document.getElementById('erroTexto').textContent = 'Não foi possível se conectar ao servidor de análise.';
        carregando.hidden = true; erro.hidden = false;
    }
}

function montarModal(d) {
    document.getElementById('modalScore').textContent  = d.score;
    document.getElementById('modalRotulo').textContent = d.rotulo || '';

    const lista = document.getElementById('listaCategorias');
    lista.replaceChildren();
    (d.categorias || []).forEach(c => {
        const li   = elemento('li', 'match-cat');
        li.dataset.categoria = c.nome;
        const topo = elemento('div', 'match-cat-topo');
        topo.appendChild(jcIcone(c.icone || 'check'));
        topo.appendChild(elemento('span', 'match-cat-nome', c.nome));
        topo.appendChild(elemento('span', 'match-cat-valor', `${c.percentual}/100`));
        const barra = elemento('div', 'match-barra');
        barra.setAttribute('aria-hidden', 'true');
        const fill = elemento('span');
        fill.style.width = Math.max(0, Math.min(100, c.percentual)) + '%';
        barra.appendChild(fill);
        li.append(topo, barra,
                  elemento('p', 'match-cat-pontos', `${c.pontos} de ${c.maximo} pontos`),
                  elemento('p', 'match-cat-exp', c.explicacao));
        lista.appendChild(li);
    });

    const blocoAjuste = document.getElementById('blocoAjuste');
    blocoAjuste.replaceChildren();
    if (d.ajuste) {
        const aj = elemento('div', 'match-ajuste');
        aj.appendChild(jcIcone('alert-triangle'));
        const txt = elemento('p');
        txt.append(elemento('strong', '', `${d.ajuste.nome}: ${d.ajuste.pontos} pontos. `), d.ajuste.explicacao || '');
        aj.appendChild(txt);
        blocoAjuste.appendChild(aj);
    }

    preencherLista('listaFortes', d.fortes, 'Nenhum critério com pontuação máxima.');
    preencherLista('listaReduziram', d.reduziram, 'Nenhum critério perdeu pontos.');
    preencherLista('listaLimites', d.limites, '');

    const local = d.local || {};
    document.getElementById('localRotulo').textContent = local.rotulo || 'Local de trabalho não informado';
    document.getElementById('localTexto').textContent  = local.texto || '';
    document.getElementById('localAviso').textContent  =
        local.nivel === 'completo' ? '' :
        local.nivel === 'parcial'  ? 'Endereço incompleto: ' + ((local.faltando || []).join(', ') || 'faltam dados') + ' não informado(s). Nada foi completado automaticamente.' :
                                     'Sem endereço para a vaga: a localização não pôde ser avaliada.';
    contextoCarregado = false;
    document.getElementById('contextoConteudo').replaceChildren();
}

async function carregarContexto() {
    if (contextoCarregado) return;
    contextoCarregado = true;
    const caixa = document.getElementById('contextoConteudo');
    const carregando = elemento('p', 'match-carregando');
    carregando.append(elemento('span', 'loading-spinner'), 'Gerando a análise contextual...');
    caixa.replaceChildren(carregando);
    caixa.setAttribute('aria-busy', 'true');
    let data = {};
    try {
        data = (await postarMatch('contexto')).data;
    } catch (e) {
        data = {};
    }
    caixa.removeAttribute('aria-busy');
    const a = data && data.disponivel === true ? data.analise : null;
    if (!a || typeof a.resumo !== 'string') {
        caixa.replaceChildren(elemento('p', 'text-secondary',
            'A análise contextual por IA não está disponível agora. A nota e as categorias acima vêm do Match tradicional e continuam valendo.'));
        contextoCarregado = false;   // permite tentar de novo ao reabrir
        return;
    }
    const conf = { alta: 'alta', media: 'média', baixa: 'baixa' }[a.confianca] || 'baixa';
    const selo = elemento('p', 'match-selo-ia');
    selo.append(jcIcone('sparkles'), `Gerada por IA (Gemini) · confiança ${conf} · não altera a nota`);
    const partes = [selo, elemento('p', 'match-cat-exp', a.resumo)];
    const blocoLista = (titulo, itens) => {
        if (!Array.isArray(itens) || !itens.length) return;
        partes.push(elemento('p', 'match-cat-nome', titulo));
        const ul = elemento('ul', 'match-lista');
        itens.forEach(t => ul.appendChild(elemento('li', '', t)));
        partes.push(ul);
    };
    blocoLista('Pontos fortes', a.pontos_fortes);
    blocoLista('Pontos de atenção', a.pontos_fracos);
    blocoLista('Observações', a.observacoes);
    caixa.replaceChildren(...partes);

    // comentários por categoria: só nas categorias do Match (os demais vão como observação)
    (a.categorias || []).forEach(c => {
        const li = [...document.querySelectorAll('#listaCategorias .match-cat')].find(el => el.dataset.categoria === c.nome);
        if (li) {
            const nota = elemento('p', 'match-cat-ia');
            nota.append(elemento('strong', '', 'Contexto (IA): '), c.explicacao);
            li.appendChild(nota);
        } else {
            const ul = caixa.querySelector('ul.match-lista-extra') || (() => {
                caixa.appendChild(elemento('p', 'match-cat-nome', 'Outros aspectos'));
                const novo = elemento('ul', 'match-lista match-lista-extra');
                caixa.appendChild(novo);
                return novo;
            })();
            ul.appendChild(elemento('li', '', `${c.nome}: ${c.explicacao}`));
        }
    });
}

document.getElementById('btnAnaliseDetalhada')?.addEventListener('click', function () {
    jcModal.abrir(document.getElementById('modalAnalise'), this);
    carregarContexto();
});

document.addEventListener('DOMContentLoaded', carregarAvaliacao);
<?php endif; ?>
</script>

</body>
</html>
