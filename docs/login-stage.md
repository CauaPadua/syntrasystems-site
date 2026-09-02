# Etapa 2 — Tela de login com autenticação PHP (sem banco de dados)

Esta etapa entrega a tela de login e uma autenticação PHP funcional, sustentada
temporariamente por um repositório simulado. **Não há banco de dados, cadastro,
recuperação de senha, dashboard nem sistema interno.**

---

## Separação entre front-end e back-end

A regra é simples: **o front-end nunca inclui arquivos de `backend/`**, e a
única ponte entre os dois lados são os três endpoints de `api/v1/auth/`.

### Front-end — o que o navegador recebe

| Arquivo | Papel |
| --- | --- |
| `login.php` | Renderiza a tela. Só monta HTML: nenhuma regra de autenticação, sessão ou acesso a usuários. |
| `assets/css/login.css` | Estilos exclusivos do login. |
| `assets/css/style.css` | Tokens e componentes compartilhados com a landing page (carregado antes do `login.css`). |
| `assets/js/login.js` | Conversa com a API por `fetch`. Valida campos, controla estados e troca as vistas. |
| `assets/images/` | Imagens públicas. |
| `includes/config.php`, `includes/icons.php` | Utilitários de apresentação reaproveitados da landing page (escape de HTML e ícones SVG). |

### Back-end — o que nunca chega ao navegador

| Arquivo | Papel |
| --- | --- |
| `api/v1/auth/login.php` | **Ponto de entrada.** Valida método, `Content-Type` e formato; delega ao `AuthService`. |
| `api/v1/auth/logout.php` | **Ponto de entrada.** Encerra a sessão. |
| `api/v1/auth/me.php` | **Ponto de entrada.** Devolve o usuário da sessão. |
| `backend/bootstrap.php` | Autoload, supressão de erros na saída, tratamento global de exceções, início da sessão. |
| `backend/config/session.php` | Configuração centralizada da sessão (cookie e limites de expiração). |
| `backend/src/Auth/AuthService.php` | **Regras de autenticação.** Verifica credenciais, cria/lê/encerra a sessão. |
| `backend/src/Http/JsonResponse.php` | Monta todas as respostas JSON (cabeçalhos, envelope, código HTTP). |
| `backend/src/Http/Request.php` | Lê e normaliza a requisição (método, `Content-Type`, JSON, e-mail). |
| `backend/src/Repositories/UserRepositoryInterface.php` | **Contrato de acesso a usuários** — o ponto de troca para o banco. |
| `backend/src/Repositories/MockUserRepository.php` | Implementação temporária, somente leitura. |
| `backend/storage/mock/users.php` | Um usuário fixo, **apenas com o hash** da senha. |

Os arquivos em `api/` são finos de propósito: eles só traduzem HTTP. Toda
decisão sobre "quem é o usuário e se pode entrar" está no `AuthService`.

---

## Como executar no XAMPP

**Opção A — pasta do Apache (recomendada)**

1. Copie a pasta do projeto para `C:\xampp\htdocs\aquapulse`.
2. Inicie o **Apache** pelo painel do XAMPP.
3. Acesse <http://localhost/aquapulse/login.php>.

**Opção B — servidor embutido do PHP**

Na raiz do projeto:

```bash
C:\xampp\php\php.exe -S localhost:8000 -t .
```

Depois acesse <http://localhost:8000/login.php>.

As chamadas da API usam caminhos **relativos** (`api/v1/auth/...`), então as duas
opções funcionam — inclusive com o projeto em uma subpasta.

> Requisitos: PHP 8.0 ou superior, com as extensões `session` e `json`
> (ambas já vêm ativas no XAMPP).

---

## Credenciais locais

```
e-mail: demo@aquapulse.local
senha:  Aquapulse@123
```

Servem **somente** para desenvolvimento. O projeto guarda apenas o hash
(`password_hash`); a senha em texto puro não existe em nenhum arquivo de código.

---

## Como testar

### Login

1. Abra `login.php`.
2. Informe o e-mail e a senha acima e clique em **Entrar**.
3. O botão fica desabilitado e mostra "Entrando…" durante a requisição.
4. Em caso de sucesso, a tela troca para o estado **"Acesso validado"**, com
   nome, e-mail e perfil do usuário.

Para conferir pelo terminal:

```bash
curl -i -c cookies.txt -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@aquapulse.local","password":"Aquapulse@123"}' \
  http://localhost:8000/api/v1/auth/login.php
```

### Sessão ativa

Atualize a página (F5): `login.js` consulta `me.php` no carregamento e, havendo
sessão, exibe direto o estado autenticado.

```bash
curl -i -b cookies.txt http://localhost:8000/api/v1/auth/me.php
```

### Logout

Clique em **Encerrar sessão**. A tela volta ao formulário.

```bash
curl -i -b cookies.txt -X POST http://localhost:8000/api/v1/auth/logout.php
curl -i -b cookies.txt http://localhost:8000/api/v1/auth/me.php   # agora responde 401
```

### Casos de erro

| Teste | Resultado esperado |
| --- | --- |
| Senha incorreta | `401 INVALID_CREDENTIALS` |
| E-mail inexistente | `401 INVALID_CREDENTIALS` (mesma mensagem) |
| Campos vazios | `422 VALIDATION_ERROR` |
| JSON malformado | `400 INVALID_JSON` |
| `Content-Type` errado | `400 INVALID_CONTENT_TYPE` |
| `GET` em `login.php` | `405 METHOD_NOT_ALLOWED` |

Os resultados registrados estão em `validation/auth-test-results.txt`.

---

## Como o banco substituirá o repositório simulado

O ponto de troca já está isolado em `UserRepositoryInterface`:

```php
interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?array;
    public function findById(int $id): ?array;
}
```

Passos da próxima etapa:

1. Criar a tabela de usuários com, no mínimo, as colunas
   `id`, `name`, `email`, `role` e `password_hash`.
2. Criar `backend/src/Repositories/PdoUserRepository.php` implementando a
   interface, devolvendo o mesmo formato de array.
3. Trocar uma linha em cada um dos três endpoints:

   ```php
   // de:
   $auth = new AuthService(new MockUserRepository());
   // para:
   $auth = new AuthService(new PdoUserRepository($pdo));
   ```

4. Apagar `MockUserRepository.php` e `backend/storage/mock/`.

**Não mudam:** `login.php`, `login.js`, `login.css`, os endpoints, o contrato da
API e o `AuthService`.

---

## O que ainda NÃO está pronto para produção

Esta etapa é intencionalmente incompleta. Antes de qualquer uso real:

- **Repositório simulado.** Um único usuário fixo em arquivo PHP, sem cadastro,
  edição ou remoção. Precisa virar banco de dados.
- **Sem proteção contra força bruta.** Não há limite de tentativas, atraso
  progressivo, bloqueio de conta nem CAPTCHA. Hoje é possível tentar senhas
  indefinidamente.
- **Sem proteção CSRF explícita.** A defesa atual é `SameSite=Lax` mais o
  `Content-Type: application/json` exigido. Um token CSRF por sessão é
  recomendável quando houver mais operações de escrita.
- **"Lembrar de mim" é apenas visual.** O campo existe na interface, mas ainda
  não altera a duração da sessão. Exigirá um token persistente próprio.
- **"Esqueci minha senha" não existe.** O elemento está preparado visualmente e
  informa que a funcionalidade virá depois — não há rota quebrada.
- **Sem HTTPS no ambiente local.** O cookie só recebe `Secure` sob HTTPS; em
  produção o site inteiro precisa estar em HTTPS.
- **Sem registro de auditoria.** Não há log de tentativas de acesso.
- **Sessão em arquivos.** O armazenamento padrão do PHP não é adequado para
  múltiplos servidores.
- **Papéis sem autorização.** O campo `role` é devolvido, mas nada ainda
  restringe acesso com base nele.
