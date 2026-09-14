<?php
/** Aquapulse — Monitoramento / Comparativo de vazão. */
/*
 * Compara a vazão de UMA represa entre dois períodos semanais escolhidos pelo
 * usuário. Dados de GET api/v1/monitoring/flow-comparison.php, carregados por
 * assets/js/pages/comparison.js (com monitor-page.js).
 * É a única tela de monitoramento que não usa aq_monitor_bar(), porque precisa
 * de DOIS seletores de período.
 */

declare(strict_types=1);

define('AQ_DEPTH', 2);
require dirname(__DIR__) . '/includes/page.php';

aq_page_start([
    'route'    => 'monitoring.comparison',
    'title'    => 'Comparativo de vazão',
    'subtitle' => 'Compare a vazão atual com períodos anteriores',
]);

$ranges = [                                                               // semanas disponíveis; valor e texto iguais, e idênticos à allowlist do endpoint
    '16 – 22 mai'     => '16 – 22 mai',
    '09 – 15 mai'     => '09 – 15 mai',
    '02 – 08 mai'     => '02 – 08 mai',
    '25 abr – 01 mai' => '25 abr – 01 mai',
];
?>

<!-- barra própria: esta tela tem dois seletores de período independentes -->
<section class="aq-context" aria-label="Contexto da comparação">
  <?php echo aq_select(['id' => 'filtro-represa', 'label' => 'Represa analisada', 'options' => []]); // opções preenchidas pelo JS ?>

  <div class="aq-field"> <?php /* mesma estrutura de aq_monitor_bar(), repetida aqui */ ?>
    <span class="aq-field__label">Código</span>
    <strong style="font-size:1rem;line-height:42px" data-field="reservoir.code">—</strong>
  </div>

  <div class="aq-field">
    <span class="aq-field__label">&nbsp;</span>
    <span class="aq-status-text" style="line-height:42px">
      <span class="aq-dot aq-dot--normal" data-field-class="reservoir.telemetry"></span>
      <span data-field="reservoir.telemetry">—</span>
    </span>
  </div>

  <span class="aq-context__spacer"></span>

  <?php echo aq_select(['id' => 'filtro-atual', 'label' => 'Período atual', 'options' => $ranges, 'value' => '16 – 22 mai']); // enviado como ?current= ?>
  <?php echo aq_select(['id' => 'filtro-anterior', 'label' => 'Comparar com', 'options' => $ranges, 'value' => '09 – 15 mai']); // enviado como ?previous= (a API recusa se for igual ao atual) ?>
</section>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'current', 'label' => 'Vazão média atual', 'icon' => 'waves', 'unit' => 'm³/s', 'tip' => 'Média do período selecionado como atual.']);
  echo aq_kpi(['id' => 'previous', 'label' => 'Período anterior', 'icon' => 'waves', 'unit' => 'm³/s', 'tip' => 'Média do período usado como comparação.']);
  echo aq_kpi(['id' => 'variation', 'label' => 'Variação', 'icon' => 'chart-up', 'unit' => '%', 'tip' => 'Diferença percentual entre os dois períodos.']);
  echo aq_kpi(['id' => 'max_diff', 'label' => 'Maior diferença', 'icon' => 'arrow-down-circle', 'unit' => 'm³/s', 'tip' => 'Dia com a maior diferença absoluta entre os períodos.']);
  ?>
</div>

<div class="aq-grid aq-grid--2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Vazão diária comparada', 'tip' => 'Vazão média de cada dia nos dois períodos.']); ?>
    <div data-content="chart" hidden>
      <?php echo aq_chart(['id' => 'grafico-comparativo', 'size' => 'lg', 'axis' => 'm³/s', 'desc' => 'Comparação diária da vazão entre dois períodos.']); // data.chart (current x previous) ?>
      <div data-legend-periods></div> <?php /* legenda gerada pelo JS com os nomes dos períodos escolhidos */ ?>
    </div>
    <?php echo aq_states('chart'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Diferença por dia', 'tip' => 'Diferença diária: verde quando positiva, âmbar quando negativa.']); ?>
    <div data-content="diff" hidden>
      <?php echo aq_chart(['id' => 'grafico-diferenca', 'size' => 'lg', 'axis' => 'm³/s', 'desc' => 'Diferença diária de vazão entre os períodos.']); // barras de data.diff_chart ?>
      <?php echo aq_legend([
          ['label' => 'Diferença positiva', 'color' => '#16a34a', 'style' => 'square'],
          ['label' => 'Diferença negativa', 'color' => '#f59e0b', 'style' => 'square'],
      ]); ?>
    </div>
    <?php echo aq_states('diff'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--5-4-7">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Afluência x defluência', 'tip' => 'Médias de entrada e saída no período atual.']); ?>
    <div data-content="inout" hidden>
    <?php echo aq_chart(['id' => 'grafico-afl-defl', 'size' => 'md', 'axis' => 'm³/s', 'desc' => 'Afluência e defluência médias do período atual.']); // data.in_out ?>
    <?php echo aq_legend([
        ['label' => 'Afluência média (atual)', 'color' => '#0b5bea', 'style' => 'square'],
        ['label' => 'Defluência média (atual)', 'color' => '#b6d3fe', 'style' => 'square'],
    ]); ?>
    </div>
    <?php echo aq_states('inout'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Resumo da comparação', 'tip' => 'Destaques calculados a partir dos dois períodos.']); ?>
    <div class="aq-list" data-summary></div> <?php /* melhor dia, menor vazão, médias e tendência (data.summary) */ ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Comparativo diário', 'tip' => 'Valores médios diários lado a lado.']); ?>
    <?php echo aq_table_open('Comparativo diário de vazão'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr>
          <th scope="col">Dia</th>
          <th scope="col" class="is-num">Atual (m³/s)</th>
          <th scope="col" class="is-num">Anterior (m³/s)</th>
          <th scope="col" class="is-num">Diferença (m³/s)</th>
          <th scope="col" class="is-num">Variação (%)</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody data-rows></tbody> <?php /* data.rows: um dia por linha */ ?>
    </table>
    <?php echo aq_table_close(); ?>
    <p class="aq-card__sub" style="margin-top:12px">Valores médios diários (m³/s)</p>
  </article>
</div>

<article class="aq-card" style="flex-direction:row;align-items:center;gap:18px">
  <span class="aq-kpi__icon" aria-hidden="true"><?php aq_the_icon('chart-up'); ?></span>
  <div>
    <h3 style="font-size:1rem">Insight</h3>
    <p class="aq-card__sub" data-field="insight.text"></p> <?php /* "A vazão média aumentou X m³/s..." */ ?>
  </div>
</article>

<?php aq_page_end(['scripts' => ['pages/comparison.js'], 'monitor' => true]); // lógica desta tela