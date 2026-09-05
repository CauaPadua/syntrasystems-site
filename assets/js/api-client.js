/**
 * Aquapulse — cliente da API do sistema interno.
 *
 * Responsabilidades:
 *   - falar com os endpoints PHP na mesma origem (credentials: same-origin);
 *   - cancelar a requisição anterior do mesmo escopo com AbortController,
 *     evitando que uma resposta atrasada sobrescreva a atual;
 *   - traduzir erros da API em mensagens claras;
 *   - tratar sessão expirada (401) devolvendo o usuário ao login.
 *
 * Nada de token, senha ou dado de autenticação é guardado aqui:
 * a sessão vive no cookie HttpOnly, inacessível ao JavaScript.
 */
window.AqApi = (function () {
  'use strict';

  var base = document.body.getAttribute('data-api-base') || 'api/v1';

  /** Controllers ativos por escopo, para cancelar requisições superadas. */
  var controllers = {};

  /** Erro com código da API, para as telas reagirem de forma específica. */
  function ApiError(code, message, status) {
    this.name = 'ApiError';
    this.code = code;
    this.message = message;
    this.status = status;
  }
  ApiError.prototype = Object.create(Error.prototype);

  /** Monta a query string ignorando valores vazios. */
  function qs(params) {
    var parts = [];
    Object.keys(params || {}).forEach(function (k) {
      var v = params[k];
      if (v !== undefined && v !== null && v !== '') {
        parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
      }
    });
    return parts.length ? '?' + parts.join('&') : '';
  }

  /**
   * GET em um endpoint.
   *
   * @param {string} path   ex.: "/overview.php"
   * @param {object} params parâmetros de query
   * @param {string} scope  identificador para cancelamento (opcional)
   * @returns {Promise<{data:object, meta:object}>}
   */
  function get(path, params, scope) {
    // cancela a requisição anterior do mesmo escopo
    if (scope) {
      if (controllers[scope]) {
        controllers[scope].abort();
      }
      controllers[scope] = new AbortController();
    }

    var options = {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    };
    if (scope) {
      options.signal = controllers[scope].signal;
    }

    return fetch(base + path + qs(params), options).then(function (response) {
      return response.text().then(function (text) {
        var body;
        try {
          body = text ? JSON.parse(text) : {};
        } catch (e) {
          throw new ApiError('INVALID_RESPONSE', 'A resposta do servidor não pôde ser lida.', response.status);
        }

        if (response.status === 401) {
          // sessão expirada: volta ao login preservando o destino
          var login = base.replace(/api\/v1\/?$/, '') + 'login.php';
          window.location.href = login + '?expired=1';
          throw new ApiError('UNAUTHENTICATED', 'Sessão expirada.', 401);
        }

        if (!response.ok || !body.success) {
          var err = body.error || {};
          throw new ApiError(
            err.code || 'REQUEST_FAILED',
            err.message || 'Não foi possível carregar os dados.',
            response.status
          );
        }

        return { data: body.data, meta: body.meta || {} };
      });
    });
  }

  /** true quando o erro veio de um cancelamento (não deve virar mensagem). */
  function isAbort(error) {
    return error && (error.name === 'AbortError' || error.code === 20);
  }

  return {
    get: get,
    isAbort: isAbort,
    ApiError: ApiError,

    /* atalhos por recurso — mantêm os caminhos em um único lugar */
    companies:  function ()      { return get('/companies.php', {}, 'companies'); },
    reservoirs: function (c)     { return get('/reservoirs.php', { company_id: c }, 'reservoirs'); },
    overview:   function (p)     { return get('/overview.php', p, 'overview'); },
    flow:       function (p)     { return get('/monitoring/flow.php', p, 'page'); },
    level:      function (p)     { return get('/monitoring/level.php', p, 'page'); },
    ph:         function (p)     { return get('/monitoring/ph.php', p, 'page'); },
    storage:    function (p)     { return get('/monitoring/storage.php', p, 'page'); },
    rain:       function (p)     { return get('/monitoring/precipitation.php', p, 'page'); },
    duration:   function (p)     { return get('/monitoring/duration.php', p, 'page'); },
    operation:  function (p)     { return get('/monitoring/operation.php', p, 'page'); },
    comparison: function (p)     { return get('/monitoring/flow-comparison.php', p, 'page'); },
    reports:    function (p)     { return get('/reports.php', p, 'page'); },
    alerts:     function (p)     { return get('/alerts.php', p, 'page'); },
    mapMarkers: function (c)     { return get('/map/reservoirs.php', { company_id: c }, 'map'); },
    settings:   function ()      { return get('/settings.php', {}, 'page'); }
  };
})();
