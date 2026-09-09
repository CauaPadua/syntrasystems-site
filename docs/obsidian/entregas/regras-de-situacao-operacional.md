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

# Regras de situação operacional

Função responsável: [[backend]]
Origem do requisito: [[criterios-operacionais]]

## O que é

Classificação centralizada de status, para que cartões, gráficos, tabelas e
mapa nunca classifiquem o mesmo valor de formas diferentes. **O banco guarda os
números; a classificação fica aqui.**

## Evidência no projeto

- `backend/src/Services/StatusRules.php` (138 linhas)

```php
LEVEL_ATTENTION = 80.0;   // % da capacidade
LEVEL_CRITICAL  = 90.0;
PH_MIN = 6.5;
PH_MAX = 8.5;
```

## Faixas

| Situação | Nível |
| --- | --- |
| Normal | até 80 % |
| Atenção | de 80 % a 90 % |
| Crítico | acima de 90 % |

## Acessibilidade

`describe()` devolve sempre `key` + `label` + `icon` — **o status nunca é
comunicado só por cor**. `levelNote()` gera a frase do cartão a partir do mesmo
status, para que texto e badge não se contradigam.

Também centraliza os rótulos em português de severidade, status de alerta,
status e tipo de relatório.

## Situação

Concluído.

## Relacionadas

[[backend]] · [[criterios-operacionais]] · [[servicos-de-monitoramento]] · [[componentes-visuais]] · [[banco-de-dados]]
