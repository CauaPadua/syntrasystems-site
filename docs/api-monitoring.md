# Contrato da API — Monitoramento (v1)

Documento compartilhado entre o front-end do sistema interno e o back-end.
Enquanto este contrato for respeitado, cada lado pode mudar de forma
independente — inclusive a troca da fonte de dados simulada por um banco.

- **Base:** `api/v1/` (caminho relativo à raiz do projeto)
- **Formato:** exclusivamente JSON
- **Cabeçalho de resposta:** `Content-Type: application/json; charset=utf-8`
- **Envelope:** `success` + `data` + `meta` no sucesso; `success: false` + `error` no erro
- **Fuso horário:** `America/Sao_Paulo`
- **Autenticação:** sessão PHP existente (a mesma do login). Sem sessão a
  resposta é **401 em JSON**, nunca HTML e nunca um redirecionamento.

> ⚠️ **Etapa temporária.** Os dados vêm de `MockMonitoringRepository`, que lê um
> arquivo PHP determinístico. Não há banco de dados. Veja
> [`database-handoff.md`](database-handoff.md) para a troca.

---

## Envelopes

**Sucesso**

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

`meta` sempre traz `source`, `generated_at` (ISO 8601 com fuso) e
`updated_label` (texto pronto, em português). Os parâmetros aceitos pelo
endpoint são ecoados em `meta` — é assim que o front-end confirma qual contexto
foi realmente atendido.

> `updated_label` é calculado **no servidor**. O relógio da demonstração é fixo
> (`Clock::DEMO_INSTANT`), então um cálculo no navegador diria "há 2 anos".
> Quando o banco entrar e o relógio voltar a ser o real, o campo continua
> válido — basta manter o cálculo no servidor.

**Erro**

```json
{
  "success": false,
  "data": null,
  "meta": [],
  "error": { "code": "CODIGO", "message": "Mensagem para exibição." }
}
```

| HTTP | Quando |
| --- | --- |
| `200` | Sucesso |
| `400` | Parâmetro obrigatório ausente ou fora da lista permitida |
| `401` | Sem sessão ativa |
| `404` | Empresa ou represa inexistente / sem dados para o contexto |
| `500` | Falha inesperada (mensagem genérica, sem detalhes internos) |

Nenhuma resposta expõe caminho de arquivo, *stack trace*, consulta ou dado de
sessão.

---

## Parâmetros e listas permitidas

Todo parâmetro é validado por **lista fechada**. Valor fora da lista é `400`;
identificador inexistente é `404`. Nada vindo do navegador é usado sem passar
por essa checagem.

| Parâmetro | Valores | Padrão |
| --- | --- | --- |
| `company_id` | `all` ou um id existente (`hidrovale`, `aguas-do-norte`) | `all` |
| `reservoir_id` | `all` ou um id existente (`santa-clara`, `rio-verde`, `serra-azul`) | depende do endpoint |
| `period` | `24h`, `7d`, `30d`, `90d`, `12m` | `24h` ou `7d` |
| `horizon` | `30d`, `60d`, `90d`, `180d` | `90d` |
| `severity` | `all`, `critical`, `attention`, `info` | `all` |
| `status` (alertas) | `all`, `new`, `analysis`, `resolved` | `all` |
| `status` (relatórios) | `all`, `done`, `processing`, `scheduled` | `all` |
| `type` | `all`, `operational`, `hydrological`, `quality`, `planning` | `all` |

As oito telas de Monitoramento exigem uma represa específica: `reservoir_id=all`
devolve `400 RESERVOIR_REQUIRED`.

---

## Endpoints de contexto

### `GET /api/v1/companies.php`

Empresas disponíveis para o seletor de contexto.

`data.companies[]`: `id`, `name`, `code`, `manager`, `status`, `status_label`,
`reservoir_count`.

### `GET /api/v1/reservoirs.php?company_id={id|all}`

Represas da empresa escolhida.

`data.reservoirs[]`: `id`, `code`, `name`, `company_id`, `city`, `basin`,
`status` (objeto com `key`, `label`, `icon`), `telemetry`, `capacity`.

---

## Visão geral

### `GET /api/v1/overview.php?company_id={id|all}&reservoir_id={id|all}&period={period}`

Um único endpoint atende os dois modos da tela. `data.mode` diz qual veio:

| `mode` | Quando | Blocos em `data` |
| --- | --- | --- |
| `consolidated` | `reservoir_id=all` | `kpis`, `comparison`, `flow_chart`, `donut`, `alert_counts`, `reservoirs`, `priority_alerts` |
| `single` | represa específica | `kpis`, `level_chart`, `flow_chart`, `location`, `recent_alerts`, `reports` |

---

## Monitoramento

Todos exigem `reservoir_id` específico. Todos devolvem `data.reservoir`
(cabeçalho da represa: `id`, `code`, `name`, `telemetry`, `status`).

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

**Séries.** Todo bloco de gráfico segue a mesma forma: `labels` (rótulos do eixo
X já formatados em pt-BR) e uma ou mais listas numéricas do mesmo tamanho. O
último ponto de cada série é exatamente o valor do KPI correspondente — gráfico
e cartão nunca se contradizem.

**Status.** Sempre um objeto `{ "key": "attention", "label": "Atenção",
"icon": "alert-triangle" }`. O `key` é o valor estável para lógica; `label` e
`icon` existem para que a interface nunca dependa só da cor.

---

## Demais telas

| Endpoint | Parâmetros | Blocos em `data` |
| --- | --- | --- |
| `alerts.php` | `company_id`, `reservoir_id`, `severity`, `status` | `alerts`, `counts`, `chart`, `channels` |
| `reports.php` | `company_id`, `reservoir_id`, `type`, `status` | `reports`, `summary`, `listed`, `scheduled_reports` |
| `map/reservoirs.php` | `company_id` | `markers`, `coordinates_note` |
| `settings.php` | — | `companies`, `settings` |

`markers[]` traz `id`, `name`, `city`, `basin`, `lat`, `lng`, `coordinates`,
`status`, `level`, `cota`, `flow`, `ph`, `rain`, `duration`, `updated_at`.

---

## Exemplos de erro

```
GET /api/v1/monitoring/flow.php?reservoir_id=all
400  {"success":false,"data":null,"meta":[],"error":{"code":"RESERVOIR_REQUIRED","message":"Selecione uma represa …"}}

GET /api/v1/monitoring/flow.php?reservoir_id=inexistente
404  {"success":false,…,"error":{"code":"INVALID_RESERVOIR","message":"A represa informada não existe."}}

GET /api/v1/monitoring/flow.php?reservoir_id=santa-clara&period=99x
400  {"success":false,…,"error":{"code":"INVALID_PERIOD","message":"O período informado não é válido. Use: 24h, 7d, 30d, 90d, 12m."}}

GET /api/v1/monitoring/duration.php?reservoir_id=santa-clara&horizon=999d
400  {"success":false,…,"error":{"code":"INVALID_HORIZON","message":"O horizonte informado não é válido. Use: 30d, 60d, 90d ou 180d."}}

GET /api/v1/reservoirs.php?company_id=fantasma
404  {"success":false,…,"error":{"code":"INVALID_COMPANY","message":"A empresa informada não existe."}}

GET /api/v1/overview.php   (sem sessão)
401  {"success":false,…,"error":{"code":"UNAUTHENTICATED","message":"Sessão não encontrada ou expirada. Faça login novamente."}}
```

---

## Consumo pelo front-end

`assets/js/api-client.js` (`AqApi`) concentra todas as chamadas:

- monta a URL a partir da profundidade da página, sem caminho absoluto;
- cancela requisições superadas com `AbortController` (uma por escopo), o que
  evita respostas antigas sobrescreverem as novas ao trocar filtro depressa;
- em `401`, redireciona para o login em vez de mostrar erro genérico;
- devolve sempre `{ data, meta }` já validado (o `success` do envelope é conferido no cliente e vira erro quando `false`).

Nenhum token, senha ou dado de sessão trafega pelo JavaScript. O contexto
guardado em `sessionStorage` (`aq.context`) tem apenas `company_id` e
`reservoir_id` — identificadores que já são públicos dentro do sistema.
