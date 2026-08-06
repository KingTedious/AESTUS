<?php
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$segments = explode('/', trim($scriptPath, '/'));
$basePath = isset($segments[0]) && $segments[0] !== '' ? '/' . $segments[0] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro de Empresa | JovemConnect</title>
  <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="auth-page">
  <main class="auth-wrap">
    <div class="auth-card cadastro-card">
      <h2>Cadastro de Empresa</h2>
      <p>Publique vagas e encontre jovens talentos com o perfil ideal para sua empresa.</p>

      <form class="form-stack">
        <div class="input-group">
          <label for="empresa">Nome da empresa</label>
          <input type="text" id="empresa" name="empresa" required>
        </div>

        <div class="input-group">
          <label for="cnpj">CNPJ</label>
          <input type="text" id="cnpj" name="cnpj" maxlength="18" placeholder="00.000.000/0000-00" required>
        </div>

        <div class="input-group">
          <label for="area">Area de atuacao</label>
          <input type="text" id="area" name="area" placeholder="Ex: Tecnologia, Varejo..." required>
        </div>

        <div class="input-group">
          <label for="localizacao">Cidade / Estado</label>
          <input type="text" id="localizacao" name="localizacao" placeholder="Ex: Sao Paulo - SP" required>
        </div>

        <div class="input-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>
        </div>

        <div class="input-group">
          <label for="senha">Senha</label>
          <input type="password" id="senha" name="senha" required>
        </div>

        <button type="submit" class="btn btn-primary btn-full">Cadastrar</button>
      </form>

      <p class="link-cadastro">Ja tem conta? <a href="login.php?tipo=empresa">Entrar</a></p>
    </div>
  </main>

  <script src="<?php echo $basePath; ?>/script.js"></script>
</body>
</html>
