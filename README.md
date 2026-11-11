# Aquapulse — monitoramento de represas

Site institucional, login e painel de monitoramento de represas, em PHP, MySQL,
HTML, CSS e JavaScript puro (sem frameworks).

## O fluxo do projeto

```
simulador  →  API de ingestão  →  banco de dados  →  painel
(equipamento)  (recebe e valida)    (readings)        (gráficos e alertas)
```

1. **Simulador** (`simulador/simulador.php`) faz o papel dos equipamentos: envia
   medições de nível, vazão, pH, chuva e temperatura, cada uma com o código do
   equipamento e o horário.
2. **API** (`api/v1/ingest/readings.php`) confere a chave do equipamento, o
   horário, a faixa do valor e se o equipamento está ativo, e grava a leitura.
3. **Banco** guarda a leitura em `readings`. Se o valor passou de um limite
   (`operational_limits`), um alerta é aberto em `alerts`.
4. **Painel** (`dashboard/`) busca os dados a cada 15 segundos e mostra as
   leituras, os gráficos e os alertas.

## Como rodar (XAMPP)

1. Inicie o **MySQL** no painel do XAMPP.
2. Copie `backend/.env.example` para `backend/.env` e preencha:
   ```
   DB_HOST=127.0.0.1
   DB_NAME=aquapulse_dev
   DB_USER=root
   DB_PASS=
   INGEST_API_KEY=uma-chave-qualquer
   ```
3. Crie o banco, as tabelas e os dados iniciais (a partir da raiz do projeto):
   ```powershell
   & C:\xampp\php\php.exe backend/database/migrate.php install
   ```
4. Suba o site:
   ```powershell
   & C:\xampp\php\php.exe -S localhost:8000
   ```
5. Abra <http://localhost:8000/login.php>.

Em outro terminal, ligue o simulador:

```powershell
& C:\xampp\php\php.exe simulador/simulador.php                       # todos os equipamentos, valores normais
& C:\xampp\php\php.exe simulador/simulador.php --cenario=alerta      # leva nível, pH e chuva para a faixa de alerta
& C:\xampp\php\php.exe simulador/simulador.php --represa=rio-verde   # só uma represa
& C:\xampp\php\php.exe simulador/simulador.php --equipamento=SEN-PH-01 --valor=15   # valor inválido: a API recusa
& C:\xampp\php\php.exe simulador/simulador.php --ajuda
```

### Contas de acesso

Todas usam a senha do usuário de demonstração.

| E-mail | Perfil | O que pode |
| --- | --- | --- |
| `demo@aquapulse.local` | Administrador | vê todas as empresas e cadastra equipamentos |
| `operador@aquapulse.local` | Operador | vê só a Hidrovale; trata alertas e abre chamados |
| `suspenso@aquapulse.local` | Operador suspenso | não consegue entrar |

## Onde está cada coisa

```
index.php, login.php        site institucional e tela de login
includes/                   partes do site institucional (cabeçalho, seções, ícones)

dashboard/                  telas do painel
  index.php                 visão geral
  equipamentos.php          CRUD de equipamentos
  alertas.php  relatorios.php  niveis.php  mapas.php  configuracoes.php
  monitoramento/            vazão, nível, pH, volume, precipitação, operação, comparativo
  includes/                 layout comum (menu, topo) e componentes

api/v1/                     endpoints chamados pelo painel (respondem JSON)
  ingest/                   recebe as leituras dos equipamentos (usado pelo simulador)
  equipment.php             CRUD de equipamentos
  alerts.php  reports.php   tratar alertas e gerar relatórios
  monitoring/               dados de cada tela de monitoramento

backend/
  src/Services/             regras: alertas, ingestão, limites, relatórios, telas
  src/Repositories/         SQL (PdoMonitoringRepository, EquipmentRepository...)
  src/Support/              conexão, relógio, sessão, validação, respostas
  database/migrations/      criação das tabelas e das views (um arquivo por tabela)
  database/seeds/           dados iniciais (empresas, represas, equipamentos, limites)
  database/consultas_exemplo.sql   exemplos de SELECT, JOIN, subconsulta, views e CRUD
  database/testar_banco.php        testes do banco
  database/migrate.php             instalador
  storage/mock/             dados usados só quando não há banco configurado

simulador/simulador.php     simulador de equipamentos
assets/                     CSS, JavaScript das telas, imagens e bibliotecas (Chart.js, Leaflet)
```

## Banco de dados

11 tabelas e 2 views:

| Tabela | Para quê |
| --- | --- |
| `users`, `user_company_access` | contas e quais empresas cada uma pode ver |
| `companies`, `reservoirs` | empresas e represas |
| `sensors` | equipamentos (tabela do CRUD) |
| `readings` | leituras enviadas pelos equipamentos |
| `alerts`, `alert_timeline` | alertas e o histórico de tratamento |
| `operational_limits` | limites Normal / Atenção / Crítico |
| `reports`, `maintenances` | relatórios gerados e chamados de manutenção |

| View | Para quê |
| --- | --- |
| `vw_ultimas_medicoes` | última leitura de cada equipamento |
| `vw_resumo_represas` | valor atual de cada grandeza, por represa |

Comandos úteis:

```powershell
& C:\xampp\php\php.exe backend/database/migrate.php status         # tabelas, views e quantidade de linhas
& C:\xampp\php\php.exe backend/database/testar_banco.php           # testes (CRUD, chaves, views, ingestão)
& C:\xampp\php\php.exe backend/database/migrate.php reset --force  # apaga o banco (depois rode install)
```

## Roteiro para a apresentação

1. **Banco:** `migrate.php status` mostra as tabelas e as views; abrir
   `consultas_exemplo.sql` para explicar SELECT, JOIN, subconsulta, views e CRUD.
2. **Fluxo:** entrar como `demo@aquapulse.local`, abrir *Monitoramento › Nível* e
   ligar o simulador. A cada 15 s a tela mostra a leitura nova ("Última leitura há X s").
3. **Alerta:** rodar o simulador com `--cenario=alerta`. O nível passa de 80% e
   depois de 90%, e os alertas aparecem em *Alertas*; assumir e resolver um alerta.
4. **Validação:** `--equipamento=SEN-PH-01 --valor=15` (fora da faixa) e
   `--equipamento=SEN-VAZ-03 --valor=40` (equipamento em manutenção) são recusados.
5. **CRUD:** em *Equipamentos*, cadastrar, editar, colocar em manutenção e excluir
   um equipamento.
6. **Permissões:** entrar como operador (só vê a Hidrovale e não edita equipamentos)
   e tentar entrar com a conta suspensa.

## Publicação

Cada envio para o branch `main` publica o site na InfinityFree pelo GitHub Actions
(`.github/workflows/deploy.yml`). O arquivo `backend/.env` não vai para o
repositório: no servidor ele precisa ser criado à parte.
