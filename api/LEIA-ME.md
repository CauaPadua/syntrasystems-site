# api/ — a porta de entrada dos dados (BACK-END)

Cada arquivo aqui é um **endpoint**: um endereço que o JavaScript chama para
pedir dados. A resposta é sempre **JSON**, nunca HTML.

Exemplo real: quando o painel abre, o navegador chama
`http://localhost/Aquapulse/api/v1/overview.php` e recebe de volta os números
da Visão geral em formato de texto estruturado.

---

## Por que a pasta se chama `v1`

É a **versão 1** da API. Se um dia o formato das respostas precisar mudar de um
jeito que quebraria as telas antigas, cria-se `v2/` ao lado e as duas convivem
enquanto a migração acontece. É uma prática comum em APIs.

---

## O arquivo mais importante: `_boot.php`

O underline no começo do nome é uma convenção: indica que **não é um endpoint**,
é um arquivo de apoio incluído pelos outros.

Todo endpoint começa chamando ele, e ele garante três coisas de uma vez:

1. Carrega o back-end (`backend/bootstrap.php`).
2. Confere se o método HTTP é permitido (leitura só aceita `GET`).
3. Confere se existe **sessão válida**. Sem ela, responde `401` e para ali.

Concentrar isso em um lugar só evita repetir a mesma verificação em cada um dos 18 endpoints
e, principalmente, evita esquecer a verificação em algum deles.

---

## Os endpoints

| Arquivo | Responde |
|---|---|
| `auth/login.php` | Recebe e-mail e senha, cria a sessão |
| `auth/logout.php` | Encerra a sessão |
| `auth/me.php` | Diz quem está logado (ou `401`) |
| `overview.php` | Todos os números da Visão geral |
| `reservoirs.php` | Lista de represas |
| `companies.php` | Lista de empresas |
| `alerts.php` | Alertas ativos |
| `reports.php` | Relatórios disponíveis |
| `settings.php` | Configurações do usuário |
| `map/reservoirs.php` | Coordenadas das represas para o mapa |
| `monitoring/level.php` | Série histórica de nível |
| `monitoring/flow.php` | Vazão |
| `monitoring/flow-comparison.php` | Comparativo de vazão entre períodos |
| `monitoring/ph.php` | pH da água |
| `monitoring/storage.php` | Volume armazenado |
| `monitoring/precipitation.php` | Chuva |
| `monitoring/duration.php` | Projeção de duração da água |
| `monitoring/operation.php` | Situação operacional |

---

## Como ver um endpoint funcionando

Com o Apache ligado e **já logado no painel**, basta abrir o endereço no
navegador. Sem login, a resposta será:

```json
{"success":false,"data":null,"meta":[],"error":{"code":"UNAUTHENTICATED","message":"Sessão não encontrada ou expirada. Faça login novamente."}}
```

Esse `401` não é um erro do sistema: é a proteção funcionando.
