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

# Shell do dashboard

Função responsável: [[frontend]]

## O que é

A moldura comum às 14 telas do sistema interno: sidebar, submenu de
Monitoramento, topo, menu do usuário, filtros de contexto e carregamento dos
scripts. Escrita uma única vez.

## Evidência no projeto

- `dashboard/includes/page.php` — `aq_page_start()`, `aq_page_end()`, `aq_nav()`
- `assets/js/dashboard-shell.js` (340 linhas) — `AqShell`
- `assets/js/filters.js` (106 linhas) — `AqContext`, contexto preservado entre telas
- `assets/css/dashboard.css` (1.190 linhas)

## Detalhes

- `aq_nav()` define a navegação como array, com submenu para as oito telas de
  Monitoramento.
- `AqContext` guarda em `sessionStorage` (`aq.context`) **apenas**
  `company_id` e `reservoir_id`.
- `aq_asset_version()` calcula o `?v=` dos assets pelo `mtime` mais recente de
  `assets/css` e `assets/js` — substituiu a constante fixa `2.0.0`.
- No `pagehide`, cancela requisições em voo e destrói os gráficos.

## Situação

Concluído. A função de versionamento por `mtime` faz parte da correção dos
gráficos ainda não commitada.

## Relacionadas

[[frontend]] · [[componentes-visuais]] · [[estados-de-carregamento-e-erro]] · [[registro-de-correcoes]] · [[arquitetura]]
