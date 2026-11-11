/** Aquapulse — Equipamentos (CRUD). */
/*
 * Página: dashboard/equipamentos.php
 * API: GET api/v1/equipment.php                       consultar
 *      POST api/v1/equipment.php { action: 'create' } cadastrar
 *      POST api/v1/equipment.php { action: 'update' } editar
 *      POST api/v1/equipment.php { action: 'delete' } excluir
 *
 * Os botões de cadastro só existem na página para administradores; a API
 * confere o perfil de novo a cada gravação.
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var Api = window.AqApi;
  var Ctx = window.AqContext;

  var selReservoir = document.getElementById('filtro-represa');
  var search = document.getElementById('busca-equipamento');
  var form = document.getElementById('form-equipamento');       // null para quem não é administrador

  var rows = [];          // equipamentos recebidos da API
  var canEdit = false;    // informado pela API (perfil de administrador)
  var editingId = null;   // código em edição; null = novo cadastro
  var deletingId = null;  // código aguardando confirmação de exclusão

  var DOT = { online: 'normal', offline: 'offline', maintenance: 'attention', inactive: 'offline' };
  var BADGE = { active: 'normal', maintenance: 'attention', inactive: 'neutral' };

  var ICON_EDIT = '<path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>';
  var ICON_TRASH = '<path d="M4 7h16"/><path d="M9 7V4.5h6V7"/><path d="M6.5 7l1 13h9l1-13"/>';

  function svg(path) {
    return '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
      + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
  }

  function options(items, value, label) {
    return items.map(function (i) {
      return '<option value="' + S.esc(i[value]) + '">' + S.esc(i[label]) + '</option>';
    }).join('');
  }

  /* --------------------------------------------------------- renderização */

  function applySearch(list) {
    var term = (search.value || '').trim().toLowerCase();
    if (!term) return list;
    return list.filter(function (e) {
      return (e.id + ' ' + e.name + ' ' + e.location + ' ' + e.type_label).toLowerCase().indexOf(term) >= 0;
    });
  }

  function renderRows() {
    var list = applySearch(rows);

    if (!list.length) {
      S.setState('equipment', 'empty');
      return;
    }
    S.setState('equipment', 'ready');

    document.querySelector('[data-rows]').innerHTML = list.map(function (e) {
      var leitura = e.last_value === null
        ? '<span class="aq-card__sub">Nenhuma leitura</span>'
        : '<strong>' + F.num(e.last_value, e.type === 'ph' ? 2 : 1) + ' ' + S.esc(e.unit) + '</strong>'
          + '<br><span class="aq-card__sub" title="' + S.esc(e.last_reading_time) + '">' + S.esc(e.last_reading_label) + '</span>';

      var acoes = canEdit
        ? '<td><div class="aq-table__actions">'
          + '<button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-edit="' + S.esc(e.id) + '" aria-label="Editar ' + S.esc(e.id) + '">' + svg(ICON_EDIT) + '</button>'
          + '<button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-delete="' + S.esc(e.id) + '" aria-label="Excluir ' + S.esc(e.id) + '">' + svg(ICON_TRASH) + '</button>'
          + '</div></td>'
        : '';

      return '<tr>'
        + '<td class="is-nowrap"><strong>' + S.esc(e.id) + '</strong></td>'
        + '<td>' + S.esc(e.name) + '</td>'
        + '<td>' + S.esc(e.type_label) + '</td>'
        + '<td>' + S.esc(e.reservoir_name.replace('Represa ', '')) + '</td>'
        + '<td>' + S.esc(e.location) + '</td>'
        + '<td>' + S.badge(S.esc(e.status_label), BADGE[e.status] || 'neutral') + '</td>'
        + '<td><span class="aq-status-text"><span class="aq-dot aq-dot--' + (DOT[e.communication] || 'offline') + '"></span>'
        + S.esc(e.communication_label) + '</span></td>'
        + '<td class="is-num">' + leitura + '</td>'
        + acoes
        + '</tr>';
    }).join('');

    S.fill({ 'list.count': list.length });
  }

  /** @param {boolean} silent true nas recargas automáticas: mantém a tabela visível */
  function load(silent) {
    if (silent !== true) S.setState('equipment', 'loading');

    return Api.equipment({ reservoir_id: Ctx.get().reservoir_id }).then(function (r) {
      var d = r.data;

      rows = d.equipment;
      canEdit = d.can_edit;

      S.fill({
        'total.value': F.int(d.summary.total), 'total.foot': 'Cadastrados',
        'online.value': F.int(d.summary.online), 'online.foot': 'Transmitindo agora',
        'offline.value': F.int(d.summary.offline), 'offline.foot': 'Ativos sem leitura recente',
        'unavailable.value': F.int(d.summary.unavailable), 'unavailable.foot': 'Manutenção ou desativados',
        'online.window': d.online_window_minutes
      });

      // filtro de represa e campos do formulário (só na primeira carga)
      if (selReservoir.options.length <= 1) {
        selReservoir.innerHTML = '<option value="all">Todas as represas</option>' + options(d.reservoirs, 'id', 'name');
        selReservoir.value = Ctx.reconcile(d.reservoirs).reservoir_id;
      }
      if (form && !form.elements.type.options.length) {
        form.elements.type.innerHTML = options(d.types, 'value', 'label');
        form.elements.status.innerHTML = options(d.statuses, 'value', 'label');
        form.elements.reservoir_id.innerHTML = options(d.reservoirs, 'id', 'name');
      }

      renderRows();
      S.setUpdated(r.meta);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('equipment', 'error', err.message);
    });
  }

  /* -------------------------------------------------- cadastrar e editar */

  /** Abre o formulário vazio (novo) ou preenchido com um equipamento (edição). */
  function openForm(e) {
    editingId = e ? e.id : null;
    S.showErrors(form, {});
    form.reset();

    document.querySelector('[data-form-title]').textContent = e ? 'Editar ' + e.id : 'Novo equipamento';
    form.elements.id.readOnly = !!e;                            // o código não muda: é a identificação usada nos envios

    if (e) {
      form.elements.id.value = e.id;
      form.elements.name.value = e.name;
      form.elements.location.value = e.location;
      form.elements.type.value = e.type;
      form.elements.status.value = e.status;
      form.elements.reservoir_id.value = e.reservoir_id;
    } else if (Ctx.get().reservoir_id !== 'all') {
      form.elements.reservoir_id.value = Ctx.get().reservoir_id;
    }

    S.openModal('modal-equipamento');
  }

  if (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      S.showErrors(form, {});

      var acao = editingId ? 'update' : 'create';

      Api.saveEquipment(acao, {
        id: form.elements.id.value,
        name: form.elements.name.value,
        location: form.elements.location.value,
        type: form.elements.type.value,
        status: form.elements.status.value,
        reservoir_id: form.elements.reservoir_id.value
      }).then(function (r) {
        S.closeModal('modal-equipamento');
        S.notify(acao === 'create' ? 'Equipamento cadastrado' : 'Equipamento atualizado', r.data.equipment.id + ' foi gravado no banco.', 'info');
        return load(true);
      }).catch(function (err) {
        if (err.code === 'VALIDATION_ERROR') {
          S.showErrors(form, err.details);                      // mensagens abaixo de cada campo
        } else {
          S.notify('Não foi possível salvar', err.message, 'error');
        }
      });
    });

    document.querySelector('[data-new]').addEventListener('click', function () { openForm(null); });

    document.querySelector('[data-confirm-delete]').addEventListener('click', function () {
      if (!deletingId) return;

      Api.saveEquipment('delete', { id: deletingId }).then(function () {
        S.closeModal('modal-excluir');
        S.notify('Equipamento excluído', deletingId + ' foi removido do cadastro.', 'info');
        deletingId = null;
        return load(true);
      }).catch(function (err) {
        S.closeModal('modal-excluir');
        S.notify('Não foi possível excluir', err.message, 'error');
      });
    });
  }

  /* ----------------------------------------------------------- eventos */

  document.querySelector('[data-rows]').addEventListener('click', function (ev) {
    var editar = ev.target.closest('[data-edit]');
    var excluir = ev.target.closest('[data-delete]');
    var codigo = (editar || excluir || { getAttribute: function () { return null; } }).getAttribute(editar ? 'data-edit' : 'data-delete');
    var item = rows.filter(function (e) { return e.id === codigo; })[0];
    if (!item) return;

    if (editar) {
      openForm(item);
    } else {
      deletingId = item.id;
      S.fill({ 'delete.name': item.id + ' — ' + item.name });
      S.openModal('modal-excluir');
    }
  });

  selReservoir.addEventListener('change', function () {
    Ctx.set({ reservoir_id: selReservoir.value });
    load();
  });

  search.addEventListener('input', renderRows);

  S.onRetry('equipment', function () { load(); });
  S.onReload(function () { return load(true); });

  load();
})();
