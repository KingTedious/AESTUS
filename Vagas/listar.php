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
$dados = [
"formacao" => $jovem['formacao'],
"cidade_jovem" => $jovem['cidade'],
"descricao" => $row['descricao'],
"cidade_vaga" => $row['cidade']
];

$resposta = avaliarIA($dados);

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

echo "<div class='card'>";

echo "<h3>".$row['titulo']."</h3>";
echo "<p>".$row['descricao']."</p>";

echo "<p><b>Cidade:</b> ".$row['cidade']."</p>";

echo "<a class='btn' href='candidatar.php?vaga_id=".$row['id']."'>Candidatar</a><br><br>";

echo "</div>";

if($ja_favoritou){
echo "<a href='favoritar.php?vaga_id=".$row['id']."'>💔 Remover dos favoritos</a><br>";
}else{
echo "<a href='favoritar.php?vaga_id=".$row['id']."'>❤️ Favoritar</a><br>";
}

function avaliarIA($dados){


$url = "http://localhost:5000/avaliar";

$options = [
    "http" => [
        "header" => "Content-Type: application/json",
        "method" => "POST",
        "content" => json_encode($dados)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

return json_decode($result, true);

echo "Compatibilidade: ".$resposta['compatibilidade']."%<br>";
echo $resposta['mensagem']."<br><br>";

}

echo "<a href='avaliar.php?vaga_id=".$row['id']."'>Avaliar para mim</a><br><br>";
}

}
else{

echo "Nenhuma vaga disponível.";

}

?>

<?php include("../includes/footer.php"); ?>