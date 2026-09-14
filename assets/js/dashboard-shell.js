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
 *
 * "Shell" = a moldura fixa da página (sidebar + topbar) gerada por
 * dashboard/includes/page.php. Exposto como window.AqShell; as telas usam
 * principalmente fill(), setState(), badge(), esc(), notify() e onReload().
 */
window.AqShell = (function () {
  'use strict';

  var body = document.body;

  /* ------------------------------------------------- submenu do menu lateral */
  var submenuToggle = document.querySelector('[data-submenu-toggle]');   // botão "Monitoramento" da sidebar
  if (submenuToggle) {
    var submenu = document.getElementById(submenuToggle.getAttribute('aria-controls')); // a <ul> controlada pelo botão (id em aria-controls)

    submenuToggle.addEventListener('click', function () {
      var open = submenuToggle.getAttribute('aria-expanded') === 'true';   // estado atual lido do próprio atributo de acessibilidade
      submenuToggle.setAttribute('aria-expanded', open ? 'false' : 'true'); // inverte o estado
      if (submenu) submenu.classList.toggle('is-open', !open);            // a classe is-open mostra/esconde o submenu no CSS
    });

    // Enquanto uma página interna de Monitoramento estiver ativa, o submenu
    // permanece aberto — o PHP já entrega aria-expanded="true".
  }

  /* --------------------------------------------------- sidebar em telas pequenas */
  var menuToggle = document.querySelector('[data-menu-toggle]');         // botão hambúrguer da topbar
  var sidebar = document.querySelector('[data-sidebar]');
  var backdrop = document.querySelector('[data-backdrop]');             // fundo escuro atrás da gaveta

  /** Fecha a gaveta do menu (celular/tablet) e restaura os atributos de acessibilidade. */
  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('is-open');
    if (menuToggle) {
      menuToggle.setAttribute('aria-expanded', 'false');
      menuToggle.setAttribute('aria-label', 'Abrir menu do sistema');    // o rótulo lido pelo leitor de tela acompanha a ação disponível
    }
    if (backdrop) backdrop.hidden = true;
    body.classList.remove('aq-nav-open');                                // o CSS usa esta classe para travar a rolagem da página
  }

  /** Abre a gaveta do menu. */
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
    menuToggle.addEventListener('click', function () {                   // o mesmo botão abre e fecha
      var open = menuToggle.getAttribute('aria-expanded') === 'true';
      if (open) closeSidebar(); else openSidebar();
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeSidebar);        // clicar fora da gaveta fecha o menu

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {                                            // tecla Esc fecha menus abertos (padrão de acessibilidade)
      closeSidebar();
      closeUserMenu();                                                   // declarada mais abaixo; funciona porque declarações de função são "içadas" (hoisting)
    }
  });

  /* --------------------------------------------------------- menu do usuário */
  var userToggle = document.querySelector('[data-user-toggle]');         // botão com nome e avatar
  var userMenu = document.querySelector('[data-user-menu]');             // caixa com Configurações e Sair

  function closeUserMenu() {
    if (!userMenu || !userToggle) return;
    userMenu.hidden = true;
    userToggle.setAttribute('aria-expanded', 'false');
  }

  if (userToggle && userMenu) {
    userToggle.addEventListener('click', function (e) {
      e.stopPropagation();                                               // impede que o clique chegue ao "clique fora" abaixo e feche o menu na hora
      var open = userToggle.getAttribute('aria-expanded') === 'true';
      userMenu.hidden = open;                                            // se estava aberto, esconde; se fechado, mostra
      userToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
    });

    document.addEventListener('click', function (e) {                    // clique em qualquer lugar da página
      if (!userMenu.contains(e.target) && !userToggle.contains(e.target)) { // ...que não seja no menu nem no botão
        closeUserMenu();
      }
    });
  }

  /* ---------------------------------------------------------------- logout */
  var logoutBtn = document.querySelector('[data-logout]');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function () {
      var apiBase = body.getAttribute('data-api-base') || 'api/v1';
      var loginUrl = apiBase.replace(/api\/v1\/?$/, '') + 'login.php';   // mesmo cálculo do api-client: raiz do site + login.php

      fetch(apiBase + '/auth/logout.php', {                              // pede ao servidor para destruir a sessão
        method: 'POST',
        credentials: 'same-origin',                                      // envia o cookie da sessão que será encerrada
        headers: { Accept: 'application/json' }
      })
        .catch(function () { /* mesmo com falha de rede, devolvemos ao login */ })
        .then(function () { window.location.href = loginUrl; });         // sempre termina na tela de login
    });
  }

  /* ---------------------------------------------- estados dos blocos de dados */

  /**
   * Alterna o estado de um escopo da tela.
   * @param {string} scope
   * @param {'loading'|'ready'|'empty'|'error'} state
   * @param {string} [message] mensagem exibida no estado de erro
   *
   * Os elementos vêm de aq_states() no PHP (data-scope + data-state) e do
   * contêiner data-content do card. "ready" esconde os três avisos e mostra o conteúdo.
   */
  function setState(scope, state, message) {
    var nodes = document.querySelectorAll('[data-scope="' + scope + '"]'); // os três avisos (loading, empty, error) deste bloco
    nodes.forEach(function (n) {
      n.hidden = n.getAttribute('data-state') !== state;                 // mostra só o aviso do estado pedido
    });

    // conteúdo real fica escondido enquanto carrega / falha
    document.querySelectorAll('[data-content="' + scope + '"]').forEach(function (n) {
      n.hidden = state !== 'ready';
    });

    if (state === 'error' && message) {                                  // troca o texto padrão de erro pela mensagem recebida
      document.querySelectorAll('[data-scope="' + scope + '"][data-state="error"] [data-error-message]')
        .forEach(function (n) { n.textContent = message; });             // textContent: a mensagem nunca é interpretada como HTML
    }
  }

  /** Registra o botão "Tentar novamente" de um escopo. */
  function onRetry(scope, handler) {
    document.querySelectorAll('[data-scope="' + scope + '"][data-state="error"] [data-retry]')
      .forEach(function (btn) { btn.addEventListener('click', handler); });
  }

  /* ------------------------------------------------------------------ toast */
  var toast = document.querySelector('[data-toast]');                    // caixa de aviso criada por aq_page_end()
  var toastTimer = null;                                                 // temporizador que esconde o aviso

  /**
   * Mostra um aviso flutuante por 4,5 segundos.
   * @param {string} title título em negrito
   * @param {string} text  texto complementar
   * @param {string} kind  variação visual (ex.: 'error', 'info')
   */
  function notify(title, text, kind) {
    if (!toast) return;

    toast.className = 'aq-toast' + (kind ? ' aq-toast--' + kind : '');
    toast.querySelector('[data-toast-title]').textContent = title;
    toast.querySelector('[data-toast-text]').textContent = text || '';
    toast.hidden = false;

    window.clearTimeout(toastTimer);                                     // um aviso novo reinicia a contagem do anterior
    toastTimer = window.setTimeout(function () { toast.hidden = true; }, 4500);
  }

  /* ------------------------------------------- atualização manual e automática */
  var refreshBtn = document.querySelector('[data-refresh]');             // botão "Atualizado há..." da topbar
  var updatedLabel = document.querySelector('[data-updated-label]');
  var reloadHandler = null;                                              // função de recarga registrada pela tela atual
  var autoTimer = null;                                                  // intervalo da atualização automática

  /** A página informa como recarregar os próprios dados. */
  function onReload(handler) {
    reloadHandler = handler;                                             // só uma função por página: a última registrada vale
  }

  /**
   * Executa a recarga da tela.
   * @param {boolean} manual true quando o usuário clicou (mostra o ícone girando)
   */
  function runReload(manual) {
    if (typeof reloadHandler !== 'function') return;

    if (manual && refreshBtn) {
      refreshBtn.setAttribute('data-loading', '');                       // o CSS anima o ícone enquanto esse atributo existir
    }

    var result = reloadHandler();
    var done = function () {
      if (refreshBtn) refreshBtn.removeAttribute('data-loading');
    };

    if (result && typeof result.then === 'function') {                   // se a tela devolveu uma Promise, espera ela terminar (com sucesso ou erro)
      result.then(done, done);
    } else {
      window.setTimeout(done, 500);                                      // sem Promise: para a animação depois de meio segundo
    }
  }

  if (refreshBtn) {
    refreshBtn.addEventListener('click', function () { runReload(true); });
  }

  /** Mostra "Atualizado há X" a partir do meta.generated_at da API. */
  function setUpdated(iso, label) {
    if (!updatedLabel) return;
    // o relogio do sistema e demonstrativo e fixo: o rotulo vem pronto do servidor
    var rel = label || window.AqFormat.relative(iso);                    // prefere o rótulo do servidor; senão calcula pelo relógio local
    updatedLabel.textContent = 'Atualizado ' + (rel || 'agora');
  }

  /*
   * Atualização automática demonstrativa: 5 minutos, pausada enquanto a aba
   * estiver oculta e retomada ao voltar. Não altera os dados aleatoriamente —
   * apenas relê a mesma fonte determinística.
   */
  var AUTO_INTERVAL = 5 * 60 * 1000;                                     // 5 minutos em milissegundos

  function startAuto() {
    stopAuto();                                                          // garante que nunca existam dois intervalos ao mesmo tempo
    autoTimer = window.setInterval(function () {
      if (!document.hidden) runReload(false);                            // não recarrega se a aba não estiver visível
    }, AUTO_INTERVAL);
  }

  function stopAuto() {
    if (autoTimer) {
      window.clearInterval(autoTimer);
      autoTimer = null;
    }
  }

  document.addEventListener('visibilitychange', function () {            // disparado quando o usuário troca de aba ou minimiza
    if (document.hidden) stopAuto(); else startAuto();                   // economiza requisições com a aba em segundo plano
  });

  startAuto();

  /* ------------------------------------------------------------------ modal */
  /** Mostra o modal pelo id e coloca o foco no primeiro elemento clicável dele. */
  function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.hidden = false;
    var focusable = m.querySelector('button, [href], input, select, textarea');
    if (focusable) focusable.focus();                                    // quem usa teclado já começa dentro da janela
  }

  function closeModal(id) {
    var m = document.getElementById(id);
    if (m) m.hidden = true;
  }

  // Delegação de eventos: um único ouvinte no documento atende todos os botões
  // de abrir/fechar modal, inclusive os que forem criados depois pelo JS.
  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal-open]');                  // closest sobe pelos pais: funciona clicando no ícone dentro do botão
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
   *
   * Valor comum vira texto (seguro); valor no formato { html: '...' } é inserido
   * como HTML — usado para badges e ícones montados pelo próprio JS.
   */
  function fill(map) {
    Object.keys(map).forEach(function (key) {                            // percorre cada chave informada
      document.querySelectorAll('[data-field="' + key + '"]').forEach(function (el) { // pode haver mais de um elemento com a mesma chave
        var v = map[key];
        if (v && typeof v === 'object' && v.html !== undefined) {
          el.innerHTML = v.html;
        } else {
          el.textContent = v === null || v === undefined ? '—' : String(v); // ausente vira "—"
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
    var circumference = 119.4;                                           // 2 × π × raio 19 (mesmo valor do SVG em aq_kpi)
    if (circle) {
      circle.style.strokeDashoffset = String(circumference * (1 - Math.min(100, pct) / 100)); // quanto menor o offset, maior o arco pintado; limita em 100%
    }
    if (text) text.textContent = Math.round(pct) + '%';
  }

  /** Badge HTML reutilizável (status sempre com texto, nunca só cor). */
  function badge(label, status) {
    var icons = {                                                        // mesmos ícones de aq_badge() no PHP
      normal: '<path d="M20.5 11.3V12a8.5 8.5 0 1 1-5-7.77"/><path d="m8.6 11.6 3 3 8.9-9"/>',
      attention: '<path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
      critical: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.8v4.7"/><path d="M12 16.2h.01"/>',
      info: '<circle cx="12" cy="12" r="8.5"/><path d="M12 11.5v5"/><path d="M12 7.8h.01"/>'
    };
    var svg = icons[status]
      ? '<svg class="aq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"'
        + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + icons[status] + '</svg>'
      : '';                                                              // status sem ícone (ex.: neutral) vira badge só com texto
    return '<span class="aq-badge aq-badge--' + status + '">' + svg + '<span>' + label + '</span></span>'; // atenção: label entra sem escape; quem chama deve passar texto confiável
  }

  /** Escapa texto que será inserido como HTML. */
  function esc(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') // & primeiro, senão as entidades geradas depois seriam escapadas de novo
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');                   // aspas também, para valores usados dentro de atributos
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

/* ---------------------------------------------------------------------------
   Encerramento da tela

   Ao sair da página, as instâncias do Chart.js, os observadores de tamanho e
   as requisições em voo são descartados. Sem isso, uma resposta que chegava
   depois da navegação tentava desenhar em um canvas que já não existia mais.
   `pagehide` cobre também o cache de retorno (voltar/avançar) do navegador.
   --------------------------------------------------------------------------- */
window.addEventListener('pagehide', function () {
  if (window.AqApi && window.AqApi.abortAll) window.AqApi.abortAll();          // cancela requisições pendentes
  if (window.AqCharts && window.AqCharts.destroyAll) window.AqCharts.destroyAll(); // libera os gráficos
});
