<?php
/* 1. CONFIGURAÇÕES E SEGURANÇA */
include("../includes/config.php");
session_start();

// Verifica se o jovem está logado
if(!isset($_SESSION['jovem_id'])){
    header("Location: ../jovem/login.php");
    exit;
}

// Verifica se o ID da vaga foi passado via URL
if(!isset($_GET['vaga_id'])){
    header("Location: listar.php");
    exit;
}

$jovem_id = $_SESSION['jovem_id'];
$vaga_id  = $conn->real_escape_string($_GET['vaga_id']);

/* 2. VERIFICAÇÃO DE EXISTÊNCIA (Lógica Toggle) */
$sql_check = "SELECT id FROM favoritos WHERE jovem_id='$jovem_id' AND vaga_id='$vaga_id'";
$res = $conn->query($sql_check);

if($res->num_rows > 0){
    /* 3. SE JÁ EXISTE: REMOVE DOS FAVORITOS */
    $sql = "DELETE FROM favoritos WHERE jovem_id='$jovem_id' AND vaga_id='$vaga_id'";
} else {
    /* 4. SE NÃO EXISTE: ADICIONA AOS FAVORITOS */
    $sql = "INSERT INTO favoritos (jovem_id, vaga_id) VALUES ('$jovem_id', '$vaga_id')";
}

// Executa a operação final
if($conn->query($sql)){
    // Redireciona de volta para a listagem ou para a página anterior
    header("Location: " . $_SERVER['HTTP_REFERER']); 
} else {
    // Caso ocorra erro no banco, volta para a lista padrão
    header("Location: listar.php?erro=favorito");
}
exit;
?>