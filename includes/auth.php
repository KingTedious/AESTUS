<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/logger.php';

/**
 * Função para verificar se o utilizador está logado e se tem a permissão correta.
 * 
 * @param string $tipo_requerido O tipo de utilizador necessário ('jovem' ou 'empresa')
 */
function verificar_autenticacao($tipo_requerido) {
    // Se não houver uma sessão de ID ativa...
    if (!isset($_SESSION['usuario_id'])) {
        
        // REGISTO DE LOG: Alguém tentou aceder a uma área restrita sem login
        $url_tentada = $_SERVER['REQUEST_URI'] ?? 'URL desconhecida';
        registrar_log("Tentativa de acesso não autorizado à página [{$url_tentada}]. Redirecionado para o login.", "WARNING");
        
        $_SESSION['erro_login'] = "Precisa de iniciar sessão para aceder a esta página.";
        
        // Redireciona de volta para a pasta de login correta
        header("Location: ../" . $tipo_requerido . "/login.php");
        exit;
    }

    // Se o utilizador estiver logado, mas tentar aceder ao painel do outro tipo (Ex: Jovem a tentar entrar na Empresa)
    if ($_SESSION['usuario_tipo'] !== $tipo_requerido) {
        
        // REGISTO DE LOG: Possível tentativa de invasão ou erro de navegação entre perfis
        registrar_log("Utilizador ID {$_SESSION['usuario_id']} ({$_SESSION['usuario_tipo']}) tentou aceder ilegalmente à área reservada de: [{$tipo_requerido}]", "WARNING");
        
        // Expulsa o utilizador para a sua respetiva página correta
        if ($_SESSION['usuario_tipo'] === 'empresa') {
            header("Location: ../empresa/dashboard.php");
        } else {
            header("Location: ../jovem/perfil.php");
        }
        exit;
    }
}