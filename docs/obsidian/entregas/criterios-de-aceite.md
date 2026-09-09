---
projeto: Aquapulse
tipo: entrega
papel: Granmaster
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/granmaster
  - tipo/entrega
---

# Critérios de aceite

Função responsável: [[granmaster]]
Origem: [[requisitos-do-sistema]] · Verificação: [[analista-de-qualidade]]

## O que é

A definição escrita de "pronto" da etapa 3 — o acordo que fecha o trabalho.

## Os 14 critérios

De `PROMPT-CLAUDE-CODE.md`, seção "critérios de aceite":

1. As 15 referências têm tela correspondente.
2. As duas versões da Visão geral funcionam pelo filtro.
3. O submenu Monitoramento abre e as oito opções navegam.
4. Todas as páginas detalhadas têm seletor de represa.
5. Filtros atualizam todos os componentes relacionados.
6. Gráficos funcionais e visualmente próximos às referências.
7. O mapa real mostra os marcadores simulados.
8. A API PHP fornece todos os dados simulados.
9. O dashboard está protegido pela sessão existente.
10. O código não depende de banco.
11. A camada mock está isolada e pronta para troca.
12. Landing e login continuam intactos.
13. Não há erros PHP, JavaScript, HTTP ou responsivos.
14. A documentação para front-end, back-end e banco está completa.

## Situação

Concluído — os 14 foram atendidos na entrega da etapa 3.

O critério 13 voltou a ser tensionado pelos defeitos de gráfico levantados
depois ([[erros-encontrados-nos-graficos]]): eles não produziam erro de
console, mas deixavam o canvas em branco.

## Relacionadas

[[granmaster]] · [[requisitos-do-sistema]] · [[analista-de-qualidade]] · [[validacao-das-telas]] · [[erros-encontrados-nos-graficos]]
