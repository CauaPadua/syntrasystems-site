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

# Erros encontrados nos gráficos

Função responsável: [[analista-de-qualidade]]

## O que é

Levantamento dos defeitos que deixavam um gráfico em branco **sem explicar o
motivo** — título e legenda visíveis, canvas vazio, nenhuma pista na tela.

## Os 12 defeitos

| # | Defeito | Onde |
| --- | --- | --- |
| 1 | Exceção no meio do `render()` interrompia a montagem: todos os gráficos seguintes ficavam em branco | `monitor-page.js`, `overview.js` |
| 2 | Ausência do Chart.js ou do plugin de anotação passava em silêncio | `charts.js` |
| 3 | Dados inválidos (não numéricos, séries vazias, labels e valores de tamanhos diferentes) quebravam a biblioteca | `charts.js` |
| 4 | Canvas revelado dentro de contêiner oculto nascia com tamanho zero | `charts.js` |
| 5 | Versão de asset fixa em `2.0.0`: o navegador seguia executando JavaScript antigo contra a API nova | `dashboard/includes/page.php` |
| 6 | Comparativo mensal com eixo fixo em 550–570 m — em Rio Verde (548,9 m) e Serra Azul (604,1 m) as linhas caíam fora da área desenhada | `pages/levels.js` |
| 7 | `d.forecast.cota` ausente derrubava os três gráficos da tela de Níveis | `pages/levels.js` |
| 8 | Tendência de 7 dias exibia amostragem de 5 pontos com rótulos fixos | `pages/levels.js` |
| 9 | Resposta chegando após a navegação tentava desenhar em canvas removido | `dashboard-shell.js`, `api-client.js` |
| 10 | Registro duplicado do plugin de anotação a cada carga | `charts.js` |
| 11 | Oito gráficos sem escopo de estado: falhavam sem sinalizar nada | 7 arquivos PHP |
| 12 | Alturas de contêiner apertadas, espremendo eixos e legendas | `dashboard.css`, `dashboard-responsive.css` |

## Evidência

O diagnóstico está registrado nos comentários do próprio código corrigido — 22
arquivos modificados e ainda não commitados (+467 / −84). Os defeitos 6 e 7 são
verificáveis contra os valores âncora em `backend/storage/mock/monitoring.php`.

## Situação

**Em andamento.** Os 12 estão corrigidos, mas o trabalho ainda não foi
commitado e a adoção de `guard()` está incompleta.

## Relacionadas

[[analista-de-qualidade]] · [[registro-de-correcoes]] · [[graficos-chartjs]] · [[status-atual]] · [[tarefas]]
