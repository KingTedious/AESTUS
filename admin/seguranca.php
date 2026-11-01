<?php
/* ============================================================
   admin/seguranca.php — eventos de segurança (Stage 7)
   - números do limite de tentativas da Central (só hashes no banco);
   - acessos negados e envios recusados registrados no histórico;
   - últimos eventos de logs/seguranca/seguranca.log (login, cadastro, conta,
     CSRF, acesso negado...), já sem dados sensíveis (o logger remove
     senha, token, e-mail, telefone, CPF e anonimiza o IP).
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';
$admin = jc_admin_exigir('seguranca.ver');

$limite = jc_admin_limite_resumo($conn);
$negados = jc_admin_select($conn,
    "SELECT a.id, DATE_FORMAT(a.criado_em, '%Y-%m-%d %H:%i:%s') AS criado_em, a.acao, a.resultado, a.motivo_codigo, a.ip_anon, ad.nome AS admin_nome
     FROM admin_auditoria a LEFT JOIN administradores ad ON ad.id = a.admin_id
     WHERE a.resultado IN ('negado', 'falha') ORDER BY a.id DESC LIMIT 20");
$prefixos = ['login' => 'Login', 'admin' => 'Central administrativa', 'csrf' => 'CSRF', 'conta' => 'Conta', 'cadastro' => 'Cadastro',
             'acesso' => 'Acesso negado', 'recuperacao' => 'Recuperação de senha', 'denuncia' => 'Denúncias'];
$prefixo = jc_admin_get_enum('evento', array_keys($prefixos));
$eventos = jc_admin_eventos_seguranca(100, $prefixo);
$acoes = jc_admin_acoes();

jc_admin_inicio('Eventos de segurança', 'seguranca.php', $admin);

echo '<div class="adm-grade">'
   . '<section class="adm-num"><h2>' . jc_admin_icone('lock') . 'Logins que falharam (24 h)</h2><p class="adm-num-grande">' . jc_admin_num($limite['falhas_24h']) . '</p>'
   . '<ul><li><span>Nos últimos 7 dias</span><strong>' . jc_admin_num($limite['falhas_7d']) . '</strong></li>'
   . '<li><span>Logins bem-sucedidos (24 h)</span><strong>' . jc_admin_num($limite['sucessos_24h']) . '</strong></li></ul></section>'
   . '<section class="adm-num"><h2>' . jc_admin_icone('shield') . 'Bloqueios ativos</h2><p class="adm-num-grande">'
   . jc_admin_num($limite['emails_bloqueados'] + $limite['ips_bloqueados']) . '</p>'
   . '<ul><li><span>E-mails (5 falhas em 15 min)</span><strong>' . jc_admin_num($limite['emails_bloqueados']) . '</strong></li>'
   . '<li><span>IPs (20 falhas em 15 min)</span><strong>' . jc_admin_num($limite['ips_bloqueados']) . '</strong></li></ul></section>'
   . '</div><p class="adm-ajuda">O banco guarda só hashes do e-mail digitado e do IP: não é possível ver quem tentou, apenas quantas vezes.</p>';

echo '<section class="adm-cartao adm-mt" aria-labelledby="t-neg"><h2 id="t-neg">Falhas e acessos negados na Central</h2>';
if (!$negados) {
    echo jc_admin_vazio('Nenhuma falha ou acesso negado registrado.');
} else {
    echo '<div class="adm-tabela-rolagem"><table class="adm-tabela"><caption class="adm-sr">Falhas e negações recentes</caption><thead><tr>'
       . '<th scope="col">Quando</th><th scope="col">Ação</th><th scope="col">Resultado</th><th scope="col">Motivo</th><th scope="col">Conta</th><th scope="col">IP (anonimizado)</th></tr></thead><tbody>';
    foreach ($negados as $n) {
        echo '<tr><td>' . jc_e(jc_admin_data($n['criado_em'])) . '</td><td>' . jc_e($acoes[$n['acao']] ?? $n['acao']) . '</td>'
           . '<td>' . jc_admin_selo_resultado($n['resultado']) . '</td><td><code>' . jc_e($n['motivo_codigo'] ?? '—') . '</code></td>'
           . '<td>' . jc_e($n['admin_nome'] ?? '—') . '</td><td class="adm-mono">' . jc_e($n['ip_anon'] ?? '—') . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</section>';

echo '<section class="adm-cartao" aria-labelledby="t-log"><h2 id="t-log">Últimos eventos do log de segurança</h2>'
   . '<form method="get" action="seguranca.php" class="adm-filtros adm-mb0"><div class="adm-campo"><label for="evento">Tipo de evento</label>'
   . jc_admin_select_html('evento', 'evento', $prefixos, $prefixo, 'Todos') . '</div>'
   . '<div class="adm-acoes"><button type="submit" class="adm-btn adm-btn-sec">' . jc_admin_icone('filter') . ' Filtrar</button></div></form>';
if (!$eventos) {
    echo jc_admin_vazio('Nenhum evento encontrado no log de segurança atual.', 'O arquivo é renovado todo dia (os anteriores ficam na pasta logs/seguranca/).');
} else {
    echo '<div class="adm-tabela-rolagem adm-mt"><table class="adm-tabela"><caption class="adm-sr">Eventos do log de segurança</caption><thead><tr>'
       . '<th scope="col">Quando</th><th scope="col">Evento</th><th scope="col">Nível</th><th scope="col">Quem</th><th scope="col">Página</th>'
       . '<th scope="col">IP</th><th scope="col">Detalhes</th></tr></thead><tbody>';
    foreach ($eventos as $e) {
        $det = [];
        foreach ($e['dados'] as $k => $v) {
            $det[] = $k . '=' . (is_scalar($v) || $v === null ? var_export($v, true) : '[...]');
        }
        echo '<tr><td>' . jc_e(jc_admin_data(str_replace('T', ' ', substr($e['ts'], 0, 19)))) . '</td>'
           . '<td><code>' . jc_e($e['evento']) . '</code></td><td>' . jc_e($e['nivel']) . '</td>'
           . '<td><code>' . jc_e($e['usuario']) . '</code></td><td class="adm-quebra"><code>' . jc_e($e['pagina']) . '</code></td>'
           . '<td class="adm-mono">' . jc_e($e['ip']) . '</td><td class="adm-quebra"><code>' . jc_e(mb_substr(implode(' · ', $det), 0, 300)) . '</code></td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</section>';
jc_admin_fim();
