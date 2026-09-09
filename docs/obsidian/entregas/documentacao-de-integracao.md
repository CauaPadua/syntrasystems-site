---
projeto: Aquapulse
tipo: entrega
papel: Banco de Dados
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/banco-de-dados
  - tipo/entrega
---

# Documentação de integração

Função responsável: [[banco-de-dados]]
Colaboração: [[granmaster]] (coordenação do handoff)

## O que é

O guia de quem for substituir os dados simulados por um banco real, com a
troca em três passos e os pontos de atenção da migração.

## Evidência no projeto

- `docs/database-handoff.md` (6.837 bytes)
- `docs/api-contract.md`, seção "Substituição futura pelo banco de dados"
- `docs/mock-data.md`
- Exigido em `PROMPT-CLAUDE-CODE.md`: "explicando ao responsável pelo banco
  quais entidades e campos serão necessários, sem implementar banco agora"

## Sete pontos de atenção registrados

1. Formato das respostas — mudar a origem não quebra; mudar a forma, sim.
2. Último ponto da série = valor do KPI.
3. Relógio — `Clock::DEMO_MODE = false`.
4. Coordenadas demonstrativas.
5. Previsão de duração isolada em um serviço.
6. Ações demonstrativas exigirão endpoints `POST` / `PATCH`.
7. Validação por allowlist em `Validator.php`.

## Situação

Concluído.

## Relacionadas

[[banco-de-dados]] · [[contrato-do-repositorio]] · [[ponto-de-substituicao]] · [[planejamento-das-entidades]] · [[granmaster]]
