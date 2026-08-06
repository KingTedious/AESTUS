<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
include("../includes/config.php");

$sql = "SELECT vagas.*, empresas.nome_empresa 
        FROM vagas
        JOIN empresas ON vagas.empresa_id = empresas.id
        ORDER BY vagas.criado_em DESC";

$result = $conn->query($sql);
?>

<h1>Vagas disponíveis</h1>

<form method="GET">

<input type="text" name="busca" placeholder="Buscar vaga...">

<button type="submit">Buscar</button>

</form>

<hr>

<?php

if($result->num_rows > 0){

while($vaga = $result->fetch_assoc()){

echo "<hr>";

echo "<h2>".$vaga['titulo']."</h2>";

echo "<b>Empresa:</b> ".$vaga['nome_empresa']."<br>";

echo "<b>Local:</b> ".$vaga['cidade']." - ".$vaga['estado']."<br>";

echo "<b>Salário:</b> ".$vaga['salario']."<br>";

echo "<b>Tipo:</b> ".$vaga['tipo_contrato']."<br><br>";

echo "<b>Descrição:</b><br>".$vaga['descricao']."<br>";

echo "<br>";
echo "<a href='candidatar.php?vaga=".$vaga['id']."'>Candidatar-se</a>";

}

}
else{

echo "Nenhuma vaga disponível.";

}

?>

<?php include("../includes/footer.php"); ?>