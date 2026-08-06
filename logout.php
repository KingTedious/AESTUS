<?php
session_start();

// 1. Limpa todas as variáveis de sessão ativas
$_SESSION = array();

// 2. Destrói os cookies de sessão no navegador do usuário
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destrói a sessão no servidor efetivamente
session_destroy();

// 4. Redireciona na hora para a página inicial do site
header("Location: index.php");
exit;
?>