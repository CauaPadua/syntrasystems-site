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

# Regras de seleção de empresa e represa

Função responsável: [[analista-de-negocios]]

## O que é

Como o contexto de análise se comporta: o que pode ser consolidado, o que exige
escolha e o que é preservado na navegação.

## Regras definidas

**Visão geral**

- Filtro de empresa aceita "Todas as empresas"; filtro de represa aceita
  "Todas as represas".
- Com "Todas as represas": dados consolidados e comparação entre as três.
- Com represa específica: somente os dados dela.
- Ao escolher uma empresa, listar apenas as represas dela.
- Se a represa selecionada não pertencer à nova empresa, redefinir a seleção de
  forma previsível.

**Telas de monitoramento**

- Cada tela tem o filtro visível "Represa analisada".
- Exigem represa específica — **não** consolidam métricas detalhadas.
- A última represa escolhida é preservada entre as oito páginas.
- Guardar apenas IDs de contexto; nunca dados sensíveis.
- Cancelar requisições anteriores ao trocar filtros depressa.

## Evidência no projeto

- `PROMPT-CLAUDE-CODE.md`, seção "comportamento dos filtros"
- `assets/js/filters.js` — `AqContext`, `requireReservoir()`, chave `aq.context`
- `assets/js/monitor-page.js` — "telas detalhadas nunca consolidam"
- `backend/src/Support/Validator.php` — `reservoirId()` com `allowAll`
- Erro `400 RESERVOIR_REQUIRED` documentado em `docs/api-monitoring.md`

## Situação

Concluído.

## Relacionadas

[[analista-de-negocios]] · [[telas-de-monitoramento]] · [[visao-geral-e-telas-gerais]] · [[validacao-de-requisicoes]] · [[api]]
