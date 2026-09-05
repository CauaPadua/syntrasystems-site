/** Aquapulse — Central de alertas. */
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

  var rows = [];
  var selectedId = null;

  /*
   * Ações locais (assumir / resolver) em modo DEMONSTRATIVO.
   * Vivem apenas nesta sessão do navegador — não há persistência real.
   */
  var DEMO_KEY = 'aq.demo.alerts';

  function readLocal() {
    try { return JSON.parse(sessionStorage.getItem(DEMO_KEY) || '{}'); } catch (e) { return {}; }
  }
  function writeLocal(map) {
    try { sessionStorage.setItem(DEMO_KEY, JSON.stringify(map)); } catch (e) { /* segue sem persistir */ }
  }
  function localStatus(id, fallback) {
    var m = readLocal();
    return m[id] || fallback;
  }

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

    var st = localStatus(a.id, a.status);
    var stLabel = st === 'resolved' ? 'Resolvido' : (st === 'analysis' ? 'Em análise' : 'Novo');

    S.fill({
      'detail.icon': { html: '<span style="color:var(--aq-' + (a.severity === 'critical' ? 'danger' : a.severity === 'attention' ? 'warning' : 'info') + ')">' + sevIcon(a.severity) + '</span>' },
      'detail.title': a.title,
      'detail.severity': { html: S.badge(a.severity_label, a.severity) },
      'detail.reservoir': 'Represa ' + a.reservoir + ' · ' + stLabel,
      'detail.value': a.current_value,
      'detail.detail': a.detail,
      'detail.threshold': a.threshold,
      'detail.threshold_detail': a.threshold_detail
    });

    document.querySelector('[data-timeline]').innerHTML = a.timeline.map(function (t) {
      var color = t.done ? 'var(--aq-primary)' : 'var(--aq-border)';
      return '<li style="display:flex;gap:12px">'
        + '<span style="width:11px;height:11px;border-radius:50%;flex:none;margin-top:4px;background:' + color + '"></span>'
        + '<div><strong style="display:block;font-size:.82rem;color:var(--aq-text-secondary)">' + S.esc(t.at) + '</strong>'
        + '<span style="font-size:.86rem">' + S.esc(t.text) + '</span></div></li>';
    }).join('');

    // destaca a linha selecionada
    document.querySelectorAll('[data-rows] tr').forEach(function (tr) {
      tr.classList.toggle('is-selected', tr.getAttribute('data-alert') === a.id);
    });
  }

  /* --------------------------------------------------------- renderização */

  function applySearch(list) {
    var term = (search.value || '').trim().toLowerCase();
    if (!term) return list;
    return list.filter(function (a) {
      return (a.title + ' ' + a.reservoir + ' ' + a.metric).toLowerCase().indexOf(term) >= 0;
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
      var st = localStatus(a.id, a.status);
      var stLabel = st === 'resolved' ? 'Resolvido' : (st === 'analysis' ? 'Em análise' : 'Novo');
      var color = a.severity === 'critical' ? 'danger' : (a.severity === 'attention' ? 'warning' : 'info');

      return '<tr data-alert="' + S.esc(a.id) + '" style="cursor:pointer">'
        + '<td><span class="aq-status-text" style="color:var(--aq-' + color + ')">'
        + sevIcon(a.severity) + S.esc(a.severity_label) + '</span></td>'
        + '<td>' + S.esc(a.title) + '</td>'
        + '<td>' + S.esc(a.reservoir) + '</td>'
        + '<td>' + S.esc(a.metric) + '</td>'
        + '<td class="is-nowrap">' + S.esc(a.detected_at) + '</td>'
        + '<td>' + S.esc(a.owner) + '</td>'
        + '<td>' + S.badge(stLabel, statusKey(st)) + '</td>'
        + '</tr>';
    }).join('');

    S.fill({
      'pagination.count': list.length,
      'pagination.range': '1–' + list.length + ' de ' + list.length + ' alertas'
    });

    var keep = list.filter(function (a) { return a.id === selectedId; })[0];
    showDetail(keep || list[0]);
  }

  function load() {
    S.setState('alerts', 'loading');
    S.setState('detail', 'loading');

    var ctx = Ctx.get();
    return Api.alerts({
      company_id: ctx.company_id,
      reservoir_id: ctx.reservoir_id,
      severity: selSeverity.value,
      status: selStatus.value
    }).then(function (r) {
      var d = r.data;

      S.fill({
        'active.value': F.int(d.counts.active), 'active.foot': 'Requerem atenção',
        'critical.value': F.int(d.counts.critical),
        'critical.foot': d.counts.active ? F.pct(d.counts.critical / d.counts.active * 100) + ' do total ativo' : '—',
        'attention.value': F.int(d.counts.attention),
        'attention.foot': d.counts.active ? F.pct(d.counts.attention / d.counts.active * 100) + ' do total ativo' : '—',
        'resolved.value': F.int(d.counts.resolved), 'resolved.foot': 'Desde 00:00',
        'avg.value': F.int(d.counts.avg_minutes), 'avg.foot': 'Últimos 7 dias'
      });

      rows = d.alerts;
      renderRows();

      /* -------------------------- alertas dos últimos 7 dias (empilhado) */
      var c = d.chart;
      G.create('grafico-alertas', {
        type: 'bar',
        data: {
          labels: c.labels,
          datasets: [
            G.bar('Críticos', c.critical, G.colors.danger, { radius: 0, maxThickness: 22 }),
            G.bar('Atenção', c.attention, G.colors.warning, { radius: 0, maxThickness: 22 }),
            G.bar('Informação', c.info, '#3b82f6', { radius: 0, maxThickness: 22 }),
            G.bar('Resolvidos', c.resolved, G.colors.success, { radius: 4, maxThickness: 22 })
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            x: { stacked: true, grid: { display: false }, ticks: { color: G.colors.axis }, border: { display: false } },
            y: { stacked: true, beginAtZero: true, max: 10, grid: { color: G.colors.grid }, ticks: { color: G.colors.axis, stepSize: 2 }, border: { display: false } }
          },
          plugins: G.plugins('', 0)
        }
      });

      /* --------------------------------------- canais de notificação */
      document.querySelector('[data-channels]').innerHTML = (d.channels || []).map(function (ch) {
        return '<div class="aq-list__item">'
          + '<span class="aq-list__icon aq-list__icon--info" aria-hidden="true">'
          + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
          + ' stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/>'
          + '<path d="m3.2 7.1 8 5.4a1.6 1.6 0 0 0 1.6 0l8-5.4"/></svg></span>'
          + '<div class="aq-list__body"><p class="aq-list__title">' + S.esc(ch.label) + '</p>'
          + '<p class="aq-list__meta">' + S.esc(ch.target) + '</p></div>'
          + '<div class="aq-list__side">' + S.badge(ch.enabled ? 'Ativo' : 'Inativo', ch.enabled ? 'normal' : 'neutral') + '</div></div>';
      }).join('');

      var upd = document.querySelector('[data-context-updated]');
      if (upd) upd.textContent = r.meta.updated_label || '';
      S.setUpdated(r.meta.generated_at, r.meta.updated_label);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('alerts', 'error', err.message);
      S.setState('detail', 'error', err.message);
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

  selSeverity.addEventListener('change', load);
  selStatus.addEventListener('change', load);
  search.addEventListener('input', renderRows);

  document.addEventListener('click', function (e) {
    var tr = e.target.closest('[data-alert]');
    if (tr) {
      var a = rows.filter(function (x) { return x.id === tr.getAttribute('data-alert'); })[0];
      if (a) showDetail(a);
      return;
    }

    if (e.target.closest('[data-ack]')) {
      if (!selectedId) return;
      var m = readLocal(); m[selectedId] = 'analysis'; writeLocal(m);
      S.notify('Alerta assumido', 'Registro demonstrativo mantido apenas nesta sessão.', 'info');
      renderRows();
      return;
    }

    if (e.target.closest('[data-resolve]')) {
      if (!selectedId) return;
      var mm = readLocal(); mm[selectedId] = 'resolved'; writeLocal(mm);
      S.notify('Alerta resolvido', 'Registro demonstrativo mantido apenas nesta sessão.', 'info');
      renderRows();
    }
  });

  S.onRetry('alerts', load);
  S.onRetry('detail', load);
  S.onReload(load);

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
