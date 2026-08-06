<?php
include("../includes/config.php");
session_start();

if(!isset($_SESSION['jovem_id'])){
    echo "Faça login.";
    exit;
}

$jovem_id = $_SESSION['jovem_id'];
$vaga_id = $_GET['vaga_id'];

// pega jovem
$jovem = $conn->query("SELECT * FROM jovens WHERE id='$jovem_id'")->fetch_assoc();

// pega vaga
$vaga = $conn->query("SELECT * FROM vagas WHERE id='$vaga_id'")->fetch_assoc();

// monta dados
$dados = [
    "formacao" => $jovem['formacao'],
    "cidade_jovem" => $jovem['cidade'],
    "descricao" => $vaga['descricao'],
    "cidade_vaga" => $vaga['cidade']
];

// envia pra IA
$options = [
    "http" => [
        "header" => "Content-Type: application/json",
        "method" => "POST",
        "content" => json_encode($dados)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents("http://localhost:5000/avaliar", false, $context);

$resposta = json_decode($result, true);

// exibir
echo "<h1>Resultado da IA</h1>";
echo "Compatibilidade: ".$resposta['compatibilidade']."%<br>";
echo $resposta['mensagem']."<br><br>";

echo "<b>Motivos:</b><br>";

foreach($resposta['motivos'] as $motivo){
    echo "- ".$motivo."<br>";
}

echo "<br><a href='listar.php'>Voltar</a>";