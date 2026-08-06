<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['jovem_id'])){
    echo "Acesso negado.";
    exit;
}

$jovem_id = $_SESSION['jovem_id'];

if($_SERVER['REQUEST_METHOD'] == 'POST'){

$nome = $_POST['nome'];
$email = $_POST['email'];
$escolaridade = $_POST['escolaridade'];
$cidade = $_POST['cidade'];
$estado = $_POST['estado'];

$sql = "UPDATE jovens SET 
nome='$nome',
email='$email',
escolaridade='$escolaridade',
cidade='$cidade',
estado='$estado'
WHERE id='$jovem_id'";

$conn->query($sql);

echo "Perfil atualizado!";
}

$sql = "SELECT * FROM jovens WHERE id = '$jovem_id'";
$result = $conn->query($sql);
$jovem = $result->fetch_assoc();
?>

<?php include("../includes/header.php"); ?>

<h1>Meu Perfil</h1>

<form method="POST">

<label>Nome</label>
<input type="text" name="nome" value="<?php echo $jovem['nome']; ?>">

<label>Email</label>
<input type="email" name="email" value="<?php echo $jovem['email']; ?>">

<label>Escolaridade</label>
<input type="text" name="escolaridade" value="<?php echo $jovem['escolaridade']; ?>">

<label>Cidade</label>
<input type="text" name="cidade" value="<?php echo $jovem['cidade']; ?>">

<label>Estado</label>
<input type="text" name="estado" value="<?php echo $jovem['estado']; ?>">

<button type="submit">Salvar</button>

</form>

<?php include("../includes/footer.php"); ?>