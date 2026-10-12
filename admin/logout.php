<?php
/* ============================================================
   admin/logout.php — sair da Central (Stage 7)
   Só por POST com token CSRF (um link ou imagem de outro site não
   consegue derrubar a sessão). Apaga a sessão no servidor e o cookie.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';

jc_admin_cabecalhos();
jc_admin_sessao_iniciar();

if (jc_admin_post()) {
    if (!jc_admin_csrf_valido()) {
        jc_admin_redirecionar('index.php');
    }
    $id = intval($_SESSION['adm_id'] ?? 0);
    if ($id > 0 && jc_stage7_disponivel($conn)) {
        jc_admin_auditar($conn, $id, 'logout', 'administrador', $id, 'sucesso');
    }
    jc_admin_sessao_destruir();
    jc_admin_redirecionar('login.php', ['motivo' => 'logout']);
}

// GET: nada muda; só oferece o botão
if (empty($_SESSION['adm_id'])) {
    jc_admin_redirecionar('login.php');
}
jc_admin_layout_abrir_simples('Sair');
echo '<h1 class="adm-titulo">Sair da Central?</h1>'
   . '<form method="post" action="logout.php">' . jc_admin_csrf_campo()
   . '<button type="submit" class="adm-btn">' . jc_admin_icone('log-out') . ' Sair agora</button></form>'
   . '<p class="adm-mt"><a href="index.php">Voltar ao painel</a></p>';
jc_admin_layout_fechar_simples();
