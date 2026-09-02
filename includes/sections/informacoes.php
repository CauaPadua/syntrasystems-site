<?php
/** Seção 2 — Informações: por que monitorar represas. */
?>
<section class="section section--info" id="informacoes" aria-labelledby="info-titulo">

  <img class="deco deco--onda"
       src="<?php aq_out(aq_asset('images/onda-agua.webp')); ?>"
       width="980" height="552" alt="" aria-hidden="true" loading="lazy" decoding="async">
  <img class="deco deco--linhas"
       src="<?php aq_out(aq_asset('images/linhas-decorativas.webp')); ?>"
       width="1100" height="619" alt="" aria-hidden="true" loading="lazy" decoding="async">
  <span class="deco deco--dots" aria-hidden="true"></span>

  <div class="container section__inner">

    <div class="section__head section__head--center reveal">
      <p class="eyebrow"><?php aq_the_icon('info'); ?><span>Informações</span></p>
      <h2 class="section__title" id="info-titulo">
        Por que o monitoramento <br>de represas é fundamental?
      </h2>
      <p class="section__lead">
        Represas desempenham um papel essencial para o abastecimento de água,
        a geração de energia e o desenvolvimento das comunidades.
        Monitorar é antecipar riscos, proteger vidas e garantir o uso responsável dos recursos.
      </p>
    </div>

    <ul class="info-grid">
      <?php foreach (AQ_INFO_CARDS as $i => $card): ?>
        <li class="reveal" style="--delay: <?php echo $i * 80; ?>ms">
          <article class="info-card">
            <span class="icon-badge icon-badge--round" aria-hidden="true"><?php aq_the_icon($card['icon']); ?></span>
            <h3 class="info-card__title"><?php aq_out($card['title']); ?></h3>
            <span class="info-card__rule" aria-hidden="true"></span>
            <p class="info-card__text"><?php aq_out($card['text']); ?></p>
          </article>
        </li>
      <?php endforeach; ?>
    </ul>

  </div>
</section>
