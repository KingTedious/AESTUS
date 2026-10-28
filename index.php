<?php
/* ============================================================
   index.php — página inicial pública do JovemConnect

   - Visitante: apresentação (hero), seção "Sobre" com revelação suave ao
     rolar a página, rodapé com Termos de Uso, Privacidade e Contato (modais
     de includes/institucional.php) e o assistente (includes/assistente.php).
   - Quem já está logado é levado direto ao próprio painel.
   - Não abre conexão com o banco (página sem consultas).
   ============================================================ */
require_once __DIR__ . '/includes/session.php';   // Cookies de sessao endurecidos
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
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="assets/img/marca/jovemconnect-icone-180.png">
<script>
/* Revelar ao rolar: ligado antes da primeira pintura (sem "piscar"); sem suporte
   ou com "reduzir movimento", nada é escondido. */
if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches)
    document.documentElement.classList.add('revelar-ativo');
</script>
</head>
<body class="landing-page">

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav class="navbar" id="mainNav">
    <a href="index.php" class="nav-logo" aria-label="JovemConnect — página inicial">
        <picture class="marca marca-nav marca-tema-escuro">
            <source media="(max-width: 480px)" srcset="assets/img/marca/jovemconnect-simbolo.png" width="910" height="910">
            <img src="assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="848" height="291">
        </picture>
        <picture class="marca marca-nav marca-tema-claro">
            <source media="(max-width: 480px)" srcset="assets/img/marca/jovemconnect-simbolo-claro.png" width="910" height="910">
            <img src="assets/img/marca/jovemconnect-horizontal-claro.png" alt="JovemConnect" width="848" height="291" loading="lazy">
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
        <a href="comecar.php" class="btn btn-primary btn-sm">Começar</a><!-- Escolha Jovem/Empresa -->
    </div>
</nav>

<!-- ============================================================
     HERO SECTION
     Texto em primeiro plano; a foto oficial (pessoa no
     computador) fica ao fundo, até a borda da seção, discreta e coberta
     pela cor do site. Decorativa → alt vazio. No celular vira uma faixa
     abaixo do texto (não cobre o conteúdo).
     ============================================================ -->
<section class="hero-section">

    <div class="home-fundo hero-fundo" aria-hidden="true">
        <img src="assets/img/home/pessoa-computador.jpg" alt="" width="1398" height="2097" decoding="async">
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

    <!-- Foto oficial (edifício) ao fundo, do lado esquerdo até a borda -->
    <div class="home-fundo about-fundo" aria-hidden="true">
        <img src="assets/img/home/edificio-empresa.jpg" alt="" width="683" height="1024" loading="lazy" decoding="async">
    </div>

    <div class="about-text">
        <h2 data-revelar>Simplificando o Programa<br>Jovem Aprendiz</h2>
        <p data-revelar style="--ordem:1">
            Nossa plataforma utiliza <strong>inteligência artificial</strong> para encontrar
            a combinação perfeita entre o perfil do candidato e as necessidades da empresa.
        </p>
        <ul class="about-features">
            <li data-revelar style="--ordem:2">Análise de compatibilidade via IA</li>
            <li data-revelar style="--ordem:3">Gerador de currículos profissional</li>
            <li data-revelar style="--ordem:4">Filtros geográficos e de área</li>
        </ul>
        <div class="about-links" data-revelar style="--ordem:5">
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
        <img class="marca-tema-escuro" src="assets/img/marca/jovemconnect-horizontal.png" alt="JovemConnect" width="848" height="291" loading="lazy"><img class="marca-tema-claro" src="assets/img/marca/jovemconnect-horizontal-claro.png" alt="JovemConnect" width="848" height="291" loading="lazy">
    </div>
    <p class="footer-tagline" data-revelar>
        Promovendo inclusão social e profissional através de tecnologia de ponta
        e inteligência artificial generativa.
    </p>
    <!-- Abrem modais sem sair da página (includes/institucional.php); o texto dos
         Termos e da Privacidade fica em includes/documentos.php (minutas). -->
    <nav class="footer-links" aria-label="Informações do JovemConnect" data-revelar style="--ordem:1">
        <button type="button" class="footer-link" data-jc-modal-abrir="modalTermos" aria-haspopup="dialog"><span class="i i-file-text" aria-hidden="true"></span> Termos de Uso</button>
        <button type="button" class="footer-link" data-jc-modal-abrir="modalPrivacidade" aria-haspopup="dialog"><span class="i i-shield" aria-hidden="true"></span> Privacidade</button>
        <button type="button" class="footer-link" data-jc-modal-abrir="modalContato" aria-haspopup="dialog"><span class="i i-mail" aria-hidden="true"></span> Contato</button>
    </nav>
</footer>

<?php include __DIR__ . '/includes/institucional.php'; ?>

<!-- ============================================================
     ASSISTENTE + ACESSIBILIDADE + FABs
     Esta página tinha uma cópia própria do chat e do painel de
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

/* ── Revelar ao rolar ([data-revelar]) ─────────────────────────────
   Sem IntersectionObserver ou com "reduzir movimento", nada é escondido. */
(() => {
    const itens = document.querySelectorAll('[data-revelar]');
    if (!itens.length || !html.classList.contains('revelar-ativo')) return;   // ligado no <head>
    const obs = new IntersectionObserver(entradas => entradas.forEach(en => {
        if (en.isIntersecting) { en.target.classList.add('revelado'); obs.unobserve(en.target); }
    }), { threshold: 0.01 });
    itens.forEach(el => obs.observe(el));
})();

/* ── Navbar ao scroll ─────────────────────────────────────────── */
window.addEventListener('scroll', () => {
    document.getElementById('mainNav')
        ?.classList.toggle('scrolled', window.scrollY > 20);
}, { passive: true });

/* Chat e acessibilidade agora vêm de includes/assistente.php */

/* Aviso depois de excluir a conta (o redirecionamento já trazia ?conta=excluida) */
if (new URLSearchParams(location.search).get('conta') === 'excluida') {
    window.jcAviso?.('Sua conta foi excluída.', 'sucesso');
    history.replaceState(null, '', location.pathname);
}
</script>

</body>
</html>