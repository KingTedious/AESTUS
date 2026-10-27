<?php
require_once __DIR__ . '/compat.php';   // PHP 7.4/8.0: str_contains etc. (includes/compat.php)
/* ============================================================
   includes/logger.php — registro de eventos (logs) do JovemConnect

   Cada evento vira UMA linha JSON num arquivo de log:
     {"ts":"2026-10-10T09:30:00-03:00","nivel":"info","componente":"php",
      "evento":"login.sucesso","req":"a1b2c3d4e5f60718","usuario":"jovem:12",...}

   Arquivos (pasta definida por JC_LOG_DIR no .env; padrão: logs/ do projeto,
   bloqueada para o navegador pelo logs/.htaccess):
     app.log        → funcionamento: erros, Match, IA, Flask, vagas, candidaturas
     seguranca.log  → auditoria: login, cadastro, conta, CSRF, acesso negado
   O Flask grava ia.log na mesma pasta (IA/recomendar.py).

   Proteções:
   - Campos sensíveis (senha, token, CPF, telefone, e-mail, CEP, chave...)
     são trocados por "[removido]" antes de gravar, pelo NOME do campo.
   - Textos são cortados (300 caracteres) e não podem quebrar a linha:
     o JSON escapa \n e \r, então ninguém consegue "inventar" um evento.
   - IP só aparece em eventos de segurança e anonimizado (último bloco = 0).
   - Rotação: um arquivo por dia (e um novo se passar de 2 MB) → o atual
     vira app-AAAAMMDD-HHMMSS.log. Arquivos antigos com mais de 30 dias são
     apagados na próxima gravação (no máximo 60 por tipo). Configurável:
     JC_LOG_MAX_MB, JC_LOG_DIAS.
   - Se não der para gravar (pasta sem permissão, disco cheio), o site
     continua funcionando: a falha vai só para o error_log do PHP.

   Uso:
     jc_log('vaga.criada', 'info', ['vaga' => $vaga_id]);
     jc_log_seguranca('login.falha', 'aviso', ['tipo' => 'jovem']);
     jc_log_excecao($e);              // erros inesperados
     registrar_log('texto', 'ERROR'); // forma antiga — continua funcionando
   ============================================================ */

if (!function_exists('jc_log')) {

    /** Identificador desta requisição (correlaciona PHP → Flask → Gemini). Não é a sessão. */
    function jc_req_id(): string {
        static $id = null;
        if ($id === null) {
            $recebido = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
            $id = (is_string($recebido) && preg_match('/^[a-f0-9]{16,32}$/', $recebido))
                ? $recebido
                : bin2hex(random_bytes(8));
        }
        return $id;
    }

    /** Lê uma configuração do ambiente (.env já carregado por includes/env.php). */
    function jc_log_config(string $nome, string $padrao): string {
        $v = getenv($nome);
        return ($v === false || $v === '') ? $padrao : $v;
    }

    /** Pasta dos logs (criada se não existir). */
    function jc_log_dir(): string {
        $dir = jc_log_config('JC_LOG_DIR', dirname(__DIR__) . '/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return rtrim($dir, '/\\');
    }

    /** Nomes de campo que NUNCA são gravados (comparação sem maiúsculas/acentos). */
    function jc_log_campo_proibido(string $campo): bool {
        $c = strtolower($campo);
        foreach (['senha', 'password', 'pass', 'token', 'csrf', 'cookie', 'sess', 'cpf', 'cnpj',
                  'telefone', 'phone', 'email', 'e-mail', 'cep', 'endereco', 'logradouro', 'numero',
                  'chave', 'key', 'secret', 'segredo', 'codigo', 'curriculo', 'mensagem', 'resposta',
                  'prompt', 'nascimento', 'authorization'] as $p) {
            if (str_contains($c, $p)) {
                return true;
            }
        }
        return false;
    }

    /** Limpa um valor: tira caracteres de controle, corta e remove campos proibidos. */
    function jc_log_limpar($valor, int $nivel = 0) {
        if (is_array($valor)) {
            if ($nivel > 3) {
                return '[...]';
            }
            $saida = [];
            foreach (array_slice($valor, 0, 30, true) as $k => $v) {
                if ($k === 'elo' && is_string($v) && preg_match('/^[0-9a-f]{16}$/', $v)) {
                    $saida[$k] = $v;   // começo do hash da auditoria (Stage 7): gerado pelo sistema; não passa pela máscara de números
                    continue;
                }
                $saida[$k] = (is_string($k) && jc_log_campo_proibido($k)) ? '[removido]' : jc_log_limpar($v, $nivel + 1);
            }
            return $saida;
        }
        if (is_bool($valor) || is_int($valor) || is_float($valor) || $valor === null) {
            return $valor;
        }
        $texto = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $valor) ?? '';
        // e-mails e sequências longas de dígitos (CPF, telefone, cartão) que escaparem pelo texto
        $texto = preg_replace('/[^\s@"\']+@[^\s@"\']+/', '[e-mail]', $texto) ?? '';
        $texto = preg_replace('/\d[\d.\-\/ ]{8,}\d/', '[número]', $texto) ?? '';
        return mb_substr($texto, 0, 300);
    }

    /** Quem fez a ação (tipo:id interno) — sem nome nem e-mail. */
    function jc_log_usuario(): string {
        // $_SESSION continua legível depois de session_write_close()
        if (isset($_SESSION) && is_array($_SESSION)) {
            if (isset($_SESSION['adm_id'])) {
                return 'admin:' . intval($_SESSION['adm_id']);   // Central administrativa (Stage 7)
            }
            if (isset($_SESSION['jovem_id'])) {
                return 'jovem:' . intval($_SESSION['jovem_id']);
            }
            if (isset($_SESSION['empresa_id'])) {
                return 'empresa:' . intval($_SESSION['empresa_id']);
            }
        }
        return 'visitante';
    }

    /** IP anonimizado (IPv4: último bloco = 0; IPv6: só os 3 primeiros grupos). */
    function jc_log_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return implode(':', array_slice(explode(':', $ip), 0, 3)) . '::';
        }
        return '-';
    }

    /** Rotação diária ou por tamanho + retenção por idade (e quantidade). */
    function jc_log_rotacionar(string $arquivo): void {
        $max = max(1, intval(jc_log_config('JC_LOG_MAX_MB', '2'))) * 1024 * 1024;
        clearstatcache(true, $arquivo);
        if (!is_file($arquivo)) {
            return;
        }
        // Data do primeiro evento do arquivo (início da linha: {"ts":"AAAA-MM-DD...)
        $inicio = (string) @file_get_contents($arquivo, false, null, 0, 20);
        $dia    = preg_match('/"ts":"(\d{4}-\d{2}-\d{2})/', $inicio, $m) ? $m[1] : date('Y-m-d');
        if (filesize($arquivo) < $max && $dia === date('Y-m-d')) {
            return;
        }
        $base = substr($arquivo, 0, -4);   // tira ".log"
        @rename($arquivo, $base . '-' . date('Ymd-His') . '.log');
        $antigos = glob($base . '-*.log') ?: [];
        rsort($antigos);                    // nomes com data: mais novo primeiro
        $dias = max(1, intval(jc_log_config('JC_LOG_DIAS', '30')));
        foreach ($antigos as $i => $velho) {
            if ($i >= 60 || @filemtime($velho) < time() - $dias * 86400) {
                @unlink($velho);
            }
        }
    }

    /**
     * Grava um evento.
     * @param string $evento  nome curto "area.acao" (ex.: "vaga.criada")
     * @param string $nivel   debug | info | aviso | erro
     * @param array  $dados   detalhes NÃO sensíveis (ids, contagens, motivo, duração)
     * @param string $arquivo 'app' ou 'seguranca'
     */
    function jc_log(string $evento, string $nivel = 'info', array $dados = [], string $arquivo = 'app'): void {
        try {
            $linha = [
                'ts'         => date('c'),
                'nivel'      => in_array($nivel, ['debug', 'info', 'aviso', 'erro'], true) ? $nivel : 'info',
                'componente' => 'php',
                'evento'     => preg_replace('/[^a-z0-9_.\-]/i', '', $evento) ?: 'evento',
                'req'        => jc_req_id(),
                'usuario'    => jc_log_usuario(),
                'pagina'     => jc_log_limpar(strtok($_SERVER['SCRIPT_NAME'] ?? '-', '?')),
            ];
            if ($arquivo === 'seguranca') {
                $linha['ip'] = jc_log_ip();
            }
            if ($dados) {
                $linha['dados'] = jc_log_limpar($dados);
            }
            $json = json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            $destino = jc_log_dir() . '/' . ($arquivo === 'seguranca' ? 'seguranca' : 'app') . '.log';
            jc_log_rotacionar($destino);
            if (@file_put_contents($destino, $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
                error_log('[JovemConnect] não foi possível gravar o log em ' . basename($destino));
            }
        } catch (Throwable $e) {
            error_log('[JovemConnect] falha no logger: ' . get_class($e));
        }
    }

    /** Evento de segurança/auditoria (vai para seguranca.log, com IP anonimizado). */
    function jc_log_seguranca(string $evento, string $nivel = 'info', array $dados = []): void {
        jc_log($evento, $nivel, $dados, 'seguranca');
    }

    /**
     * Erro inesperado. Não grava a mensagem crua de erros de banco (pode ter SQL e
     * dados digitados) — só o código; nas demais, a mensagem passa pela limpeza.
     */
    function jc_log_excecao(Throwable $e, string $evento = 'erro.inesperado'): void {
        $dados = [
            'tipo'  => get_class($e),
            'local' => basename($e->getFile()) . ':' . $e->getLine(),
        ];
        if ($e instanceof mysqli_sql_exception) {
            $dados['errno'] = $e->getCode();
            $dados['sqlstate'] = method_exists($e, 'getSqlState') ? $e->getSqlState() : '';
        } else {
            // valores entre aspas costumam ser dados → "?"
            $dados['detalhe'] = preg_replace('/([\'"]).*?\1/u', '?', $e->getMessage());
        }
        jc_log($evento, 'erro', $dados);
    }

    /** Forma antiga (texto livre) — mantida para os arquivos que ainda a usam. */
    function registrar_log($mensagem, $nivel = 'INFO'): void {
        $mapa = ['ERROR' => 'erro', 'WARNING' => 'aviso', 'WARN' => 'aviso', 'DEBUG' => 'debug'];
        jc_log('legado', $mapa[strtoupper((string) $nivel)] ?? 'info', ['texto' => (string) $mensagem]);
    }
}
