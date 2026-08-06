<?php
/**
 * ARQUIVO: config.php
 * OBJETIVO: Estabelecer a conexão com o banco de dados MySQL 'jovemconnect'.
 * DOCUMENTAÇÃO: Este arquivo deve ser incluído em qualquer página que precise consultar ou salvar dados.
 */

// 1. Definição das credenciais de acesso
$host = "localhost"; // Servidor local (XAMPP)
$user = "root";      // Usuário administrador padrão
$pass = "";          // Senha padrão (vazia no XAMPP)
$db   = "jovemconnect"; // Nome do banco de dados identificado no PDF

// 2. Criação da conexão utilizando a extensão MySQLi
$conn = new mysqli($host, $user, $pass, $db);

// 3. Verificação de Erros
// Se houver uma falha, o script é interrompido e exibe o motivo.
if ($conn->connect_error) {
    die("Erro crítico na conexão: " . $conn->connect_error);
}

// 4. Configuração de Charset (IMPORTANTE)
// Garante que caracteres como 'ç', 'ã', 'é' sejam exibidos corretamente no navegador.
$conn->set_charset("utf8mb4");

?>