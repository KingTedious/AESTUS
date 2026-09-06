<?php
/* ============================================================
   IA/avaliar_ajax.php — Endpoint JSON para o botão "Avaliar Match"

   Chamado via fetch() pelo JS de:
     - dashboard/jovem.php    → jovem avalia uma vaga
     - dashboard/empresa.php   → empresa avalia um candidato
     - jovem/perfil.php        → IA Advisor analisa o currículo

   Retorna JSON sempre — nunca HTML.
   ============================================================ */
session_start();
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

// Lê o body JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido.']);
    exit;
}

$action = $input['action'] ?? 'match'; // 'match' ou 'curriculo'

/* ============================================================
   ACTION: match — compatibilidade jovem ↔ vaga
   ============================================================ */
if ($action === 'match') {

    $jovem_id = intval($input['jovem_id'] ?? $_SESSION['jovem_id'] ?? 0);
    $vaga_id  = intval($input['vaga_id']  ?? 0);

    if ($jovem_id <= 0 || $vaga_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'IDs inválidos.']);
        exit;
    }

    // Usuário empresa não pode avaliar outra empresa
    if (!isset($_SESSION['jovem_id']) && !isset($_SESSION['empresa_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso não autorizado.']);
        exit;
    }

    // ── Verifica cache ────────────────────────────────────────────────────────
    $stmt = $conn->prepare(
        "SELECT score, analise FROM avaliacoes_ia
         WHERE jovem_id = ? AND vaga_id = ? LIMIT 1"
    );
    $stmt->bind_param("ii", $jovem_id, $vaga_id);
    $stmt->execute();
    $cache = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($cache) {
        echo json_encode([
            'score'    => intval($cache['score']),
            'analise'  => $cache['analise'],
            'do_cache' => true,
        ]);
        exit;
    }

    // ── Busca dados do jovem ──────────────────────────────────────────────────
    $stmt_j = $conn->prepare(
        "SELECT cidade, area_interesse,
                escolaridade_tipo, escolaridade_curso, escolaridade_status
         FROM jovens WHERE id = ? LIMIT 1"
    );
    $stmt_j->bind_param("i", $jovem_id);
    $stmt_j->execute();
    $jovem = $stmt_j->get_result()->fetch_assoc();
    $stmt_j->close();

    // ── Busca dados da vaga ───────────────────────────────────────────────────
    $stmt_v = $conn->prepare(
        "SELECT cidade, area, tipo_contrato, descricao FROM vagas WHERE id = ? LIMIT 1"
    );
    $stmt_v->bind_param("i", $vaga_id);
    $stmt_v->execute();
    $vaga = $stmt_v->get_result()->fetch_assoc();
    $stmt_v->close();

    if (!$jovem || !$vaga) {
        http_response_code(404);
        echo json_encode(['error' => 'Jovem ou vaga não encontrados.']);
        exit;
    }

    // ── Monta payload completo para o Flask ───────────────────────────────────
    $payload = [
        'cidade_jovem'        => $jovem['cidade']               ?? '',
        'area_interesse'      => $jovem['area_interesse']        ?? '',
        'escolaridade_tipo'   => $jovem['escolaridade_tipo']     ?? '',
        'escolaridade_curso'  => $jovem['escolaridade_curso']    ?? '',
        'escolaridade_status' => $jovem['escolaridade_status']   ?? '',
        'cidade_vaga'         => $vaga['cidade']                 ?? '',
        'area_vaga'           => $vaga['area']                   ?? '',
        'tipo_contrato'       => $vaga['tipo_contrato']          ?? '',
        'descricao'           => $vaga['descricao']              ?? '',
    ];

    // ── Chama o Flask ─────────────────────────────────────────────────────────
    $ch = curl_init("http://127.0.0.1:5000/avaliar");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $result     = curl_exec($ch);
    $errno      = curl_errno($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno || $result === false || $http_code !== 200) {
        http_response_code(503);
        echo json_encode(['error' => 'Servidor de IA indisponível.']);
        exit;
    }

    $resposta = json_decode($result, true);

    if (!isset($resposta['score'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Resposta inválida do servidor de IA.']);
        exit;
    }

    $score   = intval($resposta['score']);
    $analise = $resposta['analise'] ?? '';

    // ── Salva no cache ────────────────────────────────────────────────────────
    $stmt_ins = $conn->prepare(
        "INSERT INTO avaliacoes_ia (jovem_id, vaga_id, score, analise)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE score = ?, analise = ?"
    );
    $stmt_ins->bind_param(
        "iiisis",
        $jovem_id, $vaga_id, $score, $analise,
        $score, $analise
    );
    $stmt_ins->execute();
    $stmt_ins->close();

    echo json_encode([
        'score'    => $score,
        'analise'  => $analise,
        'motivos'  => $resposta['motivos'] ?? [],
        'do_cache' => false,
    ]);
    exit;
}

/* ============================================================
   ACTION: curriculo — análise do perfil do jovem pela IA
   ============================================================ */
if ($action === 'curriculo') {

    $jovem_id = intval($input['jovem_id'] ?? $_SESSION['jovem_id'] ?? 0);

    if ($jovem_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID inválido.']);
        exit;
    }

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