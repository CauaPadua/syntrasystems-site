<?php
/** Aquapulse — Monitoramento / Volume de vazão. */

declare(strict_types=1);

define('AQ_DEPTH', 2);
require dirname(__DIR__) . '/includes/page.php';

aq_page_start([
    'route'    => 'monitoring.flow',
    'title'    => 'Volume de vazão',
    'subtitle' => 'Acompanhe a entrada e a saída de água em tempo real',
]);

echo aq_monitor_bar(['periods' => ['24h' => 'Últimas 24 horas', '7d' => 'Últimos 7 dias']]);
?>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'flow', 'label' => 'Vazão atual', 'icon' => 'waves', 'unit' => 'm³/s', 'tip' => 'Vazão instantânea medida pelos sensores da represa.']);
  echo aq_kpi(['id' => 'inflow', 'label' => 'Afluência', 'icon' => 'arrow-down-circle', 'unit' => 'm³/s', 'tip' => 'Volume de água que entra no reservatório.']);
  echo aq_kpi(['id' => 'outflow', 'label' => 'Defluência', 'icon' => 'arrow-up-circle', 'unit' => 'm³/s', 'tip' => 'Volume de água que sai do reservatório.']);
  echo aq_kpi(['id' => 'balance', 'label' => 'Saldo hídrico', 'icon' => 'droplet', 'unit' => 'm³/s', 'tip' => 'Diferença entre afluência e defluência.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Vazão em tempo real', 'tip' => 'Afluência e defluência medidas ao longo do período selecionado.']); ?>
    <div data-content="realtime" hidden>
      <?php echo aq_chart(['id' => 'grafico-vazao', 'size' => 'lg', 'axis' => 'm³/s', 'desc' => 'Afluência e defluência ao longo do período.']); ?>
      <?php echo aq_legend([
          ['label' => 'Afluência (entrada)', 'color' => '#0b5bea'],
          ['label' => 'Defluência (saída)', 'color' => '#6ea8fe'],
      ]); ?>
    </div>
    <?php echo aq_states('realtime'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Condição da vazão', 'tip' => 'Posição da vazão atual dentro da faixa operacional esperada.']); ?>
    <div data-content="condition" hidden style="text-align:center">
      <div style="max-width:300px;margin:0 auto">
        <?php echo aq_chart(['id' => 'medidor-vazao', 'size' => 'sm', 'desc' => 'Medidor semicircular da condição da vazão.']); ?>
      </div>
      <p style="font-size:1.35rem;font-weight:800;margin-top:-46px;color:var(--aq-success)" data-field="condition.status">—</p>
      <p class="aq-card__sub" style="margin-top:34px" data-field="condition.text"></p>
      <p style="margin-top:12px" data-field="condition.badge"></p>
      <p class="aq-card__sub" style="margin-top:14px" data-field="condition.range"></p>
    </div>
    <?php echo aq_states('condition'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--5-4-7">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Média diária — últimos 7 dias', 'tip' => 'Média de afluência e defluência por dia.']); ?>
    <?php echo aq_chart(['id' => 'grafico-media-diaria', 'size' => 'md', 'axis' => 'm³/s', 'desc' => 'Média diária de afluência e defluência.']); ?>
    <?php echo aq_legend([
        ['label' => 'Afluência (média)', 'color' => '#0b5bea', 'style' => 'square'],
        ['label' => 'Defluência (média)', 'color' => '#b6d3fe', 'style' => 'square'],
    ]); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Sensores de vazão', 'tip' => 'Situação dos sensores instalados na represa.']); ?>
    <div class="aq-list" data-sensors></div>
    <p style="margin-top:12px"><a class="aq-card__link" href="operacional.php">Ver todos os sensores <?php aq_the_icon('arrow-right'); ?></a></p>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head([
        'title'   => 'Últimas leituras',
        'tip'     => 'Registros mais recentes enviados pela telemetria.',
        'actions' => '<a class="aq-card__link" href="#">Ver histórico completo ' . aq_icon('arrow-right') . '</a>',
    ]); ?>
    <?php echo aq_table_open('Últimas leituras de vazão'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr>
          <th scope="col">Horário</th>
          <th scope="col" class="is-num">Afluência (m³/s)</th>
          <th scope="col" class="is-num">Defluência (m³/s)</th>
          <th scope="col" class="is-num">Saldo (m³/s)</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody data-readings></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <p class="aq-card__sub" style="margin-top:12px">Atualização automática a cada 5 minutos</p>
  </article>
</div>

<?php aq_page_end(['scripts' => ['pages/flow.js'], 'monitor' => true]);
