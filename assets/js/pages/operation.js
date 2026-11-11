/** Aquapulse — Monitoramento / Situação operacional. */
/*
 * Página: dashboard/monitoramento/operacional.php
 * API: GET api/v1/monitoring/operation.php (dados da tela)
 *      POST api/v1/monitoring/operation.php (abrir chamado — grava em maintenances)
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;
  var Api = window.AqApi;
  var Ctx = window.AqContext;

  /** Cores por situação de comunicação e por status geral. */
  var DOT = { online: 'normal', offline: 'offline', maintenance: 'attention', inactive: 'offline' };
  var TONE = { normal: 'success', attention: 'warning', critical: 'danger' };

  var page = window.AqMonitorPage({
    scopes: ['availability'],
    fetch: function (p) { return Api.operation({ reservoir_id: p.reservoir_id }); },

    render: function (d) {
      var k = d.kpis;

      S.fill({
        'general.value': { html: '<span style="color:var(--aq-' + TONE[k.general.status.key] + ')">' + S.esc(k.general.status.label) + '</span>' },
        'general.foot': k.general.note,
        'sensors.value': { html: k.sensors.online + ' <span class="aq-kpi__unit">de</span> ' + k.sensors.total },
        'sensors.foot': k.sensors.note,
        'last.value': k.last.value,
        'last.foot': k.last.note,
        'alerts.value': { html: '<span style="color:var(--aq-' + TONE[k.alerts.status] + ')">' + k.alerts.count + '</span>' },
        'alerts.foot': k.alerts.note
      });

      /* ----------------------------------- equipamentos por grandeza */
      document.querySelector('[data-systems]').innerHTML = d.systems.map(function (s) {
        var tone = s.status === 'normal' ? 'success' : 'warning';
        return '<div style="border:1.6px solid var(--aq-' + tone + ');border-radius:12px;padding:14px;'
          + 'background:var(--aq-' + tone + '-soft)">'
          + '<strong style="display:block;font-size:.88rem">' + S.esc(s.label) + '</strong>'
          + '<span style="font-size:.83rem;color:var(--aq-text-secondary)">' + S.esc(s.value) + '</span></div>';
      }).join('') || '<p class="aq-card__sub">Esta represa não possui equipamentos cadastrados.</p>';

      /* ------------------------------------------------ comunicação */
      var a = d.availability;
      G.donut('grafico-disponibilidade', {
        labels: ['Online', 'Sem comunicação'],
        values: [a.general, 100 - a.general],
        colors: [G.colors.success, '#e2eaf4'],
        cutout: '74%',
        unit: '%',
        decimals: 1,
        center: [
          { text: F.pct(a.general), size: 27, color: a.general >= 100 ? '#16a34a' : '#b45309' },
          { text: 'dos equipamentos', size: 11, color: '#536b96', weight: '600' },
          { text: 'online', size: 11, color: '#536b96', weight: '600' }
        ]
      });

      document.querySelector('[data-availability]').innerHTML = a.items.map(function (i) {
        return '<li>'
          + '<div style="display:flex;align-items:center;gap:11px;margin-bottom:6px">'
          + '<span style="flex:1 1 auto;font-weight:600;font-size:.88rem">' + S.esc(i.label) + '</span>'
          + '<strong>' + F.int(i.pct) + '%</strong></div>'
          + '<div class="aq-bar__track"><div class="aq-bar__fill aq-bar__fill--' + (i.pct >= 100 ? 'normal' : 'attention') + '" style="width:' + i.pct + '%"></div></div>'
          + '<p style="font-size:.78rem;color:var(--aq-text-secondary);margin-top:5px">' + S.esc(i.note) + '</p></li>';
      }).join('');

      /* -------------------------------------- equipamentos da represa */
      document.querySelector('[data-components]').innerHTML = d.components.map(function (c) {
        return '<tr><td>' + S.esc(c.name) + '</td>'
          + '<td>' + S.esc(c.type_label) + '</td>'
          + '<td><span class="aq-status-text"><span class="aq-dot aq-dot--' + (DOT[c.status] || 'offline') + '"></span>'
          + S.esc(c.status_label) + '</span></td>'
          + '<td class="is-nowrap">' + S.esc(c.at) + '</td></tr>';
      }).join('') || '<tr><td colspan="4">Nenhum equipamento cadastrado.</td></tr>';

      /* ----------------------------------------- manutenções e chamados */
      document.querySelector('[data-maintenances]').innerHTML = d.maintenances.map(function (m) {
        var key = m.priority === 'critical' ? 'critical' : (m.priority === 'attention' ? 'attention' : 'neutral');
        return '<tr title="' + S.esc(m.description) + '"><td class="is-nowrap">' + S.esc(m.date) + '</td>'
          + '<td>' + S.esc(m.equipment) + '</td>'
          + '<td>' + S.esc(m.type) + '</td>'
          + '<td>' + S.badge(S.esc(m.priority_label), key) + '</td></tr>';
      }).join('') || '<tr><td colspan="4">Nenhuma manutenção em aberto.</td></tr>';

      // O formulário de chamado oferece os equipamentos desta represa. A escolha
      // feita pelo usuário é preservada quando a tela se atualiza sozinha.
      var select = document.getElementById('chamado-equipamento');
      var escolhido = select.value;
      select.innerHTML = d.components.map(function (c) {
        return '<option value="' + S.esc(c.id) + '">' + S.esc(c.name) + '</option>';
      }).join('');
      if (escolhido) select.value = escolhido;
    }
  });

  /* ------------------------------------------------ abertura de chamado */
  var form = document.getElementById('form-chamado');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    S.showErrors(form, {});

    Api.openTicket({
      reservoir_id: Ctx.get().reservoir_id,
      equipment_id: form.elements.equipment_id.value,
      priority: form.elements.priority.value,
      description: form.elements.description.value
    }).then(function () {
      S.closeModal('modal-chamado');
      S.notify('Chamado registrado', 'O chamado foi gravado e já aparece na lista de manutenções.', 'info');
      form.reset();
      page.reload();
    }).catch(function (err) {
      if (err.code === 'VALIDATION_ERROR') {
        S.showErrors(form, err.details);
      } else {
        S.notify('Não foi possível registrar', err.message, 'error');
      }
    });
  });
})();
