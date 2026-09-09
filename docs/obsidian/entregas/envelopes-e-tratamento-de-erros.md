---
projeto: Aquapulse
tipo: entrega
papel: Backend
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/backend
  - tipo/entrega
---

# Envelopes e tratamento de erros

Função responsável: [[backend]]

## O que é

O formato único das respostas e a garantia de que **nenhuma falha vaza para a
saída**.

## Evidência no projeto

- `backend/src/Support/ApiResponse.php` — envelope do sistema
- `backend/src/Http/JsonResponse.php` — envelope da autenticação
- `backend/bootstrap.php` — `set_exception_handler`, `set_error_handler`

## Dois envelopes

Por razões históricas: a autenticação nasceu na etapa 2, o sistema na etapa 3.

| Camada | Sucesso | Erro |
| --- | --- | --- |
| `auth/` | `{ "data": {...} }` | `{ "error": { code, message } }` |
| Sistema | `{ "success": true, "data": {...}, "meta": {...} }` | `{ "success": false, "data": null, "meta": [], "error": {...} }` |

`meta` sempre traz `source`, `generated_at` e `updated_label`, e **ecoa os
parâmetros atendidos** — é assim que o cliente confirma o contexto.

## Erros nunca vão para a saída

`display_errors = 0`, `log_errors = 1`. Qualquer exceção vira
`500 INTERNAL_ERROR` genérico, com o texto real no log do servidor. Nenhuma
resposta expõe caminho de arquivo, *stack trace*, consulta ou dado de sessão.

## Situação

Concluído.

## Relacionadas

[[backend]] · [[api]] · [[endpoints-da-api]] · [[relogio-da-aplicacao]] · [[decisoes]]
