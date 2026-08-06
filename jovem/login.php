<?php
echo "<div class='auth'>";
include("../includes/config.php");
session_start();

if($_SERVER['REQUEST_METHOD'] == 'POST'){

$email = $_POST['email'];
$senha = $_POST['senha'];

$stmt = $conn->prepare("SELECT * FROM jovens WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows > 0){

$jovem = $result->fetch_assoc();

if(password_verify($senha, $jovem['senha'])){

$_SESSION['jovem_id'] = $jovem['id'];

header("Location: ../dashboard/jovem.php");
exit;

}else{
echo "Senha incorreta";
}

}else{
echo "Email não encontrado";
}

}
?>