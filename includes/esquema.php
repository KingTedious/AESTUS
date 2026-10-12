<?php
/* ============================================================
   includes/esquema.php — detecção das migrações aditivas (Stage 7)

   O projeto evolui o banco com migrações ADITIVAS (pasta migrations/) e
   o PHP confere se as colunas novas existem antes de usá-las, para o site
   funcionar antes e depois da migração. Este arquivo concentra essa
   conferência para a migração da Stage 7
   (migrations/2026-10-11_stage7_admin_moderacao.sql):

     jc_stage7_disponivel($conn)  → true quando TODAS as tabelas e colunas
                                     da Stage 7 existem (uma consulta só,
                                     guardada até o fim da requisição).

   Sem a migração: o site público funciona exatamente como na v4.00
   (sem suspensão de contas, sem estados de moderação, sem denúncias) e
   a Central administrativa (admin/) mostra que a migração está pendente.
   ============================================================ */

if (!function_exists('jc_stage7_disponivel')) {

    /** Colunas que provam que a migração da Stage 7 foi aplicada por inteiro. */
    function jc_stage7_colunas(): array {
        return [
            ['jovens', 'conta_status'],
            ['empresas', 'conta_status'],
            ['vagas', 'moderacao_status'],
            ['administradores', 'sessao_versao'],
            ['admin_tentativas', 'ip_hash'],
            ['admin_auditoria', 'hash_anterior'],
            ['moderacao_itens', 'conteudo_hash'],
            ['denuncias', 'item_id'],
        ];
    }

    /** true quando a migração da Stage 7 está aplicada (tabelas e colunas). */
    function jc_stage7_disponivel(mysqli $conn): bool {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $pares = jc_stage7_colunas();
        $cond  = implode(' OR ', array_fill(0, count($pares), '(TABLE_NAME = ? AND COLUMN_NAME = ?)'));
        $tipos = str_repeat('ss', count($pares));
        $args  = [];
        foreach ($pares as [$t, $c]) {
            $args[] = $t;
            $args[] = $c;
        }
        try {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND ({$cond})"
            );
            $stmt->bind_param($tipos, ...$args);
            $stmt->execute();
            $n = intval($stmt->get_result()->fetch_assoc()['n'] ?? 0);
            $stmt->close();
            $ok = ($n === count($pares));
        } catch (Throwable $e) {
            // sem permissão de leitura no information_schema (raro): considera não aplicada
            $ok = false;
        }
        return $ok;
    }

    /** Lista do que falta (para a tela de "migração pendente" da Central administrativa). */
    function jc_stage7_faltando(mysqli $conn): array {
        $faltam = [];
        foreach (jc_stage7_colunas() as [$t, $c]) {
            try {
                $stmt = $conn->prepare(
                    "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
                );
                $stmt->bind_param("ss", $t, $c);
                $stmt->execute();
                $n = intval($stmt->get_result()->fetch_assoc()['n'] ?? 0);
                $stmt->close();
            } catch (Throwable $e) {
                $n = 0;
            }
            if ($n === 0) {
                $faltam[] = "{$t}.{$c}";
            }
        }
        return $faltam;
    }
}
