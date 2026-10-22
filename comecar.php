<?php
/* ============================================================
   comecar.php — destino do botão "Começar": o visitante escolhe se é Jovem
   ou Empresa antes de ir para o cadastro correspondente.
   ============================================================ */
require_once __DIR__ . '/includes/session.php';
jc_session_start();

// Já logado → painel correto (mesma regra do index.php)
if (isset($_SESSION['jovem_id'])) {
    header("Location: dashboard/jovem.php");
    exit;
}
if (isset($_SESSION['empresa_id'])) {
    header("Location: dashboard/empresa.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Começar | JovemConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="assets/img/marca/jovemconnect-icone-180.png">
    <script>
        (function () {
            var t = localStorage.getItem('jc-theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
        }());
    </script>
</head>
<body>

<div class="form-page">
<div class="form-container">

    <!-- Marca oficial vertical (leva à página inicial) -->
    <div class="form-page-logo">
        <a href="index.php" class="marca marca-vertical nav-logo" aria-label="JovemConnect — página inicial">
            <img class="marca-tema-escuro" src="assets/img/marca/jovemconnect-vertical.png" alt="JovemConnect" width="878" height="689"><img class="marca-tema-claro" src="assets/img/marca/jovemconnect-vertical-claro.png" alt="JovemConnect" width="878" height="689" loading="lazy">
        </a>
    </div>

    <div class="form-card" style="text-align:center;">
        <div class="form-page-title" style="justify-content:center;">
            <h2>Como você quer começar?</h2>
        </div>
        <p class="form-page-subtitle">Escolha o tipo de conta para fazer o seu cadastro.</p>

        <div style="display:flex;flex-direction:column;gap:12px;margin-top:20px;">
            <a href="jovem/cadastro.php" class="btn btn-primary btn-lg"><span class="i i-graduation-cap" aria-hidden="true"></span> Sou Jovem</a>
            <a href="empresa/cadastro.php" class="btn btn-secondary btn-lg"><span class="i i-building" aria-hidden="true"></span> Sou Empresa</a>
        </div>

        <p class="text-secondary" style="font-size:13px;margin-top:20px;">
            Já tem conta? <a href="jovem/login.php">Entrar</a>
        </p>
    </div>

    <p style="text-align:center;margin-top:16px;">
        <a href="index.php" class="back-link"><span class="i i-arrow-left" aria-hidden="true"></span> Voltar para a página inicial</a>
    </p>

</div>
</div>

<!-- Assistente + acessibilidade (as preferências do usuário passam a valer aqui também) -->
<?php include __DIR__ . '/includes/assistente.php'; ?>

</body>
</html>
