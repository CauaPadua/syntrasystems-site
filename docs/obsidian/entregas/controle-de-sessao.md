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

# Controle de sessão

Função responsável: [[backend]]

## O que é

Configuração central da sessão PHP e a proteção de rotas que a reaproveita.
**Não existem dois sistemas de autenticação** — o dashboard usa a sessão criada
no login.

## Evidência no projeto

- `backend/config/session.php` — configuração declarada
- `backend/bootstrap.php` — `aq_start_session()`, `aq_destroy_session()`
- `backend/src/Support/Guard.php` — `requireApiSession()`, `requirePageSession()`

## Parâmetros

| Item | Valor |
| --- | --- |
| Cookie | `AQUAPULSE_SESSION` |
| `HttpOnly` | sim — inacessível ao JavaScript |
| `SameSite` | `Lax` |
| `Secure` | automático sob HTTPS |
| Tempo de vida do cookie | `0` |
| Inatividade máxima | 30 min (`idle_timeout = 1800`) |
| Duração absoluta | 12 h (`absolute_timeout = 43200`) |
| `use_strict_mode` / `use_only_cookies` | `1` |

Os dois limites são verificados **no servidor**. A sessão guarda só `user_id`,
`started_at` e `last_seen_at` — nome, e-mail e papel são relidos a cada
consulta.

## Comportamento sem sessão

| Contexto | Resposta |
| --- | --- |
| Página do dashboard | redireciona para `login.php?redirect=dashboard` |
| Endpoint da API | **401 em JSON** — nunca HTML, nunca redirecionamento |

## Situação

Concluído. Sessão em arquivos, o que não serve para múltiplos servidores.

## Relacionadas

[[backend]] · [[autenticacao]] · [[endpoints-da-api]] · [[decisoes]] · [[tarefas]]
