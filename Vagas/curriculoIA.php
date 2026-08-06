<?php 
/* 1. CONFIGURAÇÕES E PROTEÇÃO */
include("../includes/config.php"); 
session_start();

if(!isset($_SESSION['jovem_id'])){
    header("Location: login.php");
    exit;
}

$jovem_id = $_SESSION['jovem_id'];
$resultado_ia = null;

/* 2. PROCESSAMENTO COM A IA (FLASK) */
if($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['texto'])){
    $texto = $_POST['texto'];
    $dados = ["texto" => $texto];

    $options = [
        "http" => [
            "header"  => "Content-Type: application/json\r\n",
            "method"  => "POST",
            "content" => json_encode($dados),
            "timeout" => 10
        ]
    ];

    $context = stream_context_create($options);
    $url_ia = "http://localhost:5000/curriculo";

    // Tentativa de conexão com o servidor Python
    $result = @file_get_contents($url_ia, false, $context);

    if ($result !== FALSE) {
        $resultado_ia = json_decode($result, true);
    } else {
        $erro_conexao = "O servidor de IA está offline. Tente novamente mais tarde.";
    }
}

include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="card">
        <h1>🤖 Mentor de Currículo (IA)</h1>
        <p>Cole o resumo do seu currículo abaixo e a nossa IA dará dicas para torná-lo irresistível para as empresas.</p>
        <hr>

        <form method="POST">
            <label>Seu Resumo Profissional / Experiências:</label>
            <textarea name="texto" rows="8" placeholder="Ex: Sou estudante de informática, procuro estágio, tenho conhecimentos em Excel..." required style="width: 100%; padding: 15px; border-radius: 8px; background: #0F172A; color: white; border: 1px solid var(--borda); font-family: inherit;"><?php echo isset($_POST['texto']) ? $_POST['texto'] : ''; ?></textarea>
            
            <button type="submit" class="btn" style="margin-top: 15px; width: 100%;">🚀 Analisar com IA</button>
        </form>

        <?php if($resultado_ia): ?>
            <div class="card" style="margin-top: 30px; border-left: 5px solid var(--principal); background: #1e293b;">
                <h3 style="color: var(--principal);">Resultado da Análise</h3>
                <p><strong><?php echo $resultado_ia['mensagem']; ?></strong></p>
                
                <?php if(!empty($resultado_ia['sugestoes'])): ?>
                    <ul style="margin-top: 15px; padding-left: 20px;">
                        <?php foreach($resultado_ia['sugestoes'] as $s): ?>
                            <li style="margin-bottom: 8px; color: #cbd5e1;">💡 <?php echo $s; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php elseif(isset($erro_conexao)): ?>
            <p style="color: #ff4d4d; margin-top: 20px;"><?php echo $erro_conexao; ?></p>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="../dashboard/jovem.php" style="color: #64748b;">← Voltar ao Dashboard</a>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>