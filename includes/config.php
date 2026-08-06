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

// ── Credenciais ───────────────────────────────────────────────────────────────
// Usando define() para evitar sobrescrita acidental por outras variáveis.
if (!defined('DB_HOST')) {
    define('DB_HOST',    'localhost');
    define('DB_USER',    'root');
    define('DB_PASS',    '');
    define('DB_NAME',    'jovemconnect');
    define('DB_CHARSET', 'utf8mb4');
}

// ── Conexão mysqli ────────────────────────────────────────────────────────────
// Uma única instância. Nenhum PDO. Nenhuma duplicata.
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

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