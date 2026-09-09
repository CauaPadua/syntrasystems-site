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

# Tela de login

Função responsável: [[frontend]]
Colaboração: [[backend]] (regras de autenticação)

## O que é

Interface de acesso (etapa 2). O arquivo PHP **só renderiza HTML** — nenhuma
regra de autenticação, sessão ou acesso a usuários acontece nele.

## Evidência no projeto

- `login.php` (242 linhas), com `meta robots noindex, nofollow`
- `assets/css/login.css` (569 linhas)
- `assets/js/login.js` (318 linhas) — fala apenas com `api/v1/auth/`
- `validation/screenshots/login-desktop-1440.png`, `login-mobile-375.png`
- `docs/login-stage.md` — separação front/back documentada

## Regras seguidas no cliente

- A senha nunca é armazenada, registrada em log ou reaproveitada.
- Nada de sessão vai para `localStorage` / `sessionStorage` — o cookie é `HttpOnly`.
- Envio duplicado bloqueado enquanto houver requisição em andamento.
- `me.php` é consultado no carregamento: havendo sessão, mostra direto o estado autenticado.

## Situação

Concluída. Dois elementos são **apenas visuais** nesta etapa: "Lembrar de mim"
(não altera a duração da sessão) e "Esqueci minha senha" (informa que virá
depois).

## Relacionadas

[[frontend]] · [[autenticacao]] · [[controle-de-sessao]] · [[testes-de-autenticacao]] · [[tarefas]]
