/** Aquapulse — Monitoramento / Previsão de duração da água. */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;

  var SCEN_ICONS = {
    leaf: '<path d="M20 4c0 9-5.2 13.5-11 13.5A5 5 0 0 1 4 12.5C4 6.8 9.5 4 20 4Z"/><path d="M4.5 20C7 15 11 11.5 16 9.5"/>',
    waves: '<path d="M2 7.5c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/><path d="M2 13c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/>',
    'chart-up': '<path d="M3 21h18"/><path d="M11 21V9"/><path d="M16 21v-9"/><path d="M14 4h6v6"/><path d="M20 4l-7.5 7.5"/>',
    clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 1.8"/>',
    'cloud-rain': '<path d="M6.5 15.5a4 4 0 0 1 .6-8 5.5 5.5 0 0 1 10.5 1.6 3.5 3.5 0 0 1-.6 6.4"/><path d="M8.5 18v2.5"/><path d="M12 18.5v2.5"/>'
  };

  function icon(name, size) {
    return '<svg class="aq-icon" style="width:' + (size || 20) + 'px;height:' + (size || 20) + 'px" viewBox="0 0 24 24"'
      + ' fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
      + ' aria-hidden="true">' + (SCEN_ICONS[name] || SCEN_ICONS.waves) + '</svg>';
  }

  window.AqMonitorPage({
    periodId: 'filtro-horizonte',
    periodParam: 'horizon',
    scopes: ['projection', 'estimate'],
    fetch: function (p) { return window.AqApi.duration(p); },

    render: function (d) {
      var k = d.kpis;

      S.fill({
        'duration.value': F.int(k.duration.value), 'duration.foot': k.duration.note,
        'useful.value': F.int(k.useful.value), 'useful.foot': k.useful.note,
        'consumption.value': F.num(k.consumption.value, 1), 'consumption.foot': k.consumption.note,
        'reliability.value': F.int(k.reliability.value), 'reliability.foot': k.reliability.note,
        'estimate.date': d.estimate.date,
        'estimate.badge': { html: S.badge(d.estimate.badge, 'normal') },
        'estimate.note': d.estimate.note,
        'insight.text': d.insight.text,
        'insight.gain': F.signed(d.insight.gain, 0)
      });

      /* ------------------- projeção com capacidade e reserva técnica */
      var p = d.projection;
      var ctx = document.getElementById('grafico-projecao').getContext('2d');

      G.create('grafico-projecao', {
        type: 'line',
        data: {
          labels: p.labels,
          datasets: [
            G.line('Consumo atual', p.current, G.colors.primary, { fillCtx: ctx, alpha: 0.13, points: false }),
            G.line('Consumo elevado (+20%)', p.high, G.colors.warning, { dashed: true, points: false, width: 1.9 }),
            G.line('Economia de 10%', p.saving, G.colors.success, { dashed: true, points: false, width: 1.9 })
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ max: 1600, decimals: 0 }),
          plugins: G.plugins('hm³', 0, {
            annotation: {
              annotations: {
                capacidade: G.limitLine(p.capacity, G.colors.danger, 'Capacidade máxima (' + F.int(p.capacity) + ' hm³)', 'end'),
                reserva: G.limitLine(p.reserve, G.colors.warning, 'Reserva técnica (' + F.int(p.reserve) + ' hm³)', 'end', true)
              }
            }
          })
        }
      });
      G.describe('grafico-projecao', p.current, 'hm³', 0);

      /* -------------------------------------- medidor da estimativa */
      G.gauge('medidor-duracao', {
        value: d.estimate.days,
        min: 0,
        max: d.estimate.max_days,
        color: G.colors.primary,
        cutout: '76%',
        center: [
          { text: String(d.estimate.days), size: 40 },
          { text: 'dias', size: 13, color: '#536b96', weight: '600' }
        ]
      });

      /* --------------------------------------------------- cenários */
      document.querySelector('[data-scenarios]').innerHTML = d.scenarios.map(function (s) {
        var tone = s.status === 'attention' ? 'warning' : (s.status === 'normal' ? 'success' : 'primary');
        var isBase = s.key === 'current';
        return '<article class="aq-card" style="text-align:center;'
          + (isBase ? 'border-color:var(--aq-primary);box-shadow:0 0 0 1px var(--aq-primary) inset' : '') + '">'
          + '<div class="aq-card__title" style="justify-content:center;font-size:.95rem">' + S.esc(s.label)
          + '</div>'
          + '<span style="width:56px;height:56px;margin:14px auto 10px;display:inline-flex;align-items:center;'
          + 'justify-content:center;border-radius:50%;background:var(--aq-' + tone + '-soft);color:var(--aq-' + tone + ')"'
          + ' aria-hidden="true">' + icon(s.icon, 24) + '</span>'
          + '<p class="aq-kpi__value" style="justify-content:center;font-size:1.9rem">'
          + F.int(s.days) + '<span class="aq-kpi__unit">dias</span></p>'
          + '<p class="aq-card__sub" style="margin:6px 0 12px">' + S.esc(s.note) + '</p>'
          + S.badge(s.badge, s.status === 'info' ? 'info' : s.status)
          + '</article>';
      }).join('');

      /* ---------------------------------------- fatores considerados */
      document.querySelector('[data-factors]').innerHTML = d.factors.map(function (f) {
        return '<div class="aq-list__item">'
          + '<span style="color:var(--aq-primary)" aria-hidden="true">' + icon(f.icon, 20) + '</span>'
          + '<div class="aq-list__body"><p class="aq-list__meta" style="margin:0">' + S.esc(f.label) + '</p></div>'
          + '<div class="aq-list__side"><strong style="color:var(--aq-text)">' + S.esc(f.value) + '</strong></div></div>';
      }).join('');

      /* ------------------------------------ histórico das estimativas */
      document.querySelector('[data-estimates]').innerHTML = d.history.map(function (h) {
        return '<tr>'
          + '<td class="is-nowrap">' + S.esc(h.date) + '</td>'
          + '<td class="is-num">' + F.int(h.estimate) + '</td>'
          + '<td class="is-num">' + S.esc(h.variation) + '</td>'
          + '<td>' + S.esc(h.scenario) + '</td>'
          + '<td class="is-num"><span class="aq-status-text" style="justify-content:flex-end">'
          + '<span class="aq-dot aq-dot--normal"></span>' + h.confidence + '%</span></td>'
          + '</tr>';
      }).join('');
    }
  });
})();
