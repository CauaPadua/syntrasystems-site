<?php
/**
 * Seção 1 — Hero.
 *
 * Composição editorial sobre fotografia: selo e título no canto inferior
 * esquerdo, texto de apoio à direita e chamada principal no canto inferior
 * direito. A navegação flutuante fica no cabeçalho (includes/header.php),
 * posicionado por cima desta seção.
 *
 * A ordem do HTML é a ordem de leitura (selo, título, apoio, chamada); o
 * posicionamento em cada canto é feito pelo grid do CSS, de modo que leitores
 * de tela e navegação por teclado sigam sempre a mesma sequência.
 *
 * O título já vem completo no HTML: o JavaScript apenas envolve cada palavra
 * para revelá-las em sequência. Sem JavaScript, o texto continua visível.
 */
?>
<section class="hero" id="inicio" aria-labelledby="hero-titulo">

  <div class="hero__frame">

    <img class="hero__photo"
         src="<?php aq_out(aq_asset('images/aquapulse-hero-reservatorio.png')); ?>"
         width="1672" height="941"
         alt="Vista aérea de um reservatório cercado por montanhas, com a barragem vertendo água à direita."
         fetchpriority="high" decoding="async">

    <span class="hero__scrim" aria-hidden="true"></span>

    <div class="hero__layout">

      <div class="hero__intro">
        <span class="hero__eyebrow">
          <span class="hero__eyebrow-dot" aria-hidden="true"></span>
          Monitoramento de represas
        </span>

        <h1 class="hero__title" id="hero-titulo" data-words-stagger>Cada gota importa. Cada decisão também.</h1>
      </div>

      <p class="hero__lead">
        Uma visão mais clara da água para apoiar decisões e cuidar do futuro.
      </p>

      <div class="hero__cta">
        <a class="btn btn--cyan btn--lg" href="#sistema">
          <span>Conheça o Aquapulse</span>
          <?php aq_the_icon('arrow-up-right'); ?>
        </a>
      </div>

    </div>
  </div>

</section>
