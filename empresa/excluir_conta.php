<?php
/* ============================================================
   empresa/excluir_conta.php  (Etapa 2)

   Exclusao da PROPRIA conta da empresa autenticada.
   Protegida por: sessao + ownership + CSRF + POST.

   - Autoriza SOMENTE pela sessao ($_SESSION['empresa_id']).
   - Apaga apenas a propria empresa (DELETE ... WHERE id = <sessao>).
   - ON DELETE CASCADE remove vagas e, em cascata, candidaturas,
     favoritos, requisitos_vaga, beneficios_vaga e avaliacoes_ia.
   - Encerra a sessao por completo.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // inicia sessao endurecida
require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['empresa_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php');
    exit;
}
csrf_require();

// Stage 3: exige a confirmação enviada pelo modal ("Excluir Permanentemente")
if (($_POST['confirmar'] ?? '') !== '1') {
    header('Location: perfil.php');
    exit;
}

$empresa_id = intval($_SESSION['empresa_id']);

// Apaga SOMENTE a propria conta autenticada
$stmt = $conn->prepare("DELETE FROM empresas WHERE id = ?");
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$stmt->close();

// Encerra a sessao por completo
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: ../index.php?conta=excluida');
exit;
