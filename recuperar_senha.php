<?php
require_once __DIR__ . '/includes/csrf.php';   // Sessao endurecida + CSRF
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/documentos.php';    // JC_CONTATO_EMAIL (aviso de indisponibilidade)
require_once __DIR__ . '/includes/recuperacao.php';   // estado do fluxo (remediação, Lote A)

/* ============================================================
   recuperar_senha.php — "Esqueci minha senha" (jovens e empresas)

   Remediação, Lote A (AUD-001 / SEG-01): a autorização para trocar a
   senha fica VINCULADA à conta cujo código foi conferido (tabela + id +
   e-mail lidos do banco). Todo pedido novo apaga o estado anterior da
   sessão; o código é de uso único e consumido na hora; a nova senha só é
   gravada na conta vinculada, dentro do prazo, e encerra a autorização.
   Regras em includes/recuperacao.php.

   Falha de envio não vira "código enviado": sem e-mail instalado e
   configurado, a página avisa que a recuperação está indisponível (vale
   para qualquer e-mail digitado, então não revela contas); se o envio de
   um código falhar, ele é apagado e a pessoa é avisada para tentar de novo.
   ============================================================ */

$mensagemErro = null;
$recuperacao_indisponivel = !jc_mailer_disponivel();
jc_rec_limpar_legado($_SESSION);   // fluxo aberto na versão anterior recomeça do início

// --- ETAPA 1: PEDIR O CÓDIGO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_solicitar_codigo'])) {
    csrf_require();
    jc_rec_limpar($_SESSION);   // pedido novo: nenhum pedido ou validação anterior sobrevive
    $emailInput = trim((string) ($_POST['email'] ?? ''));

    if ($emailInput === '' || mb_strlen($emailInput) > 100 || !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
        $mensagemErro = "Por favor, digite um e-mail válido.";
    } elseif ($recuperacao_indisponivel) {
        jc_log_seguranca('senha.recuperacao_indisponivel', 'aviso');
        $mensagemErro = jc_rec_msg_indisponivel();
    } else {
        $conta = jc_rec_buscar_conta($conn, $emailInput);
        $envio = 'sem_conta';
        if ($conta !== null) {
            $codigo = sprintf("%06d", random_int(0, 999999));
            jc_rec_gravar_codigo($conn, $conta, $codigo, date('Y-m-d H:i:s', time() + JC_REC_VALIDADE_SEG));
            if (enviarCodigoVerificacao($conta['email'], $conta['nome'], $codigo)) {
                $envio = 'ok';
            } else {
                jc_rec_invalidar($conn, $conta);   // o código não chegou a ninguém: não pode continuar valendo
                $envio = 'falhou';
            }
            unset($codigo);
        }
        jc_log_seguranca('senha.recuperacao_solicitada', $envio === 'falhou' ? 'aviso' : 'info',
                         ['conta_encontrada' => $conta !== null, 'envio' => $envio]);

        if ($envio === 'falhou') {
            $mensagemErro = "Não foi possível enviar o código agora. Tente novamente em alguns minutos.";
        } else {
            // Mesma tela com ou sem cadastro (não revela se o e-mail existe)
            jc_rec_iniciar($_SESSION, $emailInput, $conta);
        }
    }
}

// --- ETAPA 2: CONFERIR O CÓDIGO RECEBIDO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_validar_codigo_recuperacao'])) {
    csrf_require();
    if (jc_rec_etapa($_SESSION) !== 3) {   // já validado nesta sessão (ex.: clique duplo): segue para a etapa 3
        $codigoDigitado = trim((string) ($_POST['codigo'] ?? ''));
        $conta = jc_rec_conta_pendente($_SESSION);
        $agora = time();
        $codigoOk = $conta !== null && jc_rec_consumir_codigo(
            $conn, $conta, $codigoDigitado, date('Y-m-d H:i:s', $agora), date('Y-m-d H:i:s', $agora + JC_REC_VALIDADE_SEG)
        );

        if ($codigoOk) {
            jc_rec_autorizar($_SESSION, $agora);   // autorização SÓ para a conta deste pedido
            jc_session_regenerate();               // nova identificação de sessão para a etapa autorizada
            jc_log_seguranca('senha.codigo_validado', 'info', ['tipo' => $conta['tabela'] === 'jovens' ? 'jovem' : 'empresa']);
        } else {
            // Mesma mensagem para conta inexistente, código errado, expirado ou já usado
            $mensagemErro = "Código incorreto ou expirado.";
            jc_log_seguranca('senha.codigo_recusado', 'aviso');
        }
    }
}

// --- ETAPA 3: DEFINIR NOVA SENHA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_definir_nova_senha'])) {
    csrf_require();
    $conta = jc_rec_conta_autorizada($_SESSION);
    $novaSenha = (string) ($_POST['nova_senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

    if ($conta === null) {
        // Sem código conferido nesta sessão (ou prazo vencido): recomeça do zero, nada é alterado
        jc_log_seguranca('senha.redefinicao_recusada', 'aviso', ['motivo' => 'sem_autorizacao']);
        jc_rec_limpar($_SESSION);
        header("Location: recuperar_senha.php");
        exit;
    } elseif (strlen($novaSenha) < 8) {
        $mensagemErro = "A nova senha precisa ter pelo menos 8 caracteres.";
    } elseif ($novaSenha !== $confirmarSenha) {
        $mensagemErro = "As senhas não coincidem.";
    } elseif (jc_rec_trocar_senha($conn, $conta, password_hash($novaSenha, PASSWORD_DEFAULT), date('Y-m-d H:i:s'))) {
        $tipoLogin = ($conta['tabela'] === 'jovens') ? 'jovem' : 'empresa';
        jc_rec_limpar($_SESSION);
        jc_log_seguranca('senha.redefinida', 'info', ['tipo' => $tipoLogin]);
        $_SESSION['sucesso_login'] = "Senha redefinida com sucesso! Faça login com sua nova senha.";
        header("Location: {$tipoLogin}/login.php");
        exit;
    } else {
        // Prazo vencido ou um código mais novo foi pedido para esta conta depois da validação
        jc_rec_limpar($_SESSION);
        jc_log_seguranca('senha.redefinicao_recusada', 'aviso', ['motivo' => 'prazo_ou_pedido_novo']);
        $mensagemErro = "O prazo para definir a nova senha terminou ou um novo código foi pedido para esta conta. Solicite a recuperação novamente.";
    }
}

// --- REENVIAR E-MAIL / REINICIAR ---
if (isset($_GET['reenviar'])) {
    jc_rec_limpar($_SESSION);
    header("Location: recuperar_senha.php");
    exit;
}

// Autorização da etapa 3 vencida (página reaberta depois do prazo): avisa e recomeça
if ($mensagemErro === null && jc_rec_autorizacao_vencida($_SESSION)) {
    jc_rec_limpar($_SESSION);
    $mensagemErro = "O prazo para definir a nova senha terminou. Solicite a recuperação novamente.";
}

$etapa = jc_rec_etapa($_SESSION);
$emailMascarado = jc_rec_mascara($_SESSION);
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
        (function () {   // Aplica o tema salvo, como nas outras telas
            try { var t = localStorage.getItem('jc-theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}
        }());
    </script>
    
    <!-- Estilos identicos ao design da imagem.
         Cores pelas variáveis do style.css (antes fixas em hexadecimal —
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

        /* Marca oficial (versão vertical) no lugar do ícone 💼 + texto */
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

        /* Os ícones são <span> (o seletor só pegava <i>) — ficavam
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
            background-color: var(--accent-fill);   /* Azul da marca + texto escuro (5,5:1) */
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
        <!-- Marca oficial (vertical) -->
        <h1 class="brand-marca marca marca-vertical"><img src="assets/img/marca/jovemconnect-vertical.png" alt="JovemConnect" width="546" height="420"></h1>

        <?php if ($mensagemErro): ?>
            <div class="alert-error" role="alert">
                <span class="i i-alert-triangle" aria-hidden="true"></span> <?= htmlspecialchars($mensagemErro) ?>
            </div>
        <?php elseif ($etapa === 1 && $recuperacao_indisponivel): ?>
            <!-- Sem envio de e-mail instalado/configurado: avisa antes, em vez de fingir que mandou o código -->
            <div class="alert-error" role="status">
                <span class="i i-alert-triangle" aria-hidden="true"></span> <?= htmlspecialchars(jc_rec_msg_indisponivel()) ?>
            </div>
        <?php endif; ?>

        <?php if ($etapa === 1): ?>
            <!-- ETAPA 1: DIGITAR E-MAIL -->
            <h2 class="page-title">Recuperar senha</h2>
            <p class="page-desc">Informe seu e-mail para receber as instruções de redefinição.</p>

            <!-- Envio único — um 2º clique mandava outro código e o 1º e-mail deixava de valer -->
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

            <a href="jovem/login.php" class="back-link">Voltar para o login</a><!-- Login.php não existe na raiz (404) -->

        <?php elseif ($etapa === 2): ?>
            <!-- ETAPA 2: DIGITAR O CÓDIGO RECEBIDO -->
            <h2 class="page-title">Verifique seu e-mail</h2>
            <!-- Mesmo texto com ou sem cadastro: a página não revela se o e-mail existe -->
            <p class="page-desc">
                Se houver uma conta com o e-mail <span class="highlight-email"><?= htmlspecialchars($emailMascarado) ?></span>,
                enviamos para ele um código de 6 dígitos, válido por 15 minutos. Digite-o abaixo para continuar.
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

            <a href="jovem/login.php" class="back-link">Voltar para o login</a><!-- Login.php não existe na raiz (404) -->

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

            <!-- A última etapa não tinha caminho de volta -->
            <a href="jovem/login.php" class="back-link">Voltar para o login</a>
        <?php endif; ?>
    </div>

    <!-- Assistente + acessibilidade (as preferências do usuário passam a valer aqui também) -->
    <?php include __DIR__ . '/includes/assistente.php'; ?>

</body>
</html>