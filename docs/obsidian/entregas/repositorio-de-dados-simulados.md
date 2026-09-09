---
projeto: Aquapulse
tipo: entrega
papel: Backend
responsavel: A definir
status: demonstrativo
tags:
  - aquapulse
  - papel/backend
  - tipo/entrega
---

# Repositório de dados simulados

Função responsável: [[backend]]
Colaboração: [[banco-de-dados]] (substituição futura)

## O que é

A implementação provisória do contrato de dados. Lê um arquivo PHP
determinístico e **deriva** tudo o mais — não guarda séries prontas.

## Evidência no projeto

- `backend/src/Repositories/Mock/MockMonitoringRepository.php` (384 linhas)
- `backend/storage/mock/monitoring.php` (402 linhas)
- `backend/src/Repositories/MockUserRepository.php`
- `backend/storage/mock/users.php` — um usuário, **só com o hash**
- `backend/.htaccess` — `Require all denied`
- `docs/mock-data.md`

## Como as séries são geradas

Função `wave()`, semeada pelo `crc32` do id da represa e da métrica:

```php
$osc = sin(($i * 0.9) + $seed * 6.283) * $amplitude
     + sin(($i * 0.37) + $seed * 3.14) * ($amplitude * 0.45);
$osc *= (1 - $t * $t);                          // a oscilação some perto do fim
$values[$count - 1] = round($end, $decimals);   // crava o KPI atual
```

Sem `rand()`, `mt_rand()`, `shuffle()` ou `time()` em lugar nenhum.

## Consequências

1. A mesma requisição devolve sempre a mesma resposta.
2. Último ponto da série = valor do KPI, por construção.
3. Cada represa tem uma forma de curva própria e estável.

## Situação

**Provisório por decisão.** É descartado quando o banco entrar.

## Relacionadas

[[backend]] · [[contrato-do-repositorio]] · [[ponto-de-substituicao]] · [[relogio-da-aplicacao]] · [[banco-de-dados]]
