<?php 
/* 1. CONFIGURAÇÕES E SESSÃO 
   Sempre no topo para garantir que o banco e a sessão funcionam. */
include("../includes/config.php"); 
session_start();

/* 2. VERIFICAÇÃO DE SEGURANÇA
   Confirmamos se o jovem_id existe na sessão (conforme o PDF). */
if(!isset($_SESSION['jovem_id'])){
    echo "Acesso negado. Por favor, faça login.";
    exit;
}

$jovem_id = $_SESSION['jovem_id'];
$jovem_nome = $_SESSION['jovem_nome'] ?? "Jovem";

/* 3. ESTATÍSTICAS
   Contamos quantas vezes o ID deste jovem aparece na tabela 'candidaturas'. */
$sql = "SELECT COUNT(*) as total FROM candidaturas WHERE jovem_id='$jovem_id'";
$res = $conn->query($sql);
$total_candidaturas = $res->fetch_assoc()['total'];

/* 4. CABEÇALHO VISUAL */
include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="card">
        <h1>Dashboard</h1>
        <p>
            <?php 
                if (isset($_SESSION['primeiro_acesso']) && $_SESSION['primeiro_acesso'] == true) {
                    echo "Bem-vindo(a), ";
                } else {
                    echo "Bem-vindo(a) de volta, ";
                }
            ?><strong><?php echo $_SESSION['jovem_nome']; ?></strong>!
        </p>
    </div>    

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 20px 0;">
            <a href="../jovem/perfil.php" class="btn" style="background: #334155;">Meu Perfil</a> /* Colocar um icone de usuario */
            <a href="../jovem/curriculo_ia.php" class="btn" style="background: #1DB954;">IA: Melhorar Currículo</a>
            <a href="../vagas/listar.php" class="btn">Ver Vagas</a> /* Colocar um icone de lupa */
            <a href="../candidaturas/minhas.php" class="btn" style="background: #334155;">Minhas Candidaturas</a>
        </div>

        <div class="card" style="background: #0F172A; border-left: 5px solid var(--principal);">
            <h3>Minhas Estatísticas</h3>
            <p><strong>Candidaturas enviadas:</strong> <?php echo $total_candidaturas; ?></p>
            <p><small>Continue a candidatar-se para aumentar as suas chances!</small></p>
        </div>

        <div style="margin-top: 20px;">
            <a href="../vagas/favoritos.php" style="color: var(--principal);">Ver minhas vagas favoritas</a> /* Colocar um icone de estrela */
            <br><br>
            <a href="../logout.php" style="color: #ff4d4d;">Sair</a>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>