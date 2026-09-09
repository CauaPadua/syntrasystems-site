# Aquapulse — seção editorial com slides automáticos

Implemente a seção da referência imediatamente abaixo da hero já aprovada, no projeto existente. Este pedido autoriza a implementação dessa seção, não uma reformulação de toda a landing.

## 1. Antes de alterar qualquer arquivo

- Leia as instruções do repositório e registre `git status` e o diff inicial. Preserve alterações anteriores de outras pessoas e não reverta arquivos.
- Localize este pacote dentro de `reference` ou `references`; use a pasta real do projeto, sem duplicar estruturas.
- Abra `referencia/secao-monitorar.png`, leia `manifesto-slides.json` e inspecione as cinco fotografias em `imagens/`.
- Inspecione o include que renderiza a seção informativa antiga, sua posição em `index.php`, seus IDs de âncora, CSS e JavaScript.
- Identifique as regras compartilhadas com a hero e com o login antes de editar qualquer estilo global.
- Capture a landing, a hero e o login antes das mudanças, para comparação posterior.

## 2. Escopo e tecnologia

- Somente PHP 8.0.30, HTML5, CSS3 e JavaScript puro, compatíveis com XAMPP.
- Sem React, Tailwind, framework, bundler, npm, Swiper, jQuery ou dependência de CDN.
- Não implementar banco, API, upload de imagens ou painel administrativo para esta seção.
- As imagens são arquivos estáticos locais; a troca acontece no navegador.
- Preservar hero, animação WordsStagger existente, cabeçalho flutuante, login, dashboard, sessão, APIs e dados simulados.
- Substituir a antiga seção de informações equivalente; não empilhar duas seções que dizem a mesma coisa.
- Preservar as demais seções. Não apagar os cards de importância de outra seção por confundi-los com a seção a substituir.
- Sem commit, push, deploy, alteração de pipeline ou instalação de plugins.

## 3. Conteúdo exato em português

Selo: `POR QUE MONITORAR`

Chamada lateral:
`Água segura.`
`Futuro protegido.`

Linha de apoio lateral: `INFORMAÇÃO QUE ORIENTA DECISÕES`

Título principal da seção:
`Proteger a água é cuidar de quem depende dela.`

Parágrafo:
`Represas abastecem cidades, geram energia e sustentam comunidades. Acompanhar suas mudanças ajuda a antecipar riscos e orientar uma gestão responsável.`

Botão: `Entenda a importância` com seta diagonal para cima e para a direita, reutilizando o ícone existente.

Use um `h2` para o título principal, sem introduzir outro `h1`. A chamada lateral pode ser um parágrafo destacado, para manter uma hierarquia coerente. Texto real selecionável em HTML, nunca uma captura da seção inteira.

As imagens são ilustrativas: não associar a locais, funcionários ou clientes reais, nem inventar estatísticas, nomes de represas ou marcas nos textos.

## 4. Composição visual

Fonte, azul-marinho e linguagem visual devem conversar com a hero aprovada. A referência desta seção é a autoridade para composição; valores abaixo são aproximações, não medições do código original.

- Seção clara com fundo azul-gelo, container centralizado, bastante respiro e cantos externos discretamente arredondados.
- Na referência de 1536px: bloco externo ocupa cerca de 93% da largura; margens de aproximadamente 52px e padding interno de 44px. Adaptar ao container real do projeto.
- Parte superior em duas colunas: coluna esquerda perto de 34%, direita perto de 66%, sem espremer títulos.
- Selo no alto à esquerda. A chamada lateral aparece abaixo, próxima da base da área de textos.
- À direita, título editorial grande, parágrafo e botão alinhados à esquerda.
- Título principal fluido, aproximadamente 64–72px no desktop de referência, peso 650–700 e entrelinha próxima de 1.08; pode ser menor conforme a fonte real. Não forçar quebras que provoquem overflow.
- Chamada lateral aproximadamente 44–50px em desktop; corpo 18–22px, com bom contraste. Texto secundário não excessivamente claro.
- Paleta inicial sugerida: título #0B204D, botão #0752EF, fundo #EFF7FC, selo #E1F1FF, apoio #52678E; ajustar pelos tokens existentes e validar contraste.
- Não criar uma grade de quatro cards, ícones genéricos, números fictícios, partículas ou brilho neon.

Parte inferior:

- Dois painéis fotográficos alinhados, mesma altura no desktop, separados por cerca de 20px.
- Painel grande ocupa aproximadamente 72% da faixa; lateral cerca de 28%.
- Painel grande com relação visual próxima de 2:1; lateral vertical, com altura igual à principal, usando `object-fit: cover`.
- Bordas assimétricas semelhantes à referência: canto superior esquerdo e inferior direito maiores (aproximadamente 60–80px em desktop); outros discretos (aproximadamente 4–8px).
- Aplicar recorte pelo CSS do container com `overflow: hidden`. As fotografias fornecidas são retangulares e não devem receber cantos desenhados dentro dos arquivos.
- Não esticar, desfocar artificialmente ou recortar o profissional e a estação fora do enquadramento.
- A foto lateral da água fica fixa. Não duplicar os quatro slides no painel estreito nem criar outro temporizador.

## 5. Recursos fornecidos

Copie somente os cinco WebP de `imagens/` para `assets/images/monitorar/`, preservando nomes:

1. `01-monitoramento.webp` — primeiro slide: profissional junto à estação de monitoramento.
2. `02-vista-aerea.webp` — segundo: vista aérea do reservatório.
3. `03-barragem.webp` — terceiro: barragem e escoamento.
4. `04-reservatorio.webp` — quarto: estação e reservatório.
5. `agua-lateral.webp` — foto lateral fixa, não participante da sequência.

Use descrições alternativas e posições do `manifesto-slides.json`. Os caminhos `source` do manifesto são relativos ao pacote, não URLs finais do site: remapeie para a pasta de destino usando o mecanismo de base URL existente no projeto. Não usar caminhos absolutos do computador, file:// nem caminhos Windows no HTML.

Pode representar os quatro slides em um array PHP local à seção. Não é necessário buscar o JSON pelo navegador nem criar endpoint para dados estáticos. Ao renderizar atributos e texto em PHP, use o helper de escape existente.

Os PNGs em `originais/` são arquivos de edição/referência. Não baixá-los junto com os WebP em produção e não fazer preload de todas as imagens. A captura da referência também não é um recurso público da seção.

## 6. Carrossel e regras de estado

Começar com o slide 1 e alternar 1 → 2 → 3 → 4 → 1. Permanência de 5000ms e crossfade de aproximadamente 700ms. Textos e CTA permanecem estáticos.

- Preferir fade entre imagens sobrepostas, sem deslocar o layout nem mudar a altura do painel.
- Zoom opcional muito discreto (1 → 1.025), somente na imagem ativa, respeitando pausa e movimento reduzido. Se comprometer a leitura visual, omitir o zoom e manter o fade.
- Abaixo do painel principal, controles pequenos e legíveis: anterior, quatro indicadores, próximo e pausar/reproduzir. Alvos de toque confortáveis, idealmente 44px. Incluir contador visual `1 / 4` se couber sem poluição.
- Não trocar slide ao mover foco entre controles. Só realizar a navegação solicitada.
- Botões `type="button"` para evitar submissão de formulários.
- Inicializar uma única vez por instância; se a seção não existir, sair sem erro. Não interferir em outros componentes.
- Usar uma única rotina de agendamento cancelável; não acumular intervalos em entradas/saídas da seção, resize ou mudança de aba.

Pausas:

- Não iniciar autoplay até que parte significativa da seção esteja visível, por exemplo 35% da área do carrossel, com IntersectionObserver.
- Pausar fora da viewport e quando `document.hidden` for verdadeiro. Ao voltar, esperar um ciclo inteiro, sem compensar slides perdidos.
- Pausar em hover nos dispositivos com mouse; retomar ao sair apenas se nenhuma outra condição de pausa estiver ativa.
- Ao receber foco de teclado, parar a reprodução e mantê-la parada até o usuário pedir explicitamente para reproduzir. Não reiniciar só porque o foco saiu.
- Ao clicar em anterior/próximo/indicador, manter o modo manual até reproduzir explicitamente.
- Clique em Pausar tem prioridade sobre hover, foco, visibilidade e quaisquer eventos de retomada. O rótulo deve refletir a intenção do controle.
- Com `prefers-reduced-motion: reduce`, iniciar sem autoplay, sem fade/zoom e permitir navegação manual instantânea. Detectar também mudança dessa preferência durante a sessão e interromper a animação.
- Sem suporte a IntersectionObserver, manter navegação manual; não quebrar a seção.

## 7. Carregamento, fallback e robustez

- Reservar dimensões com `aspect-ratio` e/ou atributos width/height reais. Manter altura estável antes e depois de carregar cada imagem.
- Primeira imagem presente em HTML e visível sem JavaScript; demais não devem virar uma pilha vertical no fallback.
- Controles que dependem de JS só aparecem depois da inicialização bem-sucedida.
- Seção abaixo da hero: não competir com o preload/prioridade alta da hero. Usar lazy loading apropriado nas imagens desta seção.
- Carregar/decodificar o próximo recurso antes do crossfade; não depender apenas de lazy loading em elementos invisíveis.
- Se um slide falhar, conservar o atual, não exibir painel vazio, não criar rejeição de Promise sem tratamento nem loop de tentativas. Pular slides indisponíveis e parar autoplay se restar menos de dois válidos.
- Cliques rápidos não devem deixar duas imagens ativas, contador errado ou callbacks antigos substituindo uma escolha recente. Implementar bloqueio/controle de versão para transições assíncronas.
- Não usar `innerHTML` com dados externos, `eval`, IDs duplicados ou listeners globais desnecessários.
- A inicialização do carrossel não deve poder impedir a animação da hero ou o menu caso ocorra erro local.

## 8. Acessibilidade e âncoras

- Carrossel com nome acessível, controles nomeados em português e indicador atual reconhecível sem depender apenas de cor.
- Somente o slide ativo deve ser exposto como conteúdo atual ao leitor de tela. Slides ocultos não contêm elementos focáveis.
- Não anunciar cada troca automática por região live. Se anunciar navegação manual, fazê-lo de forma discreta, sem duplicar o texto alternativo.
- Foco visível, ordem de tabulação lógica, botão de pausa utilizável no celular. Não depender exclusivamente de hover para parar a animação.
- Foto lateral decorativa com `alt=""`, conforme o manifesto.
- Preservar a âncora da seção que já é usada pelo menu/CTA da hero. Usar `scroll-margin-top` para compensar o cabeçalho fixo sem mudar sua lógica.
- O botão `Entenda a importância` deve apontar para a seção real de importância já existente. Verificar que o destino existe e não é a própria seção, um `href="#"` ou uma âncora quebrada. Se esse conteúdo ainda não existir, registrar o impedimento e pedir a definição do destino, sem inventar página nem alterar outras seções.

## 9. Responsividade

- Desktop: reproduzir a assimetria da referência, sem margens desiguais acidentais.
- Tablet: reduzir títulos, gaps e raios, empilhando textos quando necessário; não esmagar as duas colunas para manter uma grade a qualquer custo.
- Mobile: texto em fluxo lógico, CTA acessível, painel grande em largura total com proporção 16:9 ou 2:1 conforme melhor enquadramento e foto lateral abaixo em faixa menor. Não recortar conteúdo nem usar altura fixa na seção.
- A imagem lateral pode ganhar outro enquadramento no celular, mas deve permanecer presente, sem aumentar a altura da seção desnecessariamente.
- Não alterar breakpoints globais de hero/login. Usar classes específicas, por exemplo `.aq-monitorar` e `.aq-monitorar__...`, evitando regras genéricas para `h2`, `img`, `button`, `.container` ou `section`.

## 10. Integração e validação obrigatória

Adapte a arquitetura existente: edite o include equivalente ou crie um componente local e substitua só aquela chamada. CSS/JS específicos podem ficar em arquivos locais da landing, carregados uma única vez, apenas onde necessários. Não entregue arquivos de demonstração desconectados do site como se fossem implementação concluída.

Teste no endereço real do XAMPP, inclusive se o projeto estiver em subpasta. Não usar só `file://` ou servidor de arquivos estáticos para alegar validação do PHP.

Checklist de aceite:

- Capturas finais em 1536, 1440, 1024, 768 e 375px; confirmar layout, proporções, recortes e inexistência de overflow horizontal. Comparar com a referência, não apenas medir elementos.
- Primeira fotografia correta; percorrer os quatro slides e observar retorno ao primeiro por pelo menos dois ciclos.
- Testar anterior/próximo/indicadores, cliques rápidos e estado consistente dos controles.
- Testar pausa explícita, hover, foco de teclado, saída da viewport, aba em segundo plano e retorno. Pausa explícita não pode ser desfeita automaticamente.
- Testar movimento reduzido e ausência total de JavaScript: texto, primeiro slide e CTA continuam utilizáveis.
- Simular falha em uma imagem e rede lenta: sem painel branco, timers em loop ou erro não tratado.
- Confirmar dimensão reservada e ausência de saltos na troca; verificar console, 404 e exceções assíncronas.
- Conferir que todos os cinco recursos são locais e que a página não carrega os PNGs de referência.
- Testar CTA, menu mobile, hero e seu WordsStagger após integração. Comparar login antes/depois, já que pode compartilhar CSS.
- Rodar lint dos PHP alterados e checagem de sintaxe JS disponível. Se faltar ferramenta, relatar o que não foi testado; não inventar aprovação.

Ao concluir, entregar em português:

1. Resumo da mudança e dos arquivos alterados/criados.
2. Caminho da seção e endereço local para abri-la.
3. Evidências de testes e capturas, separando resultado observado de limitação.
4. Peso efetivo dos recursos e estratégia de carregamento.
5. Onde trocar imagens e ajustar os 5000ms futuramente.
6. Confirmação de preservação da hero, login, dashboard e backend, com base no diff e nos testes realmente realizados.
7. Nenhum commit, push ou deploy automático.
