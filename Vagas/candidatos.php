
<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php

if($_SERVER['REQUEST_METHOD'] == 'POST'){

$candidatura_id = $_POST['candidatura_id'];
$status = $_POST['status'];

$sql = "UPDATE candidaturas 
SET status='$status' 
WHERE id='$candidatura_id'";

$conn->query($sql);

echo "Status atualizado!";
}

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

<form method="POST">

<input type="hidden" name="candidatura_id" value="<?php echo $row['candidatura_id']; ?>">

<select name="status">

<option value="Recebido">Recebido</option>
<option value="Em análise">Em análise</option>
<option value="Aprovado">Aprovado</option>
<option value="Rejeitado">Rejeitado</option>

</select>

<button type="submit">Atualizar</button>

</form>

<?php include("../includes/footer.php"); ?>