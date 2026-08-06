<?php
/* ============================================================
   dashboard/index.php — Despachante de sessão
   
   Função: redirecionar imediatamente para o painel correto.
   
   ❌ NUNCA colocar includes, echo ou HTML antes dos header().
      Qualquer output (mesmo um espaço) invalida o redirect.
   ============================================================ */
session_start();

// Jovem autenticado → painel do jovem
if (isset($_SESSION['jovem_id'])) {
    header("Location: jovem.php");
    exit;
}

// Empresa autenticada → painel da empresa
if (isset($_SESSION['empresa_id'])) {
    header("Location: empresa.php");
    exit;
}

// Não autenticado → página inicial
header("Location: ../index.php");
exit;