<?php
/* ============================================================
   empresa/processar_login.php
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

// Bloqueia GET direto na URL
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// config.php está em includes/, um nível acima de empresa/
require_once dirname(__DIR__) . "/includes/config.php";
csrf_require();

// ── Leitura e validação básica ────────────────────────────────────────────────
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha']      ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($senha)) {
    jc_log_seguranca('login.falha', 'aviso', ['tipo' => 'empresa', 'motivo' => 'campos_invalidos']);
    $_SESSION['erro_login'] = "Preencha todos os campos corretamente.";
    header("Location: login.php");
    exit;
}

// ── Busca a empresa pelo e-mail (prepared statement mysqli) ───────────────────
$stmt = $conn->prepare(
    "SELECT id, nome_empresa, senha
     FROM   empresas
     WHERE  email = ?
     LIMIT  1"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$empresa = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Verifica senha ────────────────────────────────────────────────────────────
// password_verify() exige senha gravada com password_hash(). No schema.sql a
// empresa de exemplo (id=3) já tem hash; quem tem senha em texto puro é um
// jovem de exemplo (tabela jovens), que por isso não consegue entrar.
if ($empresa && password_verify($senha, $empresa['senha'])) {

    // Stage 7: conta suspensa pela administração não entra (aviso só depois da senha certa)
    require_once dirname(__DIR__) . '/includes/conta_status.php';
    if (jc_conta_suspensa($conn, 'empresa', intval($empresa['id']))) {
        jc_log_seguranca('login.bloqueado', 'aviso', ['tipo' => 'empresa', 'motivo' => 'conta_suspensa']);
        $_SESSION['erro_login'] = jc_conta_msg_suspensa();
        header("Location: login.php");
        exit;
    }

    // Login bem-sucedido → limpa erros anteriores
    unset($_SESSION['erro_login']);

    // ✅ Chaves de sessão corretas — usadas por todas as outras páginas
    $_SESSION['empresa_id']   = intval($empresa['id']);
    $_SESSION['empresa_nome'] = $empresa['nome_empresa'];
    jc_session_regenerate(); // MED-001

    // Redireciona para o dashboard (de empresa/ → ../dashboard/)
    jc_log_seguranca('login.sucesso', 'info', ['tipo' => 'empresa']);
    header("Location: ../dashboard/empresa.php");
    exit;
}

// ── Credenciais inválidas ─────────────────────────────────────────────────────
// e-mail inexistente e senha errada geram o MESMO evento (não revela qual foi)
jc_log_seguranca('login.falha', 'aviso', ['tipo' => 'empresa', 'motivo' => 'credenciais']);
$_SESSION['erro_login'] = "E-mail ou senha incorretos. Tente novamente.";
header("Location: login.php");
exit;