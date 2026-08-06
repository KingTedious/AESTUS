<?php include("../includes/header.php"); ?>

<link rel="stylesheet" href="../assets/css/style.css">

<?php
include("../includes/config.php");

if($_SERVER["REQUEST_METHOD"] == "POST"){

$nome = $_POST['nome'];
$email = $_POST['email'];
$senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
$cpf = $_POST['cpf'];
$data = $_POST['data_nascimento'];
$cidade = $_POST['cidade'];
$estado = $_POST['estado'];
$formacao = $_POST['formacao'];
$telefone = $_POST['telefone'];

$verifica = "SELECT * FROM jovens WHERE email='$email' OR cpf='$cpf'";
$result = $conn->query($verifica);

if($result->num_rows > 0){
    echo "Email ou CPF já cadastrado.";
    exit;
}

$stmt = $conn->prepare("INSERT INTO jovens (nome, email, senha) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $nome, $email, $senha);
$stmt->execute();

if($conn->query($sql) === TRUE){
echo "Cadastro realizado com sucesso!";
}else{
echo "Erro: ".$conn->error;
}

}
?>

<h2>Cadastro de Jovem</h2>

<form method="POST">

Nome:<br>
<input type="text" name="nome" required>
<br><br>

Email:<br>
<input type="email" name="email" required>
<br><br>

Senha:<br>
<input type="password" name="senha" required>
<br><br>

CPF:<br>
<input type="text" name="cpf" required>
<br><br>

Data de nascimento:<br>
<input type="date" name="data_nascimento" required>
<br><br>

Cidade:<br>
<input type="text" name="cidade" required>
<br><br>

Estado:<br>
<input type="text" name="estado" required>
<br><br>

Formação:<br>
<input type="text" name="formacao" required>
<br><br>

Telefone:<br>
<input type="text" name="telefone">
<br><br>

<button type="submit">Cadastrar</button>

</form>

<?php include("../includes/footer.php"); ?>