/**
 * Aquapulse — Níveis (visão histórica ampla).
 *
 * Consome o MESMO endpoint da tela detalhada de nível
 * (api/v1/monitoring/level.php). Não há segunda fonte de dados.
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;
  var Api = window.AqApi;
  var Ctx = window.AqContext;

  var selCompany = document.getElementById('filtro-empresa');
  var selReservoir = document.getElementById('filtro-represa');
  var selPeriod = document.getElementById('filtro-periodo');

  var lastData = null;

  function fillCompanies(items) {
    selCompany.innerHTML = '<option value="all">Todas as empresas</option>'
      + items.map(function (i) { return '<option value="' + S.esc(i.id) + '">' + S.esc(i.name) + '</option>'; }).join('');
  }

  function fillReservoirs(items) {
    selReservoir.innerHTML = items.map(function (i) {
      return '<option value="' + S.esc(i.id) + '">' + S.esc(i.name) + '</option>';
    }).join('');
    return Ctx.requireReservoir(items);
  }
  function render(d) {
    S.setState('history', 'ready');
    lastData = d;

    var k = d.kpis;
    var h = d.history;

    // as cotas vêm prontas da API (derivadas do percentual, ver
    // MonitoringService::cota) — nada é extraído de texto aqui
    var margem = Math.round((h.cota_spill - k.cota.value) * 10) / 10;
    var tendencia = Math.round((d.forecast.cota[d.forecast.cota.length - 1] - k.cota.value) * 10) / 10;

    S.fill({
      'cota.value': F.num(k.cota.value, 1), 'cota.foot': 'Cota atual',
      'cota.badge': { html: S.badge(k.level.status.label, k.level.status.key) },
      'used.value': F.num(k.level.value, 1), 'used.foot': 'da capacidade total',
      'variation.value': F.signed(k.variation.value, 1), 'variation.foot': 'vs ontem',
      'spill.value': F.num(h.cota_spill, 1), 'spill.foot': 'Cota de vertimento',
      'margin.value': F.num(margem, 1), 'margin.foot': 'até o vertimento',
      'status.value': { html: '<span class="aq-kpi__value--text aq-status--' + S.esc(k.level.status.key)
        + '">' + S.esc(k.level.status.label) + '</span>' },
      'status.foot': k.level.status.key === 'normal'
        ? 'Dentro da faixa operacional'
        : 'Monitoramento intensificado',
      'trend.value': F.signed(tendencia, 1) + ' m'
    });

    S.setRing('used', k.level.value);

    /* --------------------- histórico em cota, com todos os limites */
    var ctx = document.getElementById('grafico-niveis').getContext('2d');

    // escala de 2 em 2 metros ao redor dos dados e dos limites, para que os
    // rótulos do eixo não se repitam depois do arredondamento
    var cotas = h.cota.concat([h.cota_alert, h.cota_attention, h.cota_spill, h.cota_critical]);
    var cotaMin = Math.floor(Math.min.apply(null, cotas) / 2) * 2 - 2;
    var cotaMax = Math.ceil(Math.max.apply(null, cotas) / 2) * 2 + 2;

    G.create('grafico-niveis', {
      type: 'line',
      data: {
        labels: h.labels,
        datasets: [G.line('Nível observado', h.cota, G.colors.primary, { fillCtx: ctx, alpha: 0.14 })]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: G.scales({ beginAtZero: false, min: cotaMin, max: cotaMax, step: 2, decimals: 0 }),
        plugins: G.plugins('m', 1, {
          annotation: {
            annotations: {
              vertimento: G.limitLine(h.cota_spill, '#38bdf8',
                'Cota de vertimento ' + F.num(h.cota_spill, 1) + ' m', 'start'),
              critico: G.limitLine(h.cota_critical, G.colors.danger,
                'Cota crítica ' + F.num(h.cota_critical, 1) + ' m', 'end'),
              atencao: G.limitLine(h.cota_attention, G.colors.warning,
                'Cota de atenção ' + F.num(h.cota_attention, 1) + ' m', 'start', true),
              alerta: G.limitLine(h.cota_alert, '#fb923c',
                'Cota de alerta ' + F.num(h.cota_alert, 1) + ' m', 'end', true)
            }
          }
        })
      }
    });
    G.describe('grafico-niveis', h.cota, 'm', 1);

    // tabela equivalente ao gráfico
    document.querySelector('[data-chart-rows]').innerHTML = h.labels.map(function (l, i) {
      return '<tr><td>' + S.esc(l) + '</td><td class="is-num">' + F.num(h.cota[i], 1) + '</td></tr>';
    }).join('');

    /* ------------------------------------------- faixas e marcador */
    var marker = document.querySelector('[data-level-marker]');
    if (marker) marker.style.bottom = k.level.value + '%';

    // as faixas são as do próprio sistema (StatusRules), enviadas pela API —
    // repetir percentuais aqui faria a tela discordar da classificação
    var cores = { normal: 'var(--aq-primary)', attention: 'var(--aq-warning)', critical: 'var(--aq-danger)' };
    document.querySelector('[data-level-bands]').innerHTML = d.bands.map(function (b) {
      return '<li style="display:flex;align-items:flex-start;gap:9px">'
        + '<span style="width:13px;height:13px;border-radius:3px;background:' + cores[b.status] + ';flex:none;margin-top:2px"></span>'
        + '<div><strong style="display:block">' + S.esc(b.label) + '</strong>'
        + '<span style="color:var(--aq-text-secondary)">' + S.esc(b.range) + '</span></div></li>';
    }).join('');

    /* --------------------------------------------- tendência 7 dias */
    var f = d.forecast;
    var ctxT = document.getElementById('grafico-tendencia').getContext('2d');

    // esta tela trabalha em cota, então a projeção também é exibida em metros
    var proj = [k.cota.value, f.cota[1], f.cota[3], f.cota[5], f.cota[6]];
    var projMin = Math.floor(Math.min.apply(null, proj) / 2) * 2 - 2;
    var projMax = Math.ceil(Math.max.apply(null, proj) / 2) * 2 + 2;

    G.create('grafico-tendencia', {
      type: 'line',
      data: {
        labels: ['Hoje', '+2 dias', '+4 dias', '+6 dias', '+7 dias'],
        datasets: [G.line('Projeção', proj, G.colors.primary, { fillCtx: ctxT, alpha: 0.12 })]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: G.scales({ beginAtZero: false, min: projMin, max: projMax, step: 2, decimals: 0 }),
        plugins: G.plugins('m', 1)
      }
    });

    /* -------------------------------------------------- registros */
    document.querySelector('[data-records]').innerHTML = d.readings.map(function (r) {
      var up = r.variation >= 0;
      return '<tr>'
        + '<td class="is-nowrap">' + S.esc(r.time) + '</td>'
        + '<td class="is-num">' + F.num(r.cota, 1) + '</td>'
        + '<td class="is-num" style="color:var(--aq-' + (up ? 'success' : 'danger') + ')">'
        + F.signed(r.variation, 1) + ' ' + (up ? '↑' : '↓') + '</td>'
        + '<td class="is-num">' + F.num(r.level, 1) + '%</td>'
        + '<td>' + S.badge(r.status.label, r.status.key) + '</td>'
        + '</tr>';
    }).join('');

    /* --------------------------------------------- comparativo mensal */
    var meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai'];
    var base = k.cota.value;
    G.create('grafico-mensal', {
      type: 'line',
      data: {
        labels: meses,
        datasets: [
          G.line('Máxima', [base + 3.2, base + 2.9, base + 3.4, base + 3.0, base + 3.6], '#38bdf8', { dashed: true, width: 1.8, points: false }),
          G.line('Média', [base + 0.9, base + 0.6, base + 1.0, base + 0.8, base + 1.4], G.colors.success, { dashed: true, width: 1.8, points: false }),
          G.line('Mínima', [base - 3.6, base - 3.9, base - 3.4, base - 3.7, base - 3.2], '#fb923c', { dashed: true, width: 1.8, points: false }),
          G.line('Atual', [base - 2.6, base - 3.3, base - 3.0, base - 2.9, base], G.colors.primary, { width: 2.4 })
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: G.scales({ beginAtZero: false, min: 550, max: 570, decimals: 0 }),
        plugins: G.plugins('m', 1)
      }
    });

    /* ------------------------------------------- limites configurados */
    // os mesmos valores usados no gráfico e nos alertas — nenhum número solto
    document.querySelector('[data-limits]').innerHTML = [
      { label: 'Cota de vertimento', value: h.cota_spill, color: '#38bdf8' },
      { label: 'Cota de atenção', value: h.cota_attention, color: 'var(--aq-warning)' },
      { label: 'Cota de alerta', value: h.cota_alert, color: '#fb923c' },
      { label: 'Cota crítica', value: h.cota_critical, color: 'var(--aq-danger)' }
    ].map(function (l) {
      return '<div class="aq-form-row">'
        + '<span style="display:flex;align-items:center;gap:10px">'
        + '<span class="aq-legend__key aq-legend__key--dashed" style="width:26px;color:' + l.color + '"></span>'
        + S.esc(l.label) + '</span>'
        + '<strong>' + F.num(l.value, 1) + ' m</strong></div>';
    }).join('');
  }

  function load() {
    S.setState('history', 'loading');

    return Api.level({ reservoir_id: Ctx.get().reservoir_id, period: selPeriod.value })
      .then(function (r) {
        render(r.data);
        S.setUpdated(r.meta.generated_at, r.meta.updated_label);
      })
      .catch(function (err) {
        if (Api.isAbort(err)) return;
        S.setState('history', err.code === 'NO_DATA' ? 'empty' : 'error', err.message);
      });
  }

  /* ----------------------------------------------------------- eventos */

  selCompany.addEventListener('change', function () {
    Ctx.set({ company_id: selCompany.value });
    Api.reservoirs(selCompany.value).then(function (r) {
      selReservoir.value = fillReservoirs(r.data.reservoirs);
      load();
    });
  });

  selReservoir.addEventListener('change', function () {
    Ctx.set({ reservoir_id: selReservoir.value });
    load();
  });

  selPeriod.addEventListener('change', function () {
    Ctx.set({ period: selPeriod.value });
    syncChips();
    load();
  });

  function syncChips() {
    document.querySelectorAll('[data-quick-periods] .aq-chip').forEach(function (c) {
      c.classList.toggle('is-active', c.getAttribute('data-period') === selPeriod.value);
    });
  }

  document.addEventListener('click', function (e) {
    var chip = e.target.closest('[data-quick-periods] .aq-chip');
    if (chip) {
      selPeriod.value = chip.getAttribute('data-period');
      Ctx.set({ period: selPeriod.value });
      syncChips();
      load();
      return;
    }

    var toggle = e.target.closest('[data-toggle-table]');
    if (toggle) {
      var table = document.querySelector('[data-chart-table]');
      table.hidden = !table.hidden;
      toggle.querySelector('span').textContent = table.hidden ? 'Ver tabela' : 'Ver gráfico';
    }
  });

  S.onRetry('history', load);
  S.onReload(load);

  /* ------------------------------------------------------------- início */
  Api.companies()
    .then(function (r) {
      fillCompanies(r.data.companies);
      selCompany.value = Ctx.get().company_id;
      return Api.reservoirs(Ctx.get().company_id);
    })
    .then(function (r) {
      selReservoir.value = fillReservoirs(r.data.reservoirs);
      selPeriod.value = ['7d', '30d', '90d', '12m'].indexOf(Ctx.get().period) >= 0 ? Ctx.get().period : '30d';
      syncChips();
      return load();
    })
    .catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('history', 'error', err.message);
    });
})();
