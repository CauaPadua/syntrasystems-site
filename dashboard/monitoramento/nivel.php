<?php
/** Aquapulse — Monitoramento / Nível do reservatório. */

declare(strict_types=1);

define('AQ_DEPTH', 2);
require dirname(__DIR__) . '/includes/page.php';

aq_page_start([
    'route'    => 'monitoring.level',
    'title'    => 'Nível do reservatório',
    'subtitle' => 'Acompanhe a cota, a capacidade e as variações do reservatório',
]);

echo aq_monitor_bar(['periods' => ['7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', '90d' => 'Últimos 90 dias']]);
?>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'level', 'label' => 'Nível atual', 'icon' => 'waves', 'unit' => '%', 'tip' => 'Percentual da capacidade total ocupado no momento.', 'badge' => true]);
  echo aq_kpi(['id' => 'cota', 'label' => 'Cota atual', 'icon' => 'ruler', 'unit' => 'm', 'tip' => 'Altura da lâmina de água em metros acima do nível do mar.']);
  echo aq_kpi(['id' => 'variation', 'label' => 'Variação diária', 'icon' => 'chart-up', 'unit' => 'm', 'tip' => 'Diferença de cota nas últimas 24 horas.']);
  echo aq_kpi(['id' => 'available', 'label' => 'Capacidade disponível', 'icon' => 'droplet', 'unit' => '%', 'tip' => 'Percentual ainda livre no reservatório.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Histórico do nível', 'tip' => 'Nível observado com as linhas de atenção e crítico marcadas no gráfico.']); ?>
    <div data-content="history" hidden>
      <?php echo aq_chart(['id' => 'grafico-historico-nivel', 'size' => 'lg', 'axis' => '% da capacidade', 'desc' => 'Histórico do nível do reservatório com limites operacionais.']); ?>
      <?php echo aq_legend([
          ['label' => 'Nível observado (%)', 'color' => '#0b5bea'],
          ['label' => 'Cota de atenção', 'color' => '#f59e0b', 'style' => 'dashed'],
          ['label' => 'Cota crítica', 'color' => '#ef4444', 'style' => 'dashed'],
      ]); ?>
    </div>
    <?php echo aq_states('history'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Capacidade do reservatório', 'tip' => 'Representação visual do nível dentro das faixas operacionais.']); ?>
    <div data-content="capacity" hidden style="display:flex;gap:14px;align-items:center">
      <!-- escala decorativa: o valor numérico já é anunciado dentro da coluna -->
      <ul class="aq-scale" aria-hidden="true">
        <?php for ($p = 100; $p >= 0; $p -= 10): ?>
          <li><?php echo $p; ?>%</li>
        <?php endfor; ?>
      </ul>

      <!-- coluna de capacidade desenhada em HTML/CSS, não é imagem -->
      <div style="flex:none;width:104px">
        <div style="position:relative;height:250px;border:2px solid var(--aq-border);border-radius:12px;background:var(--aq-bg);overflow:hidden">
          <div data-capacity-fill
               style="position:absolute;left:0;right:0;bottom:0;background:linear-gradient(180deg,#3b82f6,#0b5bea);transition:height 600ms ease"></div>
          <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.25)">
            <strong style="font-size:1.5rem" data-field="capacity.level">—</strong>
            <span style="font-size:.78rem">Nível atual</span>
          </div>
        </div>
      </div>
      <ul style="flex:1 1 auto;display:flex;flex-direction:column;gap:14px" data-capacity-bands></ul>
    </div>
    <p class="aq-card__sub" style="text-align:center;margin-top:12px" data-field="capacity.total"></p>
    <?php echo aq_states('capacity'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--5-4-7">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Tendência para os próximos 7 dias', 'tip' => 'Projeção demonstrativa baseada na variação recente.']); ?>
    <?php echo aq_chart(['id' => 'grafico-tendencia-nivel', 'size' => 'md', 'axis' => '% da capacidade', 'desc' => 'Tendência projetada do nível para os próximos sete dias.']); ?>
    <?php echo aq_legend([
        ['label' => 'Observado', 'color' => '#0b5bea'],
        ['label' => 'Previsão', 'color' => '#6ea8fe', 'style' => 'dashed'],
    ]); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Faixas operacionais', 'tip' => 'Regras de classificação usadas em todo o sistema.']); ?>
    <div class="aq-list" data-bands></div>
    <p class="aq-card__sub" style="margin-top:12px">Faixas definidas conforme regras operacionais.</p>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head([
        'title'   => 'Últimas leituras',
        'actions' => '<a class="aq-card__link" href="../niveis.php">Ver histórico completo ' . aq_icon('arrow-right') . '</a>',
    ]); ?>
    <?php echo aq_table_open('Últimas leituras de nível'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr>
          <th scope="col">Horário</th>
          <th scope="col" class="is-num">Cota (m)</th>
          <th scope="col" class="is-num">Nível (%)</th>
          <th scope="col" class="is-num">Variação (24h)</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody data-readings></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <p class="aq-card__sub" style="margin-top:12px">Atualização automática a cada 5 minutos</p>
  </article>
</div>

<?php aq_page_end(['scripts' => ['pages/level.js'], 'monitor' => true]);
