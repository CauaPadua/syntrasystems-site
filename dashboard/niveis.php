<?php
/**
 * Aquapulse — Níveis (visão histórica ampla).
 *
 * Reutiliza EXATAMENTE os mesmos dados e o mesmo endpoint da tela detalhada
 * de Nível do reservatório (api/v1/monitoring/level.php). Não existe uma
 * segunda fonte de dados para nível no sistema.
 *
 * Lógica em assets/js/pages/levels.js. Enquanto a tela de monitoramento de
 * nível trabalha em percentual, esta trabalha principalmente em cota (metros),
 * usando os campos history.cota_* devolvidos pela API.
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
  <?php echo aq_select(['id' => 'filtro-represa', 'label' => 'Represa', 'options' => []]); // sem "Todas": a análise de nível exige uma represa ?>
  <?php echo aq_select(['id' => 'filtro-periodo', 'label' => 'Período', 'options' => [
      '7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', '90d' => 'Últimos 90 dias', '12m' => 'Últimos 12 meses',
  ], 'value' => '30d']); // começa em 30 dias ?>
  <span class="aq-context__spacer"></span>
</section>

<div class="aq-grid aq-grid--6"> <?php /* seis cards de KPI em metros e percentual */ ?>
  <?php
  echo aq_kpi(['id' => 'cota', 'label' => 'Nível atual', 'icon' => 'waves', 'unit' => 'm', 'badge' => true, 'tip' => 'Cota atual do reservatório em metros.']);
  echo aq_kpi(['id' => 'used', 'label' => 'Capacidade utilizada', 'icon' => 'box', 'unit' => '%', 'ring' => true, 'tip' => 'Percentual da capacidade total em uso.']);
  echo aq_kpi(['id' => 'variation', 'label' => 'Variação em 24h', 'icon' => 'chart-up', 'unit' => 'm', 'tip' => 'Cota atual menos a cota de 24 horas atrás.']);
  echo aq_kpi(['id' => 'spill', 'label' => 'Cota de vertimento', 'icon' => 'arrow-down-circle', 'unit' => 'm', 'tip' => 'Cota a partir da qual o reservatório verte.']);
  echo aq_kpi(['id' => 'margin', 'label' => 'Margem disponível', 'icon' => 'ruler', 'unit' => 'm', 'tip' => 'Distância entre a cota atual e a cota de vertimento.']); // calculada no JS: cota de vertimento - cota atual
  echo aq_kpi(['id' => 'status', 'label' => 'Status operacional', 'icon' => 'shield-check', 'tone' => 'warning', 'tip' => 'Classificação atual conforme as faixas operacionais.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head([
        'title'   => 'Histórico do nível do reservatório',
        'tip'     => 'Cota média de cada dia (ou mês), com as linhas dos limites.',
        'actions' => '<button class="aq-btn aq-btn--ghost aq-btn--sm" type="button" data-toggle-table>'   // alterna entre gráfico e tabela
                     . aq_icon('table') . '<span>Ver tabela</span></button>',
    ]); ?>
    <?php echo aq_legend([
        ['label' => 'Nível observado', 'color' => '#0b5bea'],
        ['label' => 'Cota de atenção', 'color' => '#f59e0b', 'style' => 'dashed'],
        ['label' => 'Cota crítica', 'color' => '#ef4444', 'style' => 'dashed'],
        ['label' => 'Cota de vertimento', 'color' => '#38bdf8', 'style' => 'dashed'],
    ], true); // true = legenda "plain", sem fundo ?>

    <div data-content="history" hidden>
      <?php echo aq_chart(['id' => 'grafico-niveis', 'size' => 'xl', 'axis' => 'Cota (m)', 'desc' => 'Histórico da cota do reservatório com todos os limites configurados.']); ?>

      <!-- tabela equivalente ao gráfico (acessibilidade + alternância) -->
      <div data-chart-table hidden style="margin-top:14px">
        <?php echo aq_table_open('Tabela equivalente ao gráfico de níveis'); ?>
        <table class="aq-table">
          <thead><tr><th scope="col">Data</th><th scope="col" class="is-num">Cota (m)</th></tr></thead>
          <tbody data-chart-rows></tbody> <?php /* uma linha por ponto do gráfico */ ?>
        </table>
        <?php echo aq_table_close(); ?>
      </div>

      <div style="display:flex;gap:6px;justify-content:center;margin-top:12px" data-quick-periods> <?php /* atalhos de período: mudam o mesmo filtro do topo */ ?>
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
        <?php
        // Coluna de faixas com as proporções dos limites em vigor.
        $atencao = \Aquapulse\Services\StatusRules::levelAttention();
        $critico = \Aquapulse\Services\StatusRules::levelCritical();
        ?>
        <div style="flex:none;width:92px;position:relative;height:250px;border-radius:10px;overflow:hidden;display:flex;flex-direction:column">
          <div style="flex:<?php echo 100 - $critico; ?>;background:var(--aq-danger)"></div>
          <div style="flex:<?php echo $critico - $atencao; ?>;background:var(--aq-warning)"></div>
          <div style="flex:<?php echo $atencao; ?>;background:var(--aq-primary)"></div>
          <?php /* linha que marca o nível atual; o JS define "bottom" com a porcentagem e o CSS anima a mudança */ ?>
          <div data-level-marker
               style="position:absolute;left:-4px;right:-4px;height:2.5px;background:var(--aq-text);transition:bottom 600ms ease"></div>
        </div>
        <ul style="flex:1 1 auto;display:flex;flex-direction:column;gap:11px;font-size:.84rem" data-level-bands></ul> <?php /* descrição de cada faixa (data.bands) */ ?>
      </div>
    </article>

  </div>
</div>

<div class="aq-grid aq-grid--7-6-4"> <?php /* três cards com larguras proporcionais 7:6:4 */ ?>
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
      <tbody data-records></tbody> <?php /* últimas leituras (data.readings) */ ?>
    </table>
    <?php echo aq_table_close(); ?>
    <p class="aq-card__sub" style="margin-top:12px">Leituras agrupadas por minuto. A tela busca novas leituras a cada 15 segundos.</p>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Comparativo mensal', 'tip' => 'Maior, média e menor cota diária de cada um dos últimos seis meses.']); ?>
    <div data-content="monthly" hidden>
      <?php echo aq_chart(['id' => 'grafico-mensal', 'size' => 'md', 'axis' => 'Cota (m)', 'desc' => 'Comparativo mensal de cota máxima, média e mínima.']); ?>
      <?php echo aq_legend([
          ['label' => 'Máxima', 'color' => '#38bdf8', 'style' => 'dashed'],
          ['label' => 'Média', 'color' => '#0b5bea'],
          ['label' => 'Mínima', 'color' => '#fb923c', 'style' => 'dashed'],
      ]); ?>
    </div>
    <?php echo aq_states('monthly'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Limites configurados', 'tip' => 'Cotas de atenção e crítica calculadas a partir dos limites de nível; alerta e vertimento vêm do cadastro da represa.']); ?>
    <div data-limits></div> <?php /* lista de cotas: alerta, atenção, crítica e vertimento */ ?>
    <p style="margin-top:14px">
      <a class="aq-card__link" href="configuracoes.php#limites">Ver limites operacionais <?php aq_the_icon('arrow-right'); ?></a>
    </p>
  </article>
</div>

<?php aq_page_end(['scripts' => ['pages/levels.js']]); // lógica desta tela