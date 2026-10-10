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

<?php
// Assistente, painel de acessibilidade e botões flutuantes vêm de
// includes/assistente.php (o mesmo componente das páginas com HTML próprio).
include __DIR__ . '/assistente.php';
?>


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


})();
</script>

</body>
</html>