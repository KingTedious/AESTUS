<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['empresa_id'])){
echo "Acesso negado.";
exit;
}

echo "<h1>Dashboard da Empresa</h1>";

echo "<hr>";

echo "<a href='../vagas/criar.php'>Criar vaga</a><br><br>";

echo "<a href='../vagas/minhas.php'>Minhas vagas</a><br><br>";

echo "<a href='../logout.php'>Sair</a>";
?>

<?php include("../includes/footer.php"); ?>