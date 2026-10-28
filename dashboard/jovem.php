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
require_once dirname(__DIR__) . "/includes/card_vaga.php";      // card de vaga padrão (com favorito)

$jovem_id   = intval($_SESSION['jovem_id']);
$sql_visiveis = jc_vagas_visiveis_sql($conn, 'v');   // '' sem a migração da Stage 7
$jovem_nome = $_SESSION['jovem_nome'] ?? 'Jovem';

// ── O painel mostra só as vagas mais recentes ─────────────────────────────────
// A lista completa, com busca, filtros (área, cidade/UF) e favoritas, fica em
// Vagas/listar.php — antes o painel repetia a mesma lista, sem esses recursos.
$limite_painel = 6;

// ── 1. Áreas distintas (atalhos "Explorar por Área" → lista completa filtrada) ─
$result_areas = $conn->query(
    "SELECT DISTINCT v.area FROM vagas v
     WHERE  v.area IS NOT NULL AND v.area != ''{$sql_visiveis}
     ORDER  BY v.area ASC"
);

// ── 2. Total de vagas visíveis (o mesmo número que a lista completa mostra) ───
$res_total = $conn->query(
    "SELECT COUNT(*) AS total
     FROM   vagas v
     JOIN   empresas e ON v.empresa_id = e.id
     WHERE  1 = 1{$sql_visiveis}"
);
$total_vagas = $res_total ? (int) $res_total->fetch_assoc()['total'] : 0;

// ── 3. As mais recentes ───────────────────────────────────────────────────────
$stmt_vagas = $conn->prepare(
    "SELECT v.id, v.titulo, v.descricao, v.cidade, v.estado,
            v.salario, v.carga_horaria, v.area, v.tipo_contrato,
            e.nome_empresa,
            IF(f.id IS NOT NULL, 1, 0) AS eh_favorito
     FROM   vagas v
     JOIN   empresas e ON v.empresa_id = e.id
     LEFT JOIN favoritos f
            ON f.vaga_id = v.id AND f.jovem_id = ?
     WHERE  1 = 1{$sql_visiveis}
     ORDER  BY v.criado_em DESC, v.id DESC
     LIMIT  {$limite_painel}"
);
$stmt_vagas->bind_param("i", $jovem_id);   // estrela: favoritos DESTE jovem (sessão)
$stmt_vagas->execute();
$result_vagas = $stmt_vagas->get_result();
$qtd_exibidas = $result_vagas->num_rows;

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
    <title>Meu Painel | JovemConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
        <picture class="marca marca-nav marca-tema-escuro">
            <source media="(max-width: 480px)" srcset="../assets/img/marca/jovemconnect-simbolo.png" width="910" height="910">
            <img src="../assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="848" height="291">
        </picture>
        <picture class="marca marca-nav marca-tema-claro">
            <source media="(max-width: 480px)" srcset="../assets/img/marca/jovemconnect-simbolo-claro.png" width="910" height="910">
            <img src="../assets/img/marca/jovemconnect-horizontal-claro.png" alt="JovemConnect" width="848" height="291" loading="lazy">
        </picture>
    </a>

    <div class="navbar-inner-center">
        <!-- A busca procura em todas as vagas, na lista completa (o painel mostra só as mais recentes) -->
        <form method="GET" action="../Vagas/listar.php" role="search">
            <div class="search-bar">
                <span class="search-icon i i-search" aria-hidden="true"></span>
                <input
                    type="search"
                    name="busca"
                    id="searchVagas"
                    placeholder="Buscar vagas..."
                    autocomplete="off"
                    aria-label="Buscar vagas">
            </div>
        </form>
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

        <!-- Explorar por Área: atalhos para a lista completa já filtrada
             (o filtro fica em Vagas/listar.php, junto da busca e das favoritas) -->
        <div class="sidebar-card">
            <p class="sidebar-section-title"><span class="i i-filter" aria-hidden="true"></span> Explorar por Área</p>
            <div class="filter-wrap">

                <a href="../Vagas/listar.php" class="filter-chip">
                    Todos
                </a>

                <?php if ($result_areas && $result_areas->num_rows > 0):
                    while ($row = $result_areas->fetch_assoc()): ?>
                <a href="../Vagas/listar.php?area=<?= urlencode($row['area']) ?>" class="filter-chip">
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
            <h1>Vagas Recentes</h1>
            <?php if ($total_vagas > 0): ?>
            <p class="text-secondary">
                <?php if ($total_vagas > $qtd_exibidas): ?>
                    As <?= $qtd_exibidas ?> vagas mais recentes, de <?= $total_vagas ?> disponíveis.
                <?php else: ?>
                    <?= $total_vagas ?> vaga<?= $total_vagas !== 1 ? 's disponíveis' : ' disponível' ?>.
                <?php endif; ?>
                Para buscar, filtrar por área ou cidade e salvar favoritas, veja todas as vagas.
            </p>
            <?php endif; ?>
        </div>

        <?php if ($total_vagas > 0): ?>

        <div class="items-grid fade-up" id="gridVagas">
            <?php while ($vaga = $result_vagas->fetch_assoc()):
                jc_card_vaga($vaga, '../Vagas/', 'painel');   // includes/card_vaga.php
            endwhile; ?>
        </div><!-- /#gridVagas -->

        <!-- Lista completa: busca, filtros por área e cidade, favoritas -->
        <div class="fade-up" style="text-align:center;margin-top:24px;">
            <a href="../Vagas/listar.php" class="btn btn-primary">
                Ver todas as vagas<?= $total_vagas > $qtd_exibidas ? ' (' . $total_vagas . ')' : '' ?>
                <span class="i i-arrow-right" aria-hidden="true"></span>
            </a>
        </div>

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
                Ainda não há vagas cadastradas na plataforma.
            </p>
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

/* ── Busca de vagas: o campo do topo envia para a lista completa (Vagas/listar.php?busca=...) ── */

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

<?php jc_card_vaga_script('../Vagas/favoritar.php'); ?>
<?php $stmt_vagas->close(); ?>
</body>
</html>