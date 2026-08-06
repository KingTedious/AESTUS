<?php
session_start();

// Proteção: Garante que só empresa logada acessa
if (!isset($_SESSION['empresa_id'])) {
    header("Location: ../empresa/login.php"); // Corrigido para a sua pasta empresa/login.php
    exit;
}

// Caminho absoluto usando o config.php dentro de includes/
require_once dirname(__DIR__) . "/includes/config.php";

$empresa_id = $_SESSION['empresa_id'];

// Buscando o nome atualizado direto do banco
$query_emp_dados = "SELECT nome_empresa FROM empresas WHERE id = ?";
$stmt_emp = $conn->prepare($query_emp_dados);
$stmt_emp->bind_param("i", $empresa_id);
$stmt_emp->execute();
$res_emp = $stmt_emp->get_result()->fetch_assoc();
$empresa_nome = $res_emp['nome_empresa'] ?? $_SESSION['empresa_nome'] ?? 'Empresa';

// 1. BUSCAR VAGAS DA EMPRESA
$query_vagas = "SELECT id, titulo, descricao, cidade, estado FROM vagas WHERE empresa_id = ? ORDER BY id DESC";
$stmt_vagas = $conn->prepare($query_vagas);
$stmt_vagas->bind_param("i", $empresa_id);
$stmt_vagas->execute();
$result_vagas = $stmt_vagas->get_result();

// 2. BUSCAR JOVENS
$query_jovens = "SELECT id, nome, escolaridade_curso, cidade FROM jovens ORDER BY id DESC LIMIT 5";
$result_jovens = $conn->query($query_jovens);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Empresa | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css"> 
</head>
<body class="dark-theme">

    <header class="header-dashboard" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background-color: #1e1e24; border-bottom: 1px solid #333;">
        <div>
            <h1>Olá, <?php echo htmlspecialchars($empresa_nome); ?>! 👋</h1>
            <p>Gerencie suas oportunidades aqui.</p>
        </div>
        
        <div class="perfil-menu" style="display: flex; align-items: center; gap: 15px;">
            <a href="../Vagas/criar.php" class="btn-criar-vaga" style="background-color: #00ff88; color: #121214; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold;">+ Criar Nova Vaga</a>
            
            <a href="../empresa/perfil.php" class="avatar-letra" title="Editar Perfil" style="width: 45px; height: 45px; border-radius: 50%; border: 2px solid #00ff88; background-color: #333; color: #00ff88; display: flex; align-items: center; justify-content: center; font-weight: bold; text-transform: uppercase; text-decoration: none;">
                <?php echo mb_substr($empresa_nome, 0, 1); ?>
            </a>
            
            <a href="../logout.php" style="color: #ff5555; margin-left: 10px; text-decoration: none;">Sair</a>
        </div>
    </header>

    <main style="padding: 20px;">
        <section class="section-vagas" style="margin: 20px 0;">
            <h2>💼 Suas Vagas Publicadas</h2>
            <br>
            <div class="grid-vagas" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php if ($result_vagas && $result_vagas->num_rows > 0): ?>
                    <?php while($vaga = $result_vagas->fetch_assoc()): ?>
                        <div class="card-vaga" style="background: #1e1e24; padding: 20px; border-radius: 8px; border-left: 4px solid #00ff88;">
                            <h3><?php echo htmlspecialchars($vaga['titulo'] ?? 'Sem título'); ?></h3>
                            <p><strong>Local:</strong> <?php echo htmlspecialchars(($vaga['cidade'] ?? 'Não informada') . " - " . ($vaga['estado'] ?? '')); ?></p>
                            <p><?php echo substr(htmlspecialchars($vaga['descricao'] ?? ''), 0, 100) . '...'; ?></p>
                            <br>
                            <a href="../Vagas/candidatos.php?id=<?php echo $vaga['id']; ?>" style="color: #00ff88; text-decoration: none;">Gerenciar Candidatos →</a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>Você ainda não publicou nenhuma vaga. Clique em "+ Criar Nova Vaga" para começar!</p>
                <?php endif; ?>
            </div>
        </section>

        <hr style="border-color: #333; margin: 40px 0;">

        <section class="section-jovens" style="margin: 20px 0; opacity: 0.85;">
            <h2>🔍 Descobrir Talentos (Cadastros Recentes)</h2>
            <p style="font-size: 0.9em; color: #aaa;">Jovens recém-chegados à plataforma:</p>
            <br>
            <div class="grid-vagas" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php if ($result_jovens && $result_jovens->num_rows > 0): ?>
                    <?php while($jovem = $result_jovens->fetch_assoc()): ?>
                        <div class="card-vaga" style="background: #1e1e24; padding: 20px; border-radius: 8px; border-left: 4px solid #00aaff;">
                            <h3><?php echo htmlspecialchars($jovem['nome'] ?? 'Inominado'); ?></h3>
                            <p><strong>Curso:</strong> <?php echo htmlspecialchars($jovem['escolaridade_curso'] ?? 'Regular / Não especificado'); ?></p>
                            <p><strong>Cidade:</strong> <?php echo htmlspecialchars($jovem['cidade'] ?? 'Não informada'); ?></p>
                            <br>
                            <a href="../jovem/perfil.php?id=<?php echo $jovem['id']; ?>" style="color: #00aaff; text-decoration: none;">Ver Perfil Completo</a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>Nenhum jovem cadastrado no momento.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

</body>
</html>