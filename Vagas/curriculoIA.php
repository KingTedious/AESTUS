<?php
/* ============================================================
   Vagas/curriculoIA.php — Análise de currículo pela IA
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/moderacao.php";   // Stage 7: validação + moderação centrais
require_once dirname(__DIR__) . "/includes/ia_endereco.php"; // porta da IA (5000 ou a escolhida ao iniciar)

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

    // Stage 7: o resumo EDITADO passa pela validação/moderação antes de ir para a IA
    // e para o banco (texto igual ao já gravado não é conferido de novo)
    $sinais_resumo = [];
    $resumo_editado = $resumo_analise !== '' && str_replace("\r\n", "\n", $resumo_analise)
                      !== str_replace("\r\n", "\n", trim((string) ($jovem['resumo_profissional'] ?? '')));
    if ($resumo_editado) {
        $mod = jc_mod_formulario(['jovem.resumo' => [$resumo_analise, 'resumo']]);
        if ($mod['erros']) {
            $erro_ia = (string) reset($mod['erros']);
        } else {
            $resumo_analise = $mod['valores']['jovem.resumo'] ?? $resumo_analise;
            $sinais_resumo  = $mod['sinais'];
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erro_ia === null) {

    $payload = [
        'resumo'      => $resumo_analise,
        'habilidades' => $habilidades,
        'formacoes'   => $formacoes,
    ];

    // ── Chama Flask via cURL ─────────────────────────────────────────────────
    $ch = curl_init(jc_ia_url('/curriculo'));
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
        // Sem endereço/porta interna na mensagem — o jovem não tem o que fazer com isso
        $erro_ia = "O servidor de IA está indisponível no momento. Tente novamente em alguns minutos.";
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
                jc_mod_registrar($conn, 'jovem', $jovem_id, $sinais_resumo, 'jovem', $jovem_id);   // Stage 7
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
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>

<!-- Barra de topo -->
<div class="detail-bar">
    <div class="d-flex align-center gap-12">
        <a href="../dashboard/jovem.php" class="icon-btn" title="Voltar ao painel" aria-label="Voltar ao painel"><span class="i i-arrow-left" aria-hidden="true"></span></a>
        <span class="marca marca-compacta"><img class="marca-tema-escuro" src="../assets/img/marca/jovemconnect-simbolo.png" alt="JovemConnect" width="910" height="910"><img class="marca-tema-claro" src="../assets/img/marca/jovemconnect-simbolo-claro.png" alt="JovemConnect" width="910" height="910" loading="lazy"></span>
        <span class="detail-bar-title" style="display:inline-flex;align-items:center;gap:8px;"><span class="i i-sparkles" aria-hidden="true"></span> Mentor de Currículo IA</span>
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
        <h2 style="font-size:18px;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:10px;">
            <span class="i i-bot" aria-hidden="true" style="color:var(--accent);"></span> Mentor de Currículo
        </h2>
        <p class="text-secondary" style="font-size:13px;line-height:1.6;margin-bottom:20px;">
            A IA analisa seu resumo, habilidades e formação para dar dicas personalizadas.
            O texto abaixo foi carregado do seu perfil — edite à vontade antes de enviar.
        </p>

        <form method="POST" id="formIA" data-envio-unico="Analisando...">
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
                    <span class="i i-lightbulb" aria-hidden="true"></span> Adicione habilidades ao seu perfil para uma análise mais completa.
                    <?php endif; ?>
                </p>
            </div>

            <button type="submit" class="btn btn-primary btn-full" id="btnAnalisar">
                <span class="i i-sparkles" aria-hidden="true"></span> Analisar com IA
            </button>

        </form>
    </div>

    <!-- ── Resultado da IA ────────────────────────────────────────────────── -->
    <?php if ($erro_ia): ?>
    <div class="card fade-up"
         style="border-color:rgba(239,68,68,.3);padding:28px;text-align:center;">
        <div class="icone-destaque aviso" aria-hidden="true"><span class="i i-alert-triangle" aria-hidden="true"></span></div>
        <p class="text-secondary" style="font-size:13px;line-height:1.6;">
            <?= htmlspecialchars($erro_ia) ?>
        </p>
    </div>

    <?php elseif ($resultado_ia): ?>
    <div class="card fade-up"
         style="border-color:rgba(75,142,241,.3);margin-bottom:16px;">

        <!-- Mensagem principal -->
        <div class="d-flex align-center gap-10" style="margin-bottom:16px;">
            <span class="i <?= !empty($resultado_ia['sugestoes']) ? 'i-file-text' : 'i-check-circle' ?>" aria-hidden="true"
                  style="font-size:24px;color:var(--accent);"></span>
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
                <span class="i i-lightbulb" aria-hidden="true" style="color:var(--accent);font-size:16px;margin-top:2px;"></span>
                <?= htmlspecialchars($s) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- Ação após análise -->
    <div class="d-flex gap-12 fade-up" style="flex-wrap:wrap;">
        <a href="../jovem/perfil.php" class="btn btn-primary btn-sm">
            Atualizar Perfil <span class="i i-arrow-right" aria-hidden="true"></span>
        </a>
        <a href="../dashboard/jovem.php" class="btn btn-ghost btn-sm">
            Ver Vagas Disponíveis
        </a>
    </div>

    <?php endif; ?>

</div><!-- /container -->

<!-- FABs + Assistente + Acessibilidade -->
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>

<script>
/* ── Tema ──────────────────────────────────────────────────── */
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

/* ── Feedback visual no submit ─────────────────────────────────
   Feito pelo data-envio-unico do formulário (includes/assistente.php),
   que também destrava o botão ao voltar pelo navegador — antes ele ficava
   preso em "Analisando..." depois do "Voltar". */
</script>

</body>
</html>