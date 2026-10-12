<?php
/* ============================================================
   dashboard/jovem.php
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/conta_status.php";   // Stage 7: só vagas visíveis

$jovem_id   = intval($_SESSION['jovem_id']);
$sql_visiveis = jc_vagas_visiveis_sql($conn, 'v');   // '' sem a migração da Stage 7
$jovem_nome = $_SESSION['jovem_nome'] ?? 'Jovem';

// ── Filtro de área via GET ────────────────────────────────────────────────────
// htmlspecialchars() no output, trim() na leitura — prepared stmt na query
$area_filtro = !empty($_GET['area']) ? trim($_GET['area']) : null;

// ── 1. Áreas distintas (chips de filtro do sidebar) ──────────────────────────
$result_areas = $conn->query(
    "SELECT DISTINCT v.area FROM vagas v
     WHERE  v.area IS NOT NULL AND v.area != ''{$sql_visiveis}
     ORDER  BY v.area ASC"
);

// ── 2. Vagas (com ou sem filtro de área) ─────────────────────────────────────
if ($area_filtro) {
    $stmt_vagas = $conn->prepare(
        "SELECT v.id, v.titulo, v.descricao, v.cidade, v.estado,
                v.salario, v.carga_horaria, v.area, v.tipo_contrato,
                e.nome_empresa
         FROM   vagas v
         JOIN   empresas e ON v.empresa_id = e.id
         WHERE  v.area = ?{$sql_visiveis}
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
         WHERE  1 = 1{$sql_visiveis}
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
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>
<?= csrf_js() ?>

<!-- ============================================================
     NAVBAR INTERNA
     ============================================================ -->
<nav class="navbar-inner">
    <a href="jovem.php" class="nav-logo" aria-label="JovemConnect — meu painel">
        <picture class="marca marca-nav">
            <source media="(max-width: 480px)" srcset="../assets/img/marca/jovemconnect-simbolo.png" width="192" height="192">
            <img src="../assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="493" height="160">
        </picture>
    </a>

    <div class="navbar-inner-center">
        <div class="search-bar">
            <span class="search-icon i i-search" aria-hidden="true"></span>
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
        <!-- Páginas existentes que não tinham nenhum link de acesso -->
        <a href="../candidaturas/minhas.php" class="icon-btn" title="Minhas candidaturas" aria-label="Minhas candidaturas"><span class="i i-clipboard-list" aria-hidden="true"></span></a>
        <a href="../Vagas/favoritos.php" class="icon-btn" title="Vagas favoritas" aria-label="Vagas favoritas"><span class="i i-star" aria-hidden="true"></span></a>
        <a href="../jovem/perfil.php"  class="icon-btn" title="Meu Perfil" aria-label="Meu Perfil"><span class="i i-user" aria-hidden="true"></span></a>
        <a href="../logout.php"        class="icon-btn text-danger" title="Sair" aria-label="Sair"><span class="i i-log-out" aria-hidden="true"></span></a>
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
            <p class="sidebar-section-title"><span class="i i-filter" aria-hidden="true"></span> Filtros de Área</p>
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
            <p class="sidebar-section-title"><span class="i i-newspaper" aria-hidden="true"></span> Notícias & Dicas</p>
            <div class="news-list">

                <?php foreach ($noticias as $noticia): ?>
                <div class="news-item">
                    <div class="news-meta">
                        <span class="news-tag"><?= htmlspecialchars($noticia['tag']) ?></span>
                        <span class="news-date"><span class="i i-calendar" aria-hidden="true"></span> <?= htmlspecialchars($noticia['data']) ?></span>
                    </div>
                    <p class="news-title"><?= htmlspecialchars($noticia['titulo']) ?></p>
                    <p class="news-excerpt"><?= htmlspecialchars($noticia['resumo']) ?></p>
                </div>
                <?php endforeach; ?>

                <!-- Era um link para "#" (não levava a lugar nenhum); as notícias ainda são fixas -->
                <span class="news-see-all" style="cursor:default;opacity:.75;">Mais notícias em breve</span>

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
                 onclick="location.href='../Vagas/listar.php?id=<?= intval($vaga['id']) ?>&amp;voltar=painel'"
                 style="cursor:pointer;"
                 title="Ver detalhes da vaga">

                <!-- Título + badge de área -->
                <div class="job-card-top">
                    <h3 class="job-card-title">
                        <!-- Link de verdade — antes só o clique do mouse no card abria a vaga
                             (pelo teclado não havia como chegar aos detalhes) -->
                        <a href="../Vagas/listar.php?id=<?= intval($vaga['id']) ?>&amp;voltar=painel"
                           onclick="event.stopPropagation()"
                           style="color:inherit;text-decoration:none;">
                            <?= htmlspecialchars($vaga['titulo']) ?>
                        </a>
                    </h3>
                    <?php if (!empty($vaga['area'])): ?>
                    <span class="badge badge-area"><?= htmlspecialchars($vaga['area']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Empresa -->
                <div class="job-card-company">
                    <span class="i i-building" aria-hidden="true"></span>
                    <?= htmlspecialchars($vaga['nome_empresa']) ?>
                </div>

                <!-- Salário + carga horária -->
                <div class="job-card-meta">
                    <?php if (!empty($vaga['salario'])): ?>
                    <div class="job-card-meta-item">
                        <span class="i i-banknote" aria-hidden="true"></span>
                        <span>R$ <?= htmlspecialchars($vaga['salario']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($vaga['carga_horaria'])): ?>
                    <div class="job-card-meta-item">
                        <span class="i i-clock" aria-hidden="true"></span>
                        <span><?= htmlspecialchars($vaga['carga_horaria']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Localização -->
                <?php if (!empty($vaga['cidade'])): ?>
                <div class="job-card-location">
                    <span class="i i-map-pin" aria-hidden="true"></span>
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
                        <span class="i i-sparkles" aria-hidden="true"></span> Avaliar Match com IA
                    </button>
                </div>

            </div><!-- /.job-card -->

            <?php endwhile; ?>
        </div><!-- /#gridVagas -->

        <?php else: ?>

        <!-- Estado vazio -->
        <div class="card fade-up"
             style="text-align:center;padding:56px 32px;max-width:460px;margin:0 auto;">
            <div class="icone-destaque" aria-hidden="true"><span class="i i-briefcase" aria-hidden="true"></span></div>
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
     ASSISTENTE + ACESSIBILIDADE + FABs
     Este painel tinha uma cópia própria do chat e do painel de
     acessibilidade, com outras chaves no localStorage (a preferência ligada
     aqui se perdia nas outras páginas). Agora usa o mesmo componente de
     todas as páginas.
     ============================================================ -->
<?php
$assistente_boas_vindas = 'Oi! 👋 Posso te ajudar a entender como funcionam as vagas, a avaliação de '
    . 'compatibilidade da IA, ou qualquer dúvida sobre o JovemConnect. Como posso ajudar?';
include dirname(__DIR__) . '/includes/assistente.php';
?>

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

        if (!res.ok) {
            // Guarda o motivo devolvido pelo servidor (ex.: "Servidor de IA indisponível.")
            const corpo = await res.json().catch(() => ({}));
            // Sem motivo do servidor, mensagem amigável
            throw Object.assign(new Error(corpo.error || 'Não foi possível avaliar agora.'), { amigavel: true });
        }

        const { score, analise } = await res.json();

        btn.replaceChildren(jcIcone('sparkles'), `Match: ${score ?? '--'}%`);   // Ícone + texto
        // Atalho para a análise detalhada (composição da nota, local, contexto)
        const rodape = btn.closest('.job-card-footer');
        if (rodape && !rodape.querySelector('.match-link')) {
            const link = document.createElement('a');
            link.className = 'match-link';
            link.href = `../Vagas/avaliar.php?vaga_id=${vagaId}`;
            link.append(jcIcone('list-checks'), ' Ver análise detalhada');
            rodape.appendChild(link);
        }
        btn.style.color = 'var(--accent)';
        btn.classList.add('match-resultado');   // Resultado legível (sem o esmaecido de desabilitado)
        btn.title       = analise ?? '';

    } catch (err) {
        btn.disabled  = false;
        // Antes o botão só voltava ao normal, sem explicar o que houve
        // Erro de rede/resposta inválida não aparece em inglês ("Failed to fetch")
        btn.replaceChildren(jcIcone('sparkles'), err?.amigavel ? err.message : 'Não foi possível avaliar agora. Tente de novo.');
        btn.title       = 'Clique para tentar novamente';
        setTimeout(() => { btn.innerHTML = '<span class="i i-sparkles" aria-hidden="true"></span> Avaliar Match com IA'; btn.title = ''; }, 3500);
        console.error('Erro no match IA:', err);
    }
}

/* Chat e acessibilidade agora vêm de includes/assistente.php */

/* Confirmação depois da verificação da conta (o aviso se perdia no caminho) */
(function () {
    const v = new URLSearchParams(location.search).get('verificado');
    if (v !== 'email' && v !== 'telefone') return;
    window.jcAviso?.(v === 'email' ? 'E-mail verificado com sucesso!' : 'Telefone verificado com sucesso!', 'sucesso');
    history.replaceState(null, '', location.pathname);   // F5 não repete o aviso
})();

</script>

<?php $stmt_vagas->close(); ?>
</body>
</html>