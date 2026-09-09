---
projeto: Aquapulse
tipo: backlog
papel: Granmaster
responsavel: A definir
status: em-andamento
colaboradores:
  - Frontend
  - Backend
  - Banco de Dados
  - Analista de Qualidade
tags:
  - aquapulse
  - papel/granmaster
  - tipo/backlog
---

# Tarefas

Trabalho que ainda precisa ser realizado, levantado a partir do código e dos
avisos deixados nos próprios arquivos.

Situação de cada frente em [[status-atual]] · índice em [[00-inicio]]

---

## 1. Fechar a correção dos gráficos

A frente em andamento. Doze problemas já foram corrigidos ([[status-atual]]),
mas restam dois itens.

### 1.1 Adotar `AqCharts.guard()` nas telas restantes

`guard(id, fn)` isola a falha de um gráfico para que ela não derrube os
seguintes da mesma tela. Foi criado em `charts.js` e exposto na API pública,
mas só é usado em **2 das 13 telas com gráfico**:

| Tela | Arquivo | Montagens | Usa `guard` |
| --- | --- | --- | --- |
| Alertas | `pages/alerts.js` | 1 | sim |
| Níveis | `pages/levels.js` | 3 | sim |
| Visão geral | `pages/overview.js` | 3 | **não** |
| Comparativo | `pages/comparison.js` | 3 | **não** |
| Vazão | `pages/flow.js` | 2 | **não** |
| Nível | `pages/level.js` | 2 | **não** |
| pH | `pages/ph.js` | 2 | **não** |
| Volume | `pages/storage.js` | 2 | **não** |
| Duração | `pages/duration.js` | 2 (`create` + `gauge`) | **não** |
| Precipitação | `pages/rain.js` | 1 | **não** |
| Operacional | `pages/operation.js` | 1 (`donut`) | **não** |

As onze telas continuam chamando `G.create()`, `G.gauge()` e `G.donut()`
diretamente. O `try/catch` de `monitor-page.js` já evita que a tela inteira
caia, mas sem `guard` a falha de um gráfico ainda interrompe a montagem dos
seguintes **dentro do mesmo `render()`**.

### 1.2 Commitar o trabalho

São 22 arquivos modificados e ainda não commitados (+467 / −84). Enquanto
não houver commit, o deploy automático não leva as correções ao ar — ver
[[arquitetura]].

---

## 2. Banco de dados

A etapa 4 inteira. O ponto de troca já está preparado ([[banco-de-dados]]):

- [ ] modelar as tabelas a partir das entidades documentadas;
- [ ] criar `backend/src/Repositories/Pdo/PdoMonitoringRepository.php` com os
      **15 métodos** da interface;
- [ ] criar `backend/src/Repositories/PdoUserRepository.php` com os **2
      métodos** de `UserRepositoryInterface`;
- [ ] trocar as duas linhas em `Container.php`;
- [ ] apagar `MockMonitoringRepository.php`, `MockUserRepository.php` e
      `backend/storage/mock/`;
- [ ] trocar `Clock::DEMO_MODE` para `false`;
- [ ] substituir as coordenadas demonstrativas pelas do cadastro real;
- [ ] substituir `estimateDays()` e `project()` de `DurationForecastService`
      pelo modelo real;
- [ ] garantir que o KPI continue vindo da mesma fonte da série (último ponto
      da série = valor do KPI).

---

## 3. Endpoints de escrita

Não existe nenhum endpoint `POST` / `PATCH` no sistema. Estas ações hoje só
valem para a sessão do navegador e avisam isso na tela:

| Tela | Ação | Endpoint necessário |
| --- | --- | --- |
| Alertas | assumir alerta | `PATCH` de responsável |
| Alertas | marcar como resolvido | `PATCH` de status |
| Configurações | salvar preferências e limites | `PATCH` de configurações |
| Configurações | adicionar empresa / represa | `POST` de cadastro |
| Relatórios | gerar novo relatório | `POST` de geração (hoje o PDF usa a impressão do navegador e o CSV é montado no cliente) |
| Operacional | abrir chamado | `POST` de chamado |

Depende do item 2 — não há onde persistir antes do banco.

---

## 4. Segurança antes de qualquer uso real

Pendências registradas em [`docs/login-stage.md`](../login-stage.md):

- [ ] **Proteção contra força bruta.** Não há limite de tentativas, atraso
      progressivo, bloqueio de conta nem CAPTCHA — hoje é possível tentar
      senhas indefinidamente.
- [ ] **Token CSRF por sessão.** A defesa atual é `SameSite=Lax` mais o
      `Content-Type: application/json` exigido. Recomendável quando houver
      operações de escrita (item 3).
- [ ] **"Lembrar de mim" funcional.** O campo existe na interface, mas não
      altera a duração da sessão. Exige um token persistente próprio.
- [ ] **Recuperação de senha.** O elemento está preparado visualmente e
      informa que a funcionalidade virá depois — não há rota quebrada.
- [ ] **HTTPS.** O cookie só recebe `Secure` sob HTTPS; em produção o site
      inteiro precisa estar em HTTPS.
- [ ] **Registro de auditoria.** Não há log de tentativas de acesso.
- [ ] **Armazenamento de sessão.** Sessão em arquivos não serve para múltiplos
      servidores.
- [ ] **Autorização por papel.** O campo `role` é devolvido, mas nada ainda
      restringe acesso com base nele.
- [ ] **Cadastro de usuários.** Hoje há um único usuário fixo, somente leitura.

---

## 5. Landing page

- [ ] **Solicitar demonstração** ainda é um `button` com aviso de
      indisponibilidade (`includes/sections/vantagens.php:57`). Definir o
      destino: formulário, e-mail ou página de contato.
- [ ] O painel da seção "sistema" é imagem estática
      (`assets/images/dashboard-aquapulse.webp`). Avaliar se vale trocar por
      uma prévia real agora que o dashboard existe.

---

## 6. Documentação

- [ ] `README.md` fala em "17 endpoints" na etapa 3; a contagem real é **15 do
      sistema + 3 de autenticação = 18**.
- [ ] `docs/api-contract.md` traz um exemplo com `"name": "Ana Silva"`,
      enquanto `validation/auth-test-results.txt` registra
      `"Usuário de demonstração"` — o valor atual em
      `backend/storage/mock/users.php` é **Ana Silva**. O arquivo de validação
      é anterior à mudança e não foi regerado.
- [ ] Regerar `validation/auth-test-results.txt` quando a autenticação mudar.

---

## Fora de escopo por decisão

Não são pendências: foram decididos assim ([[decisoes]]).

- Framework, bundler ou gerenciador de pacotes.
- CDN para Chart.js e Leaflet.
- Dados variando a cada carregamento.
- Publicar `docs/`, `reference/` e `validation/` no servidor.

---

Ver também: [[status-atual]] · [[banco-de-dados]] · [[decisoes]] · [[api]]

Função responsável: [[granmaster]] · [[equipe]] · [[coordenacao-do-backlog]]
