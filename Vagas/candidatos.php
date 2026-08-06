<?php 
/* 1. CONFIGURAÇÕES E SESSÃO */
include("../includes/config.php");
session_start();

// Proteção: Apenas empresas logadas
if(!isset($_SESSION['empresa_id'])){
    echo "Acesso negado. Apenas empresas podem gerir candidatos.";
    exit;
}

$mensagem = "";

/* 2. PROCESSAMENTO DA ATUALIZAÇÃO DE STATUS */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_status'])){
    $candidatura_id = $_POST['candidatura_id'];
    $novo_status = $_POST['status'];

    $sql_update = "UPDATE candidaturas SET status='$novo_status' WHERE id='$candidatura_id'";
    
    if($conn->query($sql_update)){
        $mensagem = "<p style='color: var(--principal);'>Status atualizado com sucesso!</p>";
    }
}

/* 3. BUSCA DE CANDIDATOS PARA A VAGA ESPECÍFICA */
$vaga_id = isset($_GET['vaga']) ? $_GET['vaga'] : 0;

// Selecionamos dados do jovem e o ID da candidatura para o formulário
$sql = "SELECT 
            jovens.nome, 
            jovens.email, 
            jovens.cidade, 
            jovens.estado, 
            jovens.formacao,
            candidaturas.id AS cand_id, 
            candidaturas.status 
        FROM candidaturas
        JOIN jovens ON candidaturas.jovem_id = jovens.id
        WHERE candidaturas.vaga_id = '$vaga_id'";

$result = $conn->query($sql);

include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <h1>Candidatos Inscritos</h1>
    <p>Gerencie o status dos jovens que se candidataram a esta vaga.</p>
    <?php echo $mensagem; ?>
    <hr>

    <?php if($result->num_rows > 0): ?>
        
        <?php while($row = $result->fetch_assoc()): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin:0;"><?php echo $row['nome']; ?></h3>
                        <p>📧 <?php echo $row['email']; ?> | 📍 <?php echo $row['cidade']; ?>-<?php echo $row['estado']; ?></p>
                        <p>🎓 <strong>Formação:</strong> <?php echo $row['formacao']; ?></p>
                    </div>
                    
                    <div style="text-align: right; background: #0F172A; padding: 15px; border-radius: 8px;">
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="candidatura_id" value="<?php echo $row['cand_id']; ?>">
                            <label><small>Alterar Status:</small></label><br>
                            <select name="status" style="margin-bottom: 10px; padding: 5px;">
                                <option value="Recebido" <?php if($row['status'] == 'Recebido') echo 'selected'; ?>>Recebido</option>
                                <option value="Em análise" <?php if($row['status'] == 'Em análise') echo 'selected'; ?>>Em análise</option>
                                <option value="Aprovado" <?php if($row['status'] == 'Aprovado') echo 'selected'; ?>>Aprovado</option>
                                <option value="Rejeitado" <?php if($row['status'] == 'Rejeitado') echo 'selected'; ?>>Rejeitado</option>
                            </select>
                            <button type="submit" name="atualizar_status" class="btn" style="padding: 5px 10px; font-size: 0.8rem;">Atualizar</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>

    <?php else: ?>
        <div class="card">
            <p>Nenhum candidato se inscreveu para esta vaga ainda.</p>
        </div>
    <?php endif; ?>

    <br>
    <a href="minhas.php" style="color: var(--principal);">← Voltar para minhas vagas</a>
</div>

<?php include("../includes/footer.php"); ?>