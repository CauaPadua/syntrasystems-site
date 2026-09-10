# dashboard/ — as telas do painel interno (FRONT-END)

Cada arquivo `.php` aqui é **uma tela** do sistema, protegida por login.

Estas telas são de front-end: montam a estrutura HTML **vazia**. Os números
chegam depois, por JavaScript, através da API. Por isso ao abrir o painel os
gráficos aparecem em branco por um instante antes de preencherem.

---

## As telas

| Arquivo | Tela |
|---|---|
| `index.php` | Visão geral (a inicial do painel) |
| `niveis.php` | Níveis de todas as represas |
| `alertas.php` | Alertas ativos |
| `mapas.php` | Mapa das represas |
| `relatorios.php` | Relatórios |
| `configuracoes.php` | Configurações |

### Monitoramento (submenu)

| Arquivo | Tela |
|---|---|
| `monitoramento/nivel.php` | Nível do reservatório |
| `monitoramento/vazao.php` | Vazão |
| `monitoramento/volume.php` | Volume armazenado |
| `monitoramento/ph.php` | pH da água |
| `monitoramento/precipitacao.php` | Precipitação |
| `monitoramento/comparativo.php` | Comparativo entre períodos |
| `monitoramento/duracao.php` | Projeção de duração |
| `monitoramento/operacional.php` | Situação operacional |

---

## `includes/` — a moldura comum

| Arquivo | O que faz |
|---|---|
| `page.php` | O "molde" de toda tela: cabeçalho, menu lateral, filtros e rodapé |
| `components.php` | Peças reaproveitadas: cartão de KPI, caixa de gráfico, tabela |

Sem isso, o menu lateral estaria copiado em 14 arquivos e mudar um item exigiria
editar os 14. Com o molde, muda em um lugar só.

---

## Duas coisas para saber explicar

### `AQ_DEPTH`

No topo de cada tela existe uma linha assim:

```php
define('AQ_DEPTH', 1);
```

Ela diz **quantas pastas a tela está abaixo da raiz** do projeto. As telas de
`monitoramento/` usam `2`. Serve para montar os caminhos de CSS, JS e API
corretamente de qualquer profundidade, sem caminho absoluto travado.

### Cada tela tem seu script

A tela `dashboard/index.php` carrega `assets/js/pages/overview.js`,
`niveis.php` carrega `pages/levels.js`, e assim por diante. Um arquivo de
JavaScript por tela, com o nome correspondente.

Esse script é quem pede os dados à API e manda desenhar os gráficos.
