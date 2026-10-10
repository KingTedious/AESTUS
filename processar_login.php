<?php
/* ============================================================
   processar_login.php  (raiz) — NEUTRALIZADO (HIGH-001)

   O login real acontece em:
     - jovem/processar_login.php
     - empresa/processar_login.php
   acionados pelos formularios de jovem/login.php e empresa/login.php.

   Este endpoint legado continha um fallback inseguro:
       password_verify($senha, $user['senha']) || $senha === $user['senha']
   que aceitava a PROPRIA HASH como senha e senhas em TEXTO PURO.
   Nenhum formulario atual o utiliza (verificado na auditoria).

   Para nao deixar uma rota de login insegura acessivel por URL direta,
   ele agora apenas redireciona para a pagina de login. Nao autentica nada.
   ============================================================ */
require_once __DIR__ . '/includes/session.php';
jc_session_start();

// Usuario ja logado -> dashboard correto
if (isset($_SESSION['jovem_id'])) {
    header('Location: dashboard/jovem.php');
    exit;
}
if (isset($_SESSION['empresa_id'])) {
    header('Location: dashboard/empresa.php');
    exit;
}

// Qualquer acesso (GET ou POST) -> pagina de login. NENHUMA autenticacao aqui.
header('Location: jovem/login.php');
exit;
