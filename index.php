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
</head>
<body class="landing-page">

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<nav class="navbar" id="mainNav">
    <a href="index.php" class="nav-logo">
        <span class="logo-box">💼</span>
        <span class="logo-jovem">Jovem</span><span class="logo-connect">Connect</span>
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
     HERO SECTION (2 colunas: texto + imagem)
     ============================================================ -->
<section class="hero-section">

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
            <a href="jovem/cadastro.php" class="btn btn-primary btn-lg">Sou Jovem 🎓</a>
            <a href="empresa/cadastro.php" class="btn btn-secondary btn-lg">Sou Empresa 💼</a>
        </div>
    </div>

    <div class="hero-image-wrap fade-up">
        <!--
            Substitua pelo <img> real quando disponível:
            <img src="Assets/img/hero.jpg" alt="Jovem trabalhando" loading="eager">
        -->
        <div style="
            width:100%;height:520px;
            background:linear-gradient(135deg,var(--bg-surface) 0%,var(--bg-hover) 60%,var(--bg-surface) 100%);
            display:flex;align-items:center;justify-content:center;
            color:var(--text-muted);font-size:96px;
        ">👩‍💻</div>
    </div>

</section>

<div class="scroll-indicator" aria-hidden="true">↓</div>

<!-- ============================================================
     SEÇÃO "SIMPLIFICANDO"
     ============================================================ -->
<section class="about-section" id="sobre">

    <div class="about-img-wrap">
        <div style="
            width:100%;height:420px;
            background:linear-gradient(160deg,var(--bg-surface),var(--bg-hover));
            display:flex;align-items:center;justify-content:center;font-size:80px;
        ">🏢</div>
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
                Guia Oficial do Jovem Aprendiz (Gov.br) ↗
            </a>
            <a href="https://www.planalto.gov.br/ccivil_03/leis/l10097.htm"
               target="_blank" rel="noopener">
                Leia a Lei do Aprendiz (Nº 10.097) →
            </a>
            <a href="https://www.planalto.gov.br/ccivil_03/decreto-lei/del5452.htm"
               target="_blank" rel="noopener">
                Consulte a CLT (Leis Trabalhistas) →
            </a>
        </div>
    </div>

</section>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer class="site-footer">
    <div class="footer-logo">
        <span class="logo-box" style="width:28px;height:28px;font-size:14px;">💼</span>
        <span class="logo-jovem">Jovem</span><span class="logo-connect">Connect</span>
    </div>
    <p class="footer-tagline">
        Promovendo inclusão social e profissional através de tecnologia de ponta
        e inteligência artificial generativa.
    </p>
    <nav class="footer-links">
        <a href="termos.php">📄 Termos</a>
        <a href="privacidade.php">🔒 Privacidade</a>
        <a href="contato.php">✉ Contato</a>
    </nav>
</footer>

<!-- ============================================================
     CHAT WIDGET — Assistente JovemConnect
     ============================================================ -->
<div class="chat-widget" id="chatWidget" role="dialog"
     aria-label="Assistente JovemConnect" aria-modal="false">
    <div class="chat-header">
        <div class="chat-header-left">
            <span>✦</span><span>Assistente JovemConnect</span>
        </div>
        <button class="chat-close" id="chatClose" aria-label="Fechar">✕</button>
    </div>
    <div class="chat-body" id="chatBody">
        <div class="chat-msg">
            Bem-vindo à Página Inicial do JovemConnect! 👋<br><br>
            Aqui é onde tudo começa para conectar jovens aprendizes com empresas incríveis.
            Cadastre-se como <strong>jovem</strong> para encontrar vagas, ou como
            <strong>empresa</strong> para publicar oportunidades.<br><br>
            Como posso te ajudar?
        </div>
    </div>
    <div class="chat-footer">
        <input type="text" class="chat-input" id="chatInput"
               placeholder="Pergunte algo..." aria-label="Mensagem">
        <button class="chat-send" id="chatSend" aria-label="Enviar">✈</button>
    </div>
</div>

<!-- ============================================================
     PAINEL DE ACESSIBILIDADE
     ============================================================ -->
<div class="a11y-panel" id="a11yPanel" role="dialog"
     aria-label="Acessibilidade" aria-modal="false">

    <div class="a11y-header">
        <h3>♿ Acessibilidade</h3>
        <button class="a11y-reset" id="a11yReset" title="Restaurar" aria-label="Restaurar padrões">↺</button>
    </div>

    <div class="a11y-body">

        <div class="a11y-item">
            <label class="a11y-label" for="selIdioma">🌐 Idioma / Language</label>
            <select id="selIdioma" class="form-control">
                <option value="pt">Português (Brasil)</option>
                <option value="en">English</option>
                <option value="es">Español</option>
            </select>
        </div>

        <hr class="a11y-divider">

        <div class="a11y-item">
            <label class="a11y-label" id="lblFs">
                T Tamanho do Texto (<span id="fontSizeVal">100</span>%)
            </label>
            <input type="range" id="rangeFontSize" min="80" max="140" value="100"
                   aria-labelledby="lblFs">
        </div>

        <hr class="a11y-divider">

        <div class="a11y-row">
            <label class="a11y-label" for="togDislexia">⋮ᴬ Fonte para Dislexia</label>
            <label class="toggle">
                <input type="checkbox" id="togDislexia">
                <span class="toggle-track"></span>
            </label>
        </div>

        <hr class="a11y-divider">

        <div class="a11y-item">
            <label class="a11y-label" for="selDaltonismo">🎨 Filtro de Daltonismo</label>
            <select id="selDaltonismo" class="form-control">
                <option value="">Nenhum</option>
                <option value="protanopia">Protanopia</option>
                <option value="deuteranopia">Deuteranopia</option>
                <option value="tritanopia">Tritanopia</option>
            </select>
        </div>

        <hr class="a11y-divider">

        <div class="a11y-row">
            <label class="a11y-label" for="togContraste">⊙ Alto Contraste</label>
            <label class="toggle">
                <input type="checkbox" id="togContraste">
                <span class="toggle-track"></span>
            </label>
        </div>

        <hr class="a11y-divider">

        <div class="a11y-row">
            <label class="a11y-label" for="togCinza">👁 Tons de Cinza</label>
            <label class="toggle">
                <input type="checkbox" id="togCinza">
                <span class="toggle-track"></span>
            </label>
        </div>

    </div>
</div>

<!-- FABs -->
<div class="fab-stack">
    <button class="fab fab-ai"   id="btnChat" title="Assistente IA"  aria-label="Abrir assistente">✦</button>
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Acessibilidade">♿</button>
</div>

<!-- SVG filters daltonismo -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
        <filter id="protanopia">
            <feColorMatrix type="matrix"
                values="0.567,0.433,0,0,0 0.558,0.442,0,0,0 0,0.242,0.758,0,0 0,0,0,1,0"/>
        </filter>
        <filter id="deuteranopia">
            <feColorMatrix type="matrix"
                values="0.625,0.375,0,0,0 0.7,0.3,0,0,0 0,0.3,0.7,0,0 0,0,0,1,0"/>
        </filter>
        <filter id="tritanopia">
            <feColorMatrix type="matrix"
                values="0.95,0.05,0,0,0 0,0.433,0.567,0,0 0,0.475,0.525,0,0 0,0,0,1,0"/>
        </filter>
    </defs>
</svg>

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

/* ── Chat Widget ──────────────────────────────────────────────── */
const chatWidget = document.getElementById('chatWidget');
const chatBody   = document.getElementById('chatBody');
const chatInput  = document.getElementById('chatInput');
const a11yPanel  = document.getElementById('a11yPanel');

document.getElementById('btnChat')?.addEventListener('click', () => {
    chatWidget.classList.toggle('is-open');
    a11yPanel?.classList.remove('is-open');
    if (chatWidget.classList.contains('is-open')) chatInput?.focus();
});
document.getElementById('chatClose')?.addEventListener('click', () => {
    chatWidget.classList.remove('is-open');
});

function addMsg(html, fromUser = false) {
    const d = document.createElement('div');
    d.className = 'chat-msg';
    if (fromUser) d.style.cssText =
        'background:var(--accent-subtle);border:1px solid var(--accent);margin-left:24px;';
    // Stage 3: o texto digitado pelo usuário nunca é interpretado como HTML
    if (fromUser) d.textContent = html; else d.innerHTML = html;
    chatBody.appendChild(d);
    chatBody.scrollTop = chatBody.scrollHeight;
    return d;
}

const respostas = {
    jovem:   'Para se cadastrar como jovem, clique em <strong>Sou Jovem</strong>. O processo é rápido e gratuito! 🎓',
    empresa: 'Para publicar vagas, clique em <strong>Sou Empresa</strong>. Use nossa IA para encontrar candidatos compatíveis! 💼',
    vaga:    'As vagas aparecem no dashboard após o login. A IA calcula automaticamente a compatibilidade com seu perfil! ✦',
    ia:      'Nossa IA analisa formação, localização e área de interesse para gerar um score de 0–100% de compatibilidade. ✦',
    aprendiz:'O Jovem Aprendiz é um programa que permite a jovens entre 14 e 24 anos seu primeiro emprego com carteira assinada. 📋',
};

async function sendChat() {
    const msg = chatInput.value.trim();
    if (!msg) return;
    chatInput.value = '';
    addMsg(msg, true);
    const spinner = addMsg('<span class="loading-spinner"></span>');

    // Stage 3: removido o fetch ao /ping do Flask — o resultado era ignorado
    // (respostas são predefinidas) e gerava erro no console com o Flask desligado.
    await new Promise(r => setTimeout(r, 350));

    spinner.remove();

    // Stage 3: casa PALAVRAS inteiras, sem acento — "bom dia" não dispara a
    // resposta de "ia"; "aprendiz" é testado antes de "jovem"; aceita plural.
    const palavras = msg.toLowerCase().normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '').split(/[^a-z0-9]+/);
    const radicais = { aprendiz: 'aprendiz', empresa: 'empresa', vaga: 'vaga', ia: 'ia', jovem: 'jove' };
    const key = Object.keys(radicais).find(k => palavras.some(p =>
        radicais[k].length <= 2 ? p === radicais[k] : p.startsWith(radicais[k])));
    addMsg(key
        ? respostas[key]
        : 'Boa pergunta! Para mais informações, explore o site ou entre em contato. 😊');
}

document.getElementById('chatSend')?.addEventListener('click', sendChat);
chatInput?.addEventListener('keydown', e => { if (e.key === 'Enter') sendChat(); });

/* ── Painel de Acessibilidade ─────────────────────────────────── */
document.getElementById('btnA11y')?.addEventListener('click', () => {
    a11yPanel.classList.toggle('is-open');
    chatWidget.classList.remove('is-open');
});

const rangeFs = document.getElementById('rangeFontSize');
rangeFs?.addEventListener('input', function () {
    document.getElementById('fontSizeVal').textContent = this.value;
    html.style.fontSize = (this.value / 100) + 'rem';
    localStorage.setItem('jc-fontsize', this.value);
});

document.getElementById('togDislexia')?.addEventListener('change', function () {
    html.style.fontFamily = this.checked ? '"OpenDyslexic","Comic Sans MS",cursive' : '';
    localStorage.setItem('jc-dyslexia', this.checked ? '1' : '0');
});

document.getElementById('selDaltonismo')?.addEventListener('change', function () {
    const f = { protanopia:'url(#protanopia)', deuteranopia:'url(#deuteranopia)', tritanopia:'url(#tritanopia)' };
    html.style.filter = f[this.value] || '';
    localStorage.setItem('jc-daltonismo', this.value);
});

document.getElementById('togContraste')?.addEventListener('change', function () {
    html.classList.toggle('high-contrast', this.checked);
    localStorage.setItem('jc-contraste', this.checked ? '1' : '0');
});

document.getElementById('togCinza')?.addEventListener('change', function () {
    html.style.filter = this.checked ? 'grayscale(100%)' : '';
    localStorage.setItem('jc-cinza', this.checked ? '1' : '0');
});

document.getElementById('a11yReset')?.addEventListener('click', () => {
    html.style.fontSize = html.style.fontFamily = html.style.filter = '';
    html.classList.remove('high-contrast');
    if (rangeFs) rangeFs.value = 100;
    document.getElementById('fontSizeVal').textContent = '100';
    ['togDislexia','togContraste','togCinza'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.checked = false;
    });
    document.getElementById('selDaltonismo').value = '';
    ['jc-fontsize','jc-dyslexia','jc-daltonismo','jc-contraste','jc-cinza']
        .forEach(k => localStorage.removeItem(k));
});

// Restaura preferências salvas
;(function() {
    const fs = localStorage.getItem('jc-fontsize');
    if (fs && rangeFs) {
        rangeFs.value = fs;
        document.getElementById('fontSizeVal').textContent = fs;
        html.style.fontSize = (fs / 100) + 'rem';
    }
    if (localStorage.getItem('jc-dyslexia') === '1') {
        document.getElementById('togDislexia').checked = true;
        html.style.fontFamily = '"OpenDyslexic","Comic Sans MS",cursive';
    }
    if (localStorage.getItem('jc-contraste') === '1') {
        document.getElementById('togContraste').checked = true;
        html.classList.add('high-contrast');
    }
    if (localStorage.getItem('jc-cinza') === '1') {
        document.getElementById('togCinza').checked = true;
        html.style.filter = 'grayscale(100%)';
    }
})();
</script>

</body>
</html>