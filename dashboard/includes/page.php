<?php
/**
 * Aquapulse — inicialização e layout das páginas do sistema interno.
 *
 * Toda página do dashboard inclui este arquivo. Ele:
 *   - exige sessão válida (reutilizando a autenticação já existente);
 *   - carrega ícones e componentes compartilhados;
 *   - define o menu em um único lugar (nenhuma página duplica a sidebar);
 *   - abre e fecha o shell visual.
 *
 * Uso:
 *   require __DIR__ . '/includes/page.php';
 *   aq_page_start(['route' => 'overview', 'title' => '...', 'subtitle' => '...']);
 *   ... conteúdo ...
 *   aq_page_end(['scripts' => ['pages/overview.js']]);
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/icons.php';
require_once __DIR__ . '/components.php';

use Aquapulse\Support\Clock;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;

/* ------------------------------------------------------------------ sessão */
/*
 * Profundidade da página em relação à raiz do projeto:
 *   dashboard/index.php            -> AQ_DEPTH = 1  (base "../")
 *   dashboard/monitoramento/x.php  -> AQ_DEPTH = 2  (base "../../")
 * Definida por cada página ANTES de incluir este arquivo.
 */
if (!defined('AQ_DEPTH')) {
    define('AQ_DEPTH', 1);
}

/** Caminho relativo até a raiz do projeto. */
function aq_base(): string
{
    return str_repeat('../', AQ_DEPTH);
}

/** Caminho relativo até a pasta dashboard/. */
function aq_dash(): string
{
    return AQ_DEPTH > 1 ? str_repeat('../', AQ_DEPTH - 1) : './';
}

$AQ_USER = Guard::requirePageSession(aq_base() . 'login.php');

/* ------------------------------------------------------- menu do sistema */
/**
 * Estrutura única do menu. Usada pela sidebar de todas as páginas.
 * `route` identifica o item ativo.
 */
function aq_nav(): array
{
    return [
        ['route' => 'overview',   'label' => 'Visão geral', 'icon' => 'home', 'href' => 'index.php'],
        [
            'route'    => 'monitoring',
            'label'    => 'Monitoramento',
            'icon'     => 'activity',
            'children' => [
                ['route' => 'monitoring.flow',       'label' => 'Volume de vazão',      'href' => 'monitoramento/vazao.php'],
                ['route' => 'monitoring.level',      'label' => 'Nível do reservatório','href' => 'monitoramento/nivel.php'],
                ['route' => 'monitoring.ph',         'label' => 'pH',                   'href' => 'monitoramento/ph.php'],
                ['route' => 'monitoring.storage',    'label' => 'Volume armazenado',    'href' => 'monitoramento/volume.php'],
                ['route' => 'monitoring.rain',       'label' => 'Precipitação',         'href' => 'monitoramento/precipitacao.php'],
                ['route' => 'monitoring.duration',   'label' => 'Duração da água',      'href' => 'monitoramento/duracao.php'],
                ['route' => 'monitoring.operation',  'label' => 'Situação operacional', 'href' => 'monitoramento/operacional.php'],
                ['route' => 'monitoring.comparison', 'label' => 'Comparativo de vazão', 'href' => 'monitoramento/comparativo.php'],
            ],
        ],
        ['route' => 'reports',  'label' => 'Relatórios',    'icon' => 'file-text',    'href' => 'relatorios.php'],
        ['route' => 'levels',   'label' => 'Níveis',        'icon' => 'layers-list',  'href' => 'niveis.php'],
        ['route' => 'maps',     'label' => 'Mapas',         'icon' => 'map',          'href' => 'mapas.php'],
        ['route' => 'alerts',   'label' => 'Alertas',       'icon' => 'bell',         'href' => 'alertas.php', 'badge' => 3],
        ['route' => 'settings', 'label' => 'Configurações', 'icon' => 'gear',         'href' => 'configuracoes.php'],
    ];
}

/**
 * Abre o shell visual da página.
 *
 * @param array{route:string,title:string,subtitle?:string,body_class?:string} $o
 */
function aq_page_start(array $o): void
{
    global $AQ_USER;

    $route    = $o['route'];
    $title    = $o['title'];
    $subtitle = $o['subtitle'] ?? '';
    $base     = aq_base();
    $dash     = aq_dash();
    $version  = '2.0.0';

    $isMonitoring = strpos($route, 'monitoring') === 0;

    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#05295f">
<meta name="robots" content="noindex, nofollow">
<title><?php aq_e($title); ?> — Aquapulse</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">

<link rel="stylesheet" href="<?php echo $base; ?>assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?php echo $base; ?>assets/css/dashboard.css?v=<?php echo $version; ?>">
<link rel="stylesheet" href="<?php echo $base; ?>assets/css/dashboard-responsive.css?v=<?php echo $version; ?>">
</head>
<body class="aq-app<?php echo !empty($o['body_class']) ? ' ' . aq_h($o['body_class']) : ''; ?>"
      data-route="<?php aq_e($route); ?>"
      data-api-base="<?php echo $base; ?>api/v1">

<a class="aq-skip" href="#aq-conteudo">Ir para o conteúdo</a>

<div class="aq-shell">

  <!-- ------------------------------------------------------------ sidebar -->
  <aside class="aq-sidebar" id="aq-sidebar" data-sidebar>
    <div class="aq-sidebar__brand">
      <a href="<?php echo $dash; ?>index.php" aria-label="Aquapulse — visão geral">
        <img src="<?php echo $base; ?>assets/images/logo-aquapulse-branco.png"
             alt="Aquapulse — monitoramento de represas" width="380" height="146">
      </a>
    </div>

    <nav class="aq-sidebar__nav" aria-label="Menu do sistema">
      <ul>
        <?php foreach (aq_nav() as $item): ?>
          <?php if (!isset($item['children'])): ?>
            <li class="aq-nav__item">
              <a class="aq-nav__link<?php echo $route === $item['route'] ? ' is-active' : ''; ?>"
                 href="<?php echo $dash . aq_h($item['href']); ?>"
                 <?php echo $route === $item['route'] ? 'aria-current="page"' : ''; ?>>
                <?php aq_the_icon($item['icon']); ?>
                <span class="aq-nav__label"><?php aq_e($item['label']); ?></span>
                <?php if (!empty($item['badge'])): ?>
                  <span class="aq-nav__badge"><?php aq_e((string) $item['badge']); ?><span class="aq-visually-hidden"> alertas ativos</span></span>
                <?php endif; ?>
              </a>
            </li>
          <?php else: ?>
            <li class="aq-nav__item">
              <button class="aq-nav__link<?php echo $isMonitoring ? ' is-active' : ''; ?>"
                      type="button"
                      data-submenu-toggle
                      aria-expanded="<?php echo $isMonitoring ? 'true' : 'false'; ?>"
                      aria-controls="aq-submenu-monitoramento">
                <?php aq_the_icon($item['icon']); ?>
                <span class="aq-nav__label"><?php aq_e($item['label']); ?></span>
                <span class="aq-nav__caret" aria-hidden="true"><?php aq_the_icon('chevron-down'); ?></span>
              </button>

              <ul class="aq-nav__submenu<?php echo $isMonitoring ? ' is-open' : ''; ?>"
                  id="aq-submenu-monitoramento">
                <?php foreach ($item['children'] as $child): ?>
                  <li>
                    <a class="aq-nav__sublink<?php echo $route === $child['route'] ? ' is-active' : ''; ?>"
                       href="<?php echo $dash . aq_h($child['href']); ?>"
                       <?php echo $route === $child['route'] ? 'aria-current="page"' : ''; ?>>
                      <span><?php aq_e($child['label']); ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="aq-sidebar__footer">
      <div class="aq-secure">
        <span class="aq-secure__icon" aria-hidden="true"><?php aq_the_icon('shield-lock'); ?></span>
        <div>
          <strong>Sistema seguro</strong>
          <span>Seus dados estão protegidos com criptografia de ponta a ponta.</span>
        </div>
      </div>
    </div>
  </aside>

  <div class="aq-backdrop" data-backdrop hidden></div>

  <!-- --------------------------------------------------------------- main -->
  <div class="aq-main">

    <header class="aq-topbar">
      <button class="aq-menu-toggle" type="button" data-menu-toggle
              aria-expanded="false" aria-controls="aq-sidebar" aria-label="Abrir menu do sistema">
        <?php aq_the_icon('menu'); ?>
      </button>

      <div class="aq-topbar__title">
        <h1><?php aq_e($title); ?></h1>
        <?php if ($subtitle !== ''): ?><p><?php aq_e($subtitle); ?></p><?php endif; ?>
      </div>

      <div class="aq-topbar__meta">
        <span class="aq-meta-item aq-meta-item--date">
          <?php aq_the_icon('calendar'); ?>
          <span><?php aq_e(Clock::longDateTime()); ?></span>
        </span>

        <button class="aq-refresh" type="button" data-refresh aria-label="Atualizar dados agora">
          <?php aq_the_icon('refresh'); ?>
          <span data-updated-label>Atualizado há 2 min</span>
        </button>

        <button class="aq-user" type="button" data-user-toggle aria-expanded="false" aria-controls="aq-user-menu">
          <span class="aq-user__avatar" aria-hidden="true"><?php aq_e(Guard::initials($AQ_USER['name'])); ?></span>
          <span class="aq-user__info">
            <span class="aq-user__name"><?php aq_e($AQ_USER['name']); ?></span>
            <span class="aq-user__role"><?php aq_e($AQ_USER['role'] === 'admin' ? 'Operador' : $AQ_USER['role']); ?></span>
          </span>
          <?php aq_the_icon('chevron-down'); ?>
        </button>

        <div class="aq-user-menu" id="aq-user-menu" data-user-menu hidden>
          <a href="<?php echo $dash; ?>configuracoes.php"><?php aq_the_icon('gear'); ?><span>Configurações</span></a>
          <button type="button" data-logout><?php aq_the_icon('log-out'); ?><span>Sair do sistema</span></button>
        </div>
      </div>
    </header>

    <main class="aq-content" id="aq-conteudo">
<?php
}

/**
 * Fecha o shell e carrega os scripts.
 *
 * @param array{scripts?:array<int,string>,needs_map?:bool} $o
 */
function aq_page_end(array $o = []): void
{
    $base = aq_base();
    $version = '2.0.0';
    ?>
    </main>
  </div>
</div>

<div class="aq-toast" data-toast hidden role="status" aria-live="polite">
  <span class="aq-toast__icon" aria-hidden="true"><?php aq_the_icon('check-circle'); ?></span>
  <div><strong data-toast-title></strong><span data-toast-text></span></div>
</div>

<script src="<?php echo $base; ?>assets/vendor/chartjs/chart.umd.js"></script>
<script src="<?php echo $base; ?>assets/vendor/chartjs/chartjs-plugin-annotation.min.js"></script>
<?php if (!empty($o['needs_map'])): ?>
<script src="<?php echo $base; ?>assets/vendor/leaflet/leaflet.js"></script>
<?php endif; ?>

<script src="<?php echo $base; ?>assets/js/format.js?v=<?php echo $version; ?>"></script>
<script src="<?php echo $base; ?>assets/js/api-client.js?v=<?php echo $version; ?>"></script>
<script src="<?php echo $base; ?>assets/js/charts.js?v=<?php echo $version; ?>"></script>
<script src="<?php echo $base; ?>assets/js/filters.js?v=<?php echo $version; ?>"></script>
<?php if (!empty($o['needs_map'])): ?>
<script src="<?php echo $base; ?>assets/js/maps.js?v=<?php echo $version; ?>"></script>
<?php endif; ?>
<script src="<?php echo $base; ?>assets/js/dashboard-shell.js?v=<?php echo $version; ?>"></script>
<?php if (!empty($o['monitor'])): ?>
<script src="<?php echo $base; ?>assets/js/monitor-page.js?v=<?php echo $version; ?>"></script>
<?php endif; ?>
<?php foreach ($o['scripts'] ?? [] as $s): ?>
<script src="<?php echo $base . 'assets/js/' . aq_h($s); ?>?v=<?php echo $version; ?>"></script>
<?php endforeach; ?>

</body>
</html>
<?php
}
