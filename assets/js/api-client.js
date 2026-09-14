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
 *
 * Exposto como window.AqApi. As telas chamam os atalhos (AqApi.overview(...),
 * AqApi.flow(...)), que devolvem Promises com { data, meta }.
 */
window.AqApi = (function () {
  'use strict';

  var base = document.body.getAttribute('data-api-base') || 'api/v1'; // caminho da API escrito por page.php no <body> (ex.: "../../api/v1")

  /** Controllers ativos por escopo, para cancelar requisições superadas. */
  var controllers = {};

  /** Erro com código da API, para as telas reagirem de forma específica. */
  function ApiError(code, message, status) {
    this.name = 'ApiError';
    this.code = code;                                                 // ex.: NO_DATA, INVALID_PERIOD, UNAUTHENTICATED
    this.message = message;                                           // texto já pronto para exibir
    this.status = status;                                             // código HTTP da resposta
  }
  ApiError.prototype = Object.create(Error.prototype);                // herda de Error: funciona com try/catch e .catch() como um erro comum

  /** Monta a query string ignorando valores vazios. */
  function qs(params) {
    var parts = [];
    Object.keys(params || {}).forEach(function (k) {                  // percorre cada parâmetro recebido
      var v = params[k];
      if (v !== undefined && v !== null && v !== '') {                // omite parâmetros vazios em vez de mandar "?x="
        parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v)); // codifica espaços, acentos e "&" para a URL não quebrar
      }
    });
    return parts.length ? '?' + parts.join('&') : '';                 // ex.: "?reservoir_id=santa-clara&period=7d"
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
      if (controllers[scope]) {                                       // ainda há uma requisição deste escopo em andamento?
        controllers[scope].abort();                                   // cancela: a resposta antiga nunca chegará à tela
      }
      controllers[scope] = new AbortController();                     // controlador novo para esta requisição
    }

    var options = {
      method: 'GET',
      credentials: 'same-origin',                                     // envia o cookie de sessão (AQUAPULSE_SESSION) para o mesmo domínio
      headers: { Accept: 'application/json' }                         // avisa o servidor que a resposta esperada é JSON
    };
    if (scope) {
      options.signal = controllers[scope].signal;                     // liga a requisição ao controlador, permitindo cancelar
    }

    return fetch(base + path + qs(params), options).then(function (response) {
      return response.text().then(function (text) {                   // lê como texto primeiro para tratar corpo vazio ou não-JSON
        var body;
        try {
          body = text ? JSON.parse(text) : {};
        } catch (e) {                                                 // ex.: um erro fatal do PHP devolveu HTML em vez de JSON
          throw new ApiError('INVALID_RESPONSE', 'A resposta do servidor não pôde ser lida.', response.status);
        }

        if (response.status === 401) {
          // sessão expirada: volta ao login preservando o destino
          var login = base.replace(/api\/v1\/?$/, '') + 'login.php';  // remove "api/v1" do fim do caminho para chegar à raiz do site
          window.location.href = login + '?expired=1';                // redireciona ao login; obs.: hoje nenhum arquivo lê o parâmetro ?expired=1
          throw new ApiError('UNAUTHENTICATED', 'Sessão expirada.', 401);
        }

        if (!response.ok || !body.success) {                          // HTTP de erro ou envelope com success = false
          var err = body.error || {};
          throw new ApiError(
            err.code || 'REQUEST_FAILED',
            err.message || 'Não foi possível carregar os dados.',
            response.status
          );
        }

        return { data: body.data, meta: body.meta || {} };            // sucesso: entrega só o que as telas usam
      });
    });
  }

  /** true quando o erro veio de um cancelamento (não deve virar mensagem). */
  function isAbort(error) {
    return error && (error.name === 'AbortError' || error.code === 20); // 20 = código de cancelamento em navegadores mais antigos
  }

  /** Cancela tudo que ainda estiver em voo (usado ao sair da página). */
  function abortAll() {
    Object.keys(controllers).forEach(function (scope) {
      try {
        controllers[scope].abort();
      } catch (e) {
        console.warn('[AqApi] não foi possível cancelar o escopo "' + scope + '": ' + e.message);
      }
      delete controllers[scope];
    });
  }

  return {
    get: get,
    isAbort: isAbort,
    abortAll: abortAll,
    ApiError: ApiError,

    /* atalhos por recurso — mantêm os caminhos em um único lugar */
    /* o terceiro argumento é o escopo: todas as telas de monitoramento usam "page",
       então trocar o filtro rapidamente cancela a busca anterior da mesma tela */
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
