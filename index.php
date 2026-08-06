<?php include('includes/header.php'); ?>

<main class="home">
  <section class="hero">
    <div class="hero-text">
      <h1>O seu primeiro passo para o <span>sucesso profissional</span></h1>
      <p>Conectamos jovens talentos em busca de oportunidades com empresas que valorizam o aprendizado e o crescimento.</p>

      <div class="hero-buttons">
        <a href="pages/login.php?tipo=jovem" class="btn btn-primary">Sou Jovem</a>
        <a href="pages/login.php?tipo=empresa" class="btn btn-secondary">Sou Empresa</a>
      </div>
    </div>

    <div class="hero-image">
      <img src="assets/hero-image.svg" alt="Jovem estudando com notebook">
    </div>
  </section>

  <section class="about">
    <div class="about-container">
      <div class="about-image">
        <img src="assets/office.svg" alt="Ambiente de trabalho moderno">
      </div>
      <div class="about-text">
        <h2>Simplificando o Programa Jovem Aprendiz</h2>
        <p>Nossa plataforma utiliza inteligencia artificial para encontrar a combinacao perfeita entre o perfil do candidato e as necessidades da empresa.</p>
        <ul>
          <li>Analise de compatibilidade via IA</li>
          <li>Gerador de curriculos profissional</li>
          <li>Filtros geograficos e de area</li>
        </ul>
      </div>
    </div>
  </section>
</main>

<?php include('includes/footer.php'); ?>
