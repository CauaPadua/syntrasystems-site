/**
 * Aquapulse — comportamentos do shell do sistema interno.
 *
 * Cobre o que é comum a todas as telas:
 *   - submenu de Monitoramento (mouse, teclado e toque);
 *   - sidebar recolhível em tablet/celular;
 *   - menu do usuário e logout;
 *   - estados de carregando / vazio / erro;
 *   - atualização manual e automática (pausada com a aba oculta);
 *   - avisos (toast) e modais de ações demonstrativas.
 */
window.AqShell = (function () {
  'use strict';

  var body = document.body;

  /* ------------------------------------------------- submenu do menu lateral */
  var submenuToggle = document.querySelector('[data-submenu-toggle]');
  if (submenuToggle) {
    var submenu = document.getElementById(submenuToggle.getAttribute('aria-controls'));

    submenuToggle.addEventListener('click', function () {
      var open = submenuToggle.getAttribute('aria-expanded') === 'true';
      submenuToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
      if (submenu) submenu.classList.toggle('is-open', !open);
    });

    // Enquanto uma página interna de Monitoramento estiver ativa, o submenu
    // permanece aberto — o PHP já entrega aria-expanded="true".
  }

  /* --------------------------------------------------- sidebar em telas pequenas */
  var menuToggle = document.querySelector('[data-menu-toggle]');
  var sidebar = document.querySelector('[data-sidebar]');
  var backdrop = document.querySelector('[data-backdrop]');

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('is-open');
    if (menuToggle) {
      menuToggle.setAttribute('aria-expanded', 'false');
      menuToggle.setAttribute('aria-label', 'Abrir menu do sistema');
    }
    if (backdrop) backdrop.hidden = true;
    body.classList.remove('aq-nav-open');
  }

  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('is-open');
    if (menuToggle) {
      menuToggle.setAttribute('aria-expanded', 'true');
      menuToggle.setAttribute('aria-label', 'Fechar menu do sistema');
    }
    if (backdrop) backdrop.hidden = false;
    body.classList.add('aq-nav-open');
  }

  if (menuToggle) {
    menuToggle.addEventListener('click', function () {
      var open = menuToggle.getAttribute('aria-expanded') === 'true';
      if (open) closeSidebar(); else openSidebar();
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeSidebar();
      closeUserMenu();
    }
  });

  /* --------------------------------------------------------- menu do usuário */
  var userToggle = document.querySelector('[data-user-toggle]');
  var userMenu = document.querySelector('[data-user-menu]');

  function closeUserMenu() {
    if (!userMenu || !userToggle) return;
    userMenu.hidden = true;
    userToggle.setAttribute('aria-expanded', 'false');
  }

  if (userToggle && userMenu) {
    userToggle.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = userToggle.getAttribute('aria-expanded') === 'true';
      userMenu.hidden = open;
      userToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
    });

    document.addEventListener('click', function (e) {
      if (!userMenu.contains(e.target) && !userToggle.contains(e.target)) {
        closeUserMenu();
      }
    });
  }

  /* ---------------------------------------------------------------- logout */
  var logoutBtn = document.querySelector('[data-logout]');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function () {
      var apiBase = body.getAttribute('data-api-base') || 'api/v1';
      var loginUrl = apiBase.replace(/api\/v1\/?$/, '') + 'login.php';

      fetch(apiBase + '/auth/logout.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      })
        .catch(function () { /* mesmo com falha de rede, devolvemos ao login */ })
        .then(function () { window.location.href = loginUrl; });
    });
  }

  /* ---------------------------------------------- estados dos blocos de dados */

  /**
   * Alterna o estado de um escopo da tela.
   * @param {string} scope
   * @param {'loading'|'ready'|'empty'|'error'} state
   * @param {string} [message] mensagem exibida no estado de erro
   */
  function setState(scope, state, message) {
    var nodes = document.querySelectorAll('[data-scope="' + scope + '"]');
    nodes.forEach(function (n) {
      n.hidden = n.getAttribute('data-state') !== state;
    });

    // conteúdo real fica escondido enquanto carrega / falha
    document.querySelectorAll('[data-content="' + scope + '"]').forEach(function (n) {
      n.hidden = state !== 'ready';
    });

    if (state === 'error' && message) {
      document.querySelectorAll('[data-scope="' + scope + '"][data-state="error"] [data-error-message]')
        .forEach(function (n) { n.textContent = message; });
    }
  }

  /** Registra o botão "Tentar novamente" de um escopo. */
  function onRetry(scope, handler) {
    document.querySelectorAll('[data-scope="' + scope + '"][data-state="error"] [data-retry]')
      .forEach(function (btn) { btn.addEventListener('click', handler); });
  }

  /* ------------------------------------------------------------------ toast */
  var toast = document.querySelector('[data-toast]');
  var toastTimer = null;

  function notify(title, text, kind) {
    if (!toast) return;

    toast.className = 'aq-toast' + (kind ? ' aq-toast--' + kind : '');
    toast.querySelector('[data-toast-title]').textContent = title;
    toast.querySelector('[data-toast-text]').textContent = text || '';
    toast.hidden = false;

    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () { toast.hidden = true; }, 4500);
  }

  /* ------------------------------------------- atualização manual e automática */
  var refreshBtn = document.querySelector('[data-refresh]');
  var updatedLabel = document.querySelector('[data-updated-label]');
  var reloadHandler = null;
  var autoTimer = null;

  /** A página informa como recarregar os próprios dados. */
  function onReload(handler) {
    reloadHandler = handler;
  }

  function runReload(manual) {
    if (typeof reloadHandler !== 'function') return;

    if (manual && refreshBtn) {
      refreshBtn.setAttribute('data-loading', '');
    }

    var result = reloadHandler();
    var done = function () {
      if (refreshBtn) refreshBtn.removeAttribute('data-loading');
    };

    if (result && typeof result.then === 'function') {
      result.then(done, done);
    } else {
      window.setTimeout(done, 500);
    }
  }

  if (refreshBtn) {
    refreshBtn.addEventListener('click', function () { runReload(true); });
  }

  /** Mostra "Atualizado há X" a partir do meta.generated_at da API. */
  function setUpdated(iso, label) {
    if (!updatedLabel) return;
    // o relogio do sistema e demonstrativo e fixo: o rotulo vem pronto do servidor
    var rel = label || window.AqFormat.relative(iso);
    updatedLabel.textContent = 'Atualizado ' + (rel || 'agora');
  }

  /*
   * Atualização automática demonstrativa: 5 minutos, pausada enquanto a aba
   * estiver oculta e retomada ao voltar. Não altera os dados aleatoriamente —
   * apenas relê a mesma fonte determinística.
   */
  var AUTO_INTERVAL = 5 * 60 * 1000;

  function startAuto() {
    stopAuto();
    autoTimer = window.setInterval(function () {
      if (!document.hidden) runReload(false);
    }, AUTO_INTERVAL);
  }

  function stopAuto() {
    if (autoTimer) {
      window.clearInterval(autoTimer);
      autoTimer = null;
    }
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stopAuto(); else startAuto();
  });

  startAuto();

  /* ------------------------------------------------------------------ modal */
  function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.hidden = false;
    var focusable = m.querySelector('button, [href], input, select, textarea');
    if (focusable) focusable.focus();
  }

  function closeModal(id) {
    var m = document.getElementById(id);
    if (m) m.hidden = true;
  }

  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal-open]');
    if (opener) {
      openModal(opener.getAttribute('data-modal-open'));
      return;
    }
    var closer = e.target.closest('[data-modal-close]');
    if (closer) {
      closeModal(closer.getAttribute('data-modal-close'));
    }
  });

  /* ---------------------------------------------- preenchimento de campos */

  /**
   * Preenche elementos [data-field="chave"] com os valores de um objeto plano.
   * Ex.: fill({ 'level.value': '82,4' })
   */
  function fill(map) {
    Object.keys(map).forEach(function (key) {
      document.querySelectorAll('[data-field="' + key + '"]').forEach(function (el) {
        var v = map[key];
        if (v && typeof v === 'object' && v.html !== undefined) {
          el.innerHTML = v.html;
        } else {
          el.textContent = v === null || v === undefined ? '—' : String(v);
        }
      });
    });
  }

  /** Atualiza um anel de progresso de KPI. */
  function setRing(id, pct) {
    var ring = document.querySelector('[data-ring="' + id + '"]');
    if (!ring) return;
    var circle = ring.querySelector('.aq-ring__fill');
    var text = ring.querySelector('.aq-ring__text');
    var circumference = 119.4;
    if (circle) {
      circle.style.strokeDashoffset = String(circumference * (1 - Math.min(100, pct) / 100));
    }
    if (text) text.textContent = Math.round(pct) + '%';
  }

  /** Badge HTML reutilizável (status sempre com texto, nunca só cor). */
  function badge(label, status) {
    var icons = {
      normal: '<path d="M20.5 11.3V12a8.5 8.5 0 1 1-5-7.77"/><path d="m8.6 11.6 3 3 8.9-9"/>',
      attention: '<path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
      critical: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.8v4.7"/><path d="M12 16.2h.01"/>',
      info: '<circle cx="12" cy="12" r="8.5"/><path d="M12 11.5v5"/><path d="M12 7.8h.01"/>'
    };
    var svg = icons[status]
      ? '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"'
        + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + icons[status] + '</svg>'
      : '';
    return '<span class="aq-badge aq-badge--' + status + '">' + svg + '<span>' + label + '</span></span>';
  }

  /** Escapa texto que será inserido como HTML. */
  function esc(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  return {
    setState: setState,
    onRetry: onRetry,
    notify: notify,
    onReload: onReload,
    reload: function () { runReload(true); },
    setUpdated: setUpdated,
    openModal: openModal,
    closeModal: closeModal,
    fill: fill,
    setRing: setRing,
    badge: badge,
    esc: esc,
    closeSidebar: closeSidebar
  };
})();
