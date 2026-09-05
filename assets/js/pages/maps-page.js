/** Aquapulse — Mapas. */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var Api = window.AqApi;
  var Ctx = window.AqContext;

  var selCompany = document.getElementById('filtro-empresa');
  var search = document.getElementById('busca-represa');

  var allMarkers = [];
  var statusFilter = 'all';
  var selectedId = null;

  function visible() {
    var term = (search.value || '').trim().toLowerCase();
    return allMarkers.filter(function (m) {
      if (statusFilter !== 'all' && m.status.key !== statusFilter) return false;
      if (term && (m.name + ' ' + m.city).toLowerCase().indexOf(term) < 0) return false;
      return true;
    });
  }

  /* ---------------------------------------- painel da represa selecionada */

  function selectReservoir(m, keepView) {
    if (!m) {
      S.setState('panel', 'empty');
      return;
    }

    selectedId = m.id;
    S.setState('panel', 'ready');

    S.fill({
      'sel.name': m.name,
      'sel.status': { html: S.badge(m.status.label, m.status.key) },
      'sel.basin': m.basin,
      'sel.coords': m.coordinates,
      'sel.level.value': F.num(m.level, 1), 'sel.level.foot': 'Cota: ' + F.unit(m.cota, 'm'),
      'sel.flow.value': F.num(m.flow, 1), 'sel.flow.foot': 'Média 24h',
      'sel.ph.value': F.num(m.ph, 1), 'sel.ph.foot': '',
      'sel.ph.badge': { html: S.badge('Neutro', 'normal') },
      'sel.rain.value': F.num(m.rain, 1), 'sel.rain.foot': 'Na bacia',
      'sel.duration.value': F.int(m.duration), 'sel.duration.foot': 'Com o volume atual',
      'sel.operation.value': { html: '<span style="color:var(--aq-success)">' + m.status.label + '</span>' },
      'sel.operation.foot': 'Todas as condições dentro dos limites'
    });

    // o link leva ao monitoramento já com a represa escolhida
    Ctx.set({ reservoir_id: m.id });
    var link = document.querySelector('[data-monitor-link]');
    if (link) link.href = 'monitoramento/vazao.php?reservoir_id=' + encodeURIComponent(m.id);

    // destaca o cartão correspondente
    document.querySelectorAll('[data-reservoir-card]').forEach(function (c) {
      c.style.borderColor = c.getAttribute('data-reservoir-card') === m.id
        ? 'var(--aq-primary)' : 'var(--aq-border)';
    });

    // no primeiro carregamento preserva o enquadramento de todos os marcadores
    if (!keepView) window.AqMap.focus('mapa-principal', m);
  }

  /* --------------------------------------------------------- renderização */

  function renderCards(list) {
    document.querySelector('[data-reservoir-cards]').innerHTML = list.map(function (m) {
      return '<button class="aq-card" type="button" data-reservoir-card="' + S.esc(m.id) + '"'
        + ' style="text-align:left;cursor:pointer;transition:border-color 180ms ease">'
        + '<div style="display:flex;align-items:center;gap:11px;margin-bottom:12px">'
        + '<span class="aq-list__icon aq-list__icon--' + m.status.key + '" aria-hidden="true">'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
        + ' stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/>'
        + '<circle cx="12" cy="10" r="2.6"/></svg></span>'
        + '<strong style="flex:1 1 auto;font-size:.95rem">' + S.esc(m.name) + '</strong>'
        + S.badge(m.status.label, m.status.key) + '</div>'
        + '<div class="aq-form-row" style="padding:6px 0"><span>Município</span><strong>' + S.esc(m.city) + '</strong></div>'
        + '<div class="aq-form-row" style="padding:6px 0"><span>Última atualização</span><strong>' + S.esc(m.updated_at) + '</strong></div>'
        + '</button>';
    }).join('');
  }

  function refresh() {
    var list = visible();

    window.AqMap.render('mapa-principal', list, {
      onSelect: selectReservoir,
      zoomControl: true
    });

    renderCards(list);

    // mantém a seleção quando ela ainda estiver visível
    var keep = list.filter(function (m) { return m.id === selectedId; })[0];
    selectReservoir(keep || list[0] || null, true);
  }

  function load() {
    S.setState('panel', 'loading');

    return Api.mapMarkers(Ctx.get().company_id).then(function (r) {
      allMarkers = r.data.markers;

      if (!allMarkers.length) {
        S.setState('panel', 'empty');
        renderCards([]);
        return;
      }

      // respeita a represa já escolhida no contexto
      var ctxId = Ctx.get().reservoir_id;
      selectedId = allMarkers.some(function (m) { return m.id === ctxId; }) ? ctxId : allMarkers[0].id;

      refresh();
      S.setUpdated(r.meta.generated_at, r.meta.updated_label);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('panel', 'error', err.message);
      window.AqMap.showFallback('mapa-principal');
    });
  }

  /* ----------------------------------------------------------- eventos */

  selCompany.addEventListener('change', function () {
    Ctx.set({ company_id: selCompany.value, reservoir_id: 'all' });
    selectedId = null;
    load();
  });

  search.addEventListener('input', refresh);

  document.addEventListener('click', function (e) {
    var chip = e.target.closest('[data-status-filters] .aq-chip');
    if (chip) {
      statusFilter = chip.getAttribute('data-status');
      document.querySelectorAll('[data-status-filters] .aq-chip').forEach(function (c) {
        c.classList.toggle('is-active', c === chip);
      });
      refresh();
      return;
    }

    var card = e.target.closest('[data-reservoir-card]');
    if (card) {
      var id = card.getAttribute('data-reservoir-card');
      var m = allMarkers.filter(function (x) { return x.id === id; })[0];
      if (m) selectReservoir(m);
    }
  });

  S.onRetry('panel', load);
  S.onReload(load);

  /* ------------------------------------------------------------- início */
  Api.companies()
    .then(function (r) {
      selCompany.innerHTML = '<option value="all">Todas as empresas</option>'
        + r.data.companies.map(function (c) {
          return '<option value="' + S.esc(c.id) + '">' + S.esc(c.name) + '</option>';
        }).join('');
      selCompany.value = Ctx.get().company_id;
      return load();
    })
    .catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('panel', 'error', err.message);
    });
})();
