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

# Pipeline de publicação

Função responsável: [[granmaster]]

## O que é

Publicação automática: todo `push` para `main` envia o projeto por FTP para a
hospedagem.

## Evidência no projeto

- `.github/workflows/deploy.yml` — `SamKirkland/FTP-Deploy-Action@v4.3.5`,
  destino `/htdocs/`, credenciais em *secrets*
- `CNAME` — `syntrasystems.com.br`
- Commits `56e21f5` ("ci: adiciona deploy automático para InfinityFree") e
  `17385b3` ("Update deploy.yml")

## O que não é publicado

`.git*`, `.github/`, `README.md`, `CNAME`, `reference/`, `validation/` e
`docs/`.

Ou seja: **esta documentação é versionada, mas não vai para o servidor** —
inclusive as notas deste mapa.

## Consequência operacional

Enquanto a correção dos gráficos não for commitada, ela não chega ao ar.

## Situação

Concluído e em uso.

## Relacionadas

[[granmaster]] · [[arquitetura]] · [[coordenacao-do-backlog]] · [[registro-de-correcoes]] · [[decisoes]]
