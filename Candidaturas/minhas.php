<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['jovem_id'])){
    echo "Acesso negado.";
    exit;
}

$jovem_id = $_SESSION['jovem_id'];

$sql = "SELECT vagas.titulo, vagas.descricao, empresas.nome_empresa
        FROM candidaturas
        JOIN vagas ON candidaturas.vaga_id = vagas.id
        JOIN empresas ON vagas.empresa_id = empresas.id
        WHERE candidaturas.jovem_id = '$jovem_id'";

$result = $conn->query($sql);

echo "<h1>Minhas candidaturas</h1>";
echo "<hr>";

if($result->num_rows > 0){

    while($row = $result->fetch_assoc()){

        echo "<b>".$row['titulo']."</b><br>";
        echo "Empresa: ".$row['nome_empresa']."<br>";
        echo $row['descricao']."<br>";
        echo "<b>Status:</b> ".$row['status']."<br><br>";

        echo "<hr>";
    }

}else{
    echo "Você ainda não se candidatou a nenhuma vaga.";
}
?>

<?php include("../includes/footer.php"); ?>