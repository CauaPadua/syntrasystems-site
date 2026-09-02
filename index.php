<?php
/**
 * Aquapulse — landing page institucional.
 *
 * Etapa 1 do projeto: apenas a página pública. Sem autenticação, banco de
 * dados, API ou painel administrativo.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/icons.php';

$aq_version = '1.0.0';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#ffffff">
  <title>Aquapulse — Monitoramento inteligente para represas mais segura</title>
  <meta name="description" content="O Aquapulse centraliza as informações dos seus reservatórios com dados contínuos, análise inteligente e alertas precisos para decisões seguras e uma gestão eficiente dos recursos hídricos.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">

  <link rel="preload" as="image" href="<?php aq_out(aq_asset('images/hero-represa.webp')); ?>" fetchpriority="high">
  <link rel="stylesheet" href="<?php aq_out(aq_asset('css/style.css')); ?>?v=<?php aq_out($aq_version); ?>">

  <script>document.documentElement.classList.add('js');</script>
</head>
<body>

  <?php require __DIR__ . '/includes/header.php'; ?>

  <main id="conteudo">
    <?php require __DIR__ . '/includes/sections/hero.php'; ?>
    <?php require __DIR__ . '/includes/sections/informacoes.php'; ?>
    <?php require __DIR__ . '/includes/sections/sistema.php'; ?>
    <?php require __DIR__ . '/includes/sections/vantagens.php'; ?>
  </main>

  <?php require __DIR__ . '/includes/footer.php'; ?>

  <script src="<?php aq_out(aq_asset('js/main.js')); ?>?v=<?php aq_out($aq_version); ?>" defer></script>
</body>
</html>
