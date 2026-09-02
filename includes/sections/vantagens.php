<?php
/** Seção 4 — Vantagens de um monitoramento mais inteligente. */
?>
<section class="section section--advantages" id="vantagens" aria-labelledby="vantagens-titulo">

  <div class="advantages__media" aria-hidden="true">
    <img class="advantages__photo"
         src="<?php aq_out(aq_asset('images/vantagens-represa.webp')); ?>"
         width="1672" height="941" alt="" loading="lazy" decoding="async">
    <img class="advantages__overlay"
         src="<?php aq_out(aq_asset('images/overlay-monitoramento.webp')); ?>"
         width="1100" height="619" alt="" loading="lazy" decoding="async">
    <span class="advantages__fade"></span>
  </div>

  <img class="deco deco--linhas-vantagens"
       src="<?php aq_out(aq_asset('images/linhas-decorativas.webp')); ?>"
       width="1100" height="619" alt="" aria-hidden="true" loading="lazy" decoding="async">

  <div class="container section__inner">

    <div class="section__head reveal">
      <p class="eyebrow"><?php aq_the_icon('sparkle'); ?><span>Vantagens</span></p>
      <h2 class="section__title" id="vantagens-titulo">
        Vantagens de um <br>monitoramento mais inteligente
      </h2>
      <p class="section__lead section__lead--left">
        Adotar uma solução inteligente de monitoramento de represas é investir em
        segurança, eficiência e sustentabilidade. Conheça os principais benefícios
        para a sua operação e para a sociedade.
      </p>
    </div>

    <ul class="advantages-grid">
      <?php foreach (AQ_ADVANTAGES as $i => $card): ?>
        <li class="reveal" style="--delay: <?php echo ($i % 3) * 80; ?>ms">
          <article class="advantage-card">
            <span class="icon-badge icon-badge--round" aria-hidden="true"><?php aq_the_icon($card['icon']); ?></span>
            <div class="advantage-card__body">
              <h3 class="advantage-card__title"><?php aq_out($card['title']); ?></h3>
              <p class="advantage-card__text"><?php aq_out($card['text']); ?></p>
            </div>
          </article>
        </li>
      <?php endforeach; ?>
    </ul>

    <aside class="cta" id="solicitar-demonstracao" aria-labelledby="cta-titulo">
      <span class="icon-badge icon-badge--round icon-badge--lg" aria-hidden="true"><?php aq_the_icon('shield-check'); ?></span>
      <div class="cta__body">
        <h3 class="cta__title" id="cta-titulo">Proteja hoje o que importa amanhã.</h3>
        <p class="cta__text">
          Dê o próximo passo para uma gestão de represas mais segura,
          inteligente e sustentável.
        </p>
      </div>
      <button class="btn btn--primary btn--lg" type="button" data-demo-trigger aria-describedby="aviso-demo">
        <span>Solicitar demonstração</span>
        <?php aq_the_icon('arrow-right'); ?>
      </button>
      <p class="cta__note" id="aviso-demo" role="status" hidden>
        Canal de contato em preparação. Em breve disponível.
      </p>
    </aside>

  </div>
</section>
