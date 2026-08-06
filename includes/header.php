<?php
if (defined('HEADER_ALREADY_INCLUDED')) return;
define('HEADER_ALREADY_INCLUDED', true);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>

<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jovem Aprendiz Conecta</title>
  <link rel="stylesheet" href="/jovemaprendiz_v1/assets/style.css">
</head>
<body>
  <header>
    <img src="/jovemaprendiz_v1/assets/logo.png" alt="Logo" class="logo">
    <nav>
      <a href="/jovemaprendiz_v1/index.php">Início</a>
      <a href="/jovemaprendiz_v1/pages/vagas.php">Vagas</a>

      <?php if (!isset($_SESSION["logado"])): ?>
        <a href="/jovemaprendiz_v1/pages/login.php">Entrar</a>
        <a href="/jovemaprendiz_v1/pages/login.php?tipo=jovem">Cadastrar</a>
      <?php else: ?>
        <a href="/jovemaprendiz_v1/logout.php">Sair</a>
      <?php endif; ?>
    </nav>
  </header>