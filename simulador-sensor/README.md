# Simulador de sensores

Programa **independente** do site e da estação de telemetria. Faz o papel dos
**sensores físicos** instalados nas represas, sem precisar comprar equipamento. Ele só
**gera leituras e as disponibiliza em um endpoint local**; quem vem buscá-las é a
[estação de telemetria](../estacao/README.md). O simulador não grava no banco.

```
simulador de sensores  ──(a estação busca a cada 5 s)──▶  estação de telemetria  ──▶  API  ──▶  banco  ──▶  painel
 simulador-sensor/                                         estacao/
 os sensores geram valores                                 coleta, guarda e envia
```

**Os sensores vêm do cadastro do sistema.** Ao ligar, o simulador lê os equipamentos
ativos (tabela `sensors`, pela mesma API e chave da estação) e **começa de onde o banco
parou**: o último valor gravado de cada grandeza. Assim simulador, banco e painel partem
dos mesmos números. Equipamento cadastrado no CRUD passa a ser simulado depois do botão
**"Sincronizar com o sistema"**. Se o site estiver desligado, o simulador usa uma lista
padrão e tenta ler o cadastro de novo a cada 20 s.

Com o cadastro de exemplo são **18 sensores**, 6 por represa:

| Tipo | Santa Clara | Rio Verde | Serra Azul |
| --- | --- | --- | --- |
| Nível do reservatório | `SEN-NIV-01` | `SEN-NIV-11` | `SEN-NIV-21` |
| Vazão que entra | `SEN-VAZ-01` | `SEN-VAZ-11` | `SEN-VAZ-21` |
| Vazão que sai | `SEN-VAZ-02` | `SEN-VAZ-12` | `SEN-VAZ-22` |
| pH da água | `SEN-PH-01` | `SEN-PH-11` | `SEN-PH-21` |
| Temperatura da água | `SEN-TMP-01` | `SEN-TMP-11` | `SEN-TMP-21` |
| Chuva (pluviômetro) | `SEN-PLU-01` | `SEN-PLU-11` | `SEN-PLU-21` |

(`SEN-VAZ-03` está "em manutenção" no cadastro e por isso não é simulado.)

## Como abrir

O **`iniciar.bat`** (raiz do projeto) já abre o simulador, na janela **"Simulador de
sensores"**, e a tela em <http://localhost:8100/>. Deixe a janela aberta: fechá-la
desliga os sensores.

Sozinho: dois cliques em `simulador-sensor\abrir-sensor.bat` (precisa do XAMPP em
`C:\xampp`). Se o simulador já estiver ligado, o `.bat` só abre a tela. Cada vez que o
simulador é ligado, os sensores recomeçam dos últimos valores do banco.

## A tela

- **Lista à esquerda:** os sensores agrupados por represa, com o valor atual e a
  situação (Normal, Atenção, Crítico, Pausado, Sem resposta, Valor impossível).
- **Centro:** o sensor escolhido: valor atual, mínimo, máximo e média da sessão e o
  gráfico das últimas 60 leituras (passe o mouse para ver cada ponto). As linhas
  tracejadas são os limites de alerta; pontos vermelhos são valores impossíveis.
- **Execução:** Iniciar, Pausar e Reiniciar.
- **Simular falha:** ⚠ Sem resposta, ⚠ Valor impossível, ✔ Corrigir.
- **Modo automático:** o valor varia sozinho a cada leitura.
- **Modo manual:** o sensor repete o valor escolhido. Os atalhos provocam alertas (nível
  crítico, pH ácido, chuva de 5 mm por leitura...) e são calculados dos limites de cada
  sensor, lidos do sistema. Mudou um limite no painel? Clique em "Sincronizar com o sistema".
- **Configuração:** faixa, variação por leitura e intervalo (1 a 60 s).
- **Direita:** se a estação está buscando as leituras, o botão "Sincronizar com o
  sistema", o fluxo e o log de eventos.
- **Topo, "Todos":** inicia, pausa ou reinicia todos de uma vez.

## As falhas e o que o sistema faz com elas

| Na tela do simulador | O que o sensor faz | O que acontece no sistema |
| --- | --- | --- |
| **⚠ Sem resposta** | para de medir | a estação mostra `sem leitura: SEN-X: sem resposta`; em **2 min** o equipamento fica **"Sem comunicação"** em *Equipamentos* e abre o alerta **"Equipamento sem leituras"** |
| **✔ Corrigir** (depois de "sem resposta") | volta a medir | volta a "Online"; a linha do tempo do alerta registra "Equipamento voltou a transmitir" (o alerta fica aberto até o operador resolver) |
| **⚠ Valor impossível** | a cada 3 leituras manda **-5** | a estação envia, a API **recusa** (`422 VALUE_OUT_OF_RANGE`) e mostra `RECUSADA` na janela da estação; o valor **não entra no banco** e as leituras boas continuam sendo gravadas |
| **❚❚ Pausar** | para de medir (por escolha) | igual a "sem resposta" |

E para provocar alertas de valor:

| Na tela do simulador | O que acontece no sistema |
| --- | --- |
| `SEN-NIV-01` → **87 % · crítico** | a estação grava 87 % (a Santa Clara verte em 85 %) e o alerta de nível fica **crítico** |
| `SEN-PLU-01` → **5 mm · temporal** | a chuva acumulada em 24 h passa de 20 mm (atenção) e de 60 mm (crítico) em menos de 1 min |
| `SEN-PH-01` → **6,00 · ácido** | o pH sai da faixa ideal (6,5 a 8,5) e abre alerta de pH |

## O algoritmo

```
Iniciar cada sensor com o último valor gravado no banco
Enquanto a simulação estiver ativa, a cada intervalo (5 s):
    modo automático:  valor = valor anterior + número aleatório entre -variação e +variação
                      limitado à faixa configurada
                      (pluviômetro: em cada leitura há uma chance de chover de 0,2 a 0,8 mm)
    modo manual:      valor = o escolhido na tela
    registra código, valor e horário
    disponibiliza a leitura para a estação
```

Variação padrão por leitura: nível ±0,1 %, vazões ±0,3 a 0,4 m³/s, pH ±0,02,
temperatura ±0,05 °C, chuva com 3 % de chance por leitura.

O código está em `src/Sensores.php`. Detalhe de implementação: o servidor do PHP atende
um pedido por vez e não tem tarefa em segundo plano, então, a cada pedido, cada sensor
produz as leituras cujo horário já chegou, **uma por intervalo e cada uma com o seu
horário exato**. Para quem consulta, é igual a sensores que medem sozinhos.

## O endpoint que a estação consulta

```
GET http://localhost:8100/api/leituras?apos=N
```

`apos` é o número da última leitura que a estação já recebeu; o simulador devolve só as
seguintes, de todos os sensores, e a situação de cada um:

```json
{ "ok": true, "execucao": "9cc106dc", "ultimo_seq": 56,
  "sensores": { "SEN-NIV-01": "ok", "SEN-PH-21": "falha", "...": "..." },
  "atuais":   { "SEN-NIV-01": 82.4, "SEN-PH-21": 7.3, "...": "..." },
  "leituras": [ { "seq": 55, "code": "SEN-NIV-01", "value": 82.4, "unit": "%",    "recorded_at": "2026-11-19T13:01:58-03:00" },
                { "seq": 56, "code": "SEN-VAZ-01", "value": 56.4, "unit": "m³/s", "recorded_at": "2026-11-19T13:01:58-03:00" } ] }
```

`execucao` muda cada vez que o simulador é ligado: a estação percebe e recomeça a contagem.
Com `&sensor=SEN-NIV-01`, responde só aquele sensor e devolve **HTTP 503** se ele estiver
pausado ou sem resposta. Outros endereços: `GET /api/estado?sensor=COD` (o que a tela
mostra) e `POST /api/comando` (os botões da tela).

## Se o painel mostrar valores diferentes do simulador

| O que aparece | Causa | Solução |
| --- | --- | --- |
| Simulador: "Aguardando a estação" | a estação não está ligada: nada chega ao banco | abra o `iniciar.bat` (ele liga a estação) |
| Painel: "Última leitura há X dias", gráficos vazios | o projeto ficou dias desligado | o `iniciar.bat` completa sozinho; ou `migrate.php completar` |
| Simulador: "lista padrão (sistema desligado)" | o site não estava ligado quando o simulador abriu | ligue o site; ele sincroniza sozinho em até 20 s (ou "Sincronizar com o sistema") |
| Diferença por alguns segundos | o painel atualiza a cada 15 s | espere a próxima atualização |
