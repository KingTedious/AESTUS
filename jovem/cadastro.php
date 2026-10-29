<?php
/* ============================================================
   jovem/cadastro.php
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

// Já logado → redireciona direto
if (isset($_SESSION['jovem_id'])) {
    header("Location: ../dashboard/jovem.php");
    exit;
}
// Empresa logada também vai para o painel dela (antes via o formulário
// e, ao enviar, a sessão ficava com jovem E empresa ao mesmo tempo)
if (isset($_SESSION['empresa_id'])) {
    header("Location: ../dashboard/empresa.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/formacao.php";   // nível → curso/ano → situação
require_once dirname(__DIR__) . "/includes/termos.php";     // versão e registro do aceite
require_once dirname(__DIR__) . "/includes/etapas.php";     // formulário em etapas
require_once dirname(__DIR__) . "/includes/moderacao.php";  // Stage 7: validação + moderação centrais

$erros  = [];   // chave = nome do campo (mensagem aparece perto dele); sem chave = erro geral
$campos = [];
$form_formacao = null;
$habilidades_txt = '';
$sinais_mod = [];   // Stage 7: sinais de moderação (registrados na fila depois de gravar)

/* Em que etapa fica cada campo (a página reabre na etapa do primeiro erro). */
$etapa_dos_campos = [
    'nome' => 1, 'cpf' => 1, 'data_nascimento' => 1, 'email' => 1, 'senha' => 1, 'confirmar_senha' => 1,
    '*' => 2,   // erros da formação (vêm de includes/formacao.php sem nome de campo)
    'area_interesse' => 3, 'resumo_profissional' => 3, 'habilidades' => 3,
    'cep' => 4, 'aceite_termos' => 4, 'geral' => 4,
];

/* ============================================================
   PROCESSAMENTO POST (todo PHP antes de qualquer output HTML)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // ── Leitura e limpeza (limites = tamanho das colunas) ─────────────────────
    $campos = [
        'nome'     => mb_substr(trim($_POST['nome']            ?? ''), 0, 100),
        'email'    => trim($_POST['email']           ?? ''),
        'cpf'      => trim($_POST['cpf']             ?? ''),
        'data_nasc'=> trim($_POST['data_nascimento'] ?? ''),
        'cep'      => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
        'cidade'   => mb_substr(trim($_POST['cidade'] ?? ''), 0, 100),
        'estado'   => mb_substr(strtoupper(trim($_POST['estado'] ?? '')), 0, 2),
        'formacao' => trim($_POST['formacao']        ?? ''),
        // etapa 3 (opcional): perfil profissional
        'area_interesse'      => mb_substr(trim((string) ($_POST['area_interesse'] ?? '')), 0, 100),
        'resumo_profissional' => mb_substr(trim((string) ($_POST['resumo_profissional'] ?? '')), 0, 1000),
    ];
    // Sem JavaScript, cidade/UF chegam juntas em "Cidade - UF" (cidade_estado)
    if ($campos['cidade'] === '' && is_string($_POST['cidade_estado'] ?? null)) {
        $partes = array_map('trim', explode('-', $_POST['cidade_estado']));
        if (count($partes) >= 2 && preg_match('/^[A-Za-z]{2}$/', end($partes))) {
            $campos['estado'] = strtoupper(array_pop($partes));
        }
        $campos['cidade'] = mb_substr(trim(implode('-', $partes)), 0, 100);
    }
    $habilidades_txt = mb_substr(trim((string) ($_POST['habilidades'] ?? '')), 0, 1500);
    $aceite = ($_POST['aceite_termos'] ?? '') === '1';
    $campos['aceite_termos'] = $aceite;

    // Situação da escolaridade: só os valores do banco (ENUM)
    $esc_status = in_array($_POST['escolaridade_status'] ?? '', ['cursando', 'completo'], true)
                  ? $_POST['escolaridade_status']
                  : 'cursando';
    $esc_tipo   = (($_POST['escolaridade_tipo'] ?? '') === 'tecnico') ? 'tecnico' : 'regular';
    $clt        = isset($_POST['clt']) ? 1 : 0;
    $campos['escolaridade_status'] = $esc_status;
    $campos['escolaridade_tipo']   = $esc_tipo;
    $campos['clt']                 = $clt;

    $senha      = $_POST['senha']           ?? '';
    $conf_senha = $_POST['confirmar_senha'] ?? '';

    // Curso técnico no formato antigo do formulário (sem "formacao_nivel")
    $esc_curso = null;
    if ($esc_tipo === 'tecnico') {
        $pred      = trim($_POST['curso_predefinido'] ?? '');
        $esc_curso = mb_substr(($pred === 'Outro') ? trim($_POST['curso_outro'] ?? '') : $pred, 0, 100);
        if (empty($esc_curso)) $erros[] = 'Informe o nome do curso técnico.';
    }

    // Formação no formato atual: nível → curso/ano → situação (includes/formacao.php)
    $form_formacao = jc_formacao_ler_post($_POST, $erros, '', 'escolaridade_status', true, true);
    if ($form_formacao !== null) {
        $campos['formacao'] = $form_formacao['rotulo'];
        $esc_status = $form_formacao['situacao'];
        $esc_tipo   = $form_formacao['tipo'];
        $esc_curso  = ($form_formacao['curso_tecnico'] !== '') ? $form_formacao['curso_tecnico'] : null;
        $campos['escolaridade_status'] = $esc_status;
        $campos['escolaridade_tipo']   = $esc_tipo;
    }

    // Habilidades: separadas por vírgula, sem repetir, até 15 (80 caracteres cada)
    $habilidades = [];
    foreach (preg_split('/[,;\n]+/', $habilidades_txt) ?: [] as $h) {
        $h = mb_substr(trim($h), 0, 80);
        if ($h !== '' && !in_array(mb_strtolower($h), array_map('mb_strtolower', $habilidades), true)) {
            $habilidades[] = $h;
        }
    }
    if (count($habilidades) > 15) {
        $erros['habilidades'] = 'Informe no máximo 15 habilidades (você pode adicionar outras depois, no perfil).';
    }

    // ── Stage 7: validação estrutural + moderação (includes/moderacao.php) ────
    // Os limites de tamanho continuam sendo aplicados como antes (corte acima);
    // aqui entram codificação, caracteres de controle/invisíveis, formato do nome,
    // código HTML/JavaScript executável (recusado) e sinais para a fila.
    $mod = jc_mod_formulario([
        'jovem.nome'           => [$campos['nome'], 'nome'],
        'jovem.cidade'         => [$campos['cidade'], 'cidade'],
        'jovem.area_interesse' => [$campos['area_interesse'], 'area_interesse'],
        'jovem.resumo'         => [$campos['resumo_profissional'], 'resumo_profissional'],
    ]);
    foreach ($mod['erros'] as $campo_form => $msg) {
        $erros[$campo_form === 'cidade' ? 'cep' : $campo_form] = $msg;   // cidade vem do CEP (etapa 4)
    }
    foreach (['jovem.nome' => 'nome', 'jovem.cidade' => 'cidade', 'jovem.area_interesse' => 'area_interesse',
              'jovem.resumo' => 'resumo_profissional'] as $chave => $campo) {
        if (isset($mod['valores'][$chave])) {
            $campos[$campo] = $mod['valores'][$chave];
        }
    }
    $mod_hab = jc_mod_lista('jovem.habilidade', $habilidades);
    if ($mod_hab['erro'] !== null) {
        $erros['habilidades'] = $mod_hab['erro'];
    }
    $habilidades = [];
    foreach ($mod_hab['valores'] as $h) {   // normalizado (acentos em NFC, sem invisíveis): sem repetir
        if (!in_array(mb_strtolower($h), array_map('mb_strtolower', $habilidades), true)) {
            $habilidades[] = $h;
        }
    }
    $sinais_mod = $mod['sinais'] + ($mod_hab['sinal'] ? ['jovem.habilidade' => $mod_hab['sinal']] : [])
                + ($form_formacao['sinais'] ?? []);

    // ── Validações ────────────────────────────────────────────────────────────
    if ($campos['nome'] === '')
        $erros['nome'] = 'Informe seu nome completo.';

    if ($campos['email'] === '')
        $erros['email'] = 'Informe seu e-mail.';
    elseif (!filter_var($campos['email'], FILTER_VALIDATE_EMAIL))
        $erros['email'] = 'Formato de e-mail inválido.';
    elseif (mb_strlen($campos['email']) > 100)
        $erros['email'] = 'E-mail muito longo (máximo 100 caracteres).';

    $cpf_num = preg_replace('/\D/', '', $campos['cpf']);
    if (strlen($cpf_num) !== 11)
        $erros['cpf'] = 'CPF inválido (deve ter 11 dígitos).';
    else   // mesmo formato da máscara do campo: 000.000.000-00 (cabe na coluna de 14 caracteres)
        $campos['cpf'] = substr($cpf_num, 0, 3) . '.' . substr($cpf_num, 3, 3) . '.' . substr($cpf_num, 6, 3) . '-' . substr($cpf_num, 9, 2);

    if ($campos['data_nasc'] === '')
        $erros['data_nascimento'] = 'Informe sua data de nascimento.';
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $campos['data_nasc']) || strtotime($campos['data_nasc']) === false
            || $campos['data_nasc'] > date('Y-m-d'))
        $erros['data_nascimento'] = 'Data de nascimento inválida.';

    if ($senha === '')
        $erros['senha'] = 'Crie uma senha.';
    elseif (strlen($senha) < 8)
        $erros['senha'] = 'A senha deve ter pelo menos 8 caracteres.';
    elseif ($senha !== $conf_senha)
        $erros['confirmar_senha'] = 'As senhas não coincidem.';

    if ($campos['cep'] !== '' && strlen($campos['cep']) !== 8)
        $erros['cep'] = 'CEP inválido (deve ter 8 dígitos).';

    if (!$aceite)
        $erros['aceite_termos'] = 'Para criar a conta, leia e aceite os Termos de Uso e a Política de Privacidade.';

    // ── Duplicidade de conta (e-mail ou CPF já usados) ────────────────────────
    if (empty($erros)) {
        // CPF com máscara ou só números (contas antigas podem ter qualquer um dos dois)
        $stmt_ck = $conn->prepare("SELECT id FROM jovens WHERE email = ? OR cpf = ? OR cpf = ? LIMIT 1");
        $stmt_ck->bind_param("sss", $campos['email'], $campos['cpf'], $cpf_num);
        $stmt_ck->execute();
        $stmt_ck->store_result();
        if ($stmt_ck->num_rows > 0)
            $erros['email'] = 'E-mail ou CPF já cadastrado na plataforma. Se a conta é sua, faça login ou recupere a senha.';
        $stmt_ck->close();
    }

    // ── Inserção (uma transação: conta + habilidades) ─────────────────────────
    if (empty($erros)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $area   = $campos['area_interesse'] !== '' ? $campos['area_interesse'] : null;
        $resumo = $campos['resumo_profissional'] !== '' ? $campos['resumo_profissional'] : null;
        try {
            $conn->begin_transaction();
            // Correção SEG-03 (decisão de produto: privacidade é o padrão): o perfil
            // nasce PRIVADO (perfil_publico = 0). Empresas só o veem depois de uma
            // candidatura às vagas delas ou se o próprio jovem ligar, no perfil,
            // "Permitir que empresas encontrem seu currículo".
            $stmt_ins = $conn->prepare(
                "INSERT INTO jovens
                    (nome, email, senha, cpf, data_nascimento,
                     cidade, estado, cep, formacao,
                     escolaridade_status, escolaridade_tipo, escolaridade_curso, clt,
                     area_interesse, resumo_profissional, perfil_publico)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
            );
            $stmt_ins->bind_param(
                "ssssssssssssiss",
                $campos['nome'],   $campos['email'],  $senha_hash,
                $campos['cpf'],    $campos['data_nasc'],
                $campos['cidade'], $campos['estado'],  $campos['cep'],
                $campos['formacao'],
                $esc_status, $esc_tipo, $esc_curso, $clt,
                $area, $resumo
            );
            $stmt_ins->execute();
            $novo_id = intval($conn->insert_id);
            $stmt_ins->close();

            if ($habilidades) {
                $stmt_h = $conn->prepare("INSERT INTO habilidades_jovem (jovem_id, habilidade) VALUES (?, ?)");
                foreach ($habilidades as $h) {
                    $stmt_h->bind_param("is", $novo_id, $h);
                    $stmt_h->execute();
                }
                $stmt_h->close();
            }
            jc_termos_registrar($conn, 'jovens', $novo_id);
            $conn->commit();
            // Stage 7: conteúdo suspeito vai para a fila de moderação (a conta é criada normalmente)
            jc_mod_registrar($conn, 'jovem', $novo_id, $sinais_mod, 'jovem', $novo_id);

            // Login automático após o cadastro (jc_sessao_entrar: troca o ID da sessão,
            // apaga a identidade de empresa, renova o token CSRF e guarda a referência da senha)
            jc_sessao_entrar('jovem', $novo_id, (string) $campos['nome'], $senha_hash);
            jc_log_seguranca('cadastro.concluido', 'info', ['tipo' => 'jovem', 'habilidades' => count($habilidades)]);
            // Antes do painel, a pessoa passa pela verificação da conta
            header("Location: ../verificacoes/escolher_verificacao.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            try { $conn->rollback(); } catch (Throwable $e2) { /* nada a desfazer */ }
            if ($e->getCode() === 1062) {   // e-mail/CPF gravado por outro envio ao mesmo tempo
                $erros['email'] = 'E-mail ou CPF já cadastrado na plataforma. Se a conta é sua, faça login ou recupere a senha.';
            } else {
                jc_log_excecao($e, 'cadastro.erro_banco');
                $erros['geral'] = 'Erro interno ao cadastrar. Tente novamente.';
            }
        }
    }

    if ($erros) {
        // só os NOMES dos campos com problema — nunca os valores digitados
        jc_log_seguranca('cadastro.falha', 'aviso', ['tipo' => 'jovem', 'campos' => array_map('strval', array_keys($erros))]);
    }
}
$etapa_erro = $erros ? jc_etapa_do_erro($erros, $etapa_dos_campos) : 1;
$err = fn(string $campo) => jc_campo_erro($erros, $campo);
$aria = fn(string $campo, string $ajuda = '') => jc_campo_aria($erros, $campo, $ajuda);   // orientação + erro num aria-describedby só
$val = fn(string $campo) => htmlspecialchars((string) ($campos[$campo] ?? ''), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro | JovemConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>


<div class="form-page">
<div class="form-container">

    <!-- Marca oficial vertical -->
    <div class="form-page-logo">
        <a href="../index.php" class="marca marca-vertical nav-logo" aria-label="JovemConnect — página inicial">
            <img class="marca-tema-escuro" src="../assets/img/marca/jovemconnect-vertical.png" alt="JovemConnect" width="878" height="689"><img class="marca-tema-claro" src="../assets/img/marca/jovemconnect-vertical-claro.png" alt="JovemConnect" width="878" height="689" loading="lazy">
        </a>
    </div>

    <!-- Volta para a escolha Jovem/Empresa -->
    <p style="margin:0 0 12px;"><a href="../comecar.php" class="back-link"><span class="i i-arrow-left" aria-hidden="true"></span> Voltar</a></p>

    <!-- ── Resumo dos erros do servidor (anunciado e focado ao abrir) ─────── -->
    <?php if (!empty($erros)): ?>
    <div class="form-card" id="errosCadastro" role="alert" tabindex="-1"
         style="border-color:rgba(239,68,68,.4);margin-bottom:0;">
        <p class="form-error" style="font-weight:700;"><span class="i i-alert-triangle" aria-hidden="true"></span> Confira <?= count($erros) === 1 ? 'o campo indicado' : 'os campos indicados' ?>:</p>
        <ul class="erros-lista">
            <?php foreach ($erros as $erro): ?>
            <li><?= htmlspecialchars($erro) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Formulário em etapas (includes/etapas.php). Sem JavaScript, todas as
         etapas aparecem juntas e o envio funciona igual. data-envio-unico
         evita cadastro em dobro com clique repetido. -->
    <form method="POST" id="formCadastroJovem" novalidate data-envio-unico="Cadastrando..."
          data-etapas data-etapa-inicial="<?= intval($etapa_erro) ?>">
        <?= csrf_field() ?>

        <div class="form-card">
            <div class="form-page-title">
                <span class="i i-graduation-cap" aria-hidden="true"></span>
                <h1 style="font-size:22px;">Cadastro de Jovem</h1>
            </div>
            <p class="form-page-subtitle">
                Crie sua conta em 4 etapas rápidas para encontrar vagas de Jovem Aprendiz e estágio.
            </p>
            <p class="text-secondary" style="font-size:12px;margin-top:6px;">Campos com * são obrigatórios.</p>
        </div>

        <!-- ================================================================
             ETAPA 1 — Informações básicas
             ================================================================ -->
        <section class="etapa form-card" data-etapa="1" data-titulo="Informações básicas" aria-labelledby="etapa1Titulo">
            <h2 class="etapa-titulo" id="etapa1Titulo"><span class="i i-user" aria-hidden="true"></span> Informações básicas</h2>
            <p class="etapa-descricao">Seus dados de identificação e de acesso à conta.</p>

            <div class="form-group">
                <label class="form-label" for="nome">Nome completo *</label>
                <input type="text" id="nome" name="nome" class="form-control" placeholder="Seu nome aqui"
                       value="<?= $val('nome') ?>" required maxlength="100" autocomplete="name" data-regra="nome"
                       data-revisao="Nome" data-erro-vazio="Informe seu nome completo."<?= $aria('nome', 'nomeAjuda') ?>>
                <small id="nomeAjuda" class="campo-ajuda">Como no seu documento. Acentos, hífen e apóstrofo são aceitos.</small>
                <?= $err('nome') ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cpf">CPF *</label>
                    <input type="text" id="cpf" name="cpf" class="form-control" placeholder="000.000.000-00" maxlength="14"
                           inputmode="numeric" value="<?= $val('cpf') ?>" required autocomplete="off"
                           data-digitos="11" data-erro-digitos="O CPF tem 11 números."<?= $aria('cpf', 'cpfAjuda') ?>>
                    <small id="cpfAjuda" class="campo-ajuda">11 números (a pontuação entra sozinha). Usado só para evitar contas duplicadas; não aparece para empresas.</small>
                    <?= $err('cpf') ?>
                </div>
                <div class="form-group">
                    <label class="form-label" for="dataNasc">Data de nascimento *</label>
                    <input type="date" id="dataNasc" name="data_nascimento" class="form-control"
                           value="<?= $val('data_nasc') ?>" required max="<?= date('Y-m-d') ?>"
                           data-erro-vazio="Informe sua data de nascimento." data-erro-max="A data de nascimento não pode ser no futuro."
                           data-erro-formato="Data incompleta ou inválida. Use dia, mês e ano."<?= $aria('data_nascimento', 'dataNascAjuda') ?>>
                    <small id="dataNascAjuda" class="campo-ajuda">Não pode ser uma data futura.</small>
                    <small id="idadeDisplay" class="text-secondary" style="font-size:12px;" aria-live="polite"></small>
                    <?= $err('data_nascimento') ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">E-mail *</label>
                <div class="input-group">
                    <span class="input-icon i i-mail" aria-hidden="true"></span>
                    <input type="email" id="email" name="email" class="form-control" placeholder="seu@email.com"
                           value="<?= $val('email') ?>" required maxlength="100" autocomplete="email"
                           data-revisao="E-mail" data-erro-vazio="Informe seu e-mail."<?= $aria('email', 'emailAjuda') ?>>
                </div>
                <small id="emailAjuda" class="campo-ajuda">Ex.: nome@dominio.com. É com ele que você entra e recupera a senha.</small>
                <?= $err('email') ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="senha">Senha *</label>
                    <div class="input-group">
                        <span class="input-icon i i-lock" aria-hidden="true"></span>
                        <input type="password" id="senha" name="senha" class="form-control" placeholder="••••••••"
                               minlength="8" required autocomplete="new-password"
                               data-erro-vazio="Crie uma senha."<?= $aria('senha', 'senhaAjuda') ?>>
                    </div>
                    <small id="senhaAjuda" class="campo-ajuda">Mínimo de 8 caracteres.<?= $erros ? ' Por segurança, a senha não volta preenchida: digite-a de novo.' : '' ?></small>
                    <?= $err('senha') ?>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirmarSenha">Confirmar senha *</label>
                    <div class="input-group">
                        <span class="input-icon i i-lock" aria-hidden="true"></span>
                        <input type="password" id="confirmarSenha" name="confirmar_senha" class="form-control" placeholder="••••••••"
                               minlength="8" required autocomplete="new-password" data-igual-a="senha"
                               data-erro-igual="As senhas não coincidem." data-erro-vazio="Repita a senha."<?= $aria('confirmar_senha', 'confirmarAjuda') ?>>
                    </div>
                    <small id="confirmarAjuda" class="campo-ajuda">Repita a senha para conferir.</small>
                    <?= $err('confirmar_senha') ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             ETAPA 2 — Formação e escolaridade
             ================================================================ -->
        <section class="etapa form-card" data-etapa="2" data-titulo="Formação" aria-labelledby="etapa2Titulo">
            <h2 class="etapa-titulo" id="etapa2Titulo"><span class="i i-graduation-cap" aria-hidden="true"></span> Formação e escolaridade</h2>
            <p class="etapa-descricao">Nível de ensino, curso e se você está cursando ou já concluiu.</p>

            <!-- nível → curso/ano → situação (includes/formacao.php); o Match usa tipo, curso e situação -->
            <?= jc_formacao_campos([
                'id' => 'cad', 'nome' => '', 'form' => '', 'situacao' => 'escolaridade_status',
                'em_tecnico' => true, 'obrigatorio' => true,
                'valores' => $form_formacao['valores'] ?? [],
            ]) ?>

            <div class="form-checks-group" style="margin-top:4px;">
                <label class="form-check">
                    <input type="checkbox" name="clt" value="1" <?= !empty($campos['clt']) ? 'checked' : '' ?>>
                    <span class="form-check-label">Já trabalhei em regime CLT anteriormente</span>
                </label>
            </div>
        </section>

        <!-- ================================================================
             ETAPA 3 — Perfil profissional (opcional)
             ================================================================ -->
        <section class="etapa form-card" data-etapa="3" data-titulo="Perfil profissional" aria-labelledby="etapa3Titulo">
            <h2 class="etapa-titulo" id="etapa3Titulo"><span class="i i-briefcase" aria-hidden="true"></span> Perfil profissional</h2>
            <p class="etapa-descricao">Tudo aqui é opcional — experiência profissional não é exigida.
                Você pode completar ou mudar depois, no seu perfil.</p>

            <div class="form-group">
                <label class="form-label" for="areaInteresse">Área de interesse</label>
                <select id="areaInteresse" name="area_interesse" class="form-control" data-revisao="Área de interesse"<?= $aria('area_interesse', 'areaAjuda') ?>>
                    <option value="">Escolher depois</option>
                    <?php foreach (['Administrativo', 'TI', 'Vendas', 'Logística', 'Marketing', 'Recursos Humanos',
                                    'Financeiro', 'Saúde', 'Educação', 'Outro'] as $opc): ?>
                    <option value="<?= htmlspecialchars($opc) ?>" <?= ($campos['area_interesse'] ?? '') === $opc ? 'selected' : '' ?>><?= htmlspecialchars($opc) ?></option>
                    <?php endforeach; ?>
                </select>
                <small id="areaAjuda" class="campo-ajuda">Opcional. É uma das informações usadas no Match com as vagas.</small>
                <?= $err('area_interesse') ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="resumoProfissional">Objetivo profissional</label>
                <textarea id="resumoProfissional" name="resumo_profissional" class="form-control" rows="3" maxlength="1000"
                          placeholder="Ex: Busco minha primeira oportunidade como jovem aprendiz na área administrativa."
                          data-revisao="Objetivo profissional"<?= $aria('resumo_profissional') ?>><?= $val('resumo_profissional') ?></textarea>
                <?= $err('resumo_profissional') ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="habilidades">Habilidades</label>
                <input type="text" id="habilidades" name="habilidades" class="form-control" maxlength="1500"
                       placeholder="Ex: Pacote Office, comunicação, trabalho em equipe"
                       value="<?= htmlspecialchars($habilidades_txt, ENT_QUOTES) ?>" data-regra="habilidades"
                       data-revisao="Habilidades"<?= $aria('habilidades', 'habilidadesAjuda') ?>>
                <small id="habilidadesAjuda" class="campo-ajuda">Opcional. Separe por vírgula (até 15).</small>
                <?= $err('habilidades') ?>
            </div>
        </section>

        <!-- ================================================================
             ETAPA 4 — Localização, revisão e conclusão
             ================================================================ -->
        <section class="etapa form-card" data-etapa="4" data-titulo="Localização e revisão" aria-labelledby="etapa4Titulo">
            <h2 class="etapa-titulo" id="etapa4Titulo"><span class="i i-map-pin" aria-hidden="true"></span> Localização e conclusão</h2>
            <p class="etapa-descricao">Sua cidade ajuda o Match a encontrar vagas perto de você. Empresas veem só cidade e UF.</p>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cep">CEP</label>
                    <input type="text" id="cep" name="cep" class="form-control" placeholder="00000-000" maxlength="9"
                           inputmode="numeric" value="<?= $val('cep') ?>" autocomplete="postal-code" data-digitos="8"
                           data-erro-digitos="O CEP tem 8 números."<?= $aria('cep', 'cepAjudaJovem cepStatusJovem') ?>>
                    <small id="cepAjudaJovem" class="campo-ajuda">Opcional: 8 números. A cidade é preenchida pelo CEP.</small>
                    <small id="cepStatusJovem" class="text-muted" style="font-size:12px;" aria-live="polite"></small>
                    <?= $err('cep') ?>
                </div>
                <div class="form-group">
                    <label class="form-label" for="cidadeEstado">Cidade / UF</label>
                    <div class="input-group">
                        <span class="input-icon i i-map-pin" aria-hidden="true"></span>
                        <input type="text" id="cidadeEstado" name="cidade_estado" class="form-control" placeholder="Ex: São Paulo - SP" maxlength="110"
                               data-revisao="Cidade / UF" aria-describedby="cidadeEstadoAjuda"
                               value="<?= htmlspecialchars(!empty($campos['cidade'])
                                   ? $campos['cidade'] . (!empty($campos['estado']) ? ' - ' . $campos['estado'] : '') : '', ENT_QUOTES) ?>">
                    </div>
                    <small id="cidadeEstadoAjuda" class="campo-ajuda">Opcional. Cidade, traço e a sigla do estado (ex.: São Paulo - SP).</small>
                    <!-- preenchidos pelo CEP ou ao enviar (a partir de "Cidade - UF") -->
                    <input type="hidden" name="cidade" id="hidCidade" value="<?= $val('cidade') ?>">
                    <input type="hidden" name="estado" id="hidEstado" value="<?= $val('estado') ?>">
                </div>
            </div>

            <h3 class="etapa-subtitulo"><span class="i i-list-checks" aria-hidden="true"></span> Revise seus dados</h3>
            <div class="etapas-revisao" data-etapas-revisao>
                <p class="text-secondary" style="font-size:13px;">Confira as informações das etapas anteriores antes de concluir.</p>
            </div>

            <div class="etapa-aceite">
                <label class="form-check">
                    <input type="checkbox" name="aceite_termos" value="1" required
                           data-erro-vazio="Para criar a conta, leia e aceite os Termos de Uso e a Política de Privacidade."
                           <?= !empty($campos['aceite_termos']) ? 'checked' : '' ?><?= $aria('aceite_termos') ?>>
                    <span class="form-check-label">Li e aceito os
                        <a class="link-botao" href="../documentos.php?doc=termos" target="_blank" rel="noopener" data-jc-modal-abrir="modalTermos">Termos de Uso</a> e a
                        <a class="link-botao" href="../documentos.php?doc=privacidade" target="_blank" rel="noopener" data-jc-modal-abrir="modalPrivacidade">Política de Privacidade</a>. *</span>
                </label>
                <?= $err('aceite_termos') ?>
            </div>
            <?= $err('geral') ?>

            <div class="form-submit-card" data-etapas-fim>
                <button type="submit" class="btn btn-primary btn-full btn-lg">Criar minha conta</button>
                <p style="text-align:center;font-size:13px;color:var(--text-secondary);">
                    Já tem uma conta? <a href="login.php" class="font-semibold">Faça login</a>
                </p>
            </div>
        </section>
    </form>

</div><!-- /.form-container -->
</div><!-- /.form-page -->

<?php include dirname(__DIR__) . '/includes/institucional.php'; ?>
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>
<?= jc_etapas_script() ?>

<script>
/* Tema salvo */
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

/* Erro do servidor: o foco vai para o resumo dos erros */
document.getElementById('errosCadastro')?.focus();

/* Máscara do CPF (000.000.000-00) */
document.getElementById('cpf')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 11);
    v = v.replace(/(\d{3})(\d)/,            '$1.$2');
    v = v.replace(/(\d{3})\.(\d{3})(\d)/,   '$1.$2.$3');
    v = v.replace(/\.(\d{3})\.(\d{3})(\d)/, '.$1.$2-$3');
    this.value = v;
});

/* Máscara do CEP (00000-000) */
document.getElementById('cep')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 8);
    if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
    this.value = v;
});

/* CEP → cidade/UF (ViaCEP). O resultado aparece embaixo do campo; se falhar,
   a pessoa digita a cidade à mão. */
document.getElementById('cep')?.addEventListener('blur', async function () {
    const raw    = this.value.replace(/\D/g, '');
    const status = document.getElementById('cepStatusJovem');
    if (raw.length !== 8) return;
    if (status) status.textContent = 'Buscando cidade pelo CEP...';
    try {
        const res  = await fetch(`https://viacep.com.br/ws/${raw}/json/`);
        const data = await res.json();
        if (data.erro) {
            if (status) status.textContent = 'CEP não encontrado — preencha a cidade manualmente.';
            return;
        }
        document.getElementById('cidadeEstado').value = `${data.localidade} - ${data.uf}`;
        document.getElementById('hidCidade').value = data.localidade || '';
        document.getElementById('hidEstado').value = data.uf || '';
        if (status) status.replaceChildren(jcIcone('check-circle'), ' Cidade preenchida pelo CEP.');
    } catch {
        if (status) status.textContent = 'Não foi possível buscar o CEP agora — preencha a cidade manualmente.';
    }
});

/* Idade calculada a partir da data de nascimento (só informativo) */
document.getElementById('dataNasc')?.addEventListener('change', function () {
    const nasc = new Date(this.value);
    const el = document.getElementById('idadeDisplay');
    if (isNaN(nasc) || !el) return;
    const hoje = new Date();
    let idade = hoje.getFullYear() - nasc.getFullYear();
    const m = hoje.getMonth() - nasc.getMonth();
    if (m < 0 || (m === 0 && hoje.getDate() < nasc.getDate())) idade--;
    el.textContent = idade > 0 ? `${idade} anos` : '';
});

/* "Cidade - UF" → campos cidade e estado enviados ao servidor */
function separarCidadeUf() {
    const val    = document.getElementById('cidadeEstado')?.value.trim() ?? '';
    const partes = val.split('-').map(s => s.trim());
    if (partes.length >= 2) {
        document.getElementById('hidCidade').value = partes.slice(0, -1).join('-').trim();
        document.getElementById('hidEstado').value = partes[partes.length - 1].toUpperCase().slice(0, 2);
    } else {
        document.getElementById('hidCidade').value = val;
    }
}
document.getElementById('cidadeEstado')?.addEventListener('change', separarCidadeUf);
document.getElementById('formCadastroJovem')?.addEventListener('submit', separarCidadeUf);
</script>

</body>
</html>