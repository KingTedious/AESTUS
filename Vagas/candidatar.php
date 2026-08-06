<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['jovem_id'])){
echo "Você precisa estar logado como jovem.";
exit;
}

$jovem_id = $_SESSION['jovem_id'];
$vaga_id = $_GET['vaga'];

$sql = "INSERT INTO candidaturas (vaga_id, jovem_id)
VALUES ('$vaga_id','$jovem_id')";

if($conn->query($sql) === TRUE){
echo "Candidatura realizada com sucesso!";
}else{
echo "Você já se candidatou a essa vaga.";
}
?>

<?php include("../includes/footer.php"); ?>