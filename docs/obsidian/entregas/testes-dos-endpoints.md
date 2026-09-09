---
projeto: Aquapulse
tipo: entrega
papel: Analista de Qualidade
responsavel: A definir
status: parcial
tags:
  - aquapulse
  - papel/qualidade
  - tipo/entrega
---

# Testes dos endpoints

Função responsável: [[analista-de-qualidade]]

## O que é

Conferência do comportamento da API: códigos HTTP, envelopes e mensagens de
erro.

## Evidência no projeto

**Com registro de execução:**

- `validation/auth-test-results.txt` — os 3 endpoints de `api/v1/auth/`,
  cobrindo 200, 401, 405 e 422.

**Com casos documentados, sem log de execução arquivado:**

- `docs/api-monitoring.md`, seção "Exemplos de erro" — seis pares
  requisição/resposta reais: `RESERVOIR_REQUIRED`, `INVALID_RESERVOIR`,
  `INVALID_PERIOD`, `INVALID_HORIZON`, `INVALID_COMPANY`, `UNAUTHENTICATED`.

**Critério exigido** em `PROMPT-CLAUDE-CODE.md`, "validação técnica
obrigatória": HTTP 200 nas páginas autorizadas, redirecionamento sem sessão,
401 JSON nos endpoints, lint PHP em todos os arquivos.

## Situação

**Parcial.** Os 3 endpoints de autenticação têm evidência arquivada; os 15
endpoints do sistema têm casos documentados, mas não há arquivo de resultados
equivalente ao da autenticação.

## Relacionadas

[[analista-de-qualidade]] · [[endpoints-da-api]] · [[validacao-de-requisicoes]] · [[api]] · [[tarefas]]
