---
projeto: Aquapulse
tipo: entrega
papel: Analista de Qualidade
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/qualidade
  - tipo/entrega
---

# Testes de responsividade

Função responsável: [[analista-de-qualidade]]

## O que é

Verificação da página completa e das seções nos tamanhos exigidos, com captura
de página inteira.

## Evidência no projeto

| Captura | Dimensão registrada |
| --- | --- |
| `desktop-1920-full.png` | 1920 × 4296 |
| `desktop-1440-full.png` | 1440 × 4293 |
| `mobile-375-full.png` | 375 × 8015 |
| `hero-1920-centered.png`, `hero-1440.png`, `hero-mobile-375.png` | hero em três larguras |
| `informacoes-1440.png`, `sistema-1440.png`, `vantagens-1440.png` | seções |
| `login-desktop-1440.png`, `login-mobile-375.png` | login |

Todas em `validation/screenshots/`. Geradas com Chrome headless sobre o
servidor local, conforme registrado no `README.md`.

## Critério aplicado

`PROMPT-CLAUDE-CODE.md` exige verificação em 1440, 1280, 1024, 768 e 375 px.

## Situação

Concluída para a landing e o login. As larguras 1280, 1024 e 768 não têm
captura própria registrada — foram verificadas sem evidência arquivada.

## Relacionadas

[[analista-de-qualidade]] · [[responsividade]] · [[landing-page]] · [[tela-de-login]]
