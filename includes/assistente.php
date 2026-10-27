<?php
require_once __DIR__ . '/compat.php';   // PHP 7.4/8.0: str_contains etc. (includes/compat.php)
/* ============================================================
   includes/assistente.php — assistente, painel de acessibilidade e botões
   flutuantes (✦ assistente / ♿ acessibilidade), iguais em todas as páginas.

   Assistente JovemConnect
   - Com a IA configurada (GEMINI_API_KEY no .env + servidor Flask no ar), as
     mensagens vão para IA/assistente_ajax.php → Flask → Gemini. A resposta é
     mostrada como TEXTO (textContent): nada vindo da IA é interpretado como
     HTML ou executado.
   - Sem IA (sem chave, Flask fora do ar, erro, tempo esgotado), responde com
     as respostas prontas (lista RESPOSTAS) e avisa que é uma resposta
     automática. Limite de mensagens → aviso amigável.
   - A conversa fica só na memória da página (some ao sair ou recarregar).
   - Saudação configurável por página: $assistente_boas_vindas = 'Texto...';
     (antes do include).

   Painel de acessibilidade
   - Tamanho do texto (amplia o conteúdo sem ampliar painéis e botões
     flutuantes), fonte para dislexia, alto contraste, tons de cinza e filtros
     de daltonismo. Preferências guardadas no navegador (localStorage, só
     essas preferências); chaves antigas são migradas uma vez (ver LEGADO).
     "Idioma" aparece como "em breve".

   Utilidades usadas pelas páginas
   - <form data-envio-unico="Enviando..."> — trava o botão depois do primeiro
     envio (evita cadastro/vaga/candidatura em dobro).
   - jcAviso('mensagem', 'erro'|'sucesso') — aviso flutuante.
   - Modais: <button data-jc-modal-abrir="idDoModal"> abre o .jc-modal;
     [data-jc-modal-fechar], Esc e clique no fundo fecham; o foco fica preso
     no modal e volta para quem abriu.

   Uso (antes de </body>, no nível do <body>):
     <?php include dirname(__DIR__) . '/includes/assistente.php'; ?>
   ============================================================ */
if (defined('JC_ASSISTENTE_INCLUIDO')) {
    return; // evita IDs duplicados se for incluído duas vezes
}
define('JC_ASSISTENTE_INCLUIDO', true);

require_once __DIR__ . '/csrf.php';   // token para o assistente com IA (páginas sem csrf_js)

// Caminho até a raiz do projeto ('' na página inicial, '../' nas subpastas) e tipo da página
$_assist_raiz = (realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(dirname(__DIR__))) ? '' : '../';
$_assist_arq  = strtolower(basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) . '/' . basename($_SERVER['SCRIPT_NAME'] ?? ''));
$_assist_pagina = 'outra';
foreach (['/index.php' => 'inicio', '/cadastro.php' => 'cadastro', '/login.php' => 'login', 'dashboard/' => 'painel',
          'vagas/listar.php' => 'vagas', 'vagas/criar.php' => 'vagas', 'vagas/minhas.php' => 'vagas',
          'vagas/favoritos.php' => 'favoritos', 'candidaturas/' => 'candidaturas', 'vagas/candidatar.php' => 'vaga',
          '/perfil.php' => 'perfil', 'vagas/candidatos.php' => 'candidatos', 'vagas/avaliar.php' => 'avaliacao',
          'empresa/locais.php' => 'locais', 'verificacoes/' => 'verificacao'] as $_k => $_v) {
    if (str_contains($_assist_arq, $_k)) { $_assist_pagina = $_v; break; }
}
unset($_k, $_v, $_assist_arq);

$_assist_boas_vindas = (isset($assistente_boas_vindas) && is_string($assistente_boas_vindas) && $assistente_boas_vindas !== '')
    ? $assistente_boas_vindas
    : 'Bem-vindo ao JovemConnect! Aqui é onde tudo começa para conectar jovens aprendizes com empresas incríveis. Como posso ajudar você hoje?';
?>
<!-- ============================================================
     CHAT WIDGET — Assistente JovemConnect
     ============================================================ -->
<div class="chat-widget" id="chatWidget" role="dialog" aria-label="Assistente JovemConnect" aria-hidden="true"
     data-endpoint="<?= htmlspecialchars($_assist_raiz) ?>IA/assistente_ajax.php"
     data-pagina="<?= htmlspecialchars($_assist_pagina) ?>"
     data-token="<?= htmlspecialchars(csrf_token()) ?>">

    <div class="chat-header">
        <div class="chat-header-left">
            <span class="i i-sparkles" aria-hidden="true"></span>
            <span>Assistente JovemConnect</span>
        </div>
        <button type="button" class="chat-close" id="chatClose" aria-label="Fechar assistente"><span class="i i-x" aria-hidden="true"></span></button>
    </div>

    <p class="chat-aviso" id="chatAviso">
        <span class="i i-sparkles" aria-hidden="true"></span>
        Assistente virtual com IA: pode cometer erros e não acessa sua conta. Não compartilhe dados pessoais.
    </p>

    <div class="chat-body" id="chatBody" aria-live="polite" aria-describedby="chatAviso">
        <div class="chat-msg"><?= htmlspecialchars($_assist_boas_vindas) ?></div>
    </div>

    <div class="chat-footer">
        <input
            type="text"
            class="chat-input"
            id="chatInput"
            placeholder="Pergunte algo..."
            aria-label="Digite sua mensagem"
            autocomplete="off"
            maxlength="500">
        <button type="button" class="chat-send" id="chatSend" aria-label="Enviar mensagem"><span class="i i-send" aria-hidden="true"></span></button>
    </div>

</div><!-- /#chatWidget -->


<!-- ============================================================
     PAINEL DE ACESSIBILIDADE
     ============================================================ -->
<div class="a11y-panel" id="a11yPanel" role="dialog" aria-label="Opções de acessibilidade" aria-hidden="true">

    <div class="a11y-header">
        <h3>
            <span class="i i-acessibilidade" aria-hidden="true"></span>
            Acessibilidade
        </h3>
        <div class="a11y-header-acoes">
            <button type="button" class="a11y-reset" id="a11yReset" title="Restaurar padrões" aria-label="Restaurar padrões de acessibilidade"><span class="i i-rotate-ccw" aria-hidden="true"></span></button>
            <!-- Fechar pelo × -->
            <button type="button" class="a11y-fechar" id="a11yFechar" title="Fechar" aria-label="Fechar painel de acessibilidade"><span class="i i-x" aria-hidden="true"></span></button>
        </div>
    </div>

    <div class="a11y-body">

        <!-- Idioma — a tradução ainda não existe; o controle não finge funcionar -->
        <div class="a11y-item">
            <label class="a11y-label" for="a11yIdioma">
                <span class="i i-globe" aria-hidden="true"></span> Idioma / Language
                <span class="text-muted" style="font-size:11px;font-weight:400;">(em breve)</span>
            </label>
            <select class="form-control" id="a11yIdioma" style="font-size:13px;" disabled
                    aria-describedby="a11yIdiomaInfo">
                <option value="pt-br" selected>Português (Brasil)</option>
                <option value="en">English</option>
            </select>
            <small id="a11yIdiomaInfo" class="text-muted" style="font-size:11px;">A versão em inglês ainda não está disponível.</small>
        </div>

        <hr class="a11y-divider">

        <!-- Tamanho do texto -->
        <div class="a11y-item">
            <label class="a11y-label" for="a11yFontSize">
                <span class="i i-tamanho-texto" aria-hidden="true"></span>
                Tamanho do Texto (<span id="fontSizeLabel">100</span>%)
            </label>
            <input type="range" id="a11yFontSize" min="80" max="140" value="100" step="5"
                   aria-valuemin="80" aria-valuemax="140" aria-valuenow="100">
        </div>

        <hr class="a11y-divider">

        <!-- Fonte para Dislexia -->
        <div class="a11y-row">
            <label class="a11y-label" for="a11yDyslexia">
                <span class="i i-type" aria-hidden="true"></span> Fonte para Dislexia
            </label>
            <label class="toggle">
                <input type="checkbox" id="a11yDyslexia">
                <span class="toggle-track"></span>
            </label>
        </div>

        <hr class="a11y-divider">

        <!-- Filtro de Daltonismo -->
        <div class="a11y-item">
            <label class="a11y-label" for="a11yColorFilter">
                <span class="i i-palette" aria-hidden="true"></span> Filtro de Daltonismo
            </label>
            <select class="form-control" id="a11yColorFilter" style="font-size:13px;">
                <option value="">Nenhum</option>
                <option value="protanopia">Protanopia</option>
                <option value="deuteranopia">Deuteranopia</option>
                <option value="tritanopia">Tritanopia</option>
            </select>
        </div>

        <hr class="a11y-divider">

        <!-- Alto Contraste -->
        <div class="a11y-row">
            <label class="a11y-label" for="a11yContrast">
                <span class="i i-contrast" aria-hidden="true"></span> Alto Contraste
            </label>
            <label class="toggle">
                <input type="checkbox" id="a11yContrast">
                <span class="toggle-track"></span>
            </label>
        </div>

        <hr class="a11y-divider">

        <!-- Tons de Cinza -->
        <div class="a11y-row">
            <label class="a11y-label" for="a11yGrayscale">
                <span class="i i-eye" aria-hidden="true"></span> Tons de Cinza
            </label>
            <label class="toggle">
                <input type="checkbox" id="a11yGrayscale">
                <span class="toggle-track"></span>
            </label>
        </div>

    </div><!-- /.a11y-body -->

</div><!-- /#a11yPanel -->


<!-- Espaço no fim da página (só no celular) para os últimos
     campos/botões não ficarem embaixo dos botões flutuantes -->
<div class="fab-espaco" aria-hidden="true"></div>

<!-- ============================================================
     FABs FLUTUANTES
     ============================================================ -->
<div class="fab-stack" id="fabStackGlobal">
    <button type="button" class="fab fab-ai"   id="btnChat" title="Assistente IA"  aria-label="Abrir assistente IA"
            aria-controls="chatWidget" aria-expanded="false"><span class="i i-sparkles" aria-hidden="true"></span></button>
    <button type="button" class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Abrir painel de acessibilidade"
            aria-controls="a11yPanel" aria-expanded="false"><span class="i i-acessibilidade" aria-hidden="true"></span></button>
</div>


<script>
(function () {
    'use strict';

    const html = document.documentElement;

    /* ── localStorage protegido (modo privado / bloqueado não quebra a página) ── */
    const ls = {
        get(k)    { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* sem persistência */ } },
        del(k)    { try { localStorage.removeItem(k); } catch (e) { /* idem */ } },
    };

    /* ── migração das chaves antigas (index.php e dashboards) ────────
       Quem ligou uma opção nessas páginas não perde a preferência: o valor
       antigo é copiado para a chave única (se ela ainda não existir) e a
       chave antiga é apagada. "jc-dyslexia" já era igual nas duas versões. */
    const LEGADO = {
        'jc-fontsize':   'jc-font-size',
        'jc-daltonismo': 'jc-color-filter',
        'jc-contraste':  'jc-contrast',
        'jc-cinza':      'jc-grayscale',
    };
    Object.keys(LEGADO).forEach(antiga => {
        const valor = ls.get(antiga);
        if (valor === null) return;
        if (ls.get(LEGADO[antiga]) === null) ls.set(LEGADO[antiga], valor);
        ls.del(antiga);
    });

    /* ── Chat Widget ───────────────────────────────────────────── */
    const chatWidget = document.getElementById('chatWidget');
    const chatBody   = document.getElementById('chatBody');
    const chatInput  = document.getElementById('chatInput');
    const btnChat    = document.getElementById('btnChat');
    const chatClose  = document.getElementById('chatClose');
    const chatSend   = document.getElementById('chatSend');
    const a11yPanel  = document.getElementById('a11yPanel');
    const btnA11y    = document.getElementById('btnA11y');

    function toggleChat(force) {
        const open = force !== undefined ? force : !chatWidget?.classList.contains('is-open');
        chatWidget?.classList.toggle('is-open', open);
        chatWidget?.setAttribute('aria-hidden', String(!open));
        btnChat?.setAttribute('aria-expanded', String(open));
        if (open) {
            a11yPanel?.classList.remove('is-open');
            a11yPanel?.setAttribute('aria-hidden', 'true');
            btnA11y?.setAttribute('aria-expanded', 'false');
            chatInput?.focus();
        }
    }

    btnChat?.addEventListener('click',  () => toggleChat());
    chatClose?.addEventListener('click', () => { toggleChat(false); btnChat?.focus(); });

    function appendMsg(texto, isUser = false) {
        const div = document.createElement('div');
        div.className = 'chat-msg' + (isUser ? ' chat-msg-usuario' : '');
        if (isUser) {
            div.style.cssText = 'background:var(--accent-subtle);margin-left:20%;';
        }
        div.textContent = texto;            // texto puro: nunca interpreta HTML (nem o digitado, nem o da IA)
        chatBody?.appendChild(div);
        if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
        return div;
    }

    // Respostas predefinidas (sem IA generativa). Ordem = prioridade.
    // Casa PALAVRAS inteiras (sem acento): "dia" não dispara a resposta de "ia";
    // radicais com 3+ letras aceitam plural ("vagas", "empresas", "jovens").
    const RESPOSTAS = [
        ['candidat', 'Para se candidatar, entre como jovem, abra “Vagas”, escolha a vaga e use “Candidatar-se”. Você acompanha o andamento em “Minhas candidaturas”. 📨'],
        ['curricul', 'No seu perfil você pode enviar o currículo em PDF e completar formação, habilidades e experiências — isso ajuda no Match. 📄'],
        ['senha',    'Esqueceu a senha? Na tela de login, use “Esqueci minha senha” para receber um código de recuperação. 🔑'],
        ['perfil',   'Mantenha o perfil completo (formação, habilidades, experiências). Você escolhe se empresas podem encontrar seu perfil nas buscas. 👤'],
        ['contato',  'Fale com a equipe pelo e-mail que aparece em “Contato”, no rodapé da página inicial. ✉️'],
        ['aprendiz', 'O Jovem Aprendiz é um programa que permite a jovens entre 14 e 24 anos seu primeiro emprego com carteira assinada. 📋'],
        ['empresa',  'Para publicar vagas, cadastre sua empresa em “Sou Empresa”. Nossa IA ajuda a encontrar candidatos compatíveis! 💼'],
        ['vaga',     'As vagas aparecem no seu painel após o login. A IA calcula a compatibilidade com o seu perfil! ✦'],
        ['ia',       'Nossa IA analisa formação, localização e área de interesse para gerar um score de 0 a 100% de compatibilidade. ✦'],
        ['jove',     'Para se cadastrar como jovem, use “Sou Jovem”. O processo é rápido e gratuito! 🎓'],
    ];
    const PADRAO = 'Boa pergunta! Para mais informações, explore o site ou entre em contato. 😊';

    function responder(texto) {
        const palavras = texto.toLowerCase().normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '').split(/[^a-z0-9]+/).filter(Boolean);
        const achou = RESPOSTAS.find(([chave]) =>
            palavras.some(p => chave.length <= 2 ? p === chave : p.startsWith(chave)));
        return achou ? achou[1] : PADRAO;
    }

    /* ── IA generativa com alternativa local ──────────────────
       Envia a mensagem para IA/assistente_ajax.php (que mascara dados pessoais,
       limita tamanho e quantidade e chama o Flask/Gemini). Se a IA não estiver
       disponível, responde com RESPOSTAS (acima). A conversa fica só aqui, na
       memória da página. */
    const historico = [];            // [{papel: 'usuario'|'assistente', texto}]
    let enviando = false;

    function mostrarResposta(el, texto, nota) {
        el.classList.remove('chat-msg-digitando');
        el.removeAttribute('aria-busy');
        el.textContent = texto;
        if (nota) {
            const n = document.createElement('small');
            n.className = 'chat-msg-nota';
            n.textContent = nota;
            el.appendChild(n);
        }
        if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
    }

    async function enviarMensagem() {
        const texto = (chatInput?.value || '').trim().slice(0, 500);
        if (!texto || enviando) return;
        enviando = true;
        chatInput.value = '';
        chatSend?.setAttribute('disabled', '');
        chatInput?.setAttribute('aria-busy', 'true');
        appendMsg(texto, true);

        const digitando = appendMsg('Digitando…');
        digitando.classList.add('chat-msg-digitando');
        digitando.setAttribute('aria-busy', 'true');

        let resposta = null, nota = '', mascarado = false;
        const ctrl = ('AbortController' in window) ? new AbortController() : null;
        const limite = setTimeout(() => ctrl?.abort(), 25000);
        try {
            const r = await fetch(chatWidget.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': chatWidget.dataset.token || '' },
                body: JSON.stringify({ mensagem: texto, historico: historico.slice(-6), pagina: chatWidget.dataset.pagina || 'outra' }),
                signal: ctrl?.signal,
                credentials: 'same-origin',
            });
            const dados = await r.json().catch(() => ({}));
            mascarado = dados.mascarado === true;
            if (r.status === 429) {
                resposta = typeof dados.erro === 'string' ? dados.erro : 'Muitas mensagens em pouco tempo. Aguarde um minuto.';
            } else if (r.ok && dados.fonte === 'ia' && typeof dados.resposta === 'string' && dados.resposta.trim()) {
                resposta = dados.resposta.trim();
                historico.push({ papel: 'usuario', texto }, { papel: 'assistente', texto: resposta });
            }
        } catch (e) { /* sem conexão ou tempo esgotado: usa a resposta pronta */ }
        clearTimeout(limite);

        if (resposta === null) {
            resposta = responder(texto);
            nota = 'Resposta automática: o assistente com IA não está disponível agora.';
        }
        if (mascarado) {
            nota = (nota ? nota + ' ' : '') + 'Removemos dados pessoais da sua mensagem antes de enviar.';
        }
        mostrarResposta(digitando, resposta, nota);
        enviando = false;
        chatSend?.removeAttribute('disabled');
        chatInput?.removeAttribute('aria-busy');
        chatInput?.focus();
    }

    chatSend?.addEventListener('click', enviarMensagem);
    chatInput?.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) enviarMensagem();
    });


    /* ── Painel de Acessibilidade ──────────────────────────────── */
    function toggleA11y(force) {
        const open = force !== undefined ? force : !a11yPanel?.classList.contains('is-open');
        a11yPanel?.classList.toggle('is-open', open);
        a11yPanel?.setAttribute('aria-hidden', String(!open));
        btnA11y?.setAttribute('aria-expanded', String(open));
        if (open) {
            chatWidget?.classList.remove('is-open');
            chatWidget?.setAttribute('aria-hidden', 'true');
            btnChat?.setAttribute('aria-expanded', 'false');
            // O painel vem ANTES do botão no HTML — sem isto, quem
            // abre pelo teclado não alcança as opções com Tab
            a11yPanel?.querySelector('.a11y-body input:not([disabled]), .a11y-body select:not([disabled])')?.focus();
        }
    }

    btnA11y?.addEventListener('click', () => toggleA11y());
    // Botão × do painel (o foco volta para o botão que abre o painel)
    document.getElementById('a11yFechar')?.addEventListener('click', () => { toggleA11y(false); btnA11y?.focus(); });

    // Fechar ao clicar fora
    document.addEventListener('click', e => {
        if (chatWidget?.classList.contains('is-open') &&
            !chatWidget.contains(e.target) && !btnChat?.contains(e.target))
            toggleChat(false);

        if (a11yPanel?.classList.contains('is-open') &&
            !a11yPanel.contains(e.target) && !btnA11y?.contains(e.target))
            toggleA11y(false);
    });

    // Fechar com Escape (o foco volta para o botão que abriu o painel)
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if (modaisAbertos.length) return;   // Termos/Privacidade/Contato aberto → o Esc fecha só o modal
        if (chatWidget?.classList.contains('is-open')) { toggleChat(false); btnChat?.focus(); }
        if (a11yPanel?.classList.contains('is-open'))  { toggleA11y(false); btnA11y?.focus(); }
    });


    /* ── Tamanho do texto ──────────────────────────────────────────
       O CSS do projeto usa px, então mudar o font-size da raiz
       não alterava nada. O conteúdo da página é ampliado com "zoom"
       (regra html[data-jc-zoom] no style.css), sem ampliar o chat, este
       painel e os botões flutuantes — eles continuam inteiros na tela. */
    const fontRange = document.getElementById('a11yFontSize');
    const fontLabel = document.getElementById('fontSizeLabel');

    function applyFontSize(v) {
        v = Math.min(140, Math.max(80, parseInt(v, 10) || 100));
        if (v === 100) {
            html.removeAttribute('data-jc-zoom');
            html.style.removeProperty('--jc-zoom');
        } else {
            html.setAttribute('data-jc-zoom', String(v));
            html.style.setProperty('--jc-zoom', String(v / 100));
        }
        if (fontLabel) fontLabel.textContent = v;
        if (fontRange) { fontRange.value = v; fontRange.setAttribute('aria-valuenow', v); }
    }

    fontRange?.addEventListener('input', function () {
        applyFontSize(this.value);
        ls.set('jc-font-size', this.value);
    });

    const savedFont = ls.get('jc-font-size');
    if (savedFont) applyFontSize(savedFont);


    /* ── Fonte para Dislexia ───────────────────────────────────── */
    const chkDyslexia = document.getElementById('a11yDyslexia');

    function applyDyslexia(on) {
        if (on) {
            html.style.setProperty('--font', '"OpenDyslexic", "Comic Sans MS", cursive');
        } else {
            html.style.removeProperty('--font');
        }
        if (chkDyslexia) chkDyslexia.checked = on;
    }

    chkDyslexia?.addEventListener('change', function () {
        applyDyslexia(this.checked);
        ls.set('jc-dyslexia', this.checked ? '1' : '0');
    });

    if (ls.get('jc-dyslexia') === '1') applyDyslexia(true);


    /* ── Filtros de cor: daltonismo + tons de cinza ────────────────
       Os dois usam a propriedade "filter" — antes um apagava o
       outro. Agora são combinados num único valor. */
    const selFilter = document.getElementById('a11yColorFilter');
    const chkGray   = document.getElementById('a11yGrayscale');

    const filterMap = {
        protanopia:   'grayscale(0.1) sepia(0.4) hue-rotate(-30deg)',
        deuteranopia: 'grayscale(0.1) sepia(0.3) hue-rotate(30deg)',
        tritanopia:   'grayscale(0.1) sepia(0.2) hue-rotate(180deg)',
    };
    let filtroCor = '';
    let cinza     = false;

    function aplicarFiltros() {
        const partes = [];
        if (filterMap[filtroCor]) partes.push(filterMap[filtroCor]);
        if (cinza) partes.push('grayscale(1)');
        html.style.filter = partes.join(' ');
    }

    function applyColorFilter(v) {
        filtroCor = filterMap[v] ? v : '';
        if (selFilter) selFilter.value = filtroCor;
        aplicarFiltros();
    }

    function applyGrayscale(on) {
        cinza = !!on;
        if (chkGray) chkGray.checked = cinza;
        aplicarFiltros();
    }

    selFilter?.addEventListener('change', function () {
        applyColorFilter(this.value);
        ls.set('jc-color-filter', this.value);
    });

    chkGray?.addEventListener('change', function () {
        applyGrayscale(this.checked);
        ls.set('jc-grayscale', this.checked ? '1' : '0');
    });

    const savedFilter = ls.get('jc-color-filter');
    if (savedFilter) applyColorFilter(savedFilter);
    if (ls.get('jc-grayscale') === '1') applyGrayscale(true);


    /* ── Alto Contraste (regras html[data-high-contrast] no style.css) ── */
    const chkContrast = document.getElementById('a11yContrast');

    function applyContrast(on) {
        html.toggleAttribute('data-high-contrast', on);
        if (chkContrast) chkContrast.checked = on;
    }

    chkContrast?.addEventListener('change', function () {
        applyContrast(this.checked);
        ls.set('jc-contrast', this.checked ? '1' : '0');
    });

    if (ls.get('jc-contrast') === '1') applyContrast(true);


    /* ── Restaurar Padrões ─────────────────────────────────────── */
    document.getElementById('a11yReset')?.addEventListener('click', () => {
        const keys = ['jc-font-size','jc-dyslexia','jc-color-filter','jc-contrast','jc-grayscale']
            .concat(Object.keys(LEGADO));
        keys.forEach(k => ls.del(k));

        applyFontSize(100);
        applyDyslexia(false);
        applyColorFilter('');
        applyContrast(false);
        applyGrayscale(false);
    });


    /* ── aviso flutuante para erros/sucessos de ações AJAX ──
       Reaproveita o .toast do style.css. Erros ficam mais tempo na tela. */
    window.jcAviso = function (mensagem, tipo) {
        const erro = (tipo !== 'sucesso');
        let el = document.getElementById('jcAviso');
        if (!el) {
            el = document.createElement('div');
            el.id = 'jcAviso';
            document.body.appendChild(el);
        }
        el.className = 'toast ' + (erro ? 'toast-error' : 'toast-success');
        el.setAttribute('role', erro ? 'alert' : 'status');
        el.textContent = '';
        clearTimeout(el._timer); clearTimeout(el._texto);
        // o texto entra um instante depois da região existir — senão o leitor de tela não anuncia
        el._texto = setTimeout(() => {
            // Ícone de erro/sucesso; a mensagem continua como texto puro
            const icone = document.createElement('span');
            icone.className = 'i ' + (erro ? 'i-x-circle' : 'i-check-circle');
            icone.setAttribute('aria-hidden', 'true');
            el.replaceChildren(icone, document.createTextNode(mensagem));
            el.classList.add('toast-visible');
            el._timer = setTimeout(() => el.classList.remove('toast-visible'), erro ? 7000 : 4000);
        }, 60);
    };


    /* ── modais padronizados ────────────────────────────────
       <button data-jc-modal-abrir="idDoModal"> abre o <div class="jc-modal">;
       qualquer [data-jc-modal-fechar] dentro dele fecha (×, "Fechar").
       Esc e clique no fundo escuro também fecham; o foco volta para o
       elemento que abriu e a página atrás não rola enquanto ele está aberto.
       (Os modais de excluir conta / remover vaga continuam com o script
       da própria página.) */
    const modaisAbertos = [];
    function abrirModal(modal, origem) {
        if (!modal || modaisAbertos.includes(modal)) return;
        modal._jcOrigem = origem || document.activeElement;
        modal.style.display = 'flex';
        modal.scrollTop = 0;
        modaisAbertos.push(modal);
        html.classList.add('jc-sem-rolagem');
        (modal.querySelector('[data-jc-foco-inicial]') || modal.querySelector('.jc-modal-fechar'))?.focus();
    }
    function fecharModal(modal) {
        const i = modaisAbertos.indexOf(modal);
        if (i === -1) return;
        modaisAbertos.splice(i, 1);
        modal.style.display = 'none';
        if (!modaisAbertos.length) html.classList.remove('jc-sem-rolagem');
        modal._jcOrigem?.focus?.();
    }
    window.jcModal = { abrir: abrirModal, fechar: fecharModal };

    // Cria um ícone decorativo para mensagens montadas pelo JavaScript
    window.jcIcone = function (nome) {
        const s = document.createElement('span');
        s.className = 'i i-' + nome;
        s.setAttribute('aria-hidden', 'true');
        return s;
    };

    document.addEventListener('click', e => {
        const abre = e.target.closest?.('[data-jc-modal-abrir]');
        if (abre) {
            e.preventDefault();
            abrirModal(document.getElementById(abre.getAttribute('data-jc-modal-abrir')), abre);
            return;
        }
        const fecha = e.target.closest?.('[data-jc-modal-fechar]');
        if (fecha) {
            fecharModal(fecha.closest('.jc-modal'));
            // ex.: "Abrir o assistente" no modal de Contato
            if (fecha.hasAttribute('data-jc-abrir-assistente')) toggleChat(true);
            return;
        }
        if (modaisAbertos.includes(e.target)) fecharModal(e.target);   // clique no fundo, fora da caixa
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modaisAbertos.length) {
            e.preventDefault();
            fecharModal(modaisAbertos[modaisAbertos.length - 1]);
        }
    });


    /* ── modal aberto (role="dialog" aria-modal="true") prende o Tab ──
       Antes o Tab saía do modal e chegava aos botões ✦/♿, escondidos atrás
       do fundo escuro. Vale para os modais de excluir conta e remover vaga. */
    document.addEventListener('keydown', e => {
        if (e.key !== 'Tab') return;
        const modal = [...document.querySelectorAll('[role="dialog"][aria-modal="true"]')]
            .find(m => getComputedStyle(m).display !== 'none');
        if (!modal) return;
        const itens = [...modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')];
        if (!itens.length) return;
        const primeiro = itens[0], ultimo = itens[itens.length - 1];
        if (e.shiftKey && (document.activeElement === primeiro || !modal.contains(document.activeElement))) {
            e.preventDefault(); ultimo.focus();
        } else if (!e.shiftKey && (document.activeElement === ultimo || !modal.contains(document.activeElement))) {
            e.preventDefault(); primeiro.focus();
        }
    });


    /* ── formulário com envio único ──────────────────────
       <form data-envio-unico="Enviando..."> → depois do primeiro envio
       válido, o botão é travado e mostra o texto. Validações da própria
       página (que chamam preventDefault) rodam antes e continuam valendo.
       Ao voltar pelo navegador (página restaurada do cache), destrava. */
    document.addEventListener('submit', e => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-envio-unico')) return;
        if (e.defaultPrevented) return;
        if (form.dataset.jcEnviando === '1') { e.preventDefault(); return; }
        form.dataset.jcEnviando = '1';
        const btn = e.submitter || form.querySelector('[type="submit"]');
        if (btn) {
            btn.dataset.jcTextoOriginal = btn.innerHTML;
            btn.textContent = form.getAttribute('data-envio-unico') || 'Enviando...';
            // desabilita depois que o navegador montou os dados do envio
            setTimeout(() => { btn.disabled = true; }, 0);
            btn.setAttribute('aria-disabled', 'true');
        }
    });
    window.addEventListener('pageshow', ev => {
        if (!ev.persisted) return;
        document.querySelectorAll('form[data-envio-unico]').forEach(form => {
            delete form.dataset.jcEnviando;
        });
        // o botão pode estar fora do <form> (ligado por form="...") — procura no documento
        document.querySelectorAll('[data-jc-texto-original]').forEach(btn => {
            btn.innerHTML = btn.dataset.jcTextoOriginal;
            btn.disabled = false;
            btn.removeAttribute('aria-disabled');
            delete btn.dataset.jcTextoOriginal;
        });
    });

})();
</script>
