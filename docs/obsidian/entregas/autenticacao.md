---
projeto: Aquapulse
tipo: entrega
papel: Backend
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/backend
  - tipo/entrega
---

# Autenticação

Função responsável: [[backend]]
Colaboração: [[frontend]] (tela), [[analista-de-qualidade]] (testes)

## O que é

Toda a decisão sobre "quem é o usuário e se pode entrar" concentrada em um
serviço. Os arquivos em `api/v1/auth/` são finos de propósito: só traduzem
HTTP.

## Evidência no projeto

- `backend/src/Auth/AuthService.php` — `attempt()`, `login()`, `check()`,
  `currentUser()`, `logout()`, `publicUser()`
- `api/v1/auth/login.php`, `logout.php`, `me.php`
- `backend/src/Http/Request.php`, `JsonResponse.php`
- `docs/api-contract.md` — contrato completo
- `validation/auth-test-results.txt` — 13 casos, todos OK

## Decisões de segurança

- Verificação com `password_verify()`; o hash nunca sai do servidor.
- `session_regenerate_id(true)` imediatamente após o login.
- `401` usa **a mesma mensagem** para e-mail inexistente e senha incorreta, com
  tempo de resposta equalizado — a resposta não revela se um e-mail existe.
- `logout` é idempotente: sem sessão ativa também responde `200`.

## Situação

Concluída sobre repositório simulado (um usuário fixo). **Não está pronta para
produção** — faltam proteção contra força bruta, CSRF, auditoria e autorização
por papel.

## Relacionadas

[[backend]] · [[controle-de-sessao]] · [[tela-de-login]] · [[testes-de-autenticacao]] · [[contrato-do-repositorio]] · [[tarefas]]
