<?php
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/includes/tentativas.php';   // SEG-02/SEG-07: limite de códigos e de envios

// Identifica se é Jovem ou Empresa logada
$tipoUsuario = isset($_SESSION['jovem_id']) ? 'jovem' : (isset($_SESSION['empresa_id']) ? 'empresa' : null);
$idUsuario = $_SESSION['jovem_id'] ?? $_SESSION['empresa_id'] ?? null;

// Se não houver ninguém em sessão, redireciona para a home
if (!$tipoUsuario || !$idUsuario) {
    header("Location: ../index.php");
    exit;
}

$tabela = ($tipoUsuario === 'jovem') ? 'jovens' : 'empresas';
// A tabela empresas não tem coluna "nome" (é nome_empresa). Antes, a
// verificação de e-mail da EMPRESA parava com erro fatal "Unknown column 'nome'".
$colNome = ($tabela === 'jovens') ? 'nome' : 'nome_empresa AS nome';
$mensagemSucesso = null;
$mensagemErro = null;
$chave_conta = "verif_email|{$tabela}|" . intval($idUsuario);   // contadores desta conta (SEG-02/SEG-07)

if (!function_exists('gerarEEnviarCodigoEmail')) {
    // Gera um novo código, grava no banco e envia por e-mail. Reaproveitada
    // no reenvio manual e no primeiro envio automático (ao entrar na página).
    function gerarEEnviarCodigoEmail(mysqli $conn, string $tabela, int $idUsuario, string $email, string $nome): bool {
        $novoCodigo    = sprintf("%06d", random_int(0, 999999));
        $novaExpiracao = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $update = $conn->prepare("UPDATE {$tabela} SET codigo_verificacao = ?, expiracao_codigo = ? WHERE id = ?");
        $update->bind_param("ssi", $novoCodigo, $novaExpiracao, $idUsuario);
        $update->execute();
        $update->close();

        $enviado = enviarCodigoVerificacao($email, $nome, $novoCodigo);
        jc_log_seguranca('verificacao.email.enviado', $enviado ? 'info' : 'aviso', ['ok' => (bool) $enviado]);
        return $enviado;
    }
}

// 1. PROCESSAR VALIDAÇÃO DO CÓDIGO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_verificar'])) {
    csrf_require();
    $codigoDigitado = trim($_POST['codigo'] ?? '');

    $stmt = $conn->prepare("SELECT email, codigo_verificacao, expiracao_codigo, email_verificado FROM {$tabela} WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user['email_verificado'] == 1) {
        header("Location: ../dashboard/index.php");
        exit;
    }

    $agora = date('Y-m-d H:i:s');
    $espera = jc_tent_espera('codigo_ip', jc_tent_ip(), JC_TENT_CODIGO_IP);

    if ($espera > 0) {
        jc_log_seguranca('verificacao.email.bloqueada', 'aviso', ['motivo' => 'muitas_tentativas']);
        $mensagemErro = jc_tent_msg($espera);
    } elseif (!empty($codigoDigitado) && $user['codigo_verificacao'] !== null
        && hash_equals((string) $user['codigo_verificacao'], $codigoDigitado)
        && $user['expiracao_codigo'] >= $agora) {
        // Marca e-mail como verificado e limpa os tokens temporários (código não é reutilizável)
        $update = $conn->prepare("UPDATE {$tabela} SET email_verificado = 1, codigo_verificacao = NULL, expiracao_codigo = NULL WHERE id = ?");
        $update->bind_param("i", $idUsuario);
        $update->execute();
        $update->close();

        jc_tent_zerar('codigo_conta', $chave_conta);
        jc_log_seguranca('verificacao.email.sucesso', 'info');
        header("Location: ../dashboard/index.php?verified=1");
        exit;
    } else {
        jc_tent_registrar('codigo_ip', jc_tent_ip());
        $erros_codigo = jc_tent_registrar('codigo_conta', $chave_conta);
        if ($erros_codigo >= JC_TENT_CODIGO_CONTA) {
            // SEG-02: 5 erros cancelam o código; um novo precisa ser pedido
            $update = $conn->prepare("UPDATE {$tabela} SET codigo_verificacao = NULL, expiracao_codigo = NULL WHERE id = ?");
            $update->bind_param("i", $idUsuario);
            $update->execute();
            $update->close();
            jc_tent_zerar('codigo_conta', $chave_conta);
            jc_log_seguranca('verificacao.email.codigo_cancelado', 'aviso', ['motivo' => 'muitas_tentativas']);
            $mensagemErro = "Código incorreto muitas vezes. Por segurança, ele foi cancelado: use \"Reenviar código\" para receber outro.";
        } else {
            jc_log_seguranca('verificacao.email.falha', 'aviso');
            $mensagemErro = "Código incorreto ou expirado. Verifique a caixa de entrada ou solicite o reenvio.";
        }
        jc_tent_atraso($erros_codigo);
    }
}

// 2. PROCESSAR REENVIO DE CÓDIGO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_reenviar'])) {
    csrf_require();
    $stmt = $conn->prepare("SELECT {$colNome}, email FROM {$tabela} WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (($espera_envio = jc_tent_envio_permitido($chave_conta)) > 0) {   // SEG-07
        jc_log_seguranca('verificacao.email.limitada', 'aviso');
        $mensagemErro = jc_tent_msg_envio($espera_envio);
    } elseif (gerarEEnviarCodigoEmail($conn, $tabela, $idUsuario, $user['email'], $user['nome'])) {
        $mensagemSucesso = "Novo código enviado para " . htmlspecialchars($user['email']) . "!";
    } else {
        $mensagemErro = "Não foi possível enviar o e-mail agora. Tente novamente em instantes.";
    }
}

// 3. PRIMEIRO ACESSO (GET): se não há código válido pendente, envia um automaticamente
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT {$colNome}, email, codigo_verificacao, expiracao_codigo, email_verificado FROM {$tabela} WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user['email_verificado'] == 1) {
        header("Location: ../dashboard/index.php");
        exit;
    }

    $agora = date('Y-m-d H:i:s');
    $temCodigoValido = !empty($user['codigo_verificacao']) && $user['expiracao_codigo'] >= $agora;

    if (!$temCodigoValido) {
        if (($espera_envio = jc_tent_envio_permitido($chave_conta)) > 0) {   // SEG-07: recarregar a página não dispara e-mails sem fim
            $mensagemErro = jc_tent_msg_envio($espera_envio);
        } elseif (gerarEEnviarCodigoEmail($conn, $tabela, $idUsuario, $user['email'], $user['nome'])) {
            $mensagemSucesso = "Enviamos um código para " . htmlspecialchars($user['email']) . "!";
        } else {
            $mensagemErro = "Não foi possível enviar o e-mail agora. Você pode tentar novamente pelo botão abaixo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar E-mail | JovemConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>

<?php include_once dirname(__DIR__) . '/includes/header.php'; ?>

<!-- 60 px da barra fixa + 40 px de respiro -->
<main class="container verificacao-main" style="padding: 100px 16px 40px; max-width: 480px; margin: 0 auto;">
    <div class="card" style="text-align: center; padding: 32px 24px;">
        <div class="icone-destaque" aria-hidden="true"><span class="i i-mail" aria-hidden="true"></span></div>
        
        <h2>Verifique seu E-mail</h2>
        <p class="text-muted" style="margin-bottom: 24px; font-size: 14px; line-height: 1.5;">
            Insira o código de 6 dígitos que enviamos para a sua caixa de entrada para ativar sua conta.
        </p>

        <?php if ($mensagemErro): ?>
            <div role="alert" class="alerta alerta-erro">
                <span class="i i-alert-triangle" aria-hidden="true"></span> <span><?= htmlspecialchars($mensagemErro) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($mensagemSucesso): ?>
            <div role="status" class="alerta alerta-sucesso">
                <span class="i i-check-circle" aria-hidden="true"></span> <span><?= htmlspecialchars($mensagemSucesso) ?></span>
            </div>
        <?php endif; ?>

        <!-- Formulário de Validação -->
        <form action="verificar_email.php" method="POST" style="margin-bottom: 20px;" data-envio-unico="Validando...">
            <input type="hidden" name="acao_verificar" value="1">
            <?= csrf_field() ?>
            <div class="form-group" style="margin-bottom: 20px;">
                <!-- O campo não tinha rótulo (só o texto de exemplo "000000") -->
                <input type="text" name="codigo" class="form-control" placeholder="000000" maxlength="6" required
                       aria-label="Código de verificação de 6 dígitos" inputmode="numeric" autocomplete="one-time-code" 
                       style="font-size: 28px; text-align: center; letter-spacing: 8px; font-weight: bold; padding: 12px; font-family: monospace;">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Validar Conta Agora</button>
        </form>

        <!-- Form de Reenvio -->
        <form action="verificar_email.php" method="POST" data-envio-unico="Enviando...">
            <input type="hidden" name="acao_reenviar" value="1">
            <?= csrf_field() ?>
            <button type="submit" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 13px; text-decoration: underline;">
                Não recebeu o código? Reenviar e-mail
            </button>
        </form>
        <!-- Saídas explícitas -->
        <p style="margin-top: 24px; font-size: 13px; display: flex; flex-direction: column; gap: 8px; align-items: center;">
            <a href="escolher_verificacao.php" class="back-link"><span class="i i-arrow-left" aria-hidden="true"></span> Escolher outra forma de verificação</a>
            <a href="../dashboard/index.php" class="back-link">Verificar depois e ir para o meu painel <span class="i i-arrow-right" aria-hidden="true"></span></a>
        </p>
    </div>
</main>

<?php include_once dirname(__DIR__) . '/includes/footer.php'; ?>

</body>
</html>