---
projeto: Aquapulse
tipo: entrega
papel: Analista de Qualidade
responsavel: A definir
status: em-andamento
tags:
  - aquapulse
  - papel/qualidade
  - tipo/entrega
---

# Registro de correções

Função responsável: [[analista-de-qualidade]]
Colaboração: [[frontend]] (implementação)

## O que é

O que foi efetivamente alterado para resolver os defeitos de
[[erros-encontrados-nos-graficos]].

## Correções aplicadas

| Área | Correção |
| --- | --- |
| Dependências | `falhaDependencia` sinaliza o cartão e registra no console quando Chart.js ou o plugin faltam |
| Validação de dados | `motivoInvalido()` recusa a configuração antes de desenhar e explica o motivo |
| Isolamento | `guard(id, fn)` e `scopeReady(escopo)` — a falha de um gráfico não derruba os seguintes |
| Ciclo de vida | `requestAnimationFrame` + `ResizeObserver` para canvas revelado com tamanho zero |
| Navegação | `pagehide` chama `AqApi.abortAll()` e `AqCharts.destroyAll()` |
| Cache | `aq_asset_version()` deriva o `?v=` do `mtime` dos assets |
| Escalas | eixo do comparativo mensal derivado dos dados, não fixo |
| Tolerância | `ultimoValido()` — projeção ausente vira "—" em vez de quebrar a tela |
| Estados | `data-content` + `aq_states` para os 8 gráficos que não tinham |
| Layout | alturas de gráfico ampliadas em desktop e mobile |

## Arquivos tocados

22 arquivos: 8 em `assets/js/`, 2 em `assets/css/`, 7 telas PHP,
`dashboard/includes/page.php` e 4 páginas de monitoramento.

## Situação

**Em andamento.** Duas pendências:

1. `guard()` adotado em apenas 2 das 13 telas com gráfico.
2. Nada commitado — enquanto não houver commit, o deploy automático não leva as
   correções ao ar.

## Relacionadas

[[analista-de-qualidade]] · [[erros-encontrados-nos-graficos]] · [[graficos-chartjs]] · [[estados-de-carregamento-e-erro]] · [[tarefas]]
