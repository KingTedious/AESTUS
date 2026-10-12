<?php
/* ============================================================
   includes/config.php

   Único propósito: estabelecer a conexão mysqli com o banco.
   ============================================================ */

// ── Logger (com fallback se logger.php não existir) ────────────────────────
if (file_exists(__DIR__ . '/logger.php')) {
    require_once __DIR__ . '/logger.php';
}

if (!function_exists('registrar_log')) {
    /** Fallback: grava no error_log padrão do PHP. */
    function registrar_log(string $mensagem, string $nivel = 'INFO'): void {
        error_log("[JovemConnect][{$nivel}] {$mensagem}");
    }
}

// ── rede de segurança para exceções não tratadas ────────────────────
// Desde o PHP 8.1 o mysqli lança mysqli_sql_exception por padrão. Sem este
// handler, um erro inesperado (chave estrangeira, dado longo demais, coluna
// inexistente...) virava "Fatal error" com caminho do arquivo, SQL e stack
// trace na tela (display_errors do XAMPP). Agora os detalhes completos vão
// para logs/app.log (evento erro.inesperado, via jc_log_excecao) e o usuário recebe só uma mensagem amigável
// (JSON nos endpoints AJAX). Não engole o erro: ele continua registrado.
if (!function_exists('jc_excecao_nao_tratada')) {
    function jc_excecao_nao_tratada(Throwable $e): void {
        // tipo, arquivo:linha e código — sem SQL, parâmetros nem dados digitados
        if (function_exists('jc_log_excecao')) {
            jc_log_excecao($e);
        } else {
            error_log('[JovemConnect] ' . get_class($e) . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
        }

        $json = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
        foreach (headers_list() as $h) {
            if (stripos($h, 'application/json') !== false) {
                $json = true;
                break;
            }
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: ' . ($json ? 'application/json' : 'text/html') . '; charset=utf-8');
        }

        // Além do "Voltar" (não faz nada se a página foi aberta direto), um destino
        // explícito: a página inicial do projeto, com o caminho relativo ao script atual
        $inicio = 'index.php';
        $raiz   = realpath(dirname(__DIR__));
        $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
        if ($raiz && $script && stripos($script, $raiz) === 0) {
            $rel    = trim(str_replace('\\', '/', substr(dirname($script), strlen($raiz))), '/');
            $inicio = str_repeat('../', ($rel === '') ? 0 : substr_count($rel, '/') + 1) . 'index.php';
        }

        echo $json
            ? json_encode(['error' => 'Erro interno. Tente novamente em instantes.'])
            : '<p style="font-family:sans-serif;padding:24px;">Ocorreu um erro inesperado. '
              . 'Tente novamente em instantes. <a href="javascript:history.back()">Voltar</a>'
              . ' · <a href="' . htmlspecialchars($inicio) . '">Ir para a página inicial</a></p>';
    }
    set_exception_handler('jc_excecao_nao_tratada');
}

// ── Credenciais ───────────────────────────────────────────────────────────────
// Usando define() para evitar sobrescrita acidental por outras variáveis.
require_once __DIR__ . '/env.php';

if (!defined('DB_HOST')) {
    define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
    define('DB_USER',    getenv('DB_USER') ?: 'root');
    define('DB_PASS',    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    define('DB_NAME',    getenv('DB_NAME') ?: 'jovemconnect');
    define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
}

// ── Conexão mysqli ────────────────────────────────────────────────────────────
// Uma única instância. Nenhum PDO. Nenhuma duplicata.
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (\mysqli_sql_exception $e) {
    // PHP 8.1+ lanca excecao em falha de conexao antes de checar connect_error.
    registrar_log('Falha na conexao com o banco de dados (codigo ' . $e->getCode() . ')', 'ERROR');
    if (function_exists('jc_log')) {
        jc_log('banco.indisponivel', 'erro', ['errno' => $e->getCode()]);
    }
    http_response_code(503);
    die('O sistema esta temporariamente indisponivel. Tente novamente em alguns instantes.');
}

if ($conn->connect_error) {
    // Loga o erro real (interno) sem exibi-lo ao usuário
    registrar_log(
        "Falha na conexão com o banco de dados: " . $conn->connect_error,
        'ERROR'
    );

    // Mensagem amigável — sem vazar detalhes técnicos
    http_response_code(503);
    die("O sistema está temporariamente indisponível. Tente novamente em alguns instantes.");
}

// Charset utf8mb4: suporta todos os caracteres latinos e emojis
$conn->set_charset(DB_CHARSET);

// ── Stage 7: conta suspensa pela administração perde a sessão ─────────────────
// Só age quando há sessão de jovem ou empresa aberta e a migração da Stage 7
// foi aplicada; sem ela, nada muda (ver includes/conta_status.php).
if (session_status() === PHP_SESSION_ACTIVE && (isset($_SESSION['jovem_id']) || isset($_SESSION['empresa_id']))) {
    require_once __DIR__ . '/conta_status.php';
    jc_conta_verificar_sessao($conn);
}

// ── FLASK: não verificar aqui ─────────────────────────────────────────────────
// A checagem de disponibilidade do servidor de IA pertence a avaliar_ajax.php.
// Rodar fsockopen() em config.php adicionaria ~1s de latência em cada página.