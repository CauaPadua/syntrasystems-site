---
projeto: Aquapulse
tipo: entrega
papel: Backend
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/backend
  - tipo/entrega
---

# Serviços de monitoramento

Função responsável: [[backend]]

## O que é

As regras de negócio que montam o payload de cada tela. Recebem o repositório
por interface e **não sabem de onde os dados vêm**.

## Evidência no projeto

| Classe | Linhas | Responsabilidade |
| --- | --- | --- |
| `MonitoringService` | 595 | `flow()`, `level()`, `ph()`, `storage()`, `precipitation()`, `operation()`, `flowComparison()` |
| `OverviewService` | 263 | `build()` → `single()` ou `consolidated()` |

Ambos em `backend/src/Services/`.

## Regra estrutural

Todo bloco de gráfico tem a mesma forma: `labels` e listas numéricas do mesmo
tamanho. **O último ponto de cada série é o valor do KPI** correspondente —
gráfico e cartão nunca se contradizem.

## Situação

Concluído. A regra acima precisa ser **preservada** quando o banco entrar.

## Relacionadas

[[backend]] · [[telas-de-monitoramento]] · [[regras-de-situacao-operacional]] · [[contrato-do-repositorio]] · [[api]]
