<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['empresa_id'])){
echo "Apenas empresas podem ver candidatos.";
exit;
}

$vaga_id = $_GET['vaga'];

$sql = "SELECT jovens.nome, jovens.email, jovens.cidade, jovens.estado

FROM candidaturas

JOIN jovens ON candidaturas.jovem_id = jovens.id

WHERE candidaturas.vaga_id = '$vaga_id'";

$result = $conn->query($sql);

echo "<h1>Candidatos</h1>";

if($result->num_rows > 0){

while($row = $result->fetch_assoc()){

echo "<hr>";

echo "<b>Nome:</b> ".$row['nome']."<br>";

echo "<b>Email:</b> ".$row['email']."<br>";

echo "<b>Cidade:</b> ".$row['cidade']." - ".$row['estado']."<br>";

}

}else{

echo "Nenhum candidato ainda.";

}
?>

<?php include("../includes/footer.php"); ?>