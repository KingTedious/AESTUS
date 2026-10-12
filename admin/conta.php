<?php
/* ============================================================
   admin/conta.php — ficha de uma conta (jovem ou empresa) (Stage 7)

   Mostra só dados não sensíveis (nunca senha/hash, CPF, nascimento,
   telefone, CEP/endereço, códigos de verificação ou o currículo) e o
   conteúdo PÚBLICO do perfil, que é o que a moderação avalia.
   Ações (POST + CSRF + justificativa + confirmação + senha):
     editar     → corrige campos não sensíveis com a validação pública;
     suspender  → bloqueia o acesso e esconde o conteúdo (nada é apagado);
     reativar   → desfaz a suspensão.
   Exclusão definitiva NÃO existe na Central (ver o quadro na página).
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';

$tipo = jc_admin_get_enum('tipo', ['jovem', 'empresa']);
$id   = jc_admin_get_int('id');
if (jc_admin_post()) {
    $tipo = in_array($_POST['tipo'] ?? '', ['jovem', 'empresa'], true) ? $_POST['tipo'] : '';
    $id   = ctype_digit((string) ($_POST['id'] ?? '')) ? intval($_POST['id']) : 0;
}
$acao = jc_admin_post() ? (string) ($_POST['acao'] ?? '') : '';
$permissao = match ($acao) {
    'editar' => 'contas.editar',
    'suspender', 'reativar' => 'contas.suspender',
    default => 'contas.ver',
};
$admin = jc_admin_exigir($permissao);

if ($tipo === '' || $id <= 0) {
    jc_admin_pagina_erro(404, 'Conta não encontrada', 'Informe uma conta válida.');
}

$erros_edicao = [];
$valores_edicao = null;
$erro_status = null;

if (jc_admin_post()) {
    $voltar = ['tipo' => $tipo, 'id' => $id];
    if (!jc_admin_confirmou()) {
        jc_admin_flash('erro', 'Marque a caixa de confirmação para concluir.');
        jc_admin_redirecionar('conta.php', $voltar);
    }
    if ($e = jc_admin_exigir_reauth($conn, $admin)) {
        jc_admin_flash('erro', $e);
        jc_admin_redirecionar('conta.php', $voltar);
    }
    if ($acao === 'editar') {
        $entrada = is_array($_POST['campos'] ?? null) ? $_POST['campos'] : [];
        $r = jc_admin_editar($conn, $admin, $tipo, $id, $entrada, (string) ($_POST['justificativa'] ?? ''));
        if ($r['ok']) {
            jc_admin_flash($r['campos'] ? 'sucesso' : 'info', $r['campos']
                ? 'Dados corrigidos (' . count($r['campos']) . ' campo(s)). A alteração foi registrada no histórico.'
                : 'Nada foi alterado: os valores enviados são iguais aos atuais.');
            jc_admin_redirecionar('conta.php', $voltar);
        }
        $erros_edicao = $r['erros'];
        $valores_edicao = $entrada;
    } elseif ($acao === 'suspender' || $acao === 'reativar') {
        $r = jc_admin_conta_status($conn, $admin, $tipo, $id, $acao === 'suspender' ? 'suspensa' : 'ativa',
                                   (string) ($_POST['motivo'] ?? ''), (string) ($_POST['justificativa'] ?? ''));
        if ($r['ok']) {
            jc_admin_flash('sucesso', $acao === 'suspender'
                ? 'Conta suspensa. As sessões abertas dela serão encerradas no próximo acesso.'
                : 'Conta reativada.');
            jc_admin_redirecionar('conta.php', $voltar);
        }
        $erro_status = $r['erro'];
    } else {
        jc_admin_flash('erro', 'Ação desconhecida.');
        jc_admin_redirecionar('conta.php', $voltar);
    }
}

$c = jc_admin_conta_carregar($conn, $tipo, $id);
if (!$c) {
    jc_admin_pagina_erro(404, 'Conta não encontrada', 'Essa conta não existe (ou foi excluída pelo próprio titular).');
}
$acoes_nomes = jc_admin_acoes();
$titulo = ($tipo === 'jovem' ? 'Jovem: ' : 'Empresa: ') . $c['nome_exibicao'];
jc_admin_inicio($titulo, 'contas.php', $admin, ['Contas' => 'contas.php', '#' . $id => null]);

if ($erros_edicao) {
    echo '<div class="adm-alerta adm-alerta-erro" role="alert">' . jc_admin_icone('x-circle') . '<div><strong>Corrija os campos:</strong><ul>';
    foreach ($erros_edicao as $msg) {
        echo '<li>' . jc_e($msg) . '</li>';
    }
    echo '</ul></div></div>';
}
if ($erro_status) {
    echo '<div class="adm-alerta adm-alerta-erro" role="alert">' . jc_admin_icone('x-circle') . '<div>' . jc_e($erro_status) . '</div></div>';
}

$sim = fn($v) => intval($v) ? 'Sim' : 'Não';
echo '<div class="adm-grade-2"><section class="adm-cartao" aria-labelledby="t-dados"><h2 id="t-dados">Dados da conta</h2><dl class="adm-dl">';
echo '<dt>Estado</dt><dd>' . jc_admin_selo_conta($c['conta_status'])
   . ($c['conta_status_em'] ? ' <span class="adm-fraco">desde ' . jc_e(jc_admin_data($c['conta_status_em'])) . '</span>' : '') . '</dd>';
echo '<dt>ID</dt><dd>' . intval($c['id']) . '</dd>';
echo '<dt>' . ($tipo === 'jovem' ? 'Nome' : 'Empresa') . '</dt><dd>' . jc_e($c['nome_exibicao']) . '</dd>';
echo '<dt>E-mail</dt><dd class="adm-mono">' . jc_e($c['email']) . '</dd>';
echo '<dt>Cidade</dt><dd>' . jc_e(trim(($c['cidade'] ?? '') . ' / ' . ($c['estado'] ?? ''), ' /') ?: '—') . '</dd>';
echo '<dt>Cadastro</dt><dd>' . jc_e(jc_admin_data($c['criado_em'])) . '</dd>';
echo '<dt>E-mail verificado</dt><dd>' . $sim($c['email_verificado']) . '</dd>';
echo '<dt>Telefone verificado</dt><dd>' . $sim($c['telefone_verificado']) . '</dd>';
if ($tipo === 'jovem') {
    echo '<dt>Último acesso</dt><dd>' . jc_e(jc_admin_data($c['ultimo_login'])) . '</dd>';
    echo '<dt>Perfil visível para empresas</dt><dd>' . $sim($c['perfil_publico']) . '</dd>';
    echo '<dt>Currículo enviado</dt><dd>' . $sim($c['tem_curriculo']) . ' <span class="adm-fraco">(o arquivo não é aberto pela Central)</span></dd>';
    echo '<dt>Candidaturas</dt><dd>' . jc_admin_num($c['numeros']['candidaturas']) . '</dd>';
    echo '<dt>Favoritos</dt><dd>' . jc_admin_num($c['numeros']['favoritos']) . '</dd>';
    echo '<dt>Denúncias feitas</dt><dd>' . jc_admin_num($c['numeros']['denuncias_feitas']) . '</dd>';
} else {
    echo '<dt>CNPJ</dt><dd class="adm-mono">' . jc_e($c['cnpj']) . '</dd>';
    echo '<dt>Perfil verificado</dt><dd>' . $sim($c['perfil_verificado']) . '</dd>';
    echo '<dt>Vagas</dt><dd>' . jc_admin_num($c['numeros']['vagas']) . ' (' . jc_admin_num($c['numeros']['vagas_visiveis'])
       . ' com moderação aprovada/sinalizada, ' . jc_admin_num($c['numeros']['vagas_fora']) . ' fora do ar)</dd>';
    echo '<dt>Candidaturas recebidas</dt><dd>' . jc_admin_num($c['numeros']['candidaturas']) . '</dd>';
    echo '<dt>Locais de trabalho</dt><dd>' . jc_admin_num($c['numeros']['locais']) . '</dd>';
}
echo '</dl><p class="adm-ajuda adm-mt">Dados sensíveis (senha, CPF, data de nascimento, telefone, endereço, códigos de verificação) '
   . 'não são exibidos nem consultados pela Central.</p></section>';

// Conteúdo público do perfil
echo '<section class="adm-cartao" aria-labelledby="t-publico"><h2 id="t-publico">Conteúdo público do perfil</h2>';
if ($tipo === 'jovem') {
    echo '<h3>Área de interesse</h3><p>' . jc_e($c['area_interesse'] ?: '—') . '</p>';
    echo '<h3>Resumo profissional</h3>' . ($c['resumo_profissional'] !== null && $c['resumo_profissional'] !== ''
        ? '<div class="adm-texto-usuario">' . jc_e($c['resumo_profissional']) . '</div>' : '<p>—</p>');
    echo '<h3>Habilidades</h3>';
    if ($c['habilidades']) {
        echo '<ul class="adm-lista-chips">';
        foreach ($c['habilidades'] as $h) {
            echo '<li>' . jc_e($h) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>—</p>';
    }
    echo '<h3>Formação</h3>';
    if ($c['formacoes']) {
        echo '<ul>';
        foreach ($c['formacoes'] as $fm) {
            echo '<li>' . jc_e($fm['titulo']) . ($fm['instituicao'] ? ' — ' . jc_e($fm['instituicao']) : '')
               . ($fm['conclusao'] ? ' (' . jc_e($fm['conclusao']) . ')' : '') . ($fm['area'] ? ' · ' . jc_e($fm['area']) : '') . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>—</p>';
    }
    echo '<h3>Experiências</h3>';
    if ($c['experiencias']) {
        foreach ($c['experiencias'] as $ex) {
            echo '<p><strong>' . jc_e($ex['titulo']) . '</strong>' . ($ex['local'] ? ' — ' . jc_e($ex['local']) : '')
               . ($ex['periodo'] ? ' <span class="adm-fraco">(' . jc_e($ex['periodo']) . ')</span>' : '') . '</p>';
            if ($ex['descricao']) {
                echo '<div class="adm-texto-usuario">' . jc_e($ex['descricao']) . '</div>';
            }
        }
    } else {
        echo '<p>—</p>';
    }
} else {
    echo '<h3>Área de atuação</h3><p>' . jc_e($c['area_atuacao'] ?: '—') . '</p>';
    echo '<h3>Sobre a empresa</h3>' . ($c['descricao'] ? '<div class="adm-texto-usuario">' . jc_e($c['descricao']) . '</div>' : '<p>—</p>');
    echo '<h3>Site e LinkedIn</h3><p class="adm-mono">' . jc_e($c['site_oficial'] ?: '—') . '<br>' . jc_e($c['linkedin'] ?: '—') . '</p>'
       . '<p class="adm-ajuda">Endereços exibidos como texto (não viram link) para não abrir sites desconhecidos a partir da Central.</p>';
    echo '<h3>Vagas recentes</h3>';
    if ($c['vagas']) {
        echo '<ul>';
        foreach ($c['vagas'] as $v) {
            echo '<li><a href="vaga.php?id=' . intval($v['id']) . '">' . jc_e($v['titulo']) . '</a> ' . jc_admin_selo_vaga($v['moderacao_status'])
               . ' <span class="adm-fraco">' . jc_e(jc_admin_data($v['criado_em'], false)) . '</span></li>';
        }
        echo '</ul><p><a href="vagas.php?empresa=' . intval($c['id']) . '">Ver todas as vagas desta empresa</a></p>';
    } else {
        echo '<p>Nenhuma vaga publicada.</p>';
    }
}
if (jc_admin_pode($admin, 'ia.consultar')) {
    echo '<form method="post" action="ia_moderacao.php" data-ia="ia-saida" class="adm-mt">' . jc_admin_csrf_campo()
       . '<input type="hidden" name="tipo" value="' . jc_e($tipo) . '"><input type="hidden" name="id" value="' . intval($id) . '">'
       . '<button type="submit" class="adm-btn adm-btn-sec">' . jc_admin_icone('sparkles') . ' Pedir análise complementar por IA</button>'
       . '<p class="adm-ajuda">A IA avalia só os textos públicos do perfil e dá uma orientação: não suspende, não altera nada. '
       . 'Dados pessoais são mascarados antes do envio.</p></form>'
       . '<div id="ia-saida" class="adm-ia-resultado" tabindex="-1" aria-live="polite" hidden></div>';
}
echo '</section></div>';

// Itens de moderação
echo '<section class="adm-cartao" aria-labelledby="t-itens"><h2 id="t-itens">Sinalizações deste perfil</h2>';
if (!$c['itens']) {
    echo jc_admin_vazio('Nenhuma sinalização para esta conta.');
} else {
    echo '<div class="adm-tabela-rolagem"><table class="adm-tabela"><caption class="adm-sr">Itens da fila ligados a esta conta</caption><thead><tr>'
       . '<th scope="col">Item</th><th scope="col">Campo</th><th scope="col">Regras</th><th scope="col">Risco</th><th scope="col">Situação</th><th scope="col">Quando</th></tr></thead><tbody>';
    foreach ($c['itens'] as $it) {
        echo '<tr><td><a href="moderacao.php?item=' . intval($it['id']) . '">#' . intval($it['id']) . '</a></td>'
           . '<td>' . jc_e(jc_admin_rotulo_campo($it['campo'])) . '</td><td>' . jc_admin_regras_html($it['regras']) . '</td>'
           . '<td>' . jc_admin_selo_risco($it['risco']) . '</td>'
           . '<td>' . ($it['estado'] === 'pendente' ? jc_admin_selo('aviso', 'Pendente') : jc_admin_selo('neutro', 'Resolvido: ' . $it['decisao'])) . '</td>'
           . '<td>' . jc_e(jc_admin_data($it['criado_em'])) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</section>';

// Ações
echo '<section class="adm-cartao" aria-labelledby="t-acoes"><h2 id="t-acoes">Ações</h2>';

if (jc_admin_pode($admin, 'contas.suspender')) {
    $suspensa = $c['conta_status'] === 'suspensa';
    echo '<details class="adm-detalhes"' . ($erro_status ? ' open' : '') . '><summary>'
       . ($suspensa ? jc_admin_icone('play') . ' Reativar a conta' : jc_admin_icone('ban') . ' Suspender a conta') . '</summary><div>';
    if ($suspensa) {
        echo '<p>A pessoa volta a poder entrar e o conteúdo dela volta a aparecer como antes.</p>';
    } elseif ($tipo === 'jovem') {
        echo '<p><strong>Efeitos:</strong> o jovem não consegue entrar e as sessões abertas são encerradas; o perfil e o currículo '
           . 'deixam de aparecer para as empresas. Candidaturas, favoritos e dados <strong>continuam guardados</strong> e voltam com a reativação.</p>';
    } else {
        echo '<p><strong>Efeitos:</strong> a empresa não consegue entrar e as sessões abertas são encerradas; as vagas dela deixam de '
           . 'aparecer para os jovens (o estado de moderação de cada vaga não muda). Vagas e candidaturas <strong>continuam guardadas</strong>.</p>';
    }
    echo '<form method="post" action="conta.php">' . jc_admin_csrf_campo()
       . '<input type="hidden" name="acao" value="' . ($suspensa ? 'reativar' : 'suspender') . '">'
       . '<input type="hidden" name="tipo" value="' . jc_e($tipo) . '"><input type="hidden" name="id" value="' . intval($id) . '">'
       . '<div class="adm-campo"><label for="st-motivo">Motivo</label>'
       . jc_admin_select_html('motivo', 'st-motivo', $suspensa ? jc_admin_motivos_reativacao() : jc_admin_motivos_suspensao(), '', 'Escolha...', true)
       . '</div>'
       . jc_admin_campos_decisao('st', $admin, true, $suspensa ? 'Confirmo que quero reativar esta conta.' : 'Confirmo que quero suspender esta conta.')
       . '<div class="adm-acoes"><button type="submit" class="adm-btn ' . ($suspensa ? '' : 'adm-btn-perigo') . '">'
       . ($suspensa ? 'Reativar conta' : 'Suspender conta') . '</button></div></form></div></details>';
}

if (jc_admin_pode($admin, 'contas.editar')) {
    $mapa = jc_admin_campos_editaveis($tipo);
    $atuais = [];
    foreach ($mapa as $chave => $coluna) {
        $atuais[$chave] = $valores_edicao[$chave] ?? ($c[$coluna] ?? '');
    }
    echo '<details class="adm-detalhes"' . ($erros_edicao ? ' open' : '') . '><summary>' . jc_admin_icone('pencil') . ' Corrigir dados do perfil</summary><div>'
       . '<p class="adm-ajuda">Use só para corrigir conteúdo impróprio ou erros evidentes. Valem as mesmas regras dos formulários do site; '
       . 'o histórico guarda os nomes dos campos alterados, não os valores.</p>'
       . '<form method="post" action="conta.php">' . jc_admin_csrf_campo()
       . '<input type="hidden" name="acao" value="editar"><input type="hidden" name="tipo" value="' . jc_e($tipo) . '">'
       . '<input type="hidden" name="id" value="' . intval($id) . '">';
    foreach ($mapa as $chave => $coluna) {
        $def = jc_campo($chave);
        $cid = 'ed-' . str_replace('.', '-', $chave);
        $err = $erros_edicao[$chave] ?? null;
        $attr = ' maxlength="' . intval($def['max']) . '"' . (!empty($def['obrig']) ? ' required' : '')
              . ($err ? ' aria-invalid="true" aria-describedby="' . $cid . '-erro"' : '');
        echo '<div class="adm-campo"><label for="' . $cid . '">' . jc_e($def['rotulo']) . '</label>';
        if ($def['tipo'] === 'texto') {
            echo '<textarea id="' . $cid . '" name="campos[' . jc_e($chave) . ']" rows="5"' . $attr . ' data-contador>' . jc_e($atuais[$chave]) . '</textarea>';
        } else {
            echo '<input type="text" id="' . $cid . '" name="campos[' . jc_e($chave) . ']" value="' . jc_e($atuais[$chave]) . '"' . $attr . '>';
        }
        if ($err) {
            echo '<p class="adm-erro-campo" id="' . $cid . '-erro">' . jc_e($err) . '</p>';
        }
        echo '</div>';
    }
    echo jc_admin_campos_decisao('ed', $admin, true, 'Confirmo que revisei as correções.')
       . '<div class="adm-acoes"><button type="submit" class="adm-btn">Salvar correções</button></div></form></div></details>';
}

echo '<details class="adm-detalhes"><summary>' . jc_admin_icone('info') . ' Exclusão de conta</summary><div>'
   . '<p>A Central <strong>não exclui contas</strong>. Para afastar alguém da plataforma, use a suspensão: ela é reversível e preserva os dados '
   . '(candidaturas, vagas e histórico) para eventual revisão.</p>'
   . '<p>O próprio titular continua podendo excluir a conta pelo perfil (fluxo já existente). Pedidos de exclusão feitos por outras '
   . 'pessoas devem seguir a Política de Privacidade, fora da Central.</p></div></details>';
echo '</section>';

// Histórico
echo '<section class="adm-cartao" aria-labelledby="t-hist"><h2 id="t-hist">Histórico administrativo desta conta</h2>';
if (!$c['historico']) {
    echo jc_admin_vazio('Nenhuma ação administrativa registrada para esta conta.');
} else {
    echo '<div class="adm-tabela-rolagem"><table class="adm-tabela"><caption class="adm-sr">Ações administrativas nesta conta</caption><thead><tr>'
       . '<th scope="col">Quando</th><th scope="col">Quem</th><th scope="col">Ação</th><th scope="col">Motivo</th><th scope="col">Justificativa</th><th scope="col">Campos</th></tr></thead><tbody>';
    foreach ($c['historico'] as $h) {
        echo '<tr><td>' . jc_e(jc_admin_data($h['criado_em'])) . '</td><td>' . jc_e($h['admin_nome'] ?? 'Linha de comando') . '</td>'
           . '<td>' . jc_e($acoes_nomes[$h['acao']] ?? $h['acao']) . ' ' . ($h['resultado'] !== 'sucesso' ? jc_admin_selo_resultado($h['resultado']) : '') . '</td>'
           . '<td><code>' . jc_e($h['motivo_codigo'] ?? '—') . '</code></td>'
           . '<td class="adm-quebra">' . jc_e($h['justificativa'] ?? '—') . '</td><td><code>' . jc_e($h['campos'] ?? '—') . '</code></td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</section>';

jc_admin_fim();
