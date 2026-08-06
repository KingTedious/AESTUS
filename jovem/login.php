<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
include("../includes/config.php");
session_start();

if($_SERVER["REQUEST_METHOD"] == "POST"){

$email = $_POST['email'];
$senha = $_POST['senha'];

$sql = "SELECT * FROM jovens WHERE email='$email'";
$result = $conn->query($sql);

if($result->num_rows > 0){

$user = $result->fetch_assoc();

if(password_verify($senha,$user['senha'])){

$_SESSION['jovem_id'] = $user['id'];
$_SESSION['jovem_nome'] = $user['nome'];

header("Location: ../dashboard/jovem.php");
exit;

}else{
echo "Senha incorreta.";
}

}else{
echo "Email não encontrado.";
}

}
?>

<h2>Login Jovem</h2>

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