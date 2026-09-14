/**
 * Aquapulse - comportamentos da landing page.
 *
 * JavaScript puro, sem dependencias. Cobre apenas o necessario:
 * menu mobile acessivel, estado do cabecalho na rolagem, rolagem suave com
 * compensacao do cabecalho fixo, avisos dos botoes ainda sem destino real e
 * animacoes discretas de entrada.
 *
 * Carregado por index.php. Os elementos são encontrados pelos atributos data-*
 * definidos em includes/header.php e nas seções (data-header, data-nav,
 * data-words-stagger, data-demo-trigger...).
 */
(function () {                                                        // IIFE: variáveis ficam privadas, nada vaza para o escopo global
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)'); // preferência do sistema operacional por menos animação

  /* ------------------------------------------------------ menu de navegacao */
  var header = document.querySelector('[data-header]');
  var nav = document.querySelector('[data-nav]');
  var toggle = document.querySelector('[data-nav-toggle]');           // botão hambúrguer
  var backdrop = document.querySelector('[data-nav-backdrop]');
  var mobileQuery = window.matchMedia('(max-width: 960px)');          // mesmo ponto de quebra do CSS em que o menu vira painel

  /** O menu está aberto? (lido do aria-expanded do botão, que é a fonte da verdade) */
  function isMenuOpen() {
    return toggle && toggle.getAttribute('aria-expanded') === 'true';
  }

  /** Abre o painel do menu no celular. */
  function openMenu() {
    if (!nav || !toggle) return;
    nav.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'Fechar menu de navegação');    // o rótulo acompanha a próxima ação possível
    if (backdrop) backdrop.hidden = false;
    document.body.classList.add('nav-open');                          // o CSS usa esta classe para travar a rolagem do fundo
  }

  /**
   * Fecha o painel do menu.
   * @param {boolean} returnFocus true devolve o foco ao botão (usado ao fechar com Esc)
   */
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
    toggle.addEventListener('click', function () {                   // o mesmo botão abre e fecha
      if (isMenuOpen()) {
        closeMenu(false);
      } else {
        openMenu();
      }
    });

    if (backdrop) {
      backdrop.addEventListener('click', function () {                // clicar fora do painel fecha o menu
        closeMenu(false);
      });
    }

    // Fecha ao acionar um link do menu.
    nav.addEventListener('click', function (event) {
      var link = event.target.closest('a[href^="#"]');                // só links internos (âncoras "#...")
      if (link && mobileQuery.matches) closeMenu(false);
    });

    // Acessibilidade por teclado: Esc fecha e devolve o foco ao botao.
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && isMenuOpen()) closeMenu(true);
    });

    // Mantem o foco dentro do painel enquanto ele estiver aberto.
    nav.addEventListener('keydown', function (event) {
      if (event.key !== 'Tab' || !isMenuOpen()) return;

      var focusables = nav.querySelectorAll('a[href], button:not([disabled])'); // elementos que podem receber foco dentro do menu
      if (!focusables.length) return;

      var first = focusables[0];
      var last = focusables[focusables.length - 1];

      if (event.shiftKey && document.activeElement === first) {       // Shift+Tab no primeiro item: volta para o botão de fechar
        event.preventDefault();
        toggle.focus();
      } else if (!event.shiftKey && document.activeElement === last) { // Tab no último item: também volta para o botão (o foco circula)
        event.preventDefault();
        toggle.focus();
      }
    });

    // Ao voltar para o desktop, restaura o estado normal da navegacao.
    var onBreakpointChange = function (event) {
      if (!event.matches) closeMenu(false);                           // saiu da largura de celular com o menu aberto: fecha
    };
    if (typeof mobileQuery.addEventListener === 'function') {
      mobileQuery.addEventListener('change', onBreakpointChange);
    } else if (typeof mobileQuery.addListener === 'function') {       // navegadores antigos (Safari < 14) só têm addListener
      mobileQuery.addListener(onBreakpointChange);
    }
  }

  /* --------------------------------------------- estado do cabecalho na rolagem */
  // Sobre a hero o cabecalho e uma barra flutuante escura; depois dela vira a
  // barra clara encostada no topo. O limite e o fim da hero, nao 8px, para a
  // troca de tema nao acontecer ainda por cima da fotografia.
  if (header) {
    var ticking = false;                                              // evita recalcular mais de uma vez por quadro de tela
    var heroSection = document.querySelector('.hero');

    /** Posição de rolagem (em px) a partir da qual o cabeçalho troca de tema. */
    var switchPoint = function () {
      if (!heroSection) return 8;
      // troca um pouco antes do fim da hero, para a barra ja chegar legivel
      return Math.max(8, heroSection.offsetTop + heroSection.offsetHeight - header.offsetHeight - 24);
    };

    var updateHeader = function () {
      header.classList.toggle('is-scrolled', window.scrollY > switchPoint()); // is-scrolled ativa o tema claro no CSS
      ticking = false;
    };

    window.addEventListener('scroll', function () {
      if (ticking) return;                                            // já existe uma atualização agendada para este quadro
      ticking = true;
      window.requestAnimationFrame(updateHeader);                     // atualiza junto com o próximo desenho da tela (mais leve que a cada evento)
    }, { passive: true });                                            // passive: avisa que não haverá preventDefault, a rolagem fica mais fluida

    window.addEventListener('resize', updateHeader, { passive: true }); // a altura da hero muda ao redimensionar
    updateHeader();                                                   // aplica o estado certo já ao carregar (a página pode abrir no meio)
  }

  /* --------------------------- revelacao escalonada das palavras do titulo */
  /*
   * Equivalente em JavaScript puro ao <WordsStagger> do Spell UI (React).
   * O texto completo ja existe no HTML; aqui cada palavra e envolvida por um
   * <span> e revelada em sequencia. O estado inicial escondido so e aplicado
   * pela classe `is-staggering`, adicionada por este script: se ele nao rodar,
   * o titulo continua totalmente visivel.
   */
  function initWordsStagger() {
    var alvos = document.querySelectorAll('[data-words-stagger]');

    Array.prototype.forEach.call(alvos, function (alvo) {             // NodeList não tem forEach em navegadores antigos: usa o do Array
      if (alvo.getAttribute('data-words-ready') === 'true') return; // nao repete
      alvo.setAttribute('data-words-ready', 'true');

      var hero = alvo.closest('.hero');

      // Sem movimento: nao marca nada. Como o estado escondido depende das
      // classes aplicadas aqui, o conteudo simplesmente continua visivel.
      if (reduceMotion.matches) return;

      var texto = alvo.textContent.replace(/\s+/g, ' ').trim();       // normaliza espaços e quebras de linha do HTML
      var palavras = texto.split(' ');
      var fragmento = document.createDocumentFragment();              // monta tudo fora da página e insere de uma vez (um único redesenho)
      var spans = [];

      palavras.forEach(function (palavra, i) {                        // um <span> por palavra
        var span = document.createElement('span');
        span.className = 'hero__word';
        span.textContent = palavra;
        fragmento.appendChild(span);
        spans.push(span);
        // o espaco entre palavras continua sendo texto comum: a quebra de
        // linha do navegador nao muda e nenhuma palavra e cortada
        if (i < palavras.length - 1) {
          fragmento.appendChild(document.createTextNode(' '));
        }
      });

      alvo.textContent = '';                                          // remove o texto original...
      alvo.appendChild(fragmento);                                    // ...e coloca a versão com um <span> por palavra
      alvo.classList.add('is-staggering');                            // a partir daqui o CSS esconde as palavras para animar
      if (hero) hero.classList.add('is-entering');

      // o atraso por palavra fica no proprio span: uma unica passada de estilo
      spans.forEach(function (span, i) {
        span.style.transitionDelay = (180 + (i * 135)) + 'ms';        // 180 ms para a primeira e +135 ms para cada palavra seguinte
      });

      // proximo quadro: o estado inicial ja foi pintado, entao a transicao roda
      window.requestAnimationFrame(function () {                      // dois quadros seguidos garantem que o navegador desenhou o estado escondido antes
        window.requestAnimationFrame(function () {
          if (hero) hero.classList.add('is-ready');
          spans.forEach(function (span) { span.classList.add('is-visible'); }); // dispara a transição de cada palavra
        });
      });
    });
  }

  initWordsStagger();

  /* ------------------------------------------------------------ rolagem suave */
  // A rolagem suave e feita por CSS (scroll-behavior). Aqui apenas garantimos o
  // deslocamento correto do cabecalho fixo e o foco no destino, sem sujar a URL.
  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[href^="#"]');                  // qualquer link interno da página
    if (!link) return;

    var id = link.getAttribute('href');
    if (!id || id === '#') return;                                    // "#" sozinho não tem destino

    var target = document.querySelector(id);                          // elemento de destino (ex.: #sistema)
    if (!target) return;

    event.preventDefault();                                           // impede o salto padrão e a mudança da URL

    var headerHeight = header ? header.offsetHeight : 0;
    var top = target.getBoundingClientRect().top + window.scrollY - headerHeight - 16; // posição absoluta do destino, descontando o cabeçalho fixo e 16 px de folga

    window.scrollTo({
      top: top < 0 ? 0 : top,
      behavior: reduceMotion.matches ? 'auto' : 'smooth'              // sem animação para quem prefere menos movimento
    });

    // Move o foco para a secao de destino, mantendo a navegacao por teclado util.
    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1'); // tabindex -1: pode receber foco via código, sem entrar na ordem do Tab
    target.focus({ preventScroll: true });                            // foca sem rolar de novo (a rolagem suave já está acontecendo)
  });

  /* --------------------------------------- botoes ainda sem destino definitivo */
  // "Entrar" e "Solicitar demonstracao" ficam preparados visualmente. Nesta etapa
  // eles apenas informam que o recurso ainda nao esta disponivel.
  // (Hoje só o "Solicitar demonstração" usa este aviso: "Entrar" já leva ao login.php.)
  /**
   * Liga um botão a um aviso: ao clicar, o aviso aparece por 4 segundos.
   * @param {string} triggerSelector seletor do botão
   * @param {string} noteId          id do parágrafo de aviso
   */
  function bindPlaceholder(triggerSelector, noteId) {
    var trigger = document.querySelector(triggerSelector);
    var note = document.getElementById(noteId);
    if (!trigger || !note) return;

    var timer = null;

    trigger.addEventListener('click', function () {
      note.hidden = false;
      window.clearTimeout(timer);                                     // cliques repetidos reiniciam os 4 segundos
      timer = window.setTimeout(function () {
        note.hidden = true;
      }, 4000);
    });
  }

  bindPlaceholder('[data-demo-trigger]', 'aviso-demo');               // botão da seção Vantagens (includes/sections/vantagens.php)

  /* ------------------------------------------- areas com rolagem lateral propria */
  // O quadro do mockup so vira uma regiao focavel quando realmente transborda,
  // permitindo percorre-lo pelo teclado em telas pequenas.
  // (Nenhuma página atual tem elementos data-scroller; este bloco não encontra nada.)
  var scrollers = document.querySelectorAll('[data-scroller]');

  function updateScrollers() {
    scrollers.forEach(function (el) {
      if (el.scrollWidth > el.clientWidth + 1) {                      // conteúdo mais largo que a área visível: há rolagem
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
  // Elementos com a classe "reveal" aparecem suavemente ao entrar na tela.
  // (Nenhuma seção atual usa essa classe; o bloco encerra na verificação abaixo.)
  var revealables = document.querySelectorAll('.reveal');

  if (!revealables.length) return;                                    // sai da IIFE: nada mais a fazer

  if (reduceMotion.matches || !('IntersectionObserver' in window)) {  // sem animação ou sem suporte: mostra tudo de uma vez
    revealables.forEach(function (el) {
      el.classList.add('is-visible');
    });
    return;
  }

  var observer = new IntersectionObserver(function (entries) {        // avisa quando cada elemento entra na área visível
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);                               // anima uma única vez
    });
  }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });           // dispara quando 8% do elemento aparece, antes dos últimos 12% da tela

  revealables.forEach(function (el) {
    observer.observe(el);
  });
})();
