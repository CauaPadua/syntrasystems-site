# Aquapulse — substituir a última seção da landing page

Implemente no projeto existente a nova seção de vantagens conforme os arquivos deste pacote. A composição visual foi aprovada. Execute a implementação, compare o resultado com a referência e entregue o resumo das alterações.

## 1. Objetivo e escopo

Substituir a seção antiga de vantagens da landing page, normalmente identificada pelo título “Vantagens de um monitoramento mais inteligente”, pela composição aprovada deste pacote.

“Última página” significa aqui a última seção de conteúdo da landing page. Não criar outra página, rota ou tela do dashboard. Localize a seção existente e mantenha a posição dela no fluxo, preservando o rodapé e eventuais blocos de contato que já existam depois dela.

O projeto usa PHP, HTML, CSS e JavaScript puro. Trabalhe na estrutura atual, sem React, Next.js, Tailwind, npm, bundler ou novas bibliotecas. Não implementar banco de dados. Login, dashboard, APIs, autenticação, hero e carrossel das outras seções permanecem fora deste trabalho.

## 2. Arquivos e como utilizá-los

Este pacote deverá estar em `references/aquapulse-vantagens/`. Se o repositório já utilizar a pasta `reference/`, pode estar dentro dela; localize os arquivos sem duplicar diretórios existentes.

- `referencia-vantagens.png`: imagem completa aprovada, com textos e botão. É apenas a referência visual para comparação.
- `aquapulse-vantagens-fundo.png`: fotografia preparada sem textos, linhas ou botão. É o recurso visual que deve aparecer na seção.
- `IMPLEMENTAR-VANTAGENS.md`: estas instruções.
- `LEIA-ME.md`: instruções para utilizar o pacote.

Copie a fotografia limpa para a pasta pública de imagens já usada pelo projeto, com nome claro, por exemplo `assets/images/aquapulse-vantagens-fundo.png`. Monte todos os títulos, descrições, divisores e botão em HTML/CSS reais, selecionáveis e responsivos.

Não use a imagem completa da referência como se fosse a seção pronta. Isso deixaria textos e botão gravados no PNG e impediria interação e adaptação ao celular.

A referência completa é uma cópia do protótipo aprovado. O fundo foi preparado por remoção dos elementos e reconstrução visual das regiões cobertas. Ele não é uma camada original recuperada, nem uma fotografia comprovadamente associada a uma represa real. Use-o como imagem ilustrativa, sem inventar nome, coordenadas, certificações ou dados de operação.

## 3. Antes de editar

Leia as instruções locais do repositório e registre o estado atual das alterações. Localize o arquivo que renderiza a landing, os estilos e scripts dela, a seção antiga de vantagens e o destino atualmente usado pelo CTA de demonstração.

Inspecione os dois PNGs deste pacote. Identifique também a fonte local utilizada na hero e os padrões existentes de caminhos de assets, espaçamento, responsividade e acessibilidade. Reaproveite o que for compatível com a composição aprovada.

Preserve alterações de outros integrantes. Não substituir arquivos inteiros quando uma alteração localizada resolver. Não renomear pastas do projeto, reorganizar o backend ou alterar contratos de API para esta mudança visual.

## 4. Direção visual obrigatória

Crie uma seção fotográfica contínua, de largura total, com o azul da água dominante. A barragem fica à esquerda; todo o conteúdo fica sobreposto à fotografia, à direita. A água precisa continuar visível atrás dos textos.

A seção não é dividida entre foto e painel sólido. Não usar cards, moldura externa, borda arredondada em volta da seção, faixa branca intermediária, ícones decorativos, partículas, brilhos, ondas artificiais ou indicadores inventados.

Preserve a aparência mais sóbria aprovada: fotografia com textura natural, azul profundo, fonte leve, hierarquia clara, espaços consistentes, três grupos de benefícios em uma coluna e divisores discretos. Não retornar à grade antiga de seis cards com ícones ciano.

O fundo já possui tratamento azul e escurecimento suficiente em parte da área direita. Comece utilizando a imagem como está. Se o contraste exigir ajuste, acrescente uma camada translúcida muito suave, preservando a textura da água. Não aplicar uma segunda camada pesada que transforme a região em um retângulo quase preto.

## 5. Composição e medidas de referência

As medidas abaixo são pontos de partida aproximados, não coordenadas absolutas para todos os dispositivos. A referência desktop tem proporção próxima de 1,60:1.

- Fotografia ocupando toda a largura e altura da seção, com proporção preservada.
- Barragem e paisagem livres na metade esquerda, sem sobreposição por cards.
- Coluna de texto começando aproximadamente aos 54% da largura, com largura aproximada de 39% e margem direita de 7%.
- Início do conteúdo a aproximadamente 13% da altura da referência.
- Título de peso regular ou médio, cerca de 56–62 px na escala de referência, entrelinha de 1,06–1,12 e espaçamento entre letras discreto.
- Texto de introdução aproximadamente 21–24 px nessa escala.
- Títulos dos benefícios aproximadamente 25–28 px; descrições aproximadamente 18–20 px nessa escala.
- Espaço maior entre introdução e benefícios; três blocos separados por linhas finas, com respiro antes e depois.
- CTA alinhado à esquerda da coluna de texto, abaixo do último benefício.

Adapte tamanhos com unidades fluidas e limites coerentes. Use o fluxo normal do documento para o conteúdo: apenas a imagem e sua camada visual podem ficar posicionadas atrás dele. Evite posicionar cada texto com coordenadas absolutas.

A altura deve acomodar o conteúdo. Não forçar `100vh` nem recortar os textos com altura fixa. Não usar `100vw` se isso gerar rolagem horizontal pela largura da barra de rolagem.

Use a fonte local mais próxima da referência. Mantenha peso regular ou médio, evitando o título extra-bold da versão antiga. Não adicionar carregamento externo de fonte ou biblioteca apenas para esta seção.

Paleta aproximada para os elementos HTML/CSS:

- título e títulos dos benefícios: branco suave, próximo de `#F5FAFD`;
- textos de apoio: azul muito claro, próximo de `#D9EAF3`;
- identificação superior: azul-claro próximo de `#8ADFF1`;
- CTA: azul-claro próximo de `#A4E8FA`, com texto azul-marinho próximo de `#062C4A`;
- divisores: branco com transparência baixa, ajustado visualmente sobre o fundo.

Compare as cores no navegador com o PNG. Estes valores são estimativas visuais, não tokens recuperados de um arquivo de design.

## 6. Conteúdo exato

Identificação superior:

> AQUAPULSE / VANTAGENS

Título principal da seção, em `h2`:

> Decisões mais seguras. Uma gestão mais consciente da água.

No desktop, buscar estas quebras da referência, sem forçá-las de forma a prejudicar telas menores:

```text
Decisões mais seguras.
Uma gestão mais
consciente da água.
```

Introdução:

> Informação para orientar a operação, apoiar equipes e cuidar de quem depende da represa.

Primeiro benefício, título em `h3`:

> Segurança para agir

Descrição:

> Antecipe mudanças e responda com mais clareza a situações críticas.

Segundo benefício, título em `h3`:

> Confiança para planejar

Descrição:

> Organize informações para orientar a operação e apoiar a gestão.

Terceiro benefício, título em `h3`:

> Responsabilidade para preservar

Descrição:

> Acompanhe decisões, fortaleça a governança e cuide dos recursos hídricos.

CTA:

> Solicitar demonstração

Acrescente uma pequena seta para a direita, conforme o sistema de ícones já existente ou um SVG simples. A seta é decorativa; o nome acessível do CTA deve continuar claro.

Não adicionar o slogan inferior da versão antiga, promessas de resultados, números de clientes ou métricas não fornecidas. Os três benefícios acima são o conteúdo aprovado para substituir os seis cards anteriores.

## 7. Botão e comportamento

O CTA tem formato retangular, cantos levemente arredondados, aproximadamente 3–5 px, sem sombra pesada. Na escala desktop da referência ele tem cerca de 290 px de largura e 54 px de altura; adapte ao texto e ao dispositivo.

Reutilize o destino funcional já existente para “Solicitar demonstração” no projeto. Se ele leva ao contato, mantenha o mesmo destino. Use link para navegação e botão apenas se a ação abrir um componente interativo existente.

Se não houver destino funcional, verifique se existe uma seção de contato e conecte o CTA a ela. Se nenhum contato ou destino tiver sido definido, registre essa pendência na entrega: não invente e-mail, WhatsApp, formulário com envio simulado ou link `#` sem função.

Hover discreto, foco visível e área de toque confortável. Preserve animações de entrada existentes quando forem compatíveis com esta seção; não criar carrossel, zoom contínuo, parallax, texto digitado ou dependência de JavaScript para tornar o conteúdo visível.

Se houver animação de entrada, ela deve ser curta, acontecer uma vez e respeitar `prefers-reduced-motion`. Os textos e o CTA devem continuar disponíveis quando JavaScript estiver desativado.

## 8. Responsividade e acessibilidade

No desktop, mantenha a assimetria da referência. Em larguras intermediárias, aumente a área disponível ao texto e reduza tipografia e espaçamentos com cuidado, sem espremer descrições em colunas estreitas.

No celular, use uma única coluna com margens laterais de aproximadamente 20–24 px. Mantenha os textos sobre a mesma fotografia contínua. Ajuste o recorte para mostrar uma parte reconhecível da barragem no topo ou no enquadramento; a adaptação pode ser diferente do desktop para preservar legibilidade.

Evite uma imagem com `cover` que, por causa de uma seção muito alta, elimine toda a barragem. Se necessário, use uma faixa fotográfica superior contínua com o restante do fundo ou um enquadramento próprio para mobile, sem criar um cartão separado. Não espelhar nem deformar a barragem.

Permita crescimento natural da seção e do botão. Nenhum texto deve ser cortado, sobrepor outro elemento ou exigir rolagem horizontal. As descrições devem continuar legíveis, normalmente a partir de 16 px em telas pequenas.

Use marcação semântica, `section` com nome acessível e hierarquia `h2`/`h3`. Preserve o `id` atual da seção se houver âncoras apontando para ela. Se a foto for decorativa, use fundo CSS ou `img` com `alt=""`; não anuncie conteúdo decorativo repetidamente a leitores de tela. Confira contraste dos textos e foco do CTA sobre as áreas reais da imagem.

## 9. Implementação e desempenho

Isole os seletores com o padrão do projeto, por exemplo `.aq-benefits`. Não alterar regras genéricas de `section`, `h2`, `button` ou `img` que atinjam o restante da landing.

Remova apenas o HTML antigo desta seção e o CSS/JS comprovadamente exclusivo dela que ficar sem uso. Mantenha estilos e ícones compartilhados que outras páginas utilizem.

Não duplicar a seção antiga e a nova. Não usar uma imagem remota nem carregar a referência completa no site publicado. Se houver ferramentas de otimização já disponíveis, crie uma versão WebP de boa qualidade do fundo, conservando o PNG fonte. Não adicionar um processo de build só para isso.

Reserve espaço para a seção e escolha o carregamento de uma imagem abaixo da dobra de forma a não competir com a hero. Não utilizar `fetchpriority="high"` nesta imagem por padrão. Caminhos devem funcionar no projeto aberto por subdiretório do XAMPP e no ambiente já utilizado para publicação.

## 10. Validação e entrega

Valide visualmente no navegador em 1536, 1440, 1024, 768 e 390 px de largura. Compare a composição desktop diretamente com `referencia-vantagens.png`, ajustando o que divergir de forma significativa.

Confirme:

- imagem contínua, barragem à esquerda e textos sobrepostos à direita no desktop;
- três benefícios, copy exata e CTA em HTML real;
- ausência dos seis cards antigos, molduras e ícones decorativos;
- fotografia carregando pelo caminho correto;
- nenhum corte de texto, sobreposição ou rolagem horizontal;
- CTA com destino correto e foco visível por teclado;
- conteúdo disponível sem JavaScript;
- hero, carrossel, rodapé e navegação preservados;
- nenhum erro novo de console ou requisição de asset com falha.

Para esta mudança visual, use as verificações existentes e capturas reais; não crie uma suíte de testes que apenas replique CSS. Faça lint dos arquivos PHP se forem alterados e se o executável estiver disponível.

Ao concluir, informe os arquivos alterados, o destino do CTA, como foi validado, capturas desktop/mobile e qualquer limitação concreta. Não declare validações que não conseguiu executar. Se não houver navegador disponível, informe isso e entregue a verificação que foi possível realizar.

Implemente e deixe o resultado pronto para revisão local. Este pedido não inclui commit, push ou deploy.
