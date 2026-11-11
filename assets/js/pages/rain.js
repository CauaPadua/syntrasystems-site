/** Aquapulse — Monitoramento / Precipitação. */
/*
 * Página: dashboard/monitoramento/precipitacao.php | API: GET api/v1/monitoring/precipitation.php.
 * Represa, período, estados e recarga ficam em AqMonitorPage; aqui só o render().
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;

  /** Cor do texto conforme a classificação (normal, attention, critical). */
  function cor(status) {
    return 'var(--aq-' + (status === 'critical' ? 'danger' : (status === 'attention' ? 'warning' : 'success')) + ')';
  }

  window.AqMonitorPage({
    scopes: ['chart', 'current'],
    fetch: function (p) { return window.AqApi.rain(p); },

    render: function (d) {
      var k = d.kpis;

      S.fill({
        'rain_24h.value': F.num(k.rain_24h.value, 1), 'rain_24h.foot': k.rain_24h.note,
        'rain_7d.value': F.num(k.rain_7d.value, 1), 'rain_7d.foot': k.rain_7d.note,
        'rain_month.value': F.num(k.rain_month.value, 1), 'rain_month.foot': k.rain_month.note,
        'intensity.value': { html: '<span style="color:' + cor(k.intensity.status.key) + '">' + S.esc(k.intensity.label) + '</span>' },
        'intensity.foot': k.intensity.note,
        'current.value': F.num(d.current.value, 1),
        'current.label': { html: '<span style="color:' + cor(d.current.status) + '">' + S.esc(d.current.label) + '</span>' },
        'current.last': d.current.last
      });

      /* -------------- barras diárias + linha de acumulado (dois eixos) */
      var c = d.chart;
      G.create('grafico-chuva', {
        type: 'bar',
        data: {
          labels: c.labels,
          datasets: [
            Object.assign(G.bar('Precipitação diária (mm)', c.daily, G.colors.primary, { maxThickness: 34 }), { order: 2, yAxisID: 'y' }),
            Object.assign(
              G.line('Acumulado (mm)', c.accumulated, G.colors.primary, { width: 2, points: c.labels.length <= 10, tension: 0.2 }),
              { type: 'line', order: 1, yAxisID: 'y1' }
            )
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: { padding: { top: 20 } },
          scales: {
            x: { grid: { display: false }, ticks: { color: G.colors.axis, maxRotation: 0, autoSkipPadding: 12 }, border: { display: false } },
            y: {
              position: 'left', beginAtZero: true,
              title: { display: true, text: 'Precipitação (mm)', color: G.colors.axis, font: { size: 10 } },
              grid: { color: G.colors.grid }, border: { display: false }, ticks: { color: G.colors.axis }
            },
            y1: {
              position: 'right', beginAtZero: true,
              title: { display: true, text: 'Acumulado (mm)', color: G.colors.axis, font: { size: 10 } },
              grid: { display: false }, border: { display: false }, ticks: { color: G.colors.axis }
            }
          },
          plugins: G.plugins('mm', 1)
        },
        // valores escritos sobre as barras só quando há poucos pontos (7 dias)
        plugins: c.labels.length <= 10 ? [G.valueLabels({ datasets: [0], decimals: 1, color: '#09245a', offset: 6 })] : []
      });
      G.describe('grafico-chuva', c.daily, 'mm', 1);

      /* ------------------------------------------- pluviômetros */
      S.equipmentList('[data-sensors]', d.sensors, 'Esta represa não possui pluviômetro cadastrado.');

      /* ------------------------- aviso quando o acumulado passa do limite */
      var warn = document.querySelector('[data-warning]');
      if (d.warning.active) {
        warn.hidden = false;
        warn.innerHTML = '<article class="aq-card" style="flex-direction:row;align-items:center;gap:16px;'
          + 'border-color:#f6dfae;background:var(--aq-warning-soft)">'
          + '<span class="aq-kpi__icon aq-kpi__icon--warning" aria-hidden="true">'
          + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"'
          + ' stroke-linecap="round" stroke-linejoin="round"><path d="M12 4 2.8 19.5h18.4L12 4Z"/>'
          + '<path d="M12 10v4"/><path d="M12 17h.01"/></svg></span>'
          + '<div style="flex:1 1 auto"><h3 style="font-size:.98rem">' + S.esc(d.warning.title) + '</h3>'
          + '<p class="aq-card__sub">' + S.esc(d.warning.text) + '</p></div>'
          + '</article>';
      } else {
        warn.hidden = true;
      }
    }
  });
})();
