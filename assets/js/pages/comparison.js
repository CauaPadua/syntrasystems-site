/** Aquapulse — Monitoramento / Comparativo de vazão. */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;

  var selCurrent = document.getElementById('filtro-atual');
  var selPrevious = document.getElementById('filtro-anterior');

  var ICONS = {
    best: '<path d="M8 4h8v5a4 4 0 0 1-8 0V4Z"/><path d="M8 6H5.5A2.5 2.5 0 0 0 8 10.5"/><path d="M16 6h2.5A2.5 2.5 0 0 1 16 10.5"/><path d="M10 20h4"/><path d="M12 13v7"/>',
    worst: '<circle cx="12" cy="12" r="8.5"/><path d="M12 16V8"/><path d="m8.5 12.5 3.5 3.5 3.5-3.5"/>',
    avg: '<path d="M2 7.5c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/><path d="M2 13c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/>',
    trend: '<path d="M3 21h18"/><path d="M11 21V9"/><path d="M16 21v-9"/><path d="M14 4h6v6"/><path d="M20 4l-7.5 7.5"/>'
  };

  function icon(name) {
    return '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
      + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>';
  }

  var page = window.AqMonitorPage({
    periodId: null,
    scopes: ['chart', 'diff'],

    // os dois períodos são parâmetros próprios desta tela
    extraParams: function () {
      return { current: selCurrent.value, previous: selPrevious.value };
    },

    fetch: function (p) { return window.AqApi.comparison(p); },

    render: function (d) {
      var k = d.kpis;

      S.fill({
        'current.value': F.num(k.current.value, 1), 'current.foot': k.current.note,
        'previous.value': F.num(k.previous.value, 1), 'previous.foot': k.previous.note,
        'variation.value': F.signed(k.variation.value, 1), 'variation.foot': k.variation.note,
        'max_diff.value': F.signed(k.max_diff.value, 1), 'max_diff.foot': k.max_diff.note,
        'insight.text': d.insight.text
      });

      ['variation', 'max_diff'].forEach(function (id) {
        document.querySelectorAll('[data-kpi="' + id + '"] .aq-kpi__value').forEach(function (el) {
          el.classList.toggle('aq-kpi__value--success', k[id].positive);
          el.classList.toggle('aq-kpi__value--warning', !k[id].positive);
        });
      });

      /* ------------------------------------- vazão diária comparada */
      var c = d.chart;
      G.create('grafico-comparativo', {
        type: 'line',
        data: {
          labels: c.labels,
          datasets: [
            G.line('Período atual (' + d.periods.current + ')', c.current, G.colors.primary, { width: 2.6 }),
            G.line('Período anterior (' + d.periods.previous + ')', c.previous, '#9ec5fe', { dashed: true, width: 2 })
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: { padding: { top: 24, bottom: 14 } },
          interaction: { mode: 'index', intersect: false },
          scales: G.scales({ max: 80, decimals: 0 }),
          plugins: G.plugins('m³/s', 1)
        },
        plugins: [G.valueLabels({ datasets: [0], decimals: 1, color: '#0b5bea', offset: 10 })]
      });
      G.describe('grafico-comparativo', c.current, 'm³/s', 1);

      document.querySelector('[data-legend-periods]').innerHTML =
        '<div class="aq-legend">'
        + '<span class="aq-legend__item"><span class="aq-legend__key" style="background:#0b5bea"></span>'
        + 'Período atual (' + S.esc(d.periods.current) + ')</span>'
        + '<span class="aq-legend__item"><span class="aq-legend__key aq-legend__key--dashed" style="color:#9ec5fe"></span>'
        + 'Período anterior (' + S.esc(d.periods.previous) + ')</span></div>';

      /* ------------------------------- diferença por dia (barras horizontais) */
      var df = d.diff_chart;
      G.create('grafico-diferenca', {
        type: 'bar',
        data: {
          labels: df.labels,
          datasets: [{
            label: 'Diferença',
            data: df.values,
            backgroundColor: df.values.map(function (v) { return v >= 0 ? G.colors.success : G.colors.warning; }),
            borderRadius: 4,
            borderSkipped: false,
            maxBarThickness: 16
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            x: { min: -4, max: 12, grid: { color: G.colors.grid }, border: { display: false }, ticks: { color: G.colors.axis } },
            y: { grid: { display: false }, border: { display: false }, ticks: { color: G.colors.axis } }
          },
          plugins: G.plugins('m³/s', 1)
        },
        plugins: [G.valueLabels({
          horizontal: true, signed: true, decimals: 1, offset: 7,
          color: function (v) { return v >= 0 ? G.colors.success : G.colors.warning; }
        })]
      });

      /* ---------------------------------------- afluência x defluência */
      var io = d.in_out;
      G.create('grafico-afl-defl', {
        type: 'bar',
        data: {
          labels: io.labels,
          datasets: [
            G.bar('Afluência média', io.inflow, G.colors.primary, { maxThickness: 16 }),
            G.bar('Defluência média', io.outflow, '#b6d3fe', { maxThickness: 16 })
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ max: 80, decimals: 0 }),
          plugins: G.plugins('m³/s', 1)
        }
      });

      /* ---------------------------------------------------- resumo */
      var sm = d.summary;
      document.querySelector('[data-summary]').innerHTML = [
        { ic: 'best', tone: 'success', label: sm.best.label, right: '<strong>' + S.esc(sm.best.day) + '</strong><br>' + F.unit(sm.best.value, sm.best.unit) },
        { ic: 'worst', tone: 'warning', label: sm.worst.label, right: '<strong>' + S.esc(sm.worst.day) + '</strong><br>' + F.unit(sm.worst.value, sm.worst.unit) },
        { ic: 'avg', tone: 'info', label: sm.average.label, right: '<strong>' + F.unit(sm.average.current, sm.average.unit) + '</strong>  ' + F.unit(sm.average.previous, sm.average.unit) + '<br><span style="font-size:.76rem">(atual)  (anterior)</span>' },
        { ic: 'trend', tone: 'info', label: sm.trend.label, right: '<strong style="color:var(--aq-success)">' + S.esc(sm.trend.value) + '</strong><br><span style="font-size:.76rem">' + S.esc(sm.trend.note) + '</span>' }
      ].map(function (i) {
        return '<div class="aq-list__item">'
          + '<span class="aq-list__icon aq-list__icon--' + i.tone + '" aria-hidden="true">' + icon(i.ic) + '</span>'
          + '<div class="aq-list__body"><p class="aq-list__title">' + S.esc(i.label) + '</p></div>'
          + '<div class="aq-list__side" style="text-align:right;display:block">' + i.right + '</div></div>';
      }).join('');

      /* -------------------------------------------- comparativo diário */
      var SETA = {
        up:   '<path d="M12 19V5"/><path d="m6 11 6-6 6 6"/>',
        down: '<path d="M12 5v14"/><path d="m6 13 6 6 6-6"/>',
        flat: '<path d="M5 12h14"/>'
      };

      document.querySelector('[data-rows]').innerHTML = d.rows.map(function (r) {
        var flat = r.status === 'flat' || r.diff === 0;
        var chave = flat ? 'flat' : (r.status === 'up' ? 'up' : 'down');
        var rotulo = flat ? 'Estável' : (chave === 'up' ? 'Aumento' : 'Redução');
        return '<tr>'
          + '<td class="is-nowrap">' + S.esc(r.day) + '</td>'
          + '<td class="is-num">' + F.num(r.current, 1) + '</td>'
          + '<td class="is-num">' + F.num(r.previous, 1) + '</td>'
          + '<td class="is-num">' + F.signed(r.diff, 1) + '</td>'
          + '<td class="is-num">' + F.signed(r.pct, 1) + '%</td>'
          + '<td><span class="aq-arrow aq-arrow--' + chave + '" aria-hidden="true">'
          + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"'
          + ' stroke-linecap="round" stroke-linejoin="round">' + SETA[chave] + '</svg></span>'
          + '<span class="aq-visually-hidden">' + rotulo + '</span></td>'
          + '</tr>';
      }).join('');
    }
  });

  /* ---------------------- períodos: recusa comparação inválida (iguais) */
  function onPeriodChange() {
    if (selCurrent.value === selPrevious.value) {
      S.notify(
        'Períodos iguais',
        'Selecione períodos diferentes para comparar. Ajuste um dos seletores.',
        'error'
      );
      ['chart', 'diff'].forEach(function (s) {
        S.setState(s, 'error', 'O período atual e o de comparação não podem ser iguais.');
      });
      return;
    }
    page.reload();
  }

  selCurrent.addEventListener('change', onPeriodChange);
  selPrevious.addEventListener('change', onPeriodChange);
})();
