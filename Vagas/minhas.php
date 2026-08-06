<?php 
/* 1. CONFIGURAÇÕES E PROTEÇÃO */
include("../includes/config.php");
session_start();

// Proteção: Apenas empresas logadas
if(!isset($_SESSION['empresa_id'])){
    header("Location: ../empresa/login.php");
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

/* 2. CONSULTA DAS VAGAS DA EMPRESA */
$sql = "SELECT * FROM vagas WHERE empresa_id = '$empresa_id' ORDER BY id DESC";
$result = $conn->query($sql);

include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>💼 Minhas Vagas Publicadas</h1>
        <a href="criar.php" class="btn" style="padding: 10px 20px;">+ Criar Nova Vaga</a>
    </div>
    <p>Acompanhe o desempenho das suas publicações e gira os candidatos inscritos.</p>
    <hr>

    <?php if($result->num_rows > 0): ?>
        <div style="display: grid; gap: 20px; margin-top: 20px;">
            
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h3 style="color: var(--principal); margin: 0;"><?php echo $row['titulo']; ?></h3>
                            <p style="font-size: 0.85rem; color: #94a3b8; margin: 5px 0;">
                                📍 <?php echo $row['cidade']; ?> - <?php echo $row['estado']; ?> | 
                                📅 Publicada em: <?php echo date('d/m/Y', strtotime($row['criado_at'] ?? 'now')); ?>
                            </p>
                        </div>
                        <span style="background: #1e293b; padding: 5px 10px; border-radius: 5px; font-size: 0.8rem; border: 1px solid var(--borda);">
                            <?php echo $row['tipo_contrato']; ?>
                        </span>
                    </div>

                    <p style="margin: 15px 0; color: #cbd5e1; font-size: 0.95rem;">
                        <?php echo (strlen($row['descricao']) > 150) ? substr($row['descricao'], 0, 150) . "..." : $row['descricao']; ?>
                    </p>

                    <div style="display: flex; gap: 10px; border-top: 1px solid var(--borda); padding-top: 15px;">
                        <a href="candidatos.php?vaga=<?php echo $row['id']; ?>" class="btn" style="flex: 1; text-align: center; font-size: 0.85rem;">
                            👥 Ver Candidatos
                        </a>
                        
                        <a href="editar.php?id=<?php echo $row['id']; ?>" class="btn" style="background: #334155; font-size: 0.85rem;">
                            ✏️ Editar
                        </a>

                        <a href="excluir.php?id=<?php echo $row['id']; ?>" 
                           onclick="return confirm('Tem certeza que deseja remover esta vaga?')" 
                           style="color: #ff4d4d; text-decoration: none; padding: 10px; font-size: 0.85rem;">
                           Remover
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>

        </div>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 60px;">
            <p style="font-size: 1.2rem; color: #94a3b8;">A sua empresa ainda não tem vagas ativas.</p>
            <a href="criar.php" class="btn" style="display: inline-block; margin-top: 20px;">Publicar Primeira Vaga</a>
        </div>
    <?php endif; ?>

    <br>
    <a href="../dashboard/empresa.php" style="color: #64748b;">← Voltar ao Painel Geral</a>
</div>

<?php include("../includes/footer.php"); ?>