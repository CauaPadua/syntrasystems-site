/** Aquapulse — Monitoramento / Situação operacional. */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;

  var ICONS = {
    signal: '<path d="M4.9 4.9a10 10 0 0 0 0 14.2"/><path d="M19.1 4.9a10 10 0 0 1 0 14.2"/><path d="M8 8a5.5 5.5 0 0 0 0 8"/><path d="M16 8a5.5 5.5 0 0 1 0 8"/><circle cx="12" cy="12" r="1.6"/>',
    gate: '<path d="M3.5 20.5V7l8.5-3.5L20.5 7v13.5"/><path d="M3.5 11h17"/><path d="M3.5 15.5h17"/><path d="M8.5 8.7v11.8"/><path d="M15.5 8.7v11.8"/>',
    'cloud-rain': '<path d="M6.5 15.5a4 4 0 0 1 .6-8 5.5 5.5 0 0 1 10.5 1.6 3.5 3.5 0 0 1-.6 6.4"/><path d="M8.5 18v2.5"/><path d="M12 18.5v2.5"/>',
    zap: '<path d="M13 2 4.5 13H11l-1 9 8.5-11H12l1-9Z"/>',
    radio: '<circle cx="12" cy="12" r="1.8"/><path d="M8.5 8.5a5 5 0 0 0 0 7"/><path d="M15.5 15.5a5 5 0 0 0 0-7"/><path d="M5.8 5.8a9 9 0 0 0 0 12.4"/><path d="M18.2 18.2a9 9 0 0 0 0-12.4"/>',
    wrench: '<path d="M15.5 3.5a5 5 0 0 0-4.6 7l-7 7 2.6 2.6 7-7a5 5 0 0 0 6.4-6.4l-3 3-2.6-2.6 3-3a5 5 0 0 0-1.8-.6Z"/>'
  };

  function icon(name, size) {
    return '<svg class="aq-icon" style="width:' + (size || 20) + 'px;height:' + (size || 20) + 'px" viewBox="0 0 24 24"'
      + ' fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
      + ' aria-hidden="true">' + (ICONS[name] || ICONS.signal) + '</svg>';
  }

  /* Chamados abertos em modo demonstrativo: vivem só nesta sessão. */
  var DEMO_KEY = 'aq.demo.tickets';

  function readTickets() {
    try { return JSON.parse(sessionStorage.getItem(DEMO_KEY) || '[]'); } catch (e) { return []; }
  }
  function saveTicket(t) {
    try {
      var list = readTickets();
      list.push(t);
      sessionStorage.setItem(DEMO_KEY, JSON.stringify(list));
    } catch (e) { /* armazenamento indisponível: segue sem registrar */ }
  }

  var page = window.AqMonitorPage({
    periodId: 'filtro-sistemas',
    periodParam: null,
    scopes: ['availability'],
    fetch: function (p) { return window.AqApi.operation({ reservoir_id: p.reservoir_id }); },

    render: function (d) {
      var k = d.kpis;

      S.fill({
        'general.value': { html: '<span style="color:var(--aq-success)">' + k.general.status.label + '</span>' },
        'general.foot': k.general.note,
        'sensors.value': { html: k.sensors.online + ' <span class="aq-kpi__unit">de</span> ' + k.sensors.total },
        'sensors.foot': k.sensors.note,
        'gates.value': { html: k.gates.online + ' <span class="aq-kpi__unit">de</span> ' + k.gates.total },
        'gates.foot': k.gates.note,
        'alerts.value': { html: k.alerts.count + ' <span class="aq-kpi__unit" style="color:var(--aq-warning)">' + k.alerts.label + '</span>' },
        'alerts.foot': k.alerts.note
      });

      /* --------------------------------------- visão geral dos sistemas */
      document.querySelector('[data-systems]').innerHTML = d.systems.map(function (s) {
        var tone = s.status === 'attention' ? 'warning' : 'success';
        return '<div style="border:1.6px solid var(--aq-' + tone + ');border-radius:12px;padding:14px;'
          + 'display:flex;align-items:center;gap:11px;background:var(--aq-' + tone + '-soft)">'
          + '<span style="color:var(--aq-' + tone + ')" aria-hidden="true">' + icon(s.icon, 22) + '</span>'
          + '<div><strong style="display:block;font-size:.88rem">' + S.esc(s.label) + '</strong>'
          + '<span style="font-size:.83rem;color:var(--aq-text-secondary)">' + S.esc(s.value) + '</span></div></div>';
      }).join('');

      /* ---------------------------------------------- disponibilidade */
      var a = d.availability;
      G.donut('grafico-disponibilidade', {
        labels: ['Disponível', 'Indisponível'],
        values: [a.general, 100 - a.general],
        colors: [G.colors.success, '#e2eaf4'],
        cutout: '74%',
        unit: '%',
        decimals: 1,
        center: [
          { text: F.pct(a.general), size: 27, color: '#16a34a' },
          { text: 'Disponibilidade', size: 11, color: '#536b96', weight: '600' },
          { text: 'geral', size: 11, color: '#536b96', weight: '600' }
        ]
      });

      document.querySelector('[data-availability]').innerHTML = a.items.map(function (i) {
        return '<li>'
          + '<div style="display:flex;align-items:center;gap:11px;margin-bottom:6px">'
          + '<span style="width:34px;height:34px;flex:none;display:inline-flex;align-items:center;justify-content:center;'
          + 'border-radius:9px;background:var(--aq-success-soft);color:var(--aq-success)" aria-hidden="true">'
          + icon(i.icon, 18) + '</span>'
          + '<span style="flex:1 1 auto;font-weight:600;font-size:.88rem">' + S.esc(i.label) + '</span>'
          + '<strong>' + F.int(i.pct) + '%</strong></div>'
          + '<div class="aq-bar__track"><div class="aq-bar__fill aq-bar__fill--normal" style="width:' + i.pct + '%"></div></div>'
          + '<p style="font-size:.78rem;color:var(--aq-text-secondary);margin-top:5px">' + S.esc(i.note) + '</p></li>';
      }).join('');

      /* -------------------------------------------------- componentes */
      document.querySelector('[data-components]').innerHTML = d.components.map(function (c) {
        return '<tr><td>' + S.esc(c.name) + '</td>'
          + '<td><span class="aq-status-text"><span class="aq-dot aq-dot--normal"></span>Normal</span></td>'
          + '<td>' + S.esc(c.at) + '</td></tr>';
      }).join('');

      /* ------------------------------------------------------ eventos */
      document.querySelector('[data-events]').innerHTML = d.events.map(function (e) {
        var st = e.status === 'resolved' ? 'normal' : (e.status === 'new' ? 'info' : 'attention');
        return '<tr><td>' + S.esc(e.at) + '</td>'
          + '<td>' + S.esc(e.component) + '</td>'
          + '<td>' + S.esc(e.event) + '</td>'
          + '<td>' + S.badge(e.priority_label, e.priority) + '</td>'
          + '<td><span class="aq-status-text"><span class="aq-dot aq-dot--' + st + '"></span>'
          + S.esc(e.status_label) + '</span></td></tr>';
      }).join('');

      /* ------------------------------------------------- manutenções */
      var extras = readTickets().map(function (t) {
        return { date: t.date, equipment: t.equipment, type: 'Chamado (demo)', priority: t.priority,
                 priority_label: t.priority === 'critical' ? 'Crítica' : (t.priority === 'attention' ? 'Atenção' : 'Baixa') };
      });

      document.querySelector('[data-maintenances]').innerHTML = d.maintenances.concat(extras).map(function (m) {
        return '<tr><td class="is-nowrap">' + S.esc(m.date) + '</td>'
          + '<td>' + S.esc(m.equipment) + '</td>'
          + '<td>' + S.esc(m.type) + '</td>'
          + '<td>' + S.badge(m.priority_label, m.priority === 'attention' ? 'attention' : (m.priority === 'critical' ? 'critical' : 'neutral')) + '</td></tr>';
      }).join('');

      // alimenta o seletor do modal com os equipamentos conhecidos
      var sel = document.querySelector('[data-chamado-equipamentos]');
      if (sel) {
        sel.innerHTML = d.components.map(function (c) {
          return '<option value="' + S.esc(c.name) + '">' + S.esc(c.name) + '</option>';
        }).join('');
      }
    }
  });

  /* --------------------------- abertura de chamado (modo demonstrativo) */
  var form = document.getElementById('form-chamado');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      saveTicket({
        equipment: document.getElementById('chamado-equipamento').value,
        priority: document.getElementById('chamado-prioridade').value,
        description: document.getElementById('chamado-descricao').value,
        date: new Date().toLocaleDateString('pt-BR')
      });

      S.closeModal('modal-chamado');
      S.notify('Chamado registrado', 'Registro demonstrativo mantido apenas nesta sessão.', 'info');
      form.reset();
      page.reload();
    });
  }
})();
