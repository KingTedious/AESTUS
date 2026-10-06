<?php
/* ============================================================
   empresa/cadastro.php
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF

// Já logado → redireciona direto
if (isset($_SESSION['empresa_id'])) {
    header("Location: ../dashboard/empresa.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/endereco_empresa.php";   // Stage 3: endereço completo

$erros  = [];
$campos = []; // guarda os valores para repopular o form em caso de erro
$tem_endereco = empresa_tem_endereco($conn);   // Stage 3: colunas da migração aditiva já existem?

/* ============================================================
   PROCESSAMENTO POST
   Todo PHP antes de qualquer output HTML.
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // ── Leitura e sanitização básica ──────────────────────────────────────────
    // Stage 3: limites = tamanho das colunas + endereço completo (CEP primeiro)
    $campos = [
        'nome_empresa' => mb_substr(trim($_POST['nome_empresa'] ?? ''), 0, 150),
        'email'        => trim($_POST['email']        ?? ''),   // validado abaixo (não cortado)
        'cnpj'         => trim($_POST['cnpj']         ?? ''),
        'area_atuacao' => mb_substr(trim($_POST['area_atuacao'] ?? ''), 0, 100),
        'cidade'       => mb_substr(trim($_POST['cidade']       ?? ''), 0, 100),
        'estado'       => mb_substr(strtoupper(trim($_POST['estado'] ?? '')), 0, 2),
    ] + endereco_empresa_do_post();
    $senha      = $_POST['senha']           ?? '';
    $conf_senha = $_POST['confirmar_senha'] ?? '';

    // ── Validações ────────────────────────────────────────────────────────────
    if (empty($campos['nome_empresa']))
        $erros[] = 'Nome da empresa é obrigatório.';

    if (empty($campos['email']))
        $erros[] = 'E-mail corporativo é obrigatório.';
    elseif (!filter_var($campos['email'], FILTER_VALIDATE_EMAIL))
        $erros[] = 'Formato de e-mail inválido.';
    elseif (mb_strlen($campos['email']) > 100)
        $erros[] = 'E-mail muito longo (máximo 100 caracteres).';

    $cnpj_numeros = preg_replace('/\D/', '', $campos['cnpj']);
    if (strlen($cnpj_numeros) !== 14)
        $erros[] = 'CNPJ inválido (deve ter 14 dígitos).';
    else
        $campos['cnpj'] = cnpj_formatado($campos['cnpj']);   // Stage 3: formato único 00.000.000/0000-00

    if ($campos['cep'] !== '' && strlen($campos['cep']) !== 8)
        $erros[] = 'CEP inválido (deve ter 8 dígitos).';

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

        if (empresa_tem_endereco($conn)) {
            // Stage 3: endereço completo (colunas da migração aditiva)
            $stmt_ins = $conn->prepare(
                "INSERT INTO empresas
                    (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao,
                     cep, logradouro, numero, complemento, bairro)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt_ins->bind_param(
                "ssssssssssss",
                $campos['nome_empresa'], $campos['email'], $senha_hash, $campos['cnpj'],
                $campos['cidade'], $campos['estado'], $campos['area_atuacao'],
                $campos['cep'], $campos['logradouro'], $campos['numero'],
                $campos['complemento'], $campos['bairro']
            );
        } else {
            // Migração ainda não aplicada: mesmo INSERT de antes (só cidade/UF)
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
        }

        if ($stmt_ins->execute()) {
            // Login automático após cadastro bem-sucedido
            $_SESSION['empresa_id']   = $conn->insert_id;
            $_SESSION['empresa_nome'] = $campos['nome_empresa'];
            jc_session_regenerate(); // MED-001
            $stmt_ins->close();

            // Etapa 2: antes de ir ao dashboard, passa pela verificação de conta.
            header("Location: ../verificacoes/escolher_verificacao.php");
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

    <!-- Stage 3: volta para a escolha Jovem/Empresa -->
    <p style="margin:0 0 12px;"><a href="../comecar.php" class="back-link">← Voltar</a></p>

    <!-- ── Alertas de erro ─────────────────────────────────────────────────── -->
    <?php if (!empty($erros)): ?>
    <div class="form-card" style="border-color:rgba(239,68,68,.4);margin-bottom:0;">
        <?php foreach ($erros as $erro): ?>
        <p class="form-error">⚠ <?= htmlspecialchars($erro) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="formEmpresa" novalidate>
        <?= csrf_field() ?>

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

            <!-- Área de Atuação (Stage 3: cidade/UF foram para o card Endereço) -->
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

            </div>

        </div><!-- /.form-card dados empresariais -->

        <!-- ================================================================
             CARD — Endereço (Stage 3)
             Digite o CEP primeiro: rua, bairro, cidade e UF são preenchidos
             automaticamente (ViaCEP); número e complemento ficam manuais.
             Antes só existia o campo combinado "Cidade / Estado".
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>📍</span> Endereço
            </div>

            <?php if ($tem_endereco): ?>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cep">CEP</label>
                    <input type="text" id="cep" name="cep" class="form-control"
                           placeholder="00000-000" maxlength="9" inputmode="numeric"
                           autocomplete="postal-code"
                           value="<?= htmlspecialchars($campos['cep'] ?? '') ?>">
                    <small id="cepStatus" class="text-muted" style="font-size:12px;" aria-live="polite"></small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="logradouro">Rua / Logradouro</label>
                    <input type="text" id="logradouro" name="logradouro" class="form-control"
                           maxlength="150" autocomplete="address-line1"
                           value="<?= htmlspecialchars($campos['logradouro'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="numero">Número</label>
                    <input type="text" id="numero" name="numero" class="form-control"
                           maxlength="10" placeholder="Ex: 123 ou S/N"
                           value="<?= htmlspecialchars($campos['numero'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="complemento">Complemento</label>
                    <input type="text" id="complemento" name="complemento" class="form-control"
                           maxlength="100" placeholder="Ex: Sala 4, Bloco B"
                           value="<?= htmlspecialchars($campos['complemento'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="bairro">Bairro</label>
                <input type="text" id="bairro" name="bairro" class="form-control" maxlength="100"
                       value="<?= htmlspecialchars($campos['bairro'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cidade">Cidade</label>
                    <input type="text" id="cidade" name="cidade" class="form-control" maxlength="100"
                           autocomplete="address-level2"
                           value="<?= htmlspecialchars($campos['cidade'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="estado">UF</label>
                    <input type="text" id="estado" name="estado" class="form-control" maxlength="2"
                           placeholder="SP" autocomplete="address-level1"
                           value="<?= htmlspecialchars($campos['estado'] ?? '') ?>">
                </div>
            </div>
        </div><!-- /.form-card endereço -->

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
        const nomeEl = document.getElementById('nomeEmpresa');
        if (nomeEl && data.razao_social)
            nomeEl.value = data.razao_social;

        // Cidade/UF do CNPJ (como antes), só se ainda estiverem vazios
        const cidEl = document.getElementById('cidade');
        const ufEl  = document.getElementById('estado');
        if (cidEl && !cidEl.value.trim() && data.municipio) cidEl.value = data.municipio;
        if (ufEl  && !ufEl.value.trim()  && data.uf)        ufEl.value  = data.uf;

        // Stage 3: usa o CEP do CNPJ para preencher o endereço pelo mesmo fluxo do campo CEP
        const cepCnpj = document.getElementById('cep');
        if (cepCnpj && !cepCnpj.value.trim() && data.cep) {
            cepCnpj.value = String(data.cep).replace(/\D/g, '').replace(/^(\d{5})(\d{3})$/, '$1-$2');
            buscarCep();
        }

    } catch (err) {
        alert('Não foi possível buscar o CNPJ:\n' + err.message);
    } finally {
        this.textContent = '🔍';
        this.disabled    = false;
    }
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

document.getElementById('formEmpresa')?.addEventListener('submit', function (e) {
    // Stage 3: cidade/UF agora são campos próprios (preenchidos pelo CEP) —
    // não existe mais o campo combinado "Cidade - UF" para separar aqui.

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