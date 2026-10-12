<?php
/* ============================================================
   empresa/perfil.php
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

if (!isset($_SESSION['empresa_id'])) {
    header("Location: login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/endereco_empresa.php";   // Endereço completo
require_once dirname(__DIR__) . "/includes/locais.php";             // Locais de trabalho
require_once dirname(__DIR__) . "/includes/moderacao.php";          // Stage 7: validação + moderação centrais

$empresa_id = intval($_SESSION['empresa_id']);

// ── PRG: redireciona após save para evitar resubmissão no F5 ──────────────────
$saved = isset($_GET['saved']) ? (int)$_GET['saved'] : 0; // 1=ok, 2=erro, 3=em uso, 4=texto recusado (Stage 7)

// ── Processamento POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // Limites = tamanho das colunas
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

    // E-mail/CNPJ de outra empresa → aviso
    $stmt = $conn->prepare("SELECT id FROM empresas WHERE (email = ? OR cnpj = ?) AND id <> ? LIMIT 1");
    $stmt->bind_param("ssi", $email, $cnpj, $empresa_id);
    $stmt->execute();
    $em_uso = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($em_uso) {
        header("Location: perfil.php?saved=3");
        exit;
    }

    // Trocar o e-mail desfaz a verificação (o endereço novo não foi verificado)
    $cols_end_atual = empresa_tem_endereco($conn) ? ', logradouro, numero, complemento, bairro' : '';
    $stmt = $conn->prepare("SELECT email, email_verificado, nome_empresa, cidade, area_atuacao, descricao, site_oficial, linkedin{$cols_end_atual}
                            FROM empresas WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $empresa_id);
    $stmt->execute();
    $atual = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $email_verificado = (mb_strtolower((string)$atual['email']) === mb_strtolower($email))
                        ? (int)$atual['email_verificado'] : 0;

    // Stage 7: valida e modera só o que MUDOU (texto antigo não trava o salvamento)
    $igual = fn($a, $b): bool => trim(str_replace("\r\n", "\n", (string) $a)) === trim(str_replace("\r\n", "\n", (string) $b));
    $entrada = [];
    foreach (['empresa.nome' => ['nome_empresa', $nome_empresa], 'empresa.cidade' => ['cidade', $cidade],
              'empresa.area_atuacao' => ['area_atuacao', $area_atuacao], 'empresa.descricao' => ['descricao', $descricao],
              'empresa.site' => ['site_oficial', $site_oficial], 'empresa.linkedin' => ['linkedin', $linkedin],
              'local.logradouro' => ['logradouro', $end['logradouro']], 'local.numero' => ['numero', $end['numero']],
              'local.complemento' => ['complemento', $end['complemento']], 'local.bairro' => ['bairro', $end['bairro']]]
             as $chave => [$coluna, $valor]) {
        if (!$igual($valor, $atual[$coluna] ?? '')) {
            $entrada[$chave] = [$valor, $coluna];
        }
    }
    $mod = jc_mod_formulario($entrada);
    if ($mod['erros']) {
        $_SESSION['erro_perfil'] = (string) reset($mod['erros']);
        header("Location: perfil.php?saved=4");
        exit;
    }
    $nome_empresa      = $mod['valores']['empresa.nome'] ?? $nome_empresa;
    $cidade            = $mod['valores']['empresa.cidade'] ?? $cidade;
    $area_atuacao      = $mod['valores']['empresa.area_atuacao'] ?? $area_atuacao;
    $descricao         = $mod['valores']['empresa.descricao'] ?? $descricao;
    $site_oficial      = $mod['valores']['empresa.site'] ?? $site_oficial;
    $linkedin          = $mod['valores']['empresa.linkedin'] ?? $linkedin;
    foreach (['logradouro', 'numero', 'complemento', 'bairro'] as $c) {
        $end[$c] = $mod['valores']['local.' . $c] ?? $end[$c];
    }

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

    // Endereço completo (só depois que a migração aditiva for aplicada)
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
        // Stage 7: conteúdo suspeito vai para a fila de moderação (o perfil é salvo normalmente)
        jc_mod_registrar($conn, 'empresa', $empresa_id, $mod['sinais'], 'empresa', $empresa_id);
        // Vagas que usam o endereço principal acompanham a mudança (cidade/UF/CEP)
        jc_vagas_sincronizar_empresa($conn, $empresa_id);
        $_SESSION['empresa_nome'] = $nome_empresa;
        header("Location: perfil.php?saved=1");
        exit;
    }

    header("Location: perfil.php?saved=2");
    exit;
}

// ── Leitura dos dados da empresa ──────────────────────────────────────────────
$tem_endereco  = empresa_tem_endereco($conn);
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

// Stage 7: texto recusado pela validação/moderação (?saved=4)
$erro_perfil = (string) ($_SESSION['erro_perfil'] ?? '');
unset($_SESSION['erro_perfil']);
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>

<!-- ============================================================
     BARRA DE TOPO
     ============================================================ -->
<div class="profile-bar">
    <div class="d-flex align-center gap-12">
        <a href="../dashboard/empresa.php" class="icon-btn" title="Voltar ao painel" aria-label="Voltar ao painel"><span class="i i-arrow-left" aria-hidden="true"></span></a>
        <span class="marca marca-compacta"><img src="../assets/img/marca/jovemconnect-simbolo.png" alt="JovemConnect" width="192" height="192"></span>
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
<div id="toast" class="toast toast-success"><span class="i i-check-circle" aria-hidden="true"></span>Perfil atualizado com sucesso!</div>
<?php elseif ($saved === 2): ?>
<div id="toast" class="toast toast-error"><span class="i i-x-circle" aria-hidden="true"></span>Não foi possível salvar. Confira o nome, o e-mail, o CNPJ (14 dígitos) e o CEP (8 dígitos).</div>
<?php elseif ($saved === 3): ?>
<div id="toast" class="toast toast-error"><span class="i i-x-circle" aria-hidden="true"></span>Este e-mail ou CNPJ já está em uso por outra empresa.</div>
<?php elseif ($saved === 4): ?>
<div id="toast" class="toast toast-error"><span class="i i-x-circle" aria-hidden="true"></span><?= htmlspecialchars($erro_perfil !== '' ? $erro_perfil : 'Confira o texto digitado. Nada foi salvo.') ?></div>
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
                <p class="profile-side-role"><span class="i i-building" aria-hidden="true"></span> Perfil Corporativo</p>
            </div>

            <!-- EDIT: input de nome (oculto por padrão) -->
            <div id="editNome" class="hidden w-full">
                <div class="form-group" style="width:100%;">
                    <label class="form-label" for="perfilNomeEmpresa">Nome da Empresa *</label>
                    <input type="text" name="nome_empresa" id="perfilNomeEmpresa" class="form-control"
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
            <?php $endereco_txt = endereco_empresa_formatado($emp);   // Endereço completo ?>
            <?php if ($endereco_txt !== ''): ?>
            <div class="profile-info-item">
                <p class="profile-info-label">Endereço</p>
                <p class="profile-info-value"><span class="i i-map-pin" aria-hidden="true"></span> <?= htmlspecialchars($endereco_txt) ?></p>
            </div>
            <?php endif; ?>
            <?php if (jc_locais_disponiveis($conn)): $n_locais = count(jc_locais_da_empresa($conn, $empresa_id)); ?>
            <!-- Sede + outros locais (escritório, filial...) escolhidos em cada vaga -->
            <div class="profile-info-item">
                <p class="profile-info-label">Locais de trabalho</p>
                <p class="profile-info-value">
                    Sede<?= $n_locais > 0 ? ' + ' . $n_locais . ($n_locais === 1 ? ' outro local' : ' outros locais') : '' ?> ·
                    <a href="locais.php">Gerenciar locais</a>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Informações básicas (edit mode — oculto por padrão) -->
        <div id="editDados" class="hidden">
            <div class="form-group">
                <label class="form-label" for="perfilEmail">E-mail Corporativo *</label>
                <input type="email" name="email" id="perfilEmail" class="form-control"
                       value="<?= htmlspecialchars($emp['email']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="perfilCnpj">CNPJ *</label>
                <input type="text" name="cnpj" id="perfilCnpj" class="form-control"
                       value="<?= htmlspecialchars($emp['cnpj']) ?>" maxlength="18">
            </div>
            <?php if ($tem_endereco): ?>
            <!-- Endereço completo — CEP primeiro; rua, bairro, cidade e UF
                 vêm do ViaCEP; número e complemento são preenchidos à mão -->
            <div class="form-group">
                <label class="form-label" for="cep">CEP</label>
                <input type="text" name="cep" id="cep" class="form-control"
                       placeholder="00000-000" maxlength="9" inputmode="numeric"
                       value="<?= htmlspecialchars(preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $emp['cep'] ?? '')) ?>">
                <small id="cepStatus" class="text-muted" style="font-size:12px;" aria-live="polite"></small>
            </div>
            <div class="form-group">
                <label class="form-label" for="logradouro">Rua / Logradouro</label>
                <input type="text" name="logradouro" id="logradouro" class="form-control" maxlength="150"
                       value="<?= htmlspecialchars($emp['logradouro'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="numero">Número</label>
                    <input type="text" name="numero" id="numero" class="form-control" maxlength="10"
                           placeholder="Ex: 123 ou S/N" value="<?= htmlspecialchars($emp['numero'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="complemento">Complemento</label>
                    <input type="text" name="complemento" id="complemento" class="form-control" maxlength="100"
                           placeholder="Ex: Sala 4" value="<?= htmlspecialchars($emp['complemento'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="bairro">Bairro</label>
                <input type="text" name="bairro" id="bairro" class="form-control" maxlength="100"
                       value="<?= htmlspecialchars($emp['bairro'] ?? '') ?>">
            </div>
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cidade">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control" maxlength="100"
                           value="<?= htmlspecialchars($emp['cidade'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="estado">UF</label>
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
                <h2 class="profile-section-title"><span class="i i-building" aria-hidden="true"></span> Dados Institucionais</h2>
            </div>

            <!-- Área de atuação -->
            <div class="form-group">
                <label class="form-label" for="perfilAreaAtuacao">Área de Atuação</label>
                <input type="text" name="area_atuacao" id="perfilAreaAtuacao" class="form-control"
                       placeholder="Ex: Tecnologia, Varejo"
                       value="<?= htmlspecialchars($emp['area_atuacao'] ?? '') ?>">
            </div>

            <!-- Missão e Visão -->
            <div class="form-group">
                <label class="form-label" for="perfilDescricao">Sobre a Empresa (Missão e Visão)</label>
                <textarea name="descricao" id="perfilDescricao" class="form-control"
                          placeholder="Descreva a missão, visão e valores da sua empresa..."
                          rows="5"><?= htmlspecialchars($emp['descricao'] ?? '') ?></textarea>
            </div>

            <!-- Site + LinkedIn -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="perfilSite">Site Oficial</label>
                    <div class="input-group">
                        <span class="input-icon i i-globe" aria-hidden="true"></span>
                        <input type="url" name="site_oficial" id="perfilSite" class="form-control"
                               placeholder="www.suaempresa.com.br"
                               value="<?= htmlspecialchars($emp['site_oficial'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="perfilLinkedin">LinkedIn</label>
                    <div class="input-group">
                        <span class="input-icon i i-briefcase" aria-hidden="true"></span>
                        <input type="text" name="linkedin" id="perfilLinkedin" class="form-control"
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
                <h2 class="profile-section-title"><span class="i i-briefcase" aria-hidden="true"></span> Vagas Ativas</h2>
                <a href="../Vagas/criar.php" class="btn btn-primary btn-sm"><span class="i i-plus" aria-hidden="true"></span> Criar Vaga</a>
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
                    <a href="../Vagas/candidatos.php?id=<?= intval($v['id']) ?>&amp;voltar=perfil"
                       class="btn btn-ghost btn-sm">
                        Gerenciar
                    </a>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-secondary" style="font-size:14px;">
                    Nenhuma vaga publicada ainda.
                    <a href="../Vagas/criar.php">Criar a primeira vaga <span class="i i-arrow-right" aria-hidden="true"></span></a>
                </p>
            <?php endif; ?>
        </div>


        <!-- SEÇÃO: Configurações de Visibilidade -->
        <div class="profile-section fade-up">
            <div class="profile-section-head">
                <h2 class="profile-section-title"><span class="i i-settings" aria-hidden="true"></span> Configurações de Visibilidade</h2>
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
                           aria-label="Perfil verificado"
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
<div id="modalExcluir" class="jc-modal" role="dialog" aria-modal="true" aria-labelledby="modalExcluirTitulo">
    <div class="card" style="max-width:400px;width:100%;text-align:center;padding:36px 28px;">
        <!-- Fechar pelo × -->
        <button type="button" class="jc-modal-fechar" aria-label="Fechar" onclick="fecharModalExcluir()"><span class="i i-x" aria-hidden="true"></span></button>
        <div class="icone-destaque aviso" aria-hidden="true"><span class="i i-alert-triangle" aria-hidden="true"></span></div>
        <h3 id="modalExcluirTitulo" style="font-weight:700;margin-bottom:10px;">Excluir Conta</h3>
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


<!-- FABs + Assistente + Acessibilidade -->
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
    // Mensagens de erro ficam mais tempo e são anunciadas:
    // o texto volta um instante depois do role — região "viva" que já nasce com texto não é lida
    const ehErro = toast.classList.contains('toast-error');
    const conteudo = [...toast.childNodes];   // Ícone + texto
    toast.replaceChildren();
    toast.setAttribute('role', ehErro ? 'alert' : 'status');
    setTimeout(() => { toast.replaceChildren(...conteudo); toast.classList.add('toast-visible'); }, 100);
    setTimeout(() => toast.classList.remove('toast-visible'), ehErro ? 7000 : 4000);
}

/* ── Toggle sidebar: view ↔ edit ──────────────────────────── */
const btnEditar = document.getElementById('btnEditar');
let   editando  = false;

btnEditar?.addEventListener('click', () => {
    editando = !editando;
    if (!editando) {
        // "Cancelar" descarta o que foi digitado no modo edição —
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

/* ── endereço — CEP primeiro (ViaCEP); número e complemento manuais ── */
// começa com o CEP já preenchido — só focar/sair do campo não
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
        if (status) status.replaceChildren(jcIcone('check-circle'), ' Endereço preenchido. Informe o número e o complemento.');
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

// O foco vai para "Cancelar" ao abrir e volta para "Excluir Conta" ao
// fechar; Esc também fecha (antes só o clique fora ou em "Cancelar")
function abrirModalExcluir()  { modal.style.display = 'flex'; btnCancelar?.focus(); }
function fecharModalExcluir() { modal.style.display = 'none'; btnExcluir?.focus(); }
btnExcluir?.addEventListener('click',  abrirModalExcluir);
btnCancelar?.addEventListener('click', fecharModalExcluir);
modal?.addEventListener('click', e => { if (e.target === modal) fecharModalExcluir(); });
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal?.style.display === 'flex') fecharModalExcluir();
});
</script>

</body>
</html>