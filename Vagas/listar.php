<?php
/* ============================================================
   Vagas/listar.php — Dual-mode:
   - GET sem ?id  → listagem com filtros
   - GET com ?id  → página de detalhes da vaga
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/locais.php";   // Local efetivo da vaga
require_once dirname(__DIR__) . "/includes/conta_status.php";   // Stage 7: visibilidade (moderação/suspensão)
require_once dirname(__DIR__) . "/includes/moderacao.php";      // Stage 7: motivos de denúncia
require_once dirname(__DIR__) . "/includes/card_vaga.php";      // card de vaga padrão (modelo do painel)

$jovem_id = intval($_SESSION['jovem_id']);

/* ============================================================
   MODO: DETAIL VIEW  (GET ?id=X)
   ============================================================ */
if (isset($_GET['id']) && intval($_GET['id']) > 0) {

    $vaga_id = intval($_GET['id']);

    // O "←" voltava sempre para a listagem, mesmo vindo do painel, dos
    // favoritos ou das candidaturas. Agora volta para a origem — lista FIXA de
    // destinos (qualquer outro valor cai na listagem, nunca numa URL externa).
    $destinos_voltar = [
        'painel'       => ['../dashboard/jovem.php',     'Voltar ao painel'],
        'favoritos'    => ['favoritos.php',              'Voltar aos favoritos'],
        'candidaturas' => ['../candidaturas/minhas.php', 'Voltar às minhas candidaturas'],
    ];
    $voltar_chave = is_string($_GET['voltar'] ?? null) && isset($destinos_voltar[$_GET['voltar']]) ? $_GET['voltar'] : '';
    [$voltar_url, $voltar_rotulo] = $destinos_voltar[$voltar_chave] ?? ['listar.php', 'Voltar para a lista de vagas'];
    $voltar_qs = ($voltar_chave !== '') ? '&voltar=' . $voltar_chave : '';

    // ── Dados da vaga + empresa ───────────────────────────────────────────────
    $stmt_v = $conn->prepare(
        "SELECT v.id, v.titulo, v.descricao, v.cidade, v.estado,
                v.salario, v.carga_horaria, v.area, v.tipo_contrato, v.cep,
                e.nome_empresa, e.cidade AS empresa_cidade
         FROM   vagas v
         JOIN   empresas e ON v.empresa_id = e.id
         WHERE  v.id = ?" . jc_vagas_visiveis_sql($conn, 'v') . "
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
    // Local de trabalho efetivo (local da vaga → endereço da empresa)
    $local_vaga = jc_local_efetivo($conn, $vaga_id);

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

    // ── Stage 7: denúncia (só com a migração aplicada) ────────────────────────
    $denuncia_ativa = jc_stage7_disponivel($conn);
    $denuncia_feita = null;
    if ($denuncia_ativa) {
        $stmt_d = $conn->prepare("SELECT criado_em FROM denuncias WHERE vaga_id = ? AND jovem_id = ? LIMIT 1");
        $stmt_d->bind_param("ii", $vaga_id, $jovem_id);
        $stmt_d->execute();
        $denuncia_feita = $stmt_d->get_result()->fetch_assoc();
        $stmt_d->close();
    }
    $denuncia_msgs = [
        'ok'       => ['sucesso', 'check-circle', 'Denúncia enviada. A equipe do JovemConnect vai analisar a vaga. Obrigado por avisar!'],
        'ja'       => ['info', 'info', 'Você já tinha denunciado esta vaga. A denúncia anterior continua em análise.'],
        'limite'   => ['aviso', 'alert-triangle', 'Você atingiu o limite de denúncias de hoje. Tente de novo amanhã ou fale com a equipe pelo Contato.'],
        'invalida' => ['erro', 'x-circle', 'Não foi possível enviar: escolha um motivo e escreva só texto nos detalhes (até 500 caracteres).'],
        'erro'     => ['erro', 'x-circle', 'Não foi possível enviar a denúncia agora. Tente de novo em instantes.'],
    ];
    $denuncia_retorno = is_string($_GET['denuncia'] ?? null) ? ($denuncia_msgs[$_GET['denuncia']] ?? null) : null;

    ?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($vaga['titulo']) ?> | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>
<?= csrf_js() ?>

<div class="detail-bar">
    <div class="d-flex align-center gap-12">
        <a href="<?= htmlspecialchars($voltar_url) ?>" class="icon-btn"
           title="<?= htmlspecialchars($voltar_rotulo) ?>" aria-label="<?= htmlspecialchars($voltar_rotulo) ?>"><span class="i i-arrow-left" aria-hidden="true"></span></a>
        <span class="marca marca-compacta"><img class="marca-tema-escuro" src="../assets/img/marca/jovemconnect-simbolo.png" alt="JovemConnect" width="910" height="910"><img class="marca-tema-claro" src="../assets/img/marca/jovemconnect-simbolo-claro.png" alt="JovemConnect" width="910" height="910" loading="lazy"></span>
        <span class="detail-bar-title">Detalhes da Vaga</span>
    </div>
    <?php if (!$ja_candidatado): ?>
    <a href="candidatar.php?vaga=<?= $vaga_id ?><?= htmlspecialchars($voltar_qs) ?>"
       class="btn btn-primary btn-sm"><span class="i i-send" aria-hidden="true"></span> Candidatar-se</a>
    <?php else: ?>
    <span class="badge badge-success" style="padding:10px 16px;gap:6px;"><span class="i i-check" aria-hidden="true"></span> Candidatura Enviada</span>
    <?php endif; ?>
</div>

<div class="page-progress"><div class="page-progress-fill"></div></div>

<div class="detail-page">

    <!-- ── COLUNA PRINCIPAL ──────────────────────────────────────────────────── -->
    <div class="detail-main">

        <?php if ($denuncia_retorno): ?>
        <div class="alerta alerta-<?= $denuncia_retorno[0] ?>" role="<?= $denuncia_retorno[0] === 'erro' ? 'alert' : 'status' ?>">
            <span class="i i-<?= $denuncia_retorno[1] ?>" aria-hidden="true"></span>
            <span><?= htmlspecialchars($denuncia_retorno[2]) ?></span>
        </div>
        <?php endif; ?>

        <!-- Header da vaga -->
        <div class="detail-vaga-header">
            <?php if (!empty($vaga['area'])): ?>
            <span class="badge badge-area"><?= htmlspecialchars($vaga['area']) ?></span>
            <?php endif; ?>

            <h1 class="detail-vaga-title"><?= htmlspecialchars($vaga['titulo']) ?></h1>

            <div class="detail-vaga-company">
                <span class="i i-building" aria-hidden="true"></span>
                <span><?= htmlspecialchars($vaga['nome_empresa']) ?></span>
            </div>

            <div class="detail-vaga-meta">
                <?php if (!empty($vaga['salario'])): ?>
                <div class="detail-meta-item">
                    <span class="detail-meta-label">SALÁRIO</span>
                    <span class="detail-meta-value">
                        <span class="i i-banknote" aria-hidden="true"></span>
                        R$ <?= htmlspecialchars($vaga['salario']) ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($vaga['carga_horaria'])): ?>
                <div class="detail-meta-item">
                    <span class="detail-meta-label">CARGA HORÁRIA</span>
                    <span class="detail-meta-value">
                        <span class="i i-clock" aria-hidden="true"></span>
                        <?= htmlspecialchars($vaga['carga_horaria']) ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($vaga['cidade'])): ?>
                <div class="detail-meta-item">
                    <span class="detail-meta-label">LOCAL</span>
                    <span class="detail-meta-value">
                        <span class="i i-map-pin" aria-hidden="true"></span>
                        <?= htmlspecialchars($vaga['cidade']) ?>
                        <?= !empty($vaga['estado']) ? ', ' . htmlspecialchars($vaga['estado']) : '' ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($vaga['tipo_contrato'])): ?>
                <!-- O tipo de contrato já era carregado, mas não aparecia -->
                <div class="detail-meta-item">
                    <span class="detail-meta-label">CONTRATO</span>
                    <span class="detail-meta-value">
                        <span class="i i-clipboard-list" aria-hidden="true"></span>
                        <?= htmlspecialchars($vaga['tipo_contrato']) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Descrição -->
        <div class="detail-section">
            <h2 class="detail-section-title"><span class="i i-file-text" aria-hidden="true"></span> Descrição da Vaga</h2>
            <p class="text-secondary" style="font-size:14px;line-height:1.8;">
                <?= nl2br(htmlspecialchars($vaga['descricao'] ?? '')) ?>
            </p>
        </div>

        <!-- Requisitos -->
        <?php if (!empty($requisitos)): ?>
        <div class="detail-section">
            <h2 class="detail-section-title"><span class="i i-list-checks" aria-hidden="true"></span> Requisitos</h2>
            <div class="req-grid">
                <?php foreach ($requisitos as $r): ?>
                <div class="req-item">
                    <span class="i i-check" aria-hidden="true" style="color:var(--accent);"></span>
                    <?= htmlspecialchars($r['requisito']) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Benefícios -->
        <?php if (!empty($beneficios)): ?>
        <div class="detail-section">
            <h2 class="detail-section-title"><span class="i i-gift" aria-hidden="true"></span> Benefícios</h2>
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
                <span class="i i-map-pin" aria-hidden="true"></span>
            </div>
            <div class="map-info">
                <strong><?= htmlspecialchars($vaga['nome_empresa']) ?></strong>
                <!-- Local de trabalho efetivo, com o endereço completo quando existir -->
                <span class="local-origem"><?= htmlspecialchars($local_vaga['rotulo'] ?? '') ?></span>
                <?php if (($local_vaga['texto'] ?? '') !== ''): ?>
                <span><?= htmlspecialchars($local_vaga['texto']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($local_vaga['nivel'] ?? 'indisponivel') !== 'indisponivel'): ?>
        <a href="https://www.google.com/maps/search/<?= urlencode(($local_vaga['nivel'] === 'completo' ? '' : $vaga['nome_empresa'] . ' ') . $local_vaga['texto']) ?>"
           target="_blank" rel="noopener"
           class="btn btn-secondary btn-sm btn-full"
           style="margin-bottom:10px;">
            <span class="i i-external-link" aria-hidden="true"></span> Ver no Google Maps<span class="sr-only"> (abre em nova aba)</span>
        </a>
        <?php endif; ?>

        <?php if (!$ja_candidatado): ?>
        <a href="candidatar.php?vaga=<?= $vaga_id ?><?= htmlspecialchars($voltar_qs) ?>"
           class="btn btn-primary btn-sm btn-full"
           style="margin-bottom:12px;">
            <span class="i i-send" aria-hidden="true"></span> Candidatar-se Agora
        </a>
        <?php else: ?>
        <!-- Leva ao acompanhamento -->
        <a href="../candidaturas/minhas.php" class="btn btn-secondary btn-sm btn-full"
           style="margin-bottom:12px;">
            <span class="i i-check-circle" aria-hidden="true"></span> Já candidatado · ver status
        </a>
        <?php endif; ?>

        <!-- IA Dica -->
        <div class="ia-dica-card">
            <span class="ia-dica-icon i i-sparkles" aria-hidden="true"></span>
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
                <span class="i i-sparkles" aria-hidden="true"></span> Avaliar Compatibilidade
            </a>
            <?php endif; ?>
        </div>

        <?php if ($denuncia_ativa): ?>
        <!-- Stage 7: denunciar a vaga (vai para a fila de moderação; nada é escondido automaticamente) -->
        <?php if ($denuncia_feita): ?>
        <p class="denuncia-feita"><span class="i i-check" aria-hidden="true"></span>
            Você denunciou esta vaga em <?= htmlspecialchars(date('d/m/Y', strtotime($denuncia_feita['criado_em']))) ?>. A equipe vai analisar.</p>
        <?php else: ?>
        <details class="denuncia-card"<?= ($_GET['denuncia'] ?? '') === 'invalida' ? ' open' : '' ?>>
            <summary><span class="i i-alert-triangle" aria-hidden="true"></span> Algo errado com esta vaga? Denunciar</summary>
            <form method="post" action="denunciar.php" class="denuncia-corpo">
                <?= csrf_field() ?>
                <input type="hidden" name="vaga_id" value="<?= $vaga_id ?>">
                <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltar_chave) ?>">
                <fieldset>
                    <legend>Qual é o problema?</legend>
                    <?php foreach (jc_denuncia_motivos() as $cod => $rot): ?>
                    <label class="denuncia-opcao">
                        <input type="radio" name="motivo" value="<?= htmlspecialchars($cod) ?>" required>
                        <span><?= htmlspecialchars($rot) ?></span>
                    </label>
                    <?php endforeach; ?>
                </fieldset>
                <div class="form-group">
                    <label class="form-label" for="denunciaDetalhe">Detalhes (opcional)</label>
                    <textarea id="denunciaDetalhe" name="detalhe" class="form-control" rows="3" maxlength="500"
                              aria-describedby="denunciaAjuda"></textarea>
                    <p class="denuncia-ajuda" id="denunciaAjuda">Até 500 caracteres. Não escreva seus dados pessoais.
                        A empresa não fica sabendo quem denunciou.</p>
                </div>
                <button type="submit" class="btn btn-danger btn-sm btn-full">
                    <span class="i i-send" aria-hidden="true"></span> Enviar denúncia
                </button>
            </form>
        </details>
        <?php endif; ?>
        <?php endif; ?>

    </aside><!-- /.detail-aside -->

</div><!-- /.detail-page -->

<!-- FABs + Assistente + Acessibilidade -->
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>

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
        WHERE 1=1" . jc_vagas_visiveis_sql($conn, 'v') . " {$where_sql}
        ORDER BY v.criado_em DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result     = $stmt->get_result();
$total_vagas = $result->num_rows;
$stmt->close();

// ── Áreas distintas para o filtro lateral ────────────────────────────────────
$areas_result = $conn->query(
    "SELECT DISTINCT v.area FROM vagas v WHERE v.area IS NOT NULL AND v.area != ''" . jc_vagas_visiveis_sql($conn, 'v') . " ORDER BY v.area ASC"
);
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

    <!-- Busca centralizada -->
    <div class="navbar-inner-center">
        <form method="GET" action="listar.php" id="formBusca">
            <!-- Preserva filtros de área ao buscar -->
            <?php if (!empty($area)): ?>
            <input type="hidden" name="area" value="<?= htmlspecialchars($area) ?>">
            <?php endif; ?>
            <div class="search-bar">
                <span class="search-icon i i-search" aria-hidden="true"></span>
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
        <button class="icon-btn btn-theme" id="btnTheme" title="Alternar tema" aria-label="Alternar tema">🌙</button>
        <!-- Mesmos atalhos do painel do jovem -->
        <a href="../candidaturas/minhas.php" class="icon-btn" title="Minhas candidaturas" aria-label="Minhas candidaturas"><span class="i i-clipboard-list" aria-hidden="true"></span></a>
        <a href="favoritos.php" class="icon-btn" title="Vagas favoritas" aria-label="Vagas favoritas"><span class="i i-star" aria-hidden="true"></span></a>
        <a href="../jovem/perfil.php" class="icon-btn" title="Meu Perfil" aria-label="Meu Perfil"><span class="i i-user" aria-hidden="true"></span></a>
        <a href="../logout.php" class="icon-btn text-danger" title="Sair" aria-label="Sair"><span class="i i-log-out" aria-hidden="true"></span></a>
    </div>
</nav>

<div class="dashboard-wrap">

    <!-- Sidebar com filtros -->
    <aside class="dashboard-sidebar">

        <!-- Filtros avançados -->
        <div class="sidebar-card">
            <p class="sidebar-section-title"><span class="i i-filter" aria-hidden="true"></span> Filtros de Área</p>
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
            <p class="sidebar-section-title"><span class="i i-map-pin" aria-hidden="true"></span> Localização</p>
            <form method="GET" action="listar.php">
                <?php if (!empty($busca)): ?>
                <input type="hidden" name="busca" value="<?= htmlspecialchars($busca) ?>">
                <?php endif; ?>
                <?php if (!empty($area)): ?>
                <input type="hidden" name="area" value="<?= htmlspecialchars($area) ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label" for="filtroCidade">Cidade</label>
                    <input type="text" name="cidade" id="filtroCidade" class="form-control"
                           placeholder="Ex: São Paulo"
                           value="<?= htmlspecialchars($cidade) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="filtroEstado">Estado (UF)</label>
                    <input type="text" name="estado" id="filtroEstado" class="form-control"
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

        <!-- Caminho de volta explícito -->
        <p style="margin:0 0 12px;"><a href="../dashboard/jovem.php" class="back-link"><span class="i i-arrow-left" aria-hidden="true"></span> Voltar ao painel</a></p>

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
                jc_card_vaga($vaga, '');   // includes/card_vaga.php (detalhe volta para esta lista)
            endwhile; ?>
        </div><!-- /#gridVagas -->

        <?php else: ?>

        <div class="card fade-up"
             style="text-align:center;padding:56px 32px;max-width:460px;margin:0 auto;">
            <div class="icone-destaque" aria-hidden="true"><span class="i i-search" aria-hidden="true"></span></div>
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
    applyTheme(next); localStorage.setItem('jc-theme', next);
});

/* Toggle favorito: agora vem de includes/card_vaga.php (jc_card_vaga_script) */

/* ── Avaliar Match IA ──────────────────────────────────────── */
async function avaliarMatch(vagaId, btn) {
    btn.disabled  = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Analisando...';
    try {
        const res = await fetch('../IA/avaliar_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'match', vaga_id: vagaId, jovem_id: <?= $jovem_id ?> }),
        });
        if (!res.ok) {
            // Guarda o motivo devolvido pelo servidor (ex.: "Servidor de IA indisponível.")
            const corpo = await res.json().catch(() => ({}));
            // Sem motivo do servidor, mensagem amigável
            throw Object.assign(new Error(corpo.error || 'Não foi possível avaliar agora.'), { amigavel: true });
        }
        const { score } = await res.json();
        btn.replaceChildren(jcIcone('sparkles'), `Match: ${score ?? '--'}%`);   // Ícone + texto
        // Atalho para a análise detalhada (composição da nota, local, contexto)
        const rodape = btn.closest('.job-card-footer');
        if (rodape && !rodape.querySelector('.match-link')) {
            const link = document.createElement('a');
            link.className = 'match-link';
            link.href = `avaliar.php?vaga_id=${vagaId}`;
            link.append(jcIcone('list-checks'), ' Ver análise detalhada');
            rodape.appendChild(link);
        }
        btn.style.color = 'var(--accent)';
        btn.classList.add('match-resultado');   // Resultado legível (sem o esmaecido de desabilitado)
    } catch (err) {
        btn.disabled  = false;
        // Antes o botão só voltava ao normal, sem explicar o que houve
        // Erro de rede/resposta inválida não aparece em inglês ("Failed to fetch")
        btn.replaceChildren(jcIcone('sparkles'), err?.amigavel ? err.message : 'Não foi possível avaliar agora. Tente de novo.');
        btn.title       = 'Clique para tentar novamente';
        setTimeout(() => { btn.innerHTML = '<span class="i i-sparkles" aria-hidden="true"></span> Avaliar Match com IA'; btn.title = ''; }, 3500);
    }
}
</script>
<?php jc_card_vaga_script('favoritar.php'); ?>

</body>
</html>