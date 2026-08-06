<?php 
/* 1. CONFIGURAÇÕES E PROTEÇÃO */
include("../includes/config.php");
session_start();

if(!isset($_SESSION['jovem_id'])){
    header("Location: ../jovem/login.php");
    exit;
}

$jovem_id = $_SESSION['jovem_id'];

/* 2. CONSULTA COM JOIN 
   Buscamos os dados da vaga 'v' onde o ID da vaga está na tabela de favoritos 'f' */
$sql = "
    SELECT v.*, f.id AS fav_id 
    FROM favoritos f
    JOIN vagas v ON f.vaga_id = v.id
    WHERE f.jovem_id = '$jovem_id'
";

$result = $conn->query($sql);

include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>⭐ Minhas Vagas Favoritas</h1>
        <a href="listar.php" style="color: var(--principal);">Ver todas as vagas</a>
    </div>
    <p>Aqui estão as oportunidades que você salvou para revisar mais tarde.</p>
    <hr>

    <?php if($result->num_rows > 0): ?>
        
        <div style="display: grid; gap: 20px; margin-top: 20px;">
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h3 style="color: var(--principal); margin-bottom: 5px;">
                                <?php echo $row['titulo']; ?>
                            </h3>
                            <p style="font-size: 0.9rem; color: #94a3b8; margin-bottom: 10px;">
                                📍 <?php echo $row['cidade']; ?> - <?php echo $row['estado']; ?> | 
                                💰 <?php echo $row['salario']; ?>
                            </p>
                            <p><?php echo substr($row['descricao'], 0, 150) . "..."; // Mostra apenas o início ?></p>
                        </div>
                        
                        <div style="text-align: right;">
                            <a href="favoritar.php?vaga_id=<?php echo $row['id']; ?>" title="Remover dos favoritos" style="text-decoration: none; font-size: 1.5rem;">⭐</a>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <a href="avaliar_ia.php?vaga_id=<?php echo $row['id']; ?>" class="btn" style="background: #334155; font-size: 0.8rem;">🤖 Avaliar com IA</a>
                        <a href="../candidaturas/candidatar.php?vaga=<?php echo $row['id']; ?>" class="btn" style="font-size: 0.8rem;">📩 Candidatar-me</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

    <?php else: ?>
        <div class="card" style="text-align: center; padding: 40px;">
            <p>Você ainda não favoritou nenhuma vaga.</p>
            <a href="listar.php" class="btn" style="display: inline-block; margin-top: 15px;">Explorar Oportunidades</a>
        </div>
    <?php endif; ?>

    <br>
    <a href="../dashboard/jovem.php" style="color: #64748b;">← Voltar ao Dashboard</a>
</div>

<?php include("../includes/footer.php"); ?>