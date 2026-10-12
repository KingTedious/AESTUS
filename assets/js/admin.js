/* ============================================================
   assets/js/admin.js — Central administrativa (Stage 7)

   Só melhorias progressivas: tudo funciona sem JavaScript.
   - contador de caracteres nos campos com data-contador;
   - evita envio duplo dos formulários;
   - análise complementar por IA sem recarregar a página
     (sem JavaScript, o formulário abre uma página com o resultado).
   Nada de alert()/confirm(): as confirmações são campos do formulário.
   ============================================================ */
(function () {
  'use strict';

  // Contador de caracteres (anunciado de forma discreta para leitores de tela)
  document.querySelectorAll('textarea[data-contador], input[data-contador]').forEach(function (campo) {
    var max = parseInt(campo.getAttribute('maxlength') || '0', 10);
    if (!max) { return; }
    var p = document.createElement('p');
    p.className = 'adm-contagem';
    p.setAttribute('aria-live', 'polite');
    p.id = (campo.id || 'campo') + '-contagem';
    campo.insertAdjacentElement('afterend', p);
    var atualizar = function () {
      var n = Array.from(campo.value).length;
      p.textContent = n + ' de ' + max + ' caracteres';
    };
    campo.addEventListener('input', atualizar);
    atualizar();
  });

  // Evita envio duplo (o servidor também recusa decisões repetidas)
  document.querySelectorAll('form[method="post"]').forEach(function (form) {
    if (form.hasAttribute('data-ia')) { return; }
    form.addEventListener('submit', function () {
      if (!form.checkValidity()) { return; }
      form.querySelectorAll('button[type="submit"]').forEach(function (b) {
        b.disabled = true;
        b.setAttribute('aria-busy', 'true');
      });
    });
  });

  // Análise complementar por IA (só orientação: não altera nada)
  document.querySelectorAll('form[data-ia]').forEach(function (form) {
    var saida = document.getElementById(form.getAttribute('data-ia'));
    if (!saida) { return; }
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var botao = form.querySelector('button[type="submit"]');
      botao.disabled = true;
      botao.classList.add('adm-carregando');
      saida.hidden = false;
      saida.textContent = 'Consultando a análise por IA...';
      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
        credentials: 'same-origin'
      }).then(function (r) {
        return r.json().catch(function () { return { ok: false, erro: 'Resposta inválida do servidor.' }; });
      }).then(function (d) {
        saida.textContent = '';
        var caixa = document.createElement('div');
        var corpo = document.createElement('div');
        caixa.appendChild(corpo);
        var titulo = document.createElement('p');
        var forte = document.createElement('strong');
        if (!d || !d.ok) {
          caixa.className = 'adm-alerta adm-alerta-aviso';
          forte.textContent = 'Análise por IA indisponível. ';
          titulo.appendChild(forte);
          titulo.appendChild(document.createTextNode((d && d.erro) || 'Tente mais tarde.'));
          corpo.appendChild(titulo);
        } else {
          caixa.className = 'adm-alerta adm-alerta-info';
          forte.textContent = 'Orientação da IA (não altera nada): ';
          titulo.appendChild(forte);
          titulo.appendChild(document.createTextNode('risco ' + d.risco_rotulo + '.'));
          corpo.appendChild(titulo);
          if (d.resumo) {
            var r = document.createElement('p');
            r.textContent = d.resumo;
            corpo.appendChild(r);
          }
          if (d.categorias && d.categorias.length) {
            var ul = document.createElement('ul');
            d.categorias.forEach(function (c) {
              var li = document.createElement('li');
              li.textContent = c;
              ul.appendChild(li);
            });
            corpo.appendChild(ul);
          }
          if (d.do_cache) {
            var c = document.createElement('p');
            c.className = 'adm-ajuda';
            c.textContent = 'Resposta reaproveitada de uma consulta recente sobre o mesmo texto (até 1 hora).';
            corpo.appendChild(c);
          }
        }
        if (d && d.regras_resumo !== undefined) {
          var regras = document.createElement('p');
          regras.className = 'adm-ajuda';
          regras.textContent = 'Regras automáticas no texto atual: ' + (d.regras_resumo || 'nenhuma');
          corpo.appendChild(regras);
        }
        saida.appendChild(caixa);
        saida.focus();
      }).catch(function () {
        saida.textContent = 'Não foi possível consultar a análise por IA agora.';
      }).finally(function () {
        botao.disabled = false;
        botao.classList.remove('adm-carregando');
      });
    });
  });
})();
