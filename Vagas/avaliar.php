<?php 
/* 1. CONFIGURAÇÕES E PROTEÇÃO */
include("../includes/config.php"); 
session_start();

if(!isset($_SESSION['jovem_id'])){
    echo "Acesso negado. Faça login.";
    exit;
}

// 2. RECUPERAÇÃO DE IDs (Vaga vem via URL ?vaga_id=X)
if(!isset($_GET['vaga_id'])){
    echo "Vaga não especificada.";
    exit;
}

$jovem_id = $_SESSION['jovem_id'];
$vaga_id = $_GET['vaga_id'];

/* 3. COLETA DE DADOS DO BANCO (jovemconnect) */
// Pega dados do jovem (atenção à coluna 'formacao' conforme o PDF)
$jovem = $conn->query("SELECT * FROM jovens WHERE id='$jovem_id'")->fetch_assoc();

// Pega dados da vaga
$vaga = $conn->query("SELECT * FROM vagas WHERE id='$vaga_id'")->fetch_assoc();

if(!$vaga){
    echo "Erro: Vaga inexistente.";
    exit;
}

/* 4. COMUNICAÇÃO COM A IA (PYTHON / FLASK) */
$dados_para_ia = [
    "formacao"     => $jovem['formacao'],
    "cidade_jovem" => $jovem['cidade'],
    "descricao"    => $vaga['descricao'],
    "cidade_vaga"  => $vaga['cidade']
];

$options = [
    "http" => [
        "header"  => "Content-Type: application/json\r\n",
        "method"  => "POST",
        "content" => json_encode($dados_para_ia),
        "timeout" => 5 // Tempo máximo de espera pela IA
    ]
];

$context = stream_context_create($options);
$url_ia = "http://localhost:5000/avaliar";

// Faz a requisição para o script Python
$result = @file_get_contents($url_ia, false, $context);

if ($result === FALSE) {
    $erro_ia = "Não foi possível conectar ao servidor de IA. Certifique-se de que o Python está rodando.";
} else {
    $resposta = json_decode($result, true);
}

/* 5. INTERFACE VISUAL */
include("../includes/header.php");
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <div class="card">
        <h1>🔍 Análise de Compatibilidade (IA)</h1>
        <p>A nossa inteligência analisa o seu perfil em relação aos requisitos desta vaga.</p>
        <hr>

        <?php if(isset($erro_ia)): ?>
            <div class="card" style="border-color: #ff4d4d;">
                <p style="color: #ff4d4d;"><?php echo $erro_ia; ?></p>
            </div>
        <?php else: ?>
            
            <div style="text-align: center; margin: 20px 0;">
                <h2 style="font-size: 2.5rem; color: var(--principal);">
                    <?php echo $resposta['compatibilidade']; ?>%
                </h2>
                <p><strong><?php echo $resposta['mensagem']; ?></strong></p>
            </div>

            <div class="card" style="background: #0F172A;">
                <h3>💡 Por que este resultado?</h3>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach($resposta['motivos'] as $motivo): ?>
                        <li style="margin-bottom: 10px;">✅ <?php echo $motivo; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

        <?php endif; ?>

        <br>
        <div style="display: flex; gap: 10px;">
            <a href="listar.php" class="btn" style="background: #334155;">Voltar às Vagas</a>
            <a href="../candidaturas/candidatar.php?vaga_id=<?php echo $vaga_id; ?>" class="btn">Candidatar-me Agora</a>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>