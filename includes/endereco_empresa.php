<?php
/* ============================================================
   includes/endereco_empresa.php

   Endereço completo da empresa: CEP, logradouro, número, complemento
   e bairro (colunas novas, opcionais) + cidade/estado (já existiam).

   As colunas novas vêm da migração ADITIVA
   migrations/2026-10-06_stage3_endereco_empresa.sql.
   Enquanto ela não for aplicada, o cadastro e o perfil da empresa
   continuam funcionando só com cidade/UF — nada quebra.
   ============================================================ */

if (!function_exists('empresa_tem_endereco')) {

    /** true se a migração de endereço já foi aplicada neste banco. */
    function empresa_tem_endereco(mysqli $conn): bool {
        static $tem = null;
        if ($tem === null) {
            $r   = $conn->query("SHOW COLUMNS FROM empresas LIKE 'logradouro'");
            $tem = ($r instanceof mysqli_result) && $r->num_rows > 0;
        }
        return $tem;
    }

    /** Lê os campos de endereço do POST, já limitados ao tamanho das colunas. */
    function endereco_empresa_do_post(): array {
        return [
            'cep'         => substr(preg_replace('/\D/', '', $_POST['cep'] ?? ''), 0, 8),
            'logradouro'  => mb_substr(trim($_POST['logradouro']  ?? ''), 0, 150),
            'numero'      => mb_substr(trim($_POST['numero']      ?? ''), 0, 10),
            'complemento' => mb_substr(trim($_POST['complemento'] ?? ''), 0, 100),
            'bairro'      => mb_substr(trim($_POST['bairro']      ?? ''), 0, 100),
        ];
    }

    /** "Rua X, 123 - Sala 4 - Bairro - Cidade/UF - CEP 00000-000" (partes vazias omitidas). */
    function endereco_empresa_formatado(array $e): string {
        $rua       = trim(($e['logradouro'] ?? '') . (!empty($e['numero']) ? ', ' . $e['numero'] : ''), ' ,');
        $cidade_uf = trim(($e['cidade'] ?? '') . (!empty($e['estado']) ? '/' . $e['estado'] : ''), '/');
        $cep       = preg_replace('/\D/', '', $e['cep'] ?? '');
        $partes = [
            $rua,
            $e['complemento'] ?? '',
            $e['bairro'] ?? '',
            $cidade_uf,
            strlen($cep) === 8 ? 'CEP ' . substr($cep, 0, 5) . '-' . substr($cep, 5) : '',
        ];
        return implode(' - ', array_filter($partes, fn($p) => trim((string) $p) !== ''));
    }

    /** CNPJ no formato 00.000.000/0000-00 a partir dos dígitos (null se não tiver 14). */
    function cnpj_formatado(string $cnpj): ?string {
        $d = preg_replace('/\D/', '', $cnpj);
        if (strlen($d) !== 14) {
            return null;
        }
        return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3)
             . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
    }
}
