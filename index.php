<?php
// Conexão com as configurações globais do sistema
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jovem Connect - O seu primeiro passo para o sucesso</title>
    <link rel="stylesheet" href="Assets/css/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    
    <style>
        /* CSS Dedicado para recriar com precisão o print "Página Inicial (Dark)" */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #0b0f19; /* Azul-grafite profundo oficial */
            color: #ffffff;
            overflow-x: hidden;
        }

        /* 1. NAVBAR (Conforme o topo do print) */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 80px;
            background: rgba(11, 15, 25, 0.7);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid #1e293b;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            color: #ffffff;
            text-decoration: none;
        }

        .navbar-brand span {
            color: #89b4fa; /* Realce azul oficial */
        }

        .navbar-menu {
            display: flex;
            gap: 32px;
        }

        .navbar-menu a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .navbar-menu a:hover {
            color: #89b4fa;
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-text {
            color: #ffffff;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .btn-nav-primary {
            background: #89b4fa;
            color: #0b0f19;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: background 0.2s;
        }

        /* 2. HERO SECTION CENTRALIZADA (Conforme o print) */
        .hero-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 180px 20px 100px 20px;
            max-width: 900px;
            margin: 0 auto;
        }

        /* Gradiente no título principal igual ao efeito visual do print */
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 24px;
            color: #ffffff;
        }

        .hero-title span {
            background: linear-gradient(to right, #89b4fa, #b4befe);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            color: #94a3b8; /* Texto de apoio secundário */
            line-height: 1.6;
            margin-bottom: 40px;
            max-width: 640px;
        }

        /* Botões lado a lado do Hero */
        .cta-group {
            display: flex;
            gap: 16px;
            margin-bottom: 80px;
        }

        .btn-cta {
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
        }

        .btn-cta-blue {
            background: #89b4fa;
            color: #0b0f19;
        }

        .btn-cta-blue:hover {
            background: #74a2ec;
        }

        .btn-cta-dark {
            background: #131c2e;
            color: #ffffff;
            border: 1px solid #1e293b;
        }

        .btn-cta-dark:hover {
            border-color: #89b4fa;
            background: #1e293b;
        }

        /* 3. SEÇÃO INFORMATIVA DE BAIXO (Jovem Aprendiz) */
        .info-section {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 40px 100px 40px;
            box-sizing: border-box;
        }

        .info-card {
            background: #131c2e; /* Card com elevação física */
            border: 1px solid #1e293b;
            border-radius: 16px;
            padding: 48px;
            display: grid;
            grid-template-columns: 1.2fr 1fr; /* Divide o espaço deixando o texto na esquerda */
            gap: 60px;
            align-items: center;
        }

        .info-content h2 {
            font-size: 2rem;
            color: #ffffff;
            margin-bottom: 16px;
        }

        .info-content p {
            color: #94a3b8;
            font-size: 1rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Container reservado para colocar a arte gráfica/imagem depois */
        .image-placeholder-box {
            width: 100%;
            height: 240px;
            background: #0b0f19;
            border: 2px dashed #334155;
            border-radius: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #475569;
            font-size: 0.9rem;
        }

        /* Responsividade para telas menores */
        @media (max-width: 768px) {
            .navbar { padding: 20px 30px; }
            .navbar-menu { display: none; } /* Oculta menu no mobile */
            .hero-title { font-size: 2.5rem; }
            .cta-group { flex-direction: column; width: 100%; max-width: 300px; }
            .info-card { grid-template-columns: 1fr; padding: 32px; gap: 32px; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            Jovem<span>Connect</span>
        </a>
        
        <div class="navbar-menu">
            <a href="#sobre">Sobre</a>
            <a href="#contato">Contato</a>
        </div>
        
        <div class="navbar-actions">
            <a href="jovem/login.php" class="btn-text">Entrar</a>
            <a href="jovem/cadastro.php" class="btn-nav-primary">Começar</a>
        </div>
    </nav>

    <header class="hero-section">
        <h1 class="hero-title">
            O seu primeiro passo<br>para o <span>sucesso profissional</span>
        </h1>
        <p class="hero-subtitle">
            Conectamos jovens talentos em busca de oportunidades com empresas que valorizam o aprendizado e o crescimento.
        </p>
        
        <div class="cta-group">
            <a href="jovem/login.php" class="btn-cta btn-cta-blue">Sou Jovem</a>
            <a href="empresa/login.php" class="btn-cta btn-cta-dark">Sou Empresa</a>
        </div>
    </header>

    <section class="info-section">
        <div class="info-card">
            <div class="info-content">
                <h2>Simplificando o Programa Jovem Aprendiz</h2>
                <p>
                    Nossa plataforma automatiza a triagem e o mapeamento de competências com inteligência artificial, tornando o processo de contratação muito mais rápido, transparente e eficiente para ambos os lados.
                </p>
            </div>
            
            <div class="image-placeholder-box">
                [ Espaço reservado para a ilustração descritiva ]
            </div>
        </div>
    </section>

<?php 
// Injeta os FABs flutuantes originais no canto inferior direito
include 'includes/footer.php'; 
?>
</body>
</html>