<?php
/* ============================================================
   includes/admin/totp.php — segundo fator (TOTP, RFC 6238) da
   Central administrativa (Stage 7)

   Código de 6 dígitos que muda a cada 30 s, gerado por qualquer app
   autenticador (Google Authenticator, Microsoft Authenticator, Aegis,
   2FAS...). Implementação própria, sem dependências:
   HMAC-SHA1 sobre o número do intervalo, segredo de 160 bits em base32.

   - Aceita o intervalo atual e um de cada lado (relógios desajustados).
   - Um código só vale UMA vez: o intervalo aceito é gravado em
     administradores.mfa_ultimo_passo e códigos daquele intervalo ou
     anteriores são recusados (impede reaproveitar um código visto).
   - O segredo é gerado e mostrado UMA vez pela linha de comando
     (scripts/admin.php); a Central nunca exibe o segredo.
   ============================================================ */

if (!function_exists('jc_totp_verificar')) {

    function jc_base32_codificar(string $bin): string {
        $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 5) as $grupo) {
            $saida .= $alfabeto[bindec(str_pad($grupo, 5, '0'))];
        }
        return $saida;
    }

    function jc_base32_decodificar(string $b32): ?string {
        $b32 = strtoupper(preg_replace('/[\s=-]/', '', $b32) ?? '');
        if ($b32 === '' || !preg_match('/^[A-Z2-7]+$/', $b32)) {
            return null;
        }
        $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($b32) as $c) {
            $bits .= str_pad(decbin(strpos($alfabeto, $c)), 5, '0', STR_PAD_LEFT);
        }
        $bin = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bin .= chr(bindec($byte));
            }
        }
        return $bin;
    }

    /** Segredo novo (160 bits) em base32. */
    function jc_totp_novo_segredo(): string {
        return jc_base32_codificar(random_bytes(20));
    }

    /** Código de 6 dígitos para um intervalo. */
    function jc_totp_codigo(string $segredo_b32, int $passo): string {
        $chave = jc_base32_decodificar($segredo_b32);
        if ($chave === null) {
            return '';
        }
        $msg  = pack('N*', 0) . pack('N*', $passo);
        $hash = hash_hmac('sha1', $msg, $chave, true);
        $off  = ord($hash[19]) & 0x0f;
        $num  = ((ord($hash[$off]) & 0x7f) << 24) | (ord($hash[$off + 1]) << 16)
              | (ord($hash[$off + 2]) << 8) | ord($hash[$off + 3]);
        return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
    }

    function jc_totp_passo_atual(): int {
        return intdiv(time(), 30);
    }

    /**
     * Confere um código. @return int|null o intervalo aceito (para gravar) ou null
     */
    function jc_totp_verificar(string $segredo_b32, string $codigo, ?int $ultimo_passo): ?int {
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';
        if (strlen($codigo) !== 6) {
            return null;
        }
        $atual = jc_totp_passo_atual();
        foreach ([0, -1, 1] as $d) {
            $p = $atual + $d;
            if ($ultimo_passo !== null && $p <= $ultimo_passo) {
                continue;   // código já usado (ou mais antigo que o último usado)
            }
            if (hash_equals(jc_totp_codigo($segredo_b32, $p), $codigo)) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Grava o intervalo aceito de forma atômica (só avança): se outra
     * requisição já usou o mesmo código, esta perde. @return bool
     */
    function jc_totp_gravar_passo(mysqli $conn, int $admin_id, int $passo): bool {
        $stmt = $conn->prepare(
            "UPDATE administradores SET mfa_ultimo_passo = ?
             WHERE id = ? AND (mfa_ultimo_passo IS NULL OR mfa_ultimo_passo < ?)"
        );
        $stmt->bind_param("iii", $passo, $admin_id, $passo);
        $stmt->execute();
        $ok = $stmt->affected_rows === 1;
        $stmt->close();
        return $ok;
    }

    /** Endereço otpauth:// para cadastrar no app autenticador. */
    function jc_totp_uri(string $segredo_b32, string $email): string {
        $emissor = 'JovemConnect Admin';
        return 'otpauth://totp/' . rawurlencode($emissor . ':' . $email)
             . '?secret=' . $segredo_b32 . '&issuer=' . rawurlencode($emissor) . '&algorithm=SHA1&digits=6&period=30';
    }
}
