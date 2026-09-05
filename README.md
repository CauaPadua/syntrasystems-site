# Aquapulse — landing page, login e sistema interno

- **Etapa 1** — landing page institucional pública (`index.php`).
- **Etapa 2** — tela de login com autenticação PHP funcional (`login.php` + `api/v1/auth/`
  + `backend/`), sustentada por um repositório **simulado**.
- **Etapa 3** — sistema interno completo (`dashboard/` + `api/v1/`), com 14 telas,
  17 endpoints e dados **simulados e determinísticos**. Sem banco de dados.

Documentação:

| Documento | Assunto |
| --- | --- |
| [`docs/login-stage.md`](docs/login-stage.md) | etapa do login |
| [`docs/api-contract.md`](docs/api-contract.md) | contrato da API de autenticação |
| [`docs/api-monitoring.md`](docs/api-monitoring.md) | contrato da API do sistema interno |
| [`docs/mock-data.md`](docs/mock-data.md) | como os dados simulados funcionam |
| [`docs/database-handoff.md`](docs/database-handoff.md) | guia para a equipe de banco de dados |


## Stack

PHP (apenas para estruturar e servir a página), HTML semântico, CSS e JavaScript
puro. Sem frameworks, bundlers, dependências ou banco de dados.

## Como executar

Com o PHP disponível no PATH, a partir da raiz do projeto:

```bash
php -S localhost:8000
```

Depois acesse <http://localhost:8000>.

Se o PHP não estiver no PATH (instalação via XAMPP no Windows, por exemplo):

```powershell
& C:\xampp\php\php.exe -S localhost:8000 -t .
```

Também funciona ao colocar a pasta em `htdocs/` do Apache/XAMPP.

## Estrutura

```
index.php                       documento principal
includes/
  config.php                    textos, navegação e helpers de escape
  icons.php                     biblioteca de ícones SVG (traço linear)
  header.php                    cabeçalho + navegação (com menu mobile)
  footer.php                    rodapé
  sections/
    hero.php                    seção 1 — hero
    informacoes.php             seção 2 — por que monitorar represas
    sistema.php                 seção 3 — como o Aquapulse apoia a operação
    vantagens.php               seção 4 — vantagens + chamada final
login.php                       tela de login (front-end)

dashboard/                      SISTEMA INTERNO (etapa 3)
  index.php                     visão geral (consolidada e por represa)
  relatorios.php  niveis.php  mapas.php  alertas.php  configuracoes.php
  monitoramento/                as oito telas de monitoramento
    vazao.php  nivel.php  ph.php  volume.php
    precipitacao.php  duracao.php  operacional.php  comparativo.php
  includes/
    page.php                    shell comum (sidebar, topo, menu, scripts)
    components.php              KPI, card, gráfico, tabela, badge, estados

api/v1/
  auth/                         login, logout, me (etapa 2)
  companies.php  reservoirs.php  overview.php
  settings.php  reports.php  alerts.php
  map/reservoirs.php
  monitoring/                   os oito endpoints de monitoramento
  _boot.php                     sessão, cabeçalhos e container compartilhados

backend/
  src/Contracts/                MonitoringRepositoryInterface (ponto de troca)
  src/Repositories/Mock/        implementação simulada
  src/Services/                 regras de negócio (status, visão geral, previsão)
  src/Support/                  relógio, validação, resposta, container, guarda
  storage/mock/                 dados simulados (fora da pasta pública)

assets/
  css/style.css                 landing e login
  css/dashboard.css             sistema interno (design tokens + componentes)
  css/dashboard-responsive.css  pontos de quebra do sistema interno
  js/                           cliente da API, gráficos, mapas, shell, telas
  vendor/                       Chart.js e Leaflet locais (sem CDN)
  images/                       imagens públicas otimizadas

docs/                           contratos da API e guias de etapa
reference/                      material de referência original (não alterado)
validation/screenshots/         capturas de validação
```

## Observações

- Os botões **Entrar** e **Solicitar demonstração** (bloco final) estão apenas
  preparados visualmente: exibem um aviso de indisponibilidade e não apontam
  para páginas ou back-ends inexistentes.
- O painel exibido na seção "sistema" é uma **prévia ilustrativa em imagem**,
  não um dashboard funcional.
- O sistema interno (`dashboard/`) exige sessão: sem login, a página redireciona
  para `login.php` e a API responde **401 em JSON**.
- Os dados do sistema interno são **simulados e determinísticos** — atualizar a
  página não muda os números. Ver [`docs/mock-data.md`](docs/mock-data.md).
- **Não há banco de dados** em nenhuma etapa. A troca está preparada por
  interface: ver [`docs/database-handoff.md`](docs/database-handoff.md).

## Evidências de validação

A pasta `validation/screenshots/` guarda as capturas usadas na revisão visual
(geradas com Chrome headless sobre o servidor local):

| Arquivo | Conteúdo |
| --- | --- |
| `desktop-1920-full.png` | página completa em 1920px (1920×4296) |
| `hero-1920-centered.png` | hero em 1920px, com o container ampliado |
| `desktop-1440-full.png` | página completa em 1440px (1440×4293) |
| `mobile-375-full.png` | página completa em 375px (375×8015) |
| `hero-1440.png` | hero em 1440px |
| `informacoes-1440.png` | seção de informações em 1440px |
| `sistema-1440.png` | seção do sistema em 1440px |
| `vantagens-1440.png` | seção de vantagens em 1440px |
| `hero-mobile-375.png` | hero em 375px |
