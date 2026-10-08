<?php
/* ============================================================
   Vagas/avaliar.php
   ------------------------------------------------------------
   Página de análise de compatibilidade IA (jovem ↔ vaga).

   Linkada por:
     - Vagas/listar.php    (?vaga_id=X)               → jovem
     - Vagas/favoritos.php (?vaga_id=X)                → jovem
     - dashboard/empresa.php / Vagas/candidatos.php    → poderão
       linkar aqui no futuro com (?vaga_id=X&jovem_id=Y) → empresa

   Esta página só monta o contexto e a autorização; quem calcula
   e retorna o score continua sendo, sem nenhuma duplicação:
       IA/avaliar_ajax.php → IA/recomendar.py (Flask)

   Autorização (nunca confia em ID vindo do cliente sozinho):
     - jovem   → jovem_id sempre de $_SESSION['jovem_id'].
     - empresa → vaga precisa pertencer a empresa_id da sessão;
                 jovem_id (candidato) vem do GET, mas só é aceito
                 se o perfil for público — mesma regra já usada em
                 dashboard/empresa.php.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF
require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['jovem_id']) && !isset($_SESSION['empresa_id'])) {
    header("Location: ../index.php");
    exit;
}

$tipo              = isset($_SESSION['jovem_id']) ? 'jovem' : 'empresa';
$erroPagina        = null;
$vaga              = null;
$candidato         = null;
$jovem_id_avaliado = 0;

$vaga_id = filter_input(INPUT_GET, 'vaga_id', FILTER_VALIDATE_INT);
$vaga_id = ($vaga_id === false || $vaga_id === null) ? 0 : $vaga_id;

if ($vaga_id <= 0) {
    $erroPagina = "Vaga inválida.";

} elseif ($tipo === 'jovem') {
    $jovem_id_avaliado = intval($_SESSION['jovem_id']);

    $stmt_v = $conn->prepare(
        "SELECT id, titulo, cidade, estado, area, tipo_contrato, descricao
         FROM vagas WHERE id = ? LIMIT 1"
    );
    $stmt_v->bind_param("i", $vaga_id);
    $stmt_v->execute();
    $vaga = $stmt_v->get_result()->fetch_assoc();
    $stmt_v->close();

    if (!$vaga) {
        $erroPagina = "Vaga não encontrada.";
    }

} else { // empresa
    $empresa_id = intval($_SESSION['empresa_id']);

    $jovem_id_get      = filter_input(INPUT_GET, 'jovem_id', FILTER_VALIDATE_INT);
    $jovem_id_avaliado = ($jovem_id_get === false || $jovem_id_get === null) ? 0 : $jovem_id_get;

    $stmt_v = $conn->prepare(
        "SELECT id, titulo, cidade, estado, area, tipo_contrato, descricao
         FROM vagas WHERE id = ? AND empresa_id = ? LIMIT 1"
    );
    $stmt_v->bind_param("ii", $vaga_id, $empresa_id);
    $stmt_v->execute();
    $vaga = $stmt_v->get_result()->fetch_assoc();
    $stmt_v->close();

    if (!$vaga) {
        // Mesma mensagem para "vaga não existe" e "vaga é de outra empresa" —
        // evita confirmar a existência de uma vaga de um concorrente.
        $erroPagina = "Vaga não encontrada.";
    } elseif ($jovem_id_avaliado <= 0) {
        $erroPagina = "Nenhum candidato foi especificado para esta avaliação.";
    } else {
        $stmt_j = $conn->prepare(
            "SELECT id, nome, cidade, estado, area_interesse
             FROM jovens WHERE id = ? AND perfil_publico = 1 LIMIT 1"
        );
        $stmt_j->bind_param("i", $jovem_id_avaliado);
        $stmt_j->execute();
        $candidato = $stmt_j->get_result()->fetch_assoc();
        $stmt_j->close();

        if (!$candidato) {
            $erroPagina = "Candidato não encontrado.";
        }
    }
}

$perfilLink = ($tipo === 'empresa') ? '../empresa/perfil.php' : '../jovem/perfil.php';
$voltarLink = ($tipo === 'empresa')
    ? '../Vagas/candidatos.php?vaga=' . $vaga_id
    : '../Vagas/listar.php?id=' . $vaga_id;
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Compatibilidade | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>
<?= csrf_js() ?>

<nav class="navbar-inner">
    <a href="<?= $tipo === 'empresa' ? '../dashboard/empresa.php' : '../dashboard/jovem.php' ?>" class="nav-logo"
       aria-label="JovemConnect — meu painel">
        <picture class="marca marca-nav">
            <source media="(max-width: 480px)" srcset="../assets/img/marca/jovemconnect-simbolo.png" width="192" height="192">
            <img src="../assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="493" height="160">
        </picture>
    </a>
    <div class="navbar-inner-right">
        <button class="icon-btn btn-theme" id="btnTheme" title="Alternar tema" aria-label="Alternar tema">🌙</button>
        <a href="<?= htmlspecialchars($perfilLink) ?>" class="icon-btn" title="Meu Perfil" aria-label="Meu Perfil"><span class="i i-user" aria-hidden="true"></span></a>
        <a href="../logout.php" class="icon-btn text-danger" title="Sair" aria-label="Sair"><span class="i i-log-out" aria-hidden="true"></span></a>
    </div>
</nav>

<div class="dashboard-wrap">
<main class="dashboard-main" style="max-width:720px;">

    <?php if ($erroPagina): ?>

        <!-- ── ESTADO: NÃO AUTORIZADO / DADOS INVÁLIDOS ─────────────────────── -->
        <div class="card fade-up" style="text-align:center;padding:56px 32px;max-width:460px;margin:40px auto;">
            <div class="icone-destaque neutro" aria-hidden="true"><span class="i i-lock" aria-hidden="true"></span></div>
            <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">Não foi possível abrir esta análise</h3>
            <p class="text-secondary" style="font-size:14px;line-height:1.6;margin-bottom:28px;">
                <?= htmlspecialchars($erroPagina) ?>
            </p>
            <a href="<?= $tipo === 'empresa' ? '../dashboard/empresa.php' : '../dashboard/jovem.php' ?>"
               class="btn btn-primary btn-full">Voltar ao painel</a>
        </div>

    <?php else: ?>

        <!-- ── CABEÇALHO: VAGA (+ CANDIDATO, SE FOR VISÃO DE EMPRESA) ───────── -->
        <div class="page-heading fade-up">
            <h1>Análise de Compatibilidade</h1>
            <p class="text-secondary">
                <?php if ($tipo === 'empresa'): ?>
                    Comparando <strong><?= htmlspecialchars($candidato['nome']) ?></strong>
                    com a vaga <strong><?= htmlspecialchars($vaga['titulo']) ?></strong>.
                <?php else: ?>
                    Sua compatibilidade com a vaga <strong><?= htmlspecialchars($vaga['titulo']) ?></strong>.
                <?php endif; ?>
            </p>
        </div>

        <div class="card fade-up" style="margin-bottom:20px;">
            <div class="job-card-top">
                <h3 class="job-card-title"><?= htmlspecialchars($vaga['titulo']) ?></h3>
                <?php if (!empty($vaga['area'])): ?>
                <span class="badge badge-area"><?= htmlspecialchars($vaga['area']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($vaga['cidade'])): ?>
            <div class="job-card-location">
                <span class="i i-map-pin" aria-hidden="true"></span>
                <span><?= htmlspecialchars($vaga['cidade']) ?><?= !empty($vaga['estado']) ? ', ' . htmlspecialchars($vaga['estado']) : '' ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($vaga['tipo_contrato'])): ?>
            <div class="job-card-meta-item" style="margin-top:8px;">
                <span class="i i-file-text" aria-hidden="true"></span><span><?= htmlspecialchars($vaga['tipo_contrato']) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($tipo === 'empresa'): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                <div class="candidate-card-info">
                    <div class="avatar avatar-md"><?= strtoupper(mb_substr($candidato['nome'], 0, 1)) ?></div>
                    <div>
                        <div class="candidate-name"><?= htmlspecialchars($candidato['nome']) ?></div>
                        <div class="candidate-sub">
                            <?= htmlspecialchars($candidato['cidade'] ?? '') ?><?= !empty($candidato['estado']) ? ', ' . htmlspecialchars($candidato['estado']) : '' ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── RESULTADO DA IA (preenchido via JS) ──────────────────────────── -->
        <div class="card fade-up" id="cardResultado" style="text-align:center;padding:40px 24px;">
            <div id="estadoCarregando">
                <span class="loading-spinner"></span>
                <p class="text-secondary" style="margin-top:12px;">Calculando compatibilidade...</p>
            </div>

            <div id="estadoSucesso" style="display:none;">
                <div style="font-size:40px;font-weight:800;color:var(--accent);" id="scoreValor">--%</div>
                <p class="text-secondary" style="margin:8px 0 20px;">de compatibilidade</p>
                <p id="analiseTexto" style="text-align:left;line-height:1.6;font-size:14px;"></p>
                <ul id="motivosLista" style="text-align:left;font-size:13px;margin-top:12px;padding-left:20px;"></ul>
            </div>

            <div id="estadoErro" style="display:none;">
                <div class="icone-destaque aviso" aria-hidden="true"><span class="i i-alert-triangle" aria-hidden="true"></span></div>
                <p class="text-secondary" id="erroTexto">Não foi possível calcular a compatibilidade agora.</p>
                <button class="btn btn-ghost btn-sm" style="margin-top:16px;" onclick="carregarAvaliacao()">Tentar novamente</button>
            </div>
        </div>

        <a href="<?= htmlspecialchars($voltarLink) ?>" class="back-link" style="display:flex;justify-content:center;margin-top:20px;">
            <span class="i i-arrow-left" aria-hidden="true"></span> Voltar
        </a>

    <?php endif; ?>

</main>
</div>

<!-- Stage 4: assistente + acessibilidade (esta página não tinha os botões ✦/♿) -->
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>

<script>
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

<?php if (!$erroPagina): ?>
async function carregarAvaliacao() {
    const carregando = document.getElementById('estadoCarregando');
    const sucesso     = document.getElementById('estadoSucesso');
    const erro        = document.getElementById('estadoErro');

    carregando.style.display = '';
    sucesso.style.display    = 'none';
    erro.style.display       = 'none';

    try {
        const res = await fetch('../IA/avaliar_ajax.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action:   'match',
                vaga_id:  <?= (int) $vaga_id ?>,
                jovem_id: <?= (int) $jovem_id_avaliado ?>
            })
        });

        const data = await res.json();

        if (!res.ok || typeof data.score === 'undefined') {
            document.getElementById('erroTexto').textContent =
                data.error || 'Não foi possível calcular a compatibilidade agora.';
            carregando.style.display = 'none';
            erro.style.display = '';
            return;
        }

        document.getElementById('scoreValor').textContent = data.score + '%';
        document.getElementById('analiseTexto').textContent = data.analise || '';

        const listaMotivos = document.getElementById('motivosLista');
        listaMotivos.innerHTML = '';
        if (Array.isArray(data.motivos) && data.motivos.length > 0) {
            data.motivos.forEach(m => {
                const li = document.createElement('li');
                li.textContent = m;
                listaMotivos.appendChild(li);
            });
        }

        carregando.style.display = 'none';
        sucesso.style.display = '';

    } catch (err) {
        document.getElementById('erroTexto').textContent = 'Não foi possível se conectar ao servidor de análise.';
        carregando.style.display = 'none';
        erro.style.display = '';
    }
}

document.addEventListener('DOMContentLoaded', carregarAvaliacao);
<?php endif; ?>
</script>

</body>
</html>
