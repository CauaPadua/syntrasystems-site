<?php
/** Aquapulse — Monitoramento / Precipitação. */
/*
 * Chuva na bacia de UMA represa: medida (24h, 7 dias, mês), distribuição por
 * estação e previsão de 5 dias. Dados de GET api/v1/monitoring/precipitation.php,
 * carregados por assets/js/pages/rain.js (com monitor-page.js).
 */

declare(strict_types=1);

define('AQ_DEPTH', 2);
require dirname(__DIR__) . '/includes/page.php';

aq_page_start([
    'route'    => 'monitoring.rain',
    'title'    => 'Precipitação',
    'subtitle' => 'Acompanhe o volume de chuvas na bacia da represa',
]);

echo aq_monitor_bar(['periods' => ['7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias']]);
?>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'rain_24h', 'label' => 'Precipitação 24h', 'icon' => 'cloud-rain', 'unit' => 'mm', 'tip' => 'Soma das leituras de chuva das últimas 24 horas.']);
  echo aq_kpi(['id' => 'rain_7d', 'label' => 'Acumulado em 7 dias', 'icon' => 'cloud-rain', 'unit' => 'mm', 'tip' => 'Soma da chuva registrada nos últimos sete dias.']);
  echo aq_kpi(['id' => 'rain_month', 'label' => 'Acumulado no mês', 'icon' => 'cloud-rain', 'unit' => 'mm', 'tip' => 'Total acumulado no mês corrente.']);
  echo aq_kpi(['id' => 'intensity', 'label' => 'Intensidade', 'icon' => 'gauge', 'tip' => 'Classificação pelo acumulado de 24 horas, com os mesmos limites que abrem os alertas.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Precipitação no período', 'tip' => 'Chuva diária em barras e acumulado do período em linha.']); ?>
    <?php echo aq_legend([
        ['label' => 'Precipitação diária (mm)', 'color' => '#0b5bea', 'style' => 'square'],
        ['label' => 'Acumulado (mm)', 'color' => '#0b5bea'],
    ], true); ?>
    <div data-content="chart" hidden>
      <?php echo aq_chart(['id' => 'grafico-chuva', 'size' => 'lg', 'desc' => 'Precipitação diária e acumulado do período.']); // gráfico misto: barras (data.chart.daily) + linha (data.chart.accumulated) ?>
    </div>
    <?php echo aq_states('chart'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Últimas 24 horas', 'tip' => 'Chuva acumulada nas últimas 24 horas e a classificação correspondente.']); ?>
    <div data-content="current" hidden style="text-align:center">
      <span style="display:inline-flex;color:var(--aq-text-secondary)" aria-hidden="true">
        <?php echo aq_icon('cloud-rain', 'aq-weather-icon'); // ícone grande de chuva (classe extra aumenta o tamanho) ?>
      </span>
      <p class="aq-kpi__value" style="justify-content:center;font-size:2.1rem;margin-top:8px">
        <span data-field="current.value">—</span><span class="aq-kpi__unit">mm</span>
      </p>
      <p style="font-weight:700;margin-top:4px" data-field="current.label"></p> <?php /* "Chuva moderada"; a cor é aplicada pelo JS conforme o status */ ?>
      <p class="aq-card__sub" style="margin-top:18px" data-field="current.last">—</p> <?php /* "Última leitura há 12 s" */ ?>
    </div>
    <?php echo aq_states('current'); ?>
  </article>
</div>

<article class="aq-card">
  <?php echo aq_card_head(['title' => 'Pluviômetros', 'tip' => 'Cada leitura é a chuva desde o envio anterior; o acumulado de 24 h é a soma delas.']); ?>
  <div class="aq-list" data-sensors></div> <?php /* data.sensors */ ?>
</article>

<div data-warning hidden></div> <?php /* aviso exibido pelo JS quando o acumulado de 24 h passa do limite de atenção */ ?>

<?php aq_page_end(['scripts' => ['pages/rain.js'], 'monitor' => true]); // lógica desta tela