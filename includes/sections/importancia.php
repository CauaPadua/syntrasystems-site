<?php
/**
 * Seção 2b — Importância: os quatro pilares do monitoramento.
 *
 * Estes cartões viviam dentro de `informacoes.php`. Quando aquela seção passou
 * a ser o bloco editorial "Por que monitorar", os cartões foram preservados
 * aqui, com âncora própria (`#importancia`), porque são o destino do botão
 * "Entenda a importância".
 *
 * O título e o parágrafo de abertura antigos não vieram junto: repetiam o que
 * a seção anterior agora diz. Resta um título estruturalmente necessário,
 * disponível para leitores de tela.
 */
?>
<section class="section section--info" id="importancia" aria-labelledby="importancia-titulo">

  <img class="deco deco--onda"
       src="<?php aq_out(aq_asset('images/onda-agua.webp')); ?>"
       width="980" height="552" alt="" aria-hidden="true" loading="lazy" decoding="async">
  <img class="deco deco--linhas"
       src="<?php aq_out(aq_asset('images/linhas-decorativas.webp')); ?>"
       width="1100" height="619" alt="" aria-hidden="true" loading="lazy" decoding="async">
  <span class="deco deco--dots" aria-hidden="true"></span>

  <div class="container section__inner">

    <h2 class="visually-hidden" id="importancia-titulo">Importância do monitoramento</h2>

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
