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
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF

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
    csrf_require();

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

$deleted   = isset($_GET['deleted']);
$publicada = isset($_GET['publicada']);   // Stage 4: retorno de Vagas/criar.php

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
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>

<!-- Navbar interna -->
<nav class="navbar-inner">
    <a href="../dashboard/empresa.php" class="nav-logo" aria-label="JovemConnect — meu painel">
        <picture class="marca marca-nav">
            <source media="(max-width: 480px)" srcset="../assets/img/marca/jovemconnect-simbolo.png" width="192" height="192">
            <img src="../assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="493" height="160">
        </picture>
    </a>
    <div class="navbar-inner-right">
        <button class="icon-btn btn-theme" id="btnTheme" title="Alternar tema" aria-label="Alternar tema">🌙</button>
        <!-- Stage 4: mesmo atalho do painel da empresa -->
        <a href="minhas.php" class="icon-btn" title="Minhas vagas" aria-label="Minhas vagas" aria-current="page"><span class="i i-briefcase" aria-hidden="true"></span></a>
        <a href="../empresa/perfil.php" class="icon-btn" title="Meu Perfil" aria-label="Meu Perfil"><span class="i i-user" aria-hidden="true"></span></a>
        <a href="../logout.php" class="icon-btn text-danger" title="Sair" aria-label="Sair"><span class="i i-log-out" aria-hidden="true"></span></a>
    </div>
</nav>

<div class="dashboard-wrap">
<main class="dashboard-main">

    <!-- Toast de feedback -->
    <?php if ($deleted): ?>
    <div id="toast" class="toast toast-success"><span class="i i-check-circle" aria-hidden="true"></span>Vaga removida com sucesso.</div>
    <?php elseif ($publicada): ?>
    <div id="toast" class="toast toast-success"><span class="i i-check-circle" aria-hidden="true"></span>Vaga publicada! Ela já aparece para os jovens.</div>
    <?php endif; ?>

    <!-- Stage 4: caminho de volta também no topo (antes só no fim da lista) -->
    <p style="margin:0 0 12px;"><a href="../dashboard/empresa.php" class="back-link"><span class="i i-arrow-left" aria-hidden="true"></span> Voltar ao painel</a></p>

    <!-- Cabeçalho -->
    <div class="page-heading fade-up">
        <div class="d-flex align-center justify-between" style="flex-wrap:wrap;gap:12px;">
            <div>
                <h1><span class="i i-briefcase" aria-hidden="true"></span> Minhas Vagas</h1>
                <p class="text-secondary" style="margin-top:4px;font-size:14px;">
                    <?= $total_vagas ?> vaga<?= $total_vagas !== 1 ? 's' : '' ?> publicada<?= $total_vagas !== 1 ? 's' : '' ?>.
                    Gerencie candidatos e acompanhe o desempenho.
                </p>
            </div>
            <a href="criar.php" class="btn btn-primary btn-sm"><span class="i i-plus" aria-hidden="true"></span> Criar Nova Vaga</a>
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
                        <span class="i i-map-pin" aria-hidden="true"></span> <?= htmlspecialchars($row['cidade']) ?>
                        <?= !empty($row['estado']) ? ', ' . htmlspecialchars($row['estado']) : '' ?>
                        <?php endif; ?>
                        <?php if (!empty($row['salario'])): ?>
                        • <span class="i i-banknote" aria-hidden="true"></span> R$ <?= htmlspecialchars($row['salario']) ?>
                        <?php endif; ?>
                        <?php if (!empty($row['carga_horaria'])): ?>
                        • <span class="i i-clock" aria-hidden="true"></span> <?= htmlspecialchars($row['carga_horaria']) ?>
                        <?php endif; ?>
                        • <span class="i i-calendar" aria-hidden="true"></span> Publicada em <?= $criado_em ?>
                    </p>
                </div>

                <!-- Badge de candidatos -->
                <div class="contagem-candidatos" style="
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
                <a href="candidatos.php?vaga=<?= $vaga_id ?>&amp;voltar=minhas"
                   class="btn btn-primary btn-sm">
                    <span class="i i-users" aria-hidden="true"></span> Ver Candidatos<?= $n_cands > 0 ? " ({$n_cands})" : '' ?>
                </a>

                <!-- Stage 3: o botão "✏ Editar" foi removido — criar.php ignora ?editar=
                     e abria o formulário de CRIAÇÃO vazio (salvar gerava uma vaga nova,
                     duplicada). A edição de vaga não existe no backend: pendência. -->

                <!-- Delete via POST (botão abre modal de confirmação) -->
                <button
                    type="button"
                    class="btn btn-danger btn-sm"
                    onclick="confirmarDelete(<?= $vaga_id ?>, '<?= htmlspecialchars(addslashes($row['titulo'])) ?>')">
                    <span class="i i-trash" aria-hidden="true"></span> Remover
                </button>
            </div>

        </div><!-- /.card -->

        <?php endwhile; ?>

    </div>

    <?php else: ?>

    <div class="card fade-up"
         style="text-align:center;padding:60px 32px;max-width:460px;margin:0 auto;">
        <div class="icone-destaque" aria-hidden="true"><span class="i i-briefcase" aria-hidden="true"></span></div>
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
        <a href="../dashboard/empresa.php" class="text-secondary link-seta"
           style="font-size:13px;text-decoration:none;">
            <span class="i i-arrow-left" aria-hidden="true"></span> Voltar ao Painel
        </a>
    </div>

</main>
</div><!-- /.dashboard-wrap -->

<!-- Modal de confirmação de exclusão -->
<div id="modalDelete" class="jc-modal" role="dialog" aria-modal="true" aria-labelledby="modalDeleteTitulo">
    <div class="card"
         style="max-width:400px;width:100%;text-align:center;padding:36px 28px;">
        <!-- Stage 5: fechar pelo × (antes só Cancelar, Esc ou clique fora) -->
        <button type="button" class="jc-modal-fechar" aria-label="Fechar" onclick="fecharModal()"><span class="i i-x" aria-hidden="true"></span></button>
        <div class="icone-destaque aviso" aria-hidden="true"><span class="i i-alert-triangle" aria-hidden="true"></span></div>
        <h3 id="modalDeleteTitulo" style="font-weight:700;margin-bottom:10px;">Remover Vaga</h3>
        <p class="text-secondary" id="modalDeleteMsg"
           style="font-size:14px;margin-bottom:24px;line-height:1.6;">
        </p>
        <p class="text-muted" style="font-size:12px;margin-bottom:20px;">
            Todas as candidaturas e dados associados serão apagados automaticamente.
        </p>
        <!-- Form POST — sem GET, sem excluir.php separado -->
        <form method="POST" action="minhas.php" id="formDelete" data-envio-unico="Removendo...">
            <?= csrf_field() ?>
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

<!-- FABs + Assistente + Acessibilidade (Stage 3: antes os botões não tinham painel nem script) -->
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

/* ── Toast ─────────────────────────────────────────────────── */
const toast = document.getElementById('toast');
if (toast) {
    // Stage 4: mensagens de erro ficam mais tempo (antes sumiam em 3,5 s) e são anunciadas:
    // o texto volta um instante depois do role — região "viva" que já nasce com texto não é lida
    const ehErro = toast.classList.contains('toast-error');
    const conteudo = [...toast.childNodes];   // Stage 5: ícone + texto (antes só o texto)
    toast.replaceChildren();
    toast.setAttribute('role', ehErro ? 'alert' : 'status');
    setTimeout(() => { toast.replaceChildren(...conteudo); toast.classList.add('toast-visible'); }, 100);
    setTimeout(() => toast.classList.remove('toast-visible'), ehErro ? 7000 : 4000);
}

/* ── Modal de exclusão ─────────────────────────────────────── */
const modal = document.getElementById('modalDelete');

// Stage 4: ao abrir, o foco vai para "Cancelar"; ao fechar, volta para o botão
// "Remover" que abriu o modal. Esc também fecha (antes só clique fora/Cancelar).
let modalOrigem = null;

function confirmarDelete(vagaId, titulo) {
    modalOrigem = document.activeElement;
    document.getElementById('deleteVagaId').value = vagaId;
    document.getElementById('modalDeleteMsg').textContent =
        `Tem certeza que deseja remover "${titulo}"? Esta ação não pode ser desfeita.`;
    modal.style.display = 'flex';
    modal.querySelector('form button[type="button"]')?.focus();   // "Cancelar" (o × vem antes no HTML)
}

function fecharModal() {
    modal.style.display = 'none';
    modalOrigem?.focus?.();
}

modal?.addEventListener('click', e => {
    if (e.target === modal) fecharModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal?.style.display === 'flex') fecharModal();
});
</script>

</body>
</html>