/**
 * Aquapulse — mapas com Leaflet + OpenStreetMap.
 *
 * Mapa real e interativo: nada de captura de tela.
 * As coordenadas vêm da API (hoje simuladas) — o navegador nunca acessa o
 * banco diretamente.
 *
 * Sem chave paga e sem Google Maps.
 *
 * Depende da biblioteca Leaflet (objeto global L), carregada por page.php
 * quando a página pede needs_map. Exposto como window.AqMap (render, focus, showFallback).
 */
window.AqMap = (function () {
  'use strict';

  var TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'; // endereço das imagens ("tiles") do mapa: {z}=zoom, {x}/{y}=posição, {s}=subdomínio
  var ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'; // crédito exigido pela licença do OpenStreetMap

  var instances = {};                                                 // mapas já criados, por id do contêiner: evita criar dois mapas no mesmo div

  /** Ícone de marcador colorido por status, desenhado em HTML/CSS. */
  function markerIcon(status) {
    var icons = {                                                     // desenho SVG de cada status: check, triângulo de alerta, círculo de exclamação
      normal: '<path d="M20.5 11.3V12a8.5 8.5 0 1 1-5-7.77"/><path d="m8.6 11.6 3 3 8.9-9"/>',
      attention: '<path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
      critical: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.8v4.7"/><path d="M12 16.2h.01"/>'
    };
    var svg = '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
      + ' stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
      + (icons[status] || icons.normal) + '</svg>';                   // status desconhecido usa o ícone normal

    return L.divIcon({                                                // divIcon: marcador feito de HTML (em vez de imagem PNG)
      className: '',                                                  // remove a classe padrão do Leaflet (fundo branco)
      html: '<span class="aq-marker aq-marker--' + status + '">' + svg + '</span>', // a classe do status define a cor no CSS
      iconSize: [30, 30],                                             // tamanho em pixels
      iconAnchor: [15, 30],                                           // ponto do ícone que fica sobre a coordenada: centro da base
      popupAnchor: [0, -30]                                           // o popup abre 30 px acima do ponto
    });
  }

  /** Conteúdo do popup de uma represa. */
  function popupHtml(m) {
    var e = window.AqShell.esc;                                       // escape de HTML: nomes vindos da API não podem injetar tags
    var F = window.AqFormat;

    return '<div class="aq-popup">'
      + '<p class="aq-popup__title">' + e(m.name) + '</p>'
      + '<div class="aq-popup__row"><span>Localização</span><span>' + e(m.city) + '</span></div>'
      + '<div class="aq-popup__row"><span>Nível</span><span>' + F.pct(m.level) + '</span></div>'
      + '<div class="aq-popup__row"><span>Vazão</span><span>' + F.unit(m.flow, 'm³/s') + '</span></div>'
      + '<div class="aq-popup__row"><span>Situação</span><span>' + e(m.status.label) + '</span></div>'
      + '</div>';
  }

  /**
   * Cria ou atualiza um mapa.
   *
   * @param {string} id       id do contêiner
   * @param {array}  markers  marcadores vindos da API
   * @param {object} options  { onSelect, zoomControl, selectedId }
   *                          (também aceita tooltip: true para rótulos sempre visíveis)
   * @returns {object|null} instância do mapa Leaflet, ou null se não foi possível criar
   */
  function render(id, markers, options) {
    var o = options || {};
    var el = document.getElementById(id);
    if (!el || typeof L === 'undefined') {                            // contêiner inexistente ou Leaflet não carregou (sem internet, bloqueio)
      showFallback(id);                                               // mostra a mensagem "Mapa indisponível"
      return null;
    }

    var map = instances[id];

    if (!map) {                                                       // primeira vez: cria o mapa e a camada de imagens
      map = L.map(el, {
        zoomControl: o.zoomControl !== false,                         // botões +/− ligados, a não ser que zoomControl seja false
        scrollWheelZoom: false,                                       // a rodinha do mouse rola a página, não dá zoom sem querer
        attributionControl: true
      });
      instances[id] = map;

      var tiles = L.tileLayer(TILE_URL, { maxZoom: 18, attribution: ATTRIBUTION });

      // fallback claro quando os tiles não carregam (sem internet, por exemplo)
      var failed = 0;                                                 // quantas imagens do mapa falharam
      tiles.on('tileerror', function () {
        failed++;
        if (failed > 3) showFallback(id);                             // tolera falhas pontuais; mais de 3 = mapa indisponível
      });
      tiles.on('load', function () { hideFallback(id); });            // carregou: esconde a mensagem

      tiles.addTo(map);
      map.layerGroup = L.layerGroup().addTo(map);                     // grupo onde ficam os marcadores (facilita limpar todos de uma vez)
    }

    map.layerGroup.clearLayers();                                     // remove marcadores anteriores antes de desenhar os novos

    if (!markers || !markers.length) {                                // sem represas: centraliza na região de Rio Claro/SP com zoom 9
      map.setView([-22.3, -47.65], 9);
      return map;
    }

    var bounds = [];                                                  // coordenadas de todos os marcadores, para enquadrar o mapa
    markers.forEach(function (m) {                                    // cria um marcador por represa
      var marker = L.marker([m.lat, m.lng], {
        icon: markerIcon(m.status.key),
        title: m.name,                                                // texto ao passar o mouse
        alt: m.name + ' — situação ' + m.status.label,                // descrição para leitores de tela
        keyboard: true                                                // o marcador pode receber foco pelo teclado
      });

      marker.bindPopup(popupHtml(m));                                 // popup aberto ao clicar

      // rótulo sempre visível: identifica a represa e a situação sem exigir
      // clique. O conteúdo fica no DOM, então também é lido por leitor de tela.
      if (o.tooltip) {
        var e = window.AqShell.esc;
        marker.bindTooltip(
          '<strong>' + e(m.name) + '</strong><br>Situação: ' + e(m.status.label),
          { permanent: true, direction: 'top', offset: [0, -32], className: 'aq-map-tip' }
        );
      }

      marker.on('click', function () {
        if (typeof o.onSelect === 'function') o.onSelect(m);          // avisa a tela (ex.: preencher o painel lateral em mapas.php)
      });
      marker.addTo(map.layerGroup);
      bounds.push([m.lat, m.lng]);
    });

    // zoom automático para enquadrar todos os marcadores
    if (bounds.length === 1) {
      map.setView(bounds[0], 12);                                     // um único ponto: centraliza com zoom 12
    } else {
      map.fitBounds(bounds, { padding: [46, 46], maxZoom: 12 });      // vários: ajusta o zoom para caber todos, com margem de 46 px
    }

    // o Leaflet precisa recalcular o tamanho quando o contêiner acabou de surgir
    window.setTimeout(function () { map.invalidateSize(); }, 120);

    return map;
  }

  /** Mostra a mensagem "Mapa indisponível" ligada ao mapa informado. */
  function showFallback(id) {
    var fb = document.querySelector('[data-map-fallback="' + id + '"]');
    if (fb) fb.hidden = false;
  }

  /** Esconde a mensagem de mapa indisponível. */
  function hideFallback(id) {
    var fb = document.querySelector('[data-map-fallback="' + id + '"]');
    if (fb) fb.hidden = true;
  }

  /** Centraliza o mapa em uma represa (usado pelo painel lateral). */
  function focus(id, marker) {
    var map = instances[id];
    if (map && marker) {
      map.setView([marker.lat, marker.lng], 12, { animate: true });   // desliza suavemente até a represa
    }
  }

  return {
    render: render,
    focus: focus,
    showFallback: showFallback
  };
})();
