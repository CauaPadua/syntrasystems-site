<?php
/** Aquapulse — Monitoramento / Previsão de duração da água. */

declare(strict_types=1);

define('AQ_DEPTH', 2);
require dirname(__DIR__) . '/includes/page.php';

aq_page_start([
    'route'    => 'monitoring.duration',
    'title'    => 'Previsão de duração da água',
    'subtitle' => 'Estime por quanto tempo a reserva poderá atender à demanda',
]);

echo aq_monitor_bar([
    'period_id'    => 'filtro-horizonte',
    'period_label' => 'Horizonte de previsão',
    'periods'      => ['30d' => 'Próximos 30 dias', '60d' => 'Próximos 60 dias', '90d' => 'Próximos 90 dias', '180d' => 'Próximos 180 dias'],
    'period_value' => '90d',
]);
?>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'duration', 'label' => 'Duração estimada', 'icon' => 'clock', 'unit' => 'dias', 'tip' => 'Projeção demonstrativa com base no consumo médio atual.']);
  echo aq_kpi(['id' => 'useful', 'label' => 'Volume útil', 'icon' => 'waves', 'unit' => 'hm³', 'tip' => 'Volume efetivamente disponível para abastecimento.']);
  echo aq_kpi(['id' => 'consumption', 'label' => 'Consumo médio', 'icon' => 'chart-up', 'unit' => 'hm³/dia', 'tip' => 'Média de retirada diária dos últimos 7 dias.']);
  echo aq_kpi(['id' => 'reliability', 'label' => 'Confiabilidade da previsão', 'icon' => 'shield-check', 'tone' => 'success', 'unit' => '%', 'tip' => 'Grau de confiança do modelo demonstrativo.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Projeção da reserva', 'tip' => 'Evolução do volume nos três cenários de consumo.']); ?>
    <div data-content="projection" hidden>
      <?php echo aq_chart(['id' => 'grafico-projecao', 'size' => 'lg', 'axis' => 'Volume (hm³)', 'desc' => 'Projeção do volume da reserva em três cenários de consumo.']); ?>
      <?php echo aq_legend([
          ['label' => 'Consumo atual', 'color' => '#0b5bea'],
          ['label' => 'Consumo elevado (+20%)', 'color' => '#f59e0b', 'style' => 'dashed'],
          ['label' => 'Economia de 10%', 'color' => '#16a34a', 'style' => 'dashed'],
      ]); ?>
    </div>
    <?php echo aq_states('projection'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Estimativa atual', 'tip' => 'Duração projetada no cenário base.']); ?>
    <div data-content="estimate" hidden style="text-align:center">
      <div style="max-width:280px;margin:0 auto">
        <?php echo aq_chart(['id' => 'medidor-duracao', 'size' => 'md', 'desc' => 'Indicador circular da duração estimada.']); ?>
      </div>
      <p class="aq-card__sub" style="margin-top:10px">
        <span class="aq-status-text" style="font-weight:inherit">
          <?php aq_the_icon('calendar'); ?>
          <span>Data estimada: <strong data-field="estimate.date">—</strong></span>
        </span>
      </p>
      <p style="margin-top:10px" data-field="estimate.badge"></p>
      <p class="aq-card__sub" style="margin-top:10px" data-field="estimate.note"></p>
    </div>
    <?php echo aq_states('estimate'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--3-3-3-4-8">
  <div data-scenarios style="display:contents"></div>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Fatores considerados', 'tip' => 'Variáveis usadas pelo cálculo demonstrativo.']); ?>
    <div class="aq-list" data-factors></div>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head([
        'title'   => 'Histórico das estimativas',
        'actions' => '<a class="aq-card__link" href="#">Ver histórico completo ' . aq_icon('arrow-right') . '</a>',
    ]); ?>
    <?php echo aq_table_open('Histórico das estimativas de duração'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr>
          <th scope="col">Data</th>
          <th scope="col" class="is-num">Estimativa (dias)</th>
          <th scope="col" class="is-num">Variação</th>
          <th scope="col">Cenário</th>
          <th scope="col" class="is-num">Confiança</th>
        </tr>
      </thead>
      <tbody data-estimates></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <p class="aq-card__sub" style="margin-top:12px">Atualização automática a cada 5 minutos</p>
  </article>
</div>

<article class="aq-card" style="flex-direction:row;align-items:center;gap:18px">
  <span class="aq-kpi__icon" aria-hidden="true"><?php aq_the_icon('chart-bars'); ?></span>
  <div style="flex:1 1 auto">
    <h3 style="font-size:1rem">Insight importante</h3>
    <p class="aq-card__sub" data-field="insight.text"></p>
  </div>
  <div style="text-align:center;padding:12px 26px;border-radius:12px;background:var(--aq-primary-soft)">
    <p class="aq-kpi__value" style="color:var(--aq-primary);font-size:1.7rem">
      <span data-field="insight.gain">—</span><span class="aq-kpi__unit" style="color:var(--aq-primary)">dias</span>
    </p>
    <p style="font-size:.8rem;color:var(--aq-text-secondary)">Ganho estimado</p>
  </div>
</article>

<div class="aq-demo-note">
  <?php aq_the_icon('info'); ?>
  <span><strong>Projeção demonstrativa.</strong> O cálculo atual é um balanço linear simples, isolado em
  <code>DurationForecastService</code>. O modelo definitivo será implementado junto com o banco de dados.</span>
</div>

<?php aq_page_end(['scripts' => ['pages/duration.js'], 'monitor' => true]);
