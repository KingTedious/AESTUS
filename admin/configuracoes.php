<?php
/* ============================================================
   admin/configuracoes.php — configurações mínimas (Stage 7)

   - Estado do ambiente (somente leitura): migração, HTTPS, tempos de
     sessão, administradores ativos e sem segundo fator, serviço de IA.
   - Minha conta: trocar a senha (com reautenticação; encerra as outras
     sessões) e "encerrar minhas outras sessões".
   Criação de administradores, papéis e segundo fator: só pela linha de
   comando (scripts/admin.php) — não há tela para isso, de propósito.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';
$admin = jc_admin_exigir('configuracoes.ver');
$id = intval($admin['id']);
$erro = null;

if (jc_admin_post()) {
    $acao = (string) ($_POST['acao'] ?? '');
    if (!jc_admin_confirmou()) {
        jc_admin_flash('erro', 'Marque a caixa de confirmação para concluir.');
        jc_admin_redirecionar('configuracoes.php');
    }
    if ($e = jc_admin_exigir_reauth($conn, $admin)) {
        jc_admin_flash('erro', $e);
        jc_admin_redirecionar('configuracoes.php');
    }
    if ($acao === 'senha') {
        $nova = (string) ($_POST['nova_senha'] ?? '');
        $rep  = (string) ($_POST['nova_senha2'] ?? '');
        if ($nova !== $rep) {
            $erro = 'As duas senhas novas não são iguais.';
        } elseif ($p = jc_admin_senha_problema($nova, (string) $admin['email'])) {
            $erro = $p;
        } else {
            $hash = password_hash($nova, PASSWORD_DEFAULT);
            jc_admin_transacao($conn, function () use ($conn, $id, $hash) {
                $stmt = $conn->prepare("UPDATE administradores SET senha_hash = ?, senha_alterada_em = NOW(), sessao_versao = sessao_versao + 1 WHERE id = ?");
                $stmt->bind_param("si", $hash, $id);
                $stmt->execute();
                $stmt->close();
                jc_admin_auditar($conn, $id, 'senha.alterada', 'administrador', $id, 'sucesso', null, null, ['senha_hash', 'sessao_versao']);
            });
            $_SESSION['adm_versao'] = intval(jc_admin_carregar($conn, $id)['sessao_versao']);
            session_regenerate_id(true);
            jc_admin_flash('sucesso', 'Senha alterada. As outras sessões desta conta foram encerradas.');
            jc_admin_redirecionar('configuracoes.php');
        }
    } elseif ($acao === 'sessoes') {
        jc_admin_transacao($conn, function () use ($conn, $id) {
            $stmt = $conn->prepare("UPDATE administradores SET sessao_versao = sessao_versao + 1 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            jc_admin_auditar($conn, $id, 'sessoes.encerradas', 'administrador', $id, 'sucesso', null, null, ['sessao_versao']);
        });
        $_SESSION['adm_versao'] = intval(jc_admin_carregar($conn, $id)['sessao_versao']);
        session_regenerate_id(true);
        jc_admin_flash('sucesso', 'As outras sessões desta conta foram encerradas. Esta continua aberta.');
        jc_admin_redirecionar('configuracoes.php');
    } else {
        jc_admin_flash('erro', 'Ação desconhecida.');
        jc_admin_redirecionar('configuracoes.php');
    }
}

$ativos  = intval($conn->query("SELECT COUNT(*) AS n FROM administradores WHERE ativo = 1")->fetch_assoc()['n'] ?? 0);
$sem_mfa = intval($conn->query("SELECT COUNT(*) AS n FROM administradores WHERE ativo = 1 AND mfa_segredo IS NULL")->fetch_assoc()['n'] ?? 0);

jc_admin_inicio('Configurações', 'configuracoes.php', $admin);
if ($erro) {
    echo '<div class="adm-alerta adm-alerta-erro" role="alert">' . jc_admin_icone('x-circle') . '<div>' . jc_e($erro) . '</div></div>';
}

echo '<div class="adm-grade-2"><section class="adm-cartao" aria-labelledby="t-amb"><h2 id="t-amb">Ambiente</h2><dl class="adm-dl">'
   . '<dt>Migração da Stage 7</dt><dd>' . jc_admin_selo('ok', 'Aplicada') . '</dd>'
   . '<dt>Conexão</dt><dd>' . (jc_admin_https() ? jc_admin_selo('ok', 'HTTPS') : jc_admin_selo(jc_admin_local() ? 'aviso' : 'erro', 'Sem HTTPS' . (jc_admin_local() ? ' (local)' : ''))) . '</dd>'
   . '<dt>Fim da sessão por inatividade</dt><dd>' . intdiv(jc_admin_ocioso_seg(), 60) . ' min <span class="adm-fraco">(JC_ADMIN_OCIOSO_SEG)</span></dd>'
   . '<dt>Duração máxima da sessão</dt><dd>' . round(jc_admin_max_seg() / 3600, 1) . ' h <span class="adm-fraco">(JC_ADMIN_MAX_SEG)</span></dd>'
   . '<dt>Confirmação de senha vale por</dt><dd>' . intdiv(jc_admin_reauth_seg(), 60) . ' min</dd>'
   . '<dt>Limite de tentativas</dt><dd>5 por e-mail e 20 por IP a cada 15 min</dd>'
   . '<dt>Administradores ativos</dt><dd>' . jc_admin_num($ativos) . ($sem_mfa ? ' ' . jc_admin_selo('aviso', $sem_mfa . ' sem segundo fator') : '') . '</dd>'
   . '<dt>Análise complementar por IA</dt><dd>Serviço Flask local (<code>127.0.0.1:5000</code>, rota /moderacao) '
   . '<span class="adm-fraco">(a chave do Gemini fica só no servidor e nunca aparece aqui)</span></dd>'
   . '</dl></section>';

echo '<section class="adm-cartao" aria-labelledby="t-papel"><h2 id="t-papel">Seu papel e permissões</h2>'
   . '<p>Papel: <strong>' . jc_e($admin['papel']) . '</strong>. Papéis e novos administradores só pela linha de comando '
   . '(<code>php scripts/admin.php</code>), nunca por esta tela.</p><ul>';
foreach (jc_admin_permissoes() as $p => $desc) {
    if (jc_admin_pode($admin, $p)) {
        echo '<li><code>' . jc_e($p) . '</code> — ' . jc_e($desc) . '</li>';
    }
}
echo '</ul></section></div>';

echo '<section class="adm-cartao" aria-labelledby="t-conta"><h2 id="t-conta">Minha conta</h2><dl class="adm-dl">'
   . '<dt>Nome</dt><dd>' . jc_e($admin['nome']) . '</dd><dt>E-mail</dt><dd class="adm-mono">' . jc_e($admin['email']) . '</dd>'
   . '<dt>Segundo fator</dt><dd>' . (!empty($admin['mfa_ativo']) ? jc_admin_selo('ok', 'Ativo') : jc_admin_selo('aviso', 'Desativado')) . '</dd>'
   . '<dt>Último login</dt><dd>' . jc_e(jc_admin_data($admin['ultimo_login_em'])) . '</dd>'
   . '<dt>Senha alterada em</dt><dd>' . jc_e(jc_admin_data($admin['senha_alterada_em'])) . '</dd></dl>';

echo '<details class="adm-detalhes adm-mt"' . ($erro ? ' open' : '') . '><summary>' . jc_admin_icone('key') . ' Trocar minha senha</summary><div>'
   . '<form method="post" action="configuracoes.php">' . jc_admin_csrf_campo() . '<input type="hidden" name="acao" value="senha">'
   . '<div class="adm-campo"><label for="nova">Nova senha</label><input type="password" id="nova" name="nova_senha" autocomplete="new-password" minlength="12" maxlength="200" required aria-describedby="nova-ajuda">'
   . '<p class="adm-ajuda" id="nova-ajuda">No mínimo 12 caracteres; evite palavras óbvias e não use o seu e-mail. Um gerenciador de senhas ajuda.</p></div>'
   . '<div class="adm-campo"><label for="nova2">Repita a nova senha</label><input type="password" id="nova2" name="nova_senha2" autocomplete="new-password" minlength="12" maxlength="200" required></div>';
if (!jc_admin_reauth_recente()) {
    echo '<fieldset class="adm-reauth"><legend>Confirme sua identidade</legend><div class="adm-campo"><label for="rs">Senha atual</label>'
       . '<input type="password" id="rs" name="reauth_senha" autocomplete="current-password" required></div>'
       . (!empty($admin['mfa_ativo']) ? '<div class="adm-campo"><label for="rc">Código do autenticador</label><input type="text" id="rc" name="reauth_codigo" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required></div>' : '')
       . '</fieldset>';
}
echo '<div class="adm-campo adm-campo-check"><input type="checkbox" id="cf-senha" name="confirmo" value="1" required><label for="cf-senha">Confirmo a troca de senha (as outras sessões serão encerradas).</label></div>'
   . '<button type="submit" class="adm-btn">Trocar senha</button></form></div></details>';

echo '<details class="adm-detalhes"><summary>' . jc_admin_icone('log-out') . ' Encerrar minhas outras sessões</summary><div>'
   . '<p>Encerra a Central em todos os outros navegadores e computadores onde esta conta estiver aberta. Esta sessão continua.</p>'
   . '<form method="post" action="configuracoes.php">' . jc_admin_csrf_campo() . '<input type="hidden" name="acao" value="sessoes">';
if (!jc_admin_reauth_recente()) {
    echo '<fieldset class="adm-reauth"><legend>Confirme sua identidade</legend><div class="adm-campo"><label for="rs2">Senha atual</label>'
       . '<input type="password" id="rs2" name="reauth_senha" autocomplete="current-password" required></div>'
       . (!empty($admin['mfa_ativo']) ? '<div class="adm-campo"><label for="rc2">Código do autenticador</label><input type="text" id="rc2" name="reauth_codigo" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required></div>' : '')
       . '</fieldset>';
}
echo '<div class="adm-campo adm-campo-check"><input type="checkbox" id="cf-sess" name="confirmo" value="1" required><label for="cf-sess">Confirmo que quero encerrar as outras sessões.</label></div>'
   . '<button type="submit" class="adm-btn adm-btn-sec">Encerrar outras sessões</button></form></div></details>';
echo '<p class="adm-ajuda adm-mt">Para ativar, trocar ou remover o segundo fator, ou para criar e desativar administradores, use '
   . '<code>php scripts/admin.php</code> no servidor (veja docs/ADMINISTRACAO_E_MODERACAO.md).</p></section>';
jc_admin_fim();
