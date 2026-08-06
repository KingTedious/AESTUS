<?php
/* ============================================================
   logout.php — Encerramento de sessão

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. $_SESSION['usuario_nome'] / ['usuario_id'] / ['usuario_tipo']
      → chaves que NUNCA foram escritas no projeto.
      O projeto usa empresa_id/empresa_nome e jovem_id/jovem_nome.
      O log sempre gravava "Desconhecido / Sem ID / Desconhecido".

   2. Sequência incompleta de destruição de sessão:
      só fazia session_destroy() sem session_unset() nem
      limpeza do cookie → a sessão do browser poderia persistir.
      Sequência correta:
        session_unset()      → limpa $_SESSION da memória
        invalidar cookie     → remove o cookie PHPSESSID do browser
        session_destroy()    → destrói os dados no servidor

   3. logger.php incluído sem verificar se existe.
   ============================================================ */
session_start();

// ── Captura dados antes de destruir (chaves reais do projeto) ─────────────────
$nome_usuario = $_SESSION['jovem_nome']   ?? $_SESSION['empresa_nome'] ?? 'Desconhecido';
$id_usuario   = $_SESSION['jovem_id']     ?? $_SESSION['empresa_id']   ?? 'sem-id';
$tipo_usuario = isset($_SESSION['jovem_id']) ? 'jovem' : (isset($_SESSION['empresa_id']) ? 'empresa' : 'desconhecido');

// ── Logger (opcional — não quebra se não existir) ─────────────────────────────
if (file_exists(__DIR__ . '/includes/logger.php')) {
    require_once __DIR__ . '/includes/logger.php';
    registrar_log(
        "Logout: '{$nome_usuario}' (ID: {$id_usuario} | tipo: {$tipo_usuario})",
        'INFO'
    );
}

// ── Sequência correta de destruição de sessão ─────────────────────────────────

// 1. Remove todas as variáveis da memória
session_unset();

// 2. Invalida o cookie de sessão no browser
//    (sem isso, o PHPSESSID permanece no browser e pode ser reutilizado)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,              // Data no passado → browser apaga
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destrói os dados da sessão no servidor
session_destroy();

// ── Redireciona para a página inicial ────────────────────────────────────────
header('Location: index.php');
exit;