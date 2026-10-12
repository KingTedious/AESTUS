<?php
/* ============================================================
   Vagas/criar.php — Publicação de nova vaga
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

if (!isset($_SESSION['empresa_id'])) {
    header("Location: ../empresa/login.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/locais.php";   // Local de trabalho
require_once dirname(__DIR__) . "/includes/moderacao.php";      // Stage 7: validação + moderação centrais
require_once dirname(__DIR__) . "/includes/conta_status.php";   // Stage 7: estados de moderação

$empresa_id = intval($_SESSION['empresa_id']);
$tem_locais = jc_locais_disponiveis($conn);   // Sem a migração, o formulário é o de antes

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

    // ── Stage 7: validação estrutural + moderação (includes/moderacao.php) ────
    // Requisitos/benefícios: os itens acima do tamanho da coluna continuam sendo
    // descartados como antes; os demais passam pelas mesmas regras de texto.
    $req_lista = array_values(array_filter(array_map('trim', explode(',', $requisitos_raw)),
                                           fn($r) => $r !== '' && strlen($r) <= 150));
    $ben_lista = array_values(array_filter(array_map('trim', explode(',', $beneficios_raw)),
                                           fn($b) => $b !== '' && strlen($b) <= 100));
    $mod = jc_mod_formulario([
        'vaga.titulo'        => [$titulo, 'titulo'],
        'vaga.descricao'     => [$descricao, 'descricao'],
        'vaga.salario'       => [$salario, 'salario'],
        'vaga.carga_horaria' => [$carga_horaria, 'carga_horaria'],
        'vaga.cidade'        => [$cidade, 'cidade'],
    ], ['tipo_contrato' => $tipo_contrato]);
    $mod_req = jc_mod_lista('vaga.requisito', $req_lista);
    $mod_ben = jc_mod_lista('vaga.beneficio', $ben_lista);
    $erros_validacao = array_values($mod['erros']);
    foreach ([$mod_req['erro'], $mod_ben['erro']] as $e) {
        if ($e !== null) {
            $erros_validacao[] = $e;
        }
    }
    if ($erros_validacao) {
        // volta ao formulário com o que foi digitado (rascunho só na sessão desta empresa)
        $_SESSION['vaga_rascunho'] = [
            'titulo' => mb_substr($titulo, 0, 300), 'descricao' => mb_substr($descricao, 0, 6000),
            'salario' => mb_substr($salario, 0, 100), 'carga_horaria' => mb_substr($carga_horaria, 0, 40),
            'area' => mb_substr($area, 0, 100), 'tipo_contrato' => mb_substr($tipo_contrato, 0, 50),
            'requisitos' => mb_substr($requisitos_raw, 0, 3000), 'beneficios' => mb_substr($beneficios_raw, 0, 2000),
        ];
        $_SESSION['vaga_erros'] = array_slice($erros_validacao, 0, 8);
        jc_log('vaga.recusada', 'aviso', ['campos' => implode(',', array_keys($mod['erros']))]);
        header("Location: criar.php?error=validacao");
        exit;
    }
    $titulo        = $mod['valores']['vaga.titulo'] ?? $titulo;
    $descricao     = $mod['valores']['vaga.descricao'] ?? $descricao;
    $salario       = $mod['valores']['vaga.salario'] ?? $salario;
    $carga_horaria = $mod['valores']['vaga.carga_horaria'] ?? $carga_horaria;
    $cidade        = $mod['valores']['vaga.cidade'] ?? $cidade;
    $req_lista     = array_values($mod_req['valores']);
    $ben_lista     = array_values($mod_ben['valores']);
    $sinais_vaga   = $mod['sinais']
                   + ($mod_req['sinal'] ? ['vaga.requisito' => $mod_req['sinal']] : [])
                   + ($mod_ben['sinal'] ? ['vaga.beneficio' => $mod_ben['sinal']] : []);
    // Muitas vagas da mesma empresa em pouco tempo (10+ na última hora): sinal para a fila
    if (jc_stage7_disponivel($conn)) {
        $stmt_fl = $conn->prepare("SELECT COUNT(*) AS n FROM vagas WHERE empresa_id = ? AND criado_em >= NOW() - INTERVAL 1 HOUR");
        $stmt_fl->bind_param("i", $empresa_id);
        $stmt_fl->execute();
        $recentes = intval($stmt_fl->get_result()->fetch_assoc()['n'] ?? 0);
        $stmt_fl->close();
        if ($recentes >= 10) {
            $sinais_vaga['vaga.flood'] = ['decisao' => 'sinalizar', 'risco' => 'medio', 'mensagem' => null, 'hash' => null,
                'regras' => [['cod' => 'flood.vagas', 'cat' => 'Spam', 'risco' => 'medio',
                              'desc' => 'Muitas vagas publicadas pela mesma empresa em pouco tempo']]];
        }
    }
    // Estado de moderação inicial (separado da publicação):
    //   risco alto  → "pendente": fora do ar até a revisão humana;
    //   risco médio → "sinalizada": no ar, com item na fila;
    //   risco baixo → "aprovada" (no ar), com item na fila de baixa prioridade.
    $nivel_mod = 0;
    foreach ($sinais_vaga as $sv) {
        $nivel_mod = max($nivel_mod, jc_mod_nivel((string) ($sv['risco'] ?? 'baixo')));
    }
    $status_mod = $nivel_mod >= 3 ? 'pendente' : ($nivel_mod >= 2 ? 'sinalizada' : 'aprovada');
    if (!jc_stage7_disponivel($conn)) {
        // Sem a migração da Stage 7 não há estados de moderação nem fila: a vaga é publicada como na
        // v4.00 (o bloqueio de código executável, que não depende do banco, continua valendo).
        $status_mod = 'aprovada';
    }

    // Formata CEP apenas se preenchido (00000-000)
    $cep_fmt = strlen($cep) === 8
        ? substr($cep, 0, 5) . '-' . substr($cep, 5)
        : null;

    // ── local de trabalho ──────────────────────────────────────────
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
        // Stage 7: estado de moderação inicial (padrão do banco = "aprovada")
        if ($status_mod !== 'aprovada' && jc_stage7_disponivel($conn)) {
            $motivo_mod = $status_mod === 'pendente' ? 'revisao_automatica' : null;
            $stmt_mod = $conn->prepare("UPDATE vagas SET moderacao_status = ?, moderacao_motivo = ?, moderacao_em = NOW() WHERE id = ?");
            $stmt_mod->bind_param("ssi", $status_mod, $motivo_mod, $vaga_id);
            $stmt_mod->execute();
            $stmt_mod->close();
        }
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
        $requisitos = $req_lista;   // Stage 7: já validados e normalizados
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
        $beneficios = $ben_lista;   // Stage 7: já validados e normalizados
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

    // PRG → vai para "Minhas vagas" com o aviso certo. Antes ia para o
    // perfil da empresa e mostrava "Perfil atualizado com sucesso!".
    jc_log('vaga.criada', 'info', ['vaga' => $vaga_id, 'local' => $local_trabalho ?? 'vaga', 'moderacao' => $status_mod]);
    // Stage 7: sinais vão para a fila de moderação (nunca suspendem a conta)
    jc_mod_registrar($conn, 'vaga', $vaga_id, $sinais_vaga, 'empresa', $empresa_id);
    header("Location: minhas.php?" . ($status_mod === 'pendente' ? 'revisao=1' : 'publicada=1'));
    exit;
}

// Lê o código de erro do redirect (se houver)
$error = $_GET['error'] ?? null;
$erros_msg = [
    'missing' => 'Preencha os campos obrigatórios: Título, Descrição e Tipo de Contrato.',
    'db'      => 'Erro interno ao salvar a vaga. Tente novamente.',
    'local'   => 'Informe pelo menos a cidade e a UF do local de trabalho (ou escolha o endereço principal da empresa).',
    'validacao' => 'A vaga não foi publicada. Corrija o texto e envie de novo:',
];
// Stage 7: rascunho e motivos quando a validação recusou o envio (só uma vez)
$rascunho = ($error === 'validacao' && is_array($_SESSION['vaga_rascunho'] ?? null)) ? $_SESSION['vaga_rascunho'] : [];
$erros_validacao = ($error === 'validacao' && is_array($_SESSION['vaga_erros'] ?? null)) ? $_SESSION['vaga_erros'] : [];
unset($_SESSION['vaga_rascunho'], $_SESSION['vaga_erros']);
$rv  = fn(string $campo): string => htmlspecialchars((string) ($rascunho[$campo] ?? ''), ENT_QUOTES);
$sel = fn(string $campo, string $valor): string => (($rascunho[$campo] ?? null) === $valor) ? ' selected' : '';
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
    <!-- Marca oficial vertical, como nas outras telas de formulário -->
    <div class="form-page-logo">
        <a href="../dashboard/empresa.php" class="marca marca-vertical nav-logo" aria-label="JovemConnect — meu painel">
            <img src="../assets/img/marca/jovemconnect-vertical.png" alt="JovemConnect" width="546" height="420">
        </a>
    </div>

    <!-- Caminho de volta no topo -->
    <p style="margin:0 0 12px;"><a href="../dashboard/empresa.php" class="back-link"><span class="i i-arrow-left" aria-hidden="true"></span> Voltar ao painel</a></p>

    <!-- ── Erro (GET redirect) ────────────────────────────────────────────────── -->
    <?php if ($error && isset($erros_msg[$error])): ?>
    <div class="form-card" role="alert" style="border-color:rgba(239,68,68,.4);margin-bottom:0;">
        <p class="form-error"><span class="i i-alert-triangle" aria-hidden="true"></span> <?= htmlspecialchars($erros_msg[$error]) ?></p>
        <?php if ($erros_validacao): ?>
        <ul class="erros-lista">
            <?php foreach ($erros_validacao as $ev): ?>
            <li><?= htmlspecialchars((string) $ev) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Data-envio-unico evita publicar a mesma vaga duas vezes (duplo clique) -->
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
                    class="form-control" maxlength="150" value="<?= $rv('titulo') ?>"
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
                        <option value="Jovem Aprendiz"<?= $sel('tipo_contrato', 'Jovem Aprendiz') ?>>Jovem Aprendiz</option>
                        <option value="Estágio"<?= $sel('tipo_contrato', 'Estágio') ?>>Estágio</option>
                        <option value="CLT"<?= $sel('tipo_contrato', 'CLT') ?>>CLT (Efetivo)</option>
                        <option value="PJ"<?= $sel('tipo_contrato', 'PJ') ?>>PJ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="area">Área de Atuação</label>
                    <select id="area" name="area" class="form-control">
                        <option value="">Selecione uma área...</option>
                        <option value="Administrativo"<?= $sel('area', 'Administrativo') ?>>Administrativo</option>
                        <option value="TI"<?= $sel('area', 'TI') ?>>TI</option>
                        <option value="Vendas"<?= $sel('area', 'Vendas') ?>>Vendas</option>
                        <option value="Logística"<?= $sel('area', 'Logística') ?>>Logística</option>
                        <option value="Marketing"<?= $sel('area', 'Marketing') ?>>Marketing</option>
                        <option value="Recursos Humanos"<?= $sel('area', 'Recursos Humanos') ?>>Recursos Humanos</option>
                        <option value="Financeiro"<?= $sel('area', 'Financeiro') ?>>Financeiro</option>
                        <option value="Saúde"<?= $sel('area', 'Saúde') ?>>Saúde</option>
                        <option value="Educação"<?= $sel('area', 'Educação') ?>>Educação</option>
                        <option value="Outro"<?= $sel('area', 'Outro') ?>>Outro</option>
                    </select>
                </div>
            </div>

        </div><!-- /.form-card básico -->

        <!-- ================================================================
             CARD 3 — Localização
             Com a migração, "Local de trabalho" (endereço principal
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
                            class="form-control" maxlength="50" value="<?= $rv('salario') ?>"
                            placeholder="Ex: 1200.00 ou A combinar"
                            style="padding-left:44px;">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="carga_horaria">Carga Horária</label>
                    <select id="carga_horaria" name="carga_horaria" class="form-control">
                        <option value="">Selecione...</option>
                        <option value="20h/semana"<?= $sel('carga_horaria', '20h/semana') ?>>20h/semana (Jovem Aprendiz padrão)</option>
                        <option value="25h/semana"<?= $sel('carga_horaria', '25h/semana') ?>>25h/semana</option>
                        <option value="30h/semana"<?= $sel('carga_horaria', '30h/semana') ?>>30h/semana</option>
                        <option value="40h/semana"<?= $sel('carga_horaria', '40h/semana') ?>>40h/semana</option>
                        <option value="A combinar"<?= $sel('carga_horaria', 'A combinar') ?>>A combinar</option>
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
                    maxlength="5000" required><?= $rv('descricao') ?></textarea>
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
                    class="form-control" value="<?= $rv('requisitos') ?>"
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
                    class="form-control" value="<?= $rv('beneficios') ?>"
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
                <!-- "Voltar ao Painel" levava ao perfil da empresa -->
                <a href="../dashboard/empresa.php" class="link-seta"><span class="i i-arrow-left" aria-hidden="true"></span> Cancelar e voltar ao painel</a>
            </p>
        </div>

    </form>

</div><!-- /.form-container -->
</div><!-- /.form-page -->

<!-- FABs + Assistente + Acessibilidade -->
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

    this.innerHTML = '<span class="i i-loader spin" aria-hidden="true"></span>';   // Ícone de carregamento
    this.setAttribute('aria-busy', 'true');
    this.disabled    = true;

    try {
        const res  = await fetch(`https://viacep.com.br/ws/${cepRaw}/json/`);
        const data = await res.json();

        // CEP inexistente ≠ falha de conexão
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

/* ── "Outro endereço" mostra (e habilita) os campos do local ── */
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