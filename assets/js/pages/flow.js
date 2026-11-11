/** Aquapulse — Monitoramento / Volume de vazão. */
/*
 * Página: dashboard/monitoramento/vazao.php | API: GET api/v1/monitoring/flow.php.
 * Represa, período, estados e recarga ficam em AqMonitorPage; aqui só o
 * render(), que desenha os dados recebidos.
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;

  window.AqMonitorPage({
    scopes: ['realtime', 'condition', 'daily'],
    fetch: function (p) { return window.AqApi.flow(p); },

    render: function (d) {
      var k = d.kpis;

      S.fill({
        'flow.value': F.num(k.flow.value, 1), 'flow.foot': k.flow.note,
        'inflow.value': F.num(k.inflow.value, 1), 'inflow.foot': k.inflow.note,
        'outflow.value': F.num(k.outflow.value, 1), 'outflow.foot': k.outflow.note,
        'balance.value': F.signed(k.balance.value, 1), 'balance.foot': k.balance.note,
        'condition.label': d.condition.label,
        'condition.text': d.condition.text,
        'condition.range': d.condition.range
      });

      document.querySelectorAll('[data-kpi="balance"] .aq-kpi__value').forEach(function (el) {
        el.classList.toggle('aq-kpi__value--success', k.balance.positive);
      });

      /* ------------------------------------ afluência e defluência */
      var rt = d.realtime;
      G.create('grafico-vazao', {
        type: 'line',
        data: {
          labels: rt.labels,
          datasets: [
            G.line('Afluência (entrada)', rt.inflow, G.colors.primary, { points: false, width: 2.4 }),
            G.line('Defluência (saída)', rt.outflow, G.colors.secondary, { points: false, width: 2.4 })
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          scales: G.scales({ decimals: 0, beginAtZero: false }),   // a escala acompanha os valores recebidos
          plugins: G.plugins('m³/s', 1)
        }
      });
      G.describe('grafico-vazao', rt.inflow, 'm³/s', 1);

      /* --------------- medidor: afluência atual entre mínima e máxima do período */
      var c = d.condition;
      G.gauge('medidor-vazao', {
        value: c.value,
        min: c.min,
        max: c.max > c.min ? c.max : c.min + 1,                    // evita escala de tamanho zero
        color: G.colors.primary,
        cutout: '74%'
      });

      /* ------------------------------------------- média diária 7 dias */
      var dl = d.daily;
      G.create('grafico-media-diaria', {
        type: 'bar',
        data: {
          labels: dl.labels,
          datasets: [
            G.bar('Afluência (média)', dl.inflow, G.colors.primary),
            G.bar('Defluência (média)', dl.outflow, '#b6d3fe')
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ decimals: 0 }),
          plugins: G.plugins('m³/s', 1)
        }
      });

      /* -------------------------------------------------- equipamentos */
      S.equipmentList('[data-sensors]', d.sensors, 'Esta represa não possui equipamentos de vazão cadastrados.');

      /* ------------------------------------------------ últimas leituras */
      document.querySelector('[data-readings]').innerHTML = d.readings.map(function (r) {
        return '<tr>'
          + '<td class="is-nowrap">' + S.esc(r.time) + '</td>'
          + '<td class="is-num">' + F.num(r.inflow, 1) + '</td>'
          + '<td class="is-num">' + F.num(r.outflow, 1) + '</td>'
          + '<td class="is-num">' + F.signed(r.balance, 1) + '</td>'
          + '</tr>';
      }).join('') || '<tr><td colspan="4">Nenhuma leitura de vazão nos últimos 7 dias.</td></tr>';
    }
  });
})();
