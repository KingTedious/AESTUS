<?php
/* ============================================================
   includes/admin/limite.php — limite de tentativas do login
   administrativo (Stage 7)

   Tabela admin_tentativas: guarda só HASHES (SHA-256) do e-mail
   digitado e do IP — nunca o e-mail, o IP ou a senha.

   Regras (janela de 15 minutos, horário do banco):
   - 5 falhas seguidas para o MESMO e-mail → bloqueio de 15 min
     para esse e-mail (vale para qualquer IP);
   - 20 falhas do MESMO IP → bloqueio de 15 min para esse IP
     (pega quem testa muitos e-mails);
   - cada falha ainda atrasa a resposta (0,5 s, 1 s, 2 s... até 4 s).
   Um login bem-sucedido zera a contagem do e-mail.
   A mensagem é a mesma para e-mail existente ou não (não revela contas).
   ============================================================ */

if (!function_exists('jc_admin_limite_estado')) {

    define('JC_ADMIN_JANELA_MIN', 15);
    define('JC_ADMIN_MAX_EMAIL', 5);
    define('JC_ADMIN_MAX_IP', 20);

    function jc_admin_hash_email(string $email): string {
        return hash('sha256', 'jc-admin-email|' . mb_strtolower(trim($email), 'UTF-8'));
    }

    function jc_admin_hash_ip(): string {
        return hash('sha256', 'jc-admin-ip|' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    }

    /**
     * Situação do limite para este e-mail e este IP.
     * @return array{bloqueado:bool, falhas:int, motivo:?string}
     */
    function jc_admin_limite_estado(mysqli $conn, string $email): array {
        $chave = jc_admin_hash_email($email);
        $ip    = jc_admin_hash_ip();
        $min   = JC_ADMIN_JANELA_MIN;

        // falhas do e-mail depois do último sucesso, dentro da janela
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS n FROM admin_tentativas
             WHERE chave_hash = ? AND sucesso = 0
               AND criado_em > NOW() - INTERVAL {$min} MINUTE
               AND id > COALESCE((SELECT MAX(t2.id) FROM admin_tentativas t2
                                  WHERE t2.chave_hash = ? AND t2.sucesso = 1), 0)"
        );
        $stmt->bind_param("ss", $chave, $chave);
        $stmt->execute();
        $falhas_email = intval($stmt->get_result()->fetch_assoc()['n'] ?? 0);
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS n FROM admin_tentativas
             WHERE ip_hash = ? AND sucesso = 0 AND criado_em > NOW() - INTERVAL {$min} MINUTE"
        );
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $falhas_ip = intval($stmt->get_result()->fetch_assoc()['n'] ?? 0);
        $stmt->close();

        $motivo = $falhas_email >= JC_ADMIN_MAX_EMAIL ? 'email' : ($falhas_ip >= JC_ADMIN_MAX_IP ? 'ip' : null);
        return ['bloqueado' => $motivo !== null, 'falhas' => $falhas_email, 'motivo' => $motivo];
    }

    /** Registra uma tentativa (com o horário do banco). */
    function jc_admin_limite_registrar(mysqli $conn, string $email, bool $sucesso): void {
        $chave = jc_admin_hash_email($email);
        $ip    = jc_admin_hash_ip();
        $s     = $sucesso ? 1 : 0;
        $stmt  = $conn->prepare("INSERT INTO admin_tentativas (chave_hash, ip_hash, sucesso, criado_em) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ssi", $chave, $ip, $s);
        $stmt->execute();
        $stmt->close();
    }

    /** Atraso progressivo depois de uma falha (0,5 s → 4 s). Desligado em testes automatizados de CLI. */
    function jc_admin_atraso_progressivo(int $falhas): void {
        if ($falhas <= 0 || PHP_SAPI === 'cli') {
            return;
        }
        $ms = min(4000, 500 * (2 ** max(0, $falhas - 1)));
        usleep($ms * 1000);
    }

    /** Números para a página de segurança (sem identificar ninguém). */
    function jc_admin_limite_resumo(mysqli $conn): array {
        $min = JC_ADMIN_JANELA_MIN;
        $r = $conn->query(
            "SELECT
               SUM(sucesso = 0 AND criado_em > NOW() - INTERVAL 1 DAY) AS falhas_24h,
               SUM(sucesso = 1 AND criado_em > NOW() - INTERVAL 1 DAY) AS sucessos_24h,
               SUM(sucesso = 0 AND criado_em > NOW() - INTERVAL 7 DAY) AS falhas_7d
             FROM admin_tentativas"
        )->fetch_assoc();
        $emails = $conn->query(
            "SELECT COUNT(*) AS n FROM (
               SELECT chave_hash FROM admin_tentativas
               WHERE sucesso = 0 AND criado_em > NOW() - INTERVAL {$min} MINUTE
               GROUP BY chave_hash HAVING COUNT(*) >= " . JC_ADMIN_MAX_EMAIL . ") x"
        )->fetch_assoc();
        $ips = $conn->query(
            "SELECT COUNT(*) AS n FROM (
               SELECT ip_hash FROM admin_tentativas
               WHERE sucesso = 0 AND criado_em > NOW() - INTERVAL {$min} MINUTE
               GROUP BY ip_hash HAVING COUNT(*) >= " . JC_ADMIN_MAX_IP . ") x"
        )->fetch_assoc();
        return [
            'falhas_24h'      => intval($r['falhas_24h'] ?? 0),
            'sucessos_24h'    => intval($r['sucessos_24h'] ?? 0),
            'falhas_7d'       => intval($r['falhas_7d'] ?? 0),
            'emails_bloqueados' => intval($emails['n'] ?? 0),
            'ips_bloqueados'  => intval($ips['n'] ?? 0),
        ];
    }
}
