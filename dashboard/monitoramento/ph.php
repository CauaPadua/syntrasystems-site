<?php
/** Aquapulse — Monitoramento / pH. */

declare(strict_types=1);

define('AQ_DEPTH', 2);
require dirname(__DIR__) . '/includes/page.php';

aq_page_start([
    'route'    => 'monitoring.ph',
    'title'    => 'Monitoramento de pH',
    'subtitle' => 'Avalie a qualidade e o equilíbrio químico da água',
]);

echo aq_monitor_bar(['periods' => ['24h' => 'Últimas 24 horas', '7d' => 'Últimos 7 dias']]);
?>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'ph', 'label' => 'pH atual', 'icon' => 'droplet', 'tip' => 'Última leitura registrada pelos sensores de qualidade.']);
  echo aq_kpi(['id' => 'min', 'label' => 'Mínimo', 'icon' => 'arrow-down-circle', 'tip' => 'Menor valor registrado no período.']);
  echo aq_kpi(['id' => 'max', 'label' => 'Máximo', 'icon' => 'arrow-up-circle', 'tip' => 'Maior valor registrado no período.']);
  echo aq_kpi(['id' => 'condition', 'label' => 'Condição', 'icon' => 'check-circle', 'tone' => 'success', 'tip' => 'Classificação em relação à faixa ideal configurada.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Variação do pH — últimas 24 horas', 'tip' => 'Leituras do período com a faixa ideal destacada.']); ?>
    <div data-content="variation" hidden>
      <?php echo aq_chart(['id' => 'grafico-ph', 'size' => 'lg', 'desc' => 'Variação do pH ao longo do período com faixa ideal.']); ?>
      <?php echo aq_legend([
          ['label' => 'pH observado', 'color' => '#0b5bea'],
          ['label' => 'Faixa ideal (6,5 – 8,5)', 'color' => '#16a34a', 'style' => 'dashed'],
      ]); ?>
    </div>
    <?php echo aq_states('variation'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Escala de pH', 'tip' => 'Posição da leitura atual na escala de 0 a 14.']); ?>
    <div data-content="scale" hidden style="text-align:center">
      <p style="font-size:.82rem;color:var(--aq-text-secondary);margin-bottom:-6px"><strong style="color:var(--aq-text);font-size:1rem">7</strong><br>Neutro</p>
      <div style="max-width:330px;margin:0 auto;position:relative">
        <?php echo aq_chart(['id' => 'escala-ph', 'size' => 'sm', 'desc' => 'Escala de pH de 0 a 14 com marcador na leitura atual.']); ?>
        <!-- ponteiro desenhado em CSS sobre o medidor -->
        <div data-ph-needle
             style="position:absolute;left:50%;bottom:34%;width:3px;height:62px;background:var(--aq-text);border-radius:2px;transform-origin:bottom center;transition:transform 600ms ease"></div>
      </div>
      <div style="display:flex;justify-content:space-between;max-width:330px;margin:-28px auto 0;font-size:.8rem">
        <span><strong>0</strong><br><span style="color:var(--aq-danger)">Ácido</span></span>
        <span><strong>14</strong><br><span style="color:var(--aq-info)">Alcalino</span></span>
      </div>
      <p style="font-size:2rem;font-weight:800;margin-top:6px" data-field="scale.value">—</p>
      <p style="font-weight:700;color:var(--aq-success)" data-field="scale.label"></p>
      <p style="margin-top:12px" data-field="scale.range"></p>
    </div>
    <?php echo aq_states('scale'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--5-4-7">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Média diária — últimos 7 dias', 'tip' => 'Média das leituras de cada dia.']); ?>
    <?php echo aq_chart(['id' => 'grafico-ph-diario', 'size' => 'md', 'axis' => 'pH', 'desc' => 'Média diária de pH nos últimos sete dias.']); ?>
    <?php echo aq_legend([
        ['label' => 'pH médio diário', 'color' => '#0b5bea', 'style' => 'square'],
        ['label' => 'Faixa ideal (6,5 – 8,5)', 'color' => '#16a34a', 'style' => 'dashed'],
    ]); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Pontos de coleta', 'tip' => 'Locais monitorados dentro do reservatório.']); ?>
    <?php echo aq_table_open('Pontos de coleta de pH'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr><th scope="col">Ponto de coleta</th><th scope="col" class="is-num">pH atual</th><th scope="col">Status</th></tr>
      </thead>
      <tbody data-points></tbody>
    </table>
    <?php echo aq_table_close(); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head([
        'title'   => 'Últimas leituras',
        'actions' => '<a class="aq-card__link" href="#">Ver histórico completo ' . aq_icon('arrow-right') . '</a>',
    ]); ?>
    <?php echo aq_table_open('Últimas leituras de pH'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr>
          <th scope="col">Horário</th>
          <th scope="col" class="is-num">pH</th>
          <th scope="col" class="is-num">Temperatura (°C)</th>
          <th scope="col">Ponto</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody data-readings></tbody>
    </table>
    <?php echo aq_table_close(); ?>
  </article>
</div>

<div class="aq-demo-note">
  <?php aq_the_icon('info'); ?>
  <span><strong>Faixa operacional configurada:</strong> 6,5 a 8,5 — definida em Configurações › Limites e alertas.</span>
</div>

<?php aq_page_end(['scripts' => ['pages/ph.js'], 'monitor' => true]);
