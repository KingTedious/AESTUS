<?php
$tipo = $_GET['tipo'] ?? 'jovem';
$titulo = ($tipo === 'empresa') ? 'Entrar como Empresa' : 'Entrar como Jovem';
$linkCadastro = ($tipo === 'empresa') ? 'cadastro_empresa.php' : 'cadastro_jovem.php';
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$segments = explode('/', trim($scriptPath, '/'));
$basePath = isset($segments[0]) && $segments[0] !== '' ? '/' . $segments[0] : '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - JovemConnect</title>
  <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="auth-page">
  <main class="auth-wrap">
    <div class="auth-card login-card">
      <h2>Bem-vindo de volta</h2>
      <p><?php echo $titulo; ?></p>

      <form method="POST" action="" class="form-stack">
        <div class="input-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="seuemail@email.com" required>
        </div>

        <div class="input-group">
          <label for="senha">Senha</label>
          <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
        </div>

        <button type="submit" class="btn btn-primary btn-full">Entrar</button>
      </form>

      <p class="link-cadastro">Nao tem conta?
        <a href="<?php echo $linkCadastro; ?>">Cadastre-se aqui</a>
      </p>
    </div>
  </main>

  <script src="<?php echo $basePath; ?>/script.js"></script>
</body>
</html>
