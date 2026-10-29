<?php
/* ============================================================
   empresa/login.php — Página de login unificada (empresa + jovem)
   
   Sem include de config.php: essa página só exibe o formulário.
   A query de autenticação acontece em processar_login.php.
   Sem include de auth.php:   sessão verificada diretamente aqui.
   Sem include de footer.php: página tem sua própria estrutura HTML.
   ============================================================ */
require_once dirname(__DIR__) . '/includes/csrf.php';   // Sessao endurecida + CSRF

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
    <link rel="stylesheet" href="../assets/css/style.css?v=4.60">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/marca/jovemconnect-icone-32.png">
    <link rel="apple-touch-icon" href="../assets/img/marca/jovemconnect-icone-180.png">
</head>
<body>

<div class="auth-page">
<div class="auth-card">

    <!-- Caminho de volta -->
    <p style="margin:0 0 12px;"><a href="../index.php" class="back-link"><span class="i i-arrow-left" aria-hidden="true"></span> Voltar para a página inicial</a></p>

    <!-- Marca oficial — versão vertical (símbolo + nome embaixo) na composição empilhada do cartão -->
    <div class="auth-logo">
        <h1 class="marca marca-vertical"><img class="marca-tema-escuro" src="../assets/img/marca/jovemconnect-vertical.png" alt="JovemConnect" width="878" height="689"><img class="marca-tema-claro" src="../assets/img/marca/jovemconnect-vertical-claro.png" alt="JovemConnect" width="878" height="689" loading="lazy"></h1>
    </div>

    <p style="font-size:20px;font-weight:700;text-align:center;margin-bottom:6px;">
        Acesse sua conta
    </p>
    <p class="text-secondary" style="font-size:13px;text-align:center;margin-bottom:28px;">
        Entre para ver as novidades no seu feed
    </p>

    <!-- ── Erro de login (gravado por processar_login.php) ───────────────────── -->
    <!-- Empresa/processar_login.php grava este erro, mas esta página
         nunca o exibia — senha errada recarregava a tela sem nenhum aviso. -->
    <?php if (!empty($_SESSION['erro_login'])): ?>
    <div id="erroLogin" role="alert" class="alerta alerta-erro">
        <span class="i i-alert-triangle" aria-hidden="true"></span>
        <span><?= htmlspecialchars($_SESSION['erro_login']) ?></span>
        <?php unset($_SESSION['erro_login']); ?>
    </div>
    <?php endif; ?>

    <!-- ── Sucesso (ex.: senha redefinida por recuperar_senha.php) ────────────── -->
    <?php if (!empty($_SESSION['sucesso_login'])): ?>
    <div role="status" class="alerta alerta-sucesso">
        <span class="i i-check-circle" aria-hidden="true"></span>
        <span><?= htmlspecialchars($_SESSION['sucesso_login']) ?></span>
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
            <span class="i i-graduation-cap" aria-hidden="true"></span> Sou Jovem
        </button>
        <button
            type="button"
            class="auth-tab"
            id="tabEmpresa"
            role="tab"
            aria-selected="true"
            onclick="switchProfile('empresa')">
            <span class="i i-building" aria-hidden="true"></span> Sou Empresa
        </button>
    </div>

    <!-- ── Formulário de login ────────────────────────────────────────────── -->
    <form action="processar_login.php" method="POST" id="loginForm" novalidate data-envio-unico="Entrando...">

        <!-- Campo oculto: define qual processar_login.php será usado -->
        <input type="hidden" name="tipo_usuario" id="tipo_usuario" value="empresa">
        <?= csrf_field() ?>

        <!-- E-mail -->
        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <div class="input-group">
                <span class="input-icon i i-mail" aria-hidden="true"></span>
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
                <span class="input-icon i i-lock" aria-hidden="true"></span>
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
        <span id="footerText">Sua empresa ainda não é parceira?</span>
        <a href="cadastro.php" id="footerLink" class="font-semibold">Registre sua Empresa</a>
    </div>

    <?php require_once dirname(__DIR__) . '/includes/documentos.php'; ?>
    <p class="footer-copy auth-copy"><?= htmlspecialchars(jc_copyright()) ?></p>

</div><!-- /.auth-card -->
</div><!-- /.auth-page -->

<!-- FABs + Assistente + Acessibilidade -->
<?php include dirname(__DIR__) . '/includes/assistente.php'; ?>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script>
/* ── Aplica tema salvo ─────────────────────────────────────── */
const savedTheme = localStorage.getItem('jc-theme');
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

/* Depois de um erro de login o cursor já fica no e-mail para tentar de novo */
if (document.getElementById('erroLogin')) document.getElementById('email')?.focus();

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

        footerText.textContent = 'Sua empresa ainda não é parceira?';   // Concordância
        footerLink.textContent = 'Registre sua Empresa';
        footerLink.href        = 'cadastro.php';
    }
}

// Inicia com a aba correta conforme o arquivo (empresa/login.php → empresa ativa)
document.addEventListener('DOMContentLoaded', () => switchProfile('empresa'));
</script>

</body>
</html>