<?php
/* ============================================================
   dashboard/index.php  (Etapa 2 — CRIT-002)

   Roteador simples. Encaminha o usuario ao dashboard correto.

   ANTES: este arquivo era uma copia da landing com caminhos
   relativos de raiz, mas vive em /dashboard/. Os redirects
   ("dashboard/jovem.php") resolviam para /dashboard/dashboard/jovem.php
   e o CSS para /dashboard/Assets/... -> 404 e pagina sem estilo.
   As verificacoes (verificar_email/telefone, escolher_verificacao)
   redirecionam para ca ao concluir; agora chegam ao dashboard certo.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/session.php';
jc_session_start();

// Stage 4: repassa o aviso de conta verificada (verificacoes/*.php mandam
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
