<?php
/* ============================================================
   includes/csrf.php  (HIGH-002)

   Protecao CSRF centralizada, por sessao.

   Uso em paginas:
     require_once <caminho>/includes/csrf.php;   // ja inicia a sessao endurecida

   Em formularios HTML:
     <form ...> <?= csrf_field() ?> ... </form>

   No inicio do processamento de um POST que altera estado:
     csrf_require();            // paginas normais (responde 403 + texto)
     csrf_require(true);        // endpoints AJAX/JSON (responde 403 + JSON)

   Em paginas que fazem chamadas fetch() AJAX, emitir UMA vez no <body>:
     <?= csrf_js() ?>
   Isso injeta o token no header 'X-CSRF-Token' de todo fetch() POST
   de mesma origem, sem precisar alterar cada chamada existente.
   ============================================================ */

require_once __DIR__ . '/session.php';
jc_session_start();

if (!function_exists('csrf_token')) {

    function csrf_token(): string {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Campo hidden pronto para inserir em qualquer <form>. */
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="'
             . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Valida o token recebido. Procura, nesta ordem:
     *   1. $_POST['csrf_token']           (forms classicos)
     *   2. header X-CSRF-Token            (fetch AJAX via csrf_js)
     *   3. campo csrf_token no corpo JSON (fetch com Content-Type: application/json)
     * Comparacao em tempo constante (hash_equals).
     */
    function csrf_verify(): bool {
        $enviado = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if ($enviado === null) {
            // Ultimo recurso: corpo JSON (nao e multipart, php://input e re-legivel).
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $json = json_decode($raw, true);
                if (is_array($json) && isset($json['csrf_token'])) {
                    $enviado = $json['csrf_token'];
                }
            }
        }

        return is_string($enviado)
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $enviado);
    }

    /** Exige token valido; caso contrario encerra com 403 e nao executa a acao. */
    function csrf_require(bool $ajax = false): void {
        if (csrf_verify()) {
            return;
        }
        if (function_exists('jc_log_seguranca')) {
            jc_log_seguranca('csrf.recusado', 'aviso', ['ajax' => $ajax]);
        }
        http_response_code(403);
        // Mesma recusa, mensagem compreensível (antes: texto técnico,
        // sem acentos, numa página sem nenhum link para sair dela)
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Sua sessão expirou ou a página está desatualizada. '
                                       . 'Recarregue a página e tente novamente.']);
        } else {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            $inicio = htmlspecialchars(_csrf_url_inicio(), ENT_QUOTES, 'UTF-8');
            $voltar = _csrf_url_voltar();
            $link_voltar = $voltar !== null
                ? '<p><a href="' . htmlspecialchars($voltar, ENT_QUOTES, 'UTF-8') . '">Abrir a página de novo</a></p>'
                : '';
            echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">'
               . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
               . '<title>Não foi possível concluir | JovemConnect</title></head>'
               . '<body style="font-family:system-ui,sans-serif;max-width:520px;margin:48px auto;padding:0 16px;line-height:1.6;">'
               . '<h1 style="font-size:20px;">Não foi possível concluir</h1>'
               . '<p>Sua sessão expirou ou a página ficou desatualizada. Por segurança, nada foi enviado.</p>'
               . '<p>Abra a página de novo e tente outra vez.</p>'
               . $link_voltar
               . '<p><a href="' . $inicio . '">Ir para a página inicial</a></p>'
               . '</body></html>';
        }
        exit;
    }

    /** Index.php do projeto, relativo ao script atual (ex.: "../index.php"). */
    function _csrf_url_inicio(): string {
        $raiz   = realpath(dirname(__DIR__));
        $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($raiz && $script && stripos($script, $raiz) === 0) {
            $rel   = trim(str_replace('\\', '/', substr(dirname($script), strlen($raiz))), '/');
            $nivel = ($rel === '') ? 0 : substr_count($rel, '/') + 1;
            return str_repeat('../', $nivel) . 'index.php';
        }
        return '/';
    }

    /** Página de onde veio o envio — só se for deste mesmo site (link, nunca redirect). */
    function _csrf_url_voltar(): ?string {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $p   = is_string($ref) ? parse_url($ref) : false;
        if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) {
            return null;
        }
        $host = $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        return (strcasecmp($host, (string)($_SERVER['HTTP_HOST'] ?? '')) === 0) ? $ref : null;
    }

    /**
     * Emite um <script> que faz todo fetch() POST (de MESMA ORIGEM) carregar
     * o header X-CSRF-Token automaticamente. Nao toca fetch GET nem chamadas
     * cross-origin (ViaCEP, BrasilAPI, ping do Flask continuam intactos).
     */
    function csrf_js(): string {
        $t = json_encode(csrf_token());
        return "<script>(function(){var t={$t};var of=window.fetch;"
             . "window.fetch=function(u,o){o=o||{};try{var a=new URL(u,location.href);"
             . "if(a.origin===location.origin){var m=(o.method||'GET').toUpperCase();"
             . "if(m!=='GET'&&m!=='HEAD'){o.headers=Object.assign({},o.headers||{},"
             . "{'X-CSRF-Token':t});}}}catch(e){}return of.call(this,u,o);};})();</script>";
    }
}
