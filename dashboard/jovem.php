<?php
/* ============================================================
   dashboard/jovem.php
   ============================================================ */
session_start();

if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$jovem_id   = intval($_SESSION['jovem_id']);
$jovem_nome = $_SESSION['jovem_nome'] ?? 'Jovem';

// ── Filtro de área via GET ────────────────────────────────────────────────────
// htmlspecialchars() no output, trim() na leitura — prepared stmt na query
$area_filtro = !empty($_GET['area']) ? trim($_GET['area']) : null;

// ── 1. Áreas distintas (chips de filtro do sidebar) ──────────────────────────
$result_areas = $conn->query(
    "SELECT DISTINCT area FROM vagas
     WHERE  area IS NOT NULL AND area != ''
     ORDER  BY area ASC"
);

// ── 2. Vagas (com ou sem filtro de área) ─────────────────────────────────────
if ($area_filtro) {
    $stmt_vagas = $conn->prepare(
        "SELECT v.id, v.titulo, v.descricao, v.cidade, v.estado,
                v.salario, v.carga_horaria, v.area, v.tipo_contrato,
                e.nome_empresa
         FROM   vagas v
         JOIN   empresas e ON v.empresa_id = e.id
         WHERE  v.area = ?
         ORDER  BY v.criado_em DESC"
    );
    $stmt_vagas->bind_param("s", $area_filtro);
} else {
    $stmt_vagas = $conn->prepare(
        "SELECT v.id, v.titulo, v.descricao, v.cidade, v.estado,
                v.salario, v.carga_horaria, v.area, v.tipo_contrato,
                e.nome_empresa
         FROM   vagas v
         JOIN   empresas e ON v.empresa_id = e.id
         ORDER  BY v.criado_em DESC"
    );
}
$stmt_vagas->execute();
$result_vagas = $stmt_vagas->get_result();
$total_vagas  = $result_vagas->num_rows;

// ── Notícias (estáticas por enquanto — substituir por tabela futuramente) ─────
$noticias = [
    [
        'tag'    => 'Legislação',
        'data'   => '15 Mai',
        'titulo' => 'Novo decreto amplia vagas para Jovem Aprendiz em 2026',
        'resumo' => 'As novas regras facilitam a contratação...',
    ],
    [
        'tag'    => 'Carreira',
        'data'   => '12 Mai',
        'titulo' => '5 dicas para se destacar na sua primeira entrevista de estágio',
        'resumo' => 'Especialistas em RH dão dicas valiosas...',
    ],
    [
        'tag'    => 'Oportunidades',
        'data'   => '10 Mai',
        'titulo' => 'Empresas de tecnologia abrem 500+ vagas para aprendizes',
        'resumo' => 'Grandes hubs de inovação buscam...',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vagas Disponíveis | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body>

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
                id="searchVagas"
                placeholder="Buscar vagas..."
                autocomplete="off"
                aria-label="Buscar vagas">
        </div>
    </div>

    <div class="navbar-inner-right">
        <button class="icon-btn btn-theme" id="btnTheme" title="Alternar tema" aria-label="Alternar tema">🌙</button>
        <a href="../jovem/perfil.php"  class="icon-btn" title="Meu Perfil">👤</a>
        <a href="../logout.php"        class="icon-btn text-danger" title="Sair" aria-label="Sair">↪</a>
    </div>
</nav>

<!-- ============================================================
     LAYOUT COM SIDEBAR
     ============================================================ -->
<div class="dashboard-wrap">

    <!-- ── SIDEBAR ESQUERDO ──────────────────────────────────────────────── -->
    <aside class="dashboard-sidebar">

        <!-- Filtros de Área -->
        <div class="sidebar-card">
            <p class="sidebar-section-title">⚡ Filtros de Área</p>
            <div class="filter-wrap">

                <a href="jovem.php"
                   class="filter-chip <?= !$area_filtro ? 'active' : '' ?>">
                    Todos
                </a>

                <?php if ($result_areas && $result_areas->num_rows > 0):
                    while ($row = $result_areas->fetch_assoc()): ?>
                <a href="?area=<?= urlencode($row['area']) ?>"
                   class="filter-chip <?= $area_filtro === $row['area'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($row['area']) ?>
                </a>
                <?php endwhile; endif; ?>

            </div>
        </div>

        <!-- Notícias & Dicas -->
        <div class="sidebar-card">
            <p class="sidebar-section-title">📰 Notícias & Dicas</p>
            <div class="news-list">

                <?php foreach ($noticias as $noticia): ?>
                <div class="news-item">
                    <div class="news-meta">
                        <span class="news-tag"><?= htmlspecialchars($noticia['tag']) ?></span>
                        <span class="news-date">📅 <?= htmlspecialchars($noticia['data']) ?></span>
                    </div>
                    <p class="news-title"><?= htmlspecialchars($noticia['titulo']) ?></p>
                    <p class="news-excerpt"><?= htmlspecialchars($noticia['resumo']) ?></p>
                </div>
                <?php endforeach; ?>

                <a href="#" class="news-see-all">Ver todas as notícias</a>

            </div>
        </div>

    </aside><!-- /.dashboard-sidebar -->

    <!-- ── CONTEÚDO PRINCIPAL ─────────────────────────────────────────────── -->
    <main class="dashboard-main">

        <div class="page-heading fade-up">
            <h1>Vagas Disponíveis</h1>
            <p class="text-secondary">
                Mostrando <?= $total_vagas ?> resultado<?= $total_vagas !== 1 ? 's' : '' ?>
                <?php if ($area_filtro): ?>
                    em <strong><?= htmlspecialchars($area_filtro) ?></strong>.
                <?php else: ?>
                    baseados no seu perfil.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($total_vagas > 0): ?>

        <div class="items-grid fade-up" id="gridVagas">
            <?php while ($vaga = $result_vagas->fetch_assoc()): ?>

            <div class="job-card"
                 data-titulo="<?=  strtolower(htmlspecialchars($vaga['titulo'])) ?>"
                 data-empresa="<?= strtolower(htmlspecialchars($vaga['nome_empresa'])) ?>"
                 onclick="location.href='../Vagas/listar.php?id=<?= intval($vaga['id']) ?>'"
                 style="cursor:pointer;"
                 title="Ver detalhes da vaga">

                <!-- Título + badge de área -->
                <div class="job-card-top">
                    <h3 class="job-card-title">
                        <?= htmlspecialchars($vaga['titulo']) ?>
                    </h3>
                    <?php if (!empty($vaga['area'])): ?>
                    <span class="badge badge-area"><?= htmlspecialchars($vaga['area']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Empresa -->
                <div class="job-card-company">
                    <span>🏢</span>
                    <?= htmlspecialchars($vaga['nome_empresa']) ?>
                </div>

                <!-- Salário + carga horária -->
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

                <!-- Localização -->
                <?php if (!empty($vaga['cidade'])): ?>
                <div class="job-card-location">
                    <span>📍</span>
                    <span>
                        <?= htmlspecialchars($vaga['cidade']) ?>
                        <?= !empty($vaga['estado']) ? ', ' . htmlspecialchars($vaga['estado']) : '' ?>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Botão IA (rodapé do card) -->
                <div class="job-card-footer" onclick="event.stopPropagation()">
                    <button
                        class="btn btn-ia"
                        onclick="avaliarMatch(<?= intval($vaga['id']) ?>, this)"
                        data-vaga="<?= intval($vaga['id']) ?>">
                        ✦ Avaliar Match com IA
                    </button>
                </div>

            </div><!-- /.job-card -->

            <?php endwhile; ?>
        </div><!-- /#gridVagas -->

        <?php else: ?>

        <!-- Estado vazio -->
        <div class="card fade-up"
             style="text-align:center;padding:56px 32px;max-width:460px;margin:0 auto;">
            <div style="font-size:44px;margin-bottom:18px;">💼</div>
            <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">
                Nenhuma vaga encontrada
            </h3>
            <p class="text-secondary"
               style="font-size:14px;line-height:1.6;margin-bottom:28px;">
                <?php if ($area_filtro): ?>
                    Não há vagas em <strong><?= htmlspecialchars($area_filtro) ?></strong> no momento.
                <?php else: ?>
                    Ainda não há vagas cadastradas na plataforma.
                <?php endif; ?>
            </p>
            <?php if ($area_filtro): ?>
            <a href="jovem.php" class="btn btn-primary">Ver todas as vagas</a>
            <?php endif; ?>
        </div>

        <?php endif; ?>

    </main><!-- /.dashboard-main -->

</div><!-- /.dashboard-wrap -->


<!-- ============================================================
     FABs FLUTUANTES
     ============================================================ -->
<div class="fab-stack">
    <button class="fab fab-ai"   id="btnChat" title="Assistente IA"  aria-label="Abrir assistente IA">✦</button>
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Abrir acessibilidade">♿</button>
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
/* ── Tema ──────────────────────────────────────────────────── */
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

/* ── Busca de vagas (filtro client-side) ────────────────────── */
document.getElementById('searchVagas')?.addEventListener('input', function () {
    const q     = this.value.toLowerCase().trim();
    const cards = document.querySelectorAll('#gridVagas .job-card');
    cards.forEach(card => {
        const match = card.dataset.titulo.includes(q)
                   || card.dataset.empresa.includes(q);
        card.style.display = match ? '' : 'none';
    });
});

/* ── Avaliar Match com IA ───────────────────────────────────── */
async function avaliarMatch(vagaId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Analisando...';

    try {
        const res = await fetch('../IA/avaliar_ajax.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                vaga_id:  vagaId,
                jovem_id: <?= $jovem_id ?>
            })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const { score, analise } = await res.json();

        btn.innerHTML = `✦ Match: ${score ?? '--'}%`;
        btn.style.color = 'var(--accent)';
        btn.title       = analise ?? '';

    } catch (err) {
        btn.innerHTML = '✦ Avaliar Match com IA';
        btn.disabled  = false;
        console.error('Erro no match IA:', err);
    }
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
    d.innerHTML = html;
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

    try {
        await fetch('http://127.0.0.1:5000/ping', { signal: AbortSignal.timeout(2000) });
    } catch { /* Flask offline - fallback estatico */ }

    spinner.remove();

    const lower = msg.toLowerCase();
    const key   = Object.keys(respostas).find(k => lower.includes(k));
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

<?php $stmt_vagas->close(); ?>
</body>
</html>