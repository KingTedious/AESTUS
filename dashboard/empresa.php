<?php
/* ============================================================
   dashboard/empresa.php
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF
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
// Nota (Etapa 3): sem DISTINCT/sem colapsar por jovem — cada candidatura já é
// um par (jovem, vaga) inequívoco, então cada linha vira um card com o
// vaga_id certo para o botão "Avaliar Match com IA" (candidaturas.vaga_id +
// vagas.titulo). Um mesmo jovem que se candidatou a 2 vagas desta empresa
// aparece em 2 cards, um por vaga — evita escolher uma vaga arbitrária.
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
         TIMESTAMPDIFF(YEAR, j.data_nascimento, CURDATE()) AS idade,
         c.vaga_id  AS vaga_id_card,
         v.titulo   AS vaga_titulo_card
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
$modo_fallback = false;

// Fallback: buscar por área compatível se não há candidatos ainda.
// Aqui não existe candidatura, então não existe um vaga_id específico por
// jovem — ver bloco de decisão logo abaixo (vaga_fallback_id / seletor).
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
    $modo_fallback = true;
}

$total_cands = $result_cands->num_rows;

// No modo fallback não há vaga_id por card. Resolve isso com a menor
// alteração possível dado o que já existe na página: se a empresa tem
// exatamente 1 vaga, essa é a vaga (zero ambiguidade); com 2+ vagas, o
// usuário escolhe explicitamente pelo seletor (evita adivinhar por ele);
// com 0 vagas, não há nada para avaliar e o botão fica desabilitado.
$vaga_fallback_unica = null;
if ($modo_fallback && $total_vagas === 1) {
    $result_vagas->data_seek(0);
    $primeira_vaga = $result_vagas->fetch_assoc();
    $vaga_fallback_unica = ['id' => $primeira_vaga['id'], 'titulo' => $primeira_vaga['titulo']];
    $result_vagas->data_seek(0);
}

$vagas_para_seletor = [];
if ($modo_fallback && $total_vagas > 1) {
    $result_vagas->data_seek(0);
    while ($v = $result_vagas->fetch_assoc()) {
        $vagas_para_seletor[] = ['id' => $v['id'], 'titulo' => $v['titulo']];
    }
    $result_vagas->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Empresa | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>
<?= csrf_js() ?>

<!-- ============================================================
     NAVBAR INTERNA
     ============================================================ -->
<nav class="navbar-inner">
    <a href="empresa.php" class="nav-logo" aria-label="JovemConnect — meu painel">
        <picture class="marca marca-nav">
            <source media="(max-width: 480px)" srcset="../assets/img/marca/jovemconnect-simbolo.png" width="192" height="192">
            <img src="../assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="493" height="160">
        </picture>
    </a>

    <div class="navbar-inner-center">
        <div class="search-bar">
            <span class="search-icon i i-search" aria-hidden="true"></span>
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
        <!-- Stage 3: Vagas/minhas.php (editar/excluir vagas) não tinha nenhum link de acesso -->
        <a href="../Vagas/minhas.php" class="icon-btn" title="Minhas vagas" aria-label="Minhas vagas"><span class="i i-briefcase" aria-hidden="true"></span></a>
        <a href="../empresa/perfil.php"  class="icon-btn" title="Meu Perfil" aria-label="Meu Perfil"><span class="i i-user" aria-hidden="true"></span></a>
        <a href="../logout.php"          class="icon-btn text-danger" title="Sair" aria-label="Sair"><span class="i i-log-out" aria-hidden="true"></span></a>
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

            // Qual vaga este card representa (ver bloco de decisão logo acima das queries):
            //  - modo normal: vem direto da candidatura (par jovem+vaga inequívoco);
            //  - fallback com 1 vaga só: essa é a vaga, sem ambiguidade;
            //  - fallback com 2+ vagas: usuário escolhe no seletor do próprio card;
            //  - fallback com 0 vagas: não há o que avaliar, botão desabilitado.
            $vaga_id_card    = null;
            $vaga_titulo_card = null;
            if (!$modo_fallback) {
                $vaga_id_card     = intval($jovem['vaga_id_card']);
                $vaga_titulo_card = $jovem['vaga_titulo_card'];
            } elseif ($vaga_fallback_unica) {
                $vaga_id_card     = intval($vaga_fallback_unica['id']);
                $vaga_titulo_card = $vaga_fallback_unica['titulo'];
            }
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
                <span class="i i-graduation-cap" aria-hidden="true"></span>
                <span><?= $eh_tecnico ? 'Técnico em ' : '' ?><?= $curso ?></span>
            </div>
            <?php endif; ?>

            <!-- Bio resumida -->
            <p class="candidate-bio"><?= $resumo ?></p>

            <?php if ($vaga_titulo_card): ?>
            <!-- Contexto: para qual vaga esta avaliação é (evita ambiguidade) -->
            <div class="candidate-area" style="margin-bottom:10px;">
                <span class="i i-briefcase" aria-hidden="true"></span>
                <span>Candidatura para: <strong><?= htmlspecialchars($vaga_titulo_card) ?></strong></span>
            </div>
            <?php elseif ($modo_fallback && count($vagas_para_seletor) > 0): ?>
            <!-- Sem candidatura ainda: a empresa escolhe explicitamente contra qual vaga comparar -->
            <div class="form-group" style="margin-bottom:10px;">
                <label class="form-label" style="font-size:12px;">Avaliar para qual vaga?</label>
                <select class="form-control select-vaga-avaliar" style="font-size:13px;padding:8px;"
                        aria-label="Avaliar para qual vaga?">
                    <option value="">Selecione uma vaga...</option>
                    <?php foreach ($vagas_para_seletor as $vOpt): ?>
                    <option value="<?= intval($vOpt['id']) ?>"><?= htmlspecialchars($vOpt['titulo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Botão de match IA -->
            <?php if ($vaga_id_card): ?>
            <button
                class="btn btn-ia"
                onclick="avaliarMatch(<?= intval($jovem['id']) ?>, <?= $vaga_id_card ?>, this)"
                data-jovem="<?= intval($jovem['id']) ?>">
                <span class="i i-sparkles" aria-hidden="true"></span> Avaliar Match com IA
            </button>
            <?php elseif ($modo_fallback && count($vagas_para_seletor) > 0): ?>
            <button
                class="btn btn-ia"
                onclick="avaliarMatchComSeletor(<?= intval($jovem['id']) ?>, this)"
                data-jovem="<?= intval($jovem['id']) ?>">
                <span class="i i-sparkles" aria-hidden="true"></span> Avaliar Match com IA
            </button>
            <?php else: ?>
            <button class="btn btn-ia" disabled title="Publique uma vaga para poder avaliar candidatos.">
                <span class="i i-sparkles" aria-hidden="true"></span> Avaliar Match com IA
            </button>
            <?php endif; ?>

        </div>
        <?php endwhile; ?>
    </div><!-- /#gridCandidatos -->

    <?php else: ?>

    <div class="card fade-up"
         style="text-align:center;padding:56px 32px;max-width:460px;margin:0 auto;">
        <div class="icone-destaque" aria-hidden="true"><span class="i i-search" aria-hidden="true"></span></div>
        <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">Nenhum candidato ainda</h3>
        <p class="text-secondary"
           style="font-size:14px;line-height:1.6;margin-bottom:28px;">
            Publique vagas para começar a receber candidaturas de jovens talentos.
        </p>
        <a href="../Vagas/criar.php" class="btn btn-primary btn-full"><span class="i i-plus" aria-hidden="true"></span> Criar Nova Vaga</a>
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
            <a href="../Vagas/criar.php" class="btn btn-primary btn-sm"><span class="i i-plus" aria-hidden="true"></span> Criar Vaga</a>
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

            <div class="job-card-footer">
                <a href="../Vagas/candidatos.php?id=<?= intval($vaga['id']) ?>&amp;voltar=painel"
                   class="btn btn-ghost btn-sm btn-full">
                    Gerenciar Candidatos <span class="i i-arrow-right" aria-hidden="true"></span>
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
     ASSISTENTE + ACESSIBILIDADE + FABs
     Stage 4: este painel tinha uma cópia própria do chat e do painel de
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
async function avaliarMatch(jovemId, vagaId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Analisando...';

    try {
        const res = await fetch('../IA/avaliar_ajax.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                action:     'match',
                jovem_id:   jovemId,
                vaga_id:    vagaId,
                empresa_id: <?= $empresa_id ?>
            })
        });

        const data = await res.json();

        if (!res.ok || typeof data.score === 'undefined') {
            // Stage 3: mostra o motivo no próprio botão (antes só no tooltip)
            btn.disabled    = false;
            btn.replaceChildren(jcIcone('sparkles'), data.error || 'Não foi possível avaliar agora.');
            btn.title       = 'Clique para tentar novamente';
            setTimeout(() => { btn.innerHTML = '<span class="i i-sparkles" aria-hidden="true"></span> Avaliar Match com IA'; btn.title = ''; }, 3500);
            return;
        }

        btn.replaceChildren(jcIcone('sparkles'), `Match: ${data.score}%`);   // Stage 5: ícone + texto
        btn.style.color = 'var(--accent)';
        btn.classList.add('match-resultado');   // Stage 5.1: resultado legível (sem o esmaecido de desabilitado)
        btn.title       = data.analise ?? '';

        // Link para a análise completa (reaproveita Vagas/avaliar.php da Etapa 3) —
        // o botão só mostra o score; a análise por extenso e os motivos individuais
        // só cabem numa tooltip de hover, pouco visível (principalmente em touch).
        if (!btn.nextElementSibling?.classList.contains('link-analise-completa')) {
            const linkAnalise = document.createElement('a');
            linkAnalise.href      = `../Vagas/avaliar.php?vaga_id=${vagaId}&jovem_id=${jovemId}`;
            linkAnalise.className = 'link-analise-completa back-link';
            linkAnalise.style.cssText = 'display:block;margin-top:8px;font-size:12px;';
            linkAnalise.replaceChildren('Ver análise completa ', jcIcone('arrow-right'));
            btn.after(linkAnalise);
        }

    } catch (err) {
        // Stage 4: erro de rede/resposta inválida também avisa (antes o botão só voltava ao normal)
        btn.disabled    = false;
        btn.replaceChildren(jcIcone('sparkles'), 'Não foi possível avaliar agora. Tente de novo.');
        btn.title       = 'Clique para tentar novamente';
        setTimeout(() => { btn.innerHTML = '<span class="i i-sparkles" aria-hidden="true"></span> Avaliar Match com IA'; btn.title = ''; }, 3500);
        console.error('Erro no match IA:', err);
    }
}

/* Cards sem candidatura (modo "descoberta"): a vaga vem do <select> do
   próprio card em vez de já vir pronta — exige escolha explícita antes
   de avaliar, para nunca comparar com uma vaga arbitrária. */
function avaliarMatchComSeletor(jovemId, btn) {
    const select = btn.closest('.candidate-card')?.querySelector('.select-vaga-avaliar');
    const vagaId = select ? parseInt(select.value, 10) : 0;

    if (!vagaId) {
        select?.focus();
        select?.classList.add('input-error');
        // Stage 4: antes só a borda ficava vermelha, sem dizer o que fazer
        window.jcAviso?.('Escolha a vaga para comparar antes de avaliar o candidato.');
        return;
    }

    avaliarMatch(jovemId, vagaId, btn);
}

/* Stage 4: chat e acessibilidade agora vêm de includes/assistente.php */

/* Stage 4: confirmação depois da verificação da conta (o aviso se perdia no caminho) */
(function () {
    const v = new URLSearchParams(location.search).get('verificado');
    if (v !== 'email' && v !== 'telefone') return;
    window.jcAviso?.(v === 'email' ? 'E-mail verificado com sucesso!' : 'Telefone verificado com sucesso!', 'sucesso');
    history.replaceState(null, '', location.pathname);   // F5 não repete o aviso
})();

</script>

<?php
$stmt_vagas->close();
$stmt_cands->close();
?>
</body>
</html>