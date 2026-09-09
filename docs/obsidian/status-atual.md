---
projeto: Aquapulse
tipo: panorama
papel: Granmaster
responsavel: A definir
status: em-andamento
colaboradores:
  - Frontend
  - Backend
  - Banco de Dados
  - Analista de Qualidade
tags:
  - aquapulse
  - tipo/geral
---

# Status atual

Retrato do projeto em **08/09/2026**, levantado a partir do código e dos
documentos em `docs/`.

Mapa geral em [[arquitetura]] · índice em [[00-inicio]]

---

## Resumo por etapa

| Etapa | Entrega | Situação |
| --- | --- | --- |
| 1 | Landing page institucional (`index.php`) | Concluída |
| 2 | Login com autenticação PHP (`login.php` + `api/v1/auth/` + `backend/`) | Concluída, sobre repositório simulado |
| 3 | Sistema interno (`dashboard/` + `api/v1/`) — 14 telas, 18 endpoints | Concluída, sobre dados simulados |
| 4 | Banco de dados | **Não iniciada** — ponto de troca preparado |

Em andamento fora das etapas: **correção dos gráficos** do sistema interno
(alterações ainda não commitadas — ver adiante).

---

## Landing page

Concluída. Arquivo `index.php`, com quatro seções montadas por `require`:

| Seção | Arquivo |
| --- | --- |
| Hero | `includes/sections/hero.php` |
| Por que monitorar represas | `includes/sections/informacoes.php` |
| Como o Aquapulse apoia a operação | `includes/sections/sistema.php` |
| Vantagens + chamada final | `includes/sections/vantagens.php` |

Textos e navegação ficam centralizados em `includes/config.php` (constantes
`AQ_NAV`, `AQ_HERO_HIGHLIGHTS`, `AQ_INFO_CARDS`, `AQ_SYSTEM_POINTS` e outras),
não dentro do HTML.

Pontos em aberto na landing:

- O botão **Entrar** do cabeçalho já aponta para `login.php`
  (`includes/header.php:30`).
- O botão **Solicitar demonstração** do bloco final é um `button` com
  `data-demo-trigger` — exibe aviso de indisponibilidade, sem destino real
  (`includes/sections/vantagens.php:57`).
- O painel mostrado na seção "sistema" é **imagem estática**
  (`assets/images/dashboard-aquapulse.webp`), não o dashboard funcional.

Detalhes em [[frontend]].

---

## Login

Concluído e testado. Tela em `login.php` (242 linhas), lógica de tela em
`assets/js/login.js`, autenticação em `backend/src/Auth/AuthService.php`.

- Sessão PHP com cookie `AQUAPULSE_SESSION`, `HttpOnly`, `SameSite=Lax`.
- Inatividade máxima de 30 minutos; duração absoluta de 12 horas.
- Usuário único e fixo em `backend/storage/mock/users.php` — apenas o hash
  gerado por `password_hash()`, sem senha em texto puro.
- Resultados dos testes registrados em `validation/auth-test-results.txt`
  (PHP 8.0.30, todos os casos OK).

Credencial local: `demo@aquapulse.local` / `Aquapulse@123` (usuário
"Ana Silva", papel `admin`).

Ainda **não** existe: proteção contra força bruta, token CSRF, "lembrar de
mim" funcional, recuperação de senha, log de auditoria e autorização por
papel. Lista completa em [[tarefas]].

---

## Telas do dashboard

Quatorze telas, todas exigindo sessão (`Guard::requirePageSession()`).

**Gerais (6)**

| Tela | Arquivo | Gráficos |
| --- | --- | --- |
| Visão geral | `dashboard/index.php` | 4 |
| Relatórios | `dashboard/relatorios.php` | — |
| Níveis | `dashboard/niveis.php` | 3 |
| Mapas | `dashboard/mapas.php` | — (Leaflet) |
| Alertas | `dashboard/alertas.php` | 1 |
| Configurações | `dashboard/configuracoes.php` | — |

**Monitoramento (8)** — em `dashboard/monitoramento/`

| Tela | Arquivo | Gráficos |
| --- | --- | --- |
| Volume de vazão | `vazao.php` | 3 |
| Nível do reservatório | `nivel.php` | 2 |
| pH | `ph.php` | 3 |
| Volume armazenado | `volume.php` | 3 |
| Precipitação | `precipitacao.php` | 1 |
| Duração da água | `duracao.php` | 2 |
| Situação operacional | `operacional.php` | 1 |
| Comparativo de vazão | `comparativo.php` | 3 |

As oito telas de monitoramento exigem uma represa específica: `reservoir_id=all`
devolve `400 RESERVOIR_REQUIRED`. A visão geral aceita os dois modos.

---

## API

Dezoito endpoints em `api/v1/` — 3 de autenticação e 15 do sistema. Todos
respondem exclusivamente JSON. Contrato completo em [[api]].

---

## Dados simulados

Não há banco. Toda a fonte é `backend/storage/mock/monitoring.php` (402 linhas,
um `return` de array puro), lida por `MockMonitoringRepository`.

Duas empresas (`hidrovale`, `aguas-do-norte`) e três represas:

| Represa | Nível | Cota | Volume / Capacidade | Vazão | pH | Chuva 24h | Duração |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Santa Clara (RSC-001) | 82,4 % | 562,4 m | 1.234 / 1.500 hm³ | 56,2 m³/s | 7,2 | 18,6 mm | 84 d |
| Rio Verde (RRV-002) | 76,1 % | 548,9 m | 980 / 1.288 hm³ | 43,8 m³/s | 7,4 | 12,3 mm | 96 d |
| Serra Azul (RSA-003) | 77,3 % | 604,1 m | 740 / 957 hm³ | 32,5 m³/s | 7,3 | 8,7 mm | 91 d |

As séries dos gráficos **não são armazenadas**: são derivadas dos valores
âncora por uma função determinística (`wave()`), semeada pelo `crc32` do id da
represa e da métrica. O relógio é fixo em `2024-05-22 09:30:00`
(`Clock::DEMO_MODE = true`). Detalhes em [[banco-de-dados]] e em
[`docs/mock-data.md`](../mock-data.md).

---

## Correção dos gráficos — em andamento

Há **22 arquivos modificados e ainda não commitados** (+467 / −84 linhas). O
trabalho ataca falhas em que o gráfico ficava em branco sem explicar o motivo.

### Erros conhecidos nos gráficos

| # | Erro | Onde estava | Situação |
| --- | --- | --- | --- |
| 1 | Uma exceção no meio do `render()` interrompia a montagem e todos os gráficos seguintes da tela ficavam em branco, com título e legenda visíveis | `monitor-page.js`, `overview.js` | Corrigido — `try/catch` mais `G.scopeReady()` marcam só os cartões que ficaram sem gráfico |
| 2 | Ausência do Chart.js ou do plugin de anotação passava em silêncio: canvas branco, sem pista do motivo | `charts.js` | Corrigido — `falhaDependencia` sinaliza o cartão e registra no console |
| 3 | Dados inválidos (não numéricos, séries vazias, labels e valores de tamanhos diferentes) chegavam ao Chart.js e quebravam | `charts.js` | Corrigido — `motivoInvalido()` recusa antes de desenhar |
| 4 | Canvas revelado dentro de contêiner oculto nascia com tamanho zero e não se ajustava | `charts.js` | Corrigido — `requestAnimationFrame` mais `ResizeObserver` no contêiner |
| 5 | Assets versionados com a constante fixa `2.0.0`: após uma atualização, o navegador seguia executando JavaScript antigo contra a API nova — origem de gráficos "vazios" | `dashboard/includes/page.php` | Corrigido — `aq_asset_version()` usa o `mtime` mais recente de `assets/css` e `assets/js` |
| 6 | Comparativo mensal com eixo fixo em 550–570 m: em Rio Verde (548,9 m) e Serra Azul (604,1 m) as linhas caíam fora da área desenhada e o gráfico parecia vazio | `pages/levels.js` | Corrigido — eixo derivado dos próprios dados |
| 7 | `d.forecast.cota` ausente quebrava todo o `render()` e derrubava os três gráficos da tela de Níveis | `pages/levels.js` | Corrigido — `ultimoValido()`; o KPI passa a mostrar "—" |
| 8 | Tendência de 7 dias exibia uma amostragem de 5 pontos com rótulos fixos ("Hoje", "+2 dias"…) | `pages/levels.js` | Corrigido — usa os sete pontos e os rótulos vindos da API |
| 9 | Resposta que chegava depois da navegação tentava desenhar em canvas já removido | `dashboard-shell.js`, `api-client.js` | Corrigido — `pagehide` chama `AqApi.abortAll()` e `AqCharts.destroyAll()` |
| 10 | Registro duplicado do plugin de anotação a cada carga | `charts.js` | Corrigido — verifica `Chart.registry.plugins.items.annotation` |
| 11 | Gráficos sem estado próprio: alertas (`chart`), vazão (`daily`), nível (`forecast`), pH (`daily`), volume (`balance`), comparativo (`inout`) e níveis (`trend`, `monthly`) não tinham `data-content` nem `aq_states` | 7 arquivos PHP | Corrigido — todo canvas do sistema está sob um escopo de estado |
| 12 | Alturas de contêiner apertadas demais, espremendo eixos e legendas | `dashboard.css`, `dashboard-responsive.css` | Corrigido — `sm` 170→220 px, `md` 215→250, `lg` 255→300, `xl` 300→340 |

### O que ainda falta nessa frente

`AqCharts.guard()` foi criado para isolar a falha de cada gráfico, mas só é
usado em **2 das 13 telas com gráfico**: `alerts.js` (1 uso) e `levels.js`
(3 usos). As outras 11 continuam chamando `G.create()` direto. Ver [[tarefas]].

---

## O que não existe

- Banco de dados (nenhuma etapa).
- Endpoints de escrita (`POST` / `PATCH`) — assumir alerta, resolver alerta,
  salvar configurações, gerar relatório e abrir chamado valem só para a sessão
  do navegador.
- Framework, bundler ou dependência externa em tempo de execução: Chart.js e
  Leaflet estão versionados em `assets/vendor/`.

---

Ver também: [[frontend]] · [[backend]] · [[api]] · [[decisoes]] · [[tarefas]]

Mapa da equipe: [[equipe]] · [[registro-de-entregas]] · [[legenda-do-grafico]]
