/** Aquapulse — Central de alertas. */
/*
 * Página: dashboard/alertas.php
 * API: GET api/v1/alerts.php (lista, números do topo e gráfico)
 *      POST api/v1/alerts.php (assumir / resolver — grava em alerts e alert_timeline)
 *
 * Fluxo: empresas -> represas -> load(). A busca por texto filtra só no
 * navegador; severidade e status são filtrados no servidor.
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
  var selSeverity = document.getElementById('filtro-severidade');
  var selStatus = document.getElementById('filtro-status');
  var search = document.getElementById('busca-alerta');
  var btnAck = document.querySelector('[data-ack]');
  var btnResolve = document.querySelector('[data-resolve]');

  var rows = [];            // alertas recebidos da API (antes da busca por texto)
  var selectedId = null;    // alerta aberto no painel de detalhes

  var SEV_ICON = {
    critical: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.8v4.7"/><path d="M12 16.2h.01"/>',
    attention: '<path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
    info: '<circle cx="12" cy="12" r="8.5"/><path d="M12 11.5v5"/><path d="M12 7.8h.01"/>'
  };

  function sevIcon(sev) {
    return '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"'
      + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
      + (SEV_ICON[sev] || SEV_ICON.info) + '</svg>';
  }

  /** Cor do texto para cada severidade. */
  function sevColor(sev) {
    return sev === 'critical' ? 'danger' : (sev === 'attention' ? 'warning' : 'info');
  }

  /** Cor do selo de andamento: resolvido verde, em análise âmbar, novo azul. */
  function statusKey(st) {
    return st === 'resolved' ? 'normal' : (st === 'analysis' ? 'attention' : 'info');
  }

  function fillSelect(select, items, allLabel) {
    select.innerHTML = '<option value="all">' + allLabel + '</option>'
      + items.map(function (i) {
        return '<option value="' + S.esc(i.id) + '">' + S.esc(i.name) + '</option>';
      }).join('');
  }

  /* ------------------------------------------------------ painel lateral */

  function showDetail(a) {
    if (!a) {
      S.setState('detail', 'empty');
      return;
    }

    selectedId = a.id;
    S.setState('detail', 'ready');

    S.fill({
      'detail.icon': { html: '<span style="color:var(--aq-' + sevColor(a.severity) + ')">' + sevIcon(a.severity) + '</span>' },
      'detail.title': a.id + ' · ' + a.title,
      'detail.severity': { html: S.badge(S.esc(a.severity_label), a.severity) },
      'detail.reservoir': 'Represa ' + a.reservoir + ' · ' + a.status_label + ' · Responsável: ' + a.owner,
      'detail.value': a.current_value,
      'detail.detail': a.detail,
      'detail.threshold': a.threshold,
      'detail.threshold_detail': a.threshold_detail
    });

    document.querySelector('[data-timeline]').innerHTML = a.timeline.map(function (t) {
      return '<li style="display:flex;gap:12px">'
        + '<span style="width:11px;height:11px;border-radius:50%;flex:none;margin-top:4px;background:var(--aq-primary)"></span>'
        + '<div><strong style="display:block;font-size:.82rem;color:var(--aq-text-secondary)">' + S.esc(t.at) + '</strong>'
        + '<span style="font-size:.86rem">' + S.esc(t.text) + '</span></div></li>';
    }).join('');

    // Só é possível assumir um alerta novo e resolver um que ainda não foi resolvido.
    btnAck.disabled = a.status !== 'new';
    btnResolve.disabled = a.status === 'resolved';

    document.querySelectorAll('[data-rows] tr').forEach(function (tr) {
      tr.classList.toggle('is-selected', tr.getAttribute('data-alert') === a.id);
    });
  }

  /* --------------------------------------------------------- renderização */

  function applySearch(list) {
    var term = (search.value || '').trim().toLowerCase();
    if (!term) return list;
    return list.filter(function (a) {
      return (a.id + ' ' + a.title + ' ' + a.reservoir + ' ' + a.metric).toLowerCase().indexOf(term) >= 0;
    });
  }

  function renderRows() {
    var list = applySearch(rows);

    if (!list.length) {
      S.setState('alerts', 'empty');
      S.setState('detail', 'empty');
      return;
    }
    S.setState('alerts', 'ready');

    document.querySelector('[data-rows]').innerHTML = list.map(function (a) {
      return '<tr data-alert="' + S.esc(a.id) + '" style="cursor:pointer">'
        + '<td><span class="aq-status-text" style="color:var(--aq-' + sevColor(a.severity) + ')">'
        + sevIcon(a.severity) + S.esc(a.severity_label) + '</span></td>'
        + '<td>' + S.esc(a.title) + '</td>'
        + '<td>' + S.esc(a.reservoir) + '</td>'
        + '<td>' + S.esc(a.metric) + '</td>'
        + '<td class="is-nowrap">' + S.esc(a.detected_at) + '</td>'
        + '<td>' + S.esc(a.owner) + '</td>'
        + '<td>' + S.badge(S.esc(a.status_label), statusKey(a.status)) + '</td>'
        + '</tr>';
    }).join('');

    S.fill({ 'list.count': list.length });

    var keep = list.filter(function (a) { return a.id === selectedId; })[0];
    showDetail(keep || list[0]);
  }

  /**
   * Busca os alertas e desenha a tela.
   * @param {boolean} silent true nas recargas automáticas: não troca a tela por "carregando"
   */
  function load(silent) {
    if (!silent) {
      S.setState('alerts', 'loading');
      S.setState('detail', 'loading');
      S.setState('chart', 'loading');
    }

    var ctx = Ctx.get();
    return Api.alerts({
      company_id: ctx.company_id,
      reservoir_id: ctx.reservoir_id,
      severity: selSeverity.value,
      status: selStatus.value
    }).then(function (r) {
      var d = r.data;
      var c = d.counts;

      S.fill({
        'active.value': F.int(c.active), 'active.foot': c.active ? 'Requerem ação' : 'Nenhum em aberto',
        'critical.value': F.int(c.critical),
        'critical.foot': c.active ? F.pct(c.critical / c.active * 100) + ' dos abertos' : '—',
        'attention.value': F.int(c.attention),
        'attention.foot': c.active ? F.pct(c.attention / c.active * 100) + ' dos abertos' : '—',
        'resolved.value': F.int(c.resolved), 'resolved.foot': 'Nos últimos 7 dias',
        'avg.value': c.avg_minutes === null ? '—' : F.int(c.avg_minutes),
        'avg.foot': c.avg_minutes === null ? 'Nenhum alerta resolvido em 7 dias' : 'Da detecção à resolução',
        'period': d.period
      });

      rows = d.alerts;
      renderRows();

      /* ------------------- alertas dos últimos 7 dias (barras empilhadas) */
      S.setState('chart', 'ready');
      G.guard('grafico-alertas', function () {
        var ch = d.chart;
        G.create('grafico-alertas', {
          type: 'bar',
          data: {
            labels: ch.labels,
            datasets: [
              G.bar('Abertos — críticos', ch.critical, G.colors.danger, { radius: 0, maxThickness: 22 }),
              G.bar('Abertos — atenção', ch.attention, G.colors.warning, { radius: 0, maxThickness: 22 }),
              G.bar('Abertos — informação', ch.info, '#3b82f6', { radius: 0, maxThickness: 22 }),
              G.bar('Resolvidos', ch.resolved, G.colors.success, { radius: 4, maxThickness: 22 })
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { stacked: true, grid: { display: false }, ticks: { color: G.colors.axis }, border: { display: false } },
              y: { stacked: true, beginAtZero: true, suggestedMax: 4, grid: { color: G.colors.grid }, ticks: { color: G.colors.axis, precision: 0 }, border: { display: false } }
            },
            plugins: G.plugins('', 0)
          }
        });
      });

      S.setUpdated(r.meta);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('alerts', 'error', err.message);
      S.setState('detail', 'error', err.message);
      S.setState('chart', 'error', err.message);
    });
  }

  /** Envia "assumir" ou "resolver" e recarrega a lista com o que ficou gravado. */
  function act(action, titulo) {
    if (!selectedId) return;

    btnAck.disabled = true;
    btnResolve.disabled = true;

    Api.alertAction(action, selectedId).then(function () {
      S.notify(titulo, 'A alteração foi gravada no alerta ' + selectedId + '.', 'info');
      return load(true);
    }).catch(function (err) {
      S.notify('Não foi possível concluir', err.message, 'error');
      return load(true);
    });
  }

  /* ----------------------------------------------------------- eventos */

  selCompany.addEventListener('change', function () {
    Ctx.set({ company_id: selCompany.value });
    Api.reservoirs(selCompany.value).then(function (r) {
      fillSelect(selReservoir, r.data.reservoirs, 'Todas as represas');
      selReservoir.value = Ctx.reconcile(r.data.reservoirs).reservoir_id;
      load();
    });
  });

  selReservoir.addEventListener('change', function () {
    Ctx.set({ reservoir_id: selReservoir.value });
    load();
  });

  selSeverity.addEventListener('change', function () { load(); });
  selStatus.addEventListener('change', function () { load(); });
  search.addEventListener('input', renderRows);

  document.querySelector('[data-rows]').addEventListener('click', function (e) {
    var tr = e.target.closest('[data-alert]');
    if (!tr) return;
    var a = rows.filter(function (x) { return x.id === tr.getAttribute('data-alert'); })[0];
    if (a) showDetail(a);
  });

  btnAck.addEventListener('click', function () { act('acknowledge', 'Alerta assumido'); });
  btnResolve.addEventListener('click', function () { act('resolve', 'Alerta resolvido'); });

  S.onRetry('alerts', function () { load(); });
  S.onRetry('detail', function () { load(); });
  S.onRetry('chart', function () { load(); });
  S.onReload(function () { return load(true); });   // recarga automática: mantém a tela e o alerta selecionado

  /* ------------------------------------------------------------- início */
  Api.companies()
    .then(function (r) {
      fillSelect(selCompany, r.data.companies, 'Todas as empresas');
      selCompany.value = Ctx.get().company_id;
      return Api.reservoirs(Ctx.get().company_id);
    })
    .then(function (r) {
      fillSelect(selReservoir, r.data.reservoirs, 'Todas as represas');
      selReservoir.value = Ctx.reconcile(r.data.reservoirs).reservoir_id;
      return load();
    })
    .catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('alerts', 'error', err.message);
    });
})();
