<?php
/* ============================================================
   jovem/processar_login.php

   Espelho de empresa/processar_login.php para o fluxo jovem.
   Diferenças: tabela jovens, coluna nome (não nome_empresa),
   chaves de sessão jovem_id / jovem_nome.

   Erros comuns do original que este arquivo corrige:
   1. require_once 'includes/config.php'  → caminho relativo
      busca jovem/includes/config.php (não existe).
      Correto: dirname(__DIR__) . "/includes/config.php"
   2. PDO em projeto mysqli.
   3. Chaves de sessão erradas.
   ============================================================ */
session_start();

// Bloqueia GET direto na URL
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// dirname(__DIR__) → sobe de jovem/ para a raiz do projeto
require_once dirname(__DIR__) . "/includes/config.php";

// ── Leitura e validação básica ────────────────────────────────────────────────
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha']      ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($senha)) {
    $_SESSION['erro_login'] = "Preencha todos os campos corretamente.";
    header("Location: login.php");
    exit;
}

// ── Busca o jovem pelo e-mail ─────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT id, nome, senha
     FROM   jovens
     WHERE  email = ?
     LIMIT  1"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$jovem = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Verifica senha ────────────────────────────────────────────────────────────
if ($jovem && password_verify($senha, $jovem['senha'])) {

    unset($_SESSION['erro_login']);

    // ✅ Chaves corretas — lidas por todas as páginas do projeto
    $_SESSION['jovem_id']   = intval($jovem['id']);
    $_SESSION['jovem_nome'] = $jovem['nome'];

    // Atualiza ultimo_login
    $stmt_ul = $conn->prepare(
        "UPDATE jovens SET ultimo_login = NOW() WHERE id = ?"
    );
    $stmt_ul->bind_param("i", $_SESSION['jovem_id']);
    $stmt_ul->execute();
    $stmt_ul->close();

    header("Location: ../dashboard/jovem.php");
    exit;
}

// ── Credenciais inválidas ─────────────────────────────────────────────────────
$_SESSION['erro_login'] = "E-mail ou senha incorretos. Tente novamente.";
header("Location: login.php");
exit;