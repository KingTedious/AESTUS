<?php
/* ============================================================
   scripts/jovemconnect.php — ajudante de linha de comando dos
   iniciar_jovemconnect.bat e parar_jovemconnect.bat

   Só roda pelo terminal (php.exe do XAMPP); a pasta scripts/ também é
   bloqueada para o navegador (.htaccess). Lê o banco pelo .env (mesmas
   regras de includes/config.php) e nunca mostra a senha. Mensagens sem
   acento de propósito (o console do Windows nem sempre mostra UTF-8).

   Comandos (código de saída 0 = ok):
     checar                 PHP, extensões, .env, pastas graváveis, htdocs
     criar-env              cria o .env a partir do .env.example (nunca sobrescreve)
     porta <n>              0 se 127.0.0.1:<n> aceita conexão
     esperar-porta <n> <s>  espera até <s> segundos a porta abrir
     quem-usa <n>           mostra o programa que escuta na porta (Windows)
     apache                 0 se quem responde em http://127.0.0.1/ é um Apache
     site <pasta>           0 se http://localhost/<pasta>/index.php é o JovemConnect
     banco-status           0 instalado | 10 não existe | 11 existe vazio | 2 MySQL inacessível
     banco-instalar         cria o banco (se não existe) e importa o schema.sql
                            SÓ se estiver vazio — nunca importa por cima
     banco-seed             importa database/seed_demo.sql (não duplica nada)
     banco-nome             imprime o nome do banco (DB_NAME)
     banco-apagar <nome>    DROP DATABASE — só se <nome> == DB_NAME e não for do sistema
     ia-parar               fecha SÓ a IA desta pasta (pid/instância de logs/ia_porta.json)
   ============================================================ */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$RAIZ = dirname(__DIR__);
require_once $RAIZ . '/includes/env.php';
mysqli_report(MYSQLI_REPORT_OFF);

$DB = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '',
    'nome' => getenv('DB_NAME') ?: 'jovemconnect',
];
const BANCOS_DO_SISTEMA = ['mysql', 'information_schema', 'performance_schema', 'sys', 'phpmyadmin', 'test'];

function diz(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }

function conectar(array $db): ?mysqli {
    $c = @new mysqli($db['host'], $db['user'], $db['pass']);
    if ($c->connect_errno) {
        diz('  MySQL inacessivel (' . $c->connect_errno . '). O MySQL do XAMPP esta ligado? Usuario/senha do .env corretos?');
        return null;
    }
    $c->set_charset('utf8mb4');
    return $c;
}

function nome_valido(string $nome): bool {
    return (bool) preg_match('/^[A-Za-z0-9_]{1,64}$/', $nome);
}

function tabelas(mysqli $c, string $nome): ?int {
    $st = $c->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
    $st->bind_param('s', $nome);
    $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0];
    $st = $c->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
    $st->bind_param('s', $nome);
    $st->execute();
    return ((int) $st->get_result()->fetch_row()[0]) === 0 ? null : $n;   // null = não existe
}

/** Executa um arquivo .sql inteiro; para no primeiro erro (como uma importação). */
function importar(mysqli $c, string $arquivo): bool {
    $sql = @file_get_contents($arquivo);
    if ($sql === false) {
        diz('  Arquivo nao encontrado: ' . basename($arquivo));
        return false;
    }
    if (!$c->multi_query($sql)) {
        diz('  ERRO no SQL: ' . $c->error);
        return false;
    }
    do {
        if ($r = $c->store_result()) {
            $r->free();
        }
        if ($c->errno) {
            diz('  ERRO no SQL: ' . $c->error);
            return false;
        }
    } while ($c->more_results() && $c->next_result());
    if ($c->errno) {
        diz('  ERRO no SQL: ' . $c->error);
        return false;
    }
    return true;
}

function porta_aberta(int $porta): bool {
    $s = @fsockopen('127.0.0.1', $porta, $en, $es, 1.0);
    if ($s) {
        fclose($s);
        return true;
    }
    return false;
}

function http_get(string $url, int $timeout = 3): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2,
                            CURLOPT_TIMEOUT => $timeout, CURLOPT_PROXY => '', CURLOPT_FOLLOWLOCATION => false]);
    $corpo = curl_exec($ch);
    $http = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);
    return [$http, is_string($corpo) ? $corpo : ''];
}

$cmd = $argv[1] ?? '';
$arg = $argv[2] ?? '';

switch ($cmd) {

case 'checar':
    $ok = true;
    diz('  PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '8.1.0', '>=') ? ' (ok)' : ' - ERRO: o projeto precisa do PHP 8.1 ou mais novo'));
    $ok = $ok && version_compare(PHP_VERSION, '8.1.0', '>=');
    foreach (['mysqli', 'curl', 'openssl', 'mbstring', 'json'] as $ext) {
        $tem = extension_loaded($ext);
        diz('  extensao ' . $ext . ': ' . ($tem ? 'ok' : 'FALTANDO (ative no php.ini do XAMPP)'));
        $ok = $ok && $tem;
    }
    $env = is_file($RAIZ . '/.env');
    diz('  .env: ' . ($env ? 'ok' : 'nao existe (sera oferecido criar a partir do .env.example)'));
    foreach (['logs', 'uploads/curriculos'] as $p) {
        $dir = $RAIZ . '/' . $p;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $grava = is_dir($dir) && is_writable($dir);
        diz('  pasta ' . $p . ': ' . ($grava ? 'gravavel' : 'SEM PERMISSAO DE ESCRITA'));
        $ok = $ok && $grava;
    }
    $em_htdocs = strtolower(basename(dirname($RAIZ))) === 'htdocs';
    diz('  dentro de htdocs: ' . ($em_htdocs ? 'sim' : 'NAO - o Apache do XAMPP so mostra pastas dentro de htdocs'));
    exit($ok ? ($env ? 0 : 20) : 1);

case 'criar-env':
    if (is_file($RAIZ . '/.env')) {
        diz('  .env ja existe; nada foi alterado.');
        exit(0);
    }
    if (!@copy($RAIZ . '/.env.example', $RAIZ . '/.env')) {
        diz('  ERRO: nao consegui criar o .env a partir do .env.example.');
        exit(1);
    }
    diz('  .env criado a partir do .env.example (banco: usuario root, sem senha - padrao do XAMPP).');
    exit(0);

case 'porta':
    exit(porta_aberta((int) $arg) ? 0 : 1);

case 'esperar-porta':
    $limite = microtime(true) + (float) ($argv[3] ?? 20);
    while (microtime(true) < $limite) {
        if (porta_aberta((int) $arg)) {
            exit(0);
        }
        usleep(300000);
    }
    exit(1);

case 'quem-usa':
    if (stripos(PHP_OS, 'WIN') !== 0) {
        diz('  (so no Windows)');
        exit(0);
    }
    $porta = (int) $arg;
    $linhas = [];
    exec('netstat -ano -p TCP', $linhas);
    foreach ($linhas as $l) {
        if (preg_match('/^\s*TCP\s+\S+:' . $porta . '\s+\S+\s+LISTENING\s+(\d+)/i', $l, $m)) {
            $info = [];
            exec('tasklist /FI "PID eq ' . intval($m[1]) . '" /FO CSV /NH', $info);
            $prog = isset($info[0]) ? trim(explode(',', $info[0])[0], '"') : '?';
            diz('  porta ' . $porta . ' em uso por: ' . $prog . ' (PID ' . $m[1] . ')');
            exit(0);
        }
    }
    diz('  nao identifiquei o programa na porta ' . $porta . '.');
    exit(0);

case 'apache':
    $ch = curl_init('http://127.0.0.1:' . (ctype_digit($arg) ? $arg : '80') . '/');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_NOBODY => true,
                            CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 4, CURLOPT_PROXY => '']);
    $cab = (string) curl_exec($ch);
    curl_close($ch);
    exit(preg_match('/^Server:\s*Apache/mi', $cab) ? 0 : 1);

case 'site':
    $pasta = rawurlencode($arg !== '' ? $arg : basename($RAIZ));
    [$http, $corpo] = http_get("http://127.0.0.1/{$pasta}/index.php");
    exit($http === 200 && stripos($corpo, 'JovemConnect') !== false ? 0 : 1);

case 'banco-nome':
    diz($DB['nome']);
    exit(0);

case 'banco-status':
    if (!nome_valido($DB['nome'])) {
        diz('  DB_NAME invalido no .env.');
        exit(3);
    }
    $c = conectar($DB);
    if (!$c) {
        exit(2);
    }
    $n = tabelas($c, $DB['nome']);
    if ($n === null) {
        diz('  Banco "' . $DB['nome'] . '" nao existe neste computador.');
        exit(10);
    }
    if ($n === 0) {
        diz('  Banco "' . $DB['nome'] . '" existe, mas esta vazio.');
        exit(11);
    }
    diz('  Banco "' . $DB['nome'] . '" instalado (' . $n . ' tabelas).' . ($n < 20 ? ' Aviso: o schema atual tem 20 tabelas; veja docs/MIGRACOES.md.' : ''));
    exit(0);

case 'banco-instalar':
    if (!nome_valido($DB['nome']) || in_array(strtolower($DB['nome']), BANCOS_DO_SISTEMA, true)) {
        diz('  DB_NAME invalido no .env; nada foi feito.');
        exit(3);
    }
    $c = conectar($DB);
    if (!$c) {
        exit(2);
    }
    $n = tabelas($c, $DB['nome']);
    if ($n !== null && $n > 0) {
        diz('  O banco "' . $DB['nome'] . '" ja tem ' . $n . ' tabelas: NADA foi importado (o schema.sql so instala em banco vazio).');
        exit(12);
    }
    if ($n === null && !$c->query('CREATE DATABASE `' . $DB['nome'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
        diz('  ERRO ao criar o banco: ' . $c->error);
        exit(1);
    }
    $c->select_db($DB['nome']);
    if (!importar($c, $RAIZ . '/schema.sql')) {
        diz('  A instalacao parou. Como o banco e novo, ele pode ser apagado e instalado de novo.');
        exit(1);
    }
    $c2 = conectar($DB);
    $total = $c2 ? (int) tabelas($c2, $DB['nome']) : 0;
    diz('  Banco "' . $DB['nome'] . '" instalado pelo schema.sql: ' . $total . ' tabelas.');
    exit($total > 0 ? 0 : 1);

case 'banco-seed':
    $c = conectar($DB);
    if (!$c) {
        exit(2);
    }
    if (!(tabelas($c, $DB['nome']) > 0)) {
        diz('  O banco ainda nao esta instalado; nada foi importado.');
        exit(1);
    }
    $c->select_db($DB['nome']);
    if (!importar($c, $RAIZ . '/database/seed_demo.sql')) {
        exit(1);
    }
    diz('  Dados de demonstracao carregados (senha das contas em docs/SEED_DEMO.md).');
    exit(0);

case 'banco-apagar':
    $nome = $DB['nome'];
    if ($arg !== $nome || !nome_valido($nome) || in_array(strtolower($nome), BANCOS_DO_SISTEMA, true)) {
        diz('  Recusado: o nome confirmado nao e o banco do projeto (DB_NAME). Nada foi apagado.');
        exit(3);
    }
    $c = conectar($DB);
    if (!$c) {
        exit(2);
    }
    $n = tabelas($c, $nome);
    if ($n === null) {
        diz('  O banco "' . $nome . '" nao existe neste computador; nada a apagar.');
        exit(0);
    }
    if (!$c->query('DROP DATABASE `' . $nome . '`')) {
        diz('  ERRO ao apagar o banco: ' . $c->error);
        exit(1);
    }
    diz('  Banco "' . $nome . '" apagado (' . $n . ' tabelas).');
    exit(0);

case 'ia-parar':
    $arq = $RAIZ . '/logs/ia_porta.json';
    $d = is_file($arq) ? json_decode((string) @file_get_contents($arq, false, null, 0, 4096), true) : null;
    $porta = is_array($d) ? ($d['porta'] ?? null) : null;
    $inst = is_array($d) ? ($d['instancia'] ?? null) : null;
    $pid = is_array($d) ? ($d['pid'] ?? null) : null;
    if (!is_int($porta) || !is_int($pid) || !is_string($inst) || !preg_match('/^[a-f0-9]{32}$/', $inst)) {
        diz('  Nenhuma IA desta pasta registrada (logs/ia_porta.json); nada a fechar.');
        exit(0);
    }
    [$http, $corpo] = http_get("http://127.0.0.1:{$porta}/ping", 2);
    $ping = $http === 200 ? json_decode($corpo, true) : null;
    if (!is_array($ping) || ($ping['instancia'] ?? null) !== $inst) {
        diz('  A IA registrada nao esta mais rodando; nada a fechar.');
        @unlink($arq);
        exit(0);
    }
    if (stripos(PHP_OS, 'WIN') === 0) {
        $info = [];
        exec('tasklist /FI "PID eq ' . $pid . '" /FO CSV /NH', $info);
        if (!isset($info[0]) || stripos($info[0], 'python') === false) {
            diz('  O PID registrado nao e um Python; por seguranca nada foi fechado.');
            exit(1);
        }
        exec('taskkill /PID ' . $pid . ' /F', $saida, $rc);
    } else {
        $rc = function_exists('posix_kill') && posix_kill($pid, 15) ? 0 : 1;
    }
    for ($i = 0; $i < 20 && porta_aberta($porta); $i++) {
        usleep(250000);
    }
    $fechou = !porta_aberta($porta);
    if ($fechou) {
        $d2 = json_decode((string) @file_get_contents($arq), true);
        if (is_array($d2) && ($d2['instancia'] ?? null) === $inst) {
            @unlink($arq);
        }
    }
    diz($fechou ? "  IA desta pasta fechada (porta {$porta})." : '  ERRO: a IA nao fechou; feche a janela "JovemConnect - IA".');
    exit($fechou ? 0 : 1);

default:
    diz('Uso: php scripts/jovemconnect.php <comando>  (veja o cabecalho deste arquivo)');
    exit(2);
}
