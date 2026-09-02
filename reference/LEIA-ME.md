# Imagens da landing page Aqualpulse

Arquivos recriados a partir das quatro referências visuais, sem textos ou componentes da landing page incorporados às fotografias.

## Mapa de uso

| Arquivo | Seção sugerida | Aplicação |
| --- | --- | --- |
| `logo-aqualpulse.png` | cabeçalho | logotipo horizontal com fundo transparente |
| `hero-represa.webp` | hero | imagem principal, com o conteúdo textual posicionado à esquerda |
| `overlay-monitoramento.png` | hero | sobrepor à fotografia da hero com `position: absolute` e `pointer-events: none` |
| `onda-agua.png` | informações | decoração transparente no canto inferior esquerdo |
| `linhas-decorativas.png` | informações, sistema ou CTA | decoração transparente e discreta atrás do conteúdo |
| `dashboard-aqualpulse.png` | sistema | mockup principal do produto, com cantos arredondados e sombra suave |
| `vantagens-represa.webp` | vantagens | fotografia panorâmica da represa na parte superior/direita da seção |

## Orientações para o Claude Code

- Copiar os arquivos para `public/referencias/aqualpulse/` ou para a pasta pública equivalente do projeto.
- Implementar textos, botões, cartões, ícones e indicadores com HTML, CSS e componentes; não transformá-los em uma imagem única.
- Usar `object-fit: cover` nas duas fotografias e ajustar `object-position` conforme o ponto de quebra.
- Preservar o fundo transparente dos quatro PNGs: logotipo, overlay, linhas e onda.
- Na hero, aplicar um degradê branco muito suave somente pelo CSS no lado esquerdo para manter a leitura do título.
- Exibir o mockup do dashboard sem cortar a barra lateral ou os cartões superiores.
- Em telas pequenas, reduzir ou ocultar primeiro os elementos decorativos; nunca reduzir o texto até ficar ilegível.
- Fornecer textos alternativos úteis para as imagens de conteúdo. Os overlays puramente decorativos devem usar `alt=""`.

## Observação

Os ícones presentes nas referências não foram rasterizados. O ideal é usar uma biblioteca consistente de ícones em SVG, como Lucide, para preservar nitidez e acessibilidade.
