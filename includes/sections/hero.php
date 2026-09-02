<?php
/** Seção 1 — Hero. */
?>
<section class="hero" id="inicio" aria-labelledby="hero-titulo">

  <div class="hero__media">
    <img class="hero__photo"
         src="<?php aq_out(aq_asset('images/hero-represa.webp')); ?>"
         width="1672" height="941"
         alt="Vista aérea de uma represa em operação, com reservatório cercado por montanhas."
         fetchpriority="high" decoding="async">
    <img class="hero__overlay"
         src="<?php aq_out(aq_asset('images/overlay-monitoramento.webp')); ?>"
         width="1100" height="619" alt="" aria-hidden="true" decoding="async">
    <span class="hero__fade" aria-hidden="true"></span>
  </div>

  <div class="container hero__inner">

    <div class="hero__content">
      <h1 class="hero__title" id="hero-titulo">
        Monitoramento inteligente para
        <span class="hero__title-accent">represas mais seguras</span>
      </h1>

      <span class="hero__rule" aria-hidden="true"></span>

      <p class="hero__text">
        Acompanhe em tempo real o que realmente importa.
        Dados contínuos, análise inteligente e alertas precisos para decisões
        seguras e uma gestão eficiente dos recursos hídricos.
      </p>

      <div class="hero__actions">
        <a class="btn btn--primary btn--lg" href="#sistema">
          <span>Conheça a solução</span>
          <?php aq_the_icon('arrow-right'); ?>
        </a>
        <a class="btn btn--link" href="#informacoes">
          <span>Entenda a importância</span>
          <?php aq_the_icon('chevron-right'); ?>
        </a>
      </div>

      <ul class="hero__highlights">
        <?php foreach (AQ_HERO_HIGHLIGHTS as $item): ?>
          <li class="hero-highlight">
            <span class="icon-badge icon-badge--sm" aria-hidden="true"><?php aq_the_icon($item['icon']); ?></span>
            <span class="hero-highlight__body">
              <strong class="hero-highlight__title"><?php aq_out($item['title']); ?></strong>
              <span class="hero-highlight__text"><?php aq_out($item['text']); ?></span>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="hero__aside">
      <figure class="level-card">
        <figcaption class="level-card__label">
          <span class="level-card__dot" aria-hidden="true"></span>
          Nível do reservatório
        </figcaption>
        <p class="level-card__value">82,4<span>%</span></p>
        <p class="level-card__caption">Capacidade total</p>

        <svg class="level-card__chart" viewBox="0 0 260 74" role="img"
             aria-label="Gráfico ilustrativo com tendência de elevação do nível do reservatório.">
          <defs>
            <linearGradient id="grad-nivel" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#1557e0" stop-opacity=".18"/>
              <stop offset="100%" stop-color="#1557e0" stop-opacity="0"/>
            </linearGradient>
          </defs>
          <path fill="url(#grad-nivel)"
                d="M4 60 22 56 40 62 58 50 76 55 94 45 112 52 130 40 148 46 166 34 184 40 202 27 220 33 238 20 256 24V74H4Z"/>
          <path fill="none" stroke="#1557e0" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
                d="M4 60 22 56 40 62 58 50 76 55 94 45 112 52 130 40 148 46 166 34 184 40 202 27 220 33 238 20 256 24"/>
        </svg>

        <p class="level-card__footer">
          <span class="level-card__status">Condição normal</span>
          <span class="level-card__meta">Atualizado agora</span>
        </p>
      </figure>
    </div>

  </div>

  <div class="container">
    <ul class="hero__badges">
      <?php foreach (AQ_HERO_BADGES as $badge): ?>
        <li class="hero-badge">
          <?php aq_the_icon($badge['icon']); ?>
          <span><?php aq_out($badge['label']); ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

</section>
