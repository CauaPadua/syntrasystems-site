# Contrato da API — Autenticação (v1)

> O contrato do sistema interno (visão geral, monitoramento, alertas,
> relatórios, mapas e configurações) está em
> [`api-monitoring.md`](api-monitoring.md).

Documento compartilhado entre front-end e back-end. Enquanto este contrato for
respeitado, cada lado pode ser alterado de forma independente — inclusive a
troca do repositório simulado por um banco de dados.

- **Base:** `api/v1/auth/` (caminho relativo à raiz do projeto)
- **Formato:** exclusivamente JSON
- **Cabeçalho de resposta:** `Content-Type: application/json; charset=utf-8`
- **Envelope:** sucesso sempre em `data`, erro sempre em `error`

> ⚠️ **Etapa temporária.** O repositório de usuários atual é **simulado**
> (`MockUserRepository`), com um único usuário fixo e somente leitura. Não há
> banco de dados. Esta autenticação **não está pronta para produção**.

---

## Envelopes

**Sucesso**

```json
{ "data": { } }
```

**Erro**

```json
{ "error": { "code": "CODIGO", "message": "Mensagem para exibição." } }
```

Em erros de validação (`422`), `error` também traz `details`, com uma mensagem
por campo:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Verifique os campos informados.",
    "details": { "email": "Informe um e-mail válido." }
  }
}
```

---

## `POST /api/v1/auth/login.php`

Autentica o usuário e cria a sessão.

**Requisição** — `Content-Type: application/json`

```json
{
  "email": "demo@aquapulse.local",
  "password": "senha-digitada"
}
```

| Campo | Tipo | Regras |
| --- | --- | --- |
| `email` | string | obrigatório; normalizado (minúsculas, sem espaços nas pontas); formato validado |
| `password` | string | obrigatório; **não** sofre `trim` (espaços podem fazer parte da senha) |

**200 — sucesso**

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Ana Silva",
      "email": "demo@aquapulse.local",
      "role": "admin"
    }
  }
}
```

O cookie de sessão acompanha a resposta em `Set-Cookie`. O identificador de
sessão é regenerado (`session_regenerate_id(true)`) imediatamente após o login.

**Erros**

| HTTP | `code` | Quando acontece |
| --- | --- | --- |
| `400` | `INVALID_CONTENT_TYPE` | `Content-Type` não é `application/json` |
| `400` | `INVALID_JSON` | corpo vazio ou JSON malformado |
| `401` | `INVALID_CREDENTIALS` | e-mail inexistente **ou** senha incorreta |
| `405` | `METHOD_NOT_ALLOWED` | método diferente de `POST` (resposta traz `Allow: POST`) |
| `422` | `VALIDATION_ERROR` | campo ausente, vazio ou e-mail com formato inválido |
| `500` | `INTERNAL_ERROR` | falha inesperada (mensagem genérica, sem detalhes internos) |

`401` usa **a mesma mensagem** para e-mail inexistente e senha incorreta, de
propósito: a resposta não revela se um e-mail está cadastrado. O tempo de
resposta também é equalizado.

---

## `POST /api/v1/auth/logout.php`

Encerra a sessão atual. **Idempotente**: chamar sem sessão ativa também responde
`200`, porque o estado desejado (sem sessão) já foi alcançado.

**Requisição** — sem corpo.

**200**

```json
{ "data": { "message": "Sessão encerrada." } }
```

| HTTP | `code` | Quando acontece |
| --- | --- | --- |
| `405` | `METHOD_NOT_ALLOWED` | método diferente de `POST` (resposta traz `Allow: POST`) |
| `500` | `INTERNAL_ERROR` | falha inesperada |

---

## `GET /api/v1/auth/me.php`

Devolve o usuário da sessão atual. É a **única** forma de o front-end saber se
existe sessão: o cookie é `HttpOnly` e não pode ser lido por JavaScript.

**200**

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Ana Silva",
      "email": "demo@aquapulse.local",
      "role": "admin"
    }
  }
}
```

| HTTP | `code` | Quando acontece |
| --- | --- | --- |
| `401` | `UNAUTHENTICATED` | sem sessão, sessão expirada ou usuário inexistente |
| `405` | `METHOD_NOT_ALLOWED` | método diferente de `GET` (resposta traz `Allow: GET`) |
| `500` | `INTERNAL_ERROR` | falha inesperada |

---

## Sessão

Configuração centralizada em `backend/config/session.php` e aplicada por
`aq_start_session()` (`backend/bootstrap.php`).

| Item | Valor |
| --- | --- |
| Nome do cookie | `AQUAPULSE_SESSION` |
| `HttpOnly` | sim — inacessível ao JavaScript |
| `SameSite` | `Lax` |
| `Secure` | automático: ativado quando a requisição está sob HTTPS |
| `Path` | `/` |
| Tempo de vida do cookie | `0` — cookie de sessão, descartado ao fechar o navegador |
| Inatividade máxima | **30 minutos** (verificada no servidor) |
| Duração máxima absoluta | **12 horas** (verificada no servidor) |
| `session.use_strict_mode` | `1` — só aceita identificadores gerados pelo PHP |
| `session.use_only_cookies` | `1` — nunca aceita o id pela URL |

Conteúdo gravado na sessão — apenas o necessário:

```php
$_SESSION['auth'] = [
    'user_id'      => 1,          // identificador
    'started_at'   => 1735830000, // criação (limite absoluto)
    'last_seen_at' => 1735830000, // último acesso (limite de inatividade)
];
```

Nome, e-mail e função **não** ficam na sessão: são relidos do repositório a cada
consulta. O hash da senha nunca sai do servidor.

O front-end **não** guarda sessão, token ou senha em `localStorage` /
`sessionStorage`. Todas as chamadas usam `credentials: 'same-origin'`.

---

## Credenciais locais

Válidas **apenas** no ambiente de desenvolvimento:

```
e-mail: demo@aquapulse.local
senha:  Aquapulse@123
função: admin
```

O arquivo `backend/storage/mock/users.php` guarda **somente o hash** gerado por
`password_hash()` — a senha em texto puro não existe em nenhum arquivo do
projeto. A verificação usa `password_verify()`.

---

## Substituição futura pelo banco de dados

O `AuthService` depende apenas de `UserRepositoryInterface`:

```php
public function findByEmail(string $email): ?array;
public function findById(int $id): ?array;
```

Ambos devolvem `['id', 'name', 'email', 'role', 'password_hash']` ou `null`.

Para conectar o banco, basta criar `backend/src/Repositories/PdoUserRepository.php`
implementando essa interface e trocar a instanciação nos três endpoints:

```php
// de:
$auth = new AuthService(new MockUserRepository());
// para:
$auth = new AuthService(new PdoUserRepository($pdo));
```

Nada mais muda: nem a tela, nem o JavaScript, nem os endpoints, nem este
contrato, nem o `AuthService`.
