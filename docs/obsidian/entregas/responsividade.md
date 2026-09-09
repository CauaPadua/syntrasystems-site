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

# Responsividade

Função responsável: [[frontend]]

## O que é

Adaptação da landing, do login e do sistema interno aos tamanhos exigidos pela
especificação: 1920, 1440, 1280, 1024, 768 e 375 px.

## Evidência no projeto

- `assets/css/dashboard-responsive.css` (160 linhas) — pontos de quebra do sistema
- `assets/css/style.css` e `login.css` — landing e login
- `validation/screenshots/desktop-1920-full.png` (1920×4296)
- `validation/screenshots/desktop-1440-full.png` (1440×4293)
- `validation/screenshots/mobile-375-full.png` (375×8015)
- `validation/screenshots/hero-mobile-375.png`, `login-mobile-375.png`
- Requisito em `aquapulse-kit-claude-sistema/PROMPT-CLAUDE-CODE.md`, seção "responsividade"

## Comportamentos

- Sidebar recolhível em tablet e celular (`AqShell`).
- Alturas de gráfico próprias por ponto de quebra.
- `.aq-user__info` oculto em telas estreitas.

## Situação

Concluído. As alturas de gráfico foram revisadas na correção em andamento
(mobile: `sm` 180→210 px, `md` 200→220, `lg` 220→240, `xl` 240→260) porque
eixos e legendas ficavam espremidos.

## Relacionadas

[[frontend]] · [[testes-de-responsividade]] · [[graficos-chartjs]] · [[registro-de-correcoes]]
