<?php include("../includes/header.php"); 
include("../includes/auth.php");
verificarJovem();?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
$empresa_id = $_SESSION['empresa_id'];

// Total de vagas criadas
$sql_vagas = "SELECT COUNT(*) as total FROM vagas WHERE empresa_id='$empresa_id'";
$res_vagas = $conn->query($sql_vagas);
$total_vagas = $res_vagas->fetch_assoc()['total'];

// Total de candidatos recebidos
$sql_candidatos = "
SELECT COUNT(*) as total 
FROM candidaturas c
JOIN vagas v ON c.vaga_id = v.id
WHERE v.empresa_id='$empresa_id'
";

$res_candidatos = $conn->query($sql_candidatos);
$total_candidatos = $res_candidatos->fetch_assoc()['total'];

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

echo "<h2>Estatísticas</h2>";

echo "Vagas criadas: ".$total_vagas."<br>";
echo "Candidatos recebidos: ".$total_candidatos."<br><br>";

echo "<a href='../logout.php'>Sair</a>";
?>

<?php include("../includes/footer.php"); ?>