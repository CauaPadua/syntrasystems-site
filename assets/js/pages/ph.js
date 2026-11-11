/** Aquapulse — Monitoramento / pH. */
/*
 * Página: dashboard/monitoramento/ph.php | API: GET api/v1/monitoring/ph.php.
 * Represa, período, estados e recarga ficam em AqMonitorPage; aqui só o render().
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var G = window.AqCharts;

  /** Cor do texto conforme o status devolvido pela API. */
  function cor(status) {
    return 'var(--aq-' + (status === 'normal' ? 'success' : (status === 'critical' ? 'danger' : 'warning')) + ')';
  }

  /** Ponto colorido + rótulo do status ({key, label}). */
  function situacao(status) {
    return '<span class="aq-status-text"><span class="aq-dot aq-dot--' + S.esc(status.key) + '"></span>' + S.esc(status.label) + '</span>';
  }

  window.AqMonitorPage({
    scopes: ['variation', 'scale', 'daily'],
    fetch: function (p) { return window.AqApi.ph(p); },

    render: function (d) {
      var k = d.kpis;
      var corAtual = cor(k.condition.status.key);

      S.fill({
        'ph.value': F.num(k.ph.value, 2), 'ph.foot': k.ph.note,
        'min.value': F.num(k.min.value, 2), 'min.foot': k.min.note,
        'max.value': F.num(k.max.value, 2), 'max.foot': k.max.note,
        'condition.value': { html: '<span style="color:' + corAtual + '">' + S.esc(k.condition.label) + '</span>' },
        'condition.foot': k.condition.note,
        'scale.value': F.num(d.scale.value, 2),
        'scale.label': { html: '<span style="color:' + corAtual + '">' + S.esc(d.scale.label) + '</span>' },
        'scale.range': d.scale.range
      });

      /* ------------------------- variação com a faixa ideal destacada */
      var v = d.variation;
      G.create('grafico-ph', {
        type: 'line',
        data: {
          labels: v.labels,
          datasets: [G.line('pH observado', v.values, G.colors.primary, { width: 2.2 })]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ min: 0, max: 14, decimals: 0 }),
          plugins: G.plugins('', 2, {
            annotation: {
              annotations: { ideal: G.band(v.ideal_min, v.ideal_max, G.colors.success, v.ideal_label) }
            }
          })
        }
      });
      G.describe('grafico-ph', v.values, '', 2);

      /* -------------------- escala de pH de 0 a 14 (medidor segmentado) */
      var sc = d.scale;
      G.gauge('escala-ph', {
        value: sc.value,
        min: sc.min,
        max: sc.max,
        cutout: '68%',
        segments: [
          { size: 14.3, color: '#ef4444' },
          { size: 14.3, color: '#f59e0b' },
          { size: 14.3, color: '#fbbf24' },
          { size: 14.3, color: '#16a34a' },
          { size: 14.3, color: '#0ea5e9' },
          { size: 14.3, color: '#2563eb' },
          { size: 14.2, color: '#1e3a8a' }
        ]
      });

      // ponteiro: converte o pH em ângulo (0 = esquerda, 7 = em pé, 14 = direita)
      var needle = document.querySelector('[data-ph-needle]');
      if (needle) {
        var angle = ((sc.value - sc.min) / (sc.max - sc.min)) * 180 - 90;
        needle.style.transform = 'translateX(-50%) rotate(' + angle + 'deg)';
      }

      /* ----------------------------------------- média diária 7 dias */
      var dl = d.daily;
      G.create('grafico-ph-diario', {
        type: 'bar',
        data: {
          labels: dl.labels,
          datasets: [G.bar('pH médio diário', dl.values, '#93c5fd')]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: G.scales({ min: 0, max: 14, decimals: 0 }),
          plugins: G.plugins('', 2, {
            annotation: {
              annotations: { ideal: G.band(dl.ideal_min, dl.ideal_max, G.colors.success) }
            }
          })
        }
      });

      /* -------------------------------------------- equipamentos de pH */
      S.equipmentList('[data-sensors]', d.sensors, 'Esta represa não possui sonda de pH cadastrada.');

      /* --------------------------------------------- últimas leituras */
      document.querySelector('[data-readings]').innerHTML = d.readings.map(function (r) {
        return '<tr>'
          + '<td class="is-nowrap">' + S.esc(r.time) + '</td>'
          + '<td class="is-num">' + F.num(r.ph, 2) + '</td>'
          + '<td class="is-num">' + F.num(r.temp, 1) + '</td>'
          + '<td>' + S.esc(r.point) + '</td>'
          + '<td>' + situacao(r.status) + '</td>'
          + '</tr>';
      }).join('') || '<tr><td colspan="5">Nenhuma leitura de pH registrada.</td></tr>';
    }
  });
})();
