<?php
/* ============================================================
   Vagas/candidatos.php — Gestão de candidatos de uma vaga

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. session_start() depois do include config.php.
   2. SQL Injection em TODAS as queries:
      $vaga_id, $candidatura_id, $novo_status direto no SQL.
   3. FALHA CRÍTICA DE AUTORIZAÇÃO: o UPDATE de status não
      verificava se a candidatura pertencia a uma vaga DESTA
      empresa. Qualquer empresa logada podia alterar o status
      de candidatos de outra empresa.
   4. Sem validação de ownership da vaga no GET (qualquer
      empresa podia ver candidatos de vagas alheias).
   5. Status 'Rejeitado' diverge do schema (é 'Reprovado').
   6. Sem PRG no POST → F5 reenviava o form de status.
   7. Sem htmlspecialchars() nos outputs.
   8. include header.php após queries e <link> no body.
   9. Campos v3.00 ausentes: area_interesse, escolaridade_tipo,
      resumo_profissional, idade.
   ============================================================ */
session_start();

if (!isset($_SESSION['empresa_id'])) {
    header("Location: ../empresa/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$empresa_id = intval($_SESSION['empresa_id']);

// Aceita ?vaga= ou ?id= (compatibilidade com links do perfil da empresa)
$vaga_id = intval($_GET['vaga'] ?? $_GET['id'] ?? 0);

if ($vaga_id <= 0) {
    header("Location: ../empresa/perfil.php");
    exit;
}

// ── Verifica que a vaga pertence a ESTA empresa ──────────────────────────────
// (Ownership check — corrige a falha de autorização do original)
$stmt_vaga = $conn->prepare(
    "SELECT id, titulo, area, cidade, estado, salario, carga_horaria
     FROM   vagas
     WHERE  id = ? AND empresa_id = ?
     LIMIT  1"
);
$stmt_vaga->bind_param("ii", $vaga_id, $empresa_id);
$stmt_vaga->execute();
$vaga = $stmt_vaga->get_result()->fetch_assoc();
$stmt_vaga->close();

// Se não encontrou: vaga inexistente ou de outra empresa → nega acesso
if (!$vaga) {
    header("Location: ../empresa/perfil.php");
    exit;
}

// ── Status válidos (enum implícito — alinhado ao schema v3.00) ───────────────
$statuses_validos = ['Recebido', 'Visualizado', 'Em análise', 'Aprovado', 'Reprovado'];

/* ============================================================
   AJAX: atualização de status (chamada pelo JS do card)
   Retorna JSON — não renderiza HTML.
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {

    header('Content-Type: application/json; charset=utf-8');

    $candidatura_id = intval($_POST['candidatura_id'] ?? 0);
    $novo_status    = trim($_POST['status']           ?? '');

    if ($candidatura_id <= 0 || !in_array($novo_status, $statuses_validos, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos.']);
        exit;
    }

    // UPDATE com JOIN garante que só atualiza candidaturas de vagas desta empresa
    $stmt_upd = $conn->prepare(
        "UPDATE candidaturas c
         JOIN   vagas v ON c.vaga_id = v.id
         SET    c.status = ?
         WHERE  c.id = ?
           AND  v.empresa_id = ?"
    );
    $stmt_upd->bind_param("sii", $novo_status, $candidatura_id, $empresa_id);
    $ok = $stmt_upd->execute();
    $affected = $stmt_upd->affected_rows;
    $stmt_upd->close();

    if ($ok && $affected > 0) {
        echo json_encode(['ok' => true, 'status' => $novo_status]);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão ou candidatura não encontrada.']);
    }
    exit;
}

// ── POST convencional (fallback sem JS) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $candidatura_id = intval($_POST['candidatura_id'] ?? 0);
    $novo_status    = trim($_POST['status']           ?? '');

    if ($candidatura_id > 0 && in_array($novo_status, $statuses_validos, true)) {
        $stmt_upd = $conn->prepare(
            "UPDATE candidaturas c
             JOIN   vagas v ON c.vaga_id = v.id
             SET    c.status = ?
             WHERE  c.id = ?
               AND  v.empresa_id = ?"
        );
        $stmt_upd->bind_param("sii", $novo_status, $candidatura_id, $empresa_id);
        $stmt_upd->execute();
        $stmt_upd->close();
    }

    header("Location: candidatos.php?vaga={$vaga_id}&saved=1");
    exit;
}

$saved = intval($_GET['saved'] ?? 0);

// ── Busca candidatos desta vaga ───────────────────────────────────────────────
$stmt_c = $conn->prepare(
    "SELECT
         j.id              AS jovem_id,
         j.nome,
         j.email,
         j.cidade,
         j.estado,
         j.area_interesse,
         j.escolaridade_tipo,
         j.escolaridade_curso,
         j.resumo_profissional,
         TIMESTAMPDIFF(YEAR, j.data_nascimento, CURDATE()) AS idade,
         c.id              AS cand_id,
         c.status,
         c.criado_em
     FROM candidaturas c
     JOIN jovens j ON c.jovem_id = j.id
     WHERE c.vaga_id = ?
     ORDER BY c.criado_em DESC"
);
$stmt_c->bind_param("i", $vaga_id);
$stmt_c->execute();
$result = $stmt_c->get_result();
$total  = $result->num_rows;
$stmt_c->close();
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidatos — <?= htmlspecialchars($vaga['titulo']) ?> | JovemConnect</title>
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
    <div class="navbar-inner-right">
        <button class="icon-btn btn-theme" id="btnTheme" title="Alternar tema">🌙</button>
        <a href="../empresa/perfil.php" class="icon-btn" title="Perfil">👤</a>
        <a href="../logout.php" class="icon-btn text-danger" title="Sair">↪</a>
    </div>
</nav>

<!-- ============================================================
     CONTEÚDO
     ============================================================ -->
<div class="dashboard-wrap">
<main class="dashboard-main">

    <!-- Toast de feedback (fallback PRG) -->
    <?php if ($saved): ?>
    <div id="toast" class="toast toast-success">✓ Status atualizado com sucesso!</div>
    <?php endif; ?>

    <!-- Cabeçalho: info da vaga -->
    <div class="page-heading fade-up">
        <div class="d-flex align-center justify-between" style="flex-wrap:wrap;gap:12px;">
            <div>
                <div class="d-flex align-center gap-8" style="margin-bottom:6px;">
                    <a href="../empresa/perfil.php" class="text-muted"
                       style="font-size:13px;text-decoration:none;">← Voltar</a>
                    <?php if (!empty($vaga['area'])): ?>
                    <span class="badge badge-area"><?= htmlspecialchars($vaga['area']) ?></span>
                    <?php endif; ?>
                </div>
                <h1><?= htmlspecialchars($vaga['titulo']) ?></h1>
                <p class="text-secondary" style="margin-top:4px;font-size:14px;">
                    <?= $total ?> candidato<?= $total !== 1 ? 's' : '' ?> inscrito<?= $total !== 1 ? 's' : '' ?>
                    <?php if (!empty($vaga['cidade'])): ?>
                    • <?= htmlspecialchars($vaga['cidade']) ?>
                    <?= !empty($vaga['estado']) ? ', ' . htmlspecialchars($vaga['estado']) : '' ?>
                    <?php endif; ?>
                    <?php if (!empty($vaga['salario'])): ?>
                    • R$ <?= htmlspecialchars($vaga['salario']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <a href="../Vagas/criar.php" class="btn btn-primary btn-sm">+ Nova Vaga</a>
        </div>
    </div>

    <?php if ($total > 0): ?>

    <div class="items-grid fade-up" id="gridCandidatos">
        <?php while ($row = $result->fetch_assoc()):
            $inicial     = strtoupper(mb_substr($row['nome'], 0, 1));
            $idade_label = $row['idade'] !== null ? intval($row['idade']) . ' anos' : '';
            $local_parts = array_filter([
                htmlspecialchars($row['cidade'] ?? ''),
                htmlspecialchars($row['estado'] ?? ''),
            ]);
            $localizacao = implode(', ', $local_parts);
            $resumo = $row['resumo_profissional']
                ? htmlspecialchars(mb_substr($row['resumo_profissional'], 0, 120)) . '...'
                : 'Busco oportunidade na área de '
                  . htmlspecialchars($row['area_interesse'] ?: 'aprendizado')
                  . ' para aplicar meus conhecimentos e crescer profissionalmente...';
            $cand_id = intval($row['cand_id']);
        ?>
        <div class="candidate-card" id="card-<?= $cand_id ?>">

            <!-- Cabeçalho -->
            <div class="candidate-card-head">
                <div class="candidate-card-info">
                    <div class="avatar avatar-md"><?= $inicial ?></div>
                    <div>
                        <div class="candidate-name"><?= htmlspecialchars($row['nome']) ?></div>
                        <div class="candidate-sub">
                            <?= $idade_label ?><?= ($idade_label && $localizacao) ? ' • ' : '' ?><?= $localizacao ?>
                        </div>
                    </div>
                </div>
                <!-- Badge de status (atualizado via JS) -->
                <span class="badge <?= statusClass($row['status']) ?>"
                      id="badge-<?= $cand_id ?>">
                    <?= htmlspecialchars($row['status']) ?>
                </span>
            </div>

            <!-- Área / curso -->
            <?php
            $area_display = '';
            if ($row['escolaridade_tipo'] === 'tecnico' && !empty($row['escolaridade_curso'])) {
                $area_display = 'Técnico em ' . htmlspecialchars($row['escolaridade_curso']);
            } elseif (!empty($row['area_interesse'])) {
                $area_display = htmlspecialchars($row['area_interesse']);
            }
            ?>
            <?php if ($area_display): ?>
            <div class="candidate-area">
                <span>🎓</span>
                <span><?= $area_display ?></span>
            </div>
            <?php endif; ?>

            <!-- Resumo -->
            <p class="candidate-bio"><?= $resumo ?></p>

            <!-- Candidatado em -->
            <?php if (!empty($row['criado_em'])): ?>
            <p class="text-muted" style="font-size:11px;margin-bottom:14px;">
                Inscrito em <?= date('d/m/Y', strtotime($row['criado_em'])) ?>
            </p>
            <?php endif; ?>

            <!-- Ações -->
            <div style="display:flex;flex-direction:column;gap:8px;">

                <!-- Select de status (AJAX) -->
                <div style="display:flex;gap:8px;align-items:center;">
                    <select
                        class="form-control"
                        style="flex:1;font-size:13px;padding:10px 14px;"
                        id="sel-<?= $cand_id ?>"
                        aria-label="Alterar status de <?= htmlspecialchars($row['nome']) ?>">
                        <?php foreach ($statuses_validos as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>"
                            <?= $row['status'] === $s ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button
                        class="btn btn-primary btn-sm"
                        style="white-space:nowrap;"
                        onclick="atualizarStatus(<?= $cand_id ?>, this)">
                        Salvar
                    </button>
                </div>

                <!-- Botões de navegação -->
                <div style="display:flex;gap:8px;">
                    <a href="../jovem/perfil.php?id=<?= intval($row['jovem_id']) ?>"
                       class="btn btn-ghost btn-sm" style="flex:1;justify-content:center;">
                        Ver Perfil
                    </a>
                    <button
                        class="btn btn-ia"
                        style="flex:1;"
                        onclick="avaliarMatch(<?= intval($row['jovem_id']) ?>, <?= $vaga_id ?>, this)">
                        ✦ Match com IA
                    </button>
                </div>

                <!-- Fallback sem JS -->
                <noscript>
                    <form method="POST" action="candidatos.php?vaga=<?= $vaga_id ?>">
                        <input type="hidden" name="candidatura_id" value="<?= $cand_id ?>">
                        <input type="hidden" name="status" value="Em análise">
                        <button type="submit" class="btn btn-ghost btn-sm btn-full">
                            Salvar (sem JS)
                        </button>
                    </form>
                </noscript>

            </div>

        </div><!-- /.candidate-card -->
        <?php endwhile; ?>
    </div><!-- /#gridCandidatos -->

    <?php else: ?>

    <div class="card fade-up"
         style="text-align:center;padding:56px 32px;max-width:440px;margin:0 auto;">
        <div style="font-size:44px;margin-bottom:18px;">👥</div>
        <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">
            Nenhum candidato ainda
        </h3>
        <p class="text-secondary" style="font-size:14px;line-height:1.6;margin-bottom:28px;">
            Quando jovens se candidatarem a esta vaga, eles aparecerão aqui.
        </p>
        <a href="../dashboard/empresa.php" class="btn btn-primary btn-sm">
            Ver Dashboard
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

/* ── Toast de feedback ─────────────────────────────────────── */
const toast = document.getElementById('toast');
if (toast) {
    setTimeout(() => toast.classList.add('toast-visible'), 100);
    setTimeout(() => toast.classList.remove('toast-visible'), 3500);
}

/* ── Cores dos badges (espelho do PHP statusClass) ─────────── */
function badgeClass(status) {
    const map = {
        'Aprovado':   'badge-success',
        'Reprovado':  'badge-danger',
        'Em análise': 'badge-warning',
        'Visualizado':'badge-info',
    };
    return 'badge ' + (map[status] || '');
}

/* ── Atualizar status via AJAX ─────────────────────────────── */
async function atualizarStatus(candId, btn) {
    const sel    = document.getElementById('sel-' + candId);
    const status = sel.value;

    btn.disabled  = true;
    btn.innerHTML = '<span class="loading-spinner"></span>';

    try {
        const fd = new FormData();
        fd.append('candidatura_id', candId);
        fd.append('status', status);

        const res = await fetch('candidatos.php?vaga=<?= $vaga_id ?>', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    fd,
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);

        const data = await res.json();

        if (data.ok) {
            // Atualiza o badge sem recarregar a página
            const badge = document.getElementById('badge-' + candId);
            if (badge) {
                badge.className = badgeClass(status);
                badge.textContent = status;
            }
            // Feedback visual no botão
            btn.innerHTML = '✓ Salvo';
            btn.style.background = 'rgba(74,222,128,.15)';
            btn.style.color      = '#4ade80';
            setTimeout(() => {
                btn.innerHTML = 'Salvar';
                btn.style.background = '';
                btn.style.color      = '';
                btn.disabled = false;
            }, 2000);
        } else {
            throw new Error(data.error || 'Erro desconhecido');
        }
    } catch (err) {
        btn.innerHTML = '✕ Erro';
        btn.style.color = '#f87171';
        setTimeout(() => {
            btn.innerHTML = 'Salvar';
            btn.style.color  = '';
            btn.disabled = false;
        }, 2500);
        console.error('Erro ao atualizar status:', err);
    }
}

/* ── Avaliar Match com IA ───────────────────────────────────── */
async function avaliarMatch(jovemId, vagaId, btn) {
    btn.disabled  = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Analisando...';

    try {
        const res = await fetch('../IA/avaliar_ajax.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action:     'match',
                jovem_id:   jovemId,
                vaga_id:    vagaId,
                empresa_id: <?= $empresa_id ?>
            }),
        });

        if (!res.ok) throw new Error('HTTP ' + res.status);

        const { score, analise } = await res.json();

        btn.innerHTML   = `✦ Match: ${score ?? '--'}%`;
        btn.title       = analise ?? '';
        btn.style.color = 'var(--accent)';

        // Link para a análise completa (Vagas/avaliar.php, Etapa 3) — o botão só
        // mostra o score; motivos individuais só existem na tooltip do title.
        if (!btn.nextElementSibling?.classList.contains('link-analise-completa')) {
            const linkAnalise = document.createElement('a');
            linkAnalise.href      = `avaliar.php?vaga_id=${vagaId}&jovem_id=${jovemId}`;
            linkAnalise.className = 'link-analise-completa back-link';
            linkAnalise.style.cssText = 'display:block;margin-top:8px;font-size:12px;';
            linkAnalise.textContent = 'Ver análise completa →';
            btn.after(linkAnalise);
        }
    } catch {
        btn.innerHTML = '✦ Match com IA';
        btn.disabled  = false;
    }
}
</script>

</body>
</html>

<?php
/* Função auxiliar: retorna classe CSS do badge pelo status */
function statusClass(string $status): string {
    $map = [
        'Aprovado'    => 'badge-success',
        'Reprovado'   => 'badge-danger',
        'Em análise'  => 'badge-warning',
        'Visualizado' => 'badge-info',
    ];
    return $map[$status] ?? '';
}
?>