<?php
/* ============================================================
   Vagas/listar.php — Dual-mode:
   - GET sem ?id  → listagem com filtros
   - GET com ?id  → página de detalhes da vaga

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. session_start() depois do include config.php.
   2. SQL Injection: $busca/$cidade/$estado direto nos LIKE
      → nenhum escaping ou prepared statement.
   3. N+1 Queries: dentro do while, uma query extra por vaga
      para checar favorito (50 vagas = 51 queries).
      Corrigido com LEFT JOIN na query principal.
   4. N+1 query também com SQL Injection ($jovem_id/$vaga_id).
   5. Link favoritar via GET → quebrado (favoritar.php = POST).
   6. ../Candidaturas/candidatar.php → path errado.
   7. SELECT v.* → sem controle das colunas, sem campos v3.00.
   8. include header.php após queries e <link> no body.
   ============================================================ */
session_start();

if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$jovem_id = intval($_SESSION['jovem_id']);

/* ============================================================
   MODO: DETAIL VIEW  (GET ?id=X)
   ============================================================ */
if (isset($_GET['id']) && intval($_GET['id']) > 0) {

    $vaga_id = intval($_GET['id']);

    // ── Dados da vaga + empresa ───────────────────────────────────────────────
    $stmt_v = $conn->prepare(
        "SELECT v.id, v.titulo, v.descricao, v.cidade, v.estado,
                v.salario, v.carga_horaria, v.area, v.tipo_contrato, v.cep,
                e.nome_empresa, e.cidade AS empresa_cidade
         FROM   vagas v
         JOIN   empresas e ON v.empresa_id = e.id
         WHERE  v.id = ?
         LIMIT  1"
    );
    $stmt_v->bind_param("i", $vaga_id);
    $stmt_v->execute();
    $vaga = $stmt_v->get_result()->fetch_assoc();
    $stmt_v->close();

    if (!$vaga) {
        header("Location: listar.php");
        exit;
    }

    // ── Requisitos ────────────────────────────────────────────────────────────
    $stmt_r = $conn->prepare(
        "SELECT requisito FROM requisitos_vaga WHERE vaga_id = ? ORDER BY id ASC"
    );
    $stmt_r->bind_param("i", $vaga_id);
    $stmt_r->execute();
    $requisitos = $stmt_r->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_r->close();

    // ── Benefícios ────────────────────────────────────────────────────────────
    $stmt_b = $conn->prepare(
        "SELECT beneficio FROM beneficios_vaga WHERE vaga_id = ? ORDER BY id ASC"
    );
    $stmt_b->bind_param("i", $vaga_id);
    $stmt_b->execute();
    $beneficios = $stmt_b->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_b->close();

    // ── Score de match em cache ────────────────────────────────────────────────
    $stmt_ia = $conn->prepare(
        "SELECT score, analise FROM avaliacoes_ia
         WHERE jovem_id = ? AND vaga_id = ? LIMIT 1"
    );
    $stmt_ia->bind_param("ii", $jovem_id, $vaga_id);
    $stmt_ia->execute();
    $ia_cache = $stmt_ia->get_result()->fetch_assoc();
    $stmt_ia->close();

    // ── Já candidatado? ───────────────────────────────────────────────────────
    $stmt_c = $conn->prepare(
        "SELECT id FROM candidaturas WHERE jovem_id = ? AND vaga_id = ? LIMIT 1"
    );
    $stmt_c->bind_param("ii", $jovem_id, $vaga_id);
    $stmt_c->execute();
    $stmt_c->store_result();
    $ja_candidatado = $stmt_c->num_rows > 0;
    $stmt_c->close();

    ?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($vaga['titulo']) ?> | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body>

<div class="detail-bar">
    <div class="d-flex align-center gap-12">
        <a href="listar.php" class="icon-btn" title="Voltar">←</a>
        <span class="detail-bar-title">Detalhes da Vaga</span>
    </div>
    <?php if (!$ja_candidatado): ?>
    <a href="candidatar.php?vaga=<?= $vaga_id ?>"
       class="btn btn-primary btn-sm">✈ Candidatar-se</a>
    <?php else: ?>
    <span class="badge badge-success" style="padding:10px 16px;">✓ Candidatura Enviada</span>
    <?php endif; ?>
</div>

<div class="page-progress"><div class="page-progress-fill"></div></div>

<div class="detail-page">

    <!-- ── COLUNA PRINCIPAL ──────────────────────────────────────────────────── -->
    <div class="detail-main">

        <!-- Header da vaga -->
        <div class="detail-vaga-header">
            <?php if (!empty($vaga['area'])): ?>
            <span class="badge badge-area"><?= htmlspecialchars($vaga['area']) ?></span>
            <?php endif; ?>

            <h1 class="detail-vaga-title"><?= htmlspecialchars($vaga['titulo']) ?></h1>

            <div class="detail-vaga-company">
                <span>🏢</span>
                <span><?= htmlspecialchars($vaga['nome_empresa']) ?></span>
            </div>

            <div class="detail-vaga-meta">
                <?php if (!empty($vaga['salario'])): ?>
                <div class="detail-meta-item">
                    <span class="detail-meta-label">SALÁRIO</span>
                    <span class="detail-meta-value">
                        <span style="color:#4ade80;">💰</span>
                        R$ <?= htmlspecialchars($vaga['salario']) ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($vaga['carga_horaria'])): ?>
                <div class="detail-meta-item">
                    <span class="detail-meta-label">CARGA HORÁRIA</span>
                    <span class="detail-meta-value">
                        <span>🕐</span>
                        <?= htmlspecialchars($vaga['carga_horaria']) ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($vaga['cidade'])): ?>
                <div class="detail-meta-item">
                    <span class="detail-meta-label">LOCAL</span>
                    <span class="detail-meta-value">
                        <span style="color:#f87171;">📍</span>
                        <?= htmlspecialchars($vaga['cidade']) ?>
                        <?= !empty($vaga['estado']) ? ', ' . htmlspecialchars($vaga['estado']) : '' ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Descrição -->
        <div class="detail-section">
            <h2 class="detail-section-title">ℹ Descrição da Vaga</h2>
            <p class="text-secondary" style="font-size:14px;line-height:1.8;">
                <?= nl2br(htmlspecialchars($vaga['descricao'] ?? '')) ?>
            </p>
        </div>

        <!-- Requisitos -->
        <?php if (!empty($requisitos)): ?>
        <div class="detail-section">
            <h2 class="detail-section-title">✦ Requisitos</h2>
            <div class="req-grid">
                <?php foreach ($requisitos as $r): ?>
                <div class="req-item">
                    <span style="color:var(--accent);flex-shrink:0;">✓</span>
                    <?= htmlspecialchars($r['requisito']) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Benefícios -->
        <?php if (!empty($beneficios)): ?>
        <div class="detail-section">
            <h2 class="detail-section-title">✈ Benefícios</h2>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($beneficios as $b): ?>
                <span class="benefit-chip"><?= htmlspecialchars($b['beneficio']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.detail-main -->

    <!-- ── ASIDE ─────────────────────────────────────────────────────────────── -->
    <aside class="detail-aside">

        <!-- Localização -->
        <div class="map-card">
            <div class="map-visual">
                <span>📍</span>
            </div>
            <div class="map-info">
                <strong><?= htmlspecialchars($vaga['nome_empresa']) ?></strong>
                <span><?= htmlspecialchars($vaga['cidade'] ?? '') ?><?= !empty($vaga['estado']) ? ', ' . htmlspecialchars($vaga['estado']) : '' ?></span>
                <?php if (!empty($vaga['cep'])): ?>
                <span class="text-muted" style="font-size:11px;"><?= htmlspecialchars($vaga['cep']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($vaga['cidade'])): ?>
        <a href="https://www.google.com/maps/search/<?= urlencode($vaga['nome_empresa'] . ' ' . $vaga['cidade']) ?>"
           target="_blank" rel="noopener"
           class="btn btn-secondary btn-sm btn-full"
           style="margin-bottom:10px;">
            ✈ Ver no Google Maps
        </a>
        <?php endif; ?>

        <?php if (!$ja_candidatado): ?>
        <a href="candidatar.php?vaga=<?= $vaga_id ?>"
           class="btn btn-primary btn-sm btn-full"
           style="margin-bottom:12px;">
            ✈ Candidatar-se Agora
        </a>
        <?php else: ?>
        <div class="btn btn-secondary btn-sm btn-full"
             style="margin-bottom:12px;cursor:default;opacity:.6;">
            ✓ Já Candidatado
        </div>
        <?php endif; ?>

        <!-- IA Dica -->
        <div class="ia-dica-card">
            <span class="ia-dica-icon">✦</span>
            <p class="ia-dica-title">IA Dica</p>
            <?php if ($ia_cache): ?>
            <p class="ia-dica-text">
                Match calculado: <strong><?= intval($ia_cache['score']) ?>%</strong><br>
                <span style="font-size:11px;"><?= htmlspecialchars(mb_substr($ia_cache['analise'] ?? '', 0, 120)) ?>...</span>
            </p>
            <a href="avaliar.php?vaga_id=<?= $vaga_id ?>"
               class="btn btn-ghost btn-sm btn-full" style="margin-top:10px;">
                Ver análise completa
            </a>
            <?php else: ?>
            <p class="ia-dica-text">
                Clique abaixo para ver sua compatibilidade com esta vaga.
            </p>
            <a href="avaliar.php?vaga_id=<?= $vaga_id ?>"
               class="btn btn-ghost btn-sm btn-full" style="margin-top:10px;">
                ✦ Avaliar Compatibilidade
            </a>
            <?php endif; ?>
        </div>

    </aside><!-- /.detail-aside -->

</div><!-- /.detail-page -->

<!-- FABs -->
<div class="fab-stack">
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Acessibilidade">♿</button>
</div>

<script>
const t = localStorage.getItem('jc-theme');
if (t) document.documentElement.setAttribute('data-theme', t);
</script>
</body>
</html>
    <?php
    exit; // Para aqui — não executa o modo listagem
}

/* ============================================================
   MODO: LISTAGEM COM FILTROS (GET sem ?id)
   ============================================================ */

// Leitura segura dos filtros
$busca  = trim($_GET['busca']  ?? '');
$cidade = trim($_GET['cidade'] ?? '');
$estado = strtoupper(trim($_GET['estado'] ?? ''));
$area   = trim($_GET['area']   ?? '');

// ── Monta query dinâmica com prepared statements ──────────────────────────────
// LEFT JOIN favoritos resolve o N+1: verifica favorito em UMA única query
$types  = "i";          // Primeiro parâmetro = jovem_id (para o LEFT JOIN)
$params = [$jovem_id];
$where  = [];

if (!empty($busca)) {
    $where[]  = "(v.titulo LIKE ? OR v.descricao LIKE ?)";
    $b        = "%{$busca}%";
    $params[] = $b;
    $params[] = $b;
    $types   .= "ss";
}
if (!empty($cidade)) {
    $where[]  = "v.cidade LIKE ?";
    $params[] = "%{$cidade}%";
    $types   .= "s";
}
if (!empty($estado)) {
    $where[]  = "v.estado = ?";   // UF é exata — LIKE desnecessário
    $params[] = $estado;
    $types   .= "s";
}
if (!empty($area)) {
    $where[]  = "v.area = ?";
    $params[] = $area;
    $types   .= "s";
}

$where_sql = !empty($where) ? "AND " . implode(" AND ", $where) : "";

$sql = "SELECT
            v.id, v.titulo, v.descricao, v.cidade, v.estado,
            v.salario, v.carga_horaria, v.area, v.tipo_contrato,
            e.nome_empresa,
            IF(f.id IS NOT NULL, 1, 0) AS eh_favorito
        FROM vagas v
        JOIN empresas e ON v.empresa_id = e.id
        LEFT JOIN favoritos f
               ON f.vaga_id = v.id AND f.jovem_id = ?
        WHERE 1=1 {$where_sql}
        ORDER BY v.criado_em DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result     = $stmt->get_result();
$total_vagas = $result->num_rows;
$stmt->close();

// ── Áreas distintas para o filtro lateral ────────────────────────────────────
$areas_result = $conn->query(
    "SELECT DISTINCT area FROM vagas WHERE area IS NOT NULL AND area != '' ORDER BY area ASC"
);
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

<!-- Navbar interna -->
<nav class="navbar-inner">
    <a href="../index.php" class="nav-logo">
        <span class="logo-box">💼</span>
        <span class="logo-jovem">Jovem</span><span class="logo-connect">Connect</span>
    </a>

    <!-- Busca centralizada -->
    <div class="navbar-inner-center">
        <form method="GET" action="listar.php" id="formBusca">
            <!-- Preserva filtros de área ao buscar -->
            <?php if (!empty($area)): ?>
            <input type="hidden" name="area" value="<?= htmlspecialchars($area) ?>">
            <?php endif; ?>
            <div class="search-bar">
                <span class="search-icon">⌕</span>
                <input
                    type="search" name="busca"
                    placeholder="Buscar vagas..."
                    value="<?= htmlspecialchars($busca) ?>"
                    autocomplete="off"
                    aria-label="Buscar vagas">
            </div>
        </form>
    </div>

    <div class="navbar-inner-right">
        <button class="icon-btn btn-theme" id="btnTheme" title="Alternar tema">🌙</button>
        <a href="../jovem/perfil.php" class="icon-btn" title="Perfil">👤</a>
        <a href="../logout.php" class="icon-btn text-danger" title="Sair">↪</a>
    </div>
</nav>

<div class="dashboard-wrap">

    <!-- Sidebar com filtros -->
    <aside class="dashboard-sidebar">

        <!-- Filtros avançados -->
        <div class="sidebar-card">
            <p class="sidebar-section-title">⚡ Filtros de Área</p>
            <div class="filter-wrap">
                <a href="listar.php<?= !empty($busca) ? '?busca='.urlencode($busca) : '' ?>"
                   class="filter-chip <?= empty($area) ? 'active' : '' ?>">
                    Todos
                </a>
                <?php if ($areas_result): while ($ar = $areas_result->fetch_assoc()): ?>
                <a href="listar.php?area=<?= urlencode($ar['area']) ?><?= !empty($busca) ? '&busca='.urlencode($busca) : '' ?>"
                   class="filter-chip <?= $area === $ar['area'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($ar['area']) ?>
                </a>
                <?php endwhile; endif; ?>
            </div>
        </div>

        <!-- Filtro cidade/estado -->
        <div class="sidebar-card">
            <p class="sidebar-section-title">📍 Localização</p>
            <form method="GET" action="listar.php">
                <?php if (!empty($busca)): ?>
                <input type="hidden" name="busca" value="<?= htmlspecialchars($busca) ?>">
                <?php endif; ?>
                <?php if (!empty($area)): ?>
                <input type="hidden" name="area" value="<?= htmlspecialchars($area) ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade" class="form-control"
                           placeholder="Ex: São Paulo"
                           value="<?= htmlspecialchars($cidade) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Estado (UF)</label>
                    <input type="text" name="estado" class="form-control"
                           placeholder="SP" maxlength="2"
                           value="<?= htmlspecialchars($estado) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm btn-full">
                    Filtrar
                </button>
                <?php if (!empty($cidade) || !empty($estado)): ?>
                <a href="listar.php<?= !empty($busca) ? '?busca='.urlencode($busca) : '' ?><?= !empty($area) ? (!empty($busca)?'&':'?').'area='.urlencode($area) : '' ?>"
                   class="btn btn-ghost btn-sm btn-full" style="margin-top:6px;">
                    Limpar filtro
                </a>
                <?php endif; ?>
            </form>
        </div>

    </aside><!-- /.dashboard-sidebar -->

    <!-- Conteúdo principal -->
    <main class="dashboard-main">

        <div class="page-heading fade-up">
            <h1>Vagas Disponíveis</h1>
            <p class="text-secondary" style="margin-top:4px;font-size:14px;">
                Mostrando <?= $total_vagas ?> resultado<?= $total_vagas !== 1 ? 's' : '' ?>
                <?php if (!empty($busca)): ?>
                    para "<strong><?= htmlspecialchars($busca) ?></strong>"
                <?php endif; ?>
                <?php if (!empty($area)): ?>
                    em <strong><?= htmlspecialchars($area) ?></strong>
                <?php endif; ?>
                <?php if (!empty($cidade) || !empty($estado)): ?>
                    em <strong><?= htmlspecialchars(trim("{$cidade} {$estado}")) ?></strong>
                <?php endif; ?>.
            </p>
        </div>

        <?php if ($total_vagas > 0): ?>

        <div class="items-grid fade-up" id="gridVagas">
            <?php while ($vaga = $result->fetch_assoc()):
                $vaga_id     = intval($vaga['id']);
                $eh_favorito = (bool)$vaga['eh_favorito'];
            ?>

            <div class="job-card"
                 data-titulo="<?= strtolower(htmlspecialchars($vaga['titulo'])) ?>"
                 data-empresa="<?= strtolower(htmlspecialchars($vaga['nome_empresa'])) ?>">

                <!-- Título + badge + favorito -->
                <div class="job-card-top">
                    <h3 class="job-card-title">
                        <a href="listar.php?id=<?= $vaga_id ?>"
                           style="color:inherit;text-decoration:none;">
                            <?= htmlspecialchars($vaga['titulo']) ?>
                        </a>
                    </h3>
                    <div class="d-flex align-center gap-8">
                        <?php if (!empty($vaga['area'])): ?>
                        <span class="badge badge-area"><?= htmlspecialchars($vaga['area']) ?></span>
                        <?php endif; ?>
                        <!-- Botão de favorito (AJAX POST) -->
                        <button
                            type="button"
                            class="btn-fav"
                            id="fav-<?= $vaga_id ?>"
                            onclick="toggleFavorito(<?= $vaga_id ?>, this)"
                            title="<?= $eh_favorito ? 'Remover dos favoritos' : 'Salvar nos favoritos' ?>"
                            aria-label="<?= $eh_favorito ? 'Remover dos favoritos' : 'Salvar nos favoritos' ?>"
                            style="
                                background:none;border:none;cursor:pointer;
                                font-size:18px;line-height:1;
                                color:<?= $eh_favorito ? '#facc15' : 'var(--text-muted)' ?>;
                                transition:color var(--dur),transform var(--dur);
                            ">
                            <?= $eh_favorito ? '⭐' : '☆' ?>
                        </button>
                    </div>
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

                <!-- Rodapé do card: botão de match IA -->
                <div class="job-card-footer">
                    <div style="display:flex;gap:8px;">
                        <a href="listar.php?id=<?= $vaga_id ?>"
                           class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;">
                            Ver Detalhes
                        </a>
                        <button
                            class="btn btn-ia"
                            style="flex:1;"
                            onclick="avaliarMatch(<?= $vaga_id ?>, this)">
                            ✦ Match IA
                        </button>
                    </div>
                </div>

            </div><!-- /.job-card -->

            <?php endwhile; ?>
        </div><!-- /#gridVagas -->

        <?php else: ?>

        <div class="card fade-up"
             style="text-align:center;padding:56px 32px;max-width:460px;margin:0 auto;">
            <div style="font-size:44px;margin-bottom:18px;">🔍</div>
            <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">
                Nenhuma vaga encontrada
            </h3>
            <p class="text-secondary"
               style="font-size:14px;line-height:1.6;margin-bottom:28px;">
                Tente outros termos de busca ou remova os filtros.
            </p>
            <a href="listar.php" class="btn btn-primary btn-sm">
                Ver todas as vagas
            </a>
        </div>

        <?php endif; ?>

    </main><!-- /.dashboard-main -->

</div><!-- /.dashboard-wrap -->

<!-- FABs -->
<div class="fab-stack">
    <button class="fab fab-ai"   id="btnChat" title="Assistente IA"  aria-label="IA">✦</button>
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Acessibilidade">♿</button>
</div>

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
    applyTheme(next); localStorage.setItem('jc-theme', next);
});

/* ── Toggle favorito (AJAX POST) ───────────────────────────── */
async function toggleFavorito(vagaId, btn) {
    btn.style.transform = 'scale(.8)';
    const fd = new FormData();
    fd.append('vaga_id', vagaId);
    try {
        const res = await fetch('favoritar.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        btn.textContent = data.favorito ? '⭐' : '☆';
        btn.style.color = data.favorito ? '#facc15' : 'var(--text-muted)';
        btn.title       = data.favorito ? 'Remover dos favoritos' : 'Salvar nos favoritos';
    } catch (err) {
        console.error('Erro ao favoritar:', err);
    } finally {
        btn.style.transform = '';
    }
}

/* ── Avaliar Match IA ──────────────────────────────────────── */
async function avaliarMatch(vagaId, btn) {
    btn.disabled  = true;
    btn.innerHTML = '<span class="loading-spinner"></span>';
    try {
        const res = await fetch('../IA/avaliar_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'match', vaga_id: vagaId, jovem_id: <?= $jovem_id ?> }),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const { score } = await res.json();
        btn.innerHTML   = `✦ Match: ${score ?? '--'}%`;
        btn.style.color = 'var(--accent)';
    } catch {
        btn.innerHTML = '✦ Match IA';
        btn.disabled  = false;
    }
}
</script>

</body>
</html>