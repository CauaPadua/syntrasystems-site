---
projeto: Aquapulse
tipo: entrega
papel: Frontend
responsavel: A definir
status: em-andamento
tags:
  - aquapulse
  - papel/frontend
  - tipo/entrega
---

# Gráficos (Chart.js)

Função responsável: [[frontend]]

## O que é

Camada própria sobre o Chart.js, com escalas, linhas de limite, faixas,
medidores e descrição textual das séries. Vinte e três canvas espalhados por
13 telas.

## Evidência no projeto

- `assets/js/charts.js` (620 linhas) — `AqCharts`
- `assets/vendor/chartjs/chart.umd.js` e `chartjs-plugin-annotation.min.js`
  (versionados, sem CDN)
- 14 arquivos em `assets/js/pages/`

## API pública

`create`, `guard`, `scopeReady`, `destroy`, `destroyAll`, `fill`, `rgba`,
`scales`, `tooltip`, `plugins`, `line`, `bar`, `limitLine`, `band`, `donut`,
`gauge`, `valueLabels`, `describe`.

`describe()` gera a alternativa textual da série (pontos, mínimo, máximo,
valor atual); as telas ainda oferecem "Ver tabela" com os mesmos dados.

## Situação

**Em andamento.** Doze defeitos já corrigidos, mas as alterações ainda não
foram commitadas, e `guard()` só está adotado em 2 das 13 telas com gráfico.

Ver [[erros-encontrados-nos-graficos]] e [[registro-de-correcoes]].

## Relacionadas

[[frontend]] · [[erros-encontrados-nos-graficos]] · [[registro-de-correcoes]] · [[estados-de-carregamento-e-erro]] · [[tarefas]]
