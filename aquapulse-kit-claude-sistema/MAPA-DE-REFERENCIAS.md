# mapa de referências e ativos

## telas

| referência | implementação esperada |
| --- | --- |
| `references/dashboard/visao-geral/01-visao-geral-represa-selecionada.png` | visão geral de uma represa selecionada |
| `references/dashboard/visao-geral/02-visao-geral-todas-as-represas.png` | visão consolidada com todas as represas |
| `references/dashboard/monitoramento/01-volume-de-vazao.png` | monitoramento de afluência e defluência |
| `references/dashboard/monitoramento/02-nivel-do-reservatorio.png` | nível, cota, capacidade e tendência |
| `references/dashboard/monitoramento/03-ph.png` | qualidade da água e faixa ideal de pH |
| `references/dashboard/monitoramento/04-volume-armazenado.png` | volume armazenado e balanço hídrico |
| `references/dashboard/monitoramento/05-precipitacao.png` | precipitação observada e prevista |
| `references/dashboard/monitoramento/06-previsao-duracao-agua.png` | previsão de duração em diferentes cenários |
| `references/dashboard/monitoramento/07-situacao-operacional.png` | disponibilidade dos componentes e manutenção |
| `references/dashboard/monitoramento/08-comparativo-vazao.png` | comparação da vazão entre períodos |
| `references/dashboard/demais-telas/03-relatorios.png` | relatórios, filtros e histórico |
| `references/dashboard/demais-telas/04-niveis.png` | visão ampla dos níveis e limites |
| `references/dashboard/demais-telas/05-mapas.png` | localização interativa das represas |
| `references/dashboard/demais-telas/06-alertas.png` | central de alertas e eventos |
| `references/dashboard/demais-telas/07-configuracoes.png` | empresas, represas, indicadores e limites |

## ativo reaproveitável

- `assets/brand/logo-aquapulse.png`: logotipo correto com transparência. Nunca usar a versão escrita `Aqualpulse`.

## elementos que devem ser recriados em código

- gráficos: Chart.js;
- linhas de limite nos gráficos: chartjs-plugin-annotation ou plugin próprio;
- mapa: Leaflet com OpenStreetMap;
- ícones: SVGs Lucide, preferencialmente integrados ao `includes/icons.php` existente;
- cards, tabelas, badges, filtros e menus: HTML e CSS;
- indicadores circulares e semicirculares: Chart.js ou SVG/CSS acessível.

Não recortar gráficos, mapas, ícones ou componentes das capturas. As imagens completas são referências visuais e não devem aparecer na interface final.
