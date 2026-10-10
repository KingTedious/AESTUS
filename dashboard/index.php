<?php
/* ============================================================
   dashboard/index.php — encaminha cada usuário ao seu painel
   (jovem → dashboard/jovem.php, empresa → dashboard/empresa.php;
   sem sessão → página inicial). As páginas de verificação de e-mail e
   telefone redirecionam para cá ao concluir.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/session.php';
jc_session_start();

// Repassa o aviso de conta verificada (verificacoes/*.php mandam
// ?verified=1 / ?phone_verified=1, mas o aviso se perdia neste redirecionamento)
$aviso = isset($_GET['verified']) ? '?verificado=email'
       : (isset($_GET['phone_verified']) ? '?verificado=telefone' : '');

if (isset($_SESSION['jovem_id'])) {
    header('Location: jovem.php' . $aviso);      // mesmo diretorio /dashboard/
    exit;
}
if (isset($_SESSION['empresa_id'])) {
    header('Location: empresa.php' . $aviso);    // mesmo diretorio /dashboard/
    exit;
}

// Sem sessao ativa -> landing publica (um nivel acima)
header('Location: ../index.php');
exit;
