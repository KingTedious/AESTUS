<?php
/* ============================================================
   includes/etapas.php — formulário em etapas (cadastros)

   Como funciona:
   - O formulário continua sendo UM <form> só, com todos os campos.
     Cada etapa é um <section class="etapa" data-etapa data-titulo="...">.
     Por isso os dados nunca se perdem entre as etapas: nada é enviado
     ou apagado ao clicar em "Voltar"/"Continuar".
   - Com JavaScript: mostra uma etapa por vez, com "Etapa X de N", barra
     de progresso, botões Voltar/Continuar, validação dos campos da etapa
     antes de avançar e um resumo para revisar no fim
     (<div data-etapas-revisao>; os campos com data-revisao="Rótulo"
     entram no resumo).
   - Sem JavaScript: todas as etapas aparecem uma embaixo da outra e o
     formulário funciona normalmente.
   - A validação de verdade continua no servidor; se ele recusar, a
     página volta aberta na etapa do primeiro erro
     (<form data-etapa-inicial="N">) com a mensagem perto do campo.

   Uso numa página:
     <?php require_once __DIR__ . '/../includes/etapas.php'; ?>
     <form data-etapas data-etapa-inicial="<?= $etapa_erro ?>"> ... </form>
     <?= jc_etapas_script() ?>
   ============================================================ */

if (!function_exists('jc_campo_erro')) {

    /**
     * Mensagem de erro de um campo, logo abaixo dele.
     * @param array  $erros  erros do servidor (chave = nome do campo)
     * @param string $campo  nome do campo (ex.: 'email')
     */
    function jc_campo_erro(array $erros, string $campo): string {
        if (!isset($erros[$campo]) || !is_string($erros[$campo])) {
            return '';
        }
        return '<p class="campo-erro" id="erro-' . htmlspecialchars($campo) . '" data-erro-servidor>'
             . '<span class="i i-alert-triangle" aria-hidden="true"></span> '
             . htmlspecialchars($erros[$campo]) . '</p>';
    }

    /** Atributos para o campo com erro do servidor (aria-invalid + descrição). */
    function jc_campo_aria(array $erros, string $campo): string {
        return isset($erros[$campo])
            ? ' aria-invalid="true" aria-describedby="erro-' . htmlspecialchars($campo) . '"'
            : '';
    }

    /** Número da etapa do primeiro erro (1 se não houver erro). */
    function jc_etapa_do_erro(array $erros, array $mapa): int {
        foreach (array_keys($erros) as $campo) {
            if (isset($mapa[$campo])) {
                return $mapa[$campo];
            }
            if (is_int($campo) && isset($mapa['*'])) {   // erros sem campo (ex.: formação)
                return $mapa['*'];
            }
        }
        return 1;
    }

    /** Script do componente (imprime só uma vez por página). */
    function jc_etapas_script(): string {
        static $impresso = false;
        if ($impresso) {
            return '';
        }
        $impresso = true;
        return <<<'HTML'
<script>
/* Formulário em etapas — ver includes/etapas.php */
(function () {
    'use strict';
    // visível dentro da etapa: ignora o "hidden" da própria etapa (as outras ficam escondidas)
    const visivel = (el, secao) => {
        if (el.disabled || el.type === 'hidden') return false;
        const oculto = el.closest('[hidden]');
        return !oculto || oculto === secao;
    };

    function mensagemDoCampo(el) {
        if (el.dataset.erroVazio && !valorDe(el)) return el.dataset.erroVazio;
        return null;
    }
    function valorDe(el) {
        if (el.type === 'checkbox') return el.checked ? '1' : '';
        if (el.type === 'radio') {
            const marcado = el.form.querySelector(`input[name="${CSS.escape(el.name)}"]:checked`);
            return marcado ? marcado.value : '';
        }
        return (el.value || '').trim();
    }
    /** Devolve a mensagem de erro do campo, ou '' se estiver ok. */
    function validar(el) {
        const v = valorDe(el);
        if (el.required && !v) {
            return mensagemDoCampo(el) || (el.type === 'checkbox' ? 'Marque esta opção para continuar.' : 'Preencha este campo.');
        }
        if (!v) return '';
        if (el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Digite um e-mail válido (ex.: nome@dominio.com).';
        if (el.minLength > 0 && v.length < el.minLength) return `Use pelo menos ${el.minLength} caracteres.`;
        if (el.dataset.digitos && v.replace(/\D/g, '').length !== Number(el.dataset.digitos))
            return el.dataset.erroDigitos || `Digite os ${el.dataset.digitos} números.`;
        if (el.dataset.igualA) {
            const outro = document.getElementById(el.dataset.igualA);
            if (outro && outro.value !== el.value) return el.dataset.erroIgual || 'Os valores não coincidem.';
        }
        if (el.dataset.uf !== undefined && !/^[A-Za-z]{2}$/.test(v)) return 'Use a sigla do estado com 2 letras (ex.: SP).';
        return '';
    }
    function lugarDaMensagem(el) {
        return el.closest('.etapa-aceite') || el.closest('.form-group') || el.closest('.form-check') || el.parentElement;
    }
    function mostrarErro(el, msg) {
        const id = 'erro-js-' + (el.id || el.name);
        let p = document.getElementById(id);
        if (!msg) {
            p?.remove();
            el.removeAttribute('aria-invalid');
            const desc = (el.getAttribute('aria-describedby') || '').split(' ').filter(x => x && x !== id);
            desc.length ? el.setAttribute('aria-describedby', desc.join(' ')) : el.removeAttribute('aria-describedby');
            return;
        }
        if (!p) {
            p = document.createElement('p');
            p.className = 'campo-erro';
            p.id = id;
            lugarDaMensagem(el).appendChild(p);
        }
        p.replaceChildren(window.jcIcone ? jcIcone('alert-triangle') : '', ' ' + msg);
        el.setAttribute('aria-invalid', 'true');
        const desc = new Set((el.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
        desc.add(id);
        el.setAttribute('aria-describedby', [...desc].join(' '));
    }
    function campos(secao) {
        return [...secao.querySelectorAll('input, select, textarea')].filter(el => el.name && visivel(el, secao));
    }

    function iniciar(form) {
        const etapas = [...form.querySelectorAll('.etapa[data-etapa]')];
        if (etapas.length < 2) return;
        form.classList.add('etapas-ativo');
        let atual = Math.min(Math.max(Number(form.dataset.etapaInicial) || 1, 1), etapas.length) - 1;

        // cabeçalho: "Etapa X de N", barra e lista das etapas
        const topo = document.createElement('div');
        topo.className = 'etapas-topo';
        const contador = document.createElement('p');
        contador.className = 'etapas-contador';
        contador.setAttribute('aria-live', 'polite');
        const barra = document.createElement('div');
        barra.className = 'etapas-barra';
        barra.setAttribute('role', 'progressbar');
        barra.setAttribute('aria-valuemin', '1');
        barra.setAttribute('aria-valuemax', String(etapas.length));
        barra.setAttribute('aria-label', 'Progresso do cadastro');
        const preenchimento = document.createElement('span');
        barra.appendChild(preenchimento);
        const lista = document.createElement('ol');
        lista.className = 'etapas-lista';
        etapas.forEach((s, i) => {
            const li = document.createElement('li');
            li.textContent = s.dataset.titulo || `Etapa ${i + 1}`;
            lista.appendChild(li);
        });
        topo.append(contador, barra, lista);
        form.insertBefore(topo, etapas[0]);

        // navegação no fim de cada etapa
        etapas.forEach((s, i) => {
            const nav = document.createElement('div');
            nav.className = 'etapas-nav';
            if (i > 0) {
                const voltar = document.createElement('button');
                voltar.type = 'button';
                voltar.className = 'btn btn-secondary';
                voltar.append(window.jcIcone ? jcIcone('arrow-left') : '', ' Voltar');
                voltar.addEventListener('click', () => ir(i - 1, true));
                nav.appendChild(voltar);
            }
            if (i < etapas.length - 1) {
                const seguir = document.createElement('button');
                seguir.type = 'button';
                seguir.className = 'btn btn-primary';
                seguir.append('Continuar ', window.jcIcone ? jcIcone('arrow-right') : '');
                seguir.addEventListener('click', () => { if (validarEtapa(i)) ir(i + 1, true); });
                nav.appendChild(seguir);
            }
            const fim = s.querySelector('[data-etapas-fim]');
            fim ? s.insertBefore(nav, fim) : s.appendChild(nav);
        });

        function validarEtapa(i, focar = true) {
            let primeiro = null;
            campos(etapas[i]).forEach(el => {
                const msg = validar(el);
                mostrarErro(el, msg);
                if (msg && !primeiro) primeiro = el;
            });
            if (primeiro && focar) primeiro.focus();
            return !primeiro;
        }
        function montarRevisao() {
            form.querySelectorAll('[data-etapas-revisao]').forEach(caixa => {
                const dl = document.createElement('dl');
                dl.className = 'etapas-resumo';
                form.querySelectorAll('[data-revisao]').forEach(el => {
                    if (!visivel(el, el.closest('.etapa'))) return;
                    let v = '';
                    if (el.tagName === 'SELECT') v = el.value ? el.options[el.selectedIndex].text : '';
                    else if (el.type === 'checkbox') v = el.checked ? 'Sim' : 'Não';
                    else v = (el.value || '').trim();
                    const dt = document.createElement('dt');
                    dt.textContent = el.dataset.revisao;
                    const dd = document.createElement('dd');
                    dd.textContent = v || 'Não informado';
                    if (!v) dd.className = 'text-muted';
                    dl.append(dt, dd);
                });
                caixa.replaceChildren(dl);
            });
        }
        function ir(i, focar) {
            atual = i;
            etapas.forEach((s, k) => {
                s.hidden = k !== i;
                s.classList.toggle('etapa-atual', k === i);
            });
            [...lista.children].forEach((li, k) => {
                li.classList.toggle('feita', k < i);
                li.classList.toggle('atual', k === i);
                k === i ? li.setAttribute('aria-current', 'step') : li.removeAttribute('aria-current');
            });
            contador.textContent = `Etapa ${i + 1} de ${etapas.length}: ${etapas[i].dataset.titulo || ''}`;
            barra.setAttribute('aria-valuenow', String(i + 1));
            preenchimento.style.width = ((i + 1) / etapas.length * 100) + '%';
            if (i === etapas.length - 1) montarRevisao();
            if (focar) {
                const titulo = etapas[i].querySelector('.etapa-titulo');
                if (titulo) { titulo.setAttribute('tabindex', '-1'); titulo.focus({ preventScroll: true }); }
                topo.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            }
        }

        // erro some ao corrigir o campo
        form.addEventListener('input', e => {
            if (e.target.getAttribute('aria-invalid') === 'true' && !validar(e.target)) {
                mostrarErro(e.target, '');
                const doServidor = document.getElementById('erro-' + e.target.name);
                if (doServidor && doServidor.hasAttribute('data-erro-servidor')) doServidor.remove();
            }
        });
        // Enter num campo avança a etapa em vez de enviar o formulário pela metade
        form.addEventListener('keydown', e => {
            if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || e.target.type === 'submit') return;
            if (atual < etapas.length - 1) {
                e.preventDefault();
                if (validarEtapa(atual)) ir(atual + 1, true);
            }
        });
        // envio: confere todas as etapas; mostra a primeira com problema
        // (fase de captura: roda antes do "envio único", que só trava o botão se passar)
        form.addEventListener('submit', e => {
            for (let i = 0; i < etapas.length; i++) {
                if (!validarEtapa(i, false)) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    ir(i, false);
                    validarEtapa(i);
                    return;
                }
            }
        }, true);

        ir(atual, false);
        const comErro = etapas[atual].querySelector('[aria-invalid="true"]');
        if (comErro) comErro.focus();
    }

    document.querySelectorAll('form[data-etapas]').forEach(iniciar);
})();
</script>
HTML;
    }
}
