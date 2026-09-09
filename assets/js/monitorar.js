/**
 * Aquapulse — carrossel da seção "Por que monitorar".
 *
 * JavaScript puro, sem dependências. Roda em arquivo próprio para que uma
 * falha aqui não possa impedir a animação da hero nem o menu, que vivem em
 * main.js. Se a seção não existir, o script sai em silêncio.
 *
 * Reprodução:
 *   - começa sozinho quando a seção fica visível e troca a cada 3000ms,
 *     em sequência contínua 1 → 2 → 3 → 4 → 1;
 *   - os 3000ms contam entre INÍCIOS de transição: a duração da revelação
 *     não se soma ao intervalo;
 *   - as pausas são todas temporárias (ponteiro ou foco nos controles, seção
 *     fora da viewport, aba oculta). Nenhuma interação deixa a reprodução
 *     parada para sempre;
 *   - navegar manualmente apenas reinicia a contagem dos 3000ms;
 *   - ao retomar, uma única troca é agendada: nunca se avança vários slides
 *     de uma vez para "compensar" o tempo parado.
 *
 * Transição: a fotografia que entra vai para cima da atual e abre o recorte
 * da direita para a esquerda, enquanto assenta de scale(1.025) para 1. A
 * anterior segue preenchendo o painel até o fim, então não há fundo vazio.
 */
(function () {
  'use strict';

  var INTERVALO_PADRAO = 3000;
  var TRANSICAO_PADRAO = 650;
  var FRACAO_VISIVEL = 0.35;

  /** matchMedia antigo do Safari usa addListener. */
  function escutarMedia(mq, aoMudar) {
    if (typeof mq.addEventListener === 'function') mq.addEventListener('change', aoMudar);
    else if (typeof mq.addListener === 'function') mq.addListener(aoMudar);
  }

  function iniciar(raiz) {
    if (!raiz || raiz.getAttribute('data-monitorar-pronto') === 'true') return;

    var slides = Array.prototype.slice.call(raiz.querySelectorAll('[data-monitorar-slide]'));
    if (slides.length < 2) return; // um slide só não é carrossel

    var controles = raiz.querySelector('[data-monitorar-controles]');
    var botaoAnterior = raiz.querySelector('[data-monitorar-anterior]');
    var botaoProximo = raiz.querySelector('[data-monitorar-proximo]');
    var pontos = Array.prototype.slice.call(raiz.querySelectorAll('[data-monitorar-ponto]'));

    if (!controles || !botaoAnterior || !botaoProximo) return;

    raiz.setAttribute('data-monitorar-pronto', 'true');

    var intervalo = parseInt(raiz.getAttribute('data-intervalo'), 10) || INTERVALO_PADRAO;
    var transicao = parseInt(raiz.getAttribute('data-transicao'), 10) || TRANSICAO_PADRAO;

    var mqMovimento = window.matchMedia('(prefers-reduced-motion: reduce)');
    var mqPonteiro = window.matchMedia('(hover: hover) and (pointer: fine)');

    var indice = 0;
    var versao = 0;
    var timerCiclo = null;
    var timerFim = null;
    var emTransicao = false;
    var pendente = null;      // última escolha feita durante uma transição
    var pausas = Object.create(null);
    var validos = slides.map(function (_, i) { return i; });

    /* ----------------------------------------------------- pausas temporárias */

    function pausar(motivo) { pausas[motivo] = true; agendar(); }
    function liberar(motivo) { delete pausas[motivo]; agendar(); }
    function estaPausado() {
      for (var k in pausas) { if (pausas[k]) return true; }
      return false;
    }

    /* ------------------------------------------------- agendamento único */
    /*
       Uma só rotina cancelável. Cada chamada limpa a anterior, então entrar e
       sair da seção, redimensionar ou trocar de aba nunca acumula
       temporizadores nem enfileira trocas atrasadas.
    */
    function agendar() {
      if (timerCiclo !== null) { window.clearTimeout(timerCiclo); timerCiclo = null; }
      if (estaPausado() || validos.length < 2) return;

      preparar(seguinte(indice, 1)); // a próxima imagem chega antes da hora

      timerCiclo = window.setTimeout(function () {
        timerCiclo = null;
        irPara(seguinte(indice, 1));
      }, intervalo);
    }

    /* --------------------------------------------------------- navegação */

    function seguinte(de, passo) {
      if (!validos.length) return de;
      var pos = validos.indexOf(de);
      if (pos === -1) return validos[0];
      return validos[(pos + passo + validos.length) % validos.length];
    }

    function invalidar(i) {
      var pos = validos.indexOf(i);
      if (pos === -1) return;
      validos.splice(pos, 1);
      // o indicador de um slide indisponível para de prometer o que não entrega
      if (pontos[i]) {
        pontos[i].disabled = true;
        pontos[i].setAttribute('aria-label', 'Fotografia ' + (i + 1) + ' indisponível');
      }
    }

    function imagemDe(i) {
      return slides[i] ? slides[i].querySelector('img') : null;
    }

    /*
       Garante que a imagem esteja carregada antes de a revelação começar. O
       atributo lazy sai no momento em que a imagem passa a ser necessária,
       para não depender do carregamento de um elemento recortado.
    */
    function garantirCarregada(img) {
      return new Promise(function (resolve, reject) {
        if (!img) { reject(new Error('sem imagem')); return; }
        if (img.getAttribute('loading') === 'lazy') img.removeAttribute('loading');
        if (img.complete) {
          if (img.naturalWidth > 0) resolve();
          else reject(new Error('imagem indisponível'));
          return;
        }
        img.addEventListener('load', function () { resolve(); }, { once: true });
        img.addEventListener('error', function () { reject(new Error('imagem indisponível')); }, { once: true });
      });
    }

    function decodificar(img) {
      if (!img || typeof img.decode !== 'function') return Promise.resolve();
      return img.decode().catch(function () { /* decode falhou: o load já bastou */ });
    }

    /** Adianta uma imagem sem bloquear nada; falha só marca o slide inválido. */
    function preparar(i) {
      if (i === indice) return;
      garantirCarregada(imagemDe(i)).catch(function () { invalidar(i); });
    }

    /** Indicadores acompanham a escolha assim que a revelação começa. */
    function marcar(alvo) {
      pontos.forEach(function (ponto, i) {
        var ativo = i === alvo;
        ponto.classList.toggle('is-ativo', ativo);
        if (ativo) ponto.setAttribute('aria-current', 'true');
        else ponto.removeAttribute('aria-current');
      });
      slides.forEach(function (slide, i) {
        // só o slide que passa a valer é exposto ao leitor de tela
        if (i === alvo) slide.removeAttribute('aria-hidden');
        else slide.setAttribute('aria-hidden', 'true');
      });
    }

    /**
     * Revela `alvo` por cima do slide atual.
     *
     * Cliques rápidos não sobrepõem transições: enquanto uma roda, a escolha
     * mais recente fica guardada em `pendente` e é executada assim que a atual
     * termina. `versao` descarta respostas de carregamento que chegaram tarde.
     */
    function irPara(alvo) {
      if (typeof alvo !== 'number' || alvo === indice) { agendar(); return; }

      if (emTransicao) { pendente = alvo; return; }

      var minha = ++versao;
      var img = imagemDe(alvo);

      garantirCarregada(img).then(function () {
        if (minha !== versao) return;
        return decodificar(img).then(function () {
          if (minha !== versao) return;
          revelar(alvo);
        });
      }).catch(function () {
        if (minha !== versao) return;
        invalidar(alvo);   // mantém a atual: nunca fica painel vazio
        agendar();
      });
    }

    function revelar(alvo) {
      var anterior = slides[indice];
      var novo = slides[alvo];
      if (!novo) { agendar(); return; }

      emTransicao = true;
      indice = alvo;
      marcar(alvo);

      // a contagem dos 3000ms recomeça aqui, no INÍCIO da revelação
      agendar();

      novo.classList.add('is-entrando');   // vai para cima, ainda recortado
      void novo.offsetWidth;               // fixa o estado inicial antes de animar
      novo.classList.add('is-revelada');   // abre da direita para a esquerda

      var espera = mqMovimento.matches ? 0 : transicao;
      if (timerFim !== null) window.clearTimeout(timerFim);
      timerFim = window.setTimeout(function () {
        timerFim = null;
        // troca de papéis num só bloco: o estilo só é recalculado no fim,
        // então não existe quadro intermediário nem piscada
        if (anterior && anterior !== novo) anterior.classList.remove('is-base');
        novo.classList.remove('is-entrando', 'is-revelada');
        novo.classList.add('is-base');

        emTransicao = false;

        if (pendente !== null) {
          var proximo = pendente;
          pendente = null;
          irPara(proximo);
        }
      }, espera);
    }

    /* -------------------------------------------------- movimento reduzido */

    function aplicarMovimento() {
      if (mqMovimento.matches) {
        raiz.style.setProperty('--mon-transicao', '0ms');
        pausas.movimento = true;           // sem reprodução automática
      } else {
        raiz.style.setProperty('--mon-transicao', transicao + 'ms');
        delete pausas.movimento;
      }
      agendar();
    }

    /* ------------------------------------------------------- interações */
    /*
       Navegar manualmente não para a reprodução: `agendar()` dentro de
       `revelar()` apenas reinicia a contagem dos 3000ms a partir dali.
    */
    botaoAnterior.addEventListener('click', function () { irPara(seguinte(indice, -1)); });
    botaoProximo.addEventListener('click', function () { irPara(seguinte(indice, 1)); });

    pontos.forEach(function (ponto, i) {
      ponto.addEventListener('click', function () { irPara(i); });
    });

    // ponteiro e foco nos controles seguram a troca só enquanto durarem
    if (mqPonteiro.matches) {
      controles.addEventListener('mouseenter', function () { pausar('ponteiro'); });
      controles.addEventListener('mouseleave', function () { liberar('ponteiro'); });
    }
    controles.addEventListener('focusin', function () { pausar('foco'); });
    controles.addEventListener('focusout', function () {
      // só libera quando o foco realmente saiu da barra de controles
      if (!controles.contains(document.activeElement)) liberar('foco');
    });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) pausar('aba');
      else liberar('aba');
    });

    /* ------------------------------------------- visibilidade da seção */
    /*
       Sem IntersectionObserver a navegação manual continua funcionando; só a
       reprodução automática não arranca sozinha.
    */
    if ('IntersectionObserver' in window) {
      pausas.fora = true;
      var observador = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (entrada) {
          if (entrada.isIntersecting && entrada.intersectionRatio >= FRACAO_VISIVEL) liberar('fora');
          else pausar('fora');
        });
      }, { threshold: [0, FRACAO_VISIVEL, 0.6] });
      observador.observe(raiz);
    }

    aplicarMovimento();
    escutarMedia(mqMovimento, aplicarMovimento);

    controles.hidden = false;   // só agora os controles fazem sentido
    marcar(0);
    agendar();
  }

  try {
    var raizes = document.querySelectorAll('[data-monitorar-carrossel]');
    Array.prototype.forEach.call(raizes, iniciar);
  } catch (erro) {
    // um erro aqui não pode derrubar o resto da página
    if (window.console && console.error) console.error('[monitorar] falha ao iniciar:', erro);
  }
})();
