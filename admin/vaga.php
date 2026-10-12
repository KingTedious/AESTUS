<?php
/* ============================================================
   admin/vaga.php — ficha e moderação de uma vaga (Stage 7)

   Estado de moderação SEPARADO dos demais dados da vaga:
     aprovada | sinalizada → visível para os jovens
     pendente | rejeitada | suspensa → fora do ar (a empresa continua
     vendo a vaga em "Minhas vagas", com o aviso do motivo)
   Toda decisão exige motivo, justificativa e confirmação; nada volta
   ao ar sozinho. A correção de texto NÃO mexe no local de trabalho
   escolhido pela empresa (as colunas de endereço/local nem entram
   no formulário).
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';

$id = jc_admin_post() ? (ctype_digit((string) ($_POST['id'] ?? '')) ? intval($_POST['id']) : 0) : jc_admin_get_int('id');
$acao = jc_admin_post() ? (string) ($_POST['acao'] ?? '') : '';
$admin = jc_admin_exigir(match ($acao) {
    'moderar' => 'vagas.moderar',
    'editar'  => 'vagas.editar',
    default   => 'vagas.ver',
});
if ($id <= 0) {
    jc_admin_pagina_erro(404, 'Vaga não encontrada', 'Informe uma vaga válida.');
}

$erros_edicao = [];
$valores_edicao = null;
$erro_moderacao = null;

if (jc_admin_post()) {
    if (!jc_admin_confirmou()) {
        jc_admin_flash('erro', 'Marque a caixa de confirmação para concluir.');
        jc_admin_redirecionar('vaga.php', ['id' => $id]);
    }
    if ($acao === 'moderar') {
        $r = jc_admin_vaga_moderar($conn, $admin, $id, (string) ($_POST['decisao'] ?? ''), (string) ($_POST['motivo'] ?? ''),
                                   (string) ($_POST['justificativa'] ?? ''), ($_POST['resolver_itens'] ?? '') === '1');
        if ($r['ok']) {
            $estados = jc_admin_estados_vaga();
            jc_admin_flash('sucesso', 'Moderação registrada: ' . ($estados[$r['de']] ?? $r['de']) . ' → ' . ($estados[$r['para']] ?? $r['para'])
                . ($r['itens'] ? '. ' . $r['itens'] . ' sinalização(ões) da fila resolvida(s).' : '.'));
            jc_admin_redirecionar('vaga.php', ['id' => $id]);
        }
        $erro_moderacao = $r['erro'];
    } elseif ($acao === 'editar') {
        if ($e = jc_admin_exigir_reauth($conn, $admin)) {
            jc_admin_flash('erro', $e);
            jc_admin_redirecionar('vaga.php', ['id' => $id]);
        }
        $entrada = is_array($_POST['campos'] ?? null) ? $_POST['campos'] : [];
        $r = jc_admin_editar($conn, $admin, 'vaga', $id, $entrada, (string) ($_POST['justificativa'] ?? ''));
        if ($r['ok']) {
            jc_admin_flash($r['campos'] ? 'sucesso' : 'info', $r['campos']
                ? 'Vaga corrigida (' . count($r['campos']) . ' campo(s)). O local de trabalho não foi alterado.'
                : 'Nada foi alterado: os valores enviados são iguais aos atuais.');
            jc_admin_redirecionar('vaga.php', ['id' => $id]);
        }
        $erros_edicao = $r['erros'];
        $valores_edicao = $entrada;
    } else {
        jc_admin_flash('erro', 'Ação desconhecida.');
        jc_admin_redirecionar('vaga.php', ['id' => $id]);
    }
}

$v = jc_admin_vaga_carregar($conn, $id);
if (!$v) {
    jc_admin_pagina_erro(404, 'Vaga não encontrada', 'Essa vaga não existe (ou foi excluída pela empresa).');
}
$acoes_nomes = jc_admin_acoes();
$estados = jc_admin_estados_vaga();
jc_admin_inicio('Vaga: ' . $v['titulo'], 'vagas.php', $admin, ['Vagas' => 'vagas.php', '#' . $id => null]);

if ($erro_moderacao) {
    echo '<div class="adm-alerta adm-alerta-erro" role="alert">' . jc_admin_icone('x-circle') . '<div>' . jc_e($erro_moderacao) . '</div></div>';
}
if ($erros_edicao) {
    echo '<div class="adm-alerta adm-alerta-erro" role="alert">' . jc_admin_icone('x-circle') . '<div><strong>Corrija os campos:</strong><ul>';
    foreach ($erros_edicao as $m) {
        echo '<li>' . jc_e($m) . '</li>';
    }
    echo '</ul></div></div>';
}
$visivel = in_array($v['moderacao_status'], ['aprovada', 'sinalizada'], true) && $v['empresa_status'] !== 'suspensa';

echo '<div class="adm-grade-2"><section class="adm-cartao" aria-labelledby="t-situacao"><h2 id="t-situacao">Situação</h2><dl class="adm-dl">'
   . '<dt>Moderação</dt><dd>' . jc_admin_selo_vaga($v['moderacao_status'])
   . ($v['moderacao_em'] ? ' <span class="adm-fraco">desde ' . jc_e(jc_admin_data($v['moderacao_em'])) . '</span>' : '') . '</dd>'
   . '<dt>Motivo mostrado à empresa</dt><dd>' . jc_e($v['moderacao_motivo'] ? (jc_moderacao_motivos_vaga()[$v['moderacao_motivo']] ?? $v['moderacao_motivo']) : '—') . '</dd>'
   . '<dt>Visível para os jovens</dt><dd>' . ($visivel ? jc_admin_selo('ok', 'Sim') : jc_admin_selo('erro', 'Não')) . '</dd>'
   . '<dt>Empresa</dt><dd><a href="conta.php?tipo=empresa&amp;id=' . intval($v['empresa_id']) . '">' . jc_e($v['nome_empresa']) . '</a> '
   . jc_admin_selo_conta($v['empresa_status']) . '</dd>'
   . '<dt>Criada</dt><dd>' . jc_e(jc_admin_data($v['criado_em'])) . '</dd>'
   . '<dt>Candidaturas</dt><dd>' . jc_admin_num($v['numeros']['candidaturas']) . '</dd>'
   . '<dt>Favoritada por</dt><dd>' . jc_admin_num($v['numeros']['favoritos']) . ' jovem(ns)</dd>'
   . '<dt>Denúncias</dt><dd>' . jc_admin_num($v['numeros']['denuncias']) . '</dd>'
   . '</dl>';
if ($v['empresa_status'] === 'suspensa') {
    echo '<p class="adm-ajuda adm-mt">A empresa está suspensa: a vaga fica fora do ar enquanto durar a suspensão, qualquer que seja o estado de moderação.</p>';
}
echo '</section>';

$local = '—';
if ($v['local_trabalho'] === 'local' && $v['local_id']) {
    $local = 'Local cadastrado: ' . ($v['local_nome'] ?: ucfirst((string) $v['local_tipo'])) . ' (' . trim(($v['local_cidade'] ?? '') . ' / ' . ($v['local_estado'] ?? ''), ' /') . ')';
} elseif ($v['local_trabalho'] === 'empresa') {
    $local = 'Endereço principal da empresa';
} elseif ($v['local_trabalho'] === null) {
    $local = 'Não informado (vaga anterior aos locais de trabalho)';
}
echo '<section class="adm-cartao" aria-labelledby="t-dados"><h2 id="t-dados">Dados da vaga</h2><dl class="adm-dl">'
   . '<dt>Cidade</dt><dd>' . jc_e(trim(($v['cidade'] ?? '') . ' / ' . ($v['estado'] ?? ''), ' /') ?: '—') . '</dd>'
   . '<dt>Local de trabalho</dt><dd>' . jc_e($local) . '</dd>'
   . '<dt>Contrato</dt><dd>' . jc_e($v['tipo_contrato'] ?: '—') . '</dd>'
   . '<dt>Área</dt><dd>' . jc_e($v['area'] ?: '—') . '</dd>'
   . '<dt>Salário</dt><dd>' . jc_e($v['salario'] ?: '—') . '</dd>'
   . '<dt>Carga horária</dt><dd>' . jc_e($v['carga_horaria'] ?: '—') . '</dd>'
   . '</dl></section></div>';

echo '<section class="adm-cartao" aria-labelledby="t-texto"><h2 id="t-texto">Texto publicado</h2>'
   . '<h3>Título</h3><p>' . jc_e($v['titulo']) . '</p>'
   . '<h3>Descrição</h3><div class="adm-texto-usuario">' . jc_e($v['descricao'] ?? '') . '</div>';
foreach (['Requisitos' => $v['requisitos'], 'Benefícios' => $v['beneficios']] as $rot => $lista) {
    echo '<h3>' . $rot . '</h3>';
    if ($lista) {
        echo '<ul class="adm-lista-chips">';
        foreach ($lista as $x) {
            echo '<li>' . jc_e($x) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>—</p>';
    }
}
if (jc_admin_pode($admin, 'ia.consultar')) {
    echo '<form method="post" action="ia_moderacao.php" data-ia="ia-saida" class="adm-mt">' . jc_admin_csrf_campo()
       . '<input type="hidden" name="tipo" value="vaga"><input type="hidden" name="id" value="' . intval($id) . '">'
       . '<button type="submit" class="adm-btn adm-btn-sec">' . jc_admin_icone('sparkles') . ' Pedir análise complementar por IA</button>'
       . '<p class="adm-ajuda">A IA só dá uma orientação: não aprova, não rejeita e não altera nada. Dados pessoais são mascarados antes do envio.</p></form>'
       . '<div id="ia-saida" class="adm-ia-resultado" tabindex="-1" aria-live="polite" hidden></div>';
}
echo '</section>';

// Sinalizações e denúncias
echo '<section class="adm-cartao" aria-labelledby="t-itens"><h2 id="t-itens">Sinalizações e denúncias</h2>';
if (!$v['itens']) {
    echo jc_admin_vazio('Nenhuma sinalização para esta vaga.');
} else {
    echo '<div class="adm-tabela-rolagem"><table class="adm-tabela"><caption class="adm-sr">Itens da fila desta vaga</caption><thead><tr>'
       . '<th scope="col">Item</th><th scope="col">Campo</th><th scope="col">Origem</th><th scope="col">Regras</th><th scope="col">Risco</th><th scope="col">Situação</th></tr></thead><tbody>';
    foreach ($v['itens'] as $it) {
        echo '<tr><td><a href="moderacao.php?item=' . intval($it['id']) . '">#' . intval($it['id']) . '</a></td>'
           . '<td>' . jc_e(jc_admin_rotulo_campo($it['campo'])) . '</td>'
           . '<td>' . ($it['origem'] === 'denuncia' ? 'Denúncia' : 'Regra automática') . ($it['ocorrencias'] > 1 ? ' (' . intval($it['ocorrencias']) . '×)' : '') . '</td>'
           . '<td>' . jc_admin_regras_html($it['regras']) . '</td><td>' . jc_admin_selo_risco($it['risco']) . '</td>'
           . '<td>' . ($it['estado'] === 'pendente' ? jc_admin_selo('aviso', 'Pendente') : jc_admin_selo('neutro', 'Resolvido')) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
if ($v['denuncias']) {
    $motivos_den = jc_denuncia_motivos();
    echo '<h3 class="adm-mt">Denúncias recebidas</h3><ul>';
    foreach ($v['denuncias'] as $d) {
        echo '<li><strong>' . jc_e($motivos_den[$d['motivo']] ?? $d['motivo']) . '</strong> · ' . jc_e(jc_admin_data($d['criado_em']))
           . ' · <a href="conta.php?tipo=jovem&amp;id=' . intval($d['jovem_id']) . '">jovem #' . intval($d['jovem_id']) . '</a>'
           . ($d['detalhe'] ? '<div class="adm-texto-usuario">' . jc_e($d['detalhe']) . '</div>' : '') . '</li>';
    }
    echo '</ul>';
}
echo '</section>';

// Ações
echo '<section class="adm-cartao" aria-labelledby="t-acoes"><h2 id="t-acoes">Ações</h2>';
if (jc_admin_pode($admin, 'vagas.moderar')) {
    $possiveis = array_filter(jc_admin_vaga_acoes(), fn($a) => in_array($v['moderacao_status'], $a['de'], true));
    echo '<details class="adm-detalhes"' . ($erro_moderacao ? ' open' : '') . '><summary>' . jc_admin_icone('flag') . ' Decidir a moderação</summary><div>'
       . '<form method="post" action="vaga.php">' . jc_admin_csrf_campo()
       . '<input type="hidden" name="acao" value="moderar"><input type="hidden" name="id" value="' . intval($id) . '">'
       . '<div class="adm-campo"><label for="md-decisao">Decisão</label>'
       . jc_admin_select_html('decisao', 'md-decisao', array_map(fn($a) => $a['rotulo'], $possiveis), '', 'Escolha...', true) . '</div>'
       . '<div class="adm-campo"><label for="md-motivo">Motivo</label><select name="motivo" id="md-motivo" required>'
       . '<option value="">Escolha...</option><optgroup label="Para aprovar">';
    foreach (jc_admin_motivos_aprovacao() as $k => $t) {
        echo '<option value="' . jc_e($k) . '">' . jc_e($t) . '</option>';
    }
    echo '</optgroup><optgroup label="Para revisão, rejeição ou suspensão (texto mostrado à empresa)">';
    foreach (jc_moderacao_motivos_vaga() as $k => $t) {
        echo '<option value="' . jc_e($k) . '">' . jc_e($t) . '</option>';
    }
    echo '</optgroup></select><p class="adm-ajuda">O motivo da segunda lista aparece para a empresa em "Minhas vagas".</p></div>'
       . '<div class="adm-campo adm-campo-check"><input type="checkbox" id="md-itens" name="resolver_itens" value="1" checked>'
       . '<label for="md-itens">Resolver também as sinalizações pendentes desta vaga com esta decisão</label></div>'
       . jc_admin_campos_decisao('md', $admin, false, 'Confirmo a decisão de moderação.')
       . '<div class="adm-acoes"><button type="submit" class="adm-btn">Registrar decisão</button></div></form></div></details>';
}
if (jc_admin_pode($admin, 'vagas.editar')) {
    echo '<details class="adm-detalhes"' . ($erros_edicao ? ' open' : '') . '><summary>' . jc_admin_icone('pencil') . ' Corrigir o texto da vaga</summary><div>'
       . '<p class="adm-ajuda">Corrige título, descrição, salário e carga horária com as mesmas regras do formulário da empresa. '
       . 'O local de trabalho escolhido pela empresa não é alterado. Para mudar o estado da vaga, use "Decidir a moderação".</p>'
       . '<form method="post" action="vaga.php">' . jc_admin_csrf_campo()
       . '<input type="hidden" name="acao" value="editar"><input type="hidden" name="id" value="' . intval($id) . '">';
    foreach (jc_admin_campos_editaveis('vaga') as $chave => $coluna) {
        $def = jc_campo($chave);
        $cid = 'ed-' . str_replace('.', '-', $chave);
        $valor = $valores_edicao[$chave] ?? ($v[$coluna] ?? '');
        $err = $erros_edicao[$chave] ?? null;
        $attr = ' maxlength="' . intval($def['max']) . '"' . (!empty($def['obrig']) ? ' required' : '')
              . ($err ? ' aria-invalid="true" aria-describedby="' . $cid . '-erro"' : '');
        echo '<div class="adm-campo"><label for="' . $cid . '">' . jc_e($def['rotulo']) . '</label>';
        echo $def['tipo'] === 'texto'
            ? '<textarea id="' . $cid . '" name="campos[' . jc_e($chave) . ']" rows="8"' . $attr . ' data-contador>' . jc_e($valor) . '</textarea>'
            : '<input type="text" id="' . $cid . '" name="campos[' . jc_e($chave) . ']" value="' . jc_e($valor) . '"' . $attr . '>';
        if ($err) {
            echo '<p class="adm-erro-campo" id="' . $cid . '-erro">' . jc_e($err) . '</p>';
        }
        echo '</div>';
    }
    echo jc_admin_campos_decisao('ed', $admin, true, 'Confirmo que revisei as correções.')
       . '<div class="adm-acoes"><button type="submit" class="adm-btn">Salvar correções</button></div></form></div></details>';
}
echo '</section>';

// Histórico
echo '<section class="adm-cartao" aria-labelledby="t-hist"><h2 id="t-hist">Histórico de moderação</h2>';
if (!$v['historico']) {
    echo jc_admin_vazio('Nenhuma ação administrativa registrada para esta vaga.');
} else {
    echo '<div class="adm-tabela-rolagem"><table class="adm-tabela"><caption class="adm-sr">Ações administrativas nesta vaga</caption><thead><tr>'
       . '<th scope="col">Quando</th><th scope="col">Quem</th><th scope="col">Ação</th><th scope="col">Decisão e motivo</th><th scope="col">Justificativa</th></tr></thead><tbody>';
    foreach ($v['historico'] as $h) {
        echo '<tr><td>' . jc_e(jc_admin_data($h['criado_em'])) . '</td><td>' . jc_e($h['admin_nome'] ?? 'Linha de comando') . '</td>'
           . '<td>' . jc_e($acoes_nomes[$h['acao']] ?? $h['acao']) . '</td><td><code>' . jc_e($h['motivo_codigo'] ?? '—') . '</code>'
           . ($h['campos'] ? ' <span class="adm-fraco">campos: ' . jc_e($h['campos']) . '</span>' : '') . '</td>'
           . '<td class="adm-quebra">' . jc_e($h['justificativa'] ?? '—') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</section>';

jc_admin_fim();
