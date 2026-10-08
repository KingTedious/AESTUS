<?php
/* ============================================================
   index.php — Landing page pública do JovemConnect

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. require_once 'includes/config.php' → página pública sem
      nenhuma query. Abria conexão de banco para todo visitante.
   2. 200+ linhas de CSS inline → tudo movido para o design system.
   3. Hero com layout centralizado (coluna única) → o print mostra
      duas colunas: texto à esquerda, imagem à direita.
   4. Sem session_start() → sem redirect para usuário já logado.
   5. Sem toggle de tema (dark/light) funcional.
   6. Sem chat widget do Assistente JovemConnect.
   7. Sem painel de acessibilidade.
   8. Navbar sem ícone de tema e sem logo-box.
   9. Footer sem links de Termos/Privacidade/Contato.

   Stage 5: marca oficial (horizontal na barra e no rodapé; símbolo no
   celular), fotos oficiais ao fundo do hero e da seção "Sobre" (no lugar
   dos emojis 👩‍💻/🏢), Termos/Privacidade/Contato em modais.
   ============================================================ */
require_once __DIR__ . '/includes/session.php';   // Etapa 2: cookies de sessao endurecidos
jc_session_start();

// Usuário já logado → redireciona para o painel correto
if (isset($_SESSION['jovem_id'])) {
    header("Location: dashboard/jovem.php");
    exit;
}
if (isset($_SESSION['empresa_id'])) {
    header("Location: dashboard/empresa.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JovemConnect — O seu primeiro passo para o sucesso profissional</title>
    <meta name="description"
          content="Conectamos jovens talentos com empresas que valorizam o aprendizado e o crescimento.">
    <link rel="stylesheet" href="Assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="assets/img/marca/jovemconnect-icone-180.png">
</head>
<body class="landing-page">

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav class="navbar" id="mainNav">
    <a href="index.php" class="nav-logo" aria-label="JovemConnect — página inicial">
        <picture class="marca marca-nav">
            <source media="(max-width: 480px)" srcset="assets/img/marca/jovemconnect-simbolo.png" width="192" height="192">
            <img src="assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="493" height="160">
        </picture>
    </a>

    <div class="nav-links">
        <a href="#sobre">Sobre</a>
    </div>

    <div class="nav-actions">
        <a href="jovem/login.php"
           style="color:var(--text-secondary);font-size:14px;font-weight:500;padding:8px 12px;text-decoration:none;">
            Entrar
        </a>
        <button class="btn-theme" id="btnTheme" title="Alternar tema" aria-label="Alternar tema">🌙</button>
        <a href="comecar.php" class="btn btn-primary btn-sm">Começar</a><!-- Stage 3: escolha Jovem/Empresa -->
    </div>
</nav>

<!-- ============================================================
     HERO SECTION
     Stage 5: texto em primeiro plano; a foto oficial (pessoa no
     computador) fica ao fundo, até a borda da seção, discreta e coberta
     pela cor do site. Decorativa → alt vazio. No celular vira uma faixa
     abaixo do texto (não cobre o conteúdo).
     ============================================================ -->
<section class="hero-section">

    <div class="home-fundo hero-fundo" aria-hidden="true">
        <img src="assets/img/home/pessoa-computador.jpg" alt="" width="275" height="183" decoding="async">
    </div>

    <div class="hero-text fade-up">
        <h1 class="hero-title">
            O seu primeiro passo<br>
            para o <span class="hl">sucesso<br>profissional</span>
        </h1>
        <p class="hero-desc">
            Conectamos jovens talentos em busca de oportunidades
            com empresas que valorizam o aprendizado e o crescimento.
        </p>
        <div class="hero-cta">
            <a href="jovem/cadastro.php" class="btn btn-primary btn-lg"><span class="i i-graduation-cap" aria-hidden="true"></span> Sou Jovem</a>
            <a href="empresa/cadastro.php" class="btn btn-secondary btn-lg"><span class="i i-building" aria-hidden="true"></span> Sou Empresa</a>
        </div>
    </div>

</section>

<div class="scroll-indicator" aria-hidden="true"><span class="i i-chevron-down" aria-hidden="true"></span></div>

<!-- ============================================================
     SEÇÃO "SIMPLIFICANDO"
     ============================================================ -->
<section class="about-section" id="sobre">

    <!-- Stage 5: foto oficial (edifício) ao fundo, do lado esquerdo até a borda -->
    <div class="home-fundo about-fundo" aria-hidden="true">
        <img src="assets/img/home/edificio-empresa.jpg" alt="" width="683" height="1024" loading="lazy" decoding="async">
    </div>

    <div class="about-text">
        <h2>Simplificando o Programa<br>Jovem Aprendiz</h2>
        <p>
            Nossa plataforma utiliza <strong>inteligência artificial</strong> para encontrar
            a combinação perfeita entre o perfil do candidato e as necessidades da empresa.
        </p>
        <ul class="about-features">
            <li>Análise de compatibilidade via IA</li>
            <li>Gerador de currículos profissional</li>
            <li>Filtros geográficos e de área</li>
        </ul>
        <div class="about-links">
            <a href="https://www.gov.br/trabalho-e-emprego/pt-br/servicos/jovem-aprendiz"
               target="_blank" rel="noopener" class="highlight">
                Guia Oficial do Jovem Aprendiz (Gov.br) <span class="i i-external-link" aria-hidden="true"></span><span class="sr-only">(abre em nova aba)</span>
            </a>
            <a href="https://www.planalto.gov.br/ccivil_03/leis/l10097.htm"
               target="_blank" rel="noopener">
                Leia a Lei do Aprendiz (Nº 10.097) <span class="i i-external-link" aria-hidden="true"></span><span class="sr-only">(abre em nova aba)</span>
            </a>
            <a href="https://www.planalto.gov.br/ccivil_03/decreto-lei/del5452.htm"
               target="_blank" rel="noopener">
                Consulte a CLT (Leis Trabalhistas) <span class="i i-external-link" aria-hidden="true"></span><span class="sr-only">(abre em nova aba)</span>
            </a>
        </div>
    </div>

</section>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer class="site-footer">
    <div class="footer-logo marca marca-rodape">
        <img src="assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="493" height="160" loading="lazy">
    </div>
    <p class="footer-tagline">
        Promovendo inclusão social e profissional através de tecnologia de ponta
        e inteligência artificial generativa.
    </p>
    <!-- Stage 5: abrem modais sem sair da página (includes/institucional.php).
         Os textos oficiais de Termos/Privacidade e o e-mail oficial de contato
         ainda não foram fornecidos — os modais mostram isso, sem texto inventado. -->
    <nav class="footer-links" aria-label="Informações do JovemConnect">
        <button type="button" class="footer-link" data-jc-modal-abrir="modalTermos" aria-haspopup="dialog"><span class="i i-file-text" aria-hidden="true"></span> Termos de Uso</button>
        <button type="button" class="footer-link" data-jc-modal-abrir="modalPrivacidade" aria-haspopup="dialog"><span class="i i-shield" aria-hidden="true"></span> Privacidade</button>
        <button type="button" class="footer-link" data-jc-modal-abrir="modalContato" aria-haspopup="dialog"><span class="i i-mail" aria-hidden="true"></span> Contato</button>
    </nav>
</footer>

<?php include __DIR__ . '/includes/institucional.php'; ?>

<!-- ============================================================
     ASSISTENTE + ACESSIBILIDADE + FABs
     Stage 4: esta página tinha uma cópia própria do chat e do painel de
     acessibilidade, com outras chaves no localStorage (a preferência ligada
     aqui se perdia nas outras páginas). Agora usa o mesmo componente de
     todas as páginas.
     ============================================================ -->
<?php
$assistente_boas_vindas = 'Bem-vindo à Página Inicial do JovemConnect! 👋 Aqui é onde tudo começa para conectar '
    . 'jovens aprendizes com empresas incríveis. Cadastre-se como jovem para encontrar vagas, ou como empresa '
    . 'para publicar oportunidades. Como posso te ajudar?';
include __DIR__ . '/includes/assistente.php';
?>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script>
/* ── Tema ────────────────────────────────────────────────────── */
const html = document.documentElement;
const btnTheme = document.getElementById('btnTheme');

function applyTheme(t) {
    html.setAttribute('data-theme', t);
    if (btnTheme) btnTheme.textContent = t === 'light' ? '☀️' : '🌙';
}
applyTheme(localStorage.getItem('jc-theme') || 'dark');
btnTheme?.addEventListener('click', () => {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    localStorage.setItem('jc-theme', next);
});

/* ── Navbar ao scroll ─────────────────────────────────────────── */
window.addEventListener('scroll', () => {
    document.getElementById('mainNav')
        ?.classList.toggle('scrolled', window.scrollY > 20);
}, { passive: true });

/* Stage 4: chat e acessibilidade agora vêm de includes/assistente.php */

/* Stage 4: aviso depois de excluir a conta (o redirecionamento já trazia ?conta=excluida) */
if (new URLSearchParams(location.search).get('conta') === 'excluida') {
    window.jcAviso?.('Sua conta foi excluída.', 'sucesso');
    history.replaceState(null, '', location.pathname);
}
</script>

</body>
</html>