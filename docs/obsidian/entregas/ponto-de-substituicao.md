---
projeto: Aquapulse
tipo: entrega
papel: Banco de Dados
responsavel: A definir
status: pendente
tags:
  - aquapulse
  - papel/banco-de-dados
  - tipo/entrega
---

# Ponto de substituição dos dados simulados

Função responsável: [[banco-de-dados]]

## O que é

O lugar único onde a fonte de dados é escolhida. Trocar o banco não é uma
refatoração: é uma linha.

## Evidência no projeto

- `backend/src/Support/Container.php` — comentado no próprio arquivo como
  "PONTO ÚNICO DE TROCA PARA O BANCO DE DADOS"

```php
// hoje:
self::$monitoring = new MockMonitoringRepository();
// futuro:
self::$monitoring = new PdoMonitoringRepository($pdo);
```

O mesmo vale para `Container::users()` → `PdoUserRepository`.

## O que não muda na troca

Nenhuma tela, JavaScript, CSS, endpoint ou contrato de API. Nem `AuthService`,
`MonitoringService`, `OverviewService` ou `StatusRules`.

## Situação

**Pendente** — é a etapa 4, ainda não iniciada. O ponto está preparado e
documentado; falta a implementação `Pdo*Repository` e a modelagem das tabelas.

## Relacionadas

[[banco-de-dados]] · [[contrato-do-repositorio]] · [[documentacao-de-integracao]] · [[repositorio-de-dados-simulados]] · [[tarefas]]
