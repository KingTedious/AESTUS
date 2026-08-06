<?php
/* ============================================================
   empresa/cadastro.php
   ============================================================ */
session_start();

// Já logado → redireciona direto
if (isset($_SESSION['empresa_id'])) {
    header("Location: ../dashboard/empresa.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$erros  = [];
$campos = []; // guarda os valores para repopular o form em caso de erro

/* ============================================================
   PROCESSAMENTO POST
   Todo PHP antes de qualquer output HTML.
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Leitura e sanitização básica ──────────────────────────────────────────
    $campos = [
        'nome_empresa' => trim($_POST['nome_empresa'] ?? ''),
        'email'        => trim($_POST['email']        ?? ''),
        'cnpj'         => trim($_POST['cnpj']         ?? ''),
        'area_atuacao' => trim($_POST['area_atuacao'] ?? ''),
        'cidade'       => trim($_POST['cidade']       ?? ''),
        'estado'       => strtoupper(trim($_POST['estado'] ?? '')),
    ];
    $senha      = $_POST['senha']           ?? '';
    $conf_senha = $_POST['confirmar_senha'] ?? '';

    // ── Validações ────────────────────────────────────────────────────────────
    if (empty($campos['nome_empresa']))
        $erros[] = 'Nome da empresa é obrigatório.';

    if (empty($campos['email']))
        $erros[] = 'E-mail corporativo é obrigatório.';
    elseif (!filter_var($campos['email'], FILTER_VALIDATE_EMAIL))
        $erros[] = 'Formato de e-mail inválido.';

    $cnpj_numeros = preg_replace('/\D/', '', $campos['cnpj']);
    if (strlen($cnpj_numeros) !== 14)
        $erros[] = 'CNPJ inválido (deve ter 14 dígitos).';

    if (empty($senha))
        $erros[] = 'Senha é obrigatória.';
    elseif (strlen($senha) < 8)
        $erros[] = 'Senha deve ter pelo menos 8 caracteres.';
    elseif ($senha !== $conf_senha)
        $erros[] = 'As senhas não coincidem.';

    // ── Verificação de duplicidade (prepared statement) ───────────────────────
    if (empty($erros)) {
        $stmt_check = $conn->prepare(
            "SELECT id FROM empresas WHERE email = ? OR cnpj = ? LIMIT 1"
        );
        $stmt_check->bind_param("ss", $campos['email'], $campos['cnpj']);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0)
            $erros[] = 'E-mail ou CNPJ já cadastrado na plataforma.';

        $stmt_check->close();
    }

    // ── Inserção (prepared statement) ─────────────────────────────────────────
    if (empty($erros)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        $stmt_ins = $conn->prepare(
            "INSERT INTO empresas
                (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_ins->bind_param(
            "sssssss",
            $campos['nome_empresa'],
            $campos['email'],
            $senha_hash,
            $campos['cnpj'],
            $campos['cidade'],
            $campos['estado'],
            $campos['area_atuacao']
        );

        if ($stmt_ins->execute()) {
            // Login automático após cadastro bem-sucedido
            $_SESSION['empresa_id']   = $conn->insert_id;
            $_SESSION['empresa_nome'] = $campos['nome_empresa'];
            $stmt_ins->close();

            header("Location: ../dashboard/empresa.php");
            exit;
        }

        $erros[] = 'Erro interno ao cadastrar. Tente novamente.';
        $stmt_ins->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Empresa | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body>

<!-- Barra de progresso (topo) -->
<div class="page-progress">
    <div class="page-progress-fill" style="width:100%;"></div>
</div>

<div class="form-page">
<div class="form-container">

    <!-- ── Logo ────────────────────────────────────────────────────────────── -->
    <div class="form-page-logo">
        <div class="logo-inner">
            <span class="logo-box">💼</span>
            <span class="logo-jovem">JovemAprendiz</span>
            <span class="logo-connect"> Conecta</span>
        </div>
    </div>

    <!-- ── Alertas de erro ─────────────────────────────────────────────────── -->
    <?php if (!empty($erros)): ?>
    <div class="form-card" style="border-color:rgba(239,68,68,.4);margin-bottom:0;">
        <?php foreach ($erros as $erro): ?>
        <p class="form-error">⚠ <?= htmlspecialchars($erro) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="formEmpresa" novalidate>

        <!-- ================================================================
             CARD 1 — Cabeçalho do formulário
             ================================================================ -->
        <div class="form-card">
            <div class="form-page-title">
                <span style="font-size:22px;">🏢</span>
                <h2>Cadastro de Empresa</h2>
            </div>
            <p class="form-page-subtitle">
                Registre sua empresa para anunciar vagas e contratar jovens talentos.
            </p>
        </div>

        <!-- ================================================================
             CARD 2 — Dados Empresariais
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>🏢</span> Dados Empresariais
            </div>

            <!-- CNPJ com botão de consulta automática -->
            <div class="form-group">
                <label class="form-label" for="cnpj">CNPJ</label>
                <div class="input-row">
                    <input
                        type="text"
                        id="cnpj"
                        name="cnpj"
                        class="form-control"
                        placeholder="00.000.000/0000-00"
                        maxlength="18"
                        value="<?= htmlspecialchars($campos['cnpj'] ?? '') ?>"
                        autocomplete="off"
                        required>
                    <button
                        type="button"
                        class="btn-icon"
                        id="btnBuscarCnpj"
                        title="Consultar CNPJ automaticamente"
                        aria-label="Buscar CNPJ">
                        🔍
                    </button>
                </div>
            </div>

            <!-- Nome da Empresa -->
            <div class="form-group">
                <label class="form-label" for="nomeEmpresa">Nome da Empresa (Razão Social)</label>
                <input
                    type="text"
                    id="nomeEmpresa"
                    name="nome_empresa"
                    class="form-control"
                    placeholder="Empresa LTDA"
                    value="<?= htmlspecialchars($campos['nome_empresa'] ?? '') ?>"
                    required>
            </div>

            <!-- Área de Atuação + Cidade/Estado (grid 2 colunas) -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="areaAtuacao">Área de Atuação</label>
                    <input
                        type="text"
                        id="areaAtuacao"
                        name="area_atuacao"
                        class="form-control"
                        placeholder="Ex: Tecnologia, Varejo"
                        value="<?= htmlspecialchars($campos['area_atuacao'] ?? '') ?>">
                </div>

                <!-- Campo visual combinado "Cidade - UF", separado via JS no submit -->
                <div class="form-group">
                    <label class="form-label" for="cidadeEstado">Cidade / Estado</label>
                    <div class="input-group">
                        <span class="input-icon">📍</span>
                        <input
                            type="text"
                            id="cidadeEstado"
                            class="form-control"
                            placeholder="São Paulo - SP"
                            value="<?= htmlspecialchars(
                                !empty($campos['cidade'])
                                    ? $campos['cidade'] . (!empty($campos['estado']) ? ' - ' . $campos['estado'] : '')
                                    : ''
                            ) ?>">
                    </div>
                    <!-- Campos hidden preenchidos pelo JS antes do submit -->
                    <input type="hidden" name="cidade" id="hidCidade" value="<?= htmlspecialchars($campos['cidade'] ?? '') ?>">
                    <input type="hidden" name="estado" id="hidEstado" value="<?= htmlspecialchars($campos['estado'] ?? '') ?>">
                </div>
            </div>

        </div><!-- /.form-card dados empresariais -->

        <!-- ================================================================
             CARD 3 — Acesso
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>🔒</span> Acesso
            </div>

            <!-- E-mail -->
            <div class="form-group">
                <label class="form-label" for="email">E-mail Corporativo</label>
                <div class="input-group">
                    <span class="input-icon">✉</span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="rh@empresa.com.br"
                        value="<?= htmlspecialchars($campos['email'] ?? '') ?>"
                        required>
                </div>
            </div>

            <!-- Senha + Confirmar Senha (grid 2 colunas) -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="senha">Senha</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input
                            type="password"
                            id="senha"
                            name="senha"
                            class="form-control"
                            placeholder="••••••••"
                            minlength="8"
                            required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirmarSenha">Confirmar Senha</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input
                            type="password"
                            id="confirmarSenha"
                            name="confirmar_senha"
                            class="form-control"
                            placeholder="••••••••"
                            minlength="8"
                            required>
                    </div>
                </div>
            </div>

        </div><!-- /.form-card acesso -->

        <!-- ================================================================
             CARD 4 — Submit
             ================================================================ -->
        <div class="form-submit-card">
            <button type="submit" class="btn btn-primary btn-full btn-lg">
                Registrar Empresa
            </button>
            <p style="text-align:center;font-size:13px;color:var(--text-secondary);">
                Sua empresa já é parceira?
                <a href="login.php" class="font-semibold">Faça login</a>
            </p>
        </div>

    </form>

</div><!-- /.form-container -->
</div><!-- /.form-page -->

<!-- FAB acessibilidade -->
<div class="fab-stack">
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Abrir acessibilidade">♿</button>
</div>
<div class="user-indicator" title="Início">N</div>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script>
/* ── Aplica tema salvo ─────────────────────────────────────── */
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

/* ── Máscara de CNPJ ───────────────────────────────────────── */
document.getElementById('cnpj')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/,           '$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/,  '$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/,          '.$1/$2');
    v = v.replace(/(\d{4})(\d)/,            '$1-$2');
    this.value = v;
});

/* ── Busca CNPJ via BrasilAPI ──────────────────────────────── */
document.getElementById('btnBuscarCnpj')?.addEventListener('click', async function () {
    const cnpjRaw = document.getElementById('cnpj').value.replace(/\D/g, '');

    if (cnpjRaw.length !== 14) {
        alert('Digite um CNPJ completo (14 dígitos) antes de buscar.');
        return;
    }

    this.textContent = '⏳';
    this.disabled    = true;

    try {
        const res = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpjRaw}`);

        if (!res.ok) throw new Error('CNPJ não encontrado ou inválido.');

        const data = await res.json();

        // Preenche campos automaticamente com os dados retornados
        const nomeEl        = document.getElementById('nomeEmpresa');
        const cidadeEstadoEl = document.getElementById('cidadeEstado');

        if (nomeEl && data.razao_social)
            nomeEl.value = data.razao_social;

        if (cidadeEstadoEl && data.municipio && data.uf)
            cidadeEstadoEl.value = data.municipio + ' - ' + data.uf;

    } catch (err) {
        alert('Não foi possível buscar o CNPJ:\n' + err.message);
    } finally {
        this.textContent = '🔍';
        this.disabled    = false;
    }
});

/* ── Extrai cidade e estado do campo combinado antes do submit ─
   O campo "cidadeEstado" é só visual. O PHP lê os hidden fields. */
document.getElementById('formEmpresa')?.addEventListener('submit', function (e) {
    const val    = document.getElementById('cidadeEstado')?.value.trim() ?? '';
    const partes = val.split('-').map(s => s.trim());

    if (partes.length >= 2) {
        const uf     = partes[partes.length - 1];
        const cidade = partes.slice(0, -1).join('-').trim();
        document.getElementById('hidCidade').value = cidade;
        document.getElementById('hidEstado').value = uf.toUpperCase();
    } else {
        document.getElementById('hidCidade').value = val;
        document.getElementById('hidEstado').value  = '';
    }

    // Validação client-side: senhas coincidem?
    const s1 = document.getElementById('senha')?.value;
    const s2 = document.getElementById('confirmarSenha')?.value;
    if (s1 !== s2) {
        e.preventDefault();
        alert('As senhas não coincidem. Verifique e tente novamente.');
    }
});
</script>

</body>
</html>