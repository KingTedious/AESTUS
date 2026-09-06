<?php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$tipoUsuario = $_SESSION['jovem_id'] ?? $_SESSION['empresa_id'] ?? null;
if (!$tipoUsuario) {
    header("Location: login.php");
    exit;
}

$tipo = isset($_SESSION['jovem_id']) ? 'jovem' : 'empresa';
$idUsuario = $_SESSION['jovem_id'] ?? $_SESSION['empresa_id'];
$tabela = ($tipo === 'jovem') ? 'jovens' : 'empresas';

// Busca status de verificação atual
$stmt = $conn->prepare("SELECT email, telefone, email_verificado FROM {$tabela} WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Se já tiver verificado por algum dos dois meios, redireciona
if ($user['email_verificado'] == 1) {
    header("Location: ../dashboard/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escolha a Verificação | JovemConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        .option-card {
            background: var(--bg-card, #1e293b);
            border: 1px solid var(--border, #334155);
            border-radius: var(--radius, 12px);
            padding: 24px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, border-color 0.2s;
        }
        .option-card:hover {
            transform: translateY(-4px);
            border-color: var(--accent, #4b8ef1);
        }
        .option-card i {
            font-size: 36px;
            color: var(--accent, #4b8ef1);
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

<?php include_once dirname(__DIR__) . '/includes/header.php'; ?>

<main class="container" style="padding: 40px 16px; max-width: 560px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 32px;">
        <h2>Como deseja verificar sua conta?</h2>
        <p class="text-muted">Escolha o método mais conveniente para receber o seu código de segurança.</p>
    </div>

    <div class="options-grid">
        <!-- Opção E-mail -->
        <a href="verificar_email.php" class="option-card">
            <i class="fa-solid fa-envelope"></i>
            <h3>Via E-mail</h3>
            <p class="text-muted" style="font-size: 13px; margin-top: 8px;">
                Enviaremos um código para:<br>
                <strong style="color: #fff;"><?= htmlspecialchars($user['email']) ?></strong>
            </p>
        </a>

        <!-- Opção Telefone / SMS -->
        <a href="verificar_telefone.php" class="option-card">
            <i class="fa-solid fa-comment-sms"></i>
            <h3>Via SMS</h3>
            <p class="text-muted" style="font-size: 13px; margin-top: 8px;">
                Enviaremos um código por SMS para o seu celular com DDD.
            </p>
        </a>
    </div>
</main>

<?php include_once dirname(__DIR__) . '/includes/footer.php'; ?>

</body>
</html>