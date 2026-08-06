<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['jovem_id'])){
    echo "Faça login como jovem.";
    exit;
}

$jovem_id = $_SESSION['jovem_id'];
$vaga_id = $_GET['vaga_id'];

// evita duplicar favorito
$sql_check = "SELECT * FROM favoritos WHERE jovem_id='$jovem_id' AND vaga_id='$vaga_id'";
$res = $conn->query($sql_check);

if($res->num_rows == 0){

$sql = "INSERT INTO favoritos (jovem_id, vaga_id)
VALUES ('$jovem_id', '$vaga_id')";

$conn->query($sql);

}

header("Location: listar.php");