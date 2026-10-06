<?php
/* ============================================================
   empresa/login.php — Página de login unificada (empresa + jovem)
   
   Sem include de config.php: essa página só exibe o formulário.
   A query de autenticação acontece em processar_login.php.
   Sem include de auth.php:   sessão verificada diretamente aqui.
   Sem include de footer.php: página tem sua própria estrutura HTML.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Etapa 2: sessao endurecida + CSRF

// Sessão ativa → redireciona sem mostrar o formulário
if (isset($_SESSION['empresa_id'])) {
    header("Location: ../dashboard/empresa.php");
    exit;
}
if (isset($_SESSION['jovem_id'])) {
    header("Location: ../dashboard/jovem.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar | JovemConnect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
</head>
<body>

<div class="auth-page">
<div class="auth-card">

    <!-- ── Logo + Marca ──────────────────────────────────────────────────── -->
    <div class="auth-logo">
        <div class="logo-box">💼</div>
        <h1><span class="logo-jovem">Jovem</span><span class="logo-connect">Connect</span></h1>
    </div>

    <p style="font-size:20px;font-weight:700;text-align:center;margin-bottom:6px;">
        Acesse sua conta
    </p>
    <p class="text-secondary" style="font-size:13px;text-align:center;margin-bottom:28px;">
        Entre para ver as novidades no seu feed
    </p>

    <!-- ── Erro de login (gravado por processar_login.php) ───────────────────── -->
    <!-- Stage 3: empresa/processar_login.php grava este erro, mas esta página
         nunca o exibia — senha errada recarregava a tela sem nenhum aviso. -->
    <?php if (!empty($_SESSION['erro_login'])): ?>
    <div style="
        background : rgba(239,68,68,.12);
        border     : 1px solid rgba(239,68,68,.3);
        border-radius: var(--radius-sm);
        padding    : 12px 16px;
        margin-bottom: 20px;
        font-size  : 13px;
        color      : #f87171;
    ">
        ⚠ <?= htmlspecialchars($_SESSION['erro_login']) ?>
        <?php unset($_SESSION['erro_login']); ?>
    </div>
    <?php endif; ?>

    <!-- ── Sucesso (ex.: senha redefinida por recuperar_senha.php) ────────────── -->
    <?php if (!empty($_SESSION['sucesso_login'])): ?>
    <div style="
        background : rgba(34,197,94,.12);
        border     : 1px solid rgba(34,197,94,.3);
        border-radius: var(--radius-sm);
        padding    : 12px 16px;
        margin-bottom: 20px;
        font-size  : 13px;
        color      : #4ade80;
    ">
        ✓ <?= htmlspecialchars($_SESSION['sucesso_login']) ?>
        <?php unset($_SESSION['sucesso_login']); ?>
    </div>
    <?php endif; ?>

    <!-- ── Abas Jovem / Empresa ──────────────────────────────────────────── -->
    <div class="auth-tabs" role="tablist" aria-label="Tipo de usuário">
        <button
            type="button"
            class="auth-tab"
            id="tabJovem"
            role="tab"
            aria-selected="false"
            onclick="switchProfile('jovem')">
            🎓 Sou Jovem
        </button>
        <button
            type="button"
            class="auth-tab"
            id="tabEmpresa"
            role="tab"
            aria-selected="true"
            onclick="switchProfile('empresa')">
            💼 Sou Empresa
        </button>
    </div>

    <!-- ── Formulário de login ────────────────────────────────────────────── -->
    <form action="processar_login.php" method="POST" id="loginForm" novalidate>

        <!-- Campo oculto: define qual processar_login.php será usado -->
        <input type="hidden" name="tipo_usuario" id="tipo_usuario" value="empresa">
        <?= csrf_field() ?>

        <!-- E-mail -->
        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <div class="input-group">
                <span class="input-icon">✉</span>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="rh@empresa.com.br"
                    required
                    autocomplete="email">
            </div>
        </div>

        <!-- Senha -->
        <div class="form-group">
            <!-- Label + "Esqueceu a senha?" na mesma linha -->
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;">
                <label class="form-label" for="senha" style="margin:0;">Senha</label>
                <a href="../recuperar_senha.php" class="forgot-link" id="linkRecuperar">
                    Esqueceu a senha?
                </a>
            </div>
            <div class="input-group">
                <span class="input-icon">🔒</span>
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    class="form-control"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password">
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full">
            Entrar
        </button>

    </form>

    <!-- ── Link de cadastro (muda conforme a aba ativa) ─────────────────── -->
    <div class="auth-footer-link">
        <span id="footerText">Sua empresa não está parceira?</span>
        <a href="cadastro.php" id="footerLink" class="font-semibold">Registre sua Empresa</a>
    </div>

</div><!-- /.auth-card -->
</div><!-- /.auth-page -->

<!-- FABs -->
<div class="fab-stack">
    <button class="fab fab-a11y" id="btnA11y" title="Acessibilidade" aria-label="Acessibilidade">♿</button>
</div>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script>
/* ── Aplica tema salvo ─────────────────────────────────────── */
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

/* ── Troca de perfil (jovem ↔ empresa) ────────────────────── */
function switchProfile(type) {
    const tabJovem   = document.getElementById('tabJovem');
    const tabEmpresa = document.getElementById('tabEmpresa');
    const form       = document.getElementById('loginForm');
    const inputTipo  = document.getElementById('tipo_usuario');
    const emailInput = document.getElementById('email');
    const footerText = document.getElementById('footerText');
    const footerLink = document.getElementById('footerLink');
    const linkRec    = document.getElementById('linkRecuperar');

    if (type === 'jovem') {

        tabJovem.classList.add('active');
        tabJovem.setAttribute('aria-selected', 'true');
        tabEmpresa.classList.remove('active');
        tabEmpresa.setAttribute('aria-selected', 'false');

        inputTipo.value        = 'jovem';
        emailInput.placeholder = 'seu@email.com';
        form.action            = '../jovem/processar_login.php';
        linkRec.href           = '../recuperar_senha.php';

        footerText.textContent = 'Não tem uma conta?';
        footerLink.textContent = 'Cadastre-se agora';
        footerLink.href        = '../jovem/cadastro.php';

    } else { // empresa

        tabEmpresa.classList.add('active');
        tabEmpresa.setAttribute('aria-selected', 'true');
        tabJovem.classList.remove('active');
        tabJovem.setAttribute('aria-selected', 'false');

        inputTipo.value        = 'empresa';
        emailInput.placeholder = 'rh@empresa.com.br';
        form.action            = 'processar_login.php';
        linkRec.href           = '../recuperar_senha.php';

        footerText.textContent = 'Sua empresa não está parceira?';
        footerLink.textContent = 'Registre sua Empresa';
        footerLink.href        = 'cadastro.php';
    }
}

// Inicia com a aba correta conforme o arquivo (empresa/login.php → empresa ativa)
document.addEventListener('DOMContentLoaded', () => switchProfile('empresa'));
</script>

</body>
</html>