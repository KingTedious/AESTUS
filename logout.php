<?php 
/* 1. PROTEÇÃO DE ACESSO E SESSÃO */
session_start();

// Se não houver sessão de jovem, redireciona para o login na raiz
if(!isset($_SESSION['jovem_id'])){
    header("Location: ../index.php");
    exit;
}

// 2. INCLUSÕES NECESSÁRIAS
include("../includes/config.php");
include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="margin: 0;">Olá, <span style="color: var(--principal);"><?php echo explode(' ', $_SESSION['jovem_nome'])[0]; ?></span>! 👋</h1>
            <p style="color: #94a3b8;">Bem-vindo ao seu painel Jovem Connect.</p>
        </div>
        <a href="../logout.php" style="color: #ff4d4d; text-decoration: none; font-weight: bold;">Sair da Conta</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        
        <div class="card" style="text-align: center;">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">🔍</div>
            <h3>Buscar Vagas</h3>
            <p>Encontre as melhores oportunidades para o seu perfil.</p>
            <br>
            <a href="../vagas/listar.php" class="btn" style="display: block;">Explorar</a>
        </div>

        <div class="card" style="text-align: center;">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">⭐</div>
            <h3>Favoritos</h3>
            <p>Veja as vagas que você guardou para analisar depois.</p>
            <br>
            <a href="../vagas/favoritos.php" class="btn" style="display: block; background: #334155;">Ver Salvas</a>
        </div>

        <div class="card" style="text-align: center; border: 1px solid var(--principal);">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">🤖</div>
            <h3>Mentor de IA</h3>
            <p>Melhore o seu currículo com dicas da nossa inteligência.</p>
            <br>
            <a href="../jovem/curriculo_ia.php" class="btn" style="display: block;">Melhorar Perfil</a>
        </div>

    </div>

    <div class="card" style="margin-top: 30px; background: #0F172A;">
        <h3>Suas Candidaturas</h3>
        <p style="color: #94a3b8; font-size: 0.9rem;">Você ainda não se candidatou a nenhuma vaga esta semana.</p>
        <br>
        <a href="../candidaturas/minhas_candidaturas.php" style="color: var(--principal); text-decoration: none;">Ver histórico completo ></a>
    </div>
</div>

<?php 
// 5. FECHAMENTO
include("../includes/footer.php"); 
?>