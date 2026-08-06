<?php
/**
 * ARQUIVO: auth.php
 * OBJETIVO: Gerir permissões de acesso para Jovens e Empresas.
 */

/**
 * Função para proteger áreas exclusivas de Jovens.
 * Verifica se o 'jovem_id' existe na sessão.
 */
function verificarJovem(){
    // Verifica se a sessão já foi iniciada para evitar erro de duplicidade
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Se a variável de sessão 'jovem_id' não estiver definida... 
    if(!isset($_SESSION['jovem_id'])){
        // Redireciona para o login de jovens e interrompe o script
        header("Location: ../jovem/login.php");
        exit;
    }
}

/**
 * Função para proteger áreas exclusivas de Empresas.
 * Verifica se o 'empresa_id' existe na sessão.
 */
function verificarEmpresa(){
    // Verifica se a sessão já foi iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Se a variável de sessão 'empresa_id' não estiver definida... [cite: 6]
    if(!isset($_SESSION['empresa_id'])){
        // Redireciona para o login de empresas e interrompe o script
        header("Location: ../empresa/login.php");
        exit;
    }
}
?>