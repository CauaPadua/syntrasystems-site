---
projeto: Aquapulse
tipo: nucleo
papel: Granmaster
responsavel: A definir
status: em-andamento
colaboradores:
  - Frontend
  - Backend
  - Banco de Dados
tags:
  - aquapulse
  - papel/granmaster
  - tipo/nucleo
---

# Granmaster

Núcleo de função · cor **#FFD166** · voltar para [[equipe]]

## Escopo

Sustentar a estrutura do projeto: arquitetura, fronteiras entre as áreas,
sequenciamento das etapas, decisões técnicas, backlog e publicação.

## Entregas (6)

| Entrega | Situação |
| --- | --- |
| [[arquitetura-em-camadas]] | concluída |
| [[integracao-entre-areas]] | concluída — três contratos escritos |
| [[organizacao-em-etapas]] | em andamento — etapa 4 não iniciada |
| [[criterios-de-aceite]] | concluída — 14 critérios |
| [[coordenacao-do-backlog]] | em andamento |
| [[pipeline-de-publicacao]] | concluída |

## Notas estruturais sob esta função

- [[arquitetura]] — as três camadas e o caminho de uma requisição
- [[decisoes]] — as escolhas de projeto e o motivo de cada uma
- [[tarefas]] — o backlog

## Princípio que organiza o projeto

**Cada fronteira é um contrato escrito.** Enquanto o contrato for respeitado,
os lados mudam de forma independente:

| Fronteira | Contrato |
| --- | --- |
| Front ↔ Back (auth) | `docs/api-contract.md` |
| Front ↔ Back (sistema) | `docs/api-monitoring.md` |
| Back ↔ Banco | `MonitoringRepositoryInterface` + `docs/database-handoff.md` |

É isso que permite conectar o banco alterando **uma linha** em
`Container.php`, sem tocar em tela, JavaScript ou endpoint.

## Prática de etapa

Cada etapa declara os próprios limites. "Não há banco de dados" é afirmação
explícita em cinco documentos, não uma ausência silenciosa.

## Relacionadas

[[equipe]] · [[arquitetura]] · [[decisoes]] · [[tarefas]] · [[banco-de-dados]]
