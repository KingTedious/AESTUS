<?php
require_once __DIR__ . '/../includes/compat.php';   // PHP 7.4/8.0: str_contains (includes/compat.php)
// includes/validacoes.php
/* ------------------------------------------------------------------
   Stage 7 — ARQUIVO LEGADO, SEM USO. Nenhuma página inclui este arquivo
   (conferido na auditoria da Stage 7). Ele foi mantido sem alterações de
   comportamento só para não apagar código do projeto.
   NÃO use em código novo: o filtro por trecho de palavra recusaria nomes
   legítimos (ex.: "Paulo" contém "pau") e a lista de caracteres recusa
   nomes estrangeiros (ex.: "Nguyễn"). A validação e a moderação oficiais
   ficam em includes/validacao.php e includes/moderacao.php.
   ------------------------------------------------------------------ */

/**
 * Normaliza o texto removendo acentos e convertendo leetspeak simples
 */
function normalizarTexto(string $texto): string {
    $texto = mb_strtolower($texto, 'UTF-8');
    
    // Substituições comuns de leetspeak (ex: P4l4vr4 -> palavra)
    $leetspeak = [
        '0' => 'o', '1' => 'i', '3' => 'e', 
        '4' => 'a', '5' => 's', '7' => 't', '@' => 'a'
    ];
    $texto = strtr($texto, $leetspeak);

    // Remove acentuação
    $mapaAcentos = [
        'á'=>'a', 'à'=>'a', 'ã'=>'a', 'â'=>'a', 'ä'=>'a',
        'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e',
        'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i',
        'ó'=>'o', 'ò'=>'o', 'õ'=>'o', 'ô'=>'o', 'ö'=>'o',
        'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u',
        'ç'=>'c'
    ];
    
    return strtr($texto, $mapaAcentos);
}

/**
 * Verifica se a string contém algum termo impróprio ou ofensivo
 */
function contemPalavraImpropria(string $nome): bool {
    $nomeNormalizado = normalizarTexto($nome);

    // Lista de termos proibidos (adicione os termos que desejar)
    $palabrasProibidas = [
        'admin', 'administrator', 'suporte', 'moderador', 'jovemconnect',
        'puto', 'puta', 'caralho', 'porra', 'merda', 'viado', 'cadela',
        'arrombado', 'buceta', 'pinto', 'pau', 'assassino', 'hitler', 'nazista'
        // ... adicione mais termos aqui
    ];

    foreach ($palabrasProibidas as $palavra) {
        // Verifica palavra isolada ou dentro do texto
        if (str_contains($nomeNormalizado, $palavra)) {
            return true;
        }
    }

    return false;
}

/**
 * Valida se o nome do JOVEM é um nome humano real
 * - Mínimo de 2 palavras (Nome + Sobrenome)
 * - Apenas letras, espaços, apóstrofos e hífens
 * - Sem números ou caracteres especiais de nickname
 */
function validarNomeJovem(string $nome): array {
    $nome = trim(preg_replace('/\s+/', ' ', $nome)); // Remove espaços duplicados

    // 1. Checa palavrões
    if (contemPalavraImpropria($nome)) {
        return ['valido' => false, 'erro' => 'O nome contém termos não permitidos.'];
    }

    // 2. Permite apenas letras (com acentos), espaços, hífen e apóstrofo
    if (!preg_match('/^[a-zA-ZÀ-ÿ\s\'-]+$/u', $nome)) {
        return ['valido' => false, 'erro' => 'Digite apenas letras. Nicknames, números e símbolos não são permitidos.'];
    }

    // 3. Verifica se tem pelo menos 2 nomes (ex: João Silva)
    $partes = explode(' ', $nome);
    if (count($partes) < 2) {
        return ['valido' => false, 'erro' => 'Por favor, digite seu nome completo (Nome e Sobrenome).'];
    }

    // 4. Verifica o tamanho de cada parte (evita coisas como "A B")
    foreach ($partes as $parte) {
        if (mb_strlen($parte, 'UTF-8') < 2) {
            return ['valido' => false, 'erro' => 'Cada parte do nome deve ter pelo menos 2 letras.'];
        }
    }

    // 5. Evita repetição exagerada de caracteres (ex: "Aaaaaaa Silva")
    if (preg_match('/(.)\1{3,}/u', $nome)) {
        return ['valido' => false, 'erro' => 'Nome inválido (caracteres repetidos em excesso).'];
    }

    return ['valido' => true, 'nome_formatado' => mb_convert_case($nome, MB_CASE_TITLE, 'UTF-8')];
}

/**
 * Valida o nome/razão social da EMPRESA
 * - Aceita letras, números e símbolos corporativos (&, ., -, /)
 */
function validarNomeEmpresa(string $nome): array {
    $nome = trim(preg_replace('/\s+/', ' ', $nome));

    // 1. Checa palavrões
    if (contemPalavraImpropria($nome)) {
        return ['valido' => false, 'erro' => 'O nome da empresa contém termos não permitidos.'];
    }

    // 2. Permite letras, números, espaços e caracteres de empresa (&, ., -, /)
    if (!preg_match('/^[a-zA-Z0-9À-ÿ\s\'.&\/-]+$/u', $nome)) {
        return ['valido' => false, 'erro' => 'O nome da empresa contém caracteres inválidos.'];
    }

    // 3. Tamanho mínimo
    if (mb_strlen($nome, 'UTF-8') < 2) {
        return ['valido' => false, 'erro' => 'O nome da empresa deve ter pelo menos 2 caracteres.'];
    }

    return ['valido' => true, 'nome_formatado' => $nome];
}