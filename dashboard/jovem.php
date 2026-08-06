<?php include("../includes/header.php"); 
include("../includes/auth.php");
verificarJovem();?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php

$jovem_id = $_SESSION['jovem_id'];

$sql = "SELECT COUNT(*) as total FROM candidaturas WHERE jovem_id='$jovem_id'";
$res = $conn->query($sql);
$total_candidaturas = $res->fetch_assoc()['total'];

include("../includes/config.php");
session_start();

if(!isset($_SESSION['jovem_id'])){
echo "Acesso negado.";
exit;
}

echo "<h1>Dashboard do Jovem</h1>";

echo "<hr>";

echo "<a href='../jovem/perfil.php'>Meu perfil</a><br><br>";

echo "<a href='../jovem/curriculo_ia.php'>Melhorar meu currículo</a><br><br>";

echo "<a href='../vagas/listar.php'>Ver vagas</a><br><br>";

echo "<a href='../candidaturas/minhas.php'>Minhas candidaturas</a><br><br>";

echo "<a href='../vagas/favoritos.php'>Minhas vagas favoritas</a><br><br>";

echo "<h2>Minhas estatísticas</h2>";
echo "Candidaturas enviadas: ".$total_candidaturas."<br><br>";

echo "<a href='../logout.php'>Sair</a>";
?>

<?php include("../includes/footer.php"); ?>