<?php
/** Aquapulse — Relatórios. */
/*
 * Exportação das leituras em CSV. A lista mostra os relatórios já gerados
 * (tabela reports); "Gerar relatório" registra um novo e o arquivo é montado
 * no servidor, a partir das leituras gravadas, a cada download.
 *
 * Lógica em assets/js/pages/reports.js, que usa api/v1/reports.php.
 */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

aq_page_start([
    'route'    => 'reports',
    'title'    => 'Relatórios',
    'subtitle' => 'Exporte as leituras registradas de cada represa',
]);
?>

<section class="aq-context" aria-label="Contexto de análise">
  <div class="aq-context__label">
    <?php aq_the_icon('filter'); ?>
    <div><strong>Contexto de análise</strong><span>Relatórios do contexto selecionado</span></div>
  </div>
  <?php echo aq_select(['id' => 'filtro-empresa', 'label' => 'Empresa', 'options' => ['all' => 'Todas as empresas']]); ?>
  <?php echo aq_select(['id' => 'filtro-represa', 'label' => 'Represa', 'options' => ['all' => 'Todas as represas']]); ?>
</section>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'total', 'label' => 'Relatórios gerados', 'icon' => 'file-text', 'tip' => 'Total de relatórios do contexto selecionado.']);
  echo aq_kpi(['id' => 'operational', 'label' => 'Operacionais', 'icon' => 'table', 'tip' => 'Todas as grandezas medidas.']);
  echo aq_kpi(['id' => 'hydrological', 'label' => 'Hidrológicos', 'icon' => 'waves', 'tip' => 'Nível, volume, vazão e chuva.']);
  echo aq_kpi(['id' => 'quality', 'label' => 'Qualidade', 'icon' => 'droplet', 'tip' => 'pH e temperatura da água.']);
  ?>
</div>

<section class="aq-context" aria-label="Filtros de relatórios">
  <div class="aq-field" style="flex:1 1 200px">
    <label class="aq-field__label" for="busca-relatorio">Buscar relatório</label>
    <input class="aq-input" type="search" id="busca-relatorio" placeholder="Nome, represa ou tipo" style="width:100%">
  </div>
  <?php
  echo aq_select(['id' => 'filtro-tipo', 'label' => 'Tipo', 'options' => [
      'all' => 'Todos', 'operational' => 'Operacional', 'hydrological' => 'Hidrológico', 'quality' => 'Qualidade',
  ]]);
  ?>
  <span class="aq-context__spacer"></span>
  <button class="aq-btn aq-btn--primary" type="button" style="align-self:flex-end" data-modal-open="modal-relatorio">
    <?php aq_the_icon('plus'); ?><span>Gerar relatório</span>
  </button>
</section>

<article class="aq-card">
  <?php echo aq_card_head(['title' => 'Relatórios gerados', 'tip' => 'O arquivo é montado a partir das leituras do banco a cada download.']); ?>
  <div data-content="reports" hidden>
    <?php echo aq_table_open('Relatórios gerados'); ?>
    <table class="aq-table">
      <thead>
        <tr>
          <th scope="col">Relatório</th>
          <th scope="col">Tipo</th>
          <th scope="col">Represa</th>
          <th scope="col">Período</th>
          <th scope="col">Gerado em</th>
          <th scope="col">Gerado por</th>
          <th scope="col"><span class="aq-visually-hidden">Baixar</span></th>
        </tr>
      </thead>
      <tbody data-rows></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <p class="aq-card__sub" style="margin-top:12px">Exibindo <strong data-field="list.count">—</strong> relatório(s).</p>
  </div>
  <?php echo aq_states('reports'); ?>
</article>

<div class="aq-note">
  <?php aq_the_icon('info'); ?>
  <span><strong>O que o arquivo contém.</strong> Uma linha por dia do período, com a média das leituras de cada
  grandeza (para a chuva, o total do dia). Formato CSV com ponto e vírgula, pronto para abrir no Excel.</span>
</div>

<!-- ----------------------------------------------- modal de geração -->
<div class="aq-modal" id="modal-relatorio" role="dialog" aria-modal="true" aria-labelledby="modal-relatorio-titulo" hidden>
  <div class="aq-modal__box">
    <div class="aq-modal__head">
      <span class="aq-kpi__icon" aria-hidden="true"><?php aq_the_icon('file-text'); ?></span>
      <h2 id="modal-relatorio-titulo">Gerar relatório</h2>
      <button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-modal-close="modal-relatorio" aria-label="Fechar">
        <?php aq_the_icon('close'); ?>
      </button>
    </div>

    <form id="form-relatorio" novalidate> <?php /* enviado por reports.js; a validação é feita no servidor */ ?>
      <div class="aq-field" style="margin-bottom:14px">
        <label class="aq-field__label" for="relatorio-represa">Represa</label>
        <select class="aq-select" id="relatorio-represa" name="reservoir_id" style="width:100%"></select>
        <span class="aq-field__error" data-error-for="reservoir_id" hidden></span>
      </div>
      <div class="aq-field" style="margin-bottom:14px">
        <label class="aq-field__label" for="relatorio-tipo">Tipo</label>
        <select class="aq-select" id="relatorio-tipo" name="type" style="width:100%">
          <option value="operational">Operacional — todas as grandezas</option>
          <option value="hydrological">Hidrológico — nível, volume, vazão e chuva</option>
          <option value="quality">Qualidade — pH e temperatura</option>
        </select>
        <span class="aq-field__error" data-error-for="type" hidden></span>
      </div>
      <div class="aq-grid aq-grid--2">
        <div class="aq-field">
          <label class="aq-field__label" for="relatorio-inicio">Data inicial</label>
          <input class="aq-input" type="date" id="relatorio-inicio" name="from" style="width:100%">
          <span class="aq-field__error" data-error-for="from" hidden></span>
        </div>
        <div class="aq-field">
          <label class="aq-field__label" for="relatorio-fim">Data final</label>
          <input class="aq-input" type="date" id="relatorio-fim" name="to" style="width:100%">
          <span class="aq-field__error" data-error-for="to" hidden></span>
        </div>
      </div>

      <div class="aq-modal__foot">
        <button class="aq-btn aq-btn--ghost" type="button" data-modal-close="modal-relatorio">Cancelar</button>
        <button class="aq-btn aq-btn--primary" type="submit"><?php aq_the_icon('download'); ?><span>Gerar e baixar</span></button>
      </div>
    </form>
  </div>
</div>

<?php aq_page_end(['scripts' => ['pages/reports.js']]);
