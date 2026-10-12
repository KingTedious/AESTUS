<?php
/* ============================================================
   admin/index.php — painel da Central administrativa (Stage 7)
   Indicadores agregados (COUNT/SUM, uma consulta por tabela),
   sem nenhum dado pessoal.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';
$admin = jc_admin_exigir('painel.ver');

$i = jc_admin_indicadores($conn);
$acoes = jc_admin_acoes();

jc_admin_inicio('Painel', 'index.php', $admin);

$bloco = function (string $titulo, string $icone, $grande, array $linhas, string $link = '', string $link_rot = '') {
    echo '<section class="adm-num" aria-labelledby="n-' . md5($titulo) . '"><h2 id="n-' . md5($titulo) . '">'
       . jc_admin_icone($icone) . jc_e($titulo) . '</h2><p class="adm-num-grande">' . jc_admin_num($grande) . '</p><ul>';
    foreach ($linhas as $rot => $v) {
        echo '<li><span>' . jc_e($rot) . '</span><strong>' . jc_admin_num($v) . '</strong></li>';
    }
    echo '</ul>' . ($link ? '<p class="adm-mt adm-mb0"><a href="' . jc_e($link) . '">' . jc_e($link_rot) . '</a></p>' : '') . '</section>';
};

echo '<div class="adm-grade">';
$bloco('Jovens', 'user', $i['jovens']['total'], ['Ativos' => $i['jovens']['ativas'], 'Suspensos' => $i['jovens']['suspensas'],
       'Novos (7 dias)' => $i['jovens']['novas7']], 'contas.php?tipo=jovem', 'Ver contas de jovens');
$bloco('Empresas', 'building', $i['empresas']['total'], ['Ativas' => $i['empresas']['ativas'], 'Suspensas' => $i['empresas']['suspensas'],
       'Novas (7 dias)' => $i['empresas']['novas7']], 'contas.php?tipo=empresa', 'Ver contas de empresas');
$bloco('Vagas', 'briefcase', $i['vagas']['total'], ['Visíveis para os jovens' => $i['vagas']['visiveis'],
       'Sinalizadas (no ar)' => $i['vagas']['sinalizadas'], 'Em revisão (fora do ar)' => $i['vagas']['pendentes'],
       'Rejeitadas' => $i['vagas']['rejeitadas'], 'Suspensas' => $i['vagas']['suspensas'], 'Novas (7 dias)' => $i['vagas']['novas7']],
       'vagas.php', 'Ver vagas');
$bloco('Candidaturas', 'file-text', $i['candidaturas']['total'], ['Novas (7 dias)' => $i['candidaturas']['novas7']]);
$bloco('Fila de moderação', 'flag', $i['fila']['pendentes'], ['Risco alto' => $i['fila']['alto'], 'Risco médio' => $i['fila']['medio'],
       'Risco baixo' => $i['fila']['baixo'], 'Vindos de denúncias' => $i['fila']['denuncias']], 'moderacao.php', 'Abrir a fila');
$bloco('Logins que falharam (24 h)', 'shield', $i['seguranca']['falhas_24h'], ['Logins com sucesso' => $i['seguranca']['sucessos_24h'],
       'Acessos negados' => $i['seguranca']['negados_24h'], 'E-mails bloqueados agora' => $i['seguranca']['emails_bloqueados'],
       'IPs bloqueados agora' => $i['seguranca']['ips_bloqueados']], 'seguranca.php', 'Ver eventos de segurança');
echo '</div>';
echo '<p class="adm-ajuda">Denúncias de vagas recebidas no total: ' . jc_admin_num($i['denuncias']['total'])
   . ' (' . jc_admin_num($i['denuncias']['novas7']) . ' nos últimos 7 dias). Os números são agregados: o painel não mostra dados pessoais.</p>';

echo '<section class="adm-cartao adm-mt" aria-labelledby="t-recentes"><h2 id="t-recentes">Atividade administrativa recente</h2>';
if (!$i['recentes']) {
    echo jc_admin_vazio('Nenhuma ação administrativa registrada ainda.');
} else {
    echo '<div class="adm-tabela-rolagem"><table class="adm-tabela"><caption class="adm-sr">Últimas ações administrativas</caption>'
       . '<thead><tr><th scope="col">Quando</th><th scope="col">Quem</th><th scope="col">Ação</th><th scope="col">Recurso</th><th scope="col">Resultado</th></tr></thead><tbody>';
    foreach ($i['recentes'] as $r) {
        echo '<tr><td>' . jc_e(jc_admin_data($r['criado_em'])) . '</td>'
           . '<td>' . jc_e($r['admin_nome'] ?? ($r['acao'] === 'login' ? 'Tentativa sem login' : 'Linha de comando')) . '</td>'
           . '<td>' . jc_e($acoes[$r['acao']] ?? $r['acao']) . '</td>'
           . '<td>' . jc_admin_link_recurso($r['recurso_tipo'], $r['recurso_id']) . '</td>'
           . '<td>' . jc_admin_selo_resultado($r['resultado']) . '</td></tr>';
    }
    echo '</tbody></table></div><p class="adm-mt adm-mb0"><a href="auditoria.php">Ver o histórico completo</a></p>';
}
echo '</section>';

jc_admin_fim();
