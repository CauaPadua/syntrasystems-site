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
 *
 * Divisão de trabalho: o PHP gera só a ESTRUTURA da página (menu, topbar,
 * cards vazios com "—"). Os NÚMEROS são buscados depois pelo JavaScript da tela,
 * na API (api/v1/*), e escritos nos elementos marcados com data-field.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';        // sessão, autoload e tratamento de erros
require_once dirname(__DIR__, 2) . '/includes/icons.php';             // aq_icon() e aq_the_icon(): ícones SVG compartilhados com a landing
require_once __DIR__ . '/components.php';                            // cards, gráficos, selects e estados reutilizáveis

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
if (!defined('AQ_DEPTH')) {                                          // páginas da pasta dashboard/ não precisam definir: vale 1
    define('AQ_DEPTH', 1);
}

/**
 * Versão dos assets, para quebrar o cache do navegador.
 *
 * Antes era a constante '2.0.0' escrita à mão em dois lugares: qualquer
 * alteração em CSS/JS continuava sendo servida com a MESMA URL, e navegadores
 * que já tinham a página em cache seguiam executando o JavaScript antigo
 * contra a API nova — a origem dos gráficos "vazios" após uma atualização.
 *
 * Agora a versão vem da data de modificação mais recente entre os assets, de
 * modo que a URL muda sozinha sempre que um arquivo muda. É calculada uma vez
 * por requisição.
 */
function aq_asset_version(): string
{
    static $versao = null;                                           // "static" mantém o valor entre chamadas da função na mesma requisição
    if ($versao !== null) {                                          // já calculado: devolve direto (evita varrer as pastas de novo)
        return $versao;
    }

    $raiz = dirname(__DIR__, 2);                                     // raiz do projeto
    $maisRecente = 0;                                                // maior timestamp de modificação encontrado

    foreach ([$raiz . '/assets/css', $raiz . '/assets/js'] as $pasta) { // varre as duas pastas de assets próprios
        if (!is_dir($pasta)) {
            continue;
        }
        $itens = new RecursiveIteratorIterator(                      // percorre a pasta e todas as subpastas (ex.: assets/js/pages)
            new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS) // SKIP_DOTS ignora "." e ".."
        );
        foreach ($itens as $arquivo) {
            /** @var SplFileInfo $arquivo */
            if (!$arquivo->isFile()) {
                continue;
            }
            $ext = strtolower($arquivo->getExtension());
            if ($ext !== 'css' && $ext !== 'js') {                   // só CSS e JS influenciam a versão
                continue;
            }
            $maisRecente = max($maisRecente, (int) $arquivo->getMTime()); // getMTime = data da última modificação do arquivo
        }
    }

    // sem assets legíveis, cai para um valor fixo em vez de quebrar a página
    $versao = $maisRecente > 0 ? (string) $maisRecente : '2.0.0';
    return $versao;                                                  // usado como "?v=123456" nas URLs de CSS e JS
}

/** Caminho relativo até a raiz do projeto. */
function aq_base(): string
{
    return str_repeat('../', AQ_DEPTH);                              // profundidade 2 -> "../../"
}

/** Caminho relativo até a pasta dashboard/. */
function aq_dash(): string
{
    return AQ_DEPTH > 1 ? str_repeat('../', AQ_DEPTH - 1) : './';    // de monitoramento/ sobe um nível; de dashboard/ fica em "./"
}

$AQ_USER = Guard::requirePageSession(aq_base() . 'login.php');       // PROTEÇÃO: sem sessão, redireciona ao login e o script para aqui

/* ------------------------------------------------------- menu do sistema */
/**
 * Estrutura única do menu. Usada pela sidebar de todas as páginas.
 * `route` identifica o item ativo.
 *
 * Cada item: route (identificador), label (texto), icon (nome do ícone),
 * href (caminho relativo a dashboard/). Um item com "children" vira submenu.
 */
function aq_nav(): array
{
    return [
        ['route' => 'overview',   'label' => 'Visão geral', 'icon' => 'home', 'href' => 'index.php'],
        [
            'route'    => 'monitoring',
            'label'    => 'Monitoramento',
            'icon'     => 'activity',
            'children' => [                                          // as oito telas detalhadas ficam agrupadas no submenu
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
        ['route' => 'alerts',   'label' => 'Alertas',       'icon' => 'bell',         'href' => 'alertas.php', 'badge' => 3], // contador fixo de alertas exibido no menu
        ['route' => 'settings', 'label' => 'Configurações', 'icon' => 'gear',         'href' => 'configuracoes.php'],
    ];
}

/**
 * Abre o shell visual da página.
 *
 * Imprime <!DOCTYPE>, <head>, a sidebar com o menu, a topbar (título, data,
 * botão de atualizar, menu do usuário) e abre o <main> onde a página coloca
 * o próprio conteúdo. O fechamento fica em aq_page_end().
 *
 * @param array{route:string,title:string,subtitle?:string,body_class?:string} $o
 */
function aq_page_start(array $o): void
{
    global $AQ_USER;                                                 // usuário autenticado obtido no topo deste arquivo

    $route    = $o['route'];                                         // qual item do menu fica destacado
    $title    = $o['title'];
    $subtitle = $o['subtitle'] ?? '';
    $base     = aq_base();
    $dash     = aq_dash();
    $version  = aq_asset_version();

    $isMonitoring = strpos($route, 'monitoring') === 0;              // true para as rotas "monitoring.*": o submenu começa aberto

    /* A partir da tag de fechamento do PHP logo abaixo, o texto é HTML enviado
       ao navegador; os blocos PHP no meio dele inserem os valores dinâmicos. */
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1"> <?php /* layout responsivo: largura da tela do dispositivo */ ?>
<meta name="theme-color" content="#05295f"> <?php /* cor da barra do navegador no celular */ ?>
<meta name="robots" content="noindex, nofollow"> <?php /* área interna: não deve aparecer em buscadores */ ?>
<title><?php aq_e($title); // título da aba, escapado contra injeção de HTML ?> — Aquapulse</title>

<link rel="preconnect" href="https://fonts.googleapis.com"> <?php /* abre a conexão com o servidor de fontes antes, para carregar mais rápido */ ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"> <?php /* fonte Manrope; display=swap mostra texto antes da fonte chegar */ ?>

<link rel="stylesheet" href="<?php echo $base; ?>assets/vendor/leaflet/leaflet.css"> <?php /* estilos da biblioteca de mapas */ ?>
<link rel="stylesheet" href="<?php echo $base; ?>assets/css/dashboard.css?v=<?php echo $version; // ?v muda quando o CSS muda, forçando o navegador a baixar de novo ?>">
<link rel="stylesheet" href="<?php echo $base; ?>assets/css/dashboard-responsive.css?v=<?php echo $version; ?>">
</head>
<?php /* data-route: tela atual (lido pelo JavaScript); data-api-base: caminho da API usado por api-client.js */ ?>
<body class="aq-app<?php echo !empty($o['body_class']) ? ' ' . aq_h($o['body_class']) : ''; ?>"
      data-route="<?php aq_e($route); ?>"
      data-api-base="<?php echo $base; ?>api/v1">

<a class="aq-skip" href="#aq-conteudo">Ir para o conteúdo</a> <?php /* link de acessibilidade: aparece com Tab e pula direto ao conteúdo */ ?>

<div class="aq-shell"> <?php /* grade geral: sidebar à esquerda + área principal */ ?>

  <!-- ------------------------------------------------------------ sidebar -->
  <aside class="aq-sidebar" id="aq-sidebar" data-sidebar> <?php /* menu lateral; no celular vira gaveta aberta pelo botão da topbar */ ?>
    <div class="aq-sidebar__brand">
      <a href="<?php echo $dash; ?>index.php" aria-label="Aquapulse — visão geral">
        <img src="<?php echo $base; ?>assets/images/logo-aquapulse-branco.png"
             alt="Aquapulse — monitoramento de represas" width="380" height="146">
      </a>
    </div>

    <nav class="aq-sidebar__nav" aria-label="Menu do sistema">
      <ul>
        <?php foreach (aq_nav() as $item): // um <li> por item do menu definido em aq_nav() ?>
          <?php if (!isset($item['children'])): // item simples: vira um link direto ?>
            <li class="aq-nav__item">
              <?php /* is-active + aria-current marcam a página atual (visual e para leitores de tela) */ ?>
              <a class="aq-nav__link<?php echo $route === $item['route'] ? ' is-active' : ''; ?>"
                 href="<?php echo $dash . aq_h($item['href']); ?>"
                 <?php echo $route === $item['route'] ? 'aria-current="page"' : ''; ?>>
                <?php aq_the_icon($item['icon']); ?>
                <span class="aq-nav__label"><?php aq_e($item['label']); ?></span>
                <?php if (!empty($item['badge'])): // só o item Alertas tem contador ?>
                  <span class="aq-nav__badge"><?php aq_e((string) $item['badge']); ?><span class="aq-visually-hidden"> alertas ativos</span></span>
                <?php endif; ?>
              </a>
            </li>
          <?php else: // item com filhos: botão que abre/fecha o submenu ?>
            <li class="aq-nav__item">
              <?php /* aria-expanded informa se o submenu está aberto; o JS (dashboard-shell.js) alterna esse valor */ ?>
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
                <?php foreach ($item['children'] as $child): // links das oito telas de monitoramento ?>
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

    <div class="aq-sidebar__footer"> <?php /* selo informativo no rodapé da sidebar */ ?>
      <div class="aq-secure">
        <span class="aq-secure__icon" aria-hidden="true"><?php aq_the_icon('shield-lock'); ?></span>
        <div>
          <strong>Sistema seguro</strong>
          <span>Seus dados estão protegidos com criptografia de ponta a ponta.</span>
        </div>
      </div>
    </div>
  </aside>

  <div class="aq-backdrop" data-backdrop hidden></div> <?php /* fundo escurecido atrás da gaveta do menu no celular; clicar fecha o menu */ ?>

  <!-- --------------------------------------------------------------- main -->
  <div class="aq-main">

    <header class="aq-topbar">
      <?php /* botão hambúrguer: só aparece em telas estreitas (CSS) */ ?>
      <button class="aq-menu-toggle" type="button" data-menu-toggle
              aria-expanded="false" aria-controls="aq-sidebar" aria-label="Abrir menu do sistema">
        <?php aq_the_icon('menu'); ?>
      </button>

      <div class="aq-topbar__title">
        <h1><?php aq_e($title); ?></h1> <?php /* título da tela (único h1 da página) */ ?>
        <?php if ($subtitle !== ''): ?><p><?php aq_e($subtitle); ?></p><?php endif; // subtítulo só quando informado ?>
      </div>

      <div class="aq-topbar__meta">
        <span class="aq-meta-item aq-meta-item--date">
          <?php aq_the_icon('calendar'); ?>
          <span><?php aq_e(Clock::longDateTime()); // data/hora da aplicação (fixa no modo demonstrativo) ?></span>
        </span>

        <button class="aq-refresh" type="button" data-refresh aria-label="Atualizar dados agora"> <?php /* recarrega os dados da tela via JS */ ?>
          <?php aq_the_icon('refresh'); ?>
          <span data-updated-label>Atualizado há 2 min</span> <?php /* o JS substitui pelo meta.updated_label da API */ ?>
        </button>

        <?php /* botão do usuário: abre o menu com Configurações e Sair */ ?>
        <button class="aq-user" type="button" data-user-toggle aria-expanded="false" aria-controls="aq-user-menu"
                data-user-email="<?php aq_e($AQ_USER['email']); ?>">
          <span class="aq-user__avatar" aria-hidden="true"><?php aq_e(Guard::initials($AQ_USER['name'])); // iniciais, ex.: "AS" ?></span>
          <span class="aq-user__info">
            <span class="aq-user__name"><?php aq_e($AQ_USER['name']); ?></span>
            <span class="aq-user__role"><?php aq_e($AQ_USER['role'] === 'admin' ? 'Operador' : $AQ_USER['role']); // o perfil "admin" é exibido como "Operador" ?></span>
          </span>
          <?php aq_the_icon('chevron-down'); ?>
        </button>

        <div class="aq-user-menu" id="aq-user-menu" data-user-menu hidden> <?php /* começa oculto (hidden); dashboard-shell.js mostra ao clicar */ ?>
          <a href="<?php echo $dash; ?>configuracoes.php"><?php aq_the_icon('gear'); ?><span>Configurações</span></a>
          <button type="button" data-logout><?php aq_the_icon('log-out'); ?><span>Sair do sistema</span></button> <?php /* chama POST api/v1/auth/logout.php */ ?>
        </div>
      </div>
    </header>

    <main class="aq-content" id="aq-conteudo"> <?php /* alvo do link "Ir para o conteúdo"; a página escreve seu conteúdo aqui dentro */ ?>
<?php
}

/**
 * Fecha o shell e carrega os scripts.
 *
 * A ordem dos <script> importa: bibliotecas (Chart.js, Leaflet) e módulos
 * compartilhados (format, api-client, charts, filters) vêm antes do shell e dos
 * scripts específicos da página, que dependem deles.
 *
 * @param array{scripts?:array<int,string>,needs_map?:bool} $o
 *        scripts   = arquivos de assets/js/ exclusivos da página (ex.: 'pages/overview.js')
 *        needs_map = true carrega Leaflet e maps.js
 *        monitor   = true carrega monitor-page.js (base das telas de monitoramento)
 */
function aq_page_end(array $o = []): void
{
    $base = aq_base();
    $version = aq_asset_version();
    ?>
    </main>
  </div>
</div>

<?php /* aviso flutuante (toast); role="status" + aria-live fazem o leitor de tela anunciar a mensagem */ ?>
<div class="aq-toast" data-toast hidden role="status" aria-live="polite">
  <span class="aq-toast__icon" aria-hidden="true"><?php aq_the_icon('check-circle'); ?></span>
  <div><strong data-toast-title></strong><span data-toast-text></span></div>
</div>

<script src="<?php echo $base; ?>assets/vendor/chartjs/chart.umd.js"></script> <?php /* biblioteca de gráficos Chart.js (cria o objeto global Chart) */ ?>
<script src="<?php echo $base; ?>assets/vendor/chartjs/chartjs-plugin-annotation.min.js"></script> <?php /* plugin de linhas de referência (limites, cotas) nos gráficos */ ?>
<?php if (!empty($o['needs_map'])): // Leaflet só é carregado nas páginas que têm mapa ?>
<script src="<?php echo $base; ?>assets/vendor/leaflet/leaflet.js"></script>
<?php endif; ?>

<script src="<?php echo $base; ?>assets/js/format.js?v=<?php echo $version; // formatação de números e datas (AqFormat) ?>"></script>
<script src="<?php echo $base; ?>assets/js/api-client.js?v=<?php echo $version; // chamadas à API (AqApi) ?>"></script>
<script src="<?php echo $base; ?>assets/js/charts.js?v=<?php echo $version; // fábrica de gráficos padronizados (AqCharts) ?>"></script>
<script src="<?php echo $base; ?>assets/js/filters.js?v=<?php echo $version; // contexto de filtros compartilhado entre telas: empresa, represa e período (AqContext) ?>"></script>
<?php if (!empty($o['needs_map'])): ?>
<script src="<?php echo $base; ?>assets/js/maps.js?v=<?php echo $version; // criação do mapa e marcadores (AqMap) ?>"></script>
<?php endif; ?>
<script src="<?php echo $base; ?>assets/js/dashboard-shell.js?v=<?php echo $version; // menu, topbar, logout e estados de carregamento (AqShell) ?>"></script>
<?php if (!empty($o['monitor'])): // telas de Monitoramento compartilham a lógica de seleção de represa/período (AqMonitorPage) ?>
<script src="<?php echo $base; ?>assets/js/monitor-page.js?v=<?php echo $version; ?>"></script>
<?php endif; ?>
<?php foreach ($o['scripts'] ?? [] as $s): // scripts próprios da página, na ordem informada ?>
<script src="<?php echo $base . 'assets/js/' . aq_h($s); ?>?v=<?php echo $version; ?>"></script>
<?php endforeach; ?>

</body>
</html>
<?php
}
