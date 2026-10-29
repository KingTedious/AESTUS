<?php
require_once __DIR__ . '/compat.php';   // PHP 7.4/8.0: str_contains etc. (includes/compat.php)
/**
 * includes/env.php
 *
 * Carregador mínimo de variáveis de ambiente a partir de um arquivo .env
 * na raiz do projeto. NÃO é um framework nem uma dependência nova — é um
 * utilitário pequeno e autocontido (mesmo espírito de functions.php),
 * usado só para manter credenciais de SMTP/Twilio fora do código-fonte.
 *
 * Uso: require_once antes de qualquer getenv('CHAVE').
 */

if (!function_exists('carregarEnv')) {
    function carregarEnv(): void {
        static $carregado = false;
        if ($carregado) {
            return;
        }
        $carregado = true;

        $caminho = dirname(__DIR__) . '/.env';
        if (!is_file($caminho) || !is_readable($caminho)) {
            return;
        }

        $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($linhas === false) {
            return;
        }

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
                continue;
            }

            [$chave, $valor] = explode('=', $linha, 2);
            $chave = trim($chave);
            $valor = trim($valor);

            // Remove aspas simples/duplas envolventes, se houver.
            if (strlen($valor) >= 2) {
                $primeiro = $valor[0];
                $ultimo   = substr($valor, -1);
                if (($primeiro === '"' && $ultimo === '"') || ($primeiro === "'" && $ultimo === "'")) {
                    $valor = substr($valor, 1, -1);
                }
            }

            if ($chave !== '' && getenv($chave) === false) {
                putenv("{$chave}={$valor}");
                $_ENV[$chave] = $valor;
            }
        }
    }
}

carregarEnv();

/**
 * Correção de segurança (4.5): erros do PHP fora da página.
 * O php.ini do XAMPP vem com display_errors ligado: um aviso do PHP mostraria
 * na tela caminhos do servidor, nomes de arquivo e trechos de SQL a qualquer
 * visitante. Por padrão os erros vão só para o log do PHP (no XAMPP:
 * C:\xampp\php\logs\php_error_log); as exceções continuam no logs/app.log.
 * Para VER os erros na tela enquanto desenvolve: JC_EXIBIR_ERROS=1 no .env.
 * A linha de comando (scripts/*.php) não muda.
 */
if (!function_exists('jc_configurar_erros')) {
    function jc_configurar_erros(): void {
        static $feito = false;
        if ($feito || PHP_SAPI === 'cli') {
            return;
        }
        $feito = true;
        if (getenv('JC_EXIBIR_ERROS') !== '1') {
            @ini_set('display_errors', '0');
            @ini_set('display_startup_errors', '0');
        }
        @ini_set('log_errors', '1');
    }
}
jc_configurar_erros();
