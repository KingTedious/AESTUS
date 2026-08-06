<?php
// Inclui o motor de logs para registar falhas na conexão
require_once __DIR__ . '/logger.php';

$host = 'localhost';
$dbname = 'jovemconnect';
$username = 'root';
$password = '';

try {
    // Tenta estabelecer a conexão usando PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Configura o PDO para lançar exceções em caso de erro de SQL
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    // REGISTO DE LOG: Se o banco falhar, grava o erro técnico real no arquivo text
    registrar_log("Falha catastrófica na conexão com o Banco de Dados: " . $e->getMessage(), "ERROR");
    
    // Exibe uma mensagem amigável para o utilizador para não assustar com códigos de erro
    die("Desculpe, o sistema está temporariamente indisponível. Por favor, tente mais tarde.");
}
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


/**
 * Verifica se o microsserviço Flask está online. 
 * Se estiver offline, inicia-o automaticamente em segundo plano.
 */
function garantir_flask_ativo() {
    $flask_host = '127.0.0.1';
    $flask_port = 5000; // Altere para a porta que seu Flask usa (ex: 5000 ou 8080)
    $timeout = 1; // tempo máximo de espera pelo teste em segundos

    // Faz uma conexão rápida de teste de socket na porta do Flask
    $socket = @fsockopen($flask_host, $flask_port, $errno, $errstr, $timeout);

    if (!$socket) {
        // O Flask está offline! Vamos dar o comando para ligar em segundo plano sem travar o PHP
        
        // Defina aqui o caminho exato onde está o arquivo python do seu Flask
        // Exemplo: 'C:/xampp/htdocs/JovemConnect/ia/app.py' ou relativo:
        $caminho_flask = __DIR__ . '/../ia/app.py'; 

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Comando para o Windows: Inicia o python em modo oculto/segundo plano (/B)
            pclose(popen("start /B python \"" . $caminho_flask . "\"", "r"));
        } else {
            // Comando para Linux / Mac: Roda em background usando o '&' no final
            exec("python3 \"" . $caminho_flask . "\" > /dev/null 2>&1 &");
        }
        
        // Opcional: Registra no log que o PHP precisou acordar o Flask
        if (function_exists('registrar_log')) {
            registrar_log("Microsserviço Flask estava offline e foi iniciado automaticamente pelo PHP.", "INFO");
        }
    } else {
        // Se conectou com sucesso, fecha o teste. O Flask já está de pé!
        fclose($socket);
    }
}

// Executa a verificação automaticamente sempre que uma página carregar o config.php
garantir_flask_ativo();
?>