/** Aquapulse — Monitoramento / Precipitação. */
/*
 * Página: dashboard/monitoramento/precipitacao.php | API: GET api/v1/monitoring/precipitation.php.
 * Represa, período, estados e recarga ficam em AqMonitorPage; aqui só o render().
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;

  var ICONS = {                                                       // desenhos SVG usados nesta tela (nome do ícone -> caminhos)
    'cloud-rain': '<path d="M6.5 15.5a4 4 0 0 1 .6-8 5.5 5.5 0 0 1 10.5 1.6 3.5 3.5 0 0 1-.6 6.4"/><path d="M8.5 18v2.5"/><path d="M12 18.5v2.5"/><path d="M15.5 18v2.5"/>',
    'cloud-sun': '<circle cx="8" cy="7" r="2.6"/><path d="M8 2v1.6"/><path d="M3 7h1.6"/><path d="M10.5 17.5a3.5 3.5 0 0 1 .5-7 4.8 4.8 0 0 1 9.2 1.4 3 3 0 0 1-.7 5.6Z"/>'
  };

  /** Monta o <svg> de um ícone com o tamanho informado (em pixels). */
  function svg(name, size) {
    return '<svg class="aq-icon" style="width:' + size + 'px;height:' + size + 'px" viewBox="0 0 24 24"'
      + ' fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"'
      + ' aria-hidden="true">' + (ICONS[name] || ICONS['cloud-rain']) + '</svg>'; // ícone desconhecido usa o de chuva
  }

  window.AqMonitorPage({
    scopes: ['chart', 'current'],
    fetch: function (p) { return window.AqApi.rain(p); },

    render: function (d) {                                            // d = MonitoringService::precipitation()
      var k = d.kpis;

      S.fill({
        'rain_24h.value': F.num(k.rain_24h.value, 1), 'rain_24h.foot': k.rain_24h.note,
        'rain_7d.value': F.num(k.rain_7d.value, 1), 'rain_7d.foot': k.rain_7d.note,
        'rain_month.value': F.num(k.rain_month.value, 1), 'rain_month.foot': k.rain_month.note,
        'intensity.value': { html: '<span style="color:var(--aq-warning)">' + k.intensity.label + '</span>' }, // intensidade sempre em âmbar
        'intensity.foot': k.intensity.note,
        'current.value': F.num(d.current.value, 1),
        'current.label': d.current.label,
        'current.humidity': d.current.humidity + '%',
        'current.last': d.current.last
      });

      /* -------------- barras diárias + linha de acumulado (dois eixos) */
      var c = d.chart;
      G.create('grafico-chuva', {
        type: 'bar',                                                  // gráfico misto: o tipo base é barra...
        data: {
          labels: c.labels,
          datasets: [
            Object.assign(G.bar('Precipitação diária (mm)', c.daily, G.colors.primary, { maxThickness: 34 }), { order: 2, yAxisID: 'y' }), // barras no eixo da esquerda; order 2 = desenhadas atrás
            Object.assign(
              G.line('Acumulado (mm)', c.accumulated, G.colors.primary, { width: 2, points: true, tension: 0.2 }),
              { type: 'line', order: 1, yAxisID: 'y1' }             // ...e esta série vira linha, no eixo da direita, desenhada na frente
            )
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: { padding: { top: 20 } },
          scales: {
            x: { grid: { display: false }, ticks: { color: G.colors.axis, maxRotation: 0 }, border: { display: false } },
            y: {                                                      // eixo esquerdo: chuva diária (0 a 40 mm)
              position: 'left', beginAtZero: true, max: 40,
              title: { display: true, text: 'Precipitação (mm)', color: G.colors.axis, font: { size: 10 } },
              grid: { color: G.colors.grid }, border: { display: false }, ticks: { color: G.colors.axis }
            },
            y1: {                                                     // eixo direito: acumulado (0 a 100 mm)
              position: 'right', beginAtZero: true, max: 100,
              title: { display: true, text: 'Acumulado (mm)', color: G.colors.axis, font: { size: 10 } },
              grid: { display: false }, border: { display: false }, ticks: { color: G.colors.axis } // sem grade para não duplicar as linhas do eixo esquerdo
            }
          },
          plugins: G.plugins('mm', 1)
        },
        plugins: [G.valueLabels({ datasets: [0], decimals: 1, color: '#09245a', offset: 6 })] // valores escritos só sobre as barras (dataset 0)
      });
      G.describe('grafico-chuva', c.daily, 'mm', 1);

      /* --------------------------------------- distribuição na bacia */
      document.querySelector('[data-basin]').innerHTML = d.basin.map(function (b) { // uma linha por estação da bacia
        var key = b.level === 'high' ? 'attention' : (b.level === 'medium' ? 'info' : 'normal'); // alta = âmbar, média = azul, baixa = verde
        return '<div class="aq-list__item">'
          + '<span class="aq-list__icon aq-list__icon--' + key + '" aria-hidden="true">' + svg('cloud-rain', 18) + '</span>'
          + '<div class="aq-list__body"><p class="aq-list__title">' + S.esc(b.name) + '</p></div>'
          + '<div class="aq-list__side"><strong style="color:var(--aq-text)">' + F.num(b.mm, 1) + ' mm</strong></div></div>';
      }).join('');

      /* ------------------------------------------- previsão 5 dias */
      var maxMm = Math.max.apply(null, d.forecast.map(function (f) { return f.mm; })) || 1; // maior previsão, base da escala das mini-barras ("|| 1" evita dividir por zero)
      document.querySelector('[data-forecast]').innerHTML = d.forecast.map(function (f) { // um card por dia
        var h = Math.max(10, Math.round(f.mm / maxMm * 56));          // altura da barra proporcional (máx. 56 px, mín. 10 px para continuar visível)
        return '<div>'
          + '<p style="font-size:.83rem;font-weight:700">' + S.esc(f.day) + '</p>'
          + '<p style="font-size:.78rem;color:var(--aq-text-secondary)">' + S.esc(f.date) + '</p>'
          + '<p style="color:var(--aq-text-secondary);display:flex;justify-content:center;margin:8px 0">' + svg(f.icon, 26) + '</p>'
          + '<div style="height:60px;display:flex;align-items:flex-end;justify-content:center">'  // área fixa de 60 px; a barra cresce de baixo para cima
          + '<span style="display:block;width:26px;height:' + h + 'px;border-radius:5px;background:var(--aq-primary)"></span></div>'
          + '<p style="font-size:.8rem;font-weight:700;margin-top:6px">' + f.mm + ' mm</p></div>';
      }).join('');

      /* ------------------------------------------------------ estações */
      document.querySelector('[data-stations]').innerHTML = d.stations.map(function (s) {
        var on = s.status === 'online';
        var color = s.rain_24h >= 20 ? 'warning' : (s.rain_24h >= 10 ? 'primary' : 'success'); // mesmos limites de 10 e 20 mm
        return '<tr>'
          + '<td><span class="aq-table__name"><span class="aq-table__icon" style="background:var(--aq-' + color + ');color:#fff">'
          + '<span style="font-size:.7rem;font-weight:800">' + S.esc(s.id) + '</span></span></span></td>' // selo colorido com o código da estação
          + '<td>' + S.esc(s.name) + '</td>'
          + '<td class="is-num">' + F.num(s.rain_24h, 1) + '</td>'
          + '<td><span class="aq-status-text"><span class="aq-dot aq-dot--' + (on ? 'normal' : 'offline') + '"></span>'
          + (on ? 'Online' : 'Offline') + '</span></td></tr>';
      }).join('');

      /* --------------------------------------------- aviso meteorológico */
      var warn = document.querySelector('[data-warning]');
      if (d.warning.active) {                                         // a API liga o aviso com 15 mm ou mais nas últimas 24 h
        warn.hidden = false;
        warn.innerHTML = '<article class="aq-card" style="flex-direction:row;align-items:center;gap:16px;'
          + 'border-color:#f6dfae;background:var(--aq-warning-soft)">'
          + '<span class="aq-kpi__icon aq-kpi__icon--warning" aria-hidden="true">'
          + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"'
          + ' stroke-linecap="round" stroke-linejoin="round"><path d="M12 4 2.8 19.5h18.4L12 4Z"/>'
          + '<path d="M12 10v4"/><path d="M12 17h.01"/></svg></span>'
          + '<div style="flex:1 1 auto"><h3 style="font-size:.98rem">' + S.esc(d.warning.title) + '</h3>'
          + '<p class="aq-card__sub">' + S.esc(d.warning.text) + '</p></div>'
          + '<button class="aq-btn aq-btn--ghost" type="button" data-modal-open="modal-aviso">Ver detalhes</button>' // obs.: não existe elemento #modal-aviso na página
          + '</article>';
      } else {
        warn.hidden = true;
      }
    }
  });
})();
