<?php
/* ============================================================
   dashboard/empresa.php
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF
require_once dirname(__DIR__) . "/includes/config.php";

// Proteção de rota
if (!isset($_SESSION['empresa_id'])) {
    header("Location: ../empresa/login.php");
    exit;
}

$empresa_id = intval($_SESSION['empresa_id']);

// ── Dados da empresa (nome + área para o filtro de compatibilidade) ──────────
$stmt = $conn->prepare(
    "SELECT nome_empresa, area_atuacao FROM empresas WHERE id = ? LIMIT 1"
);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$empresa      = $stmt->get_result()->fetch_assoc();
$stmt->close();

$empresa_nome = $empresa['nome_empresa']  ?? $_SESSION['empresa_nome'] ?? 'Empresa';
$empresa_area = $empresa['area_atuacao']  ?? null;

// ── 1. Vagas publicadas ───────────────────────────────────────────────────────
$stmt_vagas = $conn->prepare(
    "SELECT id, titulo, cidade, estado, area, salario, carga_horaria
     FROM   vagas
     WHERE  empresa_id = ?
     ORDER  BY criado_em DESC"
);
$stmt_vagas->bind_param("i", $empresa_id);
$stmt_vagas->execute();
$result_vagas  = $stmt_vagas->get_result();
$total_vagas   = $result_vagas->num_rows;

// ── 2. Candidatos das vagas desta empresa ─────────────────────────────────────
//    Prioridade: jovens que se candidataram a alguma vaga da empresa.
//    Fallback (0 candidatos): jovens com área_interesse compatível.
// Nota (Etapa 3): sem DISTINCT/sem colapsar por jovem — cada candidatura já é
// um par (jovem, vaga) inequívoco, então cada linha vira um card com o
// vaga_id certo para o botão "Avaliar Match com IA" (candidaturas.vaga_id +
// vagas.titulo). Um mesmo jovem que se candidatou a 2 vagas desta empresa
// aparece em 2 cards, um por vaga — evita escolher uma vaga arbitrária.
$stmt_cands = $conn->prepare(
    "SELECT
         j.id,
         j.nome,
         j.cidade,
         j.estado,
         j.area_interesse,
         j.resumo_profissional,
         j.escolaridade_curso,
         j.escolaridade_tipo,
         TIMESTAMPDIFF(YEAR, j.data_nascimento, CURDATE()) AS idade,
         c.vaga_id  AS vaga_id_card,
         v.titulo   AS vaga_titulo_card
     FROM   jovens j
     JOIN   candidaturas c ON c.jovem_id = j.id
     JOIN   vagas        v ON c.vaga_id  = v.id
     WHERE  v.empresa_id   = ?
       AND  j.perfil_publico = 1
     ORDER  BY c.criado_em DESC"
);
$stmt_cands->bind_param("i", $empresa_id);
$stmt_cands->execute();
$result_cands = $stmt_cands->get_result();
$modo_fallback = false;

// Fallback: buscar por área compatível se não há candidatos ainda.
// Aqui não existe candidatura, então não existe um vaga_id específico por
// jovem — ver bloco de decisão logo abaixo (vaga_fallback_id / seletor).
if ($result_cands->num_rows === 0 && $empresa_area) {
    $stmt_cands->close();
    $stmt_cands = $conn->prepare(
        "SELECT
             j.id,
             j.nome,
             j.cidade,
             j.estado,
             j.area_interesse,
             j.resumo_profissional,
             j.escolaridade_curso,
             j.escolaridade_tipo,
             TIMESTAMPDIFF(YEAR, j.data_nascimento, CURDATE()) AS idade
         FROM   jovens j
         WHERE  j.area_interesse LIKE ?
           AND  j.perfil_publico = 1
         ORDER  BY j.criado_em DESC
         LIMIT  12"
    );
    $area_like = "%{$empresa_area}%";
    $stmt_cands->bind_param("s", $area_like);
    $stmt_cands->execute();
    $result_cands = $stmt_cands->get_result();
    $modo_fallback = true;
}

$total_cands = $result_cands->num_rows;

// No modo fallback não há vaga_id por card. Resolve isso com a menor
// alteração possível dado o que já existe na página: se a empresa tem
// exatamente 1 vaga, essa é a vaga (zero ambiguidade); com 2+ vagas, o
// usuário escolhe explicitamente pelo seletor (evita adivinhar por ele);
// com 0 vagas, não há nada para avaliar e o botão fica desabilitado.
$vaga_fallback_unica = null;
if ($modo_fallback && $total_vagas === 1) {
    $result_vagas->data_seek(0);
    $primeira_vaga = $result_vagas->fetch_assoc();
    $vaga_fallback_unica = ['id' => $primeira_vaga['id'], 'titulo' => $primeira_vaga['titulo']];
    $result_vagas->data_seek(0);
}

$vagas_para_seletor = [];
if ($modo_fallback && $total_vagas > 1) {
    $result_vagas->data_seek(0);
    while ($v = $result_vagas->fetch_assoc()) {
        $vagas_para_seletor[] = ['id' => $v['id'], 'titulo' => $v['titulo']];
    }
    $result_vagas->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Empresa | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body>
<?= csrf_js() ?>

<!-- ============================================================
     NAVBAR INTERNA
     ============================================================ -->
<nav class="navbar-inner">
    <a href="../index.php" class="nav-logo">
        <span class="logo-box">💼</span>
        <span class="logo-jovem">Jovem</span><span class="logo-connect">Connect</span>
    </a>

    <div class="navbar-inner-center">
        <div class="search-bar">
            <span class="search-icon">⌕</span>
            <input
                type="search"
                id="searchCandidatos"
                placeholder="Buscar candidatos..."
                autocomplete="off"
                aria-label="Buscar candidatos">
        </div>
    </div>

    <div class="navbar-inner-right">
        <button class="icon-btn btn-theme" id="btnTheme" title="Alternar tema" aria-label="Alternar tema">🌙</button>
        <!-- Stage 3: Vagas/minhas.php (editar/excluir vagas) não tinha nenhum link de acesso -->
        <a href="../Vagas/minhas.php" class="icon-btn" title="Minhas vagas" aria-label="Minhas vagas">📋</a>
        <a href="../empresa/perfil.php"  class="icon-btn" title="Meu Perfil">👤</a>
        <a href="../logout.php"          class="icon-btn text-danger" title="Sair" aria-label="Sair">↪</a>
    </div>
</nav>

<!-- ============================================================
     CONTEÚDO PRINCIPAL (sem sidebar — layout empresa)
     ============================================================ -->
<div class="dashboard-wrap">
<main class="dashboard-main">

    <!-- ── SEÇÃO: CANDIDATOS COMPATÍVEIS ──────────────────────────────────── -->
    <div class="page-heading fade-up">
        <h1>Candidatos Compatíveis</h1>
        <p class="text-secondary">
            <?php if ($total_cands > 0): ?>
                Mostrando <?= $total_cands ?> jovem<?= $total_cands !== 1 ? 's' : '' ?>
                <?= $empresa_area
                    ? 'interessados em <strong>' . htmlspecialchars($empresa_area) . '</strong>.'
                    : 'candidatados às suas vagas.' ?>
            <?php else: ?>
                Nenhum candidato encontrado ainda. Publique vagas para começar!
            <?php endif; ?>
        </p>
    </div>

    <?php if ($total_cands > 0): ?>

    <div class="items-grid fade-up" id="gridCandidatos">
        <?php while ($jovem = $result_cands->fetch_assoc()):
            $inicial     = strtoupper(mb_substr($jovem['nome'], 0, 1));
            $idade       = $jovem['idade'] !== null ? intval($jovem['idade']) . ' anos' : '';
            $local_parts = array_filter([
                htmlspecialchars($jovem['cidade'] ?? ''),
                htmlspecialchars($jovem['estado']  ?? ''),
            ]);
            $localizacao = implode(', ', $local_parts);
            $area_label  = htmlspecialchars($jovem['area_interesse'] ?? '');
            $curso       = htmlspecialchars($jovem['escolaridade_curso'] ?? '');
            $eh_tecnico  = ($jovem['escolaridade_tipo'] === 'tecnico');
            $resumo      = $jovem['resumo_profissional']
                ? htmlspecialchars(mb_substr($jovem['resumo_profissional'], 0, 130)) . '...'
                : 'Busco oportunidade na área de ' . ($area_label ?: 'aprendizado')
                  . ' para aplicar meus conhecimentos e crescer profissionalmente...';

            // Qual vaga este card representa (ver bloco de decisão logo acima das queries):
            //  - modo normal: vem direto da candidatura (par jovem+vaga inequívoco);
            //  - fallback com 1 vaga só: essa é a vaga, sem ambiguidade;
            //  - fallback com 2+ vagas: usuário escolhe no seletor do próprio card;
            //  - fallback com 0 vagas: não há o que avaliar, botão desabilitado.
            $vaga_id_card    = null;
            $vaga_titulo_card = null;
            if (!$modo_fallback) {
                $vaga_id_card     = intval($jovem['vaga_id_card']);
                $vaga_titulo_card = $jovem['vaga_titulo_card'];
            } elseif ($vaga_fallback_unica) {
                $vaga_id_card     = intval($vaga_fallback_unica['id']);
                $vaga_titulo_card = $vaga_fallback_unica['titulo'];
            }
        ?>
        <div class="candidate-card"
             data-nome="<?= strtolower(htmlspecialchars($jovem['nome'])) ?>">

            <!-- Cabeçalho do card -->
            <div class="candidate-card-head">
                <div class="candidate-card-info">
                    <div class="avatar avatar-md"><?= $inicial ?></div>
                    <div>
                        <div class="candidate-name"><?= htmlspecialchars($jovem['nome']) ?></div>
                        <div class="candidate-sub">
                            <?= $idade ?><?= ($idade && $localizacao) ? ' • ' : '' ?><?= $localizacao ?>
                        </div>
                    </div>
                </div>
                <?php if ($area_label): ?>
                <span class="badge badge-area"><?= $area_label ?></span>
                <?php endif; ?>
            </div>

            <!-- Curso / área -->
            <?php if ($curso): ?>
            <div class="candidate-area">
                <span>🎓</span>
                <span><?= $eh_tecnico ? 'Técnico em ' : '' ?><?= $curso ?></span>
            </div>
            <?php endif; ?>

            <!-- Bio resumida -->
            <p class="candidate-bio"><?= $resumo ?></p>

            <?php if ($vaga_titulo_card): ?>
            <!-- Contexto: para qual vaga esta avaliação é (evita ambiguidade) -->
            <div class="candidate-area" style="margin-bottom:10px;">
                <span>💼</span>
                <span>Candidatura para: <strong><?= htmlspecialchars($vaga_titulo_card) ?></strong></span>
            </div>
            <?php elseif ($modo_fallback && count($vagas_para_seletor) > 0): ?>
            <!-- Sem candidatura ainda: a empresa escolhe explicitamente contra qual vaga comparar -->
            <div class="form-group" style="margin-bottom:10px;">
                <label class="form-label" style="font-size:12px;">Avaliar para qual vaga?</label>
                <select class="form-control select-vaga-avaliar" style="font-size:13px;padding:8px;">
                    <option value="">Selecione uma vaga...</option>
                    <?php foreach ($vagas_para_seletor as $vOpt): ?>
                    <option value="<?= intval($vOpt['id']) ?>"><?= htmlspecialchars($vOpt['titulo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Botão de match IA -->
            <?php if ($vaga_id_card): ?>
            <button
                class="btn btn-ia"
                onclick="avaliarMatch(<?= intval($jovem['id']) ?>, <?= $vaga_id_card ?>, this)"
                data-jovem="<?= intval($jovem['id']) ?>">
                ✦ Avaliar Match com IA
            </button>
            <?php elseif ($modo_fallback && count($vagas_para_seletor) > 0): ?>
            <button
                class="btn btn-ia"
                onclick="avaliarMatchComSeletor(<?= intval($jovem['id']) ?>, this)"
                data-jovem="<?= intval($jovem['id']) ?>">
                ✦ Avaliar Match com IA
            </button>
            <?php else: ?>
            <button class="btn btn-ia" disabled title="Publique uma vaga para poder avaliar candidatos.">
                ✦ Avaliar Match com IA
            </button>
            <?php endif; ?>

        </div>
        <?php endwhile; ?>
    </div><!-- /#gridCandidatos -->

    <?php else: ?>

    <div class="card fade-up"
         style="text-align:center;padding:56px 32px;max-width:460px;margin:0 auto;">
        <div style="font-size:44px;margin-bottom:18px;">🔍</div>
        <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">Nenhum candidato ainda</h3>
        <p class="text-secondary"
           style="font-size:14px;line-height:1.6;margin-bottom:28px;">
            Publique vagas para começar a receber candidaturas de jovens talentos.
        </p>
        <a href="../Vagas/criar.php" class="btn btn-primary btn-full">+ Criar Nova Vaga</a>
    </div>

    <?php endif; ?>


    <!-- ── DIVISOR ─────────────────────────────────────────────────────────── -->
    <div style="margin:48px 0 32px;border-top:1px solid var(--border);"></div>


    <!-- ── SEÇÃO: VAGAS ATIVAS ─────────────────────────────────────────────── -->
    <div class="page-heading fade-up">
        <div class="d-flex align-center justify-between">
            <div>
                <h2 style="font-size:20px;font-weight:700;letter-spacing:-.01em;">
                    Suas Vagas Ativas
                </h2>
                <p class="text-secondary" style="font-size:14px;margin-top:4px;">
                    Gerencie as oportunidades publicadas
                </p>
            </div>
            <a href="../Vagas/criar.php" class="btn btn-primary btn-sm">+ Criar Vaga</a>
        </div>
    </div>

    <?php if ($total_vagas > 0): ?>

    <div class="items-grid fade-up">
        <?php while ($vaga = $result_vagas->fetch_assoc()): ?>
        <div class="job-card">

            <div class="job-card-top">
                <h3 class="job-card-title"><?= htmlspecialchars($vaga['titulo']) ?></h3>
                <?php if (!empty($vaga['area'])): ?>
                <span class="badge badge-area"><?= htmlspecialchars($vaga['area']) ?></span>
                <?php endif; ?>
            </div>

            <div class="job-card-meta">
                <?php if (!empty($vaga['salario'])): ?>
                <div class="job-card-meta-item">
                    <span>💰</span>
                    <span>R$ <?= htmlspecialchars($vaga['salario']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($vaga['carga_horaria'])): ?>
                <div class="job-card-meta-item">
                    <span>🕐</span>
                    <span><?= htmlspecialchars($vaga['carga_horaria']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($vaga['cidade'])): ?>
            <div class="job-card-location">
                <span>📍</span>
                <span>
                    <?= htmlspecialchars($vaga['cidade']) ?>
                    <?= !empty($vaga['estado']) ? ', ' . htmlspecialchars($vaga['estado']) : '' ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="job-card-footer">
                <a href="../Vagas/candidatos.php?id=<?= intval($vaga['id']) ?>"
                   class="btn btn-ghost btn-sm btn-full">
                    Gerenciar Candidatos →
                </a>
            </div>

        </div>
        <?php endwhile; ?>
    </div>

    <?php else: ?>

    <div class="card fade-up"
         style="text-align:center;padding:40px 24px;max-width:400px;margin:0 auto;">
        <p class="text-secondary" style="margin-bottom:16px;">
            Você ainda não publicou nenhuma vaga.
        </p>
        <a href="../Vagas/criar.php" class="btn btn-primary">Criar Primeira Vaga</a>
    </div>

    <?php endif; ?>

</main>
</div><!-- /.dashboard-wrap -->


<!-- ============================================================
     FABs FLUTUANTES
     ============================================================ -->
<div class="fab-stack">
    <button class="fab fab-ai"   id="btnChat" title="Assistente IA"   aria-label="Abrir assistente IA">✦</button>
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade"  aria-label="Abrir acessibilidade">♿</button>
</div>

<div class="chat-widget" id="chatWidget" role="dialog"
     aria-label="Assistente JovemConnect" aria-modal="false">
    <div class="chat-header">
        <div class="chat-header-left">
            <span>✦</span><span>Assistente JovemConnect</span>
        </div>
        <button class="chat-close" id="chatClose" aria-label="Fechar">✕</button>
    </div>
    <div class="chat-body" id="chatBody">
        <div class="chat-msg">
            Oi! 👋 Posso te ajudar a entender como funcionam as vagas,
            a avaliação de compatibilidade da IA, ou qualquer dúvida sobre o JovemConnect.
            Como posso ajudar?
        </div>
    </div>
    <div class="chat-footer">
        <input type="text" class="chat-input" id="chatInput"
               placeholder="Pergunte algo..." aria-label="Mensagem">
        <button class="chat-send" id="chatSend" aria-label="Enviar">✈</button>
    </div>
</div>

<!-- ============================================================
     PAINEL DE ACESSIBILIDADE
     ============================================================ -->
<div class="a11y-panel" id="a11yPanel" role="dialog"
     aria-label="Acessibilidade" aria-modal="false">

    <div class="a11y-header">
        <h3>♿ Acessibilidade</h3>
        <button class="a11y-reset" id="a11yReset" title="Restaurar" aria-label="Restaurar padrões">↺</button>
    </div>

    <div class="a11y-body">

        <div class="a11y-item">
            <label class="a11y-label" id="lblFs">
                T Tamanho do Texto (<span id="fontSizeVal">100</span>%)
            </label>
            <input type="range" id="rangeFontSize" min="80" max="140" value="100"
                   aria-labelledby="lblFs">
        </div>

        <hr class="a11y-divider">

        <div class="a11y-row">
            <label class="a11y-label" for="togDislexia">⋮ᴬ Fonte para Dislexia</label>
            <label class="toggle">
                <input type="checkbox" id="togDislexia">
                <span class="toggle-track"></span>
            </label>
        </div>

        <hr class="a11y-divider">

        <div class="a11y-item">
            <label class="a11y-label" for="selDaltonismo">🎨 Filtro de Daltonismo</label>
            <select id="selDaltonismo" class="form-control">
                <option value="">Nenhum</option>
                <option value="protanopia">Protanopia</option>
                <option value="deuteranopia">Deuteranopia</option>
                <option value="tritanopia">Tritanopia</option>
            </select>
        </div>

        <hr class="a11y-divider">

        <div class="a11y-row">
            <label class="a11y-label" for="togContraste">⊙ Alto Contraste</label>
            <label class="toggle">
                <input type="checkbox" id="togContraste">
                <span class="toggle-track"></span>
            </label>
        </div>

        <hr class="a11y-divider">

        <div class="a11y-row">
            <label class="a11y-label" for="togCinza">👁 Tons de Cinza</label>
            <label class="toggle">
                <input type="checkbox" id="togCinza">
                <span class="toggle-track"></span>
            </label>
        </div>

    </div>
</div>

<!-- SVG filters daltonismo -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
        <filter id="protanopia">
            <feColorMatrix type="matrix"
                values="0.567,0.433,0,0,0 0.558,0.442,0,0,0 0,0.242,0.758,0,0 0,0,0,1,0"/>
        </filter>
        <filter id="deuteranopia">
            <feColorMatrix type="matrix"
                values="0.625,0.375,0,0,0 0.7,0.3,0,0,0 0,0.3,0.7,0,0 0,0,0,1,0"/>
        </filter>
        <filter id="tritanopia">
            <feColorMatrix type="matrix"
                values="0.95,0.05,0,0,0 0,0.433,0.567,0,0 0,0.475,0.525,0,0 0,0,0,1,0"/>
        </filter>
    </defs>
</svg>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script>
/* ── Tema ─────────────────────────────────────────────── */
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

/* ── Busca de candidatos (filtro client-side) ─────────── */
document.getElementById('searchCandidatos')?.addEventListener('input', function () {
    const q     = this.value.toLowerCase().trim();
    const cards = document.querySelectorAll('#gridCandidatos .candidate-card');
    cards.forEach(card => {
        card.style.display = card.dataset.nome.includes(q) ? '' : 'none';
    });
});

/* ── Avaliação de Match com IA ────────────────────────── */
async function avaliarMatch(jovemId, vagaId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Analisando...';

    try {
        const res = await fetch('../IA/avaliar_ajax.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                action:     'match',
                jovem_id:   jovemId,
                vaga_id:    vagaId,
                empresa_id: <?= $empresa_id ?>
            })
        });

        const data = await res.json();

        if (!res.ok || typeof data.score === 'undefined') {
            // Stage 3: mostra o motivo no próprio botão (antes só no tooltip)
            btn.disabled    = false;
            btn.textContent = '✦ ' + (data.error || 'Não foi possível avaliar agora.');
            btn.title       = 'Clique para tentar novamente';
            setTimeout(() => { btn.innerHTML = '✦ Avaliar Match com IA'; btn.title = ''; }, 3500);
            return;
        }

        btn.innerHTML   = `✦ Match: ${data.score}%`;
        btn.style.color = 'var(--accent)';
        btn.title       = data.analise ?? '';

        // Link para a análise completa (reaproveita Vagas/avaliar.php da Etapa 3) —
        // o botão só mostra o score; a análise por extenso e os motivos individuais
        // só cabem numa tooltip de hover, pouco visível (principalmente em touch).
        if (!btn.nextElementSibling?.classList.contains('link-analise-completa')) {
            const linkAnalise = document.createElement('a');
            linkAnalise.href      = `../Vagas/avaliar.php?vaga_id=${vagaId}&jovem_id=${jovemId}`;
            linkAnalise.className = 'link-analise-completa back-link';
            linkAnalise.style.cssText = 'display:block;margin-top:8px;font-size:12px;';
            linkAnalise.textContent = 'Ver análise completa →';
            btn.after(linkAnalise);
        }

    } catch (err) {
        btn.innerHTML = '✦ Avaliar Match com IA';
        btn.disabled  = false;
        console.error('Erro no match IA:', err);
    }
}

/* Cards sem candidatura (modo "descoberta"): a vaga vem do <select> do
   próprio card em vez de já vir pronta — exige escolha explícita antes
   de avaliar, para nunca comparar com uma vaga arbitrária. */
function avaliarMatchComSeletor(jovemId, btn) {
    const select = btn.closest('.candidate-card')?.querySelector('.select-vaga-avaliar');
    const vagaId = select ? parseInt(select.value, 10) : 0;

    if (!vagaId) {
        select?.focus();
        select?.classList.add('input-error');
        return;
    }

    avaliarMatch(jovemId, vagaId, btn);
}

/* ── Assistente + Acessibilidade (mesma implementação de index.php) ──── */
const chatWidget = document.getElementById('chatWidget');
const chatBody   = document.getElementById('chatBody');
const chatInput  = document.getElementById('chatInput');
const a11yPanel  = document.getElementById('a11yPanel');

document.getElementById('btnChat')?.addEventListener('click', () => {
    chatWidget.classList.toggle('is-open');
    a11yPanel?.classList.remove('is-open');
    if (chatWidget.classList.contains('is-open')) chatInput?.focus();
    atualizarVisibilidadeFabs();
});
document.getElementById('chatClose')?.addEventListener('click', () => {
    chatWidget.classList.remove('is-open');
    atualizarVisibilidadeFabs();
});

// Evita sobreposicao visual: enquanto um painel esta aberto, esconde o FAB
// do OUTRO (o FAB do painel aberto continua visivel, para dar para fechar).
function atualizarVisibilidadeFabs() {
    const chatAberto = chatWidget.classList.contains('is-open');
    const a11yAberto  = a11yPanel.classList.contains('is-open');
    const btnChatEl = document.getElementById('btnChat');
    const btnA11yEl = document.getElementById('btnA11y');
    if (btnChatEl) btnChatEl.style.display = a11yAberto ? 'none' : '';
    if (btnA11yEl) btnA11yEl.style.display = chatAberto ? 'none' : '';
}

function addMsg(html, fromUser = false) {
    const d = document.createElement('div');
    d.className = 'chat-msg';
    if (fromUser) d.style.cssText =
        'background:var(--accent-subtle);border:1px solid var(--accent);margin-left:24px;';
    // Stage 3: o texto digitado pelo usuário nunca é interpretado como HTML
    if (fromUser) d.textContent = html; else d.innerHTML = html;
    chatBody.appendChild(d);
    chatBody.scrollTop = chatBody.scrollHeight;
    return d;
}

const respostas = {
    jovem:   'Para se cadastrar como jovem, clique em <strong>Sou Jovem</strong>. O processo e rapido e gratuito! 🎓',
    empresa: 'Para publicar vagas, clique em <strong>Sou Empresa</strong>. Use nossa IA para encontrar candidatos compativeis! 💼',
    vaga:    'As vagas aparecem no dashboard apos o login. A IA calcula automaticamente a compatibilidade com seu perfil! ✦',
    ia:      'Nossa IA analisa formacao, localizacao e area de interesse para gerar um score de 0-100% de compatibilidade. ✦',
    aprendiz:'O Jovem Aprendiz e um programa que permite a jovens entre 14 e 24 anos seu primeiro emprego com carteira assinada. 📋',
};

async function sendChat() {
    const msg = chatInput.value.trim();
    if (!msg) return;
    chatInput.value = '';
    addMsg(msg, true);
    const spinner = addMsg('<span class="loading-spinner"></span>');

    // Stage 3: removido o fetch ao /ping do Flask — o resultado era ignorado
    // (respostas são predefinidas) e gerava erro no console com o Flask desligado.
    await new Promise(r => setTimeout(r, 350));

    spinner.remove();

    // Stage 3: casa PALAVRAS inteiras, sem acento — "bom dia" não dispara a
    // resposta de "ia"; "aprendiz" é testado antes de "jovem"; aceita plural.
    const palavras = msg.toLowerCase().normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '').split(/[^a-z0-9]+/);
    const radicais = { aprendiz: 'aprendiz', empresa: 'empresa', vaga: 'vaga', ia: 'ia', jovem: 'jove' };
    const key = Object.keys(radicais).find(k => palavras.some(p =>
        radicais[k].length <= 2 ? p === radicais[k] : p.startsWith(radicais[k])));
    addMsg(key
        ? respostas[key]
        : 'Boa pergunta! Para mais informacoes, explore o site ou entre em contato. 😊');
}

document.getElementById('chatSend')?.addEventListener('click', sendChat);
chatInput?.addEventListener('keydown', e => { if (e.key === 'Enter') sendChat(); });

/* -- Painel de Acessibilidade -- */
document.getElementById('btnA11y')?.addEventListener('click', () => {
    a11yPanel.classList.toggle('is-open');
    chatWidget.classList.remove('is-open');
    atualizarVisibilidadeFabs();
});

const rangeFs = document.getElementById('rangeFontSize');
rangeFs?.addEventListener('input', function () {
    document.getElementById('fontSizeVal').textContent = this.value;
    html.style.fontSize = (this.value / 100) + 'rem';
    localStorage.setItem('jc-fontsize', this.value);
});

document.getElementById('togDislexia')?.addEventListener('change', function () {
    html.style.fontFamily = this.checked ? '"OpenDyslexic","Comic Sans MS",cursive' : '';
    localStorage.setItem('jc-dyslexia', this.checked ? '1' : '0');
});

document.getElementById('selDaltonismo')?.addEventListener('change', function () {
    const f = { protanopia:'url(#protanopia)', deuteranopia:'url(#deuteranopia)', tritanopia:'url(#tritanopia)' };
    html.style.filter = f[this.value] || '';
    localStorage.setItem('jc-daltonismo', this.value);
});

document.getElementById('togContraste')?.addEventListener('change', function () {
    html.classList.toggle('high-contrast', this.checked);
    localStorage.setItem('jc-contraste', this.checked ? '1' : '0');
});

document.getElementById('togCinza')?.addEventListener('change', function () {
    html.style.filter = this.checked ? 'grayscale(100%)' : '';
    localStorage.setItem('jc-cinza', this.checked ? '1' : '0');
});

document.getElementById('a11yReset')?.addEventListener('click', () => {
    html.style.fontSize = html.style.fontFamily = html.style.filter = '';
    html.classList.remove('high-contrast');
    if (rangeFs) rangeFs.value = 100;
    document.getElementById('fontSizeVal').textContent = '100';
    ['togDislexia','togContraste','togCinza'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });
    document.getElementById('selDaltonismo').value = '';
    ['jc-fontsize','jc-dyslexia','jc-daltonismo','jc-contraste','jc-cinza']
        .forEach(k => localStorage.removeItem(k));
});

// Restaura preferencias salvas
;(function() {
    const fs = localStorage.getItem('jc-fontsize');
    if (fs && rangeFs) {
        rangeFs.value = fs;
        document.getElementById('fontSizeVal').textContent = fs;
        html.style.fontSize = (fs / 100) + 'rem';
    }
    if (localStorage.getItem('jc-dyslexia') === '1') {
        document.getElementById('togDislexia').checked = true;
        html.style.fontFamily = '"OpenDyslexic","Comic Sans MS",cursive';
    }
    if (localStorage.getItem('jc-contraste') === '1') {
        document.getElementById('togContraste').checked = true;
        html.classList.add('high-contrast');
    }
    if (localStorage.getItem('jc-cinza') === '1') {
        document.getElementById('togCinza').checked = true;
        html.style.filter = 'grayscale(100%)';
    }
})();

</script>

<?php
$stmt_vagas->close();
$stmt_cands->close();
?>
</body>
</html>