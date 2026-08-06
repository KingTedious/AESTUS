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

// Base da Query - JOIN com empresas para mostrar quem contratada
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
    <p>Encontre a oportunidade ideal e use a nossa IA para ver o seu Match.</p>

    <div class="card" style="background: #1e293b; padding: 20px; border: none;">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" name="busca" placeholder="O que procura?" value="<?php echo $busca; ?>" style="flex: 2; min-width: 200px;">
            <input type="text" name="cidade" placeholder="Cidade" value="<?php echo $cidade; ?>" style="flex: 1;">
            <input type="text" name="estado" placeholder="UF" maxlength="2" value="<?php echo $estado; ?>" style="flex: 0.5;">
            <button type="submit" class="btn" style="flex: 0.5;">Filtrar</button>
        </form>
    </div>

    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #334155;">

    <?php if($result->num_rows > 0): ?>
        <div style="display: grid; gap: 20px;">
            
            <?php while($vaga = $result->fetch_assoc()): 
                // Verifica se esta vaga está nos favoritos deste jovem
                $vaga_id = $vaga['id'];
                $check_fav = $conn->query("SELECT id FROM favoritos WHERE jovem_id='$jovem_id' AND vaga_id='$vaga_id'");
                $eh_favorito = $check_fav->num_rows > 0;
            ?>

            <div class="card" style="position: relative;">
                <div style="position: absolute; top: 20px; right: 20px;">
                    <a href="favoritar.php?vaga_id=<?php echo $vaga_id; ?>" style="text-decoration: none; font-size: 1.5rem;">
                        <?php echo $eh_favorito ? "⭐" : "☆"; ?>
                    </a>
                </div>

                <small style="color: var(--principal); font-weight: bold; text-transform: uppercase;">
                    <?php echo $vaga['nome_empresa']; ?>
                </small>
                <h3 style="margin-top: 5px;"><?php echo $vaga['titulo']; ?></h3>
                <p style="color: #94a3b8; font-size: 0.9rem;">
                    📍 <?php echo $vaga['cidade']; ?> - <?php echo $vaga['estado']; ?> | 
                    💼 <?php echo $vaga['tipo_contrato']; ?>
                </p>
                
                <p style="margin: 15px 0;"><?php echo substr($vaga['descricao'], 0, 200) . "..."; ?></p>

                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <a href="../candidaturas/candidatar.php?vaga=<?php echo $vaga_id; ?>" class="btn">Candidatar-me</a>
                    <a href="avaliar_ia.php?vaga_id=<?php echo $vaga_id; ?>" class="btn" style="background: #334155;">🤖 Analisar com IA</a>
                </div>
            </div>

            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="card">
    <div style="display: flex; justify-content: space-between; align-items: start;">
        <h3 style="margin: 0;"><?php echo $vaga['titulo']; ?></h3>
        <span class="badge"><?php echo $vaga['tipo_contrato']; ?></span>
    </div>
    <p style="color: var(--text-secondary); margin-top: 4px;">🏢 <?php echo $vaga['nome_empresa']; ?></p>
    
    <div style="display: flex; gap: 20px; margin: 20px 0; font-size: 0.9rem;">
        <span>💰 R$ <?php echo $vaga['salario']; ?></span>
        <span>🕒 20h/semana</span>
        <span>📍 <?php echo $vaga['cidade']; ?></span>
    </div>

    <a href="detalhes.php?id=<?php echo $vaga['id']; ?>" class="btn" style="width: 100%; background: transparent; border: 1px solid var(--border);">
        ✨ Avaliar Match com IA
    </a>
      </div>
        </div>
    <?php endif; ?>
</div>

<?php include("../includes/footer.php"); ?>