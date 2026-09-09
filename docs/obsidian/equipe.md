---
projeto: Aquapulse
tipo: hub
tags:
  - aquapulse
  - tipo/hub
---

# Equipe

Núcleo de organização · voltar para [[00-inicio]]

As seis funções do projeto, cada uma com cor própria no gráfico e um núcleo
secundário próprio.

## Funções

| Função | Nome da pessoa | Cor | Responsabilidades | Nota |
| --- | --- | --- | --- | --- |
| Frontend | A definir | `#00D9FF` azul-ciano | Landing, login, shell do dashboard, componentes, gráficos, mapas, estados e responsividade | [[frontend]] |
| Backend | A definir | `#8B5CF6` roxo | Autenticação, sessão, endpoints, serviços de monitoramento, validação e fonte simulada | [[backend]] |
| Banco de Dados | A definir | `#22C55E` verde | Contrato do repositório, entidades, documentação de integração e ponto de substituição | [[banco-de-dados]] |
| Analista de Qualidade | A definir | `#FF9F1C` laranja | Validação das telas, responsividade, testes de autenticação e endpoints, defeitos e correções | [[analista-de-qualidade]] |
| Analista de Negócios | A definir | `#FF4ECD` rosa | Requisitos, definição das telas, indicadores, critérios operacionais e regras de seleção | [[analista-de-negocios]] |
| Granmaster | A definir | `#FFD166` dourado | Arquitetura, integração entre áreas, etapas, decisões, backlog e publicação | [[granmaster]] |

## Sobre os nomes

**Todos os campos `responsavel` estão como "A definir".** Nenhum documento do
projeto atribui uma pessoa a uma função, e o histórico Git não substitui essa
atribuição.

O que o Git comprova, e apenas isso:

| Conta | Commits | Período | Escopo |
| --- | --- | --- | --- |
| `CauaPadua` | 56 | 31/07/2026 – 02/09/2026 | protótipo inicial (`index.html`, favicon, `stric.js`) — arquivos depois removidos |
| `bryannn0205` | 8 | 02/09/2026 – 04/09/2026 | estrutura atual: "Adiciona estrutura inicial do AquaPulse", "Sistema", "front", CI de deploy |

Isso identifica **quem commitou**, não quem exerce cada função: a mesma conta
commitou front-end, back-end e CI. Preencher a coluna exigiria uma definição da
equipe, não uma inferência.

## Uma observação sobre a divisão

A especificação (`PROMPT-CLAUDE-CODE.md`) organiza o trabalho em **três**
frentes técnicas — front-end, back-end e banco — e exige "documentação para as
três pessoas da equipe".

As seis funções deste mapa são a organização atual da equipe aplicada sobre os
mesmos artefatos. Qualidade, Negócios e Granmaster têm entregas reais e
comprovadas no repositório (`validation/`, o pacote de especificação, os
contratos e o pipeline), mas não aparecem nomeadas na especificação original.

## Distribuição das entregas

| Função | Entregas | Concluídas | Em andamento | Outras |
| --- | --- | --- | --- | --- |
| Frontend | 10 | 9 | 1 | — |
| Backend | 10 | 7 | — | 3 demonstrativas |
| Banco de Dados | 4 | 3 | — | 1 pendente |
| Analista de Qualidade | 6 | 3 | 2 | 1 parcial |
| Analista de Negócios | 6 | 6 | — | — |
| Granmaster | 6 | 4 | 2 | — |
| **Total** | **42** | **32** | **5** | **5** |

Tabela completa em [[registro-de-entregas]].

## Relacionadas

[[00-inicio]] · [[registro-de-entregas]] · [[legenda-do-grafico]] · [[status-atual]]
