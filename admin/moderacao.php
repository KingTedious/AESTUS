<?php
/* ============================================================
   admin/moderacao.php — fila de moderação (Stage 7)

   Lista: itens criados pelas regras automáticas (origem "regra") e
   pelas denúncias de jovens (origem "denuncia"), do maior risco e
   mais antigo para o menor. Filtros: situação, risco, tipo, origem.

   ?item=N: detalhe do item — regras que dispararam, o conteúdo ATUAL
   do campo (lido do registro original; a fila guarda só o hash) e se
   ele mudou desde a sinalização; decisão com justificativa. Em itens
   de vaga, dá para aplicar a moderação da vaga junto.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';

$item_id = jc_admin_post() ? (ctype_digit((string) ($_POST['item'] ?? '')) ? intval($_POST['item']) : 0) : jc_admin_get_int('item');
$admin = jc_admin_exigir(jc_admin_post() ? 'moderacao.decidir' : 'moderacao.ver');
$erro_decisao = null;

if (jc_admin_post()) {
    if ($item_id <= 0) {
        jc_admin_redirecionar('moderacao.php');
    }
    if (!jc_admin_confirmou()) {
        jc_admin_flash('erro', 'Marque a caixa de confirmação para concluir.');
        jc_admin_redirecionar('moderacao.php', ['item' => $item_id]);
    }
    $acao_vaga = (string) ($_POST['acao_vaga'] ?? '');
    if ($acao_vaga !== '' && !jc_admin_pode($admin, 'vagas.moderar')) {
        jc_admin_pagina_erro(403, 'Acesso negado', 'Sua conta não pode moderar vagas.');
    }
    $r = jc_admin_item_decidir($conn, $admin, $item_id, (string) ($_POST['decisao'] ?? ''), (string) ($_POST['justificativa'] ?? ''),
                               $acao_vaga !== '' ? $acao_vaga : null, (string) ($_POST['motivo_vaga'] ?? ''));
    if ($r['ok']) {
        jc_admin_flash('sucesso', 'Decisão registrada para o item #' . $item_id . '.');
        jc_admin_redirecionar('moderacao.php');
    }
    $erro_decisao = $r['erro'];
}

$tipos_nome = ['vaga' => 'Vaga', 'jovem' => 'Perfil de jovem', 'empresa' => 'Perfil de empresa'];

// ── Detalhe de um item ───────────────────────────────────────────────
if ($item_id > 0) {
    $it = jc_admin_item_carregar($conn, $item_id);
    if (!$it) {
        jc_admin_pagina_erro(404, 'Item não encontrado', 'Esse item da fila não existe.');
    }
    jc_admin_inicio('Item #' . $item_id . ' da fila', 'moderacao.php', $admin, ['Fila de moderação' => 'moderacao.php', 'Item #' . $item_id => null]);
    if ($erro_decisao) {
        echo '<div class="adm-alerta adm-alerta-erro" role="alert">' . jc_admin_icone('x-circle') . '<div>' . jc_e($erro_decisao) . '</div></div>';
    }
    echo '<div class="adm-grade-2"><section class="adm-cartao" aria-labelledby="t-item"><h2 id="t-item">Sinalização</h2><dl class="adm-dl">'
       . '<dt>Situação</dt><dd>' . ($it['estado'] === 'pendente' ? jc_admin_selo('aviso', 'Pendente')
            : jc_admin_selo('neutro', 'Resolvido') . ' <span class="adm-fraco">' . jc_e(jc_admin_decisoes_item()[$it['decisao']] ?? $it['decisao'])
              . ' · ' . jc_e($it['decidido_por_nome'] ?? '—') . ' · ' . jc_e(jc_admin_data($it['decidido_em_fmt'])) . '</span>') . '</dd>'
       . '<dt>Recurso</dt><dd>' . jc_e($tipos_nome[$it['recurso_tipo']] ?? $it['recurso_tipo']) . ' · '
       . jc_admin_link_recurso($it['recurso_tipo'], $it['recurso_id'], $it['recurso_tipo'] === 'vaga' ? ($it['vaga']['titulo'] ?? null) : null) . '</dd>'
       . '<dt>Campo</dt><dd>' . jc_e(jc_admin_rotulo_campo($it['campo'])) . '</dd>'
       . '<dt>Origem</dt><dd>' . ($it['origem'] === 'denuncia' ? 'Denúncia de jovem' : 'Regra automática') . '</dd>'
       . '<dt>Risco</dt><dd>' . jc_admin_selo_risco($it['risco']) . '</dd>'
       . '<dt>Ocorrências</dt><dd>' . jc_admin_num($it['ocorrencias']) . '</dd>'
       . '<dt>Autor</dt><dd>' . jc_admin_link_recurso($it['autor_tipo'], $it['autor_id']) . '</dd>'
       . '<dt>Criado</dt><dd>' . jc_e(jc_admin_data($it['criado_em_fmt'])) . '</dd>'
       . '<dt>Atualizado</dt><dd>' . jc_e(jc_admin_data($it['atualizado_em_fmt'])) . '</dd>'
       . '</dl>';
    if ($it['estado'] !== 'pendente' && $it['justificativa']) {
        echo '<h3 class="adm-mt">Justificativa da decisão</h3><p>' . jc_e($it['justificativa']) . '</p>';
    }
    echo '</section><section class="adm-cartao" aria-labelledby="t-regras"><h2 id="t-regras">Regras que sinalizaram</h2>'
       . jc_admin_regras_html($it['regras'])
       . '<p class="adm-ajuda adm-mt">As regras indicam um risco possível, não uma conclusão sobre a intenção de quem escreveu. '
       . 'A decisão é sempre humana.</p></section></div>';

    echo '<section class="adm-cartao" aria-labelledby="t-conteudo"><h2 id="t-conteudo">Conteúdo atual do campo</h2>';
    if ($it['campo'] === 'denuncia') {
        if ($it['denuncias']) {
            $motivos_den = jc_denuncia_motivos();
            echo '<ul>';
            foreach ($it['denuncias'] as $d) {
                echo '<li><strong>' . jc_e($motivos_den[$d['motivo']] ?? $d['motivo']) . '</strong> · ' . jc_e(jc_admin_data($d['criado_em']))
                   . ' · <a href="conta.php?tipo=jovem&amp;id=' . intval($d['jovem_id']) . '">jovem #' . intval($d['jovem_id']) . '</a>'
                   . ($d['detalhe'] ? '<div class="adm-texto-usuario">' . jc_e($d['detalhe']) . '</div>' : '') . '</li>';
            }
            echo '</ul>';
        }
        echo '<p>Abra a <a href="vaga.php?id=' . intval($it['recurso_id']) . '">ficha da vaga</a> para ler o texto denunciado.</p>';
    } elseif ($it['conteudo'] === null) {
        echo '<p>Este campo não tem leitura automática; abra o recurso pelo link acima.</p>';
    } elseif (!$it['conteudo']) {
        echo jc_admin_vazio('O campo está vazio agora (o conteúdo foi apagado ou o registro não existe mais).');
    } else {
        if ($it['conteudo_igual'] === false) {
            echo '<div class="adm-alerta adm-alerta-info" role="note">' . jc_admin_icone('info')
               . '<div>O texto mudou desde a sinalização: o trecho avaliado não está mais no campo.</div></div>';
        }
        foreach ($it['conteudo'] as $texto) {
            echo '<div class="adm-texto-usuario">' . jc_e($texto) . '</div>';
        }
    }
    echo '</section>';

    if ($it['estado'] === 'pendente' && jc_admin_pode($admin, 'moderacao.decidir')) {
        echo '<section class="adm-cartao" aria-labelledby="t-decidir"><h2 id="t-decidir">Decidir</h2>'
           . '<form method="post" action="moderacao.php">' . jc_admin_csrf_campo()
           . '<input type="hidden" name="item" value="' . intval($item_id) . '">'
           . '<div class="adm-campo"><label for="dc-decisao">Decisão</label>'
           . jc_admin_select_html('decisao', 'dc-decisao', jc_admin_decisoes_item(), '', 'Escolha...', true) . '</div>';
        if ($it['recurso_tipo'] === 'vaga' && !empty($it['vaga']) && jc_admin_pode($admin, 'vagas.moderar')) {
            $estado_vaga = (string) $it['vaga']['moderacao_status'];
            $possiveis = array_filter(jc_admin_vaga_acoes(), fn($a) => in_array($estado_vaga, $a['de'], true));
            echo '<div class="adm-campo"><label for="dc-acao">Aplicar também na vaga <span class="adm-obrig">(opcional; agora: '
               . jc_e(jc_admin_estados_vaga()[$estado_vaga] ?? $estado_vaga) . ')</span></label>'
               . jc_admin_select_html('acao_vaga', 'dc-acao', array_map(fn($a) => $a['rotulo'], $possiveis), '', 'Não mudar a vaga') . '</div>'
               . '<div class="adm-campo"><label for="dc-motivo">Motivo para a vaga <span class="adm-obrig">(se for mudar a vaga)</span></label>'
               . '<select name="motivo_vaga" id="dc-motivo"><option value="">—</option><optgroup label="Para aprovar">';
            foreach (jc_admin_motivos_aprovacao() as $k => $t) {
                echo '<option value="' . jc_e($k) . '">' . jc_e($t) . '</option>';
            }
            echo '</optgroup><optgroup label="Para revisão, rejeição ou suspensão">';
            foreach (jc_moderacao_motivos_vaga() as $k => $t) {
                echo '<option value="' . jc_e($k) . '">' . jc_e($t) . '</option>';
            }
            echo '</optgroup></select></div>';
        } elseif ($it['recurso_tipo'] !== 'vaga') {
            echo '<p class="adm-ajuda">Para corrigir o texto ou suspender a conta, use a ficha da conta (link acima) e depois registre aqui a decisão.</p>';
        }
        echo jc_admin_campos_decisao('dc', $admin, false, 'Confirmo a decisão sobre este item.')
           . '<div class="adm-acoes"><button type="submit" class="adm-btn">Registrar decisão</button>'
           . '<a class="adm-btn adm-btn-sec" href="moderacao.php">Voltar à fila</a></div></form></section>';
    }
    jc_admin_fim();
    exit;
}

// ── Lista ────────────────────────────────────────────────────────────
$f = [
    'estado' => jc_admin_get_enum('estado', ['pendente', 'resolvido', 'todos'], 'pendente'),
    'risco'  => jc_admin_get_enum('risco', ['alto', 'medio', 'baixo']),
    'tipo'   => jc_admin_get_enum('tipo', ['vaga', 'jovem', 'empresa']),
    'origem' => jc_admin_get_enum('origem', ['regra', 'denuncia']),
];
$pagina = max(1, jc_admin_get_int('p', 1));
$por = 25;
$r = jc_admin_fila_buscar($conn, $f, $pagina, $por);

jc_admin_inicio('Fila de moderação', 'moderacao.php', $admin);
?>
<form method="get" action="moderacao.php" class="adm-cartao" aria-label="Filtrar a fila">
    <div class="adm-filtros">
        <div class="adm-campo">
            <label for="estado">Situação</label>
            <?= jc_admin_select_html('estado', 'estado', ['pendente' => 'Pendentes', 'resolvido' => 'Resolvidos', 'todos' => 'Todos'], $f['estado']) ?>
        </div>
        <div class="adm-campo">
            <label for="risco">Risco</label>
            <?= jc_admin_select_html('risco', 'risco', ['alto' => 'Alto', 'medio' => 'Médio', 'baixo' => 'Baixo'], $f['risco'], 'Todos') ?>
        </div>
        <div class="adm-campo">
            <label for="tipo">Tipo</label>
            <?= jc_admin_select_html('tipo', 'tipo', $tipos_nome, $f['tipo'], 'Todos') ?>
        </div>
        <div class="adm-campo">
            <label for="origem">Origem</label>
            <?= jc_admin_select_html('origem', 'origem', ['regra' => 'Regras automáticas', 'denuncia' => 'Denúncias'], $f['origem'], 'Todas') ?>
        </div>
        <div class="adm-acoes">
            <button type="submit" class="adm-btn"><?= jc_admin_icone('filter') ?> Filtrar</button>
        </div>
    </div>
</form>
<?php
echo '<p class="adm-ajuda" role="status">' . jc_admin_num($r['total']) . ($r['total'] === 1 ? ' item.' : ' itens.')
   . ' Ordem: pendentes primeiro, maior risco e mais antigos no topo.</p>';
if (!$r['linhas']) {
    echo jc_admin_vazio($f['estado'] === 'pendente' ? 'Nenhum item pendente. A fila está em dia.' : 'Nenhum item encontrado.');
} else {
    echo '<div class="adm-tabela-rolagem"><table class="adm-tabela"><caption class="adm-sr">Itens da fila de moderação</caption><thead><tr>'
       . '<th scope="col">Item</th><th scope="col">Recurso</th><th scope="col">Campo</th><th scope="col">Regras</th>'
       . '<th scope="col">Risco</th><th scope="col">Origem</th><th scope="col">Situação</th><th scope="col">Criado</th></tr></thead><tbody>';
    foreach ($r['linhas'] as $it) {
        echo '<tr><td><a href="moderacao.php?item=' . intval($it['id']) . '">#' . intval($it['id']) . ' · abrir</a></td>'
           . '<td class="adm-quebra">' . jc_e($tipos_nome[$it['recurso_tipo']] ?? $it['recurso_tipo']) . ': '
           . jc_admin_link_recurso($it['recurso_tipo'], $it['recurso_id'], $it['recurso_nome']) . '</td>'
           . '<td>' . jc_e(jc_admin_rotulo_campo($it['campo'])) . '</td>'
           . '<td>' . jc_admin_regras_html($it['regras']) . '</td>'
           . '<td>' . jc_admin_selo_risco($it['risco']) . '</td>'
           . '<td>' . ($it['origem'] === 'denuncia' ? 'Denúncia' : 'Regra') . (intval($it['ocorrencias']) > 1 ? ' (' . intval($it['ocorrencias']) . '×)' : '') . '</td>'
           . '<td>' . ($it['estado'] === 'pendente' ? jc_admin_selo('aviso', 'Pendente') : jc_admin_selo('neutro', 'Resolvido')) . '</td>'
           . '<td>' . jc_e(jc_admin_data($it['criado_em'])) . '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo jc_admin_paginacao($r['total'], $pagina, $por, 'moderacao.php', $f);
}
echo '<p class="adm-ajuda adm-mt">A fila não guarda cópia dos textos: cada item aponta para o registro original e guarda só um hash do trecho avaliado.</p>';
jc_admin_fim();
