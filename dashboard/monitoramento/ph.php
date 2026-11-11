<?php
/** Aquapulse — Monitoramento / pH. */
/*
 * Qualidade da água de UMA represa pelo pH (0 = ácido, 7 = neutro, 14 = alcalino),
 * com a faixa ideal 6,5–8,5. Dados de GET api/v1/monitoring/ph.php, carregados
 * por assets/js/pages/ph.js (com monitor-page.js).
 */

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
  echo aq_kpi(['id' => 'ph', 'label' => 'pH atual', 'icon' => 'droplet', 'tip' => 'Leitura mais recente da sonda de pH.']);
  echo aq_kpi(['id' => 'min', 'label' => 'Mínimo', 'icon' => 'arrow-down-circle', 'tip' => 'Menor leitura das últimas 24 horas.']);
  echo aq_kpi(['id' => 'max', 'label' => 'Máximo', 'icon' => 'arrow-up-circle', 'tip' => 'Maior leitura das últimas 24 horas.']);
  echo aq_kpi(['id' => 'condition', 'label' => 'Condição', 'icon' => 'check-circle', 'tip' => 'Classificação em relação à faixa ideal cadastrada.']); // "Ideal" ou "Atenção"
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Variação do pH', 'tip' => 'Média por hora (24 horas) ou por dia (7 dias), com a faixa ideal destacada.']); ?>
    <div data-content="variation" hidden>
      <?php echo aq_chart(['id' => 'grafico-ph', 'size' => 'lg', 'desc' => 'Variação do pH ao longo do período com faixa ideal.']); // data.variation + faixa ideal_min/ideal_max ?>
      <?php echo aq_legend([
          ['label' => 'pH observado', 'color' => '#0b5bea'],
          ['label' => 'Faixa ideal', 'color' => '#16a34a', 'style' => 'dashed'],
      ]); ?>
    </div>
    <?php echo aq_states('variation'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Escala de pH', 'tip' => 'Posição da leitura atual na escala de 0 a 14.']); ?>
    <div data-content="scale" hidden style="text-align:center">
      <p style="font-size:.82rem;color:var(--aq-text-secondary);margin-bottom:-6px"><strong style="color:var(--aq-text);font-size:1rem">7</strong><br>Neutro</p> <?php /* marca do meio da escala */ ?>
      <div style="max-width:330px;margin:0 auto;position:relative">
        <?php echo aq_chart(['id' => 'escala-ph', 'size' => 'sm', 'desc' => 'Escala de pH de 0 a 14 com marcador na leitura atual.']); // meia rosca colorida de 0 a 14 ?>
        <!-- ponteiro desenhado em CSS sobre o medidor -->
        <?php /* o JS gira o ponteiro (transform: rotate) conforme o pH; transform-origin na base faz ele girar como agulha */ ?>
        <div data-ph-needle
             style="position:absolute;left:50%;bottom:34%;width:3px;height:62px;background:var(--aq-text);border-radius:2px;transform-origin:bottom center;transition:transform 600ms ease"></div>
      </div>
      <div style="display:flex;justify-content:space-between;max-width:330px;margin:-28px auto 0;font-size:.8rem"> <?php /* rótulos das pontas da escala */ ?>
        <span><strong>0</strong><br><span style="color:var(--aq-danger)">Ácido</span></span>
        <span><strong>14</strong><br><span style="color:var(--aq-info)">Alcalino</span></span>
      </div>
      <p style="font-size:2rem;font-weight:800;margin-top:6px" data-field="scale.value">—</p> <?php /* pH atual em destaque */ ?>
      <p style="font-weight:700" data-field="scale.label"></p> <?php /* "Ideal" ou "Atenção"; a cor é aplicada pelo JS conforme o status */ ?>
      <p style="margin-top:12px" data-field="scale.range"></p>
    </div>
    <?php echo aq_states('scale'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--5-4-7">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Média diária — últimos 7 dias', 'tip' => 'Média das leituras de cada dia.']); ?>
    <div data-content="daily" hidden>
    <?php echo aq_chart(['id' => 'grafico-ph-diario', 'size' => 'md', 'axis' => 'pH', 'desc' => 'Média diária de pH nos últimos sete dias.']); // data.daily ?>
    <?php echo aq_legend([
        ['label' => 'pH médio diário', 'color' => '#0b5bea', 'style' => 'square'],
        ['label' => 'Faixa ideal', 'color' => '#16a34a', 'style' => 'dashed'],
    ]); ?>
    </div>
    <?php echo aq_states('daily'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Equipamentos de qualidade', 'tip' => 'Sonda de pH e termômetro da água desta represa, com a última leitura de cada um.']); ?>
    <div class="aq-list" data-sensors></div> <?php /* data.sensors */ ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Últimas leituras', 'tip' => 'Leituras mais recentes recebidas, com o equipamento que enviou.']); ?>
    <?php echo aq_table_open('Últimas leituras de pH'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr>
          <th scope="col">Horário</th>
          <th scope="col" class="is-num">pH</th>
          <th scope="col" class="is-num">Temperatura (°C)</th>
          <th scope="col">Equipamento</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody data-readings></tbody> <?php /* data.readings */ ?>
    </table>
    <?php echo aq_table_close(); ?>
  </article>
</div>

<div class="aq-note">
  <?php aq_the_icon('info'); ?>
  <span><strong data-field="scale.range">Faixa ideal</strong> — vem dos limites operacionais cadastrados (Configurações › Limites) e é a mesma usada para abrir alertas.</span>
</div>


<?php aq_page_end(['scripts' => ['pages/ph.js'], 'monitor' => true]); // lógica desta tela