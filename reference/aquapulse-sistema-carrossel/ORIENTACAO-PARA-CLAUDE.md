# Reformular a seção de apresentação do sistema Aquapulse

Implemente a composição de `referencia-secao-sistema.png` na landing existente. Esta é a seção que tem o título “Visão clara para monitorar, analisar e decidir”. Não é a seção final de vantagens aprovada anteriormente.

## Escopo

Trabalhe na estrutura atual em PHP, HTML, CSS e JavaScript puro. Preserve o restante da landing, incluindo hero, carrossel de fotografias de represas, vantagens, navegação e rodapé. Preserve login, dashboard e API. Não adicionar banco de dados ou dependências de framework. Leia as instruções locais do repositório antes de editar e preserve alterações em andamento de outros integrantes.

O cabeçalho aparece na referência para contextualizar o layout. Não duplique o menu dentro desta seção nem troque o cabeçalho atual por uma imagem.

## Layout

- Fundo branco, com continuidade visual com as seções brancas adjacentes.
- Textos à esquerda e carrossel à direita no desktop.
- Coluna da imagem maior, aproximadamente 60–65% da área útil. A coluna de texto ocupa o restante, separada por um espaço confortável.
- Fotografia de paisagem não deve aparecer nesta seção. As cinco imagens são capturas do próprio sistema.
- Título azul-marinho, hierarquia clara, fonte local do projeto e parágrafos em azul acinzentado com contraste suficiente.
- Identificação superior pequena, sem cápsula de fundo azul; três benefícios em uma coluna, sem cards individuais.
- Ícones discretos, reaproveitados do sistema de ícones do projeto.
- Quadro do carrossel plano, sem perspectiva, laptop, navegador falso, efeito 3D ou recorte fora da página. Borda fina e sombra suave apenas no quadro da imagem.
- Área das imagens com `aspect-ratio: 2 / 1`. Preserve a proporção com `object-fit: contain`; não esticar, nem usar `cover` cortando dados.
- Todas as imagens têm o mesmo tamanho para evitar mudanças de altura a cada troca.
- Legenda discreta abaixo da imagem e contador de cinco telas. Abaixo, cinco opções de navegação com texto; destaque azul e linha fina na opção ativa.

No mobile, coloque texto antes do carrossel e use a largura disponível. Mantenha a proporção dos prints. As opções podem quebrar linha ou ficar em uma faixa de navegação com rolagem própria, sem provocar rolagem horizontal na página. Use controles com área de toque confortável. Os prints são uma prévia visual do sistema; não tente tornar cada número minúsculo legível deformando a imagem.

## Conteúdo exato da seção

Identificação:

> COMO A AQUAPULSE APOIA SUA OPERAÇÃO

Título em `h2`:

> Visão clara para monitorar, analisar e decidir

Parágrafo:

> O Aquapulse centraliza as informações estratégicas dos seus reservatórios em um só lugar, com dados confiáveis e atualizados para apoiar decisões mais seguras e operações mais eficientes.

Benefício 1:

> Dados organizados em uma visão unificada

> Todas as informações essenciais em um único lugar, com clareza e contexto.

Benefício 2:

> Alertas que ajudam na tomada de decisão

> Notificações inteligentes que antecipam riscos e apoiam a resposta da equipe.

Benefício 3:

> Informações confiáveis para equipes técnicas

> Dados precisos e atualizados que fortalecem o planejamento e a gestão da operação.

Legenda:

> Prévia ilustrativa da interface do Aquapulse.

Mantenha o nome Aquapulse. Não reescrever a copy, inserir métricas publicitárias ou adicionar chamadas extras.

## Imagens e ordem

Use as versões WebP de `slides-web/` como assets públicos, copiando para o diretório de imagens já existente no projeto. Os PNGs de `slides/` servem como versões maiores para referência ou visualização ampliada.

1. `01-visao-geral.webp` — Visão geral.
2. `02-nivel-reservatorio.webp` — Nível do reservatório.
3. `03-relatorios.webp` — Relatórios.
4. `04-mapas.webp` — Mapas.
5. `05-alertas.webp` — Alertas.

São imagens estáticas ilustrativas do sistema enviado pelo usuário. Não conectar essa seção à API de monitoramento, não abrir iframes do dashboard e não tentar gerar gráficos novos para reproduzir o conteúdo dos prints. Preserve os créditos visíveis do mapa e os dados das capturas.

## Comportamento do carrossel

Trocar automaticamente a cada 3 segundos, em sequência e em loop. A primeira imagem é Visão geral. Use uma transição de opacidade discreta, aproximadamente 450 ms, como em `previa-carrossel.mp4`. Apenas o quadro, a opção ativa e o contador mudam; o texto de apresentação continua imóvel.

Não mostrar botão de pausar/iniciar nem controles sobre os gráficos. As cinco opções textuais abaixo permitem selecionar uma tela manualmente. São botões de navegação: mantenha foco visível e indique a seleção, por exemplo com `aria-pressed`. Só use semântica de tabs se implementar também todo o comportamento de teclado correspondente.

Para leitura e acesso por teclado, suspenda a troca enquanto o foco estiver dentro do carrossel; suspenda também durante hover. A navegação manual não deve ser imediatamente revertida por um temporizador antigo. Respeite `prefers-reduced-motion`: nesse caso apresente a primeira imagem sem autoplay e mantenha a seleção manual disponível.

Pause o temporizador quando a seção não estiver visível ou quando a aba estiver oculta. Ao retomar, não acumule várias trocas atrasadas. Evite timers duplicados e listeners compartilhados que interfiram no outro carrossel da landing.

Mantenha a primeira imagem disponível sem JavaScript. Carregue e decodifique a próxima antes de exibi-la. Em falha de carregamento, preserve uma imagem válida; não apresentar quadro branco. Reserve o tamanho das imagens desde o início e evite cinco downloads prioritários concorrendo com a hero.

Use descrições acessíveis breves das telas, como “Visão geral do Aquapulse com indicadores e comparativo entre represas”. Não anunciar automaticamente cada troca a leitores de tela em uma região `aria-live` contínua.

## Implementação e validação

Isole classes e script desta seção, seguindo o padrão do projeto. Não aplicar CSS global que altere outros títulos, botões, imagens ou cards. Reutilize o carrossel existente se ele permitir instâncias independentes e atender a estes requisitos; caso contrário, crie um módulo pequeno e específico em JavaScript puro.

Valide em desktop e mobile: imagem à direita no desktop; conteúdo empilhado no celular; cinco telas na ordem; contador e seleção sincronizados; loop sem flashes; proporções preservadas; ausência de rolagem horizontal; navegação por teclado; redução de movimento; nenhuma interferência no carrossel de represas. Verifique carregamento dos assets tanto no subdiretório do XAMPP quanto no padrão de URL já utilizado pelo projeto.

Compare o resultado com a referência, mantendo os textos em HTML real. Não publique o PNG inteiro como conteúdo da seção. Relate arquivos alterados, verificações efetivamente executadas e eventuais limitações. Não afirmar testes não executados. Esta tarefa termina com a implementação pronta para revisão local; não inclui commit, push ou deploy.
