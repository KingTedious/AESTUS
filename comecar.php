<?php
/* ============================================================
   comecar.php  (Stage 3)

   Destino do botão "Começar": o visitante escolhe claramente se é
   Jovem ou Empresa antes de ir para o cadastro correspondente.
   Antes, o "Começar" do index.php levava direto ao cadastro de
   jovem e o do header público (includes/header.php) levava ao
   login de empresa — nenhum dos dois oferecia a escolha.
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

    <div class="form-page-logo">
        <a href="index.php" class="logo-inner" style="text-decoration:none;">
            <span class="logo-box">💼</span>
            <span class="logo-jovem">Jovem</span><span class="logo-connect">Connect</span>
        </a>
    </div>

    <div class="form-card" style="text-align:center;">
        <div class="form-page-title" style="justify-content:center;">
            <h2>Como você quer começar?</h2>
        </div>
        <p class="form-page-subtitle">Escolha o tipo de conta para fazer o seu cadastro.</p>

        <div style="display:flex;flex-direction:column;gap:12px;margin-top:20px;">
            <a href="jovem/cadastro.php" class="btn btn-primary btn-lg">Sou Jovem 🎓</a>
            <a href="empresa/cadastro.php" class="btn btn-secondary btn-lg">Sou Empresa 💼</a>
        </div>

        <p class="text-secondary" style="font-size:13px;margin-top:20px;">
            Já tem conta? <a href="jovem/login.php">Entrar</a>
        </p>
    </div>

    <p style="text-align:center;margin-top:16px;">
        <a href="index.php" class="back-link">← Voltar para a página inicial</a>
    </p>

</div>
</div>

</body>
</html>
