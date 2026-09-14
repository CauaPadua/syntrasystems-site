<?php
/**
 * Aquapulse — landingg page institucional.
 *
 * Etapa 1 do projeto: apenas a página pública. Sem autenticação, banco de
 * dados, API ou painel administrativo.
 *
 * Observação: o texto acima é da primeira etapa. Hoje o projeto também tem
 * login (login.php), API (api/v1) e painel (dashboard/); esta página continua
 * sendo só a parte pública e não usa nenhum deles.
 *
 * Estrutura: head -> header.php -> seções (hero, informações, sistema,
 * vantagens) -> footer.php -> scripts. Cada seção vive em includes/sections/.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';                            // textos, menu e funções aq_out()/aq_asset()
require_once __DIR__ . '/includes/icons.php';                             // ícones SVG

/*
 * Versão dos assets a partir da data de modificação: qualquer alteração no
 * CSS ou no JS muda a URL sozinha, evitando que o navegador sirva um arquivo
 * antigo em cache junto com o HTML novo.
 */
$aq_version = (string) max(                                               // max() pega a data de modificação mais recente entre os arquivos
    (int) @filemtime(__DIR__ . '/assets/css/style.css'),                  // filemtime = data de modificação; @ silencia o aviso se o arquivo não existir (vira 0)
    (int) @filemtime(__DIR__ . '/assets/js/main.js'),
    (int) @filemtime(__DIR__ . '/assets/css/monitorar.css'),
    (int) @filemtime(__DIR__ . '/assets/js/monitorar.js'),
    (int) @filemtime(__DIR__ . '/assets/css/vantagens.css'),
    (int) @filemtime(__DIR__ . '/assets/css/sistema.css'),
    (int) @filemtime(__DIR__ . '/assets/js/sistema-carrossel.js')
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#ffffff">
  <title>Aquapulse — Monitoramento inteligente para represas mais segura</title>
  <meta name="description" content="O Aquapulse centraliza as informações dos seus reservatórios com dados contínuos, análise inteligente e alertas precisos para decisões seguras e uma gestão eficiente dos recursos hídricos."> <?php /* resumo exibido por buscadores */ ?>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">

  <link rel="preload" as="image" href="<?php aq_out(aq_asset('images/hero-reservatorio.webp')); // pré-carrega a foto da hero: é o maior elemento da primeira tela ?>" fetchpriority="high">
  <link rel="stylesheet" href="<?php aq_out(aq_asset('css/style.css')); // estilos base compartilhados com o login ?>?v=<?php aq_out($aq_version); ?>">
  <?php /* estilos exclusivos da seção "Por que monitorar": só a landing carrega */ ?>
  <link rel="stylesheet" href="<?php aq_out(aq_asset('css/monitorar.css')); ?>?v=<?php aq_out($aq_version); ?>">
  <?php /* estilos exclusivos da seção "Vantagens" */ ?>
  <link rel="stylesheet" href="<?php aq_out(aq_asset('css/vantagens.css')); ?>?v=<?php aq_out($aq_version); ?>">
  <?php /* estilos exclusivos da seção "Sistema" */ ?>
  <link rel="stylesheet" href="<?php aq_out(aq_asset('css/sistema.css')); ?>?v=<?php aq_out($aq_version); ?>">

  <script>document.documentElement.classList.add('js');</script>
</head>
<body>

  <?php require __DIR__ . '/includes/header.php'; // cabeçalho fixo com logo e menu ?>

  <main id="conteudo"> <?php /* id="conteudo": destino do link "Ir para o conteúdo principal" */ ?>
    <?php require __DIR__ . '/includes/sections/hero.php'; // 1. primeira dobra com foto e chamada ?>
    <?php require __DIR__ . '/includes/sections/informacoes.php'; // 2. "Por que monitorar" com carrossel de represas ?>
    <?php require __DIR__ . '/includes/sections/sistema.php'; // 3. o sistema, com carrossel de telas ?>
    <?php require __DIR__ . '/includes/sections/vantagens.php'; // 4. vantagens + chamada para demonstração ?>
  </main>

  <?php require __DIR__ . '/includes/footer.php'; ?>

  <?php /* defer: os scripts rodam depois que todo o HTML foi lido, na ordem em que aparecem */ ?>
  <script src="<?php aq_out(aq_asset('js/main.js')); // menu, cabeçalho e animações de entrada ?>?v=<?php aq_out($aq_version); ?>" defer></script>
  <script src="<?php aq_out(aq_asset('js/monitorar.js')); // carrossel da seção "Por que monitorar" ?>?v=<?php aq_out($aq_version); ?>" defer></script>
  <script src="<?php aq_out(aq_asset('js/sistema-carrossel.js')); // carrossel de telas da seção "Sistema" ?>?v=<?php aq_out($aq_version); ?>" defer></script>
</body>
</html>
