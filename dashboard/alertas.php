<?php
/** Aquapulse — Central de alertas. */
/*
 * Lista os alertas abertos pelas leituras e permite tratá-los. Estrutura em
 * PHP; dados e interações em assets/js/pages/alerts.js, que usa
 * api/v1/alerts.php (GET para listar, POST para assumir ou resolver).
 *
 * Fluxo: filtros (empresa, represa, severidade, status, busca) -> tabela ->
 * clicar em uma linha abre o detalhe à direita -> "Assumir" / "Resolver"
 * gravam o novo status no banco.
 */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

aq_page_start([
    'route'    => 'alerts',
    'title'    => 'Alertas',
    'subtitle' => 'Acompanhe ocorrências e condições que exigem atenção',
]);
?>

<section class="aq-context" aria-label="Contexto de análise">
  <div class="aq-context__label">
    <?php aq_the_icon('filter'); ?>
    <div><strong>Contexto de análise</strong><span>Alertas do contexto selecionado</span></div>
  </div>
  <?php echo aq_select(['id' => 'filtro-empresa', 'label' => 'Empresa', 'options' => ['all' => 'Todas as empresas']]); ?>
  <?php echo aq_select(['id' => 'filtro-represa', 'label' => 'Represa', 'options' => ['all' => 'Todas as represas']]); ?>
  <span class="aq-context__spacer"></span>
  <p class="aq-context__note">
    <?php aq_the_icon('calendar'); ?>
    <span>Resumo de 7 dias: <span data-field="period">—</span></span> <?php /* período dos cards "resolvidos" e "tempo médio" e do gráfico */ ?>
  </p>
</section>

<div class="aq-grid aq-grid--5">
  <?php
  echo aq_kpi(['id' => 'active', 'label' => 'Alertas em aberto', 'icon' => 'bell', 'tone' => 'danger', 'tip' => 'Alertas novos ou em análise.']);
  echo aq_kpi(['id' => 'critical', 'label' => 'Críticos', 'icon' => 'alert-circle', 'tone' => 'danger', 'tip' => 'Alertas em aberto de severidade crítica.']);
  echo aq_kpi(['id' => 'attention', 'label' => 'Em atenção', 'icon' => 'alert-triangle', 'tone' => 'warning', 'tip' => 'Alertas em aberto que requerem acompanhamento.']);
  echo aq_kpi(['id' => 'resolved', 'label' => 'Resolvidos', 'icon' => 'check-circle', 'tone' => 'success', 'tip' => 'Alertas resolvidos nos últimos 7 dias.']);
  echo aq_kpi(['id' => 'avg', 'label' => 'Tempo até resolver', 'icon' => 'clock', 'unit' => 'min', 'tip' => 'Média entre a detecção e a resolução, nos alertas resolvidos em 7 dias.']);
  ?>
</div>

<section class="aq-context" aria-label="Filtros de alertas">
  <div class="aq-field" style="flex:1 1 200px">
    <label class="aq-field__label" for="busca-alerta">Buscar alerta</label>
    <input class="aq-input" type="search" id="busca-alerta" placeholder="Título, represa ou métrica" style="width:100%"> <?php /* filtra no navegador, sobre a lista carregada */ ?>
  </div>
  <?php
  echo aq_select(['id' => 'filtro-severidade', 'label' => 'Severidade', 'options' => [
      'all' => 'Todas', 'critical' => 'Crítico', 'attention' => 'Atenção',
  ]]);
  echo aq_select(['id' => 'filtro-status', 'label' => 'Status', 'options' => [
      'all' => 'Todos', 'new' => 'Novo', 'analysis' => 'Em análise', 'resolved' => 'Resolvido',
  ]]);
  ?>
  <span class="aq-context__spacer"></span>
  <a class="aq-btn aq-btn--ghost" href="configuracoes.php#limites" style="align-self:flex-end">
    <?php aq_the_icon('sliders'); ?><span>Ver limites</span>
  </a>
</section>

<div class="aq-grid aq-grid--2-1">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Alertas da operação', 'tip' => 'Selecione um alerta para ver os detalhes ao lado.']); ?>
    <div data-content="alerts" hidden>
      <?php echo aq_table_open('Alertas da operação'); ?>
      <table class="aq-table aq-table--tight">
        <thead>
          <tr>
            <th scope="col">Severidade</th>
            <th scope="col">Alerta</th>
            <th scope="col">Represa</th>
            <th scope="col">Métrica</th>
            <th scope="col">Detectado em</th>
            <th scope="col">Responsável</th>
            <th scope="col">Status</th>
          </tr>
        </thead>
        <tbody data-rows></tbody> <?php /* cada linha é clicável e abre o detalhe */ ?>
      </table>
      <?php echo aq_table_close(); ?>
      <p class="aq-card__sub" style="margin-top:12px">Exibindo <strong data-field="list.count">—</strong> alerta(s).</p>
    </div>
    <?php echo aq_states('alerts'); ?>
  </article>

  <!-- --------------------------------------------- painel de detalhes -->
  <article class="aq-card">
    <div data-content="detail" hidden>
      <div class="aq-card__head">
        <div class="aq-card__title">
          <span data-field="detail.icon"></span>
          <span data-field="detail.title">—</span>
        </div>
        <div class="aq-card__actions"><span data-field="detail.severity"></span></div>
      </div>
      <p class="aq-card__sub" data-field="detail.reservoir">—</p>

      <div class="aq-grid aq-grid--2" style="margin-top:16px">
        <div>
          <p class="aq-card__sub">Valor medido</p>
          <strong style="font-size:1.35rem" data-field="detail.value">—</strong>
          <p class="aq-card__sub" data-field="detail.detail"></p>
        </div>
        <div>
          <p class="aq-card__sub">Limite</p>
          <strong style="font-size:1.35rem" data-field="detail.threshold">—</strong>
          <p class="aq-card__sub" data-field="detail.threshold_detail"></p>
        </div>
      </div>

      <h3 style="font-size:.9rem;margin-top:20px">Linha do tempo</h3>
      <ol style="margin-top:12px;display:flex;flex-direction:column;gap:14px" data-timeline></ol>

      <div style="display:flex;gap:10px;margin-top:18px">
        <button class="aq-btn aq-btn--primary" type="button" style="flex:1" data-ack> <?php /* grava status "em análise" e o responsável */ ?>
          <?php aq_the_icon('user'); ?><span>Assumir alerta</span>
        </button>
        <button class="aq-btn aq-btn--outline" type="button" style="flex:1" data-resolve> <?php /* grava status "resolvido" e o horário */ ?>
          <?php aq_the_icon('check-circle'); ?><span>Marcar como resolvido</span>
        </button>
      </div>
    </div>
    <?php echo aq_states('detail'); ?>
  </article>
</div>

<article class="aq-card">
  <?php echo aq_card_head(['title' => 'Alertas nos últimos 7 dias', 'tip' => 'Alertas abertos em cada dia, por severidade, e alertas resolvidos em cada dia.']); ?>
  <div data-content="chart" hidden>
  <?php echo aq_chart(['id' => 'grafico-alertas', 'size' => 'md', 'desc' => 'Alertas abertos e resolvidos por dia.']); ?>
  <?php echo aq_legend([
      ['label' => 'Abertos — críticos', 'color' => '#ef4444', 'style' => 'square'],
      ['label' => 'Abertos — atenção', 'color' => '#f59e0b', 'style' => 'square'],
      ['label' => 'Resolvidos', 'color' => '#16a34a', 'style' => 'square'],
  ]); ?>
  </div>
  <?php echo aq_states('chart'); ?>
</article>

<div class="aq-note">
  <?php aq_the_icon('info'); ?>
  <span><strong>Como os alertas surgem.</strong> A cada leitura recebida, o sistema compara o valor com os limites
  cadastrados. Se o valor sai da faixa normal e ainda não há alerta em aberto para aquela grandeza na represa,
  um alerta é criado. "Assumir" e "Resolver" ficam gravados, com o responsável e o horário.</span>
</div>

<?php aq_page_end(['scripts' => ['pages/alerts.js']]);
