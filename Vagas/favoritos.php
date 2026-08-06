<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['jovem_id'])){
    echo "Acesso negado.";
    exit;
}

$jovem_id = $_SESSION['jovem_id'];

$sql = "
SELECT v.* 
FROM favoritos f
JOIN vagas v ON f.vaga_id = v.id
WHERE f.jovem_id='$jovem_id'
";

$result = $conn->query($sql);

echo "<h1>Minhas vagas favoritas</h1>";

while($row = $result->fetch_assoc()){

echo "<b>".$row['titulo']."</b><br>";
echo $row['descricao']."<br><br>";

}