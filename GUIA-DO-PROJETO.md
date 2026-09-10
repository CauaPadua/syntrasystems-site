# Guia do projeto Aquapulse

Este arquivo é o mapa do sistema. Leia daqui de cima para baixo: cada seção
explica uma camada, e no fim há um roteiro de perguntas prováveis.

Dentro de cada pasta principal existe um `LEIA-ME.md` com o detalhe daquela
pasta. Este guia é a visão geral.

---

## 1. O que o Aquapulse faz

É um sistema de monitoramento de represas. Ele tem duas partes visíveis:

- **A página pública** (`index.php`): apresenta o produto para quem chega ao
  site. É a "vitrine".
- **O painel interno** (`dashboard/`): protegido por login, mostra os números
  das represas em gráficos, tabelas e mapa.

Os dados hoje são **simulados**. A seção 6 explica por quê e como o banco de
dados entra depois sem reescrever o sistema.

---

## 2. Front-end e back-end, em uma frase cada

| | Onde roda | Linguagem | Do que cuida |
|---|---|---|---|
| **Front-end** | No navegador de quem acessa | HTML, CSS, JavaScript | Desenhar a tela e reagir a cliques |
| **Back-end** | No servidor (XAMPP/Apache) | PHP | Guardar as regras, decidir os dados e proteger o acesso |

A regra que separa os dois neste projeto:

> O front-end **nunca** decide um número. Ele pede ao back-end e desenha o que
> vier. O back-end **nunca** desenha uma tela. Ele responde dados em JSON.

Essa separação é o motivo de existirem as pastas `api/` e `backend/`.

---

## 3. Mapa das pastas

### Front-end

| Pasta | O que tem dentro |
|---|---|
| `assets/css/` | Folhas de estilo. Cor, tamanho, posição de tudo. |
| `assets/js/` | Scripts do navegador. Menu, gráficos, carrossel, chamadas à API. |
| `assets/images/` | Fotografias e logotipos usados nas telas. |
| `assets/vendor/` | Bibliotecas de terceiros baixadas para dentro do projeto: Chart.js (gráficos) e Leaflet (mapa). |
| `includes/` | Pedaços da página pública: cabeçalho, rodapé e cada seção da home. |
| `dashboard/` | As telas do painel interno, uma por arquivo. |
| `index.php` | A página pública. Monta a home juntando os pedaços de `includes/`. |
| `login.php` | A tela de entrada. |

### Back-end

| Pasta | O que tem dentro |
|---|---|
| `api/v1/` | Os "endereços" que o JavaScript chama para pedir dados. Cada arquivo responde um JSON. |
| `backend/src/` | O código de verdade: regras, cálculos e acesso aos dados. |
| `backend/storage/mock/` | Os dados simulados que alimentam o sistema hoje. |
| `backend/config/` | Configuração da sessão (o "crachá" de quem está logado). |
| `backend/bootstrap.php` | Ligação inicial do back-end. Carrega as classes e prepara tudo. |

### Apoio (não faz parte do site publicado)

| Pasta | O que tem dentro |
|---|---|
| `docs/` | Documentação técnica escrita durante o desenvolvimento. |
| `reference/` | Imagens e briefings de referência visual usados como base do design. |
| `validation/` | Capturas de tela e resultados dos testes feitos. |

Essas três pastas ficam de fora do envio para o servidor. Quem define isso é
o arquivo `.github/workflows/`, na linha `exclude`.

---

## 4. Como um pedido viaja pelo sistema

Este é o trajeto completo quando o painel carrega a Visão geral. Vale a pena
decorar: é a resposta para "explique a arquitetura do seu projeto".

```
1. NAVEGADOR   dashboard/index.php  ......  a tela é desenhada vazia
2. NAVEGADOR   assets/js/pages/overview.js   pede os dados
3. NAVEGADOR   assets/js/api-client.js  ...  faz a chamada HTTP
                      |
                      v
4. SERVIDOR    api/v1/overview.php  .......  ponto de entrada
5. SERVIDOR    api/v1/_boot.php  ..........  confere método e sessão
6. SERVIDOR    backend/src/Support/Container.php   escolhe a fonte de dados
7. SERVIDOR    backend/src/Services/OverviewService.php   aplica as regras
8. SERVIDOR    .../Repositories/Mock/MockMonitoringRepository.php   busca
9. SERVIDOR    backend/storage/mock/monitoring.php   os dados simulados
                      |
                      v  devolve JSON
10. NAVEGADOR  assets/js/charts.js  .......  desenha o gráfico
```

Repare que **cada camada só conversa com a vizinha**. A tela não sabe de onde
o dado veio; o repositório não sabe que existe um gráfico.

---

## 5. Onde fica cada tela

| Endereço no navegador | Arquivo |
|---|---|
| `/` | `index.php` |
| `/login.php` | `login.php` |
| `/dashboard/` | `dashboard/index.php` (Visão geral) |
| `/dashboard/niveis.php` | Níveis |
| `/dashboard/alertas.php` | Alertas |
| `/dashboard/mapas.php` | Mapas |
| `/dashboard/relatorios.php` | Relatórios |
| `/dashboard/configuracoes.php` | Configurações |
| `/dashboard/monitoramento/*.php` | As oito telas de monitoramento (nível, vazão, pH, volume, precipitação, comparativo, duração, operacional) |

---

## 6. Decisões técnicas e o porquê

Professores costumam perguntar "por que você fez assim". Estas são as
respostas honestas.

### Por que ainda não há banco de dados

O sistema foi construído com **Repository Pattern**. Os dados são pedidos
através de uma interface (`MonitoringRepositoryInterface`), não diretamente de
um arquivo ou tabela. Hoje quem atende essa interface é
`MockMonitoringRepository`, que lê dados simulados.

Quando o banco existir, basta trocar **uma linha** em
`backend/src/Support/Container.php`. Nenhum endpoint, nenhuma tela e nenhum
JavaScript precisa mudar. O próprio arquivo tem esse comentário no topo.

Isso é o princípio da **inversão de dependência**: o código de regra depende de
uma abstração, não de um detalhe.

### Por que a API responde JSON e nunca HTML

Se a sessão expira, o endpoint devolve `401` em JSON. Se devolvesse uma página
de login em HTML, o JavaScript tentaria desenhar um gráfico com o HTML do login
e quebraria de um jeito confuso. Separar formatos evita esse erro.

### Por que Chart.js e Leaflet estão dentro do projeto

Ficam em `assets/vendor/`, baixados, e não vindos de um CDN. Assim o sistema
funciona sem internet e a versão nunca muda sozinha por atualização externa.

### Por que a senha não aparece no JavaScript

O login é validado no servidor (`backend/src/Auth/AuthService.php`). O que volta
para o navegador é apenas um **cookie de sessão HttpOnly** — um cookie que o
JavaScript não consegue ler. Isso protege contra roubo de sessão por script
malicioso.

### Por que a página pública e o painel têm CSS separado

`assets/css/style.css` serve a página pública e o login. `dashboard.css` serve o
painel. Assim, mexer no painel não corre o risco de estragar a home.

---

## 7. Como rodar o projeto

1. Abrir o XAMPP e ligar o **Apache**.
2. O projeto precisa estar dentro de `htdocs` (ou ter um atalho apontando para lá).
3. Acessar `http://localhost/Aquapulse/`.
4. Para entrar no painel, usar o usuário de demonstração cadastrado em
   `backend/storage/mock/users.php`.

---

## 8. Perguntas prováveis na apresentação

**"Isso é front-end ou back-end?"**
Os dois. O front-end são as pastas `assets/` e `includes/` mais as telas em
`dashboard/`. O back-end são `api/` e `backend/`.

**"Onde estão as regras de negócio?"**
Em `backend/src/Services/`. Por exemplo, `StatusRules.php` decide quando uma
represa está em situação normal, de atenção ou crítica.

**"Como você garante que só quem fez login vê o painel?"**
Cada endpoint chama `Guard` através do `_boot.php`, que verifica a sessão antes
de responder. Sem sessão válida, retorna 401 e o JavaScript devolve o usuário
ao login.

**"E se dois usuários pedirem dados ao mesmo tempo?"**
Cada requisição é independente e a sessão é individual por cookie. O
`api-client.js` ainda cancela a requisição anterior do mesmo escopo, para uma
resposta atrasada não sobrescrever a atual.

**"Qual foi a maior dificuldade?"**
Uma resposta honesta e específica vale mais que uma genérica. Exemplos reais
deste projeto: manter os gráficos consistentes ao trocar de represa sem
requisições antigas sobrescreverem as novas; e garantir que o carrossel da home
não acumule temporizadores ao sair e voltar da seção.

---

## 9. Glossário rápido

| Termo | Em português simples |
|---|---|
| **Endpoint** | Um endereço do servidor que responde dados, não uma página. |
| **JSON** | Formato de texto para trocar dados entre servidor e navegador. |
| **Sessão** | A memória de que você está logado, guardada no servidor. |
| **Cookie HttpOnly** | Cookie que o JavaScript não consegue ler. Mais seguro. |
| **Interface** (PHP) | Um contrato: diz quais métodos uma classe precisa ter. |
| **Repository** | Camada que busca dados, escondendo de onde eles vêm. |
| **Service** | Camada que aplica as regras do negócio. |
| **Mock** | Dado simulado, usado no lugar do dado real. |
| **Responsivo** | Layout que se adapta ao tamanho da tela. |
| **CDN** | Servidor externo que entrega bibliotecas. Aqui não usamos. |
