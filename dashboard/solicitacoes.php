<?php
/** Aquapulse — Solicitações (só administrador). */
/*
 * Pedidos enviados pelo formulário público (solicitar.php):
 *   Acesso         colaborador de uma represa: aprovar cria a conta, com o cargo
 *                  e as represas escolhidas aqui;
 *   Demonstração   dono de uma represa: o administrador entra em contato, cadastra a
 *                  empresa e a represa (o botão leva os dados do pedido para
 *                  Configurações) e aprova: a conta de gestor nasce com a senha
 *                  que o dono escolheu no pedido.
 *
 * Lógica em assets/js/pages/requests.js, que usa api/v1/requests.php. A API
 * confere de novo se quem responde é administrador.
 */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

use Aquapulse\Support\Guard;

if (!Guard::can($AQ_USER, 'requests.manage')) {              // quem não é administrador volta para a visão geral
    header('Location: index.php', true, 302);
    exit;
}

aq_page_start([
    'route'    => 'requests',
    'title'    => 'Solicitações',
    'subtitle' => 'Pedidos de acesso da equipe e contatos de donos de represas',
]);
?>

<div class="aq-grid aq-grid--4">
  <?php
  echo aq_kpi(['id' => 'pending', 'label' => 'Esperando resposta', 'icon' => 'inbox', 'tone' => 'warning', 'tip' => 'Pedidos ainda não respondidos.']);
  echo aq_kpi(['id' => 'access', 'label' => 'De acesso', 'icon' => 'user', 'tip' => 'Colaboradores pedindo acesso a uma represa.']);
  echo aq_kpi(['id' => 'demo', 'label' => 'De demonstração', 'icon' => 'building', 'tip' => 'Donos de represas pedindo contato.']);
  echo aq_kpi(['id' => 'decided', 'label' => 'Já respondidas', 'icon' => 'check-circle', 'tone' => 'success', 'tip' => 'Aprovadas, atendidas ou recusadas.']);
  ?>
</div>

<section class="aq-context" aria-label="Filtros de solicitações">
  <?php
  echo aq_select(['id' => 'filtro-situacao', 'label' => 'Situação', 'options' => [
      'pending' => 'Esperando resposta', 'approved' => 'Aprovadas ou atendidas', 'rejected' => 'Recusadas', 'all' => 'Todas',
  ]]);
  echo aq_select(['id' => 'filtro-tipo', 'label' => 'Tipo', 'options' => [
      'all' => 'Todos', 'access' => 'Acesso', 'demo' => 'Demonstração',
  ]]);
  ?>
</section>

<article class="aq-card">
  <?php echo aq_card_head(['title' => 'Pedidos recebidos', 'tip' => 'Os que esperam há mais tempo aparecem primeiro.']); ?>
  <div data-content="requests" hidden>
    <?php echo aq_table_open('Pedidos recebidos'); ?>
    <table class="aq-table">
      <thead>
        <tr>
          <th scope="col">Nº</th>
          <th scope="col">Tipo</th>
          <th scope="col">Nome</th>
          <th scope="col">Empresa e represa</th>
          <th scope="col">Recebido em</th>
          <th scope="col">Situação</th>
          <th scope="col"><span class="aq-visually-hidden">Abrir</span></th>
        </tr>
      </thead>
      <tbody data-rows></tbody>
    </table>
    <?php echo aq_table_close(); ?>
  </div>
  <?php echo aq_states('requests', 'Nenhum pedido', 'Não há pedidos com a situação e o tipo selecionados.'); ?>
</article>

<div class="aq-note">
  <?php aq_the_icon('info'); ?>
  <span><strong>Antes de aprovar.</strong> O e-mail do pedido não é verificado: confirme por telefone, com a empresa ou
  com o gestor informado, que a pessoa trabalha na represa. A conta criada só enxerga as represas marcadas.</span>
</div>

<!-- --------------------------------------------------- modal: pedido -->
<div class="aq-modal" id="modal-pedido" role="dialog" aria-modal="true" aria-labelledby="modal-pedido-titulo" hidden>
  <div class="aq-modal__box aq-modal__box--wide">
    <div class="aq-modal__head">
      <span class="aq-kpi__icon" aria-hidden="true"><?php aq_the_icon('inbox'); ?></span>
      <h2 id="modal-pedido-titulo" data-field="request.title">—</h2>
      <button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-modal-close="modal-pedido" aria-label="Fechar">
        <?php aq_the_icon('close'); ?>
      </button>
    </div>

    <div data-request-details></div> <?php /* dados enviados pela pessoa, montados por requests.js */ ?>

    <p class="aq-note aq-note--warning" style="margin-top:14px" data-request-warning hidden></p>

    <form id="form-pedido" novalidate> <?php /* enviado por requests.js; a validação é feita no servidor */ ?>
      <!-- dono de represa: primeiro a empresa e a represa precisam existir -->
      <div class="aq-note" style="margin-top:16px" data-when="demo" hidden>
        <?php aq_the_icon('info'); ?>
        <span><strong>Dono de represa:</strong> depois do contato, cadastre a empresa e a represa dele; em seguida
        marque a represa abaixo e aprove. A conta de gestor é criada com o e-mail e a senha deste pedido.
        <span data-demo-missing hidden><br><strong>A represa deste pedido ainda não está cadastrada.</strong></span>
        <button class="aq-btn aq-btn--ghost aq-btn--sm" style="margin-top:8px" type="button" data-action="register">
          <?php aq_the_icon('plus'); ?><span>Cadastrar empresa e represa deste pedido</span>
        </button></span>
      </div>

      <!-- pedido antigo de demonstração, de antes de o formulário pedir senha -->
      <p class="aq-note" style="margin-top:16px" data-when="legacy" hidden>
        <?php aq_the_icon('info'); ?>
        <span>Este pedido chegou antes de o formulário pedir senha: crie a conta em
        <a href="configuracoes.php#usuarios">Configurações › Usuários</a>, informe a senha à pessoa e marque o pedido como atendido.</span>
      </p>

      <!-- pedido com senha, em análise: aprovar cria a conta -->
      <div data-when="approve" hidden>
        <div class="aq-field" style="margin:16px 0 14px">
          <label class="aq-field__label" for="pedido-cargo">Cargo da conta</label>
          <select class="aq-select" id="pedido-cargo" name="role" style="width:100%"></select>
          <span class="aq-field__error" data-error-for="role" hidden></span>
        </div>
        <fieldset class="aq-checklist">
          <legend class="aq-field__label">Represas que a pessoa vai ver</legend>
          <div data-reservoirs></div>
          <span class="aq-field__error" data-error-for="reservoirs" hidden></span>
        </fieldset>
      </div>

      <div class="aq-field" style="margin-top:14px" data-when="pending" hidden>
        <label class="aq-field__label" for="pedido-nota">Observação (obrigatória para recusar)</label>
        <textarea class="aq-input" id="pedido-nota" name="note" rows="2" maxlength="300"
                  style="height:auto;padding:10px 14px;width:100%" placeholder="Num pedido recusado, a pessoa lê este texto ao tentar entrar"></textarea>
        <span class="aq-field__error" data-error-for="note" hidden></span>
      </div>

      <div class="aq-modal__foot">
        <button class="aq-btn aq-btn--ghost" type="button" data-modal-close="modal-pedido">Fechar</button>
        <button class="aq-btn aq-btn--ghost" type="button" data-action="delete" data-when="decided" hidden>Excluir pedido</button>
        <button class="aq-btn aq-btn--danger" type="button" data-action="reject" data-when="pending" hidden>Recusar</button>
        <button class="aq-btn aq-btn--primary" type="button" data-action="approve" data-when="approve" hidden>
          <?php aq_the_icon('check-circle'); ?><span>Aprovar e criar conta</span>
        </button>
        <button class="aq-btn aq-btn--primary" type="button" data-action="conclude" data-when="legacy" hidden>
          <?php aq_the_icon('check-circle'); ?><span>Marcar como atendida</span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php aq_page_end(['scripts' => ['pages/requests.js']]);
