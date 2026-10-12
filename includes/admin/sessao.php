<?php
/* ============================================================
   includes/admin/sessao.php — sessão, CSRF e cabeçalhos da Central
   administrativa (Stage 7)

   A sessão administrativa é SEPARADA da sessão de jovens e empresas:
   - cookie próprio (JCADMIN), válido só no caminho da pasta admin/;
   - HttpOnly, SameSite=Strict e Secure quando a conexão é HTTPS;
   - modo estrito (IDs não gerados pelo servidor são recusados);
   - ID regenerado no login;
   - expira por inatividade (JC_ADMIN_OCIOSO_SEG, padrão 15 min) e por
     tempo total (JC_ADMIN_MAX_SEG, padrão 8 h), contados no servidor;
   - presa ao navegador que fez o login (hash do User-Agent);
   - derrubada quando a conta administrativa muda de "versão de sessão"
     (troca de senha, desativação, "encerrar outras sessões").

   Os valores guardados na sessão são só identificadores e marcas de
   tempo — nunca senha, hash ou segredo do autenticador.
   ============================================================ */

if (!function_exists('jc_admin_sessao_iniciar')) {

    /** Lê um número de segundos do ambiente, dentro de limites seguros. */
    function jc_admin_config_seg(string $nome, int $padrao, int $min, int $max): int {
        $v = getenv($nome);
        $n = ($v === false || $v === '' || !ctype_digit(trim((string) $v))) ? $padrao : intval($v);
        return max($min, min($max, $n));
    }

    function jc_admin_ocioso_seg(): int {
        return jc_admin_config_seg('JC_ADMIN_OCIOSO_SEG', 900, 120, 3600);
    }

    function jc_admin_max_seg(): int {
        return jc_admin_config_seg('JC_ADMIN_MAX_SEG', 28800, 900, 43200);
    }

    /** Janela da reautenticação para operações críticas (segundos). */
    function jc_admin_reauth_seg(): int {
        return 300;
    }

    function jc_admin_https(): bool {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    }

    /** Conexão local (localhost)? Usado só para o aviso de "sem HTTPS". */
    function jc_admin_local(): bool {
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
    }

    /** Caminho do cookie: a pasta admin/ do projeto (ex.: /JovemConnect/admin/). */
    function jc_admin_cookie_path(): string {
        $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php')));
        return rtrim($dir, '/') . '/';
    }

    /** Hash do navegador (a sessão só vale no navegador que fez o login). */
    function jc_admin_hash_navegador(): string {
        return hash('sha256', 'jc-admin-ua|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    /** Inicia (ou retoma) a sessão administrativa. Não valida o login. */
    function jc_admin_sessao_iniciar(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() === 'JCADMIN') {
                return;
            }
            // nunca misturar com a sessão pública
            session_write_close();
        }
        if (PHP_SAPI !== 'cli') {
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.use_trans_sid', '0');
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.gc_maxlifetime', (string) jc_admin_max_seg());
            session_name('JCADMIN');
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => jc_admin_cookie_path(),
                'httponly' => true,
                'samesite' => 'Strict',
                'secure'   => jc_admin_https(),
            ]);
        }
        session_start();
    }

    /** Apaga a sessão administrativa e o cookie. */
    function jc_admin_sessao_destruir(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            jc_admin_sessao_iniciar();
        }
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie('JCADMIN', '', [
                'expires'  => time() - 3600,
                'path'     => jc_admin_cookie_path(),
                'httponly' => true,
                'samesite' => 'Strict',
                'secure'   => jc_admin_https(),
            ]);
        }
        @session_destroy();
    }

    /**
     * Grava a identidade administrativa na sessão depois de um login válido.
     * O ID da sessão é trocado (impede fixação de sessão).
     */
    function jc_admin_sessao_entrar(array $admin): void {
        jc_admin_sessao_iniciar();
        session_regenerate_id(true);
        $agora = time();
        $_SESSION = [
            'adm_id'         => intval($admin['id']),
            'adm_versao'     => intval($admin['sessao_versao']),
            'adm_inicio'     => $agora,
            'adm_ultimo'     => $agora,
            'adm_ua'         => jc_admin_hash_navegador(),
            'adm_csrf'       => bin2hex(random_bytes(32)),
            'adm_reauth_ate' => $agora + jc_admin_reauth_seg(),   // acabou de digitar a senha
        ];
    }

    /**
     * Confere os prazos e o navegador da sessão atual.
     * @return string|null  null = válida; senão o código do motivo
     */
    function jc_admin_sessao_problema(): ?string {
        if (empty($_SESSION['adm_id'])) {
            return 'sem_login';
        }
        $agora = time();
        if ($agora - intval($_SESSION['adm_inicio'] ?? 0) > jc_admin_max_seg()) {
            return 'tempo_total';
        }
        if ($agora - intval($_SESSION['adm_ultimo'] ?? 0) > jc_admin_ocioso_seg()) {
            return 'inatividade';
        }
        if (!hash_equals((string) ($_SESSION['adm_ua'] ?? ''), jc_admin_hash_navegador())) {
            return 'navegador';
        }
        return null;
    }

    /** Mensagem amigável para cada motivo de fim de sessão. */
    function jc_admin_msg_fim_sessao(string $motivo): string {
        return [
            'inatividade' => 'Sua sessão terminou por inatividade. Entre de novo.',
            'tempo_total' => 'Sua sessão atingiu o tempo máximo. Entre de novo.',
            'navegador'   => 'Sua sessão foi encerrada por segurança. Entre de novo.',
            'versao'      => 'Sua sessão foi encerrada (senha alterada ou sessões encerradas). Entre de novo.',
            'inativo'     => 'Esta conta administrativa está desativada.',
            'logout'      => 'Você saiu da Central administrativa.',
        ][$motivo] ?? 'Entre para continuar.';
    }

    // ── Cabeçalhos de segurança ─────────────────────────────────────────

    /** Nonce desta resposta (para o <script>/<style> permitidos pela CSP). */
    function jc_admin_nonce(): string {
        static $n = null;
        if ($n === null) {
            $n = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        }
        return $n;
    }

    function jc_admin_cabecalhos(): void {
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        $nonce = jc_admin_nonce();
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; "
             . "style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; "
             . "form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('X-Robots-Tag: noindex, nofollow');
    }

    // ── CSRF ────────────────────────────────────────────────────────────

    function jc_admin_csrf_token(): string {
        if (empty($_SESSION['adm_csrf']) || !is_string($_SESSION['adm_csrf'])) {
            $_SESSION['adm_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['adm_csrf'];
    }

    function jc_admin_csrf_campo(): string {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(jc_admin_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Confere o token (campo _csrf ou cabeçalho X-CSRF-Token) e, quando o
     * navegador informa, a origem da requisição (mesmo host).
     */
    function jc_admin_csrf_valido(): bool {
        $enviado = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!is_string($enviado) || empty($_SESSION['adm_csrf']) || !hash_equals((string) $_SESSION['adm_csrf'], $enviado)) {
            return false;
        }
        $origem = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origem !== '' && $origem !== 'null') {
            $p = parse_url($origem);
            $host = ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
            if (strcasecmp($host, (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
                return false;
            }
        }
        return true;
    }

    /** Requisição POST? (toda ação que muda estado na Central é POST) */
    function jc_admin_post(): bool {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    // ── Mensagens entre redirecionamentos (padrão POST → redirect → GET) ─

    function jc_admin_flash(string $tipo, string $texto): void {
        $_SESSION['adm_flash'][] = ['tipo' => $tipo, 'texto' => $texto];
    }

    function jc_admin_flash_pegar(): array {
        $f = $_SESSION['adm_flash'] ?? [];
        unset($_SESSION['adm_flash']);
        return is_array($f) ? $f : [];
    }

    /** Redireciona dentro da pasta admin/ (só nomes de página conhecidos). */
    function jc_admin_redirecionar(string $pagina, array $params = []): void {
        if (!preg_match('/^[a-z_]+\.php$/', $pagina)) {
            $pagina = 'index.php';
        }
        $url = $pagina . ($params ? '?' . http_build_query($params) : '');
        if (!headers_sent()) {
            header('Location: ' . $url, true, 303);
        }
        exit;
    }
}
