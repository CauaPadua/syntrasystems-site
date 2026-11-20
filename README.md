# Aquapulse — monitoramento de represas

Site institucional, login e painel de monitoramento de represas, em PHP, MySQL,
HTML, CSS e JavaScript puro (sem frameworks).

## O fluxo do projeto

```
simulador de sensores  →  estação de telemetria  →  API de ingestão  →  banco de dados  →  painel
(os sensores da represa)  (coleta, guarda, envia)   (recebe e valida)   (readings, alerts)  (gráficos e alertas)
 simulador-sensor/          estacao/                  api/v1/ingest/      MySQL               dashboard/
```

1. **Simulador de sensores** (`simulador-sensor/`) faz o papel dos 18 sensores
   físicos das 3 represas (nível, vazão que entra e que sai, pH, temperatura e chuva).
   Tem tela própria, onde dá para mudar valores e simular falhas. Os sensores vêm do
   cadastro do banco e começam do último valor gravado.
2. **Estação de telemetria** (`estacao/`) faz o papel do equipamento da barragem: a
   cada 5 s busca as leituras novas dos sensores, guarda na memória e envia um pacote
   JSON à API. Se a comunicação cair, guarda e envia depois, sem perder nada.
3. **API** (`api/v1/ingest/batch.php`) confere a chave da estação e, em cada
   leitura, o horário, a faixa do valor e se o equipamento está ativo, e grava.
4. **Banco** guarda a leitura em `readings`. Se o valor passou de um limite
   (`operational_limits`), abre um alerta em `alerts`. Se um equipamento parar de
   enviar por 2 min, abre o alerta "Equipamento sem leituras".
5. **Painel** (`dashboard/`) busca os dados a cada 15 s e mostra leituras, gráficos e alertas.

Peça por peça: [simulador-sensor/README.md](simulador-sensor/README.md) e
[estacao/README.md](estacao/README.md).

## Como rodar (XAMPP)

1. No painel do XAMPP, clique em **Start** no **MySQL** (e no **Apache**, só se for abrir o
   phpMyAdmin).
2. Dê dois cliques em **`iniciar.bat`**. Ele:
   - cria o `backend/.env` e o banco `aquapulse_dev` na primeira vez;
   - completa o histórico até agora, se o projeto ficou dias desligado;
   - abre a janela **"Simulador de sensores"** e a tela dele no navegador;
   - abre a janela **"Estação de telemetria"**;
   - liga o site nesta janela e abre o login no navegador.

Deixe as três janelas abertas. Para desligar, feche-as.

| O quê | Endereço | Pasta | Janela |
| --- | --- | --- | --- |
| Site e login | <http://localhost:8000/login.php> | raiz do projeto | "Aquapulse - site" |
| Painel | <http://localhost:8000/dashboard/> | `dashboard/` | (a mesma) |
| API que recebe as leituras | <http://localhost:8000/api/v1/ingest/batch.php> | `api/v1/ingest/` | (a mesma) |
| Simulador de sensores | <http://localhost:8100> | `simulador-sensor/` | "Simulador de sensores" |
| Estação de telemetria | não tem tela: lê de 8100 e envia para 8000 | `estacao/` | "Estação de telemetria" |
| Banco de dados | MySQL na porta 3306, banco `aquapulse_dev` | `backend/database/` | XAMPP |
| phpMyAdmin | <http://localhost/phpmyadmin> (precisa do Apache) | — | XAMPP |

### Contas de acesso

Todas usam a senha do usuário de demonstração.

| E-mail | Perfil | O que pode |
| --- | --- | --- |
| `demo@aquapulse.local` | Administrador | vê todas as empresas e cadastra equipamentos |
| `operador@aquapulse.local` | Operador | vê só a Hidrovale; trata alertas e abre chamados |
| `suspenso@aquapulse.local` | Operador suspenso | não consegue entrar |

### Comandos da estação

O `iniciar.bat` já liga a estação. Para usar uma opção abaixo, feche antes a janela
"Estação de telemetria" (só pode haver uma ligada por vez) e rode, na pasta do projeto:

```powershell
& C:\xampp\php\php.exe estacao/estacao.php --represa=santa-clara --pacote   # mostra o JSON de cada envio
& C:\xampp\php\php.exe estacao/estacao.php --queda-rede                     # perde o sinal por 8 ciclos e depois envia o que guardou
& C:\xampp\php\php.exe estacao/estacao.php --ajuda
```

Para testar a validação sem fechar a estação:
`& C:\xampp\php\php.exe estacao/estacao.php --equipamento=SEN-PH-01 --valor=15` (a API recusa).

## Onde está cada coisa

```
index.php, login.php        site institucional e tela de login
includes/                   partes do site institucional (cabeçalho, seções, ícones)

dashboard/                  telas do painel
  index.php                 visão geral
  equipamentos.php          CRUD de equipamentos (mostra quem está "Sem comunicação")
  alertas.php  relatorios.php  niveis.php  mapas.php  configuracoes.php
  monitoramento/            vazão, nível, pH, volume, precipitação, operação, comparativo
  includes/                 layout comum (menu, topo) e componentes

api/v1/                     endpoints (respondem JSON)
  ingest/                   usados pela estação: equipment.php (cadastro) e batch.php (pacote de leituras)
  equipment.php             CRUD de equipamentos
  alerts.php  reports.php   tratar alertas e gerar relatórios
  monitoring/               dados de cada tela de monitoramento

backend/
  src/Services/             regras: alertas (inclusive comunicação), ingestão, limites, relatórios, telas
  src/Repositories/         SQL (PdoMonitoringRepository, EquipmentRepository...)
  src/Support/              conexão, relógio, sessão, validação, respostas
  database/migrations/      criação das tabelas e das views (um arquivo por tabela)
  database/seeds/           dados iniciais (empresas, represas, equipamentos, limites)
  database/consultas_exemplo.sql   exemplos de SELECT, JOIN, subconsulta, views e CRUD
  database/testar_banco.php        testes do banco
  database/migrate.php             instalador
  storage/mock/             dados usados só quando não há banco configurado

simulador-sensor/           os sensores (programa à parte, tela em http://localhost:8100)
estacao/                    a estação de telemetria
  esp32/                    a mesma estação para uma placa ESP32 com sensores reais
iniciar.bat                 liga tudo com um duplo clique
assets/                     CSS, JavaScript das telas, imagens e bibliotecas (Chart.js, Leaflet)
```

## Banco de dados

11 tabelas e 2 views:

| Tabela | Para quê |
| --- | --- |
| `users`, `user_company_access` | contas e quais empresas cada uma pode ver |
| `companies`, `reservoirs` | empresas e represas |
| `sensors` | equipamentos (tabela do CRUD; o simulador de sensores lê daqui) |
| `readings` | leituras enviadas pela estação |
| `alerts`, `alert_timeline` | alertas (de valor e de comunicação) e o histórico de tratamento |
| `operational_limits` | limites Normal / Atenção / Crítico |
| `reports`, `maintenances` | relatórios gerados e chamados de manutenção |

| View | Para quê |
| --- | --- |
| `vw_ultimas_medicoes` | última leitura de cada equipamento |
| `vw_resumo_represas` | valor atual de cada grandeza, por represa |

Comandos úteis (na pasta do projeto):

```powershell
& C:\xampp\php\php.exe backend/database/migrate.php status         # tabelas, views e quantidade de linhas
& C:\xampp\php\php.exe backend/database/testar_banco.php           # 37 testes: estrutura, CRUD, chaves, views, ingestão, comunicação
& C:\xampp\php\php.exe backend/database/migrate.php completar      # completa o histórico até agora, sem apagar nada
& C:\xampp\php\php.exe backend/database/migrate.php reset --force  # apaga o banco (depois rode install)
```

## Roteiro para a apresentação

1. **Banco:** `migrate.php status` mostra as tabelas e as views; `testar_banco.php` roda
   os testes; abrir `consultas_exemplo.sql` para explicar SELECT, JOIN, subconsulta,
   views e CRUD.
2. **Coleta:** explicar o fluxo acima. Na janela da estação, cada linha é um ciclo; com
   `--pacote` aparece o JSON enviado.
3. **Tempo real:** entrar como `demo@aquapulse.local`. O que aparece na tela do simulador
   chega ao banco em até 5 s e ao painel em até 15 s ("Última leitura há X s").
4. **Alerta de valor:** no simulador, `SEN-NIV-01` → **92 % · crítico** (nível crítico) ou
   `SEN-PLU-01` → **5 mm · temporal** (chuva acumulada). O alerta aparece em *Alertas*;
   assumir e resolver.
5. **Falha "sem resposta":** no simulador, escolher um sensor → **⚠ Sem resposta**. A
   estação mostra "sem leitura"; em 2 min o equipamento fica "Sem comunicação" em
   *Equipamentos* e abre o alerta **"Equipamento sem leituras"**. **✔ Corrigir**: a linha do
   tempo do alerta registra que ele voltou.
6. **Falha "valor impossível":** **⚠ Valor impossível** → a cada 3 leituras o sensor manda
   -5; a janela da estação mostra **RECUSADA 422 VALUE_OUT_OF_RANGE** e nada entra no banco
   (`SELECT COUNT(*) FROM readings WHERE value < 0` dá 0).
7. **CRUD:** em *Equipamentos*, cadastrar, editar, colocar em manutenção e excluir um
   equipamento (`--equipamento=SEN-VAZ-03 --valor=40` mostra a API recusando um
   equipamento em manutenção).
8. **Permissões:** entrar como operador (só vê a Hidrovale e não edita equipamentos) e
   tentar entrar com a conta suspensa.

## Publicação

Cada envio para o branch `main` publica o site na InfinityFree pelo GitHub Actions
(`.github/workflows/deploy.yml`). O simulador de sensores, a estação e o `iniciar.bat`
não são publicados: rodam só no computador. O arquivo `backend/.env` não vai para o
repositório; no servidor ele precisa ser criado à parte.
