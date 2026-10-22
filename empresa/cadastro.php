<?php
/* ============================================================
   empresa/cadastro.php
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

// Já logado → redireciona direto
if (isset($_SESSION['empresa_id'])) {
    header("Location: ../dashboard/empresa.php");
    exit;
}
// Jovem logado também vai para o painel dele (antes via o formulário
// e, ao enviar, a sessão ficava com jovem E empresa ao mesmo tempo)
if (isset($_SESSION['jovem_id'])) {
    header("Location: ../dashboard/jovem.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/endereco_empresa.php";   // endereço completo da empresa
require_once dirname(__DIR__) . "/includes/termos.php";             // versão e registro do aceite
require_once dirname(__DIR__) . "/includes/etapas.php";             // formulário em etapas
require_once dirname(__DIR__) . "/includes/moderacao.php";          // Stage 7: validação + moderação centrais

$erros  = [];   // chave = nome do campo (mensagem aparece perto dele)
$campos = [];   // valores para repreencher o formulário quando houver erro
$tem_endereco = empresa_tem_endereco($conn);   // colunas de endereço completo já existem?

/* Em que etapa fica cada campo (a página reabre na etapa do primeiro erro). */
$etapa_dos_campos = [
    'cnpj' => 1, 'nome_empresa' => 1, 'email' => 1, 'senha' => 1, 'confirmar_senha' => 1,
    'cep' => 2, 'cidade' => 2, 'estado' => 2,
    'area_atuacao' => 3, 'descricao' => 3, 'site_oficial' => 3,
    'aceite_termos' => 4, 'geral' => 4,
];

/* ============================================================
   PROCESSAMENTO POST — todo PHP antes de qualquer HTML
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // ── Leitura e limpeza (limites = tamanho das colunas) ─────────────────────
    $campos = [
        'nome_empresa' => mb_substr(trim($_POST['nome_empresa'] ?? ''), 0, 150),
        'email'        => trim($_POST['email']        ?? ''),
        'cnpj'         => trim($_POST['cnpj']         ?? ''),
        'area_atuacao' => mb_substr(trim($_POST['area_atuacao'] ?? ''), 0, 100),
        'cidade'       => mb_substr(trim($_POST['cidade']       ?? ''), 0, 100),
        'estado'       => mb_substr(strtoupper(trim($_POST['estado'] ?? '')), 0, 2),
        'descricao'    => mb_substr(trim((string) ($_POST['descricao'] ?? '')), 0, 2000),
        'site_oficial' => mb_substr(trim((string) ($_POST['site_oficial'] ?? '')), 0, 255),
    ] + endereco_empresa_do_post();
    $senha      = $_POST['senha']           ?? '';
    $conf_senha = $_POST['confirmar_senha'] ?? '';
    $aceite     = ($_POST['aceite_termos'] ?? '') === '1';
    $campos['aceite_termos'] = $aceite;

    // ── Stage 7: validação estrutural + moderação (includes/moderacao.php) ────
    // Os cortes de tamanho acima continuam valendo como antes.
    $mod = jc_mod_formulario([
        'empresa.nome'         => [$campos['nome_empresa'], 'nome_empresa'],
        'empresa.area_atuacao' => [$campos['area_atuacao'], 'area_atuacao'],
        'empresa.cidade'       => [$campos['cidade'], 'cidade'],
        'empresa.descricao'    => [$campos['descricao'], 'descricao'],
        'empresa.site'         => [$campos['site_oficial'], 'site_oficial'],
        'local.logradouro'     => [$campos['logradouro'] ?? '', 'logradouro'],
        'local.numero'         => [$campos['numero'] ?? '', 'numero'],
        'local.complemento'    => [$campos['complemento'] ?? '', 'complemento'],
        'local.bairro'         => [$campos['bairro'] ?? '', 'bairro'],
    ]);
    foreach ($mod['erros'] as $campo_form => $msg) {
        // endereço: a mensagem aparece junto do CEP (mesma etapa)
        $chave_erro = in_array($campo_form, ['logradouro', 'numero', 'complemento', 'bairro'], true) ? 'cep' : $campo_form;
        $erros[$chave_erro] = $erros[$chave_erro] ?? $msg;
    }
    foreach (['empresa.nome' => 'nome_empresa', 'empresa.area_atuacao' => 'area_atuacao', 'empresa.cidade' => 'cidade',
              'empresa.descricao' => 'descricao', 'empresa.site' => 'site_oficial', 'local.logradouro' => 'logradouro',
              'local.numero' => 'numero', 'local.complemento' => 'complemento', 'local.bairro' => 'bairro'] as $chave => $campo) {
        if (isset($mod['valores'][$chave])) {
            $campos[$campo] = $mod['valores'][$chave];
        }
    }

    // ── Validações ────────────────────────────────────────────────────────────
    if ($campos['nome_empresa'] === '')
        $erros['nome_empresa'] = 'Informe o nome da empresa.';

    if ($campos['email'] === '')
        $erros['email'] = 'Informe o e-mail de acesso.';
    elseif (!filter_var($campos['email'], FILTER_VALIDATE_EMAIL))
        $erros['email'] = 'Formato de e-mail inválido.';
    elseif (mb_strlen($campos['email']) > 100)
        $erros['email'] = 'E-mail muito longo (máximo 100 caracteres).';

    if (strlen(preg_replace('/\D/', '', $campos['cnpj'])) !== 14)
        $erros['cnpj'] = 'CNPJ inválido (deve ter 14 dígitos).';
    else
        $campos['cnpj'] = cnpj_formatado($campos['cnpj']);   // formato único 00.000.000/0000-00

    if ($campos['cep'] !== '' && strlen($campos['cep']) !== 8)
        $erros['cep'] = 'CEP inválido (deve ter 8 dígitos).';
    if ($campos['estado'] !== '' && !preg_match('/^[A-Z]{2}$/', $campos['estado']))
        $erros['estado'] = 'Use a sigla do estado com 2 letras (ex.: SP).';

    if ($campos['site_oficial'] !== '' && !isset($erros['site_oficial']) && !filter_var(
            preg_match('~^https?://~i', $campos['site_oficial']) ? $campos['site_oficial'] : 'https://' . $campos['site_oficial'],
            FILTER_VALIDATE_URL))
        $erros['site_oficial'] = 'Endereço do site inválido.';

    if ($senha === '')
        $erros['senha'] = 'Crie uma senha.';
    elseif (strlen($senha) < 8)
        $erros['senha'] = 'A senha deve ter pelo menos 8 caracteres.';
    elseif ($senha !== $conf_senha)
        $erros['confirmar_senha'] = 'As senhas não coincidem.';

    if (!$aceite)
        $erros['aceite_termos'] = 'Para criar a conta, leia e aceite os Termos de Uso e a Política de Privacidade.';

    // ── Duplicidade (e-mail ou CNPJ já usados) ────────────────────────────────
    if (empty($erros)) {
        $stmt_check = $conn->prepare("SELECT id FROM empresas WHERE email = ? OR cnpj = ? LIMIT 1");
        $stmt_check->bind_param("ss", $campos['email'], $campos['cnpj']);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0)
            $erros['email'] = 'E-mail ou CNPJ já cadastrado na plataforma. Se a conta é sua, faça login ou recupere a senha.';
        $stmt_check->close();
    }

    // ── Inserção ──────────────────────────────────────────────────────────────
    if (empty($erros)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $descricao  = $campos['descricao'] !== '' ? $campos['descricao'] : null;
        $site       = $campos['site_oficial'] !== '' ? $campos['site_oficial'] : null;
        try {
            if ($tem_endereco) {
                $stmt_ins = $conn->prepare(
                    "INSERT INTO empresas
                        (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, site_oficial,
                         cep, logradouro, numero, complemento, bairro)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt_ins->bind_param(
                    "ssssssssssssss",
                    $campos['nome_empresa'], $campos['email'], $senha_hash, $campos['cnpj'],
                    $campos['cidade'], $campos['estado'], $campos['area_atuacao'], $descricao, $site,
                    $campos['cep'], $campos['logradouro'], $campos['numero'],
                    $campos['complemento'], $campos['bairro']
                );
            } else {
                // migração do endereço completo ainda não aplicada: só cidade/UF
                $stmt_ins = $conn->prepare(
                    "INSERT INTO empresas
                        (nome_empresa, email, senha, cnpj, cidade, estado, area_atuacao, descricao, site_oficial)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt_ins->bind_param(
                    "sssssssss",
                    $campos['nome_empresa'], $campos['email'], $senha_hash, $campos['cnpj'],
                    $campos['cidade'], $campos['estado'], $campos['area_atuacao'], $descricao, $site
                );
            }
            $stmt_ins->execute();
            $novo_id = intval($conn->insert_id);
            $stmt_ins->close();
            jc_termos_registrar($conn, 'empresas', $novo_id);
            // Stage 7: conteúdo suspeito vai para a fila de moderação (a conta é criada normalmente)
            jc_mod_registrar($conn, 'empresa', $novo_id, $mod['sinais'], 'empresa', $novo_id);

            // Login automático após o cadastro
            $_SESSION['empresa_id']   = $novo_id;
            $_SESSION['empresa_nome'] = $campos['nome_empresa'];
            jc_session_regenerate();
            jc_log_seguranca('cadastro.concluido', 'info', ['tipo' => 'empresa']);
            // Antes do painel, a empresa passa pela verificação da conta
            header("Location: ../verificacoes/escolher_verificacao.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {   // e-mail/CNPJ gravado por outro envio ao mesmo tempo
                $erros['email'] = 'E-mail ou CNPJ já cadastrado na plataforma. Se a conta é sua, faça login ou recupere a senha.';
            } else {
                jc_log_excecao($e, 'cadastro.erro_banco');
                $erros['geral'] = 'Erro interno ao cadastrar. Tente novamente.';
            }
        }
    }

    if ($erros) {
        jc_log_seguranca('cadastro.falha', 'aviso', ['tipo' => 'empresa', 'campos' => array_map('strval', array_keys($erros))]);
    }
}
$etapa_erro = $erros ? jc_etapa_do_erro($erros, $etapa_dos_campos) : 1;
$err  = fn(string $campo) => jc_campo_erro($erros, $campo);
$aria = fn(string $campo, string $ajuda = '') => jc_campo_aria($erros, $campo, $ajuda);   // orientação + erro num aria-describedby só
$val  = fn(string $campo) => htmlspecialchars((string) ($campos[$campo] ?? ''), ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Empresa | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
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
         etapas aparecem juntas. data-envio-unico evita cadastro em dobro. -->
    <form method="POST" id="formEmpresa" novalidate data-envio-unico="Registrando empresa..."
          data-etapas data-etapa-inicial="<?= intval($etapa_erro) ?>">
        <?= csrf_field() ?>

        <div class="form-card">
            <div class="form-page-title">
                <span class="i i-building" aria-hidden="true"></span>
                <h1 style="font-size:22px;">Cadastro de Empresa</h1>
            </div>
            <p class="form-page-subtitle">
                Registre sua empresa em 4 etapas para anunciar vagas e encontrar jovens talentos.
            </p>
            <p class="text-secondary" style="font-size:12px;margin-top:6px;">Campos com * são obrigatórios.</p>
        </div>

        <!-- ================================================================
             ETAPA 1 — Identificação da empresa e acesso
             ================================================================ -->
        <section class="etapa form-card" data-etapa="1" data-titulo="Identificação" aria-labelledby="etapa1Titulo">
            <h2 class="etapa-titulo" id="etapa1Titulo"><span class="i i-building" aria-hidden="true"></span> Identificação da empresa</h2>
            <p class="etapa-descricao">CNPJ, nome e os dados de acesso à conta.</p>

            <div class="form-group">
                <label class="form-label" for="cnpj">CNPJ *</label>
                <div class="input-row">
                    <input type="text" id="cnpj" name="cnpj" class="form-control" placeholder="00.000.000/0000-00"
                           maxlength="18" inputmode="numeric" value="<?= $val('cnpj') ?>" autocomplete="off" required
                           data-digitos="14" data-erro-digitos="O CNPJ tem 14 números." data-revisao="CNPJ"
                           <?= $aria('cnpj', 'cnpjAjuda cnpjStatus') ?>>
                    <button type="button" class="btn-icon" id="btnBuscarCnpj" title="Consultar CNPJ automaticamente"
                            aria-label="Buscar dados pelo CNPJ"><span class="i i-search" aria-hidden="true"></span></button>
                </div>
                <small id="cnpjAjuda" class="campo-ajuda">14 números (a pontuação entra sozinha). A lupa preenche os dados públicos da empresa.</small>
                <small id="cnpjStatus" class="text-muted" style="font-size:12px;" aria-live="polite"></small>
                <?= $err('cnpj') ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="nomeEmpresa">Nome da empresa (razão social ou nome de exibição) *</label>
                <input type="text" id="nomeEmpresa" name="nome_empresa" class="form-control" placeholder="Empresa LTDA"
                       maxlength="150" value="<?= $val('nome_empresa') ?>" required data-revisao="Empresa" data-regra="nome_org"
                       data-erro-vazio="Informe o nome da empresa."<?= $aria('nome_empresa', 'nomeEmpresaAjuda') ?>>
                <small id="nomeEmpresaAjuda" class="campo-ajuda">Como os candidatos vão ver. Comece com uma letra ou um número.</small>
                <?= $err('nome_empresa') ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">E-mail de acesso *</label>
                <div class="input-group">
                    <span class="input-icon i i-mail" aria-hidden="true"></span>
                    <input type="email" id="email" name="email" class="form-control" placeholder="rh@empresa.com.br"
                           maxlength="100" value="<?= $val('email') ?>" required autocomplete="email" data-revisao="E-mail"
                           data-erro-vazio="Informe o e-mail de acesso."<?= $aria('email', 'emailAjuda') ?>>
                </div>
                <small id="emailAjuda" class="campo-ajuda">Ex.: rh@empresa.com.br. É com ele que a empresa entra e recupera a senha.</small>
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
             ETAPA 2 — Endereço principal (sede)
             Outros endereços (escritórios, filiais) ficam em "Locais de
             trabalho", depois do cadastro — este é o endereço principal.
             ================================================================ -->
        <section class="etapa form-card" data-etapa="2" data-titulo="Endereço principal" aria-labelledby="etapa2Titulo">
            <h2 class="etapa-titulo" id="etapa2Titulo"><span class="i i-map-pin" aria-hidden="true"></span> Endereço principal</h2>
            <p class="etapa-descricao">O endereço da sede é o local padrão das vagas. Escritórios e filiais podem ser
                cadastrados depois, em "Locais de trabalho".</p>

            <?php if ($tem_endereco): ?>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cep">CEP</label>
                    <input type="text" id="cep" name="cep" class="form-control" placeholder="00000-000" maxlength="9"
                           inputmode="numeric" autocomplete="postal-code" value="<?= $val('cep') ?>" data-digitos="8"
                           data-erro-digitos="O CEP tem 8 números."<?= $aria('cep', 'cepAjuda cepStatus') ?>>
                    <small id="cepAjuda" class="campo-ajuda">Opcional: 8 números. Rua, bairro, cidade e UF são preenchidos pelo CEP.</small>
                    <small id="cepStatus" class="text-muted" style="font-size:12px;" aria-live="polite"></small>
                    <?= $err('cep') ?>
                </div>
                <div class="form-group">
                    <label class="form-label" for="logradouro">Rua / Logradouro</label>
                    <input type="text" id="logradouro" name="logradouro" class="form-control" maxlength="150"
                           autocomplete="address-line1" value="<?= $val('logradouro') ?>" data-revisao="Rua">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="numero">Número</label>
                    <input type="text" id="numero" name="numero" class="form-control" maxlength="10" placeholder="Ex: 123 ou S/N"
                           value="<?= $val('numero') ?>" data-revisao="Número">
                </div>
                <div class="form-group">
                    <label class="form-label" for="complemento">Complemento</label>
                    <input type="text" id="complemento" name="complemento" class="form-control" maxlength="100"
                           placeholder="Ex: Sala 4, Bloco B" value="<?= $val('complemento') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="bairro">Bairro</label>
                <input type="text" id="bairro" name="bairro" class="form-control" maxlength="100" value="<?= $val('bairro') ?>"
                       data-revisao="Bairro">
            </div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cidade">Cidade</label>
                    <input type="text" id="cidade" name="cidade" class="form-control" maxlength="100"
                           autocomplete="address-level2" value="<?= $val('cidade') ?>" data-revisao="Cidade"<?= $aria('cidade') ?>>
                    <?= $err('cidade') ?>
                </div>
                <div class="form-group">
                    <label class="form-label" for="estado">UF</label>
                    <input type="text" id="estado" name="estado" class="form-control" maxlength="2" placeholder="SP"
                           autocomplete="address-level1" value="<?= $val('estado') ?>" data-uf data-revisao="UF"<?= $aria('estado', 'ufAjuda') ?>>
                    <small id="ufAjuda" class="campo-ajuda">Opcional. Sigla com 2 letras (ex.: SP).</small>
                    <?= $err('estado') ?>
                </div>
            </div>
        </section>

        <!-- ================================================================
             ETAPA 3 — Perfil da empresa (opcional)
             ================================================================ -->
        <section class="etapa form-card" data-etapa="3" data-titulo="Perfil da empresa" aria-labelledby="etapa3Titulo">
            <h2 class="etapa-titulo" id="etapa3Titulo"><span class="i i-briefcase" aria-hidden="true"></span> Perfil da empresa</h2>
            <p class="etapa-descricao">Opcional — ajuda os jovens a conhecer a empresa. Dá para completar depois, no perfil.</p>

            <div class="form-group">
                <label class="form-label" for="areaAtuacao">Área de atuação</label>
                <input type="text" id="areaAtuacao" name="area_atuacao" class="form-control" maxlength="100"
                       placeholder="Ex: Tecnologia, Varejo" value="<?= $val('area_atuacao') ?>" data-revisao="Área de atuação">
            </div>
            <div class="form-group">
                <label class="form-label" for="descricaoEmpresa">Sobre a empresa</label>
                <textarea id="descricaoEmpresa" name="descricao" class="form-control" rows="3" maxlength="2000"
                          placeholder="O que a empresa faz e como é trabalhar nela."
                          data-revisao="Sobre a empresa"><?= $val('descricao') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="siteOficial">Site</label>
                <input type="text" id="siteOficial" name="site_oficial" class="form-control" maxlength="255" inputmode="url"
                       placeholder="www.empresa.com.br" value="<?= $val('site_oficial') ?>" data-revisao="Site" data-regra="site"<?= $aria('site_oficial', 'siteAjuda') ?>>
                <small id="siteAjuda" class="campo-ajuda">Opcional. Ex.: www.suaempresa.com.br</small>
                <?= $err('site_oficial') ?>
            </div>
        </section>

        <!-- ================================================================
             ETAPA 4 — Revisão e conclusão
             ================================================================ -->
        <section class="etapa form-card" data-etapa="4" data-titulo="Revisão" aria-labelledby="etapa4Titulo">
            <h2 class="etapa-titulo" id="etapa4Titulo"><span class="i i-list-checks" aria-hidden="true"></span> Revisão e conclusão</h2>
            <p class="etapa-descricao">Confira os dados antes de criar a conta.</p>

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
                <button type="submit" class="btn btn-primary btn-full btn-lg">Registrar empresa</button>
                <p style="text-align:center;font-size:13px;color:var(--text-secondary);">
                    Sua empresa já é parceira? <a href="login.php" class="font-semibold">Faça login</a>
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

/* Máscara do CNPJ (00.000.000/0000-00) */
document.getElementById('cnpj')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/,          '$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/,         '.$1/$2');
    v = v.replace(/(\d{4})(\d)/,           '$1-$2');
    this.value = v;
});

/* CNPJ → nome, cidade/UF e CEP (BrasilAPI). Só preenche o que está vazio
   (o nome é trocado pela razão social). O resultado aparece embaixo do campo. */
document.getElementById('btnBuscarCnpj')?.addEventListener('click', async function () {
    const cnpjRaw = document.getElementById('cnpj').value.replace(/\D/g, '');
    const status  = document.getElementById('cnpjStatus');
    if (cnpjRaw.length !== 14) {
        if (status) status.textContent = 'Digite o CNPJ completo (14 números) antes de buscar.';
        return;
    }
    this.replaceChildren(jcIcone('loader'));
    this.firstChild?.classList.add('spin');
    this.setAttribute('aria-busy', 'true');
    this.disabled = true;
    if (status) status.textContent = 'Consultando o CNPJ...';
    try {
        const res = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpjRaw}`);
        if (!res.ok) throw Object.assign(new Error('CNPJ não encontrado'), { naoEncontrado: true });
        const data = await res.json();
        const nomeEl = document.getElementById('nomeEmpresa');
        if (nomeEl && data.razao_social) nomeEl.value = data.razao_social;
        const cidEl = document.getElementById('cidade');
        const ufEl  = document.getElementById('estado');
        if (cidEl && !cidEl.value.trim() && data.municipio) cidEl.value = data.municipio;
        if (ufEl  && !ufEl.value.trim()  && data.uf)        ufEl.value  = data.uf;
        const cepCnpj = document.getElementById('cep');
        if (cepCnpj && !cepCnpj.value.trim() && data.cep) {
            cepCnpj.value = String(data.cep).replace(/\D/g, '').replace(/^(\d{5})(\d{3})$/, '$1-$2');
            buscarCep();
        }
        if (status) status.replaceChildren(jcIcone('check-circle'), ' Dados preenchidos pelo CNPJ. Confira antes de continuar.');
    } catch (err) {
        if (status) status.textContent = err?.naoEncontrado
            ? 'CNPJ não encontrado. Confira o número ou preencha os dados manualmente.'
            : 'Não foi possível consultar o CNPJ agora. Preencha os dados manualmente.';
    } finally {
        this.replaceChildren(jcIcone('search'));
        this.removeAttribute('aria-busy');
        this.disabled = false;
    }
});

/* CEP → rua, bairro, cidade e UF (ViaCEP); número e complemento são digitados.
   Não rebusca o CEP já preenchido (não sobrescreve o que foi digitado à mão). */
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
</script>

</body>
</html>