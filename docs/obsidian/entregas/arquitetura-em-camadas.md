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

# Arquitetura em camadas

Função responsável: [[granmaster]]

## O que é

A separação em três camadas e a regra que a sustenta: **o front-end nunca
inclui um arquivo de `backend/`**. A única ponte são os endpoints de `api/v1/`.

## Evidência no projeto

- `backend/.htaccess` — `Require all denied`, o isolamento é aplicado, não só combinado
- `backend/bootstrap.php` — "Carregado exclusivamente pelos pontos de entrada em api/"
- `api/v1/_boot.php` — inicialização comum dos endpoints
- `docs/login-stage.md` — tabela front-end × back-end, arquivo por arquivo
- `PROMPT-CLAUDE-CODE.md`, seção "arquitetura esperada"

## Consequências práticas

- O arquivo de dados simulados não é acessível pelo navegador.
- Regra de negócio nunca vaza para o cliente.
- As duas camadas são trocáveis de forma independente enquanto o contrato for
  respeitado.

## Situação

Concluído.

## Relacionadas

[[granmaster]] · [[arquitetura]] · [[integracao-entre-areas]] · [[endpoints-da-api]] · [[decisoes]]
