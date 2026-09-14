<?php
/**
 * Aquapulse — tela pública de login (FRONT-END).
 *
 * Esta página apenas renderiza a interface. Nenhuma regra de autenticação,
 * sessão ou acesso a usuários acontece aqui: tudo isso vive em backend/ e é
 * consumido por assets/js/login.js através dos endpoints em api/v1/auth/.
 *
 * Fluxo do login:
 *  1. ao abrir, login.js chama GET api/v1/auth/me.php; se já houver sessão, vai ao painel;
 *  2. o usuário preenche e envia o formulário; login.js valida os campos no navegador;
 *  3. login.js envia POST api/v1/auth/login.php com e-mail e senha em JSON;
 *  4. com 200, o servidor já criou a sessão (cookie) e o navegador vai para dashboard/index.php;
 *  5. com erro, a mensagem da API aparece no alerta e/ou embaixo do campo.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';                            // constantes do site (AQ_SITE_NAME...) e funções aq_out() / aq_asset()
require_once __DIR__ . '/includes/icons.php';                             // aq_the_icon(): ícones SVG embutidos

$aq_version = '1.1.0';                                                    // versão manual dos assets desta página (vai em ?v= para renovar o cache)

/** Base relativa da API — funciona na raiz e em subpastas do XAMPP. */
$aq_api_base = 'api/v1/auth';                                             // caminho relativo: não depende do nome da pasta onde o site está instalado
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> <?php /* layout responsivo */ ?>
  <meta name="theme-color" content="#e9f1fb">
  <meta name="robots" content="noindex, nofollow"> <?php /* a tela de login não deve ser indexada por buscadores */ ?>
  <title>Entrar — <?php aq_out(AQ_SITE_NAME); // aq_out escapa e imprime ?></title>
  <meta name="description" content="Acesse a plataforma Aquapulse para acompanhar as informações e os relatórios das suas represas.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">

  <!-- style.css traz os tokens e componentes compartilhados com a landing page. -->
  <link rel="stylesheet" href="<?php aq_out(aq_asset('css/style.css')); ?>?v=<?php aq_out($aq_version); ?>">
  <link rel="stylesheet" href="<?php aq_out(aq_asset('css/login.css')); // estilos exclusivos desta tela ?>?v=<?php aq_out($aq_version); ?>">

  <script>document.documentElement.classList.add('js');</script>
</head>
<body class="login-page">

  <a class="skip-link" href="#login-form">Ir para o formulário de acesso</a> <?php /* link de acessibilidade: com Tab, pula direto ao formulário */ ?>

  <?php /* imagem decorativa de fundo: alt vazio + aria-hidden, porque não transmite informação */ ?>
  <img class="login-page__deco"
       src="<?php aq_out(aq_asset('images/linhas-decorativas.webp')); ?>"
       width="1100" height="619" alt="" aria-hidden="true" decoding="async">

  <main class="login">

    <div class="login__card"> <?php /* cartão com duas colunas: marca (esquerda) e formulário (direita) */ ?>

      <!-- ---------------------------------------------------- painel de marca -->
      <section class="login__brand" aria-labelledby="login-brand-title">

        <?php /* foto principal: fetchpriority="high" pede ao navegador que baixe primeiro (é a maior imagem visível) */ ?>
        <img class="login__photo"
             src="<?php aq_out(aq_asset('images/vantagens-represa.webp')); ?>"
             width="1672" height="941"
             alt="Represa em operação, com vertedouros abertos e reservatório cercado por montanhas."
             fetchpriority="high" decoding="async">
        <img class="login__overlay"
             src="<?php aq_out(aq_asset('images/overlay-monitoramento.webp')); ?>"
             width="1100" height="619" alt="" aria-hidden="true" decoding="async">
        <span class="login__wash" aria-hidden="true"></span> <?php /* camada de cor sobre a foto para o texto branco ter contraste */ ?>

        <div class="login__brand-top">
          <img class="login__logo"
               src="<?php aq_out(aq_asset('images/logo-aquapulse.png')); ?>"
               width="560" height="215"
               alt="<?php aq_out(AQ_SITE_NAME); ?> — <?php aq_out(AQ_SITE_TAGLINE); ?>"
               decoding="async">

          <h1 class="login__title" id="login-brand-title">
            Acesse a <br>plataforma <?php aq_out(AQ_SITE_NAME); ?>
          </h1>

          <p class="login__text">
            Informações precisas e em tempo real<br>
            para decisões seguras e gestão eficiente<br>
            das suas represas.
          </p>
        </div>

        <p class="login__assurance">
          <span class="login__assurance-icon" aria-hidden="true"><?php aq_the_icon('shield-lock'); ?></span>
          <span>
            Dados protegidos.<br>
            Monitoramento inteligente.<br>
            Gestão com confiança.
          </span>
        </p>
      </section>

      <!-- --------------------------------------------------- painel do formulário -->
      <section class="login__panel" aria-labelledby="login-panel-title">

        <div class="login-box" data-view="form"> <?php /* vista 1: formulário (visível por padrão) */ ?>

          <?php /* logo repetido aqui para aparecer no celular, onde o painel de marca fica oculto */ ?>
          <img class="login-box__logo"
               src="<?php aq_out(aq_asset('images/logo-aquapulse.png')); ?>"
               width="560" height="215"
               alt="<?php aq_out(AQ_SITE_NAME); ?>"
               decoding="async">

          <h2 class="login-box__title" id="login-panel-title">Entrar na sua conta</h2>
          <p class="login-box__subtitle">Acesse as informações e relatórios da sua represa.</p>

          <?php /* novalidate: desliga os balões de validação do navegador; login.js valida e mostra mensagens próprias. data-api-base: caminho lido pelo JS */ ?>
          <form class="login-form" id="login-form" novalidate
                data-api-base="<?php aq_out($aq_api_base); ?>">

            <div class="field">
              <label class="field__label" for="email">E-mail</label>
              <div class="field__control">
                <span class="field__icon" aria-hidden="true"><?php aq_the_icon('mail'); ?></span>
                <?php /* autocomplete="username" ajuda gerenciadores de senha; aria-describedby liga o campo à mensagem de erro dele */ ?>
                <input class="field__input" type="email" id="email" name="email"
                       placeholder="seu@email.com"
                       autocomplete="username" inputmode="email"
                       spellcheck="false" required
                       aria-describedby="erro-email">
              </div>
              <p class="field__error" id="erro-email" hidden></p> <?php /* mensagem de erro do e-mail (preenchida pelo JS) */ ?>
            </div>

            <div class="field">
              <label class="field__label" for="password">Senha</label>
              <div class="field__control">
                <span class="field__icon" aria-hidden="true"><?php aq_the_icon('lock'); ?></span>
                <input class="field__input field__input--password" type="password" id="password" name="password"
                       placeholder="Digite sua senha"
                       autocomplete="current-password" required
                       aria-describedby="erro-password">
                <?php /* botão do olho: alterna entre mostrar e ocultar a senha; aria-pressed informa o estado */ ?>
                <button class="field__toggle" type="button"
                        data-password-toggle
                        aria-controls="password"
                        aria-pressed="false"
                        aria-label="Mostrar senha">
                  <span data-icon="show"><?php aq_the_icon('eye'); ?></span>
                  <span data-icon="hide" hidden><?php aq_the_icon('eye-off'); ?></span>
                </button>
              </div>
              <p class="field__error" id="erro-password" hidden></p>
            </div>

            <div class="login-form__row">
              <label class="checkbox">
                <input class="checkbox__input" type="checkbox" id="remember" name="remember" checked> <?php /* "Lembrar de mim" é visual: o valor não é enviado à API */ ?>
                <span class="checkbox__box" aria-hidden="true"><?php aq_the_icon('shield-check'); ?></span>
                <span class="checkbox__label">Lembrar de mim</span>
              </label>

              <button class="login-link" type="button"
                      data-soon-trigger
                      aria-describedby="aviso-senha">Esqueci minha senha</button>
            </div>

            <p class="login-note" id="aviso-senha" role="status" hidden> <?php /* aviso de "recurso futuro", exibido por 5 s ao clicar no link acima */ ?>
              Recuperação de senha será disponibilizada em uma etapa futura.
            </p>

            <p class="login-alert" id="login-alert" role="alert" hidden> <?php /* role="alert": o leitor de tela anuncia o erro assim que aparece */ ?>
              <span class="login-alert__icon" aria-hidden="true"><?php aq_the_icon('alert-circle'); ?></span>
              <span data-alert-text></span>
            </p>

            <button class="btn btn--primary btn--block login-submit" type="submit" data-submit>
              <span class="login-submit__spinner" aria-hidden="true"></span> <?php /* animação de carregamento, visível só com data-loading */ ?>
              <span class="login-submit__icon" aria-hidden="true"><?php aq_the_icon('log-in'); ?></span>
              <span data-submit-label>Entrar</span> <?php /* o JS troca para "Entrando…" durante o envio */ ?>
            </button>

            <p class="visually-hidden" role="status" aria-live="polite" data-status></p> <?php /* região invisível usada para anunciar mensagens a leitores de tela */ ?>
          </form>

          <p class="login-divider" aria-hidden="true"><span>ou</span></p>

          <a class="btn btn--outline btn--block" href="index.php#solicitar-demonstracao"> <?php /* leva ao formulário de demonstração da landing */ ?>
            <?php aq_the_icon('user'); ?>
            <span>Solicitar acesso</span>
          </a>

          <p class="login-back">
            <a href="index.php"><?php aq_the_icon('arrow-left'); ?><span>Voltar para o site</span></a>
          </p>
        </div>

        <!-- estado de autenticação concluída (preenchido pelo JavaScript) -->
        <?php /* vista 2: hoje não é exibida, porque login.js redireciona direto ao painel após o login */ ?>
        <div class="login-box login-box--auth" data-view="authenticated" hidden>
          <span class="login-box__badge" aria-hidden="true"><?php aq_the_icon('check-circle'); ?></span>

          <h2 class="login-box__title">Acesso validado</h2>
          <p class="login-box__subtitle" data-auth-status>
            Sua sessão está ativa. Redirecionando para o painel…
          </p>

          <dl class="session-card"> <?php /* lista de definição: termo (dt) e valor (dd) */ ?>
            <div>
              <dt>Usuário</dt>
              <dd data-session-name>—</dd>
            </div>
            <div>
              <dt>E-mail</dt>
              <dd data-session-email>—</dd>
            </div>
            <div>
              <dt>Perfil</dt>
              <dd data-session-role>—</dd>
            </div>
          </dl>

          <a class="btn btn--primary btn--block" href="dashboard/index.php" data-go-dashboard>
            <?php aq_the_icon('layers-list'); ?>
            <span>Ir para o painel</span>
          </a>

          <button class="btn btn--outline btn--block login-submit" type="button" data-logout> <?php /* chama POST api/v1/auth/logout.php (login.js) */ ?>
            <span class="login-submit__spinner" aria-hidden="true"></span>
            <span class="login-submit__icon" aria-hidden="true"><?php aq_the_icon('log-out'); ?></span>
            <span data-logout-label>Encerrar sessão</span>
          </button>

          <p class="login-back">
            <a href="index.php"><?php aq_the_icon('arrow-left'); ?><span>Voltar para o site</span></a>
          </p>
        </div>

      </section>
    </div>

    <footer class="login__footer">
      <p class="login__footer-claim">
        <?php aq_the_icon('shield-check'); ?>
        <span>Monitoramento de represas com inteligência e segurança</span>
      </p>
      <p class="login__footer-legal">
        &copy; <?php echo date('Y'); // ano atual do servidor ?> <?php aq_out(AQ_SITE_NAME); ?>. Todos os direitos reservados.
      </p>
    </footer>

  </main>

  <script src="<?php aq_out(aq_asset('js/login.js')); ?>?v=<?php aq_out($aq_version); ?>" defer></script> <?php /* defer: o script roda depois que o HTML foi todo lido */ ?>
</body>
</html>
