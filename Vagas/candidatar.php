<?php 
/* 1. CONFIGURAÇÕES E SESSÃO */
include("../includes/config.php"); 
session_start();

// Proteção: Apenas jovens logados podem se candidatar
if(!isset($_SESSION['jovem_id'])){
    include("../includes/header.php");
    echo "<div class='container'><div class='card'><h2>Acesso Negado</h2><p>Você precisa estar logado como jovem para se candidatar.</p><a href='../jovem/login.php' class='btn'>Fazer Login</a></div></div>";
    include("../includes/footer.php");
    exit;
}

$jovem_id = $_SESSION['jovem_id'];

// 2. VERIFICAÇÃO DO ID DA VAGA
if(!isset($_GET['vaga'])){
    header("Location: ../vagas/listar.php");
    exit;
}

$vaga_id = $conn->real_escape_string($_GET['vaga']);
$mensagem = "";
$sucesso = false;

/* 3. VERIFICAR SE JÁ EXISTE CANDIDATURA
   Evita que o jovem clique duas vezes e gere registros duplicados no banco. */
$check = $conn->query("SELECT id FROM candidaturas WHERE vaga_id = '$vaga_id' AND jovem_id = '$jovem_id'");

if($check->num_rows > 0){
    $mensagem = "Você já se candidatou a esta vaga anteriormente.";
} else {
    /* 4. INSERIR CANDIDATURA
       Definimos o status inicial como 'Pendente' (conforme a lógica do projeto). */
    $sql = "INSERT INTO candidaturas (vaga_id, jovem_id, status) VALUES ('$vaga_id', '$jovem_id', 'Pendente')";

    if($conn->query($sql) === TRUE){
        $mensagem = "Candidatura realizada com sucesso! Boa sorte!";
        $sucesso = true;
    } else {
        $mensagem = "Erro técnico ao processar candidatura: " . $conn->error;
    }
}

/* 5. INTERFACE VISUAL */
include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="card" style="text-align: center; padding: 50px;">
        <?php if($sucesso): ?>
            <h2 style="color: var(--principal);">✔️ Sucesso!</h2>
            <p style="font-size: 1.2rem; margin: 20px 0;"><?php echo $mensagem; ?></p>
        <?php else: ?>
            <h2 style="color: #64748b;">⚠️ Atenção</h2>
            <p style="font-size: 1.2rem; margin: 20px 0;"><?php echo $mensagem; ?></p>
        <?php endif; ?>

        <hr>
        <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
            <a href="../vagas/listar.php" class="btn" style="background: #334155;">Ver outras vagas</a>
            <a href="minhas.php" class="btn">Minhas Candidaturas</a>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>