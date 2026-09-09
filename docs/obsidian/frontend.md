---
projeto: Aquapulse
tipo: nucleo
papel: Frontend
responsavel: A definir
status: em-andamento
colaboradores:
  - Backend
  - Analista de Qualidade
tags:
  - aquapulse
  - papel/frontend
  - tipo/nucleo
---

# Front-end

Tudo o que chega ao navegador: landing page, login e as 14 telas do sistema
interno.

Camada oposta em [[backend]] · contrato entre as duas em [[api]] · índice em
[[00-inicio]]

---

## Regra de separação

O front-end **nunca** inclui um arquivo de `backend/`. Ele conversa com o
servidor apenas por `fetch` nos endpoints de `api/v1/`. Os arquivos `.php` do
front-end só montam HTML — nenhuma regra de negócio, autenticação ou acesso a
dados acontece neles.

Duas exceções de apoio, sem regra de negócio: `includes/config.php` (textos e
escape) e `includes/icons.php` (ícones SVG), reaproveitados pela landing e pelo
login.

---

## Landing page (etapa 1)

`index.php` monta o documento e inclui quatro seções:

| Arquivo | Seção | Linhas |
| --- | --- | --- |
| `includes/sections/hero.php` | Hero | 101 |
| `includes/sections/informacoes.php` | Por que monitorar represas | 42 |
| `includes/sections/sistema.php` | Como o Aquapulse apoia a operação | 49 |
| `includes/sections/vantagens.php` | Vantagens + chamada final | 67 |

Apoio: `includes/header.php` (54 linhas, com menu mobile) e
`includes/footer.php` (36 linhas).

**Conteúdo centralizado.** Os textos não ficam no HTML: estão em
`includes/config.php` como constantes — `AQ_SITE_NAME`, `AQ_NAV`,
`AQ_HERO_HIGHLIGHTS`, `AQ_HERO_BADGES`, `AQ_INFO_CARDS`, `AQ_SYSTEM_POINTS`.
Alterar a copy não exige mexer na estrutura das seções.

**Comportamento** — `assets/js/main.js` (214 linhas), JavaScript puro: menu
mobile acessível, estado do cabeçalho na rolagem, rolagem suave compensando o
cabeçalho fixo, avisos dos botões sem destino real e animações de entrada
(respeitando `prefers-reduced-motion`).

**Estilo** — `assets/css/style.css` (1.090 linhas), compartilhado com o login.

**Situação dos elementos:**

- **Entrar** (cabeçalho) → `login.php`, funcional.
- **Solicitar demonstração** (bloco final) → `button` com `data-demo-trigger`;
  exibe aviso de indisponibilidade, sem rota quebrada.
- O painel da seção "sistema" é **prévia ilustrativa em imagem**
  (`assets/images/dashboard-aquapulse.webp`), não o dashboard funcional.

A fonte Manrope vem do Google Fonts; as imagens são locais e otimizadas
(`.webp`).

---

## Login (etapa 2)

`login.php` (242 linhas) apenas renderiza a tela — inclusive
`meta robots noindex, nofollow`. Estilo próprio em `assets/css/login.css`
(569 linhas), carregado depois de `style.css`.

`assets/js/login.js` (318 linhas) é quem conversa com `api/v1/auth/`:

- valida os campos, controla estados e troca as vistas;
- bloqueia envio duplicado enquanto houver requisição em andamento;
- consulta `me.php` no carregamento — se já houver sessão, mostra direto o
  estado autenticado;
- **a senha nunca é armazenada, registrada em log ou reaproveitada**;
- **nada de sessão vai para `localStorage` / `sessionStorage`** — o cookie é
  `HttpOnly` e inacessível ao JavaScript.

Elementos apenas visuais nesta etapa: **"Lembrar de mim"** (não altera a
duração da sessão) e **"Esqueci minha senha"** (informa que virá depois). Ver
[[tarefas]].

---

## Sistema interno (etapa 3)

### Shell comum

`dashboard/includes/page.php` concentra o que se repete nas 14 telas:
`aq_page_start()` e `aq_page_end()` montam sidebar, topo, menu de usuário,
filtros de contexto e as tags de script. A navegação é um array em `aq_nav()`,
com submenu para as oito telas de Monitoramento.

`dashboard/includes/components.php` traz os componentes de marcação: KPI,
card, gráfico (`aq_chart`), tabela, badge (`aq_badge`) e os blocos de estado
(`aq_states`).

**Versão dos assets.** `aq_asset_version()` calcula a versão a partir do
`mtime` mais recente de `assets/css` e `assets/js`, e a acrescenta como `?v=`
nas URLs. Antes era a constante fixa `2.0.0` escrita à mão em dois lugares — o
que fazia o navegador seguir executando JavaScript antigo contra a API nova
depois de uma atualização. Era uma das causas de gráfico "vazio"
([[status-atual]]).

### As telas

Todas exigem sessão. O HTML entregue é sempre um **esqueleto**: nenhum dado
vem embutido, tudo chega depois por `fetch`.

| Grupo | Telas |
| --- | --- |
| Gerais | Visão geral (`index.php`), Relatórios, Níveis, Mapas, Alertas, Configurações |
| Monitoramento | Vazão, Nível, pH, Volume, Precipitação, Duração, Operacional, Comparativo |

A visão geral atende dois modos no mesmo arquivo: **consolidado** (todas as
represas) e **individual**. As oito telas de monitoramento exigem uma represa
específica.

### Módulos JavaScript

| Arquivo | Linhas | Papel |
| --- | --- | --- |
| `api-client.js` | 139 | `AqApi` — todas as chamadas à API, cancelamento e 401 |
| `charts.js` | 620 | `AqCharts` — Chart.js, escalas, linhas de limite, estados de falha |
| `dashboard-shell.js` | 340 | `AqShell` — submenu, sidebar, estados, toasts, atualização |
| `monitor-page.js` | 131 | `AqMonitorPage` — ciclo comum das oito telas de monitoramento |
| `filters.js` | 106 | `AqContext` — empresa/represa/período preservados entre telas |
| `format.js` | 80 | `AqFormat` — formatação pt-BR (a vírgula só existe na apresentação) |
| `maps.js` | 162 | `AqMap` — Leaflet + OpenStreetMap, sem chave paga |
| `pages/*.js` | 14 arquivos | uma por tela |

`AqContext` guarda em `sessionStorage` (`aq.context`) **apenas**
`company_id` e `reservoir_id` — identificadores já públicos dentro do sistema.
Nenhum token, senha ou dado de sessão.

### Estados de bloco

Cada bloco que depende de dados tem um **escopo** nomeado. No HTML:

```php
<div data-content="daily" hidden>  <!-- conteúdo real -->
  <?php echo aq_chart(['id' => 'grafico-media-diaria', ...]); ?>
</div>
<?php echo aq_states('daily'); ?>  <!-- loading / empty / error -->
```

No JavaScript: `AqShell.setState('daily', 'ready')`. O estado `error` traz
mensagem e botão "Tentar novamente" (`AqShell.onRetry`).

Desde a correção em andamento, **todo canvas do sistema está sob um escopo** —
antes, oito gráficos ficavam de fora e falhavam em silêncio.

### Gráficos

`AqCharts` embrulha o Chart.js. API pública: `create`, `guard`, `scopeReady`,
`destroy`, `destroyAll`, `fill`, `rgba`, `scales`, `tooltip`, `plugins`,
`line`, `bar`, `limitLine`, `band`, `donut`, `gauge`, `valueLabels`,
`describe`.

Dois pontos de acessibilidade: `describe()` gera uma descrição textual da série
(pontos, mínimo, máximo, valor atual) e as telas oferecem "Ver tabela" com os
mesmos dados do gráfico.

`guard(id, fn)` isola a falha de um gráfico para que ela não derrube os
seguintes. Hoje é usado só em `alerts.js` e `levels.js` — a adoção nas outras
11 telas está pendente ([[tarefas]]).

### Bibliotecas

Versionadas no repositório, **sem CDN**:

- `assets/vendor/chartjs/chart.umd.js` + `chartjs-plugin-annotation.min.js`
- `assets/vendor/leaflet/leaflet.js` + `leaflet.css` + ícones de marcador

Os tiles do mapa vêm do OpenStreetMap em tempo de execução.

### CSS

| Arquivo | Linhas | Escopo |
| --- | --- | --- |
| `style.css` | 1.090 | landing + login |
| `login.css` | 569 | login |
| `dashboard.css` | 1.190 | sistema interno (tokens e componentes) |
| `dashboard-responsive.css` | 160 | pontos de quebra do sistema |

`dashboard.css` é independente de `style.css`: o sistema interno não herda
nada da landing.

---

## Ações demonstrativas

Existem para mostrar o fluxo completo, mas **não persistem** — valem só para a
sessão do navegador, e a própria tela avisa:

| Tela | Ação |
| --- | --- |
| Alertas | "Assumir alerta", "Marcar como resolvido" |
| Relatórios | "Gerar novo relatório" (PDF pela impressão do navegador; CSV montado no cliente) |
| Configurações | preferências, limites, adicionar empresa/represa, salvar |
| Operacional | "Abrir chamado" |

Elas passam a exigir endpoints de escrita quando o banco entrar —
ver [[banco-de-dados]] e [[tarefas]].

---

---

## Entregas desta função (10)

Núcleo de função · cor **#00D9FF** · voltar para [[equipe]]

| Entrega | Situação |
| --- | --- |
| [[landing-page]] | concluída |
| [[tela-de-login]] | concluída |
| [[shell-do-dashboard]] | concluída |
| [[componentes-visuais]] | concluída |
| [[graficos-chartjs]] | **em andamento** |
| [[mapas-leaflet]] | concluída |
| [[estados-de-carregamento-e-erro]] | concluída |
| [[responsividade]] | concluída |
| [[telas-de-monitoramento]] | concluída |
| [[visao-geral-e-telas-gerais]] | concluída |

## Integrações reais

- Consome o contrato de [[api]], implementado pelo [[backend]].
- Requisitos e definição de telas vêm de [[analista-de-negocios]].
- Validação e defeitos registrados por [[analista-de-qualidade]].

---

Ver também: [[equipe]] · [[backend]] · [[api]] · [[status-atual]] · [[decisoes]] · [[registro-de-entregas]]
