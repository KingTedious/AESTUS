<?php
// Inicia a sessão com segurança
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conecta ao arquivo de configuração global e ao banco de dados ($conn)
require_once 'includes/config.php'; 

// Bloqueia acessos diretos via URL sem submissão de formulário
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Captura e sanitiza as credenciais enviadas pelos inputs empilhados
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$senha = $_POST['senha'] ?? '';
$tipo_usuario = $_POST['tipo_usuario'] ?? 'jovem'; // Captura o valor do controle de abas

// Validação simples de campos em branco
if (empty($email) || empty($senha)) {
    $_SESSION['erro_login'] = "Por favor, preencha todos os campos.";
    header("Location: " . $tipo_usuario . "/login.php");
    exit;
}

try {
    // Roteamento inteligente baseado no tipo selecionado na interface (#89B4FA)
    if ($tipo_usuario === 'empresa') {
        
        // --- AUTENTICAÇÃO DO FLUXO EMPRESA ---
        $sql = "SELECT id, nome, email, senha FROM empresas WHERE email = :email LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        // Corrigido: Usando a variável no singular $usuario
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Corrigido: Verificando se $usuario (no singular) existe e validando a senha
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = 'empresa';

            header("Location: empresa/dashboard.php");
            exit;
        }

    } else {
        
        // --- AUTENTICAÇÃO DO FLUXO JOVEM (PADRÃO) ---
        $sql = "SELECT id, nome, email, senha FROM jovens WHERE email = :email LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        // Corrigido: Usando a variável no singular $usuario
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Corrigido: Verificando se $usuario (no singular) existe e validando a senha
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = 'jovem';

            header("Location: jovem/perfil.php");
            exit;
        }
    }

    // Se não encontrou o usuário ou a senha estiver errada
    $_SESSION['erro_login'] = "E-mail ou senha incorretos.";
    header("Location: " . $tipo_usuario . "/login.php");
    exit;

} catch (PDOException $e) {
    $_SESSION['erro_login'] = "Erro interno no servidor de autenticação.";
    header("Location: " . $tipo_usuario . "/login.php");
    exit;
}