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
  <title>Cadastro de Jovem | JovemConnect</title>
  <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="auth-page">
  <main class="auth-wrap">
    <div class="auth-card cadastro-card">
      <h2>Cadastro de Jovem</h2>
      <p>Crie seu perfil para encontrar oportunidades de aprendizado e crescimento profissional.</p>

      <form class="form-stack">
        <div class="input-group">
          <label for="nome">Nome completo</label>
          <input type="text" id="nome" name="nome" required>
        </div>

        <div class="input-group">
          <label for="cpf">CPF</label>
          <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" maxlength="14" required>
        </div>

        <div class="input-group">
          <label for="nascimento">Data de nascimento</label>
          <input type="date" name="nascimento" id="nascimento" required>
        </div>

        <div class="input-group">
          <label for="idade">Idade</label>
          <input type="number" id="idade" readonly>
        </div>

        <div class="input-group">
          <label for="cep">Regiao (CEP)</label>
          <input type="text" id="cep" name="cep" maxlength="9" placeholder="00000-000" required>
        </div>

        <div class="input-group">
          <label for="escolaridade">Escolaridade</label>
          <input type="text" id="escolaridade" name="escolaridade" placeholder="Ex: 2o ano do ensino medio tecnico" required>
        </div>

        <div class="input-check">
          <input type="checkbox" id="clt" name="clt">
          <label for="clt">Possui registro CLT</label>
        </div>

        <div class="input-group">
          <label for="senha">Senha</label>
          <input type="password" id="senha" name="senha" required>
        </div>

        <div class="input-group">
          <label for="confirmar">Confirmar senha</label>
          <input type="password" id="confirmar" name="confirmar" required>
        </div>

        <button type="submit" class="btn btn-primary btn-full">Cadastrar</button>
      </form>

      <p class="link-cadastro">Ja tem conta? <a href="login.php?tipo=jovem">Entrar</a></p>
    </div>
  </main>

  <script>
    const nasc = document.getElementById('nascimento');
    const idade = document.getElementById('idade');

    nasc.addEventListener('change', () => {
      const hoje = new Date();
      const nascimento = new Date(nasc.value);
      let anos = hoje.getFullYear() - nascimento.getFullYear();
      const mes = hoje.getMonth() - nascimento.getMonth();
      if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
        anos--;
      }
      idade.value = anos;
    });
  </script>
</body>
</html>
