<?php
/* ============================================================
   empresa/perfil.php
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF

if (!isset($_SESSION['empresa_id'])) {
    header("Location: login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/endereco_empresa.php";   // Stage 3: endereço completo

$empresa_id = intval($_SESSION['empresa_id']);

// ── PRG: redireciona após save para evitar resubmissão no F5 ──────────────────
$saved = isset($_GET['saved']) ? (int)$_GET['saved'] : 0; // 1=ok, 2=erro

// ── Processamento POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // Stage 3: limites = tamanho das colunas (antes, texto longo = erro fatal)
    $nome_empresa      = mb_substr(trim($_POST['nome_empresa']     ?? ''), 0, 150);
    $email             = trim($_POST['email']             ?? '');   // validado abaixo (não cortado)
    $cnpj              = cnpj_formatado($_POST['cnpj'] ?? '');   // null se não tiver 14 dígitos
    $cidade            = mb_substr(trim($_POST['cidade']            ?? ''), 0, 100);
    $estado            = mb_substr(strtoupper(trim($_POST['estado'] ?? '')), 0, 2);
    $area_atuacao      = mb_substr(trim($_POST['area_atuacao']      ?? ''), 0, 100);
    $descricao         = trim($_POST['descricao']         ?? '');
    $site_oficial      = mb_substr(trim($_POST['site_oficial']      ?? ''), 0, 255);
    $linkedin          = mb_substr(trim($_POST['linkedin']          ?? ''), 0, 255);
    $perfil_verificado = isset($_POST['perfil_verificado']) ? 1 : 0;
    $end               = endereco_empresa_do_post();

    if (empty($nome_empresa) || mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $cnpj === null
        || ($end['cep'] !== '' && strlen($end['cep']) !== 8)) {
        header("Location: perfil.php?saved=2");
        exit;
    }

    // Stage 3: e-mail/CNPJ de outra empresa → aviso (antes: erro fatal de chave única)
    $stmt = $conn->prepare("SELECT id FROM empresas WHERE (email = ? OR cnpj = ?) AND id <> ? LIMIT 1");
    $stmt->bind_param("ssi", $email, $cnpj, $empresa_id);
    $stmt->execute();
    $em_uso = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($em_uso) {
        header("Location: perfil.php?saved=3");
        exit;
    }

    // Stage 3: trocar o e-mail desfaz a verificação (o endereço novo não foi verificado)
    $stmt = $conn->prepare("SELECT email, email_verificado FROM empresas WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $empresa_id);
    $stmt->execute();
    $atual = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $email_verificado = (mb_strtolower((string)$atual['email']) === mb_strtolower($email))
                        ? (int)$atual['email_verificado'] : 0;

    $stmt = $conn->prepare(
        "UPDATE empresas
         SET nome_empresa      = ?,
             email             = ?,
             email_verificado  = ?,
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
        "ssisssssssii",
        $nome_empresa, $email, $email_verificado, $cnpj, $cidade, $estado,
        $area_atuacao, $descricao, $site_oficial, $linkedin,
        $perfil_verificado, $empresa_id
    );
    $ok = $stmt->execute();
    $stmt->close();

    // Stage 3: endereço completo (só depois que a migração aditiva for aplicada)
    if ($ok && empresa_tem_endereco($conn)) {
        $stmt = $conn->prepare(
            "UPDATE empresas
             SET cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?
             WHERE id = ?"
        );
        $stmt->bind_param("sssssi", $end['cep'], $end['logradouro'], $end['numero'],
                          $end['complemento'], $end['bairro'], $empresa_id);
        $ok = $stmt->execute();
        $stmt->close();
    }

    if ($ok) {
        $_SESSION['empresa_nome'] = $nome_empresa;
        header("Location: perfil.php?saved=1");
        exit;
    }

    header("Location: perfil.php?saved=2");
    exit;
}

// ── Leitura dos dados da empresa ──────────────────────────────────────────────
$tem_endereco  = empresa_tem_endereco($conn);   // Stage 3
$cols_endereco = $tem_endereco ? ', cep, logradouro, numero, complemento, bairro' : '';
$stmt = $conn->prepare(
    "SELECT nome_empresa, email, cnpj, cidade, estado, telefone,
            area_atuacao, descricao, site_oficial, linkedin, perfil_verificado{$cols_endereco}
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
<?php elseif ($saved === 3): ?>
<div id="toast" class="toast toast-error">✕ Este e-mail ou CNPJ já está em uso por outra empresa.</div>
<?php endif; ?>

<!-- ============================================================
     LAYOUT DE PERFIL (form único — cobre todos os campos)
     ============================================================ -->
<form method="POST" action="perfil.php" id="formPerfil">
    <?= csrf_field() ?>

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
            <?php $endereco_txt = endereco_empresa_formatado($emp);   // Stage 3: endereço completo ?>
            <?php if ($endereco_txt !== ''): ?>
            <div class="profile-info-item">
                <p class="profile-info-label">Endereço</p>
                <p class="profile-info-value">📍 <?= htmlspecialchars($endereco_txt) ?></p>
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
            <?php if ($tem_endereco): ?>
            <!-- Stage 3: endereço completo — CEP primeiro; rua, bairro, cidade e UF
                 vêm do ViaCEP; número e complemento são preenchidos à mão -->
            <div class="form-group">
                <label class="form-label">CEP</label>
                <input type="text" name="cep" id="cep" class="form-control"
                       placeholder="00000-000" maxlength="9" inputmode="numeric"
                       value="<?= htmlspecialchars(preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $emp['cep'] ?? '')) ?>">
                <small id="cepStatus" class="text-muted" style="font-size:12px;" aria-live="polite"></small>
            </div>
            <div class="form-group">
                <label class="form-label">Rua / Logradouro</label>
                <input type="text" name="logradouro" id="logradouro" class="form-control" maxlength="150"
                       value="<?= htmlspecialchars($emp['logradouro'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Número</label>
                    <input type="text" name="numero" id="numero" class="form-control" maxlength="10"
                           placeholder="Ex: 123 ou S/N" value="<?= htmlspecialchars($emp['numero'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Complemento</label>
                    <input type="text" name="complemento" id="complemento" class="form-control" maxlength="100"
                           placeholder="Ex: Sala 4" value="<?= htmlspecialchars($emp['complemento'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Bairro</label>
                <input type="text" name="bairro" id="bairro" class="form-control" maxlength="100"
                       value="<?= htmlspecialchars($emp['bairro'] ?? '') ?>">
            </div>
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control" maxlength="100"
                           value="<?= htmlspecialchars($emp['cidade'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">UF</label>
                    <input type="text" name="estado" id="estado" class="form-control"
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
<div id="modalExcluir"
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
    <?= csrf_field() ?>
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


<!-- FABs + Assistente + Acessibilidade (Stage 3: antes os botões não tinham painel nem script) -->
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>


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
    if (!editando) {
        // Revisão Stage 3: "Cancelar" descarta o que foi digitado no modo edição —
        // senão os outros botões "Salvar" do formPerfil enviariam essas alterações
        document.querySelectorAll('#editNome input, #editNome select, #editDados input, #editDados select')
            .forEach(el => {
                if (el.type === 'checkbox' || el.type === 'radio') el.checked = el.defaultChecked;
                else if (el.tagName === 'SELECT') [...el.options].forEach(o => { o.selected = o.defaultSelected; });
                else el.value = el.defaultValue;
            });
        const st = document.getElementById('cepStatus');
        if (st) st.textContent = '';
        ultimoCepBuscado = (document.getElementById('cep')?.value || '').replace(/\D/g, '');
    }

    document.getElementById('viewNome').classList.toggle('hidden', editando);
    document.getElementById('editNome').classList.toggle('hidden', !editando);
    document.getElementById('viewDados').classList.toggle('hidden', editando);
    document.getElementById('editDados').classList.toggle('hidden', !editando);

    btnEditar.textContent = editando ? 'Cancelar' : 'Editar Dados';
    btnEditar.classList.toggle('btn-secondary', !editando);
    btnEditar.classList.toggle('btn-ghost',     editando);
});

/* ── Stage 3: endereço — CEP primeiro (ViaCEP); número e complemento manuais ── */
// Revisão Stage 3: começa com o CEP já preenchido — só focar/sair do campo não
// rebusca nem sobrescreve rua/bairro digitados à mão (CEPs gerais de cidade)
let ultimoCepBuscado = (document.getElementById('cep')?.value || '').replace(/\D/g, '');
async function buscarCep() {
    const cepEl  = document.getElementById('cep');
    const status = document.getElementById('cepStatus');
    const raw    = (cepEl?.value || '').replace(/\D/g, '');
    if (raw.length !== 8 || raw === ultimoCepBuscado) return;
    ultimoCepBuscado = raw;
    if (status) status.textContent = 'Buscando endereço...';
    try {
        const res = await fetch(`https://viacep.com.br/ws/${raw}/json/`);
        const d   = await res.json();
        if (d.erro) {
            if (status) status.textContent = 'CEP não encontrado — preencha o endereço manualmente.';
            return;
        }
        const preencher = (id, valor) => { const el = document.getElementById(id); if (el) el.value = valor || ''; };
        preencher('logradouro', d.logradouro);
        preencher('bairro',     d.bairro);
        preencher('cidade',     d.localidade);
        preencher('estado',     d.uf);
        if (status) status.textContent = '✓ Endereço preenchido. Informe o número e o complemento.';
        document.getElementById('numero')?.focus();
    } catch {
        if (status) status.textContent = 'Não foi possível buscar o CEP agora — preencha manualmente.';
    }
}
document.getElementById('cep')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 8);
    if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
    this.value = v;
    if (v.length === 9) buscarCep();
});
document.getElementById('cep')?.addEventListener('blur', buscarCep);

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