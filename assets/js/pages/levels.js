/**
 * Aquapulse — Níveis (visão histórica, em cota).
 *
 * Usa o mesmo endpoint da tela detalhada de nível (api/v1/monitoring/level.php):
 * não há segunda fonte de dados. Página: dashboard/niveis.php. Diferente das
 * telas de monitoramento, tem filtro de empresa e trabalha em metros.
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

  var ESCOPOS = ['history', 'monthly'];     // blocos com estados de carregamento

  function fillCompanies(items) {
    selCompany.innerHTML = '<option value="all">Todas as empresas</option>'
      + items.map(function (i) { return '<option value="' + S.esc(i.id) + '">' + S.esc(i.name) + '</option>'; }).join('');
  }

  /** Preenche o seletor de represas (sem "todas") e devolve a represa a usar. */
  function fillReservoirs(items) {
    selReservoir.innerHTML = items.map(function (i) {
      return '<option value="' + S.esc(i.id) + '">' + S.esc(i.name) + '</option>';
    }).join('');
    return Ctx.requireReservoir(items);
  }

  /** Limites do eixo vertical: múltiplos de 2 m com uma folga em volta dos valores. */
  function eixo(valores) {
    return {
      min: Math.floor(Math.min.apply(null, valores) / 2) * 2 - 2,
      max: Math.ceil(Math.max.apply(null, valores) / 2) * 2 + 2
    };
  }

  /** Desenha a tela a partir dos dados de MonitoringService::level(). */
  function render(d) {
    ESCOPOS.forEach(function (s) { S.setState(s, 'ready'); });

    var k = d.kpis;
    var h = d.history;
    var margem = Math.round((h.cota_spill - k.cota.value) * 10) / 10;   // metros que faltam até a cota de vertimento

    S.fill({
      'cota.value': F.num(k.cota.value, 1), 'cota.foot': k.level.note,
      'cota.badge': { html: S.badge(k.level.status.label, k.level.status.key) },
      'used.value': F.num(k.level.value, 1), 'used.foot': 'da capacidade total',
      'variation.value': F.signed(k.variation.value, 1), 'variation.foot': 'nas últimas 24 h',
      'spill.value': F.num(h.cota_spill, 1), 'spill.foot': 'Cadastro da represa',
      'margin.value': F.num(margem, 1), 'margin.foot': 'até o vertimento',
      'status.value': { html: '<span class="aq-kpi__value--text aq-status--' + S.esc(k.level.status.key)
        + '">' + S.esc(k.level.status.label) + '</span>' },
      'status.foot': k.level.status.key === 'normal' ? 'Dentro da faixa normal' : 'Fora da faixa normal'
    });
    S.setRing('used', k.level.value);

    /* --------------------- histórico em cota, com as linhas dos limites */
    G.guard('grafico-niveis', function () {
      var ctx = document.getElementById('grafico-niveis').getContext('2d');
      var limites = eixo(h.cota.concat([h.cota_alert, h.cota_attention, h.cota_spill, h.cota_critical]));

      G.create('grafico-niveis', {
        type: 'line',
        data: {
          labels: h.labels,
          datasets: [G.line('Nível observado', h.cota, G.colors.primary, { fillCtx: ctx, alpha: 0.14 })]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ beginAtZero: false, min: limites.min, max: limites.max, step: 2, decimals: 0 }),
          plugins: G.plugins('m', 1, {
            annotation: {
              annotations: {
                vertimento: G.limitLine(h.cota_spill, '#38bdf8', 'Cota de vertimento ' + F.num(h.cota_spill, 1) + ' m', 'start'),
                critico: G.limitLine(h.cota_critical, G.colors.danger, 'Cota crítica ' + F.num(h.cota_critical, 1) + ' m', 'end'),
                atencao: G.limitLine(h.cota_attention, G.colors.warning, 'Cota de atenção ' + F.num(h.cota_attention, 1) + ' m', 'start', true),
                alerta: G.limitLine(h.cota_alert, '#fb923c', 'Cota de alerta ' + F.num(h.cota_alert, 1) + ' m', 'end', true)
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
    });

    /* ------------------------------------------- faixas e marcador */
    var marker = document.querySelector('[data-level-marker]');
    if (marker) marker.style.bottom = k.level.value + '%';

    var cores = { normal: 'var(--aq-primary)', attention: 'var(--aq-warning)', critical: 'var(--aq-danger)' };
    document.querySelector('[data-level-bands]').innerHTML = d.capacity.bands.map(function (b) {
      return '<li style="display:flex;align-items:flex-start;gap:9px">'
        + '<span style="width:13px;height:13px;border-radius:3px;background:' + cores[b.status] + ';flex:none;margin-top:2px"></span>'
        + '<div><strong style="display:block">' + S.esc(b.label) + '</strong>'
        + '<span style="color:var(--aq-text-secondary)">' + S.esc(b.range) + '</span></div></li>';
    }).join('');

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
    }).join('') || '<tr><td colspan="5">Nenhuma leitura de nível nos últimos 7 dias.</td></tr>';

    /* ------- comparativo mensal: maior, média e menor cota diária de cada mês */
    G.guard('grafico-mensal', function () {
      var m = d.monthly;
      var limites = eixo(m.max.concat(m.min));

      G.create('grafico-mensal', {
        type: 'line',
        data: {
          labels: m.labels,
          datasets: [
            G.line('Máxima', m.max, '#38bdf8', { dashed: true, width: 1.8, points: false }),
            G.line('Média', m.avg, G.colors.primary, { width: 2.4 }),
            G.line('Mínima', m.min, '#fb923c', { dashed: true, width: 1.8, points: false })
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ beginAtZero: false, min: limites.min, max: limites.max, step: 2, decimals: 0 }),
          plugins: G.plugins('m', 1)
        }
      });
    });

    /* ------------------------------------------- limites em cota */
    document.querySelector('[data-limits]').innerHTML = [
      { label: 'Cota de vertimento', value: h.cota_spill, color: '#38bdf8' },
      { label: 'Cota crítica', value: h.cota_critical, color: 'var(--aq-danger)' },
      { label: 'Cota de atenção', value: h.cota_attention, color: 'var(--aq-warning)' },
      { label: 'Cota de alerta', value: h.cota_alert, color: '#fb923c' }
    ].map(function (l) {
      return '<div class="aq-form-row">'
        + '<span style="display:flex;align-items:center;gap:10px">'
        + '<span class="aq-legend__key aq-legend__key--dashed" style="width:26px;color:' + l.color + '"></span>'
        + S.esc(l.label) + '</span>'
        + '<strong>' + F.num(l.value, 1) + ' m</strong></div>';
    }).join('');
  }

  /**
   * Busca os dados da represa e do período escolhidos.
   * @param {boolean} silent true na recarga automática: mantém a tela visível
   */
  function load(silent) {
    if (silent !== true) {
      ESCOPOS.forEach(function (s) { S.setState(s, 'loading'); });
    }

    return Api.level({ reservoir_id: Ctx.get().reservoir_id, period: selPeriod.value })
      .then(function (r) {
        try {
          render(r.data);
        } catch (e) {
          console.error('[Níveis] falha ao montar a tela:', e);
          ESCOPOS.forEach(function (s) {
            if (!G.scopeReady(s)) S.setState(s, 'error', 'Não foi possível carregar este bloco.');
          });
        }
        S.setUpdated(r.meta);
      })
      .catch(function (err) {
        if (Api.isAbort(err)) return;
        var estado = err.code === 'NO_DATA' ? 'empty' : 'error';
        ESCOPOS.forEach(function (s) { S.setState(s, estado, err.message); });
      });
  }

  /* ----------------------------------------------------------- eventos */

  /** Marca como ativo o atalho de período igual ao valor do seletor. */
  function syncChips() {
    document.querySelectorAll('[data-quick-periods] .aq-chip').forEach(function (c) {
      c.classList.toggle('is-active', c.getAttribute('data-period') === selPeriod.value);
    });
  }

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

  document.addEventListener('click', function (e) {
    var chip = e.target.closest('[data-quick-periods] .aq-chip');
    if (chip) {
      selPeriod.value = chip.getAttribute('data-period');
      Ctx.set({ period: selPeriod.value });
      syncChips();
      load();
      return;
    }

    // alterna entre o gráfico e a tabela equivalente
    var toggle = e.target.closest('[data-toggle-table]');
    if (toggle) {
      var table = document.querySelector('[data-chart-table]');
      table.hidden = !table.hidden;
      toggle.querySelector('span').textContent = table.hidden ? 'Ver tabela' : 'Ocultar tabela';
    }
  });

  ESCOPOS.forEach(function (s) { S.onRetry(s, function () { load(); }); });
  S.onReload(function () { return load(true); });

  /* ------------------------------------------------------------- início */
  Api.companies()
    .then(function (r) {
      fillCompanies(r.data.companies);
      selCompany.value = Ctx.get().company_id;
      return Api.reservoirs(Ctx.get().company_id);
    })
    .then(function (r) {
      selReservoir.value = fillReservoirs(r.data.reservoirs);
      // usa o período salvo no contexto se ele existir nesta tela; senão, 30 dias
      selPeriod.value = ['7d', '30d', '90d', '12m'].indexOf(Ctx.get().period) >= 0 ? Ctx.get().period : '30d';
      syncChips();
      return load();
    })
    .catch(function (err) {
      if (Api.isAbort(err)) return;
      ESCOPOS.forEach(function (s) { S.setState(s, 'error', err.message); });
    });
})();
