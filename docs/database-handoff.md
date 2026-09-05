# Passagem para a equipe de banco de dados

Este documento é o guia de quem for substituir os dados simulados por um banco
real. **Nenhuma tela, JavaScript, endpoint ou contrato de API precisa mudar.**

---

## O que existe hoje

```
backend/src/Contracts/MonitoringRepositoryInterface.php   ← o contrato
backend/src/Repositories/Mock/MockMonitoringRepository.php ← implementação atual
backend/storage/mock/monitoring.php                        ← dados simulados
backend/src/Support/Container.php                          ← ponto único de troca
```

Services (`OverviewService`, `MonitoringService`, `DurationForecastService`) e
todos os endpoints dependem **apenas** da interface. Nenhum deles conhece o
arquivo simulado, nem o abre, nem sabe que ele existe.

---

## A troca, em três passos

**1. Criar a implementação**

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

**2. Trocar uma linha em `Container.php`**

```php
// de:
self::$monitoring = new MockMonitoringRepository();
// para:
self::$monitoring = new PdoMonitoringRepository($pdo);
```

**3. Apagar o que ficou obsoleto**

`MockMonitoringRepository.php` e `backend/storage/mock/monitoring.php`. Nada
mais referencia esses arquivos.

O mesmo vale para a autenticação, que já seguia o mesmo padrão desde a etapa do
login: `PdoUserRepository` implementando `UserRepositoryInterface`
(ver [`api-contract.md`](api-contract.md)).

---

## Métodos a implementar

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
nomes das colunas — a tradução coluna → chave é responsabilidade do repositório.

### Empresa

| Campo | Tipo | Observação |
| --- | --- | --- |
| `id` | string | identificador estável, usado em URL e filtros |
| `code` | string | código interno (ex.: `HVE-001`) |
| `name` | string | |
| `manager` | string | responsável |
| `status` | string | `active` / `inactive` |
| `status_label` | string | rótulo em português |

### Represa

| Campo | Tipo | Observação |
| --- | --- | --- |
| `id`, `code`, `name`, `company_id` | string | |
| `city`, `basin` | string | município e bacia |
| `lat`, `lng` | float | **hoje são demonstrativas** e devem vir do cadastro real |
| `coordinates_label` | string | coordenadas formatadas para exibição |
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

Uma linha por instante medido: `reservoir_id`, `metric`, `measured_at`, `value`.
`series()` agrega conforme o período; `readings()` devolve as últimas em ordem
decrescente.

### Alerta

`id`, `reservoir_id`, `severity` (`critical`/`attention`/`info`), `title`,
`metric`, `value`, `threshold`, `detected_at`, `owner`, `status`
(`new`/`analysis`/`resolved`), `timeline[]`.

### Relatório

`id`, `name`, `type` (`operational`/`hydrological`/`quality`/`planning`),
`reservoir_id`, `period`, `generated_at`, `owner`, `status`
(`done`/`processing`/`scheduled`), `icon`.

### Sensor, ponto de pH, estação pluviométrica

`id`, `reservoir_id`, `name`, `location`, `status`, e o valor lido mais recente.

### Evento operacional e manutenção

Evento: `at`, `component`, `event`, `priority`, `status`.
Manutenção: `date`, `equipment`, `type`, `priority`.

---

## Regras que ficam no back-end, não no banco

Estas decisões estão em `backend/src/Services/StatusRules.php` e devem
continuar centralizadas — o banco guarda os números, não a classificação:

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

1. **Formato das respostas.** O front-end espera exatamente as estruturas
   descritas em [`api-monitoring.md`](api-monitoring.md). Uma mudança de forma
   quebra as telas; uma mudança de origem dos dados, não.
2. **Último ponto da série = valor do KPI.** Hoje isso é garantido por
   construção. Com dados reais, garanta que o KPI seja lido da mesma fonte da
   série, para que gráfico e cartão não se contradigam.
3. **Relógio.** Trocar `Clock::DEMO_MODE` para `false`. O rótulo
   *"Atualizado há N min"* deve continuar sendo calculado no servidor.
4. **Coordenadas.** As atuais são demonstrativas (região de Rio Claro/SP).
5. **Previsão de duração.** `DurationForecastService` é um algoritmo
   demonstrativo isolado; substitua `estimateDays()` e `project()` pelo modelo
   real sem tocar no restante.
6. **Ações demonstrativas.** Assumir/resolver alerta, salvar configurações e
   gerar relatório hoje só valem para a sessão do navegador. Ao entrar o banco,
   estas ações passam a precisar de endpoints `POST`/`PATCH` — que ainda não
   existem, por decisão desta etapa.
7. **Validação.** As listas permitidas ficam em `Validator.php`. Se o banco
   trouxer novos períodos ou tipos, acrescente-os lá — nunca aceite um valor
   direto do navegador.
