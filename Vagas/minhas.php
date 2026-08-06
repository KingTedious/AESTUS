<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['empresa_id'])){
    echo "Acesso negado.";
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

$sql = "SELECT * FROM vagas WHERE empresa_id = '$empresa_id'";
$result = $conn->query($sql);

echo "<h1>Minhas vagas</h1>";
echo "<hr>";

if($result->num_rows > 0){

    while($row = $result->fetch_assoc()){

        echo "<b>".$row['titulo']."</b><br>";
        echo $row['descricao']."<br><br>";

        echo "<a href='candidatos.php?vaga=".$row['id']."'>Ver candidatos</a>";

        echo "<hr>";
    }

}else{
    echo "Você ainda não criou vagas.";
}
?>

<?php include("../includes/footer.php"); ?>