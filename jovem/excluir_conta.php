<?php
/* ============================================================
   jovem/excluir_conta.php  (Etapa 2)

   Exclusao da PROPRIA conta do jovem autenticado.
   Nasce protegida por: sessao + ownership + CSRF + POST.

   - Autoriza SOMENTE pela sessao ($_SESSION['jovem_id']). Nunca por
     ?jovem_id=... ou qualquer parametro do cliente.
   - Apaga apenas a propria conta (DELETE ... WHERE id = <sessao>).
   - ON DELETE CASCADE do schema remove candidaturas, favoritos,
     avaliacoes_ia, formacao_jovem, habilidades_jovem, experiencias_jovem.
   - Encerra a sessao por completo (sem volta pelo botao do navegador).
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // inicia sessao endurecida
require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['jovem_id'])) {
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

$jovem_id = intval($_SESSION['jovem_id']);

// Remove o arquivo de curriculo, se existir (o restante cai por CASCADE)
$stmt = $conn->prepare("SELECT curriculo FROM jovens WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $jovem_id);
$stmt->execute();
$dados = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Stage 3: também remove pelo nome padrão usado por uploads/curriculos.php,
// caso a coluna esteja vazia mas o arquivo exista
$arquivos = ["curriculo_{$jovem_id}.pdf"];
if (!empty($dados['curriculo'])) {
    $arquivos[] = basename($dados['curriculo']);
}
foreach (array_unique($arquivos) as $nome_arquivo) {
    $arquivo = dirname(__DIR__) . '/uploads/curriculos/' . $nome_arquivo;
    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
}

// Apaga SOMENTE a propria conta autenticada
$stmt = $conn->prepare("DELETE FROM jovens WHERE id = ?");
$stmt->bind_param("i", $jovem_id);
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
