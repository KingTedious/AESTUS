<?php
session_start();

// Proteção simples: se não houver sessão, volta para o login
if(!isset($_SESSION['jovem_id'])){
    header("Location: ../jovem/login.php");
    exit;
}

// Usando o caminho absoluto seguro para o config.php
require_once dirname(__DIR__) . "/includes/config.php";

// 1. Incluímos o cabeçalho (que já tem a logo e o botão de perfil)
include("../includes/header.php");

// 2. Lógica para buscar as vagas no banco de dados (Sua query está excelente!)
$sql = "SELECT v.*, e.nome_empresa FROM vagas v 
        JOIN empresas e ON v.empresa_id = e.id 
        ORDER BY v.id DESC";
$result = $conn->query($sql);
?>

<link rel="stylesheet" href="../assets/css/style.css">

<main class="container">
    <header class="dashboard-welcome">
        <h1>Vagas Disponíveis</h1>
        <p class="subtitulo">Encontre a oportunidade ideal para o seu perfil.</p>
    </header>

    <div class="grid-vagas">
        <?php if($result && $result->num_rows > 0): ?>
            <?php while($vaga = $result->fetch_assoc()): ?>
                
                <div class="card-vaga">
                    <span class="badge-empresa"><?php echo htmlspecialchars($vaga['nome_empresa']); ?></span>
                    
                    <h3><?php echo htmlspecialchars($vaga['titulo']); ?></h3>
                    
                    <p class="localizacao">
                        📍 <?php echo htmlspecialchars($vaga['cidade']) . " - " . htmlspecialchars($vaga['estado']); ?>
                    </p>
                    
                    <?php if(!empty($vaga['salario'])): ?>
                        <p class="salario">
                            💵 R$ <?php echo htmlspecialchars($vaga['salario']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <p class="descricao-breve">
                        <?php 
                            // Corta a descrição em 120 caracteres para não quebrar o layout do card
                            echo substr(htmlspecialchars($vaga['descricao']), 0, 120) . '...'; 
                        ?>
                    </p>
                    
                    <br>
                    <a href="../Vagas/listar.php?id=<?php echo $vaga['id']; ?>" class="btn btn-vaga">
                        Ver Detalhes da Vaga
                    </a>
                </div>

            <?php endwhile; ?>
        <?php else: ?>
            <p class="aviso-vazio">Nenhuma vaga encontrada no momento.</p>
        <?php endif; ?>
    </div>
</main>

<?php include("../includes/footer.php"); ?>