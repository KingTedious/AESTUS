<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $nome = $_POST["nome"];
  $cnpj = $_POST["cnpj"];
  $area = $_POST["area"];
  $cidade = $_POST["cidade"];
  $estado = $_POST["estado"];
  $email = $_POST["email"];
  $senha = $_POST["senha"];

  $_SESSION["empresa"] = [
    "nome" => $nome,
    "cnpj" => $cnpj,
    "area" => $area,
    "cidade" => $cidade,
    "estado" => $estado,
    "email" => $email
  ];

  header("Location: login.php?tipo=empresa");
  exit;
}
?>

<?php include("../includes/header.php"); ?>

<main>
  <h2>Cadastro de Empresa</h2>

  <form method="POST" action="">
    <label>Nome da empresa:</label><br>
    <input type="text" name="nome"><br><br>

    <label>CNPJ:</label><br>
    <input type="text" name="cnpj"><br><br>

    <label>Área de atuação:</label><br>
    <input type="text" name="area"><br><br>

    <label>Cidade:</label><br>
    <input type="text" name="cidade"><br><br>

    <label>Estado:</label><br>
    <input type="text" name="estado"><br><br>

    <label>Email:</label><br>
    <input type="email" name="email"><br><br>

    <label>Senha:</label><br>
    <input type="password" name="senha"><br><br>

    <button type="submit">Cadastrar</button>
  </form>
</main>

<?php include("../includes/footer.php"); ?>
