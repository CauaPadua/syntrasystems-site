<?php
/** Aquapulse — Mapas (Leaflet + OpenStreetMap). */

declare(strict_types=1);

define('AQ_DEPTH', 1);
require __DIR__ . '/includes/page.php';

aq_page_start([
    'route'    => 'maps',
    'title'    => 'Mapas',
    'subtitle' => 'Visualize a localização e a situação das represas',
]);
?>

<section class="aq-context" aria-label="Filtros do mapa">
  <?php echo aq_select(['id' => 'filtro-empresa', 'label' => 'Empresa', 'options' => ['all' => 'Todas as empresas']]); ?>

  <div class="aq-field" style="flex:1 1 240px">
    <label class="aq-field__label" for="busca-represa">Buscar represa</label>
    <input class="aq-input" type="search" id="busca-represa" placeholder="Buscar represa" style="width:100%">
  </div>

  <span class="aq-context__spacer"></span>
</section>

<div class="aq-grid aq-grid--3-2">
  <article class="aq-card aq-card--flush" style="padding:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px" data-status-filters>
      <button class="aq-chip is-active" type="button" data-status="all"><?php aq_the_icon('grid'); ?><span>Todas</span></button>
      <button class="aq-chip" type="button" data-status="normal"><span class="aq-dot aq-dot--normal"></span><span>Normal</span></button>
      <button class="aq-chip" type="button" data-status="attention"><span class="aq-dot aq-dot--attention"></span><span>Atenção</span></button>
      <button class="aq-chip" type="button" data-status="critical"><span class="aq-dot aq-dot--critical"></span><span>Crítico</span></button>
    </div>

    <div class="aq-map aq-map--lg" id="mapa-principal">
      <div class="aq-map__fallback" data-map-fallback="mapa-principal" hidden>
        <span class="aq-state__icon" aria-hidden="true"><?php aq_the_icon('map'); ?></span>
        <p class="aq-state__title">Mapa indisponível</p>
        <p class="aq-state__text">
          Não foi possível carregar os blocos do mapa. Verifique a conexão com a internet —
          o mapa usa OpenStreetMap. As represas continuam listadas abaixo.
        </p>
      </div>
    </div>

    <div class="aq-map-legend" style="margin-top:14px;flex-direction:row;gap:20px;align-items:center">
      <strong>Legenda</strong>
      <div><span class="aq-dot aq-dot--normal"></span> Normal</div>
      <div><span class="aq-dot aq-dot--attention"></span> Atenção</div>
      <div><span class="aq-dot aq-dot--critical"></span> Crítico</div>
    </div>
  </article>

  <!-- ------------------------------- painel lateral da represa selecionada -->
  <article class="aq-card" data-panel>
    <div data-content="panel" hidden>
      <h2 style="font-size:1.2rem" data-field="sel.name">—</h2>

      <div class="aq-form-row">
        <span>Status operacional</span>
        <span data-field="sel.status"></span>
      </div>

      <p class="aq-card__sub" style="display:flex;align-items:center;gap:8px;margin-top:12px">
        <?php aq_the_icon('map-pin'); ?> Localização
        <strong style="margin-left:auto;color:var(--aq-text)" data-field="sel.basin">—</strong>
      </p>
      <p class="aq-card__sub" style="display:flex;align-items:center;gap:8px;margin-top:8px">
        <?php aq_the_icon('locate'); ?> Coordenadas
        <strong style="margin-left:auto;color:var(--aq-text)" data-field="sel.coords">—</strong>
      </p>

      <div class="aq-grid aq-grid--2" style="margin-top:16px">
        <?php
        echo aq_kpi(['id' => 'sel.level', 'label' => 'Nível atual', 'icon' => 'waves', 'unit' => '%']);
        echo aq_kpi(['id' => 'sel.flow', 'label' => 'Vazão atual', 'icon' => 'arrow-down-circle', 'unit' => 'm³/s']);
        echo aq_kpi(['id' => 'sel.ph', 'label' => 'pH da água', 'icon' => 'droplet', 'badge' => true]);
        echo aq_kpi(['id' => 'sel.rain', 'label' => 'Precipitação (24h)', 'icon' => 'cloud-rain', 'unit' => 'mm']);
        echo aq_kpi(['id' => 'sel.duration', 'label' => 'Previsão de duração', 'icon' => 'clock', 'unit' => 'dias']);
        echo aq_kpi(['id' => 'sel.operation', 'label' => 'Situação operacional', 'icon' => 'shield-check', 'tone' => 'success']);
        ?>
      </div>

      <a class="aq-btn aq-btn--primary" href="monitoramento/vazao.php" style="width:100%;margin-top:16px" data-monitor-link>
        <span>Ver monitoramento completo</span><?php aq_the_icon('chart-up'); ?>
      </a>
    </div>
    <?php echo aq_states('panel'); ?>
  </article>
</div>

<article class="aq-card">
  <?php echo aq_card_head([
      'title'   => 'Represas cadastradas',
      'tip'     => 'Clique em uma represa para centralizar o mapa e ver os detalhes.',
      'actions' => '<span class="aq-card__sub">Coordenadas demonstrativas</span>',
  ]); ?>
  <div class="aq-grid aq-grid--3" data-reservoir-cards></div>
</article>

<div class="aq-demo-note">
  <?php aq_the_icon('info'); ?>
  <span><strong>Coordenadas demonstrativas.</strong> Os pontos vêm da fonte simulada da API e deverão ser
  substituídos pelas coordenadas reais quando o banco de dados for conectado. O mapa usa Leaflet com
  blocos do OpenStreetMap — sem chave paga e sem Google Maps.</span>
</div>

<?php aq_page_end(['scripts' => ['pages/maps-page.js'], 'needs_map' => true]);
