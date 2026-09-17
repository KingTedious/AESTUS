<?php
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
