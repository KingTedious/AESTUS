<?php
/* ============================================================
   Vagas/minhas.php — Vagas publicadas pela empresa

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. session_start() depois do include config.php.
   2. SQL Injection: $empresa_id direto na query.
   3. SELECT * sem colunas v3.00 (area, carga_horaria, salario).
   4. $row['criado_at'] → coluna não existe no schema (é criado_em).
   5. Sem htmlspecialchars() nos outputs → XSS.
   6. Delete via GET (excluir.php?id=X que nem existe no projeto).
      GET não deve modificar dados. Qualquer link/bot dispara.
   7. Sem ownership check no delete → empresa A deletaria
      vagas da empresa B passando ?id=X.
   8. Sem contagem de candidatos por vaga.
   9. var(--principal) e var(--borda) não existem no CSS v3.00.
   10. include header.php após queries e <link> no body.
   ============================================================ */
session_start();

if (!isset($_SESSION['empresa_id'])) {
    header("Location: ../empresa/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$empresa_id = intval($_SESSION['empresa_id']);

/* ============================================================
   POST: exclusão de vaga (com ownership check e PRG)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'delete') {

    $vaga_id = intval($_POST['vaga_id'] ?? 0);

    if ($vaga_id > 0) {
        // WHERE empresa_id = ? garante que só exclui vaga DESTA empresa
        // ON DELETE CASCADE no schema elimina automaticamente:
        // candidaturas, favoritos, requisitos_vaga, beneficios_vaga
        $stmt_del = $conn->prepare(
            "DELETE FROM vagas WHERE id = ? AND empresa_id = ?"
        );
        $stmt_del->bind_param("ii", $vaga_id, $empresa_id);
        $stmt_del->execute();
        $stmt_del->close();
    }

    header("Location: minhas.php?deleted=1");
    exit;
}

$deleted = isset($_GET['deleted']);

/* ============================================================
   GET: busca vagas da empresa com contagem de candidatos
   ============================================================ */
$stmt = $conn->prepare(
    "SELECT
         v.id, v.titulo, v.descricao, v.cidade, v.estado,
         v.salario, v.carga_horaria, v.area, v.tipo_contrato,
         v.criado_em,
         COUNT(c.id) AS total_candidatos
     FROM vagas v
     LEFT JOIN candidaturas c ON c.vaga_id = v.id
     WHERE v.empresa_id = ?
     GROUP BY v.id
     ORDER BY v.criado_em DESC"
);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$result     = $stmt->get_result();
$total_vagas = $result->num_rows;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Vagas | JovemConnect</title>
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
        <a href="../empresa/perfil.php" class="icon-btn" title="Perfil">👤</a>
        <a href="../logout.php" class="icon-btn text-danger" title="Sair">↪</a>
    </div>
</nav>

<div class="dashboard-wrap">
<main class="dashboard-main">

    <!-- Toast de feedback -->
    <?php if ($deleted): ?>
    <div id="toast" class="toast toast-success">✓ Vaga removida com sucesso.</div>
    <?php endif; ?>

    <!-- Cabeçalho -->
    <div class="page-heading fade-up">
        <div class="d-flex align-center justify-between" style="flex-wrap:wrap;gap:12px;">
            <div>
                <h1>💼 Minhas Vagas</h1>
                <p class="text-secondary" style="margin-top:4px;font-size:14px;">
                    <?= $total_vagas ?> vaga<?= $total_vagas !== 1 ? 's' : '' ?> publicada<?= $total_vagas !== 1 ? 's' : '' ?>.
                    Gerencie candidatos e acompanhe o desempenho.
                </p>
            </div>
            <a href="criar.php" class="btn btn-primary btn-sm">+ Criar Nova Vaga</a>
        </div>
    </div>

    <?php if ($total_vagas > 0): ?>

    <div style="display:flex;flex-direction:column;gap:16px;" class="fade-up">

        <?php while ($row = $result->fetch_assoc()):
            $vaga_id   = intval($row['id']);
            $n_cands   = intval($row['total_candidatos']);
            $criado_em = !empty($row['criado_em'])
                ? date('d/m/Y', strtotime($row['criado_em']))
                : '—';
        ?>

        <div class="card" style="padding:20px 24px;">

            <!-- Cabeçalho do card -->
            <div class="d-flex align-center justify-between"
                 style="flex-wrap:wrap;gap:12px;margin-bottom:12px;">
                <div style="flex:1;min-width:200px;">
                    <div class="d-flex align-center gap-8" style="margin-bottom:6px;flex-wrap:wrap;">
                        <h3 style="font-size:16px;font-weight:700;margin:0;">
                            <?= htmlspecialchars($row['titulo']) ?>
                        </h3>
                        <?php if (!empty($row['area'])): ?>
                        <span class="badge badge-area"><?= htmlspecialchars($row['area']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($row['tipo_contrato'])): ?>
                        <span class="badge"><?= htmlspecialchars($row['tipo_contrato']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted" style="font-size:12px;">
                        <?php if (!empty($row['cidade'])): ?>
                        📍 <?= htmlspecialchars($row['cidade']) ?>
                        <?= !empty($row['estado']) ? ', ' . htmlspecialchars($row['estado']) : '' ?>
                        <?php endif; ?>
                        <?php if (!empty($row['salario'])): ?>
                        • 💰 R$ <?= htmlspecialchars($row['salario']) ?>
                        <?php endif; ?>
                        <?php if (!empty($row['carga_horaria'])): ?>
                        • 🕐 <?= htmlspecialchars($row['carga_horaria']) ?>
                        <?php endif; ?>
                        • 📅 Publicada em <?= $criado_em ?>
                    </p>
                </div>

                <!-- Badge de candidatos -->
                <div style="
                    background:var(--accent-subtle);
                    border:1px solid var(--accent);
                    border-radius:var(--radius);
                    padding:10px 18px;
                    text-align:center;
                    flex-shrink:0;
                ">
                    <div style="font-size:22px;font-weight:800;color:var(--accent);">
                        <?= $n_cands ?>
                    </div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">
                        candidato<?= $n_cands !== 1 ? 's' : '' ?>
                    </div>
                </div>
            </div>

            <!-- Descrição resumida -->
            <?php if (!empty($row['descricao'])): ?>
            <p class="text-secondary"
               style="font-size:13px;line-height:1.6;margin-bottom:16px;">
                <?= htmlspecialchars(mb_substr($row['descricao'], 0, 180)) ?>…
            </p>
            <?php endif; ?>

            <!-- Ações -->
            <div style="
                display:flex;gap:8px;flex-wrap:wrap;
                border-top:1px solid var(--border);
                padding-top:16px;
            ">
                <a href="candidatos.php?vaga=<?= $vaga_id ?>"
                   class="btn btn-primary btn-sm">
                    👥 Ver Candidatos<?= $n_cands > 0 ? " ({$n_cands})" : '' ?>
                </a>

                <a href="criar.php?editar=<?= $vaga_id ?>"
                   class="btn btn-ghost btn-sm"
                   title="Editar esta vaga (criar.php com modo edição)">
                    ✏ Editar
                </a>

                <!-- Delete via POST (botão abre modal de confirmação) -->
                <button
                    type="button"
                    class="btn btn-danger btn-sm"
                    onclick="confirmarDelete(<?= $vaga_id ?>, '<?= htmlspecialchars(addslashes($row['titulo'])) ?>')">
                    Remover
                </button>
            </div>

        </div><!-- /.card -->

        <?php endwhile; ?>

    </div>

    <?php else: ?>

    <div class="card fade-up"
         style="text-align:center;padding:60px 32px;max-width:460px;margin:0 auto;">
        <div style="font-size:44px;margin-bottom:18px;">💼</div>
        <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">
            Nenhuma vaga publicada ainda
        </h3>
        <p class="text-secondary"
           style="font-size:14px;line-height:1.6;margin-bottom:28px;">
            Comece publicando uma vaga para encontrar jovens talentos.
        </p>
        <a href="criar.php" class="btn btn-primary btn-sm">
            Publicar Primeira Vaga
        </a>
    </div>

    <?php endif; ?>

    <div style="margin-top:20px;">
        <a href="../dashboard/empresa.php" class="text-secondary"
           style="font-size:13px;text-decoration:none;">
            ← Voltar ao Painel
        </a>
    </div>

</main>
</div><!-- /.dashboard-wrap -->

<!-- Modal de confirmação de exclusão -->
<div id="modalDelete"
     style="position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:2000;
            display:none;align-items:center;justify-content:center;">
    <div class="card"
         style="max-width:400px;width:90%;text-align:center;padding:36px 28px;">
        <p style="font-size:36px;margin-bottom:12px;">⚠️</p>
        <h3 style="font-weight:700;margin-bottom:10px;">Remover Vaga</h3>
        <p class="text-secondary" id="modalDeleteMsg"
           style="font-size:14px;margin-bottom:24px;line-height:1.6;">
        </p>
        <p class="text-muted" style="font-size:12px;margin-bottom:20px;">
            Todas as candidaturas e dados associados serão apagados automaticamente.
        </p>
        <!-- Form POST — sem GET, sem excluir.php separado -->
        <form method="POST" action="minhas.php" id="formDelete">
            <input type="hidden" name="action"   value="delete">
            <input type="hidden" name="vaga_id"  id="deleteVagaId" value="">
            <div class="d-flex gap-12" style="justify-content:center;">
                <button type="button" class="btn btn-secondary btn-sm"
                        onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn btn-danger btn-sm">
                    Remover Permanentemente
                </button>
            </div>
        </form>
    </div>
</div>

<!-- FABs -->
<div class="fab-stack">
    <button class="fab fab-ai"   id="btnChat" title="Assistente IA"  aria-label="IA">✦</button>
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Acessibilidade">♿</button>
</div>
<div class="user-indicator">
    <?= strtoupper(mb_substr($_SESSION['empresa_nome'] ?? 'E', 0, 1)) ?>
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

/* ── Toast ─────────────────────────────────────────────────── */
const toast = document.getElementById('toast');
if (toast) {
    setTimeout(() => toast.classList.add('toast-visible'), 100);
    setTimeout(() => toast.classList.remove('toast-visible'), 3500);
}

/* ── Modal de exclusão ─────────────────────────────────────── */
const modal = document.getElementById('modalDelete');

function confirmarDelete(vagaId, titulo) {
    document.getElementById('deleteVagaId').value = vagaId;
    document.getElementById('modalDeleteMsg').textContent =
        `Tem certeza que deseja remover "${titulo}"? Esta ação não pode ser desfeita.`;
    modal.style.display = 'flex';
}

function fecharModal() {
    modal.style.display = 'none';
}

modal?.addEventListener('click', e => {
    if (e.target === modal) fecharModal();
});
</script>

</body>
</html>