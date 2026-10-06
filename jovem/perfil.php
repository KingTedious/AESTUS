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
        if (isset($_POST['escolaridade_status'])) {
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
$esc_resumo = ($jovem['escolaridade_status'] === 'completo') ? 'Concluído' : 'Cursando';
if ($jovem['escolaridade_tipo'] === 'tecnico') {
    $esc_resumo .= ' • Curso técnico' . (!empty($jovem['escolaridade_curso']) ? ': ' . $jovem['escolaridade_curso'] : '');
}
if (!empty($jovem['formacao'])) {
    $esc_resumo .= ' • ' . $jovem['formacao'];
}

// Stage 3: currículo em PDF — só mostra link se o arquivo existir de fato
$tem_curriculo = !empty($jovem['curriculo'])
    && is_file(dirname(__DIR__) . '/uploads/curriculos/' . basename($jovem['curriculo']));
$link_curriculo = '../uploads/ver_curriculo.php?id=' . $jovem_id;

// Stage 3: mensagens gravadas por uploads/curriculos.php (antes nunca exibidas)
$msg_upload_ok   = $eh_dono ? ($_SESSION['sucesso_upload'] ?? null) : null;
$msg_upload_erro = $eh_dono ? ($_SESSION['erro_upload']    ?? null) : null;
unset($_SESSION['sucesso_upload'], $_SESSION['erro_upload']);
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
</head>
<body>
<?= csrf_js() ?><!-- Stage 3: o IA Advisor usa fetch() em endpoint protegido por CSRF (antes: 403) -->

<!-- ============================================================
     BARRA DE TOPO
     ============================================================ -->
<div class="profile-bar">
    <div class="d-flex align-center gap-12">
        <a href="<?= $eh_empresa ? '../dashboard/empresa.php' : '../dashboard/jovem.php' ?>"
           class="icon-btn" title="Voltar">←</a>
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
            ✈ Entrar em Contato
        </a>
    <?php endif; ?>
</div>

<!-- Toast de feedback (PRG) -->
<?php if ($msg_upload_ok): ?>
<div id="toast" class="toast toast-success">✓ <?= htmlspecialchars($msg_upload_ok) ?></div>
<?php elseif ($msg_upload_erro): ?>
<div id="toast" class="toast toast-error">✕ <?= htmlspecialchars($msg_upload_erro) ?></div>
<?php elseif ($saved === 1): ?>
<div id="toast" class="toast toast-success">✓ Perfil atualizado com sucesso!</div>
<?php elseif ($saved === 2): ?>
<div id="toast" class="toast toast-error">✕ Preencha o nome e um e-mail válido antes de salvar.</div>
<?php elseif ($saved === 3): ?>
<div id="toast" class="toast toast-error">✕ Este e-mail já está em uso por outra conta.</div>
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
                <p class="profile-side-role">📍 Jovem Aprendiz</p>
            </div>

            <!-- EDIT (oculto por padrão) -->
            <div id="editNome" class="hidden w-full">
                <div class="form-group" style="width:100%">
                    <label class="form-label">Nome Completo *</label>
                    <input type="text" name="nome" class="form-control" form="formPerfil"
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
                <p class="profile-info-value">📍 <?= $localizacao ?: '—' ?></p>
            </div>
        </div>

        <!-- Dados básicos EDIT -->
        <div id="editDados" class="hidden">
            <div class="form-group">
                <label class="form-label">E-mail *</label>
                <input type="email" name="email" class="form-control" form="formPerfil"
                       value="<?= htmlspecialchars($jovem['email']) ?>" maxlength="100" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade" class="form-control" form="formPerfil"
                           value="<?= htmlspecialchars($jovem['cidade'] ?? '') ?>" maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">UF</label>
                    <input type="text" name="estado" class="form-control" form="formPerfil"
                           value="<?= htmlspecialchars($jovem['estado'] ?? '') ?>"
                           maxlength="2" placeholder="SP">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">CEP</label>
                <input type="text" name="cep" class="form-control" form="formPerfil"
                       value="<?= htmlspecialchars($jovem['cep'] ?? '') ?>"
                       placeholder="00000-000" maxlength="9">
            </div>
            <div class="form-group">
                <label class="form-label">Área de Interesse</label>
                <input type="text" name="area_interesse" class="form-control" form="formPerfil"
                       value="<?= htmlspecialchars($jovem['area_interesse'] ?? '') ?>"
                       placeholder="Ex: Administrativo, TI" maxlength="100">
            </div>
            <!-- Stage 3: escolaridade editável (antes só existia no cadastro) -->
            <div class="form-group">
                <label class="form-label">Escolaridade</label>
                <select name="escolaridade_status" class="form-control" form="formPerfil">
                    <option value="cursando" <?= $jovem['escolaridade_status'] !== 'completo' ? 'selected' : '' ?>>Cursando</option>
                    <option value="completo" <?= $jovem['escolaridade_status'] === 'completo' ? 'selected' : '' ?>>Concluído</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Ano Escolar / Conclusão</label>
                <input type="text" name="formacao" class="form-control" form="formPerfil"
                       value="<?= htmlspecialchars($jovem['formacao'] ?? '') ?>"
                       placeholder="Ex: 3º ano Ensino Médio" maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="escolaridade_tipo" value="tecnico" form="formPerfil"
                           <?= $jovem['escolaridade_tipo'] === 'tecnico' ? 'checked' : '' ?>>
                    <span class="form-check-label">Possuo curso técnico</span>
                </label>
                <input type="text" name="escolaridade_curso" class="form-control mt-8" form="formPerfil"
                       value="<?= htmlspecialchars($jovem['escolaridade_curso'] ?? '') ?>"
                       placeholder="Nome do curso técnico (ex: Administração)" maxlength="100">
            </div>
            <button type="submit" form="formPerfil" class="btn btn-primary btn-full btn-sm mt-8">
                Salvar Dados Básicos
            </button>
        </div>

        <!-- IA Advisor -->
        <div class="ia-advisor-card">
            <p class="ia-advisor-title">✦ IA Advisor</p>
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
                <h2 class="profile-section-title">📄 Currículo Automático</h2>
                <?php if ($tem_curriculo): ?>
                <a href="<?= $link_curriculo ?>"
                   target="_blank" rel="noopener"
                   class="btn btn-secondary btn-sm"
                   id="btnBaixarPDF">
                    ⬇ Baixar PDF
                </a>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Objetivo e Resumo Profissional</label>
                <textarea name="resumo_profissional" class="form-control" form="formPerfil"
                          rows="5"
                          placeholder="Escreva um resumo profissional claro: seus objetivos, área de interesse e diferenciais..."
                          ><?= htmlspecialchars($jovem['resumo_profissional'] ?? '') ?></textarea>
            </div>

            <button type="submit" form="formPerfil" class="btn btn-primary btn-sm">Salvar Resumo</button>

            <!-- Stage 3: envio do currículo em PDF. uploads/curriculos.php já existia
                 (com validação de tipo/tamanho), mas nenhuma tela o usava. -->
            <div class="form-group" id="curriculo" style="margin-top:20px;">
                <label class="form-label">Currículo em PDF</label>
                <p class="text-secondary" style="font-size:13px;margin-bottom:8px;">
                    <?php if ($tem_curriculo): ?>
                        ✓ Currículo enviado.
                        <a href="<?= $link_curriculo ?>" target="_blank" rel="noopener">Ver PDF</a>
                        — envie outro arquivo para substituir.
                    <?php else: ?>
                        Nenhum currículo enviado ainda.
                    <?php endif; ?>
                </p>
                <form method="POST" action="../uploads/curriculos.php" enctype="multipart/form-data"
                      style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                    <input type="file" name="curriculo" accept="application/pdf,.pdf" required
                           class="form-control" style="flex:1;min-width:200px;">
                    <button type="submit" class="btn btn-secondary btn-sm">Enviar PDF</button>
                </form>
                <p class="text-muted" style="font-size:11px;margin-top:6px;">Somente PDF, até 5 MB.</p>
            </div>
        </div>

        <!-- Formação Acadêmica -->
        <div class="profile-section fade-up" id="formacao">
            <div class="profile-section-head">
                <h2 class="profile-section-title">🎓 Formação</h2>
                <button type="button" class="btn btn-ghost btn-sm"
                        onclick="toggleAddForm('addFormacaoForm')">+</button>
            </div>

            <!-- Form de adição (oculto) -->
            <div id="addFormacaoForm" class="hidden"
                 style="background:var(--bg-input);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;">
                <form method="POST" action="perfil.php">
                    <input type="hidden" name="action" value="add_formacao">
    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Curso / Nível *</label>
                            <input type="text" name="form_titulo" class="form-control"
                                   placeholder="Ex: Ensino Médio, Técnico em Adm" maxlength="150" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Instituição</label>
                            <input type="text" name="form_instituicao" class="form-control"
                                   placeholder="Nome da escola" maxlength="150">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Conclusão</label>
                            <input type="text" name="form_conclusao" class="form-control"
                                   placeholder="Ex: 12/2025 ou Em andamento" maxlength="20">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Área (técnico)</label>
                            <input type="text" name="form_area" class="form-control"
                                   placeholder="Ex: Administrativo" maxlength="100">
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
                        <p class="edu-sub">
                            <?= htmlspecialchars($f['instituicao'] ?? '') ?>
                            <?= !empty($f['conclusao']) ? ' • Conclusão: ' . htmlspecialchars($f['conclusao']) : '' ?>
                            <?= !empty($f['area'])      ? ' • Área: ' . htmlspecialchars($f['area']) : '' ?>
                        </p>
                    </div>
                    <form method="POST" action="perfil.php" style="display:inline;">
                        <input type="hidden" name="action"  value="del_formacao">
    <?= csrf_field() ?>
                        <input type="hidden" name="form_id" value="<?= intval($f['id']) ?>">
                        <button type="submit" class="edu-delete" title="Remover">🗑</button>
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
                <h2 class="profile-section-title">💼 Experiência (Opcional)</h2>
                <button type="button" class="btn btn-ghost btn-sm"
                        onclick="toggleAddForm('addExpForm')">+</button>
            </div>

            <!-- Form de adição (oculto) -->
            <div id="addExpForm" class="hidden"
                 style="background:var(--bg-input);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;">
                <form method="POST" action="perfil.php">
                    <input type="hidden" name="action" value="add_experiencia">
    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Título / Função *</label>
                            <input type="text" name="exp_titulo" class="form-control"
                                   placeholder="Ex: Estágio, Voluntariado" maxlength="150" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Local</label>
                            <input type="text" name="exp_local" class="form-control"
                                   placeholder="Nome da organização" maxlength="150">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Período</label>
                            <input type="text" name="exp_periodo" class="form-control"
                                   placeholder="Jan/2024 – Mar/2024" maxlength="50">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="exp_descricao" class="form-control"
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
                        <button type="submit" class="edu-delete" title="Remover">🗑</button>
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
                <h2 class="profile-section-title">✦ Habilidades</h2>
                <button type="button" class="btn btn-ghost btn-sm"
                        onclick="toggleAddForm('addHabForm')">+</button>
            </div>

            <div id="addHabForm" class="hidden"
                 style="display:flex;gap:8px;margin-bottom:16px;">
                <form method="POST" action="perfil.php"
                      style="display:flex;gap:8px;width:100%;">
                    <input type="hidden" name="action" value="add_habilidade">
    <?= csrf_field() ?>
                    <!-- Stage 3: escolha uma habilidade da lista ou digite uma personalizada -->
                    <input type="text" name="habilidade" class="form-control"
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
                                    title="Remover">✕</button>
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
                <h2 class="profile-section-title">⚙ Configurações de Visibilidade</h2>
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
                📅 <?= $idade_label ?><?= ($idade_label && $localizacao) ? ' • ' : '' ?><?= $localizacao ?>
            </p>
        </div>

        <!-- Resumo Profissional -->
        <?php if (!empty($jovem['resumo_profissional'])): ?>
        <div class="profile-info-item">
            <p class="profile-info-label">👤 Resumo Profissional</p>
            <p style="font-size:13px;color:var(--text-secondary);line-height:1.65;">
                <?= htmlspecialchars($jovem['resumo_profissional']) ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Stage 3: escolaridade do cadastro também visível para a empresa -->
        <div class="profile-info-item">
            <p class="profile-info-label">🎓 Escolaridade</p>
            <p style="font-size:13px;color:var(--text-secondary);"><?= htmlspecialchars($esc_resumo) ?></p>
        </div>

        <!-- Formação Acadêmica -->
        <?php if (!empty($formacoes)): ?>
        <div class="profile-info-item">
            <p class="profile-info-label">🎓 Formação Acadêmica</p>
            <?php foreach ($formacoes as $f): ?>
            <div class="edu-item" style="margin-bottom:8px;">
                <div>
                    <p class="edu-title"><?= htmlspecialchars($f['titulo']) ?></p>
                    <p class="edu-sub">
                        <?= htmlspecialchars($f['instituicao'] ?? '') ?>
                        <?= !empty($f['conclusao']) ? ' • Conclusão: ' . htmlspecialchars($f['conclusao']) : '' ?>
                        <?= !empty($f['area'])      ? ' • Área: ' . htmlspecialchars($f['area']) : '' ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Habilidades -->
        <?php if (!empty($habilidades)): ?>
        <div class="profile-info-item">
            <p class="profile-info-label">✦ Habilidades</p>
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
                <div class="pdf-icon">📄</div>
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
                ✈ Agendar Entrevista
            </a>

            <!-- Salvar nos favoritos -->
            <form method="POST" action="../Vagas/favoritar.php">
                <input type="hidden" name="jovem_id" value="<?= $jovem_id ?>">
                <button type="submit" class="btn btn-secondary btn-full">
                    Salvar nos Favoritos
                </button>
            </form>
        </div>

        <!-- IA Dica -->
        <div class="ia-dica-card fade-up" style="margin-top:0;">
            <span class="ia-dica-icon">✦</span>
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
<div id="modalExcluir"
     style="position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:2000;
            display:none;align-items:center;justify-content:center;">
    <div class="card" style="max-width:380px;width:90%;text-align:center;padding:32px 24px;">
        <p style="font-size:36px;margin-bottom:12px;">⚠️</p>
        <h3 style="font-weight:700;margin-bottom:10px;">Excluir Conta</h3>
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
    setTimeout(() => toast.classList.add('toast-visible'), 100);
    setTimeout(() => toast.classList.remove('toast-visible'), 3500);
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
function toggleAddForm(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('hidden');
    // Se estava oculto e usava display:flex, restaura
    if (!el.classList.contains('hidden') && el.id === 'addHabForm') {
        el.style.display = 'flex';
    }
}

/* ── Modal de exclusão ─────────────────────────────────────── */
const modal       = document.getElementById('modalExcluir');
const btnExcluir  = document.getElementById('btnExcluir');
const btnCancelar = document.getElementById('btnCancelarExcluir');
btnExcluir?.addEventListener('click',  () => modal.style.display = 'flex');
btnCancelar?.addEventListener('click', () => modal.style.display = 'none');
modal?.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

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