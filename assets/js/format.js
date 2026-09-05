/**
 * Aquapulse — formatação em português (pt-BR).
 *
 * O JSON da API sempre traz números normalizados (ponto decimal).
 * A vírgula e o separador de milhar aparecem apenas na apresentação.
 */
window.AqFormat = (function () {
  'use strict';

  var LOCALE = 'pt-BR';

  /** Número com casas decimais fixas: 1234.5 -> "1.234,5" */
  function num(value, decimals) {
    if (value === null || value === undefined || isNaN(value)) return '—';
    var d = decimals === undefined ? 1 : decimals;
    return Number(value).toLocaleString(LOCALE, {
      minimumFractionDigits: d,
      maximumFractionDigits: d
    });
  }

  /** Inteiro: 1234 -> "1.234" */
  function int(value) {
    return num(value, 0);
  }

  /** Número seguido de unidade: (56.2, "m³/s") -> "56,2 m³/s" */
  function unit(value, u, decimals) {
    var n = num(value, decimals);
    return u ? n + ' ' + u : n;
  }

  /** Percentual: 82.4 -> "82,4%" */
  function pct(value, decimals) {
    return num(value, decimals === undefined ? 1 : decimals) + '%';
  }

  /** Valor com sinal explícito: 6.4 -> "+6,4" */
  function signed(value, decimals) {
    if (value === null || value === undefined || isNaN(value)) return '—';
    var s = Number(value) >= 0 ? '+' : '';
    return s + num(value, decimals);
  }

  /** Rótulo de status a partir da chave. */
  function statusLabel(key) {
    var map = {
      normal: 'Normal',
      attention: 'Atenção',
      critical: 'Crítico',
      info: 'Informação',
      offline: 'Offline'
    };
    return map[key] || key;
  }

  /** "há 2 min" a partir de um ISO 8601. */
  function relative(iso) {
    if (!iso) return '';
    var then = new Date(iso).getTime();
    var mins = Math.max(0, Math.round((Date.now() - then) / 60000));

    if (mins < 1) return 'agora';
    if (mins === 1) return 'há 1 min';
    if (mins < 60) return 'há ' + mins + ' min';

    var h = Math.round(mins / 60);
    return h === 1 ? 'há 1 hora' : 'há ' + h + ' horas';
  }

  return {
    num: num,
    int: int,
    unit: unit,
    pct: pct,
    signed: signed,
    statusLabel: statusLabel,
    relative: relative
  };
})();
