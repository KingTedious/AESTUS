<?php
// Gerenciamento de rotas e includes baseado na sua árvore de diretórios
require_once '../includes/config.php'; 
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Corporativo - Jovem Connect</title>
    <link rel="stylesheet" href="../Assets/css/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    
    <style>
        /* Estilização centralizada da viewport de autenticação conforme o Mockup */
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #0b0f19; /* Fundo escuro profundo oficial */
            padding: 20px;
            box-sizing: border-box;
            position: relative;
        }

        .auth-card {
            background: #131c2e; /* Tom de elevação física dos containers */
            border: 1px solid #1e293b;
            border-radius: 12px; /* Cantos arredondados padronizados */
            padding: 40px 32px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .brand-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .brand-logo {
            width: 32px;
            height: 32px;
            background: #89b4fa; /* Novo tom azul de destaque */
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0b0f19;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .brand-title {
            font-size: 1.4rem;
            color: #ffffff; /* Texto puro para alto impacto */
            font-weight: 700;
            margin: 0;
        }

        .auth-subtitle {
            font-size: 1.1rem;
            color: #ffffff;
            margin: 0 0 8px 0;
            font-weight: 600;
        }

        .auth-desc {
            color: #94a3b8; /* Texto secundário */
            font-size: 0.875rem;
            margin: 0 0 24px 0;
        }

        /* Segmented Controls (Abas de transição interna) */
        .segmented-control {
            display: flex;
            background: #0b0f19;
            padding: 4px;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 1px solid #1e293b;
        }

        .segment-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: #94a3b8;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease-in-out; /* Interpolação suave de estados */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Estado ativo aplicando o tom #89B4FA com contraste escuro */
        .segment-btn.active {
            background: #89b4fa;
            color: #0b0f19;
        }

        /* Estrutura síncrona e empilhada dos campos */
        .form-group {
            text-align: left;
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #475569;
            font-size: 1.1rem;
            pointer-events: none;
        }

        .form-group input {
            width: 100%;
            background: #0b0f19;
            border: 1px solid #1e293b;
            color: #ffffff;
            padding: 12px 16px 12px 42px; /* Margem interna para acomodar o ícone decorativo */
            border-radius: 8px;
            outline: none;
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: all 0.3s ease-in-out;
        }

        .form-group input:focus {
            border-color: #89b4fa;
            box-shadow: 0 0 0 2px rgba(137, 180, 250, 0.15);
        }

        .form-options {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 24px;
        }

        .forgot-password {
            color: #94a3b8;
            font-size: 0.825rem;
            text-decoration: none;
            transition: color 0.2s;
        }

        .forgot-password:hover {
            color: #89b4fa;
        }

        /* Botão Principal de Submissão */
        .btn-submit {
            width: 100%;
            background: #89b4fa;
            color: #0b0f19;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease-in-out;
            margin-bottom: 20px;
        }

        .btn-submit:hover {
            background: #74a2ec;
            transform: translateY(-1px);
        }

        .auth-footer {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .auth-footer a {
            color: #89b4fa;
            text-decoration: none;
            font-weight: 500;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="brand-header">
            <div class="brand-logo">JC</div>
            <h1 class="brand-title">JovemConnect</h1>
        </div>

        <h2 class="auth-subtitle">Acesse sua conta</h2>
        <p class="auth-desc">Entre para gerenciar suas vagas e candidatos</p>

        <div class="segmented-control">
            <button type="button" class="segment-btn" id="btnJovem" onclick="switchProfile('jovem')">
                🎓 Sou Jovem
            </button>
            <button type="button" class="segment-btn" id="btnEmpresa" onclick="switchProfile('empresa')">
                💼 Sou Empresa
            </button>
        </div>

        <?php if(isset($_SESSION['login_error'])): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 12px; border-radius: 8px; margin-bottom: 24px; text-align: center; font-size: 0.9rem;">
                <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
            </div>
        <?php endif; ?>

        <form action="../processar_login.php" method="POST" id="loginForm">
            <input type="hidden" name="tipo_usuario" id="tipo_usuario" value="empresa">

            <div class="form-group">
                <label for="email">E-mail Corporativo</label>
                <div class="input-wrapper">
                    <span class="input-icon">✉️</span>
                    <input type="email" id="email" name="email" placeholder="empresa@dominio.com" required autocomplete="email">
                </div>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="senha" name="senha" placeholder="••••••••" required autocomplete="current-password">
                </div>
            </div>

            <div class="form-options">
                <a href="recuperar_senha.php" class="forgot-password">Esqueceu a senha?</a>
            </div>

            <button type="submit" class="btn-submit">Entrar</button>
        </form>

        <div class="auth-footer">
            <span id="footerText">Sua empresa não está parceira?</span> 
            <a href="cadastro.php" id="footerLink">Registre sua Empresa</a>
        </div>
    </div>
</div>

<script>
// Mantém as transições de troca de contexto idênticas às do mockup
function switchProfile(type) {
    const btnJovem = document.getElementById('btnJovem');
    const btnEmpresa = document.getElementById('btnEmpresa');
    const inputTipo = document.getElementById('tipo_usuario');
    const footerText = document.getElementById('footerText');
    const footerLink = document.getElementById('footerLink');
    const form = document.getElementById('loginForm');

    if (type === 'jovem') {
        btnJovem.classList.add('active');
        btnEmpresa.classList.remove('active');
        inputTipo.value = 'jovem';
        
        footerText.textContent = "Não tem uma conta?";
        footerLink.textContent = "Cadastre-se agora";
        footerLink.href = "../jovem/cadastro.php";
        form.action = "../processar_login.php"; // rota central
    } else {
        btnEmpresa.classList.add('active');
        btnJovem.classList.remove('active');
        inputTipo.value = 'empresa';
        
        footerText.textContent = "Sua empresa não está parceira?";
        footerLink.textContent = "Registre sua Empresa";
        footerLink.href = "cadastro.php";
        form.action = "../processar_login.php"; // rota central
    }
}

// GATILHO AUTOMÁTICO: Garante que esta tela já inicie com o perfil "empresa" pré-selecionado de forma nativa
document.addEventListener("DOMContentLoaded", function() {
    switchProfile('empresa');
});
</script>

<?php 
// Renderização do encerramento e injeção do Guia de Microinterações
include '../includes/footer.php'; 
?>