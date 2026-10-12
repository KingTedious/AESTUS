<?php
/* ============================================================
   admin/vagas.php — busca de vagas (Stage 7)
   Filtros: texto (ID, título ou empresa), estado de moderação, empresa,
   área, empresa suspensa e período de criação. Paginação.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';
$admin = jc_admin_exigir('vagas.ver');

$estados = jc_admin_estados_vaga();
$areas = jc_admin_vagas_areas($conn);
$f = [
    'q'       => jc_admin_get_str('q', 100),
    'estado'  => jc_admin_get_enum('estado', array_keys($estados)),
    'empresa' => jc_admin_get_int('empresa'),
    'area'    => jc_admin_get_enum('area', $areas),
    'empresa_suspensa' => jc_admin_get_enum('empresa_suspensa', ['1']),
    'de'      => jc_admin_data_param(jc_admin_get_str('de', 10)) ?? '',
    'ate'     => jc_admin_data_param(jc_admin_get_str('ate', 10)) ?? '',
];
$pagina = max(1, jc_admin_get_int('p', 1));
$por = 20;
$r = jc_admin_vagas_buscar($conn, $f, $pagina, $por);

jc_admin_inicio('Vagas', 'vagas.php', $admin);
?>
<form method="get" action="vagas.php" class="adm-cartao" role="search" aria-label="Buscar vagas">
    <div class="adm-filtros">
        <div class="adm-campo">
            <label for="q">ID, título ou empresa</label>
            <input type="search" id="q" name="q" value="<?= jc_e($f['q']) ?>" maxlength="100">
        </div>
        <div class="adm-campo">
            <label for="estado">Moderação</label>
            <?= jc_admin_select_html('estado', 'estado', $estados, $f['estado'], 'Todas') ?>
        </div>
        <div class="adm-campo">
            <label for="area">Área</label>
            <?= jc_admin_select_html('area', 'area', array_combine($areas, $areas) ?: [], $f['area'], 'Todas') ?>
        </div>
        <div class="adm-campo">
            <label for="de">Criada a partir de</label>
            <input type="date" id="de" name="de" value="<?= jc_e($f['de']) ?>">
        </div>
        <div class="adm-campo">
            <label for="ate">Criada até</label>
            <input type="date" id="ate" name="ate" value="<?= jc_e($f['ate']) ?>">
        </div>
        <div class="adm-campo adm-campo-check">
            <input type="checkbox" id="empresa_suspensa" name="empresa_suspensa" value="1"<?= $f['empresa_suspensa'] ? ' checked' : '' ?>>
            <label for="empresa_suspensa">Só de empresas suspensas</label>
        </div>
        <?php if ($f['empresa'] > 0): ?>
            <input type="hidden" name="empresa" value="<?= intval($f['empresa']) ?>">
        <?php endif; ?>
        <div class="adm-acoes">
            <button type="submit" class="adm-btn"><?= jc_admin_icone('search') ?> Buscar</button>
            <a class="adm-btn adm-btn-sec" href="vagas.php">Limpar</a>
        </div>
    </div>
</form>
<?php
if ($f['empresa'] > 0) {
    echo '<p class="adm-ajuda">Mostrando só as vagas da empresa #' . intval($f['empresa']) . '. '
       . '<a href="conta.php?tipo=empresa&amp;id=' . intval($f['empresa']) . '">Abrir a ficha da empresa</a></p>';
}
echo '<p class="adm-ajuda" role="status">' . jc_admin_num($r['total']) . ($r['total'] === 1 ? ' vaga encontrada.' : ' vagas encontradas.') . '</p>';
if (!$r['linhas']) {
    echo jc_admin_vazio('Nenhuma vaga encontrada.', 'Confira o texto da busca ou limpe os filtros.');
} else {
    echo '<div class="adm-tabela-rolagem"><table class="adm-tabela"><caption class="adm-sr">Vagas encontradas</caption><thead><tr>'
       . '<th scope="col">ID</th><th scope="col">Título</th><th scope="col">Empresa</th><th scope="col">Cidade</th>'
       . '<th scope="col">Moderação</th><th scope="col">Candidaturas</th><th scope="col">Sinalizações abertas</th><th scope="col">Criada</th></tr></thead><tbody>';
    foreach ($r['linhas'] as $v) {
        echo '<tr><td class="adm-num-col">' . intval($v['id']) . '</td>'
           . '<td class="adm-quebra"><a href="vaga.php?id=' . intval($v['id']) . '">' . jc_e($v['titulo']) . '</a></td>'
           . '<td class="adm-quebra"><a href="conta.php?tipo=empresa&amp;id=' . intval($v['empresa_id']) . '">' . jc_e($v['nome_empresa']) . '</a>'
           . ($v['empresa_status'] === 'suspensa' ? ' ' . jc_admin_selo('erro', 'Empresa suspensa') : '') . '</td>'
           . '<td>' . jc_e(trim(($v['cidade'] ?? '') . ' / ' . ($v['estado'] ?? ''), ' /') ?: '—') . '</td>'
           . '<td>' . jc_admin_selo_vaga($v['moderacao_status']) . '</td>'
           . '<td class="adm-num-col">' . jc_admin_num($v['candidaturas']) . '</td>'
           . '<td class="adm-num-col">' . (intval($v['itens_abertos']) ? jc_admin_selo('aviso', (string) intval($v['itens_abertos'])) : '0') . '</td>'
           . '<td>' . jc_e(jc_admin_data($v['criado_em'], false)) . '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo jc_admin_paginacao($r['total'], $pagina, $por, 'vagas.php', $f);
}
jc_admin_fim();
