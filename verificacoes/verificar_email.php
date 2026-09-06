<?php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

// Identifica se é Jovem ou Empresa logada
$tipoUsuario = isset($_SESSION['jovem_id']) ? 'jovem' : (isset($_SESSION['empresa_id']) ? 'empresa' : null);
$idUsuario = $_SESSION['jovem_id'] ?? $_SESSION['empresa_id'] ?? null;

// Se não houver ninguém em sessão, redireciona para a home
if (!$tipoUsuario || !$idUsuario) {
    header("Location: ../index.php");
    exit;
}

$tabela = ($tipoUsuario === 'jovem') ? 'jovens' : 'empresas';
$mensagemSucesso = null;
$mensagemErro = null;

// 1. PROCESSAR VALIDAÇÃO DO CÓDIGO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_verificar'])) {
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

    if (!empty($codigoDigitado) && $user['codigo_verificacao'] === $codigoDigitado && $user['expiracao_codigo'] >= $agora) {
        // Marca e-mail como verificado e limpa os tokens temporários
        $update = $conn->prepare("UPDATE {$tabela} SET email_verificado = 1, codigo_verificacao = NULL, expiracao_codigo = NULL WHERE id = ?");
        $update->bind_param("i", $idUsuario);
        $update->execute();
        $update->close();

        header("Location: ../dashboard/index.php?verified=1");
        exit;
    } else {
        $mensagemErro = "Código incorreto ou expirado. Verifique a caixa de entrada ou solicite o reenvio.";
    }
}

// 2. PROCESSAR REENVIO DE CÓDIGO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_reenviar'])) {
    $stmt = $conn->prepare("SELECT nome, email FROM {$tabela} WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $novoCodigo = sprintf("%06d", random_int(0, 999999));
    $novaExpiracao = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $update = $conn->prepare("UPDATE {$tabela} SET codigo_verificacao = ?, expiracao_codigo = ? WHERE id = ?");
    $update->bind_param("ssi", $novoCodigo, $novaExpiracao, $idUsuario);
    $update->execute();
    $update->close();

    if (enviarCodigoVerificacao($user['email'], $user['nome'], $novoCodigo)) {
        $mensagemSucesso = "Novo código enviado para " . htmlspecialchars($user['email']) . "!";
    } else {
        $mensagemErro = "Falha ao enviar o e-mail. Verifique suas configurações de SMTP em includes/mailer.php.";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<?php include_once dirname(__DIR__) . '/includes/header.php'; ?>

<main class="container" style="padding: 40px 16px; max-width: 480px; margin: 0 auto;">
    <div class="card" style="text-align: center; padding: 32px 24px;">
        <div style="font-size: 48px; color: var(--accent); margin-bottom: 16px;">
            <i class="fa-solid fa-envelope-open-text"></i>
        </div>
        
        <h2>Verifique seu E-mail</h2>
        <p class="text-muted" style="margin-bottom: 24px; font-size: 14px; line-height: 1.5;">
            Insira o código de 6 dígitos que enviamos para a sua caixa de entrada para ativar sua conta.
        </p>

        <?php if ($mensagemErro): ?>
            <div style="background: rgba(231, 76, 60, 0.2); color: #e74c3c; padding: 12px; border-radius: var(--radius); margin-bottom: 16px; font-size: 14px; border: 1px solid #e74c3c;">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($mensagemErro) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagemSucesso): ?>
            <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 12px; border-radius: var(--radius); margin-bottom: 16px; font-size: 14px; border: 1px solid #2ecc71;">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensagemSucesso) ?>
            </div>
        <?php endif; ?>

        <!-- Formulário de Validação -->
        <form action="verificar_email.php" method="POST" style="margin-bottom: 20px;">
            <input type="hidden" name="acao_verificar" value="1">
            <div class="form-group" style="margin-bottom: 20px;">
                <input type="text" name="codigo" class="form-control" placeholder="000000" maxlength="6" required 
                       style="font-size: 28px; text-align: center; letter-spacing: 8px; font-weight: bold; padding: 12px; font-family: monospace;">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Validar Conta Agora</button>
        </form>

        <!-- Form de Reenvio -->
        <form action="verificar_email.php" method="POST">
            <input type="hidden" name="acao_reenviar" value="1">
            <button type="submit" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 13px; text-decoration: underline;">
                Não recebeu o código? Reenviar e-mail
            </button>
        </form>
    </div>
</main>

<?php include_once dirname(__DIR__) . '/includes/footer.php'; ?>

</body>
</html>