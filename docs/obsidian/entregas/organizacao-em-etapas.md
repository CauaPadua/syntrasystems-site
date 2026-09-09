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

# Organização em etapas

Função responsável: [[granmaster]]

## O que é

O sequenciamento do projeto em etapas fechadas, cada uma entregando algo
utilizável e explicitando o que **não** faz.

## As etapas

| Etapa | Entrega | Situação |
| --- | --- | --- |
| 1 | Landing page institucional | Concluída |
| 2 | Login com autenticação PHP, sobre repositório simulado | Concluída |
| 3 | Sistema interno: 14 telas, 18 endpoints, dados simulados | Concluída |
| 4 | Banco de dados | Não iniciada |

## Evidência no projeto

- `README.md` — as três etapas declaradas na abertura
- `docs/login-stage.md` — etapa 2, com seção "O que ainda NÃO está pronto para produção"
- `PROMPT-CLAUDE-CODE.md`, "contexto e limites desta etapa"
- Commits `8db3bde` (estrutura inicial), `5810aec` ("Sistema"), `996e2a5` ("front")

## Prática adotada

Cada etapa declara seus próprios limites. É por isso que "não há banco de
dados" aparece como afirmação explícita em cinco documentos, em vez de ser uma
ausência silenciosa.

## Situação

**Em andamento** — a etapa 4 não começou, e a correção dos gráficos corre fora
da numeração das etapas.

## Relacionadas

[[granmaster]] · [[status-atual]] · [[coordenacao-do-backlog]] · [[ponto-de-substituicao]] · [[tarefas]]
