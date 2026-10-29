<?php
/* ============================================================
   includes/auth.php
   Utilitário de verificação de autenticação.

   USO (apenas em páginas protegidas — nunca em login/cadastro):
     require_once dirname(__DIR__) . "/includes/auth.php";
     verificar_autenticacao('empresa'); // ou 'jovem'

   ⚠ NÃO chamar em páginas de login ou cadastro.
     Essas páginas fazem a verificação de sessão inline e
     redirecionam para o dashboard se o usuário já estiver logado.

   CHAVES DE SESSÃO usadas pelo projeto:
     Empresa → $_SESSION['empresa_id'], $_SESSION['empresa_nome']
     Jovem   → $_SESSION['jovem_id'],   $_SESSION['jovem_nome']
   ============================================================ */

// Sessão endurecida (HttpOnly, SameSite, modo estrito) — antes era um session_start()
// cru, que ignorava essas proteções se esta fosse a primeira inclusão da página.
require_once __DIR__ . '/session.php';
jc_session_start();

// Carrega logger.php se disponível; fallback silencioso caso contrário
if (file_exists(__DIR__ . '/logger.php')) {
    require_once __DIR__ . '/logger.php';
}

if (!function_exists('registrar_log')) {
    /**
     * Fallback: grava no error_log padrão do PHP quando logger.php não existe.
     */
    function registrar_log(string $mensagem, string $nivel = 'INFO'): void {
        error_log("[JovemConnect][{$nivel}] {$mensagem}");
    }
}

/**
 * Verifica se o usuário está autenticado com o tipo correto.
 * Redireciona e encerra a execução se não autorizado.
 *
 * @param string $tipo  Tipo exigido pela página: 'empresa' ou 'jovem'
 */
function verificar_autenticacao(string $tipo): void {

    // Guarda contra tipos inválidos (evita redirects inesperados)
    if (!in_array($tipo, ['empresa', 'jovem'], true)) {
        registrar_log(
            "verificar_autenticacao() chamada com tipo inválido: '{$tipo}'.",
            'ERROR'
        );
        header('Location: ../index.php');
        exit;
    }

    $tem_empresa = isset($_SESSION['empresa_id']);
    $tem_jovem   = isset($_SESSION['jovem_id']);
    $autenticado = $tem_empresa || $tem_jovem;
    $url         = $_SERVER['REQUEST_URI'] ?? 'URL desconhecida';

    // ── Sem sessão ativa ──────────────────────────────────────────────────────
    if (!$autenticado) {
        registrar_log(
            "Acesso não autorizado a [{$url}]. Sem sessão ativa. " .
            "Redirecionado para ../{$tipo}/login.php",
            'WARNING'
        );
        $_SESSION['erro_login'] = 'Faça login para acessar esta página.';
        header("Location: ../{$tipo}/login.php");
        exit;
    }

    // ── Logado como jovem tentando acessar área de empresa ───────────────────
    if ($tipo === 'empresa' && !$tem_empresa) {
        $jovem_id = intval($_SESSION['jovem_id'] ?? 0);
        registrar_log(
            "Jovem ID {$jovem_id} tentou acessar área restrita de empresa em [{$url}].",
            'WARNING'
        );
        header('Location: ../dashboard/jovem.php');
        exit;
    }

    // ── Logado como empresa tentando acessar área de jovem ───────────────────
    if ($tipo === 'jovem' && !$tem_jovem) {
        $empresa_id = intval($_SESSION['empresa_id'] ?? 0);
        registrar_log(
            "Empresa ID {$empresa_id} tentou acessar área restrita de jovem em [{$url}].",
            'WARNING'
        );
        header('Location: ../dashboard/empresa.php');
        exit;
    }

    // Autenticado e tipo correto — continua normalmente
}