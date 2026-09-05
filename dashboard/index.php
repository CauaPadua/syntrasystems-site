<?php
/**
 * Aquapulse — Visão geral.
 *
 * Duas situações, controladas pelo filtro de represa:
 *   - "Todas as represas": dados consolidados do sistema;
 *   - represa específica: apenas os dados dela.
 *
 * Os dois blocos existem no HTML e o JavaScript alterna conforme o filtro.
 */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

aq_page_start([
    'route'    => 'overview',
    'title'    => 'Visão geral',
    'subtitle' => 'Acompanhe os principais indicadores em tempo real',
]);
?>

<!-- ------------------------------------------------- contexto de análise -->
<section class="aq-context" aria-label="Contexto de análise">
  <div class="aq-context__label">
    <?php aq_the_icon('filter'); ?>
    <div>
      <strong>Contexto de análise</strong>
      <span data-context-note>Dados consolidados do sistema</span>
    </div>
  </div>

  <?php echo aq_select(['id' => 'filtro-empresa', 'label' => 'Empresa', 'options' => ['all' => 'Todas as empresas']]); ?>
  <?php echo aq_select(['id' => 'filtro-represa', 'label' => 'Represa', 'options' => ['all' => 'Todas as represas']]); ?>

  <p class="aq-context__note">
    <?php aq_the_icon('info'); ?>
    <span>A seleção da empresa determina as represas disponíveis.</span>
  </p>

  <span class="aq-context__spacer"></span>

  <p class="aq-context__note">
    <?php aq_the_icon('refresh'); ?>
    <span>Contexto atualizado <span data-context-updated>há 2 min</span></span>
  </p>
</section>

<!-- ============================ MODO: TODAS AS REPRESAS ================== -->
<div data-view="all" hidden>

  <div class="aq-grid aq-grid--6" style="margin-bottom:var(--aq-content-gap)">
    <?php
    echo aq_kpi(['id' => 'all.reservoirs', 'label' => 'Represas monitoradas', 'icon' => 'gate', 'badge' => true]);
    echo aq_kpi(['id' => 'all.storage', 'label' => 'Volume total armazenado', 'icon' => 'box', 'unit' => 'hm³']);
    echo aq_kpi(['id' => 'all.level', 'label' => 'Nível médio', 'icon' => 'waves', 'unit' => '%']);
    echo aq_kpi(['id' => 'all.flow', 'label' => 'Vazão total', 'icon' => 'arrow-down-circle', 'unit' => 'm³/s']);
    echo aq_kpi(['id' => 'all.ph', 'label' => 'pH médio', 'icon' => 'droplet', 'badge' => true]);
    echo aq_kpi(['id' => 'all.operation', 'label' => 'Situação operacional', 'icon' => 'shield-check', 'tone' => 'success']);
    ?>
  </div>

  <div class="aq-grid aq-grid--3" style="margin-bottom:var(--aq-content-gap)">

    <article class="aq-card">
      <?php echo aq_card_head([
          'title' => 'Comparativo entre represas',
          'tip'   => 'Capacidade ocupada de cada represa, classificada pela faixa operacional.',
      ]); ?>
      <p class="aq-card__sub" style="margin-bottom:14px">Capacidade ocupada (%)</p>
      <div data-content="comparison" hidden>
        <div data-comparison-bars></div>
        <?php echo aq_legend([
            ['label' => 'Normal até 80%', 'color' => '#16a34a', 'style' => 'square'],
            ['label' => 'Atenção: 80%–90%', 'color' => '#f59e0b', 'style' => 'square'],
            ['label' => 'Crítico: acima de 90%', 'color' => '#ef4444', 'style' => 'square'],
        ]); ?>
      </div>
      <?php echo aq_states('comparison'); ?>
    </article>

    <article class="aq-card">
      <?php echo aq_card_head([
          'title' => 'Vazão consolidada — últimos 7 dias',
          'tip'   => 'Somatório diário da vazão de todas as represas do contexto.',
      ]); ?>
      <div data-content="flow-all" hidden>
        <?php echo aq_chart(['id' => 'grafico-vazao-consolidada', 'size' => 'md', 'axis' => 'Somatório diário (m³/s)', 'desc' => 'Vazão total consolidada dos últimos sete dias.']); ?>
        <?php echo aq_legend([['label' => 'Vazão total (m³/s)', 'color' => '#0b5bea']]); ?>
      </div>
      <?php echo aq_states('flow-all'); ?>
    </article>

    <div style="display:flex;flex-direction:column;gap:var(--aq-content-gap);min-width:0">
      <article class="aq-card">
        <?php echo aq_card_head(['title' => 'Situação geral', 'tip' => 'Distribuição das represas por faixa operacional.']); ?>
        <div data-content="donut" hidden style="display:flex;align-items:center;gap:18px">
          <div style="width:150px;flex:none">
            <?php echo aq_chart(['id' => 'grafico-situacao', 'size' => 'sm', 'desc' => 'Distribuição das represas por situação.']); ?>
          </div>
          <ul style="flex:1 1 auto;display:flex;flex-direction:column;gap:9px;font-size:.86rem" data-donut-legend></ul>
        </div>
        <?php echo aq_states('donut'); ?>
      </article>

      <article class="aq-card">
        <p style="text-align:center;font-weight:700;margin-bottom:12px"><span data-field="all.alerts.total">—</span> alertas ativos</p>
        <div style="display:flex;justify-content:center;gap:34px" data-alert-counts></div>
      </article>
    </div>
  </div>

  <div class="aq-grid aq-grid--2-1">
    <article class="aq-card">
      <?php echo aq_card_head([
          'title' => 'Resumo de todas as represas',
          'tip'   => 'Comparação lado a lado dos indicadores de cada represa.',
      ]); ?>
      <div data-content="summary" hidden>
        <?php echo aq_table_open('Resumo de todas as represas'); ?>
        <table class="aq-table aq-table--bordered aq-table--tight">
          <thead>
            <tr>
              <th scope="col">Represa</th>
              <th scope="col" class="is-num">Nível (%)</th>
              <th scope="col" class="is-num">Volume (hm³)</th>
              <th scope="col" class="is-num">Vazão (m³/s)</th>
              <th scope="col" class="is-num">pH</th>
              <th scope="col" class="is-num">Precipitação 24h (mm)</th>
              <th scope="col" class="is-num">Duração estimada</th>
              <th scope="col">Situação</th>
            </tr>
          </thead>
          <tbody data-summary-rows></tbody>
        </table>
        <?php echo aq_table_close(); ?>
      </div>
      <?php echo aq_states('summary'); ?>
    </article>

    <div style="display:flex;flex-direction:column;gap:var(--aq-content-gap);min-width:0">
      <article class="aq-card">
        <?php echo aq_card_head(['title' => 'Localização das represas', 'tip' => 'Coordenadas demonstrativas — serão substituídas pelo banco de dados.']); ?>
        <div class="aq-map aq-map--sm" id="mapa-visao-geral">
          <div class="aq-map__fallback" data-map-fallback="mapa-visao-geral" hidden>
            <span class="aq-state__icon" aria-hidden="true"><?php aq_the_icon('map'); ?></span>
            <p class="aq-state__title">Mapa indisponível</p>
            <p class="aq-state__text">Não foi possível carregar o mapa. Verifique a conexão com a internet.</p>
          </div>
        </div>
      </article>

      <article class="aq-card">
        <?php echo aq_card_head(['title' => 'Alertas prioritários', 'tip' => 'Alertas ativos com maior severidade.']); ?>
        <div class="aq-list" data-priority-alerts></div>
        <p style="margin-top:12px">
          <a class="aq-card__link" href="alertas.php">Ver todos os alertas <?php aq_the_icon('arrow-right'); ?></a>
        </p>
      </article>
    </div>
  </div>
</div>

<!-- ========================= MODO: REPRESA SELECIONADA =================== -->
<div data-view="single" hidden>

  <div class="aq-grid aq-grid--7" style="margin-bottom:var(--aq-content-gap)">
    <?php
    echo aq_kpi(['id' => 'one.level', 'label' => 'Nível do reservatório', 'icon' => 'waves', 'unit' => '%', 'badge' => true]);
    echo aq_kpi(['id' => 'one.storage', 'label' => 'Volume armazenado', 'icon' => 'box', 'unit' => 'hm³', 'ring' => true]);
    echo aq_kpi(['id' => 'one.flow', 'label' => 'Vazão atual', 'icon' => 'arrow-down-circle', 'unit' => 'm³/s', 'badge' => true]);
    echo aq_kpi(['id' => 'one.ph', 'label' => 'pH da água', 'icon' => 'droplet', 'badge' => true]);
    echo aq_kpi(['id' => 'one.rain', 'label' => 'Precipitação (24h)', 'icon' => 'cloud-rain', 'unit' => 'mm', 'badge' => true]);
    echo aq_kpi(['id' => 'one.duration', 'label' => 'Previsão de duração', 'icon' => 'clock', 'unit' => 'dias']);
    echo aq_kpi(['id' => 'one.operation', 'label' => 'Situação operacional', 'icon' => 'shield-check', 'tone' => 'success']);
    ?>
  </div>

  <div class="aq-grid aq-grid--3" style="margin-bottom:var(--aq-content-gap)">
    <article class="aq-card">
      <?php echo aq_card_head([
          'title'   => 'Nível do reservatório',
          'icon'    => 'chart-bars',
          'tip'     => 'Cota observada no período, comparada com a cota de vertimento.',
          'actions' => '<span class="aq-card__sub">Período: 7 dias</span>',
      ]); ?>
      <div data-content="level-chart" hidden>
        <?php echo aq_chart(['id' => 'grafico-nivel', 'size' => 'md', 'axis' => 'Cota (m)', 'desc' => 'Histórico da cota do reservatório.']); ?>
        <p class="aq-card__sub" data-field="one.spill"></p>
        <?php echo aq_legend([
            ['label' => 'Nível observado', 'color' => '#0b5bea'],
            ['label' => 'Cota de vertimento', 'color' => '#0b5bea', 'style' => 'dashed'],
        ]); ?>
      </div>
      <?php echo aq_states('level-chart'); ?>
    </article>

    <article class="aq-card">
      <?php echo aq_card_head([
          'title' => 'Comparativo de vazão',
          'tip'   => 'Vazão do dia atual comparada com a média dos dias anteriores.',
          'actions' => '<span class="aq-card__sub">Período: 7 dias</span>',
      ]); ?>
      <div data-content="flow-chart" hidden>
        <?php echo aq_chart(['id' => 'grafico-vazao-comparativo', 'size' => 'md', 'axis' => 'Vazão (m³/s)', 'desc' => 'Comparativo de vazão entre o dia atual e os dias anteriores.']); ?>
        <?php echo aq_legend([
            ['label' => 'Dia atual', 'color' => '#0b5bea'],
            ['label' => 'Dias anteriores (média)', 'color' => '#7f90af', 'style' => 'dashed'],
        ]); ?>
      </div>
      <?php echo aq_states('flow-chart'); ?>
    </article>

    <article class="aq-card">
      <?php echo aq_card_head([
          'title'   => 'Localização da represa',
          'actions' => '<a class="aq-card__link" href="mapas.php">Ver no mapa ' . aq_icon('external-link') . '</a>',
      ]); ?>
      <div class="aq-map aq-map--sm" id="mapa-represa" style="flex:1 1 auto">
        <div class="aq-map__fallback" data-map-fallback="mapa-represa" hidden>
          <span class="aq-state__icon" aria-hidden="true"><?php aq_the_icon('map'); ?></span>
          <p class="aq-state__title">Mapa indisponível</p>
          <p class="aq-state__text">Não foi possível carregar o mapa.</p>
        </div>
      </div>
    </article>
  </div>

  <div class="aq-grid aq-grid--1-2">
    <article class="aq-card">
      <?php echo aq_card_head([
          'title'   => 'Alertas recentes',
          'icon'    => 'bell',
          'actions' => '<a class="aq-card__link" href="alertas.php">Ver todos</a>',
      ]); ?>
      <div class="aq-list" data-recent-alerts></div>
      <p style="margin-top:14px">
        <a class="aq-card__link" href="alertas.php">Ver todos os alertas <?php aq_the_icon('arrow-right'); ?></a>
      </p>
    </article>

    <article class="aq-card">
      <?php echo aq_card_head([
          'title'   => 'Relatórios e status',
          'icon'    => 'file-text',
          'actions' => '<a class="aq-card__link" href="relatorios.php">Ver todos os relatórios</a>',
      ]); ?>
      <?php echo aq_table_open('Relatórios recentes'); ?>
      <table class="aq-table">
        <thead>
          <tr>
            <th scope="col">Relatório</th>
            <th scope="col">Represa</th>
            <th scope="col">Período</th>
            <th scope="col">Gerado em</th>
            <th scope="col">Status</th>
            <th scope="col"><span class="aq-visually-hidden">Baixar</span></th>
          </tr>
        </thead>
        <tbody data-reports-rows></tbody>
      </table>
      <?php echo aq_table_close(); ?>
    </article>
  </div>
</div>

<?php
aq_page_end([
    'scripts'   => ['pages/overview.js'],
    'needs_map' => true,
]);
