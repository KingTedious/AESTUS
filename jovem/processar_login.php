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
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

// Bloqueia GET direto na URL
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// dirname(__DIR__) → sobe de jovem/ para a raiz do projeto
require_once dirname(__DIR__) . "/includes/config.php";
csrf_require();

// ── Leitura e validação básica ────────────────────────────────────────────────
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha']      ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($senha)) {
    jc_log_seguranca('login.falha', 'aviso', ['tipo' => 'jovem', 'motivo' => 'campos_invalidos']);
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

    // Stage 7: conta suspensa pela administração não entra (aviso só depois da senha certa)
    require_once dirname(__DIR__) . '/includes/conta_status.php';
    if (jc_conta_suspensa($conn, 'jovem', intval($jovem['id']))) {
        jc_log_seguranca('login.bloqueado', 'aviso', ['tipo' => 'jovem', 'motivo' => 'conta_suspensa']);
        $_SESSION['erro_login'] = jc_conta_msg_suspensa();
        header("Location: login.php");
        exit;
    }

    unset($_SESSION['erro_login']);

    // ✅ Chaves corretas — lidas por todas as páginas do projeto
    $_SESSION['jovem_id']   = intval($jovem['id']);
    $_SESSION['jovem_nome'] = $jovem['nome'];
    jc_session_regenerate(); // MED-001: evita session fixation

    // Atualiza ultimo_login
    $stmt_ul = $conn->prepare(
        "UPDATE jovens SET ultimo_login = NOW() WHERE id = ?"
    );
    $stmt_ul->bind_param("i", $_SESSION['jovem_id']);
    $stmt_ul->execute();
    $stmt_ul->close();

    jc_log_seguranca('login.sucesso', 'info', ['tipo' => 'jovem']);
    header("Location: ../dashboard/jovem.php");
    exit;
}

// ── Credenciais inválidas ─────────────────────────────────────────────────────
// e-mail inexistente e senha errada geram o MESMO evento (não revela qual foi)
jc_log_seguranca('login.falha', 'aviso', ['tipo' => 'jovem', 'motivo' => 'credenciais']);
$_SESSION['erro_login'] = "E-mail ou senha incorretos. Tente novamente.";
header("Location: login.php");
exit;