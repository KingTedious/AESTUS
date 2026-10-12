<?php
/* ============================================================
   admin/contas.php — busca de contas de jovens e empresas (Stage 7)
   Busca por ID, nome ou e-mail; filtros por tipo, estado e data de
   cadastro; paginação. A lista mostra o e-mail parcialmente oculto e
   nenhum dado sensível (CPF, telefone, endereço, nascimento...).
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';
$admin = jc_admin_exigir('contas.ver');

$f = [
    'q'      => jc_admin_get_str('q', 100),
    'tipo'   => jc_admin_get_enum('tipo', ['jovem', 'empresa']),
    'status' => jc_admin_get_enum('status', ['ativa', 'suspensa']),
    'de'     => jc_admin_data_param(jc_admin_get_str('de', 10)) ?? '',
    'ate'    => jc_admin_data_param(jc_admin_get_str('ate', 10)) ?? '',
];
$pagina = max(1, jc_admin_get_int('p', 1));
$por = 20;
$r = jc_admin_contas_buscar($conn, $f, $pagina, $por);

jc_admin_inicio('Contas', 'contas.php', $admin);
?>
<form method="get" action="contas.php" class="adm-cartao" role="search" aria-label="Buscar contas">
    <div class="adm-filtros">
        <div class="adm-campo">
            <label for="q">ID, nome ou e-mail</label>
            <input type="search" id="q" name="q" value="<?= jc_e($f['q']) ?>" maxlength="100">
        </div>
        <div class="adm-campo">
            <label for="tipo">Tipo</label>
            <?= jc_admin_select_html('tipo', 'tipo', ['jovem' => 'Jovens', 'empresa' => 'Empresas'], $f['tipo'], 'Todos') ?>
        </div>
        <div class="adm-campo">
            <label for="status">Estado</label>
            <?= jc_admin_select_html('status', 'status', ['ativa' => 'Ativas', 'suspensa' => 'Suspensas'], $f['status'], 'Todos') ?>
        </div>
        <div class="adm-campo">
            <label for="de">Cadastro a partir de</label>
            <input type="date" id="de" name="de" value="<?= jc_e($f['de']) ?>">
        </div>
        <div class="adm-campo">
            <label for="ate">Cadastro até</label>
            <input type="date" id="ate" name="ate" value="<?= jc_e($f['ate']) ?>">
        </div>
        <div class="adm-acoes">
            <button type="submit" class="adm-btn"><?= jc_admin_icone('search') ?> Buscar</button>
            <a class="adm-btn adm-btn-sec" href="contas.php">Limpar</a>
        </div>
    </div>
</form>
<?php
echo '<p class="adm-ajuda" role="status">' . jc_admin_num($r['total']) . ($r['total'] === 1 ? ' conta encontrada.' : ' contas encontradas.') . '</p>';
if (!$r['linhas']) {
    echo jc_admin_vazio('Nenhuma conta encontrada.', 'Confira o texto da busca ou limpe os filtros.');
} else {
    echo '<div class="adm-tabela-rolagem"><table class="adm-tabela"><caption class="adm-sr">Contas encontradas</caption><thead><tr>'
       . '<th scope="col">Tipo</th><th scope="col">ID</th><th scope="col">Nome</th><th scope="col">E-mail</th>'
       . '<th scope="col">Cidade</th><th scope="col">Estado da conta</th><th scope="col">Cadastro</th></tr></thead><tbody>';
    foreach ($r['linhas'] as $c) {
        $link = 'conta.php?tipo=' . $c['tipo'] . '&id=' . intval($c['id']);
        echo '<tr><td>' . ($c['tipo'] === 'jovem' ? 'Jovem' : 'Empresa') . '</td>'
           . '<td class="adm-num-col">' . intval($c['id']) . '</td>'
           . '<td class="adm-quebra"><a href="' . jc_e($link) . '">' . jc_e($c['nome']) . '</a></td>'
           . '<td class="adm-mono">' . jc_e(jc_admin_email_mascarado($c['email'])) . '</td>'
           . '<td>' . jc_e(trim(($c['cidade'] ?? '') . (($c['estado'] ?? '') !== '' ? ' / ' . $c['estado'] : ''), ' /') ?: '—') . '</td>'
           . '<td>' . jc_admin_selo_conta($c['conta_status']) . '</td>'
           . '<td>' . jc_e(jc_admin_data($c['criado_em'], false)) . '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo jc_admin_paginacao($r['total'], $pagina, $por, 'contas.php', $f);
}
jc_admin_fim();
