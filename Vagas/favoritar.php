<?php
/* ============================================================
   Vagas/favoritar.php — Toggle de favoritos (jovem ↔ vaga)

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):

   1. session_start() depois do include config.php.

   2. SQL Injection em TODAS as queries: real_escape_string()
      num inteiro não é proteção — intval() + prepared statement.

   3. OPEN REDIRECT:
      header("Location: " . $_SERVER['HTTP_REFERER'])
      O header HTTP_REFERER é controlado pelo cliente/browser.
      Um atacante define Referer: http://malicious.com e
      o site redireciona para lá — phishing clássico.
      NUNCA usar HTTP_REFERER diretamente num redirect.

   4. Ação de escrita (INSERT/DELETE) via GET:
      Crawlers, prefetch de browsers e bots indexam links GET,
      podendo favoritar/desfavoritar involuntariamente.
      Dados modificados → POST obrigatório.

   ─── SOLUÇÃO ──────────────────────────────────────────────
   Aceita POST (form ou fetch).
   Se for chamada AJAX (X-Requested-With: XMLHttpRequest)
   → retorna JSON {favorito: bool, action: 'added'|'removed'}.
   Caso contrário (form convencional, sem JS)
   → redirect seguro para página da vaga ou dashboard.
   ============================================================ */
session_start();

// ── Detecta se é chamada AJAX ────────────────────────────────────────────────
$is_ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

function jsonError(int $code, string $msg): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $msg]);
    exit;
}

function jsonOk(array $data): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['jovem_id'])) {
    if ($is_ajax) jsonError(401, 'Não autenticado.');
    header("Location: ../jovem/login.php");
    exit;
}

// ── Só aceita POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_ajax) jsonError(405, 'Método não permitido.');
    header("Location: ../dashboard/jovem.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";

$jovem_id = intval($_SESSION['jovem_id']);
$vaga_id  = intval($_POST['vaga_id'] ?? 0);

if ($vaga_id <= 0) {
    if ($is_ajax) jsonError(400, 'vaga_id inválido.');
    header("Location: ../dashboard/jovem.php");
    exit;
}

// ── Verifica se a vaga existe ─────────────────────────────────────────────────
$stmt_v = $conn->prepare("SELECT id FROM vagas WHERE id = ? LIMIT 1");
$stmt_v->bind_param("i", $vaga_id);
$stmt_v->execute();
$stmt_v->store_result();
$vaga_existe = $stmt_v->num_rows > 0;
$stmt_v->close();

if (!$vaga_existe) {
    if ($is_ajax) jsonError(404, 'Vaga não encontrada.');
    header("Location: ../dashboard/jovem.php");
    exit;
}

// ── Toggle: verifica se já é favorito ─────────────────────────────────────────
$stmt_chk = $conn->prepare(
    "SELECT id FROM favoritos WHERE jovem_id = ? AND vaga_id = ? LIMIT 1"
);
$stmt_chk->bind_param("ii", $jovem_id, $vaga_id);
$stmt_chk->execute();
$stmt_chk->store_result();
$ja_favoritado = $stmt_chk->num_rows > 0;
$stmt_chk->close();

if ($ja_favoritado) {
    // ── REMOVE dos favoritos ──────────────────────────────────────────────────
    $stmt_del = $conn->prepare(
        "DELETE FROM favoritos WHERE jovem_id = ? AND vaga_id = ?"
    );
    $stmt_del->bind_param("ii", $jovem_id, $vaga_id);
    $ok     = $stmt_del->execute();
    $stmt_del->close();
    $action = 'removed';
} else {
    // ── ADICIONA aos favoritos ────────────────────────────────────────────────
    $stmt_ins = $conn->prepare(
        "INSERT INTO favoritos (jovem_id, vaga_id) VALUES (?, ?)"
    );
    $stmt_ins->bind_param("ii", $jovem_id, $vaga_id);
    $ok     = $stmt_ins->execute();
    $stmt_ins->close();
    $action = 'added';
}

if (!$ok) {
    error_log("[JovemConnect] Erro ao atualizar favoritos: " . $conn->error);
    if ($is_ajax) jsonError(500, 'Erro interno ao atualizar favoritos.');
    header("Location: ../dashboard/jovem.php?erro=favorito");
    exit;
}

// ── Resposta ──────────────────────────────────────────────────────────────────
if ($is_ajax) {
    jsonOk([
        'action'     => $action,
        'favorito'   => ($action === 'added'),
        'vaga_id'    => $vaga_id,
    ]);
}

// Redirect seguro (sem HTTP_REFERER) para a página da vaga
header("Location: listar.php?id={$vaga_id}&favorito={$action}");
exit;