<?php
session_start();
include("../includes/config.php");

// Proteção simples: se não houver sessão, volta para o login
if(!isset($_SESSION['jovem_id'])){
    header("Location: ../jovem/login.php");
    exit;
}

// 1. Incluímos o cabeçalho (que já tem a logo e o botão de perfil)
include("../includes/header.php");

// 2. Lógica para buscar as vagas no banco de dados
$sql = "SELECT v.*, e.nome_empresa FROM vagas v 
        JOIN empresas e ON v.empresa_id = e.id 
        ORDER BY v.id DESC";
$result = $conn->query($sql);
?>

<!-- 3. Início do Conteúdo Principal -->
<main class="container" style="margin-top: 30px;">
    <header style="margin-bottom: 40px;">
        <h1>Vagas Disponíveis</h1>
        <p style="color: var(--text-secondary);">Encontre a oportunidade ideal para o seu perfil.</p>
    </header>

    <div class="grid-vagas">
        <?php if($result->num_rows > 0): ?>
            <?php while($vaga = $result->fetch_assoc()): ?>
                <!-- Aqui entrariam os cards das vagas -->
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: var(--text-secondary);">Nenhuma vaga encontrada no momento.</p>
        <?php endif; ?>
    </div>
</main>

<?php include("../includes/footer.php"); ?>