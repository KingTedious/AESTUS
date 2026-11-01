<?php
/* ============================================================
   scripts/seguranca/regressao_isolada.php — testes de regressão
   das correções de segurança, num ambiente ISOLADO

   O que é isolado (nada disso toca o seu banco nem a pasta do projeto):
     - BANCO: cria um banco NOVO "jc_teste_seg_AAAAMMDDHHMMSS" no MySQL do
       XAMPP (mesmo usuário do .env), instala o schema.sql e o seed de
       demonstração, e APAGA esse banco no fim (use --manter para guardar).
       O banco do .env (DB_NAME) nunca é aberto.
     - ARQUIVOS: copia o projeto para uma pasta temporária (sem .git, venv,
       logs e currículos) e é essa cópia que roda — uploads, logs e o limite
       de tentativas dos testes ficam lá e são apagados no fim.
     - SERVIDOR: o servidor embutido do PHP (php -S) numa porta livre de
       127.0.0.1, só durante o teste. O Apache não é usado (o .htaccess é
       conferido por scripts\seguranca\verificar_protecao.bat).
     - IA e e-mail: a IA fica "fora do ar" de propósito (porta sem nada) e o
       e-mail/SMS não é enviado (sem vendor/); os testes não dependem deles.

   Mostra só: teste, resultado e um detalhe técnico curto. Nunca mostra
   senhas (as contas de teste usam senhas aleatórias criadas na hora).
   Relatório: logs/regressao_isolada-AAAAMMDD-HHMMSS.log (fora do Git).

   Uso (na pasta do projeto, com o MySQL do XAMPP ligado):
     scripts\seguranca\regressao_isolada.bat           (Windows)
     php scripts/seguranca/regressao_isolada.php [--manter]
   Código de saída: 0 = tudo aprovado; 1 = alguma reprovação; 2 = não deu
   para montar o ambiente (MySQL desligado, sem permissão etc.).
   ============================================================ */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$RAIZ = dirname(__DIR__, 2);
require_once $RAIZ . '/includes/env.php';
mysqli_report(MYSQLI_REPORT_OFF);
$MANTER = in_array('--manter', $argv, true);
$INICIO = microtime(true);
$CARIMBO = date('YmdHis');
$BANCO = 'jc_teste_seg_' . $CARIMBO;
$DB = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '',
    'nome' => getenv('DB_NAME') ?: 'jovemconnect',
];
$SAIDA = [];
$RESULTADOS = [];
$AVISOS_PHP = [];

function diz(string $t): void { global $SAIDA; echo $t, PHP_EOL; $SAIDA[] = $t; }

function teste(string $id, string $descricao, bool $ok, string $detalhe = ''): void {
    global $RESULTADOS;
    $RESULTADOS[] = [$id, $descricao, $ok, $detalhe];
    diz(sprintf('%-10s %-9s %s%s', $id, $ok ? 'APROVADO' : 'REPROVADO', $descricao, $detalhe !== '' ? ' — ' . $detalhe : ''));
}

function apagar_arvore(string $dir): void {
    if ($dir === '' || strpos($dir, 'jc_teste_seg_') === false || !is_dir($dir)) {
        return;   // só apaga a pasta temporária criada por este script
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                                        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

function copiar_projeto(string $origem, string $destino): void {
    $pular = '#^(\.git|venv[^/]*|\.venv|node_modules|IA/__pycache__)(/|$)|^repomix-output\.|^\.env$|^logs/(?!\.htaccess$)|^uploads/curriculos/(?!\.htaccess$)#i';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origem, FilesystemIterator::SKIP_DOTS),
                                        RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($origem) + 1));
        if (preg_match($pular, $rel)) {
            continue;
        }
        $alvo = $destino . '/' . $rel;
        if ($f->isDir()) {
            @mkdir($alvo, 0777, true);
        } else {
            @mkdir(dirname($alvo), 0777, true);
            copy($f->getPathname(), $alvo);
        }
    }
    @mkdir($destino . '/logs', 0777, true);
    @mkdir($destino . '/uploads/curriculos', 0777, true);
}

function importar(mysqli $c, string $arquivo): ?string {
    $sql = @file_get_contents($arquivo);
    if ($sql === false) {
        return 'arquivo não encontrado: ' . basename($arquivo);
    }
    // Mensagem do MySQL (sem dados: o schema.sql não tem nenhum) e o comando em que parou
    $falha = function () use ($c): string {
        $msg = trim((string) $c->error);
        return 'erro no SQL (' . $c->errno . ($msg !== '' ? ': ' . mb_substr($msg, 0, 300) : '') . ')';
    };
    $n = 1;
    if (!$c->multi_query($sql)) {
        return $falha() . ' no comando 1';
    }
    do {
        if ($r = $c->store_result()) {
            $r->free();
        }
        if ($c->errno) {
            return $falha() . ' no comando ' . $n;
        }
        $n++;
    } while ($c->more_results() && $c->next_result());
    return $c->errno ? $falha() . ' no comando ' . $n : null;
}

/** O MySQL continua de pé? (2006/2013 = conexão perdida; pode ser queda do servidor) */
function mysql_vivo(array $DB): bool {
    $t = @new mysqli($DB['host'], $DB['user'], $DB['pass']);
    if ($t->connect_errno) {
        return false;
    }
    $t->close();
    return true;
}

function porta_livre(): int {
    for ($p = 8790; $p < 8890; $p++) {
        $s = @fsockopen('127.0.0.1', $p, $en, $es, 0.2);
        if ($s) {
            fclose($s);
            continue;
        }
        return $p;
    }
    return 0;
}

// ── Cliente HTTP com sessão própria (um "navegador" por cliente) ───────────────
final class Cliente {
    public $jar;
    public $base;
    public function __construct(string $base, string $tmp) {
        $this->base = $base;
        $this->jar = $tmp . '/cookies_' . bin2hex(random_bytes(4)) . '.txt';
        touch($this->jar);
    }
    /** @return array{0:int,1:string,2:string,3:string} [código, cabeçalhos, corpo, Location] */
    public function pedir(string $metodo, string $caminho, $dados = null, bool $multipart = false): array {
        global $AVISOS_PHP;
        $ch = curl_init($this->base . '/' . ltrim($caminho, '/'));
        $opts = [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_PROXY => '',
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => ['Expect:'],
        ];
        if ($dados !== null) {
            $opts[CURLOPT_POSTFIELDS] = $multipart ? $dados : (is_array($dados) ? http_build_query($dados) : $dados);
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $cod = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $tam = intval(curl_getinfo($ch, CURLINFO_HEADER_SIZE));
        curl_close($ch);
        $resp = is_string($resp) ? $resp : '';
        $cab = substr($resp, 0, $tam);
        $corpo = (string) substr($resp, $tam);
        $loc = preg_match('/^Location:\s*(\S+)/mi', $cab, $m) ? $m[1] : '';
        if (preg_match('/(<b>)?(Warning|Notice|Deprecated|Fatal error|Parse error)(<\/b>)?:\s|Stack trace:|mysqli_sql_exception/i', $corpo, $mm)) {
            $AVISOS_PHP[] = $caminho . ' (' . strip_tags($mm[0]) . ')';
        }
        return [$cod, $cab, $corpo, $loc];
    }
    public function get(string $c): array { return $this->pedir('GET', $c); }
    public function post(string $c, array $d): array { return $this->pedir('POST', $c, $d); }
    public function cookie(string $nome): string {
        foreach (@file($this->jar) ?: [] as $l) {
            $p = explode("\t", trim($l));
            if (count($p) >= 7 && $p[5] === $nome) {
                return $p[6];
            }
        }
        return '';
    }
}

function token(string $html): string {
    if (preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/var t="([a-f0-9]{64})"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function cpf_valido_novo(): string {
    $n = [];
    for ($i = 0; $i < 9; $i++) {
        $n[] = random_int(0, 9);
    }
    for ($t = 9; $t < 11; $t++) {
        $s = 0;
        for ($i = 0; $i < $t; $i++) {
            $s += $n[$i] * (($t + 1) - $i);
        }
        $n[] = (($s * 10) % 11) % 10;
    }
    return implode('', $n);
}

function login(Cliente $c, string $tipo, string $email, string $senha): array {
    [, , $html] = $c->get("{$tipo}/login.php");
    return $c->post("{$tipo}/processar_login.php", ['email' => $email, 'senha' => $senha, 'csrf_token' => token($html)]);
}

// ======================================================================
diz('Testes de regressao isolados - JovemConnect');
diz('Data: ' . date('d/m/Y H:i:s') . '   PHP ' . PHP_VERSION . '   Projeto: ' . basename($RAIZ));
diz('Banco de teste: ' . $BANCO . ' (o banco do .env NAO e usado)');
diz('');

if (!function_exists('curl_init')) {
    diz('ERRO: a extensao curl do PHP nao esta ativa (php.ini). Nada foi feito.');
    exit(2);
}
$c = @new mysqli($DB['host'], $DB['user'], $DB['pass']);
if ($c->connect_errno) {
    diz('ERRO: MySQL inacessivel (' . $c->connect_errno . '). Ligue o MySQL do XAMPP e rode de novo. Nada foi feito.');
    exit(2);
}
$c->set_charset('utf8mb4');
if (strcasecmp($BANCO, $DB['nome']) === 0) {
    diz('ERRO: nome de banco de teste coincide com DB_NAME. Nada foi feito.');
    exit(2);
}
if (!$c->query('CREATE DATABASE `' . $BANCO . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
    diz('ERRO: nao consegui criar o banco de teste (' . $c->errno . '). O usuario do .env precisa poder criar bancos. Nada foi feito.');
    exit(2);
}
$TMP = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'jc_teste_seg_' . $CARIMBO;
$COPIA = $TMP . DIRECTORY_SEPARATOR . basename($RAIZ);
$servidor = null;
$pipes = [];

$fim = function (int $codigo) use (&$servidor, &$pipes, $c, $BANCO, $TMP, $MANTER, $RAIZ, $CARIMBO, $INICIO, $DB) {
    global $SAIDA;
    parar_servidor($servidor, $pipes);
    if ($MANTER) {
        diz('');
        diz('--manter: banco ' . $BANCO . ' e copia em ' . $TMP . ' foram GUARDADOS (apague quando terminar).');
    } else {
        $apagou = @$c->query('DROP DATABASE IF EXISTS `' . $BANCO . '`');
        if (!$apagou) {
            // conexão perdida (ex.: erro 2006): tenta de novo com uma conexão nova
            $c2 = @new mysqli($DB['host'], $DB['user'], $DB['pass']);
            $apagou = !$c2->connect_errno && $c2->query('DROP DATABASE IF EXISTS `' . $BANCO . '`');
        }
        apagar_arvore($TMP);
        diz('');
        if ($apagou) {
            diz('Ambiente de teste apagado: banco ' . $BANCO . ' e a copia temporaria.');
        } else {
            diz('Copia temporaria apagada. O banco de teste ' . $BANCO . ' NAO pode ser apagado (MySQL inacessivel):');
            diz('apague-o depois no phpMyAdmin (so ele; o banco do .env nao e usado por este teste).');
        }
    }
    diz(sprintf('Tempo: %.1f s', microtime(true) - $INICIO));
    @mkdir($RAIZ . '/logs', 0775, true);
    @file_put_contents($RAIZ . '/logs/regressao_isolada-' . $CARIMBO . '.log', implode(PHP_EOL, $SAIDA) . PHP_EOL);
    exit($codigo);
};

// ── 1. Banco de teste ───────────────────────────────────────────────────────
$c->select_db($BANCO);
$erro = importar($c, $RAIZ . '/schema.sql');
if ($erro !== null) {
    diz('ERRO ao instalar o schema.sql no banco de teste: ' . $erro);
    if (preg_match('/\((2006|2013)\b/', $erro)) {
        diz(mysql_vivo($DB)
            ? 'O MySQL continua ligado: ele fechou esta conexao no meio da importacao (veja max_allowed_packet no my.ini).'
            : 'O MySQL CAIU durante a importacao. Veja as ultimas linhas de C:\\xampp\\mysql\\data\\mysql_error.log e ligue o MySQL de novo.');
    }
    $fim(2);
}
$c = new mysqli($DB['host'], $DB['user'], $DB['pass'], $BANCO);   // conexão limpa depois do multi_query
$c->set_charset('utf8mb4');
$seed = importar($c, $RAIZ . '/database/seed_demo.sql');
diz('Banco de teste: schema.sql instalado' . ($seed === null ? ' + seed de demonstracao.' : ' (seed nao carregado: ' . $seed . ').'));
$c = new mysqli($DB['host'], $DB['user'], $DB['pass'], $BANCO);
$c->set_charset('utf8mb4');

// contas e vagas próprias do teste (senhas aleatórias, nunca mostradas)
$senha = [];
$contas = [];
foreach (['A' => 'jovens', 'B' => 'jovens', 'E' => 'empresas', 'F' => 'empresas'] as $k => $tabela) {
    $senha[$k] = bin2hex(random_bytes(6)) . 'Aa1';
    $email = 'teste.seg.' . strtolower($k) . '.' . $CARIMBO . '@exemplo.com';
    $hash = password_hash($senha[$k], PASSWORD_DEFAULT);
    if ($tabela === 'jovens') {
        $cpf = cpf_valido_novo();
        $cpf = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        $st = $c->prepare("INSERT INTO jovens (nome, email, senha, cpf, cidade, estado, perfil_publico, email_verificado)
                           VALUES (?, ?, ?, ?, 'Sao Paulo', 'SP', 0, 1)");
        $nome = 'Teste Seguranca ' . $k;
        $st->bind_param('ssss', $nome, $email, $hash, $cpf);
    } else {
        $cnpj = sprintf('%02d.%03d.%03d/0001-%02d', random_int(10, 99), random_int(100, 999), random_int(100, 999), random_int(10, 99));
        $st = $c->prepare("INSERT INTO empresas (nome_empresa, email, senha, cnpj, cidade, estado, email_verificado)
                           VALUES (?, ?, ?, ?, 'Sao Paulo', 'SP', 1)");
        $nome = 'Empresa Teste ' . $k;
        $st->bind_param('ssss', $nome, $email, $hash, $cnpj);
    }
    if (!$st->execute()) {
        diz('ERRO ao criar conta de teste ' . $k . ' (' . $c->errno . ').');
        $fim(2);
    }
    $contas[$k] = ['id' => intval($c->insert_id), 'email' => $email, 'tabela' => $tabela];
}
$vagas = [];
foreach (['E', 'F'] as $k) {
    $st = $c->prepare("INSERT INTO vagas (empresa_id, titulo, descricao, cidade, estado, tipo_contrato, area)
                       VALUES (?, ?, 'Vaga criada pelo teste de regressao.', 'Sao Paulo', 'SP', 'Jovem Aprendiz', 'Administrativo')");
    $titulo = 'Vaga Teste ' . $k;
    $st->bind_param('is', $contas[$k]['id'], $titulo);
    $st->execute();
    $vagas[$k] = intval($c->insert_id);
}

// ── 2. Cópia isolada + servidor embutido ─────────────────────────────────────
@mkdir($TMP . '/tentativas', 0777, true);
@mkdir($TMP . '/logs_teste', 0777, true);
copiar_projeto($RAIZ, $COPIA);
$porta = porta_livre();
if ($porta === 0) {
    diz('ERRO: nenhuma porta livre entre 8790 e 8889.');
    $fim(2);
}
$amb = getenv();
$amb = is_array($amb) ? $amb : [];
$amb = array_merge($amb, [
    'DB_HOST' => $DB['host'], 'DB_USER' => $DB['user'], 'DB_PASS' => $DB['pass'], 'DB_NAME' => $BANCO,
    'JC_TENTATIVAS_DIR' => $TMP . DIRECTORY_SEPARATOR . 'tentativas',
    'JC_LOG_DIR' => $TMP . DIRECTORY_SEPARATOR . 'logs_teste',
    'JC_IA_PORTA' => '59876', 'JC_IA_PORTAS_EXTRAS' => '59877-59878',
    'JC_EXIBIR_ERROS' => '1',
]);
function subir_servidor(int $porta, string $raiz_www, array $amb, &$pipes) {
    $cmd = [PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=' . E_ALL, '-S', "127.0.0.1:{$porta}", '-t', $raiz_www];
    $nulo = stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null';
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w']], $pipes, $raiz_www, $amb);
    for ($i = 0; $i < 50 && is_resource($proc); $i++) {
        $s = @fsockopen('127.0.0.1', $porta, $en, $es, 0.2);
        if ($s) {
            fclose($s);
            return $proc;
        }
        usleep(200000);
    }
    return null;
}
function parar_servidor($proc, array $pipes): void {
    if (!is_resource($proc)) {
        return;
    }
    $st = proc_get_status($proc);
    if (!empty($st['pid']) && stripos(PHP_OS, 'WIN') === 0) {
        @exec('taskkill /PID ' . intval($st['pid']) . ' /T /F 2>nul');
    }
    @proc_terminate($proc);
    foreach ($pipes as $p) {
        if (is_resource($p)) {
            @fclose($p);
        }
    }
    @proc_close($proc);
}
$servidor = subir_servidor($porta, $TMP, $amb, $pipes);
if ($servidor === null) {
    diz('ERRO: o servidor embutido do PHP nao subiu na porta ' . $porta . '.');
    $fim(2);
}
$BASE = "http://127.0.0.1:{$porta}/" . rawurlencode(basename($RAIZ));
diz('Copia isolada servida em ' . $BASE . '/');
diz('');
diz(sprintf('%-10s %-9s %s', 'TESTE', 'RESULTADO', 'DESCRICAO'));
diz(str_repeat('-', 100));

// ── 3. Testes ───────────────────────────────────────────────────────────────
$anon = new Cliente($BASE, $TMP);

// Cabeçalhos de segurança
[$cod, $cab] = $anon->get('index.php');
teste('HDR-01', 'index.php responde 200', $cod === 200, "HTTP {$cod}");
teste('HDR-02', 'X-Content-Type-Options: nosniff', (bool) preg_match('/^X-Content-Type-Options:\s*nosniff/mi', $cab));
teste('HDR-03', 'X-Frame-Options: SAMEORIGIN', (bool) preg_match('/^X-Frame-Options:\s*SAMEORIGIN/mi', $cab));
teste('HDR-04', 'Referrer-Policy presente', (bool) preg_match('/^Referrer-Policy:\s*\S+/mi', $cab));
teste('HDR-05', "CSP com frame-ancestors 'self'", (bool) preg_match("/^Content-Security-Policy:.*frame-ancestors 'self'/mi", $cab));

// Páginas públicas e privadas sem sessão
foreach (['index.php', 'comecar.php', 'documentos.php', 'documentos.php?doc=privacidade', 'jovem/login.php',
          'empresa/login.php', 'jovem/cadastro.php', 'empresa/cadastro.php', 'recuperar_senha.php'] as $p) {
    [$cod] = $anon->get($p);
    teste('PUB', "pagina publica {$p}", $cod === 200, "HTTP {$cod}");
}
foreach (['dashboard/jovem.php', 'dashboard/empresa.php', 'jovem/perfil.php', 'empresa/perfil.php', 'Vagas/minhas.php',
          'candidaturas/minhas.php', 'uploads/ver_curriculo.php?id=1'] as $p) {
    [$cod] = $anon->get($p);
    teste('PRIV', "sem sessao, {$p} redireciona", $cod === 302, "HTTP {$cod}");
}
[$cod, , $corpo] = $anon->post('IA/avaliar_ajax.php', ['x' => '1']);
teste('PRIV', 'IA/avaliar_ajax.php sem sessao = 401 JSON', $cod === 401 && strpos($corpo, '{') === 0, "HTTP {$cod}");

// Login, renovação do token CSRF e da sessão (SEG-09, MED-001)
$cj = new Cliente($BASE, $TMP);
[, , $html] = $cj->get('jovem/login.php');
$t_antes = token($html);
$sid_antes = $cj->cookie('PHPSESSID');
[$cod, , , $loc] = $cj->post('jovem/processar_login.php', ['email' => $contas['A']['email'], 'senha' => $senha['A'], 'csrf_token' => $t_antes]);
teste('LOGIN-01', 'login do jovem leva ao painel', $cod === 302 && strpos($loc, 'dashboard/jovem.php') !== false, "HTTP {$cod} -> {$loc}");
$sid_depois = $cj->cookie('PHPSESSID');
teste('LOGIN-02', 'ID da sessao trocado no login', $sid_antes !== '' && $sid_depois !== '' && $sid_antes !== $sid_depois);
[$cod, , $html] = $cj->get('jovem/perfil.php');
$t_depois = token($html);
teste('LOGIN-03', 'token CSRF renovado no login (SEG-09)', $t_depois !== '' && $t_depois !== $t_antes);
foreach (['dashboard/jovem.php', 'Vagas/listar.php', 'Vagas/listar.php?id=' . $vagas['E'], 'Vagas/favoritos.php',
          'candidaturas/minhas.php', 'jovem/perfil.php', 'verificacoes/escolher_verificacao.php'] as $p) {
    [$cod] = $cj->get($p);
    teste('JOVEM', "pagina do jovem {$p}", in_array($cod, [200, 302], true) && ($cod === 200 || strpos($p, 'escolher') !== false), "HTTP {$cod}");
}

$ce = new Cliente($BASE, $TMP);
[$cod, , , $loc] = login($ce, 'empresa', $contas['E']['email'], $senha['E']);
teste('LOGIN-04', 'login da empresa leva ao painel', $cod === 302 && strpos($loc, 'dashboard/empresa.php') !== false, "HTTP {$cod} -> {$loc}");
foreach (['dashboard/empresa.php', 'empresa/perfil.php', 'Vagas/minhas.php', 'Vagas/criar.php', 'empresa/locais.php',
          'Vagas/candidatos.php?vaga=' . $vagas['E']] as $p) {
    [$cod] = $ce->get($p);
    teste('EMPRESA', "pagina da empresa {$p}", $cod === 200, "HTTP {$cod}");
}

// Autorização (IDOR) e privacidade por padrão (SEG-03)
[$cod] = $ce->get('Vagas/candidatos.php?vaga=' . $vagas['F']);
teste('IDOR-01', 'empresa nao abre candidatos da vaga de OUTRA empresa', $cod === 302, "HTTP {$cod}");
[$cod] = $ce->get('jovem/perfil.php?id=' . $contas['A']['id']);
teste('PRIV-01', 'empresa sem relacao nao ve perfil privado (404)', $cod === 404, "HTTP {$cod}");
[$cod] = $ce->get('uploads/ver_curriculo.php?id=' . $contas['A']['id']);
teste('PRIV-02', 'empresa sem relacao nao baixa curriculo (404)', $cod === 404, "HTTP {$cod}");
$c->query('UPDATE jovens SET perfil_publico = 1 WHERE id = ' . $contas['A']['id']);
[$cod] = $ce->get('jovem/perfil.php?id=' . $contas['A']['id']);
teste('PRIV-03', 'perfil que o jovem tornou publico aparece (200)', $cod === 200, "HTTP {$cod}");
$c->query('UPDATE jovens SET perfil_publico = 0 WHERE id = ' . $contas['A']['id']);
[, , $html] = $cj->get('Vagas/candidatar.php?vaga_id=' . $vagas['E']);
[$cod, , , $loc] = $cj->post('Vagas/candidatar.php', ['vaga_id' => $vagas['E'], 'csrf_token' => token($html)]);
$r = $c->query('SELECT COUNT(*) n FROM candidaturas WHERE jovem_id = ' . $contas['A']['id'] . ' AND vaga_id = ' . $vagas['E'])->fetch_assoc();
teste('CAND-01', 'candidatura do jovem e gravada', intval($r['n']) === 1, "HTTP {$cod} -> {$loc}");
[$cod] = $ce->get('jovem/perfil.php?id=' . $contas['A']['id']);
teste('PRIV-04', 'empresa ve o perfil de quem se candidatou a ela (200)', $cod === 200, "HTTP {$cod}");
[$cod] = $ce->get('Vagas/candidatos.php?vaga=' . $vagas['E']);
teste('CAND-02', 'candidato aparece para a empresa dona da vaga', $cod === 200, "HTTP {$cod}");
$cf = new Cliente($BASE, $TMP);
login($cf, 'empresa', $contas['F']['email'], $senha['F']);
[$cod] = $cf->get('jovem/perfil.php?id=' . $contas['A']['id']);
teste('PRIV-05', 'outra empresa continua sem ver o perfil privado (404)', $cod === 404, "HTTP {$cod}");

// Cadastro novo nasce privado (SEG-03) e já entra com a sessão nova
$cn = new Cliente($BASE, $TMP);
[, , $html] = $cn->get('jovem/cadastro.php');
$email_novo = 'teste.seg.novo.' . $CARIMBO . '@exemplo.com';
$senha_nova = bin2hex(random_bytes(6)) . 'Aa1';
[$cod, , , $loc] = $cn->post('jovem/cadastro.php', [
    'csrf_token' => token($html), 'nome' => 'Teste Cadastro Novo', 'email' => $email_novo, 'cpf' => cpf_valido_novo(),
    'data_nascimento' => date('Y-m-d', strtotime('-17 years')), 'cep' => '01001000', 'cidade' => 'Sao Paulo',
    'estado' => 'SP', 'senha' => $senha_nova, 'confirmar_senha' => $senha_nova, 'aceite_termos' => '1',
    'escolaridade_status' => 'cursando', 'formacao_nivel' => 'medio', 'formacao_ano' => '2',
]);
teste('CAD-01', 'cadastro de jovem conclui e segue para a verificacao', $cod === 302 && strpos($loc, 'escolher_verificacao') !== false, "HTTP {$cod} -> {$loc}");
$st = $c->prepare('SELECT id, perfil_publico FROM jovens WHERE email = ?');
$st->bind_param('s', $email_novo);
$st->execute();
$novo = $st->get_result()->fetch_assoc();
teste('CAD-02', 'perfil do jovem novo nasce PRIVADO (perfil_publico = 0)', $novo !== null && intval($novo['perfil_publico']) === 0,
      $novo === null ? 'conta nao encontrada' : 'perfil_publico = ' . $novo['perfil_publico']);
if ($novo) {
    [$cod] = $ce->get('jovem/perfil.php?id=' . intval($novo['id']));
    teste('CAD-03', 'empresa nao ve o perfil do jovem recem-cadastrado (404)', $cod === 404, "HTTP {$cod}");
}

// Limite de tentativas no login (SEG-02)
$cl = new Cliente($BASE, $TMP);
for ($i = 1; $i <= 5; $i++) {
    login($cl, 'jovem', $contas['B']['email'], 'senha-errada-' . $i);
}
[$cod, , , $loc] = login($cl, 'jovem', $contas['B']['email'], $senha['B']);
[, , $html] = $cl->get('jovem/login.php');
teste('LIM-01', 'depois de 5 erros, nem a senha certa entra', $cod === 302 && strpos($loc, 'dashboard') === false, "HTTP {$cod} -> {$loc}");
teste('LIM-02', 'mensagem de bloqueio temporario aparece', strpos($html, 'Muitas tentativas') !== false);
$cx = new Cliente($BASE, $TMP);
[$cod, , , $loc] = login($cx, 'jovem', $contas['A']['email'], $senha['A']);
teste('LIM-03', 'outra conta no mesmo IP continua entrando', $cod === 302 && strpos($loc, 'dashboard/jovem.php') !== false, "-> {$loc}");
$saida_cli = [];
$cmd_limpar = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($COPIA . '/scripts/jovemconnect.php') . ' limpar-tentativas';
$amb_ant = getenv('JC_TENTATIVAS_DIR');
putenv('JC_TENTATIVAS_DIR=' . $TMP . DIRECTORY_SEPARATOR . 'tentativas');
exec($cmd_limpar, $saida_cli, $rc_limpar);
putenv($amb_ant === false ? 'JC_TENTATIVAS_DIR' : 'JC_TENTATIVAS_DIR=' . $amb_ant);
[$cod, , , $loc] = login($cl, 'jovem', $contas['B']['email'], $senha['B']);
teste('LIM-04', 'comando limpar-tentativas libera a conta', $rc_limpar === 0 && strpos($loc, 'dashboard/jovem.php') !== false, "rc {$rc_limpar} -> {$loc}");

// Troca de senha derruba sessões abertas (SEG-09)
$novo_hash = password_hash(bin2hex(random_bytes(8)) . 'Aa1', PASSWORD_DEFAULT);
$st = $c->prepare('UPDATE jovens SET senha = ? WHERE id = ?');
$st->bind_param('si', $novo_hash, $contas['A']['id']);
$st->execute();
[$cod, , , $loc] = $cj->get('dashboard/jovem.php');
teste('SESS-01', 'sessao aberta antes da troca de senha termina', $cod === 302 && strpos($loc, 'login.php') !== false, "HTTP {$cod} -> {$loc}");
[, , $html] = $cj->get('jovem/login.php');
teste('SESS-02', 'aviso de senha alterada aparece no login', strpos($html, 'senha desta conta foi alterada') !== false);
$h = password_hash($senha['A'], PASSWORD_DEFAULT);
$st->bind_param('si', $h, $contas['A']['id']);
$st->execute();

// Uma identidade por vez (SEG-13)
$ci = new Cliente($BASE, $TMP);
login($ci, 'jovem', $contas['A']['email'], $senha['A']);
[, , $html] = $ci->get('jovem/perfil.php');
[$cod, , , $loc] = $ci->post('empresa/processar_login.php', ['email' => $contas['E']['email'], 'senha' => $senha['E'], 'csrf_token' => token($html)]);
[$cod2, , , $loc2] = $ci->get('dashboard/jovem.php');
teste('IDENT-01', 'login de empresa apaga a identidade de jovem da sessao', strpos($loc, 'dashboard/empresa.php') !== false && $cod2 === 302, "painel do jovem: HTTP {$cod2} -> {$loc2}");

// Currículo: nome imprevisível, troca apaga o anterior, entrega com permissão (SEG-10)
$pdf = $TMP . DIRECTORY_SEPARATOR . 'teste.pdf';
file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
$cu = new Cliente($BASE, $TMP);
login($cu, 'jovem', $contas['A']['email'], $senha['A']);
$nomes_cv = [];
for ($i = 0; $i < 2; $i++) {
    [, , $html] = $cu->get('jovem/perfil.php');
    [$cod, , , $loc] = $cu->pedir('POST', 'uploads/curriculos.php',
        ['csrf_token' => token($html), 'curriculo' => new CURLFile($pdf, 'application/pdf', 'meu cv.pdf')], true);
    $nomes_cv[] = (string) ($c->query('SELECT curriculo FROM jovens WHERE id = ' . $contas['A']['id'])->fetch_assoc()['curriculo'] ?? '');
}
$padrao_cv = '/^curriculo_' . $contas['A']['id'] . '_[a-f0-9]{16}\.pdf$/';
teste('CV-01', 'envio do curriculo funciona', strpos($loc, 'saved=1') !== false, "-> {$loc}");
teste('CV-02', 'nome gravado e imprevisivel (curriculo_<id>_<aleatorio>.pdf)', (bool) preg_match($padrao_cv, $nomes_cv[1]));
teste('CV-03', 'novo envio gera outro nome e apaga o arquivo anterior',
      $nomes_cv[0] !== $nomes_cv[1] && !is_file($COPIA . '/uploads/curriculos/' . $nomes_cv[0]) && is_file($COPIA . '/uploads/curriculos/' . $nomes_cv[1]));
[$cod, $cab] = $cu->get('uploads/ver_curriculo.php?id=' . $contas['A']['id']);
teste('CV-04', 'o proprio jovem baixa o curriculo (PDF)', $cod === 200 && (bool) preg_match('/^Content-Type:\s*application\/pdf/mi', $cab), "HTTP {$cod}");
$cb = new Cliente($BASE, $TMP);
login($cb, 'jovem', $contas['B']['email'], $senha['B']);
[$cod] = $cb->get('uploads/ver_curriculo.php?id=' . $contas['A']['id']);
teste('CV-05', 'outro jovem nao baixa o curriculo (404)', $cod === 404, "HTTP {$cod}");
file_put_contents($pdf, "<?php echo 'x';");
[, , $html] = $cu->get('jovem/perfil.php');
[$cod, , , $loc] = $cu->pedir('POST', 'uploads/curriculos.php', ['csrf_token' => token($html), 'curriculo' => new CURLFile($pdf, 'application/pdf', 'falso.pdf')], true);
teste('CV-06', 'arquivo que nao e PDF e recusado', strpos($loc, 'saved=2') !== false, "-> {$loc}");

// Código de verificação: 5 erros cancelam o código (SEG-02)
$c->query("UPDATE jovens SET email_verificado = 0, codigo_verificacao = '" . sprintf('%06d', random_int(0, 999999))
          . "', expiracao_codigo = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = " . $contas['B']['id']);
for ($i = 1; $i <= 5; $i++) {
    [, , $html] = $cb->get('verificacoes/verificar_email.php');
    $cb->post('verificacoes/verificar_email.php', ['acao_verificar' => '1', 'codigo' => 'x' . $i . '0000', 'csrf_token' => token($html)]);
}
$r = $c->query('SELECT codigo_verificacao FROM jovens WHERE id = ' . $contas['B']['id'])->fetch_assoc();
teste('COD-01', 'codigo de verificacao cancelado apos 5 erros', $r['codigo_verificacao'] === null);

// Logout
[$cod, , , $loc] = $cu->get('logout.php');
[$cod2] = $cu->get('dashboard/jovem.php');
teste('SAIR-01', 'logout encerra a sessao', $cod === 302 && $cod2 === 302, "logout HTTP {$cod}; painel depois: HTTP {$cod2}");

// Ambiente de produção: conta de demonstração não entra; seed recusado (correção 2.5)
$senha_demo = bin2hex(random_bytes(6)) . 'Aa1';
$email_demo = 'teste.seg.' . $CARIMBO . '@demo.jovemconnect.example';
$cpf_demo = cpf_valido_novo();
$st = $c->prepare("INSERT INTO jovens (nome, email, senha, cpf, perfil_publico, email_verificado) VALUES ('Conta Demo Teste', ?, ?, ?, 0, 1)");
$h = password_hash($senha_demo, PASSWORD_DEFAULT);
$st->bind_param('sss', $email_demo, $h, $cpf_demo);
$st->execute();
$cdd = new Cliente($BASE, $TMP);
[$cod, , , $loc] = login($cdd, 'jovem', $email_demo, $senha_demo);
teste('DEMO-01', 'conta de demonstracao entra no modo normal (apresentacao)', strpos($loc, 'dashboard/jovem.php') !== false, "-> {$loc}");
$porta2 = porta_livre();
$pipes2 = [];
$srv2 = $porta2 ? subir_servidor($porta2, $TMP, array_merge($amb, ['JC_AMBIENTE' => 'producao']), $pipes2) : null;
if ($srv2 === null) {
    teste('DEMO-02', 'servidor de teste em modo producao', false, 'nao subiu');
} else {
    $cdp = new Cliente("http://127.0.0.1:{$porta2}/" . rawurlencode(basename($RAIZ)), $TMP);
    [$cod, , , $loc] = login($cdp, 'jovem', $email_demo, $senha_demo);
    [, , $html] = $cdp->get('jovem/login.php');
    teste('DEMO-02', 'com JC_AMBIENTE=producao a conta de demonstracao NAO entra', strpos($loc, 'dashboard') === false && strpos($html, 'demonstra') !== false, "-> {$loc}");
    [$cod, , , $loc] = login($cdp, 'jovem', $contas['A']['email'], $senha['A']);
    teste('DEMO-03', 'com JC_AMBIENTE=producao uma conta comum entra normalmente', strpos($loc, 'dashboard/jovem.php') !== false, "-> {$loc}");
    parar_servidor($srv2, $pipes2);
}
$amb_ant2 = getenv('JC_AMBIENTE');
putenv('JC_AMBIENTE=producao');
$saida_seed = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($COPIA . '/scripts/jovemconnect.php') . ' banco-seed', $saida_seed, $rc_seed);
putenv($amb_ant2 === false ? 'JC_AMBIENTE' : 'JC_AMBIENTE=' . $amb_ant2);
teste('DEMO-04', 'com JC_AMBIENTE=producao o comando banco-seed recusa', $rc_seed === 3, "codigo {$rc_seed}");

// Avisos/erros do PHP em qualquer resposta
teste('PHP-01', 'nenhuma pagina mostrou aviso/erro do PHP', !$AVISOS_PHP, $AVISOS_PHP ? implode('; ', array_slice($AVISOS_PHP, 0, 5)) : '');

// ── 4. Resumo ───────────────────────────────────────────────────────────────
$aprov = count(array_filter($RESULTADOS, function ($r) { return $r[2]; }));
$reprov = count($RESULTADOS) - $aprov;
diz('');
diz(sprintf('Resumo: %d aprovado(s), %d reprovado(s) de %d.', $aprov, $reprov, count($RESULTADOS)));
diz('Relatorio: logs/regressao_isolada-' . $CARIMBO . '.log');
$fim($reprov ? 1 : 0);
