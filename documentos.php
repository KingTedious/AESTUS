<?php
/* ============================================================
   documentos.php — Termos de Uso e Política de Privacidade em página
   própria (?doc=termos | ?doc=privacidade). Mesmo texto dos modais
   (includes/documentos.php). Funciona sem JavaScript e sem login —
   é o destino dos links do cadastro quando o JavaScript está desligado.
   ============================================================ */
require_once __DIR__ . '/includes/documentos.php';

$docs  = jc_documentos();
$chave = is_string($_GET['doc'] ?? null) && isset($docs[$_GET['doc']]) ? $_GET['doc'] : 'termos';
$doc   = $docs[$chave];
$outro = $chave === 'termos' ? 'privacidade' : 'termos';
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($doc['titulo']) ?> | JovemConnect</title>
    <link rel="stylesheet" href="assets/css/style.css?v=4.60">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/marca/jovemconnect-icone-32.png">
    <script>try { var t = localStorage.getItem('jc-theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}</script>
</head>
<body>
<main class="doc-pagina">
    <p><a href="index.php" class="doc-voltar"><span class="i i-arrow-left" aria-hidden="true"></span> Página inicial do JovemConnect</a></p>
    <h1><span class="i i-<?= $doc['icone'] ?>" aria-hidden="true"></span> <?= htmlspecialchars($doc['titulo']) ?></h1>
    <?= jc_documento_versao() ?>
    <section aria-labelledby="docResumo" class="card doc-resumo-caixa">
        <h2 id="docResumo">Resumo</h2>
        <?= jc_documento_resumo($doc) ?>
    </section>
    <section aria-label="Texto completo" class="doc-completo">
        <?= jc_documento_secoes($doc, 2) ?>
    </section>
    <p class="doc-rodape">
        Veja também: <a href="documentos.php?doc=<?= $outro ?>"><?= htmlspecialchars($docs[$outro]['titulo']) ?></a>
        <?php if (filter_var(JC_CONTATO_EMAIL, FILTER_VALIDATE_EMAIL)): ?>
        · Contato: <a href="mailto:<?= htmlspecialchars(JC_CONTATO_EMAIL) ?>"><?= htmlspecialchars(JC_CONTATO_EMAIL) ?></a>
        <?php endif; ?>
    </p>
    <p class="doc-rodape footer-copy"><?= htmlspecialchars(jc_copyright()) ?></p>
</main>
</body>
</html>
