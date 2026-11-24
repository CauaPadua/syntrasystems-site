/** Aquapulse — Solicitações (só administrador). */
/*
 * Página: dashboard/solicitacoes.php
 * API: GET  api/v1/requests.php                          listar
 *      POST api/v1/requests.php { action: 'approve' }    aprovar acesso e criar a conta
 *      POST api/v1/requests.php { action: 'conclude' }   demonstração atendida
 *      POST api/v1/requests.php { action: 'reject' }     recusar com motivo
 *      POST api/v1/requests.php { action: 'delete' }     apagar um já respondido
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var Api = window.AqApi;

  var selStatus = document.getElementById('filtro-situacao');
  var selType = document.getElementById('filtro-tipo');
  var form = document.getElementById('form-pedido');

  var rows = [];           // pedidos recebidos da API
  var reservoirs = [];     // represas cadastradas (para marcar na aprovação)
  var current = null;      // pedido aberto na janela

  var BADGE = { pending: 'attention', approved: 'normal', rejected: 'neutral' };

  /** "Represa Santa Clara" -> "santa clara": para comparar o que a pessoa digitou com o cadastro. */
  function chave(texto) {
    return (texto || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
      .replace(/\b(represa|barragem|usina|reservatorio)\b/g, '').replace(/\s+/g, ' ').trim();
  }

  /* --------------------------------------------------------- renderização */

  function renderRows() {
    if (!rows.length) {
      S.setState('requests', 'empty');
      return;
    }
    S.setState('requests', 'ready');

    document.querySelector('[data-rows]').innerHTML = rows.map(function (r) {
      return '<tr>'
        + '<td class="is-nowrap"><strong>' + r.id + '</strong></td>'
        + '<td>' + S.esc(r.type_label) + '</td>'
        + '<td>' + S.esc(r.name) + '<br><span class="aq-card__sub">' + S.esc(r.email) + '</span></td>'
        + '<td>' + S.esc(r.company_name) + '<br><span class="aq-card__sub">' + S.esc(r.reservoir_name) + '</span></td>'
        + '<td class="is-nowrap">' + S.esc(r.created_at) + '</td>'
        + '<td>' + S.badge(S.esc(r.status_label), BADGE[r.status] || 'neutral') + '</td>'
        + '<td><button class="aq-btn aq-btn--ghost aq-btn--sm" type="button" data-open="' + r.id + '">'
        + (r.status === 'pending' ? 'Analisar' : 'Ver') + '</button></td>'
        + '</tr>';
    }).join('');
  }

  function linha(rotulo, valor) {
    return valor ? '<div class="aq-form-row"><span>' + rotulo + '</span><strong>' + S.esc(valor) + '</strong></div>' : '';
  }

  /** Abre a janela com os dados do pedido e só os campos que fazem sentido para ele. */
  function openRequest(r) {
    current = r;
    form.reset();
    S.showErrors(form, {});

    S.fill({ 'request.title': 'Pedido nº ' + r.id + ' — ' + r.type_label });

    document.querySelector('[data-request-details]').innerHTML =
      linha('Nome', r.name) + linha('E-mail', r.email) + linha('Telefone', r.phone) + linha('Matrícula', r.registration)
      + linha('Empresa', r.company_name) + linha('Represa', r.reservoir_name) + linha('Cidade', r.city)
      + linha('Gestor direto', r.manager_name) + linha(r.type === 'demo' ? 'Mensagem' : 'Justificativa', r.message)
      + linha('Recebido em', r.created_at)
      + linha('Situação', r.status_label + (r.decided_at ? ' em ' + r.decided_at + (r.decided_by ? ', por ' + r.decided_by : '') : ''))
      + linha(r.status === 'rejected' ? 'Motivo' : 'Resposta', r.decision_note);

    var aviso = document.querySelector('[data-request-warning]');
    aviso.hidden = !(r.status === 'pending' && r.has_account);
    aviso.textContent = r.type === 'access'
      ? 'Já existe uma conta com este e-mail. Ajuste a conta em Configurações › Usuários e recuse este pedido.'
      : 'Já existe uma conta com este e-mail.';

    var estados = {
      pending: r.status === 'pending',
      decided: r.status !== 'pending',
      access: r.status === 'pending' && r.type === 'access',
      demo: r.status === 'pending' && r.type === 'demo'
    };
    form.querySelectorAll('[data-when]').forEach(function (el) {
      el.hidden = !estados[el.getAttribute('data-when')];
    });

    if (estados.access) renderReservoirs(r.reservoir_name);

    S.openModal('modal-pedido');
  }

  /** Lista de represas para marcar; a que combina com o texto digitado já vem marcada. */
  function renderReservoirs(digitado) {
    var alvo = chave(digitado);

    document.querySelector('[data-reservoirs]').innerHTML = reservoirs.map(function (v) {
      var nome = chave(v.name);
      var combina = alvo !== '' && (nome === alvo || alvo.indexOf(nome) >= 0);
      return '<label class="aq-checklist__item"><input type="checkbox" name="reservoirs" value="' + S.esc(v.id) + '"'
        + (combina ? ' checked' : '') + '><span>' + S.esc(v.name) + ' <small>' + S.esc(v.company) + '</small></span></label>';
    }).join('');
  }

  /* ---------------------------------------------------------- carregar */

  /** @param {boolean} silent true nas recargas automáticas: mantém a tabela visível */
  function load(silent) {
    if (silent !== true) S.setState('requests', 'loading');

    return Api.requests({ status: selStatus.value, type: selType.value }).then(function (r) {
      var d = r.data;

      rows = d.requests;
      reservoirs = d.reservoirs;

      S.fill({
        'pending.value': F.int(d.summary.pending), 'pending.foot': 'Total',
        'access.value': F.int(d.summary.access), 'access.foot': 'Esperando resposta',
        'demo.value': F.int(d.summary.demo), 'demo.foot': 'Esperando resposta',
        'decided.value': F.int(d.summary.decided), 'decided.foot': 'Aprovadas, atendidas ou recusadas'
      });

      if (!form.elements.role.options.length) {
        form.elements.role.innerHTML = d.roles.map(function (c) {
          return '<option value="' + S.esc(c.value) + '">' + S.esc(c.label) + '</option>';
        }).join('');
      }

      renderRows();
      updateBadge(d.summary.pending);
      S.setUpdated(r.meta);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      S.setState('requests', 'error', err.message);
    });
  }

  /** O contador do menu vem do PHP; aqui ele acompanha as respostas dadas nesta tela. */
  function updateBadge(pending) {
    var badge = document.querySelector('[data-nav-badge="requests"]');
    if (!badge) return;
    badge.hidden = pending === 0;
    badge.querySelector('[data-nav-count]').textContent = pending;
  }

  /* ----------------------------------------------------------- responder */

  var MENSAGEM = {
    approve: ['Conta criada', 'A pessoa já pode entrar com o e-mail e a senha que escolheu.'],
    conclude: ['Pedido atendido', 'O contato foi registrado.'],
    reject: ['Pedido recusado', 'O motivo foi registrado.'],
    'delete': ['Pedido excluído', 'O registro foi apagado.']
  };

  form.addEventListener('click', function (e) {
    var botao = e.target.closest('[data-action]');
    if (!botao || !current) return;

    var acao = botao.getAttribute('data-action');
    var marcadas = Array.prototype.map.call(form.querySelectorAll('[name="reservoirs"]:checked'), function (c) { return c.value; });

    S.showErrors(form, {});
    botao.disabled = true;

    Api.requestAction(acao, {
      id: current.id,
      role: form.elements.role.value,
      reservoirs: marcadas,
      note: form.elements.note.value
    }).then(function () {
      S.closeModal('modal-pedido');
      S.notify(MENSAGEM[acao][0], MENSAGEM[acao][1], 'info');
      return load(true);
    }).catch(function (err) {
      if (err.code === 'VALIDATION_ERROR') {
        S.showErrors(form, err.details);
      } else {
        S.notify('Não foi possível concluir', err.message, 'error');
      }
    }).then(function () { botao.disabled = false; });
  });

  form.addEventListener('submit', function (e) { e.preventDefault(); });   // Enter em um campo não responde o pedido

  /* ----------------------------------------------------------- eventos */

  document.querySelector('[data-rows]').addEventListener('click', function (e) {
    var abrir = e.target.closest('[data-open]');
    if (!abrir) return;

    var id = Number(abrir.getAttribute('data-open'));
    var pedido = rows.filter(function (r) { return r.id === id; })[0];
    if (pedido) openRequest(pedido);
  });

  selStatus.addEventListener('change', function () { load(); });
  selType.addEventListener('change', function () { load(); });

  S.onRetry('requests', function () { load(); });
  S.onReload(function () { return load(true); });

  load();
})();
