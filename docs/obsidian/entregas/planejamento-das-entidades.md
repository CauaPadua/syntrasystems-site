---
projeto: Aquapulse
tipo: entrega
papel: Banco de Dados
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/banco-de-dados
  - tipo/entrega
---

# Planejamento das entidades

Função responsável: [[banco-de-dados]]

## O que é

Descrição das entidades e campos que o banco precisará fornecer — **sem SQL,
sem migration, sem conexão**, como exigido pela etapa.

## Evidência no projeto

- `docs/database-handoff.md`, seção "Entidades e campos esperados"
- `aquapulse-kit-claude-sistema/PROMPT-CLAUDE-CODE.md`, seção "documentação
  para as três pessoas da equipe" — lista as entidades futuras exigidas

## Entidades documentadas

Empresa · Represa · Leitura (série temporal) · Alerta · Relatório · Sensor ·
Ponto de pH · Estação pluviométrica · Evento operacional · Manutenção ·
Usuário.

Os nomes documentados são as **chaves do array devolvido**, não
necessariamente nomes de coluna — a tradução coluna → chave é responsabilidade
do repositório.

## Campos que hoje são demonstrativos

- `lat` / `lng` — região de Rio Claro/SP, devem vir do cadastro real.
- `updated_at` — depende do relógio fixo ([[relogio-da-aplicacao]]).

## Situação

Concluído como planejamento. Nenhuma tabela foi criada — por decisão da etapa.

## Relacionadas

[[banco-de-dados]] · [[contrato-do-repositorio]] · [[documentacao-de-integracao]] · [[mapas-leaflet]] · [[tarefas]]
