/** Aquapulse — Configurações. */
/*
 * Página: dashboard/configuracoes.php
 * API: GET  api/v1/settings.php      empresas, represas, limites e contas
 *      POST api/v1/companies.php     cadastrar e editar empresa
 *      POST api/v1/reservoirs.php    cadastrar e editar represa (inclusive os limites de nível)
 *      POST api/v1/limits.php        mudar os limites de pH e de chuva
 *      POST api/v1/users.php         cadastrar e editar conta
 *
 * Os botões e as janelas de cadastro só existem na página do administrador; a
 * API confere o cargo de novo a cada gravação.
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var Api = window.AqApi;

  var companies = [];           // empresas com a lista de represas de cada uma
  var selectedCompany = null;   // empresa aberta nos cards de detalhe
  var users = [];               // contas de acesso (só administrador)
  var limitValues = {};         // limites de pH e chuva em números, para o formulário
  var canEdit = false;          // informado pela API: pode cadastrar?

  var formCompany = document.getElementById('form-empresa');      // null para quem não é administrador
  var formReservoir = document.getElementById('form-represa');
  var formLimits = document.getElementById('form-limites');
  var formAccount = document.getElementById('form-conta');

  // Dados de um pedido de demonstração, quando o administrador chega pelo botão
  // "Cadastrar empresa e represa deste pedido" (dashboard/solicitacoes.php).
  var pedido = null;
  try {
    pedido = JSON.parse(sessionStorage.getItem('aq.pedido') || 'null');
    sessionStorage.removeItem('aq.pedido');
  } catch (erro) { pedido = null; }

  /* ------------------------------------------------------------ abas */

  var tabs = Array.prototype.slice.call(document.querySelectorAll('[role="tab"]'));

  /** Ativa uma aba: marca aria-selected e mostra só o painel dela. */
  function activate(tab) {
    tabs.forEach(function (t) {
      var on = t === tab;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.setAttribute('tabindex', on ? '0' : '-1');
      var panel = document.getElementById(t.getAttribute('aria-controls'));
      if (panel) panel.hidden = !on;
    });
  }

  tabs.forEach(function (tab, i) {
    tab.addEventListener('click', function () { activate(tab); });

    // navegação por teclado entre abas (padrão WAI-ARIA)
    tab.addEventListener('keydown', function (e) {
      var next = null;
      if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
      if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
      if (e.key === 'Home') next = tabs[0];
      if (e.key === 'End') next = tabs[tabs.length - 1];
      if (next) {
        e.preventDefault();
        activate(next);
        next.focus();
      }
    });
  });

  // um link como configuracoes.php#limites abre direto na aba correspondente
  var pedida = document.getElementById('tab-' + window.location.hash.replace('#', ''));
  if (pedida) activate(pedida);

  /* ------------------------------------------------------- renderização */

  /** Lista de empresas; a selecionada fica destacada. */
  function renderCompanies() {
    document.querySelector('[data-companies]').innerHTML = companies.map(function (c) {
      var on = selectedCompany && c.id === selectedCompany.id;
      return '<button class="aq-card" type="button" data-company="' + S.esc(c.id) + '"'
        + ' style="text-align:left;cursor:pointer;margin-bottom:10px;width:100%;'
        + (on ? 'border-color:var(--aq-primary);background:var(--aq-primary-lighter)' : '') + '">'
        + '<strong style="display:block;font-size:.92rem">' + S.esc(c.name) + '</strong>'
        + '<span style="display:block;font-size:.8rem;color:var(--aq-text-secondary);margin-top:4px">'
        + S.esc(c.code) + ' · ' + c.reservoirs.length + (c.reservoirs.length === 1 ? ' represa' : ' represas') + '</span>'
        + '</button>';
    }).join('') || '<p class="aq-card__sub">Sua conta não está vinculada a nenhuma empresa.</p>';
  }

  /** Dados e represas da empresa selecionada. */
  function renderCompanyDetail() {
    if (!selectedCompany) return;

    S.fill({
      'company.name': selectedCompany.name,
      'company.code': selectedCompany.code,
      'company.manager': selectedCompany.manager,
      'company.status': { html: S.badge(S.esc(selectedCompany.status_label), selectedCompany.status === 'active' ? 'normal' : 'neutral') }
    });

    document.querySelector('[data-company-reservoirs]').innerHTML = selectedCompany.reservoirs.map(function (r) {
      var todos = r.sensors_total > 0 && r.sensors_online === r.sensors_total;
      return '<div style="border:1px solid var(--aq-border);border-radius:11px;padding:14px;margin-bottom:12px">'
        + '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">'
        + '<strong style="flex:1 1 auto">' + S.esc(r.name) + ' <span class="aq-card__sub">' + S.esc(r.code) + '</span></strong>'
        + S.badge(r.status.key === 'neutral' ? S.esc(r.status.label) : 'Nível: ' + S.esc(r.status.label), r.status.key)
        + (canEdit ? '<button class="aq-btn aq-btn--ghost aq-btn--sm" type="button" data-edit-reservoir="' + S.esc(r.id) + '">Editar</button>' : '')
        + '</div>'
        + '<div class="aq-grid aq-grid--3" style="gap:10px;font-size:.83rem">'
        + '<div><p class="aq-card__sub">Localidade</p><strong>' + S.esc(r.city) + '</strong></div>'
        + '<div><p class="aq-card__sub">Capacidade</p><strong>' + F.unit(r.capacity_hm3, 'hm³', 0) + '</strong></div>'
        + '<div><p class="aq-card__sub">Equipamentos online</p><strong style="color:var(--aq-' + (todos ? 'success' : 'warning') + ')">'
        + r.sensors_online + ' de ' + r.sensors_total + '</strong></div>'
        + '</div></div>';
    }).join('') || '<p class="aq-card__sub">Nenhuma represa vinculada a esta empresa.</p>';
  }

  /** Um limite de nível: a porcentagem e, embaixo, a cota de onde ela sai. */
  function limitCell(pct, origem, cota) {
    return '<strong>' + F.num(pct, 1) + '%</strong><span class="aq-card__sub" style="display:block;white-space:nowrap">'
      + origem + ': ' + F.num(cota, 1) + ' m</span>';
  }

  /** Limites em vigor (aba "Limites operacionais"): nível por represa; pH e chuva para todas. */
  function renderLimits(limits) {
    var represas = companies.reduce(function (lista, c) { return lista.concat(c.reservoirs); }, []);

    document.querySelector('[data-level-limits]').innerHTML = represas.map(function (r) {
      return '<tr><td>' + S.esc(r.name) + '</td>'
        + '<td>' + limitCell(r.level_attention, 'cota de alerta', r.cota_alert_m) + '</td>'
        + '<td>' + limitCell(r.level_critical, 'cota de vertimento', r.cota_spill_m) + '</td>'
        + (canEdit ? '<td><button class="aq-btn aq-btn--ghost aq-btn--sm" type="button" data-edit-reservoir="' + S.esc(r.id) + '">Editar</button></td>' : '')
        + '</tr>';
    }).join('') || '<tr><td colspan="4">Nenhuma represa vinculada à sua conta.</td></tr>';

    document.querySelector('[data-limits]').innerHTML = limits.map(function (l) {
      return '<div class="aq-form-row"><span>' + S.esc(l.label) + '</span><strong>' + S.esc(l.value) + '</strong></div>';
    }).join('');
  }

  /** Contas de acesso (a aba só existe para o administrador). */
  function renderUsers() {
    var alvo = document.querySelector('[data-users]');
    if (!alvo) return;

    alvo.innerHTML = users.map(function (u) {
      return '<tr><td>' + S.esc(u.name) + '</td>'
        + '<td>' + S.esc(u.email) + '</td>'
        + '<td>' + S.esc(u.role_label) + '</td>'
        + '<td>' + (u.reservoir_names.length ? S.esc(u.reservoir_names.join(', ')) : '<span class="aq-card__sub">Nenhuma</span>') + '</td>'
        + '<td>' + S.badge(S.esc(u.status_label), u.status === 'active' ? 'normal' : 'attention') + '</td>'
        + '<td class="is-nowrap">' + S.esc(u.last_login) + '</td>'
        + '<td><button class="aq-btn aq-btn--ghost aq-btn--sm" type="button" data-edit-account="' + u.id + '">Editar</button></td></tr>';
    }).join('');
  }

  /* ---------------------------------------------------------- carregar */

  function load() {
    return Api.settings().then(function (r) {
      companies = r.data.companies;
      users = r.data.users;
      limitValues = r.data.limit_values;
      canEdit = r.data.can_edit;

      // listas fixas dos formulários (só na primeira carga)
      if (formAccount && !formAccount.elements.role.options.length) {
        formAccount.elements.role.innerHTML = options(r.data.roles, 'value', 'label');
        formAccount.elements.status.innerHTML = options(r.data.statuses, 'value', 'label');
      }

      // mantém a empresa que estava aberta quando a tela se atualiza
      var anterior = selectedCompany ? selectedCompany.id : null;
      selectedCompany = companies.filter(function (c) { return c.id === anterior; })[0] || companies[0] || null;

      renderCompanies();
      renderCompanyDetail();
      renderLimits(r.data.limits);
      renderUsers();

      S.setUpdated(r.meta);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      S.notify('Não foi possível carregar', err.message, 'error');
    });
  }

  /* ---------------------------------------------------------- cadastros */

  function options(items, value, label) {
    return items.map(function (i) {
      return '<option value="' + S.esc(i[value]) + '">' + S.esc(i[label]) + '</option>';
    }).join('');
  }

  /** Todas as represas, com o nome da empresa, na ordem da tela. */
  function allReservoirs() {
    return companies.reduce(function (lista, c) {
      return lista.concat(c.reservoirs.map(function (r) { return { id: r.id, name: r.name, company: c.name }; }));
    }, []);
  }

  /**
   * Abre uma janela de cadastro, vazia ou preenchida com um registro.
   * @param {string} id     empresa, represa, limites ou conta
   * @param {object} values valores dos campos (chave = nome do campo)
   */
  function openForm(id, title, form, values) {
    form.reset();
    S.showErrors(form, {});

    Object.keys(values).forEach(function (campo) {
      var el = form.elements[campo];
      if (!el || !el.tagName) return;
      // números com vírgula, como o resto da tela
      el.value = typeof values[campo] === 'number' ? String(values[campo]).replace('.', ',') : values[campo];
    });

    var fill = {};
    fill[id + '.title'] = title;
    S.fill(fill);
    S.openModal('modal-' + id);
  }

  /** Envia um formulário e mostra os erros de cada campo, se houver. */
  function submit(id, form, promise, title, text) {
    var botao = form.querySelector('[type="submit"]');
    botao.disabled = true;
    S.showErrors(form, {});

    return promise.then(function () {
      S.closeModal('modal-' + id);
      S.notify(title, text, 'info');
      return load().then(function () { return true; });
    }).catch(function (err) {
      if (err.code === 'VALIDATION_ERROR') {
        S.showErrors(form, err.details);
      } else {
        S.notify('Não foi possível salvar', err.message, 'error');
      }
      return false;
    }).then(function (gravou) {
      botao.disabled = false;
      return gravou;                                              // true quando gravou
    });
  }

  /** Campos do formulário em um objeto (sem botões nem caixas de seleção). */
  function values(form) {
    var dados = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (el.name && el.type !== 'checkbox') dados[el.name] = el.value;
    });
    return dados;
  }

  var editing = { company: null, reservoir: null, account: null };   // id em edição; null = novo cadastro

  /* ------------------------------------------------------------ empresa */

  /** @param {object} [preset] valores iniciais de um cadastro novo */
  function openCompany(c, preset) {
    editing.company = c ? c.id : null;
    openForm('empresa', c ? 'Editar ' + c.name : 'Nova empresa', formCompany, c || preset || { status: 'active' });
  }

  if (formCompany) formCompany.addEventListener('submit', function (e) {
    e.preventDefault();
    var nova = !editing.company;
    var gravada = null;

    submit('empresa', formCompany,
      Api.saveCompany(editing.company ? 'update' : 'create', Object.assign(values(formCompany), { id: editing.company || '' }))
        .then(function (r) {
          gravada = r.data.id;
          selectedCompany = { id: r.data.id };                    // a empresa gravada fica selecionada
        }),
      editing.company ? 'Empresa atualizada' : 'Empresa cadastrada', 'Os dados foram gravados no banco.')
      .then(function (gravou) {
        if (gravou && nova && pedido) openReservoirFromRequest(gravada);   // do pedido: segue para a represa
      });
  });

  /* ------------------------------------------------------------ represa */

  /** @param {object} [preset] valores iniciais de um cadastro novo */
  function openReservoir(r, preset) {
    editing.reservoir = r ? r.id : null;
    formReservoir.elements.company_id.innerHTML = options(companies, 'id', 'name');
    document.querySelector('[data-reservoir-hint]').textContent = r
      ? 'As cotas de alerta e de vertimento são os limites de Atenção e Crítico do nível. Mudar a capacidade ou as cotas vale para as próximas leituras; o histórico já gravado não muda.'
      : 'Depois de salvar: cadastre os sensores em Equipamentos, ligue as contas na aba Usuários e, por fim, feche as janelas do simulador e da estação e abra o iniciar.bat de novo, para lerem os sensores novos.';
    openForm('represa', r ? 'Editar ' + r.name : 'Nova represa', formReservoir,
      r || preset || { company_id: selectedCompany ? selectedCompany.id : '' });
  }

  if (formReservoir) formReservoir.addEventListener('submit', function (e) {
    e.preventDefault();
    var dados = values(formReservoir);
    var nova = !editing.reservoir;

    submit('represa', formReservoir,
      Api.saveReservoir(editing.reservoir ? 'update' : 'create', Object.assign(dados, { id: editing.reservoir || '' }))
        .then(function () { selectedCompany = { id: dados.company_id }; }),
      editing.reservoir ? 'Represa atualizada' : 'Represa cadastrada',
      editing.reservoir ? 'Os dados foram gravados no banco.' : 'Ela aparece como "Sem leituras" até o primeiro equipamento transmitir.')
      .then(function (gravou) {
        if (!(gravou && nova && pedido)) return;

        // do pedido: volta para Solicitações com o pedido aberto, pronto para aprovar
        try { sessionStorage.setItem('aq.abrirPedido', String(pedido.id)); } catch (erro) { /* abre a lista */ }
        S.notify('Represa cadastrada', 'Voltando ao pedido nº ' + pedido.id + ' para aprovar a conta do dono.', 'info');
        window.setTimeout(function () { window.location.href = 'solicitacoes.php'; }, 1500);
      });
  });

  /* ------------------------------------------- cadastro vindo de um pedido */

  /** "Represa Santa Clara" e "santa clara" contam como o mesmo nome. */
  function mesmoNome(a, b) {
    var limpar = function (t) {
      return (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
        .replace(/\b(represa|barragem|usina|reservatorio)\b/g, '').replace(/\s+/g, ' ').trim();
    };
    return limpar(a) !== '' && limpar(a) === limpar(b);
  }

  function openReservoirFromRequest(companyId) {
    openReservoir(null, { company_id: companyId, name: pedido.reservoir, city: pedido.city });
  }

  /** Empresa já cadastrada: vai direto para a represa. Senão, começa pela empresa. */
  function followRequest() {
    if (!pedido || !formCompany) return;

    var empresa = companies.filter(function (c) { return mesmoNome(c.name, pedido.company); })[0];
    if (empresa) {
      selectedCompany = empresa;
      renderCompanies();
      renderCompanyDetail();
      openReservoirFromRequest(empresa.id);
    } else {
      openCompany(null, { name: pedido.company, manager: pedido.manager, status: 'active' });
    }
    S.notify('Cadastro do pedido nº ' + pedido.id, 'Os campos já vêm com os dados que o dono informou. Complete e salve.', 'info');
  }

  /* ------------------------------------------------------------ limites */

  if (formLimits) formLimits.addEventListener('submit', function (e) {
    e.preventDefault();
    submit('limites', formLimits, Api.saveLimits(values(formLimits)),
      'Limites atualizados', 'Valem a partir da próxima leitura.');
  });

  /* -------------------------------------------------------------- conta */

  /** O administrador vê todas as represas: a lista só aparece para os outros cargos. */
  function toggleReservoirs() {
    document.querySelector('[data-account-reservoirs]').hidden = formAccount.elements.role.value === 'admin';
  }

  function openAccount(u) {
    editing.account = u ? u.id : null;

    var marcadas = u ? u.reservoirs : [];
    document.querySelector('[data-reservoir-options]').innerHTML = allReservoirs().map(function (r) {
      return '<label class="aq-checklist__item"><input type="checkbox" name="reservoirs" value="' + S.esc(r.id) + '"'
        + (marcadas.indexOf(r.id) >= 0 ? ' checked' : '') + '><span>' + S.esc(r.name) + ' <small>' + S.esc(r.company) + '</small></span></label>';
    }).join('');

    openForm('conta', u ? 'Editar ' + u.name : 'Nova conta', formAccount,
      u ? { name: u.name, email: u.email, role: u.role, status: u.status } : { role: 'operator', status: 'active' });

    formAccount.elements.password.placeholder = u ? 'Deixe em branco para manter a atual' : 'Pelo menos 8 caracteres';
    formAccount.elements.role.disabled = !!(u && u.is_self);        // o próprio cargo e a própria situação não mudam
    formAccount.elements.status.disabled = !!(u && u.is_self);
    toggleReservoirs();
  }

  if (formAccount) {
    formAccount.elements.role.addEventListener('change', toggleReservoirs);

    formAccount.addEventListener('submit', function (e) {
      e.preventDefault();
      var dados = values(formAccount);
      dados.id = editing.account || '';
      dados.reservoirs = Array.prototype.map.call(formAccount.querySelectorAll('[name="reservoirs"]:checked'), function (c) { return c.value; });

      submit('conta', formAccount, Api.saveAccount(editing.account ? 'update' : 'create', dados),
        editing.account ? 'Conta atualizada' : 'Conta criada',
        editing.account ? 'As mudanças valem a partir da próxima ação da pessoa.' : 'A pessoa já pode entrar com o e-mail e a senha informados.');
    });
  }

  /* ----------------------------------------------------------- eventos */

  if (formCompany) document.addEventListener('click', function (e) {
    if (e.target.closest('[data-new-company]')) { openCompany(null); return; }
    if (e.target.closest('[data-edit-company]') && selectedCompany) { openCompany(selectedCompany); return; }
    if (e.target.closest('[data-new-account]')) { openAccount(null); return; }
    if (e.target.closest('[data-edit-limits]')) { openForm('limites', 'Limites de pH e chuva', formLimits, limitValues); return; }

    if (e.target.closest('[data-new-reservoir]')) {
      if (companies.length) { openReservoir(null); } else { S.notify('Cadastre uma empresa primeiro', 'Toda represa pertence a uma empresa.', 'error'); }
      return;
    }

    var represa = e.target.closest('[data-edit-reservoir]');
    if (represa) {                                                  // da lista da empresa ou da tabela de limites (todas as empresas)
      companies.forEach(function (c) {
        c.reservoirs.forEach(function (r) { if (r.id === represa.getAttribute('data-edit-reservoir')) openReservoir(r); });
      });
      return;
    }

    var conta = e.target.closest('[data-edit-account]');
    if (conta) openAccount(users.filter(function (u) { return String(u.id) === conta.getAttribute('data-edit-account'); })[0]);
  });

  document.querySelector('[data-companies]').addEventListener('click', function (e) {
    var company = e.target.closest('[data-company]');
    if (!company) return;

    var id = company.getAttribute('data-company');
    selectedCompany = companies.filter(function (c) { return c.id === id; })[0] || selectedCompany;
    renderCompanies();
    renderCompanyDetail();
  });

  document.getElementById('busca-empresa').addEventListener('input', function (e) {
    var term = e.target.value.trim().toLowerCase();
    document.querySelectorAll('[data-company]').forEach(function (c) {
      c.style.display = (!term || c.textContent.toLowerCase().indexOf(term) >= 0) ? '' : 'none';
    });
  });

  S.onReload(load);

  load().then(followRequest);
})();
