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
    <!-- Stage 5: ícone da aba = símbolo oficial -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">

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
    <a href="../dashboard/<?= $_hdr_jovem ? 'jovem' : 'empresa' ?>.php" class="nav-logo"
       aria-label="JovemConnect — ir para o meu painel">
        <picture class="marca marca-nav">
            <source media="(max-width: 480px)" srcset="../assets/img/marca/jovemconnect-simbolo.png" width="192" height="192">
            <img src="../assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="493" height="160">
        </picture>
    </a>

    <!-- Barra de busca central.
         Stage 4: antes era só um campo solto — nenhuma página tratava a busca
         e digitar não fazia nada. Para o jovem, agora busca de verdade na
         lista de vagas. Para a empresa não existe busca global de candidatos,
         então o campo não aparece (o painel da empresa tem a busca dele). -->
    <div class="navbar-inner-center">
        <?php if ($_hdr_jovem): ?>
        <form method="GET" action="../Vagas/listar.php" role="search">
            <div class="search-bar">
                <span class="search-icon i i-search" aria-hidden="true"></span>
                <input
                    type="search"
                    name="busca"
                    id="searchGlobal"
                    class="search-global-input"
                    placeholder="Buscar vagas..."
                    autocomplete="off"
                    aria-label="Buscar vagas">
            </div>
        </form>
        <?php endif; ?>
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

        <!-- Atalhos e perfil (Stage 4: mesmos atalhos dos dashboards em todas as páginas internas) -->
        <?php if ($_hdr_jovem): ?>
            <a href="../candidaturas/minhas.php" class="icon-btn" title="Minhas candidaturas" aria-label="Minhas candidaturas"><span class="i i-clipboard-list" aria-hidden="true"></span></a>
            <a href="../Vagas/favoritos.php"     class="icon-btn" title="Vagas favoritas"     aria-label="Vagas favoritas"><span class="i i-star" aria-hidden="true"></span></a>
            <a href="../jovem/perfil.php"   class="icon-btn" title="Meu Perfil"       aria-label="Meu Perfil"><span class="i i-user" aria-hidden="true"></span></a>
        <?php else: ?>
            <a href="../Vagas/minhas.php"   class="icon-btn" title="Minhas vagas"      aria-label="Minhas vagas"><span class="i i-briefcase" aria-hidden="true"></span></a>
            <a href="../empresa/perfil.php" class="icon-btn" title="Perfil da Empresa" aria-label="Perfil da Empresa"><span class="i i-user" aria-hidden="true"></span></a>
        <?php endif; ?>

        <!-- Logout -->
        <a href="../logout.php"
           class="icon-btn text-danger"
           title="Sair"
           aria-label="Sair da conta"><span class="i i-log-out" aria-hidden="true"></span></a>
    </div>

</nav><!-- /.navbar-inner -->

<?php else: ?>

<!-- ============================================================
     NAVBAR PÚBLICA (visitante não autenticado)
     ============================================================ -->
<nav class="navbar">

    <a href="../index.php" class="nav-logo" aria-label="JovemConnect — página inicial">
        <picture class="marca marca-nav">
            <source media="(max-width: 480px)" srcset="../assets/img/marca/jovemconnect-simbolo.png" width="192" height="192">
            <img src="../assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="493" height="160">
        </picture>
    </a>

    <div class="nav-links">
        <a href="../index.php#sobre">Sobre</a>
        <a href="../jovem/login.php">Entrar</a><!-- Stage 4: mesmo destino do "Entrar" da página inicial (a tela tem as abas Jovem/Empresa) -->
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