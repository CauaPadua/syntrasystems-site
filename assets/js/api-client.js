/**
 * Aquapulse — cliente da API do sistema interno.
 *
 * Responsabilidades:
 *   - falar com os endpoints PHP na mesma origem (o cookie de sessão vai junto);
 *   - cancelar a consulta anterior do mesmo escopo, para uma resposta atrasada
 *     não sobrescrever a atual;
 *   - traduzir erros da API em mensagens claras;
 *   - devolver o usuário ao login quando a sessão expira (401).
 *
 * Nada de autenticação é guardado aqui: a sessão vive em um cookie HttpOnly,
 * inacessível ao JavaScript.
 *
 * Exposto como window.AqApi. As consultas (GET) e as gravações (POST) devolvem
 * Promises com { data, meta }.
 */
window.AqApi = (function () {
  'use strict';

  var base = document.body.getAttribute('data-api-base') || 'api/v1';

  /** Controllers ativos por escopo, para cancelar consultas superadas. */
  var controllers = {};

  /** Erro com o código da API, para as telas reagirem de forma específica. */
  function ApiError(code, message, status, details) {
    this.name = 'ApiError';
    this.code = code;                 // ex.: NO_DATA, VALIDATION_ERROR, FORBIDDEN
    this.message = message;           // texto pronto para exibir
    this.status = status;             // código HTTP
    this.details = details || {};     // erros por campo, nas validações de formulário
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

  /** Lê a resposta e a converte em { data, meta } ou em ApiError. */
  function handle(response) {
    return response.text().then(function (text) {
      var body;
      try {
        body = text ? JSON.parse(text) : {};
      } catch (e) {
        throw new ApiError('INVALID_RESPONSE', 'A resposta do servidor não pôde ser lida.', response.status);
      }

      if (response.status === 401) {
        window.location.href = base.replace(/api\/v1\/?$/, '') + 'login.php';
        throw new ApiError('UNAUTHENTICATED', 'Sessão expirada.', 401);
      }

      if (!response.ok || !body.success) {
        var err = body.error || {};
        throw new ApiError(
          err.code || 'REQUEST_FAILED',
          err.message || 'Não foi possível concluir a operação.',
          response.status,
          err.details
        );
      }

      return { data: body.data, meta: body.meta || {} };
    });
  }

  /**
   * Consulta (GET).
   *
   * @param {string} path   ex.: "/overview.php"
   * @param {object} params parâmetros de query
   * @param {string} scope  identificador para cancelamento (opcional)
   */
  function get(path, params, scope) {
    var options = { method: 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } };

    if (scope) {
      if (controllers[scope]) controllers[scope].abort();
      controllers[scope] = new AbortController();
      options.signal = controllers[scope].signal;
    }

    return fetch(base + path + qs(params), options).then(handle);
  }

  /**
   * Gravação (POST com corpo JSON). Não é cancelável: uma vez enviada, a
   * operação precisa terminar.
   */
  function post(path, body) {
    return fetch(base + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {})
    }).then(handle);
  }

  /** true quando o erro veio de um cancelamento (não deve virar mensagem). */
  function isAbort(error) {
    return error && (error.name === 'AbortError' || error.code === 20);
  }

  /** Cancela tudo que ainda estiver em andamento (usado ao sair da página). */
  function abortAll() {
    Object.keys(controllers).forEach(function (scope) {
      try { controllers[scope].abort(); } catch (e) { /* já encerrado */ }
      delete controllers[scope];
    });
  }

  return {
    get: get,
    post: post,
    isAbort: isAbort,
    abortAll: abortAll,
    ApiError: ApiError,

    /* consultas — os caminhos ficam em um único lugar */
    companies:  function ()  { return get('/companies.php', {}, 'companies'); },
    reservoirs: function (c) { return get('/reservoirs.php', { company_id: c }, 'reservoirs'); },
    overview:   function (p) { return get('/overview.php', p, 'overview'); },
    flow:       function (p) { return get('/monitoring/flow.php', p, 'page'); },
    level:      function (p) { return get('/monitoring/level.php', p, 'page'); },
    ph:         function (p) { return get('/monitoring/ph.php', p, 'page'); },
    storage:    function (p) { return get('/monitoring/storage.php', p, 'page'); },
    rain:       function (p) { return get('/monitoring/precipitation.php', p, 'page'); },
    operation:  function (p) { return get('/monitoring/operation.php', p, 'page'); },
    comparison: function (p) { return get('/monitoring/flow-comparison.php', p, 'page'); },
    reports:    function (p) { return get('/reports.php', p, 'page'); },
    alerts:     function (p) { return get('/alerts.php', p, 'page'); },
    equipment:  function (p) { return get('/equipment.php', p, 'page'); },
    mapMarkers: function (c) { return get('/map/reservoirs.php', { company_id: c }, 'map'); },
    settings:   function ()  { return get('/settings.php', {}, 'page'); },

    /* gravações */
    saveEquipment:   function (action, fields) { return post('/equipment.php', Object.assign({ action: action }, fields)); },
    alertAction:     function (action, id)     { return post('/alerts.php', { action: action, id: id }); },
    createReport:    function (fields)         { return post('/reports.php', fields); },
    openTicket:      function (fields)         { return post('/monitoring/operation.php', fields); },

    /** Endereço de download do CSV de um relatório. */
    reportUrl: function (id) { return base + '/reports.php?download=' + encodeURIComponent(id); }
  };
})();
