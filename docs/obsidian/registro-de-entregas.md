---
projeto: Aquapulse
tipo: registro
tags:
  - aquapulse
  - tipo/geral
---

# Registro de entregas

Todas as 42 entregas do projeto, com a evidência que comprova cada uma.
Voltar para [[equipe]] · índice em [[00-inicio]]

**Critério:** só entrou nesta tabela o que existe como arquivo, documento ou
commit no repositório. Nenhuma entrega foi inferida.

**Responsável:** todos como "A definir" — nenhum documento atribui pessoas a
funções, e o histórico Git comprova autoria de commit, não divisão de papéis
([[equipe]]).

## Frontend — 10 entregas

| Entrega | Função principal | Responsável | Status | Evidência | Nota |
|---|---|---|---|---|---|
| Landing page | Frontend | A definir | concluído | `index.php`, `includes/sections/` (4 arquivos), `main.js`, `style.css`, commit `8db3bde` | [[landing-page]] |
| Tela de login | Frontend | A definir | concluído | `login.php`, `login.css`, `login.js`, `docs/login-stage.md` | [[tela-de-login]] |
| Shell do dashboard | Frontend | A definir | concluído | `dashboard/includes/page.php`, `dashboard-shell.js`, `filters.js` | [[shell-do-dashboard]] |
| Componentes visuais | Frontend | A definir | concluído | `dashboard/includes/components.php`, `includes/icons.php` | [[componentes-visuais]] |
| Gráficos (Chart.js) | Frontend | A definir | em andamento | `charts.js` (620 linhas), `assets/vendor/chartjs/` | [[graficos-chartjs]] |
| Mapas (Leaflet) | Frontend | A definir | concluído | `maps.js`, `maps-page.js`, `assets/vendor/leaflet/`, `dashboard/mapas.php` | [[mapas-leaflet]] |
| Estados de carregamento e erro | Frontend | A definir | concluído | `aq_states()`, `AqShell.setState()`, `data-content` nas 14 telas | [[estados-de-carregamento-e-erro]] |
| Responsividade | Frontend | A definir | concluído | `dashboard-responsive.css`, capturas 1920/1440/375 | [[responsividade]] |
| Telas de monitoramento | Frontend | A definir | concluído | 8 páginas em `dashboard/monitoramento/`, `monitor-page.js`, 8 capturas | [[telas-de-monitoramento]] |
| Visão geral e telas gerais | Frontend | A definir | concluído | `dashboard/index.php` + 5 páginas, 7 capturas | [[visao-geral-e-telas-gerais]] |

## Backend — 10 entregas

| Entrega | Função principal | Responsável | Status | Evidência | Nota |
|---|---|---|---|---|---|
| Autenticação | Backend | A definir | concluído | `AuthService.php`, `api/v1/auth/` (3 endpoints), `docs/api-contract.md` | [[autenticacao]] |
| Controle de sessão | Backend | A definir | concluído | `config/session.php`, `bootstrap.php`, `Guard.php` | [[controle-de-sessao]] |
| Endpoints da API | Backend | A definir | concluído | 18 arquivos em `api/v1/`, `_boot.php`, `docs/api-monitoring.md` | [[endpoints-da-api]] |
| Serviços de monitoramento | Backend | A definir | concluído | `MonitoringService.php` (595), `OverviewService.php` (263) | [[servicos-de-monitoramento]] |
| Regras de situação operacional | Backend | A definir | concluído | `StatusRules.php` (138 linhas) | [[regras-de-situacao-operacional]] |
| Validação das requisições | Backend | A definir | concluído | `Validator.php`, allowlists e erros documentados | [[validacao-de-requisicoes]] |
| Envelopes e tratamento de erros | Backend | A definir | concluído | `ApiResponse.php`, `JsonResponse.php`, handlers em `bootstrap.php` | [[envelopes-e-tratamento-de-erros]] |
| Previsão de duração da água | Backend | A definir | demonstrativo | `DurationForecastService.php` (181), marcado no próprio arquivo | [[previsao-de-duracao-da-agua]] |
| Relógio da aplicação | Backend | A definir | demonstrativo | `Clock.php`, `DEMO_MODE = true` | [[relogio-da-aplicacao]] |
| Repositório de dados simulados | Backend | A definir | demonstrativo | `MockMonitoringRepository.php` (384), `storage/mock/` (402), `docs/mock-data.md` | [[repositorio-de-dados-simulados]] |

## Banco de Dados — 4 entregas

| Entrega | Função principal | Responsável | Status | Evidência | Nota |
|---|---|---|---|---|---|
| Contrato do repositório | Banco de Dados | A definir | concluído | `MonitoringRepositoryInterface.php` (15 métodos), `UserRepositoryInterface.php` (2) | [[contrato-do-repositorio]] |
| Planejamento das entidades | Banco de Dados | A definir | concluído | `docs/database-handoff.md`, seção "Entidades e campos esperados" | [[planejamento-das-entidades]] |
| Documentação de integração | Banco de Dados | A definir | concluído | `docs/database-handoff.md`, `docs/api-contract.md`, `docs/mock-data.md` | [[documentacao-de-integracao]] |
| Ponto de substituição | Banco de Dados | A definir | **pendente** | `Container.php` marcado como ponto único de troca — implementação não iniciada | [[ponto-de-substituicao]] |

## Analista de Qualidade — 6 entregas

| Entrega | Função principal | Responsável | Status | Evidência | Nota |
|---|---|---|---|---|---|
| Validação das telas | Analista de Qualidade | A definir | concluído | 15 capturas em `validation/screenshots/dashboard/` | [[validacao-das-telas]] |
| Testes de responsividade | Analista de Qualidade | A definir | concluído | 11 capturas em `validation/screenshots/` | [[testes-de-responsividade]] |
| Testes de autenticação | Analista de Qualidade | A definir | concluído | `validation/auth-test-results.txt`, 13 casos | [[testes-de-autenticacao]] |
| Testes dos endpoints | Analista de Qualidade | A definir | **parcial** | auth com log; sistema só com casos documentados em `docs/api-monitoring.md` | [[testes-dos-endpoints]] |
| Erros encontrados nos gráficos | Analista de Qualidade | A definir | em andamento | 12 defeitos, diagnosticados nos comentários dos 22 arquivos alterados | [[erros-encontrados-nos-graficos]] |
| Registro de correções | Analista de Qualidade | A definir | em andamento | diff de 22 arquivos (+467 / -84), ainda não commitado | [[registro-de-correcoes]] |

## Analista de Negócios — 6 entregas

| Entrega | Função principal | Responsável | Status | Evidência | Nota |
|---|---|---|---|---|---|
| Requisitos do sistema | Analista de Negócios | A definir | concluído | `PROMPT-CLAUDE-CODE.md` (743 linhas), `LEIA-ME.md` | [[requisitos-do-sistema]] |
| Definição das telas | Analista de Negócios | A definir | concluído | `MAPA-DE-REFERENCIAS.md`, 15 referências visuais | [[definicao-das-telas]] |
| Indicadores monitorados | Analista de Negócios | A definir | concluído | campos âncora em `monitoring.php`, `metric` na interface | [[indicadores-monitorados]] |
| Critérios operacionais | Analista de Negócios | A definir | concluído | constantes de `StatusRules.php` "conforme especificação da etapa" | [[criterios-operacionais]] |
| Regras de seleção de empresa e represa | Analista de Negócios | A definir | concluído | `PROMPT-CLAUDE-CODE.md` "comportamento dos filtros", `filters.js` | [[regras-de-selecao-de-empresa-e-represa]] |
| Conteúdo institucional | Analista de Negócios | A definir | concluído | constantes em `includes/config.php` | [[conteudo-institucional]] |

## Granmaster — 6 entregas

| Entrega | Função principal | Responsável | Status | Evidência | Nota |
|---|---|---|---|---|---|
| Arquitetura em camadas | Granmaster | A definir | concluído | `backend/.htaccess`, `_boot.php`, `docs/login-stage.md` | [[arquitetura-em-camadas]] |
| Integração entre as áreas | Granmaster | A definir | concluído | os três contratos em `docs/`, `Container.php` | [[integracao-entre-areas]] |
| Organização em etapas | Granmaster | A definir | em andamento | `README.md`, `docs/login-stage.md`, commits `8db3bde`, `5810aec`, `996e2a5` | [[organizacao-em-etapas]] |
| Critérios de aceite | Granmaster | A definir | concluído | `PROMPT-CLAUDE-CODE.md`, seção "critérios de aceite" (14 itens) | [[criterios-de-aceite]] |
| Coordenação do backlog | Granmaster | A definir | em andamento | `login-stage.md` (8 itens), `database-handoff.md` (7 itens), avisos nas telas | [[coordenacao-do-backlog]] |
| Pipeline de publicação | Granmaster | A definir | concluído | `.github/workflows/deploy.yml`, `CNAME`, commits `56e21f5`, `17385b3` | [[pipeline-de-publicacao]] |

## Resumo por situação

| Situação | Quantidade |
| --- | --- |
| Concluído | 32 |
| Em andamento | 5 |
| Demonstrativo por decisão | 3 |
| Parcial | 1 |
| Pendente | 1 |
| **Total** | **42** |

## Resumo por função

| Função | Entregas |
| --- | --- |
| [[frontend]] | 10 |
| [[backend]] | 10 |
| [[analista-de-qualidade]] | 6 |
| [[analista-de-negocios]] | 6 |
| [[granmaster]] | 6 |
| [[banco-de-dados]] | 4 |

## Relacionadas

[[equipe]] · [[status-atual]] · [[tarefas]] · [[legenda-do-grafico]] · [[00-inicio]]
