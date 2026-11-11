/** Aquapulse — Relatórios. */
/*
 * Página: dashboard/relatorios.php
 * API: GET api/v1/reports.php            lista os relatórios gerados
 *      POST api/v1/reports.php           registra um novo relatório
 *      GET api/v1/reports.php?download=  baixa o CSV montado no servidor
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var Api = window.AqApi;
  var Ctx = window.AqContext;

  var selCompany = document.getElementById('filtro-empresa');
  var selReservoir = document.getElementById('filtro-represa');
  var selType = document.getElementById('filtro-tipo');
  var search = document.getElementById('busca-relatorio');
  var form = document.getElementById('form-relatorio');

  var currentRows = [];     // relatórios recebidos (base para a busca por texto)

  var ICONS = {
    'file-text': '<path d="M14 3.5H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5L14 3.5Z"/><path d="M14 3.5V9h5"/>',
    droplet: '<path d="M12 3.5c3.2 3.3 5.5 6 5.5 8.9A5.5 5.5 0 0 1 12 18a5.5 5.5 0 0 1-5.5-5.6c0-2.9 2.3-5.6 5.5-8.9Z"/>',
    waves: '<path d="M2 7.5c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/><path d="M2 13c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/>'
  };

  function icon(name) {
    return '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
      + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
      + (ICONS[name] || ICONS['file-text']) + '</svg>';
  }

  function options(items, allLabel) {
    return (allLabel ? '<option value="all">' + allLabel + '</option>' : '')
      + items.map(function (i) {
        return '<option value="' + S.esc(i.id) + '">' + S.esc(i.name) + '</option>';
      }).join('');
  }

  /** Data no formato dos campos type="date" (AAAA-MM-DD), deslocada em dias. */
  function isoDate(base, deltaDias) {
    var d = new Date(base + 'T12:00:00');
    d.setDate(d.getDate() + deltaDias);
    return d.toISOString().slice(0, 10);
  }

  /* ---------------------------------------------------------- carregar */

  function applySearch(rows) {
    var term = (search.value || '').trim().toLowerCase();
    if (!term) return rows;
    return rows.filter(function (r) {
      return (r.id + ' ' + r.name + ' ' + r.reservoir + ' ' + r.type_label).toLowerCase().indexOf(term) >= 0;
    });
  }

  function renderRows() {
    var rows = applySearch(currentRows);

    if (!rows.length) {
      S.setState('reports', 'empty');
      return;
    }
    S.setState('reports', 'ready');

    document.querySelector('[data-rows]').innerHTML = rows.map(function (r) {
      return '<tr>'
        + '<td><span class="aq-table__name"><span class="aq-table__icon">' + icon(r.icon) + '</span>'
        + S.esc(r.id) + ' · ' + S.esc(r.name) + '</span></td>'
        + '<td>' + S.esc(r.type_label) + '</td>'
        + '<td>' + S.esc(r.reservoir) + '</td>'
        + '<td class="is-nowrap">' + S.esc(r.period) + '</td>'
        + '<td class="is-nowrap">' + S.esc(r.generated_at) + '</td>'
        + '<td>' + S.esc(r.owner) + '</td>'
        + '<td><a class="aq-btn aq-btn--ghost aq-btn--icon" href="' + S.esc(Api.reportUrl(r.id)) + '"'
        + ' aria-label="Baixar ' + S.esc(r.id) + ' em CSV">'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
        + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<path d="M12 3.5v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4.5 20.5h15"/></svg></a></td>'
        + '</tr>';
    }).join('');

    S.fill({ 'list.count': rows.length });
  }

  /** @param {boolean} silent true nas recargas automáticas */
  function load(silent) {
    if (silent !== true) S.setState('reports', 'loading');
    var ctx = Ctx.get();

    return Api.reports({
      company_id: ctx.company_id,
      reservoir_id: ctx.reservoir_id,
      type: selType.value
    }).then(function (r) {
      var d = r.data;

      S.fill({
        'total.value': F.int(d.summary.total), 'total.foot': 'No contexto selecionado',
        'operational.value': F.int(d.summary.operational), 'operational.foot': 'Todas as grandezas',
        'hydrological.value': F.int(d.summary.hydrological), 'hydrological.foot': 'Nível, volume, vazão e chuva',
        'quality.value': F.int(d.summary.quality), 'quality.foot': 'pH e temperatura'
      });

      currentRows = d.reports;
      renderRows();

      // período padrão do formulário: os últimos 7 dias, até hoje (data do servidor)
      if (!form.elements.to.value) {
        form.elements.to.value = d.today;
        form.elements.from.value = isoDate(d.today, -6);
      }
      form.elements.to.max = d.today;
      form.elements.from.max = d.today;

      S.setUpdated(r.meta);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('reports', 'error', err.message);
    });
  }

  /** Preenche os seletores de represa (filtro e formulário) da empresa escolhida. */
  function loadReservoirs() {
    return Api.reservoirs(Ctx.get().company_id).then(function (r) {
      selReservoir.innerHTML = options(r.data.reservoirs, 'Todas as represas');
      selReservoir.value = Ctx.reconcile(r.data.reservoirs).reservoir_id;
      form.elements.reservoir_id.innerHTML = options(r.data.reservoirs);

      var atual = Ctx.get().reservoir_id;
      if (atual !== 'all') form.elements.reservoir_id.value = atual;
    });
  }

  /* ----------------------------------------------------------- eventos */

  selCompany.addEventListener('change', function () {
    Ctx.set({ company_id: selCompany.value });
    loadReservoirs().then(function () { load(); });
  });

  selReservoir.addEventListener('change', function () {
    Ctx.set({ reservoir_id: selReservoir.value });
    if (selReservoir.value !== 'all') form.elements.reservoir_id.value = selReservoir.value;
    load();
  });

  selType.addEventListener('change', function () { load(); });
  search.addEventListener('input', renderRows);

  // gerar: registra o relatório no banco e inicia o download do CSV
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    S.showErrors(form, {});

    Api.createReport({
      reservoir_id: form.elements.reservoir_id.value,
      type: form.elements.type.value,
      from: form.elements.from.value,
      to: form.elements.to.value
    }).then(function (r) {
      S.closeModal('modal-relatorio');
      S.notify('Relatório gerado', r.data.id + ' foi registrado e o download foi iniciado.', 'info');
      window.location.href = Api.reportUrl(r.data.id);   // resposta com "attachment": o navegador baixa sem sair da página
      return load(true);
    }).catch(function (err) {
      if (err.code === 'VALIDATION_ERROR') {
        S.showErrors(form, err.details);
      } else {
        S.notify('Não foi possível gerar', err.message, 'error');
      }
    });
  });

  S.onRetry('reports', function () { load(); });
  S.onReload(function () { return load(true); });

  /* ------------------------------------------------------------- início */
  Api.companies()
    .then(function (r) {
      selCompany.innerHTML = options(r.data.companies, 'Todas as empresas');
      selCompany.value = Ctx.get().company_id;
      return loadReservoirs();
    })
    .then(function () { return load(); })
    .catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('reports', 'error', err.message);
    });
})();
