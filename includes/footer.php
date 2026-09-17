<!--
=================================================================
  includes/footer.php

  Injetado no final de cada página que usa o padrão
  include header.php / include footer.php.

  Contém:
    · Chat widget do Assistente JovemConnect
    · Painel de Acessibilidade completo
    · FABs flutuantes (IA + acessibilidade)
    · JavaScript global: tema, chat, acessibilidade, persistência

  ⚠ ATENÇÃO sobre o </div> do original:
    O arquivo original abria com </div> solto para fechar um
    container aberto em header.php. Esse acoplamento implícito
    é problemático — qualquer mudança no header quebrava o layout.
    Com o novo design system, cada página gerencia seus próprios
    containers. Se seu header.php ainda abre um wrapper, feche-o
    explicitamente NO PRÓPRIO header.php ou na página.
=================================================================
-->

<!-- ============================================================
     CHAT WIDGET — Assistente JovemConnect
     ============================================================ -->
<div class="chat-widget" id="chatWidget" role="dialog" aria-label="Assistente JovemConnect" aria-hidden="true">

    <div class="chat-header">
        <div class="chat-header-left">
            <span aria-hidden="true">✦</span>
            <span>Assistente JovemConnect</span>
        </div>
        <button class="chat-close" id="chatClose" aria-label="Fechar assistente">✕</button>
    </div>

    <div class="chat-body" id="chatBody" aria-live="polite">
        <div class="chat-msg">
            Bem-vindo ao JovemConnect! Aqui é onde tudo começa para conectar jovens
            aprendizes com empresas incríveis. Como posso ajudar você hoje?
        </div>
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
        <button class="chat-send" id="chatSend" aria-label="Enviar mensagem">➤</button>
    </div>

</div><!-- /#chatWidget -->


<!-- ============================================================
     PAINEL DE ACESSIBILIDADE
     ============================================================ -->
<div class="a11y-panel" id="a11yPanel" role="dialog" aria-label="Opções de acessibilidade" aria-hidden="true">

    <div class="a11y-header">
        <h3>
            <span aria-hidden="true">♿</span>
            Acessibilidade
        </h3>
        <button class="a11y-reset" id="a11yReset" title="Restaurar padrões" aria-label="Restaurar padrões de acessibilidade">↺</button>
    </div>

    <div class="a11y-body">

        <!-- Idioma -->
        <div class="a11y-item">
            <label class="a11y-label" for="a11yIdioma">
                <span aria-hidden="true">🌐</span> Idioma / Language
            </label>
            <select class="form-control" id="a11yIdioma" style="font-size:13px;">
                <option value="pt-br" selected>Português (Brasil)</option>
                <option value="en">English</option>
            </select>
        </div>

        <hr class="a11y-divider">

        <!-- Tamanho do texto -->
        <div class="a11y-item">
            <label class="a11y-label" for="a11yFontSize">
                <span aria-hidden="true">T</span>
                Tamanho do Texto (<span id="fontSizeLabel">100</span>%)
            </label>
            <input type="range" id="a11yFontSize" min="80" max="140" value="100" step="5"
                   aria-valuemin="80" aria-valuemax="140" aria-valuenow="100">
        </div>

        <hr class="a11y-divider">

        <!-- Fonte para Dislexia -->
        <div class="a11y-row">
            <label class="a11y-label" for="a11yDyslexia">
                <span aria-hidden="true">🔤</span> Fonte para Dislexia
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
                <span aria-hidden="true">🎨</span> Filtro de Daltonismo
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
                <span aria-hidden="true">◑</span> Alto Contraste
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
                <span aria-hidden="true">👁</span> Tons de Cinza
            </label>
            <label class="toggle">
                <input type="checkbox" id="a11yGrayscale">
                <span class="toggle-track"></span>
            </label>
        </div>

    </div><!-- /.a11y-body -->

</div><!-- /#a11yPanel -->


<!-- ============================================================
     FABs FLUTUANTES
     (para páginas que usam o padrão header/footer include)
     Páginas com layout próprio já incluem seus próprios FABs.
     ============================================================ -->
<?php
// Só renderiza os FABs se a página não os definiu por conta própria.
// Páginas que têm class="fab-stack" no HTML não precisam destes.
?>
<div class="fab-stack" id="fabStackGlobal">
    <button class="fab fab-ai"   id="btnChat" title="Assistente IA"  aria-label="Abrir assistente IA">✦</button>
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Abrir painel de acessibilidade">♿</button>
</div>

<?php
// Mostra a inicial do usuário logado (jovem ou empresa)
$_nome_usuario = $_SESSION['jovem_nome']   ??
                 $_SESSION['empresa_nome'] ??
                 null;
if ($_nome_usuario): ?>
<?php endif; ?>


<!-- ============================================================
     JAVASCRIPT GLOBAL
     ============================================================ -->
<script>
(function () {
    'use strict';

    const html = document.documentElement;

    /* ── Tema (dark / light) ───────────────────────────────────── */
    function applyTheme(t) {
        html.setAttribute('data-theme', t);
        document.querySelectorAll('.btn-theme, #btnTheme').forEach(btn => {
            btn.textContent = t === 'light' ? '☀️' : '🌙';
        });
    }

    applyTheme(localStorage.getItem('jc-theme') || 'dark');

    document.querySelectorAll('.btn-theme, #btnTheme').forEach(btn => {
        btn.addEventListener('click', () => {
            const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            localStorage.setItem('jc-theme', next);
        });
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
        if (open) {
            a11yPanel?.classList.remove('is-open');
            a11yPanel?.setAttribute('aria-hidden', 'true');
            chatInput?.focus();
        }
    }

    btnChat?.addEventListener('click',  () => toggleChat());
    chatClose?.addEventListener('click', () => toggleChat(false));

    function appendMsg(texto, isUser = false) {
        const div = document.createElement('div');
        div.className = 'chat-msg';
        if (isUser) {
            div.style.cssText = 'background:var(--accent-subtle);margin-left:20%;';
        }
        div.textContent = texto;
        chatBody?.appendChild(div);
        if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
        return div;
    }

    async function enviarMensagem() {
        const texto = chatInput?.value.trim();
        if (!texto) return;

        appendMsg(texto, true);
        if (chatInput) chatInput.value = '';

        const typing = appendMsg('...');

        try {
            // Chama o proxy PHP que faz a requisição server-side ao serviço de IA
            // Crie includes/chat_ajax.php para integrar com a API desejada
            const res = await fetch('../includes/chat_ajax.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ mensagem: texto })
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json();
            typing.textContent = data.resposta ?? 'Não consegui responder. Tente novamente.';

        } catch {
            typing.textContent = 'Assistente temporariamente indisponível.';
        }

        if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
    }

    chatSend?.addEventListener('click', enviarMensagem);
    chatInput?.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) enviarMensagem();
    });


    /* ── Painel de Acessibilidade ──────────────────────────────── */
    function toggleA11y(force) {
        const open = force !== undefined ? force : !a11yPanel?.classList.contains('is-open');
        a11yPanel?.classList.toggle('is-open', open);
        a11yPanel?.setAttribute('aria-hidden', String(!open));
        if (open) {
            chatWidget?.classList.remove('is-open');
            chatWidget?.setAttribute('aria-hidden', 'true');
        }
    }

    btnA11y?.addEventListener('click', () => toggleA11y());

    // Fechar ao clicar fora
    document.addEventListener('click', e => {
        if (chatWidget?.classList.contains('is-open') &&
            !chatWidget.contains(e.target) && e.target !== btnChat)
            toggleChat(false);

        if (a11yPanel?.classList.contains('is-open') &&
            !a11yPanel.contains(e.target) && e.target !== btnA11y)
            toggleA11y(false);
    });

    // Fechar com Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { toggleChat(false); toggleA11y(false); }
    });


    /* ── Tamanho do texto ──────────────────────────────────────── */
    const fontRange = document.getElementById('a11yFontSize');
    const fontLabel = document.getElementById('fontSizeLabel');

    function applyFontSize(v) {
        html.style.fontSize = v + '%';
        if (fontLabel) fontLabel.textContent = v;
        if (fontRange) { fontRange.value = v; fontRange.setAttribute('aria-valuenow', v); }
    }

    fontRange?.addEventListener('input', function () {
        applyFontSize(this.value);
        localStorage.setItem('jc-font-size', this.value);
    });

    const savedFont = localStorage.getItem('jc-font-size');
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
        localStorage.setItem('jc-dyslexia', this.checked ? '1' : '0');
    });

    if (localStorage.getItem('jc-dyslexia') === '1') applyDyslexia(true);


    /* ── Filtro de Daltonismo ──────────────────────────────────── */
    const selFilter = document.getElementById('a11yColorFilter');

    const filterMap = {
        protanopia:   'grayscale(0.1) sepia(0.4) hue-rotate(-30deg)',
        deuteranopia: 'grayscale(0.1) sepia(0.3) hue-rotate(30deg)',
        tritanopia:   'grayscale(0.1) sepia(0.2) hue-rotate(180deg)',
    };

    function applyColorFilter(v) {
        html.style.filter = filterMap[v] || '';
        if (selFilter) selFilter.value = v;
    }

    selFilter?.addEventListener('change', function () {
        applyColorFilter(this.value);
        localStorage.setItem('jc-color-filter', this.value);
    });

    const savedFilter = localStorage.getItem('jc-color-filter');
    if (savedFilter) applyColorFilter(savedFilter);


    /* ── Alto Contraste ────────────────────────────────────────── */
    const chkContrast = document.getElementById('a11yContrast');

    function applyContrast(on) {
        html.toggleAttribute('data-high-contrast', on);
        if (chkContrast) chkContrast.checked = on;
    }

    chkContrast?.addEventListener('change', function () {
        applyContrast(this.checked);
        localStorage.setItem('jc-contrast', this.checked ? '1' : '0');
    });

    if (localStorage.getItem('jc-contrast') === '1') applyContrast(true);


    /* ── Tons de Cinza ─────────────────────────────────────────── */
    const chkGray = document.getElementById('a11yGrayscale');

    function applyGrayscale(on) {
        html.style.filter = on ? 'grayscale(1)' : '';
        if (chkGray) chkGray.checked = on;
    }

    chkGray?.addEventListener('change', function () {
        applyGrayscale(this.checked);
        localStorage.setItem('jc-grayscale', this.checked ? '1' : '0');
    });

    if (localStorage.getItem('jc-grayscale') === '1') applyGrayscale(true);


    /* ── Restaurar Padrões ─────────────────────────────────────── */
    document.getElementById('a11yReset')?.addEventListener('click', () => {
        const keys = ['jc-font-size','jc-dyslexia','jc-color-filter','jc-contrast','jc-grayscale'];
        keys.forEach(k => localStorage.removeItem(k));

        applyFontSize(100);
        applyDyslexia(false);
        applyColorFilter('');
        applyContrast(false);
        applyGrayscale(false);
    });

})();
</script>

</body>
</html>