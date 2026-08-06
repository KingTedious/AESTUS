<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$segments = explode('/', trim($scriptPath, '/'));
$basePath = isset($segments[0]) && $segments[0] !== '' ? '/' . $segments[0] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JovemConnect</title>
  <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
  <header class="navbar">
    <div class="nav-logo">
      <img src="<?php echo $basePath; ?>/assets/logo.svg" alt="Logo JovemConnect">
      <h1>Jovem<span>Connect</span></h1>
    </div>

    <nav class="nav-links">
      <a href="<?php echo $basePath; ?>/index.php">Sobre</a>
      <a href="<?php echo $basePath; ?>/pages/login.php">Entrar</a>
      <a href="<?php echo $basePath; ?>/pages/cadastro_jovem.php" class="btn-nav">Comecar</a>
      <button id="themeToggle" class="theme-btn" title="Alternar tema">Tema</button>
    </nav>
  </header>
