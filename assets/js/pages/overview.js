/**
 * Aquapulse — tela Visão geral.
 *
 * Carrega empresas e represas, aplica o contexto salvo e alterna entre o modo
 * consolidado e o modo de represa selecionada.
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
  var viewAll = document.querySelector('[data-view="all"]');
  var viewSingle = document.querySelector('[data-view="single"]');

  var SCOPES_ALL = ['comparison', 'flow-all', 'donut', 'summary'];
  var SCOPES_ONE = ['level-chart', 'flow-chart'];

  var reservoirCache = [];

  /* ------------------------------------------------------------ filtros */

  function fillSelect(select, items, allLabel) {
    var html = '<option value="all">' + allLabel + '</option>';
    items.forEach(function (i) {
      html += '<option value="' + S.esc(i.id) + '">' + S.esc(i.name) + '</option>';
    });
    select.innerHTML = html;
  }

  function loadCompanies() {
    return Api.companies().then(function (r) {
      fillSelect(selCompany, r.data.companies, 'Todas as empresas');
      selCompany.value = Ctx.get().company_id;
    });
  }

  function loadReservoirs() {
    var ctx = Ctx.get();
    return Api.reservoirs(ctx.company_id).then(function (r) {
      reservoirCache = r.data.reservoirs;
      fillSelect(selReservoir, reservoirCache, 'Todas as represas');

      // se a represa não pertence mais à empresa, a seleção é redefinida
      var next = Ctx.reconcile(reservoirCache);
      selReservoir.value = next.reservoir_id;
    });
  }

  /* -------------------------------------------------- renderização: todas */

  function renderAll(d) {
    var k = d.kpis;

    // conteudo visivel antes dos graficos: canvas oculto nasce com tamanho zero
    SCOPES_ALL.forEach(function (s) { S.setState(s, 'ready'); });

    S.fill({
      'all.reservoirs.value': F.int(k.reservoirs.value),
      'all.reservoirs.foot': '',
      'all.reservoirs.badge': { html: '<span class="aq-status-text"><span class="aq-dot aq-dot--normal"></span>' + k.reservoirs.online + ' online</span>' },
      'all.storage.value': F.int(k.storage.value),
      'all.storage.foot': F.pct(k.storage.pct) + ' da capacidade',
      'all.level.value': F.num(k.level.value, 1),
      'all.flow.value': F.num(k.flow.value, 1),
      'all.ph.value': F.num(k.ph.value, 1),
      'all.ph.badge': { html: S.badge(k.ph.note, 'normal') },
      'all.operation.value': { html: '<span class="aq-kpi__inline">'
        + '<span style="color:var(--aq-success);font-weight:800">' + k.operation.normal + ' normais</span> · '
        + '<span style="color:var(--aq-warning);font-weight:800">' + k.operation.attention + ' atenção</span></span>' },
      'all.alerts.total': d.alert_counts.total
    });

    // barras horizontais de comparação
    var bars = document.querySelector('[data-comparison-bars]');
    bars.innerHTML = d.comparison.map(function (c) {
      return '<div class="aq-bar" style="margin-bottom:16px">'
        + '<div class="aq-bar__head"><span style="font-weight:600">' + S.esc(c.name) + '</span>'
        + '<strong style="color:var(--aq-' + (c.status.key === 'normal' ? 'success' : c.status.key === 'attention' ? 'warning' : 'danger') + ')">'
        + F.pct(c.level) + '</strong></div>'
        + '<div class="aq-bar__track"><div class="aq-bar__fill aq-bar__fill--' + c.status.key
        + '" style="width:' + c.level + '%"></div></div>'
        + '<span class="aq-visually-hidden">Situação: ' + c.status.label + '</span>'
        + '</div>';
    }).join('');
    S.setState('comparison', 'ready');

    // linha da vazão consolidada
    var fc = d.flow_chart;
    G.create('grafico-vazao-consolidada', {
      type: 'line',
      data: {
        labels: fc.labels,
        datasets: [(function () {
          var ctx = document.getElementById('grafico-vazao-consolidada').getContext('2d');
          return G.line('Vazão total', fc.values, G.colors.primary, { fillCtx: ctx, alpha: 0.16 });
        })()]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 22 } },
        scales: G.scales({ decimals: 0, max: 200 }),
        plugins: G.plugins('m³/s', 1)
      },
      plugins: [G.valueLabels({ decimals: 1, color: '#09245a' })]
    });
    G.describe('grafico-vazao-consolidada', fc.values, 'm³/s', 1);
    S.setState('flow-all', 'ready');

    // donut da situação
    G.donut('grafico-situacao', {
      labels: ['Normal', 'Atenção', 'Crítico'],
      values: [d.donut.normal, d.donut.attention, d.donut.critical],
      colors: [G.colors.success, G.colors.warning, G.colors.danger],
      cutout: '70%',
      center: [
        { text: String(d.donut.total), size: 30 },
        { text: 'represas', size: 12, color: '#536b96', weight: '600' }
      ]
    });

    var total = d.donut.total || 1;
    document.querySelector('[data-donut-legend]').innerHTML = [
      { label: 'Normal', value: d.donut.normal, key: 'normal' },
      { label: 'Atenção', value: d.donut.attention, key: 'attention' },
      { label: 'Crítico', value: d.donut.critical, key: 'critical' }
    ].map(function (i) {
      return '<li style="display:flex;align-items:center;gap:9px">'
        + '<span class="aq-dot aq-dot--' + i.key + '"></span>'
        + '<span style="flex:1 1 auto">' + i.label + '</span>'
        + '<strong>' + i.value + ' (' + F.pct(i.value / total * 100) + ')</strong></li>';
    }).join('');
    S.setState('donut', 'ready');

    // contagem de alertas
    document.querySelector('[data-alert-counts]').innerHTML = [
      { n: d.alert_counts.critical, label: 'críticos', key: 'critical' },
      { n: d.alert_counts.attention, label: 'atenção', key: 'attention' }
    ].map(function (i) {
      return '<div style="text-align:center">'
        + S.badge(String(i.n), i.key)
        + '<p style="font-size:.8rem;color:var(--aq-text-secondary);margin-top:6px">' + i.label + '</p></div>';
    }).join('');

    // tabela resumo
    document.querySelector('[data-summary-rows]').innerHTML = d.reservoirs.map(function (r) {
      return '<tr class="is-' + r.status.key + '">'
        + '<td><span class="aq-table__name"><span class="aq-table__icon">'
          + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
          + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
          + '<path d="M3.5 20.5V7l8.5-3.5L20.5 7v13.5"/><path d="M3.5 11h17"/><path d="M3.5 15.5h17"/></svg>'
          + '</span><span class="is-nowrap">' + S.esc(r.name) + '</span></span></td>'
        + '<td class="is-num" style="font-weight:700;color:var(--aq-' + (r.status.key === 'attention' ? 'warning' : 'success') + ')">' + F.pct(r.level) + '</td>'
        + '<td class="is-num is-nowrap">' + F.int(r.volume) + ' hm³</td>'
        + '<td class="is-num is-nowrap">' + F.num(r.flow, 1) + ' m³/s</td>'
        + '<td class="is-num">' + F.num(r.ph, 1) + '</td>'
        + '<td class="is-num is-nowrap">' + F.num(r.rain, 1) + ' mm</td>'
        + '<td class="is-num is-nowrap">' + r.duration + ' dias</td>'
        + '<td>' + S.badge(r.status.label, r.status.key) + '</td>'
        + '</tr>';
    }).join('');
    S.setState('summary', 'ready');

    // mapa consolidado
    if (window.AqMap) {
      window.AqMap.render('mapa-visao-geral', d.reservoirs.map(function (r) {
        return { lat: r.lat, lng: r.lng, name: r.name, city: r.city, level: r.level, flow: r.flow, status: r.status };
      }), {});
    }

    // alertas prioritários
    document.querySelector('[data-priority-alerts]').innerHTML = d.priority_alerts.map(function (a) {
      return '<div class="aq-list__item">'
        + '<span class="aq-list__icon aq-list__icon--' + a.severity + '" aria-hidden="true">'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"'
        + ' stroke-linecap="round" stroke-linejoin="round"><path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4"/><path d="M12 17h.01"/></svg>'
        + '</span>'
        + '<div class="aq-list__body"><p class="aq-list__title" style="font-size:.86rem">'
        + S.esc(a.reservoir_short) + ' — ' + S.esc(a.title.toLowerCase()) + '</p></div>'
        + '<div class="aq-list__side">' + S.badge(a.severity_label, a.severity)
        + '<span>' + S.esc(a.time) + '</span></div></div>';
    }).join('');
  }

  /* ------------------------------------------- renderização: uma represa */

  function renderSingle(d) {
    var k = d.kpis;

    SCOPES_ONE.forEach(function (s) { S.setState(s, 'ready'); });

    S.fill({
      'one.level.value': F.num(k.level.value, 1),
      'one.level.foot': 'Cota: ' + F.unit(k.level.cota, 'm'),
      'one.level.badge': { html: S.badge(k.level.status.label, k.level.status.key) },
      'one.storage.value': F.int(k.storage.value),
      'one.storage.foot': 'de ' + F.int(k.storage.capacity) + ' hm³',
      'one.flow.value': F.num(k.flow.value, 1),
      'one.flow.foot': 'Média 24h',
      'one.flow.badge': { html: '<span class="aq-trend aq-trend--down">▼ ' + Math.abs(k.flow.trend) + '% <span style="font-weight:400;color:var(--aq-text-secondary)">' + k.flow.trend_label + '</span></span>' },
      'one.ph.value': F.num(k.ph.value, 1),
      'one.ph.foot': k.ph.note,
      'one.ph.badge': { html: S.badge(k.ph.status.label, k.ph.status.key) },
      'one.rain.value': F.num(k.rain.value, 1),
      'one.rain.foot': k.rain.note,
      'one.rain.badge': { html: '<span class="aq-trend aq-trend--up">▲ ' + k.rain.trend + '% <span style="font-weight:400;color:var(--aq-text-secondary)">' + k.rain.trend_label + '</span></span>' },
      'one.duration.value': F.int(k.duration.value),
      'one.duration.foot': k.duration.note,
      // a cor acompanha a situação apurada — verde fixo diria "normal" mesmo
      // quando a represa está em atenção
      'one.operation.value': { html: '<span class="aq-kpi__value--text aq-status--'
        + S.esc(k.operation.status.key) + '">' + S.esc(k.operation.status.label) + '</span>' },
      'one.operation.foot': k.operation.note,
      'one.spill': d.level_chart.spill_label
    });

    S.setRing('one.storage', k.storage.occupancy);

    // gráfico de nível (cota) com linha de vertimento
    var lc = d.level_chart;
    var ctxLevel = document.getElementById('grafico-nivel').getContext('2d');

    // a cota varia poucos metros: sem uma escala de 5 em 5 os rótulos
    // arredondados do eixo apareceriam repetidos (562, 562, 563, 563…)
    var cotas = lc.observed.concat(lc.spill);
    var cotaMin = Math.floor(Math.min.apply(null, cotas) / 5) * 5 - 5;
    var cotaMax = Math.ceil(Math.max.apply(null, cotas) / 5) * 5 + 5;

    G.create('grafico-nivel', {
      type: 'line',
      data: {
        labels: lc.labels,
        datasets: [
          G.line('Nível observado', lc.observed, G.colors.primary, { fillCtx: ctxLevel, alpha: 0.14, points: false }),
          G.line('Cota de vertimento', lc.spill, G.colors.primary, { dashed: true, points: false, width: 1.6, tension: 0 })
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: G.scales({ beginAtZero: false, min: cotaMin, max: cotaMax, step: 5, decimals: 0 }),
        plugins: G.plugins('m', 1)
      }
    });
    G.describe('grafico-nivel', lc.observed, 'm', 1);
    S.setState('level-chart', 'ready');

    // comparativo de vazão
    var fc = d.flow_chart;
    G.create('grafico-vazao-comparativo', {
      type: 'line',
      data: {
        labels: fc.labels,
        datasets: [
          G.line('Dia atual', fc.current, G.colors.primary, { points: false }),
          G.line('Dias anteriores (média)', fc.previous, '#7f90af', { dashed: true, points: false, width: 1.8 })
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: G.scales({ decimals: 0 }),
        plugins: G.plugins('m³/s', 1)
      }
    });
    G.describe('grafico-vazao-comparativo', fc.current, 'm³/s', 1);
    S.setState('flow-chart', 'ready');

    // mapa da represa
    if (window.AqMap && d.reservoir) {
      window.AqMap.render('mapa-represa', [{
        lat: d.reservoir.lat, lng: d.reservoir.lng, name: d.reservoir.name,
        city: d.reservoir.city, level: k.level.value, flow: k.flow.value, status: d.reservoir.status
      }], { zoomControl: true });
    }

    // alertas recentes
    document.querySelector('[data-recent-alerts]').innerHTML = d.alerts.map(function (a) {
      return '<div class="aq-list__item">'
        + '<span class="aq-list__icon aq-list__icon--' + a.severity + '" aria-hidden="true">'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"'
        + ' stroke-linecap="round" stroke-linejoin="round"><path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4"/><path d="M12 17h.01"/></svg>'
        + '</span>'
        + '<div class="aq-list__body"><p class="aq-list__title">' + S.esc(a.title) + '</p>'
        + '<p class="aq-list__meta">' + S.esc(a.reservoir) + '</p></div>'
        + '<div class="aq-list__side">' + S.badge(a.severity_label, a.severity)
        + '<span>' + S.esc(a.at) + '</span></div></div>';
    }).join('') || '<p class="aq-card__sub">Nenhum alerta recente.</p>';

    // relatórios recentes
    document.querySelector('[data-reports-rows]').innerHTML = d.reports.map(function (r) {
      var statusKey = r.status === 'done' ? 'normal' : (r.status === 'processing' ? 'info' : 'neutral');
      return '<tr>'
        + '<td>' + S.esc(r.name) + '</td>'
        + '<td>' + S.esc(r.reservoir) + '</td>'
        + '<td>' + S.esc(r.period) + '</td>'
        + '<td class="is-nowrap">' + S.esc(r.generated_at) + '</td>'
        + '<td>' + S.badge(r.status_label, statusKey) + '</td>'
        + '<td><a class="aq-card__link" href="relatorios.php" aria-label="Ver relatório ' + S.esc(r.name) + '">'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
        + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<path d="M12 3.5v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4.5 20.5h15"/></svg></a></td>'
        + '</tr>';
    }).join('');
  }

  /* ------------------------------------------------------------- carregar */

  function load() {
    var ctx = Ctx.get();
    var isAll = ctx.reservoir_id === 'all';

    viewAll.hidden = !isAll;
    viewSingle.hidden = isAll;

    document.querySelector('[data-context-note]').textContent = isAll
      ? 'Dados consolidados do sistema'
      : 'Dados da represa selecionada';

    var scopes = isAll ? SCOPES_ALL : SCOPES_ONE;
    scopes.forEach(function (s) { S.setState(s, 'loading'); });

    return Api.overview({
      company_id: ctx.company_id,
      reservoir_id: ctx.reservoir_id,
      period: ctx.period
    }).then(function (r) {
      if (isAll) renderAll(r.data); else renderSingle(r.data);
      S.setUpdated(r.meta.generated_at, r.meta.updated_label);
      var el = document.querySelector('[data-context-updated]');
      if (el) el.textContent = r.meta.updated_label || F.relative(r.meta.generated_at);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      scopes.forEach(function (s) { S.setState(s, 'error', err.message); });
    });
  }

  /* --------------------------------------------------------------- eventos */

  selCompany.addEventListener('change', function () {
    Ctx.set({ company_id: selCompany.value });
    loadReservoirs().then(load);
  });

  selReservoir.addEventListener('change', function () {
    Ctx.set({ reservoir_id: selReservoir.value });
    load();
  });

  SCOPES_ALL.concat(SCOPES_ONE).forEach(function (s) {
    S.onRetry(s, load);
  });

  S.onReload(load);

  /* ---------------------------------------------------------------- início */
  loadCompanies()
    .then(loadReservoirs)
    .then(load)
    .catch(function (err) {
      if (Api.isAbort(err)) return;
      SCOPES_ALL.forEach(function (s) { S.setState(s, 'error', err.message); });
    });
})();
