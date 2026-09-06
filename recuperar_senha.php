<?php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

$mensagemErro = null;
$etapa = $_SESSION['recuperacao_etapa'] ?? 1;
$emailMascarado = $_SESSION['recuperacao_email_mascarado'] ?? '';

// Função para mascarar o e-mail (ex: gabriel@gmail.com -> ga***el@gmail.com)
function mascararEmail(string $email): string {
    $partes = explode('@', $email);
    if (count($partes) !== 2) return $email;
    
    $usuario = $partes[0];
    $dominio = $partes[1];
    $tamanho = strlen($usuario);

    if ($tamanho <= 2) {
        $mascarado = substr($usuario, 0, 1) . '***';
    } else {
        $mascarado = substr($usuario, 0, 2) . '***' . substr($usuario, -1);
    }

    return $mascarado . '@' . $dominio;
}

// --- ETAPA 1: ENVIAR O E-MAIL DE RECUPERAÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_solicitar_codigo'])) {
    $emailInput = trim($_POST['email'] ?? '');

    if (empty($emailInput) || !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
        $mensagemErro = "Por favor, digite um e-mail válido.";
    } else {
        $tabelaEncontrada = null;
        $nomeUsuario = '';

        // Busca em jovens
        $stmt = $conn->prepare("SELECT id, nome FROM jovens WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $emailInput);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $tabelaEncontrada = 'jovens';
            $user = $res->fetch_assoc();
            $nomeUsuario = $user['nome'];
        } else {
            $stmt->close();
            // Busca em empresas
            $stmt = $conn->prepare("SELECT id, nome_empresa AS nome FROM empresas WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $emailInput);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $tabelaEncontrada = 'empresas';
                $user = $res->fetch_assoc();
                $nomeUsuario = $user['nome'];
            }
        }
        $stmt->close();

        if ($tabelaEncontrada) {
            $codigo = sprintf("%06d", random_int(0, 999999));
            $expiracao = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $update = $conn->prepare("UPDATE {$tabelaEncontrada} SET codigo_recuperacao = ?, expiracao_recuperacao = ? WHERE email = ?");
            $update->bind_param("sss", $codigo, $expiracao, $emailInput);
            $update->execute();
            $update->close();

            // Dispara e-mail
            enviarCodigoVerificacao($emailInput, $nomeUsuario, $codigo);
        }

        // Avança a etapa mesmo se o e-mail não existir (boa prática de segurança)
        $_SESSION['recuperacao_etapa'] = 2;
        $_SESSION['recuperacao_email_mascarado'] = mascararEmail($emailInput);
        $_SESSION['recuperacao_email_raw'] = $emailInput;
        $etapa = 2;
        $emailMascarado = $_SESSION['recuperacao_email_mascarado'];
    }
}

// --- REENVIAR E-MAIL / REINICIAR ---
if (isset($_GET['reenviar'])) {
    unset($_SESSION['recuperacao_etapa'], $_SESSION['recuperacao_email_mascarado']);
    header("Location: recuperar_senha.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha | JovemConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Estilos identicos ao design da imagem -->
    <style>
        body {
            background-color: #121214;
            color: #ffffff;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .auth-card {
            background-color: #1e1e24;
            width: 100%;
            max-width: 440px;
            padding: 40px 32px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            text-align: center;
            box-sizing: border-box;
        }

        .brand-icon {
            width: 52px;
            height: 52px;
            background-color: #3b82f6;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px auto;
            color: #ffffff;
            font-size: 22px;
        }

        .brand-title {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 24px 0;
            color: #ffffff;
        }

        .page-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: #ffffff;
        }

        .page-desc {
            font-size: 14px;
            color: #a1a1aa;
            line-height: 1.5;
            margin: 0 0 28px 0;
        }

        .highlight-email {
            color: #3b82f6;
            font-weight: 600;
            word-break: break-all;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            color: #a1a1aa;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #71717a;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px 12px 40px;
            background-color: #27272a;
            border: 1px solid #3f3f46;
            border-radius: 8px;
            color: #ffffff;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #3b82f6;
        }

        .btn-primary {
            width: 100%;
            padding: 12px;
            background-color: #3b82f6;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            display: inline-block;
            box-sizing: border-box;
        }

        .btn-primary:hover {
            background-color: #2563eb;
        }

        .btn-secondary {
            width: 100%;
            padding: 12px;
            background-color: transparent;
            color: #a1a1aa;
            border: 1px solid #3f3f46;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            box-sizing: border-box;
        }

        .btn-secondary:hover {
            background-color: #27272a;
            color: #ffffff;
        }

        .back-link {
            display: inline-block;
            margin-top: 24px;
            color: #3b82f6;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #f87171;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: left;
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <!-- Ícone + Nome da Marca -->
        <div class="brand-icon">
            <i class="fa-solid fa-briefcase"></i>
        </div>
        <h1 class="brand-title">JovemConnect</h1>

        <?php if ($mensagemErro): ?>
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($mensagemErro) ?>
            </div>
        <?php endif; ?>

        <?php if ($etapa === 1): ?>
            <!-- ETAPA 1: DIGITAR E-MAIL -->
            <h2 class="page-title">Recuperar senha</h2>
            <p class="page-desc">Informe seu e-mail para receber as instruções de redefinição.</p>

            <form action="recuperar_senha.php" method="POST">
                <input type="hidden" name="acao_solicitar_codigo" value="1">
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="seu@email.com" required autofocus>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Enviar e-mail</button>
            </form>

            <a href="login.php" class="back-link">Voltar para o login</a>

        <?php else: ?>
            <!-- ETAPA 2: TELA DE CONFIRMAÇÃO (COMO VOCÊ PEDIU) -->
            <h2 class="page-title">Verifique seu e-mail</h2>
            <p class="page-desc">
                Mandamos um e-mail para <span class="highlight-email"><?= htmlspecialchars($emailMascarado) ?></span> para recuperar sua senha, siga o processo e atualize a página.
            </p>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="recuperar_senha.php?reenviar=1" class="btn-secondary">
                    Não recebi meu e-mail
                </a>
            </div>

            <a href="login.php" class="back-link">Voltar para o login</a>
        <?php endif; ?>
    </div>

</body>
</html>