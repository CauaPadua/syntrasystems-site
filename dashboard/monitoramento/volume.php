<?php
/** Aquapulse — Monitoramento / Volume armazenado. */
/*
 * Quanto de água UMA represa guarda, quanto cabe e como a capacidade se divide.
 * Dados de GET api/v1/monitoring/storage.php, carregados por
 * assets/js/pages/storage.js (com monitor-page.js).
 */

declare(strict_types=1);

define('AQ_DEPTH', 2);
require dirname(__DIR__) . '/includes/page.php';

aq_page_start([
    'route'    => 'monitoring.storage',
    'title'    => 'Volume armazenado',
    'subtitle' => 'Acompanhe a reserva hídrica e a capacidade disponível',
]);

echo aq_monitor_bar(['periods' => ['30d' => 'Últimos 30 dias', '7d' => 'Últimos 7 dias', '90d' => 'Últimos 90 dias']]); // 30 dias aparece primeiro: é o período padrão desta tela
?>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'volume', 'label' => 'Volume atual', 'icon' => 'waves', 'unit' => 'hm³', 'tip' => 'Volume de água armazenado no momento.']);
  echo aq_kpi(['id' => 'capacity', 'label' => 'Capacidade total', 'icon' => 'ruler', 'unit' => 'hm³', 'tip' => 'Capacidade máxima de armazenamento do reservatório.']);
  echo aq_kpi(['id' => 'occupancy', 'label' => 'Ocupação', 'icon' => 'chart-up', 'unit' => '%', 'tip' => 'Percentual da capacidade total em uso.']);
  echo aq_kpi(['id' => 'available', 'label' => 'Volume disponível', 'icon' => 'droplet', 'unit' => 'hm³', 'tip' => 'Volume ainda disponível para utilização.']); // capacidade - volume
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Evolução do volume armazenado', 'tip' => 'Volume acumulado no período, com a capacidade máxima marcada.']); ?>
    <div data-content="evolution" hidden>
      <?php echo aq_chart(['id' => 'grafico-volume', 'size' => 'lg', 'axis' => 'Volume (hm³)', 'desc' => 'Evolução do volume armazenado com a capacidade máxima.']); // data.evolution + linha da capacidade ?>
      <?php echo aq_legend([
          ['label' => 'Volume armazenado', 'color' => '#0b5bea'],
          ['label' => 'Capacidade máxima', 'color' => '#ef4444', 'style' => 'dashed'],
      ]); ?>
    </div>
    <?php echo aq_states('evolution'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Ocupação do reservatório', 'tip' => 'Proporção entre volume armazenado e volume disponível.']); ?>
    <div data-content="occupancy" hidden>
      <div style="max-width:280px;margin:0 auto;position:relative">
        <?php echo aq_chart(['id' => 'medidor-ocupacao', 'size' => 'md', 'desc' => 'Indicador circular da ocupação do reservatório.']); // rosca armazenado x disponível (data.occupancy) ?>
      </div>
      <ul style="margin-top:6px;display:flex;flex-direction:column;gap:11px" data-occupancy-legend></ul>
      <p class="aq-card__sub" style="text-align:center;margin-top:14px" data-field="occupancy.total"></p>
    </div>
    <?php echo aq_states('occupancy'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--5-4-7">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Balanço hídrico diário', 'tip' => 'Entradas e saídas de água por dia no período.']); ?>
    <div data-content="balance" hidden>
    <?php echo aq_chart(['id' => 'grafico-balanco', 'size' => 'md', 'axis' => 'Volume (hm³)', 'desc' => 'Balanço hídrico diário com entradas e saídas.']); // barras positivas (entrada) e negativas (saída) ?>
    <?php echo aq_legend([
        ['label' => 'Entrada (hm³)', 'color' => '#0b5bea', 'style' => 'square'],
        ['label' => 'Saída (hm³)', 'color' => '#b6d3fe', 'style' => 'square'],
    ]); ?>
    </div>
    <?php echo aq_states('balance'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Distribuição da capacidade', 'tip' => 'Como a capacidade total está dividida.']); ?>
    <div class="aq-list" data-distribution></div> <?php /* volume útil, reserva técnica e disponível (data.distribution) */ ?>
    <div class="aq-form-row" style="border-top:1px solid var(--aq-border);margin-top:8px;padding-top:12px">
      <strong>Capacidade total</strong>
      <strong data-field="distribution.total">—</strong>
    </div>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head([
        'title'   => 'Histórico de volume',
        'actions' => '<a class="aq-card__link" href="#">Ver histórico completo ' . aq_icon('arrow-right') . '</a>',
    ]); ?>
    <?php echo aq_table_open('Histórico de volume'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr>
          <th scope="col">Data</th>
          <th scope="col" class="is-num">Volume (hm³)</th>
          <th scope="col" class="is-num">Ocupação (%)</th>
          <th scope="col" class="is-num">Variação (24h)</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody data-history></tbody> <?php /* data.history: um registro por dia */ ?>
    </table>
    <?php echo aq_table_close(); ?>
    <p class="aq-card__sub" style="margin-top:12px">Atualização automática a cada 5 minutos</p>
  </article>
</div>

<article class="aq-card" style="flex-direction:row;align-items:center;gap:18px"> <?php /* faixa de destaque com o ganho no período (data.insight) */ ?>
  <span class="aq-kpi__icon aq-kpi__icon--success" aria-hidden="true"><?php aq_the_icon('chart-up'); ?></span>
  <div style="flex:1 1 auto">
    <h3 style="font-size:1rem">Ganho acumulado no período</h3>
    <p class="aq-card__sub">Diferença entre o primeiro e o último dia do período selecionado.</p>
  </div>
  <p class="aq-kpi__value aq-kpi__value--success" style="font-size:1.9rem">
    <span data-field="insight.value">—</span><span class="aq-kpi__unit">hm³</span>
  </p>
  <span data-field="insight.badge"></span> <?php /* badge com a variação em % */ ?>
</article>

<?php aq_page_end(['scripts' => ['pages/storage.js'], 'monitor' => true]); // lógica desta tela