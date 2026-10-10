<?php
/* ============================================================
   uploads/ver_curriculo.php

   Entrega o PDF do currículo COM controle de acesso:
     - o próprio jovem (sessão jovem_id = id pedido); ou
     - uma empresa com relação de recrutamento com o jovem
       (includes/candidato.php — mesma regra de jovem/perfil.php).

   O acesso direto a uploads/curriculos/*.pdf fica bloqueado pelo
   .htaccess daquela pasta: o PDF só sai por este arquivo.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/session.php';
jc_session_start();

$tem_jovem   = isset($_SESSION['jovem_id']);
$tem_empresa = isset($_SESSION['empresa_id']);

if (!$tem_jovem && !$tem_empresa) {
    header('Location: ../jovem/login.php');
    exit;
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/candidato.php';

function curriculo_nao_encontrado(): never {
    // Mesma resposta para "não existe" e "sem permissão": não revela nada
    http_response_code(404);
    echo 'Currículo não encontrado.';
    exit;
}

$jovem_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$jovem_id = ($jovem_id === false || $jovem_id === null) ? 0 : $jovem_id;
if ($jovem_id <= 0) {
    curriculo_nao_encontrado();
}

$eh_dono = $tem_jovem && intval($_SESSION['jovem_id']) === $jovem_id;
if (!$eh_dono && !$tem_empresa) {
    curriculo_nao_encontrado();          // outro jovem não vê currículo alheio
}

$stmt = $conn->prepare("SELECT curriculo FROM jovens WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $jovem_id);
$stmt->execute();
$jovem = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$jovem || empty($jovem['curriculo'])) {
    curriculo_nao_encontrado();
}
if (!$eh_dono && !jc_empresa_pode_ver_candidato($conn, intval($_SESSION['empresa_id']), $jovem_id)) {
    jc_log_seguranca('acesso.negado', 'aviso', ['recurso' => 'curriculo', 'jovem' => $jovem_id]);
    curriculo_nao_encontrado();          // sem relação de recrutamento: empresa não acessa
}

$arquivo = __DIR__ . '/curriculos/' . basename($jovem['curriculo']);
if (!is_file($arquivo)) {
    curriculo_nao_encontrado();
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($arquivo));
header('Content-Disposition: inline; filename="curriculo.pdf"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($arquivo);
exit;
