<?php
/* ============================================================
   dashboard/empresa.php
   ============================================================ */
session_start();
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
$stmt_cands = $conn->prepare(
    "SELECT DISTINCT
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
     JOIN   candidaturas c ON c.jovem_id = j.id
     JOIN   vagas        v ON c.vaga_id  = v.id
     WHERE  v.empresa_id   = ?
       AND  j.perfil_publico = 1
     ORDER  BY c.criado_em DESC"
);
$stmt_cands->bind_param("i", $empresa_id);
$stmt_cands->execute();
$result_cands = $stmt_cands->get_result();

// Fallback: buscar por área compatível se não há candidatos ainda
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
}

$total_cands = $result_cands->num_rows;
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

            <!-- Botão de match IA -->
            <button
                class="btn btn-ia"
                onclick="avaliarMatch(<?= intval($jovem['id']) ?>, this)"
                data-jovem="<?= intval($jovem['id']) ?>">
                ✦ Avaliar Match com IA
            </button>

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
<div class="user-indicator" title="<?= htmlspecialchars($empresa_nome) ?>">
    <?= strtoupper(mb_substr($empresa_nome, 0, 1)) ?>
</div>


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
async function avaliarMatch(jovemId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Analisando...';

    try {
        const res = await fetch('../IA/avaliar_ajax.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                jovem_id:   jovemId,
                empresa_id: <?= $empresa_id ?>
            })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const { score, analise } = await res.json();

        btn.innerHTML   = `✦ Match: ${score ?? '--'}%`;
        btn.style.color = 'var(--accent)';
        btn.title       = analise ?? '';

    } catch (err) {
        btn.innerHTML = '✦ Avaliar Match com IA';
        btn.disabled  = false;
        console.error('Erro no match IA:', err);
    }
}
</script>

<?php
$stmt_vagas->close();
$stmt_cands->close();
?>
</body>
</html>