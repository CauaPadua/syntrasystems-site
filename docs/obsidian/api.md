---
projeto: Aquapulse
tipo: contrato
papel: Backend
responsavel: A definir
status: concluido
colaboradores:
  - Frontend
tags:
  - aquapulse
  - papel/backend
  - tipo/contrato
---

# API

O contrato entre [[frontend]] e [[backend]]. Enquanto ele for respeitado, cada
lado pode mudar de forma independente — inclusive a troca dos dados simulados
por um banco ([[banco-de-dados]]).

Índice em [[00-inicio]] · contratos completos em
[`docs/api-contract.md`](../api-contract.md) (autenticação) e
[`docs/api-monitoring.md`](../api-monitoring.md) (sistema).

---

## Fundamentos

- **Base:** `api/v1/` — caminho **relativo**, então funciona na raiz e em
  subpasta do XAMPP.
- **Formato:** exclusivamente JSON.
  `Content-Type: application/json; charset=utf-8`.
- **Fuso:** `America/Sao_Paulo`.
- **Autenticação:** sessão PHP (a mesma do login). Sem sessão, **401 em JSON**
  — nunca HTML, nunca redirecionamento.
- **Métodos:** só `GET` nos endpoints de leitura; `POST` em `login` e `logout`.
  **Não há endpoint de escrita** no sistema.

---

## Dois envelopes

O projeto tem dois formatos de resposta, por razões históricas: a autenticação
nasceu na etapa 2, o sistema na etapa 3.

**Autenticação** (`api/v1/auth/`) — `JsonResponse`

```json
{ "data": { } }
{ "error": { "code": "CODIGO", "message": "Mensagem para exibição." } }
```

Em `422`, `error` também traz `details` com uma mensagem por campo.

**Sistema** (demais endpoints) — `ApiResponse`

```json
{
  "success": true,
  "data": { },
  "meta": {
    "source": "mock",
    "generated_at": "2024-05-22T09:28:00-03:00",
    "updated_label": "há 2 min"
  }
}
```

```json
{
  "success": false,
  "data": null,
  "meta": [],
  "error": { "code": "CODIGO", "message": "Mensagem para exibição." }
}
```

`meta` sempre traz `source`, `generated_at` (ISO 8601 com fuso) e
`updated_label` (texto pronto, em português). Os parâmetros aceitos são
**ecoados em `meta`** — é assim que o front-end confirma qual contexto foi
realmente atendido.

> `updated_label` é calculado **no servidor** porque o relógio da demonstração
> é fixo (`Clock::DEMO_INSTANT`); um cálculo no navegador diria "há 2 anos".
> Quando o banco entrar e o relógio voltar a ser real, o campo continua válido.

---

## Os 18 endpoints

### Autenticação (3)

| Método | Endpoint | Papel |
| --- | --- | --- |
| `POST` | `auth/login.php` | autentica e cria a sessão |
| `POST` | `auth/logout.php` | encerra a sessão (**idempotente**: sem sessão também responde 200) |
| `GET` | `auth/me.php` | usuário da sessão atual |

`me.php` é a **única** forma de o front-end saber se existe sessão — o cookie é
`HttpOnly` e não pode ser lido por JavaScript.

Erros: `400 INVALID_CONTENT_TYPE`, `400 INVALID_JSON`,
`401 INVALID_CREDENTIALS`, `401 UNAUTHENTICATED`, `405 METHOD_NOT_ALLOWED`,
`422 VALIDATION_ERROR`, `500 INTERNAL_ERROR`.

### Contexto (2)

| Endpoint | Parâmetros | `data` |
| --- | --- | --- |
| `companies.php` | — | `companies[]` |
| `reservoirs.php` | `company_id` | `reservoirs[]` |

### Visão geral (1)

`overview.php` — `company_id`, `reservoir_id`, `period`

Um endpoint atende os dois modos da tela; `data.mode` diz qual veio:

| `mode` | Quando | Blocos em `data` |
| --- | --- | --- |
| `consolidated` | `reservoir_id=all` | `kpis`, `comparison`, `flow_chart`, `donut`, `alert_counts`, `reservoirs`, `priority_alerts` |
| `single` | represa específica | `kpis`, `level_chart`, `flow_chart`, `location`, `recent_alerts`, `reports` |

### Monitoramento (8)

Todos exigem `reservoir_id` **específico** e devolvem `data.reservoir`
(cabeçalho: `id`, `code`, `name`, `telemetry`, `status`).

| Endpoint | Parâmetros | Blocos em `data` |
| --- | --- | --- |
| `monitoring/flow.php` | `reservoir_id`, `period` | `kpis`, `realtime`, `condition`, `daily`, `sensors`, `readings` |
| `monitoring/level.php` | `reservoir_id`, `period` | `kpis`, `history`, `capacity`, `forecast`, `bands`, `readings` |
| `monitoring/ph.php` | `reservoir_id`, `period` | `kpis`, `variation`, `scale`, `daily`, `points`, `readings`, `note` |
| `monitoring/storage.php` | `reservoir_id`, `period` | `kpis`, `evolution`, `occupancy`, `balance`, `distribution`, `history`, `insight` |
| `monitoring/precipitation.php` | `reservoir_id`, `period` | `kpis`, `chart`, `current`, `basin`, `forecast`, `stations`, `warning` |
| `monitoring/duration.php` | `reservoir_id`, `horizon` | `kpis`, `projection`, `estimate`, `scenarios`, `factors`, `history`, `insight` |
| `monitoring/operation.php` | `reservoir_id` | `kpis`, `systems`, `availability`, `components`, `events`, `maintenances` |
| `monitoring/flow-comparison.php` | `reservoir_id`, `current`, `previous` | `periods`, `kpis`, `chart`, `diff_chart`, `in_out`, `summary`, `rows`, `insight` |

### Demais telas (4)

| Endpoint | Parâmetros | Blocos em `data` |
| --- | --- | --- |
| `alerts.php` | `company_id`, `reservoir_id`, `severity`, `status` | `alerts`, `counts`, `chart`, `channels` |
| `reports.php` | `company_id`, `reservoir_id`, `type`, `status` | `reports`, `summary`, `listed`, `scheduled_reports` |
| `map/reservoirs.php` | `company_id` | `markers`, `coordinates_note` |
| `settings.php` | — | `companies`, `settings` |

---

## Parâmetros e listas permitidas

Todo parâmetro passa por `Validator` e é validado por **lista fechada**. Valor
fora da lista é `400`; identificador inexistente é `404`.

| Parâmetro | Valores | Padrão |
| --- | --- | --- |
| `company_id` | `all` ou id existente (`hidrovale`, `aguas-do-norte`) | `all` |
| `reservoir_id` | `all` ou id existente (`santa-clara`, `rio-verde`, `serra-azul`) | depende do endpoint |
| `period` | `24h`, `7d`, `30d`, `90d`, `12m` | `24h` ou `7d` |
| `horizon` | `30d`, `60d`, `90d`, `180d` | `90d` |
| `severity` | `all`, `critical`, `attention`, `info` | `all` |
| `status` (alertas) | `all`, `new`, `analysis`, `resolved` | `all` |
| `status` (relatórios) | `all`, `done`, `processing`, `scheduled` | `all` |
| `type` | `all`, `operational`, `hydrological`, `quality`, `planning` | `all` |

---

## Códigos HTTP (sistema)

| HTTP | Quando |
| --- | --- |
| `200` | sucesso |
| `400` | parâmetro ausente ou fora da lista permitida |
| `401` | sem sessão ativa |
| `404` | empresa ou represa inexistente / sem dados para o contexto |
| `500` | falha inesperada (mensagem genérica) |

Exemplos reais:

```
GET monitoring/flow.php?reservoir_id=all
400  RESERVOIR_REQUIRED    "Selecione uma represa …"

GET monitoring/flow.php?reservoir_id=inexistente
404  INVALID_RESERVOIR     "A represa informada não existe."

GET monitoring/flow.php?reservoir_id=santa-clara&period=99x
400  INVALID_PERIOD        "O período informado não é válido. Use: 24h, 7d, 30d, 90d, 12m."

GET monitoring/duration.php?reservoir_id=santa-clara&horizon=999d
400  INVALID_HORIZON       "O horizonte informado não é válido. Use: 30d, 60d, 90d ou 180d."

GET reservoirs.php?company_id=fantasma
404  INVALID_COMPANY       "A empresa informada não existe."

GET overview.php            (sem sessão)
401  UNAUTHENTICATED       "Sessão não encontrada ou expirada. Faça login novamente."
```

**Nenhuma resposta expõe caminho de arquivo, *stack trace*, consulta ou dado de
sessão.**

---

## Formato das séries

Todo bloco de gráfico segue a mesma forma: `labels` (rótulos do eixo X já
formatados em pt-BR) e uma ou mais listas numéricas **do mesmo tamanho**.

**O último ponto de cada série é exatamente o valor do KPI correspondente** —
gráfico e cartão nunca se contradizem. Hoje isso é garantido por construção
pelo repositório simulado; com dados reais, precisa ser preservado
([[banco-de-dados]]).

**Status** é sempre um objeto, nunca uma cor:

```json
{ "key": "attention", "label": "Atenção", "icon": "alert-triangle" }
```

`key` é o valor estável para lógica; `label` e `icon` existem para que a
interface nunca dependa só da cor.

---

## Consumo pelo front-end

`assets/js/api-client.js` (`AqApi`) concentra todas as chamadas:

- monta a URL a partir da profundidade da página, sem caminho absoluto;
- **cancela requisições superadas** com `AbortController`, uma por escopo — o
  que evita respostas antigas sobrescreverem as novas ao trocar filtro
  depressa;
- em `401`, redireciona para `login.php?expired=1` em vez de mostrar erro
  genérico;
- confere o `success` do envelope e devolve sempre `{ data, meta }` validado;
- `abortAll()` cancela tudo o que estiver em voo ao sair da página (chamado no
  `pagehide`, junto com `AqCharts.destroyAll()`).

Escopos usados: `companies`, `reservoirs`, `overview`, `map` e `page` (as
telas detalhadas compartilham `page`).

**Nenhum token, senha ou dado de sessão trafega pelo JavaScript.** O contexto
guardado em `sessionStorage` (`aq.context`) tem apenas `company_id` e
`reservoir_id`.

---

## O que a API ainda não faz

Não existe nenhum endpoint de escrita. Assumir alerta, resolver alerta, salvar
configurações, gerar relatório e abrir chamado são **ações demonstrativas** que
vivem só na sessão do navegador. Quando o banco entrar, essas ações vão exigir
endpoints `POST` / `PATCH` — decisão consciente desta etapa ([[decisoes]],
[[tarefas]]).

---

Ver também: [[backend]] · [[frontend]] · [[banco-de-dados]] · [[status-atual]]

Função responsável: [[backend]] · consumida por [[frontend]] · [[equipe]] · [[endpoints-da-api]]
