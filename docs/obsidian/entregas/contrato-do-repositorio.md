---
projeto: Aquapulse
tipo: entrega
papel: Banco de Dados
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/banco-de-dados
  - tipo/entrega
---

# Contrato do repositório

Função responsável: [[banco-de-dados]]
Colaboração: [[backend]] (consumo)

## O que é

As interfaces de que services e endpoints dependem. **Nenhum deles conhece o
arquivo simulado, nem o abre, nem sabe que ele existe** — é isso que torna a
troca pelo banco uma alteração de uma linha.

## Evidência no projeto

- `backend/src/Contracts/MonitoringRepositoryInterface.php` — **15 métodos**
- `backend/src/Repositories/UserRepositoryInterface.php` — **2 métodos**
  (`findByEmail`, `findById`)

## Os 15 métodos

`companies()` · `reservoirs()` · `reservoir()` · `series()` · `readings()` ·
`sensors()` · `phPoints()` · `rainStations()` · `alerts()` · `reports()` ·
`scheduledReports()` · `operationEvents()` · `maintenances()` · `settings()`

`series()` devolve `['labels' => [...], 'values' => [...]]`. `metric` aceita
`level`, `flow`, `inflow`, `outflow`, `ph`, `storage`, `precipitation`;
`period` aceita `24h`, `7d`, `30d`, `90d`, `12m`.

## Situação

Concluído. O contrato está **pronto e estável** — falta apenas a implementação
sobre banco real.

## Relacionadas

[[banco-de-dados]] · [[ponto-de-substituicao]] · [[planejamento-das-entidades]] · [[repositorio-de-dados-simulados]] · [[backend]]
