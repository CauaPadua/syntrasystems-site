<?php
/** Seção 3 — Sistema: como o Aquapulse apoia a operação. */
?>
<section class="section section--system" id="sistema" aria-labelledby="sistema-titulo">

  <img class="deco deco--linhas-sistema"
       src="<?php aq_out(aq_asset('images/linhas-decorativas.webp')); ?>"
       width="1100" height="619" alt="" aria-hidden="true" loading="lazy" decoding="async">

  <div class="container system__inner">

    <div class="system__content reveal">
      <p class="eyebrow"><?php aq_the_icon('target'); ?><span>Como a Aquapulse apoia sua operação</span></p>
      <h2 class="section__title" id="sistema-titulo">
        Visão clara para <br>monitorar, analisar <br>e decidir
      </h2>
      <p class="section__lead section__lead--left">
        O Aquapulse centraliza as informações estratégicas dos seus reservatórios
        em um só lugar, com dados confiáveis e atualizados para apoiar decisões
        mais seguras e operações mais eficientes.
      </p>

      <ul class="system__points">
        <?php foreach (AQ_SYSTEM_POINTS as $point): ?>
          <li class="system-point">
            <span class="icon-badge icon-badge--tile" aria-hidden="true"><?php aq_the_icon($point['icon']); ?></span>
            <div>
              <h3 class="system-point__title"><?php aq_out($point['title']); ?></h3>
              <p class="system-point__text"><?php aq_out($point['text']); ?></p>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <figure class="system__preview reveal">
      <div class="system__frame" data-scroller
           aria-label="Prévia do painel Aquapulse (role horizontalmente para ver todo o painel)">
        <img class="system__mockup"
             src="<?php aq_out(aq_asset('images/dashboard-aquapulse.webp')); ?>"
             width="1536" height="1024"
             alt="Prévia do painel Aquapulse: visão geral com nível do reservatório, volume armazenado, afluência, precipitação, gráfico de nível, mapa da represa, alertas recentes e relatórios."
             loading="lazy" decoding="async">
      </div>
      <figcaption class="system__caption">Prévia ilustrativa da interface do Aquapulse.</figcaption>
    </figure>

  </div>
</section>
