<?php
/* ============================================================
   includes/assistente.php  (Stage 3; ampliado na Stage 4)

   Assistente JovemConnect (respostas PREDEFINIDAS, sem IA generativa)
   + Painel de Acessibilidade + FABs flutuantes.

   Extraído de includes/footer.php para poder ser incluído também nas
   páginas com HTML próprio que mostravam os botões ✦/♿ sem nenhum
   painel nem script (cliques não faziam nada).

   Antes (footer.php) o chat chamava includes/chat_ajax.php, arquivo que
   nunca existiu → toda pergunta respondia "Assistente temporariamente
   indisponível". Agora responde localmente. Não mexe em tema (cada
   página já cuida do seu).

   Stage 4:
   - Passa a ser a ÚNICA implementação do assistente e do painel de
     acessibilidade: index.php e os dois dashboards tinham cópias próprias,
     com outras chaves no localStorage — a preferência ligada numa página
     se perdia na outra. Chaves antigas são migradas uma vez (ver LEGADO).
   - Os controles do painel passam a ter efeito: tamanho do texto (o CSS
     usa px, então mudar o font-size da raiz não fazia nada — agora o
     conteúdo é ampliado sem ampliar os painéis/botões flutuantes), alto
     contraste (não existia CSS para ele), tons de cinza + daltonismo
     juntos (um apagava o outro). "Idioma" fica sinalizado como "em breve".
   - Saudação do chat configurável por página:
       $assistente_boas_vindas = 'Texto...';   // antes do include
   - Dois comportamentos pequenos reaproveitados pelas páginas:
       · <form data-envio-unico="Enviando..."> — trava o botão depois do
         primeiro envio (evita vaga/candidatura/cadastro em dobro);
       · jcAviso('mensagem', 'erro'|'sucesso') — aviso flutuante (usa o
         .toast já existente) para erros de ações AJAX.

   Stage 5:
   - Ícones de traço (assets/css/style.css, seção 27) no lugar dos
     emojis/símbolos dos botões ✦/♿, do chat e do painel. As respostas
     predefinidas do assistente NÃO mudaram.
   - Painel de acessibilidade ganhou o botão × (como o chat).
   - Modais padronizados: <button data-jc-modal-abrir="idDoModal"> abre,
     [data-jc-modal-fechar] fecha; Esc e clique no fundo também fecham e o
     foco volta para quem abriu (ex.: Termos, Privacidade e Contato).

   Uso (antes de </body>, no nível do <body>):
     <?php include dirname(__DIR__) . '/includes/assistente.php'; ?>
   ============================================================ */
if (defined('JC_ASSISTENTE_INCLUIDO')) {
    return; // evita IDs duplicados se for incluído duas vezes
}
define('JC_ASSISTENTE_INCLUIDO', true);

$_assist_boas_vindas = (isset($assistente_boas_vindas) && is_string($assistente_boas_vindas) && $assistente_boas_vindas !== '')
    ? $assistente_boas_vindas
    : 'Bem-vindo ao JovemConnect! Aqui é onde tudo começa para conectar jovens aprendizes com empresas incríveis. Como posso ajudar você hoje?';
?>
<!-- ============================================================
     CHAT WIDGET — Assistente JovemConnect
     ============================================================ -->
<div class="chat-widget" id="chatWidget" role="dialog" aria-label="Assistente JovemConnect" aria-hidden="true">

    <div class="chat-header">
        <div class="chat-header-left">
            <span class="i i-sparkles" aria-hidden="true"></span>
            <span>Assistente JovemConnect</span>
        </div>
        <button type="button" class="chat-close" id="chatClose" aria-label="Fechar assistente"><span class="i i-x" aria-hidden="true"></span></button>
    </div>

    <div class="chat-body" id="chatBody" aria-live="polite">
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
            <!-- Stage 5: fechar pelo × (antes só pelo Esc, pelo botão flutuante ou clicando fora) -->
            <button type="button" class="a11y-fechar" id="a11yFechar" title="Fechar" aria-label="Fechar painel de acessibilidade"><span class="i i-x" aria-hidden="true"></span></button>
        </div>
    </div>

    <div class="a11y-body">

        <!-- Idioma — Stage 4: a tradução ainda não existe; o controle não finge funcionar -->
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


<!-- Stage 4: espaço no fim da página (só no celular) para os últimos
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

    /* ── Stage 4: migração das chaves antigas (index.php e dashboards) ────────
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
        div.className = 'chat-msg';
        if (isUser) {
            div.style.cssText = 'background:var(--accent-subtle);margin-left:20%;';
        }
        div.textContent = texto;            // texto puro: nunca interpreta HTML digitado
        chatBody?.appendChild(div);
        if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
        return div;
    }

    // Respostas predefinidas (sem IA generativa). Ordem = prioridade.
    // Casa PALAVRAS inteiras (sem acento): "dia" não dispara a resposta de "ia";
    // radicais com 3+ letras aceitam plural ("vagas", "empresas", "jovens").
    const RESPOSTAS = [
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

    function enviarMensagem() {
        const texto = chatInput?.value.trim();
        if (!texto) return;

        appendMsg(texto, true);
        if (chatInput) chatInput.value = '';

        const digitando = appendMsg('...');
        setTimeout(() => {
            digitando.textContent = responder(texto);
            if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
        }, 350);
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
            // Stage 4 (revisão): o painel vem ANTES do botão no HTML — sem isto, quem
            // abre pelo teclado não alcança as opções com Tab
            a11yPanel?.querySelector('.a11y-body input:not([disabled]), .a11y-body select:not([disabled])')?.focus();
        }
    }

    btnA11y?.addEventListener('click', () => toggleA11y());
    // Stage 5: botão × do painel (o foco volta para o botão que abre o painel)
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
        if (modaisAbertos.length) return;   // Stage 5: Termos/Privacidade/Contato aberto → o Esc fecha só o modal
        if (chatWidget?.classList.contains('is-open')) { toggleChat(false); btnChat?.focus(); }
        if (a11yPanel?.classList.contains('is-open'))  { toggleA11y(false); btnA11y?.focus(); }
    });


    /* ── Tamanho do texto ──────────────────────────────────────────
       Stage 4: o CSS do projeto usa px, então mudar o font-size da raiz
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
       Stage 4: os dois usam a propriedade "filter" — antes um apagava o
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


    /* ── Alto Contraste (Stage 4: regras html[data-high-contrast] no style.css) ── */
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


    /* ── Stage 4: aviso flutuante para erros/sucessos de ações AJAX ──
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
            // Stage 5: ícone de erro/sucesso (antes ✕/✓ em texto); a mensagem continua como texto puro
            const icone = document.createElement('span');
            icone.className = 'i ' + (erro ? 'i-x-circle' : 'i-check-circle');
            icone.setAttribute('aria-hidden', 'true');
            el.replaceChildren(icone, document.createTextNode(mensagem));
            el.classList.add('toast-visible');
            el._timer = setTimeout(() => el.classList.remove('toast-visible'), erro ? 7000 : 4000);
        }, 60);
    };


    /* ── Stage 5: modais padronizados ────────────────────────────────
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

    // Stage 5: cria um ícone decorativo para mensagens montadas pelo JavaScript
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


    /* ── Stage 4: modal aberto (role="dialog" aria-modal="true") prende o Tab ──
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


    /* ── Stage 4: formulário com envio único ──────────────────────
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
