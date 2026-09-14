/**
 * Aquapulse — camada compartilhada de gráficos (Chart.js 4).
 *
 * Centraliza a identidade visual dos gráficos e o ciclo de vida das instâncias.
 * Nenhuma tela configura cores, eixos ou tooltips por conta própria.
 *
 * REGRA IMPORTANTE: antes de criar um gráfico, a instância anterior daquele
 * canvas é destruída. Isso impede o erro "Canvas is already in use" ao trocar
 * de filtro ou navegar entre páginas.
 *
 * Exposto como window.AqCharts. As telas montam os datasets com line()/bar(),
 * os eixos com scales(), os tooltips com plugins() e desenham com create(),
 * donut() ou gauge().
 */
window.AqCharts = (function () {
  'use strict';

  var F = window.AqFormat;                                               // formatação pt-BR dos números nos eixos e tooltips

  /* ------------------------------------------------------------- paleta */
  var C = {                                                              // cores oficiais dos gráficos (as telas acessam como G.colors.primary)
    primary:   '#0b5bea',                                                // azul principal
    secondary: '#6ea8fe',
    success:   '#16a34a',                                                // verde = normal
    warning:   '#f59e0b',                                                // âmbar = atenção
    danger:    '#ef4444',                                                // vermelho = crítico
    grid:      'rgba(32, 79, 146, 0.09)',                                // linhas de grade quase transparentes
    axis:      '#62759a',
    text:      '#09245a'
  };

  /* ------------------------------------------ dependências obrigatórias */
  /*
   * Se o Chart.js ou o plugin não carregarem, o cartão precisa dizer isso.
   * Antes a ausência passava em silêncio e o canvas simplesmente ficava
   * branco, sem nenhuma pista do que havia acontecido.
   */
  var falhaDependencia = null;                                           // mensagem de erro exibida em todos os gráficos se faltar biblioteca

  if (!window.Chart) {                                                   // chart.umd.js não carregou
    falhaDependencia = 'A biblioteca de gráficos (Chart.js) não foi carregada.';
    console.error('[AqCharts] Chart.js ausente: verifique assets/vendor/chartjs/chart.umd.js');
  } else if (!window['chartjs-plugin-annotation']) {                     // plugin de linhas de limite não carregou
    falhaDependencia = 'O complemento de linhas de limite não foi carregado.';
    console.error('[AqCharts] chartjs-plugin-annotation ausente: as linhas de limite não serão desenhadas.');
  }

  /* registra o plugin de anotação (linhas de limite) — uma única vez */
  if (window.Chart && window['chartjs-plugin-annotation']) {
    var jaRegistrado = !!(Chart.registry && Chart.registry.plugins
      && Chart.registry.plugins.items && Chart.registry.plugins.items.annotation); // confere no registro interno do Chart.js se já existe
    if (!jaRegistrado) {
      Chart.register(window['chartjs-plugin-annotation']);
    }
  }

  if (window.Chart) {                                                    // padrões globais aplicados a todos os gráficos
    Chart.defaults.font.family = '"Manrope", "Segoe UI", system-ui, sans-serif'; // mesma fonte do restante do dashboard
    Chart.defaults.font.size = 11;
    Chart.defaults.color = C.axis;
    // animação curta e discreta; desligada para quem prefere menos movimento
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches; // preferência de acessibilidade do sistema operacional
    Chart.defaults.animation = reduced ? false : { duration: 420 };
  }

  /** Instâncias vivas, indexadas pelo id do canvas. */
  var instances = {};

  /** Destrói a instância de um canvas, se existir. */
  function destroy(id) {
    if (instances[id]) {
      instances[id].destroy();                                           // libera memória e ouvintes do gráfico antigo
      delete instances[id];
    }
    // segurança extra: o Chart.js pode ter registro próprio do canvas
    var el = document.getElementById(id);
    if (el && window.Chart) {
      var existing = Chart.getChart(el);                                 // gráfico criado fora deste módulo no mesmo canvas
      if (existing) existing.destroy();
    }
  }

  /** Destrói todos os gráficos da página (usado ao sair/recarregar). */
  function destroyAll() {
    Object.keys(instances).forEach(destroy);
    Object.keys(observadores).forEach(function (id) {                    // desliga também os observadores de tamanho
      observadores[id].disconnect();
      delete observadores[id];
    });
  }

  /* ------------------------------------------------------- diagnóstico */

  /** Escopo de estado (`data-content`) que envolve um canvas, se houver. */
  function escopoDe(el) {
    var wrap = el && el.closest ? el.closest('[data-content]') : null;   // sobe na árvore até o contêiner do card
    return wrap ? wrap.getAttribute('data-content') : null;
  }

  /**
   * Marca o cartão dono do canvas com um estado, quando ele tiver escopo.
   * Sem isso, uma falha em um gráfico deixava apenas um retângulo branco.
   */
  function sinalizar(el, estado, mensagem) {
    var escopo = escopoDe(el);
    if (escopo && window.AqShell && window.AqShell.setState) {
      window.AqShell.setState(escopo, estado, mensagem);                 // mostra o aviso de erro/vazio do card
    }
  }

  /** Um valor só entra em um dataset se for número finito. */
  function numeroValido(v) {
    if (v === null || v === undefined) return false;
    var n = (typeof v === 'object') ? (v.y !== undefined ? v.y : v.x) : v; // aceita número puro ou ponto {x, y}
    return typeof n === 'number' && isFinite(n);                         // rejeita NaN, Infinity e textos
  }

  /**
   * Confere a consistência de uma configuração antes de desenhar.
   * Devolve null quando está tudo certo, ou o motivo técnico da recusa.
   */
  function motivoInvalido(config) {
    var dados = config && config.data;
    if (!dados || !Array.isArray(dados.datasets) || dados.datasets.length === 0) {
      return 'nenhum dataset informado';
    }

    var labels = Array.isArray(dados.labels) ? dados.labels : null;
    var algumPonto = false;                                              // vira true se existir pelo menos um valor em alguma série

    for (var i = 0; i < dados.datasets.length; i++) {                    // percorre cada série do gráfico
      var serie = dados.datasets[i];
      var valores = serie && serie.data;
      if (!Array.isArray(valores)) {
        return 'dataset ' + i + ' (' + (serie && serie.label) + ') sem array de dados';
      }
      // Gráficos de eixo categórico exigem labels e valores do mesmo tamanho.
      // Medidores (doughnut) passam `labels: []` de propósito — e um array
      // vazio é truthy, então a comparação só vale quando há labels de fato.
      if (labels && labels.length > 0 && valores.length && labels.length !== valores.length) {
        return 'dataset ' + i + ' com ' + valores.length + ' valores para ' + labels.length + ' labels';
      }
      for (var j = 0; j < valores.length; j++) {                         // percorre cada ponto da série
        if (!numeroValido(valores[j])) {
          return 'valor não numérico em ' + (serie.label || 'dataset ' + i) + '[' + j + ']: ' + valores[j];
        }
        algumPonto = true;
      }
    }

    return algumPonto ? null : 'todas as séries estão vazias';
  }

  /**
   * Cria (ou recria) um gráfico.
   *
   * Nunca lança: qualquer problema vira estado visível no cartão + mensagem
   * técnica no console. Assim a falha de um gráfico não impede os seguintes
   * da mesma tela de serem desenhados.
   *
   * @param {string} id     id do <canvas> (criado por aq_chart() no PHP)
   * @param {object} config configuração no formato do Chart.js
   * @returns {object|null} instância do gráfico, ou null se foi recusado
   */
  function create(id, config) {
    var el = document.getElementById(id);

    if (!el) {                                                           // 1. o canvas existe?
      console.error('[AqCharts] canvas "' + id + '" não encontrado no DOM.');
      return null;
    }
    if (el.tagName !== 'CANVAS') {                                       // 2. é mesmo um canvas?
      console.error('[AqCharts] o elemento "' + id + '" não é um <canvas>.');
      sinalizar(el, 'error', 'Não foi possível carregar este gráfico.');
      return null;
    }
    if (falhaDependencia) {                                              // 3. as bibliotecas carregaram?
      sinalizar(el, 'error', falhaDependencia);
      return null;
    }

    var ctx = el.getContext ? el.getContext('2d') : null;                // 4. o navegador fornece o contexto de desenho 2D?
    if (!ctx) {
      console.error('[AqCharts] contexto 2D indisponível em "' + id + '".');
      sinalizar(el, 'error', 'Não foi possível carregar este gráfico.');
      return null;
    }

    var motivo = motivoInvalido(config);                                 // 5. os dados são coerentes?
    if (motivo) {
      console.error('[AqCharts] "' + id + '" recusado: ' + motivo);
      destroy(id);
      sinalizar(el, motivo === 'todas as séries estão vazias' ? 'empty' : 'error', // séries vazias = "sem dados"; demais problemas = erro
        'Não foi possível carregar este gráfico.');
      return null;
    }

    destroy(id);                                                         // 6. remove o gráfico anterior deste canvas

    try {
      instances[id] = new Chart(ctx, config);                            // 7. cria o gráfico
    } catch (e) {
      console.error('[AqCharts] Chart.js falhou ao criar "' + id + '": ' + (e && e.message), e);
      sinalizar(el, 'error', 'Não foi possível carregar este gráfico.');
      return null;
    }

    // um canvas dentro de um contêiner que acabou de ser revelado nasce com
    // tamanho zero; o Chart.js só se ajusta depois de um resize explícito
    if (!el.clientWidth || !el.clientHeight) {
      window.requestAnimationFrame(function () {                         // espera o próximo quadro, quando o navegador já calculou o layout
        if (instances[id]) instances[id].resize();
      });
    }

    observarTamanho(id, el);
    return instances[id];
  }

  /**
   * Redesenha o gráfico quando o contêiner muda de tamanho — sidebar
   * recolhendo, submenu abrindo, aba trocando ou cartão sendo revelado.
   */
  var observadores = {};                                                 // um ResizeObserver por canvas

  function observarTamanho(id, el) {
    if (observadores[id] || typeof ResizeObserver === 'undefined') return; // já observado, ou navegador sem suporte

    var alvo = el.parentElement;                                         // observa o contêiner, que é quem muda de tamanho
    if (!alvo) return;

    var ro = new ResizeObserver(function () {
      var chart = instances[id];
      if (!chart) return;
      if (alvo.clientWidth > 0 && alvo.clientHeight > 0) chart.resize(); // ignora quando o contêiner está oculto (tamanho 0)
    });
    ro.observe(alvo);
    observadores[id] = ro;
  }

  /**
   * Um escopo está "pronto" quando todo canvas dentro dele virou gráfico.
   *
   * Serve para decidir, depois de uma falha no meio da montagem da tela,
   * quais cartões realmente ficaram sem gráfico — só esses viram erro, em vez
   * de derrubar a tela inteira.
   */
  function scopeReady(scope) {
    var wrap = document.querySelector('[data-content="' + scope + '"]');
    if (!wrap) return true;                                              // escopo sem contêiner: nada a conferir

    var canvases = wrap.querySelectorAll('canvas');
    if (!canvases.length) return true;                                   // card sem gráfico (ex.: só tabela)

    for (var i = 0; i < canvases.length; i++) {
      if (!instances[canvases[i].id]) return false;                      // algum canvas ainda sem gráfico
    }
    return true;
  }

  /**
   * Executa a montagem de um gráfico isolando falhas.
   *
   * Antes, uma exceção no meio do `render()` de uma tela interrompia tudo o
   * que vinha depois: os gráficos seguintes nunca eram criados e ficavam em
   * branco, com título e legenda visíveis. Aqui cada bloco falha sozinho.
   */
  function guard(id, montar) {
    try {
      return montar();                                                   // função da tela que monta e cria o gráfico
    } catch (e) {
      console.error('[AqCharts] falha ao montar "' + id + '": ' + (e && e.message), e);
      var el = document.getElementById(id);
      if (el) sinalizar(el, 'error', 'Não foi possível carregar este gráfico.');
      destroy(id);
      return null;
    }
  }

  /** Preenchimento em gradiente vertical, suave. */
  function fill(ctx, color, alphaTop) {
    var a = alphaTop === undefined ? 0.18 : alphaTop;                    // opacidade no topo da área
    var g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height || 240); // gradiente de cima (y=0) até a base do canvas
    g.addColorStop(0, hexToRgba(color, a));                              // cor translúcida no topo...
    g.addColorStop(1, hexToRgba(color, 0));                              // ...totalmente transparente embaixo
    return g;
  }

  /** Converte "#0b5bea" + opacidade em "rgba(11,91,234,0.18)". */
  function hexToRgba(hex, alpha) {
    var h = hex.replace('#', '');
    var r = parseInt(h.substring(0, 2), 16);                             // dois primeiros dígitos hexadecimais = vermelho
    var g = parseInt(h.substring(2, 4), 16);                             // verde
    var b = parseInt(h.substring(4, 6), 16);                             // azul
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  /* -------------------------------------------------- eixos e tooltips */

  /**
   * Configuração padrão dos eixos (`step` fixa o intervalo entre marcas).
   * @param {object} options { beginAtZero, min, max, step, decimals }
   */
  function scales(options) {
    var o = options || {};
    return {
      x: {                                                               // eixo horizontal (datas/categorias)
        grid: { display: false, drawBorder: false },                     // sem linhas verticais de grade
        ticks: { color: C.axis, maxRotation: 0, autoSkipPadding: 12 },   // rótulos sempre na horizontal; pula rótulos quando não cabem
        border: { display: false }
      },
      y: {                                                               // eixo vertical (valores)
        beginAtZero: o.beginAtZero !== false,                            // começa em zero, a não ser que a tela peça o contrário (ex.: cota)
        min: o.min,
        max: o.max,
        grid: { color: C.grid, drawBorder: false },
        border: { display: false },
        ticks: {
          color: C.axis,
          padding: 6,
          stepSize: o.step,
          callback: function (value) {                                   // formata cada marca do eixo em pt-BR
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
      displayColors: true,                                               // mostra o quadradinho de cor da série
      boxWidth: 9,
      boxHeight: 9,
      boxPadding: 4,
      callbacks: {
        label: function (item) {                                         // texto de cada linha do tooltip: "Série: 56,2 m³/s"
          var v = item.parsed.y !== undefined && item.parsed.y !== null ? item.parsed.y : item.parsed; // linhas/barras usam y; roscas usam o valor direto
          var label = item.dataset.label ? item.dataset.label + ': ' : '';
          return label + F.unit(v, unit, decimals === undefined ? 1 : decimals);
        }
      }
    };
  }

  /** Bloco padrão de plugins (legenda desligada: usamos legenda em HTML). */
  function plugins(unit, decimals, extra) {
    var p = {
      legend: { display: false },                                        // a legenda vem de aq_legend() no PHP
      tooltip: tooltip(unit, decimals)
    };
    return Object.assign(p, extra || {});                                // extra permite acrescentar, por exemplo, annotation (linhas de limite)
  }

  /**
   * Dataset de linha com a identidade do sistema.
   * @param {object} options { fillCtx (liga área em gradiente), alpha, width, dashed, tension, points }
   */
  function line(label, data, color, options) {
    var o = options || {};
    return {
      label: label,
      data: data,
      borderColor: color,
      backgroundColor: o.fillCtx ? fill(o.fillCtx, color, o.alpha) : 'transparent',
      borderWidth: o.width || 2.5,
      borderDash: o.dashed ? [6, 5] : undefined,                         // tracejado: 6 px de traço, 5 px de espaço
      tension: o.tension === undefined ? 0.33 : o.tension,               // curvatura da linha (0 = segmentos retos)
      fill: !!o.fillCtx,
      pointRadius: o.points === false ? 0 : 2,                           // points: false esconde as bolinhas dos pontos
      pointHoverRadius: 5,
      pointBackgroundColor: color,
      pointBorderColor: '#fff',
      pointBorderWidth: 1.5,
      spanGaps: true                                                     // valores ausentes não quebram a linha
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
      borderSkipped: false,                                              // arredonda os quatro cantos, não só o topo
      barPercentage: o.barPercentage || 0.7,                             // largura da barra dentro da categoria
      categoryPercentage: o.categoryPercentage || 0.75,
      maxBarThickness: o.maxThickness || 26                              // limite de largura em telas largas
    };
  }

  /**
   * Linha tracejada de limite (cota de atenção, cota crítica…).
   *
   * `below` desenha o rótulo abaixo da linha — necessário quando a série
   * passa logo acima do limite e o rótulo cobriria os dados.
   */
  function limitLine(value, color, label, position, below) {
    return {                                                             // formato do chartjs-plugin-annotation
      type: 'line',
      yMin: value,                                                       // yMin = yMax = linha horizontal na altura do valor
      yMax: value,
      borderColor: color,
      borderWidth: 1.6,
      borderDash: [6, 5],
      label: {
        display: !!label,
        content: label,
        position: position || 'end',                                     // rótulo no fim (direita) da linha por padrão
        backgroundColor: 'transparent',
        color: color,
        font: { size: 11, weight: '700' },
        yAdjust: below ? 14 : -14                                        // desloca o rótulo 14 px para baixo ou para cima da linha
      }
    };
  }

  /** Faixa horizontal translúcida (ex.: faixa ideal de pH). */
  function band(min, max, color, label) {
    return {
      type: 'box',                                                       // retângulo entre yMin e yMax ocupando toda a largura
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
   *
   * É um plugin do Chart.js: o hook afterDatasetsDraw roda depois que as
   * séries foram desenhadas, e o texto é pintado direto no canvas.
   */
  function valueLabels(options) {
    var o = options || {};
    var deitado = o.horizontal === true;
    return {
      id: 'aqValueLabels',
      afterDatasetsDraw: function (chart) {
        var ctx = chart.ctx;
        ctx.save();                                                      // guarda o estado do canvas para não afetar outros desenhos
        ctx.textBaseline = deitado ? 'middle' : 'bottom';
        ctx.font = '700 ' + (o.size || 11) + 'px Manrope, sans-serif';

        (o.datasets || [0]).forEach(function (di) {                      // séries que recebem rótulo (padrão: só a primeira)
          var meta = chart.getDatasetMeta(di);                           // posições calculadas pelo Chart.js para cada ponto
          if (!meta || meta.hidden) return;

          var corFixa = o.color || chart.data.datasets[di].borderColor || C.text;

          meta.data.forEach(function (point, i) {                        // um rótulo por ponto/barra
            var v = chart.data.datasets[di].data[i];
            if (v === null || v === undefined) return;

            var casas = o.decimals === undefined ? 1 : o.decimals;
            var texto = o.signed ? F.signed(v, casas) : F.num(v, casas);
            var afast = o.offset === undefined ? 8 : o.offset;           // distância entre o ponto e o texto

            ctx.fillStyle = typeof corFixa === 'function' ? corFixa(v, i) : corFixa; // a cor pode depender do valor (ex.: verde se positivo)

            if (deitado) {                                               // barra horizontal: texto à direita (positivo) ou à esquerda (negativo)
              ctx.textAlign = v >= 0 ? 'left' : 'right';
              ctx.fillText(texto, point.x + (v >= 0 ? afast : -afast), point.y);
            } else {                                                     // barra/ponto vertical: texto centralizado acima
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

        var x = meta.data[0].x;                                          // centro da rosca, calculado pelo Chart.js
        var y = meta.data[0].y;

        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        var offset = -(lines.length - 1) * 11;                           // sobe o bloco de texto para ficar centralizado verticalmente
        lines.forEach(function (l) {                                     // uma linha de texto por item (ex.: "3" e "represas")
          ctx.fillStyle = l.color || C.text;
          ctx.font = (l.weight || '800') + ' ' + (l.size || 24) + 'px Manrope, sans-serif';
          ctx.fillText(l.text, x, y + offset);
          offset += (l.size || 24) * 0.95;                               // próxima linha logo abaixo, proporcional ao tamanho da fonte
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
          backgroundColor: o.colors,                                     // uma cor por fatia
          borderWidth: 0,
          hoverOffset: 4                                                 // a fatia "salta" 4 px ao passar o mouse
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,                                      // usa a altura do contêiner em vez de manter proporção fixa
        cutout: o.cutout || '70%',                                       // tamanho do furo central
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
    var value = Math.min(Math.max(o.value, min), max);                   // limita o valor ao intervalo [min, max]
    var filled = ((value - min) / (max - min)) * 100;                    // quanto do arco (em %) fica preenchido

    var data, colors;
    if (o.segments) {
      // escala segmentada (ex.: pH de 0 a 14 com faixas coloridas)
      data = o.segments.map(function (s) { return s.size; });
      colors = o.segments.map(function (s) { return s.color; });
    } else {
      data = [filled, 100 - filled];                                     // duas fatias: parte preenchida + restante
      colors = [o.color || C.primary, o.track || '#e2eaf4'];             // cor do valor + cinza do trilho
    }

    return create(id, {
      type: 'doughnut',
      data: { labels: [], datasets: [{ data: data, backgroundColor: colors, borderWidth: 0 }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        circumference: 180,                                              // meia volta: vira um semicírculo
        rotation: 270,                                                   // começa à esquerda (270°) e termina à direita
        cutout: o.cutout || '72%',
        plugins: { legend: { display: false }, tooltip: { enabled: false } } // medidor não mostra tooltip
      },
      plugins: o.center ? [centerTextPlugin(o.center)] : []
    });
  }

  /**
   * Preenche a alternativa textual do canvas (acessibilidade).
   * Ex.: "Série de 7 pontos. Menor 54,6. Maior 65,5. Atual 56,2 m³/s."
   */
  function describe(id, values, unit, decimals) {
    var el = document.querySelector('[data-chart-summary="' + id + '"]'); // parágrafo invisível criado por aq_chart() no PHP
    if (!el || !values || !values.length) return;

    var nums = values.filter(function (v) { return typeof v === 'number'; });
    if (!nums.length) return;

    var minV = Math.min.apply(null, nums);                               // apply espalha o array como argumentos de Math.min
    var maxV = Math.max.apply(null, nums);
    var last = nums[nums.length - 1];                                    // último ponto = valor atual
    var d = decimals === undefined ? 1 : decimals;

    el.textContent = 'Série com ' + nums.length + ' pontos. Menor valor ' + F.num(minV, d)
      + '. Maior valor ' + F.num(maxV, d) + '. Valor atual ' + F.unit(last, unit, d) + '.';
  }

  return {
    colors: C,
    create: create,
    guard: guard,
    scopeReady: scopeReady,
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
