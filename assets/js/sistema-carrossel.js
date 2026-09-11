/**
 * Aquapulse — carrossel de telas da seção "Sistema".
 *
 * Mesmo comportamento e mesma transição do carrossel da seção "Por que
 * monitorar": ciclo de 3 s em loop, revelação lateral da direita para a
 * esquerda e pausas apenas temporárias. A diferença é que aqui não há
 * controles manuais: a sequência roda sozinha.
 *
 * O script vive em arquivo próprio e só enxerga `[data-sistema-carrossel]`.
 * Assim as duas instâncias da landing nunca compartilham temporizador,
 * listener ou estado.
 *
 * Regras de estado:
 *   - a troca só acontece quando NENHUM motivo de pausa está ativo;
 *   - ponteiro sobre o carrossel, seção fora da viewport e aba oculta entram e
 *     saem sozinhos — nenhum deles trava a reprodução;
 *   - ao retomar, uma única troca é agendada, sem compensar o tempo parado;
 *   - os 3 s contam entre INÍCIOS de transição: a duração da revelação não se
 *     soma ao intervalo.
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
    if (!raiz || raiz.getAttribute('data-sistema-pronto') === 'true') return;

    var telas = Array.prototype.slice.call(raiz.querySelectorAll('[data-sistema-tela]'));
    var saidaAtual = raiz.querySelector('[data-sistema-atual]');

    if (telas.length < 2) return; // uma tela só não é carrossel

    raiz.setAttribute('data-sistema-pronto', 'true');

    var intervalo = parseInt(raiz.getAttribute('data-intervalo'), 10) || INTERVALO_PADRAO;
    var transicao = parseInt(raiz.getAttribute('data-transicao'), 10) || TRANSICAO_PADRAO;

    var mqMovimento = window.matchMedia('(prefers-reduced-motion: reduce)');
    var mqPonteiro = window.matchMedia('(hover: hover) and (pointer: fine)');

    var indice = 0;
    var versao = 0;
    var timerCiclo = null;
    var timerFim = null;
    var emTransicao = false;
    var pendente = null;
    var pausas = Object.create(null);
    var validos = telas.map(function (_, i) { return i; });

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
       sair da seção ou trocar de aba nunca acumula temporizadores nem
       enfileira trocas atrasadas.
    */
    function agendar() {
      if (timerCiclo !== null) { window.clearTimeout(timerCiclo); timerCiclo = null; }
      if (estaPausado() || validos.length < 2) return;

      preparar(seguinte(indice)); // a próxima captura chega antes da hora

      timerCiclo = window.setTimeout(function () {
        timerCiclo = null;
        irPara(seguinte(indice));
      }, intervalo);
    }

    /* --------------------------------------------------------- navegação */

    function seguinte(de) {
      if (!validos.length) return de;
      var pos = validos.indexOf(de);
      if (pos === -1) return validos[0];
      return validos[(pos + 1) % validos.length];
    }

    function invalidar(i) {
      var pos = validos.indexOf(i);
      if (pos !== -1) validos.splice(pos, 1);
    }

    function imagemDe(i) {
      return telas[i] ? telas[i].querySelector('img') : null;
    }

    /*
       Carrega e decodifica antes de revelar. O atributo lazy sai no momento em
       que a captura passa a ser necessária, para não depender do carregamento
       de um elemento recortado.
    */
    function garantirCarregada(img) {
      return new Promise(function (resolve, reject) {
        if (!img) { reject(new Error('sem imagem')); return; }
        if (img.getAttribute('loading') === 'lazy') img.removeAttribute('loading');
        if (img.complete) {
          if (img.naturalWidth > 0) resolve();
          else reject(new Error('captura indisponível'));
          return;
        }
        img.addEventListener('load', function () { resolve(); }, { once: true });
        img.addEventListener('error', function () { reject(new Error('captura indisponível')); }, { once: true });
      });
    }

    function decodificar(img) {
      if (!img || typeof img.decode !== 'function') return Promise.resolve();
      return img.decode().catch(function () { /* decode falhou: o load já bastou */ });
    }

    /** Adianta uma captura sem bloquear nada; falha só marca a tela inválida. */
    function preparar(i) {
      if (i === indice) return;
      garantirCarregada(imagemDe(i)).catch(function () { invalidar(i); });
    }

    /** O contador acompanha a escolha assim que a revelação começa. */
    function marcar(alvo) {
      telas.forEach(function (tela, i) {
        // só a captura que passa a valer é exposta ao leitor de tela
        if (i === alvo) tela.removeAttribute('aria-hidden');
        else tela.setAttribute('aria-hidden', 'true');
      });
      // o contador não fica em região live: as trocas não são anunciadas
      if (saidaAtual) saidaAtual.textContent = (alvo + 1 < 10 ? '0' : '') + (alvo + 1);
      indice = alvo;
    }

    /**
     * Revela `alvo` por cima da captura atual.
     *
     * `versao` descarta respostas de carregamento que chegaram tarde e
     * `pendente` guarda uma escolha feita durante uma transição, para que duas
     * revelações nunca rodem ao mesmo tempo.
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
        invalidar(alvo);   // mantém a captura atual: nunca fica quadro vazio
        agendar();
      });
    }

    function revelar(alvo) {
      var anterior = telas[indice];
      var nova = telas[alvo];
      if (!nova) { agendar(); return; }

      emTransicao = true;
      marcar(alvo);

      // a contagem dos 3 s recomeça aqui, no INÍCIO da revelação
      agendar();

      nova.classList.add('is-entrando');   // vai para cima, ainda recortada
      void nova.offsetWidth;               // fixa o estado inicial antes de animar
      nova.classList.add('is-revelada');   // abre da direita para a esquerda

      var espera = mqMovimento.matches ? 0 : transicao;
      if (timerFim !== null) window.clearTimeout(timerFim);
      timerFim = window.setTimeout(function () {
        timerFim = null;
        // troca de papéis num só bloco: o estilo só é recalculado no fim,
        // então não existe quadro intermediário nem piscada
        if (anterior && anterior !== nova) anterior.classList.remove('is-base');
        nova.classList.remove('is-entrando', 'is-revelada');
        nova.classList.add('is-base');

        emTransicao = false;

        if (pendente !== null) {
          var proximo = pendente;
          pendente = null;
          irPara(proximo);
        }
      }, espera);
    }

    /* -------------------------------------------------- movimento reduzido */
    /* Sem reprodução automática e sem revelação: fica na primeira captura. */
    function aplicarMovimento() {
      if (mqMovimento.matches) {
        raiz.style.setProperty('--sis-transicao', '0ms');
        pausas.movimento = true;
      } else {
        raiz.style.setProperty('--sis-transicao', transicao + 'ms');
        delete pausas.movimento;
      }
      agendar();
    }

    /* ------------------------------------------------------- interações */

    if (mqPonteiro.matches) {
      raiz.addEventListener('mouseenter', function () { pausar('ponteiro'); });
      raiz.addEventListener('mouseleave', function () { liberar('ponteiro'); });
    }

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) pausar('aba');
      else liberar('aba');
    });

    /* ------------------------------------------- visibilidade da seção */
    /*
       Sem IntersectionObserver a primeira captura continua aparecendo; só a
       troca automática não arranca sozinha.
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

    marcar(0);
    agendar();
  }

  try {
    var raizes = document.querySelectorAll('[data-sistema-carrossel]');
    Array.prototype.forEach.call(raizes, iniciar);
  } catch (erro) {
    // um erro aqui não pode derrubar o resto da página
    if (window.console && console.error) console.error('[sistema] falha ao iniciar:', erro);
  }
})();
