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
4. **Banco** guarda a leitura em `readings`. Se o valor passou de um limite, abre um
   alerta em `alerts`: no nível, a cota de alerta (Atenção) e a de vertimento (Crítico) da
   própria represa; no pH e na chuva, os limites de `operational_limits`. Se um
   equipamento parar de enviar por 2 min, abre o alerta "Equipamento sem leituras".
5. **Painel** (`dashboard/`) busca os dados a cada 15 s e mostra leituras, gráficos e alertas.

Peça por peça: [simulador-sensor/README.md](simulador-sensor/README.md) e
[estacao/README.md](estacao/README.md).

## Como rodar (XAMPP)

1. No painel do XAMPP, clique em **Start** no **MySQL** (e no **Apache**, só se for abrir o
   phpMyAdmin).
2. Dê dois cliques em **`iniciar.bat`**. Ele:
   - cria o `backend/.env` e o banco `aquapulse_dev` na primeira vez;
   - se o banco é de uma versão anterior do projeto, cria só as tabelas que faltam;
   - completa o histórico até agora, se o projeto ficou dias desligado;
   - abre a janela **"Simulador de sensores"** e a tela dele no navegador;
   - abre a janela **"Estação de telemetria"**;
   - liga o site nesta janela e abre a página inicial no navegador (o login fica no botão "Entrar").

Deixe as três janelas abertas. Para desligar, feche-as.

**Portas ocupadas:** o `iniciar.bat` confere as portas antes de ligar (`backend/portas.php`) e
nunca encerra nada do computador. Porta livre: usa. O Aquapulse já está ligado nela (janelas
abertas antes): não liga de novo, só abre as telas. Ocupada por outro programa: usa a próxima
livre (8001, 8002… / 8101, 8102…) e mostra o endereço na janela. Nos três casos, a página inicial e o
simulador abrem no navegador.

| O quê | Endereço | Pasta | Janela |
| --- | --- | --- | --- |
| Site e login (8000 ou a próxima livre) | <http://localhost:8000/login.php> | raiz do projeto | "Aquapulse - site" |
| Painel | <http://localhost:8000/dashboard/> | `dashboard/` | (a mesma) |
| API que recebe as leituras | <http://localhost:8000/api/v1/ingest/batch.php> | `api/v1/ingest/` | (a mesma) |
| Simulador de sensores (8100 ou a próxima livre) | <http://localhost:8100> | `simulador-sensor/` | "Simulador de sensores" |
| Estação de telemetria | não tem tela: lê de 8100 e envia para 8000 | `estacao/` | "Estação de telemetria" |
| Banco de dados | MySQL na porta 3306, banco `aquapulse_dev` | `backend/database/` | XAMPP |
| phpMyAdmin | <http://localhost/phpmyadmin> (precisa do Apache) | — | XAMPP |

### Contas de acesso

Todas usam a senha do usuário de demonstração.

| E-mail | Cargo | Represas |
| --- | --- | --- |
| `demo@aquapulse.local` | Administrador | todas |
| `gestor@aquapulse.local` | Gestor da represa | Serra Azul |
| `operador@aquapulse.local` | Operador | Santa Clara e Rio Verde |
| `suspenso@aquapulse.local` | Operador suspenso | não consegue entrar |

O **cargo** diz o que a pessoa pode fazer; as **represas** dizem o que ela pode ver. Uma conta
pode ter mais de uma represa. O servidor confere os dois em toda requisição (`Support/Guard.php`).

| | Administrador | Gestor | Operador |
| --- | :---: | :---: | :---: |
| Represas que vê | todas | as dele | as dele |
| Painel, gráficos, mapas, baixar relatórios | sim | sim | sim |
| Assumir e resolver alertas, abrir chamados | sim | sim | sim |
| Gerar relatórios | sim | sim | — |
| Cadastrar equipamentos | sim | só nas dele | — |
| Responder solicitações | sim | — | — |
| Cadastrar empresas, represas e contas | sim | — | — |

### Como alguém entra no sistema

- **Colaborador de uma represa já monitorada:** tela de login → **Solicitar acesso**. Informa
  matrícula, empresa, represa, gestor direto, o motivo e cria a própria senha. O administrador
  confere com a empresa e responde em **Solicitações**: aprovar cria a conta com o cargo e as
  represas marcadas; recusar exige um motivo. Antes da resposta, o login avisa "em análise".
- **Dono de uma represa nova:** página inicial → **Solicitar demonstração** (nome, e-mail,
  telefone, empresa, represa, cidade) e cria a própria senha. O administrador entra em contato e,
  em **Solicitações**, usa **Cadastrar empresa e represa deste pedido**: os formulários de
  Configurações já abrem com os dados do pedido e, ao salvar a represa, a tela volta ao pedido com
  ela marcada. **Aprovar** cria a conta de gestor com o e-mail e a senha do pedido. A represa
  aparece como **"Sem leituras"** até o primeiro sensor transmitir: cadastre os sensores em
  **Equipamentos**, feche as janelas do simulador e da estação e abra o `iniciar.bat` de novo, para
  lerem o cadastro. Pedidos de demonstração enviados antes de o formulário pedir senha não criam
  conta: crie-a em **Configurações › Usuários** e marque o pedido como atendido.

O e-mail de um pedido não é verificado: por isso quem aprova é o administrador, depois de confirmar
por telefone. O formulário público aceita um pedido em análise por e-mail e até 50 por hora.

Criar uma conta direto no banco (a senha é a da conta de demonstração):

```sql
INSERT INTO users (name, email, role, status, password_hash)
SELECT 'Maria Souza', 'maria.souza@aquapulse.local', 'operator', 'active', password_hash
  FROM users WHERE email = 'demo@aquapulse.local';
INSERT INTO user_reservoir_access (user_id, reservoir_id) VALUES (LAST_INSERT_ID(), 'santa-clara');
```

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
solicitar.php               pedido de acesso e de demonstração (público)
includes/                   partes do site institucional (cabeçalho, seções, ícones)

dashboard/                  telas do painel
  index.php                 visão geral
  equipamentos.php          CRUD de equipamentos (mostra quem está "Sem comunicação")
  alertas.php  relatorios.php  niveis.php  mapas.php
  configuracoes.php         empresas, represas, limites e contas (cadastro só do administrador)
  solicitacoes.php          pedidos recebidos (só administrador)
  monitoramento/            vazão, nível, pH, volume, precipitação, operação, comparativo
  includes/                 layout comum (menu, topo) e componentes

api/v1/                     endpoints (respondem JSON)
  ingest/                   usados pela estação: equipment.php (cadastro) e batch.php (pacote de leituras)
  public/request.php        recebe os pedidos de solicitar.php (único que grava sem login)
  requests.php              responder pedidos
  equipment.php             CRUD de equipamentos
  companies.php  reservoirs.php  users.php   cadastro de empresas, represas e contas
  limits.php                limites de pH e de chuva (só administrador)
  alerts.php  reports.php   tratar alertas e gerar relatórios
  monitoring/               dados de cada tela de monitoramento

backend/
  src/Services/             regras: alertas (inclusive comunicação), ingestão, limites, relatórios, telas
  src/Repositories/         SQL (PdoMonitoringRepository, EquipmentRepository...)
  src/Auth/                 login e limite de tentativas
  src/Support/              conexão, relógio, sessão, validação, numeração, respostas
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

14 tabelas e 2 views:

| Tabela | Para quê |
| --- | --- |
| `users`, `user_reservoir_access` | contas (cargo) e quais represas cada uma pode ver |
| `requests` | pedidos de acesso e de demonstração |
| `companies`, `reservoirs` | empresas e represas (as cotas da represa são os limites do nível) |
| `sensors` | equipamentos (tabela do CRUD; o simulador de sensores lê daqui) |
| `readings` | leituras enviadas pela estação |
| `alerts`, `alert_timeline` | alertas (de valor e de comunicação) e o histórico de tratamento |
| `operational_limits` | limites de pH e de chuva, mudados em *Configurações › Limites* |
| `sequences` | numeração dos alertas e relatórios, sem repetição |
| `login_attempts` | tentativas de login dos últimos 15 min (5 por e-mail, 20 por endereço) |
| `reports`, `maintenances` | relatórios gerados (com a observação que sai no arquivo) e chamados de manutenção |

| View | Para quê |
| --- | --- |
| `vw_ultimas_medicoes` | última leitura de cada equipamento |
| `vw_resumo_represas` | valor atual de cada grandeza, por represa |

Comandos úteis (na pasta do projeto):

```powershell
& C:\xampp\php\php.exe backend/database/migrate.php status         # tabelas, views e quantidade de linhas
& C:\xampp\php\php.exe backend/database/testar_banco.php           # 76 testes: estrutura, CRUD, chaves, views, ingestão, comunicação, acesso, limites, login, falhas, ajustes
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
4. **Alerta de valor:** no simulador, `SEN-NIV-01` → **87 % · crítico** (nível crítico) ou
   `SEN-PLU-01` → **5 mm · temporal** (chuva acumulada). O alerta aparece em *Alertas*;
   assumir e resolver. Resolvido, ele não volta enquanto o valor continua fora da faixa: só
   abre outro se o valor voltar ao normal e sair de novo, ou se piorar (atenção → crítico).
5. **Falha "sem resposta":** no simulador, escolher um sensor → **⚠ Sem resposta**. A
   estação mostra "sem leitura"; em 2 min o equipamento fica "Sem comunicação" em
   *Equipamentos* e abre o alerta **"Equipamento sem leituras"**. **✔ Corrigir**: a linha do
   tempo do alerta registra que ele voltou.
6. **Falha "valor impossível":** **⚠ Valor impossível** → a cada 3 leituras o sensor manda
   -5; a janela da estação mostra **RECUSADA 422 VALUE_OUT_OF_RANGE** e nada entra no banco
   (`SELECT COUNT(*) FROM readings WHERE value < 0` dá 0).
7. **CRUD:** em *Equipamentos*, cadastrar, mudar a situação (depois de cadastrado, só ela muda:
   o código e a grandeza identificam as leituras já enviadas) e excluir um equipamento (`--equipamento=SEN-VAZ-03 --valor=40` mostra a API recusando um
   equipamento em manutenção).
8. **Cargos:** entrar como gestor (só vê a Serra Azul e cadastra equipamentos nela) e como
   operador (só vê Santa Clara e Rio Verde, não cadastra nem gera relatório); tentar entrar com
   a conta suspensa.
9. **Pedido de acesso:** no login, **Solicitar acesso** e enviar; tentar entrar ("em análise").
   Como administrador, em *Solicitações*, aprovar escolhendo cargo e represa; entrar com a conta nova.
10. **Limites:** em *Configurações › Limites*, mostrar que cada represa tem o seu limite de nível
    (a Rio Verde fica crítica em 82,1 %; a Santa Clara, em 85 %) e mudar a faixa ideal do pH.
11. **Tentativas de login:** errar a senha 5 vezes; a 6ª é recusada por 15 min (use um e-mail que
    não seja o da apresentação).
12. **Represa nova:** em *Configurações*, cadastrar empresa, represa e conta de gestor; mostrar a
    represa "Sem leituras" na visão geral e no mapa.

## Publicação

Cada envio para o branch `main` roda os testes e, se todos passarem, publica o site na
InfinityFree por FTPS, pelo GitHub Actions (`.github/workflows/deploy.yml`). O simulador de sensores, a estação e o `iniciar.bat`
não são publicados: rodam só no computador. O arquivo `backend/.env` não vai para o
repositório; no servidor ele precisa ser criado à parte.

A publicação envia só os arquivos: **as tabelas novas não são criadas sozinhas no banco do
servidor**. Se o site publicado usa banco, importe pelo phpMyAdmin da hospedagem, em ordem,
as de `backend/database/migrations/` que ele ainda não tem. As mais novas são
`014_limites_por_represa.sql`, `015_sequencias.sql` e `016_tentativas_de_login.sql`: sem as duas
últimas, o login e a abertura de alertas não funcionam no servidor.
