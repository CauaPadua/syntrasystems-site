<?php
/** Aquapulse — Monitoramento / Precipitação. */

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
  echo aq_kpi(['id' => 'rain_24h', 'label' => 'Precipitação 24h', 'icon' => 'cloud-rain', 'unit' => 'mm', 'tip' => 'Chuva acumulada nas últimas 24 horas na bacia.']);
  echo aq_kpi(['id' => 'rain_7d', 'label' => 'Acumulado em 7 dias', 'icon' => 'cloud-rain', 'unit' => 'mm', 'tip' => 'Soma da chuva registrada nos últimos sete dias.']);
  echo aq_kpi(['id' => 'rain_month', 'label' => 'Acumulado no mês', 'icon' => 'cloud-rain', 'unit' => 'mm', 'tip' => 'Total acumulado no mês corrente.']);
  echo aq_kpi(['id' => 'intensity', 'label' => 'Intensidade atual', 'icon' => 'gauge', 'tone' => 'warning', 'tip' => 'Classificação da chuva registrada agora.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Precipitação — últimos 7 dias', 'tip' => 'Chuva diária em barras e acumulado do período em linha.']); ?>
    <?php echo aq_legend([
        ['label' => 'Precipitação diária (mm)', 'color' => '#0b5bea', 'style' => 'square'],
        ['label' => 'Acumulado (mm)', 'color' => '#0b5bea'],
    ], true); ?>
    <div data-content="chart" hidden>
      <?php echo aq_chart(['id' => 'grafico-chuva', 'size' => 'lg', 'desc' => 'Precipitação diária e acumulado do período.']); ?>
    </div>
    <?php echo aq_states('chart'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Condição atual', 'tip' => 'Situação registrada pela estação meteorológica.']); ?>
    <div data-content="current" hidden style="text-align:center">
      <span style="display:inline-flex;color:var(--aq-text-secondary)" aria-hidden="true">
        <?php echo aq_icon('cloud-rain', 'aq-weather-icon'); ?>
      </span>
      <p class="aq-kpi__value" style="justify-content:center;font-size:2.1rem;margin-top:8px">
        <span data-field="current.value">—</span><span class="aq-kpi__unit">mm</span>
      </p>
      <p style="font-weight:700;color:var(--aq-warning);margin-top:4px" data-field="current.label"></p>

      <div class="aq-grid aq-grid--2" style="margin-top:18px">
        <div style="border:1px solid var(--aq-border);border-radius:10px;padding:12px">
          <p class="aq-card__sub">Umidade relativa</p>
          <strong style="font-size:1.15rem" data-field="current.humidity">—</strong>
        </div>
        <div style="border:1px solid var(--aq-border);border-radius:10px;padding:12px">
          <p class="aq-card__sub">Última leitura</p>
          <strong style="font-size:1.15rem" data-field="current.last">—</strong>
        </div>
      </div>
    </div>
    <?php echo aq_states('current'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--5-4-7">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Distribuição na bacia', 'tip' => 'Chuva medida por região da bacia hidrográfica.']); ?>
    <div class="aq-list" data-basin></div>
    <?php echo aq_legend([
        ['label' => 'Baixa (< 10 mm)', 'color' => '#16a34a', 'style' => 'square'],
        ['label' => 'Média (10 – 20 mm)', 'color' => '#0b5bea', 'style' => 'square'],
        ['label' => 'Alta (> 20 mm)', 'color' => '#f59e0b', 'style' => 'square'],
    ]); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Previsão para os próximos 5 dias', 'tip' => 'Previsão demonstrativa — será substituída por serviço meteorológico real.']); ?>
    <div data-forecast style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;text-align:center"></div>
    <?php echo aq_legend([['label' => 'Precipitação prevista (mm)', 'color' => '#0b5bea', 'style' => 'square']]); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head([
        'title'   => 'Estações pluviométricas',
        'actions' => '<a class="aq-card__link" href="operacional.php">Ver todas as estações ' . aq_icon('arrow-right') . '</a>',
    ]); ?>
    <?php echo aq_table_open('Estações pluviométricas'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr>
          <th scope="col">Estação</th>
          <th scope="col">Localidade</th>
          <th scope="col" class="is-num">Chuva 24h (mm)</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody data-stations></tbody>
    </table>
    <?php echo aq_table_close(); ?>
  </article>
</div>

<div data-warning hidden></div>

<?php aq_page_end(['scripts' => ['pages/rain.js'], 'monitor' => true]);
