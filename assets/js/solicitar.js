/**
 * Aquapulse — envio do formulário de solicitação (FRONT-END).
 *
 * Página: solicitar.php. Endpoint: POST api/v1/public/request.php.
 * O servidor valida todos os campos; aqui só se evita uma viagem à toa quando
 * um campo obrigatório está vazio. A senha não é guardada em lugar nenhum.
 */
(function () {
  'use strict';

  var form = document.getElementById('form-pedido');
  if (!form) return;

  var alerta = document.getElementById('pedido-alerta');
  var alertaTexto = alerta.querySelector('[data-alert-text]');
  var botao = form.querySelector('[data-submit]');
  var rotulo = form.querySelector('[data-submit-label]');
  var rotuloNormal = rotulo.textContent;
  var enviando = false;

  /** Mostra a mensagem de cada campo e limpa as que não têm mais erro. */
  function mostrarErros(erros) {
    form.querySelectorAll('[data-error-for]').forEach(function (el) {
      var nome = el.getAttribute('data-error-for');
      var campo = form.elements[nome];

      el.textContent = erros[nome] || '';
      el.hidden = !erros[nome];
      if (erros[nome]) campo.setAttribute('aria-invalid', 'true'); else campo.removeAttribute('aria-invalid');
    });

    var primeiro = form.querySelector('[aria-invalid="true"]');
    if (primeiro) primeiro.focus();
  }

  function mostrarAlerta(texto) {
    alertaTexto.textContent = texto;                       // textContent: nada vindo da API vira HTML
    alerta.hidden = !texto;
  }

  function carregando(sim) {
    enviando = sim;
    botao.disabled = sim;
    if (sim) botao.setAttribute('data-loading', ''); else botao.removeAttribute('data-loading');
    rotulo.textContent = sim ? 'Enviando…' : rotuloNormal;
  }

  function mostrarConcluido(numero) {
    var fim = document.querySelector('[data-view="done"]');

    document.querySelector('[data-view="form"]').hidden = true;
    document.querySelector('[data-done-number]').textContent = numero ? 'Pedido nº ' + numero : '';
    fim.hidden = false;
    fim.focus();
    window.scrollTo(0, 0);
  }

  /* ------------------------------------------------- mostrar/ocultar senha */

  document.querySelectorAll('[data-password-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var campo = document.getElementById(b.getAttribute('aria-controls'));
      var visivel = campo.type === 'text';

      campo.type = visivel ? 'password' : 'text';
      b.setAttribute('aria-pressed', visivel ? 'false' : 'true');
      b.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');
      b.querySelector('[data-icon="show"]').hidden = !visivel;
      b.querySelector('[data-icon="hide"]').hidden = visivel;
    });
  });

  /* ------------------------------------------------------------------ envio */

  form.addEventListener('submit', function (evento) {
    evento.preventDefault();
    if (enviando) return;                                  // impede envio duplicado

    mostrarAlerta('');

    var dados = { type: form.getAttribute('data-type') };
    var erros = {};

    Array.prototype.forEach.call(form.elements, function (campo) {
      if (!campo.name) return;
      dados[campo.name] = campo.value;
      if (campo.required && campo.value.trim() === '') erros[campo.name] = 'Preencha este campo.';
    });

    if (dados.password !== undefined && !erros.password_confirm && dados.password !== dados.password_confirm) {
      erros.password_confirm = 'As senhas não são iguais.';
    }

    mostrarErros(erros);
    if (Object.keys(erros).length) return;

    carregando(true);

    fetch(form.getAttribute('data-api'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(dados)
    })
      .then(function (resposta) {
        return resposta.json().catch(function () { return {}; }).then(function (corpo) {
          return { ok: resposta.ok, corpo: corpo };
        });
      })
      .then(function (r) {
        if (r.ok && r.corpo.success) {
          form.reset();                                    // apaga a senha da tela
          mostrarConcluido(r.corpo.data.id);
          return;
        }

        var erro = r.corpo.error || {};
        mostrarErros(erro.details || {});
        mostrarAlerta(erro.message || 'Não foi possível enviar o pedido. Tente novamente.');
      })
      .catch(function () {
        mostrarAlerta('Não foi possível falar com o servidor. Verifique sua conexão e tente novamente.');
      })
      .then(function () { carregando(false); });
  });
})();
