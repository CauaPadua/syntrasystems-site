---
projeto: Aquapulse
tipo: entrega
papel: Frontend
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/frontend
  - tipo/entrega
---

# Componentes visuais

Função responsável: [[frontend]]

## O que é

Biblioteca de marcação reutilizável do sistema interno. Cada componente é uma
função PHP que devolve HTML — as telas montam a página combinando-os, sem
repetir estrutura.

## Evidência no projeto

- `dashboard/includes/components.php` — `aq_card_head()`, `aq_chart()`,
  `aq_states()`, `aq_badge()`, `aq_legend()`, KPI e tabela
- `includes/icons.php` (110 linhas) — biblioteca de ícones SVG de traço linear
- `assets/css/dashboard.css` — tokens e estilos dos componentes
- `aquapulse-kit-claude-sistema/design/design-tokens.css` — tokens de origem

## Regra de acessibilidade

`aq_badge()` sempre recebe rótulo textual além da cor. O status nunca é
comunicado só por cor — a API entrega `key` + `label` + `icon`
([[regras-de-situacao-operacional]]).

## Situação

Concluído.

## Relacionadas

[[frontend]] · [[shell-do-dashboard]] · [[estados-de-carregamento-e-erro]] · [[definicao-das-telas]]
