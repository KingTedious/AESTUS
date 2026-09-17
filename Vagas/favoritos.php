<?php
/* ============================================================
   Vagas/favoritos.php — Vagas favoritas do jovem

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. session_start() depois do include config.php.
   2. SQL Injection: $jovem_id direto na query.
   3. SELECT v.* sem JOIN com empresas → sem nome_empresa.
   4. Campos v3.00 ausentes: area, carga_horaria.
   5. Link de desfavoritar via GET (favoritar.php?vaga_id=X)
      → quebrado desde que corrigimos favoritar.php para
      aceitar apenas POST.
   6. Link ../candidaturas/candidatar.php → caminho errado;
      o arquivo é Vagas/candidatar.php.
   7. Sem htmlspecialchars() nas saídas → XSS.
   8. include header.php após queries e <link> no body.
   9. var(--principal) não existe no CSS v3.00.
   ============================================================ */
session_start();

if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$jovem_id = intval($_SESSION['jovem_id']);

// Feedback de vaga removida (redirect de favoritar.php)
$removido = isset($_GET['favorito']) && $_GET['favorito'] === 'removed';

// ── Busca vagas favoritas com dados completos ─────────────────────────────────
$stmt = $conn->prepare(
    "SELECT
         v.id, v.titulo, v.descricao, v.cidade, v.estado,
         v.salario, v.carga_horaria, v.area, v.tipo_contrato,
         e.nome_empresa,
         f.id AS fav_id,
         f.criado_em AS favoritado_em
     FROM favoritos f
     JOIN vagas    v ON f.vaga_id    = v.id
     JOIN empresas e ON v.empresa_id = e.id
     WHERE f.jovem_id = ?
     ORDER BY f.criado_em DESC"
);
$stmt->bind_param("i", $jovem_id);
$stmt->execute();
$result = $stmt->get_result();
$total  = $result->num_rows;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vagas Favoritas | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body>

<!-- Navbar interna -->
<nav class="navbar-inner">
    <a href="../index.php" class="nav-logo">
        <span class="logo-box">💼</span>
        <span class="logo-jovem">Jovem</span><span class="logo-connect">Connect</span>
    </a>
    <div class="navbar-inner-right">
        <button class="icon-btn btn-theme" id="btnTheme" title="Alternar tema">🌙</button>
        <a href="../jovem/perfil.php" class="icon-btn" title="Perfil">👤</a>
        <a href="../logout.php" class="icon-btn text-danger" title="Sair">↪</a>
    </div>
</nav>

<div class="dashboard-wrap">
<main class="dashboard-main">

    <!-- Toast de feedback -->
    <?php if ($removido): ?>
    <div id="toast" class="toast toast-success">✓ Vaga removida dos favoritos.</div>
    <?php endif; ?>

    <!-- Cabeçalho da página -->
    <div class="page-heading fade-up">
        <div class="d-flex align-center justify-between" style="flex-wrap:wrap;gap:12px;">
            <div>
                <h1>⭐ Vagas Favoritas</h1>
                <p class="text-secondary" style="margin-top:4px;font-size:14px;">
                    <?= $total ?> vaga<?= $total !== 1 ? 's' : '' ?> salva<?= $total !== 1 ? 's' : '' ?>
                    para revisar mais tarde.
                </p>
            </div>
            <a href="../dashboard/jovem.php" class="btn btn-ghost btn-sm">
                Ver todas as vagas →
            </a>
        </div>
    </div>

    <?php if ($total > 0): ?>

    <div class="items-grid fade-up" id="gridFavoritos">

        <?php while ($row = $result->fetch_assoc()):
            $vaga_id = intval($row['id']);
        ?>

        <div class="job-card" id="card-fav-<?= $vaga_id ?>">

            <!-- Título + área -->
            <div class="job-card-top">
                <h3 class="job-card-title">
                    <?= htmlspecialchars($row['titulo']) ?>
                </h3>
                <?php if (!empty($row['area'])): ?>
                <span class="badge badge-area"><?= htmlspecialchars($row['area']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Empresa -->
            <div class="job-card-company">
                <span>🏢</span>
                <?= htmlspecialchars($row['nome_empresa']) ?>
            </div>

            <!-- Salário + carga horária -->
            <div class="job-card-meta">
                <?php if (!empty($row['salario'])): ?>
                <div class="job-card-meta-item">
                    <span>💰</span>
                    <span>R$ <?= htmlspecialchars($row['salario']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($row['carga_horaria'])): ?>
                <div class="job-card-meta-item">
                    <span>🕐</span>
                    <span><?= htmlspecialchars($row['carga_horaria']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Localização -->
            <?php if (!empty($row['cidade'])): ?>
            <div class="job-card-location">
                <span>📍</span>
                <span>
                    <?= htmlspecialchars($row['cidade']) ?>
                    <?= !empty($row['estado']) ? ', ' . htmlspecialchars($row['estado']) : '' ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Descrição resumida -->
            <p class="text-secondary"
               style="font-size:13px;line-height:1.6;margin-bottom:6px;flex:1;">
                <?= htmlspecialchars(mb_substr($row['descricao'] ?? '', 0, 130)) ?>…
            </p>

            <!-- Salvo em -->
            <?php if (!empty($row['favoritado_em'])): ?>
            <p class="text-muted" style="font-size:11px;margin-bottom:14px;">
                ⭐ Salvo em <?= date('d/m/Y', strtotime($row['favoritado_em'])) ?>
            </p>
            <?php endif; ?>

            <!-- Ações -->
            <div class="job-card-footer" style="display:flex;flex-direction:column;gap:8px;">

                <!-- Candidatar + Avaliar -->
                <div style="display:flex;gap:8px;">
                    <a href="candidatar.php?vaga=<?= $vaga_id ?>"
                       class="btn btn-primary btn-sm" style="flex:1;justify-content:center;">
                        ✈ Candidatar-me
                    </a>
                    <a href="avaliar.php?vaga_id=<?= $vaga_id ?>"
                       class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;">
                        ✦ Avaliar IA
                    </a>
                </div>

                <!-- Desfavoritar — POST via AJAX com fallback form -->
                <button
                    type="button"
                    class="btn btn-secondary btn-sm btn-full"
                    onclick="desfavoritar(<?= $vaga_id ?>, this)">
                    ⭐ Remover dos Favoritos
                </button>

                <!-- Fallback sem JS: form POST convencional -->
                <noscript>
                    <form method="POST" action="favoritar.php">
                        <input type="hidden" name="vaga_id" value="<?= $vaga_id ?>">
                        <button type="submit"
                                class="btn btn-secondary btn-sm btn-full">
                            ⭐ Remover dos Favoritos
                        </button>
                    </form>
                </noscript>

            </div>

        </div><!-- /.job-card -->

        <?php endwhile; ?>

    </div><!-- /#gridFavoritos -->

    <?php else: ?>

    <!-- Estado vazio -->
    <div class="card fade-up"
         style="text-align:center;padding:56px 32px;max-width:460px;margin:0 auto;">
        <div style="font-size:44px;margin-bottom:18px;">⭐</div>
        <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">
            Nenhuma vaga salva ainda
        </h3>
        <p class="text-secondary"
           style="font-size:14px;line-height:1.6;margin-bottom:28px;">
            Explore as vagas disponíveis e salve as que mais combinam com você.
        </p>
        <a href="../dashboard/jovem.php" class="btn btn-primary btn-sm">
            Explorar Oportunidades
        </a>
    </div>

    <?php endif; ?>

</main>
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
    applyTheme(next);
    localStorage.setItem('jc-theme', next);
});

/* ── Toast ─────────────────────────────────────────────────── */
const toast = document.getElementById('toast');
if (toast) {
    setTimeout(() => toast.classList.add('toast-visible'), 100);
    setTimeout(() => toast.classList.remove('toast-visible'), 3500);
}

/* ── Desfavoritar via AJAX ─────────────────────────────────── */
async function desfavoritar(vagaId, btn) {
    btn.disabled  = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Removendo...';

    const fd = new FormData();
    fd.append('vaga_id', vagaId);

    try {
        const res = await fetch('favoritar.php', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    fd,
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);

        const data = await res.json();

        if (data.action === 'removed') {
            // Remove o card com animação
            const card = document.getElementById('card-fav-' + vagaId);
            if (card) {
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity    = '0';
                card.style.transform  = 'scale(.97)';
                setTimeout(() => {
                    card.remove();

                    // Verifica se ficou vazio
                    const grid = document.getElementById('gridFavoritos');
                    if (grid && grid.children.length === 0) {
                        grid.innerHTML = `
                            <div class="card fade-up"
                                 style="text-align:center;padding:56px 32px;
                                        max-width:460px;grid-column:1/-1;">
                                <div style="font-size:44px;margin-bottom:18px;">⭐</div>
                                <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">
                                    Nenhuma vaga salva ainda
                                </h3>
                                <p class="text-secondary"
                                   style="font-size:14px;line-height:1.6;margin-bottom:28px;">
                                    Explore as vagas disponíveis e salve as que mais combinam.
                                </p>
                                <a href="../dashboard/jovem.php" class="btn btn-primary btn-sm">
                                    Explorar Oportunidades
                                </a>
                            </div>`;
                    }
                }, 300);
            }
        } else {
            // Toggle voltou a favoritar — atualiza o botão
            btn.disabled  = false;
            btn.innerHTML = '⭐ Remover dos Favoritos';
        }
    } catch (err) {
        btn.disabled  = false;
        btn.innerHTML = '⭐ Remover dos Favoritos';
        console.error('Erro ao desfavoritar:', err);
    }
}
</script>

</body>
</html>