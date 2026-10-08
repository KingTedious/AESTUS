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
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF

if (!isset($_SESSION['empresa_id'])) {
    header("Location: ../empresa/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/locais.php";   // Stage 5.1: local de trabalho

$empresa_id = intval($_SESSION['empresa_id']);
$tem_locais = jc_locais_disponiveis($conn);   // Stage 5.1: sem a migração, o formulário é o de antes

/* ============================================================
   PROCESSAMENTO POST
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

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

    // ── Stage 5.1: local de trabalho ──────────────────────────────────────────
    // Padrão: endereço principal da empresa. Ou um local já cadastrado
    // (escritório, filial...). Ou "outro endereço", que vira um local novo
    // da empresa. A vaga guarda a referência + cópia de cidade/UF/CEP
    // (a busca e as listas de vagas usam essas colunas).
    $local_trabalho = null;
    $local_id       = null;
    $local_novo     = null;
    $escolha        = is_string($_POST['local_trabalho'] ?? null) ? $_POST['local_trabalho'] : null;
    if ($tem_locais && $escolha === null && ($cidade !== '' || $estado !== '')) {
        // formulário antigo (página aberta antes da atualização): mantém o
        // comportamento anterior — cidade/UF/CEP digitados na própria vaga
        $tem_locais_form = false;
    } else {
        $tem_locais_form = $tem_locais;
    }
    if ($tem_locais_form) {
        $escolha = $escolha ?? 'empresa';
        if ($escolha === 'outro') {
            $local_novo = jc_local_do_post($_POST, 'local_');
            if (jc_local_erro($local_novo) !== null) {
                header("Location: criar.php?error=local");
                exit;
            }
            $local_trabalho = 'local';
            $end_vaga       = $local_novo;
        } elseif (preg_match('/^local:(\d+)$/', $escolha, $m)) {
            $end_vaga = null;
            foreach (jc_locais_da_empresa($conn, $empresa_id) as $l) {   // só locais da própria empresa
                if (intval($l['id']) === intval($m[1])) {
                    $end_vaga = jc_local_partes($l, '');
                    $local_id = intval($l['id']);
                }
            }
            if ($end_vaga === null) {
                header("Location: criar.php?error=local");
                exit;
            }
            $local_trabalho = 'local';
        } else {
            $local_trabalho = 'empresa';
            $end_vaga       = jc_endereco_principal($conn, $empresa_id);
            if ($end_vaga['cidade'] === '') {   // sem cidade no perfil: a opção nem aparece habilitada
                header("Location: criar.php?error=local");
                exit;
            }
        }
        $cidade  = $end_vaga['cidade'];
        $estado  = $end_vaga['estado'];
        $cep_fmt = jc_cep_formatado($end_vaga['cep']);
    }

    // ── Insere a vaga ─────────────────────────────────────────────────────────
    try {
        if ($tem_locais_form) {
            $conn->begin_transaction();
            if ($local_novo !== null) {
                $local_id = jc_local_inserir($conn, $empresa_id, $local_novo);
                if ($local_id <= 0) {
                    throw new RuntimeException('local de trabalho não gravado');
                }
            }
            $stmt = $conn->prepare(
                "INSERT INTO vagas
                     (empresa_id, titulo, descricao, cidade, estado, cep, local_trabalho, local_id,
                      salario, carga_horaria, area, tipo_contrato)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "issssssissss",
                $empresa_id, $titulo, $descricao, $cidade, $estado, $cep_fmt, $local_trabalho, $local_id,
                $salario, $carga_horaria, $area, $tipo_contrato
            );
        } else {
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
        }
        if (!$stmt->execute()) {
            throw new RuntimeException($conn->error);
        }
        $vaga_id = intval($conn->insert_id);
        $stmt->close();
        if ($tem_locais_form) {
            $conn->commit();
        }
    } catch (Throwable $e) {
        error_log("[JovemConnect] Erro ao criar vaga: " . $e->getMessage());
        if ($tem_locais_form) {
            try { $conn->rollback(); } catch (Throwable $e2) { /* sem transação aberta */ }
        }
        header("Location: criar.php?error=db");
        exit;
    }

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

    // PRG → Stage 4: vai para "Minhas vagas" com o aviso certo. Antes ia para o
    // perfil da empresa e mostrava "Perfil atualizado com sucesso!".
    header("Location: minhas.php?publicada=1");
    exit;
}

// Lê o código de erro do redirect (se houver)
$error = $_GET['error'] ?? null;
$erros_msg = [
    'missing' => 'Preencha os campos obrigatórios: Título, Descrição e Tipo de Contrato.',
    'db'      => 'Erro interno ao salvar a vaga. Tente novamente.',
    // Stage 5.1
    'local'   => 'Informe pelo menos a cidade e a UF do local de trabalho (ou escolha o endereço principal da empresa).',
];
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Nova Vaga | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>

<!-- Barra de progresso -->
<div class="page-progress"><div class="page-progress-fill"></div></div>

<div class="form-page">
<div class="form-container">

    <!-- ── Logo ──────────────────────────────────────────────────────────────── -->
    <!-- Stage 5: marca oficial vertical, como nas outras telas de formulário -->
    <div class="form-page-logo">
        <a href="../dashboard/empresa.php" class="marca marca-vertical nav-logo" aria-label="JovemConnect — meu painel">
            <img src="../assets/img/marca/jovemconnect-vertical.png" alt="JovemConnect" width="546" height="420">
        </a>
    </div>

    <!-- Stage 4: caminho de volta no topo (antes só no fim de um formulário longo) -->
    <p style="margin:0 0 12px;"><a href="../dashboard/empresa.php" class="back-link"><span class="i i-arrow-left" aria-hidden="true"></span> Voltar ao painel</a></p>

    <!-- ── Erro (GET redirect) ────────────────────────────────────────────────── -->
    <?php if ($error && isset($erros_msg[$error])): ?>
    <div class="form-card" role="alert" style="border-color:rgba(239,68,68,.4);margin-bottom:0;">
        <p class="form-error"><span class="i i-alert-triangle" aria-hidden="true"></span> <?= htmlspecialchars($erros_msg[$error]) ?></p>
    </div>
    <?php endif; ?>

    <!-- Stage 4: data-envio-unico evita publicar a mesma vaga duas vezes (duplo clique) -->
    <form method="POST" id="formVaga" novalidate data-envio-unico="Publicando vaga...">
        <?= csrf_field() ?>

        <!-- ================================================================
             CARD 1 — Cabeçalho
             ================================================================ -->
        <div class="form-card">
            <div class="form-page-title">
                <span class="i i-briefcase" aria-hidden="true"></span>
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
                <span class="i i-info" aria-hidden="true"></span> Informações Básicas
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
             Stage 5.1: com a migração, "Local de trabalho" (endereço principal
             da empresa, um local cadastrado ou outro endereço). Sem ela, o
             card antigo (CEP/cidade/UF) continua igual.
             ================================================================ -->
        <?php if ($tem_locais):
            $principal     = jc_endereco_principal($conn, $empresa_id);
            $principal_txt = endereco_empresa_formatado($principal);
            $locais_emp    = jc_locais_da_empresa($conn, $empresa_id);
            $padrao        = $principal['cidade'] !== '' ? 'empresa' : ($locais_emp ? 'local:' . intval($locais_emp[0]['id']) : 'outro');
            $form          = ['cep' => '', 'logradouro' => '', 'numero' => '', 'complemento' => '', 'bairro' => '', 'cidade' => '', 'estado' => ''];
        ?>
        <div class="form-card">
            <div class="form-section-header">
                <span class="i i-map-pin" aria-hidden="true"></span> Local de trabalho
            </div>
            <fieldset class="local-opcoes">
                <legend class="form-label">Onde a pessoa vai trabalhar?</legend>
                <label class="local-opcao">
                    <input type="radio" name="local_trabalho" value="empresa"
                           <?= $padrao === 'empresa' ? 'checked' : '' ?> <?= $principal['cidade'] === '' ? 'disabled' : '' ?>>
                    <span class="local-opcao-texto">
                        <strong>Endereço principal da empresa</strong>
                        <span><?= $principal_txt !== '' ? htmlspecialchars($principal_txt) : 'A empresa ainda não tem cidade cadastrada no perfil.' ?></span>
                    </span>
                </label>
                <?php foreach ($locais_emp as $l): $valor = 'local:' . intval($l['id']); ?>
                <label class="local-opcao">
                    <input type="radio" name="local_trabalho" value="<?= $valor ?>" <?= $padrao === $valor ? 'checked' : '' ?>>
                    <span class="local-opcao-texto">
                        <strong><?= htmlspecialchars(jc_local_rotulo($l['tipo'], $l['nome'])) ?></strong>
                        <span><?= htmlspecialchars(endereco_empresa_formatado($l)) ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
                <label class="local-opcao">
                    <input type="radio" name="local_trabalho" value="outro" <?= $padrao === 'outro' ? 'checked' : '' ?>>
                    <span class="local-opcao-texto">
                        <strong>Outro endereço</strong>
                        <span>Fica salvo nos locais da empresa para as próximas vagas.</span>
                    </span>
                </label>
            </fieldset>

            <!-- escondido = desabilitado: os campos só vão no envio quando "Outro endereço" está marcado -->
            <fieldset id="localOutro" class="local-outro" <?= $padrao === 'outro' ? '' : 'hidden disabled' ?>>
                <legend class="sr-only">Endereço do outro local</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="vagaLocalTipo">Tipo de local</label>
                        <select name="local_tipo" id="vagaLocalTipo" class="form-control">
                            <?php foreach (jc_local_tipos() as $v => $tt): ?>
                            <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($tt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="vagaLocalNome">Nome do local (opcional)</label>
                        <input type="text" name="local_nome" id="vagaLocalNome" class="form-control" maxlength="100"
                               placeholder="Ex: Unidade Augusta">
                    </div>
                </div>
                <?php $campos_prefixo = 'vagaLocal'; $campos_nome = 'local_'; include dirname(__DIR__) . '/includes/locais_campos.php'; ?>
            </fieldset>
            <p style="font-size:13px;margin-top:4px;">
                <a href="../empresa/locais.php" class="link-seta">Gerenciar locais de trabalho <span class="i i-arrow-right" aria-hidden="true"></span></a>
            </p>
        </div><!-- /.form-card local de trabalho -->
        <?php else: ?>
        <div class="form-card">
            <div class="form-section-header">
                <span class="i i-map-pin" aria-hidden="true"></span> Localização
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
                            title="Buscar CEP" aria-label="Buscar CEP"><span class="i i-search" aria-hidden="true"></span></button>
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
        <?php endif; ?>

        <!-- ================================================================
             CARD 4 — Remuneração e Carga Horária
             ================================================================ -->
        <div class="form-card">
            <div class="form-section-header">
                <span class="i i-banknote" aria-hidden="true"></span> Remuneração
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
                <span class="i i-file-text" aria-hidden="true"></span> Descrição da Vaga
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
                <span class="i i-list-checks" aria-hidden="true"></span> Requisitos e Benefícios
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
                <span class="i i-send" aria-hidden="true"></span> Publicar Vaga Agora
            </button>
            <p style="text-align:center;font-size:13px;color:var(--text-secondary);">
                <!-- Stage 4: "Voltar ao Painel" levava ao perfil da empresa -->
                <a href="../dashboard/empresa.php" class="link-seta"><span class="i i-arrow-left" aria-hidden="true"></span> Cancelar e voltar ao painel</a>
            </p>
        </div>

    </form>

</div><!-- /.form-container -->
</div><!-- /.form-page -->

<!-- FABs + Assistente + Acessibilidade (Stage 4: antes o botão ♿ não tinha painel nem script) -->
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>

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

    this.innerHTML = '<span class="i i-loader spin" aria-hidden="true"></span>';   // Stage 5: ícone de carregamento
    this.setAttribute('aria-busy', 'true');
    this.disabled    = true;

    try {
        const res  = await fetch(`https://viacep.com.br/ws/${cepRaw}/json/`);
        const data = await res.json();

        // Stage 4: CEP inexistente ≠ falha de conexão (antes a falha aparecia em inglês: "Failed to fetch")
        if (data.erro) throw Object.assign(new Error('CEP não encontrado'), { naoEncontrado: true });

        document.getElementById('cidade').value = data.localidade ?? '';
        document.getElementById('estado').value = data.uf         ?? '';
    } catch (err) {
        alert(err?.naoEncontrado
            ? 'CEP não encontrado. Confira o número ou preencha cidade e UF manualmente.'
            : 'Não foi possível buscar o CEP agora. Preencha cidade e UF manualmente.');
    } finally {
        this.innerHTML = '<span class="i i-search" aria-hidden="true"></span>';
        this.removeAttribute('aria-busy');
        this.disabled    = false;
    }
});

/* ── Stage 5.1: "Outro endereço" mostra (e habilita) os campos do local ── */
document.querySelectorAll('input[name="local_trabalho"]').forEach(r => r.addEventListener('change', () => {
    const outro = document.querySelector('input[name="local_trabalho"][value="outro"]');
    const bloco = document.getElementById('localOutro');
    if (!bloco) return;
    bloco.hidden   = !outro?.checked;
    bloco.disabled = !outro?.checked;
}));
// "Outro endereço" precisa de cidade e UF (o servidor confere de novo)
document.getElementById('formVaga')?.addEventListener('submit', e => {
    const outro = document.querySelector('input[name="local_trabalho"][value="outro"]');
    if (!outro?.checked) return;
    const vazio = ['vagaLocalCidade', 'vagaLocalEstado']
        .map(id => document.getElementById(id))
        .find(el => el && !el.value.trim());
    if (vazio) {
        e.preventDefault();
        window.jcAviso?.('Informe a cidade e a UF do local de trabalho.');
        vazio.focus();
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