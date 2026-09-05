/** Aquapulse — Configurações. */
(function () {
  'use strict';

  var S = window.AqShell;
  var F = window.AqFormat;
  var Api = window.AqApi;

  var companies = [];
  var settings = null;
  var selectedCompany = null;

  /*
   * Preferências alteradas nesta tela vivem apenas na sessão do navegador.
   * A gravação real dependerá do banco de dados.
   */
  var DEMO_KEY = 'aq.demo.settings';

  function readLocal() {
    try { return JSON.parse(sessionStorage.getItem(DEMO_KEY) || '{}'); } catch (e) { return {}; }
  }
  function writeLocal(v) {
    try { sessionStorage.setItem(DEMO_KEY, JSON.stringify(v)); } catch (e) { /* segue sem persistir */ }
  }

  /* ------------------------------------------------------------ abas */

  var tabs = Array.prototype.slice.call(document.querySelectorAll('[role="tab"]'));

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

  /* ------------------------------------------------------- renderização */

  function renderCompanies() {
    document.querySelector('[data-companies]').innerHTML = companies.map(function (c) {
      var on = selectedCompany && c.id === selectedCompany.id;
      return '<button class="aq-card" type="button" data-company="' + S.esc(c.id) + '"'
        + ' style="text-align:left;cursor:pointer;margin-bottom:10px;flex-direction:row;align-items:center;gap:12px;'
        + (on ? 'border-color:var(--aq-primary);background:var(--aq-primary-lighter)' : '') + '">'
        + '<span class="aq-list__icon aq-list__icon--info" aria-hidden="true">'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
        + ' stroke-linecap="round" stroke-linejoin="round">'
        + '<path d="M4.5 20.5V5a1.5 1.5 0 0 1 1.5-1.5h7A1.5 1.5 0 0 1 14.5 5v15.5"/>'
        + '<path d="M14.5 9.5h4a1 1 0 0 1 1 1v10"/><path d="M3 20.5h18"/></svg></span>'
        + '<div style="flex:1 1 auto"><strong style="display:block;font-size:.92rem">' + S.esc(c.name) + '</strong>'
        + S.badge(c.status_label, 'normal')
        + '<span style="display:block;font-size:.8rem;color:var(--aq-text-secondary);margin-top:4px">'
        + c.reservoirs.length + (c.reservoirs.length === 1 ? ' represa' : ' represas') + '</span></div>'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
        + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9.5 5.5 6.5 6.5-6.5 6.5"/></svg>'
        + '</button>';
    }).join('');
  }

  function renderCompanyDetail() {
    if (!selectedCompany) return;

    S.fill({
      'company.name': selectedCompany.name,
      'company.code': selectedCompany.code,
      'company.manager': selectedCompany.manager,
      'company.status': { html: S.badge(selectedCompany.status_label, 'normal') }
    });

    document.querySelector('[data-company-reservoirs]').innerHTML = selectedCompany.reservoirs.map(function (r) {
      return '<div style="border:1px solid var(--aq-border);border-radius:11px;padding:14px;margin-bottom:12px">'
        + '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">'
        + '<span class="aq-list__icon aq-list__icon--info" aria-hidden="true">'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
        + ' stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 20.5V7l8.5-3.5L20.5 7v13.5"/>'
        + '<path d="M3.5 11h17"/><path d="M3.5 15.5h17"/></svg></span>'
        + '<strong style="flex:1 1 auto">' + S.esc(r.name) + '</strong>'
        + S.badge(r.status, 'normal') + '</div>'
        + '<div class="aq-grid aq-grid--3" style="gap:10px;font-size:.83rem">'
        + '<div><p class="aq-card__sub">Localidade</p><strong>' + S.esc(r.city) + '</strong></div>'
        + '<div><p class="aq-card__sub">Capacidade</p><strong>' + F.int(r.capacity) + ' hm³</strong></div>'
        + '<div><p class="aq-card__sub">Telemetria</p><strong style="color:var(--aq-success)">' + S.esc(r.telemetry) + '</strong></div>'
        + '</div></div>';
    }).join('') || '<p class="aq-card__sub">Nenhuma represa vinculada a esta empresa.</p>';
  }

  function renderIndicators() {
    var local = readLocal();
    document.querySelector('[data-indicators]').innerHTML = settings.indicators.map(function (i) {
      var on = local['ind.' + i.id] !== undefined ? local['ind.' + i.id] : i.enabled;
      return '<div class="aq-form-row">'
        + '<span id="ind-label-' + S.esc(i.id) + '">' + S.esc(i.label) + '</span>'
        + '<button class="aq-check" type="button" role="checkbox" aria-checked="' + (on ? 'true' : 'false') + '"'
        + ' aria-labelledby="ind-label-' + S.esc(i.id) + '" data-indicator="' + S.esc(i.id) + '">'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"'
        + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-9"/></svg>'
        + '</button></div>';
    }).join('');
  }

  function renderThresholds() {
    var t = settings.thresholds;
    document.querySelector('[data-thresholds]').innerHTML = [
      { icon: 'waves', label: 'Nível de atenção', value: t.level_attention_pct + '%' },
      { icon: 'droplet', label: 'pH mínimo', value: F.num(t.ph_min, 1) },
      { icon: 'droplet', label: 'pH máximo', value: F.num(t.ph_max, 1) },
      { icon: 'cloud-rain', label: 'Precipitação crítica', value: t.rain_critical_mm + ' mm' }
    ].map(function (i) {
      return '<div style="border:1px solid var(--aq-border);border-radius:11px;padding:14px;'
        + 'display:flex;align-items:center;gap:12px">'
        + '<span class="aq-kpi__icon" aria-hidden="true" style="width:38px;height:38px">'
        + '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
        + ' stroke-linecap="round" stroke-linejoin="round">'
        + '<path d="M2 7.5c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/>'
        + '<path d="M2 13c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/></svg></span>'
        + '<div><p class="aq-card__sub">' + S.esc(i.label) + '</p>'
        + '<strong style="font-size:1.3rem">' + S.esc(i.value) + '</strong></div></div>';
    }).join('');

    // aba "Limites e alertas" reaproveita os mesmos valores
    document.querySelector('[data-limits-form]').innerHTML = [
      { label: 'Nível de atenção do reservatório', value: t.level_attention_pct + '%' },
      { label: 'pH mínimo aceitável', value: F.num(t.ph_min, 1) },
      { label: 'pH máximo aceitável', value: F.num(t.ph_max, 1) },
      { label: 'Precipitação crítica em 24h', value: t.rain_critical_mm + ' mm' }
    ].map(function (i) {
      return '<div class="aq-form-row"><span>' + S.esc(i.label) + '</span><strong>' + S.esc(i.value) + '</strong></div>';
    }).join('');
  }

  function renderNotifications() {
    document.querySelector('[data-notifications]').innerHTML = settings.notifications.map(function (n) {
      return '<div class="aq-form-row">'
        + '<div><strong style="display:block">' + S.esc(n.label) + '</strong>'
        + '<span style="font-size:.83rem;color:var(--aq-text-secondary)">' + S.esc(n.target) + '</span></div>'
        + '<button class="aq-switch" type="button" role="switch" aria-checked="' + (n.enabled ? 'true' : 'false') + '"'
        + ' aria-label="Notificações por ' + S.esc(n.label) + '" data-switch="notif-' + S.esc(n.id) + '"></button></div>';
    }).join('');
  }

  function renderUsers() {
    document.querySelector('[data-users]').innerHTML =
      '<tr><td>Usuário de demonstração</td><td>demo@aquapulse.local</td>'
      + '<td>Administrador</td><td>' + S.badge('Ativo', 'normal') + '</td></tr>';
  }

  /* ---------------------------------------------------------- carregar */

  function load() {
    return Api.settings().then(function (r) {
      companies = r.data.companies;
      settings = r.data.settings;
      selectedCompany = companies[0] || null;

      renderCompanies();
      renderCompanyDetail();
      renderIndicators();
      renderThresholds();
      renderNotifications();
      renderUsers();

      // aplica preferências salvas na sessão
      var local = readLocal();
      ['pref-nivel', 'pref-volume', 'pref-vazao', 'pref-refresh'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el && local[id]) el.value = local[id];
      });

      S.setUpdated(r.meta.generated_at, r.meta.updated_label);
    }).catch(function (err) {
      if (Api.isAbort(err)) return;
      S.notify('Não foi possível carregar', err.message, 'error');
    });
  }

  /* ----------------------------------------------------------- eventos */

  document.addEventListener('click', function (e) {
    var company = e.target.closest('[data-company]');
    if (company) {
      var id = company.getAttribute('data-company');
      selectedCompany = companies.filter(function (c) { return c.id === id; })[0] || selectedCompany;
      renderCompanies();
      renderCompanyDetail();
      return;
    }

    var sw = e.target.closest('[data-switch]');
    if (sw) {
      var on = sw.getAttribute('aria-checked') === 'true';
      sw.setAttribute('aria-checked', on ? 'false' : 'true');
      var l = readLocal(); l[sw.getAttribute('data-switch')] = !on; writeLocal(l);
      return;
    }

    var check = e.target.closest('[data-indicator]');
    if (check) {
      var checked = check.getAttribute('aria-checked') === 'true';
      check.setAttribute('aria-checked', checked ? 'false' : 'true');
      var li = readLocal(); li['ind.' + check.getAttribute('data-indicator')] = !checked; writeLocal(li);
      return;
    }

    if (e.target.closest('[data-demo-action]')) {
      S.notify('Modo demonstrativo',
        'Cadastro e edição dependem do banco de dados, que será implementado na próxima etapa.', 'info');
      return;
    }

    if (e.target.closest('[data-save]')) {
      var s = readLocal();
      ['pref-nivel', 'pref-volume', 'pref-vazao', 'pref-refresh'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) s[id] = el.value;
      });
      writeLocal(s);
      S.notify('Alterações salvas',
        'Preferências mantidas apenas nesta sessão — a gravação definitiva depende do banco de dados.', 'info');
      return;
    }

    if (e.target.closest('[data-cancel]')) {
      writeLocal({});
      load();
      S.notify('Alterações descartadas', 'As preferências voltaram aos valores originais.', 'info');
    }
  });

  // filtro de empresas
  var busca = document.getElementById('busca-empresa');
  if (busca) {
    busca.addEventListener('input', function () {
      var term = busca.value.trim().toLowerCase();
      document.querySelectorAll('[data-company]').forEach(function (c) {
        var name = c.textContent.toLowerCase();
        c.style.display = (!term || name.indexOf(term) >= 0) ? '' : 'none';
      });
    });
  }

  S.onReload(load);

  /* ------------------------------------------------------------- início */
  load();
})();
