<?php
/**
 * Aquapulse — formulário público de solicitação (FRONT-END).
 *
 *   solicitar.php?tipo=acesso          colaborador de uma represa já monitorada
 *                                      (botão "Solicitar acesso" do login)
 *   solicitar.php?tipo=demonstracao    dono ou responsável por uma represa
 *                                      (botão "Solicitar demonstração" da página inicial)
 *
 * A página só desenha o formulário. assets/js/solicitar.js envia os dados para
 * POST api/v1/public/request.php, que valida e grava o pedido; quem responde é
 * o administrador, em dashboard/solicitacoes.php. Usa o visual da tela de login.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';                            // aq_out(), aq_asset() e as constantes do site
require_once __DIR__ . '/includes/icons.php';                             // aq_the_icon(): ícones SVG

$aq_version = '1.2.0';                                                    // muda a URL do CSS/JS quando a página muda

$demo = ($_GET['tipo'] ?? '') === 'demonstracao';                         // qualquer outro valor mostra o pedido de acesso

/*
 * Campos de cada formulário, na ordem da tela.
 * nome do campo => [rótulo, ícone, tipo do input, exemplo, autocomplete, tamanho máximo, ocupa a linha inteira?]
 */
$campos = $demo ? [
    'name'           => ['Nome completo', 'user', 'text', 'Seu nome', 'name', 120, true],
    'email'          => ['E-mail', 'mail', 'email', 'nome@empresa.com', 'email', 190, false],
    'phone'          => ['Telefone', 'phone', 'tel', '(11) 91234-5678', 'tel', 30, false],
    'company_name'   => ['Empresa', 'building', 'text', 'Nome da empresa', 'organization', 120, false],
    'reservoir_name' => ['Represa', 'waves', 'text', 'Nome da represa', 'off', 120, false],
    'city'           => ['Cidade', 'map-pin', 'text', 'Cidade e estado', 'address-level2', 120, true],
] : [
    'name'           => ['Nome completo', 'user', 'text', 'Seu nome', 'name', 120, true],
    'email'          => ['E-mail da empresa', 'mail', 'email', 'nome@empresa.com', 'username', 190, false],
    'registration'   => ['Matrícula', 'id-card', 'text', 'Ex.: HV-1234', 'off', 40, false],
    'company_name'   => ['Empresa', 'building', 'text', 'Onde você trabalha', 'organization', 120, false],
    'reservoir_name' => ['Represa', 'waves', 'text', 'Nome da represa', 'off', 120, false],
    'manager_name'   => ['Gestor direto', 'users', 'text', 'Quem confirma o seu pedido', 'off', 120, true],
];

$textos = $demo ? [
    'title'    => 'Solicitar demonstração',
    'subtitle' => 'Conte sobre a sua represa. Entramos em contato para apresentar o sistema e combinar a instalação.',
    'brand'    => 'Sua represa no Aquapulse',
    'text'     => 'Nível, vazão, pH, chuva e alertas<br>em um só lugar, com os dados<br>vindos dos sensores da barragem.',
    'message'  => ['Mensagem (opcional)', 'Quantas represas, equipamentos que já existem, melhor horário para contato…'],
    'submit'   => 'Enviar pedido de demonstração',
] : [
    'title'    => 'Solicitar acesso',
    'subtitle' => 'Para quem trabalha em uma represa já monitorada. O administrador confere os dados antes de liberar.',
    'brand'    => 'Acesso para a equipe da represa',
    'text'     => 'O administrador confirma seus dados<br>com a empresa e libera somente<br>a represa em que você trabalha.',
    'message'  => ['Por que precisa do acesso?', 'Ex.: opero o turno da noite na barragem'],
    'submit'   => 'Enviar pedido de acesso',
];

/** Um campo de texto com ícone, rótulo e lugar para a mensagem de erro. */
function aq_campo(string $nome, array $c): void
{
    [$rotulo, $icone, $tipo, $exemplo, $auto, $max, $inteiro] = $c;
    ?>
            <div class="field<?php echo $inteiro ? ' request-grid__full' : ''; ?>">
              <label class="field__label" for="<?php aq_out($nome); ?>"><?php aq_out($rotulo); ?></label>
              <div class="field__control">
                <span class="field__icon" aria-hidden="true"><?php aq_the_icon($icone); ?></span>
                <input class="field__input" type="<?php aq_out($tipo); ?>" id="<?php aq_out($nome); ?>" name="<?php aq_out($nome); ?>"
                       placeholder="<?php aq_out($exemplo); ?>" autocomplete="<?php aq_out($auto); ?>" maxlength="<?php echo (int) $max; ?>"
                       required aria-describedby="erro-<?php aq_out($nome); ?>">
              </div>
              <p class="field__error" id="erro-<?php aq_out($nome); ?>" data-error-for="<?php aq_out($nome); ?>" hidden></p>
            </div>
    <?php
}

/** Campo de senha, com o botão de mostrar e ocultar. */
function aq_campo_senha(string $nome, string $rotulo, string $exemplo): void
{
    ?>
            <div class="field">
              <label class="field__label" for="<?php aq_out($nome); ?>"><?php aq_out($rotulo); ?></label>
              <div class="field__control">
                <span class="field__icon" aria-hidden="true"><?php aq_the_icon('lock'); ?></span>
                <input class="field__input field__input--password" type="password" id="<?php aq_out($nome); ?>" name="<?php aq_out($nome); ?>"
                       placeholder="<?php aq_out($exemplo); ?>" autocomplete="new-password" maxlength="72"
                       required aria-describedby="erro-<?php aq_out($nome); ?>">
                <button class="field__toggle" type="button" data-password-toggle aria-controls="<?php aq_out($nome); ?>"
                        aria-pressed="false" aria-label="Mostrar senha">
                  <span data-icon="show"><?php aq_the_icon('eye'); ?></span>
                  <span data-icon="hide" hidden><?php aq_the_icon('eye-off'); ?></span>
                </button>
              </div>
              <p class="field__error" id="erro-<?php aq_out($nome); ?>" data-error-for="<?php aq_out($nome); ?>" hidden></p>
            </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#e9f1fb">
  <meta name="robots" content="noindex, nofollow">
  <title><?php aq_out($textos['title']); ?> — <?php aq_out(AQ_SITE_NAME); ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">

  <link rel="stylesheet" href="<?php aq_out(aq_asset('css/style.css')); ?>?v=<?php aq_out($aq_version); ?>">
  <link rel="stylesheet" href="<?php aq_out(aq_asset('css/login.css')); // mesmo visual do login ?>?v=<?php aq_out($aq_version); ?>">
  <link rel="stylesheet" href="<?php aq_out(aq_asset('css/solicitar.css')); ?>?v=<?php aq_out($aq_version); ?>">
</head>
<body class="login-page">

  <a class="skip-link" href="#form-pedido">Ir para o formulário</a>

  <img class="login-page__deco"
       src="<?php aq_out(aq_asset('images/linhas-decorativas.webp')); ?>"
       width="1100" height="619" alt="" aria-hidden="true" decoding="async">

  <main class="login">
    <div class="login__card">

      <!-- ---------------------------------------------------- painel de marca -->
      <section class="login__brand" aria-labelledby="pedido-marca">
        <img class="login__photo"
             src="<?php aq_out(aq_asset('images/vantagens-represa.webp')); ?>"
             width="1672" height="941"
             alt="Represa em operação, com vertedouros abertos e reservatório cercado por montanhas."
             decoding="async">
        <img class="login__overlay"
             src="<?php aq_out(aq_asset('images/overlay-monitoramento.webp')); ?>"
             width="1100" height="619" alt="" aria-hidden="true" decoding="async">
        <span class="login__wash" aria-hidden="true"></span>

        <div class="login__brand-top">
          <img class="login__logo"
               src="<?php aq_out(aq_asset('images/logo-aquapulse.png')); ?>"
               width="560" height="215"
               alt="<?php aq_out(AQ_SITE_NAME); ?> — <?php aq_out(AQ_SITE_TAGLINE); ?>" decoding="async">
          <h1 class="login__title" id="pedido-marca"><?php aq_out($textos['brand']); ?></h1>
          <p class="login__text"><?php echo $textos['text']; // texto fixo deste arquivo, com quebras de linha ?></p>
        </div>
      </section>

      <!-- --------------------------------------------------- painel do formulário -->
      <section class="login__panel" aria-labelledby="pedido-titulo">

        <div class="login-box" data-view="form">
          <h2 class="login-box__title" id="pedido-titulo"><?php aq_out($textos['title']); ?></h2>
          <p class="login-box__subtitle"><?php aq_out($textos['subtitle']); ?></p>

          <?php /* data-type vai no corpo do pedido; a validação que vale é a do servidor */ ?>
          <form class="login-form" id="form-pedido" novalidate
                data-type="<?php echo $demo ? 'demo' : 'access'; ?>"
                data-api="api/v1/public/request.php">

            <div class="request-grid">
              <?php foreach ($campos as $nome => $campo) { aq_campo($nome, $campo); } ?>

              <div class="field request-grid__full">
                <label class="field__label" for="message"><?php aq_out($textos['message'][0]); ?></label>
                <textarea class="field__input request-textarea" id="message" name="message" rows="3" maxlength="500"
                          placeholder="<?php aq_out($textos['message'][1]); ?>" aria-describedby="erro-message"></textarea>
                <p class="field__error" id="erro-message" data-error-for="message" hidden></p>
              </div>

              <?php if (!$demo) {
                  aq_campo_senha('password', 'Crie uma senha', 'Mín. 8 caracteres');
                  aq_campo_senha('password_confirm', 'Repita a senha', 'A mesma senha');
              } ?>
            </div>

            <?php /* armadilha para robôs: fica fora da tela e da navegação por teclado */ ?>
            <div class="request-trap" aria-hidden="true">
              <label for="website">Não preencha este campo</label>
              <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <p class="login-alert" id="pedido-alerta" role="alert" hidden>
              <span class="login-alert__icon" aria-hidden="true"><?php aq_the_icon('alert-circle'); ?></span>
              <span data-alert-text></span>
            </p>

            <button class="btn btn--primary btn--block login-submit" type="submit" data-submit>
              <span class="login-submit__spinner" aria-hidden="true"></span>
              <span data-submit-label><?php aq_out($textos['submit']); ?></span>
            </button>

            <p class="request-note">Seus dados são usados somente para responder a este pedido.</p>
          </form>

          <p class="request-switch">
            <?php if ($demo): ?>
              Já trabalha em uma represa monitorada? <a href="solicitar.php?tipo=acesso">Solicitar acesso</a>
            <?php else: ?>
              É responsável por uma represa que ainda não usa o Aquapulse? <a href="solicitar.php?tipo=demonstracao">Solicitar demonstração</a>
            <?php endif; ?>
          </p>

          <p class="login-back">
            <?php if ($demo): ?>
              <a href="index.php"><?php aq_the_icon('arrow-left'); ?><span>Voltar para o site</span></a>
            <?php else: ?>
              <a href="login.php"><?php aq_the_icon('arrow-left'); ?><span>Voltar para o login</span></a>
            <?php endif; ?>
          </p>
        </div>

        <!-- pedido enviado -->
        <div class="login-box" data-view="done" tabindex="-1" hidden>
          <span class="login-box__badge" aria-hidden="true"><?php aq_the_icon('check-circle'); ?></span>
          <h2 class="login-box__title">Pedido enviado</h2>
          <p class="login-box__subtitle" data-done-number></p>

          <p class="request-done">
            <?php if ($demo): ?>
              Nossa equipe entra em contato pelo telefone ou pelo e-mail informado para apresentar o sistema.
              Depois disso, cadastramos a sua represa e criamos o seu acesso de gestor.
            <?php else: ?>
              O administrador vai conferir seus dados com a empresa. Quando o acesso for liberado, entre com o seu e-mail e a
              senha que você acabou de criar. Até lá, a tela de login informa se o pedido ainda está em análise.
            <?php endif; ?>
          </p>

          <a class="btn btn--primary btn--block" href="<?php echo $demo ? 'index.php' : 'login.php'; ?>">
            <span><?php echo $demo ? 'Voltar para o site' : 'Ir para o login'; ?></span>
          </a>
        </div>

      </section>
    </div>

    <footer class="login__footer">
      <p class="login__footer-claim">
        <?php aq_the_icon('shield-check'); ?>
        <span>Monitoramento de represas com inteligência e segurança</span>
      </p>
      <p class="login__footer-legal">
        &copy; <?php echo date('Y'); ?> <?php aq_out(AQ_SITE_NAME); ?>. Todos os direitos reservados.
      </p>
    </footer>
  </main>

  <script src="<?php aq_out(aq_asset('js/solicitar.js')); ?>?v=<?php aq_out($aq_version); ?>" defer></script>
</body>
</html>
