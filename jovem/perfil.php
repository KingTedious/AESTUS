<?php
/* ============================================================
   jovem/perfil.php

   Serve DOIS layouts conforme quem acessa:
   - Jovem vendo o próprio perfil  → editável (Perfil_Jovem.png)
   - Empresa vendo um candidato    → read-only + ações (Perfil_Jovem__Empresa_POV_.png)

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. verificar_autenticacao('jovem') bloqueava EMPRESAS de ver
      perfis de candidatos — contradiz a lógica abaixo que tenta
      tratar o caso de empresa.
   2. session_start() depois de require auth.php → redundante / frágil.
   3. include header.php no meio do bloco PHP após queries.
   4. <link> de CSS no body.
   5. Bug: $formacao = trim($_POST['nome']) → copiava o nome.
   6. SELECT * → sem controle das colunas retornadas.
   7. Nenhum campo v3.00: resumo_profissional, area_interesse,
      clt, cep, perfil_publico, habilidades_jovem, formacao_jovem,
      experiencias_jovem.
   8. Sem PRG → F5 resubmetia o formulário.
   9. include footer.php em página com HTML próprio.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF
require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/formacao.php";   // Stage 5: nível → curso/ano → situação

// ── Auth inline (não usa verificar_autenticacao pois atende jovem E empresa) ──
$tem_jovem   = isset($_SESSION['jovem_id']);
$tem_empresa = isset($_SESSION['empresa_id']);

if (!$tem_jovem && !$tem_empresa) {
    header("Location: login.php");
    exit;
}

// ── Define qual jovem está sendo visualizado ──────────────────────────────────
if (isset($_GET['id'])) {
    $jovem_id = intval($_GET['id']);
} elseif ($tem_jovem) {
    $jovem_id = intval($_SESSION['jovem_id']);
} else {
    // Empresa acessou /jovem/perfil.php sem ?id → volta para dashboard
    header("Location: ../dashboard/empresa.php");
    exit;
}

$eh_dono    = $tem_jovem && (intval($_SESSION['jovem_id']) === $jovem_id);
$eh_empresa = $tem_empresa && !$eh_dono;

// Empresa só pode ver perfis públicos (perfil_publico = 1)
// Jovem dono sempre vê o próprio
// Outro jovem tentando ver → redireciona para o próprio perfil
if (!$eh_dono && !$eh_empresa) {
    header("Location: perfil.php");
    exit;
}

$saved = isset($_GET['saved']) ? intval($_GET['saved']) : 0;

/* ============================================================
   PROCESSAMENTO POST — apenas para o dono do perfil
   Usa ?action= para distinguir as operações.
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $eh_dono) {
    csrf_require();

    $action = $_POST['action'] ?? 'update_perfil';

    // ── Atualização dos dados principais ─────────────────────────────────────
    if ($action === 'update_perfil') {

        // Stage 3: limites iguais ao tamanho das colunas (antes, texto longo = erro fatal)
        $nome               = mb_substr(trim($_POST['nome']               ?? ''), 0, 100);
        $email              = trim($_POST['email']              ?? '');   // validado abaixo (não cortado)
        $cidade             = mb_substr(trim($_POST['cidade']             ?? ''), 0, 100);
        $estado             = mb_substr(strtoupper(trim($_POST['estado']  ?? '')), 0, 2);
        $cep                = mb_substr(preg_replace('/\D/', '', $_POST['cep'] ?? ''), 0, 8);
        $resumo             = trim($_POST['resumo_profissional'] ?? '');
        $area_interesse     = mb_substr(trim($_POST['area_interesse']     ?? ''), 0, 100);
        $perfil_publico     = isset($_POST['perfil_publico']) ? 1 : 0;

        if (empty($nome) || mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: perfil.php?saved=2");
            exit;
        }

        // Stage 3: e-mail já usado por outra conta → aviso (antes: erro fatal de chave única)
        $stmt = $conn->prepare("SELECT id FROM jovens WHERE email = ? AND id <> ? LIMIT 1");
        $stmt->bind_param("si", $email, $jovem_id);
        $stmt->execute();
        $email_em_uso = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($email_em_uso) {
            header("Location: perfil.php?saved=3");
            exit;
        }

        $stmt = $conn->prepare(
            "SELECT email, email_verificado, escolaridade_status, escolaridade_tipo,
                    escolaridade_curso, formacao
             FROM jovens WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $jovem_id);
        $stmt->execute();
        $atual = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Stage 3: trocar o e-mail desfaz a verificação (o endereço novo não foi verificado)
        $email_verificado = (mb_strtolower((string)$atual['email']) === mb_strtolower($email))
                            ? (int)$atual['email_verificado'] : 0;

        // Stage 3: escolaridade editável no perfil (antes só no cadastro; o Match usa esses campos).
        // Mesmas regras do cadastro: status controlado; "técnico" quando há nome de curso.
        $esc_status = $atual['escolaridade_status'];
        $esc_tipo   = $atual['escolaridade_tipo'];
        $esc_curso  = $atual['escolaridade_curso'];
        $formacao   = $atual['formacao'];
        // Stage 5: formulário novo (nível → curso/ano → situação). Erro na formação
        // não salva nada e avisa qual campo falta; POST antigo segue as regras da Stage 3.
        $erros_formacao = [];
        $form_formacao  = jc_formacao_ler_post($_POST, $erros_formacao, '', 'escolaridade_status', false, true);
        if ($form_formacao !== null && jc_formacao_inalterado($form_formacao['valores'], jc_formacao_de_jovem($atual))) {
            // o jovem salvou outros dados sem mexer na formação: o registro (até texto antigo) fica igual
        } elseif ($form_formacao !== null) {
            if ($erros_formacao) {
                $_SESSION['erro_formacao'] = $erros_formacao[0];
                header("Location: perfil.php?saved=4");
                exit;
            }
            $esc_status = $form_formacao['situacao'];
            $esc_tipo   = $form_formacao['tipo'];
            $esc_curso  = ($form_formacao['curso_tecnico'] !== '') ? $form_formacao['curso_tecnico'] : null;
            $formacao   = $form_formacao['rotulo'];
        } elseif (isset($_POST['escolaridade_status'])) {
            if (in_array($_POST['escolaridade_status'], ['cursando', 'completo'], true)) {
                $esc_status = $_POST['escolaridade_status'];
            }
            // "Possuo curso técnico" é um checkbox, como no cadastro (um registro antigo
            // marcado como técnico sem nome de curso não vira "regular" ao salvar)
            $esc_tipo   = isset($_POST['escolaridade_tipo']) ? 'tecnico' : 'regular';
            $curso      = mb_substr(trim($_POST['escolaridade_curso'] ?? ''), 0, 100);
            $esc_curso  = ($esc_tipo === 'tecnico' && $curso !== '') ? $curso : null;
            $formacao   = mb_substr(trim($_POST['formacao'] ?? ''), 0, 100);
        }

        $stmt = $conn->prepare(
            "UPDATE jovens
             SET nome = ?, email = ?, email_verificado = ?, cidade = ?, estado = ?, cep = ?,
                 resumo_profissional = ?, area_interesse = ?, perfil_publico = ?,
                 escolaridade_status = ?, escolaridade_tipo = ?, escolaridade_curso = ?,
                 formacao = ?
             WHERE id = ?"
        );
        $stmt->bind_param(
            "ssisssssissssi",
            $nome, $email, $email_verificado, $cidade, $estado, $cep,
            $resumo, $area_interesse, $perfil_publico,
            $esc_status, $esc_tipo, $esc_curso, $formacao, $jovem_id
        );
        $stmt->execute();
        $stmt->close();

        // Atualiza nome na sessão
        $_SESSION['jovem_nome'] = $nome;

        header("Location: perfil.php?saved=1");
        exit;
    }

    // ── Adicionar formação ────────────────────────────────────────────────────
    if ($action === 'add_formacao' && array_key_exists('form_formacao_nivel', $_POST)) {
        // Stage 5: formação adicional também é estruturada: título padronizado
        // ("Ensino Técnico — Logística"), situação + ano em "conclusao", curso em "area".
        $erros_add = [];
        $nova = jc_formacao_ler_post($_POST, $erros_add, 'form_', 'form_situacao', true, false);
        $ano_conc = trim($_POST['form_conclusao'] ?? '');
        if ($ano_conc !== '' && !preg_match('/^(19|20)\d{2}$/', $ano_conc)) {
            $erros_add[] = 'Ano de conclusão inválido (use 4 dígitos, ex.: 2026).';
        }
        if ($erros_add) {
            $_SESSION['erro_formacao'] = $erros_add[0];
            header("Location: perfil.php?saved=4#formacao");
            exit;
        }
        $titulo      = mb_substr($nova['rotulo'], 0, 150);
        $instituicao = mb_substr(trim($_POST['form_instituicao'] ?? ''), 0, 150);
        $conclusao   = ($nova['situacao'] === 'completo' ? 'Concluído' : 'Cursando') . ($ano_conc !== '' ? " ($ano_conc)" : '');
        $area        = in_array($nova['nivel'], ['tecnico', 'superior', 'profissionalizante'], true)
                       ? mb_substr($nova['curso'], 0, 100) : '';
        $stmt = $conn->prepare(
            "INSERT INTO formacao_jovem (jovem_id, titulo, instituicao, conclusao, area)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("issss", $jovem_id, $titulo, $instituicao, $conclusao, $area);
        $stmt->execute();
        $stmt->close();
        header("Location: perfil.php#formacao");
        exit;
    }
    if ($action === 'add_formacao') {
        // Stage 3: limites das colunas (conclusao tem só 20 caracteres → antes, erro fatal)
        $titulo      = mb_substr(trim($_POST['form_titulo']      ?? ''), 0, 150);
        $instituicao = mb_substr(trim($_POST['form_instituicao'] ?? ''), 0, 150);
        $conclusao   = mb_substr(trim($_POST['form_conclusao']   ?? ''), 0, 20);
        $area        = mb_substr(trim($_POST['form_area']        ?? ''), 0, 100);

        if (!empty($titulo)) {
            $stmt = $conn->prepare(
                "INSERT INTO formacao_jovem (jovem_id, titulo, instituicao, conclusao, area)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("issss", $jovem_id, $titulo, $instituicao, $conclusao, $area);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: perfil.php#formacao");
        exit;
    }

    // ── Remover formação ──────────────────────────────────────────────────────
    if ($action === 'del_formacao') {
        $form_id = intval($_POST['form_id'] ?? 0);
        $stmt = $conn->prepare(
            "DELETE FROM formacao_jovem WHERE id = ? AND jovem_id = ?"
        );
        $stmt->bind_param("ii", $form_id, $jovem_id);
        $stmt->execute();
        $stmt->close();
        header("Location: perfil.php#formacao");
        exit;
    }

    // ── Adicionar habilidade ──────────────────────────────────────────────────
    if ($action === 'add_habilidade') {
        $habilidade = mb_substr(trim(preg_replace('/\s+/u', ' ', $_POST['habilidade'] ?? '')), 0, 80);
        if ($habilidade !== '') {
            // Stage 3: não duplica. Não há chave UNIQUE na tabela (o INSERT IGNORE
            // nunca bloqueava nada); a comparação usa a collation da coluna
            // (utf8mb4_unicode_ci): "excel básico" = "Excel Básico".
            $stmt = $conn->prepare(
                "SELECT id FROM habilidades_jovem WHERE jovem_id = ? AND habilidade = ? LIMIT 1"
            );
            $stmt->bind_param("is", $jovem_id, $habilidade);
            $stmt->execute();
            $ja_tem = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$ja_tem) {
                $stmt = $conn->prepare(
                    "INSERT IGNORE INTO habilidades_jovem (jovem_id, habilidade) VALUES (?, ?)"
                );
                $stmt->bind_param("is", $jovem_id, $habilidade);
                $stmt->execute();
                $stmt->close();
            }
        }
        header("Location: perfil.php#habilidades");
        exit;
    }

    // ── Remover habilidade ────────────────────────────────────────────────────
    if ($action === 'del_habilidade') {
        $hab_id = intval($_POST['hab_id'] ?? 0);
        $stmt = $conn->prepare(
            "DELETE FROM habilidades_jovem WHERE id = ? AND jovem_id = ?"
        );
        $stmt->bind_param("ii", $hab_id, $jovem_id);
        $stmt->execute();
        $stmt->close();
        header("Location: perfil.php#habilidades");
        exit;
    }

    // ── Adicionar experiência ─────────────────────────────────────────────────
    if ($action === 'add_experiencia') {
        $titulo   = mb_substr(trim($_POST['exp_titulo']   ?? ''), 0, 150);   // Stage 3: limites das colunas
        $local    = mb_substr(trim($_POST['exp_local']    ?? ''), 0, 150);
        $periodo  = mb_substr(trim($_POST['exp_periodo']  ?? ''), 0, 50);
        $descricao = trim($_POST['exp_descricao'] ?? '');

        if (!empty($titulo)) {
            $stmt = $conn->prepare(
                "INSERT INTO experiencias_jovem (jovem_id, titulo, local, periodo, descricao)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("issss", $jovem_id, $titulo, $local, $periodo, $descricao);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: perfil.php#experiencias");
        exit;
    }

    // ── Remover experiência ───────────────────────────────────────────────────
    if ($action === 'del_experiencia') {
        $exp_id = intval($_POST['exp_id'] ?? 0);
        $stmt = $conn->prepare(
            "DELETE FROM experiencias_jovem WHERE id = ? AND jovem_id = ?"
        );
        $stmt->bind_param("ii", $exp_id, $jovem_id);
        $stmt->execute();
        $stmt->close();
        header("Location: perfil.php#experiencias");
        exit;
    }
}

/* ============================================================
   LEITURA DOS DADOS
   ============================================================ */

// Dados principais do jovem
$stmt = $conn->prepare(
    "SELECT nome, email, cpf, data_nascimento, cidade, estado, cep,
            escolaridade_status, escolaridade_tipo, escolaridade_curso, formacao,
            resumo_profissional, area_interesse, perfil_publico, curriculo,
            TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) AS idade
     FROM   jovens
     WHERE  id = ?
     LIMIT  1"
);
$stmt->bind_param("i", $jovem_id);
$stmt->execute();
$jovem = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$jovem) {
    http_response_code(404);
    echo "Perfil não encontrado.";
    exit;
}

// Empresa não pode ver perfis privados
if ($eh_empresa && !$jovem['perfil_publico']) {
    header("Location: ../dashboard/empresa.php");
    exit;
}

// Formações
$stmt_f = $conn->prepare(
    "SELECT id, titulo, instituicao, conclusao, area
     FROM   formacao_jovem WHERE jovem_id = ? ORDER BY id ASC"
);
$stmt_f->bind_param("i", $jovem_id);
$stmt_f->execute();
$formacoes = $stmt_f->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_f->close();

// Habilidades
$stmt_h = $conn->prepare(
    "SELECT id, habilidade FROM habilidades_jovem WHERE jovem_id = ? ORDER BY id ASC"
);
$stmt_h->bind_param("i", $jovem_id);
$stmt_h->execute();
$habilidades = $stmt_h->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_h->close();

// Experiências (só carrega para o dono)
$experiencias = [];
if ($eh_dono) {
    $stmt_e = $conn->prepare(
        "SELECT id, titulo, local, periodo, descricao
         FROM   experiencias_jovem WHERE jovem_id = ? ORDER BY id ASC"
    );
    $stmt_e->bind_param("i", $jovem_id);
    $stmt_e->execute();
    $experiencias = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_e->close();
}

// Dados para exibição
$inicial     = strtoupper(mb_substr($jovem['nome'], 0, 1));
$nome_jovem  = htmlspecialchars($jovem['nome']);
$idade_label = $jovem['idade'] !== null ? intval($jovem['idade']) . ' anos' : '';
$local_parts = array_filter([
    htmlspecialchars($jovem['cidade'] ?? ''),
    htmlspecialchars($jovem['estado'] ?? ''),
]);
$localizacao = implode(', ', $local_parts);

// Stage 3: escolaridade informada no cadastro (antes não aparecia em lugar nenhum do perfil)
// Stage 5: resumo pelo formato novo ("Ensino Médio — 3º ano · Cursando"); registro antigo = resumo da Stage 3
$esc_resumo = jc_formacao_resumo($jovem);

// Stage 5: motivo de um erro ao salvar a formação (?saved=4)
$erro_formacao = $eh_dono ? (string)($_SESSION['erro_formacao'] ?? '') : '';
unset($_SESSION['erro_formacao']);

// Stage 3: currículo em PDF — só mostra link se o arquivo existir de fato
$tem_curriculo = !empty($jovem['curriculo'])
    && is_file(dirname(__DIR__) . '/uploads/curriculos/' . basename($jovem['curriculo']));
$link_curriculo = '../uploads/ver_curriculo.php?id=' . $jovem_id;

// Stage 3: mensagens gravadas por uploads/curriculos.php (antes nunca exibidas)
$msg_upload_ok   = $eh_dono ? ($_SESSION['sucesso_upload'] ?? null) : null;
$msg_upload_erro = $eh_dono ? ($_SESSION['erro_upload']    ?? null) : null;
unset($_SESSION['sucesso_upload'], $_SESSION['erro_upload']);

// Stage 4: destino do "←". A empresa que abriu o perfil pela lista de candidatos
// de uma vaga volta para essa lista (antes ia sempre para o painel). Só aceita o
// valor fixo "candidatos" + id numérico — nunca uma URL vinda da requisição.
$voltar_url    = $eh_empresa ? '../dashboard/empresa.php' : '../dashboard/jovem.php';
$voltar_rotulo = 'Voltar ao painel';
if ($eh_empresa && ($_GET['voltar'] ?? '') === 'candidatos' && intval($_GET['vaga'] ?? 0) > 0) {
    $voltar_url    = '../Vagas/candidatos.php?vaga=' . intval($_GET['vaga']);
    $voltar_rotulo = 'Voltar aos candidatos da vaga';
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $eh_dono ? 'Meu Perfil' : 'Perfil do Candidato' ?> | JovemConnect
    </title>
    <link rel="stylesheet" href="../Assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>
<?= csrf_js() ?><!-- Stage 3: o IA Advisor usa fetch() em endpoint protegido por CSRF (antes: 403) -->

<!-- ============================================================
     BARRA DE TOPO
     ============================================================ -->
<div class="profile-bar">
    <div class="d-flex align-center gap-12">
        <a href="<?= htmlspecialchars($voltar_url) ?>"
           class="icon-btn" title="<?= htmlspecialchars($voltar_rotulo) ?>" aria-label="<?= htmlspecialchars($voltar_rotulo) ?>"><span class="i i-arrow-left" aria-hidden="true"></span></a>
        <span class="marca marca-compacta"><img src="../assets/img/marca/jovemconnect-simbolo.png" alt="JovemConnect" width="192" height="192"></span>
        <span class="profile-bar-title">
            <?= $eh_dono ? 'Meu Perfil' : 'Perfil do Candidato' ?>
        </span>
    </div>

    <?php if ($eh_dono): ?>
        <button type="button" class="btn btn-secondary btn-sm" id="btnEditar">
            Editar Dados
        </button>
    <?php elseif ($eh_empresa): ?>
        <a href="mailto:<?= htmlspecialchars($jovem['email']) ?>"
           class="btn btn-primary btn-sm">
            <span class="i i-mail" aria-hidden="true"></span> Entrar em Contato
        </a>
    <?php endif; ?>
</div>

<!-- Toast de feedback (PRG) -->
<?php if ($msg_upload_ok): ?>
<div id="toast" class="toast toast-success"><span class="i i-check-circle" aria-hidden="true"></span><?= htmlspecialchars($msg_upload_ok) ?></div>
<?php elseif ($msg_upload_erro): ?>
<div id="toast" class="toast toast-error"><span class="i i-x-circle" aria-hidden="true"></span><?= htmlspecialchars($msg_upload_erro) ?></div>
<?php elseif ($saved === 1): ?>
<div id="toast" class="toast toast-success"><span class="i i-check-circle" aria-hidden="true"></span>Perfil atualizado com sucesso!</div>
<?php elseif ($saved === 2): ?>
<div id="toast" class="toast toast-error"><span class="i i-x-circle" aria-hidden="true"></span>Preencha o nome e um e-mail válido antes de salvar.</div>
<?php elseif ($saved === 3): ?>
<div id="toast" class="toast toast-error"><span class="i i-x-circle" aria-hidden="true"></span>Este e-mail já está em uso por outra conta.</div>
<?php elseif ($saved === 4): ?>
<div id="toast" class="toast toast-error"><span class="i i-x-circle" aria-hidden="true"></span><?= htmlspecialchars($erro_formacao !== '' ? $erro_formacao : 'Confira os dados da formação.') ?></div>
<?php endif; ?>

<!-- ============================================================
     BARRA DE PROGRESSO (topo da página)
     ============================================================ -->
<div class="page-progress"><div class="page-progress-fill"></div></div>

<?php if ($eh_dono): ?>
<!-- ============================================================
     VIEW 1: PRÓPRIO PERFIL (editável)
     ============================================================ -->
<!-- Stage 3: este form antes envolvia a página inteira e "engolia" o form de
     adicionar formação (forms aninhados são inválidos em HTML): "Salvar" era
     bloqueado pelo campo obrigatório da formação ou enviava action=add_formacao.
     Agora ele fica vazio aqui e os campos do perfil apontam para ele com
     form="formPerfil"; os demais forms da página voltam a ser independentes. -->
<form method="POST" action="perfil.php" id="formPerfil">
    <input type="hidden" name="action" value="update_perfil">
    <?= csrf_field() ?>
</form>

<div class="profile-page">

    <!-- ── SIDEBAR ───────────────────────────────────────────────────────── -->
    <aside class="profile-side">

        <div class="profile-side-avatar">
            <div class="avatar avatar-xl"><?= $inicial ?></div>

            <!-- VIEW -->
            <div id="viewNome">
                <p class="profile-side-name"><?= $nome_jovem ?></p>
                <p class="profile-side-role"><span class="i i-graduation-cap" aria-hidden="true"></span> Jovem Aprendiz</p>
            </div>

            <!-- EDIT (oculto por padrão) -->
            <div id="editNome" class="hidden w-full">
                <div class="form-group" style="width:100%">
                    <label class="form-label" for="perfilNome">Nome Completo *</label>
                    <input type="text" name="nome" id="perfilNome" class="form-control" form="formPerfil"
                           value="<?= $nome_jovem ?>" maxlength="100" required>
                </div>
            </div>
        </div>

        <!-- Dados básicos VIEW -->
        <div id="viewDados">
            <div class="profile-info-item">
                <p class="profile-info-label">E-mail</p>
                <p class="profile-info-value"><?= htmlspecialchars($jovem['email']) ?></p>
            </div>
            <div class="profile-info-item">
                <p class="profile-info-label">Idade</p>
                <p class="profile-info-value"><?= $idade_label ?: '—' ?></p>
            </div>
            <div class="profile-info-item">
                <p class="profile-info-label">Localização</p>
                <p class="profile-info-value"><span class="i i-map-pin" aria-hidden="true"></span> <?= $localizacao ?: '—' ?></p>
            </div>
        </div>

        <!-- Dados básicos EDIT -->
        <div id="editDados" class="hidden">
            <div class="form-group">
                <label class="form-label" for="perfilEmail">E-mail *</label>
                <input type="email" name="email" id="perfilEmail" class="form-control" form="formPerfil"
                       value="<?= htmlspecialchars($jovem['email']) ?>" maxlength="100" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="perfilCidade">Cidade</label>
                    <input type="text" name="cidade" id="perfilCidade" class="form-control" form="formPerfil"
                           value="<?= htmlspecialchars($jovem['cidade'] ?? '') ?>" maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label" for="perfilEstado">UF</label>
                    <input type="text" name="estado" id="perfilEstado" class="form-control" form="formPerfil"
                           value="<?= htmlspecialchars($jovem['estado'] ?? '') ?>"
                           maxlength="2" placeholder="SP">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="perfilCep">CEP</label>
                <input type="text" name="cep" id="perfilCep" class="form-control" form="formPerfil"
                       value="<?= htmlspecialchars($jovem['cep'] ?? '') ?>"
                       placeholder="00000-000" maxlength="9">
            </div>
            <div class="form-group">
                <label class="form-label" for="perfilAreaInteresse">Área de Interesse</label>
                <input type="text" name="area_interesse" id="perfilAreaInteresse" class="form-control" form="formPerfil"
                       value="<?= htmlspecialchars($jovem['area_interesse'] ?? '') ?>"
                       placeholder="Ex: Administrativo, TI" maxlength="100">
            </div>
            <!-- Stage 3: escolaridade editável (antes só existia no cadastro).
                 Stage 5: nível → curso/ano → situação; dados antigos são reconhecidos
                 ou aparecem em "Outro" — nada é apagado sem o jovem salvar. -->
            <p class="form-label" style="margin:6px 0 10px;">Escolaridade</p>
            <?= jc_formacao_campos([
                'id' => 'perf', 'nome' => '', 'form' => 'formPerfil', 'situacao' => 'escolaridade_status',
                'em_tecnico' => true, 'obrigatorio' => false, 'vazio' => 'Não informado',
                'valores' => jc_formacao_de_jovem($jovem),
            ]) ?>
            <button type="submit" form="formPerfil" class="btn btn-primary btn-full btn-sm mt-8">
                Salvar Dados Básicos
            </button>
        </div>

        <!-- IA Advisor -->
        <div class="ia-advisor-card">
            <p class="ia-advisor-title"><span class="i i-sparkles" aria-hidden="true"></span> IA Advisor</p>
            <p class="ia-advisor-sub">Sugestões personalizadas para seu sucesso</p>
            <button type="button" class="btn btn-primary btn-full btn-sm" id="btnAnalisarPerfil">
                Analisar meu Perfil
            </button>
            <p class="text-muted" style="font-size:11px;margin-top:8px;text-align:center;">
                Clique no botão acima para receber sugestões de melhoria da nossa IA.
            </p>
            <div id="iaAdvisorResult" class="hidden" style="margin-top:12px;"></div>
        </div>

        <!-- Excluir conta -->
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
            <button type="button" class="btn btn-danger btn-full btn-sm" id="btnExcluir">
                Excluir Conta
            </button>
        </div>

    </aside><!-- /.profile-side -->

    <!-- ── CONTEÚDO PRINCIPAL ─────────────────────────────────────────────── -->
    <main class="profile-main">

        <!-- Currículo Automático -->
        <div class="profile-section fade-up">
            <div class="profile-section-head">
                <h2 class="profile-section-title"><span class="i i-file-text" aria-hidden="true"></span> Currículo Automático</h2>
                <?php if ($tem_curriculo): ?>
                <a href="<?= $link_curriculo ?>"
                   target="_blank" rel="noopener"
                   class="btn btn-secondary btn-sm"
                   id="btnBaixarPDF">
                    <span class="i i-download" aria-hidden="true"></span> Baixar PDF
                </a>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="perfilResumo">Objetivo e Resumo Profissional</label>
                <textarea name="resumo_profissional" id="perfilResumo" class="form-control" form="formPerfil"
                          rows="5"
                          placeholder="Escreva um resumo profissional claro: seus objetivos, área de interesse e diferenciais..."
                          ><?= htmlspecialchars($jovem['resumo_profissional'] ?? '') ?></textarea>
            </div>

            <button type="submit" form="formPerfil" class="btn btn-primary btn-sm">Salvar Resumo</button>

            <!-- Stage 3: envio do currículo em PDF. uploads/curriculos.php já existia
                 (com validação de tipo/tamanho), mas nenhuma tela o usava. -->
            <div class="form-group" id="curriculo" style="margin-top:20px;">
                <label class="form-label" for="perfilCurriculo">Currículo em PDF</label>
                <p class="text-secondary" style="font-size:13px;margin-bottom:8px;">
                    <?php if ($tem_curriculo): ?>
                        <span class="i i-check-circle" aria-hidden="true"></span> Currículo enviado.
                        <a href="<?= $link_curriculo ?>" target="_blank" rel="noopener">Ver PDF</a>
                        — envie outro arquivo para substituir.
                    <?php else: ?>
                        Nenhum currículo enviado ainda.
                    <?php endif; ?>
                </p>
                <!-- Stage 4: data-envio-unico evita enviar o mesmo PDF duas vezes -->
                <form method="POST" action="../uploads/curriculos.php" enctype="multipart/form-data"
                      style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;"
                      data-envio-unico="Enviando PDF...">
                    <?= csrf_field() ?>
                    <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                    <input type="file" name="curriculo" id="perfilCurriculo" accept="application/pdf,.pdf" required
                           aria-describedby="perfilCurriculoAjuda"
                           class="form-control" style="flex:1;min-width:200px;">
                    <button type="submit" class="btn btn-secondary btn-sm">Enviar PDF</button>
                </form>
                <p class="text-muted" id="perfilCurriculoAjuda" style="font-size:11px;margin-top:6px;">Somente PDF, até 5 MB.</p>
            </div>
        </div>

        <!-- Formação Acadêmica -->
        <div class="profile-section fade-up" id="formacao">
            <div class="profile-section-head">
                <h2 class="profile-section-title"><span class="i i-graduation-cap" aria-hidden="true"></span> Formação</h2>
                <button type="button" class="btn btn-ghost btn-sm"
                        onclick="toggleAddForm('addFormacaoForm', this)"
                        title="Adicionar formação" aria-label="Adicionar formação"
                        aria-expanded="false" aria-controls="addFormacaoForm"><span class="i i-plus" aria-hidden="true"></span></button>
            </div>

            <!-- Form de adição (oculto) -->
            <div id="addFormacaoForm" class="hidden"
                 style="background:var(--bg-input);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;">
                <form method="POST" action="perfil.php" data-envio-unico="Adicionando...">
                    <input type="hidden" name="action" value="add_formacao">
    <?= csrf_field() ?>
                    <!-- Stage 5: nível → curso/ano → situação (antes: "Curso / Nível" em texto livre) -->
                    <?= jc_formacao_campos([
                        'id' => 'add', 'nome' => 'form_', 'form' => '', 'situacao' => 'form_situacao',
                        'em_tecnico' => false, 'obrigatorio' => true,
                    ]) ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="formInstituicao">Instituição</label>
                            <input type="text" name="form_instituicao" id="formInstituicao" class="form-control"
                                   placeholder="Nome da escola ou faculdade" maxlength="150">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="formConclusao">Ano de conclusão (ou previsão)</label>
                            <input type="text" name="form_conclusao" id="formConclusao" class="form-control"
                                   placeholder="Ex.: 2026" maxlength="4" inputmode="numeric" pattern="(19|20)[0-9]{2}"
                                   title="Ano com 4 dígitos, ex.: 2026">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Adicionar</button>
                </form>
            </div>

            <!-- Stage 3: escolaridade informada no cadastro (editável em "Editar Dados") -->
            <div class="edu-item">
                <div>
                    <p class="edu-title">Escolaridade</p>
                    <p class="edu-sub"><?= htmlspecialchars($esc_resumo) ?></p>
                </div>
            </div>

            <!-- Lista de formações -->
            <?php if (!empty($formacoes)): ?>
                <?php foreach ($formacoes as $f): ?>
                <div class="edu-item">
                    <div>
                        <p class="edu-title"><?= htmlspecialchars($f['titulo']) ?></p>
                        <p class="edu-sub"><?= jc_formacao_detalhe($f) ?></p>
                    </div>
                    <form method="POST" action="perfil.php" style="display:inline;">
                        <input type="hidden" name="action"  value="del_formacao">
    <?= csrf_field() ?>
                        <input type="hidden" name="form_id" value="<?= intval($f['id']) ?>">
                        <button type="submit" class="edu-delete" title="Remover formação"
                                aria-label="Remover formação: <?= htmlspecialchars($f['titulo']) ?>"><span class="i i-trash" aria-hidden="true"></span></button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">Nenhuma formação adicionada ainda.</div>
            <?php endif; ?>
        </div>

        <!-- Experiências (Opcional) -->
        <div class="profile-section fade-up" id="experiencias">
            <div class="profile-section-head">
                <h2 class="profile-section-title"><span class="i i-briefcase" aria-hidden="true"></span> Experiência (Opcional)</h2>
                <button type="button" class="btn btn-ghost btn-sm"
                        onclick="toggleAddForm('addExpForm', this)"
                        title="Adicionar experiência" aria-label="Adicionar experiência"
                        aria-expanded="false" aria-controls="addExpForm"><span class="i i-plus" aria-hidden="true"></span></button>
            </div>

            <!-- Form de adição (oculto) -->
            <div id="addExpForm" class="hidden"
                 style="background:var(--bg-input);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;">
                <form method="POST" action="perfil.php" data-envio-unico="Adicionando...">
                    <input type="hidden" name="action" value="add_experiencia">
    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="expTitulo">Título / Função *</label>
                            <input type="text" name="exp_titulo" id="expTitulo" class="form-control"
                                   placeholder="Ex: Estágio, Voluntariado" maxlength="150" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="expLocal">Local</label>
                            <input type="text" name="exp_local" id="expLocal" class="form-control"
                                   placeholder="Nome da organização" maxlength="150">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="expPeriodo">Período</label>
                            <input type="text" name="exp_periodo" id="expPeriodo" class="form-control"
                                   placeholder="Jan/2024 – Mar/2024" maxlength="50">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="expDescricao">Descrição</label>
                            <input type="text" name="exp_descricao" id="expDescricao" class="form-control"
                                   placeholder="Breve descrição das atividades">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Adicionar</button>
                </form>
            </div>

            <?php if (!empty($experiencias)): ?>
                <?php foreach ($experiencias as $e): ?>
                <div class="edu-item">
                    <div>
                        <p class="edu-title"><?= htmlspecialchars($e['titulo']) ?></p>
                        <p class="edu-sub">
                            <?= htmlspecialchars($e['local'] ?? '') ?>
                            <?= !empty($e['periodo']) ? ' • ' . htmlspecialchars($e['periodo']) : '' ?>
                        </p>
                    </div>
                    <form method="POST" action="perfil.php" style="display:inline;">
                        <input type="hidden" name="action" value="del_experiencia">
    <?= csrf_field() ?>
                        <input type="hidden" name="exp_id" value="<?= intval($e['id']) ?>">
                        <button type="submit" class="edu-delete" title="Remover experiência"
                                aria-label="Remover experiência: <?= htmlspecialchars($e['titulo']) ?>"><span class="i i-trash" aria-hidden="true"></span></button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    Adicione experiências informais, trabalhos voluntários ou cursos.
                </div>
            <?php endif; ?>
        </div>

        <!-- Habilidades -->
        <div class="profile-section fade-up" id="habilidades">
            <div class="profile-section-head">
                <h2 class="profile-section-title"><span class="i i-list-checks" aria-hidden="true"></span> Habilidades</h2>
                <button type="button" class="btn btn-ghost btn-sm"
                        onclick="toggleAddForm('addHabForm', this)"
                        title="Adicionar habilidade" aria-label="Adicionar habilidade"
                        aria-expanded="false" aria-controls="addHabForm"><span class="i i-plus" aria-hidden="true"></span></button>
            </div>

            <div id="addHabForm" class="hidden"
                 style="display:flex;gap:8px;margin-bottom:16px;">
                <form method="POST" action="perfil.php" data-envio-unico="Adicionando..."
                      style="display:flex;gap:8px;width:100%;">
                    <input type="hidden" name="action" value="add_habilidade">
    <?= csrf_field() ?>
                    <!-- Stage 3: escolha uma habilidade da lista ou digite uma personalizada -->
                    <input type="text" name="habilidade" class="form-control" aria-label="Nova habilidade"
                           list="habilidadesSugeridas" maxlength="80" autocomplete="off"
                           placeholder="Escolha da lista ou digite (ex: Excel Básico)" required>
                    <datalist id="habilidadesSugeridas">
                        <option value="Boa Comunicação">
                        <option value="Trabalho em Equipe">
                        <option value="Organização">
                        <option value="Pontualidade">
                        <option value="Proatividade">
                        <option value="Responsabilidade">
                        <option value="Atendimento ao Cliente">
                        <option value="Resolução de Problemas">
                        <option value="Informática Básica">
                        <option value="Excel Básico">
                        <option value="Word">
                        <option value="PowerPoint">
                        <option value="Canva">
                        <option value="Inglês Básico">
                        <option value="Espanhol Básico">
                        <option value="Digitação">
                    </datalist>
                    <button type="submit" class="btn btn-primary btn-sm"
                            style="white-space:nowrap;">Adicionar</button>
                </form>
            </div>

            <div class="skills-wrap">
                <?php if (!empty($habilidades)): ?>
                    <?php foreach ($habilidades as $h): ?>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span class="skill-chip"><?= htmlspecialchars($h['habilidade']) ?></span>
                        <form method="POST" action="perfil.php" style="display:inline;">
                            <input type="hidden" name="action" value="del_habilidade">
    <?= csrf_field() ?>
                            <input type="hidden" name="hab_id" value="<?= intval($h['id']) ?>">
                            <button type="submit"
                                    class="text-muted"
                                    style="font-size:12px;background:none;border:none;cursor:pointer;"
                                    title="Remover habilidade"
                                    aria-label="Remover habilidade: <?= htmlspecialchars($h['habilidade']) ?>"><span class="i i-x" aria-hidden="true"></span></button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-muted" style="font-size:13px;">
                        Nenhuma habilidade adicionada ainda.
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Configurações de Visibilidade -->
        <div class="profile-section fade-up">
            <div class="profile-section-head">
                <h2 class="profile-section-title"><span class="i i-settings" aria-hidden="true"></span> Configurações de Visibilidade</h2>
            </div>
            <p class="text-secondary" style="font-size:13px;margin-bottom:16px;">
                Como as empresas veem seu perfil
            </p>
            <div class="visibility-toggle-item">
                <div class="vt-text">
                    <strong>Perfil Público</strong>
                    <span>Permitir que empresas encontrem seu currículo nas buscas.</span>
                </div>
                <label class="toggle">
                    <input type="checkbox" name="perfil_publico" value="1" form="formPerfil"
                           aria-label="Perfil público"
                           <?= $jovem['perfil_publico'] ? 'checked' : '' ?>>
                    <span class="toggle-track"></span>
                </label>
            </div>
            <div style="margin-top:16px;">
                <button type="submit" form="formPerfil" class="btn btn-primary btn-sm">Salvar Visibilidade</button>
            </div>
        </div>

    </main><!-- /.profile-main -->

</div><!-- /.profile-page -->

<?php else: ?>
<!-- ============================================================
     VIEW 2: EMPRESA VENDO O CANDIDATO (read-only)
     ============================================================ -->
<div class="profile-page">

    <!-- ── SIDEBAR (info do jovem) ───────────────────────────────────────── -->
    <aside class="profile-side">

        <div class="profile-side-avatar">
            <?php if (!empty($jovem['area_interesse'])): ?>
            <span class="badge"><?= htmlspecialchars($jovem['area_interesse']) ?></span>
            <?php endif; ?>
            <div class="avatar avatar-xl"><?= $inicial ?></div>
            <p class="profile-side-name"><?= $nome_jovem ?></p>
            <p class="profile-side-role">
                <span class="i i-calendar" aria-hidden="true"></span> <?= $idade_label ?><?= ($idade_label && $localizacao) ? ' • ' : '' ?><?= $localizacao ?>
            </p>
        </div>

        <!-- Resumo Profissional -->
        <?php if (!empty($jovem['resumo_profissional'])): ?>
        <div class="profile-info-item">
            <p class="profile-info-label"><span class="i i-user" aria-hidden="true"></span> Resumo Profissional</p>
            <p style="font-size:13px;color:var(--text-secondary);line-height:1.65;">
                <?= htmlspecialchars($jovem['resumo_profissional']) ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Stage 3: escolaridade do cadastro também visível para a empresa -->
        <div class="profile-info-item">
            <p class="profile-info-label"><span class="i i-graduation-cap" aria-hidden="true"></span> Escolaridade</p>
            <p style="font-size:13px;color:var(--text-secondary);"><?= htmlspecialchars($esc_resumo) ?></p>
        </div>

        <!-- Formação Acadêmica -->
        <?php if (!empty($formacoes)): ?>
        <div class="profile-info-item">
            <p class="profile-info-label"><span class="i i-graduation-cap" aria-hidden="true"></span> Formação Acadêmica</p>
            <?php foreach ($formacoes as $f): ?>
            <div class="edu-item" style="margin-bottom:8px;">
                <div>
                    <p class="edu-title"><?= htmlspecialchars($f['titulo']) ?></p>
                    <p class="edu-sub"><?= jc_formacao_detalhe($f) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Habilidades -->
        <?php if (!empty($habilidades)): ?>
        <div class="profile-info-item">
            <p class="profile-info-label"><span class="i i-list-checks" aria-hidden="true"></span> Habilidades</p>
            <div class="skills-wrap" style="margin-top:8px;">
                <?php foreach ($habilidades as $h): ?>
                <span class="skill-chip"><?= htmlspecialchars($h['habilidade']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </aside>

    <!-- ── PAINEL DE AÇÕES (empresa) ─────────────────────────────────────── -->
    <main class="profile-main">

        <!-- Ações -->
        <div class="profile-section fade-up">
            <div class="profile-section-head">
                <h2 class="profile-section-title">Ações</h2>
            </div>
            <p class="text-secondary" style="font-size:13px;margin-bottom:16px;">
                Gerencie o recrutamento
            </p>

            <!-- Currículo PDF -->
            <div class="pdf-card" style="margin-bottom:12px;">
                <div class="pdf-icon"><span class="i i-file-text" aria-hidden="true"></span></div>
                <p class="pdf-title">Currículo em PDF</p>
                <?php if ($tem_curriculo): ?>
                <a href="<?= $link_curriculo ?>"
                   target="_blank" rel="noopener"
                   class="btn btn-ghost btn-sm btn-full"
                   style="margin-top:10px;">
                    Ver documento
                </a>
                <?php else: ?>
                <p class="text-muted" style="font-size:12px;margin-top:10px;">
                    Este candidato ainda não enviou um currículo.
                </p>
                <?php endif; ?>
            </div>

            <!-- Agendar entrevista -->
            <a href="mailto:<?= htmlspecialchars($jovem['email']) ?>?subject=Entrevista JovemConnect"
               class="btn btn-accent-outline btn-full"
               style="margin-bottom:10px;">
                <span class="i i-calendar" aria-hidden="true"></span> Agendar Entrevista
            </a>

            <!-- Stage 4: "Salvar nos Favoritos" enviava para Vagas/favoritar.php, que é o
                 favorito de VAGAS do jovem — a empresa caía na tela de login do jovem.
                 Favoritar candidatos ainda não existe (pendência): botão desativado. -->
            <button type="button" class="btn btn-secondary btn-full" disabled
                    aria-describedby="favCandidatoInfo">
                Salvar candidato (em breve)
            </button>
            <p id="favCandidatoInfo" class="text-muted" style="font-size:12px;margin-top:6px;text-align:center;">
                Salvar candidatos nos favoritos ainda não está disponível.
            </p>
        </div>

        <!-- IA Dica -->
        <div class="ia-dica-card fade-up" style="margin-top:0;">
            <span class="ia-dica-icon i i-sparkles" aria-hidden="true"></span>
            <p class="ia-dica-title">IA Dica</p>
            <p class="ia-dica-text" id="iaDicaText">
                <?php if (!empty($jovem['escolaridade_tipo']) && $jovem['escolaridade_tipo'] === 'tecnico'): ?>
                    Este candidato possui formação técnica na área que você procura.
                    Recomendamos o contato imediato!
                <?php else: ?>
                    Clique em "Avaliar Match" no dashboard para ver a análise completa
                    de compatibilidade deste candidato.
                <?php endif; ?>
            </p>
        </div>

    </main>

</div>
<?php endif; ?>


<!-- ============================================================
     FABs + Assistente + Acessibilidade
     Stage 3: antes os botões ✦/♿ existiam sem painel nem script.
     ============================================================ -->
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>

<!-- Modal de exclusão (só aparece para o dono) -->
<?php if ($eh_dono): ?>
<div id="modalExcluir" class="jc-modal" role="dialog" aria-modal="true" aria-labelledby="modalExcluirTitulo">
    <div class="card" style="max-width:400px;width:100%;text-align:center;padding:36px 28px;">
        <!-- Stage 5: fechar pelo × (antes só Cancelar, Esc ou clique fora) -->
        <button type="button" class="jc-modal-fechar" aria-label="Fechar" onclick="fecharModalExcluir()"><span class="i i-x" aria-hidden="true"></span></button>
        <div class="icone-destaque aviso" aria-hidden="true"><span class="i i-alert-triangle" aria-hidden="true"></span></div>
        <h3 id="modalExcluirTitulo" style="font-weight:700;margin-bottom:10px;">Excluir Conta</h3>
        <p class="text-secondary" style="font-size:14px;margin-bottom:24px;">
            Esta ação é <strong>irreversível</strong>. Todos os seus dados, candidaturas
            e currículo serão permanentemente apagados.
        </p>
        <form method="POST" action="excluir_conta.php">
            <input type="hidden" name="confirmar" value="1">
    <?= csrf_field() ?>
            <div class="d-flex gap-12" style="justify-content:center;">
                <button type="button" class="btn btn-secondary" id="btnCancelarExcluir">Cancelar</button>
                <button type="submit" class="btn btn-danger">Excluir Permanentemente</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script>
/* ── Tema ──────────────────────────────────────────────────── */
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

/* ── Toast ─────────────────────────────────────────────────── */
const toast = document.getElementById('toast');
if (toast) {
    // Stage 4: mensagens de erro ficam mais tempo (antes sumiam em 3,5 s) e são anunciadas:
    // o texto volta um instante depois do role — região "viva" que já nasce com texto não é lida
    const ehErro = toast.classList.contains('toast-error');
    const conteudo = [...toast.childNodes];   // Stage 5: ícone + texto (antes só o texto)
    toast.replaceChildren();
    toast.setAttribute('role', ehErro ? 'alert' : 'status');
    setTimeout(() => { toast.replaceChildren(...conteudo); toast.classList.add('toast-visible'); }, 100);
    setTimeout(() => toast.classList.remove('toast-visible'), ehErro ? 7000 : 4000);
}

/* ── Toggle sidebar edit mode ──────────────────────────────── */
const btnEditar = document.getElementById('btnEditar');
let editando = false;
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
    }
    ['viewNome','editNome','viewDados','editDados'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const isView = id.startsWith('view');
        el.classList.toggle('hidden', editando ? isView : !isView);
    });
    btnEditar.textContent = editando ? 'Cancelar' : 'Editar Dados';
    btnEditar.className   = editando
        ? 'btn btn-ghost btn-sm'
        : 'btn btn-secondary btn-sm';
});

/* ── Toggle formulários de adição ──────────────────────────── */
function toggleAddForm(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('hidden');
    // Se estava oculto e usava display:flex, restaura
    if (!el.classList.contains('hidden') && el.id === 'addHabForm') {
        el.style.display = 'flex';
    }
    // Stage 4: o leitor de tela sabe se abriu/fechou e o foco vai para o 1º campo
    const aberto = !el.classList.contains('hidden');
    btn?.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    if (aberto) el.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
}

/* ── Modal de exclusão ─────────────────────────────────────── */
const modal       = document.getElementById('modalExcluir');
const btnExcluir  = document.getElementById('btnExcluir');
const btnCancelar = document.getElementById('btnCancelarExcluir');
// Stage 4: o foco vai para "Cancelar" ao abrir e volta para "Excluir Conta" ao
// fechar; Esc também fecha (antes só o clique fora ou em "Cancelar")
function abrirModalExcluir()  { modal.style.display = 'flex'; btnCancelar?.focus(); }
function fecharModalExcluir() { modal.style.display = 'none'; btnExcluir?.focus(); }
btnExcluir?.addEventListener('click',  abrirModalExcluir);
btnCancelar?.addEventListener('click', fecharModalExcluir);
modal?.addEventListener('click', e => { if (e.target === modal) fecharModalExcluir(); });
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal?.style.display === 'flex') fecharModalExcluir();
});

/* ── IA Advisor (análise de currículo) ─────────────────────── */
document.getElementById('btnAnalisarPerfil')?.addEventListener('click', async function () {
    this.disabled  = true;
    this.innerHTML = '<span class="loading-spinner"></span> Analisando...';

    const resultEl = document.getElementById('iaAdvisorResult');

    try {
        const res = await fetch('../IA/avaliar_ajax.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'curriculo', jovem_id: <?= $jovem_id ?> })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const { mensagem, sugestoes } = await res.json();

        let html = `<p style="font-size:13px;font-weight:600;color:var(--accent);margin-bottom:10px;">${mensagem}</p>`;
        if (sugestoes?.length) {
            html += '<ul style="margin:0;padding-left:16px;">';
            sugestoes.forEach(s => {
                html += `<li style="font-size:12px;color:var(--text-secondary);margin-bottom:6px;">${s}</li>`;
            });
            html += '</ul>';
        }

        resultEl.innerHTML = html;
        resultEl.classList.remove('hidden');

    } catch {
        resultEl.innerHTML = '<p style="font-size:12px;color:var(--text-muted);">IA temporariamente indisponível.</p>';
        resultEl.classList.remove('hidden');
    } finally {
        this.disabled  = false;
        this.innerHTML = 'Analisar meu Perfil';
    }
});
</script>

</body>
</html>