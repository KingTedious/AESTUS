<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<form method="GET">

<input type="text" name="busca" placeholder="Buscar vagas...">

<button type="submit">Buscar</button>

</form><br>

<?php
include("../includes/config.php");

$busca = "";

if(isset($_GET['busca'])){
    $busca = $_GET['busca'];
}

if($busca != ""){

$sql = "SELECT * FROM vagas 
WHERE titulo LIKE '%$busca%' 
OR descricao LIKE '%$busca%'";

}else{

$sql = "SELECT * FROM vagas";

}
$busca = $_GET['busca'] ?? "";
$cidade = $_GET['cidade'] ?? "";
$estado = $_GET['estado'] ?? "";

$sql = "SELECT * FROM vagas WHERE 1=1";

if($busca != ""){
    $sql .= " AND (titulo LIKE '%$busca%' OR descricao LIKE '%$busca%')";
}

if($cidade != ""){
    $sql .= " AND cidade LIKE '%$cidade%'";
}

if($estado != ""){
    $sql .= " AND estado LIKE '%$estado%'";
}

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

<form method="GET">

<input type="text" name="busca" placeholder="Buscar vaga...">

<input type="text" name="cidade" placeholder="Cidade">

<input type="text" name="estado" placeholder="Estado (SP)">

<button type="submit">Filtrar</button>

</form><br>
<?php

if($busca != ""){
echo "<h3>Resultados para: ".$busca."</h3>";
}

if($result->num_rows > 0){


while($vaga = $result->fetch_assoc()){

$jovem_id = $_SESSION['jovem_id'];

$sql_fav = "SELECT * FROM favoritos 
WHERE jovem_id='$jovem_id' AND vaga_id='".$row['id']."'";

$res_fav = $conn->query($sql_fav);

$ja_favoritou = $res_fav->num_rows > 0;

echo "<hr>";

echo "<h3>Filtros aplicados:</h3>";

if($busca) echo "Busca: $busca <br>";
if($cidade) echo "Cidade: $cidade <br>";
if($estado) echo "Estado: $estado <br>";

echo "<hr>";

echo "<h2>".$vaga['titulo']."</h2>";

echo "<b>Empresa:</b> ".$vaga['nome_empresa']."<br>";

echo "<b>Local:</b> ".$vaga['cidade']." - ".$vaga['estado']."<br>";

echo "<b>Salário:</b> ".$vaga['salario']."<br>";

echo "<b>Tipo:</b> ".$vaga['tipo_contrato']."<br><br>";

echo "<b>Descrição:</b><br>".$vaga['descricao']."<br>";

echo "<br>";
echo "<a href='candidatar.php?vaga=".$vaga['id']."'>Candidatar-se</a>";

if($ja_favoritou){
echo "<a href='favoritar.php?vaga_id=".$row['id']."'>💔 Remover dos favoritos</a><br>";
}else{
echo "<a href='favoritar.php?vaga_id=".$row['id']."'>❤️ Favoritar</a><br>";
}

}

}
else{

echo "Nenhuma vaga disponível.";

}

?>

<?php include("../includes/footer.php"); ?>