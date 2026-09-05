/**
 * Aquapulse — camada compartilhada de gráficos (Chart.js 4).
 *
 * Centraliza a identidade visual dos gráficos e o ciclo de vida das instâncias.
 * Nenhuma tela configura cores, eixos ou tooltips por conta própria.
 *
 * REGRA IMPORTANTE: antes de criar um gráfico, a instância anterior daquele
 * canvas é destruída. Isso impede o erro "Canvas is already in use" ao trocar
 * de filtro ou navegar entre páginas.
 */
window.AqCharts = (function () {
  'use strict';

  var F = window.AqFormat;

  /* ------------------------------------------------------------- paleta */
  var C = {
    primary:   '#0b5bea',
    secondary: '#6ea8fe',
    success:   '#16a34a',
    warning:   '#f59e0b',
    danger:    '#ef4444',
    grid:      'rgba(32, 79, 146, 0.09)',
    axis:      '#7f90af',
    text:      '#09245a'
  };

  /* registra o plugin de anotação (linhas de limite) */
  if (window.Chart && window['chartjs-plugin-annotation']) {
    Chart.register(window['chartjs-plugin-annotation']);
  }

  if (window.Chart) {
    Chart.defaults.font.family = '"Manrope", "Segoe UI", system-ui, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = C.axis;
    // animação curta e discreta; desligada para quem prefere menos movimento
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    Chart.defaults.animation = reduced ? false : { duration: 420 };
  }

  /** Instâncias vivas, indexadas pelo id do canvas. */
  var instances = {};

  /** Destrói a instância de um canvas, se existir. */
  function destroy(id) {
    if (instances[id]) {
      instances[id].destroy();
      delete instances[id];
    }
    // segurança extra: o Chart.js pode ter registro próprio do canvas
    var el = document.getElementById(id);
    if (el && window.Chart) {
      var existing = Chart.getChart(el);
      if (existing) existing.destroy();
    }
  }

  /** Destrói todos os gráficos da página (usado ao sair/recarregar). */
  function destroyAll() {
    Object.keys(instances).forEach(destroy);
  }

  /** Cria (ou recria) um gráfico. */
  function create(id, config) {
    var el = document.getElementById(id);
    if (!el) return null;

    destroy(id);
    instances[id] = new Chart(el.getContext('2d'), config);
    return instances[id];
  }

  /** Preenchimento em gradiente vertical, suave. */
  function fill(ctx, color, alphaTop) {
    var a = alphaTop === undefined ? 0.18 : alphaTop;
    var g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height || 240);
    g.addColorStop(0, hexToRgba(color, a));
    g.addColorStop(1, hexToRgba(color, 0));
    return g;
  }

  function hexToRgba(hex, alpha) {
    var h = hex.replace('#', '');
    var r = parseInt(h.substring(0, 2), 16);
    var g = parseInt(h.substring(2, 4), 16);
    var b = parseInt(h.substring(4, 6), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  /* -------------------------------------------------- eixos e tooltips */

  /** Configuração padrão dos eixos (`step` fixa o intervalo entre marcas). */
  function scales(options) {
    var o = options || {};
    return {
      x: {
        grid: { display: false, drawBorder: false },
        ticks: { color: C.axis, maxRotation: 0, autoSkipPadding: 12 },
        border: { display: false }
      },
      y: {
        beginAtZero: o.beginAtZero !== false,
        min: o.min,
        max: o.max,
        grid: { color: C.grid, drawBorder: false },
        border: { display: false },
        ticks: {
          color: C.axis,
          padding: 6,
          stepSize: o.step,
          callback: function (value) {
            return o.decimals === 0 ? F.int(value) : F.num(value, o.decimals || 0);
          }
        }
      }
    };
  }

  /** Tooltip padronizado: título, série, valor em pt-BR e unidade. */
  function tooltip(unit, decimals) {
    return {
      enabled: true,
      backgroundColor: '#09245a',
      titleColor: '#fff',
      bodyColor: '#dfe9fb',
      padding: 10,
      cornerRadius: 8,
      displayColors: true,
      boxWidth: 9,
      boxHeight: 9,
      boxPadding: 4,
      callbacks: {
        label: function (item) {
          var v = item.parsed.y !== undefined && item.parsed.y !== null ? item.parsed.y : item.parsed;
          var label = item.dataset.label ? item.dataset.label + ': ' : '';
          return label + F.unit(v, unit, decimals === undefined ? 1 : decimals);
        }
      }
    };
  }

  /** Bloco padrão de plugins (legenda desligada: usamos legenda em HTML). */
  function plugins(unit, decimals, extra) {
    var p = {
      legend: { display: false },
      tooltip: tooltip(unit, decimals)
    };
    return Object.assign(p, extra || {});
  }

  /** Dataset de linha com a identidade do sistema. */
  function line(label, data, color, options) {
    var o = options || {};
    return {
      label: label,
      data: data,
      borderColor: color,
      backgroundColor: o.fillCtx ? fill(o.fillCtx, color, o.alpha) : 'transparent',
      borderWidth: o.width || 2.5,
      borderDash: o.dashed ? [6, 5] : undefined,
      tension: o.tension === undefined ? 0.33 : o.tension,
      fill: !!o.fillCtx,
      pointRadius: o.points === false ? 0 : 2,
      pointHoverRadius: 5,
      pointBackgroundColor: color,
      pointBorderColor: '#fff',
      pointBorderWidth: 1.5,
      spanGaps: true
    };
  }

  /** Dataset de barra com cantos arredondados. */
  function bar(label, data, color, options) {
    var o = options || {};
    return {
      label: label,
      data: data,
      backgroundColor: color,
      borderRadius: o.radius === undefined ? 5 : o.radius,
      borderSkipped: false,
      barPercentage: o.barPercentage || 0.7,
      categoryPercentage: o.categoryPercentage || 0.75,
      maxBarThickness: o.maxThickness || 26
    };
  }

  /**
   * Linha tracejada de limite (cota de atenção, cota crítica…).
   *
   * `below` desenha o rótulo abaixo da linha — necessário quando a série
   * passa logo acima do limite e o rótulo cobriria os dados.
   */
  function limitLine(value, color, label, position, below) {
    return {
      type: 'line',
      yMin: value,
      yMax: value,
      borderColor: color,
      borderWidth: 1.6,
      borderDash: [6, 5],
      label: {
        display: !!label,
        content: label,
        position: position || 'end',
        backgroundColor: 'transparent',
        color: color,
        font: { size: 11, weight: '700' },
        yAdjust: below ? 14 : -14
      }
    };
  }

  /** Faixa horizontal translúcida (ex.: faixa ideal de pH). */
  function band(min, max, color, label) {
    return {
      type: 'box',
      yMin: min,
      yMax: max,
      backgroundColor: hexToRgba(color, 0.10),
      borderColor: hexToRgba(color, 0.35),
      borderWidth: 1,
      borderDash: [5, 4],
      label: {
        display: !!label,
        content: label,
        position: { x: 'end', y: 'center' },
        color: color,
        font: { size: 11, weight: '700' }
      }
    };
  }

  /* ------------------------------------------------ gráficos especiais */

  /**
   * Rótulos de valor desenhados sobre os pontos/barras.
   *
   * `horizontal: true` posiciona o rótulo na ponta da barra (barras deitadas);
   * `signed: true` escreve o sinal, como no comparativo de vazão.
   */
  function valueLabels(options) {
    var o = options || {};
    var deitado = o.horizontal === true;
    return {
      id: 'aqValueLabels',
      afterDatasetsDraw: function (chart) {
        var ctx = chart.ctx;
        ctx.save();
        ctx.textBaseline = deitado ? 'middle' : 'bottom';
        ctx.font = '700 ' + (o.size || 11) + 'px Manrope, sans-serif';

        (o.datasets || [0]).forEach(function (di) {
          var meta = chart.getDatasetMeta(di);
          if (!meta || meta.hidden) return;

          var corFixa = o.color || chart.data.datasets[di].borderColor || C.text;

          meta.data.forEach(function (point, i) {
            var v = chart.data.datasets[di].data[i];
            if (v === null || v === undefined) return;

            var casas = o.decimals === undefined ? 1 : o.decimals;
            var texto = o.signed ? F.signed(v, casas) : F.num(v, casas);
            var afast = o.offset === undefined ? 8 : o.offset;

            ctx.fillStyle = typeof corFixa === 'function' ? corFixa(v, i) : corFixa;

            if (deitado) {
              ctx.textAlign = v >= 0 ? 'left' : 'right';
              ctx.fillText(texto, point.x + (v >= 0 ? afast : -afast), point.y);
            } else {
              ctx.textAlign = 'center';
              ctx.fillText(texto, point.x, point.y - afast);
            }
          });
        });

        ctx.restore();
      }
    };
  }

  /** Plugin que escreve texto no centro de um donut. */
  function centerTextPlugin(lines) {
    return {
      id: 'aqCenterText',
      afterDraw: function (chart) {
        var ctx = chart.ctx;
        var meta = chart.getDatasetMeta(0);
        if (!meta || !meta.data || !meta.data.length) return;

        var x = meta.data[0].x;
        var y = meta.data[0].y;

        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        var offset = -(lines.length - 1) * 11;
        lines.forEach(function (l) {
          ctx.fillStyle = l.color || C.text;
          ctx.font = (l.weight || '800') + ' ' + (l.size || 24) + 'px Manrope, sans-serif';
          ctx.fillText(l.text, x, y + offset);
          offset += (l.size || 24) * 0.95;
        });

        ctx.restore();
      }
    };
  }

  /**
   * Donut / rosca.
   *
   * @param {string} id
   * @param {object} o { labels, values, colors, center:[{text,size,color}], cutout }
   */
  function donut(id, o) {
    return create(id, {
      type: 'doughnut',
      data: {
        labels: o.labels,
        datasets: [{
          data: o.values,
          backgroundColor: o.colors,
          borderWidth: 0,
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: o.cutout || '70%',
        plugins: {
          legend: { display: false },
          tooltip: tooltip(o.unit || '', o.decimals === undefined ? 0 : o.decimals)
        }
      },
      plugins: o.center ? [centerTextPlugin(o.center)] : []
    });
  }

  /**
   * Medidor semicircular (condição da vazão, ocupação, escala de pH).
   *
   * @param {string} id
   * @param {object} o { value, min, max, color, track, center, segments }
   */
  function gauge(id, o) {
    var min = o.min === undefined ? 0 : o.min;
    var max = o.max === undefined ? 100 : o.max;
    var value = Math.min(Math.max(o.value, min), max);
    var filled = ((value - min) / (max - min)) * 100;

    var data, colors;
    if (o.segments) {
      // escala segmentada (ex.: pH de 0 a 14 com faixas coloridas)
      data = o.segments.map(function (s) { return s.size; });
      colors = o.segments.map(function (s) { return s.color; });
    } else {
      data = [filled, 100 - filled];
      colors = [o.color || C.primary, o.track || '#e2eaf4'];
    }

    return create(id, {
      type: 'doughnut',
      data: { labels: [], datasets: [{ data: data, backgroundColor: colors, borderWidth: 0 }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        circumference: 180,
        rotation: 270,
        cutout: o.cutout || '72%',
        plugins: { legend: { display: false }, tooltip: { enabled: false } }
      },
      plugins: o.center ? [centerTextPlugin(o.center)] : []
    });
  }

  /**
   * Preenche a alternativa textual do canvas (acessibilidade).
   * Ex.: "Série de 7 pontos. Menor 54,6. Maior 65,5. Atual 56,2 m³/s."
   */
  function describe(id, values, unit, decimals) {
    var el = document.querySelector('[data-chart-summary="' + id + '"]');
    if (!el || !values || !values.length) return;

    var nums = values.filter(function (v) { return typeof v === 'number'; });
    if (!nums.length) return;

    var minV = Math.min.apply(null, nums);
    var maxV = Math.max.apply(null, nums);
    var last = nums[nums.length - 1];
    var d = decimals === undefined ? 1 : decimals;

    el.textContent = 'Série com ' + nums.length + ' pontos. Menor valor ' + F.num(minV, d)
      + '. Maior valor ' + F.num(maxV, d) + '. Valor atual ' + F.unit(last, unit, d) + '.';
  }

  return {
    colors: C,
    create: create,
    destroy: destroy,
    destroyAll: destroyAll,
    fill: fill,
    rgba: hexToRgba,
    scales: scales,
    tooltip: tooltip,
    plugins: plugins,
    line: line,
    bar: bar,
    limitLine: limitLine,
    band: band,
    donut: donut,
    gauge: gauge,
    valueLabels: valueLabels,
    describe: describe
  };
})();
