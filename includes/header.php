<?php
/* ============================================================
   includes/header.php

   Abre o documento HTML e injeta a navbar correta baseada
   no estado de sessão.

   USO:
     // 1. session_start() sempre ANTES de incluir este arquivo
     // 2. (opcional) defina $page_title antes do include:
     //    $page_title = 'Minhas Candidaturas';
     //    include '../includes/header.php';

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
     · Somente suporte a $_SESSION['jovem_id'] — empresa invisível
     · Todos os estilos inline (sem classes do design system)
     · Variáveis CSS inexistentes: --primary, --text-main
     · Google Fonts duplicado (Inter carregado duas vezes + Poppins
       que não é usada em nenhuma outra parte do projeto)
     · Sem data-theme no <html> → tema nunca aplicado pelo CSS
     · Sem título dinâmico → todas as páginas mostram o mesmo título
     · Sem navbar pública para usuários não logados
   ============================================================ */

// Segurança: session_status evita "headers already sent" se
// a página chamar session_start() antes de incluir este arquivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Detecta estado de sessão ──────────────────────────────────────────────────
$_hdr_jovem   = isset($_SESSION['jovem_id']);
$_hdr_empresa = isset($_SESSION['empresa_id']);
$_hdr_logado  = $_hdr_jovem || $_hdr_empresa;

// ── Título dinâmico ───────────────────────────────────────────────────────────
// Cada página pode definir $page_title antes do include.
// Ex: $page_title = 'Minhas Candidaturas';
$_hdr_title = !empty($page_title)
    ? htmlspecialchars($page_title) . ' | JovemConnect'
    : 'JovemConnect — Conectando talentos ao futuro';
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="JovemConnect — conectando jovens talentos com oportunidades de carreira.">
    <title><?= $_hdr_title ?></title>

    <!-- Google Fonts: Inter — uma única requisição, pesos necessários -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
          rel="stylesheet">

    <!-- Design System JovemConnect v3.00 -->
    <link rel="stylesheet" href="../Assets/css/style.css">

    <!--
        Aplica o tema salvo ANTES do primeiro render do CSS.
        Sem este script inline, ocorre "flash of unstyled content":
        a página aparece brevemente no tema padrão antes de mudar.
    -->
    <script>
        (function () {
            var t = localStorage.getItem('jc-theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
        }());
    </script>
</head>
<body>

<?php if ($_hdr_logado): ?>

<!-- ============================================================
     NAVBAR INTERNA (usuário autenticado)
     ============================================================ -->
<nav class="navbar-inner">

    <!-- Logo → dashboard do tipo de usuário correto -->
    <a href="../dashboard/<?= $_hdr_jovem ? 'jovem' : 'empresa' ?>.php" class="nav-logo">
        <span class="logo-box">💼</span>
        <span class="logo-jovem">Jovem</span><span class="logo-connect">Connect</span>
    </a>

    <!-- Barra de busca central (ID usado pela página para adicionar filtro) -->
    <div class="navbar-inner-center">
        <div class="search-bar">
            <span class="search-icon">⌕</span>
            <input
                type="search"
                id="searchGlobal"
                class="search-global-input"
                placeholder="<?= $_hdr_jovem ? 'Buscar vagas...' : 'Buscar candidatos...' ?>"
                autocomplete="off"
                aria-label="<?= $_hdr_jovem ? 'Buscar vagas' : 'Buscar candidatos' ?>">
        </div>
    </div>

    <!-- Ações do lado direito -->
    <div class="navbar-inner-right">
        <!-- Toggle de tema -->
        <button
            class="icon-btn btn-theme"
            id="btnTheme"
            title="Alternar tema"
            aria-label="Alternar tema claro/escuro">
            🌙
        </button>

        <!-- Link de perfil (destino muda conforme o tipo) -->
        <?php if ($_hdr_jovem): ?>
            <a href="../jovem/perfil.php"   class="icon-btn" title="Meu Perfil"       aria-label="Meu Perfil">👤</a>
        <?php else: ?>
            <a href="../empresa/perfil.php" class="icon-btn" title="Perfil da Empresa" aria-label="Perfil da Empresa">👤</a>
        <?php endif; ?>

        <!-- Logout -->
        <a href="../logout.php"
           class="icon-btn text-danger"
           title="Sair"
           aria-label="Sair da conta">↪</a>
    </div>

</nav><!-- /.navbar-inner -->

<?php else: ?>

<!-- ============================================================
     NAVBAR PÚBLICA (visitante não autenticado)
     ============================================================ -->
<nav class="navbar">

    <a href="../index.php" class="nav-logo">
        <span class="logo-box">💼</span>
        <span class="logo-jovem">Jovem</span><span class="logo-connect">Connect</span>
    </a>

    <div class="nav-links">
        <a href="../index.php#sobre">Sobre</a>
        <a href="../empresa/login.php">Entrar</a>
    </div>

    <div class="nav-actions">
        <button
            class="btn-theme"
            id="btnTheme"
            title="Alternar tema"
            aria-label="Alternar tema claro/escuro">
            🌙
        </button>
        <a href="../comecar.php" class="btn btn-primary btn-sm">Começar</a><!-- Stage 3: antes ia ao login de empresa -->
    </div>

</nav><!-- /.navbar -->

<?php endif; ?>
<?php
// Limpa variáveis locais para não vazar para o escopo da página
unset($_hdr_jovem, $_hdr_empresa, $_hdr_logado, $_hdr_title);
?>