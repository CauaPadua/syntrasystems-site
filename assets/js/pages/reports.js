/** Aquapulse — Relatórios. */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var Api = window.AqApi;
  var Ctx = window.AqContext;

  var selCompany = document.getElementById('filtro-empresa');
  var selReservoir = document.getElementById('filtro-represa');
  var selType = document.getElementById('filtro-tipo');
  var selStatus = document.getElementById('filtro-status');
  var search = document.getElementById('busca-relatorio');

  var currentRows = [];

  var ICONS = {
    'file-text': '<path d="M14 3.5H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5L14 3.5Z"/><path d="M14 3.5V9h5"/>',
    droplet: '<path d="M12 3.5c3.2 3.3 5.5 6 5.5 8.9A5.5 5.5 0 0 1 12 18a5.5 5.5 0 0 1-5.5-5.6c0-2.9 2.3-5.6 5.5-8.9Z"/>',
    waves: '<path d="M2 7.5c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/><path d="M2 13c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/>',
    'chart-up': '<path d="M3 21h18"/><path d="M11 21V9"/><path d="M16 21v-9"/><path d="M14 4h6v6"/><path d="M20 4l-7.5 7.5"/>',
    calendar: '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="M3.5 10h17"/>'
  };

  function icon(name) {
    return '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
      + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
      + (ICONS[name] || ICONS['file-text']) + '</svg>';
  }

  function fillSelect(select, items, allLabel) {
    select.innerHTML = '<option value="all">' + allLabel + '</option>'
      + items.map(function (i) {
        return '<option value="' + S.esc(i.id) + '">' + S.esc(i.name) + '</option>';
      }).join('');
  }

  /* ------------------------------------------------------- download CSV */

  /** Gera o CSV no próprio navegador a partir dos dados já carregados. */
  function downloadCsv(rows) {
    var head = ['Relatório', 'Tipo', 'Represa', 'Período', 'Gerado em', 'Responsável', 'Status'];
    var lines = [head.join(';')];

    rows.forEach(function (r) {
      lines.push([r.name, r.type_label, r.reservoir, r.period, r.generated_at, r.owner, r.status_label]
        .map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; })
        .join(';'));
    });

    // BOM para o Excel abrir os acentos corretamente
    var blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'aquapulse-relatorios.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  /* ---------------------------------------------------------- carregar */

  function applySearch(rows) {
    var term = (search.value || '').trim().toLowerCase();
    if (!term) return rows;
    return rows.filter(function (r) {
      return (r.name + ' ' + r.reservoir + ' ' + r.type_label).toLowerCase().indexOf(term) >= 0;
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
      var key = r.status === 'done' ? 'normal' : (r.status === 'processing' ? 'attention' : 'info');
      return '<tr>'
        + '<td><span class="aq-table__name"><span class="aq-table__icon">' + icon(r.icon) + '</span>'
        + S.esc(r.name) + '</span></td>'
        + '<td>' + S.esc(r.type_label) + '</td>'
        + '<td>' + S.esc(r.reservoir) + '</td>'
        + '<td>' + S.esc(r.period) + '</td>'
        + '<td class="is-nowrap">' + S.esc(r.generated_at) + '</td>'
        + '<td>' + S.esc(r.owner) + '</td>'
        + '<td>' + S.badge(r.status_label, key) + '</td>'
        + '<td><button class="aq-btn aq-btn--ghost aq-btn--icon" type="button" data-csv="' + S.esc(r.id) + '"'
        + ' aria-label="Baixar ' + S.esc(r.name) + ' em CSV">'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
        + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<path d="M12 3.5v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4.5 20.5h15"/></svg></button></td>'
        + '</tr>';
    }).join('');

    S.fill({
      'pagination.count': rows.length,
      'pagination.range': '1–' + rows.length + ' de ' + rows.length + ' registros'
    });
  }

  function load() {
    S.setState('reports', 'loading');
    var ctx = Ctx.get();

    return Api.reports({
      company_id: ctx.company_id,
      reservoir_id: ctx.reservoir_id,
      type: selType.value,
      status: selStatus.value
    }).then(function (r) {
      var d = r.data;

      S.fill({
        'total.value': F.int(d.summary.total), 'total.foot': 'Total no período',
        'done.value': F.int(d.summary.done), 'done.foot': F.pct(d.summary.done_pct) + ' do total',
        'processing.value': F.int(d.summary.processing), 'processing.foot': F.pct(d.summary.processing_pct) + ' do total',
        'scheduled.value': F.int(d.summary.scheduled), 'scheduled.foot': F.pct(d.summary.scheduled_pct) + ' do total'
      });

      currentRows = d.reports;
      renderRows();

      document.querySelector('[data-scheduled]').innerHTML = d.scheduled_reports.map(function (s) {
        return '<tr><td>' + S.esc(s.name) + '</td><td>' + S.esc(s.frequency) + '</td>'
          + '<td>' + S.esc(s.next_run) + '</td></tr>';
      }).join('');

      S.setUpdated(r.meta.generated_at, r.meta.updated_label);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('reports', 'error', err.message);
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

  selType.addEventListener('change', load);
  selStatus.addEventListener('change', load);
  search.addEventListener('input', renderRows);

  // download CSV por linha
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-csv]');
    if (!btn) return;
    var id = btn.getAttribute('data-csv');
    var row = currentRows.filter(function (r) { return r.id === id; });
    downloadCsv(row.length ? row : currentRows);
    S.notify('Download iniciado', 'Arquivo CSV gerado no navegador.', 'info');
  });

  // geração demonstrativa
  var form = document.getElementById('form-relatorio');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var formato = document.getElementById('novo-formato').value;
      S.closeModal('modal-relatorio');

      if (formato === 'csv') {
        downloadCsv(currentRows);
        S.notify('Relatório gerado', 'CSV baixado a partir dos dados carregados.', 'info');
      } else {
        S.notify('Modo demonstrativo', 'A geração de PDF no servidor será implementada com o banco de dados. Use Ctrl+P para imprimir esta página.', 'info');
      }
    });
  }

  S.onRetry('reports', load);
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
      S.setState('reports', 'error', err.message);
    });
})();
