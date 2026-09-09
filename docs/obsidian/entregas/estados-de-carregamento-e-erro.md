---
projeto: Aquapulse
tipo: entrega
papel: Frontend
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/frontend
  - tipo/entrega
---

# Estados de carregamento e erro

Função responsável: [[frontend]]

## O que é

Todo bloco que depende de dados tem quatro estados nomeados por escopo:
`loading` (esqueletos), `ready` (conteúdo real), `empty` ("Sem dados para o
período") e `error` (mensagem + "Tentar novamente").

## Evidência no projeto

- `dashboard/includes/components.php` — `aq_states($escopo)`
- `assets/js/dashboard-shell.js` — `AqShell.setState()`, `onRetry()`, `onReload()`
- `data-content` e `aq_states` presentes nas 14 telas

## Como funciona

```php
<div data-content="daily" hidden>   <!-- conteúdo real -->
  <?php echo aq_chart(['id' => 'grafico-media-diaria', ...]); ?>
</div>
<?php echo aq_states('daily'); ?>   <!-- loading / empty / error -->
```

O conteúdo real nasce com `hidden` e só é revelado no estado `ready`.

## Situação

Concluído. Oito gráficos ficavam **fora** de qualquer escopo e falhavam em
silêncio; a correção em andamento colocou todo canvas do sistema sob um escopo
de estado.

## Relacionadas

[[frontend]] · [[componentes-visuais]] · [[graficos-chartjs]] · [[registro-de-correcoes]] · [[decisoes]]
