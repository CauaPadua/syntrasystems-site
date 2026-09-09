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

# Endpoints da API

Função responsável: [[backend]]
Colaboração: [[frontend]] (consumo)

## O que é

Dezoito pontos de entrada HTTP — 3 de autenticação e 15 do sistema. Todos
respondem exclusivamente JSON, em caminho **relativo** (funciona na raiz e em
subpasta do XAMPP).

## Evidência no projeto

- `api/v1/_boot.php` — `aq_api_boot()`: valida método, exige sessão, devolve o repositório
- `api/v1/auth/` — 3 endpoints
- `api/v1/companies.php`, `reservoirs.php`, `overview.php`, `settings.php`, `reports.php`, `alerts.php`
- `api/v1/map/reservoirs.php`
- `api/v1/monitoring/` — 8 endpoints
- `docs/api-monitoring.md` — contrato completo
- `assets/js/api-client.js` — consumo pelo cliente

## Regras

- Somente `GET` nos endpoints de leitura; `POST` em login e logout.
- **Nenhum endpoint de escrita existe** — decisão consciente desta etapa.
- Sem sessão: `401` em JSON.
- Nenhuma resposta expõe caminho de arquivo, *stack trace* ou consulta.

## Situação

Concluído. Contagem correta: 15 do sistema + 3 de autenticação = **18** (o
`README.md` menciona 17 — ver [[tarefas]]).

## Relacionadas

[[backend]] · [[api]] · [[envelopes-e-tratamento-de-erros]] · [[validacao-de-requisicoes]] · [[testes-dos-endpoints]] · [[frontend]]
