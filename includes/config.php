<?php
/* ============================================================
   includes/config.php

   Único propósito: estabelecer a conexão mysqli com o banco.

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):

   1. DUPLA CONEXÃO — definia $conn como PDO e logo depois
      sobrescrevia com mysqli. O PDO ficava pendurado na memória
      e qualquer include feito entre os dois blocos recebia
      o tipo errado. Esse foi o motivo de processar_login.php
      usar PDO: o dev copiou do bloco PDO sem perceber que
      havia um mysqli logo abaixo.

   2. VARIÁVEIS DUPLICADAS — $host, $dbname/$db, $username/$user,
      $password/$pass definidos duas vezes com nomes diferentes.

   3. garantir_flask_ativo() em config.php — rodava a cada
      carregamento de página (login, cadastro, perfil, vagas…)
      fazendo fsockopen() com timeout de 1s mesmo em páginas
      que não usam IA. Removida daqui; a verificação de
      disponibilidade do Flask cabe em avaliar_ajax.php.

   4. die() com $conn->connect_error expunha detalhes técnicos
      do servidor para o usuário final.
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

// ── Stage 3: rede de segurança para exceções não tratadas ────────────────────
// Desde o PHP 8.1 o mysqli lança mysqli_sql_exception por padrão. Sem este
// handler, um erro inesperado (chave estrangeira, dado longo demais, coluna
// inexistente...) virava "Fatal error" com caminho do arquivo, SQL e stack
// trace na tela (display_errors do XAMPP). Agora os detalhes completos vão
// para logs/sistema.log e o usuário recebe só uma mensagem amigável
// (JSON nos endpoints AJAX). Não engole o erro: ele continua registrado.
if (!function_exists('jc_excecao_nao_tratada')) {
    function jc_excecao_nao_tratada(Throwable $e): void {
        registrar_log(
            'Exceção não tratada: ' . get_class($e) . ': ' . $e->getMessage()
            . ' em ' . $e->getFile() . ':' . $e->getLine()
            . ' | URL: ' . ($_SERVER['REQUEST_URI'] ?? '-')
            . ' | Trace: ' . str_replace(["\r", "\n"], ' | ', $e->getTraceAsString()),
            'ERROR'
        );

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

        echo $json
            ? json_encode(['error' => 'Erro interno. Tente novamente em instantes.'])
            : '<p style="font-family:sans-serif;padding:24px;">Ocorreu um erro inesperado. '
              . 'Tente novamente em instantes. <a href="javascript:history.back()">Voltar</a></p>';
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
    registrar_log('Falha na conexao com o banco de dados: ' . $e->getMessage(), 'ERROR');
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

// ── FLASK: não verificar aqui ─────────────────────────────────────────────────
// A checagem de disponibilidade do servidor de IA pertence a avaliar_ajax.php.
// Rodar fsockopen() em config.php adicionaria ~1s de latência em cada página.