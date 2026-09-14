/**
 * Aquapulse — contexto de análise (empresa, represa, período).
 *
 * O contexto é preservado durante a navegação entre as telas para que o
 * usuário não precise reescolher a represa a cada página.
 *
 * ARMAZENAMENTO: apenas IDs de contexto vão para o sessionStorage.
 * Nenhum dado sensível, token ou informação de sessão é guardado no navegador.
 *
 * sessionStorage dura enquanto a aba estiver aberta: fechar a aba zera o contexto.
 * Exposto como window.AqContext (get, set, requireReservoir, reconcile).
 */
window.AqContext = (function () {
  'use strict';

  var KEY = 'aq.context';                                             // nome da chave no sessionStorage

  var defaults = {                                                    // contexto inicial: tudo consolidado, últimos 7 dias
    company_id: 'all',
    reservoir_id: 'all',
    period: '7d'
  };

  /** Lê o contexto salvo, tolerando armazenamento indisponível. */
  function read() {
    try {                                                             // sessionStorage pode lançar erro (modo privado, bloqueio do navegador)
      var raw = sessionStorage.getItem(KEY);
      if (!raw) return Object.assign({}, defaults);                   // nada salvo: cópia dos padrões (Object.assign evita alterar o objeto original)
      var parsed = JSON.parse(raw);
      return {                                                        // aceita cada campo só se for texto; senão usa o padrão
        company_id: typeof parsed.company_id === 'string' ? parsed.company_id : defaults.company_id,
        reservoir_id: typeof parsed.reservoir_id === 'string' ? parsed.reservoir_id : defaults.reservoir_id,
        period: typeof parsed.period === 'string' ? parsed.period : defaults.period
      };
    } catch (e) {                                                     // JSON corrompido ou storage bloqueado
      return Object.assign({}, defaults);
    }
  }

  /** Grava o contexto (silencioso se o armazenamento estiver bloqueado). */
  function write(ctx) {
    try {
      sessionStorage.setItem(KEY, JSON.stringify({                    // grava só os três campos conhecidos
        company_id: ctx.company_id,
        reservoir_id: ctx.reservoir_id,
        period: ctx.period
      }));
    } catch (e) { /* modo privado ou storage desabilitado: segue sem persistir */ }
  }

  var state = read();                                                 // estado atual em memória, carregado uma vez ao abrir a página

  /** Parâmetros da URL têm prioridade sobre o contexto salvo. */
  (function applyUrl() {
    var params = new URLSearchParams(window.location.search);         // ex.: vazao.php?reservoir_id=rio-verde (link vindo da tela de mapas)
    ['company_id', 'reservoir_id', 'period'].forEach(function (k) {
      var v = params.get(k);
      if (v) state[k] = v;                                            // só sobrescreve o que veio na URL (em memória; não grava no storage)
    });
  })();

  /** Devolve uma CÓPIA do contexto, para ninguém alterar o estado sem passar por set(). */
  function get() {
    return Object.assign({}, state);
  }

  /** Altera parte do contexto (ex.: set({period: '30d'})), grava e devolve o resultado. */
  function set(patch) {
    Object.assign(state, patch || {});                                // mescla só os campos informados
    write(state);
    return get();
  }

  /**
   * Para as telas detalhadas: exige uma represa específica.
   * Se o contexto estiver em "todas", devolve a primeira represa da lista.
   *
   * @param {Array} list represas disponíveis vindas da API
   * @returns {string} ID da represa a usar ('' se a lista estiver vazia)
   */
  function requireReservoir(list) {
    if (state.reservoir_id && state.reservoir_id !== 'all') {         // já existe uma represa específica escolhida
      // confirma que a represa ainda existe na lista atual
      var found = (list || []).some(function (r) { return r.id === state.reservoir_id; });
      if (found || !list) return state.reservoir_id;
    }
    if (list && list.length) {                                        // "todas" ou represa inexistente: escolhe a primeira da lista
      set({ reservoir_id: list[0].id });
      return list[0].id;
    }
    return '';                                                        // nenhuma represa disponível
  }

  /**
   * Ao trocar de empresa, a represa selecionada pode não pertencer mais a ela.
   * Nesse caso a seleção é redefinida de forma previsível (volta para "todas").
   */
  function reconcile(list) {
    if (state.reservoir_id === 'all') return get();                   // "todas" é sempre válido

    var belongs = (list || []).some(function (r) { return r.id === state.reservoir_id; }); // a represa está entre as da nova empresa?
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
