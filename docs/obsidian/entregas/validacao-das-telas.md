---
projeto: Aquapulse
tipo: entrega
papel: Analista de Qualidade
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/qualidade
  - tipo/entrega
---

# Validação das telas

Função responsável: [[analista-de-qualidade]]

## O que é

Conferência visual de cada rota do dashboard contra a referência
correspondente, com captura registrada no repositório.

## Evidência no projeto

15 capturas em `validation/screenshots/dashboard/`:

| Captura | Tela |
| --- | --- |
| `visao-geral-consolidada.png`, `visao-geral-represa.png` | os dois modos da Visão geral |
| `monitoramento-vazao.png` … `monitoramento-comparativo.png` | as 8 telas de monitoramento |
| `relatorios.png`, `niveis.png`, `mapas.png`, `alertas.png`, `configuracoes.png` | demais telas |

Referências de comparação em
`aquapulse-kit-claude-sistema/references/dashboard/` (15 imagens) e mapa em
`MAPA-DE-REFERENCIAS.md`.

## Critério aplicado

De `PROMPT-CLAUDE-CODE.md`, seção "validação visual obrigatória": comparar lado
a lado, e não considerar concluído se houver elementos cortados, desalinhados,
sobrepostos ou ilegíveis.

## Situação

Concluída — as 15 referências têm tela correspondente e captura registrada.

## Relacionadas

[[analista-de-qualidade]] · [[definicao-das-telas]] · [[telas-de-monitoramento]] · [[visao-geral-e-telas-gerais]] · [[testes-de-responsividade]]
