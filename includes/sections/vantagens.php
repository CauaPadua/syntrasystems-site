<?php
/**
 * Seção 4 — Vantagens.
 *
 * Seção fotográfica de largura total: a barragem fica livre à esquerda e todo
 * o conteúdo é sobreposto à direita, em HTML real e selecionável. A fotografia
 * é apenas ilustrativa e fica atrás do conteúdo; o texto segue o fluxo normal
 * do documento, sem posicionamento absoluto.
 *
 * Duas âncoras precisam continuar existindo aqui:
 *   - `#vantagens`, usada pelo rodapé e pelo botão da seção "Por que monitorar";
 *   - `#solicitar-demonstracao`, usada pelo item "Contato" do menu e pelo botão
 *     "Solicitar acesso" da tela de login.
 *
 * O botão de demonstração mantém o comportamento que já existia no projeto:
 * revela um aviso de canal em preparação, tratado por assets/js/main.js.
 */

/** @var array<int, array{titulo:string, texto:string}> */
$aq_vantagens = [                                                         // os três itens da lista de vantagens
    [
        'titulo' => 'Segurança para agir',
        'texto'  => 'Antecipe mudanças e responda com mais clareza a situações críticas.',
    ],
    [
        'titulo' => 'Confiança para planejar',
        'texto'  => 'Organize informações para orientar a operação e apoiar a gestão.',
    ],
    [
        'titulo' => 'Responsabilidade para preservar',
        'texto'  => 'Acompanhe decisões, fortaleça a governança e cuide dos recursos hídricos.',
    ],
];
?>
<section class="aq-benefits" id="vantagens" aria-labelledby="vantagens-titulo">

  <?php /* fotografia decorativa: fica atrás do conteúdo e não é anunciada */ ?>
  <img class="aq-benefits__foto"
       src="<?php aq_out(aq_asset('images/aquapulse-vantagens-fundo.webp')); ?>"
       width="1586" height="992"
       alt="" aria-hidden="true"
       loading="lazy" decoding="async">

  <span class="aq-benefits__veu" aria-hidden="true"></span> <?php /* véu escuro sobre a foto, para o texto claro ter contraste */ ?>

  <div class="aq-benefits__conteudo">

    <p class="aq-benefits__id">Aquapulse <span aria-hidden="true">/</span> Vantagens</p> <?php /* a barra é só visual: leitores de tela leem "Aquapulse Vantagens" */ ?>

    <h2 class="aq-benefits__titulo" id="vantagens-titulo">
      Decisões mais seguras. Uma gestão mais consciente da água.
    </h2>

    <p class="aq-benefits__intro">
      Informação para orientar a operação, apoiar equipes e cuidar de quem
      depende da represa.
    </p>

    <ul class="aq-benefits__lista">
      <?php foreach ($aq_vantagens as $item): // um <li> por vantagem ?>
        <li class="aq-benefits__item">
          <h3 class="aq-benefits__item-titulo"><?php aq_out($item['titulo']); ?></h3>
          <p class="aq-benefits__item-texto"><?php aq_out($item['texto']); ?></p>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="aq-benefits__acao" id="solicitar-demonstracao"> <?php /* âncora do item "Contato" do menu */ ?>
      <?php /* data-demo-trigger: main.js mostra o aviso abaixo ao clicar; aria-describedby liga o botão ao aviso */ ?>
      <button class="aq-benefits__cta" type="button"
              data-demo-trigger aria-describedby="aviso-demo">
        <span>Solicitar demonstração</span>
        <?php aq_the_icon('arrow-right'); ?>
      </button>

      <p class="aq-benefits__aviso" id="aviso-demo" role="status" hidden> <?php /* role="status": o leitor de tela anuncia quando aparece */ ?>
        Canal de contato em preparação. Em breve disponível.
      </p>
    </div>

  </div>
</section>
