<?php 
/* 1. CONFIGURAÇÕES INICIAIS 
   O session_start() e o config devem vir primeiro para evitar erros de conexão. */
include("../includes/config.php"); 
session_start();

/* 2. VERIFICAÇÃO DE LOGIN
   Garante que apenas empresas logadas vejam esta página. */
if(!isset($_SESSION['empresa_id'])){
    echo "Acesso negado. Por favor, faça login como empresa.";
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

/* 3. COLETA DE ESTATÍSTICAS
   Buscamos os números reais do banco de dados 'jovemconnect'[cite: 1, 5]. */

// Total de vagas criadas pela empresa [cite: 18, 19]
$sql_vagas = "SELECT COUNT(*) as total FROM vagas WHERE empresa_id='$empresa_id'";
$res_vagas = $conn->query($sql_vagas);
$total_vagas = $res_vagas->fetch_assoc()['total'];

// Total de candidatos recebidos em todas as vagas desta empresa 
$sql_candidatos = "
    SELECT COUNT(*) as total 
    FROM candidaturas c
    JOIN vagas v ON c.vaga_id = v.id
    WHERE v.empresa_id='$empresa_id'
";
$res_candidatos = $conn->query($sql_candidatos);
$total_candidatos = $res_candidatos->fetch_assoc()['total'];

/* 4. INCLUSÃO DO CABEÇALHO VISUAL */
include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="card">
        <h1>Dashboard da Empresa</h1>
        <p>Bem-vindo ao seu painel de gestão de talentos.</p>
        <hr>

        <div style="margin: 20px 0;">
            <a href="../vagas/criar.php" class="btn">➕ Criar Nova Vaga</a>
            <a href="../vagas/minhas.php" class="btn" style="background: #334155;">📋 Minhas Vagas</a>
        </div>

        <div class="card" style="background: #0F172A; border-color: var(--principal);">
            <h2>📊 Estatísticas</h2>
            <p><strong>Vagas publicadas:</strong> <?php echo $total_vagas; ?></p>
            <p><strong>Total de Candidaturas:</strong> <?php echo $total_candidatos; ?></p>
        </div>

        <br>
        <a href="../logout.php" style="color: #ff4d4d;">🔴 Sair do Sistema</a>
    </div>
</div>

<?php include("../includes/footer.php"); ?>