<?php include('includes/header.php'); ?>

<main class="home">
  <section class="intro">
    <div class="text-content">
      <h1>Conectando Jovens Aprendizes e Empresas</h1>
      <p>Descubra oportunidades, crie seu currículo e encontre o trabalho certo pra você.  
      Empresas também podem encontrar talentos prontos para crescer.</p>

     <div class="buttons">
      <a href="/jovemaprendiz_v1/pages/login.php?tipo=jovem" class="btn green">Sou Jovem</a>
      <a href="/jovemaprendiz_v1/pages/login.php?tipo=empresa" class="btn gray">Sou Empresa</a>
     </div>

      <a href="#sobre" class="link">Saiba mais ↓</a>
    </div>
  </section>

  <section id="sobre" class="about">
    <h2>Sobre o Jovem Aprendiz Conecta</h2>
    <p>
      Nosso objetivo é simplificar o primeiro passo no mundo profissional.  
      Aqui, você pode encontrar vagas compatíveis com seu perfil, montar um currículo profissional e usar a inteligência artificial para avaliar as melhores oportunidades.
    </p>
  </section>
</main>

<?php include('includes/footer.php'); ?>
<?php
error_reporting(E_ALL & ~E_NOTICE);
?>