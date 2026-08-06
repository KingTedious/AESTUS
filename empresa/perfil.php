<?php
/* ============================================================
   empresa/perfil.php
   ============================================================ */
session_start();

if (!isset($_SESSION['empresa_id'])) {
    header("Location: login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$empresa_id = intval($_SESSION['empresa_id']);

// ── PRG: redireciona após save para evitar resubmissão no F5 ──────────────────
$saved = isset($_GET['saved']) ? (int)$_GET['saved'] : 0; // 1=ok, 2=erro

// ── Processamento POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome_empresa      = trim($_POST['nome_empresa']     ?? '');
    $email             = trim($_POST['email']             ?? '');
    $cnpj              = trim($_POST['cnpj']              ?? '');
    $cidade            = trim($_POST['cidade']            ?? '');
    $estado            = strtoupper(trim($_POST['estado'] ?? ''));
    $area_atuacao      = trim($_POST['area_atuacao']      ?? '');
    $descricao         = trim($_POST['descricao']         ?? '');
    $site_oficial      = trim($_POST['site_oficial']      ?? '');
    $linkedin          = trim($_POST['linkedin']          ?? '');
    $perfil_verificado = isset($_POST['perfil_verificado']) ? 1 : 0;

    if (empty($nome_empresa) || empty($email) || empty($cnpj)) {
        header("Location: perfil.php?saved=2");
        exit;
    }

    $stmt = $conn->prepare(
        "UPDATE empresas
         SET nome_empresa      = ?,
             email             = ?,
             cnpj              = ?,
             cidade            = ?,
             estado            = ?,
             area_atuacao      = ?,
             descricao         = ?,
             site_oficial      = ?,
             linkedin          = ?,
             perfil_verificado = ?
         WHERE id = ?"
    );
    $stmt->bind_param(
        "sssssssssii",
        $nome_empresa, $email, $cnpj, $cidade, $estado,
        $area_atuacao, $descricao, $site_oficial, $linkedin,
        $perfil_verificado, $empresa_id
    );

    if ($stmt->execute()) {
        $_SESSION['empresa_nome'] = $nome_empresa;
        $stmt->close();
        header("Location: perfil.php?saved=1");
        exit;
    }

    $stmt->close();
    header("Location: perfil.php?saved=2");
    exit;
}

// ── Leitura dos dados da empresa ──────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT nome_empresa, email, cnpj, cidade, estado, telefone,
            area_atuacao, descricao, site_oficial, linkedin, perfil_verificado
     FROM   empresas
     WHERE  id = ?
     LIMIT  1"
);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) {
    echo "Erro ao carregar dados da empresa.";
    exit;
}

// ── Vagas ativas ──────────────────────────────────────────────────────────────
$stmt_v = $conn->prepare(
    "SELECT id, titulo, area, cidade, estado
     FROM   vagas
     WHERE  empresa_id = ?
     ORDER  BY criado_em DESC"
);
$stmt_v->bind_param("i", $empresa_id);
$stmt_v->execute();
$result_vagas = $stmt_v->get_result();
$stmt_v->close();

// Inicial para o avatar
$inicial = strtoupper(mb_substr($emp['nome_empresa'], 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body>

<!-- ============================================================
     BARRA DE TOPO
     ============================================================ -->
<div class="profile-bar">
    <div class="d-flex align-center gap-12">
        <a href="../dashboard/empresa.php" class="icon-btn" title="Voltar">←</a>
        <span class="profile-bar-title">Meu Perfil</span>
    </div>
    <!-- "Editar Dados" alterna o sidebar entre view e edit mode (via JS) -->
    <button type="button" class="btn btn-secondary btn-sm" id="btnEditar">
        Editar Dados
    </button>
</div>

<!-- ============================================================
     FEEDBACK DE SAVE
     ============================================================ -->
<?php if ($saved === 1): ?>
<div id="toast" class="toast toast-success">✓ Perfil atualizado com sucesso!</div>
<?php elseif ($saved === 2): ?>
<div id="toast" class="toast toast-error">✕ Erro ao salvar. Verifique os campos obrigatórios.</div>
<?php endif; ?>

<!-- ============================================================
     LAYOUT DE PERFIL (form único — cobre todos os campos)
     ============================================================ -->
<form method="POST" action="perfil.php" id="formPerfil">

<div class="profile-page">

    <!-- ── SIDEBAR ESQUERDO ──────────────────────────────────────────────── -->
    <aside class="profile-side">

        <div class="profile-side-avatar">
            <div class="avatar avatar-xl"><?= $inicial ?></div>

            <!-- VIEW: nome e role como texto -->
            <div id="viewNome">
                <p class="profile-side-name"><?= htmlspecialchars($emp['nome_empresa']) ?></p>
                <p class="profile-side-role">🏢 Perfil Corporativo</p>
            </div>

            <!-- EDIT: input de nome (oculto por padrão) -->
            <div id="editNome" class="hidden w-full">
                <div class="form-group" style="width:100%;">
                    <label class="form-label">Nome da Empresa *</label>
                    <input type="text" name="nome_empresa" class="form-control"
                           value="<?= htmlspecialchars($emp['nome_empresa']) ?>" required>
                </div>
            </div>
        </div>

        <!-- Informações básicas (view mode) -->
        <div id="viewDados">
            <div class="profile-info-item">
                <p class="profile-info-label">E-mail</p>
                <p class="profile-info-value"><?= htmlspecialchars($emp['email']) ?></p>
            </div>
            <div class="profile-info-item">
                <p class="profile-info-label">CNPJ</p>
                <p class="profile-info-value"><?= htmlspecialchars($emp['cnpj']) ?></p>
            </div>
            <?php if (!empty($emp['area_atuacao'])): ?>
            <div class="profile-info-item">
                <p class="profile-info-label">Área de Atuação</p>
                <p class="profile-info-value"><?= htmlspecialchars($emp['area_atuacao']) ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($emp['cidade'])): ?>
            <div class="profile-info-item">
                <p class="profile-info-label">Localização</p>
                <p class="profile-info-value">
                    📍 <?= htmlspecialchars($emp['cidade']) ?><?= !empty($emp['estado']) ? ', ' . htmlspecialchars($emp['estado']) : '' ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Informações básicas (edit mode — oculto por padrão) -->
        <div id="editDados" class="hidden">
            <div class="form-group">
                <label class="form-label">E-mail Corporativo *</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($emp['email']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">CNPJ *</label>
                <input type="text" name="cnpj" class="form-control"
                       value="<?= htmlspecialchars($emp['cnpj']) ?>" maxlength="18">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade" class="form-control"
                           value="<?= htmlspecialchars($emp['cidade'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">UF</label>
                    <input type="text" name="estado" class="form-control"
                           value="<?= htmlspecialchars($emp['estado'] ?? '') ?>"
                           maxlength="2" placeholder="SP">
                </div>
            </div>
            <!-- Salvar dados básicos (aparece só no edit mode) -->
            <button type="submit" class="btn btn-primary btn-full btn-sm">
                Salvar Dados Básicos
            </button>
        </div>

        <!-- Excluir conta (sempre visível, no rodapé do sidebar) -->
        <div style="margin-top:auto;padding-top:24px;border-top:1px solid var(--border);">
            <button type="button" class="btn btn-danger btn-full btn-sm" id="btnExcluir">
                Excluir Conta
            </button>
        </div>

    </aside><!-- /.profile-side -->

    <!-- ── CONTEÚDO PRINCIPAL ─────────────────────────────────────────────── -->
    <main class="profile-main">

        <!-- SEÇÃO: Dados Institucionais -->
        <div class="profile-section fade-up">
            <div class="profile-section-head">
                <h2 class="profile-section-title">🏢 Dados Institucionais</h2>
            </div>

            <!-- Área de atuação -->
            <div class="form-group">
                <label class="form-label">Área de Atuação</label>
                <input type="text" name="area_atuacao" class="form-control"
                       placeholder="Ex: Tecnologia, Varejo"
                       value="<?= htmlspecialchars($emp['area_atuacao'] ?? '') ?>">
            </div>

            <!-- Missão e Visão -->
            <div class="form-group">
                <label class="form-label">Sobre a Empresa (Missão e Visão)</label>
                <textarea name="descricao" class="form-control"
                          placeholder="Descreva a missão, visão e valores da sua empresa..."
                          rows="5"><?= htmlspecialchars($emp['descricao'] ?? '') ?></textarea>
            </div>

            <!-- Site + LinkedIn -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Site Oficial</label>
                    <div class="input-group">
                        <span class="input-icon">🌐</span>
                        <input type="url" name="site_oficial" class="form-control"
                               placeholder="www.suaempresa.com.br"
                               value="<?= htmlspecialchars($emp['site_oficial'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">LinkedIn</label>
                    <div class="input-group">
                        <span class="input-icon">💼</span>
                        <input type="text" name="linkedin" class="form-control"
                               placeholder="linkedin.com/company/suaempresa"
                               value="<?= htmlspecialchars($emp['linkedin'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        </div>


        <!-- SEÇÃO: Vagas Ativas -->
        <div class="profile-section fade-up">
            <div class="profile-section-head">
                <h2 class="profile-section-title">📋 Vagas Ativas</h2>
                <a href="../Vagas/criar.php" class="btn btn-primary btn-sm">+ Criar Vaga</a>
            </div>

            <?php if ($result_vagas->num_rows > 0): ?>
                <?php while ($v = $result_vagas->fetch_assoc()): ?>
                <div class="job-active-item">
                    <div>
                        <p class="job-active-title"><?= htmlspecialchars($v['titulo']) ?></p>
                        <p class="job-active-sub">
                            <?= !empty($v['area'])   ? htmlspecialchars($v['area'])   . ' • ' : '' ?>
                            <?= !empty($v['cidade'])  ? htmlspecialchars($v['cidade'])         : '' ?>
                            <?= !empty($v['estado'])  ? ', ' . htmlspecialchars($v['estado'])  : '' ?>
                        </p>
                    </div>
                    <a href="../Vagas/candidatos.php?id=<?= intval($v['id']) ?>"
                       class="btn btn-ghost btn-sm">
                        Gerenciar
                    </a>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-secondary" style="font-size:14px;">
                    Nenhuma vaga publicada ainda.
                    <a href="../Vagas/criar.php">Criar a primeira vaga →</a>
                </p>
            <?php endif; ?>
        </div>


        <!-- SEÇÃO: Configurações de Visibilidade -->
        <div class="profile-section fade-up">
            <div class="profile-section-head">
                <h2 class="profile-section-title">⚙ Configurações de Visibilidade</h2>
            </div>
            <p class="text-secondary" style="font-size:13px;margin-bottom:16px;">
                Como os candidatos veem sua empresa
            </p>

            <div class="visibility-toggle-item">
                <div class="vt-text">
                    <strong>Perfil Verificado</strong>
                    <span>Sua empresa aparecerá com selo de verificação para candidatos.</span>
                </div>
                <label class="toggle">
                    <input type="checkbox"
                           name="perfil_verificado"
                           value="1"
                           <?= $emp['perfil_verificado'] ? 'checked' : '' ?>>
                    <span class="toggle-track"></span>
                </label>
            </div>
        </div>

    </main><!-- /.profile-main -->

</div><!-- /.profile-page -->
</form>


<!-- ============================================================
     MODAL DE EXCLUSÃO
     ============================================================ -->
<div id="modalExcluir" class="hidden"
     style="position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:2000;
            display:none;align-items:center;justify-content:center;">
    <div class="card" style="max-width:400px;width:90%;text-align:center;padding:36px 28px;">
        <p style="font-size:36px;margin-bottom:12px;">⚠️</p>
        <h3 style="font-weight:700;margin-bottom:10px;">Excluir Conta</h3>
        <p class="text-secondary" style="font-size:14px;margin-bottom:24px;">
            Esta ação é <strong>irreversível</strong>. Todas as vagas e dados da empresa
            serão permanentemente apagados.
        </p>
        <form method="POST" action="excluir_conta.php">
            <input type="hidden" name="confirmar" value="1">
            <div class="d-flex gap-12" style="justify-content:center;">
                <button type="button" class="btn btn-secondary" id="btnCancelarExcluir">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-danger">
                    Excluir Permanentemente
                </button>
            </div>
        </form>
    </div>
</div>


<!-- FABs -->
<div class="fab-stack">
    <button class="fab fab-ai"   id="btnChat" title="Assistente IA"  aria-label="Assistente IA">✦</button>
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Acessibilidade">♿</button>
</div>
<div class="user-indicator" title="<?= htmlspecialchars($emp['nome_empresa']) ?>">
    <?= $inicial ?>
</div>


<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script>
/* ── Tema ──────────────────────────────────────────────────── */
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

/* ── Toast de feedback ─────────────────────────────────────── */
const toast = document.getElementById('toast');
if (toast) {
    setTimeout(() => toast.classList.add('toast-visible'), 100);
    setTimeout(() => toast.classList.remove('toast-visible'), 3500);
}

/* ── Toggle sidebar: view ↔ edit ──────────────────────────── */
const btnEditar = document.getElementById('btnEditar');
let   editando  = false;

btnEditar?.addEventListener('click', () => {
    editando = !editando;

    document.getElementById('viewNome').classList.toggle('hidden', editando);
    document.getElementById('editNome').classList.toggle('hidden', !editando);
    document.getElementById('viewDados').classList.toggle('hidden', editando);
    document.getElementById('editDados').classList.toggle('hidden', !editando);

    btnEditar.textContent = editando ? 'Cancelar' : 'Editar Dados';
    btnEditar.classList.toggle('btn-secondary', !editando);
    btnEditar.classList.toggle('btn-ghost',     editando);
});

/* ── Modal de exclusão ─────────────────────────────────────── */
const modal          = document.getElementById('modalExcluir');
const btnExcluir     = document.getElementById('btnExcluir');
const btnCancelar    = document.getElementById('btnCancelarExcluir');

btnExcluir?.addEventListener('click', () => {
    modal.style.display = 'flex';
});
btnCancelar?.addEventListener('click', () => {
    modal.style.display = 'none';
});
modal?.addEventListener('click', e => {
    if (e.target === modal) modal.style.display = 'none';
});
</script>

</body>
</html>