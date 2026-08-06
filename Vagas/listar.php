<?php
/* 1. CONFIGURAÇÕES E SESSÃO */
include("../includes/config.php");
session_start();

// Proteção básica
if(!isset($_SESSION['jovem_id'])){
    header("Location: ../jovem/login.php");
    exit;
}

$jovem_id = $_SESSION['jovem_id'];

/* 2. LÓGICA DE FILTROS (CAPTURA VIA GET) */
$busca  = $_GET['busca'] ?? "";
$cidade = $_GET['cidade'] ?? "";
$estado = $_GET['estado'] ?? "";

// Base da Query - JOIN com empresas para mostrar quem contrata
$sql = "SELECT v.*, e.nome_empresa 
        FROM vagas v
        JOIN empresas e ON v.empresa_id = e.id 
        WHERE 1=1";

// Aplicação dinâmica de filtros
if(!empty($busca)){
    $sql .= " AND (v.titulo LIKE '%$busca%' OR v.descricao LIKE '%$busca%')";
}
if(!empty($cidade)){
    $sql .= " AND v.cidade LIKE '%$cidade%'";
}
if(!empty($estado)){
    $sql .= " AND v.estado LIKE '%$estado%'";
}

$sql .= " ORDER BY v.id DESC"; // Vagas mais recentes primeiro
$result = $conn->query($sql);

include("../includes/header.php"); 
?>

<link rel="stylesheet" href="../assets/css/style.css">

<div class="container">
    <h1>Vagas Disponíveis</h1>
    <p class="subtitulo">Encontre a oportunidade ideal e use a nossa IA para ver o seu Match.</p>

    <div class="card card-filtros">
        <form method="GET" class="form-filtros">
            <input type="text" name="busca" placeholder="O que procura?" value="<?php echo htmlspecialchars($busca); ?>">
            <input type="text" name="cidade" placeholder="Cidade" value="<?php echo htmlspecialchars($cidade); ?>">
            <input type="text" name="estado" placeholder="UF" maxlength="2" value="<?php echo htmlspecialchars($estado); ?>">
            <button type="submit" class="btn">Filtrar</button>
        </form>
    </div>

    <hr class="divisor-dashboard">

    <?php if($result && $result->num_rows > 0): ?>
        <div class="grid-vagas-lista">
            
            <?php while($vaga = $result->fetch_assoc()): 
                $vaga_id = $vaga['id'];
                
                // Verifica se esta vaga está nos favoritos deste jovem
                $check_fav = $conn->query("SELECT id FROM favoritos WHERE jovem_id='$jovem_id' AND vaga_id='$vaga_id'");
                $eh_favorito = $check_fav && $check_fav->num_rows > 0;
            ?>

            <div class="card card-vaga-item">
                <div class="container-favorito">
                    <a href="favoritar.php?vaga_id=<?php echo $vaga_id; ?>" class="btn-favorito">
                        <?php echo $eh_favorito ? "⭐" : "☆"; ?>
                    </a>
                </div>

                <small class="empresa-badge">
                    <?php echo htmlspecialchars($vaga['nome_empresa']); ?>
                </small>
                
                <h3><?php echo htmlspecialchars($vaga['titulo']); ?></h3>
                
                <p class="meta-dados-vaga">
                    📍 <?php echo htmlspecialchars($vaga['cidade']); ?> - <?php echo htmlspecialchars($vaga['estado']); ?> | 
                    💼 <?php echo htmlspecialchars($vaga['tipo_contrato'] ?? 'Efetivo'); ?>
                </p>
                
                <?php if(!empty($vaga['salario'])): ?>
                    <p class="salario-vaga">💰 R$ <?php echo htmlspecialchars($vaga['salario']); ?></p>
                <?php endif; ?>
                
                <p class="descricao-vaga"><?php echo substr(htmlspecialchars($vaga['descricao']), 0, 200) . "..."; ?></p>

                <div class="botoes-acoes">
                    <a href="../Candidaturas/candidatar.php?vaga=<?php echo $vaga_id; ?>" class="btn">Candidatar-me</a>
                    <a href="avaliar.php?vaga_id=<?php echo $vaga_id; ?>" class="btn btn-secundario">🤖 Analisar com IA</a>
                </div>
            </div>

            <?php endwhile; ?>
        </div>
    
    <?php else: ?>
        <div class="card card-aviso-vazio">
            <p>Nenhuma vaga encontrada para os filtros aplicados no momento.</p>
        </div>
    <?php endif; ?>
</div>

<?php include("../includes/footer.php"); ?>