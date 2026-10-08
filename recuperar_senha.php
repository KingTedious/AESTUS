<?php
require_once __DIR__ . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';

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
    csrf_require();
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

            $_SESSION['recuperacao_tabela'] = $tabelaEncontrada;
        }

        // Avança a etapa mesmo se o e-mail não existir (boa prática de segurança)
        $_SESSION['recuperacao_etapa'] = 2;
        $_SESSION['recuperacao_email_mascarado'] = mascararEmail($emailInput);
        $_SESSION['recuperacao_email_raw'] = $emailInput;
        $etapa = 2;
        $emailMascarado = $_SESSION['recuperacao_email_mascarado'];
    }
}

// --- ETAPA 2: VALIDAR O CÓDIGO RECEBIDO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_validar_codigo_recuperacao'])) {
    csrf_require();
    $codigoDigitado = trim($_POST['codigo'] ?? '');
    $tabela   = $_SESSION['recuperacao_tabela']    ?? null;
    $emailRaw = $_SESSION['recuperacao_email_raw'] ?? null;
    $codigoOk = false;

    if ($tabela && $emailRaw && !empty($codigoDigitado)) {
        $stmtV = $conn->prepare("SELECT codigo_recuperacao, expiracao_recuperacao FROM {$tabela} WHERE email = ? LIMIT 1");
        $stmtV->bind_param("s", $emailRaw);
        $stmtV->execute();
        $rowV = $stmtV->get_result()->fetch_assoc();
        $stmtV->close();

        $agora = date('Y-m-d H:i:s');
        if ($rowV && $rowV['codigo_recuperacao'] !== null
            && hash_equals((string) $rowV['codigo_recuperacao'], $codigoDigitado)
            && $rowV['expiracao_recuperacao'] >= $agora) {
            $codigoOk = true;
        }
    }

    if ($codigoOk) {
        $_SESSION['recuperacao_etapa'] = 3;
        $_SESSION['recuperacao_codigo_ok'] = true;  // CRIT-001: etapa 2 valida o codigo e so entao autoriza a etapa 3
        $etapa = 3;
    } else {
        // Mesma mensagem independentemente do motivo (conta inexistente, código
        // errado ou expirado) — evita confirmar/negar existência da conta.
        $mensagemErro = "Código incorreto ou expirado.";
    }
}

// --- ETAPA 3: DEFINIR NOVA SENHA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_definir_nova_senha'])) {
    csrf_require();
    $tabela   = $_SESSION['recuperacao_tabela']    ?? null;
    $emailRaw = $_SESSION['recuperacao_email_raw'] ?? null;
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if (!$tabela || !$emailRaw || empty($_SESSION['recuperacao_codigo_ok'])) {
        // Sessão perdida/expirada no meio do fluxo — recomeça do zero.
        unset($_SESSION['recuperacao_etapa'], $_SESSION['recuperacao_email_mascarado'],
              $_SESSION['recuperacao_email_raw'], $_SESSION['recuperacao_tabela'], $_SESSION['recuperacao_codigo_ok']);
        header("Location: recuperar_senha.php");
        exit;
    } elseif (strlen($novaSenha) < 8) {
        $mensagemErro = "A nova senha precisa ter pelo menos 8 caracteres.";
        $etapa = 3;
    } elseif ($novaSenha !== $confirmarSenha) {
        $mensagemErro = "As senhas não coincidem.";
        $etapa = 3;
    } else {
        // Reconfirma a validade do código (defesa extra contra expiração entre etapas)
        $stmtV = $conn->prepare("SELECT expiracao_recuperacao FROM {$tabela} WHERE email = ? LIMIT 1");
        $stmtV->bind_param("s", $emailRaw);
        $stmtV->execute();
        $rowV = $stmtV->get_result()->fetch_assoc();
        $stmtV->close();

        $agora = date('Y-m-d H:i:s');
        if (!$rowV || $rowV['expiracao_recuperacao'] === null || $rowV['expiracao_recuperacao'] < $agora) {
            unset($_SESSION['recuperacao_etapa'], $_SESSION['recuperacao_email_mascarado'],
                  $_SESSION['recuperacao_email_raw'], $_SESSION['recuperacao_tabela'], $_SESSION['recuperacao_codigo_ok']);
            $mensagemErro = "O código expirou. Solicite a recuperação novamente.";
            $etapa = 1;
        } else {
            $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE {$tabela} SET senha = ?, codigo_recuperacao = NULL, expiracao_recuperacao = NULL WHERE email = ?");
            $update->bind_param("ss", $novoHash, $emailRaw);
            $update->execute();
            $update->close();

            $tipoLogin = ($tabela === 'jovens') ? 'jovem' : 'empresa';
            unset($_SESSION['recuperacao_etapa'], $_SESSION['recuperacao_email_mascarado'],
                  $_SESSION['recuperacao_email_raw'], $_SESSION['recuperacao_tabela'], $_SESSION['recuperacao_codigo_ok']);

            $_SESSION['sucesso_login'] = "Senha redefinida com sucesso! Faça login com sua nova senha.";
            header("Location: {$tipoLogin}/login.php");
            exit;
        }
    }
}

// --- REENVIAR E-MAIL / REINICIAR ---
if (isset($_GET['reenviar'])) {
    unset($_SESSION['recuperacao_etapa'], $_SESSION['recuperacao_email_mascarado'],
          $_SESSION['recuperacao_email_raw'], $_SESSION['recuperacao_tabela'], $_SESSION['recuperacao_codigo_ok']);
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
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="assets/img/marca/jovemconnect-icone-180.png">
    <script>
        (function () {   // Stage 4: aplica o tema salvo, como nas outras telas
            try { var t = localStorage.getItem('jc-theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}
        }());
    </script>
    
    <!-- Estilos identicos ao design da imagem.
         Stage 4: cores pelas variáveis do style.css (antes fixas em hexadecimal —
         a página ficava sempre escura e o alto contraste/dislexia não funcionavam) -->
    <style>
        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            font-family: var(--font);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .auth-card {
            background-color: var(--bg-card);
            width: 100%;
            max-width: 440px;
            padding: 40px 32px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            text-align: center;
            box-sizing: border-box;
        }

        /* Stage 5: marca oficial (versão vertical) no lugar do ícone 💼 + texto */
        .brand-marca {
            margin: 0 0 24px 0;
        }

        .page-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: var(--text-primary);
        }

        .page-desc {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin: 0 0 28px 0;
        }

        .highlight-email {
            color: var(--accent);
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
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .input-wrapper {
            position: relative;
        }

        /* Stage 4: os ícones são <span> (o seletor só pegava <i>) — ficavam
           numa linha separada, acima do campo */
        .input-wrapper i,
        .input-wrapper > span {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px 12px 40px;
            background-color: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-subtle);
        }

        .btn-primary {
            width: 100%;
            padding: 12px;
            background-color: var(--accent-fill);   /* Stage 5: azul da marca + texto escuro (5,5:1) */
            color: var(--on-accent);
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
            background-color: var(--accent-fill-hover);
        }

        .btn-primary:disabled {
            opacity: .7;
            cursor: wait;
        }

        .btn-secondary {
            width: 100%;
            padding: 12px;
            background-color: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
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
            background-color: var(--bg-hover);
            color: var(--text-primary);
        }

        .back-link {
            display: inline-block;
            margin-top: 24px;
            color: var(--accent);
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

        [data-theme="light"] .alert-error { color: #b91c1c; }
    </style>
</head>
<body>

    <div class="auth-card">
        <!-- Stage 5: marca oficial (vertical) -->
        <h1 class="brand-marca marca marca-vertical"><img src="assets/img/marca/jovemconnect-vertical.png" alt="JovemConnect" width="546" height="420"></h1>

        <?php if ($mensagemErro): ?>
            <div class="alert-error" role="alert">
                <span class="i i-alert-triangle" aria-hidden="true"></span> <?= htmlspecialchars($mensagemErro) ?>
            </div>
        <?php endif; ?>

        <?php if ($etapa === 1): ?>
            <!-- ETAPA 1: DIGITAR E-MAIL -->
            <h2 class="page-title">Recuperar senha</h2>
            <p class="page-desc">Informe seu e-mail para receber as instruções de redefinição.</p>

            <!-- Stage 4: envio único — um 2º clique mandava outro código e o 1º e-mail deixava de valer -->
            <form action="recuperar_senha.php" method="POST" data-envio-unico="Enviando...">
                <input type="hidden" name="acao_solicitar_codigo" value="1">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="recEmail">E-mail</label>
                    <div class="input-wrapper">
                        <span class="i i-mail" aria-hidden="true"></span>
                        <input type="email" name="email" id="recEmail" class="form-control" placeholder="seu@email.com" required autofocus autocomplete="email">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Enviar e-mail</button>
            </form>

            <a href="jovem/login.php" class="back-link">Voltar para o login</a><!-- Stage 3: login.php não existe na raiz (404) -->

        <?php elseif ($etapa === 2): ?>
            <!-- ETAPA 2: DIGITAR O CÓDIGO RECEBIDO -->
            <h2 class="page-title">Verifique seu e-mail</h2>
            <p class="page-desc">
                Mandamos um código para <span class="highlight-email"><?= htmlspecialchars($emailMascarado) ?></span>. Digite-o abaixo para continuar.
            </p>

            <form action="recuperar_senha.php" method="POST" data-envio-unico="Confirmando...">
                <input type="hidden" name="acao_validar_codigo_recuperacao" value="1">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="recCodigo">Código de verificação</label>
                    <div class="input-wrapper">
                        <span class="i i-key" aria-hidden="true"></span>
                        <input type="text" name="codigo" id="recCodigo" class="form-control" placeholder="000000" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required autofocus autocomplete="one-time-code" title="Digite os 6 números do código">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Confirmar código</button>
            </form>

            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 12px;">
                <a href="recuperar_senha.php?reenviar=1" class="btn-secondary">
                    Não recebi meu e-mail
                </a>
            </div>

            <a href="jovem/login.php" class="back-link">Voltar para o login</a><!-- Stage 3: login.php não existe na raiz (404) -->

        <?php else: ?>
            <!-- ETAPA 3: DEFINIR NOVA SENHA -->
            <h2 class="page-title">Defina sua nova senha</h2>
            <p class="page-desc">Escolha uma nova senha para acessar sua conta.</p>

            <form action="recuperar_senha.php" method="POST" data-envio-unico="Salvando...">
                <input type="hidden" name="acao_definir_nova_senha" value="1">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="recNovaSenha">Nova senha</label>
                    <div class="input-wrapper">
                        <span class="i i-lock" aria-hidden="true"></span>
                        <input type="password" name="nova_senha" id="recNovaSenha" class="form-control" placeholder="Mínimo de 8 caracteres" minlength="8" required autofocus autocomplete="new-password">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="recConfirmarSenha">Confirmar nova senha</label>
                    <div class="input-wrapper">
                        <span class="i i-lock" aria-hidden="true"></span>
                        <input type="password" name="confirmar_senha" id="recConfirmarSenha" class="form-control" placeholder="Repita a nova senha" minlength="8" required autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Redefinir senha</button>
            </form>

            <!-- Stage 4: a última etapa não tinha caminho de volta -->
            <a href="jovem/login.php" class="back-link">Voltar para o login</a>
        <?php endif; ?>
    </div>

    <!-- Stage 4: assistente + acessibilidade (as preferências do usuário passam a valer aqui também) -->
    <?php include __DIR__ . '/includes/assistente.php'; ?>

</body>
</html>