/**
 * Aquapulse — formatação em português (pt-BR).
 *
 * O JSON da API sempre traz números normalizados (ponto decimal).
 * A vírgula e o separador de milhar aparecem apenas na apresentação.
 *
 * Padrão de módulo usado em todo o dashboard: uma função que se executa na hora
 * (IIFE) cria variáveis privadas e devolve só o que deve ser público. O resultado
 * fica em window.AqFormat e é usado pelas telas como F.num(), F.pct() etc.
 */
window.AqFormat = (function () {
  'use strict';                                                       // modo estrito: erros silenciosos do JS viram exceções

  var LOCALE = 'pt-BR';                                               // idioma/região usados por toLocaleString (vírgula decimal, ponto de milhar)

  /** Número com casas decimais fixas: 1234.5 -> "1.234,5" */
  function num(value, decimals) {
    if (value === null || value === undefined || isNaN(value)) return '—'; // valor ausente ou não numérico vira travessão, nunca "NaN" na tela
    var d = decimals === undefined ? 1 : decimals;                    // padrão: 1 casa decimal
    return Number(value).toLocaleString(LOCALE, {
      minimumFractionDigits: d,                                       // mínimo e máximo iguais = sempre exatamente d casas ("7,0" e não "7")
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
    return u ? n + ' ' + u : n;                                       // sem unidade, devolve só o número
  }

  /** Percentual: 82.4 -> "82,4%" */
  function pct(value, decimals) {
    return num(value, decimals === undefined ? 1 : decimals) + '%';
  }

  /** Valor com sinal explícito: 6.4 -> "+6,4" */
  function signed(value, decimals) {
    if (value === null || value === undefined || isNaN(value)) return '—';
    var s = Number(value) >= 0 ? '+' : '';                            // o sinal "-" já vem do próprio número; só o "+" precisa ser acrescentado
    return s + num(value, decimals);
  }

  /** Rótulo de status a partir da chave. */
  function statusLabel(key) {
    var map = {                                                       // mesmas traduções de StatusRules::describe() no PHP
      normal: 'Normal',
      attention: 'Atenção',
      critical: 'Crítico',
      info: 'Informação',
      offline: 'Offline'
    };
    return map[key] || key;                                           // chave desconhecida aparece como veio
  }

  /** "há 2 min" a partir de um ISO 8601. */
  function relative(iso) {
    if (!iso) return '';
    var then = new Date(iso).getTime();                               // instante informado, em milissegundos
    var mins = Math.max(0, Math.round((Date.now() - then) / 60000));  // diferença para o relógio do navegador, em minutos (nunca negativa)

    if (mins < 1) return 'agora';
    if (mins === 1) return 'há 1 min';
    if (mins < 60) return 'há ' + mins + ' min';

    var h = Math.round(mins / 60);                                    // a partir de 1 hora, arredonda para horas
    return h === 1 ? 'há 1 hora' : 'há ' + h + ' horas';
  }

  // API pública do módulo: só estas funções ficam acessíveis fora da IIFE.
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
