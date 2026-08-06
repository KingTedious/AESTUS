<?php
/* ============================================================
   Vagas/criar.php — Publicação de nova vaga

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. session_start() depois dos includes.
   2. real_escape_string() em vez de prepared statements
      → SQL Injection em todos os campos POST.
   3. $conn->error exposto ao usuário.
   4. Sem PRG: F5 após sucesso republicava a vaga.
   5. Campos v3.00 ausentes: carga_horaria, area, cep.
   6. Sem inserção em requisitos_vaga e beneficios_vaga.
   7. var(--principal) e var(--borda) não existem no CSS v3.00.
   8. include header.php após queries e <link> no body.
   9. include footer.php em página com HTML próprio.
   ============================================================ */
session_start();

if (!isset($_SESSION['empresa_id'])) {
    header("Location: ../empresa/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$empresa_id = intval($_SESSION['empresa_id']);

/* ============================================================
   PROCESSAMENTO POST
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Leitura e sanitização
    $titulo         = trim($_POST['titulo']         ?? '');
    $descricao      = trim($_POST['descricao']      ?? '');
    $cidade         = trim($_POST['cidade']         ?? '');
    $estado         = strtoupper(trim($_POST['estado'] ?? ''));
    $cep            = preg_replace('/\D/', '', $_POST['cep'] ?? '');
    $salario        = trim($_POST['salario']        ?? '');
    $carga_horaria  = trim($_POST['carga_horaria']  ?? '');
    $area           = trim($_POST['area']           ?? '');
    $tipo_contrato  = trim($_POST['tipo_contrato']  ?? '');
    $requisitos_raw = trim($_POST['requisitos']      ?? '');
    $beneficios_raw = trim($_POST['beneficios']      ?? '');

    // Validação mínima — campos obrigatórios
    if (empty($titulo) || empty($descricao) || empty($tipo_contrato)) {
        header("Location: criar.php?error=missing");
        exit;
    }

    // Formata CEP apenas se preenchido (00000-000)
    $cep_fmt = strlen($cep) === 8
        ? substr($cep, 0, 5) . '-' . substr($cep, 5)
        : null;

    // ── Insere a vaga ─────────────────────────────────────────────────────────
    $stmt = $conn->prepare(
        "INSERT INTO vagas
             (empresa_id, titulo, descricao, cidade, estado, cep,
              salario, carga_horaria, area, tipo_contrato)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "isssssssss",
        $empresa_id, $titulo, $descricao, $cidade, $estado, $cep_fmt,
        $salario, $carga_horaria, $area, $tipo_contrato
    );

    if (!$stmt->execute()) {
        error_log("[JovemConnect] Erro ao criar vaga: " . $conn->error);
        $stmt->close();
        header("Location: criar.php?error=db");
        exit;
    }

    $vaga_id = intval($conn->insert_id);
    $stmt->close();

    // ── Insere requisitos (tabela requisitos_vaga) ────────────────────────────
    if (!empty($requisitos_raw)) {
        $requisitos = array_filter(
            array_map('trim', explode(',', $requisitos_raw))
        );
        if (!empty($requisitos)) {
            $stmt_req = $conn->prepare(
                "INSERT INTO requisitos_vaga (vaga_id, requisito) VALUES (?, ?)"
            );
            foreach ($requisitos as $req) {
                if (strlen($req) > 0 && strlen($req) <= 150) {
                    $stmt_req->bind_param("is", $vaga_id, $req);
                    $stmt_req->execute();
                }
            }
            $stmt_req->close();
        }
    }

    // ── Insere benefícios (tabela beneficios_vaga) ────────────────────────────
    if (!empty($beneficios_raw)) {
        $beneficios = array_filter(
            array_map('trim', explode(',', $beneficios_raw))
        );
        if (!empty($beneficios)) {
            $stmt_ben = $conn->prepare(
                "INSERT INTO beneficios_vaga (vaga_id, beneficio) VALUES (?, ?)"
            );
            foreach ($beneficios as $ben) {
                if (strlen($ben) > 0 && strlen($ben) <= 100) {
                    $stmt_ben->bind_param("is", $vaga_id, $ben);
                    $stmt_ben->execute();
                }
            }
            $stmt_ben->close();
        }
    }

    // PRG → redireciona para o perfil da empresa com feedback
    header("Location: ../empresa/perfil.php?saved=1");
    exit;
}

// Lê o código de erro do redirect (se houver)
$error = $_GET['error'] ?? null;
$erros_msg = [
    'missing' => 'Preencha os campos obrigatórios: Título, Descrição e Tipo de Contrato.',
    'db'      => 'Erro interno ao salvar a vaga. Tente novamente.',
];
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Nova Vaga | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body>

<!-- Barra de progresso -->
<div class="page-progress"><div class="page-progress-fill"></div></div>

<div class="form-page">
<div class="form-container">

    <!-- ── Logo ──────────────────────────────────────────────────────────────── -->
    <div class="form-page-logo">
        <div class="logo-inner">
            <span class="logo-box">💼</span>
            <span class="logo-jovem">Jovem</span><span class="logo-connect">Connect</span>
        </div>
    </div>

    <!-- ── Erro (GET redirect) ────────────────────────────────────────────────── -->
    <?php if ($error && isset($erros_msg[$error])): ?>
    <div class="form-card" style="border-color:rgba(239,68,68,.4);margin-bottom:0;">
        <p class="form-error">⚠ <?= htmlspecialchars($erros_msg[$error]) ?></p>
    </div>
    <?php endif; ?>

    <form method="POST" id="formVaga" novalidate>

        <!-- ================================================================
             CARD 1 — Cabeçalho
             ================================================================ -->
        <div class="form-card">
            <div class="form-page-title">
                <span style="font-size:22px;">📋</span>
                <h2>Criar Nova Vaga</h2>
            </div>
            <p class="form-page-subtitle">
                Preencha os detalhes da oportunidade. A IA usará esses dados
                para calcular a compatibilidade com os jovens.
            </p>
        </div>

        <!-- ================================================================
             CARD 2 — Informações Básicas
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>📌</span> Informações Básicas
            </div>

            <!-- Título -->
            <div class="form-group">
                <label class="form-label" for="titulo">Título da Vaga *</label>
                <input
                    type="text" id="titulo" name="titulo"
                    class="form-control"
                    placeholder="Ex: Auxiliar Administrativo, Desenvolvedor Júnior"
                    required>
            </div>

            <!-- Tipo de contrato + Área (grid) -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="tipo_contrato">Tipo de Contrato *</label>
                    <select id="tipo_contrato" name="tipo_contrato"
                            class="form-control" required>
                        <option value="">Selecione...</option>
                        <option value="Jovem Aprendiz">Jovem Aprendiz</option>
                        <option value="Estágio">Estágio</option>
                        <option value="CLT">CLT (Efetivo)</option>
                        <option value="PJ">PJ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="area">Área de Atuação</label>
                    <select id="area" name="area" class="form-control">
                        <option value="">Selecione uma área...</option>
                        <option value="Administrativo">Administrativo</option>
                        <option value="TI">TI</option>
                        <option value="Vendas">Vendas</option>
                        <option value="Logística">Logística</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Recursos Humanos">Recursos Humanos</option>
                        <option value="Financeiro">Financeiro</option>
                        <option value="Saúde">Saúde</option>
                        <option value="Educação">Educação</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
            </div>

        </div><!-- /.form-card básico -->

        <!-- ================================================================
             CARD 3 — Localização
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>📍</span> Localização
            </div>

            <!-- CEP com busca automática -->
            <div class="form-group">
                <label class="form-label" for="cep">CEP</label>
                <div class="input-row">
                    <input
                        type="text" id="cep" name="cep"
                        class="form-control"
                        placeholder="00000-000" maxlength="9">
                    <button type="button" class="btn-icon" id="btnBuscarCep"
                            title="Buscar CEP" aria-label="Buscar CEP">🔍</button>
                </div>
            </div>

            <!-- Cidade + Estado -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cidade">Cidade</label>
                    <input
                        type="text" id="cidade" name="cidade"
                        class="form-control"
                        placeholder="Ex: São Paulo">
                </div>
                <div class="form-group">
                    <label class="form-label" for="estado">UF</label>
                    <input
                        type="text" id="estado" name="estado"
                        class="form-control"
                        placeholder="SP" maxlength="2">
                </div>
            </div>

        </div><!-- /.form-card localização -->

        <!-- ================================================================
             CARD 4 — Remuneração e Carga Horária
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>💰</span> Remuneração
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="salario">Salário (Opcional)</label>
                    <div class="input-group">
                        <span class="input-icon">R$</span>
                        <input
                            type="text" id="salario" name="salario"
                            class="form-control"
                            placeholder="Ex: 1200.00 ou A combinar"
                            style="padding-left:44px;">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="carga_horaria">Carga Horária</label>
                    <select id="carga_horaria" name="carga_horaria" class="form-control">
                        <option value="">Selecione...</option>
                        <option value="20h/semana">20h/semana (Jovem Aprendiz padrão)</option>
                        <option value="25h/semana">25h/semana</option>
                        <option value="30h/semana">30h/semana</option>
                        <option value="40h/semana">40h/semana</option>
                        <option value="A combinar">A combinar</option>
                    </select>
                </div>
            </div>

        </div><!-- /.form-card remuneração -->

        <!-- ================================================================
             CARD 5 — Descrição da Vaga
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>📝</span> Descrição da Vaga
            </div>

            <div class="form-group">
                <label class="form-label" for="descricao">
                    Descrição Detalhada *
                    <span class="text-muted" style="font-weight:400;font-size:11px;">
                        (A IA usa o texto desta descrição no cálculo de match)
                    </span>
                </label>
                <textarea
                    id="descricao" name="descricao"
                    class="form-control"
                    rows="6"
                    placeholder="Descreva as responsabilidades, o ambiente de trabalho e o que o candidato vai aprender..."
                    required></textarea>
            </div>

        </div><!-- /.form-card descrição -->

        <!-- ================================================================
             CARD 6 — Requisitos e Benefícios
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span>✓</span> Requisitos e Benefícios
            </div>

            <!-- Requisitos -->
            <div class="form-group">
                <label class="form-label" for="requisitos">
                    Requisitos
                    <span class="text-muted" style="font-weight:400;font-size:11px;">
                        (separe por vírgula)
                    </span>
                </label>
                <input
                    type="text" id="requisitos" name="requisitos"
                    class="form-control"
                    placeholder="Ex: Ensino Médio em curso, Excel básico, Boa comunicação">
                <!-- Preview de chips -->
                <div id="reqChips" class="skills-wrap" style="margin-top:10px;min-height:0;"></div>
            </div>

            <!-- Benefícios -->
            <div class="form-group">
                <label class="form-label" for="beneficios">
                    Benefícios
                    <span class="text-muted" style="font-weight:400;font-size:11px;">
                        (separe por vírgula)
                    </span>
                </label>
                <input
                    type="text" id="beneficios" name="beneficios"
                    class="form-control"
                    placeholder="Ex: Vale Transporte, Vale Refeição, Curso de Capacitação">
                <div id="benChips" class="skills-wrap" style="margin-top:10px;min-height:0;"></div>
            </div>

        </div><!-- /.form-card requisitos -->

        <!-- ================================================================
             CARD 7 — Submit
             ================================================================ -->
        <div class="form-submit-card">
            <button type="submit" class="btn btn-primary btn-full btn-lg">
                📋 Publicar Vaga Agora
            </button>
            <p style="text-align:center;font-size:13px;color:var(--text-secondary);">
                <a href="../empresa/perfil.php">← Voltar ao Painel</a>
            </p>
        </div>

    </form>

</div><!-- /.form-container -->
</div><!-- /.form-page -->

<!-- FABs -->
<div class="fab-stack">
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Acessibilidade">♿</button>
</div>
<div class="user-indicator">
    <?= strtoupper(mb_substr($_SESSION['empresa_nome'] ?? 'E', 0, 1)) ?>
</div>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script>
/* ── Tema ──────────────────────────────────────────────────── */
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

/* ── Máscara de CEP ────────────────────────────────────────── */
document.getElementById('cep')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 8);
    if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
    this.value = v;
});

/* ── Busca CEP via ViaCEP ──────────────────────────────────── */
document.getElementById('btnBuscarCep')?.addEventListener('click', async function () {
    const cepRaw = document.getElementById('cep').value.replace(/\D/g, '');
    if (cepRaw.length !== 8) {
        alert('Digite um CEP completo (8 dígitos) antes de buscar.');
        return;
    }

    this.textContent = '⏳';
    this.disabled    = true;

    try {
        const res  = await fetch(`https://viacep.com.br/ws/${cepRaw}/json/`);
        const data = await res.json();

        if (data.erro) throw new Error('CEP não encontrado.');

        document.getElementById('cidade').value = data.localidade ?? '';
        document.getElementById('estado').value = data.uf         ?? '';
    } catch (err) {
        alert('Não foi possível buscar o CEP:\n' + err.message);
    } finally {
        this.textContent = '🔍';
        this.disabled    = false;
    }
});

/* ── Preview de chips para requisitos e benefícios ─────────── */
function renderChips(inputId, chipsId) {
    const input  = document.getElementById(inputId);
    const wrap   = document.getElementById(chipsId);
    if (!input || !wrap) return;

    input.addEventListener('input', () => {
        const items = input.value
            .split(',')
            .map(s => s.trim())
            .filter(Boolean);

        wrap.innerHTML = items
            .map(item => `<span class="skill-chip" style="font-size:12px;">${
                item.replace(/</g,'&lt;').replace(/>/g,'&gt;')
            }</span>`)
            .join('');
    });
}
renderChips('requisitos', 'reqChips');
renderChips('beneficios', 'benChips');

/* ── Validação client-side antes do submit ─────────────────── */
document.getElementById('formVaga')?.addEventListener('submit', function (e) {
    const titulo = document.getElementById('titulo')?.value.trim();
    const desc   = document.getElementById('descricao')?.value.trim();
    const tipo   = document.getElementById('tipo_contrato')?.value;

    if (!titulo || !desc || !tipo) {
        e.preventDefault();
        alert('Preencha os campos obrigatórios: Título, Descrição e Tipo de Contrato.');
    }
});
</script>

</body>
</html>