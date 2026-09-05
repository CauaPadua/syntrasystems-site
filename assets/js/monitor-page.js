/**
 * Aquapulse — base compartilhada das telas detalhadas de Monitoramento.
 *
 * As oito telas repetem o mesmo ciclo: carregar as represas, exigir uma
 * represa específica, aplicar o período e recarregar ao trocar os filtros.
 * Essa lógica vive aqui uma única vez.
 *
 * A represa escolhida é preservada durante a navegação entre as telas
 * (AqContext), então o usuário não precisa reescolher a cada página.
 */
window.AqMonitorPage = function (config) {
  'use strict';

  var S = window.AqShell;
  var Api = window.AqApi;
  var Ctx = window.AqContext;

  var selReservoir = document.getElementById('filtro-represa');
  var selPeriod = document.getElementById(config.periodId || 'filtro-periodo');
  var scopes = config.scopes || [];

  /** Preenche o seletor de represas (sem a opção "todas"). */
  function loadReservoirs() {
    return Api.reservoirs('all').then(function (r) {
      var list = r.data.reservoirs;

      selReservoir.innerHTML = list.map(function (i) {
        return '<option value="' + S.esc(i.id) + '">' + S.esc(i.name) + '</option>';
      }).join('');

      // telas detalhadas nunca consolidam: exigem uma represa específica
      var id = Ctx.requireReservoir(list);
      selReservoir.value = id;
      return id;
    });
  }

  function currentParams() {
    var ctx = Ctx.get();
    var params = { reservoir_id: ctx.reservoir_id };

    if (selPeriod) {
      params[config.periodParam || 'period'] = selPeriod.value;
    }
    if (typeof config.extraParams === 'function') {
      Object.assign(params, config.extraParams());
    }
    return params;
  }

  function load() {
    scopes.forEach(function (s) { S.setState(s, 'loading'); });

    return config.fetch(currentParams()).then(function (r) {
      var d = r.data;

      // cabeçalho comum: código e situação da telemetria
      if (d.reservoir) {
        S.fill({
          'reservoir.code': d.reservoir.code,
          'reservoir.telemetry': d.reservoir.telemetry_label
        });
        document.querySelectorAll('[data-field-class="reservoir.telemetry"]').forEach(function (el) {
          el.className = 'aq-dot aq-dot--' + (d.reservoir.telemetry === 'online' ? 'normal' : 'attention');
        });
      }

      // O conteúdo precisa estar visível ANTES de criar os gráficos: um canvas
      // dentro de um contêiner oculto nasce com tamanho zero.
      scopes.forEach(function (s) { S.setState(s, 'ready'); });

      config.render(d, r.meta);
      S.setUpdated(r.meta.generated_at, r.meta.updated_label);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;

      // filtro inválido e ausência de dados recebem tratamento distinto
      var state = (err.code === 'NO_DATA') ? 'empty' : 'error';
      scopes.forEach(function (s) { S.setState(s, state, err.message); });

      if (state === 'error') {
        S.notify('Não foi possível carregar', err.message, 'error');
      }
    });
  }

  /* --------------------------------------------------------------- eventos */

  if (selReservoir) {
    selReservoir.addEventListener('change', function () {
      Ctx.set({ reservoir_id: selReservoir.value });
      load();
    });
  }

  if (selPeriod) {
    selPeriod.addEventListener('change', function () {
      if (config.periodParam !== 'horizon') {
        Ctx.set({ period: selPeriod.value });
      }
      load();
    });
  }

  scopes.forEach(function (s) { S.onRetry(s, load); });
  S.onReload(load);

  /* ---------------------------------------------------------------- início */
  loadReservoirs()
    .then(load)
    .catch(function (err) {
      if (Api.isAbort(err)) return;
      scopes.forEach(function (s) { S.setState(s, 'error', err.message); });
    });

  return { reload: load };
};
