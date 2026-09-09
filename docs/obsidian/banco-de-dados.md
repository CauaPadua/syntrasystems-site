---
projeto: Aquapulse
tipo: nucleo
papel: Banco de Dados
responsavel: A definir
status: pendente
colaboradores:
  - Backend
  - Granmaster
tags:
  - aquapulse
  - papel/banco-de-dados
  - tipo/nucleo
---

# Banco de dados

**Não existe banco de dados em nenhuma etapa do projeto.** Esta nota descreve o
que alimenta o sistema hoje e o ponto já preparado para a troca.

Guia original em [`docs/database-handoff.md`](../database-handoff.md) ·
dados simulados em [`docs/mock-data.md`](../mock-data.md) · índice em
[[00-inicio]]

---

## O que existe hoje

```
backend/src/Contracts/MonitoringRepositoryInterface.php    ← o contrato
backend/src/Repositories/Mock/MockMonitoringRepository.php ← implementação atual
backend/storage/mock/monitoring.php                        ← dados simulados
backend/src/Support/Container.php                          ← ponto único de troca
```

E, desde a etapa do login:

```
backend/src/Repositories/UserRepositoryInterface.php
backend/src/Repositories/MockUserRepository.php
backend/storage/mock/users.php
```

`backend/` fica fora da pasta pública e é bloqueada por `.htaccess`: os
arquivos de dados não são acessíveis pelo navegador.

---

## Os dados simulados

`monitoring.php` é um `return` de array PHP puro (402 linhas):

| Chave | Conteúdo |
| --- | --- |
| `companies` | duas empresas (`hidrovale`, `aguas-do-norte`) |
| `reservoirs` | três represas com todos os valores âncora |
| `sensors` | sensores por represa (id, tipo, local, status) |
| `ph_points` | pontos de coleta de pH |
| `rain_stations` | estações pluviométricas |
| `alerts` | cinco ocorrências (uma já resolvida) |
| `reports` | oito relatórios em três status |
| `scheduled_reports` | quatro agendamentos |
| `operation_events` | eventos operacionais recentes |
| `maintenances` | manutenções programadas |
| `settings` | preferências, limites, indicadores e canais |

### Valores âncora

Todos os cartões, gráficos e tabelas derivam destes números:

| Represa | Nível | Cota | Volume | Capacidade | Vazão | pH | Chuva 24h | Duração | Situação |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Santa Clara (RSC-001) | 82,4 % | 562,4 m | 1.234 hm³ | 1.500 hm³ | 56,2 m³/s | 7,2 | 18,6 mm | 84 d | Atenção |
| Rio Verde (RRV-002) | 76,1 % | 548,9 m | 980 hm³ | 1.288 hm³ | 43,8 m³/s | 7,4 | 12,3 mm | 96 d | Normal |
| Serra Azul (RSA-003) | 77,3 % | 604,1 m | 740 hm³ | 957 hm³ | 32,5 m³/s | 7,3 | 8,7 mm | 91 d | Normal |

Consolidado: 3 monitoradas / 3 online, 2.954 hm³, 78,6 % de nível médio,
132,5 m³/s de vazão total, pH 7,3, 2 normais + 1 em atenção.

### Como as séries são geradas

O repositório **não guarda séries prontas**: ele as deriva com `wave()`, uma
função determinística semeada pelo `crc32` do id da represa e da métrica.

```php
$osc = sin(($i * 0.9) + $seed * 6.283) * $amplitude
     + sin(($i * 0.37) + $seed * 3.14) * ($amplitude * 0.45);
$osc *= (1 - $t * $t);                          // a oscilação some perto do fim
$values[$count - 1] = round($end, $decimals);   // crava o KPI atual
```

Três consequências:

1. **Nada é aleatório.** Sem `rand()`, `mt_rand()`, `shuffle()` ou `time()`. A
   mesma requisição devolve sempre a mesma resposta — apertar *Atualizar* não
   muda os números, como não mudaria com dados reais parados.
2. **Gráfico e cartão nunca se contradizem.** O último ponto de cada série é,
   por construção, o valor do KPI daquela métrica.
3. **A forma da curva é estável por represa.** Cada represa tem sua própria
   semente.

O usuário do login segue a mesma lógica: um registro fixo em `users.php`, com
**apenas o hash** gerado por `password_hash()`.

---

## Ponto preparado para a integração

A troca não é uma refatoração: é uma linha. Services e endpoints dependem
**apenas da interface** — nenhum deles conhece o arquivo simulado, nem o abre,
nem sabe que ele existe.

### Passo 1 — criar a implementação

```
backend/src/Repositories/Pdo/PdoMonitoringRepository.php
```

```php
namespace Aquapulse\Repositories\Pdo;

use Aquapulse\Contracts\MonitoringRepositoryInterface;

final class PdoMonitoringRepository implements MonitoringRepositoryInterface
{
    public function __construct(private \PDO $pdo) {}

    // implementar os 15 métodos da interface
}
```

### Passo 2 — trocar uma linha em `Container.php`

```php
// de:
self::$monitoring = new MockMonitoringRepository();
// para:
self::$monitoring = new PdoMonitoringRepository($pdo);
```

O mesmo vale para a autenticação: `PdoUserRepository` implementando
`UserRepositoryInterface`, trocado em `Container::users()`.

### Passo 3 — apagar o que ficou obsoleto

`MockMonitoringRepository.php`, `MockUserRepository.php` e
`backend/storage/mock/`. Nada mais referencia esses arquivos.

**Não mudam:** nenhuma tela, nenhum JavaScript, nenhum CSS, nenhum endpoint,
nenhum contrato de API, nem `AuthService`, `MonitoringService`,
`OverviewService` ou `StatusRules`.

---

## Os 15 métodos a implementar

| Método | Devolve |
| --- | --- |
| `companies()` | todas as empresas |
| `reservoirs(string $companyId = 'all')` | represas da empresa (ou todas) |
| `reservoir(string $id)` | uma represa, ou `null` |
| `series(string $id, string $metric, string $period)` | `['labels' => [...], 'values' => [...]]` |
| `readings(string $id, string $metric, int $limit = 5)` | últimas leituras |
| `sensors(string $id)` | sensores da represa |
| `phPoints(string $id)` | pontos de coleta de pH |
| `rainStations(string $id)` | estações pluviométricas |
| `alerts(string $id, string $severity, string $status)` | alertas filtrados |
| `reports(string $id, string $type, string $status)` | relatórios filtrados |
| `scheduledReports()` | agendamentos |
| `operationEvents(string $id)` | eventos operacionais |
| `maintenances(string $id)` | manutenções programadas |
| `settings()` | preferências, limites, indicadores e canais |

`metric` aceita `level`, `flow`, `inflow`, `outflow`, `ph`, `storage`,
`precipitation`. `period` aceita `24h`, `7d`, `30d`, `90d`, `12m`.

---

## Entidades e campos esperados

Os nomes abaixo são as **chaves do array devolvido**, não necessariamente os
nomes das colunas — a tradução coluna → chave é responsabilidade do
repositório.

### Empresa

`id`, `code` (ex.: `HVE-001`), `name`, `manager`, `status`
(`active` / `inactive`), `status_label`.

### Represa

| Campo | Tipo | Observação |
| --- | --- | --- |
| `id`, `code`, `name`, `company_id` | string | |
| `city`, `basin` | string | município e bacia |
| `lat`, `lng` | float | **hoje são demonstrativas** (região de Rio Claro/SP) |
| `coordinates_label` | string | coordenadas formatadas |
| `level_pct` | float | nível em % da capacidade |
| `cota_m` | float | cota atual em metros |
| `volume_hm3`, `capacity_hm3` | float | |
| `flow_m3s`, `inflow_m3s`, `outflow_m3s` | float | |
| `ph` | float | |
| `rain_24h_mm` | float | |
| `duration_days` | int | |
| `sensors_online`, `sensors_total` | int | |
| `gates_online`, `gates_total` | int | |
| `availability_pct`, `telemetry_pct`, `communication_pct`, `power_pct` | float | |
| `updated_at` | string | data/hora da última leitura |

### Leitura (série temporal)

Uma linha por instante medido: `reservoir_id`, `metric`, `measured_at`,
`value`. `series()` agrega conforme o período; `readings()` devolve as últimas
em ordem decrescente.

### Alerta

`id`, `reservoir_id`, `severity` (`critical` / `attention` / `info`), `title`,
`metric`, `value`, `threshold`, `detected_at`, `owner`, `status`
(`new` / `analysis` / `resolved`), `timeline[]`.

### Relatório

`id`, `name`, `type` (`operational` / `hydrological` / `quality` /
`planning`), `reservoir_id`, `period`, `generated_at`, `owner`, `status`
(`done` / `processing` / `scheduled`), `icon`.

### Sensor, ponto de pH, estação pluviométrica

`id`, `reservoir_id`, `name`, `location`, `status` e o valor lido mais recente.

### Evento operacional e manutenção

Evento: `at`, `component`, `event`, `priority`, `status`.
Manutenção: `date`, `equipment`, `type`, `priority`.

### Usuário

No mínimo `id`, `name`, `email`, `role`, `password_hash` — o mesmo formato de
array devolvido hoje por `MockUserRepository`.

---

## Regras que ficam no back-end, não no banco

O banco guarda os números; **a classificação continua em
`backend/src/Services/StatusRules.php`**:

```php
LEVEL_ATTENTION = 80;   // % da capacidade
LEVEL_CRITICAL  = 90;
PH_MIN = 6.5;
PH_MAX = 8.5;
```

`StatusRules::describe()` devolve sempre `key` + `label` + `icon`, para que a
interface nunca dependa apenas da cor.

---

## Pontos de atenção na migração

1. **Formato das respostas.** O front-end espera exatamente as estruturas de
   [[api]]. Mudar a *origem* dos dados não quebra nada; mudar a *forma*
   quebra as telas.
2. **Último ponto da série = valor do KPI.** Hoje é garantido por construção.
   Com dados reais, garanta que o KPI seja lido da mesma fonte da série.
3. **Relógio.** Trocar `Clock::DEMO_MODE` para `false`. O rótulo
   "Atualizado há N min" deve continuar sendo calculado no servidor.
4. **Coordenadas.** As atuais são demonstrativas e devem vir do cadastro real.
5. **Previsão de duração.** `DurationForecastService` é um algoritmo
   demonstrativo isolado; substitua `estimateDays()` e `project()` pelo modelo
   real sem tocar no restante.
6. **Ações demonstrativas.** Assumir/resolver alerta, salvar configurações,
   gerar relatório e abrir chamado hoje só valem para a sessão do navegador.
   Com o banco, passam a precisar de endpoints `POST` / `PATCH` — que ainda não
   existem, por decisão desta etapa.
7. **Validação.** As listas permitidas ficam em `Validator.php`. Se o banco
   trouxer novos períodos ou tipos, acrescente-os **lá** — nunca aceite um
   valor direto do navegador.

---

---

## Entregas desta função (4)

Núcleo de função · cor **#22C55E** · voltar para [[equipe]]

| Entrega | Situação |
| --- | --- |
| [[contrato-do-repositorio]] | concluída |
| [[planejamento-das-entidades]] | concluída |
| [[documentacao-de-integracao]] | concluída |
| [[ponto-de-substituicao]] | **pendente** — etapa 4 |

## Integrações reais

- O [[backend]] consome o [[contrato-do-repositorio]] e nada além dele.
- O [[repositorio-de-dados-simulados]] é a implementação a ser substituída.
- O handoff é coordenado por [[granmaster]] ([[integracao-entre-areas]]).

---

Ver também: [[equipe]] · [[backend]] · [[api]] · [[decisoes]] · [[tarefas]] · [[registro-de-entregas]]
