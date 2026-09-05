# kit para iniciar o sistema Aquapulse

Este pacote reúne o prompt completo, as 15 referências visuais do dashboard, o logotipo correto e os tokens visuais básicos.

## como colocar no projeto

1. Extraia o pacote.
2. Copie `references/dashboard` para a pasta `references/dashboard` do projeto Aquapulse.
3. Copie `assets/brand/logo-aquapulse.png` apenas se o projeto ainda não possuir esse arquivo correto.
4. Coloque `PROMPT-CLAUDE-CODE.md`, `MAPA-DE-REFERENCIAS.md` e a pasta `design` na raiz temporariamente para o Claude Code conseguir lê-los.
5. Envie o conteúdo de `PROMPT-CLAUDE-CODE.md` ao Claude Code.

As imagens da pasta `references` não devem ser movidas para a pasta pública nem utilizadas como fundos das páginas. Depois da implementação e validação, elas podem continuar no projeto como documentação visual ou ser mantidas fora do repositório, conforme a decisão da equipe.

## o que foi extraído

- 15 capturas completas organizadas por módulo;
- logotipo transparente com a escrita correta `Aquapulse`;
- mapa das referências para cada página;
- tokens iniciais de cor, espaçamento e dimensões;
- prompt de implementação e contrato provisório da API.

Ícones, gráficos, mapas e componentes não foram rasterizados. O prompt orienta a recriá-los com SVG, Chart.js, Leaflet, HTML e CSS para que sejam funcionais e possam receber os dados reais quando o banco for conectado.
