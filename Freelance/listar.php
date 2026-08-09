<?php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Busca os freelances com status 'aberto'
$sql = "SELECT f.*, e.nome_empresa, j.nome AS nome_jovem 
        FROM freelances f 
        LEFT JOIN empresas e ON f.empresa_id = e.id 
        LEFT JOIN jovens j ON f.jovem_contratante_id = j.id 
        WHERE f.status = 'aberto' 
        ORDER BY f.criado_em DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mural de Freelances | JovemConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<?php include_once dirname(__DIR__) . '/includes/header.php'; ?>

<main class="container" style="padding: 32px 16px; max-width: 1200px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2><i class="fa-solid fa-laptop-code" style="color: var(--accent);"></i> Oportunidades Freelance</h2>
            <p class="text-muted">Faça microtarefas, adquira experiência prática e receba com segurança.</p>
        </div>
        
        <a href="criar.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Publicar Projeto
        </a>
    </div>

    <!-- Feed de Cards -->
    <div class="grid-vagas" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($freelance = $result->fetch_assoc()): ?>
                <div class="card job-card" style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <span class="badge" style="background: var(--bg-surface); color: var(--accent); border: 1px solid var(--border); font-size: 12px;">
                                <?= htmlspecialchars($freelance['categoria']) ?>
                            </span>
                            <small class="text-muted"><i class="fa-regular fa-clock"></i> <?= intval($freelance['prazo_dias']) ?> dias</small>
                        </div>
                        
                        <h3 style="margin-bottom: 8px; font-size: 18px;"><?= htmlspecialchars($freelance['titulo']) ?></h3>
                        
                        <p class="text-muted" style="font-size: 14px; line-height: 1.5; margin-bottom: 16px;">
                            <?= htmlspecialchars(mb_substr($freelance['descricao'], 0, 110)) ?>...
                        </p>
                    </div>

                    <div style="border-top: 1px solid var(--border); padding-top: 12px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <small class="text-muted" style="display:block; font-size: 11px;">Orçamento Máx.</small>
                            <strong style="color: #2ecc71; font-size: 18px;">
                                R$ <?= number_format($freelance['orcamento_maximo'], 2, ',', '.') ?>
                            </strong>
                        </div>
                        <a href="detalhe.php?id=<?= intval($freelance['id']) ?>" class="btn btn-sm btn-secondary">
                            Ver Projeto
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                <i class="fa-solid fa-folder-open" style="font-size: 40px; color: var(--text-muted); margin-bottom: 12px;"></i>
                <p class="text-muted">Nenhum projeto de freelance aberto no momento.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include_once dirname(__DIR__) . '/includes/footer.php'; ?>

</body>
</html>