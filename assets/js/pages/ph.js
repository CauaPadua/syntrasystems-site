/** Aquapulse — Monitoramento / pH. */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;

  window.AqMonitorPage({
    scopes: ['variation', 'scale'],
    fetch: function (p) { return window.AqApi.ph(p); },

    render: function (d) {
      var k = d.kpis;

      // o pH não tem unidade: apenas o número, com vírgula na apresentação
      S.fill({
        'ph.value': F.num(k.ph.value, 1), 'ph.foot': k.ph.note,
        'min.value': F.num(k.min.value, 1), 'min.foot': k.min.note,
        'max.value': F.num(k.max.value, 1), 'max.foot': k.max.note,
        'condition.value': { html: '<span style="color:var(--aq-success)">' + k.condition.label + '</span>' },
        'condition.foot': k.condition.note,
        'scale.value': F.num(d.scale.value, 1),
        'scale.label': d.scale.label,
        'scale.range': { html: S.badge(d.scale.range, 'normal') }
      });

      /* ------------------ variação com faixa ideal translúcida */
      var v = d.variation;
      G.create('grafico-ph', {
        type: 'line',
        data: {
          labels: v.labels,
          datasets: [G.line('pH observado', v.values, G.colors.primary, { width: 2.2 })]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ min: 0, max: 10, decimals: 0 }),
          plugins: G.plugins('', 1, {
            annotation: {
              annotations: {
                ideal: G.band(v.ideal_min, v.ideal_max, G.colors.success, 'Faixa ideal\n6,5 – 8,5')
              }
            }
          })
        }
      });
      G.describe('grafico-ph', v.values, '', 1);

      /* -------------------- escala de pH de 0 a 14 (medidor segmentado) */
      var sc = d.scale;
      G.gauge('escala-ph', {
        value: sc.value,
        min: sc.min,
        max: sc.max,
        cutout: '68%',
        segments: [
          { size: 14.3, color: '#ef4444' },
          { size: 14.3, color: '#f59e0b' },
          { size: 14.3, color: '#fbbf24' },
          { size: 14.3, color: '#16a34a' },
          { size: 14.3, color: '#0ea5e9' },
          { size: 14.3, color: '#2563eb' },
          { size: 14.2, color: '#1e3a8a' }
        ]
      });

      // ponteiro: 0 -> -90°, 14 -> +90°
      var needle = document.querySelector('[data-ph-needle]');
      if (needle) {
        var angle = ((sc.value - sc.min) / (sc.max - sc.min)) * 180 - 90;
        needle.style.transform = 'translateX(-50%) rotate(' + angle + 'deg)';
      }

      /* ----------------------------------------- média diária 7 dias */
      var dl = d.daily;
      G.create('grafico-ph-diario', {
        type: 'bar',
        data: {
          labels: dl.labels,
          datasets: [G.bar('pH médio diário', dl.values, '#93c5fd')]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ min: 0, max: 10, decimals: 0 }),
          plugins: G.plugins('', 1, {
            annotation: {
              annotations: { ideal: G.band(dl.ideal_min, dl.ideal_max, G.colors.success) }
            }
          })
        }
      });

      /* ------------------------------------------ pontos de coleta */
      document.querySelector('[data-points]').innerHTML = d.points.map(function (p) {
        return '<tr>'
          + '<td><span class="aq-table__name"><span class="aq-table__icon">'
          + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
          + ' stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/>'
          + '<circle cx="12" cy="10" r="2.6"/></svg></span>' + S.esc(p.name) + '</span></td>'
          + '<td class="is-num">' + F.num(p.ph, 1) + '</td>'
          + '<td><span class="aq-status-text"><span class="aq-dot aq-dot--normal"></span>Normal</span></td>'
          + '</tr>';
      }).join('');

      /* --------------------------------------------- últimas leituras */
      document.querySelector('[data-readings]').innerHTML = d.readings.map(function (r) {
        return '<tr>'
          + '<td class="is-nowrap">' + S.esc(r.time) + '</td>'
          + '<td class="is-num">' + F.num(r.ph, 1) + '</td>'
          + '<td class="is-num">' + F.num(r.temp, 1) + '</td>'
          + '<td>' + S.esc(r.point) + '</td>'
          + '<td><span class="aq-status-text"><span class="aq-dot aq-dot--normal"></span>Normal</span></td>'
          + '</tr>';
      }).join('');
    }
  });
})();
