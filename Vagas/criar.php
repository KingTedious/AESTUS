<?php 
/* 1. CONFIGURAÇÕES E PROTEÇÃO */
include("../includes/config.php");
include("../includes/auth.php"); // Certifica que verificarEmpresa() existe
session_start();

// Garante que apenas empresas autenticadas acedem
if(!isset($_SESSION['empresa_id'])){
    header("Location: ../empresa/login.php");
    exit;
}

$mensagem = "";

/* 2. PROCESSAMENTO DO CADASTRO DA VAGA */
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $empresa_id = $_SESSION['empresa_id'];
    
    // Limpeza de dados para evitar erros de SQL
    $titulo    = $conn->real_escape_string($_POST['titulo']);
    $descricao = $conn->real_escape_string($_POST['descricao']);
    $cidade    = $conn->real_escape_string($_POST['cidade']);
    $estado    = $conn->real_escape_string($_POST['estado']);
    $salario   = $conn->real_escape_string($_POST['salario']);
    $tipo      = $conn->real_escape_string($_POST['tipo_contrato']);

    $sql = "INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, salario, tipo_contrato) 
            VALUES ('$empresa_id', '$titulo', '$descricao', '$cidade', '$estado', '$salario', '$tipo')";

    if($conn->query($sql) === TRUE){
        $mensagem = "<p style='color: var(--principal);'>✔️ Vaga publicada com sucesso!</p>";
    } else {
        $mensagem = "<p style='color: #ff4d4d;'>❌ Erro ao publicar: " . $conn->error . "</p>";
    }
}

include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="auth" style="max-width: 700px;"> <h2>Criar Nova Vaga</h2>
        <p>Preencha os detalhes da oportunidade para os jovens talentos.</p>
        <hr>

        <?php echo $mensagem; ?>

        <form method="POST">
            <label>Título da Vaga:</label>
            <input type="text" name="titulo" placeholder="Ex: Desenvolvedor Júnior, Estagiário de RH..." required>

            <label>Descrição Detalhada (A IA usará isto para o Match):</label>
            <textarea name="descricao" rows="5" placeholder="Descreva as responsabilidades e requisitos..." required></textarea>

            <div style="display: flex; gap: 10px;">
                <div style="flex: 2;">
                    <label>Cidade:</label>
                    <input type="text" name="cidade" placeholder="Ex: São Paulo" required>
                </div>
                <div style="flex: 1;">
                    <label>Estado (UF):</label>
                    <input type="text" name="estado" maxlength="2" placeholder="SP" required>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <div style="flex: 1;">
                    <label>Salário (Opcional):</label>
                    <input type="text" name="salario" placeholder="Ex: 1500,00">
                </div>
                <div style="flex: 1;">
                    <label>Tipo de Contrato:</label>
                    <select name="tipo_contrato" required style="width: 100%; padding: 10px; border-radius: 5px; background: #1e293b; color: white; border: 1px solid var(--borda);">
                        <option value="Estágio">Estágio</option>
                        <option value="Jovem Aprendiz">Jovem Aprendiz</option>
                        <option value="CLT">CLT (Efetivo)</option>
                        <option value="PJ">PJ</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn" style="margin-top: 20px;">Publicar Vaga Agora</button>
        </form>

        <p style="margin-top: 15px; text-align: center;">
            <a href="../dashboard/empresa.php" style="color: #64748b;">← Voltar ao Painel</a>
        </p>
    </div>
</div>

<?php include("../includes/footer.php"); ?>