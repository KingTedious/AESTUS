<?php
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF
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
    csrf_require();
    $novoTelefone = trim($_POST['telefone'] ?? '');

    if (empty($novoTelefone) || normalizarTelefoneE164($novoTelefone) === null) {
        $mensagemErro = "Digite um número de celular válido, com DDD (ex: (11) 98765-4321).";
    } else {
        $novoCodigo = sprintf("%06d", random_int(0, 999999));
        $novaExpiracao = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $update = $conn->prepare("UPDATE {$tabela} SET telefone = ?, codigo_telefone = ?, expiracao_telefone = ? WHERE id = ?");
        $update->bind_param("sssi", $novoTelefone, $novoCodigo, $novaExpiracao, $idUsuario);
        $update->execute();
        $update->close();

        $sms_ok = enviarCodigoSMS($novoTelefone, $novoCodigo);
        jc_log_seguranca('verificacao.telefone.enviado', $sms_ok ? 'info' : 'aviso', ['ok' => (bool) $sms_ok]);
        if ($sms_ok) {
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
    csrf_require();
    $codigoDigitado = trim($_POST['codigo'] ?? '');
    $agora = date('Y-m-d H:i:s');

    if (!empty($codigoDigitado) && $user['codigo_telefone'] !== null
        && hash_equals((string) $user['codigo_telefone'], $codigoDigitado)
        && $user['expiracao_telefone'] >= $agora) {
        $update = $conn->prepare("UPDATE {$tabela} SET telefone_verificado = 1, codigo_telefone = NULL, expiracao_telefone = NULL WHERE id = ?");
        $update->bind_param("i", $idUsuario);
        $update->execute();
        $update->close();

        jc_log_seguranca('verificacao.telefone.sucesso', 'info');
        header("Location: ../dashboard/index.php?phone_verified=1");
        exit;
    } else {
        jc_log_seguranca('verificacao.telefone.falha', 'aviso');
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
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>

<?php include_once dirname(__DIR__) . '/includes/header.php'; ?>

<!-- 60 px da barra fixa + 40 px de respiro -->
<main class="container verificacao-main" style="padding: 100px 16px 40px; max-width: 480px; margin: 0 auto;">
    <div class="card" style="text-align: center; padding: 32px 24px;">
        <div class="icone-destaque" aria-hidden="true"><span class="i i-smartphone" aria-hidden="true"></span></div>
        
        <h2>Verificar Telefone (SMS)</h2>
        <p class="text-muted" style="margin-bottom: 24px; font-size: 14px; line-height: 1.5;">
            Confirme seu celular para aumentar a segurança da sua conta e habilitar recursos exclusivos.
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

        <!-- Form 1: Celular -->
        <form action="verificar_telefone.php" method="POST" style="margin-bottom: 24px;" data-envio-unico="Enviando SMS...">
            <input type="hidden" name="acao_enviar_sms" value="1">
            <?= csrf_field() ?>
            <div class="form-group" style="margin-bottom: 12px; text-align: left;">
                <label class="form-label" for="telCelular">Número do Celular (com DDD)</label>
                <input type="tel" name="telefone" id="telCelular" autocomplete="tel" class="form-control" placeholder="(11) 99999-9999" 
                       value="<?= htmlspecialchars($user['telefone'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn btn-secondary btn-full">
                <span class="i i-send" aria-hidden="true"></span> <?= !empty($user['telefone']) ? 'Reenviar SMS' : 'Enviar Código SMS' ?>
            </button>
        </form>

        <!-- Form 2: Validar Código -->
        <?php if (!empty($user['telefone'])): ?>
            <hr style="border-color: var(--border); margin: 24px 0;">

            <form action="verificar_telefone.php" method="POST" data-envio-unico="Confirmando...">
                <input type="hidden" name="acao_validar_codigo" value="1">
            <?= csrf_field() ?>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="telCodigo">Digite o código de 6 dígitos recebido</label>
                    <input type="text" name="codigo" id="telCodigo" inputmode="numeric" autocomplete="one-time-code" class="form-control" placeholder="000000" maxlength="6" required 
                           style="font-size: 28px; text-align: center; letter-spacing: 8px; font-weight: bold; padding: 10px; font-family: monospace;">
                </div>
                <button type="submit" class="btn btn-primary btn-full">Confirmar Código</button>
            </form>
        <?php endif; ?>
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