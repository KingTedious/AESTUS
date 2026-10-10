<?php
/* ============================================================
   includes/termos.php — versão dos Termos de Uso / Política de Privacidade
   e registro do aceite no cadastro.

   O aceite é uma AÇÃO da pessoa (marcar a caixa no fim do cadastro,
   que nunca vem marcada). Abrir os documentos não conta como aceite.
   Fica gravado em jovens/empresas: termos_versao + termos_aceitos_em
   (migração migrations/2026-10-10_stage6_aceite_termos.sql). Sem a
   migração, o cadastro continua exigindo a caixa, mas não grava a data.

   Ao publicar uma versão nova dos documentos, troque JC_TERMOS_VERSAO e
   JC_TERMOS_DATA (e o texto em includes/institucional.php).
   ============================================================ */

if (!defined('JC_TERMOS_VERSAO')) {
    define('JC_TERMOS_VERSAO', '1.0-minuta');      // até a revisão jurídica: minuta
    define('JC_TERMOS_DATA', '10/10/2026');
}

if (!function_exists('jc_termos_colunas')) {

    /** true se a tabela (jovens|empresas) já tem as colunas do aceite. */
    function jc_termos_colunas(mysqli $conn, string $tabela): bool {
        static $cache = [];
        if (!in_array($tabela, ['jovens', 'empresas'], true)) {
            return false;
        }
        if (!isset($cache[$tabela])) {
            $r = $conn->query("SHOW COLUMNS FROM `{$tabela}` LIKE 'termos_aceitos_em'");
            $cache[$tabela] = ($r instanceof mysqli_result) && $r->num_rows > 0;
        }
        return $cache[$tabela];
    }

    /** Grava versão e data do aceite para a conta recém-criada. */
    function jc_termos_registrar(mysqli $conn, string $tabela, int $id): void {
        if ($id <= 0 || !jc_termos_colunas($conn, $tabela)) {
            return;
        }
        $versao = JC_TERMOS_VERSAO;
        $stmt = $conn->prepare("UPDATE `{$tabela}` SET termos_versao = ?, termos_aceitos_em = NOW() WHERE id = ?");
        $stmt->bind_param("si", $versao, $id);
        $stmt->execute();
        $stmt->close();
    }
}
