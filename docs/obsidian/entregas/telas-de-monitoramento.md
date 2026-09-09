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

# Telas de monitoramento

Função responsável: [[frontend]]

## O que é

As oito telas detalhadas, todas com o mesmo ciclo: carregar represas, exigir
uma represa específica, aplicar o período e recarregar ao trocar filtros. A
lógica comum vive em um único arquivo.

## Evidência no projeto

| Tela | Página | Script | Gráficos |
| --- | --- | --- | --- |
| Volume de vazão | `monitoramento/vazao.php` | `pages/flow.js` | 3 |
| Nível do reservatório | `monitoramento/nivel.php` | `pages/level.js` | 2 |
| pH | `monitoramento/ph.php` | `pages/ph.js` | 3 |
| Volume armazenado | `monitoramento/volume.php` | `pages/storage.js` | 3 |
| Precipitação | `monitoramento/precipitacao.php` | `pages/rain.js` | 1 |
| Duração da água | `monitoramento/duracao.php` | `pages/duration.js` | 2 |
| Situação operacional | `monitoramento/operacional.php` | `pages/operation.js` | 1 |
| Comparativo de vazão | `monitoramento/comparativo.php` | `pages/comparison.js` | 3 |

- `assets/js/monitor-page.js` (131 linhas) — `AqMonitorPage`, o ciclo comum
- `validation/screenshots/dashboard/monitoramento-*.png` — 8 capturas
- Referências visuais em `aquapulse-kit-claude-sistema/references/dashboard/monitoramento/`

## Regra

Todas exigem represa específica: `reservoir_id=all` devolve
`400 RESERVOIR_REQUIRED`. A represa escolhida é preservada na navegação entre
as oito páginas.

## Situação

Concluído.

## Relacionadas

[[frontend]] · [[regras-de-selecao-de-empresa-e-represa]] · [[servicos-de-monitoramento]] · [[api]] · [[validacao-das-telas]]
