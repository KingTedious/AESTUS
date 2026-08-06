
<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
echo "<div class='auth'>";
include("../includes/config.php");
session_start();

if($_SERVER["REQUEST_METHOD"] == "POST"){

$email = $_POST['email'];
$senha = $_POST['senha'];

$sql = "SELECT * FROM empresas WHERE email='$email'";
$result = $conn->query($sql);

if($result->num_rows > 0){

$empresa = $result->fetch_assoc();

if(password_verify($senha,$empresa['senha'])){

$_SESSION['empresa_id'] = $empresa['id'];
$_SESSION['empresa_nome'] = $empresa['nome_empresa'];

header("Location: ../dashboard/empresa.php");
exit;

}else{
echo "Senha incorreta.";
}

}else{
echo "Empresa não encontrada.";
}

}
?>

<h2>Login Empresa</h2>

<form method="POST">

Email:<br>
<input type="email" name="email" required>
<br><br>

Senha:<br>
<input type="password" name="senha" required>
<br><br>

<button type="submit">Entrar</button>

</form>

<?php include("../includes/footer.php"); ?>