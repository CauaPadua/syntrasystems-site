<?php
/** Aquapulse — Monitoramento / Volume de vazão. */
/*
 * Tela detalhada de vazão de UMA represa: entrada (afluência) e saída
 * (defluência) de água. Dados de GET api/v1/monitoring/flow.php, carregados por
 * assets/js/pages/flow.js com apoio de monitor-page.js (seletor de represa e período).
 *
 * As telas desta pasta seguem o mesmo esqueleto:
 *   AQ_DEPTH 2 -> page.php -> aq_page_start -> aq_monitor_bar -> KPIs -> cards -> aq_page_end(monitor)
 */

declare(strict_types=1);

define('AQ_DEPTH', 2);                                                    // dashboard/monitoramento/: a raiz do projeto está dois níveis acima
require dirname(__DIR__) . '/includes/page.php';                          // dirname(__DIR__) = pasta dashboard/

aq_page_start([
    'route'    => 'monitoring.flow',                                      // abre o submenu Monitoramento e destaca "Volume de vazão"
    'title'    => 'Volume de vazão',
    'subtitle' => 'Acompanhe a entrada e a saída de água',
]);

echo aq_monitor_bar(['periods' => ['24h' => 'Últimas 24 horas', '7d' => 'Últimos 7 dias']]); // barra com represa, código, telemetria e período (só 24h e 7d nesta tela)
?>

<div class="aq-grid aq-grid--4"> <?php /* quatro cards preenchidos com data.kpis */ ?>
  <?php
  echo aq_kpi(['id' => 'flow', 'label' => 'Afluência atual', 'icon' => 'waves', 'unit' => 'm³/s', 'tip' => 'Leitura mais recente do equipamento de afluência.']);
  echo aq_kpi(['id' => 'inflow', 'label' => 'Afluência média', 'icon' => 'arrow-down-circle', 'unit' => 'm³/s', 'tip' => 'Média da água que entrou no reservatório nas últimas 24 horas.']);
  echo aq_kpi(['id' => 'outflow', 'label' => 'Defluência média', 'icon' => 'arrow-up-circle', 'unit' => 'm³/s', 'tip' => 'Média da água que saiu do reservatório nas últimas 24 horas.']);
  echo aq_kpi(['id' => 'balance', 'label' => 'Saldo hídrico', 'icon' => 'droplet', 'unit' => 'm³/s', 'tip' => 'Afluência média menos defluência média das últimas 24 horas.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Afluência e defluência', 'tip' => 'Média por hora (24 horas) ou por dia (7 dias) das leituras recebidas.']); ?>
    <div data-content="realtime" hidden>
      <?php echo aq_chart(['id' => 'grafico-vazao', 'size' => 'lg', 'axis' => 'm³/s', 'desc' => 'Afluência e defluência ao longo do período.']); // duas linhas: data.realtime.inflow e .outflow ?>
      <?php echo aq_legend([
          ['label' => 'Afluência (entrada)', 'color' => '#0b5bea'],
          ['label' => 'Defluência (saída)', 'color' => '#6ea8fe'],
      ]); ?>
    </div>
    <?php echo aq_states('realtime'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Condição da vazão', 'tip' => 'O medidor posiciona a afluência atual entre a menor e a maior do período; o texto compara entrada e saída nas últimas 24 horas.']); ?>
    <div data-content="condition" hidden style="text-align:center">
      <div style="max-width:300px;margin:0 auto">
        <?php echo aq_chart(['id' => 'medidor-vazao', 'size' => 'sm', 'desc' => 'Medidor semicircular da condição da vazão.']); // meia rosca que funciona como medidor ?>
      </div>
      <p style="font-size:1.35rem;font-weight:800;margin-top:-46px" data-field="condition.label">—</p> <?php /* margem negativa sobe o texto para dentro do medidor */ ?>
      <p class="aq-card__sub" style="margin-top:34px" data-field="condition.text"></p>
      <p class="aq-card__sub" style="margin-top:14px" data-field="condition.range"></p> <?php /* menor e maior afluência do período */ ?>
    </div>
    <?php echo aq_states('condition'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--5-4-7"> <?php /* três cards com larguras 5:4:7 */ ?>
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Média diária — últimos 7 dias', 'tip' => 'Média de afluência e defluência por dia.']); ?>
    <div data-content="daily" hidden>
    <?php echo aq_chart(['id' => 'grafico-media-diaria', 'size' => 'md', 'axis' => 'm³/s', 'desc' => 'Média diária de afluência e defluência.']); // barras com data.daily ?>
    <?php echo aq_legend([
        ['label' => 'Afluência (média)', 'color' => '#0b5bea', 'style' => 'square'],
        ['label' => 'Defluência (média)', 'color' => '#b6d3fe', 'style' => 'square'],
    ]); ?>
    </div>
    <?php echo aq_states('daily'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Equipamentos de vazão', 'tip' => 'Um equipamento fica online enquanto envia leituras; sem leitura recente, aparece sem comunicação.']); ?>
    <div class="aq-list" data-sensors></div> <?php /* data.sensors: código, nome, situação e última leitura */ ?>
    <p style="margin-top:12px"><a class="aq-card__link" href="../equipamentos.php">Ver todos os equipamentos <?php aq_the_icon('arrow-right'); ?></a></p>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head([
        'title'   => 'Últimas leituras',
        'tip'     => 'Leituras mais recentes, agrupadas por minuto.',
    ]); ?>
    <?php echo aq_table_open('Últimas leituras de vazão'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr>
          <th scope="col">Horário</th>
          <th scope="col" class="is-num">Afluência (m³/s)</th>
          <th scope="col" class="is-num">Defluência (m³/s)</th>
          <th scope="col" class="is-num">Saldo (m³/s)</th>
        </tr>
      </thead>
      <tbody data-readings></tbody> <?php /* 5 leituras mais recentes (data.readings) */ ?>
    </table>
    <?php echo aq_table_close(); ?>
    <p class="aq-card__sub" style="margin-top:12px">A tela busca novas leituras a cada 15 segundos.</p>

  </article>
</div>

<?php aq_page_end(['scripts' => ['pages/flow.js'], 'monitor' => true]); // monitor => true carrega monitor-page.js antes de flow.js