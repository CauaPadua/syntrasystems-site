<?php
/**
 * Seção 3 — Sistema: como o Aquapulse apoia a operação.
 *
 * Fundo branco, textos à esquerda e, à direita, um carrossel com cinco
 * capturas do próprio sistema. As capturas são imagens estáticas: esta seção
 * não conversa com a API nem abre o dashboard em iframe.
 *
 * O carrossel é o mesmo já usado na seção "Por que monitorar": ciclo de 3 s,
 * revelação lateral da direita para a esquerda, pausas apenas temporárias e
 * sem controles manuais. O script vive em assets/js/sistema-carrossel.js,
 * isolado, para que as duas instâncias nunca compartilhem temporizador nem
 * listener.
 *
 * A âncora `#sistema` é preservada: o menu principal ("Sobre") aponta para ela.
 */

/** @var array<int, array{arquivo:string, alt:string}> */
$aq_sistema_telas = [                                                     // uma entrada por captura de tela, na ordem de exibição
    [
        'arquivo' => '01-visao-geral.webp',                               // arquivo em assets/images/sistema/
        'alt'     => 'Visão geral do Aquapulse com indicadores, comparativo entre represas e situação geral.', // descrição para leitores de tela
    ],
    [
        'arquivo' => '02-nivel-reservatorio.webp',
        'alt'     => 'Tela de nível do reservatório do Aquapulse, com gráfico do período e faixas operacionais.',
    ],
    [
        'arquivo' => '03-relatorios.webp',
        'alt'     => 'Tela de relatórios do Aquapulse, com histórico, filtros e formatos de exportação.',
    ],
    [
        'arquivo' => '04-mapas.webp',
        'alt'     => 'Tela de mapas do Aquapulse, com a localização das represas monitoradas.',
    ],
    [
        'arquivo' => '05-alertas.webp',
        'alt'     => 'Tela de alertas do Aquapulse, com ocorrências críticas e de atenção.',
    ],
];

$aq_sistema_total = count($aq_sistema_telas);                             // total de telas, usado no contador "01 / 05"
?>
<section class="aq-system" id="sistema" aria-labelledby="sistema-titulo">
  <div class="container aq-system__inner"> <?php /* grade de duas colunas: texto (32%) e carrossel (68%) */ ?>

    <div class="aq-system__texto">

      <p class="aq-system__id">Como a Aquapulse apoia sua operação</p>

      <h2 class="aq-system__titulo" id="sistema-titulo">
        Visão clara para monitorar, analisar e decidir
      </h2>

      <p class="aq-system__lead">
        O Aquapulse centraliza as informações estratégicas dos seus reservatórios
        em um só lugar, com dados confiáveis e atualizados para apoiar decisões
        mais seguras e operações mais eficientes.
      </p>

      <ul class="aq-system__beneficios">
        <?php foreach (AQ_SYSTEM_POINTS as $point): // os três benefícios vêm de includes/config.php ?>
          <li class="aq-system__beneficio">
            <span class="aq-system__icone" aria-hidden="true"><?php aq_the_icon($point['icon']); ?></span>
            <div class="aq-system__beneficio-corpo">
              <h3 class="aq-system__beneficio-titulo"><?php aq_out($point['title']); ?></h3>
              <p class="aq-system__beneficio-texto"><?php aq_out($point['text']); ?></p>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

    </div>

    <?php /*
      Carrossel das capturas. A primeira já vem marcada como `is-base` no HTML,
      então ela aparece mesmo sem JavaScript; as outras ficam recortadas.
    */ ?>
    <?php /* data-intervalo (ms entre trocas) e data-transicao (ms da animação) são lidos por sistema-carrossel.js */ ?>
    <div class="aq-system__carrossel"
         data-sistema-carrossel
         data-intervalo="3000"
         data-transicao="650"
         role="group"
         aria-roledescription="carrossel"
         aria-label="Telas do sistema Aquapulse">

      <figure class="aq-system__quadro">
        <div class="aq-system__palco"> <?php /* área 2:1 onde as capturas ficam empilhadas */ ?>
          <?php foreach ($aq_sistema_telas as $i => $tela): // $i = posição da tela (0 a 4) ?>
            <?php /*
              `is-base` marca a captura que preenche o quadro. A que entra ganha
              `is-entrando` e é revelada por cima, da direita para a esquerda,
              sem que a anterior saia antes da hora.
            */ ?>
            <?php /* só a primeira fica visível a leitores de tela; as outras recebem aria-hidden */ ?>
            <div class="aq-system__tela<?php echo $i === 0 ? ' is-base' : ''; ?>"
                 data-sistema-tela="<?php echo (int) $i; ?>"
                 <?php echo $i === 0 ? '' : 'aria-hidden="true"'; ?>>
              <?php /* loading="lazy": a seção fica abaixo da primeira tela, então as imagens só baixam quando necessário */ ?>
              <img class="aq-system__imagem"
                   src="<?php aq_out(aq_asset('images/sistema/' . $tela['arquivo'])); ?>"
                   width="3840" height="1920"
                   alt="<?php aq_out($tela['alt']); ?>"
                   loading="lazy" fetchpriority="low" decoding="async">
            </div>
          <?php endforeach; ?>
        </div>

        <figcaption class="aq-system__rodape">
          <span class="aq-system__legenda">Prévia ilustrativa da interface do Aquapulse.</span>
          <span class="aq-system__contador">
            <span data-sistema-atual>01</span> / <?php echo sprintf('%02d', $aq_sistema_total); // %02d = número com 2 dígitos ("05") ?>
          </span>
        </figcaption>
      </figure>

    </div>

  </div>
</section>
