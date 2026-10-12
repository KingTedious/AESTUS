<?php
/* ============================================================
   Vagas/denunciar.php — denúncia de vaga feita por um jovem (Stage 7)

   POST do formulário "Algo errado com esta vaga?" (Vagas/listar.php).
   - Só jovem logado, com token CSRF, para vaga VISÍVEL.
   - Uma denúncia por jovem e vaga (chave única no banco) e no máximo
     10 por dia por jovem (contra abuso).
   - Motivo de uma lista fechada; detalhe opcional (até 500 caracteres,
     validado; código HTML/JavaScript é recusado).
   - Cria (ou soma em) um item da fila de moderação, origem "denuncia",
     com risco conforme o motivo. NADA é escondido automaticamente: a
     decisão é sempre de uma pessoa da administração. A empresa não
     fica sabendo quem denunciou.
   Sem a migração da Stage 7, o formulário nem aparece e esta página
   só devolve para a lista.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

if (!isset($_SESSION['jovem_id'])) {
    header("Location: ../jovem/login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: listar.php");
    exit;
}

require_once dirname(__DIR__) . "/includes/config.php";
csrf_require();
require_once dirname(__DIR__) . "/includes/conta_status.php";
require_once dirname(__DIR__) . "/includes/moderacao.php";

$jovem_id = intval($_SESSION['jovem_id']);
$vaga_id  = intval($_POST['vaga_id'] ?? 0);
$voltar   = in_array($_POST['voltar'] ?? '', ['painel', 'favoritos', 'candidaturas'], true) ? $_POST['voltar'] : '';

$ir = function (string $resultado) use ($vaga_id, $voltar): void {
    header('Location: listar.php?id=' . $vaga_id . ($voltar !== '' ? '&voltar=' . $voltar : '') . '&denuncia=' . $resultado);
    exit;
};

if (!jc_stage7_disponivel($conn) || $vaga_id <= 0 || !jc_vaga_visivel($conn, $vaga_id)) {
    header("Location: listar.php");
    exit;
}

$motivo = is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : '';
if (!isset(jc_denuncia_motivos()[$motivo])) {
    $ir('invalida');
}
$analise = jc_mod_analisar_campos(['denuncia.detalhe' => $_POST['detalhe'] ?? '']);
if (!$analise['ok']) {
    jc_log_seguranca('denuncia.recusada', 'aviso', ['vaga' => $vaga_id, 'motivo' => 'detalhe_invalido']);
    $ir('invalida');
}
$detalhe = $analise['valores']['denuncia.detalhe'] !== '' ? $analise['valores']['denuncia.detalhe'] : null;

// já denunciou esta vaga?
$stmt = $conn->prepare("SELECT id FROM denuncias WHERE vaga_id = ? AND jovem_id = ? LIMIT 1");
$stmt->bind_param("ii", $vaga_id, $jovem_id);
$stmt->execute();
$ja = (bool) $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($ja) {
    $ir('ja');
}

// limite diário por jovem
$stmt = $conn->prepare("SELECT COUNT(*) AS n FROM denuncias WHERE jovem_id = ? AND criado_em >= NOW() - INTERVAL 1 DAY");
$stmt->bind_param("i", $jovem_id);
$stmt->execute();
$hoje = intval($stmt->get_result()->fetch_assoc()['n'] ?? 0);
$stmt->close();
if ($hoje >= 10) {
    jc_log_seguranca('denuncia.limite', 'aviso', ['vaga' => $vaga_id]);
    $ir('limite');
}

// autor do conteúdo denunciado = a empresa dona da vaga
$stmt = $conn->prepare("SELECT empresa_id FROM vagas WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $vaga_id);
$stmt->execute();
$empresa_id = intval($stmt->get_result()->fetch_assoc()['empresa_id'] ?? 0);
$stmt->close();

$item_id = null;
try {
    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO denuncias (vaga_id, jovem_id, motivo, detalhe) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $vaga_id, $jovem_id, $motivo, $detalhe);
    $stmt->execute();
    $denuncia_id = intval($conn->insert_id);
    $stmt->close();

    $ids = jc_mod_registrar($conn, 'vaga', $vaga_id, [
        'denuncia' => ['regras' => [['cod' => 'denuncia.' . $motivo]], 'risco' => jc_denuncia_risco($motivo), 'hash' => null],
    ], 'empresa', $empresa_id ?: null, 'denuncia');
    $item_id = $ids['denuncia'] ?? null;
    if (!$item_id) {
        throw new RuntimeException('fila indisponível');
    }
    $stmt = $conn->prepare("UPDATE denuncias SET item_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $item_id, $denuncia_id);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if (intval($e->getCode()) === 1062) {   // chave única: denúncia repetida ao mesmo tempo
        $ir('ja');
    }
    jc_log_excecao($e, 'denuncia.erro');
    $ir('erro');
} catch (Throwable $e) {
    $conn->rollback();
    jc_log_excecao($e, 'denuncia.erro');
    $ir('erro');
}

jc_log_seguranca('denuncia.criada', 'info', ['vaga' => $vaga_id, 'motivo' => $motivo, 'item' => $item_id]);
$ir('ok');
