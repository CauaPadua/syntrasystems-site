# assets/ — o que o navegador baixa (FRONT-END)

Tudo aqui roda **no computador de quem acessa o site**, não no servidor.
São os arquivos que dão aparência e comportamento às telas.

```
assets/
├── css/       Aparência: cor, tamanho, espaçamento, posição
├── js/        Comportamento: cliques, gráficos, chamadas à API
├── images/    Fotografias e logotipos
└── vendor/    Bibliotecas de terceiros, baixadas para dentro do projeto
```

---

## css/ — a aparência

| Arquivo | Cuida de |
|---|---|
| `style.css` | Página pública e login. É o maior: tem as cores e fontes de todo o projeto. |
| `monitorar.css` | Só a seção "Por que monitorar" da home. |
| `login.css` | Só a tela de login. |
| `dashboard.css` | Todo o painel interno. |
| `dashboard-responsive.css` | Ajustes do painel em telas menores. |

**Por que separar?** Porque `style.css` é servido também para o login. Se tudo
estivesse num arquivo só, mexer no painel poderia estragar a home sem querer.

No topo de `style.css` existe um bloco `:root` com as **variáveis de cor e
medida** do projeto. Mudar uma cor ali muda em todas as telas de uma vez.

---

## js/ — o comportamento

### Scripts da página pública

| Arquivo | Faz |
|---|---|
| `main.js` | Menu mobile, rolagem suave, animação do título da capa |
| `monitorar.js` | O carrossel de fotografias da seção "Por que monitorar" |

### Scripts do painel

| Arquivo | Faz |
|---|---|
| `api-client.js` | **Fala com a API.** Todo pedido de dados passa por aqui. |
| `charts.js` | Cria e atualiza os gráficos (usa Chart.js) |
| `maps.js` | Cria o mapa (usa Leaflet) |
| `dashboard-shell.js` | Menu lateral, filtros do topo, estado geral do painel |
| `monitor-page.js` | Base comum das telas de monitoramento |
| `format.js` | Formata números e datas no padrão brasileiro |
| `filters.js` | Guarda a represa e o período escolhidos |
| `login.js` | Envia o formulário de login e trata a resposta |
| `pages/` | Um arquivo por tela do painel |

**A regra importante:** nenhum destes arquivos inventa um número. Eles pedem ao
back-end através de `api-client.js` e desenham o que voltar.

---

## vendor/ — bibliotecas de terceiros

| Pasta | Biblioteca | Para quê |
|---|---|---|
| `chartjs/` | Chart.js 4.4.1 | Desenhar os gráficos |
| `leaflet/` | Leaflet 1.9.4 | Desenhar o mapa |

Estão **baixadas dentro do projeto**, e não carregadas de um servidor externo
(CDN). Vantagens: o sistema funciona sem internet, e a versão nunca muda
sozinha por uma atualização de fora.

---

## images/

Fotografias e logotipos. O formato `.webp` aparece bastante porque comprime
melhor que `.jpg` mantendo a qualidade, deixando as páginas mais leves.
