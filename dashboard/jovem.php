<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['jovem_id'])){
echo "Acesso negado.";
exit;
}

echo "<h1>Dashboard do Jovem</h1>";

echo "<hr>";

echo "<a href='../jovem/perfil.php'>Meu perfil</a><br><br>";

echo "<a href='../vagas/listar.php'>Ver vagas</a><br><br>";

echo "<a href='../candidaturas/minhas.php'>Minhas candidaturas</a><br><br>";

echo "<a href='../logout.php'>Sair</a>";
?>

<?php include("../includes/footer.php"); ?>