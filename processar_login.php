<?php
// Central de Autenticação - JovemConnect
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $tipo_usuario = $_POST['tipo_usuario'] ?? '';

    // Se os campos não foram preenchidos
    if (empty($email) || empty($senha)) {
        $_SESSION['login_error'] = "Por favor, preencha todos os campos.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    if ($tipo_usuario === 'jovem') {
        // Query para jovem
        $stmt = $conn->prepare("SELECT id, senha FROM jovens WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Validação híbrida (senhas antigas em plain text e novas em hash)
            if (password_verify($senha, $user['senha']) || $senha === $user['senha']) {
                $_SESSION['jovem_id'] = $user['id'];
                
                // Atualiza último login (se houver na tabela, que existe conforme schema.sql)
                $update_login = $conn->prepare("UPDATE jovens SET ultimo_login = NOW() WHERE id = ?");
                $update_login->bind_param("i", $user['id']);
                $update_login->execute();

                header("Location: jovem/perfil.php");
                exit;
            } else {
                $_SESSION['login_error'] = "Credenciais inválidas. Verifique seu e-mail e senha.";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit;
            }
        } else {
            $_SESSION['login_error'] = "Credenciais inválidas. Verifique seu e-mail e senha.";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }

    } elseif ($tipo_usuario === 'empresa') {
        // Query para empresa
        $stmt = $conn->prepare("SELECT id, senha FROM empresas WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Validação híbrida
            if (password_verify($senha, $user['senha']) || $senha === $user['senha']) {
                $_SESSION['empresa_id'] = $user['id'];
                header("Location: empresa/dashboard.php");
                exit;
            } else {
                $_SESSION['login_error'] = "Credenciais inválidas. Verifique seu e-mail corporativo e senha.";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit;
            }
        } else {
            $_SESSION['login_error'] = "Credenciais inválidas. Verifique seu e-mail corporativo e senha.";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }
    } else {
        // Tipo de usuário não reconhecido
        $_SESSION['login_error'] = "Tipo de usuário inválido.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
} else {
    // Redireciona para a página inicial se o acesso não for via POST
    header("Location: index.php");
    exit;
}
?>
