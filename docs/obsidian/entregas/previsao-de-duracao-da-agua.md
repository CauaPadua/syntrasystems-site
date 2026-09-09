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

# Previsão de duração da água

Função responsável: [[backend]]

## O que é

Estimativa de por quantos dias a água disponível dura, em quatro cenários e
quatro horizontes (`30d`, `60d`, `90d`, `180d`).

## Evidência no projeto

- `backend/src/Services/DurationForecastService.php` (181 linhas)
- `api/v1/monitoring/duration.php`
- `dashboard/monitoramento/duracao.php`, `assets/js/pages/duration.js`
- Referência visual `06-previsao-duracao-agua.png`

## Fórmula atual

```
(volume útil − reserva técnica) / consumo diário
```

Mais um ganho demonstrativo por precipitação prevista, e um histórico de
estimativas também demonstrativo.

## Situação

**Demonstrativo por decisão.** É o único ponto do sistema com um modelo
inventado, e está isolado em uma classe justamente para isso: `estimateDays()`
e `project()` devem ser substituídos pelo modelo real sem tocar em mais nada.

## Relacionadas

[[backend]] · [[telas-de-monitoramento]] · [[banco-de-dados]] · [[decisoes]] · [[tarefas]]
