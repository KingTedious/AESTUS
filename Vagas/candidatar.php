<?php
/* ============================================================
   Vagas/candidatar.php

   Fluxo correto (HTTP semântico + PRG):
   ┌─────────────────────────────────────────────────────────┐
   │ GET  ?vaga=X   → exibe página de confirmação da vaga   │
   │ POST           → processa a candidatura                 │
   │ Redirect  →    GET ?result=ok | duplicate | error       │
   └─────────────────────────────────────────────────────────┘
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/conta_status.php";   // Stage 7: só vagas visíveis

$jovem_id = intval($_SESSION['jovem_id']);

// ── Lê o result do redirect (PRG) ────────────────────────────────────────────
$result_code = $_GET['result'] ?? null; // 'ok' | 'duplicate' | 'error' | null

// ── Lê o vaga_id (de GET ou de result redirect) ───────────────────────────────
$vaga_id = intval($_GET['vaga'] ?? $_GET['vaga_id'] ?? 0);

// Guarda de onde o jovem veio (painel, favoritos, candidaturas) para o
// "←" e o "Cancelar" voltarem aos detalhes com o mesmo "Voltar" — lista fixa,
// a mesma de Vagas/listar.php; qualquer outro valor é ignorado.
$voltar_chave = $_POST['voltar'] ?? $_GET['voltar'] ?? '';
$voltar_chave = (is_string($voltar_chave) && in_array($voltar_chave, ['painel', 'favoritos', 'candidaturas'], true))
                ? $voltar_chave : '';
$voltar_qs    = ($voltar_chave !== '') ? '&voltar=' . $voltar_chave : '';

/* ============================================================
   POST: processa a candidatura
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $vaga_id = intval($_POST['vaga_id'] ?? 0);

    if ($vaga_id <= 0) {
        header("Location: ../dashboard/jovem.php");
        exit;
    }

    // Vaga inexistente (ou excluída com a página aberta) → volta ao painel.
    // Antes o INSERT violava a chave estrangeira e mostrava um erro fatal.
    // Stage 7: vaga fora do ar pela moderação (ou de empresa suspensa) também.
    $stmt_vx = $conn->prepare("SELECT v.id FROM vagas v WHERE v.id = ?" . jc_vagas_visiveis_sql($conn, 'v') . " LIMIT 1");
    $stmt_vx->bind_param("i", $vaga_id);
    $stmt_vx->execute();
    $vaga_existe = $stmt_vx->get_result()->fetch_assoc();
    $stmt_vx->close();
    if (!$vaga_existe) {
        header("Location: ../dashboard/jovem.php");
        exit;
    }

    // Verifica duplicata (a UNIQUE KEY do banco também bloqueia, mas checamos antes por UX)
    $stmt_chk = $conn->prepare(
        "SELECT id FROM candidaturas WHERE vaga_id = ? AND jovem_id = ? LIMIT 1"
    );
    $stmt_chk->bind_param("ii", $vaga_id, $jovem_id);
    $stmt_chk->execute();
    $stmt_chk->store_result();
    $ja_existe = $stmt_chk->num_rows > 0;
    $stmt_chk->close();

    if ($ja_existe) {
        header("Location: candidatar.php?vaga_id={$vaga_id}&result=duplicate{$voltar_qs}");
        exit;
    }

    // Insere — status 'Recebido' (valor DEFAULT do schema v3.00)
    $stmt_ins = $conn->prepare(
        "INSERT INTO candidaturas (vaga_id, jovem_id, status) VALUES (?, ?, 'Recebido')"
    );
    $stmt_ins->bind_param("ii", $vaga_id, $jovem_id);

    // No PHP 8.1+ o mysqli lança exceção (o "else" abaixo nunca rodava).
    // Chave duplicada (envio repetido em paralelo) vira "já candidatado".
    try {
        $inseriu = $stmt_ins->execute();
    } catch (mysqli_sql_exception $e) {
        $inseriu = false;
        if ($e->getCode() === 1062) {
            $stmt_ins->close();
            header("Location: candidatar.php?vaga_id={$vaga_id}&result=duplicate{$voltar_qs}");
            exit;
        }
        jc_log_excecao($e, 'candidatura.erro');
    }

    if ($inseriu) {
        $stmt_ins->close();
        jc_log('candidatura.criada', 'info', ['vaga' => $vaga_id]);
        header("Location: candidatar.php?vaga_id={$vaga_id}&result=ok{$voltar_qs}");
    } else {
        // Loga o erro real; não expõe para o usuário
        jc_log('candidatura.erro', 'erro', ['vaga' => $vaga_id]);
        $stmt_ins->close();
        header("Location: candidatar.php?vaga_id={$vaga_id}&result=error{$voltar_qs}");
    }
    exit;
}

/* ============================================================
   GET: carrega dados da vaga para exibição
   ============================================================ */
if ($vaga_id <= 0) {
    header("Location: ../dashboard/jovem.php");
    exit;
}

// Busca a vaga (com nome da empresa)
$stmt_v = $conn->prepare(
    "SELECT v.id, v.titulo, v.descricao, v.cidade, v.estado,
            v.salario, v.carga_horaria, v.area, v.tipo_contrato,
            e.nome_empresa
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
    header("Location: ../dashboard/jovem.php");
    exit;
}

// Verifica se já se candidatou (para não mostrar o form desnecessariamente)
$stmt_chk2 = $conn->prepare(
    "SELECT id FROM candidaturas WHERE vaga_id = ? AND jovem_id = ? LIMIT 1"
);
$stmt_chk2->bind_param("ii", $vaga_id, $jovem_id);
$stmt_chk2->execute();
$stmt_chk2->store_result();
$ja_candidatado = $stmt_chk2->num_rows > 0;
$stmt_chk2->close();

// Se não há result code mas já está candidatado, redireciona direto
if (!$result_code && $ja_candidatado) {
    $result_code = 'duplicate';
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $result_code ? 'Candidatura' : 'Confirmar Candidatura' ?> | JovemConnect
    </title>
    <link rel="stylesheet" href="../Assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>

<!-- Barra de topo -->
<div class="detail-bar">
    <div class="d-flex align-center gap-12">
        <a href="listar.php?id=<?= $vaga_id ?><?= htmlspecialchars($voltar_qs) ?>"
           class="icon-btn" title="Voltar para a vaga" aria-label="Voltar para a vaga"><span class="i i-arrow-left" aria-hidden="true"></span></a>
        <span class="marca marca-compacta"><img class="marca-tema-escuro" src="../assets/img/marca/jovemconnect-simbolo.png" alt="JovemConnect" width="910" height="910"><img class="marca-tema-claro" src="../assets/img/marca/jovemconnect-simbolo-claro.png" alt="JovemConnect" width="910" height="910" loading="lazy"></span>
        <span class="detail-bar-title">
            <?= $result_code ? 'Status da Candidatura' : 'Confirmar Candidatura' ?>
        </span>
    </div>
</div>

<div class="page-progress"><div class="page-progress-fill"></div></div>

<div style="max-width:560px;margin:88px auto 60px;padding:0 24px;">

    <?php if ($result_code === 'ok'): ?>
    <!-- ══ SUCESSO ══════════════════════════════════════════════════════════ -->
    <div class="card fade-up" style="text-align:center;padding:56px 32px;">
        <div class="icone-destaque sucesso" aria-hidden="true"><span class="i i-check-circle" aria-hidden="true"></span></div>
        <h2 style="font-size:20px;font-weight:800;margin-bottom:10px;color:var(--jc-sucesso-texto, #4ade80);">
            Candidatura Enviada!
        </h2>
        <p class="text-secondary" style="font-size:14px;line-height:1.7;margin-bottom:8px;">
            Você se candidatou com sucesso à vaga de
            <strong><?= htmlspecialchars($vaga['titulo']) ?></strong>
            na <strong><?= htmlspecialchars($vaga['nome_empresa']) ?></strong>.
        </p>
        <p class="text-secondary" style="font-size:13px;margin-bottom:28px;">
            Status atual: <span class="badge">Recebido</span>
        </p>
        <div class="d-flex gap-12" style="justify-content:center;flex-wrap:wrap;">
            <a href="../dashboard/jovem.php"
               class="btn btn-ghost btn-sm">Ver outras vagas</a>
            <a href="../candidaturas/minhas.php"
               class="btn btn-primary btn-sm">Minhas Candidaturas</a>
        </div>
    </div>

    <?php elseif ($result_code === 'duplicate'): ?>
    <!-- ══ JÁ CANDIDATADO ═══════════════════════════════════════════════════ -->
    <div class="card fade-up" style="text-align:center;padding:56px 32px;
         border-color:rgba(250,204,21,.3);">
        <div class="icone-destaque aviso" aria-hidden="true"><span class="i i-alert-triangle" aria-hidden="true"></span></div>
        <h2 style="font-size:20px;font-weight:800;margin-bottom:10px;color:var(--jc-aviso-texto, #facc15);">
            Candidatura já registrada
        </h2>
        <p class="text-secondary" style="font-size:14px;line-height:1.7;margin-bottom:28px;">
            Você já se candidatou à vaga de
            <strong><?= htmlspecialchars($vaga['titulo']) ?></strong>.
            Acompanhe o andamento em <em>Minhas Candidaturas</em>.
        </p>
        <div class="d-flex gap-12" style="justify-content:center;flex-wrap:wrap;">
            <a href="../dashboard/jovem.php"
               class="btn btn-ghost btn-sm">Ver outras vagas</a>
            <a href="../candidaturas/minhas.php"
               class="btn btn-primary btn-sm">Minhas Candidaturas</a>
        </div>
    </div>

    <?php elseif ($result_code === 'error'): ?>
    <!-- ══ ERRO ════════════════════════════════════════════════════════════ -->
    <div class="card fade-up" style="text-align:center;padding:56px 32px;
         border-color:rgba(239,68,68,.3);">
        <div class="icone-destaque erro" aria-hidden="true"><span class="i i-x-circle" aria-hidden="true"></span></div>
        <h2 style="font-size:20px;font-weight:800;margin-bottom:10px;color:var(--jc-erro-texto, #f87171);">
            Algo deu errado
        </h2>
        <p class="text-secondary" style="font-size:14px;line-height:1.7;margin-bottom:28px;">
            Não foi possível registrar sua candidatura.
            Tente novamente em alguns instantes.
        </p>
        <div class="d-flex gap-12" style="justify-content:center;flex-wrap:wrap;">
            <a href="../dashboard/jovem.php"
               class="btn btn-ghost btn-sm">Ver vagas</a>
            <a href="candidatar.php?vaga=<?= $vaga_id ?><?= htmlspecialchars($voltar_qs) ?>"
               class="btn btn-primary btn-sm">Tentar novamente</a>
        </div>
    </div>

    <?php else: ?>
    <!-- ══ CONFIRMAÇÃO (exibida no GET normal) ══════════════════════════════ -->

    <!-- Card da vaga (resumo) -->
    <div class="card fade-up" style="margin-bottom:16px;">
        <?php if (!empty($vaga['area'])): ?>
        <span class="badge badge-area" style="margin-bottom:10px;display:inline-flex;">
            <?= htmlspecialchars($vaga['area']) ?>
        </span>
        <?php endif; ?>

        <h2 style="font-size:20px;font-weight:800;margin-bottom:4px;letter-spacing:-.01em;">
            <?= htmlspecialchars($vaga['titulo']) ?>
        </h2>
        <div class="job-card-company" style="margin-bottom:14px;">
            <span class="i i-building" aria-hidden="true"></span><?= htmlspecialchars($vaga['nome_empresa']) ?>
        </div>

        <div class="job-card-meta" style="margin-bottom:10px;">
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

        <?php if (!empty($vaga['cidade'])): ?>
        <div class="job-card-location">
            <span class="i i-map-pin" aria-hidden="true"></span>
            <span>
                <?= htmlspecialchars($vaga['cidade']) ?>
                <?= !empty($vaga['estado']) ? ', ' . htmlspecialchars($vaga['estado']) : '' ?>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form de confirmação -->
    <div class="card fade-up" style="text-align:center;padding:32px 24px;">
        <div class="icone-destaque" aria-hidden="true"><span class="i i-send" aria-hidden="true"></span></div>
        <h3 style="font-size:17px;font-weight:700;margin-bottom:8px;">
            Confirmar candidatura?
        </h3>
        <p class="text-secondary" style="font-size:13px;line-height:1.6;margin-bottom:24px;">
            Ao confirmar, a empresa receberá seu perfil e currículo.
            O status inicial será <span class="badge">Recebido</span>.
        </p>

        <!-- POST — processa de forma segura -->
        <!-- Data-envio-unico trava o botão após o 1º clique — com duplo clique
             o 2º envio respondia "já candidatado" e a tela de sucesso nunca aparecia -->
        <form method="POST" action="candidatar.php" data-envio-unico="Enviando candidatura...">
            <input type="hidden" name="vaga_id" value="<?= $vaga_id ?>">
            <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltar_chave) ?>">
            <?= csrf_field() ?>
            <div class="d-flex gap-12" style="justify-content:center;flex-wrap:wrap;">
                <a href="listar.php?id=<?= $vaga_id ?><?= htmlspecialchars($voltar_qs) ?>"
                   class="btn btn-secondary btn-sm">Cancelar</a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="i i-send" aria-hidden="true"></span> Confirmar Candidatura
                </button>
            </div>
        </form>
    </div>

    <?php endif; ?>

</div><!-- /container -->

<!-- FABs + Assistente + Acessibilidade -->
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>

<script>
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);
</script>

</body>
</html>