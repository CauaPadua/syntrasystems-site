<?php
/** Cabeçalho fixo com logotipo, navegação principal e ações. */
/*
 * Incluído por index.php. O comportamento (menu no celular, troca de tema ao
 * rolar, link ativo) é controlado por assets/js/main.js através dos atributos
 * data-header, data-nav, data-nav-toggle e data-nav-backdrop.
 */
?>
<a class="skip-link" href="#conteudo">Ir para o conteúdo principal</a> <?php /* aparece só ao receber foco pelo Tab (acessibilidade) */ ?>

<header class="site-header" id="topo" data-header>
  <div class="container site-header__inner">

    <?php /* duas versões do logotipo: a clara vale sobre a hero, a escura depois dela */ ?>
    <a class="brand" href="#inicio" aria-label="Aquapulse — página inicial">
      <img
        class="brand__logo brand__logo--light"
        src="<?php aq_out(aq_asset('images/logo-aquapulse-branco.png')); ?>"
        width="760" height="292"
        alt="Aquapulse — monitoramento de represas"
        decoding="async">
      <img
        class="brand__logo brand__logo--dark"
        src="<?php aq_out(aq_asset('images/logo-aquapulse.png')); ?>"
        width="560" height="215"
        alt="" aria-hidden="true"
        decoding="async">
    </a>

    <nav class="site-nav" id="menu-principal" aria-label="Navegação principal" data-nav>
      <ul class="site-nav__list">
        <?php foreach (AQ_NAV as $index => $item): // percorre os itens definidos em config.php; $index = posição (0, 1, 2...) ?>
          <li>
            <?php /* o primeiro item (Início) começa marcado como ativo; main.js atualiza conforme a rolagem */ ?>
            <a class="site-nav__link<?php echo $index === 0 ? ' is-active' : ''; ?>"
               href="<?php aq_out($item['href']); ?>"
               <?php echo $index === 0 ? 'aria-current="page"' : ''; ?>><?php aq_out($item['label']); ?></a>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="site-nav__actions">
        <a class="site-nav__enter" href="login.php">Entrar</a> <?php /* acesso à área restrita */ ?>

        <a class="btn btn--cyan" href="#sistema">
          <span>Conheça a solução</span>
          <?php aq_the_icon('arrow-up-right'); ?>
        </a>
      </div>
    </nav>

    <?php /* botão hambúrguer (só no celular): aria-expanded indica se o menu está aberto; aria-controls aponta para o <nav> */ ?>
    <button class="nav-toggle" type="button"
            data-nav-toggle
            aria-expanded="false"
            aria-controls="menu-principal"
            aria-label="Abrir menu de navegação">
      <span class="nav-toggle__open"><?php aq_the_icon('menu'); ?></span> <?php /* ícone de abrir (três linhas) */ ?>
      <span class="nav-toggle__close"><?php aq_the_icon('close'); ?></span> <?php /* ícone de fechar (X); o CSS mostra um ou outro */ ?>
    </button>

  </div>
</header>

<div class="nav-backdrop" data-nav-backdrop hidden></div> <?php /* fundo escuro atrás do menu aberto no celular */ ?>
