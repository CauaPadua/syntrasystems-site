---
projeto: Aquapulse
tipo: entrega
papel: Analista de Negócios
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/negocios
  - tipo/entrega
---

# Conteúdo institucional

Função responsável: [[analista-de-negocios]]
Implementação: [[landing-page]]

## O que é

A mensagem pública do produto: proposta de valor, argumentos e chamadas da
landing page. Fica **fora do HTML**, em constantes, para poder mudar sem tocar
na estrutura.

## Evidência no projeto

`includes/config.php`:

| Constante | Conteúdo |
| --- | --- |
| `AQ_SITE_NAME`, `AQ_SITE_TAGLINE` | "Aquapulse" / "Monitoramento de represas" |
| `AQ_NAV` | Início, Sobre, Importância, Contato |
| `AQ_HERO_HIGHLIGHTS` | mais segurança, decisões melhores, sustentabilidade |
| `AQ_HERO_BADGES` | monitoramento contínuo, dados em tempo real, alertas inteligentes, gestão sustentável |
| `AQ_INFO_CARDS` | segurança e prevenção, eficiência operacional, sustentabilidade, decisões estratégicas |
| `AQ_SYSTEM_POINTS` | dados unificados, alertas que apoiam a decisão, informação confiável para equipes técnicas |

Também em `index.php`: `title` e `meta description` da página.

## Situação

Concluído. A chamada "Solicitar demonstração" ainda **não tem destino
definido** — hoje exibe aviso de indisponibilidade. É uma definição de negócio
pendente ([[tarefas]]).

## Relacionadas

[[analista-de-negocios]] · [[landing-page]] · [[requisitos-do-sistema]] · [[tarefas]]
