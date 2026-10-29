<?php
/* ============================================================
   logout.php — Encerramento de sessão
   ============================================================ */
require_once __DIR__ . '/includes/session.php';
jc_session_start();   // mesma sessão endurecida do resto do site (antes: session_start() cru)

// ── Captura dados antes de destruir (chaves reais do projeto) ─────────────────
$nome_usuario = $_SESSION['jovem_nome']   ?? $_SESSION['empresa_nome'] ?? 'Desconhecido';
$id_usuario   = $_SESSION['jovem_id']     ?? $_SESSION['empresa_id']   ?? 'sem-id';
$tipo_usuario = isset($_SESSION['jovem_id']) ? 'jovem' : (isset($_SESSION['empresa_id']) ? 'empresa' : 'desconhecido');

// ── Logger (opcional — não quebra se não existir) ─────────────────────────────
if (file_exists(__DIR__ . '/includes/logger.php')) {
    require_once __DIR__ . '/includes/logger.php';
    // sem o nome da pessoa: tipo e id interno bastam para auditoria
    jc_log_seguranca('logout', 'info', ['tipo' => $tipo_usuario]);
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