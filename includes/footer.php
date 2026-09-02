<?php
/** Rodapé institucional. */
?>
<footer class="site-footer">
  <div class="container site-footer__inner">

    <div class="site-footer__brand">
      <img class="brand__logo"
           src="<?php aq_out(aq_asset('images/logo-aquapulse.png')); ?>"
           width="560" height="215"
           alt="Aquapulse — monitoramento de represas"
           loading="lazy" decoding="async">
      <p class="site-footer__text">
        Monitoramento inteligente de represas e reservatórios para apoiar
        operações mais seguras, decisões bem informadas e o uso responsável
        dos recursos hídricos.
      </p>
    </div>

    <nav class="site-footer__nav" aria-label="Navegação do rodapé">
      <h2 class="site-footer__heading">Navegação</h2>
      <ul>
        <?php foreach (AQ_NAV as $item): ?>
          <li><a href="<?php aq_out($item['href']); ?>"><?php aq_out($item['label']); ?></a></li>
        <?php endforeach; ?>
        <li><a href="#vantagens">Vantagens</a></li>
      </ul>
    </nav>

  </div>

  <div class="container site-footer__bottom">
    <p>&copy; <?php echo date('Y'); ?> <?php aq_out(AQ_SITE_NAME); ?>. Todos os direitos reservados.</p>
    <p><?php aq_out(AQ_SITE_TAGLINE); ?></p>
  </div>
</footer>
