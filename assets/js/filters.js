/**
 * Aquapulse — contexto de análise (empresa, represa, período).
 *
 * O contexto é preservado durante a navegação entre as telas para que o
 * usuário não precise reescolher a represa a cada página.
 *
 * ARMAZENAMENTO: apenas IDs de contexto vão para o sessionStorage.
 * Nenhum dado sensível, token ou informação de sessão é guardado no navegador.
 */
window.AqContext = (function () {
  'use strict';

  var KEY = 'aq.context';

  var defaults = {
    company_id: 'all',
    reservoir_id: 'all',
    period: '7d'
  };

  /** Lê o contexto salvo, tolerando armazenamento indisponível. */
  function read() {
    try {
      var raw = sessionStorage.getItem(KEY);
      if (!raw) return Object.assign({}, defaults);
      var parsed = JSON.parse(raw);
      return {
        company_id: typeof parsed.company_id === 'string' ? parsed.company_id : defaults.company_id,
        reservoir_id: typeof parsed.reservoir_id === 'string' ? parsed.reservoir_id : defaults.reservoir_id,
        period: typeof parsed.period === 'string' ? parsed.period : defaults.period
      };
    } catch (e) {
      return Object.assign({}, defaults);
    }
  }

  /** Grava o contexto (silencioso se o armazenamento estiver bloqueado). */
  function write(ctx) {
    try {
      sessionStorage.setItem(KEY, JSON.stringify({
        company_id: ctx.company_id,
        reservoir_id: ctx.reservoir_id,
        period: ctx.period
      }));
    } catch (e) { /* modo privado ou storage desabilitado: segue sem persistir */ }
  }

  var state = read();

  /** Parâmetros da URL têm prioridade sobre o contexto salvo. */
  (function applyUrl() {
    var params = new URLSearchParams(window.location.search);
    ['company_id', 'reservoir_id', 'period'].forEach(function (k) {
      var v = params.get(k);
      if (v) state[k] = v;
    });
  })();

  function get() {
    return Object.assign({}, state);
  }

  function set(patch) {
    Object.assign(state, patch || {});
    write(state);
    return get();
  }

  /**
   * Para as telas detalhadas: exige uma represa específica.
   * Se o contexto estiver em "todas", devolve a primeira represa da lista.
   */
  function requireReservoir(list) {
    if (state.reservoir_id && state.reservoir_id !== 'all') {
      // confirma que a represa ainda existe na lista atual
      var found = (list || []).some(function (r) { return r.id === state.reservoir_id; });
      if (found || !list) return state.reservoir_id;
    }
    if (list && list.length) {
      set({ reservoir_id: list[0].id });
      return list[0].id;
    }
    return '';
  }

  /**
   * Ao trocar de empresa, a represa selecionada pode não pertencer mais a ela.
   * Nesse caso a seleção é redefinida de forma previsível (volta para "todas").
   */
  function reconcile(list) {
    if (state.reservoir_id === 'all') return get();

    var belongs = (list || []).some(function (r) { return r.id === state.reservoir_id; });
    if (!belongs) {
      set({ reservoir_id: 'all' });
    }
    return get();
  }

  return {
    get: get,
    set: set,
    requireReservoir: requireReservoir,
    reconcile: reconcile
  };
})();
