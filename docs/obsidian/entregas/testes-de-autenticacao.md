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

# Testes de autenticação

Função responsável: [[analista-de-qualidade]]

## O que é

Bateria de testes do fluxo de login, sessão e logout, com requisição e resposta
registradas.

## Evidência no projeto

`validation/auth-test-results.txt` — PHP 8.0.30, servidor embutido em
127.0.0.1:8123, repositório simulado.

| # | Caso | Esperado | Resultado |
| --- | --- | --- | --- |
| 1 | Abrir `login.php` | 200, sem avisos PHP | OK |
| 2–4 | Login correto | 200 + `Set-Cookie` | OK |
| 5 | `me.php` com sessão | 200 com usuário | OK |
| 6 | Sessão persiste entre requisições | 200 / 200 | OK |
| 7 | Logout | 200 | OK |
| 8 | `me.php` após logout | 401 `UNAUTHENTICATED` | OK |
| 9 | Senha incorreta | 401 `INVALID_CREDENTIALS` | OK |
| 10 | E-mail inexistente | 401, **mensagem idêntica** ao caso 9 | OK |
| 11 | Campos vazios / ausentes | 422 `VALIDATION_ERROR` com `details` | OK |

O arquivo registra explicitamente a observação do caso 10: "a API não revela se
o e-mail existe".

## Situação

Concluída. O arquivo é anterior a uma mudança no nome do usuário simulado —
registra "Usuário de demonstração", enquanto `users.php` hoje traz "Ana Silva".
Precisa ser regerado ([[tarefas]]).

## Relacionadas

[[analista-de-qualidade]] · [[autenticacao]] · [[controle-de-sessao]] · [[tela-de-login]] · [[tarefas]]
