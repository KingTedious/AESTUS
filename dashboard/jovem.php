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
                 data-empresa="<?= strtolower(htmlspecialchars($vaga['nome_empresa'])) ?>">

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
                <div class="job-card-footer">
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
<div class="user-indicator" title="<?= htmlspecialchars($jovem_nome) ?>">
    <?= strtoupper(mb_substr($jovem_nome, 0, 1)) ?>
</div>


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
</script>

<?php $stmt_vagas->close(); ?>
</body>
</html>