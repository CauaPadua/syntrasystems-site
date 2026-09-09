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

# Validação das requisições

Função responsável: [[backend]]

## O que é

Nada vindo do navegador é confiável. Todo parâmetro é comparado com uma
**lista fechada**; valor fora dela vira erro explícito, nunca uma consulta com
valor arbitrário.

## Evidência no projeto

- `backend/src/Support/Validator.php`

```php
PERIODS       = ['24h', '7d', '30d', '90d', '12m'];
SEVERITY      = ['all', 'critical', 'attention', 'info'];
ALERT_STATUS  = ['all', 'new', 'analysis', 'resolved'];
REPORT_STATUS = ['all', 'done', 'processing', 'scheduled'];
REPORT_TYPES  = ['all', 'operational', 'hydrological', 'quality', 'planning'];
```

## Comportamento

| Situação | Resposta |
| --- | --- |
| Valor fora da lista | `400` (`INVALID_PERIOD`, `INVALID_HORIZON`…) |
| Identificador inexistente | `404` (`INVALID_COMPANY`, `INVALID_RESERVOIR`) |
| Represa obrigatória ausente | `400 RESERVOIR_REQUIRED` |

## Situação

Concluído. Se o banco trouxer novos períodos ou tipos, eles devem ser
acrescentados **aqui** — nunca aceitos direto do navegador.

## Relacionadas

[[backend]] · [[endpoints-da-api]] · [[api]] · [[decisoes]] · [[banco-de-dados]]
