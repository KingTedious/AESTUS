<?php
/* ============================================================
   Vagas/curriculoIA.php — Análise de currículo pela IA

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. session_start() depois do include config.php.
   2. file_get_contents() com stream context → sem controle real
      de timeout, sem tratamento de erros HTTP, sem verificação
      de código de resposta.
   3. localhost:5000 → usar 127.0.0.1:5000 (mais confiável).
   4. Payload inconsistente: enviava {"texto":"..."} mas o
      Flask v3.00 /curriculo espera {resumo, habilidades, formacoes}.
   5. Sem htmlspecialchars() nas saídas da IA → XSS.
   6. $_POST['texto'] repopulado sem htmlspecialchars() → XSS.
   7. include header.php após processamento e <link> no body.
   8. var(--borda) e var(--principal) não existem no CSS v3.00.
   9. Página não carregava dados reais do perfil do jovem —
      forçava o usuário a redigitar o que já estava no banco.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF

if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$jovem_id = intval($_SESSION['jovem_id']);

// ── Carrega dados atuais do perfil para pré-preencher o form ──────────────────
$stmt_j = $conn->prepare(
    "SELECT nome, resumo_profissional, area_interesse,
            escolaridade_tipo, escolaridade_curso
     FROM   jovens WHERE id = ? LIMIT 1"
);
$stmt_j->bind_param("i", $jovem_id);
$stmt_j->execute();
$jovem = $stmt_j->get_result()->fetch_assoc();
$stmt_j->close();

// Habilidades
$stmt_h = $conn->prepare(
    "SELECT habilidade FROM habilidades_jovem WHERE jovem_id = ? ORDER BY id ASC"
);
$stmt_h->bind_param("i", $jovem_id);
$stmt_h->execute();
$habs_raw    = $stmt_h->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_h->close();
$habilidades = array_column($habs_raw, 'habilidade');

// Formações
$stmt_f = $conn->prepare(
    "SELECT titulo, instituicao, conclusao FROM formacao_jovem WHERE jovem_id = ? ORDER BY id ASC"
);
$stmt_f->bind_param("i", $jovem_id);
$stmt_f->execute();
$formacoes = $stmt_f->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_f->close();

// ── Resultado da IA ───────────────────────────────────────────────────────────
$resultado_ia = null;
$erro_ia      = null;

/* ============================================================
   PROCESSAMENTO POST
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // Usa o texto do POST (editável) ou o resumo atual
    $resumo_analise = trim($_POST['resumo'] ?? $jovem['resumo_profissional'] ?? '');

    $payload = [
        'resumo'      => $resumo_analise,
        'habilidades' => $habilidades,
        'formacoes'   => $formacoes,
    ];

    // ── Chama Flask via cURL ─────────────────────────────────────────────────
    $ch = curl_init("http://127.0.0.1:5000/curriculo");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $result     = curl_exec($ch);
    $curl_errno = curl_errno($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_errno || $result === false || $http_code !== 200) {
        $erro_ia = "O servidor de IA está offline. Verifique se o Flask está rodando em http://127.0.0.1:5000.";
    } else {
        $decoded = json_decode($result, true);
        if (is_array($decoded)) {
            $resultado_ia = $decoded;
            // Atualiza o resumo no banco se o usuário editou
            if (!empty($resumo_analise)
                && $resumo_analise !== ($jovem['resumo_profissional'] ?? '')) {
                $stmt_upd = $conn->prepare(
                    "UPDATE jovens SET resumo_profissional = ? WHERE id = ?"
                );
                $stmt_upd->bind_param("si", $resumo_analise, $jovem_id);
                $stmt_upd->execute();
                $stmt_upd->close();
                // Atualiza local para repopular o form
                $jovem['resumo_profissional'] = $resumo_analise;
            }
        } else {
            $erro_ia = "Resposta inválida do servidor de IA.";
        }
    }
}

// Resumo atual para o textarea (pós-POST usa o enviado, senão usa o do banco)
$resumo_atual = htmlspecialchars(
    ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resumo']))
        ? ($_POST['resumo'] ?? '')
        : ($jovem['resumo_profissional'] ?? '')
);
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor de Currículo IA | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body>

<!-- Barra de topo -->
<div class="detail-bar">
    <div class="d-flex align-center gap-12">
        <a href="../dashboard/jovem.php" class="icon-btn" title="Voltar">←</a>
        <span class="detail-bar-title">✦ Mentor de Currículo IA</span>
    </div>
    <a href="../jovem/perfil.php" class="btn btn-ghost btn-sm">Ver Meu Perfil</a>
</div>

<div class="page-progress"><div class="page-progress-fill"></div></div>

<div style="max-width:680px;margin:88px auto 60px;padding:0 24px;">

    <!-- ── Contexto do perfil ─────────────────────────────────────────────── -->
    <?php if (!empty($jovem['nome'])): ?>
    <div class="card fade-up" style="margin-bottom:16px;padding:16px 20px;">
        <div class="d-flex align-center gap-12">
            <div class="avatar avatar-sm">
                <?= strtoupper(mb_substr($jovem['nome'], 0, 1)) ?>
            </div>
            <div>
                <p style="font-size:13px;font-weight:600;">
                    <?= htmlspecialchars($jovem['nome']) ?>
                </p>
                <p class="text-muted" style="font-size:11px;">
                    <?php
                    $infos = [];
                    if (!empty($jovem['area_interesse']))   $infos[] = htmlspecialchars($jovem['area_interesse']);
                    if (!empty($habilidades))               $infos[] = count($habilidades) . ' habilidade(s)';
                    if (!empty($formacoes))                 $infos[] = count($formacoes) . ' formação(ões)';
                    echo implode(' • ', $infos) ?: 'Perfil ainda incompleto';
                    ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Formulário de análise ──────────────────────────────────────────── -->
    <div class="card fade-up" style="margin-bottom:16px;">
        <h2 style="font-size:18px;font-weight:700;margin-bottom:6px;">
            🤖 Mentor de Currículo
        </h2>
        <p class="text-secondary" style="font-size:13px;line-height:1.6;margin-bottom:20px;">
            A IA analisa seu resumo, habilidades e formação para dar dicas personalizadas.
            O texto abaixo foi carregado do seu perfil — edite à vontade antes de enviar.
        </p>

        <form method="POST" id="formIA">
        <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="resumo">
                    Resumo Profissional / Objetivo
                </label>
                <textarea
                    id="resumo"
                    name="resumo"
                    class="form-control"
                    rows="7"
                    placeholder="Descreva seus objetivos, área de interesse e o que você tem a oferecer..."
                    ><?= $resumo_atual ?></textarea>
                <p class="text-muted" style="font-size:11px;margin-top:6px;">
                    <?php if (!empty($habilidades)): ?>
                    Habilidades do perfil que serão incluídas:
                    <?= implode(', ', array_map('htmlspecialchars', $habilidades)) ?>
                    <?php else: ?>
                    💡 Adicione habilidades ao seu perfil para uma análise mais completa.
                    <?php endif; ?>
                </p>
            </div>

            <button type="submit" class="btn btn-primary btn-full" id="btnAnalisar">
                ✦ Analisar com IA
            </button>

        </form>
    </div>

    <!-- ── Resultado da IA ────────────────────────────────────────────────── -->
    <?php if ($erro_ia): ?>
    <div class="card fade-up"
         style="border-color:rgba(239,68,68,.3);padding:28px;text-align:center;">
        <p style="font-size:28px;margin-bottom:12px;">⚠️</p>
        <p class="text-secondary" style="font-size:13px;line-height:1.6;">
            <?= htmlspecialchars($erro_ia) ?>
        </p>
    </div>

    <?php elseif ($resultado_ia): ?>
    <div class="card fade-up"
         style="border-color:rgba(75,142,241,.3);margin-bottom:16px;">

        <!-- Mensagem principal -->
        <div class="d-flex align-center gap-10" style="margin-bottom:16px;">
            <span style="font-size:24px;">
                <?= !empty($resultado_ia['sugestoes']) ? '📝' : '🌟' ?>
            </span>
            <p style="font-size:15px;font-weight:700;line-height:1.4;">
                <?= htmlspecialchars($resultado_ia['mensagem'] ?? '') ?>
            </p>
        </div>

        <!-- Sugestões -->
        <?php if (!empty($resultado_ia['sugestoes'])): ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($resultado_ia['sugestoes'] as $s): ?>
            <div style="
                display:flex;align-items:flex-start;gap:10px;
                padding:12px 14px;
                background:var(--bg-input);
                border-radius:var(--radius-sm);
                font-size:13px;
                color:var(--text-secondary);
                line-height:1.6;
            ">
                <span style="color:var(--accent);flex-shrink:0;font-size:15px;">💡</span>
                <?= htmlspecialchars($s) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- Ação após análise -->
    <div class="d-flex gap-12 fade-up" style="flex-wrap:wrap;">
        <a href="../jovem/perfil.php" class="btn btn-primary btn-sm">
            Atualizar Perfil →
        </a>
        <a href="../dashboard/jovem.php" class="btn btn-ghost btn-sm">
            Ver Vagas Disponíveis
        </a>
    </div>

    <?php endif; ?>

</div><!-- /container -->

<!-- FABs -->
<div class="fab-stack">
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Acessibilidade">♿</button>
</div>

<script>
/* ── Tema ──────────────────────────────────────────────────── */
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

/* ── Feedback visual no submit ─────────────────────────────── */
document.getElementById('formIA')?.addEventListener('submit', function () {
    const btn = document.getElementById('btnAnalisar');
    if (btn) {
        btn.disabled  = true;
        btn.innerHTML = '<span class="loading-spinner"></span> Analisando...';
    }
});
</script>

</body>
</html>