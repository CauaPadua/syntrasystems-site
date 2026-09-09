---
projeto: Aquapulse
tipo: nucleo
papel: Analista de Negócios
responsavel: A definir
status: concluido
colaboradores:
  - Frontend
  - Granmaster
tags:
  - aquapulse
  - papel/negocios
  - tipo/nucleo
---

# Analista de Negócios

Núcleo de função · cor **#FF4ECD** · voltar para [[equipe]]

## Escopo

Definir o que o sistema precisa fazer e por quê: requisitos, telas,
indicadores acompanhados, critérios operacionais e o comportamento esperado dos
filtros.

## Entregas (6)

| Entrega | Situação |
| --- | --- |
| [[requisitos-do-sistema]] | concluída — especificação de 743 linhas |
| [[definicao-das-telas]] | concluída — 15 telas mapeadas por referência |
| [[indicadores-monitorados]] | concluída |
| [[criterios-operacionais]] | concluída |
| [[regras-de-selecao-de-empresa-e-represa]] | concluída |
| [[conteudo-institucional]] | concluída, com uma definição pendente |

## Onde ficam os requisitos

```
aquapulse-kit-claude-sistema/PROMPT-CLAUDE-CODE.md    especificação (743 linhas)
aquapulse-kit-claude-sistema/MAPA-DE-REFERENCIAS.md   referência → tela esperada
aquapulse-kit-claude-sistema/references/dashboard/    15 referências visuais
includes/config.php                                   conteúdo institucional
```

## Regras de negócio que viraram código

| Regra | Onde vive |
| --- | --- |
| Nível: 80 % atenção, 90 % crítico | `StatusRules::LEVEL_ATTENTION` / `LEVEL_CRITICAL` |
| pH ideal entre 6,5 e 8,5 | `StatusRules::PH_MIN` / `PH_MAX` |
| Monitoramento exige represa específica | `Validator`, erro `RESERVOIR_REQUIRED` |
| Represa preservada entre telas | `AqContext` |
| Status nunca só por cor | `StatusRules::describe()` → `key` + `label` + `icon` |

## Pendência de negócio

"Solicitar demonstração" ainda não tem destino definido — hoje exibe aviso de
indisponibilidade. É decisão de negócio, não limitação técnica.

## Relacionadas

[[equipe]] · [[criterios-de-aceite]] · [[regras-de-situacao-operacional]] · [[frontend]] · [[tarefas]]
