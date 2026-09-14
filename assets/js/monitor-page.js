/**
 * Aquapulse — base compartilhada das telas detalhadas de Monitoramento.
 *
 * As oito telas repetem o mesmo ciclo: carregar as represas, exigir uma
 * represa específica, aplicar o período e recarregar ao trocar os filtros.
 * Essa lógica vive aqui uma única vez.
 *
 * A represa escolhida é preservada durante a navegação entre as telas
 * (AqContext), então o usuário não precisa reescolher a cada página.
 *
 * Como uma tela usa (ex.: pages/flow.js):
 *   AqMonitorPage({
 *     scopes: ['realtime', ...],          // blocos com estados carregando/erro
 *     fetch: AqApi.flow,                  // função que chama o endpoint
 *     render: function (data, meta) {...} // desenha os dados recebidos
 *     periodId / periodParam / extraParams // opcionais
 *   });
 *
 * @param {object} config configuração da tela (ver acima)
 * @returns {{reload: Function}} permite à tela recarregar os dados quando precisar
 */
window.AqMonitorPage = function (config) {
  'use strict';

  var S = window.AqShell;                                             // atalhos para os módulos globais
  var Api = window.AqApi;
  var Ctx = window.AqContext;
  var G = window.AqCharts;

  var selReservoir = document.getElementById('filtro-represa');       // select criado por aq_monitor_bar() no PHP
  var selPeriod = document.getElementById(config.periodId || 'filtro-periodo'); // select de período (ou horizonte/sistemas, conforme a tela)
  var scopes = config.scopes || [];                                   // nomes dos blocos que alternam entre carregando/pronto/erro

  /** Preenche o seletor de represas (sem a opção "todas"). */
  function loadReservoirs() {
    return Api.reservoirs('all').then(function (r) {                  // GET reservoirs.php?company_id=all
      var list = r.data.reservoirs;

      selReservoir.innerHTML = list.map(function (i) {                // uma <option> por represa; esc() protege os textos
        return '<option value="' + S.esc(i.id) + '">' + S.esc(i.name) + '</option>';
      }).join('');

      // telas detalhadas nunca consolidam: exigem uma represa específica
      var id = Ctx.requireReservoir(list);
      selReservoir.value = id;
      return id;                                                      // o valor segue para o próximo .then() da cadeia
    });
  }

  /** Monta os parâmetros da requisição a partir do contexto e dos seletores. */
  function currentParams() {
    var ctx = Ctx.get();
    var params = { reservoir_id: ctx.reservoir_id };

    if (selPeriod) {
      params[config.periodParam || 'period'] = selPeriod.value;       // normalmente "period"; na tela de duração vira "horizon"
    }
    if (typeof config.extraParams === 'function') {
      Object.assign(params, config.extraParams());                    // parâmetros extras da tela (ex.: current/previous no comparativo)
    }
    return params;
  }

  /**
   * Busca e desenha os dados.
   * Fluxo: blocos em "carregando" -> chamada à API -> cabeçalho comum ->
   * blocos visíveis -> render() da tela -> rótulo "Atualizado há...".
   */
  function load() {
    scopes.forEach(function (s) { S.setState(s, 'loading'); });

    return config.fetch(currentParams()).then(function (r) {
      var d = r.data;

      // cabeçalho comum: código e situação da telemetria
      if (d.reservoir) {
        S.fill({                                                      // preenche os elementos data-field da barra de contexto
          'reservoir.code': d.reservoir.code,
          'reservoir.telemetry': d.reservoir.telemetry_label
        });
        document.querySelectorAll('[data-field-class="reservoir.telemetry"]').forEach(function (el) {
          el.className = 'aq-dot aq-dot--' + (d.reservoir.telemetry === 'online' ? 'normal' : 'attention'); // bolinha verde (online) ou amarela (parcial)
        });
      }

      // O conteúdo precisa estar visível ANTES de criar os gráficos: um canvas
      // dentro de um contêiner oculto nasce com tamanho zero.
      scopes.forEach(function (s) { S.setState(s, 'ready'); });

      // Uma exceção no meio do render() deixava todos os escopos em "ready"
      // com os canvases seguintes em branco. Agora a falha é registrada e
      // apenas os cartões que ficaram sem gráfico passam para o estado de erro.
      try {
        config.render(d, r.meta);
      } catch (e) {
        console.error('[AqMonitorPage] falha ao montar a tela:', e);
        scopes.forEach(function (s) {
          if (!G.scopeReady(s)) {                                     // só marca erro nos blocos cujo gráfico não foi criado
            S.setState(s, 'error', 'Não foi possível carregar este bloco.');
          }
        });
      }

      S.setUpdated(r.meta.generated_at, r.meta.updated_label);        // atualiza "Atualizado há 2 min" na topbar
    }).catch(function (err) {
      if (Api.isAbort(err)) return;                                   // requisição cancelada por outra mais nova: não é erro

      // filtro inválido e ausência de dados recebem tratamento distinto
      var state = (err.code === 'NO_DATA') ? 'empty' : 'error';       // NO_DATA (404 da API) mostra "sem dados"; o resto mostra erro
      scopes.forEach(function (s) { S.setState(s, state, err.message); });

      if (state === 'error') {
        S.notify('Não foi possível carregar', err.message, 'error');  // aviso flutuante (toast)
      }
    });
  }

  /* --------------------------------------------------------------- eventos */

  if (selReservoir) {
    selReservoir.addEventListener('change', function () {             // usuário escolheu outra represa
      Ctx.set({ reservoir_id: selReservoir.value });                  // guarda a escolha para as próximas telas
      load();
    });
  }

  if (selPeriod) {
    selPeriod.addEventListener('change', function () {                // usuário trocou o período
      if (config.periodParam !== 'horizon') {                         // o horizonte de previsão não é salvo como "período" do contexto
        Ctx.set({ period: selPeriod.value });
      }
      load();
    });
  }

  scopes.forEach(function (s) { S.onRetry(s, load); });               // botões "Tentar novamente" de cada bloco recarregam os dados
  S.onReload(load);                                                   // botão de atualizar da topbar também

  /* ---------------------------------------------------------------- início */
  loadReservoirs()                                                    // 1. carrega as represas
    .then(load)                                                       // 2. carrega os dados da tela
    .catch(function (err) {                                           // falha ao listar represas: todos os blocos em erro
      if (Api.isAbort(err)) return;
      scopes.forEach(function (s) { S.setState(s, 'error', err.message); });
    });

  return { reload: load };
};
