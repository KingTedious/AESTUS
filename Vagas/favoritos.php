<?php
/* ============================================================
   Vagas/favoritos.php — Vagas favoritas do jovem
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/conta_status.php";   // Stage 7: vagas fora do ar

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
         f.criado_em AS favoritado_em,
         " . jc_vaga_visivel_expr($conn, 'v') . " AS visivel
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
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>
<?= csrf_js() ?>

<!-- Navbar interna -->
<nav class="navbar-inner">
    <a href="../dashboard/jovem.php" class="nav-logo" aria-label="JovemConnect — meu painel">
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
        <!-- Mesmos atalhos do painel do jovem -->
        <a href="../candidaturas/minhas.php" class="icon-btn" title="Minhas candidaturas" aria-label="Minhas candidaturas"><span class="i i-clipboard-list" aria-hidden="true"></span></a>
        <a href="favoritos.php" class="icon-btn" title="Vagas favoritas" aria-label="Vagas favoritas" aria-current="page"><span class="i i-star" aria-hidden="true"></span></a>
        <a href="../jovem/perfil.php" class="icon-btn" title="Meu Perfil" aria-label="Meu Perfil"><span class="i i-user" aria-hidden="true"></span></a>
        <a href="../logout.php" class="icon-btn text-danger" title="Sair" aria-label="Sair"><span class="i i-log-out" aria-hidden="true"></span></a>
    </div>
</nav>

<div class="dashboard-wrap">
<main class="dashboard-main">

    <!-- Toast de feedback -->
    <?php if ($removido): ?>
    <div id="toast" class="toast toast-success"><span class="i i-check-circle" aria-hidden="true"></span>Vaga removida dos favoritos.</div>
    <?php endif; ?>

    <!-- Caminho de volta explícito -->
    <p style="margin:0 0 12px;"><a href="../dashboard/jovem.php" class="back-link"><span class="i i-arrow-left" aria-hidden="true"></span> Voltar ao painel</a></p>

    <!-- Cabeçalho da página -->
    <div class="page-heading fade-up">
        <div class="d-flex align-center justify-between" style="flex-wrap:wrap;gap:12px;">
            <div>
                <h1><span class="i i-star" aria-hidden="true"></span> Vagas Favoritas</h1>
                <p class="text-secondary" style="margin-top:4px;font-size:14px;">
                    <?= $total ?> vaga<?= $total !== 1 ? 's' : '' ?> salva<?= $total !== 1 ? 's' : '' ?>
                    para revisar mais tarde.
                </p>
            </div>
            <a href="../dashboard/jovem.php" class="btn btn-ghost btn-sm">
                Ver todas as vagas <span class="i i-arrow-right" aria-hidden="true"></span>
            </a>
        </div>
    </div>

    <?php if ($total > 0): ?>

    <div class="items-grid fade-up" id="gridFavoritos">

        <?php while ($row = $result->fetch_assoc()):
            $vaga_id = intval($row['id']);
            if (!intval($row['visivel'])):
                // Stage 7: vaga fora do ar (moderação ou empresa suspensa) — o favorito
                // continua guardado, mas sem os dados da vaga; dá para remover.
        ?>
        <div class="job-card" id="card-fav-<?= $vaga_id ?>">
            <div class="job-card-top">
                <h3 class="job-card-title">Vaga indisponível</h3>
                <span class="badge badge-warning">Fora do ar</span>
            </div>
            <p class="aviso-moderacao"><span class="i i-info" aria-hidden="true"></span>
                <span>Esta vaga saiu do ar: pode estar em revisão pela equipe do JovemConnect ou ter sido retirada.
                Se ela voltar, aparece aqui de novo.</span></p>
            <div class="job-card-footer" style="margin-top:14px;">
                <button type="button" class="btn btn-secondary btn-sm btn-full" onclick="desfavoritar(<?= $vaga_id ?>, this)">
                    <span class="i i-star-cheia" aria-hidden="true"></span> Remover dos Favoritos
                </button>
                <noscript>
                    <form method="POST" action="favoritar.php">
                        <input type="hidden" name="vaga_id" value="<?= $vaga_id ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary btn-sm btn-full">
                            <span class="i i-star-cheia" aria-hidden="true"></span> Remover dos Favoritos
                        </button>
                    </form>
                </noscript>
            </div>
        </div><!-- /.job-card (indisponível) -->
        <?php
                continue;
            endif;
        ?>

        <div class="job-card" id="card-fav-<?= $vaga_id ?>">

            <!-- Título + área -->
            <div class="job-card-top">
                <h3 class="job-card-title">
                    <a href="listar.php?id=<?= $vaga_id ?>&amp;voltar=favoritos"
                       style="color:inherit;text-decoration:none;">
                        <?= htmlspecialchars($row['titulo']) ?>
                    </a>
                </h3>
                <?php if (!empty($row['area'])): ?>
                <span class="badge badge-area"><?= htmlspecialchars($row['area']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Empresa -->
            <div class="job-card-company">
                <span class="i i-building" aria-hidden="true"></span>
                <?= htmlspecialchars($row['nome_empresa']) ?>
            </div>

            <!-- Salário + carga horária -->
            <div class="job-card-meta">
                <?php if (!empty($row['salario'])): ?>
                <div class="job-card-meta-item">
                    <span class="i i-banknote" aria-hidden="true"></span>
                    <span>R$ <?= htmlspecialchars($row['salario']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($row['carga_horaria'])): ?>
                <div class="job-card-meta-item">
                    <span class="i i-clock" aria-hidden="true"></span>
                    <span><?= htmlspecialchars($row['carga_horaria']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Localização -->
            <?php if (!empty($row['cidade'])): ?>
            <div class="job-card-location">
                <span class="i i-map-pin" aria-hidden="true"></span>
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
                <span class="i i-star-cheia" aria-hidden="true"></span> Salvo em <?= date('d/m/Y', strtotime($row['favoritado_em'])) ?>
            </p>
            <?php endif; ?>

            <!-- Ações -->
            <div class="job-card-footer" style="display:flex;flex-direction:column;gap:8px;">

                <!-- Candidatar + Avaliar -->
                <div style="display:flex;gap:8px;">
                    <a href="candidatar.php?vaga=<?= $vaga_id ?>&amp;voltar=favoritos"
                       class="btn btn-primary btn-sm" style="flex:1;justify-content:center;">
                        <span class="i i-send" aria-hidden="true"></span> Candidatar-me
                    </a>
                    <a href="avaliar.php?vaga_id=<?= $vaga_id ?>"
                       class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;">
                        <span class="i i-sparkles" aria-hidden="true"></span> Avaliar IA
                    </a>
                </div>

                <!-- Desfavoritar — POST via AJAX com fallback form -->
                <button
                    type="button"
                    class="btn btn-secondary btn-sm btn-full"
                    onclick="desfavoritar(<?= $vaga_id ?>, this)">
                    <span class="i i-star-cheia" aria-hidden="true"></span> Remover dos Favoritos
                </button>

                <!-- Fallback sem JS: form POST convencional -->
                <noscript>
                    <form method="POST" action="favoritar.php">
                        <input type="hidden" name="vaga_id" value="<?= $vaga_id ?>">
            <?= csrf_field() ?>
                        <button type="submit"
                                class="btn btn-secondary btn-sm btn-full">
                            <span class="i i-star-cheia" aria-hidden="true"></span> Remover dos Favoritos
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
        <div class="icone-destaque" aria-hidden="true"><span class="i i-star" aria-hidden="true"></span></div>
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

<!-- FABs + Assistente + Acessibilidade -->
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>

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
    // Mensagens de erro ficam mais tempo e são anunciadas:
    // o texto volta um instante depois do role — região "viva" que já nasce com texto não é lida
    const ehErro = toast.classList.contains('toast-error');
    const conteudo = [...toast.childNodes];   // Ícone + texto
    toast.replaceChildren();
    toast.setAttribute('role', ehErro ? 'alert' : 'status');
    setTimeout(() => { toast.replaceChildren(...conteudo); toast.classList.add('toast-visible'); }, 100);
    setTimeout(() => toast.classList.remove('toast-visible'), ehErro ? 7000 : 4000);
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
                                <div class="icone-destaque" aria-hidden="true"><span class="i i-star" aria-hidden="true"></span></div>
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
            btn.innerHTML = '<span class="i i-star-cheia" aria-hidden="true"></span> Remover dos Favoritos';
        }
    } catch (err) {
        btn.disabled  = false;
        btn.innerHTML = '<span class="i i-star-cheia" aria-hidden="true"></span> Remover dos Favoritos';
        console.error('Erro ao desfavoritar:', err);
        window.jcAviso?.('Não foi possível remover dos favoritos. Tente de novo.');
    }
}
</script>

</body>
</html>