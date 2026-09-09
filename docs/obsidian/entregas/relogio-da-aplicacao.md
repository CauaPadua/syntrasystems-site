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

# Relógio da aplicação

Função responsável: [[backend]]

## O que é

Todas as datas do sistema passam por uma única classe, sempre em
`America/Sao_Paulo`. Em modo demonstração, o "agora" é **fixo**.

## Evidência no projeto

- `backend/src/Support/Clock.php`

```php
public const DEMO_INSTANT = '2024-05-22 09:30:00';
public const DEMO_MODE    = true;
```

## Por que é fixo

Mantém as telas idênticas às referências visuais em qualquer dia, e sustenta o
determinismo dos dados simulados. `lastUpdate()` devolve 2 minutos antes do
"agora".

É também o motivo de `meta.updated_label` ("Atualizado há 2 min") ser calculado
**no servidor**: um cálculo no navegador compararia 2024 com a data real da
máquina e diria "há 2 anos".

## Situação

**Demonstrativo por decisão.** Voltar ao relógio real é `DEMO_MODE = false` —
nenhum outro arquivo muda.

## Relacionadas

[[backend]] · [[repositorio-de-dados-simulados]] · [[envelopes-e-tratamento-de-erros]] · [[ponto-de-substituicao]] · [[decisoes]]
