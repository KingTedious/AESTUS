<?php 
// 1. Inclui o cabeçalho (que já tem o CSS e as fontes)
include("includes/header.php"); 
?>

<link rel="stylesheet" href="assets/css/style.css">

<div class="container" style="text-align: center; padding-top: 50px;">
    
    <h1 style="font-size: 2.5rem; color: var(--principal);">Jovem Connect</h1>
    <p style="font-size: 1.1rem; color: #94a3b8; margin-bottom: 40px;">
        A ponte inteligente entre o seu talento e o mercado de trabalho.
    </p>

    <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
        
        <div class="card" style="flex: 1; min-width: 300px; max-width: 400px; padding: 40px;">
            <div style="font-size: 3rem; margin-bottom: 10px;">'adciona um icone de jovem estudante'</div>
            <h2>Sou Jovem</h2>
            <p>Procuro vagas de estágio, aprendizagem e dicas de IA para o meu currículo.</p>
            <br>
            <a href="jovem/login.php" class="btn" style="display: block; margin-bottom: 10px;">Entrar agora</a>
            <a href="jovem/cadastro.php" style="color: var(--principal); text-decoration: none; font-size: 0.9rem;">
                Ainda não tenho conta 
            </a>
        </div>

        <div class="card" style="flex: 1; min-width: 300px; max-width: 400px; padding: 40px;">
            <div style="font-size: 3rem; margin-bottom: 10px;">'adciona um icone de empresa'</div>
            <h2>Sou Empresa</h2>
            <p>Desejo publicar vagas e encontrar os melhores talentos da região.</p>
            <br>
            <a href="empresa/login.php" class="btn" style="display: block; margin-bottom: 10px; background: #334155;">Entrar agora</a>
            <a href="empresa/cadastro.php" style="color: #94a3b8; text-decoration: none; font-size: 0.9rem;">
                Cadastrar minha empresa
            </a>
        </div>

    </div>

    <div style="margin-top: 60px; padding: 20px; border-top: 1px solid var(--borda);">
        <p style="color: #64748b; font-size: 0.9rem;">
            Potencializado por <strong>Inteligência Artificial</strong> para o melhor Match de vagas.
        </p>
    </div>

</div>

<?php 
// 4. Inclui o rodapé (que fecha as divs abertas no header)
include("includes/footer.php"); 
?>