---
projeto: Aquapulse
tipo: entrega
papel: Granmaster
responsavel: A definir
status: em-andamento
tags:
  - aquapulse
  - papel/granmaster
  - tipo/entrega
---

# Coordenação do backlog

Função responsável: [[granmaster]]

## O que é

O que ficou para depois, com o motivo registrado — separando **pendência** de
**decisão de escopo**.

## Frentes abertas

| # | Frente | Depende de |
| --- | --- | --- |
| 1 | Fechar a correção dos gráficos (adotar `guard()` em 11 telas, commitar) | — |
| 2 | Banco de dados (etapa 4) | modelagem |
| 3 | Endpoints de escrita (`POST` / `PATCH`) | frente 2 |
| 4 | Segurança antes de uso real (força bruta, CSRF, auditoria, papéis, HTTPS) | — |
| 5 | Landing: destino de "Solicitar demonstração" | definição de negócio |
| 6 | Correções de documentação | — |

## Fora de escopo por decisão

Não são pendências esquecidas: framework ou bundler, CDN para Chart.js e
Leaflet, dados variando a cada carregamento, e publicar `docs/`, `reference/`
e `validation/` no servidor.

## Evidência no projeto

- `docs/login-stage.md`, seção "O que ainda NÃO está pronto para produção" — 8 itens
- `docs/database-handoff.md`, "Pontos de atenção na migração" — 7 itens
- Avisos "modo demonstrativo" nas próprias telas (`pages/alerts.js`,
  `reports.js`, `settings.js`, `operation.js`)
- Comentários `TROCA FUTURA` em `Container.php`

## Situação

**Em andamento**, permanentemente — é o backlog vivo do projeto.

## Relacionadas

[[granmaster]] · [[tarefas]] · [[organizacao-em-etapas]] · [[status-atual]] · [[decisoes]]
