<?php
/**
 * Seção 2 — Por que monitorar.
 *
 * Bloco editorial claro logo abaixo da hero: selo e chamada lateral à
 * esquerda, título/parágrafo/CTA à direita e, no rodapé, o carrossel de
 * fotografias ao lado de um painel editorial fixo.
 *
 * A âncora `#informacoes` é preservada porque o menu principal aponta para
 * ela (item "Importância"). O botão desta seção leva a `#vantagens`, que é
 * onde o site explica por que o monitoramento vale a pena.
 *
 * Os dados dos slides vêm do manifesto do pacote de referência e ficam locais
 * a esta seção: são conteúdo estático, sem API nem banco.
 *
 * O comportamento do carrossel está em assets/js/monitorar.js.
 */

/** @var array<int, array{id:string, src:string, alt:string, pos:string}> */
$aq_monitorar_slides = [                                                  // fotografias do carrossel, na ordem de exibição
    [
        'id'  => 'monitoramento',                                         // identificador interno do slide
        'src' => 'images/monitorar/01-monitoramento.webp',                // caminho dentro de assets/
        'alt' => 'Imagem ilustrativa de profissional operando uma estação de monitoramento junto a um reservatório.',
        'pos' => '50% 50%',                                               // ponto focal da foto (object-position) quando ela é recortada
    ],
    [
        'id'  => 'vista-aerea',
        'src' => 'images/monitorar/02-vista-aerea.webp',
        'alt' => 'Imagem ilustrativa de vista aérea de um reservatório azul cercado por morros e vegetação.',
        'pos' => '50% 50%',
    ],
    [
        'id'  => 'barragem',
        'src' => 'images/monitorar/03-barragem.webp',
        'alt' => 'Imagem ilustrativa de uma barragem com escoamento controlado e vegetação nas margens.',
        'pos' => '50% 50%',
    ],
    [
        'id'  => 'reservatorio',
        'src' => 'images/monitorar/04-reservatorio.webp',
        'alt' => 'Imagem ilustrativa de estação de monitoramento com painel solar próxima a um reservatório.',
        'pos' => '50% 50%',
    ],
];

/**
 * Painel editorial à direita do carrossel. Conteúdo fixo: não acompanha o
 * slide ativo nem depende do JavaScript.
 *
 * @var array<int, array{titulo:string, texto:string}>
 */
$aq_monitorar_passos = [
    [
        'titulo' => 'Observar mudanças',
        'texto'  => 'Acompanhar o comportamento da água ajuda a compreender o cenário da represa.',
    ],
    [
        'titulo' => 'Antecipar riscos',
        'texto'  => 'Reconhecer alterações permite planejar respostas com mais antecedência.',
    ],
    [
        'titulo' => 'Orientar decisões',
        'texto'  => 'Informações organizadas apoiam uma gestão responsável dos recursos hídricos.',
    ],
];

$aq_monitorar_total = count($aq_monitorar_slides);                        // total de slides (usado nos rótulos "1 de 4")
?>
<section class="aq-monitorar" id="informacoes" aria-labelledby="monitorar-titulo">
  <div class="container">
    <div class="aq-monitorar__quadro">

      <div class="aq-monitorar__topo"> <?php /* parte de cima: coluna lateral + texto principal */ ?>

        <div class="aq-monitorar__lateral">
          <p class="aq-monitorar__selo">
            <span class="aq-monitorar__selo-ponto" aria-hidden="true"></span>
            Por que monitorar
          </p>

          <p class="aq-monitorar__chamada">
            Água segura.<br>Futuro protegido.
          </p>

          <p class="aq-monitorar__apoio">Informação que orienta decisões</p>
        </div>

        <div class="aq-monitorar__texto">
          <h2 class="aq-monitorar__titulo" id="monitorar-titulo">
            Proteger a água é cuidar de quem depende dela.
          </h2>

          <p class="aq-monitorar__paragrafo">
            Represas abastecem cidades, geram energia e sustentam comunidades.
            Acompanhar suas mudanças ajuda a antecipar riscos e orientar uma
            gestão responsável.
          </p>

          <a class="aq-monitorar__cta" href="#vantagens"> <?php /* leva à seção de vantagens */ ?>
            <span>Entenda a importância</span>
            <?php aq_the_icon('arrow-up-right'); ?>
          </a>
        </div>

      </div>

      <div class="aq-monitorar__paineis"> <?php /* parte de baixo: carrossel (68%) + painel editorial (32%) */ ?>

        <?php /* Carrossel: painel de fotografias e, abaixo dele, os controles. */ ?>
        <?php /* data-intervalo e data-transicao (em ms) são lidos por monitorar.js */ ?>
        <div class="aq-monitorar__carrossel"
             data-monitorar-carrossel
             data-intervalo="3000"
             data-transicao="650"
             role="group"
             aria-roledescription="carrossel"
             aria-label="Fotografias de monitoramento de represas">

          <div class="aq-monitorar__palco">
            <div class="aq-monitorar__quadros" data-monitorar-quadros>
              <?php foreach ($aq_monitorar_slides as $i => $slide): // um <figure> por fotografia ?>
                <?php /*
                  `is-base` marca a fotografia que preenche o painel. A que
                  entra ganha `is-entrando` e é revelada por cima, da direita
                  para a esquerda, sem que esta saia antes da hora.
                */ ?>
                <?php /* aria-label "1 de 4" identifica o slide; os que não estão visíveis recebem aria-hidden */ ?>
                <figure class="aq-monitorar__slide<?php echo $i === 0 ? ' is-base' : ''; ?>"
                        data-monitorar-slide="<?php echo (int) $i; ?>"
                        role="group"
                        aria-roledescription="slide"
                        aria-label="<?php echo (int) ($i + 1); ?> de <?php echo (int) $aq_monitorar_total; ?>"
                        <?php echo $i === 0 ? '' : 'aria-hidden="true"'; ?>>
                  <img class="aq-monitorar__foto"
                       src="<?php aq_out(aq_asset($slide['src'])); ?>"
                       width="1774" height="887"
                       alt="<?php aq_out($slide['alt']); ?>"
                       style="object-position: <?php aq_out($slide['pos']); ?>"
                       loading="lazy" fetchpriority="low"
                       decoding="async">
                </figure>
              <?php endforeach; ?>
            </div>
          </div>

          <?php /*
            Setas e indicadores. Só aparecem depois que o script inicializa:
            sem JavaScript eles não teriam efeito nenhum.
          */ ?>
          <div class="aq-monitorar__controles" data-monitorar-controles hidden>
            <button class="aq-monitorar__botao" type="button" data-monitorar-anterior
                    aria-label="Fotografia anterior">
              <?php aq_the_icon('chevron-left'); ?>
            </button>

            <div class="aq-monitorar__pontos"> <?php /* um ponto clicável por slide */ ?>
              <?php foreach ($aq_monitorar_slides as $i => $slide): ?>
                <?php /* aria-current marca o ponto do slide visível */ ?>
                <button class="aq-monitorar__ponto<?php echo $i === 0 ? ' is-ativo' : ''; ?>"
                        type="button"
                        data-monitorar-ponto="<?php echo (int) $i; ?>"
                        <?php echo $i === 0 ? 'aria-current="true"' : ''; ?>
                        aria-label="Fotografia <?php echo (int) ($i + 1); ?> de <?php echo (int) $aq_monitorar_total; ?>">
                  <span class="aq-monitorar__ponto-marca" aria-hidden="true"></span>
                </button>
              <?php endforeach; ?>
            </div>

            <button class="aq-monitorar__botao" type="button" data-monitorar-proximo
                    aria-label="Próxima fotografia">
              <?php aq_the_icon('chevron-right'); ?>
            </button>
          </div>
        </div>

        <?php /* Painel editorial fixo: não muda com o slide ativo. */ ?>
        <div class="aq-monitorar__acao">
          <h3 class="aq-monitorar__acao-titulo">Do acompanhamento à ação</h3>

          <ol class="aq-monitorar__passos"> <?php /* <ol>: lista ordenada, a sequência tem significado */ ?>
            <?php foreach ($aq_monitorar_passos as $i => $passo): ?>
              <li class="aq-monitorar__passo">
                <?php /* o número é decorativo: a própria lista já ordena para o leitor de tela */ ?>
                <span class="aq-monitorar__passo-numero" aria-hidden="true"><?php echo sprintf('%02d', $i + 1); // 01, 02, 03 ?></span>
                <div class="aq-monitorar__passo-corpo">
                  <h4 class="aq-monitorar__passo-titulo"><?php aq_out($passo['titulo']); ?></h4>
                  <p class="aq-monitorar__passo-texto"><?php aq_out($passo['texto']); ?></p>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>

      </div>

    </div>
  </div>
</section>
