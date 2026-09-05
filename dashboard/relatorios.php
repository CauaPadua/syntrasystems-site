<?php
/** Aquapulse — Relatórios. */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

aq_page_start([
    'route'    => 'reports',
    'title'    => 'Relatórios',
    'subtitle' => 'Consulte, filtre e gere relatórios da operação',
]);
?>

<section class="aq-context" aria-label="Contexto de análise">
  <?php echo aq_select(['id' => 'filtro-empresa', 'label' => 'Empresa', 'options' => ['all' => 'Todas as empresas']]); ?>
  <?php echo aq_select(['id' => 'filtro-represa', 'label' => 'Represa', 'options' => ['all' => 'Todas as represas']]); ?>
  <span class="aq-context__spacer"></span>
</section>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'total', 'label' => 'Relatórios gerados', 'icon' => 'file-text', 'tip' => 'Total de relatórios no período.']);
  echo aq_kpi(['id' => 'done', 'label' => 'Concluídos', 'icon' => 'check-circle', 'tone' => 'success', 'tip' => 'Relatórios prontos para download.']);
  echo aq_kpi(['id' => 'processing', 'label' => 'Em processamento', 'icon' => 'refresh', 'tone' => 'warning', 'tip' => 'Relatórios sendo gerados no momento.']);
  echo aq_kpi(['id' => 'scheduled', 'label' => 'Agendados', 'icon' => 'calendar', 'tip' => 'Relatórios com geração programada.']);
  ?>
</div>

<!-- ------------------------------------------------------------- filtros -->
<section class="aq-context" aria-label="Filtros de relatórios">
  <div class="aq-field" style="flex:1 1 200px">
    <label class="aq-field__label" for="busca-relatorio">Buscar relatório</label>
    <input class="aq-input" type="search" id="busca-relatorio" placeholder="Buscar relatório" style="width:100%">
  </div>

  <?php
  echo aq_select(['id' => 'filtro-tipo', 'label' => 'Tipo', 'options' => [
      'all' => 'Todos os tipos', 'operational' => 'Operacional', 'hydrological' => 'Hidrológico',
      'quality' => 'Qualidade', 'planning' => 'Planejamento',
  ]]);
  echo aq_select(['id' => 'filtro-status', 'label' => 'Status', 'options' => [
      'all' => 'Todos', 'done' => 'Concluído', 'processing' => 'Processando', 'scheduled' => 'Agendado',
  ]]);
  ?>

  <div class="aq-field">
    <span class="aq-field__label">Período de geração</span>
    <span class="aq-status-text" style="line-height:42px"><?php aq_the_icon('calendar'); ?> 16/05/2024 → 22/05/2024</span>
  </div>

  <span class="aq-context__spacer"></span>

  <button class="aq-btn aq-btn--primary" type="button" data-modal-open="modal-relatorio" style="align-self:flex-end">
    <?php aq_the_icon('plus'); ?><span>Gerar novo relatório</span>
  </button>
</section>

<div class="aq-grid aq-grid--7-3">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Histórico de relatórios', 'tip' => 'Relatórios já gerados para o contexto selecionado.']); ?>
    <div data-content="reports" hidden>
      <?php echo aq_table_open('Histórico de relatórios'); ?>
      <table class="aq-table aq-table--tight">
        <thead>
          <tr>
            <th scope="col">Relatório</th>
            <th scope="col">Tipo</th>
            <th scope="col">Represa</th>
            <th scope="col">Período</th>
            <th scope="col">Gerado em</th>
            <th scope="col">Responsável</th>
            <th scope="col">Status</th>
            <th scope="col">Ações</th>
          </tr>
        </thead>
        <tbody data-rows></tbody>
      </table>
      <?php echo aq_table_close(); ?>

      <div class="aq-pagination">
        <span>Exibindo <strong data-field="pagination.count">—</strong> relatórios</span>
        <div class="aq-pagination__pages">
          <button class="aq-page-btn" type="button" disabled aria-label="Página anterior"><?php aq_the_icon('chevron-left'); ?></button>
          <button class="aq-page-btn is-active" type="button" aria-current="page">1</button>
          <button class="aq-page-btn" type="button" disabled aria-label="Próxima página"><?php aq_the_icon('chevron-right'); ?></button>
        </div>
        <span data-field="pagination.range">—</span>
      </div>
    </div>
    <?php echo aq_states('reports'); ?>
  </article>

  <div style="display:flex;flex-direction:column;gap:var(--aq-content-gap);min-width:0">
    <article class="aq-card">
      <?php echo aq_card_head(['title' => 'Relatórios agendados', 'icon' => 'calendar']); ?>
      <?php echo aq_table_open('Relatórios agendados'); ?>
      <table class="aq-table aq-table--tight">
        <thead>
          <tr><th scope="col">Relatório</th><th scope="col">Periodicidade</th><th scope="col">Próxima geração</th></tr>
        </thead>
        <tbody data-scheduled></tbody>
      </table>
      <?php echo aq_table_close(); ?>
      <p style="margin-top:12px"><a class="aq-card__link" href="#">Ver todos os agendamentos <?php aq_the_icon('arrow-right'); ?></a></p>
    </article>

    <article class="aq-card">
      <?php echo aq_card_head(['title' => 'Formatos disponíveis', 'icon' => 'download']); ?>
      <p class="aq-card__sub" style="margin-bottom:14px">Escolha o formato padrão para exportação dos relatórios.</p>
      <div class="aq-grid aq-grid--2">
        <div style="border:1px solid var(--aq-border);border-radius:11px;padding:14px">
          <p style="display:flex;align-items:center;gap:8px;font-weight:700;color:var(--aq-danger)">
            <?php aq_the_icon('file-text'); ?> PDF
          </p>
          <p class="aq-card__sub" style="margin-top:6px">Documento portátil ideal para impressão e compartilhamento.</p>
        </div>
        <div style="border:1px solid var(--aq-border);border-radius:11px;padding:14px">
          <p style="display:flex;align-items:center;gap:8px;font-weight:700;color:var(--aq-success)">
            <?php aq_the_icon('table'); ?> CSV
          </p>
          <p class="aq-card__sub" style="margin-top:6px">Arquivo de dados para análises e planilhas.</p>
        </div>
      </div>
    </article>
  </div>
</div>

<div class="aq-demo-note">
  <?php aq_the_icon('info'); ?>
  <span><strong>Dica.</strong> O download em <strong>CSV</strong> é gerado no próprio navegador a partir dos dados carregados.
  A exportação em <strong>PDF</strong> usa a impressão do navegador — a geração no servidor será implementada com o banco de dados.</span>
</div>

<!-- ------------------------------------- modal de geração (demonstrativo) -->
<div class="aq-modal" id="modal-relatorio" role="dialog" aria-modal="true" aria-labelledby="modal-relatorio-titulo" hidden>
  <div class="aq-modal__box">
    <div class="aq-modal__head">
      <span class="aq-kpi__icon" aria-hidden="true"><?php aq_the_icon('file-text'); ?></span>
      <h2 id="modal-relatorio-titulo">Gerar novo relatório</h2>
      <button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-modal-close="modal-relatorio" aria-label="Fechar">
        <?php aq_the_icon('close'); ?>
      </button>
    </div>

    <form id="form-relatorio">
      <div class="aq-field" style="margin-bottom:14px">
        <label class="aq-field__label" for="novo-tipo">Tipo de relatório</label>
        <select class="aq-select" id="novo-tipo" style="width:100%">
          <option value="operational">Operacional</option>
          <option value="hydrological">Hidrológico</option>
          <option value="quality">Qualidade</option>
          <option value="planning">Planejamento</option>
        </select>
      </div>
      <div class="aq-field">
        <label class="aq-field__label" for="novo-formato">Formato</label>
        <select class="aq-select" id="novo-formato" style="width:100%">
          <option value="pdf">PDF</option>
          <option value="csv">CSV</option>
        </select>
      </div>

      <div class="aq-demo-note" style="margin-top:16px">
        <?php aq_the_icon('info'); ?>
        <span><strong>Modo demonstrativo.</strong> O relatório não é gravado no servidor.
        A geração real dependerá do banco de dados.</span>
      </div>

      <div class="aq-modal__foot">
        <button class="aq-btn aq-btn--ghost" type="button" data-modal-close="modal-relatorio">Cancelar</button>
        <button class="aq-btn aq-btn--primary" type="submit"><?php aq_the_icon('plus'); ?><span>Gerar relatório</span></button>
      </div>
    </form>
  </div>
</div>

<?php aq_page_end(['scripts' => ['pages/reports.js']]);
