<?php
session_start();

if(!isset($_SESSION['jovem_id']) && !isset($_SESSION['empresa_id'])){
header("Location: ../index.php");
exit;
}

if(isset($_SESSION['jovem_nome'])){
echo "Bem-vindo, Jovem: " . $_SESSION['jovem_nome'];
}

if(isset($_SESSION['empresa_nome'])){
echo "Bem-vindo, Empresa: " . $_SESSION['empresa_nome'];
}
?>

<br><br>

<a href="../logout.php">Sair</a>