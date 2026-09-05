/** Aquapulse — Monitoramento / Volume armazenado. */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;

  window.AqMonitorPage({
    scopes: ['evolution', 'occupancy'],
    fetch: function (p) { return window.AqApi.storage(p); },

    render: function (d) {
      var k = d.kpis;

      S.fill({
        'volume.value': F.int(k.volume.value), 'volume.foot': k.volume.note,
        'capacity.value': F.int(k.capacity.value), 'capacity.foot': k.capacity.note,
        'occupancy.value': F.num(k.occupancy.value, 1), 'occupancy.foot': k.occupancy.note,
        'available.value': F.int(k.available.value), 'available.foot': k.available.note,
        'occupancy.total': 'Capacidade total: ' + F.int(d.occupancy.capacity) + ' hm³',
        'distribution.total': F.int(k.capacity.value) + ' hm³  ·  100%',
        'insight.value': F.signed(d.insight.value, 0),
        'insight.badge': { html: S.badge(F.signed(d.insight.pct, 1) + '%', 'normal') }
      });

      /* ------------------- evolução com linha de capacidade máxima */
      var e = d.evolution;
      var ctx = document.getElementById('grafico-volume').getContext('2d');

      G.create('grafico-volume', {
        type: 'line',
        data: {
          labels: e.labels,
          datasets: [G.line('Volume armazenado', e.values, G.colors.primary, { fillCtx: ctx, alpha: 0.18 })]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ max: 1800, decimals: 0 }),
          plugins: G.plugins('hm³', 0, {
            annotation: {
              annotations: {
                capacidade: G.limitLine(
                  e.capacity, G.colors.danger,
                  'Capacidade máxima (' + F.int(e.capacity) + ' hm³)', 'end'
                )
              }
            }
          })
        }
      });
      G.describe('grafico-volume', e.values, 'hm³', 0);

      /* ------------------------------- indicador circular de ocupação */
      var o = d.occupancy;
      G.gauge('medidor-ocupacao', {
        value: o.pct,
        min: 0,
        max: 100,
        color: G.colors.primary,
        cutout: '76%',
        center: [
          { text: F.pct(o.pct), size: 30 },
          { text: 'Ocupação atual', size: 12, color: '#536b96', weight: '600' }
        ]
      });

      document.querySelector('[data-occupancy-legend]').innerHTML = [
        { label: 'Armazenado', v: o.stored, p: o.pct, color: 'var(--aq-primary)' },
        { label: 'Disponível', v: o.available, p: o.available_pct, color: '#c7dcfb' }
      ].map(function (i) {
        return '<li style="display:flex;align-items:center;gap:10px;font-size:.87rem">'
          + '<span style="width:11px;height:11px;border-radius:50%;background:' + i.color + '"></span>'
          + '<span style="flex:1 1 auto">' + i.label + '</span>'
          + '<strong>' + F.int(i.v) + ' hm³</strong>'
          + '<span style="color:var(--aq-text-secondary);min-width:52px;text-align:right">' + F.pct(i.p) + '</span></li>';
      }).join('');

      /* ------------------------------------------- balanço hídrico */
      var b = d.balance;
      G.create('grafico-balanco', {
        type: 'bar',
        data: {
          labels: b.labels,
          datasets: [
            G.bar('Entrada (hm³)', b.inflow, G.colors.primary, { maxThickness: 14 }),
            G.bar('Saída (hm³)', b.outflow, '#b6d3fe', { maxThickness: 14 })
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            x: { stacked: false, grid: { display: false }, ticks: { color: G.colors.axis, maxRotation: 0 }, border: { display: false } },
            y: { min: -150, max: 150, grid: { color: G.colors.grid }, border: { display: false }, ticks: { color: G.colors.axis } }
          },
          plugins: G.plugins('hm³', 0)
        }
      });

      /* --------------------------------- distribuição da capacidade */
      document.querySelector('[data-distribution]').innerHTML = d.distribution.map(function (i) {
        return '<div class="aq-list__item">'
          + '<span class="aq-list__icon aq-list__icon--' + i.status + '" aria-hidden="true">'
          + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
          + ' stroke-linecap="round" stroke-linejoin="round">'
          + '<path d="M2 7.5c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/>'
          + '<path d="M2 13c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/></svg></span>'
          + '<div class="aq-list__body"><p class="aq-list__title">' + S.esc(i.label) + '</p>'
          + '<p class="aq-list__meta">' + S.esc(i.note) + '</p></div>'
          + '<div class="aq-list__side" style="flex-direction:column;align-items:flex-end;gap:2px">'
          + '<strong style="color:var(--aq-text)">' + F.int(i.value) + ' hm³</strong>'
          + '<span>' + F.pct(i.pct) + '</span></div></div>';
      }).join('');

      /* ------------------------------------------- histórico de volume */
      document.querySelector('[data-history]').innerHTML = d.history.map(function (r) {
        return '<tr>'
          + '<td class="is-nowrap">' + S.esc(r.date) + '</td>'
          + '<td class="is-num">' + F.int(r.volume) + '</td>'
          + '<td class="is-num">' + F.num(r.occupancy, 1) + '</td>'
          + '<td class="is-num">' + F.signed(r.variation, 0) + '</td>'
          + '<td><span class="aq-status-text"><span class="aq-dot aq-dot--normal"></span>Normal</span></td>'
          + '</tr>';
      }).join('');
    }
  });
})();
