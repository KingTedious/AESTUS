<?php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/sms.php';

$tipoUsuario = isset($_SESSION['jovem_id']) ? 'jovem' : (isset($_SESSION['empresa_id']) ? 'empresa' : null);
$idUsuario = $_SESSION['jovem_id'] ?? $_SESSION['empresa_id'] ?? null;

if (!$tipoUsuario || !$idUsuario) {
    header("Location: ../index.php");
    exit;
}

$tabela = ($tipoUsuario === 'jovem') ? 'jovens' : 'empresas';
$mensagemSucesso = null;
$mensagemErro = null;

// Busca dados atuais do usuário
$stmt = $conn->prepare("SELECT telefone, telefone_verificado, codigo_telefone, expiracao_telefone FROM {$tabela} WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user['telefone_verificado'] == 1) {
    header("Location: ../dashboard/index.php");
    exit;
}

// 1. SOLICITAR / REENVIAR CÓDIGO SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_enviar_sms'])) {
    $novoTelefone = trim($_POST['telefone'] ?? '');

    if (empty($novoTelefone)) {
        $mensagemErro = "Por favor, digite um número de telefone válido.";
    } else {
        $novoCodigo = sprintf("%06d", random_int(0, 999999));
        $novaExpiracao = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $update = $conn->prepare("UPDATE {$tabela} SET telefone = ?, codigo_telefone = ?, expiracao_telefone = ? WHERE id = ?");
        $update->bind_param("sssi", $novoTelefone, $novoCodigo, $novaExpiracao, $idUsuario);
        $update->execute();
        $update->close();

        if (enviarCodigoSMS($novoTelefone, $novoCodigo)) {
            $mensagemSucesso = "Código SMS enviado com sucesso para " . htmlspecialchars($novoTelefone) . "!";
            $user['telefone'] = $novoTelefone;
            $user['codigo_telefone'] = $novoCodigo;
            $user['expiracao_telefone'] = $novaExpiracao;
        } else {
            $mensagemErro = "Falha ao enviar SMS. Verifique o número e tente novamente.";
        }
    }
}

// 2. VALIDAR CÓDIGO DIGITADO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_validar_codigo'])) {
    $codigoDigitado = trim($_POST['codigo'] ?? '');
    $agora = date('Y-m-d H:i:s');

    if (!empty($codigoDigitado) && $user['codigo_telefone'] === $codigoDigitado && $user['expiracao_telefone'] >= $agora) {
        $update = $conn->prepare("UPDATE {$tabela} SET telefone_verificado = 1, codigo_telefone = NULL, expiracao_telefone = NULL WHERE id = ?");
        $update->bind_param("i", $idUsuario);
        $update->execute();
        $update->close();

        header("Location: ../dashboard/index.php?phone_verified=1");
        exit;
    } else {
        $mensagemErro = "Código incorreto ou expirado. Tente novamente.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação por Telefone | JovemConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<?php include_once dirname(__DIR__) . '/includes/header.php'; ?>

<main class="container" style="padding: 40px 16px; max-width: 480px; margin: 0 auto;">
    <div class="card" style="text-align: center; padding: 32px 24px;">
        <div style="font-size: 48px; color: var(--accent); margin-bottom: 16px;">
            <i class="fa-solid fa-mobile-screen-button"></i>
        </div>
        
        <h2>Verificar Telefone (SMS)</h2>
        <p class="text-muted" style="margin-bottom: 24px; font-size: 14px; line-height: 1.5;">
            Confirme seu celular para aumentar a segurança da sua conta e habilitar recursos exclusivos.
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

        <!-- Form 1: Celular -->
        <form action="verificar_telefone.php" method="POST" style="margin-bottom: 24px;">
            <input type="hidden" name="acao_enviar_sms" value="1">
            <div class="form-group" style="margin-bottom: 12px; text-align: left;">
                <label class="form-label">Número do Celular (com DDD)</label>
                <input type="tel" name="telefone" class="form-control" placeholder="(11) 99999-9999" 
                       value="<?= htmlspecialchars($user['telefone'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn btn-secondary btn-full">
                <i class="fa-solid fa-paper-plane"></i> <?= !empty($user['telefone']) ? 'Reenviar SMS' : 'Enviar Código SMS' ?>
            </button>
        </form>

        <!-- Form 2: Validar Código -->
        <?php if (!empty($user['telefone'])): ?>
            <hr style="border-color: var(--border); margin: 24px 0;">

            <form action="verificar_telefone.php" method="POST">
                <input type="hidden" name="acao_validar_codigo" value="1">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Digite o código de 6 dígitos recebido</label>
                    <input type="text" name="codigo" class="form-control" placeholder="000000" maxlength="6" required 
                           style="font-size: 28px; text-align: center; letter-spacing: 8px; font-weight: bold; padding: 10px; font-family: monospace;">
                </div>
                <button type="submit" class="btn btn-primary btn-full">Confirmar Código</button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php include_once dirname(__DIR__) . '/includes/footer.php'; ?>

</body>
</html>