<?php
/** Aquapulse — Central de alertas. */
/*
 * Tela de acompanhamento das ocorrências. Estrutura em PHP; dados e interações
 * em assets/js/pages/alerts.js, que chama GET api/v1/alerts.php.
 *
 * Fluxo: filtros (empresa, represa, severidade, status, busca) -> tabela de
 * alertas -> clicar em uma linha abre o painel de detalhes à direita.
 * "Assumir" e "Resolver" são só demonstrativos (ver aviso no fim da página).
 */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';                                   // exige login e carrega o layout

aq_page_start([
    'route'    => 'alerts',                                               // destaca "Alertas" no menu
    'title'    => 'Alertas',
    'subtitle' => 'Acompanhe ocorrências e condições que exigem atenção',
]);
?>

<section class="aq-context" aria-label="Contexto de análise"> <?php /* filtros de empresa e represa (mesmo contexto das outras telas) */ ?>
  <div class="aq-context__label">
    <?php aq_the_icon('filter'); ?>
    <div><strong>Contexto de análise</strong><span>Alertas do contexto selecionado</span></div>
  </div>
  <?php echo aq_select(['id' => 'filtro-empresa', 'label' => 'Empresa', 'options' => ['all' => 'Todas as empresas']]); // demais opções carregadas pelo JS ?>
  <?php echo aq_select(['id' => 'filtro-represa', 'label' => 'Represa', 'options' => ['all' => 'Todas as represas']]); ?>
  <span class="aq-context__spacer"></span>
  <p class="aq-context__note">
    <?php aq_the_icon('refresh'); ?>
    <span>Contexto atualizado <span data-context-updated>há 2 min</span></span>
  </p>
</section>

<div class="aq-grid aq-grid--5"> <?php /* cinco cards de resumo, preenchidos com data.counts */ ?>
  <?php
  echo aq_kpi(['id' => 'active', 'label' => 'Alertas ativos', 'icon' => 'bell', 'tone' => 'danger', 'tip' => 'Ocorrências que ainda exigem ação.']);            // counts.active: não resolvidos
  echo aq_kpi(['id' => 'critical', 'label' => 'Críticos', 'icon' => 'alert-circle', 'tone' => 'danger', 'tip' => 'Alertas de severidade crítica.']);        // counts.critical
  echo aq_kpi(['id' => 'attention', 'label' => 'Em atenção', 'icon' => 'alert-triangle', 'tone' => 'warning', 'tip' => 'Alertas que requerem acompanhamento.']); // counts.attention
  echo aq_kpi(['id' => 'resolved', 'label' => 'Resolvidos hoje', 'icon' => 'check-circle', 'tone' => 'success', 'tip' => 'Ocorrências encerradas desde 00:00.']); // counts.resolved (fixo na API)
  echo aq_kpi(['id' => 'avg', 'label' => 'Tempo médio de resposta', 'icon' => 'clock', 'unit' => 'min', 'tip' => 'Média dos últimos 7 dias.']);             // counts.avg_minutes (fixo na API)
  ?>
</div>

<section class="aq-context" aria-label="Filtros de alertas"> <?php /* segunda barra: filtros específicos da lista de alertas */ ?>
  <div class="aq-field" style="flex:1 1 200px">
    <label class="aq-field__label" for="busca-alerta">Buscar alerta</label>
    <input class="aq-input" type="search" id="busca-alerta" placeholder="Buscar alerta" style="width:100%"> <?php /* busca por texto feita no navegador, sobre a lista já carregada */ ?>
  </div>

  <?php
  echo aq_select(['id' => 'filtro-severidade', 'label' => 'Severidade', 'options' => [        // valores iguais à allowlist Validator::SEVERITY
      'all' => 'Todas', 'critical' => 'Crítico', 'attention' => 'Atenção', 'info' => 'Informação',
  ]]);
  echo aq_select(['id' => 'filtro-status', 'label' => 'Status', 'options' => [                // valores iguais à allowlist Validator::ALERT_STATUS
      'all' => 'Todos', 'new' => 'Novo', 'analysis' => 'Em análise', 'resolved' => 'Resolvido',
  ]]);
  ?>

  <div class="aq-field">
    <span class="aq-field__label">Período</span>
    <span class="aq-status-text" style="line-height:42px"><?php aq_the_icon('calendar'); // período apenas informativo (texto fixo, não é um filtro) ?> 16/05/2024 → 22/05/2024</span>
  </div>

  <span class="aq-context__spacer"></span>

  <a class="aq-btn aq-btn--primary" href="configuracoes.php#limites" style="align-self:flex-end"> <?php /* leva à aba de limites nas Configurações */ ?>
    <?php aq_the_icon('gear'); ?><span>Configurar alertas</span>
  </a>
</section>

<div class="aq-grid aq-grid--2-1"> <?php /* tabela (2/3 da largura) + painel de detalhes (1/3) */ ?>
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
        <tbody data-rows></tbody> <?php /* linhas geradas por alerts.js; cada uma é clicável e abre o detalhe */ ?>
      </table>
      <?php echo aq_table_close(); ?>

      <div class="aq-pagination"> <?php /* paginação visual: há uma única página, por isso os botões ficam desabilitados */ ?>
        <span>Exibindo <strong data-field="pagination.count">—</strong> alertas</span>
        <div class="aq-pagination__pages">
          <button class="aq-page-btn" type="button" disabled aria-label="Página anterior"><?php aq_the_icon('chevron-left'); ?></button>
          <button class="aq-page-btn is-active" type="button" aria-current="page">1</button>
          <button class="aq-page-btn" type="button" disabled aria-label="Próxima página"><?php aq_the_icon('chevron-right'); ?></button>
        </div>
        <span data-field="pagination.range">—</span>
      </div>
    </div>
    <?php echo aq_states('alerts'); // carregando / sem alertas / erro da lista ?>
  </article>

  <!-- --------------------------------------------- painel de detalhes -->
  <article class="aq-card">
    <div data-content="detail" hidden> <?php /* preenchido com o alerta selecionado na tabela */ ?>
      <div class="aq-card__head">
        <div class="aq-card__title">
          <span data-field="detail.icon"></span>
          <span data-field="detail.title">—</span>
        </div>
        <div class="aq-card__actions"><span data-field="detail.severity"></span></div> <?php /* badge de severidade */ ?>
      </div>
      <p class="aq-card__sub" data-field="detail.reservoir">—</p>

      <div class="aq-grid aq-grid--2" style="margin-top:16px">
        <div>
          <p class="aq-card__sub">Valor atual</p>
          <strong style="font-size:1.35rem" data-field="detail.value">—</strong> <?php /* current_value do alerta */ ?>
          <p class="aq-card__sub" data-field="detail.detail"></p>
        </div>
        <div>
          <p class="aq-card__sub">Limite configurado</p>
          <strong style="font-size:1.35rem" data-field="detail.threshold">—</strong> <?php /* limite que disparou o alerta */ ?>
          <p class="aq-card__sub" data-field="detail.threshold_detail"></p>
        </div>
      </div>

      <h3 style="font-size:.9rem;margin-top:20px">Linha do tempo da ocorrência</h3>
      <ol style="margin-top:12px;display:flex;flex-direction:column;gap:14px" data-timeline></ol> <?php /* etapas do campo timeline (concluídas e pendentes) */ ?>

      <div class="aq-field" style="margin-top:18px">
        <label class="aq-field__label" for="alerta-observacao">Observações</label>
        <textarea class="aq-input" id="alerta-observacao" rows="2"
                  style="height:auto;padding:10px 14px;width:100%"
                  placeholder="Adicione uma observação sobre este alerta…"></textarea>
      </div>

      <div style="display:flex;gap:10px;margin-top:14px">
        <button class="aq-btn aq-btn--primary" type="button" style="flex:1" data-ack> <?php /* data-ack: alerts.js muda o status para "Em análise" (só no navegador) */ ?>
          <?php aq_the_icon('user'); ?><span>Assumir alerta</span>
        </button>
        <button class="aq-btn aq-btn--outline" type="button" style="flex:1" data-resolve> <?php /* data-resolve: marca como resolvido (só no navegador) */ ?>
          <?php aq_the_icon('check-circle'); ?><span>Marcar como resolvido</span>
        </button>
      </div>
    </div>
    <?php echo aq_states('detail'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--2-1">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Alertas nos últimos 7 dias', 'tip' => 'Distribuição diária por severidade.']); ?>
    <div data-content="chart" hidden>
    <?php echo aq_chart(['id' => 'grafico-alertas', 'size' => 'md', 'desc' => 'Alertas por dia agrupados por severidade.']); // barras empilhadas com data.chart ?>
    <?php echo aq_legend([
        ['label' => 'Críticos', 'color' => '#ef4444', 'style' => 'square'],
        ['label' => 'Atenção', 'color' => '#f59e0b', 'style' => 'square'],
        ['label' => 'Informação', 'color' => '#3b82f6', 'style' => 'square'],
        ['label' => 'Resolvidos', 'color' => '#16a34a', 'style' => 'square'],
    ]); ?>
    </div>
    <?php echo aq_states('chart'); ?>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Canais de notificação', 'tip' => 'Meios pelos quais os alertas são comunicados.']); ?>
    <div class="aq-list" data-channels></div> <?php /* lista de data.channels (e-mail, painel) */ ?>
  </article>
</div>

<div class="aq-demo-note"> <?php /* aviso de que as ações não são salvas no servidor */ ?>
  <?php aq_the_icon('info'); ?>
  <span><strong>Ações demonstrativas.</strong> "Assumir alerta" e "Marcar como resolvido" ficam registrados
  apenas nesta sessão do navegador (<code>sessionStorage</code>). A persistência definitiva será
  implementada com o banco de dados.</span>
</div>

<?php aq_page_end(['scripts' => ['pages/alerts.js']]); // fecha o layout e carrega a lógica da tela