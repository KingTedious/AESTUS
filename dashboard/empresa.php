<?php
    session_start();
    include("../includes/config.php");

    // Proteção
    if(!isset($_SESSION['empresa_id'])){
        header("Location: ../index.php");
        exit;
    }

    include("../includes/header.php");

    // Consulta os jovens cadastrados
    $sql = "SELECT * FROM jovens ORDER BY id DESC";
    $result = $conn->query($sql);
?>

<div class="container">
    <header style="margin-bottom: 40px;">
        <h1>Candidatos Compatíveis</h1>
        <p style="color: var(--text-secondary);">Jovens talentos prontos para o mercado.</p>
    </header>

    <div class="grid-vagas"> <?php if($result->num_rows > 0): ?>
            <?php while($jovem = $result->fetch_assoc()): ?>
                <div class="card" style="display: flex; flex-direction: column; align-items: center; text-align: center;">
                    <div style="width: 60px; height: 60px; background: var(--border); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 15px;">
                        <?php echo strtoupper(substr($jovem['nome'], 0, 1)); ?>
                    </div>
                    
                    <h3 style="margin: 0;"><?php echo $jovem['nome']; ?></h3>
                    <p style="color: var(--text-secondary); font-size: 0.85rem; margin: 5px 0;">
                        <?php echo $jovem['cidade']; ?> • <?php echo $jovem['idade'] ?? '18'; ?> anos
                    </p>
                    
                    <span class="badge" style="margin-top: 10px;">Candidato Ativo</span>

                    <div style="width: 100%; border-top: 1px solid var(--border); margin-top: 20px; padding-top: 15px;">
                        <a href="../empresa/ver_perfil_jovem.php?id=<?php echo $jovem['id']; ?>" class="btn" style="width: 100%; background: transparent; border: 1px solid var(--border);">
                            🔍 Avaliar Match com IA
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Ainda não há jovens cadastrados no sistema.</p>
        <?php endif; ?>
    </div>
</div>

<?php include("../includes/footer.php"); ?>