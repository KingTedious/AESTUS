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
   - Validação durante o preenchimento (pré-entrega do TCC): cada campo
     mostra uma orientação curta (texto de ajuda ligado por aria-describedby)
     e é conferido enquanto a pessoa preenche:
       · valor certo → o campo fica marcado como correto na hora (ícone de
         confirmação, não só a cor);
       · valor com problema → a mensagem aparece ao sair do campo; depois
         disso, ela se atualiza a cada tecla e some quando o valor fica certo;
       · campo vazio só visitado não ganha erro antes da hora; campo opcional
         vazio nunca é marcado como errado;
       · campos dependentes (confirmação de senha, campos da formação que
         dependem do nível) são conferidos juntos.
     As regras são as MESMAS do servidor (ver jovem/cadastro.php,
     empresa/cadastro.php, includes/formacao.php e includes/validacao.php):
       data-erro-vazio="..."          mensagem do campo obrigatório vazio
       data-obrigatorio-visivel       obrigatório só quando está visível
       data-digitos="11"              quantidade exata de números (CPF, CEP, CNPJ)
       data-igual-a="id"              igual a outro campo (confirmação de senha)
       data-uf                        sigla de 2 letras
       data-regra="nome|nome_org|site|habilidades"   regras de formato do servidor
       data-erro-max="..."            data depois do limite (max) do campo
     Leitor de tela: a mensagem fica ligada ao campo (aria-describedby +
     aria-invalid) e só é anunciada quando aparece ao sair do campo ou ao
     escolher uma opção — nunca a cada tecla.
   - Sem JavaScript: todas as etapas aparecem uma embaixo da outra e o
     formulário funciona normalmente (as orientações continuam visíveis).
   - A validação de verdade continua no servidor; se ele recusar, a
     página volta aberta na etapa do primeiro erro
     (<form data-etapa-inicial="N">) com a mensagem perto do campo. O aviso
     do servidor fica no campo até o valor mudar.

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

    /**
     * Atributos de acessibilidade do campo: aria-describedby (orientação e/ou
     * erro do servidor) e aria-invalid quando o servidor recusou o valor.
     * @param string $ajuda  id(s) do texto de orientação do campo, separados por espaço
     *                       (num atributo só: dois aria-describedby no mesmo campo
     *                       fazem o navegador ignorar o segundo)
     */
    function jc_campo_aria(array $erros, string $campo, string $ajuda = ''): string {
        $ids = trim($ajuda);
        if (isset($erros[$campo])) {
            $ids = trim($ids . ' erro-' . $campo);
            return ' aria-invalid="true" aria-describedby="' . htmlspecialchars($ids) . '"';
        }
        return $ids !== '' ? ' aria-describedby="' . htmlspecialchars($ids) . '"' : '';
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
/* Formulário em etapas + validação durante o preenchimento — ver includes/etapas.php */
(function () {
    'use strict';
    // visível dentro da etapa: ignora o "hidden" da própria etapa (as outras ficam escondidas)
    const visivel = (el, secao) => {
        if (el.disabled || el.type === 'hidden') return false;
        const oculto = el.closest('[hidden]');
        return !oculto || oculto === secao;
    };
    // expressões com classes Unicode (\p{L}) montadas em tempo de execução: se o navegador
    // não suportar, só aquela regra deixa de ser conferida aqui (o servidor confere sempre)
    const re = (fonte, flags) => { try { return new RegExp(fonte, flags); } catch (e) { return null; } };
    const RE = {
        nome:      re("^[\\p{L}\\p{M}][\\p{L}\\p{M}\\p{N}' \\u2019.\\-]*$", 'u'),
        letra:     re('\\p{L}', 'gu'),
        orgInicio: re('^[\\p{L}\\p{N}"\'(]', 'u'),
        orgLetra:  re('[\\p{L}\\p{N}]', 'gu'),
    };
    const conta = (r, v) => (r ? (v.match(r) || []).length : 99);

    /* Regras de formato do servidor (o servidor continua decidindo; aqui é orientação) */
    const REGRAS = {
        // includes/validacao.php, tipo "nome" (nome do jovem)
        nome(v) {
            if ([...v].length < 2) return 'Use pelo menos 2 caracteres.';
            if ((RE.nome && !RE.nome.test(v)) || conta(RE.letra, v) < 2)
                return 'Use letras (acentos são aceitos), espaços, apóstrofo, hífen ou ponto.';
            return '';
        },
        // includes/validacao.php, tipo "nome_org" (nome da empresa)
        nome_org(v) {
            if ([...v].length < 2) return 'Use pelo menos 2 caracteres.';
            if (/[<>{}\[\]\\|`]/.test(v) || (RE.orgInicio && !RE.orgInicio.test(v)) || conta(RE.orgLetra, v) < 2)
                return 'Comece com uma letra ou número e não use os sinais < > { } [ ] \\ | `.';
            return '';
        },
        // includes/validacao.php, jc_url_valida(): sem "https://" o servidor completa
        site(v) {
            const u = /^[a-z][a-z0-9+.\-]*:/i.test(v) ? v : 'https://' + v;
            let ok = false;
            try {
                const p = new URL(u);
                ok = (p.protocol === 'http:' || p.protocol === 'https:') && p.hostname.includes('.') && !/\s/.test(u);
            } catch (e) { ok = false; }
            return ok ? '' : 'Informe um endereço de site válido (ex.: www.suaempresa.com.br).';
        },
        // jovem/cadastro.php: até 15 habilidades, sem contar repetidas
        habilidades(v) {
            const itens = new Set(v.split(/[,;\n]+/).map(s => s.trim().slice(0, 80).toLowerCase()).filter(Boolean));
            return itens.size > 15
                ? `Informe no máximo 15 habilidades (agora são ${itens.size}). Você pode adicionar outras depois, no perfil.`
                : '';
        },
    };

    function valorDe(el) {
        if (el.type === 'checkbox') return el.checked ? '1' : '';
        if (el.type === 'radio') {
            const marcado = el.form.querySelector(`input[name="${CSS.escape(el.name)}"]:checked`);
            return marcado ? marcado.value : '';
        }
        return (el.value || '').trim();
    }
    const obrigatorio = el => el.required || el.hasAttribute('data-obrigatorio-visivel');
    // campos que têm alguma regra conferida aqui (os outros, livres e opcionais, ficam neutros)
    const temRegra = el => obrigatorio(el) || el.type === 'email' || el.type === 'date' || el.minLength > 0
        || !!(el.dataset.digitos || el.dataset.igualA || el.dataset.regra) || el.dataset.uf !== undefined;

    /** Devolve a mensagem de erro do campo, ou '' se estiver ok. */
    function validar(el) {
        const v = valorDe(el);
        if (el.validity && el.validity.badInput) return el.dataset.erroFormato || 'Data incompleta ou inválida.';
        if (obrigatorio(el) && !v) {
            if (el.dataset.erroVazio) return el.dataset.erroVazio;
            if (el.type === 'checkbox') return 'Marque esta opção para continuar.';
            return el.tagName === 'SELECT' ? 'Escolha uma opção.' : 'Preencha este campo.';
        }
        if (!v) return '';
        if (el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Digite um e-mail válido (ex.: nome@dominio.com).';
        if (el.minLength > 0 && v.length < el.minLength) {
            const falta = el.minLength - v.length;
            return `Use pelo menos ${el.minLength} caracteres (falta${falta > 1 ? 'm' : ''} ${falta}).`;
        }
        if (el.dataset.digitos && v.replace(/\D/g, '').length !== Number(el.dataset.digitos))
            return el.dataset.erroDigitos || `Digite os ${el.dataset.digitos} números.`;
        if (el.dataset.igualA) {
            const outro = document.getElementById(el.dataset.igualA);
            if (outro && outro.value !== el.value) return el.dataset.erroIgual || 'Os valores não coincidem.';
        }
        if (el.dataset.uf !== undefined && !/^[A-Za-z]{2}$/.test(v)) return 'Use a sigla do estado com 2 letras (ex.: SP).';
        if (el.type === 'date' && el.max && v > el.max) return el.dataset.erroMax || 'A data não pode ser no futuro.';
        const regra = REGRAS[el.dataset.regra];
        return regra ? regra(v) : '';
    }

    /* ── Mensagens ligadas ao campo ─────────────────────────────────────── */
    function lugarDaMensagem(el) {
        return el.closest('.etapa-aceite') || el.closest('.form-group') || el.closest('.form-check') || el.parentElement;
    }
    function porDescricao(el, id) {
        const desc = new Set((el.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
        desc.add(id);
        el.setAttribute('aria-describedby', [...desc].join(' '));
    }
    function tirarDescricao(el, id) {
        const desc = (el.getAttribute('aria-describedby') || '').split(' ').filter(x => x && x !== id);
        desc.length ? el.setAttribute('aria-describedby', desc.join(' ')) : el.removeAttribute('aria-describedby');
    }
    /** Aviso do servidor ainda ligado ao campo (vale até o valor mudar). */
    function erroServidor(el) {
        const p = el.name ? document.getElementById('erro-' + el.name) : null;
        return p && p.hasAttribute('data-erro-servidor') ? p : null;
    }
    function tirarErroServidor(el) {
        const p = erroServidor(el);
        if (p) { tirarDescricao(el, p.id); p.remove(); }
        delete el.dataset.jcValorServidor;
    }
    function mostrarErro(el, msg) {
        const id = 'erro-js-' + (el.id || el.name);
        let p = document.getElementById(id);
        if (!msg) {
            p?.remove();
            tirarDescricao(el, id);
            if (!erroServidor(el)) el.removeAttribute('aria-invalid');
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
        porDescricao(el, id);
    }
    /** Estado visual do campo: erro (mensagem + borda), correto (ícone + borda) ou neutro. */
    function marcar(el, msg) {
        if (erroServidor(el)) { el.classList.remove('campo-ok'); return; }   // o aviso do servidor fica até o valor mudar
        mostrarErro(el, msg);
        el.classList.toggle('campo-ok', !msg && valorDe(el) !== '' && el.type !== 'checkbox' && el.type !== 'radio' && temRegra(el));
    }
    function limpar(el) {
        mostrarErro(el, '');
        el.classList.remove('campo-ok');
    }
    function campos(secao) {
        return [...secao.querySelectorAll('input, select, textarea')].filter(el => el.name && visivel(el, secao));
    }
    function rotuloDe(el) {
        if (el.type === 'checkbox') return '';
        const t = (el.labels && el.labels[0] ? el.labels[0].textContent : '').replace(/\*/g, '').replace(/\s+/g, ' ').trim();
        return t ? t + ': ' : '';
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

        // um anúncio educado por vez para leitores de tela (nunca a cada tecla)
        const vivo = document.createElement('p');
        vivo.className = 'sr-only';
        vivo.setAttribute('aria-live', 'polite');
        vivo.setAttribute('data-anuncio-validacao', '');
        form.appendChild(vivo);
        let anuncio = 0;
        const anunciar = msg => {
            clearTimeout(anuncio);
            vivo.textContent = '';
            anuncio = setTimeout(() => { vivo.textContent = msg; }, 120);
        };

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
                el.dataset.jcTocado = '1';
                const msg = validar(el);
                marcar(el, msg);
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

        /* ── Validação durante o preenchimento ─────────────────────────────── */
        const ehCampo = el => el && el.name && el.closest && el.closest('.etapa') && !el.disabled && el.type !== 'hidden'
            && ['INPUT', 'SELECT', 'TEXTAREA'].includes(el.tagName);
        // escolhas (lista, caixa de marcar) e data completa: conferidas na hora da mudança, como ao sair do campo
        const ehEscolha = el => el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'radio' || el.type === 'date';

        /**
         * Confere um campo.
         *   modo 'digitando': valor certo é confirmado na hora; problema só aparece se o
         *                     campo já foi conferido antes (saída ou "Continuar") ou já tinha erro
         *   modo 'saindo'   : ao sair do campo ou ao escolher uma opção — mostra e anuncia o problema
         */
        function conferir(el, modo) {
            if (!visivel(el, el.closest('.etapa'))) { limpar(el); return; }
            const valor = valorDe(el);
            if (el.dataset.jcValorServidor !== undefined && valor !== el.dataset.jcValorServidor) tirarErroServidor(el);
            if (erroServidor(el)) return;                        // valor recusado pelo servidor e ainda igual
            const msg = validar(el);
            const tinhaErro = el.getAttribute('aria-invalid') === 'true';
            if (!msg) { marcar(el, ''); return; }
            if (modo === 'saindo') {
                const digitouAlgo = valor || (el.validity && el.validity.badInput);   // data pela metade conta como digitada
                if (!digitouAlgo && !el.dataset.jcAlterado) return;    // só passou pelo campo vazio: sem erro antes da hora
                el.dataset.jcTocado = '1';
                marcar(el, msg);
                if (!tinhaErro) anunciar(rotuloDe(el) + msg);
            } else if (tinhaErro || el.dataset.jcTocado) {
                marcar(el, msg);                                 // atualiza a mensagem, sem anunciar
            } else {
                el.classList.remove('campo-ok');
            }
        }
        // campos que dependem deste (confirmação de senha)
        function dependentes(el) {
            if (!el.id) return;
            form.querySelectorAll(`[data-igual-a="${CSS.escape(el.id)}"]`).forEach(c => {
                if (valorDe(c) || c.getAttribute('aria-invalid') === 'true') conferir(c, 'digitando');
            });
        }
        // formação: ao trocar o nível (ou "curso técnico junto"), campos escondidos perdem
        // as marcas e os que aparecem são conferidos se já tinham sido mexidos
        function formacao(el) {
            const bloco = el.closest('[data-formacao]');
            if (!bloco) return;
            setTimeout(() => bloco.querySelectorAll('input, select, textarea').forEach(f => {
                if (f === el || f.type === 'radio') return;
                if (!visivel(f, f.closest('.etapa'))) limpar(f);
                else if (f.dataset.jcTocado || f.getAttribute('aria-invalid') === 'true') conferir(f, 'digitando');
            }), 0);   // depois do script da formação mostrar/esconder os campos
        }

        form.addEventListener('input', e => {
            const el = e.target;
            if (!ehCampo(el)) return;
            el.dataset.jcAlterado = '1';
            if (ehEscolha(el)) return;                           // escolhas e data: conferidas no "change"
            conferir(el, 'digitando');
            dependentes(el);
        });
        form.addEventListener('change', e => {
            const el = e.target;
            if (!ehCampo(el) || !ehEscolha(el)) return;
            el.dataset.jcAlterado = '1';
            conferir(el, 'saindo');
            formacao(el);
        });
        form.addEventListener('focusout', e => {
            const el = e.target;
            if (!ehCampo(el) || (ehEscolha(el) && el.type !== 'date')) return;   // data: também ao sair (pela metade)
            conferir(el, 'saindo');
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

        // avisos do servidor: guardam o valor recusado (o aviso some quando o valor muda)
        form.querySelectorAll('input, select, textarea').forEach(el => {
            if (el.name && erroServidor(el)) el.dataset.jcValorServidor = valorDe(el);
        });

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
