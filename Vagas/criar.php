<?php include("../includes/header.php"); 
verificarEmpresa();?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['empresa_id'])){
echo "Apenas empresas podem criar vagas.";
exit;
}

if($_SERVER["REQUEST_METHOD"] == "POST"){

$empresa_id = $_SESSION['empresa_id'];

$titulo = $_POST['titulo'];
$descricao = $_POST['descricao'];
$cidade = $_POST['cidade'];
$estado = $_POST['estado'];
$salario = $_POST['salario'];
$tipo = $_POST['tipo_contrato'];

$sql = "INSERT INTO vagas
(empresa_id,titulo,descricao,cidade,estado,salario,tipo_contrato)

VALUES
('$empresa_id','$titulo','$descricao','$cidade','$estado','$salario','$tipo')";

if($conn->query($sql) === TRUE){
echo "Vaga criada com sucesso!";
}else{
echo "Erro: ".$conn->error;
}

}
?>

<h2>Criar vaga</h2>

<form method="POST">

Título da vaga:<br>
<input type="text" name="titulo" required>
<br><br>

Descrição:<br>
<textarea name="descricao"></textarea>
<br><br>

Cidade:<br>
<input type="text" name="cidade">
<br><br>

Estado:<br>
<input type="text" name="estado">
<br><br>

Salário:<br>
<input type="text" name="salario">
<br><br>

Tipo de contrato:<br>
<input type="text" name="tipo_contrato">
<br><br>

<button type="submit">Publicar vaga</button>

</form>

<?php include("../includes/footer.php"); ?>