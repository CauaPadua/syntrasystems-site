---
projeto: Aquapulse
tipo: nucleo
papel: Analista de Qualidade
responsavel: A definir
status: em-andamento
colaboradores:
  - Frontend
  - Backend
tags:
  - aquapulse
  - papel/qualidade
  - tipo/nucleo
---

# Analista de Qualidade

Núcleo de função · cor **#FF9F1C** · voltar para [[equipe]]

## Escopo

Verificar que o entregue corresponde ao especificado, e registrar a evidência
dentro do repositório. No Aquapulse, a validação é **arquivada**, não apenas
executada: capturas e resultados ficam versionados em `validation/`.

## Entregas (6)

| Entrega | Situação |
| --- | --- |
| [[validacao-das-telas]] | concluída — 15 capturas |
| [[testes-de-responsividade]] | concluída — 11 capturas |
| [[testes-de-autenticacao]] | concluída — 13 casos registrados |
| [[testes-dos-endpoints]] | **parcial** — só a autenticação tem log arquivado |
| [[erros-encontrados-nos-graficos]] | em andamento — 12 defeitos |
| [[registro-de-correcoes]] | em andamento |

## Onde ficam as evidências

```
validation/auth-test-results.txt          resultados dos testes de autenticação
validation/screenshots/                   11 capturas de landing, login e responsividade
validation/screenshots/dashboard/         15 capturas das telas do sistema
```

## Critérios aplicados

Definidos em `aquapulse-kit-claude-sistema/PROMPT-CLAUDE-CODE.md`:

- **Validação visual obrigatória** — comparar cada rota com a referência, em
  1536×1024, e verificar 1440, 1280, 1024, 768 e 375 px.
- **Validação técnica obrigatória** — lint PHP, HTTP 200 nas páginas
  autorizadas, redirecionamento sem sessão, 401 JSON nos endpoints, console sem
  erros, rede sem 404/500.

## Achado mais relevante

Os 12 defeitos de gráfico não produziam erro de console — o canvas
simplesmente ficava branco, com título e legenda visíveis. Escapavam do
critério "console sem erros" e só apareciam na conferência visual.

## Relacionadas

[[equipe]] · [[criterios-de-aceite]] · [[frontend]] · [[status-atual]] · [[tarefas]]
