---
projeto: Aquapulse
tipo: estrutural
papel: Granmaster
responsavel: A definir
status: concluido
colaboradores:
  - Frontend
  - Backend
  - Banco de Dados
tags:
  - aquapulse
  - papel/granmaster
  - tipo/estrutural
---

# Decisões

Escolhas de projeto que já estão no código, com o motivo de cada uma. Registrar
o **porquê** evita que uma decisão deliberada seja desfeita por engano.

Índice em [[00-inicio]]

---

## Arquitetura e stack

### PHP puro, sem framework, bundler ou dependência externa

O projeto usa PHP, HTML semântico, CSS e JavaScript sem framework. Não há
`composer.json`, `package.json` nem etapa de build.

**Por quê:** o destino é hospedagem compartilhada (InfinityFree, deploy por
FTP). Qualquer etapa de build exigiria infraestrutura que o ambiente não tem.

### Chart.js e Leaflet versionados, sem CDN

Ficam em `assets/vendor/` dentro do repositório.

**Por quê:** o sistema funciona sem internet externa e não depende de um
terceiro ficar no ar. Os tiles do mapa são a única exceção — vêm do
OpenStreetMap em tempo de execução, sem chave paga.

### O front-end nunca inclui `backend/`

A única ponte entre os dois lados são os endpoints em `api/v1/`, e
`backend/.htaccess` bloqueia o acesso direto pela web.

**Por quê:** garante que regra de negócio e dados nunca vazem para o navegador
por engano, e mantém as duas camadas trocáveis de forma independente.

### Páginas entregam esqueleto, dados chegam por `fetch`

Nenhum dado vem embutido no HTML das telas do dashboard.

**Por quê:** o mesmo endpoint serve a tela e serviria a qualquer outro cliente;
trocar filtro não recarrega a página; e a fronteira entre front e back fica
óbvia.

---

## Dados e migração para o banco

### Troca do banco por interface, não por refatoração

Services e endpoints dependem apenas de `MonitoringRepositoryInterface` e
`UserRepositoryInterface`. `Container.php` é o ponto único de instanciação.

**Por quê:** conectar o banco vira uma linha em `Container.php`, sem tocar em
tela, JavaScript, endpoint ou contrato. Ver [[banco-de-dados]].

### Dados simulados determinísticos, nunca aleatórios

Sem `rand()`, `mt_rand()`, `shuffle()` ou `time()`. As séries são derivadas dos
valores âncora por `wave()`, semeada pelo `crc32` do id da represa e da
métrica.

**Por quê:** a mesma requisição devolve sempre a mesma resposta. Apertar
*Atualizar* não muda os números — como não mudaria com dados reais parados.
Números dançando a cada carga esconderiam bugs reais.

### O último ponto da série é o valor do KPI

Garantido por construção no repositório simulado.

**Por quê:** gráfico e cartão nunca se contradizem. É uma regra a **preservar**
quando o banco entrar, não um detalhe da simulação.

### Relógio fixo em modo demonstração

`Clock::DEMO_MODE = true`, `DEMO_INSTANT = '2024-05-22 09:30:00'`.

**Por quê:** mantém as telas idênticas às referências visuais em qualquer dia.
Voltar ao relógio real é `DEMO_MODE = false` — **nenhum outro arquivo muda**.

### `updated_label` calculado no servidor

O rótulo "Atualizado há 2 min" vem pronto em `meta.updated_label`.

**Por quê:** o relógio da demonstração é fixo; um cálculo no navegador
compararia 2024 com a data real da máquina e diria "há 2 anos". Quando o
relógio voltar a ser real, o campo continua válido — basta manter o cálculo no
servidor.

### Nenhum endpoint de escrita nesta etapa

Assumir alerta, resolver alerta, salvar configurações, gerar relatório e abrir
chamado são ações **demonstrativas**, com aviso na própria tela.

**Por quê:** escrever exige onde persistir. Criar endpoints `POST` sobre um
arquivo PHP somente leitura produziria uma API que mente sobre o que faz.
Decisão consciente de adiar — ver [[tarefas]].

### `DurationForecastService` isolado em uma classe

O algoritmo de previsão de duração da água vive sozinho, com `estimateDays()`
e `project()` marcados para substituição.

**Por quê:** é o único ponto do sistema com um modelo demonstrativo de fato.
Isolá-lo permite trocar o modelo sem tocar em mais nada.

---

## Segurança

### Sessão em cookie `HttpOnly`, nada no `localStorage`

O front-end não guarda sessão, token ou senha. `me.php` é a única forma de
saber se existe sessão.

**Por quê:** um token acessível ao JavaScript é um token exposto a XSS.

### Limites de sessão verificados no servidor

Cookie com `lifetime = 0`; inatividade de 30 min e duração absoluta de 12 h
conferidas em `aq_start_session()`.

**Por quê:** limite que vive no cookie é limite que o cliente controla.

### A sessão guarda o mínimo

Só `user_id`, `started_at` e `last_seen_at`. Nome, e-mail e papel são relidos
do repositório a cada consulta.

**Por quê:** dado duplicado na sessão fica velho; e o hash da senha nunca
precisa sair do servidor.

### `401` idêntico para e-mail inexistente e senha errada

Mesma mensagem e tempo de resposta equalizado.

**Por quê:** a resposta não revela se um e-mail está cadastrado.

### Validação por allowlist, sempre

Todo parâmetro é comparado com uma lista fechada em `Validator.php`. Fora da
lista, `400`.

**Por quê:** nada vindo do navegador é confiável. Nunca se consulta com valor
arbitrário.

### Erros nunca vão para a saída

`display_errors = 0`; exceções viram `500 INTERNAL_ERROR` genérico, com o texto
real no log do servidor.

**Por quê:** nenhuma resposta expõe caminho de arquivo, *stack trace*, consulta
ou dado de sessão.

### Um sistema de autenticação só

O sistema interno reaproveita a sessão da etapa 2 via `Guard`. Página sem
sessão redireciona; endpoint sem sessão responde **401 em JSON**.

**Por quê:** dois mecanismos de login divergiriam. E um endpoint que devolve
HTML de login quebra o cliente JavaScript, que espera JSON.

---

## Interface

### Status nunca depende só de cor

`StatusRules::describe()` devolve sempre `key` + `label` + `icon`.

**Por quê:** acessibilidade — cor sozinha não comunica para quem não a
distingue.

### Todo bloco de dados tem quatro estados

`loading`, `ready`, `empty`, `error` — montados por `aq_states()` e trocados
por `AqShell.setState()`.

**Por quê:** um cartão em branco não diz nada ao usuário. O estado `error`
ainda traz "Tentar novamente".

### Gráfico sempre acompanhado de alternativa textual

`AqCharts.describe()` gera a descrição da série; as telas oferecem "Ver
tabela" com os mesmos dados.

**Por quê:** acessibilidade, e também depuração — dá para conferir o número
sem ler o desenho.

### Requisição superada é cancelada

`AbortController`, um por escopo, em `api-client.js`.

**Por quê:** trocar filtro depressa fazia uma resposta antiga chegar depois da
nova e sobrescrever a tela.

### Falha de um gráfico não derruba os outros

`AqCharts.guard()` e `AqCharts.scopeReady()` isolam cada montagem; o `render()`
das telas roda dentro de `try/catch`.

**Por quê:** uma exceção no meio da montagem interrompia tudo o que vinha
depois — os gráficos seguintes ficavam em branco, com título e legenda
visíveis, sem nenhuma pista do motivo. Ver [[status-atual]].

### Versão dos assets pelo `mtime`, não por constante

`aq_asset_version()` usa o timestamp mais recente de `assets/css` e
`assets/js`.

**Por quê:** a constante `2.0.0` escrita à mão em dois lugares não mudava ao
editar CSS/JS. O navegador seguia executando JavaScript antigo contra a API
nova — uma das causas de gráfico "vazio" depois de uma atualização.

### Textos da landing centralizados em constantes

`includes/config.php` guarda a copy; as seções só montam a estrutura.

**Por quê:** ajustar texto sem mexer em HTML.

---

## Publicação

### `docs/`, `reference/` e `validation/` não vão para o servidor

Excluídos no `.github/workflows/deploy.yml`.

**Por quê:** documentação e evidências de validação são para o repositório, não
para o público. Continuam versionadas.

### `.obsidian/` fora do Git

Adicionado ao `.gitignore`.

**Por quê:** são preferências pessoais de editor (tema, layout de painéis,
arquivos abertos) — mudam a cada sessão e não dizem respeito a mais ninguém. As
notas Markdown continuam versionadas.

---

Ver também: [[arquitetura]] · [[backend]] · [[api]] · [[tarefas]]

Função responsável: [[granmaster]] · [[equipe]] · [[integracao-entre-areas]]
