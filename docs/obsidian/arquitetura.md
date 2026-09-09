---
projeto: Aquapulse
tipo: estrutural
papel: Granmaster
responsavel: A definir
status: concluido
colaboradores:
  - Frontend
  - Backend
  - Banco de Dados
tags:
  - aquapulse
  - papel/granmaster
  - tipo/estrutural
---

# Arquitetura

Como o Aquapulse está organizado e por onde uma requisição passa.

Situação de cada parte em [[status-atual]] · índice em [[00-inicio]]

---

## Stack

PHP puro (8.0+, extensões `session` e `json`), HTML semântico, CSS e JavaScript
sem framework. **Sem bundler, sem gerenciador de pacotes, sem dependência
baixada em tempo de execução.** Chart.js e Leaflet ficam versionados dentro do
repositório, em `assets/vendor/`.

---

## As três camadas

```
NAVEGADOR                         SERVIDOR PÚBLICO              SERVIDOR PRIVADO
┌──────────────────┐              ┌────────────────┐            ┌──────────────┐
│ index.php        │              │                │            │              │
│ login.php        │─── fetch ───▶│  api/v1/*.php  │──────────▶ │  backend/    │
│ dashboard/*.php  │   JSON       │  (pontos de    │            │  (regras +   │
│ assets/js/*      │◀─────────────│   entrada)     │◀────────── │   dados)     │
└──────────────────┘              └────────────────┘            └──────────────┘
     camada 1                          camada 2                    camada 3
```

**Regra que sustenta a separação:** o front-end **nunca** inclui um arquivo de
`backend/`. A única ponte entre os dois lados são os endpoints em `api/v1/`.

`backend/` fica fora do alcance do navegador — bloqueado por
`backend/.htaccess` (`Require all denied`). O arquivo de dados simulados não é
acessível por URL.

Detalhes de cada camada em [[frontend]] e [[backend]]; o contrato entre elas em
[[api]].

---

## Caminho de uma requisição

Exemplo: abrir a tela **Volume de vazão**.

1. `dashboard/monitoramento/vazao.php` chama `aq_page_start()`
   (`dashboard/includes/page.php`), que exige sessão via
   `Guard::requirePageSession()`. Sem sessão, redireciona para `login.php`.
2. A página renderiza o **esqueleto**: sidebar, filtros, cartões vazios, os
   três canvas e os blocos de estado (`aq_states`). Nenhum dado vem no HTML.
3. `assets/js/pages/flow.js` chama `AqMonitorPage`, que usa `AqApi.flow()`.
4. `assets/js/api-client.js` faz `GET api/v1/monitoring/flow.php?...` com
   `credentials: 'same-origin'`.
5. `api/v1/monitoring/flow.php` inclui `_boot.php`, que valida o método, exige
   sessão (`Guard::requireApiSession()`, 401 JSON sem ela) e devolve o
   repositório via `Container::monitoring()`.
6. `MonitoringService` monta o payload da tela a partir da interface
   `MonitoringRepositoryInterface`.
7. `ApiResponse` envelopa em `success` + `data` + `meta` e devolve JSON.
8. `flow.js` preenche os campos e `AqCharts` desenha os gráficos.

O ponto importante do passo 6: **nenhum service e nenhum endpoint sabe de onde
vêm os dados.** Todos dependem só da interface. É isso que torna a troca pelo
banco uma alteração de uma linha — ver [[banco-de-dados]].

---

## Estrutura de pastas

```
index.php                     landing page (etapa 1)
login.php                     tela de login (etapa 2)
CNAME                         domínio: syntrasystems.com.br

includes/                     apoio da landing e do login
  config.php                  textos, navegação e helpers de escape
  icons.php                   biblioteca de ícones SVG
  header.php  footer.php
  sections/                   hero, informacoes, sistema, vantagens

dashboard/                    SISTEMA INTERNO — 14 telas (etapa 3)
  index.php                   visão geral (consolidada e por represa)
  relatorios.php  niveis.php  mapas.php  alertas.php  configuracoes.php
  monitoramento/              as oito telas de monitoramento
  includes/
    page.php                  shell comum: sidebar, topo, scripts, versão dos assets
    components.php            KPI, card, gráfico, tabela, badge, estados

api/v1/                       PONTOS DE ENTRADA HTTP — 18 endpoints
  _boot.php                   método, sessão e container compartilhados
  auth/                       login, logout, me
  companies.php  reservoirs.php  overview.php
  settings.php  reports.php  alerts.php
  map/reservoirs.php
  monitoring/                 os oito endpoints de monitoramento

backend/                      REGRAS E DADOS — nunca chega ao navegador
  .htaccess                   Require all denied
  bootstrap.php               autoload, erros, sessão
  config/session.php          cookie e limites de expiração
  src/Auth/                   AuthService
  src/Contracts/              MonitoringRepositoryInterface  ← ponto de troca
  src/Http/                   Request, JsonResponse
  src/Repositories/           MockUserRepository + Mock/MockMonitoringRepository
  src/Services/               MonitoringService, OverviewService,
                              DurationForecastService, StatusRules
  src/Support/                Clock, Container, Guard, Validator, ApiResponse
  storage/mock/               monitoring.php, users.php  ← dados simulados

assets/
  css/style.css               landing e login
  css/login.css
  css/dashboard.css           sistema interno (tokens + componentes)
  css/dashboard-responsive.css
  js/                         cliente da API, gráficos, mapas, shell, telas
  vendor/                     Chart.js e Leaflet locais (sem CDN)
  images/

docs/                         contratos da API e guias de etapa
docs/obsidian/                esta documentação
reference/                    material de referência original (não alterado)
aquapulse-kit-claude-sistema/ prompt, referências visuais e tokens da etapa 3
validation/                   capturas e resultados dos testes
```

---

## Autenticação: um sistema só

Não existem dois mecanismos de login. O sistema interno reaproveita a sessão da
etapa 2, através de `backend/src/Support/Guard.php`:

| Contexto | Sem sessão |
| --- | --- |
| Página do dashboard | `Guard::requirePageSession()` → redireciona para `login.php?redirect=dashboard` |
| Endpoint da API | `Guard::requireApiSession()` → **401 em JSON**, nunca HTML, nunca redirecionamento |

Do lado do navegador, `api-client.js` reconhece o 401 e leva o usuário a
`login.php?expired=1`.

---

## Estados de tela

Todo bloco que depende de dados tem quatro estados, montados no HTML por
`aq_states($escopo)` e trocados por `AqShell.setState()`:

| Estado | O que aparece |
| --- | --- |
| `loading` | esqueletos animados |
| `ready` | o conteúdo real (`div` com `data-content`) |
| `empty` | "Sem dados para o período" |
| `error` | mensagem + botão "Tentar novamente" |

O conteúdo real nasce com `hidden` e só é revelado no estado `ready`. Isso
existe justamente para que uma falha de gráfico não deixe um retângulo branco
sem explicação — ver [[status-atual]].

---

## Publicação

`.github/workflows/deploy.yml`: todo `push` para `main` dispara deploy por FTP
para a InfinityFree (`SamKirkland/FTP-Deploy-Action@v4.3.5`, destino
`/htdocs/`).

Ficam **fora** do envio: `.git*`, `.github/`, `README.md`, `CNAME`,
`reference/`, `validation/` e `docs/` — ou seja, esta documentação é
versionada, mas não publicada no servidor.

---

Ver também: [[frontend]] · [[backend]] · [[api]] · [[banco-de-dados]] · [[decisoes]]

Função responsável: [[granmaster]] · [[equipe]] · [[arquitetura-em-camadas]]
