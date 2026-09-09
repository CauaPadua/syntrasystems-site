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

# Integração entre as áreas

Função responsável: [[granmaster]]

## O que é

A estratégia que permite três frentes trabalharem em paralelo sem se
bloquearem: **cada fronteira é um contrato escrito**.

## Os três contratos

| Fronteira | Contrato | Documento |
| --- | --- | --- |
| Front-end ↔ Back-end (autenticação) | envelope `data` / `error` | `docs/api-contract.md` |
| Front-end ↔ Back-end (sistema) | envelope `success` / `data` / `meta` | `docs/api-monitoring.md` |
| Back-end ↔ Banco | `MonitoringRepositoryInterface`, `UserRepositoryInterface` | `docs/database-handoff.md` |

Cada documento abre com a mesma ideia: "enquanto este contrato for respeitado,
cada lado pode ser alterado de forma independente".

## Evidência no projeto

- Os três documentos acima, versionados em `docs/`
- `backend/src/Support/Container.php` — o ponto único onde as implementações
  são escolhidas
- `PROMPT-CLAUDE-CODE.md` exige documentação "para as três pessoas da equipe":
  front-end, back-end e banco

## Situação

Concluído. Note que a especificação documenta **três** frentes técnicas; as
funções de Qualidade, Negócios e Granmaster deste mapa são a organização da
equipe sobre os mesmos artefatos ([[equipe]]).

## Relacionadas

[[granmaster]] · [[arquitetura-em-camadas]] · [[api]] · [[documentacao-de-integracao]] · [[equipe]]
