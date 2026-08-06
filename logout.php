<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclui o motor de logs
require_once 'includes/logger.php';

// Captura o nome ou ID antes de destruir a sessão para podermos escrever no log
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Desconhecido';
$id_usuario = $_SESSION['usuario_id'] ?? 'Sem ID';
$tipo_usuario = $_SESSION['usuario_tipo'] ?? 'Desconhecido';

// REGISTO DE LOG: Registamos a saída limpa do utilizador
registrar_log("O utilizador '{$nome_usuario}' (ID: {$id_usuario} - Tipo: {$tipo_usuario}) encerrou a sessão.", "INFO");

// Limpa todas as variáveis de sessão da memória
$_SESSION = array();

// Destrói a sessão no servidor
session_destroy();

// Redireciona o utilizador de volta para a página inicial atualizada
header("Location: index.php");
exit;
?>