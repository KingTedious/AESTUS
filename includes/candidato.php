<?php
/* ============================================================
   includes/candidato.php

   Regra ÚNICA de acesso de uma empresa aos dados de um candidato
   (perfil, currículo em PDF e análise de compatibilidade).

   A empresa pode ver o candidato quando existe relação com o
   recrutamento dela:
     1. o jovem se candidatou a uma vaga DESTA empresa; ou
     2. o jovem marcou "Permitir que empresas encontrem seu currículo"
        (perfil_publico = 1) — é o caso das sugestões por área do
        painel da empresa, que já existiam.

   O e-mail do candidato só é liberado no caso 1 (a candidatura é o
   momento em que o jovem escolhe se apresentar àquela empresa).
   CPF, CEP, endereço, telefone e data de nascimento nunca entram na
   visão da empresa.

   Stage 7: jovem com conta SUSPENSA pela administração fica
   indisponível para todas as empresas (mesma resposta de "sem
   permissão"); nada é apagado e tudo volta com a reativação.
   ============================================================ */
require_once __DIR__ . '/esquema.php';

if (!function_exists('jc_empresa_acesso_candidato')) {

    /**
     * Diz se a empresa pode ver o candidato e por quê.
     *
     * @return array{permitido: bool, publico: bool, candidaturas: array<int, array>}
     *   candidaturas = vagas DESTA empresa às quais o jovem se candidatou
     *   (id da candidatura, vaga_id, titulo, status, criado_em), mais recentes primeiro.
     *   Jovem inexistente → permitido = false (mesma resposta de "sem permissão").
     */
    function jc_empresa_acesso_candidato(mysqli $conn, int $empresa_id, int $jovem_id): array
    {
        $r = ['permitido' => false, 'publico' => false, 'candidaturas' => []];
        if ($empresa_id <= 0 || $jovem_id <= 0) {
            return $r;
        }

        $com_status = jc_stage7_disponivel($conn);
        $stmt = $conn->prepare("SELECT perfil_publico" . ($com_status ? ", conta_status" : "") . " FROM jovens WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $jovem_id);
        $stmt->execute();
        $j = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$j || ($com_status && ($j['conta_status'] ?? 'ativa') === 'suspensa')) {
            return $r;
        }
        $r['publico'] = (int) $j['perfil_publico'] === 1;

        $stmt = $conn->prepare(
            "SELECT c.id, c.vaga_id, c.status, c.criado_em, v.titulo
             FROM   candidaturas c
             JOIN   vagas v ON v.id = c.vaga_id
             WHERE  c.jovem_id = ? AND v.empresa_id = ?
             ORDER  BY c.criado_em DESC, c.id DESC"
        );
        $stmt->bind_param("ii", $jovem_id, $empresa_id);
        $stmt->execute();
        $r['candidaturas'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $r['permitido'] = $r['publico'] || !empty($r['candidaturas']);
        return $r;
    }

    /** Atalho: só o sim/não. */
    function jc_empresa_pode_ver_candidato(mysqli $conn, int $empresa_id, int $jovem_id): bool
    {
        return jc_empresa_acesso_candidato($conn, $empresa_id, $jovem_id)['permitido'];
    }
}

if (!function_exists('jc_candidato_indisponivel')) {
    /**
     * Resposta única para "candidato não existe" e "sem permissão" (não revela
     * qual dos dois). Página simples com caminho de volta ao painel.
     */
    function jc_candidato_indisponivel(string $voltar = '../dashboard/empresa.php'): never
    {
        http_response_code(404);
        $v = htmlspecialchars($voltar, ENT_QUOTES);
        echo '<!DOCTYPE html><html lang="pt-br" data-theme="dark"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
           . '<title>Perfil indisponível | JovemConnect</title>'
           . '<link rel="stylesheet" href="../assets/css/style.css">'
           . '<link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">'
           . '</head><body><main class="card perfil-indisponivel">'
           . '<h1>Perfil indisponível</h1>'
           . '<p>Este perfil não existe ou não está disponível para a sua empresa. '
           . 'Você vê os perfis de quem se candidatou às suas vagas e de quem permite ser encontrado por empresas.</p>'
           . '<a class="btn btn-primary" href="' . $v . '">Voltar ao painel</a>'
           . '</main></body></html>';
        exit;
    }
}
