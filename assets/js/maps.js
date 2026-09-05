/**
 * Aquapulse — mapas com Leaflet + OpenStreetMap.
 *
 * Mapa real e interativo: nada de captura de tela.
 * As coordenadas vêm da API (hoje simuladas) — o navegador nunca acessa o
 * banco diretamente.
 *
 * Sem chave paga e sem Google Maps.
 */
window.AqMap = (function () {
  'use strict';

  var TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  var ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

  var instances = {};

  /** Ícone de marcador colorido por status, desenhado em HTML/CSS. */
  function markerIcon(status) {
    var icons = {
      normal: '<path d="M20.5 11.3V12a8.5 8.5 0 1 1-5-7.77"/><path d="m8.6 11.6 3 3 8.9-9"/>',
      attention: '<path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
      critical: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.8v4.7"/><path d="M12 16.2h.01"/>'
    };
    var svg = '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
      + ' stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
      + (icons[status] || icons.normal) + '</svg>';

    return L.divIcon({
      className: '',
      html: '<span class="aq-marker aq-marker--' + status + '">' + svg + '</span>',
      iconSize: [30, 30],
      iconAnchor: [15, 30],
      popupAnchor: [0, -30]
    });
  }

  /** Conteúdo do popup de uma represa. */
  function popupHtml(m) {
    var e = window.AqShell.esc;
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
   */
  function render(id, markers, options) {
    var o = options || {};
    var el = document.getElementById(id);
    if (!el || typeof L === 'undefined') {
      showFallback(id);
      return null;
    }

    var map = instances[id];

    if (!map) {
      map = L.map(el, {
        zoomControl: o.zoomControl !== false,
        scrollWheelZoom: false,
        attributionControl: true
      });
      instances[id] = map;

      var tiles = L.tileLayer(TILE_URL, { maxZoom: 18, attribution: ATTRIBUTION });

      // fallback claro quando os tiles não carregam (sem internet, por exemplo)
      var failed = 0;
      tiles.on('tileerror', function () {
        failed++;
        if (failed > 3) showFallback(id);
      });
      tiles.on('load', function () { hideFallback(id); });

      tiles.addTo(map);
      map.layerGroup = L.layerGroup().addTo(map);
    }

    map.layerGroup.clearLayers();

    if (!markers || !markers.length) {
      map.setView([-22.3, -47.65], 9);
      return map;
    }

    var bounds = [];
    markers.forEach(function (m) {
      var marker = L.marker([m.lat, m.lng], {
        icon: markerIcon(m.status.key),
        title: m.name,
        alt: m.name + ' — situação ' + m.status.label,
        keyboard: true
      });

      marker.bindPopup(popupHtml(m));
      marker.on('click', function () {
        if (typeof o.onSelect === 'function') o.onSelect(m);
      });
      marker.addTo(map.layerGroup);
      bounds.push([m.lat, m.lng]);
    });

    // zoom automático para enquadrar todos os marcadores
    if (bounds.length === 1) {
      map.setView(bounds[0], 12);
    } else {
      map.fitBounds(bounds, { padding: [46, 46], maxZoom: 12 });
    }

    // o Leaflet precisa recalcular o tamanho quando o contêiner acabou de surgir
    window.setTimeout(function () { map.invalidateSize(); }, 120);

    return map;
  }

  function showFallback(id) {
    var fb = document.querySelector('[data-map-fallback="' + id + '"]');
    if (fb) fb.hidden = false;
  }

  function hideFallback(id) {
    var fb = document.querySelector('[data-map-fallback="' + id + '"]');
    if (fb) fb.hidden = true;
  }

  /** Centraliza o mapa em uma represa (usado pelo painel lateral). */
  function focus(id, marker) {
    var map = instances[id];
    if (map && marker) {
      map.setView([marker.lat, marker.lng], 12, { animate: true });
    }
  }

  return {
    render: render,
    focus: focus,
    showFallback: showFallback
  };
})();
