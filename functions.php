<?php
/* ============================================================
   functions.php — Utilitários globais do JovemConnect v3.00

   ❌ PROBLEMA DO ORIGINAL:
   calcularIdadeDinamica() lançava Exception sem try/catch
   se recebesse null, '' ou data inválida — derrubava a página.

   Novas funções adicionadas (PHP 8.2):
   ─ calcularIdade()   — versão robusta com validação
   ─ validarCpf()      — algoritmo oficial Receita Federal
   ─ formatarCpf()     — exibe CPF mascarado
   ─ validarCnpj()     — algoritmo oficial Receita Federal
   ─ formatarCnpj()    — exibe CNPJ mascarado
   ─ formatarCep()     — formata CEP como XXXXX-XXX
   ─ chamarFlask()     — cURL centralizado para o servidor IA
   ─ apenasNumeros()   — remove tudo que não é dígito
   ─ truncar()         — corta string com reticências (mb-safe)
   ============================================================ */

/* ============================================================
   DATAS
   ============================================================ */

/**
 * Calcula a idade em anos a partir de uma data de nascimento.
 * Retorna null se a data for inválida ou nula.
 *
 * @param string|null $dataNascimento Formato YYYY-MM-DD (vindo do MySQL)
 */
function calcularIdade(?string $dataNascimento): ?int {
    if (empty($dataNascimento)) {
        return null;
    }

    try {
        $nascimento = new DateTimeImmutable($dataNascimento);
        $hoje       = new DateTimeImmutable();

        // Data de nascimento no futuro → não faz sentido
        if ($nascimento > $hoje) {
            return null;
        }

        return (int) $hoje->diff($nascimento)->y;

    } catch (Exception) {
        // Data malformada (ex: "0000-00-00" que o MySQL retorna às vezes)
        return null;
    }
}

// Alias para compatibilidade com o código antigo
function calcularIdadeDinamica(?string $dataNascimento): int {
    return calcularIdade($dataNascimento) ?? 0;
}


/* ============================================================
   CPF
   ============================================================ */

/**
 * Remove tudo que não é dígito de uma string.
 */
function apenasNumeros(string $valor): string {
    return preg_replace('/\D/', '', $valor);
}

/**
 * Valida um CPF usando o algoritmo oficial da Receita Federal.
 */
function validarCpf(string $cpf): bool {
    $cpf = apenasNumeros($cpf);

    if (strlen($cpf) !== 11) return false;

    // Rejeita sequências repetidas (111.111.111-11, etc.)
    if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;

    // Primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto  = $soma % 11;
    $dig1   = $resto < 2 ? 0 : 11 - $resto;
    if (intval($cpf[9]) !== $dig1) return false;

    // Segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto  = $soma % 11;
    $dig2   = $resto < 2 ? 0 : 11 - $resto;

    return intval($cpf[10]) === $dig2;
}

/**
 * Formata CPF para exibição: XXX.XXX.XXX-XX
 * Retorna a string original se inválida.
 */
function formatarCpf(string $cpf): string {
    $cpf = apenasNumeros($cpf);
    if (strlen($cpf) !== 11) return $cpf;
    return substr($cpf, 0, 3) . '.'
         . substr($cpf, 3, 3) . '.'
         . substr($cpf, 6, 3) . '-'
         . substr($cpf, 9, 2);
}


/* ============================================================
   CNPJ
   ============================================================ */

/**
 * Valida um CNPJ usando o algoritmo oficial da Receita Federal.
 */
function validarCnpj(string $cnpj): bool {
    $cnpj = apenasNumeros($cnpj);

    if (strlen($cnpj) !== 14) return false;

    // Rejeita sequências repetidas
    if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;

    // Pesos para cálculo
    $calcularDigito = function (string $cnpj, int $tamanho): int {
        $pesos = [];
        $pos   = $tamanho - 7;
        for ($i = $tamanho; $i >= 1; $i--) {
            $pesos[] = $pos--;
            if ($pos < 2) $pos = 9;
        }
        $soma = 0;
        for ($i = 0; $i < $tamanho; $i++) {
            $soma += intval($cnpj[$i]) * $pesos[$i];
        }
        $resto = $soma % 11;
        return $resto < 2 ? 0 : 11 - $resto;
    };

    $dig1 = $calcularDigito($cnpj, 12);
    if (intval($cnpj[12]) !== $dig1) return false;

    $dig2 = $calcularDigito($cnpj, 13);
    return intval($cnpj[13]) === $dig2;
}

/**
 * Formata CNPJ para exibição: XX.XXX.XXX/XXXX-XX
 * Retorna a string original se inválida.
 */
function formatarCnpj(string $cnpj): string {
    $cnpj = apenasNumeros($cnpj);
    if (strlen($cnpj) !== 14) return $cnpj;
    return substr($cnpj, 0,  2) . '.'
         . substr($cnpj, 2,  3) . '.'
         . substr($cnpj, 5,  3) . '/'
         . substr($cnpj, 8,  4) . '-'
         . substr($cnpj, 12, 2);
}


/* ============================================================
   CEP
   ============================================================ */

/**
 * Formata CEP para exibição: XXXXX-XXX
 * Retorna a string original se inválida.
 */
function formatarCep(string $cep): string {
    $cep = apenasNumeros($cep);
    if (strlen($cep) !== 8) return $cep;
    return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
}


/* ============================================================
   TEXTO
   ============================================================ */

/**
 * Corta uma string para o tamanho máximo, adicionando reticências.
 * Usa mb_substr para suporte correto a UTF-8 (ã, ç, é...).
 *
 * @param string $texto   String original
 * @param int    $limite  Máximo de caracteres (default: 150)
 * @param string $sufixo  Sufixo ao truncar (default: '…')
 */
function truncar(string $texto, int $limite = 150, string $sufixo = '…'): string {
    $texto = trim($texto);
    if (mb_strlen($texto) <= $limite) {
        return $texto;
    }
    return mb_substr($texto, 0, $limite) . $sufixo;
}

/**
 * Alias seguro para htmlspecialchars com UTF-8.
 * Evita esquecer o charset ao usar a função nativa.
 */
function h(string $texto): string {
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/* ============================================================
   FLASK / IA
   ============================================================ */

/**
 * Faz uma requisição POST ao servidor Flask (IA).
 * Centraliza o código cURL para não duplicar em cada arquivo.
 *
 * @param  string $rota    Rota Flask, sem barra inicial (ex: 'avaliar', 'curriculo', 'ping')
 * @param  array  $dados   Payload a ser enviado como JSON
 * @param  int    $timeout Timeout total da requisição em segundos (default: 6)
 *
 * @return array|false     Array com a resposta da IA, ou false em caso de erro
 *
 * Exemplo de uso:
 *   $resultado = chamarFlask('avaliar', ['cidade_jovem' => 'SP', ...]);
 *   if ($resultado === false) { // Flask offline }
 *   $score = $resultado['score'] ?? 0;
 */
function chamarFlask(string $rota, array $dados = [], int $timeout = 6): array|false {
    $url = "http://127.0.0.1:5000/" . ltrim($rota, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($dados),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $result     = curl_exec($ch);
    $errno      = curl_errno($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno || $result === false || $http_code !== 200) {
        error_log(
            "[JovemConnect][Flask] Falha ao chamar /{$rota} — "
            . "errno: {$errno}, HTTP: {$http_code}"
        );
        return false;
    }

    $decoded = json_decode($result, true);
    return is_array($decoded) ? $decoded : false;
}

/**
 * Verifica se o servidor Flask está online (rota GET /ping).
 * Usa timeout curto (2s) para não travar o carregamento da página.
 *
 * @return bool true se online, false se offline
 */
function flaskOnline(): bool {
    $ch = curl_init("http://127.0.0.1:5000/ping");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 2,
        CURLOPT_CONNECTTIMEOUT => 1,
    ]);
    $result    = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $http_code === 200;
}