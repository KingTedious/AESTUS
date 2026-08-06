<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $nome = $_POST["nome"];
  $cpf = $_POST["cpf"];
  $data = $_POST["data"];
  $cep = $_POST["cep"];
  $escolaridade = $_POST["escolaridade"];
  $tecnico = $_POST["tecnico"];
  $clt = isset($_POST["clt"]) ? "Sim" : "Não";
  $senha = $_POST["senha"];
  $confirmar = $_POST["confirmar"];

  if ($senha !== $confirmar) {
    $erro = "As senhas não coincidem!";
  } else {
    $_SESSION["usuario"] = [
      "nome" => $nome,
      "cpf" => $cpf,
      "nascimento" => $data,
      "cep" => $cep,
      "escolaridade" => $escolaridade,
      "tecnico" => $tecnico,
      "clt" => $clt
    ];
    header("Location: login.php?tipo=jovem");
    exit;
  }
}
?>

<?php include("../includes/header.php"); ?>

<main>
  <h2>Cadastro de Jovem</h2>

  <?php if (isset($erro)) echo "<p style='color:red;'>$erro</p>"; ?>

  <form method="POST" action="">
    <label>Nome completo:</label><br>
    <input type="text" name="nome"><br><br>

    <label>CPF:</label><br>
    <input type="text" name="cpf"><br><br>

    <label>Data de nascimento:</label><br>
    <input type="date" name="data"><br><br>

    <label>CEP:</label><br>
    <input type="text" name="cep"><br><br>

    <label>Escolaridade:</label><br>
    <input type="text" name="escolaridade" placeholder="Ex: 2º ano do ensino médio"><br><br>

    <label>Curso Técnico (se aplicável):</label><br>
    <input type="text" name="tecnico" placeholder="Ex: Informática"><br><br>

    <label><input type="checkbox" name="clt"> Possui registro CLT</label><br><br>

    <label>Senha:</label><br>
    <input type="password" name="senha"><br><br>

    <label>Confirmar Senha:</label><br>
    <input type="password" name="confirmar"><br><br>

    <button type="submit">Cadastrar</button>
  </form>
</main>

<?php include("../includes/footer.php"); ?>