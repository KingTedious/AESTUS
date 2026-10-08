<?php
/* ============================================================
   IA/avaliar_ajax.php — Endpoint JSON para o botão "Avaliar Match"

   Chamado via fetch() pelo JS de:
     - dashboard/jovem.php    → jovem avalia uma vaga
     - dashboard/empresa.php   → empresa avalia um candidato
     - jovem/perfil.php        → IA Advisor analisa o currículo
     - Vagas/avaliar.php       → análise detalhada (Stage 5.1: actions
                                 "analise" e "contexto")

   Retorna JSON sempre — nunca HTML.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF
header('Content-Type: application/json; charset=utf-8');

// Sem sessão → 401
if (!isset($_SESSION['jovem_id']) && !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

// Só aceita POST com JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/match.php";   // Stage 5.1: algoritmo em PHP + local efetivo
csrf_require(true);

// Lê o body JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido.']);
    exit;
}

$action = $input['action'] ?? 'match'; // 'match' ou 'curriculo'

/* ============================================================
   ACTIONS: match / analise / contexto — compatibilidade jovem ↔ vaga
   Stage 5.1:
   - match    → nota (como antes). O Flask continua sendo chamado para
                pares novos; fora do ar, a nota vem da cópia fiel do
                algoritmo em PHP (includes/match.php) — antes dava
                "Servidor de IA indisponível". O local da vaga agora é o
                local EFETIVO (includes/locais.php).
   - analise  → composição da nota por critério (pontos reais), pontos
                fortes, o que reduziu a nota e o local de trabalho usado.
   - contexto → análise contextual do Gemini (opcional, via Flask);
                nunca altera a nota. Indisponível → {"disponivel": false}.
   ============================================================ */
if (in_array($action, ['match', 'analise', 'contexto'], true)) {

    $vaga_id = intval($input['vaga_id'] ?? 0);

    // ── Autorização derivada da SESSÃO, nunca do jovem_id enviado pelo frontend ──
    if (isset($_SESSION['jovem_id'])) {
        // Jovem só pode avaliar o próprio match, mesmo que outro jovem_id seja enviado.
        $jovem_id = intval($_SESSION['jovem_id']);
        $quem     = 'jovem';

    } elseif (isset($_SESSION['empresa_id'])) {
        // Empresa escolhe QUAL candidato avaliar, mas só em uma vaga que seja dela
        // (ownership check — mesmo padrão usado em Vagas/candidatos.php).
        $jovem_id   = intval($input['jovem_id'] ?? 0);
        $empresa_id = intval($_SESSION['empresa_id']);
        $quem       = 'empresa';

        $stmt_own = $conn->prepare("SELECT id FROM vagas WHERE id = ? AND empresa_id = ? LIMIT 1");
        $stmt_own->bind_param("ii", $vaga_id, $empresa_id);
        $stmt_own->execute();
        $vaga_pertence_a_empresa = $stmt_own->get_result()->fetch_assoc();
        $stmt_own->close();

        if (!$vaga_pertence_a_empresa) {
            http_response_code(403);
            echo json_encode(['error' => 'Esta vaga não pertence à sua empresa.']);
            exit;
        }

        // Stage 5.1: a análise detalhada só mostra perfis públicos (mesma regra de Vagas/avaliar.php)
        if ($action !== 'match') {
            $stmt_pub = $conn->prepare("SELECT id FROM jovens WHERE id = ? AND perfil_publico = 1 LIMIT 1");
            $stmt_pub->bind_param("i", $jovem_id);
            $stmt_pub->execute();
            $publico = $stmt_pub->get_result()->fetch_assoc();
            $stmt_pub->close();
            if (!$publico) {
                http_response_code(404);
                echo json_encode(['error' => 'Candidato não encontrado.']);
                exit;
            }
        }

    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso não autorizado.']);
        exit;
    }

    if ($jovem_id <= 0 || $vaga_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'IDs inválidos.']);
        exit;
    }

    // ── Dados do jovem, da vaga e do local efetivo da vaga ───────────────────
    $dados = jc_match_carregar($conn, $jovem_id, $vaga_id);
    if (!$dados) {
        http_response_code(404);
        echo json_encode(['error' => 'Jovem ou vaga não encontrados.']);
        exit;
    }
    // Match tradicional (mesmo algoritmo e pesos do Flask)
    $calc = jc_match_calcular($dados['entrada']);

    // Nota guardada (cache) — lida nas três ações
    $stmt = $conn->prepare("SELECT score, analise FROM avaliacoes_ia WHERE jovem_id = ? AND vaga_id = ? LIMIT 1");
    $stmt->bind_param("ii", $jovem_id, $vaga_id);
    $stmt->execute();
    $cache = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $guardar = function (int $score, string $analise) use ($conn, $jovem_id, $vaga_id): void {
        $stmt_ins = $conn->prepare(
            "INSERT INTO avaliacoes_ia (jovem_id, vaga_id, score, analise)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE score = ?, analise = ?"
        );
        $stmt_ins->bind_param("iiisis", $jovem_id, $vaga_id, $score, $analise, $score, $analise);
        $stmt_ins->execute();
        $stmt_ins->close();
    };

    /* ── ACTION: match ─────────────────────────────────────────────────────── */
    if ($action === 'match') {

        // Cache válido: mesma nota que o algoritmo dá com os dados de hoje
        if ($cache && intval($cache['score']) === $calc['score']) {
            echo json_encode([
                'score'    => intval($cache['score']),
                'analise'  => $cache['analise'],
                'do_cache' => true,
            ]);
            exit;
        }

        // Stage 5.1: dados do perfil/vaga mudaram desde o cálculo guardado → atualiza
        // (antes a nota antiga ficava para sempre e não batia com a análise detalhada)
        if ($cache) {
            $analise = jc_match_texto_estatico($calc['score']);
            $guardar($calc['score'], $analise);
            echo json_encode([
                'score'      => $calc['score'],
                'analise'    => $analise,
                'motivos'    => $calc['motivos'],
                'do_cache'   => false,
                'atualizado' => true,
            ]);
            exit;
        }

        // Par novo: Flask (como antes); fora do ar → mesmo cálculo em PHP
        session_write_close();   // a espera pelo Flask não trava as outras páginas do usuário
        $resposta = jc_flask_post('/avaliar', $dados['entrada'], 6);
        if (is_array($resposta) && isset($resposta['score'])) {
            $score   = intval($resposta['score']);
            $analise = is_string($resposta['analise'] ?? null) ? $resposta['analise'] : jc_match_texto_estatico($score);
            $motivos = is_array($resposta['motivos'] ?? null) ? $resposta['motivos'] : [];
            $fonte   = 'flask';
            if ($score !== $calc['score']) {
                error_log("[JovemConnect] Match: Flask={$score} PHP={$calc['score']} (jovem {$jovem_id}, vaga {$vaga_id})");
            }
        } else {
            $score   = $calc['score'];
            $analise = jc_match_texto_estatico($score);
            $motivos = $calc['motivos'];
            $fonte   = 'local';
        }

        $guardar($score, $analise);
        echo json_encode([
            'score'    => $score,
            'analise'  => $analise,
            'motivos'  => $motivos,
            'do_cache' => false,
            'fonte'    => $fonte,
        ]);
        exit;
    }

    $ctx = [
        'quem'           => $quem,
        'cidade_jovem'   => $dados['jovem']['cidade'] ?? '',
        'uf_jovem'       => $dados['jovem']['estado'] ?? '',
        'local'          => $dados['local'],
        'area_interesse' => $dados['jovem']['area_interesse'] ?? '',
        'area_vaga'      => $dados['vaga']['area'] ?? '',
        'curso'          => $dados['jovem']['escolaridade_curso'] ?? '',
        'tipo_contrato'  => $dados['vaga']['tipo_contrato'] ?? '',
    ];
    $categorias = jc_match_categorias($calc, $ctx);
    $ajuste     = jc_match_ajuste($calc, $quem);

    /* ── ACTION: analise ───────────────────────────────────────────────────── */
    if ($action === 'analise') {
        // a nota guardada passa a ser a mesma mostrada aqui (inline e detalhe batem)
        if (!$cache || intval($cache['score']) !== $calc['score']) {
            $guardar($calc['score'], jc_match_texto_estatico($calc['score']));
        }
        $destaques = jc_match_destaques($categorias, $ajuste);
        $local     = $dados['local'];
        echo json_encode([
            'score'      => $calc['score'],
            'rotulo'     => jc_match_rotulo($calc['score']),
            'texto'      => jc_match_texto_estatico($calc['score']),
            'categorias' => $categorias,
            'ajuste'     => $ajuste,
            'fortes'     => $destaques['fortes'],
            'reduziram'  => $destaques['reduziram'],
            'local'      => [
                'origem'   => $local['origem'] ?? 'indisponivel',
                'rotulo'   => $local['rotulo'] ?? 'Local de trabalho não informado',
                'texto'    => $local['texto'] ?? '',
                'nivel'    => $local['nivel'] ?? 'indisponivel',
                'faltando' => $local['faltando'] ?? [],
            ],
            'limites'    => [
                'A localização compara a cidade do perfil com a cidade do local de trabalho.',
                'O JovemConnect não calcula distância em quilômetros nem tem dados de transporte público ou tempo de deslocamento.',
                'Habilidades e experiências não entram na nota; podem aparecer só na análise contextual.',
            ],
        ]);
        exit;
    }

    /* ── ACTION: contexto (Gemini, opcional) ───────────────────────────────── */
    // Limite simples por sessão (protege a cota da API; o Flask também guarda
    // cada análise em cache por algumas horas). Passou do limite → Match tradicional.
    $agora    = time();
    $chamadas = array_values(array_filter(
        is_array($_SESSION['jc_ctx_chamadas'] ?? null) ? $_SESSION['jc_ctx_chamadas'] : [],
        fn($t) => is_int($t) && $t > $agora - 600
    ));
    if (count($chamadas) >= 20) {
        echo json_encode(['disponivel' => false]);
        exit;
    }
    $chamadas[] = $agora;
    $_SESSION['jc_ctx_chamadas'] = $chamadas;
    session_write_close();   // a espera pela IA não trava as outras páginas do usuário

    $payload  = jc_match_payload_ia($conn, $jovem_id, $vaga_id, $dados, $calc, $categorias, $ajuste, $quem);
    $resposta = jc_flask_post('/analise_contextual', $payload, 20);
    $analise  = (is_array($resposta) && ($resposta['disponivel'] ?? false) === true)
        ? jc_match_validar_ia($resposta['analise'] ?? null)
        : null;
    echo json_encode($analise !== null
        ? ['disponivel' => true, 'analise' => $analise]
        : ['disponivel' => false]);
    exit;
}


/* ============================================================
   ACTION: curriculo — análise do perfil do jovem pela IA
   ============================================================ */
if ($action === 'curriculo') {

    // Autoanálise de currículo — somente o próprio jovem logado pode usar esta ação.
    if (!isset($_SESSION['jovem_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso não autorizado.']);
        exit;
    }
    $jovem_id = intval($_SESSION['jovem_id']);

    // Busca dados completos do jovem
    $stmt_j = $conn->prepare(
        "SELECT resumo_profissional FROM jovens WHERE id = ? LIMIT 1"
    );
    $stmt_j->bind_param("i", $jovem_id);
    $stmt_j->execute();
    $jovem = $stmt_j->get_result()->fetch_assoc();
    $stmt_j->close();

    // Habilidades
    $stmt_h = $conn->prepare(
        "SELECT habilidade FROM habilidades_jovem WHERE jovem_id = ?"
    );
    $stmt_h->bind_param("i", $jovem_id);
    $stmt_h->execute();
    $habs_raw  = $stmt_h->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_h->close();
    $habilidades = array_column($habs_raw, 'habilidade');

    // Formações
    $stmt_f = $conn->prepare(
        "SELECT titulo FROM formacao_jovem WHERE jovem_id = ?"
    );
    $stmt_f->bind_param("i", $jovem_id);
    $stmt_f->execute();
    $formacoes = $stmt_f->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_f->close();

    // Payload para o Flask
    $payload = [
        'resumo'      => $jovem['resumo_profissional'] ?? '',
        'habilidades' => $habilidades,
        'formacoes'   => $formacoes,
    ];

    // Chama Flask
    $ch = curl_init("http://127.0.0.1:5000/curriculo");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $result = curl_exec($ch);
    $errno  = curl_errno($ch);
    curl_close($ch);

    if ($errno || $result === false) {
        http_response_code(503);
        echo json_encode(['error' => 'Servidor de IA indisponível.']);
        exit;
    }

    $resposta = json_decode($result, true);

    echo json_encode([
        'mensagem'  => $resposta['mensagem']  ?? '',
        'sugestoes' => $resposta['sugestoes'] ?? [],
    ]);
    exit;
}

// Ação desconhecida
http_response_code(400);
echo json_encode(['error' => "Ação '{$action}' desconhecida."]);