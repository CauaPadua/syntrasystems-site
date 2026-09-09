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

# Critérios operacionais

Função responsável: [[analista-de-negocios]]
Implementação: [[regras-de-situacao-operacional]]

## O que é

Os limites que separam operação normal de atenção e de situação crítica. São
regra de negócio, não detalhe técnico.

## Critérios definidos

| Grandeza | Faixa | Situação |
| --- | --- | --- |
| Nível | até 80 % | Normal |
| Nível | 80 % a 90 % | Atenção |
| Nível | acima de 90 % | Crítico |
| pH | 6,5 a 8,5 | dentro da faixa ideal |
| pH | fora dessa faixa | Atenção |

## Severidades e ciclo de vida

- Alertas: `critical`, `attention`, `info`; status `new`, `analysis`, `resolved`.
- Relatórios: tipos `operational`, `hydrological`, `quality`, `planning`;
  status `done`, `processing`, `scheduled`.

## Evidência no projeto

- `backend/src/Services/StatusRules.php` — constantes `LEVEL_ATTENTION = 80.0`,
  `LEVEL_CRITICAL = 90.0`, `PH_MIN = 6.5`, `PH_MAX = 8.5`, com o comentário
  "conforme especificação da etapa"
- `backend/src/Support/Validator.php` — as listas fechadas de severidade e status
- `aquapulse-kit-claude-sistema/PROMPT-CLAUDE-CODE.md` — seções por tela
- Referência visual `03-ph.png` — "faixa ideal de pH"

## Regra derivada

O status **nunca é comunicado só por cor**: a API entrega sempre `key`,
`label` e `icon`.

## Situação

Concluído.

## Relacionadas

[[analista-de-negocios]] · [[regras-de-situacao-operacional]] · [[indicadores-monitorados]] · [[validacao-de-requisicoes]] · [[banco-de-dados]]
