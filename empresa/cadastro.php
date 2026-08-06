<?php
include("../includes/config.php");

if($_SERVER["REQUEST_METHOD"] == "POST"){

$nome = $_POST['nome_empresa'];
$email = $_POST['email'];
$senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
$cnpj = $_POST['cnpj'];
$cidade = $_POST['cidade'];
$estado = $_POST['estado'];
$telefone = $_POST['telefone'];

$verifica = "SELECT * FROM empresas WHERE email='$email' OR cnpj='$cnpj'";
$result = $conn->query($verifica);

if($result->num_rows > 0){
echo "Email ou CNPJ já cadastrado.";
exit;
}

$sql = "INSERT INTO empresas
(nome_empresa,email,senha,cnpj,cidade,estado,telefone)

VALUES
('$nome','$email','$senha','$cnpj','$cidade','$estado','$telefone')";

if($conn->query($sql) === TRUE){
echo "Empresa cadastrada com sucesso!";
}else{
echo "Erro: ".$conn->error;
}

}
?>

<h2>Cadastro de Empresa</h2>

<form method="POST">

Nome da empresa:<br>
<input type="text" name="nome_empresa" required>
<br><br>

Email:<br>
<input type="email" name="email" required>
<br><br>

Senha:<br>
<input type="password" name="senha" required>
<br><br>

CNPJ:<br>
<input type="text" name="cnpj" required>
<br><br>

Cidade:<br>
<input type="text" name="cidade" required>
<br><br>

Estado:<br>
<input type="text" name="estado" required>
<br><br>

Telefone:<br>
<input type="text" name="telefone">
<br><br>

<button type="submit">Cadastrar Empresa</button>

</form>