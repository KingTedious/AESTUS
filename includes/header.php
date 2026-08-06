<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JovemConnect - O Futuro Começa Aqui</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Estrutura do Header -->
<header style="background-color: var(--bg-card); border-bottom: 1px solid var(--border); padding: 10px 0;">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; padding: 0 20px;">
        
        <!-- Lado Esquerdo: Logo -->
        <div class="logo">
            <a href="../dashboard/jovem.php" style="text-decoration: none; display: flex; align-items: center; gap: 10px;">
                <div style="background: var(--primary); width: 35px; height: 35px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                    JC
                </div>
                <span style="color: var(--text-main); font-weight: 700; font-size: 1.2rem;">JovemConnect</span>
            </a>
        </div>

        <!-- Lado Direito: Menu de Navegação e Perfil -->
        <nav style="display: flex; align-items: center; gap: 20px;">
            
            <?php if(isset($_SESSION['jovem_id'])): ?>
                <!-- Links Extras (Opcional) -->
                <a href="../dashboard/jovem.php" style="color: var(--text-secondary); text-decoration: none; font-size: 0.9rem;">Vagas</a>

                <!-- Botão de Perfil -->
                <a href="../jovem/perfil.php" class="profile-button" style="display: flex; align-items: center; gap: 10px; text-decoration: none; background: var(--bg-input); padding: 5px 15px; border-radius: 20px; border: 1px solid var(--border); transition: 0.3s;">
                    <span style="color: var(--text-main); font-size: 0.9rem; font-weight: 500;">
                        <?php echo explode(' ', $_SESSION['jovem_nome'])[0]; ?>
                    </span>
                    <!-- Círculo com a Inicial -->
                    <div style="width: 30px; height: 30px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: bold;">
                        <?php echo strtoupper(substr($_SESSION['jovem_nome'], 0, 1)); ?>
                    </div>
                </a>
            <?php endif; ?>

        </nav>
    </div>
</header>