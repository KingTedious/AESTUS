<?php
/* ============================================================
   includes/compat.php — compatibilidade com PHP 7.4 e 8.0

   O JovemConnect é desenvolvido e testado no PHP 8.1 ou mais novo, mas
   alguns computadores (ex.: os da escola) têm um XAMPP mais antigo.
   Este arquivo define as poucas funções do PHP 8.0 que o projeto usa e
   que não existem no 7.4. No PHP 8.0 ou mais novo NADA é definido aqui
   (function_exists): as funções nativas continuam sendo usadas.

   Sem tipos nos parâmetros de propósito: as nativas aceitam qualquer
   escalar (inclusive null) e convertem para texto; uma função do
   usuário com "string $x" daria TypeError com null no PHP 7.4.
   ============================================================ */

if (!function_exists('str_contains')) {
    function str_contains($palheiro, $agulha): bool
    {
        $agulha = (string) $agulha;
        return $agulha === '' || strpos((string) $palheiro, $agulha) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($palheiro, $agulha): bool
    {
        $agulha = (string) $agulha;
        return strncmp((string) $palheiro, $agulha, strlen($agulha)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($palheiro, $agulha): bool
    {
        $agulha = (string) $agulha;
        $palheiro = (string) $palheiro;
        return $agulha === '' || ($palheiro !== '' && substr($palheiro, -strlen($agulha)) === $agulha);
    }
}
