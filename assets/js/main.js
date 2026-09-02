/**
 * Aquapulse - comportamentos da landing page.
 *
 * JavaScript puro, sem dependencias. Cobre apenas o necessario:
 * menu mobile acessivel, estado do cabecalho na rolagem, rolagem suave com
 * compensacao do cabecalho fixo, avisos dos botoes ainda sem destino real e
 * animacoes discretas de entrada.
 */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* ------------------------------------------------------ menu de navegacao */
  var header = document.querySelector('[data-header]');
  var nav = document.querySelector('[data-nav]');
  var toggle = document.querySelector('[data-nav-toggle]');
  var backdrop = document.querySelector('[data-nav-backdrop]');
  var mobileQuery = window.matchMedia('(max-width: 960px)');

  function isMenuOpen() {
    return toggle && toggle.getAttribute('aria-expanded') === 'true';
  }

  function openMenu() {
    if (!nav || !toggle) return;
    nav.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'Fechar menu de navegação');
    if (backdrop) backdrop.hidden = false;
    document.body.classList.add('nav-open');
  }

  function closeMenu(returnFocus) {
    if (!nav || !toggle) return;
    nav.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Abrir menu de navegação');
    if (backdrop) backdrop.hidden = true;
    document.body.classList.remove('nav-open');
    if (returnFocus) toggle.focus();
  }

  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      if (isMenuOpen()) {
        closeMenu(false);
      } else {
        openMenu();
      }
    });

    if (backdrop) {
      backdrop.addEventListener('click', function () {
        closeMenu(false);
      });
    }

    // Fecha ao acionar um link do menu.
    nav.addEventListener('click', function (event) {
      var link = event.target.closest('a[href^="#"]');
      if (link && mobileQuery.matches) closeMenu(false);
    });

    // Acessibilidade por teclado: Esc fecha e devolve o foco ao botao.
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && isMenuOpen()) closeMenu(true);
    });

    // Mantem o foco dentro do painel enquanto ele estiver aberto.
    nav.addEventListener('keydown', function (event) {
      if (event.key !== 'Tab' || !isMenuOpen()) return;

      var focusables = nav.querySelectorAll('a[href], button:not([disabled])');
      if (!focusables.length) return;

      var first = focusables[0];
      var last = focusables[focusables.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        toggle.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        toggle.focus();
      }
    });

    // Ao voltar para o desktop, restaura o estado normal da navegacao.
    var onBreakpointChange = function (event) {
      if (!event.matches) closeMenu(false);
    };
    if (typeof mobileQuery.addEventListener === 'function') {
      mobileQuery.addEventListener('change', onBreakpointChange);
    } else if (typeof mobileQuery.addListener === 'function') {
      mobileQuery.addListener(onBreakpointChange);
    }
  }

  /* --------------------------------------------- estado do cabecalho na rolagem */
  if (header) {
    var ticking = false;

    var updateHeader = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
      ticking = false;
    };

    window.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(updateHeader);
    }, { passive: true });

    updateHeader();
  }

  /* ------------------------------------------------------------ rolagem suave */
  // A rolagem suave e feita por CSS (scroll-behavior). Aqui apenas garantimos o
  // deslocamento correto do cabecalho fixo e o foco no destino, sem sujar a URL.
  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[href^="#"]');
    if (!link) return;

    var id = link.getAttribute('href');
    if (!id || id === '#') return;

    var target = document.querySelector(id);
    if (!target) return;

    event.preventDefault();

    var headerHeight = header ? header.offsetHeight : 0;
    var top = target.getBoundingClientRect().top + window.scrollY - headerHeight - 16;

    window.scrollTo({
      top: top < 0 ? 0 : top,
      behavior: reduceMotion.matches ? 'auto' : 'smooth'
    });

    // Move o foco para a secao de destino, mantendo a navegacao por teclado util.
    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
  });

  /* --------------------------------------- botoes ainda sem destino definitivo */
  // "Entrar" e "Solicitar demonstracao" ficam preparados visualmente. Nesta etapa
  // eles apenas informam que o recurso ainda nao esta disponivel.
  function bindPlaceholder(triggerSelector, noteId) {
    var trigger = document.querySelector(triggerSelector);
    var note = document.getElementById(noteId);
    if (!trigger || !note) return;

    var timer = null;

    trigger.addEventListener('click', function () {
      note.hidden = false;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        note.hidden = true;
      }, 4000);
    });
  }

  bindPlaceholder('[data-demo-trigger]', 'aviso-demo');

  /* ------------------------------------------- areas com rolagem lateral propria */
  // O quadro do mockup so vira uma regiao focavel quando realmente transborda,
  // permitindo percorre-lo pelo teclado em telas pequenas.
  var scrollers = document.querySelectorAll('[data-scroller]');

  function updateScrollers() {
    scrollers.forEach(function (el) {
      if (el.scrollWidth > el.clientWidth + 1) {
        el.setAttribute('tabindex', '0');
        el.setAttribute('role', 'group');
      } else {
        el.removeAttribute('tabindex');
        el.removeAttribute('role');
        el.scrollLeft = 0;
      }
    });
  }

  if (scrollers.length) {
    updateScrollers();
    window.addEventListener('resize', updateScrollers, { passive: true });
    window.addEventListener('load', updateScrollers);
  }

  /* ------------------------------------------------------ animacoes de entrada */
  var revealables = document.querySelectorAll('.reveal');

  if (!revealables.length) return;

  if (reduceMotion.matches || !('IntersectionObserver' in window)) {
    revealables.forEach(function (el) {
      el.classList.add('is-visible');
    });
    return;
  }

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    });
  }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

  revealables.forEach(function (el) {
    observer.observe(el);
  });
})();
