<?php
/* ============================================================
   IA/assistente_ajax.php — Assistente JovemConnect com IA (Gemini via Flask)

   POST JSON {mensagem, historico: [{papel: "usuario"|"assistente", texto}], pagina}
   + cabeçalho X-CSRF-Token (o mesmo token das outras chamadas da página).

   Resposta:
     {"fonte": "ia", "resposta": "...", "mascarado": bool}  → texto gerado pela IA
     {"fonte": "local", "mascarado": bool}                   → IA indisponível: o
        navegador usa as respostas prontas (includes/assistente.php)
     HTTP 429 {"erro": "..."}  → limite de mensagens
     HTTP 400/403/405 {"erro": "..."}

   Proteções:
   - limite de mensagens por sessão (minuto/hora) e por endereço IP;
   - tamanho máximo da mensagem e do histórico;
   - dados pessoais digitados (e-mail, CPF, CNPJ, telefone, CEP) são
     trocados por "[dado removido]" ANTES de sair do servidor;
   - para a IA vão só a mensagem, as últimas falas da conversa aberta e
     o tipo de página e de perfil (visitante/jovem/empresa) — nunca nome,
     e-mail, IDs ou dados da conta;
   - o conteúdo das conversas NÃO é gravado em banco nem em log.
   ============================================================ */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/csrf.php';      // sessão endurecida + CSRF
require_once dirname(__DIR__) . '/includes/env.php';       // .env (JC_LOG_DIR etc.)
require_once dirname(__DIR__) . '/includes/logger.php';
require_once dirname(__DIR__) . '/includes/match.php';     // jc_flask_post() (mesmo cliente HTTP do Match)

const JC_ASSIST_MSG_MAX       = 500;   // caracteres por mensagem (igual ao maxlength do campo)
const JC_ASSIST_HIST_ITENS    = 6;     // falas anteriores enviadas à IA
const JC_ASSIST_HIST_MAX      = 600;   // caracteres por fala anterior
const JC_ASSIST_LIMITE_MINUTO = 8;     // por sessão
const JC_ASSIST_LIMITE_HORA   = 60;    // por sessão
const JC_ASSIST_LIMITE_IP_MIN = 40;    // por endereço IP (várias sessões; ex.: laboratório da escola)

function jc_assist_sair(int $codigo, array $corpo): never {
    http_response_code($codigo);
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jc_assist_sair(405, ['erro' => 'Método não permitido.']);
}
csrf_require(true);

$entrada = json_decode((string) file_get_contents('php://input', false, null, 0, 20000), true);
if (!is_array($entrada) || !is_string($entrada['mensagem'] ?? null)) {
    jc_assist_sair(400, ['erro' => 'Mensagem inválida.']);
}

/** Texto limpo: sem caracteres de controle, espaços normalizados, cortado no limite. */
function jc_assist_texto(string $t, int $max): string {
    $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $t) ?? '';
    $t = trim(preg_replace('/[ \t]+/u', ' ', $t) ?? '');
    return mb_substr($t, 0, $max, 'UTF-8');
}

/** Troca dados pessoais por "[dado removido]". $houve = true se trocou algo. */
function jc_assist_mascarar(string $t, bool &$houve): string {
    $padroes = [
        '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',                       // e-mail
        '/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/u',                         // CNPJ
        '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/u',                                 // CPF
        '/(?:\+?55\s?)?\(?\b\d{2}\)?\s?9?\d{4}[\s\-]?\d{4}\b/u',               // telefone
        '/\b\d{5}-\d{3}\b/u',                                                  // CEP
        '/\b\d{8,}\b/u',                                                       // outros números longos
    ];
    $novo = preg_replace($padroes, '[dado removido]', $t) ?? $t;
    if ($novo !== $t) {
        $houve = true;
    }
    return $novo;
}

$mascarado = false;
$mensagem  = jc_assist_mascarar(jc_assist_texto($entrada['mensagem'], JC_ASSIST_MSG_MAX), $mascarado);
if ($mensagem === '') {
    jc_assist_sair(400, ['erro' => 'Digite uma mensagem.']);
}
if (mb_strlen((string) $entrada['mensagem'], 'UTF-8') > JC_ASSIST_MSG_MAX + 50) {
    jc_assist_sair(400, ['erro' => 'Mensagem muito longa (máximo ' . JC_ASSIST_MSG_MAX . ' caracteres).']);
}

$historico = [];
foreach (array_slice(is_array($entrada['historico'] ?? null) ? $entrada['historico'] : [], -JC_ASSIST_HIST_ITENS) as $fala) {
    if (!is_array($fala) || !is_string($fala['texto'] ?? null)) {
        continue;
    }
    $t = jc_assist_mascarar(jc_assist_texto($fala['texto'], JC_ASSIST_HIST_MAX), $mascarado);
    if ($t !== '') {
        $historico[] = ['papel' => ($fala['papel'] ?? '') === 'assistente' ? 'assistente' : 'usuario', 'texto' => $t];
    }
}

$pagina = is_string($entrada['pagina'] ?? null) && preg_match('/^[a-z]{2,20}$/', $entrada['pagina']) ? $entrada['pagina'] : 'outra';
$perfil = isset($_SESSION['jovem_id']) ? 'jovem' : (isset($_SESSION['empresa_id']) ? 'empresa' : 'visitante');

// ── Limite de mensagens ─────────────────────────────────────────────────────
$agora = time();
$marcas = array_values(array_filter(
    is_array($_SESSION['jc_assist'] ?? null) ? $_SESSION['jc_assist'] : [],
    fn($t) => is_int($t) && $t > $agora - 3600
));
$no_minuto = count(array_filter($marcas, fn($t) => $t > $agora - 60));

// por IP: contador em arquivo temporário (nome = hash do IP, nada legível)
$ip_ok = true;
$arq_ip = rtrim(sys_get_temp_dir(), '/\\') . '/jc_assist_' . hash('sha256', 'jc|' . ($_SERVER['REMOTE_ADDR'] ?? '')) . '.json';
$fh = @fopen($arq_ip, 'c+');
if ($fh && flock($fh, LOCK_EX)) {
    $lista = json_decode((string) stream_get_contents($fh), true);
    $lista = array_values(array_filter(is_array($lista) ? $lista : [], fn($t) => is_int($t) && $t > $agora - 60));
    if (count($lista) >= JC_ASSIST_LIMITE_IP_MIN) {
        $ip_ok = false;
    } else {
        $lista[] = $agora;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($lista));
    }
    flock($fh, LOCK_UN);
}
if ($fh) {
    fclose($fh);
}

if (!$ip_ok || $no_minuto >= JC_ASSIST_LIMITE_MINUTO || count($marcas) >= JC_ASSIST_LIMITE_HORA) {
    jc_log_seguranca('assistente.limite', 'aviso', ['escopo' => !$ip_ok ? 'ip' : ($no_minuto >= JC_ASSIST_LIMITE_MINUTO ? 'minuto' : 'hora')]);
    session_write_close();
    jc_assist_sair(429, ['erro' => 'Muitas mensagens em pouco tempo. Aguarde um minuto e tente de novo.']);
}
$marcas[] = $agora;
$_SESSION['jc_assist'] = $marcas;
session_write_close();   // a espera pela IA não trava as outras páginas do usuário

// ── IA (Flask → Gemini) ─────────────────────────────────────────────────────
$t0 = microtime(true);
$resp = jc_flask_post('/assistente', [
    'mensagem'  => $mensagem,
    'historico' => $historico,
    'contexto'  => ['pagina' => $pagina, 'perfil' => $perfil],
], 20);
$ms = intval((microtime(true) - $t0) * 1000);

if (is_array($resp) && ($resp['disponivel'] ?? false) === true && is_string($resp['resposta'] ?? null) && trim($resp['resposta']) !== '') {
    jc_log('assistente.resposta', 'info', ['fonte' => 'ia', 'ms' => $ms, 'mascarado' => $mascarado]);
    jc_assist_sair(200, ['fonte' => 'ia', 'resposta' => mb_substr(trim($resp['resposta']), 0, 1500, 'UTF-8'), 'mascarado' => $mascarado]);
}

$motivo = is_array($resp) ? (is_string($resp['motivo'] ?? null) ? mb_substr($resp['motivo'], 0, 30) : 'resposta_invalida') : 'flask_indisponivel';

// Sem chave ou com o Flask fora do ar o Gemini nem foi chamado: a mensagem não
// conta no limite (a pessoa usa as respostas prontas à vontade).
if (in_array($motivo, ['sem_chave', 'flask_indisponivel'], true)) {
    jc_session_start();
    $marcas = is_array($_SESSION['jc_assist'] ?? null) ? $_SESSION['jc_assist'] : [];
    $pos = array_search($agora, $marcas, true);
    if ($pos !== false) {
        array_splice($marcas, (int) $pos, 1);
        $_SESSION['jc_assist'] = $marcas;
    }
    session_write_close();
    $fh = @fopen($arq_ip, 'c+');
    if ($fh && flock($fh, LOCK_EX)) {
        $lista = json_decode((string) stream_get_contents($fh), true);
        $lista = is_array($lista) ? $lista : [];
        $pos = array_search($agora, $lista, true);
        if ($pos !== false) {
            array_splice($lista, (int) $pos, 1);
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode(array_values($lista)));
        }
        flock($fh, LOCK_UN);
    }
    if ($fh) {
        fclose($fh);
    }
}
jc_log('assistente.resposta', 'info', ['fonte' => 'local', 'motivo' => $motivo, 'ms' => $ms, 'mascarado' => $mascarado]);
jc_assist_sair(200, ['fonte' => 'local', 'mascarado' => $mascarado]);
