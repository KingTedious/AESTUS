<?php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT f.*, 
           COALESCE(e.nome_empresa, j.nome) AS autor_nome
    FROM freelances f 
    LEFT JOIN empresas e ON f.empresa_id = e.id 
    LEFT JOIN jovens j ON f.jovem_contratante_id = j.id 
    WHERE f.id = ? LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$freelance = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$freelance) {
    header("Location: listar.php");
    exit;
}

$isJovem = isset($_SESSION['jovem_id']);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($freelance['titulo']) ?> | JovemConnect</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<?php include_once dirname(__DIR__) . '/includes/header.php'; ?>

<main class="container" style="padding: 32px 16px; max-width: 900px; margin: 0 auto;">

    <a href="listar.php" class="text-muted" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px;">
        <i class="fa-solid fa-arrow-left"></i> Voltar para o mural
    </a>

    <div class="card" style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
            <div>
                <h2><?= htmlspecialchars($freelance['titulo']) ?></h2>
                <p class="text-muted">Publicado por: <strong><?= htmlspecialchars($freelance['autor_nome']) ?></strong></p>
            </div>
            <span class="badge" style="background: rgba(75, 142, 241, 0.15); color: var(--accent); padding: 8px 16px; border-radius: 20px;">
                R$ <?= number_format($freelance['orcamento_maximo'], 2, ',', '.') ?> (Máx)
            </span>
        </div>

        <hr style="border-color: var(--border); margin: 20px 0;">

        <h3>Descrição do Serviço</h3>
        <p style="line-height: 1.6; white-space: pre-line; margin-top: 8px; color: var(--text-primary);">
            <?= htmlspecialchars($freelance['descricao']) ?>
        </p>

        <div style="margin-top: 24px; display: flex; gap: 24px; font-size: 14px;">
            <div><i class="fa-solid fa-tag text-muted"></i> Categoria: <strong><?= htmlspecialchars($freelance['categoria']) ?></strong></div>
            <div><i class="fa-solid fa-calendar-day text-muted"></i> Prazo max: <strong><?= intval($freelance['prazo_dias']) ?> dias</strong></div>
        </div>
    </div>

    <!-- Formulário de Proposta (Exclusivo para Jovens) -->
    <?php if ($isJovem): ?>
        <div class="card">
            <h3><i class="fa-solid fa-paper-plane" style="color: var(--accent);"></i> Enviar Proposta</h3>
            <p class="text-muted" style="font-size: 14px; margin-bottom: 20px;">
                Defina seu valor e mensagem. A taxa da plataforma é descontada automaticamente na aprovação.
            </p>

            <form action="processar.php" method="POST">
                <input type="hidden" name="action" value="enviar_proposta">
                <input type="hidden" name="freelance_id" value="<?= $freelance['id'] ?>">

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Seu Valor Proposto (R$)</label>
                        <input type="number" step="0.01" min="10" max="<?= $freelance['orcamento_maximo'] ?>" 
                               id="valor_proposto" name="valor_proposto" class="form-control" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Prazo para entrega (Dias)</label>
                        <input type="number" name="prazo_dias" class="form-control" required min="1" max="90" placeholder="Ex: 5">
                    </div>
                </div>

                <!-- Box de Resumo da Taxa da Plataforma -->
                <div id="taxa_box" style="background: var(--bg-surface); padding: 16px; border-radius: 8px; border: 1px solid var(--border); margin: 16px 0; display: none;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                        <span>Valor Proposto:</span>
                        <strong id="lbl_proposto">R$ 0,00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #e74c3c;">
                        <span>Taxa JovemConnect (<span id="lbl_percentual">10</span>%):</span>
                        <strong id="lbl_taxa">- R$ 0,00</strong>
                    </div>
                    <hr style="border-color: var(--border); margin: 8px 0;">
                    <div style="display: flex; justify-content: space-between; font-size: 16px; color: #2ecc71;">
                        <span>Você receberá (Líquido):</span>
                        <strong id="lbl_liquido">R$ 0,00</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mensagem / Apresentação</label>
                    <textarea name="mensagem" class="form-control" rows="4" required placeholder="Explique por que você é ideal para este freelance e como pretende realizá-lo..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Enviar Proposta Agora</button>
            </form>
        </div>
    <?php endif; ?>

</main>

<script>
// Simulador de Taxa Dinâmica em Tempo Real
document.getElementById('valor_proposto')?.addEventListener('input', function() {
    const valor = parseFloat(this.value) || 0;
    const box = document.getElementById('taxa_box');

    if (valor <= 0) {
        box.style.display = 'none';
        return;
    }

    box.style.display = 'block';

    let percentual = 10;
    if (valor > 500) {
        percentual = 5;
    } else if (valor > 200) {
        percentual = 8;
    }

    const taxa = valor * (percentual / 100);
    const liquido = valor - taxa;

    document.getElementById('lbl_proposto').innerText = 'R$ ' + valor.toFixed(2).replace('.', ',');
    document.getElementById('lbl_percentual').innerText = percentual;
    document.getElementById('lbl_taxa').innerText = '- R$ ' + taxa.toFixed(2).replace('.', ',');
    document.getElementById('lbl_liquido').innerText = 'R$ ' + liquido.toFixed(2).replace('.', ',');
});
</script>

<?php include_once dirname(__DIR__) . '/includes/footer.php'; ?>

</body>
</html>