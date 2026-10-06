<?php
/* ============================================================
   jovem/cadastro.php

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. session_start() ausente.
   2. include header.php ANTES de config.php — $conn não existe
      quando o POST é processado se algo der errado no header.
   3. SQL Injection na verificação: WHERE email='$email' OR cpf='$cpf'
   4. INSERT definido DUAS VEZES: $sql (nunca executado, só comentado)
      e $stmt (sem escolaridade_status, escolaridade_tipo, escolaridade_curso,
      cep, clt — todos os campos novos do schema v3.00 ignorados).
   5. Nenhuma validação de email, CPF ou confirmação de senha.
   6. <link> CSS no body após output do header.php.
   7. Layout sem seções — não corresponde ao print Cadastro_Jovem.png.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF

// Já logado → redireciona direto
if (isset($_SESSION['jovem_id'])) {
    header("Location: ../dashboard/jovem.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$erros  = [];
$campos = [];

/* ============================================================
   PROCESSAMENTO POST (todo PHP antes de qualquer output HTML)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // ── Leitura e sanitização básica ──────────────────────────────────────────
    $campos = [
        'nome'     => trim($_POST['nome']            ?? ''),
        'email'    => trim($_POST['email']           ?? ''),
        'cpf'      => trim($_POST['cpf']             ?? ''),
        'data_nasc'=> trim($_POST['data_nascimento'] ?? ''),
        'cep'      => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
        'cidade'   => trim($_POST['cidade']          ?? ''),
        'estado'   => strtoupper(trim($_POST['estado'] ?? '')),
        'formacao' => trim($_POST['formacao']        ?? ''),
    ];

    // Enum controlado — evita valores arbitrários no banco
    $esc_status = in_array($_POST['escolaridade_status'] ?? '', ['cursando', 'completo'], true)
                  ? $_POST['escolaridade_status']
                  : 'cursando';

    $esc_tipo   = (($_POST['escolaridade_tipo'] ?? '') === 'tecnico') ? 'tecnico' : 'regular';
    $clt        = isset($_POST['clt']) ? 1 : 0;

    // Stage 3: guarda as escolhas para repopular o formulário quando houver erro
    // (antes os rádios/checkbox de escolaridade voltavam ao padrão)
    $campos['escolaridade_status'] = $esc_status;
    $campos['escolaridade_tipo']   = $esc_tipo;
    $campos['clt']                 = $clt;

    $senha      = $_POST['senha']           ?? '';
    $conf_senha = $_POST['confirmar_senha'] ?? '';

    // Curso técnico (só lido se tipo = técnico)
    $esc_curso = null;
    if ($esc_tipo === 'tecnico') {
        $pred      = trim($_POST['curso_predefinido'] ?? '');
        $esc_curso = ($pred === 'Outro')
                     ? trim($_POST['curso_outro'] ?? '')
                     : $pred;
        if (empty($esc_curso)) $erros[] = 'Informe o nome do curso técnico.';
    }

    // ── Validações ────────────────────────────────────────────────────────────
    if (empty($campos['nome']))
        $erros[] = 'Nome completo é obrigatório.';

    if (empty($campos['email']))
        $erros[] = 'E-mail é obrigatório.';
    elseif (!filter_var($campos['email'], FILTER_VALIDATE_EMAIL))
        $erros[] = 'Formato de e-mail inválido.';
    elseif (mb_strlen($campos['email']) > 100)   // Stage 3: coluna tem 100 caracteres
        $erros[] = 'E-mail muito longo (máximo 100 caracteres).';

    $cpf_num = preg_replace('/\D/', '', $campos['cpf']);
    if (strlen($cpf_num) !== 11)
        $erros[] = 'CPF inválido (deve ter 11 dígitos).';

    if (empty($campos['data_nasc']))
        $erros[] = 'Data de nascimento é obrigatória.';

    if (empty($senha))
        $erros[] = 'Senha é obrigatória.';
    elseif (strlen($senha) < 8)
        $erros[] = 'Senha deve ter pelo menos 8 caracteres.';
    elseif ($senha !== $conf_senha)
        $erros[] = 'As senhas não coincidem.';

    // ── Verificação de duplicidade (prepared statement) ───────────────────────
    if (empty($erros)) {
        $stmt_ck = $conn->prepare(
            "SELECT id FROM jovens WHERE email = ? OR cpf = ? LIMIT 1"
        );
        $stmt_ck->bind_param("ss", $campos['email'], $campos['cpf']);
        $stmt_ck->execute();
        $stmt_ck->store_result();
        if ($stmt_ck->num_rows > 0)
            $erros[] = 'E-mail ou CPF já cadastrado na plataforma.';
        $stmt_ck->close();
    }

    // ── Inserção — um único INSERT com TODOS os campos do schema v3.00 ────────
    if (empty($erros)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        $stmt_ins = $conn->prepare(
            "INSERT INTO jovens
                (nome, email, senha, cpf, data_nascimento,
                 cidade, estado, cep, formacao,
                 escolaridade_status, escolaridade_tipo, escolaridade_curso, clt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        // 12 strings + 1 inteiro = "ssssssssssssi"
        $stmt_ins->bind_param(
            "ssssssssssssi",
            $campos['nome'],   $campos['email'],  $senha_hash,
            $campos['cpf'],    $campos['data_nasc'],
            $campos['cidade'], $campos['estado'],  $campos['cep'],
            $campos['formacao'],
            $esc_status, $esc_tipo, $esc_curso, $clt
        );

        if ($stmt_ins->execute()) {
            // Login automático após cadastro
            $_SESSION['jovem_id']   = $conn->insert_id;
            $_SESSION['jovem_nome'] = $campos['nome'];
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
    <title>Cadastro | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body>

<!-- Barra de progresso -->
<div class="page-progress"><div class="page-progress-fill"></div></div>

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

    <form method="POST" id="formCadastroJovem" novalidate>
        <?= csrf_field() ?>

        <!-- ================================================================
             CARD 1 — Cabeçalho
             ================================================================ -->
        <div class="form-card">
            <div class="form-page-title">
                <span style="font-size:22px;">🎓</span>
                <h2>Cadastro de Jovem</h2>
            </div>
            <p class="form-page-subtitle">
                Crie sua conta para encontrar as melhores vagas de Jovem Aprendiz.
            </p>
        </div>

        <!-- ================================================================
             CARD 2 — Informações Pessoais
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>👤</span> Informações Pessoais
            </div>

            <!-- Nome + CPF -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" class="form-control"
                           placeholder="Seu nome aqui"
                           value="<?= htmlspecialchars($campos['nome'] ?? '') ?>"
                           required autocomplete="name">
                </div>
                <div class="form-group">
                    <label class="form-label" for="cpf">CPF</label>
                    <input type="text" id="cpf" name="cpf" class="form-control"
                           placeholder="000.000.000-00" maxlength="14"
                           value="<?= htmlspecialchars($campos['cpf'] ?? '') ?>"
                           required autocomplete="off">
                </div>
            </div>

            <!-- Data de nascimento + Idade (calculada) -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="dataNasc">Data de Nascimento</label>
                    <div class="input-group">
                        <span class="input-icon">📅</span>
                        <input type="date" id="dataNasc" name="data_nascimento"
                               class="form-control"
                               value="<?= htmlspecialchars($campos['data_nasc'] ?? '') ?>"
                               required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="idadeDisplay">Idade</label>
                    <input type="text" id="idadeDisplay" class="form-control"
                           placeholder="Selecione a data" readonly
                           aria-label="Idade calculada automaticamente"
                           style="color:var(--text-muted);cursor:default;">
                </div>
            </div>

        </div><!-- /.form-card pessoal -->


        <!-- ================================================================
             CARD 3 — Localização
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>📍</span> Localização
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cep">CEP</label>
                    <input type="text" id="cep" name="cep" class="form-control"
                           placeholder="00000-000" maxlength="9"
                           value="<?= htmlspecialchars($campos['cep'] ?? '') ?>"
                           autocomplete="postal-code">
                </div>
                <div class="form-group">
                    <label class="form-label" for="cidadeEstado">Cidade / Estado</label>
                    <div class="input-group">
                        <span class="input-icon">📍</span>
                        <input type="text" id="cidadeEstado" class="form-control"
                               placeholder="Ex: São Paulo - SP"
                               value="<?= htmlspecialchars(
                                   !empty($campos['cidade'])
                                       ? $campos['cidade'] . (!empty($campos['estado']) ? ' - ' . $campos['estado'] : '')
                                       : ''
                               ) ?>">
                    </div>
                    <!-- Campos ocultos — preenchidos pelo JS ao digitar/buscar CEP -->
                    <input type="hidden" name="cidade" id="hidCidade"
                           value="<?= htmlspecialchars($campos['cidade'] ?? '') ?>">
                    <input type="hidden" name="estado" id="hidEstado"
                           value="<?= htmlspecialchars($campos['estado'] ?? '') ?>">
                </div>
            </div>

        </div><!-- /.form-card localização -->


        <!-- ================================================================
             CARD 4 — Escolaridade
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>🎓</span> Escolaridade
            </div>

            <!-- Ano escolar / Conclusão -->
            <div class="form-group">
                <label class="form-label" for="formacao">Ano Escolar / Conclusão</label>
                <input type="text" id="formacao" name="formacao" class="form-control"
                       placeholder="Ex: 3º ano Ensino Médio"
                       value="<?= htmlspecialchars($campos['formacao'] ?? '') ?>">
            </div>

            <!-- Status: cursando ou completo (radios) -->
            <div class="form-group">
                <div class="form-checks-group" style="flex-direction:row;gap:28px;flex-wrap:wrap;">
                    <label class="form-check">
                        <input type="radio" name="escolaridade_status" value="cursando"
                               <?= ($campos['escolaridade_status'] ?? 'cursando') === 'cursando' ? 'checked' : '' ?>>
                        <span class="form-check-label">Cursando</span>
                    </label>
                    <label class="form-check">
                        <input type="radio" name="escolaridade_status" value="completo"
                               <?= ($campos['escolaridade_status'] ?? '') === 'completo' ? 'checked' : '' ?>>
                        <span class="form-check-label">Concluído</span>
                    </label>
                </div>
            </div>

            <!-- Checkboxes adicionais -->
            <div class="form-checks-group" style="margin-top:4px;">
                <label class="form-check">
                    <input type="checkbox" id="chkTecnico"
                           name="escolaridade_tipo" value="tecnico"
                           <?= (($campos['escolaridade_tipo'] ?? '') === 'tecnico') ? 'checked' : '' ?>>
                    <span class="form-check-label">Possuo curso técnico</span>
                </label>
                <label class="form-check">
                    <input type="checkbox" name="clt" value="1"
                           <?= !empty($campos['clt']) ? 'checked' : '' ?>>
                    <span class="form-check-label">Já trabalhei em regime CLT anteriormente</span>
                </label>
            </div>

            <!-- Campo condicional: área e nome do curso técnico -->
            <div id="campoTecnico"
                 style="display:<?= (($campos['escolaridade_tipo'] ?? '') === 'tecnico') ? 'block' : 'none' ?>;
                        margin-top:18px;">

                <div class="form-group">
                    <label class="form-label" for="cursoPredefinido">Área do Curso Técnico</label>
                    <select id="cursoPredefinido" name="curso_predefinido" class="form-control">
                        <option value="Administração">Administração</option>
                        <option value="TI">Tecnologia da Informação (TI)</option>
                        <option value="Logística">Logística</option>
                        <option value="Enfermagem">Enfermagem</option>
                        <option value="Mecânica">Mecânica</option>
                        <option value="Contabilidade">Contabilidade</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Outro">Outro (especificar)</option>
                    </select>
                </div>

                <div class="form-group" id="campoCursoOutro" style="display:none;">
                    <label class="form-label" for="cursoOutro">Nome do Curso</label>
                    <input type="text" id="cursoOutro" name="curso_outro"
                           class="form-control" placeholder="Digite o nome do curso">
                </div>

            </div><!-- /#campoTecnico -->

        </div><!-- /.form-card escolaridade -->


        <!-- ================================================================
             CARD 5 — Segurança (credenciais de login)
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>🔒</span> Segurança
            </div>

            <!-- E-mail -->
            <div class="form-group">
                <label class="form-label" for="email">E-mail</label>
                <div class="input-group">
                    <span class="input-icon">✉</span>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="seu@email.com"
                           value="<?= htmlspecialchars($campos['email'] ?? '') ?>"
                           required autocomplete="email">
                </div>
            </div>

            <!-- Senha + Confirmar -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="senha">Senha</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="senha" name="senha"
                               class="form-control" placeholder="••••••••"
                               minlength="8" required autocomplete="new-password">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirmarSenha">Confirmar Senha</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="confirmarSenha" name="confirmar_senha"
                               class="form-control" placeholder="••••••••"
                               minlength="8" required autocomplete="new-password">
                    </div>
                </div>
            </div>

        </div><!-- /.form-card segurança -->


        <!-- ================================================================
             CARD 6 — Submit
             ================================================================ -->
        <div class="form-submit-card">
            <button type="submit" class="btn btn-primary btn-full btn-lg">
                Cadastrar Agora
            </button>
            <p style="text-align:center;font-size:13px;color:var(--text-secondary);">
                Já tem uma conta?
                <a href="login.php" class="font-semibold">Faça login</a>
            </p>
        </div>

    </form>

</div><!-- /.form-container -->
</div><!-- /.form-page -->

<!-- FAB -->
<div class="fab-stack">
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Acessibilidade">♿</button>
</div>


<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script>
/* ── Tema ───────────────────────────────────────────────────── */
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

/* ── Máscara CPF ────────────────────────────────────────────── */
document.getElementById('cpf')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 11);
    v = v.replace(/(\d{3})(\d)/,           '$1.$2');
    v = v.replace(/(\d{3})\.(\d{3})(\d)/,  '$1.$2.$3');
    v = v.replace(/\.(\d{3})\.(\d{3})(\d)/, '.$1.$2-$3');
    this.value = v;
});

/* ── Máscara CEP ────────────────────────────────────────────── */
document.getElementById('cep')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 8);
    if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
    this.value = v;
});

/* ── Busca CEP via ViaCEP ───────────────────────────────────── */
document.getElementById('cep')?.addEventListener('blur', async function () {
    const raw = this.value.replace(/\D/g, '');
    if (raw.length !== 8) return;
    try {
        const res  = await fetch(`https://viacep.com.br/ws/${raw}/json/`);
        const data = await res.json();
        if (data.erro) return;
        const cidEl = document.getElementById('cidadeEstado');
        if (cidEl) cidEl.value = `${data.localidade} - ${data.uf}`;
        document.getElementById('hidCidade').value = data.localidade || '';
        document.getElementById('hidEstado').value  = data.uf         || '';
    } catch {
        // falha silenciosa — o usuário preenche manualmente
    }
});

/* ── Cálculo automático de idade ────────────────────────────── */
document.getElementById('dataNasc')?.addEventListener('change', function () {
    const nasc = new Date(this.value);
    if (isNaN(nasc)) return;
    const hoje = new Date();
    let   idade = hoje.getFullYear() - nasc.getFullYear();
    const m     = hoje.getMonth() - nasc.getMonth();
    if (m < 0 || (m === 0 && hoje.getDate() < nasc.getDate())) idade--;
    const el = document.getElementById('idadeDisplay');
    if (el) el.value = idade > 0 ? `${idade} anos` : '';
});

/* ── Toggle campo técnico ───────────────────────────────────── */
document.getElementById('chkTecnico')?.addEventListener('change', function () {
    const campo = document.getElementById('campoTecnico');
    if (campo) campo.style.display = this.checked ? 'block' : 'none';
});

document.getElementById('cursoPredefinido')?.addEventListener('change', function () {
    const campo = document.getElementById('campoCursoOutro');
    if (campo) campo.style.display = this.value === 'Outro' ? 'block' : 'none';
});

/* ── Extrai cidade/estado e valida antes do submit ──────────── */
document.getElementById('formCadastroJovem')?.addEventListener('submit', function (e) {

    // Divide "São Paulo - SP" → cidade = "São Paulo", estado = "SP"
    const val    = document.getElementById('cidadeEstado')?.value.trim() ?? '';
    const partes = val.split('-').map(s => s.trim());
    if (partes.length >= 2) {
        document.getElementById('hidCidade').value = partes.slice(0, -1).join('-').trim();
        document.getElementById('hidEstado').value  = partes[partes.length - 1].toUpperCase();
    } else {
        document.getElementById('hidCidade').value = val;
    }

    // Validação client-side de senhas
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