<?php
/* ============================================================
   admin/auditoria.php — histórico de ações administrativas (Stage 7)
   Somente leitura (não há ação para editar ou apagar o histórico).
   Filtros + paginação + conferência da cadeia de hashes (POST).
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';

$admin = jc_admin_exigir(jc_admin_post() ? 'auditoria.verificar' : 'auditoria.ver');
$verificacao = null;
$espelho = null;
if (jc_admin_post()) {
    $verificacao = jc_admin_auditoria_verificar($conn);
    $espelho = jc_admin_auditoria_conferir_log($conn);
    $codigo = !$verificacao['ok'] ? 'quebra_' . intval($verificacao['primeira_falha'])
            : ($espelho['ok'] ? 'integro' : 'log_' . ($espelho['ausentes'] ? 'ausentes_' . count($espelho['ausentes']) : 'divergente'));
    jc_admin_auditar($conn, intval($admin['id']), 'auditoria.verificada', null, null,
                     ($verificacao['ok'] && $espelho['ok']) ? 'sucesso' : 'falha', $codigo);
}

$acoes = jc_admin_acoes();
$f = [
    'admin'     => jc_admin_get_int('admin'),
    'acao'      => jc_admin_get_enum('acao', array_keys($acoes)),
    'tipo'      => jc_admin_get_enum('tipo', ['jovem', 'empresa', 'vaga', 'moderacao_item', 'administrador']),
    'recurso'   => jc_admin_get_int('recurso'),
    'resultado' => jc_admin_get_enum('resultado', ['sucesso', 'falha', 'negado']),
    'de'        => jc_admin_data_param(jc_admin_get_str('de', 10)) ?? '',
    'ate'       => jc_admin_data_param(jc_admin_get_str('ate', 10)) ?? '',
];
$pagina = max(1, jc_admin_get_int('p', 1));
$por = 30;
$r = jc_admin_auditoria_buscar($conn, $f, $pagina, $por);
$admins = [];
foreach (jc_admin_lista_admins($conn) as $a) {
    $admins[$a['id']] = $a['nome'] . ' (#' . $a['id'] . ')';
}

jc_admin_inicio('Histórico administrativo', 'auditoria.php', $admin);

if ($verificacao !== null) {
    if ($verificacao['ok']) {
        echo '<div class="adm-alerta adm-alerta-sucesso" role="status">' . jc_admin_icone('check-circle') . '<div><strong>Histórico íntegro.</strong> '
           . jc_admin_num($verificacao['total']) . ' registros conferidos; nenhuma alteração ou remoção no meio da cadeia.'
           . ($verificacao['ultimo_hash'] ? ' Último elo: <code>' . jc_e(substr($verificacao['ultimo_hash'], 0, 16)) . '</code>' : '') . '</div></div>';
    } else {
        echo '<div class="adm-alerta adm-alerta-erro" role="alert">' . jc_admin_icone('alert-triangle') . '<div><strong>Problema no histórico</strong> a partir do registro #'
           . intval($verificacao['primeira_falha']) . ': ' . jc_e($verificacao['detalhe']) . '</div></div>';
    }
    if ($espelho['conferidos'] === 0) {
        echo '<p class="adm-ajuda">Espelho no log: nenhum registro encontrado em logs/seguranca/seguranca.log para comparar.</p>';
    } elseif ($espelho['ok']) {
        echo '<p class="adm-ajuda">Espelho no log: ' . jc_admin_num($espelho['conferidos'])
           . ' registros citados em logs/seguranca/seguranca.log conferidos — todos presentes e iguais no banco.</p>';
    } else {
        echo '<div class="adm-alerta adm-alerta-erro" role="alert">' . jc_admin_icone('alert-triangle') . '<div><strong>O log cita registros que não batem com o banco.</strong> ';
        if ($espelho['ausentes']) {
            echo 'Ausentes no banco: ' . jc_e(jc_admin_lista_ids($espelho['ausentes'])) . ' (removidos — ou o banco foi restaurado de um backup mais antigo que o log). ';
        }
        if ($espelho['diferentes']) {
            echo 'Com conteúdo diferente: ' . jc_e(jc_admin_lista_ids($espelho['diferentes'])) . '.';
        }
        echo '</div></div>';
    }
}
?>
<form method="get" action="auditoria.php" class="adm-cartao" aria-label="Filtrar o histórico">
    <div class="adm-filtros">
        <div class="adm-campo">
            <label for="f-admin">Administrador</label>
            <?= jc_admin_select_html('admin', 'f-admin', $admins, $f['admin'] ? (string) $f['admin'] : '', 'Todos') ?>
        </div>
        <div class="adm-campo">
            <label for="f-acao">Ação</label>
            <?= jc_admin_select_html('acao', 'f-acao', $acoes, $f['acao'], 'Todas') ?>
        </div>
        <div class="adm-campo">
            <label for="f-tipo">Recurso</label>
            <?= jc_admin_select_html('tipo', 'f-tipo', ['jovem' => 'Jovem', 'empresa' => 'Empresa', 'vaga' => 'Vaga',
                'moderacao_item' => 'Item da fila', 'administrador' => 'Administrador'], $f['tipo'], 'Todos') ?>
        </div>
        <div class="adm-campo">
            <label for="f-recurso">ID do recurso</label>
            <input type="number" id="f-recurso" name="recurso" min="1" value="<?= $f['recurso'] ?: '' ?>">
        </div>
        <div class="adm-campo">
            <label for="f-resultado">Resultado</label>
            <?= jc_admin_select_html('resultado', 'f-resultado', ['sucesso' => 'Sucesso', 'falha' => 'Falha', 'negado' => 'Negado'], $f['resultado'], 'Todos') ?>
        </div>
        <div class="adm-campo">
            <label for="f-de">De</label>
            <input type="date" id="f-de" name="de" value="<?= jc_e($f['de']) ?>">
        </div>
        <div class="adm-campo">
            <label for="f-ate">Até</label>
            <input type="date" id="f-ate" name="ate" value="<?= jc_e($f['ate']) ?>">
        </div>
        <div class="adm-acoes">
            <button type="submit" class="adm-btn"><?= jc_admin_icone('filter') ?> Filtrar</button>
            <a class="adm-btn adm-btn-sec" href="auditoria.php">Limpar</a>
        </div>
    </div>
</form>
<?php
if (jc_admin_pode($admin, 'auditoria.verificar')) {
    echo '<form method="post" action="auditoria.php" class="adm-cartao"><h2>Integridade</h2>'
       . '<p>Cada registro guarda o hash do anterior. A conferência refaz a cadeia inteira, aponta o primeiro registro alterado ou removido '
       . 'e compara o histórico com o espelho em logs/seguranca/seguranca.log (o que revela também remoções no fim da tabela). '
       . 'A própria conferência fica registrada no histórico.</p>'
       . jc_admin_csrf_campo()
       . '<button type="submit" class="adm-btn adm-btn-sec">' . jc_admin_icone('shield') . ' Conferir integridade agora</button></form>';
}

echo '<p class="adm-ajuda" role="status">' . jc_admin_num($r['total']) . ' registros.</p>';
if (!$r['linhas']) {
    echo jc_admin_vazio('Nenhum registro encontrado.');
} else {
    echo '<div class="adm-tabela-rolagem"><table class="adm-tabela"><caption class="adm-sr">Registros do histórico</caption><thead><tr>'
       . '<th scope="col">#</th><th scope="col">Quando</th><th scope="col">Quem</th><th scope="col">Ação</th><th scope="col">Recurso</th>'
       . '<th scope="col">Resultado</th><th scope="col">Motivo</th><th scope="col">Justificativa</th><th scope="col">Campos</th>'
       . '<th scope="col">Requisição</th></tr></thead><tbody>';
    foreach ($r['linhas'] as $l) {
        $quem = $l['admin_nome'] !== null ? $l['admin_nome'] . ' (#' . $l['admin_id'] . ')'
              : ($l['ip_anon'] === 'cli' ? 'Linha de comando' : 'Sem login');
        echo '<tr><td class="adm-num-col">' . intval($l['id']) . '</td><td>' . jc_e(jc_admin_data($l['criado_em'])) . '</td>'
           . '<td>' . jc_e($quem) . '</td><td>' . jc_e($acoes[$l['acao']] ?? $l['acao']) . '</td>'
           . '<td>' . jc_admin_link_recurso($l['recurso_tipo'], $l['recurso_id']) . '</td>'
           . '<td>' . jc_admin_selo_resultado($l['resultado']) . '</td>'
           . '<td><code>' . jc_e($l['motivo_codigo'] ?? '—') . '</code></td>'
           . '<td class="adm-quebra">' . jc_e($l['justificativa'] ?? '—') . '</td>'
           . '<td><code>' . jc_e($l['campos'] ?? '—') . '</code></td>'
           . '<td><code title="IP ' . jc_e($l['ip_anon'] ?? '-') . ' · hash ' . jc_e($l['hash_curto']) . '">' . jc_e($l['req_id'] ?? '—') . '</code></td></tr>';
    }
    echo '</tbody></table></div>';
    echo jc_admin_paginacao($r['total'], $pagina, $por, 'auditoria.php', $f);
}
echo '<p class="adm-ajuda adm-mt">O código da requisição liga o registro às linhas de logs/site/app.log e logs/seguranca/seguranca.log. '
   . 'O histórico guarda identificadores, códigos, nomes de campos e a justificativa escrita pelo administrador '
   . '(e-mails e números longos são mascarados) — nunca o conteúdo dos campos alterados.</p>';
jc_admin_fim();
