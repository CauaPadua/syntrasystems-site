<?php
/** Aquapulse — Monitoramento / Situação operacional. */
/*
 * Estado dos equipamentos de UMA represa: quem está transmitindo, alertas em
 * aberto e manutenções. Dados de GET api/v1/monitoring/operation.php,
 * carregados por assets/js/pages/operation.js (com monitor-page.js).
 *
 * "Abrir chamado" grava no banco: POST api/v1/monitoring/operation.php.
 */

declare(strict_types=1);

define('AQ_DEPTH', 2);
require dirname(__DIR__) . '/includes/page.php';

aq_page_start([
    'route'    => 'monitoring.operation',
    'title'    => 'Situação operacional',
    'subtitle' => 'Acompanhe a comunicação dos equipamentos e as manutenções da represa',
]);

echo aq_monitor_bar(['periods' => []]);                                   // esta tela mostra o estado atual: não tem seletor de período
?>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'general', 'label' => 'Situação geral', 'icon' => 'check-circle', 'tip' => 'Atenção quando há equipamento sem comunicação ou alerta em aberto; crítico quando há alerta crítico.']);
  echo aq_kpi(['id' => 'sensors', 'label' => 'Equipamentos online', 'icon' => 'radio', 'tip' => 'Equipamentos ativos que enviaram leitura nos últimos minutos.']);
  echo aq_kpi(['id' => 'last', 'label' => 'Última leitura', 'icon' => 'clock', 'tip' => 'Momento da leitura mais recente recebida desta represa.']);
  echo aq_kpi(['id' => 'alerts', 'label' => 'Alertas em aberto', 'icon' => 'bell', 'tip' => 'Alertas desta represa ainda não resolvidos.']);
  ?>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Equipamentos por grandeza', 'tip' => 'Quantos equipamentos de cada tipo estão transmitindo.']); ?>
    <div data-systems style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:6px"></div>
  </article>

  <article class="aq-card">
    <?php echo aq_card_head(['title' => 'Comunicação', 'tip' => 'Percentual dos equipamentos ativos que estão online agora.']); ?>
    <div data-content="availability" hidden style="display:flex;gap:20px;align-items:center">
      <div style="flex:none;width:190px;position:relative">
        <?php echo aq_chart(['id' => 'grafico-disponibilidade', 'size' => 'md', 'desc' => 'Percentual de equipamentos online.']); ?>
      </div>
      <ul style="flex:1 1 auto;display:flex;flex-direction:column;gap:16px" data-availability></ul>
    </div>
    <?php echo aq_states('availability'); ?>
  </article>
</div>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card aq-card--dense">
    <?php echo aq_card_head([
        'title'   => 'Equipamentos da represa',
        'tip'     => 'Situação de comunicação e horário da última leitura de cada equipamento.',
        'actions' => '<a class="aq-card__link" href="../equipamentos.php">Gerenciar equipamentos ' . aq_icon('arrow-right') . '</a>',
    ]); ?>
    <?php echo aq_table_open('Equipamentos da represa'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr><th scope="col">Equipamento</th><th scope="col">Grandeza</th><th scope="col">Situação</th><th scope="col">Última leitura</th></tr>
      </thead>
      <tbody data-components></tbody>
    </table>
    <?php echo aq_table_close(); ?>
  </article>

  <article class="aq-card aq-card--dense">
    <?php echo aq_card_head(['title' => 'Manutenções e chamados', 'tip' => 'Manutenções programadas e chamados abertos, da data mais próxima para a mais distante.']); ?>
    <?php echo aq_table_open('Manutenções e chamados'); ?>
    <table class="aq-table aq-table--tight">
      <thead>
        <tr><th scope="col">Data</th><th scope="col">Equipamento</th><th scope="col">Tipo</th><th scope="col">Prioridade</th></tr>
      </thead>
      <tbody data-maintenances></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <button class="aq-btn aq-btn--outline" type="button" style="width:100%;margin-top:14px" data-modal-open="modal-chamado">
      <?php aq_the_icon('plus'); ?><span>Abrir chamado</span>
    </button>
  </article>
</div>

<!-- --------------------------------------- modal de abertura de chamado -->
<div class="aq-modal" id="modal-chamado" role="dialog" aria-modal="true" aria-labelledby="modal-chamado-titulo" hidden>
  <div class="aq-modal__box">
    <div class="aq-modal__head">
      <span class="aq-kpi__icon" aria-hidden="true"><?php aq_the_icon('wrench'); ?></span>
      <h2 id="modal-chamado-titulo">Abrir chamado de manutenção</h2>
      <button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-modal-close="modal-chamado" aria-label="Fechar">
        <?php aq_the_icon('close'); ?>
      </button>
    </div>

    <form id="form-chamado" novalidate> <?php /* enviado pelo operation.js; a validação é feita no servidor */ ?>
      <div class="aq-field" style="margin-bottom:14px">
        <label class="aq-field__label" for="chamado-equipamento">Equipamento</label>
        <select class="aq-select" id="chamado-equipamento" name="equipment_id" style="width:100%"></select> <?php /* equipamentos da represa, preenchidos pelo JS */ ?>
        <span class="aq-field__error" data-error-for="equipment_id" hidden></span>
      </div>
      <div class="aq-field" style="margin-bottom:14px">
        <label class="aq-field__label" for="chamado-prioridade">Prioridade</label>
        <select class="aq-select" id="chamado-prioridade" name="priority" style="width:100%">
          <option value="low">Baixa</option>
          <option value="attention" selected>Atenção</option>
          <option value="critical">Crítica</option>
        </select>
        <span class="aq-field__error" data-error-for="priority" hidden></span>
      </div>
      <div class="aq-field">
        <label class="aq-field__label" for="chamado-descricao">Descrição</label>
        <textarea class="aq-input" id="chamado-descricao" name="description" rows="3" maxlength="255"
                  style="height:auto;padding:10px 14px;width:100%" placeholder="Descreva a ocorrência"></textarea>
        <span class="aq-field__error" data-error-for="description" hidden></span>
      </div>

      <div class="aq-modal__foot">
        <button class="aq-btn aq-btn--ghost" type="button" data-modal-close="modal-chamado">Cancelar</button>
        <button class="aq-btn aq-btn--primary" type="submit"><?php aq_the_icon('save'); ?><span>Registrar chamado</span></button>
      </div>
    </form>
  </div>
</div>

<?php aq_page_end(['scripts' => ['pages/operation.js'], 'monitor' => true]);
