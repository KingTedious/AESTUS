<?php
/* ============================================================
   includes/ia_endereco.php — endereço do servidor de IA (Flask local)

   Único lugar em que o PHP decide para onde vão as chamadas à IA.
   - Host SEMPRE 127.0.0.1 (a IA só escuta na própria máquina).
   - Porta: a que a própria IA gravou em logs/ia_porta.json ao abrir
     (IA/recomendar.py), desde que esteja na faixa configurada no .env
     (JC_IA_PORTA, padrão 5000; JC_IA_PORTAS_EXTRAS, padrão 5001-5010).
   - O PHP só fala com uma porta depois de CONFIRMAR no /ping que quem
     responde é a IA do JovemConnect (e, pela porta do arquivo, a MESMA
     "instancia" gravada nele). Arquivo desatualizado ou outro programa
     na porta: tenta a porta preferida; sem IA confirmada, devolve null
     e a chamada falha como "IA fora do ar" — dados do site nunca vão
     para um programa desconhecido.
   - Nada vem da requisição HTTP: nenhum parâmetro do navegador muda o
     destino. A pasta logs/ é bloqueada para o navegador (.htaccess).
   Mesmas regras de IA/recomendar.py e IA/verificar_ambiente.py.
   ============================================================ */

require_once __DIR__ . '/env.php';

if (!function_exists('jc_ia_url')) {

    /** [porta preferida, início da faixa extra, fim da faixa extra], validados. */
    function jc_ia_portas(): array {
        $numero = static function ($valor, int $padrao): int {
            $valor = is_string($valor) ? trim($valor) : '';
            return (ctype_digit($valor) && (int) $valor >= 1024 && (int) $valor <= 65535) ? (int) $valor : $padrao;
        };
        $preferida = $numero(getenv('JC_IA_PORTA'), 5000);
        [$inicio, $fim] = [5001, 5010];
        $faixa = getenv('JC_IA_PORTAS_EXTRAS');
        if (is_string($faixa) && preg_match('/^\s*(\d{4,5})\s*-\s*(\d{4,5})\s*$/', $faixa, $m)) {
            [$a, $b] = [(int) $m[1], (int) $m[2]];
            if ($a >= 1024 && $a <= $b && $b <= 65535 && $b - $a < 50) {
                [$inicio, $fim] = [$a, $b];
            }
        }
        return [$preferida, $inicio, $fim];
    }

    /** GET /ping em 127.0.0.1:<porta>: os dados se for a IA do JovemConnect, senão null (sem proxy, ~1 ms). */
    function jc_ia_ping(int $porta): ?array {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init("http://127.0.0.1:{$porta}/ping");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_PROXY          => '',   // nunca passar por proxy
        ]);
        $resp = curl_exec($ch);
        $http = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        $dados = ($http === 200 && is_string($resp)) ? json_decode($resp, true) : null;
        return (is_array($dados) && ($dados['status'] ?? null) === 'ok' && isset($dados['versao'])) ? $dados : null;
    }

    /**
     * "http://127.0.0.1:<porta>" da IA confirmada, ou null se nenhuma IA do JovemConnect
     * respondeu (calculado uma vez por requisição PHP).
     */
    function jc_ia_base(): ?string {
        static $base = false;
        if ($base !== false) {
            return $base;
        }
        [$preferida, $inicio, $fim] = jc_ia_portas();

        // 1) porta gravada pela IA, se válida e se a MESMA instância responder nela
        $arquivo = dirname(__DIR__) . '/logs/ia_porta.json';
        $bruto   = is_file($arquivo) ? @file_get_contents($arquivo, false, null, 0, 4096) : false;
        $dados   = is_string($bruto) ? json_decode($bruto, true) : null;
        $p       = is_array($dados) ? ($dados['porta'] ?? null) : null;
        $inst    = is_array($dados) ? ($dados['instancia'] ?? null) : null;
        if (is_int($p) && ($p === $preferida || ($p >= $inicio && $p <= $fim))
            && is_string($inst) && preg_match('/^[a-f0-9]{32}$/', $inst)) {
            $ping = jc_ia_ping($p);
            if ($ping !== null && ($ping['instancia'] ?? null) === $inst) {
                return $base = "http://127.0.0.1:{$p}";
            }
        }
        // 2) sem arquivo válido: a porta preferida, se nela houver uma IA do JovemConnect
        //    (ex.: IA que não conseguiu gravar o arquivo, ou de versão anterior)
        if (jc_ia_ping($preferida) !== null) {
            return $base = "http://127.0.0.1:{$preferida}";
        }
        return $base = null;
    }

    /**
     * URL de uma rota da IA, ex.: jc_ia_url('/curriculo'); null = IA fora do ar.
     * Com null, curl_init(null) não abre conexão e curl_exec falha na hora (CURLE_URL_MALFORMAT),
     * que todos os consumidores já tratam como "IA indisponível".
     */
    function jc_ia_url(string $rota): ?string {
        $base = jc_ia_base();
        return $base === null ? null : $base . '/' . ltrim($rota, '/');
    }
}
