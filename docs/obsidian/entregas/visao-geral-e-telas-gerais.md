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

# Visão geral e telas gerais

Função responsável: [[frontend]]

## O que é

As seis telas fora do submenu de Monitoramento. A Visão geral atende **dois
modos no mesmo arquivo**: consolidado (todas as represas) e individual.

## Evidência no projeto

| Tela | Página | Script |
| --- | --- | --- |
| Visão geral | `dashboard/index.php` | `pages/overview.js` (411 linhas) |
| Relatórios | `dashboard/relatorios.php` | `pages/reports.js` (208) |
| Níveis | `dashboard/niveis.php` | `pages/levels.js` (321) |
| Mapas | `dashboard/mapas.php` | `pages/maps-page.js` (172) |
| Alertas | `dashboard/alertas.php` | `pages/alerts.js` (287) |
| Configurações | `dashboard/configuracoes.php` | `pages/settings.js` (272) |

- `validation/screenshots/dashboard/visao-geral-consolidada.png` e `visao-geral-represa.png`
- `validation/screenshots/dashboard/relatorios.png`, `niveis.png`, `mapas.png`, `alertas.png`, `configuracoes.png`

## Ações demonstrativas

Não persistem — valem só para a sessão do navegador, com aviso na própria tela:
assumir/resolver alerta (Alertas), gerar relatório (Relatórios), salvar
preferências e limites (Configurações), abrir chamado (Operacional).

Passam a exigir endpoints de escrita quando o banco entrar.

## Situação

Concluído, com as ações acima em modo demonstrativo por decisão de etapa.

## Relacionadas

[[frontend]] · [[endpoints-da-api]] · [[regras-de-selecao-de-empresa-e-represa]] · [[validacao-das-telas]] · [[tarefas]]
