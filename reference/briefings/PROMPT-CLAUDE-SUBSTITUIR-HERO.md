# Aquapulse — substituição completa da hero da landing page

Analise primeiro o projeto existente e a imagem de referência localizada em:

- `reference/hero/referencia-visual-hero-aquapulse.png` — composição visual desejada.
- `assets/images/aquapulse-hero-reservatorio.png` — fotografia limpa que deve ser usada como fundo.

Implemente somente a nova seção **hero** da landing page do Aquapulse. Substitua integralmente a hero antiga, mas preserve as demais seções, o login, o dashboard, o backend, as APIs e todos os comportamentos já existentes.

## Restrições técnicas obrigatórias

- O projeto usa PHP 8, HTML5, CSS3 e JavaScript puro.
- Não instalar React, Vue, Tailwind, npm, bundler ou qualquer framework.
- Não copiar o código React do `WordsStagger`; recriar o mesmo princípio em JavaScript puro.
- Reutilizar a estrutura e os componentes existentes quando isso não prejudicar a fidelidade visual.
- Não alterar autenticação, sessão, rotas, endpoints, banco ou dados simulados.
- Não usar CDN para a animação.
- O nome correto da marca é sempre **Aquapulse**, nunca Aqualpulse.
- Não inserir textos dentro da imagem de fundo; todo conteúdo deve continuar sendo HTML acessível.

## Resultado visual esperado

A hero deve ocupar praticamente toda a primeira dobra da tela, com aparência editorial, institucional e de alto padrão.

### Estrutura geral

- Fundo fotográfico cobrindo toda a hero (`background-size: cover`) e usando a imagem fornecida.
- Altura em desktop próxima de `100svh`, com mínimo de aproximadamente `760px`.
- Cantos externos arredondados entre `20px` e `24px`.
- Aplicar sobre a fotografia uma camada azul-marinho sutil para garantir contraste:
  - mais escura na parte inferior e no lado esquerdo;
  - moderada no topo, atrás da navegação;
  - mais transparente no centro e no céu para manter a fotografia viva.
- Evitar glassmorphism exagerado, brilho neon, partículas ou efeitos genéricos de IA.

### Navegação flutuante

Criar uma barra horizontal interna, afastada aproximadamente `24px` do topo e `56px` das laterais no desktop.

- Fundo azul-marinho translúcido, aproximadamente `rgba(4, 42, 82, 0.88)`.
- Borda azul discreta de 1px, sombra leve e `backdrop-filter: blur(12px)` como aprimoramento progressivo.
- Bordas arredondadas em torno de `14px`.
- À esquerda: logo Aquapulse já existente, em tamanho legível, sem recriar a marca por texto caso exista um arquivo oficial.
- Centro: links `Início`, `Sobre`, `Importância` e `Contato`.
- Direita: link `Entrar` e botão ciano `Conheça a solução ↗`.
- Manter estados de hover e foco claramente visíveis.
- Os links devem continuar apontando para as seções/rotas existentes.
- Em telas menores, usar o menu responsivo já existente ou criar um botão acessível com `aria-expanded`, sem permitir overflow horizontal.

### Conteúdo principal

No canto inferior esquerdo:

1. Selo em formato pill:
   - ponto circular ciano;
   - texto `MONITORAMENTO DE REPRESAS`;
   - fundo azul translúcido.
2. Título principal em branco, forte e com aproximadamente três linhas:

   `Cada gota importa.`  
   `Cada decisão`  
   `também.`

- Usar uma fonte sans-serif limpa já existente no projeto, com peso entre 600 e 700.
- Desktop: tamanho fluido próximo de `clamp(3.5rem, 5.4vw, 5.9rem)`.
- Entrelinha compacta, aproximadamente `0.96` a `1.02`.
- Limitar a largura para preservar as três linhas da referência.

No lado direito, aproximadamente na metade superior:

`Uma visão mais clara da água`  
`para apoiar decisões e cuidar`  
`do futuro.`

- Texto branco, médio, com largura controlada e boa leitura.
- Não transformar esse texto em cartão.

No canto inferior direito:

- Botão ciano grande `Conheça o Aquapulse ↗`.
- Deve navegar/rolar para a próxima seção relevante da landing page.
- Hover com pequena elevação e deslocamento da seta; foco de teclado visível.

## Animação de palavras — equivalente ao WordsStagger

O exemplo recebido usa um componente React do Spell UI:

```jsx
<WordsStagger>
  Spell UI is an open source collection...
</WordsStagger>
```

Como este projeto não usa React, implemente um equivalente leve em JavaScript puro apenas no título principal.

### Comportamento

- Cada palavra deve ser envolvida por um `span` gerado pelo JavaScript.
- Na entrada da página, revelar as palavras em sequência.
- Estado inicial: `opacity: 0`, `transform: translateY(22px)` e leve `filter: blur(5px)`.
- Estado final: `opacity: 1`, `transform: translateY(0)` e `filter: blur(0)`.
- Duração por palavra entre `550ms` e `700ms`.
- Intervalo escalonado de aproximadamente `65ms` entre palavras.
- Curva suave, semelhante a `cubic-bezier(.22, 1, .36, 1)`.
- O selo pode aparecer primeiro; depois o título; em seguida o texto lateral e os botões com fade/slide muito discreto.
- A animação deve ocorrer uma vez, sem loop e sem efeito de máquina de escrever.
- Não cortar palavras nem alterar espaços de forma perceptível.
- O conteúdo completo deve existir no HTML antes da animação para acessibilidade e fallback.
- Se o JavaScript falhar, todo o texto deve permanecer visível.
- Respeitar `prefers-reduced-motion: reduce`: nesse caso, exibir tudo imediatamente, sem transições.
- Evitar dependência de `IntersectionObserver` para o título acima da dobra; iniciar após `DOMContentLoaded` ou com a classe de carregamento já utilizada pelo projeto.

Uma estrutura possível é usar `data-words-stagger` no título e uma classe como `.is-visible` nos spans. Implemente de forma modular e com proteção para não inicializar duas vezes.

## Responsividade

### Desktop (`>= 1200px`)

- Preservar a composição da referência: título inferior esquerdo, texto no lado direito e CTA inferior direito.
- A barragem deve continuar visível no lado direito e as montanhas no centro.

### Tablet (`768px–1199px`)

- Reduzir o título e os espaçamentos.
- Reposicionar o texto lateral sem cobrir a barragem.
- A navegação pode recolher conforme o padrão existente.

### Mobile (`< 768px`)

- Hero com altura baseada no conteúdo, nunca conteúdo cortado.
- Título, texto e CTAs em uma única coluna.
- Usar `background-position` que mantenha água e barragem reconhecíveis; se necessário, ajustar por breakpoint.
- Botões largos, mas não obrigatoriamente 100% se isso piorar a composição.
- Sem rolagem horizontal em 375px.

## Acessibilidade e qualidade

- Um único `h1` na página.
- Links e botões com semântica correta.
- Contraste suficiente sobre a imagem.
- `aria-label` no controle do menu mobile, se houver.
- Foco visível em todos os elementos interativos.
- Não animar propriedades que provoquem layout shift; preferir `transform` e `opacity`.
- A imagem deve ter carregamento prioritário por ser LCP: se for `<img>`, usar `fetchpriority="high"`; se continuar como background CSS, fazer preload apropriado no `<head>`.
- Não usar `loading="lazy"` na imagem principal da hero.
- Manter CLS próximo de zero reservando a altura da seção.

## Organização esperada

- Salvar a fotografia em `assets/images/aquapulse-hero-reservatorio.png`.
- Manter estilos da hero no CSS existente da landing ou em um arquivo específico já previsto pela arquitetura; não criar folhas duplicadas.
- Manter o JavaScript da animação no módulo/arquivo da landing já existente, com nomes claros e sem poluir o escopo global.
- Remover CSS e JS que eram exclusivos da hero antiga somente depois de confirmar que não são usados em outras seções.

## Critérios de aceite

1. A hero antiga foi substituída e as demais áreas permanecem intactas.
2. A composição é visualmente fiel à referência enviada.
3. A fotografia limpa fornecida é usada, não a captura com textos.
4. Logo e nome exibem `Aquapulse` corretamente.
5. O título usa animação escalonada por palavra em JavaScript puro.
6. A animação não roda em loop e respeita movimento reduzido.
7. Todos os links e CTAs funcionam.
8. Não há erro no console, requisição 404, conteúdo cortado ou overflow horizontal.
9. Funciona no XAMPP e não depende de npm ou CDN.
10. Validar visualmente em 1536px, 1440px, 1024px, 768px e 375px.

Ao concluir, entregue:

- resumo objetivo do que foi alterado;
- lista exata dos arquivos modificados/criados;
- explicação breve de como a animação foi adaptada de React para JavaScript puro;
- resultado das validações nas cinco larguras;
- confirmação de que nenhuma outra tela ou camada do sistema foi alterada.
