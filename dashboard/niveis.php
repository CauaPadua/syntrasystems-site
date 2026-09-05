<?php
/**
 * Aquapulse — Níveis (visão histórica ampla).
 *
 * Reutiliza EXATAMENTE os mesmos dados e o mesmo endpoint da tela detalhada
 * de Nível do reservatório (api/v1/monitoring/level.php). Não existe uma
 * segunda fonte de dados para nível no sistema.
 */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

aq_page_start([
    'route'    => 'levels',
    'title'    => 'Níveis',
    'subtitle' => 'Analise a evolução e os limites do reservatório',
]);
?>

<section class="aq-context" aria-label="Contexto de análise">
  <?php echo aq_select(['id' => 'filtro-empresa', 'label' => 'Empresa', 'options' => ['all' => 'Todas as empresas']]); ?>
  <?php echo aq_select(['id' => 'filtro-represa', 'label' => 'Represa', 'options' => []]); ?>
  <?php echo aq_select(['id' => 'filtro-periodo', 'label' => 'Período', 'options' => [
      '7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', '90d' => 'Últimos 90 dias', '12m' => 'Últimos 12 meses',
  ], 'value' => '30d']); ?>
  <span class="aq-context__spacer"></span>
</section>

<div class="aq-grid aq-grid--6">
  <?php
  echo aq_kpi(['id' => 'cota', 'label' => 'Nível atual', 'icon' => 'waves', 'unit' => 'm', 'badge' => true, 'tip' => 'Cota atual do reservatório em metros.']);
  echo aq_kpi(['id' => 'used', 'label' => 'Capacidade utilizada', 'icon' => 'box', 'unit' => '%', 'ring' => true, 'tip' => 'Percentual da capacidade total em uso.']);
  echo aq_kpi(['id' => 'variation', 'label' => 'Variação em 24h', 'icon' => 'chart-up', 'unit' => 'm', 'tip' => 'Diferença de cota nas últimas 24 horas.']);
  echo aq_kpi(['id' => 'spill', 'label' => 'Cota de vertimento', 'icon' => 'arrow-down-circle', 'unit' => 'm', 'tip' => 'Cota a partir da qual o reservatório verte.']);
  echo aq_kpi(['id' => 'margin', 'label' => 'Margem disponível', 'icon' => 'ruler', 'unit' => 'm', 'tip' => 'Distância entre a cota atual e a cota de vertimento.']);
  echo aq_kpi(['id' => 'status', 'label' => 'Status operacional', 'icon' => 'shield-check', 'tone' => 'warning', 'tip' => 'Classificação atual conforme as faixas operacionais.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head([
        'title'   => 'Histórico do nível do reservatório',
        'tip'     => 'Cota observada com as linhas de atenção, alerta e vertimento.',
        'actions' => '<button class="aq-btn aq-btn--ghost aq-btn--sm" type="button" data-toggle-table>'
                     . aq_icon('table') . '<span>Ver tabela</span></button>',
    ]); ?>
    <?php echo aq_legend([
        ['label' => 'Nível observado', 'color' => '#0b5bea'],
        ['label' => 'Cota de atenção', 'color' => '#f59e0b', 'style' => 'dashed'],
        ['label' => 'Cota crítica', 'color' => '#ef4444', 'style' => 'dashed'],
        ['label' => 'Cota de vertimento', 'color' => '#38bdf8', 'style' => 'dashed'],
    ], true); ?>

    <div data-content="history" hidden>
      <?php echo aq_chart(['id' => 'grafico-niveis', 'size' => 'xl', 'axis' => 'Cota (m)', 'desc' => 'Histórico da cota do reservatório com todos os limites configurados.']); ?>

      <!-- tabela equivalente ao gráfico (acessibilidade + alternância) -->
      <div data-chart-table hidden style="margin-top:14px">
        <?php echo aq_table_open('Tabela equivalente ao gráfico de níveis'); ?>
        <table class="aq-table">
          <thead><tr><th scope="col">Data</th><th scope="col" class="is-num">Nível (%)</th></tr></thead>
          <tbody data-chart-rows></tbody>
        </table>
        <?php echo aq_table_close(); ?>
      </div>

      <div style="display:flex;gap:6px;justify-content:center;margin-top:12px" data-quick-periods>
        <button class="aq-chip" type="button" data-period="7d">7 dias</button>
        <button class="aq-chip is-active" type="button" data-period="30d">30 dias</button>
        <button class="aq-chip" type="button" data-period="90d">90 dias</button>
        <button class="aq-chip" type="button" data-period="12m">12 meses</button>
      </div>
    </div>
    <?php echo aq_states('history'); ?>
  </article>

  <div style="display:flex;flex-direction:column;gap:var(--aq-content-gap);min-width:0">
    <article class="aq-card">
      <?php echo aq_card_head(['title' => 'Capacidade do reservatório', 'tip' => 'Faixas operacionais e posição do nível atual.']); ?>
      <div style="display:flex;gap:18px;align-items:center">
        <!-- coluna de faixas desenhada em CSS; as proporções são as mesmas
             regras de StatusRules (atenção 80%, crítico 90%) -->
        <div style="flex:none;width:92px;position:relative;height:250px;border-radius:10px;overflow:hidden;display:flex;flex-direction:column">
          <div style="flex:10;background:var(--aq-danger)"></div>
          <div style="flex:10;background:var(--aq-warning)"></div>
          <div style="flex:80;background:var(--aq-primary)"></div>
          <div data-level-marker
               style="position:absolute;left:-4px;right:-4px;height:2.5px;background:var(--aq-text);transition:bottom 600ms ease"></div>
        </div>
        <ul style="flex:1 1 auto;display:flex;flex-direction:column;gap:11px;font-size:.84rem" data-level-bands></ul>
      </div>
    </article>

    <article class="aq-card">
      <?php echo aq_card_head(['title' => 'Tendência para 7 dias', 'tip' => 'Projeção demonstrativa do nível.']); ?>
      <p style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        <span class="aq-kpi__icon aq-kpi__icon--success" aria-hidden="true"><?php aq_the_icon('chart-up'); ?></span>
        <span><strong style="font-size:1.2rem" data-field="trend.value">—</strong>
        <span style="display:block;font-size:.82rem;color:var(--aq-text-secondary)">Variação projetada</span></span>
      </p>
      <?php echo aq_chart(['id' => 'grafico-tendencia', 'size' => 'sm', 'axis' => 'Cota (m)', 'desc' => 'Projeção do nível para os próximos sete dias.']); ?>
    </article>
  </div>
</div>

<div class="aq-grid aq-grid--7-6-4">
  <article class="aq-card">
    <?php echo aq_card_head([
        'title'   => 'Registros de nível',
        'actions' => '<a class="aq-card__link" href="monitoramento/nivel.php">Ver todos</a>',
    ]); ?>
    <?php echo aq_table_open('Registros de nível'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr>
          <th scope="col">Data e hora</th>
          <th scope="col" class="is-num">Cota (m)</th>
          <th scope="col" class="is-num">Variação (m)</th>
          <th scope="col" class="is-num">Capacidade (%)</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody data-records></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <p class="aq-card__sub" style="margin-top:12px">Atualização automática a cada 5 minutos</p>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Comparativo mensal', 'tip' => 'Cota máxima, média e mínima por mês.']); ?>
    <?php echo aq_chart(['id' => 'grafico-mensal', 'size' => 'md', 'axis' => 'Cota (m)', 'desc' => 'Comparativo mensal de cota máxima, média, mínima e atual.']); ?>
    <?php echo aq_legend([
        ['label' => 'Máxima', 'color' => '#38bdf8', 'style' => 'dashed'],
        ['label' => 'Média', 'color' => '#16a34a', 'style' => 'dashed'],
        ['label' => 'Mínima', 'color' => '#fb923c', 'style' => 'dashed'],
        ['label' => 'Atual', 'color' => '#0b5bea'],
    ]); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Limites configurados', 'tip' => 'Cotas definidas em Configurações › Limites e alertas.']); ?>
    <div data-limits></div>
    <p style="margin-top:14px">
      <a class="aq-card__link" href="configuracoes.php"><?php aq_the_icon('edit'); ?> Editar limites</a>
    </p>
  </article>
</div>

<?php aq_page_end(['scripts' => ['pages/levels.js']]);
