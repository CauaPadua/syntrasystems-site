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
 *
 * Página: login.php. Endpoints usados: GET me.php, POST login.php, POST logout.php.
 */
(function () {
  'use strict';

  var form = document.getElementById('login-form');
  if (!form) return;                                                  // script carregado fora da tela de login: não faz nada

  var base = form.getAttribute('data-api-base') || 'api/v1/auth';     // caminho da API vindo do atributo do <form>

  // Referências aos elementos da tela (buscadas uma vez só).
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
  var status     = form.querySelector('[data-status]');               // região invisível lida por leitores de tela
  var botaoSair  = document.querySelector('[data-logout]');
  var enviando   = false;                                             // true enquanto a requisição de login está em andamento

  /* ------------------------------------------------------------ utilidades */

  /** Envia uma mensagem para leitores de tela (sem mudar nada visível). */
  function anunciar(texto) {
    if (status) status.textContent = texto;
  }

  /** Mostra o alerta vermelho geral do formulário. */
  function mostrarAlerta(mensagem) {
    if (!alerta || !alertaTexto) return;
    alertaTexto.textContent = mensagem;                               // textContent: a mensagem da API nunca é interpretada como HTML
    alerta.hidden = false;
  }

  function limparAlerta() {
    if (alerta) alerta.hidden = true;
  }

  /**
   * Mostra ou limpa o erro de um campo.
   * Com mensagem: exibe o texto e marca aria-invalid (leitores de tela anunciam "inválido").
   * Sem mensagem: esconde o texto e remove a marcação.
   */
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

  /** Limpa o alerta geral e os erros dos dois campos. */
  function limparErros() {
    limparAlerta();
    marcarErroCampo(campoEmail, erroEmail, '');
    marcarErroCampo(campoSenha, erroSenha, '');
  }

  /**
   * Coloca ou tira um botão do estado "carregando".
   * Enquanto carrega: desabilita o botão (evita duplo clique), mostra o spinner e troca o texto.
   */
  function definirCarregando(elemento, rotulo, carregando, textoCarregando, textoNormal) {
    if (!elemento) return;

    if (carregando) {
      elemento.setAttribute('data-loading', '');                      // o CSS mostra o spinner quando este atributo existe
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
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(valor);              // algo@algo.xx : sem espaços, um @ e um domínio com extensão de 2+ letras
  }

  /** Lê a resposta como JSON sem quebrar quando o corpo não é JSON. */
  function lerJson(resposta) {
    return resposta.text().then(function (texto) {
      try {
        return texto ? JSON.parse(texto) : {};
      } catch (e) {
        return {};                                                    // corpo inválido vira objeto vazio (tratado como erro genérico adiante)
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
    } catch (e) {                                                     // navegador sem URLSearchParams
      return 'dashboard/index.php';
    }

    var alvo = params.get('redirect');
    if (alvo === 'dashboard') return 'dashboard/index.php';
    if (alvo && /^dashboard\/[a-z0-9_/-]+\.php$/i.test(alvo)) return alvo; // só aceita "dashboard/...php" com letras, números, _ / - (bloqueia "http://" e "//")
    return 'dashboard/index.php';                                     // qualquer outro valor é ignorado
  }

  /**
   * Login validado — nada de tela intermediária: segue direto para o painel.
   *
   * O anúncio para leitores de tela usa a região `[data-status]` já existente
   * no formulário (visualmente oculta), então a navegação acontece sem
   * nenhum flash de conteúdo na tela.
   */
  function irParaPainel() {
    anunciar('Acesso validado. Abrindo o painel…');
    window.location.replace(destinoPainel());                         // replace: a tela de login não fica no histórico (o botão Voltar não retorna a ela)
  }

  /** Volta a exibir o formulário limpo (usado após encerrar a sessão). */
  function mostrarFormulario() {
    if (vistaAuth) vistaAuth.hidden = true;
    if (vistaForm) vistaForm.hidden = false;
    form.reset();
    limparErros();
  }
  function mostrarFormulario() {                                      // declaração repetida, idêntica à de cima (a segunda substitui a primeira)
    if (vistaAuth) vistaAuth.hidden = true;
    if (vistaForm) vistaForm.hidden = false;
    form.reset();
    limparErros();
  }

  /* --------------------------------------------- sessão já existente na abertura */

  // Ao abrir a tela, pergunta ao servidor se já existe sessão válida.
  fetch(base + '/me.php', {
    method: 'GET',
    credentials: 'same-origin',                                       // envia o cookie de sessão, se existir
    headers: { 'Accept': 'application/json' }
  })
    .then(function (resposta) {
      if (!resposta.ok) return null;                                  // 401 = sem sessão: segue na tela de login
      return lerJson(resposta);
    })
    .then(function (corpo) {
      if (corpo && corpo.data && corpo.data.user) {                   // já logado: não faz sentido mostrar o formulário
        irParaPainel();
      }
    })
    .catch(function () {
      // Sem sessão ou API indisponível: o formulário normal permanece.
    });

  /* ----------------------------------------------------------- mostrar/esconder */

  var alternarSenha = document.querySelector('[data-password-toggle]');
  if (alternarSenha && campoSenha) {
    alternarSenha.addEventListener('click', function () {
      var visivel = campoSenha.type === 'text';                       // "text" = senha visível no momento
      campoSenha.type = visivel ? 'password' : 'text';                // trocar o type do input mostra/esconde os caracteres

      alternarSenha.setAttribute('aria-pressed', visivel ? 'false' : 'true');
      alternarSenha.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');

      var iconeMostrar = alternarSenha.querySelector('[data-icon="show"]');
      var iconeOcultar = alternarSenha.querySelector('[data-icon="hide"]');
      if (iconeMostrar) iconeMostrar.hidden = !visivel;               // alterna entre o ícone de olho e o de olho riscado
      if (iconeOcultar) iconeOcultar.hidden = visivel;

      campoSenha.focus({ preventScroll: true });                      // devolve o foco ao campo sem rolar a página
    });
  }

  /* ------------------------------------------- recurso ainda não disponível */

  var aviso = document.querySelector('[data-soon-trigger]');          // botão "Esqueci minha senha"
  var avisoTexto = document.getElementById('aviso-senha');
  if (aviso && avisoTexto) {
    var temporizador = null;
    aviso.addEventListener('click', function () {
      avisoTexto.hidden = false;
      window.clearTimeout(temporizador);                              // cliques repetidos reiniciam a contagem
      temporizador = window.setTimeout(function () { avisoTexto.hidden = true; }, 5000); // esconde após 5 segundos
    });
  }

  /* ------------------------------------------------------------------- login */

  /*
   * Envio do formulário.
   * Fluxo: bloqueia duplo envio -> valida no navegador -> POST login.php ->
   * sucesso: vai ao painel | erro: mostra mensagens -> sempre reabilita o botão.
   */
  form.addEventListener('submit', function (evento) {
    evento.preventDefault();                                          // impede o envio tradicional (que recarregaria a página)

    if (enviando) return; // bloqueia envio duplicado

    limparErros();

    var email = (campoEmail.value || '').trim();
    var senha = campoSenha.value || '';                               // senha sem trim: espaços fazem parte dela
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

    if (invalido) {                                                   // não envia nada ao servidor com campos inválidos
      anunciar('Há campos para corrigir no formulário.');
      var primeiro = form.querySelector('[aria-invalid="true"]');
      if (primeiro) primeiro.focus();                                 // leva o foco ao primeiro campo com erro
      return;
    }

    enviando = true;
    definirCarregando(botao, botaoRotulo, true, 'Entrando…', 'Entrar');
    anunciar('Verificando as credenciais.');

    fetch(base + '/login.php', {
      method: 'POST',
      credentials: 'same-origin',                                     // permite ao navegador guardar o cookie de sessão devolvido
      headers: {
        'Content-Type': 'application/json',                           // o endpoint recusa outro formato (400 INVALID_CONTENT_TYPE)
        'Accept': 'application/json'
      },
      // A senha só existe nesta requisição: não é guardada em lugar nenhum.
      body: JSON.stringify({ email: email, password: senha })
    })
      .then(function (resposta) {
        return lerJson(resposta).then(function (corpo) {              // junta status HTTP e corpo em um único objeto
          return { ok: resposta.ok, status: resposta.status, corpo: corpo };
        });
      })
      .then(function (resultado) {
        if (resultado.ok && resultado.corpo.data && resultado.corpo.data.user) { // 200 com usuário: login feito
          campoSenha.value = '';                                      // apaga a senha da memória do campo antes de sair
          irParaPainel();
          return;
        }

        var erro = resultado.corpo.error || {};
        var detalhes = erro.details || {};                            // erros por campo (resposta 422 da API)

        if (detalhes.email) marcarErroCampo(campoEmail, erroEmail, detalhes.email);
        if (detalhes.password) marcarErroCampo(campoSenha, erroSenha, detalhes.password);

        var mensagem = erro.message || 'Não foi possível concluir o acesso. Tente novamente.';
        mostrarAlerta(mensagem);
        anunciar(mensagem);

        if (resultado.status === 401) campoSenha.focus();             // credenciais erradas: foco na senha para tentar de novo
      })
      .catch(function () {                                            // falha de rede (servidor fora do ar, sem internet)
        var mensagem = 'Não foi possível falar com o servidor. Verifique sua conexão e tente novamente.';
        mostrarAlerta(mensagem);
        anunciar(mensagem);
      })
      .then(function () {                                             // funciona como "finally": roda depois de sucesso ou erro
        enviando = false;
        definirCarregando(botao, botaoRotulo, false, 'Entrando…', 'Entrar');
      });
  });

  /* ------------------------------------------------------------------ logout */

  if (botaoSair) {                                                    // botão "Encerrar sessão" da vista autenticada
    var rotuloSair = document.querySelector('[data-logout-label]');
    var saindo = false;                                               // evita cliques repetidos durante a requisição

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
        .then(function () {                                           // "finally": libera o botão
          saindo = false;
          definirCarregando(botaoSair, rotuloSair, false, 'Encerrando…', 'Encerrar sessão');
        });
    });
  }
})();
