/** Aquapulse — Configurações. */
/*
 * Página: dashboard/configuracoes.php | API: GET api/v1/settings.php.
 * Tela de consulta: troca de abas e desenho de empresas, represas, limites e
 * contas de acesso. Nada é gravado aqui.
 */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var Api = window.AqApi;

  var companies = [];           // empresas com a lista de represas de cada uma
  var selectedCompany = null;   // empresa aberta nos cards de detalhe

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
        + '<strong style="flex:1 1 auto">' + S.esc(r.name) + '</strong>'
        + S.badge('Nível: ' + S.esc(r.status.label), r.status.key) + '</div>'
        + '<div class="aq-grid aq-grid--3" style="gap:10px;font-size:.83rem">'
        + '<div><p class="aq-card__sub">Localidade</p><strong>' + S.esc(r.city) + '</strong></div>'
        + '<div><p class="aq-card__sub">Capacidade</p><strong>' + F.int(r.capacity) + ' hm³</strong></div>'
        + '<div><p class="aq-card__sub">Equipamentos online</p><strong style="color:var(--aq-' + (todos ? 'success' : 'warning') + ')">'
        + r.sensors_online + ' de ' + r.sensors_total + '</strong></div>'
        + '</div></div>';
    }).join('') || '<p class="aq-card__sub">Nenhuma represa vinculada a esta empresa.</p>';
  }

  /** Limites em vigor (aba "Limites operacionais"). */
  function renderLimits(limits) {
    document.querySelector('[data-limits]').innerHTML = limits.map(function (l) {
      return '<div class="aq-form-row"><span>' + S.esc(l.label) + '</span><strong>' + S.esc(l.value) + '</strong></div>';
    }).join('');
  }

  /** Contas de acesso (a aba só existe para administradores). */
  function renderUsers(users) {
    var alvo = document.querySelector('[data-users]');
    if (!alvo) return;

    alvo.innerHTML = users.map(function (u) {
      return '<tr><td>' + S.esc(u.name) + '</td>'
        + '<td>' + S.esc(u.email) + '</td>'
        + '<td>' + S.esc(u.role_label) + '</td>'
        + '<td>' + S.badge(S.esc(u.status_label), u.status === 'active' ? 'normal' : 'attention') + '</td>'
        + '<td class="is-nowrap">' + S.esc(u.last_login) + '</td></tr>';
    }).join('');
  }

  /* ---------------------------------------------------------- carregar */

  function load() {
    return Api.settings().then(function (r) {
      companies = r.data.companies;

      // mantém a empresa que estava aberta quando a tela se atualiza
      var anterior = selectedCompany ? selectedCompany.id : null;
      selectedCompany = companies.filter(function (c) { return c.id === anterior; })[0] || companies[0] || null;

      renderCompanies();
      renderCompanyDetail();
      renderLimits(r.data.limits);
      renderUsers(r.data.users);

      S.setUpdated(r.meta);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      S.notify('Não foi possível carregar', err.message, 'error');
    });
  }

  /* ----------------------------------------------------------- eventos */

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

  load();
})();
