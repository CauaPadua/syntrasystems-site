<?php
/** Aquapulse — Monitoramento / Situação operacional. */

declare(strict_types=1);

define('AQ_DEPTH', 2);
require dirname(__DIR__) . '/includes/page.php';

aq_page_start([
    'route'    => 'monitoring.operation',
    'title'    => 'Situação operacional',
    'subtitle' => 'Acompanhe a disponibilidade dos sistemas e equipamentos da represa',
]);

echo aq_monitor_bar([
    'period_id'    => 'filtro-sistemas',
    'period_label' => 'Sistemas',
    'periods'      => ['all' => 'Todos os sistemas'],
]);
?>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'general', 'label' => 'Situação geral', 'icon' => 'check-circle', 'tone' => 'success', 'tip' => 'Consolidação da situação de todos os sistemas.']);
  echo aq_kpi(['id' => 'sensors', 'label' => 'Sensores online', 'icon' => 'radio', 'tone' => 'success', 'tip' => 'Sensores respondendo à telemetria.']);
  echo aq_kpi(['id' => 'gates', 'label' => 'Comportas operacionais', 'icon' => 'gate', 'tone' => 'success', 'tip' => 'Comportas em condição de operação.']);
  echo aq_kpi(['id' => 'alerts', 'label' => 'Alertas ativos', 'icon' => 'bell', 'tone' => 'warning', 'tip' => 'Ocorrências abertas que exigem atenção.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Visão geral dos sistemas', 'tip' => 'Situação de cada subsistema conectado à represa.']); ?>
    <div data-systems style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:6px"></div>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Disponibilidade', 'tip' => 'Percentual de disponibilidade dos serviços críticos.']); ?>
    <div data-content="availability" hidden style="display:flex;gap:20px;align-items:center">
      <div style="flex:none;width:190px;position:relative">
        <?php echo aq_chart(['id' => 'grafico-disponibilidade', 'size' => 'md', 'desc' => 'Disponibilidade geral dos sistemas.']); ?>
      </div>
      <ul style="flex:1 1 auto;display:flex;flex-direction:column;gap:16px" data-availability></ul>
    </div>
    <?php echo aq_states('availability'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--4-6-5">
  <article class="aq-card aq-card--dense">
    <?php echo aq_card_head(['title' => 'Status dos componentes', 'tip' => 'Última verificação de cada componente monitorado.']); ?>
    <?php echo aq_table_open('Status dos componentes'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr><th scope="col">Componente</th><th scope="col">Status</th><th scope="col">Última atualização</th></tr>
      </thead>
      <tbody data-components></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <p style="margin-top:12px"><a class="aq-card__link" href="#">Ver detalhes dos componentes <?php aq_the_icon('arrow-right'); ?></a></p>
  </article>

  <article class="aq-card aq-card--dense">
    <?php echo aq_card_head(['title' => 'Eventos recentes', 'tip' => 'Ocorrências registradas pelo sistema.']); ?>
    <?php echo aq_table_open('Eventos recentes'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr><th scope="col">Horário</th><th scope="col">Componente</th><th scope="col">Evento</th><th scope="col">Prioridade</th><th scope="col">Status</th></tr>
      </thead>
      <tbody data-events></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <p style="margin-top:12px"><a class="aq-card__link" href="../alertas.php">Ver todos os eventos <?php aq_the_icon('arrow-right'); ?></a></p>
  </article>

  <article class="aq-card aq-card--dense">
    <?php echo aq_card_head(['title' => 'Próximas manutenções', 'tip' => 'Agenda de manutenções preventivas e corretivas.']); ?>
    <?php echo aq_table_open('Próximas manutenções'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr><th scope="col">Data</th><th scope="col">Equipamento</th><th scope="col">Tipo</th><th scope="col">Prioridade</th></tr>
      </thead>
      <tbody data-maintenances></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <button class="aq-btn aq-btn--outline" type="button" style="width:100%;margin-top:14px" data-modal-open="modal-chamado">
      <?php aq_the_icon('external-link'); ?><span>Abrir chamado</span>
    </button>
  </article>
</div>

<!-- ------------------------------- modal de abertura de chamado (demo) ---- -->
<div class="aq-modal" id="modal-chamado" role="dialog" aria-modal="true" aria-labelledby="modal-chamado-titulo" hidden>
  <div class="aq-modal__box">
    <div class="aq-modal__head">
      <span class="aq-kpi__icon" aria-hidden="true"><?php aq_the_icon('wrench'); ?></span>
      <h2 id="modal-chamado-titulo">Abrir chamado de manutenção</h2>
      <button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-modal-close="modal-chamado" aria-label="Fechar">
        <?php aq_the_icon('close'); ?>
      </button>
    </div>

    <form id="form-chamado">
      <div class="aq-field" style="margin-bottom:14px">
        <label class="aq-field__label" for="chamado-equipamento">Equipamento</label>
        <select class="aq-select" id="chamado-equipamento" style="width:100%" data-chamado-equipamentos></select>
      </div>
      <div class="aq-field" style="margin-bottom:14px">
        <label class="aq-field__label" for="chamado-prioridade">Prioridade</label>
        <select class="aq-select" id="chamado-prioridade" style="width:100%">
          <option value="low">Baixa</option>
          <option value="attention" selected>Atenção</option>
          <option value="critical">Crítica</option>
        </select>
      </div>
      <div class="aq-field">
        <label class="aq-field__label" for="chamado-descricao">Descrição</label>
        <textarea class="aq-input" id="chamado-descricao" rows="3" style="height:auto;padding:10px 14px;width:100%"
                  placeholder="Descreva a ocorrência"></textarea>
      </div>

      <div class="aq-demo-note" style="margin-top:16px">
        <?php aq_the_icon('info'); ?>
        <span><strong>Modo demonstrativo.</strong> O chamado fica registrado apenas nesta sessão do navegador.
        A persistência definitiva será implementada com o banco de dados.</span>
      </div>

      <div class="aq-modal__foot">
        <button class="aq-btn aq-btn--ghost" type="button" data-modal-close="modal-chamado">Cancelar</button>
        <button class="aq-btn aq-btn--primary" type="submit"><?php aq_the_icon('save'); ?><span>Registrar chamado</span></button>
      </div>
    </form>
  </div>
</div>

<?php aq_page_end(['scripts' => ['pages/operation.js'], 'monitor' => true]);
