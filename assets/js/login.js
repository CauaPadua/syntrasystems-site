/**
 * Aquapulse — integração da tela de login com a API de autenticação (FRONT-END).
 *
 * Este arquivo só fala com os endpoints em api/v1/auth/. Ele não conhece
 * usuários, senhas nem sessão: quem decide é o back-end.
 *
 * Regras seguidas aqui:
 *  - a senha nunca é armazenada, registrada em log ou reaproveitada;
 *  - nada de sessão vai para localStorage/sessionStorage (o cookie é HttpOnly);
 *  - envio duplicado é bloqueado enquanto houver requisição em andamento.
 */
(function () {
  'use strict';

  var form = document.getElementById('login-form');
  if (!form) return;

  var base = form.getAttribute('data-api-base') || 'api/v1/auth';

  var vistaForm  = document.querySelector('[data-view="form"]');
  var vistaAuth  = document.querySelector('[data-view="authenticated"]');
  var campoEmail = document.getElementById('email');
  var campoSenha = document.getElementById('password');
  var erroEmail  = document.getElementById('erro-email');
  var erroSenha  = document.getElementById('erro-password');
  var alerta     = document.getElementById('login-alert');
  var alertaTexto = alerta ? alerta.querySelector('[data-alert-text]') : null;
  var botao      = form.querySelector('[data-submit]');
  var botaoRotulo = form.querySelector('[data-submit-label]');
  var status     = form.querySelector('[data-status]');
  var botaoSair  = document.querySelector('[data-logout]');
  var enviando   = false;

  /* ------------------------------------------------------------ utilidades */

  function anunciar(texto) {
    if (status) status.textContent = texto;
  }

  function mostrarAlerta(mensagem) {
    if (!alerta || !alertaTexto) return;
    alertaTexto.textContent = mensagem;
    alerta.hidden = false;
  }

  function limparAlerta() {
    if (alerta) alerta.hidden = true;
  }

  function marcarErroCampo(campo, elementoErro, mensagem) {
    if (!campo || !elementoErro) return;

    if (mensagem) {
      elementoErro.textContent = mensagem;
      elementoErro.hidden = false;
      campo.setAttribute('aria-invalid', 'true');
    } else {
      elementoErro.textContent = '';
      elementoErro.hidden = true;
      campo.removeAttribute('aria-invalid');
    }
  }

  function limparErros() {
    limparAlerta();
    marcarErroCampo(campoEmail, erroEmail, '');
    marcarErroCampo(campoSenha, erroSenha, '');
  }

  function definirCarregando(elemento, rotulo, carregando, textoCarregando, textoNormal) {
    if (!elemento) return;

    if (carregando) {
      elemento.setAttribute('data-loading', '');
      elemento.disabled = true;
      elemento.setAttribute('aria-busy', 'true');
      if (rotulo) rotulo.textContent = textoCarregando;
    } else {
      elemento.removeAttribute('data-loading');
      elemento.disabled = false;
      elemento.removeAttribute('aria-busy');
      if (rotulo) rotulo.textContent = textoNormal;
    }
  }

  /** Validação de e-mail suficiente para o formulário; o servidor valida de novo. */
  function emailPareceValido(valor) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(valor);
  }

  /** Lê a resposta como JSON sem quebrar quando o corpo não é JSON. */
  function lerJson(resposta) {
    return resposta.text().then(function (texto) {
      try {
        return texto ? JSON.parse(texto) : {};
      } catch (e) {
        return {};
      }
    });
  }

  /* ------------------------------------------------- alternância entre as vistas */

  /**
   * Destino após a autenticação.
   *
   * O único valor que o Guard do dashboard produz hoje é `?redirect=dashboard`
   * (ver backend/src/Support/Guard.php), mas também aceitamos um caminho
   * relativo dentro de `dashboard/` para permitir voltar à página exata que
   * pediu login. Nunca aceitamos uma URL absoluta ou de outro domínio — isso
   * seria um redirecionamento aberto.
   */
  function destinoPainel() {
    var params;
    try {
      params = new URLSearchParams(window.location.search);
    } catch (e) {
      return 'dashboard/index.php';
    }

    var alvo = params.get('redirect');
    if (alvo === 'dashboard') return 'dashboard/index.php';
    if (alvo && /^dashboard\/[a-z0-9_/-]+\.php$/i.test(alvo)) return alvo;
    return 'dashboard/index.php';
  }

  function mostrarAutenticado(user) {
    var nome = document.querySelector('[data-session-name]');
    var email = document.querySelector('[data-session-email]');
    var perfil = document.querySelector('[data-session-role]');
    var linkPainel = document.querySelector('[data-go-dashboard]');

    if (nome) nome.textContent = user.name || '—';
    if (email) email.textContent = user.email || '—';
    if (perfil) perfil.textContent = user.role || '—';

    var destino = destinoPainel();
    if (linkPainel) linkPainel.setAttribute('href', destino);

    if (vistaForm) vistaForm.hidden = true;
    if (vistaAuth) {
      vistaAuth.hidden = false;
      vistaAuth.setAttribute('tabindex', '-1');
      vistaAuth.focus({ preventScroll: true });
    }

    // O painel (etapa 3) já existe: a sessão validada segue direto para lá.
    // O cartão fica visível por um instante (para o anúncio de acessibilidade
    // e como confirmação visual) e também serve de saída manual — via
    // data-go-dashboard — caso o redirecionamento automático seja bloqueado.
    window.setTimeout(function () {
      window.location.href = destino;
    }, 500);
  }

  function mostrarFormulario() {
    if (vistaAuth) vistaAuth.hidden = true;
    if (vistaForm) vistaForm.hidden = false;
    form.reset();
    limparErros();
  }

  /* --------------------------------------------- sessão já existente na abertura */

  fetch(base + '/me.php', {
    method: 'GET',
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  })
    .then(function (resposta) {
      if (!resposta.ok) return null;
      return lerJson(resposta);
    })
    .then(function (corpo) {
      if (corpo && corpo.data && corpo.data.user) {
        mostrarAutenticado(corpo.data.user);
      }
    })
    .catch(function () {
      // Sem sessão ou API indisponível: o formulário normal permanece.
    });

  /* ----------------------------------------------------------- mostrar/esconder */

  var alternarSenha = document.querySelector('[data-password-toggle]');
  if (alternarSenha && campoSenha) {
    alternarSenha.addEventListener('click', function () {
      var visivel = campoSenha.type === 'text';
      campoSenha.type = visivel ? 'password' : 'text';

      alternarSenha.setAttribute('aria-pressed', visivel ? 'false' : 'true');
      alternarSenha.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');

      var iconeMostrar = alternarSenha.querySelector('[data-icon="show"]');
      var iconeOcultar = alternarSenha.querySelector('[data-icon="hide"]');
      if (iconeMostrar) iconeMostrar.hidden = !visivel;
      if (iconeOcultar) iconeOcultar.hidden = visivel;

      campoSenha.focus({ preventScroll: true });
    });
  }

  /* ------------------------------------------- recurso ainda não disponível */

  var aviso = document.querySelector('[data-soon-trigger]');
  var avisoTexto = document.getElementById('aviso-senha');
  if (aviso && avisoTexto) {
    var temporizador = null;
    aviso.addEventListener('click', function () {
      avisoTexto.hidden = false;
      window.clearTimeout(temporizador);
      temporizador = window.setTimeout(function () { avisoTexto.hidden = true; }, 5000);
    });
  }

  /* ------------------------------------------------------------------- login */

  form.addEventListener('submit', function (evento) {
    evento.preventDefault();

    if (enviando) return; // bloqueia envio duplicado

    limparErros();

    var email = (campoEmail.value || '').trim();
    var senha = campoSenha.value || '';
    var invalido = false;

    if (email === '') {
      marcarErroCampo(campoEmail, erroEmail, 'Informe o e-mail.');
      invalido = true;
    } else if (!emailPareceValido(email)) {
      marcarErroCampo(campoEmail, erroEmail, 'Informe um e-mail válido.');
      invalido = true;
    }

    if (senha === '') {
      marcarErroCampo(campoSenha, erroSenha, 'Informe a senha.');
      invalido = true;
    }

    if (invalido) {
      anunciar('Há campos para corrigir no formulário.');
      var primeiro = form.querySelector('[aria-invalid="true"]');
      if (primeiro) primeiro.focus();
      return;
    }

    enviando = true;
    definirCarregando(botao, botaoRotulo, true, 'Entrando…', 'Entrar');
    anunciar('Verificando as credenciais.');

    fetch(base + '/login.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      // A senha só existe nesta requisição: não é guardada em lugar nenhum.
      body: JSON.stringify({ email: email, password: senha })
    })
      .then(function (resposta) {
        return lerJson(resposta).then(function (corpo) {
          return { ok: resposta.ok, status: resposta.status, corpo: corpo };
        });
      })
      .then(function (resultado) {
        if (resultado.ok && resultado.corpo.data && resultado.corpo.data.user) {
          campoSenha.value = '';
          anunciar('Acesso validado.');
          mostrarAutenticado(resultado.corpo.data.user);
          return;
        }

        var erro = resultado.corpo.error || {};
        var detalhes = erro.details || {};

        if (detalhes.email) marcarErroCampo(campoEmail, erroEmail, detalhes.email);
        if (detalhes.password) marcarErroCampo(campoSenha, erroSenha, detalhes.password);

        var mensagem = erro.message || 'Não foi possível concluir o acesso. Tente novamente.';
        mostrarAlerta(mensagem);
        anunciar(mensagem);

        if (resultado.status === 401) campoSenha.focus();
      })
      .catch(function () {
        var mensagem = 'Não foi possível falar com o servidor. Verifique sua conexão e tente novamente.';
        mostrarAlerta(mensagem);
        anunciar(mensagem);
      })
      .then(function () {
        enviando = false;
        definirCarregando(botao, botaoRotulo, false, 'Entrando…', 'Entrar');
      });
  });

  /* ------------------------------------------------------------------ logout */

  if (botaoSair) {
    var rotuloSair = document.querySelector('[data-logout-label]');
    var saindo = false;

    botaoSair.addEventListener('click', function () {
      if (saindo) return;

      saindo = true;
      definirCarregando(botaoSair, rotuloSair, true, 'Encerrando…', 'Encerrar sessão');

      fetch(base + '/logout.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function () {
          mostrarFormulario();
          anunciar('Sessão encerrada.');
          if (campoEmail) campoEmail.focus();
        })
        .catch(function () {
          mostrarAlerta('Não foi possível encerrar a sessão. Tente novamente.');
        })
        .then(function () {
          saindo = false;
          definirCarregando(botaoSair, rotuloSair, false, 'Encerrando…', 'Encerrar sessão');
        });
    });
  }
})();
