<?php
session_start();

if(!isset($_SESSION['jovem_id'])){
header("Location: ../index.php");
exit;
}

echo "Bem-vindo, " . $_SESSION['jovem_nome'];
?>

<br><br>

<a href="../logout.php">Sair</a>