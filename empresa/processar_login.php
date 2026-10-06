<?php
/* ============================================================
   empresa/processar_login.php

   ❌ PROBLEMAS DO ORIGINAL (todos corrigidos aqui):
   1. Usava PDO  ($stmt->fetch(PDO::FETCH_ASSOC), bindParam(':email'))
      mas o projeto inteiro usa mysqli → quebrava na execução.
   2. require_once 'includes/config.php'  → caminho errado.
      Arquivo está em empresa/, config em includes/ → usar dirname(__DIR__).
   3. SELECT nome FROM empresas → coluna não existe; é nome_empresa.
   4. $_SESSION['usuario_id'] / ['usuario_nome'] / ['usuario_tipo']
      → chaves que nenhuma outra página do projeto lê.
      O projeto usa empresa_id / empresa_nome (e jovem_id / jovem_nome).
   5. Redirect pós-login: "empresa/dashboard.php" → caminho relativo errado.
      De dentro de empresa/, o dashboard está em ../dashboard/empresa.php.
   6. Tentava autenticar jovem neste arquivo, mas o JS da login.php
      redireciona jovens para ../jovem/processar_login.php → duplicidade.
      Este arquivo agora é empresa-only.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF

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
// ATENÇÃO: o usuário de teste (id=3) tem senha '123456' em texto puro no banco.
// password_verify() NÃO vai funcionar para ele até que a senha seja re-hashada.
// Para corrigir, atualize no banco:
//   UPDATE empresas SET senha = '$2y$...' WHERE id = 3;
// Gere o hash com: echo password_hash('123456', PASSWORD_DEFAULT);
if ($empresa && password_verify($senha, $empresa['senha'])) {

    // Login bem-sucedido → limpa erros anteriores
    unset($_SESSION['erro_login']);

    // ✅ Chaves de sessão corretas — usadas por todas as outras páginas
    $_SESSION['empresa_id']   = intval($empresa['id']);
    $_SESSION['empresa_nome'] = $empresa['nome_empresa'];
    jc_session_regenerate(); // MED-001

    // Redireciona para o dashboard (de empresa/ → ../dashboard/)
    header("Location: ../dashboard/empresa.php");
    exit;
}

// ── Credenciais inválidas ─────────────────────────────────────────────────────
$_SESSION['erro_login'] = "E-mail ou senha incorretos. Tente novamente.";
header("Location: login.php");
exit;