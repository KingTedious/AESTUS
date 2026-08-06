<?php
    /* 1. INÍCIO DA SESSÃO 
    Necessário para aceder aos dados de login. */
    session_start();

    /* 2. VERIFICAÇÃO DE SEGURANÇA
    Se não for jovem E não for empresa, volta para o login. */
    if(!isset($_SESSION['jovem_id']) && !isset($_SESSION['empresa_id'])){
        header("Location: ../index.php");
        exit;
    }

    /* 3. INCLUSÃO DO CABEÇALHO VISUAL */
    include("../includes/header.php");
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="card" style="text-align: center;">
        <?php
        /* 4. SAUDAÇÃO PERSONALIZADA
           Verifica qual tipo de conta está ativa no momento. */
        if(isset($_SESSION['jovem_nome'])){
            echo "<h1>Bem-vindo, Jovem: <span style='color: var(--principal);'>" . $_SESSION['jovem_nome'] . "</span></h1>";
            echo "<p>Redirecionando para o seu painel...</p>";
            // Opcional: Redirecionar automaticamente após 2 segundos
            header("Refresh: 2; url=jovem.php");
        }

        if(isset($_SESSION['empresa_nome'])){
            echo "<h1>Bem-vindo, Empresa: <span style='color: var(--principal);'>" . $_SESSION['empresa_nome'] . "</span></h1>";
            echo "<p>Redirecionando para o seu painel...</p>";
            // Opcional: Redirecionar automaticamente após 2 segundos
            header("Refresh: 2; url=empresa.php");
        }
        ?>

        <br><br>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <a href="jovem.php" class="btn">Ir para Dashboard</a>
            <a href="../logout.php" class="btn" style="background: #ff4d4d;">Sair</a>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>