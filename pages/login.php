<?php
session_start();

$tipo = $_GET['tipo'] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = trim($_POST["email"]);
  $senha = trim($_POST["senha"]);

  if (empty($email) || empty($senha)) {
    $erro = "Preencha todos os campos!";
  } else {
    // simulação temporária (sem banco)
    if ($email === "teste@jovem.com" && $senha === "123") {
      $_SESSION["logado"] = true;
      $_SESSION["tipo"] = "jovem";
      header("Location: ../index.php");
      exit;
    } elseif ($email === "teste@empresa.com" && $senha === "123") {
      $_SESSION["logado"] = true;
      $_SESSION["tipo"] = "empresa";
      header("Location: ../index.php");
      exit;
    } else {
      $erro = "Email ou senha incorretos!";
    }
  }
}
?>

<?php include("../includes/header.php"); ?>

<main class="login">
  <h2>Login</h2>

  <?php if (!$tipo): ?>
    <p>Você é...</p>
    <div class="buttons">
      <a href="?tipo=jovem" class="btn green">Sou Jovem</a>
      <a href="?tipo=empresa" class="btn gray">Sou Empresa</a>
    </div>
  <?php else: ?>
    <form method="POST" action="">
      <p>Entrando como: <strong><?= ucfirst($tipo) ?></strong></p>
      <?php if (isset($erro)) echo "<p style='color:red;'>$erro</p>"; ?>

      <label>Email:</label><br>
      <input type="email" name="email"><br><br>

      <label>Senha:</label><br>
      <input type="password" name="senha"><br><br>

      <button type="submit">Entrar</button>
    </form>

    <p>Não tem conta? 
      <?php if ($tipo === "jovem"): ?>
        <a href="cadastro_jovem.php">Cadastre-se aqui</a>
      <?php else: ?>
        <a href="cadastro_empresa.php">Cadastre-se aqui</a>
      <?php endif; ?>
    </p>
  <?php endif; ?>
</main>

<?php include("../includes/footer.php"); ?>