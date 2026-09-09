---
projeto: Aquapulse
tipo: nucleo
papel: Backend
responsavel: A definir
status: concluido
colaboradores:
  - Frontend
  - Banco de Dados
tags:
  - aquapulse
  - papel/backend
  - tipo/nucleo
---

# Back-end

Onde ficam as regras e os dados. Nada aqui chega ao navegador.

Camada oposta em [[frontend]] · contrato entre as duas em [[api]] · índice em
[[00-inicio]]

---

## Isolamento

`backend/` é incluído **apenas** pelos pontos de entrada em `api/`. O
front-end (`index.php`, `login.php`, `dashboard/*.php`) nunca o inclui.

`backend/.htaccess` bloqueia o acesso direto pela web:

```apache
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
```

Consequência prática: o arquivo de dados simulados não é acessível por URL.

---

## `bootstrap.php`

Carregado por todo endpoint. Quatro responsabilidades:

1. **Autoload** por PSR-4 simples para o namespace `Aquapulse\` →
   `backend/src/`.
2. **Erros nunca vão para a saída** — `display_errors = 0`, `log_errors = 1`,
   `error_reporting(E_ALL)`. A saída é sempre JSON.
3. **Tratamento global**: `set_exception_handler` e `set_error_handler`
   convertem qualquer falha em `500 INTERNAL_ERROR` genérico. Nenhuma resposta
   expõe caminho de arquivo, mensagem interna ou *stack trace* — a mensagem
   real vai para o log do servidor.
4. **Sessão** — `aq_start_session()` e `aq_destroy_session()`.

---

## Sessão

Configuração descrita em `backend/config/session.php`, aplicada por
`aq_start_session()`.

| Item | Valor |
| --- | --- |
| Nome do cookie | `AQUAPULSE_SESSION` |
| `HttpOnly` | sim — inacessível ao JavaScript |
| `SameSite` | `Lax` |
| `Secure` | automático: ativado sob HTTPS (detecta `HTTPS`, porta 443 ou `X-Forwarded-Proto`) |
| `Path` | `/` |
| Tempo de vida do cookie | `0` — descartado ao fechar o navegador |
| Inatividade máxima | 30 minutos (`idle_timeout = 1800`) |
| Duração absoluta | 12 horas (`absolute_timeout = 43200`) |
| `use_strict_mode` | `1` — só aceita id gerado pelo PHP |
| `use_only_cookies` | `1` — nunca aceita o id pela URL |

**Os dois limites são verificados no servidor**, não no cookie. O conteúdo
gravado é o mínimo:

```php
$_SESSION['auth'] = [
    'user_id'      => 1,
    'started_at'   => 1735830000,  // limite absoluto
    'last_seen_at' => 1735830000,  // limite de inatividade
];
```

Nome, e-mail e papel **não** ficam na sessão: são relidos do repositório a cada
consulta. O hash da senha nunca sai do servidor.

---

## Autenticação

`backend/src/Auth/AuthService.php` concentra toda a decisão sobre "quem é o
usuário e se pode entrar". Os arquivos em `api/v1/auth/` são finos de
propósito: só traduzem HTTP.

| Método | Papel |
| --- | --- |
| `attempt($email, $password)` | verifica credenciais com `password_verify()` |
| `login($user)` | cria a sessão e regenera o id (`session_regenerate_id(true)`) |
| `check()` | há sessão válida? |
| `currentUser()` | relê o usuário do repositório |
| `logout()` | encerra a sessão |
| `publicUser($user)` | remove o hash antes de devolver ao cliente |

O serviço depende só de `UserRepositoryInterface` (`findByEmail`, `findById`).
A implementação atual é `MockUserRepository`, sobre
`backend/storage/mock/users.php`: **um usuário fixo, somente leitura, apenas
com o hash**.

O `401` usa a mesma mensagem para e-mail inexistente e senha incorreta, de
propósito — a resposta não revela se um e-mail está cadastrado, e o tempo de
resposta é equalizado.

---

## Proteção de rotas

`backend/src/Support/Guard.php` reaproveita a sessão da etapa 2. **Não existe
um segundo sistema de autenticação.**

| Método | Uso | Sem sessão |
| --- | --- | --- |
| `requireApiSession()` | endpoints | `401` em JSON |
| `requirePageSession()` | páginas do dashboard | redireciona para `login.php?redirect=dashboard` |
| `initials($name)` | avatar da topbar ("Ana Silva" → "AS") | — |

---

## Services — as regras de negócio

| Classe | Linhas | Responsabilidade |
| --- | --- | --- |
| `MonitoringService` | 595 | payload das oito telas de monitoramento: `flow`, `level`, `ph`, `storage`, `precipitation`, `operation`, `flowComparison` |
| `OverviewService` | 263 | visão geral nos dois modos: `single()` e `consolidated()` |
| `DurationForecastService` | 181 | previsão de duração da água |
| `StatusRules` | 138 | classificação de status e rótulos em português |

Todos recebem `MonitoringRepositoryInterface` no construtor. **Nenhum deles
conhece o arquivo simulado, nem o abre, nem sabe que ele existe** — é isso que
permite trocar a fonte de dados sem tocar em regra ([[banco-de-dados]]).

### `StatusRules` — a classificação mora aqui, não no banco

```php
LEVEL_ATTENTION = 80.0;   // % da capacidade
LEVEL_CRITICAL  = 90.0;
PH_MIN = 6.5;
PH_MAX = 8.5;
```

Faixas: normal até 80 %, atenção de 80 a 90 %, crítico acima de 90 %.

`describe()` devolve sempre `key` + `label` + `icon` — **o status nunca é
comunicado só por cor**. `levelNote()` gera a frase do cartão a partir do mesmo
status, para que texto e badge não se contradigam.

Também centraliza os rótulos em português de severidade, status de alerta,
status de relatório e tipo de relatório.

### `DurationForecastService` — isolado de propósito

Algoritmo **demonstrativo**, com fórmula
`(volume útil − reserva técnica) / consumo diário`. Está sozinho em uma classe
justamente para que `estimateDays()` e `project()` sejam substituídos pelo
modelo real sem tocar no resto do sistema.

---

## Support

| Classe | Papel |
| --- | --- |
| `Container` | **ponto único de troca do banco** — monta `monitoring()` e `users()` |
| `Clock` | todas as datas do sistema, sempre em `America/Sao_Paulo` |
| `Validator` | allowlist de todo parâmetro vindo do navegador |
| `Guard` | exige sessão em páginas e endpoints |
| `ApiResponse` | envelope do sistema (`success` + `data` + `meta`) |

`backend/src/Http/` traz `Request` (lê e normaliza método, `Content-Type`,
JSON e e-mail) e `JsonResponse` (envelope da autenticação: `data` / `error`).

### `Clock` — relógio da demonstração

```php
public const DEMO_INSTANT = '2024-05-22 09:30:00';
public const DEMO_MODE    = true;
```

Com `DEMO_MODE = true`, "agora" é fixo — as telas ficam idênticas às
referências visuais em qualquer dia. `lastUpdate()` devolve 2 minutos antes.
Para voltar ao relógio real basta `DEMO_MODE = false`: **nenhum outro arquivo
muda**.

É por isso que o rótulo "Atualizado há 2 min" é calculado no servidor
(`meta.updated_label`) — um cálculo no navegador compararia 2024 com a data
real da máquina e diria "há 2 anos".

### `Validator` — nada do navegador é confiável

Todo parâmetro é comparado com uma **lista fechada**:

```php
PERIODS       = ['24h', '7d', '30d', '90d', '12m'];
SEVERITY      = ['all', 'critical', 'attention', 'info'];
ALERT_STATUS  = ['all', 'new', 'analysis', 'resolved'];
REPORT_STATUS = ['all', 'done', 'processing', 'scheduled'];
REPORT_TYPES  = ['all', 'operational', 'hydrological', 'quality', 'planning'];
```

Valor fora da lista → `400`. Identificador inexistente → `404`. Nunca se
consulta com valor arbitrário.

---

## Repositórios

| Arquivo | Linhas | Papel |
| --- | --- | --- |
| `Contracts/MonitoringRepositoryInterface.php` | — | **o contrato** — 15 métodos |
| `Repositories/Mock/MockMonitoringRepository.php` | 384 | implementação simulada |
| `Repositories/UserRepositoryInterface.php` | — | contrato de usuários — 2 métodos |
| `Repositories/MockUserRepository.php` | — | implementação simulada |
| `storage/mock/monitoring.php` | 402 | dados de monitoramento |
| `storage/mock/users.php` | — | um usuário, só com o hash |

O repositório simulado **não guarda séries prontas**: ele as deriva dos valores
âncora com `wave()`, uma função determinística semeada pelo `crc32` do id da
represa e da métrica. Sem `rand()`, `shuffle()` ou `time()` em lugar nenhum — a
mesma requisição devolve sempre a mesma resposta.

Por construção, **o último ponto de cada série é o valor do KPI** daquela
métrica: gráfico e cartão nunca se contradizem.

Ver [[banco-de-dados]] para a lista de métodos e as entidades esperadas.

---

## `_boot.php` — inicialização dos endpoints

Todo endpoint do sistema inclui `api/v1/_boot.php`. `aq_api_boot()` faz, em um
só lugar:

1. valida o método HTTP (somente `GET` nos endpoints de leitura);
2. exige sessão via `Guard::requireApiSession()`;
3. devolve o repositório por `Container::monitoring()`.

Ele não emite nada por conta própria — quem responde é cada endpoint.

---

---

## Entregas desta função (10)

Núcleo de função · cor **#8B5CF6** · voltar para [[equipe]]

| Entrega | Situação |
| --- | --- |
| [[autenticacao]] | concluída |
| [[controle-de-sessao]] | concluída |
| [[endpoints-da-api]] | concluída |
| [[servicos-de-monitoramento]] | concluída |
| [[regras-de-situacao-operacional]] | concluída |
| [[validacao-de-requisicoes]] | concluída |
| [[envelopes-e-tratamento-de-erros]] | concluída |
| [[previsao-de-duracao-da-agua]] | demonstrativa por decisão |
| [[relogio-da-aplicacao]] | demonstrativo por decisão |
| [[repositorio-de-dados-simulados]] | provisório por decisão |

## Integrações reais

- Publica o contrato de [[api]], consumido pelo [[frontend]].
- Depende do [[contrato-do-repositorio]], mantido por [[banco-de-dados]].
- Implementa os [[criterios-operacionais]] definidos por [[analista-de-negocios]].

---

Ver também: [[equipe]] · [[api]] · [[banco-de-dados]] · [[arquitetura]] · [[decisoes]] · [[registro-de-entregas]]
