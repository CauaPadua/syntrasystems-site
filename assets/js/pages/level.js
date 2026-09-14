/** Aquapulse — Monitoramento / Nível do reservatório. */
/*
 * Página: dashboard/monitoramento/nivel.php | API: GET api/v1/monitoring/level.php.
 * Represa, período, estados e recarga ficam em AqMonitorPage; aqui só o render().
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;

  window.AqMonitorPage({
    scopes: ['history', 'capacity', 'forecast'],
    fetch: function (p) { return window.AqApi.level(p); },

    render: function (d) {                                            // d = MonitoringService::level()
      var k = d.kpis;

      S.fill({
        'level.value': F.num(k.level.value, 1), 'level.foot': k.level.note,
        'level.badge': { html: S.badge(k.level.status.label, k.level.status.key) }, // Normal / Atenção / Crítico
        'cota.value': F.num(k.cota.value, 1), 'cota.foot': k.cota.note,
        'variation.value': F.signed(k.variation.value, 1), 'variation.foot': k.variation.note,
        'available.value': F.num(k.available.value, 1), 'available.foot': k.available.note,
        'capacity.level': F.pct(d.capacity.level),                    // texto dentro da coluna de capacidade
        'capacity.total': 'Capacidade total: 100%'
      });

      document.querySelectorAll('[data-kpi="variation"] .aq-kpi__value').forEach(function (el) {
        el.classList.toggle('aq-kpi__value--success', k.variation.positive); // variação positiva (cota subindo) em verde
      });

      /* --------------- histórico com linhas de limite (plugin annotation) */
      var h = d.history;
      var ctx = document.getElementById('grafico-historico-nivel').getContext('2d'); // contexto usado para o gradiente da área

      G.create('grafico-historico-nivel', {
        type: 'line',
        data: {
          labels: h.labels,
          datasets: [G.line('Nível observado', h.values, G.colors.primary, { fillCtx: ctx, alpha: 0.16 })]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ beginAtZero: false, min: 50, max: 100, step: 10, decimals: 0 }), // eixo de 50% a 100% (valores abaixo de 50% ficariam fora do gráfico)
          plugins: G.plugins('%', 1, {
            annotation: {                                             // linhas horizontais de limite
              annotations: {
                atencao: G.limitLine(h.attention, G.colors.warning, h.attention_label, 'end', true), // 80%, rótulo abaixo da linha
                critico: G.limitLine(h.critical, G.colors.danger, h.critical_label, 'end')           // 90%
              }
            }
          })
        }
      });
      G.describe('grafico-historico-nivel', h.values, '%', 1);

      /* ------------------------------------ capacidade e faixas */
      var fill = document.querySelector('[data-capacity-fill]');
      if (fill) fill.style.height = d.capacity.level + '%';           // altura da "água" = nível em %; o CSS anima a mudança

      document.querySelector('[data-capacity-bands]').innerHTML = d.capacity.bands.map(function (b) { // uma linha por faixa
        var color = b.status === 'critical' ? 'danger' : (b.status === 'attention' ? 'warning' : 'success'); // status -> variável de cor do CSS
        return '<li style="display:flex;gap:12px;align-items:flex-start">'
          + '<span style="width:4px;align-self:stretch;border-radius:3px;background:var(--aq-' + color + ')"></span>'
          + '<div><strong style="display:block;font-size:.88rem">' + S.esc(b.label) + '</strong>'
          + '<span style="font-size:.83rem;color:var(--aq-' + color + ');font-weight:700">' + S.esc(b.text) + '</span></div></li>';
      }).join('');

      /* ------------------------------------------------- tendência 7 dias */
      var f = d.forecast;
      var ctxF = document.getElementById('grafico-tendencia-nivel').getContext('2d');
      G.create('grafico-tendencia-nivel', {
        type: 'line',
        data: {
          labels: f.labels,
          datasets: [G.line('Previsão', f.values, G.colors.primary, { fillCtx: ctxF, alpha: 0.12, dashed: true })] // tracejado: é projeção, não medida
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ beginAtZero: false, min: 60, max: 100, step: 10, decimals: 0 }),
          plugins: G.plugins('%', 1)
        }
      });

      /* ------------------------------------------- faixas operacionais */
      document.querySelector('[data-bands]').innerHTML = d.bands.map(function (b) {
        return '<div class="aq-list__item">'
          + '<span class="aq-list__icon aq-list__icon--' + b.status + '" aria-hidden="true">'
          + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
          + ' stroke-linecap="round" stroke-linejoin="round">'
          + '<path d="M2 7.5c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/>'
          + '<path d="M2 13c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/></svg></span>'
          + '<div class="aq-list__body"><p class="aq-list__title">' + S.esc(b.label) + '</p>'
          + '<p class="aq-list__meta">' + S.esc(b.range) + '</p></div>'
          + '<div class="aq-list__side" style="max-width:130px;text-align:right">' + S.esc(b.text) + '</div></div>';
      }).join('');

      /* ------------------------------------------------ últimas leituras */
      document.querySelector('[data-readings]').innerHTML = d.readings.map(function (r) {
        return '<tr>'
          + '<td class="is-nowrap">' + S.esc(r.time) + '</td>'
          + '<td class="is-num">' + F.num(r.cota, 1) + '</td>'
          + '<td class="is-num">' + F.num(r.level, 1) + '</td>'
          + '<td class="is-num">' + F.signed(r.variation, 1) + ' m</td>'
          + '<td>' + S.badge(r.status.label, r.status.key) + '</td>'  // status calculado no servidor para cada leitura
          + '</tr>';
      }).join('');
    }
  });
})();
