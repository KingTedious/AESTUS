<?php
/* ============================================================
   includes/session.php  (Etapa 2 — MED-001)

   Inicializacao de sessao endurecida, compativel com XAMPP/HTTP local.
   - HttpOnly: cookie de sessao inacessivel ao JavaScript (mitiga roubo via XSS)
   - SameSite=Lax: mitiga CSRF cross-site sem quebrar navegacao normal/PRG
   - use_strict_mode: rejeita IDs de sessao nao gerados pelo servidor (fixation)
   - Secure: so quando a requisicao for HTTPS (nunca forca em HTTP local)

   Uso: substitui chamadas diretas a session_start().
        require_once <caminho>/includes/session.php; jc_session_start();
   (includes/csrf.php ja chama jc_session_start() automaticamente.)
   ============================================================ */

if (!function_exists('jc_session_start')) {
    function jc_session_start(): void {
        if (session_status() !== PHP_SESSION_NONE) {
            return; // ja iniciada por outro include
        }
        if (PHP_SAPI !== 'cli') {
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.cookie_httponly', '1');

            $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                   || (($_SERVER['SERVER_PORT'] ?? '') === '443');

            // Define params do cookie ANTES de iniciar a sessao.
            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'secure'   => $secure,
                ]);
            } else {
                session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
            }
        }
        session_start();
    }
}

/**
 * Regenera o ID da sessao preservando os dados (chamar apos autenticar).
 * Mitiga session fixation. No-op se nao houver sessao ativa.
 */
if (!function_exists('jc_session_regenerate')) {
    function jc_session_regenerate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
