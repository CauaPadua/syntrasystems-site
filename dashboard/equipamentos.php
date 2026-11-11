<?php
/** Aquapulse — Equipamentos (cadastro completo: consultar, cadastrar, editar e excluir). */
/*
 * CRUD da tabela sensors. A lista vem de GET api/v1/equipment.php; as gravações
 * vão por POST para o mesmo endpoint (ações create, update e delete). Lógica em
 * assets/js/pages/equipment.js.
 *
 * Consultar é permitido a qualquer usuário logado. Cadastrar, editar e excluir
 * são só para administradores: os botões só são gerados para esse perfil, e a
 * API confere o perfil de novo a cada gravação.
 */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

use Aquapulse\Support\Guard;

$podeEditar = Guard::isAdmin($AQ_USER);

aq_page_start([
    'route'    => 'equipment',
    'title'    => 'Equipamentos',
    'subtitle' => 'Cadastro dos equipamentos de medição e a última leitura de cada um',
]);
?>

<section class="aq-context" aria-label="Filtros de equipamentos">
  <?php echo aq_select(['id' => 'filtro-represa', 'label' => 'Represa', 'options' => ['all' => 'Todas as represas']]); ?>
  <div class="aq-field" style="flex:1 1 220px">
    <label class="aq-field__label" for="busca-equipamento">Buscar equipamento</label>
    <input class="aq-input" type="search" id="busca-equipamento" placeholder="Código, nome ou local" style="width:100%">
  </div>
  <span class="aq-context__spacer"></span>
  <?php if ($podeEditar): ?>
    <button class="aq-btn aq-btn--primary" type="button" style="align-self:flex-end" data-new>
      <?php aq_the_icon('plus'); ?><span>Novo equipamento</span>
    </button>
  <?php endif; ?>
</section>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'total', 'label' => 'Equipamentos', 'icon' => 'signal', 'tip' => 'Equipamentos cadastrados no filtro selecionado.']);
  echo aq_kpi(['id' => 'online', 'label' => 'Online', 'icon' => 'radio', 'tone' => 'success', 'tip' => 'Ativos e com leitura recebida nos últimos minutos.']);
  echo aq_kpi(['id' => 'offline', 'label' => 'Sem comunicação', 'icon' => 'wifi-off', 'tone' => 'warning', 'tip' => 'Ativos no cadastro, mas sem leitura recente.']);
  echo aq_kpi(['id' => 'unavailable', 'label' => 'Fora de operação', 'icon' => 'wrench', 'tip' => 'Em manutenção ou desativados: não enviam leituras.']);
  ?>
</div>

<article class="aq-card">
  <?php echo aq_card_head(['title' => 'Equipamentos cadastrados', 'tip' => 'A última leitura vem da view vw_ultimas_medicoes.']); ?>
  <div data-content="equipment" hidden>
    <?php echo aq_table_open('Equipamentos cadastrados'); ?>
    <table class="aq-table">
      <thead>
        <tr>
          <th scope="col">Código</th>
          <th scope="col">Nome</th>
          <th scope="col">Grandeza</th>
          <th scope="col">Represa</th>
          <th scope="col">Local</th>
          <th scope="col">Cadastro</th>
          <th scope="col">Comunicação</th>
          <th scope="col" class="is-num">Última leitura</th>
          <?php if ($podeEditar): ?><th scope="col"><span class="aq-visually-hidden">Ações</span></th><?php endif; ?>
        </tr>
      </thead>
      <tbody data-rows></tbody>
    </table>
    <?php echo aq_table_close(); ?>
    <p class="aq-card__sub" style="margin-top:12px">Exibindo <strong data-field="list.count">—</strong> equipamento(s).</p>
  </div>
  <?php echo aq_states('equipment'); ?>
</article>

<div class="aq-note">
  <?php aq_the_icon('info'); ?>
  <span><strong>Cadastro e comunicação.</strong> "Cadastro" é a situação definida aqui (ativo, em manutenção ou desativado).
  "Comunicação" é calculada: um equipamento ativo fica online enquanto envia leituras e passa a "sem comunicação"
  depois de <span data-field="online.window">alguns</span> minutos sem enviar.
  <?php if (!$podeEditar): ?>Seu perfil permite apenas consultar.<?php endif; ?></span>
</div>

<?php if ($podeEditar): ?>
<!-- ------------------------------------------ modal: cadastrar / editar -->
<div class="aq-modal" id="modal-equipamento" role="dialog" aria-modal="true" aria-labelledby="modal-equipamento-titulo" hidden>
  <div class="aq-modal__box">
    <div class="aq-modal__head">
      <span class="aq-kpi__icon" aria-hidden="true"><?php aq_the_icon('signal'); ?></span>
      <h2 id="modal-equipamento-titulo" data-form-title>Novo equipamento</h2>
      <button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-modal-close="modal-equipamento" aria-label="Fechar">
        <?php aq_the_icon('close'); ?>
      </button>
    </div>

    <form id="form-equipamento" novalidate> <?php /* enviado por equipment.js; a validação é feita no servidor */ ?>
      <div class="aq-grid aq-grid--2" style="margin-bottom:14px">
        <div class="aq-field">
          <label class="aq-field__label" for="eq-id">Código</label>
          <input class="aq-input" type="text" id="eq-id" name="id" maxlength="30" placeholder="SEN-NIV-04" style="width:100%;text-transform:uppercase">
          <span class="aq-field__error" data-error-for="id" hidden></span>
        </div>
        <div class="aq-field">
          <label class="aq-field__label" for="eq-type">Grandeza medida</label>
          <select class="aq-select" id="eq-type" name="type" style="width:100%"></select>
          <span class="aq-field__error" data-error-for="type" hidden></span>
        </div>
      </div>
      <div class="aq-field" style="margin-bottom:14px">
        <label class="aq-field__label" for="eq-name">Nome</label>
        <input class="aq-input" type="text" id="eq-name" name="name" maxlength="120" placeholder="Régua de nível" style="width:100%">
        <span class="aq-field__error" data-error-for="name" hidden></span>
      </div>
      <div class="aq-field" style="margin-bottom:14px">
        <label class="aq-field__label" for="eq-location">Local de instalação</label>
        <input class="aq-input" type="text" id="eq-location" name="location" maxlength="120" placeholder="Barragem principal" style="width:100%">
        <span class="aq-field__error" data-error-for="location" hidden></span>
      </div>
      <div class="aq-grid aq-grid--2">
        <div class="aq-field">
          <label class="aq-field__label" for="eq-reservoir">Represa</label>
          <select class="aq-select" id="eq-reservoir" name="reservoir_id" style="width:100%"></select>
          <span class="aq-field__error" data-error-for="reservoir_id" hidden></span>
        </div>
        <div class="aq-field">
          <label class="aq-field__label" for="eq-status">Situação</label>
          <select class="aq-select" id="eq-status" name="status" style="width:100%"></select>
          <span class="aq-field__error" data-error-for="status" hidden></span>
        </div>
      </div>

      <div class="aq-modal__foot">
        <button class="aq-btn aq-btn--ghost" type="button" data-modal-close="modal-equipamento">Cancelar</button>
        <button class="aq-btn aq-btn--primary" type="submit"><?php aq_the_icon('save'); ?><span>Salvar</span></button>
      </div>
    </form>
  </div>
</div>

<!-- ------------------------------------------------- modal: excluir -->
<div class="aq-modal" id="modal-excluir" role="dialog" aria-modal="true" aria-labelledby="modal-excluir-titulo" hidden>
  <div class="aq-modal__box">
    <div class="aq-modal__head">
      <span class="aq-kpi__icon aq-kpi__icon--danger" aria-hidden="true"><?php aq_the_icon('alert-triangle'); ?></span>
      <h2 id="modal-excluir-titulo">Excluir equipamento</h2>
      <button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-modal-close="modal-excluir" aria-label="Fechar">
        <?php aq_the_icon('close'); ?>
      </button>
    </div>
    <p>Excluir <strong data-field="delete.name">—</strong>?</p>
    <p class="aq-card__sub" style="margin-top:8px">O cadastro é apagado. As leituras que ele já enviou continuam no
    histórico da represa, sem equipamento associado.</p>
    <div class="aq-modal__foot">
      <button class="aq-btn aq-btn--ghost" type="button" data-modal-close="modal-excluir">Cancelar</button>
      <button class="aq-btn aq-btn--danger" type="button" data-confirm-delete>Excluir</button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php aq_page_end(['scripts' => ['pages/equipment.js']]);
