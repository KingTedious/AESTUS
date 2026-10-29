<?php
/* ============================================================
   includes/session.php  (MED-001)

   Inicializacao de sessao endurecida, compativel com XAMPP/HTTP local.
   - HttpOnly: cookie de sessao inacessivel ao JavaScript (mitiga roubo via XSS)
   - SameSite=Lax: mitiga CSRF cross-site sem quebrar navegacao normal/PRG
   - use_strict_mode: rejeita IDs de sessao nao gerados pelo servidor (fixation)
   - Secure: so quando a requisicao for HTTPS (nunca forca em HTTP local)

   Uso: substitui chamadas diretas a session_start().
        require_once <caminho>/includes/session.php; jc_session_start();
   (includes/csrf.php ja chama jc_session_start() automaticamente.)

   Correcoes de seguranca (RELATORIO_CORRECAO_SEGURANCA): jc_session_start()
   tambem envia os cabecalhos de seguranca; jc_sessao_entrar() grava a
   identidade no login/cadastro (limpa o outro tipo, renova o token CSRF e
   guarda a referencia da senha usada por includes/conta_status.php).
   ============================================================ */

/**
 * Cabecalhos de seguranca das paginas do site publico (correcao de
 * seguranca 4.5). A Central administrativa tem os seus, mais rigidos
 * (includes/admin/sessao.php). Sem CSP de scripts: as paginas usam
 * JavaScript embutido, e uma politica estrita as quebraria; estes
 * cabecalhos nao mudam nada que a pagina faca.
 *   - nosniff: o navegador nao "adivinha" o tipo do arquivo;
 *   - SAMEORIGIN / frame-ancestors: outro site nao abre o JovemConnect
 *     dentro de um quadro (clickjacking);
 *   - Referrer-Policy: outros sites nao recebem o endereco completo;
 *   - base-uri/form-action: injecao de <base> ou de formulario para fora.
 */
// .env + erros fora da página (includes/env.php) já no começo de toda página com sessão
require_once __DIR__ . '/env.php';

if (!function_exists('jc_cabecalhos_seguranca')) {
    function jc_cabecalhos_seguranca(): void {
        static $enviados = false;
        if ($enviados || PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        $enviados = true;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    }
}

if (!function_exists('jc_session_start')) {
    function jc_session_start(): void {
        jc_cabecalhos_seguranca();
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

/**
 * Referencia da senha guardada na sessao no login (correcao SEG-09).
 * E um resumo (SHA-256) do hash da senha — nunca a senha nem o hash.
 * includes/conta_status.php compara a cada pagina: se a senha da conta
 * mudou (ex.: recuperacao de senha), as sessoes abertas antes terminam.
 */
if (!function_exists('jc_sessao_ref_senha')) {
    function jc_sessao_ref_senha(string $hash_senha): string {
        return substr(hash('sha256', 'jovemconnect-sessao|' . $hash_senha), 0, 32);
    }
}

/**
 * Grava a identidade de quem acabou de entrar (login ou cadastro):
 *   - apaga a identidade do OUTRO tipo (SEG-13: antes a sessao podia ficar
 *     com jovem e empresa ao mesmo tempo);
 *   - guarda a referencia da senha (SEG-09);
 *   - troca o ID da sessao (session fixation) e o token CSRF (SEG-09:
 *     o token de antes do login deixa de valer).
 */
if (!function_exists('jc_sessao_entrar')) {
    function jc_sessao_entrar(string $tipo, int $id, string $nome, string $hash_senha): void {
        $outro = ($tipo === 'jovem') ? 'empresa' : 'jovem';
        unset($_SESSION[$outro . '_id'], $_SESSION[$outro . '_nome']);
        $_SESSION[$tipo . '_id']   = $id;
        $_SESSION[$tipo . '_nome'] = $nome;
        $_SESSION['jc_senha_ref']  = jc_sessao_ref_senha($hash_senha);
        jc_session_regenerate();
        unset($_SESSION['csrf_token']);
    }
}
