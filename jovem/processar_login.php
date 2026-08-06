<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php'; 
// --> ADICIONE ISSO: Inclui o motor de logs que criamos
require_once 'includes/logger.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Registamos que alguém tentou acessar o script de forma incorreta diretamente pela URL
    registrar_log("Acesso direto bloqueado ao processar_login.php", "WARNING");
    header("Location: index.php");
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$senha = $_POST['senha'] ?? '';
$tipo_usuario = $_POST['tipo_usuario'] ?? 'jovem';

if (empty($email) || empty($senha)) {
    // Registamos que houve um erro de digitação/campos em branco
    registrar_log("Tentativa de login com campos vazios para o e-mail: " . ($email ?: 'Não informado'), "WARNING");
    $_SESSION['erro_login'] = "Por favor, preencha todos os campos.";
    header("Location: " . $tipo_usuario . "/login.php");
    exit;
}

try {
    if ($tipo_usuario === 'empresa') {
        $sql = "SELECT id, nome, email, senha FROM empresas WHERE email = :email LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = 'empresa';

            // --> ADICIONE ISSO: Login corporativo com sucesso!
            registrar_log("Empresa '{$usuario['nome']}' (ID: {$usuario['id']}) logou com sucesso.", "INFO");

            header("Location: empresa/dashboard.php");
            exit;
        }

    } else {
        $sql = "SELECT id, nome, email, senha FROM jovens WHERE email = :email LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = 'jovem';

            // --> ADICIONE ISSO: Login do candidato com sucesso!
            registrar_log("Candidato '{$usuario['nome']}' (ID: {$usuario['id']}) logou com sucesso.", "INFO");

            header("Location: jovem/perfil.php");
            exit;
        }
    }

    // --> ADICIONE ISSO: Se chegou aqui, a senha ou e-mail estavam errados
    registrar_log("Falha de login: Senha incorreta ou e-mail inexistente para [{$email}] no painel [{$tipo_usuario}]", "WARNING");

    $_SESSION['erro_login'] = "E-mail ou senha incorretos.";
    header("Location: " . $tipo_usuario . "/login.php");
    exit;

} catch (PDOException $e) {
    // --> ADICIONE ISSO: Erro crítico no banco de dados! Salvamos a mensagem real do MySQL para o programador ver
    registrar_log("Erro crítico de banco de dados no login: " . $e->getMessage(), "ERROR");

    $_SESSION['erro_login'] = "Erro interno no servidor de autenticação.";
    header("Location: " . $tipo_usuario . "/login.php");
    exit;
}