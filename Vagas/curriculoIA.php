<?php
include("../includes/config.php");
session_start();
?>

<h1>Melhorar meu currículo (IA)</h1>

<form method="POST">

<textarea name="texto" rows="6" cols="50" placeholder="Escreva seu currículo aqui..."></textarea><br><br>

<button type="submit">Analisar</button>

</form>

<?php

if($_SERVER['REQUEST_METHOD'] == 'POST'){

$texto = $_POST['texto'];

$dados = ["texto" => $texto];

$options = [
    "http" => [
        "header" => "Content-Type: application/json",
        "method" => "POST",
        "content" => json_encode($dados)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents("http://localhost:5000/curriculo", false, $context);

$resposta = json_decode($result, true);

echo "<h3>".$resposta['mensagem']."</h3>";

echo "<b>Sugestões:</b><br>";

foreach($resposta['sugestoes'] as $s){
    echo "- ".$s."<br>";
}

}
?>