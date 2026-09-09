---
projeto: Aquapulse
tipo: entrega
papel: Analista de Negócios
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/negocios
  - tipo/entrega
---

# Indicadores monitorados

Função responsável: [[analista-de-negocios]]

## O que é

As grandezas que o sistema acompanha, e a unidade de cada uma.

## Indicadores

| Indicador | Unidade | Onde aparece |
| --- | --- | --- |
| Nível | % da capacidade | Visão geral, Nível, Níveis, Mapas |
| Cota | m | Nível, Níveis |
| Volume armazenado | hm³ | Visão geral, Volume |
| Capacidade | hm³ | Volume |
| Vazão | m³/s | Visão geral, Vazão, Comparativo |
| Afluência / defluência | m³/s | Vazão, Comparativo, Volume |
| pH | — | pH |
| Precipitação 24 h | mm | Precipitação |
| Duração da água | dias | Duração |
| Disponibilidade / telemetria / comunicação / energia | % | Operacional |
| Sensores e comportas online | contagem | Operacional |

## Evidência no projeto

- `backend/storage/mock/monitoring.php` — campos âncora por represa
  (`level_pct`, `cota_m`, `volume_hm3`, `capacity_hm3`, `flow_m3s`,
  `inflow_m3s`, `outflow_m3s`, `ph`, `rain_24h_mm`, `duration_days`,
  `availability_pct`, `telemetry_pct`, `communication_pct`, `power_pct`)
- `backend/src/Contracts/MonitoringRepositoryInterface.php` — `metric` aceita
  `level`, `flow`, `inflow`, `outflow`, `ph`, `storage`, `precipitation`
- Chave `settings` do arquivo de dados — indicadores e limites configuráveis
- `docs/database-handoff.md` — os mesmos campos, para o banco

## Situação

Concluído.

## Relacionadas

[[analista-de-negocios]] · [[criterios-operacionais]] · [[servicos-de-monitoramento]] · [[planejamento-das-entidades]] · [[telas-de-monitoramento]]
