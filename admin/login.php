<?php
/* ============================================================
   admin/login.php — entrada da Central administrativa (Stage 7)

   - Só contas da tabela administradores (criadas pela linha de comando).
   - Senha (password_verify) + código do autenticador quando ativo.
   - Mesma mensagem para qualquer erro (não revela se o e-mail existe,
     se a senha ou o código é que estava errado, ou se a conta está
     desativada); tempo de resposta parecido nos dois casos.
   - Limite de tentativas por e-mail e por IP + atraso progressivo.
   - ID de sessão novo a cada login; token CSRF também no formulário
     de login.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/admin/nucleo.php';

jc_admin_cabecalhos();
$migracao = jc_stage7_disponivel($conn);
jc_admin_sessao_iniciar();

if ($migracao && !empty($_SESSION['adm_id']) && jc_admin_sessao_problema() === null) {
    jc_admin_redirecionar('index.php');   // o painel confere a conta no banco
}

$erro   = null;
$email_digitado = '';
$motivo = jc_admin_get_enum('motivo', ['inatividade', 'tempo_total', 'navegador', 'versao', 'inativo', 'logout']);

if ($migracao && jc_admin_post()) {
    if (!jc_admin_csrf_valido()) {
        jc_log_seguranca('admin.login_csrf', 'aviso');
        $erro = 'A página ficou desatualizada. Tente entrar de novo.';
    } else {
        $email  = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
        $senha  = (string) ($_POST['senha'] ?? '');
        $codigo = (string) ($_POST['codigo'] ?? '');
        $email_digitado = mb_substr($email, 0, 100, 'UTF-8');
        if (strlen($senha) > 200) {
            $senha = substr($senha, 0, 200);
        }
        $estado = jc_admin_limite_estado($conn, $email);

        if ($estado['bloqueado']) {
            jc_admin_auditar($conn, null, 'login', null, null, 'negado', 'limite_' . $estado['motivo']);
            $erro = 'Muitas tentativas seguidas. Por segurança, aguarde 15 minutos e tente de novo.';
        } else {
            $adm = null;
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 100) {
                $stmt = $conn->prepare(
                    "SELECT id, email, nome, senha_hash, papel, ativo, mfa_segredo, mfa_ultimo_passo, sessao_versao
                     FROM administradores WHERE email = ? LIMIT 1"
                );
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $adm = $stmt->get_result()->fetch_assoc() ?: null;
                $stmt->close();
            }

            if ($adm === null) {
                password_hash($senha, PASSWORD_DEFAULT);   // mesmo custo de tempo de uma conferência real
                $falha = 'email';
            } elseif (!password_verify($senha, (string) $adm['senha_hash'])) {
                $falha = 'senha';
            } elseif (intval($adm['ativo']) !== 1) {
                $falha = 'inativo';
            } elseif (!isset(jc_admin_papeis()[(string) $adm['papel']])) {
                $falha = 'papel';
            } elseif (!empty($adm['mfa_segredo'])) {
                $passo = jc_totp_verificar((string) $adm['mfa_segredo'], $codigo,
                                           $adm['mfa_ultimo_passo'] !== null ? intval($adm['mfa_ultimo_passo']) : null);
                $falha = ($passo !== null && jc_totp_gravar_passo($conn, intval($adm['id']), $passo)) ? null : 'mfa';
            } else {
                $falha = null;
            }

            if ($falha === null) {
                $id = intval($adm['id']);
                jc_admin_limite_registrar($conn, $email, true);
                if (password_needs_rehash((string) $adm['senha_hash'], PASSWORD_DEFAULT)) {
                    $novo = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE administradores SET senha_hash = ? WHERE id = ?");
                    $stmt->bind_param("si", $novo, $id);
                    $stmt->execute();
                    $stmt->close();
                }
                $stmt = $conn->prepare("UPDATE administradores SET ultimo_login_em = NOW() WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                jc_admin_sessao_entrar($adm);
                if (jc_admin_auditar($conn, $id, 'login', 'administrador', $id, 'sucesso', empty($adm['mfa_segredo']) ? 'sem_mfa' : 'mfa')) {
                    jc_admin_redirecionar('index.php');
                }
                // Sem a linha no histórico, não há sessão: toda entrada na Central precisa ficar registrada.
                jc_admin_sessao_destruir();
                $erro = 'Não foi possível registrar a entrada no histórico agora e, por segurança, ela foi cancelada. '
                      . 'Tente de novo em instantes (com o próximo código do autenticador).';
            } else {
                jc_admin_limite_registrar($conn, $email, false);
                jc_admin_auditar($conn, $adm ? intval($adm['id']) : null, 'login', $adm ? 'administrador' : null,
                                 $adm ? intval($adm['id']) : null, 'falha', $falha);
                jc_admin_atraso_progressivo(intval($estado['falhas']) + 1);
                $erro = 'E-mail, senha ou código do autenticador incorretos.';
            }
        }
    }
}

jc_admin_layout_abrir_simples('Entrar');
echo '<h1 class="adm-titulo">Entrar na Central</h1>';

if (!$migracao) {
    echo '<div class="adm-alerta adm-alerta-aviso" role="status">' . jc_admin_icone('info')
       . '<div><strong>A Central administrativa ainda não foi instalada neste banco.</strong> '
       . 'É preciso aplicar a migração da Stage 7 e criar o primeiro administrador pela linha de comando '
       . '(veja docs/ADMINISTRACAO_E_MODERACAO.md).</div></div>';
    echo '<p><a href="../index.php">Voltar ao site</a></p>';
    jc_admin_layout_fechar_simples();
    exit;
}

if ($erro !== null) {
    echo '<div class="adm-alerta adm-alerta-erro" role="alert" id="login-erro">' . jc_admin_icone('x-circle') . '<div>' . jc_e($erro) . '</div></div>';
} elseif ($motivo !== '') {
    echo '<div class="adm-alerta adm-alerta-' . ($motivo === 'logout' ? 'sucesso' : 'info') . '" role="status">'
       . jc_admin_icone($motivo === 'logout' ? 'check-circle' : 'info') . '<div>' . jc_e(jc_admin_msg_fim_sessao($motivo)) . '</div></div>';
}
if (!jc_admin_https() && !jc_admin_local()) {
    echo '<div class="adm-alerta adm-alerta-erro" role="note">' . jc_admin_icone('alert-triangle')
       . '<div>Conexão sem HTTPS. Não digite sua senha aqui fora do computador local.</div></div>';
}
$desc = $erro !== null ? ' aria-describedby="login-erro"' : '';
?>
<form method="post" action="login.php">
    <?= jc_admin_csrf_campo() ?>
    <div class="adm-campo">
        <label for="email">E-mail administrativo</label>
        <input type="email" id="email" name="email" value="<?= jc_e($email_digitado) ?>" autocomplete="username"
               maxlength="100" required autofocus<?= $desc ?><?= $erro !== null ? ' aria-invalid="true"' : '' ?>>
    </div>
    <div class="adm-campo">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" autocomplete="current-password" maxlength="200" required<?= $desc ?>>
    </div>
    <div class="adm-campo">
        <label for="codigo">Código do autenticador</label>
        <input type="text" id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="7"
               pattern="[0-9 ]{6,7}" aria-describedby="codigo-ajuda">
        <p class="adm-ajuda" id="codigo-ajuda">Os 6 dígitos do app autenticador (Google Authenticator, Aegis...).</p>
    </div>
    <button type="submit" class="adm-btn"><?= jc_admin_icone('lock') ?> Entrar</button>
</form>
<p class="adm-ajuda adm-mt">Acesso restrito à equipe do JovemConnect. As tentativas são registradas.</p>
<p><a href="../index.php"><?= jc_admin_icone('arrow-left') ?> Voltar ao site</a></p>
<?php
jc_admin_layout_fechar_simples();
